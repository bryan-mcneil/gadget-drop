/*
 | DOCX → PDF conversion engine (browser only).
 |
 | Dynamically imported by the `docxToPdf` Alpine component in tools.js, so
 | mammoth + jsPDF only download when someone actually drops a .docx on
 | /tools/docx-to-pdf — the other tool pages never pay for the weight.
 |
 | Pipeline:  .docx --mammoth--> semantic HTML --(this module)--> laid-out jsPDF.
 |
 | The output holds REAL text (selectable, searchable, copy-pasteable, small)
 | rather than a page raster, which is the whole point of the tool: the "free
 | docx to pdf" sites that screenshot each page produce 10x bigger files you
 | can't search. Everything runs on-device; nothing is uploaded.
 |
 | Known limits, surfaced to the user rather than hidden (see WARNINGS below):
 | jsPDF's built-in fonts are WinAnsi (Latin-1 + cp1252), so non-Latin scripts
 | can't be embedded without shipping a multi-MB font; table rowspans and
 | Word's exact pagination are approximated.
 */

import mammoth from 'mammoth';
import { jsPDF } from 'jspdf';

/* ── Options ─────────────────────────────────────────────────── */

export const MARGIN_PRESETS = { narrow: 36, normal: 72, wide: 90 }; // points (72pt = 1in)

const DEFAULTS = {
    pageSize: 'letter',   // 'letter' | 'a4'
    orientation: 'p',     // 'p' | 'l'
    margin: 'normal',     // key of MARGIN_PRESETS
    font: 'helvetica',    // 'helvetica' | 'times'
    fontSize: 11,         // points
    pageNumbers: false,
};

// Word style names mammoth doesn't map on its own. Appended to (not replacing)
// mammoth's default map, so Heading 1-6 / lists / bold / italic still apply.
const STYLE_MAP = [
    'u => u',
    'strike => s',
    "p[style-name='Title'] => h1:fresh",
    "p[style-name='Subtitle'] => h2:fresh",
    "p[style-name='Quote'] => blockquote:fresh",
    "p[style-name='Intense Quote'] => blockquote:fresh",
    "p[style-name='Caption'] => p.caption:fresh",
];

const HEADING_SCALE = { h1: 1.85, h2: 1.45, h3: 1.22, h4: 1.08, h5: 1.0, h6: 0.94 };
const LINE_HEIGHT = 1.42;
const PARA_GAP = 0.55;          // × base font size
const LIST_INDENT = 20;         // points per nesting level
const BULLETS = ['•', 'o', '-']; // all WinAnsi-safe (Word's own defaults)
const INK = [17, 24, 39];
const LINK_INK = [29, 78, 216];
const QUOTE_INK = [75, 85, 99];
const RULE = [209, 213, 219];

/* ── Character support ───────────────────────────────────────────
 | The 14 built-in PDF fonts encode WinAnsi: Latin-1 plus the cp1252 range
 | (smart quotes, dashes, bullet, ellipsis, euro …). Anything else has no
 | glyph. We fold the common typographic strays down to safe equivalents and
 | report whatever is left so the user hears it from us, not from a mangled
 | PDF.                                                                      */

const CP1252_EXTRA = '€‚ƒ„…†‡ˆ‰Š‹ŒŽ‘’“”•–—˜™š›œžŸ';

// Keys are written as escapes on purpose: half of them are invisible in an editor.
const CHAR_FALLBACK = {
    '\u00a0': ' ', '\u2007': ' ', '\u2009': ' ', '\u202f': ' ', '\u2002': ' ', '\u2003': ' ',
    '\u200b': '', '\u200c': '', '\u200d': '', '\ufeff': '',
    '\u2028': ' ', '\u2029': ' ', '\u2011': '-', '\u2012': '\u2013', '\u2212': '-',
    '\u2192': '->', '\u2190': '<-', '\u2194': '<->', '\u21d2': '=>', '\u21d0': '<=',
    '\u2264': '<=', '\u2265': '>=', '\u2260': '!=', '\u2248': '~',
    '\u25cf': '\u2022', '\u25aa': '\u2022', '\u25a0': '\u2022', '\u25e6': 'o', '\u2043': '-',
    '\u2713': '[x]', '\u2714': '[x]', '\u2717': '[ ]', '\u2610': '[ ]', '\u2611': '[x]',
};

