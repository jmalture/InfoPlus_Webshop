jQuery(document).ready(function($) {
    const $modelSelect = $('select[name^="attribute_pa_model"]');
    const $uploadWrapper = $('#upload_wrapper');
    const $uploadContainer = $('#upload_container');
    const $uploadButton = $('#upload');
    
    // Early exit if required elements don't exist
    if ($modelSelect.length === 0 || $uploadWrapper.length === 0) {
        return;
    }

    // Check if REST API settings are available
    if (typeof infoplusSonderkonturUpload === 'undefined') {
        console.error('Sonderkontur Upload: REST API settings not found');
        return;
    }

    // Create a hidden file input
    const $fileInput = $('<input type="file" id="sonderkontur_file_input" multiple accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.doc,.docx,.mp3,.mp4" style="display:none;">');
    $('body').append($fileInput);

    // Create file list container (will be inserted after the button)
    const $fileList = $('<div id="sonderkontur_file_list" class="sonderkontur-file-list"></div>');
    $uploadButton.after($fileList);

    // Array to store uploaded files (synced with backend)
    let uploadedFiles = [];
    
    // Max file size: 38MB in bytes
    const MAX_FILE_SIZE = 38 * 1024 * 1024;
    const MAX_FILE_SIZE_MB = 38;

    /**
     * Check if file type is an image
     */
    function isImageFile(type) {
        return type && type.startsWith('image/');
    }

    /**
     * Get file icon based on type
     */
    function getFileIcon(type) {
        if (type.startsWith('image/')) return '🖼️';
        if (type === 'application/pdf') return '📄';
        if (type.startsWith('audio/')) return '🎵';
        if (type.startsWith('video/')) return '🎬';
        return '📎';
    }

    /**
     * Check if the selected model contains "Sonderkontur"
     * and toggle the upload field visibility
     */
    function toggleUploadField() {
        const selectedValue = $modelSelect.val();
        const selectedText = $modelSelect.find('option:selected').text();
        
        const isSonderkontur = selectedValue.toLowerCase().includes('sonderkontur') || 
                              selectedText.toLowerCase().includes('sonderkontur');
        
        if (isSonderkontur) {
            $uploadWrapper.addClass('visible');
            console.log('Sonderkontur detected - showing upload field');
            loadExistingFiles();
        } else {
            $uploadWrapper.removeClass('visible');
            console.log('No Sonderkontur - hiding upload field');
            clearAllFiles();
        }
    }

    /**
     * Load existing files from backend session
     */
    function loadExistingFiles() {
        $.ajax({
            url: infoplusSonderkonturUpload.restUrl + 'sonderkontur/files',
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', infoplusSonderkonturUpload.nonce);
            },
            success: function(response) {
                console.log('Load files response:', response);
                if (response.success && response.files) {
                    uploadedFiles = response.files;
                    renderFileList();
                    console.log('Loaded', response.count, 'existing file(s) from session');
                }
            },
            error: function(xhr) {
                console.error('Failed to load existing files:', xhr);
                console.error('Status:', xhr.status);
                console.error('Response:', xhr.responseText);
            }
        });
    }

    /**
     * Render the file list UI
     */
    function renderFileList() {
        $fileList.empty();
        
        if (uploadedFiles.length === 0) {
            $fileList.hide();
            return;
        }
        
        $fileList.show();
        
        // Add header
        const $header = $('<div class="file-list-header">' +
            '<h4>Hochgeladene Dateien (' + uploadedFiles.length + ')</h4>' +
            '</div>');
        $fileList.append($header);
        
        // Add each file
        uploadedFiles.forEach((file, index) => {
            const icon = getFileIcon(file.type);
            const isImage = isImageFile(file.type);
            
            let $fileItem = $('<div class="file-item' + (isImage ? ' has-preview' : '') + '" data-index="' + index + '">' +
                '<div class="file-icon">' + icon + '</div>' +
                '<div class="file-info">' +
                    '<div class="file-name">' + file.name + '</div>' +
                    '<div class="file-size">' + file.size_formatted + '</div>' +
                '</div>' +
                '<button type="button" class="file-remove" data-index="' + index + '" title="Entfernen">' +
                    '<i class="fas fa-times"></i>' +
                '</button>' +
            '</div>');
            
            $fileList.append($fileItem);
        });
    }

    /**
     * Remove a file from the list (backend + frontend)
     */
    $fileList.on('click', '.file-remove', function(e) {
        e.preventDefault();
        const index = parseInt($(this).data('index'), 10);
        console.log('Removing file at index:', index);
        
        const $button = $(this);
        $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
        
        $.ajax({
            url: infoplusSonderkonturUpload.restUrl + 'sonderkontur/files/' + index,
            method: 'DELETE',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', infoplusSonderkonturUpload.nonce);
            },
            success: function(response) {
                if (response.success) {
                    uploadedFiles.splice(index, 1);
                    renderFileList();
                    showFeedback('✓ Datei entfernt', 'success');
                }
            },
            error: function(xhr) {
                console.error('Failed to delete file:', xhr);
                console.error('Status:', xhr.status);
                console.error('Response:', xhr.responseText);
                showFeedback('✗ Fehler beim Löschen', 'error');
                $button.prop('disabled', false).html('<i class="fas fa-times"></i>');
            }
        });
    });

    /**
     * Clear all files (backend + frontend)
     */
    function clearAllFiles() {
        if (uploadedFiles.length === 0) return;
        
        $.ajax({
            url: infoplusSonderkonturUpload.restUrl + 'sonderkontur/clear',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', infoplusSonderkonturUpload.nonce);
            },
            success: function(response) {
                if (response.success) {
                    uploadedFiles = [];
                    renderFileList();
                    console.log('All files cleared from session');
                }
            },
            error: function(xhr) {
                console.error('Failed to clear files:', xhr);
                console.error('Status:', xhr.status);
                console.error('Response:', xhr.responseText);
            }
        });
    }

    /**
     * Show feedback message in the upload container
     */
    function showFeedback(message, type = 'success') {
        const $heading = $uploadContainer.find('.elementor-heading-title');
        if ($heading.length) {
            const originalText = $heading.data('original-text') || $heading.text();
            
            if (!$heading.data('original-text')) {
                $heading.data('original-text', originalText);
            }
            
            $heading.text(message);
            
            if (type === 'success') {
                $heading.css('color', '#22c55e');
            } else if (type === 'error') {
                $heading.css('color', '#ef4444');
            }
            
            setTimeout(function() {
                $heading.text(originalText);
                $heading.css('color', '');
            }, 3000);
        }
    }

    /**
     * Upload files to backend
     */
    function uploadFiles(files) {
        if (!files || files.length === 0) {
            console.error('No files provided to uploadFiles!');
            return;
        }
        
        console.log('Uploading', files.length, 'file(s) to backend');
        console.log('REST URL:', infoplusSonderkonturUpload.restUrl + 'sonderkontur/upload');
        
        showFeedback('⏳ Hochladen...', 'success');
        
        const formData = new FormData();
        
        Array.from(files).forEach((file, index) => {
            console.log('Adding file to FormData:', file.name, file.type, file.size);
            formData.append('files[]', file);
        });
        
        $.ajax({
            url: infoplusSonderkonturUpload.restUrl + 'sonderkontur/upload',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', infoplusSonderkonturUpload.nonce);
                console.log('Sending request with nonce:', infoplusSonderkonturUpload.nonce);
            },
            success: function(response) {
                console.log('Upload response:', response);
                
                if (response.success) {
                    uploadedFiles = response.files || [];
                    renderFileList();
                    
                    let successMsg = response.uploaded_count === 1 
                        ? '✓ Datei hochgeladen!'
                        : '✓ ' + response.uploaded_count + ' Dateien hochgeladen!';
                    
                    if (response.errors && response.errors.length > 0) {
                        console.warn('Upload errors:', response.errors);
                        successMsg += ' (' + response.errors.length + ' Fehler)';
                    }
                    
                    showFeedback(successMsg, response.errors ? 'error' : 'success');
                } else {
                    showFeedback('✗ Hochladen fehlgeschlagen', 'error');
                    console.error('Upload failed:', response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('Upload error - Full details:');
                console.error('Status:', xhr.status);
                console.error('Status Text:', xhr.statusText);
                console.error('Ready State:', xhr.readyState);
                console.error('Response Text:', xhr.responseText);
                console.error('Error:', error);
                
                let errorMsg = 'Hochladen fehlgeschlagen';
                
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.message) {
                        errorMsg = response.message;
                    }
                    console.error('Parsed error response:', response);
                } catch (e) {
                    console.error('Could not parse error response');
                }
                
                showFeedback('✗ ' + errorMsg, 'error');
            }
        });
    }

    /**
     * Handle file selection (validation only, then upload)
     */
    function handleFiles(files) {
        if (!files || files.length === 0) {
            console.error('No files provided to handleFiles!');
            return;
        }
        
        console.log('Processing', files.length, 'file(s)');
        
        let validFiles = [];
        let rejectedFiles = [];
        
        Array.from(files).forEach((file) => {
            if (file.size > MAX_FILE_SIZE) {
                rejectedFiles.push({
                    name: file.name,
                    size: (file.size / 1024 / 1024).toFixed(2) + ' MB',
                    reason: 'Zu groß (max ' + MAX_FILE_SIZE_MB + 'MB)'
                });
                console.warn('File too large:', file.name, '-', (file.size / 1024 / 1024).toFixed(2) + ' MB');
            } else {
                validFiles.push(file);
            }
        });
        
        if (rejectedFiles.length > 0 && validFiles.length === 0) {
            let errorMsg = rejectedFiles.length === 1 
                ? '✗ Datei zu groß! Max. ' + MAX_FILE_SIZE_MB + 'MB'
                : '✗ ' + rejectedFiles.length + ' Dateien zu groß! Max. ' + MAX_FILE_SIZE_MB + 'MB';
            showFeedback(errorMsg, 'error');
            console.error('Rejected files:', rejectedFiles);
            return;
        }
        
        if (validFiles.length > 0) {
            uploadFiles(validFiles);
            
            if (rejectedFiles.length > 0) {
                setTimeout(function() {
                    showFeedback('⚠ ' + rejectedFiles.length + ' Datei(en) zu groß', 'error');
                }, 3000);
            }
        }
    }

    /**
     * Button click - trigger file input
     */
    $uploadButton.on('click', function(e) {
        e.preventDefault();
        $fileInput.click();
    });

    /**
     * File input change handler
     */
    $fileInput.on('change', function(e) {
        if (this.files && this.files.length > 0) {
            handleFiles(this.files);
            $(this).val('');
        }
    });

    /**
     * Drag & Drop handlers
     */
    
    $(document).on('drag dragstart dragend dragover dragenter dragleave drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
    });

    $uploadContainer.on('dragover dragenter', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).addClass('drag-hover');
    });

    $uploadContainer.on('dragleave dragend', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('drag-hover');
    });

    $uploadContainer.on('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('drag-hover');
        
        let files = null;
        
        if (e.originalEvent && e.originalEvent.dataTransfer) {
            files = e.originalEvent.dataTransfer.files;
        } else if (e.dataTransfer) {
            files = e.dataTransfer.files;
        }
        
        if (files && files.length > 0) {
            handleFiles(files);
        } else {
            console.error('No files found in drop event!');
        }
    });

    /**
     * Make container clickable
     */
    $uploadContainer.on('click', function(e) {
        if ($(e.target).closest('#upload').length === 0) {
            $fileInput.click();
        }
    });

    // Listen for model select changes
    $modelSelect.on('change', function() {
        toggleUploadField();
    });

    // Initial state
    toggleUploadField();
    setTimeout(toggleUploadField, 100);
});
