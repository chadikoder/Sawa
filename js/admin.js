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

    if (localStorage.getItem('sawa-admin-sidebar') === 'collapsed') {
        shell.classList.add('is-collapsed');
    }

    document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            shell.classList.toggle('is-collapsed');
            localStorage.setItem(
                'sawa-admin-sidebar',
                shell.classList.contains('is-collapsed') ? 'collapsed' : 'expanded'
            );
        });
    });

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

    if (searchInput && searchPanel) {
        searchInput.addEventListener('focus', () => {
            searchPanel.hidden = false;
        });
        searchInput.addEventListener('input', () => {
            const term = searchInput.value.trim().toLowerCase();
            document.querySelectorAll('[data-admin-table] tbody tr').forEach((row) => {
                row.hidden = term !== '' && !row.textContent.toLowerCase().includes(term);
            });
            searchPanel.hidden = term !== '';
        });
    }

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
