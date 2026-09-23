---
name: update-existing-theme-file
description: Safely update an existing WordPress Sage theme file while preserving structure and returning the complete file.
---

# Update Existing Theme File Skill

Use this skill when modifying an existing file in a WordPress Sage theme.

## Goal

Make the requested change with the smallest safe edit while preserving existing project structure and returning the full updated file.

## Step 1: Read the Existing File

Open the current file before making changes.

Treat the current file as the source of truth.

Do not reconstruct the file from memory.

## Step 2: Identify the Requested Change

Determine exactly what the user asked to change.

Do not expand the task.

Do not perform unrelated cleanup.

Do not refactor unrelated code.

## Step 3: Preserve Existing Structure

Preserve existing:

- File organization
- Comments
- Naming
- Classes
- Functions
- Hooks
- Filters
- Blade structure
- CSS selectors
- Design tokens
- Accessibility behavior

Only change what is necessary.

## Step 4: Verify Stack Fit

Make sure the change fits:

- WordPress
- Sage 11
- Blade
- Tailwind CSS
- Existing project conventions

If a plugin API is involved, verify the correct hook, filter, class, or method before changing code.

## Step 5: Return Complete File

Return:

- Full file path.
- Complete updated file contents.

Do not return only a snippet.

Do not return only a diff.

Do not omit unchanged sections.

## Step 6: Versioning Check

Do not bump the theme version automatically for a small adjustment inside an active task.

Only use the `complete-theme-change` skill when the completed change set is finalized.

## Step 7: Final Review

Before responding:

- Confirm the requested change was made.
- Confirm no unrelated changes were introduced.
- Confirm the full file was returned.
- Mention any assumptions briefly.