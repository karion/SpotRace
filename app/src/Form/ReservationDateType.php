<?php

namespace App\Form;

use App\Model\ReservationData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ReservationDateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('date', HiddenType::class, [
            'empty_data' => '',
            'constraints' => [new Assert\NotBlank(message: 'Wybierz dzień rezerwacji.'), new Assert\Date(message: 'Nieprawidłowa data rezerwacji.')],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ReservationData::class,
            'method' => 'POST',
            'csrf_message' => 'Nieprawidłowy token CSRF. Odśwież formularz.',
            'allow_extra_fields' => false,
            'extra_fields_message' => 'Formularz zawiera nieobsługiwane pola.',
        ]);
    }
}
