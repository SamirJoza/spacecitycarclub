// scripts/prune-theme-json.mjs
import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
let file = path.resolve(root, 'public/build/assets/theme.json');
// fallback if your build path differs
if (!fs.existsSync(file)) {
  const alt = path.resolve(root, 'build/assets/theme.json');
  if (fs.existsSync(alt)) file = alt;
}

if (!fs.existsSync(file)) {
  console.error('theme.json not found. Checked public/build/assets/ and build/assets/.');
  process.exit(1);
}

const json = JSON.parse(fs.readFileSync(file, 'utf8'));

// Ensure objects exist
json.settings ||= {};
json.settings.color ||= {};
json.settings.typography ||= {};

// Lock down color controls
json.settings.color.custom = false;
json.settings.color.customGradient = false; // presets still show when false
json.settings.color.defaultPalette = false;
json.settings.color.defaultGradients = false;
json.settings.color.defaultDuotone = false;
json.settings.color.customDuotone = false;
json.settings.color.duotone = [];

// Brand palette only (kept consistent)
json.settings.color.palette = [
  { slug: 'primary', name: 'Electric Blue', color: '#2979FF' },
  { slug: 'accent',  name: 'Neon Red',      color: '#FF1744' },
  { slug: 'canvas',  name: 'Canvas',        color: '#0A0A0A' },
  { slug: 'surface', name: 'Surface',       color: '#141414' },
  { slug: 'text',    name: 'Text',          color: '#FFFFFF' },
  { slug: 'muted',   name: 'Muted',         color: '#A0A7B2' },
  { slug: 'line',    name: 'Line',          color: 'rgba(255,255,255,.10)' },
];

// Editor previews tuned to better match front-end (multi-layer where needed).
// IMPORTANT: Keep these slugs EXACT — gradients.css overrides
// --wp--preset--gradient--{slug} on the front end.
json.settings.color.gradients = [
  {
    slug: 'hero-corner-glow',
    name: 'Hero — Dual Corner Glow (Blue↔Red)',
    // Two corner radials over a dark linear base (approximates dark mode hero)
    gradient:
      'radial-gradient(1200px 600px at -10% -20%, color-mix(in srgb, var(--wp--preset--color--primary) 22%, transparent) 0%, color-mix(in srgb, var(--wp--preset--color--primary) 10%, transparent) 36%, transparent 72%), ' +
      'radial-gradient(900px 520px at 110% 10%, color-mix(in srgb, var(--wp--preset--color--accent) 22%, transparent) 0%, color-mix(in srgb, var(--wp--preset--color--accent) 10%, transparent) 40%, transparent 72%), ' +
      'linear-gradient(135deg,#0A0A0A 0%,#111111 60%,#000000 100%)'
  },
  {
    slug: 'band-bloom-blue-red',
    name: 'Band — Blue↔Red Bloom',
    // Transparent twin blooms; shows over whatever block bg is set in the editor
    gradient:
      'radial-gradient(800px 420px at 10% -10%, color-mix(in srgb, var(--wp--preset--color--primary) 18%, transparent) 0%, transparent 60%), ' +
      'radial-gradient(760px 360px at 90% 110%, color-mix(in srgb, var(--wp--preset--color--accent) 18%, transparent) 0%, transparent 60%)'
  },
  {
    slug: 'surface-bloom',
    name: 'Surface Bloom',
    gradient:
      'radial-gradient(520px 260px at 10% -10%, color-mix(in srgb, var(--wp--preset--color--primary) 14%, transparent) 0%, transparent 60%), ' +
      'radial-gradient(420px 240px at 90% -20%, color-mix(in srgb, var(--wp--preset--color--accent) 16%, transparent) 0%, transparent 60%)'
  },
  {
    slug: 'hero-diagonal-sheen',
    name: 'Hero Diagonal Sheen',
    // Subtle accent-tinted stripes over canvas so the swatch shows the effect
    gradient:
      'repeating-linear-gradient(160deg, color-mix(in srgb, var(--wp--preset--color--accent) 22%, var(--wp--preset--color--canvas)) 0 8px, color-mix(in srgb, var(--wp--preset--color--accent) 10%, var(--wp--preset--color--canvas)) 8px 16px), ' +
      'linear-gradient(0deg, var(--wp--preset--color--canvas), var(--wp--preset--color--canvas))'
  },
  {
    slug: 'hero-diagonal-sheen-blue',
    name: 'Hero Diagonal Sheen — Blue',
    // Blue-tinted stripes (mirrors red version with primary)
    gradient:
      'repeating-linear-gradient(160deg, color-mix(in srgb, var(--wp--preset--color--primary) 22%, var(--wp--preset--color--canvas)) 0 8px, color-mix(in srgb, var(--wp--preset--color--primary) 10%, var(--wp--preset--color--canvas)) 8px 16px), ' +
      'linear-gradient(0deg, var(--wp--preset--color--canvas), var(--wp--preset--color--canvas))'
  },
  {
    slug: 'neon-green-plume',
    name: 'Plume — Neon Green',
    // Transparent twin neon blooms; lets block bg show through like front-end
    gradient:
      'radial-gradient(640px 340px at 12% -10%, color-mix(in srgb, #00FF6A 24%, transparent) 0%, transparent 60%), ' +
      'radial-gradient(560px 300px at 88% 110%, color-mix(in srgb, #00FF6A 16%, transparent) 0%, transparent 60%)'
  },
  {
    slug: 'hero-with-diag-multi',
    name: 'Hero — Corner Glow + Diagonal Sheen',
    // Editor swatch shows the red slab + crisp 45° stripes (Option D).
    // On the front-end, your ::before/::after paint the real thing, this
    // inline preview won’t be visible behind the slab — but the swatch looks right.
    gradient:
      'repeating-linear-gradient(45deg, rgba(0,0,0,0.20) 0 8px, transparent 8px 16px), ' +
      'linear-gradient(0deg, var(--wp--preset--color--accent), var(--wp--preset--color--accent))'
  }
];

// (optional) limit font families to the two you use
json.settings.typography.fontFamilies = [
  { slug: 'body',    name: 'Inter',  fontFamily: "'Inter', ui-sans-serif, system-ui" },
  { slug: 'display', name: 'Oswald', fontFamily: "'Oswald', ui-sans-serif, system-ui" },
];

fs.writeFileSync(file, JSON.stringify(json, null, 2));
console.log(`Pruned ${path.relative(root, file)} ✔`);
