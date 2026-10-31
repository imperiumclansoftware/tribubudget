<?php

namespace App\Tests\Money;

use App\Api\VehicleCatalog;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class VehicleCatalogTest extends TestCase
{
    public function testVersionsCarryTheirEnergyAndApprovedConsumption(): void
    {
        $catalog = $this->catalog([new MockResponse(json_encode(['results' => [
            ['Description_Commerciale' => 'CLIO (91ch)', 'Energie' => 'ESSENCE', 'Conso_vitesse_mixte_Max' => 5.83],
            ['Description_Commerciale' => 'CLIO (91ch)', 'Energie' => 'ESSENCE', 'Conso_vitesse_mixte_Max' => 5.83],
            ['Description_Commerciale' => 'CLIO E-TECH (145ch)', 'Energie' => 'ESS+ELEC HNR', 'Conso_vitesse_mixte_Max' => 4.3],
            ['Description_Commerciale' => 'ZOE (51Kw)', 'Energie' => 'ELECTRIC', 'Conso_vitesse_mixte_Max' => null, 'Conso_elec_Max' => 189.9],
        ]]))], $requests);

        self::assertSame([
            ['label' => 'CLIO (91ch)', 'energy' => 'petrol', 'consumption' => 583],
            ['label' => 'CLIO E-TECH (145ch)', 'energy' => 'hybrid', 'consumption' => 430],
            ['label' => 'ZOE (51Kw)', 'energy' => 'electric', 'consumption' => 1899],
        ], $catalog->versions('RENAULT', 'CLIO'));
        self::assertStringContainsString('Marque_eq=RENAULT', $requests[0]);
    }

    public function testMakesAreSortedAndKeptADay(): void
    {
        $catalog = $this->catalog([new MockResponse(json_encode(['aggs' => [['value' => 'RENAULT'], ['value' => 'DACIA']]]))], $requests);

        self::assertSame(['DACIA', 'RENAULT'], $catalog->makes());
        self::assertSame(['DACIA', 'RENAULT'], $catalog->makes());
        self::assertCount(1, $requests);
    }

    public function testAnUnreachableCatalogIsEmptyAndAskedAgainNextTime(): void
    {
        $catalog = $this->catalog([
            new MockResponse('', ['error' => 'Timeout']),
            new MockResponse(json_encode(['aggs' => [['value' => 'DACIA']]])),
        ], $requests);

        self::assertSame([], $catalog->makes());
        self::assertSame(['DACIA'], $catalog->makes());
    }

    /**
     * @param list<MockResponse> $responses
     * @param list<string>|null  $requests  URLs asked, filled by the client
     */
    private function catalog(array $responses, ?array &$requests): VehicleCatalog
    {
        $requests = [];
        $client = new MockHttpClient(static function (string $method, string $url) use (&$responses, &$requests): MockResponse {
            $requests[] = $url;

            return array_shift($responses);
        });

        return new VehicleCatalog($client, new ArrayAdapter(), new NullLogger());
    }
}
