import './bootstrap';

// NOTE: Do NOT manually import/start Alpine.js here.
// Livewire (v3/v4) already bundles and auto-starts its own Alpine instance
// via the @livewireScripts directive in resources/views/admin/layouts/app.blade.php.
// Starting a second Alpine instance on top of it causes two independent
// reactive runtimes to fight over the same DOM: click handlers fire against
// one instance while x-bind/:action bindings are read from the other, which
// is exactly what produced the "bulk toggle does nothing" and
// "delete confirmation not submitting" bugs on the Colleges page.

// Livewire's navigate API gives the admin area SPA-style page transitions while
// keeping the browser history and server-rendered Blade responses intact.  This
// delegated fallback also covers existing links/forms that do not yet have an
// explicit `wire:navigate` attribute.
(function setupAdminSpaNavigation() {
    if (window.__adminSpaNavigationReady) return;
    window.__adminSpaNavigationReady = true;

    const isLocalHost = (host) => ['localhost', '127.0.0.1', '::1'].includes(host);
    const canNavigate = () => window.Livewire && typeof window.Livewire.navigate === 'function';
    const currentBasePath = () => {
        const index = window.location.pathname.indexOf('/admin');
        return index >= 0 ? window.location.pathname.slice(0, index) : '';
    };
    const localNavigatePath = (url) => {
        const adminIndex = url.pathname.indexOf('/admin');
        const pathname = adminIndex >= 0
            ? `${currentBasePath()}${url.pathname.slice(adminIndex)}`
            : url.pathname;

        return `${pathname}${url.search}${url.hash}`;
    };
    window.adminLocalNavigatePath = localNavigatePath;
    // Build admin API/form URLs from the URL currently open in the browser.
    // This keeps AJAX actions working when the same build is served from
    // Laravel's root, /public, or an XAMPP subdirectory even if APP_URL still
    // points at a different local host/port.
    window.adminBasePath = currentBasePath();

    // Keep the active theme visually stable while Livewire replaces the
    // current page.  The incoming body can momentarily be detached before
    // its classes are applied; disabling transitions for that short window
    // prevents a white background flash in dark mode.
    const syncThemeBeforeNavigationPaint = () => {
        if (typeof window.getSavedTheme === 'function' && typeof window.setTheme === 'function') {
            window.setTheme(window.getSavedTheme());
        }
    };
    const navigationUsesDarkTheme = () => {
        const mode = typeof window.getSavedTheme === 'function'
            ? window.getSavedTheme()
            : (window.localStorage.getItem('theme') || 'system');

        return mode === 'dark' || (
            mode === 'system' &&
            window.matchMedia('(prefers-color-scheme: dark)').matches
        );
    };
    let navigationEndFrame = 0;
    let navigationEndTimer = 0;
    let navigationCanvasFrame = 0;
    let navigationPaintLocked = false;
    const cancelNavigationCanvasLock = () => {
        if (navigationCanvasFrame && typeof window.cancelAnimationFrame === 'function') {
            window.cancelAnimationFrame(navigationCanvasFrame);
        }
        navigationCanvasFrame = 0;
    };
    const cancelNavigationRelease = () => {
        if (navigationEndFrame && typeof window.cancelAnimationFrame === 'function') {
            window.cancelAnimationFrame(navigationEndFrame);
        }
        navigationEndFrame = 0;
        window.clearTimeout(navigationEndTimer);
        navigationEndTimer = 0;
    };
    const markNavigationStart = () => {
        // A second filter/navigation can start before the previous two-frame
        // release completes. Cancel that release or it can remove the guard
        // in the middle of the newer navigation and reintroduce the flash.
        cancelNavigationRelease();
        cancelNavigationCanvasLock();
        navigationPaintLocked = true;
        syncThemeBeforeNavigationPaint();
        document.documentElement.classList.add('is-navigating');

        // Keep repainting the persisted chrome until Livewire has finished
        // morphing the filtered page. This covers browsers that briefly apply
        // the incoming light-mode body/sidebar styles between two animation
        // frames, even though the `dark` class is already present.
        const lockCanvas = () => {
            const root = document.documentElement;
            // Never infer the theme from the DOM during a Livewire swap.
            // The incoming document can temporarily remove both the `dark`
            // class and data attribute. Reading that transient state caused
            // this loop itself to paint the active sidebar item light.
            const dark = navigationUsesDarkTheme();
            root.classList.toggle('dark', dark);
            root.classList.add('is-navigating');
            root.dataset.pmamsTheme = dark ? 'dark' : 'light';
            root.style.backgroundColor = dark ? '#0f172a' : '#f5f5f4';
            root.style.colorScheme = dark ? 'dark' : 'light';
            root.style.setProperty('--pmams-sidebar-bg', dark ? '#1f2937' : '#fafaf9');
            root.style.setProperty('--pmams-sidebar-border', dark ? '#374151' : '#d1d5db');
            root.style.setProperty('--pmams-sidebar-active-bg', dark ? 'rgba(30, 58, 138, 0.3)' : '#eff6ff');
            root.style.setProperty('--pmams-sidebar-active-text', dark ? '#60a5fa' : '#1d4ed8');
            root.style.setProperty('--pmams-sidebar-active-icon', dark ? '#60a5fa' : '#2563eb');
            root.style.setProperty('--pmams-sidebar-link-text', dark ? '#d1d5db' : '#374151');
            root.style.setProperty('--pmams-sidebar-link-icon', dark ? '#9ca3af' : '#6b7280');
            root.style.setProperty('--pmams-sidebar-link-hover-bg', dark ? '#374151' : '#f3f4f6');
            root.style.setProperty('--pmams-sidebar-link-hover-text', dark ? '#f3f4f6' : '#1f2937');
            root.style.setProperty('--pmams-sidebar-scrollbar-thumb', dark ? '#6b7280' : '#a8a29e');
            if (document.body) {
                document.body.style.backgroundColor = dark ? '#0f172a' : '#f5f5f4';
            }
            const sidebar = document.querySelector('[data-admin-sidebar]');
            if (sidebar) {
                sidebar.dataset.theme = dark ? 'dark' : 'light';
                sidebar.style.backgroundColor = dark ? '#1f2937' : '#fafaf9';
                sidebar.style.borderRightColor = dark ? '#374151' : '#d1d5db';
                sidebar.querySelectorAll('nav a[data-active]').forEach((link) => {
                    const active = link.dataset.active === 'true';
                    link.style.backgroundColor = active
                        ? (dark ? 'rgba(30, 58, 138, 0.3)' : '#eff6ff')
                        : 'transparent';
                    link.style.color = active
                        ? (dark ? '#60a5fa' : '#1d4ed8')
                        : (dark ? '#d1d5db' : '#374151');

                    const icon = link.querySelector('svg');
                    if (icon) {
                        icon.style.color = active
                            ? (dark ? '#60a5fa' : '#2563eb')
                            : (dark ? '#9ca3af' : '#6b7280');
                    }
                });
            }
            if (navigationPaintLocked && typeof window.requestAnimationFrame === 'function') {
                navigationCanvasFrame = window.requestAnimationFrame(lockCanvas);
            } else {
                navigationCanvasFrame = 0;
            }
        };
        lockCanvas();
    };
    // Expose the same early guard for inline controls and other page scripts.
    // Native mobile select menus can paint one frame after `change` but before
    // the form's submit event, so callers need a way to lock the theme canvas
    // before requesting the filtered page.
    window.prepareAdminNavigation = markNavigationStart;
    const markNavigationEnd = () => {
        // Livewire swaps the body before emitting `navigated`. Re-apply the
        // saved theme before removing the no-transition guard so a filtered
        // result cannot paint once with the default light body background.
        //
        // Keep the guard for two animation frames. The navigation-state
        // listener runs after this listener and closes the persisted mobile
        // sidebar in the same `livewire:navigated` turn. Removing the guard
        // immediately lets the sidebar's transform transition run for one
        // frame, which is the white flash users see in dark mode.
        syncThemeBeforeNavigationPaint();
        cancelNavigationRelease();

        const release = () => {
            navigationPaintLocked = false;
            cancelNavigationCanvasLock();
            syncThemeBeforeNavigationPaint();
            document.documentElement.classList.remove('is-navigating');
        };

        if (typeof window.requestAnimationFrame === 'function') {
            navigationEndFrame = window.requestAnimationFrame(() => {
                navigationEndFrame = window.requestAnimationFrame(() => {
                    navigationEndFrame = 0;
                    release();
                });
            });
        } else {
            navigationEndTimer = window.setTimeout(release, 50);
        }
    };
    document.addEventListener('livewire:navigate', markNavigationStart);
    document.addEventListener('livewire:navigating', markNavigationStart);
    document.addEventListener('livewire:navigated', markNavigationEnd);
    window.addEventListener('pageshow', markNavigationEnd);

    // Filter forms are submitted by requestSubmit() as the user changes a
    // search field or select. Capture that submit before the delegated SPA
    // handler below so the no-transition guard is active before the incoming
    // page controls are painted. This prevents a dark-mode input from briefly
    // showing its light (white) background during the filter request.
    const isFilterForm = (form) => Boolean(form.querySelector(
        'input[type="search"], input[name="q"], input[name*="date"], input[name*="filter"], select'
    ));
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || form.method.toUpperCase() !== 'GET' ||
            form.hasAttribute('wire:navigate') || form.dataset.noSpa === 'true' || !isFilterForm(form)) {
            return;
        }

        markNavigationStart();
    }, true);

    // A select's native popup closes before submit is dispatched. Start the
    // guard during capture-phase change handling so the persisted sidebar and
    // document canvas cannot fall back to the light palette in that gap.
    document.addEventListener('change', (event) => {
        const control = event.target;
        const form = control && control.form;
        if (!(form instanceof HTMLFormElement) || form.method.toUpperCase() !== 'GET' ||
            form.hasAttribute('wire:navigate') || form.dataset.noSpa === 'true' || !isFilterForm(form)) {
            return;
        }

        markNavigationStart();
    }, true);

    // Restore explicit page anchors after Livewire swaps the document. Native
    // browser navigation handles the same hash on a full reload, while this
    // listener keeps SPA filter/pagination requests at the requested section.
    const restoreHashTarget = () => {
        const rawHash = window.location.hash.slice(1);
        if (!rawHash) return;

        let id = rawHash;
        try { id = decodeURIComponent(rawHash); } catch (error) { /* keep the raw id */ }

        const target = document.getElementById(id);
        if (!target) return;

        const scroll = () => target.scrollIntoView({ behavior: 'auto', block: 'start' });
        if (typeof window.requestAnimationFrame === 'function') {
            window.requestAnimationFrame(scroll);
        } else {
            window.setTimeout(scroll, 0);
        }
    };

    document.addEventListener('livewire:navigated', () => {
        const url = new URL(window.location.href);
        if (url.searchParams.has('_spa_refresh')) {
            url.searchParams.delete('_spa_refresh');
            window.history.replaceState(window.history.state, '', url.pathname + url.search + url.hash);
        }
        restoreHashTarget();
    });
    window.addEventListener('hashchange', restoreHashTarget);
    window.addEventListener('pageshow', restoreHashTarget);
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', restoreHashTarget, { once: true });
    } else {
        restoreHashTarget();
    }

    document.addEventListener('click', (event) => {
        if (!canNavigate() || event.defaultPrevented || event.button !== 0) return;
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

        const link = event.target.closest('a[href]');
        if (!link || link.hasAttribute('download') || link.target === '_blank' ||
            link.hasAttribute('wire:navigate') || link.dataset.noSpa === 'true') return;

        const url = new URL(link.href, window.location.href);
        const sameOrigin = url.origin === window.location.origin ||
            (isLocalHost(url.hostname) && isLocalHost(window.location.hostname));
        const path = localNavigatePath(url);
        if (!sameOrigin || url.protocol !== window.location.protocol ||
            (path === `${window.location.pathname}${window.location.search}` && !url.hash)) return;

        event.preventDefault();
        window.Livewire.navigate(path);
    });

    document.addEventListener('submit', (event) => {
        if (!canNavigate() || event.defaultPrevented) return;
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || form.method.toUpperCase() !== 'GET' ||
            form.hasAttribute('wire:navigate') || form.dataset.noSpa === 'true') return;

        // Read the attribute instead of the form.action property. A control
        // named "action" (such as the activity-log filter) shadows the
        // native HTMLFormElement.action property and would otherwise produce
        // a URL like /admin/[object%20HTMLSelectElement].
        const action = new URL(form.getAttribute('action') || window.location.href, window.location.href);
        const sameOrigin = action.origin === window.location.origin ||
            (isLocalHost(action.hostname) && isLocalHost(window.location.hostname));
        if (!sameOrigin) return;

        event.preventDefault();
        const params = new URLSearchParams(new FormData(form));
        const query = params.toString();
        const path = localNavigatePath(action).split('#')[0].split('?')[0];
        const hash = action.hash || (form.dataset.preserveHash === 'true' ? window.location.hash : '');
        window.Livewire.navigate(`${path}${query ? `?${query}` : ''}${hash}`);
    });
})();

