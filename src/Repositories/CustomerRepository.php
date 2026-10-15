<?php
declare(strict_types=1);

namespace Bulbula\Repositories;

use Bulbula\Core\Database;

/**
 * Customer Accounts, Password-Free OTP Tokens, Saved Businesses & Privacy Repository (C-14, C-30..C-36).
 */
final class CustomerRepository
{
    public function findById(int $id): ?array
    {
        return Database::fetchOne(
            "SELECT * FROM customers WHERE id = :id AND account_status != 'DELETED' LIMIT 1",
            [':id' => $id]
        );
    }

    public function findByEmail(string $email): ?array
    {
        return Database::fetchOne(
            'SELECT * FROM customers WHERE LOWER(email) = :email LIMIT 1',
            [':email' => mb_strtolower(trim($email), 'UTF-8')]
        );
    }

    public function findOrCreateByEmail(string $email, ?string $displayName = null, ?string $googleSubId = null, string $surface = 'WEB'): array
    {
        $normEmail = mb_strtolower(trim($email), 'UTF-8');
        $existing  = $this->findByEmail($normEmail);

        if ($existing !== null) {
            Database::execute(
                'UPDATE customers
                 SET email_verified_at = COALESCE(email_verified_at, CURRENT_TIMESTAMP),
                     google_sub_id = COALESCE(google_sub_id, :gsub),
                     last_active_surface = :surf,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id',
                [
                    ':gsub' => $googleSubId,
                    ':surf' => $surface,
                    ':id'   => $existing['id'],
                ]
            );
            return (array) $this->findById((int) $existing['id']);
        }

        $fallbackName = $displayName ?: explode('@', $normEmail)[0];
        $newId = Database::insert(
            "INSERT INTO customers (email, display_name, google_sub_id, account_status, email_verified_at, last_active_surface)
             VALUES (:email, :name, :gsub, 'ACTIVE', CURRENT_TIMESTAMP, :surf)",
            [
                ':email' => $normEmail,
                ':name'  => mb_substr(trim($fallbackName), 0, 80, 'UTF-8'),
                ':gsub'  => $googleSubId,
                ':surf'  => $surface,
            ]
        );

        return (array) $this->findById($newId);
    }

    public function createWithPassword(string $email, string $displayName, string $passwordHash, string $surface = 'WEB'): array
    {
        $id = Database::insert(
            "INSERT INTO customers (email, display_name, password_hash, account_status, last_active_surface, password_changed_at)
             VALUES (:email, :name, :hash, 'ACTIVE', :surface, CURRENT_TIMESTAMP)",
            [':email' => mb_strtolower(trim($email), 'UTF-8'), ':name' => $displayName, ':hash' => $passwordHash, ':surface' => $surface]
        );
        return (array) $this->findById($id);
    }

    public function updateProfile(int $customerId, string $displayName, ?string $phoneNumber, string $language): void
    {
        Database::execute(
            'UPDATE customers SET display_name = :name, phone_number = :phone, preferred_language = :lang, updated_at = CURRENT_TIMESTAMP WHERE id = :id',
            [':name' => $displayName, ':phone' => $phoneNumber, ':lang' => $language, ':id' => $customerId]
        );
    }

    public function updatePassword(int $customerId, string $passwordHash): void
    {
        Database::execute(
            'UPDATE customers SET password_hash = :hash, password_changed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = :id',
            [':hash' => $passwordHash, ':id' => $customerId]
        );
    }

    public function emailBelongsToAnotherCustomer(string $email, int $customerId): bool
    {
        $row = Database::fetchOne('SELECT id FROM customers WHERE LOWER(email) = :email AND id <> :id LIMIT 1', [':email' => mb_strtolower(trim($email), 'UTF-8'), ':id' => $customerId]);
        return $row !== null;
    }

    public function updateEmail(int $customerId, string $email): void
    {
        Database::execute('UPDATE customers SET email = :email, email_verified_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = :id', [':email' => mb_strtolower(trim($email), 'UTF-8'), ':id' => $customerId]);
    }

    public function markEmailVerified(int $customerId): void
    {
        Database::execute(
            'UPDATE customers SET email_verified_at = COALESCE(email_verified_at, CURRENT_TIMESTAMP), updated_at = CURRENT_TIMESTAMP WHERE id = :id',
            [':id' => $customerId]
        );
    }

