<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250709173206 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE payment ADD contingency_id INT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE payment ADD CONSTRAINT FK_6D28840D5EB46DD6 FOREIGN KEY (contingency_id) REFERENCES contingency (id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_6D28840D5EB46DD6 ON payment (contingency_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE payment DROP FOREIGN KEY FK_6D28840D5EB46DD6
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_6D28840D5EB46DD6 ON payment
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE payment DROP contingency_id
        SQL);
    }
}
