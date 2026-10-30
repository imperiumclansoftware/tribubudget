<?php

namespace App\Entity;

use App\Enum\Frequency;
use App\Repository\RecurringOperationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Income or expense coming back at fixed dates (rent, subscription, salary): an Operation is
 * recorded on each date by RecurringOperationRecorder.
 */
#[ORM\Entity(repositoryClass: RecurringOperationRepository::class)]
class RecurringOperation implements FamilyOwned
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Monthly, quarterly and yearly dates fall on this day, or on the last day of shorter months */
    #[ORM\Column(type: Types::SMALLINT)]
    private int $dayOfMonth;

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
        #[ORM\Column(length: Operation::LABEL_MAX_LENGTH)]
        private string $label,
        #[ORM\Column(length: 10, enumType: Frequency::class)]
        private Frequency $frequency,
        #[ORM\Column(type: Types::DATE_IMMUTABLE)]
        private \DateTimeImmutable $nextOn,
        /** Last possible date (end of a loan…); null to repeat forever */
        #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
        private ?\DateTimeImmutable $endsOn = null,
        #[ORM\Column]
        private bool $active = true,
    ) {
        $this->dayOfMonth = (int) $nextOn->format('j');
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

    public function getFrequency(): Frequency
    {
        return $this->frequency;
    }

    public function getNextOn(): \DateTimeImmutable
    {
        return $this->nextOn;
    }

    public function getEndsOn(): ?\DateTimeImmutable
    {
        return $this->endsOn;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function edit(Account $account, ?Category $category, int $amount, string $label, Frequency $frequency, \DateTimeImmutable $nextOn, ?\DateTimeImmutable $endsOn, bool $active): void
    {
        $this->account = $account;
        $this->category = $category;
        $this->amount = $amount;
        $this->label = $label;
        $this->frequency = $frequency;
        $this->nextOn = $nextOn;
        $this->dayOfMonth = (int) $nextOn->format('j');
        $this->endsOn = $endsOn;
        $this->active = $active;
    }

    /** Past its end date: nothing more to record */
    public function isFinished(): bool
    {
        return null !== $this->endsOn && $this->nextOn > $this->endsOn;
    }

    public function isDue(\DateTimeImmutable $today): bool
    {
        return $this->active && !$this->isFinished() && $this->nextOn <= $today;
    }

    /** Records the operation of the current date and moves on to the next one. */
    /**
     * Dates still to be recorded before $end (excluded), from the next one: none once paused or
     * past its end date.
     *
     * @return list<\DateTimeImmutable>
     */
    public function datesBefore(\DateTimeImmutable $end): array
    {
        $dates = [];
        for ($date = $this->nextOn; $this->active && $date < $end && (null === $this->endsOn || $date <= $this->endsOn); $date = $this->frequency->next($date, $this->dayOfMonth)) {
            $dates[] = $date;
        }

        return $dates;
    }

    public function occur(): Operation
    {
        $operation = new Operation($this->account, $this->category, $this->amount, $this->label, $this->nextOn, null, $this);
        $this->nextOn = $this->frequency->next($this->nextOn, $this->dayOfMonth);

        return $operation;
    }
}
