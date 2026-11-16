document.addEventListener('DOMContentLoaded', () => {
  initNav();
  initLazyLoad();
  initHeroVideo();
  initGalleryVideos();
  initContactLinks();
  document.querySelectorAll('.carousel').forEach(initCarousel);
});

/** Build mailto without leaving a scrapeable address in HTML. */
function contactMailto(opts) {
  const addr = ['info', 'bachatabakery.com'].join('@');
  const parts = [];
  if (opts && opts.subject) parts.push('subject=' + encodeURIComponent(opts.subject));
  if (opts && opts.body) parts.push('body=' + encodeURIComponent(opts.body));
  return 'mailto:' + addr + (parts.length ? '?' + parts.join('&') : '');
}

function initContactLinks() {
  document.querySelectorAll('[data-contact]').forEach((el) => {
    el.addEventListener('click', (e) => {
      e.preventDefault();
      window.location.href = contactMailto({
        subject: el.getAttribute('data-contact-subject') || undefined,
        body: el.getAttribute('data-contact-body') || undefined,
      });
    });
  });
}

window.BachataContact = { mailto: contactMailto };

/** iOS Safari/Chrome often paints the first frame but never starts muted autoplay. */
function initHeroVideo() {
  const video = document.querySelector('.hero-bg-video');
  if (!video) return;
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    video.removeAttribute('autoplay');
    video.pause();
    return;
  }

  const tryPlay = () => {
    video.muted = true;
    video.defaultMuted = true;
    video.setAttribute('muted', '');
    video.playsInline = true;
    video.setAttribute('playsinline', '');
    video.setAttribute('webkit-playsinline', '');
    const p = video.play();
    if (p && typeof p.catch === 'function') p.catch(() => {});
  };

  tryPlay();
  video.addEventListener('loadeddata', tryPlay, { once: true });
  video.addEventListener('canplay', tryPlay, { once: true });
  window.addEventListener('pageshow', tryPlay);
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') tryPlay();
  });
  const unlock = () => {
    tryPlay();
    window.removeEventListener('touchstart', unlock);
    window.removeEventListener('click', unlock);
  };
  window.addEventListener('touchstart', unlock, { once: true, passive: true });
  window.addEventListener('click', unlock, { once: true });
}

function initGalleryVideos() {
  const videos = document.querySelectorAll('.gallery-item video');
  if (!videos.length) return;
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    videos.forEach((v) => { v.removeAttribute('autoplay'); v.pause(); v.controls = true; });
    return;
  }

  const tryPlay = (video) => {
    video.muted = true;
    video.defaultMuted = true;
    video.setAttribute('muted', '');
    video.playsInline = true;
    video.setAttribute('playsinline', '');
    video.setAttribute('webkit-playsinline', '');
    const p = video.play();
    if (p && typeof p.catch === 'function') p.catch(() => {});
  };

  videos.forEach((video) => {
    tryPlay(video);
    video.addEventListener('loadeddata', () => tryPlay(video), { once: true });
    video.addEventListener('canplay', () => tryPlay(video), { once: true });
  });
  window.addEventListener('pageshow', () => videos.forEach(tryPlay));
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') videos.forEach(tryPlay);
  });
  const unlock = () => videos.forEach(tryPlay);
  window.addEventListener('touchstart', unlock, { once: true, passive: true });
  window.addEventListener('click', unlock, { once: true });
}

