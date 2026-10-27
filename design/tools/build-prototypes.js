// Génère prototypes/{components,page-portail,page-projet}.html (planche MAIN DESIGN SYSTEM + pages 04 / 05). Usage : node build-prototypes.js
const fs = require('fs');
const path = require('path');
const OUT = path.join(__dirname, '..', 'prototypes');

const A = '#52525B', B = '#A1A1AA', T3 = '#71717A';
const st = (w = 1.6) => `stroke="currentColor" stroke-width="${w}" fill="none" stroke-linecap="round" stroke-linejoin="round"`;

// Icônes 20×20 — bicolores pour les types de bloc, traits (currentColor) pour les utilitaires
const ICONS = {
  dash: `<rect x="2" y="2" width="7" height="7" rx="2" fill="${B}"/><rect x="11" y="2" width="7" height="7" rx="3.5" fill="${A}"/><rect x="2" y="11" width="7" height="7" rx="2" fill="${A}"/><rect x="11" y="11" width="7" height="7" rx="2" fill="${A}"/>`,
  comments: `<path d="M10 2.5c4.4 0 8 3 8 6.8s-3.6 6.8-8 6.8c-1 0-2-.2-2.9-.5L3 17l1-3.6C2.8 12.2 2 10.8 2 9.3 2 5.5 5.6 2.5 10 2.5z" fill="${A}"/><circle cx="6.8" cy="9.4" r="1.2" fill="${B}"/><circle cx="10" cy="9.4" r="1.2" fill="${B}"/><circle cx="13.2" cy="9.4" r="1.2" fill="${B}"/>`,
  inbox: `<path d="M5 3h10l2 8h-4l-1.5 2h-3L7 11H3z" fill="${A}"/><path d="M3 11h4l1.5 2h3L13 11h4v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" fill="${B}"/>`,
  folder: `<path d="M2 5a2 2 0 0 1 2-2h3.6l2 2H16a2 2 0 0 1 2 2v1H2z" fill="${B}"/><rect x="2" y="7" width="16" height="10" rx="2" fill="${A}"/>`,
  video: `<rect x="2" y="4" width="12" height="12" rx="2.5" fill="${A}"/><path d="M14 8.5l4-2.5v8l-4-2.5z" fill="${B}"/><path d="M6.5 7.5v5l4-2.5z" fill="${B}"/>`,
  storyboard: `<rect x="2" y="4" width="16" height="12" rx="2" fill="${A}"/><rect x="4.5" y="7" width="5" height="6" rx="1" fill="${B}"/><rect x="10.5" y="7" width="5" height="6" rx="1" fill="${B}"/>`,
  versioned: `<path d="M10 2l8 4-8 4-8-4z" fill="${B}"/><path d="M2 10l8 4 8-4v2.5l-8 4-8-4z" fill="${A}"/>`,
  script: `<path d="M5 2h7l4 4v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2z" fill="${A}"/><path d="M12 2l4 4h-3a1 1 0 0 1-1-1z" fill="${B}"/><rect x="6" y="9" width="7" height="1.6" rx=".8" fill="${B}"/><rect x="6" y="12.5" width="5" height="1.6" rx=".8" fill="${B}"/>`,
  link: `<path d="M8.5 11.5l3-3" stroke="${B}" stroke-width="1.8" stroke-linecap="round"/><path d="M9 5.5l1.5-1.5a3.5 3.5 0 0 1 5 5L14 10.5M11 14.5L9.5 16a3.5 3.5 0 0 1-5-5L6 9.5" stroke="${A}" stroke-width="2.2" fill="none" stroke-linecap="round"/>`,
  deposit: `<path d="M3 12h4l1.5 2h3L13 12h4v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" fill="${A}"/><path d="M10 2l4 4h-2.5v5h-3V6H6z" fill="${B}"/>`,
  code: `<rect x="2" y="3" width="16" height="14" rx="2.5" fill="${A}"/><path d="M8 7.5L5.5 10 8 12.5M12 7.5l2.5 2.5-2.5 2.5" stroke="${B}" stroke-width="1.6" fill="none" stroke-linecap="round" stroke-linejoin="round"/>`,
  progress: `<rect x="2" y="7.5" width="16" height="5" rx="2.5" fill="${A}"/><rect x="2" y="7.5" width="9.5" height="5" rx="2.5" fill="${B}"/>`,
  separator: `<rect x="2" y="3.5" width="16" height="3" rx="1.5" fill="${A}"/><rect x="2" y="13.5" width="16" height="3" rx="1.5" fill="${A}"/><path d="M4 10h12" stroke="${B}" stroke-width="1.8" stroke-linecap="round"/>`,
  stack: `<path d="M10 2.5l7 3.5-7 3.5L3 6z" fill="${B}"/><path d="M3 10l7 3.5 7-3.5v2l-7 3.5L3 12z" fill="${A}"/>`,
  settings: `<circle cx="10" cy="10" r="7.5" fill="${A}"/><circle cx="10" cy="10" r="3" fill="${B}"/>`,
  wp: `<rect x="3" y="3" width="14" height="14" rx="3" fill="${A}"/><path d="M7 10h6M10 7v6" stroke="${B}" stroke-width="1.6" stroke-linecap="round"/>`,
  logout: `<path d="M3 4a2 2 0 0 1 2-2h5v16H5a2 2 0 0 1-2-2z" fill="${A}"/><path d="M12.5 6.5L16 10l-3.5 3.5M16 10H8" stroke="#F87171" stroke-width="1.6" fill="none" stroke-linecap="round" stroke-linejoin="round"/>`,
  file: `<path d="M5 2h7l4 4v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2z" fill="${A}"/><path d="M12 2l4 4h-3a1 1 0 0 1-1-1z" fill="${B}"/>`,
  bulb: `<path d="M10 2a5.5 5.5 0 0 0-3.2 10c.5.4.7.9.7 1.5v.5h5v-.5c0-.6.2-1.1.7-1.5A5.5 5.5 0 0 0 10 2z" fill="#FACC15"/><rect x="7.5" y="15" width="5" height="2" rx="1" fill="#A1A1AA"/>`,
  // utilitaires (traits)
  search: `<circle cx="9" cy="9" r="5.5" ${st()}/><path d="M13.2 13.2L17 17" ${st()}/>`,
  filter: `<path d="M3.5 5h13l-5 6v4.5l-3-1.5V11z" ${st(1.5)}/>`,
  grid: `<rect x="3" y="3" width="6" height="6" rx="1.8" ${st(1.5)}/><rect x="11" y="3" width="6" height="6" rx="1.8" ${st(1.5)}/><rect x="3" y="11" width="6" height="6" rx="1.8" ${st(1.5)}/><rect x="11" y="11" width="6" height="6" rx="1.8" ${st(1.5)}/>`,
  list: `<path d="M7.5 5h9M7.5 10h9M7.5 15h9" ${st(1.5)}/><circle cx="3.8" cy="5" r=".9" fill="currentColor"/><circle cx="3.8" cy="10" r=".9" fill="currentColor"/><circle cx="3.8" cy="15" r=".9" fill="currentColor"/>`,
  plus: `<path d="M10 4v12M4 10h12" ${st(1.8)}/>`,
  dotsV: `<circle cx="10" cy="4.5" r="1.5" fill="currentColor"/><circle cx="10" cy="10" r="1.5" fill="currentColor"/><circle cx="10" cy="15.5" r="1.5" fill="currentColor"/>`,
  dotsH: `<circle cx="4.5" cy="10" r="1.5" fill="currentColor"/><circle cx="10" cy="10" r="1.5" fill="currentColor"/><circle cx="15.5" cy="10" r="1.5" fill="currentColor"/>`,
  chevD: `<path d="M5 7.5l5 5 5-5" ${st(1.6)}/>`,
  chevR: `<path d="M7.5 5l5 5-5 5" ${st(1.6)}/>`,
  chevL: `<path d="M12.5 5l-5 5 5 5" ${st(1.6)}/>`,
  pen: `<path d="M12.5 3.5l4 4L8 16H4v-4z" ${st(1.5)}/>`,
  copy: `<rect x="7" y="7" width="10" height="10" rx="2.2" ${st(1.5)}/><path d="M13 7V5.2A2.2 2.2 0 0 0 10.8 3H5.2A2.2 2.2 0 0 0 3 5.2v5.6A2.2 2.2 0 0 0 5.2 13H7" ${st(1.5)}/>`,
  mail: `<rect x="2.5" y="4.5" width="15" height="11" rx="2.5" ${st(1.5)}/><path d="M3.5 6.5l6.5 5 6.5-5" ${st(1.5)}/>`,
  trash: `<path d="M4.5 6h11M8 6V4.2c0-.4.3-.7.7-.7h2.6c.4 0 .7.3.7.7V6M6 6l.6 9.2c0 .5.4.8.9.8h5c.5 0 .9-.3.9-.8L14 6" ${st(1.5)}/>`,
  upload: `<path d="M10 13V4M6.5 7.5L10 4l3.5 3.5M4 13.5v1.3c0 .7.5 1.2 1.2 1.2h9.6c.7 0 1.2-.5 1.2-1.2v-1.3" ${st(1.5)}/>`,
  ext: `<path d="M8 4.5H5.5a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V12M11.5 3.5h5v5M16.5 3.5L9.5 10.5" ${st(1.5)}/>`,
  close: `<path d="M5 5l10 10M15 5L5 15" ${st(1.6)}/>`,
  check: `<path d="M4.5 10.5l3.5 3.5 7.5-8" ${st(1.9)}/>`,
  bubble: `<path d="M10 3c3.9 0 7 2.5 7 5.7s-3.1 5.7-7 5.7c-.8 0-1.6-.1-2.3-.3L4 16l.8-3C3.7 12 3 10.4 3 8.7 3 5.5 6.1 3 10 3z" ${st(1.4)}/>`,
  play: `<path d="M7.5 5.5v9l7.5-4.5z" fill="currentColor"/>`,
  info: `<circle cx="10" cy="10" r="7.5" ${st(1.5)}/><path d="M10 9v4.5M10 6.5v.1" ${st(1.7)}/>`,
  warn: `<path d="M10 3.5l7.5 13h-15z" ${st(1.5)}/><path d="M10 8.5v3.5M10 14.3v.1" ${st(1.7)}/>`,
  clock: `<circle cx="10" cy="10" r="7" ${st(1.5)}/><path d="M10 6v4.3l2.6 1.6" ${st(1.5)}/>`,
  bell: `<path d="M5.5 8a4.5 4.5 0 0 1 9 0c0 4 1.5 5 1.5 5H4s1.5-1 1.5-5zM8.3 16a1.9 1.9 0 0 0 3.4 0" ${st(1.5)}/>`,
  image: `<rect x="3" y="3.5" width="14" height="13" rx="2.5" ${st(1.5)}/><circle cx="7.5" cy="8" r="1.4" fill="currentColor"/><path d="M3.5 14l4-3.5 3 2.5 3-3 3 3" ${st(1.5)}/>`,
  drag: `<circle cx="7.5" cy="5" r="1.2" fill="currentColor"/><circle cx="12.5" cy="5" r="1.2" fill="currentColor"/><circle cx="7.5" cy="10" r="1.2" fill="currentColor"/><circle cx="12.5" cy="10" r="1.2" fill="currentColor"/><circle cx="7.5" cy="15" r="1.2" fill="currentColor"/><circle cx="12.5" cy="15" r="1.2" fill="currentColor"/>`,
  lock: `<path d="M6.5 8.5V6.5a3.5 3.5 0 0 1 7 0v2" stroke="${B}" stroke-width="1.8" fill="none" stroke-linecap="round"/><rect x="3.5" y="8.5" width="13" height="9" rx="2.5" fill="${A}"/><circle cx="10" cy="12.2" r="1.5" fill="${B}"/><path d="M10 13v2" stroke="${B}" stroke-width="1.4" stroke-linecap="round"/>`,
  key: `<circle cx="6.5" cy="13.5" r="3.5" ${st(1.5)}/><path d="M9 11l7-7M13.5 6.5l2 2M11.5 8.5l1.5 1.5" ${st(1.5)}/>`,
  logo: `<path d="M15.5 2.5c-5.5 1-9 5-10.5 12l-1.5 3 3-1.5c6.5-1.5 10.5-5 9-13.5z" fill="#18181B"/><path d="M5 14.5l5.5-6" stroke="#F4F4F5" stroke-width="1.4" stroke-linecap="round"/>`,
};
const ico = (n, s = 16, extra = '') => `<svg width="${s}" height="${s}" viewBox="0 0 20 20" ${extra}>${ICONS[n]}</svg>`;
const chev = (dir = 'd', s = 12, col = T3) => `<svg width="${s}" height="${s}" viewBox="0 0 20 20" style="color:${col}">${ICONS['chev' + dir.toUpperCase()]}</svg>`;

