<?php

namespace App\Money;

use App\Entity\Account;
use App\Entity\Category;
use App\Entity\Family;
use App\Enum\AccountType;
use App\Enum\CategoryKind;
use Doctrine\ORM\EntityManagerInterface;

/** Gives a new family an account and the usual categories, ready to use. */
final class FamilySetup
{
    /** name => color, for the expenses */
    private const EXPENSES = [
        'Alimentation' => '#e07a5f',
        'Logement' => '#3d5a80',
        'Transports' => '#5b8e7d',
        'Santé' => '#d1495b',
        'Loisirs' => '#8f6bb3',
        'Éducation' => '#edae49',
        'Vêtements' => '#c46ea0',
        'Abonnements' => '#4d7ea8',
        'Divers' => '#8d8d8d',
    ];

    private const INCOME = [
        'Salaires' => '#2a9d8f',
        'Allocations' => '#59a96a',
        'Autres revenus' => '#7fb069',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function populate(Family $family): void
    {
        $this->em->persist(new Account($family, 'Compte courant', AccountType::Checking));
        foreach (self::EXPENSES as $name => $color) {
            $this->em->persist(new Category($family, $name, CategoryKind::Expense, $color));
        }
        $this->em->persist(new Category($family, 'Argent de poche', CategoryKind::Expense, '#f4a261', Category::POCKET_MONEY));
        foreach (self::INCOME as $name => $color) {
            $this->em->persist(new Category($family, $name, CategoryKind::Income, $color));
        }
    }
}
