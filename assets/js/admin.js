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
        
        // Generate comments button click
        $('.cg-generate-btn').on('click', function() {
            var productRow = $(this).closest('tr');
            var productId = productRow.data('product-id');
            currentProductId = productId;
            var productName = productRow.find('.cg-product-name strong').text();
            var hasExistingComments = productRow.find('.cg-existing-comments').length > 0;
            
            // Set product name in modal
            $('#cg-modal-product-name').text(productName);
            
            // Eğer üründe mevcut yorumlar varsa, bilgi kutusu ekle
            if (hasExistingComments) {
                var commentCount = productRow.find('.cg-comment-badge').text().trim();
                var warningHtml = '<div class="cg-info-box cg-warning-box">' +
                                 '<span class="dashicons dashicons-warning"></span>' +
                                 '<p><strong>Dikkat:</strong> Bu ürün şu anda ' + commentCount + ' sahip. Yeni yorumlar eklemek, toplam yorum sayısını artıracaktır.</p>' +
                                 '</div>';
                
                // Mevcut uyarı mesajı varsa kaldır ve yenisini ekle
                $('.cg-warning-box').remove();
                $('.cg-edit-instructions').append(warningHtml);
            } else {
                // Ürünün yorumu yoksa, uyarı mesajını kaldır
                $('.cg-warning-box').remove();
            }
            
            // Open modal
            $('#cg-comments-modal').show();
            
            // Clear existing comments
            $('.cg-comments-list').empty();
            
            // Generate comments
            generateComments(productId);
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
            if (!currentProductId) {
                alert('Ürün ID bulunamadı. Lütfen sayfayı yenileyip tekrar deneyin.');
                return;
            }
            
            saveComments(currentProductId);
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
        function generateComments(productId) {
            if (!productId) {
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
                    product_id: productId,
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
        function saveComments(productId) {
            // Collect selected comments
            var selectedComments = [];
            
            $('.cg-comment-item').each(function() {
                var $item = $(this);
                var isSelected = $item.find('.cg-comment-checkbox').prop('checked');
                
                if (isSelected) {
                    selectedComments.push({
                        name: $item.attr('data-name'),
                        rating: $item.attr('data-rating'),
                        comment: $item.attr('data-comment'),
                        selected: 'true'
                    });
                }
            });
            
            if (selectedComments.length === 0) {
                showError('Lütfen kaydedilecek en az bir yorum seçin.');
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
                    product_id: productId,
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
                        
                        // Ürün satırındaki yorum sayısını güncelle
                        var $productRow = $('.cg-products-table tr[data-product-id="' + productId + '"]');
                        var existingComments = $productRow.find('.cg-existing-comments');
                        
                        // Yeni yorum sayısını hesapla
                        var newCommentCount = selectedComments.length;
                        if (existingComments.length > 0) {
                            // Var olan yorumlar üzerine ekleme yapılacak, göstergeyi güncelle
                            var commentText = existingComments.find('.cg-comment-badge').text().trim();
                            var oldCount = parseInt(commentText);
                            if (!isNaN(oldCount)) {
                                newCommentCount += oldCount;
                            }
                            existingComments.find('.cg-comment-badge').html('<span class="dashicons dashicons-admin-comments"></span> ' + newCommentCount + ' yorum');
                        } else {
                            // Yeni yorum göstergesi ekle
                            $productRow.find('.cg-product-name').append(
                                '<div class="cg-existing-comments">' +
                                '<span class="cg-comment-badge">' +
                                '<span class="dashicons dashicons-admin-comments"></span> ' +
                                newCommentCount + ' yorum' +
                                '</span>' +
                                '</div>'
                            );
                        }
                        
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
                    
                    showError('Sunucuya bağlanırken bir hata oluştu.');
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
                    stars += '<span class="dashicons dashicons-star-filled" data-rating="' + i + '"></span>';
                } else {
                    stars += '<span class="dashicons dashicons-star-empty" data-rating="' + i + '"></span>';
                }
            }
            
            // Update data attributes
            $commentItem.attr('data-name', comment.name);
            $commentItem.attr('data-rating', comment.rating);
            $commentItem.attr('data-comment', comment.comment);
            
            // Update DOM content
            $commentItem.find('.cg-comment-author').text(comment.name);
            $commentItem.find('.cg-comment-rating').html(stars);
            $commentItem.find('.cg-comment-text').text(comment.comment);
            
            // Update edit panel fields
            $commentItem.find('.cg-edit-author').val(comment.name);
            $commentItem.find('.cg-edit-comment').val(comment.comment);
            
            // Update editable stars
            var editableStars = generateEditableStars(comment.rating);
            $commentItem.find('.cg-edit-rating').html(editableStars);
            
            // Close edit panel if open
            $commentItem.find('.cg-comment-edit-panel').hide();
            
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
                    stars += '<span class="dashicons dashicons-star-filled" data-rating="' + i + '"></span>';
                } else {
                    stars += '<span class="dashicons dashicons-star-empty" data-rating="' + i + '"></span>';
                }
            }
            
            var commentHtml = '<div class="cg-comment-item" data-index="' + $('.cg-comment-item').length + '" data-name="' + escapeHtml(comment.name) + '" data-rating="' + comment.rating + '" data-comment="' + escapeHtml(comment.comment) + '">' +
                            '<div class="cg-comment-select">' +
                                '<input type="checkbox" class="cg-comment-checkbox" ' + (comment.selected ? 'checked' : '') + ' id="comment-' + $('.cg-comment-item').length + '">' +
                                '<label for="comment-' + $('.cg-comment-item').length + '">Seç</label>' +
                            '</div>' +
                            '<div class="cg-comment-content">' +
                                '<div class="cg-comment-header">' +
                                    '<div class="cg-comment-header-inner">' +
                                        '<div class="cg-comment-author">' + escapeHtml(comment.name) + '</div>' +
                                        '<div class="cg-comment-rating">' + stars + '</div>' +
                                    '</div>' +
                                    '<div class="cg-comment-actions">' +
                                        '<button type="button" class="cg-regenerate-comment-btn" title="' + cg_data.regenerate_comment_text + '">' +
                                            '<span class="dashicons dashicons-update"></span>' +
                                        '</button>' +
                                        '<button type="button" class="cg-edit-comment-btn" title="' + (cg_data.edit_comment_text || 'Edit this comment') + '">' +
                                            '<span class="dashicons dashicons-edit"></span>' +
                                        '</button>' +
                                    '</div>' +
                                '</div>' +
                                '<div class="cg-comment-body">' +
                                    '<div class="cg-comment-text">' + escapeHtml(comment.comment) + '</div>' +
                                '</div>' +
                            '</div>' +
                            '<div class="cg-comment-edit-panel" style="display:none;">' +
                                '<div class="cg-comment-edit-header">' +
                                    '<strong>Yorumu Düzenle</strong>' +
                                '</div>' +
                                '<div class="cg-comment-edit-body">' +
                                    '<div class="cg-edit-field">' +
                                        '<label for="edit-name-' + $('.cg-comment-item').length + '">İsim:</label>' +
                                        '<input type="text" id="edit-name-' + $('.cg-comment-item').length + '" class="cg-edit-author" value="' + escapeHtml(comment.name) + '">' +
                                    '</div>' +
                                    '<div class="cg-edit-field">' +
                                        '<label for="edit-rating-' + $('.cg-comment-item').length + '">Puanlama:</label>' +
                                        '<div class="cg-edit-rating" id="edit-rating-' + $('.cg-comment-item').length + '">' +
                                            generateEditableStars(comment.rating) +
                                        '</div>' +
                                    '</div>' +
                                    '<div class="cg-edit-field">' +
                                        '<label for="edit-comment-' + $('.cg-comment-item').length + '">Yorum:</label>' +
                                        '<textarea id="edit-comment-' + $('.cg-comment-item').length + '" class="cg-edit-comment">' + escapeHtml(comment.comment) + '</textarea>' +
                                    '</div>' +
                                    '<div class="cg-edit-actions">' +
                                        '<button type="button" class="button button-primary cg-save-edit-btn">Değişiklikleri Kaydet</button>' +
                                        '<button type="button" class="button cg-cancel-edit-btn">İptal</button>' +
                                    '</div>' +
                                '</div>' +
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
        
        // Helper function to generate editable star rating
        function generateEditableStars(rating) {
            var stars = '';
            for (var i = 1; i <= 5; i++) {
                var starClass = i <= rating ? 'dashicons-star-filled' : 'dashicons-star-empty';
                stars += '<span class="dashicons ' + starClass + ' cg-editable-star" data-rating="' + i + '"></span>';
            }
            return stars;
        }
        
        // Handle edit comment button click
        $(document).on('click', '.cg-edit-comment-btn', function() {
            var $commentItem = $(this).closest('.cg-comment-item');
            
            // Toggle edit panel
            $commentItem.find('.cg-comment-edit-panel').slideToggle(200);
        });
        
        // Handle cancel edit button click
        $(document).on('click', '.cg-cancel-edit-btn', function() {
            var $commentItem = $(this).closest('.cg-comment-item');
            
            // Hide edit panel
            $commentItem.find('.cg-comment-edit-panel').slideUp(200);
        });
        
        // Handle editable stars click
        $(document).on('click', '.cg-editable-star', function() {
            var $star = $(this);
            var newRating = parseInt($star.attr('data-rating'));
            var $ratingContainer = $star.closest('.cg-edit-rating');
            
            // Update stars
            $ratingContainer.find('.cg-editable-star').each(function() {
                var currentRating = parseInt($(this).attr('data-rating'));
                if (currentRating <= newRating) {
                    $(this).removeClass('dashicons-star-empty').addClass('dashicons-star-filled');
                } else {
                    $(this).removeClass('dashicons-star-filled').addClass('dashicons-star-empty');
                }
            });
        });
        
        // Handle save edit button click
        $(document).on('click', '.cg-save-edit-btn', function() {
            var $commentItem = $(this).closest('.cg-comment-item');
            
            // Get edited values
            var newName = $commentItem.find('.cg-edit-author').val();
            var newComment = $commentItem.find('.cg-edit-comment').val();
            var newRating = $commentItem.find('.cg-edit-rating .dashicons-star-filled').length;
            
            // Validate
            if (newName.trim() === '' || newComment.trim() === '') {
                alert('İsim ve yorum alanları boş olamaz!');
                return;
            }
            
            // Update data attributes
            $commentItem.attr('data-name', newName);
            $commentItem.attr('data-comment', newComment);
            $commentItem.attr('data-rating', newRating);
            
            // Update display fields
            $commentItem.find('.cg-comment-author').text(newName);
            $commentItem.find('.cg-comment-text').text(newComment);
            
            // Update stars in the main view
            var starsHtml = '';
            for (var i = 1; i <= 5; i++) {
                if (i <= newRating) {
                    starsHtml += '<span class="dashicons dashicons-star-filled" data-rating="' + i + '"></span>';
                } else {
                    starsHtml += '<span class="dashicons dashicons-star-empty" data-rating="' + i + '"></span>';
                }
            }
            $commentItem.find('.cg-comment-rating').html(starsHtml);
            
            // Hide edit panel
            $commentItem.find('.cg-comment-edit-panel').slideUp(200);
            
            // Flash effect to highlight the change
            $commentItem.css('background-color', '#f0f7ff');
            setTimeout(function() {
                $commentItem.css('transition', 'background-color 1s ease');
                $commentItem.css('background-color', '');
            }, 50);
        });
    });
    
})(jQuery); 