# ConsensusPress by Seekrates AI

**AI-search resilient WordPress content infrastructure.**  
Five LLMs validate every post. One consensus. Zero hallucination drift.

---

## What it does

ConsensusPress is a WordPress plugin that connects to the [Seekrates AI](https://seekrates-ai.com) consensus engine. Before a word reaches your WordPress editor, five leading LLMs — OpenAI, Claude, Gemini, Mistral, and Cohere — are queried simultaneously. Their responses are cross-validated, hallucinations filtered, and a quality-scored consensus is delivered as a WordPress draft.

This is not an AI writer. It is **content infrastructure**.

---

## Two modes

**Create** — Enter a topic. The consensus engine queries five LLMs, scores responses, resolves conflicts, and creates a WordPress draft with SEO metadata, schema markup, and a featured image.

**Rescue** — Select an existing post. The engine restructures it for AI-search survival (GEO / LLMO / AEO), then creates a new draft alongside a before/after diff viewer.

---

## Why cross-model consensus

Single-LLM content has a structural weakness: one model's blind spot becomes your published content's blind spot. Cross-model validation catches what individual models miss — factual drift, structural gaps, hallucinated citations.

**164 published posts. 81+ average Rank Math score. Methodology validated at scale.**

---

## Features

- 5-LLM simultaneous query (OpenAI, Claude, Gemini, Mistral, Cohere)
- Quality scoring + 12 refusal pattern filters
- Oracle Risk Analysis on every response
- Rank Math SEO integration
- JSON-LD schema injection
- Unsplash featured image (automatic, keyword-matched)
- Async WP-Cron processing — no browser timeouts
- Consensus meta box on every draft
- Usage tracking + tier enforcement
- Before/after diff viewer (Rescue mode)

---

## Architecture

The plugin is a **thin API client**. All LLM intelligence runs server-side on the Seekrates AI Railway endpoint. No API keys for individual LLMs are required in WordPress. This protects both IP and security surface.

```
WordPress plugin  →  POST /api/v1/cp/consensus  →  Seekrates AI (Railway)
                                                        ↓
                                              5 LLMs queried in parallel
                                                        ↓
                                              Consensus scored + filtered
                                                        ↓
                  WordPress draft  ←  JSON response with full content payload
```

---

## Requirements

- WordPress 6.2+
- PHP 7.4+
- Seekrates AI account + API key ([seekrates-ai.com](https://seekrates-ai.com))

---

## Installation

1. Upload the `consensuspress` folder to `/wp-content/plugins/`
2. Activate via **Plugins → Installed Plugins**
3. Go to **Settings → ConsensusPress**
4. Enter your Seekrates AI API key
5. (Optional) Enter your Unsplash API key for automatic featured images

Or install directly from the [WordPress.org plugin directory](https://wordpress.org/plugins/consensuspress/).

---

## Development

```bash
# Install dependencies
cd plugin/consensuspress
composer install

# Run test suite
cd /path/to/ConsensusPress
plugin/consensuspress/vendor/bin/phpunit --configuration tests/phpunit.xml
```

**70 tests. 153 assertions. PHPUnit 9.**

---

## License

GPL v2 or later — see [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html)

---

## About Seekrates AI

Seekrates AI builds AI-search resilient content infrastructure for WordPress publishers, agencies, and SaaS teams. [seekrates-ai.com](https://seekrates-ai.com)