<?php

namespace App\Dto;

use App\Entity\PocketMoneyEntry;
use Symfony\Component\Validator\Constraints as Assert;

/** Credit (positive) or debit (negative) of a child's pocket money by a parent. */
final readonly class AdjustmentRequest
{
    public function __construct(
        #[Assert\NotEqualTo(0, message: 'Indiquez un montant.')]
        #[Assert\Range(min: -Amount::MAX, max: Amount::MAX)]
        public int $amount = 0,
        #[Assert\NotBlank(message: 'Indiquez un libellé.', normalizer: 'trim')]
        #[Assert\Length(max: PocketMoneyEntry::LABEL_MAX_LENGTH, normalizer: 'trim')]
        public string $label = '',
    ) {
    }
}
