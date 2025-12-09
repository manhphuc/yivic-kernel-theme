<?php
declare( strict_types = 1 );

namespace Yivic\YivicKernelTheme\Foundation\WP;

use InvalidArgumentException;
use Yivic\YivicKernelTheme\Foundation\Shared\Traits\ConfigTrait;
use Yivic\YivicKernelTheme\App\Support\ServiceProvider;

/**
 * Base class for all Yivic themes that use the Kernel Theme.
 *
 * - No Illuminate, no ServiceProvider, no wp-app bridge.
 * - Only WordPress-related wiring: slug, paths, URLs, and a hook entry point.
 *
 * @property string      $theme_slug
 * @property string      $base_path
 * @property string      $base_url
 * @property string|null $parent_base_path
 * @property string|null $parent_base_url
 */
abstract class WPTheme extends ServiceProvider implements WPThemeInterface {
    use ConfigTrait;

    /**
     * Theme slug (usually the theme folder name).
     *
     * @var string
     */
    protected string $theme_slug = '';

    /**
     * Active (child) theme base path.
     *
     * @var string
     */
    protected string $base_path = '';

    /**
     * Active (child) theme base URL.
     *
     * @var string
     */
    protected string $base_url = '';

    /**
     * Parent theme base path (if any).
     *
     * @var string|null
     */
    protected ?string $parent_base_path = null;

    /**
     * Parent theme base URL (if any).
     *
     * @var string|null
     */
    protected ?string $parent_base_url = null;

    /**
     * Primary static initializer for themes using the kernel.
     *
     * Usage in a concrete theme `functions.php`:
     *
     *   MyTheme_WP_Theme::init( MY_THEME_SLUG );
     *
     * @param  string $theme_slug
     * @return static
     */
    public static function init( string $theme_slug ): self {
        $instance = new static();
        $instance->init_with_needed_params( $theme_slug );
        $instance->manipulate_hooks();

        return $instance;
    }

    /**
     * Backward-compatible alias for the old plugin style.
     *
     * This keeps your existing code style familiar:
     *
     *   Yivic_Lite_WP_Theme::init_with_wp_app( YIVIC_LITE_SLUG );
     *
     * but for Kernel Theme it no longer depends on wp-app or a container.
     *
     * @param  string $slug
     * @return static
     */
    public static function init_with_wp_app( string $slug ): self {
        // For kernel theme we simply delegate to init().
        return static::init( $slug );
    }

    /**
     * Bind base parameters using an associative array.
     *
     * @param  array<string,mixed> $base_params_arr
     * @return void
     */
    public function bind_base_params( array $base_params_arr ): void {
        // ConfigTrait will map keys (PARAM_KEY_*) to properties.
        $this->bind_config( $base_params_arr, true );
    }

    /**
     * Get the theme slug.
     *
     * @return string
     */
    public function get_theme_slug(): string {
        return $this->theme_slug;
    }

    /**
     * Get the active (child) theme base path.
     *
     * @return string
     */
    public function get_base_path(): string {
        return $this->base_path;
    }

    /**
     * Get the active (child) theme base URL.
     *
     * @return string
     */
    public function get_base_url(): string {
        return $this->base_url;
    }

    /**
     * Get the parent theme base path, or empty string if not available.
     *
     * @return string
     */
    public function get_parent_base_path(): string {
        return (string) $this->parent_base_path;
    }

    /**
     * Get the parent theme base URL, or empty string if not available.
     *
     * @return string
     */
    public function get_parent_base_url(): string {
        return (string) $this->parent_base_url;
    }

    /**
     * Validate all required properties for this theme.
     *
     * @return void
     * @throws InvalidArgumentException
     */
    protected function validate_needed_properties(): void {
        if (
            empty( $this->theme_slug )
            || ! preg_match( '/^[a-zA-Z0-9_-]+$/', $this->theme_slug )
        ) {
            throw new InvalidArgumentException(
                sprintf(
                // translators: 1: property name, 2: class name.
                    __(
                        'Property %1$s must be set for %2$s.',
                        'yivic-kernel-theme'
                    ) . ' ' .
                    __(
                        'Value must contain only alphanumeric characters, underscores, or dashes.',
                        'yivic-kernel-theme'
                    ),
                    'theme_slug',
                    get_class( $this )
                )
            );
        }
    }

    /**
     * Initialize base parameters (paths, URLs, slug) for the theme.
     *
     * @param  string $theme_slug
     * @return void
     * @throws InvalidArgumentException
     */
    protected function init_with_needed_params( string $theme_slug ): void {
        // Active (possibly child) theme.
        $theme_path     = get_stylesheet_directory();
        $theme_base_url = get_stylesheet_directory_uri();

        // Parent theme (may equal child if there is no child theme).
        $parent_theme_path = get_template_directory();
        $parent_theme_url  = get_template_directory_uri();

        if ( $theme_path !== $parent_theme_path ) {
            $theme_base_path        = $theme_path;
            $parent_theme_base_path = $parent_theme_path;
            $parent_theme_base_url  = $parent_theme_url;
        } else {
            $theme_base_path        = $theme_path;
            $parent_theme_base_path = '';
            $parent_theme_base_url  = '';
        }

        $this->bind_base_params(
            [
                WPThemeInterface::PARAM_KEY_THEME_SLUG             => $theme_slug,
                WPThemeInterface::PARAM_KEY_THEME_BASE_PATH        => $theme_base_path,
                WPThemeInterface::PARAM_KEY_THEME_BASE_URL         => $theme_base_url,
                WPThemeInterface::PARAM_KEY_PARENT_THEME_BASE_PATH => $parent_theme_base_path,
                WPThemeInterface::PARAM_KEY_PARENT_THEME_BASE_URL  => $parent_theme_base_url,
            ]
        );

        $this->validate_needed_properties();
    }

    /**
     * Each concrete theme (kernel or child) must register its own hooks here.
     *
     * - Kernel theme: textdomain, Blade template integration, shared utilities.
     * - Child theme: supports, menus, enqueue assets, custom logic.
     *
     * @return void
     */
    abstract public function manipulate_hooks(): void;
}