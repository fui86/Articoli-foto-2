<?php
/**
 * Plugin Name: Gestore Eventi Automatico
 * Plugin URI: https://tuosito.it
 * Description: Crea automaticamente articoli per eventi con foto da Google Drive con OAuth nativo
 * Version: 1.2.0
 * Author: Il Tuo Nome
 * Author URI: https://tuosito.it
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: my-event-plugin
 * Domain Path: /languages
 */

defined('ABSPATH') || exit;

// Definisci costanti
define('MEP_VERSION', '1.0.0');
define('MEP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MEP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MEP_PLUGIN_FILE', __FILE__);

/**
 * Classe principale del plugin
 */
class My_Event_Plugin {
    
    /**
     * @var My_Event_Plugin Singleton instance
     */
    private static $instance = null;
    
    /**
     * ID del post template da clonare
     * MODIFICA QUESTO VALORE con l'ID del tuo post template
     */
    private $template_post_id = 0; // ⚠️ INSERISCI QUI L'ID DEL TUO TEMPLATE!
    
    /**
     * Get singleton instance
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Carica le classi
        $this->load_dependencies();
        
        // Verifica dipendenze
        add_action('admin_init', [$this, 'check_dependencies']);
        
        // Menu admin
        add_action('admin_menu', [$this, 'add_admin_menu']);
        
        // Enqueue assets
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        
        // AJAX handlers
        add_action('wp_ajax_mep_process_event_creation', [$this, 'handle_event_creation']);
        add_action('wp_ajax_mep_validate_folder', [$this, 'handle_folder_validation']);
        add_action('wp_ajax_mep_get_folder_photos', [$this, 'handle_get_folder_photos']);
        add_action('wp_ajax_mep_get_template_preview', [$this, 'handle_get_template_preview']);
        add_action('wp_ajax_mep_browse_gdrive_folder', [$this, 'handle_browse_gdrive_folder']); // 🚀 Nuovo browser
        add_action('wp_ajax_mep_proxy_thumbnail', [$this, 'handle_proxy_thumbnail']); // 🖼️ Proxy miniature
        add_action('wp_ajax_mep_import_photos_only', [$this, 'handle_import_photos_only']); // 📸 Importa solo foto
        
        // Shortcode per frontend (opzionale)
        add_shortcode('my_event_form', [$this, 'render_frontend_form']);
        
        // Hook per tracciare import
        add_action('useyourdrive_imported_entry', [$this, 'track_imported_file'], 10, 2);
        
        // Enqueue CSS responsive galleria nel frontend
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_styles']);
    }
    
    /**
     * Carica CSS responsive per la galleria nel frontend
     */
    public function enqueue_frontend_styles() {
        // Carica solo se l'opzione è attiva
        if (get_option('mep_gallery_responsive', 'yes') === 'yes') {
            wp_enqueue_style(
                'mep-gallery-responsive',
                MEP_PLUGIN_URL . 'assets/css/gallery-responsive.css',
                [],
                MEP_VERSION
            );
        }
    }
    
    /**
     * Carica le dipendenze
     */
    private function load_dependencies() {
        require_once MEP_PLUGIN_DIR . 'includes/class-helpers.php';
        require_once MEP_PLUGIN_DIR . 'includes/class-google-oauth.php'; // 🔐 OAuth Google
        require_once MEP_PLUGIN_DIR . 'includes/class-google-drive-api.php'; // 🚀 API diretta
        require_once MEP_PLUGIN_DIR . 'includes/class-post-creator.php';
        require_once MEP_PLUGIN_DIR . 'includes/class-gdrive-integration.php';
    }
    
    /**
     * Verifica configurazione OAuth Google
     */
    public function check_dependencies() {
        // Verifica OAuth solo se si è in una pagina del plugin
        if (isset($_GET['page']) && in_array($_GET['page'], ['my-event-creator', 'my-event-settings'])) {
            if (!MEP_Google_OAuth::is_authorized()) {
                add_action('admin_notices', [$this, 'oauth_notice']);
            }
        }
        
        return true;
    }
    
