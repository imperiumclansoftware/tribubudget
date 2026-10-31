<?php

namespace App\Entity;

use App\Enum\VehicleEnergy;
use App\Repository\VehicleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A vehicle of the family: its fill-ups are expenses of its own fuel category, which carries its
 * monthly budget; its maintenance is planned with a budget of its own (see Garage).
 */
#[ORM\Entity(repositoryClass: VehicleRepository::class)]
class Vehicle implements FamilyOwned
{
    public const NAME_MAX_LENGTH = 40;
    public const PLATE_MAX_LENGTH = 15;
    public const MAKE_MAX_LENGTH = 40;
    public const MODEL_MAX_LENGTH = 60;
    public const VERSION_MAX_LENGTH = 120;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Latest odometer reading, in km, and its date */
    #[ORM\Column(nullable: true)]
    private ?int $odometer = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $odometerOn = null;

    /** Where its fill-ups are recorded; null once the parents deleted that category */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Category $fuelCategory = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** Registration plate */
    #[ORM\Column(length: self::PLATE_MAX_LENGTH, nullable: true)]
    private ?string $plate = null;

    #[ORM\Column(length: self::MAKE_MAX_LENGTH, nullable: true)]
    private ?string $make = null;

    #[ORM\Column(length: self::MODEL_MAX_LENGTH, nullable: true)]
    private ?string $model = null;

    /** Commercial description, e.g. "CLIO (91ch)" */
    #[ORM\Column(length: self::VERSION_MAX_LENGTH, nullable: true)]
    private ?string $version = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $firstRegistration = null;

    /** Approved consumption, in hundredths of litre (or kWh) per 100 km: 5.83 L/100 km is 583 */
    #[ORM\Column(nullable: true)]
    private ?int $officialConsumption = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Family $family,
        /** What the family calls it: "Clio", "le scooter" */
        #[ORM\Column(length: self::NAME_MAX_LENGTH)]
        private string $name,
        #[ORM\Column(length: 15, enumType: VehicleEnergy::class)]
        private VehicleEnergy $energy = VehicleEnergy::Petrol,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFamily(): Family
    {
        return $this->family;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEnergy(): VehicleEnergy
    {
        return $this->energy;
    }

    public function getPlate(): ?string
    {
        return $this->plate;
    }

    public function getMake(): ?string
    {
        return $this->make;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function getFirstRegistration(): ?\DateTimeImmutable
    {
        return $this->firstRegistration;
    }

    public function getOfficialConsumption(): ?int
    {
        return $this->officialConsumption;
    }

    public function describe(string $name, VehicleEnergy $energy, ?string $plate, ?string $make, ?string $model, ?string $version, ?\DateTimeImmutable $firstRegistration, ?int $officialConsumption): void
    {
        $this->name = $name;
        $this->energy = $energy;
        $this->plate = null === $plate ? null : self::normalizePlate($plate);
        $this->make = $make;
        $this->model = $model;
        $this->version = $version;
        $this->firstRegistration = $firstRegistration;
        $this->officialConsumption = $officialConsumption;
    }

    public function getOdometer(): ?int
    {
        return $this->odometer;
    }

    public function getOdometerOn(): ?\DateTimeImmutable
    {
        return $this->odometerOn;
    }

    /**
     * Records an odometer reading: an odometer only goes up, so only a higher reading replaces the
     * latest (an old fill-up entered afterwards does not move it back).
     */
    public function readOdometer(int $km, \DateTimeImmutable $on): void
    {
        if (null === $this->odometer || $km > $this->odometer) {
            $this->odometer = $km;
            $this->odometerOn = $on;
        }
    }

    /** Corrects the latest reading, whatever it was (a mistake, a new odometer) */
    public function setOdometer(?int $km, ?\DateTimeImmutable $on): void
    {
        $this->odometer = $km;
        $this->odometerOn = null === $km ? null : $on;
    }

    public function getFuelCategory(): ?Category
    {
        return $this->fuelCategory;
    }

    public function setFuelCategory(?Category $category): void
    {
        $this->fuelCategory = $category;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** French plates in their usual form: "ab123cd" → "AB-123-CD"; other plates in capitals */
    public static function normalizePlate(string $plate): string
    {
        $compact = preg_replace('/[^A-Z0-9]/', '', mb_strtoupper($plate));
        if (preg_match('/^([A-Z]{2})(\d{3})([A-Z]{2})$/', $compact, $m)) {
            return $m[1].'-'.$m[2].'-'.$m[3];
        }

        return mb_strtoupper(trim(preg_replace('/\s+/', ' ', $plate)));
    }
}
