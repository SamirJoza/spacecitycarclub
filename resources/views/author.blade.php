{{--
  File: resources/views/author.blade.php

  Purpose:
  - Custom author archive template for the Space City Car Club Sage theme.
  - Uses the native WordPress author archive query.
  - Keeps post cards visually aligned with the existing blog listing card style.
  - Uses the top masthead as archive/context messaging.
  - Uses the sidebar author card for the actual author profile.
  - Displays member social profile links as icon + @username so multiple
    accounts on the same platform are easy to distinguish.
  - Supports the member Website field as a separate profile link.
  - Adds a real WordPress widget sidebar below the compressed author card.
  - Keeps author-specific category/content-type navigation below the widget area.

  Important Fix:
  - Do NOT use @{{ $social['username'] }} for the visible handle.
  - In Blade, @{{ ... }} intentionally escapes the Blade echo and leaves the
    raw curly-brace expression in the rendered HTML.
  - Use {{ '@' . $social['username'] }} instead.

  Data Sources:
  - Author/user context comes from the current WordPress author archive.
  - Member website:
      sccc_member_website
  - Member social links:
      sccc_member_social_links
        - platform
        - username
        - url

  Social Display Rules:
  - Only approved platforms are displayed:
      Facebook, Instagram, X, TikTok, YouTube.
  - Multiple rows per platform are allowed.
  - Frontend output is icon + @username.
  - Older rows without username get a best-effort username from the URL path.
  - Rows without a usable URL are skipped unless URL can be built from
    platform + username.

  Important Assumption:
  - A widget area with the ID "author-archive-sidebar" is registered elsewhere
    in the theme.
--}}

@extends('layouts.app')

