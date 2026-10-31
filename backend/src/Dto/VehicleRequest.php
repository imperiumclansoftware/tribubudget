<?php

namespace App\Dto;

use App\Entity\Vehicle;
use App\Enum\VehicleEnergy;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class VehicleRequest
{
    /** Odometer readings, in km, up to this */
    public const MAX_ODOMETER = 2_000_000;

    public function __construct(
        #[Assert\NotBlank(message: 'Donnez un nom au véhicule.', normalizer: 'trim')]
        #[Assert\Length(max: Vehicle::NAME_MAX_LENGTH, normalizer: 'trim')]
        public string $name = '',
        public VehicleEnergy $energy = VehicleEnergy::Petrol,
        #[Assert\Length(max: Vehicle::PLATE_MAX_LENGTH, normalizer: 'trim')]
        public ?string $plate = null,
        #[Assert\Length(max: Vehicle::MAKE_MAX_LENGTH, normalizer: 'trim')]
        public ?string $make = null,
        #[Assert\Length(max: Vehicle::MODEL_MAX_LENGTH, normalizer: 'trim')]
        public ?string $model = null,
        #[Assert\Length(max: Vehicle::VERSION_MAX_LENGTH, normalizer: 'trim')]
        public ?string $version = null,
        #[Assert\Date(message: 'Cette date n’est pas valide.')]
        public ?string $firstRegistration = null,
        /** In hundredths of litre (or kWh) per 100 km */
        #[Assert\Range(min: 1, max: 10_000, notInRangeMessage: 'Cette consommation n’est pas valide.')]
        public ?int $officialConsumption = null,
        /** Current odometer reading, in km */
        #[Assert\Range(min: 0, max: self::MAX_ODOMETER, notInRangeMessage: 'Ce kilométrage n’est pas valide.')]
        public ?int $odometer = null,
        /** Monthly fuel budget, in cents; null for none */
        #[Assert\Range(min: 1, max: Amount::MAX, notInRangeMessage: 'Le budget carburant doit être positif.')]
        public ?int $fuelBudget = null,
    ) {
    }

    /** Trimmed; null when empty */
    public static function optional(?string $text): ?string
    {
        $text = Text::clean($text ?? '');

        return '' === $text ? null : $text;
    }
}
