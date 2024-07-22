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
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_nopriv_generate_epub', array($this, 'generate_epub'));
        add_action('wp_ajax_nopriv_get_post_title', 'get_post_title');
        add_action('wp_ajax_generate_epub', array($this, 'generate_epub'));
        add_action('wp_ajax_get_post_title', array($this, 'get_post_title')); // Add this line
    
        error_log('WPEPUBConverter plugin initialized.');
    }
    
    
    function get_post_title() {
        $post_id = intval($_GET['post_id']);
        $post = get_post($post_id);
    
        if ($post) {
            wp_send_json_success(array('title' => $post->post_title));
        } else {
            wp_send_json_error(array('message' => 'Post not found'));
        }
    
        wp_die();
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
        register_setting('wp_epub_converter_settings', 'wp_epub_converter_log_path'); // Nytt felt for log path

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

        add_settings_field(
            'wp_epub_converter_log_path', // Legg til felt for log path
            'Log Path',
            array($this, 'log_path_field_html'),
            'epub-converter',
            'wp_epub_converter_settings_section'
        );
    }

    public function author_field_html() {
        $value = get_option('wp_epub_converter_author', '');
        echo '<input type="text" name="wp_epub_converter_author" value="' . esc_attr($value) . '" />';
    }

    public function bin_path_field_html() {
        $value = get_option('wp_epub_converter_bin_path', plugin_dir_path(__FILE__) . 'wp-go-epub');
        echo '<input type="text" name="wp_epub_converter_bin_path" value="' . esc_attr($value) . '" />';
    }

    public function log_path_field_html() { // Ny funksjon for å vise input felt for log path
        $value = get_option('wp_epub_converter_log_path', '');
        echo '<input type="text" name="wp_epub_converter_log_path" value="' . esc_attr($value) . '" />';
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
        // Ensure the request is an AJAX request
        if (!defined('DOING_AJAX') || !DOING_AJAX) {
            wp_send_json_error(array('message' => 'Invalid request'));
            wp_die();
        }
    
        // Get and sanitize the form data
        $post_id = intval($_POST['post_id']);
        $author = sanitize_text_field($_POST['author']);
        $title = sanitize_text_field($_POST['title']);
        $version = intval($_POST['version']);
        $kepub = isset($_POST['kepub']) ? boolval($_POST['kepub']) : false;
    
        error_log('Generating EPUB for post ID: ' . $post_id);
        error_log('Author: ' . $author);
        error_log('Title: ' . $title);
        error_log('Version: ' . $version);
        error_log('KEpub: ' . $kepub);
    
        // Generate EPUB file and get the URL
        $epub_url = $this->generate_epub_file($post_id, $author, $title, $version, $kepub);
        
        if ($epub_url) {
            error_log('EPUB URL: ' . $epub_url);
            wp_send_json_success(array('url' => $epub_url));
        } else {
            wp_send_json_error(array('message' => 'Failed to generate EPUB file'));
        }
        
        wp_die(); // Terminate AJAX request properly
    }
                                                           
    private function generate_epub_file($post_id, $author, $title, $version, $kepub) {
        $post = get_post($post_id);
        if (!$post) {
            error_log("Post not found.");
            return false;
        }
    
        $default_author = get_option('wp_epub_converter_author', get_the_author_meta('display_name', $post->post_author));
        $author = empty($author) ? $default_author : $author;
        $title = empty($title) ? $post->post_title : $title;
        $content = $post->post_content;
        $bin_path = plugin_dir_path(__FILE__) . 'wp-go-epub';
        $log_path = plugin_dir_path(__FILE__) . 'log';
    
        error_log('Using binary path: ' . $bin_path);
        error_log('Log path: ' . $log_path);
    
        $wp_folder = wp_upload_dir()['basedir'] . '/epub_converter';
        $wp_file = 'post_' . $post_id . '.html';
        $epub_folder = wp_upload_dir()['basedir'] . '/epub_converter';
        $epub_file = 'post_' . $post_id . '.epub';
        $kepub_file = 'post_' . $post_id . '.kepub.epub';
    
        if (!file_exists($wp_folder)) {
            mkdir($wp_folder, 0755, true);
            error_log('Created directory: ' . $wp_folder);
        }
    
        $wp_file_path = $wp_folder . '/' . $wp_file;
        file_put_contents($wp_file_path, $content);
        error_log('Saved post content to: ' . $wp_file_path);
    
        $command = sprintf('\'%s\' -author=%s -title=%s -wpfile=%s -epubfile=%s -wpfolder=%s -epubfolder=%s -logdir=%s -br -version=%d',
            escapeshellcmd($bin_path),
            escapeshellarg($author),
            escapeshellarg($title),
            escapeshellarg($wp_file),
            escapeshellarg($epub_file),
            escapeshellarg($wp_folder),
            escapeshellarg($epub_folder),
            escapeshellarg($log_path),
            $version
        );
    
        if ($kepub) {
            $command .= ' -kepub';
        }
    
        error_log('Executing command: ' . $command);
        exec($command, $output, $return_var);
        error_log('Command output: ' . implode("\n", $output));
        error_log('Command return value: ' . $return_var);
    
        $epub_url = wp_upload_dir()['baseurl'] . '/epub_converter/' . ($kepub ? $kepub_file : $epub_file);
        error_log('EPUB file URL: ' . $epub_url);
        error_log('EPUB file exists: ' . (file_exists($epub_folder . '/' . ($kepub ? $kepub_file : $epub_file)) ? 'Yes' : 'No'));
    
        // Set file permissions to 0644 (readable by everyone)
        $file_to_chmod = $epub_folder . '/' . ($kepub ? $kepub_file : $epub_file);
        chmod($file_to_chmod, 0644);
    
        if ($return_var !== 0) {
            error_log('Error during EPUB conversion: ' . implode("\n", $output));
            return false;
        }
    
        return $epub_url;
    }
                                    
    public function enqueue_styles() {
        wp_enqueue_style('wp-epub-converter', plugins_url('wp-epub-converter.css', __FILE__));
        error_log('Styles enqueued.');
    }

    public function enqueue_scripts() {
        wp_enqueue_script('wp-epub-converter', plugins_url('wp-epub-converter.js', __FILE__), array('jquery'), null, true);
        wp_localize_script('wp-epub-converter', 'wpEpubConverter', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'default_author' => get_option('wp_epub_converter_author', ''),
        ));
        error_log('Scripts enqueued.');
    }
            
}

function display_epub_link() {
    if (is_single()) {
        $post_id = get_the_ID();
        $url = admin_url('admin-ajax.php') . '?action=generate_epub&post_id=' . $post_id;
        return '<span class="meta-epub"> <svg class="icon icon-book" aria-hidden="true" role="img"> <use xlink:href="' . get_template_directory_uri() . '/assets/icons/genericons-neue.svg#book"></use> </svg><a href="#" class="button epub-link" data-post-id="' . $post_id . '">Download as EPUB</a></span>';
    }
}

new WPEPUBConverter();