const BLOCK_TYPES = [
  ['video', 'Vidéo'], ['storyboard', 'Storyboard'], ['versioned', 'Média versionné'], ['script', 'Script'],
  ['link', 'Lien'], ['deposit', 'Dépôt de fichiers'], ['code', 'Code'], ['progress', 'Barre de progression'], ['separator', 'Séparateur'],
];

const head = (title, w, h) => `<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>${title}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=block" rel="stylesheet">
<link rel="stylesheet" href="ag-components.css">
<style>body { width: ${w}px; height: ${h}px; }</style>
</head>
<body>
`;
const foot = `</body>\n</html>\n`;

// ---------- Cartes ----------
const typeTag = (t, label, client = false) => `<span class="tag icon${client ? ' client' : ''}">${ico(t, 12)}${label}</span>`;
const moreBtn = (hover = false) => `<div class="icon-btn ghost more${hover ? ' is-hover' : ''}">${ico('dotsV', 16)}</div>`;

const PREVIEW = {
  video: () => `<div class="cover is-img"><div class="cv img"></div><div class="cv-sq"></div><div class="play">${ico('play', 18)}</div><span class="chip-ov" style="right:10px;bottom:10px">0:30</span></div>`,
  storyboard: () => `<div class="cover"><div class="cv"></div><div class="cv-sq"></div><div class="mosaic"><div class="big"></div><div class="sm"><div class="im"></div><div class="im"></div></div></div><span class="chip-ov" style="left:18px;bottom:18px">12 plans</span></div>`,
  versioned: () => `<div class="cover"><div class="cv"></div><div class="cv-sq"></div><div class="poster"></div><span class="chip-ov" style="right:10px;top:10px;color:#7C83FD">V3</span></div>`,
  script: () => `<div class="cover"><div class="cv"></div><div class="cv-sq"></div><div class="txt"><div class="sc">INT. STUDIO — JOUR</div>Une lumière douce traverse l’atelier.<br>Les mains de Léa effleurent la maquette.<div class="who">LÉA (V.O.)</div>« Tout commence par un geste. »</div><div class="fade"></div></div>`,
  link: () => `<div class="cover"><div class="cv"></div><div class="cv-sq"></div><div class="linkprev"><div class="fav">${ico('link', 20)}</div><div class="nm">Moodboard Figma</div><div class="dom">figma.com</div></div><span class="chip-ov" style="right:10px;top:10px;padding:0 4px">${ico('ext', 12, 'style="margin-top:4px"')}</span></div>`,
  deposit: () => `<div class="cover"><div class="cv"></div><div class="cv-sq"></div><div class="files"><div class="file">${ico('file', 14)}logo_lumen.svg<span class="sz">24 Ko</span></div><div class="file">${ico('video', 14)}rushs_01.mov<span class="sz">1,2 Go</span></div><div class="file">${ico('file', 14)}photos_produit.zip<span class="sz">48 Mo</span></div></div></div>`,
  code: () => `<div class="cover" style="--cv:#0E0E10"><div class="cv"></div><div class="cv-sq"></div><div class="code"><span class="k">&lt;iframe</span>\n  src=<span class="s">"player.lumen.fr/e/aurora"</span>\n  width=<span class="s">"640"</span> height=<span class="s">"360"</span>\n  allow=<span class="s">"autoplay"</span>\n<span class="k">&gt;&lt;/iframe&gt;</span></div></div>`,
};
// La carte « code » a besoin d'un fond plus sombre que le panneau : liseré bas pour la séparer du corps.

