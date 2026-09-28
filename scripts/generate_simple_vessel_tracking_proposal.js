import pptxgen from 'pptxgenjs';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const pptx = new pptxgen();
pptx.layout = 'LAYOUT_WIDE';
pptx.author = 'Villa Group Operations System';
pptx.company = 'Villa Shipping Lines, Inc.';
pptx.title = 'Simple Vessel Map Tracking Proposal';
pptx.subject = 'Map-enabled voyage location monitoring proposal';
pptx.lang = 'en-PH';
pptx.theme = { headFontFace: 'Aptos Display', bodyFontFace: 'Aptos', lang: 'en-PH' };

const C = {
  navy: '102F5E', blue: '126E9B', cyan: '12AFC2', white: 'FFFFFF', bg: 'F4F7FB',
  ink: '17243A', slate: '596A80', line: 'D8E2EC', green: '138B65', red: 'C83F45',
  gold: 'D19A2A', pale: 'E9F4F8'
};
const mapImage = path.join(__dirname, '..', 'resources', 'presentation', 'central-philippines-map.png');
const logo = path.join(__dirname, '..', 'public', 'logo.jpg');
const outDir = path.join(__dirname, '..', 'deliverables');
const outFile = path.join(outDir, 'Villa_Shipping_Simple_Vessel_Map_Tracking_Proposal.pptx');

pptx.defineSlideMaster({
  title: 'SIMPLE',
  background: { color: C.bg },
  objects: [
    { rect: { x: 0, y: 0, w: 13.333, h: 0.07, fill: { color: C.blue }, line: { color: C.blue } } },
    { text: { text: 'VILLA SHIPPING LINES, INC.  |  VESSEL MAP TRACKING PROPOSAL', options: { x: 0.55, y: 7.12, w: 8.2, h: 0.16, fontSize: 7.2, bold: true, color: '71839A', charSpacing: 1.1, margin: 0 } } }
  ],
  slideNumber: { x: 12.55, y: 7.1, w: 0.25, h: 0.18, fontSize: 7.5, color: '71839A', align: 'right' }
});

function title(slide, heading, sub) {
  slide.addText(heading, { x: 0.68, y: 0.4, w: 11.8, h: 0.5, fontSize: 26, bold: true, color: C.navy, margin: 0, fit: 'shrink' });
  if (sub) slide.addText(sub, { x: 0.7, y: 0.96, w: 11.6, h: 0.28, fontSize: 11, color: C.slate, margin: 0, fit: 'shrink' });
  slide.addShape(pptx.ShapeType.line, { x: 0.68, y: 1.32, w: 12, h: 0, line: { color: C.line, width: 1 } });
}

function card(slide, x, y, w, h, fill = C.white, line = C.line) {
  slide.addShape(pptx.ShapeType.roundRect, { x, y, w, h, rectRadius: 0.08, fill: { color: fill }, line: { color: line, width: 0.9 }, shadow: { type: 'outer', color: '98A7B8', opacity: 0.12, blur: 1.2, angle: 45, distance: 1 } });
}

function text(slide, value, x, y, w, h, opts = {}) {
  slide.addText(value, { x, y, w, h, fontSize: opts.size || 12, bold: opts.bold || false, color: opts.color || C.ink, align: opts.align || 'left', valign: opts.valign || 'mid', margin: opts.margin ?? 0.04, fit: 'shrink' });
}

function pill(slide, value, x, y, w, fill, color = C.white) {
  slide.addShape(pptx.ShapeType.roundRect, { x, y, w, h: 0.36, rectRadius: 0.15, fill: { color: fill }, line: { color: fill } });
  text(slide, value, x + 0.08, y + 0.08, w - 0.16, 0.15, { size: 8, bold: true, color, align: 'center', margin: 0 });
}

