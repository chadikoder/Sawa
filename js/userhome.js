// Press feedback for nav/sidebar/buttons on all devices
document.querySelectorAll('nav a, .sidebar-item, .sidebar-user, .bottom-nav-item, .btn, .action-btn').forEach(el => {
  el.addEventListener('mousedown', () => el.classList.add('pressed'));
  el.addEventListener('touchstart', () => el.classList.add('pressed'), {passive: true});
  el.addEventListener('mouseup', () => el.classList.remove('pressed'));
  el.addEventListener('mouseleave', () => el.classList.remove('pressed'));
  el.addEventListener('touchend', () => el.classList.remove('pressed'), {passive: true});
  el.addEventListener('touchcancel', () => el.classList.remove('pressed'), {passive: true});
});

/* ── Sub-section back-bar (X / Twitter pattern) ──
   Sections that carry data-back="<parent-id>" are sub-pages. We inject a
   sticky back bar at the very top of each sub-section once, on demand.
   The bar shows the section title (data-title) and a ← back arrow that
   returns to the parent section. We push a navigation history stack so
   sequential sub-page taps still go back through them properly. */
const _SECTION_HISTORY = [];

function ensureBackBar(section) {
  if (!section || !section.dataset.back || section.querySelector(':scope > .section-back-bar')) return;
  const bar = document.createElement('div');
  bar.className = 'section-back-bar';
  bar.innerHTML =
    '<button type="button" class="section-back-btn" aria-label="Back">'
    + '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>'
    + '</button>'
    + '<span class="section-back-title">' + (section.dataset.title || '') + '</span>';
  bar.querySelector('.section-back-btn').addEventListener('click', () => {
    // Pop the history stack if it has anything, otherwise fall back to data-back.
    const previous = _SECTION_HISTORY.pop() || section.dataset.back;
    switchSection(previous, { fromBack: true });
  });
  section.prepend(bar);
}

document.querySelectorAll('.section[data-back]').forEach(ensureBackBar);

/* ── Section switching ── */
function switchSection(sectionId, opts) {
  // If the campaign-detail modal is open, close it first — otherwise the
  // section change happens behind the modal and looks like a broken click.
  const cm = document.getElementById('campaign-modal');
  if (cm?.classList.contains('show')) {
    cm.classList.remove('show');
    cm.setAttribute('aria-hidden', 'true');
  }

  // History bookkeeping: when navigating FORWARD into a sub-page from
  // somewhere else, push the current section so back can return. Skipped
  // when this call came from the back button itself (opts.fromBack).
  const currentActive = document.querySelector('.section.active');
  if (currentActive && currentActive.id !== sectionId && !(opts && opts.fromBack)) {
    const targetEl = document.getElementById(sectionId);
    if (targetEl && targetEl.dataset.back) _SECTION_HISTORY.push(currentActive.id);
  }

  document.querySelectorAll('.sidebar-item, .bottom-nav-item').forEach(i => {
    i.classList.remove('active');
    i.removeAttribute('aria-current');
  });
  document.querySelectorAll('.section').forEach(s => s.classList.remove('active', 'section-entering', 'campaign-entering'));

  const section = document.getElementById(sectionId);
  if (section) {
    section.classList.add('active', 'section-entering');
    if (sectionId === 'campaign-new') section.classList.add('campaign-entering');
    window.setTimeout(() => section.classList.remove('section-entering', 'campaign-entering'), 420);
  }

  // Full-screen sub-page mode: when the active section has data-back, the
  // dashboard chrome (top header + bottom bar) hides via body.in-subpage so
  // the section takes the full viewport. Top-level sections show chrome again.
  if (section && section.dataset.back) {
    document.body.classList.add('in-subpage');
  } else {
    document.body.classList.remove('in-subpage');
  }

  document.querySelectorAll(`[data-section="${sectionId}"]`).forEach(el => {
    el.classList.add('active');
    if (el.matches('.sidebar-item, .bottom-nav-item')) el.setAttribute('aria-current', 'page');
  });

  // Sync top-nav (site-nav-link uses data-jump + .is-active, not data-section + .active)
  document.querySelectorAll('[data-jump]').forEach(el => {
    const isActive = el.dataset.jump === sectionId;
    el.classList.toggle('is-active', isActive);
    if (isActive) el.setAttribute('aria-current', 'page');
    else el.removeAttribute('aria-current');
  });

  updateBottomNavGlass();
  closeSidebar();
}

document.querySelectorAll('.sidebar-item[data-section]').forEach(item => {
  item.addEventListener('click', () => switchSection(item.dataset.section));
});

document.querySelectorAll('.bottom-nav-item[data-section]').forEach(item => {
  item.addEventListener('click', () => switchSection(item.dataset.section));
});

// Catch-all for any other in-page jump button (e.g. dashboard "How Sawa works"
// cards, profile quick actions). Skips elements already handled above and
// anything that has a real href so links still work normally.
document.addEventListener('click', (e) => {
  const el = e.target.closest('[data-section]');
  if (!el) return;
  if (el.matches('.sidebar-item, .bottom-nav-item')) return;
  if (el.tagName === 'A' && el.getAttribute('href')) return;
  e.preventDefault();
  switchSection(el.dataset.section);
});


/* ── Sidebar open / close (mobile) ── */
/* The page behind the drawer must not scroll while it is open. Setting
   overflow on <body> alone is not enough here: <html> is the scrolling element
   on this page, so the document kept scrolling underneath the drawer. Both are
   locked, and the scroll position is restored on close so reopening the drawer
   does not jump the page back to the top. */
let _scrollLockY = 0;

function closeSidebar() {
  document.querySelector('.sidebar')?.classList.remove('open');
  document.querySelector('.mobile-overlay')?.classList.remove('show');
  document.documentElement.style.overflow = '';
  document.body.style.overflow = '';
  document.body.style.position = '';
  document.body.style.top = '';
  document.body.style.width = '';
  window.scrollTo(0, _scrollLockY);
}

function openSidebar() {
  _scrollLockY = window.scrollY || document.documentElement.scrollTop || 0;
  document.querySelector('.sidebar')?.classList.add('open');
  document.querySelector('.mobile-overlay')?.classList.add('show');
  document.documentElement.style.overflow = 'hidden';
  document.body.style.overflow = 'hidden';
  // position:fixed is what actually stops iOS Safari rubber-banding the page.
  document.body.style.position = 'fixed';
  document.body.style.top = (-_scrollLockY) + 'px';
  document.body.style.width = '100%';
}

/* ── Guest unified header / mobile drawer ── */
(function() {
    const burger    = document.getElementById('site-burger');
    const drawer    = document.getElementById('guest-drawer');
    const backdrop  = document.getElementById('guest-drawer-backdrop');
    const closeBtn  = document.getElementById('guest-drawer-close');

    function openDrawer() {
        if (!drawer) return;
        drawer.hidden = false;
        backdrop.hidden = false;
        requestAnimationFrame(() => {
            drawer.classList.add('is-open');
            backdrop.classList.add('is-open');
        });
        burger?.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
    }
    function closeDrawer() {
        if (!drawer) return;
        drawer.classList.remove('is-open');
        backdrop.classList.remove('is-open');
        burger?.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
        setTimeout(() => {
            if (!drawer.classList.contains('is-open')) {
                drawer.hidden = true;
                backdrop.hidden = true;
            }
        }, 280);
    }

    burger?.addEventListener('click', openDrawer);
    closeBtn?.addEventListener('click', closeDrawer);
    backdrop?.addEventListener('click', closeDrawer);
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && drawer && !drawer.hidden) closeDrawer();
    });

    // Jump-to-section links inside the header + drawer (optionally pre-filter by category)
    document.querySelectorAll('[data-jump]').forEach(el => {
        el.addEventListener('click', (e) => {
            e.preventDefault();
            const target = el.dataset.jump;
            const sectionBtn = document.querySelector(`[data-section="${target}"]`);
            if (sectionBtn) sectionBtn.click();
            const cat = el.dataset.category;
            if (cat) {
                const chip = document.querySelector(`.cat-chip[data-category="${cat}"]`);
                if (chip) chip.click();
            }
            // Highlight active link in header + drawer
            document.querySelectorAll('[data-jump]').forEach(other => {
                other.classList.toggle('is-active', other.dataset.jump === target);
            });
            closeDrawer();
        });
    });
})();

document.querySelector('.mobile-overlay')?.addEventListener('click', closeSidebar);

/* Clicking anywhere outside the drawer closes it. The overlay above covers the
   mobile case, but the overlay is hidden from 769px up — so a drawer left open
   while the window was narrow stayed open and unclosable after a resize to
   desktop, with the page still scroll-locked behind it. */
document.addEventListener('click', (e) => {
  const sidebar = document.querySelector('.sidebar');
  if (!sidebar || !sidebar.classList.contains('open')) return;
  if (e.target.closest('.sidebar')) return;              // inside the drawer
  if (e.target.closest('[data-open-sidebar], .site-header-user, .site-header-burger')) return;
  closeSidebar();
});

/* Crossing into desktop turns the drawer back into a permanent column, so any
   leftover open state (and its scroll lock) has to be cleared or the page
   stays frozen with no visible way out. */
window.addEventListener('resize', () => {
  if (window.innerWidth > 768 && document.querySelector('.sidebar')?.classList.contains('open')) {
    closeSidebar();
  }
});

/* Sidebar user card → opens profile section (and closes drawer on mobile) */
document.querySelector('.sidebar-user')?.addEventListener('click', () => {
  switchSection('profile');
});

const MOBILE_BREAKPOINT = 768;
const isMobile = () => window.innerWidth <= MOBILE_BREAKPOINT;

/* The top-left profile widget opens the sidebar drawer (which holds
   My Campaigns + Logout + secondary nav) at every viewport width. */
document.getElementById('nav-user-btn')?.addEventListener('click', () => {
  if (document.body.classList.contains('is-auth')) {
    openSidebar();
  } else {
    switchSection('dashboard');
  }
});

/* Removed: promptSignIn / closeAuthPrompt / GUEST_RESTRICTED + their listeners.
   The only caller was the bottom-nav guest-restriction handler, but the
   bottom-nav is auth-only — guests never see it. All dead code. */

/* ── Discover filters: bottom-sheet on mobile ──
   The trigger button (#discover-filter-trigger) is rendered mobile-only via
   CSS. Tapping opens the sheet (.discover-filters slides up). Backdrop tap,
   close button, or Escape closes. Desktop never invokes any of this — the
   filter panel is inline as before. */
