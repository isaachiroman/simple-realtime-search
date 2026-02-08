/**
 * JSON Search - Admin JavaScript
 *
 * @package Simple_Realtime_Search
 */

(function($) {
    'use strict';

    var config = window.srsAdmin || {};

    /**
     * Show message.
     *
     * @param {string} message Message content.
     * @param {string} type    'success' or 'error'.
     */
    function showMessage(message, type) {
        var $msg = $('#srs-message');
        $msg.removeClass('srs-message--success srs-message--error')
            .addClass('srs-message--' + type)
            .text(message)
            .show();
    }

    /**
     * Hide message.
     */
    function hideMessage() {
        $('#srs-message').hide();
    }

    /**
     * Show progress.
     *
     * @param {string} text Progress text.
     */
    function showProgress(text) {
        $('#srs-progress').show();
        $('#srs-progress .srs-progress-text').text(text);
    }

    /**
     * Hide progress.
     */
    function hideProgress() {
        $('#srs-progress').hide();
    }

    /**
     * Get selected post types.
     *
     * @return {Array} List of selected post types.
     */
    function getSelectedPostTypes() {
        var postTypes = [];
        $('input[name="srs_settings[post_types][]"]:checked').each(function() {
            postTypes.push($(this).val());
        });
        return postTypes;
    }

    /**
     * Generate JSON data (recursive).
     *
     * @param {number} page Current page.
     */
    function generateData(page) {
        page = page || 1;

        $.ajax({
            type: 'POST',
            url: config.ajaxUrl,
            data: {
                action: 'srs_generate',
                security: config.generateNonce,
                page: page
            },
            success: function(response) {
                if (response.success) {
                    if (response.data.done) {
                        hideProgress();
                        var message = config.i18n.completed.replace('%d', response.data.count);
                        showMessage(message, 'success');
                        // Reload to update status.
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    } else {
                        var progressText = config.i18n.generating + ' (' + response.data.count + ' posts)';
                        showProgress(progressText);
                        // Continue next page.
                        generateData(response.data.page);
                    }
                } else {
                    hideProgress();
                    showMessage(response.data.message || config.i18n.error, 'error');
                }
            },
            error: function() {
                hideProgress();
                showMessage(config.i18n.error, 'error');
            }
        });
    }

    /**
     * Delete JSON data.
     */
    function deleteData() {
        $.ajax({
            type: 'POST',
            url: config.ajaxUrl,
            data: {
                action: 'srs_delete',
                security: config.deleteNonce
            },
            success: function(response) {
                hideProgress();
                if (response.success) {
                    showMessage(config.i18n.deleted, 'success');
                    // Reload to update status.
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    showMessage(response.data.message || config.i18n.error, 'error');
                }
            },
            error: function() {
                hideProgress();
                showMessage(config.i18n.error, 'error');
            }
        });
    }

    // DOM Ready.
    $(function() {
        // Generate button.
        $('#srs-generate-data').on('click', function() {
            var postTypes = getSelectedPostTypes();

            if (postTypes.length === 0) {
                showMessage(config.i18n.noPostTypes, 'error');
                return;
            }

            hideMessage();
            showProgress(config.i18n.generating);
            generateData(1);
        });

        // Delete button.
        $('#srs-delete-data').on('click', function() {
            if (!confirm('Are you sure you want to delete search data?')) {
                return;
            }

            hideMessage();
            showProgress(config.i18n.deleting);
            deleteData();
        });
    });

})(jQuery);
