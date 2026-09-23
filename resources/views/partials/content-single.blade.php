{{--
  ============================================================
  File: resources/views/partials/content-single.blade.php
  ============================================================
  Single blog post — Space City Car Club.

  Layout structure (all sections are full container width except the grid):

    <article>
      <header>            ← FULL WIDTH: category pill, title, author row, hero image
      <div.grid>          ← GRID: article body (2/3) + sidebar (1/3)
        <div.col-span-2>  ← article body: prose, tags, pagination, comments
        <aside>           ← sidebar: $sidebarHtml from dynamic_sidebar()
                             hidden when $sidebarHtml is empty (no widgets active)
    </article>

    <div.author-bio>      ← FULL WIDTH: avatar, name, bio, link
    <section.read-next>   ← FULL WIDTH: 3-column card grid

  Variables from ContentSingle composer (app/View/Composers/ContentSingle.php):
    $postClasses        string
    $postContent        string   — captured the_content()
    $pagingHtml         string   — wp_link_pages, empty if not paginated
    $commentsHtml       string   — captured comments_template()
    $readingTime        int
    $firstCat           WP_Term|null
    $heroImgUrl         string
    $heroImgCaption     string
    $authorName         string
    $authorBio          string
    $authorUrl          string
    $authorAvatarSmall  string   — get_avatar() <img> at 40px
    $authorAvatarLarge  string   — get_avatar() <img> at 80px
    $postDate           string   — "F j, Y"
    $postDateMachine    string   — "Y-m-d"
    $tags               WP_Term[]|false
    $readNextPosts      array[]

  Passed by single.blade.php:
    $sidebarHtml        string   — captured dynamic_sidebar('blog-sidebar'),
                                   empty string when no widgets are active
  ============================================================
--}}