// Every key is non-ASCII, so none of them are character-class metacharacters.
const FALLBACK_RE = new RegExp(`[${Object.keys(CHAR_FALLBACK).join('')}]`, 'g');

const isSupported = (ch) => ch.codePointAt(0) < 256 || CP1252_EXTRA.includes(ch);

// Fold the known strays, then replace anything still unencodable with '?' and
// record it. Silent corruption is the one outcome worth ruling out.
const encodeText = (text, unsupported) => {
    let out = text.replace(FALLBACK_RE, (ch) => CHAR_FALLBACK[ch]);
    if (![...out].some((ch) => !isSupported(ch))) return out;
    out = [...out].map((ch) => {
        if (isSupported(ch)) return ch;
        unsupported.add(ch);
        return '?';
    }).join('');
    return out;
};

/* ── Layout context ──────────────────────────────────────────── */

const newContext = (doc, opts) => ({
    doc,
    family: opts.font,
    base: opts.fontSize,
    margin: MARGIN_PRESETS[opts.margin] ?? MARGIN_PRESETS.normal,
    pageW: doc.internal.pageSize.getWidth(),
    pageH: doc.internal.pageSize.getHeight(),
    get contentW() { return this.pageW - this.margin * 2; },
    y: 0,
    unsupported: new Set(),
    skippedImages: 0,
    complexTable: false,
});

const bottom = (ctx) => ctx.pageH - ctx.margin;
const newPage = (ctx) => { ctx.doc.addPage(); ctx.y = ctx.margin; };
const ensure = (ctx, height) => {
    if (ctx.y + height > bottom(ctx) && ctx.y > ctx.margin) newPage(ctx);
};

/* ── Inline runs ─────────────────────────────────────────────────
 | Flatten a node's children into styled segments so a single paragraph can mix
 | bold / italic / links / super- and subscript and still wrap correctly.      */

const BLOCK_INSIDE_INLINE = /^(p|div|h[1-6])$/i;

const safeHref = (href) => {
    if (!href) return null;
    try {
        const url = new URL(href, window.location.href);
        // Only ever emit navigable, non-scripting protocols into the PDF.
        return ['http:', 'https:', 'mailto:'].includes(url.protocol) ? url.href : null;
    } catch {
        return null;
    }
};

const collectInline = (nodes, style, out = []) => {
    nodes.forEach((node) => {
        if (node.nodeType === Node.TEXT_NODE) {
            if (node.nodeValue) out.push({ ...style, text: node.nodeValue });
            return;
        }
        if (node.nodeType !== Node.ELEMENT_NODE) return;

        const tag = node.tagName.toLowerCase();
        if (tag === 'br') { out.push({ br: true }); return; }
        if (tag === 'img') return; // images are placed as blocks, never inline

        if (BLOCK_INSIDE_INLINE.test(tag) && out.length) out.push({ br: true });

        const next = { ...style };
        if (tag === 'strong' || tag === 'b') next.bold = true;
        if (tag === 'em' || tag === 'i') next.italic = true;
        if (tag === 'u') next.underline = true;
        if (tag === 's' || tag === 'strike' || tag === 'del') next.strike = true;
        if (tag === 'code' || tag === 'kbd' || tag === 'samp' || tag === 'tt') next.mono = true;
        if (tag === 'sup') next.sup = true;
        if (tag === 'sub') next.sub = true;
        if (tag === 'a') {
            const href = safeHref(node.getAttribute('href'));
            if (href) next.href = href;
        }
        collectInline([...node.childNodes], next, out);
    });
    return out;
};

