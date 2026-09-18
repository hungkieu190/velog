> Workflow: read [AGENTS.md](AGENTS.md) and [the project index](ai-document/README.md). Implement approved tasks only.

# Contributing to VeLog — by [Mamflow](https://mamflow.com)

Thank you for your interest in contributing to **VeLog — Digital Vehicle Passport** by [Mamflow](https://mamflow.com)!

We welcome contributions from everyone. By participating in this project, you agree to abide by our [Code of Conduct](CODE_OF_CONDUCT.md).

---

## Table of Contents

- [Development Workflow](#development-workflow)
- [Getting Started](#getting-started)
- [Coding Standards](#coding-standards)
- [Submitting Changes](#submitting-changes)
- [Reporting Bugs](#reporting-bugs)
- [Feature Requests](#feature-requests)

---

## Development Workflow

> **Important**: Always read [AGENTS.md](AGENTS.md) before contributing code.

1. **Document first** — Every implementation must have an approved task in `ai-document/tasks/`; `ai-document/product-inputs/` retains product inputs.
2. **Design second** — Architecture must be approved before implementation.
3. **Implement third** — Write code only after documentation and design are reviewed.

---

## Getting Started

### Prerequisites

- PHP 8.1+
- WordPress 6.4+
- Composer
- Node.js 24 LTS (see .nvmrc)
- npm >=10 <12
- PHP ZipArchive for local release packaging

### Setup

```bash
# Clone the repository
git clone https://mamflow.com/velog.git
cd velog

# Install PHP dependencies
composer install

# Select the project runtime and install locked JS dependencies
nvm install
nvm use
npm ci

# Verify code quality
composer run lint
```

---

## Coding Standards

- Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/).
- All PHP functions must be prefixed with `mf_`.
- All classes must use namespace `MF\VeLog\`.
- Never commit debug code (`var_dump`, `console.log`, `print_r`).
- All user-facing strings must be translatable.

Run before every commit:

```bash
composer run phpcs
composer run phpstan
```

---

## Submitting Changes

1. Fork the repository at [mamflow.com/velog](https://mamflow.com/velog).
2. Create a branch: `git checkout -b feature/your-feature-name`.
3. Commit your changes following [Conventional Commits](https://www.conventionalcommits.org/en/v1.0.0/).
4. Push to your fork and open a Pull Request.
5. Fill in the Pull Request template completely.

---

## Reporting Bugs

Use the [Bug Report](.github/ISSUE_TEMPLATE/bug_report.md) template or contact support at [mamflow.com/support](https://mamflow.com/support).

Please include:
- WordPress version
- PHP version
- Plugin version
- Steps to reproduce
- Expected vs actual behavior

---

## Feature Requests

Use the [Feature Request](.github/ISSUE_TEMPLATE/feature_request.md) template.

New features must have product documentation and an approved task in `ai-document/tasks/` before code is written.

---

## License

By contributing, you agree that your contributions will be licensed under the [GPL-2.0-or-later](LICENSE) license. VeLog is a product of [Mamflow](https://mamflow.com).
