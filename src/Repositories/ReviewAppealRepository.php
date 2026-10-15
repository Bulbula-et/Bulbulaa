<?php
declare(strict_types=1);

namespace Bulbula\Repositories;

use Bulbula\Core\Database;

final class ReviewAppealRepository
{
    public function findForReview(int $reviewId): ?array
    {
        return Database::fetchOne('SELECT a.*,d.outcome,d.reason_code AS decision_reason_code,d.decision_notes,d.decided_by_admin_id,d.created_at AS decided_at FROM review_appeals a LEFT JOIN review_appeal_decisions d ON d.appeal_id=a.id WHERE a.review_id=:review LIMIT 1',[':review'=>$reviewId]);
    }
    public function findDetailed(int $appealId): ?array
    {
        return Database::fetchOne("SELECT a.*,d.outcome,d.decided_by_admin_id,r.branch_id,r.rating,r.review_text,r.status AS current_review_status,r.customer_id,c.display_name AS customer_name,br.branch_name_en,s.full_name AS original_moderator_name FROM review_appeals a INNER JOIN reviews r ON r.id=a.review_id INNER JOIN customers c ON c.id=a.customer_id INNER JOIN branches br ON br.id=r.branch_id LEFT JOIN staff_users s ON s.id=a.original_moderated_by_staff_id LEFT JOIN review_appeal_decisions d ON d.appeal_id=a.id WHERE a.id=:id LIMIT 1",[':id'=>$appealId]);
    }
    public function listAll(int $limit=100): array
    {
        $limit=max(1,min(200,$limit));
        return Database::fetchAll("SELECT a.*,d.outcome,d.decision_notes,d.created_at AS decided_at,r.rating,r.review_text,r.status AS current_review_status,c.display_name AS customer_name,br.branch_name_en,s.full_name AS original_moderator_name FROM review_appeals a INNER JOIN reviews r ON r.id=a.review_id INNER JOIN customers c ON c.id=a.customer_id INNER JOIN branches br ON br.id=r.branch_id LEFT JOIN staff_users s ON s.id=a.original_moderated_by_staff_id LEFT JOIN review_appeal_decisions d ON d.appeal_id=a.id ORDER BY (d.id IS NULL) DESC,a.created_at ASC LIMIT {$limit}");
    }
    public function create(array $data): int
    {
        return Database::insert('INSERT INTO review_appeals(review_id,customer_id,original_status,original_reason_code,original_moderated_by_staff_id,original_moderated_at,appeal_statement) VALUES(:review,:customer,:status,:reason,:moderator,:moderated,:statement)',[':review'=>$data['review_id'],':customer'=>$data['customer_id'],':status'=>$data['original_status'],':reason'=>$data['original_reason_code'],':moderator'=>$data['original_moderated_by_staff_id'],':moderated'=>$data['original_moderated_at'],':statement'=>$data['appeal_statement']]);
    }
    public function recordDecision(int $appealId,int $adminId,string $outcome,string $reason,string $notes): int
    {
        return Database::insert('INSERT INTO review_appeal_decisions(appeal_id,decided_by_admin_id,outcome,reason_code,decision_notes) VALUES(:appeal,:admin,:outcome,:reason,:notes)',[':appeal'=>$appealId,':admin'=>$adminId,':outcome'=>$outcome,':reason'=>$reason,':notes'=>$notes]);
    }
}
