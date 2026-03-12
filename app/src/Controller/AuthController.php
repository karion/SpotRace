<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\AuthService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AuthController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $entityManager,
        private readonly AuthService $authService,
        private readonly string $allowedEmailDomains,
    ) {
    }

    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(Request $request): Response
    {
        if ($this->authService->isLoggedIn()) {
            return $this->redirectToRoute('app_home');
        }

        if ($request->isMethod('POST')) {
            $email = (string) $request->request->get('email', '');
            $password = (string) $request->request->get('password', '');

            $user = $this->users->findOneByEmail($email);
            if (!$user || !password_verify($password, $user->getPasswordHash())) {
                $this->addFlash('error', 'Nieprawidłowy email lub hasło.');
            } elseif (!$user->isEmailVerified()) {
                $this->addFlash('error', 'Potwierdź email zanim się zalogujesz.');
            } else {
                $this->authService->login($user);

                return $this->redirectToRoute('app_home');
            }
        }

        return $this->render('auth/login.html.twig');
    }

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(Request $request): Response
    {
        if ($this->authService->isLoggedIn()) {
            return $this->redirectToRoute('app_home');
        }

        if ($request->isMethod('POST')) {
            $name = trim((string) $request->request->get('name', ''));
            $email = mb_strtolower(trim((string) $request->request->get('email', '')));
            $password = (string) $request->request->get('password', '');

            $errors = $this->validateRegistrationData($name, $email, $password);
            if ($this->users->findOneByEmail($email)) {
                $errors[] = 'Użytkownik z tym emailem już istnieje.';
            }

            if ([] === $errors) {
                $token = bin2hex(random_bytes(32));
                $user = (new User())
                    ->setName($name)
                    ->setEmail($email)
                    ->setPasswordHash(password_hash($password, PASSWORD_ARGON2ID))
                    ->setRoles(['ROLE_USER'])
                    ->setEmailVerificationToken($token);

                $this->entityManager->persist($user);
                $this->entityManager->flush();

                $verifyLink = $this->generateUrl('app_verify_email', ['token' => $token]);
                $this->addFlash('success', sprintf('Konto utworzone. Potwierdź email: %s', $verifyLink));

                return $this->redirectToRoute('app_login');
            }

            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
        }

        return $this->render('auth/register.html.twig');
    }

    #[Route('/verify-email/{token}', name: 'app_verify_email', methods: ['GET'])]
    public function verifyEmail(string $token): Response
    {
        $user = $this->users->findOneByVerificationToken($token);
        if (!$user) {
            $this->addFlash('error', 'Nieprawidłowy token potwierdzający email.');

            return $this->redirectToRoute('app_login');
        }

        $user->markEmailVerified();
        $this->entityManager->flush();

        $this->addFlash('success', 'Email został potwierdzony. Możesz się zalogować.');

        return $this->redirectToRoute('app_login');
    }

    #[Route('/forgot-password', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function forgotPassword(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $email = (string) $request->request->get('email', '');
            $user = $this->users->findOneByEmail($email);

            if ($user) {
                $token = bin2hex(random_bytes(32));
                $user
                    ->setPasswordResetToken($token)
                    ->setPasswordResetExpiresAt(new \DateTimeImmutable('+1 hour'));
                $this->entityManager->flush();

                $resetLink = $this->generateUrl('app_reset_password', ['token' => $token]);
                $this->addFlash('success', sprintf('Link do resetu hasła: %s', $resetLink));
            } else {
                $this->addFlash('success', 'Jeśli konto istnieje, link do resetu został wygenerowany.');
            }

            return $this->redirectToRoute('app_login');
        }

        return $this->render('auth/forgot_password.html.twig');
    }

    #[Route('/reset-password/{token}', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function resetPassword(Request $request, string $token): Response
    {
        $user = $this->users->findOneByPasswordResetToken($token);
        if (!$user || !$user->getPasswordResetExpiresAt() || $user->getPasswordResetExpiresAt() < new \DateTimeImmutable()) {
            $this->addFlash('error', 'Token resetu jest nieprawidłowy lub wygasł.');

            return $this->redirectToRoute('app_forgot_password');
        }

        if ($request->isMethod('POST')) {
            $password = (string) $request->request->get('password', '');
            if (mb_strlen($password) < 8) {
                $this->addFlash('error', 'Hasło musi mieć minimum 8 znaków.');
            } else {
                $user
                    ->setPasswordHash(password_hash($password, PASSWORD_ARGON2ID))
                    ->setPasswordResetToken(null)
                    ->setPasswordResetExpiresAt(null);

                $this->entityManager->flush();
                $this->addFlash('success', 'Hasło zostało zmienione.');

                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('auth/reset_password.html.twig', ['token' => $token]);
    }

    #[Route('/logout', name: 'app_logout', methods: ['POST'])]
    public function logout(): Response
    {
        $this->authService->logout();
        $this->addFlash('success', 'Wylogowano.');

        return $this->redirectToRoute('app_login');
    }

    /** @return array<int, string> */
    private function validateRegistrationData(string $name, string $email, string $password): array
    {
        $errors = [];

        if ('' === $name) {
            $errors[] = 'Imię użytkownika jest wymagane.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Podaj poprawny adres email.';
        } elseif (!$this->isDomainAllowed($email)) {
            $errors[] = 'Domena email nie jest dozwolona.';
        }

        if (mb_strlen($password) < 8) {
            $errors[] = 'Hasło musi mieć minimum 8 znaków.';
        }

        return $errors;
    }

    private function isDomainAllowed(string $email): bool
    {
        $allowedDomains = array_values(array_filter(array_map('trim', explode(',', $this->allowedEmailDomains))));
        if ([] === $allowedDomains) {
            return true;
        }

        $parts = explode('@', $email);
        $domain = mb_strtolower((string) end($parts));

        return in_array($domain, array_map('mb_strtolower', $allowedDomains), true);
    }
}
