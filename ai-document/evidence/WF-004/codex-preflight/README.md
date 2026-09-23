# Codex CLI preflight — 2026-09-23

Architect verification in disposable /tmp directories, not implementation of WF-004. Codex CLI 0.155.0-alpha.9; login status reported ChatGPT authentication. Summary files were copied from the actual probes; original stdout/stderr and fixture/schema remain at the temporary paths recorded in each summary and may expire. These summaries are not full controller evidence.

- Non-interactive stdin prompt, read-only sandbox, JSONL and output schema: exit 0; expected marker returned.
- A separate persisted session read HANDOFF.md and returned the expected four fields: exit 0.
- Resume by that explicit session UUID returned the same fields without tools after the fixture was renamed: exit 0, same thread ID.
- Invalid CLI argument --velog-invalid-probe-option: exit 2 before a turn. This is startup error detection, not a transport-failure or cleanup test.

Invocation shapes: `codex exec --ephemeral --skip-git-repo-check --sandbox read-only --json --output-schema <schema> -o <result> -C <fixture> -`; persisted probe omitted --ephemeral; resume used `codex exec --sandbox read-only resume <explicit UUID> --skip-git-repo-check --json --output-schema <schema> -o <result> -`. Prompt was passed on stdin. Each subprocess had a bounded deadline and a dedicated process group; timeout code was available but not exercised.

NOT VERIFIED: integrated controller, JSON publication, polling, replay protection, crash recovery, real project rule intake, permission boundaries for review tests, real Antigravity CLI, live two-agent loop, application desktop conversation integration. command -v agy and command -v antigravity found no executable in the inspected environment. Shell default Node was v20.19.2; project testing must select supported Node 24. No product source was changed by these probes.
