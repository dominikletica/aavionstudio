<?php

declare(strict_types=1);

namespace App\Form\Setup;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class AdminAccountType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var array<string, mixed> $passwordPolicy */
        $passwordPolicy = $options['password_policy'];
        /** @var array<string, array<string, mixed>> $tooltips */
        $tooltips = $options['tooltips'];
        $localeChoices = $this->normalizeChoices($options['locale_choices']);
        $timezoneChoices = $this->normalizeChoices($options['timezone_choices']);
        $passwordLabelAttr = $this->buildTooltipLabelAttr($tooltips['admin.password'] ?? null, true);

        $builder
            ->add('email', EmailType::class, [
                'label' => 'installer.admin.form.email',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Email(),
                ],
                'attr' => [
                    'data-help-key' => 'admin.email',
                ],
            ])
            ->add('display_name', TextType::class, [
                'label' => 'installer.admin.form.display_name',
                'constraints' => [
                    new Assert\NotBlank(),
                ],
                'attr' => [
                    'data-help-key' => 'admin.display_name',
                ],
            ])
            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'installer.admin.form.password_mismatch',
                'required' => true,
                'attr' => [
                    'data-help-key' => 'admin.password',
                ],
                'first_options' => [
                    'label' => 'installer.admin.form.password',
                    'attr' => [
                        'autocomplete' => 'new-password',
                        'data-help-key' => 'admin.password',
                    ],
                    'label_attr' => $passwordLabelAttr,
                ],
                'second_options' => [
                    'label' => 'installer.admin.form.password_confirmation',
                    'attr' => [
                        'autocomplete' => 'new-password',
                    ],
                    'label_attr' => $passwordLabelAttr,
                ],
                'constraints' => [
                    new Assert\NotBlank(),
                    ...$this->buildPasswordConstraints($passwordPolicy),
                ],
            ])
            ->add('locale', ChoiceType::class, [
                'label' => 'installer.admin.form.locale',
                'constraints' => [
                    new Assert\NotBlank(),
                ],
                'choices' => $localeChoices,
                'placeholder' => 'installer.admin.form.locale_placeholder',
                'choice_translation_domain' => false,
                'attr' => [
                    'data-help-key' => 'admin.locale',
                ],
            ])
            ->add('timezone', ChoiceType::class, [
                'label' => 'installer.admin.form.timezone',
                'constraints' => [
                    new Assert\NotBlank(),
                ],
                'choices' => $timezoneChoices,
                'placeholder' => 'installer.admin.form.timezone_placeholder',
                'choice_translation_domain' => false,
                'attr' => [
                    'data-help-key' => 'admin.timezone',
                ],
            ])
            ->add('require_mfa', CheckboxType::class, [
                'label' => 'installer.admin.form.require_mfa',
                'required' => false,
                'attr' => [
                    'data-help-key' => 'admin.require_mfa',
                ],
            ])
            ->add('recovery_email', EmailType::class, [
                'label' => 'installer.admin.form.recovery_email',
                'required' => false,
                'attr' => [
                    'data-help-key' => 'admin.recovery_email',
                ],
            ])
            ->add('recovery_phone', TextType::class, [
                'label' => 'installer.admin.form.recovery_phone',
                'required' => false,
                'attr' => [
                    'data-help-key' => 'admin.recovery_phone',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'csrf_protection' => true,
            'csrf_token_id' => 'setup_admin',
            'password_policy' => [],
            'locale_choices' => [],
            'timezone_choices' => [],
            'tooltips' => [],
        ]);
        $resolver->setAllowedTypes('password_policy', ['array']);
        $resolver->setAllowedTypes('locale_choices', ['array']);
        $resolver->setAllowedTypes('timezone_choices', ['array']);
        $resolver->setAllowedTypes('tooltips', ['array']);
    }

    /**
     * @param array<string, mixed>|null $tooltip
     *
     * @return array<string, string>
     */
    private function buildTooltipLabelAttr(?array $tooltip, bool $required = false): array
    {
        $attributes = [];

        if ($required) {
            $attributes['class'] = 'required';
        }

        if (!\is_array($tooltip)) {
            return $attributes;
        }

        $rawBody = (string) ($tooltip['body'] ?? '');
        $body = trim(preg_replace('/\s+/', ' ', strip_tags($rawBody)) ?? '');
        if ($body === '') {
            return $attributes;
        }

        $existingClass = $attributes['class'] ?? '';
        $attributes['class'] = trim($existingClass.' tooltip');
        $attributes['data-tooltip'] = $body;

        $title = trim((string) ($tooltip['title'] ?? ''));
        $attributes['aria-label'] = $title === '' ? $body : sprintf('%s: %s', $title, $body);

        return $attributes;
    }

    /**
     * @param array<string, mixed> $policy
     *
     * @return list<Assert\Constraint>
     */
    private function buildPasswordConstraints(array $policy): array
    {
        $defaults = [
            'min_length' => 12,
            'require_numbers' => true,
            'require_mixed_case' => true,
            'require_special_characters' => false,
        ];

        $policy = array_merge($defaults, $policy);

        $constraints = [
            new Assert\NotBlank(),
            new Assert\Length(
                min: (int) $policy['min_length'],
                minMessage: 'installer.admin.form.password_require_length'
            ),
        ];

        if (!empty($policy['require_numbers'])) {
            $constraints[] = new Assert\Regex(
                pattern: '/\d/',
                message: 'installer.admin.form.password_require_number'
            );
        }

        if (!empty($policy['require_mixed_case'])) {
            $constraints[] = new Assert\Regex(
                pattern: '/(?=.*[a-z])(?=.*[A-Z])/',
                message: 'installer.admin.form.password_require_mixed_case'
            );
        }

        if (!empty($policy['require_special_characters'])) {
            $constraints[] = new Assert\Regex(
                pattern: '/[^a-zA-Z0-9]/',
                message: 'installer.admin.form.password_require_special'
            );
        }

        return $constraints;
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
