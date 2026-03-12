<?php

namespace App\Controller;

use App\Service\AuthService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(AuthService $authService): Response
    {
        return $this->render('home/index.html.twig', [
            'user' => $authService->getCurrentUser(),
        ]);
    }
}
