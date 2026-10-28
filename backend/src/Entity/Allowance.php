<?php

namespace App\Entity;

use App\Enum\Frequency;
use App\Repository\AllowanceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Pocket money paid to a child every week or month (see AllowancePayer). */
#[ORM\Entity(repositoryClass: AllowanceRepository::class)]
class Allowance implements FamilyOwned
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Monthly payments are made on this day, or on the last day of shorter months */
    #[ORM\Column(type: Types::SMALLINT)]
    private int $dayOfMonth;

    public function __construct(
        #[ORM\OneToOne]
        #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
        private User $child,
        /** In cents */
        #[ORM\Column]
        private int $amount,
        #[ORM\Column(length: 10, enumType: Frequency::class)]
        private Frequency $frequency,
        #[ORM\Column(type: Types::DATE_IMMUTABLE)]
        private \DateTimeImmutable $nextPaymentOn,
        /** Household account the allowance is taken from; null to only credit the child */
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        private ?Account $account = null,
        #[ORM\Column]
        private bool $active = true,
    ) {
        $this->dayOfMonth = (int) $nextPaymentOn->format('j');
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

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getFrequency(): Frequency
    {
        return $this->frequency;
    }

    public function getNextPaymentOn(): \DateTimeImmutable
    {
        return $this->nextPaymentOn;
    }

    public function getAccount(): ?Account
    {
        return $this->account;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function edit(int $amount, Frequency $frequency, \DateTimeImmutable $nextPaymentOn, ?Account $account, bool $active): void
    {
        $this->amount = $amount;
        $this->frequency = $frequency;
        $this->nextPaymentOn = $nextPaymentOn;
        $this->dayOfMonth = (int) $nextPaymentOn->format('j');
        $this->account = $account;
        $this->active = $active;
    }

    public function isDue(\DateTimeImmutable $today): bool
    {
        return $this->active && $this->nextPaymentOn <= $today;
    }

    /** Moves on to the next payment date, once the current one is paid. */
    public function advance(): void
    {
        $this->nextPaymentOn = $this->frequency->next($this->nextPaymentOn, $this->dayOfMonth);
    }
}
