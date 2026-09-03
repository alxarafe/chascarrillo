# Chascarrillo operational checkpoint

Date: 2026-09-03 (Europe/Madrid)

## Current dependency

Chascarrillo is locked to `alxarafe/alxarafe v0.6.11` at commit
`4b5a6252750537280c04aa4378b3fcf2570f8efb`. Composer was run with
`--no-scripts`, so no published assets were overwritten.

The general `partial/user_menu` and `partial/theme_switcher` overrides were
removed. Default and High Contrast now inherit them from Alxarafe. The
Cyberpunk language and theme partials remain because their markup is specific
to that theme.

## Validated

- PHPUnit: 2 tests, 2 assertions.
- PHPCS: clean for `Modules`.
- PHPStan: no errors for `Modules`.
- Psalm: no errors for `Modules` (116 informational issues remain).
- Composer configuration: valid.
- Browser matrix: 75/75 public checks passed across Default, High Contrast and
  Cyberpunk at 360, 390, 768, 1024 and 1440 px.
- Routes covered: home, blog index, a real article, a static page and login.
- Login fields render without a literal `::component.card` identifier.
- Language links work from `/blog`, use root-relative URLs and persist the
  language cookie.
- Language flags load in all three themes.
- User, language and theme controls are aligned.
- Markdown tables no longer cause global horizontal overflow.
- Blade was tested with cold and warm compiled caches.

## Pending before release

1. Obtain or create an authorised local administrative session. The saved
   audit browser session has expired; protected routes redirect to login.
2. Repeat the administrative matrix for Post, PageAdmin, Menu, Tag and Config
   in Default, High Contrast and Cyberpunk.
3. Validate open Select2 widgets, tabs and modals with keyboard and touch-sized
   viewports.
4. Decide and implement the mobile table behaviour owned by Chascarrillo and
   by `resource-controller`; do not duplicate renderer templates locally.
5. Review whether `templates/partial/head.blade.php` can be reduced further by
   moving Chascarrillo-only assets to Blade stacks or another narrow extension
   point.
6. Move the Cyberpunk CSS change back to its source template/theme asset before
   republishing, if a source file exists; avoid treating `public_html` as the
   canonical source.
7. Review Composer advisory `PKSA-rdkp-vv9z-mjkg` / CVE-2026-67434 affecting
   the installed PHP_CodeSniffer development dependency.
8. Reconcile the existing `UpdateService::VERSION` change with the next
   Chascarrillo release version, then repeat all QA before tagging or pushing.
9. Triage `docs/audit/chascarrillo-responsive.md` into GitHub issues. The audit
   report and evidence directory intentionally remain untracked at this
   checkpoint.

No Chascarrillo tag or remote push has been created from this checkpoint.