// Keep an unfinished maintenance checklist intact while an admin moves
// through the SPA (for example, when linking/editing a peripheral) or reloads
// the browser tab. The checklist page also exposes Alpine restore methods; the
// persistent listener below is the fallback for navigations where the page's
// ordinary script/x-init is not evaluated again.
(function setupMaintenanceChecklistState() {
    if (window.__pmamsChecklistStateReady) return;
    window.__pmamsChecklistStateReady = true;

    const formSelector = '#maintenance-checklist-form';
    let restoring = false;
    let skipPageSave = false;

    const getStorage = () => {
        try { return window.sessionStorage; } catch (error) { return null; }
    };

    const getKey = () => `pmams-checklist-state:${window.location.pathname}`;
    const getForm = () => document.querySelector(formSelector);

    const serializeForm = (form) => ({
        version: 2,
        fields: Array.from(form.elements || [])
            .filter((control) => control.name
                && control.type !== 'file'
                && !['_token', '_method'].includes(control.name))
            .map((control) => ({
                name: control.name,
                type: control.type || control.tagName?.toLowerCase(),
                value: control.value ?? '',
                checked: control.type === 'radio' || control.type === 'checkbox'
                    ? control.checked
                    : undefined,
            })),
    });

    const save = () => {
        if (restoring) return;

        const form = getForm();
        const storage = getStorage();
        if (!form || !storage) return;

        try {
            storage.setItem(getKey(), JSON.stringify(serializeForm(form)));
        } catch (error) {
            // Session storage can be unavailable in private/restricted browsers.
        }
    };

    const clear = () => {
        const storage = getStorage();
        if (!storage) return;

        try { storage.removeItem(getKey()); } catch (error) { /* best effort */ }
    };

    const read = () => {
        const storage = getStorage();
        if (!storage) return null;

        try {
            const stored = JSON.parse(storage.getItem(getKey()) || 'null');
            return stored?.fields ? stored : null;
        } catch (error) {
            return null;
        }
    };

    const findChecklistData = (form) => {
        const stack = form?._x_dataStack || [];
        return stack.find((data) => typeof data?.restoreChecklistState === 'function') || null;
    };

    const restoreControlsDirectly = (form, stored) => {
        const controls = Array.from(form.elements || []);

        controls.forEach((control) => {
            const isChoice = control.type === 'radio' || control.type === 'checkbox';
            const saved = stored.fields.find((candidate) => candidate.name === control.name
                && (!isChoice
                    || (candidate.type === control.type
                        && String(candidate.value ?? '') === String(control.value ?? ''))));

            if (!saved) return;

            if (isChoice) {
                control.checked = Boolean(saved.checked);
            } else if (typeof saved.value === 'string') {
                control.value = saved.value;
            }
        });

        // Re-run Alpine's normal change handlers so dependent condition and
        // disposition fields match the restored hardware result.
        controls.forEach((control) => {
            if (!control.name || control.name === '_token' || control.name === '_method') return;

            if (control.type === 'radio' || control.type === 'checkbox') {
                if (control.checked) control.dispatchEvent(new Event('change', { bubbles: true }));
            } else if (['date', 'text', 'textarea', 'search'].includes(control.type || control.tagName?.toLowerCase())) {
                control.dispatchEvent(new Event('input', { bubbles: true }));
                control.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    };

    const restore = () => {
        const form = getForm();
        const stored = read();
        if (!form || !stored) return;

        restoring = true;
        try {
            const data = findChecklistData(form);
            if (data) {
                data.restoreChecklistState();
                data.applyChecklistDefaults?.();
                data.refreshChecklistState?.();
            } else {
                restoreControlsDirectly(form, stored);
            }
        } catch (error) {
            // A page-level Alpine tree may still be initializing. The direct
            // control restore keeps the selected values available in either
            // case, and the normal page x-init will rebuild dependent state.
            try { restoreControlsDirectly(form, stored); } catch (ignored) { /* best effort */ }
        } finally {
            restoring = false;
            clear();
        }
    };

    const restoreWhenReady = (attempt = 0) => {
        if (!getForm()) return;

        if (findChecklistData(getForm()) || attempt >= 40) {
            restore();
            return;
        }

        window.setTimeout(() => restoreWhenReady(attempt + 1), 25);
    };

    document.addEventListener('input', (event) => {
        if (event.target?.closest?.(formSelector)) save();
    }, true);

    document.addEventListener('change', (event) => {
        if (event.target?.closest?.(formSelector)) save();
    }, true);

    document.addEventListener('submit', (event) => {
        if (event.target?.id !== 'maintenance-checklist-form') return;

        // A real checklist submission is now handled by the server. Do not
        // restore its previous draft on the success/validation response.
        skipPageSave = true;
        clear();
    }, true);

    // The unlink action uses a native form submit from an inline handler, so
    // capture the click before that handler leaves the page.
    document.addEventListener('click', (event) => {
        if (event.target?.closest?.('#maintenance-checklist-form [data-unlink-url]')) save();
    }, true);

    document.addEventListener('livewire:navigating', () => {
        if (!skipPageSave) save();
    });

    document.addEventListener('livewire:navigated', () => {
        skipPageSave = false;
        window.setTimeout(restoreWhenReady, 0);
    });

    window.addEventListener('pagehide', () => {
        if (!skipPageSave) save();
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => restoreWhenReady(), { once: true });
    } else {
        restoreWhenReady();
    }
})();

// Keep the maintenance checklist page at the last vertical scroll position
// when the browser reloads it or Livewire swaps the page through SPA
// navigation. The offset is scoped to the checklist route and tab, so it
// cannot leak between equipment records or browser sessions.
(function setupMaintenanceChecklistScrollState() {
    if (window.__pmamsChecklistScrollReady) return;
    window.__pmamsChecklistScrollReady = true;

    const formSelector = '#maintenance-checklist-form';
    let restoring = false;
    let skipRestore = false;
    let navigationStarted = false;
    let saveTimer = 0;

    const isChecklistPath = () => /\/admin\/devices\/[^/]+\/maintenance-checklist\/?$/.test(window.location.pathname);
    const isChecklistPage = () => Boolean(document.querySelector(formSelector)) && isChecklistPath();
    let lastChecklistPath = isChecklistPath() ? window.location.pathname : null;

    const getStorage = () => {
        try { return window.sessionStorage; } catch (error) { return null; }
    };

    const rememberChecklistPath = () => {
        if (isChecklistPath()) lastChecklistPath = window.location.pathname;
    };

    const getKey = () => `pmams-checklist-scroll:${lastChecklistPath || window.location.pathname}`;

    const save = () => {
        rememberChecklistPath();

        const form = document.querySelector(formSelector);
        if (restoring || !form || !lastChecklistPath) return;

        const storage = getStorage();
        if (!storage) return;

        const offset = Math.max(0, Number(window.scrollY
            ?? document.scrollingElement?.scrollTop
            ?? 0));

        try { storage.setItem(getKey(), String(Number.isFinite(offset) ? offset : 0)); }
        catch (error) { /* session storage can be unavailable in restricted browsers */ }
    };

    const scheduleSave = () => {
        if (saveTimer || restoring || navigationStarted) return;

        saveTimer = window.setTimeout(() => {
            saveTimer = 0;
            save();
        }, 100);
    };

    const cancelScheduledSave = () => {
        if (!saveTimer) return;

        window.clearTimeout(saveTimer);
        saveTimer = 0;
    };

    const prepareForNavigation = () => {
        if (navigationStarted) return;

        // Capture the old page before Livewire/browser navigation resets its
        // scroll position, then ignore the transition's synthetic scroll-to-
        // top event so it cannot overwrite the saved offset with zero.
        save();
        cancelScheduledSave();
        navigationStarted = true;
    };

    const clear = () => {
        const storage = getStorage();
        if (!storage) return;

        try { storage.removeItem(getKey()); } catch (error) { /* best effort */ }
    };

    const read = () => {
        const storage = getStorage();
        if (!storage) return null;

        try {
            const rawValue = storage.getItem(getKey());
            if (rawValue === null) return null;

            const value = Number(rawValue);
            return Number.isFinite(value) && value >= 0 ? value : null;
        } catch (error) {
            return null;
        }
    };

    const restore = () => {
        rememberChecklistPath();
        if (skipRestore || window.location.hash || !isChecklistPage()) return;

        const offset = read();
        if (offset === null) return;

        const apply = () => {
            if (skipRestore || window.location.hash || !isChecklistPage()) return;

            restoring = true;
            try {
                const maxOffset = Math.max(
                    0,
                    document.documentElement.scrollHeight - window.innerHeight,
                );
                window.scrollTo({ top: Math.min(offset, maxOffset), left: 0, behavior: 'auto' });
            } finally {
                restoring = false;
            }
        };

        // Run once after the incoming page is painted and once shortly after
        // that in case Alpine/Livewire expands a dependent checklist row.
        if (typeof window.requestAnimationFrame === 'function') {
            window.requestAnimationFrame(() => {
                apply();
                window.setTimeout(apply, 80);
            });
        } else {
            window.setTimeout(apply, 0);
        }
    };

    const restoreWhenReady = (attempt = 0) => {
        if (skipRestore || window.location.hash || !isChecklistPath()) {
            return;
        }

        if (document.querySelector(formSelector) || attempt >= 40) {
            restore();
            return;
        }

        window.setTimeout(() => restoreWhenReady(attempt + 1), 25);
    };

    window.addEventListener('scroll', scheduleSave, { passive: true });

    document.addEventListener('submit', (event) => {
        if (event.target?.id !== 'maintenance-checklist-form') return;

        // A completed submission redirects to another page (or a new plan
        // state); do not jump that response back to an unfinished offset.
        skipRestore = true;
        clear();
    }, true);

    document.addEventListener('livewire:navigating', () => {
        if (!skipRestore) prepareForNavigation();
    });

    // `livewire:navigate` fires before Livewire prepares the transition. Save
    // here as well because some versions reset the old document's scroll
    // offset before emitting the later `navigating` hook.
    document.addEventListener('livewire:navigate', () => {
        if (!skipRestore) prepareForNavigation();
    });

    document.addEventListener('livewire:navigated', () => {
        navigationStarted = false;
        skipRestore = false;
        if (!isChecklistPath()) lastChecklistPath = null;
        restoreWhenReady();
        // Some Livewire responses emit `navigated` before the page component
        // has finished inserting its form. A delayed retry covers that short
        // gap without polling while the user is on another route.
        window.setTimeout(restoreWhenReady, 250);
    });

    window.addEventListener('pagehide', () => {
        if (!skipRestore) save();
    });

    window.addEventListener('pageshow', () => {
        navigationStarted = false;
        skipRestore = false;
        restoreWhenReady();
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', restoreWhenReady, { once: true });
    } else {
        restoreWhenReady();
    }
})();

// Keep unfinished Add and Edit Equipment forms through a browser reload, while
// discarding them when the user leaves the page or explicitly closes/cancels
// the form. Session storage is tab-scoped. File inputs, CSRF fields, and
// navigation fields are intentionally excluded; the server remains the source
// of truth after a real submission.
(function setupEquipmentDraftState() {
    if (window.__pmamsEquipmentDraftReady) return;
    window.__pmamsEquipmentDraftReady = true;

    const formEntries = [
        {
            key: 'add',
            selector: 'form[data-equipment-add-form]',
            storageKey: 'pmams.equipment.add-draft',
        },
        {
            key: 'edit',
            selector: 'form[data-equipment-edit-form]',
            storageKey: 'pmams.equipment.edit-draft',
        },
    ];
    const formSelector = formEntries.map(({ selector }) => selector).join(', ');
    let restoring = false;
    let skipPageSave = false;
    let discardOnNavigation = false;
    let saveTimer = null;

    const getStorage = () => {
        try { return window.sessionStorage; } catch (error) { return null; }
    };

    const pageKey = () => `${window.location.pathname}${window.location.search}`;
    const isExcluded = (control) => !control.name
        || control.type === 'file'
        || ['_token', '_method', 'form_context', 'return_to'].includes(control.name);

    const isFormOpen = (form) => {
        const modal = form?.closest('[role="dialog"]');
        if (!modal) return true;

        return modal.dataset.nativeOpen === 'true'
            || window.getComputedStyle(modal).display !== 'none';
    };

    const getForm = (entry) => entry ? document.querySelector(entry.selector) : null;
    const getEntry = (form) => formEntries.find((entry) => form?.matches?.(entry.selector)) || null;
    const getEntryForModalId = (id) => {
        if (/edit-(?:equipment|device)-modal$/.test(id)) {
            return formEntries.find((entry) => entry.key === 'edit') || null;
        }

        if (/(?:dashboard-)?add-equipment-modal$/.test(id)) {
            return formEntries.find((entry) => entry.key === 'add') || null;
        }

        return null;
    };

    const serializeForm = (form, entry) => {
        const occurrences = new Map();
        const fields = Array.from(form.elements || [])
            .filter((control) => !isExcluded(control))
            .map((control) => {
                const ordinal = occurrences.get(control.name) || 0;
                occurrences.set(control.name, ordinal + 1);

                return {
                    name: control.name,
                    ordinal,
                    type: control.type || control.tagName?.toLowerCase(),
                    value: control.value ?? '',
                    checked: control.type === 'radio' || control.type === 'checkbox'
                        ? control.checked
                        : undefined,
                };
            });

        return {
            version: 1,
            kind: entry.key,
            page: pageKey(),
            fields,
        };
    };

    const read = (entry) => {
        const storage = getStorage();
        if (!storage) return null;

        try {
            const draft = JSON.parse(storage.getItem(entry.storageKey) || 'null');
            return draft?.version === 1 && Array.isArray(draft.fields) ? draft : null;
        } catch (error) {
            return null;
        }
    };

    const clear = (entry) => {
        const storage = getStorage();
        if (!storage || !entry) return;

        try { storage.removeItem(entry.storageKey); } catch (error) { /* best effort */ }
    };

    const activeForm = () => formEntries
        .map((entry) => ({ entry, form: getForm(entry) }))
        .find(({ form }) => form && isFormOpen(form)) || null;

    const save = (form = null) => {
        if (restoring || skipPageSave || discardOnNavigation) return;

        const active = form
            ? { entry: getEntry(form), form }
            : activeForm();
        const entry = active?.entry;
        const targetForm = active?.form;
        const storage = getStorage();
        if (!entry || !targetForm || !storage || !isFormOpen(targetForm)) return;

        try {
            storage.setItem(entry.storageKey, JSON.stringify(serializeForm(targetForm, entry)));
        } catch (error) {
            // Session storage can be unavailable in private/restricted browsers.
        }
    };

    const flushSave = (form = null) => {
        if (saveTimer) {
            window.clearTimeout(saveTimer);
            saveTimer = null;
        }
        save(form);
    };

    const queueSave = (form) => {
        if (!form || !isFormOpen(form)) return;
        // A cancel/close can leave the page in place (for example when the
        // modal is reopened). A new edit/add interaction starts a fresh draft
        // lifecycle without carrying over the previous discard guard.
        discardOnNavigation = false;
        if (saveTimer) window.clearTimeout(saveTimer);
        saveTimer = window.setTimeout(() => {
            saveTimer = null;
            save(form);
        }, 0);
    };

    const controlsByName = (form) => {
        const controls = new Map();

        Array.from(form.elements || []).forEach((control) => {
            if (isExcluded(control)) return;
            const list = controls.get(control.name) || [];
            list.push(control);
            controls.set(control.name, list);
        });

        return controls;
    };

    const restoreControls = (form, draft) => {
        const controls = controlsByName(form);
        const restored = [];

        draft.fields.forEach((saved) => {
            const candidates = controls.get(saved.name) || [];
            const control = candidates[saved.ordinal ?? 0];
            if (!control) return;

            const isChoice = control.type === 'radio' || control.type === 'checkbox';
            if (isChoice) {
                control.checked = Boolean(saved.checked);
            } else if (typeof saved.value === 'string') {
                control.value = saved.value;
            }

            restored.push({ control, saved });
        });

        const dispatch = (control) => {
            if (control.matches('[data-location-deployed-search], [data-location-deployed-id], [data-office-deployed-id]')) {
                return;
            }

            if (control.type === 'radio' || control.type === 'checkbox') {
                control.dispatchEvent(new Event('change', { bubbles: true }));
                return;
            }

            control.dispatchEvent(new Event('input', { bubbles: true }));
            control.dispatchEvent(new Event('change', { bubbles: true }));
        };

        // Update the Alpine type/condition state first so dependent fields are
        // enabled before their restored values are processed.
        const typeField = restored.find(({ control }) => control.name === 'device_type_id');
        if (typeField) dispatch(typeField.control);
        restored.forEach(({ control }) => {
            if (control !== typeField?.control) dispatch(control);
        });

        // Location lookup intentionally clears its hidden IDs when the text is
        // changed without a selected suggestion. Restore those references after
        // dispatching the other controls.
        restored
            .filter(({ control }) => control.matches('[data-location-deployed-id], [data-office-deployed-id]'))
            .forEach(({ control, saved }) => {
                control.value = saved.value ?? '';
            });
    };

    const openRestoredForm = (form, entry) => {
        const root = form.closest('[x-data]');
        const stack = root?._x_dataStack || [];
        const state = stack.find((candidate) => {
            if (!candidate) return false;

            if (entry.key === 'edit') {
                return Object.prototype.hasOwnProperty.call(candidate, 'editOpen');
            }

            return Object.prototype.hasOwnProperty.call(candidate, 'addOpen')
                || Object.prototype.hasOwnProperty.call(candidate, 'addDeviceOpen');
        });

        if (state) {
            if (entry.key === 'edit' && Object.prototype.hasOwnProperty.call(state, 'editOpen')) {
                state.editOpen = true;
            }
            if (entry.key === 'add') {
                if (Object.prototype.hasOwnProperty.call(state, 'addOpen')) state.addOpen = true;
                if (Object.prototype.hasOwnProperty.call(state, 'addDeviceOpen')) state.addDeviceOpen = true;
            }
        }

        const modal = form.closest('[role="dialog"]');
        if (modal?.id) window.pmamsOpenModal?.(modal.id);
    };

    const restore = () => {
        const drafts = formEntries
            .map((entry) => ({ entry, draft: read(entry) }))
            .filter(({ draft }) => draft);
        if (!drafts.length) return;

        restoring = true;
        try {
            drafts.forEach(({ entry, draft }) => {
                const form = getForm(entry);
                if (!form || draft.page !== pageKey()) {
                    clear(entry);
                    return;
                }

                try {
                    restoreControls(form, draft);
                    openRestoredForm(form, entry);
                } catch (error) {
                    // A partially initialized Alpine tree should not prevent
                    // basic control restoration; the next navigation/reload
                    // can retry.
                    try {
                        restoreControls(form, draft);
                        openRestoredForm(form, entry);
                    } catch (ignored) { /* best effort */ }
                } finally {
                    clear(entry);
                }
            });
        } finally {
            restoring = false;
        }
    };

    const restoreWhenReady = (attempt = 0) => {
        const drafts = formEntries.filter((entry) => read(entry));
        if (!drafts.length) return;

        const alpinePending = drafts.some((entry) => {
            const form = getForm(entry);
            const root = form?.closest('[x-data]');
            return !form || (root && window.Alpine && !root._x_dataStack?.length);
        });

        if (alpinePending && attempt < 40) {
            window.setTimeout(() => restoreWhenReady(attempt + 1), 25);
            return;
        }

        if (attempt >= 40) {
            drafts.forEach((entry) => {
                if (!getForm(entry)) clear(entry);
            });
        }

        restore();
    };

    const discard = (entry) => {
        discardOnNavigation = true;
        if (saveTimer) {
            window.clearTimeout(saveTimer);
            saveTimer = null;
        }
        clear(entry);
    };

    document.addEventListener('input', (event) => {
        const form = event.target?.closest?.(formSelector);
        if (form) queueSave(form);
    }, true);

    document.addEventListener('change', (event) => {
        const form = event.target?.closest?.(formSelector);
        if (form) queueSave(form);
    }, true);

    document.addEventListener('submit', (event) => {
        const form = event.target?.matches?.(formSelector) ? event.target : null;
        const entry = getEntry(form);
        if (!entry) return;

        // A real submission is now handled by the server. Do not restore its
        // previous draft on the success or validation response.
        skipPageSave = true;
        if (saveTimer) {
            window.clearTimeout(saveTimer);
            saveTimer = null;
        }
        clear(entry);
    }, true);

    document.addEventListener('click', (event) => {
        const form = event.target?.closest?.(formSelector);
        const nativeClose = event.target?.closest?.('[data-native-modal-close]');
        const nativeCloseId = nativeClose?.dataset?.nativeModalClose || '';
        const modalId = nativeCloseId || event.target?.id || '';
        const entry = getEntry(form) || getEntryForModalId(modalId);
        const cancel = event.target?.closest?.('[data-equipment-add-cancel], [data-equipment-edit-cancel]');

        if (entry && (cancel || getEntryForModalId(modalId))) {
            discard(entry);
        }
    }, true);

    document.addEventListener('pmams-modal-close', (event) => {
        const entry = getEntryForModalId(event.detail?.id || '');
        if (entry) discard(entry);
    }, true);

    document.addEventListener('livewire:navigating', () => {
        if (!skipPageSave && !discardOnNavigation) flushSave();
    });

    document.addEventListener('livewire:navigated', () => {
        skipPageSave = false;
        discardOnNavigation = false;
        window.setTimeout(restoreWhenReady, 0);
    });

    window.addEventListener('pagehide', () => {
        if (!skipPageSave && !discardOnNavigation) flushSave();
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => restoreWhenReady(), { once: true });
    } else {
        restoreWhenReady();
    }
})();

// Offer recently entered, reusable equipment values through an inline
// suggestion panel. Native datalist popups are controlled by the browser and
// can cover the active field or the on-screen keyboard on mobile devices. The
// history is intentionally tab-scoped and bounded: it helps the current
// operator repeat common brand/model/name values without copying unique
// identifiers or remarks into browser storage. Existing saved values seed the
// same local history when an edit form is opened; a suggestion never changes a
// field automatically and remains an ordinary user-editable value.
(function setupEquipmentInputSuggestions() {
    if (window.__pmamsEquipmentInputSuggestionsReady) return;
    window.__pmamsEquipmentInputSuggestionsReady = true;

    const storageKey = 'pmams.equipment.input-suggestions.v1';
    const maxValuesPerField = 8;
    const maxValueLength = 255;
    const fields = new Set(['brand', 'model', 'computer_name', 'processor']);
    const inputMenus = new WeakMap();
    const suppressNextFocusMenu = new WeakSet();
    let menuSequence = 0;

    const getStorage = () => {
        try { return window.sessionStorage; } catch (error) { return null; }
    };

    const emptyState = () => Object.fromEntries(
        Array.from(fields, (field) => [field, []])
    );

    const normalize = (value) => String(value ?? '').trim();

    const read = () => {
        const storage = getStorage();
        const state = emptyState();
        if (!storage) return state;

        try {
            const stored = JSON.parse(storage.getItem(storageKey) || '{}');
            fields.forEach((field) => {
                const values = Array.isArray(stored?.[field]) ? stored[field] : [];
                const seen = new Set();
                state[field] = values
                    .map(normalize)
                    .filter((value) => {
                        const key = value.toLocaleLowerCase();
                        if (!value || value.length > maxValueLength || seen.has(key)) return false;
                        seen.add(key);
                        return true;
                    })
                    .slice(0, maxValuesPerField);
            });
        } catch (error) {
            // Session storage can contain stale or malformed data; ignore it.
        }

        return state;
    };

    const write = (state) => {
        const storage = getStorage();
        if (!storage) return;

        try {
            storage.setItem(storageKey, JSON.stringify(state));
        } catch (error) {
            // Session storage can be unavailable in private/restricted browsers.
        }
    };

    const eligibleInputs = (root = document) => Array.from(
        root.querySelectorAll('input[data-equipment-suggestion]')
    ).filter((input) => fields.has(String(input.dataset.equipmentSuggestion || '')));

    const menuId = (input, field) => {
        const suffix = input.id || input.name || field;
        menuSequence += 1;
        return `pmams-equipment-suggestions-${String(suffix).replace(/[^a-z0-9_-]/gi, '-')}-${menuSequence}`;
    };

    const hideMenu = (input) => {
        const menu = inputMenus.get(input);
        if (!menu) return;

        menu.hidden = true;
        input.setAttribute('aria-expanded', 'false');
    };

    const ensureMenu = (input, field) => {
        let menu = inputMenus.get(input);
        if (menu?.isConnected) return menu;

        menu = document.createElement('div');
        menu.id = menuId(input, field);
        menu.dataset.pmamsEquipmentSuggestionMenu = '1';
        menu.setAttribute('role', 'listbox');
        menu.setAttribute('aria-label', `${field.replace(/_/g, ' ')} suggestions`);
        menu.className = 'mt-1 max-h-40 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-600 dark:bg-gray-800';
        menu.hidden = true;

        // Keep the menu in normal document flow. In particular, do not use
        // absolute/fixed positioning: the form can then scroll the suggestions
        // above the mobile keyboard instead of letting them cover the field.
        input.insertAdjacentElement('afterend', menu);
        input.setAttribute('aria-controls', menu.id);
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-expanded', 'false');
        inputMenus.set(input, menu);

        return menu;
    };

    const attach = (input) => {
        const field = String(input.dataset.equipmentSuggestion || '');
        if (!fields.has(field)) return null;

        // Remove the native datalist hook. Its popup is browser/OS-owned and
        // cannot be constrained to the input's layout on mobile.
        input.removeAttribute('list');
        input.setAttribute('autocomplete', 'off');
        ensureMenu(input, field);
        return field;
    };

    const valuesFor = (input, state = read()) => {
        const field = String(input.dataset.equipmentSuggestion || '');
        if (!fields.has(field)) return [];

        const query = normalize(input.value).toLocaleLowerCase();
        return state[field].filter((value) => !query || value.toLocaleLowerCase().includes(query));
    };

    const showMenu = (input, state = read()) => {
        const field = attach(input);
        if (!field) return;

        const menu = ensureMenu(input, field);
        const values = valuesFor(input, state);
        menu.replaceChildren(...values.map((value) => {
            const option = document.createElement('button');
            option.type = 'button';
            option.dataset.pmamsEquipmentSuggestionOption = '1';
            option.dataset.value = value;
            option.setAttribute('role', 'option');
            option.className = 'block w-full truncate border-b border-gray-100 px-3 py-2 text-left text-sm text-gray-700 last:border-b-0 hover:bg-blue-50 focus:bg-blue-50 focus:outline-none dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-700 dark:focus:bg-gray-700';
            option.textContent = value;
            option.addEventListener('mousedown', (event) => event.preventDefault());
            option.addEventListener('click', () => {
                input.value = option.dataset.value || '';
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
                hideMenu(input);
                // The mousedown guard normally keeps the input focused. If a
                // touch/keyboard interaction moved focus to the option first,
                // restore it without reopening the menu on that focus event.
                if (document.activeElement !== input) {
                    suppressNextFocusMenu.add(input);
                    input.focus();
                } else {
                    suppressNextFocusMenu.delete(input);
                }
            });
            return option;
        }));

        const visible = values.length > 0;
        menu.hidden = !visible;
        input.setAttribute('aria-expanded', visible ? 'true' : 'false');
    };

    const remember = (input) => {
        const field = attach(input);
        if (!field || input.disabled) return;

        const value = normalize(input.value);
        if (!value || value.length > maxValueLength) return;

        const state = read();
        const key = value.toLocaleLowerCase();
        state[field] = [value, ...state[field].filter((candidate) => (
            candidate.toLocaleLowerCase() !== key
        ))].slice(0, maxValuesPerField);
        write(state);
    };

    const initialize = () => {
        // Clean up lists created by older page instances after a Livewire
        // morph. The current controls no longer reference native datalists.
        document.querySelectorAll('datalist[data-pmams-equipment-suggestions]').forEach((list) => list.remove());
        eligibleInputs().forEach((input) => {
            const field = attach(input);
            // Seed suggestions from values already persisted by the server,
            // but never overwrite the current form value.
            if (field && normalize(input.value)) remember(input);
        });
    };

    document.addEventListener('focusin', (event) => {
        const input = event.target?.closest?.('input[data-equipment-suggestion]');
        if (!input) return;
        if (suppressNextFocusMenu.has(input)) {
            suppressNextFocusMenu.delete(input);
            return;
        }
        showMenu(input);
    }, true);

    document.addEventListener('input', (event) => {
        const input = event.target?.closest?.('input[data-equipment-suggestion]');
        if (!input) return;
        showMenu(input);
    }, true);

    document.addEventListener('change', (event) => {
        const input = event.target?.closest?.('input[data-equipment-suggestion]');
        if (!input || event.isTrusted === false) return;
        remember(input);
    }, true);

    document.addEventListener('focusout', (event) => {
        const input = event.target?.closest?.('input[data-equipment-suggestion]');
        if (!input || event.isTrusted === false) return;
        // Store the completed value after the operator leaves the field, not
        // every partial keystroke typed into it.
        remember(input);
        window.setTimeout(() => {
            if (document.activeElement !== input) hideMenu(input);
        }, 120);
    }, true);

    document.addEventListener('keydown', (event) => {
        const input = event.target?.closest?.('input[data-equipment-suggestion]');
        if (!input || event.key !== 'Escape') return;
        hideMenu(input);
    }, true);

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)
            || !form.matches('form[data-equipment-add-form], form[data-equipment-edit-form]')) {
            return;
        }

        // Capture the final submitted values as well, including values that
        // came from browser autofill or the inline menu without an input event.
        eligibleInputs(form).forEach((input) => remember(input));
    }, true);

    document.addEventListener('livewire:navigated', initialize);
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize, { once: true });
    } else {
        initialize();
    }
})();

