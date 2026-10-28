<?php

namespace App\Entity;

use App\Enum\FamilyRole;
use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Member of a family: a parent (every right) or a child (their own pocket money and requests).
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[UniqueEntity('username')]
class User implements UserInterface, PasswordAuthenticatedUserInterface, FamilyOwned
{
    public const USERNAME_MAX_LENGTH = 40;
    public const DISPLAY_NAME_MAX_LENGTH = 60;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private string $password = '';

    /** Advances granted to a child and not yet repaid by their allowances, in cents */
    #[ORM\Column(options: ['default' => 0])]
    private int $advanceOwed = 0;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Family $family,
        /** Stored in lower case: signing in ignores the case */
        #[ORM\Column(length: self::USERNAME_MAX_LENGTH, unique: true)]
        private string $username,
        #[ORM\Column(length: self::DISPLAY_NAME_MAX_LENGTH)]
        private string $displayName,
        #[ORM\Column(length: 10, enumType: FamilyRole::class)]
        private FamilyRole $role,
    ) {
        $this->username = mb_strtolower($username);
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFamily(): Family
    {
        return $this->family;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function rename(string $displayName): void
    {
        $this->displayName = $displayName;
    }

    public function getRole(): FamilyRole
    {
        return $this->role;
    }

    public function isParent(): bool
    {
        return FamilyRole::Parent === $this->role;
    }

    public function isChild(): bool
    {
        return FamilyRole::Child === $this->role;
    }

    public function getRoles(): array
    {
        return ['ROLE_USER', $this->role->securityRole()];
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $hashedPassword): void
    {
        $this->password = $hashedPassword;
    }

    public function getAdvanceOwed(): int
    {
        return $this->advanceOwed;
    }

    public function owe(int $amount): void
    {
        $this->advanceOwed += $amount;
    }

    /** Repays the advances with up to $available cents; returns the amount repaid. */
    public function repay(int $available): int
    {
        $repaid = min($this->advanceOwed, max(0, $available));
        $this->advanceOwed -= $repaid;

        return $repaid;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
