<?php

namespace App\Dto;

use App\Entity\SavingsProject;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class SavingsProjectRequest
{
    public function __construct(
        #[Assert\NotNull(message: 'Choisissez un compte d’épargne.')]
        public ?int $accountId = null,
        #[Assert\NotBlank(message: 'Donnez un nom au projet.', normalizer: 'trim')]
        #[Assert\Length(max: SavingsProject::NAME_MAX_LENGTH, normalizer: 'trim')]
        public string $name = '',
        /** In cents */
        #[Assert\Range(min: 1, max: Amount::MAX, notInRangeMessage: 'Indiquez un montant positif.')]
        public int $target = 0,
    ) {
    }
}
