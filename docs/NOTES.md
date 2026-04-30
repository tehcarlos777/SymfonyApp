### Zadanie 1 - zadbaj o jakość kodu oraz rozwiązań w projekcie SymfonyApp.

Moje commity zwiazane z zad 1:

[`0d8fcae1`](https://github.com/tehcarlos777/SymfonyApp/commit/0d8fcae1) Add php-cs-fixer PSR-12 setup for Symfony app
  - Dodano `friendsofphp/php-cs-fixer` do `require-dev` oraz zaktualizowano `composer.lock`, aby zapewnić spójne wersje narzędzi lokalnie i w CI.
  - Dodano `symfony-app/.php-cs-fixer.dist.php` z regułą `@PSR12`, żeby styl kodu był jednoznacznie zdefiniowany w repozytorium, a nie w ustawieniach IDE.
  - Dodano skrypty `composer cs:check` i `composer cs:fix`, żeby łatwo uruchamiać kontrolę i automatyczne poprawki lokalnie oraz w pipeline.
  - Dodano `**/.php-cs-fixer.cache` do `.gitignore`, bo to plik lokalny i nie powinien trafiać do kontroli wersji.

[`17c0f2f`](https://github.com/tehcarlos777/SymfonyApp/commit/17c0f2f) Fix SQL injection risk in auth login query flow.
  - Zastąpiono interpolację stringa w zapytaniach SQL metodami Doctrine ORM (`findOneBy`), eliminując ryzyko SQL injection w procesie logowania.
  - Usunięto zależność od `Doctrine\DBAL\Connection` na rzecz `EntityManagerInterface`, co jest zgodne ze standardami Symfony.
  - Zapytania operują teraz na encjach (`AuthToken`, `User`), a dane sesji są ustawiane przez gettery encji, a nie przez surowe tablice z bazy.