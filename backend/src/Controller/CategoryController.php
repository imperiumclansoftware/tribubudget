<?php

namespace App\Controller;

use App\Api\ApiPresenter;
use App\Dto\CategoryRequest;
use App\Dto\Text;
use App\Entity\Category;
use App\Entity\User;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Categories of income and expenses; the monthly budgets are set on the expense categories. */
#[Route('/api/categories', format: 'json')]
#[IsGranted('ROLE_PARENT', message: 'Seuls les parents gèrent les catégories.')]
final class CategoryController extends AbstractController
{
    use FamilyScope;

    public function __construct(
        private readonly ApiPresenter $presenter,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'api_categories', methods: ['GET'])]
    public function list(#[CurrentUser] User $me, CategoryRepository $categories): JsonResponse
    {
        return $this->json(array_map($this->presenter->category(...), $categories->forFamily($me->getFamily())));
    }

    #[Route('', name: 'api_categories_create', methods: ['POST'])]
    public function create(#[CurrentUser] User $me, #[MapRequestPayload] CategoryRequest $payload): JsonResponse
    {
        $category = new Category($me->getFamily(), Text::clean($payload->name), $payload->kind, strtolower($payload->color));
        $category->setMonthlyBudget($payload->monthlyBudget);
        $this->em->persist($category);
        $this->em->flush();

        return $this->json($this->presenter->category($category), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_categories_update', requirements: ['id' => Requirement::DIGITS], methods: ['PUT'])]
    public function update(Category $category, #[CurrentUser] User $me, #[MapRequestPayload] CategoryRequest $payload): JsonResponse
    {
        $this->sameFamily($category, $me);
        $category->edit(Text::clean($payload->name), $payload->kind, strtolower($payload->color));
        $category->setMonthlyBudget($payload->monthlyBudget);
        $this->em->flush();

        return $this->json($this->presenter->category($category));
    }

    /** Its operations are kept, without a category. */
    #[Route('/{id}', name: 'api_categories_delete', requirements: ['id' => Requirement::DIGITS], methods: ['DELETE'])]
    public function delete(Category $category, #[CurrentUser] User $me): Response
    {
        $this->sameFamily($category, $me);
        $this->em->remove($category);
        $this->em->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
