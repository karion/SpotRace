<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\SettingKeys;
use App\Service\SettingsFormHandler;
use App\Service\SettingsResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/company/settings')]
class CompanySettingsController extends AbstractController
{
    public function __construct(
        private readonly SettingsFormHandler $settingsForm,
        private readonly SettingsResolver $settings,
    ) {
    }

    #[Route('', name: 'app_company_settings', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || !$user->getCompany()) {
            throw $this->createAccessDeniedException('Konto nie jest przypisane do firmy.');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('company-settings-'.$user->getCompany()->getId(), (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Nieprawidłowy token CSRF.');
            }
            $this->settingsForm->updateCompanyLicensePlateRequirement($request, $user->getCompany());
            $this->addFlash('success', 'Ustawienia firmy zostały zapisane.');

            return $this->redirectToRoute('app_company_settings');
        }

        return $this->render('company/settings/index.html.twig', [
            'company' => $user->getCompany(),
            'requireLicensePlate' => $this->settings->bool(SettingKeys::RESERVATION_REQUIRE_LICENSE_PLATE, $user->getCompany()),
        ]);
    }
}
