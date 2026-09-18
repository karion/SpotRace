<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ConfirmReservationType;
use App\Form\DelegateReservationType;
use App\Form\FreeReservationType;
use App\Form\ReleaseReservationType;
use App\Form\ReservationFormFactory;
use App\Model\ReservationData;
use App\Service\ReservationManager;
use App\Service\ReservationPolicy;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class ReservationController extends AbstractController
{
    public function __construct(private readonly ReservationFormFactory $forms, private readonly ReservationManager $reservations)
    {
    }

    #[Route('/reservations/free', name: 'app_reservation_free', methods: ['POST'])]
    public function reserveFree(Request $request, #[CurrentUser] User $user): Response
    {
        return $this->submit($request, $this->forms->create(FreeReservationType::class, $user), fn (ReservationData $data) => $this->reservations->reserveFree($user, $data), 'Wolne miejsce zostało zarezerwowane.');
    }

    #[Route('/reservations/confirm-assigned', name: 'app_reservation_confirm_assigned', methods: ['POST'])]
    public function confirmAssigned(Request $request, #[CurrentUser] User $user): Response
    {
        return $this->submit($request, $this->forms->create(ConfirmReservationType::class, $user), fn (ReservationData $data) => $this->reservations->confirmAssigned($user, $data), 'Przypisane miejsce zostało potwierdzone.');
    }

    #[Route('/reservations/delegate-assigned', name: 'app_reservation_delegate_assigned', methods: ['GET'])]
    public function delegateAssignedForm(Request $request, #[CurrentUser] User $user, ReservationPolicy $policy): Response
    {
        $data = new ReservationData();
        $data->date = $request->query->getString('date');
        try {
            $date = $policy->parseDate($data->date);
            $assignment = $this->reservations->assignedSpot($user, $date);
        } catch (\DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('app_home');
        }

        return $this->render('home/delegate_assigned.html.twig', [
            'date' => $date,
            'assignment' => $assignment,
            'form' => $this->forms->create(DelegateReservationType::class, $user, $data)->createView(),
        ]);
    }

    #[Route('/reservations/delegate-assigned/submit', name: 'app_reservation_delegate_assigned_submit', methods: ['POST'])]
    public function delegateAssignedSubmit(Request $request, #[CurrentUser] User $user): Response
    {
        return $this->submit($request, $this->forms->create(DelegateReservationType::class, $user), fn (ReservationData $data) => $this->reservations->delegateAssigned($user, $data), 'Przypisane miejsce zostało przekazane innej osobie.');
    }

    #[Route('/reservations/release', name: 'app_reservation_release', methods: ['POST'])]
    public function release(Request $request, #[CurrentUser] User $user): Response
    {
        return $this->submit($request, $this->forms->create(ReleaseReservationType::class, $user), fn (ReservationData $data) => $this->reservations->release($user, $data), 'Twoja rezerwacja została zwolniona.');
    }

    /** @param callable(ReservationData): void $operation */
    private function submit(Request $request, FormInterface $form, callable $operation, string $message): Response
    {
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var ReservationData $data */
            $data = $form->getData();
            try {
                $operation($data);
                $this->addFlash('success', $message);

                return $this->redirectToRoute('app_home', ['date' => $data->date]);
            } catch (\DomainException $exception) {
                $form->addError(new FormError($exception->getMessage()));
            }
        }

        return $this->render('home/reservation_form.html.twig', ['form' => $form->createView()], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
    }
}