// Submit marked PM Plan actions through fetch, then let Livewire replace the
// current page. This keeps the PM Plan in SPA mode while preserving Laravel's
// normal redirects, validation errors, flash messages, CSRF protection, and
// confirmation handlers on destructive forms.
(function setupAdminSpaForms() {
    if (window.__adminSpaFormsReady) return;
    window.__adminSpaFormsReady = true;

    const canNavigate = () => window.Livewire && typeof window.Livewire.navigate === 'function';
    const navigateTo = (path) => {
        const currentPath = window.location.pathname + window.location.search + window.location.hash;
        const targetPath = path === currentPath
            ? path + (path.includes('?') ? '&' : '?') + '_spa_refresh=' + Date.now()
            : path;
        window.Livewire.navigate(targetPath);
    };

    document.addEventListener('submit', async (event) => {
        if (event.defaultPrevented || !canNavigate()) return;

        const form = event.target;
        if (!(form instanceof HTMLFormElement) || form.dataset.spaForm !== 'true' || form.dataset.spaSubmitting === '1') return;

        event.preventDefault();
        form.dataset.spaSubmitting = '1';
        form.setAttribute('aria-busy', 'true');
        form.querySelectorAll('button[type="submit"]').forEach((button) => {
            button.disabled = true;
            button.classList.add('cursor-wait', 'opacity-70');
        });

        try {
            const action = new URL(form.getAttribute('action') || window.location.href, window.location.href);
            const response = await fetch(action.href, {
                method: (form.getAttribute('method') || 'POST').toUpperCase(),
                body: new FormData(form),
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-SPA-Request': '1',
                    'Accept': 'text/html, application/xhtml+xml',
                },
            });

            const responseType = response.headers.get('content-type') || '';
            if (responseType.includes('application/json')) {
                const payload = await response.json();
                const target = new URL(payload.redirect || action.href, window.location.href);
                const path = window.adminLocalNavigatePath
                    ? window.adminLocalNavigatePath(target)
                    : `${target.pathname}${target.search}${target.hash}`;

                if (canNavigate()) {
                    navigateTo(path);
                } else {
                    window.location.assign(target.href);
                }
                return;
            }

            // PM Plan actions redirect back to the index on success and after
            // validation failures. Navigate to that final URL so the response
            // includes the normal flash/error state without a full reload.
            if (response.redirected || response.ok) {
                const responseUrl = new URL(response.url || action.href, window.location.href);
                const path = window.adminLocalNavigatePath
                    ? window.adminLocalNavigatePath(responseUrl)
                    : `${responseUrl.pathname}${responseUrl.search}${responseUrl.hash}`;

                if (canNavigate()) {
                    navigateTo(path);
                } else {
                    window.location.assign(responseUrl.href);
                }
                return;
            }

            // Authorization/server errors should still be visible rather than
            // silently leaving the user on a stale PM Plan page.
            window.location.assign(response.url || action.href);
        } catch (error) {
            // Network failures retain the browser's normal submit behavior so
            // the user receives the browser/server error instead of losing the
            // action entirely.
            form.removeAttribute('data-spa-submitting');
            form.removeAttribute('aria-busy');
            form.querySelectorAll('button[type="submit"]').forEach((button) => {
                button.disabled = false;
                button.classList.remove('cursor-wait', 'opacity-70');
            });
            HTMLFormElement.prototype.submit.call(form);
        }
    });
})();

