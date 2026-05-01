# Bachata Bakery Website Redesign Spec
**Date:** 2026-04-29  
**Status:** Approved for implementation  
**Site:** bachatabakery.com

---

## 1. Project Context

Bachata Bakery is a custom sugar cookie and cinnamon roll business founded by Raquel, a Bronx-born Dominican/Puerto Rican baker based in Delaware. The brand story centers on Latin Caribbean heritage, pandemic-era resilience, and desserts as cultural storytelling.

**Rebuild drivers:** Upcoming high-traffic marketing events requiring a polished, modern site that converts new visitors unfamiliar with custom bakery workflows.

---

## 2. Stack & Constraints

| Decision | Choice | Reason |
|----------|--------|--------|
| Framework | Vanilla HTML/CSS/JS | No build tooling, no framework — static files only |
| Component library | None (custom) | Shadcn/UI explicitly dropped |
| CMS | None | Content managed manually |
| Hosting | Hostinger | Existing; email hosting must not change |
| Order form | Jotform embed retained | Replacement deferred |
| Instagram feed | LightWidget embed | Live feed, auto-updates |
| Cinnamon roll order system | `cinnamon-roll-order-form/` subproject | PHP/Square backend — out of scope, untouched |

---

## 3. Site Structure

| File | Purpose |
|------|---------|
| `index.html` | Home |
| `products.html` | Products & Pricing |
| `faq.html` | FAQ |
| `order.html` | Order Form |
| `cinnamon-rolls.html` | Cinnamon Roll Presale |
| `meet-the-baker.html` | Meet the Baker |
| `styles.css` | Single shared stylesheet |
| `script.js` | Shared JS (nav toggle, lazy-load) |

---

## 4. Shared Components

### Navigation
- Logo left, links right
- 6 nav links: Home, Products, FAQ, Order, Cinnamon Rolls, Meet the Baker
- Mobile: hamburger toggle (JS in `script.js`)
- Style: glassmorphism — `background: rgba(252, 246, 237, 0.8)` + `backdrop-filter: blur(20px)`
- Sticky on scroll

### Footer
- Contact: `info@bachatabakery.com`
- Socials: Instagram `@bachatabakery`, TikTok `@bachatabakery`
- Pickup locations: Middletown DE (Fri 3–7PM), West Chester PA (confirmed by email)
- Copyright

### Buttons
- **Primary:** pill-shaped (`border-radius: 9999px`), `background: linear-gradient(135deg, #b70049, #ff7290)`, white text
- **Secondary:** `surface-container-highest` background, `#b70049` text, no border
- **Tertiary:** text-only in `#ab2d00`, custom underline flourish on hover

---

## 5. Page Specs

### 5.1 Home (`index.html`)

**Sections in order:**
1. **Hero** — Full-width food photo (`Nav Banner Image.webp`), large Epilogue headline, primary CTA → Order page
2. **Gallery** — Horizontal carousel (not grid), user-controlled via drag/swipe/arrow buttons (no autoplay): Hamilton Logo, Bad Bunny PR Set, Ice Cream Cookies, Graduation Cookies, Bluey Cookies, Tangled Set. `radius-lg` (2rem) on all images.
3. **About Teaser** — Asymmetric 2-col: photo left (Raquel holding Hamilton cookie), floating text block overlapping right. Short brand blurb + "Meet the Baker" link.
4. **Instagram Feed** — LightWidget `<script>` embed, `@bachatabakery`

### 5.2 Products & Pricing (`products.html`)

**Sections:**
1. **Page hero** — Headline + subhead ("Custom sugar cookies made to order")
2. **Pricing cards** — 5 cards, horizontal scroll on mobile:

| Tier | Price | Designs | Notes |
|------|-------|---------|-------|
| Simple Set | $56+/doz | 4 | Up to 3 colors (excl. white) |
| Detailed Set | $70+/doz | 6 | 5 colors, 3D florals, metallics, lettering |
| Elaborate Set | $84+/doz | 8 | 6 colors, 3D florals, metallics |
| My Favorite Things | $96+/doz | 12 unique | Gift packaging, 1-doz min |
| Character Cookies | $8–$10 ea | 1 | Add-on to dozen sets |

3. **Order CTA banner** — "Ready to order? Minimum 2 dozen · 2–4 week lead time" → `/order.html`

**Cookie specs (on all cards):** Vanilla icing sugar cookies, 3–4 inches, individually cellophane-wrapped with Care Card.

### 5.3 FAQ (`faq.html`)

**Sections:**
1. **Page hero** — Headline + short intro
2. **Policy callout box** — Key constraint: "Pickup only — no shipping (Delaware Cottage Food License)"
3. **Accordion** — CSS-only via `<details>`/`<summary>`, 9 questions:
   - Where do you pick up?
   - Do you ship? (No — Delaware Cottage Food License)
   - How far in advance do I need to order? (2–4 weeks; rush orders 25% surcharge within 7 days)
   - What's the minimum order? (2 dozen)
   - How does payment work? (50% deposit within 48hrs; balance 1 week before pickup)
   - Can I change my order? (Theme/quantity changes within 3 days of deposit)
   - What if I cancel? (No refund; rescheduling offered)
   - What if I don't pay my invoice? (Auto-cancelled after 48hrs)
   - Are rush orders available? (Yes, +25% surcharge within 7 days of pickup)