function blockCard({ type, label, title, meta, comments = 0, unread = false, state = '', client = false }) {
  const cm = comments ? `<span class="chat${unread ? ' unread' : ''}">${unread ? '<i class="udot"></i>' : ''}${ico('bubble', 14)}${comments}</span>` : `<span class="chat">${ico('bubble', 14)}0</span>`;
  return `<div class="card${state ? ' is-' + state : ''}">${PREVIEW[type === 'deposit' ? 'deposit' : type]()}<div class="body"><div class="tags">${typeTag(type, label, client)}${moreBtn(state === 'hover')}</div><div class="ttl"><span class="t">${title}</span></div><div class="meta">${meta}</div><div class="foot">${cm}</div></div></div>`;
}
function projectCard({ title, labels = [], blocks, updated, state = '', cover = true }) {
  const tags = labels.map((l) => `<span class="tag outline">${l}</span>`).join('');
  return `<div class="card${state ? ' is-' + state : ''}"><div class="cover is-img"><div class="cv img"></div><div class="cv-sq"></div></div><div class="body"><div class="tags">${tags}${moreBtn(state === 'hover')}</div><div class="ttl">${ico('bulb', 18)}<span class="t">${title}</span></div><div class="meta">${ico('stack', 12)}${blocks} · ${updated}</div></div></div>`;
}
const quickMenu = (style = '') => `<div class="menu" style="width:216px;${style}"><div class="row"><span style="color:#A1A1AA">${ico('ext', 16)}</span><span class="lbl">Ouvrir le bloc</span></div><div class="row"><span style="color:#A1A1AA">${ico('pen', 16)}</span><span class="lbl">Renommer</span></div><div class="row"><span style="color:#A1A1AA">${ico('copy', 16)}</span><span class="lbl">Dupliquer</span></div><div class="row"><span style="color:#A1A1AA">${ico('mail', 16)}</span><span class="lbl">Notifier le client</span></div><div class="row"><span style="color:#A1A1AA">${ico('link', 16)}</span><span class="lbl">Copier le lien</span></div><hr><div class="row danger"><span style="color:#F87171">${ico('trash', 16)}</span><span class="lbl">Supprimer</span></div></div>`;
const pickerMenu = (style = '') => `<div class="menu" style="width:244px;${style}"><div class="mlabel">Type de bloc</div>${BLOCK_TYPES.map(([t, l], i) => `<div class="row${i === 0 ? ' is-hover' : ''}">${ico(t, 16)}<span class="lbl">${l}</span></div>`).join('')}</div>`;

const toolbar = (ph, primary, extra = '') => `<div class="toolbar"><div class="input search"><span style="color:#71717A">${ico('search', 16)}</span><span class="grow ph">${ph}</span><kbd>⌘K</kbd></div>${extra}<button class="btn secondary" style="gap:8px"><span style="color:#A1A1AA">${ico('filter', 16)}</span>Filtrer</button><div class="seg"><div class="s on">${ico('grid', 16)}</div><div class="s">${ico('list', 16)}</div></div><button class="btn primary"><span>${ico('plus', 16)}</span>${primary}</button></div>`;

