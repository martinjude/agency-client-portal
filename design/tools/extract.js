// Extrait la mise en page rendue (boîtes, lignes de texte, SVG) de pages web vers des fichiers de données pour Illustrator.
// Usage : node extract.js <config.json> [ids]   (voir design/design.md → « Importer une page »)
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const OUT = path.join(__dirname, 'out');
fs.mkdirSync(path.join(OUT, 'svg'), { recursive: true });

const PAGES = [];
const only = process.argv[3] ? process.argv[3].split(',') : null;
// Pages et éléments ignorés configurables (ex. barre WP et ancienne sidebar)
const CONF = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));
CONF.pages.forEach((p) => PAGES.push(p));
const SKIP = CONF.skip || [];

async function extractInPage(SKIP) {
  const ctx2d = document.createElement('canvas').getContext('2d');
  const out = [];
  let order = 0;
  const parse = (s) => {
    const m = s && s.match(/rgba?\(([^)]+)\)/);
    if (!m) return null;
    const p = m[1].split(/[\s,\/]+/).filter(Boolean).map(parseFloat);
    return [p[0], p[1], p[2], p.length > 3 ? p[3] : 1];
  };
  const inter = (a, b) => {
    const x = Math.max(a.x, b.x), y = Math.max(a.y, b.y);
    const r = Math.min(a.x + a.w, b.x + b.w), btm = Math.min(a.y + a.h, b.y + b.h);
    return { x, y, w: Math.max(0, r - x), h: Math.max(0, btm - y) };
  };
  const name = (el) => el.tagName.toLowerCase() + (el.id ? '#' + el.id : '') + (el.classList[0] ? '.' + el.classList[0] : '');
  const box = (el) => { const r = el.getBoundingClientRect(); return { x: r.left + scrollX, y: r.top + scrollY, w: r.width, h: r.height }; };
  const radius = (cs, b) => {
    const v = cs.borderTopLeftRadius.split(' ')[0];
    let r = v.endsWith('%') ? parseFloat(v) / 100 * Math.min(b.w, b.h) : parseFloat(v) || 0;
    return Math.min(r, b.w / 2, b.h / 2);
  };
  const fontOf = (cs) => ({
    fam: cs.fontFamily.split(',')[0].replace(/["']/g, '').trim(),
    wt: parseInt(cs.fontWeight) || 400,
    it: cs.fontStyle === 'italic',
  });
  const metrics = (cs, txt) => {
    ctx2d.font = `${cs.fontStyle} ${cs.fontWeight} ${cs.fontSize} ${cs.fontFamily}`;
    const m = ctx2d.measureText(txt || 'Hg');
    return { asc: m.fontBoundingBoxAscent, desc: m.fontBoundingBoxDescent, w: m.width };
  };
  const transform = (s, cs) => cs.textTransform === 'uppercase' ? s.toUpperCase()
    : cs.textTransform === 'lowercase' ? s.toLowerCase()
    : cs.textTransform === 'capitalize' ? s.replace(/\b\w/g, (c) => c.toUpperCase()) : s;
  const pushText = (s, x, baseline, cs, opacity, z) => {
    const c = parse(cs.color); if (!c || c[3] === 0) return;
    const sz = parseFloat(cs.fontSize);
    const ls = parseFloat(cs.letterSpacing) || 0;
    out.push({ t: 't', s, x, y: baseline, sz, font: fontOf(cs), c, tr: Math.round(ls / sz * 1000), o: opacity, z, ord: order++ });
  };

  function svgItem(el, b, opacity, z, clip) {
    const clone = el.cloneNode(true);
    const src = [el, ...el.querySelectorAll('*')], dst = [clone, ...clone.querySelectorAll('*')];
    src.forEach((s, i) => {
      const cs = getComputedStyle(s), d = dst[i];
      ['fill', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'fill-opacity', 'stroke-opacity', 'opacity', 'fill-rule']
        .forEach((p) => { const v = cs.getPropertyValue(p); if (v) d.setAttribute(p, v); });
      d.removeAttribute('class'); d.removeAttribute('style');
    });
    const vb = el.viewBox && el.viewBox.baseVal && el.viewBox.baseVal.width ? el.viewBox.baseVal : { x: 0, y: 0, width: b.w, height: b.h };
    clone.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
    clone.setAttribute('width', b.w); clone.setAttribute('height', b.h);
    clone.removeAttribute('opacity');
    const bb = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
    bb.setAttribute('id', 'bbox'); bb.setAttribute('x', vb.x); bb.setAttribute('y', vb.y);
    bb.setAttribute('width', vb.width); bb.setAttribute('height', vb.height); bb.setAttribute('fill', 'none'); bb.setAttribute('stroke', 'none');
    clone.insertBefore(bb, clone.firstChild);
    out.push({ t: 'svg', n: 'icon ' + name(el.parentElement || el), x: b.x, y: b.y, w: b.w, h: b.h, svg: clone.outerHTML, o: opacity, z, ord: order++ });
  }

  async function walk(el, clip, opacity, z) {
    if (SKIP.some((sel) => el.matches(sel))) return;
    const cs = getComputedStyle(el);
    if (cs.display === 'none') return;
    opacity *= parseFloat(cs.opacity);
    if (opacity < 0.02) return;
    if (cs.position !== 'static' && cs.zIndex !== 'auto' && z === 0) z = parseInt(cs.zIndex) || 0;
    if (cs.position === 'fixed') clip = { x: 0, y: 0, w: document.documentElement.scrollWidth, h: document.documentElement.scrollHeight };
    const b = box(el);
    const vis = cs.visibility !== 'hidden';
    const tag = el.tagName.toLowerCase();
    const shown = inter(b, clip);

    if (tag === 'svg') { if (vis && shown.w > 0.5 && shown.h > 0.5) svgItem(el, b, opacity, z, clip); return; }

    if (vis && shown.w > 0.5 && shown.h > 0.5) {
      const r = radius(cs, b);
      let fill = parse(cs.backgroundColor); if (fill && fill[3] === 0) fill = null;
      let grad = null, placeholder = false;
      const bi = cs.backgroundImage;
      if (bi && bi !== 'none') {
        if (/gradient/.test(bi)) {
          const g = bi.match(/linear-gradient\((.*)\)/);
          if (g) {
            const body = g[1];
            let ang = 180; const am = body.match(/^\s*(-?[\d.]+)deg/);
            if (am) ang = parseFloat(am[1]);
            else if (/^\s*to right/.test(body)) ang = 90; else if (/^\s*to left/.test(body)) ang = 270; else if (/^\s*to top/.test(body)) ang = 0;
            const stops = [...body.matchAll(/(rgba?\([^)]+\))\s*([\d.]+%)?/g)].map((m, i, all) => ({
              c: parse(m[1]), p: m[2] ? parseFloat(m[2]) : (all.length > 1 ? i / (all.length - 1) * 100 : 0),
            }));
            if (stops.length >= 2) grad = { ang, stops };
          }
        }
        if (/url\(/.test(bi)) placeholder = true;
      }
      if (tag === 'img' || tag === 'video' || tag === 'iframe' || tag === 'canvas') placeholder = true;
      const bw = ['Top', 'Right', 'Bottom', 'Left'].map((s) => cs['border' + s + 'Style'] === 'none' ? 0 : parseFloat(cs['border' + s + 'Width']) || 0);
      const bc = ['Top', 'Right', 'Bottom', 'Left'].map((s) => parse(cs['border' + s + 'Color']));
      const uniform = bw.every((w) => w === bw[0]) && bc.every((c) => c && bc[0] && c.join() === bc[0].join());
      const n = name(el);
      const base = { x: b.x, y: b.y, w: b.w, h: b.h, r, o: opacity, z };
      // Anneaux box-shadow sans flou (ex. focus / avatar actif)
      if (cs.boxShadow && cs.boxShadow !== 'none') {
        const rings = cs.boxShadow.split(/,(?![^(]*\))/).map((sh) => {
          const c = parse(sh); const nums = sh.replace(/rgba?\([^)]*\)/, '').trim().split(/\s+/).map(parseFloat);
          return { c, ox: nums[0], oy: nums[1], blur: nums[2] || 0, spread: nums[3] || 0, inset: /inset/.test(sh) };
        }).filter((r) => r.c && !r.inset && r.blur === 0 && r.ox === 0 && r.oy === 0 && r.spread > 0);
        rings.reverse().forEach((rg) => out.push({ t: 'r', n: n + ' [ring]', x: b.x - rg.spread, y: b.y - rg.spread, w: b.w + 2 * rg.spread, h: b.h + 2 * rg.spread, r: r + rg.spread, f: rg.c, o: opacity, z, ord: order++ }));
      }
      if (fill) out.push({ t: 'r', n, ...base, f: fill, ord: order++ });
      if (tag === 'img' && /\.svg(\?|$)/i.test(el.currentSrc || el.src)) { out.push({ t: 'svgurl', n: 'emoji/icon ' + name(el), ...base, url: el.currentSrc || el.src, ord: order++ }); placeholder = false; }
      if (placeholder) out.push({ t: 'r', n: n + ' [image]', ...base, f: [72, 72, 86, 1], ord: order++ });
      if (grad) out.push({ t: 'r', n: n + ' [gradient]', ...base, g: grad, ord: order++ });
      if (uniform && bw[0] > 0 && bc[0][3] > 0) out.push({ t: 'r', n: n + ' [border]', ...base, s: bc[0], sw: bw[0], ord: order++ });
      else if (!uniform) {
        const sides = [[b.x, b.y, b.w, bw[0]], [b.x + b.w - bw[1], b.y, bw[1], b.h], [b.x, b.y + b.h - bw[2], b.w, bw[2]], [b.x, b.y, bw[3], b.h]];
        sides.forEach((s, i) => { if (bw[i] > 0 && bc[i] && bc[i][3] > 0) out.push({ t: 'r', n: n + ' [border]', x: s[0], y: s[1], w: s[2], h: s[3], r: 0, f: bc[i], o: opacity, z, ord: order++ }); });
      }
      // Champs de formulaire : valeur ou placeholder
      if (tag === 'input' || tag === 'textarea' || tag === 'select') {
        const type = (el.type || '').toLowerCase();
        if (type === 'checkbox' || type === 'radio') {
          if (cs.appearance !== 'none') {
            out.push({ t: 'r', n: n, ...base, r: type === 'radio' ? b.w / 2 : 3, f: el.checked ? [0, 117, 255, 1] : [255, 255, 255, 1], s: el.checked ? [0, 117, 255, 1] : [118, 118, 118, 1], sw: 1, ord: order++ });
          }
        } else if (type === 'color') {
          const v = el.value; const rgb = [1, 3, 5].map((i) => parseInt(v.substr(i, 2), 16));
          out.push({ t: 'r', n: n + ' [swatch]', x: b.x + 4, y: b.y + 4, w: b.w - 8, h: b.h - 8, r: 2, f: [...rgb, 1], o: opacity, z, ord: order++ });
        } else if (type !== 'hidden' && type !== 'file') {
          let txt = tag === 'select' ? (el.selectedOptions[0] ? el.selectedOptions[0].text : '') : el.value;
          let tcs = cs;
          if (!txt && el.placeholder) { txt = el.placeholder; tcs = getComputedStyle(el, '::placeholder'); }
          if (txt) {
            const m = metrics(cs, txt);
            const bt = parseFloat(cs.borderTopWidth), bl = parseFloat(cs.borderLeftWidth), pt = parseFloat(cs.paddingTop), pl = parseFloat(cs.paddingLeft);
            const pb = parseFloat(cs.paddingBottom), bb2 = parseFloat(cs.borderBottomWidth), pr = parseFloat(cs.paddingRight);
            let x = b.x + bl + pl, baseline;
            if (tag === 'textarea') {
              const lh = parseFloat(cs.lineHeight) || parseFloat(cs.fontSize) * 1.4;
              baseline = b.y + bt + pt + (lh - m.asc - m.desc) / 2 + m.asc;
              txt = txt.split('\n')[0];
              const lines = (tag === 'textarea' ? (el.value || el.placeholder) : txt).split('\n');
              lines.forEach((ln, i) => { if (ln && baseline + i * lh < b.y + b.h) pushText(ln, x, baseline + i * lh, tcs, opacity, z); });
            } else {
              const ch = b.h - bt - bb2 - pt - pb;
              baseline = b.y + bt + pt + ch / 2 + (m.asc - m.desc) / 2;
              if (type === 'submit' || type === 'button') x = b.x + (b.w - m.w) / 2;
              const maxW = b.w - bl - pl - pr;
              let s = txt; while (s.length > 1 && metrics(cs, s).w > maxW) s = s.slice(0, -1);
              pushText(s, x, baseline, tcs, opacity, z);
            }
          }
        }
      }
    }

    if (tag === 'input' || tag === 'textarea' || tag === 'select' || tag === 'option') return;
    let childClip = clip;
    if (cs.overflowX !== 'visible' || cs.overflowY !== 'visible') childClip = inter(clip, b);

    for (const node of el.childNodes) {
      if (node.nodeType === 1) await walk(node, childClip, opacity, z);
      else if (node.nodeType === 3 && vis && node.textContent.trim()) {
        const text = node.textContent;
        const words = [...text.matchAll(/\S+/g)];
        const lines = [];
        for (const w of words) {
          const rg = document.createRange(); rg.setStart(node, w.index); rg.setEnd(node, w.index + w[0].length);
          const rects = rg.getClientRects(); if (!rects.length) continue;
          const rc = rects[0];
          const last = lines[lines.length - 1];
          if (last && Math.abs(last.top - rc.top) < 2) { last.end = w.index + w[0].length; last.right = rc.right; }
          else lines.push({ top: rc.top, left: rc.left, right: rc.right, start: w.index, end: w.index + w[0].length });
        }
        const m = metrics(cs);
        for (const ln of lines) {
          const lb = { x: ln.left + scrollX, y: ln.top + scrollY, w: ln.right - ln.left, h: m.asc + m.desc };
          const vis2 = inter(lb, childClip);
          if (vis2.w < lb.w * 0.4 || vis2.h < lb.h * 0.4) continue;
          let s = transform(text.slice(ln.start, ln.end).replace(/\s+/g, ' '), cs);
          // text-overflow: ellipsis — tronquer comme le navigateur
          const maxRight = childClip.x + childClip.w;
          if (cs.textOverflow === 'ellipsis' && lb.x + lb.w > maxRight + 0.5) {
            while (s.length > 1 && lb.x + metrics(cs, s + '…').w > maxRight) s = s.slice(0, -1);
            s = s.replace(/\s+$/, '') + '…';
          }
          pushText(s, lb.x, lb.y + m.asc, cs, opacity, z);
        }
      }
    }
  }

  const W = document.documentElement.scrollWidth, H = document.documentElement.scrollHeight;
  const bodyBg = parse(getComputedStyle(document.body).backgroundColor);
  const htmlBg = parse(getComputedStyle(document.documentElement).backgroundColor);
  const pageBg = bodyBg && bodyBg[3] > 0 ? bodyBg : (htmlBg && htmlBg[3] > 0 ? htmlBg : [255, 255, 255, 1]);
  out.push({ t: 'r', n: 'page background', x: 0, y: 0, w: W, h: H, r: 0, f: pageBg, o: 1, z: -1e9, ord: order++ });
  await walk(document.body, { x: 0, y: 0, w: W, h: H }, 1, 0);
  out.sort((a, b) => (a.z - b.z) || (a.ord - b.ord));
  return { W, H, items: out };
}

