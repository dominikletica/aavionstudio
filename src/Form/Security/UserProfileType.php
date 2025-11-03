<?php

declare(strict_types=1);

namespace App\Form\Security;

use Symfony\Component\Form\AbstractType;
use App\Security\User\UserProfileFieldRegistry;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class UserProfileType extends AbstractType
{
    public function __construct(
        private readonly UserProfileFieldRegistry $fieldRegistry,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('display_name', TextType::class, [
                'label' => 'Display name',
                'constraints' => [
                    new NotBlank(),
                    new Length(min: 2, max: 190),
                ],
            ])
            ->add('locale', TextType::class, [
                'label' => 'Locale',
                'empty_data' => 'en',
                'constraints' => [
                    new NotBlank(),
                    new Length(max: 12),
                ],
            ])
            ->add('timezone', TextType::class, [
                'label' => 'Timezone',
                'empty_data' => 'UTC',
                'constraints' => [
                    new NotBlank(),
                    new Length(max: 64),
                ],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Account status',
                'choices' => [
                    'Active' => 'active',
                    'Pending' => 'pending',
                    'Disabled' => 'disabled',
                    'Archived' => 'archived',
                ],
                'constraints' => [
                    new NotBlank(),
                    new Choice(choices: ['active', 'pending', 'disabled', 'archived']),
                ],
            ])
            ->add('roles', ChoiceType::class, [
                'label' => 'Roles',
                'choices' => $options['role_choices'],
                'multiple' => true,
                'expanded' => true,
            ]);

        $this->addProfileFields($builder);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('role_choices');
        $resolver->setAllowedTypes('role_choices', ['array']);
        $resolver->setDefaults([
            'csrf_token_id' => 'admin_user_profile',
        ]);
    }

    private function addProfileFields(FormBuilderInterface $builder): void
    {
        foreach ($this->fieldRegistry->getFields() as $name => $definition) {
            $type = $definition['type'] ?? 'string';
            $label = $definition['label'] ?? $this->humanize($name);
            $required = (bool) ($definition['required'] ?? false);
            $maxLength = \is_int($definition['max_length'] ?? null) ? (int) $definition['max_length'] : null;

            $fieldOptions = [
                'label' => $label,
                'required' => $required,
            ];

            $constraints = [];
            if ($required) {
                $constraints[] = new NotBlank();
            }

            if ($maxLength !== null) {
                $constraints[] = new Length(max: $maxLength);
            }

            switch ($type) {
                case 'textarea':
                    $formType = TextareaType::class;
                    break;

                case 'url':
                    $formType = UrlType::class;
                    break;

                case 'tel':
                case 'phone':
                    $formType = TelType::class;
                    break;

                case 'boolean':
                    $formType = CheckboxType::class;
                    $fieldOptions['required'] = false;
                    break;

                default:
                    $formType = TextType::class;
                    break;
            }

            if ($constraints !== []) {
                $fieldOptions['constraints'] = $constraints;
            }

            $builder->add($name, $formType, $fieldOptions);
        }
    }

    private function humanize(string $name): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $name));
    }
}
