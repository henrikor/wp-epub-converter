<?php
/*
Plugin Name: WP EPUB Converter
Description: Convert WordPress posts to EPUB using a Go program.
Version: 1.1
Author: Your Name
*/

if (!defined('ABSPATH')) {
    exit;
}

class WPEPUBConverter {

    public function __construct() {
        add_action('admin_menu', array($this, 'create_admin_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_post_convert_to_epub', array($this, 'convert_to_epub'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('wp_ajax_nopriv_generate_epub', array($this, 'generate_epub'));
        add_action('wp_ajax_generate_epub', array($this, 'generate_epub'));

        error_log('WPEPUBConverter plugin initialized.');
    }

    public function create_admin_page() {
        add_menu_page(
            'EPUB Converter',
            'EPUB Converter',
            'manage_options',
            'epub-converter',
            array($this, 'admin_page_html')
        );
        error_log('Admin page created.');
    }

    public function admin_page_html() {
        ?>
        <div class="wrap">
            <h1>EPUB Converter Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('wp_epub_converter_settings');
                do_settings_sections('epub-converter');
                submit_button();
                ?>
            </form>
        </div>
        <?php
        error_log('Admin page HTML rendered.');
    }

    public function register_settings() {
        register_setting('wp_epub_converter_settings', 'wp_epub_converter_author');
        register_setting('wp_epub_converter_settings', 'wp_epub_converter_bin_path');

        add_settings_section(
            'wp_epub_converter_settings_section',
            'General Settings',
            null,
            'epub-converter'
        );

        add_settings_field(
            'wp_epub_converter_author',
            'Default Author',
            array($this, 'author_field_html'),
            'epub-converter',
            'wp_epub_converter_settings_section'
        );

        add_settings_field(
            'wp_epub_converter_bin_path',
            'Convert to EPUB Binary Path',
            array($this, 'bin_path_field_html'),
            'epub-converter',
            'wp_epub_converter_settings_section'
        );
    }

    public function author_field_html() {
        $value = get_option('wp_epub_converter_author', '');
        echo '<input type="text" name="wp_epub_converter_author" value="' . esc_attr($value) . '" />';
    }

    public function bin_path_field_html() {
        $value = get_option('wp_epub_converter_bin_path', '/usr/local/bin/convert_to_epub');
        echo '<input type="text" name="wp_epub_converter_bin_path" value="' . esc_attr($value) . '" />';
    }

    public function convert_to_epub() {
        if (!current_user_can('manage_options')) {
            error_log('Unauthorized user attempted to convert to EPUB.');
            wp_die('Unauthorized user');
        }

        $post_id = intval($_POST['post_id']);
        error_log('Starting EPUB conversion for post ID: ' . $post_id);
        $this->generate_epub_file($post_id);
    }

    public function generate_epub() {
        $post_id = intval($_GET['post_id']);
        error_log('Generating EPUB for post ID: ' . $post_id);
        $epub_url = $this->generate_epub_file($post_id);
        wp_redirect($epub_url);
        exit;
    }

    private function generate_epub_file($post_id) {
        $post = get_post($post_id);
        $author = get_option('wp_epub_converter_author', get_the_author_meta('display_name', $post->post_author));
        $title = $post->post_title;
        $content = $post->post_content;
        $bin_path = get_option('wp_epub_converter_bin_path', '/usr/local/bin/convert_to_epub');

        $wp_folder = wp_upload_dir()['basedir'] . '/epub_converter';
        $wp_file = 'post_' . $post_id . '.html';
        $epub_folder = wp_upload_dir()['basedir'] . '/epub_converter';
        $epub_file = 'post_' . $post_id . '.epub';

        if (!file_exists($wp_folder)) {
            mkdir($wp_folder, 0755, true);
            error_log('Created directory: ' . $wp_folder);
        }

        $wp_file_path = $wp_folder . '/' . $wp_file;
        file_put_contents($wp_file_path, $content);
        error_log('Saved post content to: ' . $wp_file_path);

        $command = sprintf('%s -author=%s -title=%s -wpfile=%s -epubfile=%s -wpfolder=%s -epubfolder=%s -headingtype=h2',
            escapeshellcmd($bin_path),
            escapeshellarg($author),
            escapeshellarg($title),
            escapeshellarg($wp_file),
            escapeshellarg($epub_file),
            escapeshellarg($wp_folder),
            escapeshellarg($epub_folder)
        );

        exec($command, $output, $return_var);
        error_log('Executed command: ' . $command);

        if ($return_var !== 0) {
            error_log('Error during EPUB conversion: ' . implode("\n", $output));
            wp_die('Error during EPUB conversion: ' . implode("\n", $output));
        }

        $epub_url = wp_upload_dir()['baseurl'] . '/epub_converter/' . $epub_file;
        error_log('EPUB file generated: ' . $epub_url);
        return $epub_url;
    }

    public function enqueue_styles() {
        wp_enqueue_style('wp-epub-converter', plugins_url('wp-epub-converter.css', __FILE__));
        error_log('Styles enqueued.');
    }
}

function display_epub_link() {
    if (is_single()) {
        $post_id = get_the_ID();
        $url = admin_url('admin-ajax.php') . '?action=generate_epub&post_id=' . $post_id;
        return '<span class="meta-epub"> <svg class="icon icon-book" aria-hidden="true" role="img"> <use xlink:href="https://proletarianperspectives.local/wp-content/themes/tortuga/assets/icons/genericons-neue.svg#book"></use> </svg><a href="' . esc_url($url) . '" class="button epub-link">Download as EPUB</a></span>';
    }
}

new WPEPUBConverter();
?>
