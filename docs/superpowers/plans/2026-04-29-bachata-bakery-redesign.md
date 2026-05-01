# Bachata Bakery Website Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild bachatabakery.com as a polished 6-page vanilla HTML/CSS/JS static site using the "High-End Latin Artisanal Editorial" design system.

**Architecture:** Single shared `styles.css` with CSS custom properties for all design tokens. Each page is a self-contained HTML file with nav/footer HTML inlined. `script.js` handles nav toggle, lazy-load, and carousel. No build step — files deploy directly to Hostinger via FTP.

**Tech Stack:** HTML5, CSS3 (custom properties, flexbox, grid, backdrop-filter), vanilla JS (ES6+), Google Fonts (Epilogue + Plus Jakarta Sans), LightWidget (Instagram embed), Jotform (iframe, retained as-is).

**Spec:** `docs/superpowers/specs/2026-04-29-bachata-bakery-redesign.md`

---

## File Map

| File | Action | Responsibility |
|------|--------|---------------|
| `styles.css` | Rewrite | All design tokens, reset, shared component styles |
| `script.js` | Rewrite | Nav toggle, image lazy-load, carousel |
| `index.html` | Rewrite | Home: hero, gallery carousel, about teaser, Instagram embed |
| `products.html` | Create | Products & Pricing: 5 tier cards, order CTA |
| `faq.html` | Create | FAQ: policy callout, 9-item accordion |
| `order.html` | Create | Order Form: policy gate + Jotform iframe |
| `cinnamon-rolls.html` | Create | Presale: hero, 4 flavor cards, schedule, order CTA, pickup |
| `meet-the-baker.html` | Create | Baker hero, origin story, brand values, name callout |

---

## Task 0: Git Init & Dev Server

**Files:** none

- [ ] **Step 1: Initialize git**

```bash
cd "G:/coding/Claude POC Projects/bachata-bakery"
git init
git add .
git commit -m "chore: initial commit — pre-redesign snapshot"
```

- [ ] **Step 2: Start local dev server**

Run this in a separate terminal and leave it running for all subsequent tasks:

```bash
cd "G:/coding/Claude POC Projects/bachata-bakery"
python -m http.server 8000
```

Site available at: `http://localhost:8000`

---

## Task 1: CSS Foundation

**Files:**
- Rewrite: `styles.css`

- [ ] **Step 1: Replace styles.css with the complete foundation**