function numberStep(slide, n, heading, description, x, y, w, color = C.blue) {
  card(slide, x, y, w, 1.28);
  slide.addShape(pptx.ShapeType.ellipse, { x: x + 0.22, y: y + 0.3, w: 0.57, h: 0.57, fill: { color }, line: { color } });
  text(slide, String(n), x + 0.22, y + 0.43, 0.57, 0.17, { size: 10, bold: true, color: C.white, align: 'center', margin: 0 });
  text(slide, heading, x + 0.96, y + 0.2, w - 1.18, 0.32, { size: 13, bold: true, color: C.navy });
  text(slide, description, x + 0.96, y + 0.54, w - 1.18, 0.49, { size: 9.5, color: C.slate, valign: 'top' });
}

function mapPanel(slide, x, y, w, h, details = true) {
  slide.addImage({ path: mapImage, x, y, w, h });
  slide.addShape(pptx.ShapeType.rect, { x, y, w, h, fill: { color: '001C38', transparency: 78 }, line: { color: '88C9D7', width: 1 } });

  const origin = { x: x + w * 0.18, y: y + h * 0.24 };
  const current = { x: x + w * 0.51, y: y + h * 0.52 };
  const destination = { x: x + w * 0.83, y: y + h * 0.79 };
  slide.addShape(pptx.ShapeType.line, { x: origin.x, y: origin.y, w: current.x - origin.x, h: current.y - origin.y, line: { color: C.cyan, width: 3, dash: 'dash' } });
  slide.addShape(pptx.ShapeType.line, { x: current.x, y: current.y, w: destination.x - current.x, h: destination.y - current.y, line: { color: C.cyan, width: 3, dash: 'dash', endArrowType: 'triangle' } });

  const markers = [
    [origin, C.green, 'PORT ORIGIN', 'Cebu Port'],
    [current, C.gold, 'CURRENT VESSEL LOCATION', 'Updated from latest activity'],
    [destination, C.red, 'PORT DESTINATION', 'Destination Port']
  ];
  markers.forEach(([p, color, label, detail], index) => {
    slide.addShape(pptx.ShapeType.ellipse, { x: p.x - 0.15, y: p.y - 0.15, w: 0.3, h: 0.3, fill: { color }, line: { color: C.white, width: 1.5 } });
    if (details) {
      const bx = index === 2 ? p.x - 1.85 : p.x + 0.18;
      const by = index === 1 ? p.y - 0.86 : p.y - 0.48;
      card(slide, bx, by, 1.7, 0.65, C.white, C.line);
      text(slide, label, bx + 0.12, by + 0.1, 1.46, 0.18, { size: 7.2, bold: true, color });
      text(slide, detail, bx + 0.12, by + 0.33, 1.46, 0.17, { size: 7.2, color: C.slate });
    }
  });
}

// Slide 1
{
  const slide = pptx.addSlide();
  slide.addImage({ path: mapImage, x: 0, y: 0, w: 13.333, h: 7.5 });
  slide.addShape(pptx.ShapeType.rect, { x: 0, y: 0, w: 13.333, h: 7.5, fill: { color: C.navy, transparency: 18 }, line: { color: C.navy, transparency: 100 } });
  slide.addShape(pptx.ShapeType.rect, { x: 0, y: 0, w: 6.4, h: 7.5, fill: { color: C.navy, transparency: 4 }, line: { color: C.navy, transparency: 100 } });
  slide.addImage({ path: logo, x: 0.7, y: 0.48, w: 1.5, h: 0.85 });
  text(slide, 'SIMPLE CEO PROPOSAL', 0.76, 1.7, 3.2, 0.25, { size: 9, bold: true, color: '8EE7ED' });
  text(slide, 'Vessel Location\nTracking on a Map', 0.72, 2.12, 5.35, 1.35, { size: 34, bold: true, color: C.white });
  text(slide, 'Use the existing Voyage and Activity process to show where each active vessel was last reported.', 0.76, 3.88, 4.9, 0.78, { size: 16, color: 'E5F1F7' });
  pill(slide, 'PORT ORIGIN', 7.35, 2.15, 1.35, C.green);
  pill(slide, 'CURRENT LOCATION', 9.0, 3.45, 1.65, C.gold, C.navy);
  pill(slide, 'PORT DESTINATION', 10.65, 4.82, 1.75, C.red);
  slide.addShape(pptx.ShapeType.line, { x: 8.04, y: 2.58, w: 1.8, h: 1.08, line: { color: C.white, width: 3, dash: 'dash' } });
  slide.addShape(pptx.ShapeType.line, { x: 9.84, y: 3.66, w: 1.75, h: 1.36, line: { color: C.white, width: 3, dash: 'dash', endArrowType: 'triangle' } });
  text(slide, 'Prepared for Management Review', 0.76, 6.45, 3.5, 0.25, { size: 10, color: 'CFE3EE' });
}

