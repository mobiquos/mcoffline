<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250613221046 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE contingency (id INT AUTO_INCREMENT NOT NULL, location_code VARCHAR(80) NOT NULL, started_at DATETIME NOT NULL, ended_at DATETIME DEFAULT NULL, started_by_name VARCHAR(255) NOT NULL, location_id INT DEFAULT NULL, started_by_id INT DEFAULT NULL, INDEX IDX_109C07D264D218E (location_id), INDEX IDX_109C07D29740C9D5 (started_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE contingency ADD CONSTRAINT FK_109C07D264D218E FOREIGN KEY (location_id) REFERENCES location (id) ON DELETE SET NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE contingency ADD CONSTRAINT FK_109C07D29740C9D5 FOREIGN KEY (started_by_id) REFERENCES user (id) ON DELETE SET NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE contingency DROP FOREIGN KEY FK_109C07D264D218E
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE contingency DROP FOREIGN KEY FK_109C07D29740C9D5
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE contingency
        SQL);
    }
}
