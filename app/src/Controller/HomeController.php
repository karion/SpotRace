<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ConfirmReservationType;
use App\Form\FreeReservationType;
use App\Form\ReleaseReservationType;
use App\Form\ReservationFormFactory;
use App\Model\ReservationData;
use App\Service\ReservationCalendar;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(#[CurrentUser] User $user, ReservationCalendar $calendar, ReservationFormFactory $forms): Response
    {
        $days = $calendar->days($user);
        $vehicles = $forms->vehicleChoices($user);
        foreach ($days as &$day) {
            $data = new ReservationData();
            $data->date = $day['date']->format('Y-m-d');
            if ($day['canManageAssigned']) {
                $day['confirmForm'] = $forms->view($forms->create(ConfirmReservationType::class, $user, clone $data, $vehicles), 'confirm_'.$data->date);
            }
            if ($day['canReleaseReservation']) {
                $day['releaseForm'] = $forms->view($forms->create(ReleaseReservationType::class, $user, clone $data), 'release_'.$data->date);
            }
            $day['freeForms'] = [];
            if ($day['canReserveFree']) {
                foreach ($day['availableSpots'] as $spot) {
                    $spotData = clone $data;
                    $spotData->spotId = $spot->getId();
                    $day['freeForms'][$spot->getId()] = $forms->view($forms->create(FreeReservationType::class, $user, $spotData, $vehicles), 'free_'.$data->date.'_'.$spot->getId());
                }
            }
        }
        unset($day);

        return $this->render('home/index.html.twig', ['user' => $user, 'days' => $days]);
    }
}
