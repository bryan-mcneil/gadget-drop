import '../css/app.css';

/*
 | Public site JavaScript.
 |
 | Livewire (loaded via @livewireScripts) owns and boots Alpine. We must NOT
 | import or start a second Alpine instance — instead we register our custom
 | components on the `alpine:init` event that Livewire dispatches before start.
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
        init() {
            this.update();
            window.addEventListener('scroll', () => this.update(), { passive: true });
        },
        update() {
            const doc = document.documentElement;
            const total = doc.scrollHeight - doc.clientHeight;
            this.progress = total > 0 ? (doc.scrollTop / total) * 100 : 0;
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

    /* ── Shared clipboard helper ─────────────────────────────── */
    const copyToClipboard = async (text) => {
        if (!text) return;
        try {
            await navigator.clipboard.writeText(text);
        } catch {
            const el = document.createElement('textarea');
            el.value = text;
            el.style.cssText = 'position:fixed;opacity:0';
            document.body.appendChild(el);
            el.select();
            document.execCommand('copy');
            document.body.removeChild(el);
        }
    };
    const fmtBytes = (b) => (b < 1024 ? `${b} B` : `${(b / 1024).toFixed(1)} KB`);

    /* ── Tool: JSON Validator ────────────────────────────────── */
    Alpine.data('jsonValidator', () => ({
        input: '',
        status: null, // { type, message }
        copied: false,
        validate() {
            const raw = this.input.trim();
            if (!raw) { this.status = { type: 'error', message: 'Nothing to validate, paste some JSON first.' }; return; }
            try {
                this.input = JSON.stringify(JSON.parse(raw), null, 2);
                this.status = { type: 'success', message: 'Valid JSON, formatted successfully.' };
            } catch (err) {
                this.status = { type: 'error', message: err.message };
            }
        },
        clear() { this.input = ''; this.status = null; this.copied = false; },
        async copy() {
            if (!this.input) return;
            await copyToClipboard(this.input);
            this.copied = true;
            setTimeout(() => (this.copied = false), 2000);
        },
    }));

    /* ── Tool: JS & CSS Minifier ─────────────────────────────── */
    const minifyCSS = (css) => css
        .replace(/\/\*[\s\S]*?\*\//g, '')
        .replace(/\s+/g, ' ')
        .replace(/\s*([{}:;,>~+])\s*/g, '$1')
        .replace(/;}/g, '}')
        .replace(/\s*!\s*important/gi, '!important')
        .trim();

    Alpine.data('jsCssMinifier', () => ({
        tab: 'js',
        input: '',
        output: '',
        status: null,
        loading: false,
        copied: false,
        get inputBytes() { return new TextEncoder().encode(this.input).length; },
        get outputBytes() { return new TextEncoder().encode(this.output).length; },
        get savingsPct() {
            return this.inputBytes > 0 && this.outputBytes > 0 ? Math.round((1 - this.outputBytes / this.inputBytes) * 100) : null;
        },
        fmt: fmtBytes,
        setTab(t) { this.tab = t; this.input = ''; this.output = ''; this.status = null; this.copied = false; },
        async minify() {
            const raw = this.input.trim();
            if (!raw) { this.status = { type: 'error', message: 'Nothing to minify, paste some code first.' }; return; }
            this.loading = true; this.status = null;
            try {
                let result = '';
                if (this.tab === 'js') {
                    const { minify } = await import('terser');
                    const res = await minify(raw, { compress: true, mangle: true, format: { comments: false } });
                    result = res.code ?? '';
                } else {
                    result = minifyCSS(raw);
                }
                this.output = result;
                const inB = new TextEncoder().encode(raw).length;
                const outB = new TextEncoder().encode(result).length;
                const pct = Math.round((1 - outB / inB) * 100);
                this.status = { type: 'success', message: `Minified successfully. Saved ${pct}% (${fmtBytes(inB)} to ${fmtBytes(outB)})` };
            } catch (err) {
                this.output = ''; this.status = { type: 'error', message: err.message };
            } finally {
                this.loading = false;
            }
        },
        clear() { this.input = ''; this.output = ''; this.status = null; this.copied = false; },
        async copy() {
            if (!this.output) return;
            await copyToClipboard(this.output);
            this.copied = true;
            setTimeout(() => (this.copied = false), 2000);
        },
    }));

    /* ── Tool: Base64 Encoder / Decoder ──────────────────────── */
    const b64encode = (str) => { try { return btoa(unescape(encodeURIComponent(str))); } catch { return null; } };
    const b64decode = (str) => { try { return decodeURIComponent(escape(atob(str.replace(/\s/g, '')))); } catch { return null; } };

    Alpine.data('base64Tool', () => ({
        mode: 'text',
        input: '',
        output: '',
        error: null,
        fileName: null,
        outCopied: false,
        encode() {
            this.error = null;
            if (!this.input.trim()) { this.error = 'Enter some text to encode.'; return; }
            const r = b64encode(this.input);
            if (r === null) { this.error = 'Could not encode. Check for unsupported characters.'; return; }
            this.output = r;
        },
        decode() {
            this.error = null;
            if (!this.input.trim()) { this.error = 'Enter a Base64 string to decode.'; return; }
            const r = b64decode(this.input);
            if (r === null) { this.error = 'Invalid Base64 string. Make sure the input is valid Base64.'; return; }
            this.output = r;
        },
        clear() { this.input = ''; this.output = ''; this.error = null; this.fileName = null; },
        switchMode(next) { this.mode = next; this.clear(); },
        handleFile(e) {
            const file = e.target.files[0];
            if (!file) return;
            this.error = null;
            this.fileName = file.name;
            const reader = new FileReader();
            reader.onload = (ev) => { this.output = ev.target.result.split(',')[1]; };
            reader.readAsDataURL(file);
            e.target.value = '';
        },
        async copyOutput() {
            if (!this.output) return;
            await copyToClipboard(this.output);
            this.outCopied = true;
            setTimeout(() => (this.outCopied = false), 2000);
        },
    }));

    /* ── Tool: Password Generator ────────────────────────────── */
    const PW_CHARS = {
        upper: 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
        lower: 'abcdefghijklmnopqrstuvwxyz',
        numbers: '0123456789',
        symbols: '!@#$%^&*()_+-=[]{}|;:,.<>?',
    };
    Alpine.data('passwordGenerator', () => ({
        length: 16,
        opts: { upper: true, lower: true, numbers: true, symbols: true },
        password: '',
        copied: false,
        presets: [12, 16, 20, 32, 64],
        init() {
            this.regen();
            this.$watch('length', () => this.regen());
            this.$watch('opts', () => this.regen());
        },
        get typeCount() { return Object.values(this.opts).filter(Boolean).length; },
        get strength() {
            const tc = this.typeCount;
            if (tc === 0) return null;
            if (tc === 1 || this.length < 10) return { label: 'Weak', color: 'bg-red-500', text: 'text-red-600', pct: 25 };
            if (tc === 2 || this.length < 14) return { label: 'Fair', color: 'bg-yellow-500', text: 'text-yellow-600', pct: 50 };
            if (tc === 3 || this.length < 20) return { label: 'Strong', color: 'bg-lime-500', text: 'text-lime-600', pct: 75 };
            return { label: 'Very Strong', color: 'bg-green-500', text: 'text-green-600', pct: 100 };
        },
        regen() {
            let pool = '';
            if (this.opts.upper) pool += PW_CHARS.upper;
            if (this.opts.lower) pool += PW_CHARS.lower;
            if (this.opts.numbers) pool += PW_CHARS.numbers;
            if (this.opts.symbols) pool += PW_CHARS.symbols;
            if (!pool) { this.password = ''; this.copied = false; return; }
            const arr = new Uint32Array(this.length);
            crypto.getRandomValues(arr);
            this.password = Array.from(arr, (n) => pool[n % pool.length]).join('');
            this.copied = false;
        },
        toggleOpt(key) {
            const next = { ...this.opts, [key]: !this.opts[key] };
            if (Object.values(next).every((v) => !v)) return;
            this.opts = next;
        },
        async copy() {
            if (!this.password) return;
            await copyToClipboard(this.password);
            this.copied = true;
            setTimeout(() => (this.copied = false), 2000);
        },
    }));

    /* ── Tool: Meta Tag Previewer ────────────────────────────── */
    const mtTruncate = (str, max) => (!str ? '' : (str.length > max ? str.slice(0, max - 3) + '…' : str));
    const mtCharColor = (len, t) => {
        if (len === 0) return 'text-gray-400';
        if (len <= t.ideal) return 'text-green-600';
        if (len <= t.warn) return 'text-yellow-600';
        return 'text-red-600';
    };
    const mtCharBg = (len, t) => {
        const c = mtCharColor(len, t);
        if (c === 'text-green-600') return 'bg-green-50 border-green-300';
        if (c === 'text-yellow-600') return 'bg-yellow-50 border-yellow-300';
        if (c === 'text-red-600') return 'bg-red-50 border-red-300';
        return 'bg-white border-gray-300';
    };
    const mtHost = (url) => {
        try { return new URL(url.startsWith('http') ? url : `https://${url}`).hostname.replace(/^www\./, ''); }
        catch { return 'yourdomain.com'; }
    };
    Alpine.data('metaTagPreviewer', () => ({
        title: '', desc: '', url: '', ogTitle: '', ogDesc: '', ogImage: '',
        preview: 'google',
        TITLE_T: { ideal: 60, warn: 70, max: 100 },
        DESC_T: { ideal: 160, warn: 200, max: 300 },
        trunc: mtTruncate,
        titleColor() { return mtCharColor(this.title.length, this.TITLE_T); },
        descColor() { return mtCharColor(this.desc.length, this.DESC_T); },
        titleBg() { return mtCharBg(this.title.length, this.TITLE_T); },
        descBg() { return mtCharBg(this.desc.length, this.DESC_T); },
        get displayTitle() { return this.title || 'Page Title'; },
        get displayDesc() { return this.desc || 'Meta description goes here. This is the text that appears under your page title in search results.'; },
        get displayUrl() { return this.url || 'https://yourdomain.com/page-slug'; },
        get socialTitle() { return this.ogTitle || this.title || 'Page Title'; },
        get socialDesc() { return this.ogDesc || this.desc || 'Description for social media sharing.'; },
        get host() { return mtHost(this.displayUrl); },
        get breadcrumb() {
            try {
                const u = new URL(this.displayUrl.startsWith('http') ? this.displayUrl : `https://${this.displayUrl}`);
                const parts = [u.hostname.replace(/^www\./, '')];
                const segments = u.pathname.split('/').filter(Boolean);
                if (segments.length) parts.push(...segments.slice(0, 2));
                const joined = parts.join(' › ');
                return joined.length > 70 ? joined.slice(0, 67) + '…' : joined;
            } catch { return this.url || 'yourdomain.com › page'; }
        },
    }));

    /* ── Tool: shared DropZone ───────────────────────────────── */
    const DZ_MAX_HARD = 20 * 1024 * 1024;
    const DZ_MAX_WARN = 10 * 1024 * 1024;
    Alpine.data('toolDropZone', () => ({
        dragging: false,
        error: null,
        warning: null,
        process(file) {
            if (!file) return;
            this.error = null; this.warning = null;
            if (!file.type.startsWith('image/')) { this.error = 'Please upload an image file (JPG, PNG, WebP, etc.)'; return; }
            if (file.size > DZ_MAX_HARD) { this.error = `File is too large (${(file.size / 1024 / 1024).toFixed(1)} MB). Maximum is 20 MB.`; return; }
            if (file.size > DZ_MAX_WARN) { this.warning = `Large file (${(file.size / 1024 / 1024).toFixed(1)} MB). Processing may be slow on mobile.`; }
            const reader = new FileReader();
            reader.onload = (e) => this.$dispatch('file-loaded', { src: e.target.result, name: file.name, size: file.size, type: file.type });
            reader.readAsDataURL(file);
        },
        onDrop(e) { this.dragging = false; this.process(e.dataTransfer.files[0]); },
    }));

    /* ── Tool: Color Palette Extractor ───────────────────────── */
    const cpRgbToHex = (r, g, b) => '#' + [r, g, b].map((v) => v.toString(16).padStart(2, '0')).join('');
    const cpHexToRgb = (hex) => { const h = hex.replace('#', ''); return [parseInt(h.slice(0, 2), 16), parseInt(h.slice(2, 4), 16), parseInt(h.slice(4, 6), 16)]; };
    const cpRgbToHsl = (r, g, b) => {
        r /= 255; g /= 255; b /= 255;
        const max = Math.max(r, g, b), min = Math.min(r, g, b), l = (max + min) / 2;
        if (max === min) return [0, 0, Math.round(l * 100)];
        const d = max - min, s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
        let h;
        if (max === r) h = ((g - b) / d + (g < b ? 6 : 0)) / 6;
        else if (max === g) h = ((b - r) / d + 2) / 6;
        else h = ((r - g) / d + 4) / 6;
        return [Math.round(h * 360), Math.round(s * 100), Math.round(l * 100)];
    };
    const cpDist = (r1, g1, b1, r2, g2, b2) => Math.sqrt((r1 - r2) ** 2 + (g1 - g2) ** 2 + (b1 - b2) ** 2);
    const cpLum = (r, g, b) => (0.299 * r + 0.587 * g + 0.114 * b) / 255;
    const CP_SCALE = [
        { label: '50', white: 0.92 }, { label: '100', white: 0.80 }, { label: '200', white: 0.64 },
        { label: '300', white: 0.44 }, { label: '400', white: 0.22 }, { label: '500', white: 0 },
        { label: '600', black: 0.12 }, { label: '700', black: 0.26 }, { label: '800', black: 0.44 }, { label: '900', black: 0.60 },
    ];
    const cpScale = (hex) => {
        const [r, g, b] = cpHexToRgb(hex);
        return CP_SCALE.map(({ label, white = 0, black = 0 }) => {
            const nr = Math.round(white ? r + (255 - r) * white : r * (1 - black));
            const ng = Math.round(white ? g + (255 - g) * white : g * (1 - black));
            const nb = Math.round(white ? b + (255 - b) * white : b * (1 - black));
            const hx = cpRgbToHex(nr, ng, nb);
            return { label, hex: hx, textColor: cpLum(nr, ng, nb) > 0.45 ? 'rgba(0,0,0,0.65)' : 'rgba(255,255,255,0.85)' };
        });
    };
    const cpExtract = (src, count) => new Promise((resolve) => {
        const img = new Image();
        img.onload = () => {
            const SIZE = 150, scale = Math.min(SIZE / img.width, SIZE / img.height, 1);
            const canvas = document.createElement('canvas');
            canvas.width = Math.round(img.width * scale);
            canvas.height = Math.round(img.height * scale);
            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
            const { data } = ctx.getImageData(0, 0, canvas.width, canvas.height);
            const buckets = new Map(), step = 32;
            for (let i = 0; i < data.length; i += 4) {
                if (data[i + 3] < 128) continue;
                const r = Math.round(data[i] / step) * step, g = Math.round(data[i + 1] / step) * step, b = Math.round(data[i + 2] / step) * step;
                const key = (r << 16) | (g << 8) | b;
                buckets.set(key, (buckets.get(key) ?? 0) + 1);
            }
            const sorted = [...buckets.entries()].sort((a, b) => b[1] - a[1]).map(([key]) => [(key >> 16) & 0xff, (key >> 8) & 0xff, key & 0xff]);
            const palette = [];
            for (const [r, g, b] of sorted) {
                if (!palette.some(([pr, pg, pb]) => cpDist(r, g, b, pr, pg, pb) < 64)) palette.push([r, g, b]);
                if (palette.length >= count) break;
            }
            resolve(palette.map(([r, g, b]) => {
                const [h, s, l] = cpRgbToHsl(r, g, b);
                return { hex: cpRgbToHex(r, g, b), rgb: `rgb(${r}, ${g}, ${b})`, hsl: `hsl(${h}, ${s}%, ${l}%)` };
            }));
        };
        img.src = src;
    });
    Alpine.data('colorPalette', () => ({
        tab: 'image',
        imageSrc: null,
        palette: [],
        count: 6,
        loading: false,
        counts: [4, 6, 8, 12],
        baseColors: ['#3b82f6'],
        scaleHeaders: CP_SCALE.map((s) => s.label),
        async handleFile(detail) {
            this.imageSrc = detail.src;
            this.loading = true;
            const colors = await cpExtract(detail.src, this.count);
            this.palette = colors;
            this.loading = false;
        },
        async reextract(n) {
            this.count = n;
            if (!this.imageSrc) return;
            this.loading = true;
            this.palette = await cpExtract(this.imageSrc, n);
            this.loading = false;
        },
        clearImage() { this.imageSrc = null; this.palette = []; },
        copyAllHex() { copyToClipboard(this.palette.map((c) => c.hex).join('\n')); },
        scaleFor(hex) { return cpScale(hex); },
        addColor() {
            if (this.baseColors.length >= 3) return;
            const presets = ['#3b82f6', '#ef4444', '#10b981'];
            this.baseColors.push(presets.find((p) => !this.baseColors.includes(p)) ?? '#8b5cf6');
        },
        removeColor(i) { this.baseColors.splice(i, 1); },
        copyRowHex(hex) { copyToClipboard(cpScale(hex).map((s) => s.hex).join('\n')); },
        copySwatch(hex) { copyToClipboard(hex); },
    }));

    /* ── Tool: shared Save Modal mixin ───────────────────────── */
    const SM_FORMATS = [
        { id: 'webp', label: 'WebP', mime: 'image/webp', ext: 'webp', description: 'Modern format, smallest file size', recommended: true, hasQuality: true },
        { id: 'jpg', label: 'JPG', mime: 'image/jpeg', ext: 'jpg', description: 'Small files, great for photos', recommended: false, hasQuality: true },
        { id: 'png', label: 'PNG', mime: 'image/png', ext: 'png', description: 'Lossless, ideal for graphics and transparency', recommended: false, hasQuality: false },
    ];
    const smFmtBytes = (b) => (!b ? '-' : (b < 1024 ? `${b} B` : (b < 1024 * 1024 ? `${(b / 1024).toFixed(1)} KB` : `${(b / 1024 / 1024).toFixed(1)} MB`)));
    const canvasToBlob = (canvas, mime, quality) => new Promise((resolve) => canvas.toBlob(resolve, mime, quality));

    // Mixin: spread into a tool component that defines getCanvas() and saveName().
    const saveModalState = () => ({
        modalOpen: false,
        sm_formats: SM_FORMATS,
        sm_format: 'webp',
        sm_quality: 90,
        sm_outputSize: null,
        sm_estimating: false,
        sm_downloading: false,
        _sm_blob: null,
        _sm_debounce: null,
        smFmtBytes,
        // NOTE: a method, not a getter — this mixin is consumed via `...saveModalState()`,
        // and the spread operator evaluates getters once and freezes their value. A method
        // stays live so the format label/mime/ext track sm_format after the user switches.
        sm_selected() { return SM_FORMATS.find((f) => f.id === this.sm_format); },
        openSave() { this.modalOpen = true; this._sm_blob = null; this.$nextTick(() => this.smEstimate()); },
        closeSave() { this.modalOpen = false; },
        smSetFormat(id) { this.sm_format = id; this._sm_blob = null; this.smEstimateDebounced(); },
        smSetQuality(q) { this.sm_quality = q; this._sm_blob = null; this.smEstimateDebounced(); },
        smEstimateDebounced() { clearTimeout(this._sm_debounce); this._sm_debounce = setTimeout(() => this.smEstimate(), 300); },
        async smEstimate() {
            const canvas = this.getCanvas?.();
            if (!canvas) return;
            this.sm_estimating = true;
            try {
                const fmt = this.sm_selected();
                const blob = await canvasToBlob(canvas, fmt.mime, fmt.hasQuality ? this.sm_quality / 100 : undefined);
                this._sm_blob = blob;
                this.sm_outputSize = blob?.size ?? null;
            } catch { this.sm_outputSize = null; } finally { this.sm_estimating = false; }
        },
        async smDownload() {
            this.sm_downloading = true;
            try {
                const fmt = this.sm_selected();
                const blob = this._sm_blob ?? await canvasToBlob(this.getCanvas(), fmt.mime, fmt.hasQuality ? this.sm_quality / 100 : undefined);
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `${(this.saveName?.() ?? 'image').replace(/\.[^.]+$/, '')}.${fmt.ext}`;
                a.click();
                URL.revokeObjectURL(url);
            } catch { /* canvas security errors ignored */ } finally { this.sm_downloading = false; }
        },
    });

    /* ── Tool: Image Converter ───────────────────────────────── */
    Alpine.data('imageConverter', () => ({
        ...saveModalState(),
        image: null,
        width: '',
        height: '',
        aspectLocked: true,
        natW: 0,
        natH: 0,
        handleFile(detail) {
            this.image = detail;
            this.modalOpen = false;
            const img = new Image();
            img.onload = () => {
                this.natW = img.naturalWidth; this.natH = img.naturalHeight;
                this.width = String(img.naturalWidth); this.height = String(img.naturalHeight);
            };
            img.src = detail.src;
        },
        onWidth(val) {
            this.width = val;
            const n = parseInt(val, 10);
            if (this.aspectLocked && this.natW && !isNaN(n) && n > 0) this.height = String(Math.round(n * (this.natH / this.natW)));
        },
        onHeight(val) {
            this.height = val;
            const n = parseInt(val, 10);
            if (this.aspectLocked && this.natH && !isNaN(n) && n > 0) this.width = String(Math.round(n * (this.natW / this.natH)));
        },
        getCanvas() {
            const img = this.$refs.preview;
            if (!img) return null;
            const w = Math.max(1, parseInt(this.width, 10) || img.naturalWidth);
            const h = Math.max(1, parseInt(this.height, 10) || img.naturalHeight);
            const canvas = document.createElement('canvas');
            canvas.width = w; canvas.height = h;
            canvas.getContext('2d').drawImage(img, 0, 0, w, h);
            return canvas;
        },
        saveName() { return this.image?.name ?? 'image'; },
        reset() { this.image = null; this.width = ''; this.height = ''; this.modalOpen = false; },
        fmtBytes: smFmtBytes,
    }));

    /* ── Tool: Background Remover ────────────────────────────── */
    const brToHex = ({ r, g, b }) => '#' + [r, g, b].map((v) => v.toString(16).padStart(2, '0')).join('');
    const brApplyRemoval = (ctx, w, h, target, tolerance) => {
        const imageData = ctx.getImageData(0, 0, w, h);
        const { data } = imageData;
        const feather = Math.max(tolerance * 0.6, 8);
        const fullTol = tolerance + feather;
        const tr = target.r, tg = target.g, tb = target.b;
        const dist = (i) => { const dr = data[i] - tr, dg = data[i + 1] - tg, db = data[i + 2] - tb; return Math.sqrt(dr * dr + dg * dg + db * db); };
        const size = w * h;
        const visited = new Uint8Array(size);
        const queue = new Uint32Array(size);
        let qHead = 0, qTail = 0;
        const enqueue = (px) => { if (visited[px]) return; if (dist(px * 4) <= fullTol) { visited[px] = 1; queue[qTail++] = px; } };
        for (let x = 0; x < w; x++) { enqueue(x); enqueue((h - 1) * w + x); }
        for (let y = 1; y < h - 1; y++) { enqueue(y * w); enqueue(y * w + w - 1); }
        while (qHead < qTail) {
            const px = queue[qHead++], i = px * 4, d = dist(i);
            if (d <= tolerance) data[i + 3] = 0;
            else data[i + 3] = Math.round(((d - tolerance) / feather) * data[i + 3]);
            const x = px % w, y = (px - x) / w;
            if (x > 0) enqueue(px - 1);
            if (x < w - 1) enqueue(px + 1);
            if (y > 0) enqueue(px - w);
            if (y < h - 1) enqueue(px + w);
        }
        ctx.putImageData(imageData, 0, 0);
    };
    Alpine.data('backgroundRemover', () => ({
        ...saveModalState(),
        image: null,
        target: { r: 255, g: 255, b: 255 },
        tolerance: 32,
        resultUrl: null,
        processing: false,
        sampling: false,
        _img: null,
        presets: [['Clean white', 20], ['White + light shadow', 40], ['White + heavy shadow', 65]],
        get targetHex() { return brToHex(this.target); },
        handleFile(detail) {
            this.image = detail; this.resultUrl = null; this.sampling = false; this.target = { r: 255, g: 255, b: 255 };
            const img = new Image();
            img.onload = () => { this._img = img; this.runProcess(img, { r: 255, g: 255, b: 255 }, 32); };
            img.src = detail.src;
        },
        runProcess(img, tgt, tol) {
            this.processing = true;
            setTimeout(() => {
                try {
                    const canvas = this.$refs.resultCanvas;
                    canvas.width = img.naturalWidth; canvas.height = img.naturalHeight;
                    const ctx = canvas.getContext('2d');
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    ctx.drawImage(img, 0, 0);
                    brApplyRemoval(ctx, canvas.width, canvas.height, tgt, tol);
                    this.resultUrl = canvas.toDataURL('image/png');
                } finally { this.processing = false; }
            }, 20);
        },
        apply() { if (this._img) this.runProcess(this._img, this.target, this.tolerance); },
        resetWhite() { this.target = { r: 255, g: 255, b: 255 }; },
        sampleAt(e) {
            if (!this.sampling || !this._img) return;
            const el = e.currentTarget, rect = el.getBoundingClientRect();
            const px = Math.round((e.clientX - rect.left) * (el.naturalWidth / rect.width));
            const py = Math.round((e.clientY - rect.top) * (el.naturalHeight / rect.height));
            const tmp = document.createElement('canvas');
            tmp.width = this._img.naturalWidth; tmp.height = this._img.naturalHeight;
            const ctx = tmp.getContext('2d');
            ctx.drawImage(this._img, 0, 0);
            const [r, g, b] = ctx.getImageData(px, py, 1, 1).data;
            this.target = { r, g, b }; this.sampling = false;
        },
        getCanvas() { return this.$refs.resultCanvas; },
        saveName() { return this.image?.name ?? 'image'; },
        newImage() { this.image = null; this.resultUrl = null; this.sampling = false; this.target = { r: 255, g: 255, b: 255 }; this.tolerance = 32; this._img = null; },
    }));

    /* ── Tool: Image Cropper (lazy-loads cropperjs) ──────────── */
    const CROP_ASPECTS = [
        { label: 'Free', value: NaN },
        { label: '1:1', value: 1 },
        { label: '16:9', value: 16 / 9 },
        { label: '4:3', value: 4 / 3 },
    ];
    Alpine.data('imageCropper', () => ({
        ...saveModalState(),
        image: null,
        aspectKey: 'Free',
        circleMode: false,
        aspects: CROP_ASPECTS,
        _cropper: null,
        async handleFile(detail) {
            this.image = detail;
            this.modalOpen = false;
            this.circleMode = false;
            this.aspectKey = 'Free';
            const { default: Cropper } = await import('cropperjs');
            await import('cropperjs/dist/cropper.css');
            this.$nextTick(() => {
                if (this._cropper) { this._cropper.destroy(); this._cropper = null; }
                const img = this.$refs.cropImg;
                img.src = detail.src;
                this._cropper = new Cropper(img, {
                    aspectRatio: NaN,
                    guides: true,
                    viewMode: 1,
                    dragMode: 'move',
                    autoCropArea: 0.8,
                    responsive: true,
                    restore: false,
                    checkCrossOrigin: false,
                    background: true,
                });
            });
        },
        setAspect(preset) {
            this.aspectKey = preset.label;
            if (preset.label === '1:1') this.circleMode = false;
            this._cropper?.setAspectRatio(isNaN(preset.value) ? NaN : preset.value);
        },
        toggleCircle() {
            this.circleMode = !this.circleMode;
            if (this.circleMode) { this.aspectKey = '1:1'; this._cropper?.setAspectRatio(1); }
        },
        rotate(deg) { this._cropper?.rotate(deg); },
        flip(axis) {
            if (!this._cropper) return;
            const data = this._cropper.getImageData();
            if (axis === 'h') this._cropper.scaleX(data.scaleX === -1 ? 1 : -1);
            else this._cropper.scaleY(data.scaleY === -1 ? 1 : -1);
        },
        resetCrop() { this._cropper?.reset(); this.circleMode = false; this.aspectKey = 'Free'; },
        newImage() {
            if (this._cropper) { this._cropper.destroy(); this._cropper = null; }
            this.image = null; this.modalOpen = false; this.circleMode = false; this.aspectKey = 'Free';
        },
        getCanvas() {
            if (!this._cropper) return null;
            const raw = this._cropper.getCroppedCanvas({ imageSmoothingQuality: 'high' });
            if (!raw) return null;
            if (!this.circleMode) return raw;
            const size = Math.min(raw.width, raw.height);
            const canvas = document.createElement('canvas');
            canvas.width = size; canvas.height = size;
            const ctx = canvas.getContext('2d');
            ctx.beginPath();
            ctx.arc(size / 2, size / 2, size / 2, 0, Math.PI * 2);
            ctx.closePath();
            ctx.clip();
            ctx.drawImage(raw, 0, 0, size, size);
            return canvas;
        },
        saveName() { return this.image?.name ?? 'image'; },
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

/* ── AdSense: push slots after each Livewire navigation/render ─ */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('ins.adsbygoogle:not([data-ad-status])').forEach(() => {
        try {
            (window.adsbygoogle = window.adsbygoogle || []).push({});
        } catch (e) {
            /* slot already initialised */
        }
    });
});

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
