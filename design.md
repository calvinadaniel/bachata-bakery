# Design System Document: High-End Latin Artisanal Editorial

## 1. Overview & Creative North Star
### The Creative North Star: "The Modern Dulce Studio"
This design system moves away from the "generic bakery" template to create a digital experience that feels as curated and handcrafted as a Dominican cake. We are blending the high-energy, vibrant punch of Brooklyn’s Fan-Fan Doughnuts with the indulgent, midnight-craving mood of Back Door Donuts. 

The aesthetic is **Artisanal Heat**. We achieve this through a "High-End Editorial" lens: using intentional asymmetry, generous white space (breathing room), and a sophisticated layering of surfaces. By avoiding rigid grids and standard borders, we invite the user into a space that feels warm, personal, and "made with love."

---

## 2. Colors: Tonal Depth & Vibrancy
Our palette is a sophisticated dance between "Creamy Warmth" and "Sultry Latin Nights."

### The "No-Line" Rule
**Designers are strictly prohibited from using 1px solid borders to define sections.** 
In this system, boundaries are created through color. A section change must be signaled by a transition from `surface` (#fcf6ed) to `surface-container-low` (#f6f0e6) or a bold splash of `primary` (#b70049). This creates a seamless, premium flow that mimics high-end print magazines.

### Surface Hierarchy & Nesting
Treat the UI as a series of physical layers. 
- **Base Layer:** `surface` (#fcf6ed)
- **Nested Content:** Use `surface-container` tiers (Lowest to Highest) to create depth. For example, a "Featured Pastry" card should use `surface-container-lowest` (#ffffff) to appear naturally lifted against a `surface-container` background.

### The "Glass & Gradient" Rule
To add soul to the interface:
- **Signature Gradients:** For primary CTAs and hero headers, use a subtle linear gradient from `primary` (#b70049) to `primary-container` (#ff7290). This provides a "glow" that flat hex codes cannot replicate.
- **Glassmorphism:** For floating navigation bars or snackbars, use `surface` at 80% opacity with a `20px` backdrop-blur. This keeps the vibrant food photography visible even beneath the UI.

---

## 3. Typography: The Expressive Voice
We pair a bold, high-character display font with a clean, modern geometric sans to balance personality with legibility.

- **Display & Headlines (Epilogue):** This is our "Latin Heart." Use `display-lg` and `headline-lg` for product names and emotive storytelling. The bold weight conveys the "indulgent" feel of Back Door Donuts.
- **Body & Titles (Plus Jakarta Sans):** This is our "Modern Precision." Use `body-lg` for descriptions. It provides a clean, breathable contrast to the heavy headlines.
- **Hierarchy as Identity:** Always lead with large-scale typography. Overlap `display-sm` text slightly over food photography to create an editorial, "stamped" effect.

---

## 4. Elevation & Depth: Tonal Layering
We do not use structural lines. We use physics and light.

- **The Layering Principle:** Instead of a shadow, place a `surface-container-highest` element on top of a `surface` background. The slight shift in "creaminess" creates a sophisticated distinction.
- **Ambient Shadows:** When a card must float (e.g., a checkout modal), use an ultra-diffused shadow: `box-shadow: 0 20px 40px rgba(49, 46, 41, 0.06);`. The shadow color is a tint of `on-surface` (#312e29), making it look like a natural shadow on a wooden bakery counter rather than a digital drop-shadow.
- **The "Ghost Border" Fallback:** If accessibility requires a border, use `outline-variant` (#b1ada5) at **15% opacity**. It should be felt, not seen.

---

## 5. Components: The Artisanal Kit

### Buttons (The "Confection" Style)
*   **Primary:** Solid `primary` (#b70049) with `on-primary` text. Use `radius-full` (9999px) for a playful, pill-shaped look.
*   **Secondary:** `surface-container-highest` with a `primary` text label. No border.
*   **Tertiary:** Text-only in `secondary` (#ab2d00) with a custom "flourish" underline on hover.

### Cards & Food Modules
*   **Rule:** **No dividers.** Separate product name, price, and description using the Spacing Scale (8px, 16px, 24px).
*   **Visuals:** Every card must feature high-saturation food photography. Use `radius-lg` (2rem) for product images to maintain the "soft and indulgent" theme.

### Interactive "Flavor" Chips
*   Use `tertiary-container` (#c59eff) for flavor tags (e.g., "Guava," "Dulce de Leche"). These should feel like small, colorful candies sprinkled across the page.

### Input Fields
*   Filled style using `surface-container-high`. When focused, transition the background to `surface-container-lowest` and add a 2px "Ghost Border" using `primary`.

---

## 6. Do's and Don'ts

### Do:
*   **Do** use asymmetrical layouts. Place a large image on the left and a "floating" text block that partially overlaps the image on the right.
*   **Do** use the `primary-fixed` (#ff7290) color for promotional banners to grab attention without the "harshness" of a standard red.
*   **Do** lean into the "roundedness scale." Every corner should feel soft and approachable (`radius-md` or higher).

### Don't:
*   **Don't** use pure black (#000000) for text. Use `on-surface` (#312e29) to keep the warmth of the cream background.
*   **Don't** use standard "Material Design" shadows. If it looks like a default shadow, it’s too heavy.
*   **Don't** clutter the screen. If you have five pastries to show, show them in a large, sweeping horizontal carousel rather than a cramped 3-column grid.