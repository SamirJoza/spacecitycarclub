{{--
  <x-ui.section-header> — eyebrow, headline, description and accent rule
  (resources/css/components/section-header.css). Same look as the Section Header
  block, for use inside templates and other blocks.

  Props
  - eyebrow      small label above the headline (optional)
  - headline     plain text, or a named slot (<x-slot:headline>…</x-slot:headline>) for markup
  - description  plain text; line breaks are kept (optional)
  - align        left | center | right (default left)
  - spacing      none | small | medium | large | xl (default medium)
  - accent       show the gradient rule under the header (default true)
  - level        heading level 1-6 (default 2)

  Example
    <x-ui.section-header eyebrow="Monthly feature" headline="Our featured car of the month" align="center" />
--}}
@props([
  'eyebrow' => null,
  'headline' => null,
  'description' => null,
  'align' => 'left',
  'spacing' => 'medium',
  'accent' => true,
  'level' => 2,
])

@php
  $alignClass = match ($align) {
    'right'  => 'text-right items-end ml-auto',
    'center' => 'text-center items-center mx-auto',
    default  => 'text-left items-start mr-auto',
  };
  $spacingClass = in_array($spacing, ['none', 'small', 'medium', 'large', 'xl'], true)
    ? 'sccc-section-header--space-' . $spacing
    : 'sccc-section-header--space-medium';
  $tag = 'h' . min(6, max(1, (int) $level));
@endphp

<div {{ $attributes->class(['sccc-section-header', $spacingClass]) }}>
  <div class="sccc-sh w-full max-w-[75ch] {{ $alignClass }}">
    @if (! empty($eyebrow))
      <p class="sccc-sh__eyebrow">{{ $eyebrow }}</p>
    @endif

    @if (! empty($headline))
      <{{ $tag }} class="sccc-sh__headline">{{ $headline }}</{{ $tag }}>
    @endif

    @if (! empty($description))
      <div class="sccc-sh__desc">{!! nl2br(e($description)) !!}</div>
    @endif

    @if ($accent)
      <span class="sccc-sh__accent" aria-hidden="true"></span>
    @endif
  </div>
</div>
