<?php

namespace App\Dto;

use App\Enum\Frequency;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class AllowanceRequest
{
    public function __construct(
        #[Assert\Range(min: 1, max: Amount::MAX, notInRangeMessage: 'Indiquez un montant positif.')]
        public int $amount = 0,
        #[Assert\Choice(choices: [Frequency::Weekly, Frequency::Monthly], message: 'L’argent de poche est versé chaque semaine ou chaque mois.')]
        public Frequency $frequency = Frequency::Weekly,
        #[Assert\NotBlank(message: 'Indiquez la date du prochain versement.')]
        #[Assert\Date(message: 'Cette date n’est pas valide.')]
        public string $nextPaymentOn = '',
        /** Household account the allowance is taken from */
        public ?int $accountId = null,
        public bool $active = true,
    ) {
    }
}
