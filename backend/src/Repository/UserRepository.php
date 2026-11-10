<?php

namespace App\Repository;

use App\Entity\Family;
use App\Entity\User;
use App\Enum\FamilyRole;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/** @extends ServiceEntityRepository<User> */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /** @return list<User> parents first, then teenagers, then children, by name */
    public function members(Family $family): array
    {
        return $this->createQueryBuilder('u')
            ->addSelect('CASE WHEN u.role = :parent THEN 0 WHEN u.role = :teen THEN 1 ELSE 2 END AS HIDDEN rank')
            ->where('u.family = :family')
            ->setParameter('family', $family)
            ->setParameter('parent', FamilyRole::Parent->value)
            ->setParameter('teen', FamilyRole::Teen->value)
            ->orderBy('rank')
            ->addOrderBy('u.displayName')
            ->getQuery()
            ->getResult();
    }

    /** @return list<User> */
    public function children(Family $family): array
    {
        return $this->findBy(['family' => $family, 'role' => FamilyRole::Child], ['displayName' => 'ASC']);
    }

    /** @return list<User> */
    public function teens(Family $family): array
    {
        return $this->findBy(['family' => $family, 'role' => FamilyRole::Teen], ['displayName' => 'ASC']);
    }

    /** Called at sign-in when the hash is outdated (bcrypt before 0.6.0, or a cost raised since) */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            return;
        }
        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->flush();
    }

    public function isUsernameTaken(string $username): bool
    {
        return null !== $this->findOneBy(['username' => mb_strtolower($username)]);
    }
}
