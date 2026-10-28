<?php

namespace App\Entity;

use App\Repository\TransferRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Money moved from one account of the family to another: recorded as two operations, out of the
 * first account and into the second, which are neither an income nor an expense of the household.
 */
#[ORM\Entity(repositoryClass: TransferRepository::class)]
class Transfer implements FamilyOwned
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, Operation> the two legs: out of $from, into $to */
    #[ORM\OneToMany(targetEntity: Operation::class, mappedBy: 'transfer', cascade: ['persist'])]
    private Collection $operations;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Account $from,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Account $to,
        /** In cents, positive */
        #[ORM\Column]
        private int $amount,
        #[ORM\Column(length: Operation::LABEL_MAX_LENGTH)]
        private string $label,
        #[ORM\Column(type: Types::DATE_IMMUTABLE)]
        private \DateTimeImmutable $date,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        private ?User $createdBy,
    ) {
        $this->createdAt = new \DateTimeImmutable();
        $this->operations = new ArrayCollection([
            new Operation($from, null, -$amount, $label, $date, $createdBy, transfer: $this),
            new Operation($to, null, $amount, $label, $date, $createdBy, transfer: $this),
        ]);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFamily(): Family
    {
        return $this->from->getFamily();
    }

    public function getFrom(): Account
    {
        return $this->from;
    }

    public function getTo(): Account
    {
        return $this->to;
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

    /** The other account of the transfer, seen from one of its operations */
    public function counterpartOf(Operation $operation): Account
    {
        return $operation->getAmount() < 0 ? $this->to : $this->from;
    }

    public function edit(Account $from, Account $to, int $amount, string $label, \DateTimeImmutable $date): void
    {
        $this->from = $from;
        $this->to = $to;
        $this->amount = $amount;
        $this->label = $label;
        $this->date = $date;
        foreach ($this->operations as $operation) {
            $out = $operation->getAmount() < 0;
            $operation->edit($out ? $from : $to, null, $out ? -$amount : $amount, $label, $date);
        }
    }
}
