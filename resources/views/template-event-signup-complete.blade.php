{{--
  Template Name: Event Signup Complete

  File: resources/views/template-event-signup-complete.blade.php

  Purpose:
  -----------------------------------------------------------------------------
  Tablet-friendly completion page for successful onsite/event signups.

  Behavior:
  - Confirms signup completion.
  - Reminds the new member to check email for login instructions.
  - Makes clear they are not logged in on this device.
  - Returns to a fresh event signup screen automatically and via button.
--}}

@extends('layouts.kiosk')

@section('content')
  @php
    use App\Support\PMPro\EventSignupFlow;

    $flowAvailable = class_exists(EventSignupFlow::class);

    $eventName = $flowAvailable ? EventSignupFlow::eventName() : 'Event Signup';
    $message = $flowAvailable
      ? EventSignupFlow::completionMessage()
      : 'Thank you. Your signup was completed successfully. Please check your email for login instructions.';

    $buttonLabel = $flowAvailable ? EventSignupFlow::returnButtonLabel() : 'Start Next Signup';
    $entryUrl = $flowAvailable ? EventSignupFlow::entryUrl() : home_url('/event-signup/');
    $autoReturnSeconds = $flowAvailable ? EventSignupFlow::autoReturnSeconds() : 12;
  @endphp

  <style>
    /* ==========================================================================
       SCCC Event Signup Complete — page scoped
       ========================================================================== */

    .sccc-event-complete {
      --sccc-complete-bg: #f8fafc;
      --sccc-complete-card-bg: rgba(255,255,255,0.90);
      --sccc-complete-card-border: rgba(15,23,42,0.12);
      --sccc-complete-heading: #0f172a;
      --sccc-complete-body: #475569;
      --sccc-complete-muted: #64748b;
      --sccc-complete-primary: var(--color-primary, #135bec);
      --sccc-complete-shadow: 0 22px 70px rgba(2,6,23,0.16);

      background:
        radial-gradient(760px 400px at 50% 0%, color-mix(in srgb, var(--sccc-complete-primary) 18%, transparent) 0%, transparent 72%),
        var(--sccc-complete-bg);
    }

    html.dark[data-theme="dark"] .sccc-event-complete,
    .dark .sccc-event-complete {
      --sccc-complete-bg: #0f1520;
      --sccc-complete-card-bg: rgba(255,255,255,0.07);
      --sccc-complete-card-border: rgba(255,255,255,0.14);
      --sccc-complete-heading: #ffffff;
      --sccc-complete-body: #92a4c9;
      --sccc-complete-muted: rgba(255,255,255,0.68);
      --sccc-complete-shadow:
        0 1px 0 rgba(255,255,255,0.10) inset,
        0 22px 70px rgba(0,0,0,0.62);
    }

    .sccc-event-complete a {
      text-decoration: none;
    }

    .sccc-event-complete__card {
      background: var(--sccc-complete-card-bg);
      border-color: var(--sccc-complete-card-border);
      box-shadow: var(--sccc-complete-shadow);
      -webkit-backdrop-filter: blur(24px) saturate(140%);
      backdrop-filter: blur(24px) saturate(140%);
    }

    .sccc-event-complete__check {
      background:
        radial-gradient(circle at 30% 30%, rgba(255,255,255,0.28), transparent 45%),
        var(--sccc-complete-primary);
      box-shadow:
        0 0 0 8px color-mix(in srgb, var(--sccc-complete-primary) 14%, transparent),
        0 18px 42px color-mix(in srgb, var(--sccc-complete-primary) 35%, transparent);
    }
  </style>

  <section class="sccc-event-complete relative flex min-h-[calc(100vh-4rem)] items-center overflow-hidden py-12 md:py-20">
    <div class="pointer-events-none absolute inset-0 bg-gradient-to-br from-blue-500/10 via-transparent to-transparent"></div>
    <div class="pointer-events-none absolute right-[-12rem] top-[-12rem] h-[32rem] w-[32rem] rounded-full bg-blue-600/20 blur-[110px]"></div>

    <div class="relative z-10 mx-auto w-full max-w-4xl px-4 sm:px-6 lg:px-8">
      <div class="sccc-event-complete__card rounded-3xl border p-8 text-center md:p-12">
        <div class="sccc-event-complete__check mx-auto flex h-20 w-20 items-center justify-center rounded-full text-white">
          <span class="material-symbols-outlined text-[42px] leading-none">check_circle</span>
        </div>

        <p class="mt-8 text-xs font-black uppercase tracking-[0.26em] text-[color:var(--sccc-complete-primary)]">
          Signup Complete
        </p>

        <h1 class="mt-3 text-4xl font-black leading-tight tracking-tight text-[color:var(--sccc-complete-heading)] md:text-5xl">
          Welcome to the Club
        </h1>

        @if (!empty($eventName))
          <p class="mt-3 text-sm font-bold text-[color:var(--sccc-complete-muted)]">
            {{ $eventName }}
          </p>
        @endif

        <div class="mx-auto mt-6 max-w-2xl text-base leading-7 text-[color:var(--sccc-complete-body)] md:text-lg">
          {!! wp_kses_post(wpautop($message)) !!}
        </div>

        <div class="mx-auto mt-8 max-w-2xl rounded-2xl border border-[color:var(--sccc-complete-card-border)] bg-white/5 p-4">
          <p class="text-sm leading-6 text-[color:var(--sccc-complete-body)]">
            This screen is ready for the next person. No member account is left logged in on this device.
          </p>
        </div>

        <div class="mt-8 flex flex-col items-center justify-center gap-3">
          <a
            href="{{ $entryUrl }}"
            class="inline-flex w-full items-center justify-center rounded-xl px-6 py-4 text-base font-black text-white transition-all hover:brightness-110 focus:outline-none focus-visible:ring-2 focus-visible:ring-white/70 sm:w-auto"
            style="background: var(--sccc-complete-primary);"
            rel="nofollow"
          >
            {{ $buttonLabel }}
          </a>

          @if ($autoReturnSeconds > 0)
            <p class="text-xs text-[color:var(--sccc-complete-muted)]">
              Returning to signup in <span data-sccc-countdown>{{ $autoReturnSeconds }}</span> seconds.
            </p>
          @endif
        </div>
      </div>
    </div>
  </section>

  @if ($autoReturnSeconds > 0)
    <script>
      (function(){
        var seconds = {{ (int) $autoReturnSeconds }};
        var target = @json($entryUrl);
        var node = document.querySelector('[data-sccc-countdown]');

        if (!target || seconds <= 0) return;

        var remaining = seconds;

        var timer = window.setInterval(function(){
          remaining -= 1;

          if (node) {
            node.textContent = Math.max(remaining, 0).toString();
          }

          if (remaining <= 0) {
            window.clearInterval(timer);
            window.location.replace(target);
          }
        }, 1000);
      })();
    </script>
  @endif
@endsection