(function () {
  const sheet    = document.getElementById('discover-filters-sheet');
  const backdrop = document.getElementById('discover-filters-backdrop');
  const trigger  = document.getElementById('discover-filter-trigger');
  const closeBtn = document.getElementById('discover-filter-close');
  if (!sheet || !backdrop || !trigger) return;

  function open() {
    sheet.classList.add('is-open');
    backdrop.classList.add('is-open');
    backdrop.hidden = false;
    document.body.classList.add('filters-open');
    trigger.setAttribute('aria-expanded', 'true');
  }
  function close() {
    sheet.classList.remove('is-open');
    backdrop.classList.remove('is-open');
    backdrop.hidden = true;
    document.body.classList.remove('filters-open');
    trigger.setAttribute('aria-expanded', 'false');
  }

  trigger.addEventListener('click', () => {
    if (sheet.classList.contains('is-open')) close(); else open();
  });
  backdrop.addEventListener('click', close);
  closeBtn?.addEventListener('click', close);
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && sheet.classList.contains('is-open')) close();
  });
})();

document.addEventListener('keydown', e => {
  if (e.key === 'Escape') closeSidebar();
});

/* ── Sidebar collapse (desktop toggle) ── */
/* The .sidebar-toggle collapse handler was removed along with its button: the
   6rem icon rail it switched to flashed on every click and served no purpose —
   on desktop the sidebar is a permanent column, on mobile it is a drawer that
   slides away entirely. */

/* ── Discover filter / search / sort engine ── */
(function() {
  const grid          = document.getElementById('discover-grid');
  const empty         = document.getElementById('discover-empty');
  const searchInput   = document.getElementById('discover-search');
  const searchClear   = document.getElementById('discover-search-clear');
  const sortSelect    = document.getElementById('discover-sort');
  const catRow        = document.querySelector('.discover-cat-row');
  const advToggle     = document.getElementById('filter-toggle');
  const advPanel      = document.getElementById('filter-advanced');
  const advReset      = document.getElementById('filter-reset');
  const advCount      = document.getElementById('filter-active-count');
  const urgencyPills  = document.querySelectorAll('.adv-pills[data-filter="urgency"] .adv-pill');
  const locationSel   = document.getElementById('discover-location-select');
  const activePillBox = document.getElementById('discover-active-filters');
  const resultCount   = document.getElementById('discover-result-count');

  if (!grid) return;

  const state = {
    category: 'All',
    urgency:  'All',
    location: 'all',
    search:   '',
    sort:     'newest',
  };

  const allCards = Array.from(grid.querySelectorAll('.camp-card'));

  function applyFilters() {
    const term = state.search.trim().toLowerCase();
    const matches = allCards.filter(card => {
      if (state.category !== 'All' && card.dataset.category !== state.category) return false;
      if (state.urgency === 'Urgent' && card.dataset.urgent !== 'true') return false;
      if (state.location !== 'all' && card.dataset.location !== state.location) return false;
      if (term) {
        const hay = (card.dataset.campTitle + ' ' + card.dataset.category + ' ' +
                     (card.querySelector('.camp-card-desc')?.textContent || '')).toLowerCase();
        if (!hay.includes(term)) return false;
      }
      return true;
    });

    const pctOf = c => {
      const r = +c.dataset.raised || 0;
      const g = +c.dataset.goal || 1;
      return r / g;
    };
    matches.sort((a, b) => {
      switch (state.sort) {
        case 'most-funded': return (+b.dataset.raised || 0) - (+a.dataset.raised || 0);
        case 'closest':     return pctOf(b) - pctOf(a);
        case 'urgent':      return (b.dataset.urgent === 'true') - (a.dataset.urgent === 'true');
        default:            return (+b.dataset.campId || 0) - (+a.dataset.campId || 0);
      }
    });

    allCards.forEach(c => { c.style.display = 'none'; });
    matches.forEach(c => { c.style.display = ''; grid.appendChild(c); });

    if (empty) empty.hidden = matches.length > 0;
    if (resultCount) {
      resultCount.textContent = matches.length === 1
        ? '1 campaign'
        : matches.length + ' campaigns';
    }
    renderActivePills();
    updateChipCounts();
    updateAdvCount();
  }

  function updateChipCounts() {
    document.querySelectorAll('.cat-chip-count').forEach(span => {
      const cat = span.dataset.countFor;
      const n = cat === 'All'
        ? allCards.length
        : allCards.filter(c => c.dataset.category === cat).length;
      span.textContent = n;
      // Hide the "0" badge — ambiguous and adds noise per UX review.
      span.hidden = (n === 0);
    });
  }

  function updateAdvCount() {
    // Counts the number of non-default refinements (urgency / location / category).
    // Drives both the inline "More filters" badge AND the mobile trigger badge,
    // so the user sees how many filters are applied without opening the sheet.
    let n = 0;
    if (state.category !== 'All') n++;
    if (state.urgency !== 'All')  n++;
    if (state.location !== 'all') n++;
    const setBadge = (el) => {
      if (!el) return;
      if (n > 0) { el.hidden = false; el.textContent = n; }
      else       { el.hidden = true;  }
    };
    setBadge(advCount);
    setBadge(document.getElementById('discover-filter-trigger-count'));
  }

  function renderActivePills() {
    if (!activePillBox) return;
    const pills = [];
    if (state.category !== 'All') pills.push({ key: 'category', label: state.category });
    if (state.urgency  !== 'All') pills.push({ key: 'urgency',  label: 'Urgent only' });
    if (state.location !== 'all') pills.push({ key: 'location', label: state.location.replace('_lb', ' (South)') });
    if (state.search.trim())      pills.push({ key: 'search',   label: `"${state.search.trim()}"` });

    activePillBox.innerHTML = '';
    if (pills.length === 0) { activePillBox.hidden = true; return; }
    activePillBox.hidden = false;
    pills.forEach(p => {
      const pill = document.createElement('button');
      pill.type = 'button';
      pill.className = 'active-filter-pill';
      pill.textContent = p.label;
      const close = document.createElement('span');
      close.setAttribute('aria-hidden', 'true');
      close.textContent = '\u00d7';
      pill.appendChild(close);
      pill.setAttribute('aria-label', `Remove filter ${p.label}`);
      pill.addEventListener('click', () => clearFilter(p.key));
      activePillBox.appendChild(pill);
    });
    const clearAll = document.createElement('button');
    clearAll.type = 'button';
    clearAll.className = 'active-filter-clearall';
    clearAll.textContent = 'Clear all';
    clearAll.addEventListener('click', resetAll);
    activePillBox.appendChild(clearAll);
  }

  function clearFilter(key) {
    if (key === 'category') {
      state.category = 'All';
      document.querySelectorAll('.cat-chip').forEach(c => c.classList.toggle('active', c.dataset.category === 'All'));
    } else if (key === 'urgency') {
      state.urgency = 'All';
      urgencyPills.forEach(p => p.classList.toggle('active', p.dataset.urgency === 'All'));
    } else if (key === 'location') {
      state.location = 'all';
      if (locationSel) locationSel.value = 'all';
    } else if (key === 'search') {
      state.search = '';
      if (searchInput) searchInput.value = '';
      if (searchClear) searchClear.hidden = true;
    }
    applyFilters();
  }

  function resetAll() {
    state.category = 'All';
    state.urgency  = 'All';
    state.location = 'all';
    state.search   = '';
    state.sort     = 'newest';
    document.querySelectorAll('.cat-chip').forEach(c => c.classList.toggle('active', c.dataset.category === 'All'));
    urgencyPills.forEach(p => p.classList.toggle('active', p.dataset.urgency === 'All'));
    if (locationSel) locationSel.value = 'all';
    if (sortSelect)  sortSelect.value  = 'newest';
    if (searchInput) searchInput.value = '';
    if (searchClear) searchClear.hidden = true;
    applyFilters();
  }

  // Category chips
  document.querySelectorAll('.cat-chip[data-category]').forEach(chip => {
    chip.addEventListener('click', () => {
      document.querySelectorAll('.cat-chip').forEach(c => c.classList.remove('active'));
      chip.classList.add('active');
      state.category = chip.dataset.category;
      applyFilters();
    });
  });

  // Urgency pills
  urgencyPills.forEach(pill => {
    pill.addEventListener('click', () => {
      urgencyPills.forEach(p => p.classList.remove('active'));
      pill.classList.add('active');
      state.urgency = pill.dataset.urgency;
      applyFilters();
    });
  });

  // Location + sort
  locationSel?.addEventListener('change', () => { state.location = locationSel.value; applyFilters(); });
  sortSelect?.addEventListener('change', () => { state.sort = sortSelect.value; applyFilters(); });

  // Search input
  let searchTimer;
  searchInput?.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
      state.search = searchInput.value;
      if (searchClear) searchClear.hidden = !searchInput.value;
      applyFilters();
    }, 120);
  });
  searchClear?.addEventListener('click', () => clearFilter('search'));

  // Advanced toggle
  advToggle?.addEventListener('click', () => {
    const isHidden = advPanel.hasAttribute('hidden');
    if (isHidden) {
      advPanel.removeAttribute('hidden');
      advToggle.setAttribute('aria-expanded', 'true');
      advToggle.classList.add('active');
    } else {
      advPanel.setAttribute('hidden', '');
      advToggle.setAttribute('aria-expanded', 'false');
      advToggle.classList.remove('active');
    }
  });

  // Reset
  advReset?.addEventListener('click', resetAll);

  // First paint
  applyFilters();
})();

/* ── Donate modal ── */
const donateModal = document.getElementById('donate-modal');

function closeDonateModal() {
  donateModal.classList.remove('show');
  setTimeout(() => {
    document.getElementById('modal-step-1').removeAttribute('hidden');
    document.getElementById('modal-step-payment').setAttribute('hidden', '');
    document.getElementById('modal-step-2').setAttribute('hidden', '');
    document.getElementById('modal-step-thanks').setAttribute('hidden', '');
    const amt = document.getElementById('modal-amount');
    if (amt) { amt.value = ''; amt.style.borderColor = ''; }
    const summary = document.getElementById('modal-summary-amount');
    if (summary) summary.textContent = '$0';
    const paymentHidden = document.getElementById('modal-payment-method');
    if (paymentHidden) paymentHidden.value = 'whish';
    const whish = document.querySelector('[name="payment_method_choice"][value="whish"]');
    if (whish) whish.checked = true;
    updateCoverFeeLabel();
    updatePaymentMethodDetails();
    document.querySelectorAll('.modal-presets .preset-btn').forEach(b => b.classList.remove('selected'));
  }, 300);
}

function showModalStep(stepId) {
  ['modal-step-1', 'modal-step-payment', 'modal-step-2', 'modal-step-thanks'].forEach(id => {
    const el = document.getElementById(id);
    if (el) {
      if (id === stepId) el.removeAttribute('hidden');
      else el.setAttribute('hidden', '');
    }
  });
}

