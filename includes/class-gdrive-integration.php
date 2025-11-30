<?php
/**
 * Classe GDrive Integration - Gestisce l'integrazione con Google Drive via Use-your-Drive
 */

defined('ABSPATH') || exit;

class MEP_GDrive_Integration {
    
    /**
     * Ottieni lista di ID immagini da una cartella Google Drive
     * 
     * @param string $folder_id ID cartella Google Drive
     * @return array Array di file IDs
     */
    public static function get_image_ids_from_folder($folder_id) {
        if (empty($folder_id)) {
            return [];
        }
        
        try {
            $folder = \TheLion\UseyourDrive\Client::instance()->get_folder($folder_id);
            
            if (empty($folder['contents'])) {
                MEP_Helpers::log_info("Cartella {$folder_id} vuota o non accessibile");
                return [];
            }
            
            $image_mimetypes = [
                'image/jpeg',
                'image/jpg',
                'image/png',
                'image/gif',
                'image/webp',
                'image/bmp'
            ];
            
            $image_ids = [];
            foreach ($folder['contents'] as $cached_node) {
                // Verifica che sia un file (non una cartella)
                if (!$cached_node->get_entry()->is_file()) {
                    continue;
                }
                
                $mimetype = $cached_node->get_entry()->get_mimetype();
                
                if (in_array($mimetype, $image_mimetypes)) {
                    $image_ids[] = $cached_node->get_id();
                }
            }
            
            MEP_Helpers::log_info("Trovate " . count($image_ids) . " immagini nella cartella {$folder_id}");
            
            return $image_ids;
            
        } catch (Exception $e) {
            MEP_Helpers::log_error("Errore nel recupero immagini dalla cartella {$folder_id}", $e->getMessage());
            return [];
        }
    }
    
