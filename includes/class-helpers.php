<?php
/**
 * Classe Helpers - Funzioni di utilità
 */

defined('ABSPATH') || exit;

class MEP_Helpers {
    
    /**
     * Verifica che Use-your-Drive sia installato e configurato correttamente
     * 
     * @return bool|WP_Error True se OK, WP_Error se ci sono problemi
     */
    public static function check_useyourdrive_ready() {
        // Verifica che la classe principale esista
        if (!class_exists('TheLion\UseyourDrive\Core')) {
            return new WP_Error(
                'plugin_missing',
                __('Il plugin Use-your-Drive non è installato o non è attivo.', 'my-event-plugin')
            );
        }
        
        // Verifica che ci siano account connessi
        $accounts = \TheLion\UseyourDrive\Accounts::instance()->list_accounts();
        if (empty($accounts)) {
            return new WP_Error(
                'no_accounts',
                __('Nessun account Google Drive è connesso in Use-your-Drive. Per favore collega un account nelle impostazioni di Use-your-Drive.', 'my-event-plugin')
            );
        }
        
        // Verifica che almeno un account abbia un token valido
        $has_valid_account = false;
        foreach ($accounts as $account) {
            if ($account->get_authorization()->has_access_token()) {
                $has_valid_account = true;
                break;
            }
        }
        
        if (!$has_valid_account) {
            return new WP_Error(
                'no_valid_token',
                __('Nessun account Google Drive ha un\'autorizzazione valida. Per favore riautorizza l\'account in Use-your-Drive.', 'my-event-plugin')
            );
        }
        
        return true;
    }
    
    /**
     * Valida un folder ID di Google Drive
     * 
     * @param string $folder_id
     * @return bool|WP_Error
     */
    public static function validate_folder_id($folder_id) {
        if (empty($folder_id)) {
            return new WP_Error('empty_id', __('ID cartella vuoto', 'my-event-plugin'));
        }
        
        // Usa l'API Google Drive diretta per validare la cartella
        // Questo bypassa Use-your-Drive ed evita problemi di compatibilità
        return MEP_Google_Drive_API::verify_folder_access($folder_id);
    }
    
    /**
     * Ottieni informazioni su una cartella
     * 
     * @param string $folder_id
     * @return array|false
     */
    public static function get_folder_info($folder_id) {
        try {
            $folder = \TheLion\UseyourDrive\Client::instance()->get_folder($folder_id);
            
            if (empty($folder)) {
                return false;
            }
            
            return [
                'id' => $folder['folder']->get_id(),
                'name' => $folder['folder']->get_name(),
                'file_count' => count($folder['contents']),
                'path' => $folder['folder']->get_path('root')
            ];
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Sanitizza e valida i dati del form
     * 
     * @param array $data
     * @return array|WP_Error
     */
    public static function sanitize_form_data($data) {
        $sanitized = [];
        
        // Titolo (obbligatorio)
        if (empty($data['event_title'])) {
            return new WP_Error('missing_title', __('Il titolo dell\'evento è obbligatorio', 'my-event-plugin'));
        }
        $sanitized['event_title'] = sanitize_text_field($data['event_title']);
        
        // Categoria (obbligatoria)
        if (empty($data['event_category'])) {
            return new WP_Error('missing_category', __('La categoria è obbligatoria', 'my-event-plugin'));
        }
        $sanitized['event_category'] = absint($data['event_category']);
        
        // Contenuto HTML
        $sanitized['event_content'] = wp_kses_post($data['event_content'] ?? '');
        
        // SEO
        $sanitized['seo_focus_keyword'] = sanitize_text_field($data['seo_focus_keyword'] ?? '');
        $sanitized['seo_title'] = sanitize_text_field($data['seo_title'] ?? '');
        $sanitized['seo_description'] = sanitize_textarea_field($data['seo_description'] ?? '');
        
        // Folder Google Drive (obbligatorio)
        if (empty($data['event_folder_id'])) {
            return new WP_Error('missing_folder', __('Devi selezionare una cartella Google Drive', 'my-event-plugin'));
        }
        $sanitized['event_folder_id'] = sanitize_text_field($data['event_folder_id']);
        $sanitized['event_folder_account'] = sanitize_text_field($data['event_folder_account'] ?? '');
        $sanitized['event_folder_name'] = sanitize_text_field($data['event_folder_name'] ?? '');
        
        return $sanitized;
    }
    
    /**
     * Log error nel debug.log di WordPress
     * 
     * @param string $message
     * @param mixed $data
     */
    public static function log_error($message, $data = null) {
        if (defined('WP_DEBUG') && WP_DEBUG === true) {
            error_log('[My Event Plugin] ' . $message);
            if ($data !== null) {
                error_log(print_r($data, true));
            }
        }
    }
    
    /**
     * Log info nel debug.log di WordPress
     * 
     * @param string $message
     * @param mixed $data
     */
    public static function log_info($message, $data = null) {
        if (defined('WP_DEBUG') && WP_DEBUG === true && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG === true) {
            error_log('[My Event Plugin] INFO: ' . $message);
            if ($data !== null) {
                error_log(print_r($data, true));
            }
        }
    }
    
    /**
     * Formatta un messaggio di errore per l'utente
     * 
     * @param WP_Error $error
     * @return string
     */
    public static function format_error_message($error) {
        if (!is_wp_error($error)) {
            return __('Errore sconosciuto', 'my-event-plugin');
        }
        
        $code = $error->get_error_code();
        $message = $error->get_error_message();
        
        // Log per debug
        self::log_error("Error [{$code}]: {$message}");
        
        return $message;
    }
    
    /**
     * Verifica se un post ID è valido
     * 
     * @param int $post_id
     * @return bool
     */
    public static function is_valid_post($post_id) {
        if (empty($post_id) || $post_id <= 0) {
            return false;
        }
        
        $post = get_post($post_id);
        return !empty($post);
    }
    
    /**
     * Ottieni le categorie disponibili per il form
     * 
     * @return array
     */
    public static function get_available_categories() {
        $categories = get_categories([
            'orderby' => 'name',
            'order' => 'ASC',
            'hide_empty' => false
        ]);
        
        $result = [];
        foreach ($categories as $cat) {
            $result[$cat->term_id] = $cat->name;
        }
        
        return $result;
    }
    
    /**
     * Verifica se Rank Math è attivo
     * 
     * @return bool
     */
    public static function is_rankmath_active() {
        return class_exists('RankMath');
    }
    
    /**
     * Ottieni lo shortcode di default per la galleria
     * 
     * @param string $folder_id
     * @return string
     */
    public static function get_default_gallery_shortcode($folder_id) {
        $shortcode = '[useyourdrive dir="' . esc_attr($folder_id) . '" ';
        $shortcode .= 'mode="gallery" ';
        $shortcode .= 'maxheight="500px" ';
        $shortcode .= 'targetheight="200" ';
        $shortcode .= 'sortfield="name" ';
        $shortcode .= 'include_ext="jpg,jpeg,png,gif,webp" ';
        $shortcode .= 'showfilenames="0" ';
        $shortcode .= 'lightbox="1" ';
        $shortcode .= 'class="mep-gallery-responsive"]';
        
        return $shortcode;
    }
}