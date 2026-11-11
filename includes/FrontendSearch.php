<?php
/**
 * FrontendSearch Class - Frontend search logic.
 *
 * @package Simple_Realtime_Search
 */

namespace SimpleRealtimeSearch;

defined('ABSPATH') || exit;

/**
 * Class FrontendSearch
 *
 * Enqueue assets and render search elements.
 */
class FrontendSearch
{

    /**
     * Option name.
     *
     * @var string
     */
    private const OPTION_NAME = 'srs_settings';

    /**
     * Constructor.
     */
    public function __construct()
    {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_footer', array($this, 'render_search_popup'));
    }

    /**
     * Enqueue CSS and JS.
     */
    public function enqueue_assets(): void
    {
        wp_enqueue_style(
            'srs-public',
            SRS_URL . 'assets/css/search.css',
            array(),
            SRS_VERSION
        );

        wp_enqueue_script(
            'srs-public',
            SRS_URL . 'assets/js/search.js',
            array('wp-util'),
            SRS_VERSION,
            true
        );

        $options = get_option(self::OPTION_NAME, array());
        $json_generator = new JsonGenerator();

        wp_localize_script(
            'srs-public',
            'srsPublic',
            array(
                'jsonUrl' => $json_generator->get_json_file_url() ?? '',
                'resultsLimit' => $options['results_limit'] ?? 10,
                'displayMode' => $options['display_mode'] ?? 'inline',
                'postTypes' => $options['post_types'] ?? array(),
                'i18n' => array(
                    'noResults' => __('No results found', 'simple-realtime-search'),
                    'placeholder' => __('Search...', 'simple-realtime-search'),
                    'search' => __('Search', 'simple-realtime-search'),
                ),
            )
        );
    }

    /**
     * Render search popup.
     */
    public function render_search_popup(): void
    {
        $options = get_option(self::OPTION_NAME, array());
        $post_types = $options['post_types'] ?? array();
        ?>
        <!-- JSON Search Popup -->
        <div class="srs-search-overlay" id="srs-search-overlay" style="display: none;">
            <div class="srs-search-popup">
                <button type="button" class="srs-search-close" id="srs-search-close">&times;</button>
                <form class="srs-search-form" action="<?php echo esc_url(home_url('/')); ?>" method="get">
                    <?php if (in_array('product', $post_types, true)): ?>
                        <input type="hidden" name="post_type" value="product">
                    <?php endif; ?>
                    <input type="text" name="s" id="srs-search-input" class="srs-search-input"
                        placeholder="<?php esc_attr_e('Type to search...', 'simple-realtime-search'); ?>" autocomplete="off"
                        maxlength="100">
                    <button type="submit" class="srs-search-submit">
                        <?php esc_html_e('Search', 'simple-realtime-search'); ?>
                    </button>
                </form>

                <div class="srs-search-results" id="srs-search-results"></div>
            </div>
        </div>

        <!-- Templates -->
        <script type="text/html" id="tmpl-srs-search-item">
                    <# if ( data.thum ) { #>
                    <a href="{{ data.url }}" class="srs-search-item srs-search-item--has-thumb">
                        <img src="{{ data.thum }}" alt="{{ data.title }}" class="srs-search-item__thumb" loading="lazy">
                        <div class="srs-search-item__content">
                            <span class="srs-search-item__title">{{{ data.highlightedTitle }}}</span>
                            <# if ( data.taxonomy ) { #>
                            <span class="srs-search-item__category">{{ data.taxonomy }}</span>
                            <# } #>
                            <# if ( data.price ) { #>
                            <span class="srs-search-item__price">{{ data.price }}</span>
                            <# } #>
                        </div>
                    </a>
                    <# } else { #>
                    <a href="{{ data.url }}" class="srs-search-item">
                        <div class="srs-search-item__content">
                            <span class="srs-search-item__title">{{{ data.highlightedTitle }}}</span>
                            <# if ( data.taxonomy ) { #>
                            <span class="srs-search-item__category">{{ data.taxonomy }}</span>
                            <# } #>
                            <# if ( data.price ) { #>
                            <span class="srs-search-item__price">{{ data.price }}</span>
                            <# } #>
                        </div>
                    </a>
                    <# } #>
                </script>

        <script type="text/html" id="tmpl-srs-search-group">
                    <div class="srs-search-group">
                        <h4 class="srs-search-group__title">{{ data.label }}</h4>
                        <div class="srs-search-group__items">{{{ data.items }}}</div>
                    </div>
                </script>

        <script type="text/html" id="tmpl-srs-no-results">
                    <div class="srs-search-no-results">
                        <span class="dashicons dashicons-search"></span>
                        <p>{{ data.message }}</p>
                    </div>
                </script>
        <?php
    }
}
