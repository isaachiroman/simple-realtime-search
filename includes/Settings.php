<?php
/**
 * Settings Class - Admin settings page.
 *
 * @package Simple_Realtime_Search
 */

namespace SimpleRealtimeSearch;

defined( 'ABSPATH' ) || exit;

/**
 * Class Settings
 *
 * Manage settings page in admin (Settings > Simple Realtime Search).
 */
class Settings {

    /**
     * Option name in database.
     *
     * @var string
     */
    private const OPTION_NAME = 'srs_settings';

    /**
     * Constructor - Register hooks.
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
    }

    /**
     * Add sub-menu page to Settings.
     */
    public function add_settings_page(): void {
        add_options_page(
            __( 'Simple Realtime Search', 'simple-realtime-search' ),
            __( 'Simple Realtime Search', 'simple-realtime-search' ),
            'manage_options',
            'simple-realtime-search',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Register settings.
     */
    public function register_settings(): void {
        register_setting(
            'srs_setting_group',
            self::OPTION_NAME,
            array(
                'sanitize_callback' => array( $this, 'sanitize_settings' ),
            )
        );
    }

    /**
     * Sanitize settings before saving.
     *
     * @param array $input Input data.
     * @return array Sanitized data.
     */
    public function sanitize_settings( array $input ): array {
        $sanitized = array();

        $sanitized['enabled'] = ! empty( $input['enabled'] ) ? 1 : 0;
        $sanitized['results_limit'] = isset( $input['results_limit'] ) 
            ? absint( $input['results_limit'] ) 
            : 10;

        $allowed_modes = array( 'popup', 'inline' );
        $sanitized['display_mode'] = isset( $input['display_mode'] ) && in_array( $input['display_mode'], $allowed_modes, true )
            ? $input['display_mode']
            : 'inline';

        $sanitized['post_types'] = array();
        if ( ! empty( $input['post_types'] ) && is_array( $input['post_types'] ) ) {
            foreach ( $input['post_types'] as $post_type ) {
                $sanitized['post_types'][] = sanitize_key( $post_type );
            }
        }

        return $sanitized;
    }

    /**
     * Enqueue CSS and JS for admin.
     *
     * @param string $hook Current hook.
     */
    public function enqueue_admin_assets( string $hook ): void {
        if ( 'settings_page_simple-realtime-search' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'srs-admin',
            SRS_URL . 'assets/css/admin.css',
            array(),
            SRS_VERSION
        );

        wp_enqueue_script(
            'srs-admin',
            SRS_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            SRS_VERSION,
            true
        );

        wp_localize_script(
            'srs-admin',
            'srsAdmin',
            array(
                'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
                'generateNonce' => wp_create_nonce( 'srs_generate' ),
                'deleteNonce'   => wp_create_nonce( 'srs_delete' ),
                'i18n'          => array(
                    'generating'   => __( 'Generating data...', 'simple-realtime-search' ),
                    'deleting'     => __( 'Deleting...', 'simple-realtime-search' ),
                    /* translators: %d: Number of posts. */
                    'completed'    => __( 'Done! Total: %d posts', 'simple-realtime-search' ),
                    'deleted'      => __( 'Data deleted successfully', 'simple-realtime-search' ),
                    'error'        => __( 'An error occurred', 'simple-realtime-search' ),
                    'noPostTypes'  => __( 'Please select at least one post type', 'simple-realtime-search' ),
                ),
            )
        );
    }

    /**
     * Render settings page.
     */
    public function render_settings_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $options = get_option( self::OPTION_NAME, array() );
        $enabled = ! empty( $options['enabled'] );
        $results_limit = $options['results_limit'] ?? 10;
        $display_mode = $options['display_mode'] ?? 'inline';
        $selected_post_types = $options['post_types'] ?? array();

        $post_types = get_post_types( array( 'public' => true ), 'objects' );
        unset( $post_types['attachment'], $post_types['page'] );

        ?>
        <div class="wrap srs-wrap">
            <h1><?php esc_html_e( 'Simple Realtime Search Settings', 'simple-realtime-search' ); ?></h1>

            <?php
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if ( isset( $_GET['settings-updated'] ) ) :
            ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e( 'Settings saved!', 'simple-realtime-search' ); ?></p>
                </div>
            <?php endif; ?>

            <div class="srs-container">
                <form method="post" action="options.php">
                    <?php settings_fields( 'srs_setting_group' ); ?>

                    <div class="srs-card">
                        <h2 class="srs-card-title">
                            <span class="dashicons dashicons-search"></span>
                            <?php esc_html_e( 'General Settings', 'simple-realtime-search' ); ?>
                        </h2>

                        <div class="srs-field">
                            <label class="srs-toggle">
                                <input type="checkbox"
                                       id="srs-enabled"
                                       name="<?php echo esc_attr( self::OPTION_NAME ); ?>[enabled]"
                                       value="1"
                                       <?php checked( $enabled ); ?>>
                                <span class="srs-toggle-slider"></span>
                                <span class="srs-toggle-label">
                                    <?php esc_html_e( 'Enable Fast Search', 'simple-realtime-search' ); ?>
                                </span>
                            </label>
                            <p class="srs-lock-hint" <?php echo $enabled ? 'hidden' : ''; ?>>
                                <span class="dashicons dashicons-lock"></span>
                                <?php esc_html_e( 'Enable Fast Search to configure it and generate search data.', 'simple-realtime-search' ); ?>
                            </p>
                        </div>

                        <?php // Locked with inert (not disabled) so the values are still submitted on save. ?>
                        <div class="srs-lockable<?php echo $enabled ? '' : ' is-locked'; ?>" <?php echo $enabled ? '' : 'inert'; ?>>
                        <div class="srs-field">
                            <label for="results_limit">
                                <?php esc_html_e( 'Results Limit:', 'simple-realtime-search' ); ?>
                            </label>
                            <input type="number" 
                                   id="results_limit"
                                   name="<?php echo esc_attr( self::OPTION_NAME ); ?>[results_limit]" 
                                   value="<?php echo esc_attr( $results_limit ); ?>" 
                                   min="1" 
                                   max="50"
                                   class="srs-input-small">
                        </div>

                        <div class="srs-field">
                            <label><?php esc_html_e( 'Display Mode:', 'simple-realtime-search' ); ?></label>
                            <div class="srs-radio-group">
                                <label class="srs-radio">
                                    <input type="radio" 
                                           name="<?php echo esc_attr( self::OPTION_NAME ); ?>[display_mode]" 
                                           value="inline"
                                           <?php checked( $display_mode, 'inline' ); ?>>
                                    <span class="srs-radio-custom"></span>
                                    <span class="srs-radio-label">
                                        <strong><?php esc_html_e( 'Inline', 'simple-realtime-search' ); ?></strong>
                                        <small><?php esc_html_e( 'Dropdown below search input', 'simple-realtime-search' ); ?></small>
                                    </span>
                                </label>
                                <label class="srs-radio">
                                    <input type="radio" 
                                           name="<?php echo esc_attr( self::OPTION_NAME ); ?>[display_mode]" 
                                           value="popup"
                                           <?php checked( $display_mode, 'popup' ); ?>>
                                    <span class="srs-radio-custom"></span>
                                    <span class="srs-radio-label">
                                        <strong><?php esc_html_e( 'Popup', 'simple-realtime-search' ); ?></strong>
                                        <small><?php esc_html_e( 'Centered popup overlay', 'simple-realtime-search' ); ?></small>
                                    </span>
                                </label>
                            </div>
                        </div>
                        </div>
                    </div>

                    <div class="srs-lockable<?php echo $enabled ? '' : ' is-locked'; ?>" <?php echo $enabled ? '' : 'inert'; ?>>
                    <div class="srs-card">
                        <h2 class="srs-card-title">
                            <span class="dashicons dashicons-admin-post"></span>
                            <?php esc_html_e( 'Post Types', 'simple-realtime-search' ); ?>
                        </h2>
                        <p class="srs-description">
                            <?php esc_html_e( 'Select post types to index.', 'simple-realtime-search' ); ?>
                        </p>

                        <div class="srs-post-types">
                            <?php foreach ( $post_types as $post_type ) : ?>
                                <label class="srs-checkbox">
                                    <input type="checkbox" 
                                           name="<?php echo esc_attr( self::OPTION_NAME ); ?>[post_types][]" 
                                           value="<?php echo esc_attr( $post_type->name ); ?>"
                                           <?php checked( in_array( $post_type->name, $selected_post_types, true ) ); ?>>
                                    <span><?php echo esc_html( $post_type->labels->name ); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="srs-card">
                        <h2 class="srs-card-title">
                            <span class="dashicons dashicons-database"></span>
                            <?php esc_html_e( 'Data Management', 'simple-realtime-search' ); ?>
                        </h2>

                        <?php if ( ! empty( $selected_post_types ) ) : 
                            $json_generator = new JsonGenerator();
                            $stats = $json_generator->get_stats();
                            ?>
                            <p class="srs-description">
                                <?php esc_html_e( 'Generate or delete search JSON data. Data auto-updates on post changes.', 'simple-realtime-search' ); ?>
                            </p>

                            <div class="srs-data-status <?php echo $stats['exists'] ? 'srs-status-ready' : 'srs-status-empty'; ?>">
                                <?php if ( $stats['exists'] ) : ?>
                                    <span class="dashicons dashicons-yes-alt"></span>
                                    <div class="srs-status-content">
                                        <strong><?php esc_html_e( 'Data Ready', 'simple-realtime-search' ); ?></strong>
                                        <ul>
                                            <li>
                                                <?php
                                                echo wp_kses(
                                                    sprintf(
                                                        /* translators: %s: Total number of posts. */
                                                        __( 'Total Posts: %s', 'simple-realtime-search' ),
                                                        '<strong>' . esc_html( $stats['count'] ) . '</strong>'
                                                    ),
                                                    array( 'strong' => array() )
                                                );
                                                ?>
                                            </li>
                                            <li>
                                                <?php
                                                echo wp_kses(
                                                    sprintf(
                                                        /* translators: %s: File size. */
                                                        __( 'File Size: %s', 'simple-realtime-search' ),
                                                        '<strong>' . esc_html( $stats['size'] ) . '</strong>'
                                                    ),
                                                    array( 'strong' => array() )
                                                );
                                                ?>
                                            </li>
                                            <li>
                                                <?php
                                                echo wp_kses(
                                                    sprintf(
                                                        /* translators: %s: Last updated time. */
                                                        __( 'Last Updated: %s', 'simple-realtime-search' ),
                                                        '<strong>' . esc_html( $stats['last_updated'] ) . '</strong>'
                                                    ),
                                                    array( 'strong' => array() )
                                                );
                                                ?>
                                            </li>
                                        </ul>
                                    </div>
                                <?php else : ?>
                                    <span class="dashicons dashicons-warning"></span>
                                    <div class="srs-status-content">
                                        <strong><?php esc_html_e( 'No Data Generated', 'simple-realtime-search' ); ?></strong>
                                        <p><?php esc_html_e( 'Please click "Generate Data" to create the search index.', 'simple-realtime-search' ); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="srs-data-actions">
                                <button type="button" id="srs-generate-data" class="button button-primary">
                                    <span class="dashicons dashicons-update"></span>
                                    <?php esc_html_e( 'Generate Data', 'simple-realtime-search' ); ?>
                                </button>
                                <button type="button" id="srs-delete-data" class="button button-secondary" <?php disabled( ! $stats['exists'] ); ?>>
                                    <span class="dashicons dashicons-trash"></span>
                                    <?php esc_html_e( 'Delete Data', 'simple-realtime-search' ); ?>
                                </button>
                            </div>

                            <div id="srs-progress" class="srs-progress" style="display: none;">
                                <div class="srs-progress-bar"></div>
                                <span class="srs-progress-text"></span>
                            </div>

                            <div id="srs-message" class="srs-message" style="display: none;"></div>
                        <?php else : ?>
                            <div class="srs-notice srs-notice--info">
                                <span class="dashicons dashicons-info"></span>
                                <p><?php esc_html_e( 'Please select at least one Post Type above and save settings to generate data.', 'simple-realtime-search' ); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                    </div>

                    <p class="submit">
                        <button type="submit" class="button button-primary">
                            <span class="dashicons dashicons-saved"></span>
                            <?php esc_html_e( 'Save Settings', 'simple-realtime-search' ); ?>
                        </button>
                    </p>
                </form>
            </div>
        </div>
        <?php
    }
}
