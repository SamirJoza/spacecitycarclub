{{--
   ============================================================================
   MEETING MINUTES - SINGLE VIEW TEMPLATE
   ============================================================================
   File path + filename: resources/views/partials/content-single-minutes.blade.php

   PURPOSE:
   This Blade partial renders a complete, detailed view of a single meeting's
   minutes. It displays all relevant information including attendees, decisions,
   action items, and supporting documents.

   ACCESS CONTROL:
   - This partial is intentionally restricted to logged-in users who have the
     `sccc_member` WordPress role.
   - Non-members do not receive the minutes content, attachments, PDF links, or
     previous/next minutes navigation in the rendered HTML.
   - There is no admin bypass in this file because the requested rule is role
     based: only users with the `sccc_member` role may view a single minutes item.

   THEME BEHAVIOR:
   - LIGHT MODE: Clean, minimal design with outlined status pills (white bg,
     colored borders and text). No gradient background effects.
   - DARK MODE: Rich design with soft-filled status pills (colored semi-transparent
     backgrounds). Includes subtle gradient glow effects.

   DESIGN SYSTEM:
   - Primary color: Blue (primary-500) for all headings and accents
   - Material Symbols icons throughout
   - Responsive layout (mobile-first, scales to desktop)
   - Consistent spacing and typography hierarchy

   DATA STRUCTURE:
   The template expects a $minutesSingle array with the following structure:
   - title: Meeting title
   - meeting_type: ['name', 'slug'] - Type of meeting (board, committee, etc.)
   - meeting_date: Formatted date string
   - meeting_date_raw: Raw date for processing
   - location: Meeting location
   - official_pdf_url: Link to exportable PDF
   - recap_summary: Executive summary of the meeting
   - attendance: ['type', 'label', 'count', 'names' => [...]]
   - content: Main minutes body (HTML)
   - motions: Array of decisions/votes
   - action_items: Array of tasks with owners and due dates
   - attachments: Array of supporting documents
   - navigation: ['prev', 'next'] - Adjacent meeting links
   - archive_url: Link back to meeting archive
   - not_found: Boolean flag for 404 state

   ============================================================================
--}}