```css
/* ============================================================
   GOOGLE FONTS
   ============================================================ */
@import url('https://fonts.googleapis.com/css2?family=Epilogue:wght@700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600&display=swap');

/* ============================================================
   DESIGN TOKENS
   ============================================================ */
:root {
  /* Colors */
  --surface:                   #fcf6ed;
  --surface-container-low:     #f6f0e6;
  --surface-container:         #efe9e0;
  --surface-container-high:    #e9e3da;
  --surface-container-highest: #e3ddd4;
  --surface-container-lowest:  #ffffff;
  --primary:                   #b70049;
  --primary-container:         #ff7290;
  --secondary:                 #ab2d00;
  --on-surface:                #312e29;
  --on-primary:                #ffffff;
  --outline-variant:           rgba(177, 173, 165, 0.15);
  --tertiary-container:        #c59eff;

  /* Typography */
  --font-display: 'Epilogue', sans-serif;
  --font-body:    'Plus Jakarta Sans', sans-serif;

  /* Spacing */
  --space-1:  4px;
  --space-2:  8px;
  --space-3:  16px;
  --space-4:  24px;
  --space-5:  32px;
  --space-6:  48px;
  --space-7:  64px;
  --space-8:  96px;

  /* Border radius */
  --radius-full: 9999px;
  --radius-lg:   2rem;
  --radius-md:   1rem;
  --radius-sm:   0.5rem;

  /* Shadows */
  --shadow-card: 0 20px 40px rgba(49, 46, 41, 0.06);
  --shadow-nav:  0 4px 24px rgba(49, 46, 41, 0.08);

  /* Gradient */
  --gradient-primary: linear-gradient(135deg, #b70049, #ff7290);
}

/* ============================================================
   RESET & BASE
   ============================================================ */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

html { scroll-behavior: smooth; }

body {
  font-family: var(--font-body);
  background-color: var(--surface);
  color: var(--on-surface);
  line-height: 1.6;
  -webkit-font-smoothing: antialiased;
}

img { display: block; max-width: 100%; height: auto; }

a { color: inherit; text-decoration: none; }

ul, ol { list-style: none; }

/* ============================================================
   TYPOGRAPHY
   ============================================================ */
.display-lg {
  font-family: var(--font-display);
  font-size: clamp(2.5rem, 6vw, 5rem);
  font-weight: 900;
  line-height: 1.05;
  letter-spacing: -0.02em;
}

.display-md {
  font-family: var(--font-display);
  font-size: clamp(2rem, 4vw, 3.5rem);
  font-weight: 800;
  line-height: 1.1;
  letter-spacing: -0.02em;
}

.display-sm {
  font-family: var(--font-display);
  font-size: clamp(1.5rem, 3vw, 2.5rem);
  font-weight: 700;
  line-height: 1.15;
}

.headline-lg {
  font-family: var(--font-display);
  font-size: clamp(1.25rem, 2.5vw, 2rem);
  font-weight: 700;
  line-height: 1.2;
}

.body-lg {
  font-family: var(--font-body);
  font-size: 1.125rem;
  line-height: 1.7;
}

.body-md {
  font-family: var(--font-body);
  font-size: 1rem;
  line-height: 1.65;
}

.label {
  font-family: var(--font-body);
  font-size: 0.875rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

/* ============================================================
   BUTTONS
   ============================================================ */
.btn {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-3) var(--space-5);
  border: none;
  border-radius: var(--radius-full);
  font-family: var(--font-body);
  font-size: 1rem;
  font-weight: 600;
  cursor: pointer;
  transition: opacity 0.2s, transform 0.15s;
  text-decoration: none;
}

.btn:hover  { opacity: 0.88; transform: translateY(-1px); }
.btn:active { opacity: 1;    transform: translateY(0); }

.btn-primary {
  background: var(--gradient-primary);
  color: var(--on-primary);
}

.btn-secondary {
  background: var(--surface-container-highest);
  color: var(--primary);
}

.btn-tertiary {
  background: transparent;
  color: var(--secondary);
  padding-left: 0;
  padding-right: 0;
  border-radius: 0;
  border-bottom: 2px solid transparent;
  transition: border-color 0.2s;
}
.btn-tertiary:hover { border-color: var(--secondary); opacity: 1; transform: none; }

/* ============================================================
   CHIP / TAG
   ============================================================ */
.chip {
  display: inline-block;
  background: var(--tertiary-container);
  color: var(--on-surface);
  font-family: var(--font-body);
  font-size: 0.8125rem;
  font-weight: 600;
  padding: var(--space-1) var(--space-3);
  border-radius: var(--radius-full);
}

/* ============================================================
   CARD
   ============================================================ */
.card {
  background: var(--surface-container-lowest);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-card);
  overflow: hidden;
}

.card-body { padding: var(--space-4); }

.card img {
  width: 100%;
  aspect-ratio: 4/3;
  object-fit: cover;
  border-radius: var(--radius-lg);
}

/* ============================================================
   SECTION LAYOUT
   ============================================================ */
.section {
  padding: var(--space-8) var(--space-5);
}

.section-alt {
  background: var(--surface-container-low);
  padding: var(--space-8) var(--space-5);
}

.container {
  max-width: 1200px;
  margin: 0 auto;
  width: 100%;
}

/* ============================================================
   NAVIGATION
   ============================================================ */
.nav {
  position: sticky;
  top: 0;
  z-index: 100;
  background: rgba(252, 246, 237, 0.82);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  box-shadow: var(--shadow-nav);
  padding: var(--space-3) var(--space-5);
}

.nav-inner {
  max-width: 1200px;
  margin: 0 auto;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-4);
}

.nav-logo img {
  height: 48px;
  width: auto;
}

.nav-links {
  display: flex;
  align-items: center;
  gap: var(--space-5);
}

.nav-links a {
  font-family: var(--font-body);
  font-size: 0.9375rem;
  font-weight: 600;
  color: var(--on-surface);
  transition: color 0.2s;
}

.nav-links a:hover { color: var(--primary); }

.nav-links a.active { color: var(--primary); }

.nav-hamburger {
  display: none;
  flex-direction: column;
  gap: 5px;
  background: none;
  border: none;
  cursor: pointer;
  padding: var(--space-2);
}

.nav-hamburger span {
  display: block;
  width: 24px;
  height: 2px;
  background: var(--on-surface);
  border-radius: 2px;
  transition: transform 0.3s, opacity 0.3s;
}

/* Mobile nav open state */
.nav.open .nav-hamburger span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
.nav.open .nav-hamburger span:nth-child(2) { opacity: 0; }
.nav.open .nav-hamburger span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

.nav-mobile {
  display: none;
  flex-direction: column;
  gap: var(--space-3);
  padding: var(--space-4) 0 var(--space-3);
  border-top: 1px solid var(--outline-variant);
  margin-top: var(--space-3);
}

.nav-mobile a {
  font-family: var(--font-body);
  font-size: 1.0625rem;
  font-weight: 600;
  color: var(--on-surface);
  padding: var(--space-2) 0;
  transition: color 0.2s;
}

.nav-mobile a:hover { color: var(--primary); }

@media (max-width: 768px) {
  .nav-links    { display: none; }
  .nav-hamburger { display: flex; }
  .nav.open .nav-mobile { display: flex; }
}

/* ============================================================
   FOOTER
   ============================================================ */
.footer {
  background: var(--on-surface);
  color: var(--surface);
  padding: var(--space-7) var(--space-5) var(--space-5);
}

.footer-inner {
  max-width: 1200px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: var(--space-6);
}

.footer-logo img { height: 40px; width: auto; filter: brightness(10); }

.footer h4 {
  font-family: var(--font-display);
  font-size: 1rem;
  font-weight: 700;
  margin-bottom: var(--space-3);
  color: var(--primary-container);
}

.footer p, .footer a {
  font-size: 0.9375rem;
  line-height: 1.7;
  color: rgba(252, 246, 237, 0.8);
}

.footer a:hover { color: var(--surface); }

.footer-bottom {
  max-width: 1200px;
  margin: var(--space-6) auto 0;
  padding-top: var(--space-4);
  border-top: 1px solid rgba(252, 246, 237, 0.12);
  font-size: 0.875rem;
  color: rgba(252, 246, 237, 0.5);
}

/* ============================================================
   HERO
   ============================================================ */
.hero {
  position: relative;
  min-height: 90vh;
  display: flex;
  align-items: flex-end;
  overflow: hidden;
}

.hero-bg {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  z-index: 0;
}

.hero-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(to top, rgba(49, 46, 41, 0.72) 0%, transparent 60%);
  z-index: 1;
}

.hero-content {
  position: relative;
  z-index: 2;
  padding: var(--space-8) var(--space-5);
  max-width: 700px;
  color: var(--surface);
}

.hero-content .display-lg { color: var(--surface); margin-bottom: var(--space-4); }

.hero-content .body-lg { color: rgba(252, 246, 237, 0.9); margin-bottom: var(--space-5); }

/* Page hero (smaller, for interior pages) */
.page-hero {
  background: var(--surface-container-low);
  padding: var(--space-7) var(--space-5) var(--space-6);
  text-align: center;
}

.page-hero .display-md { margin-bottom: var(--space-3); }

.page-hero .body-lg { max-width: 600px; margin: 0 auto; }

/* ============================================================
   CAROUSEL
   ============================================================ */
.carousel {
  position: relative;
  overflow: hidden;
  border-radius: var(--radius-md);
}

.carousel-track {
  display: flex;
  transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
  will-change: transform;
}

.carousel-item {
  flex: 0 0 100%;
  position: relative;
}

.carousel-item img {
  width: 100%;
  aspect-ratio: 4/3;
  object-fit: cover;
  border-radius: var(--radius-lg);
}

.carousel-btn {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  z-index: 10;
  width: 48px;
  height: 48px;
  border-radius: var(--radius-full);
  background: rgba(252, 246, 237, 0.9);
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: var(--shadow-card);
  transition: background 0.2s;
}

.carousel-btn:hover { background: var(--surface-container-lowest); }

.carousel-prev { left: var(--space-3); }
.carousel-next { right: var(--space-3); }

.carousel-btn svg { width: 20px; height: 20px; stroke: var(--on-surface); fill: none; stroke-width: 2; }

.carousel-dots {
  display: flex;
  justify-content: center;
  gap: var(--space-2);
  margin-top: var(--space-3);
}

.carousel-dot {
  width: 8px;
  height: 8px;
  border-radius: var(--radius-full);
  background: var(--outline-variant);
  border: 1px solid rgba(177, 173, 165, 0.4);
  cursor: pointer;
  transition: background 0.2s, transform 0.2s;
}

.carousel-dot.active {
  background: var(--primary);
  transform: scale(1.25);
}

/* ============================================================
   ACCORDION (FAQ)
   ============================================================ */
details.accordion-item {
  border-bottom: 1px solid var(--outline-variant);
}

details.accordion-item summary {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--space-4) 0;
  cursor: pointer;
  font-family: var(--font-body);
  font-size: 1.0625rem;
  font-weight: 600;
  color: var(--on-surface);
  list-style: none;
  user-select: none;
}

details.accordion-item summary::-webkit-details-marker { display: none; }

details.accordion-item summary::after {
  content: '';
  width: 20px;
  height: 20px;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23312e29' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
  background-size: contain;
  background-repeat: no-repeat;
  transition: transform 0.25s;
  flex-shrink: 0;
}

details.accordion-item[open] summary::after { transform: rotate(180deg); }

.accordion-body {
  padding: 0 0 var(--space-4);
  color: var(--on-surface);
  font-size: 1rem;
  line-height: 1.7;
}

/* ============================================================
   CALLOUT BOX
   ============================================================ */
.callout {
  background: var(--surface-container-low);
  border-left: 4px solid var(--primary);
  border-radius: 0 var(--radius-md) var(--radius-md) 0;
  padding: var(--space-4) var(--space-5);
  margin-bottom: var(--space-6);
}

.callout-title {
  font-family: var(--font-display);
  font-weight: 700;
  font-size: 1rem;
  color: var(--primary);
  margin-bottom: var(--space-2);
}

/* ============================================================
   PRICING CARDS
   ============================================================ */
.pricing-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: var(--space-4);
}

.pricing-card {
  background: var(--surface-container-lowest);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-card);
  padding: var(--space-5);
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.pricing-card.featured {
  background: var(--gradient-primary);
  color: var(--on-primary);
}

.pricing-tier {
  font-family: var(--font-display);
  font-size: 1.375rem;
  font-weight: 700;
}

.pricing-price {
  font-family: var(--font-display);
  font-size: 2rem;
  font-weight: 900;
  color: var(--primary);
}

.pricing-card.featured .pricing-price { color: var(--on-primary); }

.pricing-features {
  font-size: 0.9375rem;
  line-height: 1.7;
  flex: 1;
}

.pricing-features li { padding: var(--space-1) 0; }

.pricing-features li::before {
  content: '✦ ';
  color: var(--primary);
  font-size: 0.75em;
}

.pricing-card.featured .pricing-features li::before { color: rgba(255,255,255,0.7); }

/* ============================================================
   CTA BANNER
   ============================================================ */
.cta-banner {
  background: var(--gradient-primary);
  color: var(--on-primary);
  padding: var(--space-7) var(--space-5);
  text-align: center;
}

.cta-banner .display-sm { margin-bottom: var(--space-3); }

.cta-banner .body-md { opacity: 0.9; margin-bottom: var(--space-5); max-width: 560px; margin-left: auto; margin-right: auto; }

.cta-banner .btn-secondary { background: rgba(255,255,255,0.15); color: var(--on-primary); }

/* ============================================================
   FLAVOR CARDS
   ============================================================ */
.flavor-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: var(--space-4);
}

.flavor-card {
  background: var(--surface-container-lowest);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-card);
  padding: var(--space-5);
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.flavor-name {
  font-family: var(--font-display);
  font-size: 1.375rem;
  font-weight: 700;
}

.flavor-detail {
  font-size: 0.9375rem;
  color: rgba(49, 46, 41, 0.75);
  line-height: 1.6;
}

/* ============================================================
   PICKUP INFO
   ============================================================ */
.pickup-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-5);
}

@media (max-width: 600px) {
  .pickup-grid { grid-template-columns: 1fr; }
}

.pickup-item {
  background: var(--surface-container-low);
  border-radius: var(--radius-md);
  padding: var(--space-5);
}

.pickup-location {
  font-family: var(--font-display);
  font-size: 1.125rem;
  font-weight: 700;
  margin-bottom: var(--space-2);
}

/* ============================================================
   ABOUT TEASER (asymmetric layout)
   ============================================================ */
.about-teaser {
  display: grid;
  grid-template-columns: 1.1fr 0.9fr;
  gap: var(--space-7);
  align-items: center;
}

.about-image-wrap {
  position: relative;
}

.about-image-wrap img {
  width: 100%;
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-card);
}

.about-text {
  position: relative;
  z-index: 2;
}

.about-text .display-sm { margin-bottom: var(--space-4); }
.about-text .body-lg    { margin-bottom: var(--space-5); }

@media (max-width: 768px) {
  .about-teaser { grid-template-columns: 1fr; }
}

/* ============================================================
   BAKER HERO
   ============================================================ */
.baker-hero {
  display: grid;
  grid-template-columns: 1fr 1.2fr;
  gap: var(--space-7);
  align-items: center;
  padding: var(--space-8) var(--space-5);
  max-width: 1200px;
  margin: 0 auto;
}

.baker-hero img {
  width: 100%;
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-card);
}

.baker-tagline {
  font-family: var(--font-display);
  font-style: italic;
  font-size: 1.25rem;
  color: var(--primary);
  margin-top: var(--space-3);
}

@media (max-width: 768px) {
  .baker-hero { grid-template-columns: 1fr; }
}

/* ============================================================
   BRAND VALUES ROW
   ============================================================ */
.values-row {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: var(--space-5);
  text-align: center;
}

@media (max-width: 600px) {
  .values-row { grid-template-columns: 1fr; }
}

.value-item { padding: var(--space-5); }

.value-icon {
  font-size: 2rem;
  margin-bottom: var(--space-3);
}

.value-title {
  font-family: var(--font-display);
  font-size: 1.125rem;
  font-weight: 700;
  margin-bottom: var(--space-2);
}

/* ============================================================
   INSTAGRAM SECTION
   ============================================================ */
.instagram-section {
  padding: var(--space-8) var(--space-5);
  text-align: center;
}

.instagram-section .display-sm { margin-bottom: var(--space-6); }

/* ============================================================
   LAZY LOAD
   ============================================================ */
img[data-src] {
  opacity: 0;
  transition: opacity 0.4s;
}

img.loaded { opacity: 1; }

/* ============================================================
   PRESALE SCHEDULE
   ============================================================ */
.schedule-box {
  background: var(--surface-container-lowest);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-card);
  padding: var(--space-6);
  text-align: center;
  max-width: 560px;
  margin: 0 auto var(--space-6);
}

.schedule-box .headline-lg { color: var(--primary); margin-bottom: var(--space-2); }

/* ============================================================
   ORDER POLICY LIST
   ============================================================ */
.policy-list {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  max-width: 720px;
  margin: 0 auto var(--space-6);
}

.policy-list li {
  display: flex;
  gap: var(--space-3);
  align-items: flex-start;
  font-size: 1rem;
  line-height: 1.65;
}

.policy-num {
  flex-shrink: 0;
  width: 28px;
  height: 28px;
  border-radius: var(--radius-full);
  background: var(--gradient-primary);
  color: var(--on-primary);
  font-family: var(--font-display);
  font-size: 0.875rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-top: 2px;
}

/* ============================================================
   RESPONSIVE UTILITIES
   ============================================================ */
.text-center { text-align: center; }
.mt-4  { margin-top: var(--space-4); }
.mt-5  { margin-top: var(--space-5); }
.mt-6  { margin-top: var(--space-6); }
.mb-4  { margin-bottom: var(--space-4); }
.mb-5  { margin-bottom: var(--space-5); }
.mb-6  { margin-bottom: var(--space-6); }

@media (max-width: 480px) {
  .section, .section-alt { padding: var(--space-7) var(--space-3); }
}
```

