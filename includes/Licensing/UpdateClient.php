<?php
/**
 * UpdateClient — calls the License Manager `/update-check` endpoint and caches
 * the result for WordPress's plugin-update flow.
 *
 * Chada Duplicate is a FREE product: it ships the Updater only, with no license
 * gating. We therefore send no license key — the marketplace serves the
 * `chada-duplicate` slug as a free product. (Ported from chada-activity-monitor's
 * UpdateClient, with the LicenseClient dependency removed.)
 *
 * @package Chada\Duplicate
 */

namespace Chada\Duplicate\Licensing;

defined( 'ABSPATH' ) || exit;

class UpdateClient {

	/** Marketplace product slug — must match the slug registered in CLM. */
	const PRODUCT_SLUG = 'chada-duplicate';

	const ROUTE_UPDATE_CHECK = '/wp-json/rfd-license/v1/update-check';

	const SUCCESS_TTL = 43200; // 12h.
	const FAILURE_TTL = 3600;  // 1h.

	const CACHE_KEY = 'cdup_update_check';

	/**
	 * Fetch the latest release metadata. Returns null when unreachable or no
	 * release is published — the caller treats null as "no update available".
	 *
	 * @param bool $force_refresh Bypass the cache.
	 * @return array<string,mixed>|null
	 */
	public function check( $force_refresh = false ) {
		if ( ! $force_refresh ) {
			$cached_response = get_transient( self::CACHE_KEY );
			if ( is_array( $cached_response ) ) {
				return $cached_response ? $cached_response : null;
			}
		}

		$update_response = $this->post(
			self::ROUTE_UPDATE_CHECK,
			array(
				'slug'            => self::PRODUCT_SLUG,
				'site_url'        => home_url(),
				'current_version' => defined( 'CHADA_DUP_VERSION' ) ? CHADA_DUP_VERSION : '0.0.0',
			)
		);

		if ( null === $update_response ) {
			set_transient( self::CACHE_KEY, array(), self::FAILURE_TTL );
			return null;
		}

		set_transient( self::CACHE_KEY, $update_response, self::SUCCESS_TTL );
		return $update_response;
	}

	/**
	 * Drop the cache (e.g. after an install, or on (de)activation).
	 *
	 * @return void
	 */
	public function flush() {
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * POST a JSON request to the marketplace and decode a successful response.
	 *
	 * @param string              $route        Path appended to the server URL.
	 * @param array<string,mixed> $request_body Form fields to send.
	 * @return array<string,mixed>|null Decoded body on success, null otherwise.
	 */
	private function post( $route, array $request_body ) {
		$endpoint_url = $this->server_url() . $route;

		$http_result = wp_remote_post(
			$endpoint_url,
			array(
				'timeout' => 10,
				'headers' => array( 'Accept' => 'application/json' ),
				'body'    => $request_body,
			)
		);

		if ( is_wp_error( $http_result ) ) {
			return null;
		}

		$decoded_body = json_decode( wp_remote_retrieve_body( $http_result ), true );
		if ( ! is_array( $decoded_body ) || empty( $decoded_body['success'] ) ) {
			return null;
		}
		return $decoded_body;
	}

	/**
	 * Resolve the marketplace origin. Honours an explicit override constant,
	 * then the platform URL, then the production default.
	 *
	 * @return string Origin with no trailing slash.
	 */
	private function server_url() {
		if ( defined( 'CHADA_DUP_LICENSE_SERVER' ) ) {
			$override_url = constant( 'CHADA_DUP_LICENSE_SERVER' );
			if ( is_string( $override_url ) && '' !== $override_url ) {
				return rtrim( $override_url, '/' );
			}
		}
		if ( defined( 'CHADA_DUP_PLATFORM_URL' ) ) {
			return rtrim( (string) constant( 'CHADA_DUP_PLATFORM_URL' ), '/' );
		}
		return 'https://shop.chadacreatives.com';
	}
}
