<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** The actual cost of a maintenance, once done. */
final readonly class MaintenanceDoneRequest
{
    public function __construct(
        #[Assert\NotNull(message: 'Choisissez le compte qui paie.')]
        public ?int $accountId = null,
        /** In cents */
        #[Assert\Range(min: 1, max: Amount::MAX, notInRangeMessage: 'Indiquez le montant payé.')]
        public int $amount = 0,
        #[Assert\NotBlank(message: 'Indiquez la date.')]
        #[Assert\Date(message: 'Cette date n’est pas valide.')]
        public string $date = '',
        #[Assert\Range(min: 0, max: VehicleRequest::MAX_ODOMETER, notInRangeMessage: 'Ce kilométrage n’est pas valide.')]
        public ?int $odometer = null,
    ) {
    }
}
