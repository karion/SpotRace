<?php

namespace App\Controller;

use App\Service\SettingsFormHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/settings')]
class AdminSettingsController extends AbstractController
{
    public function __construct(private readonly SettingsFormHandler $settingsForm)
    {
    }

    #[Route('', name: 'app_admin_settings_index', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('admin-settings', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Nieprawidłowy token CSRF.');
            }

            $this->settingsForm->updateGlobal($request);
            $this->addFlash('success', 'Ustawienia globalne zostały zapisane.');

            return $this->redirectToRoute('app_admin_settings_index');
        }

        return $this->render('admin/settings/index.html.twig', [
            'settings' => $this->settingsForm->globalRows(),
        ]);
    }
}