- [ ] **Step 2: Open `http://localhost:8000` in browser**

Verify:
- Page background is `#fcf6ed` (warm cream, not white)
- No console errors in DevTools

- [ ] **Step 3: Commit**

```bash
git add styles.css
git commit -m "feat: add CSS design token foundation and all shared component styles"
```

---

## Task 2: script.js

**Files:**
- Rewrite: `script.js`

- [ ] **Step 1: Replace script.js with complete implementation**

```javascript
document.addEventListener('DOMContentLoaded', () => {
  initNav();
  initLazyLoad();
  document.querySelectorAll('.carousel').forEach(initCarousel);
});

// ── Navigation toggle ──────────────────────────────────────
function initNav() {
  const nav = document.querySelector('.nav');
  if (!nav) return;
  const hamburger = nav.querySelector('.nav-hamburger');
  if (!hamburger) return;
  hamburger.addEventListener('click', () => nav.classList.toggle('open'));
  document.addEventListener('click', (e) => {
    if (!nav.contains(e.target)) nav.classList.remove('open');
  });
}

// ── Image lazy-load ────────────────────────────────────────
function initLazyLoad() {
  const imgs = document.querySelectorAll('img[data-src]');
  if (!imgs.length) return;
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (!entry.isIntersecting) return;
      const img = entry.target;
      img.src = img.dataset.src;
      img.addEventListener('load', () => img.classList.add('loaded'), { once: true });
      observer.unobserve(img);
    });
  }, { rootMargin: '200px' });
  imgs.forEach(img => observer.observe(img));
}

// ── Carousel ───────────────────────────────────────────────
function initCarousel(el) {
  const track   = el.querySelector('.carousel-track');
  const items   = el.querySelectorAll('.carousel-item');
  const prevBtn = el.querySelector('.carousel-prev');
  const nextBtn = el.querySelector('.carousel-next');
  const dotsEl  = el.querySelector('.carousel-dots');
  const total   = items.length;
  let current   = 0;
  let startX    = 0;

  // Build dots
  if (dotsEl) {
    items.forEach((_, i) => {
      const dot = document.createElement('button');
      dot.className = 'carousel-dot' + (i === 0 ? ' active' : '');
      dot.setAttribute('aria-label', `Go to slide ${i + 1}`);
      dot.addEventListener('click', () => goTo(i));
      dotsEl.appendChild(dot);
    });
  }

  function goTo(index) {
    current = (index + total) % total;
    track.style.transform = `translateX(-${current * 100}%)`;
    el.querySelectorAll('.carousel-dot').forEach((d, i) =>
      d.classList.toggle('active', i === current)
    );
  }

  if (prevBtn) prevBtn.addEventListener('click', () => goTo(current - 1));
  if (nextBtn) nextBtn.addEventListener('click', () => goTo(current + 1));

  // Keyboard
  el.setAttribute('tabindex', '0');
  el.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowLeft')  goTo(current - 1);
    if (e.key === 'ArrowRight') goTo(current + 1);
  });

  // Touch / swipe
  track.addEventListener('touchstart', (e) => { startX = e.touches[0].clientX; }, { passive: true });
  track.addEventListener('touchend',   (e) => {
    const diff = startX - e.changedTouches[0].clientX;
    if (Math.abs(diff) > 50) goTo(current + (diff > 0 ? 1 : -1));
  });
}
```

- [ ] **Step 2: Verify script loads without errors**

Open `http://localhost:8000` and check DevTools Console — no errors expected.

- [ ] **Step 3: Commit**

```bash
git add script.js
git commit -m "feat: add nav toggle, lazy-load, and user-controlled carousel"
```

---

## Task 3: Shared Nav & Footer (reference markup)

**Files:** Reference only — this markup is inlined into every HTML page in Tasks 4–9.

The nav and footer below are the **canonical blocks** to copy-paste into each page. All 6 pages use identical nav/footer — update all 6 if either changes.

**Nav markup:**
```html
<nav class="nav" id="site-nav">
  <div class="nav-inner">
    <a href="index.html" class="nav-logo">
      <img src="assets/logo-without-background-web.png" alt="Bachata Bakery" width="160" height="48">
    </a>
    <ul class="nav-links">
      <li><a href="index.html">Home</a></li>
      <li><a href="products.html">Products</a></li>
      <li><a href="faq.html">FAQ</a></li>
      <li><a href="order.html">Order</a></li>
      <li><a href="cinnamon-rolls.html">Cinnamon Rolls</a></li>
      <li><a href="meet-the-baker.html">Meet the Baker</a></li>
    </ul>
    <button class="nav-hamburger" aria-label="Toggle menu" aria-expanded="false">
      <span></span><span></span><span></span>
    </button>
  </div>
  <nav class="nav-mobile">
    <a href="index.html">Home</a>
    <a href="products.html">Products</a>
    <a href="faq.html">FAQ</a>
    <a href="order.html">Order</a>
    <a href="cinnamon-rolls.html">Cinnamon Rolls</a>
    <a href="meet-the-baker.html">Meet the Baker</a>
  </nav>
</nav>
```

**Footer markup:**
```html
<footer class="footer">
  <div class="footer-inner">
    <div>
      <div class="footer-logo">
        <img src="assets/logo-without-background-web.png" alt="Bachata Bakery">
      </div>
      <p style="margin-top:16px;font-size:0.9rem;opacity:0.7">Authentic Latin-inspired desserts<br>made with love.</p>
    </div>
    <div>
      <h4>Contact</h4>
      <p><a href="mailto:info@bachatabakery.com">info@bachatabakery.com</a></p>
      <p style="margin-top:8px"><a href="https://www.instagram.com/bachatabakery" target="_blank" rel="noopener">Instagram @bachatabakery</a></p>
      <p><a href="https://www.tiktok.com/@bachatabakery" target="_blank" rel="noopener">TikTok @bachatabakery</a></p>
    </div>
    <div>
      <h4>Pickup Locations</h4>
      <p>Middletown, DE — Fri 3–7 PM</p>
      <p style="margin-top:4px">West Chester, PA — date confirmed by email</p>
    </div>
    <div>
      <h4>Quick Links</h4>
      <p><a href="products.html">Products & Pricing</a></p>
      <p><a href="faq.html">FAQ</a></p>
      <p><a href="order.html">Place an Order</a></p>
      <p><a href="cinnamon-rolls.html">Cinnamon Roll Presale</a></p>
    </div>
  </div>
  <div class="footer-bottom">
    <p>&copy; 2026 Bachata Bakery. All rights reserved.</p>
  </div>
</footer>
```

**No commit needed** — this task is reference markup only. Nav/footer go into pages in Tasks 4–9.

---

## Task 4: Home Page (`index.html`)

**Files:**
- Rewrite: `index.html`

- [ ] **Step 1: Rewrite index.html**

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Bachata Bakery — Authentic Latin-Inspired Desserts</title>
  <meta name="description" content="Custom sugar cookies and cinnamon rolls made with love. Authentic Latin-inspired desserts by Raquel in Delaware.">
  <link rel="stylesheet" href="styles.css">