const fontFamily = (ctx, seg) => (seg.mono ? 'courier' : ctx.family);
const fontStyle = (seg) => {
    if (seg.bold && seg.italic) return 'bolditalic';
    if (seg.bold) return 'bold';
    if (seg.italic) return 'italic';
    return 'normal';
};
const runSize = (seg, size) => (seg.sup || seg.sub ? size * 0.72 : size);

const measure = (ctx, seg, text, size) => {
    ctx.doc.setFont(fontFamily(ctx, seg), fontStyle(seg));
    ctx.doc.setFontSize(runSize(seg, size));
    return ctx.doc.getTextWidth(text);
};

// Break a token that can't fit any line on its own (long URLs, hashes).
const breakToken = (ctx, seg, token, size, width) => {
    const parts = [];
    let current = '';
    for (const ch of token) {
        if (current && measure(ctx, seg, current + ch, size) > width) {
            parts.push(current);
            current = ch;
        } else {
            current += ch;
        }
    }
    if (current) parts.push(current);
    return parts;
};

// Greedy word wrap across mixed-style segments → array of lines of runs.
const layoutInline = (ctx, segments, size, width) => {
    const lines = [];
    let line = [];
    let lineWidth = 0;
    const flush = () => { lines.push(line); line = []; lineWidth = 0; };

    for (const seg of segments) {
        if (seg.br) { flush(); continue; }
        if (typeof seg.text !== 'string') continue;

        const text = encodeText(seg.text, ctx.unsupported);
        for (const rawToken of text.split(/(\s+)/)) {
            if (rawToken === '') continue;
            const isSpace = /^\s+$/.test(rawToken);
            if (isSpace) {
                if (lineWidth === 0) continue; // never open a line with a space
                const w = measure(ctx, seg, ' ', size);
                line.push({ ...seg, text: ' ', w });
                lineWidth += w;
                continue;
            }

            let width0 = measure(ctx, seg, rawToken, size);
            if (width0 > width) {
                for (const part of breakToken(ctx, seg, rawToken, size, width)) {
                    const pw = measure(ctx, seg, part, size);
                    if (lineWidth + pw > width && lineWidth > 0) flush();
                    line.push({ ...seg, text: part, w: pw });
                    lineWidth += pw;
                }
                continue;
            }
            if (lineWidth + width0 > width && lineWidth > 0) flush();
            line.push({ ...seg, text: rawToken, w: width0 });
            lineWidth += width0;
        }
    }
    if (line.length) flush();
    return lines.length ? lines : [];
};

const SAME_STYLE_KEYS = ['bold', 'italic', 'mono', 'sup', 'sub', 'underline', 'strike', 'href'];
const sameStyle = (a, b) => SAME_STYLE_KEYS.every((k) => (a[k] ?? false) === (b[k] ?? false));

// Wrapping works word by word, but painting a line as one text operator per
// word would bloat the content stream and leave PDF readers to re-infer the
// spaces on copy. Merge neighbouring runs that share a style back into a
// single draw (and a single link annotation) first.
const coalesce = (line) => {
    const runs = [];
    for (const run of line) {
        const last = runs[runs.length - 1];
        if (last && sameStyle(last, run)) {
            last.text += run.text;
            last.w += run.w;
        } else {
            runs.push({ ...run });
        }
    }
    return runs;
};

// Paint one prepared line at an absolute position (no pagination).
const paintLine = (ctx, line, x, top, size, color) => {
    const { doc } = ctx;
    const baseline = top + size * 0.86;
    let cx = x;
    for (const run of coalesce(line)) {
        const fs = runSize(run, size);
        doc.setFont(fontFamily(ctx, run), fontStyle(run));
        doc.setFontSize(fs);
        const dy = run.sup ? -size * 0.3 : run.sub ? size * 0.15 : 0;
        const y = baseline + dy;

        if (run.href) {
            doc.setTextColor(...LINK_INK);
            doc.textWithLink(run.text, cx, y, { url: run.href });
        } else {
            doc.setTextColor(...color);
            doc.text(run.text, cx, y);
        }
        if (run.href || run.underline) {
            doc.setDrawColor(...(run.href ? LINK_INK : color));
            doc.setLineWidth(0.5);
            doc.line(cx, y + fs * 0.12, cx + run.w, y + fs * 0.12);
        }
        if (run.strike) {
            doc.setDrawColor(...color);
            doc.setLineWidth(0.5);
            doc.line(cx, y - fs * 0.28, cx + run.w, y - fs * 0.28);
        }
        cx += run.w;
    }
    doc.setTextColor(...INK);
};