// Slide 2
{
  const slide = pptx.addSlide('SIMPLE');
  title(slide, 'What the system does today', 'The existing process already records the ports, voyage and activities.');
  numberStep(slide, 1, 'Create Voyage', 'Enter voyage number, cargo, crew, Port Origin, Port Destination and Current Location.', 0.75, 1.75, 5.75, C.blue);
  numberStep(slide, 2, 'Add Voyage Status', 'Open the current operational phase, such as Loading, Sailing or Unloading.', 6.82, 1.75, 5.75, C.blue);
  numberStep(slide, 3, 'Add Activity', 'Record the vessel activity and select its current port or location.', 0.75, 3.37, 5.75, C.ocean);
  numberStep(slide, 4, 'Complete Voyage', 'Close all activities and statuses, then complete the voyage.', 6.82, 3.37, 5.75, C.green);
  card(slide, 0.75, 5.22, 11.82, 0.76, C.navy, C.navy);
  text(slide, 'PROPOSED CHANGE:', 1.08, 5.44, 1.65, 0.22, { size: 9.5, bold: true, color: '8EE7ED' });
  text(slide, 'Add a map and coordinates to the same Create Voyage and Add Activity screens.', 2.78, 5.36, 8.95, 0.36, { size: 15, bold: true, color: C.white });
}

// Slide 3
{
  const slide = pptx.addSlide('SIMPLE');
  title(slide, 'During Create Voyage: select three locations on the map', 'Users can search, click the map, use device location, or enter latitude and longitude.');
  card(slide, 0.72, 1.55, 4.25, 4.95);
  text(slide, 'CREATE VOYAGE', 1.02, 1.86, 2.2, 0.24, { size: 10, bold: true, color: C.blue });
  const fields = [
    ['Port Origin', 'Search or select origin port', C.green],
    ['Port Destination', 'Search or select destination port', C.red],
    ['Current Location', 'Use GPS, search or drop a map pin', C.gold]
  ];
  fields.forEach((f, i) => {
    const y = 2.28 + i * 1.16;
    text(slide, f[0], 1.02, y, 2.1, 0.2, { size: 9, bold: true, color: C.navy });
    slide.addShape(pptx.ShapeType.roundRect, { x: 1.02, y: y + 0.27, w: 3.62, h: 0.46, rectRadius: 0.05, fill: { color: C.bg }, line: { color: f[2], width: 1.2 } });
    text(slide, f[1], 1.22, y + 0.39, 3.05, 0.16, { size: 8.3, color: C.slate });
    slide.addShape(pptx.ShapeType.ellipse, { x: 4.25, y: y + 0.38, w: 0.16, h: 0.16, fill: { color: f[2] }, line: { color: f[2] } });
  });
  pill(slide, 'SAVE VOYAGE & MAP POINTS', 1.02, 5.86, 2.55, C.blue);
  mapPanel(slide, 5.25, 1.55, 7.38, 4.95, true);
}

