import http from 'node:http';
import fs from 'node:fs/promises';
import path from 'node:path';
import { root } from './files.mjs';
import { readProgress } from './progress-data.mjs';
import { renderProgress } from './progress-view.mjs';

const port = Number(process.argv.find((arg) => arg.startsWith('--port='))?.slice(7) || process.env.PROGRESS_PORT || 4177);
if (!Number.isInteger(port) || port < 1 || port > 65535) throw new Error('Port must be an integer from 1 to 65535');

const server = http.createServer(async (request, response) => {
  response.setHeader('Cache-Control', 'no-store');
  response.setHeader('X-Content-Type-Options', 'nosniff');
  response.setHeader('Content-Security-Policy', "default-src 'none'; style-src 'self'; base-uri 'none'; frame-ancestors 'none'");
  if (!['GET', 'HEAD'].includes(request.method)) { response.writeHead(405); response.end(); return; }
  if (!['127.0.0.1', 'localhost'].includes((request.headers.host || '').split(':')[0])) { response.writeHead(403); response.end(); return; }
  try {
    if (request.url === '/progress.css') {
      response.setHeader('Content-Type', 'text/css');
      // Read authoritative tool stylesheet so progress works before the first build.
      response.end(await fs.readFile(path.join(root, 'src/css/progress.css')));
    } else if (['/', '/api/progress'].includes(request.url)) {
      const data = await readProgress(root);
      response.setHeader('Content-Type', request.url === '/' ? 'text/html; charset=utf-8' : 'application/json');
      response.end(request.url === '/' ? renderProgress(data) : JSON.stringify(data));
    } else { response.writeHead(404); response.end('Not found'); }
  } catch (error) {
    response.writeHead(500, { 'Content-Type': 'text/plain; charset=utf-8' });
    response.end(`Cannot read progress: ${error.message}`);
  }
});
server.on('error', (error) => { process.stderr.write(`${error.message}\n`); process.exitCode = 1; });
server.listen(port, '127.0.0.1', () => {
  process.stdout.write(`Project progress dashboard is running at http://127.0.0.1:${port}\n`);
});

function shutdown() {
  server.close();
}
process.on('SIGINT', () => { shutdown(); });
process.on('SIGTERM', () => { shutdown(); });
