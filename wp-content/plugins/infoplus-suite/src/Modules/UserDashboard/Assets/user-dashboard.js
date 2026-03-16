/**
 * User Dashboard Frontend Handler
 * Handles Elementor forms and avatar uploads
 */
(function($) {
    'use strict';

    const UserDashboard = {
        initialized: false,
        
        init: function() {
            // Prevent double initialization
            if (this.initialized) {
                return;
            }
            this.initialized = true;
            
            this.loadUserData();
            this.bindProfileForm();
            this.bindPasswordForm();
            this.bindAvatarUpload();
        },

        /**
         * Load current user data and populate forms
         */
        loadUserData: function() {
            $.ajax({
                url: infoplusUserDashboard.restUrl + 'user/profile',
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', infoplusUserDashboard.nonce);
                },
                success: function(response) {
                    if (response.success && response.data) {
                        // Populate profile form
                        $('input[name="form_fields[user_name]"]').val(response.data.user_name);
                        $('input[name="form_fields[user_email]"]').val(response.data.user_email);
                        $('input[name="form_fields[user_company]"]').val(response.data.user_company || '');
                    }
                },
                error: function(xhr) {
                    console.error('Failed to load user data:', xhr);
                }
            });
        },

        /**
         * Bind profile form submission
         */
        bindProfileForm: function() {
            const self = this;
            
            // Find the profile form by ID
            const $form = $('#infoplus_profile_form');
            
            if ($form.length === 0) {
                console.warn('Profile form not found');
                return;
            }

            // Intercept Elementor form submission
            $form.on('submit_success', function(e, response) {
                e.preventDefault();
                
                // Get form data
                const formData = {
                    user_name: $('input[name="form_fields[user_name]"]').val(),
                    user_email: $('input[name="form_fields[user_email]"]').val(),
                    user_company: $('input[name="form_fields[user_company]"]').val()
                };

                self.updateProfile(formData);
            });

            // Also handle regular submit as fallback
            $form.on('submit', function(e) {
                e.preventDefault();
                
                const formData = {
                    user_name: $('input[name="form_fields[user_name]"]').val(),
                    user_email: $('input[name="form_fields[user_email]"]').val(),
                    user_company: $('input[name="form_fields[user_company]"]').val()
                };

                self.updateProfile(formData);
            });
        },

        /**
         * Update user profile via AJAX
         */
        updateProfile: function(data) {
            const $submitBtn = $('#infoplus_profile_form').find('button[type="submit"]');
            const originalText = $submitBtn.text();
            
            $submitBtn.prop('disabled', true).text('Speichern...');

            $.ajax({
                url: infoplusUserDashboard.restUrl + 'user/profile',
                method: 'POST',
                data: data,
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', infoplusUserDashboard.nonce);
                },
                success: function(response) {
                    if (response.success) {
                        UserDashboard.showMessage('success', response.message || 'Profil erfolgreich aktualisiert!');
                        
                        // Reload page after 1.5 seconds to refresh dynamic fields
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    }
                },
                error: function(xhr) {
                    const error = xhr.responseJSON && xhr.responseJSON.message 
                        ? xhr.responseJSON.message 
                        : 'Profilaktualisierung fehlgeschlagen';
                    UserDashboard.showMessage('error', error);
                },
                complete: function() {
                    // Only re-enable button if there was an error
                    // (if success, page will reload anyway)
                    if (!response || !response.success) {
                        $submitBtn.prop('disabled', false).text(originalText);
                    }
                }
            });
        },

        /**
         * Bind password form submission
         */
        bindPasswordForm: function() {
            const self = this;
            
            const $form = $('#infoplus_password_form');
            
            if ($form.length === 0) {
                console.warn('Password form not found');
                return;
            }

            // Intercept Elementor form submission
            $form.on('submit_success', function(e, response) {
                e.preventDefault();
                
                const formData = {
                    current_password: $('input[name="form_fields[current_password]"]').val(),
                    new_password: $('input[name="form_fields[new_password]"]').val(),
                    confirm_password: $('input[name="form_fields[confirm_password]"]').val()
                };

                self.changePassword(formData);
            });

            // Also handle regular submit as fallback
            $form.on('submit', function(e) {
                e.preventDefault();
                
                const formData = {
                    current_password: $('input[name="form_fields[current_password]"]').val(),
                    new_password: $('input[name="form_fields[new_password]"]').val(),
                    confirm_password: $('input[name="form_fields[confirm_password]"]').val()
                };

                self.changePassword(formData);
            });
        },

        /**
         * Change password via AJAX
         */
        changePassword: function(data) {
            const $submitBtn = $('#infoplus_password_form').find('button[type="submit"]');
            const originalText = $submitBtn.text();
            
            $submitBtn.prop('disabled', true).text('Aktualisierung...');

            $.ajax({
                url: infoplusUserDashboard.restUrl + 'user/password',
                method: 'POST',
                data: data,
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', infoplusUserDashboard.nonce);
                },
                success: function(response) {
                    if (response.success) {
                        UserDashboard.showMessage('success', response.message || 'Passwort erfolgreich geändert!');
                        // Clear password fields
                        $('#infoplus_password_form').find('input[type="password"]').val('');
                    }
                },
                error: function(xhr) {
                    const error = xhr.responseJSON && xhr.responseJSON.message 
                        ? xhr.responseJSON.message 
                        : 'Passwortänderung fehlgeschlagen';
                    UserDashboard.showMessage('error', error);
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).text(originalText);
                }
            });
        },

        /**
         * Bind avatar upload functionality
         */
        bindAvatarUpload: function() {
            const $fileInput = $('#infoplus-avatar-upload');
            const $uploadBtn = $('#infoplus-avatar-btn');

            if ($fileInput.length === 0 || $uploadBtn.length === 0) {
                console.warn('Avatar-Upload-Elemente nicht gefunden');
                return;
            }

            // Remove any existing handlers to prevent duplicates
            $uploadBtn.off('click');
            $fileInput.off('change');

            // Trigger file input when button is clicked
            $uploadBtn.on('click', function(e) {
                e.preventDefault();
                $fileInput.click();
            });

            // Handle file selection
            $fileInput.on('change', function(e) {
                const file = e.target.files[0];
                
                if (!file) return;

                // Validate file type
                const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                if (!allowedTypes.includes(file.type)) {
                    UserDashboard.showMessage('error', 'Ungültiger Dateityp. Nur JPG, PNG und GIF sind zulässig.');
                    return;
                }

                // Validate file size (5MB max)
                const maxSize = 5 * 1024 * 1024;
                if (file.size > maxSize) {
                    UserDashboard.showMessage('error', 'Die Dateigröße darf 5 MB nicht überschreiten.');
                    return;
                }

                UserDashboard.uploadAvatar(file);
            });
        },

        /**
         * Upload avatar via AJAX
         */
        uploadAvatar: function(file) {
            const $uploadBtn = $('#infoplus-avatar-btn');
            const originalText = $uploadBtn.text();
            
            $uploadBtn.prop('disabled', true).text('Wird hochgeladen...');

            const formData = new FormData();
            formData.append('avatar', file);

            $.ajax({
                url: infoplusUserDashboard.restUrl + 'user/avatar',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', infoplusUserDashboard.nonce);
                },
                success: function(response) {
                    if (response.success) {
                        UserDashboard.showMessage('success', response.message || 'Avatar erfolgreich hochgeladen!');
                        
                        // Reload the page to show new avatar everywhere
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    }
                },
                error: function(xhr) {
                    const error = xhr.responseJSON && xhr.responseJSON.message 
                        ? xhr.responseJSON.message 
                        : 'Avatar-Upload fehlgeschlagen';
                    UserDashboard.showMessage('error', error);
                },
                complete: function() {
                    $uploadBtn.prop('disabled', false).text(originalText);
                    // Clear file input
                    $('#infoplus-avatar-upload').val('');
                }
            });
        },

        /**
         * Show success/error message
         */
        showMessage: function(type, message) {
            // Remove any existing messages
            $('.infoplus-message').remove();

            const bgColor = type === 'success' ? '#22c55e' : '#ef4444';
            
            const $message = $('<div class="infoplus-message"></div>')
                .css({
                    position: 'fixed',
                    top: '20px',
                    right: '20px',
                    padding: '16px 24px',
                    backgroundColor: bgColor,
                    color: 'white',
                    borderRadius: '8px',
                    boxShadow: '0 4px 12px rgba(0,0,0,0.15)',
                    zIndex: 99999,
                    fontSize: '15px',
                    fontWeight: '500',
                    maxWidth: '400px',
                    animation: 'slideInRight 0.3s ease'
                })
                .text(message);

            $('body').append($message);

            // Auto-remove after 4 seconds
            setTimeout(function() {
                $message.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 4000);
        }
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        UserDashboard.init();
    });

    // Also handle Elementor preview mode
    $(window).on('elementor/frontend/init', function() {
        UserDashboard.init();
    });

})(jQuery);