<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261030091542 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adolescents : comptes à eux et demandes de remboursement';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE account ADD owner_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE account ADD CONSTRAINT FK_7D3656A47E3C61F9 FOREIGN KEY (owner_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_7D3656A47E3C61F9 ON account (owner_id)');
        $this->addSql('ALTER TABLE money_request ADD operation_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE money_request ALTER kind TYPE VARCHAR(15)');
        $this->addSql('ALTER TABLE money_request ADD CONSTRAINT FK_5FFCB9D444AC3583 FOREIGN KEY (operation_id) REFERENCES operation (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_5FFCB9D444AC3583 ON money_request (operation_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE account DROP CONSTRAINT FK_7D3656A47E3C61F9');
        $this->addSql('DROP INDEX IDX_7D3656A47E3C61F9');
        $this->addSql('ALTER TABLE account DROP owner_id');
        $this->addSql('ALTER TABLE money_request DROP CONSTRAINT FK_5FFCB9D444AC3583');
        $this->addSql('DROP INDEX IDX_5FFCB9D444AC3583');
        $this->addSql('ALTER TABLE money_request DROP operation_id');
        $this->addSql('ALTER TABLE money_request ALTER kind TYPE VARCHAR(10)');
    }
}