// ====================================================================
// PAGE 04 — Portail client
// ====================================================================
// ---------- Clients, modale, accès refusé ----------
const CLIENTS = [
  { n: 'Lumen', c: '#7C83FD', i: 'L', p: '1 projet' },
  { n: 'Orbe', c: '#F59E0B', i: 'O', p: '2 projets' },
  { n: 'Studio Kasa', c: '#10B981', i: 'S', p: '0 projet' },
  { n: 'Maison Vega', c: '#EC4899', i: 'M', p: '1 projet', off: true },
];
function clientCard({ n, c, i, p, off = false, state = '' }) {
  const av = c ? `<div class="av s40" style="background:${c}">${i}</div>` : `<div class="av s40" style="background:#3F3F46;color:#A1A1AA">${i}</div>`;
  return `<div class="cc${state ? ' is-' + state : ''}"><div class="top">${av}<div class="who"><div class="nm"${off ? ' style="color:var(--ag-text-3)"' : ''}>${n}</div><div class="count">${p}</div></div><span class="pill ${off ? 'off' : 'ok'}" style="margin-left:auto">${off ? 'Inactif' : 'Actif'}</span></div><div class="btns"><button class="btn secondary sm">Modifier</button><button class="btn secondary sm">Projets</button><div class="icon-btn sm" style="margin-left:auto">${ico('copy', 16)}</div><div class="icon-btn sm">${ico('key', 16)}</div></div></div>`;
}
const newClientModal = (style = '') => `<div class="modal" style="width:480px;${style}"><div class="mh">Nouveau client<div class="icon-btn ghost x">${ico('close', 16)}</div></div><div class="mb" style="gap:18px"><div class="col"><div class="lbl-f">Nom du client *</div><div class="input is-focus"><span class="grow ph">Ex : Entreprise ABC</span></div></div><div class="col"><div class="lbl-f">Logo</div><div style="display:flex;gap:8px"><div class="input" style="flex:1"><span class="grow ph">https://…</span></div><button class="btn secondary">Choisir</button></div><div class="help">Affiché dans la sidebar et le portail.</div></div><div class="col"><div class="lbl-f">Couleur d’accent</div><div style="display:flex;gap:8px"><div class="input" style="flex:1"><div class="swatch"></div><span class="grow mono">#7C83FD</span></div></div><div style="display:flex;gap:8px;margin-top:10px"><div class="av s28 ring" style="background:#7C83FD"></div><div class="av s28" style="background:#F59E0B"></div><div class="av s28" style="background:#10B981"></div><div class="av s28" style="background:#EC4899"></div><div class="av s28" style="background:#60A5FA"></div></div></div></div><div class="mf"><button class="btn ghost">Annuler</button><button class="btn primary">Créer le client</button></div></div>`;
const accessCard = () => `<div class="access"><div class="ico-tile">${ico('lock', 28)}</div><div class="t">Merci d’utiliser votre lien pour accéder à votre espace.</div><div class="d">Aucun lien d’accès détecté. Veuillez utiliser le lien fourni par votre prestataire.</div><button class="btn secondary">Retour à l’accueil</button><div class="foot">Erreur 403 · lien manquant ou expiré</div></div>`;

function dashBody() {
  return `
<main style="position:absolute;left:312px;top:40px;width:1096px">
  <div class="page-h"><div><h1>Bonjour, Martin !</h1><div class="meta">Une nouvelle semaine, de nouveaux projets. · 4 clients · 4 projets</div></div></div>
  <div style="margin-top:28px">${toolbar('Rechercher un client', 'Nouveau client')}</div>
  <div class="sec-h" style="margin-top:28px">Clients <span class="badge">4</span></div>
  <div style="display:grid;grid-template-columns:repeat(4,262px);gap:16px;margin-top:14px;align-items:start">${CLIENTS.map((c) => clientCard(c)).join('')}</div>
</main>`;
}
function pageDashboard() { return head('Page 01 — Dashboard', 1440, 900) + dashBody() + foot; }
function pageNouveauClient() {
  return head('Page 02 — Nouveau client', 1440, 900) + dashBody() +
    `<div class="scrim"></div><div style="position:absolute;left:480px;top:150px">${newClientModal()}</div>` + foot;
}
function pageAccesRefuse() {
  return head('Page 08 — Accès refusé', 1440, 900) +
    `<div class="center"><img class="brand" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" width="48" height="48" alt="">${accessCard()}</div>` + foot;
}

function pagePortail() {
  const W = 1440, H = 900;
  const cards = [
    projectCard({ title: 'Lancement Aurora', labels: ['Lancement', 'Vidéo'], blocks: '7 blocs', updated: 'il y a 2 h' }),
    projectCard({ title: 'Identité visuelle 2027', labels: ['Branding'], blocks: '4 blocs', updated: 'il y a 3 j' }),
    projectCard({ title: 'Film de marque', labels: ['Vidéo', 'Print'], blocks: '2 blocs', updated: 'il y a 1 sem.' }),
    `<div class="card add" style="height:auto"><div class="icon-btn" style="width:36px;height:36px">${ico('plus', 18)}</div>Ajouter un projet</div>`,
  ];
  return head('Page 04 — Portail client', W, H) + `
<main style="position:absolute;left:312px;top:40px;width:1096px">
  <div class="page-h">
    <div class="av s40" style="background:#7C83FD;width:48px;height:48px;border-radius:12px;font-size:18px">L</div>
    <div><h1>Lumen</h1><div class="meta">3 projets · 13 blocs · portail actif</div></div>
    <span class="pill ok" style="margin-left:8px">Actif</span>
    <div style="margin-left:auto;display:flex;gap:8px"><button class="btn secondary">${ico('copy', 16)}Copier le lien du portail</button><div class="icon-btn" style="width:36px;height:36px">${ico('dotsH', 16)}</div></div>
  </div>
  <div style="margin-top:28px">${toolbar('Rechercher un projet', 'Ajouter un projet', `<button class="btn ghost" style="border-color:var(--ag-border)">${ico('folder', 16)}Archivés <span class="badge">0</span></button>`)}</div>
  <div class="sec-h" style="margin-top:28px">Projets <span class="badge">3</span></div>
  <div style="display:grid;grid-template-columns:repeat(4,262px);gap:16px;margin-top:14px;align-items:stretch">${cards.join('')}</div>
</main>
<footer style="position:absolute;left:0;right:0;top:820px;height:80px;border-top:1px solid var(--ag-border);background:var(--ag-bg)">
  <div style="position:absolute;left:312px;top:30px;display:flex;align-items:center;gap:12px;font:400 13px/18px var(--ag-font);color:var(--ag-text-3)"><span>Derniers projets :</span><span style="color:var(--ag-text-2)">Lancement Aurora</span><span>·</span><span style="color:var(--ag-text-2)">Identité visuelle 2027</span></div>
  <div style="position:absolute;right:32px;top:24px;width:96px;height:32px;border-radius:6px;background-image:url('data:image/gif;base64,R0lGODlhAQABAAAAACw=')"></div>
</footer>
` + foot;
}

