<?php

namespace App\Controller;

use App\Api\ApiPresenter;
use App\Api\FamilyLookup;
use App\Dto\AchieveProjectRequest;
use App\Dto\ProjectPrioritiesRequest;
use App\Dto\SavingsProjectRequest;
use App\Dto\Text;
use App\Entity\Account;
use App\Entity\Operation;
use App\Entity\SavingsProject;
use App\Entity\User;
use App\Money\SavingsAllocator;
use App\Money\Today;
use App\Repository\SavingsProjectRepository;
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

/** Projects saved up for on the savings accounts, funded in the order of their priority. */
#[Route('/api/projects', format: 'json')]
#[IsGranted('ROLE_PARENT', message: 'Seuls les parents gèrent les projets.')]
final class SavingsProjectController extends AbstractController
{
    use FamilyScope;

    public function __construct(
        private readonly ApiPresenter $presenter,
        private readonly EntityManagerInterface $em,
        private readonly FamilyLookup $lookup,
        private readonly SavingsProjectRepository $projects,
    ) {
    }

    /** Each savings account with the share of its money each project receives, and the projects carried out. */
    #[Route('', name: 'api_projects', methods: ['GET'])]
    public function list(#[CurrentUser] User $me, SavingsAllocator $allocator): JsonResponse
    {
        return $this->json([
            'accounts' => array_map($this->presenter->savingsAccount(...), $allocator->allocate($me->getFamily())),
            'achieved' => array_map($this->presenter->achievedProject(...), $this->projects->achieved($me->getFamily())),
        ]);
    }

    /** A new project comes last in the priorities of its account. */
    #[Route('', name: 'api_projects_create', methods: ['POST'])]
    public function create(#[CurrentUser] User $me, #[MapRequestPayload] SavingsProjectRequest $payload): Response
    {
        $account = $this->savingsAccount($payload->accountId, $me);
        $this->em->persist(new SavingsProject($account, Text::clean($payload->name), $payload->target, $this->projects->countCurrentOf($account) + 1));
        $this->em->flush();

        return new Response(status: Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_projects_update', requirements: ['id' => Requirement::DIGITS], methods: ['PUT'])]
    public function update(SavingsProject $project, #[CurrentUser] User $me, #[MapRequestPayload] SavingsProjectRequest $payload): Response
    {
        $this->sameFamily($project, $me);
        $project->edit(Text::clean($payload->name), $payload->target);
        $account = $this->savingsAccount($payload->accountId, $me);
        if ($account !== $project->getAccount()) {
            $previous = $project->getAccount();
            $project->moveTo($account, $this->projects->countCurrentOf($account) + 1);
            $this->em->flush();
            $this->renumber($previous);
        }
        $this->em->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/priorities', name: 'api_projects_priorities', methods: ['PUT'])]
    public function prioritize(#[CurrentUser] User $me, #[MapRequestPayload] ProjectPrioritiesRequest $payload): Response
    {
        $account = $this->savingsAccount($payload->accountId, $me);
        $projects = $this->projects->currentOf($account);
        $ids = array_map(static fn (SavingsProject $p) => $p->getId(), $projects);
        $sorted = $payload->projectIds;
        sort($ids);
        sort($sorted);
        if ($ids !== $sorted) {
            throw new UnprocessableEntityHttpException('La liste des projets a changé : rechargez la page.');
        }

        $byId = array_combine(array_map(static fn (SavingsProject $p) => $p->getId(), $projects), $projects);
        foreach (array_values($payload->projectIds) as $index => $id) {
            $byId[$id]->prioritize($index + 1);
        }
        $this->em->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * The project is carried out: it stops taking money. Recording the withdrawal from the
     * savings account (the default) leaves the remaining money to the next projects.
     */
    #[Route('/{id}/achieve', name: 'api_projects_achieve', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function achieve(SavingsProject $project, #[CurrentUser] User $me, #[MapRequestPayload] AchieveProjectRequest $payload, Today $today): Response
    {
        $this->sameFamily($project, $me);
        if ($project->isAchieved()) {
            throw new UnprocessableEntityHttpException('Ce projet est déjà réalisé.');
        }
        $project->achieve();
        if ($payload->recordWithdrawal) {
            $this->em->persist(new Operation(
                $project->getAccount(),
                $this->lookup->category($payload->categoryId, $me),
                -$project->getTarget(),
                mb_substr('Projet : '.$project->getName(), 0, Operation::LABEL_MAX_LENGTH),
                $today->date(),
                $me,
            ));
        }
        $this->em->flush();
        $this->renumber($project->getAccount());

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}', name: 'api_projects_delete', requirements: ['id' => Requirement::DIGITS], methods: ['DELETE'])]
    public function delete(SavingsProject $project, #[CurrentUser] User $me): Response
    {
        $this->sameFamily($project, $me);
        $account = $project->getAccount();
        $this->em->remove($project);
        $this->em->flush();
        $this->renumber($account);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    private function savingsAccount(?int $id, User $me): Account
    {
        $account = $this->lookup->account($id, $me);
        if (null === $account || !$account->isSavings()) {
            throw new UnprocessableEntityHttpException('Un projet se rattache à un compte d’épargne.');
        }

        return $account;
    }

    /** Priorities 1, 2, 3… without gaps once a project leaves the account */
    private function renumber(Account $account): void
    {
        foreach ($this->projects->currentOf($account) as $index => $project) {
            $project->prioritize($index + 1);
        }
        $this->em->flush();
    }
}
