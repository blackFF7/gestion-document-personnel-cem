<?php
namespace App\Security\Voter;

use App\Entity\Personnel;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class PersonnelVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, ['PERSONNEL_VIEW', 'PERSONNEL_EDIT'])
            && $subject instanceof Personnel;
    }

    protected function voteOnAttribute(
        string $attribute, 
        mixed $subject, 
        TokenInterface $token,
        ?Vote $vote = null
        ): bool
    {
        /** @var Personnel $user */
        $user = $token->getUser();
        if (!$user instanceof Personnel) return false;

        /** @var Personnel $personnel */
        $personnel = $subject;

        $roles = $user->getRoles();

        if ($attribute === 'PERSONNEL_VIEW') {
            if (in_array('ROLE_ADMIN', $roles) || in_array('ROLE_SAP', $roles) || in_array('ROLE_RH', $roles)) return true;
            if (in_array('ROLE_CHEF', $roles)) return $this->sameAgenceOrService($user, $personnel);
            return $user->getId() === $personnel->getId();
        }

        if ($attribute === 'PERSONNEL_EDIT') {
            if (in_array('ROLE_ADMIN', $roles) || in_array('ROLE_SAP', $roles)) return true;
            return $user->getId() === $personnel->getId(); // USER peut éditer son propre profil
        }

        return false;
    }

    private function sameAgenceOrService(Personnel $chef, Personnel $personnel): bool
    {
        // Vérifie même agence
        foreach ($chef->getAgencePersonnels() as $ap) {
            if (!$ap->getAgenceID()) continue;
            foreach ($personnel->getAgencePersonnels() as $ap2) {
                if ($ap->getAgenceID()->getId() === $ap2->getAgenceID()?->getId()) return true;
            }
        }
        // Vérifie même service
        foreach ($chef->getDirectionPersonnels() as $dp) {
            if (!$dp->getServiceID()) continue;
            foreach ($personnel->getDirectionPersonnels() as $dp2) {
                if ($dp->getServiceID()->getId() === $dp2->getServiceID()?->getId()) return true;
            }
        }
        return false;
    }
}