<?php

namespace App\Form;

use App\Entity\Personnel;
use App\Enum\Sexe;
use App\Enum\SituationFamilial;
use App\Enum\StatusCompte;
use App\Form\ConjointType as FormConjointType;
use App\Form\EnfantType as FormEnfantType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Vich\UploaderBundle\Form\Type\VichImageType;
use App\Form\EventSubscriber\PersonnelUsernameSubscriber;

class PersonnelType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isNew  = $options['is_new'];
        $isSelf = $options['is_self'];
        $isSap  = $options['is_sap'];

        // ── Champs SAP/Admin uniquement ────────────────────────────────────
        if ($isSap) {
            $builder
                ->add('IM', IntegerType::class, [
                    'label'    => 'Matricule (IM)',
                    'required' => true,
                ])
                ->add('nomAg', TextType::class, [
                    'label'    => 'Nom',
                    'required' => true,
                ])
                ->add('prenomAg', TextType::class, [
                    'label'    => 'Prénom',
                    'required' => true,
                ])
                ->add('dateNaissAg', DateType::class, [
                    'label'    => 'Date de naissance',
                    'widget'   => 'single_text',
                    'required' => true,
                ])
                ->add('dateEntre', DateType::class, [
                    'label'    => 'Date d\'entrée',
                    'widget'   => 'single_text',
                    'required' => true,
                ])
                ->add('sexe', EnumType::class, [
                    'class'        => Sexe::class,
                    'choice_label' => fn(Sexe $c) => $c->value,
                    'label'        => 'Sexe',
                    'required'     => true,
                ])

                // ── Autocomplete : Fonction ──────────────────────────────
                ->add('fonctionID', FonctionAutocompleteField::class, [
                    'label'    => 'Fonction',
                    'required' => true,
                ])

                // ── Autocomplete : Agence (BackFront = true) ─────────────
                // Ce champ est mapped=false : la relation AgencePersonnel
                // est gérée manuellement dans le contrôleur (POST_SUBMIT).
                ->add('agenceField', AgenceAutocompleteField::class, [
                    'label'    => 'Agence',
                    'required' => false,
                    'mapped'   => false,
                    // Pré-sélection à l'édition : injectée via PRE_SET_DATA
                ])

                // ── Autocomplete : Service (BackFront = false) ───────────
                // Idem, mapped=false, géré dans le contrôleur.
                ->add('serviceField', ServiceAutocompleteField::class, [
                    'label'    => 'Service',
                    'required' => false,
                    'mapped'   => false,
                ])

                ->add('autoUsername', CheckboxType::class, [
                    'label'    => 'Générer le nom d\'utilisateur automatiquement',
                    'mapped'   => false,
                    'required' => false,
                    'data'     => $isNew,
                ])
                ->add('username', TextType::class, [
                    'label'    => 'Nom d\'utilisateur',
                    'required' => false,
                ])
                // BackFront : true = Agence, false = Service
                ->add('BackFront', ChoiceType::class, [
                    'label'    => 'Affectation principale',
                    'choices'  => ['Agence' => true, 'Service' => false],
                    'expanded' => false,
                    'required' => true,
                ])
            ;

            // Rôle : uniquement à la création
            if ($isNew) {
                $builder->add('roles', ChoiceType::class, [
                    'label'    => 'Rôle',
                    'choices'  => [
                        'Utilisateur (USER)' => 'ROLE_USER',
                        'Chef'               => 'ROLE_CHEF',
                        'RH'                 => 'ROLE_RH',
                        'SAP'                => 'ROLE_SAP',
                        'Administrateur'     => 'ROLE_ADMIN',
                    ],
                    'multiple' => true,
                    'expanded' => true,
                    'mapped'   => false,
                    'required' => false,
                ]);
            }

            $builder->add('statusCompte', EnumType::class, [
                'class'        => StatusCompte::class,
                'label'        => 'Statut du compte',
                'choice_label' => fn(StatusCompte $c) => $c->value,
                'disabled'     => $isNew,
                'required'     => true,
            ]);

            // Conjoint embedded
            $builder->add('conjoint', FormConjointType::class, [
                'label'    => false,
                'required' => false,
                'mapped'   => true,
            ]);

            // Enfants collection
            $builder->add('enfants', CollectionType::class, [
                'entry_type'    => FormEnfantType::class,
                'label'         => false,
                'allow_add'     => true,
                'allow_delete'  => true,
                'by_reference'  => false,
                'prototype'     => true,
                'required'      => false,
                'entry_options' => ['label' => false],
            ]);
        }

        // ── Photo de profil (tous les rôles) ───────────────────────────────
        $builder->add('photoProfilFile', VichImageType::class, [
            'label'        => 'Photo de profil',
            'required'     => false,
            'allow_delete' => !$isNew,
            'download_uri' => false,
            'image_uri'    => false,
        ]);

        // ── Champs éditables par tous les rôles ────────────────────────────
        $builder
            ->add('adresseAg', TextType::class, [
                'label'    => 'Adresse',
                'required' => false,
            ])
            ->add('mailAg', EmailType::class, [
                'label'    => 'Email',
                'required' => false,
            ])
            ->add('situationFamilial', EnumType::class, [
                'class'        => SituationFamilial::class,
                'choice_label' => fn(SituationFamilial $c) => $c->value,
                'label'        => 'Situation familiale',
                'required'     => true,
            ])
        ;

        // Téléphones (JSON caché, sérialisé en POST_SUBMIT)
        $builder->add('contactAg', HiddenType::class, [
            'label'    => false,
            'required' => false,
            'mapped'   => false,
            'attr'     => ['id' => 'contactAgHidden'],
        ]);

        // Mot de passe
        $builder->add('plainPassword', RepeatedType::class, [
            'type'           => PasswordType::class,
            'mapped'         => false,
            'required'       => $isNew,
            'first_options'  => ['label' => 'Mot de passe'],
            'second_options' => ['label' => 'Confirmer'],
            'constraints'    => $isNew
                ? [new Length(['min' => 6])]
                : [new Length(['min' => 0])],
        ]);

        $builder->addEventSubscriber(
            new PersonnelUsernameSubscriber()
        );

        // ── PRE_SET_DATA : pré-remplissage téléphones + agence/service ─────
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $personnel = $event->getData();
            if (!$personnel) {
                return;
            }
            $form = $event->getForm();

            // Téléphones
            if ($personnel->getContactAg()) {
                $form->get('contactAg')->setData(
                    json_encode(array_values($personnel->getContactAg()))
                );
            }

            // Pré-sélection agence / service à l'édition
            if ($form->has('agenceField')) {
                $agencePersonnels = $personnel->getAgencePersonnels();
                if (!$agencePersonnels->isEmpty()) {
                    $form->get('agenceField')->setData(
                        $agencePersonnels->first()->getAgenceID()
                    );
                }
            }
            if ($form->has('serviceField')) {
                $directionPersonnels = $personnel->getDirectionPersonnels();
                if (!$directionPersonnels->isEmpty()) {
                    $form->get('serviceField')->setData(
                        $directionPersonnels->first()->getServiceID()
                    );
                }
            }
        });

        // ── POST_SUBMIT : téléphones + rôles ──────────────────────────────
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use ($isSap, $isNew) {
            $form      = $event->getForm();
            $personnel = $event->getData();
            if (!$personnel) {
                return;
            }

            // Téléphones
            $raw = $form->get('contactAg')->getData();
            if ($raw !== null && $raw !== '') {
                $phones = json_decode($raw, true);
                if (is_array($phones)) {
                    $personnel->setContactAg(array_values(array_filter($phones)));
                }
            }

            // Rôles (SAP, création uniquement)
            if ($isSap && $isNew && $form->has('roles')) {
                $personnel->setRoles($form->get('roles')->getData() ?: []);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Personnel::class,
            'is_new'     => false,
            'is_self'    => false,
            'is_sap'     => false,
        ]);
        $resolver->setAllowedTypes('is_new', 'bool');
        $resolver->setAllowedTypes('is_self', 'bool');
        $resolver->setAllowedTypes('is_sap', 'bool');
    }
}
