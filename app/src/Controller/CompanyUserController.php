<?php

namespace App\Controller;

use App\Entity\Company;
use App\Entity\User;
use App\Form\CompanyUserType;
use App\Repository\UserRepository;
use App\Service\CompanyRegistrationPolicy;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/company/users')]
class CompanyUserController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly CompanyRegistrationPolicy $registrationPolicy,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'app_company_user_index', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $company = $this->currentCompany();
        $user = (new User())->setCompany($company)->setRoles([User::ROLE_USER])->setStatus(User::STATUS_ACTIVE);
        $form = $this->createForm(CompanyUserType::class, $user, [
            'password_required' => true,
            'submit_label' => 'Dodaj użytkownika',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $password = (string) $form->get('plainPassword')->getData();
            $errors = $this->registrationPolicy->validate($user->getName(), $user->getEmail(), $password, $company);
            if ($this->users->findOneByEmail($user->getEmail())) {
                $errors[] = 'Użytkownik z tym emailem już istnieje.';
            }

            if ([] === $errors) {
                $user
                    ->setCompany($company)
                    ->setRoles([User::ROLE_USER])
                    ->markEmailVerified()
                    ->setPasswordHash($this->passwordHasher->hashPassword($user, $password));
                $this->entityManager->persist($user);
                $this->entityManager->flush();
                $this->addFlash('success', 'Użytkownik firmy został dodany.');

                return $this->redirectToRoute('app_company_user_index');
            }

            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
        }

        return $this->render('company/user/index.html.twig', [
            'company' => $company,
            'users' => $this->users->findByCompany($company),
            'createForm' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_company_user_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, User $managedUser): Response
    {
        $company = $this->currentCompany();
        $this->denyWhenOutsideCompany($managedUser, $company);

        $form = $this->createForm(CompanyUserType::class, $managedUser, [
            'password_required' => false,
            'submit_label' => 'Zapisz zmiany',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $password = (string) $form->get('plainPassword')->getData();
            $errors = [];
            if ('' !== $password) {
                $errors = $this->registrationPolicy->validatePassword($password, $company);
            }

            $existing = $this->users->findOneByEmail($managedUser->getEmail());
            if ($existing && $existing->getId() !== $managedUser->getId()) {
                $errors[] = 'Użytkownik z tym emailem już istnieje.';
            }

            if ([] === $errors) {
                if ('' !== $password) {
                    $managedUser->setPasswordHash($this->passwordHasher->hashPassword($managedUser, $password));
                }
                $this->entityManager->flush();
                $this->addFlash('success', 'Użytkownik firmy został zaktualizowany.');

                return $this->redirectToRoute('app_company_user_index');
            }

            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
        }

        return $this->render('company/user/edit.html.twig', [
            'managedUser' => $managedUser,
            'editForm' => $form->createView(),
        ]);
    }

    #[Route('/{id}/block', name: 'app_company_user_block', methods: ['POST'])]
    public function block(Request $request, User $managedUser): RedirectResponse
    {
        $this->mutateStatus($request, $managedUser, static fn (User $user): User => $user->block(), 'Użytkownik firmy został zablokowany.');

        return $this->redirectToRoute('app_company_user_index');
    }

    #[Route('/{id}/unlock', name: 'app_company_user_unlock', methods: ['POST'])]
    public function unlock(Request $request, User $managedUser): RedirectResponse
    {
        $this->mutateStatus($request, $managedUser, static fn (User $user): User => $user->activate(), 'Użytkownik firmy został odblokowany.');

        return $this->redirectToRoute('app_company_user_index');
    }

    /** @param callable(User): User $mutation */
    private function mutateStatus(Request $request, User $managedUser, callable $mutation, string $message): void
    {
        $company = $this->currentCompany();
        $this->denyWhenOutsideCompany($managedUser, $company);
        if (!$this->isCsrfTokenValid('company-user-'.$managedUser->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Nieprawidłowy token CSRF.');
        }
        if ($managedUser->getId() === $this->currentUser()->getId()) {
            throw $this->createAccessDeniedException('Nie możesz zmieniać statusu własnego konta.');
        }

        $mutation($managedUser);
        $this->entityManager->flush();
        $this->addFlash('success', $message);
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User || !$user->isCompanyAdmin()) {
            throw $this->createAccessDeniedException('Wymagana rola company admin.');
        }

        return $user;
    }

    private function currentCompany(): Company
    {
        $company = $this->currentUser()->getCompany();
        if (!$company instanceof Company) {
            throw $this->createAccessDeniedException('Konto company admin nie jest przypisane do firmy.');
        }

        return $company;
    }

    private function denyWhenOutsideCompany(User $managedUser, Company $company): void
    {
        if ($managedUser->getCompany()?->getId() !== $company->getId()) {
            throw $this->createAccessDeniedException('Nie możesz zarządzać użytkownikiem innej firmy.');
        }
    }
}
