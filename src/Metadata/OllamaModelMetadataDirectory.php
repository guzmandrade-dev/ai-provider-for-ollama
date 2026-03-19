<?php

declare(strict_types=1);

namespace WordPress\OllamaAiProvider\Metadata;

use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModelMetadataDirectory;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Http\Util\ResponseUtil;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;
use WordPress\OllamaAiProvider\Provider\OllamaProvider;

/**
 * Class for the Ollama model metadata directory.
 *
 * Discovers locally installed models by calling the Ollama /api/tags endpoint.
 * All discovered models are treated as text generation models, since Ollama does
 * not expose per-model capability information through its API.
 *
 * @since 1.1.0
 *
 * @phpstan-type OllamaTagsResponseData array{
 *     models?: list<array{name: string}>
 * }
 */
class OllamaModelMetadataDirectory extends AbstractApiBasedModelMetadataDirectory {

	/**
	 * {@inheritDoc}
	 *
	 * Calls GET /api/tags on the local Ollama instance and maps each model entry
	 * to a ModelMetadata object with text generation capabilities.
	 *
	 * @since 1.1.0
	 */
	protected function sendListModelsRequest(): array {
		$request = new Request(
			HttpMethodEnum::GET(),
			OllamaProvider::url( 'api/tags' ),
			array( 'Accept' => 'application/json' )
		);

		// Ollama requires no authentication; do not call getRequestAuthentication().
		$response = $this->getHttpTransporter()->send( $request );
		ResponseUtil::throwIfNotSuccessful( $response );

		/** @var OllamaTagsResponseData $responseData */
		$responseData = $response->getData();
		if ( ! isset( $responseData['models'] ) || ! is_array( $responseData['models'] ) ) {
			throw ResponseException::fromMissingData( 'Ollama', 'models' );
		}

		$capabilities = array(
			CapabilityEnum::textGeneration(),
			CapabilityEnum::chatHistory(),
		);
		$options      = array(
			new SupportedOption( OptionEnum::systemInstruction() ),
			new SupportedOption( OptionEnum::maxTokens() ),
			new SupportedOption( OptionEnum::temperature() ),
			new SupportedOption( OptionEnum::topP() ),
			new SupportedOption( OptionEnum::stopSequences() ),
			new SupportedOption( OptionEnum::customOptions() ),
			new SupportedOption( OptionEnum::inputModalities(), array( array( ModalityEnum::text() ) ) ),
			new SupportedOption( OptionEnum::outputModalities(), array( array( ModalityEnum::text() ) ) ),
		);

		$models = array();
		foreach ( $responseData['models'] as $modelData ) {
			if ( ! isset( $modelData['name'] ) || ! is_string( $modelData['name'] ) || $modelData['name'] === '' ) {
				continue;
			}
			$modelId            = $modelData['name'];
			$models[ $modelId ] = new ModelMetadata(
				$modelId,
				$modelId, // Ollama does not provide a separate display name.
				$capabilities,
				$options
			);
		}

		uasort( $models, array( $this, 'modelSortCallback' ) );

		return $models;
	}

	/**
	 * Callback function for sorting models by name, to be used with `uasort()`.
	 *
	 * Prefers models without a tag qualifier (e.g. "mistral" over "mistral:latest")
	 * and sorts alphabetically otherwise.
	 *
	 * @since 1.1.0
	 *
	 * @param ModelMetadata $a First model.
	 * @param ModelMetadata $b Second model.
	 * @return int Comparison result.
	 */
	protected function modelSortCallback( ModelMetadata $a, ModelMetadata $b ): int {
		$aId = $a->getId();
		$bId = $b->getId();

		// Prefer models without a tag qualifier (e.g. 'mistral' over 'mistral:latest').
		$aHasTag = str_contains( $aId, ':' );
		$bHasTag = str_contains( $bId, ':' );
		if ( ! $aHasTag && $bHasTag ) {
			return -1;
		}
		if ( $aHasTag && ! $bHasTag ) {
			return 1;
		}

		// Fallback: sort alphabetically.
		return strcmp( $aId, $bId );
	}
}
