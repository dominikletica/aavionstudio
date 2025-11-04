<?php

declare(strict_types=1);

namespace App\Form\Setup;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Url;

final class EnvironmentSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('environment', ChoiceType::class, [
                'label' => 'installer.environment.form.environment',
                'choices' => [
                    'installer.environment.form.environment_choices.development' => 'dev',
                    'installer.environment.form.environment_choices.testing' => 'test',
                    'installer.environment.form.environment_choices.production' => 'prod',
                ],
                'placeholder' => false,
                'attr' => [
                    'data-help-key' => 'environment.environment',
                ],
            ])
            ->add('debug', CheckboxType::class, [
                'label' => 'installer.environment.form.debug',
                'required' => false,
                'attr' => [
                    'data-help-key' => 'environment.debug',
                ],
            ])
            ->add('secret', TextType::class, [
                'label' => 'installer.environment.form.secret',
                'required' => false,
                'attr' => [
                    'autocomplete' => 'off',
                    'data-help-key' => 'environment.secret',
                ],
            ])
            ->add('instance_name', TextType::class, [
                'label' => 'installer.environment.form.instance_name',
                'constraints' => [
                    new Assert\NotBlank(),
                ],
                'attr' => [
                    'data-help-key' => 'environment.instance_name',
                ],
            ])
            ->add('tagline', TextType::class, [
                'label' => 'installer.environment.form.tagline',
                'required' => false,
                'attr' => [
                    'data-help-key' => 'environment.tagline',
                ],
            ])
            ->add('support_email', EmailType::class, [
                'label' => 'installer.environment.form.support_email',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Email(),
                ],
                'attr' => [
                    'data-help-key' => 'environment.support_email',
                ],
            ])
            ->add('base_url', UrlType::class, [
                'label' => 'installer.environment.form.base_url',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Url(requireTld: false),
                ],
                'default_protocol' => null,
                'attr' => [
                    'data-help-key' => 'environment.base_url',
                ],
            ])
            ->add('locale', ChoiceType::class, [
                'label' => 'installer.environment.form.locale',
                'constraints' => [
                    new Assert\NotBlank(),
                ],
                'choices' => $this->normalizeChoices($options['locale_choices']),
                'placeholder' => 'installer.environment.form.locale_placeholder',
                'choice_translation_domain' => false,
                'attr' => [
                    'data-help-key' => 'environment.locale',
                ],
            ])
            ->add('timezone', ChoiceType::class, [
                'label' => 'installer.environment.form.timezone',
                'constraints' => [
                    new Assert\NotBlank(),
                ],
                'choices' => $this->normalizeChoices($options['timezone_choices']),
                'placeholder' => 'installer.environment.form.timezone_placeholder',
                'choice_translation_domain' => false,
                'attr' => [
                    'data-help-key' => 'environment.timezone',
                ],
            ])
            ->add('user_registration', CheckboxType::class, [
                'label' => 'installer.environment.form.user_registration',
                'required' => false,
                'attr' => [
                    'data-help-key' => 'environment.user_registration',
                ],
            ])
            ->add('maintenance_mode', CheckboxType::class, [
                'label' => 'installer.environment.form.maintenance_mode',
                'required' => false,
                'attr' => [
                    'data-help-key' => 'environment.maintenance_mode',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'csrf_protection' => true,
            'csrf_token_id' => 'setup_environment',
            'locale_choices' => [],
            'timezone_choices' => [],
        ]);
        $resolver->setAllowedTypes('locale_choices', ['array']);
        $resolver->setAllowedTypes('timezone_choices', ['array']);
    }

    /**
     * @param array<string,string>|array<int,string> $choices
     *
     * @return array<string,string>
     */
    private function normalizeChoices(array $choices): array
    {
        if ($choices === []) {
            return [];
        }

        $normalized = [];
        foreach ($choices as $key => $value) {
            if (is_int($key)) {
                $normalized[$value] = $value;
            } else {
                $normalized[$key] = $value;
            }
        }

        ksort($normalized);

        return $normalized;
    }
}
