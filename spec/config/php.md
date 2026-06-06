# PHP config loading {#php-config-loading}

This document defines how Switon loads `config/*.php` files and how the result interacts with `switon.yml`.

This is a kernel bootstrap protocol. It is intentionally small and predictable.

---

## 1. Discovery {#discovery}

- PHP configuration is loaded from `{root}/config/*.php` when the directory exists.
- Files starting with `.` are ignored.

---

## 2. File contract {#file-contract}

- Each `config/*.php` file MUST return an array (`array<string, mixed>`).
- Definitions are keyed by service ID (for example `Psr\Log\LoggerInterface::class`).

---

## 3. Merge semantics (YAML + PHP) {#merge-semantics-yaml-php}

- YAML is loaded first (if present).
- PHP is loaded after YAML and overrides YAML on conflicts according to the recursive merge protocol.

Full details are defined in `config/yaml.md#merge-semantics-yaml-php`.