<article class="{{ $postClasses }}">

  {{-- ============================================================
       ARTICLE HEADER — full container width
       Category pill, title, author meta row, hero image.
       This section is OUTSIDE the body/sidebar grid so it always
       spans the full container width regardless of sidebar presence.
       ============================================================ --}}
  <header class="mb-10">

    {{-- ── Category pill + read time ── --}}
    <div class="flex gap-3 flex-wrap items-center mb-6">

      @if ($firstCat)
        <a
          href="{{ get_category_link($firstCat->term_id) }}"
          class="sccc-single-cat-pill flex h-8 shrink-0 items-center justify-center rounded-full bg-primary/10 dark:bg-[#232348] px-4 border border-primary/20 no-underline hover:bg-primary/20 transition-colors"
          rel="category tag"
        >
          <span class="text-primary dark:text-blue-200 text-xs font-bold uppercase tracking-wider">
            {{ $firstCat->name }}
          </span>
        </a>
        <span class="text-slate-400 text-sm" aria-hidden="true">•</span>
      @endif

      <span class="text-slate-500 dark:text-slate-400 text-sm font-medium">
        {{ $readingTime }} {{ __('min read', 'sage') }}
      </span>

    </div>

    {{-- ── Post title (p-name — microformats2) ── --}}
    <h1 class="p-name text-4xl md:text-5xl lg:text-6xl font-black leading-tight tracking-tight text-slate-900 dark:text-white mb-6 font-display">
      {!! get_the_title() !!}
    </h1>

    {{-- ── Author meta row ────────────────────────────────────────
         Inline markup (not the default entry-meta partial) so we can
         include the avatar. $authorAvatarSmall is a get_avatar() <img>
         tag which respects all WordPress avatar hooks (LocalAvatar.php).
    ─────────────────────────────────────────────────────────────── --}}
    @if ($authorName)
      <div class="entry-meta flex items-center gap-3 mt-2">

        {!! $authorAvatarSmall !!}

        <div class="flex flex-col">
          <a
            href="{{ esc_url($authorUrl) }}"
            class="p-author text-slate-900 dark:text-white text-sm font-bold hover:text-primary transition-colors no-underline"
            rel="author"
          >{{ $authorName }}</a>
          <time
            class="dt-published text-slate-500 dark:text-[#9292c9] text-xs font-normal"
            datetime="{{ $postDateMachine }}"
          >{{ $postDate }}</time>
        </div>

      </div>
    @endif

    {{-- ── Featured image (u-featured — microformats2) ── --}}
    @if ($heroImgUrl)
      <div class="w-full mt-8 relative rounded-2xl overflow-hidden shadow-2xl shadow-primary/10 group">

        <div class="absolute inset-0 bg-gradient-to-t from-background-dark/80 to-transparent opacity-60 z-10 pointer-events-none" aria-hidden="true"></div>

        <div
          class="u-featured w-full bg-center bg-no-repeat bg-cover aspect-video md:aspect-[21/9] transition-transform duration-700 group-hover:scale-105"
          style="background-image: url('{{ esc_url($heroImgUrl) }}');"
          role="img"
          aria-label="{{ esc_attr(get_the_title()) }}"
        ></div>

        @if ($heroImgCaption)
          <div class="absolute bottom-4 left-4 md:bottom-6 md:left-8 z-20">
            <p class="text-white/80 text-xs md:text-sm font-mono bg-black/50 px-3 py-1 rounded backdrop-blur-sm flex items-center gap-1">
              <span class="material-symbols-outlined text-[14px]" aria-hidden="true">photo_camera</span>
              {{ $heroImgCaption }}
            </p>
          </div>
        @endif

      </div>
    @endif

  </header>


  {{-- ============================================================
       BODY + SIDEBAR GRID
       Only this section is two-column. The header above and the
       author bio + Read Next below are full container width.

       $sidebarHtml is passed from single.blade.php.
       When empty (no active widgets), the <aside> is not rendered
       and the article body takes up the full container width.
       ============================================================ --}}
  <div class="{{ $sidebarHtml ? 'grid grid-cols-1 lg:grid-cols-3 gap-12' : '' }}">


    {{-- ── Article body — 2/3 when sidebar present, full-width when not ── --}}
    <div class="{{ $sidebarHtml ? 'lg:col-span-2' : '' }}">

      {{-- Post content (e-content — microformats2) ──
           Captured via ob_start/the_content()/ob_get_clean in the Composer.
      ── --}}
      <div class="e-content entry-content prose prose-lg dark:prose-invert max-w-none">
        {!! $postContent !!}
      </div>

      {{-- Tags ── --}}
      @if ($tags)
        <footer class="entry-footer mt-12 pt-8 border-t border-slate-200 dark:border-white/10">
          <div class="flex flex-wrap gap-2 items-center">
            <span class="text-xs font-bold uppercase text-slate-500 dark:text-slate-400 mr-2 self-center">
              {{ __('Tags:', 'sage') }}
            </span>
            @foreach ($tags as $tag)
              <a
                href="{{ get_tag_link($tag->term_id) }}"
                class="sccc-tag-pill px-4 py-1.5 rounded-full bg-slate-100 dark:bg-white/5 border border-slate-300 dark:border-white/10 text-slate-700 dark:text-gray-300 text-sm font-medium hover:bg-primary hover:border-primary hover:text-white transition-colors no-underline"
                rel="tag"
              >#{{ $tag->name }}</a>
            @endforeach
          </div>
        </footer>
      @endif

      {{-- Pagination ── --}}
      @if ($pagingHtml)
        <footer class="page-nav-footer mt-8">
          <nav class="page-nav" aria-label="{{ __('Page', 'sage') }}">
            {!! $pagingHtml !!}
          </nav>
        </footer>
      @endif

      {{-- Comments ── --}}
      @if ($commentsHtml)
        <div class="entry-comments mt-16">
          {!! $commentsHtml !!}
        </div>
      @endif

    </div>


    {{-- ── Sidebar — 1/3 column, hidden when no widgets are active ── --}}
    @if ($sidebarHtml)
      <aside
        class="lg:col-span-1"
        id="single-sidebar"
        aria-label="{{ __('Post sidebar', 'sage') }}"
      >
        {!! $sidebarHtml !!}
      </aside>
    @endif


  </div>{{-- end body/sidebar grid --}}


</article>{{-- end h-entry --}}


{{-- ============================================================
     AUTHOR BIO CARD
     Outside <article> and outside the grid — always full container
     width regardless of whether the sidebar is present.
     ============================================================ --}}
@if ($authorName)
  <div class="single-author-bio mt-12 pt-10 border-t border-slate-200 dark:border-white/10">
    <div class="single-author-bio-inner p-6 md:p-8 rounded-2xl bg-slate-50 dark:bg-[#1a1a2e] border border-slate-200 dark:border-white/5 flex flex-col md:flex-row gap-6 items-center md:items-start text-center md:text-left shadow-sm dark:shadow-xl">

      <div class="shrink-0 rounded-full border-2 border-primary overflow-hidden">
        {!! $authorAvatarLarge !!}
      </div>

      <div class="flex-1">
        <h2 class="sccc-bio-name text-xl font-bold mb-2 font-display">
          {{ $authorName }}
        </h2>

        @if ($authorBio)
          <p class="sccc-bio-text text-sm leading-relaxed mb-4">
            {{ $authorBio }}
          </p>
        @endif

        <a
          href="{{ esc_url($authorUrl) }}"
          class="sccc-bio-link text-primary font-bold text-sm hover:opacity-75 inline-flex items-center justify-center md:justify-start gap-1 transition-opacity"
        >
          {{ __('View all posts', 'sage') }}
          <span class="material-symbols-outlined text-[16px]" aria-hidden="true">arrow_forward</span>
        </a>
      </div>

    </div>
  </div>
