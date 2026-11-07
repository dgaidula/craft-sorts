// Glyph helper for build-icon.mjs: reads Jost (variable, OFL) with fontkit and emits SVG paths.
// fontkit is borrowed from the recastcinch checkout (~/sw/github-public/recastcinch/node_modules);
// craft-sorts has no node_modules of its own. Run `npm install` in recastcinch first, or point the
// createRequire path below at any package.json whose node_modules holds fontkit.
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
const require = createRequire(new URL('../../../recastcinch/package.json', import.meta.url));
const fontkit = require('fontkit');
const base = fontkit.openSync(fileURLToPath(new URL('./jost.ttf', import.meta.url)));
export function glyph(ch, wght = 600) {
  const f = base.getVariation({ wght });
  const g = f.glyphForCodePoint(ch.codePointAt(0));
  const bb = g.bbox; // font units, y up
  return { g, bb, w: bb.maxX - bb.minX, h: bb.maxY - bb.minY };
}
const r = (n, p = 0) => { const k = 10 ** p; return String(Math.round(n * k) / k).replace(/^-0$/, '0'); };
// emit a path fitted: ink bbox scaled by s and centred on (cx,cy)
export function fit(ch, { wght = 600, s, cx, cy, p = 0 }) {
  const { g, bb, w, h } = glyph(ch, wght);
  const mx = (bb.minX + bb.maxX) / 2, my = (bb.minY + bb.maxY) / 2;
  const X = x => r(cx + (x - mx) * s, p), Y = y => r(cy - (y - my) * s, p);
  let d = '';
  for (const c of g.path.commands) {
    const a = c.args;
    if (c.command === 'moveTo') d += `M${X(a[0])} ${Y(a[1])}`;
    else if (c.command === 'lineTo') d += `L${X(a[0])} ${Y(a[1])}`;
    else if (c.command === 'quadraticCurveTo') d += `Q${X(a[0])} ${Y(a[1])} ${X(a[2])} ${Y(a[3])}`;
    else if (c.command === 'bezierCurveTo') d += `C${X(a[0])} ${Y(a[1])} ${X(a[2])} ${Y(a[3])} ${X(a[4])} ${Y(a[5])}`;
    else if (c.command === 'closePath') d += 'Z';
  }
  return d;
}
