/**
 * Admin JavaScript for Capacity T-Shirts Stores plugin.
 *
 * @package CapacityTShirtsStores
 */

(function ($) {
    'use strict';

    $(document).ready(function () {
        // Handle store type change
        $('#store_type').on('change', function () {
            const storeType = $(this).val();
            updateOAuthSection(storeType);
        });

        // Handle OAuth connect button click
        $('#oauth-connect-button').on('click', function (e) {
            // Button will navigate to OAuth URL, no additional JS needed
        });

        // Confirm delete actions
        $('.button-link-delete').on('click', function (e) {
            if (!confirm(capacityTShirtsStores.strings.confirmDelete)) {
                e.preventDefault();
                return false;
            }
        });

        /**
         * Update OAuth section based on store type
         */
        function updateOAuthSection(storeType) {
            // Reload the page to show/hide credentials form based on store type
            // This ensures the server-side check for credentials is accurate
            const url = new URL(window.location.href);
            url.searchParams.set('store_type', storeType);
            // Don't auto-reload, let user save first if they've made changes
            // window.location.href = url.toString();
        }

        // Initialize
        const currentStoreType = $('#store_type').val();
        if (currentStoreType) {
            updateOAuthSection(currentStoreType);
        }

        // Handle Webflow shipping information update
        $('#webflow-shipping-form').on('submit', function(e) {
            e.preventDefault();
            
            const form = $(this);
            const submitBtn = form.find('button[type="submit"]');
            const spinner = form.find('.spinner');
            const messageDiv = $('#shipping-update-message');
            
            // Show overlay
            showOverlay();
            
            // Disable button and show spinner
            submitBtn.prop('disabled', true);
            spinner.addClass('is-active');
            messageDiv.hide();
            
            // Get form data
            const formData = form.serialize();
            
            // Submit via AJAX
            $.ajax({
                url: capacityTShirtsStores.ajaxUrl,
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showMessage(messageDiv, response.data.message, 'success');
                    } else {
                        showMessage(messageDiv, response.data.message, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    showMessage(messageDiv, 'An error occurred while updating shipping information.', 'error');
                },
                complete: function() {
                    // Hide overlay
                    hideOverlay();
                    
                    // Re-enable button and hide spinner
                    submitBtn.prop('disabled', false);
                    spinner.removeClass('is-active');
                }
            });
        });

        // Handle Webflow comment update
        $('#webflow-comment-form').on('submit', function(e) {
            e.preventDefault();
            
            const form = $(this);
            const submitBtn = form.find('button[type="submit"]');
            const spinner = form.find('.spinner');
            const messageDiv = $('#comment-update-message');
            
            // Show overlay
            showOverlay();
            
            // Disable button and show spinner
            submitBtn.prop('disabled', true);
            spinner.addClass('is-active');
            messageDiv.hide();
            
            // Get form data
            const formData = form.serialize();
            
            // Submit via AJAX
            $.ajax({
                url: capacityTShirtsStores.ajaxUrl,
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showMessage(messageDiv, response.data.message, 'success');
                    } else {
                        showMessage(messageDiv, response.data.message, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    showMessage(messageDiv, 'An error occurred while updating comment.', 'error');
                },
                complete: function() {
                    // Hide overlay
                    hideOverlay();
                    
                    // Re-enable button and hide spinner
                    submitBtn.prop('disabled', false);
                    spinner.removeClass('is-active');
                }
            });
        });

        /**
         * Show overlay
         */
        function showOverlay() {
            if ($('#capacity-tshirts-overlay').length === 0) {
                $('body').append('<div id="capacity-tshirts-overlay" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); z-index: 999999; display: flex; align-items: center; justify-content: center;"><div style="background: #fff; padding: 20px; border-radius: 4px; text-align: center;"><div class="spinner is-active" style="float: none; margin: 0 auto;"></div><p style="margin: 15px 0 0 0;">Updating order...</p></div></div>');
            }
            $('#capacity-tshirts-overlay').fadeIn(200);
        }

        /**
         * Hide overlay
         */
        function hideOverlay() {
            $('#capacity-tshirts-overlay').fadeOut(200, function() {
                $(this).remove();
            });
        }

        /**
         * Show message
         */
        function showMessage(element, message, type) {
            element
                .removeClass('notice-success notice-error')
                .addClass('notice notice-' + (type === 'success' ? 'success' : 'error'))
                .html('<p>' + message + '</p>')
                .fadeIn(200);
            
            // Auto-hide after 5 seconds (unless it's an error with a link)
            if (type !== 'error' || message.indexOf('<a') === -1) {
                setTimeout(function() {
                    element.fadeOut(200);
                }, 5000);
            }
        }

        /**
         * Update conditional fields visibility based on status selection
         */
        function updateStatusFields() {
            const status = $('#order_status').val();
            const fulfillEmailRow = $('#fulfill-email-row');
            const refundReasonRow = $('#refund-reason-row');
            
            // Hide all conditional rows first
            fulfillEmailRow.hide();
            refundReasonRow.hide();
            
            // Show relevant row based on selection
            if (status === 'fulfill') {
                fulfillEmailRow.show();
            } else if (status === 'refund') {
                refundReasonRow.show();
            }
        }

        // Handle order status dropdown change to show/hide conditional fields
        $('#order_status').on('change', updateStatusFields);
        
        // Trigger on page load if status is already selected
        if ($('#order_status').length > 0) {
            updateStatusFields();
        }

        // Handle Webflow order status update
        $('#webflow-status-form').on('submit', function(e) {
            e.preventDefault();
            
            const form = $(this);
            const submitBtn = form.find('button[type="submit"]');
            const spinner = form.find('.spinner');
            const messageDiv = $('#status-update-message');
            const statusSelect = form.find('#order_status');
            
            // Validate status selection
            if (!statusSelect.val()) {
                showMessage(messageDiv, 'Please select an order status.', 'error');
                return;
            }
            
            // Show overlay
            showOverlay();
            
            // Disable button and show spinner
            submitBtn.prop('disabled', true);
            spinner.addClass('is-active');
            messageDiv.hide();
            
            // Get form data
            const formData = form.serialize();
            
            // Submit via AJAX
            $.ajax({
                url: capacityTShirtsStores.ajaxUrl,
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showMessage(messageDiv, response.data.message, 'success');
                        // Optionally reload page after 2 seconds to show updated status
                        setTimeout(function() {
                            window.location.reload();
                        }, 2000);
                    } else {
                        showMessage(messageDiv, response.data.message, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    showMessage(messageDiv, 'An error occurred while updating order status.', 'error');
                },
                complete: function() {
                    // Hide overlay
                    hideOverlay();
                    
                    // Re-enable button and hide spinner
                    submitBtn.prop('disabled', false);
                    spinner.removeClass('is-active');
                }
            });
        });
    });
})(jQuery);