(async () => {
  const b = await chromium.launch(process.env.PW_CHROMIUM ? { executablePath: process.env.PW_CHROMIUM } : {});
  for (const [id, url, auth] of PAGES) {
    if (only && !only.includes(id)) continue;
    const ctx = await b.newContext({ ignoreHTTPSErrors: true, viewport: CONF.viewport || { width: 1440, height: 900 }, ...(auth ? { storageState: path.join(__dirname, '.auth', 'state.json') } : {}) });
    const p = await ctx.newPage();
    await p.goto(url.replace('__DESIGN__', path.resolve(__dirname, '..')), { waitUntil: 'networkidle' });
    await p.waitForTimeout(1200);
    const data = await p.evaluate(extractInPage, SKIP);
    for (const it of data.items) if (it.t === 'svgurl') {
      try {
        let svg = await (await fetch(it.url)).text();
        const vb = (svg.match(/viewBox="([^"]+)"/) || [])[1] || `0 0 ${it.w} ${it.h}`;
        const [vx, vy, vw, vh] = vb.split(/[\s,]+/);
        svg = svg.replace(/<svg([^>]*)>/, (m, a) => `<svg${a.replace(/\s(width|height)="[^"]*"/g, '')} width="${it.w}" height="${it.h}"><rect id="bbox" x="${vx}" y="${vy}" width="${vw}" height="${vh}" fill="none" stroke="none"/>`);
        it.t = 'svg'; it.svg = svg;
      } catch (e) { it.t = 'skip'; }
    }
    data.items = data.items.filter((it) => it.t !== 'skip');
    data.items.forEach((it, i) => {
      if (it.t === 'svg') { it.file = path.join(OUT, 'svg', `p${id}_${i}.svg`); fs.writeFileSync(it.file, it.svg); delete it.svg; }
    });
    fs.writeFileSync(path.join(OUT, `page${id}.js`), 'var DATA = ' + JSON.stringify(data).replace(/[\u007f-\uffff]/g, (c) => '\\u' + c.charCodeAt(0).toString(16).padStart(4, '0')) + ';');
    const counts = data.items.reduce((a, it) => (a[it.t] = (a[it.t] || 0) + 1, a), {});
    console.log(id, data.W + 'x' + data.H, JSON.stringify(counts));
    await ctx.close();
  }
  await b.close();
})();
