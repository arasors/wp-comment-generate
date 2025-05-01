<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class CG_Gemini_API {
    private $api_key;
    private $api_base_url = 'https://generativelanguage.googleapis.com/v1beta/models/';
    
    public function __construct($api_key) {
        $this->api_key = $api_key;
    }
    
    public function generate_comments($product) {
        $product_name = $product->get_name();
        $product_description = $product->get_description() ?: $product->get_short_description();
        
        // Get the default prompt and replace placeholders
        $default_prompt = get_option('cg_default_prompt', '');
        $prompt = str_replace(
            array('{product_name}', '{product_description}'),
            array($product_name, $product_description),
            $default_prompt
        );
        
        // Additional prompt from the request
        $additional_prompt = isset($_POST['additional_prompt']) ? sanitize_textarea_field($_POST['additional_prompt']) : '';
        
        if (!empty($additional_prompt)) {
            $prompt .= "\n\nAdditional instructions: " . $additional_prompt;
        }
        
        // Get language setting and add it to prompt if not English
        $comment_language = get_option('cg_comment_language', 'en');
        $languages = array(
            'en' => 'English',
            'es' => 'Spanish',
            'fr' => 'French',
            'de' => 'German',
            'it' => 'Italian',
            'pt' => 'Portuguese',
            'ru' => 'Russian',
            'zh' => 'Chinese',
            'ja' => 'Japanese',
            'ko' => 'Korean',
            'tr' => 'Turkish',
            'ar' => 'Arabic',
        );
        
        if ($comment_language != 'en' && isset($languages[$comment_language])) {
            $prompt .= "\n\nPlease write the comments in " . $languages[$comment_language] . " language.";
        }
        
        // Get min/max rating settings
        $min_rating = get_option('cg_min_rating', 4);
        $max_rating = get_option('cg_max_rating', 5);
        
        // Make sure min is not greater than max
        if ($min_rating > $max_rating) {
            $temp = $min_rating;
            $min_rating = $max_rating;
            $max_rating = $temp;
        }
        
        // Add rating range to prompt
        $prompt = str_replace(
            'between 4-5 stars',
            'between ' . $min_rating . '-' . $max_rating . ' stars',
            $prompt
        );
        
        // Make the API request
        $response = $this->make_api_request($prompt);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Parse the response to extract comments
        return $this->parse_comments_from_response($response, $product_name, $min_rating, $max_rating);
    }
    
    private function make_api_request($prompt) {
        // Get the selected model
        $model = get_option('cg_gemini_model', 'gemini-pro');
        $max_tokens = get_option('cg_max_tokens', 2048);
        
        $request_url = add_query_arg(array(
            'key' => $this->api_key,
        ), $this->api_base_url . $model . ':generateContent');
        
        $request_body = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array(
                            'text' => $prompt,
                        ),
                    ),
                ),
            ),
            'generationConfig' => array(
                'temperature' => 0.7,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => intval($max_tokens),
            ),
        );
        
        $args = array(
            'body' => wp_json_encode($request_body),
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'timeout' => 60,
        );
        
        $response = wp_remote_post($request_url, $args);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code !== 200) {
            return new WP_Error(
                'gemini_api_error',
                sprintf(__('Gemini API Error: %s', 'comment-generator'), 
                         wp_remote_retrieve_response_message($response))
            );
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (empty($data) || !isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            return new WP_Error(
                'gemini_api_error',
                __('Invalid response from Gemini API', 'comment-generator')
            );
        }
        
        return $data['candidates'][0]['content']['parts'][0]['text'];
    }
    
    private function parse_comments_from_response($response, $product_name, $min_rating = 4, $max_rating = 5) {
        // Initialize an array to store the parsed comments
        $comments = array();
        
        // Use regex to try to find structured reviews, which might be in various formats
        // Pattern 1: Looking for numbered reviews with stars/ratings
        if (preg_match_all('/(\d+\.|\*)?\s*(?:Name:|Customer:)?\s*([^,\n]+)(?:[,:]|\s+-)\s*(?:Rating:|Stars:)?\s*(\d+(?:\.\d+)?)\s*(?:\/\s*5|\s*stars?)[^\n]*\n((?:(?!\d+\.|\*|\n\s*(?:Name:|Customer:)).)+)/is', $response, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $name = trim($match[2]);
                $rating = min(5, max(1, intval($match[3])));
                $comment = trim($match[4]);
                
                if (!empty($name) && !empty($comment)) {
                    $comments[] = array(
                        'name' => $name,
                        'rating' => $rating,
                        'comment' => $comment,
                        'selected' => true,
                    );
                }
            }
        }
        
        // Pattern 2: Looking for reviews separated by newlines with clear name/rating/comment structure
        if (empty($comments) && preg_match_all('/(?:Customer|Name):\s*([^\n]+)\n(?:Rating|Stars):\s*(\d+(?:\.\d+)?)[^\n]*\n(?:Comment|Review):\s*([^\n]+(?:\n(?!Customer:|Name:|Rating:|Stars:|Comment:|Review:)[^\n]+)*)/is', $response, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $name = trim($match[1]);
                $rating = min(5, max(1, intval($match[2])));
                $comment = trim($match[3]);
                
                if (!empty($name) && !empty($comment)) {
                    $comments[] = array(
                        'name' => $name,
                        'rating' => $rating,
                        'comment' => $comment,
                        'selected' => true,
                    );
                }
            }
        }
        
        // Pattern 3: Fallback - try to find any names and comments if structured parsing failed
        if (empty($comments) && preg_match_all('/"([^"]+)"\s*(?:-|–|—)\s*([^,\n]+)(?:[,\s]+(\d+)(?:\s*\/\s*5|\s*stars?)|)/is', $response, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $comment = trim($match[1]);
                $name = trim($match[2]);
                $rating = isset($match[3]) ? min(5, max(1, intval($match[3]))) : rand($min_rating, $max_rating);
                
                if (!empty($name) && !empty($comment)) {
                    $comments[] = array(
                        'name' => $name,
                        'rating' => $rating,
                        'comment' => $comment,
                        'selected' => true,
                    );
                }
            }
        }
        
        // If all parsing attempts failed, create a more generic error
        if (empty($comments)) {
            return new WP_Error(
                'parsing_error',
                __('Could not parse comments from the AI response. Please try regenerating with a clearer prompt.', 'comment-generator')
            );
        }
        
        // Make sure we have between 5-8 comments
        if (count($comments) < 5) {
            // If we have too few, generate some generic ones using custom names
            $default_names_setting = get_option('cg_default_names', '');
            
            if (!empty($default_names_setting)) {
                $names_array = explode("\n", $default_names_setting);
                $names_array = array_map('trim', $names_array);
                $names_array = array_filter($names_array);
            } else {
                $names_array = array(
                    'John Smith', 'Sarah Johnson', 'Michael Brown', 'Emily Davis', 
                    'David Wilson', 'Jennifer Martinez', 'Robert Taylor', 'Lisa Anderson'
                );
            }
            
            $generic_comments = array(
                "Great product! I'm very satisfied with my purchase.",
                "This $product_name exceeded my expectations. Would buy again!",
                "Excellent quality and fast shipping. Very happy customer.",
                "I've been using this for a while now and it's holding up great.",
                "Perfect for what I needed. Good value for money."
            );
            
            while (count($comments) < 5) {
                $comments[] = array(
                    'name' => $names_array[array_rand($names_array)],
                    'rating' => rand($min_rating, $max_rating),
                    'comment' => $generic_comments[array_rand($generic_comments)],
                    'selected' => true,
                );
            }
        }
        
        // Adjust ratings to be within min-max range if needed
        foreach ($comments as &$comment) {
            if ($comment['rating'] < $min_rating) {
                $comment['rating'] = $min_rating;
            } elseif ($comment['rating'] > $max_rating) {
                $comment['rating'] = $max_rating;
            }
        }
        
        // Limit to 8 comments maximum
        $comments = array_slice($comments, 0, 8);
        
        return $comments;
    }
} 