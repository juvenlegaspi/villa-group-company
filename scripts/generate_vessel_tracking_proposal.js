import pptxgen from 'pptxgenjs';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

const pptx = new pptxgen();
pptx.layout = 'LAYOUT_WIDE';
pptx.author = 'Villa Group Operations System';
pptx.company = 'Villa Shipping Lines, Inc.';
pptx.subject = 'Proposed activity-based vessel location monitoring with map visualization';
pptx.title = 'Vessel Voyage Map Tracking Proposal';
pptx.lang = 'en-PH';
pptx.theme = {
  headFontFace: 'Aptos Display',
  bodyFontFace: 'Aptos',
  lang: 'en-PH'
};
pptx.defineSlideMaster({
  title: 'VSL',
  background: { color: 'F5F8FC' },
  objects: [
    { rect: { x: 0, y: 0, w: 13.333, h: 0.08, fill: { color: '0B567C' }, line: { color: '0B567C' } } },
    { text: { text: 'VILLA SHIPPING LINES, INC.  |  VESSEL MANAGEMENT', options: { x: 0.55, y: 7.12, w: 7.4, h: 0.18, fontFace: 'Aptos', fontSize: 7.5, bold: true, color: '6B7C93', charSpacing: 1.2, margin: 0 } } },
    { text: { text: 'CONFIDENTIAL — FOR MANAGEMENT DISCUSSION', options: { x: 8.4, y: 7.12, w: 4.35, h: 0.18, fontFace: 'Aptos', fontSize: 7.5, bold: true, color: '8A99AA', align: 'right', margin: 0 } } }
  ],
  slideNumber: { x: 12.8, y: 7.12, color: '6B7C93', fontSize: 7.5 }
});

// Optional validation hook: generate only the first N slides when MAX_SLIDES is set.
const maxSlides = Number(process.env.MAX_SLIDES || 999);
let requestedSlides = 0;
const realAddSlide = pptx.addSlide.bind(pptx);
const noOpSlide = new Proxy({}, { get: () => () => noOpSlide });
pptx.addSlide = (...args) => (++requestedSlides <= maxSlides ? realAddSlide(...args) : noOpSlide);

const C = {
  navy: '102A56', blue: '174E82', ocean: '087FA7', cyan: '19B8C8', sky: 'DDF4F7',
  gold: 'C9952E', ink: '18243A', slate: '56677E', muted: '8291A5', line: 'D9E3EE',
  white: 'FFFFFF', bg: 'F5F8FC', green: '178A64', amber: 'D77A14', red: 'C74343', pale: 'EEF4FA'
};
const logo = path.join(__dirname, '..', 'public', 'logo.jpg');
const outDir = path.join(__dirname, '..', 'deliverables');
const outFile = process.env.PPTX_OUTPUT
  ? path.resolve(process.env.PPTX_OUTPUT)
  : path.join(outDir, 'Villa_Shipping_Vessel_Map_Tracking_Proposal.pptx');

if (process.env.MINIMAL_DECK) {
  const validationSlide = pptx.addSlide();
  validationSlide.addText('Validation', { x: 1, y: 1, w: 3, h: 1 });
  fs.mkdirSync(path.dirname(outFile), { recursive: true });
  await pptx.writeFile({ fileName: outFile });
  console.log(outFile);
  process.exit(0);
}

function addTitle(slide, title, subtitle) {
  slide.addText(title, { x: 0.62, y: 0.38, w: 8.9, h: 0.46, fontSize: 25, bold: true, color: C.navy, margin: 0, breakLine: false, fit: 'shrink' });
  if (subtitle) slide.addText(subtitle, { x: 0.64, y: 0.9, w: 11.7, h: 0.3, fontSize: 10.5, color: C.slate, margin: 0, fit: 'shrink' });
  slide.addShape(pptx.ShapeType.line, { x: 0.63, y: 1.28, w: 12.05, h: 0, line: { color: C.line, width: 1 } });
}

function addKicker(slide, text, x = 0.64, y = 0.18, w = 4) {
  slide.addText(text.toUpperCase(), { x, y, w, h: 0.16, fontSize: 7.5, bold: true, color: C.ocean, charSpacing: 1.6, margin: 0 });
}

function card(slide, x, y, w, h, opts = {}) {
  slide.addShape(pptx.ShapeType.roundRect, {
    x, y, w, h, rectRadius: 0.08,
    fill: { color: opts.fill || C.white, transparency: opts.transparency || 0 },
    line: { color: opts.line || C.line, width: opts.lineWidth || 0.9 },
    shadow: opts.shadow === false ? undefined : { type: 'outer', color: 'AAB7C6', opacity: 0.14, blur: 1.5, angle: 45, distance: 1 }
  });
}