</head>
<body>

  <!-- NAV -->
  <nav class="nav" id="site-nav">
    <div class="nav-inner">
      <a href="index.html" class="nav-logo">
        <img src="assets/logo-without-background-web.png" alt="Bachata Bakery" width="160" height="48">
      </a>
      <ul class="nav-links">
        <li><a href="index.html" class="active">Home</a></li>
        <li><a href="products.html">Products</a></li>
        <li><a href="faq.html">FAQ</a></li>
        <li><a href="order.html">Order</a></li>
        <li><a href="cinnamon-rolls.html">Cinnamon Rolls</a></li>
        <li><a href="meet-the-baker.html">Meet the Baker</a></li>
      </ul>
      <button class="nav-hamburger" aria-label="Toggle menu" aria-expanded="false">
        <span></span><span></span><span></span>
      </button>
    </div>
    <nav class="nav-mobile">
      <a href="index.html" class="active">Home</a>
      <a href="products.html">Products</a>
      <a href="faq.html">FAQ</a>
      <a href="order.html">Order</a>
      <a href="cinnamon-rolls.html">Cinnamon Rolls</a>
      <a href="meet-the-baker.html">Meet the Baker</a>
    </nav>
  </nav>

  <!-- HERO -->
  <section class="hero">
    <img
      class="hero-bg"
      data-src="assets/Nav-Banner-Image.webp"
      src=""
      alt="Bachata Bakery artisan cookies"
      loading="eager"
    >
    <div class="hero-overlay"></div>
    <div class="hero-content">
      <h1 class="display-lg">Treats that<br>tell stories.</h1>
      <p class="body-lg">Custom sugar cookies &amp; cinnamon rolls rooted in Latin heritage — made with love in Delaware.</p>
      <a href="order.html" class="btn btn-primary">Place an Order</a>
    </div>
  </section>

  <!-- GALLERY CAROUSEL -->
  <section class="section">
    <div class="container">
      <p class="label" style="color:var(--primary);margin-bottom:var(--space-3)">Our Work</p>
      <h2 class="display-sm" style="margin-bottom:var(--space-6)">Every cookie tells a story</h2>

      <div class="carousel" aria-label="Cookie gallery">
        <div class="carousel-track">
          <div class="carousel-item">
            <img data-src="assets/Hamilton%20Logo%20Feature%20Cookie.jpg" src="" alt="Hamilton Logo Cookie" loading="lazy">
          </div>
          <div class="carousel-item">
            <img data-src="assets/Bad%20Bunny%20Puerto%20Rico%20Set.jpg" src="" alt="Bad Bunny Puerto Rico Cookie Set" loading="lazy">
          </div>
          <div class="carousel-item">
            <img data-src="assets/Ice%20Cream%20Cookies.jpg" src="" alt="Ice Cream Cookies" loading="lazy">
          </div>
          <div class="carousel-item">
            <img data-src="assets/Graduation%20Set.jpg" src="" alt="Graduation Cookie Set" loading="lazy">
          </div>
          <div class="carousel-item">
            <img data-src="assets/Bluey%20Cookies.jpg" src="" alt="Bluey Cookies" loading="lazy">
          </div>
          <div class="carousel-item">
            <img data-src="assets/Tangled%20Set.jpg" src="" alt="Tangled Cookie Set" loading="lazy">
          </div>
        </div>
        <button class="carousel-btn carousel-prev" aria-label="Previous slide">
          <svg viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
        <button class="carousel-btn carousel-next" aria-label="Next slide">
          <svg viewBox="0 0 24 24"><path d="M9 18l6-6-6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
        <div class="carousel-dots" role="tablist" aria-label="Gallery slides"></div>
      </div>
    </div>
  </section>

  <!-- ABOUT TEASER -->
  <section class="section-alt">
    <div class="container">
      <div class="about-teaser">
        <div class="about-image-wrap">
          <img
            data-src="assets/About%20Me.jpg"
            src=""
            alt="Raquel, founder of Bachata Bakery, holding a Hamilton cookie"
            loading="lazy"
          >
        </div>
        <div class="about-text">
          <p class="label" style="color:var(--primary);margin-bottom:var(--space-3)">Meet the Baker</p>
          <h2 class="display-sm" style="margin-bottom:var(--space-4)">Baked with heritage,<br>served with heart.</h2>
          <p class="body-lg" style="margin-bottom:var(--space-5)">
            I'm Raquel — a Bronx-born Dominican and Puerto Rican baker based in Delaware.
            Bachata Bakery was born in 2020 out of resilience, love, and a deep connection to Latin Caribbean culture.
            Just like the music, my treats tell stories.
          </p>
          <a href="meet-the-baker.html" class="btn btn-primary">My Story</a>
        </div>
      </div>
    </div>
  </section>

  <!-- INSTAGRAM FEED -->
  <section class="instagram-section">
    <div class="container">
      <p class="label" style="color:var(--primary);margin-bottom:var(--space-3)">Follow Along</p>
      <h2 class="display-sm" style="margin-bottom:var(--space-6)">@bachatabakery</h2>
      <!-- LightWidget embed — replace WIDGET-CODE with code from lightwidget.com -->
      <script src="https://cdn.lightwidget.com/widgets/lightwidget.js"></script>
      <iframe src="//lightwidget.com/widgets/WIDGET-CODE.html" scrolling="no" allowtransparency="true" class="lightwidget-widget" style="width:100%;border:0;overflow:hidden;"></iframe>
    </div>
  </section>

  <!-- FOOTER -->
  <footer class="footer">
    <div class="footer-inner">
      <div>
        <div class="footer-logo">
          <img src="assets/logo-without-background-web.png" alt="Bachata Bakery">
        </div>
        <p style="margin-top:16px;font-size:0.9rem;opacity:0.7">Authentic Latin-inspired desserts<br>made with love.</p>
      </div>
      <div>
        <h4>Contact</h4>
        <p><a href="mailto:info@bachatabakery.com">info@bachatabakery.com</a></p>
        <p style="margin-top:8px"><a href="https://www.instagram.com/bachatabakery" target="_blank" rel="noopener">Instagram @bachatabakery</a></p>
        <p><a href="https://www.tiktok.com/@bachatabakery" target="_blank" rel="noopener">TikTok @bachatabakery</a></p>
      </div>
      <div>
        <h4>Pickup Locations</h4>
        <p>Middletown, DE — Fri 3–7 PM</p>
        <p style="margin-top:4px">West Chester, PA — date confirmed by email</p>
      </div>
      <div>
        <h4>Quick Links</h4>
        <p><a href="products.html">Products &amp; Pricing</a></p>
        <p><a href="faq.html">FAQ</a></p>
        <p><a href="order.html">Place an Order</a></p>
        <p><a href="cinnamon-rolls.html">Cinnamon Roll Presale</a></p>
      </div>
    </div>
    <div class="footer-bottom">
      <p>&copy; 2026 Bachata Bakery. All rights reserved.</p>
    </div>
  </footer>

  <script src="script.js"></script>
</body>
</html>
```

- [ ] **Step 2: Open `http://localhost:8000` and verify**

Check:
- Hero image loads (warm photo, not broken)
- "Treats that tell stories." headline is large Epilogue font
- "Place an Order" button is pill-shaped and gradient pink/red
- Carousel shows first cookie image; Prev/Next arrows work
- Dots appear below carousel and update on click
- About teaser: photo left, text right (stacks on mobile)
- Footer has dark background, warm text

- [ ] **Step 3: Commit**

```bash
git add index.html
git commit -m "feat: build home page — hero, gallery carousel, about teaser, Instagram section"
```

---

## Task 5: Products & Pricing (`products.html`)

**Files:**
- Create: `products.html`

- [ ] **Step 1: Create products.html**

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Products & Pricing — Bachata Bakery</title>
  <meta name="description" content="Custom sugar cookie pricing tiers — Simple, Detailed, Elaborate, My Favorite Things, and Character Cookies. Minimum 2 dozen, 2–4 week lead time.">
  <link rel="stylesheet" href="styles.css">
