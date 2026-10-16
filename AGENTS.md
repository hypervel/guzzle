# Agent Guidelines

## Code and tooling

- All code must remain compatible with PHP 7.4. Run PHPStan and PHP-CS-Fixer
  only under a PHP 7.4.x runtime; never run either tool under any other PHP
  major.minor version.
- Use fully qualified `#[\SensitiveParameter]` on concrete executable
  parameters when their established role normally carries a secret,
  credential-bearing aggregate, or confidential Guzzle-owned container, and
  the active frame can throw or invoke throwing code.
- Repeat the attribute on every qualifying owned caller, callee, concrete trait
  method, and closure parameter. Do not add it to interfaces, abstract-only
  declarations, pure/no-realistic-throw helpers, assignment-only sites,
  arbitrary generic payloads, or completed non-recoverable derivatives.
- For PHP 7.4 compatibility, put `#[\SensitiveParameter]` on its own line and
  the parameter on the following line, expand the complete parameter list, and
  never add a comma after the final parameter. Native trace redaction starts on
  PHP 8.2 and does not redact logs, messages, properties, wire traffic, captured
  variables, return values, or the separate backtrace `$this`/`object`.
- Always pass an explicit character list to `trim()`, `ltrim()`, and `rtrim()`;
  never rely on the default characters.
- Handle `preg_*` engine failures: when the result is used as data, test for
  `false` or `null` and throw a `\RuntimeException` including
  `preg_last_error_msg()`; boolean validation guards must compare strictly, such
  as `=== 1`, so an engine failure can only ever fail closed. Diagnostic
  escaping is the narrow exception: use a deterministic bytewise fallback rather
  than throwing, so it cannot obscure the original exception.
- Anchor validation patterns to the true end of input with the `D` modifier or
  `\z`; a bare `$` accepts a trailing newline.
- Never embed raw control bytes in exception messages and other diagnostics;
  escape or redact the offending value first.
- Helper classes that expose only public static methods are `final` and have a
  private constructor.
- Resist native PHP serialization when a class holds live state (streams,
  resources, handles, callbacks, credentials) or when magic methods such as
  `__destruct()` have side effects that untrusted unserialized data could
  redirect, as with the file and session write fixes in Guzzle's persisting
  cookie jars. Plain data holders, such as Guzzle's in-memory cookie jar, remain
  serializable.
- To resist, `__serialize()` and `__unserialize()` both throw
  `\LogicException(static::class.' should never be serialized')` and its
  unserialized counterpart, usually via the `@internal` `NonSerializableTrait`.
  Where a `__destruct()` has such a side effect, it is enabled only by the
  constructor and disabled in `__wakeup()` and `__unserialize()` before
  throwing, so the protection holds even if the exception is swallowed.
- In general, numeric inputs should not accept non-finite floats. In situations
  where they are accepted and we need to cast to a string, we should branch on
  `\is_finite($value)`, using `(string) $value` for the finite case and
  `\is_nan($value) ? 'NAN' : ($value > 0 ? 'INF' : '-INF')` otherwise.
- Never call `strtolower()`, `strtoupper()`, `strcasecmp()`, `stripos()`, or
  other locale-sensitive case functions; use the locale-independent
  `GuzzleHttp\Psr7\Utils::asciiToLower()`, `asciiToUpper()`,
  `caselessEquals()`, and `caselessContains()` helpers instead.
- Changes in behavior need a `CHANGELOG.md` entry in the unreleased section of
  the target branch and an `UPGRADING.md` note when the behavior differs between
  major versions.

## Contributor references

- Read the [exception guidelines](docs/contributing/exception-guidelines.md)
  before deciding which exception a change should throw.
- Read the
  [cURL connection reuse reference](docs/contributing/curl-connection-reuse.md)
  before working on the cURL handlers or connection reuse and sharing behavior.

## Documentation

- Wrap markdown prose and PHPDoc text to 80 columns using greedy wrapping. Never
  split a markdown link or an inline code span across a line break; a line that
  cannot be broken may exceed the limit. Avoid em dashes.
- Keep PHPDoc and the corresponding `docs/` pages in sync: shared prose is
  deliberately word-for-word identical, including boilerplate copied verbatim
  between related functions, so apply the same edit to every copy. Only
  formatting and linking may differ, such as a docs link becoming a PHPDoc
  `@see` tag; the wording must never drift.

## Hypervel Fork

This repository is Hypervel's fork of `guzzlehttp/guzzle`. These rules apply on
top of the guidelines above and take precedence where they differ. The fork
sections of the README and `UPGRADING.md` describe the fork; the rest of
`UPGRADING.md` and `CHANGELOG.md` are upstream's history.

> [!WARNING]
> Preserve upstream's public API for supported versions. Composer installs this
> fork in place of `guzzlehttp/guzzle` for third-party packages and SDKs, so
> existing calls and implementations must keep working unchanged. Do not break
> existing public contracts or add required methods to existing interfaces. New
> APIs must be backward compatible.

- Keep fork changes minimal and in upstream's style so upstream updates merge
  cleanly.
- Land every change, including upstream syncs, through a pull request from a
  work branch into the tracked branch. The checkout also has an `upstream`
  remote, so target `hypervel/guzzle` explicitly in every `gh` command:
  `--repo hypervel/guzzle`, or `repos/hypervel/guzzle/...` for `gh api`. Merge
  with a merge commit, never a squash or rebase.
- Split changes into coherent commits that stage whole files, each with a body
  that explains what changed and why. Record the full reasoning in the pull
  request.
- Record fork-only changes in the pull request, the README's Hypervel Fork
  section and the GitHub release notes, and document required migration steps in
  the fork section of `UPGRADING.md`. `CHANGELOG.md` stays upstream's, which
  overrides the changelog rule above for fork-only changes.
- PHP 7.4 syntax is retained for the static tools; the fork's runtime
  requirements are defined in `composer.json`. Run PHPUnit under PHP 8.4 or
  newer with Swoole, as CI does in the `ghcr.io/hypervel/components-ci` image.
  Run PHPStan and PHP-CS-Fixer under PHP 7.4 as above, setting
  `COMPOSER_IGNORE_PLATFORM_REQ=php,ext-swoole` only when installing the root
  package.
- Regenerate `phpstan-baseline.neon` under PHP 7.4 with
  `make static-phpstan-update-baseline`, using the same variable, whenever an
  upstream merge conflicts in it.
- Sync upstream on a work branch with `git merge upstream/<branch>`, never a
  rebase, and keep the fork's intentional behavior when resolving conflicts.
  When upstream merges a pull request the fork already carries, reconcile the
  fork's copy with upstream's final version, keep the fork's own changes on top
  of it, and remove only the code it supersedes. Before merging, run the fork's
  checks and the affected Hypervel components test suites against the candidate
  through temporary Composer path repositories.
- Never fetch or push upstream tags: keep `remote.upstream.tagOpt` set to
  `--no-tags`, and push only work branches and fork tags.
- Tag a release after every merge that changes the fork. If the merge includes a
  new upstream release, tag `<that release>.1` (for example `8.2.1.1`);
  otherwise increase the last number (for example `8.2.0.1` to `8.2.0.2`). List
  the included upstream pull requests in the GitHub release.
