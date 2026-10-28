<?php

namespace App\Tests\Api;

use App\Tests\ApiTestCase;

final class SavingsProjectTest extends ApiTestCase
{
    private string $parent;
    private int $savingsId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parent = $this->registerFamily();
        // 3 000 € saved, of which 1 000 € kept aside
        $this->savingsId = $this->api('POST', '/api/accounts', $this->parent, ['name' => 'Livret A', 'type' => 'savings', 'initialBalance' => 300000, 'minimumBalance' => 100000])['id'];
    }

    public function testTheMoneyAboveTheMinimumGoesToTheProjectsByPriority(): void
    {
        $this->project('Vacances', 150000);
        $this->project('Vélo', 40000);
        $this->project('Canapé', 80000);

        [$account] = $this->api('GET', '/api/projects', $this->parent)['accounts'];
        self::assertSame(200000, $account['available']);
        self::assertSame([
            ['Vacances', 1, 150000, true],
            ['Vélo', 2, 40000, true],
            ['Canapé', 3, 10000, false],
        ], array_map(static fn ($p) => [$p['name'], $p['priority'], $p['allocated'], $p['fundable']], $account['projects']));
        self::assertSame(0, $account['unallocated']);

        $fundable = $this->api('GET', '/api/dashboard', $this->parent)['fundableProjects'];
        self::assertSame(['Vacances', 'Vélo'], array_column($fundable, 'name'));
    }

    public function testNothingIsAllocatedBelowTheMinimum(): void
    {
        $this->api('PUT', '/api/accounts/'.$this->savingsId, $this->parent, ['name' => 'Livret A', 'type' => 'savings', 'initialBalance' => 300000, 'minimumBalance' => 350000]);
        $this->project('Vélo', 40000);

        [$account] = $this->api('GET', '/api/projects', $this->parent)['accounts'];
        self::assertSame(0, $account['available']);
        self::assertSame(0, $account['projects'][0]['allocated']);
        self::assertSame([], $this->api('GET', '/api/dashboard', $this->parent)['fundableProjects']);
    }

    public function testAHigherPriorityIsServedFirst(): void
    {
        $this->project('Voiture', 500000);
        $this->project('Vélo', 40000);
        [$car, $bike] = $this->projects();
        self::assertFalse($bike['fundable']);

        $this->api('PUT', '/api/projects/priorities', $this->parent, ['accountId' => $this->savingsId, 'projectIds' => [$bike['id'], $car['id']]]);
        self::assertResponseStatusCodeSame(204);
        [$bike, $car] = $this->projects();
        self::assertSame(['Vélo', 40000, true], [$bike['name'], $bike['allocated'], $bike['fundable']]);
        self::assertSame(['Voiture', 160000, false], [$car['name'], $car['allocated'], $car['fundable']]);
    }

    public function testACarriedOutProjectFreesTheRestForTheNextOnes(): void
    {
        $this->project('Vélo', 40000);
        $this->project('Vacances', 180000);
        [$bike] = $this->projects();

        $this->api('POST', '/api/projects/'.$bike['id'].'/achieve', $this->parent, ['recordWithdrawal' => true]);
        self::assertResponseStatusCodeSame(204);

        $projects = $this->api('GET', '/api/projects', $this->parent);
        // 3 000 - 400 = 2 600 €, 1 000 € kept aside: 1 600 € for the holidays, now first
        self::assertSame([['Vacances', 1, 160000]], array_map(static fn ($p) => [$p['name'], $p['priority'], $p['allocated']], $projects['accounts'][0]['projects']));
        self::assertSame(['Vélo'], array_column($projects['achieved'], 'name'));
        $operations = $this->api('GET', '/api/operations?accountId='.$this->savingsId, $this->parent);
        self::assertSame([['Projet : Vélo', -40000]], array_map(static fn ($o) => [$o['label'], $o['amount']], $operations));
    }

    public function testProjectsBelongToSavingsAccounts(): void
    {
        $this->api('POST', '/api/projects', $this->parent, ['accountId' => $this->firstAccountId($this->parent), 'name' => 'Vélo', 'target' => 40000]);
        self::assertResponseStatusCodeSame(422);

        $this->project('Vélo', 40000);
        $this->api('PUT', '/api/accounts/'.$this->savingsId, $this->parent, ['name' => 'Livret A', 'type' => 'checking', 'initialBalance' => 300000]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testDeletingAProjectClosesTheGapInThePriorities(): void
    {
        $this->project('Vélo', 40000);
        $this->project('Canapé', 80000);
        $this->project('Tablette', 30000);
        [, $sofa] = $this->projects();

        $this->api('DELETE', '/api/projects/'.$sofa['id'], $this->parent);
        self::assertSame([['Vélo', 1], ['Tablette', 2]], array_map(static fn ($p) => [$p['name'], $p['priority']], $this->projects()));
    }

    public function testChildrenAndOtherFamiliesHaveNoAccess(): void
    {
        $this->project('Vélo', 40000);
        [$bike] = $this->projects();
        [, $child] = $this->addChild($this->parent);

        $this->api('GET', '/api/projects', $child);
        self::assertResponseStatusCodeSame(403);
        $this->api('DELETE', '/api/projects/'.$bike['id'], $this->registerFamily('papa', 'Famille Durand'));
        self::assertResponseStatusCodeSame(404);
    }

    private function project(string $name, int $target): void
    {
        $this->api('POST', '/api/projects', $this->parent, ['accountId' => $this->savingsId, 'name' => $name, 'target' => $target]);
        self::assertResponseStatusCodeSame(201);
    }

    /** @return list<array<string, mixed>> */
    private function projects(): array
    {
        return $this->api('GET', '/api/projects', $this->parent)['accounts'][0]['projects'];
    }
}
