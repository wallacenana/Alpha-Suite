/* global wp, jQuery */
(function ($) {
    'use strict';

    function openMediaPicker(targetId, previewId) {
        if (!wp || !wp.media) {
            console.warn('wp.media não está disponível. Você esqueceu do wp_enqueue_media()?');
            return;
        }

        const frame = wp.media({
            title: 'Selecionar imagem',
            button: { text: 'Usar esta imagem' },
            library: { type: 'image' },
            multiple: false
        });

        frame.on('select', function () {
            const att = frame.state().get('selection').first().toJSON();
            if (!att || !att.id) return;

            // seta o ID no hidden
            $('#' + targetId).val(att.id).trigger('change');

            // preview (se tiver)
            if (previewId) {
                const url = att.url || (att.sizes && att.sizes.full && att.sizes.full.url) || '';
                if (url) {
                    $('#' + previewId).attr('src', url).show();
                }
            }
        });

        frame.open();
    }

    // Selecionar imagem
    $(document).on('click', '[data-pga-media-target]', function (e) {
        e.preventDefault();

        const targetId = $(this).data('pga-media-target');
        const previewId = $(this).data('pga-preview');

        if (!targetId) return;
        openMediaPicker(targetId, previewId);
    });

    // Remover imagem
    $(document).on('click', '[data-pga-media-clear]', function (e) {
        e.preventDefault();

        const targetId = $(this).data('pga-media-clear');
        if (!targetId) return;

        $('#' + targetId).val('').trigger('change');

        // tenta esconder preview relacionado (mesmo container)
        const $wrap = $(this).closest('td, .pga-field, .pga-row');
        $wrap.find('img[id$="_prev"], img[data-pga-preview]').first().hide().attr('src', '');
    });
})(jQuery);
