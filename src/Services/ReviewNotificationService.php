<?php
declare(strict_types=1);

namespace Bulbula\Services;

use Bulbula\Repositories\ReviewRepository;
use Throwable;

final class ReviewNotificationService
{
    public function __construct(
        private readonly ReviewRepository $reviewRepo = new ReviewRepository(),
        private readonly CustomerReviewMailer $mailer = new CustomerReviewMailer(),
        private readonly AuditService $auditService = new AuditService()
    ) {}

    /** Notification delivery is best-effort and never rolls back the completed review action. */
    public function notify(int $reviewId, string $event): array
    {
        try {
            $context = $this->reviewRepo->notificationContext($reviewId);
            if ($context === null) return ['ok'=>false,'error'=>'No active notification recipient.'];
            $result = $this->mailer->send($context, $event);
            $delivery = !empty($result['ok']) ? 'SENT' : 'FAILED';
            $this->auditService->logCustomerAction(
                (int)$context['customer_id'],
                'REVIEW_NOTIFICATION_'.$delivery,
                'review',
                $reviewId,
                ['notification_event'=>$event,'delivery_status'=>$delivery,'transport'=>$result['transport']??null]
            );
            return $result;
        } catch (Throwable $e) {
            error_log('Review notification failed safely for review #'.$reviewId.'.');
            return ['ok'=>false,'error'=>'Review notification failed safely.'];
        }
    }
}
