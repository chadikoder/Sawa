window.addEventListener('load', () => {
    const loader = document.querySelector('#loader-wrapper');
    setTimeout(() => loader.classList.add('loader-hidden'), 1200);
    setTimeout(() => { window.location.href = 'userhome.html'; }, 1600);
});