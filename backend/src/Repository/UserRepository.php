<?php

namespace App\Repository;

use App\Entity\Family;
use App\Entity\User;
use App\Enum\FamilyRole;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<User> */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /** @return list<User> parents first, then children, by name */
    public function members(Family $family): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.family = :family')
            ->setParameter('family', $family)
            ->orderBy('u.role', 'DESC')
            ->addOrderBy('u.displayName')
            ->getQuery()
            ->getResult();
    }

    /** @return list<User> */
    public function children(Family $family): array
    {
        return $this->findBy(['family' => $family, 'role' => FamilyRole::Child], ['displayName' => 'ASC']);
    }

    public function isUsernameTaken(string $username): bool
    {
        return null !== $this->findOneBy(['username' => mb_strtolower($username)]);
    }
}
