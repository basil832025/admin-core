<script>
(() => {
  // Add small visible hotkey badges to Filament action buttons.
  const ensureHotkeyBadges = () => {
    const els = document.querySelectorAll('[data-hotkey][data-hotkey-label]');
    els.forEach((el) => {
      if (el.querySelector?.('.cc-hotkey-badge')) return;

      const label = (el.getAttribute('data-hotkey-label') || '').trim();
      if (!label) return;

      const badge = document.createElement('span');
      badge.className = 'cc-hotkey-badge';
      badge.textContent = label;

      // Put badge at the end of button/link content.
      el.appendChild(badge);
    });
  };

  // Inject minimal styles once.
  const ensureHotkeyStyles = () => {
    if (document.getElementById('cc-hotkey-style')) return;
    const style = document.createElement('style');
    style.id = 'cc-hotkey-style';
    style.textContent = `
      [data-hotkey][data-hotkey-label] { position: relative; }
      .cc-hotkey-badge {
        margin-left: 8px;
        padding: 2px 6px;
        border-radius: 8px;
        font-size: 11px;
        line-height: 16px;
        font-weight: 600;
        letter-spacing: .02em;
        border: 1px solid rgba(148, 163, 184, .55);
        background: rgba(241, 245, 249, .7);
        color: rgba(51, 65, 85, .9);
        transition: background-color .12s ease, border-color .12s ease, color .12s ease;
        user-select: none;
      }
      [data-hotkey][data-hotkey-label]:hover .cc-hotkey-badge {
        background: rgba(250, 204, 21, .18);
        border-color: rgba(250, 204, 21, .7);
        color: rgba(113, 63, 18, 1);
      }
    `;
    document.head.appendChild(style);
  };

  const isEditable = (el) => {
    if (!el) return false;
    const tag = (el.tagName || '').toLowerCase();
    if (tag === 'input' || tag === 'textarea' || tag === 'select') return true;
    return !!el.isContentEditable;
  };

  const isVisible = (el) => {
    if (!el) return false;
    if (el.hasAttribute('hidden')) return false;
    const style = window.getComputedStyle(el);
    if (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0') return false;
    return el.offsetParent !== null || style.position === 'fixed';
  };

  const isCallcenterOrdersList = () => {
    const path = window.location?.pathname || '';
    if (!path.startsWith('/admin/callcenter/orders')) return false;
    if (path.includes('/edit') || path.includes('/create')) return false;
    // Accept both /admin/callcenter/orders and /admin/callcenter/orders/
    return true;
  };

  const isFiltersPanelVisible = () => {
    const panel = document.querySelector('.fi-ta-filters');
    return isVisible(panel);
  };

  const setFiltersOpen = (open) => {
    const ctn = document.querySelector('.fi-ta-filters-above-content-ctn');
    if (!ctn) return false;
    const x = ctn.__x;
    if (!x || !x.$data) return false;
    try {
      x.$data.areFiltersOpen = !!open;
      return true;
    } catch (_) {
      return false;
    }
  };

  const clickFiltersToggle = () => {
    const ctn = document.querySelector('.fi-ta-filters-above-content-ctn');
    if (!ctn) return false;

    // In AboveContentCollapsible layout Filament renders a trigger span
    // with x-on:click="areFiltersOpen = ! areFiltersOpen".
    const toggle = ctn.querySelector('[x-on\\:click*="areFiltersOpen"]')
      || ctn.querySelector('[x-on\\:click*="areFilters"]')
      || ctn.querySelector('span.ms-auto');

    if (!toggle) return false;

    toggle.click();
    return true;
  };

  const autoOpenFilters = () => {
    if (!isCallcenterOrdersList()) return;

    let attempts = 0;
    const maxAttempts = 25; // ~5s

    const tryOpen = () => {
      attempts += 1;

      if (isFiltersPanelVisible()) return;

      // Prefer toggling via Filament trigger (works across Alpine versions)
      if (clickFiltersToggle()) return;

      // Fallback: direct Alpine state mutation
      if (setFiltersOpen(true)) return;

      if (attempts < maxAttempts) {
        window.setTimeout(tryOpen, 200);
      }
    };

    window.setTimeout(tryOpen, 250);
  };

  const onKeydown = (e) => {
    // Only for Callcenter orders pages
    const path = window.location?.pathname || '';
    if (!path.startsWith('/admin/callcenter/orders')) return;

    if (isEditable(document.activeElement)) return;
    if (e.defaultPrevented) return;

    const isList = !path.includes('/edit') && !path.includes('/create');
    const isEdit = /\/admin\/callcenter\/orders\/\d+\/edit$/.test(path);
    const isCreate = path.endsWith('/admin/callcenter/orders/create');

    // Alt+N (new order) - list only
    if (isList && e.altKey && !e.ctrlKey && !e.metaKey && !e.shiftKey && e.code === 'KeyN') {
      e.preventDefault();

      const btn = document.querySelector('[data-hotkey="cc-new-order"]');
      if (btn && typeof btn.click === 'function') {
        btn.click();
        return;
      }

      window.location.href = '/admin/callcenter/orders/create';
      return;
    }

    // Alt+M (menu) - create/edit
    if ((isEdit || isCreate) && e.altKey && !e.ctrlKey && !e.metaKey && !e.shiftKey && e.code === 'KeyM') {
      const btn = document.querySelector('[data-hotkey="cc-menu"]');
      if (!btn) return;
      e.preventDefault();
      btn.click();
      return;
    }

    // Alt+A (promotions) - create/edit
    if ((isEdit || isCreate) && e.altKey && !e.ctrlKey && !e.metaKey && !e.shiftKey && e.code === 'KeyA') {
      const btn = document.querySelector('[data-hotkey="cc-promos"]');
      if (!btn) return;
      e.preventDefault();
      btn.click();
      return;
    }

    // Alt+S (save/create) - create/edit
    if ((isEdit || isCreate) && e.altKey && !e.ctrlKey && !e.metaKey && !e.shiftKey && e.code === 'KeyS') {
      e.preventDefault();
      const btn = document.querySelector('[data-hotkey="cc-save"]');
      if (btn && typeof btn.click === 'function') {
        btn.click();
        return;
      }

      const form = document.querySelector('form#form');
      if (form && typeof form.requestSubmit === 'function') {
        form.requestSubmit();
      }
      return;
    }

    // Alt+P (print client + logistic) - edit only
    if (isEdit && e.altKey && !e.ctrlKey && !e.metaKey && !e.shiftKey && e.code === 'KeyP') {
      const btn = document.querySelector('[data-hotkey="cc-print-client-logistic"]');
      if (!btn) return;
      e.preventDefault();
      btn.click();
      return;
    }

    // Alt+R (print kitchen) - edit only
    if (isEdit && e.altKey && !e.ctrlKey && !e.metaKey && !e.shiftKey && e.code === 'KeyR') {
      const btn = document.querySelector('[data-hotkey="cc-print-kitchen"]');
      if (!btn) return;
      e.preventDefault();
      btn.click();
      return;
    }
  };

  window.addEventListener('keydown', onKeydown, { passive: false });

  ensureHotkeyStyles();
  ensureHotkeyBadges();

  // Auto-open filters on initial load and on SPA navigations.
  autoOpenFilters();
  window.addEventListener('livewire:navigated', ensureHotkeyBadges);
  window.addEventListener('filament:navigated', ensureHotkeyBadges);
  window.addEventListener('livewire:navigated', autoOpenFilters);
  window.addEventListener('filament:navigated', autoOpenFilters);

  // When clicking "Reset" in filters, close filters panel.
  document.addEventListener('click', (e) => {
    if (!isCallcenterOrdersList()) return;
    const resetEl = e.target?.closest?.('[wire\\:click="resetTableFiltersForm"], [wire\\:click*="resetTableFilters"]');
    if (!resetEl) return;

    // Let Livewire reset the form first, then close.
    window.setTimeout(() => {
      let attempts = 0;
      const maxAttempts = 12; // ~2.4s

      const tryClose = () => {
        attempts += 1;

        // If already hidden, stop.
        if (!isFiltersPanelVisible()) return;

        // Try direct Alpine state mutation first.
        const didSet = setFiltersOpen(false);

        // Also try toggle click (DOM might have been re-rendered).
        clickFiltersToggle();

        if (attempts < maxAttempts) {
          window.setTimeout(tryClose, didSet ? 120 : 200);
        }
      };

      window.setTimeout(tryClose, 200);
    }, 350);
  }, true);
})();
</script>