function openDonateModal(title, campId, raised, goal) {
  if (!donateModal) return;

  document.getElementById('modal-step-1').removeAttribute('hidden');
  document.getElementById('modal-step-payment').setAttribute('hidden', '');
  document.getElementById('modal-step-2').setAttribute('hidden', '');
  document.getElementById('modal-step-thanks').setAttribute('hidden', '');

  document.getElementById('modal-camp-title').textContent = title;
  document.getElementById('modal-camp-id-hidden').value   = campId;
  updateModalDonationSummary();

  const goalInfo = document.getElementById('modal-goal-info');
  if (raised !== undefined && goal !== undefined && goal > 0) {
    const pct       = Math.min(100, Math.round((raised / goal) * 100));
    const remaining = Math.max(0, goal - raised);
    document.getElementById('modal-progress-fill').style.width = pct + '%';
    document.getElementById('modal-raised').textContent        = '$' + raised.toLocaleString();
    document.getElementById('modal-goal').textContent          = '$' + goal.toLocaleString();
    document.getElementById('modal-remaining').textContent     = '$' + remaining.toLocaleString() + ' remaining';
    if (goalInfo) goalInfo.style.display = 'block';
  } else {
    if (goalInfo) goalInfo.style.display = 'none';
  }

  donateModal.classList.add('show');
  // Defer focus past the display:none→flex switch + animation frame so it actually lands.
  requestAnimationFrame(() => document.getElementById('modal-amount').focus());
}

document.querySelectorAll('.modal-close, #modal-cancel').forEach(btn => {
  btn.addEventListener('click', closeDonateModal);
});
window.addEventListener('click', (e) => {
  if (e.target === donateModal) closeDonateModal();
});
window.addEventListener('keydown', (e) => {
  if (e.key === 'Escape' && donateModal?.classList.contains('show')) closeDonateModal();
});

/* Modal step navigation
   Step 1 (amount) -> Step 2 (payment method) -> Step 3 (review) -> PHP/payment provider.
*/

function getSelectedPaymentMethod() {
  return document.querySelector('[name="payment_method_choice"]:checked')?.value || 'whish';
}

function getPaymentMethodLabel(method) {
  return {
    whish: 'Whish Money',
    hosted_checkout: 'Visa / Mastercard',
    wallet: 'Sawa Wallet',
  }[method] || 'Hosted checkout';
}

function updateModalDonationSummary() {
  const amount = parseFloat(document.getElementById('modal-amount')?.value || '0');
  const summary = document.getElementById('modal-summary-amount');
  if (summary) summary.textContent = Number.isFinite(amount) && amount > 0
    ? '$' + amount.toLocaleString()
    : '$0';
}

// Step 1 → Step 2 (Payment)
document.getElementById('modal-review-btn')?.addEventListener('click', () => {
  const amt = document.getElementById('modal-amount');
  const val = parseFloat(amt?.value);
  if (!amt || isNaN(val) || val < 1) {
    if (amt) { amt.style.borderColor = '#ef4444'; amt.focus(); }
    return;
  }
  amt.style.borderColor = '';
  updateModalDonationSummary();
  showModalStep('modal-step-payment');
});

// Step 2 → back to Step 1
document.getElementById('payment-back-btn')?.addEventListener('click', () => {
  showModalStep('modal-step-1');
});

const METHOD_ICON_SVG = {
  whish:           'W',
  hosted_checkout: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2.5"/><line x1="2" y1="10" x2="22" y2="10"/></svg>',
  wallet:          '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>',
};
const METHOD_ICON_CLASS = {
  whish:           'modal-confirm-icon--whish',
  hosted_checkout: 'modal-confirm-icon--card',
  wallet:          'modal-confirm-icon--wallet',
};
// Frontend preview only — PHP recalculates fees server-side at checkout.
const METHOD_FEE_RATE = {
  whish:           0.10,
  hosted_checkout: 0.10,
  wallet:          0.05,
};

const METHOD_DETAILS = {
  whish: {
    title: 'Whish Money checkout',
    rows: [
      ['How it works', 'You will be redirected to Whish to confirm the payment.'],
      ['You need', 'A Whish-registered Lebanese mobile number.'],
      ['Speed', 'Payment confirmation usually returns within a few minutes.'],
      ['Provider fee', 'Any Whish transfer fee is shown before checkout.'],
    ],
  },
  hosted_checkout: {
    title: 'Visa / Mastercard hosted checkout',
    rows: [
      ['Accepted', 'Visa, Mastercard, and supported provider cards.'],
      ['Security', '3-D Secure may ask for a one-time code from your bank.'],
      ['Where', 'You pay on the provider page, then return to Sawa.'],
      ['Provider fee', 'Card processing fees are shown by the payment provider.'],
    ],
  },
  wallet: {
    title: 'Sawa Wallet donation',
    rows: [
      ['Source', 'Uses your Sawa wallet balance only.'],
      ['Speed', 'Instant donation with no external redirect.'],
      ['Top up', 'Add funds from the Wallet section before donating.'],
      ['Why cheaper', 'Members pay a 5% Sawa fee on wallet donations.'],
    ],
  },
};

function getMethodFeeRate(method) {
  return METHOD_FEE_RATE[method] ?? 0.10;
}

