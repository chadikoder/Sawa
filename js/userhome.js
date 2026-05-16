// Press feedback for nav/sidebar/buttons on all devices
document.querySelectorAll('nav a, .sidebar-item, .sidebar-close, .sidebar-toggle, .bottom-nav-item, .btn, .action-btn').forEach(el => {
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

document.querySelector('.mobile-overlay')?.addEventListener('click', closeSidebar);
document.querySelector('.sidebar-close')?.addEventListener('click', closeSidebar);
document.querySelector('.nav-user')?.addEventListener('click', () => {
  if (window.innerWidth <= 768) {
    openSidebar();
  } else {
    switchSection('dashboard');
  }
});

document.addEventListener('keydown', e => {
  if (e.key === 'Escape') closeSidebar();
});

/* ── Sidebar collapse (desktop toggle) ── */
document.querySelector('.sidebar-toggle')?.addEventListener('click', () => {
  document.querySelector('.sidebar').classList.toggle('collapsed');
});

/* ── Filter panel toggle ── */
const filterToggle  = document.getElementById('filter-toggle');
const filterAdvanced = document.getElementById('filter-advanced');

filterToggle?.addEventListener('click', () => {
  const isHidden = filterAdvanced.hasAttribute('hidden');
  if (isHidden) {
    filterAdvanced.removeAttribute('hidden');
    filterToggle.setAttribute('aria-expanded', 'true');
    filterToggle.classList.add('active');
  } else {
    filterAdvanced.setAttribute('hidden', '');
    filterToggle.setAttribute('aria-expanded', 'false');
    filterToggle.classList.remove('active');
  }
});

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

/* ── Dashboard search → filters Discover cards ── */
(function() {
    const input = document.getElementById('dash-search-input');
    const grid  = document.getElementById('discover-grid');
    if (!input || !grid) return;

    function applyFilter(q) {
        const term = q.trim().toLowerCase();
        const cards = grid.querySelectorAll('.camp-card');
        let visible = 0;
        cards.forEach(card => {
            const title = (card.dataset.campTitle || '').toLowerCase();
            const cat   = (card.dataset.category || '').toLowerCase();
            const desc  = (card.querySelector('.camp-card-desc')?.textContent || '').toLowerCase();
            const match = !term || title.includes(term) || cat.includes(term) || desc.includes(term);
            card.style.display = match ? '' : 'none';
            if (match) visible++;
        });
        const empty = document.getElementById('discover-search-empty');
        if (visible === 0 && term && !empty) {
            const msg = document.createElement('p');
            msg.id = 'discover-search-empty';
            msg.className = 'discover-search-empty';
            msg.textContent = `No campaigns match "${q}". Try a different search.`;
            grid.parentElement.appendChild(msg);
        } else if (empty && (visible > 0 || !term)) {
            empty.remove();
        }
    }

    input.addEventListener('input', (e) => {
        // Jump to Discover when typing in search
        if (e.target.value.length > 0) {
            const discoverBtn = document.querySelector('[data-section="discover"]');
            if (discoverBtn && !document.getElementById('discover').classList.contains('active')) {
                discoverBtn.click();
            }
        }
        applyFilter(e.target.value);
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

document.querySelectorAll('.camp-donate, .urgent-donate').forEach(btn => {
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

/* ── Filter buttons — category (mutually exclusive) ── */
document.querySelectorAll('.campaign-filters .filter-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.campaign-filters .filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
  });
});

/* ── Filter buttons — urgency group ── */
document.querySelectorAll('.filter-group button').forEach(btn => {
  btn.addEventListener('click', () => {
    btn.closest('.filter-group').querySelectorAll('button').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
  });
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
const profileNameInput = document.getElementById('profile-name');
const profileBioInput  = document.getElementById('profile-bio');

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
