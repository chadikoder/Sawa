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

    document.querySelectorAll('.campaign-card').forEach(card => {
        card.style.display = (category === 'All' || card.dataset.category === category) ? '' : 'none';
    });

    document.querySelectorAll('.camp-section').forEach(section => {
        const cards = section.querySelectorAll('.campaign-card');
        const allHidden = cards.length > 0 && Array.from(cards).every(c => c.style.display === 'none');
        section.style.display = allHidden ? 'none' : '';
    });
}

// ==========================================
// DONATE POPOVER (sign-up required on home page)
// ==========================================
const popover = document.querySelector('.popover');

function showPopover() { popover?.classList.add('show'); }
function hidePopover() { popover?.classList.remove('show'); }

document.querySelectorAll('.campaign-donate').forEach(btn => {
    btn.addEventListener('click', showPopover);
});

document.querySelector('.popover-close')?.addEventListener('click', hidePopover);

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') hidePopover();
});

// ==========================================
// HERO CTA BUTTONS
// ==========================================
document.querySelector('.donor-btn')?.addEventListener('click', () => {
    document.getElementById('campaigns')?.scrollIntoView({ behavior: 'smooth' });
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