// Maintenance checklist camera: initialize on every SPA navigation so the
// camera controls work immediately without requiring a full page reload.
(function setupChecklistCamera() {
    const stopCamera = () => {
        const stream = window.__checklistCameraStream;
        if (stream) stream.getTracks().forEach((track) => track.stop());
        window.__checklistCameraStream = null;

        const video = document.getElementById('checklist-camera-preview');
        const capture = document.getElementById('checklist-camera-capture');
        const stop = document.getElementById('checklist-camera-stop');
        const start = document.getElementById('checklist-camera-start');
        if (video) {
            video.srcObject = null;
            video.classList.add('hidden');
        }
        capture?.classList.add('hidden');
        stop?.classList.add('hidden');
        start?.classList.remove('hidden');
    };

    const init = () => {
        const input = document.getElementById('maintenance-photo');
        const form = input?.form;
        const video = document.getElementById('checklist-camera-preview');
        const canvas = document.getElementById('checklist-camera-canvas');
        const image = document.getElementById('checklist-photo-preview');
        const placeholder = document.getElementById('checklist-photo-placeholder');
        const start = document.getElementById('checklist-camera-start');
        const capture = document.getElementById('checklist-camera-capture');
        const stop = document.getElementById('checklist-camera-stop');
        const name = document.getElementById('checklist-photo-name');
        if (!form || !input || !video || !canvas || !start || !capture || !stop || form.dataset.cameraReady === '1') return;
        form.dataset.cameraReady = '1';

        const showFile = (file) => {
            if (!file) return;
            image.src = URL.createObjectURL(file);
            image.classList.remove('hidden');
            placeholder?.classList.add('hidden');
            if (name) name.textContent = file.name || 'Captured photo';
        };

        input.addEventListener('change', () => showFile(input.files?.[0]));
        start.addEventListener('click', async () => {
            if (!navigator.mediaDevices?.getUserMedia) {
                input.click();
                return;
            }
            try {
                stopCamera();
                const stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: { ideal: 'environment' } },
                    audio: false,
                });
                window.__checklistCameraStream = stream;
                video.srcObject = stream;
                await video.play();
                video.classList.remove('hidden');
                image?.classList.add('hidden');
                placeholder?.classList.add('hidden');
                start.classList.add('hidden');
                capture.classList.remove('hidden');
                stop.classList.remove('hidden');
            } catch (error) {
                input.click();
            }
        });
        capture.addEventListener('click', () => {
            const stream = window.__checklistCameraStream;
            if (!stream || !video.videoWidth || !video.videoHeight) return;
            const size = Math.min(video.videoWidth, video.videoHeight);
            canvas.width = 1280;
            canvas.height = 1280;
            canvas.getContext('2d').drawImage(video, (video.videoWidth - size) / 2, (video.videoHeight - size) / 2, size, size, 0, 0, 1280, 1280);
            canvas.toBlob((blob) => {
                if (!blob || blob.size > 10 * 1024 * 1024) return;
                const file = new File([blob], `maintenance-photo-${Date.now()}.jpg`, { type: 'image/jpeg' });
                try {
                    const transfer = new DataTransfer();
                    transfer.items.add(file);
                    input.files = transfer.files;
                } catch (error) {
                    return;
                }
                showFile(file);
                stopCamera();
            }, 'image/jpeg', 0.9);
        });
        stop.addEventListener('click', stopCamera);
    };

    document.addEventListener('DOMContentLoaded', init, { once: true });
    document.addEventListener('livewire:navigated', init);
    document.addEventListener('livewire:navigating', stopCamera);
    init();
})();

