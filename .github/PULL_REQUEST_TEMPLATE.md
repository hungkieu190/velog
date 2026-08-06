## Pull Request Description

### Summary

Describe what this PR does and why.

### Related Issue

Closes #[issue-number]

### Type of Change

- [ ] Bug fix (non-breaking change that fixes an issue)
- [ ] New feature (non-breaking change that adds functionality)
- [ ] Breaking change (fix or feature that would cause existing functionality to change)
- [ ] Documentation update
- [ ] Refactoring (no functional changes)
- [ ] CI/CD changes

---

## Checklist

### Code Quality

- [ ] I have read `AGENT.md` and followed all rules.
- [ ] All new functions are prefixed with `mf_`.
- [ ] All new classes use namespace `MF\VeLog\`.
- [ ] All PHP files have the `ABSPATH` guard.
- [ ] All inputs are sanitized.
- [ ] All outputs are escaped.
- [ ] All forms use nonces.
- [ ] `composer run phpcs` passes.
- [ ] `composer run phpstan` passes.

### Testing

- [ ] `composer run test` passes.
- [ ] New tests written for new functionality.
- [ ] Tested on WordPress 6.4+.
- [ ] Tested on PHP 8.1+.

### Documentation

- [ ] PHPDoc added to all public methods.
- [ ] `CHANGELOG.md` updated.
- [ ] `docs/` updated if applicable.
- [ ] Feature plan in `plans/` updated if applicable.

### Security

- [ ] No `var_dump`, `console.log`, `print_r` left in code.
- [ ] No new external HTTP requests without justification.
- [ ] Capability checks in place for all sensitive operations.

---

## Screenshots (if applicable)

---

## Additional Notes
