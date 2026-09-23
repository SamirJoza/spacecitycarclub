// resources/scripts/components/offcanvas.js
// Original push-left offcanvas with a minimal pre-open reset (prevents blank drawer on successive opens)
export function initOffcanvas(root) {
  if (!root) return;

  const toggle  = root.querySelector('[data-offcanvas-toggle]');
  let   drawer  = root.querySelector('[data-offcanvas-drawer]');
  let   overlay = root.querySelector('[data-offcanvas-overlay]');
  const closer  = root.querySelector('[data-offcanvas-close]');
  const docEl   = document.documentElement;

  if (!toggle || !drawer) return;

  // Who gets pushed?
  const pushTarget =
    document.querySelector('[data-push-target]') ||
    document.getElementById('app') ||
    document.querySelector('main') ||
    document.getElementById('site') ||
    document.querySelector('.wrap') ||
    null;

  if (pushTarget) {
    pushTarget.classList.add('transition-transform','duration-300','will-change-transform');
  }

  // --- Portal fixed elements out of any transformed ancestor ---
  // Move drawer/overlay to <body> so position:fixed anchors to the viewport
  if (pushTarget && pushTarget.contains(drawer)) {
    document.body.appendChild(drawer);
  }
  if (overlay && pushTarget && pushTarget.contains(overlay)) {
    document.body.appendChild(overlay);
  }

  // Accessibility / width calc
  const setWidthVar = () => {
    // If drawer uses responsive width, read actual rendered width
    const w = drawer.offsetWidth || 320;
    docEl.style.setProperty('--drawer-w', `${w}px`);
    return w;
  };

  // --- Minimal pre-open reset to avoid "empty drawer" on re-open ---
  function resetDrawerBeforeOpen(drawerEl, overlayEl) {
    // Ensure the drawer itself isn't hidden or stuck with stale inline styles
    drawerEl.classList.remove('hidden');
    drawerEl.style.removeProperty('transform');
    drawerEl.style.removeProperty('opacity');
    drawerEl.style.removeProperty('display');

    // Clean overlay inline styles; keep it hidden until open() shows it
    if (overlayEl) {
      overlayEl.classList.add('hidden');
      overlayEl.style.removeProperty('opacity');
      overlayEl.style.removeProperty('display');
    }

    // Clear any inline display:none left on nested <ul> (e.g., from mobile accordion tests)
    drawerEl.querySelectorAll('ul').forEach((ul) => {
      if (ul.style.display === 'none') ul.style.display = '';
    });
  }

  const open = () => {
    resetDrawerBeforeOpen(drawer, overlay); // <— added line
    setWidthVar();

    drawer.classList.remove('-translate-x-full');
    drawer.classList.add('translate-x-0');               // defined open state
    overlay && overlay.classList.remove('hidden');

    toggle.setAttribute('aria-expanded','true');
    drawer.setAttribute('aria-hidden','false');
    docEl.classList.add('overflow-hidden');               // lock viewport

    if (pushTarget) pushTarget.style.transform = 'translateX(var(--drawer-w))';
  };

  const close = () => {
    drawer.classList.add('-translate-x-full');
    drawer.classList.remove('translate-x-0');
    overlay && overlay.classList.add('hidden');

    toggle.setAttribute('aria-expanded','false');
    drawer.setAttribute('aria-hidden','true');
    docEl.classList.remove('overflow-hidden');

    if (pushTarget) pushTarget.style.transform = '';
  };

  toggle.addEventListener('click', open);
  closer && closer.addEventListener('click', close);
  overlay && overlay.addEventListener('click', close);
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });

  // Keep push distance accurate when size changes
  const ro = new ResizeObserver(() => {
    if (toggle.getAttribute('aria-expanded') === 'true') setWidthVar();
  });
  ro.observe(drawer);
  window.addEventListener('resize', () => {
    if (toggle.getAttribute('aria-expanded') === 'true') setWidthVar();
  });
}

export function initOffcanvasAll() {
  document.querySelectorAll('[data-offcanvas="push-left"]').forEach(initOffcanvas);
}
