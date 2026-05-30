# LLMs.txt Generator — WordPress Plugin

Generate and serve `/llms.txt` and `/llms-full.txt` live from your WordPress
site root, so AI models (ChatGPT, Claude, Gemini, Perplexity…) can understand
your content with far less crawling and processing effort.

## What is `llms.txt`?

`llms.txt` is an emerging convention (see [llmstxt.org](https://llmstxt.org)) —
a Markdown file at your site root that gives language models a curated,
expert-level map of your most valuable content, the way `robots.txt` guides
search crawlers. It removes ambiguity: instead of converting noisy HTML (nav,
ads, JS) into text and guessing what matters, the model reads a clean index.

| File | Contains | Why |
| --- | --- | --- |
| `/llms.txt` | Title, summary, curated link list with descriptions | Lightweight map |
| `/llms-full.txt` | The above **plus** full plain-text content inline | Single-fetch context; agents fetch it ~2× more often |

> **Reality check:** No major LLM provider has *formally* committed its crawler
> to consuming `llms.txt` yet. It is cheap to provide, standards-aligned, and
> increasingly read by AI agents and tools — a low-risk bet, not a guarantee.

## File structure produced

```markdown
# Site Title

> One-line summary of the site

Optional intro paragraph with extra context for AI agents.

## Pages

- [About](https://example.com/about): Who we are and what we do.
- [Pricing](https://example.com/pricing): Plans and costs.

## Posts

- [Some Article](https://example.com/some-article): Short description.

## Optional

- [Docs](https://example.com/docs): API reference and guides.
```

## Features

- **Live generation** — always in sync with published content.
- **One section per content type** (Pages, Posts, custom post types).
- **Absolute URLs + descriptions** (SEO meta → excerpt → trimmed content).
- **Respects `noindex`** from Yoast SEO, Rank Math, All in One SEO.
- **Caching** via transients, auto-invalidated on content save.
- **Optional bot logging** (no IP stored) — last 100 requests.
- **Developer filters:** `llms_txt_is_noindex`, `llms_txt_output`.

## Installation

1. Copy this repository's contents into `wp-content/plugins/llms-txt-generator/`
   (or clone it directly there).
2. Activate **LLMs.txt Generator** in **Plugins**.
3. Ensure pretty permalinks are on (**Settings → Permalinks** → Save) — the
   endpoints need them.
4. Configure under **Settings → LLMs.txt**.
5. Verify at `https://your-site.com/llms.txt`.

## Repository layout

```
.
├── llms-txt-generator.php              # Bootstrap, activation/rewrite flush
├── includes/
│   ├── class-llms-txt-generator.php    # Rewrite endpoints + request handling + cache
│   ├── class-llms-txt-renderer.php     # Builds the markdown body
│   ├── class-llms-txt-settings.php     # Options + admin settings screen
│   └── class-llms-txt-bot-logger.php   # Optional request logging
├── uninstall.php                       # Removes options/transients on uninstall
├── readme.txt                          # WordPress.org-style readme
├── LICENSE                             # GPL-2.0-or-later
└── README.md
```

## Developer notes

```php
// Force-exclude a post from the generated files.
add_filter( 'llms_txt_is_noindex', function ( $noindex, $post_id ) {
    return $post_id === 123 ? true : $noindex;
}, 10, 2 );

// Modify the final file body before it is served/cached.
add_filter( 'llms_txt_output', function ( $body ) {
    return $body . "\n<!-- custom footer -->\n";
} );
```

## Contributors

- [muratkoseonline](https://github.com/muratkoseonline)
- [Claude (Anthropic)](https://claude.ai) — AI pair programmer

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
