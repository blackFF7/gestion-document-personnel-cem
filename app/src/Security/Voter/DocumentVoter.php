<?php
namespace App\Security\Voter;

use App\Entity\Document;
use App\Entity\Personnel;
use App\Enum\StatusDoc;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Uid\Uuid;

class DocumentVoter extends Voter
{
    public const VIEW   = 'VIEW';
    public const EDIT   = 'EDIT';
    public const DELETE = 'DELETE';

    private array $serviceCache = [];
    private array $agenceCache  = [];

    public function __construct(
        private EntityManagerInterface $em,
        private array $confidentialLevels,
        private array $statusLevels,
    ) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE])
            && $subject instanceof Document;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        /** @var Personnel $user */
        $user = $token->getUser();

        if (!$user instanceof Personnel) {
            return false;
        }

        /** @var Document $document */
        $document = $subject;
        $roles    = $user->getRoles();

        return match ($attribute) {
            self::VIEW   => $this->canView($document, $user, $roles),
            self::EDIT   => $this->canEdit($document, $user, $roles),
            self::DELETE => $this->canDelete($document, $user, $roles),
            default      => false,
        };
    }

    // ─── VIEW ─────────────────────────────────────────────────────────────────
    private function canView(Document $document, Personnel $user, array $roles): bool
    {
        $statut = $document->getStatucDoc();

        // ADMIN : tous les documents, toute confidentialité
        if (in_array('ROLE_ADMIN', $roles)) {
            return true;
        }

        // SAP : ses propres BROUILLON + tous les APPROUVE + tous les ARCHIVE
        //       toute confidentialité
        if (in_array('ROLE_SAP', $roles)) {
            if ($statut === StatusDoc::BROUILLON) {
                return $document->getPersonnelID()?->getId() === $user->getId();
            }
            return in_array($statut, [StatusDoc::APPROUVE, StatusDoc::ARCHIVE]);
        }

        // RH : ses propres BROUILLON + tous les ARCHIVE
        //      restriction confidentialité : Public + Confidentiel seulement
        if (in_array('ROLE_RH', $roles)) {
            if ($statut === StatusDoc::BROUILLON) {
                return $document->getPersonnelID()?->getId() === $user->getId();
            }
            if ($statut === StatusDoc::ARCHIVE) {
                return $this->checkConfidentialLevel($document, $roles);
            }
            return false;
        }

        // CHEF : ses propres BROUILLON
        //      + tous les SOUMIS de son agence/service
        //      + tous les APPROUVE de son agence/service
        //      + tous les ARCHIVE de son agence/service
        //        toute confidentialité
        if (in_array('ROLE_CHEF', $roles)) {
            if ($statut === StatusDoc::BROUILLON) {
                return $document->getPersonnelID()?->getId() === $user->getId();
            }
            if (in_array($statut, [StatusDoc::SOUMIS, StatusDoc::APPROUVE, StatusDoc::ARCHIVE])) {
                return $this->personnelInSameUnit($document->getPersonnelID(), $user);
            }
            return false;
        }

        // USER : tous ses propres documents (tous statuts), toute confidentialité
        return $document->getPersonnelID()?->getId() === $user->getId();
    }

    // ─── EDIT ─────────────────────────────────────────────────────────────────
    private function canEdit(Document $document, Personnel $user, array $roles): bool
    {
        $statut = $document->getStatucDoc();

        // ARCHIVE : jamais modifiable
        if ($statut === StatusDoc::ARCHIVE) {
            return false;
        }

        // ADMIN : tout sauf ARCHIVE
        if (in_array('ROLE_ADMIN', $roles)) {
            return true;
        }

        // SAP : ses propres BROUILLON ou APPROUVE
        if (in_array('ROLE_SAP', $roles)) {
            if ($statut === StatusDoc::BROUILLON) {
                return $document->getPersonnelID()?->getId() === $user->getId();
            }
            return $statut === StatusDoc::APPROUVE;
        }

        // CHEF : SOUMIS (pour approuver/rejeter) et APPROUVE
        if (in_array('ROLE_CHEF', $roles)) {
            return in_array($statut, [StatusDoc::SOUMIS, StatusDoc::APPROUVE]);
        }

        // USER/RH : ses propres BROUILLON ou REJETE
        if (in_array($statut, [StatusDoc::BROUILLON, StatusDoc::REJETE])) {
            return $document->getPersonnelID()?->getId() === $user->getId();
        }

        return false;
    }

    // ─── DELETE ───────────────────────────────────────────────────────────────
    private function canDelete(Document $document, Personnel $user, array $roles): bool
    {
        $statut = $document->getStatucDoc();

        // Seuls BROUILLON et REJETE sont supprimables
        if (!in_array($statut, [StatusDoc::BROUILLON, StatusDoc::REJETE])) {
            return false;
        }

        if (in_array('ROLE_ADMIN', $roles)) {
            return true;
        }

        // SAP : peut supprimer tout brouillon/rejeté
        if (in_array('ROLE_SAP', $roles)) {
            return true;
        }

        // CHEF : ses propres brouillons uniquement
        if (in_array('ROLE_CHEF', $roles) && $statut === StatusDoc::BROUILLON) {
            return $document->getPersonnelID()?->getId() === $user->getId();
        }

        // USER/RH : ses propres brouillons et rejetés
        return $document->getPersonnelID()?->getId() === $user->getId();
    }

    // ─── HELPERS ──────────────────────────────────────────────────────────────

    /**
     * Vérifie le niveau de confidentialité.
     * Utilisé uniquement pour RH (Public + Confidentiel).
     * Pour les autres rôles, pas de restriction → retourne true directement dans canView.
     */
    private function checkConfidentialLevel(Document $document, array $roles): bool
    {
        $niveauConf = $document->getTypeDocumentID()?->getDossierID()?->getNiveauConf()?->value ?? 'Public';

        // ADMIN, SAP, CHEF, USER : aucune restriction de confidentialité
        foreach (['ROLE_ADMIN', 'ROLE_SAP', 'ROLE_CHEF', 'ROLE_USER'] as $role) {
            if (in_array($role, $roles)) {
                return true; // tous niveaux autorisés
            }
        }

        // RH : Public et Confidentiel seulement
        if (in_array('ROLE_RH', $roles)) {
            return in_array($niveauConf, ['Public', 'Confidentiel']);
        }

        return false;
    }

    private function personnelInSameUnit(Personnel $personnel, Personnel $chef): bool
    {
        $chefAgences = $chef->getAgencePersonnels()
            ->map(fn($ap) => (string) $ap->getAgenceID()?->getId())
            ->filter(fn($id) => $id !== '')
            ->toArray();

        $chefServices = $chef->getDirectionPersonnels()
            ->map(fn($dp) => (string) $dp->getServiceID()?->getId())
            ->filter(fn($id) => $id !== '')
            ->toArray();

        foreach ($personnel->getAgencePersonnels() as $ap) {
            if (in_array((string) $ap->getAgenceID()?->getId(), $chefAgences)) {
                return true;
            }
        }

        foreach ($personnel->getDirectionPersonnels() as $dp) {
            if (in_array((string) $dp->getServiceID()?->getId(), $chefServices)) {
                return true;
            }
        }

        return false;
    }

    // ─── Méthodes utilitaires conservées ──────────────────────────────────────

    private function getAllowedLevelsForUser(Personnel $user): array
    {
        foreach (['ROLE_ADMIN', 'ROLE_SAP', 'ROLE_RH', 'ROLE_CHEF', 'ROLE_USER'] as $role) {
            if ($this->hasRole($user, $role) && isset($this->confidentialLevels[$role])) {
                return $this->confidentialLevels[$role];
            }
        }
        return $this->confidentialLevels['ROLE_USER'] ?? ['Public'];
    }

    private function getAllowedStatusesForUser(Personnel $user): array
    {
        foreach (['ROLE_ADMIN', 'ROLE_SAP', 'ROLE_RH', 'ROLE_CHEF', 'ROLE_USER'] as $role) {
            if ($this->hasRole($user, $role) && isset($this->statusLevels[$role])) {
                return $this->statusLevels[$role];
            }
        }
        return $this->statusLevels['ROLE_USER'] ?? ['Brouillon', 'Soumis', 'Approuvé', 'Rejeté', 'Archivé'];
    }

    private function hasRole(Personnel $user, string $role): bool
    {
        return in_array($role, $user->getRoles(), true);
    }

    private function isSameServiceOrAgence(Personnel $user, Personnel $owner): bool
    {
        $userId  = $user->getId();
        $ownerId = $owner->getId();

        if (array_intersect($this->getServices($userId), $this->getServices($ownerId))) {
            return true;
        }

        return !empty(array_intersect($this->getAgences($userId), $this->getAgences($ownerId)));
    }

    private function getServices(Uuid|string|int $personnelId): array
    {
        if ($personnelId instanceof Uuid) {
            $personnelId = $personnelId->toRfc4122();
        }

        if (!isset($this->serviceCache[$personnelId])) {
            $result = $this->em->createQueryBuilder()
                ->select('IDENTITY(dp.serviceID) as sid')
                ->from('App\Entity\DirectionPersonnel', 'dp')
                ->where('dp.personnelID = :id')
                ->setParameter('id', $personnelId)
                ->getQuery()
                ->getScalarResult();

            $this->serviceCache[$personnelId] = array_column($result, 'sid');
        }

        return $this->serviceCache[$personnelId];
    }

    private function getAgences(Uuid|string|int $personnelId): array
    {
        if ($personnelId instanceof Uuid) {
            $personnelId = $personnelId->toRfc4122();
        }

        if (!isset($this->agenceCache[$personnelId])) {
            $result = $this->em->createQueryBuilder()
                ->select('IDENTITY(ap.agenceID) as aid')
                ->from('App\Entity\AgencePersonnel', 'ap')
                ->where('ap.personnelID = :id')
                ->setParameter('id', $personnelId)
                ->getQuery()
                ->getScalarResult();

            $this->agenceCache[$personnelId] = array_column($result, 'aid');
        }

        return $this->agenceCache[$personnelId];
    }
}