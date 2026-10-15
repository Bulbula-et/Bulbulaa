<?php
declare(strict_types=1);
namespace Bulbula\Services;

/** Explicit catalogue; unknown event IDs can never be used for arbitrary outbound mail. */
final class EmailTemplateCatalog
{
    public const LABELS = [
        'registration_code'=>'Registration code',
        'otp_sign_in'=>'Sign-in code', 'otp_password_reset'=>'Password recovery code',
        'otp_verify_email'=>'Email verification code', 'otp_sensitive_action'=>'Sensitive-action code',
        'password_changed'=>'Password changed', 'email_changed_old'=>'Email changed — previous inbox',
        'email_changed_new'=>'Email changed — new inbox', 'deletion_scheduled'=>'Account deletion scheduled',
        'account_restored'=>'Account restored', 'account_deleted'=>'Account permanently deleted',
        'review_submitted'=>'Review received', 'review_edited'=>'Updated review received',
        'review_published'=>'Review published', 'review_rejected'=>'Review rejected',
        'review_removed_by_staff'=>'Review removed by staff', 'review_appeal_submitted'=>'Review appeal received',
        'review_appeal_upheld'=>'Review appeal upheld', 'review_appeal_overturned'=>'Review appeal overturned',
        'campaign_terminated'=>'Campaign terminated early',
    ];

    public static function isCode(string $key): bool
    {
        return $key === 'registration_code' || str_starts_with($key, 'otp_');
    }

    public static function required(string $key): string
    {
        if (!isset(self::LABELS[$key])) throw new \InvalidArgumentException('Unknown email type.');
        return self::isCode($key) ? 'code' : 'message';
    }

    public static function example(string $key): array
    {
        self::required($key);
        return [
            'code'=>'123456', 'minutes'=>'10', 'message'=>'A sample Bulbula.et update for your account. No real information is shown in this preview.',
            'subject'=>self::LABELS[$key] . ' — Bulbula.et', 'name'=>'Sample customer',
        ];
    }

    public static function initial(string $key): array
    {
        $required = self::required($key);
        $body = $required === 'code'
            ? 'Your code is {{code}}. Enter it within {{minutes}} minutes. Never share it.'
            : '{{message}}';
        $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#fff5eb;padding:26px;font-family:Arial,sans-serif"><tr><td align="center"><table role="presentation" width="560" style="max-width:560px;background:#ffffff;border-radius:16px;padding:28px"><tr><td><p style="color:#EA6A15;font-weight:bold;font-size:20px">Bulbula.et</p><h1 style="color:#292524;font-size:25px">' . htmlspecialchars(self::LABELS[$key], ENT_QUOTES, 'UTF-8') . '</h1><p style="color:#44403c;font-size:16px;line-height:1.6">' . $body . '</p><p style="color:#78716c;font-size:12px">If you did not request this, you can ignore this email.</p></td></tr></table></td></tr></table>';
        return ['subject'=>'{{subject}}', 'html'=>$html, 'text'=>$body];
    }
}
