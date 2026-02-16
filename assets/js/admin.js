(function ($) {
    function renderPreview(message, statusClass) {
        const container = document.getElementById('ddo-ajax-preview-response');

        if (!container) {
            return;
        }

        container.textContent = message;
        container.classList.remove('is-loading', 'is-success', 'is-error');

        if (statusClass) {
            container.classList.add(statusClass);
        }
    }

    function getPreviewErrorMessage(jqXHR, fallbackMessage) {
        if (jqXHR && jqXHR.status === 0) {
            return 'Netwerkfout. Controleer je verbinding en probeer opnieuw.';
        }

        if (jqXHR && jqXHR.status === 403) {
            return 'Je sessie is verlopen (nonce-fout). Vernieuw de pagina en probeer opnieuw.';
        }

        return fallbackMessage;
    }

    function setLoadingState(button, isLoading) {
        if (!button) {
            return;
        }

        if (isLoading) {
            button.dataset.originalText = button.textContent;
            button.disabled = true;
            button.textContent = 'Bezig…';
            return;
        }

        button.disabled = false;
        button.textContent = button.dataset.originalText || button.textContent;
        delete button.dataset.originalText;
    }

    $(document).on('click', '#ddo-preview-concept', function () {
        const textarea = document.querySelector('textarea[name="ddo_concept_input"]');
        const button = this;

        if (!textarea || typeof ddoAdmin === 'undefined') {
            return;
        }

        if (!textarea.value.trim()) {
            renderPreview('Voer eerst een concept in voordat je een preview aanvraagt.', 'is-error');
            textarea.focus();
            return;
        }

        setLoadingState(button, true);
        renderPreview('Bezig met preview ophalen…', 'is-loading');

        $.post(ddoAdmin.ajaxUrl, {
            action: 'ddo_preview_concept',
            nonce: ddoAdmin.previewNonce,
            concept: textarea.value
        })
            .done(function (response) {
                if (response && response.success && response.data) {
                    renderPreview(response.data.summary || 'Preview succesvol opgehaald.', 'is-success');
                    return;
                }

                renderPreview(ddoAdmin.i18n.previewFailed, 'is-error');
            })
            .fail(function (jqXHR) {
                renderPreview(getPreviewErrorMessage(jqXHR, ddoAdmin.i18n.previewFailed), 'is-error');
            })
            .always(function () {
                setLoadingState(button, false);
            });
    });
})(jQuery);
