<?php

namespace App\Tests\Api;

use App\Tests\ApiTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class VehicleTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    private string $parent;
    private int $accountId;

    protected function setUp(): void
    {
        parent::setUp();
        self::mockTime('2026-10-20 09:00:00');
        $this->parent = $this->registerFamily();
        $this->accountId = $this->firstAccountId($this->parent);
    }

    public function testAVehicleHasItsFuelBudget(): void
    {
        $clio = $this->vehicle(['name' => 'Clio', 'plate' => 'ab 123 cd', 'make' => 'RENAULT', 'model' => 'CLIO', 'version' => 'CLIO (91ch)', 'officialConsumption' => 583, 'odometer' => 45000, 'fuelBudget' => 15000]);
        self::assertSame(['AB-123-CD', 'petrol', 45000, '2026-10-20'], [$clio['plate'], $clio['energy'], $clio['odometer'], $clio['odometerOn']]);
        self::assertSame(['budget' => 15000, 'spent' => 0], array_diff_key($clio['fuel'], ['categoryId' => 0]));

        $category = array_column($this->api('GET', '/api/categories', $this->parent), null, 'id')[$clio['fuel']['categoryId']];
        self::assertSame(['Carburant · Clio', 15000], [$category['name'], $category['monthlyBudget']]);

        // Renamed with the vehicle, budget changed
        $this->api('PUT', '/api/vehicles/'.$clio['id'], $this->parent, ['name' => 'Clio de Claire', 'odometer' => 45000, 'fuelBudget' => 12000]);
        $category = array_column($this->api('GET', '/api/categories', $this->parent), null, 'id')[$clio['fuel']['categoryId']];
        self::assertSame(['Carburant · Clio de Claire', 12000], [$category['name'], $category['monthlyBudget']]);
    }

    public function testFillUpsAreFuelExpensesAndGiveTheConsumption(): void
    {
        $clio = $this->vehicle(['name' => 'Clio', 'odometer' => 45000, 'fuelBudget' => 15000]);
        $this->refuel($clio['id'], 7000, 4000, 45100, '2026-10-02');
        $this->refuel($clio['id'], 6000, 3500, 45700, '2026-10-12');
        $vehicle = $this->refuel($clio['id'], 5400, 3100, 46200, '2026-10-19');

        // 66 L after the first fill-up, over 1100 km
        self::assertSame(600, $vehicle['consumption']);
        self::assertSame([46200, '2026-10-19'], [$vehicle['odometer'], $vehicle['odometerOn']]);
        self::assertSame(18400, $vehicle['fuel']['spent']);
        self::assertSame([5400, 3100, 46200], [$vehicle['refuels'][0]['amount'], $vehicle['refuels'][0]['volume'], $vehicle['refuels'][0]['odometer']]);

        $categories = array_column($this->api('GET', '/api/dashboard?month=2026-10', $this->parent)['categories'], null, 'name');
        self::assertSame(['spent' => 18400, 'budget' => 15000], array_intersect_key($categories['Carburant · Clio'], ['spent' => 0, 'budget' => 0]));

        // Deleting the expense from the operations deletes the fill-up
        $this->api('DELETE', '/api/operations/'.$vehicle['refuels'][0]['operationId'], $this->parent);
        self::assertCount(2, $this->api('GET', '/api/vehicles/'.$clio['id'], $this->parent)['refuels']);
        // An old fill-up entered afterwards does not move the odometer back
        $vehicle = $this->refuel($clio['id'], 1000, 500, 45050, '2026-10-01');
        self::assertSame(46200, $vehicle['odometer']);
    }

    public function testAMaintenanceHasItsOwnBudgetAndCountsInTheForecastUntilDone(): void
    {
        $clio = $this->vehicle(['name' => 'Clio', 'odometer' => 45000]);
        $vehicle = $this->api('POST', '/api/vehicles/'.$clio['id'].'/maintenances', $this->parent, ['label' => 'Révision', 'plannedOn' => '2026-10-28', 'budget' => 25000, 'plannedOdometer' => 60000]);
        self::assertResponseStatusCodeSame(201);
        $this->api('POST', '/api/vehicles/'.$clio['id'].'/maintenances', $this->parent, ['label' => 'Pneus', 'plannedOn' => '2027-03-01', 'budget' => 40000]);
        self::assertSame('Révision', $vehicle['nextMaintenance']['label']);
        // Only the one of this month
        self::assertSame(25000, $this->forecast()['maintenance']);

        $done = $this->api('PUT', '/api/maintenances/'.$vehicle['maintenances'][0]['id'].'/done', $this->parent, ['accountId' => $this->accountId, 'amount' => 27350, 'date' => '2026-10-20', 'odometer' => 46000]);
        self::assertResponseIsSuccessful();
        self::assertSame(['Pneus', 46000], [$done['nextMaintenance']['label'], $done['odometer']]);
        $revision = array_column($done['maintenances'], null, 'label')['Révision'];
        self::assertSame([27350, '2026-10-20'], [$revision['done']['amount'], $revision['done']['date']]);

        // Done: its actual cost is an expense of the month, no longer a budget to come
        $forecast = $this->forecast();
        self::assertSame([0, 27350], [$forecast['maintenance'], $forecast['other']]);
        [$expense] = $this->api('GET', '/api/operations?month=2026-10', $this->parent);
        self::assertSame(['Révision · Clio', -27350], [$expense['label'], $expense['amount']]);

        // Not done after all: the expense goes, the budget comes back
        $this->api('DELETE', '/api/maintenances/'.$revision['id'].'/done', $this->parent);
        self::assertSame([], $this->api('GET', '/api/operations?month=2026-10', $this->parent));
        self::assertSame(25000, $this->forecast()['maintenance']);
    }

    public function testOverdueMaintenanceStillCounts(): void
    {
        $clio = $this->vehicle(['name' => 'Clio']);
        $this->api('POST', '/api/vehicles/'.$clio['id'].'/maintenances', $this->parent, ['label' => 'Contrôle technique', 'plannedOn' => '2026-09-15', 'budget' => 8000]);

        self::assertSame(8000, $this->forecast()['maintenance']);
    }

    public function testDeletingAVehicleKeepsItsExpensesButNotItsBudget(): void
    {
        $clio = $this->vehicle(['name' => 'Clio', 'fuelBudget' => 15000]);
        $this->refuel($clio['id'], 7000, 4000, null, '2026-10-02');
        $zoe = $this->vehicle(['name' => 'Zoé', 'fuelBudget' => 5000]);

        $this->api('DELETE', '/api/vehicles/'.$clio['id'], $this->parent);
        self::assertResponseStatusCodeSame(204);
        $this->api('DELETE', '/api/vehicles/'.$zoe['id'], $this->parent);

        self::assertSame([-7000], array_column($this->api('GET', '/api/operations?month=2026-10', $this->parent), 'amount'));
        // The Clio's category stays for its expense, without budget; the Zoé's, empty, is gone
        $fuel = array_values(array_filter($this->api('GET', '/api/categories', $this->parent), static fn ($c) => str_starts_with($c['name'], 'Carburant')));
        self::assertSame([['Carburant · Clio', null]], array_map(static fn ($c) => [$c['name'], $c['monthlyBudget']], $fuel));
    }

    public function testOnlyTheParentsAndOnlyTheHouseholdAccounts(): void
    {
        $clio = $this->vehicle(['name' => 'Clio']);
        [, $hugo] = $this->addTeen($this->parent);
        $this->api('GET', '/api/vehicles', $hugo);
        self::assertResponseStatusCodeSame(403);

        [$hugoId] = [$this->api('GET', '/api/teens', $this->parent)[0]['id']];
        $hugoAccount = $this->api('GET', "/api/teens/$hugoId", $this->parent)['accounts'][0]['id'];
        $this->api('POST', '/api/vehicles/'.$clio['id'].'/refuels', $this->parent, ['accountId' => $hugoAccount, 'amount' => 1000, 'volume' => 500, 'date' => '2026-10-02']);
        self::assertResponseStatusCodeSame(422);

        $other = $this->registerFamily('papa', 'Famille Durand');
        $this->api('GET', '/api/vehicles/'.$clio['id'], $other);
        self::assertResponseStatusCodeSame(404);
    }

    /** @param array<string, mixed> $vehicle */
    private function vehicle(array $vehicle): array
    {
        $created = $this->api('POST', '/api/vehicles', $this->parent, $vehicle);
        self::assertResponseStatusCodeSame(201);

        return $created;
    }

    private function refuel(int $vehicleId, int $amount, int $volume, ?int $odometer, string $date): array
    {
        $vehicle = $this->api('POST', "/api/vehicles/$vehicleId/refuels", $this->parent, ['accountId' => $this->accountId, 'amount' => $amount, 'volume' => $volume, 'odometer' => $odometer, 'date' => $date]);
        self::assertResponseStatusCodeSame(201);

        return $vehicle;
    }

    /** @return array<string, int> */
    private function forecast(): array
    {
        return $this->api('GET', '/api/dashboard', $this->parent)['forecast'];
    }
}