// Paint lines down the page, breaking pages as needed.
const drawLines = (ctx, lines, x, size, color = INK) => {
    const lh = size * LINE_HEIGHT;
    for (const line of lines) {
        ensure(ctx, lh);
        paintLine(ctx, line, x, ctx.y, size, color);
        ctx.y += lh;
    }
};

/* ── Images ──────────────────────────────────────────────────── */

const loadImage = (src) => new Promise((resolve) => {
    const img = new Image();
    img.onload = () => resolve(img);
    img.onerror = () => resolve(null);
    img.src = src;
});

const MAX_IMAGE_EDGE = 2200; // px; beyond this we downsample before embedding

// jsPDF takes JPEG/PNG directly — pass those straight through so screenshots
// stay lossless. Anything else (or anything oversized) goes via canvas.
const prepareImage = (img, src) => {
    const w = img.naturalWidth || img.width;
    const h = img.naturalHeight || img.height;
    if (!w || !h) return null;

    const isJpeg = /^data:image\/jpe?g/i.test(src);
    const isPng = /^data:image\/png/i.test(src);
    const oversized = Math.max(w, h) > MAX_IMAGE_EDGE;

    if ((isJpeg || isPng) && !oversized) {
        return { data: src, format: isJpeg ? 'JPEG' : 'PNG', w, h };
    }

    const scale = Math.min(1, MAX_IMAGE_EDGE / Math.max(w, h));
    const canvas = document.createElement('canvas');
    canvas.width = Math.max(1, Math.round(w * scale));
    canvas.height = Math.max(1, Math.round(h * scale));
    const c2d = canvas.getContext('2d');
    c2d.fillStyle = '#ffffff'; // PDF pages are white; flatten any alpha onto it
    c2d.fillRect(0, 0, canvas.width, canvas.height);
    c2d.drawImage(img, 0, 0, canvas.width, canvas.height);

    const megapixels = (canvas.width * canvas.height) / 1e6;
    const usePng = isPng && megapixels < 1.2;
    return {
        data: canvas.toDataURL(usePng ? 'image/png' : 'image/jpeg', 0.85),
        format: usePng ? 'PNG' : 'JPEG',
        w, h,
    };
};

const drawImage = async (ctx, src) => {
    const img = await loadImage(src);
    const prepared = img && prepareImage(img, src);
    if (!prepared) { ctx.skippedImages += 1; return; }

    // Word stores image sizes in px at 96dpi; PDF works in 72dpi points.
    let width = Math.min(ctx.contentW, prepared.w * 0.75);
    let height = width * (prepared.h / prepared.w);
    const maxHeight = ctx.pageH - ctx.margin * 2;
    if (height > maxHeight) {
        height = maxHeight;
        width = height * (prepared.w / prepared.h);
    }

    ensure(ctx, height);
    try {
        ctx.doc.addImage(prepared.data, prepared.format, ctx.margin + (ctx.contentW - width) / 2, ctx.y, width, height);
        ctx.y += height + ctx.base * PARA_GAP;
    } catch {
        ctx.skippedImages += 1;
    }
};

/* ── Tables ──────────────────────────────────────────────────── */

const CELL_PAD_X = 5;
const CELL_PAD_Y = 4;

