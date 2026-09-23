# Project Settings

This file stores project-specific identity and theme metadata for this WordPress Sage theme.

Cursor should read this file before creating or updating `style.css`, `CHANGELOG.md`, or any theme metadata.

---

## Project Identity

Project Name: Space City Car Club
Project URL: https://spacecitycarclub.com

---

## Theme Metadata

Theme Name: Space City Car Club - Theme
Theme URI:  https://spacecitycarclub.com
Description: Theme based on Sage 11.0.1
Initial Version: 1.0.0
Author: Samir Joza
Author URI: https://samirjoza.dev
Text Domain: sage
License: MIT License
License URI: https://opensource.org/licenses/MIT
Requires PHP: 8.2
Requires at least: 6.6

---

## Theme Versioning

Theme version source of truth: style.css
Changelog source of truth: CHANGELOG.md

The theme version starts at:

1.0.0

The official theme version must always be stored in the `Version:` field inside the root `style.css` theme header.

Do not use `package.json`, `composer.json`, lock files, or compiled asset filenames as the official WordPress theme version unless specifically instructed.

---

## Version Bump Rules

Use semantic versioning.

Patch version examples:

- Styling fixes
- Template fixes
- Accessibility fixes
- Copy updates
- Small bug fixes
- Small behavior corrections

Example:

1.0.0 → 1.0.1

Minor version examples:

- Completed Gutenberg blocks
- Completed templates
- Completed reusable components
- Completed theme sections
- Completed integrations

Example:

1.0.1 → 1.1.0

Major version examples:

- Breaking changes
- Major architecture changes
- Large compatibility changes

Example:

1.1.0 → 2.0.0

---

## Important Iteration Rule

Do not bump the theme version for every small adjustment during an active task.

For example, if a Gutenberg block is being created and there are multiple rounds of visual, spacing, field, or layout adjustments, do not bump the version for each iteration.

Only bump the theme version when the full task is complete and work moves on to another separate feature, fix, template, block, or integration.

A version bump should represent a completed change set, not every individual edit.

---

## Changelog Rules

Maintain a root `CHANGELOG.md` file.

Every completed version bump must have a matching changelog entry.

The changelog entry must use the same version number as `style.css`.

Use this format:

```md
# Changelog

## [1.0.1] - YYYY-MM-DD

### Added

- Description of newly added functionality.

### Changed

- Description of completed changes.

### Fixed

- Description of completed fixes.