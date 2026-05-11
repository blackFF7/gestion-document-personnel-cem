<?php

namespace App\Form;

use App\Entity\Document;
use App\Entity\Personnel;
use App\Entity\TypeDocument;
use App\Enum\StatusDoc;
use App\Repository\PersonnelRepository;
use App\Repository\TypeDocumentRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Vich\UploaderBundle\Form\Type\VichFileType;

class EspaceType extends AbstractType
{
    public function __construct(
        private PersonnelRepository $personnelRepository,
        private TypeDocumentRepository $typeDocumentRepository,
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Personnel $currentUser */
        $currentUser   = $options['current_user'];
        $isAdminOrSap  = $options['is_admin_or_sap'];

        // ── Champ personnel ────────────────────────────────────────────────────
        // SAP et ADMIN peuvent choisir n'importe quel personnel
        // Les autres roles : champ inactif, pré-sélectionné sur soi-même
        $builder->add('personnelID', EntityType::class, [
            'class'        => Personnel::class,
            'choice_label' => fn(Personnel $p) => $p->getIMFormatted() . ' - ' . $p->getFullName(),
            'label'        => 'Personnel',
            'disabled'     => !$isAdminOrSap,
            'data'         => $currentUser,
            'query_builder' => fn(PersonnelRepository $pr) => $isAdminOrSap
                ? $pr->createQueryBuilder('p')->orderBy('p.nomAg', 'ASC')
                : $pr->createQueryBuilder('p')->where('p.id = :id')->setParameter('id', $currentUser->getId()),
            'attr'          => [
                'class' => $isAdminOrSap ? 'form-select' : 'form-select bg-gray-100',
            ],
        ]);

        // ── Type de document avec Live Component pour ajout rapide ─────────────
        $builder->add('typeDocumentID', EntityType::class, [
            'class'        => TypeDocument::class,
            'choice_label' => fn(TypeDocument $t) => $t->getNomTypeDoc() . ' (' . $t->getDossierID()->getNomDos() . ')',
            'label'        => 'Type de document',
            'placeholder'  => '-- Sélectionner un type --',
            'attr'         => ['class' => 'form-select'],
        ]);

        $builder
            ->add('titre', TextType::class, [
                'label'    => 'Titre',
                'required' => false,
                'attr'     => ['class' => 'form-control', 'placeholder' => 'Titre du document (optionnel)'],
            ])
            ->add('titulaire', TextType::class, [
                'label'    => 'Titulaire',
                'required' => false,
                'attr'     => ['class' => 'form-control', 'placeholder' => 'Nom du titulaire (optionnel)'],
            ])
            ->add('dateArriveDoc', DateType::class, [
                'label'    => "Date d'arrivée",
                'required' => false,
                'widget'   => 'single_text',
                'attr'     => ['class' => 'form-control'],
            ])
            ->add('description', TextareaType::class, [
                'label'    => 'Description',
                'required' => false,
                'attr'     => ['class' => 'form-control', 'rows' => 3],
            ])
            ->add('fichierUpload', VichFileType::class, [
                'label'         => 'Fichier (PDF, DOCX, XLSX, Image)',
                'required'      => false,
                'allow_delete'  => false,
                'download_uri'  => false,
                'attr'          => ['class' => 'form-control', 'accept' => '.pdf,.docx,.xlsx,image/*'],
            ]);

        // ── Statut : selon le rôle ─────────────────────────────────────────────
        $statusChoices = $this->getStatusChoices($isAdminOrSap, $currentUser);
        if (!empty($statusChoices)) {
            $builder->add('statucDoc', ChoiceType::class, [
                'label'   => 'Statut',
                'choices' => $statusChoices,
                'attr'    => ['class' => 'form-select'],
            ]);
        }
    }

    private function getStatusChoices(bool $isAdminOrSap, Personnel $user): array
    {
        $roles = $user->getRoles();

        if ($isAdminOrSap || in_array('ROLE_ADMIN', $roles)) {
            // SAP/ADMIN : Brouillon ou Archivé directement
            return [
                'Brouillon' => StatusDoc::BROUILLON,
                'Archivé'   => StatusDoc::ARCHIVE,
            ];
        }

        if (in_array('ROLE_CHEF', $roles)) {
            return [
                'Brouillon' => StatusDoc::BROUILLON,
                'Approuvé'  => StatusDoc::APPROUVE,
            ];
        }

        // USER ou CHEF simple
        return [
            'Brouillon' => StatusDoc::BROUILLON,
            'Soumis'    => StatusDoc::SOUMIS,
        ];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'      => Document::class,
            'current_user'    => null,
            'is_admin_or_sap' => false,
        ]);
    }
}