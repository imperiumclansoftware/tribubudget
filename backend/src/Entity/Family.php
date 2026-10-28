<?php

namespace App\Entity;

use App\Repository\FamilyRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FamilyRepository::class)]
class Family
{
    public const NAME_MAX_LENGTH = 80;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\Column(length: self::NAME_MAX_LENGTH)]
        private string $name,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function rename(string $name): void
    {
        $this->name = $name;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
