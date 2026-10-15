<?php
declare(strict_types=1);

namespace Bulbula\Services;

use Bulbula\Core\Cache;
use Bulbula\Core\Database;
use Bulbula\Repositories\ReviewAppealRepository;
use Bulbula\Repositories\ReviewRepository;
use Bulbula\Repositories\StaffRepository;
use Throwable;

final class ReviewAppealService
{
    public function __construct(private readonly ReviewRepository $reviews=new ReviewRepository(),private readonly ReviewAppealRepository $appeals=new ReviewAppealRepository(),private readonly StaffRepository $staff=new StaffRepository(),private readonly AuditService $audit=new AuditService(),private readonly ReviewNotificationService $notifications=new ReviewNotificationService()){}
    public function submit(int $reviewId,int $customerId,string $statement): array
    {
        $review=$this->reviews->findById($reviewId);$statement=trim($statement);
        if($review===null||(int)$review['customer_id']!==$customerId)return ['ok'=>false,'error'=>'Review not found.'];
        if(!in_array((string)$review['status'],['REJECTED','REMOVED_BY_STAFF'],true))return ['ok'=>false,'error'=>'Only rejected or Staff-removed reviews are eligible for appeal.'];
        if(empty($review['moderated_at'])||strtotime((string)$review['moderated_at']) < strtotime('-180 days'))return ['ok'=>false,'error'=>'The 180-day appeal period has ended.'];
        if(mb_strlen($statement)<20||mb_strlen($statement)>2000)return ['ok'=>false,'error'=>'Explain the appeal in 20–2000 characters.'];
        if($this->appeals->findForReview($reviewId)!==null)return ['ok'=>false,'error'=>'This moderation decision already has an appeal.'];
        $pdo=Database::pdo();try{$pdo->beginTransaction();$id=$this->appeals->create(['review_id'=>$reviewId,'customer_id'=>$customerId,'original_status'=>$review['status'],'original_reason_code'=>$review['moderation_reason_code'],'original_moderated_by_staff_id'=>$review['moderated_by_staff_id'],'original_moderated_at'=>$review['moderated_at'],'appeal_statement'=>$statement]);$this->audit->logCustomerAction($customerId,'REVIEW_APPEAL_SUBMITTED','review_appeal',$id,['review_id'=>$reviewId,'original_status'=>$review['status']]);$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();return ['ok'=>false,'error'=>'Appeal could not be recorded.'];}
        $this->notifications->notify($reviewId,'APPEAL_SUBMITTED');return ['ok'=>true,'appeal_id'=>$id];
    }
    public function decide(int $appealId,int $adminId,string $outcome,string $reasonCode,string $notes): array
    {
        $appeal=$this->appeals->findDetailed($appealId);$outcome=strtoupper(trim($outcome));$reasonCode=strtoupper(trim($reasonCode));$notes=trim($notes);
        if($appeal===null)return ['ok'=>false,'error'=>'Appeal not found.'];if(!empty($appeal['outcome']))return ['ok'=>false,'error'=>'Appeal already has a final decision.'];
        if(!in_array($outcome,['UPHELD','OVERTURNED'],true)||preg_match('/^[A-Z0-9_]{3,64}$/',$reasonCode)!==1||mb_strlen($notes)<10)return ['ok'=>false,'error'=>'Select a valid outcome and provide a clear decision reason.'];
        $sameReviewer=(int)($appeal['original_moderated_by_staff_id']??0)===$adminId;
        if($sameReviewer&&$this->staff->hasOtherActiveAdministrator($adminId))return ['ok'=>false,'error'=>'A different active Administrator must decide this appeal.'];
        if($sameReviewer&&mb_strlen($notes)<20)return ['ok'=>false,'error'=>'Explain the controlled same-Administrator fallback.'];
        if($outcome==='OVERTURNED'){$other=Database::fetchOne("SELECT id FROM reviews WHERE branch_id=:branch AND customer_id=:customer AND id<>:review AND status IN ('PENDING_MODERATION','PUBLISHED','REJECTED') LIMIT 1",[':branch'=>$appeal['branch_id'],':customer'=>$appeal['customer_id'],':review'=>$appeal['review_id']]);if($other!==null)return ['ok'=>false,'error'=>'The original review cannot be restored while another active review exists for this Branch.'];}
        $pdo=Database::pdo();try{$pdo->beginTransaction();$decisionId=$this->appeals->recordDecision($appealId,$adminId,$outcome,$reasonCode,$notes);if($outcome==='OVERTURNED'){Database::execute("UPDATE reviews SET status='PUBLISHED',moderated_by_staff_id=:admin,moderated_at=CURRENT_TIMESTAMP,moderation_reason_code='APPEAL_OVERTURNED',moderation_notes=:notes WHERE id=:review",[':admin'=>$adminId,':notes'=>$notes,':review'=>$appeal['review_id']]);$this->reviews->recalculateBranchRatingSummary((int)$appeal['branch_id']);}$this->audit->logStaffAction($adminId,'REVIEW_APPEAL_'.$outcome,'review_appeal',$appealId,null,['review_id'=>(int)$appeal['review_id'],'decision_id'=>$decisionId,'outcome'=>$outcome,'reason_code'=>$reasonCode],$notes);$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();return ['ok'=>false,'error'=>'Appeal decision failed without changing the review.'];}
        Cache::invalidateTags(['reviews','branches']);$this->notifications->notify((int)$appeal['review_id'],'APPEAL_'.$outcome);return ['ok'=>true];
    }
}
