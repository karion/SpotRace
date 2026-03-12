<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class AuthController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly MailerInterface $mailer,
        private readonly string $allowedEmailDomains,
        private readonly string $mailerFrom,
    ) {
    }

    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        return $this->render('auth/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(Request $request): Response
    {
        if ($this->getUser()) {
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
                    ->setRoles(['ROLE_USER'])
                    ->setStatus(User::STATUS_ACTIVE)
                    ->setEmailVerificationToken($token);

                $user->setPasswordHash($this->passwordHasher->hashPassword($user, $password));

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

            if ($user && $user->canRequestPasswordReset()) {
                $token = bin2hex(random_bytes(32));
                $user
                    ->setPasswordResetToken($token)
                    ->setPasswordResetExpiresAt(new \DateTimeImmutable('+1 hour'));
                $this->entityManager->flush();

                $resetLink = $this->generateUrl('app_reset_password', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);
                $this->sendPasswordResetEmail($user, $resetLink);
            }

            $this->addFlash('success', 'Jeśli konto istnieje, link do resetu został wysłany na email.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('auth/forgot_password.html.twig');
    }

    #[Route('/reset-password/{token}', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function resetPassword(Request $request, string $token): Response
    {
        $user = $this->users->findOneByPasswordResetToken($token);
        if (!$user || !$user->canRequestPasswordReset() || !$user->getPasswordResetExpiresAt() || $user->getPasswordResetExpiresAt() < new \DateTimeImmutable()) {
            $this->addFlash('error', 'Token resetu jest nieprawidłowy lub wygasł.');

            return $this->redirectToRoute('app_forgot_password');
        }

        if ($request->isMethod('POST')) {
            $password = (string) $request->request->get('password', '');
            $passwordErrors = $this->validatePassword($password);
            if ([] !== $passwordErrors) {
                foreach ($passwordErrors as $error) {
                    $this->addFlash('error', $error);
                }
            } else {
                $user
                    ->setPasswordHash($this->passwordHasher->hashPassword($user, $password))
                    ->setPasswordResetToken(null)
                    ->setPasswordResetExpiresAt(null)
                    ->activate();

                $this->entityManager->flush();
                $this->addFlash('success', 'Hasło zostało zmienione.');

                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('auth/reset_password.html.twig', ['token' => $token]);
    }

    #[Route('/logout', name: 'app_logout', methods: ['POST'])]
    public function logout(): never
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    private function sendPasswordResetEmail(User $user, string $resetLink): void
    {
        $message = (new Email())
            ->from($this->mailerFrom)
            ->to($user->getEmail())
            ->subject('Reset hasła w SpotRace')
            ->text("Cześć {$user->getName()},\n\nAby zresetować hasło, użyj linku:\n{$resetLink}\n\nLink jest ważny przez 1 godzinę.");

        try {
            $this->mailer->send($message);
        } catch (TransportExceptionInterface) {
            $this->addFlash('error', 'Nie udało się wysłać wiadomości email. Spróbuj ponownie później.');
        }
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

        $errors = [...$errors, ...$this->validatePassword($password)];

        return $errors;
    }

    /** @return array<int, string> */
    private function validatePassword(string $password): array
    {
        $errors = [];

        if (mb_strlen($password) < 8) {
            $errors[] = 'Hasło musi mieć minimum 8 znaków.';
        }

        if (!preg_match('/\p{Lu}/u', $password) || !preg_match('/\p{Ll}/u', $password)) {
            $errors[] = 'Hasło musi zawierać co najmniej 1 wielką i 1 małą literę.';
        }

        if (!preg_match('/\d/', $password)) {
            $errors[] = 'Hasło musi zawierać co najmniej 1 cyfrę.';
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
