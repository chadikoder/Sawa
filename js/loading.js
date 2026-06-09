window.addEventListener('load', () => {
    const loader = document.querySelector('#loader-wrapper');
    // PHP can override the destination with ?next=… (relative path only, no protocol)
    const raw = new URLSearchParams(window.location.search).get('next');
    const safe = raw && /^[A-Za-z0-9._\-/?=&]+$/.test(raw) ? raw : 'userhome.php';
    setTimeout(() => loader.classList.add('loader-hidden'), 1200);
    setTimeout(() => { window.location.href = safe; }, 1600);
});