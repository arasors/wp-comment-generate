<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Get settings
$api_key = get_option('cg_gemini_api_key', '');
$default_prompt = get_option('cg_default_prompt', '');
$min_rating = get_option('cg_min_rating', 4);
$max_rating = get_option('cg_max_rating', 5);
$comment_language = get_option('cg_comment_language', 'en');
$default_names = get_option('cg_default_names', '');
$max_tokens = get_option('cg_max_tokens', 2048);
$gemini_model = get_option('cg_gemini_model', 'gemini-pro');
$custom_models = get_option('cg_custom_models', array());

// Language options
$languages = array(
    'en' => __('English', 'comment-generator'),
    'es' => __('Spanish', 'comment-generator'),
    'fr' => __('French', 'comment-generator'),
    'de' => __('German', 'comment-generator'),
    'it' => __('Italian', 'comment-generator'),
    'pt' => __('Portuguese', 'comment-generator'),
    'ru' => __('Russian', 'comment-generator'),
    'zh' => __('Chinese', 'comment-generator'),
    'ja' => __('Japanese', 'comment-generator'),
    'ko' => __('Korean', 'comment-generator'),
    'tr' => __('Turkish', 'comment-generator'),
    'ar' => __('Arabic', 'comment-generator'),
);

// Default Gemini models
$default_models = array(
    'gemini-2.0-flash-001' => __('Gemini 2.5 Flash', 'comment-generator'),
    'gemini-2.5-pro-preview-03-25' => __('Gemini 2.5 Pro', 'comment-generator'),
    'gemini-2.0-pro-exp-02-05' => __('Gemini 2.5 Pro Exp', 'comment-generator'),
    'gemini-1.5-flash-8b-exp-0827' => __('Gemini 1.5 Flash Exp', 'comment-generator'),
    'gemini-2.0-flash-thinking-exp-1219' => __('Gemini 2.0 Flash Thinking Exp', 'comment-generator'),
);
?>

