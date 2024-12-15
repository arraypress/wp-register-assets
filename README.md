# WordPress Asset Manager

A streamlined approach to managing WordPress scripts and styles with advanced features for modern WordPress development.

## Features

- 🚀 Automatic path resolution
- 🎯 Conditional loading based on context or custom conditions
- 🔧 Debug mode support with automatic version strings
- 📦 Minification handling with .min file detection
- 🎨 Screen-specific loading for admin pages
- 🔄 Script localization made easy
- ⚡ Async/Defer script loading support
- 🛠️ Advanced error handling with WP_Error
- 🏷️ Version hash generation for cache busting
- 📝 Inline script/style support
- 📊 Robust dependency management

## Installation

```bash
composer require arraypress/register-assets
```

## Basic Usage

```php
use ArrayPress\WP\register_assets;

// Define your assets
$assets = [
    [
        'handle' => 'my-script',
        'src'    => 'js/script.js',     // Will auto-detect as script
        'deps'   => [ 'jquery' ],
        'async'  => true
    ],
    [
        'handle' => 'my-style',
        'src'    => 'css/style.css',    // Will auto-detect as style
        'media'  => 'all'
    ]
];

// Optional configuration
$config = [
    'debug'      => WP_DEBUG,      // Enable debug mode
    'minify'     => true,          // Enable minification
    'assets_url' => 'dist/assets', // Custom assets directory
    'version'    => '1.0.0',       // Asset version
];

// Register assets
$manager = register_assets( __FILE__, $assets, $config );
```

## Advanced Usage

### Object-Oriented Approach

```php
use ArrayPress\WP\RegisterAssets;

// Initialize the asset manager
$assets = new RegisterAssets( __FILE__, [
    'debug'  => WP_DEBUG,
    'minify' => true
] );

// Configure manager
$assets->set_assets_directory( 'dist' )
       ->set_debug( true )
       ->set_minify( false );

// Register individual assets
$assets->script( 'my-script', 'js/script.js', [
    'deps'     => [ 'jquery' ],
    'async'    => true,
    'localize' => [
        'name' => 'myScriptData',
        'data' => [ 'ajaxUrl' => admin_url('admin-ajax.php') ]
    ]
]);

$assets->style( 'my-style', 'css/style.css', [
    'media' => 'screen',
    'deps'  => ['wp-components']
] );

// Add inline scripts/styles
$assets->add_inline( 'my-script', 'console.log("Hello!");', 'script', 'after' );

// Manage dependencies
$assets->add_dependency( 'my-script', 'another-dep', 'script' );
```

### Conditional Loading

```php
$assets->script( 'admin-script', 'js/admin.js', [
    'scope'   => 'admin',
    'screens' => [ 'post.php', 'post-new.php' ],
    'condition' => function() {
        return current_user_can('edit_posts');
    }
] );
```

## Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `debug` | bool | `SCRIPT_DEBUG` | Enable debug mode |
| `minify` | bool | `true` | Enable minification |
| `assets_url` | string | 'assets' | Base directory for assets |
| `version` | string | '1.0.0' | Asset version string |
| `scope` | string | 'both' | Where to load ('admin', 'frontend', 'both') |
| `in_footer` | bool | `true` | Load scripts in footer |
| `media` | string | 'all' | CSS media type |

## Error Handling

```php
$manager = register_assets( __FILE__, $assets, $config, function( $error ) {
    error_log( 'Asset registration failed: ' . $error->getMessage() );
});

// Or check for errors manually
if ( is_wp_error( $manager ) ) {
    // Handle error
    error_log( $manager->get_error_message() );
}
```

## Requirements

- PHP 7.4 or higher
- WordPress 5.0 or higher

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request. For major changes, please open an issue first to discuss what you would like to change.

## License

Licensed under the GPL-2.0+ License. See LICENSE file for details.

## Support

For support, please use the [issue tracker](https://github.com/arraypress/register-assets/issues).