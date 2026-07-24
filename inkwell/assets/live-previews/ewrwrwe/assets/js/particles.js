// Inkwell — ambient particle background.
// A quiet network of drifting "ink droplets" behind the whole site,
// consistent with the nib/ink-bloom brand: soft dots connected by
// thin threads that react gently to the pointer. Respects
// prefers-reduced-motion and pauses when the tab is hidden.
(function () {
  const canvas = document.getElementById('inkParticles');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  const reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  let width, height, dpr;
  let particles = [];
  let pointer = { x: -9999, y: -9999 };
  let running = true;
  let rafId = null;

  function themeColor() {
    const isLight = document.documentElement.getAttribute('data-theme') === 'light';
    return isLight ? { dot: '74,99,240', line: '74,99,240' } : { dot: '139,108,249', line: '91,124,250' };
  }

  function resize() {
    dpr = Math.min(window.devicePixelRatio || 1, 2);
    width = window.innerWidth;
    height = window.innerHeight;
    canvas.width = width * dpr;
    canvas.height = height * dpr;
    canvas.style.width = width + 'px';
    canvas.style.height = height + 'px';
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

    const density = Math.max(28, Math.min(70, Math.round((width * height) / 26000)));
    particles = new Array(density).fill(0).map(() => ({
      x: Math.random() * width,
      y: Math.random() * height,
      vx: (Math.random() - 0.5) * 0.25,
      vy: (Math.random() - 0.5) * 0.25,
      r: Math.random() * 1.6 + 0.6,
    }));
  }

  function step() {
    if (!running) { rafId = null; return; }
    const { dot, line } = themeColor();
    ctx.clearRect(0, 0, width, height);

    for (const p of particles) {
      p.x += p.vx;
      p.y += p.vy;
      if (p.x < 0 || p.x > width) p.vx *= -1;
      if (p.y < 0 || p.y > height) p.vy *= -1;

      const dx = pointer.x - p.x, dy = pointer.y - p.y;
      const dist2 = dx * dx + dy * dy;
      if (dist2 < 14400) { // 120px radius: gentle repel
        const f = (1 - dist2 / 14400) * 0.02;
        p.vx -= dx * f * 0.02;
        p.vy -= dy * f * 0.02;
      }
      p.vx *= 0.995; p.vy *= 0.995;
    }

    for (let i = 0; i < particles.length; i++) {
      for (let j = i + 1; j < particles.length; j++) {
        const a = particles[i], b = particles[j];
        const dx = a.x - b.x, dy = a.y - b.y;
        const d2 = dx * dx + dy * dy;
        if (d2 < 16000) {
          const alpha = (1 - d2 / 16000) * 0.16;
          ctx.strokeStyle = `rgba(${line},${alpha})`;
          ctx.lineWidth = 1;
          ctx.beginPath();
          ctx.moveTo(a.x, a.y);
          ctx.lineTo(b.x, b.y);
          ctx.stroke();
        }
      }
    }
    for (const p of particles) {
      ctx.beginPath();
      ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
      ctx.fillStyle = `rgba(${dot},0.55)`;
      ctx.fill();
    }

    rafId = requestAnimationFrame(step);
  }

  function start() { if (!rafId && running) rafId = requestAnimationFrame(step); }

  window.addEventListener('resize', resize, { passive: true });
  window.addEventListener('pointermove', (e) => { pointer.x = e.clientX; pointer.y = e.clientY; }, { passive: true });
  window.addEventListener('pointerleave', () => { pointer.x = -9999; pointer.y = -9999; });
  document.addEventListener('visibilitychange', () => {
    running = !document.hidden && !reduceMotion;
    if (running) start(); else if (rafId) { cancelAnimationFrame(rafId); rafId = null; }
  });

  resize();
  if (!reduceMotion) start();
  else { // draw one static frame so it isn't jarring
    running = true; step(); running = false;
  }
})();
