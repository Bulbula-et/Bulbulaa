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

    /**
     * C-31 Store Email OTP strictly as HMAC-SHA256 hash.
     */
    public function storeOtpToken(string $email, string $codeHmacSha256, string $ipHash, int $ttlSeconds = 600): int
    {
        $normEmail = mb_strtolower(trim($email), 'UTF-8');
        $expiresAt = date('Y-m-d H:i:s', time() + $ttlSeconds);

        return Database::insert(
            'INSERT INTO customer_otp_tokens (email, code_hmac_sha256, attempt_count, requester_ip_hash, expires_at)
             VALUES (:email, :hash, 0, :ip, :exp)',
            [
                ':email' => $normEmail,
                ':hash'  => $codeHmacSha256,
                ':ip'    => $ipHash,
                ':exp'   => $expiresAt,
            ]
        );
    }

    public function findLatestActiveOtp(string $email): ?array
    {
        $normEmail = mb_strtolower(trim($email), 'UTF-8');
        return Database::fetchOne(
            'SELECT * FROM customer_otp_tokens
             WHERE email = :email
               AND consumed_at IS NULL
               AND expires_at >= :now
             ORDER BY id DESC
             LIMIT 1',
            [
                ':email' => $normEmail,
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
    public function createPrivacyRequest(int $customerId, string $email, string $requestType, ?string $notes = null): int
    {
        if ($requestType === 'ACCOUNT_DELETION') {
            Database::execute(
                "UPDATE customers SET account_status = 'PENDING_DELETION', updated_at = CURRENT_TIMESTAMP WHERE id = :id",
                [':id' => $customerId]
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
}
