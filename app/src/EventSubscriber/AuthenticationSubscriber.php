<?php

namespace App\EventSubscriber;

use App\Service\AuthService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class AuthenticationSubscriber implements EventSubscriberInterface
{
    /** @var array<int, string> */
    private array $publicRoutes = [
        'app_login',
        'app_register',
        'app_verify_email',
        'app_forgot_password',
        'app_reset_password',
    ];

    public function __construct(
        private readonly AuthService $authService,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = (string) $request->attributes->get('_route', '');

        if ('' === $route || str_starts_with($route, '_')) {
            return;
        }

        if (in_array($route, $this->publicRoutes, true)) {
            return;
        }

        if (!$this->authService->isLoggedIn()) {
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_login')));

            return;
        }

        if (!str_starts_with($route, 'app_admin_')) {
            return;
        }

        $user = $this->authService->getCurrentUser();
        if (!$user || !$user->isAdmin()) {
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_home')));
        }
    }
}
