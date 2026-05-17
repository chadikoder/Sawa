// Press feedback for nav/sidebar/buttons on all devices
document.querySelectorAll('nav a, .sidebar-item, .sidebar-toggle, .sidebar-user, .bottom-nav-item, .btn, .action-btn').forEach(el => {
  el.addEventListener('mousedown', () => el.classList.add('pressed'));
  el.addEventListener('touchstart', () => el.classList.add('pressed'), {passive: true});
  el.addEventListener('mouseup', () => el.classList.remove('pressed'));
  el.addEventListener('mouseleave', () => el.classList.remove('pressed'));
  el.addEventListener('touchend', () => el.classList.remove('pressed'), {passive: true});
  el.addEventListener('touchcancel', () => el.classList.remove('pressed'), {passive: true});
});

/* ── Section switching ── */
function switchSection(sectionId) {
  document.querySelectorAll('.sidebar-item').forEach(i => i.classList.remove('active'));
  document.querySelectorAll('.bottom-nav-item').forEach(i => i.classList.remove('active'));
  document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));

  const section = document.getElementById(sectionId);
  if (section) section.classList.add('active');

  document.querySelectorAll(`[data-section="${sectionId}"]`).forEach(el => el.classList.add('active'));
  closeSidebar();
}

document.querySelectorAll('.sidebar-item[data-section]').forEach(item => {
  item.addEventListener('click', () => switchSection(item.dataset.section));
});

document.querySelectorAll('.bottom-nav-item[data-section]').forEach(item => {
  item.addEventListener('click', () => switchSection(item.dataset.section));
});

/* ── Sidebar open / close (mobile) ── */
function closeSidebar() {
  document.querySelector('.sidebar')?.classList.remove('open');
  document.querySelector('.mobile-overlay')?.classList.remove('show');
  document.body.style.overflow = '';
}

function openSidebar() {
  document.querySelector('.sidebar')?.classList.add('open');
  document.querySelector('.mobile-overlay')?.classList.add('show');
  document.body.style.overflow = 'hidden';
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

    // Jump-to-section links inside the header + drawer
    document.querySelectorAll('[data-jump]').forEach(el => {
        el.addEventListener('click', (e) => {
            e.preventDefault();
            const target = el.dataset.jump;
            const sectionBtn = document.querySelector(`[data-section="${target}"]`);
            if (sectionBtn) sectionBtn.click();
            // Highlight active link in header + drawer
            document.querySelectorAll('[data-jump]').forEach(other => {
                other.classList.toggle('is-active', other.dataset.jump === target);
            });
            closeDrawer();
        });
    });
})();

document.querySelector('.mobile-overlay')?.addEventListener('click', closeSidebar);

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

/* Sign-in-required prompt for guests trying to use auth-only bottom-nav items.
   Reuses #auth-overlay; rewrites the subtitle for context. */
const _AUTH_SUBTITLE_DEFAULT = document.querySelector('#auth-overlay .auth-subtitle')?.textContent || '';
const _AUTH_TITLE_DEFAULT    = document.querySelector('#auth-overlay .auth-title')?.textContent || '';
function promptSignIn(feature) {
  const overlay  = document.getElementById('auth-overlay');
  const title    = overlay?.querySelector('.auth-title');
  const subtitle = overlay?.querySelector('.auth-subtitle');
  if (title)    title.textContent    = 'Sign in to continue';
  if (subtitle) subtitle.textContent = `You need to sign in to use ${feature}. Create a free Sawa account in seconds — it only takes a minute.`;
  overlay?.classList.add('show');
  document.body.style.overflow = 'hidden';
}
function closeAuthPrompt() {
  const overlay  = document.getElementById('auth-overlay');
  const title    = overlay?.querySelector('.auth-title');
  const subtitle = overlay?.querySelector('.auth-subtitle');
  overlay?.classList.remove('show');
  document.body.style.overflow = '';
  if (title    && _AUTH_TITLE_DEFAULT)    title.textContent    = _AUTH_TITLE_DEFAULT;
  if (subtitle && _AUTH_SUBTITLE_DEFAULT) subtitle.textContent = _AUTH_SUBTITLE_DEFAULT;
}

document.getElementById('auth-overlay')?.addEventListener('click', (e) => {
  if (e.target.id === 'auth-overlay') closeAuthPrompt();
});
document.getElementById('auth-modal-close')?.addEventListener('click', closeAuthPrompt);
document.getElementById('auth-modal-cancel')?.addEventListener('click', closeAuthPrompt);
document.addEventListener('keydown', e => {
  if (e.key === 'Escape' && document.getElementById('auth-overlay')?.classList.contains('show')) {
    closeAuthPrompt();
  }
});

