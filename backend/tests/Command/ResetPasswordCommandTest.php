<?php

namespace App\Tests\Command;

use App\Tests\ApiTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ResetPasswordCommandTest extends ApiTestCase
{
    public function testTheAdministratorGivesAParentANewPassword(): void
    {
        $this->registerFamily();
        $tester = $this->tester();

        $tester->setInputs(['Nouveau-velo-bleu-2026', 'Nouveau-velo-bleu-2026']);
        self::assertSame(Command::SUCCESS, $tester->execute(['username' => 'MAMAN']));

        $this->login('maman', 'Nouveau-velo-bleu-2026');
    }

    public function testTheRulesOfTheAppApply(): void
    {
        $this->registerFamily();
        $tester = $this->tester();

        $tester->setInputs(['court', 'court']);
        self::assertSame(Command::FAILURE, $tester->execute(['username' => 'maman']));
        self::assertStringContainsString('au moins 12 caractères', $tester->getDisplay());

        $tester->setInputs(['Nouveau-velo-bleu-2026', 'autre-chose']);
        self::assertSame(Command::FAILURE, $tester->execute(['username' => 'maman']));

        self::assertSame(Command::FAILURE, $tester->execute(['username' => 'personne']));
        $this->login('maman', 'motdepasse');
    }

    private function tester(): CommandTester
    {
        return new CommandTester((new Application(self::$kernel))->find('app:user:reset-password'));
    }
}