// Equipment details camera fallback. Blade supplies the same handlers on a
// full render; these globals keep them available when the page is reached via
// SPA navigation where inline scripts may not be evaluated again.
(function setupEquipmentDetailsCamera() {
    let stream = null;
    let requestId = 0;

    const setStatus = (message) => {
        ['device-photo-status', 'device-camera-status']
            .map((id) => document.getElementById(id))
            .filter(Boolean)
            .forEach((status) => { status.textContent = message || ''; });
    };

    const syncBodyLock = () => {
        const camera = document.getElementById('device-camera-modal');
        const lightbox = document.getElementById('device-photo-lightbox');
        const open = [camera, lightbox].some((overlay) => overlay && !overlay.classList.contains('hidden'));
        document.body?.classList.toggle('overflow-hidden', open);
    };

    const setCameraModal = (open) => {
        const modal = document.getElementById('device-camera-modal');
        if (!modal) return;
        modal.classList.toggle('hidden', !open);
        modal.classList.toggle('flex', open);
        modal.setAttribute('aria-hidden', open ? 'false' : 'true');
        syncBodyLock();
        if (open) {
            requestAnimationFrame(() => document.getElementById('device-camera-close-button')?.focus({ preventScroll: true }));
        }
    };

    const openLightbox = () => {
        const image = document.getElementById('device-photo-image');
        const modal = document.getElementById('device-photo-lightbox');
        const lightboxImage = document.getElementById('device-photo-lightbox-image');
        const source = image?.currentSrc || image?.src || '';
        if (!image || image.classList.contains('hidden') || !source || !modal || !lightboxImage) return;
        lightboxImage.src = source;
        lightboxImage.alt = image.alt || 'Equipment photo';
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        modal.setAttribute('aria-hidden', 'false');
        syncBodyLock();
        requestAnimationFrame(() => document.getElementById('device-photo-lightbox-close')?.focus({ preventScroll: true }));
    };

    const closeLightbox = () => {
        const modal = document.getElementById('device-photo-lightbox');
        modal?.classList.add('hidden');
        modal?.classList.remove('flex');
        modal?.setAttribute('aria-hidden', 'true');
        document.getElementById('device-photo-lightbox-image')?.removeAttribute('src');
        syncBodyLock();
    };

    const setBusy = (busy) => {
        ['device-take-photo-button', 'device-clear-photo-button']
            .map((id) => document.getElementById(id))
            .filter(Boolean)
            .forEach((button) => { button.disabled = busy; });
        const captureButton = document.getElementById('device-capture-photo-button');
        if (captureButton) captureButton.disabled = busy || !stream;
    };

    const close = () => {
        requestId += 1;
        (stream || window.__deviceDetailsCameraStream)?.getTracks().forEach((track) => track.stop());
        stream = null;
        window.__deviceDetailsCameraStream = null;
        const video = document.getElementById('device-camera-video');
        const controls = document.getElementById('device-camera-controls');
        const placeholder = document.getElementById('device-camera-placeholder');
        const captureButton = document.getElementById('device-capture-photo-button');
        if (video) {
            video.pause?.();
            video.srcObject = null;
            video.classList.add('hidden');
        }
        controls?.classList.add('hidden');
        controls?.classList.remove('flex');
        placeholder?.classList.remove('hidden');
        if (captureButton) captureButton.disabled = true;
        setCameraModal(false);
    };

    const open = async () => {
        const video = document.getElementById('device-camera-video');
        const controls = document.getElementById('device-camera-controls');
        const placeholder = document.getElementById('device-camera-placeholder');
        if (!video || !controls) return;
        if (stream) close();
        const currentRequest = ++requestId;
        setCameraModal(true);
        placeholder?.classList.remove('hidden');
        setStatus('Opening camera...');
        if (!navigator.mediaDevices?.getUserMedia) {
            setStatus('Camera access is not available in this browser.');
            return;
        }

        setBusy(true);
        try {
            const nextStream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: { ideal: 'environment' } },
                audio: false,
            });
            if (currentRequest !== requestId) {
                nextStream.getTracks().forEach((track) => track.stop());
                return;
            }
            stream = nextStream;
            window.__deviceDetailsCameraStream = stream;
            video.srcObject = stream;
            await video.play();
            video.classList.remove('hidden');
            placeholder?.classList.add('hidden');
            controls.classList.remove('hidden');
            controls.classList.add('flex');
            setStatus('Camera ready. Center the equipment and capture when ready.');
        } catch (error) {
            setStatus(window.isSecureContext
                ? 'Camera permission was blocked or no camera was found.'
                : 'Camera requires HTTPS or localhost.');
        } finally {
            setBusy(false);
        }
    };

    const capture = async () => {
        const form = document.getElementById('device-photo-form');
        const video = document.getElementById('device-camera-video');
        const canvas = document.getElementById('device-camera-canvas');
        const image = document.getElementById('device-photo-image');
        const empty = document.getElementById('device-photo-empty');
        const zoomButton = document.getElementById('device-photo-zoom-button');
        if (!form || !video || !canvas || !image || !stream || !video.videoWidth) return;
        setBusy(true);
        setStatus('Saving photo...');
        try {
            const size = Math.min(video.videoWidth, video.videoHeight);
            canvas.width = 1280;
            canvas.height = 1280;
            canvas.getContext('2d').drawImage(video, (video.videoWidth - size) / 2, (video.videoHeight - size) / 2, size, size, 0, 0, 1280, 1280);
            const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.9));
            if (!blob || blob.size > 10 * 1024 * 1024) throw new Error('The captured photo is larger than 10 MB.');
            const data = new FormData(form);
            data.append('equipment_photo', blob, 'equipment-photo.jpg');
            const response = await fetch(form.getAttribute('action') || window.location.href, { method: 'POST', body: data, headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' } });
            if (!response.ok) throw new Error('Photo upload failed.');
            const result = await response.json();
            image.src = `${result.photo_url}?v=${Date.now()}`;
            image.alt = 'Photo of equipment';
            image.classList.remove('hidden');
            empty?.classList.add('hidden');
            empty?.classList.remove('flex');
            zoomButton?.classList.remove('hidden');
            zoomButton?.classList.add('flex');
            document.getElementById('device-clear-photo-button')?.classList.remove('hidden');
            setStatus(result.message || 'Photo saved.');
            close();
        } catch (error) {
            setStatus(error.message || 'Photo upload failed. Please try again.');
        } finally {
            setBusy(false);
        }
    };

    const clearPhoto = async () => {
        if (!window.confirm('Delete this equipment photo? This action cannot be undone.')) return;
        const form = document.getElementById('device-photo-delete-form');
        const image = document.getElementById('device-photo-image');
        const empty = document.getElementById('device-photo-empty');
        const zoomButton = document.getElementById('device-photo-zoom-button');
        if (!form) return;
        setBusy(true);
        setStatus('Deleting photo...');
        try {
            const response = await fetch(form.getAttribute('action') || window.location.href, { method: 'POST', body: new FormData(form), headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' } });
            if (!response.ok) throw new Error('Photo delete failed.');
            const result = await response.json();
            image?.classList.add('hidden');
            image?.removeAttribute('src');
            empty?.classList.remove('hidden');
            empty?.classList.add('flex');
            zoomButton?.classList.add('hidden');
            zoomButton?.classList.remove('flex');
            closeLightbox();
            document.getElementById('device-clear-photo-button')?.classList.add('hidden');
            setStatus(result.message || 'Photo cleared.');
        } catch (error) {
            setStatus(error.message || 'Photo delete failed. Please try again.');
        } finally {
            setBusy(false);
        }
    };

    if (!window.openDeviceCamera) window.openDeviceCamera = open;
    if (!window.closeDeviceCamera) window.closeDeviceCamera = close;
    if (!window.captureDevicePhoto) window.captureDevicePhoto = capture;
    if (!window.clearDevicePhoto) window.clearDevicePhoto = clearPhoto;
    if (!window.openDevicePhotoLightbox) window.openDevicePhotoLightbox = openLightbox;
    if (!window.closeDevicePhotoLightbox) window.closeDevicePhotoLightbox = closeLightbox;
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        const cameraModal = document.getElementById('device-camera-modal');
        const lightbox = document.getElementById('device-photo-lightbox');
        if (cameraModal && !cameraModal.classList.contains('hidden')) close();
        else if (lightbox && !lightbox.classList.contains('hidden')) closeLightbox();
    });
    document.addEventListener('livewire:navigating', () => {
        close();
        closeLightbox();
    });
})();

