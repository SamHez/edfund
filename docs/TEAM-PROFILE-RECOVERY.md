# Recovered team biographies

Recovered 12 November 2026 from the client-supplied edfundf_wp718.sql file outside this repository. The dump was parsed as data, never executed or imported. Only published wp_posts rows of type lif_team matching the four current team names were recovered. WordPress users, credentials, options, plugin tables, private content and raw Elementor markup were not copied into the project.

| Current team member | WordPress post ID | Recovered paragraphs |
| --- | --- | --- |
| Rebecca Day | 1195 | 2 |
| John C. Giebink | 4048 | 3 |
| Dhizaala Stephen | 4049 | 4 |
| Robert Ssebuliba | 4472 | 3 |

source/team-profiles.json records the export SHA256, original public post IDs/slugs/modification dates and plain-text About Me paragraphs. Existing team names, roles and portraits in site.json remain authoritative. No replacement biography was invented. Whitespace/HTML entities are normalized; original wording, figures and historical statements are preserved as published content, not independently reverified as current facts.

The public team cards now open native dialogs. Escape/Close/backdrop closes them and returns focus. Their biographies remain readable in ordinary details/summary disclosures without JavaScript. The Close control remains visible during long-profile scrolling. All four exact biographies were checked in browser tests at 1440, 390 and 320px.

To reproduce extraction intentionally:

```sh
python3 tools/recover_team_profiles.py /private/path/to/edfundf_wp718.sql
```

The recovery tool reads INSERT values with a literal parser and outputs only the four published biographies as text. A matching rerun is a no-op; it refuses to overwrite an existing profiles file with different data, preserving manual corrections. Do not commit the original export or put it under public. The public page is generated through escaped text rather than running WordPress/Elementor content.
