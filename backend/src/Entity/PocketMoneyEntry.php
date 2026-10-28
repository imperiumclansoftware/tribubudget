<?php

namespace App\Entity;

use App\Enum\PocketMoneyKind;
use App\Repository\PocketMoneyEntryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Movement of a child's pocket money: their balance is the sum of these. */
#[ORM\Entity(repositoryClass: PocketMoneyEntryRepository::class)]
class PocketMoneyEntry implements FamilyOwned
{
    public const LABEL_MAX_LENGTH = 120;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $child,
        #[ORM\Column(length: 12, enumType: PocketMoneyKind::class)]
        private PocketMoneyKind $kind,
        /** In cents: negative when money goes out */
        #[ORM\Column]
        private int $amount,
        #[ORM\Column(length: self::LABEL_MAX_LENGTH)]
        private string $label,
        #[ORM\Column(type: Types::DATE_IMMUTABLE)]
        private \DateTimeImmutable $date,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        private ?User $createdBy = null,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFamily(): Family
    {
        return $this->child->getFamily();
    }

    public function getChild(): User
    {
        return $this->child;
    }

    public function getKind(): PocketMoneyKind
    {
        return $this->kind;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * A parent deletes any movement of their family; a child only the expenses they recorded
     * themselves (a mistake), never an allowance or an advance.
     */
    public function canBeDeletedBy(User $user): bool
    {
        if ($user->getFamily() !== $this->getFamily()) {
            return false;
        }

        return $user->isParent()
            || ($this->child === $user && $this->createdBy === $user && PocketMoneyKind::Expense === $this->kind);
    }
}
