{{--
  Template Name: Member Directory
  File: resources/views/template-member-directory.blade.php

  Purpose
  ------------------------------------------------------------------------------
  Custom Sage page template for the Space City Car Club Member Directory.

  Page flow
  ------------------------------------------------------------------------------
  1) Members-only access check.
  2) If blocked, show a polished locked-state panel and do not render page content
     or directory data.
  3) If allowed, render the normal Gutenberg page content first.
     - This lets the page content area be used for the banner/intro.
  4) Render the directory toolbar directly below the Gutenberg content.
  5) Render member cards.
  6) Render pagination.
  7) Render one shared full-bio modal shell.

  Layout decision
  ------------------------------------------------------------------------------
  The hardcoded “Club Roster / Member Directory / intro copy” block was removed
  because the page content area is now responsible for the banner and intro.

  Fix: modal centering
  ------------------------------------------------------------------------------
  The modal is moved to <body> by the page script after initialization. This keeps
  the fixed-position dialog centered against the full viewport instead of being
  affected by parent wrappers, transforms, overflow, or stacking contexts.

  Fix: light-theme modal readability
  ------------------------------------------------------------------------------
  The modal is moved outside the directory wrapper, so it needs stronger
  theme-aware modal rules of its own. Light mode now uses a more solid panel,
  darker text, clearer separators, and a stronger backdrop so the full bio is
  readable over bright page/banner content.

  Data source
  ------------------------------------------------------------------------------
  Directory data is prepared by:
  - app/Support/Members/MemberDirectory.php

  Card partial
  ------------------------------------------------------------------------------
  Each member card is rendered by:
  - resources/views/partials/member-directory-card.blade.php

  Security
  ------------------------------------------------------------------------------
  This template hard-blocks non-members by calling:
  - App\Support\Members\MemberDirectory::currentUserCanView()

  The support class uses PMPro as the final active-membership authority.

  Privacy
  ------------------------------------------------------------------------------
  This directory intentionally does NOT show:
  - email
  - phone
  - birthdate
  - membership number
  - admin roles

  Visible profile fields include:
  - display name
  - profile image
  - member since
  - bio preview + full bio modal
  - garage mosaic
  - website
  - social links as icon + @username
--}}

@extends('layouts.app')

