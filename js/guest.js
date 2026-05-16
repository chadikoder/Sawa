// ==========================================
// NAV LINK PRESS FEEDBACK — instant blue flash
// ==========================================
document.querySelectorAll('.nav-link, .mobile-bottom-nav a').forEach(link => {
    const add = () => link.classList.add('pressed');
    const rm = () => link.classList.remove('pressed');
    link.addEventListener('mousedown', add);
    link.addEventListener('mouseup', rm);
    link.addEventListener('mouseleave', rm);
    link.addEventListener('touchstart', add, {passive: true});
    link.addEventListener('touchend', rm, {passive: true});
    link.addEventListener('touchcancel', rm, {passive: true});
});

// ==========================================
// HAMBURGER MENU (mobile)
// ==========================================
const hamburgerBtn = document.getElementById('nav-hamburger');
const navLinksMenu = document.getElementById('nav-links');
const navOverlay   = document.getElementById('nav-mobile-overlay');

function openNav()  { navLinksMenu?.classList.add('open');    navOverlay?.classList.add('show'); }
function closeNav() { navLinksMenu?.classList.remove('open'); navOverlay?.classList.remove('show'); }

hamburgerBtn?.addEventListener('click', () => navLinksMenu?.classList.contains('open') ? closeNav() : openNav());
navOverlay?.addEventListener('click', closeNav);
navLinksMenu?.querySelectorAll('a').forEach(a => a.addEventListener('click', closeNav));

// ==========================================
// FILTER BUTTONS
// ==========================================
document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        btn.closest('.filter-group, .filter-row')?.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        filterCampaigns();
    });
});

function filterCampaigns() {
    const activeBtn = document.querySelector('.filter-row.categories .filter-btn.active');
    const category = activeBtn?.dataset.category || 'All';

    let visibleCount = 0;
    document.querySelectorAll('.campaign-card').forEach(card => {
        const match = category === 'All' || card.dataset.category === category;
        card.style.display = match ? '' : 'none';
        if (match) visibleCount++;
    });

    document.querySelectorAll('.camp-section').forEach(section => {
        const cards = section.querySelectorAll('.campaign-card');
        const allHidden = cards.length > 0 && Array.from(cards).every(c => c.style.display === 'none');
        section.style.display = allHidden ? 'none' : '';
    });

    // Update count badges on filter buttons
    document.querySelectorAll('.filter-btn').forEach(btn => {
        const cat = btn.dataset.category;
        let count;
        if (cat === 'All') {
            count = document.querySelectorAll('.campaign-card').length;
        } else {
            count = document.querySelectorAll('.campaign-card[data-category="' + cat + '"]').length;
        }
        let badge = btn.querySelector('.filter-count');
        if (!badge) {
            badge = document.createElement('span');
            badge.className = 'filter-count';
            btn.appendChild(badge);
        }
        badge.textContent = count;
    });

    // Toggle empty state
    const emptyState = document.getElementById('empty-state');
    if (emptyState) {
        emptyState.classList.toggle('show', visibleCount === 0);
    }
}

// Initialize counts on load
filterCampaigns();

// ==========================================
// GUEST DONATE MODAL
// ==========================================
const guestModal    = document.getElementById('guest-donate-modal');
const guestBackdrop = document.getElementById('guest-donate-backdrop');
const guestClose    = document.getElementById('guest-donate-close');

function openGuestModal(campTitle, campId) {
    if (!guestModal) return;
    document.getElementById('guest-donate-campaign').textContent = campTitle;
    document.getElementById('guest-camp-id').value = campId;
    // Reset form
    document.getElementById('guest-donate-form')?.reset();
    document.querySelectorAll('.guest-preset-btn').forEach(b => b.classList.remove('selected'));
    guestModal.classList.add('show');
    guestBackdrop.classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeGuestModal() {
    guestModal?.classList.remove('show');
    guestBackdrop?.classList.remove('show');
    document.body.style.overflow = '';
}

guestClose?.addEventListener('click', closeGuestModal);
guestBackdrop?.addEventListener('click', closeGuestModal);

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeGuestModal();
});

