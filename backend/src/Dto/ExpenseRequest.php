<?php

namespace App\Dto;

use App\Entity\PocketMoneyEntry;
use Symfony\Component\Validator\Constraints as Assert;

/** Expense made by a child with their pocket money; the messages talk to the child. */
final readonly class ExpenseRequest
{
    public function __construct(
        #[Assert\Range(min: 1, max: Amount::MAX, notInRangeMessage: 'Indique un montant positif.')]
        public int $amount = 0,
        #[Assert\NotBlank(message: 'Qu’as-tu acheté ?', normalizer: 'trim')]
        #[Assert\Length(max: PocketMoneyEntry::LABEL_MAX_LENGTH, normalizer: 'trim')]
        public string $label = '',
        #[Assert\NotBlank(message: 'Indique la date.')]
        #[Assert\Date(message: 'Cette date n’est pas valide.')]
        public string $date = '',
    ) {
    }
}
