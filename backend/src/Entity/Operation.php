<?php

namespace App\Entity;

use App\Repository\OperationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Income (positive amount) or expense (negative amount) on a household account. */
#[ORM\Entity(repositoryClass: OperationRepository::class)]
#[ORM\Index(columns: ['date'])]
class Operation implements FamilyOwned
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
        private Account $account,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        private ?Category $category,
        /** In cents: negative for an expense */
        #[ORM\Column]
        private int $amount,
        #[ORM\Column(length: self::LABEL_MAX_LENGTH)]
        private string $label,
        #[ORM\Column(type: Types::DATE_IMMUTABLE)]
        private \DateTimeImmutable $date,
        /** Null once its author has left the family, and for the recorded recurring operations */
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        private ?User $createdBy,
        /** Set when recorded by a recurring operation; kept, unlinked, if it is deleted */
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        private ?RecurringOperation $recurring = null,
        /** Set on the two operations of a transfer between accounts, deleted with it */
        #[ORM\ManyToOne(inversedBy: 'operations')]
        #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
        private ?Transfer $transfer = null,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFamily(): Family
    {
        return $this->account->getFamily();
    }

    public function getAccount(): Account
    {
        return $this->account;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
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

    public function getRecurring(): ?RecurringOperation
    {
        return $this->recurring;
    }

    public function getTransfer(): ?Transfer
    {
        return $this->transfer;
    }

    public function edit(Account $account, ?Category $category, int $amount, string $label, \DateTimeImmutable $date): void
    {
        $this->account = $account;
        $this->category = $category;
        $this->amount = $amount;
        $this->label = $label;
        $this->date = $date;
    }
}
