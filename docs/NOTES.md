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