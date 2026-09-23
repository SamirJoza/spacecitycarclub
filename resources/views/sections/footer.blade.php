<footer class="site-footer pt-12 pb-10 border-t rule shrink-0" style="background: var(--bg)">
  <div class="max-w-7xl mx-auto px-4 grid md:grid-cols-4 gap-8 text-sm">
    {{-- Brand + intro --}}
    <div>
      <div class="flex gap-2">
        @svg('resources.images.logo', 'h-[72px] md:h-[100px] w-auto block')
      </div>
     
      <p class="mt-3 mx-auto" style="color: var(--muted)">
        <strong>Space City Car Club</strong><br>
        PO Box 399<br>
        Dickinson, Texas 77539
      </p>
    </div>

    {{-- Explore menu via Navi (2 columns, flattened) --}}
    <div class="md:col-span-2">
      <h4 class="font-semibold mb-3" style="color: var(--color-primary-500)">Explore</h4>
      <x-footer
        name="primary_navigation"
        :columns="2"
        inactive="hover:text-[var(--secondary)]"
        active="text-[var(--secondary)]"
        :flatten="true"
        :hashAsSpan="true"
        class="mt-2"
      />
    </div>

      {{-- Club message + Community --}}
      <div>
        <h4 class="font-semibold mb-3" style="color: var(--color-primary-500)">Space City Spirit</h4>
        <p style="color: var(--muted)">
          Space City Car Club brings together drivers, builders, families, and enthusiasts across Greater Houston.
          All makes. All models. All years. One community.
        </p>

        <div class="mt-6">
          <h4 class="font-semibold mb-2" style="color: var(--color-primary-500)">Community</h4>
          <p class="mb-3" style="color: var(--muted)">
            Follow the club, see event coverage, and connect with the Space City Car Club family.
          </p>
          <x-socials class="footer-socials mt-1" :show-labels="false" layout="row" />
        </div>
      </div>
  </div>

    {{-- Bottom bar --}}
  <div
    class="max-w-7xl mx-auto px-4 mt-8 pt-6 border-t rule flex flex-col md:flex-row md:items-center md:justify-between text-xs gap-2 md:gap-0"
    style="color: var(--muted)"
  >
    <div class="space-x-1">
      @php
        $startYear   = 2025;
        $currentYear = now()->year;
      @endphp

      <span>
        Copyright &copy; {{ $startYear }}
        @if ($currentYear > $startYear)
          - {{ $currentYear }}
        @endif
        Space City Car Club. All rights reserved.
      </span>
      <br />
      <span>
        Website created by
        <a href="https://samirjoza.dev" target="_blank" rel="noopener" class="hover:text-[var(--secondary)]">
          Samir Joza
        </a>.
      </span>
    </div>

    <div class="flex items-center gap-4">
      <x-legal
        name="legal_navigation"
        separator="•"
        class="gap-3"
      />

      {{-- Theme toggle --}}
      <div class="theme-toggle flex items-center gap-2" aria-label="Theme">
        <button
          type="button"
          data-theme-toggle="light"
          class="theme-toggle-btn"
          aria-pressed="false"
        >
          <span class="material-symbols-outlined text-lg">
            light_mode
          </span>
        </button>

        <button
          type="button"
          data-theme-toggle="dark"
          class="theme-toggle-btn"
          aria-pressed="false"
        >
          <span class="material-symbols-outlined text-lg">
            dark_mode
          </span>
        </button>
      </div>
    </div>
  </div>
</footer>