function initNav() {
  const nav = document.querySelector('.nav');
  if (!nav) return;
  const hamburger = nav.querySelector('.nav-hamburger');
  const panel = document.getElementById('nav-mobile');
  if (!hamburger || !panel) return;

  let overlay = document.getElementById('nav-overlay');
  if (!overlay) {
    overlay = document.createElement('div');
    overlay.id = 'nav-overlay';
    overlay.className = 'nav-overlay';
    overlay.setAttribute('aria-hidden', 'true');
  }

  let closeBtn = panel.querySelector('.nav-mobile-close');
  if (!panel.querySelector('.nav-mobile-top')) {
    const top = document.createElement('div');
    top.className = 'nav-mobile-top';
    const label = document.createElement('p');
    label.className = 'nav-mobile-label';
    label.textContent = 'Menu';
    closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'nav-mobile-close';
    closeBtn.setAttribute('aria-label', 'Close menu');
    closeBtn.innerHTML = '&times;';
    top.appendChild(label);
    top.appendChild(closeBtn);
    panel.insertBefore(top, panel.firstChild);
  }

  panel.setAttribute('role', 'dialog');
  panel.setAttribute('aria-modal', 'true');
  panel.setAttribute('aria-label', 'Site menu');
  panel.setAttribute('aria-hidden', 'true');
  hamburger.setAttribute('aria-controls', 'nav-mobile');
  hamburger.setAttribute('aria-label', 'Open menu');

  // Keep fixed overlay/panel outside ScrollSmoother's transformed wrapper.
  document.body.appendChild(overlay);
  document.body.appendChild(panel);

  const focusableSelector = 'a[href], button:not([disabled])';
  let lastFocus = null;

  function setPaused(paused) {
    const smoother = window.ScrollSmoother && window.ScrollSmoother.get && window.ScrollSmoother.get();
    if (smoother && typeof smoother.paused === 'function') smoother.paused(paused);
  }

  function openNav() {
    lastFocus = document.activeElement;
    nav.classList.add('open');
    document.body.classList.add('nav-open');
    hamburger.setAttribute('aria-expanded', 'true');
    hamburger.setAttribute('aria-label', 'Close menu');
    panel.setAttribute('aria-hidden', 'false');
    overlay.setAttribute('aria-hidden', 'false');
    setPaused(true);
    const focusTarget = closeBtn || panel.querySelector(focusableSelector);
    if (focusTarget) focusTarget.focus();
  }

  function closeNav() {
    if (!document.body.classList.contains('nav-open')) return;
    nav.classList.remove('open');
    document.body.classList.remove('nav-open');
    hamburger.setAttribute('aria-expanded', 'false');
    hamburger.setAttribute('aria-label', 'Open menu');
    panel.setAttribute('aria-hidden', 'true');
    overlay.setAttribute('aria-hidden', 'true');
    setPaused(false);
    if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus();
    else hamburger.focus();
  }

  function toggleNav() {
    if (document.body.classList.contains('nav-open')) closeNav();
    else openNav();
  }

  hamburger.addEventListener('click', (e) => {
    e.stopPropagation();
    toggleNav();
  });
  overlay.addEventListener('click', closeNav);
  if (closeBtn) closeBtn.addEventListener('click', closeNav);
  panel.querySelectorAll('a').forEach((link) => {
    link.addEventListener('click', closeNav);
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeNav();
    if (e.key !== 'Tab' || !document.body.classList.contains('nav-open')) return;
    const focusables = [...panel.querySelectorAll(focusableSelector)].filter(
      (el) => !el.hasAttribute('disabled') && el.getAttribute('aria-hidden') !== 'true'
    );
    if (!focusables.length) return;
    const first = focusables[0];
    const last = focusables[focusables.length - 1];
    if (e.shiftKey && document.activeElement === first) {
      e.preventDefault();
      last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
      e.preventDefault();
      first.focus();
    }
  });

  window.addEventListener('resize', () => {
    if (window.matchMedia('(min-width: 861px)').matches) closeNav();
  });
}

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

function initCarousel(el) {
  const track   = el.querySelector('.carousel-track');
  const items   = el.querySelectorAll('.carousel-item');
  const prevBtn = el.querySelector('.carousel-prev');
  const nextBtn = el.querySelector('.carousel-next');
  const dotsEl  = el.querySelector('.carousel-dots');
  const total   = items.length;
  let current   = 0;
  let startX    = 0;

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
    if (window.gsap) {
      gsap.to(track, { xPercent: -current * 100, duration: 0.45, ease: 'power2.inOut' });
    } else {
      track.style.transform = `translateX(-${current * 100}%)`;
    }
    el.querySelectorAll('.carousel-dot').forEach((d, i) =>
      d.classList.toggle('active', i === current)
    );
  }

  if (prevBtn) prevBtn.addEventListener('click', () => goTo(current - 1));
  if (nextBtn) nextBtn.addEventListener('click', () => goTo(current + 1));

  el.setAttribute('tabindex', '0');
  el.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowLeft')  goTo(current - 1);
    if (e.key === 'ArrowRight') goTo(current + 1);
  });

  track.addEventListener('touchstart', (e) => { startX = e.touches[0].clientX; }, { passive: true });
  track.addEventListener('touchend',   (e) => {
    const diff = startX - e.changedTouches[0].clientX;
    if (Math.abs(diff) > 50) goTo(current + (diff > 0 ? 1 : -1));
  });
}