function fmtMoney(n) {
  return '$' + (Math.round(n * 100) / 100).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

// Live-update the "Cover the X% fee" checkbox label when method changes
function updateCoverFeeLabel() {
  const el = document.getElementById('cover-fee-rate-label');
  if (!el) return;
  el.textContent = Math.round(getMethodFeeRate(getSelectedPaymentMethod()) * 100) + '%';
}

function updatePaymentMethodDetails() {
  const panel = document.getElementById('payment-method-details');
  if (!panel) return;
  const method = getSelectedPaymentMethod();
  const data = METHOD_DETAILS[method] || METHOD_DETAILS.whish;
  panel.innerHTML = '<strong>' + data.title + '</strong>' +
    data.rows.map(([label, value]) =>
      '<span class="pm-detail-row"><span>' + label + '</span><small>' + value + '</small></span>'
    ).join('');
}

// Step 2 → Step 3 (Review)
document.getElementById('payment-next-btn')?.addEventListener('click', () => {
  const amt = parseFloat(document.getElementById('modal-amount').value);
  const method = getSelectedPaymentMethod();
  const paymentHidden = document.getElementById('modal-payment-method');
  if (paymentHidden) paymentHidden.value = method;

  const rate = getMethodFeeRate(method);
  const fee = amt * rate;
  const total = amt + fee;

  document.getElementById('review-amount-display').textContent   = fmtMoney(amt);
  document.getElementById('review-campaign-display').textContent = document.getElementById('modal-camp-title').textContent;
  document.getElementById('review-donation-line').textContent    = fmtMoney(amt);
  document.getElementById('review-fee-rate').textContent         = '(' + Math.round(rate * 100) + '%)';
  document.getElementById('review-fee-line').textContent         = '+' + fmtMoney(fee);
  document.getElementById('review-total-line').textContent       = fmtMoney(total);

  // Method chip
  const chipText = document.querySelector('#review-payment-display .modal-confirm-method-text');
  const chipIcon = document.querySelector('#review-payment-display .modal-confirm-method-icon');
  if (chipText) chipText.textContent = 'Paying with ' + getPaymentMethodLabel(method);
  if (chipIcon) {
    chipIcon.className = 'modal-confirm-method-icon ' + (
      method === 'whish' ? 'modal-confirm-method-icon--whish' :
      method === 'hosted_checkout' ? 'modal-confirm-method-icon--card' : ''
    );
    chipIcon.innerHTML = METHOD_ICON_SVG[method] || '';
  }

  // Large method icon
  const icon = document.getElementById('review-method-icon');
  if (icon) {
    icon.className = 'modal-confirm-icon ' + (METHOD_ICON_CLASS[method] || '');
    icon.innerHTML = METHOD_ICON_SVG[method] || '';
  }

  showModalStep('modal-step-2');
});

// Step 3 → back to Step 2
document.getElementById('review-back-btn')?.addEventListener('click', () => {
  showModalStep('modal-step-payment');
});

document.getElementById('donate-form')?.addEventListener('submit', () => {
  const btn = document.getElementById('confirm-donate-btn');
  if (btn) {
    btn.disabled = true;
    btn.textContent = 'Redirecting...';
  }
});

// Thank-you Close button
document.getElementById('thanks-close-btn')?.addEventListener('click', closeDonateModal);

document.querySelectorAll('[name="payment_method_choice"]').forEach(radio => {
  radio.addEventListener('change', () => {
    const paymentHidden = document.getElementById('modal-payment-method');
    if (paymentHidden) paymentHidden.value = getSelectedPaymentMethod();
    updateCoverFeeLabel();
    updatePaymentMethodDetails();
  });
});

// Initial label sync (defaults to whish = 10%)
updateCoverFeeLabel();
updatePaymentMethodDetails();

document.getElementById('modal-amount')?.addEventListener('input', updateModalDonationSummary);

/* ── Guest/auth mode preview ──
   PHP sets the body class to is-auth or is-guest server-side.
   Static HTML falls back to is-guest. For local testing only, ?auth=1 can
   preview the logged-in dashboard shell without changing PHP-owned truth. */
(function() {
    const params = new URLSearchParams(window.location.search);
    const role  = params.get('role');
    const knownRole = role === 'donor' || role === 'taker' || role === 'org';
    // ?role implies an authenticated session — roles only exist when logged in.
    if (params.get('auth') === '1' || knownRole) {
        document.body.classList.remove('is-guest');
        document.body.classList.add('is-auth');
    }
    if (knownRole) {
        document.body.classList.remove('role-donor', 'role-taker', 'role-org');
        document.body.classList.add('role-' + role);
    }
})();

/* ── Bottom nav taskbar indicator ── */
function initBottomNavGlass() {
  const nav = document.getElementById('bottom-nav');
  if (!nav) return;
  if (!nav.querySelector('.bottom-nav-glass-indicator')) {
    const indicator = document.createElement('span');
    indicator.className = 'bottom-nav-glass-indicator';
    indicator.setAttribute('aria-hidden', 'true');
    nav.prepend(indicator);
  }
  updateBottomNavGlass();
}

function updateBottomNavGlass() {
  const nav = document.getElementById('bottom-nav');
  const active = nav?.querySelector('.bottom-nav-item.active');
  if (!nav || !active) return;

  requestAnimationFrame(() => {
    const navBox = nav.getBoundingClientRect();
    const itemBox = active.getBoundingClientRect();
    if (!navBox.width || !itemBox.width) return;

    const indicatorWidth = active.classList.contains('bottom-nav-fab') ? 34 : 28;
    const x = itemBox.left - navBox.left + (itemBox.width / 2) - (indicatorWidth / 2);
    const y = Math.max(8, itemBox.top - navBox.top + 4);

    nav.style.setProperty('--bottom-glass-x', x + 'px');
    nav.style.setProperty('--bottom-glass-y', y + 'px');
    nav.style.setProperty('--bottom-glass-w', indicatorWidth + 'px');
    nav.style.setProperty('--bottom-glass-h', '3px');
    nav.classList.toggle('is-fab-active', active.classList.contains('bottom-nav-fab'));
    nav.classList.add('has-glass-indicator');
  });
}

initBottomNavGlass();
window.addEventListener('resize', updateBottomNavGlass);
window.addEventListener('orientationchange', () => window.setTimeout(updateBottomNavGlass, 120));

/* Local preview helper: ?section=campaign-new opens that section after auth/role classes apply. */
(function() {
  const target = new URLSearchParams(window.location.search).get('section');
  if (!target || !document.getElementById(target)) return;
  requestAnimationFrame(() => switchSection(target));
})();

/* ── Notifications dropdown (mock — PHP fills list later) ── */
(function() {
    const bellBtn = document.getElementById('dash-bell-btn');
    const panel   = document.getElementById('dash-notif-panel');
    const wrap    = bellBtn?.closest('.dash-bell-wrap');
    if (!bellBtn || !panel || !wrap) return;

    bellBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        const open = wrap.classList.toggle('open');
        bellBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    // "View all notifications" → close panel + switch to Activity & Bills.
    // The href="#" is just a fallback; we always handle it here.
    document.querySelector('.dash-notif-foot')?.addEventListener('click', (e) => {
        e.preventDefault();
        wrap.classList.remove('open');
        bellBtn.setAttribute('aria-expanded', 'false');
        switchSection('activity');
    });

    document.addEventListener('click', (e) => {
        if (!wrap.contains(e.target)) {
            wrap.classList.remove('open');
            bellBtn.setAttribute('aria-expanded', 'false');
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && wrap.classList.contains('open')) {
            wrap.classList.remove('open');
            bellBtn.setAttribute('aria-expanded', 'false');
            bellBtn.focus();
        }
    });

    document.getElementById('dash-notif-mark')?.addEventListener('click', (e) => {
        e.preventDefault();
        document.getElementById('dash-notif-mark-form')?.submit();
    });

    panel.querySelector('.dash-notif-foot')?.addEventListener('click', (e) => {
        e.preventDefault();
        wrap.classList.remove('open');
        bellBtn.setAttribute('aria-expanded', 'false');
        document.querySelector('[data-section="activity"]')?.click();
    });
})();

/* ── Activity & bills receipt preview (mock rows — PHP owns real data/PDF) ── */
(function() {
  const rows = document.querySelectorAll('.activity-ledger-row');
  const billId = document.getElementById('bill-id');
  const billDate = document.getElementById('bill-date');
  const billMethod = document.getElementById('bill-method');
  const billTotal = document.getElementById('bill-total');
  const billRef = document.getElementById('bill-provider-ref');
  const billRecipient = document.getElementById('bill-recipient');
  const downloadBtn = document.getElementById('bill-download-btn');
  const printBtn = document.getElementById('bill-print-btn');
  if (!rows.length || !billId || !billDate || !billMethod || !billTotal) return;

  function syncBill(row) {
    rows.forEach(item => item.classList.remove('active'));
    row.classList.add('active');
    billId.textContent = row.dataset.billId || 'SAWA-RECEIPT';
    billDate.textContent = row.dataset.billDate || 'Pending date';
    billMethod.textContent = row.dataset.billMethod || 'Payment method';
    billTotal.textContent = row.dataset.billTotal || '$0';
    if (billRef) billRef.textContent = row.dataset.billRef || 'Pending provider ref';
    if (billRecipient) billRecipient.textContent = row.dataset.billRecipient || row.querySelector('.activity-ledger-main small')?.textContent || 'Sawa record';
    if (downloadBtn) downloadBtn.dataset.billDownload = row.dataset.billId || '';
  }

  rows.forEach(row => row.addEventListener('click', () => syncBill(row)));

  /* Activity tabs: real filtering, not just visual toggle.
     Predicate is on the button's data-filter (set in HTML). Rows are matched
     against data-bill-id (the only ledger state currently exposed on the DOM):
       all      → every row
       paid     → bill_id !== 'PENDING'
       pending  → bill_id === 'PENDING'
     Empty-state message shows when no rows match. */
  document.querySelectorAll('.activity-filter-tabs button').forEach(button => {
    button.addEventListener('click', () => {
      const tabs = button.closest('.activity-filter-tabs');
      tabs?.querySelectorAll('button').forEach(item => item.classList.remove('active'));
      button.classList.add('active');

      const list   = document.getElementById('activity-ledger-list');
      const filter = button.dataset.filter || 'all';
      if (!list) return;

      let shown = 0;
      list.querySelectorAll('.activity-ledger-row').forEach(row => {
        const billId  = row.dataset.billId || '';
        const isPending = billId === 'PENDING';
        const match = filter === 'all'
          || (filter === 'paid'    && !isPending)
          || (filter === 'pending' && isPending);
        row.hidden = !match;
        if (match) shown++;
      });

      // Toggle a "no results" line below the rows.
      let emptyEl = list.querySelector('.activity-filter-empty');
      if (shown === 0 && !emptyEl) {
        emptyEl = document.createElement('p');
        emptyEl.className = 'activity-empty activity-filter-empty';
        emptyEl.textContent = filter === 'pending'
          ? 'No pending activity right now.'
          : 'Nothing in this view yet.';
        list.appendChild(emptyEl);
      } else if (shown > 0 && emptyEl) {
        emptyEl.remove();
      }
    });
  });

  downloadBtn?.addEventListener('click', () => {
    const id = downloadBtn.dataset.billDownload || billId.textContent;
    if (id && id !== 'PENDING') {
      window.location.href = '../php/receipts/download.php?id=' + encodeURIComponent(id);
    } else {
      showToast('Receipt not available yet.', true);
    }
  });

  printBtn?.addEventListener('click', () => window.print());
})();

/* ── Dashboard search bridge → forwards to the Discover filter engine ── */
(function() {
    const input = document.getElementById('dash-search-input');
    if (!input) return;
    input.addEventListener('input', (e) => {
        const val = e.target.value;
        if (val.length > 0) {
            const discoverBtn = document.querySelector('[data-section="discover"]');
            if (discoverBtn && !document.getElementById('discover').classList.contains('active')) {
                discoverBtn.click();
            }
        }
        const discSearch = document.getElementById('discover-search');
        if (discSearch) {
            discSearch.value = val;
            discSearch.dispatchEvent(new Event('input', { bubbles: true }));
        }
    });
})();

/* ── Count-up animation for stat tiles ──
   Parses the existing rendered text ($14,400 / 1,200+ / $250k+ / 48)
   and animates from 0 to that target when scrolled into view. */
(function() {
    function parseStat(raw) {
        const trimmed = raw.trim();
        const m = trimmed.match(/^([^\d]*)([\d,.]+)\s*([a-zA-Z]?)\s*(\+?)$/);
        if (!m) return null;
        const [, prefix, numStr, suffixLetter, plus] = m;
        const multiplier = suffixLetter.toLowerCase() === 'k' ? 1000 :
                           suffixLetter.toLowerCase() === 'm' ? 1000000 : 1;
        const value = parseFloat(numStr.replace(/,/g, '')) * multiplier;
        return { prefix, value, suffixLetter, plus, hasComma: numStr.includes(',') };
    }

    function format(n, parts) {
        let display;
        if (parts.suffixLetter) {
            const scaled = parts.suffixLetter.toLowerCase() === 'k' ? n / 1000 : n / 1000000;
            display = Math.round(scaled) + parts.suffixLetter;
        } else if (parts.hasComma || parts.value >= 1000) {
            display = Math.round(n).toLocaleString();
        } else {
            display = Math.round(n).toString();
        }
        return parts.prefix + display + parts.plus;
    }

    function animate(el) {
        const parts = parseStat(el.textContent);
        if (!parts) return;
        const start = performance.now();
        const duration = 1200;
        function tick(now) {
            const t = Math.min(1, (now - start) / duration);
            const eased = 1 - Math.pow(1 - t, 3);
            el.textContent = format(parts.value * eased, parts);
            if (t < 1) requestAnimationFrame(tick);
        }
        requestAnimationFrame(tick);
    }

    const targets = document.querySelectorAll('.stat-tile-value');
    if (!targets.length) return;

    if ('IntersectionObserver' in window) {
        const obs = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    animate(entry.target);
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.4 });
        targets.forEach(t => obs.observe(t));
    } else {
        targets.forEach(animate);
    }
})();

/* ── Apply data-image as background on card media + featured thumbs ──
   Lets PHP / future content swap the gradient illustrations for real photos
   without touching markup: just add data-image="/path/to/photo.jpg". */
document.querySelectorAll('[data-image]').forEach(el => {
  const src = el.dataset.image;
  if (src) el.style.backgroundImage = `url('${src}')`;
});

/* ── Campaign card v2 enhancement ──
   Injects: location badge, creator row, days-remaining pill, and progress chip.
   PHP can later set data-creator / data-days-left / data-verified on each card. */
function enhanceCampCards(root = document) {
  root.querySelectorAll('.camp-card').forEach(card => {
    if (card.dataset.v2Enhanced) return;
    card.dataset.v2Enhanced = '1';

    const creator  = card.dataset.creator || 'Verified organisation';
    const days     = card.dataset.daysLeft;
    const verified = card.dataset.verified !== 'false';
    const location = card.dataset.location || '';

    // Location badge — overlay on the media area
    const media = card.querySelector('.camp-card-media');
    if (media && location && !media.querySelector('.camp-location-badge')) {
      const loc = document.createElement('span');
      loc.className = 'camp-location-badge';
      loc.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>';
      const locText = document.createElement('span');
      locText.textContent = location.replace('_lb', ' (South)');
      loc.appendChild(locText);
      media.appendChild(loc);
    }

    // Creator row inserted right after the title
    const content = card.querySelector('.camp-card-content');
    const title   = card.querySelector('.camp-card-title');
    if (content && title && !content.querySelector('.camp-creator-row')) {
      const initials = creator.split(' ').map(p => p[0]).join('').slice(0, 2).toUpperCase();
      const row = document.createElement('div');
      row.className = 'camp-creator-row';
      const avatar = document.createElement('span');
      avatar.className = 'camp-creator-avatar';
      avatar.setAttribute('aria-hidden', 'true');
      avatar.textContent = initials || 'VO';
      const name = document.createElement('span');
      name.className = 'camp-creator-name';
      name.textContent = creator;
      row.append(avatar, name);
      if (verified) {
        const badge = document.createElement('span');
        badge.className = 'camp-verified-badge';
        badge.title = 'Verified creator';
        badge.innerHTML = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 0l3 3 4 1 1 4 3 3-3 3-1 4-4 1-3 3-3-3-4-1-1-4-3-3 3-3 1-4 4-1z"/></svg><svg class="camp-verified-tick" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>';
        row.appendChild(badge);
      }
      title.insertAdjacentElement('afterend', row);
    }

    // Time-remaining pill in the footer
    const footer = card.querySelector('.camp-card-footer');
    if (footer && days && !footer.querySelector('.camp-days-pill')) {
      const daysNum = parseInt(days, 10);
      if (!Number.isFinite(daysNum)) return;
      const urgent  = daysNum <= 7;
      const pill = document.createElement('span');
      pill.className = 'camp-days-pill' + (urgent ? ' is-urgent' : '');
      pill.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';
      pill.append(' ' + daysNum + ' ' + (daysNum === 1 ? 'day' : 'days') + ' left');
      footer.insertBefore(pill, footer.firstChild);
    }

    // Old: promoted % into a chip floating on the bar.
    // Removed — % now lives inline in .camp-progress-info (cleaner layout).
  });
}

