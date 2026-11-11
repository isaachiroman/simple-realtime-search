<?php
/**
 * JsonGenerator Class - Manage JSON file generation.
 *
 * @package Simple_Realtime_Search
 */

namespace SimpleRealtimeSearch;

defined('ABSPATH') || exit;

/**
 * Class JsonGenerator
 *
 * Handles creation, update, and deletion of search data JSON file.
 */
class JsonGenerator
{

    /**
     * Option name.
     *
     * @var string
     */
    private const OPTION_NAME = 'srs_settings';

    /**
     * Posts per query.
     *
     * @var int
     */
    private const POSTS_PER_PAGE = 500;

    /**
     * .htaccess rules for the JSON directory (Apache/LiteSpeed): compression + long cache since the URL is versioned with ?v=.
     *
     * @var string
     */
    private const HTACCESS_RULES = <<<'HTACCESS'
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE application/json
</IfModule>
<IfModule mod_brotli.c>
    AddOutputFilterByType BROTLI_COMPRESS application/json
</IfModule>
<IfModule mod_headers.c>
    <FilesMatch "\.json$">
        Header set Cache-Control "public, max-age=31536000"
    </FilesMatch>
</IfModule>
HTACCESS;

    /**
     * Constructor - Register hooks.
     */
    public function __construct()
    {
        // AJAX handlers.
        add_action('wp_ajax_srs_generate', array($this, 'ajax_generate_json'));
        add_action('wp_ajax_srs_delete', array($this, 'ajax_delete_json'));

        // Auto-sync hooks.
        $this->register_auto_sync_hooks();
    }

    /**
     * Register auto-sync hooks.
     */
    private function register_auto_sync_hooks(): void
    {
        $options = get_option(self::OPTION_NAME, array());
        $post_types = $options['post_types'] ?? array();

        if (empty($post_types)) {
            return;
        }

        foreach ($post_types as $post_type) {
            add_action("publish_{$post_type}", array($this, 'add_post_to_json'));
        }

        add_action('delete_post', array($this, 'remove_post_from_json'));
        add_action('trashed_post', array($this, 'remove_post_from_json'));
    }

    /**
     * Get JSON directory path.
     *
     * @return string
     */
    private function get_json_dir(): string
    {
        $upload_dir = wp_upload_dir();
        return $upload_dir['basedir'] . '/simple-realtime-search';
    }

    /**
     * Get JSON file path.
     *
     * @return string
     */
    private function get_json_file_path(): string
    {
        return $this->get_json_dir() . '/data.json';
    }

    /**
     * Get JSON file URL.
     *
     * @return string|null
     */
    public function get_json_file_url(): ?string
    {
        $file_path = $this->get_json_file_path();

        if (!file_exists($file_path)) {
            return null;
        }

        $upload_dir = wp_upload_dir();
        $absolute_url = $upload_dir['baseurl'] . '/simple-realtime-search/data.json';

        // Append ?v=filemtime: the URL changes whenever the file does, so it can be cached long-term.
        $absolute_url = add_query_arg('v', (string) filemtime($file_path), $absolute_url);

        return wp_make_link_relative($absolute_url);
    }

