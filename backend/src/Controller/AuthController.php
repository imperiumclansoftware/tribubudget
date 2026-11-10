<?php

namespace App\Controller;

use App\Api\ApiPresenter;
use App\Dto\ChangePasswordRequest;
use App\Entity\User;
use App\Money\Schedules;
use App\Repository\MoneyRequestRepository;
use App\Security\PasswordPolicy;
use App\Security\SecurityAudit;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Signing in and out (session cookie, security.yaml), and the signed-in member's own account.
 * Installation: SetupController.
 */
#[Route('/api', format: 'json')]
final class AuthController extends AbstractController
{
    public function __construct(
        private readonly ApiPresenter $presenter,
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly PasswordPolicy $passwordPolicy,
        private readonly SecurityAudit $audit,
    ) {
    }

    /**
     * The json_login authenticator checks the credentials first (a failure answers 401 with a
     * generic message, whatever went wrong); once signed in, the request reaches this action.
     */
    #[Route('/login', name: 'api_login', methods: ['POST'])]
    public function login(#[CurrentUser] ?User $me, MoneyRequestRepository $requests): JsonResponse
    {
        if (null === $me) {
            throw new UnauthorizedHttpException('json', 'Identifiant ou mot de passe incorrect.');
        }

        return $this->json($this->presenter->me($me, $me->isParent() ? $requests->countPending($me->getFamily()) : 0));
    }

    /** Handled by the firewall's logout (security.yaml), answered by LogoutSubscriber. */
    #[Route('/logout', name: 'api_logout', methods: ['POST'])]
    public function logout(): never
    {
        throw new \LogicException('Intercepted by the firewall.');
    }

    /** Loaded when the application opens: first records what fell due since the last visit (Schedules). */
    #[Route('/me', name: 'api_me', methods: ['GET'])]
    public function me(#[CurrentUser] User $me, Schedules $schedules, MoneyRequestRepository $requests): JsonResponse
    {
        $schedules->catchUp($me->getFamily());

        return $this->json($this->presenter->me($me, $me->isParent() ? $requests->countPending($me->getFamily()) : 0));
    }

    #[Route('/me/password', name: 'api_me_password', methods: ['POST'])]
    public function changePassword(#[CurrentUser] User $me, #[MapRequestPayload] ChangePasswordRequest $payload): Response
    {
        if (!$this->hasher->isPasswordValid($me, $payload->currentPassword)) {
            throw new UnprocessableEntityHttpException($me->isChild() ? 'Ton code actuel n’est pas le bon.' : 'Le mot de passe actuel est incorrect.');
        }
        $this->passwordPolicy->check($me->getRole(), $payload->newPassword);
        $me->setPassword($this->hasher->hashPassword($me, $payload->newPassword));
        $this->em->flush();
        $this->audit->passwordChanged($me, $me);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