@endif


{{-- ============================================================
     READ NEXT
     Outside <article> and outside the grid — always spans the full
     container width. The internal 3-column card grid is independent
     of the article body/sidebar grid above.
     ============================================================ --}}
@if (! empty($readNextPosts))
  <section
    class="sccc-read-next mt-16 pt-12 border-t border-slate-200 dark:border-white/10"
    aria-label="{{ __('Read next', 'sage') }}"
  >

    <h2 class="sccc-rn-heading text-2xl font-bold mb-8 font-display">
      {{ __('Read Next', 'sage') }}
    </h2>

    {{-- Always 3 columns — independent of the body/sidebar grid --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      @foreach ($readNextPosts as $rnPost)
        <a href="{{ esc_url($rnPost['permalink']) }}" class="read-next-card group block no-underline rounded-xl overflow-hidden border transition-all duration-300">

          {{-- Card image ── --}}
          <div class="h-48 bg-slate-200 relative overflow-hidden">
            @if ($rnPost['imgUrl'])
              <div
                class="w-full h-full bg-center bg-cover transition-transform duration-500 group-hover:scale-105"
                style="background-image: url('{{ esc_url($rnPost['imgUrl']) }}');"
                role="img"
                aria-label="{{ esc_attr($rnPost['title']) }}"
              ></div>
            @endif
            @if ($rnPost['cat'])
              <div class="absolute top-3 left-3 bg-black/60 backdrop-blur-md px-2 py-1 rounded text-xs font-bold text-white uppercase tracking-wider">
                {{ $rnPost['cat'] }}
              </div>
            @endif
          </div>

          {{-- Card text ── --}}
          <div class="p-4">
            <h3 class="sccc-rn-title text-base font-bold leading-snug mb-2 font-display transition-colors">
              {!! $rnPost['title'] !!}
            </h3>
            <p class="sccc-rn-meta text-xs">
              {{ $rnPost['date'] }} · {{ $rnPost['readTime'] }} {{ __('min read', 'sage') }}
            </p>
          </div>

        </a>
      @endforeach
    </div>

  </section>
@endif


{{-- ============================================================
     GSAP ANIMATIONS
     Flushed by @stack('scripts') in layouts/single.blade.php.
     ============================================================ --}}
@push('scripts')
<script>
(function () {
  'use strict';

  if (typeof gsap === 'undefined') return;
  if (typeof ScrollTrigger !== 'undefined') gsap.registerPlugin(ScrollTrigger);

  // ── 1. Hero entrance ─────────────────────────────────────────
  gsap.timeline({ defaults: { ease: 'power3.out' } })
    .from('.h-entry header > div:first-child', { opacity: 0, y: 16, duration: 0.45 })
    .from('.p-name',                            { opacity: 0, y: 28, duration: 0.6  }, '-=0.25')
    .from('.entry-meta',                        { opacity: 0, y: 12, duration: 0.4  }, '-=0.2')
    .from('.u-featured',                        { opacity: 0, scale: 0.98, duration: 0.65 }, '-=0.15');

  if (typeof ScrollTrigger !== 'undefined') {

    // ── 2. Article body ───────────────────────────────────────────
    gsap.utils.toArray('.e-content > *').forEach((el, i) => {
      gsap.from(el, {
        scrollTrigger: { trigger: el, start: 'top 92%', toggleActions: 'play none none none' },
        opacity: 0, y: 18, duration: 0.45,
        delay: Math.min(i * 0.035, 0.2), ease: 'power2.out',
      });
    });

    // ── 3. Sidebar widgets ────────────────────────────────────────
    gsap.utils.toArray('#single-sidebar > *').forEach((el, i) => {
      gsap.from(el, {
        scrollTrigger: { trigger: el, start: 'top 88%', toggleActions: 'play none none none' },
        opacity: 0, x: 20, duration: 0.5, delay: i * 0.1, ease: 'power2.out',
      });
    });

    // ── 4. Read Next cards ────────────────────────────────────────
    gsap.utils.toArray('.read-next-card').forEach((card, i) => {
      gsap.from(card, {
        scrollTrigger: { trigger: card, start: 'top 90%', toggleActions: 'play none none none' },
        opacity: 0, y: 24, duration: 0.5, delay: i * 0.12, ease: 'power3.out',
      });
    });

  }

})();
</script>
@endpush