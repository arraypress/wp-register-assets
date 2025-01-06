<?php
/**
 * Asset Registration Helper
 *
 * Provides a simplified interface for registering WordPress scripts and styles.
 * This helper function wraps the RegisterAssets class to provide a quick way to register
 * multiple assets at once with error handling and validation.
 *
 * Example usage:
 * ```php
 * $assets = [
 *     [
 *         'handle' => 'my-script',
 *         'src'    => 'js/script.js',     // Will auto-detect as script
 *         'deps'   => ['jquery'],
 *         'async'  => true
 *     ],
 *     [
 *         'handle' => 'my-style',
 *         'src'    => 'css/style.css',    // Will auto-detect as style
 *         'media'  => 'all'
 *     ]
 * ];
 *
 * $config = [
 *     'debug'          => WP_DEBUG,       // Enable debug mode
 *     'minify'         => true,           // Enable minification
 *     'assets_url'     => 'dist/assets',  // Custom assets directory
 *     'version'        => '1.0.0',        // Asset version
 * ];
 *
 * register_assets( __FILE__, $assets, $config );
 * ```
 *
 * @package     ArrayPress/Utils/Register
 * @copyright   Copyright (c) 2024, ArrayPress Limited
 * @license     GPL2+
 * @version     2.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

use ArrayPress\WP\Register\Assets;

if ( ! function_exists( 'register_assets' ) ):
	/**
	 * Register custom assets (scripts and styles) for WordPress.
	 *
	 * @param string        $file           Main plugin/theme file path
	 * @param array         $assets         Array of assets to register
	 * @param array         $config         Optional. Configuration settings for the RegisterAssets manager.
	 *                                      Supports all options from RegisterAssets::parse_config()
	 * @param callable|null $error_callback Optional. Callback function for error handling
	 *
	 * @return Assets|WP_Error|null RegisterAssets manager instance, WP_Error on validation failure,
	 *                                      or null on exception
	 */
	function register_assets(
		string $file,
		array $assets,
		array $config = [],
		?callable $error_callback = null
	) {
		try {
			// Validate file exists
			if ( ! file_exists( $file ) ) {
				throw new InvalidArgumentException( "Invalid file path: {$file}" );
			}

			// Basic asset validation
			foreach ( $assets as $asset ) {
				if ( empty( $asset['handle'] ) || empty( $asset['src'] ) ) {
					return new WP_Error(
						'invalid_asset',
						'Each asset must have a handle and src defined',
						$asset
					);
				}
			}

			// Initialize the RegisterAssets manager
			$manager = new Assets( $file, $config );

			// Apply any specific configuration settings
			if ( ! empty( $config['debug'] ) ) {
				$manager->set_debug( $config['debug'] );
			}

			if ( isset( $config['minify'] ) ) {
				$manager->set_minify( $config['minify'] );
			}

			if ( ! empty( $config['assets_url'] ) ) {
				$manager->set_assets_directory( $config['assets_url'] );
			}

			// Register all assets at once
			if ( ! empty( $assets ) ) {
				$result = $manager->register( $assets );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
			}

			return $manager;
		} catch ( Exception $e ) {
			if ( is_callable( $error_callback ) ) {
				call_user_func( $error_callback, $e );
			}

			return null;
		}
	}
endif;