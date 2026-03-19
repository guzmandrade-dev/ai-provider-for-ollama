<?php

/**
 * Plugin Name: AI Provider for Ollama
 * Plugin URI: https://github.com/guzmandrade-dev/ai-provider-for-ollama
 * Description: AI Provider for Ollama for the WordPress AI Client.
 * Requires at least: 6.9
 * Requires PHP: 7.4
 * Version: 1.0.0
 * Author: WordPress AI Team
 * Author URI: https://make.wordpress.org/ai/
 * License: GPL-2.0-or-later
 * License URI: https://spdx.org/licenses/GPL-2.0-or-later.html
 * Text Domain: ai-provider-for-ollama
 *
 * @package WordPress\OllamaAiProvider
 */

declare(strict_types=1);

namespace WordPress\OllamaAiProvider;

use WordPress\AiClient\AiClient;
use WordPress\OllamaAiProvider\Provider\OllamaProvider;

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

require_once __DIR__ . '/src/autoload.php';

/**
 * Allows local Ollama host requests through WordPress safe HTTP validation.
 *
 * WordPress AI in core currently routes SDK HTTP calls through wp_safe_remote_request(),
 * which rejects local/private hosts unless explicitly allowed via filters.
 * This callback only whitelists the configured Ollama host.
 *
 * @since 1.1.0
 *
 * @param bool   $is_external Whether the host is already considered external.
 * @param string $host        Request host being validated.
 * @param string $url         Full request URL.
 * @return bool Whether to allow this host as external.
 */
function allow_ollama_http_request_host( bool $is_external, string $host, string $url ): bool {
	$ollamaBaseUrl = OllamaProvider::url();
	$ollamaParts   = wp_parse_url( $ollamaBaseUrl );
	if ( ! is_array( $ollamaParts ) || ! isset( $ollamaParts['host'] ) || ! is_string( $ollamaParts['host'] ) ) {
		return $is_external;
	}

	$ollamaHost = strtolower( $ollamaParts['host'] );
	if ( strtolower( $host ) !== $ollamaHost ) {
		return $is_external;
	}

	$requestedParts = wp_parse_url( $url );
	if ( ! is_array( $requestedParts ) || ! isset( $requestedParts['host'] ) || ! is_string( $requestedParts['host'] ) ) {
		return $is_external;
	}

	if ( strtolower( $requestedParts['host'] ) !== $ollamaHost ) {
		return $is_external;
	}

	return true;
}

/**
 * Allows the configured Ollama port in WordPress safe HTTP validation.
 *
 * @since 1.1.0
 *
 * @param int[]  $allowed_ports Allowed safe ports.
 * @param string $host          Request host being validated.
 * @param string $url           Full request URL.
 * @return int[] Updated allowed safe ports.
 */
function allow_ollama_http_request_port( array $allowed_ports, string $host, string $url ): array {
	$ollamaBaseUrl = OllamaProvider::url();
	$ollamaParts   = wp_parse_url( $ollamaBaseUrl );
	if ( ! is_array( $ollamaParts ) || ! isset( $ollamaParts['host'] ) || ! is_string( $ollamaParts['host'] ) ) {
		return $allowed_ports;
	}

	$ollamaHost = strtolower( $ollamaParts['host'] );
	if ( strtolower( $host ) !== $ollamaHost ) {
		return $allowed_ports;
	}

	$requestedParts = wp_parse_url( $url );
	if ( ! is_array( $requestedParts ) || ! isset( $requestedParts['host'] ) || ! is_string( $requestedParts['host'] ) ) {
		return $allowed_ports;
	}

	if ( strtolower( $requestedParts['host'] ) !== $ollamaHost ) {
		return $allowed_ports;
	}

	if ( ! isset( $ollamaParts['port'] ) ) {
		return $allowed_ports;
	}

	$ollamaPort = (int) $ollamaParts['port'];
	if ( $ollamaPort > 0 && ! in_array( $ollamaPort, $allowed_ports, true ) ) {
		$allowed_ports[] = $ollamaPort;
	}

	return $allowed_ports;
}

/**
 * Registers the AI Provider for Ollama with the AI Client.
 *
 * @since 1.0.0
 *
 * @return void
 */
function register_provider(): void {
	if ( ! class_exists( AiClient::class ) ) {
		return;
	}

	$registry = AiClient::defaultRegistry();

	if ( ! $registry->hasProvider( OllamaProvider::class ) ) {
		$registry->registerProvider( OllamaProvider::class );
	}
}

add_action( 'init', __NAMESPACE__ . '\\register_provider', 5 );
add_filter( 'http_request_host_is_external', __NAMESPACE__ . '\\allow_ollama_http_request_host', 10, 3 );
add_filter( 'http_allowed_safe_ports', __NAMESPACE__ . '\\allow_ollama_http_request_port', 10, 3 );
