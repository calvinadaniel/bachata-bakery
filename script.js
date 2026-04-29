/* ============================================================
   BACHATA BAKERY — script.js
   Vanilla JS · No frameworks · DOM-ready safe
   ============================================================ */

document.addEventListener('DOMContentLoaded', () => {

  /* ── OFF-CANVAS MENU ─────────────────────────────────── */
  const hamburger     = document.getElementById('hamburger');
  const offcanvas     = document.getElementById('offcanvas');
  const overlay       = document.getElementById('overlay');
  const offcanvasClose = document.getElementById('offcanvas-close');
  const offcanvasLinks = offcanvas.querySelectorAll('a');

  const openMenu = () => {
    hamburger.classList.add('open');
    hamburger.setAttribute('aria-expanded', 'true');
    offcanvas.classList.add('open');
    offcanvas.setAttribute('aria-hidden', 'false');
    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
  };

  const closeMenu = () => {
    hamburger.classList.remove('open');
    hamburger.setAttribute('aria-expanded', 'false');
    offcanvas.classList.remove('open');
    offcanvas.setAttribute('aria-hidden', 'true');
    overlay.classList.remove('active');
    document.body.style.overflow = '';
  };

  hamburger.addEventListener('click', openMenu);
  offcanvasClose.addEventListener('click', closeMenu);
  overlay.addEventListener('click', closeMenu);

  // Close on Escape key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && offcanvas.classList.contains('open')) closeMenu();
  });

  // Close when a link is clicked
  offcanvasLinks.forEach(link => link.addEventListener('click', closeMenu));


  /* ── STICKY HEADER ───────────────────────────────────── */
  const header = document.getElementById('site-header');

  const handleScroll = () => {
    if (window.scrollY > 40) {
      header.classList.add('scrolled');
    } else {
      header.classList.remove('scrolled');
    }
  };

  window.addEventListener('scroll', handleScroll, { passive: true });
  handleScroll(); // run on load


  /* ── SCROLL REVEAL (IntersectionObserver) ────────────── */
  const revealEls = document.querySelectorAll('.reveal');

  const revealObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('in-view');
        revealObserver.unobserve(entry.target);
      }
    });
  }, {
    threshold: 0.12,
    rootMargin: '0px 0px -40px 0px'
  });

  revealEls.forEach(el => revealObserver.observe(el));


  /* ── FAQ ACCORDION ───────────────────────────────────── */
  const faqItems = document.querySelectorAll('.faq-item');

  faqItems.forEach(item => {
    const btn = item.querySelector('.faq-q');
    const answer = item.querySelector('.faq-a');

    btn.addEventListener('click', () => {
      const isOpen = btn.getAttribute('aria-expanded') === 'true';

      // Close all open items
      faqItems.forEach(other => {
        const otherBtn = other.querySelector('.faq-q');
        const otherAns = other.querySelector('.faq-a');
        otherBtn.setAttribute('aria-expanded', 'false');
        otherAns.classList.remove('open');
      });

      // Toggle clicked item
      if (!isOpen) {
        btn.setAttribute('aria-expanded', 'true');
        answer.classList.add('open');
      }
    });
  });


  /* ── SMOOTH SCROLL for anchor links ─────────────────── */
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', (e) => {
      const target = document.querySelector(anchor.getAttribute('href'));
      if (!target) return;
      e.preventDefault();
      const offset = parseInt(getComputedStyle(document.documentElement)
        .getPropertyValue('--header-h')) || 70;
      const top = target.getBoundingClientRect().top + window.scrollY - offset;
      window.scrollTo({ top, behavior: 'smooth' });
    });
  });


  /* ── LAZY BACKGROUND IMAGES ──────────────────────────── */
  const bgObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const el = entry.target;
        const bg = el.dataset.bg;
        if (bg) el.style.backgroundImage = `url('${bg}')`;
        bgObserver.unobserve(el);
      }
    });
  }, { rootMargin: '200px 0px' });

  document.querySelectorAll('[data-bg]').forEach(el => bgObserver.observe(el));


  /* ── RIBBON PAUSE ON HOVER ───────────────────────────── */
  const ribbonTrack = document.querySelector('.ribbon-track');
  if (ribbonTrack) {
    ribbonTrack.addEventListener('mouseenter', () => {
      ribbonTrack.style.animationPlayState = 'paused';
    });
    ribbonTrack.addEventListener('mouseleave', () => {
      ribbonTrack.style.animationPlayState = 'running';
    });
  }

});
