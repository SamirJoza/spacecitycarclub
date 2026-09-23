---
name: initialize-sage-theme
description: Initialize a reusable WordPress Sage 11 theme workflow using project settings, style.css, and CHANGELOG.md.
---

# Initialize Sage Theme Skill

Use this skill when setting up a new WordPress Sage 11 theme project or when adding the standard workflow files to an existing Sage 11 project for the first time.

This skill is for initial setup only.

Do not use this skill for normal feature work, bug fixes, template updates, block creation, or completed change tracking.

For completed change tracking, use the `complete-theme-change` skill.

## Goal

Prepare the project for consistent WordPress Sage theme development by verifying or creating:

- `.cursor/project-settings.md`
- `style.css`
- `CHANGELOG.md`

The initial theme version should be:

`1.0.0`

Do not bump the theme version during initial setup.

## Step 1: Read Project Settings

Open:

`.cursor/project-settings.md`

Use this file as the source of truth for project identity and theme metadata.

Required values:

- Project Name
- Project URL
- Theme Name
- Theme URI
- Description
- Initial Version
- Author
- Author URI
- Text Domain
- License
- License URI
- Requires PHP
- Requires at least

If `Project Name` or `Project URL` is missing or still a placeholder, ask for those values before creating the final `style.css`.

Do not guess project identity values.

## Step 2: Verify Sage Theme Structure

Confirm the project appears to be a Sage 11 theme.

Common expected files and folders may include:

- `app/`
- `resources/`
- `resources/views/`
- `resources/css/`
- `resources/js/`
- `composer.json`
- `package.json`
- `vite.config.js`
- `style.css`

Do not create a full Sage install from scratch unless the user explicitly asks for that.

This skill prepares metadata and workflow files. It does not replace the official Sage installation process.

## Step 3: Create or Verify style.css

Open or create:

`style.css`

The file should contain the standard WordPress theme header using values from `.cursor/project-settings.md`.

Required format:

```css
/*
Theme Name:         [Project Name] - Theme
Theme URI:          [Project URL]
Description:        Theme based on Sage 11.0.1
Version:            1.0.0
Author:             Samir Joza
Author URI:         https://samirjoza.dev
Text Domain:        sage
License:            MIT License
License URI:        https://opensource.org/licenses/MIT
Requires PHP:       8.2
Requires at least:  6.6
*/