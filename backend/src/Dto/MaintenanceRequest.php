<?php

namespace App\Dto;

use App\Entity\Maintenance;
use Symfony\Component\Validator\Constraints as Assert;

/** A maintenance planned, with its own budget. */
final readonly class MaintenanceRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Indiquez l’entretien prévu.', normalizer: 'trim')]
        #[Assert\Length(max: Maintenance::LABEL_MAX_LENGTH, normalizer: 'trim')]
        public string $label = '',
        #[Assert\NotBlank(message: 'Indiquez la date prévue.')]
        #[Assert\Date(message: 'Cette date n’est pas valide.')]
        public string $plannedOn = '',
        /** In cents */
        #[Assert\Range(min: 0, max: Amount::MAX, notInRangeMessage: 'Le budget doit être positif.')]
        public int $budget = 0,
        #[Assert\Range(min: 0, max: VehicleRequest::MAX_ODOMETER, notInRangeMessage: 'Ce kilométrage n’est pas valide.')]
        public ?int $plannedOdometer = null,
    ) {
    }
}
