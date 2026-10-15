<?php
declare(strict_types=1);

use Bulbula\Services\ListingOnboardingService;
use PHPUnit\Framework\TestCase;

final class ListingOnboardingServiceTest extends TestCase
{
    public function testDuplicateFingerprintNormalizesFormattingButPreservesArea(): void
    {
        $first = ListingOnboardingService::duplicateFingerprint('Selam Clinic', 4, '93 Mazoria, Bole');
        $formatted = ListingOnboardingService::duplicateFingerprint('  SELAM—CLINIC ', 4, '93  Mazoria / Bole');
        $otherArea = ListingOnboardingService::duplicateFingerprint('Selam Clinic', 5, '93 Mazoria, Bole');
        self::assertSame($first, $formatted);
        self::assertNotSame($first, $otherArea);
    }

    public function testPermissionEvidenceAndRealRepresentativeDetailsAreRequired(): void
    {
        $valid = [
            'business_name_en'=>'Selam Clinic', 'address_en'=>'93 Mazoria', 'rep_name'=>'Abel Tesfaye',
            'rep_role'=>'Owner', 'rep_phone'=>'+251 911 222 333', 'rep_email'=>null,
            'permission_method'=>'RECORDED_VISIT', 'evidence_reference'=>'VISIT-2026-104',
        ];
        self::assertNull(ListingOnboardingService::validatePermissionInput($valid));

        $missingEvidence = $valid;
        $missingEvidence['evidence_reference'] = '';
        self::assertNotNull(ListingOnboardingService::validatePermissionInput($missingEvidence));

        $verifiedEmail = $valid;
        $verifiedEmail['permission_method'] = 'VERIFIED_EMAIL';
        self::assertNotNull(ListingOnboardingService::validatePermissionInput($verifiedEmail));
        $verifiedEmail['rep_email'] = 'owner@example.com';
        self::assertNull(ListingOnboardingService::validatePermissionInput($verifiedEmail));
    }
}
