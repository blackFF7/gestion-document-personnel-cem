<?php
// src/Controller/Api/ReferenceDataController.php
namespace App\Controller\Api;

use App\Repository\TypeDocumentRepository;
use App\Repository\PersonnelRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api')]
class ReferenceDataController extends AbstractController
{
    #[Route('/type-document/{id}', name: 'api_type_document', methods: ['GET'])]
    public function typeDocument(string $id, TypeDocumentRepository $repo): JsonResponse
    {
        $typeDoc = $repo->find($id);

        if (!$typeDoc) {
            return $this->json(['error' => 'TypeDocument non trouvé'], 404);
        }

        $dossier = $typeDoc->getDossierID();

        return $this->json([
            'nom'          => $typeDoc->getNomTypeDoc(),       // "Contrat a l'essai"
            'nomenclature' => $dossier->getNomenclature(),     // "CTA"
            'dossier'      => $dossier->getId(),               // 2
        ]);
    }

    #[Route('/personnel/{id}', name: 'api_personnel_im', methods: ['GET'])]
    public function personnel(string $id, PersonnelRepository $repo): JsonResponse
    {
        $personnel = $repo->find($id);

        if (!$personnel) {
            return $this->json(['error' => 'Personnel non trouvé'], 404);
        }

        return $this->json([
            'im' => $personnel->getIMFormatted(3), // "002"
        ]);
    }
}