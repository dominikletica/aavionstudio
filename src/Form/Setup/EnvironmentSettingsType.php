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
                'label' => 'Environment',
                'choices' => [
                    'Development' => 'dev',
                    'Testing' => 'test',
                    'Production' => 'prod',
                ],
                'placeholder' => false,
            ])
            ->add('debug', CheckboxType::class, [
                'label' => 'Enable debug mode',
                'required' => false,
            ])
            ->add('secret', TextType::class, [
                'label' => 'Application secret (optional)',
                'required' => false,
                'attr' => [
                    'autocomplete' => 'off',
                ],
            ])
            ->add('instance_name', TextType::class, [
                'label' => 'Instance name',
                'constraints' => [
                    new Assert\NotBlank(),
                ],
            ])
            ->add('tagline', TextType::class, [
                'label' => 'Tagline',
                'required' => false,
            ])
            ->add('support_email', EmailType::class, [
                'label' => 'Support email',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Email(),
                ],
            ])
            ->add('base_url', UrlType::class, [
                'label' => 'Base URL',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Url(requireTld: false),
                ],
            ])
            ->add('locale', TextType::class, [
                'label' => 'Default locale',
                'constraints' => [
                    new Assert\NotBlank(),
                ],
            ])
            ->add('timezone', TextType::class, [
                'label' => 'Default timezone',
                'constraints' => [
                    new Assert\NotBlank(),
                ],
            ])
            ->add('user_registration', CheckboxType::class, [
                'label' => 'Allow user self-registration',
                'required' => false,
            ])
            ->add('maintenance_mode', CheckboxType::class, [
                'label' => 'Enable maintenance mode',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'csrf_protection' => true,
            'csrf_token_id' => 'setup_environment',
        ]);
    }
}
