<?php

namespace App\Controller;

use App\Api\ApiPresenter;
use App\Dto\ChangePasswordRequest;
use App\Dto\RegisterRequest;
use App\Dto\Text;
use App\Entity\Family;
use App\Entity\User;
use App\Enum\FamilyRole;
use App\Money\FamilySetup;
use App\Money\Schedules;
use App\Repository\MoneyRequestRepository;
use App\Repository\UserRepository;
use App\Security\PasswordPolicy;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/** Creating a family, and the signed-in member's own account. Signing in: /api/login (security.yaml). */
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

    /** A parent creates their family and is signed in right away. */
    #[Route('/register', name: 'api_register', methods: ['POST'])]
    public function register(
        #[MapRequestPayload] RegisterRequest $payload,
        Request $request,
        UserRepository $users,
        FamilySetup $setup,
        JWTTokenManagerInterface $jwt,
        #[Autowire(service: 'limiter.registration')] RateLimiterFactoryInterface $registrationLimiter,
    ): JsonResponse {
        if (!$registrationLimiter->create($request->getClientIp())->consume()->isAccepted()) {
            throw new TooManyRequestsHttpException(message: 'Trop de familles créées depuis cette adresse, réessayez plus tard.');
        }
        PasswordPolicy::check(FamilyRole::Parent, $payload->password);
        if ($users->isUsernameTaken($payload->username)) {
            throw new UnprocessableEntityHttpException('Cet identifiant est déjà pris.');
        }

        $family = new Family(Text::clean($payload->familyName));
        $parent = new User($family, $payload->username, Text::clean($payload->displayName), FamilyRole::Parent);
        $parent->setPassword($this->hasher->hashPassword($parent, $payload->password));
        $this->em->persist($family);
        $this->em->persist($parent);
        $setup->populate($family);
        $this->em->flush();

        return $this->json([
            'token' => $jwt->create($parent),
            'user' => $this->presenter->me($parent, 0),
        ], Response::HTTP_CREATED);
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