/* Bottom-nav: gate auth-only items for guests (show 'Sign in' prompt) */
const GUEST_RESTRICTED = {
  'wallet':       'your wallet',
  'profile':      'your profile',
  'campaign-new': 'create a campaign',
};
document.querySelectorAll('.bottom-nav-item[data-section]').forEach(item => {
  item.addEventListener('click', (e) => {
    const target = item.dataset.section;
    if (document.body.classList.contains('is-guest') && GUEST_RESTRICTED[target]) {
      e.preventDefault();
      e.stopImmediatePropagation();
      promptSignIn(GUEST_RESTRICTED[target]);
    }
  }, true);
});

document.addEventListener('keydown', e => {
  if (e.key === 'Escape') closeSidebar();
});

/* ── Sidebar collapse (desktop toggle) ── */
document.querySelector('.sidebar-toggle')?.addEventListener('click', () => {
  document.querySelector('.sidebar').classList.toggle('collapsed');
});

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
    });
  }

  function updateAdvCount() {
    if (!advCount) return;
    let n = 0;
    if (state.urgency !== 'All') n++;
    if (state.location !== 'all') n++;
    if (n > 0) { advCount.hidden = false; advCount.textContent = n; }
    else       { advCount.hidden = true; }
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
      pill.innerHTML = `${p.label}<span aria-hidden="true">&times;</span>`;
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
  document.getElementById('modal-step-2').setAttribute('hidden', '');

  document.getElementById('modal-camp-title').textContent = title;
  document.getElementById('modal-camp-id-hidden').value   = campId;

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
  document.getElementById('modal-amount').focus();
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
   Step 1 (amount) → Step 2 (payment) → Step 3 (review) → on submit → Step 4 (thanks)
*/

// Step 1 → Step 2 (Payment)
document.getElementById('modal-review-btn')?.addEventListener('click', () => {
  const amt = document.getElementById('modal-amount');
  const val = parseFloat(amt?.value);
  if (!amt || isNaN(val) || val < 1) {
    if (amt) { amt.style.borderColor = '#ef4444'; amt.focus(); }
    return;
  }
  amt.style.borderColor = '';
  showModalStep('modal-step-payment');
});

// Step 2 → back to Step 1
document.getElementById('payment-back-btn')?.addEventListener('click', () => {
  showModalStep('modal-step-1');
});

// Step 2 → Step 3 (Review)
document.getElementById('payment-next-btn')?.addEventListener('click', () => {
  const num = document.getElementById('card-number');
  const exp = document.getElementById('card-expiry');
  const cvv = document.getElementById('card-cvv');
  const name = document.getElementById('card-name');

  let valid = true;
  const digits = (num.value || '').replace(/\s/g, '');
  if (digits.length < 13 || digits.length > 19 || !/^\d+$/.test(digits)) { num.style.borderColor = '#ef4444'; valid = false; } else { num.style.borderColor = ''; }
  if (!/^\d{2}\/\d{2}$/.test(exp.value)) { exp.style.borderColor = '#ef4444'; valid = false; } else { exp.style.borderColor = ''; }
  if (!/^\d{3,4}$/.test(cvv.value)) { cvv.style.borderColor = '#ef4444'; valid = false; } else { cvv.style.borderColor = ''; }
  if (!name.value.trim()) { name.style.borderColor = '#ef4444'; valid = false; } else { name.style.borderColor = ''; }
  if (!valid) return;

  // Update review step
  const amt = parseFloat(document.getElementById('modal-amount').value);
  document.getElementById('review-amount-display').textContent   = '$' + amt.toLocaleString();
  document.getElementById('review-campaign-display').textContent = document.getElementById('modal-camp-title').textContent;
  document.getElementById('review-card-display').textContent     = `paying with card ending in ${digits.slice(-4)}`;

  showModalStep('modal-step-2');
});

// Step 3 → back to Step 2
document.getElementById('review-back-btn')?.addEventListener('click', () => {
  showModalStep('modal-step-payment');
});

// Confirm & Donate → show thank-you screen (intercepts submit until PHP is wired)
document.getElementById('confirm-donate-btn')?.addEventListener('click', (e) => {
  e.preventDefault();
  // NOTE: PHP integration — replace this preventDefault + step swap with a real
  // form.submit() once `process_donation.php` is ready. The thank-you screen
  // can then be triggered by `?status=success` on redirect instead.
  const amt = parseFloat(document.getElementById('modal-amount').value);
  document.getElementById('thanks-amount').textContent   = '$' + amt.toLocaleString();
  document.getElementById('thanks-campaign').textContent = document.getElementById('modal-camp-title').textContent;
  showModalStep('modal-step-thanks');
});

// Thank-you Close button
document.getElementById('thanks-close-btn')?.addEventListener('click', closeDonateModal);

/* Card-number auto-formatting + live preview */
(function() {
  const num  = document.getElementById('card-number');
  const exp  = document.getElementById('card-expiry');
  const cvv  = document.getElementById('card-cvv');
  const name = document.getElementById('card-name');
  const previewNum   = document.getElementById('card-preview-number');
  const previewName  = document.getElementById('card-preview-name');
  const previewExp   = document.getElementById('card-preview-expiry');
  const previewBrand = document.getElementById('card-preview-brand');

  function detectBrand(digits) {
    if (/^4/.test(digits))         return 'VISA';
    if (/^5[1-5]/.test(digits))    return 'MASTERCARD';
    if (/^3[47]/.test(digits))     return 'AMEX';
    if (/^6/.test(digits))         return 'DISCOVER';
    return 'VISA';
  }

  num?.addEventListener('input', (e) => {
    let d = e.target.value.replace(/\D/g, '').slice(0, 19);
    e.target.value = d.replace(/(.{4})/g, '$1 ').trim();
    if (previewNum) previewNum.textContent = (e.target.value || '•••• •••• •••• ••••').padEnd(19, '•').slice(0, 19);
    if (previewBrand) previewBrand.textContent = detectBrand(d);
  });

  exp?.addEventListener('input', (e) => {
    let d = e.target.value.replace(/\D/g, '').slice(0, 4);
    if (d.length >= 3) d = d.slice(0, 2) + '/' + d.slice(2);
    e.target.value = d;
    if (previewExp) previewExp.textContent = d || 'MM/YY';
  });

  cvv?.addEventListener('input', (e) => {
    e.target.value = e.target.value.replace(/\D/g, '').slice(0, 4);
  });

  name?.addEventListener('input', (e) => {
    if (previewName) previewName.textContent = (e.target.value || 'YOUR NAME').toUpperCase();
  });
})();

/* ── Guest/auth mode toggle ──
   PHP sets the body class to is-auth or is-guest server-side.
   For local testing, ?guest=1 in the URL forces is-guest.
   The donate form action also flips: guests post to guest_donation.php
   (no account); logged-in users post to process_donation.php. */
(function() {
    const params = new URLSearchParams(window.location.search);
    if (params.has('guest')) {
        document.body.classList.remove('is-auth', 'role-donor', 'role-taker', 'role-org');
        document.body.classList.add('is-guest');
    }
    const role = params.get('role');
    if (role === 'donor' || role === 'taker' || role === 'org') {
        document.body.classList.remove('role-donor', 'role-taker', 'role-org');
        document.body.classList.add('role-' + role);
    }
    const form = document.getElementById('donate-form');
    if (form && document.body.classList.contains('is-guest')) {
        form.setAttribute('action', '../php/guest_donation.php');
    }
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
        panel.querySelectorAll('.dash-notif-row.unread').forEach(r => r.classList.remove('unread'));
        const dot = bellBtn.querySelector('.dash-bell-dot');
        if (dot) dot.style.display = 'none';
    });
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
   Injects: location badge (overlay), creator row with verified tick,
   days-remaining pill, and an absolute % chip on the progress bar.
   Uses existing data-* on each .camp-card. Creator + days are mocked
   deterministically by camp-id (PHP can later set data-creator /
   data-days-left / data-verified on each card to override). */
(function enhanceCampCards() {
  const creators = ['Layla N.', 'Ahmad R.', 'Sara H.', 'Yousef K.', 'Maria F.', 'Hassan T.', 'Nour A.', 'Omar S.'];
  const daysLeft = [14, 7, 21, 5, 30, 12, 18, 9];

  document.querySelectorAll('.camp-card').forEach(card => {
    if (card.dataset.v2Enhanced) return;
    card.dataset.v2Enhanced = '1';

    const id       = parseInt(card.dataset.campId || '0', 10);
    const idx      = (id - 1 + creators.length) % creators.length;
    const creator  = card.dataset.creator   || creators[idx] || 'Verified';
    const days     = card.dataset.daysLeft  || daysLeft[idx] || 14;
    const verified = card.dataset.verified !== 'false';
    const location = card.dataset.location || '';

    // Location badge — overlay on the media area
    const media = card.querySelector('.camp-card-media');
    if (media && location && !media.querySelector('.camp-location-badge')) {
      const loc = document.createElement('span');
      loc.className = 'camp-location-badge';
      loc.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg><span>${location.replace('_lb', ' (South)')}</span>`;
      media.appendChild(loc);
    }

    // Creator row inserted right after the title
    const content = card.querySelector('.camp-card-content');
    const title   = card.querySelector('.camp-card-title');
    if (content && title && !content.querySelector('.camp-creator-row')) {
      const initials = creator.split(' ').map(p => p[0]).join('').slice(0, 2).toUpperCase();
      const row = document.createElement('div');
      row.className = 'camp-creator-row';
      row.innerHTML = `
        <span class="camp-creator-avatar" aria-hidden="true">${initials}</span>
        <span class="camp-creator-name">${creator}</span>
        ${verified ? '<span class="camp-verified-badge" title="Verified creator"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 0l3 3 4 1 1 4 3 3-3 3-1 4-4 1-3 3-3-3-4-1-1-4-3-3 3-3 1-4 4-1z"/></svg><svg class="camp-verified-tick" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg></span>' : ''}
      `;
      title.insertAdjacentElement('afterend', row);
    }

    // Time-remaining pill in the footer
    const footer = card.querySelector('.camp-card-footer');
    if (footer && !footer.querySelector('.camp-days-pill')) {
      const daysNum = parseInt(days, 10);
      const urgent  = daysNum <= 7;
      const pill = document.createElement('span');
      pill.className = 'camp-days-pill' + (urgent ? ' is-urgent' : '');
      pill.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> ${daysNum} ${daysNum === 1 ? 'day' : 'days'} left`;
      footer.insertBefore(pill, footer.firstChild);
    }

    // Promote the existing % into an absolute chip on the progress bar
    const pctEl  = card.querySelector('.camp-pct');
    const bar    = card.querySelector('.progress-bar');
    if (bar && pctEl && !bar.querySelector('.progress-pct-chip')) {
      const chip = document.createElement('span');
      chip.className = 'progress-pct-chip';
      chip.textContent = pctEl.textContent.trim();
      bar.appendChild(chip);
    }
  });
})();

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

/* Clicking a campaign card → donors modal.
   Clicking the inner Donate button → donate modal (stopPropagation handles separation). */
document.querySelectorAll('#discover-grid .camp-card').forEach(card => {
  card.addEventListener('click', (e) => {
    if (e.target.closest('.camp-donate')) return;
    openDonorsModal(card.dataset.campTitle);
  });
});

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
  if (params.has('status')) {
    const isError = params.get('status') === 'error';
    showToast(params.get('msg') || (isError ? 'An error occurred' : 'Success!'), isError);
    window.history.replaceState({}, document.title, window.location.pathname);
  }
});

