
---

## `.cursor/skills/run-theme-quality-check/SKILL.md`

```md
---
name: run-theme-quality-check
description: Run the correct quality checks for a WordPress Sage 11 theme without assuming Composer is globally installed.
---

# Run Theme Quality Check Skill

Use this skill when verifying a completed WordPress Sage 11 theme change.

This skill checks the project safely without assuming Composer is installed globally.

## Goal

Verify the current theme work using the commands documented by the project.

Primary reference:

`.cursor/project-commands.md`

## Step 1: Read Project Commands

Open:

`.cursor/project-commands.md`

Use it as the source of truth for available commands.

Do not guess commands.

Do not invent npm scripts.

Do not run Composer commands from the normal Mac terminal.

## Step 2: Confirm Current Directory

Before running npm commands, confirm the current directory is the Sage theme root.

The theme root should usually contain:

- `package.json`
- `vite.config.js`
- `resources/`
- `app/`
- `style.css`

Use:

```bash
pwd
ls