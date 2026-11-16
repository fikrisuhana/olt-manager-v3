// Salin aset pihak ketiga dari node_modules ke public/assets (dipanggil `npm run build:vendor`).
const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..', '..');
const copies = [
  ['node_modules/lucide/dist/umd/lucide.min.js', 'public/assets/vendor/lucide.min.js'],
  ['node_modules/@fontsource-variable/inter/files/inter-latin-wght-normal.woff2', 'public/assets/fonts/inter-latin-wght-normal.woff2'],
  ['node_modules/@fontsource-variable/jetbrains-mono/files/jetbrains-mono-latin-wght-normal.woff2', 'public/assets/fonts/jetbrains-mono-latin-wght-normal.woff2'],
];

for (const [src, dst] of copies) {
  const from = path.join(root, src);
  const to = path.join(root, dst);
  fs.mkdirSync(path.dirname(to), { recursive: true });
  fs.copyFileSync(from, to);
  console.log(`  ${src} -> ${dst} (${fs.statSync(to).size} B)`);
}
