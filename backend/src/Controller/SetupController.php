<?php

namespace App\Controller;

use App\Api\ApiPresenter;
use App\Dto\SetupCodeRequest;
use App\Dto\SetupRequest;
use App\Dto\Text;
use App\Entity\Family;
use App\Entity\User;
use App\Enum\FamilyRole;
use App\Money\FamilySetup;
use App\Repository\FamilyRepository;
use App\Repository\UserRepository;
use App\Security\PasswordPolicy;
use App\Setup\SetupCode;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Installation: creates the family of this server and its first parent, once, with the code shown
 * on the server (container logs, or bin/console app:setup:code). There is no other sign-up: the
 * parents add the other members.
 */
#[Route('/api/setup', format: 'json')]
final class SetupController extends AbstractController
{
    public function __construct(
        private readonly FamilyRepository $families,
        private readonly SetupCode $code,
        #[Autowire(service: 'limiter.setup_code')]
        private readonly RateLimiterFactoryInterface $setupCodeLimiter,
    ) {
    }

    /** Whether TribuBudget is installed; otherwise the code is ready on the server. */
    #[Route('', name: 'api_setup', methods: ['GET'])]
    public function status(): JsonResponse
    {
        $installed = $this->installed();
        if (!$installed) {
            $this->code->current();
        }

        return $this->json(['installed' => $installed]);
    }

    /** First step of the installation page: checks the code before asking for the rest. */
    #[Route('/verify', name: 'api_setup_verify', methods: ['POST'])]
    public function verify(#[MapRequestPayload] SetupCodeRequest $payload, Request $request): Response
    {
        $this->assertNotInstalled();
        $this->assertCode($payload->code, $request);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('', name: 'api_setup_install', methods: ['POST'])]
    public function install(
        #[MapRequestPayload] SetupRequest $payload,
        Request $request,
        LockFactory $locks,
        EntityManagerInterface $em,
        UserRepository $users,
        UserPasswordHasherInterface $hasher,
        FamilySetup $setup,
        JWTTokenManagerInterface $jwt,
        ApiPresenter $presenter,
    ): JsonResponse {
        // Two installation pages submitted at the same time: only one installs
        $lock = $locks->createLock('app-setup');
        $lock->acquire(true);
        try {
            $this->assertNotInstalled();
            $this->assertCode($payload->code, $request);
            PasswordPolicy::check(FamilyRole::Parent, $payload->password);
            if ($users->isUsernameTaken($payload->username)) {
                throw new UnprocessableEntityHttpException('Cet identifiant est déjà pris.');
            }

            $family = new Family(Text::clean($payload->familyName));
            $parent = new User($family, $payload->username, Text::clean($payload->displayName), FamilyRole::Parent);
            $parent->setPassword($hasher->hashPassword($parent, $payload->password));
            $em->persist($family);
            $em->persist($parent);
            $setup->populate($family);
            $em->flush();
            $this->code->clear();
        } finally {
            $lock->release();
        }

        return $this->json([
            'token' => $jwt->create($parent),
            'user' => $presenter->me($parent, 0),
        ], Response::HTTP_CREATED);
    }

    private function installed(): bool
    {
        return $this->families->count() > 0;
    }

    private function assertNotInstalled(): void
    {
        if ($this->installed()) {
            throw new ConflictHttpException('TribuBudget est déjà installé : connectez-vous.');
        }
    }

    /** A few attempts per address: the code cannot be guessed by trying. */
    private function assertCode(string $code, Request $request): void
    {
        $limit = $this->setupCodeLimiter->create($request->getClientIp() ?? 'unknown')->consume();
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException($limit->getRetryAfter()->getTimestamp() - time(), 'Trop de tentatives : réessayez dans quelques minutes.');
        }
        if (!$this->code->matches($code)) {
            throw new AccessDeniedHttpException('Code d’installation incorrect. Il s’affiche dans les journaux du serveur (ou : bin/console app:setup:code).');
        }
    }
}
