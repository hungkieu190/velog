/** Compatibility entry point; implementation lives in scripts/. */
import('../scripts/build.mjs').then(({ build }) => build(process.argv.includes('--dev') ? 'development' : 'production')).catch((error) => {
  process.stderr.write(`${error.message}\n`);
  process.exitCode = 1;
});
