{{--
   ============================================================================
   MEETING MINUTES - ARCHIVE VIEW TEMPLATE
   ============================================================================
   File path + filename: resources/views/archive-minutes.blade.php

   PURPOSE:
   This Blade template renders the meeting minutes archive, grouped by month,
   with pagination, a month jump control, client-side search, and archive cards.

   ACCESS CONTROL:
   - This template is intentionally restricted to logged-in users who have the
     `sccc_member` WordPress role.
   - Non-members do not receive the archive controls, archive groups, archive
     cards, or item links in the rendered HTML.
   - There is no admin bypass in this file because the requested rule is role
     based: only users with the `sccc_member` role may view the minutes archive.

   WHY THIS FILE CHECKS THE ROLE:
   - The archive template is the last rendering layer before the archive content
     reaches the browser.
   - Keeping the guard here ensures the archive UI and all items inside it are
     hidden even if the route is visited directly.
   - The same role gate is also applied to the single minutes partial so direct
     single-entry URLs are protected too.

   EXISTING BEHAVIOR PRESERVED:
   - Pagination structure remains intact.
   - The `minutes-archive-pagination` CSS hook remains intact.
   - Search, jump-to-month, and scroll-to-top behavior remains unchanged for
     authorized members.
   ============================================================================
--}}
@extends('layouts.app')

@php
  /*
   * ---------------------------------------------------------------------------
   * Members-only access gate.
   * ---------------------------------------------------------------------------
   *
   * Why:
   * Meeting minutes should only render for users who have the `sccc_member`
   * WordPress role. This keeps the archive controls and every archive item out
   * of the DOM for visitors, shoppers, or logged-in users without that role.
   *
   * Important:
   * This is a strict role check. There is intentionally no admin fallback unless
   * that admin account also has the `sccc_member` role.
   */
  $scccMinutesCurrentUser = function_exists('wp_get_current_user')
      ? wp_get_current_user()
      : null;

  $scccMinutesUserRoles = $scccMinutesCurrentUser instanceof \WP_User
      ? (array) $scccMinutesCurrentUser->roles
      : [];

  $scccMinutesCanView = in_array('sccc_member', $scccMinutesUserRoles, true);

  $scccMinutesIsLoggedIn = function_exists('is_user_logged_in')
      ? is_user_logged_in()
      : false;

  /*
   * Build an absolute current URL for the login redirect.
   *
   * Why:
   * wp_login_url() expects an absolute redirect URL, so we convert the current
   * request URI into a full site URL before passing it in.
   */
  $scccMinutesRequestUri = !empty($_SERVER['REQUEST_URI'])
      ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI']))
      : '';

  $scccMinutesCurrentUrl = $scccMinutesRequestUri !== ''
      ? home_url($scccMinutesRequestUri)
      : home_url('/');

  $scccMinutesLoginUrl = function_exists('wp_login_url')
      ? wp_login_url($scccMinutesCurrentUrl)
      : $scccMinutesCurrentUrl;
@endphp