function label(slide, text, x, y, w, color = C.ocean) {
  slide.addText(text.toUpperCase(), { x, y, w, h: 0.18, fontSize: 7.5, bold: true, color, charSpacing: 1.25, margin: 0, fit: 'shrink' });
}

function body(slide, text, x, y, w, h, opts = {}) {
  slide.addText(text, { x, y, w, h, fontSize: opts.size || 12, color: opts.color || C.ink, bold: opts.bold || false, breakLine: false, valign: opts.valign || 'mid', margin: opts.margin ?? 0.05, fit: 'shrink', bullet: opts.bullet });
}

function pill(slide, text, x, y, w, fill, color = C.white) {
  slide.addShape(pptx.ShapeType.roundRect, { x, y, w, h: 0.34, rectRadius: 0.14, fill: { color: fill }, line: { color: fill } });
  slide.addText(text, { x: x + 0.08, y: y + 0.07, w: w - 0.16, h: 0.16, fontSize: 8, bold: true, color, align: 'center', margin: 0, fit: 'shrink' });
}

function metric(slide, value, title, detail, x, y, w, tone) {
  card(slide, x, y, w, 1.18, { fill: C.white, line: C.line });
  slide.addShape(pptx.ShapeType.ellipse, { x: x + 0.2, y: y + 0.2, w: 0.5, h: 0.5, fill: { color: tone }, line: { color: tone } });
  slide.addText(value, { x: x + 0.2, y: y + 0.33, w: 0.5, h: 0.16, align: 'center', fontSize: 9, bold: true, color: C.white, margin: 0 });
  body(slide, title, x + 0.84, y + 0.17, w - 1.02, 0.3, { size: 12, bold: true });
  body(slide, detail, x + 0.84, y + 0.5, w - 1.02, 0.45, { size: 9.2, color: C.slate });
}

function step(slide, n, title, text, x, y, w, last = false) {
  slide.addShape(pptx.ShapeType.ellipse, { x, y, w: 0.5, h: 0.5, fill: { color: C.ocean }, line: { color: C.ocean } });
  slide.addText(String(n), { x, y: y + 0.13, w: 0.5, h: 0.15, fontSize: 9, bold: true, color: C.white, align: 'center', margin: 0 });
  body(slide, title, x + 0.68, y - 0.01, w - 0.68, 0.25, { size: 11.5, bold: true });
  body(slide, text, x + 0.68, y + 0.27, w - 0.68, 0.46, { size: 9.2, color: C.slate });
  if (!last) slide.addShape(pptx.ShapeType.line, { x: x + 0.25, y: y + 0.52, w: 0, h: 0.62, line: { color: '9BCAD8', width: 2, dash: 'dash' } });
}

function mapMock(slide, x, y, w, h) {
  slide.addShape(pptx.ShapeType.roundRect, { x, y, w, h, rectRadius: 0.08, fill: { color: 'D8EDF4' }, line: { color: 'A9D2DE', width: 1 } });
  // stylized land masses
  slide.addShape(pptx.ShapeType.ellipse, { x: x + 0.35, y: y + 0.5, w: 2.1, h: 2.6, rotate: 18, fill: { color: 'DDE5D2' }, line: { color: 'B7C8A7' } });
  slide.addShape(pptx.ShapeType.ellipse, { x: x + w - 2.1, y: y + 0.15, w: 1.55, h: 2.1, rotate: 210, fill: { color: 'DDE5D2' }, line: { color: 'B7C8A7' } });
  slide.addShape(pptx.ShapeType.line, { x: x + 1.6, y: y + 0.75, w: w - 3.05, h: h - 1.5, line: { color: C.ocean, width: 2.6, dash: 'dash', beginArrowType: 'none', endArrowType: 'triangle' } });
  const points = [
    { px: x + 1.55, py: y + 0.78, c: C.green, t: 'Origin' },
    { px: x + w * 0.52, py: y + h * 0.5, c: C.gold, t: 'MV Sample 01' },
    { px: x + w - 1.35, py: y + h - 0.78, c: C.red, t: 'Destination' }
  ];
  points.forEach((p, i) => {
    slide.addShape(pptx.ShapeType.ellipse, { x: p.px - 0.12, y: p.py - 0.12, w: 0.24, h: 0.24, fill: { color: p.c }, line: { color: C.white, width: 1.2 } });
    if (i === 1) {
      card(slide, p.px + 0.18, p.py - 0.48, 1.55, 0.62, { fill: C.white, line: C.line, shadow: false });
      body(slide, 'MV Sample 01', p.px + 0.28, p.py - 0.4, 1.3, 0.18, { size: 8.2, bold: true });
      body(slide, 'Underway • 10:42 AM', p.px + 0.28, p.py - 0.17, 1.3, 0.15, { size: 7.2, color: C.slate });
    }
  });
  body(slide, 'Illustrative interface — not a navigational chart', x + 0.18, y + h - 0.25, w - 0.36, 0.14, { size: 6.8, color: C.muted });
}

