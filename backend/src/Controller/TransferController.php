<?php

namespace App\Controller;

use App\Api\ApiPresenter;
use App\Api\FamilyLookup;
use App\Dto\TransferRequest;
use App\Entity\Account;
use App\Entity\Category;
use App\Entity\Transfer;
use App\Entity\User;
use App\Enum\CategoryKind;
use App\Security\OperationVoter;
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
 * Transfers between the family's accounts: their two operations are changed and deleted together.
 * The parents move money between any accounts, a teenager between their own.
 */
#[Route('/api/transfers', format: 'json')]
#[IsGranted('ROLE_ACCOUNT_HOLDER', message: 'Seuls les parents et les adolescents font des virements.')]
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
        $transfer = new Transfer($from, $to, $payload->amount, $payload->cleanLabel(), new \DateTimeImmutable($payload->date), $me, $this->category($payload, $from, $me));
        $this->em->persist($transfer);
        $this->em->flush();

        return $this->json($this->presenter->transfer($transfer), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_transfers_update', requirements: ['id' => Requirement::DIGITS], methods: ['PUT'])]
    public function update(Transfer $transfer, #[CurrentUser] User $me, #[MapRequestPayload] TransferRequest $payload): JsonResponse
    {
        $this->editable($transfer, $me);
        [$from, $to] = $this->accounts($payload, $me);
        $transfer->edit($from, $to, $payload->amount, $payload->cleanLabel(), new \DateTimeImmutable($payload->date), $this->category($payload, $from, $me));
        $this->em->flush();

        return $this->json($this->presenter->transfer($transfer));
    }

    #[Route('/{id}', name: 'api_transfers_delete', requirements: ['id' => Requirement::DIGITS], methods: ['DELETE'])]
    public function delete(Transfer $transfer, #[CurrentUser] User $me): Response
    {
        $this->editable($transfer, $me);
        $this->em->remove($transfer);
        $this->em->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    private function editable(Transfer $transfer, User $me): void
    {
        $this->sameFamily($transfer, $me);
        $this->denyAccessUnlessGranted(OperationVoter::EDIT, $transfer, 'Tu ne peux changer que les virements que tu as faits toi-même.');
    }

    /** A budget is the household's: only money taken out of a household account counts in one. */
    private function category(TransferRequest $payload, Account $from, User $me): ?Category
    {
        $category = $this->lookup->category($payload->categoryId, $me);
        if (null === $category) {
            return null;
        }
        if (CategoryKind::Expense !== $category->getKind()) {
            throw new UnprocessableEntityHttpException('Choisissez une catégorie de dépenses.');
        }
        if (!$from->isHousehold()) {
            throw new UnprocessableEntityHttpException('Seul un virement depuis un compte du foyer compte dans un budget.');
        }

        return $category;
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
