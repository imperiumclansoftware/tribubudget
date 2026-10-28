<?php

namespace App\Controller;

use App\Api\ApiPresenter;
use App\Dto\ChangePasswordRequest;
use App\Entity\User;
use App\Money\Schedules;
use App\Repository\MoneyRequestRepository;
use App\Security\PasswordPolicy;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/** The signed-in member's own account. Signing in: /api/login (security.yaml); installation: SetupController. */
#[Route('/api', format: 'json')]
final class AuthController extends AbstractController
{
    public function __construct(
        private readonly ApiPresenter $presenter,
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
    }

    /** Handled by the json_login authenticator of the "login" firewall, which answers with a JWT. */
    #[Route('/login', name: 'api_login', methods: ['POST'])]
    public function login(): never
    {
        throw new \LogicException('Intercepted by the login firewall.');
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
        PasswordPolicy::check($me->getRole(), $payload->newPassword);
        $me->setPassword($this->hasher->hashPassword($me, $payload->newPassword));
        $this->em->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
