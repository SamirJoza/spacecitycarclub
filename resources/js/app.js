import.meta.glob(['../images/**', '../fonts/**']);

import { initOffcanvasAll } from './components/offcanvas';
import { initTheme } from './components/theme';

import './components/sccc-event-grid-cleanup';

function boot() {
  initTheme();
  initOffcanvasAll();
}

document.readyState === 'loading'
  ? document.addEventListener('DOMContentLoaded', boot)
  : boot();