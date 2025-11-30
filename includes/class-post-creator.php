<?php
/**
 * Classe Post Creator - Gestisce la creazione dei post evento
 */

defined('ABSPATH') || exit;

class MEP_Post_Creator {
    
    /**
     * @var int ID del post template da clonare
     */
    private $template_post_id;
    
    /**
     * Constructor
     * 
     * @param int $template_post_id
     */
    public function __construct($template_post_id) {
        $this->template_post_id = $template_post_id;
    }
    
    /**
     * Crea un nuovo post evento completo
     * 
     * @param array $form_data Dati dal form
     * @return array|WP_Error Array con ID post e info foto, o WP_Error
     */
    public function create_event_post($form_data) {
        // 1. Sanitizza e valida i dati
        $data = MEP_Helpers::sanitize_form_data($form_data);
        if (is_wp_error($data)) {
            return $data;
        }
        
        MEP_Helpers::log_info("Inizio creazione evento: " . $data['event_title']);
        
        // 2. Valida folder Google Drive
        $folder_validation = MEP_Helpers::validate_folder_id($data['event_folder_id']);
        if (is_wp_error($folder_validation)) {
            return $folder_validation;
        }
        
        // 3. Valida foto selezionate (minimo 1 foto)
        $selected_photo_ids = !empty($form_data['selected_photo_ids']) 
            ? explode(',', sanitize_text_field($form_data['selected_photo_ids'])) 
            : [];
        
        if (empty($selected_photo_ids) || count($selected_photo_ids) < 1) {
            return new WP_Error(
                'invalid_photos',
                __('Devi selezionare almeno 1 foto', 'my-event-plugin')
            );
        }
        
        // 4. Valida featured image index
        $featured_index = isset($form_data['featured_image_index']) 
            ? absint($form_data['featured_image_index']) 
            : 0;
        
        // Assicurati che l'indice sia valido rispetto al numero di foto selezionate
        $max_index = count($selected_photo_ids) - 1;
        if ($featured_index < 0 || $featured_index > $max_index) {
            $featured_index = 0; // Default alla prima foto
        }
        
        // 5. Clona il post template
        $new_post_id = $this->clone_template_post();
        if (is_wp_error($new_post_id)) {
            return $new_post_id;
        }
        
        MEP_Helpers::log_info("Post clonato con ID: {$new_post_id}");
        
        // 6. Aggiorna titolo e contenuto
        $this->update_post_content($new_post_id, $data);
        
        // 7. Imposta categoria
        if (!empty($data['event_category'])) {
            wp_set_post_categories($new_post_id, [$data['event_category']]);
        }
        
        // 8. Importa le foto selezionate da Google Drive
        MEP_Helpers::log_info("Inizio import di " . count($selected_photo_ids) . " foto selezionate");
        $attachment_ids = MEP_GDrive_Integration::import_specific_photos($selected_photo_ids);
        
        if (is_wp_error($attachment_ids)) {
            // Se fallisce l'import, elimina il post draft
            wp_delete_post($new_post_id, true);
            return $attachment_ids;
        }
        
        MEP_Helpers::log_info("Foto importate: " . count($attachment_ids));
        
        // 9. Imposta featured image (quella scelta dall'utente)
        if (!empty($attachment_ids[$featured_index])) {
            set_post_thumbnail($new_post_id, $attachment_ids[$featured_index]);
            MEP_Helpers::log_info("Featured image impostata: " . $attachment_ids[$featured_index] . " (foto #{$featured_index})");
        }
        
        // 10. Crea galleria responsive
        $gallery_shortcode = MEP_GDrive_Integration::create_gallery_shortcode(
            $data['event_folder_id']
        );
        
        // 11. Aggiungi galleria al contenuto
        $this->append_gallery_to_content($new_post_id, $gallery_shortcode);
        
        // 12. Salva metadati SEO (Rank Math se disponibile)
        if (MEP_Helpers::is_rankmath_active()) {
            $this->save_rankmath_seo($new_post_id, $data);
        }
        
        // 13. Salva metadati custom
        $this->save_custom_metadata($new_post_id, [
            'gdrive_folder_id' => $data['event_folder_id'],
            'gdrive_folder_name' => $data['event_folder_name'],
            'gdrive_photos' => $attachment_ids,
            'gdrive_photo_ids' => $selected_photo_ids,
            'featured_image_index' => $featured_index,
            'created_by_plugin' => true,
            'creation_date' => current_time('mysql')
        ]);
        
        // 14. Aggiorna lo stato a "bozza" o "pubblicato" in base alle impostazioni
        $auto_publish = get_option('mep_auto_publish', 'no');
        if ($auto_publish === 'yes') {
            wp_update_post([
                'ID' => $new_post_id,
                'post_status' => 'publish'
            ]);
            MEP_Helpers::log_info("Post pubblicato automaticamente");
        }
        
        MEP_Helpers::log_info("Evento creato con successo! Post ID: {$new_post_id}");
        
        // 15. Ottieni gli URL delle foto importate per mostrarli all'utente
        $photo_urls = [];
        foreach ($attachment_ids as $attachment_id) {
            $photo_urls[] = wp_get_attachment_url($attachment_id);
        }
        
        // Hook per estensioni future
        do_action('mep_after_event_created', $new_post_id, $data, $attachment_ids);
        
        // Restituisci array con tutte le info necessarie
        return [
            'post_id' => $new_post_id,
            'attachment_ids' => $attachment_ids,
            'photo_urls' => $photo_urls,
            'featured_index' => $featured_index
        ];
    }
    
