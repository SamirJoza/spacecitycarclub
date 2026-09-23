
---

## `.cursor/skills/debug-theme-issue/SKILL.md`

```md
---
name: debug-theme-issue
description: Debug WordPress Sage 11 theme issues safely by identifying the error type, checking relevant files, and applying the smallest safe fix.
---

# Debug Theme Issue Skill

Use this skill when debugging an issue in a WordPress Sage 11 theme.

The goal is to identify the actual cause before changing code.

## Goal

Debug the issue safely and avoid broad rewrites.

Focus on:

- Reading the error.
- Identifying the system involved.
- Checking the smallest relevant set of files.
- Applying the smallest safe fix.
- Preserving existing project structure.

## Step 1: Capture the Error

Start with the exact error message.

Look for:

- PHP fatal errors
- Blade rendering errors
- Acorn container errors
- Vite build errors
- Tailwind errors
- JavaScript console errors
- WordPress admin errors
- Plugin hook or filter errors
- Missing class errors
- Autoload errors
- 404 or rewrite issues
- White screen or blank output

Do not guess the fix before reading the error.

## Step 2: Identify the Error Type

Classify the issue.

Common categories:

### PHP Error

Likely files:

- `app/`
- `functions.php`
- Composer-loaded files
- Service providers
- Support classes
- Block registration classes

### Blade Error

Likely files:

- `resources/views/`
- `resources/views/partials/`
- `resources/views/components/`
- `resources/views/blocks/`
- View composers

### Acorn or Sage Error

Likely files:

- `app/Providers/`
- `app/View/Composers/`
- `composer.json`
- Theme boot files
- Service container bindings

### Vite or Build Error

Likely files:

- `package.json`
- `vite.config.js`
- `resources/js/`
- `resources/css/`
- Tailwind-related CSS files

### Tailwind Error

Likely files:

- `resources/css/`
- CSS entry files
- Tailwind imports
- Utility class usage
- Theme token CSS

### WordPress Admin or Hook Error

Likely files:

- `app/`
- Admin support classes
- Hook registration files
- Plugin integration files

### Rewrite or 404 Issue

Likely causes:

- Custom post type rewrite settings
- Taxonomy rewrite settings
- Endpoint registration
- Permalink rules

WP-CLI rewrite flushing should only be done inside Local's Site Shell.

## Step 3: Check Relevant Files Only

Inspect the files directly related to the error.

Do not scan or rewrite unrelated areas.

Do not change unrelated files.

Do not refactor code while debugging unless the refactor is required to fix the issue.

## Step 4: Respect Command Context

Read:

`.cursor/project-commands.md`

before running commands.

Normal Mac terminal is for npm and Vite work.

Local Site Shell is for Composer, PHP, and WP-CLI work.

Do not run Composer from the normal Mac terminal.

## Step 5: Common Debug Commands

Use normal Mac terminal for frontend/build issues:

```bash
npm run build