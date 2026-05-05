## Architektura

Ten projekt składa się z dwóch oddzielnych aplikacji z własnymi bazami danych:

- **Symfony App** (port 8000): Główna aplikacja internetowa
  - Baza danych: `symfony-db` (PostgreSQL, port 5432)
  - Nazwa bazy danych: `symfony_app`

- **Phoenix API** (port 4000): Mikroserwis REST API
  - Baza danych: `phoenix-db` (PostgreSQL, port 5433)
  - Nazwa bazy danych: `phoenix_api`

## Szybki start
```bash
cp .env.example .env
# Windows PowerShell: Copy-Item .env.example .env
# Dla środowisk innych niż lokalne wygeneruj własne wartości (hasła, PHOENIX_SECRET_KEY_BASE, SYMFONY_APP_SECRET).

docker-compose up -d

# Instalacja zależności Symfony i Phoenix
docker-compose exec symfony composer install --no-interaction --no-scripts
docker-compose exec phoenix mix deps.get

# Konfiguracja bazy danych Symfony
docker-compose exec symfony php bin/console doctrine:migrations:migrate --no-interaction
docker-compose exec symfony php bin/console app:seed
# app:seed wypisuje w terminalu plaintext auth token do logowania w Symfony (/login) i zapisuje w bazie tylko jego hash.

# Konfiguracja bazy danych Phoenix
docker-compose exec phoenix mix ecto.migrate
docker-compose exec phoenix mix run priv/repo/seeds.exs
# seed Phoenix wypisuje w terminalu plaintext token dla phoenix_api.
```

### Rozwiązywanie problemów (opcjonalnie)

Jeśli pojawią się problemy z logowaniem do PostgreSQL (np. po wcześniejszych zmianach haseł lub danych), uruchom projekt od zera:

```bash
docker-compose down -v
docker-compose up -d
```

Dostęp do aplikacji:
- Symfony App: http://localhost:8000
- Phoenix API: http://localhost:4000

## Komendy Symfony

### Migracja bazy danych
```bash
docker-compose exec symfony php bin/console doctrine:migrations:migrate --no-interaction
```

### Ponowne tworzenie bazy danych
```bash
docker-compose exec symfony php bin/console doctrine:schema:drop --force --full-database
docker-compose exec symfony php bin/console doctrine:migrations:migrate --no-interaction
docker-compose exec symfony php bin/console app:seed
# app:seed wypisuje w terminalu plaintext auth token do logowania w Symfony (/login) i zapisuje w bazie tylko jego hash.
```

### Czyszczenie pamięci podręcznej (Cache)
```bash
docker-compose exec symfony php bin/console cache:clear
```

### Restart
```bash
docker-compose restart symfony
```

### Uruchamianie testów
```bash
docker-compose exec -e APP_ENV=test symfony php bin/phpunit -c phpunit.xml.dist
```

## Komendy Phoenix

### Migracja bazy danych
```bash
docker-compose exec phoenix mix ecto.migrate
```

### Seedowanie bazy danych
```bash
docker-compose exec phoenix mix run priv/repo/seeds.exs
# seed Phoenix wypisuje w terminalu plaintext token dla phoenix_api.
```

### Ponowne tworzenie bazy danych
```bash
docker-compose exec phoenix mix ecto.reset
docker-compose exec phoenix mix run priv/repo/seeds.exs
# seed Phoenix wypisuje w terminalu plaintext token dla phoenix_api.
```

### Restart
```bash
docker-compose restart phoenix
```

### Uruchamianie testów
```bash
docker-compose exec phoenix mix deps.get
docker-compose exec phoenix mix test
```
