<?php

namespace App\Form;

use App\Entity\User;
use App\Model\ReservationData;
use App\Repository\UserRepository;
use App\Repository\VehicleRepository;
use App\Service\SettingKeys;
use App\Service\SettingsResolver;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ReservationFormFactory
{
    public function __construct(
        private readonly FormFactoryInterface $forms,
        private readonly UrlGeneratorInterface $urls,
        private readonly VehicleRepository $vehicles,
        private readonly UserRepository $users,
        private readonly SettingsResolver $settings,
    ) {
    }

    /**
     * @param class-string<FormTypeInterface> $type
     * @param array<string, string>|null      $vehicleChoices
     */
    public function create(string $type, User $actor, ?ReservationData $data = null, ?array $vehicleChoices = null): FormInterface
    {
        $options = ['action' => $this->urls->generate(match ($type) {
            FreeReservationType::class => 'app_reservation_free',
            ConfirmReservationType::class => 'app_reservation_confirm_assigned',
            DelegateReservationType::class => 'app_reservation_delegate_assigned_submit',
            ReleaseReservationType::class => 'app_reservation_release',
            default => throw new \InvalidArgumentException('Nieobsługiwany formularz rezerwacji.'),
        })];
        if (ReleaseReservationType::class !== $type) {
            $options['plate_required'] = $actor->getCompany() && $this->settings->bool(SettingKeys::RESERVATION_REQUIRE_LICENSE_PLATE, $actor->getCompany());
            $options['vehicle_choices'] = $vehicleChoices ?? $this->vehicleChoices($actor, DelegateReservationType::class === $type);
        }
        if (DelegateReservationType::class === $type) {
            $options['user_choices'] = [];
            foreach ($actor->getCompany() ? $this->users->findByCompany($actor->getCompany()) : [] as $user) {
                if ($user->getId() !== $actor->getId()) {
                    $options['user_choices'][$user->getName().' ('.$user->getEmail().')'] = $user->getId();
                }
            }
        }

        return $this->forms->createNamed('', $type, $data ?? new ReservationData(), $options);
    }

    /** @return array<string, string> */
    public function vehicleChoices(User $actor, bool $delegating = false): array
    {
        $choices = [];
        $vehicles = $delegating
            ? ($actor->getCompany() ? $this->vehicles->findByCompany($actor->getCompany()) : [])
            : $this->vehicles->findByOwner($actor);
        foreach ($vehicles as $vehicle) {
            $label = $vehicle->getLicensePlate();
            if ($delegating) {
                $label = $vehicle->getOwner()->getName().' ('.$vehicle->getOwner()->getEmail().') — '.$label;
            }
            $choices[$label] = $vehicle->getId();
        }

        return $choices;
    }

    public function view(FormInterface $form, string $id): FormView
    {
        $view = $form->createView();
        $view->vars['id'] = $id;
        foreach ($view->children as $name => $child) {
            $child->vars['id'] = $id.'_'.$name;
        }

        return $view;
    }
}
