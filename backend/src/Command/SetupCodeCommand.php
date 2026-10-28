<?php

namespace App\Command;

use App\Repository\FamilyRepository;
use App\Setup\SetupCode;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/** Run by the container at startup: the code shows in its logs until the family is created. */
#[AsCommand(name: 'app:setup:code', description: 'Affiche le code demandé par la page d’installation')]
final readonly class SetupCodeCommand
{
    public function __construct(
        private SetupCode $code,
        private FamilyRepository $families,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        if ($this->families->count() > 0) {
            $io->note('TribuBudget est déjà installé.');

            return Command::SUCCESS;
        }

        $io->success('Code d’installation : '.$this->code->current());
        $io->text('Saisissez-le sur la page d’installation de TribuBudget pour créer votre famille et votre compte de parent.');

        return Command::SUCCESS;
    }
}
