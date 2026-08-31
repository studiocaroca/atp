/*!
=========================================================
* Creative Design Landing page
=========================================================

* Copyright: 2019 DevCRUD (https://devcrud.com)
* Licensed: (https://devcrud.com/licenses)
* Coded by www.devcrud.com

=========================================================

* The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
*/ 

// Video background — half speed native playback
document.addEventListener('DOMContentLoaded', function () {
    var video = document.querySelector('.auricular-bg');
    if (video) {
        video.playbackRate = 0.5;
    }
});

// High-contrast toggle — off by default (page looks exactly as
// designed); switches a handful of white-on-coral/ocre titles and
// cards to dark-blue text for better contrast. Remembered across
// visits via localStorage.
document.addEventListener('DOMContentLoaded', function () {
    var toggle = document.getElementById('contrast-toggle');
    if (!toggle) return;

    var STORAGE_KEY = 'atp-high-contrast';

    function applyState(enabled) {
        document.body.classList.toggle('a11y-contrast', enabled);
        toggle.setAttribute('aria-pressed', enabled ? 'true' : 'false');
        toggle.setAttribute('aria-label', enabled ? 'Desactivar alto contraste' : 'Activar alto contraste');
        toggle.setAttribute('title', enabled ? 'Desactivar alto contraste' : 'Activar alto contraste');
    }

    var saved;
    try {
        saved = localStorage.getItem(STORAGE_KEY);
    } catch (e) {
        saved = null;
    }
    applyState(saved === 'on');

    toggle.addEventListener('click', function () {
        var enabled = !document.body.classList.contains('a11y-contrast');
        applyState(enabled);
        try {
            localStorage.setItem(STORAGE_KEY, enabled ? 'on' : 'off');
        } catch (e) {
            // localStorage unavailable (private browsing, etc.) — the
            // toggle still works for the current page view.
        }
    });
});

// Colorblind-mode toggle — off by default; applies the daltonization
// SVG filter (see the <filter id="daltonize-deuteranopia"> near the top
// of <body>) to the navbar and main content. Same on/off + localStorage
// pattern as the high-contrast toggle above.
document.addEventListener('DOMContentLoaded', function () {
    var toggle = document.getElementById('colorblind-toggle');
    if (!toggle) return;

    var STORAGE_KEY = 'atp-colorblind';

    function applyState(enabled) {
        document.body.classList.toggle('a11y-colorblind', enabled);
        toggle.setAttribute('aria-pressed', enabled ? 'true' : 'false');
        toggle.setAttribute('aria-label', enabled ? 'Desactivar modo daltónico' : 'Activar modo daltónico');
        toggle.setAttribute('title', enabled ? 'Desactivar modo daltónico' : 'Activar modo daltónico');
    }

    var saved;
    try {
        saved = localStorage.getItem(STORAGE_KEY);
    } catch (e) {
        saved = null;
    }
    applyState(saved === 'on');

    toggle.addEventListener('click', function () {
        var enabled = !document.body.classList.contains('a11y-colorblind');
        applyState(enabled);
        try {
            localStorage.setItem(STORAGE_KEY, enabled ? 'on' : 'off');
        } catch (e) {
            // localStorage unavailable (private browsing, etc.) — the
            // toggle still works for the current page view.
        }
    });
});

// Navbar logo animation loop
document.addEventListener('DOMContentLoaded', function () {
    const logoSequence = [
        'circulo-espiral.svg',
        'circulo-semicirculo.svg',
        'cuadrado-escalera.svg',
        'espiral-cuadrado.svg',
        'estrella-rombo.svg',
        'estrella-semicirculo.svg',
        'onda-circulo.svg'
    ];

    const logoTargets = document.querySelectorAll('#logo-atp, #logo-insignia');
    if (!logoTargets.length) return;

    let currentIndex = 0;

    function swapLogo() {
        const nextSrc = 'assets/imgs/formas/' + logoSequence[currentIndex];

        logoTargets.forEach(function (logo) {
            logo.src = nextSrc;
        });

        currentIndex = (currentIndex + 1) % logoSequence.length;
    }

    swapLogo();
    window.setInterval(swapLogo, 2000);
});