// 1 — Cover
{
  const slide = pptx.addSlide();
  const coverLevel = Number(process.env.COVER_LEVEL || 5);
  slide.background = { color: C.navy };
  slide.addShape(pptx.ShapeType.rect, { x: 0, y: 0, w: 13.333, h: 7.5, fill: { color: C.navy }, line: { color: C.navy } });
  if (coverLevel >= 2) {
    slide.addShape(pptx.ShapeType.ellipse, { x: 7.7, y: -1.8, w: 7.4, h: 8.8, rotate: 20, fill: { color: C.ocean, transparency: 12 }, line: { color: C.ocean, transparency: 100 } });
    slide.addShape(pptx.ShapeType.ellipse, { x: 8.5, y: -0.8, w: 5.8, h: 7.1, rotate: 18, fill: { color: C.cyan, transparency: 42 }, line: { color: C.cyan, transparency: 100 } });
  }
  if (coverLevel >= 3 && !process.env.NO_LOGO) slide.addImage({ path: logo, x: 0.62, y: 0.45, w: 1.55, h: 0.9 });
  if (coverLevel >= 4) {
    slide.addText('VESSEL MANAGEMENT ENHANCEMENT', { x: 0.68, y: 1.6, w: 4.8, h: 0.24, fontSize: 9, bold: true, color: '8ADDEA', charSpacing: 2, margin: 0 });
    slide.addText('Vessel Voyage\nMap Tracking', { x: 0.64, y: 2.03, w: 6.6, h: 1.55, fontSize: 35, bold: true, color: C.white, breakLine: false, margin: 0, fit: 'shrink' });
    slide.addText('A practical, phased proposal for map-based visibility using the existing Voyage and Activity workflow.', { x: 0.68, y: 3.9, w: 5.75, h: 0.72, fontSize: 16, color: 'D7EAF5', margin: 0, fit: 'shrink' });
    pill(slide, 'CEO PROPOSAL', 0.68, 5.08, 1.55, C.gold, C.navy);
    slide.addText('Prepared for Management Review  |  14 September 2026', { x: 0.68, y: 5.64, w: 5.2, h: 0.25, fontSize: 10, color: 'BFD5E6', margin: 0 });
  }
  if (coverLevel >= 5) {
    mapMock(slide, 7.2, 1.55, 5.25, 4.5);
    slide.addText('Villa Shipping Lines, Inc.', { x: 8.1, y: 6.3, w: 3.5, h: 0.3, fontSize: 12, bold: true, align: 'center', color: C.white, margin: 0 });
  }
}

// 2 — Executive summary
{
  const slide = pptx.addSlide('VSL');
  addKicker(slide, 'Executive Summary');
  addTitle(slide, 'One map for the latest known position of every active voyage', 'This proposal extends the current system; it does not replace the approved vessel and voyage workflow.');
  metric(slide, '01', 'Preserve the current process', 'Vessel → Voyage → Status → Activity → Completion remains unchanged.', 0.68, 1.62, 3.9, C.blue);
  metric(slide, '02', 'Capture coordinates', 'Origin, destination and activity updates gain latitude and longitude.', 4.73, 1.62, 3.9, C.ocean);
  metric(slide, '03', 'Display active vessels', 'The map shows only vessels with an open/current voyage.', 8.78, 1.62, 3.9, C.green);
  card(slide, 0.68, 3.15, 7.28, 2.62, { fill: C.white });
  label(slide, 'Management outcome', 0.96, 3.47, 2.4);
  body(slide, 'Faster operational awareness without introducing a complex tracking platform on day one.', 0.96, 3.78, 6.35, 0.52, { size: 19, bold: true, color: C.navy });
  body(slide, 'Managers can open the current-voyage map, identify each vessel’s latest reported position, and open the related voyage details from one screen.', 0.96, 4.55, 6.25, 0.74, { size: 11.5, color: C.slate });
  card(slide, 8.18, 3.15, 4.5, 2.62, { fill: 'EAF7F8', line: 'B5E2E7' });
  label(slide, 'Important boundary', 8.5, 3.47, 2.5, C.amber);
  body(slide, 'Phase 1 is activity-based location monitoring.', 8.5, 3.86, 3.65, 0.48, { size: 16, bold: true, color: C.navy });
  body(slide, 'It shows the latest position submitted by an authorized user. Continuous automatic tracking requires a future AIS/GPS integration.', 8.5, 4.5, 3.55, 0.78, { size: 11, color: C.slate });
}

