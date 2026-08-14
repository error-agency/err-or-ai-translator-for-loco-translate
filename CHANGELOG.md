# Changelog — Err.or AI Translator for Loco Translate

All notable changes to this plugin are documented here.  
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).  
Versioning follows [Semantic Versioning](https://semver.org/).

---

## [1.6.0] — 2026-08-14

### Rebrand & WordPress.org Compliance
- **Plugin Rebrand** — Rebranded plugin to **Err.or AI Translator for Loco Translate**.
- **Canonical Slug & Text Domain** — Standardized canonical slug (`err-or-ai-translator-for-loco-translate`) and text domain (`err-or-ai-translator-for-loco-translate`).
- **Main Plugin File Rename** — Renamed main plugin file to `err-or-ai-translator-for-loco-translate.php`.
- **PHP Namespacing & Global Prefixing** — Refactored PHP codebase into the `ErrorAgency\LocoAITranslator` namespace and prefixed WordPress global identifiers with `error_lait_`.
- **Removed GitHub Update Checker** — Removed `includes/plugin-update-checker/` library completely to rely exclusively on WordPress.org update distribution.
- **Removed Build Helpers** — Deleted local PowerShell release helper scripts (`backfill_releases.ps1`, `create_releases.ps1`).
- **Settings Migration** — Implemented backward-compatible settings migration from `lat_settings` to `error_lait_settings` with schema version tracking (`error_lait_schema_version`).
- **Standardized Requirements** — Standardized minimum PHP requirement to 7.4 and WordPress requirement to 6.0 across all plugin headers and documentation.
- **WordPress.org Readme** — Created official `readme.txt` with Third-Party Services disclosures for OpenRouter, Ollama, and custom OpenAI-compatible endpoints with verified official links.
- **Licensing & Attribution** — Standardized `LICENSE` file (GPL-2.0-or-later) and source code attributions (Developer: Err.or agency, Lead Developer: K2D).

---

## [1.5.3] — 2026-06-18

### Changed
- **WPCS & Code Quality Alignment** — Verified compliance of all newly added methods and variables with WordPress Coding Standards (using proper snake_case conventions, strict identity checks, sanitization, and array structures).

---

## [1.5.2] — 2026-06-18

### Fixed
- **Deduplication of duplicate strings** — Identical untranslated strings are now grouped and sent to the AI only once per batch, then propagated to all duplicates in memory, reducing API token costs.
- **Bypass of non-translatable strings** — Strings that are purely numeric, URLs, special HTML characters, or pure placeholders are auto-translated in memory without making any AI API requests.

### Added
- **Dynamic Character-Based Batching** — Batches are dynamically limited by character count (up to 3,000 characters of source text) to balance payload size, preventing timeouts on long strings and optimizing request size for short ones.
- **Cache-Friendly Prompt** — Shortened and refactored the default system prompt to reduce prompt tokens and make it compatible with provider-level prompt caching.

---

## [1.5.1] — 2026-06-18

### Fixed
- **Plural strings skipped** — Plural entries (with `msgid_plural`) were previously skipped or only partially translated. Now they are correctly detected and translated.
- **Support for translating plural forms** — AI prompt and API client now request exactly the number of plural forms (`nplurals`) required by the target language.

### Added
- **Performance Caching (Transient Cache)** — Added transient-based caching of the parsed PO entries array during translation jobs.

---

## [1.5.0] — 2026-03-19

### Fixed
- **Translation loop offset bug** — Always slice untranslated entries from index 0.
- **Progress bar display** — Calculate percent based on original vs remaining strings.
- **Failed batch handling** — Flag failed batches as fuzzy to prevent infinite retries.

### Added
- **Token usage tracking** — Surface token counts from API response choices.
- **Per-batch log table** — Display batch stats and source text previews.
- **Live summary strip** — Running totals of time, strings, and tokens.

---

## [1.1.0] — 2026-03-19

### Fixed
- **Editor Panel Injection** — Improved multi-selector panel injection in Loco Translate editor.
- **PHP 7.4 Compatibility** — Replaced PHP 8.0-only functions for host compatibility.

---

## [1.0.0] — 2026-03-18

### Added
- Initial release of AI translation add-on for Loco Translate.
