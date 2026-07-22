(() => {
    const shell = document.querySelector('[data-admin-shell]');
    if (!shell) {
        return;
    }

    const modal = document.querySelector('[data-admin-modal]');
    const modalCopy = document.querySelector('[data-modal-copy]');
    const confirmSubmit = document.querySelector('[data-confirm-submit]');
    const searchInput = document.querySelector('[data-admin-search]');
    const searchPanel = document.querySelector('[data-search-panel]');
    let pendingForm = null;
    let pendingSubmitter = null;

    const closeMenus = () => {
        document.querySelectorAll('.admin-menu:not([hidden])').forEach((menu) => {
            menu.hidden = true;
        });
    };

    const closeModal = () => {
        pendingForm = null;
        pendingSubmitter = null;
        if (modal) {
            modal.hidden = true;
        }
    };

    const closeSearch = () => {
        if (searchPanel) {
            searchPanel.hidden = true;
        }
    };

    /* The sidebar collapse handler went with its button. It shrank the column
       to an icon rail and hid every label; the state was also persisted, so an
       admin who collapsed it once got a rail of unlabelled icons on every
       later visit with no memory of why. */
    localStorage.removeItem('sawa-admin-sidebar');

    document.querySelectorAll('[data-mobile-menu]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.stopPropagation();
            shell.classList.toggle('is-mobile-open');
        });
    });

    document.querySelectorAll('[data-menu-toggle]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.stopPropagation();
            const menu = document.getElementById(button.dataset.menuToggle || '');
            if (!menu) {
                return;
            }
            const shouldOpen = menu.hidden;
            closeMenus();
            menu.hidden = !shouldOpen;
        });
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest('.admin-sidebar') && !event.target.closest('[data-mobile-menu]')) {
            shell.classList.remove('is-mobile-open');
        }
        if (!event.target.closest('.admin-profile-card')) {
            closeMenus();
        }
        if (!event.target.closest('.admin-search')) {
            closeSearch();
        }
    });

    /* Search the database, not the table that happens to be on screen.
       It used to hide rows of the current table, which meant typing on the
       Overview (no table) did nothing, and looking for a user while on
       Transactions found nothing however you spelled it. The panel under it
       held five fixed links that never changed with the query.

       Row filtering is kept as well, because narrowing the table you are
       already reading is genuinely useful — it is just no longer the whole of
       what the box does. */
    if (searchInput && searchPanel) {
        const base = document.body.dataset.adminBase || '';
        let seq = 0;
        let timer = null;

        const esc = (s) => String(s).replace(/[&<>"']/g, (c) => (
            { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
        ));

        const render = (html) => {
            searchPanel.innerHTML = html;
            searchPanel.hidden = false;
        };

        const run = async (term) => {
            // Every response carries the id of the request that asked for it,
            // so a slow early reply cannot overwrite the results of a later,
            // more specific query.
            const mine = ++seq;
            try {
                const res = await fetch(base + '/php/admin/search.php?q=' + encodeURIComponent(term), {
                    credentials: 'same-origin',
                });
                const data = await res.json();
                if (mine !== seq) return;
                if (!data.groups || !data.groups.length) {
                    render('<p class="admin-search-hint">No matches for &ldquo;' + esc(term) + '&rdquo;.</p>');
                    return;
                }
                render(data.groups.map((g) => (
                    '<strong>' + esc(g.label) + '</strong>'
                    + g.items.map((i) => (
                        '<a href="' + esc(i.href) + '"><span>' + esc(i.title) + '</span>'
                        + '<small>' + esc(i.meta) + '</small></a>'
                    )).join('')
                )).join(''));
            } catch (e) {
                if (mine !== seq) return;
                render('<p class="admin-search-hint">Search is unavailable right now.</p>');
            }
        };

        searchInput.addEventListener('focus', () => {
            searchPanel.hidden = false;
        });

        searchInput.addEventListener('input', () => {
            const term = searchInput.value.trim();

            document.querySelectorAll('[data-admin-table] tbody tr').forEach((row) => {
                row.hidden = term !== '' && !row.textContent.toLowerCase().includes(term.toLowerCase());
            });

            if (term.length < 2) {
                seq++;                      // cancel anything in flight
                render('<p class="admin-search-hint">Type at least 2 characters.</p>');
                return;
            }

            // Debounced: one request per pause in typing, not one per keystroke.
            clearTimeout(timer);
            timer = setTimeout(() => run(term), 220);
        });
    }

    /* Publish the topbar's real height.
       The drawer, its backdrop and the notification panel all hang off the
       bottom of the topbar, and below 768px the topbar wraps to two rows to
       give the search a full-width field — so its height is not a constant
       that can be written into the stylesheet. */
    const topbar = document.querySelector('.admin-topbar');
    if (topbar) {
        const publishHeight = () => {
            document.documentElement.style.setProperty(
                '--admin-topbar-h', Math.round(topbar.getBoundingClientRect().height) + 'px'
            );
        };
        publishHeight();
        // ResizeObserver rather than a resize listener: the bar also changes
        // height when the breadcrumb text wraps, which no window event reports.
        if (window.ResizeObserver) {
            new ResizeObserver(publishHeight).observe(topbar);
        } else {
            window.addEventListener('resize', publishHeight);
        }
    }

    /* Notification dropdown, matching the member dashboard's bell. */
    (() => {
        const wrap = document.querySelector('[data-notif-wrap]');
        const toggle = wrap && wrap.querySelector('[data-notif-toggle]');
        const panel = wrap && wrap.querySelector('[data-notif-panel]');
        if (!wrap || !toggle || !panel) return;

        const setOpen = (open) => {
            panel.hidden = !open;
            wrap.classList.toggle('is-open', open);
            toggle.setAttribute('aria-expanded', String(open));
        };

        toggle.addEventListener('click', (event) => {
            event.stopPropagation();
            setOpen(panel.hidden);
        });
        document.addEventListener('click', (event) => {
            if (!panel.hidden && !wrap.contains(event.target)) setOpen(false);
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !panel.hidden) {
                setOpen(false);
                toggle.focus();
            }
        });
    })();

    // Mobile search — the compact icon in the top bar toggles the full-width
    // search overlay <768px. Desktop still uses the inline form as before.
    const searchForm = document.querySelector('.admin-search');
    document.querySelectorAll('[data-search-open]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.stopPropagation();
            if (!searchForm) return;
            searchForm.classList.toggle('is-open');
            if (searchForm.classList.contains('is-open') && searchInput) {
                searchInput.focus();
            }
        });
    });
    document.addEventListener('click', (event) => {
        if (!searchForm) return;
        if (!searchForm.classList.contains('is-open')) return;
        if (event.target.closest('.admin-search') || event.target.closest('[data-search-open]')) return;
        searchForm.classList.remove('is-open');
    });

    document.querySelectorAll('[data-table-filter]').forEach((input) => {
        input.addEventListener('input', () => {
            const panel = input.closest('.admin-panel') || document;
            const term = input.value.trim().toLowerCase();
            panel.querySelectorAll('[data-admin-table] tbody tr').forEach((row) => {
                row.hidden = term !== '' && !row.textContent.toLowerCase().includes(term);
            });
        });
    });

    document.querySelectorAll('[data-clear-filter]').forEach((button) => {
        button.addEventListener('click', () => {
            const panel = button.closest('.admin-panel') || document;
            panel.querySelectorAll('[data-table-filter]').forEach((input) => {
                input.value = '';
            });
            panel.querySelectorAll('[data-admin-table] tbody tr').forEach((row) => {
                row.hidden = false;
            });
        });
    });

    document.querySelectorAll('[data-export-table]').forEach((button) => {
        button.addEventListener('click', () => {
            const table = document.querySelector('[data-admin-table]');
            if (!table) {
                return;
            }
            const csv = Array.from(table.querySelectorAll('tr')).map((row) => {
                return Array.from(row.children).map((cell) => {
                    const value = cell.textContent.trim().replace(/\s+/g, ' ').replace(/"/g, '""');
                    return `"${value}"`;
                }).join(',');
            }).join('\n');
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `sawa-admin-${new Date().toISOString().slice(0, 10)}.csv`;
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(link.href);
        });
    });

    document.querySelectorAll('form[data-confirm-action]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.dataset.confirmed === '1') {
                return;
            }
            event.preventDefault();
            pendingForm = form;
            pendingSubmitter = event.submitter || null;
            if (modalCopy && pendingSubmitter) {
                modalCopy.textContent = `Confirm ${pendingSubmitter.textContent.trim()} for this record. This updates the live SAWA database.`;
            }
            if (modal) {
                modal.hidden = false;
            }
        });
    });

    document.querySelectorAll('[data-close-modal]').forEach((button) => {
        button.addEventListener('click', closeModal);
    });

    if (confirmSubmit) {
        confirmSubmit.addEventListener('click', () => {
            if (!pendingForm) {
                closeModal();
                return;
            }
            pendingForm.dataset.confirmed = '1';
            if (pendingSubmitter && typeof pendingForm.requestSubmit === 'function') {
                pendingForm.requestSubmit(pendingSubmitter);
            } else {
                pendingForm.submit();
            }
        });
    }

    // Narrow screens stack each row as a card instead of scrolling a 96rem
    // table sideways (see .admin-table in css/admin.css). A stacked cell has no
    // column header above it any more, so copy each column's name onto its
    // cells and let CSS print it via ::before. Done here rather than in the PHP
    // because the four admin sections all render different column sets, and
    // reading them off <thead> stays correct whatever the section emits.
    document.querySelectorAll('table.admin-table').forEach((table) => {
        const headings = [...table.querySelectorAll('thead th')].map((th) => th.textContent.trim());
        if (!headings.length) {
            return;
        }
        table.querySelectorAll('tbody tr').forEach((row) => {
            [...row.cells].forEach((cell, i) => {
                const label = headings[i];
                // Skip the checkbox and actions columns: their headers are
                // blank or redundant once the controls are stacked.
                if (label) {
                    cell.dataset.label = label;
                }
            });
        });
    });

    document.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k' && searchInput) {
            event.preventDefault();
            searchInput.focus();
            if (searchPanel) {
                searchPanel.hidden = false;
            }
        }
        if (event.key === 'Escape') {
            closeMenus();
            closeModal();
            closeSearch();
            shell.classList.remove('is-mobile-open');
        }
    });
})();