// 3 — Current process
{
  const slide = pptx.addSlide('VSL');
  addKicker(slide, 'Current System');
  addTitle(slide, 'The enhancement connects to the process already in use', 'No new approval stage is proposed for the initial release.');
  const items = [
    ['1', 'Create Vessel', 'System Administrator registers the vessel and assigns a captain.'],
    ['2', 'Create Voyage', 'Authorized user records cargo, ports, crew and beginning Fuel ROB.'],
    ['3', 'Add Status', 'The current operational phase is opened.'],
    ['4', 'Add Activity', 'The user records work, port/location and operational remarks.'],
    ['5', 'Update Fuel', 'Consumption and bunkering update the remaining Fuel ROB.'],
    ['6', 'Complete Voyage', 'All statuses are completed before the voyage is closed.']
  ];
  items.forEach((it, i) => {
    const x = 0.66 + i * 2.08;
    card(slide, x, 1.72, 1.78, 2.25, { fill: i === 3 ? 'E8F7F9' : C.white, line: i === 3 ? '79CBD6' : C.line });
    slide.addShape(pptx.ShapeType.ellipse, { x: x + 0.57, y: 1.98, w: 0.62, h: 0.62, fill: { color: i === 3 ? C.ocean : C.navy }, line: { color: i === 3 ? C.ocean : C.navy } });
    body(slide, it[0], x + 0.57, 2.13, 0.62, 0.18, { size: 10, bold: true, color: C.white });
    slide.addText(it[1], { x: x + 0.15, y: 2.82, w: 1.48, h: 0.3, fontSize: 11, bold: true, color: C.navy, align: 'center', margin: 0, fit: 'shrink' });
    body(slide, it[2], x + 0.19, 3.19, 1.4, 0.57, { size: 8.2, color: C.slate, valign: 'top' });
    if (i < 5) slide.addShape(pptx.ShapeType.chevron, { x: x + 1.81, y: 2.65, w: 0.22, h: 0.36, fill: { color: 'A9BACB' }, line: { color: 'A9BACB' } });
  });
  card(slide, 0.68, 4.35, 12, 1.48, { fill: C.navy, line: C.navy });
  label(slide, 'Proposed insertion point', 0.98, 4.68, 2.6, '75DCE7');
  body(slide, 'Coordinates are captured during Create Voyage and each Add Activity update.', 0.98, 5.0, 7.55, 0.43, { size: 17, bold: true, color: C.white });
  body(slide, 'The operational workflow stays familiar to vessel personnel.', 9.05, 4.78, 3.05, 0.54, { size: 11.5, color: 'D5E6F2' });
}

// 4 — Opportunity
{
  const slide = pptx.addSlide('VSL');
  addKicker(slide, 'Why This Matters');
  addTitle(slide, 'Current location data exists—but it is not yet spatial', 'Port and location names are stored today; coordinates and map visualization are not.');
  const left = [
    ['Text-only location', 'Managers must open individual voyage records and interpret location names.'],
    ['No fleet map', 'There is no consolidated visual view of vessels with active voyages.'],
    ['Limited history', 'Activity locations are stored as text, not as auditable geographic points.']
  ];
  const right = [
    ['At-a-glance visibility', 'See the latest reported fleet position on one map.'],
    ['Faster follow-up', 'Open the current voyage and latest activity directly from a marker.'],
    ['Foundation for automation', 'Position history prepares the system for future AIS/GPS integration.']
  ];
  label(slide, 'Current limitation', 0.72, 1.56, 2.4, C.red);
  label(slide, 'Proposed capability', 6.9, 1.56, 2.4, C.green);
  left.forEach((v, i) => metric(slide, String(i + 1), v[0], v[1], 0.7, 1.92 + i * 1.38, 5.7, C.red));
  right.forEach((v, i) => metric(slide, '✓', v[0], v[1], 6.88, 1.92 + i * 1.38, 5.78, C.green));
}

