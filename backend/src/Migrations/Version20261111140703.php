<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261111140703 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Virements : catégorie (budget) de l’argent sorti du foyer';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE transfer ADD category_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE transfer ADD CONSTRAINT FK_4034A3C012469DE2 FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_4034A3C012469DE2 ON transfer (category_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE transfer DROP CONSTRAINT FK_4034A3C012469DE2');
        $this->addSql('DROP INDEX IDX_4034A3C012469DE2');
        $this->addSql('ALTER TABLE transfer DROP category_id');
    }
}
