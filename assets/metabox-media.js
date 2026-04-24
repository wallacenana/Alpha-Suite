/* global wp, jQuery */
(function ($) {
  'use strict';

  function openPicker(targetId, previewEl) {
    if (!wp || !wp.media) {
      console.warn('wp.media não disponível (faltou wp_enqueue_media).');
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

      $('#' + targetId).val(att.id).trigger('change');

      if (previewEl && previewEl.length) {
        const url = att.url || (att.sizes && att.sizes.full && att.sizes.full.url) || '';
        if (url) previewEl.attr('src', url).show();
      }
    });

    frame.open();
  }

  // Selecionar
  $(document).on('click', '[data-alpha-media-target]', function (e) {
    e.preventDefault();

    const targetId = $(this).data('alpha-media-target');
    if (!targetId) return;

    const $wrap = $(this).closest('.alpha-field');
    const $preview = $wrap.find('img.alpha-thumb').first(); // pega seu preview

    openPicker(targetId, $preview);
  });

  // Remover
  $(document).on('click', '[data-alpha-media-clear]', function (e) {
    e.preventDefault();

    const targetId = $(this).data('alpha-media-clear');
    if (!targetId) return;

    $('#' + targetId).val('').trigger('change');

    const $wrap = $(this).closest('.alpha-field');
    $wrap.find('img.alpha-thumb').first().hide().attr('src', '');
  });
})(jQuery);
