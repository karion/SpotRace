<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\RequestStack;

class AuthService
{
    private const SESSION_USER_ID = 'auth_user_id';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly UserRepository $users,
    ) {
    }

    public function login(User $user): void
    {
        $session = $this->requestStack->getSession();
        $session->migrate(true);
        $session->set(self::SESSION_USER_ID, $user->getId());
    }

    public function logout(): void
    {
        $session = $this->requestStack->getSession();
        $session->remove(self::SESSION_USER_ID);
        $session->invalidate();
    }

    public function getCurrentUser(): ?User
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return null;
        }

        $session = $request->getSession();
        $userId = $session->get(self::SESSION_USER_ID);
        if (!is_string($userId) || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $userId)) {
            return null;
        }

        return $this->users->find($userId);
    }

    public function isLoggedIn(): bool
    {
        return null !== $this->getCurrentUser();
    }
}
