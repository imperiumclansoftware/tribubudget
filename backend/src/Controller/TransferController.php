<?php

namespace App\Controller;

use App\Api\ApiPresenter;
use App\Api\FamilyLookup;
use App\Dto\TransferRequest;
use App\Entity\Account;
use App\Entity\Transfer;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Transfers between the family's accounts: their two operations are changed and deleted together. */
#[Route('/api/transfers', format: 'json')]
#[IsGranted('ROLE_PARENT', message: 'Seuls les parents font des virements.')]
final class TransferController extends AbstractController
{
    use FamilyScope;

    public function __construct(
        private readonly ApiPresenter $presenter,
        private readonly EntityManagerInterface $em,
        private readonly FamilyLookup $lookup,
    ) {
    }

    #[Route('', name: 'api_transfers_create', methods: ['POST'])]
    public function create(#[CurrentUser] User $me, #[MapRequestPayload] TransferRequest $payload): JsonResponse
    {
        [$from, $to] = $this->accounts($payload, $me);
        $transfer = new Transfer($from, $to, $payload->amount, $payload->cleanLabel(), new \DateTimeImmutable($payload->date), $me);
        $this->em->persist($transfer);
        $this->em->flush();

        return $this->json($this->presenter->transfer($transfer), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_transfers_update', requirements: ['id' => Requirement::DIGITS], methods: ['PUT'])]
    public function update(Transfer $transfer, #[CurrentUser] User $me, #[MapRequestPayload] TransferRequest $payload): JsonResponse
    {
        $this->sameFamily($transfer, $me);
        [$from, $to] = $this->accounts($payload, $me);
        $transfer->edit($from, $to, $payload->amount, $payload->cleanLabel(), new \DateTimeImmutable($payload->date));
        $this->em->flush();

        return $this->json($this->presenter->transfer($transfer));
    }

    #[Route('/{id}', name: 'api_transfers_delete', requirements: ['id' => Requirement::DIGITS], methods: ['DELETE'])]
    public function delete(Transfer $transfer, #[CurrentUser] User $me): Response
    {
        $this->sameFamily($transfer, $me);
        $this->em->remove($transfer);
        $this->em->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /** @return array{Account, Account} */
    private function accounts(TransferRequest $payload, User $me): array
    {
        /** @var Account $from validated not null */
        $from = $this->lookup->account($payload->fromAccountId, $me);
        /** @var Account $to validated not null */
        $to = $this->lookup->account($payload->toAccountId, $me);

        return [$from, $to];
    }
}
