<?php

namespace App\Form;

use App\Entity\Enfant;
use App\Enum\Sexe;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;

class EnfantType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('nom')
            ->add('prenom')
            ->add('dateNaiss', DateType::class, [
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('sexe', EnumType::class, [
            'class' => Sexe::class,
            'choice_label' => fn(Sexe $choice) => $choice->value,
            'placeholder' => 'Choisir le sexe',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Enfant::class,
        ]);
    }
}
