# AI Provider for Ollama

An AI Provider for [Ollama](https://ollama.com) (local LLMs) for the [PHP AI Client](https://github.com/WordPress/php-ai-client) SDK. Works as both a Composer package and a WordPress plugin.

> **Note:** This is a fork of the [AI Provider for OpenAI](https://github.com/WordPress/ai-provider-for-openai) plugin, adapted for local Ollama usage. The Ollama connector is registered automatically on plugin activation — there is no separate step in the Connector Settings screen.

## Requirements

- PHP 7.4 or higher
- When using with WordPress, requires WordPress 7.0 or higher
    - If using an older WordPress release, the [wordpress/php-ai-client](https://github.com/WordPress/php-ai-client) package must be installed

## Installation

### As a Composer Package

```bash
composer require guzmandrade-dev/ai-provider-for-ollama
```

### As a WordPress Plugin

1. Download the plugin files
2. Upload to `/wp-content/plugins/ai-provider-for-ollama/`
3. Ensure the PHP AI Client plugin is installed and activated
4. Activate the plugin through the WordPress admin

## Usage

### With WordPress

The provider automatically registers itself with the PHP AI Client on the `init` hook.

```php
// Optionally override the base URL (defaults to http://localhost:11434)
putenv('OLLAMA_BASE_URL=http://localhost:11434');

$result = AiClient::prompt('Hello, world!')
    ->usingProvider('ollama')
    ->generateTextResult();
```

### As a Standalone Package

```php
use WordPress\AiClient\AiClient;
use WordPress\OllamaAiProvider\Provider\OllamaProvider;

$registry = AiClient::defaultRegistry();
$registry->registerProvider(OllamaProvider::class);

// Optional: override base URL (defaults to http://localhost:11434)
putenv('OLLAMA_BASE_URL=http://localhost:11434');

$result = AiClient::prompt('Explain quantum computing')
    ->usingProvider('ollama')
    ->generateTextResult();

echo $result->toText();
```

## Supported Models

Available models are dynamically discovered from your local Ollama installation via the `/api/tags` endpoint. Only text generation models are supported in v1. Pull models with `ollama pull <model>` before use (e.g. `ollama pull mistral`).

## Configuration

### Ollama

Ollama requires no API key. By default the provider connects to `http://localhost:11434`. Override with the `OLLAMA_BASE_URL` environment variable:

```php
putenv('OLLAMA_BASE_URL=http://my-ollama-host:11434');
```

Ollama must be running and have at least one model pulled (`ollama pull <model>`) before the provider will report as available.

### Limitations (Ollama v1)

- Text generation only (image generation is not supported)
- Function calling, web search, and structured output schemas are not supported

## License

GPL-2.0-or-later
