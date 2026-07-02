import '../css/app.css';

/*
 | Public site JavaScript (core, loaded on every page).
 |
 | Livewire (loaded via @livewireScripts) owns and boots Alpine. We must NOT
 | import or start a second Alpine instance — instead we register our custom
 | components on the `alpine:init` event that Livewire dispatches before start.
 |
 | The free-tool Alpine components live in a separate entry (resources/js/tools.js)
 | that the layout only loads on /tools/* pages, so the homepage and articles don't
 | ship the (large) tool/image-processing code.
 */

/* Clipboard write that also works outside secure contexts (navigator.clipboard
   only exists on HTTPS/localhost — not on http://gadget-drop.test in local dev).
   Resolves true only when the text actually reached the clipboard. */
function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) {
        return navigator.clipboard.writeText(text).then(() => true).catch(() => legacyCopy(text));
    }
    return Promise.resolve(legacyCopy(text));
}

function legacyCopy(text) {
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.setAttribute('readonly', 'readonly');
    ta.style.cssText = 'position:fixed;top:0;left:0;opacity:0;pointer-events:none';
    document.body.appendChild(ta);
    ta.select();
    ta.setSelectionRange(0, text.length);
    let ok = false;
    try {
        ok = document.execCommand('copy');
    } catch (e) {
        ok = false;
    }
    ta.remove();
    return ok;
}

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;

    /* ── Header: megamenus + mobile menu ─────────────────────── */
    Alpine.data('siteHeader', () => ({
        activeMenu: null,
        mobileOpen: false,
        mobileSection: null,
        toggle(name) {
            this.activeMenu = this.activeMenu === name ? null : name;
        },
        openMobileSearch() {
            this.mobileOpen = true;
            this.mobileSection = 'search';
            this.$nextTick(() => this.$refs.mobileSearchInput?.focus());
        },
        toggleMobileSection(name) {
            this.mobileSection = this.mobileSection === name ? null : name;
        },
        closeAll() {
            this.activeMenu = null;
            this.mobileOpen = false;
        },
    }));

    /* ── Home hero carousel ──────────────────────────────────── */
    Alpine.data('heroCarousel', (count) => ({
        active: 0,
        paused: false,
        count,
        timer: null,
        touchX: 0,
        touchY: 0,
        init() {
            this.start();
        },
        start() {
            if (this.count <= 1) return;
            this.timer = setInterval(() => {
                if (!this.paused) this.next();
            }, 6000);
        },
        next() {
            this.active = (this.active + 1) % this.count;
        },
        prev() {
            this.active = (this.active - 1 + this.count) % this.count;
        },
        go(i) {
            this.active = i;
        },
        touchStart(e) {
            const t = e.changedTouches[0];
            this.touchX = t.clientX;
            this.touchY = t.clientY;
        },
        touchEnd(e) {
            if (this.count <= 1) return;
            const t = e.changedTouches[0];
            const dx = t.clientX - this.touchX;
            const dy = t.clientY - this.touchY;
            // Only treat as a swipe if it's mostly horizontal and past the
            // threshold, so vertical page scrolling isn't hijacked.
            if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy)) {
                dx < 0 ? this.next() : this.prev();
            }
        },
        destroy() {
            if (this.timer) clearInterval(this.timer);
        },
    }));

    /* ── Drop Price daily game (client state) ──────────────────────
       The day's answer (the price) NEVER reaches the browser before the
       reveal — every guess is scored server-side in the Livewire
       `drop-price` component. This Alpine layer owns ONLY client state:
       the localStorage play-streak, the one-play-per-day lockout, and the
       spoiler-free share text. It learns a round ended from the Livewire
       `dropprice-finished` event, which carries ordinal data only (won /
       number / guess count / band rows) — never the price. The binding is
       a declarative x-on (.window) so Livewire's wire:navigate teardown
       cleans it up; the lone manual handle (the copied timer) is cleared
       in destroy(). */
    Alpine.data('dropPrice', ({ number, max = 5, shareUrl = '' }) => ({
        number,
        max,
        shareUrl,
        stats: null,
        alreadyPlayed: false, // finished THIS puzzle on a previous visit (lockout)
        justFinished: false, // finished it during this visit (live reveal)
        won: false,
        guesses: 0,
        emojiRows: '',
        copied: false,
        copyTimer: null,
        rafId: null,

        // The default shape for a player who has never played.
        fresh() {
            return {
                v: 1,
                lastPlayedNumber: null,
                lastPlayedDate: null,
                playStreak: 0,
                bestPlayStreak: 0,
                winStreak: 0,
                bestWinStreak: 0,
                totalPlays: 0,
                totalWins: 0,
                lastWon: false,
                lastGuesses: 0,
                lastResultEmoji: '',
            };
        },

        init() {
            this.load();
            // One-play-per-day lockout: if this exact puzzle is already
            // recorded as played, restore the finished view instead of the
            // input. UX only — replaying leaks nothing (the answer never ships).
            if (this.stats.lastPlayedNumber === this.number) {
                this.alreadyPlayed = true;
                this.won = this.stats.lastWon;
                this.guesses = this.stats.lastGuesses;
                this.emojiRows = this.stats.lastResultEmoji;
            }
        },

        load() {
            try {
                const raw = localStorage.getItem('gadgetdrop_dropprice');
                this.stats = raw ? { ...this.fresh(), ...JSON.parse(raw) } : this.fresh();
            } catch (e) {
                this.stats = this.fresh();
            }
        },

        persist() {
            try {
                localStorage.setItem('gadgetdrop_dropprice', JSON.stringify(this.stats));
            } catch (e) {
                /* private mode / quota — the streak just won't persist */
            }
        },

        get finished() {
            return this.alreadyPlayed || this.justFinished;
        },

        // Prompt to save (= subscribe) after any win, or once a 2+ day habit
        // is worth preserving.
        get showSavePrompt() {
            return this.won || (this.stats ? this.stats.playStreak : 0) >= 2;
        },

        // UTC day key — the puzzle resets at midnight UTC.
        todayKey() {
            return new Date().toISOString().slice(0, 10);
        },
        dayGap(from, to) {
            return Math.round((Date.parse(to) - Date.parse(from)) / 86400000);
        },

        buildRows(results) {
            const emoji = { freezing: '🥶', warm: '😊', hot: '🔥', nailed: '🎯' };
            return (results || []).map((r) => emoji[r.band] || '⬜').join(' ');
        },

        // Livewire signalled the round is over (ordinal data only).
        onFinished(detail) {
            // Idempotency: never count the same puzzle twice (a stray re-dispatch
            // or same-session replay) — just refresh the live view.
            if (this.stats.lastPlayedNumber !== detail.number) {
                const today = this.todayKey();
                const consecutive =
                    this.stats.lastPlayedDate &&
                    this.dayGap(this.stats.lastPlayedDate, today) === 1;

                this.stats.playStreak = consecutive ? this.stats.playStreak + 1 : 1;
                this.stats.totalPlays += 1;

                if (detail.won) {
                    this.stats.totalWins += 1;
                    this.stats.winStreak = consecutive ? this.stats.winStreak + 1 : 1;
                } else {
                    this.stats.winStreak = 0;
                }

                this.stats.bestPlayStreak = Math.max(this.stats.bestPlayStreak, this.stats.playStreak);
                this.stats.bestWinStreak = Math.max(this.stats.bestWinStreak, this.stats.winStreak);

                this.stats.lastPlayedNumber = detail.number;
                this.stats.lastPlayedDate = today;
                this.stats.lastWon = detail.won;
                this.stats.lastGuesses = detail.guesses;
                this.stats.lastResultEmoji = this.buildRows(detail.results);
                this.persist();
            }

            this.justFinished = true;
            this.won = detail.won;
            this.guesses = detail.guesses;
            this.emojiRows = this.buildRows(detail.results);
        },

        // Reveal flourish: count the price up to its real value. The server-
        // rendered text is already the final price, so reduced motion (or any
        // bail-out) simply leaves it untouched. Post-reveal only — the price is
        // in the DOM by the time this runs.
        countUp(el) {
            const target = parseInt(el.dataset.price, 10);
            if (!Number.isFinite(target)) return;
            if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
            const duration = 900;
            const from = Math.max(1, Math.round(target * 0.35));
            const start = performance.now();
            const tick = (now) => {
                const t = Math.min(1, (now - start) / duration);
                const eased = 1 - Math.pow(1 - t, 3);
                el.textContent = '$' + Math.round(from + (target - from) * eased).toLocaleString('en-US');
                if (t < 1) this.rafId = requestAnimationFrame(tick);
            };
            this.rafId = requestAnimationFrame(tick);
        },

        shareText() {
            const score = this.won ? `${this.guesses}/${this.max}` : `X/${this.max}`;
            const flag = this.won ? '🎯' : '❌';
            return `Drop Price #${this.number} ${flag} ${score}\n${this.emojiRows}\n${this.shareUrl}`;
        },

        share() {
            const text = this.shareText();
            // Native share sheet on touch devices only; on desktop the button
            // promises "Copied to clipboard ✓", so copying IS the share. A user
            // dismissing the sheet (AbortError) is a cancel, not a failure.
            const touch = window.matchMedia && window.matchMedia('(pointer: coarse)').matches;
            if (touch && navigator.share) {
                navigator.share({ title: 'Drop Price', text }).catch((e) => {
                    if (!e || e.name !== 'AbortError') this.copy(text);
                });
                return;
            }
            this.copy(text);
        },

        copy(text) {
            copyText(text || this.shareText()).then((ok) => {
                if (!ok) return;
                this.copied = true;
                clearTimeout(this.copyTimer);
                this.copyTimer = setTimeout(() => (this.copied = false), 2000);
            });
        },

        destroy() {
            clearTimeout(this.copyTimer);
            cancelAnimationFrame(this.rafId);
        },
    }));

    /* ── Home category carousel ──────────────────────────────────
       Drag-to-scroll cards, prev/next arrows, and a custom progress
       bar (the native scrollbar is hidden via .scrollbar-hide). All
       listeners are Alpine x-on directives (incl. the .window ones),
       so Livewire's navigate teardown cleans them up — no manual
       addEventListener, so no destroy() needed. */
    Alpine.data('categoryCarousel', () => ({
        canLeft: false,
        canRight: false,
        scrollable: false,
        thumbWidth: 100, // % of the bar the thumb fills
        thumbLeft: 0, // % offset of the thumb from the left
        // card drag-to-scroll
        cardDragging: false,
        moved: false,
        startX: 0,
        startScroll: 0,
        // progress-bar drag
        barDragging: false,
        // timestamp of the last page (vertical) scroll — used to keep the
        // horizontal wheel-scroll secondary to an in-progress page scroll.
        lastPageScrollAt: 0,
        init() {
            // scrollWidth is known immediately (cards carry width/height),
            // but wait a tick so layout is settled before measuring.
            this.$nextTick(() => this.update());
        },
        update() {
            const el = this.$refs.track;
            if (!el) return;
            const max = el.scrollWidth - el.clientWidth;
            this.scrollable = max > 1;
            this.canLeft = el.scrollLeft > 1;
            this.canRight = el.scrollLeft < max - 1;
            const ratio = el.scrollWidth > 0 ? el.clientWidth / el.scrollWidth : 1;
            this.thumbWidth = Math.min(100, ratio * 100);
            this.thumbLeft = max > 0 ? (el.scrollLeft / max) * (100 - this.thumbWidth) : 0;
        },
        page(dir) {
            const el = this.$refs.track;
            if (el) el.scrollBy({ left: dir * el.clientWidth * 0.85, behavior: 'smooth' });
        },
        // Record page (vertical) scrolls so the wheel handler can tell an
        // in-progress page scroll apart from a deliberate hover-and-wheel.
        onPageScroll() {
            this.lastPageScrollAt = Date.now();
        },
        // Translate a vertical mouse wheel into horizontal scroll (the natural
        // desktop gesture) — but keep it SECONDARY to the page scroll. While the
        // user is actively scrolling the page vertically, a continuous wheel pass
        // crossing the strip flows straight through; horizontal only engages once
        // the page has been still for a moment (a deliberate hover + wheel). Also
        // release at the ends so the page can keep scrolling past us.
        onWheel(e) {
            const el = this.$refs.track;
            if (!el || e.deltaY === 0) return;
            if (Math.abs(e.deltaX) > Math.abs(e.deltaY)) return; // trackpad already pans
            const max = el.scrollWidth - el.clientWidth;
            if (max <= 0) return;
            if (Date.now() - this.lastPageScrollAt < 300) return; // page still in motion
            const atStart = el.scrollLeft <= 0;
            const atEnd = el.scrollLeft >= max - 1;
            if ((e.deltaY < 0 && atStart) || (e.deltaY > 0 && atEnd)) return;
            e.preventDefault();
            el.scrollLeft += e.deltaY;
        },
        // ── drag the cards directly ──
        cardDown(e) {
            this.cardDragging = true;
            this.moved = false;
            this.startX = e.pageX;
            this.startScroll = this.$refs.track.scrollLeft;
        },
        // ── click/drag the progress bar to seek ──
        barTo(e) {
            const el = this.$refs.track;
            const bar = this.$refs.bar;
            if (!el || !bar) return;
            const rect = bar.getBoundingClientRect();
            const max = el.scrollWidth - el.clientWidth;
            let pct = (e.clientX - rect.left) / rect.width;
            pct = Math.max(0, Math.min(1, pct));
            el.scrollLeft = pct * max;
        },
        barDown(e) {
            e.preventDefault(); // don't start a text selection
            this.barDragging = true;
            this.barTo(e);
        },
        // ── shared window-level move/up (handles both drags) ──
        onMove(e) {
            if (this.cardDragging) {
                const dx = e.pageX - this.startX;
                if (Math.abs(dx) > 4) this.moved = true; // past the click threshold
                this.$refs.track.scrollLeft = this.startScroll - dx;
            } else if (this.barDragging) {
                this.barTo(e);
            }
        },
        onUp() {
            this.cardDragging = false;
            this.barDragging = false;
        },
        // Swallow the click that ends a drag so we don't follow the link.
        onClick(e) {
            if (this.moved) {
                e.preventDefault();
                e.stopPropagation();
                this.moved = false;
            }
        },
    }));

    /* ── Reading progress bar (post page) ────────────────────── */
    Alpine.data('readingProgress', () => ({
        progress: 0,
        onScroll: null,
        init() {
            this.update();
            // Keep the handler reference so destroy() can detach it — with
            // wire:navigate the component is torn down on every page swap and
            // an anonymous listener would leak once per navigation.
            this.onScroll = () => this.update();
            window.addEventListener('scroll', this.onScroll, { passive: true });
        },
        update() {
            const doc = document.documentElement;
            const total = doc.scrollHeight - doc.clientHeight;
            this.progress = total > 0 ? (doc.scrollTop / total) * 100 : 0;
        },
        destroy() {
            if (this.onScroll) window.removeEventListener('scroll', this.onScroll);
        },
    }));

    /* ── Share bar ───────────────────────────────────────────── */
    Alpine.data('shareBar', (shortUrl, title) => ({
        copied: false,
        canShare: typeof navigator !== 'undefined' && !!navigator.share,
        nativeShare() {
            navigator.share({ title, url: shortUrl }).catch(() => {});
        },
        copy() {
            copyText(shortUrl).then((ok) => {
                if (!ok) return;
                this.copied = true;
                setTimeout(() => (this.copied = false), 2000);
            });
        },
    }));

    /* ── Cookie consent banner ───────────────────────────────── */
    Alpine.data('cookieConsent', () => ({
        visible: false,
        init() {
            if (!localStorage.getItem('gadgetdrop_consent')) {
                setTimeout(() => (this.visible = true), 800);
            }
        },
        accept() {
            localStorage.setItem('gadgetdrop_consent', 'accepted');
            if (window.gtag) {
                window.gtag('consent', 'update', {
                    ad_storage: 'granted',
                    ad_user_data: 'granted',
                    ad_personalization: 'granted',
                    analytics_storage: 'granted',
                });
            }
            this.visible = false;
        },
        reject() {
            localStorage.setItem('gadgetdrop_consent', 'rejected');
            this.visible = false;
        },
    }));

    /* ── Cookies policy page accept/reject/reset ─────────────── */
    Alpine.data('cookiePrefs', () => ({
        status: null,
        init() {
            this.status = localStorage.getItem('gadgetdrop_consent');
        },
        accept() {
            localStorage.setItem('gadgetdrop_consent', 'accepted');
            if (window.gtag) {
                window.gtag('consent', 'update', {
                    ad_storage: 'granted',
                    ad_user_data: 'granted',
                    ad_personalization: 'granted',
                    analytics_storage: 'granted',
                });
            }
            this.status = 'accepted';
        },
        reject() {
            localStorage.setItem('gadgetdrop_consent', 'rejected');
            this.status = 'rejected';
        },
        reset() {
            localStorage.removeItem('gadgetdrop_consent');
            this.status = null;
        },
    }));
});