    /** Store Email OTP strictly as HMAC-SHA256 hash. */
    public function storeOtpToken(string $email, string $codeHmacSha256, string $ipHash, int $ttlSeconds = 600, string $purpose = 'SIGN_IN'): int
    {
        $normEmail = mb_strtolower(trim($email), 'UTF-8');
        $expiresAt = date('Y-m-d H:i:s', time() + $ttlSeconds);

        return Database::insert(
            'INSERT INTO customer_otp_tokens (email, purpose, code_hmac_sha256, attempt_count, requester_ip_hash, expires_at)
             VALUES (:email, :purpose, :hash, 0, :ip, :exp)',
            [
                ':email' => $normEmail,
                ':purpose' => $purpose,
                ':hash'  => $codeHmacSha256,
                ':ip'    => $ipHash,
                ':exp'   => $expiresAt,
            ]
        );
    }

    public function findLatestActiveOtp(string $email, string $purpose = 'SIGN_IN'): ?array
    {
        $normEmail = mb_strtolower(trim($email), 'UTF-8');
        return Database::fetchOne(
            'SELECT * FROM customer_otp_tokens
             WHERE email = :email
               AND purpose = :purpose
               AND consumed_at IS NULL
               AND expires_at >= :now
             ORDER BY id DESC
             LIMIT 1',
            [
                ':email' => $normEmail,
                ':purpose' => $purpose,
                ':now'   => date('Y-m-d H:i:s'),
            ]
        );
    }

    public function incrementOtpAttempts(int $otpId): void
    {
        Database::execute(
            'UPDATE customer_otp_tokens SET attempt_count = attempt_count + 1 WHERE id = :id',
            [':id' => $otpId]
        );
    }

    public function markOtpConsumed(int $otpId): void
    {
        Database::execute(
            'UPDATE customer_otp_tokens SET consumed_at = CURRENT_TIMESTAMP WHERE id = :id',
            [':id' => $otpId]
        );
    }

    /**
     * C-14, C-34 Toggle Saved Business for a Customer across Web & Telegram Mini App.
     *
     * @return bool True if now saved, false if unsaved.
     */
    public function toggleSavedBusiness(int $customerId, int $businessId, ?int $preferredBranchId = null): bool
    {
        $existing = Database::fetchOne(
            'SELECT customer_id FROM customer_saved_businesses WHERE customer_id = :cid AND business_id = :bid LIMIT 1',
            [':cid' => $customerId, ':bid' => $businessId]
        );

        if ($existing !== null) {
            Database::execute(
                'DELETE FROM customer_saved_businesses WHERE customer_id = :cid AND business_id = :bid',
                [':cid' => $customerId, ':bid' => $businessId]
            );
            return false;
        }

        Database::execute(
            'INSERT INTO customer_saved_businesses (customer_id, business_id, preferred_branch_id)
             VALUES (:cid, :bid, :brid)',
            [
                ':cid'  => $customerId,
                ':bid'  => $businessId,
                ':brid' => $preferredBranchId,
            ]
        );
        return true;
    }

    public function isBusinessSavedByCustomer(int $customerId, int $businessId): bool
    {
        $row = Database::fetchOne(
            'SELECT 1 FROM customer_saved_businesses WHERE customer_id = :cid AND business_id = :bid LIMIT 1',
            [':cid' => $customerId, ':bid' => $businessId]
        );
        return $row !== null;
    }

    /**
     * C-34 List Saved Businesses, handling unpublished/unavailable listings clearly.
     */
    public function listSavedBusinesses(int $customerId): array
    {
        return Database::fetchAll(
            'SELECT csb.created_at AS saved_at,
                    bz.id AS business_id, bz.slug AS business_slug, bz.name_en AS business_name_en,
                    bz.name_am AS business_name_am, bz.lifecycle_status,
                    br.id AS branch_id, br.branch_slug, br.branch_name_en, br.publication_status,
                    br.address_line_en, br.phone_primary, br.is_verified, br.published_rating_avg,
                    c.name_en AS primary_category_name_en,
                    a.name_en AS area_name_en
             FROM customer_saved_businesses csb
             INNER JOIN businesses bz ON bz.id = csb.business_id
             LEFT JOIN branches br ON br.id = COALESCE(
                 csb.preferred_branch_id,
                 (SELECT b2.id FROM branches b2 WHERE b2.business_id = bz.id ORDER BY b2.is_primary_branch DESC, b2.id ASC LIMIT 1)
             )
             LEFT JOIN categories c ON c.id = bz.primary_category_id
             LEFT JOIN areas a ON a.id = br.area_id
             WHERE csb.customer_id = :cid
             ORDER BY csb.created_at DESC',
            [':cid' => $customerId]
        );
    }

