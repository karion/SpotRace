<?php

namespace App\Tests\Controller;

use App\Entity\AppSetting;
use App\Entity\Company;
use App\Entity\CompanyParkingSpot;
use App\Entity\CompanySetting;
use App\Entity\ParkingLocation;
use App\Entity\ParkingReservation;
use App\Entity\ParkingSpot;
use App\Entity\ParkingSpotAssignment;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Kernel;
use App\Repository\CompanyParkingSpotRepository;
use App\Repository\ParkingReservationRepository;
use App\Repository\ParkingSpotAssignmentRepository;
use App\Repository\ParkingSpotRepository;
use App\Repository\UserRepository;
use App\Service\ReservationManager;
use App\Service\ReservationPolicy;
use App\Service\SettingKeys;
use App\Service\SettingsResolver;
use App\Service\VehicleManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\Dotenv\Dotenv;

class ReservationControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private string $databaseFile;
    private mixed $previousDatabaseUrl;
    private Company $company;
    private User $user;
    private User $recipient;
    private ParkingSpot $spot;
    private ReservationPolicy $policy;
    private \DateTime $clock;

    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    protected function setUp(): void
    {
        (new Dotenv())->bootEnv(dirname(__DIR__, 2).'/.env');
        $this->previousDatabaseUrl = $_ENV['DATABASE_URL'] ?? null;
        $file = tempnam(sys_get_temp_dir(), 'spotrace-reservations-');
        self::assertNotFalse($file);
        $this->databaseFile = $file;
        $_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = 'sqlite:///'.$file;
        $this->client = self::createClient(['environment' => 'test', 'debug' => true]);
        $this->client->disableReboot();
        $em = $this->em();
        $em->getConnection()->executeStatement('PRAGMA foreign_keys = ON');
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());
        foreach ([SettingKeys::RESERVATION_ASSIGNED_WINDOW_DAYS => 7, SettingKeys::RESERVATION_FREE_WINDOW_DAYS => 1, SettingKeys::RESERVATION_CONFIRMATION_DEADLINE_HOUR => 7, SettingKeys::RESERVATION_REQUIRE_LICENSE_PLATE => false] as $key => $value) {
            $em->persist((new AppSetting())->setKey($key)->setType(is_bool($value) ? AppSetting::TYPE_BOOL : AppSetting::TYPE_INT)->setValue($value)->setLabel($key)->setGroup('reservations'));
        }
        $this->company = (new Company())->setName('Firma A')->setSlug('firma-a');
        $em->persist($this->company);
        $this->user = $this->newUser('Anna', $this->company);
        $this->recipient = $this->newUser('Jan', $this->company);
        $this->spot = $this->newSpot('A-1', $this->company);
        $em->flush();
        $this->freezeTime('2026-09-18 06:59:59');
        $this->client->loginUser($this->user);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = $this->previousDatabaseUrl;
        unlink($this->databaseFile);
    }

    public function testFreeReservationSavesVehicleAndSnapshotAndCanBeReleased(): void
    {
        $form = $this->form('/reservations/free');
        $this->client->submit($form, ['license_plate' => ' wa 123 ']);
        self::assertResponseRedirects('/?date=2026-09-18');
        $reservation = $this->reservation();
        self::assertSame('WA123', $reservation->getLicensePlate());
        self::assertSame('free', $reservation->getType());
        $vehicle = $this->em()->getRepository(Vehicle::class)->findOneBy(['owner' => $this->user]);
        self::assertInstanceOf(Vehicle::class, $vehicle);
        $vehicle->setLicensePlate('CHANGED');
        $this->em()->flush();
        self::assertSame('WA123', $reservation->getLicensePlate());
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'WA123');
        $this->client->submitForm('Zwolnij miejsce');
        self::assertResponseRedirects('/?date=2026-09-18');
        self::assertSame(0, $this->em()->getRepository(ParkingReservation::class)->count([]));
        self::assertSame(1, $this->em()->getRepository(Vehicle::class)->count([]));
    }

    public function testSavedVehicleAndOptionalNullPlate(): void
    {
        $vehicle = $this->vehicle($this->user, 'WA123');
        $this->client->submit($this->form('/reservations/free'), ['vehicle_id' => $vehicle->getId()]);
        self::assertResponseRedirects();
        self::assertSame('WA123', $this->reservation()->getLicensePlate());
        $this->client->submit($this->form('/reservations/free', '2026-09-19'));
        self::assertResponseRedirects();
        self::assertNull($this->reservation('2026-09-19')->getLicensePlate());
        self::assertSame(1, $this->em()->getRepository(Vehicle::class)->count([]));
    }

    public function testConfirmAndDelegatePreserveActorsAndVehicleOwner(): void
    {
        $this->assign($this->user);
        $this->client->submit($this->form('/reservations/confirm-assigned'), ['license_plate' => 'OWN123']);
        self::assertResponseRedirects();
        self::assertSame('assigned_confirmed', $this->reservation()->getType());
        $this->client->request('GET', '/reservations/delegate-assigned?date=2026-09-19');
        self::assertResponseIsSuccessful();
        $this->client->submitForm('Przekaż miejsce', ['target_user_id' => $this->recipient->getId(), 'target_license_plate' => 'jan 123']);
        self::assertResponseRedirects();
        $reservation = $this->reservation('2026-09-19');
        self::assertSame('assigned_delegated', $reservation->getType());
        self::assertSame($this->recipient->getId(), $reservation->getReservedForUser()->getId());
        self::assertSame($this->user->getId(), $reservation->getCreatedByUser()->getId());
        self::assertSame('JAN123', $reservation->getLicensePlate());
        self::assertSame(1, $this->em()->getRepository(Vehicle::class)->count(['owner' => $this->recipient, 'licensePlate' => 'JAN123']));
    }

    public function testRequiredPlateUsesCompanyOverrideAndRetainsInvalidInput(): void
    {
        $this->em()->persist((new CompanySetting())->setCompany($this->company)->setKey(SettingKeys::RESERVATION_REQUIRE_LICENSE_PLATE)->setValue(true));
        $this->em()->flush();
        $this->client->submit($this->form('/reservations/free'));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Podaj numer rejestracyjny');
        $this->client->submitForm('Rezerwuj', ['license_plate' => 'bad!']);
        self::assertResponseStatusCodeSame(422);
        self::assertInputValueSame('license_plate', 'bad!');
        self::assertSelectorTextContains('body', 'Numer rejestracyjny może zawierać');
        $this->client->submitForm('Rezerwuj', ['license_plate' => ' wa 123 ']);
        self::assertResponseRedirects();
        self::assertSame('WA123', $this->reservation()->getLicensePlate());
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidPayloads(): iterable
    {
        yield 'invalid date' => [['date' => '2026-02-30']];
        yield 'outside window' => [['date' => '2026-09-20']];
        yield 'past' => [['date' => '2026-09-17']];
        yield 'array instead of date' => [['date' => ['2026-09-18']]];
        yield 'invalid csrf' => [['_token' => 'invalid']];
        yield 'unknown fields' => [['reserved_for_user_id' => 'forged']];
        yield 'unknown spot' => [['spot_id' => '00000000-0000-4000-8000-000000000001']];
        yield 'bad plate' => [['license_plate' => 'INVALID!']];
    }

    /** @param array<string, mixed> $changes */
    #[DataProvider('invalidPayloads')]
    public function testInvalidPayloadNeverWritesReservationOrVehicle(array $changes): void
    {
        $values = $this->form('/reservations/free')->getPhpValues();
        $values['license_plate'] = 'NEW123';
        $this->client->request('POST', '/reservations/free', array_replace($values, $changes));
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->em()->getRepository(ParkingReservation::class)->count([]));
        self::assertSame(0, $this->em()->getRepository(Vehicle::class)->count([]));
    }

    public function testRejectsTwoPlateSourcesAndAnotherUsersVehicle(): void
    {
        $own = $this->vehicle($this->user, 'OWN123');
        $other = $this->vehicle($this->recipient, 'OTHER123');
        $freeForm = $this->form('/reservations/free');
        $values = $freeForm->getPhpValues();
        foreach ([['vehicle_id' => $own->getId(), 'license_plate' => 'NEW123'], ['vehicle_id' => $other->getId()]] as $changes) {
            $this->client->request('POST', '/reservations/free', array_replace($values, $changes));
            self::assertResponseStatusCodeSame(422);
            self::assertSame(0, $this->em()->getRepository(ParkingReservation::class)->count([]));
        }
        self::assertSame(2, $this->em()->getRepository(Vehicle::class)->count([]));
    }

    public function testDelegationRejectsVehicleBelongingToDifferentRecipient(): void
    {
        $this->assign($this->user);
        $own = $this->vehicle($this->user, 'OWN123');
        $this->client->request('GET', '/reservations/delegate-assigned?date=2026-09-19');
        $this->client->submitForm('Przekaż miejsce', ['target_user_id' => $this->recipient->getId(), 'target_vehicle_id' => $own->getId()]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'pojazd nie należy');
        self::assertSame(0, $this->em()->getRepository(ParkingReservation::class)->count([]));
    }

    public function testCompanyOwnershipIsRecheckedAfterFormWasDisplayed(): void
    {
        $this->assign($this->user);
        $confirm = $this->form('/reservations/confirm-assigned')->getPhpValues();
        $this->client->request('GET', '/reservations/delegate-assigned?date=2026-09-18');
        $delegate = $this->client->getCrawler()->selectButton('Przekaż miejsce')->form()->getPhpValues();
        $companySpot = $this->em()->getRepository(CompanyParkingSpot::class)->findOneBy(['parkingSpot' => $this->spot]);
        self::assertInstanceOf(CompanyParkingSpot::class, $companySpot);
        $companySpot->setEndsAt($this->policy->parseDate('2026-09-17'));
        $this->em()->flush();
        foreach ([['/reservations/confirm-assigned', $confirm], ['/reservations/delegate-assigned/submit', array_replace($delegate, ['target_user_id' => $this->recipient->getId()])]] as [$path, $values]) {
            $this->client->request('POST', $path, $values);
            self::assertResponseStatusCodeSame(422);
            self::assertSelectorTextContains('body', 'Miejsce nie należy do Twojej firmy');
        }
        $this->client->request('GET', '/');
        self::assertSelectorNotExists('form[action="/reservations/confirm-assigned"]');
        self::assertSame(0, $this->em()->getRepository(ParkingReservation::class)->count([]));
    }

    public function testOccupiedSpotAndExistingDailyReservationAreRechecked(): void
    {
        $freeForm = $this->form('/reservations/free');
        $values = $freeForm->getPhpValues();
        $second = $this->newSpot('A-2', $this->company);
        $this->em()->flush();
        $this->client->submit($freeForm, ['license_plate' => 'OWN123']);
        self::assertResponseRedirects();
        $this->client->request('POST', '/reservations/free', array_replace($values, ['spot_id' => $second->getId(), 'license_plate' => 'NEW123']));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'ma już rezerwację');
        $this->client->loginUser($this->recipient);
        // Same session, valid CSRF, but another actor competes for the occupied spot.
        $this->client->request('POST', '/reservations/free', array_replace($values, ['license_plate' => 'OTHER123']));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'miejsce jest już zarezerwowane');
        $this->client->request('GET', '/');
        self::assertSelectorExists('.text-bg-danger');
        self::assertSelectorTextContains('.text-bg-danger', 'Zarezerwowane');
        self::assertSame(1, $this->em()->getRepository(ParkingReservation::class)->count([]));
        self::assertSame(1, $this->em()->getRepository(Vehicle::class)->count([]));
    }

    /** @return iterable<string, array{bool}> */
    public static function concurrentConflicts(): iterable
    {
        yield 'spot occupied' => [false];
        yield 'user already booked elsewhere' => [true];
    }

    #[DataProvider('concurrentConflicts')]
    public function testDatabaseConflictRollsBackNewVehicleWithReservation(bool $userConflict): void
    {
        $values = $this->form('/reservations/free')->getPhpValues();
        $existingSpot = $userConflict ? $this->newSpot('A-2', $this->company) : $this->spot;
        $existingUser = $userConflict ? $this->user : $this->recipient;
        $existing = (new ParkingReservation())->setParkingSpot($existingSpot)->setReservedForUser($existingUser)->setCreatedByUser($existingUser)->setReservationDate($this->policy->today())->setType('free');
        $this->em()->persist($existing);
        $this->em()->flush();
        // Simulate stale availability reads; the actual database constraint must still win.
        $staleReservations = $this->createStub(ParkingReservationRepository::class);
        $staleReservations->method('findUserReservationForDate')->willReturn(null);
        $staleReservations->method('findSpotReservationForDate')->willReturn(null);
        $container = self::getContainer();
        $manager = new ReservationManager($container->get(ParkingSpotRepository::class), $container->get(CompanyParkingSpotRepository::class), $container->get(ParkingSpotAssignmentRepository::class), $staleReservations, $container->get(UserRepository::class), $this->policy, $container->get(SettingsResolver::class), $container->get(VehicleManager::class), $this->em());
        $container->set(ReservationManager::class, $manager);
        $this->client->request('POST', '/reservations/free', array_replace($values, ['license_plate' => 'ROLLBACK123']));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'równocześnie');
        self::assertSame(1, (int) $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM parking_reservation'));
        self::assertSame(0, (int) $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM vehicle'));
    }

    private function em(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testAssignedSpotCannotBeBookedAsFreeAndOthersAreLockedUntilCutoff(): void
    {
        $values = $this->form('/reservations/free')->getPhpValues();
        $this->assign($this->user);
        $this->client->request('POST', '/reservations/free', $values);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'użyj potwierdzenia');
        $this->client->request('GET', '/');
        self::assertSelectorExists('.bg-primary-subtle');
        self::assertSelectorTextContains('.bg-primary-subtle', 'przypisane');
        $this->client->loginUser($this->recipient);
        $this->client->request('POST', '/reservations/free', $values);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'czasowo zarezerwowane');
        $this->client->request('GET', '/');
        self::assertSelectorNotExists('form[action="/reservations/free"]');
    }

    public function testAtCutoffOtherUserCanReserveAssignedSpotButOwnerCannotConfirm(): void
    {
        $this->freezeTime('2026-09-18 07:00:00');
        $this->assign($this->user);
        $this->client->request('GET', '/');
        self::assertSelectorNotExists('form[action="/reservations/confirm-assigned"] input[value="2026-09-18"]');
        $values = $this->form('/reservations/confirm-assigned', '2026-09-19')->getPhpValues();
        $this->client->request('POST', '/reservations/confirm-assigned', array_replace($values, ['date' => '2026-09-18']));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'przed 07:00');
        $this->client->loginUser($this->recipient);
        $this->client->submit($this->form('/reservations/free'));
        self::assertResponseRedirects();
        self::assertSame($this->recipient->getId(), $this->reservation()->getReservedForUser()->getId());
    }

    public function testAssignedOwnerCanReserveSpotAsFreeAfterCutoff(): void
    {
        $this->freezeTime('2026-09-18 07:00:00');
        $this->assign($this->user);
        $this->client->request('GET', '/');
        self::assertSelectorNotExists('form[action="/reservations/confirm-assigned"] input[value="2026-09-18"]');
        self::assertSelectorExists('form[action="/reservations/free"] input[value="2026-09-18"]');

        $this->client->submit($this->form('/reservations/free'));

        self::assertResponseRedirects();
        self::assertSame('free', $this->reservation()->getType());
        self::assertSame($this->user->getId(), $this->reservation()->getReservedForUser()->getId());
    }

    public function testLastAssignedDayIsAcceptedAndNextDayIsRejected(): void
    {
        $this->assign($this->user);
        $values = $this->form('/reservations/confirm-assigned', '2026-09-25')->getPhpValues();
        $this->client->request('POST', '/reservations/confirm-assigned', array_replace($values, ['date' => '2026-09-26']));
        self::assertResponseStatusCodeSame(422);
        $this->client->request('POST', '/reservations/confirm-assigned', $values);
        self::assertResponseRedirects();
        self::assertSame('assigned_confirmed', $this->reservation('2026-09-25')->getType());
    }

    public function testCannotReleaseAnotherUsersReservationOrOwnAtCutoff(): void
    {
        $this->freezeTime('2026-09-18 07:00:00');
        $this->client->submit($this->form('/reservations/free', '2026-09-19'));
        self::assertResponseRedirects();
        $release = $this->form('/reservations/release', '2026-09-19')->getPhpValues();
        $this->client->request('POST', '/reservations/release', array_replace($release, ['date' => '2026-09-18']));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Zwolnienie miejsca jest możliwe do 07:00');
        $this->client->loginUser($this->recipient);
        $this->client->request('POST', '/reservations/release', $release);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Nie masz rezerwacji do zwolnienia');
        self::assertSame(1, $this->em()->getRepository(ParkingReservation::class)->count([]));
    }

    public function testFreeSpotAndDelegationCannotCrossCompanies(): void
    {
        $otherCompany = (new Company())->setName('Firma B')->setSlug('firma-b');
        $this->em()->persist($otherCompany);
        $foreignUser = $this->newUser('Obcy', $otherCompany);
        $foreignSpot = $this->newSpot('B-1', $otherCompany);
        $this->em()->flush();
        $values = $this->form('/reservations/free')->getPhpValues();
        self::assertSelectorTextNotContains('body', 'B-1');
        $this->client->request('POST', '/reservations/free', array_replace($values, ['spot_id' => $foreignSpot->getId()]));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Miejsce nie należy do Twojej firmy');
        $this->assign($this->user);
        $this->client->request('GET', '/reservations/delegate-assigned?date=2026-09-19');
        $values = $this->client->getCrawler()->selectButton('Przekaż miejsce')->form()->getPhpValues();
        foreach ([$foreignUser->getId(), $this->user->getId()] as $target) {
            $this->client->request('POST', '/reservations/delegate-assigned/submit', array_replace($values, ['target_user_id' => $target]));
            self::assertResponseStatusCodeSame(422);
        }
        self::assertSame(0, $this->em()->getRepository(ParkingReservation::class)->count([]));
    }

    public function testDelegateCannotGiveRecipientSecondReservationForSameDay(): void
    {
        $second = $this->newSpot('A-2', $this->company);
        $this->assign($this->user);
        $this->em()->persist((new ParkingReservation())->setParkingSpot($second)->setReservedForUser($this->recipient)->setCreatedByUser($this->recipient)->setReservationDate($this->policy->today())->setType('free'));
        $this->em()->flush();
        $this->client->request('GET', '/reservations/delegate-assigned?date=2026-09-18');
        $this->client->submitForm('Przekaż miejsce', ['target_user_id' => $this->recipient->getId(), 'target_license_plate' => 'NEW123']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'ma już rezerwację');
        self::assertSame(0, $this->em()->getRepository(Vehicle::class)->count([]));
    }

    public function testCalendarIncludesFullFreeWindowAndUsesUniqueFieldIds(): void
    {
        foreach ([SettingKeys::RESERVATION_ASSIGNED_WINDOW_DAYS => 1, SettingKeys::RESERVATION_FREE_WINDOW_DAYS => 3] as $key => $days) {
            $this->em()->persist((new CompanySetting())->setCompany($this->company)->setKey($key)->setValue($days));
        }
        $this->em()->flush();
        $this->form('/reservations/free', '2026-09-21');
        self::assertSelectorExists('#free-form-20260918_spot_id');
        self::assertSelectorExists('[data-form-target="free-form-20260918"]');
        $ids = $this->client->getCrawler()->filter('input[id], select[id], button[id]')->extract(['id']);
        self::assertSame($ids, array_values(array_unique($ids)));
    }

    public function testCalendarGroupsSpotsByLocation(): void
    {
        $locationA = $this->location('Biurowiec A');
        $locationB = $this->location('Biurowiec B');
        $this->spot->setLocation($locationA);
        $second = $this->newSpot('A-2', $this->company);
        $second->setLocation($locationB);
        $this->em()->flush();

        $this->client->request('GET', '/');
        self::assertSelectorTextContains('h4', 'Biurowiec A');
        self::assertSelectorTextContains('body', 'A-1');
        self::assertSelectorTextContains('body', 'A-2');
        self::assertSelectorTextContains('body', 'Biurowiec B');
    }

    public function testEveryMutationRequiresPostCsrfAndAuthentication(): void
    {
        foreach (['/reservations/free', '/reservations/confirm-assigned', '/reservations/delegate-assigned/submit', '/reservations/release'] as $path) {
            $this->client->request('GET', $path);
            self::assertResponseStatusCodeSame(405);
            $this->client->request('POST', $path, ['date' => '2026-09-18', '_token' => 'invalid']);
            self::assertResponseStatusCodeSame(422);
            self::assertSelectorTextContains('body', 'CSRF');
        }
        $this->client->getCookieJar()->clear();
        foreach (['/reservations/free', '/reservations/confirm-assigned', '/reservations/delegate-assigned/submit', '/reservations/release'] as $path) {
            $this->client->request('POST', $path, ['date' => '2026-09-18']);
            self::assertResponseRedirects('http://localhost/login');
        }
        self::assertSame(0, $this->em()->getRepository(ParkingReservation::class)->count([]));
    }

    private function freezeTime(string $time): void
    {
        if (isset($this->clock)) {
            $this->clock->modify($time);

            return;
        }
        $this->clock = new \DateTime($time, new \DateTimeZone('Europe/Warsaw'));
        $this->policy = new class(self::getContainer()->get(SettingsResolver::class), $this->clock) extends ReservationPolicy {
            public function __construct(SettingsResolver $settings, private readonly \DateTime $clock)
            {
                parent::__construct($settings, 'Europe/Warsaw');
            }

            public function now(): \DateTimeImmutable
            {
                return \DateTimeImmutable::createFromMutable($this->clock);
            }
        };
        self::getContainer()->set(ReservationPolicy::class, $this->policy);
    }

    private function newUser(string $name, Company $company): User
    {
        $user = (new User())->setName($name)->setEmail(strtolower($name).'@example.test')->setPasswordHash('unused')->markEmailVerified()->setCompany($company);
        $this->em()->persist($user);

        return $user;
    }

    private function newSpot(string $name, Company $company): ParkingSpot
    {
        $spot = (new ParkingSpot())->setName($name)->setDescription('Parking');
        $this->em()->persist($spot);
        $this->em()->persist((new CompanyParkingSpot())->setCompany($company)->setParkingSpot($spot)->setStartsAt(new \DateTimeImmutable('2026-01-01')));

        return $spot;
    }

    private function assign(User $owner): void
    {
        $owner = $this->em()->getReference(User::class, $owner->getId());
        $spot = $this->em()->getReference(ParkingSpot::class, $this->spot->getId());
        $this->em()->persist((new ParkingSpotAssignment())->setParkingSpot($spot)->setAssignedUser($owner)->setAssignedByUser($owner)->setStartsAt($this->policy->today()));
        $this->em()->flush();
    }

    private function vehicle(User $owner, string $plate): Vehicle
    {
        $vehicle = (new Vehicle())->setOwner($owner)->setLicensePlate($plate);
        $this->em()->persist($vehicle);
        $this->em()->flush();

        return $vehicle;
    }

    private function location(string $name): ParkingLocation
    {
        $location = (new ParkingLocation())->setName($name);
        $this->em()->persist($location);
        $this->em()->flush();

        return $location;
    }

    private function form(string $action, string $date = '2026-09-18'): Form
    {
        $crawler = $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form[action="'.$action.'"]')->reduce(fn ($node) => $node->filter('input[name="date"]')->attr('value') === $date)->first()->form();
        if ('/reservations/free' === $action) {
            $spot = $crawler->filter('[data-form-target="free-form-'.str_replace('-', '', $date).'"]')->first();
            if ($spot->count()) {
                $form->setValues(['spot_id' => $spot->attr('data-spot-id')]);
            }
        }

        return $form;
    }

    private function reservation(string $date = '2026-09-18'): ParkingReservation
    {
        $reservation = $this->em()->getRepository(ParkingReservation::class)->findOneBy(['reservationDate' => $this->policy->parseDate($date)]);
        self::assertInstanceOf(ParkingReservation::class, $reservation);

        return $reservation;
    }
}