(function setupAdminNavigationState() {
    if (window.__adminNavigationStateReady) return;
    window.__adminNavigationStateReady = true;

    const linkActiveClasses = ['bg-blue-50', 'text-blue-700', 'dark:bg-blue-900/30', 'dark:text-blue-400'];
    const linkInactiveClasses = ['text-gray-700', 'hover:bg-gray-100', 'dark:text-gray-300', 'dark:hover:bg-gray-700'];
    const iconActiveClasses = ['text-blue-600', 'dark:text-blue-400'];
    const iconInactiveClasses = ['text-gray-500', 'group-hover:text-gray-700', 'dark:text-gray-400', 'dark:group-hover:text-gray-200'];

    const adminPath = (path) => {
        const index = path.indexOf('/admin');
        return (index >= 0 ? path.slice(index) : path).replace(/\/+$/, '') || '/';
    };

    const routeGroup = (path) => {
        if (path === '/admin' || path === '/admin/dashboard') return 'dashboard';
        if (path.startsWith('/admin/locations') ||
            path.startsWith('/admin/colleges') ||
            path.startsWith('/admin/offices') ||
            path.startsWith('/admin/staff') ||
            path.startsWith('/admin/org-browser')) return 'locations';
        if (path.startsWith('/admin/devices')) return 'devices';
        if (path.startsWith('/admin/issuance')) return 'issuance';
        if (path.startsWith('/admin/maintenance-cleanup')) return 'maintenance-cleanup';
        if (path.startsWith('/admin/reports')) return 'reports';
        if (path.startsWith('/admin/database')) return 'database';
        if (path.startsWith('/admin/maintenance-gallery')) return 'gallery';
        if (path.startsWith('/admin/scanner')) return 'scanner';
        if (path.startsWith('/admin/support') || path.startsWith('/admin/contributors')) return 'support';
        if (path.startsWith('/admin/users')) return 'users';
        if (path.startsWith('/admin/logs')) return 'logs';
        return null;
    };

    const currentPageGroup = () => {
        const marker = document.querySelector('main[data-current-nav-group]') ||
            document.querySelector('body[data-current-nav-group]');
        return marker && marker.dataset.currentNavGroup
            ? marker.dataset.currentNavGroup
            : routeGroup(adminPath(window.location.pathname));
    };

    const setClasses = (element, activeClasses, inactiveClasses, isActive) => {
        element.classList.remove(...activeClasses, ...inactiveClasses);
        element.classList.add(...(isActive ? activeClasses : inactiveClasses));
    };

    const syncSidebarChrome = (open) => {
        document.querySelectorAll('[data-admin-sidebar]').forEach((sidebar) => {
            sidebar.dataset.open = open ? 'true' : 'false';
            sidebar.setAttribute('aria-hidden', (!open && window.innerWidth < 1024).toString());
        });

        document.querySelectorAll('[data-sidebar-open]').forEach((button) => {
            button.setAttribute('aria-expanded', open.toString());
        });

        document.documentElement.classList.toggle('overflow-hidden', open && window.innerWidth < 1024);
        document.body.classList.toggle('overflow-hidden', open && window.innerWidth < 1024);
    };

    const setSidebarOpen = (open) => {
        document.querySelectorAll('[x-data]').forEach((element) => {
            const data = element._x_dataStack && element._x_dataStack[0];
            if (!data) return;

            if ('sidebarOpen' in data) data.sidebarOpen = open;
            if (open && 'profileOpen' in data) data.profileOpen = false;
            if (open && 'themeOpen' in data) data.themeOpen = false;
        });

        syncSidebarChrome(open);
    };

    const closeOpenMenus = () => {
        document.querySelectorAll('[x-data]').forEach((element) => {
            const data = element._x_dataStack && element._x_dataStack[0];
            if (!data) return;

            if ('sidebarOpen' in data) data.sidebarOpen = false;
            if ('profileOpen' in data) data.profileOpen = false;
            if ('themeOpen' in data) data.themeOpen = false;
        });

        syncSidebarChrome(false);
    };

    const refreshTheme = () => {
        if (typeof window.getSavedTheme !== 'function' || typeof window.setTheme !== 'function') return;

        const theme = window.getSavedTheme();
        window.setTheme(theme);

        document.querySelectorAll('[x-data]').forEach((element) => {
            const data = element._x_dataStack && element._x_dataStack[0];
            if (data && 'theme' in data) data.theme = theme;
        });
    };

    const refreshSidebarActiveState = (group = null) => {
        const currentGroup = group || currentPageGroup();

        document.querySelectorAll('aside nav a[data-nav-group][href]').forEach((link) => {
            const linkGroup = link.dataset.navGroup ||
                routeGroup(adminPath(new URL(link.href, window.location.href).pathname));
            const isActive = Boolean(currentGroup && linkGroup === currentGroup);

            link.dataset.active = isActive ? 'true' : 'false';
            if (isActive) {
                link.setAttribute('aria-current', 'page');
            } else {
                link.removeAttribute('aria-current');
            }

            setClasses(link, linkActiveClasses, linkInactiveClasses, isActive);

            const icon = link.querySelector('svg');
            if (icon) setClasses(icon, iconActiveClasses, iconInactiveClasses, isActive);
        });
    };

    const refreshNavigationState = () => {
        refreshTheme();
        refreshSidebarActiveState();
        closeOpenMenus();
    };

    document.addEventListener('click', (event) => {
        const openButton = event.target.closest('[data-sidebar-open]');
        if (openButton) {
            setSidebarOpen(true);
            return;
        }

        const closeButton = event.target.closest('[data-sidebar-close], [data-sidebar-overlay]');
        if (closeButton) {
            setSidebarOpen(false);
            return;
        }

        const link = event.target.closest('aside nav a[data-nav-group][href]');
        if (!link || event.defaultPrevented || event.button !== 0) return;
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

        // Start the no-transition guard before Alpine closes the mobile menu
        // and before the delegated SPA handler begins fetching the next page.
        // Otherwise the persisted sidebar can briefly animate/flash during a
        // route change even though the page itself is navigating without a
        // full reload.
        const target = new URL(link.href, window.location.href);
        const targetPath = typeof window.adminLocalNavigatePath === 'function'
            ? window.adminLocalNavigatePath(target)
            : `${target.pathname}${target.search}${target.hash}`;
        const currentPath = `${window.location.pathname}${window.location.search}${window.location.hash}`;
        if (targetPath !== currentPath) {
            if (typeof window.prepareAdminNavigation === 'function') {
                window.prepareAdminNavigation();
            } else {
                document.documentElement.classList.add('is-navigating');
            }
        }

        refreshSidebarActiveState(link.dataset.navGroup);
        setSidebarOpen(false);
    }, true);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeOpenMenus();
    });

    window.addEventListener('resize', () => {
        const root = document.querySelector('[x-data]');
        const data = root && root._x_dataStack && root._x_dataStack[0];
        syncSidebarChrome(Boolean(data && data.sidebarOpen));
    });

    document.addEventListener('livewire:navigating', closeOpenMenus);
    document.addEventListener('livewire:navigated', refreshNavigationState);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', refreshNavigationState, { once: true });
    } else {
        refreshNavigationState();
    }
})();

