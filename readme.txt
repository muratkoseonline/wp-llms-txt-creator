=== LLMs.txt Generator ===
Contributors: muratkoseonline
Tags: llms.txt, ai, seo, llm, gpt
Requires at least: 5.5
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Serve a clean /llms.txt and /llms-full.txt so AI models can understand your site with less effort.

== Description ==

Large language models (ChatGPT, Claude, Gemini, Perplexity, …) crawl and process
websites to make sense of them — an expensive operation. A well-formed
`llms.txt` file gives them a curated, machine-friendly map of your most valuable
content so they spend less effort and represent your site more accurately.

This plugin generates and serves two files **live** from your site root:

* `/llms.txt` — title, summary, and a curated list of links (one section per content type) with descriptions.
* `/llms-full.txt` — the same map plus the full plain-text content of each item inline.

Both follow the structure proposed at llmstxt.org.

= Features =

* Dynamic generation — always in sync with your published content.
* One section per content type (Pages, Posts, custom post types).
* Absolute URLs and per-item descriptions (SEO meta description → excerpt → trimmed content).
* Respects `noindex` set by Yoast SEO, Rank Math and All in One SEO.
* Transient caching with automatic invalidation on content save.
* Optional "Optional" section for manually curated external links.
* Optional, IP-free logging of AI-bot requests to the files.
* Developer filters: `llms_txt_is_noindex`, `llms_txt_output`.

== Installation ==

1. Copy the `llms-txt-generator` folder into `wp-content/plugins/`.
2. Activate the plugin through the **Plugins** screen.
3. Make sure pretty permalinks are enabled (Settings → Permalinks).
4. Configure under **Settings → LLMs.txt**.
5. Visit `https://your-site.com/llms.txt` to confirm.

== Frequently Asked Questions ==

= The file returns 404 =

Pretty permalinks must be enabled. Go to Settings → Permalinks and click Save to
flush the rewrite rules.

= Does this guarantee AI models use my file? =

No. `llms.txt` is an emerging convention; no major provider formally commits to
consuming it yet. The file is low-cost to provide and improves how compliant
tools and a growing number of agents read your site.

== Changelog ==

= 1.0.0 =
* Initial release.
