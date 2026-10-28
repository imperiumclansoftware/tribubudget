<?php

namespace App\Command;

use App\Money\Schedules;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;

/** Run daily by cron, so that what falls on fixed dates is recorded even when nobody opens the application. */
#[AsCommand(name: 'app:schedules:run', description: 'Verse l’argent de poche et enregistre les opérations récurrentes arrivés à échéance')]
final class RunSchedulesCommand
{
    public function __construct(
        private readonly Schedules $schedules,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $done = $this->schedules->catchUpAll();
        $io->success(\sprintf('%d versement(s) d’argent de poche, %d opération(s) récurrente(s) enregistrée(s).', $done['allowances'], $done['operations']));

        return 0;
    }
}