(function setupAdminDashboardCharts() {
    if (window.__adminDashboardChartsReady) return;
    window.__adminDashboardChartsReady = true;

    const state = {
        loading: null,
        instances: [],
    };

    const loadChartLibrary = () => {
        if (window.Chart) return Promise.resolve(window.Chart);
        if (state.loading) return state.loading;

        state.loading = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js';
            script.async = true;
            script.onload = () => resolve(window.Chart);
            script.onerror = reject;
            document.head.appendChild(script);
        });

        return state.loading;
    };

    const destroyCharts = () => {
        state.instances.forEach((chart) => {
            try { chart.destroy(); } catch (error) { /* detached chart */ }
        });
        state.instances = [];

        if (window.Chart) {
            document.querySelectorAll('[data-admin-dashboard-charts] canvas').forEach((canvas) => {
                const chart = window.Chart.getChart?.(canvas);
                if (chart) chart.destroy();
            });
        }
    };

    const renderCharts = () => {
        const root = document.querySelector('[data-admin-dashboard-charts]');
        if (!root) {
            destroyCharts();
            return;
        }

        let data;
        try {
            data = JSON.parse(root.dataset.chartData || '{}');
        } catch (error) {
            return;
        }

        loadChartLibrary().then((Chart) => {
            if (!Chart || !root.isConnected) return;

            destroyCharts();

            const create = (id, type, chartData, options) => {
                const canvas = document.getElementById(id);
                if (!canvas) return;
                const chart = new Chart(canvas, {
                    type,
                    data: chartData,
                    options,
                });
                state.instances.push(chart);
            };

            const common = {
                responsive: true,
                maintainAspectRatio: false,
            };

            create('statusChart', 'bar', {
                labels: data.condition?.labels || [],
                datasets: [{
                    label: 'Equipment',
                    data: data.condition?.values || [],
                    backgroundColor: ['#22c55e', '#ef4444', '#6b7280'],
                    borderRadius: 6,
                    borderSkipped: false,
                }],
            }, {
                ...common,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
            });

            create('totalEquipmentChart', 'pie', {
                labels: data.status?.labels || data.availability?.labels || [],
                datasets: [{
                    data: data.status?.values || data.availability?.values || [],
                    backgroundColor: ['#10b981', '#6366f1', '#f59e0b', '#64748b'],
                    borderWidth: 2,
                }],
            }, {
                ...common,
                plugins: { legend: { position: 'bottom', labels: { padding: 12, boxWidth: 12 } } },
            });

            create('typeChart', 'doughnut', {
                labels: data.type?.labels || [],
                datasets: [{
                    data: data.type?.values || [],
                    backgroundColor: ['#3b82f6', '#6366f1', '#22c55e', '#f59e0b', '#ef4444', '#14b8a6', '#ec4899', '#8b5cf6'],
                    borderWidth: 2,
                }],
            }, {
                ...common,
                plugins: { legend: { position: 'bottom', labels: { padding: 12, boxWidth: 12 } } },
            });

            create('officeChart', 'bar', {
                labels: data.office?.labels || [],
                datasets: [{
                    label: 'Issued Equipment',
                    data: data.office?.values || [],
                    backgroundColor: '#6366f1',
                    borderRadius: 6,
                    borderSkipped: false,
                }],
            }, {
                ...common,
                indexAxis: 'y',
                plugins: { legend: { display: false } },
                scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } },
            });

            create('endUsersLocationChart', 'bar', {
                labels: data.end_users?.labels || [],
                datasets: [{
                    label: 'Active End Users',
                    data: data.end_users?.values || [],
                    backgroundColor: '#0ea5e9',
                    borderRadius: 6,
                    borderSkipped: false,
                }],
            }, {
                ...common,
                indexAxis: 'y',
                plugins: { legend: { display: false } },
                scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } },
            });

            const maintenanceCoverage = data.maintenance_coverage || {};
            const maintenanceCoverageLabels = maintenanceCoverage.labels || [];
            const maintenanceCoverageTypes = maintenanceCoverage.types || [];
            const maintenanceCoverageMaintained = maintenanceCoverage.maintained || [];
            const maintenanceCoverageNotMaintained = maintenanceCoverage.not_maintained || [];
            const maintenanceTypeColors = [
                '#2563eb', // blue
                '#7c3aed', // violet
                '#059669', // emerald
                '#d97706', // amber
                '#dc2626', // red
                '#0891b2', // cyan
                '#db2777', // pink
                '#4f46e5', // indigo
                '#65a30d', // lime
                '#9333ea', // purple
                '#0f766e', // teal
                '#ea580c', // orange
            ];
            const maintenanceCoverageValue = (series, periodIndex, typeName) => {
                const periodValues = series[periodIndex];

                return periodValues && typeof periodValues === 'object'
                    ? Number(periodValues[typeName] || 0)
                    : 0;
            };
            const maintenanceCoverageDatasets = maintenanceCoverageTypes.flatMap((typeName, typeIndex) => {
                const color = maintenanceTypeColors[typeIndex % maintenanceTypeColors.length];
                const maintainedLabel = `${typeName} · Maintained`;
                const notMaintainedLabel = `${typeName} · Not Maintained`;

                return [
                    {
                        label: maintainedLabel,
                        data: maintenanceCoverageLabels.map((_, periodIndex) => maintenanceCoverageValue(maintenanceCoverageMaintained, periodIndex, typeName)),
                        backgroundColor: color,
                        borderColor: color,
                        borderRadius: 6,
                        borderSkipped: false,
                        stack: 'maintenance',
                    },
                    {
                        label: notMaintainedLabel,
                        data: maintenanceCoverageLabels.map((_, periodIndex) => maintenanceCoverageValue(maintenanceCoverageNotMaintained, periodIndex, typeName)),
                        backgroundColor: `${color}66`,
                        borderColor: `${color}99`,
                        borderRadius: 6,
                        borderSkipped: false,
                        stack: 'maintenance',
                    },
                ];
            });

            create('maintenanceCoverageChart', 'bar', {
                labels: maintenanceCoverageLabels,
                datasets: maintenanceCoverageDatasets,
            }, {
                ...common,
                indexAxis: 'y',
                // Select only the segment directly under the pointer. Using
                // index/intersect:false made every type in the same window
                // appear in one large tooltip.
                interaction: { mode: 'nearest', intersect: true },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 12,
                            boxWidth: 12,
                            // Keep one legend key per equipment type. The
                            // translucent companion segment represents its
                            // Not Maintained count.
                            filter: (legendItem, chartData) => !String(chartData.datasets[legendItem.datasetIndex]?.label || '').includes('· Not Maintained'),
                        },
                    },
                    tooltip: {
                        mode: 'nearest',
                        intersect: true,
                        callbacks: {
                            label: (context) => `${context.dataset.label}: ${Number(context.raw || 0).toLocaleString()}`,
                        },
                    },
                },
                scales: {
                    x: { beginAtZero: true, stacked: true, ticks: { stepSize: 1 } },
                    y: { stacked: true, ticks: { autoSkip: false } },
                },
            });

            const maintenanceByType = data.maintenance_by_type || {};
            create('maintenanceStatusByTypeChart', 'bar', {
                labels: maintenanceByType.labels || [],
                datasets: [
                    {
                        label: 'Maintained',
                        data: maintenanceByType.maintained || [],
                        backgroundColor: '#22c55e',
                        borderColor: '#16a34a',
                        borderRadius: 6,
                        borderSkipped: false,
                        barPercentage: 0.72,
                        categoryPercentage: 0.82,
                    },
                    {
                        label: 'Not Maintained',
                        data: maintenanceByType.not_maintained || [],
                        backgroundColor: '#f59e0b',
                        borderColor: '#d97706',
                        borderRadius: 6,
                        borderSkipped: false,
                        barPercentage: 0.72,
                        categoryPercentage: 0.82,
                    },
                ],
            }, {
                ...common,
                indexAxis: 'y',
                interaction: { mode: 'nearest', intersect: true },
                plugins: {
                    legend: { position: 'bottom', labels: { padding: 12, boxWidth: 12 } },
                    tooltip: {
                        mode: 'nearest',
                        intersect: true,
                        callbacks: {
                            label: (context) => `${context.dataset.label}: ${Number(context.raw || 0).toLocaleString()}`,
                        },
                    },
                },
                scales: {
                    x: { beginAtZero: true, stacked: false, ticks: { stepSize: 1 } },
                    y: { stacked: false, ticks: { autoSkip: false } },
                },
            });

            create('transferChart', 'bar', {
                labels: data.transfers?.labels || [],
                datasets: [{
                    label: 'Equipment Transfers',
                    data: data.transfers?.values || [],
                    backgroundColor: '#f59e0b',
                    borderRadius: 6,
                    borderSkipped: false,
                }],
            }, {
                ...common,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
            });

            create('maintenancePlanStatusChart', 'bar', {
                labels: data.maintenance_plan_status?.labels || [],
                datasets: [{
                    label: 'PM Plans',
                    data: data.maintenance_plan_status?.values || [],
                    backgroundColor: ['#f59e0b', '#3b82f6', '#22c55e'],
                    borderRadius: 6,
                    borderSkipped: false,
                }],
            }, {
                ...common,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
            });

            create('maintenanceAttentionChart', 'line', {
                labels: data.maintenance_attention?.labels || [],
                datasets: [
                    { label: 'Critical', data: data.maintenance_attention?.critical || [], borderColor: '#dc2626', backgroundColor: '#dc2626', tension: 0.3, pointRadius: 3, fill: false },
                    { label: 'High', data: data.maintenance_attention?.high || [], borderColor: '#ea580c', backgroundColor: '#ea580c', tension: 0.3, pointRadius: 3, fill: false },
                    { label: 'Medium', data: data.maintenance_attention?.medium || [], borderColor: '#ca8a04', backgroundColor: '#ca8a04', tension: 0.3, pointRadius: 3, fill: false },
                    { label: 'Low', data: data.maintenance_attention?.low || [], borderColor: '#2563eb', backgroundColor: '#2563eb', tension: 0.3, pointRadius: 3, fill: false },
                    { label: 'Local AI recommended', data: data.maintenance_attention?.ai || [], borderColor: '#9333ea', backgroundColor: '#9333ea', borderDash: [6, 4], tension: 0.3, pointRadius: 3, fill: false },
                ],
            }, {
                ...common,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { position: 'bottom', labels: { padding: 12, boxWidth: 12 } } },
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
            });
        }).catch(() => {
            // The dashboard remains usable if the optional chart CDN is offline.
        });
    };

    const scheduleRender = () => window.setTimeout(renderCharts, 40);
    document.addEventListener('livewire:navigated', scheduleRender);
    document.addEventListener('livewire:navigating', destroyCharts);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scheduleRender, { once: true });
    } else {
        scheduleRender();
    }
})();

