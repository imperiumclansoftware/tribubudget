<?php

namespace App\Api;

use App\Enum\VehicleEnergy;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Makes, models and versions of the cars sold new in France, with their approved consumption:
 * the open data of the ADEME ("Car Labelling", free, no key), asked by the server and kept a day.
 * Only the make and model chosen are sent, never anything about the family. Unreachable, the
 * catalog is empty: the vehicle is described by hand.
 */
final class VehicleCatalog
{
    private const URL = 'https://data.ademe.fr/data-fair/api/v1/datasets/ademe-car-labelling';
    /** The ADEME updates it every quarter */
    private const TTL = 86400;
    /** Most the API returns at once */
    private const MAX = 100;

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
    ) {
    }

    /** @return list<string> */
    public function makes(): array
    {
        return $this->cached('makes', fn () => $this->values('Marque', []));
    }

    /** @return list<string> */
    public function models(string $make): array
    {
        return $this->cached('models-'.md5($make), fn () => $this->values('Libellé_modèle', ['Marque_eq' => $make]));
    }

    /**
     * @return list<array{label: string, energy: string, consumption: ?int}> consumption in
     *                                                                       hundredths of litre (or kWh) per 100 km
     */
    public function versions(string $make, string $model): array
    {
        return $this->cached('versions-'.md5($make."\n".$model), function () use ($make, $model): array {
            $lines = $this->get('/lines', [
                'Marque_eq' => $make,
                'Libellé_modèle_eq' => $model,
                'size' => self::MAX,
                'select' => 'Description_Commerciale,Energie,Conso_vitesse_mixte_Max,Conso_elec_Max',
                'sort' => 'Description_Commerciale',
            ])['results'] ?? [];
            $versions = [];
            foreach ($lines as $line) {
                $energy = VehicleEnergy::fromAdeme((string) ($line['Energie'] ?? ''));
                $label = trim((string) ($line['Description_Commerciale'] ?? ''));
                $versions[$label."\n".$energy->value] ??= [
                    'label' => $label,
                    'energy' => $energy->value,
                    'consumption' => self::consumption($energy, $line),
                ];
            }

            return array_values($versions);
        });
    }

    /**
     * Litres per 100 km for an engine (combined cycle), kWh per 100 km for an electric vehicle
     * (given in Wh/km), in hundredths; the higher of the approved values.
     *
     * @param array<string, mixed> $line
     */
    private static function consumption(VehicleEnergy $energy, array $line): ?int
    {
        if (VehicleEnergy::Electric === $energy) {
            return is_numeric($line['Conso_elec_Max'] ?? null) ? (int) round($line['Conso_elec_Max'] * 10) : null;
        }

        return is_numeric($line['Conso_vitesse_mixte_Max'] ?? null) ? (int) round($line['Conso_vitesse_mixte_Max'] * 100) : null;
    }

    /**
     * @param array<string, string> $filters
     *
     * @return list<string> sorted
     */
    private function values(string $field, array $filters): array
    {
        $values = array_map(static fn (array $agg) => (string) $agg['value'], $this->get('/values_agg', ['field' => $field, 'size' => self::MAX] + $filters)['aggs'] ?? []);
        sort($values);

        return $values;
    }

    /**
     * @param array<string, string|int> $query
     *
     * @return array<string, mixed>
     */
    private function get(string $path, array $query): array
    {
        return $this->http->request('GET', self::URL.$path, ['query' => $query, 'timeout' => 5])->toArray();
    }

    /**
     * A failure is not kept: the next request tries again.
     *
     * @template T of list<mixed>
     *
     * @param callable(): T $fetch
     *
     * @return T|list<never>
     */
    private function cached(string $key, callable $fetch): array
    {
        try {
            return $this->cache->get('vehicle-catalog-'.$key, static function (ItemInterface $item) use ($fetch): array {
                $item->expiresAfter(self::TTL);

                return $fetch();
            });
        } catch (ExceptionInterface $e) {
            $this->logger->warning('Catalogue des véhicules de l’ADEME injoignable : {message}', ['message' => $e->getMessage()]);

            return [];
        }
    }
}
