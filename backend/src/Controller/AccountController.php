<?php

namespace App\Controller;

use App\Api\ApiPresenter;
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
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Accounts of the household, with their current balance. */
#[Route('/api/accounts', format: 'json')]
#[IsGranted('ROLE_PARENT', message: 'Seuls les parents ont accès aux comptes.')]
final class AccountController extends AbstractController
{
    use FamilyScope;

    public function __construct(
        private readonly ApiPresenter $presenter,
        private readonly EntityManagerInterface $em,
        private readonly AccountRepository $accounts,
    ) {
    }

    #[Route('', name: 'api_accounts', methods: ['GET'])]
    public function list(#[CurrentUser] User $me): JsonResponse
    {
        $balances = $this->accounts->balances($me->getFamily());

        return $this->json(array_map(
            fn (Account $account) => $this->presenter->account($account, $balances[$account->getId()] ?? $account->getInitialBalance()),
            $this->accounts->forFamily($me->getFamily()),
        ));
    }

    #[Route('', name: 'api_accounts_create', methods: ['POST'])]
    public function create(#[CurrentUser] User $me, #[MapRequestPayload] AccountRequest $payload): JsonResponse
    {
        $account = new Account($me->getFamily(), Text::clean($payload->name), $payload->type, $payload->initialBalance);
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
        return $this->presenter->account($account, $this->accounts->balances($account->getFamily())[$account->getId()] ?? $account->getInitialBalance());
    }
}