// ====================================================================
// PAGE 05 — Projet
// ====================================================================
function pageProjet() {
  const W = 1440, H = 900;
  const b = [
    blockCard({ type: 'video', label: 'Vidéo', title: 'Teaser 30s', meta: 'Mis à jour il y a 1 h', comments: 2, unread: true }),
    blockCard({ type: 'storyboard', label: 'Storyboard', title: 'Storyboard — Film principal', meta: 'Mis à jour hier', comments: 5, state: 'hover' }),
    blockCard({ type: 'versioned', label: 'Média versionné', title: 'Affiche 40×60', meta: 'Mis à jour il y a 3 j', comments: 0 }),
    blockCard({ type: 'script', label: 'Script', title: 'Script voix off', meta: 'Mis à jour il y a 4 h', comments: 1, unread: true }),
    blockCard({ type: 'link', label: 'Lien', title: 'Moodboard Figma', meta: 'Ajouté il y a 1 sem.', comments: 0 }),
    blockCard({ type: 'deposit', label: 'Dépôt de fichiers', title: 'Dépôt des rushs', meta: '3 fichiers reçus', comments: 0 }),
    blockCard({ type: 'code', label: 'Code', title: 'Intégration du player', meta: 'Mis à jour il y a 2 j', comments: 3 }),
    `<div class="card add" style="height:auto"><div class="icon-btn" style="width:36px;height:36px">${ico('plus', 18)}</div>Nouveau bloc</div>`,
  ];
  return head('Page 05 — Projet', W, H) + `
<main style="position:absolute;left:312px;top:40px;width:1096px">
  <div class="crumbs" style="margin-bottom:14px"><span>Lumen</span>${chev('r', 12)}<span class="cur">Lancement Aurora</span></div>
  <div class="page-h">
    ${ico('bulb', 36)}
    <div><h1>Lancement Aurora</h1><div class="meta">7 blocs · 11 commentaires · mis à jour il y a 1 h</div></div>
    <div style="margin-left:auto;display:flex;gap:8px"><button class="btn secondary">${ico('image', 16)}Couverture</button><div class="icon-btn" style="width:36px;height:36px">${ico('dotsH', 16)}</div></div>
  </div>
  <div style="margin-top:24px">${toolbar('Rechercher un bloc', 'Nouveau bloc')}</div>
  <div class="sec-h" style="margin-top:24px">Blocs <span class="badge">7</span></div>
  <div style="position:relative;margin-top:14px">
    <div style="display:grid;grid-template-columns:repeat(4,262px);gap:16px;align-items:stretch">${b.join('')}</div>
    <div style="position:absolute;left:${262 + 16 + 262 - 216 - 8}px;top:${150 + 12 + 24 + 4}px">${quickMenu()}</div>
  </div>
</main>
` + foot;
}

