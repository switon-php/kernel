# Design Rationale {#design-rationale}
`switon/kernel` exists to keep application startup in one predictable place.

## See also {#see-also}

- [Kernel Package](README.md) — public bootstrap contract and first-use path
- [Kernel FAQ](faq.md#kernel-faq) — short answers for env, config, and startup errors

## Core Problem {#core-problem}

Application bootstrap tends to spread across entrypoints.

- one script loads env files
- another merges config
- a third wires providers
- fatal handling gets added late or forgotten

The package standardizes that startup work so the app reaches its runtime through one known bootstrap path.

## Primary Goal {#primary-goal}

The kernel makes startup order explicit.

- `.env.local` overrides `.env` when both define the same key; system env overrides both
- env loads before config interpolation
- config loads before provider bootstrap
- providers register before the runtime takes over
- fatal handling starts only after bootstrap reaches its boundary

That gives application code one stable place to start while still leaving the runtime loop to HTTP, CLI, or worker packages.

## Core Choice {#core-choice}

Switon chooses a bootstrap orchestrator, not a long-lived runtime host.

- the kernel owns startup coordination
- `switon.yml` stays the base bootstrap surface
- `config/*.php` applies as a PHP patch on top of `switon.yml` when needed
- Composer-extra discovery keeps provider registration out of manual wiring

This keeps the kernel small enough to understand, but still strong enough to remove bootstrap drift across apps.

## Trade-Off {#trade-off}

The package prefers one clear startup contract over many ad hoc entrypoint styles.

- **Benefit:** startup behavior is easier to predict and document.
- **Benefit:** env, config, providers, and fatal handling stay aligned.
- **Cost:** custom startup sequencing belongs outside the kernel.

## Deliberate Simplicity {#deliberate-simplicity}

The kernel does not own the runtime loop.

- it does not replace HTTP or CLI entrypoints
- it does not hide application-specific startup work
- it only prepares the app for the next layer

That boundary keeps the framework readable: kernel for bootstrap, component packages for their own runtime behavior.
