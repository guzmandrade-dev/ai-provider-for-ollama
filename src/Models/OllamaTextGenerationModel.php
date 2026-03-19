<?php

declare(strict_types=1);

namespace WordPress\OllamaAiProvider\Models;

use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModel;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Http\Util\ResponseUtil;
use WordPress\AiClient\Providers\Models\TextGeneration\Contracts\TextGenerationModelInterface;
use WordPress\AiClient\Results\DTO\Candidate;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\AiClient\Results\DTO\TokenUsage;
use WordPress\AiClient\Results\Enums\FinishReasonEnum;
use WordPress\OllamaAiProvider\Provider\OllamaProvider;

/**
 * Class for an Ollama text generation model using the /api/chat endpoint.
 *
 * Sends a non-streaming chat request to the local Ollama instance and maps
 * the response back to a GenerativeAiResult. Unsupported v1 features
 * (function declarations, web search, output schema) throw immediately to
 * prevent silent mismatches.
 *
 * @since 1.1.0
 *
 * @phpstan-type OllamaMessageData array{role: string, content: string}
 * @phpstan-type OllamaResponseData array{
 *     model?: string,
 *     message?: array{role?: string, content?: string},
 *     done?: bool,
 *     done_reason?: string,
 *     eval_count?: int,
 *     prompt_eval_count?: int
 * }
 */
class OllamaTextGenerationModel extends AbstractApiBasedModel implements TextGenerationModelInterface {

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 */
	final public function generateTextResult( array $prompt ): GenerativeAiResult {
		$params = $this->prepareGenerateTextParams( $prompt );

		$request = new Request(
			HttpMethodEnum::POST(),
			OllamaProvider::url( 'api/chat' ),
			array( 'Content-Type' => 'application/json' ),
			$params,
			$this->getRequestOptions()
		);

		// Ollama requires no authentication; do not call getRequestAuthentication().
		$response = $this->getHttpTransporter()->send( $request );
		ResponseUtil::throwIfNotSuccessful( $response );
		return $this->parseResponseToGenerativeAiResult( $response );
	}

	/**
	 * Prepares the given prompt and model configuration into parameters for the /api/chat request.
	 *
	 * @since 1.1.0
	 *
	 * @param list<Message> $prompt The prompt to generate text for.
	 * @return array<string, mixed> The parameters for the API request.
	 * @throws InvalidArgumentException If the configuration includes features not supported by Ollama.
	 */
	protected function prepareGenerateTextParams( array $prompt ): array {
		$config = $this->getConfig();

		// Fail fast for features not supported in Ollama v1.
		if ( $config->getFunctionDeclarations() !== null ) {
			throw new InvalidArgumentException(
				'The Ollama provider does not support function declarations.'
			);
		}
		if ( $config->getWebSearch() !== null ) {
			throw new InvalidArgumentException(
				'The Ollama provider does not support web search.'
			);
		}
		if ( $config->getOutputSchema() !== null ) {
			throw new InvalidArgumentException(
				'The Ollama provider does not support output schema.'
			);
		}

		$messages = $this->prepareMessagesParam( $prompt );

		// Prepend system instruction as a system role message.
		$systemInstruction = $config->getSystemInstruction();
		if ( $systemInstruction !== null && $systemInstruction !== '' ) {
			array_unshift(
				$messages,
				array(
					'role'    => 'system',
					'content' => $systemInstruction,
				)
			);
		}

		$params = array(
			'model'    => $this->metadata()->getId(),
			'messages' => $messages,
			'stream'   => false,
		);

		// Map supported config options to Ollama's options object.
		$options = array();

		$maxTokens = $config->getMaxTokens();
		if ( $maxTokens !== null ) {
			$options['num_predict'] = $maxTokens;
		}

		$temperature = $config->getTemperature();
		if ( $temperature !== null ) {
			$options['temperature'] = $temperature;
		}

		$topP = $config->getTopP();
		if ( $topP !== null ) {
			$options['top_p'] = $topP;
		}

		$stopSequences = $config->getStopSequences();
		if ( $stopSequences ) {
			$options['stop'] = $stopSequences;
		}

		// Pass through any custom options into the Ollama options object.
		foreach ( $config->getCustomOptions() as $key => $value ) {
			if ( isset( $options[ $key ] ) ) {
				throw new InvalidArgumentException(
					sprintf(
						'The custom option "%s" conflicts with an existing Ollama parameter.',
						$key
					)
				);
			}
			$options[ $key ] = $value;
		}

		if ( ! empty( $options ) ) {
			$params['options'] = $options;
		}

		return $params;
	}

