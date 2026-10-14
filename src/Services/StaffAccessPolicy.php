<?php
declare(strict_types=1);

namespace Bulbula\Services;

use Bulbula\Core\Request;
use Bulbula\Core\Response;
use Bulbula\Core\View;
use Bulbula\Domain\Enums\StaffRole;
use InvalidArgumentException;

/** Central fail-closed authorization policy for every Staff Console route. */
final class StaffAccessPolicy
{
    public const GUEST = 'guest';
    public const AUTHENTICATED = 'authenticated';
    public const ADMINISTRATOR = 'administrator';

    public function __construct(private readonly StaffAuthService $auth = new StaffAuthService()) {}

    public function authorize(string $policy, Request $request): ?Response
    {
        if ($policy === self::GUEST) return null;
        if (!in_array($policy, [self::AUTHENTICATED, self::ADMINISTRATOR], true)) {
            throw new InvalidArgumentException('Unknown Staff access policy.');
        }
        if ($this->auth->currentStaff() === null) {
            return Response::redirect('/staff/login?return_to=' . rawurlencode($request->path()));
        }
        if ($policy === self::ADMINISTRATOR && $this->auth->currentRole() !== StaffRole::ADMINISTRATOR) {
            return Response::html(View::renderStandaloneError(
                403,
                'Access Denied',
                'This Staff Console operation requires the Administrator role.'
            ), 403);
        }
        return null;
    }
}