// 5 — Solution
{
  const slide = pptx.addSlide('VSL');
  addKicker(slide, 'Proposed Solution');
  addTitle(slide, 'Three coordinated capabilities inside Vessel Management', 'The design is intentionally simple for vessel personnel and useful for management.');
  const cols = [
    { x: 0.7, c: C.blue, n: '01', t: 'Map-enabled voyage setup', bullets: ['Plot origin', 'Plot destination', 'Set initial current position', 'Retain existing port selections'] },
    { x: 4.55, c: C.ocean, n: '02', t: 'Position update in activity', bullets: ['Use device GPS', 'Search a place', 'Drop a map pin', 'Enter coordinates manually'] },
    { x: 8.4, c: C.green, n: '03', t: 'Current-voyage fleet map', bullets: ['Active voyages only', 'Latest position per vessel', 'Last update timestamp', 'One-click voyage details'] }
  ];
  cols.forEach(col => {
    card(slide, col.x, 1.6, 3.55, 4.45, { fill: C.white });
    pill(slide, col.n, col.x + 0.26, 1.9, 0.62, col.c);
    body(slide, col.t, col.x + 0.26, 2.48, 2.95, 0.64, { size: 17, bold: true, color: C.navy });
    col.bullets.forEach((b, i) => {
      slide.addShape(pptx.ShapeType.ellipse, { x: col.x + 0.3, y: 3.42 + i * 0.52, w: 0.17, h: 0.17, fill: { color: col.c }, line: { color: col.c } });
      body(slide, b, col.x + 0.58, 3.33 + i * 0.52, 2.58, 0.3, { size: 10.5, color: C.slate });
    });
    body(slide, iSafe(col.n), col.x + 0.28, 5.6, 2.8, 0.2, { size: 7.4, color: C.muted });
  });
  function iSafe(n) { return n === '01' ? 'Voyage origin data' : n === '02' ? 'Operational update point' : 'Management visibility'; }
}

// 6 — Capture
{
  const slide = pptx.addSlide('VSL');
  addKicker(slide, 'Position Capture');
  addTitle(slide, 'Flexible input supports both port and at-sea updates', 'Every saved position records its source, author and timestamp.');
  card(slide, 0.7, 1.55, 4.0, 4.65, { fill: C.white });
  label(slide, 'Recommended user interface', 0.98, 1.87, 2.7);
  ['Search port or location', 'Latitude', 'Longitude'].forEach((t, i) => {
    body(slide, t, 1.0, 2.28 + i * 0.72, 1.2, 0.18, { size: 8, bold: true, color: C.slate });
    slide.addShape(pptx.ShapeType.roundRect, { x: 1.0, y: 2.5 + i * 0.72, w: i === 0 ? 3.1 : 1.47, h: 0.38, rectRadius: 0.05, fill: { color: C.bg }, line: { color: C.line } });
    if (i > 0) slide.addShape(pptx.ShapeType.roundRect, { x: 2.63, y: 2.5 + i * 0.72, w: 1.47, h: 0.38, rectRadius: 0.05, fill: { color: C.bg }, line: { color: C.line } });
  });
  pill(slide, 'USE MY CURRENT LOCATION', 1.0, 4.8, 2.25, C.ocean);
  pill(slide, 'DROP PIN ON MAP', 3.35, 4.8, 1.25, C.navy);
  body(slide, 'Manual entry remains available when GPS or search is unavailable.', 1.0, 5.42, 3.15, 0.42, { size: 9.4, color: C.slate });
  const methods = [
    ['Device GPS', 'Fastest on mobile. Requires HTTPS and the user’s location permission.', C.green],
    ['Map search / pin', 'Best for a known place or a position selected visually.', C.ocean],
    ['Manual coordinates', 'Operational fallback for coordinates obtained from bridge instruments.', C.gold]
  ];
  methods.forEach((m, i) => metric(slide, String(i + 1), m[0], m[1], 5.05, 1.62 + i * 1.48, 7.55, m[2]));
  card(slide, 5.05, 5.97, 7.55, 0.48, { fill: 'FFF6E8', line: 'F0D6A7', shadow: false });
  body(slide, 'Validation: latitude −90 to 90 • longitude −180 to 180 • timestamp required', 5.3, 6.09, 7.0, 0.18, { size: 8.8, bold: true, color: C.amber });
}

// 7 — Map mockup
{
  const slide = pptx.addSlide('VSL');
  addKicker(slide, 'Illustrative Dashboard');
  addTitle(slide, 'Current Voyage Map — one marker per active vessel', 'Only the latest recorded position for an open voyage is displayed by default.');
  card(slide, 0.68, 1.5, 12.02, 4.95, { fill: C.white });
  slide.addShape(pptx.ShapeType.roundRect, { x: 0.9, y: 1.73, w: 2.72, h: 0.42, rectRadius: 0.05, fill: { color: C.bg }, line: { color: C.line } });
  body(slide, 'Search vessel or voyage…', 1.08, 1.84, 2.2, 0.15, { size: 8.5, color: C.muted });
  pill(slide, '5 ACTIVE VOYAGES', 3.82, 1.76, 1.45, C.green);
  pill(slide, 'LAST REFRESH 10:45', 5.42, 1.76, 1.58, C.blue);
  mapMock(slide, 0.9, 2.3, 8.15, 3.75);
  card(slide, 9.3, 2.3, 3.12, 3.75, { fill: C.bg, line: C.line, shadow: false });
  label(slide, 'Selected vessel', 9.58, 2.6, 1.8);
  body(slide, 'MV SAMPLE 01', 9.58, 2.93, 2.45, 0.34, { size: 17, bold: true, color: C.navy });
  pill(slide, 'UNDERWAY', 9.58, 3.39, 0.95, C.green);
  const details = [['Voyage', 'VL-00048'], ['Latest activity', 'Departed Cebu'], ['Position', '10.3157, 124.8421'], ['Updated', '10:42 AM • Capt. Dela Cruz']];
  details.forEach((d, i) => {
    body(slide, d[0], 9.58, 3.91 + i * 0.43, 0.9, 0.18, { size: 7.8, bold: true, color: C.muted });
    body(slide, d[1], 10.5, 3.87 + i * 0.43, 1.55, 0.25, { size: 8.5, bold: i < 2, color: C.ink });
  });
  pill(slide, 'OPEN VOYAGE DETAILS', 9.58, 5.56, 2.15, C.navy);
}

