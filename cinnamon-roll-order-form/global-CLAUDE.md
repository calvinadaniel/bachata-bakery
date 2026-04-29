# CLAUDE.md — Global Developer Standards
# Calvin Daniel · Freelance Web Developer
# Place this file at: ~/.claude/CLAUDE.md
# Applies to ALL Claude Code projects automatically.

---

## WHO I AM

I am Calvin Daniel — a freelance web developer and Commercial Operations Specialist. I build production-grade websites for small business clients, hosted on Hostinger. I also work in Power BI and SQL professionally. All code I write must be clean, real-world deployable, and match my personal conventions exactly.

---

## UNIVERSAL CODE STANDARDS

These rules apply to every project, every file, every session — no exceptions.

### JavaScript
- **Vanilla JS only** — no jQuery, no React, no Vue, no frameworks unless I explicitly ask
- Always use `const` and `let` — **never `var`**
- Always wrap execution in `DOMContentLoaded` or use `defer` on script tags
- Write modular, named functions — no anonymous function soup
- Use `IntersectionObserver` for scroll-triggered animations — never scroll event listeners
- Use `fetch()` for all HTTP calls — no XMLHttpRequest

### CSS
- **No CSS frameworks** — no Bootstrap, no Tailwind, no Foundation
- Always use **CSS custom properties** (variables) in `:root` for all colors, spacing, typography, and transitions
- Mobile-first always — base styles at 0px, scale up with `min-width` media queries
- Standard breakpoints: `768px` (tablet), `1024px` (desktop), `1440px` (wide)
- Use CSS Grid for layout, Flexbox for alignment — never floats
- Use `transform` for animations — never `top/left/right/bottom` position toggling
- No inline styles in HTML, no `<style>` blocks in HTML files

### PHP
- PHP 8.x only
- **All database queries use PDO with prepared statements** — no raw queries, ever
- Always use `try/catch` around DB calls
- Store all secrets in `.env` — never hardcode credentials
- Return consistent JSON from all API endpoints: `{ success, data|error_code, message }`
- Always set `Content-Type: application/json` before echoing JSON responses

### HTML
- Semantic HTML5 — use proper elements (`<header>`, `<main>`, `<section>`, `<nav>`, etc.)
- All images get `loading="lazy"` and meaningful `alt` attributes
- Full ARIA attributes on all interactive components (menus, modals, buttons)

### File Structure
- Separate files always: `index.html`, `styles.css`, `script.js` — never inline styles or scripts
- PHP API files live in `/api/` — never mix backend logic into HTML files
- Secrets always in `.env` — always include `.env.example` with placeholder values
- Always include `.htaccess` that blocks direct browser access to `/api/helpers/` and `.env`

---

## HOSTING CONTEXT

- **Host:** Hostinger (shared or VPS)
- **PHP:** 8.x
- **MySQL:** 8.x
- **Email:** PHPMailer + Hostinger SMTP (`smtp.hostinger.com`, port 465)
- SSL is always active — HTTPS only

---

## HOW I WORK

- Build phase by phase — confirm each phase is complete before moving to the next
- When you create a file, tell me what was built and what comes next
- Never skip error handling — every API endpoint, every DB call gets try/catch
- Never use placeholder comments like `// TODO: add logic here` — write the actual logic
- When in doubt about a design decision, ask me before implementing

---

## NAVIGATION / OFF-CANVAS MENUS

Every site I build uses an off-canvas mobile menu. Always follow these rules:

- Mobile: hamburger button top-right, panel slides in from the **left**
- Use `transform: translateX(-100%)` → `translateX(0)` — never position toggling
- Hamburger animates to an X using `.is-open` class (CSS-only)
- Three `<span class="hamburger__bar">` elements inside the button
- Dark overlay behind panel, closes on overlay click
- Nav links stagger in with `transition-delay` increments of 0.07s
- Desktop nav replaces off-canvas at `min-width: 1024px`
- `body.nav-open` prevents background scroll when panel is open
- Close triggers: overlay click, close button, Escape key, nav link click
- Focus: moves to close button on open, returns to hamburger on close
- Full ARIA: `aria-expanded`, `aria-controls`, `aria-hidden`, `aria-label`

---

## TYPOGRAPHY DEFAULTS

- Always pair a **display font** (headings) with a **body font** — never one font for everything
- Source from Google Fonts
- Avoid overused fonts: Inter, Roboto, Arial, Open Sans, Lato
- Use `clamp()` for fluid heading sizes
- Default pairing for warm/artisanal clients: **Playfair Display** (display) + **DM Sans** (body)
