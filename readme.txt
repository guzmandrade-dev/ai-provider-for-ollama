=== AI Provider for Ollama ===
Contributors: wordpressdotorg, h4l9k
Tags: ai, ollama, llm, local-ai, connector
Requires at least: 6.9
Tested up to: 7.0
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI Provider for Ollama (local LLMs) for the PHP AI Client SDK.

== Description ==

This plugin provides Ollama integration for the PHP AI Client SDK. It enables WordPress sites to use locally hosted Ollama models for text generation.

**Note:** This plugin is a fork of the [AI Provider for OpenAI](https://github.com/WordPress/ai-provider-for-openai) connector, adapted for local Ollama usage. Unlike connectors that are registered through the Connector Settings screen, this provider is registered automatically when the plugin is activated — no additional configuration step is required in the admin UI.

**Features:**

* Text generation with locally installed Ollama models
* Automatic provider registration

Available models are dynamically discovered at runtime from the local Ollama installation (`/api/tags`).

**Requirements:**

* PHP 7.4 or higher
* For WordPress 6.9, the [wordpress/php-ai-client](https://github.com/WordPress/php-ai-client) package must be installed
* For WordPress 7.0 and above, no additional changes are required
* A running [Ollama](https://ollama.com) instance with at least one model pulled

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/ai-provider-for-ollama/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure `OLLAMA_BASE_URL` as needed (defaults to `http://localhost:11434`)

== Frequently Asked Questions ==

= How do I use the Ollama provider? =

Install and run [Ollama](https://ollama.com) locally, then pull one or more models (e.g. `ollama pull mistral`). No API key is required. By default the plugin connects to `http://localhost:11434`. Override the URL with the `OLLAMA_BASE_URL` environment variable.

= What Ollama features are supported in v1? =

Text generation only. Function calling, web search, image generation, and structured output schemas are not supported in the initial release.

= Does this plugin work without the PHP AI Client? =

No, this plugin requires the PHP AI Client plugin to be installed and activated. It provides the Ollama-specific implementation that the PHP AI Client uses.

== Changelog ==

= 1.0.0 =

* Initial release, forked from the [AI Provider for OpenAI](https://github.com/WordPress/ai-provider-for-openai) connector
* Text generation with locally installed Ollama models via the `/api/chat` endpoint
* Automatic model discovery via the `/api/tags` endpoint
* No API key required — connects to a local Ollama instance
* Automatic provider registration on plugin activation
