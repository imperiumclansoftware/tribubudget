<?php

namespace App\Controller;

use App\Api\ApiPresenter;
use App\Api\FamilyLookup;
use App\Dto\OperationRequest;
use App\Dto\ReconcileRequest;
use App\Dto\Text;
use App\Entity\MoneyRequest;
use App\Entity\Operation;
use App\Entity\User;
use App\Money\Month;
use App\Money\Schedules;
use App\Money\Today;
use App\Repository\MoneyRequestRepository;
use App\Repository\OperationRepository;
use App\Security\OperationVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Income and expenses: the parents' on every account of the family, a teenager's on their own
 * accounts (see FamilyLookup::account() and OperationVoter).
 */
#[Route('/api/operations', format: 'json')]
#[IsGranted('ROLE_ACCOUNT_HOLDER', message: 'Seuls les parents et les adolescents ont des comptes.')]
final class OperationController extends AbstractController
{
    use FamilyScope;

    public function __construct(
        private readonly ApiPresenter $presenter,
        private readonly EntityManagerInterface $em,
        private readonly FamilyLookup $lookup,
        private readonly MoneyRequestRepository $requests,
    ) {
    }

    /**
     * Operations of one month (the current one by default), optionally of one account or category.
     * Without an account: the household's for a parent, or a teenager's with ?ownerId; a
     * teenager's own for a teenager.
     */
    #[Route('', name: 'api_operations', methods: ['GET'])]
    public function list(
        #[CurrentUser] User $me,
        OperationRepository $operations,
        Today $today,
        Schedules $schedules,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_REGEXP, options: ['regexp' => '/^'.Month::PATTERN.'$/'])] ?string $month = null,
        #[MapQueryParameter] ?int $accountId = null,
        #[MapQueryParameter] ?int $categoryId = null,
        #[MapQueryParameter] ?int $ownerId = null,
    ): JsonResponse {
        $schedules->catchUp($me->getFamily());
        $period = null === $month ? Month::of($today->date()) : Month::parse($month);

        $list = $operations->between(
            $me->getFamily(),
            $period->start,
            $period->end(),
            $this->lookup->account($accountId, $me),
            $this->lookup->category($categoryId, $me),
            $me->isTeen() ? $me : $this->lookup->teen($ownerId, $me),
        );
        $reimbursements = $this->requests->latestForOperations($list);

        return $this->json(array_map(fn (Operation $o) => $this->presenter->operation(
            $o,
            $reimbursements[$o->getId()] ?? null,
            $this->isGranted(OperationVoter::EDIT, $o->getTransfer() ?? $o) && !$this->isLocked($me, $reimbursements[$o->getId()] ?? null),
        ), $list));
    }

    #[Route('', name: 'api_operations_create', methods: ['POST'])]
    public function create(#[CurrentUser] User $me, #[MapRequestPayload] OperationRequest $payload): JsonResponse
    {
        $operation = new Operation(
            $this->lookup->account($payload->accountId, $me),
            $this->lookup->category($payload->categoryId, $me),
            $payload->signedAmount(),
            Text::clean($payload->label),
            new \DateTimeImmutable($payload->date),
            $me,
        );
        $this->em->persist($operation);
        $this->em->flush();

        return $this->json($this->presenter->operation($operation), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_operations_update', requirements: ['id' => Requirement::DIGITS], methods: ['PUT'])]
    public function update(Operation $operation, #[CurrentUser] User $me, #[MapRequestPayload] OperationRequest $payload): JsonResponse
    {
        $this->editable($operation, $me);
        $operation->edit(
            $this->lookup->account($payload->accountId, $me),
            $this->lookup->category($payload->categoryId, $me),
            $payload->signedAmount(),
            Text::clean($payload->label),
            new \DateTimeImmutable($payload->date),
        );
        $this->em->flush();

        return $this->json($this->presenter->operation($operation));
    }

    #[Route('/{id}', name: 'api_operations_delete', requirements: ['id' => Requirement::DIGITS], methods: ['DELETE'])]
    public function delete(Operation $operation, #[CurrentUser] User $me): Response
    {
        $this->editable($operation, $me);
        $this->em->remove($operation);
        $this->em->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /** Any operation, a transfer's or a reimbursed one included: ticking it off changes nothing of it. */
    #[Route('/{id}/reconciled', name: 'api_operations_reconcile', requirements: ['id' => Requirement::DIGITS], methods: ['PUT'])]
    public function reconcile(Operation $operation, #[CurrentUser] User $me, #[MapRequestPayload] ReconcileRequest $payload): JsonResponse
    {
        $this->sameFamily($operation, $me);
        $this->denyAccessUnlessGranted(OperationVoter::RECONCILE, $operation, 'Tu ne peux pointer que les opérations de tes comptes.');
        $operation->reconcile($payload->reconciled);
        $this->em->flush();

        return $this->json($this->presenter->operation($operation));
    }

    private function editable(Operation $operation, User $me): void
    {
        $this->sameFamily($operation, $me);
        $this->denyAccessUnlessGranted(OperationVoter::EDIT, $operation, 'Tu ne peux changer que les opérations que tu as notées toi-même.');
        if (null !== $operation->getTransfer()) {
            throw new UnprocessableEntityHttpException('Cette opération fait partie d’un virement : modifiez le virement.');
        }
        // An expense waiting to be reimbursed stays as asked; once reimbursed, only a parent changes it
        $request = $this->requests->latestForOperation($operation);
        if (!$this->isLocked($me, $request)) {
            return;
        }
        if ($me->isParent()) {
            throw new UnprocessableEntityHttpException('Cette dépense attend son remboursement : répondez d’abord à la demande.');
        }
        throw new UnprocessableEntityHttpException($request->isPending() ? 'Tu as demandé le remboursement de cette dépense : retire d’abord ta demande.' : 'Cette dépense t’a été remboursée : demande à tes parents de la modifier.');
    }

    /** @phpstan-assert-if-true !null $request */
    private function isLocked(User $me, ?MoneyRequest $request): bool
    {
        return null !== $request && ($request->isPending() || ($request->isApproved() && !$me->isParent()));
    }
}
