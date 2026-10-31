<?php

namespace App\Enum;

/** What a vehicle runs on: its fill-ups are counted in litres, or in kWh when electric. */
enum VehicleEnergy: string
{
    case Petrol = 'petrol';
    case Diesel = 'diesel';
    case Hybrid = 'hybrid';
    case PluginHybrid = 'plugin_hybrid';
    case Electric = 'electric';
    case Lpg = 'lpg';
    case Ethanol = 'ethanol';
    case Other = 'other';

    /** The energy as named by the ADEME data (VehicleCatalog); Other when unknown */
    public static function fromAdeme(string $energy): self
    {
        return match (trim($energy)) {
            'ESSENCE' => self::Petrol,
            'GAZOLE' => self::Diesel,
            'ESS+ELEC HNR', 'GAZ+ELEC HNR' => self::Hybrid,
            'ELEC+ESSENC HR', 'ELEC+GAZOLE HR' => self::PluginHybrid,
            'ELECTRIC' => self::Electric,
            'ESS+G.P.L.' => self::Lpg,
            'SUPERETHANOL' => self::Ethanol,
            default => self::Other,
        };
    }
}