    /**
     * Clona il post template
     * 
     * @return int|WP_Error ID del nuovo post o WP_Error
     */
    private function clone_template_post() {
        // Verifica che il template esista
        if (!MEP_Helpers::is_valid_post($this->template_post_id)) {
            return new WP_Error(
                'template_not_found',
                sprintf(
                    __('Post template con ID %d non trovato. Configura l\'ID corretto nelle impostazioni.', 'my-event-plugin'),
                    $this->template_post_id
                )
            );
        }
        
        $template = get_post($this->template_post_id);
        
        // Crea il nuovo post
        $new_post = [
            'post_title'    => __('Evento in creazione...', 'my-event-plugin'),
            'post_content'  => $template->post_content,
            'post_status'   => 'draft',
            'post_type'     => $template->post_type,
            'post_author'   => get_current_user_id(),
            'post_excerpt'  => $template->post_excerpt,
        ];
        
        $new_post_id = wp_insert_post($new_post);
        
        if (is_wp_error($new_post_id)) {
            return $new_post_id;
        }
        
        // Copia i custom fields del template (esclusi quelli privati)
        $meta_keys = get_post_custom_keys($this->template_post_id);
        if ($meta_keys) {
            foreach ($meta_keys as $key) {
                // Salta metadati privati di WordPress
                if (substr($key, 0, 1) === '_') {
                    continue;
                }
                
                $values = get_post_meta($this->template_post_id, $key, false);
                foreach ($values as $value) {
                    add_post_meta($new_post_id, $key, $value);
                }
            }
        }
        
        // Copia i tag
        $tags = wp_get_post_tags($this->template_post_id, ['fields' => 'names']);
        if (!empty($tags)) {
            wp_set_post_tags($new_post_id, $tags);
        }
        
        return $new_post_id;
    }
    
    /**
     * Aggiorna titolo e contenuto del post
     * 
     * @param int $post_id
     * @param array $data
     */
    private function update_post_content($post_id, $data) {
        $title = $data['event_title'];
        $content = $data['event_content'];
        
        // Se il contenuto è vuoto, mantieni quello del template
        if (empty($content)) {
            $template = get_post($this->template_post_id);
            $content = $template->post_content;
        }
        
        wp_update_post([
            'ID' => $post_id,
            'post_title' => $title,
            'post_content' => $content
        ]);
    }
    
    /**
     * Aggiunge la galleria Use-your-Drive al contenuto del post
     * Inserisce lo shortcode alla fine del contenuto dell'articolo
     * 
     * @param int $post_id ID del post
     * @param string $gallery_shortcode Lo shortcode della galleria
     */
    private function append_gallery_to_content($post_id, $gallery_shortcode) {
        $post = get_post($post_id);
        $content = $post->post_content;
        
        // Aggiungi lo shortcode Use-your-Drive in fondo al contenuto
        // Lo shortcode viene aggiunto su una nuova riga dopo il contenuto principale
        if (!empty($gallery_shortcode)) {
            $content .= "\n\n" . $gallery_shortcode;
        }
        
        wp_update_post([
            'ID' => $post_id,
            'post_content' => $content
        ]);
        
        MEP_Helpers::log_info("Galleria Use-your-Drive aggiunta al post {$post_id}");
    }
    
    /**
     * Salva metadati SEO per Rank Math
     * 
     * @param int $post_id
     * @param array $data
     */
    private function save_rankmath_seo($post_id, $data) {
        // Focus Keyword
        if (!empty($data['seo_focus_keyword'])) {
            update_post_meta($post_id, 'rank_math_focus_keyword', $data['seo_focus_keyword']);
        }
        
        // Titolo SEO
        if (!empty($data['seo_title'])) {
            update_post_meta($post_id, 'rank_math_title', $data['seo_title']);
        } else {
            // Usa il titolo del post se non specificato
            update_post_meta($post_id, 'rank_math_title', $data['event_title']);
        }
        
        // Meta Description
        if (!empty($data['seo_description'])) {
            update_post_meta($post_id, 'rank_math_description', $data['seo_description']);
        }
        
        MEP_Helpers::log_info("Metadati SEO Rank Math salvati per post {$post_id}");
    }
    
    /**
     * Salva metadati personalizzati del plugin
     * 
     * @param int $post_id
     * @param array $metadata
     */
    private function save_custom_metadata($post_id, $metadata) {
        foreach ($metadata as $key => $value) {
            update_post_meta($post_id, '_mep_' . $key, $value);
        }
        
        MEP_Helpers::log_info("Metadati custom salvati per post {$post_id}");
    }
}