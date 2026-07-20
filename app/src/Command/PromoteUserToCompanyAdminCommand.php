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

#[AsCommand(name: 'app:user:promote-company-admin', description: 'Nadaje użytkownikowi rolę company admin po emailu.')]
class PromoteUserToCompanyAdminCommand extends Command
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
        $user = $this->users->findOneByEmail((string) $input->getArgument('email'));
        if (!$user) {
            $io->error('Nie znaleziono użytkownika o podanym emailu.');

            return Command::FAILURE;
        }
        if (null === $user->getCompany()) {
            $io->error('Company admin musi być przypisany do firmy.');

            return Command::FAILURE;
        }

        $user->promoteToCompanyAdmin();
        $this->entityManager->flush();
        $io->success(sprintf('Użytkownik %s ma teraz rolę company admin.', $user->getEmail()));

        return Command::SUCCESS;
    }
}
