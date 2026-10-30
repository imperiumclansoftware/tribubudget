<?php

namespace App\Entity;

use App\Enum\RequestKind;
use App\Enum\RequestStatus;
use App\Repository\MoneyRequestRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Request of a child or a teenager to the parents: an advance on the pocket money, a purchase, or
 * (teenagers) the reimbursement of an expense recorded on their account.
 */
#[ORM\Entity(repositoryClass: MoneyRequestRepository::class)]
class MoneyRequest implements FamilyOwned
{
    public const REASON_MAX_LENGTH = 200;
    public const ANSWER_MAX_LENGTH = 200;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 10, enumType: RequestStatus::class)]
    private RequestStatus $status = RequestStatus::Pending;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $decidedBy = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $decidedAt = null;

    /** Word of the parent who answered */
    #[ORM\Column(length: self::ANSWER_MAX_LENGTH, nullable: true)]
    private ?string $answer = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $child,
        #[ORM\Column(length: 15, enumType: RequestKind::class)]
        private RequestKind $kind,
        /** In cents */
        #[ORM\Column]
        private int $amount,
        #[ORM\Column(length: self::REASON_MAX_LENGTH)]
        private string $reason,
        /** Reimbursements: the teenager's expense; null once deleted */
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        private ?Operation $operation = null,
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

    public function getKind(): RequestKind
    {
        return $this->kind;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getOperation(): ?Operation
    {
        return $this->operation;
    }

    public function getStatus(): RequestStatus
    {
        return $this->status;
    }

    public function isPending(): bool
    {
        return RequestStatus::Pending === $this->status;
    }

    public function isApproved(): bool
    {
        return RequestStatus::Approved === $this->status;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getDecidedBy(): ?User
    {
        return $this->decidedBy;
    }

    public function getDecidedAt(): ?\DateTimeImmutable
    {
        return $this->decidedAt;
    }

    public function getAnswer(): ?string
    {
        return $this->answer;
    }

    public function decide(User $parent, bool $approved, ?string $answer): void
    {
        $this->status = $approved ? RequestStatus::Approved : RequestStatus::Rejected;
        $this->decidedBy = $parent;
        $this->decidedAt = new \DateTimeImmutable();
        $this->answer = $answer;
    }

    public function cancel(): void
    {
        $this->status = RequestStatus::Cancelled;
    }
}