</head>
<body>

  <!-- NAV -->
  <nav class="nav" id="site-nav">
    <div class="nav-inner">
      <a href="index.html" class="nav-logo">
        <img src="assets/logo-without-background-web.png" alt="Bachata Bakery" width="160" height="48">
      </a>
      <ul class="nav-links">
        <li><a href="index.html">Home</a></li>
        <li><a href="products.html" class="active">Products</a></li>
        <li><a href="faq.html">FAQ</a></li>
        <li><a href="order.html">Order</a></li>
        <li><a href="cinnamon-rolls.html">Cinnamon Rolls</a></li>
        <li><a href="meet-the-baker.html">Meet the Baker</a></li>
      </ul>
      <button class="nav-hamburger" aria-label="Toggle menu" aria-expanded="false">
        <span></span><span></span><span></span>
      </button>
    </div>
    <nav class="nav-mobile">
      <a href="index.html">Home</a>
      <a href="products.html" class="active">Products</a>
      <a href="faq.html">FAQ</a>
      <a href="order.html">Order</a>
      <a href="cinnamon-rolls.html">Cinnamon Rolls</a>
      <a href="meet-the-baker.html">Meet the Baker</a>
    </nav>
  </nav>

  <!-- PAGE HERO -->
  <section class="page-hero">
    <p class="label" style="color:var(--primary);margin-bottom:var(--space-3)">Custom Sugar Cookies</p>
    <h1 class="display-md">Products &amp; Pricing</h1>
    <p class="body-lg mt-4">All cookies are vanilla icing sugar cookies, 3–4 inches, individually cellophane-wrapped with a Care Card. Minimum 2 dozen · 2–4 week lead time.</p>
  </section>

  <!-- PRICING CARDS -->
  <section class="section">
    <div class="container">
      <div class="pricing-grid">

        <!-- Simple Set -->
        <div class="pricing-card">
          <div class="pricing-tier">Simple Set</div>
          <div class="pricing-price">$56<span style="font-size:1.1rem;font-weight:500">+/doz</span></div>
          <ul class="pricing-features">
            <li>4 unique designs</li>
            <li>Up to 3 colors (excluding white)</li>
            <li>Individually wrapped</li>
            <li>Care Card included</li>
          </ul>
          <a href="order.html" class="btn btn-primary">Order Now</a>
        </div>

        <!-- Detailed Set -->
        <div class="pricing-card">
          <div class="pricing-tier">Detailed Set</div>
          <div class="pricing-price">$70<span style="font-size:1.1rem;font-weight:500">+/doz</span></div>
          <ul class="pricing-features">
            <li>6 unique designs</li>
            <li>Up to 5 colors</li>
            <li>3D florals &amp; metallics (gold, silver, rose gold)</li>
            <li>Lettering &amp; multi-layer icing</li>
            <li>Individually wrapped</li>
            <li>Care Card included</li>
          </ul>
          <a href="order.html" class="btn btn-primary">Order Now</a>
        </div>

        <!-- Elaborate Set -->
        <div class="pricing-card">
          <div class="pricing-tier">Elaborate Set</div>
          <div class="pricing-price">$84<span style="font-size:1.1rem;font-weight:500">+/doz</span></div>
          <ul class="pricing-features">
            <li>8 unique designs</li>
            <li>Up to 6 colors</li>
            <li>3D florals &amp; metallics</li>
            <li>Individually wrapped</li>
            <li>Care Card included</li>
          </ul>
          <a href="order.html" class="btn btn-primary">Order Now</a>
        </div>

        <!-- My Favorite Things (featured) -->
        <div class="pricing-card featured">
          <div class="pricing-tier">My Favorite Things</div>
          <div class="pricing-price">$96<span style="font-size:1.1rem;font-weight:500">+/doz</span></div>
          <ul class="pricing-features">
            <li>12 fully unique personalized designs</li>
            <li>Gift-ready packaging with ribbons, bows &amp; custom tags</li>
            <li>1-dozen minimum (only tier)</li>
            <li>Individually wrapped</li>
            <li>Care Card included</li>
          </ul>
          <a href="order.html" class="btn" style="background:rgba(255,255,255,0.2);color:#fff;border-radius:9999px">Order Now</a>
        </div>

        <!-- Character Cookies -->
        <div class="pricing-card">
          <div class="pricing-tier">Character Cookies</div>
          <div class="pricing-price">$8–$10<span style="font-size:1rem;font-weight:500"> each</span></div>
          <ul class="pricing-features">
            <li>Add-on to any dozen set</li>
            <li>1 design per cookie</li>
            <li>Designed collaboratively during order</li>
            <li>Individually wrapped</li>
          </ul>
          <a href="order.html" class="btn btn-primary">Order Now</a>
        </div>

      </div>
    </div>
  </section>

  <!-- CTA BANNER -->
  <div class="cta-banner">
    <h2 class="display-sm">Ready to order?</h2>
    <p class="body-md">Minimum 2 dozen · 2–4 week lead time · Rush orders available (+25%)</p>
    <a href="order.html" class="btn btn-secondary mt-5">Place an Order</a>
  </div>

  <!-- FOOTER -->
  <footer class="footer">
    <div class="footer-inner">
      <div>
        <div class="footer-logo"><img src="assets/logo-without-background-web.png" alt="Bachata Bakery"></div>
        <p style="margin-top:16px;font-size:0.9rem;opacity:0.7">Authentic Latin-inspired desserts<br>made with love.</p>
      </div>
      <div>
        <h4>Contact</h4>
        <p><a href="mailto:info@bachatabakery.com">info@bachatabakery.com</a></p>
        <p style="margin-top:8px"><a href="https://www.instagram.com/bachatabakery" target="_blank" rel="noopener">Instagram @bachatabakery</a></p>
        <p><a href="https://www.tiktok.com/@bachatabakery" target="_blank" rel="noopener">TikTok @bachatabakery</a></p>
      </div>
      <div>
        <h4>Pickup Locations</h4>
        <p>Middletown, DE — Fri 3–7 PM</p>
        <p style="margin-top:4px">West Chester, PA — date confirmed by email</p>
      </div>
      <div>
        <h4>Quick Links</h4>
        <p><a href="products.html">Products &amp; Pricing</a></p>
        <p><a href="faq.html">FAQ</a></p>
        <p><a href="order.html">Place an Order</a></p>
        <p><a href="cinnamon-rolls.html">Cinnamon Roll Presale</a></p>
      </div>
    </div>
    <div class="footer-bottom"><p>&copy; 2026 Bachata Bakery. All rights reserved.</p></div>
  </footer>

  <script src="script.js"></script>
</body>
</html>
```

- [ ] **Step 2: Open `http://localhost:8000/products.html` and verify**

Check:
- 5 pricing cards render, "My Favorite Things" has gradient background
- Prices display in large Epilogue font
- CTA banner at bottom is gradient pink/red
- "Order Now" buttons are pill-shaped
- Nav active state highlights "Products"

- [ ] **Step 3: Commit**

```bash
git add products.html
git commit -m "feat: build products and pricing page with 5-tier pricing cards"
```

---

## Task 6: FAQ (`faq.html`)

**Files:**
- Create: `faq.html`

- [ ] **Step 1: Create faq.html**

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>FAQ — Bachata Bakery</title>
  <meta name="description" content="Frequently asked questions about ordering custom cookies from Bachata Bakery — pickup, deposits, lead time, and policies.">
  <link rel="stylesheet" href="styles.css">
</head>
<body>

  <!-- NAV -->
  <nav class="nav" id="site-nav">
    <div class="nav-inner">
      <a href="index.html" class="nav-logo">
        <img src="assets/logo-without-background-web.png" alt="Bachata Bakery" width="160" height="48">
      </a>
      <ul class="nav-links">
        <li><a href="index.html">Home</a></li>
        <li><a href="products.html">Products</a></li>
        <li><a href="faq.html" class="active">FAQ</a></li>
        <li><a href="order.html">Order</a></li>
        <li><a href="cinnamon-rolls.html">Cinnamon Rolls</a></li>
        <li><a href="meet-the-baker.html">Meet the Baker</a></li>
      </ul>
      <button class="nav-hamburger" aria-label="Toggle menu" aria-expanded="false">
        <span></span><span></span><span></span>
      </button>
    </div>
    <nav class="nav-mobile">
      <a href="index.html">Home</a>
      <a href="products.html">Products</a>
      <a href="faq.html" class="active">FAQ</a>
      <a href="order.html">Order</a>
      <a href="cinnamon-rolls.html">Cinnamon Rolls</a>
      <a href="meet-the-baker.html">Meet the Baker</a>
    </nav>
  </nav>

  <!-- PAGE HERO -->
  <section class="page-hero">
    <p class="label" style="color:var(--primary);margin-bottom:var(--space-3)">Got Questions?</p>
    <h1 class="display-md">Frequently Asked Questions</h1>
    <p class="body-lg mt-4">Everything you need to know before placing your order.</p>
  </section>

  <!-- FAQ CONTENT -->
  <section class="section">
    <div class="container" style="max-width:800px">

      <div class="callout">
        <div class="callout-title">Pickup Only — No Shipping</div>
        <p class="body-md">All orders are pickup only. Delaware Cottage Food License restricts us to local pickup — we cannot ship cookies.</p>
      </div>

      <details class="accordion-item">
        <summary>Where do I pick up my order?</summary>
        <div class="accordion-body">
          <p>We offer pickup in <strong>Middletown, DE</strong> (free, our main location) and <strong>West Chester, PA</strong>. Wilmington and Dover pickups are available with a small convenience fee. Local delivery is available at a distance-based fee. Exact pickup address is emailed to you closer to your pickup date.</p>
        </div>
      </details>

      <details class="accordion-item">
        <summary>Do you ship?</summary>
        <div class="accordion-body">
          <p>No — we are unable to ship. Our Delaware Cottage Food License restricts all orders to local pickup only. We appreciate your understanding.</p>
        </div>
      </details>

      <details class="accordion-item">
        <summary>How far in advance do I need to order?</summary>
        <div class="accordion-body">
          <p>We require a <strong>2–4 week lead time</strong> for all custom cookie orders. Rush orders placed within 7 days of the pickup date are available but incur a <strong>25% rush surcharge</strong>.</p>
        </div>
      </details>

      <details class="accordion-item">
        <summary>What is the minimum order size?</summary>
        <div class="accordion-body">
          <p>The minimum order is <strong>2 dozen cookies</strong> for all tiers. The exception is the "My Favorite Things" set, which has a 1-dozen minimum.</p>
        </div>
      </details>

      <details class="accordion-item">
        <summary>How does payment work?</summary>
        <div class="accordion-body">
          <p>A <strong>50% non-refundable deposit</strong> is required within 48 hours of receiving your invoice — this secures your date. The remaining balance is due <strong>one week before your pickup date</strong>. Invoices not paid within 48 hours are automatically cancelled.</p>
        </div>
      </details>

      <details class="accordion-item">
        <summary>Can I change my order after placing it?</summary>
        <div class="accordion-body">
          <p>Theme and quantity changes are allowed <strong>within 3 days of your deposit</strong>. After that, your order is finalized and changes cannot be accommodated.</p>
        </div>
      </details>

      <details class="accordion-item">
        <summary>What is your cancellation policy?</summary>
        <div class="accordion-body">
          <p>Cancellations are non-refundable. As an alternative, we are happy to reschedule your order to a future available date.</p>
        </div>
      </details>

      <details class="accordion-item">
        <summary>What happens if I don't pay my invoice on time?</summary>
        <div class="accordion-body">
          <p>If your invoice is not paid within <strong>48 hours</strong> of receipt, your order will be automatically cancelled and the spot will be released.</p>
        </div>
      </details>

      <details class="accordion-item">
        <summary>Are rush orders available?</summary>
        <div class="accordion-body">
          <p>Yes — rush orders placed within 7 days of the pickup date are accepted subject to availability. A <strong>25% rush surcharge</strong> is added to the total. Contact us at <a href="mailto:info@bachatabakery.com" style="color:var(--primary);font-weight:600">info@bachatabakery.com</a> to check availability first.</p>
        </div>
      </details>

      <div style="margin-top:var(--space-7);text-align:center">
        <p class="body-lg" style="margin-bottom:var(--space-4)">Still have a question?</p>
        <a href="mailto:info@bachatabakery.com" class="btn btn-primary">Email Us</a>
      </div>

    </div>
  </section>

  <!-- FOOTER -->
  <footer class="footer">
    <div class="footer-inner">
      <div>
        <div class="footer-logo"><img src="assets/logo-without-background-web.png" alt="Bachata Bakery"></div>
        <p style="margin-top:16px;font-size:0.9rem;opacity:0.7">Authentic Latin-inspired desserts<br>made with love.</p>
      </div>
      <div>
        <h4>Contact</h4>
        <p><a href="mailto:info@bachatabakery.com">info@bachatabakery.com</a></p>
        <p style="margin-top:8px"><a href="https://www.instagram.com/bachatabakery" target="_blank" rel="noopener">Instagram @bachatabakery</a></p>
        <p><a href="https://www.tiktok.com/@bachatabakery" target="_blank" rel="noopener">TikTok @bachatabakery</a></p>
      </div>
      <div>
        <h4>Pickup Locations</h4>
        <p>Middletown, DE — Fri 3–7 PM</p>
        <p style="margin-top:4px">West Chester, PA — date confirmed by email</p>
      </div>
      <div>
        <h4>Quick Links</h4>
        <p><a href="products.html">Products &amp; Pricing</a></p>
        <p><a href="faq.html">FAQ</a></p>
        <p><a href="order.html">Place an Order</a></p>
        <p><a href="cinnamon-rolls.html">Cinnamon Roll Presale</a></p>
      </div>
    </div>
    <div class="footer-bottom"><p>&copy; 2026 Bachata Bakery. All rights reserved.</p></div>
  </footer>

  <script src="script.js"></script>
