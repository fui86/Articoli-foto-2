<?php
/**
 * Template: Pagina Impostazioni
 */

defined('ABSPATH') || exit;

// Gestisci callback OAuth Google
if (isset($_GET['google_auth']) && $_GET['google_auth'] === 'callback') {
    if (isset($_GET['code'])) {
        // Verifica state per sicurezza
        if (!isset($_GET['state']) || !wp_verify_nonce($_GET['state'], 'mep_google_oauth')) {
            echo '<div class="notice notice-error"><p>' . __('Errore: richiesta non valida (state mismatch)', 'my-event-plugin') . '</p></div>';
        } else {
            // Scambia code con token
            $result = MEP_Google_OAuth::exchange_code_for_token($_GET['code']);
            
            if (is_wp_error($result)) {
                echo '<div class="notice notice-error"><p><strong>Errore OAuth:</strong> ' . esc_html($result->get_error_message()) . '</p></div>';
            } else {
                echo '<div class="notice notice-success is-dismissible"><p>✅ <strong>Autorizzazione completata con successo!</strong> Ora puoi usare il browser Google Drive.</p></div>';
            }
        }
    } elseif (isset($_GET['error'])) {
        echo '<div class="notice notice-error"><p><strong>Errore OAuth:</strong> ' . esc_html($_GET['error']) . '</p></div>';
    }
}

// Revoca autorizzazione
if (isset($_POST['mep_revoke_auth']) && check_admin_referer('mep_settings_nonce', 'mep_settings_nonce_field')) {
    MEP_Google_OAuth::revoke_authorization();
    echo '<div class="notice notice-success is-dismissible"><p>' . __('Autorizzazione revocata!', 'my-event-plugin') . '</p></div>';
}

// Salva impostazioni
if (isset($_POST['mep_save_settings']) && check_admin_referer('mep_settings_nonce', 'mep_settings_nonce_field')) {
    update_option('mep_template_post_id', absint($_POST['template_post_id']));
    update_option('mep_auto_featured_image', sanitize_text_field($_POST['auto_featured_image']));
    update_option('mep_gallery_responsive', sanitize_text_field($_POST['gallery_responsive']));
    update_option('mep_min_photos', absint($_POST['min_photos']));
    update_option('mep_auto_publish', sanitize_text_field($_POST['auto_publish']));
    
    // Salva credenziali OAuth
    if (isset($_POST['google_client_id'])) {
        update_option('mep_google_client_id', sanitize_text_field($_POST['google_client_id']));
    }
    if (isset($_POST['google_client_secret'])) {
        update_option('mep_google_client_secret', sanitize_text_field($_POST['google_client_secret']));
    }
    
    echo '<div class="notice notice-success is-dismissible"><p>' . __('Impostazioni salvate!', 'my-event-plugin') . '</p></div>';
}

// Carica impostazioni correnti
$template_post_id = get_option('mep_template_post_id', 0);
$auto_featured_image = get_option('mep_auto_featured_image', 'yes');
$gallery_responsive = get_option('mep_gallery_responsive', 'yes');
$min_photos = get_option('mep_min_photos', 4);
$auto_publish = get_option('mep_auto_publish', 'no');

// Carica credenziali OAuth
$google_client_id = get_option('mep_google_client_id', '');
$google_client_secret = get_option('mep_google_client_secret', '');
$is_authorized = MEP_Google_OAuth::is_authorized();
$auth_url = MEP_Google_OAuth::get_auth_url();
$token_info = MEP_Google_OAuth::get_token_info();
?>