// Donate buttons on campaign cards
document.querySelectorAll('.campaign-donate').forEach(btn => {
    btn.addEventListener('click', () => {
        openGuestModal(btn.dataset.campTitle || 'this campaign', btn.dataset.campId || '');
    });
});

// Preset amount buttons inside modal
document.querySelectorAll('.guest-preset-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.guest-preset-btn').forEach(b => b.classList.remove('selected'));
        btn.classList.add('selected');
        const amountField = document.getElementById('guest-amount');
        if (amountField) amountField.value = btn.dataset.amount;
    });
});

// Clear preset selection on manual input
document.getElementById('guest-amount')?.addEventListener('input', () => {
    document.querySelectorAll('.guest-preset-btn').forEach(b => b.classList.remove('selected'));
});

// ==========================================
// GUEST DONATE FORM VALIDATION
// ==========================================
const guestForm = document.getElementById('guest-donate-form');
guestForm?.addEventListener('submit', function (e) {
    const amount = document.getElementById('guest-amount');
    const name = guestForm.querySelector('[name="donor_name"]');
    const email = guestForm.querySelector('[name="donor_email"]');
    let valid = true;

    [amount, name].forEach(f => {
        const err = f.parentElement.querySelector('.field-error');
        if (err) err.remove();
        f.style.borderColor = '';
    });
    if (email) {
        const err = email.parentElement.querySelector('.field-error');
        if (err) err.remove();
        email.style.borderColor = '';
    }

    if (!amount?.value || parseFloat(amount.value) < 1) {
        amount.style.borderColor = '#ef4444';
        showFieldError(amount, 'Please enter a valid amount (minimum $1)');
        valid = false;
    }

    if (!name?.value || name.value.trim().length < 2) {
        name.style.borderColor = '#ef4444';
        showFieldError(name, 'Please enter your full name');
        valid = false;
    }

    if (email?.value && email.value.trim() !== '') {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email.value.trim())) {
            email.style.borderColor = '#ef4444';
            showFieldError(email, 'Please enter a valid email address');
            valid = false;
        }
    }

    if (!valid) e.preventDefault();
});

function showFieldError(input, msg) {
    const err = document.createElement('span');
    err.className = 'field-error';
    err.style.cssText = 'color:#ef4444;font-size:1.1rem;display:block;margin:-0.4rem 0 0.6rem;';
    err.textContent = msg;
    input.parentElement.insertBefore(err, input.nextSibling);
}

// ==========================================
// HERO CTA BUTTONS
// ==========================================
document.querySelector('.donor-btn')?.addEventListener('click', () => {
    document.getElementById('campaigns')?.scrollIntoView({ behavior: 'smooth' });
});

document.querySelector('.taker-btn')?.addEventListener('click', () => {
    window.location.href = 'signup.html';
});

// ==========================================
// SLIDESHOW PREV/NEXT CONTROLS
// ==========================================
const slideshow = document.querySelector('.slideshow-container');
if (slideshow) {
    const slides = Array.from(slideshow.querySelectorAll('img'));
    let currentSlide = 0;
    let slideshowTimer;

    function showSlide(index) {
        slides.forEach(s => s.classList.remove('slide-active'));
        currentSlide = (index + slides.length) % slides.length;
        slides[currentSlide].classList.add('slide-active');
    }

    function startSlideAuto() {
        clearInterval(slideshowTimer);
        slideshowTimer = setInterval(() => showSlide(currentSlide + 1), 5000);
    }

    function activateManual() {
        if (!slideshow.classList.contains('manual')) {
            slideshow.classList.add('manual');
            showSlide(0);
        }
    }

    slideshow.querySelector('.slide-prev')?.addEventListener('click', () => {
        activateManual();
        showSlide(currentSlide - 1);
        startSlideAuto();
    });

    slideshow.querySelector('.slide-next')?.addEventListener('click', () => {
        activateManual();
        showSlide(currentSlide + 1);
        startSlideAuto();
    });
}
