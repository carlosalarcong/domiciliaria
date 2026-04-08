<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Tenant\Notificacion;
use App\Entity\Tenant\User;
use Doctrine\ORM\EntityManagerInterface;

class NotificacionService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    public function crear(
        User $destinatario,
        string $tipo,
        string $titulo,
        ?string $cuerpo = null,
        ?string $url = null,
    ): Notificacion {
        $notif = new Notificacion();
        $notif->setDestinatario($destinatario)
              ->setTipo($tipo)
              ->setTitulo($titulo)
              ->setCuerpo($cuerpo)
              ->setUrl($url);

        $this->em->persist($notif);
        $this->em->flush();

        return $notif;
    }

    /** Crea la misma notificación para múltiples destinatarios */
    public function crearParaVarios(
        array $destinatarios,
        string $tipo,
        string $titulo,
        ?string $cuerpo = null,
        ?string $url = null,
    ): void {
        foreach ($destinatarios as $user) {
            if (!$user instanceof User || !$user->isActivo()) {
                continue;
            }
            $notif = new Notificacion();
            $notif->setDestinatario($user)
                  ->setTipo($tipo)
                  ->setTitulo($titulo)
                  ->setCuerpo($cuerpo)
                  ->setUrl($url);
            $this->em->persist($notif);
        }
        $this->em->flush();
    }
}
