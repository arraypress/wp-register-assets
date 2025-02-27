<?php
/**
 * Asset Manager for WordPress
 *
 * A streamlined approach to managing WordPress scripts and styles with features like:
 * - Automatic path resolution
 * - Conditional loading
 * - Debug mode support
 * - Minification handling
 * - Screen-specific loading
 * - Script localization
 * - Async/Defer support
 * - Error handling using WP_Error
 * - Version hash generation
 * - Inline script/style support
 * - Dependency management
 *
 * @package     ArrayPress/Utils/Register
 * @copyright   Copyright (c) 2024, ArrayPress Limited
 * @license     GPL2+
 * @version     2.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\WP\Register;

use InvalidArgumentException;
use WP_Error;

/**
 * Class Assets
 *
 * Register and manage custom scripts and styles with optional conditional loading.
 */
class Assets {

	/**
	 * Default configuration settings
	 *
	 * @var array
	 */
	protected array $config;

	/**
	 * Collection of registered scripts
	 *
	 * @var AssetCollection
	 */
	protected AssetCollection $scripts;

	/**
	 * Collection of registered styles
	 *
	 * @var AssetCollection
	 */
	protected AssetCollection $styles;

	/**
	 * Initialize the Asset Manager
	 *
	 * @param string $file   Main plugin/theme file path
	 * @param array  $config Configuration settings
	 *
	 * @throws InvalidArgumentException If file path is invalid
	 */
	public function __construct( string $file, array $config = [] ) {
		if ( ! file_exists( $file ) ) {
			throw new InvalidArgumentException( "Invalid file path: {$file}" );
		}

		$this->config  = $this->parse_config( $file, $config );
		$this->scripts = new AssetCollection();
		$this->styles  = new AssetCollection();

		$this->setup_hooks();
	}

	/**
	 * Parse and merge configuration with defaults
	 *
	 * @param string $file   Main file path
	 * @param array  $config User configuration
	 *
	 * @return array Complete configuration array
	 */
	protected function parse_config( string $file, array $config ): array {
		$defaults = [
			'file'        => $file,
			'url'         => plugin_dir_url( $file ),
			'path'        => plugin_dir_path( $file ),
			'version'     => $this->is_debug() ? time() : '1.0.0',
			'debug'       => $this->is_debug(),
			'minify'      => false,
			'scope'       => 'both',
			'screens'     => [],
			'assets_url'  => 'assets',
			'script_deps' => [],
			'style_deps'  => [],
			'in_footer'   => true,
			'media'       => 'all'
		];

		return wp_parse_args( $config, $defaults );
	}

	/**
	 * Set the base directory for assets
	 *
	 * @param string $directory Base directory path relative to plugin/theme root
	 *
	 * @return self
	 */
	public function set_assets_directory( string $directory ): self {
		$this->config['assets_url'] = trim( $directory, '/' );

		return $this;
	}

	/**
	 * Set debug mode
	 *
	 * @param bool $debug Whether to enable debug mode
	 *
	 * @return self
	 */
	public function set_debug( bool $debug ): self {
		$this->config['debug']   = $debug;
		$this->config['version'] = $debug ? time() : $this->config['version'];

		return $this;
	}

	/**
	 * Set minification mode
	 *
	 * @param bool $minify Whether to enable minification
	 *
	 * @return self
	 */
	public function set_minify( bool $minify ): self {
		$this->config['minify'] = $minify;

		return $this;
	}

	/**
	 * Check if debug mode is enabled
	 *
	 * @return bool Whether debug mode is enabled
	 */
	protected function is_debug(): bool {
		if ( isset( $this->config['debug'] ) ) {
			return $this->config['debug'];
		}

		return defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG;
	}

	/**
	 * Handle errors consistently throughout the class
	 *
	 * @param string $message Error message
	 * @param array  $data    Additional error data
	 *
	 * @return WP_Error
	 */
	protected function handle_error( string $message, array $data = [] ): WP_Error {
		return new WP_Error( 'asset_error', $message, $data );
	}

	/**
	 * Validate asset parameters
	 *
	 * @param string $handle Asset handle
	 * @param string $src    Asset source
	 * @param array  $args   Asset arguments
	 *
	 * @return WP_Error|null WP_Error on failure, null on success
	 */
	protected function validate_asset( string $handle, string $src, array $args = [] ): ?WP_Error {
		if ( empty( $handle ) ) {
			return $this->handle_error( 'Asset handle is required' );
		}

		if ( empty( $src ) ) {
			return $this->handle_error( 'Asset source is required' );
		}

		if ( ! empty( $args['screens'] ) && ! is_array( $args['screens'] ) ) {
			return $this->handle_error( 'Screens must be an array' );
		}

		return null;
	}

	/**
	 * Generate version hash for an asset file
	 *
	 * @param string $file_path Path to the asset file
	 *
	 * @return string Version hash or default version
	 */
	protected function generate_version( string $file_path ): string {
		if ( file_exists( $file_path ) ) {
			return hash_file( 'md5', $file_path );
		}

		return $this->config['version'];
	}

