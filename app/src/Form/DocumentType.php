<?php
namespace App\Form;

use App\Entity\Document;
use App\Enum\StatusDoc;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class DocumentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $roles = $options['user_roles'];
        $currentStatus = $options['current_status'];

        // Choix de statut selon le rôle
        $statusChoices = $this->getStatusChoices($roles, $currentStatus);

        $builder
            ->add('reference', TextType::class, [
                'label'    => 'Référence',
                'required' => true,
                'attr'     => [
                    'data-document-reference-target' => 'reference',
                    'readonly'                       => true,
                    'placeholder'                    => 'Générée automatiquement',
                ],
            ])
            ->add('statucDoc', EnumType::class, [
                'label'    => 'Statut',
                'class'    => StatusDoc::class,
                'choices'  => $statusChoices,
                'required' => true,
                'attr'     => ['class' => 'form-select'],
            ])
            ->add('titre', TextType::class, [
                'label'    => 'Titre du document',
                'required' => true,
                'attr'     => [
                    'placeholder'                    => 'Titre descriptif',
                    'data-document-reference-target' => 'titre',
                ],
            ])
            ->add('titulaire', TextType::class, [
                'label'    => 'Titulaire',
                'required' => false,
                'attr'     => [
                    'placeholder'                    => 'Nom du responsable',
                    'data-document-reference-target' => 'titulaire',
                ],
            ])
            ->add('dateArriveDoc', DateType::class, [
                'label'    => "Date d'arrivée",
                'widget'   => 'single_text',
                'required' => false,
                'attr'     => [
                    'data-document-reference-target' => 'date',
                ],
            ])
            ->add('description', TextareaType::class, [
                'label'    => 'Description',
                'required' => false,
                'attr'     => ['rows' => 3],
            ])
            ->add('personnelID', PersonnelAutocompleteField::class, [
                'label'       => 'Propriétaire',
                'required'    => true,
                'placeholder' => 'Choisir un propriétaire',
                'attr'        => [
                    'data-document-reference-target' => 'personnel',
                ],
            ])
            ->add('typeDocumentID', TypeDocumentAutocompleteField::class, [
                'label'       => 'Type de document',
                'required'    => true,
                'placeholder' => 'Choisir un type de document',
                'attr'        => [
                    'data-document-reference-target' => 'typeDocument',
                ],
            ])
            ->add('fichierUpload', FileType::class, [
                'label'       => 'Fichier à uploader',
                'required'    => false,
                'mapped'      => true,
                'constraints' => [
                    new File([
                        'maxSize'          => '50M',
                        'mimeTypes'        => [
                            'application/pdf',
                            'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'image/jpeg',
                            'image/png',
                        ],
                        'mimeTypesMessage' => 'Merci de choisir un fichier valide (PDF, DOCX, XLSX, JPG, PNG)',
                    ]),
                ],
            ]);
    }

    private function getStatusChoices(array $roles, ?StatusDoc $currentStatus): array
    {
        if (in_array('ROLE_SAP', $roles) || in_array('ROLE_ADMIN', $roles)) {
            // SAP : brouillon ou archiver (si approuvé)
            if ($currentStatus === StatusDoc::APPROUVE) {
                return [StatusDoc::APPROUVE, StatusDoc::ARCHIVE];
            }
            return [StatusDoc::BROUILLON, StatusDoc::ARCHIVE];
        }

        if (in_array('ROLE_CHEF', $roles)) {
            // CHEF : peut approuver ou rejeter si soumis
            if ($currentStatus === StatusDoc::SOUMIS) {
                return [StatusDoc::SOUMIS, StatusDoc::APPROUVE, StatusDoc::REJETE];
            }
            return [StatusDoc::BROUILLON];
        }

        // USER / RH : brouillon ou soumis
        return [StatusDoc::BROUILLON, StatusDoc::SOUMIS];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'     => Document::class,
            'user_roles'     => [],
            'current_status' => null,
        ]);
    }
}