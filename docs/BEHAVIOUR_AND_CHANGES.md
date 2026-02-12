# Behaviour and Notable Changes

This document explains specific behavioural decisions and notable changes in the Performance Bundle that may affect integration, debugging, or deployment. It is the place to look for **why** certain things work the way they do and for detailed notes on non-obvious changes.

---

## Table of contents

1. [VarDumper (`dump` / `dd`) behaviour in `NowoPerformanceBundle::boot()`](#vardumper-dump--dd-behaviour-in-nowoperformancebundleboot)

---

## VarDumper (`dump` / `dd`) behaviour in `NowoPerformanceBundle::boot()`

### Summary

The bundle **does not replace** Symfony’s VarDumper handler in **web** context. It only registers a **CLI fallback** handler when running under `PHP_SAPI === 'cli'`. In web, Symfony’s default handler (e.g. `DumpDataCollector`) remains in place so `dump()` and `dd()` output appears in the **Web Debug Toolbar** as usual.

### Where it happens

- **Class:** `Nowo\PerformanceBundle\NowoPerformanceBundle`
- **Method:** `boot()`
- **Condition:** Runs only when the kernel is in **debug** mode and the `VarDumper` class exists.

### Behaviour by context

| Context | What the bundle does | Result |
|--------|----------------------|--------|
| **Web** (`PHP_SAPI !== 'cli'`) | **Nothing.** Returns before calling `VarDumper::setHandler()`. | Symfony’s default handler (e.g. `DumpDataCollector`) is used. Dumps appear in the Web Debug Toolbar. |
| **CLI** (`PHP_SAPI === 'cli'`) | Registers a custom handler that writes to `php://stderr` (or `STDOUT` if stderr is unavailable) using `CliDumper`. | `dump()` and `dd()` work in the terminal even when the default VarDumper stream would be invalid (e.g. in some embedded or non-standard CLI environments). |

### Rationale

1. **Web**
   - Replacing the handler in web was causing dumps to bypass the **Web Debug Toolbar** and go to the response body or to a custom stream.
   - Symfony’s `DumpDataCollector` is the standard way to show dumps in web when the Debug Toolbar is enabled; the bundle should not override it.
   - By **not** calling `VarDumper::setHandler()` in web, the bundle leaves the default Symfony behaviour unchanged and ensures dumps continue to appear in the toolbar.

2. **CLI**
   - In some environments (e.g. FrankenPHP CLI, or other SAPIs where the default VarDumper stream is invalid), the default handler can fail (e.g. “fwrite(): Argument #1 ($stream) must be of type resource”).
   - The bundle only applies a **fallback** in CLI: a handler that uses `php://stderr` (or `STDOUT`) and `CliDumper`, so `dump()` and `dd()` work in the terminal without affecting web behaviour.

### Impact on bundle functionality

- **None.** The VarDumper logic in `boot()` is a **convenience** for using `dump()` / `dd()` in debug; it is **not** required for any core feature of the bundle (metrics collection, dashboard, commands, DataCollector, notifications, etc.). Disabling or changing this behaviour does not destabilize the bundle or remove any of its functionality.

### FrankenPHP and other non-standard web environments

- In **web** with FrankenPHP (or similar), the bundle **no longer** sets a custom VarDumper handler. If in that environment Symfony’s default handler fails (e.g. invalid default stream), you can:
  - Rely on application-level configuration (e.g. a small script or config included from `public/index.php` or the front controller) that sets a valid VarDumper handler for web when needed.
  - The demo applications that use FrankenPHP can keep their own workaround (e.g. `config/frankenphp_vardumper.php`) if they need `dump()` / `dd()` to work in web under that SAPI.
- The bundle’s choice to **not** replace the handler in web ensures that in standard Symfony web setups (PHP-FPM, Apache, etc.) the Web Debug Toolbar remains the single, consistent place for dump output.

### Before vs after (for this change)

| Aspect | Before | After |
|--------|--------|--------|
| Web | Bundle replaced VarDumper handler with one writing to `php://output` or `php://stderr`. | Bundle does **not** replace the handler. Symfony’s default (e.g. DumpDataCollector) is used. Dumps show in the Web Debug Toolbar. |
| CLI | Bundle set a fallback handler (stderr / CliDumper). | Unchanged. Fallback handler (stderr / CliDumper) is still set only in CLI. |
| FrankenPHP web | Bundle’s custom handler could avoid stream errors. | No custom handler from the bundle. Use an app-level workaround if needed. |

### References

- Symfony VarDumper: [Symfony VarDumper component](https://symfony.com/doc/current/components/var_dumper.html)
- Web Debug Toolbar / DumpDataCollector: part of Symfony’s standard debug tooling when `symfony/debug-pack` or the profiler is installed.
