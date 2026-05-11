<?php

namespace App\Controller;

use App\Entity\Document;
use App\Enum\StatusDoc;
use App\Form\DocumentType;
use App\Repository\DocumentRepository;
use App\Service\DocumentPreviewer;
use App\Service\PreviewGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/document')]
class DocumentController extends AbstractController
{
    #[Route('/', name: 'document_index', methods: ['GET'])]
    public function index(DocumentRepository $repo, Request $request, PaginatorInterface $paginatorInterface): Response
    {
        $pagination = $paginatorInterface->paginate(
            $repo->documentByPersonnelStatus($this->getUser()),
            $request->query->getInt('page', 1),
            20
        );
        return $this->render('document/index.html.twig', [
            'pagination' => $pagination,
        ]);
    }
    

    #[Route('/preview-temp', name: 'document_preview_temp', methods: ['POST'])]
    public function previewTemp(Request $request, PreviewGenerator $previewer): JsonResponse
    {
        try {
            /** @var UploadedFile|null $file */
            $file = $request->files->get('file');

            if (!$file instanceof UploadedFile) {
                return new JsonResponse(['error' => 'Fichier non reçu'], 400);
            }

            // Vérification taille côté serveur (50MB)
            if ($file->getSize() > 50 * 1024 * 1024) {
                return new JsonResponse([
                    'error' => 'Fichier trop volumineux. Limite : 50 MB.'
                ], 422);
            }

            $preview = $previewer->generate($file);

            if (!$preview) {
                return new JsonResponse([
                    'error' => 'Format non supporté.'
                ], 422);
            }

            return new JsonResponse(['preview' => $preview]);

        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'error' => 'Erreur serveur : ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/new', name: 'document_new', methods: ['GET','POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        PreviewGenerator $previewer
    ): Response {
        $document = new Document();

        // IMPORTANT
        $document->setPersonnelID($this->getUser());
        $document->setStatucDoc(StatusDoc::BROUILLON);
        $form = $this->createForm(DocumentType::class, $document, [
            'user_roles'     => $this->getUser()->getRoles(),
            'current_status' => null,
        ]);
        $form->handleRequest($request);

        $preview = null;

        if ($form->isSubmitted() && $form->isValid()) {
            $document->setReference($this->generateReference($document));
            // creationDoc déjà init dans __construct, on ne le retouche pas
            $document->setMajDoc(new \DateTime());

            $em->persist($document);
            $em->flush();

            $this->addFlash('success', 'Document ajouté avec succès !');
            return $this->redirectToRoute('document_index');
        }

        return $this->render('document/new.html.twig', [
            'form'     => $form->createView(),
            'preview'  => $preview,
            'document' => $document,
        ]);
    }

    #[Route('/{id}/edit', name: 'document_edit', methods: ['GET','POST'])]
    public function edit(
        Document $document,
        Request $request,
        EntityManagerInterface $em,
        DocumentPreviewer $previewer
    ): Response {
        $form = $this->createForm(DocumentType::class, $document, [
            'user_roles'     => $this->getUser()->getRoles(),
            'current_status' => $document->getStatucDoc(),
        ]);
        $form->handleRequest($request);

        $preview = null;
        if ($document->getFichier()) {
            $preview = $previewer->generatePreviewFromExisting($document->getFichier());
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $document->setReference($this->generateReference($document));
            $document->setMajDoc(new \DateTime()); // màj à chaque modification

            $em->flush();
            $this->addFlash('success', 'Document modifié avec succès !');
            return $this->redirectToRoute('document_index');
        }

        return $this->render('document/edit.html.twig', [
            'form'     => $form->createView(),
            'document' => $document,
            'preview'  => $preview,
        ]);
    }

    #[Route('/{id}', name: 'app_document_show', methods: ['GET'])]
    public function show(Document $document, DocumentPreviewer $previewer): Response
    {
        $preview = null;
        if ($document->getFichier()) {
            $filePath = $document->getFichier(); // ⚡ chemin MinIO direct
            $preview = $previewer->generatePreviewFromExisting($filePath);
        }

        return $this->render('document/show.html.twig', [
            'document' => $document,
            'preview'  => $preview,
        ]);
    }

    #[Route('/preview-cleanup', name: 'document_preview_cleanup', methods: ['POST'])]
    public function previewCleanup(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $relativePath = $data['path'] ?? null;

        if (!$relativePath) {
            return new JsonResponse(['ok' => false]);
        }

        // Sécurité : uniquement les fichiers dans uploads/previews
        if (!str_starts_with($relativePath, '/uploads/previews/')) {
            return new JsonResponse(['ok' => false, 'reason' => 'forbidden']);
        }

        $absolutePath = $this->getParameter('kernel.project_dir')
            . '/public'
            . $relativePath;

        if (file_exists($absolutePath)) {
            unlink($absolutePath);
        }

        return new JsonResponse(['ok' => true]);
    }



    private function generateReference(Document $document): string
    {
        $parts = [];

        // IM du personnel (ex: 001)
        $parts[] = $document->getPersonnelID()->getIMFormatted();

        // Nomenclature du dossier (ex: PERS)
        $parts[] = strtoupper($document->getTypeDocumentID()->getDossierID()->getNomenclature());

        // Titulaire (optionnel)
        if ($document->getTitulaire()) {
            $parts[] = strtoupper(preg_replace('/\s+/', '-', trim($document->getTitulaire())));
        }

        // Type de document (ex: LIVRET-FAMILLE)
        $parts[] = strtoupper(preg_replace('/\s+/', '-', trim($document->getTypeDocumentID()->getNomTypeDoc())));

        // Date d'arrivée (optionnelle)
        if ($document->getDateArriveDoc()) {
            $parts[] = 'du';
            $parts[] = $document->getDateArriveDoc()->format('d-m-Y');
        }

        return implode('_', $parts);
    }

    #[Route('/{id}', name: 'document_delete', methods: ['POST'])]
    public function delete(Request $request, Document $document, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$document->getId(), $request->request->get('_token'))) {
            $em->remove($document);
            $em->flush();
            $this->addFlash('success', 'Document supprimé !');
        }

        return $this->redirectToRoute('document_index');
    }

    #[Route('/download/{id}', name: 'document_download', methods: ['GET'])]
    public function download(Document $document): BinaryFileResponse
    {
        $filePath = $document->getFichier();
        return $this->file($filePath, $document->getReference().'.'.$this->getExtension($filePath));
    }

    private function getExtension(string $filePath): string
    {
        return pathinfo($filePath, PATHINFO_EXTENSION);
    }
}
