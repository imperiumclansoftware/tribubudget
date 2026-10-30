<?php

namespace App\Tests\Api;

use App\Tests\ApiTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class TeenTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    private string $parent;
    private int $householdId;
    private int $hugoId;
    private string $hugo;
    private int $checkingId;
    private int $savingsId;

    protected function setUp(): void
    {
        parent::setUp();
        self::mockTime('2026-10-28 09:00:00');
        $this->parent = $this->registerFamily();
        $this->householdId = $this->firstAccountId($this->parent);
        $this->api('PUT', '/api/accounts/'.$this->householdId, $this->parent, ['name' => 'Compte courant', 'type' => 'checking', 'initialBalance' => 200000]);
        [$this->hugoId, $this->hugo] = $this->addTeen($this->parent);
        $accounts = $this->api('GET', '/api/teens/'.$this->hugoId, $this->hugo)['accounts'];
        $this->checkingId = $this->accountOfType($accounts, 'checking');
        $this->savingsId = $this->accountOfType($accounts, 'savings');
    }

    public function testATeenagerStartsWithACurrentAndASavingsAccountOfTheirOwn(): void
    {
        $teen = $this->api('GET', '/api/teens/'.$this->hugoId, $this->hugo);
        self::assertSame('teen', $teen['role']);
        self::assertSame(0, $teen['balance']);
        self::assertSame([['Compte courant', 'checking', 'Hugo'], ['Épargne', 'savings', 'Hugo']], array_map(
            static fn ($a) => [$a['name'], $a['type'], $a['owner']['displayName']],
            $teen['accounts'],
        ));

        // The household's accounts only, unless asked for
        self::assertSame([$this->householdId], array_column($this->api('GET', '/api/accounts', $this->parent), 'id'));
        self::assertCount(3, $this->api('GET', '/api/accounts?teens=1', $this->parent));
    }

    public function testATeenagerSignsInWithARealPassword(): void
    {
        $this->api('POST', '/api/members', $this->parent, ['displayName' => 'Zoé', 'username' => 'zoe', 'role' => 'teen', 'password' => '1234']);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('adolescent compte au moins 8', $this->client->getResponse()->getContent());
    }

    public function testATeenagerSeesTheirOwnAccountsButNotTheHouseholdNorASiblings(): void
    {
        [$zoeId] = $this->addTeen($this->parent, 'zoe', 'secret-zoe');

        $this->api('GET', "/api/teens/$zoeId", $this->hugo);
        self::assertResponseStatusCodeSame(403);
        foreach (['/api/teens', '/api/accounts', '/api/dashboard', '/api/children', '/api/members'] as $uri) {
            $this->api('GET', $uri, $this->hugo);
            self::assertResponseStatusCodeSame(403, $uri);
        }
        $this->api('GET', '/api/operations?accountId='.$this->householdId, $this->hugo);
        self::assertResponseStatusCodeSame(422);
        $this->api('POST', '/api/operations', $this->hugo, $this->expense($this->householdId, 1000, 'Pizza'));
        self::assertResponseStatusCodeSame(422);
    }

    public function testTheirOperationsStayOutsideTheHouseholdBudget(): void
    {
        $this->api('POST', '/api/operations', $this->hugo, ['accountId' => $this->checkingId, 'type' => 'income', 'amount' => 5000, 'label' => 'Baby-sitting', 'date' => '2026-10-10']);
        $this->api('POST', '/api/operations', $this->hugo, $this->expense($this->checkingId, 1500, 'Cinéma'));
        self::assertResponseStatusCodeSame(201);

        $dashboard = $this->api('GET', '/api/dashboard?month=2026-10', $this->parent);
        self::assertSame([0, 0, 200000], [$dashboard['income'], $dashboard['expenses'], $dashboard['totalBalance']]);
        self::assertSame([['Hugo', 3500]], array_map(static fn ($t) => [$t['displayName'], $t['balance']], $dashboard['teens']));

        // The parents' operations page: the household's; a teenager's on demand
        self::assertSame([], $this->api('GET', '/api/operations?month=2026-10', $this->parent));
        self::assertSame(['Cinéma', 'Baby-sitting'], array_column($this->api('GET', '/api/operations?month=2026-10&ownerId='.$this->hugoId, $this->parent), 'label'));
        self::assertSame(['Cinéma', 'Baby-sitting'], array_column($this->api('GET', '/api/operations?month=2026-10', $this->hugo), 'label'));
    }

    public function testTheParentsTransferMoneyAndTheTeenagerMovesItBetweenTheirAccounts(): void
    {
        $fromParents = $this->api('POST', '/api/transfers', $this->parent, ['fromAccountId' => $this->householdId, 'toAccountId' => $this->checkingId, 'amount' => 10000, 'date' => '2026-10-05']);
        self::assertResponseStatusCodeSame(201);
        $this->api('POST', '/api/transfers', $this->hugo, ['fromAccountId' => $this->checkingId, 'toAccountId' => $this->savingsId, 'amount' => 4000, 'date' => '2026-10-06']);
        self::assertResponseStatusCodeSame(201);

        self::assertSame([6000, 4000], array_column($this->api('GET', '/api/teens/'.$this->hugoId, $this->hugo)['accounts'], 'balance'));

        // Never from the household, nor undoing what the parents did
        $this->api('POST', '/api/transfers', $this->hugo, ['fromAccountId' => $this->householdId, 'toAccountId' => $this->checkingId, 'amount' => 100, 'date' => '2026-10-06']);
        self::assertResponseStatusCodeSame(422);
        $this->api('DELETE', '/api/transfers/'.$fromParents['id'], $this->hugo);
        self::assertResponseStatusCodeSame(403);

        $editable = array_column(array_map(static fn ($o) => ['label' => $o['label'].' '.$o['amount'], 'editable' => $o['editable']], $this->api('GET', '/api/operations?month=2026-10', $this->hugo)), 'editable', 'label');
        self::assertEquals(['Virement -4000' => true, 'Virement 4000' => true, 'Virement 10000' => false], $editable);
    }

    public function testATeenagerOnlyChangesWhatTheyRecordedThemselves(): void
    {
        $fromParent = $this->api('POST', '/api/operations', $this->parent, ['accountId' => $this->checkingId, 'type' => 'income', 'amount' => 2000, 'label' => 'Cadeau', 'date' => '2026-10-05']);
        self::assertResponseStatusCodeSame(201);
        $own = $this->api('POST', '/api/operations', $this->hugo, $this->expense($this->checkingId, 500, 'Kebab'));

        $this->api('DELETE', '/api/operations/'.$fromParent['id'], $this->hugo);
        self::assertResponseStatusCodeSame(403);
        $this->api('PUT', '/api/operations/'.$own['id'], $this->hugo, $this->expense($this->savingsId, 600, 'Kebab'));
        self::assertResponseIsSuccessful();
        $this->api('DELETE', '/api/operations/'.$own['id'], $this->parent);
        self::assertResponseStatusCodeSame(204);
    }

    public function testAnExpenseIsReimbursedOnItsAccountAndRecordedInTheHouseholdBudget(): void
    {
        $groceries = $this->api('GET', '/api/categories', $this->parent)[0];
        $expense = $this->api('POST', '/api/operations', $this->hugo, $this->expense($this->checkingId, 2350, 'Courses pour le dîner'));

        $request = $this->api('POST', '/api/requests/reimbursement', $this->hugo, ['operationId' => $expense['id']]);
        self::assertResponseStatusCodeSame(201);
        self::assertSame(['reimbursement', 2350, 'Courses pour le dîner', $expense['id']], [$request['kind'], $request['amount'], $request['reason'], $request['operationId']]);
        self::assertSame(1, $this->api('GET', '/api/me', $this->parent)['pendingRequests']);

        // Asked once; and the expense stays as asked while waiting
        $this->api('POST', '/api/requests/reimbursement', $this->hugo, ['operationId' => $expense['id']]);
        self::assertResponseStatusCodeSame(422);
        $this->api('DELETE', '/api/operations/'.$expense['id'], $this->hugo);
        self::assertResponseStatusCodeSame(422);
        [$listed] = $this->api('GET', '/api/operations?month=2026-10', $this->hugo);
        self::assertSame([['id' => $request['id'], 'status' => 'pending'], false], [$listed['reimbursement'], $listed['editable']]);

        $this->api('POST', '/api/requests/'.$request['id'].'/approve', $this->parent, ['accountId' => $this->householdId, 'categoryId' => $groceries['id']]);
        self::assertResponseIsSuccessful();

        self::assertSame([0, 0], array_column($this->api('GET', '/api/teens/'.$this->hugoId, $this->hugo)['accounts'], 'balance'));
        $dashboard = $this->api('GET', '/api/dashboard?month=2026-10', $this->parent);
        self::assertSame(2350, $dashboard['expenses']);
        self::assertSame(2350, array_column($dashboard['categories'], 'spent', 'id')[$groceries['id']]);
        [$household] = $this->api('GET', '/api/operations?month=2026-10', $this->parent);
        self::assertSame('Remboursement à Hugo : Courses pour le dîner', $household['label']);

        // Reimbursed: only the parents change it now
        $this->api('DELETE', '/api/operations/'.$expense['id'], $this->hugo);
        self::assertResponseStatusCodeSame(422);
    }

    public function testOnlyTheirOwnExpensesAreReimbursed(): void
    {
        $income = $this->api('POST', '/api/operations', $this->hugo, ['accountId' => $this->checkingId, 'type' => 'income', 'amount' => 100, 'label' => 'Trouvé', 'date' => '2026-10-10']);
        $household = $this->api('POST', '/api/operations', $this->parent, $this->expense($this->householdId, 100, 'Pain'));

        foreach ([$income['id'], $household['id']] as $id) {
            $this->api('POST', '/api/requests/reimbursement', $this->hugo, ['operationId' => $id]);
            self::assertResponseStatusCodeSame(422);
        }
        [, $lea] = $this->addChild($this->parent);
        $this->api('POST', '/api/requests/reimbursement', $lea, ['operationId' => $household['id']]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testTheAllowanceAndAdvancesArriveOnTheCurrentAccount(): void
    {
        $this->api('PUT', '/api/teens/'.$this->hugoId.'/allowance', $this->parent, ['amount' => 2000, 'frequency' => 'monthly', 'nextPaymentOn' => '2026-11-01', 'accountId' => $this->householdId]);
        self::assertResponseIsSuccessful();
        $this->api('PUT', '/api/teens/'.$this->hugoId.'/allowance', $this->hugo, ['amount' => 9000, 'frequency' => 'monthly', 'nextPaymentOn' => '2026-11-01']);
        self::assertResponseStatusCodeSame(403);

        $request = $this->api('POST', '/api/requests', $this->hugo, ['kind' => 'advance', 'amount' => 1500, 'reason' => 'Concert']);
        $this->api('POST', '/api/requests/'.$request['id'].'/approve', $this->parent, ['accountId' => $this->householdId]);
        $teen = $this->api('GET', '/api/teens/'.$this->hugoId, $this->hugo);
        self::assertSame([1500, 1500], [$teen['accounts'][0]['balance'], $teen['advanceOwed']]);

        // The first allowance repays the advance: Hugo receives 500, which the household pays
        self::mockTime('2026-11-02 09:00:00');
        $teen = $this->api('GET', '/api/teens/'.$this->hugoId, $this->hugo);
        self::assertSame([1500 + 500, 0], [$teen['accounts'][0]['balance'], $teen['advanceOwed']]);
        self::assertSame(200000 - 1500 - 500, $this->api('GET', '/api/dashboard', $this->parent)['totalBalance']);
    }

    public function testAccountsWithHistoryReturnToTheHouseholdWhenTheTeenagerLeaves(): void
    {
        $this->api('POST', '/api/operations', $this->hugo, $this->expense($this->checkingId, 500, 'Kebab'));

        $this->api('DELETE', '/api/members/'.$this->hugoId, $this->parent);
        self::assertResponseStatusCodeSame(204);
        self::assertSame(['Compte courant', 'Compte courant (Hugo)'], array_column($this->api('GET', '/api/accounts?teens=1', $this->parent), 'name'));
    }

    /** @param list<array<string, mixed>> $accounts */
    private function accountOfType(array $accounts, string $type): int
    {
        return array_column($accounts, 'id', 'type')[$type];
    }

    /** @return array<string, mixed> */
    private function expense(int $accountId, int $amount, string $label): array
    {
        return ['accountId' => $accountId, 'type' => 'expense', 'amount' => $amount, 'label' => $label, 'date' => '2026-10-12'];
    }
}
