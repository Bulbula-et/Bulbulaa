<?php
declare(strict_types=1);

namespace Bulbula\Domain\Policies;

final class QcReviewPolicy
{
    /** @return array{allowed:bool,error?:string,checklist:array<string,bool|int>} */
    public static function evaluate(string $outcome, array $checklist, string $notes, int $preparerId, int $reviewerId, bool $otherReviewerAvailable): array
    {
        $outcome = strtoupper(trim($outcome));
        if (!in_array($outcome, ['PASSED','RETURNED_FOR_FIXES','REJECTED'], true)) return ['allowed'=>false,'error'=>'Select a supported QC outcome.','checklist'=>[]];
        $confirmed = [];
        foreach (['permission_verified','coordinates_checked','category_checked'] as $check) $confirmed[$check] = !empty($checklist[$check]);
        if ($outcome === 'PASSED' && in_array(false, $confirmed, true)) return ['allowed'=>false,'error'=>'Every required QC check must be explicitly confirmed before publication.','checklist'=>$confirmed];
        $notes = trim($notes);
        if ($outcome !== 'PASSED' && mb_strlen($notes) < 10) return ['allowed'=>false,'error'=>'Returned or rejected reviews require a clear reason of at least 10 characters.','checklist'=>$confirmed];
        $selfReview = $preparerId === $reviewerId;
        if ($selfReview && $otherReviewerAvailable) return ['allowed'=>false,'error'=>'Independent review is required because another active Staff reviewer is available.','checklist'=>$confirmed];
        if ($selfReview && mb_strlen($notes) < 10) return ['allowed'=>false,'error'=>'Explain why controlled self-review is necessary when no other reviewer is available.','checklist'=>$confirmed];
        $confirmed['self_review_fallback']=$selfReview;
        $confirmed['preparer_staff_id']=$preparerId;
        return ['allowed'=>true,'checklist'=>$confirmed];
    }
}
