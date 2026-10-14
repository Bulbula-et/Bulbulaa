<?php
declare(strict_types=1);

use Bulbula\Core\Request;
use Bulbula\Core\Router;
use Bulbula\Services\StaffAccessPolicy;
use PHPUnit\Framework\TestCase;

final class StaffAuthorizationPolicyTest extends TestCase
{
    public function testRouterRejectsStaffRouteWithoutExplicitPolicy(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('missing an explicit access policy');
        new Router([['GET', '/staff/new-privileged-page', ['Staff\\MissingController', 'index']]]);
    }

    public function testGuestPolicyAllowsAuthenticationEndpoints(): void
    {
        $policy = new StaffAccessPolicy();
        $request = new Request('GET', '/staff/login', [], [], []);
        self::assertNull($policy->authorize(StaffAccessPolicy::GUEST, $request));
    }

    public function testUnknownPolicyFailsClosed(): void
    {
        $policy = new StaffAccessPolicy();
        $request = new Request('GET', '/staff/example', [], [], []);
        $this->expectException(InvalidArgumentException::class);
        $policy->authorize('operator-or-maybe-admin', $request);
    }
}
