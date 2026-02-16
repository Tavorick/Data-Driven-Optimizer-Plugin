(function ($) {
    function renderPreview(message, isError) {
        const container = document.getElementById('ddo-ajax-preview-response');

        if (!container) {
            return;
        }

        container.textContent = message;
        container.classList.toggle('ddo-error', Boolean(isError));
    }

    $(document).on('click', '#ddo-preview-concept', function () {
        const textarea = document.querySelector('textarea[name="ddo_concept_input"]');

        if (!textarea || typeof ddoAdmin === 'undefined') {
            return;
        }

        $.post(ddoAdmin.ajaxUrl, {
            action: 'ddo_preview_concept',
            nonce: ddoAdmin.previewNonce,
            concept: textarea.value
        })
            .done(function (response) {
                if (response && response.success && response.data) {
                    renderPreview(response.data.summary || '', false);
                    return;
                }

                renderPreview(ddoAdmin.i18n.previewFailed, true);
            })
            .fail(function () {
                renderPreview(ddoAdmin.i18n.previewFailed, true);
            });
    });
})(jQuery);
