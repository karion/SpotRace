<?php

namespace App\Form;

use App\Entity\ParkingSpotAssignment;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class ParkingSpotAssignmentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('assignedUser', EntityType::class, [
                'label' => 'Użytkownik',
                'class' => User::class,
                'choices' => $options['users'],
                'choice_label' => static fn (User $user): string => sprintf('%s (%s)', $user->getName(), $user->getEmail()),
                'placeholder' => 'Wybierz użytkownika',
                'constraints' => [
                    new NotBlank(message: 'Wybór użytkownika jest wymagany.'),
                ],
            ])
            ->add('startsAt', DateType::class, [
                'label' => 'Data od',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'constraints' => [
                    new NotBlank(message: 'Data początku przypisania jest wymagana.'),
                ],
            ])
            ->add('endsAt', DateType::class, [
                'label' => 'Data do (opcjonalnie)',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
                'empty_data' => '',
            ])
            ->add('save', SubmitType::class, [
                'label' => $options['submit_label'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ParkingSpotAssignment::class,
            'users' => [],
            'submit_label' => 'Zapisz przypisanie',
        ]);

        $resolver->setAllowedTypes('users', 'array');
        $resolver->setAllowedTypes('submit_label', 'string');
    }
}
