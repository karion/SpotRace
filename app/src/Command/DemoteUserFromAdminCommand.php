<?php

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:user:demote-admin', description: 'Odbiera użytkownikowi rolę admin po emailu.')]
class DemoteUserFromAdminCommand extends Command
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Email użytkownika');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = (string) $input->getArgument('email');

        $user = $this->users->findOneByEmail($email);
        if (!$user) {
            $io->error('Nie znaleziono użytkownika o podanym emailu.');

            return Command::FAILURE;
        }

        $user->demoteFromAdmin();
        $this->entityManager->flush();

        $io->success(sprintf('Użytkownik %s nie ma już roli admin.', $user->getEmail()));

        if (!$this->users->hasActiveAdmin()) {
            $io->warning('Po tej operacji system nie ma aktywnego administratora.');
        }

        return Command::SUCCESS;
    }
}
