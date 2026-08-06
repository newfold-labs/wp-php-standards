# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project follows
[semantic versioning](#versioning).

## Unreleased

### Fixed

- `Newfold.PHP.ForbiddenDoubleColonClass` now reports chained `::class::` fetches
  it used to walk past: `Thing::CLASS::FOO`, since PHP accepts any casing of the
  keyword; `Thing::class ::FOO`, since whitespace between the operators is legal;
  and `Thing::class::$prop` and `Thing::class::{$name}`, which the sniff skipped
  because it insisted on a plain name after the second operator. All four parse
  only on PHP 8.3 and up.

  This is a breaking change. Code that passed before can fail now.

- `Newfold.PHP.ForbiddenUnionType` now reports union types it used to walk past.
  It only visited pipes reachable from a parameter list or a return type, and its
  list of type tokens had no entry for `self`, `static` or `parent`. Property
  types, relative type names, disjunctive normal form types such as
  `(A&B)|null`, and unions on typed class constants all went unreported. Every
  one of them is a parse error on PHP 7, so the file would fatal rather than fail
  at the call site.

  This is a breaking change. Code that passed before can fail now.

### Changed

- `WordPress.NamingConventions.ValidHookName` is excluded from the standard. It
  expects lowercase underscore-separated hook names, so it reported every hook
  named to our own convention.

- Pinned every dependency to an explicit range. All four were `@stable`, which
  accepts any future major. A new PHP_CodeSniffer, WPCS or PHPCompatibility major
  would have landed in every consumer's CI on the next `composer update` with no
  deprecation window. The pins resolve to the same versions as before, so this is
  no change in behaviour today.

### Added

- `Newfold.NamingConventions.ValidHookName`, which checks that hooks we fire are
  named `newfold/[context]/[action|filter]/[name]`. It reports a name with the
  wrong number of segments, a context that is not a lowercase slug, a type that is
  neither `action` nor `filter`, a name that is not camel case, and a `do_action()`
  firing a hook named as a filter or the other way round. Names assembled at
  runtime are checked as far as they are known: an embedded value stands in for one
  segment, so the rest of the name is still checked.

  A missing `newfold/` prefix is reported as a warning rather than an error,
  because the hook naming standard and the PHP standard do not currently agree on
  what an unprefixed hook name means. Hooks that cannot be renamed can be listed in
  the sniff's `allowed_hook_names` property.

  This is a breaking change. Code that passed before can fail now.

- `phpcsstandards/phpcsutils` as an explicit dependency. It was already installed
  transitively through WPCS; the custom sniffs are moving onto it, so it is a
  direct dependency now.
- This changelog, and a documented versioning policy in the readme.
- A test suite for the custom sniffs, run by `composer run test`, plus a CI
  workflow covering PHP 7.4 through 8.4. The sniffs branch on tokenizer
  differences between PHP 7 and PHP 8, so the matrix spans both.
- `phpcs.xml.dist`, so the repository can be linted with the standard it ships.

## 1.2.6 - 2026-05-11

### Added

- `Newfold.PHP.ForbiddenUnionType`, which flags PHP 8 union type declarations
  when the configured `testVersion` includes PHP 7.

### Changed

- `PHPCompatibilityHelper::is_min_test_version_php_8_or_newer()` caches its
  result instead of re-parsing `testVersion` on every token.

## 1.2.5 - 2025-03-19

### Added

- `Newfold.PHP.ForbiddenDoubleColonClass`, which flags `::class::` usage that
  does not parse on PHP 7.
- `PHPCompatibilityHelper`, so sniffs can skip themselves when `testVersion`
  starts at PHP 8 or newer.

## 1.2.4 - 2024-07-22

### Changed

- Test directories are no longer excluded from the scan.

## 1.2.3 - 2024-04-22

### Changed

- `testVersion` default raised to `7.3-`.

## 1.2.2 - 2023-01-06

### Changed

- Dependencies moved from `require-dev` to `require`, so consuming projects get
  the sniffs they need without extra setup.

## 1.2.1 - 2023-01-05

### Changed

- Ruleset renamed to `Newfold`, which is the name consumers pass to
  `--standard`.

## 1.2 - 2023-01-04

### Added

- Initial release under the `newfold-labs` organisation, published through the
  Satis index.

## Versioning

The version number describes what a consumer has to do to upgrade.

- **Major**: code that passed before can fail now. A new sniff, a rule promoted
  from warning to error, or a stricter default all land here.
- **Minor**: new capability that does not fail existing code. A new sniff that
  ships disabled, or a dependency range widening.
- **Patch**: fixes that do not change which code passes, such as a false positive
  being removed or a message being reworded.

Fixing a sniff that was missing violations is a major bump. The sniff is more
correct, but a repository that passed yesterday can fail today, and that is what
the version number needs to warn about.