enhanceCampCards();
const discoverGridForEnhance = document.getElementById('discover-grid');
if (discoverGridForEnhance && 'MutationObserver' in window) {
  new MutationObserver(() => enhanceCampCards(discoverGridForEnhance))
    .observe(discoverGridForEnhance, { childList: true, subtree: true });
}

/* ── Time-of-day greeting ── */
(function() {
    const el = document.getElementById('dash-greeting-word');
    if (!el) return;
    const h = new Date().getHours();
    el.textContent = h < 5 ? 'Still up' :
                     h < 12 ? 'Good morning' :
                     h < 17 ? 'Good afternoon' :
                     h < 22 ? 'Good evening' : 'Good night';
})();

/* ── Relative timestamps ──
   Any <time datetime="ISO"> gets formatted "X ago" on load. PHP opts in by
   emitting datetime= attributes; mock rows without it stay literal. */
(function() {
    function relTime(iso) {
        const then = new Date(iso).getTime();
        if (!Number.isFinite(then)) return null;
        const diff = (Date.now() - then) / 1000;
        if (diff < 60)        return 'just now';
        if (diff < 3600)      return Math.floor(diff / 60) + ' min ago';
        if (diff < 86400)     return Math.floor(diff / 3600) + ' hr ago';
        if (diff < 86400 * 7) return Math.floor(diff / 86400) + ' days ago';
        return new Date(then).toLocaleDateString();
    }
    document.querySelectorAll('time[datetime]').forEach(t => {
        const label = relTime(t.getAttribute('datetime'));
        if (label) t.textContent = label;
    });
})();

/* ── Donors modal (mockup — PHP fills donors list later) ── */
const donorsModal = document.getElementById('donors-modal');

function openDonorsModal(title) {
  if (!donorsModal) return;
  document.getElementById('donors-modal-title').textContent = title || 'Donors';
  // The summary numbers + donor rows are currently mock-static.
  // PHP later: replace #donors-list contents and update #donors-total-amount / #donors-total-count.
  donorsModal.classList.add('show');
}
function closeDonorsModal() {
  if (donorsModal) donorsModal.classList.remove('show');
}

document.getElementById('donors-modal-close')?.addEventListener('click', closeDonorsModal);
window.addEventListener('click', (e) => {
  if (e.target === donorsModal) closeDonorsModal();
});
window.addEventListener('keydown', (e) => {
  if (e.key === 'Escape' && donorsModal?.classList.contains('show')) closeDonorsModal();
});

/* ── Campaign detail modal ──
   Opens when a campaign card is clicked. Shows slideshow + full info + tabs.
   Falls back gracefully when data attributes are missing. */
const campaignModal = document.getElementById('campaign-modal');
let cmCurrentCard = null;
let cmSlideIdx = 0;
let cmSlideCount = 0;

function cmGoToSlide(idx) {
  if (cmSlideCount === 0) return;
  cmSlideIdx = (idx + cmSlideCount) % cmSlideCount;
  document.querySelectorAll('#cm-slides .cm-slide').forEach((s, i) => {
    s.classList.toggle('is-active', i === cmSlideIdx);
  });
  document.querySelectorAll('#cm-dots .cm-dot').forEach((d, i) => {
    d.classList.toggle('is-active', i === cmSlideIdx);
    d.setAttribute('aria-current', i === cmSlideIdx ? 'true' : 'false');
  });
}

function cmBuildSlides(images) {
  const slidesEl = document.getElementById('cm-slides');
  const dotsEl   = document.getElementById('cm-dots');
  const prevBtn  = document.getElementById('cm-prev');
  const nextBtn  = document.getElementById('cm-next');
  if (!slidesEl || !dotsEl) return;

  slidesEl.innerHTML = '';
  dotsEl.innerHTML = '';

  if (!images.length) {
    // Placeholder — keeps the slideshow area filled
    const ph = document.createElement('div');
    ph.className = 'cm-slide cm-slide-placeholder is-active';
    ph.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>';
    slidesEl.appendChild(ph);
    cmSlideCount = 0;
    prevBtn.hidden = true;
    nextBtn.hidden = true;
    dotsEl.hidden = true;
    return;
  }

  images.forEach((src, i) => {
    const slide = document.createElement('div');
    slide.className = 'cm-slide' + (i === 0 ? ' is-active' : '');
    slide.style.backgroundImage = `url('${src.replace(/'/g, "%27")}')`;
    slide.setAttribute('role', 'img');
    slide.setAttribute('aria-label', `Campaign image ${i + 1} of ${images.length}`);
    slidesEl.appendChild(slide);

    const dot = document.createElement('button');
    dot.type = 'button';
    dot.className = 'cm-dot' + (i === 0 ? ' is-active' : '');
    dot.setAttribute('aria-label', `Show image ${i + 1}`);
    dot.addEventListener('click', () => cmGoToSlide(i));
    dotsEl.appendChild(dot);
  });

  cmSlideIdx = 0;
  cmSlideCount = images.length;
  const showControls = images.length > 1;
  prevBtn.hidden = !showControls;
  nextBtn.hidden = !showControls;
  dotsEl.hidden = !showControls;
}

function cmReadImages(card) {
  // Priority: data-images="url1|url2|url3"  →  data-image="url"  →  none
  const multi = card.dataset.images;
  if (multi) return multi.split('|').map(s => s.trim()).filter(Boolean);
  const media = card.querySelector('.camp-card-media');
  const single = card.dataset.image || (media && media.dataset.image);
  return single ? [single] : [];
}

function cmSwitchTab(name) {
  document.querySelectorAll('.cm-tab').forEach(t => {
    const on = t.dataset.cmTab === name;
    t.classList.toggle('is-active', on);
    t.setAttribute('aria-selected', on ? 'true' : 'false');
  });
  document.querySelectorAll('.cm-panel').forEach(p => {
    const on = p.dataset.cmPanel === name;
    p.classList.toggle('is-active', on);
    p.hidden = !on;
  });
}

function openCampaignModal(card) {
  if (!campaignModal || !card) return;
  cmCurrentCard = card;

  // ── Header data ──
  const title    = card.dataset.campTitle || card.querySelector('.camp-card-title')?.textContent || 'Campaign';
  const category = card.dataset.category || '';
  const urgent   = card.dataset.urgent === 'true';
  const location = (card.dataset.location || '').replace('_lb', ' (South)');
  const days     = card.dataset.daysLeft;
  const creator  = card.dataset.creator || 'Verified organisation';
  const verified = card.dataset.verified !== 'false';
  const raised   = parseFloat(card.dataset.raised) || 0;
  const goal     = parseFloat(card.dataset.goal) || 0;
  const pct      = goal > 0 ? Math.min(100, Math.round((raised / goal) * 100)) : 0;
  const donors   = parseInt(card.querySelector('.camp-donor-count')?.textContent || '0', 10) || 0;
  const desc     = card.dataset.description || card.querySelector('.camp-card-desc')?.textContent || '';

  document.getElementById('cm-title').textContent       = title;
  document.getElementById('cm-category').textContent    = category;
  document.getElementById('cm-category').hidden         = !category;
  document.getElementById('cm-urgent').hidden           = !urgent;
  document.getElementById('cm-location').textContent    = location || '—';
  document.getElementById('cm-location-wrap').hidden    = !location;
  document.getElementById('cm-days').textContent        = days ? `${days} day${days === '1' ? '' : 's'} left` : '';
  document.getElementById('cm-days-wrap').hidden        = !days;
  document.getElementById('cm-donor-count').textContent = `${donors} donor${donors === 1 ? '' : 's'}`;
  document.getElementById('cm-creator-name').firstChild.textContent = creator + ' ';
  document.getElementById('cm-verified').hidden         = !verified;
  document.getElementById('cm-creator-avatar').textContent =
    (creator.split(' ').map(p => p[0]).join('').slice(0, 2).toUpperCase()) || 'VO';

  document.getElementById('cm-raised').textContent      = '$' + raised.toLocaleString();
  document.getElementById('cm-goal').textContent        = '$' + goal.toLocaleString();
  document.getElementById('cm-pct').textContent         = pct + '%';
  document.getElementById('cm-progress-fill').style.width = pct + '%';

  document.getElementById('cm-desc').textContent        = desc || 'No description provided.';

  // Mock donors row for the Donors tab — PHP can replace #cm-donors-list later
  document.getElementById('cm-donors-list').innerHTML = donors > 0
    ? '<li class="activity-empty">Donor details rendered by PHP after donations are loaded.</li>'
    : '<li class="activity-empty">No donors yet. Be the first.</li>';

  // Comments tab — wire campaign id into the post form so PHP gets it on submit.
  const campIdHidden = document.getElementById('cm-comment-camp-id');
  if (campIdHidden) campIdHidden.value = card.dataset.campId || '';
  const commentInput = document.getElementById('cm-comment-input');
  const commentCounter = document.getElementById('cm-comment-counter');
  if (commentInput) commentInput.value = '';
  if (commentCounter) commentCounter.textContent = '0 / 500';
  // Comments are fetched rather than read off the card: putting every comment
  // into a data attribute on every card in the grid does not scale, and this
  // list was previously hardcoded to "No comments yet" no matter what had been
  // posted — so a comment saved fine and then vanished.
  loadCampaignComments(card.dataset.campId);

  // (comments are loaded by loadCampaignComments, defined below)

  // Message-creator deep link — PHP wires this to its messaging route.
  // Hide the button if no org id on the card (no destination = no button).
  const msgBtn = document.getElementById('cm-message-btn');
  const reportBtn = document.getElementById('cm-report-creator-btn');
  const creatorLink = document.getElementById('cm-creator-link');
  const orgId = card.dataset.orgId || '';
  const creatorName = card.dataset.creator || 'this creator';

  if (msgBtn) {
    if (orgId) {
      msgBtn.href = `../php/messaging/start.php?with=${encodeURIComponent(orgId)}`;
      msgBtn.style.display = '';
    } else {
      msgBtn.removeAttribute('href');
      msgBtn.style.display = 'none';
    }
  }

  // Report-creator button — pipes into the existing report modal in user mode.
  // The report modal's [data-report-user] handler reads these data attributes.
  if (reportBtn) {
    reportBtn.dataset.reportUser = '';
    reportBtn.dataset.userId = orgId;
    reportBtn.dataset.userName = creatorName;
    if (!orgId) reportBtn.style.display = 'none';
    else        reportBtn.style.display = '';
  }

  // Creator chip click — TODO(backend): route to a real creator profile page
  // once the route exists. For now, show a friendly toast.
  if (creatorLink && !creatorLink.dataset.bound) {
    creatorLink.dataset.bound = '1';
    creatorLink.addEventListener('click', () => {
      if (typeof showToast === 'function') {
        showToast('Creator profiles are coming soon.');
      }
    });
  }

  // ── Slideshow ──
  cmBuildSlides(cmReadImages(card));

  // Reset to About tab on every open
  cmSwitchTab('about');

  campaignModal.classList.add('show');
  campaignModal.setAttribute('aria-hidden', 'false');
  requestAnimationFrame(() => document.getElementById('cm-close')?.focus());
}

