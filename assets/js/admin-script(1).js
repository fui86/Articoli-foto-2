/**
 * My Event Plugin - Admin JavaScript (v1.2. 0 - Google Drive Browser Integrato)
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        console.log('🚀 My Event Plugin v1.2 - Admin Script caricato');
        console.log('📊 Config:', mepData);
        
        // ===== Inizializzazione =====
        const MEP = {
            form: $('#mep-event-form'),
            submitBtn: $('#mep-submit-btn'),
            statusMsg: $('#mep-status-message'),
            folderValidationMsg: $('#mep-folder-validation-message'),
            
            // Campi folder
            folderId: $('#event_folder_id'),
            folderAccount: $('#event_folder_account'),
            folderName: $('#event_folder_name'),
            
            // Counter SEO
            seoTitle: $('#seo_title'),
            seoDescription: $('#seo_description'),
            seoCounter: $('.seo-counter'),
            descCounter: $('.desc-counter')
        };
        
        // ===== Auto-popolamento Titolo SEO =====
        $('#event_title').on('input', function() {
            if (MEP.seoTitle.val() === '') {
                MEP.seoTitle.val($(this).val());
                updateSeoCounter();
            }
        });
        
        // ===== Counter SEO Title =====
        MEP.seoTitle.on('input', updateSeoCounter);
        function updateSeoCounter() {
            const length = MEP.seoTitle. val().length;
            const counter = MEP.seoCounter;
            
            counter.text(length + '/60');
            
            if (length > 60) {
                counter. addClass('danger'). removeClass('warning');
            } else if (length > 50) {
                counter.addClass('warning').removeClass('danger');
            } else {
                counter.removeClass('warning danger');
            }
        }
        
        // ===== Counter Meta Description =====
        MEP. seoDescription.on('input', updateDescCounter);
        function updateDescCounter() {
            const length = MEP.seoDescription.val(). length;
            const counter = MEP.descCounter;
            
            counter.text(length + '/160');
            
            if (length > 160) {
                counter.addClass('danger').removeClass('warning');
            } else if (length > 140) {
                counter.addClass('warning').removeClass('danger');
            } else {
                counter.removeClass('warning danger');
            }
        }
        
        // ===== 🚀 Google Drive Browser =====
        const GDriveBrowser = {
            currentFolderId: 'root',
            currentFolderName: 'My Drive',
            folderHistory: [],
            
            init: function() {
                console.log('🗂️ Inizializzo Google Drive Browser');
                this.loadFolder('root');
                this.bindEvents();
            },
            
            bindEvents: function() {
                // Click su "Seleziona Questa Cartella"
                $(document).on('click', '#mep-select-current-folder', () => {
                    this.selectCurrentFolder();
                });
                
                // Click su una cartella (naviga dentro)
                $(document).on('click', '. mep-gdrive-folder-item', (e) => {
                    const folderId = $(e.currentTarget).data('folder-id');
                    const folderName = $(e.currentTarget).data('folder-name');
                    console.log('📂 Click cartella:', folderName, folderId);
                    this.loadFolder(folderId, folderName);
                });
                
                // Click su breadcrumb
                $(document).on('click', '.mep-breadcrumb-item', (e) => {
                    const folderId = $(e.currentTarget). data('folder-id');
                    const folderName = $(e.currentTarget).data('folder-name') || 'My Drive';
                    this.loadFolder(folderId, folderName);
                });
            },
            
            loadFolder: function(folderId, folderName) {
                console.log('📥 Caricamento cartella:', folderId, folderName);
                
                this.currentFolderId = folderId;
                if (folderName) {
                    this.currentFolderName = folderName;
                }
                
                // Mostra loading
                $('#mep-gdrive-folders-list').html(`
                    <div style="text-align: center; padding: 40px; color: #646970;">
                        <span class="mep-spinner"></span>
                        <p style="margin: 10px 0 0 0;">Caricamento cartelle...</p>
                    </div>
                `);
                
                $('#mep-current-folder-actions').hide();
                
                // Richiesta AJAX
                $.ajax({
                    url: mepData.ajax_url,
                    type: 'POST',
                    timeout: 60000, // 60 secondi
                    data: {
                        action: 'mep_browse_gdrive_folder',
                        nonce: mepData.nonce,
                        folder_id: folderId
                    },
                    success: (response) => {
                        console.log('📦 Risposta server:', response);
                        
                        if (response.success) {
                            this.renderFolders(response.data);
                            this.updateBreadcrumb(response.data);
                            $('#mep-current-folder-actions').slideDown();
                            console.log('✅ Cartelle caricate:', response.data.total_folders, 'foto:', response.data.total_photos);
                        } else {
                            this.showError(response.data. message || 'Errore sconosciuto');
                        }
                    },
                    error: (xhr, status, error) => {
                        console.error('❌ Errore AJAX:', {xhr, status, error});
                        
                        let errorMsg = 'Errore di connessione: ' + error;
                        
                        if (xhr.status === 403) {
                            errorMsg = 'Errore 403: Accesso negato. Verifica di essere autorizzato su Google Drive.';
                        } else if (xhr.status === 500) {
                            errorMsg = 'Errore 500: Problema del server.  Riprova tra qualche minuto.';
                        }
                        
                        // Prova a parsare la risposta
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.data && response.data.message) {
                                errorMsg = response. data.message;
                            }
                        } catch(e) {
                            // Ignora parsing error
                        }
                        
                        this.showError(errorMsg);
                    }
                });
            },
            
            renderFolders: function(data) {
                const folders = data.folders || [];
                const photos = data.photos || [];
                const $list = $('#mep-gdrive-folders-list');
                
                if (folders.length === 0) {
                    $list.html(`
                        <div style="text-align: center; padding: 40px; color: #646970;">
                            <span class="dashicons dashicons-portfolio" style="font-size: 48px; opacity: 0.3;"></span>
                            <p style="margin: 10px 0 0 0; font-size: 15px;">Nessuna sottocartella trovata. </p>
                            <p style="font-size: 13px; color: #999;">
                                ${photos.length > 0 ? '📸 Questa cartella contiene ' + photos. length + ' foto!' : 'Questa cartella è vuota.'}
                            </p>
                            <p style="font-size: 13px; margin-top: 15px;">
                                ${photos. length > 0 ? '👇 Clicca "Seleziona Questa Cartella" sotto per caricare le foto!' : ''}
                            </p>
                        </div>
                    `);
                    return;
                }
                
                let html = '<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 15px;">';
                
                folders.forEach(folder => {
                    const folderName = $('<div>').text(folder.name).html(); // Escape HTML
                    html += `
                        <div class="mep-gdrive-folder-item" 
                             data-folder-id="${folder.id}" 
                             data-folder-name="${folderName}"
                             style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                                    padding: 20px 15px; 
                                    border-radius: 8px; 
                                    cursor: pointer; 
                                    transition: all 0.3s ease;
                                    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                                    display: flex;
                                    flex-direction: column;
                                    align-items: center;
                                    text-align: center;
                                    color: white;
                                    position: relative;
                                    overflow: hidden;">
                            <span class="dashicons dashicons-category" style="font-size: 40px; margin-bottom: 10px; opacity: 0.9;"></span>
                            <span style="font-weight: 600; font-size: 13px; line-height: 1.3; word-break: break-word; max-height: 40px; overflow: hidden;">${folderName}</span>
                            <div class="folder-hover-tooltip" style="position: absolute; bottom: 5px; right: 5px; opacity: 0; transition: opacity 0. 3s;">
                                <span style="font-size: 20px;">👆</span>
                            </div>
                        </div>
                    `;
                });
                
                html += '</div>';
                $list.html(html);
                
                // Hover effect
                $('.mep-gdrive-folder-item').hover(
                    function() {
                        $(this).css({
                            'transform': 'translateY(-5px) scale(1.03)',
                            'box-shadow': '0 8px 20px rgba(102, 126, 234, 0.4)'
                        });
                        $(this).find('.folder-hover-tooltip').css('opacity', '1');
                    },
                    function() {
                        $(this).css({
                            'transform': 'translateY(0) scale(1)',
                            'box-shadow': '0 2px 8px rgba(0,0,0,0.1)'
                        });
                        $(this).find('.folder-hover-tooltip').css('opacity', '0');
                    }
                );
            },
            
            updateBreadcrumb: function(data) {
                const $breadcrumb = $('#mep-gdrive-breadcrumb');
                let html = `
                    <span class="dashicons dashicons-admin-home" style="color: #2271b1;"></span>
                    <span class="mep-breadcrumb-item" data-folder-id="root" data-folder-name="My Drive" style="color: #2271b1; cursor: pointer; text-decoration: underline; margin-left: 5px;">My Drive</span>
                `;
                
                if (data.folder_info && data.folder_info.name) {
                    html += ` <span style="color: #646970; margin: 0 8px;">›</span> <span style="color: #1d2327; font-weight: 600;">${data.folder_info.name}</span>`;
                } else if (this.currentFolderId !== 'root' && this.currentFolderName !== 'My Drive') {
                    html += ` <span style="color: #646970; margin: 0 8px;">›</span> <span style="color: #1d2327; font-weight: 600;">${this.currentFolderName}</span>`;
                }
                
                $breadcrumb.html(html);
            },
            
            selectCurrentFolder: function() {
                console.log('✅ Cartella selezionata:', this. currentFolderId, this.currentFolderName);
                
                // Popola campi hidden
                $('#event_folder_id').val(this.currentFolderId);
                $('#event_folder_name').val(this.currentFolderName);
                
                // Mostra messaggio
                MEP.folderValidationMsg
                    .removeClass('error')
                    .addClass('success')
                    .html(`✅ Cartella selezionata: <strong>${this.currentFolderName}</strong>.  Caricamento foto...`)
                    .slideDown();
                
                // Carica le foto
                loadFolderPhotos(this.currentFolderId);
            },
            
            showError: function(message) {
                $('#mep-gdrive-folders-list').html(`
                    <div style="padding: 20px; background: #f8d7da; border: 2px solid #d63638; border-radius: 8px; color: #721c24;">
                        <p style="margin: 0; font-weight: 600;"><strong>❌ Errore:</strong> ${message}</p>
                        <p style="margin: 10px 0 0 0; font-size: 13px;">
                            Verifica di essere autorizzato e riprova.  
                            <a href="${mepData.ajax_url. replace('admin-ajax. php', 'admin. php? page=my-event-settings')}" style="color: #0073aa;">Vai alle Impostazioni</a>
                        </p>
                    </div>
                `);
                
                MEP.folderValidationMsg
                    .removeClass('success')
                    .addClass('error')
                    .html('❌ ' + message)
                    .slideDown();
            }
        };
        
        // Inizializza il browser
        GDriveBrowser. init();
        
        // ===== Photo Selection State =====
        const PhotoSelector = {
            selectedPhotos: [], // Array di oggetti {id, name, thumbnail}
            maxPhotos: 20, // Aumentato a 20 (o rimuovi il limite)
            
            reset: function() {
                this. selectedPhotos = [];
                this.updateUI();
            },
            
            addPhoto: function(photo) {
                if (this.maxPhotos && this.selectedPhotos.length >= this.maxPhotos) {
                    alert('Hai raggiunto il limite massimo di ' + this.maxPhotos + ' foto! ');
                    return false;
                }
                
                // Verifica che non sia già selezionata
                if (this.isSelected(photo. id)) {
                    return false;
                }
                
                this.selectedPhotos.push(photo);
                this.updateUI();
                return true;
            },
            
            removePhoto: function(photoId) {
                this.selectedPhotos = this.selectedPhotos.filter(p => p.id !== photoId);
                this.updateUI();
            },
            
            isSelected: function(photoId) {
                return this.selectedPhotos.some(p => p.id === photoId);
            },
            
            updateUI: function() {
                const count = this.selectedPhotos.length;
                
                // Aggiorna counter
                $('. mep-selection-count strong').text(count);
                
                // Aggiorna campo hidden con gli ID
                const photoIds = this.selectedPhotos. map(p => p.id). join(',');
                $('#selected_photo_ids').val(photoIds);
                
                // Cambia stile del contatore in base al progresso
                const $counter = $('. mep-selection-count strong');
                if (count >= 4) {
                    $counter. css('color', '#00a32a'); // Verde
                    $('#mep-selection-help').html('✅ <strong>' + count + ' foto selezionate! </strong> Clicca "Importa in WordPress" per salvarle.');
                } else if (count > 0) {
                    $counter.css('color', '#dba617'); // Giallo
                    $('#mep-selection-help'). html('Hai selezionato <strong>' + count + '</strong> foto.  Puoi selezionarne altre o cliccare "Importa".');
                } else {
                    $counter.css('color', '#646970'); // Grigio
                    $('#mep-selection-help').html('Clicca sui pulsanti "Seleziona" sotto ogni foto per aggiungerle alla selezione');
                }
                
                // Mostra/nascondi lista foto selezionate
                if (count > 0) {
                    this. renderSelectedPhotos();
                    $('#mep-selected-photos').slideDown();
                    
                    // Auto-scroll verso le foto selezionate (solo se sono tante)
                    if (count === 1) {
                        $('html, body').animate({
                            scrollTop: $('#mep-selected-photos').offset().top - 100
                        }, 800);
                    }
                } else {
                    $('#mep-selected-photos').slideUp();
                }
                
                // Aggiorna stato bottoni nella griglia
                this.updateGridButtons();
            },
            
            renderSelectedPhotos: function() {
                const $list = $('#mep-selected-photos-list');
                $list.empty();
                
                this.selectedPhotos.forEach((photo, index) => {
                    // Usa proxy per le miniature
                    const proxyUrl = mepData.ajax_url + 
                        '?action=mep_proxy_thumbnail' + 
                        '&nonce=' + mepData.nonce + 
                        '&url=' + encodeURIComponent(photo.thumbnail);
                    
                    $list.append(`
                        <div class="mep-selected-photo-item" data-photo-id="${photo.id}">
                            <div class="mep-selected-photo-number">${index + 1}</div>
                            <img src="${proxyUrl}" alt="${photo.name}">
                            <button type="button" class="mep-remove-photo" data-photo-id="${photo.id}">
                                <span class="dashicons dashicons-no-alt"></span>
                            </button>
                            <div class="mep-photo-name">${photo.name}</div>
                        </div>
                    `);
                });
                
                // Aggiorna dropdown foto di copertina (genera dinamicamente le opzioni)
                const $featuredSelect = $('#mep-featured-image-select');
                $featuredSelect.empty();
                $featuredSelect.append('<option value="">-- Seleziona immagine di copertina --</option>');
                
                this.selectedPhotos.forEach((photo, index) => {
                    $featuredSelect.append(`<option value="${index}">📷 Foto ${index + 1} - ${photo.name}</option>`);
                });
                
                // Richiedi selezione copertina se abbiamo almeno 1 foto
                if (this. selectedPhotos.length > 0) {
                    $featuredSelect.prop('required', true);
                    $('#mep-featured-image-section').slideDown();
                } else {
                    $('#mep-featured-image-section').slideUp();
                }
            },
            
            updateGridButtons: function() {
                $('. mep-photo-item').each((i, el) => {
                    const photoId = $(el).data('photo-id');
                    const $btn = $(el).find('.mep-select-photo-btn');
                    
                    if (this.isSelected(photoId)) {
                        $btn.text('✓ Selezionata'). addClass('selected');
                        $(el).addClass('selected');
                    } else {
                        $btn.text('Seleziona').removeClass('selected');
                        $(el).removeClass('selected');
                    }
                });
            }
        };
        
        // ===== Carica Foto dalla Cartella =====
        function loadFolderPhotos(folderId) {
            console.log('📸 Caricamento foto dalla cartella:', folderId);
            
            // Mostra sezione foto
            $('#mep-photo-selector-wrapper').slideDown();
            
            // Reset selezione
            PhotoSelector.reset();
            
            // Mostra loading
            $('#mep-photo-grid').html(`
                <div class="mep-loading-grid">
                    <span class="mep-spinner"></span>
                    <p>Caricamento foto dalla cartella...</p>
                </div>
            `);
            
            // AJAX
            $.ajax({
                url: mepData.ajax_url,
                type: 'POST',
                timeout: 60000, // 60 secondi
                data: {
                    action: 'mep_get_folder_photos',
                    nonce: mepData.nonce,
                    folder_id: folderId
                },
                success: function(response) {
                    console.log('📦 Risposta foto:', response);
                    
                    if (response.success && response.data. photos) {
                        renderPhotoGrid(response.data.photos);
                        
                        MEP.folderValidationMsg
                            .removeClass('error')
                            .addClass('success')
                            .html(`✅ Trovate <strong>${response.data.photos.length}</strong> foto!  Seleziona le 4 che vuoi importare.`)
                            .slideDown();
                    } else {
                        const errorMessage = response.data && response.data.message ? response. data.message : 'Errore nel caricamento foto';
                        
                        $('#mep-photo-grid').html(`
                            <div class="mep-loading-grid">
                                <p style="color: #d63638;">❌ ${errorMessage}</p>
                            </div>
                        `);
                        
                        MEP.folderValidationMsg
                            .removeClass('success')
                            .addClass('error')
                            .html('❌ ' + errorMessage)
                            .slideDown();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ Errore AJAX foto:', {xhr, status, error});
                    
                    let errorMsg = 'Errore di connessione: ' + error;
                    if (xhr.status === 403) {
                        errorMsg = 'Errore 403: Accesso negato. Verifica i permessi. ';
                    } else if (xhr.status === 500) {
                        errorMsg = 'Errore 500: Problema del server. ';
                    }
                    
                    $('#mep-photo-grid').html(`
                        <div class="mep-loading-grid">
                            <p style="color: #d63638;">❌ ${errorMsg}</p>
                        </div>
                    `);
                    
                    MEP.folderValidationMsg
                        .removeClass('success')
                        .addClass('error')
                        .html('❌ ' + errorMsg)
                        .slideDown();
                }
            });
        }
        
        // ===== Renderizza Griglia Foto =====
        function renderPhotoGrid(photos) {
            const grid = $('#mep-photo-grid');
            grid.empty();
            
            if (!photos || photos.length === 0) {
                grid.html('<p style="text-align: center; color: #646970;">Nessuna foto trovata in questa cartella.</p>');
                return;
            }
            
            photos.forEach(photo => {
                // Usa proxy per le miniature (richiedono OAuth)
                const proxyUrl = mepData.ajax_url + 
                    '?action=mep_proxy_thumbnail' + 
                    '&nonce=' + mepData.nonce + 
                    '&url=' + encodeURIComponent(photo.thumbnail);
                
                const $item = $(`
                    <div class="mep-photo-item" data-photo-id="${photo.id}">
                        <div class="mep-photo-thumb">
                            <img src="${proxyUrl}" alt="${photo. name}" loading="lazy" 
                                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3. org/2000/svg\' width=\'200\' height=\'200\'%3E%3Crect fill=\'%23ddd\' width=\'200\' height=\'200\'/%3E%3Ctext x=\'50%25\' y=\'50%25\' dominant-baseline=\'middle\' text-anchor=\'middle\' fill=\'%23999\' font-size=\'16\'%3EErrore%3C/text%3E%3C/svg%3E'">
                            <div class="mep-photo-overlay">
                                <button type="button" class="mep-select-photo-btn">Seleziona</button>
                            </div>
                        </div>
                        <div class="mep-photo-info">
                            <div class="mep-photo-name">${photo.name}</div>
                        </div>
                    </div>
                `);
                
                grid.append($item);
            });
            
            // Event: Click per selezionare foto
            $('. mep-select-photo-btn').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const $item = $(this).closest('.mep-photo-item');
                const photoId = $item.data('photo-id');
                
                // Trova la foto nell'array
                const photo = photos.find(p => p.id === photoId);
                
                if (! photo) return;
                
                // Toggle selezione
                if (PhotoSelector.isSelected(photoId)) {
                    PhotoSelector.removePhoto(photoId);
                } else {
                    PhotoSelector. addPhoto(photo);
                }
            });
        }
        
        // ===== Click Rimuovi Foto Selezionata =====
        $(document).on('click', '.mep-remove-photo', function() {
            const photoId = $(this).data('photo-id');
            PhotoSelector.removePhoto(photoId);
        });
        
        // ===== Click Cancella Selezione =====
        $(document).on('click', '#mep-clear-selection', function() {
            if (confirm('Sei sicuro di voler cancellare la selezione?')) {
                PhotoSelector.reset();
            }
        });
        
        // ===== Click Importa Foto in WordPress =====
        $(document). on('click', '#mep-import-photos-btn', function() {
            if (PhotoSelector.selectedPhotos.length === 0) {
                alert('Seleziona almeno una foto! ');
                return;
            }
            
            if (! confirm(`Vuoi importare ${PhotoSelector.selectedPhotos.length} foto nella Media Library di WordPress?`)) {
                return;
            }
            
            const $btn = $(this);
            const originalText = $btn.html();
            
            // Disabilita bottone
            $btn.prop('disabled', true).html('<span class="mep-spinner"></span> Importazione in corso...');
            
            // Nascondi eventuali messaggi precedenti
            $('#mep-imported-links-container').slideUp();
            
            // Prepara dati
            const photoIds = PhotoSelector.selectedPhotos.map(p => p.id);
            const photoNames = PhotoSelector.selectedPhotos. map(p => p.name);
            const folderId = $('#event_folder_id').val();
            
            // AJAX
            $.ajax({
                url: mepData.ajax_url,
                type: 'POST',
                timeout: 120000, // 120 secondi per importazione
                data: {
                    action: 'mep_import_photos_only',
                    nonce: mepData.nonce,
                    photo_ids: photoIds.join(','),
                    photo_names: photoNames.join('|||'), // Separator
                    folder_id: folderId
                },
                success: function(response) {
                    console.log('✅ Risposta importazione foto:', response);
                    
                    if (response.success) {
                        // Mostra link foto importate
                        let linksHtml = '<h4>✅ Foto Importate con Successo!</h4>';
                        linksHtml += '<p style="margin: 10px 0; color: #646970;">Ecco i link delle foto nella tua Media Library:</p>';
                        linksHtml += '<ul>';
                        
                        response.data.photo_urls.forEach((url, idx) => {
                            const name = photoNames[idx] || `Foto ${idx + 1}`;
                            linksHtml += `<li><strong>${idx + 1}.  ${name}</strong><br><a href="${url}" target="_blank">${url}</a></li>`;
                        });
                        
                        linksHtml += '</ul>';
                        
                        $('#mep-imported-links-container').html(linksHtml). slideDown();
                        
                        // Auto-scroll verso i link
                        $('html, body'). animate({
                            scrollTop: $('#mep-imported-links-container').offset().top - 100
                        }, 800);
                        
                        // Riabilita bottone
                        $btn.prop('disabled', false). html(originalText);
                        
                        alert('✅ ' + response.data.photo_urls.length + ' foto importate con successo!');
                    } else {
                        alert('❌ Errore: ' + response.data.message);
                        $btn.prop('disabled', false). html(originalText);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ Errore AJAX importazione:', {xhr, status, error});
                    
                    let errorMsg = 'Errore di connessione durante l\'importazione';
                    if (xhr.status === 403) {
                        errorMsg = 'Errore 403: Accesso negato. Verifica i permessi.';
                    } else if (xhr.status === 500) {
                        errorMsg = 'Errore 500: Problema del server durante l\'importazione.';
                    } else if (status === 'timeout') {
                        errorMsg = 'Timeout: L\'importazione sta impiegando troppo tempo.  Riprova con meno foto.';
                    }
                    
                    alert('❌ ' + errorMsg);
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        });
        
        // ===== Submit Form =====
        MEP.form.on('submit', function(e) {
            e.preventDefault();
            
            // Validazione
            if (!MEP.folderId. val()) {
                alert('Seleziona una cartella Google Drive! ');
                return;
            }
            
            if (PhotoSelector.selectedPhotos.length === 0) {
                alert('Seleziona almeno una foto!');
                return;
            }
            
            if (!$('#mep-featured-image-select').val()) {
                alert('Scegli quale foto usare come copertina!');
                return;
            }
            
            // Disabilita bottone
            MEP.submitBtn.prop('disabled', true). text('⏳ Creazione in corso...');
            
            // Mostra spinner
            MEP.statusMsg.html('<span class="mep-spinner"></span> Creazione evento in corso... ').slideDown();
            
            // AJAX submit - 🔧 FIX: Aggiunto nonce mancante! 
            $.ajax({
                url: mepData.ajax_url,
                type: 'POST',
                timeout: 120000, // 120 secondi
                data: MEP.form.serialize() + '&action=mep_process_event_creation&nonce=' + mepData. nonce,
                success: function(response) {
                    console.log('✅ Risposta creazione:', response);
                    
                    if (response.success) {
                        // Genera HTML per i link delle foto importate
                        let photoLinksHtml = '';
                        if (response.data.photo_urls && response.data.photo_urls.length > 0) {
                            photoLinksHtml = '<div style="margin-top: 15px; padding: 12px; background: white; border: 1px solid #ddd; border-radius: 4px;">';
                            photoLinksHtml += '<p style="margin: 0 0 8px 0; font-weight: 600;">📸 Link Foto Importate:</p>';
                            photoLinksHtml += '<ul style="margin: 0; padding-left: 20px; font-size: 12px; font-family: monospace;">';
                            response.data.photo_urls.forEach((url, idx) => {
                                const isFeatured = (idx === parseInt(response.data.featured_index));
                                photoLinksHtml += `<li style="margin: 5px 0;">
                                    ${isFeatured ? '<strong style="color: #d63638;">🌟 COPERTINA:</strong> ' : ''}
                                    <a href="${url}" target="_blank">${url}</a>
                                </li>`;
                            });
                            photoLinksHtml += '</ul></div>';
                        }
                        
                        MEP.statusMsg
                            .html(`
                                <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; border-left: 4px solid #28a745;">
                                    <strong>✅ Evento creato con successo!</strong>
                                    <p style="margin: 10px 0 0 0;">
                                        <a href="${response.data.edit_url}" class="button button-primary">Modifica Evento</a>
                                        <a href="${response.data. view_url}" class="button" target="_blank">Visualizza</a>
                                    </p>
                                    ${photoLinksHtml}
                                </div>
                            `)
                            .slideDown();
                        
                        // Auto-scroll verso il messaggio di successo
                        $('html, body').animate({
                            scrollTop: MEP.statusMsg.offset().top - 100
                        }, 800);
                        
                        // Reset form dopo 5 secondi (tempo per copiare i link)
                        setTimeout(() => {
                            if (confirm('Vuoi creare un altro evento?')) {
                                location.reload();
                            }
                        }, 5000);
                    } else {
                        MEP.statusMsg
                            .html(`<div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px;">❌ <strong>Errore:</strong> ${response.data.message}</div>`)
                            .slideDown();
                        
                        MEP.submitBtn.prop('disabled', false).text('Crea Evento');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ Errore submit:', {xhr, status, error});
                    
                    let errorMsg = 'Errore di connessione';
                    
                    if (xhr.status === 403) {
                        errorMsg = 'Errore 403: Token di sicurezza non valido. Ricarica la pagina (Ctrl+F5) e riprova.';
                    } else if (xhr.status === 500) {
                        errorMsg = 'Errore 500: Problema del server durante la creazione. ';
                    } else if (status === 'timeout') {
                        errorMsg = 'Timeout: La creazione sta impiegando troppo tempo.  Riprova. ';
                    } else {
                        errorMsg = `Errore di connessione: ${error}`;
                    }
                    
                    MEP.statusMsg
                        .html(`<div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px;">❌ <strong>Errore:</strong> ${errorMsg}</div>`)
                        .slideDown();
                    
                    MEP.submitBtn.prop('disabled', false). text('Crea Evento');
                }
            });
        });
        
    });
    
})(jQuery);