// Slide 4
{
  const slide = pptx.addSlide('SIMPLE');
  title(slide, 'How the vessel marker moves', 'The latest location saved from an activity becomes the vessel’s current position on the map.');
  const steps = [
    ['1', 'Voyage starts', 'Origin, destination and initial location are saved.', C.green],
    ['2', 'User adds an activity', 'Example: Departed Cebu Port or Arrived at anchorage.', C.blue],
    ['3', 'Current location is updated', 'Use GPS, search, map pin or manual coordinates.', C.gold],
    ['4', 'Map marker moves', 'Managers see the latest reported vessel location.', C.red]
  ];
  steps.forEach((s, i) => {
    const x = 0.68 + i * 3.16;
    card(slide, x, 1.75, 2.82, 3.6);
    slide.addShape(pptx.ShapeType.ellipse, { x: x + 0.96, y: 2.05, w: 0.88, h: 0.88, fill: { color: s[3] }, line: { color: s[3] } });
    text(slide, s[0], x + 0.96, 2.27, 0.88, 0.24, { size: 14, bold: true, color: C.white, align: 'center' });
    text(slide, s[1], x + 0.26, 3.25, 2.3, 0.39, { size: 15, bold: true, color: C.navy, align: 'center' });
    text(slide, s[2], x + 0.32, 3.83, 2.18, 0.78, { size: 10, color: C.slate, align: 'center', valign: 'top' });
    if (i < 3) slide.addShape(pptx.ShapeType.chevron, { x: x + 2.88, y: 3.05, w: 0.23, h: 0.4, fill: { color: '9FB2C4' }, line: { color: '9FB2C4' } });
  });
  card(slide, 2.2, 5.74, 8.95, 0.55, C.pale, 'B8DEE6');
  text(slide, 'Every update keeps the user, date, time, voyage and activity for history and accountability.', 2.48, 5.89, 8.38, 0.2, { size: 10, bold: true, color: C.blue, align: 'center' });
}

// Slide 5
{
  const slide = pptx.addSlide('SIMPLE');
  title(slide, 'What the Current Voyage Map will look like', 'Each marker represents one vessel with an active voyage.');
  mapPanel(slide, 0.72, 1.5, 9.15, 5.02, true);
  card(slide, 10.1, 1.5, 2.52, 5.02, C.white, C.line);
  text(slide, 'SELECTED VESSEL', 10.37, 1.84, 1.9, 0.22, { size: 8.5, bold: true, color: C.blue });
  text(slide, 'MV SAMPLE 01', 10.37, 2.26, 1.92, 0.42, { size: 17, bold: true, color: C.navy });
  pill(slide, 'ACTIVE VOYAGE', 10.37, 2.86, 1.35, C.green);
  const rows = [['Voyage', 'VL-00048'], ['Status', 'Sailing'], ['Origin', 'Cebu Port'], ['Destination', 'Destination Port'], ['Last updated', 'Today, 10:42 AM']];
  rows.forEach((r, i) => {
    text(slide, r[0], 10.37, 3.47 + i * 0.45, 0.82, 0.18, { size: 7.8, bold: true, color: '8291A5' });
    text(slide, r[1], 11.18, 3.43 + i * 0.45, 1.03, 0.25, { size: 8.3, bold: i < 2, color: C.ink });
  });
  pill(slide, 'VIEW VOYAGE DETAILS', 10.37, 5.91, 1.88, C.navy);
}

// Slide 6
{
  const slide = pptx.addSlide('SIMPLE');
  title(slide, 'Who can use and view it', 'The proposed map follows the current system permissions and vessel assignments.');
  const roles = [
    ['System Administrator', 'View all vessels and manage the feature', C.navy],
    ['Operations / Vessel Manager', 'View all active vessels and update locations', C.blue],
    ['Technical Manager', 'View authorized vessels for coordination', C.ocean],
    ['Vessel Captain', 'View and update the assigned vessel only', C.green],
    ['Executive Viewer', 'Read-only management map', C.gold]
  ];
  roles.forEach((r, i) => {
    const y = 1.57 + i * 0.95;
    card(slide, 0.8, y, 11.7, 0.72, i % 2 ? 'F8FAFC' : C.white, C.line);
    slide.addShape(pptx.ShapeType.ellipse, { x: 1.05, y: y + 0.17, w: 0.38, h: 0.38, fill: { color: r[2] }, line: { color: r[2] } });
    text(slide, String(i + 1), 1.05, y + 0.26, 0.38, 0.13, { size: 7.5, bold: true, color: C.white, align: 'center' });
    text(slide, r[0], 1.68, y + 0.13, 3.25, 0.3, { size: 13, bold: true, color: C.navy });
    text(slide, r[1], 5.05, y + 0.12, 6.85, 0.32, { size: 11, color: C.slate });
  });
}

