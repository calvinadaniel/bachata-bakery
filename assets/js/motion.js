/**
 * Site motion: ScrollSmoother, marquees, hero rise, reveals, hovers.
 * Requires gsap + ScrollTrigger + ScrollSmoother.
 */
(function () {
  if (!window.gsap || !window.ScrollTrigger) return;
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  document.documentElement.classList.add('js-motion');
  gsap.registerPlugin(ScrollTrigger);
  if (window.ScrollSmoother) gsap.registerPlugin(ScrollSmoother);

  setupSmooth();
  setupHero();
  setupMarquees();
  setupReveals();
  setupHovers();

  function setupSmooth() {
    if (!window.ScrollSmoother) return;

    var wrapper = document.createElement('div');
    wrapper.id = 'smooth-wrapper';
    var content = document.createElement('div');
    content.id = 'smooth-content';

    var kids = Array.prototype.slice.call(document.body.childNodes);
    kids.forEach(function (node) {
      if (node.nodeType === 1 && node.tagName === 'SCRIPT') return;
      content.appendChild(node);
    });
    wrapper.appendChild(content);
    document.body.insertBefore(wrapper, document.body.firstChild);

    ScrollSmoother.create({
      wrapper: '#smooth-wrapper',
      content: '#smooth-content',
      smooth: 1.15,
      effects: true,
      smoothTouch: 0.1,
      normalizeScroll: true,
    });

    var nav = document.querySelector('.nav');
    if (nav) {
      ScrollTrigger.create({
        trigger: nav,
        start: 'top top',
        end: 'max',
        pin: true,
        pinSpacing: false,
      });
    }

    // Native #hash jumps break under ScrollSmoother + normalizeScroll.
    // Route same-page anchors (and load/hashchange) through smoother.scrollTo.
    setupHashScroll();
  }

  function navOffsetPx() {
    var raw = getComputedStyle(document.documentElement).getPropertyValue('--nav-h');
    var n = parseFloat(raw);
    return (isFinite(n) ? n : 80) + 12;
  }

  function scrollToHash(hash, smooth) {
    if (!hash || hash === '#') return false;
    var id;
    try {
      id = decodeURIComponent(hash.slice(1));
    } catch (err) {
      return false;
    }
    if (!id) return false;
    var el = document.getElementById(id);
    if (!el) return false;

    var smoother = window.ScrollSmoother && ScrollSmoother.get();
    var offset = navOffsetPx();
    if (smoother) {
      smoother.scrollTo(el, !!smooth, 'top ' + Math.round(offset) + 'px');
      return true;
    }
    el.scrollIntoView({ behavior: smooth ? 'smooth' : 'auto', block: 'start' });
    return true;
  }

  function samePageHash(anchor) {
    var href = anchor.getAttribute('href');
    if (!href) return null;
    if (/^(mailto:|tel:|javascript:)/i.test(href)) return null;
    var url;
    try {
      url = new URL(anchor.href, window.location.href);
    } catch (err) {
      return null;
    }
    if (url.origin !== window.location.origin) return null;
    if (url.pathname !== window.location.pathname) return null;
    // Query changes (e.g. ?class=…#book) need a real navigation; load handler scrolls.
    if (url.search !== window.location.search) return null;
    if (!url.hash || url.hash === '#') return null;
    return url.hash;
  }

  function setupHashScroll() {
    document.addEventListener('click', function (e) {
      if (e.defaultPrevented) return;
      if (e.button !== 0) return;
      if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
      var anchor = e.target.closest && e.target.closest('a[href]');
      if (!anchor) return;
      var hash = samePageHash(anchor);
      if (!hash) return;
      e.preventDefault();
      if (window.location.hash !== hash) {
        history.pushState(null, '', hash);
      }
      scrollToHash(hash, true);
    });

    window.addEventListener('hashchange', function () {
      scrollToHash(window.location.hash, true);
    });

    window.addEventListener('popstate', function () {
      if (window.location.hash) scrollToHash(window.location.hash, false);
    });

    if (window.location.hash) {
      // Wait a frame so pin/layout settle, then jump without smooth (deep links).
      requestAnimationFrame(function () {
        scrollToHash(window.location.hash, false);
        // Jotform iframe can grow after embed handler loads — nudge once more.
        window.setTimeout(function () {
          scrollToHash(window.location.hash, false);
        }, 400);
      });
    }
  }

  function setupHero() {
    var heroBits = document.querySelectorAll('.hero-content > *, .preorder-strip');
    if (heroBits.length) {
      gsap.from(heroBits, {
        opacity: 0,
        y: 22,
        duration: 0.8,
        stagger: 0.1,
        ease: 'power2.out',
        delay: 0.05,
      });
    }

    var productHero = document.querySelectorAll('.products-hero-inner > *');
    if (productHero.length) {
      gsap.from(productHero, {
        opacity: 0,
        y: 20,
        duration: 0.75,
        stagger: 0.08,
        ease: 'power2.out',
      });
    }

    var heroVideo = document.querySelector('.hero-bg-video');
    if (heroVideo) {
      heroVideo.setAttribute('data-speed', '0.85');
      if (window.ScrollSmoother && ScrollSmoother.get()) {
        ScrollSmoother.get().effects(heroVideo, { speed: 0.85 });
      } else {
        gsap.to(heroVideo, {
          yPercent: 12,
          ease: 'none',
          scrollTrigger: {
            trigger: '.hero',
            start: 'top top',
            end: 'bottom top',
            scrub: true,
          },
        });
      }
    }
  }

  function setupMarquees() {
    document.querySelectorAll('.announce-track, .hero-marquee-track').forEach(function (track) {
      var duration = track.classList.contains('announce-track') ? 28 : 32;
      var tween = gsap.to(track, {
        xPercent: -50,
        duration: duration,
        ease: 'none',
        repeat: -1,
      });
      var pauseRoot = track.closest('.announce, .hero-marquee') || track.parentElement;
      if (!pauseRoot) return;
      pauseRoot.addEventListener('mouseenter', function () { tween.pause(); });
      pauseRoot.addEventListener('mouseleave', function () { tween.play(); });
      pauseRoot.addEventListener('focusin', function () { tween.pause(); });
      pauseRoot.addEventListener('focusout', function () { tween.play(); });
    });
  }

  function reveal(els, opts) {
    var list = gsap.utils.toArray(els).filter(Boolean);
    if (!list.length) return;
    gsap.from(list, {
      opacity: 0,
      y: 28,
      duration: 0.7,
      ease: 'power2.out',
      stagger: opts && opts.stagger != null ? opts.stagger : 0,
      scrollTrigger: {
        trigger: (opts && opts.trigger) || list[0],
        start: 'top 88%',
        once: true,
      },
    });
  }

  function setupReveals() {
    [
      ['.bakes-grid .bake', '.bakes-grid'],
      ['.gallery-grid .gallery-item', '.gallery-grid'],
      ['.values-row .value-item', '.values-row'],
      ['.products-tier-grid > *', '.products-tier-grid'],
      ['.class-grid .class-panel', '.class-grid'],
      ['.section .accordion-item', '.section .container'],
    ].forEach(function (pair) {
      var kids = document.querySelectorAll(pair[0]);
      if (!kids.length) return;
      reveal(kids, { stagger: 0.1, trigger: document.querySelector(pair[1]) || kids[0] });
    });

    gsap.utils
      .toArray(
        [
          '.section-head',
          '.products-section-head',
          '.manifesto-text',
          '.manifesto-lead',
          '.manifesto-facts',
          '.manifesto-cta',
          '.about .container > *',
          '.closing .container > *',
          '.bakes-more',
          '.gallery-more',
          '.products-split',
          '.products-flavor-with-media',
          '.products-section-cta',
          '.schedule-box',
          '.callout',
          '.class-form-wrap',
        ].join(',')
      )
      .forEach(function (el) {
        reveal(el, { trigger: el });
      });
  }

  function setupHovers() {
    document.querySelectorAll('.bake').forEach(function (bake) {
      var img = bake.querySelector('.bake-media img');
      if (!img) return;
      bake.addEventListener('mouseenter', function () {
        gsap.to(img, { scale: 1.05, duration: 0.55, ease: 'power2.out' });
      });
      bake.addEventListener('mouseleave', function () {
        gsap.to(img, { scale: 1, duration: 0.55, ease: 'power2.out' });
      });
    });
  }
})();
