<?php
declare(strict_types=1);

namespace Bulbula\Domain\Policies;

final class ListingLifecyclePolicy
{
    private const TRANSITIONS = [
        'PUBLISHED' => ['TEMPORARILY_CLOSED', 'PERMANENTLY_CLOSED', 'UNPUBLISHED'],
        'TEMPORARILY_CLOSED' => ['PENDING_QC', 'PERMANENTLY_CLOSED', 'UNPUBLISHED'],
        'PERMANENTLY_CLOSED' => ['PENDING_QC'],
        'UNPUBLISHED' => ['PENDING_QC'],
        'DRAFT' => ['PENDING_QC', 'UNPUBLISHED'],
        'PENDING_QC' => ['DRAFT', 'UNPUBLISHED'],
    ];

    /** @return array{allowed:bool,error?:string} */
    public static function evaluate(string $from, string $to, string $reason): array
    {
        if (!in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            return ['allowed'=>false, 'error'=>'That listing lifecycle transition is not allowed.'];
        }
        $length = mb_strlen(trim($reason));
        if ($length < 10 || $length > 500) {
            return ['allowed'=>false, 'error'=>'Provide a clear reason between 10 and 500 characters.'];
        }
        return ['allowed'=>true];
    }
}
