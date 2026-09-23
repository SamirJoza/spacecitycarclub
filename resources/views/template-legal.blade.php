{{--
  File: resources/views/template-legal.blade.php

  Template Name: Legal Document

  Purpose:
  This Sage Blade template is designed for long-form legal content such as:
  - Terms and Conditions
  - Privacy Policy
  - Refund Policy
  - Member Rules
  - Event Rules

  Why this template exists:
  Default Gutenberg text blocks work fine for short pages, but legal pages need a stronger
  reading structure. This template keeps the page editable in Gutenberg while adding:
  - A polished legal document header
  - Editable ACF-powered legal intro/meta fields
  - A readable content column
  - Automatic section anchors from H2 headings
  - A sticky desktop table of contents without its own scrollbar
  - A mobile jump menu
  - Optional duplicate body heading removal
  - Theme-aware Space City Car Club styling
  - Scoped CSS so the legal styles do not leak into other templates

  Important rendering note:
  Gutenberg content is already rendered through WordPress using the_content filters.
  Do not wrap the rendered block content in wp_kses_post() afterward. Some custom
  blocks legitimately output inline <style> tags, and sanitizing the final rendered
  content can strip the <style> wrapper while leaving the CSS text visible on-page.

  Assumptions:
  - The legal page content is still managed in the Gutenberg editor.
  - Main legal sections use H2 blocks.
  - The body content should not need its own duplicate page title heading.
  - The legal hero title, intro, last updated text, applies-to text, and bottom note
    are managed through the Legal Document Settings ACF field group.
--}}

@extends('layouts.app')

