<?php

namespace App\Controller;

use App\Entity\Company;
use App\Entity\CompanyRegistrationToken;
use App\Form\CompanyType;
use App\Repository\CompanyParkingSpotRepository;
use App\Repository\CompanyRegistrationTokenRepository;
use App\Repository\CompanyRepository;
use App\Repository\UserRepository;
use App\Service\SettingKeys;
use App\Service\SettingsFormHandler;
use App\Service\SettingsResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/admin/companies')]
class AdminCompanyController extends AbstractController
{
    public function __construct(
        private readonly CompanyRepository $companies,
        private readonly UserRepository $users,
        private readonly CompanyParkingSpotRepository $companySpots,
        private readonly CompanyRegistrationTokenRepository $registrationTokens,
        private readonly EntityManagerInterface $entityManager,
        private readonly SettingsResolver $settings,
        private readonly SettingsFormHandler $settingsForm,
    ) {
    }

    #[Route('', name: 'app_admin_company_index', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $company = new Company();
        $form = $this->createForm(CompanyType::class, $company, ['submit_label' => 'Dodaj firmę']);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($company);
            $this->entityManager->flush();
            $this->addFlash('success', 'Firma została dodana.');

            return $this->redirectToRoute('app_admin_company_index');
        }

        return $this->render('admin/company/index.html.twig', [
            'companies' => $this->companies->findBy([], ['name' => 'ASC']),
            'createForm' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_company_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Company $company): Response
    {
        $form = $this->createForm(CompanyType::class, $company, ['submit_label' => 'Zapisz zmiany']);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'Firma została zaktualizowana.');

            return $this->redirectToRoute('app_admin_company_index');
        }

        return $this->render('admin/company/edit.html.twig', [
            'company' => $company,
            'editForm' => $form->createView(),
            'tokens' => $this->registrationTokens->findByCompany($company),
            'registrationLinks' => $this->registrationLinks($company),
            'settings' => $this->settingsForm->companyRows($company),
        ]);
    }

    #[Route('/{id}/settings', name: 'app_admin_company_settings', methods: ['POST'])]
    public function updateSettings(Request $request, Company $company): RedirectResponse
    {
        $this->validateCsrf($request, $company);
        $this->settingsForm->updateCompany($request, $company);
        $this->addFlash('success', 'Ustawienia firmy zostały zapisane.');

        return $this->redirectToRoute('app_admin_company_edit', ['id' => $company->getId()]);
    }

    #[Route('/{id}/block', name: 'app_admin_company_block', methods: ['POST'])]
    public function block(Request $request, Company $company): RedirectResponse
    {
        $this->validateCsrf($request, $company);
        $company->block();
        $this->entityManager->flush();
        $this->addFlash('success', 'Firma została zablokowana.');

        return $this->redirectToRoute('app_admin_company_index');
    }

    #[Route('/{id}/unlock', name: 'app_admin_company_unlock', methods: ['POST'])]
    public function unlock(Request $request, Company $company): RedirectResponse
    {
        $this->validateCsrf($request, $company);
        $company->activate();
        $this->entityManager->flush();
        $this->addFlash('success', 'Firma została odblokowana.');

        return $this->redirectToRoute('app_admin_company_index');
    }

    #[Route('/{id}/delete', name: 'app_admin_company_delete', methods: ['POST'])]
    public function delete(Request $request, Company $company): RedirectResponse
    {
        $this->validateCsrf($request, $company);
        if ($this->users->hasUsersForCompany($company) || $this->companySpots->hasCurrentOrFutureForCompany($company, (new \DateTimeImmutable())->setTime(0, 0))) {
            $this->addFlash('error', 'Firmę można usunąć tylko bez użytkowników oraz bieżących lub przyszłych miejsc.');

            return $this->redirectToRoute('app_admin_company_index');
        }

        $this->entityManager->remove($company);
        $this->entityManager->flush();
        $this->addFlash('success', 'Firma została usunięta.');

        return $this->redirectToRoute('app_admin_company_index');
    }

    #[Route('/{id}/tokens/create', name: 'app_admin_company_token_create', methods: ['POST'])]
    public function createToken(Request $request, Company $company): RedirectResponse
    {
        $this->validateCsrf($request, $company);
        $ttlHours = max(1, $this->settings->int(SettingKeys::REGISTRATION_TOKEN_TTL_HOURS, $company));
        $token = (new CompanyRegistrationToken())
            ->setCompany($company)
            ->setExpiresAt(new \DateTimeImmutable(sprintf('+%d hours', $ttlHours)));
        $this->entityManager->persist($token);
        $this->entityManager->flush();
        $this->addFlash('success', 'Link rejestracyjny został wygenerowany.');

        return $this->redirectToRoute('app_admin_company_edit', ['id' => $company->getId()]);
    }

    #[Route('/tokens/{token}/revoke', name: 'app_admin_company_token_revoke', methods: ['POST'])]
    public function revokeToken(Request $request, CompanyRegistrationToken $token): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('company-token-'.$token->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Nieprawidłowy token CSRF.');
        }

        $token->revoke();
        $this->entityManager->flush();
        $this->addFlash('success', 'Link rejestracyjny został unieważniony.');

        return $this->redirectToRoute('app_admin_company_edit', ['id' => $token->getCompany()->getId()]);
    }

    private function validateCsrf(Request $request, Company $company): void
    {
        if (!$this->isCsrfTokenValid('admin-company-'.$company->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Nieprawidłowy token CSRF.');
        }
    }

    /** @return array<string, string> */
    private function registrationLinks(Company $company): array
    {
        $links = [];
        foreach ($this->registrationTokens->findByCompany($company) as $token) {
            $links[$token->getId()] = $this->generateUrl('app_register', [
                'companySlug' => $company->getSlug(),
                'token' => $token->getToken(),
            ], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        return $links;
    }
}