function closeCampaignModal() {
  if (!campaignModal) return;
  campaignModal.classList.remove('show');
  campaignModal.setAttribute('aria-hidden', 'true');
  cmCurrentCard = null;
}

// Wire prev/next/dots
document.getElementById('cm-prev')?.addEventListener('click', () => cmGoToSlide(cmSlideIdx - 1));
document.getElementById('cm-next')?.addEventListener('click', () => cmGoToSlide(cmSlideIdx + 1));

// Tabs
document.querySelectorAll('.cm-tab').forEach(tab => {
  tab.addEventListener('click', () => cmSwitchTab(tab.dataset.cmTab));
});

// Close
document.getElementById('cm-close')?.addEventListener('click', closeCampaignModal);
window.addEventListener('click', (e) => {
  if (e.target === campaignModal) closeCampaignModal();
});
window.addEventListener('keydown', (e) => {
  if (!campaignModal?.classList.contains('show')) return;
  if (e.key === 'Escape')      closeCampaignModal();
  if (e.key === 'ArrowLeft')   cmGoToSlide(cmSlideIdx - 1);
  if (e.key === 'ArrowRight')  cmGoToSlide(cmSlideIdx + 1);
});

// Donate from campaign modal
document.getElementById('cm-donate-btn')?.addEventListener('click', () => {
  if (!cmCurrentCard) return;
  const title  = cmCurrentCard.dataset.campTitle || 'Donate';
  const id     = cmCurrentCard.dataset.campId || '';
  const raised = parseFloat(cmCurrentCard.dataset.raised) || undefined;
  const goal   = parseFloat(cmCurrentCard.dataset.goal)   || undefined;
  closeCampaignModal();
  openDonateModal(title, id, raised, goal);
});

// Live counter for the comment composer (no submission logic — PHP handles POST)
(function() {
  const input   = document.getElementById('cm-comment-input');
  const counter = document.getElementById('cm-comment-counter');
  if (!input || !counter) return;
  const MAX = 500;
  input.addEventListener('input', () => {
    const len = input.value.length;
    counter.textContent = len + ' / ' + MAX;
    counter.style.color = len > MAX - 30 ? 'var(--color-warning)' : '';
  });
})();

// Share from campaign modal — copies a deep link to clipboard
document.getElementById('cm-share-btn')?.addEventListener('click', () => {
  if (!cmCurrentCard) return;
  const id = cmCurrentCard.dataset.campId || '';
  const url = window.location.origin + window.location.pathname + (id ? `?campaign=${id}` : '');
  if (navigator.clipboard?.writeText) {
    navigator.clipboard.writeText(url).then(
      () => showToast('Campaign link copied'),
      () => showToast('Could not copy link', true)
    );
  } else {
    showToast(url);
  }
});

/* Clicking a campaign card → campaign detail modal.
   Clicking the inner Donate button → donate modal (stopPropagation handles separation). */
function initCampaignCardOpeners(root = document) {
  root.querySelectorAll('#discover-grid .camp-card').forEach(card => {
    if (card.dataset.openInit) return;
    card.dataset.openInit = '1';
    card.setAttribute('tabindex', '0');
    card.setAttribute('role', 'button');
    card.setAttribute('aria-label', 'Open details for ' + (card.dataset.campTitle || 'campaign'));
    card.addEventListener('click', (e) => {
      if (e.target.closest('.camp-donate')) return;
      openCampaignModal(card);
    });
    card.addEventListener('keydown', (e) => {
      if (e.key !== 'Enter' && e.key !== ' ') return;
      if (e.target.closest('.camp-donate')) return;
      e.preventDefault();
      openCampaignModal(card);
    });
  });
}

initCampaignCardOpeners();
const discoverGridForOpeners = document.getElementById('discover-grid');
if (discoverGridForOpeners && 'MutationObserver' in window) {
  new MutationObserver(() => initCampaignCardOpeners(discoverGridForOpeners))
    .observe(discoverGridForOpeners, { childList: true, subtree: true });
}

document.querySelectorAll('.camp-donate, .urgent-donate, .featured-camp-donate').forEach(btn => {
  btn.addEventListener('click', (e) => {
    e.stopPropagation();
    openDonateModal(btn.dataset.campTitle || 'Donate', btn.dataset.campId || '');
  });
});

/* Donors modal "Donate to this campaign" button — closes donors, opens donate */
document.getElementById('donors-modal-donate')?.addEventListener('click', () => {
  const title = document.getElementById('donors-modal-title').textContent;
  closeDonorsModal();
  openDonateModal(title, '');
});

/* ── Toast ── */
function showToast(message, isError = false) {
  const toast = document.getElementById('toast');
  if (!toast) return;
  toast.textContent = message;
  toast.className   = 'toast' + (isError ? ' error' : '');
  toast.classList.add('show');
  clearTimeout(toast._timer);
  toast._timer = setTimeout(() => toast.classList.remove('show'), 4000);
}

window.addEventListener('DOMContentLoaded', () => {
  const params = new URLSearchParams(window.location.search);
  const campId = params.get('campaign');
  if (campId) {
    const card = document.querySelector(`.camp-card[data-camp-id="${CSS.escape(campId)}"]`);
    if (card && typeof openCampaignModal === 'function') {
      const wantedTab = params.get('tab');
      requestAnimationFrame(() => {
        openCampaignModal(card);
        // Returning from posting a comment should land on the comments tab, so
        // the comment just written is the first thing on screen rather than
        // hidden behind the About tab the modal opens on by default.
        if (wantedTab && typeof cmSwitchTab === 'function') {
          setTimeout(() => cmSwitchTab(wantedTab), 60);
        }
      });
    }
    const clean = new URL(window.location.href);
    clean.searchParams.delete('campaign');
    clean.searchParams.delete('tab');
    window.history.replaceState({}, document.title, clean.pathname + clean.search);
  }
  const toastMessages = {
    success: { text: 'Saved successfully.', error: false },
    comment_posted: { text: 'Comment posted.', error: false },
    campaign_deleted: { text: 'Campaign deleted.', error: false },
    campaign_withdrawn: { text: 'Campaign withdrawn from public view. Donation records are kept.', error: false },
    error: { text: 'Something went wrong. Please try again.', error: true },
    payment_pending: { text: 'Payment is pending confirmation.', error: false },
    payment_confirmed: { text: 'Payment confirmed. Thank you.', error: false },
    payment_failed: { text: 'Payment failed. Please try another method.', error: true },
    wallet_short: { text: 'Not enough in your Sawa Wallet. Top up or pick another method.', error: true },
    payment_cancelled: { text: 'Payment was cancelled.', error: true },
  };
  const status = params.get('status');
  if (status && toastMessages[status]) {
    const entry = toastMessages[status];
    showToast(entry.text, entry.error);
    if (status.startsWith('payment_')) showPaymentStatus(status);
    window.history.replaceState({}, document.title, window.location.pathname);
  }
});

function showPaymentStatus(status) {
  if (!donateModal) return;
  const statusContent = {
    payment_pending: {
      title: 'Payment pending',
      message: 'Your payment is waiting for provider confirmation. PHP should refresh this state when the provider callback arrives.',
    },
    payment_confirmed: {
      title: 'Payment confirmed',
      message: 'Thank you. PHP confirmed the donation and can now show the final receipt/reference.',
    },
    payment_failed: {
      title: 'Payment failed',
      message: 'The provider did not confirm the payment. Please try again or choose another method.',
    },
    wallet_short: {
      title: 'Not enough wallet balance',
      message: 'Your Sawa Wallet does not cover this donation. Top up your wallet from the Wallet section, or pay with Whish or a card instead. Nothing was charged.',
    },
    payment_cancelled: {
      title: 'Payment cancelled',
      message: 'No donation was completed. You can choose another payment method and try again.',
    },
  }[status];
  if (!statusContent) return;

  document.getElementById('payment-status-title').textContent = statusContent.title;
  document.getElementById('payment-status-message').textContent = statusContent.message;
  donateModal.classList.add('show');
  showModalStep('modal-step-thanks');
}

/* ── Form loading states ── */
document.querySelectorAll('form[action]').forEach(form => {
  form.addEventListener('submit', () => {
    const btn = form.querySelector('[type="submit"]');
    if (btn) {
      btn.disabled = true;
      const label = btn.dataset.loadingLabel || 'Saving...';
      if (btn.tagName === 'INPUT') btn.value = label;
      else btn.textContent = label;
    }
  });
});

/* ── Avatar preview ── */
document.getElementById('avatar-input')?.addEventListener('change', function (e) {
  const file = e.target.files[0];
  if (file) {
    const reader = new FileReader();
    reader.onload = ev => { document.getElementById('avatar-preview').src = ev.target.result; };
    reader.readAsDataURL(file);
  }
});

/* ── Campaign image preview ── */
const campaignImageInput = document.getElementById('camp-image-input');
const campaignUploadPreview = document.getElementById('camp-upload-preview');
const campaignUploadCount = document.getElementById('camp-upload-count');
const campaignCoverIndexInput = document.getElementById('camp-cover-index');
const CAMPAIGN_IMAGE_MAX = parseInt(campaignImageInput?.dataset.maxFiles || '6', 10);
const campaignImageFiles = [];
let campaignCoverIndex = 0;

function renderCampaignUploadEmpty() {
  renderCampaignImagePreview(campaignImageFiles);
}

function syncCampaignImageInput(files) {
  if (!campaignImageInput || !window.DataTransfer) return;
  const dt = new DataTransfer();
  files.forEach(file => dt.items.add(file));
  campaignImageInput.files = dt.files;
}

function syncCampaignUploadState() {
  syncCampaignImageInput(campaignImageFiles);
  if (campaignCoverIndex >= campaignImageFiles.length) campaignCoverIndex = 0;
  if (campaignCoverIndexInput) campaignCoverIndexInput.value = String(campaignImageFiles.length ? campaignCoverIndex : 0);
  renderCampaignImagePreview(campaignImageFiles);
}

