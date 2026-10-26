# Contributing to Agency Client Portal

Thanks for your interest in improving the plugin.

## Reporting bugs

Open an issue with:

- your WordPress and PHP versions,
- the steps to reproduce the bug,
- what you expected and what happened,
- any relevant entries from `wp-content/debug.log`.

Please do **not** report security vulnerabilities in public issues. Use GitHub's private vulnerability reporting ("Security" tab → "Report a vulnerability") instead.

## Pull requests

1. Fork the repository and create a branch from `main`.
2. Keep each pull request focused on a single change.
3. Follow the existing code style:
   - PHP classes are named `Agency_Foo` and live in `class-agency-foo.php`.
   - JavaScript uses an IIFE with jQuery (`(function($) { 'use strict'; … })(jQuery)`), with no build step.
   - User-facing strings are in English and wrapped in gettext functions with the `agency-client-portal` text domain.
   - Code comments may be in French, to match the existing codebase.
4. Escape all output, sanitize all input, and check nonces and capabilities on every write operation.
5. Put database changes in a new migration under `includes/migrations/`, and bump `Agency_Database::DB_VERSION`.
6. Run [Plugin Check](https://wordpress.org/plugins/plugin-check/) and make sure it reports no errors.
7. Describe what you changed and how you tested it.

## Translations

Translations are managed on [translate.wordpress.org](https://translate.wordpress.org/projects/wp-plugins/agency-client-portal/). The French translation is also shipped in `languages/`.

By contributing, you agree that your contributions are licensed under the GPL-2.0-or-later license.
