import { copyFileSync } from 'fs';

copyFileSync('dist/index.html', 'dist/404.html');

console.log('✓ dist/index.html copied to dist/404.html');