@section('content')
  @php
    /*
    |--------------------------------------------------------------------------
    | Directory Access + State
    |--------------------------------------------------------------------------
    |
    | Do the membership gate before rendering Gutenberg content or member data.
    | The user wanted the page secured so non-members cannot access the directory.
    |
    */

    $canViewDirectory = \App\Support\Members\MemberDirectory::currentUserCanView();

    $lockedState = $canViewDirectory
      ? []
      : \App\Support\Members\MemberDirectory::lockedState();

    $directory = $canViewDirectory
      ? \App\Support\Members\MemberDirectory::query($_GET)
      : [
          'filters' => [
            'search' => '',
            'vehicle_make' => '',
            'sort' => 'name_asc',
          ],
          'make_options' => [],
          'members' => [],
          'total' => 0,
          'page' => 1,
          'per_page' => 12,
          'total_pages' => 1,
          'pagination_links' => [],
        ];

    $querySearch = \App\Support\Members\MemberDirectory::QUERY_SEARCH;
    $queryMake = \App\Support\Members\MemberDirectory::QUERY_MAKE;
    $querySort = \App\Support\Members\MemberDirectory::QUERY_SORT;
    $queryPage = \App\Support\Members\MemberDirectory::QUERY_PAGE;

    $filters = $directory['filters'] ?? [];
    $members = $directory['members'] ?? [];
    $makeOptions = $directory['make_options'] ?? [];
    $paginationLinks = $directory['pagination_links'] ?? [];

    $searchValue = (string) ($filters['search'] ?? '');
    $selectedMake = (string) ($filters['vehicle_make'] ?? '');
    $selectedSort = (string) ($filters['sort'] ?? 'name_asc');

    $totalMembers = (int) ($directory['total'] ?? 0);
    $currentPage = (int) ($directory['page'] ?? 1);
    $totalPages = (int) ($directory['total_pages'] ?? 1);

    $resetUrl = remove_query_arg([
      $querySearch,
      $queryMake,
      $querySort,
      $queryPage,
    ]);

    $directoryCountLabel = sprintf(
      _n('%s member found', '%s members found', $totalMembers, 'sccc'),
      number_format_i18n($totalMembers)
    );
  @endphp

  <style>
    /*
    |--------------------------------------------------------------------------
    | Member Directory Template Styles
    |--------------------------------------------------------------------------
    |
    | Scoped to .sccc-member-directory so the page does not leak styles into
    | unrelated templates or blocks.
    |
    */

    .sccc-member-directory {
      --sccc-member-blue: #2fb6ff;
      --sccc-member-gradient: var(
        --sccc-header-underline-gradient,
        linear-gradient(
          90deg,
          rgba(113, 215, 255, 0) 0%,
          rgba(113, 215, 255, 0.96) 18%,
          rgba(67, 190, 255, 0.98) 48%,
          rgba(174, 113, 255, 0.92) 82%,
          rgba(174, 113, 255, 0) 100%
        )
      );

      --sccc-member-card-bg:
        radial-gradient(
          520px 230px at 18% 0%,
          color-mix(in oklab, var(--color-primary-500) 13%, transparent) 0%,
          transparent 64%
        ),
        linear-gradient(
          180deg,
          color-mix(in oklab, var(--color-surface) 88%, transparent),
          color-mix(in oklab, var(--color-primary-500) 10%, var(--color-bg))
        );

      --sccc-member-panel-bg:
        radial-gradient(
          520px 230px at 12% 0%,
          color-mix(in oklab, var(--color-primary-500) 13%, transparent) 0%,
          transparent 64%
        ),
        linear-gradient(
          180deg,
          color-mix(in oklab, var(--color-surface) 88%, transparent),
          color-mix(in oklab, var(--color-bg) 94%, transparent)
        );

      position: relative;
      isolation: isolate;
      overflow: hidden;
      color: var(--color-text);
      background:
        radial-gradient(
          920px 430px at 8% 0%,
          color-mix(in oklab, var(--color-primary-500) 12%, transparent) 0%,
          transparent 62%
        ),
        radial-gradient(
          820px 380px at 94% 12%,
          color-mix(in oklab, var(--color-accent-500) 8%, transparent) 0%,
          transparent 60%
        ),
        var(--color-bg);
    }

    .sccc-member-directory::before {
      content: "";
      position: absolute;
      inset: 0;
      z-index: -2;
      background-image:
        linear-gradient(
          color-mix(in oklab, var(--color-primary-500) 6%, transparent) 1px,
          transparent 1px
        ),
        linear-gradient(
          90deg,
          color-mix(in oklab, var(--color-primary-500) 6%, transparent) 1px,
          transparent 1px
        );
      background-size: 72px 72px;
      mask-image: linear-gradient(to bottom, black 0%, transparent 72%);
      pointer-events: none;
    }

    .sccc-member-directory::after {
      content: "";
      position: absolute;
      inset: 0;
      z-index: -1;
      background:
        linear-gradient(
          145deg,
          transparent 0 58%,
          color-mix(in oklab, var(--color-primary-500) 8%, transparent) 58.2%,
          transparent 61%
        ),
        linear-gradient(
          160deg,
          transparent 0 70%,
          color-mix(in oklab, var(--color-accent-500) 7%, transparent) 70.2%,
          transparent 72%
        );
      pointer-events: none;
    }

    .sccc-member-directory__content {
      position: relative;
      z-index: 1;
    }

    .sccc-member-directory__content > *:first-child {
      margin-top: 0;
    }

    .sccc-member-directory__content > *:last-child {
      margin-bottom: 0;
    }

    .sccc-member-directory__content .alignfull {
      margin-left: calc(50% - 50vw);
      margin-right: calc(50% - 50vw);
      max-width: 100vw;
      width: 100vw;
    }

    .sccc-member-directory-panel {
      position: relative;
      overflow: hidden;
      border-radius: 1.35rem;
      border: 1px solid color-mix(in oklab, var(--color-primary-500) 18%, transparent);
      background: var(--sccc-member-panel-bg);
      box-shadow:
        0 16px 34px rgba(0, 0, 0, 0.18),
        inset 0 1px 0 color-mix(in oklab, white 5%, transparent);
    }

    .sccc-member-directory-panel::before,
    .sccc-member-card::before {
      content: "";
      position: absolute;
      inset-inline: 1.25rem;
      top: 0;
      height: 2px;
      background: var(--sccc-member-gradient);
      opacity: 0.76;
      pointer-events: none;
    }

    .sccc-member-directory__kicker {
      color: var(--sccc-member-blue);
      font-size: 0.72rem;
      font-weight: 800;
      letter-spacing: 0.26em;
      line-height: 1.35;
      text-transform: uppercase;
    }

    .sccc-member-directory-toolbar {
      display: grid;
      gap: 1rem;
      padding: 1.1rem;
    }

    @media (min-width: 900px) {
      .sccc-member-directory-toolbar {
        grid-template-columns: minmax(0, 1.3fr) minmax(12rem, 0.7fr) minmax(12rem, 0.7fr) auto;
        align-items: end;
      }
    }

    .sccc-member-directory-field label {
      display: block;
      margin-bottom: 0.45rem;
      color: var(--color-muted);
      font-size: 0.72rem;
      font-weight: 800;
      letter-spacing: 0.14em;
      line-height: 1.25;
      text-transform: uppercase;
    }

    .sccc-member-directory-input,
    .sccc-member-directory-select {
      width: 100%;
      min-height: 2.85rem;
      border-radius: 0.9rem;
      border: 1px solid color-mix(in oklab, var(--color-primary-500) 22%, transparent);
      background:
        linear-gradient(
          180deg,
          color-mix(in oklab, var(--color-surface) 84%, transparent),
          color-mix(in oklab, var(--color-bg) 94%, transparent)
        );
      color: var(--color-text);
      padding: 0.78rem 0.95rem;
      box-shadow: inset 0 1px 0 color-mix(in oklab, white 5%, transparent);
    }

    .sccc-member-directory-select {
      padding-right: 2.35rem;
    }

    .sccc-member-directory-actions {
      display: flex;
      gap: 0.65rem;
      align-items: center;
      flex-wrap: wrap;
    }

    @media (min-width: 900px) {
      .sccc-member-directory-actions {
        justify-content: flex-end;
      }
    }

    .sccc-member-directory-button,
    .sccc-member-directory-reset {
      display: inline-flex;
      min-height: 2.85rem;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      border-radius: 999px;
      border: 1px solid color-mix(in oklab, var(--color-accent-500) 36%, transparent);
      background:
        linear-gradient(
          180deg,
          color-mix(in oklab, var(--color-surface) 84%, transparent),
          color-mix(in oklab, var(--color-bg) 94%, transparent)
        );
      color: var(--color-text);
      font-size: 0.9rem;
      font-weight: 800;
      line-height: 1;
      padding: 0.72rem 1rem;
      text-decoration: none;
      box-shadow:
        inset 0 1px 0 color-mix(in oklab, white 5%, transparent),
        0 0 16px color-mix(in oklab, var(--color-accent-500) 8%, transparent);
      transition:
        transform 180ms ease,
        border-color 180ms ease,
        box-shadow 180ms ease,
        color 180ms ease;
      cursor: pointer;
    }

    .sccc-member-directory-button:hover,
    .sccc-member-directory-reset:hover {
      transform: translateY(-1px);
      border-color: color-mix(in oklab, var(--color-accent-500) 56%, transparent);
      color: var(--sccc-member-blue);
      text-decoration: none;
      box-shadow:
        inset 0 1px 0 color-mix(in oklab, white 6%, transparent),
        0 0 20px color-mix(in oklab, var(--color-accent-500) 13%, transparent);
    }

    .sccc-member-directory-button {
      border-color: color-mix(in oklab, var(--sccc-member-blue) 42%, transparent);
    }

    .sccc-member-directory-meta {
      display: flex;
      flex-wrap: wrap;
      gap: 0.8rem;
      align-items: center;
      justify-content: space-between;
      color: var(--color-muted);
      font-size: 0.92rem;
      line-height: 1.5;
    }

    /*
    |--------------------------------------------------------------------------
    | Member Card
    |--------------------------------------------------------------------------
    */

    .sccc-member-card {
      position: relative;
      overflow: hidden;
      display: flex;
      min-height: 100%;
      flex-direction: column;
      border-radius: 1.35rem;
      border: 1px solid color-mix(in oklab, var(--color-primary-500) 18%, transparent);
      background: var(--sccc-member-card-bg);
      box-shadow:
        0 14px 32px rgba(0, 0, 0, 0.22),
        inset 0 1px 0 color-mix(in oklab, white 5%, transparent);
      transition:
        transform 220ms ease,
        border-color 220ms ease,
        box-shadow 220ms ease;
    }

    .sccc-member-card:hover {
      transform: translateY(-3px);
      border-color: color-mix(in oklab, var(--color-accent-500) 42%, var(--color-primary-500));
      box-shadow:
        0 22px 46px rgba(0, 0, 0, 0.28),
        0 0 24px color-mix(in oklab, var(--color-primary-500) 13%, transparent),
        inset 0 1px 0 color-mix(in oklab, white 6%, transparent);
    }

    .sccc-member-card__head {
      display: grid;
      grid-template-columns: auto minmax(0, 1fr);
      gap: 0.95rem;
      align-items: center;
      padding: 1.25rem 1.25rem 0.9rem;
    }

    .sccc-member-card__avatar-wrap {
      width: 4.25rem;
      height: 4.25rem;
      border-radius: 999px;
      padding: 2px;
      background:
        linear-gradient(
          135deg,
          color-mix(in oklab, var(--color-primary-500) 92%, white 8%),
          color-mix(in oklab, var(--color-accent-500) 70%, white 8%)
        );
      box-shadow:
        0 0 0 1px color-mix(in oklab, var(--color-primary-500) 28%, transparent),
        0 0 22px color-mix(in oklab, var(--color-primary-500) 20%, transparent);
    }

    .sccc-member-card__avatar {
      display: block;
      width: 100%;
      height: 100%;
      border-radius: inherit;
      object-fit: cover;
      background: var(--color-bg);
    }

    .sccc-member-card__avatar--fallback {
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--color-text);
      font-size: 1.45rem;
      font-weight: 900;
      text-transform: uppercase;
    }

    .sccc-member-card__identity {
      min-width: 0;
    }

    .sccc-member-card__eyebrow,
    .sccc-member-modal-content__eyebrow {
      margin: 0;
      color: var(--sccc-member-blue);
      font-size: 0.68rem;
      font-weight: 900;
      letter-spacing: 0.22em;
      line-height: 1.25;
      text-transform: uppercase;
    }

    .sccc-member-card__name {
      margin: 0.25rem 0 0;
      color: var(--color-text);
      font-family: var(--font-display);
      font-size: clamp(1.25rem, 1.7vw, 1.55rem);
      font-weight: 900;
      line-height: 1.1;
      letter-spacing: -0.025em;
    }

    .sccc-member-card__since {
      margin: 0.35rem 0 0;
      color: var(--color-muted);
      font-size: 0.88rem;
      line-height: 1.35;
    }

    .sccc-member-card__badges {
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem;
      padding: 0 1.25rem 0.95rem;
    }

    .sccc-member-card__badge {
      display: inline-flex;
      align-items: center;
      border-radius: 999px;
      border: 1px solid color-mix(in oklab, var(--color-primary-500) 24%, transparent);
      background:
        linear-gradient(
          180deg,
          color-mix(in oklab, var(--color-surface) 84%, transparent),
          color-mix(in oklab, var(--color-bg) 94%, transparent)
        );
      color: var(--color-text);
      font-size: 0.72rem;
      font-weight: 900;
      line-height: 1;
      padding: 0.48rem 0.7rem;
      box-shadow:
        inset 0 1px 0 color-mix(in oklab, white 5%, transparent),
        0 0 16px color-mix(in oklab, var(--color-primary-500) 8%, transparent);
    }

    .sccc-member-card__bio {
      padding: 0 1.25rem 1.1rem;
    }

    .sccc-member-card__bio p,
    .sccc-member-card__muted {
      margin: 0;
      color: var(--color-muted);
      font-size: 0.95rem;
      line-height: 1.62;
    }

    .sccc-member-card__bio-trigger {
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      margin-top: 0.75rem;
      border: 0;
      background: transparent;
      color: var(--sccc-member-blue);
      font-size: 0.9rem;
      font-weight: 900;
      line-height: 1;
      padding: 0;
      text-decoration: none;
      cursor: pointer;
      transition:
        gap 180ms ease,
        color 180ms ease,
        text-shadow 180ms ease;
    }

    .sccc-member-card__bio-trigger:hover {
      gap: 0.65rem;
      color: color-mix(in oklab, var(--sccc-member-blue) 82%, white 18%);
      text-shadow: 0 0 16px color-mix(in oklab, var(--sccc-member-blue) 34%, transparent);
    }

    /*
    |--------------------------------------------------------------------------
    | Garage Mosaic
    |--------------------------------------------------------------------------
    |
    | Updated layout:
    | - Primary vehicle remains the large hero tile.
    | - Secondary vehicles render as square tiles in a controlled right-side grid.
    | - When the final secondary slot count is odd, the last tile spans full width.
    | - This gives the stepped / mosaic feel you described and avoids the older
    |   generic "last thought" 2x2 feeling.
    */

    .sccc-member-card__garage {
      margin-top: auto;
      padding: 0 1.25rem 1.1rem;
    }

    .sccc-member-card__garage-head {
      display: flex;
      gap: 1rem;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 0.65rem;
      color: var(--color-muted);
      font-size: 0.72rem;
      font-weight: 900;
      letter-spacing: 0.14em;
      line-height: 1.25;
      text-transform: uppercase;
    }

    .sccc-member-mosaic {
      display: grid;
      grid-template-columns: minmax(0, 1.28fr) minmax(0, 1fr);
      gap: 0.5rem;
      align-items: stretch;
      min-height: 9.75rem;
    }

    .sccc-member-mosaic--empty {
      grid-template-columns: 1fr;
    }

    .sccc-member-mosaic__side {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 0.5rem;
      align-content: start;
      grid-auto-flow: row;
    }

    .sccc-member-mosaic__tile {
      position: relative;
      overflow: hidden;
      margin: 0;
      border-radius: 0.95rem;
      border: 1px solid color-mix(in oklab, var(--color-primary-500) 18%, transparent);
      background:
        radial-gradient(
          220px 120px at 20% 0%,
          color-mix(in oklab, var(--color-primary-500) 18%, transparent) 0%,
          transparent 65%
        ),
        color-mix(in oklab, var(--color-surface) 88%, var(--color-bg));
      box-shadow: inset 0 1px 0 color-mix(in oklab, white 5%, transparent);
      cursor: help;
    }

    .sccc-member-mosaic__tile img {
      display: block;
      width: 100%;
      height: 100%;
      min-height: inherit;
      object-fit: cover;
      transition:
        transform 320ms ease,
        filter 320ms ease;
    }

    .sccc-member-mosaic__tile:hover img,
    .sccc-member-mosaic__tile:focus img {
      transform: scale(1.045);
      filter: saturate(1.08) contrast(1.04);
    }

    .sccc-member-mosaic__tile--primary {
      min-height: 100%;
      height: 100%;
    }

    .sccc-member-mosaic__tile--small {
      aspect-ratio: 1 / 1;
      min-height: 0;
      border-radius: 0.75rem;
    }

    .sccc-member-mosaic__tile--span-2 {
      grid-column: 1 / -1;
      aspect-ratio: 2 / 1;
    }

    .sccc-member-mosaic__tile--more {
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--color-text);
      font-size: 1rem;
      font-weight: 900;
    }

    .sccc-member-mosaic__placeholder,
    .sccc-member-mosaic__empty {
      display: flex;
      min-height: 100%;
      align-items: center;
      justify-content: center;
      padding: 0.75rem;
      text-align: center;
      color: var(--color-muted);
      font-size: 0.75rem;
      font-weight: 900;
      line-height: 1.25;
      text-transform: uppercase;
      letter-spacing: 0.12em;
    }

    .sccc-member-mosaic__empty {
      min-height: 9rem;
      border-radius: 0.95rem;
      border: 1px dashed color-mix(in oklab, var(--color-primary-500) 24%, transparent);
    }

    .sccc-member-mosaic__tile[data-tooltip]::after {
      content: attr(data-tooltip);
      position: absolute;
      left: 50%;
      bottom: 0.55rem;
      z-index: 5;
      max-width: calc(100% - 1rem);
      transform: translate(-50%, 0.35rem);
      border-radius: 999px;
      background: color-mix(in oklab, black 72%, transparent);
      color: #fff;
      font-size: 0.68rem;
      font-weight: 900;
      line-height: 1.2;
      padding: 0.42rem 0.58rem;
      opacity: 0;
      pointer-events: none;
      white-space: nowrap;
      box-shadow: 0 10px 24px rgba(0, 0, 0, 0.28);
      transition:
        opacity 160ms ease,
        transform 160ms ease;
    }

    .sccc-member-mosaic__tile[data-tooltip]:hover::after,
    .sccc-member-mosaic__tile[data-tooltip]:focus::after {
      opacity: 1;
      transform: translate(-50%, 0);
    }

    .sccc-member-card__garage-summary {
      margin: 0.7rem 0 0;
      color: var(--color-muted);
      font-size: 0.86rem;
      line-height: 1.45;
    }

    /*
    |--------------------------------------------------------------------------
    | Website + Social Links
    |--------------------------------------------------------------------------
    */

    .sccc-member-card__links,
    .sccc-member-modal-content__links {
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem;
      align-items: center;
      padding: 0 1.25rem 1.25rem;
    }

    .sccc-member-card__link {
      display: inline-flex;
      min-width: 0;
      align-items: center;
      gap: 0.42rem;
      border-radius: 999px;
      border: 1px solid color-mix(in oklab, var(--color-primary-500) 24%, transparent);
      background:
        linear-gradient(
          180deg,
          color-mix(in oklab, var(--color-surface) 84%, transparent),
          color-mix(in oklab, var(--color-bg) 94%, transparent)
        );
      color: var(--color-text);
      font-size: 0.78rem;
      font-weight: 900;
      line-height: 1;
      max-width: 100%;
      padding: 0.55rem 0.68rem;
      text-decoration: none;
      box-shadow:
        inset 0 1px 0 color-mix(in oklab, white 5%, transparent),
        0 0 16px color-mix(in oklab, var(--color-primary-500) 7%, transparent);
      transition:
        transform 180ms ease,
        border-color 180ms ease,
        color 180ms ease,
        box-shadow 180ms ease;
    }

    .sccc-member-card__link:hover {
      transform: translateY(-1px);
      border-color: color-mix(in oklab, var(--color-accent-500) 52%, transparent);
      color: var(--sccc-member-blue);
      text-decoration: none;
      box-shadow:
        inset 0 1px 0 color-mix(in oklab, white 6%, transparent),
        0 0 20px color-mix(in oklab, var(--color-accent-500) 12%, transparent);
    }

    .sccc-member-card__link svg {
      display: block;
      width: 0.95rem;
      height: 0.95rem;
      flex: 0 0 auto;
      fill: currentColor;
    }

    .sccc-member-card__handle {
      display: inline-block;
      min-width: 0;
      max-width: 10rem;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */

    .sccc-member-directory-pagination .page-numbers {
      display: inline-flex;
      min-width: 2.5rem;
      min-height: 2.5rem;
      align-items: center;
      justify-content: center;
      border-radius: 999px;
      border: 1px solid color-mix(in oklab, var(--color-primary-500) 22%, transparent);
      background:
        linear-gradient(
          180deg,
          color-mix(in oklab, var(--color-surface) 84%, transparent),
          color-mix(in oklab, var(--color-bg) 94%, transparent)
        );
      color: var(--color-muted);
      padding: 0.55rem 0.95rem;
      text-decoration: none;
      transition:
        transform 180ms ease,
        border-color 180ms ease,
        color 180ms ease,
        box-shadow 180ms ease;
    }

    .sccc-member-directory-pagination .page-numbers:hover {
      transform: translateY(-1px);
      border-color: color-mix(in oklab, var(--color-accent-500) 60%, transparent);
      color: var(--color-text);
      box-shadow: 0 0 18px color-mix(in oklab, var(--color-accent-500) 12%, transparent);
    }

    .sccc-member-directory-pagination .page-numbers.current {
      border-color: transparent;
      background: linear-gradient(135deg, var(--color-primary-500), var(--color-accent-500));
      color: #fff;
      box-shadow: 0 0 22px color-mix(in oklab, var(--color-primary-500) 22%, transparent);
    }

    /*
    |--------------------------------------------------------------------------
    | Full Bio Modal
    |--------------------------------------------------------------------------
    |
    | The modal is moved to <body> by JavaScript. Because it is no longer inside
    | .sccc-member-directory after that move, it needs its own local CSS tokens.
    |
    */

    html.sccc-member-modal-is-open,
    body.sccc-member-modal-is-open {
      overflow: hidden;
    }

    .sccc-member-modal {
      --sccc-member-blue: #2fb6ff;
      --sccc-member-gradient: var(
        --sccc-header-underline-gradient,
        linear-gradient(
          90deg,
          rgba(113, 215, 255, 0) 0%,
          rgba(113, 215, 255, 0.96) 18%,
          rgba(67, 190, 255, 0.98) 48%,
          rgba(174, 113, 255, 0.92) 82%,
          rgba(174, 113, 255, 0) 100%
        )
      );
      --sccc-member-panel-bg:
        radial-gradient(
          520px 230px at 12% 0%,
          color-mix(in oklab, var(--color-primary-500) 13%, transparent) 0%,
          transparent 64%
        ),
        linear-gradient(
          180deg,
          color-mix(in oklab, var(--color-surface) 88%, transparent),
          color-mix(in oklab, var(--color-bg) 94%, transparent)
        );

      position: fixed;
      inset: 0;
      z-index: 999999;
      display: none;
      width: 100vw;
      min-height: 100dvh;
      box-sizing: border-box;
      padding: clamp(1rem, 4vw, 2rem);
      overflow-y: auto;
      color: var(--color-text);
      transform: none;
    }

    .sccc-member-modal[aria-hidden="false"] {
      display: grid;
      place-items: center;
    }

    .sccc-member-modal__backdrop {
      position: fixed;
      inset: 0;
      background:
        radial-gradient(
          700px 360px at 20% 0%,
          color-mix(in oklab, var(--color-primary-500) 18%, transparent),
          transparent 66%
        ),
        rgba(0, 0, 0, 0.68);
      backdrop-filter: blur(8px);
    }

    .sccc-member-modal__panel {
      position: relative;
      z-index: 1;
      width: min(44rem, calc(100vw - 2rem));
      max-height: calc(100dvh - 2rem);
      margin: auto;
      overflow: auto;
      border-radius: 1.35rem;
      border: 1px solid color-mix(in oklab, var(--color-primary-500) 22%, transparent);
      background: var(--sccc-member-panel-bg);
      box-shadow:
        0 30px 90px rgba(0, 0, 0, 0.44),
        inset 0 1px 0 color-mix(in oklab, white 6%, transparent);
    }

    .sccc-member-modal__bar {
      position: sticky;
      top: 0;
      z-index: 5;
      display: flex;
      justify-content: flex-end;
      padding: 0.9rem 0.9rem 0;
      pointer-events: none;
    }

    .sccc-member-modal__close {
      display: inline-flex;
      min-height: 2.35rem;
      align-items: center;
      justify-content: center;
      border-radius: 999px;
      border: 1px solid color-mix(in oklab, var(--color-primary-500) 24%, transparent);
      background:
        linear-gradient(
          180deg,
          color-mix(in oklab, var(--color-surface) 86%, transparent),
          color-mix(in oklab, var(--color-bg) 94%, transparent)
        );
      color: var(--color-text);
      font-size: 0.82rem;
      font-weight: 900;
      line-height: 1;
      padding: 0.55rem 0.8rem;
      cursor: pointer;
      pointer-events: auto;
    }

    .sccc-member-modal__body {
      padding: 0 1.25rem 1.35rem;
    }

    .sccc-member-modal-content__head {
      display: grid;
      grid-template-columns: auto minmax(0, 1fr);
      gap: 1rem;
      align-items: center;
      padding: 0.25rem 0 1.1rem;
    }

    .sccc-member-modal-content__avatar-wrap {
      width: 5.15rem;
      height: 5.15rem;
      border-radius: 999px;
      padding: 2px;
      background:
        linear-gradient(
          135deg,
          color-mix(in oklab, var(--color-primary-500) 92%, white 8%),
          color-mix(in oklab, var(--color-accent-500) 70%, white 8%)
        );
      box-shadow:
        0 0 0 1px color-mix(in oklab, var(--color-primary-500) 28%, transparent),
        0 0 24px color-mix(in oklab, var(--color-primary-500) 20%, transparent);
    }

    .sccc-member-modal-content__avatar {
      display: block;
      width: 100%;
      height: 100%;
      border-radius: inherit;
      object-fit: cover;
      background: var(--color-bg);
    }

    .sccc-member-modal-content__avatar--fallback {
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--color-text);
      font-size: 1.55rem;
      font-weight: 900;
      text-transform: uppercase;
    }

    .sccc-member-modal-content__title {
      margin: 0.25rem 0 0;
      color: var(--color-text);
      font-family: var(--font-display);
      font-size: clamp(1.7rem, 3vw, 2.35rem);
      font-weight: 900;
      line-height: 1.05;
      letter-spacing: -0.035em;
    }

    .sccc-member-modal-content__meta {
      margin: 0.4rem 0 0;
      color: var(--color-muted);
      font-size: 0.95rem;
      line-height: 1.45;
    }

    .sccc-member-modal-content__badge {
      margin-top: 0.6rem;
    }

    .sccc-member-modal-content__body {
      border-top: 1px solid color-mix(in oklab, var(--color-primary-500) 16%, transparent);
      padding-top: 1.15rem;
      margin-top: 1.15rem;
    }

    .sccc-member-modal-content__body h3 {
      margin: 0 0 0.75rem;
      color: var(--color-text);
      font-family: var(--font-display);
      font-size: 1.12rem;
      font-weight: 900;
      line-height: 1.2;
      letter-spacing: -0.02em;
    }

    .sccc-member-modal-content__body p {
      margin: 0;
      color: var(--color-muted);
      font-size: 1rem;
      line-height: 1.72;
    }

    .sccc-member-modal-content__links {
      padding: 1.15rem 0 0;
      margin-top: 1.15rem;
      border-top: 1px solid color-mix(in oklab, var(--color-primary-500) 16%, transparent);
    }

    /*
    |--------------------------------------------------------------------------
    | Full Bio Modal — Light Theme Readability
    |--------------------------------------------------------------------------
    |
    | Light mode needs a more opaque modal panel and stronger text contrast.
    | The modal sits on top of page/banner content, so low-opacity glass looks
    | too washed out in light mode.
    */

    html[data-theme="light"] .sccc-member-modal,
    html:not(.dark):not([data-theme="dark"]) .sccc-member-modal {
      --sccc-member-panel-bg:
        radial-gradient(
          620px 280px at 12% 0%,
          rgba(47, 182, 255, 0.14) 0%,
          rgba(47, 182, 255, 0.06) 38%,
          transparent 70%
        ),
        linear-gradient(
          180deg,
          rgba(255, 255, 255, 0.985),
          rgba(246, 249, 253, 0.985)
        );

      color: #0f172a;
    }

    html[data-theme="light"] .sccc-member-modal__backdrop,
    html:not(.dark):not([data-theme="dark"]) .sccc-member-modal__backdrop {
      background:
        radial-gradient(
          760px 380px at 20% 0%,
          rgba(47, 182, 255, 0.16),
          transparent 68%
        ),
        rgba(15, 23, 42, 0.64);
      backdrop-filter: blur(10px) saturate(0.9);
    }

    html[data-theme="light"] .sccc-member-modal__panel,
    html:not(.dark):not([data-theme="dark"]) .sccc-member-modal__panel {
      border-color: rgba(47, 124, 255, 0.36);
      background: var(--sccc-member-panel-bg);
      box-shadow:
        0 34px 95px rgba(15, 23, 42, 0.34),
        0 0 0 1px rgba(255, 255, 255, 0.82) inset,
        0 0 34px rgba(47, 182, 255, 0.15);
    }

    html[data-theme="light"] .sccc-member-modal__close,
    html:not(.dark):not([data-theme="dark"]) .sccc-member-modal__close {
      border-color: rgba(47, 124, 255, 0.34);
      background:
        linear-gradient(
          180deg,
          rgba(255, 255, 255, 0.98),
          rgba(236, 244, 255, 0.98)
        );
      color: #0f172a;
      box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08);
    }

    html[data-theme="light"] .sccc-member-modal-content__eyebrow,
    html:not(.dark):not([data-theme="dark"]) .sccc-member-modal-content__eyebrow {
      color: #0077c8;
    }

    html[data-theme="light"] .sccc-member-modal-content__title,
    html[data-theme="light"] .sccc-member-modal-content__body h3,
    html:not(.dark):not([data-theme="dark"]) .sccc-member-modal-content__title,
    html:not(.dark):not([data-theme="dark"]) .sccc-member-modal-content__body h3 {
      color: #0f172a;
    }

    html[data-theme="light"] .sccc-member-modal-content__meta,
    html[data-theme="light"] .sccc-member-modal-content__body p,
    html:not(.dark):not([data-theme="dark"]) .sccc-member-modal-content__meta,
    html:not(.dark):not([data-theme="dark"]) .sccc-member-modal-content__body p {
      color: #475569;
    }

    html[data-theme="light"] .sccc-member-modal-content__body,
    html[data-theme="light"] .sccc-member-modal-content__links,
    html:not(.dark):not([data-theme="dark"]) .sccc-member-modal-content__body,
    html:not(.dark):not([data-theme="dark"]) .sccc-member-modal-content__links {
      border-top-color: rgba(47, 124, 255, 0.22);
    }

    html[data-theme="light"] .sccc-member-modal .sccc-member-card__link,
    html:not(.dark):not([data-theme="dark"]) .sccc-member-modal .sccc-member-card__link {
      border-color: rgba(47, 124, 255, 0.28);
      background:
        linear-gradient(
          180deg,
          rgba(255, 255, 255, 0.98),
          rgba(236, 244, 255, 0.96)
        );
      color: #0f172a;
      box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.9),
        0 8px 18px rgba(15, 23, 42, 0.07);
    }

    html[data-theme="light"] .sccc-member-modal .sccc-member-card__link:hover,
    html:not(.dark):not([data-theme="dark"]) .sccc-member-modal .sccc-member-card__link:hover {
      border-color: rgba(0, 119, 200, 0.58);
      color: #0077c8;
      box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.92),
        0 10px 24px rgba(47, 124, 255, 0.15);
    }

    /*
    |--------------------------------------------------------------------------
    | Locked / Empty States
    |--------------------------------------------------------------------------
    */

    .sccc-member-directory-empty {
      padding: clamp(2rem, 5vw, 3.2rem);
      text-align: center;
    }

    .sccc-member-directory-empty h1,
    .sccc-member-directory-empty h2 {
      margin: 0;
      color: var(--color-text);
      font-family: var(--font-display);
      font-size: clamp(2rem, 5vw, 3.25rem);
      font-weight: 900;
      line-height: 1.05;
      letter-spacing: -0.04em;
    }

    .sccc-member-directory-empty p {
      max-width: 42rem;
      margin: 1rem auto 0;
      color: var(--color-muted);
      font-size: 1rem;
      line-height: 1.7;
    }

    .sccc-member-directory-empty__actions {
      display: flex;
      flex-wrap: wrap;
      gap: 0.75rem;
      justify-content: center;
      margin-top: 1.35rem;
    }

    @media (max-width: 620px) {
      .sccc-member-mosaic {
        grid-template-columns: 1fr;
      }

      .sccc-member-mosaic__side {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }

      .sccc-member-mosaic__tile--small {
        aspect-ratio: 1 / 1;
      }

      .sccc-member-mosaic__tile--span-2 {
        aspect-ratio: 2 / 1;
      }

      .sccc-member-card__links {
        padding-bottom: 1.15rem;
      }
    }

    @media (prefers-reduced-motion: reduce) {
      .sccc-member-card,
      .sccc-member-card *,
      .sccc-member-directory-button,
      .sccc-member-directory-reset,
      .sccc-member-directory-pagination .page-numbers {
        transition: none;
      }

      .sccc-member-card:hover,
      .sccc-member-directory-button:hover,
      .sccc-member-directory-reset:hover,
      .sccc-member-directory-pagination .page-numbers:hover {
        transform: none;
      }
    }
  </style>

  <main class="sccc-member-directory" data-sccc-member-directory="1">
    @if (! $canViewDirectory)
      {{--
        ======================================================================
        Locked State
        ======================================================================
        Non-members do not see the Gutenberg page content or the directory.
      --}}
      <section class="mx-auto max-w-5xl px-4 py-16 md:px-6 md:py-24" aria-labelledby="sccc-member-directory-locked-title">
        <div class="sccc-member-directory-panel sccc-member-directory-empty">
          <p class="sccc-member-directory__kicker">
            {{ __('Private Club Area', 'sccc') }}
          </p>

          <h1 id="sccc-member-directory-locked-title">
            {{ $lockedState['title'] ?? __('Members Only', 'sccc') }}
          </h1>

          <p>
            {{ $lockedState['message'] ?? __('The Member Directory is available to active Space City Car Club members only.', 'sccc') }}
          </p>

          <div class="sccc-member-directory-empty__actions">
            @if (! empty($lockedState['login_url']))
              <a class="sccc-member-directory-button" href="{{ esc_url($lockedState['login_url']) }}">
                {{ __('Member Login', 'sccc') }}
              </a>
            @endif

            @if (! empty($lockedState['join_url']))
              <a class="sccc-member-directory-reset" href="{{ esc_url($lockedState['join_url']) }}">
                {{ __('Join the Club', 'sccc') }}
              </a>
            @endif
          </div>
        </div>
      </section>
    @else
      {{--
        ======================================================================
        Gutenberg Page Content / Banner Area
        ======================================================================
        This lets the actual WordPress page content act as the banner/intro.
      --}}
      @while (have_posts())
        @php(the_post())

        <section class="sccc-member-directory__content">
          @php(the_content())
        </section>
      @endwhile

      {{--
        ======================================================================
        Directory Listing
        ======================================================================
      --}}
      <section class="mx-auto max-w-7xl px-4 py-10 md:px-6 md:py-14" aria-label="{{ esc_attr__('Member Directory listing', 'sccc') }}">
        {{--
          ====================================================================
          Search / Filter Toolbar
          ====================================================================
          Uses GET parameters so links can be copied/shared and pagination can
          preserve filters.
        --}}
        <form class="sccc-member-directory-panel sccc-member-directory-toolbar" method="get" action="{{ esc_url(get_permalink()) }}">
          <div class="sccc-member-directory-field">
            <label for="sccc_member_search">
              {{ __('Search', 'sccc') }}
            </label>

            <input
              id="sccc_member_search"
              class="sccc-member-directory-input"
              type="search"
              name="{{ esc_attr($querySearch) }}"
              value="{{ esc_attr($searchValue) }}"
              placeholder="{{ esc_attr__('Search name, bio, or vehicle…', 'sccc') }}"
            >
          </div>

          <div class="sccc-member-directory-field">
            <label for="sccc_vehicle_make">
              {{ __('Vehicle Make', 'sccc') }}
            </label>

            <select id="sccc_vehicle_make" class="sccc-member-directory-select" name="{{ esc_attr($queryMake) }}">
              <option value="">
                {{ __('All makes', 'sccc') }}
              </option>

              @foreach ($makeOptions as $makeSlug => $makeLabel)
                <option value="{{ esc_attr($makeSlug) }}" {!! selected($selectedMake, (string) $makeSlug, false) !!}>
                  {{ $makeLabel }}
                </option>
              @endforeach
            </select>
          </div>

          <div class="sccc-member-directory-field">
            <label for="sccc_member_sort">
              {{ __('Sort', 'sccc') }}
            </label>

            <select id="sccc_member_sort" class="sccc-member-directory-select" name="{{ esc_attr($querySort) }}">
              <option value="name_asc" {!! selected($selectedSort, 'name_asc', false) !!}>
                {{ __('Name A–Z', 'sccc') }}
              </option>
              <option value="newest" {!! selected($selectedSort, 'newest', false) !!}>
                {{ __('Newest members', 'sccc') }}
              </option>
              <option value="oldest" {!! selected($selectedSort, 'oldest', false) !!}>
                {{ __('Oldest members', 'sccc') }}
              </option>
            </select>
          </div>

          <div class="sccc-member-directory-actions">
            <button type="submit" class="sccc-member-directory-button">
              {{ __('Filter', 'sccc') }}
            </button>

            <a href="{{ esc_url($resetUrl) }}" class="sccc-member-directory-reset">
              {{ __('Reset', 'sccc') }}
            </a>
          </div>
        </form>

        <div class="sccc-member-directory-meta mt-5">
          <p>
            {{ $directoryCountLabel }}
          </p>

          @if ($totalPages > 1)
            <p>
              {{ sprintf(__('Page %1$d of %2$d', 'sccc'), $currentPage, $totalPages) }}
            </p>
          @endif
        </div>

        @if (! empty($members))
          <div class="mt-7 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($members as $member)
              @include('partials.member-directory-card', ['member' => $member])
            @endforeach
          </div>

          @if (! empty($paginationLinks))
            <nav
              class="sccc-member-directory-pagination mt-10 flex flex-wrap items-center justify-center gap-3 text-sm font-bold"
              aria-label="{{ esc_attr__('Member Directory pagination', 'sccc') }}"
            >
              @foreach ($paginationLinks as $paginationLink)
                {!! $paginationLink !!}
              @endforeach
            </nav>
          @endif
        @else
          <div class="sccc-member-directory-panel sccc-member-directory-empty mt-7">
            <p class="sccc-member-directory__kicker">
              {{ __('No Matches', 'sccc') }}
            </p>

            <h2>
              {{ __('No members found.', 'sccc') }}
            </h2>

            <p>
              {{ __('Try clearing the filters or searching for a different name, vehicle, or keyword.', 'sccc') }}
            </p>

            <div class="sccc-member-directory-empty__actions">
              <a href="{{ esc_url($resetUrl) }}" class="sccc-member-directory-button">
                {{ __('Clear Filters', 'sccc') }}
              </a>
            </div>
          </div>
        @endif
      </section>

      {{--
        ======================================================================
        Shared Full Bio Modal
        ======================================================================
        Each card owns its hidden modal <template>. This shared shell injects
        the clicked member’s template content into the modal body.

        The script moves this node to <body> so it is centered against the full
        viewport, not against a transformed/contained page wrapper.
      --}}
      <div
        class="sccc-member-modal"
        data-sccc-member-modal="1"
        aria-hidden="true"
        role="dialog"
        aria-modal="true"
        aria-label="{{ esc_attr__('Member bio', 'sccc') }}"
      >
        <div class="sccc-member-modal__backdrop" data-sccc-member-modal-close="1"></div>

        <div class="sccc-member-modal__panel" role="document">
          <div class="sccc-member-modal__bar">
            <button type="button" class="sccc-member-modal__close" data-sccc-member-modal-close="1">
              {{ __('Close', 'sccc') }}
            </button>
          </div>

          <div class="sccc-member-modal__body" data-sccc-member-modal-body="1"></div>
        </div>
      </div>

      <script>
        /**
         * Space City Car Club Member Directory modal
         * ------------------------------------------------------------------
         * Opens the full bio modal by copying member-specific <template>
         * content into one shared modal shell.
         *
         * Important:
         * The modal is moved to <body> so fixed positioning is centered against
         * the viewport instead of being affected by parent wrappers.
         */
        (function () {
          var root = document.querySelector('[data-sccc-member-directory="1"]');
          if (!root || root.__scccMemberDirectoryInit) return;

          root.__scccMemberDirectoryInit = true;

          var modal = root.querySelector('[data-sccc-member-modal="1"]');

          if (!modal) return;

          var modalBody = modal.querySelector('[data-sccc-member-modal-body="1"]');
          var lastActiveElement = null;

          if (!modalBody) return;

          /**
           * Move the modal to body after grabbing references.
           * This prevents fixed-position centering issues caused by transformed,
           * contained, overflow-hidden, or stacked theme wrappers.
           */
          if (modal.parentNode !== document.body) {
            document.body.appendChild(modal);
          }

          function openModal(templateId) {
            var template = document.getElementById(templateId);

            if (!template || !template.innerHTML) {
              return;
            }

            lastActiveElement = document.activeElement;

            modalBody.innerHTML = template.innerHTML;
            modal.setAttribute('aria-hidden', 'false');

            document.documentElement.classList.add('sccc-member-modal-is-open');
            document.body.classList.add('sccc-member-modal-is-open');

            var closeButton = modal.querySelector('[data-sccc-member-modal-close="1"]');
            if (closeButton) {
              closeButton.focus();
            }
          }

          function closeModal() {
            modal.setAttribute('aria-hidden', 'true');

            document.documentElement.classList.remove('sccc-member-modal-is-open');
            document.body.classList.remove('sccc-member-modal-is-open');

            modalBody.innerHTML = '';

            if (lastActiveElement && typeof lastActiveElement.focus === 'function') {
              lastActiveElement.focus();
            }

            lastActiveElement = null;
          }

          root.addEventListener('click', function (event) {
            var openButton = event.target.closest('[data-sccc-member-modal-open="1"]');

            if (!openButton) {
              return;
            }

            event.preventDefault();
            openModal(openButton.getAttribute('data-sccc-template-id'));
          });

          modal.addEventListener('click', function (event) {
            var closeButton = event.target.closest('[data-sccc-member-modal-close="1"]');

            if (!closeButton) {
              return;
            }

            event.preventDefault();
            closeModal();
          });

          document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') {
              return;
            }

            if (modal.getAttribute('aria-hidden') === 'false') {
              closeModal();
            }
          });
        })();
      </script>
    @endif
  </main>
@endsection