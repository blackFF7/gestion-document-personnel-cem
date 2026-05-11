<?php

namespace App\Service;

use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetIO;
use PhpOffice\PhpWord\IOFactory as WordIO;
use Spatie\PdfToImage\Pdf;

use League\Flysystem\FilesystemOperator;

class DocumentPreviewer
{
    private string $previewDir;
    private FilesystemOperator $documentsStorage;

    public function __construct(
        string $previewDir,
        FilesystemOperator $documentsStorage // ⚡ injection
    ) {
        $this->previewDir = rtrim($previewDir, '/');
        $this->documentsStorage = $documentsStorage;
    }

    public function generatePreviewFromExisting(string $filePath): ?string
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $filename = pathinfo($filePath, PATHINFO_FILENAME);
        $previewFile = $this->previewDir.'/'.$filename.'.png';

        if (file_exists($previewFile)) {
            return '/uploads/previews/'.$filename.'.png';
        }

        /* IMAGE */
        if (in_array($ext,['jpg','jpeg','png','gif'])) {
            return null;
        }

        /* PDF */
        if ($ext === 'pdf') {

            // ⚡ vérifier si existe dans MinIO
            if (!$this->documentsStorage->fileExists($filePath)) {
                throw new \Exception("Fichier introuvable dans MinIO: ".$filePath);
            }

            // ⚡ créer fichier temporaire
            $tempFile = sys_get_temp_dir().'/'.uniqid().'.pdf';

            // ⚡ récupérer contenu depuis MinIO
            $stream = $this->documentsStorage->readStream($filePath);

            if (!$stream) {
                throw new \Exception("Impossible de lire le fichier depuis MinIO");
            }

            $local = fopen($tempFile, 'w+');
            stream_copy_to_stream($stream, $local);

            fclose($stream);
            fclose($local);

            // ⚡ générer preview
            $pdf = new \Spatie\PdfToImage\Pdf($tempFile);

            $pdf->selectPage(1)
                ->save($previewFile);

            // 🧹 nettoyage
            unlink($tempFile);

            return '/uploads/previews/'.$filename.'.png';
        }

        return null;
    }

    public function generatePreview(string $filePath): ?string
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $filename = pathinfo($filePath, PATHINFO_FILENAME);
        $filename = preg_replace('/[^A-Za-z0-9_\-]/', '_', $filename);
        $previewFile = $this->previewDir.'/'.$filename.'.png';

        if (file_exists($previewFile)) {
            return '/uploads/previews/'.$filename.'.png';
        }

        if (in_array($ext, ['jpg','jpeg','png','gif'])) {
            // Si image déjà, on renvoie directement
            return '/uploads/documents/'.basename($filePath);
        }

        if (in_array($ext, ['pdf'])) {
            // PDF → PNG via Spatie PDF to Image
            $pdf = new Pdf($filePath);
            $pdf->setOutputFormat('png')
                ->saveImage($previewFile);
            return '/uploads/previews/'.$filename.'.png';
        }

        if (in_array($ext, ['doc','docx'])) {
            // Word → HTML → capture screenshot (optionnel : simplifié ici)
            $phpWord = WordIO::load($filePath);
            $html = WordIO::createWriter($phpWord, 'HTML');
            $html->save($this->previewDir.'/'.$filename.'.html');
            return '/uploads/previews/'.$filename.'.html';
        }

        if (in_array($ext, ['xls','xlsx'])) {
            // Excel → image simplifié : sauvegarde en HTML
            $spreadsheet = SpreadsheetIO::load($filePath);
            $writer = SpreadsheetIO::createWriter($spreadsheet, 'Html');
            $writer->save($this->previewDir.'/'.$filename.'.html');
            return '/uploads/previews/'.$filename.'.html';
        }

        return null;
    }
}
