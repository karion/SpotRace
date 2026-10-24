<?php

namespace App\Form;

use App\Service\CompanyRegistrationSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class CompanyRegistrationSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        foreach (CompanyRegistrationSettings::FIELDS as $field => $definition) {
            $row = $builder->create($field, FormType::class, ['label' => $definition['label']]);
            $row->add('mode', ChoiceType::class, ['label' => 'Źródło wartości', 'choices' => ['Dziedzicz globalne' => 'inherit', 'Własna wartość' => 'own']]);
            $valueOptions = ['label' => 'Własna wartość', 'required' => false];
            $type = TextType::class;
            if ('bool' === $definition['type']) {
                $type = ChoiceType::class;
                $valueOptions['choices'] = ['Tak' => '1', 'Nie' => '0'];
                $valueOptions['placeholder'] = false;
            } elseif ('domains' === $definition['type']) {
                $type = TextareaType::class;
                $valueOptions['help'] = 'Domeny oddziel przecinkiem lub nowym wierszem. Pusta własna lista dopuszcza wszystkie domeny. Bez adresów e-mail, URL i wildcardów.';
            }
            $row->add('value', $type, $valueOptions);
            $builder->add($row);
        }
        $builder->add('save', SubmitType::class, ['label' => 'Zapisz zasady rejestracji']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['csrf_token_id' => 'company-registration-settings', 'constraints' => [new Callback(static function (array $data, ExecutionContextInterface $context): void {
            foreach (CompanyRegistrationSettings::FIELDS as $field => $definition) {
                if ('own' !== ($data[$field]['mode'] ?? null)) {
                    continue;
                }
                $value = $data[$field]['value'] ?? '';
                if ('integer' === $definition['type'] && false === filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
                    $context->buildViolation('Podaj dodatnią liczbę całkowitą.')->atPath('['.$field.'][value]')->addViolation();
                }
                if ('domains' === $definition['type']) {
                    foreach (CompanyRegistrationSettings::domains((string) $value) as $domain) {
                        if (!str_contains($domain, '.') || !filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
                            $context->buildViolation('Podaj poprawne domeny, np. example.com.')->atPath('['.$field.'][value]')->addViolation();
                            break;
                        }
                    }
                }
            }
        })]]);
    }
}