// Admin login: the lock icon opens a small form right under it instead
// of sending you to admin/login.php first — that page still exists and
// still works on its own (direct link, no-JS fallback via the <a href>
// this progressively enhances), it's just not the only way in anymore.
document.addEventListener('DOMContentLoaded', function () {
    var trigger = document.getElementById('admin-login-trigger');
    var dropdown = document.getElementById('admin-login-dropdown');
    var form = document.getElementById('admin-login-form');
    var csrfField = document.getElementById('admin-login-csrf');
    var errorEl = document.getElementById('admin-login-error');
    var usernameField = document.getElementById('admin-login-username');
    if (!trigger || !dropdown || !form) return;

    var tokenFetched = false;

    function fetchCsrf() {
        fetch('admin/csrf.php', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                csrfField.value = data.csrf_token || '';
                tokenFetched = true;
                if (data.logged_in) {
                    window.location.href = 'admin/index.php';
                }
            })
            .catch(function () {
                // Leave the form as-is — submitting without a token just
                // fails with the normal "sesión expiró" message below.
            });
    }

    function openDropdown() {
        dropdown.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
        if (!tokenFetched) fetchCsrf();
        usernameField.focus();
    }

    function closeDropdown() {
        dropdown.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
        errorEl.textContent = '';
    }

    trigger.addEventListener('click', function (e) {
        e.preventDefault();
        if (dropdown.hidden) {
            openDropdown();
        } else {
            closeDropdown();
        }
    });

    document.addEventListener('click', function (e) {
        if (!dropdown.hidden && !dropdown.contains(e.target) && e.target !== trigger) {
            closeDropdown();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !dropdown.hidden) {
            closeDropdown();
            trigger.focus();
        }
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var submitBtn = form.querySelector('.admin-login-submit');
        submitBtn.disabled = true;
        errorEl.textContent = '';

        fetch('admin/login.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(form)
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    window.location.href = 'admin/index.php';
                } else {
                    errorEl.textContent = data.error || 'Usuario o contraseña incorrectos.';
                    submitBtn.disabled = false;
                    // A stale/failed token isn't reusable — get a fresh
                    // one so a second attempt doesn't also fail on that.
                    tokenFetched = false;
                    fetchCsrf();
                }
            })
            .catch(function () {
                errorEl.textContent = 'No se pudo conectar. Probá de nuevo.';
                submitBtn.disabled = false;
            });
    });
});


