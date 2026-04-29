document.addEventListener('DOMContentLoaded', () => {
  initNav();
  initLazyLoad();
  document.querySelectorAll('.carousel').forEach(initCarousel);
});

function initNav() {
  const nav = document.querySelector('.nav');
  if (!nav) return;
  const hamburger = nav.querySelector('.nav-hamburger');
  if (!hamburger) return;
  hamburger.addEventListener('click', () => {
    const open = nav.classList.toggle('open');
    hamburger.setAttribute('aria-expanded', open);
  });
  document.addEventListener('click', (e) => {
    if (!nav.contains(e.target)) nav.classList.remove('open');
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
    track.style.transform = `translateX(-${current * 100}%)`;
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
