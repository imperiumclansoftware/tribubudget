<?php

namespace App\Controller;

use App\Api\ApiPresenter;
use App\Api\FamilyLookup;
use App\Dto\AllowanceRequest;
use App\Entity\User;
use App\Money\Allowances;
use App\Money\Schedules;
use App\Repository\AccountRepository;
use App\Repository\AllowanceRepository;
use App\Repository\UserRepository;
use App\Security\TeenVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Accounts of the teenagers. A teenager sees their own; the parents oversee everyone's and set
 * the allowances (see TeenVoter). Their operations and transfers go through /api/operations and
 * /api/transfers.
 */
#[Route('/api/teens', format: 'json')]
final class TeenController extends AbstractController
{
    public function __construct(
        private readonly ApiPresenter $presenter,
        private readonly AccountRepository $accounts,
        private readonly AllowanceRepository $allowances,
    ) {
    }

    #[Route('', name: 'api_teens', methods: ['GET'])]
    #[IsGranted('ROLE_PARENT', message: 'Seuls les parents voient les comptes de tous les adolescents.')]
    public function list(#[CurrentUser] User $me, UserRepository $users, Schedules $schedules): JsonResponse
    {
        $schedules->catchUp($me->getFamily());
        $balances = $this->accounts->balances($me->getFamily());
        $reconciled = $this->accounts->balances($me->getFamily(), reconciled: true);

        return $this->json(array_map(
            fn (User $teen) => $this->presenter->teen($teen, $this->accounts->ownedBy($teen), $balances, $reconciled, $this->allowances->forChild($teen)),
            $users->teens($me->getFamily()),
        ));
    }

    #[Route('/{id}', name: 'api_teen', requirements: ['id' => Requirement::DIGITS], methods: ['GET'])]
    #[IsGranted(TeenVoter::VIEW, subject: 'teen', message: 'Tu ne peux voir que tes propres comptes.')]
    public function show(User $teen, #[CurrentUser] User $me, Schedules $schedules): JsonResponse
    {
        $schedules->catchUp($me->getFamily());

        return $this->json($this->presented($teen));
    }

    #[Route('/{id}/allowance', name: 'api_teen_allowance', requirements: ['id' => Requirement::DIGITS], methods: ['PUT'])]
    #[IsGranted(TeenVoter::MANAGE, subject: 'teen', message: 'Seuls les parents fixent l’argent de poche.')]
    public function setAllowance(User $teen, #[CurrentUser] User $me, #[MapRequestPayload] AllowanceRequest $payload, FamilyLookup $lookup, Allowances $allowances): JsonResponse
    {
        $allowances->set($teen, $payload, $lookup->householdAccount($payload->accountId, $me));

        return $this->json($this->presented($teen));
    }

    #[Route('/{id}/allowance', name: 'api_teen_allowance_delete', requirements: ['id' => Requirement::DIGITS], methods: ['DELETE'])]
    #[IsGranted(TeenVoter::MANAGE, subject: 'teen', message: 'Seuls les parents fixent l’argent de poche.')]
    public function removeAllowance(User $teen, Allowances $allowances): JsonResponse
    {
        $allowances->remove($teen);

        return $this->json($this->presented($teen));
    }

    /** @return array<string, mixed> */
    private function presented(User $teen): array
    {
        $family = $teen->getFamily();

        return $this->presenter->teen($teen, $this->accounts->ownedBy($teen), $this->accounts->balances($family), $this->accounts->balances($family, reconciled: true), $this->allowances->forChild($teen));
    }
}
