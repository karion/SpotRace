<?php

namespace App\Command;

use App\Entity\Company;
use App\Entity\CompanyParkingSpot;
use App\Entity\ParkingReservation;
use App\Entity\ParkingSpot;
use App\Entity\ParkingSpotAssignment;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:fixtures:load', description: 'Ładuje startowe dane developerskie.')]
class LoadFixturesCommand extends Command
{
    private const DEFAULT_PASSWORD = 'Password123!';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly KernelInterface $kernel,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!in_array($this->kernel->getEnvironment(), ['dev', 'test'], true)) {
            $io->error('Fixtures można ładować tylko w środowisku dev albo test.');

            return Command::FAILURE;
        }

        $today = (new \DateTimeImmutable())->setTime(0, 0);
        $tomorrow = $today->modify('+1 day');

        $acme = $this->company('Acme', 'acme');
        $globex = $this->company('Globex', 'globex');

        $admin = $this->user('admin@spotrace.test', 'Admin SpotRace', [User::ROLE_ADMIN], null);
        $acmeAdmin = $this->user('acme.admin@spotrace.test', 'Admin Acme', [User::ROLE_USER, User::ROLE_COMPANY_ADMIN], $acme);
        $acmeUser1 = $this->user('acme.user1@spotrace.test', 'Użytkownik Acme 1', [User::ROLE_USER], $acme);
        $acmeUser2 = $this->user('acme.user2@spotrace.test', 'Użytkownik Acme 2', [User::ROLE_USER], $acme);
        $globexAdmin = $this->user('globex.admin@spotrace.test', 'Admin Globex', [User::ROLE_USER, User::ROLE_COMPANY_ADMIN], $globex);
        $globexUser1 = $this->user('globex.user1@spotrace.test', 'Użytkownik Globex 1', [User::ROLE_USER], $globex);
        $globexUser2 = $this->user('globex.user2@spotrace.test', 'Użytkownik Globex 2', [User::ROLE_USER], $globex);

        $spotA1 = $this->parkingSpot('A-01', 'Miejsce przy wejściu A');
        $spotA2 = $this->parkingSpot('A-02', 'Miejsce przy wejściu A');
        $spotA3 = $this->parkingSpot('A-03', 'Miejsce dla gości Acme');
        $spotB1 = $this->parkingSpot('B-01', 'Miejsce przy wejściu B');
        $spotB2 = $this->parkingSpot('B-02', 'Miejsce przy wejściu B');
        $spotB3 = $this->parkingSpot('B-03', 'Miejsce dla gości Globex');

        $this->companySpot($acme, $spotA1, $today);
        $this->companySpot($acme, $spotA2, $today);
        $this->companySpot($acme, $spotA3, $today);
        $this->companySpot($globex, $spotB1, $today);
        $this->companySpot($globex, $spotB2, $today);
        $this->companySpot($globex, $spotB3, $today);

        $this->assignment($spotA1, $acmeAdmin, $admin, $today);
        $this->assignment($spotA2, $acmeUser1, $admin, $today);
        $this->assignment($spotB1, $globexAdmin, $admin, $today);
        $this->assignment($spotB2, $globexUser2, $admin, $today);

        $this->reservation($spotA3, $acmeUser2, $acmeUser2, $today, 'free');
        $this->reservation($spotA2, $acmeUser1, $acmeUser1, $tomorrow, 'assigned_confirmed');
        $this->reservation($spotB3, $globexUser1, $globexUser1, $today, 'free');
        $this->reservation($spotB2, $globexUser2, $globexUser2, $tomorrow, 'assigned_confirmed');

        $this->entityManager->flush();
        $io->success(sprintf(
            'Dane startowe załadowane. Hasło testowe dla kont: %s',
            self::DEFAULT_PASSWORD,
        ));

        return Command::SUCCESS;
    }

    private function company(string $name, string $slug): Company
    {
        $company = $this->entityManager->getRepository(Company::class)->findOneBy(['slug' => $slug]);
        if (!$company instanceof Company) {
            $company = new Company();
            $this->entityManager->persist($company);
        }

        return $company
            ->setName($name)
            ->setSlug($slug)
            ->activate();
    }

    /** @param array<int, string> $roles */
    private function user(string $email, string $name, array $roles, ?Company $company): User
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$user instanceof User) {
            $user = new User();
            $this->entityManager->persist($user);
        }

        $user
            ->setCompany($company)
            ->setEmail($email)
            ->setName($name)
            ->setRoles($roles)
            ->setStatus(User::STATUS_ACTIVE)
            ->setPasswordHash($this->passwordHasher->hashPassword($user, self::DEFAULT_PASSWORD))
            ->markEmailVerified();

        return $user;
    }

    private function parkingSpot(string $name, string $description): ParkingSpot
    {
        $spot = $this->entityManager->getRepository(ParkingSpot::class)->findOneBy(['name' => $name]);
        if (!$spot instanceof ParkingSpot) {
            $spot = new ParkingSpot();
            $this->entityManager->persist($spot);
        }

        return $spot
            ->setName($name)
            ->setDescription($description);
    }

    private function companySpot(Company $company, ParkingSpot $spot, \DateTimeImmutable $startsAt): void
    {
        $existing = $this->entityManager->getRepository(CompanyParkingSpot::class)->findOneBy([
            'company' => $company,
            'parkingSpot' => $spot,
        ]);
        if ($existing instanceof CompanyParkingSpot) {
            return;
        }

        $this->entityManager->persist((new CompanyParkingSpot())
            ->setCompany($company)
            ->setParkingSpot($spot)
            ->setStartsAt($startsAt));
    }

    private function assignment(ParkingSpot $spot, User $assignedUser, User $assignedByUser, \DateTimeImmutable $startsAt): void
    {
        $existing = $this->entityManager->getRepository(ParkingSpotAssignment::class)->findOneBy([
            'parkingSpot' => $spot,
            'assignedUser' => $assignedUser,
        ]);
        if ($existing instanceof ParkingSpotAssignment) {
            return;
        }

        $this->entityManager->persist((new ParkingSpotAssignment())
            ->setParkingSpot($spot)
            ->setAssignedUser($assignedUser)
            ->setAssignedByUser($assignedByUser)
            ->setStartsAt($startsAt));
    }

    private function reservation(ParkingSpot $spot, User $reservedForUser, User $createdByUser, \DateTimeImmutable $date, string $type): void
    {
        $reservationRepository = $this->entityManager->getRepository(ParkingReservation::class);
        if ($reservationRepository->findOneBy(['parkingSpot' => $spot, 'reservationDate' => $date]) instanceof ParkingReservation) {
            return;
        }

        if ($reservationRepository->findOneBy(['reservedForUser' => $reservedForUser, 'reservationDate' => $date]) instanceof ParkingReservation) {
            return;
        }

        $this->entityManager->persist((new ParkingReservation())
            ->setParkingSpot($spot)
            ->setReservedForUser($reservedForUser)
            ->setCreatedByUser($createdByUser)
            ->setReservationDate($date)
            ->setType($type));
    }
}
