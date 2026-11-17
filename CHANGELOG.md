# Changelog

## [Unreleased]

## [6.1.1] - 2026-11-17

### Fixed

- The Packages list excludes the built-in core entry from the total and pagination. The total matches the visible packages, and row numbers are consecutive.

## [6.1.0] - 2026-11-17

### Changed

- The Localise component and installation package moved to version 6.1.0 to reflect the Joomla 6 support and language package features introduced in 6.0.1.

## [6.0.1] - 2026-11-17

### Added

- Automatic checks compare an extension language package's files with its last export after Joomla updates the linked extension.
- The package list shows the check result, check time, and which extension triggered an automatic check. **Review file changes** shows new, missing, and changed files and preserves saved file selections.
- The installation ZIP includes the Localise component and the automatic file check plugin.

### Fixed

- The Languages, Translations, and Packages views work on Joomla 6.2.0 with the Joomla 6 backward compatibility plugin disabled.
- Translation saves report file write failures in Joomla.

[Unreleased]: https://github.com/sgagner/localise-studio/compare/6.1.1...localise-studio
[6.1.1]: https://github.com/sgagner/localise-studio/releases/tag/6.1.1
[6.1.0]: https://github.com/sgagner/localise-studio/releases/tag/6.1.0
[6.0.1]: https://github.com/sgagner/localise-studio/releases/tag/6.0.1