//modal
document.addEventListener('DOMContentLoaded', function () {
    // Event delegation throughout (listening on document, not on the
    // individual .openModalLink/.close elements) because the play
    // modals are built after this runs — see renderObras() below, which
    // fetches obras.json and injects the cards/modals into the page.
    // Direct per-element listeners set up here would simply miss
    // anything created afterward.

    // Tracks the currently open modal and whatever triggered it, so
    // Escape knows what to close and focus can return to the card that
    // opened it (a keyboard user's focus would otherwise be left
    // behind on a now-hidden trigger with no indication where they are).
    var currentModal = null;
    var lastTrigger = null;

    function openModal(modal, trigger) {
        currentModal = modal;
        lastTrigger = trigger || null;
        modal.style.display = 'block';
        document.body.classList.add('modal-open');
        var iframe = modal.querySelector('iframe[data-src]');
        if (iframe) {
            iframe.setAttribute('src', iframe.getAttribute('data-src'));
        }
        var closeBtn = modal.querySelector('.close');
        if (closeBtn) closeBtn.focus();
    }

    function closeModal(modal) {
        modal.style.display = 'none';
        document.body.classList.remove('modal-open');
        var iframe = modal.querySelector('iframe[data-src]');
        if (iframe) {
            iframe.setAttribute('src', '');
        }
        if (modal === currentModal) {
            currentModal = null;
            if (lastTrigger) {
                lastTrigger.focus();
                lastTrigger = null;
            }
        }
    }

    document.addEventListener('click', function (event) {
        var link = event.target.closest('.openModalLink');
        if (link) {
            event.preventDefault();
            var modal = document.getElementById(link.getAttribute('data-modal'));
            if (modal) openModal(modal, link);
            return;
        }
        var closer = event.target.closest('.close');
        if (closer) {
            var modalToClose = document.getElementById(closer.getAttribute('data-modal'));
            if (modalToClose) closeModal(modalToClose);
        }
    });

    // Close the modal if user clicks outside of it
    window.onclick = function (event) {
        if (event.target.classList.contains('modal')) {
            closeModal(event.target);
        }
    };

    // Escape closes the open modal — with no backdrop to click, this is
    // the only way a keyboard-only user can dismiss it. Tab/Shift+Tab
    // are trapped within it (wrapping from the last focusable element
    // back to the first, and vice versa) — aria-modal="true" tells
    // assistive tech to treat the rest of the page as inert, but Tab
    // would otherwise still physically move focus into it.
    document.addEventListener('keydown', function (event) {
        if (!currentModal) return;

        if (event.key === 'Escape' || event.key === 'Esc') {
            closeModal(currentModal);
            return;
        }

        if (event.key === 'Tab') {
            var focusable = currentModal.querySelectorAll(
                'a[href], button:not([disabled]), textarea, input, select, [tabindex]:not([tabindex="-1"])'
            );
            if (!focusable.length) return;
            var first = focusable[0];
            var last = focusable[focusable.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }
    });
});


//translation
document.addEventListener('DOMContentLoaded', function () {
    const langCurrent = document.getElementById('lang-current');
    const langDropdown = document.getElementById('lang-dropdown');

    function wrapLetters(element) {
        const text = element.textContent;
        element.innerHTML = '';
        Array.from(text).forEach((char, i) => {
            const span = document.createElement('span');
            span.textContent = char === ' ' ? ' ' : char;
            span.style.setProperty('--i', i);
            element.appendChild(span);
        });
    }

    function updateTranslations(translations, language) {
        const translation = translations[language];
        if (translation) {
            document.querySelectorAll('[data-section][data-translate]').forEach(element => {
                const section = element.getAttribute('data-section');
                const key = element.getAttribute('data-translate');
                if (translation[section] && translation[section][key]) {
                    const value = translation[section][key];
                    if (element.hasAttribute('data-translate-placeholder')) {
                        element.setAttribute('placeholder', value);
                    } else if (element.hasAttribute('data-translate-value')) {
                        element.setAttribute('value', value);
                    } else {
                        element.textContent = value;
                        if (element.classList.contains('header-eyebrow')) {
                            wrapLetters(element);
                        }
                    }
                }
            });
        }
    }

    async function fetchTranslations() {
        try {
            const response = await fetch('translations.json');
            if (!response.ok) {
                throw new Error('Network response was not ok ' + response.statusText);
            }
            const translations = await response.json();

            const allLanguages = ['en', 'es', 'pt'];
            let currentLanguage = 'es';

            function setLanguage(lang) {
                currentLanguage = lang;
                updateTranslations(translations, lang);
                langCurrent.textContent = lang.toUpperCase();
                langDropdown.innerHTML = allLanguages
                    .filter(l => l !== lang)
                    .map(l => `<div class="lang-selector__option" data-language="${l}">${l.toUpperCase()}</div>`)
                    .join('');
            }

            setLanguage('es');

            langCurrent.addEventListener('click', function (e) {
                e.stopPropagation();
                langDropdown.classList.toggle('open');
            });

            langDropdown.addEventListener('click', function (e) {
                const option = e.target.closest('.lang-selector__option');
                if (option) {
                    setLanguage(option.getAttribute('data-language'));
                    langDropdown.classList.remove('open');
                }
            });

            document.addEventListener('click', function () {
                langDropdown.classList.remove('open');
            });

        } catch (error) {
            console.error('There has been a problem with your fetch operation:', error);
        }
    }

    fetchTranslations();
});

// Contact form — AJAX submit via Formspree
(function () {
    const successMessages = {
        en: 'Your message has been sent',
        es: 'Su mensaje ha sido enviado',
        pt: 'Sua mensagem foi enviada'
    };

    const errorMessages = {
        en: 'There was a problem sending your message. Please try again in a moment.',
        es: 'Hubo un problema al enviar tu mensaje. Por favor, intentá de nuevo en un momento.',
        pt: 'Houve um problema ao enviar sua mensagem. Por favor, tente novamente em instantes.'
    };

    const form = document.querySelector('.contact-form');
    const successEl = document.getElementById('form-success');
    const errorEl = document.getElementById('form-error');

    if (!form || !successEl || !errorEl) return;

    // Both messages are aria-live regions (see index.html) so a screen
    // reader announces whichever one appears — neither was reachable
    // before without stumbling onto it manually. Hiding one whenever
    // the other shows keeps a stale message from lingering underneath.
    function showSuccess(text) {
        errorEl.style.display = 'none';
        errorEl.textContent = '';
        successEl.textContent = text;
        successEl.style.display = 'block';
    }

    function showError(text) {
        successEl.style.display = 'none';
        successEl.textContent = '';
        errorEl.textContent = text;
        errorEl.style.display = 'block';
    }

    form.addEventListener('submit', async function (e) {
        e.preventDefault();

        const data = new FormData(form);
        const lang = (document.getElementById('lang-current')?.textContent || 'en').toLowerCase();

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: data,
                headers: { 'Accept': 'application/json' }
            });

            if (response.ok) {
                showSuccess(successMessages[lang] || successMessages.en);
                form.reset();
            } else {
                showError(errorMessages[lang] || errorMessages.en);
            }
        } catch (err) {
            console.error('Form submission error:', err);
            showError(errorMessages[lang] || errorMessages.en);
        }
    });

    // Institucional tabs: click a tab to show its panel and hide the rest.
    const institucionalTabs = document.querySelectorAll('.institucional-tab');
    const institucionalPanels = document.querySelectorAll('.institucional-panel');
    institucionalTabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            const target = tab.getAttribute('data-tab');

            institucionalTabs.forEach(function (t) {
                t.classList.remove('active');
                t.setAttribute('aria-selected', 'false');
            });
            tab.classList.add('active');
            tab.setAttribute('aria-selected', 'true');

            institucionalPanels.forEach(function (panel) {
                panel.classList.toggle('active', panel.getAttribute('data-tab-panel') === target);
            });

            // The timeline is measured in pixels (see atp.js's staggered
            // layout and scroll-reveal below) — while its tab panel is
            // display:none, every measurement reads 0, so both need a
            // recompute once it's actually shown. Stagger first: reveal
            // checks each item's current position, which stagger just set.
            if (target === 'participaciones') {
                if (window.refreshTimelineStagger) window.refreshTimelineStagger();
                if (window.refreshTimelineReveal) window.refreshTimelineReveal();
            } else if (window.resetTimelineReveal) {
                // Leaving the tab — reset so the reveal plays again from
                // scratch next time Participaciones is opened.
                window.resetTimelineReveal();
            }
        });
    });
})();

