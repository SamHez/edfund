# EdFund project instructions

- Work only in this EdFund repository. Do not change sibling projects, including Kigali Movers or Amazing Guards.
- Preserve all manual text, image selections, captions and code edits. Inspect current files before editing. If generated HTML and source differ, reconcile the manual changes before rebuilding. Never use reset/clean/checkout to discard user changes.
- The client wants a basic, purpose-specific PHP/MariaDB CMS. Follow docs/CMS-PHASE-PROMPTS.md; its scope supersedes optional complexity in the earlier CMS implementation plan.
- Use a visual section editor: the actual page preview beside small labelled forms, with click-to-select sections. Preserve the site's existing design. No freeform layout canvas, plugin system or custom-code fields.
- Existing local database: edfund. XAMPP's Unix socket is documented in docs/CMS-PHASE-PROMPTS.md. Check connectivity and contents before migrations; do not recreate, truncate or overwrite existing data.
- Keep credentials, secrets, runtime uploads, sessions, logs, backups and database exports out of Git. Local root database access must never become production configuration. Do not print or request secrets in public files, commit messages or chat.
- Never let application updates overwrite client content or uploaded images. Initial content import is explicit and repeat-safe; ongoing deployments update code and versioned schema only.
- Restrict work to the phase the user requests. Security, content validation, protected media and restore capability remain requirements even with a small interface.
- Do not rebuild a deployment ZIP until requested by the user or the requested phase explicitly includes release packaging. Production publication requires an explicit deployment request and confirmed target/access; do not treat repository pushes as website deployment.
- Repository: https://github.com/SamHez/edfund.git. Preserve remote history; no force pushes. Exclude generated archives and local secrets. Commit only reviewed project changes.