<div class="wrap mep-settings-wrap">
    <h1 class="mep-page-title">
        <span class="dashicons dashicons-admin-settings"></span>
        <?php _e('Impostazioni Gestore Eventi', 'my-event-plugin'); ?>
    </h1>
    
    <form method="post" action="" class="mep-settings-form">
        <?php wp_nonce_field('mep_settings_nonce', 'mep_settings_nonce_field'); ?>
        
        <!-- 🔐 Sezione Google Drive OAuth -->
        <div class="mep-settings-section" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 25px; border-radius: 10px; margin-bottom: 30px;">
            <h2 style="color: white; margin-top: 0;">
                <span class="dashicons dashicons-google" style="font-size: 28px;"></span>
                <?php _e('🔐 Autorizzazione Google Drive', 'my-event-plugin'); ?>
            </h2>
            <p style="opacity: 0.95;">
                <?php _e('Configura le credenziali OAuth per accedere a Google Drive. Segui la guida sotto per ottenere Client ID e Secret.', 'my-event-plugin'); ?>
            </p>
            
            <table class="form-table" style="background: rgba(255,255,255,0.1); border-radius: 8px; padding: 15px;">
                <tr>
                    <th scope="row" style="color: white;">
                        <label for="google_client_id"><?php _e('Client ID', 'my-event-plugin'); ?></label>
                    </th>
                    <td>
                        <input type="text" 
                               name="google_client_id" 
                               id="google_client_id" 
                               value="<?php echo esc_attr($google_client_id); ?>" 
                               class="regular-text"
                               style="font-family: monospace; background: white; border: 2px solid rgba(255,255,255,0.3);"
                               placeholder="123456789-abc...apps.googleusercontent.com">
                        <p class="description" style="color: rgba(255,255,255,0.9);">
                            <?php _e('Ottieni da Google Cloud Console', 'my-event-plugin'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row" style="color: white;">
                        <label for="google_client_secret"><?php _e('Client Secret', 'my-event-plugin'); ?></label>
                    </th>
                    <td>
                        <input type="password" 
                               name="google_client_secret" 
                               id="google_client_secret" 
                               value="<?php echo esc_attr($google_client_secret); ?>" 
                               class="regular-text"
                               style="font-family: monospace; background: white; border: 2px solid rgba(255,255,255,0.3);"
                               placeholder="GOCSPX-...">
                        <p class="description" style="color: rgba(255,255,255,0.9);">
                            <?php _e('Mantienilo segreto!', 'my-event-plugin'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row" style="color: white;">
                        <?php _e('Redirect URI', 'my-event-plugin'); ?>
                    </th>
                    <td>
                        <code style="background: rgba(0,0,0,0.3); padding: 8px 12px; border-radius: 4px; display: inline-block; color: #38ef7d; font-size: 13px;">
                            <?php echo esc_html(MEP_Google_OAuth::get_redirect_uri()); ?>
                        </code>
                        <p class="description" style="color: rgba(255,255,255,0.9);">
                            <?php _e('Copia questo URL e incollalo nelle "Authorized redirect URIs" in Google Cloud Console', 'my-event-plugin'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <!-- Stato Autorizzazione -->
            <div style="background: rgba(255,255,255,0.15); padding: 20px; border-radius: 8px; margin-top: 20px;">
                <h3 style="margin-top: 0; color: white;">
                    <?php _e('📊 Stato Autorizzazione', 'my-event-plugin'); ?>
                </h3>
                
                <?php if ($is_authorized): ?>
                    <div style="background: rgba(56, 239, 125, 0.2); border-left: 4px solid #38ef7d; padding: 15px; border-radius: 4px; margin-bottom: 15px;">
                        <p style="margin: 0; font-weight: 600; color: white;">
                            ✅ <?php _e('Autorizzato!', 'my-event-plugin'); ?>
                        </p>
                        <ul style="margin: 10px 0 0 20px; color: rgba(255,255,255,0.9); font-size: 13px;">
                            <li><strong>Access Token:</strong> <?php echo $token_info['has_access_token'] ? '✅ Presente' : '❌ Mancante'; ?></li>
                            <li><strong>Refresh Token:</strong> <?php echo $token_info['has_refresh_token'] ? '✅ Presente' : '❌ Mancante'; ?></li>
                            <li><strong>Scadenza:</strong> <?php echo esc_html($token_info['expires_at']); ?> 
                                <?php if ($token_info['is_expired']): ?>
                                    <span style="color: #ffc107;">(Scaduto, verrà refreshato automaticamente)</span>
                                <?php endif; ?>
                            </li>
                        </ul>
                    </div>
                    
                    <button type="submit" 
                            name="mep_revoke_auth" 
                            class="button button-secondary"
                            onclick="return confirm('Sei sicuro di voler revocare l\'autorizzazione?');"
                            style="background: rgba(214, 48, 49, 0.8); color: white; border: none;">
                        🗑️ <?php _e('Revoca Autorizzazione', 'my-event-plugin'); ?>
                    </button>
                    
                <?php elseif (!empty($google_client_id) && !empty($google_client_secret)): ?>
                    <div style="background: rgba(255, 193, 7, 0.2); border-left: 4px solid #ffc107; padding: 15px; border-radius: 4px; margin-bottom: 15px;">
                        <p style="margin: 0; font-weight: 600; color: white;">
                            ⚠️ <?php _e('Non ancora autorizzato', 'my-event-plugin'); ?>
                        </p>
                        <p style="margin: 10px 0 0 0; color: rgba(255,255,255,0.9);">
                            <?php _e('Clicca il pulsante sotto per autorizzare l\'accesso a Google Drive', 'my-event-plugin'); ?>
                        </p>
                    </div>
                    
                    <a href="<?php echo esc_url($auth_url); ?>" 
                       class="button button-primary button-hero"
                       style="background: #38ef7d; border-color: #38ef7d; color: #1d2327; font-weight: 600; text-decoration: none; display: inline-block;">
                        <span class="dashicons dashicons-google" style="margin-top: 4px;"></span>
                        <?php _e('🔗 Autorizza con Google', 'my-event-plugin'); ?>
                    </a>
                    
                <?php else: ?>
                    <div style="background: rgba(214, 48, 49, 0.2); border-left: 4px solid #d63638; padding: 15px; border-radius: 4px;">
                        <p style="margin: 0; font-weight: 600; color: white;">
                            ❌ <?php _e('Configurazione Richiesta', 'my-event-plugin'); ?>
                        </p>
                        <p style="margin: 10px 0 0 0; color: rgba(255,255,255,0.9);">
                            <?php _e('Inserisci Client ID e Secret sopra, poi clicca "Salva Impostazioni"', 'my-event-plugin'); ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Guida Setup -->
            <details style="margin-top: 20px; background: rgba(255,255,255,0.1); padding: 15px; border-radius: 8px;">
                <summary style="cursor: pointer; font-weight: 600; color: white; font-size: 15px; user-select: none;">
                    📖 Come ottenere le credenziali OAuth (Guida Rapida)
                </summary>
                <ol style="margin: 15px 0 0 20px; padding: 0; color: rgba(255,255,255,0.95); line-height: 1.8;">
                    <li>Vai su <a href="https://console.cloud.google.com" target="_blank" style="color: #38ef7d; font-weight: 600;">Google Cloud Console</a></li>
                    <li>Crea un nuovo progetto o selezionane uno esistente</li>
                    <li>Vai in <strong>"APIs & Services" → "Credentials"</strong></li>
                    <li>Clicca <strong>"Create Credentials" → "OAuth 2.0 Client ID"</strong></li>
                    <li>Scegli tipo applicazione: <strong>"Web application"</strong></li>
                    <li>Aggiungi la Redirect URI mostrata sopra nelle <strong>"Authorized redirect URIs"</strong></li>
                    <li>Clicca <strong>"Create"</strong></li>
                    <li>Copia Client ID e Client Secret e incollali nei campi sopra</li>
                    <li>Abilita la <strong>Google Drive API</strong>: vai in "APIs & Services" → "Library" → cerca "Google Drive API" → "Enable"</li>
                    <li>Torna qui, salva le impostazioni, e clicca <strong>"Autorizza con Google"</strong></li>
                </ol>
            </details>
        </div>
        
        <!-- Sezione Template -->
        <div class="mep-settings-section">
            <h2><?php _e('Template Post', 'my-event-plugin'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="template_post_id"><?php _e('Seleziona Post Template', 'my-event-plugin'); ?></label>
                    </th>
                    <td>
                        <!-- Filtri -->
                        <div class="mep-template-selector" style="margin-bottom: 15px;">
                            <div style="display: flex; gap: 10px; margin-bottom: 10px; flex-wrap: wrap;">
                                <div>
                                    <label for="mep-filter-category" style="font-weight: normal; margin-right: 5px;">
                                        <?php _e('Filtra per Categoria:', 'my-event-plugin'); ?>
                                    </label>
                                    <select id="mep-filter-category" style="min-width: 200px;">
                                        <option value=""><?php _e('Tutte le categorie', 'my-event-plugin'); ?></option>
                                        <?php
                                        $categories = get_categories(['hide_empty' => false]);
                                        foreach ($categories as $cat) {
                                            echo '<option value="' . esc_attr($cat->term_id) . '">' . esc_html($cat->name) . ' (' . $cat->count . ')</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                                
                                <div style="flex: 1; min-width: 250px;">
                                    <label for="mep-search-post" style="font-weight: normal; margin-right: 5px;">
                                        <?php _e('Cerca:', 'my-event-plugin'); ?>
                                    </label>
                                    <input type="text" 
                                           id="mep-search-post" 
                                           placeholder="<?php esc_attr_e('Digita il titolo del post...', 'my-event-plugin'); ?>"
                                           style="width: 100%; max-width: 300px;">
                                </div>
                            </div>
                            
                            <!-- Dropdown Post -->
                            <select id="template_post_id" 
                                    name="template_post_id" 
                                    class="mep-template-dropdown"
                                    required
                                    style="width: 100%; max-width: 600px; padding: 8px; font-size: 14px;">
                                <option value=""><?php _e('-- Seleziona un post template --', 'my-event-plugin'); ?></option>
                                <?php
                                // Recupera tutti i post pubblicati
                                $posts = get_posts([
                                    'post_type' => 'post',
                                    'post_status' => ['publish', 'draft'],
                                    'numberposts' => -1,
                                    'orderby' => 'title',
                                    'order' => 'ASC'
                                ]);
                                
                                foreach ($posts as $post) {
                                    $categories = get_the_category($post->ID);
                                    $cat_names = !empty($categories) ? implode(', ', wp_list_pluck($categories, 'name')) : __('Nessuna categoria', 'my-event-plugin');
                                    $cat_ids = !empty($categories) ? implode(',', wp_list_pluck($categories, 'term_id')) : '';
                                    
                                    $status_label = $post->post_status === 'draft' ? ' [Bozza]' : '';
                                    
                                    $selected = ($template_post_id == $post->ID) ? 'selected' : '';
                                    
                                    echo '<option value="' . esc_attr($post->ID) . '" ' . $selected . ' 
                                                  data-categories="' . esc_attr($cat_ids) . '"
                                                  data-title="' . esc_attr(strtolower($post->post_title)) . '"
                                                  data-cat-names="' . esc_attr($cat_names) . '">
                                            ' . esc_html($post->post_title) . $status_label . ' (ID: ' . $post->ID . ') - ' . esc_html($cat_names) . '
                                          </option>';
                                }
                                ?>
                            </select>
                        </div>
                        
                        <!-- Anteprima Template Selezionato -->
                        <div id="mep-template-preview" class="mep-template-preview" style="display: <?php echo $template_post_id > 0 ? 'block' : 'none'; ?>; margin-top: 15px; padding: 15px; background: #f0f6fc; border-left: 4px solid #2271b1; border-radius: 4px;">
                            <?php if ($template_post_id > 0 && MEP_Helpers::is_valid_post($template_post_id)): 
                                $template_post = get_post($template_post_id);
                                $template_cats = get_the_category($template_post_id);
                                $template_cat_names = !empty($template_cats) ? implode(', ', wp_list_pluck($template_cats, 'name')) : __('Nessuna categoria', 'my-event-plugin');
                            ?>
                                <h4 style="margin-top: 0; color: #2271b1;">
                                    <span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span>
                                    <?php _e('Template Selezionato:', 'my-event-plugin'); ?>
                                </h4>
                                <p style="margin: 10px 0;">
                                    <strong><?php _e('Titolo:', 'my-event-plugin'); ?></strong> 
                                    <?php echo esc_html($template_post->post_title); ?>
                                </p>
                                <p style="margin: 10px 0;">
                                    <strong><?php _e('ID:', 'my-event-plugin'); ?></strong> 
                                    <?php echo $template_post_id; ?>
                                </p>
                                <p style="margin: 10px 0;">
                                    <strong><?php _e('Categorie:', 'my-event-plugin'); ?></strong> 
                                    <?php echo esc_html($template_cat_names); ?>
                                </p>
                                <p style="margin: 10px 0;">
                                    <strong><?php _e('Stato:', 'my-event-plugin'); ?></strong> 
                                    <?php 
                                    $status_labels = [
                                        'publish' => __('Pubblicato', 'my-event-plugin'),
                                        'draft' => __('Bozza', 'my-event-plugin'),
                                        'pending' => __('In attesa di revisione', 'my-event-plugin'),
                                        'private' => __('Privato', 'my-event-plugin')
                                    ];
                                    echo isset($status_labels[$template_post->post_status]) ? $status_labels[$template_post->post_status] : $template_post->post_status;
                                    ?>
                                </p>
                                <p style="margin: 10px 0 0 0;">
                                    <a href="<?php echo get_edit_post_link($template_post_id); ?>" target="_blank" class="button button-secondary">
                                        <span class="dashicons dashicons-edit" style="margin-top: 3px;"></span>
                                        <?php _e('Modifica Template', 'my-event-plugin'); ?>
                                    </a>
                                    <a href="<?php echo get_permalink($template_post_id); ?>" target="_blank" class="button button-secondary">
                                        <span class="dashicons dashicons-visibility" style="margin-top: 3px;"></span>
                                        <?php _e('Visualizza', 'my-event-plugin'); ?>
                                    </a>
                                </p>
                            <?php endif; ?>
                        </div>
                        
                        <p class="description" style="margin-top: 10px;">
                            <?php _e('Seleziona il post da usare come template per i nuovi eventi. Tutti i contenuti e metadati verranno copiati.', 'my-event-plugin'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Sezione Foto -->
        <div class="mep-settings-section">
            <h2><?php _e('Gestione Foto', 'my-event-plugin'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="min_photos"><?php _e('Minimo Foto Richieste', 'my-event-plugin'); ?></label>
                    </th>
                    <td>
                        <input type="number" 
                               id="min_photos" 
                               name="min_photos" 
                               value="<?php echo esc_attr($min_photos); ?>" 
                               min="1" 
                               max="20">
                        <p class="description">
                            <?php _e('Numero minimo di foto che deve contenere la cartella Google Drive', 'my-event-plugin'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="auto_featured_image"><?php _e('Immagine in Evidenza Automatica', 'my-event-plugin'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="radio" 
                                   name="auto_featured_image" 
                                   value="yes" 
                                   <?php checked($auto_featured_image, 'yes'); ?>>
                            <?php _e('Sì, imposta automaticamente la prima foto', 'my-event-plugin'); ?>
                        </label>
                        <br>
                        <label>
                            <input type="radio" 
                                   name="auto_featured_image" 
                                   value="no" 
                                   <?php checked($auto_featured_image, 'no'); ?>>
                            <?php _e('No, imposterò manualmente', 'my-event-plugin'); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Sezione Galleria -->
        <div class="mep-settings-section">
            <h2><?php _e('Galleria', 'my-event-plugin'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="gallery_responsive"><?php _e('CSS Responsive Personalizzato', 'my-event-plugin'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="radio" 
                                   name="gallery_responsive" 
                                   value="yes" 
                                   <?php checked($gallery_responsive, 'yes'); ?>>
                            <?php _e('Sì, applica CSS responsive custom', 'my-event-plugin'); ?>
                        </label>
                        <br>
                        <label>
                            <input type="radio" 
                                   name="gallery_responsive" 
                                   value="no" 
                                   <?php checked($gallery_responsive, 'no'); ?>>
                            <?php _e('No, usa stile default di Use-your-Drive', 'my-event-plugin'); ?>
                        </label>
                        <p class="description">
                            <?php _e('Il CSS personalizzato rende la galleria completamente responsive su mobile', 'my-event-plugin'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Sezione Pubblicazione -->
        <div class="mep-settings-section">
            <h2><?php _e('Pubblicazione', 'my-event-plugin'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="auto_publish"><?php _e('Pubblicazione Automatica', 'my-event-plugin'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="radio" 
                                   name="auto_publish" 
                                   value="yes" 
                                   <?php checked($auto_publish, 'yes'); ?>>
                            <?php _e('Sì, pubblica subito l\'evento', 'my-event-plugin'); ?>
                        </label>
                        <br>
                        <label>
                            <input type="radio" 
                                   name="auto_publish" 
                                   value="no" 
                                   <?php checked($auto_publish, 'no'); ?>>
                            <?php _e('No, crea come bozza (consigliato)', 'my-event-plugin'); ?>
                        </label>
                        <p class="description">
                            <?php _e('Se impostato su "bozza", potrai revisionare l\'evento prima di pubblicarlo', 'my-event-plugin'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Info Use-your-Drive -->
        <div class="mep-settings-section">
            <h2><?php _e('Informazioni Use-your-Drive', 'my-event-plugin'); ?></h2>
            <?php
            $uyd_check = MEP_Helpers::check_useyourdrive_ready();
            if (is_wp_error($uyd_check)):
            ?>
                <div class="notice notice-error inline">
                    <p><strong><?php _e('Attenzione:', 'my-event-plugin'); ?></strong> <?php echo esc_html($uyd_check->get_error_message()); ?></p>
                </div>
            <?php else: ?>
                <div class="notice notice-success inline">
                    <p>✓ <?php _e('Use-your-Drive è correttamente configurato', 'my-event-plugin'); ?></p>
                </div>
                
                <?php
                $accounts = \TheLion\UseyourDrive\Accounts::instance()->list_accounts();
                if (!empty($accounts)):
                ?>
                    <h3><?php _e('Account Google Drive Connessi:', 'my-event-plugin'); ?></h3>
                    <ul>
                        <?php foreach ($accounts as $account): ?>
                            <li>
                                <strong><?php echo esc_html($account->get_email()); ?></strong>
                                <?php if ($account->get_authorization()->has_access_token()): ?>
                                    <span style="color: #46b450;">✓ Autorizzato</span>
                                <?php else: ?>
                                    <span style="color: #dc3232;">⚠ Non autorizzato</span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            <?php endif; ?>
            
            <p>
                <a href="<?php echo admin_url('admin.php?page=use_your_drive_settings'); ?>" class="button">
                    <?php _e('Vai alle Impostazioni Use-your-Drive', 'my-event-plugin'); ?>
                </a>
            </p>
        </div>
        
        <p class="submit">
            <input type="submit" 
                   name="mep_save_settings" 
                   class="button button-primary" 
                   value="<?php esc_attr_e('Salva Impostazioni', 'my-event-plugin'); ?>">
        </p>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    'use strict';
    
    const $dropdown = $('#template_post_id');
    const $filterCategory = $('#mep-filter-category');
    const $searchInput = $('#mep-search-post');
    const $preview = $('#mep-template-preview');
    
    // Salva tutte le opzioni originali
    const allOptions = $dropdown.find('option').clone();
    
    // Funzione per filtrare le opzioni
    function filterOptions() {
        const selectedCategory = $filterCategory.val();
        const searchTerm = $searchInput.val().toLowerCase().trim();
        
        // Reset dropdown
        const currentValue = $dropdown.val();
        $dropdown.find('option:not(:first)').remove(); // Mantieni solo il placeholder
        
        // Filtra le opzioni
        allOptions.each(function() {
            const $option = $(this);
            
            // Salta il placeholder
            if ($option.val() === '') {
                return;
            }
            
            let show = true;
            
            // Filtro categoria
            if (selectedCategory !== '') {
                const optionCategories = $option.attr('data-categories');
                if (optionCategories) {
                    const catArray = optionCategories.split(',');
                    if (!catArray.includes(selectedCategory)) {
                        show = false;
                    }
                } else {
                    show = false;
                }
            }
            
            // Filtro ricerca
            if (searchTerm !== '') {
                const optionTitle = $option.attr('data-title') || '';
                const optionCatNames = $option.attr('data-cat-names') || '';
                
                if (!optionTitle.includes(searchTerm) && !optionCatNames.toLowerCase().includes(searchTerm)) {
                    show = false;
                }
            }
            
            // Aggiungi l'opzione se passa i filtri
            if (show) {
                $dropdown.append($option.clone());
            }
        });
        
        // Ripristina la selezione se possibile
        if ($dropdown.find('option[value="' + currentValue + '"]').length > 0) {
            $dropdown.val(currentValue);
        } else {
            $dropdown.val('');
            updatePreview();
        }
        
        // Mostra messaggio se nessun risultato
        if ($dropdown.find('option').length === 1) {
            $dropdown.append('<option value="" disabled>Nessun post trovato con questi filtri</option>');
        }
    }
    
    // Funzione per aggiornare l'anteprima
    function updatePreview() {
        const selectedId = $dropdown.val();
        
        if (!selectedId || selectedId === '') {
            $preview.slideUp();
            return;
        }
        
        // Mostra loading
        $preview.html('<p style="text-align: center;"><span class="spinner is-active" style="float: none; margin: 0;"></span> Caricamento...</p>').slideDown();
        
        // Richiesta AJAX per ottenere info post
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mep_get_template_preview',
                post_id: selectedId,
                nonce: '<?php echo wp_create_nonce('mep_settings_nonce'); ?>'
            },
            success: function(response) {
                if (response.success && response.data.html) {
                    $preview.html(response.data.html).slideDown();
                } else {
                    $preview.html('<p style="color: #d63638;">Errore nel caricamento dell\'anteprima.</p>').slideDown();
                }
            },
            error: function() {
                $preview.html('<p style="color: #d63638;">Errore di connessione.</p>').slideDown();
            }
        });
    }
    
    // Eventi
    $filterCategory.on('change', filterOptions);
    $searchInput.on('input', filterOptions);
    $dropdown.on('change', updatePreview);
    
    // Debug
    console.log('🎨 Template Selector caricato - Opzioni disponibili:', allOptions.length - 1);
});
</script>

<style>
.mep-template-dropdown {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
}

.mep-template-preview {
    animation: slideInDown 0.3s ease;
}

@keyframes slideInDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.mep-template-preview h4 {
    display: flex;
    align-items: center;
    gap: 8px;
}

.mep-template-preview .button {
    margin-right: 5px;
}

.mep-template-preview .button .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
}
</style>