    /**
     * Ottieni lista foto con thumbnails per la griglia di selezione
     * 🚀 USA GOOGLE DRIVE API DIRETTA - bypassa Use-your-Drive per evitare errori di cache
     * 
     * @param string $folder_id ID cartella Google Drive
     * @return array|WP_Error Array di foto con info e thumbnail
     */
    public static function get_photos_list_with_thumbnails($folder_id) {
        if (empty($folder_id)) {
            return new WP_Error('empty_folder_id', __('ID cartella vuoto', 'my-event-plugin'));
        }
        
        MEP_Helpers::log_info("📁 Tentativo di accesso alla cartella: {$folder_id} (via Google Drive API diretta)");
        
        try {
            // 1. Verifica accesso alla cartella
            $access_check = MEP_Google_Drive_API::verify_folder_access($folder_id);
            
            if (is_wp_error($access_check)) {
                MEP_Helpers::log_error("❌ Verifica accesso fallita", $access_check->get_error_message());
                return $access_check;
            }
            
            // 2. Ottieni lista file via Google Drive API diretta
            $files = MEP_Google_Drive_API::list_files_in_folder($folder_id, 'image/');
            
            if (is_wp_error($files)) {
                MEP_Helpers::log_error("❌ Errore lista file", $files->get_error_message());
                return $files;
            }
            
            // 3. Se non ci sono foto
            if (empty($files)) {
                return new WP_Error('no_photos', __('Nessuna foto trovata nella cartella. Assicurati che la cartella contenga file immagine (JPG, PNG, GIF, WebP).', 'my-event-plugin'));
            }
            
            // 4. Trasforma i file in formato compatibile con la UI
            $photos = [];

            foreach ($files as $file) {
                // 🔧 FIX: Usa thumbnailLink fornito dall'API di Google Drive
                // Questo link è già autenticato e pronto per l'uso
                $thumbnail_url = isset($file['thumbnailLink']) ? $file['thumbnailLink'] : '';

                // Se non c'è thumbnail, usa l'icona
                if (empty($thumbnail_url)) {
                    $thumbnail_url = isset($file['iconLink']) ? $file['iconLink'] : '';
                }

                // Se ancora non c'è, costruisci URL manuale come fallback
                if (empty($thumbnail_url)) {
                    $thumbnail_url = MEP_Google_Drive_API::get_thumbnail_url($file['id'], 400);
                }

                $photos[] = [
                    'id' => $file['id'],
                    'name' => $file['name'],
                    'thumbnail' => $thumbnail_url,
                    'size' => isset($file['size']) ? $file['size'] : 0,
                    'mimetype' => isset($file['mimeType']) ? $file['mimeType'] : 'image/jpeg'
                ];
            }
            
            MEP_Helpers::log_info("✅ Trovate " . count($photos) . " foto nella cartella {$folder_id} (via API diretta)");
            
            return $photos;
            
        } catch (Throwable $e) {
            MEP_Helpers::log_error("❌ Errore nel recupero foto dalla cartella {$folder_id}", [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return new WP_Error('api_error', sprintf(
                __('Errore API Google Drive: %s', 'my-event-plugin'),
                $e->getMessage()
            ));
        }
    }
    
    /**
     * Importa foto specifiche da Google Drive nella Media Library WordPress
     * 🚀 USA GOOGLE DRIVE API DIRETTA - bypassa Use-your-Drive
     * 
     * @param array $photo_ids Array di ID foto da importare
     * @param array $photo_names Array di nomi foto (opzionale)
     * @return array|WP_Error Array di attachment IDs o WP_Error
     */
    public static function import_specific_photos($photo_ids, $photo_names = []) {
        if (empty($photo_ids) || !is_array($photo_ids)) {
            return new WP_Error('empty_photo_ids', __('Lista foto vuota', 'my-event-plugin'));
        }
        
        MEP_Helpers::log_info("📥 Inizio import di " . count($photo_ids) . " foto selezionate (via API diretta)");
        
        // Usa la nuova API diretta per importare
        $attachment_ids = MEP_Google_Drive_API::import_files($photo_ids, $photo_names);
        
        if (is_wp_error($attachment_ids)) {
            MEP_Helpers::log_error("❌ Errore import foto", $attachment_ids->get_error_message());
            return $attachment_ids;
        }
        
        MEP_Helpers::log_info("✅ Import completato: " . count($attachment_ids) . " foto importate (via API diretta)");
        
        return $attachment_ids;
    }
    
    /**
     * Importa foto da Google Drive nella Media Library WordPress (metodo legacy)
     * 
     * @param string $folder_id ID cartella Google Drive
     * @param int $limit Numero massimo di foto da importare (default 4)
     * @return array|WP_Error Array di attachment IDs o WP_Error
     */
    public static function import_photos_from_folder($folder_id, $limit = 4) {
        if (empty($folder_id)) {
            return new WP_Error('empty_folder_id', __('ID cartella vuoto', 'my-event-plugin'));
        }
        
        // 1. Ottieni lista immagini
        $image_ids = self::get_image_ids_from_folder($folder_id);
        
        if (empty($image_ids)) {
            return new WP_Error(
                'no_images',
                __('Nessuna immagine trovata nella cartella selezionata.', 'my-event-plugin')
            );
        }
        
        // 2. Verifica numero minimo immagini
        $min_photos = get_option('mep_min_photos', 4);
        if (count($image_ids) < $min_photos) {
            return new WP_Error(
                'insufficient_images',
                sprintf(
                    __('La cartella contiene solo %d immagini. Servono almeno %d foto.', 'my-event-plugin'),
                    count($image_ids),
                    $min_photos
                )
            );
        }
        
        // 3. Limita al numero richiesto
        $image_ids = array_slice($image_ids, 0, $limit);
        
        // 4. Usa il nuovo metodo per importare
        return self::import_specific_photos($image_ids);
    }
    
    /**
     * Conta il numero di immagini in una cartella
     * 
     * @param string $folder_id
     * @return int
     */
    public static function count_images_in_folder($folder_id) {
        $image_ids = self::get_image_ids_from_folder($folder_id);
        return count($image_ids);
    }
    
    /**
     * Ottieni dettagli di una cartella
     * 
     * @param string $folder_id
     * @return array|false
     */
    public static function get_folder_details($folder_id) {
        try {
            $folder = \TheLion\UseyourDrive\Client::instance()->get_folder($folder_id);
            
            if (empty($folder)) {
                return false;
            }
            
            $image_count = self::count_images_in_folder($folder_id);
            $total_files = count($folder['contents']);
            
            return [
                'id' => $folder['folder']->get_id(),
                'name' => $folder['folder']->get_name(),
                'path' => $folder['folder']->get_path('root'),
                'total_files' => $total_files,
                'image_count' => $image_count,
                'has_enough_images' => $image_count >= get_option('mep_min_photos', 4)
            ];
            
        } catch (Exception $e) {
            MEP_Helpers::log_error("Errore recupero dettagli cartella {$folder_id}", $e->getMessage());
            return false;
        }
    }
    
    /**
     * Crea shortcode galleria Use-your-Drive
     * Genera lo shortcode semplificato con solo dir e mode="gallery"
     * 
     * @param string $folder_id ID della cartella Google Drive
     * @param array $custom_params Parametri personalizzati (opzionale)
     * @return string Lo shortcode Use-your-Drive formattato
     */
    public static function create_gallery_shortcode($folder_id, $custom_params = []) {
        // Sanifica l'ID della cartella
        $gallery_folder_id = esc_attr($folder_id);
        
        // Shortcode semplificato: solo dir e mode="gallery" come richiesto
        // Se non vengono passati parametri custom, usa solo i parametri essenziali
        if (empty($custom_params)) {
            return '[useyourdrive dir="' . $gallery_folder_id . '" mode="gallery"]';
        }
        
        // Se ci sono parametri custom, usa quelli
        $default_params = [
            'dir' => $gallery_folder_id,
            'mode' => 'gallery'
        ];
        
        $params = array_merge($default_params, $custom_params);
        
        $shortcode = '[useyourdrive';
        foreach ($params as $key => $value) {
            $shortcode .= ' ' . $key . '="' . esc_attr($value) . '"';
        }
        $shortcode .= ']';
        
        return $shortcode;
    }
    
    /**
     * Verifica se una cartella è accessibile
     * 
     * @param string $folder_id
     * @return bool
     */
    public static function is_folder_accessible($folder_id) {
        try {
            $folder = \TheLion\UseyourDrive\Client::instance()->get_folder($folder_id);
            return !empty($folder);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Ottieni il primo account Google Drive disponibile
     * 
     * @return object|false
     */
    public static function get_primary_account() {
        try {
            $accounts = \TheLion\UseyourDrive\Accounts::instance()->list_accounts();
            
            if (empty($accounts)) {
                return false;
            }
            
            // Ritorna il primo account con token valido
            foreach ($accounts as $account) {
                if ($account->get_authorization()->has_access_token()) {
                    return $account;
                }
            }
            
            return false;
            
        } catch (Exception $e) {
            MEP_Helpers::log_error("Errore recupero account primario", $e->getMessage());
            return false;
        }
    }
}