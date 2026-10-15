<?php
declare(strict_types=1);

namespace Bulbula\Services;

use Bulbula\Core\RateLimiter;
use Bulbula\Core\Session;
use Bulbula\Repositories\GoogleAuthBridgeRepository;

/** Browser verifies Google; only the originating Mini App session may consume. */
final class GoogleBrowserHandoffService
{
    public function __construct(private readonly GoogleAuthBridgeRepository $repo = new GoogleAuthBridgeRepository()) {}

    public function start(string $clientIpHash): ?string
    {
        if (!RateLimiter::allow('google-bridge-start:' . $clientIpHash, 5, 600)) return null;
        $this->repo->purgeOld(date('Y-m-d H:i:s', time() - 86400));
        $flow = bin2hex(random_bytes(24));
        $sourceSecret = bin2hex(random_bytes(32));
        $this->repo->create(hash('sha256', $flow), hash('sha256', $sourceSecret), date('Y-m-d H:i:s', time() + 300));
        Session::set('google_bridge_flow', $flow);
        Session::set('google_bridge_secret', $sourceSecret);
        return $flow;
    }

    public function available(string $flow): bool
    {
        return preg_match('/^[a-f0-9]{48}$/', $flow) === 1
            && $this->repo->available(hash('sha256', $flow), date('Y-m-d H:i:s'));
    }

    public function complete(string $flow, int $customerId): bool
    {
        return $customerId > 0 && preg_match('/^[a-f0-9]{48}$/', $flow) === 1
            && $this->repo->complete(hash('sha256', $flow), $customerId, date('Y-m-d H:i:s'));
    }

    public function currentFlow(): ?string
    {
        $flow = Session::get('google_bridge_flow');
        $secret = Session::get('google_bridge_secret');
        return is_string($flow) && preg_match('/^[a-f0-9]{48}$/', $flow) === 1
            && is_string($secret) && preg_match('/^[a-f0-9]{64}$/', $secret) === 1 ? $flow : null;
    }

    public function status(): string
    {
        $flow = $this->currentFlow();
        if ($flow === null) return 'expired';
        return $this->repo->status(hash('sha256', $flow), hash('sha256', (string) Session::get('google_bridge_secret')), date('Y-m-d H:i:s'));
    }

    public function consume(): ?int
    {
        $flow = $this->currentFlow();
        if ($flow === null) return null;
        $customerId = $this->repo->consume(hash('sha256', $flow), hash('sha256', (string) Session::get('google_bridge_secret')), date('Y-m-d H:i:s'));
        if ($customerId !== null) {
            Session::forget('google_bridge_flow');
            Session::forget('google_bridge_secret');
        }
        return $customerId;
    }
}
