<?php

namespace App\Entity;

use App\Repository\MaintenanceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Maintenance of a vehicle (service, tyres…), planned with a budget of its own. Once done, its
 * actual cost is an expense of the vehicle maintenance category (see Garage).
 */
#[ORM\Entity(repositoryClass: MaintenanceRepository::class)]
class Maintenance implements FamilyOwned
{
    public const LABEL_MAX_LENGTH = 80;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** The expense once done; deleting it from the operations makes the maintenance due again */
    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: true, unique: true, onDelete: 'SET NULL')]
    private ?Operation $operation = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Vehicle $vehicle,
        #[ORM\Column(length: self::LABEL_MAX_LENGTH)]
        private string $label,
        #[ORM\Column(type: Types::DATE_IMMUTABLE)]
        private \DateTimeImmutable $plannedOn,
        /** Expected cost, in cents */
        #[ORM\Column]
        private int $budget,
        /** Due at this odometer reading too, in km */
        #[ORM\Column(nullable: true)]
        private ?int $plannedOdometer = null,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFamily(): Family
    {
        return $this->vehicle->getFamily();
    }

    public function getVehicle(): Vehicle
    {
        return $this->vehicle;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getPlannedOn(): \DateTimeImmutable
    {
        return $this->plannedOn;
    }

    public function getBudget(): int
    {
        return $this->budget;
    }

    public function getPlannedOdometer(): ?int
    {
        return $this->plannedOdometer;
    }

    public function getOperation(): ?Operation
    {
        return $this->operation;
    }

    public function isDone(): bool
    {
        return null !== $this->operation;
    }

    public function plan(string $label, \DateTimeImmutable $plannedOn, int $budget, ?int $plannedOdometer): void
    {
        $this->label = $label;
        $this->plannedOn = $plannedOn;
        $this->budget = $budget;
        $this->plannedOdometer = $plannedOdometer;
    }

    public function done(?Operation $operation): void
    {
        $this->operation = $operation;
    }
}
