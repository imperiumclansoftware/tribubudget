<?php

namespace App\Entity;

use App\Enum\AccountType;
use App\Repository\AccountRepository;
use Doctrine\ORM\Mapping as ORM;

/** Bank account or cash of the household. */
#[ORM\Entity(repositoryClass: AccountRepository::class)]
class Account implements FamilyOwned
{
    public const NAME_MAX_LENGTH = 60;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Savings accounts: amount kept aside, in cents. Only what exceeds it is allocated to the
     * projects (see SavingsAllocator); null for none.
     */
    #[ORM\Column(nullable: true)]
    private ?int $minimumBalance = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Family $family,
        #[ORM\Column(length: self::NAME_MAX_LENGTH)]
        private string $name,
        #[ORM\Column(length: 10, enumType: AccountType::class)]
        private AccountType $type,
        /** Balance before the first recorded operation, in cents */
        #[ORM\Column]
        private int $initialBalance = 0,
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

    public function getType(): AccountType
    {
        return $this->type;
    }

    public function getInitialBalance(): int
    {
        return $this->initialBalance;
    }

    public function isSavings(): bool
    {
        return AccountType::Savings === $this->type;
    }

    public function getMinimumBalance(): ?int
    {
        return $this->minimumBalance;
    }

    /** Ignored, and cleared, for the accounts that are not savings accounts */
    public function setMinimumBalance(?int $minimumBalance): void
    {
        $this->minimumBalance = $this->isSavings() ? $minimumBalance : null;
    }

    public function edit(string $name, AccountType $type, int $initialBalance): void
    {
        $this->name = $name;
        $this->type = $type;
        $this->initialBalance = $initialBalance;
        $this->setMinimumBalance($this->minimumBalance);
    }
}