	/**
	 * Converts the SDK message list to Ollama chat messages.
	 *
	 * Only text message parts are supported. File, function call, and function
	 * response parts throw immediately rather than being silently ignored.
	 *
	 * @since 1.1.0
	 *
	 * @param list<Message> $messages The messages to convert.
	 * @return list<OllamaMessageData> The Ollama-formatted messages.
	 * @throws InvalidArgumentException If an unsupported message part type is encountered.
	 */
	protected function prepareMessagesParam( array $messages ): array {
		$ollamaMessages = array();
		foreach ( $messages as $message ) {
			$parts = $message->getParts();
			if ( empty( $parts ) ) {
				continue;
			}

			$role        = $message->getRole()->isModel() ? 'assistant' : 'user';
			$textContent = '';

			foreach ( $parts as $part ) {
				$type = $part->getType();
				if ( $type->isText() ) {
					$textContent .= $part->getText();
					continue;
				}
				if ( $type->isFunctionCall() ) {
					throw new InvalidArgumentException(
						'The Ollama provider does not support function call message parts.'
					);
				}
				if ( $type->isFunctionResponse() ) {
					throw new InvalidArgumentException(
						'The Ollama provider does not support function response message parts.'
					);
				}
				if ( $type->isFile() ) {
					throw new InvalidArgumentException(
						'The Ollama provider does not support file message parts in this version.'
					);
				}
				throw new InvalidArgumentException(
					sprintf( 'Unsupported message part type "%s".', $type )
				);
			}

			if ( $textContent !== '' ) {
				$ollamaMessages[] = array(
					'role'    => $role,
					'content' => $textContent,
				);
			}
		}
		return $ollamaMessages;
	}

	/**
	 * Parses the /api/chat response into a GenerativeAiResult.
	 *
	 * @since 1.1.0
	 *
	 * @param Response $response The response from the Ollama API.
	 * @return GenerativeAiResult The parsed generative AI result.
	 */
	protected function parseResponseToGenerativeAiResult( Response $response ): GenerativeAiResult {
		/** @var OllamaResponseData $responseData */
		$responseData = $response->getData();

		if ( ! isset( $responseData['message'] ) || ! is_array( $responseData['message'] ) ) {
			throw ResponseException::fromMissingData( 'Ollama', 'message' );
		}

		$content = isset( $responseData['message']['content'] ) && is_string( $responseData['message']['content'] )
			? $responseData['message']['content']
			: '';

		// Map done_reason to a FinishReasonEnum value.
		$doneReason = $responseData['done_reason'] ?? 'stop';
		if ( $doneReason === 'length' ) {
			$finishReason = FinishReasonEnum::length();
		} else {
			$finishReason = FinishReasonEnum::stop();
		}

		$part      = new MessagePart( $content );
		$message   = new Message( MessageRoleEnum::model(), array( $part ) );
		$candidate = new Candidate( $message, $finishReason );

		$promptTokens     = isset( $responseData['prompt_eval_count'] ) && is_int( $responseData['prompt_eval_count'] )
			? $responseData['prompt_eval_count']
			: 0;
		$completionTokens = isset( $responseData['eval_count'] ) && is_int( $responseData['eval_count'] )
			? $responseData['eval_count']
			: 0;
		$tokenUsage       = new TokenUsage( $promptTokens, $completionTokens, $promptTokens + $completionTokens );

		// Strip known fields from the response to keep only provider-specific metadata.
		$additionalData = $responseData;
		unset(
			$additionalData['message'],
			$additionalData['done_reason'],
			$additionalData['eval_count'],
			$additionalData['prompt_eval_count']
		);

		return new GenerativeAiResult(
			'',
			array( $candidate ),
			$tokenUsage,
			$this->providerMetadata(),
			$this->metadata(),
			$additionalData
		);
	}
}