const buildTableModel = (ctx, table) => {
    const rows = [...table.querySelectorAll('tr')].map((tr) => {
        const cells = [...tr.children].filter((c) => /^(td|th)$/i.test(c.tagName));
        return cells.map((cell) => {
            if (parseInt(cell.getAttribute('rowspan') ?? '1', 10) > 1) ctx.complexTable = true;
            return {
                segments: collectInline([...cell.childNodes], {}),
                header: cell.tagName.toLowerCase() === 'th',
                span: Math.max(1, parseInt(cell.getAttribute('colspan') ?? '1', 10)),
                text: cell.textContent ?? '',
            };
        });
    }).filter((r) => r.length);

    if (!rows.length) return null;
    const columns = Math.max(...rows.map((r) => r.reduce((sum, c) => sum + c.span, 0)));

    // Width by content weight so a narrow "Qty" column doesn't get the same
    // share as a paragraph-long "Notes" column. Clamped so nothing collapses.
    const weights = new Array(columns).fill(6);
    rows.forEach((row) => {
        let col = 0;
        row.forEach((cell) => {
            if (cell.span === 1 && col < columns) {
                weights[col] = Math.max(weights[col], Math.min(cell.text.trim().length || 1, 48));
            }
            col += cell.span;
        });
    });
    const total = weights.reduce((a, b) => a + b, 0);
    const widths = weights.map((w) => (ctx.contentW * w) / total);

    return { rows, widths, headerRow: rows[0].some((c) => c.header) ? rows[0] : null };
};

const layoutRow = (ctx, row, widths, size) => {
    let col = 0;
    const cells = row.map((cell) => {
        const width = widths.slice(col, col + cell.span).reduce((a, b) => a + b, 0);
        col += cell.span;
        const segments = cell.header
            ? cell.segments.map((s) => ({ ...s, bold: true }))
            : cell.segments;
        const lines = layoutInline(ctx, segments, size, Math.max(12, width - CELL_PAD_X * 2));
        return { ...cell, width, lines };
    });
    const height = Math.max(
        size * LINE_HEIGHT + CELL_PAD_Y * 2,
        ...cells.map((c) => c.lines.length * size * LINE_HEIGHT + CELL_PAD_Y * 2),
    );
    return { cells, height };
};

const paintRow = (ctx, laid, size) => {
    const { doc } = ctx;
    let x = ctx.margin;
    for (const cell of laid.cells) {
        doc.setDrawColor(...RULE);
        doc.setLineWidth(0.5);
        if (cell.header) {
            doc.setFillColor(243, 244, 246);
            doc.rect(x, ctx.y, cell.width, laid.height, 'FD');
        } else {
            doc.rect(x, ctx.y, cell.width, laid.height, 'S');
        }
        let ly = ctx.y + CELL_PAD_Y;
        for (const line of cell.lines) {
            paintLine(ctx, line, x + CELL_PAD_X, ly, size, INK);
            ly += size * LINE_HEIGHT;
        }
        x += cell.width;
    }
    ctx.y += laid.height;
};

const drawTable = (ctx, table) => {
    const model = buildTableModel(ctx, table);
    if (!model) return;

    const size = ctx.base * 0.92;
    const header = model.headerRow ? layoutRow(ctx, model.headerRow, model.widths, size) : null;

    model.rows.forEach((row, index) => {
        const laid = layoutRow(ctx, row, model.widths, size);
        if (ctx.y + laid.height > bottom(ctx) && ctx.y > ctx.margin) {
            newPage(ctx);
            // Repeat the header on the continuation page so the table stays readable.
            if (header && index > 0) paintRow(ctx, header, size);
        }
        paintRow(ctx, laid, size);
    });
    ctx.y += ctx.base * PARA_GAP;
};

/* ── Blocks ──────────────────────────────────────────────────── */

const blockImages = (el) => [...el.querySelectorAll('img')]
    .map((img) => img.getAttribute('src'))
    .filter((src) => src && src.startsWith('data:'));

