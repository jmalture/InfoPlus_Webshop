/**
 * User Icon Dropdown Handler for Elementor
 * Adds dropdown functionality to the user icon widget
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Target the Elementor icon widget
        const $iconWidget = $('#log-in'); // Your Elementor widget ID
        const $iconLink = $iconWidget.find('.elementor-icon');
        
        if ($iconWidget.length === 0) {
            console.log('User icon widget not found');
            return;
        }

        console.log('User icon widget found!');

        // Check if user is logged in (passed from PHP)
        const isLoggedIn = typeof infoplusMenuDropdown !== 'undefined' && infoplusMenuDropdown.isLoggedIn;

        // Update the icon link based on login status
        if (isLoggedIn) {
            $iconLink.attr('href', infoplusMenuDropdown.accountUrl);
        } else {
            $iconLink.attr('href', infoplusMenuDropdown.loginUrl);
        }

        // Build dropdown menu HTML based on login status
        let dropdownHtml = '<div class="user-dropdown-menu">';
        
        if (isLoggedIn) {
            // Logged in - show Dashboard and Logout
            dropdownHtml += '<a href="' + infoplusMenuDropdown.accountUrl + '" class="dropdown-item">';
            dropdownHtml += '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>';
            dropdownHtml += '<span>Dashboard</span>';
            dropdownHtml += '</a>';
            dropdownHtml += '<a href="' + infoplusMenuDropdown.logoutUrl + '" class="dropdown-item">';
            dropdownHtml += '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>';
            dropdownHtml += '<span>Abmelden</span>';
            dropdownHtml += '</a>';
        } else {
            // Not logged in - show Sign In and Register
            dropdownHtml += '<a href="' + infoplusMenuDropdown.loginUrl + '" class="dropdown-item">';
            dropdownHtml += '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path><polyline points="10 17 15 12 10 7"></polyline><line x1="15" y1="12" x2="3" y2="12"></line></svg>';
            dropdownHtml += '<span>Anmelden</span>';
            dropdownHtml += '</a>';
            dropdownHtml += '<a href="' + infoplusMenuDropdown.registerUrl + '" class="dropdown-item">';
            dropdownHtml += '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg>';
            dropdownHtml += '<span>Registrieren</span>';
            dropdownHtml += '</a>';
        }
        
        dropdownHtml += '</div>';

        // Insert dropdown into the widget container
        $iconWidget.find('.elementor-widget-container').append(dropdownHtml);

        const $dropdown = $iconWidget.find('.user-dropdown-menu');

        // Show dropdown on hover (with delay to prevent flickering)
        let hoverTimeout;
        
        $iconWidget.on('mouseenter', function() {
            clearTimeout(hoverTimeout);
            hoverTimeout = setTimeout(function() {
                $dropdown.addClass('show');
            }, 150); // Small delay before showing
        });

        $iconWidget.on('mouseleave', function() {
            clearTimeout(hoverTimeout);
            hoverTimeout = setTimeout(function() {
                $dropdown.removeClass('show');
            }, 200); // Small delay before hiding
        });

        // Keep dropdown open when hovering over it
        $dropdown.on('mouseenter', function() {
            clearTimeout(hoverTimeout);
            $(this).addClass('show');
        });

        $dropdown.on('mouseleave', function() {
            $(this).removeClass('show');
        });

        console.log('Dropdown menu initialized');
    });

})(jQuery);