// 8 — Roles
{
  const slide = pptx.addSlide('VSL');
  addKicker(slide, 'Access and Accountability');
  addTitle(slide, 'Visibility follows the existing company, position and vessel assignment rules', 'Location features inherit the same controlled access used by Vessel Management and Voyages.');
  const roles = [
    ['System Administrator', 'All vessels', 'Configuration, oversight and troubleshooting', C.navy],
    ['Operations / Vessel Manager', 'All active voyages', 'View fleet map and record operational updates', C.blue],
    ['Technical Manager', 'All authorized vessels', 'View map and support operational coordination', C.ocean],
    ['Vessel Captain', 'Assigned vessel only', 'Create voyage and submit current position updates', C.green],
    ['Executive Viewer', 'Read-only fleet view', 'Management visibility without operational editing', C.gold]
  ];
  roles.forEach((r, i) => {
    const y = 1.55 + i * 0.9;
    card(slide, 0.72, y, 11.9, 0.7, { fill: i % 2 ? 'F8FAFD' : C.white, shadow: false });
    slide.addShape(pptx.ShapeType.rect, { x: 0.72, y, w: 0.08, h: 0.7, fill: { color: r[3] }, line: { color: r[3] } });
    body(slide, r[0], 1.02, y + 0.12, 2.65, 0.34, { size: 11.5, bold: true, color: C.navy });
    body(slide, r[1], 3.9, y + 0.12, 2.2, 0.34, { size: 10.5, bold: true, color: r[3] });
    body(slide, r[2], 6.28, y + 0.1, 5.65, 0.37, { size: 10, color: C.slate });
  });
  card(slide, 0.72, 6.14, 11.9, 0.42, { fill: 'FFF6E8', line: 'F0D6A7', shadow: false });
  body(slide, 'Every position update is attributed to a user and timestamp for auditability.', 1.02, 6.25, 11.2, 0.16, { size: 9.2, bold: true, color: C.amber });
}

// 9 — Data model
{
  const slide = pptx.addSlide('VSL');
  addKicker(slide, 'System Integration');
  addTitle(slide, 'The map is built on current records plus one position-history table', 'Existing operational data remains intact; new coordinate fields are nullable for legacy records.');
  const boxes = [
    { x: 0.74, t: 'PORTS', s: 'Master coordinates', c: C.blue },
    { x: 3.22, t: 'VOYAGE HEADER', s: 'Current / origin / destination', c: C.ocean },
    { x: 6.15, t: 'VOYAGE ACTIVITY', s: 'Operational update point', c: C.green },
    { x: 9.08, t: 'POSITION LOGS', s: 'Immutable geographic history', c: C.gold }
  ];
  boxes.forEach((b, i) => {
    card(slide, b.x, 2.0, i === 0 ? 2.1 : 2.5, 1.42, { fill: C.white });
    slide.addShape(pptx.ShapeType.rect, { x: b.x, y: 2.0, w: i === 0 ? 2.1 : 2.5, h: 0.1, fill: { color: b.c }, line: { color: b.c } });
    body(slide, b.t, b.x + 0.22, 2.35, i === 0 ? 1.66 : 2.06, 0.27, { size: 12.5, bold: true, color: C.navy });
    body(slide, b.s, b.x + 0.22, 2.76, i === 0 ? 1.66 : 2.06, 0.36, { size: 9, color: C.slate });
    if (i < 3) slide.addShape(pptx.ShapeType.chevron, { x: b.x + (i === 0 ? 2.2 : 2.6), y: 2.52, w: 0.35, h: 0.42, fill: { color: 'A8BACD' }, line: { color: 'A8BACD' } });
  });
  card(slide, 0.74, 4.02, 5.65, 1.62, { fill: C.white });
  label(slide, 'New coordinate data', 1.02, 4.34, 2.25);
  body(slide, 'Latitude • Longitude • Accuracy • Source • Recorded by • Recorded at', 1.02, 4.72, 4.84, 0.52, { size: 13, bold: true, color: C.navy });
  card(slide, 6.65, 4.02, 5.75, 1.62, { fill: 'EAF7F8', line: 'B5E2E7' });
  label(slide, 'Legacy data protection', 6.94, 4.34, 2.3, C.green);
  body(slide, 'No existing voyage, activity, fuel or vessel record needs to be deleted or rewritten.', 6.94, 4.69, 4.86, 0.57, { size: 13, bold: true, color: C.navy });
}

