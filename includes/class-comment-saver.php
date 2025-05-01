<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class CG_Comment_Saver {
    
    public function save_comments($product_id, $comments) {
        if (empty($product_id) || empty($comments)) {
            return new WP_Error('invalid_data', __('Invalid product ID or comments data.', 'comment-generator'));
        }
        
        $product = wc_get_product($product_id);
        
        if (!$product) {
            return new WP_Error('product_not_found', __('Product not found.', 'comment-generator'));
        }
        
        $comments_saved = 0;
        
        foreach ($comments as $comment_data) {
            if (empty($comment_data['selected']) || $comment_data['selected'] !== 'true') {
                continue;
            }
            
            $comment_id = $this->create_comment($product_id, $comment_data);
            
            if (!is_wp_error($comment_id)) {
                $this->add_comment_meta($comment_id, $comment_data);
                $comments_saved++;
            }
        }
        
        if ($comments_saved > 0) {
            $this->update_review_count($product_id);
        }
        
        return $comments_saved;
    }
    
    private function create_comment($product_id, $comment_data) {
        $user_id = 0;
        $user = wp_get_current_user();
        
        if ($user->exists()) {
            $user_id = $user->ID;
        }
        
        $author = isset($comment_data['name']) ? sanitize_text_field($comment_data['name']) : __('Anonymous', 'comment-generator');
        $comment_content = isset($comment_data['comment']) ? sanitize_textarea_field($comment_data['comment']) : '';
        
        if (empty($comment_content)) {
            return new WP_Error('empty_comment', __('Comment content cannot be empty.', 'comment-generator'));
        }
        
        $time = current_time('mysql');
        
        $data = array(
            'comment_post_ID' => $product_id,
            'comment_author' => $author,
            'comment_author_email' => 'example@example.com',
            'comment_author_url' => '',
            'comment_content' => $comment_content,
            'comment_type' => 'review',
            'comment_parent' => 0,
            'user_id' => $user_id,
            'comment_date' => $time,
            'comment_approved' => 1,
        );
        
        // Insert the comment into the database
        $comment_id = wp_insert_comment($data);
        
        if (!$comment_id) {
            return new WP_Error('comment_insert_failed', __('Failed to insert comment.', 'comment-generator'));
        }
        
        // Add auto-response if enabled
        $enable_auto_response = get_option('cg_enable_auto_response', '');
        if ($enable_auto_response) {
            $auto_response_text = get_option('cg_auto_response_text', __('Thank you for your feedback! We appreciate your support and are glad you enjoyed our product.', 'comment-generator'));
            
            if (!empty($auto_response_text)) {
                $response_data = array(
                    'comment_post_ID' => $product_id,
                    'comment_author' => get_bloginfo('name'),
                    'comment_author_email' => get_bloginfo('admin_email'),
                    'comment_author_url' => get_bloginfo('url'),
                    'comment_content' => $auto_response_text,
                    'comment_type' => 'comment',
                    'comment_parent' => $comment_id,
                    'user_id' => 0, // Using 0 for store admin
                    'comment_date' => current_time('mysql'),
                    'comment_approved' => 1,
                );
                
                wp_insert_comment($response_data);
            }
        }
        
        return $comment_id;
    }
    
    private function add_comment_meta($comment_id, $comment_data) {
        // Add rating meta
        $rating = isset($comment_data['rating']) ? intval($comment_data['rating']) : 5;
        $rating = min(5, max(1, $rating)); // Ensure rating is between 1 and 5
        
        add_comment_meta($comment_id, 'rating', $rating, true);
        
        // Add verification meta (so it appears like a verified purchase)
        add_comment_meta($comment_id, 'verified', 1, true);
    }
    
    private function update_review_count($product_id) {
        // Get the product object
        $product = wc_get_product($product_id);
        
        if (!$product) {
            return;
        }
        
        // Get the review count and rating
        $product_id = $product->get_id();
        
        if (function_exists('wc_review_ratings_enabled') && wc_review_ratings_enabled()) {
            $reviews_count = get_comments(array(
                'post_id' => $product_id,
                'status' => 'approve',
                'type' => 'review',
                'count' => true,
            ));
            
            // Get the average rating
            global $wpdb;
            $rating = $wpdb->get_var($wpdb->prepare("
                SELECT AVG(meta_value) FROM $wpdb->commentmeta
                LEFT JOIN $wpdb->comments ON $wpdb->commentmeta.comment_id = $wpdb->comments.comment_ID
                WHERE meta_key = 'rating'
                AND comment_post_ID = %d
                AND comment_approved = '1'
                AND meta_value > 0
            ", $product_id));
            
            // Store the average rating
            update_post_meta($product_id, '_wc_average_rating', $rating);
            
            // Store the review count
            update_post_meta($product_id, '_wc_review_count', $reviews_count);
            
            // Clear any caches
            if (method_exists($product, 'clear_cache')) {
                $product->clear_cache();
            }
        }
    }
} 