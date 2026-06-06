# YAML config loading {#yaml-config-loading}

This document defines how Switon loads a single `switon.yml` configuration file and how it interacts with
`config/*.php`.

This is a kernel bootstrap protocol. It is intentionally small and predictable.

Quick lookup:

- `parameters` interpolation: section 3
- YAML + PHP merge rules: section 4
- Service ID → definition forms: section 5.2
- Factory named services (`Type#name`, `#default`): section 5.3

---

## 1. Discovery {#discovery}

- The kernel MAY load YAML configuration from an explicit path specified by an environment variable.
- If the environment variable is not set, the kernel SHOULD fall back to a default root-level file.

### 1.1. Explicit path (environment variable) {#explicit-path-environment-variable}

- If `SWITON_CONFIG_FILE` is set, it MUST be treated as the YAML configuration file path.
- The value SHOULD support Switon path aliases (for example `@root/switon.yml`).

### 1.2. Default file (no environment variable) {#default-file-no-environment-variable}

- If `SWITON_CONFIG_FILE` is not set, the kernel SHOULD load `{root}/switon.yml` if it exists.
- If this file does not exist, YAML configuration is considered absent.

### 1.3. Notes {#discovery-notes}

- If multiple candidate files exist, the kernel MUST use the first match by priority and ignore the rest (no error), to
  avoid breaking projects that contain unrelated `.yml` files.

The YAML content MUST conform to the Switon YAML Subset specification and MUST parse into a mapping or sequence.

---

## 2. Loading order and sources {#loading-order-and-sources}

The kernel loads configuration in this order:

1. Environment variables: `.env.local`, then `.env` (system environment variables always win)
2. YAML: resolved by section 1 (at most one file)
3. PHP: `{root}/config/*.php` (all visible files)

This document only defines the YAML/PHP interaction. The `.env` protocol is defined elsewhere.

---

## 3. YAML interpolation variables {#yaml-interpolation-variables}

If YAML configuration is present, the kernel MAY interpolate placeholders in YAML string scalars using a variables map.

- The variables map SHOULD be derived from environment variables (for example `getenv()`).
- If a placeholder `${NAME}` is encountered without a default (`:-default`) and `NAME` is missing, interpolation MUST
  fail.

Placeholder syntax and constraints are defined by the Switon YAML Subset specification.

---

## 4. Merge semantics (YAML + PHP) {#merge-semantics-yaml-php}

After YAML is loaded (and optionally interpolated), the kernel combines YAML and PHP configuration into one
configuration array.

### 4.1. Top-level keys {#top-level-keys}

Configuration is merged by top-level keys (service IDs). A top-level key is the array key in the configuration returned
to the kernel (for example `Psr\Log\LoggerInterface::class`).

### 4.2. Conflict handling {#conflict-handling}

When the same top-level key exists in both YAML and PHP:

- If **both values are arrays**, the kernel MUST merge them recursively as described in section 4.3.
- Otherwise (either side is not an array), the kernel MUST fail fast with a configuration load error.

This rule exists to avoid ambiguous “partial merge” behaviour for service definitions.

### 4.3. Recursive merge rules {#recursive-merge-rules}

When merging two arrays (YAML base + PHP patch) under the same top-level key:

- **Mapping + mapping**: merge recursively by key; on conflicts, the PHP value overrides the YAML value.
- **Sequence + sequence**: PHP overrides YAML (no automatic concatenation).
- **Type mismatch**: the kernel MUST fail fast with a configuration load error.

---

## 5. DI definition semantics {#di-definition-semantics}

This section defines how DI service definitions in `switon.yml` are interpreted by the container.

### 5.1. Service IDs {#service-ids}

- The YAML root is a mapping of `serviceId => definition`.
- A service ID is typically an interface FQCN (for example `Psr\Log\LoggerInterface`).

### 5.2. Definition forms {#definition-forms}

The container supports these definition forms:

- **String**: class name, or reference ID.
- **Mapping**: array definition. When `class` is missing, the container infers `class` from the service ID.
- **Object**: only available via `config/*.php` (YAML cannot express objects).

### 5.2.1. Recommended YAML shape {#recommended-yaml-shape}

For `switon.yml`, projects **SHOULD** express a service as a **mapping** with an explicit `class` key when binding an
interface to an implementation or when the definition has option keys (not only a bare alias).

- **Rationale**: YAML and `config/*.php` merge **by top-level service ID** (section 4). If both sides are **arrays**,
  the kernel merges recursively. If one side is a **string** and the other is an **array** for the same key, loading *
  *MUST** fail fast (section 4.2). Using a mapping with `class` keeps the YAML side an array so PHP patches can add or
  override keys without a type clash.
- A **string** scalar (`serviceId: SomeClassOrAlias`) remains valid for a pure alias or class-name-only definition when
  no PHP file will supply a different shape for that same key.

Example (interface → implementation):

```yaml
Switon\Example\FooInterface:
  class: Switon\Example\Foo
```

### 5.3. Factory definitions (named services) {#factory-definitions-named-services}

YAML MAY express named service factories (for example `Type#default`) using a service definition mapping.

If a top-level service definition is a mapping with a `class` key, and the configured class implements
`Switon\Di\FactoryInterface`:

- The remaining keys (everything except `class`) MUST be treated as factory named definitions (`name => definition`).
- The container MUST expand each entry into a named service ID `Type#name`.
- The container MUST map plain `Type` to `Type#default`.

Constraints:

- The `class` value MUST be a concrete FQCN.
- The factory class MUST accept the named definitions as a single array constructor argument (equivalent to
  `new Factory($definitions)`).
- Use `config/*.php` when you need dynamic logic or a different factory construction contract.

Example:

```yaml
Switon\Db\ClientInterface:
  class: Switon\Di\Factory
  default:
    uri: ${DB_URL}
```

## 6. Rationale (informative) {#rationale}

- YAML is a good format for centralized, mostly-static configuration.
- `config/*.php` remains useful for dynamic or computed configuration, but its merging rules must remain predictable.
- Recursive merging is only allowed when both sides are arrays, so the result is deterministic and reviewable.