    /**
     * AJAX handler - Generate JSON.
     */
    public function ajax_generate_json(): void
    {
        check_ajax_referer('srs_generate', 'security');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'simple-realtime-search')));
        }

        $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
        $options = get_option(self::OPTION_NAME, array());
        $post_types = $options['post_types'] ?? array();

        if (empty($post_types)) {
            wp_send_json_error(array('message' => __('No post types selected', 'simple-realtime-search')));
        }

        $data = $this->get_posts_data($post_types, $page);

        if (empty($data)) {
            $existing_data = $this->read_json_file();
            wp_send_json_success(
                array(
                    'done' => true,
                    'count' => count($existing_data),
                )
            );
        }

        // Read - merge - write under lock.
        $merged_data = $this->with_lock(
            function () use ($data, $page, $post_types) {
                $existing_data = $this->read_json_file();

                // Page 1: drop old data of the selected post types.
                if (1 === $page) {
                    $existing_data = array_filter(
                        $existing_data,
                        fn($item) => !in_array($item['type'], $post_types, true)
                    );
                }

                $merged_data = $this->merge_data($existing_data, $data);
                $this->write_json_file($merged_data);

                return $merged_data;
            }
        );

        wp_send_json_success(
            array(
                'done' => false,
                'page' => $page + 1,
                'count' => count($merged_data),
                'batch' => count($data),
            )
        );
    }

    /**
     * AJAX handler - Delete JSON.
     */
    public function ajax_delete_json(): void
    {
        check_ajax_referer('srs_delete', 'security');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'simple-realtime-search')));
        }

        $json_dir = $this->get_json_dir();

        if (is_dir($json_dir)) {
            $files = array_merge(glob($json_dir . '/*') ?: array(), glob($json_dir . '/.[!.]*') ?: array());
            foreach ($files as $file) {
                if (is_file($file)) {
                    wp_delete_file($file);
                }
            }

            // Files are deleted, now try to remove directory.
            // Using rmdir as WP_Filesystem might be overkill here but strict checks prefer it.
            // However, for uploads/simple-realtime-search, rmdir is safe if empty.
            // Let's use WP_Filesystem to be compliant.
            global $wp_filesystem;
            if (empty($wp_filesystem)) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                WP_Filesystem();
            }

            if ($wp_filesystem && $wp_filesystem->exists($json_dir)) {
                $wp_filesystem->rmdir($json_dir);
            } else {
                // Fallback if WP_Filesystem fails (e.g. no creds) - though for uploads it usually works direct.
                if (is_dir($json_dir)) {
                    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
                    @rmdir($json_dir);
                }
            }
        }

        wp_send_json_success(array('message' => __('Data deleted', 'simple-realtime-search')));
    }

    /**
     * Add post to JSON on publish.
     *
     * @param int $post_id Post ID.
     */
    public function add_post_to_json(int $post_id): void
    {
        $options = get_option(self::OPTION_NAME, array());
        $post_types = $options['post_types'] ?? array();

        if (empty($post_types)) {
            return;
        }

        $post = get_post($post_id);
        if (!$post || !in_array($post->post_type, $post_types, true)) {
            return;
        }

        $item = $this->format_post_data($post);
        if (empty($item)) {
            return;
        }

        $this->with_lock(
            function () use ($item) {
                $existing_data = $this->read_json_file();
                $this->write_json_file($this->merge_data($existing_data, array($item)));
            }
        );
    }

    /**
     * Remove post from JSON.
     *
     * @param int $post_id Post ID.
     */
    public function remove_post_from_json(int $post_id): void
    {
        if (!file_exists($this->get_json_file_path())) {
            return;
        }

        $this->with_lock(
            function () use ($post_id) {
                $existing_data = $this->read_json_file();
                $updated_data  = array_filter(
                    $existing_data,
                    fn($item) => (int) $item['ID'] !== $post_id
                );

                // Skip the write if nothing changed.
                if (count($updated_data) !== count($existing_data)) {
                    $this->write_json_file($updated_data);
                }
            }
        );
    }

    /**
     * Get posts data.
     *
     * @param array $post_types Post types.
     * @param int   $page Current page.
     * @return array
     */
    private function get_posts_data(array $post_types, int $page): array
    {
        $args = array(
            'post_type' => $post_types,
            'posts_per_page' => self::POSTS_PER_PAGE,
            'paged' => $page,
            'post_status' => 'publish',
            'orderby' => 'ID',
            'order' => 'ASC',
        );

        $posts = get_posts($args);
        $results = array();

        foreach ($posts as $post) {
            $item = $this->format_post_data($post);
            if (!empty($item)) {
                $results[] = $item;
            }
        }

        return $results;
    }

    /**
     * Format post data.
     *
     * @param \WP_Post $post Post object.
     * @return array
     */
    private function format_post_data(\WP_Post $post): array
    {
        $item = array(
            'ID' => $post->ID,
            'type' => $post->post_type,
            'title' => $post->post_title,
            'url' => get_permalink($post->ID),
            'thum' => get_the_post_thumbnail_url($post->ID, 'thumbnail') ?: '',
        );

        if ('product' === $post->post_type && function_exists('wc_get_product')) {
            $product = wc_get_product($post->ID);
            if ($product) {
                // Store price as plain text (no HTML) to keep the JSON small.
                $item['price'] = html_entity_decode(wp_strip_all_tags(wc_price($product->get_price())), ENT_QUOTES, 'UTF-8');
            }
        }

        $item['taxonomy'] = $this->get_first_taxonomy_term($post);

        return $item;
    }

    /**
     * Get first taxonomy term.
     *
     * @param \WP_Post $post Post object.
     * @return string
     */
    private function get_first_taxonomy_term(\WP_Post $post): string
    {
        $taxonomy = 'product' === $post->post_type ? 'product_cat' : 'category';

        if ('product' !== $post->post_type) {
            $taxonomies = get_object_taxonomies($post->post_type);
            $taxonomy = !empty($taxonomies) ? $taxonomies[0] : 'category';
        }

        $terms = get_the_terms($post->ID, $taxonomy);

        if ($terms && !is_wp_error($terms)) {
            return $terms[0]->name;
        }

        return '';
    }

    /**
     * Read JSON file.
     *
     * @return array
     */
    private function read_json_file(): array
    {
        $file_path = $this->get_json_file_path();

        if (!file_exists($file_path)) {
            return array();
        }

        $content = file_get_contents($file_path);
        $data = json_decode($content, true);

        return is_array($data) ? $data : array();
    }

    /**
     * Write JSON file.
     *
     * @param array $data Data to write.
     * @return bool
     */
    private function write_json_file(array $data): bool
    {
        $file_path = $this->get_json_file_path();
        $json      = wp_json_encode(array_values($data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (false === $json) {
            return false;
        }

        // Write to a temp file then rename() so clients never fetch a half-written file.
        $tmp_path = $file_path . '.tmp-' . wp_generate_password(8, false, false);

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        if (false === file_put_contents($tmp_path, $json)) {
            wp_delete_file($tmp_path);
            return false;
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
        if (!rename($tmp_path, $file_path)) {
            wp_delete_file($tmp_path);
            return false;
        }

        return true;
    }

    /**
     * Ensure the JSON directory exists, with an .htaccess enabling compression + caching.
     *
     * @return void
     */
    private function ensure_json_dir(): void
    {
        $json_dir = $this->get_json_dir();

        if (!is_dir($json_dir)) {
            wp_mkdir_p($json_dir);
        }

        $htaccess = $json_dir . '/.htaccess';
        if (!file_exists($htaccess)) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
            file_put_contents($htaccess, self::HTACCESS_RULES);
        }
    }

    /**
     * Run a callback under an exclusive flock so concurrent requests cannot overwrite each other.
     *
     * @param callable $callback Callback.
     * @return mixed
     */
    private function with_lock(callable $callback)
    {
        $this->ensure_json_dir();

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        $handle = fopen($this->get_json_dir() . '/.lock', 'c');

        if (false === $handle) {
            return $callback();
        }

        flock($handle, LOCK_EX);

        try {
            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
            fclose($handle);
        }
    }

    /**
     * Get JSON file stats (count, last modified).
     *
     * @return array
     */
    public function get_stats(): array
    {
        $file_path = $this->get_json_file_path();

        if (!file_exists($file_path)) {
            return array(
                'exists' => false,
                'count' => 0,
                'last_updated' => '',
                'size' => '',
            );
        }

        $data = $this->read_json_file();
        $count = count($data);
        if (is_array($data)) {
            $mtime = filemtime($file_path);
            $last_updated = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $mtime);
            $size = size_format(filesize($file_path));
        } else {
            $count = 0;
            $last_updated = '';
            $size = 0;
        }

        return array(
            'exists' => true,
            'count' => $count,
            'last_updated' => $last_updated,
            'size' => $size,
        );
    }

    /**
     * Merge new data with existing data.
     *
     * @param array $existing_data Existing data.
     * @param array $new_data New data.
     * @return array
     */
    private function merge_data(array $existing_data, array $new_data): array
    {
        // Index by ID so merging is O(n) instead of nested O(n*m) loops.
        $indexed = array();

        foreach ($existing_data as $item) {
            $indexed[(int) $item['ID']] = $item;
        }

        foreach ($new_data as $item) {
            $indexed[(int) $item['ID']] = $item;
        }

        return array_values($indexed);
    }
}
