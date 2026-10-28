<?php

namespace App\Controller;

use App\Api\ApiPresenter;
use App\Dto\MemberRequest;
use App\Dto\PasswordRequest;
use App\Dto\RenameRequest;
use App\Dto\Text;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\PasswordPolicy;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** The parents manage the members of the family: other parents and the children. */
#[Route('/api/members', format: 'json')]
#[IsGranted('ROLE_PARENT', message: 'Seuls les parents gèrent les membres de la famille.')]
final class MemberController extends AbstractController
{
    use FamilyScope;

    public function __construct(
        private readonly ApiPresenter $presenter,
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
    }

    #[Route('', name: 'api_members', methods: ['GET'])]
    public function list(#[CurrentUser] User $me, UserRepository $users): JsonResponse
    {
        return $this->json(array_map($this->presenter->user(...), $users->members($me->getFamily())));
    }

    #[Route('', name: 'api_members_create', methods: ['POST'])]
    public function create(#[CurrentUser] User $me, #[MapRequestPayload] MemberRequest $payload, UserRepository $users): JsonResponse
    {
        PasswordPolicy::check($payload->role, $payload->password);
        if ($users->isUsernameTaken($payload->username)) {
            throw new UnprocessableEntityHttpException('Cet identifiant est déjà pris.');
        }

        $member = new User($me->getFamily(), $payload->username, Text::clean($payload->displayName), $payload->role);
        $member->setPassword($this->hasher->hashPassword($member, $payload->password));
        $this->em->persist($member);
        $this->em->flush();

        return $this->json($this->presenter->user($member), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_members_rename', requirements: ['id' => Requirement::DIGITS], methods: ['PATCH'])]
    public function rename(User $member, #[CurrentUser] User $me, #[MapRequestPayload] RenameRequest $payload): JsonResponse
    {
        $this->sameFamily($member, $me);
        $member->rename(Text::clean($payload->displayName));
        $this->em->flush();

        return $this->json($this->presenter->user($member));
    }

    /** A forgotten password or PIN is reset by a parent. */
    #[Route('/{id}/password', name: 'api_members_password', requirements: ['id' => Requirement::DIGITS], methods: ['PUT'])]
    public function resetPassword(User $member, #[CurrentUser] User $me, #[MapRequestPayload] PasswordRequest $payload): Response
    {
        $this->sameFamily($member, $me);
        PasswordPolicy::check($member->getRole(), $payload->password);
        $member->setPassword($this->hasher->hashPassword($member, $payload->password));
        $this->em->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /** Removes the member and their pocket money; the family always keeps the parent doing it. */
    #[Route('/{id}', name: 'api_members_delete', requirements: ['id' => Requirement::DIGITS], methods: ['DELETE'])]
    public function delete(User $member, #[CurrentUser] User $me): Response
    {
        $this->sameFamily($member, $me);
        if ($member === $me) {
            throw new UnprocessableEntityHttpException('Vous ne pouvez pas supprimer votre propre compte.');
        }
        $this->em->remove($member);
        $this->em->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