const drawList = async (ctx, list, ordered, depth) => {
    const items = [...list.children].filter((c) => c.tagName.toLowerCase() === 'li');
    let index = parseInt(list.getAttribute('start') ?? '1', 10) || 1;
    const size = ctx.base;
    const indent = ctx.margin + LIST_INDENT * depth;
    const textX = indent + LIST_INDENT;

    for (const li of items) {
        const nested = [...li.children].filter((c) => /^(ul|ol)$/i.test(c.tagName));
        const own = [...li.childNodes].filter((n) => !(n.nodeType === Node.ELEMENT_NODE && /^(ul|ol)$/i.test(n.tagName)));
        const lines = layoutInline(ctx, collectInline(own, {}), size, ctx.pageW - ctx.margin - textX);
        const marker = ordered ? `${index}.` : BULLETS[depth % BULLETS.length];

        if (lines.length) {
            ensure(ctx, size * LINE_HEIGHT);
            ctx.doc.setFont(ctx.family, 'normal');
            ctx.doc.setFontSize(size);
            ctx.doc.setTextColor(...INK);
            ctx.doc.text(marker, indent, ctx.y + size * 0.86);
            drawLines(ctx, lines, textX, size);
        }
        for (const src of blockImages(li)) await drawImage(ctx, src);
        for (const child of nested) {
            await drawList(ctx, child, child.tagName.toLowerCase() === 'ol', depth + 1);
        }
        index += 1;
    }
    ctx.y += ctx.base * 0.35;
};

const drawBlock = async (ctx, el, depth = 0) => {
    const tag = el.tagName.toLowerCase();

    if (HEADING_SCALE[tag]) {
        const size = ctx.base * HEADING_SCALE[tag];
        const lines = layoutInline(ctx, collectInline([...el.childNodes], { bold: true }), size, ctx.contentW);
        if (!lines.length) return;
        if (ctx.y > ctx.margin) ctx.y += size * 0.5;
        // Keep-with-next: never strand a heading on the last line of a page.
        ensure(ctx, size * LINE_HEIGHT + ctx.base * LINE_HEIGHT);
        drawLines(ctx, lines, ctx.margin, size);
        ctx.y += size * 0.22;
        return;
    }

    switch (tag) {
        case 'p': {
            const images = blockImages(el);
            const lines = layoutInline(ctx, collectInline([...el.childNodes], {}), ctx.base, ctx.contentW);
            if (lines.length) {
                drawLines(ctx, lines, ctx.margin, ctx.base);
                ctx.y += ctx.base * PARA_GAP;
            }
            for (const src of images) await drawImage(ctx, src);
            return;
        }
        case 'ul':
        case 'ol':
            await drawList(ctx, el, tag === 'ol', 0);
            return;
        case 'table':
            drawTable(ctx, el);
            return;
        case 'blockquote': {
            const inner = [...el.children].length
                ? [...el.children].flatMap((c) => collectInline([...c.childNodes], { italic: true }))
                : collectInline([...el.childNodes], { italic: true });
            const x = ctx.margin + LIST_INDENT;
            const lines = layoutInline(ctx, inner, ctx.base, ctx.contentW - LIST_INDENT);
            if (!lines.length) return;
            const startY = ctx.y;
            const startPage = ctx.doc.getCurrentPageInfo().pageNumber;
            drawLines(ctx, lines, x, ctx.base, QUOTE_INK);
            if (ctx.doc.getCurrentPageInfo().pageNumber === startPage) {
                ctx.doc.setDrawColor(...RULE);
                ctx.doc.setLineWidth(2);
                ctx.doc.line(ctx.margin + 4, startY, ctx.margin + 4, ctx.y - ctx.base * 0.3);
            }
            ctx.y += ctx.base * PARA_GAP;
            return;
        }
        case 'pre': {
            const lines = layoutInline(ctx, collectInline([...el.childNodes], { mono: true }), ctx.base * 0.92, ctx.contentW);
            drawLines(ctx, lines, ctx.margin, ctx.base * 0.92);
            ctx.y += ctx.base * PARA_GAP;
            return;
        }
        case 'hr':
            ensure(ctx, ctx.base * 2);
            ctx.y += ctx.base * 0.5;
            ctx.doc.setDrawColor(...RULE);
            ctx.doc.setLineWidth(0.7);
            ctx.doc.line(ctx.margin, ctx.y, ctx.pageW - ctx.margin, ctx.y);
            ctx.y += ctx.base * 0.8;
            return;
        case 'img':
            await drawImage(ctx, el.getAttribute('src'));
            return;
        case 'br':
            ctx.y += ctx.base * LINE_HEIGHT;
            return;
        default: {
            // Wrapper element (div/section/article) — walk into it.
            if (depth > 6) return;
            for (const child of [...el.children]) await drawBlock(ctx, child, depth + 1);
        }
    }
};

