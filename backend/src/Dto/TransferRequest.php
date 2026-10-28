<?php

namespace App\Dto;

use App\Entity\Operation;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class TransferRequest
{
    public function __construct(
        #[Assert\NotNull(message: 'Choisissez le compte à débiter.')]
        public ?int $fromAccountId = null,
        #[Assert\NotNull(message: 'Choisissez le compte à créditer.')]
        #[Assert\NotEqualTo(propertyPath: 'fromAccountId', message: 'Choisissez deux comptes différents.')]
        public ?int $toAccountId = null,
        #[Assert\Range(min: 1, max: Amount::MAX, notInRangeMessage: 'Indiquez un montant positif.')]
        public int $amount = 0,
        #[Assert\Length(max: Operation::LABEL_MAX_LENGTH, normalizer: 'trim')]
        public string $label = '',
        #[Assert\NotBlank(message: 'Indiquez la date.')]
        #[Assert\Date(message: 'Cette date n’est pas valide.')]
        public string $date = '',
    ) {
    }

    /** "Virement" when left empty */
    public function cleanLabel(): string
    {
        $label = Text::clean($this->label);

        return '' === $label ? 'Virement' : $label;
    }
}