// "Quienes somos" coverflow carousel: one active card up front, the
// rest stacked behind left/right. Auto-advances, pauses on hover, and
// has prev/next arrows.
(function () {
    var carousel = document.querySelector('.about-carousel');
    var cards = document.querySelectorAll('.about-wrapper .content');
    if (!carousel || !cards.length) return;

    var current = 0;
    var count = cards.length;
    var AUTO_MS = 3400;
    var timer = null;

    function posClass(offset) {
        if (offset === 0) return 'about-card--active';
        if (offset === 1) return 'about-card--next1';
        if (offset === -1) return 'about-card--prev1';
        if (offset === 2) return 'about-card--next2';
        if (offset === -2) return 'about-card--prev2';
        return null;
    }

    function render() {
        cards.forEach(function (card, i) {
            var diff = i - current;
            // Shortest circular distance (e.g. with 5 cards, +4 is really -1).
            if (diff > count / 2) diff -= count;
            if (diff < -count / 2) diff += count;

            card.classList.remove(
                'about-card--active', 'about-card--next1', 'about-card--prev1',
                'about-card--next2', 'about-card--prev2'
            );
            var cls = posClass(diff);
            if (cls) card.classList.add(cls);
        });
    }

    function goTo(index) {
        current = ((index % count) + count) % count;
        render();
    }

    function next() { goTo(current + 1); }
    function prev() { goTo(current - 1); }

    function startAuto() {
        stopAuto();
        timer = setInterval(next, AUTO_MS);
    }
    function stopAuto() {
        if (timer) clearInterval(timer);
        timer = null;
    }

    carousel.querySelector('.about-carousel-arrow--next').addEventListener('click', function () {
        next();
        startAuto();
    });
    carousel.querySelector('.about-carousel-arrow--prev').addEventListener('click', function () {
        prev();
        startAuto();
    });

    carousel.addEventListener('mouseenter', stopAuto);
    carousel.addEventListener('mouseleave', startAuto);

    // Hovering the ACTIVE card just enlarges it (pure CSS :hover, see
    // styles.scss). Hovering/holding beside it — to its left or right —
    // steps the carousel that way (same as the arrows), repeating while
    // the cursor/finger stays on that side. Same logic drives mouse
    // hover on desktop and touch-and-hold on mobile.
    var hoverNavTimer = null;
    var hoverNavSide = null;

    function clearHoverNav() {
        if (hoverNavTimer) clearInterval(hoverNavTimer);
        hoverNavTimer = null;
        hoverNavSide = null;
    }

    var HOVER_NAV_MS = 2200; // a bit faster than AUTO_MS while the mouse stays on a side

    function startHoverNav(side) {
        if (hoverNavSide === side) return;
        clearHoverNav();
        hoverNavSide = side;
        var step = side === 'left' ? prev : next;
        step();
        hoverNavTimer = setInterval(step, HOVER_NAV_MS);
    }

    function handlePointerPosition(clientX, clientY) {
        var activeCard = carousel.querySelector('.about-card--active');
        if (!activeCard) {
            clearHoverNav();
            return;
        }
        var rect = activeCard.getBoundingClientRect();
        var overActive = clientX >= rect.left && clientX <= rect.right &&
            clientY >= rect.top && clientY <= rect.bottom;
        if (overActive) {
            clearHoverNav();
            return;
        }
        var isLeft = clientX < rect.left + rect.width / 2;
        startHoverNav(isLeft ? 'left' : 'right');
    }

    carousel.addEventListener('mousemove', function (e) {
        handlePointerPosition(e.clientX, e.clientY);
    });
    carousel.addEventListener('mouseleave', clearHoverNav);

    carousel.addEventListener('touchstart', function (e) {
        var t = e.touches[0];
        if (t) handlePointerPosition(t.clientX, t.clientY);
    }, { passive: true });
    carousel.addEventListener('touchmove', function (e) {
        var t = e.touches[0];
        if (t) handlePointerPosition(t.clientX, t.clientY);
    }, { passive: true });
    carousel.addEventListener('touchend', clearHoverNav);
    carousel.addEventListener('touchcancel', clearHoverNav);

    render();
    startAuto();
})();

