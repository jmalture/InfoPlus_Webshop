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
            this.bindProfileForm();    // legacy Elementor form
            this.bindSettingsForm();   // new [infoplus_profile_settings] shortcode form
            this.bindPasswordForm();
            this.bindAvatarUpload();
        },

        /**
         * Load current user data and populate all forms
         */
        loadUserData: function() {
            $.ajax({
                url: infoplusUserDashboard.restUrl + 'user/profile',
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', infoplusUserDashboard.nonce);
                },
                success: function(response) {
                    if (!response.success || !response.data) return;
                    const d = response.data;

                    // ── Legacy Elementor form fields ──────────────────────────
                    $('input[name="form_fields[user_name]"]').val(d.user_name   || '');
                    $('input[name="form_fields[user_email]"]').val(d.user_email || '');
                    $('input[name="form_fields[user_company]"]').val(d.user_company || '');

                    // ── [infoplus_profile_settings] shortcode fields ──────────
                    $('#infoplus-field-first_name').val(d.first_name || '');
                    $('#infoplus-field-last_name').val(d.last_name   || '');
                    $('#infoplus-field-email').val(d.user_email      || '');
                    $('#infoplus-field-phone').val(d.phone           || '');
                    $('#infoplus-field-company').val(d.company       || '');
                    $('#infoplus-field-vat_id').val(d.vat_id         || '');
                    $('#infoplus-field-address_1').val(d.address_1   || '');
                    $('#infoplus-field-address_2').val(d.address_2   || '');
                    $('#infoplus-field-postcode').val(d.postcode     || '');
                    $('#infoplus-field-city').val(d.city             || '');
                    if (d.country) {
                        $('#infoplus-field-country').val(d.country);
                    }

                    // Avatar preview
                    if (d.avatar_url) {
                        $('#infoplus-avatar-preview').attr('src', d.avatar_url);
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
         * Bind the [infoplus_profile_settings] shortcode form (#infoplus-profile-settings-form).
         * Collects all field values and POSTs to /user/profile.
         * Feedback shown inline rather than via the global toast, so the user can see it
         * without scrolling.
         */
        bindSettingsForm: function() {
            const self = this;
            const $form = $('#infoplus-profile-settings-form');

            if ($form.length === 0) {
                return; // Shortcode not on this page — no-op
            }

            $form.on('submit', function(e) {
                e.preventDefault();

                const $btn      = $form.find('.infoplus-settings-save-btn');
                const $feedback = $('#infoplus-settings-feedback');
                const origText  = $btn.html();

                $btn.prop('disabled', true).html('Wird gespeichert …');
                $feedback.hide().removeClass('success error');

                const payload = {
                    first_name : $('#infoplus-field-first_name').val().trim(),
                    last_name  : $('#infoplus-field-last_name').val().trim(),
                    email      : $('#infoplus-field-email').val().trim(),
                    phone      : $('#infoplus-field-phone').val().trim(),
                    company    : $('#infoplus-field-company').val().trim(),
                    vat_id     : $('#infoplus-field-vat_id').val().trim(),
                    address_1  : $('#infoplus-field-address_1').val().trim(),
                    address_2  : $('#infoplus-field-address_2').val().trim(),
                    postcode   : $('#infoplus-field-postcode').val().trim(),
                    city       : $('#infoplus-field-city').val().trim(),
                    country    : $('#infoplus-field-country').val(),
                };

                $.ajax({
                    url: infoplusUserDashboard.restUrl + 'user/profile',
                    method: 'POST',
                    data: payload,
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', infoplusUserDashboard.nonce);
                    },
                    success: function(response) {
                        if (response.success) {
                            $feedback
                                .addClass('success')
                                .text(response.message || 'Profil erfolgreich aktualisiert!')
                                .show();
                            // Auto-hide after 4 s
                            setTimeout(function() { $feedback.fadeOut(); }, 4000);
                        }
                    },
                    error: function(xhr) {
                        const msg = xhr.responseJSON && xhr.responseJSON.message
                            ? xhr.responseJSON.message
                            : 'Speichern fehlgeschlagen. Bitte erneut versuchen.';
                        $feedback.addClass('error').text(msg).show();
                    },
                    complete: function() {
                        $btn.prop('disabled', false).html(origText);
                    }
                });
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
            const $form      = $('#infoplus_password_form');
            const $submitBtn = $form.find('button[type="submit"]');
            const originalHtml = $submitBtn.html();
            const $feedback  = $('#infoplus-password-feedback');

            $submitBtn.prop('disabled', true).text('Aktualisierung...');
            if ($feedback.length) $feedback.hide().removeClass('success error');

            $.ajax({
                url: infoplusUserDashboard.restUrl + 'user/password',
                method: 'POST',
                data: data,
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', infoplusUserDashboard.nonce);
                },
                success: function(response) {
                    if (response.success) {
                        const msg = response.message || 'Passwort erfolgreich geändert!';
                        if ($feedback.length) {
                            $feedback.addClass('success').text(msg).show();
                            setTimeout(function() { $feedback.fadeOut(); }, 4000);
                        } else {
                            UserDashboard.showMessage('success', msg);
                        }
                        // Clear password fields
                        $form.find('input[type="password"]').val('');
                    }
                },
                error: function(xhr) {
                    const error = xhr.responseJSON && xhr.responseJSON.message
                        ? xhr.responseJSON.message
                        : 'Passwortänderung fehlgeschlagen';
                    if ($feedback.length) {
                        $feedback.addClass('error').text(error).show();
                    } else {
                        UserDashboard.showMessage('error', error);
                    }
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).html(originalHtml);
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