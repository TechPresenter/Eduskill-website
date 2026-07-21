/**
 * Eduskill design system — Tailwind build config.
 *
 * CONTENT GLOBS scan the CURRENT flat-file layout. The earlier breakage happened because a rebuild
 * used globs pointing at the old (deleted) app/views/** tree, so the admin markup was never scanned
 * and its utility classes (lg:pl-72, from-emerald-500, rounded-2xl, …) were purged out of admin.css.
 *
 * To rebuild after editing markup or the design:
 *   cd src-build && npm install         (first time only)
 *   npx tailwindcss -i ./src/app.css   -o ../assets/css/app.css   --minify
 *   npx tailwindcss -i ./src/admin.css -o ../assets/css/admin.css --minify
 */
const channel = (name) => `rgb(var(--${name}) / <alpha-value>)`;
const ramp = (prefix) =>
  Object.fromEntries(
    [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950].map((step) => [step, channel(`${prefix}-${step}`)])
  );

module.exports = {
  darkMode: 'class',
  content: [
    '../*.php',
    '../includes/**/*.php',
    '../admin/**/*.php',
    '../api/**/*.php',
    '../assets/js/**/*.js',
    '../recover.php',
  ],
  theme: {
    extend: {
      colors: {
        brand: ramp('brand'),
        accent: ramp('accent'),
        success: ramp('success'),
        warning: ramp('warning'),
        danger: ramp('danger'),
        info: ramp('info'),
        surface: {
          DEFAULT: channel('surface'),
          raised: channel('surface-raised'),
          sunken: channel('surface-sunken'),
          overlay: channel('surface-overlay'),
        },
        content: {
          DEFAULT: channel('content'),
          muted: channel('content-muted'),
          subtle: channel('content-subtle'),
          inverse: channel('content-inverse'),
        },
        edge: { DEFAULT: channel('edge'), strong: channel('edge-strong') },
        'on-brand': channel('on-brand'),
      },
      fontFamily: {
        sans: ['Inter var', 'Inter', 'system-ui', '-apple-system', 'Segoe UI', 'Roboto', 'sans-serif'],
        display: ['Plus Jakarta Sans', 'Inter', 'system-ui', 'sans-serif'],
        mono: ['JetBrains Mono', 'ui-monospace', 'SFMono-Regular', 'Menlo', 'monospace'],
      },
      fontSize: { '2xs': ['0.6875rem', { lineHeight: '1rem' }] },
      borderRadius: { card: '0.875rem', panel: '1.25rem' },
      boxShadow: {
        card: '0 1px 2px 0 rgb(0 0 0 / 0.04), 0 1px 3px 0 rgb(0 0 0 / 0.06)',
        raised: '0 4px 6px -1px rgb(0 0 0 / 0.07), 0 2px 4px -2px rgb(0 0 0 / 0.05)',
        pop: '0 10px 15px -3px rgb(0 0 0 / 0.08), 0 4px 6px -4px rgb(0 0 0 / 0.05)',
        modal: '0 25px 50px -12px rgb(0 0 0 / 0.25)',
        focus: '0 0 0 3px rgb(var(--brand-500) / 0.35)',
      },
      spacing: { sidebar: '16rem', 'sidebar-collapsed': '4.5rem', topbar: '4rem' },
      zIndex: { dropdown: '1000', sticky: '1020', drawer: '1030', modal: '1040', toast: '1050' },
      transitionTimingFunction: { 'out-expo': 'cubic-bezier(0.16, 1, 0.3, 1)' },
      keyframes: {
        'fade-in': { '0%': { opacity: '0' }, '100%': { opacity: '1' } },
        'slide-up': { '0%': { opacity: '0', transform: 'translateY(0.5rem)' }, '100%': { opacity: '1', transform: 'translateY(0)' } },
        'slide-in-right': { '0%': { transform: 'translateX(100%)' }, '100%': { transform: 'translateX(0)' } },
        shimmer: { '100%': { transform: 'translateX(100%)' } },
      },
      animation: {
        'fade-in': 'fade-in 0.2s ease-out',
        'slide-up': 'slide-up 0.25s cubic-bezier(0.16, 1, 0.3, 1)',
        'slide-in-right': 'slide-in-right 0.3s cubic-bezier(0.16, 1, 0.3, 1)',
        shimmer: 'shimmer 1.6s infinite',
      },
      typography: (theme) => ({
        DEFAULT: {
          css: {
            '--tw-prose-body': theme('colors.content.DEFAULT'),
            '--tw-prose-headings': theme('colors.content.DEFAULT'),
            '--tw-prose-links': theme('colors.brand.600'),
            maxWidth: '72ch',
          },
        },
      }),
    },
  },
  safelist: [
    { pattern: /^bg-(success|warning|danger|info|brand)-(50|100|500|600)$/ },
    { pattern: /^text-(success|warning|danger|info|brand)-(600|700|800)$/ },
    { pattern: /^border-(success|warning|danger|info|brand)-(200|500)$/ },
    { pattern: /^from-(brand|accent|success|warning|danger|info)-(400|500)$/ },
    { pattern: /^to-(brand|accent|success|warning|danger|info)-(600|700)$/ },
  ],
  plugins: [require('@tailwindcss/forms')({ strategy: 'class' }), require('@tailwindcss/typography')],
};
