<?php
/**
 * Updater — wires WordPress's update hooks to the License Manager update API.
 *
 * WordPress routes update checks for plugins with a custom `Update URI:` header
 * through the `update_plugins_<host>` filter, where <host> is the URI host. Our
 * URI host is the platform host, so we hook `update_plugins_shop.chadacreatives.com`.
 *
 * (Ported from chada-activity-monitor's Updater; the slug comes from
 * UpdateClient::PRODUCT_SLUG since this free product ships no LicenseClient.)
 *
 * @package Chada\Duplicate
 */

namespace Chada\Duplicate\Licensing;

defined( 'ABSPATH' ) || exit;

class Updater {

	/** @var UpdateClient Fetches + caches release metadata. */
	private $update_client;

	/**
	 * @param UpdateClient $update_client Release-metadata client.
	 */
	public function __construct( UpdateClient $update_client ) {
		$this->update_client = $update_client;
	}

	/**
	 * Register the WordPress update hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'update_plugins_' . self::platform_host(), array( $this, 'inject_update' ), 10, 4 );
		add_filter( 'plugins_api', array( $this, 'inject_plugin_info' ), 10, 3 );
		add_action( 'upgrader_process_complete', array( $this, 'flush_cache_on_install' ), 10, 2 );
	}

	/**
	 * Host of CHADA_DUP_PLATFORM_URL — must match the Update URI header host.
	 *
	 * @return string
	 */
	public static function platform_host() {
		if ( defined( 'CHADA_DUP_PLATFORM_URL' ) ) {
			$platform_host = wp_parse_url( (string) constant( 'CHADA_DUP_PLATFORM_URL' ), PHP_URL_HOST );
			if ( is_string( $platform_host ) && '' !== $platform_host ) {
				return $platform_host;
			}
		}
		return 'shop.chadacreatives.com';
	}

	/**
	 * `update_plugins_<host>` callback — advertises an available update to core.
	 *
	 * @param array|false $current_update Existing update array (or false).
	 * @param array       $plugin_data    Headers of the plugin being checked.
	 * @param string      $plugin_file    Plugin file path relative to the plugins dir.
	 * @param array       $locales        Requested locales.
	 * @return array|false
	 */
	public function inject_update( $current_update, $plugin_data, $plugin_file, $locales = array() ) {
		$release = $this->update_client->check();
		if ( ! $release || empty( $release['available'] ) ) {
			return $current_update;
		}

		return array(
			'slug'         => $release['slug'] ?? UpdateClient::PRODUCT_SLUG,
			'version'      => $release['version'] ?? '',
			'url'          => $release['homepage'] ?? '',
			'package'      => $release['download_url'] ?? '',
			'tested'       => $release['tested'] ?? '',
			'requires'     => $release['requires'] ?? '',
			'requires_php' => $release['requires_php'] ?? '',
			'icons'        => $release['icons'] ?? array(),
			'banners'      => $release['banners'] ?? array(),
		);
	}

	/**
	 * `plugins_api` callback — powers the "View details" modal.
	 *
	 * @param false|object|array $api_result Default API result.
	 * @param string             $action     Requested API action.
	 * @param object             $args       Request arguments (expects ->slug).
	 * @return false|object|array
	 */
	public function inject_plugin_info( $api_result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $api_result;
		}
		if ( empty( $args->slug ) || UpdateClient::PRODUCT_SLUG !== $args->slug ) {
			return $api_result;
		}

		$release = $this->update_client->check();
		if ( ! $release ) {
			return $api_result;
		}

		$release_sections = is_array( $release['sections'] ?? null ) ? $release['sections'] : array();

		return (object) array(
			'name'              => $release['name'] ?? 'Chada Duplicate',
			'slug'              => $release['slug'] ?? UpdateClient::PRODUCT_SLUG,
			'version'           => $release['version'] ?? '',
			'author'            => $release['author'] ?? '',
			'homepage'          => $release['homepage'] ?? '',
			'requires'          => $release['requires'] ?? '',
			'requires_php'      => $release['requires_php'] ?? '',
			'tested'            => $release['tested'] ?? '',
			'last_updated'      => $release['last_updated'] ?? '',
			'sections'          => $release_sections,
			'short_description' => $release_sections['description'] ?? '',
			'download_link'     => $release['download_url'] ?? '',
			'banners'           => $release['banners'] ?? array(),
			'icons'             => $release['icons'] ?? array(),
		);
	}

	/**
	 * Drop the cached check after a successful update of this plugin.
	 *
	 * @param mixed $upgrader      Upgrader instance (unused).
	 * @param array $process_options Details of the completed upgrade.
	 * @return void
	 */
	public function flush_cache_on_install( $upgrader, $process_options ) {
		if ( ( $process_options['action'] ?? '' ) !== 'update' || ( $process_options['type'] ?? '' ) !== 'plugin' ) {
			return;
		}
		foreach ( (array) ( $process_options['plugins'] ?? array() ) as $updated_plugin_file ) {
			if ( false !== strpos( (string) $updated_plugin_file, UpdateClient::PRODUCT_SLUG . '/' ) ) {
				$this->update_client->flush();
				return;
			}
		}
	}
}