	/**
	 * Setup WordPress hooks for asset enqueuing
	 */
	protected function setup_hooks(): void {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend' ] );
	}

	/**
	 * Register a script
	 *
	 * @param string $handle Script handle
	 * @param string $src    Script source path
	 * @param array  $args   Additional arguments
	 *
	 * @return self|WP_Error
	 */
	public function script( string $handle, string $src, array $args = [] ) {
		if ( $error = $this->validate_asset( $handle, $src, $args ) ) {
			return $error;
		}

		$defaults = [
			'deps'      => $this->config['script_deps'],
			'version'   => $this->config['version'],
			'in_footer' => $this->config['in_footer'],
			'scope'     => $this->config['scope'],
			'screens'   => $this->config['screens'],
			'async'     => false,
			'defer'     => false,
			'localize'  => null
		];

		$args         = wp_parse_args( $args, $defaults );
		$args['src']  = $this->resolve_path( $src );
		$args['type'] = 'script';

		// Generate version hash if file exists
		if ( empty( $args['version'] ) ) {
			$file_path       = str_replace( $this->config['url'], $this->config['path'], $args['src'] );
			$args['version'] = $this->generate_version( $file_path );
		}

		$this->scripts->add( $handle, $args );

		return $this;
	}

	/**
	 * Register a style
	 *
	 * @param string $handle Style handle
	 * @param string $src    Style source path
	 * @param array  $args   Additional arguments
	 *
	 * @return self|WP_Error
	 */
	public function style( string $handle, string $src, array $args = [] ) {
		if ( $error = $this->validate_asset( $handle, $src, $args ) ) {
			return $error;
		}

		$defaults = [
			'deps'    => $this->config['style_deps'],
			'version' => $this->config['version'],
			'media'   => $this->config['media'],
			'scope'   => $this->config['scope'],
			'screens' => $this->config['screens']
		];

		$args         = wp_parse_args( $args, $defaults );
		$args['src']  = $this->resolve_path( $src );
		$args['type'] = 'style';

		// Generate version hash if file exists
		if ( empty( $args['version'] ) ) {
			$file_path       = str_replace( $this->config['url'], $this->config['path'], $args['src'] );
			$args['version'] = $this->generate_version( $file_path );
		}

		$this->styles->add( $handle, $args );

		return $this;
	}

	/**
	 * Add inline script or style
	 *
	 * @param string $handle   Asset handle
	 * @param string $data     Inline content
	 * @param string $type     Asset type ('script' or 'style')
	 * @param string $position Position for scripts ('before' or 'after')
	 *
	 * @return self
	 */
	public function add_inline( string $handle, string $data, string $type = 'script', string $position = 'after' ): self {
		if ( $type === 'script' ) {
			wp_add_inline_script( $handle, $data, $position );
		} else {
			wp_add_inline_style( $handle, $data );
		}

		return $this;
	}

	/**
	 * Add dependency to an existing asset
	 *
	 * @param string $handle     Asset handle
	 * @param string $dependency Dependency handle
	 * @param string $type       Asset type ('script' or 'style')
	 *
	 * @return self
	 */
	public function add_dependency( string $handle, string $dependency, string $type = 'script' ): self {
		$collection = $type === 'script' ? $this->scripts : $this->styles;
		if ( $asset = $collection->get( $handle ) ) {
			if ( ! in_array( $dependency, $asset['deps'] ) ) {
				$asset['deps'][] = $dependency;
				$collection->add( $handle, $asset );
			}
		}

		return $this;
	}

	/**
	 * Deregister an asset
	 *
	 * @param string $handle Asset handle
	 * @param string $type   Asset type ('both', 'script', or 'style')
	 *
	 * @return self
	 */
	public function deregister( string $handle, string $type = 'both' ): self {
		if ( $type === 'both' || $type === 'script' ) {
			$this->scripts->remove( $handle );
		}
		if ( $type === 'both' || $type === 'style' ) {
			$this->styles->remove( $handle );
		}

		return $this;
	}

	/**
	 * Get asset URL
	 *
	 * @param string $path Asset path
	 *
	 * @return string Full asset URL
	 */
	public function get_asset_url( string $path ): string {
		return $this->resolve_path( $path );
	}

	/**
	 * Determine asset type from file extension
	 *
	 * @param string $src File source path
	 *
	 * @return string 'script' or 'style'
	 */
	protected function determine_asset_type( string $src ): string {
		$extension = strtolower( pathinfo( $src, PATHINFO_EXTENSION ) );

		switch ( $extension ) {
			case 'css':
			case 'less':
			case 'sass':
			case 'scss':
				return 'style';
			default:
				return 'script';
		}
	}

