// Hamburger nav (shared nav.css component)
const hamburger = document.getElementById('nav-hamburger');
const navLinks  = document.getElementById('nav-links');
const overlay   = document.getElementById('nav-mobile-overlay');

function openNav()  { navLinks?.classList.add('open'); overlay?.classList.add('show'); }
function closeNav() { navLinks?.classList.remove('open'); overlay?.classList.remove('show'); }

hamburger?.addEventListener('click', () => navLinks?.classList.contains('open') ? closeNav() : openNav());
overlay?.addEventListener('click', closeNav);
navLinks?.querySelectorAll('a').forEach(a => a.addEventListener('click', closeNav));

// Intersection Observer for scroll animations
const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
};

const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.style.opacity = '1';
        }
    });
}, observerOptions);

// Observe all animated elements
document.querySelectorAll('.animate-fade-in-up, .animate-slide-in-left, .animate-slide-in-right').forEach(el => {
    observer.observe(el);
});

// FAQ Accordion
document.querySelectorAll('.faq-question').forEach(btn => {
    btn.addEventListener('click', () => {
        const isOpen = btn.getAttribute('aria-expanded') === 'true';
        // Close all
        document.querySelectorAll('.faq-question').forEach(b => {
            b.setAttribute('aria-expanded', 'false');
            b.nextElementSibling?.setAttribute('hidden', '');
        });
        // Open clicked if it was closed
        if (!isOpen) {
            btn.setAttribute('aria-expanded', 'true');
            btn.nextElementSibling?.removeAttribute('hidden');
        }
    });
});

// Smooth scroll for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const targetId = this.getAttribute('href');
        const target = document.querySelector(targetId);
        if (target) {
            // Because we added 'scroll-padding-top' to CSS, this will scroll perfectly without hiding under the nav!
            target.scrollIntoView({ behavior: 'smooth' });
        }
    });
});