    /**
     * C-36 Submit Account Deletion / Privacy Request.
     */
    public function createPrivacyRequest(int $customerId, string $email, string $requestType, ?string $notes = null, ?string $reasonCode = null): int
    {
        if ($requestType === 'ACCOUNT_DELETION') {
            Database::execute(
                "UPDATE customers SET account_status = 'PENDING_DELETION', deletion_requested_at = CURRENT_TIMESTAMP, deletion_effective_at = :effective, deletion_reason_code = :reason, deletion_reason_detail = :detail, updated_at = CURRENT_TIMESTAMP WHERE id = :id",
                [':effective' => date('Y-m-d H:i:s', time() + 30 * 86400), ':reason' => $reasonCode, ':detail' => $notes, ':id' => $customerId]
            );
        }

        return Database::insert(
            "INSERT INTO privacy_requests (customer_id, requester_email, request_type, status, resolution_summary)
             VALUES (:cid, :email, :type, 'SUBMITTED', :notes)",
            [
                ':cid'   => $customerId,
                ':email' => $email,
                ':type'  => $requestType,
                ':notes' => $notes,
            ]
        );
    }

    public function restorePendingDeletion(int $customerId): void
    {
        Database::execute("UPDATE customers SET account_status='ACTIVE', deletion_requested_at=NULL, deletion_effective_at=NULL, deletion_reason_code=NULL, deletion_reason_detail=NULL, updated_at=CURRENT_TIMESTAMP WHERE id=:id AND account_status='PENDING_DELETION' AND deletion_effective_at>CURRENT_TIMESTAMP", [':id'=>$customerId]);
        Database::execute("UPDATE privacy_requests SET status='REJECTED_WITH_REASON', resolution_summary='Cancelled by Customer during the 30-day safety period.', completed_at=CURRENT_TIMESTAMP WHERE customer_id=:id AND request_type='ACCOUNT_DELETION' AND status IN ('SUBMITTED','IN_PROGRESS')", [':id'=>$customerId]);
    }

    public function listDueDeletionIds(int $limit = 100): array
    {
        $limit=max(1,min(500,$limit));return array_map('intval',array_column(Database::fetchAll("SELECT id FROM customers WHERE account_status='PENDING_DELETION' AND deletion_effective_at<=CURRENT_TIMESTAMP ORDER BY deletion_effective_at ASC LIMIT {$limit}"),'id'));
    }

    public function permanentlyErasePendingDeletion(int $customerId): void
    {
        Database::execute("UPDATE customers SET email=:email, display_name='Deleted Customer', google_sub_id=NULL, password_hash=NULL, password_changed_at=NULL, phone_number=NULL, preferred_language='en', account_status='DELETED', email_verified_at=NULL, deletion_reason_detail=NULL, updated_at=CURRENT_TIMESTAMP WHERE id=:id AND account_status='PENDING_DELETION'", [':email'=>'deleted+'.$customerId.'@privacy.invalid', ':id'=>$customerId]);
        Database::execute('DELETE FROM customer_saved_businesses WHERE customer_id=:id', [':id'=>$customerId]);
        Database::execute("UPDATE account_sessions SET revoked_at=CURRENT_TIMESTAMP WHERE actor_type='CUSTOMER' AND actor_id=:id AND revoked_at IS NULL", [':id'=>$customerId]);
        Database::execute("UPDATE privacy_requests SET status='COMPLETED', resolution_summary='Self-service deletion completed after safety period.', completed_at=CURRENT_TIMESTAMP WHERE customer_id=:id AND request_type='ACCOUNT_DELETION' AND status IN ('SUBMITTED','IN_PROGRESS')", [':id'=>$customerId]);
    }
}
