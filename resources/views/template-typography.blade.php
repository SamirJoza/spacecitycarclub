{{-- resources/views/template-typography.blade.php --}}
{{-- 
  Template Name: Typography Test
  Description: Visual testbed for the fluid (clamp) type scale using Tailwind v4 @theme tokens.
--}}

@extends('layouts.app')

@section('content')
  <section class="container mx-auto px-6 py-12 space-y-12">

    {{-- Page header --}}
    <header class="space-y-2">
      <h1 class="text-h1 font-bold">Typography Test</h1>
      <p class="text-body text-gray-600">Fluid Major Third scale (base 18px visual, root 16px). Utilities derive from <code>@theme</code> tokens.</p>
    </header>

    {{-- Headings showcase --}}
    <section aria-labelledby="headings" class="space-y-6">
      <h2 id="headings" class="text-h3 font-semibold">Headings</h2>

      <div class="grid gap-6 sm:grid-cols-2">
        <article class="rounded-2xl border p-6">
          <div class="mb-3 text-xs uppercase tracking-wide text-gray-500">Utility: <code>text-h1</code></div>
          <h1 class="text-h1 font-bold leading-[var(--text-h1--line-height)]">Heading One — The quick brown fox</h1>
        </article>

        <article class="rounded-2xl border p-6">
          <div class="mb-3 text-xs uppercase tracking-wide text-gray-500">Utility: <code>text-h2</code></div>
          <h2 class="text-h2 font-semibold leading-[var(--text-h2--line-height)]">Heading Two — Jumps over the lazy dog</h2>
        </article>

        <article class="rounded-2xl border p-6">
          <div class="mb-3 text-xs uppercase tracking-wide text-gray-500">Utility: <code>text-h3</code></div>
          <h3 class="text-h3 font-semibold leading-[var(--text-h3--line-height)]">Heading Three — Sphinx of black quartz</h3>
        </article>

        <article class="rounded-2xl border p-6">
          <div class="mb-3 text-xs uppercase tracking-wide text-gray-500">Utility: <code>text-h4</code></div>
          <h4 class="text-h4 font-medium leading-[var(--text-h4--line-height)]">Heading Four — Judge my vow</h4>
        </article>

        <article class="rounded-2xl border p-6">
          <div class="mb-3 text-xs uppercase tracking-wide text-gray-500">Utility: <code>text-h5</code></div>
          <h5 class="text-h5 font-medium leading-[var(--text-h5--line-height)]">Heading Five — Pack my box with five dozen liquor jugs</h5>
        </article>

        <article class="rounded-2xl border p-6">
          <div class="mb-3 text-xs uppercase tracking-wide text-gray-500">Utility: <code>text-h6</code></div>
          <h6 class="text-h6 font-medium leading-[var(--text-h6--line-height)]">Heading Six — How vexingly quick daft zebras jump</h6>
        </article>
      </div>
    </section>

    {{-- Body text showcase --}}
    <section aria-labelledby="body" class="space-y-6">
      <h2 id="body" class="text-h3 font-semibold">Body Text</h2>

      <div class="grid gap-6 sm:grid-cols-3">
        <article class="rounded-2xl border p-6">
          <div class="mb-3 text-xs uppercase tracking-wide text-gray-500">Utility: <code>text-body-xs</code></div>
          <p class="text-body-xs leading-[var(--text-body-xs--line-height)] text-gray-700">
            A small body size for fine print and captions. This text scales fluidly with viewport width.
          </p>
        </article>

        <article class="rounded-2xl border p-6">
          <div class="mb-3 text-xs uppercase tracking-wide text-gray-500">Utility: <code>text-body-sm</code></div>
          <p class="text-body-sm leading-[var(--text-body-sm--line-height)] text-gray-700">
            A compact body size for dense UI or secondary paragraphs. Fluid by design.
          </p>
        </article>

        <article class="rounded-2xl border p-6">
          <div class="mb-3 text-xs uppercase tracking-wide text-gray-500">Utility: <code>text-body</code></div>
          <p class="text-body leading-[var(--text-body--line-height)] text-gray-800">
            This is the main body size (~18px base) while keeping the root at 16px for sane <code>rem</code> math across the system.
          </p>
        </article>
      </div>
    </section>

    {{-- Mixed content (real world) --}}
    <section aria-labelledby="mixed" class="space-y-4">
      <h2 id="mixed" class="text-h3 font-semibold">Mixed Content Example</h2>
      <p class="text-body text-gray-800">
        Real content preview: Resize the window and watch headings and body scale smoothly via <code>clamp()</code>. Line heights are token‑driven for each size.
      </p>
      <ul class="list-disc ps-6 text-body text-gray-700">
        <li>Headings use <code>text-h*</code> utilities.</li>
        <li>Body text uses <code>text-body</code>, with <code>text-body-sm</code> and <code>text-body-xs</code> as needed.</li>
        <li>You can override line-height ad‑hoc with Tailwind’s slash syntax, e.g. <code>text-h3/normal</code>.</li>
      </ul>
      <blockquote class="border-l-4 ps-4 text-body italic text-gray-600">
        “Good typography is invisible. Bad typography is everywhere.”
      </blockquote>
    </section>

    {{-- Raw CSS variable usage (no utilities) --}}
    <section aria-labelledby="raw" class="space-y-3">
      <h2 id="raw" class="text-h3 font-semibold">Raw CSS Variable Usage</h2>
      <p class="text-body text-gray-700">
        Sometimes you need raw CSS (e.g., GSAP inline styles). These use the <em>same</em> tokens without Tailwind utilities:
      </p>
      <div class="rounded-2xl border p-6 space-y-2">
        <div style="font-size: var(--text-h2); line-height: var(--text-h2--line-height);" class="font-semibold">
          Raw var(--text-h2) — Same scale, no utility class
        </div>
        <div style="font-size: var(--text-body); line-height: var(--text-body--line-height);" class="text-gray-700">
          Raw var(--text-body) — Main body size via token variables
        </div>
      </div>
    </section>

  </section>
@endsection
