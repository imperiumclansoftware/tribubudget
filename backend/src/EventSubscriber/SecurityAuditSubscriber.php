<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Security\SecurityAudit;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/** Writes sign-ins, sign-outs and access refusals to the security log (SecurityAudit). */
final readonly class SecurityAuditSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private SecurityAudit $audit,
        private Security $security,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            LoginFailureEvent::class => 'onLoginFailure',
            // Before LogoutSubscriber replaces the response; the member is still known
            LogoutEvent::class => ['onLogout', 64],
            // After the firewall has turned AccessDeniedException into an HTTP 403 (priority 1),
            // before ApiErrorListener writes the response (-64)
            KernelEvents::EXCEPTION => ['onException', -32],
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $member = $event->getUser();
        if ($member instanceof User) {
            $this->audit->signedIn($member);
        }
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $badge = $event->getPassport()?->getBadge(UserBadge::class);
        $username = $badge instanceof UserBadge ? $badge->getUserIdentifier() : '?';
        $reason = $event->getException() instanceof TooManyLoginAttemptsAuthenticationException
            ? 'trop d’essais'
            : 'identifiant ou mot de passe incorrect';
        $this->audit->signInFailed($username, $reason);
    }

    public function onLogout(LogoutEvent $event): void
    {
        $member = $event->getToken()?->getUser();
        if ($member instanceof User) {
            $this->audit->signedOut($member);
        }
    }

    public function onException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        if (!$exception instanceof AccessDeniedHttpException && !$exception instanceof AccessDeniedException) {
            return;
        }
        $member = $this->security->getUser();
        $this->audit->accessDenied($member instanceof User ? $member : null, $event->getRequest()->getPathInfo());
    }
}
