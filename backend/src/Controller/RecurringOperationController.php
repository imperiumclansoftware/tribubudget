<?php

namespace App\Controller;

use App\Api\ApiPresenter;
use App\Api\FamilyLookup;
use App\Dto\RecurringOperationRequest;
use App\Dto\Text;
use App\Entity\RecurringOperation;
use App\Entity\User;
use App\Money\Schedules;
use App\Repository\RecurringOperationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Income and expenses at fixed dates (rent, subscriptions, salary). Saving one records right
 * away the dates already passed; deleting one keeps the operations already recorded.
 */
#[Route('/api/recurring-operations', format: 'json')]
#[IsGranted('ROLE_PARENT', message: 'Seuls les parents ont accès aux opérations.')]
final class RecurringOperationController extends AbstractController
{
    use FamilyScope;

    public function __construct(
        private readonly ApiPresenter $presenter,
        private readonly EntityManagerInterface $em,
        private readonly FamilyLookup $lookup,
        private readonly Schedules $schedules,
    ) {
    }

    #[Route('', name: 'api_recurring_operations', methods: ['GET'])]
    public function list(#[CurrentUser] User $me, RecurringOperationRepository $recurring): JsonResponse
    {
        $this->schedules->catchUp($me->getFamily());

        return $this->json(array_map($this->presenter->recurringOperation(...), $recurring->forFamily($me->getFamily())));
    }

    #[Route('', name: 'api_recurring_operations_create', methods: ['POST'])]
    public function create(#[CurrentUser] User $me, #[MapRequestPayload] RecurringOperationRequest $payload): JsonResponse
    {
        $recurring = new RecurringOperation(
            $this->lookup->account($payload->accountId, $me),
            $this->lookup->category($payload->categoryId, $me),
            $payload->signedAmount(),
            Text::clean($payload->label),
            $payload->frequency,
            new \DateTimeImmutable($payload->nextOn),
            null === $payload->endsOn ? null : new \DateTimeImmutable($payload->endsOn),
            $payload->active,
        );
        $this->em->persist($recurring);
        $this->em->flush();
        $this->schedules->catchUp($me->getFamily());

        return $this->json($this->presenter->recurringOperation($recurring), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_recurring_operations_update', requirements: ['id' => Requirement::DIGITS], methods: ['PUT'])]
    public function update(RecurringOperation $recurring, #[CurrentUser] User $me, #[MapRequestPayload] RecurringOperationRequest $payload): JsonResponse
    {
        $this->sameFamily($recurring, $me);
        $recurring->edit(
            $this->lookup->account($payload->accountId, $me),
            $this->lookup->category($payload->categoryId, $me),
            $payload->signedAmount(),
            Text::clean($payload->label),
            $payload->frequency,
            new \DateTimeImmutable($payload->nextOn),
            null === $payload->endsOn ? null : new \DateTimeImmutable($payload->endsOn),
            $payload->active,
        );
        $this->em->flush();
        $this->schedules->catchUp($me->getFamily());

        return $this->json($this->presenter->recurringOperation($recurring));
    }

    #[Route('/{id}', name: 'api_recurring_operations_delete', requirements: ['id' => Requirement::DIGITS], methods: ['DELETE'])]
    public function delete(RecurringOperation $recurring, #[CurrentUser] User $me): Response
    {
        $this->sameFamily($recurring, $me);
        $this->em->remove($recurring);
        $this->em->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