/* AdSense is currently disabled (config services.adsense.enabled). If it is
   re-enabled, slots on pages reached via wire:navigate must be re-pushed from a
   `livewire:navigated` listener (DOMContentLoaded does not re-fire on swaps). */

/* ── Global click/touch ripple effect ────────────────────────── */
(function ripple() {
    const SIZE = 80;
    const style = document.createElement('style');
    style.textContent = `
        @keyframes gadget-ripple { 0% { transform: scale(0); opacity: 1; } 100% { transform: scale(1); opacity: 0; } }
        .gadget-ripple { position: fixed; border-radius: 50%; pointer-events: none; z-index: 99999; animation: gadget-ripple ease-out forwards; transform-origin: center center; }
    `;
    document.head.appendChild(style);

    function spawn(x, y) {
        const el = document.createElement('div');
        el.className = 'gadget-ripple';
        el.style.cssText = [
            `left:${x - SIZE / 2}px`,
            `top:${y - SIZE / 2}px`,
            `width:${SIZE}px`,
            `height:${SIZE}px`,
            `border:1.5px solid rgba(99,102,241,0.6)`,
            `animation-duration:680ms`,
        ].join(';');
        document.body.appendChild(el);
        setTimeout(() => el.remove(), 750);
    }

    document.addEventListener('click', (e) => spawn(e.clientX, e.clientY));
    document.addEventListener('touchstart', (e) => {
        const t = e.changedTouches[0];
        spawn(t.clientX, t.clientY);
    }, { passive: true });
})();