</body>
</html>
```

- [ ] **Step 2: Open `http://localhost:8000/faq.html` and verify**

Check:
- "Pickup Only" callout has left red border, displays prominently
- All 9 accordion items render closed
- Clicking a question opens/closes it (CSS `<details>` — no JS needed)
- Chevron rotates on open
- "Email Us" button at bottom links to mailto

- [ ] **Step 3: Commit**

```bash
git add faq.html
git commit -m "feat: build FAQ page with policy callout and 9-item CSS accordion"
```

---

## Task 7: Order Form (`order.html`)

**Files:**
- Create: `order.html`

- [ ] **Step 1: Create order.html**

> **Note:** The Jotform iframe `src` URL must be obtained from the Bachata Bakery Jotform account. The placeholder `https://form.jotform.com/FORM-ID` must be replaced with the real embed URL before deploying.

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Order Form — Bachata Bakery</title>
  <meta name="description" content="Place a custom cookie order with Bachata Bakery. Review our pre-order guidelines and submit your request.">
  <link rel="stylesheet" href="styles.css">
</head>
<body>

  <!-- NAV -->
  <nav class="nav" id="site-nav">
    <div class="nav-inner">
      <a href="index.html" class="nav-logo">
        <img src="assets/logo-without-background-web.png" alt="Bachata Bakery" width="160" height="48">
      </a>
      <ul class="nav-links">
        <li><a href="index.html">Home</a></li>
        <li><a href="products.html">Products</a></li>
        <li><a href="faq.html">FAQ</a></li>
        <li><a href="order.html" class="active">Order</a></li>
        <li><a href="cinnamon-rolls.html">Cinnamon Rolls</a></li>
        <li><a href="meet-the-baker.html">Meet the Baker</a></li>
      </ul>
      <button class="nav-hamburger" aria-label="Toggle menu" aria-expanded="false">
        <span></span><span></span><span></span>
      </button>
    </div>
    <nav class="nav-mobile">
      <a href="index.html">Home</a>
      <a href="products.html">Products</a>
      <a href="faq.html">FAQ</a>
      <a href="order.html" class="active">Order</a>
      <a href="cinnamon-rolls.html">Cinnamon Rolls</a>
      <a href="meet-the-baker.html">Meet the Baker</a>
    </nav>
  </nav>

  <!-- PAGE HERO -->
  <section class="page-hero">
    <p class="label" style="color:var(--primary);margin-bottom:var(--space-3)">Custom Cookies</p>
    <h1 class="display-md">Place an Order</h1>
    <p class="body-lg mt-4">Your submission is a <strong>request</strong>, not a guarantee. We will confirm availability within 48 hours.</p>
  </section>

  <!-- PRE-ORDER GUIDELINES -->
  <section class="section">
    <div class="container" style="max-width:800px">

      <h2 class="headline-lg" style="margin-bottom:var(--space-5)">Before you submit — please review:</h2>

      <ol class="policy-list">
        <li>
          <span class="policy-num">1</span>
          <span><strong>Minimum order:</strong> 2 dozen cookies. The only exception is the "My Favorite Things" set (1-dozen minimum).</span>
        </li>
        <li>
          <span class="policy-num">2</span>
          <span><strong>Lead time:</strong> 2–4 weeks required. Rush orders within 7 days of pickup incur a 25% surcharge.</span>
        </li>
        <li>
          <span class="policy-num">3</span>
          <span><strong>Pickup only.</strong> We cannot ship. Pickup in Middletown, DE (free) or West Chester, PA. Address confirmed by email.</span>
        </li>
        <li>
          <span class="policy-num">4</span>
          <span><strong>Deposit:</strong> 50% non-refundable deposit due within 48 hours of invoice to secure your date.</span>
        </li>
        <li>
          <span class="policy-num">5</span>
          <span><strong>Balance:</strong> Remaining 50% due one week before your pickup date.</span>
        </li>
        <li>
          <span class="policy-num">6</span>
          <span><strong>Changes:</strong> Theme and quantity changes accepted within 3 days of deposit. Orders are finalized after that.</span>
        </li>
        <li>
          <span class="policy-num">7</span>
          <span><strong>Cancellations:</strong> Non-refundable. Rescheduling to a future date is available as an alternative.</span>
        </li>
        <li>
          <span class="policy-num">8</span>
          <span><strong>Unpaid invoices:</strong> Orders with invoices unpaid after 48 hours are automatically cancelled.</span>
        </li>
      </ol>

      <!-- JOTFORM EMBED -->
      <div style="margin-top:var(--space-6)">
        <iframe
          id="order-form"
          title="Bachata Bakery Order Request Form"
          src="https://form.jotform.com/FORM-ID"
          width="100%"
          height="800"
          frameborder="0"
          scrolling="yes"
          style="border-radius:var(--radius-md);box-shadow:var(--shadow-card);"
          allowfullscreen
        ></iframe>
      </div>

    </div>
  </section>

  <!-- FOOTER -->
  <footer class="footer">
    <div class="footer-inner">
      <div>
        <div class="footer-logo"><img src="assets/logo-without-background-web.png" alt="Bachata Bakery"></div>
        <p style="margin-top:16px;font-size:0.9rem;opacity:0.7">Authentic Latin-inspired desserts<br>made with love.</p>
      </div>
      <div>
        <h4>Contact</h4>
        <p><a href="mailto:info@bachatabakery.com">info@bachatabakery.com</a></p>
        <p style="margin-top:8px"><a href="https://www.instagram.com/bachatabakery" target="_blank" rel="noopener">Instagram @bachatabakery</a></p>
        <p><a href="https://www.tiktok.com/@bachatabakery" target="_blank" rel="noopener">TikTok @bachatabakery</a></p>
      </div>
      <div>
        <h4>Pickup Locations</h4>
        <p>Middletown, DE — Fri 3–7 PM</p>
        <p style="margin-top:4px">West Chester, PA — date confirmed by email</p>
      </div>
      <div>
        <h4>Quick Links</h4>
        <p><a href="products.html">Products &amp; Pricing</a></p>
        <p><a href="faq.html">FAQ</a></p>
        <p><a href="order.html">Place an Order</a></p>
        <p><a href="cinnamon-rolls.html">Cinnamon Roll Presale</a></p>
      </div>
    </div>
    <div class="footer-bottom"><p>&copy; 2026 Bachata Bakery. All rights reserved.</p></div>
  </footer>

  <script src="script.js"></script>
</body>
</html>
```

- [ ] **Step 2: Get the real Jotform embed URL**

Log into the Bachata Bakery Jotform account → open the order form → click **Publish** → copy the **Embed** iframe `src` URL → replace `https://form.jotform.com/FORM-ID` in the iframe above.

- [ ] **Step 3: Open `http://localhost:8000/order.html` and verify**

Check:
- Policy list renders with numbered gradient circles
- Jotform iframe loads (if real URL added) or shows blank area (if placeholder)
- "Your submission is a request, not a guarantee." subhead is visible in page hero

- [ ] **Step 4: Commit**

```bash
git add order.html
git commit -m "feat: build order form page with policy gate and Jotform iframe embed"
```

---

## Task 8: Cinnamon Roll Presale (`cinnamon-rolls.html`)

