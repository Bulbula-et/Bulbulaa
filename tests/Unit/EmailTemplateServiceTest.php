<?php
declare(strict_types=1);

use Bulbula\Core\Database;
use Bulbula\Services\EmailTemplateCatalog;
use Bulbula\Services\EmailTemplateService;
use PHPUnit\Framework\TestCase;

final class EmailTemplateServiceTest extends TestCase
{
    private ReflectionProperty $connection;
    private EmailTemplateService $templates;

    protected function setUp(): void
    {
        $this->connection = new ReflectionProperty(Database::class, 'pdo');
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $pdo->exec('PRAGMA foreign_keys=ON');
        $migration = require BULBULA_ROOT . '/database/migrations/20261015200000_email_templates.php';
        $migration->up($pdo, 'sqlite');
        $this->connection->setValue(null, $pdo);
        $this->templates = new EmailTemplateService();
    }

    protected function tearDown(): void { $this->connection->setValue(null, null); }

    public function testEverySupportedEmailHasAValidInitialDesignAndKnownPlaceholder(): void
    {
        foreach (EmailTemplateCatalog::LABELS as $key=>$label) {
            $initial = EmailTemplateCatalog::initial($key);
            $id = $this->templates->saveDraft($key, $initial['subject'], $initial['html'], $initial['text'], '{"pages":[]}', 1);
            self::assertGreaterThan(0, $id, $label);
            self::assertSame(0, $this->templates->activeId($key));
        }
        self::assertCount(count(EmailTemplateCatalog::LABELS), $this->templates->list());
    }

    public function testDraftDoesNotSendOrGoLiveUntilPublishedAndOlderVersionCanBeRestored(): void
    {
        $fallback = ['subject'=>'Original','html'=>'Original HTML','text'=>'Original text'];
        $key='otp_password_reset';
        $first = EmailTemplateCatalog::initial($key);
        $id1 = $this->templates->saveDraft($key, $first['subject'], $first['html'], $first['text'], '{"pages":[]}', 42);
        self::assertSame($fallback, $this->templates->render($key, ['code'=>'654321','minutes'=>'10'], $fallback));
        $this->templates->publish($key, $id1);
        $active = $this->templates->render($key, ['code'=>'654321','minutes'=>'10'], $fallback);
        self::assertStringContainsString('654321', $active['html']);
        self::assertStringContainsString('654321', $active['text']);
        self::assertStringNotContainsString('{{code}}', $active['html']);
        $id2 = $this->templates->saveDraft($key, 'Updated subject', '<p>Code {{code}} expires in {{minutes}} minutes</p>', 'Code {{code}} in {{minutes}} minutes', '{"pages":[]}', 42);
        self::assertSame($id1, $this->templates->activeId($key));
        $this->templates->publish($key, $id2);
        self::assertSame('Updated subject', $this->templates->render($key, ['code'=>'654321','minutes'=>'10'], $fallback)['subject']);
        $this->templates->publish($key, $id1);
        self::assertSame($id1, $this->templates->activeId($key));
    }

    public function testDynamicValuesAreEscapedAndProjectNeverIncludesARealCode(): void
    {
        $key = 'review_published';
        $init = EmailTemplateCatalog::initial($key);
        $id = $this->templates->saveDraft($key, $init['subject'], $init['html'], $init['text'], '{"pages":[]}', 1);
        $this->templates->publish($key, $id);
        $result = $this->templates->render($key, ['message'=>'<img src=x onerror=alert(1)>'], ['subject'=>'Review','html'=>'fallback','text'=>'fallback']);
        self::assertStringContainsString('&lt;img', $result['html']);
        self::assertStringNotContainsString('<img src=x', $result['html']);
        self::assertSame('<img src=x onerror=alert(1)>', $result['text']);
        self::assertStringNotContainsString('654321', $this->templates->revisions($key)[0]['project_json']);
    }

    public function testRequiredCodeAndExpiryCannotBeRemoved(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->templates->saveDraft('otp_password_reset', 'Hello', '<p>Only a greeting</p>', 'Only a greeting', '{"pages":[]}', 1);
    }

    public function testMaliciousMarkupAndAttributePlaceholdersAreRejected(): void
    {
        foreach (['<p>{{code}} {{minutes}}</p><script>alert(1)</script>', '<p onclick="alert(1)">{{code}} {{minutes}}</p>', '<a href="{{code}}">{{code}} {{minutes}}</a>', '<a href="https://evil.example/">{{code}} {{minutes}}</a>', '<p style="background:url(https://tracker.example/i)">{{code}} {{minutes}}</p>'] as $html) {
            try {
                $this->templates->saveDraft('otp_sign_in', 'Code', $html, '{{code}} expires in {{minutes}} minutes', '{"pages":[]}', 1);
                self::fail('Unsafe email HTML was accepted: ' . $html);
            } catch (InvalidArgumentException $e) { self::assertNotSame('', $e->getMessage()); }
        }
    }

    public function testRevisionCannotBePublishedForAnotherEmailType(): void
    {
        $init = EmailTemplateCatalog::initial('registration_code');
        $id = $this->templates->saveDraft('registration_code', $init['subject'], $init['html'], $init['text'], '{"pages":[]}', 1);
        $this->expectException(InvalidArgumentException::class);
        $this->templates->publish('otp_password_reset', $id);
    }
}
