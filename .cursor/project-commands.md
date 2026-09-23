# Project Commands

This file documents the commands used for this WordPress Sage 11 theme project.

Cursor should read this file before running any terminal commands.

Do not assume Composer is installed globally on the Mac.

Do not run Composer commands from the normal Mac terminal.

Composer, PHP, and WP-CLI commands should only be considered available inside Local's "Site shell" when the website is running.

---

## Command Contexts

There are two command contexts for this project.

### 1. Normal Mac Terminal

Use this for frontend/theme asset work.

Typical use:

- Installing Node dependencies
- Running the Vite development server
- Creating production builds
- Running npm-based checks when available

Run these commands from the Sage theme root.

The Sage theme root is the folder that contains:

- `package.json`
- `vite.config.js`
- `resources/`
- `app/`
- `style.css`

### 2. Local Site Shell

Use this only when PHP, WordPress, Composer, or WP-CLI work is required.

Local Site Shell is opened from the Local app while the website is running.

Do not assume these commands work from the normal Mac terminal.

---

# Normal Theme Workflow

## Check Current Folder

Use this before running project commands if the current location is uncertain.

```bash
pwd
ls