**Files:**
- Create: `cinnamon-rolls.html`

- [ ] **Step 1: Create cinnamon-rolls.html**

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cinnamon Roll Presale — Bachata Bakery</title>
  <meta name="description" content="Weekly cinnamon roll presale — 4 Latin-inspired flavors. Opens Friday noon, closes Sunday midnight or until sold out.">
  <link rel="stylesheet" href="styles.css">
</head>
<body>

  <!-- NAV -->
  <nav class="nav" id="site-nav">
    <div class="nav-inner">
      <a href="index.html" class="nav-logo">
        <img src="assets/logo-without-background-web.png" alt="Bachata Bakery" width="160" height="48">
      </a>
      <ul class="nav-links">
        <li><a href="index.html">Home</a></li>
        <li><a href="products.html">Products</a></li>
        <li><a href="faq.html">FAQ</a></li>
        <li><a href="order.html">Order</a></li>
        <li><a href="cinnamon-rolls.html" class="active">Cinnamon Rolls</a></li>
        <li><a href="meet-the-baker.html">Meet the Baker</a></li>
      </ul>
      <button class="nav-hamburger" aria-label="Toggle menu" aria-expanded="false">
        <span></span><span></span><span></span>
      </button>
    </div>
    <nav class="nav-mobile">
      <a href="index.html">Home</a>
      <a href="products.html">Products</a>
      <a href="faq.html">FAQ</a>
      <a href="order.html">Order</a>
      <a href="cinnamon-rolls.html" class="active">Cinnamon Rolls</a>
      <a href="meet-the-baker.html">Meet the Baker</a>
    </nav>
  </nav>

  <!-- HERO -->
  <section class="page-hero">
    <p class="label" style="color:var(--primary);margin-bottom:var(--space-3)">Weekly Presale</p>
    <h1 class="display-md">Cinnamon Roll Presale</h1>
    <p class="body-lg mt-4" style="color:var(--primary);font-weight:600">Opens Friday at noon — closes Sunday midnight or until sold out.</p>
  </section>

  <!-- SCHEDULE BOX -->
  <section class="section">
    <div class="container">
      <div class="schedule-box">
        <h2 class="headline-lg">Every Week</h2>
        <p class="body-md" style="margin-top:var(--space-2)">
          <strong>Opens:</strong> Friday 12:00 PM<br>
          <strong>Closes:</strong> Sunday midnight — or when sold out<br>
          <strong>Pickup details</strong> emailed no later than Wednesday
        </p>
      </div>

      <!-- FLAVOR CARDS -->
      <h2 class="display-sm text-center mb-6">This Week's Flavors</h2>
      <div class="flavor-grid">

        <div class="flavor-card">
          <div class="flavor-name">El Clásico</div>
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:var(--space-2)">
            <span class="chip">Classic</span>
          </div>
          <p class="flavor-detail"><strong>Frosting:</strong> Cream cheese</p>
        </div>

        <div class="flavor-card">
          <div class="flavor-name">La Dominicana</div>
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:var(--space-2)">
            <span class="chip">Guava</span>
          </div>
          <p class="flavor-detail"><strong>Frosting:</strong> Guava cream cheese</p>
        </div>

        <div class="flavor-card">
          <div class="flavor-name">Dulce</div>
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:var(--space-2)">
            <span class="chip">Dulce de Leche</span>
            <span class="chip">Sea Salt</span>
          </div>
          <p class="flavor-detail"><strong>Frosting:</strong> Dulce de leche with sea salt</p>
        </div>

        <div class="flavor-card">
          <div class="flavor-name">Café con Leche</div>
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:var(--space-2)">
            <span class="chip">Espresso</span>
          </div>
          <p class="flavor-detail"><strong>Frosting:</strong> Coffee cream cheese</p>
        </div>

      </div>

      <!-- ORDER CTA -->
      <div style="text-align:center;margin-top:var(--space-7)">
        <p class="body-lg mb-4">Ready to grab yours before they sell out?</p>
        <a href="cinnamon-roll-order-form/index.html" class="btn btn-primary">Order Cinnamon Rolls</a>
      </div>

      <!-- PICKUP INFO -->
      <h2 class="headline-lg text-center mt-6 mb-5">Pickup Locations</h2>
      <div class="pickup-grid">
        <div class="pickup-item">
          <div class="pickup-location">Middletown, DE</div>
          <p class="body-md">Fridays · 3:00 PM – 7:00 PM</p>
        </div>
        <div class="pickup-item">
          <div class="pickup-location">West Chester, PA</div>
          <p class="body-md">Date &amp; time confirmed directly with you by email after ordering.</p>
        </div>
      </div>

    </div>
  </section>

  <!-- FOOTER -->
  <footer class="footer">
    <div class="footer-inner">
      <div>
        <div class="footer-logo"><img src="assets/logo-without-background-web.png" alt="Bachata Bakery"></div>
        <p style="margin-top:16px;font-size:0.9rem;opacity:0.7">Authentic Latin-inspired desserts<br>made with love.</p>
      </div>
      <div>
        <h4>Contact</h4>
        <p><a href="mailto:info@bachatabakery.com">info@bachatabakery.com</a></p>
        <p style="margin-top:8px"><a href="https://www.instagram.com/bachatabakery" target="_blank" rel="noopener">Instagram @bachatabakery</a></p>
        <p><a href="https://www.tiktok.com/@bachatabakery" target="_blank" rel="noopener">TikTok @bachatabakery</a></p>
      </div>
      <div>
        <h4>Pickup Locations</h4>
        <p>Middletown, DE — Fri 3–7 PM</p>
        <p style="margin-top:4px">West Chester, PA — date confirmed by email</p>
      </div>
      <div>
        <h4>Quick Links</h4>
        <p><a href="products.html">Products &amp; Pricing</a></p>
        <p><a href="faq.html">FAQ</a></p>
        <p><a href="order.html">Place an Order</a></p>
        <p><a href="cinnamon-rolls.html">Cinnamon Roll Presale</a></p>
      </div>
    </div>
    <div class="footer-bottom"><p>&copy; 2026 Bachata Bakery. All rights reserved.</p></div>
  </footer>

  <script src="script.js"></script>
</body>
</html>
```

- [ ] **Step 2: Open `http://localhost:8000/cinnamon-rolls.html` and verify**

Check:
- Schedule box shows weekly presale times in a clean card
- 4 flavor cards render; chips (`#c59eff` purple) appear on each
- "Order Cinnamon Rolls" button links to `cinnamon-roll-order-form/index.html`
- Pickup grid shows 2 columns (1 on mobile)

- [ ] **Step 3: Commit**

```bash
git add cinnamon-rolls.html
git commit -m "feat: build cinnamon roll presale page with flavor cards, schedule, and order CTA"
```

---

## Task 9: Meet the Baker (`meet-the-baker.html`)

**Files:**
- Create: `meet-the-baker.html`

- [ ] **Step 1: Create meet-the-baker.html**

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Meet the Baker — Bachata Bakery</title>
  <meta name="description" content="Meet Raquel, founder of Bachata Bakery. A Bronx-born Dominican and Puerto Rican baker whose treats tell stories of heritage, resilience, and love.">
  <link rel="stylesheet" href="styles.css">
