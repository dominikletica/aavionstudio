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
                'label' => 'Administrator email',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Email(),
                ],
            ])
            ->add('display_name', TextType::class, [
                'label' => 'Display name',
                'constraints' => [
                    new Assert\NotBlank(),
                ],
            ])
            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'The password fields must match.',
                'required' => true,
                'first_options' => [
                    'label' => 'Password',
                    'attr' => [
                        'autocomplete' => 'new-password',
                    ],
                    'label_attr' => $passwordLabelAttr,
                ],
                'second_options' => [
                    'label' => 'Confirm password',
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
                'label' => 'Preferred locale',
                'constraints' => [
                    new Assert\NotBlank(),
                ],
                'choices' => $localeChoices,
                'placeholder' => 'Select locale',
                'choice_translation_domain' => false,
            ])
            ->add('timezone', ChoiceType::class, [
                'label' => 'Preferred timezone',
                'constraints' => [
                    new Assert\NotBlank(),
                ],
                'choices' => $timezoneChoices,
                'placeholder' => 'Select timezone',
                'choice_translation_domain' => false,
            ])
            ->add('require_mfa', CheckboxType::class, [
                'label' => 'Require multi-factor authentication on first login',
                'required' => false,
            ])
            ->add('recovery_email', EmailType::class, [
                'label' => 'Recovery email (optional)',
                'required' => false,
            ])
            ->add('recovery_phone', TextType::class, [
                'label' => 'Recovery phone (optional)',
                'required' => false,
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

        $title = trim((string) ($tooltip['title'] ?? 'Tip'));
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
            new Assert\Length(min: (int) $policy['min_length']),
        ];

        if (!empty($policy['require_numbers'])) {
            $constraints[] = new Assert\Regex(
                pattern: '/\d/',
                message: 'Password must contain at least one number.'
            );
        }

        if (!empty($policy['require_mixed_case'])) {
            $constraints[] = new Assert\Regex(
                pattern: '/(?=.*[a-z])(?=.*[A-Z])/',
                message: 'Password must contain both upper and lower case characters.'
            );
        }

        if (!empty($policy['require_special_characters'])) {
            $constraints[] = new Assert\Regex(
                pattern: '/[^a-zA-Z0-9]/',
                message: 'Password must contain at least one special character.'
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