    /**
     * Notice se OAuth non configurato
     */
    public function oauth_notice() {
        $settings_url = admin_url('admin.php?page=my-event-settings');
        ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php _e('Gestore Eventi Automatico', 'my-event-plugin'); ?>:</strong>
                <?php printf(
                    __('⚠️ Google Drive non è autorizzato. <a href="%s"><strong>Configura OAuth nelle Impostazioni</strong></a> per usare il browser cartelle.', 'my-event-plugin'),
                    esc_url($settings_url)
                ); ?>
            </p>
        </div>
        <?php
    }
    
    /**
     * Aggiungi menu nell'admin
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Crea Evento', 'my-event-plugin'),
            __('Eventi Auto', 'my-event-plugin'),
            'publish_posts',
            'my-event-creator',
            [$this, 'render_admin_page'],
            'dashicons-calendar-alt',
            25
        );
        
        // Sottomenu impostazioni
        add_submenu_page(
            'my-event-creator',
            __('Impostazioni', 'my-event-plugin'),
            __('Impostazioni', 'my-event-plugin'),
            'manage_options',
            'my-event-settings',
            [$this, 'render_settings_page']
        );
    }
    
    /**
     * Carica CSS e JS nell'admin
     */
    public function enqueue_admin_assets($hook) {
        // Carica solo nella pagina del plugin
        if (!in_array($hook, ['toplevel_page_my-event-creator', 'eventi-auto_page_my-event-settings'])) {
            return;
        }
        
        // CSS
        wp_enqueue_style(
            'mep-admin-style',
            MEP_PLUGIN_URL . 'assets/css/admin-style.css',
            [],
            MEP_VERSION
        );
        
        // JS
        wp_enqueue_script(
            'mep-admin-script',
            MEP_PLUGIN_URL . 'assets/js/admin-script.js',
            ['jquery'],
            MEP_VERSION,
            true
        );
        
        // Localizza script
        wp_localize_script('mep-admin-script', 'mepData', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mep_nonce'),
            'strings' => [
                'processing' => __('Creazione in corso...', 'my-event-plugin'),
                'success' => __('Evento creato con successo!', 'my-event-plugin'),
                'error' => __('Errore durante la creazione', 'my-event-plugin'),
                'select_folder' => __('Seleziona una cartella Google Drive!', 'my-event-plugin'),
                'validating' => __('Validazione cartella in corso...', 'my-event-plugin'),
                'folder_valid' => __('Cartella valida!', 'my-event-plugin'),
                'folder_invalid' => __('Cartella non valida', 'my-event-plugin'),
            ]
        ]);
    }
    
    /**
     * Renderizza la pagina admin principale
     */
    public function render_admin_page() {
        if (!current_user_can('publish_posts')) {
            wp_die(__('Non hai i permessi per accedere a questa pagina.'));
        }
        
        require_once MEP_PLUGIN_DIR . 'templates/admin-page.php';
    }
    
    /**
     * Renderizza la pagina impostazioni
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Non hai i permessi per accedere a questa pagina.'));
        }
        
        require_once MEP_PLUGIN_DIR . 'templates/settings-page.php';
    }
    
    /**
     * Handler AJAX per creare l'evento
     */
    public function handle_event_creation() {
        // Verifica nonce di sicurezza (passato sia dal form che da mepData.nonce)
        check_ajax_referer('mep_nonce', 'nonce');
        
        if (!current_user_can('publish_posts')) {
            wp_send_json_error(['message' => __('Permessi insufficienti', 'my-event-plugin')]);
        }
        
        // Recupera template ID dalle impostazioni salvate
        $template_id = $this->get_template_post_id();
        
        // Valida template ID
        if (empty($template_id) || $template_id === 0) {
            wp_send_json_error([
                'message' => __('ID template post non configurato! Vai in Impostazioni.', 'my-event-plugin')
            ]);
        }
        
        try {
            $creator = new MEP_Post_Creator($template_id);
            $result = $creator->create_event_post($_POST);
            
            if (is_wp_error($result)) {
                wp_send_json_error(['message' => $result->get_error_message()]);
            }
            
            // Il risultato ora è un array con post_id, photo_urls, ecc.
            $post_id = $result['post_id'];
            
            wp_send_json_success([
                'post_id' => $post_id,
                'edit_url' => get_edit_post_link($post_id, 'raw'),
                'view_url' => get_permalink($post_id),
                'photo_urls' => $result['photo_urls'],
                'attachment_ids' => $result['attachment_ids'],
                'featured_index' => $result['featured_index']
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
    /**
     * Handler AJAX per validare una cartella
     */
    public function handle_folder_validation() {
        check_ajax_referer('mep_nonce', 'nonce');
        
        $folder_id = sanitize_text_field($_POST['folder_id'] ?? '');
        
        if (empty($folder_id)) {
            wp_send_json_error(['message' => __('ID cartella mancante', 'my-event-plugin')]);
        }
        
        $validation = MEP_Helpers::validate_folder_id($folder_id);
        
        if (is_wp_error($validation)) {
            wp_send_json_error(['message' => $validation->get_error_message()]);
        }
        
        // Conta le immagini
        $image_count = MEP_GDrive_Integration::count_images_in_folder($folder_id);
        
        wp_send_json_success([
            'valid' => true,
            'image_count' => $image_count,
            'message' => sprintf(
                __('Cartella valida! Contiene %d immagini.', 'my-event-plugin'),
                $image_count
            )
        ]);
    }
    
    /**
     * Handler AJAX per recuperare le foto da una cartella
     */
    public function handle_get_folder_photos() {
        // Verifica nonce
        check_ajax_referer('mep_nonce', 'nonce');
        
        // Log richiesta
        MEP_Helpers::log_info("AJAX: handle_get_folder_photos chiamato");
        
        // Ottieni folder ID
        $folder_id = sanitize_text_field($_POST['folder_id'] ?? '');
        
        if (empty($folder_id)) {
            MEP_Helpers::log_error("AJAX: ID cartella mancante");
            wp_send_json_error([
                'message' => __('ID cartella mancante', 'my-event-plugin'),
                'code' => 'missing_folder_id'
            ]);
            return;
        }
        
        MEP_Helpers::log_info("AJAX: Recupero foto dalla cartella: {$folder_id}");
        
        try {
            // Ottieni la lista delle foto con thumbnail
            $photos = MEP_GDrive_Integration::get_photos_list_with_thumbnails($folder_id);
            
            if (is_wp_error($photos)) {
                MEP_Helpers::log_error("AJAX: Errore WP_Error", [
                    'code' => $photos->get_error_code(),
                    'message' => $photos->get_error_message()
                ]);
                
                wp_send_json_error([
                    'message' => $photos->get_error_message(),
                    'code' => $photos->get_error_code(),
                    'folder_id' => $folder_id
                ]);
                return;
            }
            
            if (empty($photos)) {
                MEP_Helpers::log_error("AJAX: Nessuna foto trovata");
                wp_send_json_error([
                    'message' => __('Nessuna foto trovata nella cartella', 'my-event-plugin'),
                    'code' => 'no_photos'
                ]);
                return;
            }
            
            MEP_Helpers::log_info("AJAX: Successo! Trovate " . count($photos) . " foto");
            
            wp_send_json_success([
                'photos' => $photos,
                'count' => count($photos),
                'folder_id' => $folder_id
            ]);
            
        } catch (Throwable $e) {
            MEP_Helpers::log_error("AJAX: Errore catturato", [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            wp_send_json_error([
                'message' => sprintf(
                    __('Errore server: %s', 'my-event-plugin'),
                    $e->getMessage()
                ),
                'code' => 'exception',
                'folder_id' => $folder_id
            ]);
        }
    }
    
    /**
     * Handler AJAX per ottenere anteprima template
     */
    public function handle_get_template_preview() {
        check_ajax_referer('mep_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permessi insufficienti', 'my-event-plugin')]);
        }
        
        $post_id = absint($_POST['post_id'] ?? 0);
        
        if (empty($post_id)) {
            wp_send_json_error(['message' => __('ID post mancante', 'my-event-plugin')]);
        }
        
        $post = get_post($post_id);
        
        if (!$post) {
            wp_send_json_error(['message' => __('Post non trovato', 'my-event-plugin')]);
        }
        
        // Genera HTML anteprima
        $categories = get_the_category($post_id);
        $cat_names = !empty($categories) ? implode(', ', wp_list_pluck($categories, 'name')) : __('Nessuna categoria', 'my-event-plugin');
        
        $status_labels = [
            'publish' => __('Pubblicato', 'my-event-plugin'),
            'draft' => __('Bozza', 'my-event-plugin'),
            'pending' => __('In attesa di revisione', 'my-event-plugin'),
            'private' => __('Privato', 'my-event-plugin')
        ];
        $status = isset($status_labels[$post->post_status]) ? $status_labels[$post->post_status] : $post->post_status;
        
        ob_start();
        ?>
        <h4 style="margin-top: 0; color: #2271b1;">
            <span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span>
            <?php _e('Template Selezionato:', 'my-event-plugin'); ?>
        </h4>
        <p style="margin: 10px 0;">
            <strong><?php _e('Titolo:', 'my-event-plugin'); ?></strong> 
            <?php echo esc_html($post->post_title); ?>
        </p>
        <p style="margin: 10px 0;">
            <strong><?php _e('ID:', 'my-event-plugin'); ?></strong> 
            <?php echo $post_id; ?>
        </p>
        <p style="margin: 10px 0;">
            <strong><?php _e('Categorie:', 'my-event-plugin'); ?></strong> 
            <?php echo esc_html($cat_names); ?>
        </p>
        <p style="margin: 10px 0;">
            <strong><?php _e('Stato:', 'my-event-plugin'); ?></strong> 
            <?php echo esc_html($status); ?>
        </p>
        <p style="margin: 10px 0;">
            <strong><?php _e('Data pubblicazione:', 'my-event-plugin'); ?></strong> 
            <?php echo date_i18n(get_option('date_format'), strtotime($post->post_date)); ?>
        </p>
        <p style="margin: 10px 0 0 0;">
            <a href="<?php echo get_edit_post_link($post_id); ?>" target="_blank" class="button button-secondary">
                <span class="dashicons dashicons-edit" style="margin-top: 3px;"></span>
                <?php _e('Modifica Template', 'my-event-plugin'); ?>
            </a>
            <a href="<?php echo get_permalink($post_id); ?>" target="_blank" class="button button-secondary">
                <span class="dashicons dashicons-visibility" style="margin-top: 3px;"></span>
                <?php _e('Visualizza', 'my-event-plugin'); ?>
            </a>
        </p>
        <?php
        $html = ob_get_clean();
        
        wp_send_json_success(['html' => $html]);
    }
    
    /**
     * Handler AJAX per navigare nelle cartelle di Google Drive
     * 🚀 Nuovo browser Google Drive integrato
     */
    public function handle_browse_gdrive_folder() {
        // Verifica nonce
        check_ajax_referer('mep_nonce', 'nonce');
        
        // Log richiesta
        MEP_Helpers::log_info("🚀 AJAX: handle_browse_gdrive_folder chiamato");
        
        // Ottieni folder ID (default: root)
        $folder_id = sanitize_text_field($_POST['folder_id'] ?? 'root');
        
        MEP_Helpers::log_info("📁 Navigazione cartella: {$folder_id}");
        
        try {
            // Verifica autorizzazione OAuth
            if (!MEP_Google_OAuth::is_authorized()) {
                MEP_Helpers::log_error("❌ OAuth non autorizzato");
                wp_send_json_error([
                    'message' => __('Google Drive non è autorizzato. Vai nelle Impostazioni per autorizzare.', 'my-event-plugin'),
                    'code' => 'oauth_not_authorized'
                ]);
                return;
            }
            
            // Recupera cartelle e file dalla API
            $result = MEP_Google_Drive_API::list_folders_and_files($folder_id, true, 'image/');
            
            if (is_wp_error($result)) {
                MEP_Helpers::log_error("❌ Errore API list_folders_and_files", [
                    'code' => $result->get_error_code(),
                    'message' => $result->get_error_message()
                ]);
                
                wp_send_json_error([
                    'message' => $result->get_error_message(),
                    'code' => $result->get_error_code(),
                    'folder_id' => $folder_id
                ]);
                return;
            }
            
            // Recupera info cartella per breadcrumb (solo se non è root)
            $folder_info = null;
            if ($folder_id !== 'root') {
                $folder_info = MEP_Google_Drive_API::get_folder_info($folder_id);
                if (is_wp_error($folder_info)) {
                    MEP_Helpers::log_error("⚠️ Impossibile recuperare info cartella");
                    $folder_info = null;
                }
            }
            
            // Formatta le foto per la risposta
            $photos = [];
            if (!empty($result['files'])) {
                foreach ($result['files'] as $file) {
                    $photos[] = [
                        'id' => $file['id'],
                        'name' => $file['name'],
                        'thumbnail' => $file['thumbnailLink'] ?? '',
                        'webViewLink' => $file['webViewLink'] ?? '',
                        'mimeType' => $file['mimeType'] ?? ''
                    ];
                }
            }
            
            MEP_Helpers::log_info("✅ Successo! Cartelle: " . count($result['folders']) . ", Foto: " . count($photos));
            
            wp_send_json_success([
                'folders' => $result['folders'] ?? [],
                'photos' => $photos,
                'folder_id' => $folder_id,
                'folder_info' => $folder_info,
                'total_folders' => count($result['folders'] ?? []),
                'total_photos' => count($photos)
            ]);
            
        } catch (Throwable $e) {
            MEP_Helpers::log_error("💥 Errore catturato in handle_browse_gdrive_folder", [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            wp_send_json_error([
                'message' => sprintf(
                    __('Errore server: %s', 'my-event-plugin'),
                    $e->getMessage()
                ),
                'code' => 'exception',
                'folder_id' => $folder_id,
                'error_details' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Handler AJAX per importare solo le foto senza creare l'articolo
     * 📸 Importa foto nella Media Library e restituisce i link
     */
    public function handle_import_photos_only() {
        // Verifica nonce
        check_ajax_referer('mep_nonce', 'nonce');
        
        MEP_Helpers::log_info("📸 AJAX: handle_import_photos_only chiamato");
        
        // Ottieni dati
        $photo_ids_string = isset($_POST['photo_ids']) ? sanitize_text_field($_POST['photo_ids']) : '';
        $photo_names_string = isset($_POST['photo_names']) ? $_POST['photo_names'] : '';
        $folder_id = isset($_POST['folder_id']) ? sanitize_text_field($_POST['folder_id']) : '';
        
        if (empty($photo_ids_string)) {
            wp_send_json_error(['message' => __('Nessuna foto selezionata', 'my-event-plugin')]);
            return;
        }
        
        // Parse dati
        $photo_ids = explode(',', $photo_ids_string);
        $photo_names = !empty($photo_names_string) ? explode('|||', $photo_names_string) : [];
        
        MEP_Helpers::log_info("📸 Importazione " . count($photo_ids) . " foto");
        
        try {
            // Importa foto tramite API Google Drive
            $attachment_ids = MEP_Google_Drive_API::import_files($photo_ids, $photo_names);
            
            if (is_wp_error($attachment_ids)) {
                MEP_Helpers::log_error("❌ Errore import foto", $attachment_ids->get_error_message());
                wp_send_json_error(['message' => $attachment_ids->get_error_message()]);
                return;
            }
            
            if (empty($attachment_ids)) {
                wp_send_json_error(['message' => __('Nessuna foto è stata importata', 'my-event-plugin')]);
                return;
            }
            
            // Ottieni URL delle foto importate
            $photo_urls = [];
            foreach ($attachment_ids as $att_id) {
                $url = wp_get_attachment_url($att_id);
                if ($url) {
                    $photo_urls[] = $url;
                }
            }
            
            MEP_Helpers::log_info("✅ Importate " . count($photo_urls) . " foto con successo");
            
            wp_send_json_success([
                'attachment_ids' => $attachment_ids,
                'photo_urls' => $photo_urls,
                'count' => count($photo_urls),
                'folder_id' => $folder_id
            ]);
            
        } catch (Throwable $e) {
            MEP_Helpers::log_error("💥 Errore importazione foto", [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            wp_send_json_error([
                'message' => sprintf(__('Errore durante l\'importazione: %s', 'my-event-plugin'), $e->getMessage())
            ]);
        }
    }
    
    /**
     * Handler AJAX per fare proxy delle miniature Google Drive
     * Le miniature richiedono autenticazione OAuth, questo endpoint le serve al browser
     * 🖼️ Proxy per miniature
     */
    public function handle_proxy_thumbnail() {
        // Verifica nonce
        check_ajax_referer('mep_nonce', 'nonce');

        // Ottieni URL miniatura
        $thumbnail_url = isset($_GET['url']) ? $_GET['url'] : '';

        if (empty($thumbnail_url)) {
            MEP_Helpers::log_error("❌ Proxy Thumbnail: URL mancante");
            status_header(400);
            die('Missing URL');
        }

        MEP_Helpers::log_info("🖼️ Proxy Thumbnail richiesto per: " . substr($thumbnail_url, 0, 100));

        // Ottieni access token
        $access_token = MEP_Google_OAuth::get_access_token();

        if (is_wp_error($access_token)) {
            MEP_Helpers::log_error("❌ Proxy Thumbnail: Token non valido", $access_token->get_error_message());
            status_header(403);
            die('Unauthorized: ' . $access_token->get_error_message());
        }

        // Scarica l'immagine con autenticazione
        $response = wp_remote_get($thumbnail_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token
            ],
            'timeout' => 30, // 🔧 Aumentato timeout a 30s
            'sslverify' => false // Per sviluppo locale
        ]);

        if (is_wp_error($response)) {
            MEP_Helpers::log_error("❌ Proxy Thumbnail: Errore HTTP", $response->get_error_message());
            status_header(500);
            die('Error fetching thumbnail: ' . $response->get_error_message());
        }

        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code !== 200) {
            $body = wp_remote_retrieve_body($response);
            MEP_Helpers::log_error("❌ Proxy Thumbnail: Risposta non OK", [
                'status' => $status_code,
                'body' => substr($body, 0, 200)
            ]);
            status_header($status_code);
            die('HTTP Error: ' . $status_code);
        }

        $body = wp_remote_retrieve_body($response);
        $content_type = wp_remote_retrieve_header($response, 'content-type');

        if (empty($body)) {
            MEP_Helpers::log_error("❌ Proxy Thumbnail: Corpo risposta vuoto");
            status_header(500);
            die('Empty response body');
        }

        MEP_Helpers::log_info("✅ Proxy Thumbnail: Servita miniatura (" . strlen($body) . " bytes)");

        // Serve l'immagine
        header('Content-Type: ' . ($content_type ?: 'image/jpeg'));
        header('Content-Length: ' . strlen($body));
        header('Cache-Control: public, max-age=3600');
        header('Access-Control-Allow-Origin: *'); // Permetti CORS
        echo $body;
        die();
    }
    
    /**
     * Traccia i file importati dal plugin
     */
    public function track_imported_file($attachment_id, $cached_node) {
        // Aggiungi metadata per identificare che viene dal nostro plugin
        update_post_meta($attachment_id, '_imported_by_event_plugin', true);
        update_post_meta($attachment_id, '_gdrive_file_id', $cached_node->get_id());
        update_post_meta($attachment_id, '_gdrive_file_name', $cached_node->get_name());
        update_post_meta($attachment_id, '_import_date', current_time('mysql'));
    }
    
    /**
     * Shortcode per form frontend (opzionale)
     */
    public function render_frontend_form($atts) {
        if (!is_user_logged_in() || !current_user_can('publish_posts')) {
            return '<p>' . __('Devi essere loggato per usare questa funzione.', 'my-event-plugin') . '</p>';
        }
        
        ob_start();
        require MEP_PLUGIN_DIR . 'templates/frontend-form.php';
        return ob_get_clean();
    }
    
    /**
     * Get template post ID
     */
    public function get_template_post_id() {
        // Prova a prendere dalle opzioni
        $saved_id = get_option('mep_template_post_id', 0);
        return !empty($saved_id) ? $saved_id : $this->template_post_id;
    }
}

/**
 * Inizializza il plugin
 */
function mep_init() {
    return My_Event_Plugin::instance();
}

// Avvia il plugin
add_action('plugins_loaded', 'mep_init');

/**
 * Activation hook
 */
register_activation_hook(__FILE__, function() {
    // Crea opzioni di default
    add_option('mep_version', MEP_VERSION);
    add_option('mep_template_post_id', 0);
    add_option('mep_auto_featured_image', 'yes');
    add_option('mep_gallery_responsive', 'yes');
    add_option('mep_min_photos', 4);
});

/**
 * Deactivation hook
 */
register_deactivation_hook(__FILE__, function() {
    // Cleanup se necessario
});