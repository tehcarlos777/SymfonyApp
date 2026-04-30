### Zadanie 1 - zadbaj o jakość kodu oraz rozwiązań w projekcie SymfonyApp.

Moje commity zwiazane z zad 1:

[`0d8fcae1`](https://github.com/tehcarlos777/SymfonyApp/commit/0d8fcae1) Add php-cs-fixer PSR-12 setup for Symfony app
  - Dodano `friendsofphp/php-cs-fixer` do `require-dev` oraz zaktualizowano `composer.lock`, aby zapewnić spójne wersje narzędzi lokalnie i w CI.
  - Dodano `symfony-app/.php-cs-fixer.dist.php` z regułą `@PSR12`, żeby styl kodu był jednoznacznie zdefiniowany w repozytorium, a nie w ustawieniach IDE.
  - Dodano skrypty `composer cs:check` i `composer cs:fix`, żeby łatwo uruchamiać kontrolę i automatyczne poprawki lokalnie oraz w pipeline.
  - Dodano `**/.php-cs-fixer.cache` do `.gitignore`, bo to plik lokalny i nie powinien trafiać do kontroli wersji.