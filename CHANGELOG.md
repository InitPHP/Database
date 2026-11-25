# Changelog

All notable changes to `initphp/database` are documented here. The format is based
on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [5.0.1] - 2026-11-25

### Documentation

- The DataTables pages and the Bootstrap recipe state that DataTables.js inserts
  cell values as HTML: text columns use `$.fn.dataTable.render.text()`, PHP
  renderers escape with `htmlspecialchars()`, and the endpoint answers with
  `application/json; charset=utf-8`.
- The upsert recipe validates the table, the conflict column and the keys of the
  row as plain identifiers before interpolating them.
- Installation and the README name php-cs-fixer and PER-CS instead of phpcs and
  PSR-12.

## [5.0.0] - 2026-11-22

### Changed (breaking)

- Requires PHP `^8.2` (was `^8.1`); PHP 8.1 is no longer supported. CI now
  covers PHP 8.2, 8.3, 8.4 and 8.5.
- Code style moved to PER-CS (php-cs-fixer, shared `.php-cs-fixer.dist.php`),
  replacing PHP_CodeSniffer (PSR-12); the codebase has been reformatted
  accordingly. The `cs`, `cs-ci` and `cs-fix` Composer scripts now run
  php-cs-fixer, and `cs-check` was added.
