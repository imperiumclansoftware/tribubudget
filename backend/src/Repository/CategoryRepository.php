<?php

namespace App\Repository;

use App\Entity\Category;
use App\Entity\Family;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Category> */
class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    /** @return list<Category> expenses first, then income, by name */
    public function forFamily(Family $family): array
    {
        return $this->findBy(['family' => $family], ['kind' => 'ASC', 'name' => 'ASC']);
    }

    public function pocketMoney(Family $family): ?Category
    {
        return $this->findOneBy(['family' => $family, 'code' => Category::POCKET_MONEY]);
    }
}
