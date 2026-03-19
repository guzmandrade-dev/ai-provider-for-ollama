<?php

declare(strict_types=1);

namespace WordPress\OllamaAiProvider\Provider;

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\ApiBasedImplementation\ListModelsApiBasedProviderAvailability;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\OllamaAiProvider\Metadata\OllamaModelMetadataDirectory;
use WordPress\OllamaAiProvider\Models\OllamaTextGenerationModel;

/**
 * Class for the AI Provider for Ollama (local LLM runtime).
 *
 * Connects to a locally running Ollama instance. The base URL can be configured
 * via the OLLAMA_BASE_URL constant or environment variable; it defaults to http://localhost:11434.
 *
 * No API key or other authentication is required for a standard local Ollama installation.
 *
 * @since 1.1.0
 */
class OllamaProvider extends AbstractApiProvider {

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 */
	protected static function baseUrl(): string {
		$envUrl = defined( 'OLLAMA_BASE_URL' ) ? OLLAMA_BASE_URL : getenv( 'OLLAMA_BASE_URL' );
		if ( is_string( $envUrl ) && $envUrl !== '' ) {
			return rtrim( $envUrl, '/' );
		}

		return 'http://localhost:11434';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 */
	protected static function createModel(
		ModelMetadata $modelMetadata,
		ProviderMetadata $providerMetadata
	): ModelInterface {
		$capabilities = $modelMetadata->getSupportedCapabilities();
		foreach ( $capabilities as $capability ) {
			if ( $capability->isTextGeneration() ) {
				return new OllamaTextGenerationModel( $modelMetadata, $providerMetadata );
			}
		}

		throw new RuntimeException(
			'Unsupported model capabilities: ' . implode( ', ', $capabilities )
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 */
	protected static function createProviderMetadata(): ProviderMetadata {
		$providerMetadataArgs = array(
			'ollama',
			'Ollama',
			ProviderTypeEnum::server(),
			'https://ollama.com',
			null, // No authentication required for a local Ollama installation.
		);
		// Provider description support was added in 1.2.0.
		if ( version_compare( AiClient::VERSION, '1.2.0', '>=' ) ) {
			// For WordPress, we should translate the description.
			if ( function_exists( '__' ) ) {
                // phpcs:ignore Generic.Files.LineLength.TooLong
				$providerMetadataArgs[] = __( 'Local text generation with Ollama models.', 'ai-provider-for-ollama' );
			} else {
				$providerMetadataArgs[] = 'Local text generation with Ollama models.';
			}
		}
		return new ProviderMetadata( ...$providerMetadataArgs );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 */
	protected static function createProviderAvailability(): ProviderAvailabilityInterface {
		// Check availability by attempting to list locally installed models.
		return new ListModelsApiBasedProviderAvailability(
			static::modelMetadataDirectory()
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 */
	protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface {
		return new OllamaModelMetadataDirectory();
	}
}
