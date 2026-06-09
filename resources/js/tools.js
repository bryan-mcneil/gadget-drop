/*
 | Free-tool Alpine components.
 |
 | Loaded only on /tools/* pages (see layouts/public.blade.php), so the heavy
 | image-processing / minifier code never ships on the homepage or articles.
 | Like app.js, this registers on `alpine:init` (Livewire owns Alpine, never
 | call Alpine.start()). Does NOT import app.css: app.js already loads it on
 | every page, including the tool pages.
 */

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;

    /* ── Shared toast store ──────────────────────────────────────
     | Single feedback primitive for the whole tools section. The container
     | is rendered once in components/tools/shell.blade.php. Any tool calls
     | `$store.toast.show('Copied …')` (or the toast() helper below).         */
    Alpine.store('toast', {
        items: [],
        _id: 0,
        show(message) {
            const id = ++this._id;
            this.items.push({ id, message });
            setTimeout(() => { this.items = this.items.filter((t) => t.id !== id); }, 2000);
        },
    });
    const toast = (message) => Alpine.store('toast').show(message);

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
    // Copy + confirm with a toast in one call (use for every copy action).
    const copyWithToast = async (text, message = 'Copied to clipboard') => {
        if (!text) return;
        await copyToClipboard(text);
        toast(message);
    };
    const fmtBytes = (b) => (b < 1024 ? `${b} B` : `${(b / 1024).toFixed(1)} KB`);

    /* ── Tool: JSON Validator ────────────────────────────────── */
    Alpine.data('jsonValidator', () => ({
        input: '',
        status: null, // { type, message }
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
        clear() { this.input = ''; this.status = null; },
        async copy() { await copyWithToast(this.input); },
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
        get inputBytes() { return new TextEncoder().encode(this.input).length; },
        get outputBytes() { return new TextEncoder().encode(this.output).length; },
        get savingsPct() {
            return this.inputBytes > 0 && this.outputBytes > 0 ? Math.round((1 - this.outputBytes / this.inputBytes) * 100) : null;
        },
        fmt: fmtBytes,
        setTab(t) { this.tab = t; this.input = ''; this.output = ''; this.status = null; },
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
        clear() { this.input = ''; this.output = ''; this.status = null; },
        async copy() { await copyWithToast(this.output); },
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
        async copyOutput() { await copyWithToast(this.output); },
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
            if (!pool) { this.password = ''; return; }
            const arr = new Uint32Array(this.length);
            crypto.getRandomValues(arr);
            this.password = Array.from(arr, (n) => pool[n % pool.length]).join('');
        },
        toggleOpt(key) {
            const next = { ...this.opts, [key]: !this.opts[key] };
            if (Object.values(next).every((v) => !v)) return;
            this.opts = next;
        },
        async copy() { await copyWithToast(this.password); },
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
    const hslToHex = (h, s, l) => {
        s /= 100; l /= 100;
        const c = (1 - Math.abs(2 * l - 1)) * s;
        const x = c * (1 - Math.abs((h / 60) % 2 - 1));
        const m = l - c / 2;
        let r = 0, g = 0, b = 0;
        if      (h < 60)  { r = c; g = x; b = 0; }
        else if (h < 120) { r = x; g = c; b = 0; }
        else if (h < 180) { r = 0; g = c; b = x; }
        else if (h < 240) { r = 0; g = x; b = c; }
        else if (h < 300) { r = x; g = 0; b = c; }
        else              { r = c; g = 0; b = x; }
        return cpRgbToHex(Math.round((r + m) * 255), Math.round((g + m) * 255), Math.round((b + m) * 255));
    };
    const hexToSwatch = (hex) => {
        const [r, g, b] = cpHexToRgb(hex);
        const [h, s, l] = cpRgbToHsl(r, g, b);
        return { hex, rgb: `rgb(${r}, ${g}, ${b})`, hsl: `hsl(${h}, ${s}%, ${l}%)` };
    };
    const cpHarmony = (seedHex, scheme) => {
        const [r0, g0, b0] = cpHexToRgb(seedHex);
        const [h, s, l] = cpRgbToHsl(r0, g0, b0);
        const norm = (deg) => ((deg % 360) + 360) % 360;
        const clamp = (v, mn, mx) => Math.min(mx, Math.max(mn, v));
        const sw = (dh, ds = 0, dl = 0, role = null) => ({
            ...hexToSwatch(hslToHex(norm(h + dh), clamp(s + ds, 5, 100), clamp(l + dl, 5, 95))),
            role,
        });
        switch (scheme) {
            case 'analogous':      return [-60, -30, 0, 30, 60].map((d) => sw(d));
            case 'complementary':  return [sw(0), sw(0, 0, 18), sw(0, 0, -18), sw(180), sw(180, 0, 18), sw(180, 0, -18)];
            case 'nature':         return [0, 137.5, 275, 52.5, 190].map((o) => sw(o));
            case '60-30-10':       return [sw(0, 0, 0, '60% Dominant'), sw(120, 0, 0, '30% Secondary'), sw(240, 0, 0, '10% Accent')];
        }
        return [];
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
        exportMenuOpen: false,
        rowMenuOpen: -1,
        harmonyScheme: 'analogous',
        harmonySeed: '#3b82f6',
        harmonyPalette: [],
        harmonySchemes: [
            { id: 'analogous',     label: 'Analogous' },
            { id: 'complementary', label: 'Complementary' },
            { id: 'nature',        label: 'Nature' },
            { id: '60-30-10',      label: '60-30-10' },
        ],
        harmonyExportOpen: false,
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
        copySwatch(val) { copyWithToast(val); },
        scaleFor(hex) { return cpScale(hex); },
        addColor() {
            if (this.baseColors.length >= 3) return;
            const presets = ['#3b82f6', '#ef4444', '#10b981'];
            this.baseColors.push(presets.find((p) => !this.baseColors.includes(p)) ?? '#8b5cf6');
        },
        removeColor(i) { this.baseColors.splice(i, 1); },
        copyPaletteAs(fmt) {
            const p = this.palette;
            let text, label;
            if (fmt === 'hex')           { text = p.map((c) => c.hex).join('\n'); label = 'HEX list'; }
            else if (fmt === 'css')      { text = p.map((c, i) => `--palette-${i + 1}: ${c.hex};`).join('\n'); label = 'CSS variables'; }
            else if (fmt === 'tailwind') { text = `colors: {\n${p.map((c, i) => `  'palette-${i + 1}': '${c.hex}'`).join(',\n')},\n}`; label = 'Tailwind config'; }
            else                         { text = JSON.stringify(p.map((c) => c.hex), null, 2); label = 'JSON'; }
            copyWithToast(text, `Copied as ${label}`);
            this.exportMenuOpen = false;
        },
        copyRowAs(hex, fmt, i) {
            const scale = cpScale(hex);
            let text, label;
            if (fmt === 'hex')           { text = scale.map((s) => s.hex).join('\n'); label = 'HEX scale'; }
            else if (fmt === 'css')      { text = scale.map((s) => `--color-${s.label}: ${s.hex};`).join('\n'); label = 'CSS variables'; }
            else if (fmt === 'tailwind') { text = `'brand': {\n${scale.map((s) => `  ${s.label}: '${s.hex}'`).join(',\n')},\n}`; label = 'Tailwind color'; }
            else                         { text = JSON.stringify(Object.fromEntries(scale.map((s) => [s.label, s.hex])), null, 2); label = 'JSON'; }
            copyWithToast(text, `Copied as ${label}`);
            this.rowMenuOpen = -1;
        },
        init() { this.generateHarmony(); },
        generateHarmony() { this.harmonyPalette = cpHarmony(this.harmonySeed, this.harmonyScheme); },
        copyHarmonyAs(fmt) {
            const p = this.harmonyPalette;
            let text, label;
            if (fmt === 'hex') {
                const is6030 = this.harmonyScheme === '60-30-10';
                text = p.map((c) => (is6030 && c.role ? `# ${c.role}\n${c.hex}` : c.hex)).join('\n');
                label = 'HEX list';
            } else if (fmt === 'css') {
                text = p.map((c, i) => `--color-${i + 1}: ${c.hex};`).join('\n');
                label = 'CSS variables';
            } else if (fmt === 'tailwind') {
                text = `colors: {\n${p.map((c, i) => `  'color-${i + 1}': '${c.hex}'`).join(',\n')},\n}`;
                label = 'Tailwind config';
            } else {
                text = JSON.stringify(p.map((c) => c.hex), null, 2);
                label = 'JSON';
            }
            copyWithToast(text, `Copied as ${label}`);
            this.harmonyExportOpen = false;
        },
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
        // NOTE: a method, not a getter, because this mixin is consumed via `...saveModalState()`,
        // and the spread operator evaluates getters once and freezes their value. A method
        // stays live so the format label/mime/ext track sm_format after the user switches.
        sm_selected() { return SM_FORMATS.find((f) => f.id === this.sm_format); },
        // The estimate is primed ahead of time (smEstimateDebounced fires whenever the
        // canvas content / format / quality changes), so the modal shows a size instantly.
        // Only compute on open if nothing has been primed yet, which avoids the "Calculating" flash.
        openSave() { this.modalOpen = true; if (this.sm_outputSize === null && !this.sm_estimating) this.$nextTick(() => this.smEstimate()); },
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
                toast(`Downloaded as ${fmt.label}`);
                this.closeSave();
            } catch { /* canvas security errors ignored */ } finally { this.sm_downloading = false; }
        },
    });

    /* ── Image-processing helpers (shared by the Image Editor) ─── */
    const CROP_ASPECTS = [
        { label: 'Free', value: NaN },
        { label: '1:1', value: 1 },
        { label: '4:3', value: 4 / 3 },
        { label: '3:2', value: 3 / 2 },
        { label: '16:9', value: 16 / 9 },
    ];

    // Apply color adjustments in place on a getImageData() buffer (Uint8ClampedArray,
    // so out-of-range values clamp automatically on assignment). `p` values are the
    // slider amounts: brightness/contrast/etc. in -100..100, hue in -180..180.
    const adjustPixels = (data, p) => {
        const expGain = Math.pow(2, p.exposure / 100);                 // exposure ~ stops
        const bright = p.brightness * 1.28;                            // additive, ±~128
        const c = (p.contrast / 100) * 255;                            // -255..255
        const cf = (259 * (c + 255)) / (255 * (259 - c));              // contrast factor
        const sat = 1 + p.saturation / 100;
        const vib = p.vibrance / 100;
        const temp = p.temperature * 0.6;                              // warm(+r)/cool(+b)
        const tint = p.tint * 0.6;                                     // green/magenta on g
        const needHue = p.hue !== 0;
        let h00 = 1, h01 = 0, h02 = 0, h10 = 0, h11 = 1, h12 = 0, h20 = 0, h21 = 0, h22 = 1;
        if (needHue) {
            const a = p.hue * Math.PI / 180, cos = Math.cos(a), sin = Math.sin(a);
            const lr = 0.213, lg = 0.715, lb = 0.072;
            h00 = lr + cos * (1 - lr) + sin * (-lr);
            h01 = lg + cos * (-lg) + sin * (-lg);
            h02 = lb + cos * (-lb) + sin * (1 - lb);
            h10 = lr + cos * (-lr) + sin * (0.143);
            h11 = lg + cos * (1 - lg) + sin * (0.140);
            h12 = lb + cos * (-lb) + sin * (-0.283);
            h20 = lr + cos * (-lr) + sin * (-(1 - lr));
            h21 = lg + cos * (-lg) + sin * (lg);
            h22 = lb + cos * (1 - lb) + sin * (lb);
        }
        for (let i = 0; i < data.length; i += 4) {
            let r = data[i], g = data[i + 1], b = data[i + 2];
            r *= expGain; g *= expGain; b *= expGain;
            r += bright; g += bright; b += bright;
            r += temp; b -= temp; g += tint;
            r = cf * (r - 128) + 128; g = cf * (g - 128) + 128; b = cf * (b - 128) + 128;
            const gray = 0.299 * r + 0.587 * g + 0.114 * b;
            let s = sat;
            if (vib !== 0) {
                const mx = Math.max(r, g, b), mn = Math.min(r, g, b);
                s += vib * (1 - (mx - mn) / 255);                      // boost muted pixels more
            }
            r = gray + (r - gray) * s; g = gray + (g - gray) * s; b = gray + (b - gray) * s;
            if (needHue) {
                const nr = r * h00 + g * h01 + b * h02;
                const ng = r * h10 + g * h11 + b * h12;
                const nb = r * h20 + g * h21 + b * h22;
                r = nr; g = ng; b = nb;
            }
            data[i] = r; data[i + 1] = g; data[i + 2] = b;
        }
    };

    const decodeImage = (src) => new Promise((resolve) => { const i = new Image(); i.onload = () => resolve(i); i.src = src; });

    /* ── Tool: Image Editor ──────────────────────────────────────
     | One working image. Crop / Resize / Rotate-Flip / Adjust each REPLACE the
     | working image, recorded on an undo/redo stack. Intermediate states are kept
     | as lossless PNG data URLs so repeated edits don't accumulate JPEG/WebP
     | artifacts. cropperjs is lazy-loaded only when the Crop tool is active.
     | Export runs through the shared save-modal mixin (getCanvas + saveName).    */
    Alpine.data('imageEditor', (initialTool = 'crop') => ({
        ...saveModalState(),
        fileMeta: null,         // { name, size, type } of the originally dropped file
        workingSrc: null,       // current working image (data URL)
        _workingImg: null,      // decoded <img> for the working image (for canvas ops)
        natW: 0, natH: 0,
        activeTool: initialTool,
        _initialTool: initialTool, // which tool a page (Editor / Converter / Remove BG) opens on
        history: [],            // undo stack (data URLs)
        future: [],             // redo stack (data URLs)
        hasAlpha: false,        // working image may have transparency (show checkerboard)
        _lastTool: null,        // tool that produced the current working image
        _toolBase: null,        // image the active tool edits from (see _resolveBase)
        _baseW: 0, _baseH: 0,   // dimensions of _toolBase
        _lastAdj: null,         // last applied Adjust values (to keep re-editing non-stacking)

        tools: [
            { id: 'crop', label: 'Crop' },
            { id: 'resize', label: 'Resize' },
            { id: 'transform', label: 'Rotate & Flip' },
            { id: 'adjust', label: 'Adjust' },
        ],

        // crop
        aspects: CROP_ASPECTS,
        aspectKey: 'Free',
        _cropper: null,
        _Cropper: null,

        // resize
        rsWidth: '', rsHeight: '', rsLock: true,

        // adjust (color)
        adj: { exposure: 0, brightness: 0, contrast: 0, saturation: 0, vibrance: 0, temperature: 0, tint: 0, hue: 0 },
        adjControls: [
            { key: 'exposure', label: 'Exposure', min: -100, max: 100 },
            { key: 'brightness', label: 'Brightness', min: -100, max: 100 },
            { key: 'contrast', label: 'Contrast', min: -100, max: 100 },
            { key: 'saturation', label: 'Saturation', min: -100, max: 100 },
            { key: 'vibrance', label: 'Vibrance', min: -100, max: 100 },
            { key: 'temperature', label: 'Temperature', min: -100, max: 100 },
            { key: 'tint', label: 'Tint', min: -100, max: 100 },
            { key: 'hue', label: 'Hue', min: -180, max: 180 },
        ],
        _adjDebounce: null,

        loadImage(detail) {
            this.fileMeta = { name: detail.name, size: detail.size, type: detail.type };
            this.history = []; this.future = []; this.hasAlpha = false;
            this.activeTool = this._initialTool;
            this._setWorking(detail.src, () => this.enterTool(this._initialTool));
        },
        toolLabel() { return (this.tools.find((t) => t.id === this.activeTool) || {}).label || ''; },

        // Decode `src` into the working image, refresh dims, prime the export estimate.
        _setWorking(src, after) {
            const img = new Image();
            img.onload = () => {
                this._workingImg = img;
                this.workingSrc = src;
                this.natW = img.naturalWidth; this.natH = img.naturalHeight;
                this.rsWidth = String(this.natW); this.rsHeight = String(this.natH);
                this.sm_outputSize = null;
                this.$nextTick(() => this.smEstimateDebounced());
                if (after) this.$nextTick(() => after());
            };
            img.src = src;
        },
        // Replace the working image after an edit. `opts.tool` records which tool produced
        // it; `opts.replace` re-applies the SAME tool by swapping the result in place
        // (the edit was computed from the pre-edit base) instead of stacking a new pass.
        commit(src, opts = {}, after) {
            const { tool = null, replace = false } = opts;
            if (replace && tool && this._lastTool === tool && this.history.length) {
                // history top is already the pre-edit base; discard the previous same-tool
                // result (the current workingSrc) and swap in the new one.
                this.future = [];
                this._lastTool = tool;
                this._setWorking(src, after);
                toast('Edit updated');
                return;
            }
            if (this.workingSrc) {
                this.history.push(this.workingSrc);
                if (this.history.length > 24) this.history.shift();
            }
            this.future = [];
            this._lastTool = tool;
            this._setWorking(src, after);
            toast('Edit applied');
        },

        get canUndo() { return this.history.length > 0; },
        get canRedo() { return this.future.length > 0; },
        undo() {
            if (!this.history.length) return;
            this._destroyCropper();
            this.future.push(this.workingSrc);
            this._lastTool = null; this._lastAdj = null;
            this._setWorking(this.history.pop(), () => this.enterTool(this.activeTool));
        },
        redo() {
            if (!this.future.length) return;
            this._destroyCropper();
            this.history.push(this.workingSrc);
            this._lastTool = null; this._lastAdj = null;
            this._setWorking(this.future.pop(), () => this.enterTool(this.activeTool));
        },
        resetAll() {
            if (!this.history.length) return;
            this._destroyCropper();
            const first = this.history[0];
            this.history = []; this.future = []; this.hasAlpha = false;
            this._lastTool = null; this._lastAdj = null;
            this._setWorking(first, () => this.enterTool(this.activeTool));
            toast('Reset to original');
        },
        newImage() {
            this._destroyCropper();
            this.fileMeta = null; this.workingSrc = null; this._workingImg = null;
            this.history = []; this.future = []; this.hasAlpha = false; this.modalOpen = false;
            this._lastTool = null; this._lastAdj = null;
            this.aspectKey = 'Free'; this.activeTool = 'crop';
        },

        selectTool(id) {
            if (this.activeTool === id) return;
            this._destroyCropper();
            this.resetAdj();           // discard any un-applied color edits when switching tools
            this.activeTool = id;
            this.$nextTick(() => this.enterTool(id));
        },
        // Resize and Adjust replace their own previous result on re-apply (no stacking).
        // Crop chains (crop tighter) and Rotate/Flip accumulate, which is their natural behavior.
        _replaceable(id) { return id === 'resize' || id === 'adjust'; },
        // The image the active tool edits from. When re-opening the SAME tool that produced
        // the current image, edit from the pre-edit base (history top) so a re-apply replaces
        // rather than stacks. Otherwise edit from the current working image (tools chain).
        _resolveBase(id) {
            if (this._replaceable(id) && this._lastTool === id && this.history.length) {
                return decodeImage(this.history[this.history.length - 1]);
            }
            return Promise.resolve(this._workingImg);
        },
        enterTool(id) {
            this._resolveBase(id).then((base) => {
                if (!base || this.activeTool !== id) return;
                this._toolBase = base;
                this._baseW = base.naturalWidth; this._baseH = base.naturalHeight;
                if (id === 'crop') this._initCropper();
                if (id === 'resize') { this.rsWidth = String(this._baseW); this.rsHeight = String(this._baseH); }
                if (id === 'adjust') {
                    this.adj = (this._lastTool === 'adjust' && this._lastAdj)
                        ? { ...this._lastAdj }
                        : { exposure: 0, brightness: 0, contrast: 0, saturation: 0, vibrance: 0, temperature: 0, tint: 0, hue: 0 };
                    this.$nextTick(() => this._adjRender());
                }
            });
        },

        /* ---- Crop (cropperjs, lazy-loaded) ---- */
        async _initCropper() {
            if (!this._Cropper) {
                const mod = await import('cropperjs');
                await import('cropperjs/dist/cropper.css');
                this._Cropper = mod.default;
            }
            this.$nextTick(() => {
                this._destroyCropper();
                const img = this.$refs.cropImg;
                if (!img || this.activeTool !== 'crop') return;
                const start = () => {
                    if (this.activeTool !== 'crop') return;
                    this._cropper = new this._Cropper(img, {
                        aspectRatio: this.aspectKey === 'Free' ? NaN : (this.aspects.find((a) => a.label === this.aspectKey) || {}).value,
                        viewMode: 1, dragMode: 'move', autoCropArea: 1,
                        responsive: true, restore: false, checkCrossOrigin: false, background: true, guides: true,
                    });
                };
                img.src = (this._toolBase || this._workingImg).src;
                if (img.complete) start(); else img.onload = start;
            });
        },
        _destroyCropper() { if (this._cropper) { this._cropper.destroy(); this._cropper = null; } },
        setAspect(p) {
            this.aspectKey = p.label;
            if (this._cropper) this._cropper.setAspectRatio(isNaN(p.value) ? NaN : p.value);
        },
        applyCrop() {
            if (!this._cropper) return;
            const raw = this._cropper.getCroppedCanvas({ imageSmoothingQuality: 'high' });
            if (!raw) return;
            const src = raw.toDataURL('image/png');
            this._destroyCropper();
            this.commit(src, { tool: 'crop' }, () => this.enterTool('crop'));
        },

        /* ---- Resize ---- */
        onRsWidth(v) {
            this.rsWidth = v; const n = parseInt(v, 10);
            if (this.rsLock && this._baseW && n > 0) this.rsHeight = String(Math.round(n * (this._baseH / this._baseW)));
        },
        onRsHeight(v) {
            this.rsHeight = v; const n = parseInt(v, 10);
            if (this.rsLock && this._baseH && n > 0) this.rsWidth = String(Math.round(n * (this._baseW / this._baseH)));
        },
        applyResize() {
            const base = this._toolBase || this._workingImg; if (!base) return;
            const w = Math.max(1, parseInt(this.rsWidth, 10) || this._baseW);
            const h = Math.max(1, parseInt(this.rsHeight, 10) || this._baseH);
            if (w === this._baseW && h === this._baseH) { toast('Already that size'); return; }
            const canvas = document.createElement('canvas');
            canvas.width = w; canvas.height = h;
            canvas.getContext('2d').drawImage(base, 0, 0, w, h);
            this.commit(canvas.toDataURL('image/png'), { tool: 'resize', replace: true });
        },

        /* ---- Rotate / Flip (applied immediately) ---- */
        rotate(deg) {
            const img = this._workingImg; if (!img) return;
            const w = img.naturalWidth, h = img.naturalHeight;
            const canvas = document.createElement('canvas');
            const quarter = Math.abs(deg) % 180 !== 0;
            canvas.width = quarter ? h : w; canvas.height = quarter ? w : h;
            const ctx = canvas.getContext('2d');
            ctx.translate(canvas.width / 2, canvas.height / 2);
            ctx.rotate(deg * Math.PI / 180);
            ctx.drawImage(img, -w / 2, -h / 2);
            this.commit(canvas.toDataURL('image/png'), { tool: 'transform' });
        },
        flip(axis) {
            const img = this._workingImg; if (!img) return;
            const canvas = document.createElement('canvas');
            canvas.width = img.naturalWidth; canvas.height = img.naturalHeight;
            const ctx = canvas.getContext('2d');
            if (axis === 'h') { ctx.translate(canvas.width, 0); ctx.scale(-1, 1); }
            else { ctx.translate(0, canvas.height); ctx.scale(1, -1); }
            ctx.drawImage(img, 0, 0);
            this.commit(canvas.toDataURL('image/png'), { tool: 'transform' });
        },

        /* ---- Adjust (color) ---- */
        resetAdj() { this.adj = { exposure: 0, brightness: 0, contrast: 0, saturation: 0, vibrance: 0, temperature: 0, tint: 0, hue: 0 }; },
        _adjDirty() { return Object.values(this.adj).some((v) => v !== 0); },
        onAdjust() { clearTimeout(this._adjDebounce); this._adjDebounce = setTimeout(() => this._adjRender(), 90); },
        // Live preview onto $refs.adjustCanvas. Scaled down for snappy slider response;
        // the full-resolution version is baked only on Apply.
        _adjRender() {
            const cnv = this.$refs.adjustCanvas, img = this._toolBase || this._workingImg;
            if (!cnv || !img) return;
            const maxDim = 1600;
            const scale = Math.min(1, maxDim / Math.max(img.naturalWidth, img.naturalHeight));
            const w = Math.max(1, Math.round(img.naturalWidth * scale));
            const h = Math.max(1, Math.round(img.naturalHeight * scale));
            cnv.width = w; cnv.height = h;
            const ctx = cnv.getContext('2d');
            ctx.drawImage(img, 0, 0, w, h);
            if (this._adjDirty()) {
                const id = ctx.getImageData(0, 0, w, h);
                adjustPixels(id.data, this.adj);
                ctx.putImageData(id, 0, 0);
            }
        },
        applyAdjust() {
            if (!this._adjDirty()) { toast('No adjustments to apply'); return; }
            const img = this._toolBase || this._workingImg; if (!img) return;
            const cnv = document.createElement('canvas');
            cnv.width = img.naturalWidth; cnv.height = img.naturalHeight;
            const ctx = cnv.getContext('2d');
            ctx.drawImage(img, 0, 0);
            const id = ctx.getImageData(0, 0, cnv.width, cnv.height);
            adjustPixels(id.data, this.adj);
            ctx.putImageData(id, 0, 0);
            const src = cnv.toDataURL('image/png');
            this._lastAdj = { ...this.adj };
            this.commit(src, { tool: 'adjust', replace: true }, () => this.enterTool('adjust'));
        },

        /* ---- Export ---- */
        getCanvas() {
            if (!this._workingImg) return null;
            const c = document.createElement('canvas');
            c.width = this._workingImg.naturalWidth; c.height = this._workingImg.naturalHeight;
            c.getContext('2d').drawImage(this._workingImg, 0, 0);
            return c;
        },
        saveName() { return this.fileMeta?.name ?? 'image'; },
        fmtBytes: smFmtBytes,
        get sizeDelta() {
            if (!this.fileMeta || this.sm_outputSize === null) return null;
            return Math.round((1 - this.sm_outputSize / this.fileMeta.size) * 100);
        },
    }));

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
});