// 10 — Maturity
{
  const slide = pptx.addSlide('VSL');
  addKicker(slide, 'Tracking Maturity');
  addTitle(slide, 'Start practical, then automate when the business case is proven', 'The initial release establishes usable visibility and the data foundation for later upgrades.');
  const phases = [
    ['PHASE 1', 'Activity-based monitoring', 'Position saved during voyage creation and activity updates.', 'Human-confirmed updates', 'Recommended now', C.green],
    ['PHASE 2', 'Device-assisted updates', 'Browser GPS simplifies reporting from an authorized mobile device.', 'User permission required', 'Optional enhancement', C.ocean],
    ['PHASE 3', 'AIS / vessel GPS integration', 'External telemetry automatically updates positions at defined intervals.', 'Continuous data feed', 'Future investment', C.gold]
  ];
  phases.forEach((p, i) => {
    const x = 0.72 + i * 4.1;
    card(slide, x, 1.62, 3.75, 4.42, { fill: C.white, line: i === 0 ? '8ED6B9' : C.line, lineWidth: i === 0 ? 1.5 : 0.9 });
    pill(slide, p[0], x + 0.28, 1.95, 0.95, p[5]);
    body(slide, p[1], x + 0.28, 2.52, 3.05, 0.67, { size: 17, bold: true, color: C.navy });
    body(slide, p[2], x + 0.28, 3.38, 3.05, 0.75, { size: 10.5, color: C.slate });
    label(slide, 'Data behavior', x + 0.28, 4.38, 1.4);
    body(slide, p[3], x + 0.28, 4.7, 2.95, 0.3, { size: 10, bold: true });
    pill(slide, p[4].toUpperCase(), x + 0.28, 5.35, 1.75, i === 0 ? C.green : '8091A6');
  });
}

// 11 — Controls
{
  const slide = pptx.addSlide('VSL');
  addKicker(slide, 'Controls and Data Quality');
  addTitle(slide, 'Production controls keep the map trustworthy and secure', 'The map is an operational monitoring tool—not a certified navigation system.');
  const controls = [
    ['Access control', 'Company, position and vessel assignment rules determine visibility and editing.'],
    ['Coordinate validation', 'Reject invalid latitude/longitude and preserve the selected input source.'],
    ['Audit trail', 'Record user, timestamp, activity and previous/current position.'],
    ['Stale-location warning', 'Flag markers when no update has been recorded within the agreed threshold.'],
    ['Mobile fallback', 'Manual coordinates remain available if GPS or search is unavailable offshore.'],
    ['Provider protection', 'Restrict map API keys by domain and keep secrets outside the source code.']
  ];
  controls.forEach((c, i) => {
    const col = i % 2, row = Math.floor(i / 2);
    const x = 0.72 + col * 6.05, y = 1.55 + row * 1.42;
    card(slide, x, y, 5.72, 1.13, { fill: C.white });
    slide.addShape(pptx.ShapeType.ellipse, { x: x + 0.25, y: y + 0.28, w: 0.46, h: 0.46, fill: { color: col ? C.ocean : C.navy }, line: { color: col ? C.ocean : C.navy } });
    body(slide, '✓', x + 0.25, y + 0.39, 0.46, 0.15, { size: 9, bold: true, color: C.white });
    body(slide, c[0], x + 0.88, y + 0.2, 1.75, 0.25, { size: 11.3, bold: true, color: C.navy });
    body(slide, c[1], x + 2.55, y + 0.15, 2.75, 0.56, { size: 8.8, color: C.slate });
  });
  card(slide, 0.72, 5.9, 11.77, 0.48, { fill: 'FFF2F0', line: 'F0B9B3', shadow: false });
  body(slide, 'Disclaimer: displayed positions are “latest reported” unless and until an automatic AIS/GPS feed is integrated.', 0.98, 6.02, 11.2, 0.18, { size: 9.2, bold: true, color: C.red });
}

