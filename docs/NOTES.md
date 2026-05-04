### Zadanie 1 - zadbaj o jakość kodu oraz rozwiązań w projekcie SymfonyApp.

Moje commity zwiazane z zad 1:

[`a1f0a09`](https://github.com/tehcarlos777/SymfonyApp/commit/a1f0a09) Add php-cs-fixer PSR-12 setup for Symfony app
  - Dodano `friendsofphp/php-cs-fixer` do `require-dev` oraz zaktualizowano `composer.lock`, aby zapewnić spójne wersje narzędzi lokalnie i w CI.
  - Dodano `symfony-app/.php-cs-fixer.dist.php` z regułą `@PSR12`, żeby styl kodu był jednoznacznie zdefiniowany w repozytorium, a nie w ustawieniach IDE.
  - Dodano skrypty `composer cs:check` i `composer cs:fix`, żeby łatwo uruchamiać kontrolę i automatyczne poprawki lokalnie oraz w pipeline.
  - Dodano `**/.php-cs-fixer.cache` do `.gitignore`, bo to plik lokalny i nie powinien trafiać do kontroli wersji.

[`c0694e5`](https://github.com/tehcarlos777/SymfonyApp/commit/c0694e5) Fix SQL injection risk in auth login query flow.
  - Zastąpiono interpolację stringa w zapytaniach SQL metodami Doctrine ORM (`findOneBy`), eliminując ryzyko SQL injection w procesie logowania.
  - Usunięto zależność od `Doctrine\DBAL\Connection` na rzecz `EntityManagerInterface`, co jest zgodne ze standardami Symfony.
  - Zapytania operują teraz na encjach (`AuthToken`, `User`), a dane sesji są ustawiane przez gettery encji, a nie przez surowe tablice z bazy.

[`3324ae5`](https://github.com/tehcarlos777/SymfonyApp/commit/3324ae5) Fix auth token binding and move login to POST
  - Zmieniono endpoint logowania z `GET /auth/{username}/{token}` na `POST /login` — token nie jest już widoczny w URL, historii przeglądarki ani logach serwera.
  - Usunięto parametr `{username}` z route — użytkownik jest teraz pobierany wyłącznie z relacji `$tokenEntity->getUser()`, co eliminuje możliwość zalogowania się jako inny użytkownik przez podanie cudzego `username` w URL.
  - Dodano walidację CSRF (`isCsrfTokenValid`) — formularz jest zabezpieczony przed atakiem, w którym obca strona mogłaby wymusić wysłanie żądania logowania w cudzej przeglądarce.
  - Obsługa `GET /login` renderuje formularz i generuje token CSRF; obsługa `POST /login` waliduje CSRF, sprawdza token w bazie i zapisuje użytkownika w sesji.
  - Dodano szablon `templates/auth/login.html.twig` z formularzem logowania (pole na token, ukryte pole `_csrf_token`, przycisk submit).

[`a8295f31`](https://github.com/tehcarlos777/SymfonyApp/commit/a8295f31) Move Docker secrets to .env and add .env.example
  - W `docker-compose.yml` usunięto stałe dane logowania do PostgreSQL (`postgres`/`postgres`) oraz domyślne wartości `${VAR:-sekret}` dla Phoenix i Symfony. Sekrety i hasła są teraz pobierane wyłącznie ze zmiennych środowiskowych, a składnia `${NAZWA:?komunikat}` wymusza ich ustawienie przed uruchomieniem Compose. Dzięki temu brak konfiguracji kończy się błędem zamiast uruchomieniem stacku z przewidywalnymi sekretami.
  - Dodano szablon `.env.example` z zmiennymi dla baz Phoenix i Symfony, URL-ami połączeń oraz przykładowymi wartościami na potrzeby lokalnego developmentu. W README opisano skopiowanie `.env.example` do lokalnego środowiska, przed uruchomieniem `docker compose up`.

[`32646d87`](https://github.com/tehcarlos777/SymfonyApp/commit/32646d87) Hash Symfony auth tokens before storing in database
  - `AuthController` i `SeedDatabaseCommand` dostają dedykowany sekret HMAC przez `services.yaml` (`%env(AUTH_TOKEN_HMAC_SECRET)%`)
  - Seed generuje losowy text (`bin2hex(random_bytes(32))`), wyświetla go operatorowi w terminalu i zapisuje do bazy wyłącznie skrót `hash_hmac('sha256', $plaintext, $tokenHmacSecret)`.
  - Przy logowaniu `AuthController` hashuje przesłany token tym samym kluczem, szuka w bazie po hashu i porównuje z `hash_equals`.
  - Dzięki temu plaintext nigdy nie trafia do bazy — nawet wyciek DB nie daje atakującemu działających tokenów bez znajomości sekretu HMAC tokenów.

[`ff192be`](https://github.com/tehcarlos777/SymfonyApp/commit/ff192be) Add PHPUnit tests for login CSRF and token hashing
  - Dodano testy funkcjonalne `WebTestCase` dla `/login`: `GET` renderuje ukryte pole `_csrf_token`; `POST` z błędnym CSRF kończy się przekierowaniem na `/login` i komunikatem flash „Invalid CSRF token.”.
  - Dodano test jednostkowy `AuthController` dla scenariusza: CSRF poprawny, token niepasujący do bazy — oczekiwane przekierowanie na `/login` i flash „Invalid token.” (mock repozytorium zamiast podmiany `EntityManager` w kernelu).
  - Dodano test kontraktu HMAC: `AuthController` i `SeedDatabaseCommand` muszą dawać identyczny `hash_hmac('sha256', …)` dla tego samego sekretu.
  - W `symfony-app/phpunit.xml.dist` dodano minimalne zmienne środowiskowe testowe (`APP_SECRET`, `AUTH_TOKEN_HMAC_SECRET`, `DATABASE_URL`), żeby kernel i kontener DI budowały się w `APP_ENV=test` bez zależności od lokalnego `.env`.

[`550f3852`](https://github.com/tehcarlos777/SymfonyApp/commit/550f3852) Batch user likes
  - W `docker-compose.yml` usunięto pole `version` (w nowym Compose jest ignorowane i generowało ostrzeżenie).
  - Na stronie głównej wyeliminowano N+1 dla polubień: dodano `LikeRepository::findLikedPhotoIdsForUser(User, array $photoIds)` — jedno zapytanie z `IN (:photoIds)` zamiast `hasUserLikedPhoto()` w pętli po każdym zdjęciu; `HomeController` buduje z tego mapę `userLikes` bez dodatkowych zapytań w pętli.

[`8ca5005`](https://github.com/tehcarlos777/SymfonyApp/commit/8ca5005) Inject repositories via constructor in HomeController
  - Usunięto ręczne tworzenie `new PhotoRepository($managerRegistry)` i `new LikeRepository($managerRegistry)` wewnątrz metody `index()`.
  - Repozytoria są teraz wstrzykiwane przez konstruktor (`private readonly`) — Symfony DI container zarządza ich cyklem życia przez autowiring.
  - Usunięto zbędny import `ManagerRegistry` i parametr `$managerRegistry` z sygnatury metody.
  - Zastąpiono adnotację `@Route` nowoczesnym atrybutem PHP `#[Route]` i usunięto mylący `@return JsonResponse` (metoda zwraca `Response`).

[`70b2be78`](https://github.com/tehcarlos777/SymfonyApp/commit/70b2be78) Use descriptive DQL aliases in LikeRepository and PhotoRepository
  - W `LikeRepository` zastąpiono aliasy DQL `l` → `likeEntity` oraz `p` → `photo` (w tym `innerJoin`, `select`/`where`/`andWhere` oraz stała `COND_USER`), żeby zapytania czytały się bliżej nazw encji i relacji.
  - W `PhotoRepository::findAllWithUsers()` zastąpiono `p`/`u` aliasami `photo`/`user` w `leftJoin`, `addSelect` i `orderBy`.

[`ee86a0a0`](https://github.com/tehcarlos777/SymfonyApp/commit/ee86a0a0) Add Login button
  - W `symfony-app/templates/base.html.twig` dodano przycisk `🔑 Login` prowadzący do endpointu `auth_login` (`/login`), żeby użytkownik miał bezpośrednią ścieżkę do formularza logowania.
  - Przycisk renderuje się tylko gdy brak `user_id` w sesji; po zalogowaniu nadal pokazuje się wyłącznie menu profilu (`My Profile` + `Logout`).
  - Dla trasy `auth_login` przycisk jest ukryty

[`ac057e13`](https://github.com/tehcarlos777/SymfonyApp/commit/ac057e13) Symfony-app: Add Phoenix import columns and DB migration
- Migracja `symfony-app/migrations/Version20260503181000.php`: kolumna `users.phoenix_api_token` (wartość nagłówka `access-token` używana do wywołań Phoenix), kolumna `photos.phoenix_photo_id` (identyfikator zdjęcia z Phoenix — pole `id` z JSON-a odpowiedzi), indeks `idx_photos_phoenix_photo_id` oraz **częściowy** unikalny indeks `(user_id, phoenix_photo_id) WHERE phoenix_photo_id IS NOT NULL` w PostgreSQL — zapobiega duplikatom importu dla jednego użytkownika Symfony.
- Encje: `User::$phoenixApiToken`, `Photo::$phoenixPhotoId` + gettery/settery zgodne z mapowaniem Doctrine.
- `PhotoRepository::findOneByUserAndPhoenixPhotoId()` — szybki lookup przed `persist`, żeby ponowny import nie tworzył duplikatów.
- `services.yaml` binduje `PHOENIX_BASE_URL` jako `$phoenixBaseUrl` — w Dockerze `http://phoenix:4000` (kontenery z tego samego `docker-compose` łączą się po nazwie serwisu); poza Dockerem `http://localhost:4000`.

[`2777c297`](https://github.com/tehcarlos777/SymfonyApp/commit/2777c297) Symfony-app: import Phoenix photos from profile
- `symfony-app/src/Import/PhoenixPhotoImporter.php`: `GET {PHOENIX_BASE_URL}/api/photos` z nagłówkiem `access-token`, obsługa błędów sieci (`TransportExceptionInterface` przy leniwym HttpClient — m.in. przy `getStatusCode()` / `toArray()`), mapowanie `id` → `phoenixPhotoId`, `photo_url` → `imageUrl`, zapis pod zalogowanym użytkownikiem Symfony, `flush()` na końcu.
- `symfony-app/src/Controller/ProfileController.php`: `POST /profile/phoenix-token` (zapis tokenu z CSRF `save_phoenix_token`) oraz `POST /profile/import-photos` (import z CSRF `import_photos`), flash z podsumowaniem `dodano / pominięto / łącznie z API`.
- `symfony-app/templates/profile/index.html.twig`: pole na token, przyciski „Zapisz token” i „Importuj zdjęcia z Phoenix” z tokenami CSRF.

[`73fa5690`](https://github.com/tehcarlos777/SymfonyApp/commit/73fa5690) Phoenix-api: limit and order photo index
- `phoenix-api/lib/phoenix_api_web/controllers/photo_controller.ex`: `GET /api/photos` — zmiana w zapytaniu: `order_by` rosnąco po `id`, `limit(500)` (`@photos_index_limit`).

[`HASH`](https://github.com/tehcarlos777/SymfonyApp/commit/HASH) Fix test env config for Symfony and Phoenix
- Ujednolicono uruchamianie testów w `README.md`:
  - Symfony: `APP_ENV=test` + `phpunit.xml.dist`.
  - Phoenix: `docker-compose run --rm` z `MIX_ENV=test`.
- W `docker-compose.yml` dodano dla Phoenix: `DB_HOST`, `DB_USER`, `DB_PASSWORD`.
- W `phoenix-api/config/test.exs` zastąpiono hardcoded dane DB konfiguracją z `System.get_env(...)`.

Propozycja do wdrożenia później:
  - Przejść na schemat `selector + verifier` zamiast pojedynczego hasha HMAC. Token przekazywany użytkownikowi miałby postać `selector.secret`.
  - W bazie trzymać tylko `selector` (indeksowany, jawny identyfikator) oraz `verifier_hash` liczony przez `password_hash(..., PASSWORD_ARGON2ID)`.
  - Przy logowaniu: wyszukiwać rekord po `selector`, a następnie robić `password_verify(secret, verifier_hash)`.
  - Dodać `expires_at`, `revoked_at`, `last_used_at` i odrzucać tokeny wygasłe/cofnięte oraz aktywne po rotacji.
  - Limiter prób logowania po IP/użytkowniku i pełny audyt zdarzeń auth (`success`/`fail`/`revoked`) w logach aplikacyjnych.
  - W `phoenix-api`: przechowywać `api_token` w bazie jako hash, zaktualizować seed (`priv/repo/seeds.exs`) oraz plug autoryzacji (`lib/.../authenticate.ex`), żeby nagłówek `access-token` był porównywany z hashem zamiast z plaintextem w kolumnie `users.api_token`.
  - Dodać paginację feedu (`?page=N`, `LIMIT`/`OFFSET` w `PhotoRepository`, linki prev/next w szablonie).
  - Zastąpić ręczną autoryzację sesji (`$session->get('user_id')` w kontrolerach) dedykowanym `App\Security\TokenAuthenticator extends AbstractAuthenticator` i przywrócić firewall w `security.yaml`. Dzięki temu Symfony samo wstrzykuje zalogowanego użytkownika przez `#[CurrentUser]` lub `getUser()`, a kontrolery przestają odpytywać sesję bezpośrednio.
  - Dodać migrację z indeksem `UNIQUE(user_id, photo_id)` na tabeli `likes` oraz owinąć zapis polubienia i aktualizację licznika w pojedynczą transakcję (`$em->wrapInTransaction(...)`). Bez tego race condition przy równoczesnych kliknięciach może zduplikować rekord lub dać błędny licznik.
  - Dodać pipeline CI (np. GitHub Actions), który przy każdym PR uruchamia `composer cs:check` i `composer test`. Narzędzia są już skonfigurowane — bez CI nikt ich nie uruchamia i standardy stopniowo się rozjeżdżają.
  - Internacjonalizacja (i18n): skonfigurować `translator` i locale (np. prefiks w URL lub wybór w sesji), zebrać stringi z Twig i kontrolerów do katalogów tłumaczeń (`messages.{locale}.yaml` / XLIFF) zamiast duplikować szablony na każdy język.
  - Dodać favicon do aplikacji