// ====================================================================
// PLANCHE — MAIN DESIGN SYSTEM
// ====================================================================
function components() {
  const W = 4003.97, H = 2177.51;
  const sw = (n, v, bg, bd) => `<div class="sw"><div class="chip" style="background:${bg}${bd ? ';border-color:' + bd : ''}"></div><div class="n">${n}</div><div class="v">${v}</div></div>`;
  const st1 = (cls, txt, extra = '') => `<div class="col"><span class="${cls}">${txt}</span>${extra}</div>`;
  const state = (inner, cap, w = '') => `<div class="col"${w ? ` style="width:${w}px"` : ''}>${inner}<div class="cap">${cap}</div></div>`;

  const colors = `
<div class="block" style="left:80px;top:40px;width:880px"><div class="sheet-h1">Design system</div><div class="sheet-sub">Composants communs · Inter + JetBrains Mono · tokens --ag-* · sidebar : voir au centre</div></div>
<div class="block" style="left:80px;top:130px;width:880px"><div class="sh">Couleurs · compléments</div>
  <div class="swatches">
    ${sw('--ag-field', '#1F1F23', '#1F1F23')}${sw('--ag-field-border', '#2E2E33', '#2E2E33')}${sw('--ag-focus', '#52525B', '#52525B')}${sw('--ag-line', '#3F3F46', '#3F3F46')}
    ${sw('--ag-ok', '#4ADE80', '#4ADE80')}${sw('--ag-ok-bg', '#0D3320', '#0D3320')}${sw('--ag-warn', '#FBBF24', '#FBBF24')}${sw('--ag-warn-bg', '#33270B', '#33270B')}
    ${sw('--ag-danger', '#F87171', '#F87171')}${sw('--ag-danger-bg', '#2A1517', '#2A1517')}${sw('--ag-info', '#60A5FA', '#60A5FA')}${sw('--ag-info-bg', '#0F2742', '#0F2742')}
    ${sw('--ag-client', 'Lumen #7C83FD', '#7C83FD')}${sw('--ag-client-soft', '#26273B', '#26273B')}${sw('--ag-on-client', '#0E0E10', '#0E0E10', '#3F3F46')}${sw('--ag-image', '#484856', '#484856')}
  </div>
  <div class="cap" style="margin-top:14px">Couleurs client : Lumen #7C83FD · Orbe #F59E0B · Studio Kasa #10B981 · Maison Vega #EC4899</div>
  <div class="line" style="margin-top:10px">
    <div class="av s28" style="background:#7C83FD">L</div><div class="av s28" style="background:#F59E0B">O</div><div class="av s28" style="background:#10B981">S</div><div class="av s28" style="background:#EC4899">M</div>
  </div>
</div>
<div class="block" style="left:80px;top:520px;width:880px"><div class="sh">Typographie · pages</div>
  <div class="type">
    <div><span class="k">Inter 600 · 28/36</span><span style="font:600 28px/36px var(--ag-font);letter-spacing:-.01em">Lancement Aurora</span></div>
    <div><span class="k">Inter 600 · 15/22</span><span style="font:600 15px/22px var(--ag-font)">Projets</span></div>
    <div><span class="k">Inter 600 · 14/20</span><span style="font:600 14px/20px var(--ag-font)">Storyboard — Film principal</span></div>
    <div><span class="k">Inter 500 · 13/18</span><span style="font:500 13px/18px var(--ag-font)">Notifier le client</span></div>
    <div><span class="k">Inter 400 · 13/19</span><span style="font:400 13px/19px var(--ag-font);color:#A1A1AA">Première version du teaser pour les réseaux sociaux.</span></div>
    <div><span class="k">Inter 500 · 11 (tag)</span><span class="tag">Média versionné</span></div>
    <div><span class="k">JetBrains Mono 400 · 11</span><span style="font:400 11px/16px var(--ag-mono);color:#71717A">7 blocs · mis à jour il y a 1 h</span></div>
  </div>
</div>
<div class="block" style="left:80px;top:880px;width:880px"><div class="sh">Rayons &amp; espacements</div>
  <div class="line" style="gap:20px;align-items:flex-end">
    ${[5, 8, 9, 12, 14].map((r) => `<div class="col"><div class="rad" style="border-radius:${r}px"></div><div class="cap">${r}</div></div>`).join('')}
    <div style="width:30px"></div>
    ${[4, 8, 12, 16, 24, 32].map((s) => `<div class="col" style="align-items:flex-start"><div class="spc" style="width:${s}px"></div><div class="cap">${s}</div></div>`).join('')}
  </div>
  <div class="cap" style="margin-top:6px">rayons : badge 5 · bouton 8 · champ 9 · carte 12 · panneau 14 &nbsp;|&nbsp; espacements : 4 · 8 · 12 · 16 · 24 · 32</div>
</div>
<div class="block" style="left:80px;top:1060px;width:880px"><div class="sh">Icônes · types de bloc (bicolores)</div>
  <div style="display:flex;flex-wrap:wrap;gap:12px 8px">${[...BLOCK_TYPES, ['folder', 'Projet'], ['lock', 'Accès'], ['dash', 'Tableau de bord'], ['comments', 'Commentaires'], ['inbox', 'Fichiers reçus']].map(([t, l]) => `<div class="iconcell"><div class="ic">${ico(t, 24)}</div><div class="n">${l.length > 14 ? l.slice(0, 13) + '…' : l}</div></div>`).join('')}</div>
</div>
<div class="block" style="left:80px;top:1380px;width:880px"><div class="sh">Icônes · utilitaires (traits 1,5–1,6)</div>
  <div style="display:flex;flex-wrap:wrap;gap:12px 8px">${['search', 'filter', 'grid', 'list', 'plus', 'dotsV', 'dotsH', 'chevD', 'chevR', 'pen', 'copy', 'mail', 'trash', 'upload', 'ext', 'close', 'check', 'bubble', 'play', 'info', 'warn', 'clock', 'bell', 'image', 'drag', 'key'].map((t) => `<div class="iconcell"><div class="ic">${ico(t, 20)}</div><div class="n">${t}</div></div>`).join('')}</div>
</div>`;

  const buttons = `
<div class="block" style="left:1000px;top:130px;width:940px"><div class="sh">Boutons</div>
  <div class="line" style="gap:12px;flex-wrap:wrap">
    ${state(`<button class="btn primary"><span>${ico('plus', 16)}</span>Nouveau bloc</button>`, 'primaire · défaut')}
    ${state(`<button class="btn primary is-hover"><span>${ico('plus', 16)}</span>Nouveau bloc</button>`, 'primaire · survol')}
    ${state(`<button class="btn primary is-disabled">Nouveau bloc</button>`, 'primaire · désactivé')}
    ${state(`<button class="btn secondary"><span style="color:#A1A1AA">${ico('copy', 16)}</span>Copier le lien</button>`, 'secondaire')}
    ${state(`<button class="btn secondary is-hover"><span style="color:#A1A1AA">${ico('copy', 16)}</span>Copier le lien</button>`, 'secondaire · survol')}
  </div>
  <div class="line" style="gap:12px;margin-top:18px;flex-wrap:wrap">
    ${state(`<button class="btn ghost">Annuler</button>`, 'fantôme')}
    ${state(`<button class="btn ghost is-hover">Annuler</button>`, 'fantôme · survol')}
    ${state(`<button class="btn danger">Supprimer le client</button>`, 'danger')}
    ${state(`<button class="btn danger is-hover">Supprimer le client</button>`, 'danger · survol')}
    ${state(`<button class="btn secondary is-disabled">Enregistrer</button>`, 'désactivé')}
  </div>
  <div class="line" style="gap:12px;margin-top:18px;align-items:flex-end">
    ${state(`<button class="btn primary sm">Envoyer</button>`, 'petit 28')}
    ${state(`<button class="btn secondary sm">Modifier</button>`, 'petit 28')}
    ${state(`<div class="icon-btn">${ico('pen', 16)}</div>`, 'icône 32')}
    ${state(`<div class="icon-btn sm">${ico('copy', 16)}</div>`, 'icône 28')}
    ${state(`<div class="icon-btn plain">${ico('dotsH', 16)}</div>`, 'icône sans cadre')}
    ${state(`<div class="icon-btn is-hover">${ico('pen', 16)}</div>`, 'icône · survol')}
    ${state(`<div class="seg"><div class="s on">${ico('grid', 16)}</div><div class="s">${ico('list', 16)}</div></div>`, 'bascule grille / liste')}
  </div>
</div>`;

  const fields = `
<div class="block" style="left:1000px;top:430px;width:940px"><div class="sh">Champs de formulaire</div>
  <div class="line" style="gap:20px">
    <div class="col" style="width:290px"><div class="lbl-f">Nom du client *</div><div class="input"><span class="grow">Lumen</span></div><div class="cap">défaut</div></div>
    <div class="col" style="width:290px"><div class="lbl-f">Nom du client *</div><div class="input is-focus"><span class="grow">Lumen</span></div><div class="cap">focus · --ag-focus</div></div>
    <div class="col" style="width:290px"><div class="lbl-f">Slug</div><div class="input is-error"><span class="grow">lumen!</span></div><div class="help err">Caractères non autorisés.</div><div class="cap">erreur</div></div>
  </div>
  <div class="line" style="gap:20px;margin-top:18px">
    <div class="col" style="width:290px"><div class="lbl-f">URL du portail</div><div class="input is-disabled"><span class="grow">https://agency.local/client/lumen/</span></div><div class="help">Généré automatiquement.</div><div class="cap">désactivé</div></div>
    <div class="col" style="width:290px"><div class="lbl-f">Rechercher</div><div class="input search"><span style="color:#71717A">${ico('search', 16)}</span><span class="grow ph">Rechercher un projet</span><kbd>⌘K</kbd></div><div class="cap">recherche</div></div>
    <div class="col" style="width:290px"><div class="lbl-f">Statut</div><div class="input"><span class="grow">Actif</span><span style="color:#71717A">${ico('chevD', 16)}</span></div><div class="cap">liste déroulante</div></div>
  </div>
  <div class="line" style="gap:20px;margin-top:18px">
    <div class="col" style="width:290px"><div class="lbl-f">Couleur d’accent</div><div class="input"><div class="swatch"></div><span class="grow mono">#7C83FD</span></div><div class="cap">couleur</div></div>
    <div class="col" style="width:290px"><div class="lbl-f">Notes</div><div class="input ta"><span class="ph">Ajouter des notes…</span></div><div class="cap">zone de texte</div></div>
    <div class="col" style="width:290px"><div class="lbl-f">Fichier</div><div class="drop is-hover">${ico('upload', 22)}Glissez-déposez ou cliquez pour choisir</div><div class="cap">dépôt · survol</div></div>
  </div>
  <div class="line" style="gap:28px;margin-top:22px;align-items:center">
    <div class="opt"><div class="cb">${''}</div>Case</div>
    <div class="opt"><div class="cb on">${ico('check', 12)}</div>Cochée</div>
    <div class="opt"><div class="tg"><i></i></div>Désactivé</div>
    <div class="opt"><div class="tg on"><i></i></div>Notifier par e-mail</div>
    <div class="opt"><kbd>⌘K</kbd><kbd>esc</kbd><span style="color:#71717A;font:400 11px/16px var(--ag-mono)">touches</span></div>
  </div>
</div>`;

  const tags = `
<div class="block" style="left:1000px;top:1010px;width:940px"><div class="sh">Tags, pastilles, badges, avatars</div>
  <div class="line" style="gap:10px;flex-wrap:wrap;align-items:center">
    ${typeTag('video', 'Vidéo')}${typeTag('storyboard', 'Storyboard')}${typeTag('versioned', 'Média versionné')}${typeTag('script', 'Script')}${typeTag('link', 'Lien')}${typeTag('deposit', 'Dépôt de fichiers')}${typeTag('code', 'Code')}${typeTag('video', 'Vidéo', true)}
  </div>
  <div class="cap">tag de type de bloc · neutre ; variante client = type sélectionné</div>
  <div class="line" style="gap:10px;margin-top:16px;flex-wrap:wrap;align-items:center">
    <span class="tag outline">Branding</span><span class="tag outline">Vidéo</span><span class="tag outline">Lancement</span>
    <span class="pill ok">Actif</span><span class="pill off">Inactif</span><span class="pill arch">Archivé</span><span class="pill warn">À valider</span><span class="pill err">Refusé</span><span class="pill info">Nouveau</span>
  </div>
  <div class="cap">label de projet · statuts</div>
  <div class="line" style="gap:14px;margin-top:16px;flex-wrap:wrap;align-items:center">
    <span class="badge">3</span><span class="badge client">V3</span><span class="badge">inactif</span><span class="count">2 projets</span>
    <span class="chat">${ico('bubble', 14)}0</span><span class="chat">${ico('bubble', 14)}2</span><span class="chat unread"><i class="udot"></i>${ico('bubble', 14)}2</span>
    <div class="av">L</div><div class="av s28" style="background:#F59E0B">O</div><div class="av s32" style="background:#10B981">S</div><div class="av s40" style="background:#EC4899">M</div><div class="av s32 round">MJ</div><div class="av s28 ring" style="background:#7C83FD;margin-left:6px">L</div>
  </div>
  <div class="cap">badges mono · commentaires (non lus = pastille client) · avatars 20 / 28 / 32 / 40 · actif avec anneau</div>
</div>`;

  const cards = `
<div class="block" style="left:2760px;top:40px;width:1200px"><div class="sh">Carte projet (portail client)</div>
  <div class="line" style="gap:16px">
    ${state(projectCard({ title: 'Lancement Aurora', labels: ['Lancement', 'Vidéo'], blocks: '7 blocs', updated: 'il y a 2 h' }), 'défaut')}
    ${state(projectCard({ title: 'Identité visuelle 2027', labels: ['Branding'], blocks: '4 blocs', updated: 'il y a 3 j', state: 'hover' }), 'survol')}
    ${state(projectCard({ title: 'Film de marque', labels: ['Vidéo', 'Print'], blocks: '2 blocs', updated: 'il y a 1 sem.', state: 'selected' }), 'sélectionnée')}
    ${state(`<div class="card add" style="height:240px"><div class="icon-btn" style="width:36px;height:36px">${ico('plus', 18)}</div>Ajouter un projet</div>`, 'emplacement vide')}
  </div>
</div>
<div class="block" style="left:2760px;top:390px;width:1200px"><div class="sh">Carte bloc · aperçu = média du bloc</div>
  <div class="line" style="gap:16px">
    ${state(blockCard({ type: 'video', label: 'Vidéo', title: 'Teaser 30s', meta: 'Mis à jour il y a 1 h', comments: 2, unread: true }), 'vidéo')}
    ${state(blockCard({ type: 'storyboard', label: 'Storyboard', title: 'Storyboard — Film principal', meta: 'Mis à jour hier', comments: 5 }), 'storyboard')}
    ${state(blockCard({ type: 'versioned', label: 'Média versionné', title: 'Affiche 40×60', meta: 'Mis à jour il y a 3 j', comments: 0 }), 'média versionné')}
    ${state(blockCard({ type: 'script', label: 'Script', title: 'Script voix off', meta: 'Mis à jour il y a 4 h', comments: 1, unread: true }), 'script')}
  </div>
  <div class="line" style="gap:16px;margin-top:22px">
    ${state(blockCard({ type: 'link', label: 'Lien', title: 'Moodboard Figma', meta: 'Ajouté il y a 1 sem.', comments: 0 }), 'lien')}
    ${state(blockCard({ type: 'deposit', label: 'Dépôt de fichiers', title: 'Dépôt des rushs', meta: '3 fichiers reçus', comments: 0 }), 'dépôt de fichiers')}
    ${state(blockCard({ type: 'code', label: 'Code', title: 'Intégration du player', meta: 'Mis à jour il y a 2 j', comments: 3 }), 'code / intégration')}
    ${state(blockCard({ type: 'video', label: 'Vidéo', title: 'Teaser 30s', meta: 'Mis à jour il y a 1 h', comments: 2, unread: true, state: 'hover' }), 'survol · « ⋮ » actif')}
  </div>
</div>
<div class="block" style="left:2760px;top:1090px;width:1200px"><div class="sh">Cartes · client, panneau, vide</div>
  <div class="line" style="gap:16px;align-items:flex-start">
    ${state(clientCard(CLIENTS[0]), 'carte client (tableau de bord)')}
    ${state(`<div class="panel" style="width:290px"><div class="ph-h">Détails du client</div><div class="lbl-f">Nom du client *</div><div class="input"><span class="grow">Lumen</span></div><div class="help">Nom affiché dans le portail.</div></div>`, 'panneau de formulaire', 290)}
    ${state(`<div class="empty" style="width:290px"><div class="icon-btn" style="width:40px;height:40px">${ico('image', 20)}</div><div class="t">Aucun bloc</div><div class="d">Ajoutez une vidéo, un storyboard ou un lien pour démarrer.</div><button class="btn primary sm"><span>${ico('plus', 14)}</span>Nouveau bloc</button></div>`, 'état vide', 290)}
  </div>
</div>`;

  const menus = `
<div class="block" style="left:1980px;top:1010px;width:760px"><div class="sh">Menus &amp; infobulles</div>
  <div class="line" style="gap:20px">
    ${state(quickMenu(), 'actions rapides du bloc · « ⋮ »')}
    ${state(pickerMenu(), 'type de bloc · « + Nouveau bloc »')}
    <div class="col" style="gap:14px"><div class="tip">Lumen <span class="count">3 projets</span></div><div class="cap" style="margin-top:-8px">infobulle</div><div class="tip" style="margin-top:6px">Copié</div><div class="cap" style="margin-top:-8px">confirmation</div></div>
  </div>
</div>
<div class="block" style="left:1980px;top:1560px;width:760px"><div class="sh">Alertes &amp; toast</div>
  <div class="stack" style="width:520px">
    <div class="alert ok">${ico('check', 16)}Token d’accès actif<span class="r">Généré le 18 juin 2026</span></div>
    <div class="alert warn">${ico('warn', 16)}Ne partagez jamais ce lien publiquement</div>
    <div class="alert err">${ico('warn', 16)}Impossible d’envoyer le fichier</div>
    <div class="alert info">${ico('info', 16)}Le client sera notifié par e-mail</div>
    <div class="toast"><span style="color:#4ADE80">${ico('check', 18)}</span>Lien copié dans le presse-papiers<span class="a">Annuler</span></div>
  </div>
</div>`;

  const modal = `
<div class="block" style="left:80px;top:1760px;width:880px"><div class="sh">Modale</div>
  <div class="modal"><div class="mh">Nouveau projet<div class="icon-btn ghost x">${ico('close', 16)}</div></div><div class="mb"><div class="col"><div class="lbl-f">Nom du projet *</div><div class="input is-focus"><span class="grow">Lancement Aurora</span></div></div><div class="col"><div class="lbl-f">Labels</div><div class="input"><span class="tag outline">Lancement</span><span class="tag outline">Vidéo</span><span class="ph">Ajouter…</span></div></div></div><div class="mf"><button class="btn ghost">Annuler</button><button class="btn primary">Créer le projet</button></div></div>
</div>`;

  const patterns = `
<div class="block" style="left:1000px;top:1330px;width:940px"><div class="sh">Motifs de page</div>
  <div class="crumbs"><span>Lumen</span>${chev('r', 12)}<span>Lancement Aurora</span>${chev('r', 12)}<span class="cur">Dépôt des rushs</span></div>
  <div class="cap" style="margin-top:6px">fil d’Ariane</div>
  <div class="tabs" style="margin-top:16px;width:420px"><div class="tab on">Blocs<i></i></div><div class="tab">Commentaires <span class="badge">3</span></div><div class="tab">Réglages</div></div>
  <div class="cap" style="margin-top:6px">onglets</div>
  <div class="line" style="gap:24px;margin-top:18px">
    <div class="col" style="width:300px"><div class="cmt"><div class="av s28" style="background:#7C83FD">L</div><div class="col"><div class="who">Léa Martin <span class="when">il y a 12 min</span></div><div class="txt2">Super, on peut raccourcir la fin de 2 s ?</div></div></div><div class="cap">commentaire</div></div>
    <div class="col" style="width:300px"><div class="input ta" style="height:64px"><span class="ph">Ajouter un commentaire…</span></div><div style="margin-top:8px"><button class="btn primary sm">Envoyer</button></div><div class="cap">saisie de commentaire</div></div>
  </div>
  <div class="line" style="gap:24px;margin-top:18px;align-items:flex-end">
    <div class="col" style="width:300px"><div style="display:flex;justify-content:space-between;font:400 11px/16px var(--ag-mono);color:#71717A;margin-bottom:6px"><span>Progression</span><span>65 %</span></div><div class="track"><i style="width:65%"></i></div><div class="cap">barre de progression</div></div>
    <div class="col"><div class="pager"><div class="p">${ico('chevL', 14)}</div><div class="p on">1</div><div class="p">2</div><div class="p">3</div><div class="p">${ico('chevR', 14)}</div></div><div class="cap">pagination</div></div>
  </div>
</div>
<div class="block" style="left:1000px;top:1880px;width:1000px"><div class="sh">En-tête de page + barre d’outils</div>
  <div class="page-h"><div class="av s40" style="background:#7C83FD;width:48px;height:48px;border-radius:12px;font-size:18px">L</div><div><h1>Lumen</h1><div class="meta">3 projets · 13 blocs</div></div></div>
  <div style="margin-top:16px;width:820px">${toolbar('Rechercher un projet', 'Ajouter un projet')}</div>
</div>`;

  const clients = `
<div class="block" style="left:2760px;top:1470px;width:1200px"><div class="sh">Carte client · variantes</div>
  <div class="line" style="gap:16px;align-items:flex-start">
    ${state(clientCard(CLIENTS[0]), 'actif')}
    ${state(clientCard(CLIENTS[0], 'x') && clientCard({ ...CLIENTS[0], state: 'hover' }), 'survol')}
    ${state(clientCard(CLIENTS[3]), 'inactif')}
    ${state(clientCard({ n: 'Nouveau client', c: null, i: 'N', p: '0 projet' }), 'sans couleur d’accent')}
  </div>
</div>
<div class="block" style="left:2760px;top:1720px;width:520px"><div class="sh">Modale · nouveau client</div>${newClientModal()}</div>
<div class="block" style="left:3340px;top:1720px;width:620px"><div class="sh">Page publique · accès refusé</div>${accessCard()}</div>`;

  return head('MAIN DESIGN SYSTEM', W, H) + colors + buttons + fields + tags + patterns + cards + clients + menus + modal + foot;
}

fs.writeFileSync(path.join(OUT, 'components.html'), components());
fs.writeFileSync(path.join(OUT, 'page-portail.html'), pagePortail());
fs.writeFileSync(path.join(OUT, 'page-projet.html'), pageProjet());
fs.writeFileSync(path.join(OUT, 'page-dashboard.html'), pageDashboard());
fs.writeFileSync(path.join(OUT, 'page-nouveau-client.html'), pageNouveauClient());
fs.writeFileSync(path.join(OUT, 'page-acces-refuse.html'), pageAccesRefuse());
console.log('ok');
