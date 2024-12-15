<?php
/**
 * Asset Collection
 *
 * Manages collections of assets with methods for adding, retrieving, and checking existence.
 *
 * @package     ArrayPress/Utils/Register
 * @copyright   Copyright (c) 2024, ArrayPress Limited
 * @license     GPL2+
 * @version     2.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\WP;

/**
 * Class AssetCollection
 *
 * Manages a collection of assets with methods for CRUD operations and filtering.
 */
class AssetCollection {

	/**
	 * Collection of assets
	 *
	 * @var array
	 */
	protected array $items = [];

	/**
	 * Add an asset to the collection
	 *
	 * @param string $handle Asset handle
	 * @param array  $args   Asset configuration
	 *
	 * @return void
	 */
	public function add( string $handle, array $args ): void {
		$this->items[ $handle ] = $args;
	}

	/**
	 * Get an asset from the collection
	 *
	 * @param string $handle Asset handle
	 *
	 * @return array|null Asset configuration or null if not found
	 */
	public function get( string $handle ): ?array {
		return $this->items[ $handle ] ?? null;
	}

	/**
	 * Get all assets in the collection
	 *
	 * @return array All assets
	 */
	public function all(): array {
		return $this->items;
	}

	/**
	 * Check if an asset exists in the collection
	 *
	 * @param string $handle Asset handle
	 *
	 * @return bool Whether the asset exists
	 */
	public function has( string $handle ): bool {
		return isset( $this->items[ $handle ] );
	}

	/**
	 * Remove an asset from the collection
	 *
	 * @param string $handle Asset handle
	 *
	 * @return void
	 */
	public function remove( string $handle ): void {
		unset( $this->items[ $handle ] );
	}

	/**
	 * Clear all assets from the collection
	 *
	 * @return void
	 */
	public function clear(): void {
		$this->items = [];
	}

	/**
	 * Filter assets based on a callback
	 *
	 * @param callable $callback Filter callback function
	 *
	 * @return array Filtered assets
	 */
	public function filter( callable $callback ): array {
		return array_filter( $this->items, $callback );
	}

	/**
	 * Count the number of assets in the collection
	 *
	 * @return int Number of assets
	 */
	public function count(): int {
		return count( $this->items );
	}

	/**
	 * Get all asset handles
	 *
	 * @return array Array of asset handles
	 */
	public function handles(): array {
		return array_keys( $this->items );
	}

	/**
	 * Update an existing asset
	 *
	 * @param string $handle Asset handle
	 * @param array  $args   New asset configuration
	 *
	 * @return bool True if updated, false if asset doesn't exist
	 */
	public function update( string $handle, array $args ): bool {
		if ( $this->has( $handle ) ) {
			$this->items[ $handle ] = array_merge( $this->items[ $handle ], $args );

			return true;
		}

		return false;
	}

}