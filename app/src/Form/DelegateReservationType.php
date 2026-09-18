<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class DelegateReservationType extends AbstractType
{
    public function getParent(): string
    {
        return ReservationVehicleType::class;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('target_user_id', ChoiceType::class, [
                'property_path' => 'targetUserId',
                'label' => 'Użytkownik docelowy',
                'placeholder' => 'Wybierz użytkownika',
                'choices' => $options['user_choices'],
                'constraints' => [new NotBlank(message: 'Wybierz użytkownika docelowego.')],
                'invalid_message' => 'Nie znaleziono użytkownika docelowego w Twojej firmie.',
            ])
            ->add('save', SubmitType::class, ['label' => 'Przekaż miejsce']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_token_id' => 'delegate-assigned',
            'vehicle_field' => 'target_vehicle_id',
            'plate_field' => 'target_license_plate',
            'user_choices' => [],
        ]);
        $resolver->setAllowedTypes('user_choices', 'array');
    }
}
