<?php

namespace App\Form;

use App\Model\ReservationData;
use App\Service\VehicleManager;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class ReservationVehicleType extends AbstractType
{
    public function __construct(private readonly VehicleManager $vehicles)
    {
    }

    public function getParent(): string
    {
        return ReservationDateType::class;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add($options['vehicle_field'], ChoiceType::class, [
                'property_path' => 'vehicleId',
                'label' => 'Zapisany pojazd',
                'choices' => $options['vehicle_choices'],
                'placeholder' => 'Wybierz pojazd lub wpisz rejestrację',
                'required' => false,
                'invalid_message' => 'Nieprawidłowy pojazd.',
            ])
            ->add($options['plate_field'], TextType::class, [
                'property_path' => 'licensePlate',
                'label' => 'Nowa rejestracja',
                'required' => false,
                'help' => 'Wpisanie nowej rejestracji zapisze pojazd na koncie osoby korzystającej z miejsca.',
                'constraints' => [new Callback(function (?string $value, ExecutionContextInterface $context): void {
                    try {
                        $this->vehicles->normalize($value ?? '');
                    } catch (\DomainException $exception) {
                        $context->addViolation($exception->getMessage());
                    }
                })],
            ]);
        $builder->addEventListener(\Symfony\Component\Form\FormEvents::POST_SUBMIT, function (\Symfony\Component\Form\FormEvent $event) use ($options): void {
            $data = $event->getData();
            if (!$data instanceof ReservationData) {
                return;
            }
            $hasPlate = '' !== trim($data->licensePlate ?? '');
            if ($data->vehicleId && $hasPlate) {
                $event->getForm()->addError(new \Symfony\Component\Form\FormError('Wybierz zapisany pojazd albo wpisz nową rejestrację.'));
            } elseif ($options['plate_required'] && !$data->vehicleId && !$hasPlate) {
                $event->getForm()->addError(new \Symfony\Component\Form\FormError('Podaj numer rejestracyjny przed zapisaniem rezerwacji.'));
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['vehicle_choices' => [], 'vehicle_field' => 'vehicle_id', 'plate_field' => 'license_plate', 'plate_required' => false]);
        $resolver->setAllowedTypes('plate_required', 'bool');
        $resolver->setAllowedTypes('vehicle_choices', 'array');
        $resolver->setAllowedTypes('vehicle_field', 'string');
        $resolver->setAllowedTypes('plate_field', 'string');
    }
}