### 5.4 Order Form (`order.html`)

**Sections:**
1. **Policy gate** — Ordered list of pre-order guidelines (minimum 2 doz, 2–4 week lead, rush surcharge, pickup-only, 48hr invoice, 50% deposit, change window, form is a request not a guarantee)
2. **Jotform embed** — Existing `<iframe>`, retained as-is

### 5.5 Cinnamon Roll Presale (`cinnamon-rolls.html`)

**Sections:**
1. **Hero** — Headline "Weekly Cinnamon Roll Presale" + urgency subhead ("Opens Friday noon — closes Sunday midnight or until sold out")
2. **Flavor cards** — 4 cards with flavor chip tags (`tertiary-container` `#c59eff`):

| Name | Filling | Frosting |
|------|---------|----------|
| El Clásico | Classic | Cream cheese |
| La Dominicana | Guava | Guava cream cheese |
| Dulce | Dulce de leche | Sea salt |
| Café con Leche | Espresso | Coffee cream cheese |

3. **Presale schedule** — "Every Friday 12PM → Sunday midnight"
4. **How to order** — CTA button linking to `/cinnamon-roll-order-form/` (existing PHP order system)
5. **Pickup info** — 2-col: Middletown DE (Fri 3PM–7PM) / West Chester PA (date confirmed via email)

### 5.6 Meet the Baker (`meet-the-baker.html`)

**Sections:**
1. **Baker hero** — Portrait photo (Raquel + Hamilton cookie), name headline, tagline: *"Just like the music, my treats tell stories."*
2. **Origin story** — Full narrative: Bronx upbringing, Dominican/Puerto Rican heritage, 2020 COVID recovery, family caregiving (father's Stage 3 colon cancer + newborn sibling), Sociology degree, makeup artistry background → baking as rediscovery
3. **Brand values row** — 3-item: Community · Heritage · Craft
4. **Brand name meaning** — Callout box: "Bachata is Dominican music — vibrant, culturally rich, full of heart and movement."

---

## 6. Design Tokens

### Colors

```css
--surface:                  #fcf6ed;
--surface-container-low:    #f6f0e6;
--surface-container-lowest: #ffffff;
--primary:                  #b70049;
--primary-container:        #ff7290;
--primary-fixed:            #ff7290;
--secondary:                #ab2d00;
--on-surface:               #312e29;
--on-primary:               #ffffff;
--outline-variant:          rgba(177, 173, 165, 0.15);
--tertiary-container:       #c59eff;
```

### Typography

```css
/* Google Fonts */
@import url('https://fonts.googleapis.com/css2?family=Epilogue:wght@700;800&family=Plus+Jakarta+Sans:wght@400;500;600&display=swap');

--font-display: 'Epilogue', sans-serif;   /* headlines */
--font-body:    'Plus Jakarta Sans', sans-serif; /* body, UI */
```

### Spacing Scale
`4px · 8px · 16px · 24px · 32px · 48px · 64px · 96px`

### Border Radius
```css
--radius-full: 9999px;  /* buttons */
--radius-lg:   2rem;    /* food images */
--radius-md:   1rem;    /* cards */
--radius-sm:   0.5rem;  /* chips, tags */
```

### Shadows
```css
--shadow-card: 0 20px 40px rgba(49, 46, 41, 0.06);
```

### Gradient (primary CTA)
```css
background: linear-gradient(135deg, #b70049, #ff7290);
```

---

## 7. Design Rules (Non-Negotiable)

1. **No 1px solid borders** — section separation via surface color shift only
2. **No pure black** — use `--on-surface` (`#312e29`) for all text
3. **No standard grid for galleries** — horizontal carousel only
4. **Ghost border fallback** (accessibility only): `outline: 1px solid rgba(177, 173, 165, 0.15)`
5. **Asymmetric layouts** — image left, floating text overlapping right
6. **Roundness minimum** — `radius-md` (1rem) on all cards and containers
7. **Editorial text overlap** — large display text may overlap food photography

---

## 8. Assets Available

All images in `assets/` directory:

**Product photography:** Hamilton Logo Cookie, Bad Bunny Puerto Rico Set, Ice Cream Cookies, Graduation Cookies, Bluey Cookies, Tangled Set, Disney Castle Cookie, Superman Cookie, Super Bowl Set, DIY Kit (3 views), Cinnamon Roll flyer, Tropical Cinnamon Rolls

**Brand assets:** `logo-without-background-web.png` (primary), `bachata-bakery-bg.png`, `Nav-Banner-Image.webp`, `Bachata Bakerty Title One Line.png`

---

## 9. Out of Scope

- `cinnamon-roll-order-form/` PHP subproject — untouched
- Jotform form redesign — embed retained as-is
- WordPress / CMS setup
- Any backend or database work
- Replacing Hostinger email