@section('content')
  @if (!$scccMinutesCanView)
    <section class="max-w-3xl mx-auto w-full px-4 py-16">
      <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-8 md:p-10 text-center shadow-sm">
        <div class="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-full bg-primary-500/10 text-primary-500">
          <span class="material-symbols-outlined" aria-hidden="true">lock</span>
        </div>

        <p class="mb-3 text-xs font-black uppercase tracking-[0.2em] text-primary-500">
          Members Only
        </p>

        <h1 class="mb-4 text-3xl md:text-4xl font-black tracking-tight text-slate-900 dark:text-white">
          Meeting Minutes Archive
        </h1>

        <p class="mx-auto max-w-2xl text-base leading-relaxed text-slate-600 dark:text-slate-400">
          Space City Car Club meeting minutes are available to active members only.
          Please sign in with a member account to view the archive.
        </p>

        @if (!$scccMinutesIsLoggedIn)
          <a href="{{ esc_url($scccMinutesLoginUrl) }}" class="mt-8 inline-flex items-center justify-center gap-2 rounded-full bg-primary-500 px-6 py-3 text-sm font-bold text-white no-underline transition-all hover:scale-[1.02] active:scale-[0.98]">
            <span class="material-symbols-outlined text-base" aria-hidden="true">login</span>
            Sign in to view minutes
          </a>
        @else
          <p class="mt-8 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/40 px-4 py-3 text-sm font-medium text-slate-600 dark:text-slate-400">
            You are signed in, but this account does not currently have the
            <span class="font-bold text-primary-500">sccc_member</span> role.
          </p>
        @endif
      </div>
    </section>
  @else
    <div class="max-w-7xl mx-auto w-full px-4 py-12">
      {{-- Page Title + Intro --}}
      <div class="mb-12 text-center">
        <h1 class="text-4xl md:text-6xl font-black tracking-tight mb-4">{{ $minutesArchive['archive_title'] }}</h1>
        <p class="text-slate-600 dark:text-[#9da6b9] text-lg max-w-2xl mx-auto">
          {{ $minutesArchive['archive_intro'] }}
        </p>
      </div>

      {{-- Controls Row: Jump To, Pagination, Search --}}
      <div class="mb-12 flex flex-col md:flex-row items-center justify-between gap-6 border-b border-slate-200 dark:border-slate-800 pb-8">
        {{-- Jump To Dropdown --}}
        <div class="flex items-center gap-2">
          <span class="text-sm font-bold uppercase tracking-widest text-slate-400 dark:text-slate-500">Jump to</span>
          <select
            id="jump-to-month"
            class="bg-white dark:bg-[#1c2433] border border-slate-200 dark:border-slate-800 rounded-lg text-sm font-bold focus:ring-2 focus:ring-primary-500 py-2 px-4 outline-none cursor-pointer text-slate-900 dark:text-white"
          >
            <option value="">Current Period</option>
            @foreach ($minutesArchive['all_groups'] as $group)
              <option value="{{ $group['month_key'] }}" {{ $minutesArchive['selected_month'] === $group['month_key'] ? 'selected' : '' }}>{{ $group['month'] }}</option>
            @endforeach
          </select>
        </div>

        {{-- Pagination Controls --}}
        <div class="minutes-archive-pagination flex items-center gap-2">
          @if ($minutesArchive['pagination']['has_prev'])
            <a href="{{ $minutesArchive['pagination']['prev_page'] }}" class="flex items-center justify-center rounded-full h-10 w-10 border border-slate-200 dark:border-slate-800 hover:bg-primary-500 hover:text-white transition-all text-slate-900 dark:text-white no-underline">
              <span class="material-symbols-outlined">chevron_left</span>
            </a>
          @else
            <button class="flex items-center justify-center rounded-full h-10 w-10 border border-slate-200 dark:border-slate-800 hover:bg-primary-500 hover:text-white transition-all disabled:opacity-30 disabled:hover:bg-transparent disabled:hover:text-inherit text-slate-900 dark:text-white" disabled>
              <span class="material-symbols-outlined">chevron_left</span>
            </button>
          @endif

          {{-- Page Number Pills --}}
          <div class="flex gap-1">
            @foreach ($minutesArchive['pagination']['page_numbers'] as $page)
              @if ($page['is_current'])
                <span class="px-4 py-2 rounded-lg bg-primary-500 text-white font-bold text-sm">{{ $page['number'] }}</span>
              @else
                <a href="{{ $page['url'] }}" class="px-4 py-2 rounded-lg hover:bg-slate-200 dark:hover:bg-slate-800 text-sm transition-all font-medium text-slate-900 dark:text-white no-underline">{{ $page['number'] }}</a>
              @endif
            @endforeach
          </div>

          @if ($minutesArchive['pagination']['has_next'])
            <a href="{{ $minutesArchive['pagination']['next_page'] }}" class="flex items-center justify-center rounded-full h-10 w-10 border border-slate-200 dark:border-slate-800 hover:bg-primary-500 hover:text-white transition-all text-slate-900 dark:text-white no-underline">
              <span class="material-symbols-outlined">chevron_right</span>
            </a>
          @else
            <button class="flex items-center justify-center rounded-full h-10 w-10 border border-slate-200 dark:border-slate-800 hover:bg-primary-500 hover:text-white transition-all text-slate-900 dark:text-white" disabled>
              <span class="material-symbols-outlined">chevron_right</span>
            </button>
          @endif
        </div>

        {{-- Search Field --}}
        <div class="relative w-full md:w-64 group">
          <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400 dark:text-slate-500">
            <span class="material-symbols-outlined text-sm">search</span>
          </div>
          <input
            class="block w-full h-10 pl-10 pr-4 bg-white dark:bg-[#1c2433] border border-slate-200 dark:border-slate-800 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm transition-all text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500"
            placeholder="Search archive..."
            type="text"
            id="minutes-search"
          />
        </div>
      </div>

      {{-- Archive Groups --}}
      @if (!empty($minutesArchive['groups']))
        <div class="space-y-24" id="minutes-sections">
          @foreach ($minutesArchive['groups'] as $group)
            <section id="month-{{ $group['month_key'] }}" data-month-key="{{ $group['month_key'] }}" class="month-section">
              <div class="flex items-end justify-between mb-8 px-2">
                <div>
                  <h2 class="text-4xl font-black {{ $group['is_current'] ? 'text-primary-500' : 'text-slate-400 dark:text-slate-600' }}">
                    {{ $group['month'] }}
                  </h2>
                  <div class="h-1.5 w-24 {{ $group['is_current'] ? 'bg-primary-500' : 'bg-slate-200 dark:bg-slate-800' }} mt-2 rounded-full"></div>
                </div>
                <p class="text-slate-400 text-sm font-medium">{{ count($group['items']) }} {{ count($group['items']) === 1 ? 'Meeting' : 'Meetings' }} Held</p>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 pb-6">
                @foreach ($group['items'] as $item)
                  <div>
                    @if ($group['is_current'])
                      <a href="{{ $item['permalink'] }}" class="minutes-archive-card block h-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 hover:shadow-2xl hover:shadow-primary-500/20 transition-all border-b-4 border-b-primary-500 group cursor-pointer relative overflow-hidden no-underline">
                        <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                          <span class="material-symbols-outlined text-6xl">{{ $item['meeting_type_icon'] }}</span>
                        </div>
                        <div class="mb-6 flex items-center gap-3">
                          <div class="bg-primary-500/10 text-primary-500 p-2 rounded-lg">
                            <span class="material-symbols-outlined">{{ $item['meeting_type_icon'] }}</span>
                          </div>
                          <span class="text-xs font-bold uppercase tracking-widest text-primary-500">
                            {{ $item['meeting_type']['name'] ?? 'Meeting' }}
                          </span>
                        </div>
                        <h3 class="text-xl font-bold mb-2 group-hover:text-primary-500 transition-colors text-slate-900 dark:text-primary-500">
                          {{ $item['meeting_date_label'] ?: $item['title'] }}
                        </h3>
                        <p class="text-slate-500 dark:text-slate-400 text-sm mb-6 leading-relaxed">
                          {{ $item['excerpt'] }}
                        </p>
                        <div class="flex items-center text-primary-500 font-bold text-sm">
                          View Minutes
                          <span class="material-symbols-outlined ml-1 group-hover:translate-x-1 transition-transform">arrow_right_alt</span>
                        </div>
                      </a>
                    @else
                      <a href="{{ $item['permalink'] }}" class="minutes-archive-card block h-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 hover:shadow-lg transition-all group cursor-pointer relative overflow-hidden no-underline">
                        <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                          <span class="material-symbols-outlined text-6xl">{{ $item['meeting_type_icon'] }}</span>
                        </div>
                        <div class="mb-6 flex items-center gap-3">
                          <div class="bg-slate-100 dark:bg-slate-800 text-slate-500 p-2 rounded-lg group-hover:bg-primary-500/10 group-hover:text-primary-500 transition-colors">
                            <span class="material-symbols-outlined">{{ $item['meeting_type_icon'] }}</span>
                          </div>
                          <span class="text-xs font-bold uppercase tracking-widest text-slate-500">
                            {{ $item['meeting_type']['name'] ?? 'Meeting' }}
                          </span>
                        </div>
                        <h3 class="text-xl font-bold mb-2">
                          {{ $item['meeting_date_label'] ?: $item['title'] }}
                        </h3>
                        <p class="text-slate-500 dark:text-slate-400 text-sm mb-6 leading-relaxed">
                          {{ $item['excerpt'] }}
                        </p>
                        <div class="flex items-center text-slate-400 group-hover:text-primary-500 font-bold text-sm transition-colors">
                          View Minutes
                          <span class="material-symbols-outlined ml-1 group-hover:translate-x-1 transition-transform">arrow_right_alt</span>
                        </div>
                      </a>
                    @endif
                  </div>
                @endforeach
              </div>
            </section>
          @endforeach
        </div>

        {{-- “Load More” CTA --}}
        @if ($minutesArchive['pagination']['has_next'])
          <div class="mt-24 pt-12 border-t border-dashed border-slate-200 dark:border-slate-800 text-center">
            <a href="{{ $minutesArchive['pagination']['next_page'] }}" class="inline-flex items-center gap-2 px-8 py-4 bg-slate-200 dark:bg-slate-800 rounded-full font-bold hover:bg-primary-500 hover:text-white transition-all group no-underline text-slate-900 dark:text-white">
              Load Previous 3 Months
              <span class="material-symbols-outlined group-hover:rotate-90 transition-transform">history</span>
            </a>
          </div>
        @endif
      @else
        {{-- Empty State --}}
        <div class="rounded-lg border border-slate-200 dark:border-slate-800 p-6 opacity-80 text-center">
          <p>No meeting minutes found yet.</p>
        </div>
      @endif
    </div>

    {{-- Scroll-to-top Button --}}
    <button class="fixed bottom-8 right-8 z-50 hidden items-center justify-center rounded-full h-12 w-12 bg-primary-500 text-white shadow-xl hover:scale-110 active:scale-95 transition-all" onclick="window.scrollTo({top: 0, behavior: 'smooth'})" id="scroll-to-top">
      <span class="material-symbols-outlined">arrow_upward</span>
    </button>

    <script>
      document.addEventListener('DOMContentLoaded', function() {
        // Search functionality
        const searchInput = document.getElementById('minutes-search');
        if (searchInput) {
          searchInput.addEventListener('input', function(e) {
            const query = e.target.value.toLowerCase().trim();
            const sections = document.querySelectorAll('.month-section');

            if (query === '') {
              // Show all if search is empty
              sections.forEach(section => {
                section.style.display = 'block';
                section.querySelectorAll('.carousel-container > div').forEach(card => {
                  card.style.display = 'block';
                });
              });
              return;
            }

            sections.forEach(section => {
              const cards = section.querySelectorAll('.carousel-container > div');
              let sectionHasMatch = false;

              cards.forEach(card => {
                const cardText = card.textContent.toLowerCase();
                const cardMatches = cardText.includes(query);

                if (cardMatches) {
                  card.style.display = 'block';
                  sectionHasMatch = true;
                } else {
                  card.style.display = 'none';
                }
              });

              // Show section only if it has at least one matching card
              section.style.display = sectionHasMatch ? 'block' : 'none';
            });
          });
        }

        // Jump to month functionality
        const jumpToSelect = document.getElementById('jump-to-month');
        if (jumpToSelect) {
          jumpToSelect.addEventListener('change', function(e) {
            const monthKey = e.target.value;
            if (monthKey) {
              // Redirect to page 1 with jump_to parameter to reorder months
              const url = new URL(window.location.href);
              url.searchParams.set('jump_to', monthKey);
              url.searchParams.delete('page'); // Reset to page 1
              window.location.href = url.toString();
            } else {
              // Reset to top if "Current Period" is selected
              const url = new URL(window.location.href);
              url.searchParams.delete('jump_to');
              url.searchParams.delete('page');
              window.location.href = url.toString();
            }
          });
        }

        // Scroll to selected month section if jump_to parameter is set
        const urlParams = new URLSearchParams(window.location.search);
        const jumpToMonth = urlParams.get('jump_to');
        if (jumpToMonth) {
          setTimeout(() => {
            const targetSection = document.querySelector('#month-' + jumpToMonth);
            if (targetSection) {
              targetSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
              targetSection.style.transition = 'background-color 0.3s';
              targetSection.style.backgroundColor = 'rgba(19, 91, 236, 0.1)';
              setTimeout(() => {
                targetSection.style.backgroundColor = '';
              }, 2000);
            }
          }, 300);
        }

        // Scroll to top button visibility
        const scrollBtn = document.getElementById('scroll-to-top');
        if (scrollBtn) {
          window.addEventListener('scroll', function() {
            if (window.pageYOffset > 300) {
              scrollBtn.classList.remove('hidden');
              scrollBtn.classList.add('flex');
            } else {
              scrollBtn.classList.add('hidden');
              scrollBtn.classList.remove('flex');
            }
          });
        }
      });
    </script>
  @endif
@endsection