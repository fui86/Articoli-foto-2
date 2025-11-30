# Gestore Eventi Automatico

Plugin WordPress per creare automaticamente articoli di eventi con foto da Google Drive.

## 📋 Descrizione

Questo plugin permette di automatizzare completamente la creazione di articoli WordPress per eventi, scaricando automaticamente le foto da Google Drive e generando una galleria responsive.

## ✨ Caratteristiche

- ✅ **Clonazione Template**: Clona un post template WordPress con tutti i metadati
- ✅ **Form Completo**: Titolo, categoria, contenuto HTML, campi SEO Rank Math
- ✅ **Folder Selector Visuale**: Naviga Google Drive senza copiare ID manualmente
- ✅ **Download Automatico**: Scarica 4 foto dalla cartella selezionata
- ✅ **Import Media Library**: Le foto vengono automaticamente importate in WordPress
- ✅ **Featured Image Auto**: La prima foto diventa immagine in evidenza
- ✅ **Galleria Responsive**: Genera shortcode Use-your-Drive con CSS responsive
- ✅ **SEO Integration**: Supporto completo Rank Math
- ✅ **Logging**: Sistema di log completo per debug

## 📦 Requisiti

### Plugin Richiesti
- **WordPress**: 5.8 o superiore
- **PHP**: 7.4 o superiore
- **[Use-your-Drive](https://www.wpcloudplugins.com/)**: Plugin premium (richiesto!)

### Plugin Consigliati
- **Rank Math**: Per gestione SEO avanzata

## 🚀 Installazione

### 1. Carica il Plugin

```bash
# Via FTP/cPanel
1. Carica la cartella 'my-event-plugin' in /wp-content/plugins/

# Via WordPress Admin
1. Vai in Plugin > Aggiungi nuovo > Carica plugin
2. Seleziona il file ZIP
3. Clicca "Installa ora"
```

### 2. Attiva il Plugin

1. Vai in **Plugin** > **Plugin installati**
2. Trova **Gestore Eventi Automatico**
3. Clicca **Attiva**

### 3. Configura Use-your-Drive

⚠️ **IMPORTANTE**: Prima di usare il plugin, devi configurare Use-your-Drive:

1. Vai in **Use-your-Drive** > **Accounts**
2. Collega il tuo account Google Drive
3. Autorizza l'accesso
4. Verifica che appaia "✓ Autorizzato"

### 4. Configura il Plugin

1. Vai in **Eventi Auto** > **Impostazioni**
2. **Imposta l'ID del Post Template**: Trova l'ID del post che vuoi usare come template
   - Vai in **Articoli** > **Tutti gli articoli**
   - Passa il mouse sul post template
   - Vedi l'URL: `post.php?post=123&action=edit`
   - L'ID è **123**
3. Inserisci l'ID nel campo "ID Post Template"
4. Configura le altre opzioni a piacere
5. Salva le impostazioni

## 📖 Utilizzo

### Creare un Nuovo Evento

1. Vai in **Eventi Auto** nel menu WordPress
2. Compila il form:
   - **Titolo Evento**: Il titolo principale dell'articolo
   - **Categoria**: Seleziona la categoria
   - **SEO** (opzionale):
     - Focus Keyword
     - Titolo SEO (se vuoto, usa il titolo evento)
     - Meta Description
   - **Contenuto HTML** (opzionale): Incolla HTML custom
   - **Cartella Google Drive**: Naviga e seleziona la cartella con le foto
3. Clicca **Crea Evento**
4. Attendi il completamento (circa 10-30 secondi)
5. Verrai reindirizzato all'editor del nuovo articolo

### Cosa Succede Automaticamente

Il plugin esegue questi passaggi:

1. ✅ Clona il post template (contenuto + metadati)
2. ✅ Aggiorna titolo e contenuto
3. ✅ Imposta la categoria
4. ✅ Valida la cartella Google Drive
5. ✅ Scarica le prime 4 foto
6. ✅ Importa le foto nella Media Library
7. ✅ Imposta la prima foto come featured image
8. ✅ Crea shortcode galleria responsive
9. ✅ Aggiunge la galleria al contenuto
10. ✅ Salva metadati SEO Rank Math
11. ✅ Crea l'articolo come bozza (o pubblicato se configurato)

## ⚙️ Impostazioni Disponibili

### Post Template
- **ID Post Template**: ID del post da clonare (obbligatorio!)

### Gestione Foto
- **Minimo Foto Richieste**: Numero minimo di foto nella cartella (default: 4)
- **Immagine in Evidenza Automatica**: Imposta la prima foto come featured image

### Galleria
- **CSS Responsive Personalizzato**: Rende la galleria completamente responsive

### Pubblicazione
- **Pubblicazione Automatica**: Pubblica subito o crea come bozza (consigliato: bozza)

## 🎨 Personalizzazione

### CSS Personalizzato per la Galleria

Il CSS responsive è in `/assets/css/gallery-responsive.css`

Puoi modificarlo per adattarlo al tuo tema:

```css
/* Esempio: Cambia gap tra immagini */
.useyourdrive.mep-gallery-responsive .wpcp-gallery-container {
    gap: 30px; /* Cambia da 20px a 30px */
}

/* Esempio: Cambia dimensione immagini mobile */
@media (max-width: 480px) {
    .useyourdrive.mep-gallery-responsive .entry_thumbnail {
        height: 200px !important; /* Aumenta da 140px a 200px */
    }
}
```

### Shortcode Galleria Custom

Puoi modificare i parametri della galleria in:
`/includes/class-gdrive-integration.php` → metodo `create_gallery_shortcode()`

```php
$default_params = [
    'dir' => $folder_id,
    'mode' => 'gallery',
    'maxheight' => '600px',        // Aumenta altezza
    'targetheight' => '250',       // Aumenta dimensione foto
    'sortfield' => 'name',         // Ordina per nome
    'lightbox' => '1',             // Lightbox attivo
    // Aggiungi altri parametri Use-your-Drive
];
```

### Hook Personalizzati

```php
// Dopo la creazione di un evento
add_action('mep_after_event_created', function($post_id, $data, $attachment_ids) {
    // Il tuo codice custom
    // Es: Invia notifica, aggiorna altri sistemi, ecc.
}, 10, 3);

// Modifica metadati prima del salvataggio
add_filter('mep_before_save_metadata', function($metadata, $post_id) {
    // Modifica $metadata
    return $metadata;
}, 10, 2);
```

## 🐛 Troubleshooting

### "Use-your-Drive non è installato"
**Soluzione**: Installa e attiva Use-your-Drive (plugin premium richiesto)

### "Nessun account Google Drive connesso"
**Soluzione**: 
1. Vai in Use-your-Drive > Accounts
2. Collega il tuo account Google
3. Completa l'autorizzazione OAuth

### "Post template non trovato"
**Soluzione**:
1. Vai in Eventi Auto > Impostazioni
2. Verifica che l'ID sia corretto
3. Assicurati che il post esista e non sia nel cestino

### "Nessuna immagine trovata nella cartella"
**Soluzione**:
1. Verifica che la cartella contenga file immagine (JPG, PNG, GIF, WebP)
2. Controlla i permessi della cartella su Google Drive
3. Verifica che Use-your-Drive abbia accesso alla cartella

### "La cartella contiene solo X immagini"
**Soluzione**:
1. Aggiungi più foto alla cartella Google Drive
2. Oppure riduci il minimo richiesto in Impostazioni

### Debug Mode
Attiva il debug di WordPress per vedere i log:

```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

I log saranno in `/wp-content/debug.log`

## 📁 Struttura File

```
my-event-plugin/
├── my-event-plugin.php          # File principale
├── README.md                     # Questo file
├── includes/
│   ├── class-helpers.php        # Funzioni utility
│   ├── class-gdrive-integration.php  # Integrazione Google Drive
│   └── class-post-creator.php   # Logica creazione post
├── templates/
│   ├── admin-page.php           # Form creazione evento
│   └── settings-page.php        # Pagina impostazioni
└── assets/
    ├── css/
    │   ├── admin-style.css      # Stili admin
    │   └── gallery-responsive.css  # Stili galleria responsive
    └── js/
        └── admin-script.js      # JavaScript admin
```

## 🔧 Requisiti Tecnici

### Permessi PHP Necessari
- `allow_url_fopen` = On
- `max_execution_time` >= 60 (per download foto grandi)
- `memory_limit` >= 128M
- `upload_max_filesize` >= 10M

### Permessi WordPress
- L'utente deve avere il ruolo `publish_posts` per creare eventi
- L'utente deve avere il ruolo `manage_options` per le impostazioni

## 🤝 Supporto

Per supporto:
1. Verifica di aver seguito tutte le istruzioni di installazione
2. Controlla la sezione Troubleshooting
3. Attiva il debug mode e controlla i log
4. Se il problema persiste, contatta il supporto Use-your-Drive per problemi di integrazione Google Drive

## 📄 License

GPL v2 or later

## 🙏 Credits

- Usa [Use-your-Drive](https://www.wpcloudplugins.com/) di WP Cloud Plugins
- Compatible with [Rank Math](https://rankmath.com/)

## 📝 Changelog

### 1.0.0 (2024-01-XX)
- ✨ Release iniziale
- ✅ Creazione automatica eventi
- ✅ Integrazione Use-your-Drive
- ✅ Folder selector visuale
- ✅ Download automatico foto
- ✅ Galleria responsive
- ✅ SEO Rank Math integration
- ✅ Sistema di log

## 🚀 Roadmap

Funzionalità future in considerazione:
- [ ] Bulk creation (crea più eventi da più cartelle)
- [ ] Scheduling (programma pubblicazione)
- [ ] Email notifications
- [ ] Template personalizzabili dall'interfaccia
- [ ] Supporto per altri plugin SEO (Yoast, All in One SEO)
- [ ] Export/Import impostazioni

---

**Made with ❤️ for WordPress**
