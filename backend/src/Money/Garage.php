<?php

namespace App\Money;

use App\Entity\Account;
use App\Entity\Category;
use App\Entity\Family;
use App\Entity\Maintenance;
use App\Entity\Operation;
use App\Entity\Refuel;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Enum\CategoryKind;
use App\Repository\CategoryRepository;
use App\Repository\OperationRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Money spent on the vehicles. Each vehicle has its fuel category, "Carburant · Clio", whose
 * monthly budget is the vehicle's fuel budget; the maintenance done is recorded in one category for
 * every vehicle, its budget being the one of each maintenance.
 */
final class Garage
{
    private const FUEL_PREFIX = 'Carburant · ';
    private const FUEL_COLOR = '#e76f51';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CategoryRepository $categories,
        private readonly OperationRepository $operations,
    ) {
    }

    /** The vehicle's fuel category, created if missing, renamed with the vehicle, with its monthly budget. */
    public function fuel(Vehicle $vehicle, ?int $monthlyBudget, ?string $previousName = null): Category
    {
        $category = $vehicle->getFuelCategory();
        $name = self::fuelName($vehicle->getName());
        if (null === $category) {
            $category = new Category($vehicle->getFamily(), $name, CategoryKind::Expense, self::FUEL_COLOR);
            $this->em->persist($category);
            $vehicle->setFuelCategory($category);
        } elseif (null !== $previousName && $category->getName() === self::fuelName($previousName)) {
            // Only the name the application gave it: a name chosen by the parents stays
            $category->edit($name, CategoryKind::Expense, $category->getColor());
        }
        $category->setMonthlyBudget($monthlyBudget);

        return $category;
    }

    /** A vehicle leaves: its fuel budget no longer counts, its category stays only for its expenses. */
    public function retire(Vehicle $vehicle): void
    {
        $category = $vehicle->getFuelCategory();
        if (null === $category) {
            return;
        }
        if (0 === $this->operations->count(['category' => $category])) {
            $vehicle->setFuelCategory(null);
            $this->em->remove($category);
        } else {
            $category->setMonthlyBudget(null);
        }
    }

    public function refuel(Vehicle $vehicle, Account $account, int $amount, int $volume, ?int $odometer, \DateTimeImmutable $date, User $by): Refuel
    {
        $operation = new Operation($account, $this->fuelCategory($vehicle), -$amount, self::label('Plein', $vehicle), $date, $by);
        $refuel = new Refuel($vehicle, $operation, $volume, $odometer);
        $this->em->persist($operation);
        $this->em->persist($refuel);
        $this->read($vehicle, $odometer, $date);

        return $refuel;
    }

    public function editRefuel(Refuel $refuel, Account $account, int $amount, int $volume, ?int $odometer, \DateTimeImmutable $date): void
    {
        $operation = $refuel->getOperation();
        $operation->edit($account, $operation->getCategory(), -$amount, $operation->getLabel(), $date);
        $refuel->edit($volume, $odometer);
        $this->read($refuel->getVehicle(), $odometer, $date);
    }

    /** Records the actual cost of a maintenance, in the vehicle maintenance category. */
    public function complete(Maintenance $maintenance, Account $account, int $amount, \DateTimeImmutable $date, ?int $odometer, User $by): void
    {
        $vehicle = $maintenance->getVehicle();
        $operation = new Operation($account, $this->maintenanceCategory($vehicle->getFamily()), -$amount, self::label($maintenance->getLabel(), $vehicle), $date, $by);
        $this->em->persist($operation);
        $maintenance->done($operation);
        $this->read($vehicle, $odometer, $date);
    }

    /** Back to due: its expense is deleted. */
    public function reopen(Maintenance $maintenance): void
    {
        $operation = $maintenance->getOperation();
        $maintenance->done(null);
        if (null !== $operation) {
            $this->em->remove($operation);
        }
    }

    /** Recreated if the parents deleted it */
    private function fuelCategory(Vehicle $vehicle): Category
    {
        return $vehicle->getFuelCategory() ?? $this->fuel($vehicle, null);
    }

    /** Recreated if the parents deleted it */
    private function maintenanceCategory(Family $family): Category
    {
        $category = $this->categories->withCode($family, Category::VEHICLE_MAINTENANCE);
        if (null === $category) {
            $category = new Category($family, 'Entretien véhicules', CategoryKind::Expense, '#6d597a', Category::VEHICLE_MAINTENANCE);
            $this->em->persist($category);
        }

        return $category;
    }

    private function read(Vehicle $vehicle, ?int $odometer, \DateTimeImmutable $date): void
    {
        if (null !== $odometer) {
            $vehicle->readOdometer($odometer, $date);
        }
    }

    private static function fuelName(string $vehicleName): string
    {
        return mb_substr(self::FUEL_PREFIX.$vehicleName, 0, Category::NAME_MAX_LENGTH);
    }

    private static function label(string $what, Vehicle $vehicle): string
    {
        return mb_substr($what.' · '.$vehicle->getName(), 0, Operation::LABEL_MAX_LENGTH);
    }
}