@section('content')
  @php
    /*
    |--------------------------------------------------------------------------
    | Current Author Data
    |--------------------------------------------------------------------------
    |
    | WordPress exposes the active author archive object through
    | get_queried_object(). We collect the display data once here so the Blade
    | markup remains easier to maintain and read.
    |
    */

    $author = get_queried_object();
    $authorId = ($author instanceof WP_User) ? (int) $author->ID : 0;

    $authorName = $authorId
      ? get_the_author_meta('display_name', $authorId)
      : __('Author', 'sage');

    $authorDescription = $authorId
      ? get_the_author_meta('description', $authorId)
      : '';

    $authorAvatar = $authorId
      ? get_avatar_url($authorId, ['size' => 180])
      : '';

    $authorInitial = function_exists('mb_substr')
      ? mb_substr($authorName, 0, 1)
      : substr($authorName, 0, 1);

    /*
    |--------------------------------------------------------------------------
    | Member Website
    |--------------------------------------------------------------------------
    |
    | This uses the new member-level website field instead of WordPress core
    | user_url. That keeps this author template aligned with the member profile
    | fields we just added.
    |
    */

    $authorWebsite = '';

    if ($authorId) {
      $authorAcfKey = 'user_' . $authorId;

      if (function_exists('get_field')) {
        $authorWebsite = (string) (get_field('sccc_member_website', $authorAcfKey) ?: '');
      }

      if ($authorWebsite === '') {
        $authorWebsite = (string) get_user_meta($authorId, 'sccc_member_website', true);
      }

      $authorWebsite = esc_url_raw($authorWebsite);
    }

    /*
    |--------------------------------------------------------------------------
    | Social Platform Definitions
    |--------------------------------------------------------------------------
    |
    | The icons are hardcoded inline SVGs so this template does not introduce
    | another dependency. The SVGs are only used for the approved platform keys.
    |
    */

    $authorSocialPlatforms = [
      'facebook' => [
        'label' => 'Facebook',
        'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M14.2 8.4V6.7c0-.8.6-1 1-1h2.6V2.1L14.4 2c-3.4 0-4.9 2-4.9 4.8v1.6H6.4v3.8h3.1V22h4.1v-9.8h3.3l.5-3.8h-3.2Z"/></svg>',
      ],
      'instagram' => [
        'label' => 'Instagram',
        'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M7.5 2h9A5.5 5.5 0 0 1 22 7.5v9a5.5 5.5 0 0 1-5.5 5.5h-9A5.5 5.5 0 0 1 2 16.5v-9A5.5 5.5 0 0 1 7.5 2Zm0 2A3.5 3.5 0 0 0 4 7.5v9A3.5 3.5 0 0 0 7.5 20h9a3.5 3.5 0 0 0 3.5-3.5v-9A3.5 3.5 0 0 0 16.5 4h-9Zm4.5 3.4A4.6 4.6 0 1 1 12 16.6a4.6 4.6 0 0 1 0-9.2Zm0 2A2.6 2.6 0 1 0 12 14.6a2.6 2.6 0 0 0 0-5.2Zm5-2.45a1.05 1.05 0 1 1 0 2.1 1.05 1.05 0 0 1 0-2.1Z"/></svg>',
      ],
      'x' => [
        'label' => 'X',
        'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M17.7 3h3.1l-6.8 7.8L22 21h-6.4l-5-6.5L4.9 21H1.8l7.3-8.4L1.4 3H8l4.5 5.9L17.7 3Zm-1.1 16.2h1.7L7.1 4.7H5.3l11.3 14.5Z"/></svg>',
      ],
      'tiktok' => [
        'label' => 'TikTok',
        'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M16.2 2c.3 2.4 1.7 4 4 4.2v3.5a7.4 7.4 0 0 1-4-1.2v6.7a6.3 6.3 0 1 1-6.3-6.3c.4 0 .8 0 1.2.1v3.7a2.6 2.6 0 1 0 1.8 2.5V2h3.3Z"/></svg>',
      ],
      'youtube' => [
        'label' => 'YouTube',
        'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M21.6 7.2s-.2-1.6-.8-2.3c-.8-.9-1.7-.9-2.1-1C15.8 3.7 12 3.7 12 3.7h0s-3.8 0-6.7.2c-.4.1-1.3.1-2.1 1-.6.7-.8 2.3-.8 2.3S2.2 9.1 2.2 11v1.8c0 1.9.2 3.8.2 3.8s.2 1.6.8 2.3c.8.9 1.9.9 2.4 1 1.7.2 6.4.2 6.4.2s3.8 0 6.7-.2c.4-.1 1.3-.1 2.1-1 .6-.7.8-2.3.8-2.3s.2-1.9.2-3.8V11c0-1.9-.2-3.8-.2-3.8ZM10.1 14.9V8.4l6.1 3.2-6.1 3.3Z"/></svg>',
      ],
    ];

    /*
    |--------------------------------------------------------------------------
    | Social Helper Closures
    |--------------------------------------------------------------------------
    |
    | These closures keep the template self-contained and prevent unsupported
    | social rows from rendering. This mirrors the save-side normalization from
    | AccountEditExtras while still protecting the author archive display.
    |
    */

    $usernameFromSocialUrl = function (string $url): string {
      $path = (string) parse_url($url, PHP_URL_PATH);
      $path = trim($path, "/ \t\n\r\0\x0B");

      if ($path === '') {
        return '';
      }

      $parts = array_values(array_filter(explode('/', $path)));
      $candidate = end($parts);

      if (! is_string($candidate) || $candidate === '') {
        return '';
      }

      return ltrim(sanitize_text_field($candidate), '@');
    };

    $platformFromSocialUrl = function (string $url): string {
      $host = strtolower((string) parse_url($url, PHP_URL_HOST));
      $host = preg_replace('/^www\./', '', $host) ?: $host;

      if (str_contains($host, 'facebook.com') || str_contains($host, 'fb.com')) {
        return 'facebook';
      }

      if (str_contains($host, 'instagram.com')) {
        return 'instagram';
      }

      if ($host === 'x.com' || str_contains($host, 'twitter.com')) {
        return 'x';
      }

      if (str_contains($host, 'tiktok.com')) {
        return 'tiktok';
      }

      if (str_contains($host, 'youtube.com') || str_contains($host, 'youtu.be')) {
        return 'youtube';
      }

      return '';
    };

    $profileUrlFromPlatformUsername = function (string $platform, string $username): string {
      $username = ltrim(trim($username), '@');

      if ($username === '') {
        return '';
      }

      $encoded = rawurlencode($username);

      return match ($platform) {
        'facebook' => 'https://www.facebook.com/' . $encoded,
        'instagram' => 'https://www.instagram.com/' . $encoded,
        'x' => 'https://x.com/' . $encoded,
        'tiktok' => 'https://www.tiktok.com/@' . $encoded,
        'youtube' => 'https://www.youtube.com/@' . $encoded,
        default => '',
      };
    };

    $normalizeAuthorSocialLinks = function (array $rows) use (
      $authorSocialPlatforms,
      $usernameFromSocialUrl,
      $platformFromSocialUrl,
      $profileUrlFromPlatformUsername
    ): array {
      $clean = [];
      $seen = [];

      foreach ($rows as $row) {
        if (! is_array($row)) {
          continue;
        }

        $platform = isset($row['platform']) ? sanitize_key((string) $row['platform']) : '';
        $username = isset($row['username']) ? sanitize_text_field((string) $row['username']) : '';
        $url = isset($row['url']) ? esc_url_raw((string) $row['url']) : '';

        $username = ltrim(trim($username), '@');

        if ($platform === '' && $url !== '') {
          $platform = $platformFromSocialUrl($url);
        }

        if ($platform === '' || ! isset($authorSocialPlatforms[$platform])) {
          continue;
        }

        if ($username === '' && $url !== '') {
          $username = $usernameFromSocialUrl($url);
        }

        if ($url === '' && $username !== '') {
          $url = $profileUrlFromPlatformUsername($platform, $username);
        }

        if ($url === '') {
          continue;
        }

        $fingerprint = $platform . '|' . strtolower($username) . '|' . strtolower($url);

        if (isset($seen[$fingerprint])) {
          continue;
        }

        $clean[] = [
          'platform' => $platform,
          'label' => $authorSocialPlatforms[$platform]['label'],
          'icon' => $authorSocialPlatforms[$platform]['icon'],
          'username' => $username,
          'url' => $url,
        ];

        $seen[$fingerprint] = true;

        if (count($clean) >= 15) {
          break;
        }
      }

      return $clean;
    };

    /*
    |--------------------------------------------------------------------------
    | Author Social Links
    |--------------------------------------------------------------------------
    |
    | ACF repeater values should be read through get_field() when ACF is active.
    | A user_meta fallback is included for safety.
    |
    */

    $authorSocialRaw = [];

    if ($authorId) {
      $authorAcfKey = 'user_' . $authorId;

      if (function_exists('get_field')) {
        $socialFromAcf = get_field('sccc_member_social_links', $authorAcfKey);

        if (is_array($socialFromAcf)) {
          $authorSocialRaw = $socialFromAcf;
        }
      }

      if (empty($authorSocialRaw)) {
        $socialFromMeta = get_user_meta($authorId, 'sccc_member_social_links', true);
        $authorSocialRaw = is_array($socialFromMeta) ? $socialFromMeta : [];
      }
    }

    $authorSocialLinks = $normalizeAuthorSocialLinks($authorSocialRaw);

    /*
    |--------------------------------------------------------------------------
    | Archive Count
    |--------------------------------------------------------------------------
    |
    | Use the current archive query result count so the page always reflects the
    | actual number of posts shown by WordPress for this author archive.
    |
    */

    $archiveQuery = $GLOBALS['wp_query'] ?? null;
    $authorPostCount = $archiveQuery ? (int) $archiveQuery->found_posts : 0;

    $authorPostCountLabel = sprintf(
      _n('%s story', '%s stories', $authorPostCount, 'sage'),
      number_format_i18n($authorPostCount)
    );

    /*
    |--------------------------------------------------------------------------
    | Sidebar Term Collections
    |--------------------------------------------------------------------------
    |
    | The right sidebar includes author-specific category and content type lists.
    | We build them from the author's published post IDs.
    |
    */

    $authorPostIds = $authorId
      ? get_posts([
          'author' => $authorId,
          'post_type' => 'post',
          'post_status' => 'publish',
          'posts_per_page' => 200,
          'fields' => 'ids',
          'no_found_rows' => true,
        ])
      : [];

    $sidebarCategories = [];
    $sidebarContentTypes = [];

    foreach ($authorPostIds as $authorPostId) {
      $postCategories = get_the_terms($authorPostId, 'category');

      if (! is_wp_error($postCategories) && ! empty($postCategories)) {
        foreach ($postCategories as $term) {
          if ($term->slug === 'uncategorized') {
            continue;
          }

          $termLink = get_term_link($term);

          if (is_wp_error($termLink)) {
            continue;
          }

          if (! isset($sidebarCategories[$term->term_id])) {
            $sidebarCategories[$term->term_id] = [
              'name' => $term->name,
              'url' => $termLink,
              'count' => 0,
            ];
          }

          $sidebarCategories[$term->term_id]['count']++;
        }
      }

      if (taxonomy_exists('content_type')) {
        $postContentTypes = get_the_terms($authorPostId, 'content_type');

        if (! is_wp_error($postContentTypes) && ! empty($postContentTypes)) {
          foreach ($postContentTypes as $term) {
            $termLink = get_term_link($term);

            if (is_wp_error($termLink)) {
              continue;
            }

            if (! isset($sidebarContentTypes[$term->term_id])) {
              $sidebarContentTypes[$term->term_id] = [
                'name' => $term->name,
                'url' => $termLink,
                'count' => 0,
              ];
            }

            $sidebarContentTypes[$term->term_id]['count']++;
          }
        }
      }
    }

    $sidebarCategories = array_values($sidebarCategories);
    $sidebarContentTypes = array_values($sidebarContentTypes);

    usort($sidebarCategories, fn ($a, $b) => $b['count'] <=> $a['count']);
    usort($sidebarContentTypes, fn ($a, $b) => $b['count'] <=> $a['count']);

    $sidebarCategories = array_slice($sidebarCategories, 0, 8);
    $sidebarContentTypes = array_slice($sidebarContentTypes, 0, 8);

    /*
    |--------------------------------------------------------------------------
    | Masthead Chips
    |--------------------------------------------------------------------------
    |
    | The top masthead is not a full author bio. These chips make the archive
    | feel author-related and content-aware without duplicating the sidebar card.
    |
    */

    $mastheadChips = [
      $authorPostCountLabel,
    ];

    foreach ($sidebarContentTypes as $contentType) {
      if (count($mastheadChips) >= 4) {
        break;
      }

      $mastheadChips[] = $contentType['name'];
    }

    foreach ($sidebarCategories as $category) {
      if (count($mastheadChips) >= 5) {
        break;
      }

      if (! in_array($category['name'], $mastheadChips, true)) {
        $mastheadChips[] = $category['name'];
      }
    }

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    |
    | Returning pagination as an array lets us wrap native WordPress pagination
    | links in custom styling without changing query behavior.
    |
    */

    $paginationLinks = paginate_links([
      'type' => 'array',
      'prev_text' => __('Previous', 'sage'),
      'next_text' => __('Next', 'sage'),
    ]);
  @endphp

  {{--
    Scoped template styles.
    These are intentionally local to the author archive to avoid collateral
    changes elsewhere in the theme.
  --}}
  <style>
    .sccc-author-archive {
      --sccc-author-blue: #2fb6ff;
      --sccc-author-gradient: var(
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

      --sccc-author-card-bg:
        radial-gradient(
          480px 220px at 18% 0%,
          color-mix(in oklab, var(--color-primary-500) 12%, transparent) 0%,
          transparent 62%
        ),
        linear-gradient(
          180deg,
          color-mix(in oklab, var(--color-surface) 88%, transparent),
          color-mix(in oklab, var(--color-primary-500) 12%, var(--color-bg))
        );

      --sccc-author-sidebar-bg:
        radial-gradient(
          420px 190px at 12% 0%,
          color-mix(in oklab, var(--color-primary-500) 12%, transparent) 0%,
          transparent 64%
        ),
        linear-gradient(
          180deg,
          color-mix(in oklab, var(--color-surface) 88%, transparent),
          color-mix(in oklab, var(--color-bg) 94%, transparent)
        );

      background:
        radial-gradient(
          900px 420px at 10% 0%,
          color-mix(in oklab, var(--color-primary-500) 12%, transparent) 0%,
          transparent 62%
        ),
        radial-gradient(
          780px 360px at 94% 10%,
          color-mix(in oklab, var(--color-accent-500) 7%, transparent) 0%,
          transparent 60%
        ),
        var(--color-bg);
    }

    .sccc-author-archive::before {
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
      mask-image: linear-gradient(to bottom, black 0%, transparent 74%);
      pointer-events: none;
    }

    .sccc-author-archive::after {
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

    .sccc-author-masthead,
    .sccc-author-sidebar-card {
      position: relative;
      overflow: hidden;
      border-radius: 1.25rem;
      background: var(--sccc-author-sidebar-bg);
      border: 1px solid color-mix(in oklab, var(--color-primary-500) 18%, transparent);
      box-shadow:
        0 16px 34px rgba(0, 0, 0, 0.18),
        inset 0 1px 0 color-mix(in oklab, white 5%, transparent);
    }

    .sccc-author-masthead::before,
    .sccc-author-sidebar-card::before,
    .sccc-author-post-card::before {
      content: "";
      position: absolute;
      inset-inline: 1.25rem;
      top: 0;
      height: 2px;
      background: var(--sccc-author-gradient);
      opacity: 0.76;
      pointer-events: none;
    }

    .sccc-author-masthead::after {
      content: "";
      position: absolute;
      right: -8rem;
      bottom: -10rem;
      width: 24rem;
      height: 24rem;
      border-radius: 999px;
      background: color-mix(in oklab, var(--color-primary-500) 14%, transparent);
      filter: blur(44px);
      pointer-events: none;
    }

    .sccc-author-avatar {
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

    .sccc-author-pill {
      border: 1px solid color-mix(in oklab, var(--color-primary-500) 22%, transparent);
      background:
        linear-gradient(
          180deg,
          color-mix(in oklab, var(--color-surface) 84%, transparent),
          color-mix(in oklab, var(--color-bg) 94%, transparent)
        );
      box-shadow:
        inset 0 1px 0 color-mix(in oklab, white 5%, transparent),
        0 0 16px color-mix(in oklab, var(--color-primary-500) 8%, transparent);
    }

    .sccc-author-masthead-kicker {
      color: var(--sccc-author-blue);
      font-size: 0.72rem;
      font-weight: 800;
      letter-spacing: 0.26em;
      line-height: 1.35;
      text-transform: uppercase;
    }

    .sccc-author-post-card {
      position: relative;
      overflow: hidden;
      border-radius: 1.4rem;
      background: var(--sccc-author-card-bg);
      border: 1px solid color-mix(in oklab, var(--color-primary-500) 18%, transparent);
      box-shadow:
        0 14px 32px rgba(0, 0, 0, 0.22),
        inset 0 1px 0 color-mix(in oklab, white 5%, transparent);
      transition:
        transform 220ms ease,
        border-color 220ms ease,
        box-shadow 220ms ease;
    }

    .sccc-author-post-card:hover {
      transform: translateY(-3px);
      border-color: color-mix(in oklab, var(--color-accent-500) 42%, var(--color-primary-500));
      box-shadow:
        0 22px 46px rgba(0, 0, 0, 0.28),
        0 0 24px color-mix(in oklab, var(--color-primary-500) 13%, transparent),
        inset 0 1px 0 color-mix(in oklab, white 6%, transparent);
    }

    .sccc-author-post-card__media {
      position: relative;
      overflow: hidden;
      background:
        radial-gradient(
          420px 190px at 20% 0%,
          color-mix(in oklab, var(--color-primary-500) 15%, transparent) 0%,
          transparent 64%
        ),
        var(--color-bg);
    }

    .sccc-author-post-card__media img {
      display: block;
      width: 100%;
      aspect-ratio: 16 / 9;
      object-fit: cover;
      transition:
        transform 420ms ease,
        filter 420ms ease;
    }

    .sccc-author-post-card:hover .sccc-author-post-card__media img {
      transform: scale(1.035);
      filter: saturate(1.06) contrast(1.04);
    }

    .sccc-author-post-card__badge {
      position: absolute;
      left: 1rem;
      top: 1rem;
      z-index: 4;
      max-width: calc(100% - 2rem);
      border-radius: 999px;
      padding: 0.48rem 0.78rem;
      background:
        linear-gradient(
          180deg,
          color-mix(in oklab, black 38%, transparent),
          color-mix(in oklab, black 56%, transparent)
        );
      color: #fff;
      font-size: 0.72rem;
      font-weight: 800;
      letter-spacing: 0.16em;
      line-height: 1;
      text-transform: uppercase;
      backdrop-filter: blur(10px);
      box-shadow:
        0 8px 18px rgba(0, 0, 0, 0.24),
        inset 0 1px 0 color-mix(in oklab, white 12%, transparent);
    }

    .sccc-author-post-card__body {
      padding: 1.35rem 1.45rem 1.45rem;
    }

    .sccc-author-post-card__meta {
      color: var(--sccc-author-blue);
      font-size: 0.78rem;
      font-weight: 800;
      letter-spacing: 0.16em;
      line-height: 1.45;
      text-transform: uppercase;
    }

    .sccc-author-post-card__title {
      margin-top: 0.85rem;
      color: var(--color-text);
      font-size: clamp(1.35rem, 1.7vw, 1.7rem);
      font-weight: 800;
      line-height: 1.12;
      letter-spacing: -0.025em;
    }

    .sccc-author-post-card__excerpt {
      margin-top: 0.85rem;
      color: var(--color-muted);
      font-size: 0.98rem;
      line-height: 1.65;
    }

    .sccc-author-post-card__read-more {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      margin-top: 1.65rem;
      color: var(--sccc-author-blue);
      font-size: 0.95rem;
      font-weight: 800;
      text-decoration: none;
      transition:
        gap 180ms ease,
        color 180ms ease,
        text-shadow 180ms ease;
    }

    .sccc-author-post-card:hover .sccc-author-post-card__read-more {
      gap: 0.75rem;
      color: color-mix(in oklab, var(--sccc-author-blue) 82%, white 18%);
      text-shadow: 0 0 16px color-mix(in oklab, var(--sccc-author-blue) 34%, transparent);
    }

    /*
    |--------------------------------------------------------------------------
    | Sidebar Social Links
    |--------------------------------------------------------------------------
    |
    | These are intentionally compact and readable:
    | icon + @username. This makes multiple accounts on the same platform clear.
    |
    */

    .sccc-author-social-list {
      display: flex;
      flex-wrap: wrap;
      gap: 0.55rem;
      margin-top: 1rem;
    }

    .sccc-author-social-link {
      display: inline-flex;
      align-items: center;
      min-width: 0;
      gap: 0.45rem;
      border-radius: 999px;
      border: 1px solid color-mix(in oklab, var(--color-primary-500) 24%, transparent);
      background:
        linear-gradient(
          180deg,
          color-mix(in oklab, var(--color-surface) 84%, transparent),
          color-mix(in oklab, var(--color-bg) 94%, transparent)
        );
      color: var(--color-text);
      font-size: 0.82rem;
      font-weight: 800;
      line-height: 1;
      max-width: 100%;
      padding: 0.58rem 0.72rem;
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

    .sccc-author-social-link:hover {
      transform: translateY(-1px);
      border-color: color-mix(in oklab, var(--color-accent-500) 52%, transparent);
      color: var(--sccc-author-blue);
      box-shadow:
        inset 0 1px 0 color-mix(in oklab, white 6%, transparent),
        0 0 20px color-mix(in oklab, var(--color-accent-500) 12%, transparent);
    }

    .sccc-author-social-link svg {
      display: block;
      width: 1rem;
      height: 1rem;
      flex: 0 0 auto;
      fill: currentColor;
    }

    .sccc-author-social-link__handle {
      display: inline-block;
      min-width: 0;
      max-width: 12rem;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .sccc-author-site-link {
      margin-top: 1rem;
    }

    .sccc-author-sidebar-list a {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      color: var(--color-muted);
      text-decoration: none;
      transition:
        color 180ms ease,
        transform 180ms ease;
    }

    .sccc-author-sidebar-list a:hover {
      color: var(--color-text);
      transform: translateX(2px);
    }

    .sccc-author-sidebar-count {
      display: inline-flex;
      min-width: 1.35rem;
      height: 1.35rem;
      align-items: center;
      justify-content: center;
      border-radius: 999px;
      background: color-mix(in oklab, var(--color-bg) 80%, black 8%);
      color: var(--color-text);
      font-size: 0.72rem;
      font-weight: 800;
    }

    /*
    |--------------------------------------------------------------------------
    | Real Widget Sidebar Styling
    |--------------------------------------------------------------------------
    |
    | These rules target common WordPress widget/block widget markup so dynamic
    | widgets blend into the Space City Car Club archive layout.
    |
    */

    .sccc-author-widget-area .widget + .widget {
      margin-top: 1.5rem;
      padding-top: 1.5rem;
      border-top: 1px solid color-mix(in oklab, var(--color-primary-500) 18%, transparent);
    }

    .sccc-author-widget-area .widget-title,
    .sccc-author-widget-area .wp-block-heading,
    .sccc-author-widget-area h2,
    .sccc-author-widget-area h3 {
      margin: 0 0 1rem;
      color: var(--color-text);
      font-family: var(--font-display);
      font-size: 1.35rem;
      font-weight: 800;
      line-height: 1.2;
      letter-spacing: -0.02em;
    }

    .sccc-author-widget-area p,
    .sccc-author-widget-area li,
    .sccc-author-widget-area label,
    .sccc-author-widget-area .wp-block-latest-posts__post-excerpt,
    .sccc-author-widget-area .wp-block-latest-comments__comment-excerpt {
      color: var(--color-muted);
      font-size: 0.95rem;
      line-height: 1.65;
    }

    .sccc-author-widget-area ul,
    .sccc-author-widget-area ol {
      margin: 0;
      padding-left: 0;
      list-style: none;
    }

    .sccc-author-widget-area li + li {
      margin-top: 0.75rem;
    }

    .sccc-author-widget-area a {
      color: var(--color-text);
      text-decoration: none;
      transition: color 180ms ease;
    }

    .sccc-author-widget-area a:hover {
      color: var(--sccc-author-blue);
    }

    .sccc-author-widget-area .wp-block-search__inside-wrapper,
    .sccc-author-widget-area form:not(.searchform) {
      display: flex;
      gap: 0.75rem;
      flex-wrap: wrap;
    }

    .sccc-author-widget-area input[type="text"],
    .sccc-author-widget-area input[type="search"],
    .sccc-author-widget-area input[type="email"],
    .sccc-author-widget-area select,
    .sccc-author-widget-area textarea {
      width: 100%;
      border-radius: 0.85rem;
      border: 1px solid color-mix(in oklab, var(--color-primary-500) 22%, transparent);
      background:
        linear-gradient(
          180deg,
          color-mix(in oklab, var(--color-surface) 84%, transparent),
          color-mix(in oklab, var(--color-bg) 94%, transparent)
        );
      color: var(--color-text);
      padding: 0.8rem 0.95rem;
      box-shadow: inset 0 1px 0 color-mix(in oklab, white 5%, transparent);
    }

    .sccc-author-widget-area button,
    .sccc-author-widget-area input[type="submit"],
    .sccc-author-widget-area .wp-block-search__button {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 999px;
      border: 1px solid color-mix(in oklab, var(--color-accent-500) 34%, transparent);
      background:
        linear-gradient(
          180deg,
          color-mix(in oklab, var(--color-surface) 84%, transparent),
          color-mix(in oklab, var(--color-bg) 94%, transparent)
        );
      color: var(--color-text);
      font-size: 0.9rem;
      font-weight: 700;
      padding: 0.75rem 1rem;
      text-decoration: none;
      box-shadow:
        inset 0 1px 0 color-mix(in oklab, white 5%, transparent),
        0 0 16px color-mix(in oklab, var(--color-accent-500) 8%, transparent);
      transition:
        transform 180ms ease,
        border-color 180ms ease,
        box-shadow 180ms ease;
    }

    .sccc-author-widget-area button:hover,
    .sccc-author-widget-area input[type="submit"]:hover,
    .sccc-author-widget-area .wp-block-search__button:hover {
      transform: translateY(-1px);
      border-color: color-mix(in oklab, var(--color-accent-500) 54%, transparent);
      box-shadow:
        inset 0 1px 0 color-mix(in oklab, white 6%, transparent),
        0 0 20px color-mix(in oklab, var(--color-accent-500) 12%, transparent);
    }

    .sccc-author-widget-area .wp-block-tag-cloud a,
    .sccc-author-widget-area .tagcloud a {
      display: inline-flex;
      align-items: center;
      border-radius: 999px;
      border: 1px solid color-mix(in oklab, var(--color-primary-500) 22%, transparent);
      background:
        linear-gradient(
          180deg,
          color-mix(in oklab, var(--color-surface) 84%, transparent),
          color-mix(in oklab, var(--color-bg) 94%, transparent)
        );
      color: var(--color-text);
      font-size: 0.8rem !important;
      font-weight: 700;
      line-height: 1;
      margin: 0.25rem 0.35rem 0 0;
      padding: 0.5rem 0.7rem;
      text-decoration: none;
    }

    .sccc-author-widget-area .wp-block-calendar table {
      width: 100%;
      border-collapse: separate;
      border-spacing: 0.35rem;
      color: var(--color-muted);
      text-align: center;
    }

    .sccc-author-widget-area .wp-block-calendar caption {
      color: var(--color-text);
      font-weight: 700;
      margin-bottom: 0.75rem;
    }

    .sccc-author-widget-area .wp-block-calendar td,
    .sccc-author-widget-area .wp-block-calendar th {
      padding: 0.35rem;
    }

    .sccc-author-pagination .page-numbers {
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

    .sccc-author-pagination .page-numbers:hover {
      transform: translateY(-1px);
      border-color: color-mix(in oklab, var(--color-accent-500) 60%, transparent);
      color: var(--color-text);
      box-shadow: 0 0 18px color-mix(in oklab, var(--color-accent-500) 12%, transparent);
    }

    .sccc-author-pagination .page-numbers.current {
      border-color: transparent;
      background: linear-gradient(135deg, var(--color-primary-500), var(--color-accent-500));
      color: #fff;
      box-shadow: 0 0 22px color-mix(in oklab, var(--color-primary-500) 22%, transparent);
    }

    @media (prefers-reduced-motion: reduce) {
      .sccc-author-post-card,
      .sccc-author-post-card *,
      .sccc-author-sidebar-list a,
      .sccc-author-social-link,
      .sccc-author-widget-area *,
      .sccc-author-pagination .page-numbers {
        transition: none;
      }

      .sccc-author-post-card:hover,
      .sccc-author-social-link:hover,
      .sccc-author-pagination .page-numbers:hover {
        transform: none;
      }
    }
  </style>

  <main class="sccc-author-archive relative isolate overflow-hidden text-[var(--color-text)]">
    {{--
      ========================================================================
      Contributor Archive Masthead
      ========================================================================
      This is archive/context messaging. The actual author profile lives in
      the sidebar so we avoid duplicate bio blocks.
    --}}
    <section class="px-4 pb-6 pt-8 md:px-6 md:pb-8 md:pt-10" aria-labelledby="author-archive-title">
      <div class="mx-auto max-w-7xl">
        <div class="sccc-author-masthead px-5 py-6 md:px-8 md:py-8">
          <div class="relative z-10 grid gap-6 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
            <div>
              <p class="sccc-author-masthead-kicker">
                {{ __('Contributor Garage', 'sage') }}
              </p>

              <h1
                id="author-archive-title"
                class="mt-2 text-3xl font-bold tracking-tight text-[var(--color-text)] md:text-5xl"
                style="font-family: var(--font-display);"
              >
                {{ sprintf(__('Stories by %s', 'sage'), $authorName) }}
              </h1>

              <p class="mt-4 max-w-3xl text-base leading-7 text-[var(--color-muted)]">
                {{ __('Posts, updates, and automotive stories from this contributor — covering the builds, events, alerts, and culture that keep the Space City Car Club community moving.', 'sage') }}
              </p>

              <div class="mt-5 h-[2px] w-44 rounded-full" style="background: var(--sccc-author-gradient);" aria-hidden="true"></div>
            </div>

            @if (! empty($mastheadChips))
              <div class="flex flex-wrap gap-2 lg:max-w-sm lg:justify-end">
                @foreach ($mastheadChips as $chip)
                  <span class="sccc-author-pill inline-flex rounded-full px-3.5 py-1.5 text-xs font-bold text-[var(--color-text)]">
                    {{ $chip }}
                  </span>
                @endforeach
              </div>
            @endif
          </div>
        </div>
      </div>
    </section>

    {{--
      ========================================================================
      Main Archive Layout
      ========================================================================
      The sidebar starts lower on xl+ so the first sidebar panel aligns with
      the first row of cards instead of the section heading.
    --}}
    <section class="mx-auto max-w-7xl px-4 pb-12 md:px-6 md:pb-16">
      <div class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_20rem] 2xl:grid-cols-[minmax(0,1fr)_22rem]">
        {{--
          ====================================================================
          Main Content Column
          ====================================================================
        --}}
        <div class="min-w-0">
          <header class="mb-6">
            <p class="text-[0.72rem] font-bold uppercase tracking-[0.24em] text-[var(--color-accent-500)]">
              {{ __('Latest from this author', 'sage') }}
            </p>

            <h2
              class="mt-1.5 text-3xl font-bold tracking-tight text-[var(--color-text)]"
              style="font-family: var(--font-display);"
            >
              {{ __('Stories & Updates', 'sage') }}
            </h2>

            <div class="mt-3 h-[2px] w-40 rounded-full" style="background: var(--sccc-author-gradient);" aria-hidden="true"></div>
          </header>

          @if (have_posts())
            <div class="grid gap-6 md:grid-cols-2">
              @while (have_posts())
                @php
                  the_post();

                  /*
                  |--------------------------------------------------------------------------
                  | Post Card Data
                  |--------------------------------------------------------------------------
                  |
                  | These cards stay visually consistent with the main blog listing:
                  | image, overlay pill, blue meta, compact title, excerpt, read-more.
                  |
                  */

                  $postId = get_the_ID();
                  $postTitle = get_the_title($postId);
                  $postPermalink = get_permalink($postId);
                  $postDateHuman = strtoupper(get_the_date('M j, Y', $postId));
                  $postDateMachine = get_the_date('c', $postId);
                  $postImage = get_the_post_thumbnail_url($postId, 'large');

                  $postContent = get_post_field('post_content', $postId);
                  $readingTime = max(1, (int) ceil(str_word_count(wp_strip_all_tags($postContent)) / 220));

                  $rawExcerpt = has_excerpt($postId)
                    ? get_the_excerpt($postId)
                    : wp_strip_all_tags(get_the_excerpt($postId));

                  $excerpt = wp_trim_words(
                    html_entity_decode(wp_strip_all_tags($rawExcerpt), ENT_QUOTES, get_bloginfo('charset')),
                    18,
                    '…'
                  );

                  /*
                  |--------------------------------------------------------------------------
                  | Card Eyebrow Label
                  |--------------------------------------------------------------------------
                  |
                  | Prefer content_type when available. Fall back to category, then to
                  | a safe default.
                  |
                  */

                  $eyebrowLabel = '';

                  if (taxonomy_exists('content_type')) {
                    $contentTypes = get_the_terms($postId, 'content_type');

                    if (! is_wp_error($contentTypes) && ! empty($contentTypes)) {
                      $eyebrowLabel = $contentTypes[0]->name;
                    }
                  }

                  if (! $eyebrowLabel) {
                    $categories = get_the_terms($postId, 'category');

                    if (! is_wp_error($categories) && ! empty($categories)) {
                      foreach ($categories as $category) {
                        if ($category->slug !== 'uncategorized') {
                          $eyebrowLabel = $category->name;
                          break;
                        }
                      }
                    }
                  }

                  $eyebrowLabel = $eyebrowLabel ?: __('Club News', 'sage');
                @endphp

                <article class="sccc-author-post-card group">
                  <a
                    href="{{ esc_url($postPermalink) }}"
                    class="absolute inset-0 z-10 rounded-[1.4rem] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-accent-500)] focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--color-bg)]"
                    aria-label="{{ esc_attr(sprintf(__('Read %s', 'sage'), $postTitle)) }}"
                  >
                    <span class="sr-only">
                      {{ sprintf(__('Read %s', 'sage'), $postTitle) }}
                    </span>
                  </a>

                  <div class="sccc-author-post-card__media">
                    <span class="sccc-author-post-card__badge">
                      {{ $eyebrowLabel }}
                    </span>

                    @if ($postImage)
                      <img
                        src="{{ esc_url($postImage) }}"
                        alt="{{ esc_attr($postTitle) }}"
                        loading="lazy"
                      >
                    @else
                      <div
                        class="flex aspect-video w-full items-center justify-center px-6 text-center"
                        style="
                          background:
                            radial-gradient(
                              420px 180px at 20% 12%,
                              color-mix(in oklab, var(--color-primary-500) 18%, transparent) 0%,
                              transparent 64%
                            ),
                            radial-gradient(
                              320px 160px at 88% 84%,
                              color-mix(in oklab, var(--color-accent-500) 12%, transparent) 0%,
                              transparent 60%
                            ),
                            color-mix(in oklab, var(--color-surface) 88%, var(--color-bg));
                        "
                      >
                        <span
                          class="text-xl font-bold uppercase tracking-[0.14em] text-[var(--color-muted)]"
                          style="font-family: var(--font-display);"
                        >
                          {{ __('Space City Car Club', 'sage') }}
                        </span>
                      </div>
                    @endif
                  </div>

                  <div class="sccc-author-post-card__body">
                    <div class="sccc-author-post-card__meta">
                      <time datetime="{{ esc_attr($postDateMachine) }}">
                        {{ $postDateHuman }}
                      </time>
                      <span aria-hidden="true"> • </span>
                      <span>
                        {{ sprintf(_n('%d min read', '%d min read', $readingTime, 'sage'), $readingTime) }}
                      </span>
                    </div>

                    <h3 class="sccc-author-post-card__title">
                      {{ $postTitle }}
                    </h3>

                    @if ($excerpt)
                      <p class="sccc-author-post-card__excerpt">
                        {{ $excerpt }}
                      </p>
                    @endif

                    <span class="sccc-author-post-card__read-more">
                      {{ __('Read More', 'sage') }}
                      <span aria-hidden="true">→</span>
                    </span>
                  </div>
                </article>
              @endwhile
            </div>

            @if (! empty($paginationLinks))
              <nav
                class="sccc-author-pagination mt-10 flex flex-wrap items-center justify-center gap-3 text-sm font-bold"
                aria-label="{{ esc_attr__('Author archive pagination', 'sage') }}"
              >
                @foreach ($paginationLinks as $paginationLink)
                  {!! $paginationLink !!}
                @endforeach
              </nav>
            @endif
          @else
            <div class="sccc-author-sidebar-card px-6 py-8 text-center">
              <p class="text-[0.72rem] font-bold uppercase tracking-[0.24em] text-[var(--color-accent-500)]">
                {{ __('No posts yet', 'sage') }}
              </p>

              <h2
                class="mt-2 text-2xl font-bold tracking-tight text-[var(--color-text)]"
                style="font-family: var(--font-display);"
              >
                {{ __('Nothing published by this author yet.', 'sage') }}
              </h2>

              <p class="mx-auto mt-3 max-w-xl text-sm leading-6 text-[var(--color-muted)]">
                {{ __('Check back soon for new club updates, event recaps, and automotive content.', 'sage') }}
              </p>
            </div>
          @endif
        </div>

        {{--
          ====================================================================
          Sidebar Column
          ====================================================================
          The compressed author card includes website/social profile links.
          The real WordPress widget sidebar remains directly below it.
        --}}
        <aside class="min-w-0 space-y-6 xl:sticky xl:top-28 xl:self-start xl:pt-[6.35rem]">
          {{--
            Compressed author card.
            This is the author identity area. Social links live here because
            they are personal/profile context, not archive context.
          --}}
          <section class="sccc-author-sidebar-card px-5 py-6">
            <div class="flex items-center gap-4">
              <div class="sccc-author-avatar shrink-0 rounded-full p-[2px]">
                <div class="rounded-full bg-[var(--color-bg)] p-1">
                  @if ($authorAvatar)
                    <img
                      src="{{ esc_url($authorAvatar) }}"
                      alt="{{ esc_attr(sprintf(__('%s profile photo', 'sage'), $authorName)) }}"
                      class="size-14 rounded-full object-cover"
                      loading="lazy"
                    >
                  @else
                    <div class="flex size-14 items-center justify-center rounded-full bg-[var(--color-surface)] text-xl font-bold text-[var(--color-muted)]">
                      {{ esc_html($authorInitial) }}
                    </div>
                  @endif
                </div>
              </div>

              <div>
                <p class="text-[0.68rem] font-bold uppercase tracking-[0.22em] text-[var(--color-accent-500)]">
                  {{ __('Author', 'sage') }}
                </p>

                <h2
                  class="mt-1 text-xl font-bold tracking-tight text-[var(--color-text)]"
                  style="font-family: var(--font-display);"
                >
                  {{ $authorName }}
                </h2>

                <p class="mt-1 text-sm text-[var(--color-muted)]">
                  {{ $authorPostCountLabel }}
                </p>
              </div>
            </div>

            @if ($authorDescription)
              <p class="mt-4 text-sm leading-6 text-[var(--color-muted)]">
                {{ wp_trim_words(wp_strip_all_tags($authorDescription), 24, '…') }}
              </p>
            @endif

            @if ($authorWebsite)
              <div class="sccc-author-site-link">
                <a
                  href="{{ esc_url($authorWebsite) }}"
                  class="sccc-author-social-link"
                  rel="author noopener noreferrer"
                  target="_blank"
                  aria-label="{{ esc_attr(sprintf(__('%s website', 'sage'), $authorName)) }}"
                >
                  <span aria-hidden="true">↗</span>
                  <span class="sccc-author-social-link__handle">
                    {{ __('Website', 'sage') }}
                  </span>
                </a>
              </div>
            @endif

            @if (! empty($authorSocialLinks))
              <div class="sccc-author-social-list" aria-label="{{ esc_attr__('Author social profile links', 'sage') }}">
                @foreach ($authorSocialLinks as $social)
                  <a
                    href="{{ esc_url($social['url']) }}"
                    class="sccc-author-social-link"
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label="{{ esc_attr($social['label'] . (! empty($social['username']) ? ': @' . $social['username'] : ' profile')) }}"
                    title="{{ esc_attr($social['label'] . (! empty($social['username']) ? ': @' . $social['username'] : '')) }}"
                  >
                    {!! $social['icon'] !!}

                    <span class="sccc-author-social-link__handle">
                      @if (! empty($social['username']))
                        {{ '@' . $social['username'] }}
                      @else
                        {{ $social['label'] }}
                      @endif
                    </span>
                  </a>
                @endforeach
              </div>
            @endif
          </section>

          {{--
            Real widget sidebar.
            This renders any widgets assigned to the registered sidebar ID:
            author-archive-sidebar.
          --}}
          @if (is_active_sidebar('author-archive-sidebar'))
            <section class="sccc-author-sidebar-card sccc-author-widget-area px-5 py-6">
              @php
                dynamic_sidebar('author-archive-sidebar');
              @endphp
            </section>
          @endif

          @if (! empty($sidebarCategories))
            <section class="sccc-author-sidebar-card px-5 py-6">
              <h2
                class="text-xl font-bold tracking-tight text-[var(--color-text)]"
                style="font-family: var(--font-display);"
              >
                {{ __('Browse by Category', 'sage') }}
              </h2>

              <ul class="sccc-author-sidebar-list mt-4 space-y-3">
                @foreach ($sidebarCategories as $category)
                  <li>
                    <a href="{{ esc_url($category['url']) }}">
                      <span>{{ $category['name'] }}</span>
                      <span class="sccc-author-sidebar-count">{{ $category['count'] }}</span>
                    </a>
                  </li>
                @endforeach
              </ul>
            </section>
          @endif

          @if (! empty($sidebarContentTypes))
            <section class="sccc-author-sidebar-card px-5 py-6">
              <h2
                class="text-xl font-bold tracking-tight text-[var(--color-text)]"
                style="font-family: var(--font-display);"
              >
                {{ __('Content Types', 'sage') }}
              </h2>

              <ul class="sccc-author-sidebar-list mt-4 space-y-3">
                @foreach ($sidebarContentTypes as $contentType)
                  <li>
                    <a href="{{ esc_url($contentType['url']) }}">
                      <span>{{ $contentType['name'] }}</span>
                      <span class="sccc-author-sidebar-count">{{ $contentType['count'] }}</span>
                    </a>
                  </li>
                @endforeach
              </ul>
            </section>
          @endif
        </aside>
      </div>
    </section>
  </main>
@endsection