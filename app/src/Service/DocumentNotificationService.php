<?php

namespace App\Service;

use App\Entity\Document;
use App\Entity\Personnel;
use App\Enum\StatusDoc;

/**
 * Service de notification pour les actions sur les documents.
 *
 * Dans une vraie implémentation, utiliser Symfony Notifier + Mercure pour les
 * notifications temps réel. Ici, on enregistre en base ou on envoie par mail.
 *
 * Structure de notification (à adapter selon votre système) :
 *   - Nouveau document SOUMIS  → notifier le CHEF de l'unité
 *   - Document APPROUVÉ        → notifier le SAP
 *   - Document REJETÉ          → notifier le créateur (USER)
 *   - Document ARCHIVÉ         → notifier le créateur + CHEF
 *   - Document en BROUILLON    → notifier le créateur lui-même
 */
class DocumentNotificationService
{
    public function notifyOnCreate(Document $document, Personnel $creator): void
    {
        $status = $document->getStatucDoc();

        if ($status === StatusDoc::SOUMIS) {
            $this->notifyChefs($document, sprintf(
                'Nouveau document soumis par %s : "%s" [%s]',
                $creator->getFullName(),
                $document->getReference(),
                $document->getTypeDocumentID()?->getNomTypeDoc()
            ));
        } elseif ($status === StatusDoc::BROUILLON) {
            $this->notifyPersonnel($creator, sprintf(
                'Votre document "%s" a été mis en brouillon.',
                $document->getReference()
            ));
        }
    }

    public function notifyOnUpdate(Document $document, Personnel $updater): void
    {
        $this->notifyPersonnel(
            $document->getPersonnelID(),
            sprintf('Le document "%s" a été modifié par %s.', $document->getReference(), $updater->getFullName())
        );
    }

    public function notifyOnStatusChange(Document $document, StatusDoc $oldStatus, StatusDoc $newStatus, Personnel $actor): void
    {
        $owner  = $document->getPersonnelID();
        $ref    = $document->getReference();

        match ($newStatus) {
            StatusDoc::SOUMIS => $this->notifyChefs($document, sprintf(
                'Document soumis pour approbation : "%s" par %s.',
                $ref, $owner?->getFullName()
            )),

            StatusDoc::APPROUVE => $this->notifySAP(sprintf(
                'Document approuvé par %s : "%s". En attente d\'archivage.',
                $actor->getFullName(), $ref
            )),

            StatusDoc::REJETE => $this->notifyPersonnel($owner, sprintf(
                'Votre document "%s" a été rejeté par %s.',
                $ref, $actor->getFullName()
            )),

            StatusDoc::ARCHIVE => $this->notifyAll($document, sprintf(
                'Document archivé : "%s" par %s.',
                $ref, $actor->getFullName()
            )),

            StatusDoc::BROUILLON => $this->notifyPersonnel($owner, sprintf(
                'Votre document "%s" a été remis en brouillon.',
                $ref
            )),

            default => null,
        };
    }

    // ── Méthodes privées de distribution ──────────────────────────────────────

    private function notifyPersonnel(?Personnel $personnel, string $message): void
    {
        if (!$personnel) {
            return;
        }
        // TODO : insérer dans la table notification ou envoyer par Mercure/Mailer
        // Exemple : $this->notificationRepo->create($personnel, $message);
        // Pour l'instant, on log
        error_log(sprintf('[NOTIF → %s] %s', $personnel->getFullName(), $message));
    }

    private function notifyChefs(Document $document, string $message): void
    {
        $owner = $document->getPersonnelID();
        if (!$owner) {
            return;
        }

        // Les chefs de la même agence/service
        // TODO : implémenter la récupération des chefs selon l'unité du personnel
        error_log(sprintf('[NOTIF → CHEFS de %s] %s', $owner->getFullName(), $message));
    }

    private function notifySAP(string $message): void
    {
        // TODO : notifier tous les utilisateurs avec ROLE_SAP
        error_log(sprintf('[NOTIF → SAP] %s', $message));
    }

    private function notifyAll(Document $document, string $message): void
    {
        $this->notifyPersonnel($document->getPersonnelID(), $message);
        $this->notifyChefs($document, $message);
        $this->notifySAP($message);
    }
}
