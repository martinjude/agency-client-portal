// ES3 — dessine DATA (produit par extract.js) sur un plan de travail, dans un sous-calque de PARENT_LAYER.
var ERRS = [];
function drawPage(doc, AB_INDEX, PARENT_LAYER, SUB_NAME) {
  var ab = doc.artboards[AB_INDEX].artboardRect; // [left, top, right, bottom]
  var L0 = ab[0], T0 = ab[1];
  var parent = doc.layers.getByName(PARENT_LAYER);
  var layer;
  try { layer = parent.layers.getByName(SUB_NAME); layer.locked = false; while (layer.pageItems.length) layer.pageItems[0].remove(); }
  catch (e) { layer = parent.layers.add(); layer.name = SUB_NAME; }
  for (var gi = doc.gradients.length - 1; gi >= 0; gi--) {
    if (doc.gradients[gi].name.indexOf('grad ' + SUB_NAME + ' ') === 0) { try { doc.gradients[gi].remove(); } catch (e) {} }
  }
  var FC = {};
  function rgb(c) { var k = new RGBColor(); k.red = c[0]; k.green = c[1]; k.blue = c[2]; return k; }
  function getFont(names) {
    for (var i = 0; i < names.length; i++) {
      if (FC[names[i]] === undefined) { try { FC[names[i]] = app.textFonts.getByName(names[i]); } catch (e) { FC[names[i]] = null; } }
      if (FC[names[i]]) return FC[names[i]];
    }
    return null;
  }
  var W = { 100: 'Thin', 200: 'ExtraLight', 300: 'Light', 400: 'Regular', 500: 'Medium', 600: 'SemiBold', 700: 'Bold', 800: 'ExtraBold', 900: 'Black' };
  function fontNames(f) {
    var wt = Math.round(f.wt / 100) * 100; if (wt < 100) wt = 100; if (wt > 900) wt = 900;
    var fam = f.fam.toLowerCase(), list = [];
    var std = null;
    if (fam.indexOf('poppins') >= 0) std = 'Poppins'; else if (fam.indexOf('jetbrains') >= 0) std = 'JetBrainsMono'; else if (fam.indexOf('inter') >= 0) std = 'Inter';
    if (std) {
      var w = W[wt];
      if (f.it) list.push(std + '-' + (w === 'Regular' ? '' : w) + 'Italic');
      list.push(std + '-' + w);
      list.push(std + '-Regular');
    } else if (/mono|menlo|courier|consol|code/.test(fam)) {
      list.push(wt >= 600 ? 'Menlo-Bold' : 'Menlo-Regular');
    } else {
      var h = wt >= 600 ? 'HelveticaNeue-Bold' : wt >= 500 ? 'HelveticaNeue-Medium' : wt <= 300 ? 'HelveticaNeue-Light' : 'HelveticaNeue';
      if (f.it) list.push(h === 'HelveticaNeue' ? 'HelveticaNeue-Italic' : h + 'Italic');
      list.push(h);
    }
    list.push('HelveticaNeue', 'ArialMT');
    return list;
  }
  function rect(it) {
    var x = it.x, y = it.y, w = it.w, h = it.h, r = it.r || 0;
    if (it.s) { var sw = it.sw; x += sw / 2; y += sw / 2; w -= sw; h -= sw; r = Math.max(0, r - sw / 2); }
    if (w <= 0 || h <= 0) return null;
    var p = r > 0.3 ? layer.pathItems.roundedRectangle(T0 - y, L0 + x, w, h, r, r) : layer.pathItems.rectangle(T0 - y, L0 + x, w, h);
    return p;
  }
  var n = 0;
  for (var i = 0; i < DATA.items.length; i++) {
    var it = DATA.items[i], item = null;
    try {
      if (it.t === 'r') {
        if (it.g) {
          // Rectangle tourné pour que le dégradé suive la hauteur (approximation à 90° près)
          var ang = Math.round(it.g.ang / 90) * 90 % 360; if (ang < 0) ang += 360;
          var swap = ang === 0 || ang === 180;
          var cx = it.x + it.w / 2, cy = it.y + it.h / 2;
          var gw = swap ? it.h : it.w, gh = swap ? it.w : it.h;
          item = layer.pathItems.rectangle(T0 - (cy - gh / 2), L0 + (cx - gw / 2), gw, gh);
          var g = doc.gradients.add(); g.type = GradientType.LINEAR; g.name = 'grad ' + SUB_NAME + ' ' + i;
          var st = it.g.stops;
          while (g.gradientStops.length < st.length) g.gradientStops.add();
          for (var k = 0; k < st.length; k++) {
            var gs = g.gradientStops[k]; gs.rampPoint = Math.max(0, Math.min(100, st[k].p)); gs.midPoint = 50;
            gs.color = rgb(st[k].c); gs.opacity = st[k].c[3] * 100;
          }
          var gc = new GradientColor(); gc.gradient = g;
          item.filled = true; item.fillColor = gc; item.stroked = false;
          var rot = 90 - ang; if (rot !== 0) item.rotate(rot, true, true, true, true, Transformation.CENTER);
          item.opacity = it.o * 100;
        } else {
          item = rect(it); if (!item) continue;
          if (it.f) { item.filled = true; item.fillColor = rgb(it.f); } else item.filled = false;
          if (it.s) { item.stroked = true; item.strokeColor = rgb(it.s); item.strokeWidth = it.sw; } else item.stroked = false;
          var a = it.s && !it.f ? it.s[3] : (it.f ? it.f[3] : 1);
          item.opacity = a * it.o * 100;
        }
        item.name = it.n;
      } else if (it.t === 't') {
        item = layer.textFrames.pointText([L0 + it.x, T0 - it.y]);
        item.contents = it.s;
        var ca = item.textRange.characterAttributes;
        ca.size = it.sz;
        var f = getFont(fontNames(it.font)); if (f) ca.textFont = f;
        ca.fillColor = rgb(it.c); ca.tracking = it.tr;
        item.opacity = it.c[3] * it.o * 100;
      } else if (it.t === 'svg') {
        item = layer.groupItems.createFromFile(new File(it.file));
        var bb = null;
        for (var q = 0; q < item.pageItems.length; q++) if (item.pageItems[q].name === 'bbox') { bb = item.pageItems[q]; break; }
        var ref = bb ? bb.geometricBounds : item.geometricBounds;
        var rw = ref[2] - ref[0], rh = ref[1] - ref[3];
        if (rw > 0 && rh > 0 && (Math.abs(rw - it.w) > 0.5 || Math.abs(rh - it.h) > 0.5)) {
          item.resize(it.w / rw * 100, it.h / rh * 100, true, true, true, true, 100, Transformation.TOPLEFT);
          ref = bb ? bb.geometricBounds : item.geometricBounds;
        }
        item.translate(L0 + it.x - ref[0], T0 - it.y - ref[1]);
        if (bb) bb.remove();
        item.name = it.n; item.opacity = it.o * 100;
      }
      n++;
    } catch (e) { ERRS.push(i + ' ' + it.t + ' ' + (it.file || it.n) + ': ' + e); }
  }
  return n + '/' + DATA.items.length;
}
