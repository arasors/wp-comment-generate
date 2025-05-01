(function($) {
    'use strict';
    
    // Initialize the plugin when the DOM is ready
    $(document).ready(function() {
        var currentProductId = null;
        var customModelIndex = $('.cg-custom-model-row').length;
        
        // Tab switching
        $('.cg-tab-link').on('click', function(e) {
            e.preventDefault();
            
            var tabId = $(this).data('tab');
            
            // Update active tab
            $('.cg-tab-link').removeClass('active');
            $(this).addClass('active');
            
            // Show the selected tab content
            $('.cg-tab-content').removeClass('active');
            $('#' + tabId + '-tab').addClass('active');
        });
        
        // Auto-response setting visibility
        function toggleAutoResponseFields() {
            if ($('#cg_enable_auto_response').is(':checked')) {
                $('#cg_auto_response_text').closest('.cg-form-row').show();
                
                // Add visual indicator to the products tab
                if ($('.cg-auto-response-enabled').length === 0) {
                    $('#products-tab .cg-card:first').prepend(
                        '<div class="cg-auto-response-enabled">' +
                        '<strong>' + 'Auto-response is enabled' + '</strong>: ' + 
                        'All generated comments will receive an automatic reply.' +
                        '</div>'
                    );
                }
            } else {
                $('#cg_auto_response_text').closest('.cg-form-row').hide();
                $('.cg-auto-response-enabled').remove();
            }
        }
        
        $('#cg_enable_auto_response').on('change', toggleAutoResponseFields);
        
        // Call on page load to set initial state
        toggleAutoResponseFields();
        
        // Handle adding custom model
        $('#cg-add-model').on('click', function() {
            var newRow = '<div class="cg-custom-model-row">' +
                '<input type="text" name="cg_custom_models[' + customModelIndex + '][id]" placeholder="' + cg_data.model_id_placeholder + '" class="medium-text" />' +
                '<input type="text" name="cg_custom_models[' + customModelIndex + '][name]" placeholder="' + cg_data.model_name_placeholder + '" class="medium-text" />' +
                '<button type="button" class="button cg-remove-model">' + cg_data.remove_text + '</button>' +
                '</div>';
            
            $('#cg-custom-models-container').append(newRow);
            customModelIndex++;
        });
        
        // Handle removing custom model
        $(document).on('click', '.cg-remove-model', function() {
            $(this).closest('.cg-custom-model-row').remove();
        });
        
        // Min rating change - ensure max is not less than min
        $('#cg_min_rating').on('change', function() {
            var minRating = parseInt($(this).val());
            var maxRating = parseInt($('#cg_max_rating').val());
            
            if (minRating > maxRating) {
                $('#cg_max_rating').val(minRating);
            }
        });
        
        // Max rating change - ensure min is not greater than max
        $('#cg_max_rating').on('change', function() {
            var maxRating = parseInt($(this).val());
            var minRating = parseInt($('#cg_min_rating').val());
            
            if (maxRating < minRating) {
                $('#cg_min_rating').val(maxRating);
            }
        });
        
        // Open modal and generate comments
        $('.cg-products-table').on('click', '.cg-generate-btn', function() {
            var $row = $(this).closest('tr');
            currentProductId = $row.data('product-id');
            var productName = $row.find('.cg-product-name strong').text();
            
            // Set product name in modal
            $('#cg-modal-product-name').text(productName);
            
            // Clear previous comments
            $('.cg-comments-list').empty();
            $('#cg-custom-prompt').val('');
            
            // Show modal
            $('#cg-comments-modal').show();
            
            // Generate comments
            generateComments();
        });
        
        // Close modal
        $('.cg-modal-close').on('click', function() {
            $('#cg-comments-modal').hide();
        });
        
        // Close modal when clicking outside
        $(window).on('click', function(e) {
            if ($(e.target).is('#cg-comments-modal')) {
                $('#cg-comments-modal').hide();
            }
        });
        
        // Regenerate comments
        $('.cg-regenerate-btn').on('click', function() {
            generateComments();
        });
        
        // Save selected comments
        $('.cg-save-btn').on('click', function() {
            saveComments();
        });
        
        // Handle single comment regeneration
        $(document).on('click', '.cg-regenerate-comment-btn', function() {
            var $commentItem = $(this).closest('.cg-comment-item');
            var commentIndex = $commentItem.data('index');
            
            // Add regenerating class for visual feedback
            $commentItem.addClass('regenerating');
            
            // Get additional prompt if any
            var additionalPrompt = $('#cg-custom-prompt').val();
            
            // AJAX call to regenerate a single comment
            $.ajax({
                url: cg_data.ajax_url,
                type: 'POST',
                data: {
                    action: 'cg_regenerate_single_comment',
                    nonce: cg_data.nonce,
                    product_id: currentProductId,
                    additional_prompt: additionalPrompt
                },
                success: function(response) {
                    // Remove regenerating class
                    $commentItem.removeClass('regenerating');
                    
                    if (response.success) {
                        // Replace the comment with the new one
                        replaceComment($commentItem, response.data.comment, commentIndex);
                    } else {
                        showError(response.data.message);
                    }
                },
                error: function() {
                    // Remove regenerating class
                    $commentItem.removeClass('regenerating');
                    
                    showError('An error occurred while connecting to the server.');
                }
            });
        });
        
        // Generate comments function
        function generateComments() {
            if (!currentProductId) {
                return;
            }
            
            // Show loading spinner
            $('.cg-comments-list').hide();
            $('.cg-loading-spinner').show();
            
            // Get additional prompt if any
            var additionalPrompt = $('#cg-custom-prompt').val();
            
            // AJAX call to generate comments
            $.ajax({
                url: cg_data.ajax_url,
                type: 'POST',
                data: {
                    action: 'cg_generate_comments',
                    nonce: cg_data.nonce,
                    product_id: currentProductId,
                    additional_prompt: additionalPrompt
                },
                success: function(response) {
                    // Hide loading spinner
                    $('.cg-loading-spinner').hide();
                    $('.cg-comments-list').show();
                    
                    if (response.success) {
                        renderComments(response.data.comments);
                    } else {
                        if (response.data && response.data.debug_info) {
                            showErrorWithDebug(response.data.message, response.data.debug_info);
                        } else {
                            showError(response.data.message);
                        }
                    }
                },
                error: function() {
                    // Hide loading spinner
                    $('.cg-loading-spinner').hide();
                    $('.cg-comments-list').show();
                    
                    showError('An error occurred while connecting to the server.');
                }
            });
        }
        
        // Save comments function
        function saveComments() {
            if (!currentProductId) {
                return;
            }
            
            // Collect selected comments
            var selectedComments = [];
            
            $('.cg-comment-item').each(function() {
                var $item = $(this);
                var isSelected = $item.find('.cg-comment-checkbox').prop('checked');
                
                if (isSelected) {
                    selectedComments.push({
                        name: $item.data('name'),
                        rating: $item.data('rating'),
                        comment: $item.data('comment'),
                        selected: 'true'
                    });
                }
            });
            
            if (selectedComments.length === 0) {
                showError('Please select at least one comment to save.');
                return;
            }
            
            // Show loading text
            var $saveBtn = $('.cg-save-btn');
            var originalText = $saveBtn.html();
            $saveBtn.html('<span class="spinner is-active" style="float: none; margin: 0 5px 0 0;"></span>' + cg_data.saving_text);
            $saveBtn.prop('disabled', true);
            
            // AJAX call to save comments
            $.ajax({
                url: cg_data.ajax_url,
                type: 'POST',
                data: {
                    action: 'cg_save_comments',
                    nonce: cg_data.nonce,
                    product_id: currentProductId,
                    comments: selectedComments
                },
                success: function(response) {
                    // Restore button
                    $saveBtn.html(originalText);
                    $saveBtn.prop('disabled', false);
                    
                    if (response.success) {
                        // Show success message
                        $('.cg-comments-list').prepend(
                            '<div class="notice notice-success inline"><p>' + 
                            response.data.message + 
                            '</p></div>'
                        );
                        
                        // Close modal after a delay
                        setTimeout(function() {
                            $('#cg-comments-modal').hide();
                        }, 2000);
                    } else {
                        showError(response.data.message);
                    }
                },
                error: function() {
                    // Restore button
                    $saveBtn.html(originalText);
                    $saveBtn.prop('disabled', false);
                    
                    showError('An error occurred while connecting to the server.');
                }
            });
        }
        
        // Render comments in the modal
        function renderComments(comments) {
            var $commentsList = $('.cg-comments-list');
            $commentsList.empty();
            
            if (!comments || comments.length === 0) {
                $commentsList.html('<div class="notice notice-error inline"><p>No comments were generated. Please try again with a different prompt.</p></div>');
                return;
            }
            
            // Loop through the comments and create HTML
            $.each(comments, function(index, comment) {
                var stars = '';
                for (var i = 1; i <= 5; i++) {
                    if (i <= comment.rating) {
                        stars += '<span class="cg-star dashicons dashicons-star-filled"></span>';
                    } else {
                        stars += '<span class="cg-star dashicons dashicons-star-empty"></span>';
                    }
                }
                
                var $commentItem = $(
                    '<div class="cg-comment-item" data-index="' + index + '" data-name="' + escapeHtml(comment.name) + '" data-rating="' + comment.rating + '" data-comment="' + escapeHtml(comment.comment) + '">' +
                        '<div class="cg-comment-actions">' +
                            '<button type="button" class="cg-regenerate-comment-btn" title="' + cg_data.regenerate_comment_text + '">' +
                                '<span class="dashicons dashicons-update"></span>' +
                            '</button>' +
                        '</div>' +
                        '<div class="cg-comment-header">' +
                            '<div class="cg-comment-author">' + escapeHtml(comment.name) + '</div>' +
                            '<div class="cg-comment-rating">' + stars + '</div>' +
                        '</div>' +
                        '<div class="cg-comment-text">' + escapeHtml(comment.comment) + '</div>' +
                        '<div class="cg-comment-select">' +
                            '<input type="checkbox" class="cg-comment-checkbox" ' + (comment.selected ? 'checked' : '') + ' id="comment-' + index + '">' +
                            '<label for="comment-' + index + '">Select this comment</label>' +
                        '</div>' +
                    '</div>'
                );
                
                $commentsList.append($commentItem);
            });
        }
        
        // Replace a single comment with a new one
        function replaceComment($commentItem, comment, index) {
            var stars = '';
            for (var i = 1; i <= 5; i++) {
                if (i <= comment.rating) {
                    stars += '<span class="cg-star dashicons dashicons-star-filled"></span>';
                } else {
                    stars += '<span class="cg-star dashicons dashicons-star-empty"></span>';
                }
            }
            
            // Update data attributes
            $commentItem.data('name', comment.name);
            $commentItem.data('rating', comment.rating);
            $commentItem.data('comment', comment.comment);
            $commentItem.attr('data-name', comment.name);
            $commentItem.attr('data-rating', comment.rating);
            $commentItem.attr('data-comment', comment.comment);
            
            // Update DOM content
            $commentItem.find('.cg-comment-author').text(comment.name);
            $commentItem.find('.cg-comment-rating').html(stars);
            $commentItem.find('.cg-comment-text').text(comment.comment);
            
            // Flash effect to highlight the change
            $commentItem.css('background-color', '#f7fcff');
            setTimeout(function() {
                $commentItem.css('transition', 'background-color 1s ease');
                $commentItem.css('background-color', '');
            }, 50);
        }
        
        // Helper function to show error message
        function showError(message) {
            $('.cg-comments-list').prepend(
                '<div class="notice notice-error inline"><p>' + 
                message + 
                '</p></div>'
            );
        }
        
        // Helper function to show error message with debug info
        function showErrorWithDebug(message, debugInfo) {
            var debugHtml = '';
            
            if (debugInfo && debugInfo.raw_response) {
                debugHtml = '<div class="cg-debug-info">' +
                    '<h4>' + 'Debug Information:' + '</h4>' +
                    '<div class="cg-debug-params">' +
                    '<strong>Product Name:</strong> ' + escapeHtml(debugInfo.product_name) + '<br>' +
                    '<strong>Rating Range:</strong> ' + debugInfo.min_rating + '-' + debugInfo.max_rating + '<br>' +
                    '</div>' +
                    '<div class="cg-raw-response">' +
                    '<strong>Raw API Response:</strong>' +
                    '<textarea readonly rows="10" class="large-text code">' + escapeHtml(debugInfo.raw_response) + '</textarea>' +
                    '</div>' +
                    '<p class="description">' + 'This information can help debug why the parsing failed. Check if the API response contains properly formatted reviews.' + '</p>' +
                    '</div>';
            }
            
            $('.cg-comments-list').prepend(
                '<div class="notice notice-error inline">' +
                '<p>' + message + '</p>' +
                '</div>' +
                debugHtml
            );
        }
        
        // Helper function to escape HTML
        function escapeHtml(text) {
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }
        
        // Function to add a comment to the list
        function addCommentToList(comment, productId) {
            var stars = '';
            for (var i = 1; i <= 5; i++) {
                if (i <= comment.rating) {
                    stars += '<span class="dashicons dashicons-star-filled"></span>';
                } else {
                    stars += '<span class="dashicons dashicons-star-empty"></span>';
                }
            }
            
            var commentHtml = '<div class="cg-comment-item">' +
                            '<div class="cg-comment-header">' +
                            '<input type="checkbox" class="cg-comment-checkbox" checked>' +
                            '<span class="cg-comment-author">' + comment.name + '</span>' +
                            '<div class="cg-comment-rating" data-rating="' + comment.rating + '">' + stars + '</div>' +
                            '<button type="button" class="cg-regenerate-single-btn" title="' + cg_data.regenerate_comment_text + '">' +
                            '<span class="dashicons dashicons-update"></span>' +
                            '</button>' +
                            '</div>' +
                            '<div class="cg-comment-body">' +
                            '<div class="cg-comment-text">' + comment.comment + '</div>' +
                            '</div>' +
                            '</div>';
                            
            $('.cg-comments-list').append(commentHtml);
            
            // Add auto-response info if enabled
            if ($('#cg_enable_auto_response').is(':checked')) {
                var responseText = $('#cg_auto_response_text').val();
                var responseHtml = '<div class="cg-auto-response">' +
                                 '<div class="cg-response-label"><span class="dashicons dashicons-admin-comments"></span> Auto-response:</div>' +
                                 '<div class="cg-response-text">' + responseText + '</div>' +
                                 '</div>';
                $('.cg-comments-list .cg-comment-item:last .cg-comment-body').append(responseHtml);
            }
        }
    });
    
})(jQuery); 