function renderCampaignImagePreview(files) {
  if (!campaignUploadPreview) return;
  campaignUploadPreview.innerHTML = '';

  if (!files.length) campaignCoverIndex = 0;
  if (campaignCoverIndex >= files.length) campaignCoverIndex = 0;
  if (campaignCoverIndexInput) campaignCoverIndexInput.value = String(files.length ? campaignCoverIndex : 0);

  campaignUploadPreview.className = 'campaign-upload-preview' + (files.length ? ' has-images' : ' is-empty');

  for (let index = 0; index < CAMPAIGN_IMAGE_MAX; index += 1) {
    const file = files[index];
    const item = document.createElement('span');
    const isCover = !!file && index === campaignCoverIndex;
    item.className = 'campaign-upload-slot' + (file ? ' is-filled' : ' is-empty') + (isCover ? ' is-cover' : '');

    if (file) {
      const img = document.createElement('img');
      img.alt = isCover ? 'Main campaign image preview' : 'Campaign image preview ' + (index + 1);
      img.src = URL.createObjectURL(file);
      img.onload = () => URL.revokeObjectURL(img.src);
      item.appendChild(img);

      const slotBadge = document.createElement('span');
      slotBadge.className = 'campaign-upload-cover';
      slotBadge.textContent = isCover ? 'Main' : 'Image ' + (index + 1);
      item.appendChild(slotBadge);

      const removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.className = 'campaign-image-remove';
      removeBtn.setAttribute('aria-label', 'Remove campaign image ' + (index + 1));
      removeBtn.textContent = '×';
      removeBtn.addEventListener('click', event => {
        event.preventDefault();
        event.stopPropagation();
        campaignImageFiles.splice(index, 1);
        if (campaignCoverIndex === index) campaignCoverIndex = 0;
        else if (campaignCoverIndex > index) campaignCoverIndex -= 1;
        syncCampaignUploadState();
      });
      item.appendChild(removeBtn);

      const coverBtn = document.createElement('button');
      coverBtn.type = 'button';
      coverBtn.className = 'campaign-cover-choice';
      coverBtn.textContent = isCover ? 'Main image' : 'Set main';
      coverBtn.setAttribute('aria-pressed', isCover ? 'true' : 'false');
      coverBtn.addEventListener('click', event => {
        event.preventDefault();
        event.stopPropagation();
        campaignCoverIndex = index;
        if (campaignCoverIndexInput) campaignCoverIndexInput.value = String(index);
        renderCampaignImagePreview(files);
      });
      item.appendChild(coverBtn);
    } else {
      item.innerHTML =
        '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M12 5v14m7-7H5"/></svg><span class="campaign-upload-slot-title">Slot ' + (index + 1) + '</span><small>Add image</small>';
    }

    campaignUploadPreview.appendChild(item);
  }

  if (campaignUploadCount) {
    campaignUploadCount.textContent = files.length + ' / ' + CAMPAIGN_IMAGE_MAX + ' image' + (files.length === 1 ? '' : 's') + ' selected';
  }
}

campaignImageInput?.addEventListener('change', function () {
  const picked = Array.from(this.files || []).filter(file => file.type.startsWith('image/'));
  const room = CAMPAIGN_IMAGE_MAX - campaignImageFiles.length;
  const limited = picked.slice(0, Math.max(0, room));

  if (picked.length !== (this.files || []).length) {
    showToast('Only image files can be uploaded.', true);
  }
  if (picked.length > room) {
    showToast('Upload up to ' + CAMPAIGN_IMAGE_MAX + ' campaign images.', true);
  }

  campaignImageFiles.push(...limited);
  syncCampaignUploadState();
});

renderCampaignUploadEmpty();

/* ── Preset amount buttons ── */
document.querySelectorAll('.preset-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const isModal = btn.closest('.modal');
    const target  = isModal
      ? document.getElementById('modal-amount')
      : document.getElementById('top-up-amount');
    if (target) target.value = btn.dataset.amount;
    btn.closest('.preset-amounts')?.querySelectorAll('.preset-btn').forEach(b => b.classList.remove('selected'));
    btn.classList.add('selected');
  });
});

['modal-amount', 'top-up-amount'].forEach(id => {
  document.getElementById(id)?.addEventListener('input', function () {
    this.closest('form')?.querySelectorAll('.preset-btn').forEach(b => b.classList.remove('selected'));
  });
});

/* ── Profile form inline validation ── */
const profileNameInput   = document.getElementById('profile-name');
const profileBioInput    = document.getElementById('profile-bio');
const profileDisplayName = document.getElementById('profile-display-name');

if (profileNameInput && profileDisplayName) {
  const sidebarUserName = document.getElementById('sidebar-user-name');
  const syncDisplayName = () => {
    const val = profileNameInput.value.trim();
    if (val.length === 0) {
      profileDisplayName.textContent = 'Your Name';
      profileDisplayName.classList.add('is-placeholder');
    } else {
      profileDisplayName.textContent = val;
      profileDisplayName.classList.remove('is-placeholder');
      if (sidebarUserName) sidebarUserName.textContent = val;
    }
  };
  syncDisplayName();
  profileNameInput.addEventListener('input', syncDisplayName);
}

if (profileBioInput) {
  const bioCounter = document.createElement('span');
  bioCounter.className   = 'field-hint';
  bioCounter.textContent = '0 / 250';
  profileBioInput.after(bioCounter);
  profileBioInput.addEventListener('input', () => {
    const len = profileBioInput.value.length;
    bioCounter.textContent = len + ' / 250';
    bioCounter.style.color = len > 230 ? '#f59e0b' : '';
  });
}

const profileForm = profileNameInput ? profileNameInput.closest('form') : null;
if (profileForm) {
  profileForm.addEventListener('submit', function (e) {
    if (profileNameInput.value.trim().length < 2) {
      e.preventDefault();
      profileNameInput.style.borderColor = '#ef4444';
      profileNameInput.focus();
      return;
    }
    profileNameInput.style.borderColor = '';
  });

  profileNameInput.addEventListener('input', function () {
    if (profileNameInput.value.trim().length >= 2) {
      profileNameInput.style.borderColor = '';
    }
  });
}

/* ── Campaign form validation ── */
document.querySelector('.campaign-form')?.addEventListener('submit', e => {
  const title = document.getElementById('camp-title');
  const goal  = document.getElementById('camp-goal');
  const images = Array.from(document.getElementById('camp-image-input')?.files || []);
  let firstErr = null;

  if (title && title.value.trim().length < 5) {
    title.style.borderColor = '#ef4444';
    firstErr = firstErr || title;
  } else if (title) { title.style.borderColor = ''; }

  if (goal && (isNaN(parseFloat(goal.value)) || parseFloat(goal.value) < 1)) {
    goal.style.borderColor = '#ef4444';
    firstErr = firstErr || goal;
  } else if (goal) { goal.style.borderColor = ''; }

  if (images.length > CAMPAIGN_IMAGE_MAX) {
    e.preventDefault();
    showToast('Please keep campaign uploads to ' + CAMPAIGN_IMAGE_MAX + ' images.', true);
    return;
  }

  if (firstErr) { e.preventDefault(); firstErr.focus(); }
});

/* Removed: initReadMore — targeted .campaign-card-body > p (the legacy
   card layout). The new .camp-card has no read-more affordance. */

/* ── Site footer: accordion behavior on mobile (≤600px) ──
   Each .site-footer-col (excluding the brand col) collapses to just its h4
   header on small screens. Tapping the h4 toggles open/closed.
   Desktop is unaffected — we only add the class while ≤600px. */
(function setupFooterAccordion() {
  const cols = document.querySelectorAll('.site-footer-col');
  if (!cols.length) return;
  const mq = window.matchMedia('(max-width: 600px)');

  function syncToViewport() {
    cols.forEach(col => {
      if (mq.matches) col.classList.add('is-closed');
      else col.classList.remove('is-closed');
    });
  }

  cols.forEach(col => {
    const heading = col.querySelector('h4');
    if (!heading) return;
    heading.setAttribute('role', 'button');
    heading.setAttribute('tabindex', '0');
    const toggle = () => {
      if (!mq.matches) return;        // ignore on desktop
      col.classList.toggle('is-closed');
    };
    heading.addEventListener('click', toggle);
    heading.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); }
    });
  });

  syncToViewport();
  mq.addEventListener('change', syncToViewport);
})();

/* ════════════════════════════════════════════════════════════════════════
   Stage 3a — Report Campaign
   Injects a Report button onto every .camp-card, opens the report modal,
   and POSTs { type, target_id, reason, message } to
   /php/engagement/report.php. Admin reviews in admin.php.
   ════════════════════════════════════════════════════════════════════════ */
