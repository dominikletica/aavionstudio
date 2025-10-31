<?php

declare(strict_types=1);

namespace App\Form\Security;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ProjectMembershipEntryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('project_id', HiddenType::class)
            ->add('role', ChoiceType::class, [
                'label' => 'Project role',
                'choices' => $options['role_choices'],
                'required' => false,
                'placeholder' => 'Inherit global role',
            ])
            ->add('capabilities', TextType::class, [
                'label' => 'Extra capabilities',
                'required' => false,
                'help' => 'Comma or space separated capability keys applied to this project only.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('role_choices');
        $resolver->setAllowedTypes('role_choices', ['array']);
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