/* ── Public API ──────────────────────────────────────────────── */

/**
 * Extract semantic HTML from a .docx. Kept separate from rendering so changing
 * a page-size dropdown re-renders without re-parsing the archive.
 *
 * @param {ArrayBuffer} arrayBuffer
 * @returns {Promise<{html: string, messages: string[]}>}
 */
export async function readDocx(arrayBuffer) {
    const result = await mammoth.convertToHtml({ arrayBuffer }, { styleMap: STYLE_MAP });
    const messages = [...new Set(
        (result.messages ?? [])
            .filter((m) => m.type === 'warning' || m.type === 'error')
            .map((m) => m.message),
    )];
    return { html: result.value ?? '', messages };
}

/**
 * Lay the extracted HTML out into a PDF.
 *
 * @param {string} html      output of readDocx()
 * @param {object} options   see DEFAULTS
 * @returns {Promise<{blob: Blob, pages: number, warnings: string[]}>}
 */
export async function renderPdf(html, options = {}) {
    const opts = { ...DEFAULTS, ...options };
    const doc = new jsPDF({
        unit: 'pt',
        format: opts.pageSize,
        orientation: opts.orientation,
        compress: true,
    });
    doc.setProperties({ title: opts.title ?? '', creator: 'GadgetDrop DOCX to PDF' });

    const ctx = newContext(doc, opts);
    ctx.y = ctx.margin;
    doc.setTextColor(...INK);

    const parsed = new DOMParser().parseFromString(`<div id="root">${html}</div>`, 'text/html');
    const root = parsed.getElementById('root');
    const blocks = root ? [...root.children] : [];

    for (let i = 0; i < blocks.length; i += 1) {
        await drawBlock(ctx, blocks[i]);
        // Yield to the event loop so a long document can't freeze the tab.
        if (i % 20 === 19) await new Promise((resolve) => setTimeout(resolve, 0));
    }

    if (opts.pageNumbers) {
        const total = doc.getNumberOfPages();
        for (let page = 1; page <= total; page += 1) {
            doc.setPage(page);
            doc.setFont(opts.font, 'normal');
            doc.setFontSize(9);
            doc.setTextColor(107, 114, 128);
            doc.text(`${page} / ${total}`, ctx.pageW / 2, ctx.pageH - ctx.margin / 2, { align: 'center' });
        }
    }

    const warnings = [];
    if (ctx.unsupported.size) {
        const sample = [...ctx.unsupported].slice(0, 6).join(' ');
        warnings.push(`Some characters (${sample}${ctx.unsupported.size > 6 ? ' …' : ''}) aren't available in the standard PDF fonts and were replaced with "?". This tool covers Latin-script documents; non-Latin scripts need an embedded font.`);
    }
    if (ctx.skippedImages) {
        warnings.push(`${ctx.skippedImages} image${ctx.skippedImages > 1 ? 's' : ''} could not be embedded (Word charts, WMF/EMF drawings, and SmartArt aren't real images).`);
    }
    if (ctx.complexTable) {
        warnings.push('A table uses merged rows (rowspan). Merged cells are flattened, so check those rows.');
    }

    return { blob: doc.output('blob'), pages: doc.getNumberOfPages(), warnings };
}
