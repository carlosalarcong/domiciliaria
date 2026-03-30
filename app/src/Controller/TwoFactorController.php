<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Tenant\User;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\Builder\Builder;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/2fa')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class TwoFactorController extends AbstractController
{
    public function __construct(
        private readonly TotpAuthenticatorInterface $totpAuthenticator,
        private readonly EntityManagerInterface     $em,
    ) {}

    /** Configuración inicial del 2FA — muestra QR */
    #[Route('/setup', name: 'app_2fa_setup')]
    public function setup(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($user->getTotpSecret() === null) {
            $secret = $this->totpAuthenticator->generateSecret();
            $user->setTotpSecret($secret);
            $user->setTwoFactorEnabled(false);
            $this->em->flush();
        }

        $qrContent = $this->totpAuthenticator->getQRContent($user);

        return $this->render('security/2fa_setup.html.twig', [
            'qr_content'  => $qrContent,
            'secret'      => $user->getTotpSecret(),
            'user'        => $user,
        ]);
    }

    /** Confirmar código TOTP y activar 2FA */
    #[Route('/enable', name: 'app_2fa_enable', methods: ['POST'])]
    public function enable(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $code = $request->request->get('code', '');

        if (!$this->totpAuthenticator->checkCode($user, $code)) {
            $this->addFlash('error', 'Código incorrecto. Verifica la app e intenta de nuevo.');
            return $this->redirectToRoute('app_2fa_setup');
        }

        $user->setTwoFactorEnabled(true);
        $backupCodes = $user->generateBackupCodes(10);
        $this->em->flush();

        return $this->render('security/2fa_backup_codes.html.twig', [
            'backup_codes' => $backupCodes,
        ]);
    }

    /** Desactivar 2FA (solo para roles no forzados) */
    #[Route('/disable', name: 'app_2fa_disable', methods: ['POST'])]
    public function disable(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($user->is2FAForced()) {
            $this->addFlash('error', 'Tu rol requiere 2FA obligatorio. No puedes desactivarlo.');
            return $this->redirectToRoute('app_dashboard');
        }

        $user->setTotpSecret(null);
        $user->setTwoFactorEnabled(false);
        $user->setBackupCodes([]);
        $this->em->flush();

        $this->addFlash('success', 'Autenticación de dos factores desactivada.');
        return $this->redirectToRoute('app_dashboard');
    }

    /** Regenerar códigos de respaldo */
    #[Route('/backup-codes/regenerar', name: 'app_2fa_backup_regenerar', methods: ['POST'])]
    public function regenerarBackupCodes(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $codes = $user->generateBackupCodes(10);
        $this->em->flush();

        return $this->render('security/2fa_backup_codes.html.twig', [
            'backup_codes' => $codes,
        ]);
    }
}
