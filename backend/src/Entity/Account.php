<?php

namespace App\Entity;

use App\Enum\AccountType;
use App\Repository\AccountRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Bank account or cash: of the household, or of a teenager (their owner), which stays outside the
 * household budget and is overseen by the parents.
 */
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
        /** The teenager holding the account; null for the household's accounts */
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        private ?User $owner = null,
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

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function isHousehold(): bool
    {
        return null === $this->owner;
    }

    public function setOwner(?User $owner): void
    {
        $this->owner = $owner;
    }

    public function rename(string $name): void
    {
        $this->name = $name;
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
