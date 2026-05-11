<?php

namespace App\Form\EventSubscriber;

use App\Entity\Personnel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class PersonnelUsernameSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::PRE_SUBMIT => 'preSubmit',
        ];
    }

    public function preSubmit(FormEvent $event): void
    {
        $data = $event->getData();

        if (!$data) {
            return;
        }

        // Si autoUsername activé
        if (
            isset($data['autoUsername']) &&
            $data['autoUsername']
        ) {

            $im = $data['IM'] ?? '';
            $nom = strtolower($data['nomAg'] ?? '');
            $date = '';

            if (!empty($data['dateNaissAg'])) {
                $date = (new \DateTime($data['dateNaissAg']))
                    ->format('dmY');
            }

            $data['username'] = $im . $nom . $date;
        }

        $event->setData($data);
    }
}