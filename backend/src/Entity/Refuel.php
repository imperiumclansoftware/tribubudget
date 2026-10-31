<?php

namespace App\Entity;

use App\Repository\RefuelRepository;
use Doctrine\ORM\Mapping as ORM;

/** A fill-up of a vehicle: its expense is an operation of the vehicle's fuel category. */
#[ORM\Entity(repositoryClass: RefuelRepository::class)]
class Refuel implements FamilyOwned
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Vehicle $vehicle,
        /** The expense; deleting it from the operations deletes the fill-up */
        #[ORM\OneToOne]
        #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
        private Operation $operation,
        /** In hundredths of litre (or of kWh for an electric vehicle) */
        #[ORM\Column]
        private int $volume,
        /** Odometer at the fill-up, in km */
        #[ORM\Column(nullable: true)]
        private ?int $odometer = null,
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

    public function getOperation(): Operation
    {
        return $this->operation;
    }

    public function getVolume(): int
    {
        return $this->volume;
    }

    public function getOdometer(): ?int
    {
        return $this->odometer;
    }

    public function edit(int $volume, ?int $odometer): void
    {
        $this->volume = $volume;
        $this->odometer = $odometer;
    }
}
