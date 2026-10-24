<?php

namespace App\Tests\Controller;

use App\Entity\AppSetting;
use App\Entity\Company;
use App\Entity\CompanyRegistrationToken;
use App\Entity\CompanySetting;
use App\Entity\User;
use App\Kernel;
use App\Service\CompanyRegistrationSettings;
use App\Service\SettingsResolver;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Dotenv\Dotenv;

class CompanyRegistrationSettingsControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private string $databaseFile;
    private mixed $previousDatabaseUrl;
    private Company $company;
    private Company $otherCompany;
    private User $admin;

    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    protected function setUp(): void
    {
        (new Dotenv())->bootEnv(dirname(__DIR__, 2).'/.env');
        $this->previousDatabaseUrl = $_ENV['DATABASE_URL'] ?? null;
        $file = tempnam(sys_get_temp_dir(), 'spotrace-settings-');
        self::assertNotFalse($file);
        $this->databaseFile = $file;
        $_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = 'sqlite:///'.$file;
        $this->client = self::createClient(['environment' => 'test', 'debug' => true]);
        $this->client->disableReboot();
        $em = $this->em();
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());
        foreach (CompanyRegistrationSettings::FIELDS as $field => $definition) {
            $type = match ($definition['type']) {
                'domains' => 'string_list', 'integer' => 'int', default => 'bool',
            };
            $value = match ($definition['type']) {
                'domains' => [], 'integer' => 'token_ttl_hours' === $field ? 48 : 12, default => true,
            };
            $em->persist((new AppSetting())->setKey('registration.'.$field)->setType($type)->setValue($value)->setLabel($definition['label'])->setGroup('registration'));
        }
        $em->persist((new AppSetting())->setKey('reservation.require_license_plate')->setType('bool')->setValue(false)->setLabel('Tablica')->setGroup('reservation'));
        $this->company = (new Company())->setName('Acme')->setSlug('acme');
        $this->otherCompany = (new Company())->setName('Globex')->setSlug('globex');
        $em->persist($this->company);
        $em->persist($this->otherCompany);
        $this->admin = (new User())->setName('Admin')->setEmail('admin@example.test')->setPasswordHash('unused')->markEmailVerified()->setCompany($this->company)->setRoles([User::ROLE_COMPANY_ADMIN]);
        $em->persist($this->admin);
        $em->flush();
        $this->client->loginUser($this->admin);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = $this->previousDatabaseUrl;
        unlink($this->databaseFile);
    }

    public function testOverridesInheritanceAndCompanyIsolation(): void
    {
        $em = $this->em();
        $em->persist((new CompanySetting())->setCompany($this->company)->setKey('reservation.require_license_plate')->setValue(true));
        $em->persist((new CompanySetting())->setCompany($this->otherCompany)->setKey('registration.password_min_length')->setValue(25));
        $em->flush();
        $changes = [];
        foreach (CompanyRegistrationSettings::FIELDS as $field => $definition) {
            $changes[$field] = ['mode' => 'own', 'value' => match ($definition['type']) {
                'domains' => " EXAMPLE.COM,example.com\nOther.test ", 'integer' => '8', default => '0',
            }];
        }
        $this->save($changes);
        self::assertResponseRedirects('/company/settings');
        $resolver = self::getContainer()->get(SettingsResolver::class);
        self::assertSame(['example.com', 'other.test'], $resolver->stringList('registration.allowed_email_domains', $this->company));
        self::assertFalse($resolver->bool('registration.password_require_digit', $this->company));
        self::assertSame(25, $resolver->int('registration.password_min_length', $this->otherCompany));
        self::assertTrue($resolver->bool('reservation.require_license_plate', $this->company));
        $this->save(['allowed_email_domains' => ['mode' => 'own', 'value' => ''], 'password_min_length' => ['mode' => 'inherit', 'value' => 'bad']]);
        self::assertResponseRedirects();
        self::assertSame([], $resolver->stringList('registration.allowed_email_domains', $this->company));
        self::assertSame(12, $resolver->int('registration.password_min_length', $this->company));
        self::assertFalse($resolver->bool('registration.password_require_digit', $this->company));
    }

    /** @return iterable<string, array{string, mixed}> */
    public static function invalidValues(): iterable
    {
        yield 'zero' => ['password_min_length', '0'];
        yield 'fraction' => ['token_ttl_hours', '1.5'];
        yield 'negative' => ['token_ttl_hours', '-1'];
        yield 'array' => ['password_min_length', ['invalid']];
        yield 'url' => ['allowed_email_domains', 'https://example.com'];
        yield 'email' => ['allowed_email_domains', 'person@example.com'];
        yield 'wildcard' => ['allowed_email_domains', '*.example.com'];
    }

    #[DataProvider('invalidValues')]
    public function testInvalidValuesNeverPartiallySave(string $field, mixed $value): void
    {
        $this->save(['password_require_digit' => ['mode' => 'own', 'value' => '0'], $field => ['mode' => 'own', 'value' => $value]]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->em()->getRepository(CompanySetting::class)->count([]));
    }

    public function testCsrfAndAuthorization(): void
    {
        $this->client->request('POST', '/company/settings', ['company_registration_settings' => ['_token' => 'bad']]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->em()->getRepository(CompanySetting::class)->count([]));
        $this->admin->setRoles([User::ROLE_USER]);
        $this->em()->flush();
        $this->client->loginUser($this->admin);
        foreach (['GET', 'POST'] as $method) {
            $this->client->request($method, '/company/settings');
            self::assertResponseStatusCodeSame(403);
        }
    }

    public function testForgedSettingAndCompanyAreRejected(): void
    {
        $crawler = $this->client->request('GET', '/company/settings');
        $values = $crawler->selectButton('Zapisz zasady rejestracji')->form()->getPhpValues();
        $values['company_registration_settings']['company_id'] = $this->otherCompany->getId();
        $values['company_registration_settings']['reservation_free_window_days'] = ['mode' => 'own', 'value' => '100'];
        $this->client->request('POST', '/company/settings', $values);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->em()->getRepository(CompanySetting::class)->count([]));
    }

    public function testRegistrationRespectsNewRules(): void
    {
        $this->save(['allowed_email_domains' => ['mode' => 'own', 'value' => 'example.com'], 'password_min_length' => ['mode' => 'own', 'value' => '15']]);
        $token = (new CompanyRegistrationToken())->setCompany($this->company);
        $this->em()->persist($token);
        $this->em()->flush();
        $this->client->getCookieJar()->clear();
        $url = '/register/acme?token='.$token->getToken();
        $this->client->request('POST', $url, ['name' => 'New', 'email' => 'new@other.test', 'password' => 'short']);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Domena email nie jest dozwolona');
        self::assertSelectorTextContains('body', 'minimum 15 znaków');
        self::assertSame(1, $this->em()->getRepository(User::class)->count([]));
        $this->client->request('POST', $url, ['name' => 'New', 'email' => 'new@example.com', 'password' => 'LongPassword123!']);
        self::assertResponseRedirects('/login');
        self::assertSame(2, $this->em()->getRepository(User::class)->count([]));
    }

    public function testOnlyNewTokensUseChangedLifetime(): void
    {
        $old = (new CompanyRegistrationToken())->setCompany($this->company);
        $expires = $old->getExpiresAt();
        $this->em()->persist($old);
        $this->em()->flush();
        $this->save(['token_ttl_hours' => ['mode' => 'own', 'value' => '3']]);
        self::assertEquals($expires, $old->getExpiresAt());
        $this->admin->setRoles([User::ROLE_ADMIN]);
        $this->em()->flush();
        $this->client->loginUser($this->admin);
        $this->client->request('GET', '/admin/companies/'.$this->company->getId().'/edit');
        self::assertResponseIsSuccessful();
        $before = time();
        $this->client->submitForm('Wygeneruj link rejestracyjny');
        self::assertResponseRedirects();
        $tokens = $this->em()->getRepository(CompanyRegistrationToken::class)->findBy(['company' => $this->company]);
        self::assertCount(2, $tokens);
        foreach ($tokens as $token) {
            if ($token->getId() !== $old->getId()) {
                self::assertGreaterThanOrEqual($before + 10800, $token->getExpiresAt()->getTimestamp());
                self::assertLessThanOrEqual(time() + 10800, $token->getExpiresAt()->getTimestamp());
            }
        }
    }

    /** @param array<string, array{mode: string, value: mixed}> $changes */
    private function save(array $changes): void
    {
        $crawler = $this->client->request('GET', '/company/settings');
        self::assertResponseIsSuccessful();
        $values = $crawler->selectButton('Zapisz zasady rejestracji')->form()->getPhpValues();
        foreach ($changes as $field => $row) {
            $values['company_registration_settings'][$field] = $row;
        }
        $this->client->request('POST', '/company/settings', $values);
        $this->company = $this->em()->find(Company::class, $this->company->getId());
        $this->otherCompany = $this->em()->find(Company::class, $this->otherCompany->getId());
        $this->admin = $this->em()->find(User::class, $this->admin->getId());
    }

    private function em(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
