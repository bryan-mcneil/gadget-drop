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
            navigator.clipboard.writeText(shortUrl).then(() => {
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
