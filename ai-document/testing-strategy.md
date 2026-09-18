# Testing strategy

Execution results are recorded in evidence/README.md and the WF-001 Builder report. The matrix below defines expected checks; it does not imply independent acceptance.

- AC1: Read reconciled instructions for contradictions; verify copied reference docs and checklist/task links.
- AC2: npm run progress -- --port=4187; fetch /api/progress (current focus, summary, tasks) and / (HTML); test conflicting statuses, missing prompts and HTML escaping with temporary fixtures. Record URL, process shutdown and cleanup.
- AC3: npm ci without lockfile rewrite; dev exits with readable output and valid maps; production exits with actual JS/CSS minification, notices, valid local resources and no maps. Modify a temporary source fixture, rebuild, observe output change; delete generated fixture output and rebuild; restore fixtures.
- AC4: npm run release invokes production itself; inspect staging and ZIP in a clean temporary directory; verify required PHP/autoload files, root folder, version, and excluded content. Confirm autoload resolves Core classes without repository vendor.
- AC5: git check-ignore checks dependencies/releases/maps and confirms source/scripts/lockfiles/production assets are not ignored; document existing tracked state. npm run build and legacy wrappers remain consistent. Validate changed CI without claiming an unexecuted hosted run passed.
- AC6: In a disposable fixture, invalid source, missing package input, version mismatch, unsafe paths and symlinks fail nonzero without publishing partial output. Run repeated dev/production/release cycles and inspect stale files. Run composer run phpcs, composer run phpstan, composer run test directly and capture exit codes; do not trust check.sh's current output parsing. Install ZIP in isolated WordPress and record actual detection/activation results; absent site prerequisites stay NOT VERIFIED.
- AC7: Architect independently inspects the complete diff, reruns relevant checks, records AC verdicts and stable finding IDs. Only then update checklist/status.

Store actual logs/manifests under evidence/ and manual procedures/results under walkthroughs/. Evidence files must not contain secrets. No fabricated screenshots, execution results, or product acceptance.

## Product implementation verification

Historical AC1–AC7 above refer to WF-001 only. CORE-001 owns new bootstrap/i18n regressions and isolated WordPress evidence; earlier tooling acceptance cannot satisfy these checks. For later regional work, use the representative locale/unit/currency matrix in internationalization.md, including unchanged historical values, cross-unit thresholds, RTL, zero/two/three-decimal currencies and timezone boundaries. All product checks remain NOT VERIFIED until executed and recorded under the owning task.
