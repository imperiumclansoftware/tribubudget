<?php

namespace App\Security;

use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The security log ("audit" channel, on stderr in production): sign-ins, refusals, and the
 * changes of members and passwords, with who did it, when (the log's time) and from which IP.
 * Never a password nor a PIN (SensitiveDataProcessor masks them anyway).
 */
final readonly class SecurityAudit
{
    public function __construct(
        private LoggerInterface $auditLogger,
        private RequestStack $requests,
    ) {
    }

    public function signedIn(User $member): void
    {
        $this->auditLogger->info('Connexion de {username}', $this->context(['username' => $member->getUsername(), 'member_id' => $member->getId()]));
    }

    public function signInFailed(string $username, string $reason): void
    {
        $this->auditLogger->warning('Échec de connexion pour {username} : {reason}', $this->context(['username' => $username, 'reason' => $reason]));
    }

    public function signedOut(User $member): void
    {
        $this->auditLogger->info('Déconnexion de {username}', $this->context(['username' => $member->getUsername(), 'member_id' => $member->getId()]));
    }

    public function accessDenied(?User $member, string $path): void
    {
        $this->auditLogger->warning('Accès refusé à {path} pour {username}', $this->context([
            'path' => $path,
            'username' => $member?->getUsername() ?? 'anonyme',
            'member_id' => $member?->getId(),
        ]));
    }

    public function passwordChanged(User $by, User $member): void
    {
        $this->auditLogger->notice('Mot de passe de {username} changé par {by}', $this->context([
            'username' => $member->getUsername(),
            'member_id' => $member->getId(),
            'by' => $by->getUsername(),
        ]));
    }

    /** By the server's administrator (app:user:reset-password): no member, no IP */
    public function passwordResetOnServer(User $member): void
    {
        $this->auditLogger->notice('Mot de passe de {username} changé sur le serveur (app:user:reset-password)', $this->context([
            'username' => $member->getUsername(),
            'member_id' => $member->getId(),
        ]));
    }

    public function memberCreated(User $by, User $member): void
    {
        $this->auditLogger->notice('Membre {username} créé avec le rôle {role} par {by}', $this->context([
            'username' => $member->getUsername(),
            'member_id' => $member->getId(),
            'role' => $member->getRole()->value,
            'by' => $by->getUsername(),
        ]));
    }

    public function memberRemoved(User $by, User $member): void
    {
        $this->auditLogger->notice('Membre {username} supprimé par {by}', $this->context([
            'username' => $member->getUsername(),
            'member_id' => $member->getId(),
            'by' => $by->getUsername(),
        ]));
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function context(array $context): array
    {
        return $context + ['ip' => $this->requests->getMainRequest()?->getClientIp()];
    }
}
