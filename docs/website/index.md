# Set Tag Order Documentation

Set Tag Order lets editors choose the order post tags appear in, instead of accepting WordPress's default alphabetical listing. It works in both the Block Editor and the Classic Editor, and gives themes control over the separator and CSS classes used for tag output.

## Current Release

Stable tag: **1.2.0**

Requires WordPress 6.3 or newer and PHP 7.4 or newer. Tested to WordPress 7.1.

## Documentation

- [Getting Started](getting-started.md) — install, activate, and set your first tag order
- [Usage](usage.md) — ordering tags in the Block Editor and Classic Editor
- [Theme Integration](theme-integration.md) — separators, CSS classes, and template output
- [Troubleshooting](troubleshooting.md) — when the order does not appear on the front end
- [Frequently Asked Questions](faq.md)

## What It Does

- **Custom tag order** — arrange tags per post, in the order you want them read.
- **Custom separator** — choose the character between tags, or none at all.
- **Custom CSS classes** — add classes to tag links so your theme can style them.
- **Both editors** — the same stored order in the Block Editor and the Classic Editor.
- **Debug mode** — optional diagnostic logging when something is not behaving.

Settings live under **Settings → Set Tag Order**.

## How It Works

The order is stored on the post itself, as a list of tag IDs. On the front end the plugin reorders tags at the point WordPress fetches them, which means themes, blocks, and template functions all inherit the order without needing to know the plugin is there.
