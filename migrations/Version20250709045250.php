<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250709045250 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE quote ADD contingency_id INT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE quote ADD CONSTRAINT FK_6B71CBF45EB46DD6 FOREIGN KEY (contingency_id) REFERENCES contingency (id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_6B71CBF45EB46DD6 ON quote (contingency_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE sales ADD contingency_id INT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE sales ADD CONSTRAINT FK_6B8170445EB46DD6 FOREIGN KEY (contingency_id) REFERENCES contingency (id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_6B8170445EB46DD6 ON sales (contingency_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE sales DROP FOREIGN KEY FK_6B8170445EB46DD6
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_6B8170445EB46DD6 ON sales
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE sales DROP contingency_id
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE quote DROP FOREIGN KEY FK_6B71CBF45EB46DD6
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_6B71CBF45EB46DD6 ON quote
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE quote DROP contingency_id
        SQL);
    }
}