// Menu links (#about, #institucional, etc.): scroll there with JS
// instead of a native instant anchor jump. The native jump happens
// before images/late layout settle, so the browser overshoots and then
// snaps back once things finish loading — this waits for a stable
// target position instead.
(function () {
    var links = document.querySelectorAll('a[href^="#"]');
    links.forEach(function (link) {
        var id = link.getAttribute('href');
        if (!id || id.length < 2) return;
        var target = document.querySelector(id);
        if (!target) return;

        link.addEventListener('click', function (e) {
            e.preventDefault();
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            if (history.pushState) history.pushState(null, '', id);
        });
    });
})();

// Press-and-hold scroll button: keeps scrolling down while held, flips
// to scroll up once the page is at the bottom (and back again at top).
(function () {
    var btn = document.querySelector('.scroll-button');
    if (!btn) return;

    var icon = btn.querySelector('i');
    var direction = 'down';
    var scrollTimer = null;
    var STEP = 12;

    function atBottom() {
        return window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 2;
    }
    function atTop() {
        return window.scrollY <= 2;
    }

    function updateDirection() {
        if (atBottom()) direction = 'up';
        else if (atTop()) direction = 'down';
        icon.className = direction === 'down' ? 'fa fa-chevron-down' : 'fa fa-chevron-up';
    }

    function startScrolling() {
        stopScrolling();
        scrollTimer = setInterval(function () {
            window.scrollBy(0, direction === 'down' ? STEP : -STEP);
            updateDirection();
        }, 16);
    }
    function stopScrolling() {
        if (scrollTimer) clearInterval(scrollTimer);
        scrollTimer = null;
    }

    btn.addEventListener('mousedown', function (e) { e.preventDefault(); startScrolling(); });
    btn.addEventListener('touchstart', function (e) { e.preventDefault(); startScrolling(); }, { passive: false });
    ['mouseup', 'mouseleave', 'touchend', 'touchcancel'].forEach(function (evt) {
        btn.addEventListener(evt, stopScrolling);
    });

    window.addEventListener('scroll', updateDirection, { passive: true });
    updateDirection();
})();

