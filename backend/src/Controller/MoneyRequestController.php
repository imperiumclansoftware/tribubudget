<?php

namespace App\Controller;

use App\Api\ApiPresenter;
use App\Api\FamilyLookup;
use App\Dto\DecisionRequest;
use App\Dto\NewMoneyRequest;
use App\Dto\ReimbursementRequest;
use App\Dto\Text;
use App\Entity\MoneyRequest;
use App\Entity\User;
use App\Enum\RequestKind;
use App\Money\PocketMoney;
use App\Money\TeenMoney;
use App\Repository\MoneyRequestRepository;
use App\Repository\OperationRepository;
use App\Security\MoneyRequestVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Requests of the children and teenagers (an advance, a purchase; for a teenager, the
 * reimbursement of an expense), answered by the parents.
 */
#[Route('/api/requests', format: 'json')]
final class MoneyRequestController extends AbstractController
{
    public function __construct(
        private readonly ApiPresenter $presenter,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** The parents see the whole family's requests, a child or a teenager their own. */
    #[Route('', name: 'api_requests', methods: ['GET'])]
    public function list(#[CurrentUser] User $me, MoneyRequestRepository $requests): JsonResponse
    {
        return $this->json(array_map(
            $this->presenter->request(...),
            $requests->recent($me->getFamily(), $me->isParent() ? null : $me),
        ));
    }

    #[Route('', name: 'api_requests_create', methods: ['POST'])]
    #[IsGranted(MoneyRequestVoter::CREATE, message: 'Les demandes sont faites par les enfants et les adolescents.')]
    public function create(#[CurrentUser] User $me, #[MapRequestPayload] NewMoneyRequest $payload): JsonResponse
    {
        $request = new MoneyRequest($me, $payload->kind, $payload->amount, Text::clean($payload->reason));
        $this->em->persist($request);
        $this->em->flush();

        return $this->json($this->presenter->request($request), Response::HTTP_CREATED);
    }

    /** A teenager asks to be paid back for an expense recorded on one of their accounts. */
    #[Route('/reimbursement', name: 'api_requests_reimbursement', methods: ['POST'])]
    #[IsGranted('ROLE_TEEN', message: 'Les demandes de remboursement sont faites par les adolescents.')]
    public function askReimbursement(#[CurrentUser] User $me, #[MapRequestPayload] ReimbursementRequest $payload, OperationRepository $operations, MoneyRequestRepository $requests): JsonResponse
    {
        $operation = $operations->find($payload->operationId);
        if (null === $operation || $operation->getAccount()->getOwner() !== $me) {
            throw new UnprocessableEntityHttpException('Cette dépense n’existe pas.');
        }
        if ($operation->getAmount() >= 0 || null !== $operation->getTransfer()) {
            throw new UnprocessableEntityHttpException('Seule une dépense peut être remboursée.');
        }
        $latest = $requests->latestForOperation($operation);
        if (null !== $latest && ($latest->isPending() || $latest->isApproved())) {
            throw new UnprocessableEntityHttpException('Tu as déjà demandé le remboursement de cette dépense.');
        }

        $request = new MoneyRequest($me, RequestKind::Reimbursement, -$operation->getAmount(), mb_substr($operation->getLabel(), 0, MoneyRequest::REASON_MAX_LENGTH), $operation);
        $this->em->persist($request);
        $this->em->flush();

        return $this->json($this->presenter->request($request), Response::HTTP_CREATED);
    }

    #[Route('/{id}/approve', name: 'api_requests_approve', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    #[IsGranted(MoneyRequestVoter::DECIDE, subject: 'request', message: 'Cette demande n’attend plus de réponse.')]
    public function approve(MoneyRequest $request, #[CurrentUser] User $me, #[MapRequestPayload] DecisionRequest $payload, FamilyLookup $lookup, PocketMoney $pocketMoney, TeenMoney $teenMoney): JsonResponse
    {
        $request->decide($me, true, $payload->cleanAnswer());
        $account = $lookup->householdAccount($payload->accountId, $me);
        if ($request->getChild()->isTeen()) {
            $teenMoney->grant($request, $me, $account, $lookup->category($payload->categoryId, $me));
        } else {
            $pocketMoney->grant($request, $me, $account);
        }
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