/* ── Form loading states ── */
document.querySelectorAll('form[action]').forEach(form => {
  form.addEventListener('submit', () => {
    const btn = form.querySelector('[type="submit"]');
    if (btn) { btn.disabled = true; btn.textContent = 'Saving...'; }
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
document.getElementById('camp-image-input')?.addEventListener('change', function (e) {
  const file = e.target.files[0];
  if (file) {
    const reader = new FileReader();
    reader.onload = ev => {
      document.getElementById('camp-upload-preview').innerHTML =
        `<img src="${ev.target.result}" style="width:100%;height:100%;object-fit:cover;border-radius:8px;">`;
    };
    reader.readAsDataURL(file);
  }
});

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
  let firstErr = null;

  if (title && title.value.trim().length < 5) {
    title.style.borderColor = '#ef4444';
    firstErr = firstErr || title;
  } else if (title) { title.style.borderColor = ''; }

  if (goal && (isNaN(parseFloat(goal.value)) || parseFloat(goal.value) < 1)) {
    goal.style.borderColor = '#ef4444';
    firstErr = firstErr || goal;
  } else if (goal) { goal.style.borderColor = ''; }

  if (firstErr) { e.preventDefault(); firstErr.focus(); }
});

/* ── Read-more on campaign cards (PHP renders cards into the grids on page load) ── */
function initReadMore(grid) {
  if (!grid) return;
  grid.querySelectorAll('.campaign-card-body > p').forEach(function (p) {
    if (p.dataset.rmInit) return;
    p.dataset.rmInit = '1';
    var toggle = document.createElement('button');
    toggle.className   = 'read-more-btn';
    toggle.textContent = 'Read more';
    toggle.addEventListener('click', function (ev) {
      ev.stopPropagation();
      var expanded = p.classList.toggle('expanded');
      toggle.textContent = expanded ? 'Show less' : 'Read more';
    });
    p.after(toggle);
  });
}

initReadMore(document.getElementById('discover-grid'));
initReadMore(document.getElementById('my-campaigns-list'));