@php
  // ============================================================================
  // REUSABLE CLASS DEFINITIONS
  // ============================================================================
  // These variables store commonly-used CSS classes to maintain consistency
  // and make the template easier to maintain.

  // Material Symbols icon base styling
  // Creates a consistent size/alignment for all icons throughout the template
  $msIcon = 'material-symbols-outlined inline-flex items-center justify-center shrink-0 w-5 h-5 leading-none overflow-hidden';

  // Remove underlines from UI navigation links
  // Used for breadcrumbs, buttons, and navigation elements (not prose content)
  $uiLinkNoUnderline = '!no-underline hover:!no-underline focus:!no-underline';

  // Main section headings (h2 level)
  // Always blue (primary-500) across both light and dark themes
  $sectionH2 = 'text-primary-500 text-xl font-black tracking-tight';

  // Inner content headings (h3 level)
  // Also always blue, but smaller and uppercase for visual hierarchy
  $innerH3 = 'text-primary-500 text-sm font-bold uppercase tracking-widest';

  // Extract meeting type label for navigation
  $typeLabel = $minutesSingle['meeting_type']['name'] ?? 'Meeting';

  /*
   * ---------------------------------------------------------------------------
   * Members-only access gate.
   * ---------------------------------------------------------------------------
   *
   * Why:
   * Single meeting minutes should only render for users who have the
   * `sccc_member` WordPress role. This keeps the minutes body, official PDF,
   * attachments, and previous/next navigation out of the DOM for visitors,
   * shoppers, or logged-in users without that role.
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

@if (!$scccMinutesCanView)
  <div class="max-w-3xl mx-auto w-full px-4 py-16">
    <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-8 md:p-10 text-center shadow-sm">
      <div class="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-full bg-primary-500/10 text-primary-500">
        <span class="{{ $msIcon }}" aria-hidden="true">lock</span>
      </div>

      <p class="mb-3 text-xs font-black uppercase tracking-[0.2em] text-primary-500">
        Members Only
      </p>

      <h1 class="mb-4 text-3xl md:text-4xl font-black tracking-tight text-slate-900 dark:text-white">
        Meeting Minutes
      </h1>

      <p class="mx-auto max-w-2xl text-base leading-relaxed text-slate-600 dark:text-slate-400">
        Space City Car Club meeting minutes are available to active members only.
        Please sign in with a member account to view this record.
      </p>

      @if (!$scccMinutesIsLoggedIn)
        <a href="{{ esc_url($scccMinutesLoginUrl) }}" class="mt-8 inline-flex items-center justify-center gap-2 rounded-full bg-primary-500 px-6 py-3 text-sm font-bold text-white {{ $uiLinkNoUnderline }} transition-all hover:scale-[1.02] active:scale-[0.98]">
          <span class="{{ $msIcon }} text-base" aria-hidden="true">login</span>
          Sign in to view minutes
        </a>
      @else
        <p class="mt-8 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/40 px-4 py-3 text-sm font-medium text-slate-600 dark:text-slate-400">
          You are signed in, but this account does not currently have the
          <span class="font-bold text-primary-500">sccc_member</span> role.
        </p>
      @endif
    </div>
  </div>

@elseif (!empty($minutesSingle['not_found']))
  <div class="max-w-7xl mx-auto w-full px-4 py-12">
    <div class="rounded-lg border border-slate-200 dark:border-slate-800 p-6 opacity-80 text-center bg-white dark:bg-slate-900">
      <p class="text-slate-700 dark:text-slate-200">Minutes not found.</p>
      @if (!empty($minutesSingle['archive_url']))
        <a href="{{ $minutesSingle['archive_url'] }}" class="text-primary-500 mt-4 inline-block {{ $uiLinkNoUnderline }}">
          Back to Archive
        </a>
      @endif
    </div>
  </div>

@else
  <main class="min-h-screen">
    <div class="gradient-glow flex flex-col items-center py-8 px-4 md:px-10">
      <div class="w-full max-w-[960px] flex flex-col gap-6">
        <nav class="flex items-center gap-2 px-4 py-2">
          <a class="text-slate-500 dark:text-slate-400 text-sm font-medium hover:text-primary-500 {{ $uiLinkNoUnderline }}"
             href="{{ $minutesSingle['archive_url'] }}">
            Archive
          </a>

          <span class="{{ $msIcon }} text-slate-400 dark:text-slate-600 text-xs" aria-hidden="true">chevron_right</span>

          @if (!empty($minutesSingle['meeting_date_raw']))
            <a class="text-slate-500 dark:text-slate-400 text-sm font-medium hover:text-primary-500 {{ $uiLinkNoUnderline }}"
               href="{{ $minutesSingle['archive_url'] }}">
              {{ wp_date('Y', strtotime($minutesSingle['meeting_date_raw'])) }} Meetings
            </a>
            <span class="{{ $msIcon }} text-slate-400 dark:text-slate-600 text-xs" aria-hidden="true">chevron_right</span>
          @endif

          <span class="text-primary-500 text-sm font-semibold">
            {{ $minutesSingle['meeting_date'] ?: $minutesSingle['title'] }}
          </span>
        </nav>

        <div class="flex flex-col md:flex-row justify-between items-start md:items-end gap-6 p-4">
          <div class="flex flex-col gap-4">
            @if (!empty($minutesSingle['meeting_type']))
              <div class="inline-flex items-center px-3 py-1 rounded-full bg-primary-500/10 text-primary-500 text-xs font-bold tracking-widest uppercase">
                @if (($minutesSingle['meeting_type']['slug'] ?? '') === 'board')
                  Official Board Records
                @elseif (($minutesSingle['meeting_type']['slug'] ?? '') === 'committee')
                  Committee Meeting Records
                @else
                  {{ $minutesSingle['meeting_type']['name'] }}
                @endif
              </div>
            @endif

            <h1 class="minutes-single-title text-4xl md:text-5xl font-black leading-tight tracking-tight text-slate-900 dark:text-white">
              {{ $minutesSingle['meeting_type']['name'] ?? 'Meeting' }} Minutes
              @if (!empty($minutesSingle['meeting_date']))
                <br/>
                <span class="text-primary-500">— {{ $minutesSingle['meeting_date'] }}</span>
              @endif
            </h1>

            <div class="flex flex-wrap gap-4 text-slate-600 dark:text-slate-400">
              @if (!empty($minutesSingle['location']))
                <div class="flex items-center gap-2">
                  <span class="{{ $msIcon }} text-lg" aria-hidden="true">location_on</span>
                  <span class="text-sm font-medium">{{ $minutesSingle['location'] }}</span>
                </div>
              @endif
            </div>
          </div>

          @if (!empty($minutesSingle['official_pdf_url']))
            <div class="flex gap-2">
              <a href="{{ $minutesSingle['official_pdf_url'] }}"
                 target="_blank" rel="noopener"
                 class="flex items-center gap-2 px-6 py-3 rounded-lg bg-primary-500 text-white text-sm font-bold
                        shadow-lg shadow-primary-500/20 hover:scale-[1.02] active:scale-[0.98] transition-all
                        {{ $uiLinkNoUnderline }}">
                <span class="{{ $msIcon }}" aria-hidden="true">picture_as_pdf</span>
                <span>Export to PDF</span>
              </a>
            </div>
          @endif
        </div>

        <div class="grid grid-cols-1 gap-8">
          @if (!empty($minutesSingle['recap_summary']))
            <section class="minutes-single-card bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 shadow-sm">
              <div class="flex items-start gap-3">
                <span class="{{ $msIcon }} !text-primary-500 mt-0.5" aria-hidden="true">summarize</span>
                <div class="flex flex-col gap-2">
                  <h2 class="{{ $sectionH2 }}">Recap Summary</h2>
                  <div class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
                    {!! wpautop($minutesSingle['recap_summary']) !!}
                  </div>
                </div>
              </div>
            </section>
          @endif

          @if (!empty($minutesSingle['attendance']))
            <section class="minutes-single-card bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden shadow-sm">
              <div class="border-b border-slate-100 dark:border-slate-800 px-6 py-4 flex items-center justify-between">
                <h2 class="{{ $sectionH2 }} flex items-center gap-2">
                  <span class="{{ $msIcon }} !text-primary-500" aria-hidden="true">groups</span>
                  Confirmed Attendees
                </h2>

                @if (!empty($minutesSingle['attendance']['count']))
                  <span class="text-xs font-medium text-slate-500 dark:text-slate-400">
                    {{ $minutesSingle['attendance']['count'] }} Members Present
                  </span>
                @endif
              </div>

              <div class="flex gap-3 p-6 flex-wrap">
                @if (($minutesSingle['attendance']['type'] ?? '') === 'membership')
                  <p class="text-sm text-slate-600 dark:text-slate-400">
                    {{ $minutesSingle['attendance']['label'] }}
                  </p>
                @elseif (!empty($minutesSingle['attendance']['names']))
                  @foreach ($minutesSingle['attendance']['names'] as $attendee)
                    <div class="minutes-attendee-pill flex h-10 items-center gap-x-3 rounded-full bg-slate-100 dark:bg-slate-800 pl-2 pr-4 ring-1 ring-inset ring-slate-200 dark:ring-slate-700">
                      <div class="size-7 rounded-full {{ $attendee['avatar_color'] }} flex items-center justify-center text-[10px] text-white font-bold">
                        {{ $attendee['initials'] }}
                      </div>
                      <p class="minutes-attendee-name text-sm font-semibold text-slate-900 dark:text-white">
                        {{ $attendee['name'] }}
                        @if (!empty($attendee['role']))
                          <span class="minutes-attendee-role text-slate-500 dark:text-slate-400 font-normal ml-1">
                            ({{ $attendee['role'] }})
                          </span>
                        @endif
                      </p>
                    </div>
                  @endforeach
                @endif
              </div>
            </section>
          @endif

          @if (!empty($minutesSingle['content']))
            <section class="minutes-single-card bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-8 shadow-sm">
              <article
                class="prose prose-slate dark:prose-invert max-w-none
                       prose-headings:text-primary-500 dark:prose-headings:text-primary-500
                       prose-a:underline prose-a:underline-offset-4 hover:prose-a:underline
                       prose-a:text-primary-600 dark:prose-a:text-primary-400"
              >
                {!! $minutesSingle['content'] !!}
              </article>
            </section>
          @endif

          @if (!empty($minutesSingle['motions']))
            <section class="minutes-single-card bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 shadow-sm">
              <div class="flex items-center gap-3 mb-4">
                <span class="{{ $msIcon }} !text-primary-500" aria-hidden="true">task_alt</span>
                <h2 class="{{ $sectionH2 }}">Motions / Decisions</h2>
              </div>

              <div class="space-y-4">
                @foreach ($minutesSingle['motions'] as $motion)
                  @php $outcome = $motion['outcome'] ?? ''; @endphp

                  <div class="rounded-xl border border-slate-200 dark:border-slate-800 p-4">
                    <div class="flex items-start justify-between gap-4">
                      <h3 class="{{ $innerH3 }} normal-case text-base font-semibold tracking-normal">
                        {{ $motion['text'] }}
                      </h3>

                      <span
                        class="motion-pill shrink-0 inline-flex items-center px-3 py-1 rounded-full text-xs font-bold ring-1 ring-inset
                               {{ $outcome === 'approved' ? 'motion-approved' : '' }}
                               {{ $outcome === 'rejected' ? 'motion-rejected' : '' }}
                               {{ $outcome === 'tabled' ? 'motion-tabled' : '' }}
                               {{ $outcome === 'pending' ? 'motion-pending' : '' }}
                               {{ $outcome === 'info' ? 'motion-info' : '' }}
                               {{ ($outcome === '' || !in_array($outcome, ['approved','rejected','tabled','pending','info'], true)) ? 'motion-default' : '' }}"
                      >
                        {{ $motion['outcome_label'] }}
                      </span>
                    </div>

                    @if (!empty($motion['notes']))
                      <div class="mt-3 text-sm text-slate-600 dark:text-slate-400">
                        {!! wpautop($motion['notes']) !!}
                      </div>
                    @endif
                  </div>
                @endforeach
              </div>
            </section>
          @endif

          @if (!empty($minutesSingle['action_items']))
            <section class="minutes-single-card bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 shadow-sm">
              <div class="flex items-center gap-3 mb-4">
                <span class="{{ $msIcon }} !text-primary-500" aria-hidden="true">checklist</span>
                <h2 class="{{ $sectionH2 }}">Action Items</h2>
              </div>

              <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($minutesSingle['action_items'] as $item)
                  @php $status = $item['status'] ?? ''; @endphp

                  <div class="py-4 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <div class="flex flex-col gap-1">
                      <h3 class="{{ $innerH3 }} normal-case text-base font-semibold tracking-normal">
                        {{ $item['task'] }}
                      </h3>

                      <div class="flex flex-wrap gap-3 text-xs text-slate-600 dark:text-slate-400">
                        @if (!empty($item['owner']))
                          <span>Owner: <span class="font-medium">{{ $item['owner'] }}</span></span>
                        @endif
                        @if (!empty($item['due']))
                          <span>Due: <span class="font-medium">{{ $item['due'] }}</span></span>
                        @endif
                      </div>
                    </div>

                    <span
                      class="action-pill inline-flex items-center px-3 py-1 rounded-full text-xs font-bold ring-1 ring-inset
                             {{ $status === 'done' ? 'action-done' : '' }}
                             {{ $status === 'in_progress' ? 'action-in-progress' : '' }}
                             {{ $status === 'blocked' ? 'action-blocked' : '' }}
                             {{ $status === 'deferred' ? 'action-deferred' : '' }}
                             {{ $status === 'cancelled' ? 'action-cancelled' : '' }}
                             {{ ($status === '' || !in_array($status, ['done','in_progress','blocked','deferred','cancelled'], true)) ? 'action-default' : '' }}"
                    >
                      {{ $item['status_label'] }}
                    </span>
                  </div>
                @endforeach
              </div>
            </section>
          @endif

          @if (!empty($minutesSingle['attachments']))
            <section class="minutes-single-card bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 shadow-sm">
              <div class="flex items-center gap-3 mb-4">
                <span class="{{ $msIcon }} !text-primary-500" aria-hidden="true">attach_file</span>
                <h2 class="{{ $sectionH2 }}">Attachments</h2>
              </div>

              <ul class="space-y-2">
                @foreach ($minutesSingle['attachments'] as $att)
                  <li>
                    <a href="{{ $att['url'] }}"
                       target="_blank" rel="noopener"
                       class="flex items-center gap-2 text-slate-700 dark:text-slate-300 hover:text-primary-500 transition-colors {{ $uiLinkNoUnderline }}">
                      <span class="{{ $msIcon }}" aria-hidden="true">description</span>
                      <span class="text-sm font-medium">{{ $att['label'] }}</span>
                    </a>
                  </li>
                @endforeach
              </ul>
            </section>
          @endif

          <div class="flex items-center justify-between p-4 mb-20 border-t border-slate-200 dark:border-slate-800">
            @if (!empty($minutesSingle['navigation']['prev']))
              <a href="{{ $minutesSingle['navigation']['prev']['url'] }}"
                 class="flex items-center gap-2 text-slate-500 dark:text-slate-400 hover:text-primary-500 transition-colors font-semibold {{ $uiLinkNoUnderline }}">
                <span class="{{ $msIcon }}" aria-hidden="true">keyboard_double_arrow_left</span>
                Previous {{ $typeLabel }} Meeting
              </a>
            @else
              <span class="flex items-center gap-2 text-slate-400 font-semibold">
                <span class="{{ $msIcon }}" aria-hidden="true">keyboard_double_arrow_left</span>
                Previous {{ $typeLabel }} Meeting
              </span>
            @endif

            @if (!empty($minutesSingle['navigation']['next']))
              <a href="{{ $minutesSingle['navigation']['next']['url'] }}"
                 class="flex items-center gap-2 text-slate-500 dark:text-slate-400 hover:text-primary-500 transition-colors font-semibold {{ $uiLinkNoUnderline }}">
                Next {{ $typeLabel }} Meeting
                <span class="{{ $msIcon }}" aria-hidden="true">keyboard_double_arrow_right</span>
              </a>
            @else
              <span class="flex items-center gap-2 text-slate-400 font-semibold">
                Next {{ $typeLabel }} Meeting
                <span class="{{ $msIcon }}" aria-hidden="true">keyboard_double_arrow_right</span>
              </span>
            @endif
          </div>
        </div>
      </div>
    </div>
  </main>

  <style>
    .gradient-glow {
      background: transparent;
    }

    html[data-theme="dark"] .gradient-glow {
      background:
        radial-gradient(circle at top right, rgba(19, 91, 236, 0.15), transparent 40%),
        radial-gradient(circle at bottom left, rgba(19, 91, 236, 0.05), transparent 40%);
    }

    html[data-theme="light"] .minutes-single-card {
      background: #fff;
      border-color: #e2e8f0;
      color: #0f172a;
    }

    html[data-theme="light"] .minutes-single-title {
      color: #0f172a !important;
    }

    html[data-theme="light"] .minutes-attendee-pill {
      background: #f1f5f9 !important;
      border-color: #e2e8f0 !important;
    }

    html[data-theme="light"] .minutes-attendee-name {
      color: #0f172a !important;
    }

    html[data-theme="light"] .minutes-attendee-role {
      color: #64748b !important;
    }

    html[data-theme="dark"] .motion-pill {
      box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
    }

    .motion-default {
      background: #fff;
      color: #334155;
      border-color: #cbd5e1;
    }

    html[data-theme="dark"] .motion-default {
      background: rgba(30, 41, 59, 0.3);
      color: #e2e8f0;
      border-color: rgba(255, 255, 255, 0.1);
    }

    html[data-theme="light"] .motion-approved {
      background: #fff;
      color: #047857;
      border-color: #059669;
    }

    html[data-theme="dark"] .motion-approved {
      background: rgba(6, 78, 59, 0.3);
      color: #a7f3d0;
      border-color: rgba(52, 211, 153, 0.2);
    }

    html[data-theme="light"] .motion-rejected {
      background: #fff;
      color: #be123c;
      border-color: #e11d48;
    }

    html[data-theme="dark"] .motion-rejected {
      background: rgba(136, 19, 55, 0.3);
      color: #fecdd3;
      border-color: rgba(251, 113, 133, 0.2);
    }

    html[data-theme="light"] .motion-tabled {
      background: #fff;
      color: #b45309;
      border-color: #d97706;
    }

    html[data-theme="dark"] .motion-tabled {
      background: rgba(120, 53, 15, 0.3);
      color: #fde68a;
      border-color: rgba(251, 191, 36, 0.2);
    }

    html[data-theme="light"] .motion-pending {
      background: #fff;
      color: #6d28d9;
      border-color: #7c3aed;
    }

    html[data-theme="dark"] .motion-pending {
      background: rgba(76, 29, 149, 0.3);
      color: #ddd6fe;
      border-color: rgba(167, 139, 250, 0.2);
    }

    html[data-theme="light"] .motion-info {
      background: #fff;
      color: #0369a1;
      border-color: #0284c7;
    }

    html[data-theme="dark"] .motion-info {
      background: rgba(12, 74, 110, 0.3);
      color: #bae6fd;
      border-color: rgba(56, 189, 248, 0.2);
    }

    html[data-theme="dark"] .action-pill {
      box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
    }

    html[data-theme="light"] .action-default {
      background: #fff;
      color: #334155;
      border-color: #cbd5e1;
    }

    html[data-theme="dark"] .action-default {
      background: rgba(30, 41, 59, 0.3);
      color: #e2e8f0;
      border-color: rgba(255, 255, 255, 0.1);
    }

    html[data-theme="light"] .action-done {
      background: #fff;
      color: #047857;
      border-color: #059669;
    }

    html[data-theme="dark"] .action-done {
      background: rgba(6, 78, 59, 0.3);
      color: #a7f3d0;
      border-color: rgba(52, 211, 153, 0.2);
    }

    html[data-theme="light"] .action-in-progress {
      background: #fff;
      color: #b45309;
      border-color: #d97706;
    }

    html[data-theme="dark"] .action-in-progress {
      background: rgba(120, 53, 15, 0.3);
      color: #fde68a;
      border-color: rgba(251, 191, 36, 0.2);
    }

    html[data-theme="light"] .action-blocked {
      background: #fff;
      color: #be123c;
      border-color: #e11d48;
    }

    html[data-theme="dark"] .action-blocked {
      background: rgba(136, 19, 55, 0.3);
      color: #fecdd3;
      border-color: rgba(251, 113, 133, 0.2);
    }

    html[data-theme="light"] .action-deferred {
      background: #fff;
      color: #0369a1;
      border-color: #0284c7;
    }

    html[data-theme="dark"] .action-deferred {
      background: rgba(12, 74, 110, 0.3);
      color: #bae6fd;
      border-color: rgba(56, 189, 248, 0.2);
    }

    html[data-theme="light"] .action-cancelled {
      background: #fff;
      color: #334155;
      border-color: #cbd5e1;
    }

    html[data-theme="dark"] .action-cancelled {
      background: rgba(30, 41, 59, 0.3);
      color: #e2e8f0;
      border-color: rgba(255, 255, 255, 0.1);
    }
  </style>
@endif