<?php
declare(strict_types=1);
namespace Bulbula\Services;

use Bulbula\Core\Config;
use TgWebValid\TgWebValid;

/** Small adapter: third-party protocol verification, Bulbula-owned parsing guards and freshness policy. */
final class TelegramInitDataVerifier
{
    public function verify(string $raw): ?string
    {
        $token = (string) Config::get('auth.telegram_bot_token', '');
        if ($token === '' || strlen($raw) < 20 || strlen($raw) > 8192) return null;
        // parse_str in the vendor verifier normalizes names and collapses duplicates. Reject both
        // before handing it a message; never accept ambiguous signed representations.
        $seen = [];
        foreach (explode('&', $raw) as $part) {
            $name = rawurldecode(explode('=', $part, 2)[0]);
            if (!preg_match('/^[a-z][a-z0-9_]*$/D', $name) || isset($seen[$name])) return null;
            $seen[$name] = true;
        }
        if (!isset($seen['hash'], $seen['auth_date'], $seen['user'])) return null;
        try {
            $data = (new TgWebValid($token))->bot()->validateInitData($raw);
            if ($data === false || $data->user === null) return null;
            $time = $data->authDate->getTimestamp();
            if ($time < time() - 300 || $time > time() + 60) return null;
            $id = $data->user->id;
            return $id > 0 && $id <= 4503599627370495 ? (string) $id : null;
        } catch (\Throwable) {
            // Fail closed, never log raw launch data or token.
            return null;
        }
    }
}
