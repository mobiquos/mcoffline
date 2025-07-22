<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250613064809 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE client (id INT AUTO_INCREMENT NOT NULL, rut VARCHAR(9) NOT NULL, first_last_name VARCHAR(100) NOT NULL, second_last_name VARCHAR(100) NOT NULL, name VARCHAR(200) NOT NULL, credit_limit INT NOT NULL, credit_available INT NOT NULL, block_comment VARCHAR(255) DEFAULT NULL, overdue INT NOT NULL, next_billing_at DATETIME NOT NULL, last_updated_at DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_5E9E89CB77153098 ON location (code)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            DROP TABLE client
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX UNIQ_5E9E89CB77153098 ON location
        SQL);
    }
}