// "Participaciones institucionales" timeline: stagger each card so it
// starts at the vertical midpoint of the previous one (alternating left/
// right), brick-wall style. Pure CSS can't do "half of the sibling's
// actual rendered height" since that height depends on live content
// (varying paragraph lengths, photos loading) — so this measures each
// .timeline-item after layout and sets its `top` in pixels.
(function () {
    var timeline = document.querySelector('.timeline');
    var items = document.querySelectorAll('.timeline-item');
    if (!timeline || !items.length) return;

    function layout() {
        // The timeline sits in a tab panel that's display:none until
        // "Participaciones institucionales" is selected — every
        // measurement would read 0 while hidden, so skip and wait for
        // the tab click (see the institucional-tab handler) to retry.
        if (timeline.offsetParent === null) return;

        // Below 700px, CSS switches every .timeline-item to a single
        // column in normal flow (position: relative, no left/right
        // alternation) — a leftover inline `top` from the desktop
        // stagger would still push it down from there, so clear it
        // instead of computing one.
        if (window.matchMedia('(max-width: 700px)').matches) {
            items.forEach(function (item) {
                item.style.top = '';
            });
            timeline.style.minHeight = '';
            return;
        }

        // Same gap the "half of the previous card" rule produces when
        // it's the one in control — used below so the floor case reads
        // the same as everywhere else, instead of butting flush.
        var MIN_GAP = 30;

        var tops = [];
        var heights = [];
        items.forEach(function (item, i) {
            var height = item.offsetHeight;
            var top = 0;
            if (i > 0) {
                // Starts at half the previous card's height...
                top = tops[i - 1] + heights[i - 1] / 2;
                // ...but never overlaps the card two back (same side of
                // the rail) — that card's own bottom plus a normal gap
                // is a hard floor.
                if (i > 1) {
                    top = Math.max(top, tops[i - 2] + heights[i - 2] + MIN_GAP);
                }
            }
            tops.push(top);
            heights.push(height);
            item.style.top = top + 'px';
        });

        var lastIndex = items.length - 1;
        timeline.style.minHeight = (tops[lastIndex] + heights[lastIndex]) + 'px';
    }

    window.refreshTimelineStagger = layout;

    window.addEventListener('load', layout);

    var resizeTimer;
    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(layout, 150);
    });

    // Photos load asynchronously and change their card's height.
    document.querySelectorAll('.timeline-photo').forEach(function (img) {
        if (img.tagName === 'IMG' && !img.complete) {
            img.addEventListener('load', layout);
        }
    });

    layout();
})();

// Each timeline card fades/slides up the first time it scrolls into
// view, then stays that way for good — .is-visible is only ever added,
// never removed. Cards reveal one at a time (left, right, left, right —
// DOM order), even when several cross the threshold in the same scroll
// tick, instead of all popping in together.
(function () {
    var items = document.querySelectorAll('.timeline-item');
    if (!items.length) return;

    var claimed = new Array(items.length).fill(false);
    var queue = [];
    var queueRunning = false;

    function runQueue() {
        if (!queue.length) {
            queueRunning = false;
            return;
        }
        queueRunning = true;
        queue.shift().classList.add('is-visible');
        setTimeout(runQueue, 250);
    }

    function reveal() {
        var allClaimed = true;
        items.forEach(function (item, i) {
            if (claimed[i]) return;
            if (item.offsetParent === null) {
                // Hidden (its tab panel isn't active) — getBoundingClientRect
                // would read all zeros here, which looks exactly like
                // "already scrolled into view" and would reveal the card
                // before its panel is ever actually shown.
                allClaimed = false;
                return;
            }
            if (item.getBoundingClientRect().top < window.innerHeight * 0.85) {
                claimed[i] = true;
                queue.push(item);
            } else {
                allClaimed = false;
            }
        });
        if (!queueRunning && queue.length) runQueue();
        if (allClaimed) window.removeEventListener('scroll', reveal);
    }

    // Called when navigating away from the Participaciones tab, so the
    // reveal plays again from scratch next time it's opened instead of
    // the cards just sitting there already-visible.
    function resetReveal() {
        queue.length = 0;
        queueRunning = false;
        for (var i = 0; i < claimed.length; i++) claimed[i] = false;
        items.forEach(function (item) { item.classList.remove('is-visible'); });
        // reveal() unsubscribes itself once every card is claimed; put it
        // back so scrolling triggers the reveal again after a reset.
        window.addEventListener('scroll', reveal, { passive: true });
    }

    window.refreshTimelineReveal = reveal;
    window.resetTimelineReveal = resetReveal;
    window.addEventListener('scroll', reveal, { passive: true });
    reveal();
})();