	/**
	 * Register multiple assets at once
	 *
	 * @param array $assets Array of asset configurations
	 *
	 * @return self
	 */
	public function register( array $assets ): self {
		foreach ( $assets as $asset ) {
			if ( empty( $asset['handle'] ) || empty( $asset['src'] ) ) {
				continue;
			}

			// Determine type if not explicitly set
			if ( ! isset( $asset['type'] ) ) {
				$asset['type'] = $this->determine_asset_type( $asset['src'] );
			}

			if ( $asset['type'] === 'script' ) {
				$this->script( $asset['handle'], $asset['src'], $asset );
			} else {
				$this->style( $asset['handle'], $asset['src'], $asset );
			}
		}

		return $this;
	}

	/**
	 * Resolve asset path/URL
	 *
	 * @param string $src Source path
	 *
	 * @return string Resolved URL
	 */
	protected function resolve_path( string $src ): string {
		// If it's already a full URL
		if ( filter_var( $src, FILTER_VALIDATE_URL ) ) {
			return $src;
		}

		// If it's an absolute path
		if ( strpos( $src, '/' ) === 0 ) {
			return $src;
		}

		// Get the base URL from config
		$base_url = trailingslashit( $this->config['url'] ) . $this->config['assets_url'];

		// Handle minification
		if ( $this->config['minify'] && ! $this->is_debug() ) {
			$src = $this->minify_path( $src );
		}

		return trailingslashit( $base_url ) . ltrim( $src, '/' );
	}

	/**
	 * Convert path to minified version
	 *
	 * @param string $path Original path
	 *
	 * @return string Minified path
	 */
	protected function minify_path( string $path ): string {
		$info = pathinfo( $path );

		return $info['dirname'] . '/' . $info['filename'] . '.min.' . $info['extension'];
	}

	/**
	 * Enqueue assets for admin area
	 *
	 * @param string $hook_suffix Current admin page
	 */
	public function enqueue_admin( string $hook_suffix ): void {
		$this->enqueue_assets( 'admin', $hook_suffix );
	}

	/**
	 * Enqueue assets for frontend
	 */
	public function enqueue_frontend(): void {
		$this->enqueue_assets( 'frontend' );
	}

	/**
	 * Enqueue registered assets
	 *
	 * @param string $context     'admin' or 'frontend'
	 * @param string $hook_suffix Admin page hook suffix
	 */
	protected function enqueue_assets( string $context, string $hook_suffix = '' ): void {
		// Enqueue scripts
		foreach ( $this->scripts->all() as $handle => $script ) {
			if ( $this->should_enqueue( $script, $context, $hook_suffix ) ) {
				$this->enqueue_script( $handle, $script );
			}
		}

		// Enqueue styles
		foreach ( $this->styles->all() as $handle => $style ) {
			if ( $this->should_enqueue( $style, $context, $hook_suffix ) ) {
				$this->enqueue_style( $handle, $style );
			}
		}
	}

	/**
	 * Check if an asset should be enqueued
	 *
	 * @param array  $asset       Asset configuration
	 * @param string $context     Current context
	 * @param string $hook_suffix Admin hook suffix
	 *
	 * @return bool Whether to enqueue the asset
	 */
	protected function should_enqueue( array $asset, string $context, string $hook_suffix ): bool {
		// Check scope
		if ( $asset['scope'] !== 'both' && $asset['scope'] !== $context ) {
			return false;
		}

		// Check screens for admin context
		if ( $context === 'admin' && ! empty( $asset['screens'] ) ) {
			if ( ! in_array( $hook_suffix, $asset['screens'] ) ) {
				return false;
			}
		}

		// Check condition callback
		if ( ! empty( $asset['condition'] ) && is_callable( $asset['condition'] ) ) {
			return call_user_func( $asset['condition'] );
		}

		return true;
	}

	/**
	 * Enqueue a script
	 *
	 * @param string $handle Script handle
	 * @param array  $script Script configuration
	 */
	protected function enqueue_script( string $handle, array $script ): void {
		wp_enqueue_script(
			$handle,
			$script['src'],
			$script['deps'],
			$script['version'],
			$script['in_footer']
		);

		// Handle localization
		if ( ! empty( $script['localize'] ) ) {
			wp_localize_script(
				$handle,
				$script['localize']['name'],
				$script['localize']['data']
			);
		}

		// Handle async/defer
		if ( ! empty( $script['async'] ) || ! empty( $script['defer'] ) ) {
			add_filter( 'script_loader_tag', function ( $tag, $tag_handle ) use ( $handle, $script ) {
				if ( $handle === $tag_handle ) {
					if ( ! empty( $script['async'] ) ) {
						$tag = str_replace( ' src', ' async src', $tag );
					}
					if ( ! empty( $script['defer'] ) ) {
						$tag = str_replace( ' src', ' defer src', $tag );
					}
				}

				return $tag;
			}, 10, 2 );
		}
	}

	/**
	 * Enqueue a style
	 *
	 * @param string $handle Style handle
	 * @param array  $style  Style configuration
	 */
	protected function enqueue_style( string $handle, array $style ): void {
		wp_enqueue_style(
			$handle,
			$style['src'],
			$style['deps'],
			$style['version'],
			$style['media']
		);
	}

}