// Slide 7
{
  const slide = pptx.addSlide('SIMPLE');
  title(slide, 'What “tracking” means in the first version', 'Management should clearly understand the difference between reported tracking and automatic tracking.');
  card(slide, 0.75, 1.62, 5.72, 4.65, 'EBF8F3', 'A8DBC7');
  pill(slide, 'PHASE 1 — PROPOSED NOW', 1.08, 1.96, 2.1, C.green);
  text(slide, 'Latest Reported Location', 1.08, 2.55, 4.55, 0.5, { size: 23, bold: true, color: C.navy });
  text(slide, 'The vessel marker updates when an authorized user saves the current location during voyage creation or an activity update.', 1.08, 3.25, 4.65, 1.05, { size: 13, color: C.slate, valign: 'top' });
  text(slide, '✓ Practical first release\n✓ Uses the existing workflow\n✓ Keeps position history\n✓ Lower implementation complexity', 1.08, 4.58, 4.55, 1.05, { size: 11.5, bold: true, color: C.green, valign: 'top' });
  card(slide, 6.82, 1.62, 5.72, 4.65, 'FFF6E8', 'E9CE9C');
  pill(slide, 'FUTURE OPTION', 7.15, 1.96, 1.35, C.gold, C.navy);
  text(slide, 'Automatic Live Tracking', 7.15, 2.55, 4.55, 0.5, { size: 23, bold: true, color: C.navy });
  text(slide, 'Continuous movement requires integration with an AIS provider or a dedicated GPS device installed on the vessel.', 7.15, 3.25, 4.65, 1.05, { size: 13, color: C.slate, valign: 'top' });
  text(slide, '• Automatic location feed\n• Additional provider/device cost\n• Connectivity and integration required\n• Can be added after Phase 1', 7.15, 4.58, 4.55, 1.05, { size: 11.5, bold: true, color: 'A96E13', valign: 'top' });
}

// Slide 8
{
  const slide = pptx.addSlide('SIMPLE');
  title(slide, 'Management approval requested', 'Proceed with the simple map-enabled design before considering automatic AIS/GPS tracking.');
  card(slide, 0.78, 1.65, 7.35, 4.55, C.white, C.line);
  text(slide, 'PROPOSED PHASE 1', 1.12, 2.02, 2.3, 0.23, { size: 9, bold: true, color: C.blue });
  const asks = [
    'Add a map to Create Voyage.',
    'Plot Port Origin, Port Destination and Current Location.',
    'Update the vessel marker whenever a new activity location is saved.',
    'Show only vessels with an active/current voyage.',
    'Pilot the feature before investing in automatic AIS/GPS tracking.'
  ];
  asks.forEach((a, i) => {
    slide.addShape(pptx.ShapeType.ellipse, { x: 1.12, y: 2.53 + i * 0.63, w: 0.28, h: 0.28, fill: { color: C.green }, line: { color: C.green } });
    text(slide, '✓', 1.12, 2.6 + i * 0.63, 0.28, 0.12, { size: 7.5, bold: true, color: C.white, align: 'center' });
    text(slide, a, 1.62, 2.43 + i * 0.63, 5.9, 0.42, { size: 12, color: C.ink });
  });
  card(slide, 8.45, 1.65, 4.08, 4.55, C.navy, C.navy);
  text(slide, 'RECOMMENDED DECISION', 8.82, 2.08, 3.2, 0.24, { size: 9, bold: true, color: '8EE7ED' });
  text(slide, 'APPROVE\nPHASE 1', 8.82, 2.75, 3.0, 1.05, { size: 30, bold: true, color: C.white });
  text(slide, 'Next deliverable:', 8.82, 4.28, 2.5, 0.25, { size: 10, bold: true, color: 'BFD8E8' });
  text(slide, 'Working map prototype connected to the existing Voyage and Activity screens.', 8.82, 4.68, 2.95, 0.78, { size: 13, color: C.white, valign: 'top' });
  pill(slide, 'QUESTIONS & DISCUSSION', 4.95, 6.48, 3.1, C.gold, C.navy);
}

fs.mkdirSync(outDir, { recursive: true });
await pptx.writeFile({ fileName: outFile });
console.log(outFile);