@section('content')
  @while(have_posts())
    @php
      /*
      |--------------------------------------------------------------------------
      | WordPress Loop Setup
      |--------------------------------------------------------------------------
      |
      | Keeping the loop directive and the PHP call separate avoids Blade parser
      | issues in this Sage/Acorn environment.
      |
      */

      the_post();

      /*
      |--------------------------------------------------------------------------
      | Page Data
      |--------------------------------------------------------------------------
      |
      | These values keep the legal template reusable across Terms, Privacy,
      | Refund Policy, Member Rules, and similar pages.
      |
      */

      $post_id = get_the_ID();
      $page_title = get_the_title($post_id);

      /*
      |--------------------------------------------------------------------------
      | ACF Helper
      |--------------------------------------------------------------------------
      |
      | This helper keeps ACF usage safe. It returns a fallback if ACF is not
      | available or if the field is empty.
      |
      */

      $get_legal_field = static function (string $field_name, mixed $fallback = '') use ($post_id): mixed {
        if (! function_exists('get_field')) {
          return $fallback;
        }

        $value = get_field($field_name, $post_id);

        if (is_string($value)) {
          $value = trim($value);
        }

        if ($value === null || $value === '') {
          return $fallback;
        }

        return $value;
      };

      /*
      |--------------------------------------------------------------------------
      | Editable Legal Header Values
      |--------------------------------------------------------------------------
      |
      | These fields come from app/Fields/LegalDocumentSettings.php.
      | Each value has a safe fallback so the template still works before the
      | page has been saved with the new ACF field group.
      |
      */

      $legal_eyebrow = (string) $get_legal_field(
        'legal_document_eyebrow',
        'Space City Car Club Legal Document'
      );

      $legal_title = (string) $get_legal_field(
        'legal_document_title',
        $page_title
      );

      $legal_intro_fallback = has_excerpt($post_id)
        ? get_the_excerpt($post_id)
        : 'This legal document outlines important rules, responsibilities, and expectations related to Space City Car Club, membership, events, website use, and related services.';

      $legal_intro = (string) $get_legal_field(
        'legal_document_intro',
        $legal_intro_fallback
      );

      $legal_updated_date = (string) $get_legal_field(
        'legal_document_last_updated',
        get_the_modified_date('F j, Y', $post_id)
      );

      $legal_applies_to = (string) $get_legal_field(
        'legal_document_applies_to',
        'Applies to members, visitors, event participants, and website users'
      );

      $legal_bottom_note_title = (string) $get_legal_field(
        'legal_document_bottom_note_title',
        'Questions about this document?'
      );

      $legal_bottom_note_text = (string) $get_legal_field(
        'legal_document_bottom_note_text',
        'Please contact Space City Car Club if you need clarification about these terms, membership rules, event policies, or website-related questions.'
      );

      /*
      |--------------------------------------------------------------------------
      | Duplicate Body Heading Setting
      |--------------------------------------------------------------------------
      |
      | Enabled by default. This removes only the first duplicate body heading,
      | not every heading. This keeps the legal sections intact while avoiding
      | a repeated "Terms and Conditions" title inside the content card.
      |
      */

      $strip_heading_raw = function_exists('get_field')
        ? get_field('legal_document_strip_first_heading', $post_id, false)
        : null;

      $strip_first_body_heading = ($strip_heading_raw === null || $strip_heading_raw === '')
        ? true
        : (bool) $strip_heading_raw;

      /*
      |--------------------------------------------------------------------------
      | Render Gutenberg Content
      |--------------------------------------------------------------------------
      |
      | We let WordPress render the Gutenberg content first so core blocks, embeds,
      | lists, inline styles from custom blocks, and normal editor formatting still
      | work as expected.
      |
      */

      $legal_content = apply_filters('the_content', get_the_content());

      /*
      |--------------------------------------------------------------------------
      | Remove Duplicate First Body Heading
      |--------------------------------------------------------------------------
      |
      | This removes:
      | - The first H1, because legal pages should not need a body H1 when the
      |   template already renders the hero title.
      | - Or the first H1-H6 if its text matches the page title or ACF document title.
      |
      | It does not remove ordinary H2 legal sections unless that first section is
      | just a duplicate of the document title.
      |
      */

      if ($strip_first_body_heading) {
        $normalize_heading_text = static function (string $value): string {
          $decoded = html_entity_decode(
            wp_strip_all_tags($value),
            ENT_QUOTES,
            get_bloginfo('charset') ?: 'UTF-8'
          );

          $decoded = preg_replace('/^\d+\s*[\.\)]\s*/', '', $decoded);
          $decoded = str_replace('&', 'and', $decoded);
          $decoded = preg_replace('/\s+/', ' ', trim((string) $decoded));

          return strtolower($decoded);
        };

        $title_candidates = array_filter([
          $normalize_heading_text($page_title),
          $normalize_heading_text($legal_title),
        ]);

        $legal_content = preg_replace_callback(
          '/^\s*<h([1-6])([^>]*)>(.*?)<\/h\1>\s*/is',
          static function ($matches) use ($normalize_heading_text, $title_candidates) {
            $heading_level = (string) ($matches[1] ?? '');
            $heading_text = $normalize_heading_text((string) ($matches[3] ?? ''));

            $is_h1 = $heading_level === '1';
            $matches_document_title = in_array($heading_text, $title_candidates, true);

            if ($is_h1 || $matches_document_title) {
              return '';
            }

            return $matches[0];
          },
          $legal_content,
          1
        );
      }

      /*
      |--------------------------------------------------------------------------
      | Automatic Table of Contents
      |--------------------------------------------------------------------------
      |
      | Every H2 in the Gutenberg content becomes:
      | - A readable section heading
      | - An anchor target
      | - A TOC item
      |
      | This lets the editor stay simple while the frontend gets a proper legal
      | document layout.
      |
      */

      $toc_items = [];
      $used_ids = [];
      $section_index = 0;

      $legal_content = preg_replace_callback('/<h2([^>]*)>(.*?)<\/h2>/is', function ($matches) use (&$toc_items, &$used_ids, &$section_index) {
        $attributes = $matches[1] ?? '';
        $inner_html = $matches[2] ?? '';

        $raw_heading_text = trim(
          html_entity_decode(
            wp_strip_all_tags($inner_html),
            ENT_QUOTES,
            get_bloginfo('charset') ?: 'UTF-8'
          )
        );

        if ($raw_heading_text === '') {
          return $matches[0];
        }

        /*
        |--------------------------------------------------------------------------
        | Clean Heading Label
        |--------------------------------------------------------------------------
        |
        | If the Gutenberg heading already starts with "1.", "2.", etc., remove
        | that from the displayed heading because this template adds its own
        | styled number badge.
        |
        */

        $clean_heading_text = trim(
          preg_replace('/^\d+\s*[\.\)]\s*/', '', $raw_heading_text)
        );

        $heading_label = $clean_heading_text !== ''
          ? $clean_heading_text
          : $raw_heading_text;

        /*
        |--------------------------------------------------------------------------
        | Anchor ID Handling
        |--------------------------------------------------------------------------
        |
        | If the editor already supplied an ID, use it. Otherwise generate one
        | from the heading label. Duplicate IDs are made unique safely.
        |
        */

        $existing_id = '';

        if (preg_match('/\sid=(["\'])(.*?)\1/i', $attributes, $id_match)) {
          $existing_id = sanitize_title($id_match[2]);
        }

        $base_id = $existing_id !== ''
          ? $existing_id
          : sanitize_title($heading_label);

        if ($base_id === '') {
          $base_id = 'legal-section-' . ($section_index + 1);
        }

        $id = $base_id;
        $duplicate_index = 2;

        while (isset($used_ids[$id])) {
          $id = $base_id . '-' . $duplicate_index;
          $duplicate_index++;
        }

        $used_ids[$id] = true;
        $section_index++;

        $section_number = str_pad((string) $section_index, 2, '0', STR_PAD_LEFT);

        $toc_items[] = [
          'id' => $id,
          'label' => $heading_label,
          'number' => $section_number,
        ];

        /*
        |--------------------------------------------------------------------------
        | Preserve Existing Heading Classes
        |--------------------------------------------------------------------------
        |
        | Existing Gutenberg classes are kept. We append our legal heading class
        | instead of replacing editor-generated classes.
        |
        */

        $attributes = preg_replace('/\s+id=(["\']).*?\1/i', '', $attributes);

        if (preg_match('/\sclass=(["\'])(.*?)\1/i', $attributes, $class_match)) {
          $updated_class_attribute = ' class="' . esc_attr(trim($class_match[2] . ' sccc-legal-heading')) . '"';
          $attributes = preg_replace('/\sclass=(["\']).*?\1/i', $updated_class_attribute, $attributes, 1);
        } else {
          $attributes .= ' class="sccc-legal-heading"';
        }

        return sprintf(
          '<h2 id="%1$s"%2$s><span class="sccc-legal-heading__number">%3$s</span><span class="sccc-legal-heading__text">%4$s</span></h2>',
          esc_attr($id),
          $attributes,
          esc_html($section_number),
          esc_html($heading_label)
        );
      }, $legal_content);
    @endphp

    <style>
      /*
      |--------------------------------------------------------------------------
      | Space City Car Club Legal Template Styles
      |--------------------------------------------------------------------------
      |
      | These styles are scoped to .sccc-legal-template so they only affect pages
      | using this template. The CSS leans on existing project tokens when present,
      | but includes safe fallbacks so the template remains resilient.
      |
      */

      .sccc-legal-template {
        --sccc-legal-bg: var(--color-background, var(--color-bg, #080f1c));
        --sccc-legal-surface: var(--color-surface, #101827);
        --sccc-legal-surface-soft: color-mix(in oklab, var(--sccc-legal-surface) 82%, transparent);
        --sccc-legal-text: var(--color-text, #f8fafc);
        --sccc-legal-muted: var(--color-muted, #a6b0c3);
        --sccc-legal-line: var(--color-line, rgba(255, 255, 255, 0.12));
        --sccc-legal-primary: var(--color-primary-500, #2979ff);
        --sccc-legal-accent: var(--color-accent-500, #71d7ff);
        --sccc-legal-danger: var(--color-secondary-500, #ff1744);
        --sccc-legal-display: var(--font-display, var(--font-headline, system-ui, sans-serif));
        --sccc-legal-body: var(--font-body, system-ui, sans-serif);
        --sccc-legal-radius: 1.25rem;
        --sccc-legal-radius-lg: 1.75rem;
        --sccc-legal-shadow: 0 24px 60px rgb(0 0 0 / 0.28);

        position: relative;
        isolation: isolate;
        padding-block: clamp(2.5rem, 5vw, 5rem);
        color: var(--sccc-legal-text);
        background:
          radial-gradient(900px 420px at 10% 0%, color-mix(in oklab, var(--sccc-legal-primary) 18%, transparent), transparent 62%),
          radial-gradient(760px 380px at 90% 8%, color-mix(in oklab, var(--sccc-legal-danger) 12%, transparent), transparent 66%),
          var(--sccc-legal-bg);
        font-family: var(--sccc-legal-body);
      }

      html[data-theme="light"] .sccc-legal-template {
        --sccc-legal-bg: var(--color-background, #f5f7fb);
        --sccc-legal-surface: var(--color-surface, #ffffff);
        --sccc-legal-text: var(--color-text, #172033);
        --sccc-legal-muted: var(--color-muted, #5f6b7a);
        --sccc-legal-line: var(--color-line, rgba(23, 32, 51, 0.14));
        --sccc-legal-shadow: 0 24px 60px rgb(15 23 42 / 0.12);
      }

      .sccc-legal-template a {
        color: var(--sccc-legal-accent);
        text-underline-offset: 0.2em;
        text-decoration-thickness: 0.08em;
      }

      html[data-theme="light"] .sccc-legal-template a {
        color: var(--sccc-legal-primary);
      }

      .sccc-legal-template a:hover {
        color: var(--sccc-legal-danger);
      }

      .sccc-legal-wrap {
        width: min(calc(100% - 2rem), 1320px);
        margin-inline: auto;
      }

      /*
      |--------------------------------------------------------------------------
      | Legal Hero
      |--------------------------------------------------------------------------
      |
      | The header gives the page a formal starting point. It prevents the legal
      | copy from feeling pasted directly into the page.
      |
      */

      .sccc-legal-hero {
        position: relative;
        overflow: hidden;
        margin-bottom: clamp(1.5rem, 4vw, 3rem);
        padding: clamp(1.5rem, 4vw, 3rem);
        border: 1px solid var(--sccc-legal-line);
        border-radius: var(--sccc-legal-radius-lg);
        background:
          linear-gradient(135deg, color-mix(in oklab, var(--sccc-legal-surface) 92%, transparent), color-mix(in oklab, var(--sccc-legal-surface) 72%, transparent)),
          radial-gradient(640px 240px at 100% 0%, color-mix(in oklab, var(--sccc-legal-primary) 18%, transparent), transparent 65%);
        box-shadow: var(--sccc-legal-shadow);
      }

      .sccc-legal-hero::before {
        content: "";
        position: absolute;
        inset-inline: clamp(1.5rem, 4vw, 3rem);
        top: 0;
        height: 2px;
        background: linear-gradient(
          90deg,
          transparent,
          color-mix(in oklab, var(--sccc-legal-accent) 90%, transparent),
          color-mix(in oklab, var(--sccc-legal-danger) 80%, transparent),
          transparent
        );
      }

      .sccc-legal-eyebrow {
        margin: 0 0 0.65rem;
        color: var(--sccc-legal-accent);
        font-size: 0.78rem;
        font-weight: 800;
        letter-spacing: 0.18em;
        text-transform: uppercase;
      }

      html[data-theme="light"] .sccc-legal-eyebrow {
        color: var(--sccc-legal-primary);
      }

      .sccc-legal-title {
        max-width: 12ch;
        margin: 0;
        color: var(--sccc-legal-text);
        font-family: var(--sccc-legal-display);
        font-size: clamp(2.45rem, 6vw, 5rem);
        font-weight: 800;
        letter-spacing: -0.035em;
        line-height: 0.95;
        text-transform: uppercase;
      }

      .sccc-legal-intro {
        max-width: 68ch;
        margin: clamp(1rem, 2vw, 1.35rem) 0 0;
        color: var(--sccc-legal-muted);
        font-size: clamp(1rem, 1.3vw, 1.12rem);
        line-height: 1.75;
      }

      .sccc-legal-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 0.65rem;
        margin-top: clamp(1.1rem, 2vw, 1.6rem);
      }

      .sccc-legal-meta__item {
        display: inline-flex;
        align-items: center;
        min-height: 2.15rem;
        padding: 0.4rem 0.8rem;
        border: 1px solid var(--sccc-legal-line);
        border-radius: 999px;
        color: var(--sccc-legal-muted);
        background: color-mix(in oklab, var(--sccc-legal-surface) 72%, transparent);
        font-size: 0.82rem;
        font-weight: 700;
      }

      .sccc-legal-meta__item strong {
        color: var(--sccc-legal-text);
        font-weight: 800;
      }

      /*
      |--------------------------------------------------------------------------
      | Layout
      |--------------------------------------------------------------------------
      |
      | Desktop gets a sticky TOC without an internal scrollbar. The sidebar is
      | intentionally wider and more readable now, because the previous compact
      | sizing made the text feel too small and squeezed.
      |
      */

      .sccc-legal-grid {
        display: grid;
        grid-template-columns: minmax(320px, 380px) minmax(0, 1fr);
        align-items: start;
        gap: clamp(1.5rem, 3vw, 2.75rem);
      }

      .sccc-legal-mobile-toc {
        display: none;
      }

      .sccc-legal-toc {
        position: sticky;
        top: 5.5rem;
        align-self: start;
        overflow: visible;
        padding: 1rem;
        border: 1px solid var(--sccc-legal-line);
        border-radius: var(--sccc-legal-radius);
        background: color-mix(in oklab, var(--sccc-legal-surface) 84%, transparent);
        box-shadow: 0 18px 45px rgb(0 0 0 / 0.16);
      }

      .sccc-legal-toc__title {
        margin: 0 0 0.75rem;
        color: var(--sccc-legal-text);
        font-family: var(--sccc-legal-display);
        font-size: 0.86rem;
        font-weight: 800;
        letter-spacing: 0.11em;
        line-height: 1.15;
        text-transform: uppercase;
      }

      .sccc-legal-toc__list {
        display: grid;
        gap: 0.14rem;
        margin: 0;
        padding: 0;
        list-style: none;
      }

      .sccc-legal-toc__link {
        display: grid;
        grid-template-columns: 2.3rem 1fr;
        gap: 0.45rem;
        align-items: start;
        padding: 0.4rem 0.5rem;
        border-radius: 0.7rem;
        color: var(--sccc-legal-muted);
        font-size: 0.84rem;
        line-height: 1.28;
        text-decoration: none;
        transition:
          color 180ms ease,
          background-color 180ms ease,
          transform 180ms ease;
      }

      .sccc-legal-toc__link:hover,
      .sccc-legal-toc__link.is-active {
        color: var(--sccc-legal-text);
        background: color-mix(in oklab, var(--sccc-legal-primary) 13%, transparent);
        transform: translateX(2px);
      }

      .sccc-legal-toc__number {
        color: var(--sccc-legal-accent);
        font-size: 0.76rem;
        font-weight: 900;
        font-variant-numeric: tabular-nums;
        line-height: 1.35;
      }

      html[data-theme="light"] .sccc-legal-toc__number {
        color: var(--sccc-legal-primary);
      }

      /*
      |--------------------------------------------------------------------------
      | Document Card
      |--------------------------------------------------------------------------
      |
      | The outer card gives the page a polished legal-document feel. The inner
      | content column stays narrower for easier reading.
      |
      */

      .sccc-legal-document {
        min-width: 0;
        padding: clamp(1.25rem, 3vw, 2.75rem);
        border: 1px solid var(--sccc-legal-line);
        border-radius: var(--sccc-legal-radius-lg);
        background:
          linear-gradient(180deg, color-mix(in oklab, var(--sccc-legal-surface) 96%, transparent), color-mix(in oklab, var(--sccc-legal-surface) 88%, transparent));
        box-shadow: var(--sccc-legal-shadow);
      }

      .sccc-legal-content {
        max-width: 74ch;
        margin-inline: auto;
        color: var(--sccc-legal-text);
        font-size: clamp(0.98rem, 1vw, 1.05rem);
        line-height: 1.78;
      }

      .sccc-legal-content > *:first-child {
        margin-top: 0;
      }

      .sccc-legal-content > *:last-child {
        margin-bottom: 0;
      }

      .sccc-legal-content :where(p, ul, ol, blockquote, table) {
        margin-block: 0 1.05rem;
      }

      .sccc-legal-content p {
        color: color-mix(in oklab, var(--sccc-legal-text) 86%, var(--sccc-legal-muted));
      }

      .sccc-legal-content strong {
        color: var(--sccc-legal-text);
        font-weight: 800;
      }

      .sccc-legal-content :where(ul, ol) {
        padding-left: 1.25rem;
      }

      .sccc-legal-content li {
        padding-left: 0.15rem;
        color: color-mix(in oklab, var(--sccc-legal-text) 84%, var(--sccc-legal-muted));
      }

      .sccc-legal-content li + li {
        margin-top: 0.45rem;
      }

      .sccc-legal-content :where(h3, h4) {
        color: var(--sccc-legal-text);
        font-family: var(--sccc-legal-display);
        font-weight: 800;
        line-height: 1.15;
      }

      .sccc-legal-content h3 {
        margin: 2rem 0 0.75rem;
        font-size: clamp(1.35rem, 2vw, 1.75rem);
      }

      .sccc-legal-content h4 {
        margin: 1.6rem 0 0.55rem;
        color: var(--sccc-legal-accent);
        font-size: clamp(1.05rem, 1.5vw, 1.2rem);
        letter-spacing: 0.02em;
      }

      html[data-theme="light"] .sccc-legal-content h4 {
        color: var(--sccc-legal-primary);
      }

      /*
      |--------------------------------------------------------------------------
      | Legal Section Headings
      |--------------------------------------------------------------------------
      |
      | H2 blocks are converted by the PHP above into numbered legal sections.
      | This gives the long document an organized visual rhythm.
      |
      */

      .sccc-legal-content .sccc-legal-heading {
        scroll-margin-top: 7rem;
        display: grid;
        grid-template-columns: auto 1fr;
        gap: 0.75rem;
        align-items: start;
        margin: clamp(2.4rem, 4vw, 3.35rem) 0 1rem;
        padding-top: clamp(1.25rem, 2vw, 1.6rem);
        border-top: 1px solid var(--sccc-legal-line);
        color: var(--sccc-legal-text);
        font-family: var(--sccc-legal-display);
        font-size: clamp(1.65rem, 3vw, 2.35rem);
        font-weight: 900;
        letter-spacing: -0.035em;
        line-height: 1.05;
      }

      .sccc-legal-content .sccc-legal-heading:first-child {
        margin-top: 0;
        padding-top: 0;
        border-top: 0;
      }

      .sccc-legal-heading__number {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 2.65rem;
        height: 2.15rem;
        padding-inline: 0.5rem;
        border: 1px solid color-mix(in oklab, var(--sccc-legal-accent) 55%, var(--sccc-legal-line));
        border-radius: 999px;
        color: var(--sccc-legal-accent);
        background:
          radial-gradient(circle at 50% 0%, color-mix(in oklab, var(--sccc-legal-accent) 18%, transparent), transparent 70%),
          color-mix(in oklab, var(--sccc-legal-surface) 80%, transparent);
        box-shadow: 0 0 20px color-mix(in oklab, var(--sccc-legal-accent) 18%, transparent);
        font-family: var(--sccc-legal-body);
        font-size: 0.82rem;
        font-weight: 900;
        letter-spacing: 0;
        line-height: 1;
      }

      html[data-theme="light"] .sccc-legal-heading__number {
        color: var(--sccc-legal-primary);
        border-color: color-mix(in oklab, var(--sccc-legal-primary) 35%, var(--sccc-legal-line));
        box-shadow: 0 10px 24px color-mix(in oklab, var(--sccc-legal-primary) 12%, transparent);
      }

      .sccc-legal-heading__text {
        min-width: 0;
      }

      .sccc-legal-content hr,
      .sccc-legal-content .wp-block-separator {
        height: 1px;
        margin: clamp(1.75rem, 4vw, 2.5rem) 0;
        border: 0;
        background: linear-gradient(
          90deg,
          transparent,
          color-mix(in oklab, var(--sccc-legal-accent) 60%, transparent),
          color-mix(in oklab, var(--sccc-legal-danger) 42%, transparent),
          transparent
        );
      }

      .sccc-legal-content blockquote {
        padding: 1rem 1.15rem;
        border-left: 3px solid var(--sccc-legal-accent);
        border-radius: 0.85rem;
        color: var(--sccc-legal-text);
        background: color-mix(in oklab, var(--sccc-legal-primary) 10%, transparent);
      }

      .sccc-legal-content table {
        display: block;
        width: 100%;
        overflow-x: auto;
        border-collapse: collapse;
        border: 1px solid var(--sccc-legal-line);
        border-radius: 0.9rem;
      }

      .sccc-legal-content :where(th, td) {
        padding: 0.75rem 0.9rem;
        border-bottom: 1px solid var(--sccc-legal-line);
        text-align: left;
        vertical-align: top;
      }

      .sccc-legal-content th {
        color: var(--sccc-legal-text);
        font-weight: 800;
      }

      .sccc-legal-content td {
        color: var(--sccc-legal-muted);
      }

      /*
      |--------------------------------------------------------------------------
      | Bottom Note
      |--------------------------------------------------------------------------
      |
      | A small closing card gives visitors a useful next step without making
      | the legal page feel like a marketing landing page.
      |
      */

      .sccc-legal-note {
        margin-top: clamp(1.5rem, 4vw, 2.5rem);
        padding: clamp(1rem, 2vw, 1.35rem);
        border: 1px solid var(--sccc-legal-line);
        border-radius: var(--sccc-legal-radius);
        background:
          linear-gradient(135deg, color-mix(in oklab, var(--sccc-legal-primary) 12%, transparent), color-mix(in oklab, var(--sccc-legal-surface) 82%, transparent));
      }

      .sccc-legal-note__title {
        margin: 0 0 0.4rem;
        color: var(--sccc-legal-text);
        font-family: var(--sccc-legal-display);
        font-size: 1.25rem;
        font-weight: 900;
        line-height: 1.1;
      }

      .sccc-legal-note__text {
        margin: 0;
        color: var(--sccc-legal-muted);
        line-height: 1.65;
      }

      .sccc-legal-note__text p {
        margin: 0;
      }

      .sccc-legal-note__text p + p {
        margin-top: 0.75rem;
      }

      /*
      |--------------------------------------------------------------------------
      | Focus States
      |--------------------------------------------------------------------------
      |
      | These keep keyboard navigation visible and accessible.
      |
      */

      .sccc-legal-template :where(a, summary):focus-visible {
        outline: 2px solid var(--sccc-legal-accent);
        outline-offset: 4px;
        border-radius: 0.35rem;
      }

      /*
      |--------------------------------------------------------------------------
      | Medium Desktop Adjustment
      |--------------------------------------------------------------------------
      |
      | Keeps the sidebar readable on narrower desktop screens without forcing
      | the tiny compressed type from the previous version.
      |
      */

      @media (min-width: 1024px) and (max-width: 1180px) {
        .sccc-legal-wrap {
          width: min(calc(100% - 1.5rem), 1180px);
        }

        .sccc-legal-grid {
          grid-template-columns: minmax(280px, 320px) minmax(0, 1fr);
          gap: 1.35rem;
        }

        .sccc-legal-toc {
          padding: 0.85rem;
        }

        .sccc-legal-toc__link {
          grid-template-columns: 2.05rem 1fr;
          padding: 0.34rem 0.42rem;
          font-size: 0.78rem;
          line-height: 1.24;
        }

        .sccc-legal-toc__number {
          font-size: 0.72rem;
        }
      }

      /*
      |--------------------------------------------------------------------------
      | Mobile Layout
      |--------------------------------------------------------------------------
      |
      | On smaller screens, the sticky sidebar becomes a compact jump menu above
      | the legal document.
      |
      */

      @media (max-width: 1023px) {
        .sccc-legal-grid {
          display: block;
        }

        .sccc-legal-toc {
          display: none;
        }

        .sccc-legal-mobile-toc {
          display: block;
          margin-bottom: 1rem;
          border: 1px solid var(--sccc-legal-line);
          border-radius: var(--sccc-legal-radius);
          background: color-mix(in oklab, var(--sccc-legal-surface) 88%, transparent);
        }

        .sccc-legal-mobile-toc summary {
          cursor: pointer;
          padding: 1rem;
          color: var(--sccc-legal-text);
          font-family: var(--sccc-legal-display);
          font-weight: 900;
          letter-spacing: 0.06em;
          text-transform: uppercase;
        }

        .sccc-legal-mobile-toc .sccc-legal-toc__list {
          padding: 0 1rem 1rem;
        }

        .sccc-legal-mobile-toc .sccc-legal-toc__link {
          font-size: 0.86rem;
          line-height: 1.35;
          padding: 0.48rem 0.5rem;
          grid-template-columns: 2.2rem 1fr;
        }

        .sccc-legal-mobile-toc .sccc-legal-toc__number {
          font-size: 0.78rem;
        }

        .sccc-legal-document {
          padding: clamp(1rem, 5vw, 1.45rem);
          border-radius: var(--sccc-legal-radius);
        }

        .sccc-legal-content {
          max-width: none;
        }

        .sccc-legal-content .sccc-legal-heading {
          grid-template-columns: 1fr;
          gap: 0.55rem;
        }

        .sccc-legal-heading__number {
          width: max-content;
        }
      }

      @media (max-width: 520px) {
        .sccc-legal-template {
          padding-block: 1.75rem 3rem;
        }

        .sccc-legal-wrap {
          width: min(calc(100% - 1rem), 1180px);
        }

        .sccc-legal-hero {
          padding: 1.2rem;
          border-radius: 1.15rem;
        }

        .sccc-legal-title {
          font-size: clamp(2.15rem, 13vw, 3.15rem);
        }
      }
    </style>

    <main class="sccc-legal-template" data-sccc-legal-template aria-labelledby="sccc-legal-title">
      <div class="sccc-legal-wrap">

        {{--
          Legal Hero
          The content comes from ACF first, then falls back to WordPress values.
        --}}
        <header class="sccc-legal-hero">
          @if($legal_eyebrow !== '')
            <p class="sccc-legal-eyebrow">
              {!! esc_html($legal_eyebrow) !!}
            </p>
          @endif

          <h1 id="sccc-legal-title" class="sccc-legal-title">
            {!! esc_html($legal_title) !!}
          </h1>

          @if($legal_intro !== '')
            <p class="sccc-legal-intro">
              {!! esc_html($legal_intro) !!}
            </p>
          @endif

          <div class="sccc-legal-meta" aria-label="Document information">
            @if($legal_updated_date !== '')
              <span class="sccc-legal-meta__item">
                <strong>Last updated:</strong>&nbsp;{!! esc_html($legal_updated_date) !!}
              </span>
            @endif

            @if($legal_applies_to !== '')
              <span class="sccc-legal-meta__item">
                {!! esc_html($legal_applies_to) !!}
              </span>
            @endif
          </div>
        </header>

        <div class="sccc-legal-grid">

          @if(! empty($toc_items))
            {{--
              Desktop Table of Contents
              This version is sticky and does not create a nested scrollbar.
            --}}
            <aside class="sccc-legal-toc" aria-label="Legal document navigation">
              <p class="sccc-legal-toc__title">On this page</p>

              <ol class="sccc-legal-toc__list">
                @foreach($toc_items as $toc_item)
                  <li>
                    <a
                      class="sccc-legal-toc__link"
                      href="#{!! esc_attr($toc_item['id']) !!}"
                      data-sccc-legal-link
                    >
                      <span class="sccc-legal-toc__number">
                        {!! esc_html($toc_item['number']) !!}
                      </span>

                      <span>
                        {!! esc_html($toc_item['label']) !!}
                      </span>
                    </a>
                  </li>
                @endforeach
              </ol>
            </aside>

            {{--
              Mobile Table of Contents
              A collapsible version keeps the page compact on phones.
            --}}
            <details class="sccc-legal-mobile-toc">
              <summary>Jump to section</summary>

              <ol class="sccc-legal-toc__list">
                @foreach($toc_items as $toc_item)
                  <li>
                    <a
                      class="sccc-legal-toc__link"
                      href="#{!! esc_attr($toc_item['id']) !!}"
                      data-sccc-legal-link
                    >
                      <span class="sccc-legal-toc__number">
                        {!! esc_html($toc_item['number']) !!}
                      </span>

                      <span>
                        {!! esc_html($toc_item['label']) !!}
                      </span>
                    </a>
                  </li>
                @endforeach
              </ol>
            </details>
          @endif

          {{--
            Main Legal Content
            Gutenberg remains the source of truth. This card only improves the
            presentation and readability of the legal document.

            Important:
            $legal_content is intentionally output directly because it has already
            been rendered by WordPress through the_content filters. This prevents
            inline <style> tags from custom blocks from being stripped and shown
            as raw CSS text on the frontend.
          --}}
          <article class="sccc-legal-document" aria-label="{!! esc_attr($legal_title) !!}">
            <div class="sccc-legal-content">
              {!! $legal_content !!}
            </div>

            @if($legal_bottom_note_title !== '' || $legal_bottom_note_text !== '')
              <footer class="sccc-legal-note">
                @if($legal_bottom_note_title !== '')
                  <h2 class="sccc-legal-note__title">
                    {!! esc_html($legal_bottom_note_title) !!}
                  </h2>
                @endif

                @if($legal_bottom_note_text !== '')
                  <div class="sccc-legal-note__text">
                    {!! wp_kses_post(wpautop($legal_bottom_note_text)) !!}
                  </div>
                @endif
              </footer>
            @endif
          </article>
        </div>
      </div>
    </main>

    <script>
      /*
      |--------------------------------------------------------------------------
      | Legal TOC Active State
      |--------------------------------------------------------------------------
      |
      | This small script highlights the current section in the table of contents.
      | It uses native browser APIs only and does not add any new dependencies.
      |
      */

      document.addEventListener('DOMContentLoaded', () => {
        const template = document.querySelector('[data-sccc-legal-template]');

        if (!template || !('IntersectionObserver' in window)) {
          return;
        }

        const links = Array.from(template.querySelectorAll('[data-sccc-legal-link]'));
        const headings = Array.from(template.querySelectorAll('.sccc-legal-heading[id]'));

        if (!links.length || !headings.length) {
          return;
        }

        const setActiveLink = (id) => {
          links.forEach((link) => {
            link.classList.toggle('is-active', link.getAttribute('href') === `#${id}`);
          });
        };

        const observer = new IntersectionObserver((entries) => {
          const visibleEntries = entries
            .filter((entry) => entry.isIntersecting)
            .sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top);

          if (visibleEntries.length) {
            setActiveLink(visibleEntries[0].target.id);
          }
        }, {
          root: null,
          rootMargin: '-18% 0px -68% 0px',
          threshold: 0,
        });

        headings.forEach((heading) => observer.observe(heading));
      });
    </script>

  @endwhile
@endsection