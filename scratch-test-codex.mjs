import { dispatch } from './scripts/agent-adapters/codex.mjs';
console.log('Testing codex adapter exports...');
console.log(typeof dispatch === 'function');
