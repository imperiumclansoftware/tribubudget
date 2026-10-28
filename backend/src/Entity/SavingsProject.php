<?php

namespace App\Entity;

use App\Repository\SavingsProjectRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Something the family saves up for (holidays, a bike…) on a savings account. The projects of an
 * account are funded in the order of their priority (see SavingsAllocator).
 */
#[ORM\Entity(repositoryClass: SavingsProjectRepository::class)]
class SavingsProject implements FamilyOwned
{
    public const NAME_MAX_LENGTH = 80;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Set once carried out: the project no longer takes any money */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $achievedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Account $account,
        #[ORM\Column(length: self::NAME_MAX_LENGTH)]
        private string $name,
        /** In cents */
        #[ORM\Column]
        private int $target,
        /** Rank among the projects of the account: 1 is funded first */
        #[ORM\Column]
        private int $priority,
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

    public function getName(): string
    {
        return $this->name;
    }

    public function getTarget(): int
    {
        return $this->target;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function getAchievedAt(): ?\DateTimeImmutable
    {
        return $this->achievedAt;
    }

    public function isAchieved(): bool
    {
        return null !== $this->achievedAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function edit(string $name, int $target): void
    {
        $this->name = $name;
        $this->target = $target;
    }

    /** Moves the project to another account, as its last priority. */
    public function moveTo(Account $account, int $priority): void
    {
        $this->account = $account;
        $this->priority = $priority;
    }

    public function prioritize(int $priority): void
    {
        $this->priority = $priority;
    }

    public function achieve(): void
    {
        $this->achievedAt = new \DateTimeImmutable();
    }
}