// Otros proyectos: click-to-expand accordion rows (AcceDER I / AcceDER
// II / Podcast) — only one open at a time, height set inline from
// scrollHeight so the panel can transition smoothly via CSS.
(function () {
    var items = document.querySelectorAll('.op-block');
    if (!items.length) return;

    function openItem(item) {
        var panel = item.querySelector('.op-block-panel');
        item.classList.add('is-open');
        item.querySelector('.op-block-trigger').setAttribute('aria-expanded', 'true');
        panel.style.maxHeight = panel.scrollHeight + 'px';
    }

    function closeItem(item) {
        var panel = item.querySelector('.op-block-panel');
        // Starting a transition from "auto" doesn't animate, so pin the
        // current pixel height first, then collapse to 0 on the next frame.
        panel.style.maxHeight = panel.scrollHeight + 'px';
        item.classList.remove('is-open');
        item.querySelector('.op-block-trigger').setAttribute('aria-expanded', 'false');
        requestAnimationFrame(function () {
            panel.style.maxHeight = '0px';
        });
    }

    items.forEach(function (item) {
        if (item.classList.contains('is-open')) openItem(item);

        item.querySelector('.op-block-trigger').addEventListener('click', function () {
            var isOpen = item.classList.contains('is-open');
            items.forEach(function (other) {
                if (other !== item && other.classList.contains('is-open')) closeItem(other);
            });
            if (isOpen) {
                closeItem(item);
            } else {
                openItem(item);
            }
        });
    });

    // Media (the AcceDER I video, podcast images) loading after the
    // initial openItem() call changes scrollHeight — recompute once
    // everything's in so the open panel isn't clipped.
    window.addEventListener('load', function () {
        document.querySelectorAll('.op-block.is-open .op-block-panel').forEach(function (panel) {
            panel.style.maxHeight = panel.scrollHeight + 'px';
        });
    });
})();

// Apoyos marquee: slides shrink-wrap to each logo's own width (equal
// padding on every slide is what keeps the gaps even — see styles.scss),
// so unlike the fixed-width marquee elsewhere, the CSS keyframe can't
// hardcode a per-slide-width scroll distance. The track holds the same
// 10 logos twice back to back, so half its rendered width is exactly
// the distance to scroll for a seamless loop.
(function () {
    var track = document.querySelector('.slider--apoyos .slide-track');
    if (!track) return;

    function setScrollDistance() {
        var distance = track.scrollWidth / 2;
        track.style.setProperty('--apoyos-scroll-distance', '-' + distance + 'px');
    }

    setScrollDistance();
    window.addEventListener('load', setScrollDistance);
    window.addEventListener('resize', setScrollDistance);
})();

