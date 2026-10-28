<?php

namespace App\Controller;

use App\Api\ApiPresenter;
use App\Api\FamilyLookup;
use App\Dto\DecisionRequest;
use App\Dto\NewMoneyRequest;
use App\Dto\Text;
use App\Entity\MoneyRequest;
use App\Entity\User;
use App\Money\PocketMoney;
use App\Repository\MoneyRequestRepository;
use App\Security\MoneyRequestVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Requests of the children (an advance, a purchase), answered by the parents. */
#[Route('/api/requests', format: 'json')]
final class MoneyRequestController extends AbstractController
{
    public function __construct(
        private readonly ApiPresenter $presenter,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** The parents see the whole family's requests, a child their own. */
    #[Route('', name: 'api_requests', methods: ['GET'])]
    public function list(#[CurrentUser] User $me, MoneyRequestRepository $requests): JsonResponse
    {
        return $this->json(array_map(
            $this->presenter->request(...),
            $requests->recent($me->getFamily(), $me->isChild() ? $me : null),
        ));
    }

    #[Route('', name: 'api_requests_create', methods: ['POST'])]
    #[IsGranted(MoneyRequestVoter::CREATE, message: 'Les demandes sont faites par les enfants.')]
    public function create(#[CurrentUser] User $me, #[MapRequestPayload] NewMoneyRequest $payload): JsonResponse
    {
        $request = new MoneyRequest($me, $payload->kind, $payload->amount, Text::clean($payload->reason));
        $this->em->persist($request);
        $this->em->flush();

        return $this->json($this->presenter->request($request), Response::HTTP_CREATED);
    }

    #[Route('/{id}/approve', name: 'api_requests_approve', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    #[IsGranted(MoneyRequestVoter::DECIDE, subject: 'request', message: 'Cette demande n’attend plus de réponse.')]
    public function approve(MoneyRequest $request, #[CurrentUser] User $me, #[MapRequestPayload] DecisionRequest $payload, FamilyLookup $lookup, PocketMoney $pocketMoney): JsonResponse
    {
        $request->decide($me, true, $payload->cleanAnswer());
        $pocketMoney->grant($request, $me, $lookup->account($payload->accountId, $me));
        $this->em->flush();

        return $this->json($this->presenter->request($request));
    }

    #[Route('/{id}/reject', name: 'api_requests_reject', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    #[IsGranted(MoneyRequestVoter::DECIDE, subject: 'request', message: 'Cette demande n’attend plus de réponse.')]
    public function reject(MoneyRequest $request, #[CurrentUser] User $me, #[MapRequestPayload] DecisionRequest $payload): JsonResponse
    {
        $request->decide($me, false, $payload->cleanAnswer());
        $this->em->flush();

        return $this->json($this->presenter->request($request));
    }

    #[Route('/{id}/cancel', name: 'api_requests_cancel', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    #[IsGranted(MoneyRequestVoter::CANCEL, subject: 'request', message: 'Tu ne peux retirer que tes demandes en attente.')]
    public function cancel(MoneyRequest $request): JsonResponse
    {
        $request->cancel();
        $this->em->flush();

        return $this->json($this->presenter->request($request));
    }
}
