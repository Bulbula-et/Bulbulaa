<?php
declare(strict_types=1);

use Bulbula\Core\Config;
use Bulbula\Core\Database;
use Bulbula\Core\RateLimiter;
use Bulbula\Core\Session;
use Bulbula\Services\CustomerAuthService;
use Bulbula\Services\CustomerPasswordPolicy;
use Bulbula\Services\CustomerRegistrationService;
use PHPUnit\Framework\TestCase;

final class CustomerRegistrationTest extends TestCase
{
    private ReflectionProperty $connection;

    protected function setUp(): void
    {
        $this->connection = new ReflectionProperty(Database::class, 'pdo');
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $pdo->exec("CREATE TABLE customers (id INTEGER PRIMARY KEY, email TEXT NOT NULL UNIQUE, display_name TEXT NOT NULL, password_hash TEXT, account_status TEXT, last_active_surface TEXT, password_changed_at DATETIME, email_verified_at DATETIME, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP);
            CREATE TABLE account_sessions (id INTEGER PRIMARY KEY,actor_type TEXT,actor_id INTEGER,token_hash TEXT UNIQUE,device_label TEXT,created_at DATETIME DEFAULT CURRENT_TIMESTAMP,last_seen_at DATETIME DEFAULT CURRENT_TIMESTAMP,revoked_at DATETIME);
            CREATE TABLE audit_logs (id INTEGER PRIMARY KEY,actor_type TEXT,actor_staff_id INTEGER,actor_customer_id INTEGER,action_code TEXT,entity_type TEXT,entity_id INTEGER,before_state_json TEXT,after_state_json TEXT,reason_notes TEXT);");
        $this->connection->setValue(null, $pdo);
        $_SESSION = [];
        RateLimiter::clear('registration-session:' . session_id());
        Config::setRuntime('app.env', 'development');
        Config::setRuntime('app.preview_features', true);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $this->connection->setValue(null, null);
        Config::load(BULBULA_ROOT . '/config/env.json');
    }

    public function testCustomerOnlyPolicyAllowsEightWithoutCompositionAndRejectsOnlyTrivialValues(): void
    {
        $policy = new CustomerPasswordPolicy();
        self::assertNull($policy->error('12121212'));
        self::assertNull($policy->error('abcdefgh!'));
        self::assertNull($policy->error('አበበከበደእና'));
        self::assertNotNull($policy->error('12345678'));
        self::assertNotNull($policy->error('short'));
        self::assertNotNull($policy->error(str_repeat('x', 129)));
        self::assertNotNull($policy->error("abcdefgh\0"));
        $hash = $policy->hash(str_repeat('a', 72) . 'Z');
        self::assertTrue(password_verify(str_repeat('a', 72) . 'Z', $hash));
        self::assertFalse(password_verify(str_repeat('a', 72) . 'Y', $hash));
        self::assertTrue($policy->needsRehash(password_hash('legacy-password', PASSWORD_BCRYPT)));
    }

    public function testNoRowBeforeValidCodeAndCodeIsSessionBoundAndSingleUse(): void
    {
        $service = new CustomerRegistrationService();
        $email = 'test-' . bin2hex(random_bytes(6)) . '@example.test';
        $result = $service->begin(strtoupper($email), 'Test User', '12121212', 'WEB');
        self::assertTrue($result['ok']);
        $code = $result['dev_preview_code'];
        self::assertMatchesRegularExpression('/^[0-9]{6}$/', $code);
        self::assertSame(0, (int) Database::fetchOne('SELECT COUNT(*) AS n FROM customers')['n']);
        self::assertArrayNotHasKey('code', Session::get('registration_pending'));
        $saved = $_SESSION;
        $_SESSION = [];
        self::assertFalse($service->verify($code)['ok']);
        $_SESSION = $saved;
        self::assertFalse($service->verify('00000')['ok']);
        self::assertTrue($service->verify($code)['ok']);
        $row = Database::fetchOne('SELECT * FROM customers WHERE email = :email', [':email'=>$email]);
        self::assertNotNull($row);
        self::assertNotNull($row['email_verified_at']);
        self::assertTrue(password_verify('12121212', $row['password_hash']));
        self::assertNull($service->pending());
        self::assertFalse($service->verify($code)['ok']);
    }

    public function testAttemptCapResendInvalidationAndExpiry(): void
    {
        $service = new CustomerRegistrationService();
        $initial = $service->begin('fresh-' . bin2hex(random_bytes(6)) . '@example.test', 'Fresh User', 'rainy purple comet', 'WEB');
        $old = $initial['dev_preview_code'];
        for ($i = 0; $i < 5; $i++) self::assertFalse($service->verify($old === '000000' ? '000001' : '000000')['ok']);
        self::assertFalse($service->verify($old)['ok']);
        $resend = $service->resend();
        self::assertTrue($resend['ok']);
        self::assertFalse($service->verify($old)['ok']);
        $pending = Session::get('registration_pending');
        $pending['expires_at'] = time() - 1;
        Session::set('registration_pending', $pending);
        self::assertNull($service->pending());
        self::assertFalse($service->verify($resend['dev_preview_code'])['ok']);
        self::assertSame(0, (int) Database::fetchOne('SELECT COUNT(*) AS n FROM customers')['n']);
    }

    public function testLegacyBcryptPasswordLoginUpgradesHashWithoutChangingCredentials(): void
    {
        $password = 'old-password-very-long';
        Database::execute('INSERT INTO customers (email,display_name,password_hash,account_status,last_active_surface) VALUES (:email,:name,:hash,:status,:surface)', [
            ':email'=>'legacy@example.test', ':name'=>'Legacy User', ':hash'=>password_hash($password, PASSWORD_BCRYPT), ':status'=>'ACTIVE', ':surface'=>'WEB',
        ]);
        $result = (new CustomerAuthService())->signInWithPassword('legacy@example.test', $password, 'test-ip-hash');
        self::assertTrue($result['ok']);
        $hash = (string) Database::fetchOne('SELECT password_hash FROM customers WHERE email = :email', [':email'=>'legacy@example.test'])['password_hash'];
        self::assertSame(PASSWORD_ARGON2ID, password_get_info($hash)['algo']);
        self::assertTrue(password_verify($password, $hash));
    }

    public function testProductionTransportFailureDoesNotCreatePendingRegistration(): void
    {
        Config::setRuntime('app.env', 'production');
        Config::setRuntime('app.preview_features', false);
        Config::setRuntime('mail.password', ''); // no SMTP delivery or live test message
        $result = (new CustomerRegistrationService())->begin('fail@example.test', 'Fail User', '12121212', 'WEB');
        self::assertFalse($result['ok']);
        self::assertNull(Session::get('registration_pending'));
        self::assertSame(0, (int) Database::fetchOne('SELECT COUNT(*) AS n FROM customers')['n']);
    }
}
