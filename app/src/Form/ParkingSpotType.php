<?php

namespace App\Form;

use App\Entity\ParkingLocation;
use App\Entity\ParkingSpot;
use App\Repository\ParkingLocationRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Regex;

class ParkingSpotType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nazwa',
                'constraints' => [
                    new NotBlank(message: 'Nazwa miejsca postojowego jest wymagana.'),
                    new Length(max: 120, maxMessage: 'Nazwa może mieć maksymalnie {{ limit }} znaków.'),
                ],
            ])
            ->add('location', EntityType::class, [
                'class' => ParkingLocation::class,
                'choice_label' => 'name',
                'query_builder' => fn (ParkingLocationRepository $repository) => $repository->createQueryBuilder('location')->orderBy('location.name', 'ASC'),
                'label' => 'Lokalizacja',
                'placeholder' => 'Wybierz lokalizację',
                'required' => true,
                'constraints' => [new NotNull(message: 'Wybierz lokalizację miejsca postojowego.')],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Opis (tylko tekst)',
                'attr' => ['rows' => $options['description_rows']],
                'constraints' => [
                    new NotBlank(message: 'Opis miejsca postojowego jest wymagany.'),
                    new Regex(
                        pattern: '/^[^<>]*$/u',
                        message: 'Opis może zawierać wyłącznie tekst (bez HTML).',
                    ),
                ],
            ])
            ->add('save', SubmitType::class, [
                'label' => $options['submit_label'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ParkingSpot::class,
            'submit_label' => 'Zapisz',
            'description_rows' => 4,
        ]);

        $resolver->setAllowedTypes('submit_label', 'string');
        $resolver->setAllowedTypes('description_rows', 'int');
    }
}
