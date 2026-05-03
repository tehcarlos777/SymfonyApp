<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260503181000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Phoenix import token to users and Phoenix photo id to photos';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD phoenix_api_token VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE photos ADD phoenix_photo_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_photos_phoenix_photo_id ON photos (phoenix_photo_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_photos_user_phoenix_photo ON photos (user_id, phoenix_photo_id) WHERE phoenix_photo_id IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS uniq_photos_user_phoenix_photo');
        $this->addSql('DROP INDEX IF EXISTS idx_photos_phoenix_photo_id');
        $this->addSql('ALTER TABLE photos DROP phoenix_photo_id');
        $this->addSql('ALTER TABLE users DROP phoenix_api_token');
    }
}
