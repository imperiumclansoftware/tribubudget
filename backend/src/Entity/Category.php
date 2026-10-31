<?php

namespace App\Entity;

use App\Enum\CategoryKind;
use App\Repository\CategoryRepository;
use Doctrine\ORM\Mapping as ORM;

/** Category of income or expense; an expense category may carry a monthly budget. */
#[ORM\Entity(repositoryClass: CategoryRepository::class)]
class Category implements FamilyOwned
{
    public const NAME_MAX_LENGTH = 40;
    /** Category of the allowances and advances paid to the children */
    public const POCKET_MONEY = 'pocket_money';
    /** Category of the maintenance of the vehicles, done (see Garage) */
    public const VEHICLE_MAINTENANCE = 'vehicle_maintenance';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Monthly spending limit in cents; null when the category has no budget */
    #[ORM\Column(nullable: true)]
    private ?int $monthlyBudget = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Family $family,
        #[ORM\Column(length: self::NAME_MAX_LENGTH)]
        private string $name,
        #[ORM\Column(length: 10, enumType: CategoryKind::class)]
        private CategoryKind $kind,
        /** #rrggbb */
        #[ORM\Column(length: 7)]
        private string $color,
        /** Set on the categories the application itself relies on (POCKET_MONEY) */
        #[ORM\Column(length: 20, nullable: true)]
        private ?string $code = null,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFamily(): Family
    {
        return $this->family;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getKind(): CategoryKind
    {
        return $this->kind;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function getMonthlyBudget(): ?int
    {
        return $this->monthlyBudget;
    }

    public function edit(string $name, CategoryKind $kind, string $color): void
    {
        $this->name = $name;
        $this->kind = $kind;
        $this->color = $color;
        if (CategoryKind::Income === $kind) {
            $this->monthlyBudget = null;
        }
    }

    public function setMonthlyBudget(?int $monthlyBudget): void
    {
        $this->monthlyBudget = CategoryKind::Expense === $this->kind ? $monthlyBudget : null;
    }
}