(function setupReportCampaign() {
  const overlay = document.getElementById('report-overlay');
  const form = document.getElementById('report-form');
  if (!overlay || !form) return;

  const modal = document.getElementById('report-modal');
  const closeBtn = document.getElementById('report-close');
  const cancelBtn = document.getElementById('report-cancel');
  const titleEl = document.getElementById('report-title');
  const targetIdField = document.getElementById('report-target-id');
  const typeField = document.getElementById('report-type');
  const targetNameField = document.getElementById('report-target-name');
  const reasonField = document.getElementById('report-reason');
  const messageField = document.getElementById('report-message');
  const successMsg = document.getElementById('report-success');

  // Reason lists per report type. Last entry is always "Other".
  const REASONS = {
    campaign: [
      ['fake', 'Fake campaign'],
      ['offensive', 'Offensive content'],
      ['wrong_info', 'Wrong information'],
      ['suspicious', 'Suspicious campaign'],
      ['duplicate', 'Duplicate campaign'],
      ['other', 'Other'],
    ],
    user: [
      ['fake_account', 'Fake account'],
      ['impersonation', 'Impersonation'],
      ['scam', 'Spam or scam'],
      ['inappropriate', 'Inappropriate behaviour'],
      ['harassment', 'Harassment'],
      ['other', 'Other'],
    ],
  };

  // Cache so we can restore the right name when the user toggles modes.
  let lastCampaign = { id: '', title: '' };
  let lastUser = { id: '', name: '' };

  function setMode(mode) {
    mode = mode === 'user' ? 'user' : 'campaign';
    modal.dataset.mode = mode;
    typeField.value = mode;

    // Update segmented control active state.
    modal.querySelectorAll('.report-seg-btn').forEach(btn => {
      const isActive = btn.dataset.reportMode === mode;
      btn.classList.toggle('is-active', isActive);
      btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
    });

    // Swap title + name + reason list.
    if (mode === 'campaign') {
      titleEl.textContent = 'Report this campaign';
      targetIdField.value = lastCampaign.id || '';
      targetNameField.textContent = lastCampaign.title ? '“' + lastCampaign.title + '”' : '';
    } else {
      titleEl.textContent = 'Report this user';
      targetIdField.value = lastUser.id || '';
      targetNameField.textContent = lastUser.name ? '“' + lastUser.name + '”' : '';
    }

    // Rebuild reason options.
    reasonField.innerHTML = '<option value="">Select a reason…</option>' +
      REASONS[mode].map(([v, l]) => `<option value="${v}">${l}</option>`).join('');
  }

  // ── Inject a Report flag icon onto every campaign card (top-right of media) ──
  function ensureReportButton(card) {
    if (!card || card.querySelector('.camp-report-btn')) return;
    const media = card.querySelector('.camp-card-media');
    if (!media) return;
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'camp-report-btn';
    btn.title = 'Report this campaign';
    btn.setAttribute('aria-label', 'Report this campaign');
    btn.innerHTML =
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
      '<line x1="4" y1="22" x2="4" y2="15"/><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/></svg>';
    media.appendChild(btn);
  }

  function scanCards() {
    document.querySelectorAll('.camp-card').forEach(ensureReportButton);
  }
  scanCards();
  // Re-scan when cards are added dynamically (filter / search re-renders).
  const observer = new MutationObserver(scanCards);
  document.querySelectorAll('#discover-grid, #my-campaigns-list, .urgent-list')
    .forEach(el => observer.observe(el, { childList: true, subtree: true }));

  // ── Open / close modal ──
  function openModal({ mode = 'campaign', id = '', name = '' } = {}) {
    if (mode === 'user') {
      lastUser = { id, name };
    } else {
      lastCampaign = { id, title: name };
    }
    messageField.value = '';
    successMsg.hidden = true;
    setMode(mode);
    overlay.hidden = false;
    document.body.style.overflow = 'hidden';
    reasonField.focus();
  }
  function closeModal() {
    overlay.hidden = true;
    document.body.style.overflow = '';
  }

  // Capture-phase listener so it runs BEFORE the campaign card's own click
  // handler that opens the detail modal. Otherwise clicking Report opens both.
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.camp-report-btn');
    if (!btn) return;
    e.preventDefault();
    e.stopPropagation();
    const card = btn.closest('.camp-card');
    openModal({
      mode: 'campaign',
      id: card?.dataset.campId,
      name: card?.dataset.campTitle,
    });
  }, true);

  // Future: any element with [data-report-user] opens the user variant.
  // Example markup: <button data-report-user data-user-id="42" data-user-name="Foo">Report</button>
  document.addEventListener('click', (e) => {
    const trigger = e.target.closest('[data-report-user]');
    if (!trigger) return;
    e.preventDefault();
    e.stopPropagation();
    openModal({
      mode: 'user',
      id: trigger.dataset.userId,
      name: trigger.dataset.userName,
    });
  }, true);

  // Segmented control toggles between campaign + user modes.
  modal.querySelectorAll('.report-seg-btn').forEach(btn => {
    btn.addEventListener('click', () => setMode(btn.dataset.reportMode));
  });

  closeBtn?.addEventListener('click', closeModal);
  cancelBtn?.addEventListener('click', closeModal);
  overlay.addEventListener('click', (e) => { if (e.target === overlay) closeModal(); });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !overlay.hidden) closeModal();
  });

  // ── Submit — POST to /php/engagement/report.php via fetch, hard-form fallback on failure. ──
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    if (!reasonField.value) {
      reasonField.focus();
      return;
    }

    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) submitBtn.disabled = true;

    const data = new FormData(form);

    fetch(form.action, {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
      body: data,
      credentials: 'same-origin',
    })
      .then((r) => (r.ok ? r.json() : Promise.reject(r)))
      .then(() => {
        successMsg.hidden = false;
        setTimeout(closeModal, 1800);
      })
      .catch(() => {
        form.submit();
      })
      .finally(() => {
        if (submitBtn) submitBtn.disabled = false;
      });
  });
})();

/* ── Escape key + browser back gesture pop the section stack ──
   On sub-pages, Esc goes back to the parent section. The browser's back
   button is hooked via history.pushState (best-effort — degrades cleanly
   if the user has navigated externally). */
document.addEventListener('keydown', (e) => {
  if (e.key !== 'Escape') return;
  const active = document.querySelector('.section.active');
  if (!active || !active.dataset.back) return;
  const previous = _SECTION_HISTORY.pop() || active.dataset.back;
  switchSection(previous, { fromBack: true });
});

(function () {
  if (!('history' in window) || !history.pushState) return;
  // Seed a history entry so the very first sub-page tap has somewhere to pop to.
  history.replaceState({ section: 'dashboard' }, '');
  const _origSwitch = switchSection;
  window.switchSection = function (id, opts) {
    _origSwitch(id, opts);
    if (!opts || !opts.fromBack) {
      const target = document.getElementById(id);
      if (target && target.dataset.back) {
        history.pushState({ section: id }, '', '#' + id);
      }
    }
  };
  window.addEventListener('popstate', (e) => {
    const target = (e.state && e.state.section) || 'dashboard';
    _origSwitch(target, { fromBack: true });
  });
})();

/* Profile edit: Cancel button on #profile-edit returns to #profile via the
   sub-page navigation stack. The Edit profile button itself is a regular
   [data-section="profile-edit"] handled by the catch-all delegated handler. */
document.querySelectorAll('[data-back-to]').forEach(btn => {
  btn.addEventListener('click', () => {
    switchSection(btn.dataset.backTo, { fromBack: true });
  });
});

/* Sidebar account switcher: tiny caret on the user card toggles the panel.
   The panel itself is real (current account + Add an account); additional
   accounts are populated by PHP once /php/auth/sessions.php exists. */
(function () {
  const toggle = document.getElementById('sidebar-account-toggle');
  const panel  = document.getElementById('sidebar-account-panel');
  if (!toggle || !panel) return;
  toggle.addEventListener('click', (e) => {
    e.stopPropagation();
    const open = panel.hidden;
    panel.hidden = !open;
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  });
})();

/* Instant messaging: send via fetch (no page reload) and poll for new messages
   so incoming replies appear without refreshing. Falls back to a normal form
   POST if anything goes wrong. */
(function () {
  const form = document.querySelector('.chat-compose');
  const body = document.querySelector('.chat-body');
  if (!form || !body) return;

  const threadId = body.dataset.threadId;
  if (!threadId) return; // no active conversation selected

  const input = form.querySelector('input[name="message"]');
  const csrf = form.querySelector('input[name="_csrf"]');

  let lastId = 0;
  body.querySelectorAll('.chat-bubble[data-msg-id]').forEach((el) => {
    lastId = Math.max(lastId, parseInt(el.dataset.msgId, 10) || 0);
  });

  function fmtTime(raw) {
    const d = new Date(String(raw).replace(' ', 'T'));
    if (isNaN(d.getTime())) return '';
    return d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
  }

  function appendBubble(msg, outgoing) {
    if (msg.id && msg.id <= lastId) return;
    const wrap = document.createElement('div');
    wrap.className = 'chat-bubble ' + (outgoing ? 'outgoing' : 'incoming');
    if (msg.id) wrap.dataset.msgId = msg.id;
    const p = document.createElement('p');
    p.textContent = msg.body; // textContent prevents XSS
    const t = document.createElement('time');
    t.textContent = fmtTime(msg.created_at);
    wrap.appendChild(p);
    wrap.appendChild(t);
    // Drop the "No messages yet" placeholder if present.
    const placeholder = body.querySelector('.empty-inline');
    if (placeholder) placeholder.remove();
    body.appendChild(wrap);
    if (msg.id) lastId = Math.max(lastId, msg.id);
    body.scrollTop = body.scrollHeight;
  }

  form.addEventListener('submit', (e) => {
    const text = (input?.value || '').trim();
    if (!text) { e.preventDefault(); return; }
    e.preventDefault();

    const data = new FormData();
    data.append('thread_id', threadId);
    data.append('message', text);
    if (csrf) data.append('_csrf', csrf.value);

    const sendBtn = form.querySelector('button[type="submit"]');
    if (sendBtn) sendBtn.disabled = true;

    fetch('../php/messaging/send.php', {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
      body: data,
      credentials: 'same-origin',
    })
      .then((r) => (r.ok ? r.json() : Promise.reject(r)))
      .then((res) => {
        if (res && res.message) appendBubble(res.message, true);
        if (input) input.value = '';
      })
      .catch(() => { form.submit(); }) // hard fallback to full-page POST
      .finally(() => { if (sendBtn) sendBtn.disabled = false; });
  });

  function poll() {
    if (document.visibilityState !== 'visible') return;
    fetch('../php/messaging/thread.php?thread_id=' + encodeURIComponent(threadId) + '&after=' + lastId, {
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin',
    })
      .then((r) => (r.ok ? r.json() : Promise.reject(r)))
      .then((res) => {
        if (!res || !Array.isArray(res.messages)) return;
        res.messages.forEach((m) => appendBubble(m, Number(m.sender_id) === Number(res.me)));
      })
      .catch(() => {});
  }
  setInterval(poll, 4000);
})();


/* ── Campaign comments ────────────────────────────────────────────────────
   Loads the real feed for a campaign into the modal's Comments tab and keeps
   the tab badge honest. Called whenever the modal opens, and again after a
   comment is posted so the new one appears without a reload. */
async function loadCampaignComments(campaignId) {
  const list = document.getElementById('cm-comments-list');
  const badge = document.getElementById('cm-comments-count');
  if (!list || !campaignId) return;

  list.innerHTML = '<li class="activity-empty">Loading comments…</li>';
  try {
    const res = await fetch('../php/engagement/comments-list.php?campaign_id=' + encodeURIComponent(campaignId),
                            { headers: { 'Accept': 'application/json' } });
    const data = await res.json();
    const items = (data && data.comments) || [];

    if (badge) {
      badge.textContent = items.length;
      badge.hidden = items.length === 0;
    }
    if (!items.length) {
      list.innerHTML = '<li class="activity-empty">No comments yet — be the first to post.</li>';
      return;
    }
    // textContent everywhere below, never innerHTML with server data: comment
    // bodies are user-written and must not be able to inject markup here.
    list.innerHTML = '';
    items.forEach(c => {
      const li = document.createElement('li');
      li.className = 'cm-comment-row';
      const img = document.createElement('img');
      img.className = 'cm-comment-avatar';
      img.src = c.avatar || '../images/user-profile.svg';
      img.alt = '';
      const wrap = document.createElement('div');
      wrap.className = 'cm-comment-body';
      const head = document.createElement('div');
      head.className = 'cm-comment-head';
      const who = document.createElement('strong');
      who.textContent = c.author;
      const when = document.createElement('small');
      when.textContent = c.ago;
      head.append(who, when);
      const text = document.createElement('p');
      text.textContent = c.body;
      wrap.append(head, text);
      li.append(img, wrap);
      list.appendChild(li);
    });
  } catch (e) {
    list.innerHTML = '<li class="activity-empty">Could not load comments.</li>';
  }
}
