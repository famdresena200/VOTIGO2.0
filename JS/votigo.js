(() => {
  const toast = document.querySelector('[data-toast]');
  function showToast(type, title, message) {
    if (!toast) return;
    toast.classList.remove('ok', 'err', 'show');
    toast.classList.add(type === 'ok' ? 'ok' : 'err');
    const titleEl = toast.querySelector('[data-toast-title]');
    const msgEl = toast.querySelector('[data-toast-message]');
    if (titleEl) titleEl.textContent = title || '';
    if (msgEl) msgEl.textContent = message || '';
    toast.classList.add('show');
    window.clearTimeout(window.__toastTimer);
    window.__toastTimer = window.setTimeout(() => toast.classList.remove('show'), 3500);
  }

  // auto-toast from data attributes
  const el = document.querySelector('[data-flash-type]');
  if (el) {
    showToast(el.getAttribute('data-flash-type'), el.getAttribute('data-flash-title'), el.getAttribute('data-flash-message'));
  }

  // loading state for submit buttons
  document.addEventListener('submit', (e) => {
    const form = e.target;
    const btn = form.querySelector('button[type="submit"][data-loading]');
    if (!btn) return;
    btn.disabled = true;
    const old = btn.textContent;
    btn.setAttribute('data-old', old);
    btn.textContent = btn.getAttribute('data-loading') || '...';
  });

  // Real-time form validation
  document.addEventListener('input', (e) => {
    const input = e.target;
    if (!(input instanceof HTMLInputElement || input instanceof HTMLSelectElement)) return;
    
    // Email validation
    if (input.type === 'email') {
      const isValid = !input.value || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input.value);
      input.style.borderColor = isValid ? '' : 'rgba(255, 96, 96, 0.45)';
      input.style.boxShadow = isValid ? '' : '0 0 0 3px rgba(255, 96, 96, 0.12)';
    }
    
    // Password validation (minimum length)
    if (input.name === 'new_password' || input.name === 'password') {
      const isValid = input.value.length === 0 || input.value.length >= 8;
      input.style.borderColor = isValid ? '' : 'rgba(255, 96, 96, 0.45)';
      input.style.boxShadow = isValid ? '' : '0 0 0 3px rgba(255, 96, 96, 0.12)';
    }
    
    // Confirm password match
    if (input.name === 'confirm_password') {
      const passwordField = form?.querySelector('input[name="new_password"]');
      if (passwordField) {
        const match = input.value === passwordField.value && input.value.length > 0;
        input.style.borderColor = match || input.value === '' ? '' : 'rgba(255, 96, 96, 0.45)';
        input.style.boxShadow = match || input.value === '' ? '' : '0 0 0 3px rgba(255, 96, 96, 0.12)';
      }
    }
  });

  // Add focus styles manually for better compatibility
  document.addEventListener('focus', (e) => {
    const input = e.target;
    if (input instanceof (HTMLInputElement || HTMLSelectElement || HTMLTextAreaElement)) {
      input.style.borderColor = 'rgba(26, 169, 184, 0.35)';
      input.style.boxShadow = '0 0 0 3px rgba(26, 169, 184, 0.12)';
    }
  }, true);

  document.addEventListener('blur', (e) => {
    const input = e.target;
    if (input instanceof (HTMLInputElement || HTMLSelectElement || HTMLTextAreaElement)) {
      input.style.borderColor = '';
      input.style.boxShadow = '';
    }
  }, true);

  // Restore scroll position after reload / navigation
  // Goal: keep the user at same visual position after POST/GET reload.
  const scrollKey = `votigo_scroll:${location.pathname}${location.search}`;
  function saveScroll() {
    try {
      sessionStorage.setItem(scrollKey, String(window.scrollY || 0));
      sessionStorage.setItem(`${scrollKey}:ts`, String(Date.now()));
    } catch (_) {}
  }
  function restoreScroll() {
    try {
      const raw = sessionStorage.getItem(scrollKey);
      if (!raw) return;
      const ts = parseInt(sessionStorage.getItem(`${scrollKey}:ts`) || '0', 10);
      // Ignore very old values (tab left open etc.)
      if (ts && Date.now() - ts > 3 * 60 * 1000) {
        sessionStorage.removeItem(scrollKey);
        sessionStorage.removeItem(`${scrollKey}:ts`);
        return;
      }
      const y = Math.max(0, parseInt(raw, 10) || 0);
      sessionStorage.removeItem(scrollKey);
      sessionStorage.removeItem(`${scrollKey}:ts`);
      requestAnimationFrame(() => window.scrollTo({ top: y, left: 0, behavior: 'auto' }));
    } catch (_) {}
  }

  // Save on submits and before leaving.
  document.addEventListener('submit', saveScroll, { capture: true });
  window.addEventListener('beforeunload', saveScroll);
  // Restore on load (but if there's a hash, browser anchor wins).
  if (!location.hash) {
    window.addEventListener('load', restoreScroll, { once: true });
  }

  // Theme toggle (dark/light)
  const themeBtn = document.getElementById('themeBtn');
  function applyTheme(isDark) {
    document.documentElement.classList.toggle('dark-theme', isDark);
    if (themeBtn) {
      themeBtn.textContent = isDark ? '☀️' : '🌙';
      themeBtn.setAttribute('aria-pressed', isDark ? 'true' : 'false');
    }
  }
  try {
    const stored = localStorage.getItem('votigo_theme');
    const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    applyTheme(stored === 'dark' || (stored === null && prefersDark));
  } catch (e) {}
  if (themeBtn) {
    themeBtn.addEventListener('click', () => {
      const isDark = !document.documentElement.classList.contains('dark-theme');
      applyTheme(isDark);
      try { localStorage.setItem('votigo_theme', isDark ? 'dark' : 'light'); } catch (_) {}
    });
  }

  // Détecter le zoom du navigateur et enlever le scroll si zoom > 90%
  function checkZoom() {
    const zoomLevel = window.devicePixelRatio * 100;
    const footer = document.querySelector('.sidebar-footer');
    if (!footer) return;
    
    if (zoomLevel > 90) {
      footer.style.overflowY = 'visible';
      footer.style.maxHeight = 'none';
    } else {
      footer.style.overflowY = 'auto';
      footer.style.maxHeight = '25vh';
    }
  }

  // Vérifier au chargement et à chaque resize
  checkZoom();
  window.addEventListener('resize', checkZoom);
  window.addEventListener('load', checkZoom);
})();