// Obras teatrales: cards + modals aren't hand-authored in index.html
// (see #projects-grid / #project-modals there) — they're built here
// from obras.json, so adding/removing/editing a play from the admin
// panel (admin/obras.php) doesn't need any HTML changes to show up.
(function () {
    var grid = document.getElementById('projects-grid');
    var modalsContainer = document.getElementById('project-modals');
    if (!grid || !modalsContainer) return;

    function escapeHtml(str) {
        return String(str == null ? '' : str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // "Rol: Nombre" per line -> <li><strong>Rol:</strong> Nombre</li>.
    function fichaListHTML(text) {
        return (text || '').split('\n').map(function (line) { return line.trim(); }).filter(Boolean).map(function (line) {
            var i = line.indexOf(':');
            if (i === -1) return '<li>' + escapeHtml(line) + '</li>';
            var role = line.slice(0, i + 1);
            var name = line.slice(i + 1).trim();
            return '<li><strong>' + escapeHtml(role) + '</strong> ' + escapeHtml(name) + '</li>';
        }).join('');
    }

    function cardHTML(play) {
        var modalId = 'modal-obra-' + play.id;
        var thumb = play.images && play.images[0] ? 'assets/imgs/' + play.images[0] : '';
        return (
            '<button type="button" class="project-card openModalLink" data-modal="' + modalId + '" aria-label="Ver ficha de ' + escapeHtml(play.title) + '">' +
                '<span class="project-img project-img--hover">' +
                    '<img class="project-img__default" src="' + escapeHtml(thumb) + '" alt="" loading="lazy">' +
                    '<img class="project-img__hover" src="' + escapeHtml(thumb) + '" alt="" loading="lazy">' +
                '</span>' +
            '</button>'
        );
    }

    function modalHTML(play) {
        var modalId = 'modal-obra-' + play.id;
        var titleId = modalId + '-title';
        var hasVideo = !!play.video;
        var images = play.images || [];

        var paragraphsHtml = (play.paragraphs || []).map(function (p) {
            return '<p class="play-modal-text">' + escapeHtml(p) + '</p>';
        }).join('');

        var mainHtml = hasVideo
            ? '<video src="assets/imgs/' + escapeHtml(play.video) + '" controls preload="metadata" playsinline></video>'
            : (images[0] ? '<img src="assets/imgs/' + escapeHtml(images[0]) + '" alt="' + escapeHtml(play.title) + '">' : '');

        var thumbsHtml = '';
        if (hasVideo) {
            thumbsHtml += '<button type="button" class="play-modal-thumb play-modal-thumb--video is-active" data-video="assets/imgs/' + escapeHtml(play.video) + '" aria-label="Ver video">' +
                '<video src="assets/imgs/' + escapeHtml(play.video) + '" muted preload="metadata" playsinline></video></button>';
        }
        thumbsHtml += images.map(function (img, i) {
            var active = (!hasVideo && i === 0) ? ' is-active' : '';
            return '<button type="button" class="play-modal-thumb' + active + '" data-img="assets/imgs/' + escapeHtml(img) + '" data-alt="' + escapeHtml(play.title) + '" aria-label="Ver foto">' +
                '<img src="assets/imgs/' + escapeHtml(img) + '" alt="" loading="lazy"></button>';
        }).join('');

        var notesHtml = play.notes ? '<p class="play-modal-notes">' + escapeHtml(play.notes) + '</p>' : '';

        var fichaHtml = play.ficha ? (
            '<h4 class="play-modal-subtitle">Ficha técnico-artística</h4>' +
            '<ul class="play-modal-ficha">' + fichaListHTML(play.ficha) + '</ul>'
        ) : '';

        var sponsors = play.sponsors || [];
        var sponsorsHtml = sponsors.length ? (
            '<p class="play-modal-notes">Realizada con el apoyo de:</p>' +
            '<div class="play-modal-sponsors">' + sponsors.map(function (s) {
                return '<img src="assets/imgs/' + escapeHtml(s.src) + '" alt="' + escapeHtml(s.alt || '') + '">';
            }).join('') + '</div>'
        ) : '';

        return (
            '<div id="' + modalId + '" class="modal" role="dialog" aria-modal="true" aria-labelledby="' + titleId + '">' +
                '<div class="modal-content play-modal">' +
                    '<button type="button" class="close" data-modal="' + modalId + '" aria-label="Cerrar">&times;</button>' +
                    '<h3 class="play-modal-title" id="' + titleId + '">' + escapeHtml(play.title) + '</h3>' +
                    paragraphsHtml +
                    '<div class="play-modal-media">' +
                        '<div class="play-modal-media-main">' + mainHtml + '</div>' +
                        '<div class="play-modal-thumbs">' + thumbsHtml + '</div>' +
                    '</div>' +
                    notesHtml +
                    fichaHtml +
                    sponsorsHtml +
                '</div>' +
            '</div>'
        );
    }

    fetch('obras.json')
        .then(function (response) {
            if (!response.ok) throw new Error('Network response was not ok ' + response.statusText);
            return response.json();
        })
        .then(function (data) {
            var plays = data.plays || [];
            grid.innerHTML = plays.map(cardHTML).join('');
            modalsContainer.innerHTML = plays.map(modalHTML).join('');
        })
        .catch(function (error) {
            console.error('There has been a problem fetching obras.json:', error);
        });

    // Hero + thumbnails gallery inside a play modal — clicking a thumb
    // swaps its media (video or image) into the big main slot. Delegated
    // on the container since the modals above are injected after this
    // runs, not present at DOMContentLoaded time.
    modalsContainer.addEventListener('click', function (event) {
        var thumb = event.target.closest('.play-modal-thumb');
        if (!thumb) return;
        var block = thumb.closest('.play-modal-media');
        var main = block.querySelector('.play-modal-media-main');
        var thumbs = block.querySelectorAll('.play-modal-thumb');
        var videoSrc = thumb.getAttribute('data-video');
        var imgSrc = thumb.getAttribute('data-img');
        var alt = thumb.getAttribute('data-alt') || '';

        thumbs.forEach(function (t) { t.classList.remove('is-active'); });
        thumb.classList.add('is-active');

        if (videoSrc) {
            main.innerHTML = '<video src="' + videoSrc + '" controls preload="metadata" playsinline></video>';
        } else if (imgSrc) {
            main.innerHTML = '<img src="' + imgSrc + '" alt="' + alt + '">';
        }
    });
})();
