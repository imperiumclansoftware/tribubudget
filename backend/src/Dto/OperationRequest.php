<?php

namespace App\Dto;

use App\Entity\Operation;
use App\Enum\CategoryKind;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class OperationRequest
{
    public function __construct(
        #[Assert\NotNull(message: 'Choisissez un compte.')]
        public ?int $accountId = null,
        public ?int $categoryId = null,
        /** Income or expense: the amount itself is always positive */
        public CategoryKind $type = CategoryKind::Expense,
        #[Assert\Range(min: 1, max: Amount::MAX, notInRangeMessage: 'Indiquez un montant positif.')]
        public int $amount = 0,
        #[Assert\NotBlank(message: 'Indiquez un libellé.', normalizer: 'trim')]
        #[Assert\Length(max: Operation::LABEL_MAX_LENGTH, normalizer: 'trim')]
        public string $label = '',
        #[Assert\NotBlank(message: 'Indiquez la date.')]
        #[Assert\Date(message: 'Cette date n’est pas valide.')]
        public string $date = '',
    ) {
    }

    /** Signed amount, as stored */
    public function signedAmount(): int
    {
        return CategoryKind::Expense === $this->type ? -$this->amount : $this->amount;
    }
}
