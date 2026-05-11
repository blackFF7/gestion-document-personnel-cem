<?php

namespace App\Controller\Admin;

use App\Entity\Dossier;
use App\Enum\NiveauConfidentiel;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class DossierCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Dossier::class;
    }


    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new("id")
                ->hideOnForm()
                ->setLabel("Numéro du dossier"),
            TextField::new("nomDos")
                ->setLabel("Nom du dossier"),
            TextField::new("Nomenclature")
                ->setLabel("Nomenclature"),
            ChoiceField::new("niveauConf")
                ->setChoices(NiveauConfidentiel::cases())
                ->setLabel("Niveau de confidentiel"),
        ];
    }
}
