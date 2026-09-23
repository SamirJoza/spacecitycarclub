---
name: create-sage-gutenberg-block
description: Create or modify a Gutenberg block in a Sage 11 WordPress theme using Blade and Tailwind CSS.
---

# Create Sage Gutenberg Block Skill

Use this skill when creating or modifying a custom Gutenberg block in a Sage 11 WordPress theme.

## Goal

Create or update a Gutenberg block that follows existing project structure, uses Blade for markup, uses Tailwind CSS safely, and works in the WordPress block editor.

## Important Versioning Note

Do not bump the theme version while the block is still being actively built or adjusted.

Only bump the theme version after the block is complete and the user moves on to a separate task.

When the block is complete, use the `complete-theme-change` skill.

## Step 1: Inspect Existing Block Patterns

Before creating a new block, inspect existing block files.

Look for patterns in:

- `app/Blocks/`
- `app/Fields/`
- `resources/views/blocks/`
- `resources/css/`
- `resources/js/`

Follow the structure already used in the project.

Do not invent a new block architecture if one already exists.

## Step 2: Identify Block Registration System

Determine whether the project uses:

- ACF Composer
- Native ACF PHP field registration
- `block.json`
- A project-specific block loader

Use the existing system.

Do not mix systems unless the project already does.

## Step 3: Create or Update PHP Registration

When using ACF Composer, place block classes where the project already stores them.

Common example:

`app/Blocks/ExampleBlock.php`

The PHP class should:

- Register the block.
- Define fields.
- Use clear editor labels.
- Use simple editor-friendly instructions.
- Use sensible defaults.
- Avoid hard-coded project-specific content unless requested.

## Step 4: Create or Update Blade View

Use Blade for block markup.

Common example:

`resources/views/blocks/example-block.blade.php`

The Blade view should:

- Use semantic HTML.
- Use a clear wrapper class.
- Escape output properly.
- Handle empty optional fields safely.
- Avoid heavy PHP logic.
- Preserve existing theme conventions.

## Step 5: Styling

Use Tailwind utilities first.

Use project tokens and CSS variables for reusable design values.

Scope custom CSS to the block wrapper.

Do not create broad global selectors that could affect unrelated blocks or templates.

## Step 6: Editor Experience

Make the block easy for editors to use.

Field labels should be clear.

Instructions should be plain and useful.

Use conditional logic only when it makes editing easier.

Avoid making editors manually enter values the theme can handle safely.

## Step 7: Accessibility

The block should:

- Use proper heading structure.
- Use buttons for actions.
- Use links for navigation.
- Include accessible labels where needed.
- Preserve keyboard interaction.
- Respect reduced motion when adding animation.

## Step 8: Final Review

Before responding:

- Provide full file paths.
- Provide complete file contents.
- Confirm frontend output was considered.
- Confirm Gutenberg editor output was considered.
- Confirm styling is scoped.
- Confirm no version bump was made unless the block is complete.