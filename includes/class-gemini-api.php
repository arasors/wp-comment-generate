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
        
        // Get min/max rating settings
        $min_rating = get_option('cg_min_rating', 4);
        $max_rating = get_option('cg_max_rating', 5);
        
        // Make sure min is not greater than max
        if ($min_rating > $max_rating) {
            $temp = $min_rating;
            $min_rating = $max_rating;
            $max_rating = $temp;
        }
        
        // Get language setting
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
        
        $language_name = isset($languages[$comment_language]) ? $languages[$comment_language] : 'English';
        
        // Get custom names for use in generated comments
        $default_names_setting = get_option('cg_default_names', '');
        $custom_names = array();
        
        if (!empty($default_names_setting)) {
            $custom_names = explode("\n", $default_names_setting);
            $custom_names = array_map('trim', $custom_names);
            $custom_names = array_filter($custom_names);
        }
        
        if (empty($custom_names)) {
            $custom_names = array(
                'John Smith', 'Sarah Johnson', 'Michael Brown', 'Emily Davis', 
                'David Wilson', 'Jennifer Martinez', 'Robert Taylor', 'Lisa Anderson'
            );
        }
        
        // Build a JSON-friendly prompt
        $json_prompt = sprintf(
            "Ürün adı: \"%s\". Ürün açıklaması: \"%s\". " .
            "5 adet yorum üretin. " .
            "Yorumlar %s dilinde yazılmalıdır. " .
            "İsim kullanmayın, yorumlarda sadece metinleri üretin. " .
            "Yanıt MUST valid JSON format ONLY, with this structure: { \"reviews\": [ { \"rating\": 5, \"comment\": \"Review text here\" }, ... ] }. " .
            "Yanıt JSON yapısı dışında herhangi bir metin içermemelidir. " .
            "TÜM YORUMLAR KESINLIKLE SADECE %s DILINDE OLMALIDIR. " .
            "Yorumlar gerçekçi ve stil ve uzunluk açısından değişken olmalıdır.",
            $product_name,
            $product_description,
            $language_name,
            strtoupper($language_name)
        );
        
        // Additional prompt from the request
        $additional_prompt = isset($_POST['additional_prompt']) ? sanitize_textarea_field($_POST['additional_prompt']) : '';
        
        if (!empty($additional_prompt)) {
            $json_prompt .= " Ekstra talimatlar: " . $additional_prompt;
        }
        
        // Make the API request
        $response = $this->make_api_request($json_prompt);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Try to parse the response as JSON
        return $this->parse_json_response($response, $product_name, $min_rating, $max_rating, $custom_names);
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
    
    private function parse_json_response($response, $product_name, $min_rating = 4, $max_rating = 5, $custom_names = array()) {
        // Try to find and extract JSON from the response
        $json_pattern = '/(\{.*\})/s';
        if (preg_match($json_pattern, $response, $matches)) {
            $json_string = $matches[0];
        } else {
            $json_string = $response; // Try to parse the entire response as JSON
        }
        
        // Try to decode the JSON
        $data = json_decode($json_string, true);
        
        // Check if we have valid JSON with reviews array
        if (json_last_error() === JSON_ERROR_NONE && !empty($data) && isset($data['reviews']) && is_array($data['reviews'])) {
            $comments = array();
            
            foreach ($data['reviews'] as $index => $review) {
                // Validate required fields
                if (isset($review['comment']) && !empty($review['comment'])) {
                    
                    // Get rating or use default
                    $rating = isset($review['rating']) ? intval($review['rating']) : rand($min_rating, $max_rating);
                    $rating = min(5, max(1, $rating));
                    
                    if ($rating < $min_rating) {
                        $rating = $min_rating;
                    } elseif ($rating > $max_rating) {
                        $rating = $max_rating;
                    }
                    
                    // Use a name from custom names
                    $name_index = $index % count($custom_names);
                    $name = $custom_names[$name_index];
                    
                    $comments[] = array(
                        'name' => $name,
                        'rating' => $rating,
                        'comment' => $review['comment'],
                        'selected' => true,
                    );
                }
            }
            
            // If we have valid comments, return them
            if (!empty($comments)) {
                // Limit to 8 comments maximum
                $comments = array_slice($comments, 0, 8);
                return $comments;
            }
        }
        
        // If JSON parsing failed, try legacy regex-based parsing
        $comments = $this->parse_comments_from_response($response, $product_name, $min_rating, $max_rating, $custom_names);
        
        // If regex parsing returned an error, include JSON parsing error in the debug info
        if (is_wp_error($comments)) {
            $debug_data = $comments->get_error_data();
            if (is_array($debug_data)) {
                $debug_data['json_error'] = json_last_error_msg();
                $debug_data['attempted_json'] = $json_string;
                $comments->add_data($debug_data);
            }
        }
        
        return $comments;
    }
    
    private function parse_comments_from_response($response, $product_name, $min_rating = 4, $max_rating = 5, $custom_names = array()) {
        // Initialize an array to store the parsed comments
        $comments = array();
        
        // Get comment language
        $comment_language = get_option('cg_comment_language', 'en');
        
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
        
        // Pattern 4: Markdown-style reviews with bold headers (for newer Gemini API responses)
        if (empty($comments) && preg_match_all('/\d+\.\s+\*\*(?:Customer\s+Name|Name):\*\*\s+([^\n]+)\n\s+\*\*(?:Star\s+Rating|Rating):\*\*\s+(\d+)[^\n]*\n\s+\*\*(?:Comment|Review):\*\*\s+(?:")?([^"]+)(?:")?/is', $response, $matches, PREG_SET_ORDER)) {
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
        
        // If all parsing attempts failed, create a more generic error with debug info
        if (empty($comments)) {
            $error = new WP_Error(
                'parsing_error',
                __('Could not parse comments from the AI response. Please try regenerating with a clearer prompt.', 'comment-generator')
            );
            
            // Add the raw API response as debug data
            $error->add_data(array(
                'raw_response' => $response,
                'product_name' => $product_name,
                'min_rating' => $min_rating,
                'max_rating' => $max_rating
            ));
            
            return $error;
        }
        
        // Make sure we have between 5-8 comments
        if (count($comments) < 5) {
            // If we have too few, generate some generic ones using custom names
            $generic_comments = $this->get_generic_comments_for_language($comment_language, $product_name);
            
            while (count($comments) < 5) {
                $comments[] = array(
                    'name' => $custom_names[array_rand($custom_names)],
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
    
    /**
     * Get generic comments for the specified language
     *
     * @param string $language_code The language code (e.g., 'en', 'tr')
     * @param string $product_name The product name to include in some comments
     * @return array Array of generic comments in the specified language
     */
    public function get_generic_comments_for_language($language_code, $product_name) {
        switch ($language_code) {
            case 'tr':
                return array(
                    "Harika bir ürün! Satın aldığım için çok memnunum.",
                    "Bu $product_name beklentilerimi aştı. Tekrar alırdım!",
                    "Mükemmel kalite ve hızlı kargo. Çok memnun bir müşteriyim.",
                    "Bunu bir süredir kullanıyorum ve hala çok iyi durumda.",
                    "İhtiyacım olan şey tam olarak buydu. Fiyatına göre iyi bir değer."
                );
            case 'es':
                return array(
                    "¡Excelente producto! Estoy muy satisfecho con mi compra.",
                    "Este $product_name superó mis expectativas. ¡Lo compraría de nuevo!",
                    "Excelente calidad y envío rápido. Cliente muy satisfecho.",
                    "He estado usando esto por un tiempo y sigue en excelente estado.",
                    "Perfecto para lo que necesitaba. Buena relación calidad-precio."
                );
            case 'fr':
                return array(
                    "Excellent produit ! Je suis très satisfait de mon achat.",
                    "Ce $product_name a dépassé mes attentes. J'achèterais à nouveau !",
                    "Excellente qualité et livraison rapide. Client très satisfait.",
                    "Je l'utilise depuis un certain temps et il tient toujours très bien.",
                    "Parfait pour ce dont j'avais besoin. Bon rapport qualité-prix."
                );
            case 'de':
                return array(
                    "Tolles Produkt! Ich bin mit meinem Kauf sehr zufrieden.",
                    "Dieses $product_name hat meine Erwartungen übertroffen. Würde es wieder kaufen!",
                    "Ausgezeichnete Qualität und schneller Versand. Sehr zufriedener Kunde.",
                    "Ich benutze es schon eine Weile und es hält immer noch großartig.",
                    "Perfekt für das, was ich brauchte. Gutes Preis-Leistungs-Verhältnis."
                );
            // Default to English
            default:
                return array(
                    "Great product! I'm very satisfied with my purchase.",
                    "This $product_name exceeded my expectations. Would buy again!",
                    "Excellent quality and fast shipping. Very happy customer.",
                    "I've been using this for a while now and it's holding up great.",
                    "Perfect for what I needed. Good value for money."
                );
        }
    }
} 