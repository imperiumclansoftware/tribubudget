<?php

namespace App\Controller;

use App\Api\ApiPresenter;
use App\Api\FamilyLookup;
use App\Dto\AccountRequest;
use App\Dto\Text;
use App\Entity\Account;
use App\Entity\User;
use App\Enum\AccountType;
use App\Repository\AccountRepository;
use App\Repository\OperationRepository;
use App\Repository\SavingsProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Accounts of the household and of the teenagers, with their current balance. */
#[Route('/api/accounts', format: 'json')]
#[IsGranted('ROLE_PARENT', message: 'Seuls les parents ont accès aux comptes.')]
final class AccountController extends AbstractController
{
    use FamilyScope;

    public function __construct(
        private readonly ApiPresenter $presenter,
        private readonly EntityManagerInterface $em,
        private readonly AccountRepository $accounts,
        private readonly FamilyLookup $lookup,
    ) {
    }

    /** The household's accounts; with ?teens=1, the teenagers' too. */
    #[Route('', name: 'api_accounts', methods: ['GET'])]
    public function list(#[CurrentUser] User $me, #[MapQueryParameter] bool $teens = false): JsonResponse
    {
        $balances = $this->accounts->balances($me->getFamily());
        $reconciled = $this->accounts->balances($me->getFamily(), reconciled: true);

        return $this->json(array_map(
            fn (Account $account) => $this->presenter->account(
                $account,
                $balances[$account->getId()] ?? $account->getInitialBalance(),
                $reconciled[$account->getId()] ?? $account->getInitialBalance(),
            ),
            $teens ? $this->accounts->forFamily($me->getFamily()) : $this->accounts->household($me->getFamily()),
        ));
    }

    #[Route('', name: 'api_accounts_create', methods: ['POST'])]
    public function create(#[CurrentUser] User $me, #[MapRequestPayload] AccountRequest $payload): JsonResponse
    {
        $account = new Account($me->getFamily(), Text::clean($payload->name), $payload->type, $payload->initialBalance, $this->lookup->teen($payload->ownerId, $me));
        $account->setMinimumBalance($payload->minimumBalance);
        $this->em->persist($account);
        $this->em->flush();

        return $this->json($this->presented($account), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_accounts_update', requirements: ['id' => Requirement::DIGITS], methods: ['PUT'])]
    public function update(Account $account, #[CurrentUser] User $me, #[MapRequestPayload] AccountRequest $payload, SavingsProjectRepository $projects): JsonResponse
    {
        $this->sameFamily($account, $me);
        if ($account->isSavings() && AccountType::Savings !== $payload->type && $projects->countCurrentOf($account) > 0) {
            throw new UnprocessableEntityHttpException('Ce compte porte des projets : il reste un compte d’épargne.');
        }
        $owner = $this->lookup->teen($payload->ownerId, $me);
        if ($owner !== $account->getOwner() && $projects->countCurrentOf($account) > 0) {
            throw new UnprocessableEntityHttpException('Ce compte porte des projets du foyer : il reste au foyer.');
        }
        $account->setOwner($owner);
        $account->edit(Text::clean($payload->name), $payload->type, $payload->initialBalance);
        $account->setMinimumBalance($payload->minimumBalance);
        $this->em->flush();

        return $this->json($this->presented($account));
    }

    /** Only an empty account: its operations would otherwise vanish from the history. */
    #[Route('/{id}', name: 'api_accounts_delete', requirements: ['id' => Requirement::DIGITS], methods: ['DELETE'])]
    public function delete(Account $account, #[CurrentUser] User $me, OperationRepository $operations): Response
    {
        $this->sameFamily($account, $me);
        if ($operations->countForAccount($account) > 0) {
            throw new ConflictHttpException('Ce compte a des opérations : supprimez-les ou déplacez-les d’abord.');
        }
        $this->em->remove($account);
        $this->em->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /** @return array<string, mixed> */
    private function presented(Account $account): array
    {
        $family = $account->getFamily();

        return $this->presenter->account(
            $account,
            $this->accounts->balances($family)[$account->getId()] ?? $account->getInitialBalance(),
            $this->accounts->balances($family, reconciled: true)[$account->getId()] ?? $account->getInitialBalance(),
        );
    }
}
