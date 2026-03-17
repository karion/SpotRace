<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\AdminUserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/users')]
class AdminUserController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly AdminUserService $adminUserService,
    ) {
    }

    #[Route('', name: 'app_admin_user_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/user/index.html.twig', [
            'users' => $this->users->findBy([], ['email' => 'ASC']),
        ]);
    }

    #[Route('/{id}/block', name: 'app_admin_user_block', methods: ['POST'])]
    public function block(Request $request, User $user): RedirectResponse
    {
        $this->validateCsrf($request, $user);
        $this->adminUserService->block($user, $this->getUser());

        $this->addFlash('success', sprintf('Użytkownik %s został zablokowany.', $user->getEmail()));

        return $this->redirectToRoute('app_admin_user_index');
    }

    #[Route('/{id}/force-password-reset', name: 'app_admin_user_force_password_reset', methods: ['POST'])]
    public function forcePasswordReset(Request $request, User $user): RedirectResponse
    {
        $this->validateCsrf($request, $user);
        $this->adminUserService->forcePasswordReset($user, $this->getUser());

        $this->addFlash('success', sprintf('Wymuszono reset hasła dla użytkownika %s.', $user->getEmail()));

        return $this->redirectToRoute('app_admin_user_index');
    }

    #[Route('/{id}/unlock', name: 'app_admin_user_unlock', methods: ['POST'])]
    public function unlock(Request $request, User $user): RedirectResponse
    {
        $this->validateCsrf($request, $user);
        $this->adminUserService->unlock($user, $this->getUser());

        $this->addFlash('success', sprintf('Użytkownik %s został odblokowany i musi zresetować hasło.', $user->getEmail()));

        return $this->redirectToRoute('app_admin_user_index');
    }

    private function validateCsrf(Request $request, User $managedUser): void
    {
        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('admin-user-'.$managedUser->getId(), $token)) {
            throw $this->createAccessDeniedException('Nieprawidłowy token CSRF.');
        }
    }
}
