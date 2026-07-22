/**
 * Turn every hamburger button into a burger/close toggle.
 *
 * Both menu buttons on the site were open-only: the icon stayed three bars
 * while the menu was open, so the obvious thing to press to close it did
 * nothing (userhome) or gave no sign it would (about-us). The only way back
 * was the backdrop or Escape.
 *
 * The icon is rebuilt here rather than in each page's markup so there is one
 * shape to maintain and both pages stay in step. The bars are elements, not an
 * SVG path, because a path cannot be transitioned into a cross — the top and
 * bottom bars rotate onto the middle one, which fades.
 *
 * State is read from the panel, never assumed from the click: the menus also
 * close on backdrop click, Escape, and link navigation, and an icon that
 * toggled on its own click would be showing an X over a closed menu after any
 * of those.
 */
(function () {
  const PAIRS = [
    // userhome.php — guest dropdown menu
    { button: '.site-header-burger', panel: '#guest-drawer', openClass: 'is-open' },
    // about-us.php — shared nav component
    { button: '.nav-hamburger', panel: '#nav-links', openClass: 'open' },
  ];

  PAIRS.forEach(function (pair) {
    const button = document.querySelector(pair.button);
    const panel = document.querySelector(pair.panel);
    if (!button || !panel) return;

    button.classList.add('burger-toggle');
    button.innerHTML =
      '<span class="burger-bars" aria-hidden="true"><i></i><i></i><i></i></span>';

    const sync = function () {
      const open = panel.classList.contains(pair.openClass);
      button.classList.toggle('is-open', open);
      button.setAttribute('aria-expanded', String(open));
      button.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
    };

    new MutationObserver(sync).observe(panel, {
      attributes: true,
      attributeFilter: ['class'],
    });
    sync();
  });
})();
