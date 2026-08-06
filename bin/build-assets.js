/**
 * VeLog Assets Build Script
 * Author: Mamflow <https://mamflow.com>
 *
 * Usage:
 *   node bin/build-assets.js          (Production: minified)
 *   node bin/build-assets.js --dev    (Development: expanded, sourcemap)
 */

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const isDev = process.argv.includes('--dev');
const rootDir = path.resolve(__dirname, '..');
const srcDir = path.join(rootDir, 'src', 'assets');
const distCssDir = path.join(rootDir, 'assets', 'css');
const distJsDir = path.join(rootDir, 'assets', 'js');

// Ensure output directories exist
fs.mkdirSync(distCssDir, { recursive: true });
fs.mkdirSync(distJsDir, { recursive: true });

// Clean sourcemaps in production mode
if (!isDev) {
  [distCssDir, distJsDir].forEach(dir => {
    fs.readdirSync(dir).forEach(file => {
      if (file.endsWith('.map')) {
        fs.unlinkSync(path.join(dir, file));
      }
    });
  });
}

console.log(`\x1b[36m[VeLog Build]\x1b[0m Mode: \x1b[1m${isDev ? 'Development' : 'Production (Minified)'}\x1b[0m`);

// 1. Build SCSS files
const scssFiles = ['admin.scss', 'frontend.scss'];
scssFiles.forEach((file) => {
  const srcFile = path.join(srcDir, 'scss', file);
  const outFile = path.join(distCssDir, file.replace('.scss', '.css'));
  if (fs.existsSync(srcFile)) {
    const styleFlag = isDev ? 'expanded' : 'compressed';
    const sourceMapFlag = isDev ? '--source-map' : '--no-source-map';
    try {
      execSync(`npx sass "${srcFile}" "${outFile}" --style=${styleFlag} ${sourceMapFlag}`, {
        stdio: 'inherit',
        cwd: rootDir,
      });
      console.log(`  \x1b[32m✓ CSS\x1b[0m ${path.basename(outFile)}`);
    } catch (err) {
      console.error(`  \x1b[31m✗ CSS Error\x1b[0m ${file}:`, err.message);
      process.exit(1);
    }
  }
});

// 2. Build JS files
const jsFiles = ['admin.js', 'frontend.js'];
jsFiles.forEach((file) => {
  const srcFile = path.join(srcDir, 'js', file);
  const outFile = path.join(distJsDir, file);
  if (fs.existsSync(srcFile)) {
    const minifyFlag = isDev ? '' : '--minify';
    const sourcemapFlag = isDev ? '--sourcemap' : '';
    try {
      execSync(`npx esbuild "${srcFile}" --outfile="${outFile}" --bundle ${minifyFlag} ${sourcemapFlag}`, {
        stdio: 'inherit',
        cwd: rootDir,
      });
      console.log(`  \x1b[32m✓ JS\x1b[0m ${path.basename(outFile)}`);
    } catch (err) {
      console.error(`  \x1b[31m✗ JS Error\x1b[0m ${file}:`, err.message);
      process.exit(1);
    }
  }
});

console.log(`\x1b[32m\x1b[1m[VeLog Build] Assets build complete!\x1b[0m\n`);