</head>
<body>

  <!-- NAV -->
  <nav class="nav" id="site-nav">
    <div class="nav-inner">
      <a href="index.html" class="nav-logo">
        <img src="assets/logo-without-background-web.png" alt="Bachata Bakery" width="160" height="48">
      </a>
      <ul class="nav-links">
        <li><a href="index.html">Home</a></li>
        <li><a href="products.html">Products</a></li>
        <li><a href="faq.html">FAQ</a></li>
        <li><a href="order.html">Order</a></li>
        <li><a href="cinnamon-rolls.html">Cinnamon Rolls</a></li>
        <li><a href="meet-the-baker.html" class="active">Meet the Baker</a></li>
      </ul>
      <button class="nav-hamburger" aria-label="Toggle menu" aria-expanded="false">
        <span></span><span></span><span></span>
      </button>
    </div>
    <nav class="nav-mobile">
      <a href="index.html">Home</a>
      <a href="products.html">Products</a>
      <a href="faq.html">FAQ</a>
      <a href="order.html">Order</a>
      <a href="cinnamon-rolls.html">Cinnamon Rolls</a>
      <a href="meet-the-baker.html" class="active">Meet the Baker</a>
    </nav>
  </nav>

  <!-- BAKER HERO -->
  <div class="baker-hero">
    <img
      data-src="assets/About%20Me.jpg"
      src=""
      alt="Raquel, founder of Bachata Bakery, holding a Hamilton-themed cookie"
      loading="lazy"
    >
    <div>
      <p class="label" style="color:var(--primary);margin-bottom:var(--space-3)">The Baker</p>
      <h1 class="display-md">Hi, I'm Raquel.</h1>
      <p class="baker-tagline">"Just like the music, my treats tell stories."</p>
      <p class="body-lg" style="margin-top:var(--space-4)">A Bronx-born Dominican and Puerto Rican baker making custom desserts rooted in Latin Caribbean culture — from a licensed commercial kitchen in West Chester, PA.</p>
    </div>
  </div>

  <!-- ORIGIN STORY -->
  <section class="section-alt">
    <div class="container" style="max-width:800px">
      <h2 class="display-sm" style="margin-bottom:var(--space-5)">The Story Behind the Bakery</h2>
      <p class="body-lg" style="margin-bottom:var(--space-4)">
        Bachata Bakery was born in 2020, at a moment when the world stood still. I had always baked for my family — Dominican cakes for birthdays, Puerto Rican sweets for holidays — but it wasn't until the pandemic that I truly leaned into the craft as something more than tradition. Baking became my way of processing grief, finding rhythm, and creating something beautiful in an uncertain time.
      </p>
      <p class="body-lg" style="margin-bottom:var(--space-4)">
        Growing up in the Bronx with Dominican and Puerto Rican parents, food was never just food. It was how we said "I love you." It was how we celebrated, mourned, and gathered. I carried those flavors with me when I moved to Delaware, and I carry them still in every cookie I make.
      </p>
      <p class="body-lg" style="margin-bottom:var(--space-4)">
        Before baking, I earned my Sociology degree (with a minor in Education) while being the primary caregiver for my father, who was battling Stage 3 colon cancer — and simultaneously helping raise my newborn sibling. I've always believed that strength is quiet, and that love shows up in the everyday details. A perfectly iced cookie is one of those details.
      </p>
      <p class="body-lg">
        I named this business Bachata because that music — vibrant, culturally rich, full of heart and movement — is exactly what I want people to feel when they experience my bakes. My background in makeup artistry and YouTube content creation taught me that presentation is part of the story. Every Bachata Bakery treat is designed to be seen, savored, and remembered.
      </p>
    </div>
  </section>

  <!-- BRAND VALUES -->
  <section class="section">
    <div class="container">
      <h2 class="display-sm text-center mb-6">What We Stand For</h2>
      <div class="values-row">
        <div class="value-item">
          <div class="value-icon">🤝</div>
          <div class="value-title">Community</div>
          <p class="body-md">Every order supports a small, family-run business rooted in the Delaware community.</p>
        </div>
        <div class="value-item">
          <div class="value-icon">🌺</div>
          <div class="value-title">Heritage</div>
          <p class="body-md">Our flavors and aesthetic draw directly from Dominican and Puerto Rican traditions.</p>
        </div>
        <div class="value-item">
          <div class="value-icon">✨</div>
          <div class="value-title">Craft</div>
          <p class="body-md">Every cookie is hand-decorated with intention — no shortcuts, no templates.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- BRAND NAME CALLOUT -->
  <section class="section-alt">
    <div class="container" style="max-width:680px;text-align:center">
      <div class="callout" style="text-align:left">
        <div class="callout-title">Why "Bachata"?</div>
        <p class="body-md">Bachata is Dominican music — vibrant, culturally rich, full of heart and movement. It speaks to community, to memory, to joy. That's the feeling we put into every bake. Just like the music, our treats tell stories.</p>
      </div>
      <a href="order.html" class="btn btn-primary mt-5">Order Your Story</a>
    </div>
  </section>

  <!-- FOOTER -->
  <footer class="footer">
    <div class="footer-inner">
      <div>
        <div class="footer-logo"><img src="assets/logo-without-background-web.png" alt="Bachata Bakery"></div>
        <p style="margin-top:16px;font-size:0.9rem;opacity:0.7">Authentic Latin-inspired desserts<br>made with love.</p>
      </div>
      <div>
        <h4>Contact</h4>
        <p><a href="mailto:info@bachatabakery.com">info@bachatabakery.com</a></p>
        <p style="margin-top:8px"><a href="https://www.instagram.com/bachatabakery" target="_blank" rel="noopener">Instagram @bachatabakery</a></p>
        <p><a href="https://www.tiktok.com/@bachatabakery" target="_blank" rel="noopener">TikTok @bachatabakery</a></p>
      </div>
      <div>
        <h4>Pickup Locations</h4>
        <p>Middletown, DE — Fri 3–7 PM</p>
        <p style="margin-top:4px">West Chester, PA — date confirmed by email</p>
      </div>
      <div>
        <h4>Quick Links</h4>
        <p><a href="products.html">Products &amp; Pricing</a></p>
        <p><a href="faq.html">FAQ</a></p>
        <p><a href="order.html">Place an Order</a></p>
        <p><a href="cinnamon-rolls.html">Cinnamon Roll Presale</a></p>
      </div>
    </div>
    <div class="footer-bottom"><p>&copy; 2026 Bachata Bakery. All rights reserved.</p></div>
  </footer>

  <script src="script.js"></script>
</body>
</html>
```

- [ ] **Step 2: Open `http://localhost:8000/meet-the-baker.html` and verify**

Check:
- Baker hero: Raquel's photo left, text right (stacks on mobile)
- Tagline "*Just like the music, my treats tell stories.*" appears in primary pink below the headline
- 3 value icons render in a row
- "Why Bachata?" callout box has left pink border
- "Order Your Story" button links to order.html

- [ ] **Step 3: Commit**

```bash
git add meet-the-baker.html
git commit -m "feat: build meet the baker page with origin story, values, and brand callout"
```

---

## Task 10: SEO Meta Pass & Final QA

**Files:**
- Modify: `index.html`, `products.html`, `faq.html`, `order.html`, `cinnamon-rolls.html`, `meet-the-baker.html`

- [ ] **Step 1: Add Open Graph meta tags to all 6 pages**

Add the following block inside `<head>` of each page, adjusting `og:title`, `og:description`, and `og:url` per page. Use `og:image` pointing to the nav banner for all pages.

```html
<!-- Open Graph -->
<meta property="og:type"        content="website">
<meta property="og:site_name"   content="Bachata Bakery">
<meta property="og:image"       content="https://bachatabakery.com/assets/Nav-Banner-Image.webp">
<meta property="og:image:width"  content="1200">
<meta property="og:image:height" content="630">

<!-- Per-page values (replace these): -->
<meta property="og:title"       content="Bachata Bakery — Authentic Latin-Inspired Desserts">
<meta property="og:description" content="Custom sugar cookies and cinnamon rolls made with love in Delaware.">
<meta property="og:url"         content="https://bachatabakery.com/">
```

Per-page values:

| Page | og:title | og:description | og:url |
|------|----------|----------------|--------|
| index.html | Bachata Bakery — Authentic Latin-Inspired Desserts | Custom sugar cookies and cinnamon rolls made with love in Delaware. | https://bachatabakery.com/ |
| products.html | Products & Pricing — Bachata Bakery | Custom sugar cookie tiers from $56/dozen. Minimum 2 dozen, 2–4 week lead time. | https://bachatabakery.com/products.html |
| faq.html | FAQ — Bachata Bakery | Answers to all your questions about ordering, pickup, deposits, and policies. | https://bachatabakery.com/faq.html |
| order.html | Place an Order — Bachata Bakery | Submit a custom cookie order request. Review our pre-order guidelines first. | https://bachatabakery.com/order.html |
| cinnamon-rolls.html | Cinnamon Roll Presale — Bachata Bakery | Weekly presale, 4 Latin-inspired flavors. Opens Friday noon — closes Sunday midnight. | https://bachatabakery.com/cinnamon-rolls.html |
| meet-the-baker.html | Meet the Baker — Bachata Bakery | Raquel's story: Bronx roots, Dominican & Puerto Rican heritage, and a bakery born from love. | https://bachatabakery.com/meet-the-baker.html |

- [ ] **Step 2: Replace hero lazy-load with eager load on index.html**

The hero background image should not lazy-load (it's above the fold). In `index.html`, update the hero img:

```html
<!-- Change this: -->
<img class="hero-bg" data-src="assets/Nav-Banner-Image.webp" src="" alt="..." loading="eager">

<!-- To this (remove data-src, set src directly): -->
<img class="hero-bg" src="assets/Nav-Banner-Image.webp" alt="Bachata Bakery artisan cookies" loading="eager">
```

- [ ] **Step 3: Walk every page and check internal links**

Open each page at `http://localhost:8000` and click every nav link. Verify:
- All 6 nav links resolve to the correct page
- "Place an Order" CTAs link to `order.html`
- "My Story" link on home goes to `meet-the-baker.html`
- "Order Cinnamon Rolls" on cinnamon-rolls.html links to `cinnamon-roll-order-form/index.html` (the existing PHP form)
- Email links open mail client

- [ ] **Step 4: Check mobile nav on all pages**

Resize browser to 375px width. On each page:
- Desktop nav links are hidden
- Hamburger icon is visible
- Clicking hamburger opens mobile nav
- Clicking a nav link closes the menu and navigates

- [ ] **Step 5: Final commit**

```bash
git add index.html products.html faq.html order.html cinnamon-rolls.html meet-the-baker.html
git commit -m "feat: add Open Graph meta tags and fix hero eager-load across all pages"
```

---

## Post-Implementation: LightWidget Setup

Before deploying, the Instagram embed on `index.html` requires a LightWidget account:

1. Go to [lightwidget.com](https://lightwidget.com) and create a free account
2. Connect the `@bachatabakery` Instagram account
3. Create a new widget, copy the embed code
4. In `index.html`, replace `WIDGET-CODE` in the iframe `src` with your real widget ID
5. Replace the `<script>` tag src if LightWidget provides a different one

## Deployment Checklist

- [ ] Replace Jotform `FORM-ID` with real form ID from Bachata Bakery Jotform account
- [ ] Replace LightWidget `WIDGET-CODE` with real widget ID
- [ ] Upload all files to Hostinger via FTP (do not touch `cinnamon-roll-order-form/` — it is a live PHP app)
- [ ] Verify `styles.css` and `script.js` upload correctly
- [ ] Verify `assets/` folder uploads completely (watch for filenames with spaces)
- [ ] Test all 6 pages on live domain
- [ ] Test mobile nav on a real device