(function setupAdminQrScanner() {
    if (window.__adminQrScannerReady) return;
    window.__adminQrScannerReady = true;

    const libraryUrl = 'https://unpkg.com/html5-qrcode';
    const state = window.__adminQrScannerState = window.__adminQrScannerState || {
        scanner: null,
        isRunning: false,
        detectionHandled: false,
        audioContext: null,
    };

    const unlockAudio = () => {
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;

            state.audioContext = state.audioContext || new AudioContext();
            if (state.audioContext.state === 'suspended') {
                state.audioContext.resume().catch(() => {});
            }
        } catch (error) {
            // Audio is optional; scanning must continue when it is unavailable.
        }
    };

    const playDetectionBeep = () => {
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;

            state.audioContext = state.audioContext || new AudioContext();
            const context = state.audioContext;
            const play = () => {
                const now = context.currentTime;
                const oscillator = context.createOscillator();
                const gain = context.createGain();

                oscillator.type = 'sine';
                oscillator.frequency.setValueAtTime(880, now);
                gain.gain.setValueAtTime(0.0001, now);
                gain.gain.exponentialRampToValueAtTime(0.18, now + 0.01);
                gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.16);

                oscillator.connect(gain);
                gain.connect(context.destination);
                oscillator.start(now);
                oscillator.stop(now + 0.16);
            };

            if (context.state === 'suspended') {
                context.resume().then(play).catch(() => {});
            } else {
                play();
            }
        } catch (error) {
            // Audio is optional; scanning must continue when it is unavailable.
        }
    };

    const setText = (element, text) => {
        if (element) element.textContent = text;
    };

    const loadQrLibrary = () => {
        if (window.Html5Qrcode) return Promise.resolve();
        if (window.__adminQrScannerLibraryLoading) return window.__adminQrScannerLibraryLoading;

        window.__adminQrScannerLibraryLoading = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = libraryUrl;
            script.async = true;
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });

        return window.__adminQrScannerLibraryLoading;
    };

    const stopScanner = () => {
        if (!state.scanner || !state.isRunning) return Promise.resolve();

        return state.scanner.stop()
            .catch(() => {})
            .finally(() => {
                state.isRunning = false;
            });
    };

    const initQrScanner = () => {
        const reader = document.getElementById('reader');
        const startBtn = document.getElementById('start-scanner');
        const stopBtn = document.getElementById('stop-scanner');
        const statusEl = document.getElementById('scan-status');
        const resultEl = document.getElementById('scan-result');
        const modal = document.getElementById('qr-scanner-modal');
        const closeBtn = document.getElementById('close-scanner');
        const modalStatusEl = document.getElementById('scanner-modal-status');

        if (!reader || !startBtn || !stopBtn || !modal || startBtn.dataset.scannerReady === 'true') return;

        startBtn.dataset.scannerReady = 'true';

        const setStatus = (text) => {
            setText(statusEl, text);
            setText(modalStatusEl, text);
        };
        let pendingUrl = null;

        const setResult = (text, url = null, canOpen = false) => {
            if (!resultEl) return;

            const value = text || '-';
            setText(resultEl, value);
            pendingUrl = canOpen && url ? url : null;
            resultEl.disabled = !pendingUrl;
            resultEl.dataset.url = pendingUrl ? pendingUrl.href : '';
            resultEl.title = pendingUrl
                ? 'Open scanned HTTP/HTTPS link'
                : 'No safe HTTP/HTTPS link is ready to open';
            resultEl.setAttribute(
                'aria-label',
                pendingUrl ? `Open scanned value ${value}` : `Scanned value ${value}. No safe HTTP/HTTPS link is ready to open.`
            );
        };

        const openModal = () => {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.classList.add('overflow-hidden');
        };

        const closeModal = () => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
        };

        const updateButtons = (isRunning) => {
            startBtn.disabled = isRunning;
            stopBtn.disabled = !isRunning;
        };

        const parseQrValue = (decodedText) => {
            const raw = String(decodedText || '').trim();
            if (!raw) return { kind: 'invalid', url: null };

            // Do not turn arbitrary QR text (for example a property number) into a path.
            // Only HTTP(S) links are navigable; javascript:, data:, file:, and other
            // schemes remain blocked even when they point to an external host.
            if (/^[a-z][a-z\d+.-]*:/i.test(raw) && !/^https?:\/\//i.test(raw)) {
                return { kind: 'blocked', url: null };
            }

            const looksLikeUrl = /^https?:\/\//i.test(raw) || raw.startsWith('/') || /^\.{1,2}\//.test(raw);
            if (!looksLikeUrl) return { kind: 'text', url: null };

            try {
                const url = new URL(raw, window.location.origin);
                if (!['http:', 'https:'].includes(url.protocol) || url.username || url.password) {
                    return { kind: 'blocked', url };
                }

                return { kind: 'allowed', url };
            } catch (error) {
                return { kind: 'invalid', url: null };
            }
        };

        const navigateFromQr = (url) => {
            if (!url) return;

            if (url.origin === window.location.origin) {
                const navigatePath = typeof window.adminLocalNavigatePath === 'function'
                    ? window.adminLocalNavigatePath(url)
                    : `${url.pathname}${url.search}${url.hash}`;

                if (window.Livewire && typeof window.Livewire.navigate === 'function') {
                    window.Livewire.navigate(navigatePath);
                    return;
                }

                window.location.assign(navigatePath);
                return;
            }

            // A URL on another origin needs a full browser navigation instead of
            // Livewire's same-origin router.
            window.location.assign(url.href);
        };

        const handleScanSuccess = (decodedText) => {
            if (state.detectionHandled) return;
            state.detectionHandled = true;
            playDetectionBeep();
            const parsed = parseQrValue(decodedText);
            setResult(decodedText, parsed.url, parsed.kind === 'allowed');
            if (parsed.kind === 'allowed') {
                setStatus('QR code detected. Redirecting...');
            } else if (parsed.kind === 'blocked') {
                setStatus('Blocked unsafe QR URL. Only HTTP and HTTPS links are allowed.');
            } else if (parsed.kind === 'text') {
                setStatus('QR code detected, but it is not an HTTP/HTTPS link.');
            } else {
                setStatus('Invalid QR content.');
            }

            stopScanner().finally(() => {
                updateButtons(false);
                closeModal();
                if (parsed.kind === 'allowed') {
                    navigateFromQr(parsed.url);
                }
            });
        };

        const startScanner = () => {
            if (state.isRunning) return;

            state.detectionHandled = false;
            unlockAudio();
            setStatus('Loading scanner...');
            setResult('-', null, false);

            loadQrLibrary().then(() => {
                state.scanner = new window.Html5Qrcode(reader.id);

                return window.Html5Qrcode.getCameras();
            }).then((devices) => {
                if (!devices || devices.length === 0) {
                    setStatus('No camera found');
                    updateButtons(false);
                    return;
                }

                const backCamera = devices.find((device) =>
                    device.label && device.label.toLowerCase().includes('back')
                );
                const cameraId = backCamera ? backCamera.id : devices[0].id;

                return state.scanner.start(
                    cameraId,
                    { fps: 10, qrbox: { width: 280, height: 280 } },
                    handleScanSuccess,
                    () => {}
                ).then(() => {
                    state.isRunning = true;
                    setStatus('Scanning...');
                    updateButtons(true);
                });
            }).catch((error) => {
                setStatus(window.Html5Qrcode ? 'Unable to start scanner' : 'Unable to load scanner');
                setResult(error && error.message ? error.message : String(error));
                updateButtons(false);
            });
        };

        resultEl?.addEventListener('click', () => {
            const parsed = parseQrValue(resultEl.textContent || '');
            if (parsed.kind !== 'allowed' || !pendingUrl) {
                setStatus('This scanned value cannot be opened. Only HTTP and HTTPS links are allowed.');
                return;
            }

            navigateFromQr(pendingUrl);
        });

        startBtn.addEventListener('click', () => {
            openModal();
            startScanner();
        });

        stopBtn.addEventListener('click', () => {
            stopScanner().then(() => {
                setStatus('Scanner stopped');
                updateButtons(false);
                closeModal();
            });
        });

        closeBtn.addEventListener('click', () => {
            stopScanner().then(() => {
                updateButtons(false);
                setStatus('Scanner closed');
                closeModal();
            });
        });

        modal.addEventListener('click', (event) => {
            if (event.target !== modal) return;
            stopScanner().then(() => {
                updateButtons(false);
                setStatus('Scanner closed');
                closeModal();
            });
        });

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape' || modal.classList.contains('hidden')) return;
            stopScanner().then(() => {
                updateButtons(false);
                setStatus('Scanner closed');
                closeModal();
            });
        });

        const shouldAutoStart = new URLSearchParams(window.location.search).get('start') === '1';
        if (shouldAutoStart) {
            setTimeout(() => {
                openModal();
                startScanner();
            }, 250);
        }
    };

    document.addEventListener('livewire:navigating', () => {
        stopScanner();
        const modal = document.getElementById('qr-scanner-modal');
        modal?.classList.add('hidden');
        modal?.classList.remove('flex');
        document.body.classList.remove('overflow-hidden');
    });
    document.addEventListener('livewire:navigated', initQrScanner);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initQrScanner, { once: true });
    } else {
        initQrScanner();
    }
})();