<div class="wrap cg-admin-wrap">
    <h1><?php _e('Comment Generator', 'comment-generator'); ?></h1>
    
    <div class="cg-tabs">
        <nav class="cg-tabs-nav">
            <a href="#" class="cg-tab-link active" data-tab="settings"><?php _e('Settings', 'comment-generator'); ?></a>
            <a href="#" class="cg-tab-link" data-tab="products"><?php _e('Products', 'comment-generator'); ?></a>
        </nav>
        
        <div class="cg-tab-content active" id="settings-tab">
            <div class="cg-card">
                <h2><?php _e('API Settings', 'comment-generator'); ?></h2>
                <form method="post" action="options.php" class="cg-settings-form">
                    <?php settings_fields('cg_settings'); ?>
                    
                    <div class="cg-form-row">
                        <label for="cg_gemini_api_key"><?php _e('Gemini API Key', 'comment-generator'); ?></label>
                        <input type="password" id="cg_gemini_api_key" name="cg_gemini_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text" required />
                        <p class="description">
                            <?php _e('Enter your Gemini API key. If you don\'t have one, you can get it from <a href="https://makersuite.google.com/app/apikey" target="_blank">Google AI Studio</a>.', 'comment-generator'); ?>
                        </p>
                    </div>
                    
                    <div class="cg-form-row">
                        <label for="cg_gemini_model"><?php _e('Gemini Model', 'comment-generator'); ?></label>
                        <p class="description">
                            <?php _e('Select the Gemini model to use for generating comments, you can get it from <a href="https://ai.google.dev/gemini-api/docs/models?hl=tr#model-variations" target="_blank">Google AI Studio</a>.', 'comment-generator'); ?>
                        </p>
                        <select id="cg_gemini_model" name="cg_gemini_model" class="regular-text">
                            <?php foreach ($default_models as $model_id => $model_name) : ?>
                                <option value="<?php echo esc_attr($model_id); ?>" <?php selected($gemini_model, $model_id); ?>><?php echo esc_html($model_name); ?></option>
                            <?php endforeach; ?>
                            
                            <?php if (!empty($custom_models)) : ?>
                                <optgroup label="<?php _e('Custom Models', 'comment-generator'); ?>">
                                    <?php foreach ($custom_models as $model) : ?>
                                        <option value="<?php echo esc_attr($model['id']); ?>" <?php selected($gemini_model, $model['id']); ?>><?php echo esc_html($model['name']); ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                        </select>
                        <p class="description">
                            <?php _e('Select the Gemini model to use for generating comments.', 'comment-generator'); ?>
                        </p>
                    </div>
                    
                    <div class="cg-form-row">
                        <label><?php _e('Custom Models', 'comment-generator'); ?></label>
                        <div id="cg-custom-models-container">
                            <?php 
                            if (!empty($custom_models)) : 
                                foreach ($custom_models as $index => $model) : 
                            ?>
                                <div class="cg-custom-model-row">
                                    <input type="text" name="cg_custom_models[<?php echo $index; ?>][id]" value="<?php echo esc_attr($model['id']); ?>" placeholder="<?php esc_attr_e('Model ID', 'comment-generator'); ?>" class="medium-text" />
                                    <input type="text" name="cg_custom_models[<?php echo $index; ?>][name]" value="<?php echo esc_attr($model['name']); ?>" placeholder="<?php esc_attr_e('Display Name', 'comment-generator'); ?>" class="medium-text" />
                                    <button type="button" class="button cg-remove-model"><?php _e('Remove', 'comment-generator'); ?></button>
                                </div>
                            <?php 
                                endforeach; 
                            endif; 
                            ?>
                        </div>
                        <button type="button" id="cg-add-model" class="button"><?php _e('Add Custom Model', 'comment-generator'); ?></button>
                        <p class="description">
                            <?php _e('Add custom model IDs if you have access to other Gemini models.', 'comment-generator'); ?>
                        </p>
                    </div>
                    
                    <div class="cg-form-row">
                        <label for="cg_max_tokens"><?php _e('Max Tokens', 'comment-generator'); ?></label>
                        <input type="number" id="cg_max_tokens" name="cg_max_tokens" value="<?php echo esc_attr($max_tokens); ?>" min="100" max="32768" class="small-text" />
                        <p class="description">
                            <?php _e('Maximum number of tokens to generate. Higher values allow for longer comments but may take more time and resources.', 'comment-generator'); ?>
                        </p>
                    </div>
                    
                    <h3><?php _e('Comment Settings', 'comment-generator'); ?></h3>
                    
                    <div class="cg-form-row">
                        <label for="cg_default_prompt"><?php _e('Default Prompt', 'comment-generator'); ?></label>
                        <textarea id="cg_default_prompt" name="cg_default_prompt" rows="6" class="large-text"><?php echo esc_textarea($default_prompt); ?></textarea>
                        <p class="description">
                            <?php _e('Enter the default prompt for generating comments. You can use the following placeholders: {product_name}, {product_description}.', 'comment-generator'); ?>
                        </p>
                    </div>
                    
                    <div class="cg-form-row">
                        <label><?php _e('Rating Range', 'comment-generator'); ?></label>
                        <div class="cg-rating-range">
                            <div class="cg-rating-min">
                                <span><?php _e('Min:', 'comment-generator'); ?></span>
                                <select id="cg_min_rating" name="cg_min_rating">
                                    <?php for ($i = 1; $i <= 5; $i++) : ?>
                                        <option value="<?php echo $i; ?>" <?php selected($min_rating, $i); ?>><?php echo $i; ?> <?php echo _n('Star', 'Stars', $i, 'comment-generator'); ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="cg-rating-max">
                                <span><?php _e('Max:', 'comment-generator'); ?></span>
                                <select id="cg_max_rating" name="cg_max_rating">
                                    <?php for ($i = 1; $i <= 5; $i++) : ?>
                                        <option value="<?php echo $i; ?>" <?php selected($max_rating, $i); ?>><?php echo $i; ?> <?php echo _n('Star', 'Stars', $i, 'comment-generator'); ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        <p class="description">
                            <?php _e('Set the minimum and maximum star rating for generated comments.', 'comment-generator'); ?>
                        </p>
                    </div>
                    
                    <div class="cg-form-row">
                        <label for="cg_comment_language"><?php _e('Comment Language', 'comment-generator'); ?></label>
                        <select id="cg_comment_language" name="cg_comment_language">
                            <?php foreach ($languages as $code => $name) : ?>
                                <option value="<?php echo esc_attr($code); ?>" <?php selected($comment_language, $code); ?>><?php echo esc_html($name); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php _e('Select the language for generated comments.', 'comment-generator'); ?>
                        </p>
                    </div>
                    
                    <div class="cg-form-row">
                        <label for="cg_default_names"><?php _e('Default Names', 'comment-generator'); ?></label>
                        <textarea id="cg_default_names" name="cg_default_names" rows="6" class="large-text"><?php echo esc_textarea($default_names); ?></textarea>
                        <p class="description">
                            <?php _e('Enter one name per line. These names will be used when generating comments if the AI doesn\'t provide names.', 'comment-generator'); ?>
                        </p>
                    </div>
                    
                    <h3><?php _e('Auto-Response Settings', 'comment-generator'); ?></h3>
                    
                    <div class="cg-form-row">
                        <label>
                            <input type="checkbox" id="cg_enable_auto_response" name="cg_enable_auto_response" value="1" <?php checked(get_option('cg_enable_auto_response', ''), 1); ?> />
                            <?php _e('Enable auto-responses to generated comments', 'comment-generator'); ?>
                        </label>
                        <p class="description">
                            <?php _e('When enabled, the plugin will automatically add a response to each generated comment.', 'comment-generator'); ?>
                        </p>
                    </div>
                    
                    <div class="cg-form-row">
                        <label for="cg_auto_response_text"><?php _e('Auto-Response Text', 'comment-generator'); ?></label>
                        <textarea id="cg_auto_response_text" name="cg_auto_response_text" rows="4" class="large-text"><?php echo esc_textarea(get_option('cg_auto_response_text', __('Thank you for your feedback! We appreciate your support and are glad you enjoyed our product.', 'comment-generator'))); ?></textarea>
                        <p class="description">
                            <?php _e('Enter the text that will be used as a response to generated comments.', 'comment-generator'); ?>
                        </p>
                    </div>
                    
                    <?php submit_button(__('Save Settings', 'comment-generator')); ?>
                </form>
            </div>
        </div>
        
        <div class="cg-tab-content" id="products-tab">
            <?php
            // Check if API key is set
            if (empty($api_key)) {
                echo '<div class="notice notice-warning inline"><p>' . __('Please enter your Gemini API key in the Settings tab to generate comments.', 'comment-generator') . '</p></div>';
            }
            
            // Get WooCommerce products
            $products = wc_get_products(array(
                'limit' => -1,
                'orderby' => 'title',
                'order' => 'ASC',
                'status' => 'publish',
            ));
            
            if (empty($products)) {
                echo '<div class="notice notice-info inline"><p>' . __('No products found.', 'comment-generator') . '</p></div>';
            } else {
            ?>
            <div class="cg-card">
                <h2><?php _e('WooCommerce Products', 'comment-generator'); ?></h2>
                <p><?php _e('Select a product and click the "Generate Comments" button to create AI-powered comments.', 'comment-generator'); ?></p>
                
                <div class="cg-products-table-container">
                    <table class="wp-list-table widefat fixed striped cg-products-table">
                        <thead>
                            <tr>
                                <th class="cg-product-image"><?php _e('Image', 'comment-generator'); ?></th>
                                <th class="cg-product-name"><?php _e('Product', 'comment-generator'); ?></th>
                                <th class="cg-product-actions"><?php _e('Actions', 'comment-generator'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product) : ?>
                            <tr data-product-id="<?php echo esc_attr($product->get_id()); ?>">
                                <td class="cg-product-image">
                                    <?php echo $product->get_image('thumbnail'); ?>
                                </td>
                                <td class="cg-product-name">
                                    <strong><?php echo esc_html($product->get_name()); ?></strong>
                                    <span class="cg-product-sku"><?php _e('SKU:', 'comment-generator'); ?> <?php echo $product->get_sku() ? esc_html($product->get_sku()) : __('N/A', 'comment-generator'); ?></span>
                                    
                                    <?php 
                                    // Daha önce eklenen yorumları kontrol et
                                    $review_count = get_comments(array(
                                        'post_id' => $product->get_id(),
                                        'count' => true,
                                        'status' => 'approve',
                                        'type' => 'review'
                                    ));
                                    
                                    if ($review_count > 0) : 
                                        $review_text = sprintf(_n('%d yorum', '%d yorum', $review_count, 'comment-generator'), $review_count);
                                    ?>
                                    <div class="cg-existing-comments">
                                        <span class="cg-comment-badge">
                                            <span class="dashicons dashicons-admin-comments"></span>
                                            <?php echo esc_html($review_text); ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td class="cg-product-actions">
                                    <button type="button" class="button cg-generate-btn" <?php echo empty($api_key) ? 'disabled' : ''; ?>>
                                        <span class="dashicons dashicons-update"></span> <?php _e('Generate Comments', 'comment-generator'); ?>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php } ?>
            
            <div id="cg-comments-modal" class="cg-modal">
                <div class="cg-modal-content">
                    <div class="cg-modal-header">
                        <h2><?php _e('Generated Comments', 'comment-generator'); ?></h2>
                        <button type="button" class="cg-modal-close">&times;</button>
                    </div>
                    <div class="cg-modal-body">
                        <div class="cg-product-info">
                            <h3 id="cg-modal-product-name"></h3>
                            <div class="cg-edit-instructions">
                                <div class="cg-info-box">
                                    <span class="dashicons dashicons-info"></span>
                                    <p><?php _e('Yorumları kaydetmeden önce düzenleyebilirsiniz. Her yorumun üst kısmındaki düzenle butonuna tıklayarak yorumu, ismi ve derecelendirmeyi değiştirebilirsiniz.', 'comment-generator'); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="cg-comments-list"></div>
                        
                        <div class="cg-additional-prompt">
                            <label for="cg-custom-prompt"><?php _e('Additional Prompt (Optional)', 'comment-generator'); ?></label>
                            <textarea id="cg-custom-prompt" rows="3" placeholder="<?php esc_attr_e('Add specific instructions for generating comments...', 'comment-generator'); ?>"></textarea>
                        </div>
                        
                        <div class="cg-loading-spinner" style="display: none;">
                            <span class="spinner is-active"></span>
                            <span class="cg-loading-text"><?php _e('Generating comments...', 'comment-generator'); ?></span>
                        </div>
                    </div>
                    <div class="cg-modal-footer">
                        <div class="cg-modal-actions">
                            <button type="button" class="button button-secondary cg-regenerate-btn">
                                <span class="dashicons dashicons-update"></span> <?php _e('Regenerate', 'comment-generator'); ?>
                            </button>
                            <button type="button" class="button button-primary cg-save-btn">
                                <span class="dashicons dashicons-yes"></span> <?php _e('Save Selected Comments', 'comment-generator'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div> 