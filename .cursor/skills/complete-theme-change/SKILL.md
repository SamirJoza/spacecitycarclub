---
name: complete-theme-change
description: Finalize a completed WordPress Sage theme change by bumping style.css and updating CHANGELOG.md.
---

# Complete Theme Change Skill

Use this skill when a completed WordPress Sage theme change is ready to be recorded.

Do not use this skill for every small adjustment during an active task.

Use this only when the current feature, fix, block, template, component, or integration is complete.

## Goal

Finalize a completed change set by:

1. Reading the project settings.
2. Reading the current theme version from `style.css`.
3. Bumping the version correctly.
4. Updating `CHANGELOG.md`.
5. Confirming the version and changelog match.

## Files Involved

Primary files:

- `.cursor/project-settings.md`
- `style.css`
- `CHANGELOG.md`

Do not update unrelated files as part of this skill unless the completed change itself already required those files.

## Step 1: Read Project Settings

Open:

`.cursor/project-settings.md`

Use it as the source of truth for project metadata.

Confirm the project has valid values for:

- Project Name
- Project URL
- Theme Name
- Theme URI
- Author
- Author URI
- Text Domain
- License
- Required PHP version
- Required WordPress version

If required values are missing or still placeholders, ask for the missing values before creating final theme metadata.

## Step 2: Read Current Version

Open:

`style.css`

Find the root WordPress theme header.

Locate:

`Version:            X.X.X`

Use this as the current version.

## Step 3: Choose Version Bump

Use patch for:

- Bug fixes
- Styling fixes
- Accessibility fixes
- Copy updates
- Template adjustments
- Small behavior corrections

Use minor for:

- Completed new Gutenberg blocks
- Completed new templates
- Completed reusable components
- Completed theme sections
- Completed integrations
- Completed features

Use major only for:

- Breaking changes
- Major architecture changes
- Major compatibility changes

When unsure, use patch.

## Step 4: Update style.css

Update only the `Version:` field unless the user requested additional metadata changes.

Example:

`Version:            1.0.1`

Do not rewrite unrelated header fields.

## Step 5: Update CHANGELOG.md

Open or create:

`CHANGELOG.md`

Add the new version entry near the top, directly below the `# Changelog` heading and intro text.

Use the current date.

Format:

`## [1.0.1] - YYYY-MM-DD`

Use only the sections that apply:

- Added
- Changed
- Fixed
- Removed
- Security

Write changelog bullets in clear, plain language.

Do not log every adjustment from the same active task.

Only log the completed change set.

## Step 6: Verify

Before responding:

- Confirm `style.css` and `CHANGELOG.md` use the same version.
- Confirm the changelog entry describes the completed work.
- Confirm the version bump happened only once.
- Confirm unrelated files were not changed.