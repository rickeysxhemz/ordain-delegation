# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 1.x     | :white_check_mark: |

## Reporting a Vulnerability

The security of this permission delegation package is taken seriously. If you discover a security vulnerability, please report it responsibly.

### How to Report

**Please DO NOT report security vulnerabilities through public GitHub issues.**

Instead, please report them via email to: **dev@waqasmajeed.dev**

Include the following information in your report:

- Type of vulnerability (e.g., privilege escalation, authorization bypass, injection)
- Full paths of source file(s) related to the vulnerability
- Location of the affected source code (tag/branch/commit or direct URL)
- Step-by-step instructions to reproduce the issue
- Proof-of-concept or exploit code (if possible)
- Impact of the issue, including how an attacker might exploit it

### Response Timeline

- **Initial Response**: Within 48 hours
- **Status Update**: Within 7 days
- **Fix Timeline**: Depends on severity
  - Critical: Within 24-48 hours
  - High: Within 7 days
  - Medium: Within 30 days
  - Low: Next scheduled release

### Disclosure Policy

- We will acknowledge receipt of your vulnerability report
- We will confirm the vulnerability and determine its impact
- We will release a fix as soon as possible, depending on complexity
- We will publicly disclose the vulnerability after a fix is available

### Safe Harbor

We consider security research conducted in accordance with this policy to be:

- Authorized concerning any applicable anti-hacking laws
- Authorized concerning any relevant anti-circumvention laws
- Exempt from restrictions in our Terms of Service that would interfere with conducting security research

We will not pursue civil action or initiate a complaint to law enforcement for accidental, good faith violations of this policy.

## Security Best Practices

When using this package:

1. **Keep dependencies updated** - Run `composer audit` regularly
2. **Use strict type checking** - Enable `declare(strict_types=1)`
3. **Validate all inputs** - Never trust user input for delegation decisions
4. **Audit delegation actions** - Enable the audit logging feature
5. **Principle of least privilege** - Only grant minimum required permissions
6. **Regular access reviews** - Periodically review delegated permissions