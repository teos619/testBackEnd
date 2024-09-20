<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240919073104 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE country (id INT AUTO_INCREMENT NOT NULL, id_metric_id INT NOT NULL, country VARCHAR(100) NOT NULL, value INT NOT NULL, INDEX IDX_5373C966296769A4 (id_metric_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE gender (id INT AUTO_INCREMENT NOT NULL, id_metric_id INT NOT NULL, gender VARCHAR(100) NOT NULL, value INT NOT NULL, INDEX IDX_C7470A42296769A4 (id_metric_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE metric (id INT AUTO_INCREMENT NOT NULL, metric VARCHAR(100) NOT NULL, value INT NOT NULL, date DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE country ADD CONSTRAINT FK_5373C966296769A4 FOREIGN KEY (id_metric_id) REFERENCES metric (id)');
        $this->addSql('ALTER TABLE gender ADD CONSTRAINT FK_C7470A42296769A4 FOREIGN KEY (id_metric_id) REFERENCES metric (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE country DROP FOREIGN KEY FK_5373C966296769A4');
        $this->addSql('ALTER TABLE gender DROP FOREIGN KEY FK_C7470A42296769A4');
        $this->addSql('DROP TABLE country');
        $this->addSql('DROP TABLE gender');
        $this->addSql('DROP TABLE metric');
    }
}
