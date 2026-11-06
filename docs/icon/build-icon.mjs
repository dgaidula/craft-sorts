import { glyph, fit } from './lib.mjs';
export const SW = '#f0eadc';
export const RED = '#dc0035';
// One grid: field 128 = 32 u, u = 4. margin 5u, cell 10u, gutter 2u.
export const U = 4, M = 5 * U, CELL = 10 * U, GUT = 2 * U, FIELD = 128;
const K = 0.64;                       // glyph ink box = K of the body, contain-fit, in every state
const WGHT = 800;
const r1 = n => Math.round(n * 10) / 10;
function face(ch, x, y, w, h, wght = WGHT) {
  const g = glyph(ch, wght);
  const s = Math.min(K * w / g.w, K * h / g.h);
  return fit(ch, { wght, s, cx: x + w / 2, cy: y + h / 2, p: 1 });
}
// each body is inset by EPS on every side: a half-pixel edge then snaps the same way on both sides of the field
const EPS = 0.02;
const body = (x, y, w, h) => `M${r2(x + EPS)} ${r2(y + EPS)}h${r2(w - 2 * EPS)}v${r2(h - 2 * EPS)}h-${r2(w - 2 * EPS)}z`;
const r2 = n => Math.round(n * 100) / 100;
export function states(twoSet = ['¶', '«']) {
  const c1 = M, c2 = M + CELL + GUT, big = 2 * CELL + GUT;
  const s4 = { bodies: [[c1, c1], [c2, c1], [c1, c2], [c2, c2]].map(([x, y]) => body(x, y, CELL, CELL)).join(''),
    faces: ['¶', '½', '×', '«'].map((ch, i) => face(ch, [c1, c2, c1, c2][i], [c1, c1, c2, c2][i], CELL, CELL)).join('') };
  const s2 = { bodies: [c1, c2].map(x => body(x, M, CELL, big)).join(''),
    faces: twoSet.map((ch, i) => face(ch, [c1, c2][i], M, CELL, big)).join('') };
  const s1 = { bodies: body(M, M, big, big), faces: face('¶', M, M, big, big) };
  return { s4, s2, s1 };
}
export function svg({ twoSet, bp2 = 39, bp1 = 31, css = true, faces = true, crisp = true } = {}) {
  const { s4, s2, s1 } = states(twoSet);
  const CR = crisp ? ' shape-rendering="crispEdges"' : '';
  const style = css ? `<style>
.sorts-icon{container:sorts-icon/inline-size;aspect-ratio:1}
@container sorts-icon (max-width:${bp2}px){.sorts-4{display:none}.sorts-2{display:inline}}
@container sorts-icon (max-width:${bp1}px){.sorts-2{display:none}.sorts-1{display:inline}}
</style>
` : '';
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 128 128" class="sorts-icon" role="img" aria-label="Sorts">
${style}<rect width="128" height="128" fill="${RED}"/>
<g class="sorts-4"><path${CR} fill="${SW}" d="${s4.bodies}"/>${faces ? `<path fill="${RED}" d="${s4.faces}"/>` : ''}</g>
<g class="sorts-2" display="none"><path${CR} fill="${SW}" d="${s2.bodies}"/>${faces ? `<path fill="${RED}" d="${s2.faces}"/>` : ''}</g>
<g class="sorts-1" display="none"><path${CR} fill="${SW}" d="${s1.bodies}"/>${faces ? `<path fill="${RED}" d="${s1.faces}"/>` : ''}</g>
</svg>
`;
}
