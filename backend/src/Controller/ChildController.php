<?php

namespace App\Controller;

use App\Api\ApiPresenter;
use App\Api\FamilyLookup;
use App\Dto\AdjustmentRequest;
use App\Dto\AllowanceRequest;
use App\Dto\ExpenseRequest;
use App\Dto\Text;
use App\Entity\PocketMoneyEntry;
use App\Entity\User;
use App\Enum\PocketMoneyKind;
use App\Money\Allowances;
use App\Money\PocketMoney;
use App\Money\Schedules;
use App\Repository\AllowanceRepository;
use App\Repository\PocketMoneyEntryRepository;
use App\Repository\UserRepository;
use App\Security\ChildVoter;
use App\Security\PocketMoneyEntryVoter;
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
 * Pocket money of the children. A child sees and spends their own; the parents see everyone's,
 * give or take money and set the allowances (see ChildVoter).
 */
#[Route('/api', format: 'json')]
final class ChildController extends AbstractController
{
    public function __construct(
        private readonly ApiPresenter $presenter,
        private readonly EntityManagerInterface $em,
        private readonly PocketMoneyEntryRepository $entries,
        private readonly AllowanceRepository $allowances,
        private readonly PocketMoney $pocketMoney,
    ) {
    }

    #[Route('/children', name: 'api_children', methods: ['GET'])]
    #[IsGranted('ROLE_PARENT', message: 'Seuls les parents voient l’argent de poche de tous les enfants.')]
    public function list(#[CurrentUser] User $me, UserRepository $users, Schedules $schedules): JsonResponse
    {
        $schedules->catchUp($me->getFamily());
        $balances = $this->entries->balances($me->getFamily());

        return $this->json(array_map(
            fn (User $child) => $this->presenter->child($child, $balances[$child->getId()] ?? 0, $this->allowances->forChild($child)),
            $users->children($me->getFamily()),
        ));
    }

    /** A child's pocket money: balance, allowance and history. */
    #[Route('/children/{id}', name: 'api_child', requirements: ['id' => Requirement::DIGITS], methods: ['GET'])]
    #[IsGranted(ChildVoter::VIEW, subject: 'child', message: 'Tu ne peux voir que ta propre tirelire.')]
    public function show(User $child, #[CurrentUser] User $me, Schedules $schedules): JsonResponse
    {
        $schedules->catchUp($me->getFamily());

        return $this->json($this->presented($child, $me));
    }

    #[Route('/children/{id}/expenses', name: 'api_child_expense', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    #[IsGranted(ChildVoter::SPEND, subject: 'child', message: 'Tu ne peux dépenser que ton propre argent de poche.')]
    public function spend(User $child, #[CurrentUser] User $me, #[MapRequestPayload] ExpenseRequest $payload): JsonResponse
    {
        $this->pocketMoney->spend($child, $payload->amount, Text::clean($payload->label), new \DateTimeImmutable($payload->date), $me);
        $this->em->flush();

        return $this->json($this->presented($child, $me), Response::HTTP_CREATED);
    }

    #[Route('/children/{id}/adjustments', name: 'api_child_adjustment', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    #[IsGranted(ChildVoter::MANAGE, subject: 'child', message: 'Seuls les parents donnent ou retirent de l’argent de poche.')]
    public function adjust(User $child, #[CurrentUser] User $me, #[MapRequestPayload] AdjustmentRequest $payload): JsonResponse
    {
        $this->pocketMoney->adjust($child, $payload->amount, Text::clean($payload->label), $me);
        $this->em->flush();

        return $this->json($this->presented($child, $me), Response::HTTP_CREATED);
    }

    #[Route('/children/{id}/allowance', name: 'api_child_allowance', requirements: ['id' => Requirement::DIGITS], methods: ['PUT'])]
    #[IsGranted(ChildVoter::MANAGE, subject: 'child', message: 'Seuls les parents fixent l’argent de poche.')]
    public function setAllowance(User $child, #[CurrentUser] User $me, #[MapRequestPayload] AllowanceRequest $payload, FamilyLookup $lookup, Allowances $allowances): JsonResponse
    {
        $allowances->set($child, $payload, $lookup->householdAccount($payload->accountId, $me));

        return $this->json($this->presented($child, $me));
    }

    #[Route('/children/{id}/allowance', name: 'api_child_allowance_delete', requirements: ['id' => Requirement::DIGITS], methods: ['DELETE'])]
    #[IsGranted(ChildVoter::MANAGE, subject: 'child', message: 'Seuls les parents fixent l’argent de poche.')]
    public function removeAllowance(User $child, #[CurrentUser] User $me, Allowances $allowances): JsonResponse
    {
        $allowances->remove($child);

        return $this->json($this->presented($child, $me));
    }

    /** Undoes a movement. Deleting an advance, or one of its repayments, updates what the child owes. */
    #[Route('/pocket-money/{id}', name: 'api_pocket_money_delete', requirements: ['id' => Requirement::DIGITS], methods: ['DELETE'])]
    #[IsGranted(PocketMoneyEntryVoter::DELETE, subject: 'entry', message: 'Tu ne peux effacer que les dépenses que tu as notées toi-même.')]
    public function deleteEntry(PocketMoneyEntry $entry, #[CurrentUser] User $me): JsonResponse
    {
        $child = $entry->getChild();
        match ($entry->getKind()) {
            PocketMoneyKind::Advance => $child->repay($entry->getAmount()),
            PocketMoneyKind::Repayment => $child->owe(-$entry->getAmount()),
            default => null,
        };
        $this->em->remove($entry);
        $this->em->flush();

        return $this->json($this->presented($child, $me));
    }

    /** @return array<string, mixed> */
    private function presented(User $child, User $viewer): array
    {
        return $this->presenter->child($child, $this->entries->balance($child), $this->allowances->forChild($child)) + [
            'entries' => array_map(fn (PocketMoneyEntry $e) => $this->presenter->pocketMoneyEntry($e, $viewer), $this->entries->history($child)),
        ];
    }
}
