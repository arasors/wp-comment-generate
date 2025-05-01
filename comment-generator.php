<?php
/**
 * Plugin Name: Comment Generator
 * Description: Generate AI-powered comments for WooCommerce products using Gemini API
 * Version: 1.0.0
 * Author: AI Plugin Generator
 * Text Domain: comment-generator
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * WC requires at least: 3.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('CG_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CG_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CG_VERSION', '1.0.0');

// Check if WooCommerce is active
function cg_is_woocommerce_active() {
    return in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')));
}

// Main plugin class
class Comment_Generator {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        if (!cg_is_woocommerce_active()) {
            add_action('admin_notices', array($this, 'woocommerce_not_active_notice'));
            return;
        }
        
        // Init plugin
        add_action('plugins_loaded', array($this, 'init'));
    }
    
    public function init() {
        // Load text domain
        load_plugin_textdomain('comment-generator', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Enqueue scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Ajax handlers
        add_action('wp_ajax_cg_generate_comments', array($this, 'ajax_generate_comments'));
        add_action('wp_ajax_cg_save_comments', array($this, 'ajax_save_comments'));
    }
    
    public function woocommerce_not_active_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php _e('Comment Generator requires WooCommerce to be installed and active.', 'comment-generator'); ?></p>
        </div>
        <?php
    }
    
    public function add_admin_menu() {
        add_menu_page(
            __('Comment Generator', 'comment-generator'),
            __('Comment Generator', 'comment-generator'),
            'manage_options',
            'comment-generator',
            array($this, 'render_admin_page'),
            'dashicons-format-chat',
            56
        );
    }
    
    public function register_settings() {
        // API settings
        register_setting('cg_settings', 'cg_gemini_api_key');
        register_setting('cg_settings', 'cg_default_prompt');
        
        // Comment settings
        register_setting('cg_settings', 'cg_min_rating', array(
            'default' => 4,
            'sanitize_callback' => array($this, 'sanitize_rating'),
        ));
        register_setting('cg_settings', 'cg_max_rating', array(
            'default' => 5,
            'sanitize_callback' => array($this, 'sanitize_rating'),
        ));
        register_setting('cg_settings', 'cg_comment_language', array(
            'default' => 'en',
        ));
        register_setting('cg_settings', 'cg_default_names', array(
            'sanitize_callback' => 'sanitize_textarea_field',
        ));
        
        // Model settings
        register_setting('cg_settings', 'cg_max_tokens', array(
            'default' => 2048,
            'sanitize_callback' => 'absint',
        ));
        register_setting('cg_settings', 'cg_gemini_model', array(
            'default' => 'gemini-pro',
        ));
        register_setting('cg_settings', 'cg_custom_models', array(
            'sanitize_callback' => array($this, 'sanitize_custom_models'),
        ));
    }
    
    /**
     * Sanitize rating value
     */
    public function sanitize_rating($value) {
        $value = absint($value);
        return min(5, max(1, $value)); // Ensure between 1-5
    }
    
    /**
     * Sanitize custom models
     */
    public function sanitize_custom_models($models) {
        if (!is_array($models)) {
            return array();
        }
        
        $sanitized_models = array();
        
        foreach ($models as $model) {
            if (empty($model['id']) || empty($model['name'])) {
                continue;
            }
            
            $sanitized_models[] = array(
                'id' => sanitize_text_field($model['id']),
                'name' => sanitize_text_field($model['name']),
            );
        }
        
        return $sanitized_models;
    }
    
    public function enqueue_admin_assets($hook) {
        if ('toplevel_page_comment-generator' !== $hook) {
            return;
        }
        
        wp_enqueue_style('cg-admin-css', CG_PLUGIN_URL . 'assets/css/admin.css', array(), CG_VERSION);
        wp_enqueue_script('cg-admin-js', CG_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), CG_VERSION, true);
        
        wp_localize_script('cg-admin-js', 'cg_data', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cg_nonce'),
            'generating_text' => __('Generating comments...', 'comment-generator'),
            'saving_text' => __('Saving comments...', 'comment-generator'),
            'model_id_placeholder' => __('Model ID', 'comment-generator'),
            'model_name_placeholder' => __('Display Name', 'comment-generator'),
            'remove_text' => __('Remove', 'comment-generator')
        ));
    }
    
    public function render_admin_page() {
        require_once CG_PLUGIN_DIR . 'includes/admin-page.php';
    }
    
    public function ajax_generate_comments() {
        check_ajax_referer('cg_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to do this.', 'comment-generator')));
        }
        
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        
        if (!$product_id) {
            wp_send_json_error(array('message' => __('Invalid product ID.', 'comment-generator')));
        }
        
        $api_key = get_option('cg_gemini_api_key');
        
        if (empty($api_key)) {
            wp_send_json_error(array('message' => __('Gemini API key is not set.', 'comment-generator')));
        }
        
        require_once CG_PLUGIN_DIR . 'includes/class-gemini-api.php';
        $gemini_api = new CG_Gemini_API($api_key);
        
        $product = wc_get_product($product_id);
        
        if (!$product) {
            wp_send_json_error(array('message' => __('Product not found.', 'comment-generator')));
        }
        
        $comments = $gemini_api->generate_comments($product);
        
        if (is_wp_error($comments)) {
            wp_send_json_error(array('message' => $comments->get_error_message()));
        }
        
        wp_send_json_success(array('comments' => $comments));
    }
    
    public function ajax_save_comments() {
        check_ajax_referer('cg_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to do this.', 'comment-generator')));
        }
        
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $comments = isset($_POST['comments']) ? $_POST['comments'] : array();
        
        if (!$product_id) {
            wp_send_json_error(array('message' => __('Invalid product ID.', 'comment-generator')));
        }
        
        if (empty($comments)) {
            wp_send_json_error(array('message' => __('No comments to save.', 'comment-generator')));
        }
        
        require_once CG_PLUGIN_DIR . 'includes/class-comment-saver.php';
        $comment_saver = new CG_Comment_Saver();
        
        $result = $comment_saver->save_comments($product_id, $comments);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success(array('message' => sprintf(__('%d comments saved successfully.', 'comment-generator'), count($comments))));
    }
}

// Initialize plugin
function comment_generator() {
    return Comment_Generator::get_instance();
}

// Start the plugin
comment_generator();

// Activation hook
register_activation_hook(__FILE__, 'cg_activate');
function cg_activate() {
    // Set default values
    if (!get_option('cg_default_prompt')) {
        update_option('cg_default_prompt', 'Generate 5-8 realistic product reviews for the following product. Each review should include a customer name, star rating (between 4-5 stars), and a detailed comment about the product. Make the reviews sound authentic and varied in style and length.');
    }
    
    if (!get_option('cg_default_names')) {
        $default_names = "John Smith\nSarah Johnson\nMichael Brown\nEmily Davis\nDavid Wilson\nJennifer Martinez\nRobert Taylor\nLisa Anderson\nWilliam Thomas\nMaria Garcia";
        update_option('cg_default_names', $default_names);
    }
} 