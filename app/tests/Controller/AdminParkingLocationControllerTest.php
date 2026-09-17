<?php

namespace App\Tests\Controller;

use App\Entity\Company;
use App\Entity\ParkingLocation;
use App\Entity\ParkingSpot;
use App\Entity\User;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Dotenv\Dotenv;

class AdminParkingLocationControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private string $databaseFile;
    private mixed $previousDatabaseUrl;

    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    protected function setUp(): void
    {
        (new Dotenv())->bootEnv(dirname(__DIR__, 2).'/.env');
        $this->previousDatabaseUrl = $_ENV['DATABASE_URL'] ?? null;
        $databaseFile = tempnam(sys_get_temp_dir(), 'spotrace-locations-');
        self::assertNotFalse($databaseFile);
        $this->databaseFile = $databaseFile;
        $_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = 'sqlite:///'.$databaseFile;
        $this->client = self::createClient(['environment' => 'test', 'debug' => true]);
        $em = $this->entityManager();
        $em->getConnection()->executeStatement('PRAGMA foreign_keys = ON');
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = $this->previousDatabaseUrl;
        unlink($this->databaseFile);
    }

    public function testAdminCanCreateRenameAndDeleteUnusedLocation(): void
    {
        $this->login(User::ROLE_ADMIN);
        $this->client->request('GET', '/admin/locations');
        self::assertResponseIsSuccessful();
        $this->client->submitForm('Dodaj lokalizację', ['parking_location[name]' => '  Parking Łódź  ']);
        self::assertResponseRedirects('/admin/locations');
        $this->client->followRedirect();
        self::assertSelectorTextContains('table', 'Parking Łódź');
        $location = $this->entityManager()->getRepository(ParkingLocation::class)->findOneBy(['name' => 'Parking Łódź']);
        self::assertInstanceOf(ParkingLocation::class, $location);
        $id = $location->getId();
        $this->client->request('GET', '/admin/locations/'.$id.'/edit');
        $this->client->submitForm('Zapisz zmiany', ['parking_location[name]' => 'Parking Centrum']);
        self::assertResponseRedirects('/admin/locations');
        $this->client->followRedirect();
        self::assertSelectorTextContains('table', 'Parking Centrum');
        $this->client->submitForm('Usuń');
        self::assertResponseRedirects('/admin/locations');
        $this->client->followRedirect();
        self::assertSelectorNotExists('table');
        self::assertNull($this->entityManager()->find(ParkingLocation::class, $id));
    }

    public function testRejectsBlankLongAndDuplicateNames(): void
    {
        $this->login(User::ROLE_ADMIN);
        $this->location('Parking A');
        foreach (['   ', str_repeat('a', 121), ' Parking A '] as $name) {
            $this->client->request('GET', '/admin/locations');
            $this->client->submitForm('Dodaj lokalizację', ['parking_location[name]' => $name]);
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('.invalid-feedback');
            self::assertSame(1, $this->entityManager()->getRepository(ParkingLocation::class)->count([]));
        }
    }

    public function testLocationIsRequiredForNewAndExistingSpots(): void
    {
        $this->login(User::ROLE_ADMIN);
        $spot = (new ParkingSpot())->setName('Stare miejsce')->setDescription('Opis');
        $em = $this->entityManager();
        $em->persist($spot);
        $em->flush();
        $id = $spot->getId();
        $this->client->request('GET', '/admin/parking-spots');
        self::assertSelectorTextContains('table', 'Bez lokalizacji');
        self::assertSelectorExists('a[href="/admin/locations"]');
        $this->client->submitForm('Dodaj miejsce', [
            'parking_spot[name]' => 'Nowe miejsce',
            'parking_spot[description]' => 'Opis',
        ]);
        self::assertSelectorTextContains('.invalid-feedback', 'Wybierz lokalizację');
        self::assertSame(1, $this->entityManager()->getRepository(ParkingSpot::class)->count([]));
        $locationId = $this->location('Parking A')->getId();
        $this->client->request('GET', '/admin/parking-spots/'.$id.'/edit');
        $this->client->submitForm('Zapisz zmiany', ['parking_spot[location]' => '']);
        self::assertSelectorTextContains('.invalid-feedback', 'Wybierz lokalizację');
        $this->client->submitForm('Zapisz zmiany', ['parking_spot[location]' => $locationId]);
        self::assertResponseRedirects('/admin/parking-spots');
        $this->client->followRedirect();
        self::assertSelectorTextContains('table', 'Parking A');
        $this->client->submitForm('Dodaj miejsce', [
            'parking_spot[name]' => 'Nowe miejsce',
            'parking_spot[description]' => 'Opis',
            'parking_spot[location]' => $locationId,
        ]);
        self::assertResponseRedirects('/admin/parking-spots');
        self::assertSame(2, $this->entityManager()->getRepository(ParkingSpot::class)->count([]));
    }

    public function testCannotDeleteLocationWithSpots(): void
    {
        $this->login(User::ROLE_ADMIN);
        $location = $this->location('Parking A');
        $id = $location->getId();
        $em = $this->entityManager();
        $em->persist((new ParkingSpot())->setName('A-1')->setDescription('Opis')->setLocation($location));
        $em->flush();
        $this->client->request('GET', '/admin/locations');
        $this->client->submitForm('Usuń');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'Nie można usunąć lokalizacji');
        self::assertNotNull($this->entityManager()->find(ParkingLocation::class, $id));
    }

    public function testRejectsInvalidCsrfForCreateEditAndDelete(): void
    {
        $this->login(User::ROLE_ADMIN);
        $id = $this->location('Parking A')->getId();
        $this->client->request('POST', '/admin/locations', ['parking_location' => ['name' => 'Nie zapisuj', '_token' => 'invalid']]);
        self::assertSelectorTextContains('body', 'CSRF');
        $this->client->request('POST', '/admin/locations/'.$id.'/edit', ['parking_location' => ['name' => 'Nie zapisuj', '_token' => 'invalid']]);
        self::assertSelectorTextContains('body', 'CSRF');
        $this->client->request('POST', '/admin/locations/'.$id.'/delete', ['_token' => 'invalid']);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/admin/locations/'.$id.'/delete');
        self::assertResponseStatusCodeSame(405);
        $em = $this->entityManager();
        self::assertSame(1, $em->getRepository(ParkingLocation::class)->count([]));
        self::assertSame('Parking A', $em->find(ParkingLocation::class, $id)?->getName());
    }

    public function testNonAdminsCannotReadOrModifyLocations(): void
    {
        $id = $this->location('Parking A')->getId();
        $this->client->request('GET', '/admin/locations');
        self::assertResponseRedirects('http://localhost/login');
        foreach ([User::ROLE_USER, User::ROLE_COMPANY_ADMIN] as $role) {
            $this->login($role);
            foreach ([['GET', '/admin/locations'], ['POST', '/admin/locations'], ['GET', '/admin/locations/'.$id.'/edit'], ['POST', '/admin/locations/'.$id.'/edit'], ['POST', '/admin/locations/'.$id.'/delete']] as [$method, $path]) {
                $this->client->request($method, $path);
                self::assertResponseStatusCodeSame(403);
            }
        }
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    private function login(string $role): void
    {
        $em = $this->entityManager();
        $user = (new User())->setName('Test')->setEmail(strtolower($role).'@example.test')->setRoles([$role])->setPasswordHash('unused')->markEmailVerified();
        if (User::ROLE_ADMIN !== $role) {
            $company = (new Company())->setName($role)->setSlug($role);
            $em->persist($company);
            $user->setCompany($company);
        }
        $em->persist($user);
        $em->flush();
        $this->client->loginUser($user);
    }

    private function location(string $name): ParkingLocation
    {
        $location = (new ParkingLocation())->setName($name);
        $em = $this->entityManager();
        $em->persist($location);
        $em->flush();

        return $location;
    }
}
