<?php

namespace App\Controller;

use App\Api\ApiPresenter;
use App\Api\FamilyLookup;
use App\Dto\OperationRequest;
use App\Dto\Text;
use App\Entity\Operation;
use App\Entity\User;
use App\Money\Month;
use App\Money\Schedules;
use App\Money\Today;
use App\Repository\OperationRepository;
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

/** Income and expenses of the household accounts. */
#[Route('/api/operations', format: 'json')]
#[IsGranted('ROLE_PARENT', message: 'Seuls les parents ont accès aux opérations.')]
final class OperationController extends AbstractController
{
    use FamilyScope;

    public function __construct(
        private readonly ApiPresenter $presenter,
        private readonly EntityManagerInterface $em,
        private readonly FamilyLookup $lookup,
    ) {
    }

    /** Operations of one month (the current one by default), optionally of one account or category. */
    #[Route('', name: 'api_operations', methods: ['GET'])]
    public function list(
        #[CurrentUser] User $me,
        OperationRepository $operations,
        Today $today,
        Schedules $schedules,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_REGEXP, options: ['regexp' => '/^'.Month::PATTERN.'$/'])] ?string $month = null,
        #[MapQueryParameter] ?int $accountId = null,
        #[MapQueryParameter] ?int $categoryId = null,
    ): JsonResponse {
        $schedules->catchUp($me->getFamily());
        $period = null === $month ? Month::of($today->date()) : Month::parse($month);

        return $this->json(array_map($this->presenter->operation(...), $operations->between(
            $me->getFamily(),
            $period->start,
            $period->end(),
            $this->lookup->account($accountId, $me),
            $this->lookup->category($categoryId, $me),
        )));
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
        $this->sameFamily($operation, $me);
        $this->notATransfer($operation);
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
        $this->sameFamily($operation, $me);
        $this->notATransfer($operation);
        $this->em->remove($operation);
        $this->em->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    private function notATransfer(Operation $operation): void
    {
        if (null !== $operation->getTransfer()) {
            throw new UnprocessableEntityHttpException('Cette opération fait partie d’un virement : modifiez le virement.');
        }
    }
}