// 12 — Roadmap
{
  const slide = pptx.addSlide('VSL');
  addKicker(slide, 'Implementation Roadmap');
  addTitle(slide, 'A controlled rollout reduces operational and data risk', 'Each phase can be demonstrated and accepted before the next phase begins.');
  const roadmap = [
    ['1', 'Confirm requirements', 'Map provider, stale threshold, fields, permissions and final UI approval.'],
    ['2', 'Build data foundation', 'Coordinates, position logs, indexes, validation and production-safe migration.'],
    ['3', 'Enhance workflows', 'Map-enabled Create Voyage and Add Activity screens with mobile support.'],
    ['4', 'Deliver fleet map', 'Active-voyage markers, details panel, vessel filtering and access controls.'],
    ['5', 'Pilot and train', 'Test with selected vessels, validate procedures and prepare user guidance.'],
    ['6', 'Production rollout', 'Deploy, monitor location freshness and collect management feedback.']
  ];
  roadmap.forEach((r, i) => step(slide, r[0], r[1], r[2], i < 3 ? 0.86 : 6.95, 1.55 + (i % 3) * 1.55, 5.35, i % 3 === 2));
  pill(slide, 'GO / NO-GO CHECKPOINT AFTER EACH PHASE', 4.76, 6.34, 3.78, C.navy);
}

// 13 — Benefits/KPIs
{
  const slide = pptx.addSlide('VSL');
  addKicker(slide, 'Expected Value');
  addTitle(slide, 'Success is measured through visibility, adoption and data freshness', 'Baseline values should be captured during the pilot before final targets are approved.');
  const benefits = [
    ['Fleet visibility', 'Active voyages visible on one operational map', C.blue],
    ['Response time', 'Less time spent locating and contacting a vessel', C.ocean],
    ['Accountability', 'Every position linked to a user, activity and time', C.green],
    ['Data readiness', 'Structured history available for future analytics/AIS', C.gold]
  ];
  benefits.forEach((b, i) => metric(slide, String(i + 1), b[0], b[1], 0.72 + (i % 2) * 6.04, 1.55 + Math.floor(i / 2) * 1.42, 5.7, b[2]));
  card(slide, 0.72, 4.55, 11.74, 1.44, { fill: C.navy, line: C.navy });
  label(slide, 'Suggested pilot indicators', 1.02, 4.86, 2.4, '75DCE7');
  const kpis = ['% active voyages with coordinates', 'Average age of latest position', 'Mobile update success rate', 'Map-to-voyage click-through'];
  kpis.forEach((k, i) => {
    slide.addShape(pptx.ShapeType.ellipse, { x: 1.02 + i * 2.9, y: 5.3, w: 0.14, h: 0.14, fill: { color: C.cyan }, line: { color: C.cyan } });
    body(slide, k, 1.25 + i * 2.9, 5.18, 2.32, 0.38, { size: 9.2, bold: true, color: C.white });
  });
}

// 14 — Decision
{
  const slide = pptx.addSlide('VSL');
  addKicker(slide, 'Management Decision');
  addTitle(slide, 'Approval requested: proceed with Phase 1', 'Begin with latest-reported visibility and a controlled pilot, while preserving a path to automatic tracking.');
  card(slide, 0.72, 1.52, 7.12, 4.42, { fill: C.white });
  label(slide, 'Approval scope', 1.02, 1.88, 1.8);
  const asks = [
    'Approve activity-based vessel position monitoring as Phase 1.',
    'Approve a map provider evaluation and controlled API budget.',
    'Confirm Operations Managers and Captains as position reporters.',
    'Authorize a limited pilot using selected active vessels.',
    'Review pilot results before considering AIS/GPS integration.'
  ];
  asks.forEach((a, i) => {
    slide.addShape(pptx.ShapeType.ellipse, { x: 1.02, y: 2.38 + i * 0.61, w: 0.25, h: 0.25, fill: { color: C.green }, line: { color: C.green } });
    body(slide, '✓', 1.02, 2.44 + i * 0.61, 0.25, 0.12, { size: 7.5, bold: true, color: C.white });
    body(slide, a, 1.44, 2.29 + i * 0.61, 5.76, 0.39, { size: 11.2, color: C.ink });
  });
  card(slide, 8.12, 1.52, 4.55, 4.42, { fill: C.navy, line: C.navy });
  label(slide, 'Recommended decision', 8.47, 1.92, 2.25, '75DCE7');
  body(slide, 'PROCEED TO\nDETAILED DESIGN', 8.47, 2.48, 3.55, 1.12, { size: 25, bold: true, color: C.white });
  body(slide, 'Deliverables for the next review:', 8.47, 3.9, 3.45, 0.27, { size: 10, bold: true, color: 'BFD9E9' });
  body(slide, '• Final field list and permissions\n• Working map prototype\n• Production-safe migration plan\n• Pilot acceptance checklist', 8.48, 4.31, 3.35, 1.05, { size: 10.5, color: C.white, valign: 'top' });
  pill(slide, 'QUESTIONS & DISCUSSION', 4.85, 6.28, 3.0, C.gold, C.navy);
}

fs.mkdirSync(outDir, { recursive: true });
await pptx.writeFile({ fileName: outFile });
console.log(outFile);
