/* global PGA_CFG, Swal, wp */
const i18n = (window.wp && wp.i18n) ? wp.i18n : null;

const __ = i18n ? i18n.__ : (s) => s;
const _x = i18n ? i18n._x : (s) => s;
const _n = i18n ? i18n._n : (s) => s;
const sprintf = (window.wp && wp.i18n && wp.i18n.sprintf)
  ? wp.i18n.sprintf
  : (fmt, ...args) => String(fmt).replace(/%s/g, () => String(args.shift() ?? ''));

(function ($) {
  const REST = PGA_CFG.rest;
  const NONCE = PGA_CFG.nonce;

  const pillarId = window.PGA_PILLAR_ID || 0;
  const GROUPS_KEY = `pga_gen_groups_v1_${pillarId}`;
  let PGA_LOADING_GROUPS = false;
  let PGA_SWITCHING_TAB = false;

  // Flag global pra saber se há geração em andamento
  window.PGA_IS_GENERATING = window.PGA_IS_GENERATING || false;
  window.PGA_IS_DIRTY = false;

  if (!window.PGA_BEFOREUNLOAD_BOUND) {
    window.PGA_BEFOREUNLOAD_BOUND = true;

    window.addEventListener('beforeunload', function (e) {
      if (PGA_SWITCHING_TAB) return;
      if (!window.PGA_IS_GENERATING && !window.PGA_IS_DIRTY) return;

      const msg = window.PGA_IS_GENERATING
        ? __('O conteúdo ainda está sendo gerado. Sair da página pode interromper a criação. Deseja mesmo sair?', 'alpha-suite')
        : __('Existem alterações não salvas. Deseja mesmo sair?', 'alpha-suite');

      e.preventDefault();
      e.returnValue = msg;
      return msg;
    });
  }

  function pgaSwitchTab(nextTabId) {
    const curTabId = localStorage.getItem(KEY_ACTIVE_TAB) || '';
    if (!nextTabId || nextTabId === curTabId) return;

    // salva o atual antes de trocar (autosave já salva, mas isso garante)
    try { if (curTabId) saveCurrentTabGroups(curTabId); } catch (e) { }

    localStorage.setItem(KEY_ACTIVE_TAB, nextTabId);

    loadTabGroups(nextTabId);
    renderTabsUI(loadTabs(), nextTabId);

    window.PGA_IS_DIRTY = false;
  }

  $(document).on('click', '#pga_tabs button[data-tab]', function () {
    pgaSwitchTab($(this).attr('data-tab'));
  });

  function pgaToast(icon, title, timer = 1800) {
    if (!window.Swal) return;
    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: icon || 'success',
      title: title || '',
      timer,
      timerProgressBar: true,
      showConfirmButton: false,
      customClass: { popup: 'pga-toast-offset' }
    });
  }

  // ===== Dropup "Concluídas" =====
  $(document).on('click', '#pga_done_toggle', function () {
    const $wrap = $('.pga-done-dropup');
    if (!$wrap.length) return;

    const isOpen = !$wrap.hasClass('is-open');
    $wrap.toggleClass('is-open', isOpen);

    $(this).attr('aria-expanded', isOpen ? 'true' : 'false');
    $('#pga_done_panel').attr('aria-hidden', isOpen ? 'false' : 'true');
    renderDone(pgaLoadDone());
  });

  // Fecha o dropup ao clicar fora
  $(document).on('click', function (e) {
    const $wrap = $('.pga-done-dropup');
    if (!$wrap.length || !$wrap.hasClass('is-open')) return;

    if ($wrap.is(e.target) || $wrap.has(e.target).length) return;

    $wrap.removeClass('is-open');
    $('#pga_done_toggle').attr('aria-expanded', 'false');
    $('#pga_done_panel').attr('aria-hidden', 'true');
  });

  // Toggle de campos de link interno por grupo
  $(document).on('change', '.pga_link_mode', function () {
    const $box = $(this).closest('.pga-gen-box');
    const mode = $(this).val() || 'none';

    const showExtras = mode !== 'none';
    const isManual = mode === 'manual';

    $box.find('.pga_link_extra').toggle(showExtras);
    $box.find('.pga_link_manual_wrapper').toggle(isManual);
  });

  function initLinkManualSelect2(context) {
    const $ctx = context ? $(context) : $(document);
    if (!$.fn.select2) return;

    $ctx.find('select.pga-link-manual-select').each(function () {
      const $sel = $(this);
      const $box = $sel.closest('.pga-gen-box');

      // remove APENAS o container ligado a este select (geralmente vem logo depois)
      const $next = $sel.next();
      if ($next.length && $next.hasClass('select2')) {
        $next.remove();
      }

      // se já tinha select2 ativo, destrói só ele
      if ($sel.hasClass('select2-hidden-accessible') || $sel.data('select2')) {
        try { $sel.select2('destroy'); } catch (e) { }
      }

      // limpa atributos que o select2 deixa (só no select atual)
      $sel
        .removeClass('select2-hidden-accessible')
        .removeAttr('data-select2-id')
        .find('option').removeAttr('data-select2-id');

      // init preso no box certo (evita clique cair no primeiro)
      $sel.select2({
        width: '100%',
        placeholder: __('Selecione posts para link interno', 'alpha-suite'),
        allowClear: true,
        dropdownParent: $box.length ? $box : $(document.body)
      });

      $sel.data('select2-initialized', true);
    });
  }


  // === Collapse toggle (qualquer grupo) ===
  $(document).on('click', '.pga-collapse-toggle', function () {
    const $box = $(this).closest('.pga-gen-box');
    $box.toggleClass('pga-collapse--open');
  });

  // ---------- Remover GRUPO (colapse inteiro) ----------
  $(document).off('click.pgaRemoveBox').on('click.pgaRemoveBox', '.pga_remove_box', async function () {
    const $box = $(this).closest('.pga-gen-box');
    const $container = $('#pga_gen_container');
    const totalBoxes = $container.find('.pga-gen-box').length;

    if (!$box.length) return;

    // se for o único grupo, em vez de remover, só limpamos os campos
    if (totalBoxes <= 1) {
      const ok = await pgaConfirm({
        icon: 'warning',
        title: __('Limpar este grupo?', 'alpha-suite'),
        text: __('Este é o único grupo. Em vez de remover, vamos apenas limpar os campos.', 'alpha-suite'),
        confirmButtonText: __('Limpar', 'alpha-suite'),
        cancelButtonText: __('Cancelar', 'alpha-suite')
      });

      if (!ok) return;

      $box.find('.pga_keywords').val('');
      $box.find('.pga_total').val('6');
      $box.find('.pga_per_day').val('3');
      $box.find('.pga_template_key').val('article');
      $box.find('.pga_locale').val('pt_BR');
      $box.find('.pga_length').val('medium');
      $box.find('.pga_category').val('0');

      pgaUpdateBoxTitle($box);
      return;
    }

    // confirmação para remover o grupo
    const ok = await pgaConfirm({
      icon: 'warning',
      title: __('Remover grupo?', 'alpha-suite'),
      text: __('Este grupo de geração será removido (as keywords dentro dele não serão salvas).', 'alpha-suite'),
      confirmButtonText: __('Remover', 'alpha-suite'),
      cancelButtonText: __('Cancelar', 'alpha-suite')
    });

    if (!ok) return;

    // remove do DOM
    $box.remove();

    // reindexa data-gen e atualiza títulos
    const $boxes = $container.find('.pga-gen-box');
    $boxes.each(function (idx) {
      const $b = $(this);
      $b.attr('data-gen', idx + 1);
      pgaUpdateBoxTitle($b);
    });

    // garante que algum box fique ativo com os IDs "oficiais"
    const $first = $boxes.first();
    if ($first.length) {
      pgaActivateBox($first);
    }

  });


  // === Atualiza título de UM box com base nos campos ===
  function pgaUpdateBoxTitle($box) {
    // Modelo
    const model = ($box.find('.pga_template_key option:selected').text() || '').trim() || __('Gerador', 'alpha-suite');

    // Categoria (mais robusto pra wp_dropdown_categories)
    let cat = __('Sem categoria', 'alpha-suite');
    const $catSel = $box.find('.pga_category').first();
    if ($catSel.length) {
      const el = $catSel[0];
      if (el.options && el.selectedIndex >= 0) {
        const txt = (el.options[el.selectedIndex].text || '').trim();
        if (txt) cat = txt;
      }
    }

    // Locale
    const loc = $box.find('.pga_locale').val() || 'pt_BR';

    // Quantidade total / por dia
    const total = $box.find('.pga_total').val() || '0';
    const perDay = $box.find('.pga_per_day').val() || '0';

    // Extensão
    const lengthLabel = ($box.find('.pga_length option:selected').text() || '').trim() || __('Extensão', 'alpha-suite');

    // 🔹 título curto (visível)
    const visibleTitle = `<span class="pga-model">${model}</span> <span class="pga-category-colapse">${cat}</span>`;

    // 🔹 título completo (tooltip)
    const postsLabel = sprintf(_n('%s post', '%s posts', Number(total), 'alpha-suite'), total);

    const fullTitle = sprintf(
      __('%1$s – %2$s – %3$s – %4$s – %5$s/dia – %6$s', 'alpha-suite'),
      model,
      cat,
      loc,
      postsLabel,
      perDay,
      lengthLabel
    );

    $box
      .find('.pga-gen-title')
      .html(visibleTitle)
      .attr('title', fullTitle); // tooltip nativo do browser
  }


  // dispara update quando qualquer campo relevante muda
  $(document).on(
    'change keyup',
    '.pga_template_key, .pga_category, .pga_locale, .pga_total, .pga_per_day, .pga_length',
    function () {
      const $box = $(this).closest('.pga-gen-box');
      pgaSyncLinkOptionsForBox($box);
      pgaUpdateBoxTitle($box);
    }
  );

  // serializa 1 box -> objeto JS
  function pgaSerializeBox($box) {
    // procura tanto pelo seletor com underscore quanto pelo com hífen (Select2)
    const $manualSel = $box.find('.pga_link_manual, .pga-link-manual-select');
    let manualVals = $manualSel.val() || []; // select[multiple] normal

    // se estiver usando Select2 e val() não tiver retornado, tenta extrair pelos dados do select2
    try {
      if ((!manualVals || manualVals.length === 0) && $manualSel.length && $manualSel.data('select2-initialized') && $.fn.select2) {
        const data = $manualSel.select2('data') || [];
        manualVals = data.map(d => (d && (d.id || d.text)) ? (d.id || d.text) : d);
      }
    } catch (e) { /* silencioso */ }

    return {
      keywords: $box.find('.pga_keywords').val() || '',
      locale: $box.find('.pga_locale').val() || 'pt_BR',
      template_key: $box.find('.pga_template_key').val() || 'article',
      category: $box.find('.pga_category').val() || '0',
      total: parseInt($box.find('.pga_total').val() || '0', 10) || 0,
      per_day: parseInt($box.find('.pga_per_day').val() || '0', 10) || 0,
      faq_qty: parseInt($box.find('.pga_faq_qty').val() || '0', 10) || 0,
      first_delay: $box.find('.pga_first_delay_hours').val() || '',
      length: $box.find('.pga_length').val() || 'medium',
      link_max: parseInt($box.find('.pga_link_max').val() || '2', 10) || 2,
      make_faq: $box.find('.pga_make_faq').is(':checked') ? 1 : 0,


      // 🔹 novo: salvar config de links internos por grupo
      internal_links: {
        mode: ($box.find('.pga_link_mode').val() || 'none'),
        max: parseInt($box.find('.pga_link_max').val() || '0', 10) || 0,
        manual_ids: Array.isArray(manualVals) ? manualVals.join(',') : String(manualVals || '')
      }
    };
  }

  // aplica objeto de config em 1 box
  function pgaApplyBoxConfig($box, cfg) {
    if (!cfg) return;

    $box.find('.pga_keywords').val(cfg.keywords || '');
    $box.find('.pga_locale').val(cfg.locale || 'pt_BR');
    $box.find('.pga_template_key').val(cfg.template_key || 'article');
    $box.find('.pga_category').val(cfg.category || '0');
    $box.find('.pga_make_faq').prop('checked', !!cfg.make_faq);
    $box.find('.pga_total').val(cfg.total || 0);
    $box.find('.pga_per_day').val(cfg.per_day || 0);
    $box.find('.pga_faq_qty').val(cfg.faq_qty || 0);
    if (cfg.first_delay) {
      $box.find('.pga_first_delay_hours').val(cfg.first_delay);
    }
    $box.find('.pga_length').val(cfg.length || 'medium');

    // 🔹 links internos por grupo
    const il = cfg.internal_links || {};
    const mode = il.mode || 'none';
    const max = il.max || 0;
    const manualIds = (il.manual_ids || '')
      .split(',')
      .map(s => s.trim())
      .filter(Boolean);

    // seta modo/max
    $box.find('.pga_link_mode').val(mode);
    $box.find('.pga_link_max').val(max ? String(max) : '');

    $box.find('.pga_make_faq')
      .prop('checked', !!cfg.make_faq)
      .trigger('change');

    // mostra/esconde extras
    const showExtras = mode !== 'none';
    const isManual = mode === 'manual';
    $box.find('.pga_link_extra').toggle(showExtras);
    $box.find('.pga_link_manual_wrapper').toggle(isManual);

    // aplica valores no select manual (tenta tanto underscore quanto hífen)
    const $sel = $box.find('.pga_link_manual, .pga-link-manual-select');
    if ($sel.length) {
      $sel.val(manualIds).trigger('change');
      // se for select2, força atualização
      try { if ($sel.data('select2-initialized') && $.fn.select2) $sel.trigger('change.select2'); } catch (e) { /* silencioso */ }
    }

    pgaSyncLinkOptionsForBox($box);
    pgaUpdateBoxTitle($box);
  }

  function pgaGetTabId() {
    try {
      const u = new URL(window.location.href);
      return (u.searchParams.get('tab') || '').trim();
    } catch (e) {
      return '';
    }
  }
  function pgaSaveBoxesToLocal() {
    if (PGA_LOADING_GROUPS) return;

    const tabId = localStorage.getItem(KEY_ACTIVE_TAB) || '';
    if (!tabId) return;

    const all = [];
    $('#pga_gen_container .pga-gen-box').each(function () {
      all.push(pgaSerializeBox($(this)));
    });
    if (!all.length) return;

    saveGroupsForTab(tabId, all);
  }

  // === Botão "Adicionar grupo" ===
  $(document).on('click', '#pga_add_box', function () {
    const $container = $('#pga_gen_container');
    const $first = $container.find('.pga-gen-box').first();
    if (!$first.length) return;

    const nextId = $container.find('.pga-gen-box').length + 1;

    // ✅ NÃO copie eventos/dados (Select2 odeia clone(true,true))
    const $clone = $first.clone(false, false);

    $clone.attr('data-gen', nextId);
    $clone.removeClass('pga-collapse--open');
    $clone.find('.pga-gen-title').text(sprintf(__('Geração %d', 'alpha-suite'), nextId));

    // ✅ REMOVE SUJEIRA DO SELECT2 do HTML clonado
    $clone.find('.select2-container').remove();

    $clone.find('select').each(function () {
      const $s = $(this);

      // se veio "marcado" como select2
      $s.removeClass('select2-hidden-accessible');

      // remove resíduos que fazem o clique ir pro primeiro
      $s.removeAttr('data-select2-id')
        .removeAttr('tabindex')
        .removeAttr('aria-hidden')
        .removeAttr('aria-labelledby');

      // também limpa data do jQuery (caso tenha)
      $s.removeData('select2');
    });

    // options também carregam data-select2-id às vezes
    $clone.find('option').removeAttr('data-select2-id');

    // ✅ remove IDs duplicados (se seu HTML ainda tiver id em campos do box)
    $clone.find('[id]').removeAttr('id');

    // limpa valores
    $clone.find('.pga_keywords').val('');
    $clone.find('.pga_total').val('6');
    $clone.find('.pga_per_day').val('3');

    // defaults
    $clone.find('.pga_template_key').val('article');
    $clone.find('.pga_locale').val('pt_BR');
    $clone.find('.pga_length').val('medium');
    $clone.find('.pga_category').val('0');

    // links internos
    $clone.find('.pga_link_mode').val('none');
    $clone.find('.pga_link_max').val('3');
    $clone.find('.pga_link_manual').val(null); // múltiplo
    $clone.find('.pga_link_extra').hide();
    $clone.find('.pga_link_manual_wrapper').hide();

    $container.append($clone);

    // atualiza UI/visibilidade
    pgaSyncLinkOptionsForBox($clone);

    // ✅ INICIA select2 SÓ no clone (não re-inicializa tudo)
    pgaInitSelect2InBox($clone);
    initLinkManualSelect2($clone);

    pgaUpdateBoxTitle($clone);

    window.PGA_IS_DIRTY = true;
  });


  // Atualiza o título do primeiro grupo ao carregar
  $(function () {
    $('#pga_gen_container .pga-gen-box').each(function () {
      pgaUpdateBoxTitle($(this));
    });
  });

  // Marca um box como "ativo" movendo os IDs para ele
  function pgaActivateBox($box) {
    const map = [
      ['.pga_keywords', 'pga_keywords'],
      ['.pga_locale', 'pga_locale'],
      ['.pga_template_key', 'pga_template_key'],
      ['.pga_category', 'pga_category'],
      ['.pga_total', 'pga_total'],
      ['.pga_per_day', 'pga_per_day'],
      ['.pga_first_delay_hours', 'pga_first_delay_hours'],
      ['.pga_length', 'pga_length'],
    ];

    map.forEach(([cls, id]) => {
      $(`[id="${id}"]`).removeAttr('id');          // tira ID de onde estiver
      const $el = $box.find(cls).first();
      if ($el.length) $el.attr('id', id);          // põe ID nesse grupo
    });
  }

  // ------------------ utils ------------------
  async function fetchJSON(url, options = {}) {
    // opções: method, headers, body, silent
    const { silent, method = 'GET', headers = {}, body, ...rest } = options || {};

    const res = await fetch(url, { method, headers, body, ...rest });

    const text = await res.text();
    let data = null;

    try {
      data = text ? JSON.parse(text) : null;
    } catch (e) {
      if (!silent) {
        if (window.Swal) {
          const safe = String(text || '').replace(/[<>&]/g, s => ({ '<': '&lt;', '>': '&gt;', '&': '&amp;' }[s]));
          await Swal.fire({
            icon: 'error',
            title: __('Resposta não-JSON', 'alpha-suite'),
            html: sprintf(
              __('<p><b>HTTP</b>: %d</p><pre style="white-space:pre-wrap;max-height:320px;overflow:auto;border:1px solid #eee;padding:8px;border-radius:6px;">%s</pre>', 'alpha-suite'),
              res.status,
              safe
            )
          });
        } else {
          alert(sprintf(__('Erro: resposta não-JSON (%d)', 'alpha-suite'), res.status));
        }
      }

      const err = new Error(sprintf(__('Non-JSON %d', 'alpha-suite'), res.status));
      err.status = res.status;
      err.rawBody = text;
      throw err;
    }

    if (!res.ok) {
      const msg = (data && (data.message || data.code)) || sprintf(__('HTTP %d', 'alpha-suite'), res.status);
      if (!silent) {
        if (window.Swal) {
          await Swal.fire({ icon: 'error', title: __('Falha na chamada', 'alpha-suite'), text: String(msg) });
        } else {
          alert(sprintf(__('Erro: %s', 'alpha-suite'), String(msg)));
        }
      }
      return
    }

    return data;
  }

  function pgaMaxLinksForLength(len) {
    switch (len) {
      case 'short': return 5;
      case 'medium': return 8;
      case 'long': return 10;
      case 'extra-long': return 15;
      default: return 3;
    }
  }

  function pgaSyncLinkOptionsForBox($box) {
    const len = $box.find('.pga_length').val() || 'medium';
    const maxAllowed = pgaMaxLinksForLength(len);

    const $sel = $box.find('.pga_link_max');
    if (!$sel.length) return;

    // desabilita tudo acima do limite
    $sel.find('option').each(function () {
      const val = parseInt($(this).val(), 10);
      const disabled = val > maxAllowed;
      $(this).prop('disabled', disabled).toggle(!disabled);
    });

    // se o valor atual está acima, força para o máximo permitido
    let cur = parseInt($sel.val() || '0', 10);
    if (!cur || cur > maxAllowed) {
      $sel.val(String(maxAllowed));
    }
  }

  function textareaToArray(text) { return [...new Set((text || '').split(/\r?\n/).map(t => t.trim()).filter(Boolean))]; }
  function onSettingsPage() { return !!document.querySelector('form[action="options.php"]'); }
  function getQueryParam(name) { const u = new URL(window.location.href); return u.searchParams.get(name); }

  // ============================================================
  // =============== BLOCO: CONFIGURAÇÕES (settings) ============
  // ============================================================
  async function bootSettings() {
    // feedback rápido quando salvar no WP
    if (typeof getQueryParam === 'function' && getQueryParam('settings-updated') === '1') {
      if (window.Swal) {
        await Swal.fire({
          icon: __('success', 'alpha-suite'),
          title: __('Configurações salvas', 'alpha-suite'),
          timer: 1600,
          showConfirmButton: false
        });
      }
    }

    // helpers SweetAlert
    async function safeCloseSwal() {
      try { if (window.Swal && Swal.isVisible()) Swal.close(); } catch (e) { }
    }

    // se já existe o botão, não recria
    const keyEl = document.getElementById('pga_openai_key');
    if (!keyEl) return; // sem campo de chave, sem teste

    let testBtn = document.getElementById('pga_test_openai');
    if (!testBtn) {
      testBtn = document.createElement('button');
      testBtn.type = 'button';
      testBtn.id = 'pga_test_openai';
      testBtn.className = 'button';
      testBtn.textContent = __('Testar OpenAI', 'alpha-suite');
      testBtn.style.marginLeft = '8px';
      keyEl.parentNode.insertBefore(testBtn, keyEl.nextSibling);
    }

    testBtn.addEventListener('click', async () => {
      const keyInput = document.getElementById('pga_openai_key');
      const modelInput = document.getElementById('pga_openai_model');
      const tempInput = document.getElementById('pga_openai_temp');
      const tokInput = document.getElementById('pga_openai_maxtok');

      const key = keyInput ? String(keyInput.value || '').trim() : '';
      const model = modelInput ? String(modelInput.value || '').trim() : 'gpt-4o-mini';
      const temp = tempInput ? parseFloat(tempInput.value || '0.6') : 0.6;
      const maxTok = tokInput ? parseInt(tokInput.value || '512', 10) : 512;

      if (!key) {
        await Swal.fire({
          icon: 'warning',
          title: __('Informe a chave', 'alpha-suite'),
          text: __('Digite a chave OpenAI antes de testar.', 'alpha-suite'),
          timer: 2200,
          showConfirmButton: false
        });
        return;
      }

      try {
        testBtn.disabled = true;

        await safeCloseSwal();
        Swal.fire({
          icon: 'info',
          title: __('Testando OpenAI…', 'alpha-suite'),
          allowOutsideClick: false,
          allowEscapeKey: false,
          showConfirmButton: false,
          didOpen: () => Swal.showLoading()
        });

        const payload = {
          key: key,
          model: model || 'gpt-4o-mini',
          temperature: isNaN(temp) ? 0.6 : temp,
          max_tokens: Number.isFinite(maxTok) ? maxTok : 512
        };

        const res = await fetch(`${REST}/selftest`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': NONCE
          },
          body: JSON.stringify(payload)
        });

        let j = {};
        try { j = await res.json(); } catch (e) { }

        await safeCloseSwal();

        if (!res.ok) {
          const msg = j && (j.message || j.error || j.code) ? (j.message || j.error || j.code) : `HTTP ${res.status}`;
          await Swal.fire({ icon: 'error', title: __('Erro ao testar', 'alpha-suite'), text: msg });
          return;
        }

        const safeSample = String(j.sample || '').replace(/[<>&]/g, s => ({ '<': '&lt;', '>': '&gt;', '&': '&amp;' }[s]));

        await Swal.fire({
          icon: j.ok ? 'success' : 'warning',
          title: j.ok ? __('Conectado!', 'alpha-suite') : __('Conexão incompleta', 'alpha-suite'),
          html: sprintf(
            __(
              '<div style="text-align:left">' +
              '<div><b>%s</b> %s</div>' +
              '<div><b>%s</b> %s ms</div>' +
              '<div><b>%s</b> <code>%s</code></div>' +
              '</div>',
              'alpha-suite'
            ),
            __('Modelo:', 'alpha-suite'),
            (j.model || payload.model || ''),
            __('Latência:', 'alpha-suite'),
            (j.latencyMs ?? '?'),
            __('Retorno:', 'alpha-suite'),
            safeSample
          ),
          timer: 2600,
          timerProgressBar: true,
          showConfirmButton: false
        });

      } catch (err) {
        await safeCloseSwal();
        await Swal.fire({
          icon: 'error',
          title: __('Falha no teste', 'alpha-suite'),
          text: err && err.message ? err.message : String(err || __('Erro desconhecido', 'alpha-suite'))
        });
      } finally {
        testBtn.disabled = false;
      }
    });
  }

  function pgaGlobalIsOn() {
    return !!document.getElementById('pga_plan_global_toggle')?.checked;
  }

  function pgaGlobalToggleUI() {
    const on = pgaGlobalIsOn();
    $('#pga_plan_custom_top').css('display', on ? 'flex' : 'none');

    // quando GLOBAL ligado: esconde total/per_day/inicio dentro dos geradores
    $('#pga_gen_container .pga-field-total').css('display', on ? 'none' : '');
    $('#pga_gen_container .pga-field-program').css('display', on ? 'none' : '');
    $('#pga_gen_container #pga_generator_btn').css('display', on ? 'none' : '');

    // mostra checkbox "incluir na geração" por gerador (se você quiser usar)
    $('#pga_gen_container .pga_custom_wrap').css('display', on ? '' : 'none');
  }

  $(document).off('change.pgaGlobal').on('change.pgaGlobal', '#pga_plan_global_toggle', function () {
    pgaGlobalToggleUI();
  });

  // init
  pgaGlobalToggleUI();

  // =========================
  // STORAGE OFICIAL DE GRUPOS (ÚNICO) + MIGRAÇÃO
  // =========================

  // legado antigo (você já tem acima, mas deixo aqui pra ficar claro)
  const LEGACY_PILLAR_GROUPS_KEY = GROUPS_KEY; // pga_gen_groups_v1_${pillarId}

  // legado "por tab" antigo (aquele que usava pgaGroupsStorageKey)
  function legacyTabGroupsKey_fromOldSystem() {
    const tabId = (getTabIdFromUrl() || localStorage.getItem(KEY_ACTIVE_TAB) || 'default');
    return `${LEGACY_PILLAR_GROUPS_KEY}_${tabId}`;
  }

  function safeGetLS(key) {
    try { return localStorage.getItem(key) || ''; } catch (e) { return ''; }
  }
  function safeSetLS(key, val) {
    try { localStorage.setItem(key, val); return true; } catch (e) { return false; }
  }

  function loadGroupsForTab(tabId) {
    const raw = safeGetLS(tabGroupsKey(tabId));
    if (!raw) return [];
    try {
      const v = JSON.parse(raw);
      return Array.isArray(v) ? v : [];
    } catch (e) {
      return [];
    }
  }

  function saveGroupsForTab(tabId, groups) {
    if (!tabId) return false;
    if (!Array.isArray(groups) || !groups.length) return false;
    return safeSetLS(tabGroupsKey(tabId), JSON.stringify(groups));
  }

  // pega grupos do DOM (fonte única do autosave)
  function serializeAllGroupsFromDom() {
    const out = [];
    $('#pga_gen_container .pga-gen-box').each(function () {
      out.push(pgaSerializeBox($(this)));
    });
    return out;
  }

  // aplica grupos no DOM
  function resetBoxesToOne() {
    const $container = $('#pga_gen_container');
    const $boxes = $container.find('.pga-gen-box');
    if ($boxes.length <= 1) return;
    $boxes.slice(1).remove();
  }

  function ensureBoxesCountExact(targetCount) {
    resetBoxesToOne();

    const $container = $('#pga_gen_container');
    const $first = $container.find('.pga-gen-box').first();
    if (!$first.length) return;

    // cria até bater
    while ($container.find('.pga-gen-box').length < targetCount) {
      $('#pga_add_box').trigger('click');
    }
  }

  function applyGroupsToDom(groups) {
    PGA_LOADING_GROUPS = true;
    try {
      if (!Array.isArray(groups) || !groups.length) {
        ensureBoxesCountExact(1);
        const $first = $('#pga_gen_container .pga-gen-box').first();
        if ($first.length) {
          pgaActivateBox($first);
          pgaUpdateBoxTitle($first);
        }
        return;
      }

      ensureBoxesCountExact(groups.length);

      const $boxes = $('#pga_gen_container .pga-gen-box');
      groups.forEach((g, i) => {
        const $box = $boxes.eq(i);
        if ($box.length) pgaApplyBoxConfig($box, g);
      });

      const $first = $('#pga_gen_container .pga-gen-box').first();
      if ($first.length) pgaActivateBox($first);

    } finally {
      PGA_LOADING_GROUPS = false;
    }
  }

  // migração 1 vez: só roda se a tab NOVA ainda não tiver grupos salvos
  function migrateLegacyGroupsToTabOnce(tabId) {
    if (!tabId) return false;

    // já tem no novo? não faz nada
    if (loadGroupsForTab(tabId).length) return false;

    // tenta 1) legado por tab antigo
    let raw = safeGetLS(legacyTabGroupsKey_fromOldSystem());
    let data = [];
    if (raw) {
      try { data = JSON.parse(raw || '[]'); } catch (e) { data = []; }
      if (!Array.isArray(data)) data = [];
    }

    // tenta 2) legado pillar antigo (se 1 falhou)
    if (!data.length) {
      raw = safeGetLS(LEGACY_PILLAR_GROUPS_KEY);
      if (raw) {
        try { data = JSON.parse(raw || '[]'); } catch (e) { data = []; }
        if (!Array.isArray(data)) data = [];
      }
    }

    if (!data.length) return false;

    // grava no novo padrão
    return saveGroupsForTab(tabId, data);
  }

  // Helpers SweetAlert2
  async function swalLoading(title = __('Processando…', 'alpha-suite')) {
    return Swal.fire({
      title,
      allowOutsideClick: false,
      allowEscapeKey: false,
      showConfirmButton: false,
      didOpen: () => Swal.showLoading()
    });
  }
  async function swalSuccess(html, title = __('Tudo certo!', 'alpha-suite')) {
    return Swal.fire({ icon: 'success', title, html, confirmButtonText: __('Ok', 'alpha-suite') });
  }
  async function swalError(html, title = __('Ops…', 'alpha-suite')) {
    return Swal.fire({ icon: 'error', title, html, confirmButtonText: __('Entendi', 'alpha-suite') });
  }
  async function swalWarn(html, title = __('Atenção', 'alpha-suite')) {
    return Swal.fire({ icon: 'warning', title, html, confirmButtonText: __('Ok', 'alpha-suite') });
  }

  // Helper unificado para confirmações (Swal ou fallback confirm)
  async function pgaConfirm(opts = {}) {
    const {
      title = '',
      text = '',
      icon = 'warning',
      confirmButtonText = __('Ok', 'alpha-suite'),
      cancelButtonText = __('Cancelar', 'alpha-suite')
    } = opts || {};

    if (window.Swal) {
      const res = await Swal.fire({
        icon,
        title,
        html: text,
        showCancelButton: true,
        confirmButtonText,
        cancelButtonText
      });
      return !!res.isConfirmed;
    }

    // fallback simples
    return confirm(String((title || text) || __('Confirma?', 'alpha-suite')));
  }

  // Atualiza UI de status/mensagem da licença
  function updateLicenseUI(lic) {
    if (!lic) return;
    const st = (lic.status || 'INACTIVE').toString();
    const msg = (lic.message || '').toString();
    $('#pga_license_status').text(st);
    $('#pga_license_msg').text(msg);
  }

  $(document).off('change.pgaFaq').on('change.pgaFaq', '.pga_make_faq', function () {
    const $box = $(this).closest('.pga-collapse-body, .pga-card, .pga-gen-box').first();
    const on = $(this).is(':checked');
    $box.find('.pga-faq-qty-wrap').toggle(on);
  });

  // Botão: ATIVAR
  $(document).on('click', '#pga_license_activate', async function () {
    const $btn = $(this);
    const $email = $('input[name="pga_license[email]"]');
    const $pid = $('input[name="pga_license[purchase_id]"]');
    const email = ($email.val() || '').trim();
    const pid = ($pid.val() || '').trim();

    if (!email || !pid) {
      await swalWarn(__('Preencha <b>e-mail</b> e <b>ID da compra</b> antes de ativar.', 'alpha-suite'));
      return;
    }

    try {
      $btn.prop('disabled', true);
      await swalLoading(__('Ativando licença…', 'alpha-suite'));

      const data = await fetchJSON(`${REST}/license/activate`, {
        method: 'POST',
        body: JSON.stringify({ email, purchase_id: pid })
      });

      Swal.close();

      updateLicenseUI(data.license);

      if (data.ok) {
        const html = `
          <div style="text-align:left">
            <div><b>Status:</b> ${data.license?.status || '-'}</div>
            <div><b>E-mail:</b> ${data.license?.email || '-'}</div>
            <div><b>${__('Compra:', 'alpha-suite')}</b> ${data.license?.purchase_id || '-'}</div>
            ${data.license?.message ? `<div style="margin-top:6px">${data.license.message}</div>` : ''}
          </div>
        `;
        await swalSuccess(html, __('Licença ativada!', 'alpha-suite'));
      } else {
        const html = `
          <div style="text-align:left">
            <div><b>Status:</b> ${data.license?.status || 'INACTIVE'}</div>
            ${data.license?.message ? `<div style="margin-top:6px">${data.license.message}</div>` : `<div style="margin-top:6px">${__('Não foi possível ativar. Verifique os dados.', 'alpha-suite')}</div>`}
          </div>
        `;
        await swalWarn(html, __('Licença não ativa', 'alpha-suite'));
      }
    } catch (err) {
      Swal.close();
      const fallbackMsg = __('Erro desconhecido.<br><small>Tente novamente em instantes.</small>', 'alpha-suite');
      await swalError((err && err.message) ? String(err.message) : fallbackMsg);
    } finally {

      $btn.prop('disabled', false);
    }
  });

  // (Opcional) Checar status ao abrir a página de Configurações
  $(document).ready(async function () {
    const $status = $('#pga_license_status');
    if (!$status.length) return;
    try {
      const data = await fetchJSON(`${REST}/license/status`, { method: 'GET' });
      updateLicenseUI(data.license);
    } catch (_) {
      /* silencioso */
    }
  });


  // DUPLICAR GRUPO (FIX)
  $(document).off('click.pgaCopyBox').on('click.pgaCopyBox', '.pga-copy-box', function (e) {
    e.preventDefault();
    e.stopPropagation();

    const $box = $(this).closest('.pga-gen-box');
    const $container = $('#pga_gen_container');
    if (!$box.length || !$container.length) return;

    // 1) captura config do box atual
    const cfg = pgaSerializeBox($box);

    // 2) clona VISUAL sem eventos/dados (✅)
    const $clone = $box.clone(false, false);

    // 3) gera novo data-gen
    const gens = $container.find('.pga-gen-box').map(function () {
      return parseInt($(this).attr('data-gen') || '0', 10) || 0;
    }).get();
    const nextGen = (gens.length ? Math.max.apply(null, gens) : 0) + 1;

    $clone.attr('data-gen', String(nextGen));

    // 4) remove IDs duplicados
    $clone.find('[id]').removeAttr('id');

    // ✅ 5) limpa Select2 clonado (container + ids internos)
    $clone.find('.select2-container').remove();
    $clone.find('select').each(function () {
      const $s = $(this);
      $s.removeClass('select2-hidden-accessible')
        .removeAttr('data-select2-id')
        .removeAttr('tabindex')
        .removeAttr('aria-hidden')
        .removeAttr('aria-labelledby')
        .removeData('select2');
    });
    $clone.find('option').removeAttr('data-select2-id');

    // 6) aplica config no clone
    pgaApplyBoxConfig($clone, cfg);

    // 7) abre o collapse do clone (opcional)
    $clone.addClass('pga-collapse--open');

    // 8) insere após o atual
    $box.after($clone);

    // ✅ 9) reinit select2 DO CLONE
    pgaInitSelect2InBox($clone);
    initLinkManualSelect2($clone);

    pgaUpdateBoxTitle($clone);

    window.PGA_IS_DIRTY = true;
  });

  function pgaNormalizeKeywordsText(txt) {
    let t = String(txt || '');

    // \\n -> \n
    if (t.includes('\\n') && !t.includes('\n')) t = t.replace(/\\n/g, '\n');

    t = t.replace(/\r\n|\r/g, '\n');

    const lines = t.split('\n').map(l => l.trim()).filter(Boolean).map(l => {
      l = l.replace(/^\s*(?:[-•*]|[\d]{1,3}[.)-])\s*/u, '');
      l = l.replace(/([A-Za-zÀ-ÿ])\\([A-Za-zÀ-ÿ])/g, '$1 $2');
      l = l.replace(/\\/g, ' ');
      l = l.replace(/\s+/g, ' ').trim();
      return l;
    }).filter(Boolean);

    // unique
    const seen = new Set();
    const out = [];
    for (const l of lines) {
      const key = l.toLocaleLowerCase();
      if (seen.has(key)) continue;
      seen.add(key);
      out.push(l);
    }
    return out.join('\n');
  }


  $(document).on('click', '.pga_generate_keywords', async function () {
    const $box = $(this).closest('.pga-gen-box');
    const $ta = $box.find('.pga_keywords');
    const cmd = ($ta.val() || '').trim();

    const ok = await pgaConfirm({
      icon: 'question',
      title: __('Gerar keywords?', 'alpha-suite'),
      text: __('Isso vai substituir o conteúdo do campo por keywords geradas. Tem certeza?', 'alpha-suite'),
      confirmButtonText: __('Gerar', 'alpha-suite'),
      cancelButtonText: __('Cancelar', 'alpha-suite')
    });

    if (!ok) return;

    // opcional: evita clique duplo
    const $btn = $(this);
    if ($btn.data('loading')) return;
    $btn.data('loading', 1).prop('disabled', true);

    // ✅ abre o loading ANTES do fetch
    Swal.fire({
      title: __('Gerando keywords...', 'alpha-suite'),
      text: __('Aguarde um instante.', 'alpha-suite'),
      allowOutsideClick: false,
      allowEscapeKey: false,
      didOpen: () => {
        Swal.showLoading();
      },
    });

    try {
      const payload = {
        command: cmd,
        locale: ($box.find('.pga_locale').val() || 'pt_BR'),
        template: ($box.find('.pga_template_key').val() || 'article'),
        category: ($box.find('.pga_category').val() || ''),
      };

      const j = await fetchJSON(PGA_CFG.rest + '/orion/keywords', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': PGA_CFG.nonce,
        },
        body: JSON.stringify(payload),
        silent: true
      });

      if (!j || !j.ok) {
        throw new Error((j && j.message) ? j.message : __('Falha ao gerar keywords.', 'alpha-suite'));
      }

      $ta.val(pgaNormalizeKeywordsText(j.keywords_text || ''));

      // fecha loading e mostra sucesso
      Swal.close();
      await Swal.fire({ icon: 'success', title: __('Pronto', 'alpha-suite'), text: __('Keywords geradas.', 'alpha-suite') });

      if (typeof window.PGA_saveGroupsToStorage === 'function') {
        window.PGA_saveGroupsToStorage();
      }
    } catch (err) {
      Swal.close();
      Swal.fire({ icon: 'error', title: __('Erro', 'alpha-suite'), text: String(err.message || err) });
    } finally {
      $btn.data('loading', 0).prop('disabled', false);
    }
  });

  function cfg() {
    const c = window.PGA_PROMPTS_EXPORT || {};
    return {
      ajaxurl: c.ajaxurl || window.ajaxurl || '',
      nonce: c.nonce || ''
    };
  }

  function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, m => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[m]));
  }

  // safeJson removed: use fetchJSON(...) instead for unified handling

  function pad2(n) { return String(n).padStart(2, '0'); }
  function exportFilename(prefix = 'orion-prompts') {
    const d = new Date();
    const yyyy = d.getFullYear();
    const mm = pad2(d.getMonth() + 1);
    const dd = pad2(d.getDate());
    const hh = pad2(d.getHours());
    const mi = pad2(d.getMinutes());
    const ss = pad2(d.getSeconds());
    return `${prefix}-${yyyy}-${mm}-${dd}_${hh}-${mi}-${ss}.json`;
  }

  // =========================
  // EXPORT
  // =========================
  document.addEventListener('click', async function (e) {
    const btn = e.target.closest('#pga-prompts-export');
    if (!btn) return;

    const { ajaxurl, nonce } = cfg();
    if (!ajaxurl || !nonce) { alert(__('Config export ausente (ajaxurl/nonce).', 'alpha-suite')); return; }

    try {
      if (window.Swal) {
        Swal.fire({ title: __('Exportando…', 'alpha-suite'), allowOutsideClick: false, allowEscapeKey: false, didOpen: () => Swal.showLoading() });
      }

      const j = await fetchJSON(ajaxurl + '?action=pga_orion_prompts_export', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: new URLSearchParams({ _ajax_nonce: nonce })
      });
      if (!j || !j.success) throw new Error(j?.data?.message || __('Falha no export.', 'alpha-suite'));

      const blob = new Blob([JSON.stringify(j.data, null, 2)], { type: 'application/json' });
      const url = URL.createObjectURL(blob);

      const a = document.createElement('a');
      a.href = url;
      a.download = (j.data?._meta?.filename) ? String(j.data._meta.filename) : exportFilename('orion-prompts');
      document.body.appendChild(a);
      a.click();
      a.remove();
      URL.revokeObjectURL(url);

      if (window.Swal) Swal.close();
    } catch (err) {
      if (window.Swal) Swal.fire({ icon: 'error', title: 'Erro', text: String(err.message || err) });
      else alert(String(err.message || err));
    }
  });

  // =========================
  // IMPORT (picker)
  // =========================
  document.addEventListener('click', function (e) {
    const btn = e.target.closest('#pga-prompts-import');
    if (!btn) return;

    const input = document.getElementById('pga-prompts-import-file');
    if (!input) return;

    input.value = '';
    input.click();
  });

  // =========================
  // IMPORT (prepare + modal + apply)
  // =========================
  document.getElementById('pga-prompts-import-file')?.addEventListener('change', async function () {
    const file = this.files?.[0];
    if (!file) return;

    const { ajaxurl, nonce } = cfg();
    if (!ajaxurl || !nonce) { alert(__('Config import ausente (ajaxurl/nonce).', 'alpha-suite')); return; }

    try {
      if (window.Swal) {
        Swal.fire({ title: __('Lendo arquivo…', 'alpha-suite'), allowOutsideClick: false, allowEscapeKey: false, didOpen: () => Swal.showLoading() });
      }

      // 1) PREPARE
      const fd = new FormData();
      fd.append('action', 'pga_orion_prompts_import_prepare');
      fd.append('_ajax_nonce', nonce);
      fd.append('file', file);

      const j = await fetchJSON(ajaxurl, { method: 'POST', credentials: 'same-origin', body: fd });
      if (!j || !j.success) throw new Error(j?.data?.message || __('Falha ao ler o JSON.', 'alpha-suite'));

      const token = j.data?.token || '';
      const items = Array.isArray(j.data?.items) ? j.data.items : [];
      if (!token) throw new Error(__('Token não retornado no prepare.', 'alpha-suite'));
      if (!items.length) throw new Error(__('Nada importável encontrado no arquivo.', 'alpha-suite'));

      // 2) MODAL
      // items: [{key, type:'template|prompt', tpl, stage, hasExisting, size}]

      // agrupa por tpl
      const groups = {};
      for (const it of items) {
        const tpl = it.tpl || 'unknown';
        groups[tpl] = groups[tpl] || [];
        groups[tpl].push(it);
      }

      // ordena templates (article primeiro)
      const order = Object.keys(groups).sort((a, b) => {
        const prio = { article: 0, modelar_youtube: 1 };
        const pa = (prio[a] ?? 99), pb = (prio[b] ?? 99);
        if (pa !== pb) return pa - pb;
        return a.localeCompare(b);
      });

      // cria HTML com <details> (colapse nativo, leve)
      const htmlGroups = order.map((tpl, gi) => {
        const list = groups[tpl];

        const headerExists = list.some(x => x.hasExisting);
        const headerMeta = headerExists
          ? `<span style="color:#b45309;margin-left:6px">${__('tem itens existentes', 'alpha-suite')}</span>`
          : `<span style="color:#15803d;margin-left:6px">${__('novo', 'alpha-suite')}</span>`;

        // lista interna (prompts)
        const inner = list.map((it) => {
          // você pode esconder a linha "template" e só mostrar prompts
          if (it.type === 'template') return '';

          const meta = it.hasExisting ? __('já existe', 'alpha-suite') : __('novo', 'alpha-suite');
          const metaColor = it.hasExisting ? '#b45309' : '#15803d';
          const small = it.size ? ` <span style="color:#666">(${Number(it.size)} chars)</span>` : '';

          return `
      <label style="display:flex;gap:10px;align-items:flex-start;padding:6px 0;border-bottom:1px solid #f1f1f1">
        <input type="checkbox" class="pga-import-item" data-key="${escapeHtml(it.key)}" checked style="margin-top:3px">
        <div style="line-height:1.2">
          <div>
            <code>${escapeHtml(it.stage)}</code>
            — <span style="color:${metaColor}">${meta}</span>${small}
          </div>
        </div>
      </label>
    `;
        }).join('');

        return `
    <div style="border:1px solid #eee;border-radius:12px;padding:10px;margin:10px 0">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:10px">
        <label style="display:flex;gap:10px;align-items:center">
          <input type="checkbox" class="pga-import-tpl-all" data-tpl="${escapeHtml(tpl)}" checked>
          <b>${escapeHtml(tpl)}</b>
          ${headerMeta}
        </label>

        <small style="color:#666">${list.filter(x => x.type === 'prompt').length} prompts</small>
      </div>

      <details style="margin-top:8px">
        <summary style="cursor:pointer;color:#111">${__('ver itens', 'alpha-suite')}</summary>
        <div style="margin-top:8px;max-height:260px;overflow:auto;padding-right:6px">
          ${inner || `<div style="color:#666">${__('Nenhum prompt encontrado neste modelo.', 'alpha-suite')}</div>`}
        </div>
      </details>
    </div>
  `;
      }).join('');

      const modalHtml = `
  <div style="text-align:left">
    <div style="margin-bottom:10px;color:#444;font-size:13px">
      ${__('Selecione o(s) modelo(s) para importar. Você pode abrir e desmarcar stages específicos.', 'alpha-suite')}
    </div>

    <div style="max-height:380px;overflow:auto">
      ${htmlGroups}
    </div>

    <div style="margin-top:12px;display:flex;gap:12px;flex-wrap:wrap;align-items:center">
      <label style="display:flex;gap:8px;align-items:center;font-size:13px">
        <input type="checkbox" id="pga-import-overwrite" />
        ${__('Sobrescrever itens existentes', 'alpha-suite')}
      </label>

      <button type="button" class="button" id="pga-import-select-all">${__('Marcar tudo', 'alpha-suite')}</button>
      <button type="button" class="button" id="pga-import-select-none">${__('Desmarcar tudo', 'alpha-suite')}</button>
    </div>
  </div>
`;


      let res;
      if (window.Swal) {
        res = await Swal.fire({
          title: __('Importar (seleção)', 'alpha-suite'),
          html: modalHtml,
          width: 760,
          showCancelButton: true,
          confirmButtonText: __('Importar selecionados', 'alpha-suite'),
          cancelButtonText: __('Cancelar', 'alpha-suite'),
          focusConfirm: false,
          didOpen: () => {
            // marcar/desmarcar geral
            document.getElementById('pga-import-select-all')?.addEventListener('click', () => {
              document.querySelectorAll('.pga-import-item, .pga-import-tpl-all').forEach(cb => cb.checked = true);
            });
            document.getElementById('pga-import-select-none')?.addEventListener('click', () => {
              document.querySelectorAll('.pga-import-item, .pga-import-tpl-all').forEach(cb => cb.checked = false);
            });

            // marcar/desmarcar por template
            document.querySelectorAll('.pga-import-tpl-all').forEach(cbTpl => {
              cbTpl.addEventListener('change', () => {
                const tpl = cbTpl.getAttribute('data-tpl');
                if (!tpl) return;

                // marca/desmarca todos os itens daquele tpl
                // (como a key é pr:TPL:stage, a gente filtra por prefixo)
                document.querySelectorAll('.pga-import-item').forEach(cb => {
                  const key = cb.getAttribute('data-key') || '';
                  if (key.startsWith(`pr:${tpl}:`)) {
                    cb.checked = cbTpl.checked;
                  }
                });
              });
            });
          },

          preConfirm: () => {
            const keys = Array.from(document.querySelectorAll('.pga-import-item'))
              .filter(cb => cb.checked)
              .map(cb => cb.getAttribute('data-key'))
              .filter(Boolean);

            const overwrite = !!document.getElementById('pga-import-overwrite')?.checked;

            if (!keys.length) {
              Swal.showValidationMessage(__('Selecione ao menos 1 item.', 'alpha-suite'));
              return false;
            }
            return { keys, overwrite };
          }
        });
      } else {
        res = { isConfirmed: true, value: { keys: [items[0].key], overwrite: false } };
      }

      if (!res.isConfirmed) return;

      // 3) APPLY
      if (window.Swal) {
        Swal.fire({ title: __('Importando…', 'alpha-suite'), allowOutsideClick: false, allowEscapeKey: false, didOpen: () => Swal.showLoading() });
      }

      const body = new URLSearchParams();
      body.set('action', 'pga_orion_prompts_import_apply');
      body.set('_ajax_nonce', nonce);
      body.set('token', token);
      body.set('overwrite', res.value.overwrite ? '1' : '0');
      body.set('keys', JSON.stringify(res.value.keys || []));

      const j2 = await fetchJSON(ajaxurl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body
      });
      if (!j2 || !j2.success) throw new Error(j2?.data?.message || __('Falha ao aplicar import.', 'alpha-suite'));

      if (window.Swal) {
        Swal.fire({
          icon: 'success',
          title: __('Importado!', 'alpha-suite'),
          text: j2.data?.message || __('Itens aplicados.', 'alpha-suite'),
        }).then(() => {
          window.location.reload();
        });
      } else {
        alert(j2.data?.message || __('Importado!', 'alpha-suite'));
        window.location.reload();
      }

    } catch (err) {
      if (window.Swal) Swal.fire({ icon: 'error', title: __('Erro', 'alpha-suite'), text: String(err.message || err) });
      else alert(String(err.message || err));
    } finally {
      try { this.value = ''; } catch (e) { }
    }
  });

  document.addEventListener('click', async function (e) {
    const rm = e.target.closest('.pga-remove-tpl-row');
    if (!rm) return;

    const tr = rm.closest('tr');
    const slug = tr?.getAttribute('data-slug');
    if (!slug) return;

    const cfg = window.PGA_PROMPTS_EXPORT || {};
    const ajaxurl = cfg.ajaxurl || window.ajaxurl || '';
    const nonce = cfg.nonce || '';

    const go = async () => {
      const body = new URLSearchParams({
        action: 'pga_orion_template_delete',
        _ajax_nonce: nonce,
        slug
      });

      const j = await fetchJSON(ajaxurl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body
      });
      console.log(j)
      if (!j || !j.success) throw new Error(j?.data?.message || __('Falha ao remover.', 'alpha-suite'));
      tr.remove();
    };

    const ok = await pgaConfirm({
      icon: 'warning',
      title: __('Remover modelo?', 'alpha-suite'),
      text: __('Isso apaga do banco o modelo e TODOS os prompts dele.', 'alpha-suite'),
      confirmButtonText: __('Remover de vez', 'alpha-suite'),
      cancelButtonText: __('Cancelar', 'alpha-suite')
    });
    if (!ok) return;
    try {
      if (window.Swal) Swal.fire({ title: __('Removendo…', 'alpha-suite'), allowOutsideClick: false, didOpen: () => Swal.showLoading() });
      await go();
      if (window.Swal) Swal.fire({ icon: 'success', title: __('Removido', 'alpha-suite'), timer: 900, showConfirmButton: false });
    } catch (err) {
      if (window.Swal) Swal.fire({ icon: 'error', title: __('Erro', 'alpha-suite'), text: String(err.message || err) });
      else alert(String(err.message || err));
    }
  });


  const KEY_TABS_INDEX = 'pga_orion_tabs_index_v1';
  const KEY_ACTIVE_TAB = 'pga_orion_active_tab_v1';

  function tabGroupsKey(tabId) {
    return `pga_orion_tab_${tabId}_groups_v1`;
  }

  function parseJson(raw, fallback) {
    try {
      const v = raw ? JSON.parse(raw) : null;
      return v ?? fallback;
    } catch (e) {
      return fallback;
    }
  }

  function makeId() {
    return 't_' + Date.now() + '_' + Math.random().toString(16).slice(2);
  }

  function getTabIdFromUrl() {
    const u = new URL(window.location.href);
    return u.searchParams.get('tab') || '';
  }

  function getDefaultTemplatesFromButton() {
    const btn = document.getElementById('pga_tab_add');
    if (!btn) return ['article'];

    const raw = btn.getAttribute('data-default-templates') || '';
    if (!raw) return ['article'];

    try {
      const arr = JSON.parse(raw);
      if (Array.isArray(arr) && arr.length) return arr.map(s => String(s || '').trim()).filter(Boolean);
    } catch (e) { /* ignore */ }

    return ['article'];
  }

  function pgaDoneKey() {
    return `pga_orion_done_v1`;
  }

  window.pgaLoadDone = function () {
    try {
      const raw = localStorage.getItem(pgaDoneKey()) || '[]';
      const arr = JSON.parse(raw);
      return Array.isArray(arr) ? arr : [];
    } catch (e) {
      return [];
    }
  };

  window.pgaSaveDone = function (list) {
    try {
      const arr = Array.isArray(list) ? list : [];
      localStorage.setItem(pgaDoneKey(), JSON.stringify(arr));
      return true;
    } catch (e) {
      return false;
    }
  };

  window.pgaAddDone = function (kw) {
    const v = String(kw || '').trim();
    if (!v) return;

    const list = window.pgaLoadDone();
    const exists = list.some(x => String(x).localeCompare(v, undefined, { sensitivity: 'accent' }) === 0);
    if (exists) return;

    list.unshift(v); // último feito no topo
    window.pgaSaveDone(list);
  };

  window.pgaClearDoneLocal = function () {
    try { localStorage.removeItem(pgaDoneKey()); } catch (e) { }
  };

  const $done = $('#pga_kw_done');

  function renderDone(list) {
    $done.empty();
    (list || []).forEach(k => $('<li/>').text(k).appendTo($done));
  }
  // ---------- Importar / Exportar / Limpar POR GRUPO ----------

  // input[type=file] único para todos os grupos
  let importTargetBox = null;
  let $file = $('#pga_kw_file');
  if (!$file.length) {
    $file = $('<input type="file" id="pga_kw_file" accept=".txt,text/plain" style="display:none">');
    $('body').append($file);
  }

  // Clique em "Importar .txt" dentro de um grupo
  $(document).off('click.pgaImport').on('click.pgaImport', '.pga_import_box', function () {
    importTargetBox = $(this).closest('.pga-gen-box');
    if (!importTargetBox.length) return;
    $file.trigger('click');
    window.PGA_IS_DIRTY = true;
  });

  // Quando o arquivo é escolhido
  $file.off('change.pgaImport').on('change.pgaImport', function () {
    const f = this.files && this.files[0];
    if (!f || !importTargetBox) {
      this.value = '';
      importTargetBox = null;
      return;
    }

    const reader = new FileReader();
    reader.onload = async function (ev) {
      const text = String(ev.target.result || '');
      const $ta = importTargetBox.find('.pga_keywords');

      const cur = textareaToArray($ta.val());
      const neu = textareaToArray(text);
      const set = Array.from(new Set(cur.concat(neu)));

      $ta.val(set.join('\n'));
      $file.val('');
      importTargetBox = null;

      if (window.Swal) {
        await Swal.fire({
          icon: 'info',
          title: __('Importado', 'alpha-suite'),
          text: sprintf(
            _n(
              '%d linha foi carregada. Clique em "Salvar configurações" para persistir.',
              '%d linhas foram carregadas. Clique em "Salvar configurações" para persistir.',
              neu.length,
              'alpha-suite'
            ),
            neu.length
          )
        });
      }

      pgaSaveBoxesToLocal();
    };
    reader.readAsText(f, 'utf-8');
  });

  // Exportar .txt do grupo atual
  $(document).off('click.pgaExport').on('click.pgaExport', '.pga_export_box', function () {
    const $box = $(this).closest('.pga-gen-box');
    const txt = String($box.find('.pga_keywords').val() || '');
    const blob = new Blob([txt], { type: 'text/plain;charset=utf-8' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'keywords.txt';
    a.click();
    URL.revokeObjectURL(a.href);
  });


  // Limpar apenas o grupo atual
  $(document).off('click.pgaDeleteGenerator').on('click.pgaDeleteGenerator', '.pga_clear_box', async function () {
    const $box = $(this).closest('.pga-gen-box');
    const $container = $('#pga_gen_container');
    const totalBoxes = $container.find('.pga-gen-box').length;

    if (!$box.length) return;

    // se for o único, só limpa
    if (totalBoxes <= 1) {
      const ok = await pgaConfirm({
        icon: 'warning',
        title: __('Limpar este gerador?', 'alpha-suite'),
        text: __('Este é o único gerador. Vamos apenas limpar os campos.', 'alpha-suite'),
        confirmButtonText: __('Limpar', 'alpha-suite'),
        cancelButtonText: __('Cancelar', 'alpha-suite')
      });

      if (!ok) return;

      $box.find('.pga_keywords').val('');
      $box.find('.pga_total').val('6');
      $box.find('.pga_per_day').val('3');
      $box.find('.pga_template_key').val('article');
      $box.find('.pga_locale').val('pt_BR');
      $box.find('.pga_length').val('medium');
      $box.find('.pga_category').val('0');

      pgaUpdateBoxTitle($box);
      return;
    }

    const ok = await pgaConfirm({
      icon: 'warning',
      title: __('Excluir gerador?', 'alpha-suite'),
      text: __('Este gerador será removido.', 'alpha-suite'),
      confirmButtonText: __('Excluir', 'alpha-suite'),
      cancelButtonText: __('Cancelar', 'alpha-suite')
    });

    if (!ok) return;

    $box.remove();

    // reindexa
    const $boxes = $container.find('.pga-gen-box');
    $boxes.each(function (idx) {
      const $b = $(this);
      $b.attr('data-gen', idx + 1);
      pgaUpdateBoxTitle($b);
    });

    // garante IDs no primeiro
    const $first = $boxes.first();
    if ($first.length) pgaActivateBox($first);

    window.PGA_IS_DIRTY = true;
  });

  function loadTabs() {
    const tabs = parseJson(localStorage.getItem(KEY_TABS_INDEX), []);
    return Array.isArray(tabs) ? tabs : [];
  }

  function saveTabs(tabs) {
    localStorage.setItem(KEY_TABS_INDEX, JSON.stringify(tabs || []));
  }

  function ensureTabsAndTabId() {
    let tabs = loadTabs();

    if (!tabs.length) {
      const first = { id: makeId(), title: __('Projeto 1', 'alpha-suite') };
      tabs = [first];
      saveTabs(tabs);
    }

    // NUNCA puxa da URL; só do storage
    let tabId = localStorage.getItem(KEY_ACTIVE_TAB) || tabs[0].id;

    if (!tabs.some(t => t.id === tabId)) {
      tabId = tabs[0].id;
    }

    localStorage.setItem(KEY_ACTIVE_TAB, tabId);
    return { tabs, tabId };
  }

  function renderTabsUI(tabs, tabId) {
    const $wrap = $('#pga_tabs');
    if (!$wrap.length) return;

    $wrap.empty();

    // tabs dinâmicas (como hoje)
    tabs.forEach(t => {
      const active = (t.id === tabId);

      const $btn = $('<button/>', {
        type: 'button',
        class: 'button ' + (active ? 'button-primary' : ''),
        'data-tab': t.id
      });

      const $label = $('<span/>', {
        class: 'pga-tab-label',
        text: t.title || 'Projeto'
      });

      const $trash = $('<span/>', {
        class: 'pga-tab-trash',
        html: 'x',
        title: 'Excluir aba'
      });

      $trash.on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        pgaDeleteTab(t.id);
      });

      $btn.append($label, $trash);
      $wrap.append($btn);
    });
  }


  function buildGroupsForNewProject() {
    const defaults = getDefaultTemplatesFromButton(); // ['pilar', 'artigo_topo', ...] ou ['article']
    // monta estrutura igual serialize usa, só o necessário
    return defaults.map((tplKey) => ({
      keywords: '',
      locale: 'pt_BR',
      template_key: tplKey || 'article',
      category: '0',
      total: 6,
      per_day: 3,
      first_delay: '',
      length: 'medium',
      link_max: 2,
      internal_links: { mode: 'none', max: 0, manual_ids: '' }
    }));
  }

  function addTabAndGo(name) {
    const tabs = loadTabs();
    const nextNum = tabs.length + 1;
    const title = String(name || '').trim() || (__('Projeto ', 'alpha-suite') + nextNum);

    const tab = { id: makeId(), title };
    tabs.push(tab);
    saveTabs(tabs);

    // cria grupos default já na tab nova
    try {
      const groups = buildGroupsForNewProject();
      saveGroupsForTab(tab.id, groups);
    } catch (e) { }

    // ativa sem navegar
    localStorage.setItem(KEY_ACTIVE_TAB, tab.id);
    loadTabGroups(tab.id);
    renderTabsUI(tabs, tab.id);

    window.PGA_IS_DIRTY = false;
  }

  function loadTabGroups(tabId) {
    // 1) migra 1 vez (se necessário)
    migrateLegacyGroupsToTabOnce(tabId);

    // 2) carrega do storage oficial
    const groups = loadGroupsForTab(tabId);

    // 3) aplica no DOM
    applyGroupsToDom(groups);
  }


  function saveCurrentTabGroups(tabId) {
    const groups = serializeAllGroupsFromDom();
    saveGroupsForTab(tabId, groups);
  }

  async function pgaDeleteTab(tabId) {
    if (!tabId) return;

    const tabs = loadTabs(); // << seu nome
    const idx = tabs.findIndex(t => t.id === tabId);
    if (idx === -1) return;

    const name = tabs[idx].title || __('Projeto', 'alpha-suite');

    const ok = await pgaConfirm({
      title: __('Excluir aba?', 'alpha-suite'),
      text: sprintf(
        __('Você tem certeza que deseja excluir <b>%s</b>?<br><br><small>Isso apaga os grupos salvos dessa aba.</small>', 'alpha-suite'),
        escapeHtml(name)
      ),
      icon: 'warning',
      confirmButtonText: __('Sim, excluir', 'alpha-suite'),
      cancelButtonText: __('Cancelar', 'alpha-suite')
    });

    if (!ok) return;

    // apaga storage dessa tab
    try {
      localStorage.removeItem(`pga_orion_tab_${tabId}_groups_v1`);
    } catch (e) { }

    // remove do index
    tabs.splice(idx, 1);

    // se ficou vazio, recria uma default
    if (!tabs.length) {
      const first = { id: makeId(), title: __('Projeto 1', 'alpha-suite') };
      tabs.push(first);
      saveTabs(tabs);

      const u = new URL(window.location.href);
      u.searchParams.set('tab', first.id);
      window.location.href = u.toString();
      return;
    }

    // salva index
    saveTabs(tabs);

    // se estava na tab deletada, vai pra primeira
    const cur = (typeof pgaGetTabId === 'function')
      ? pgaGetTabId()
      : (new URL(window.location.href).searchParams.get('tab') || '');

    if (cur === tabId) {
      const u = new URL(window.location.href);
      u.searchParams.set('tab', tabs[0].id);
      window.location.href = u.toString();
      return;
    }

    // senão, só re-renderiza
    if (typeof renderTabsUI === 'function') {
      renderTabsUI(tabs, cur);
    }
  }



  $(function () {
    // 1) garante tabId
    const st = ensureTabsAndTabId();

    // 2) render tabs se existir o container (depois a gente põe no PHP)
    renderTabsUI(st.tabs, st.tabId);

    // 3) botão adicionar tab (se existir no PHP)
    $('#pga_tab_add').off('click').on('click', async function () {
      // salva antes de criar (pra não perder ajustes)
      try { saveCurrentTabGroups(st.tabId); } catch (e) { }

      let name = '';
      if (window.Swal) {
        const res = await Swal.fire({
          html: `
            <div class="pga-modal-content">
              <h3 style="margin:0">${__('Novo projeto', 'alpha-suite')}</h3>
              <div class="pga-descricao">
                ${__('Crie um novo projeto para organizar seus geradores de conteúdo.', 'alpha-suite')}
              </div>
              <div class="pga-field">
                <label for="pga_new_project_name">${__('Nome do Projeto', 'alpha-suite')}</label>
                <input id="pga_new_project_name" class="swal2-input" placeholder="${__('Ex: Blog de Marketing', 'alpha-suite')}" style="width:100%;margin:0" />
              </div>
            </div>
          `,
          showCancelButton: true,
          focusConfirm: false,
          cancelButtonText: __('Cancelar', 'alpha-suite'),
          confirmButtonText: __('Criar Projeto', 'alpha-suite'),
          preConfirm: () => {
            const v = document.getElementById('pga_new_project_name')?.value || '';
            return String(v).trim();
          }
        });

        if (!res.isConfirmed) return;
        name = res.value || '';
      } else {
        name = prompt(__('Nome do projeto:', 'alpha-suite')) || '';
        if (!String(name).trim()) return;
      }

      addTabAndGo(name);
    });


    // 4) carrega dados dessa tab nos colapses existentes
    loadTabGroups(st.tabId);
  });
  // init select2 dentro de um box específico
  function pgaInitSelect2InBox($box) {
    if (!$box || !$box.length) return;

    $box.find('select.pga-select2').each(function () {
      const $el = $(this);
      if ($el.data('select2')) return;

      const isTags = $el.hasClass('pga_tags');

      $el.select2({
        width: '100%',
        dropdownParent: $box,
        tags: isTags,
        tokenSeparators: [','],
        placeholder: 'Digite algo',
      });
    });
  }

  // toggle FAQ por box
  $(document).on('change', '.pga_faq_enable', function () {
    const $box = $(this).closest('.pga-gen-box');
    const on = $(this).is(':checked');
    $box.find('.pga_faq_count_wrap').toggle(on);
  });


  $('#pga_save_keywords')
    .off('click.pgaSave')
    .on('click.pgaSave', async function (e) {
      e.preventDefault();
      e.stopPropagation();

      const btn = this;

      // âœ… guard anti re-entrada (evita loop/call stack)
      if (btn.dataset && btn.dataset.pgaSaving === '1') return;
      if (btn.dataset) btn.dataset.pgaSaving = '1';

      btn.disabled = true;

      try {
        // âœ… salva grupos 1x sÃ³
        const tabId = localStorage.getItem(KEY_ACTIVE_TAB) || getTabIdFromUrl() || '';
        if (tabId) {
          saveGroupsForTab(tabId, serializeAllGroupsFromDom());
        }

        // âœ… render done local
        if (typeof pgaLoadDone === 'function') {
          renderDone(pgaLoadDone());
        }

        window.PGA_IS_DIRTY = false;
        pgaToast('success', __('Salvo', 'alpha-suite'));

      } catch (err) {
        // âœ… nunca passe objeto Error direto pro Swal title
        const msg = (err && err.message) ? err.message : String(err || 'Erro');
        pgaToast('error', msg, 2200);

      } finally {
        btn.disabled = false;
        if (btn.dataset) delete btn.dataset.pgaSaving;
      }
    });

  $(document).off('click.pgaSaveBox').on('click.pgaSaveBox', '.pga_save_box', function (e) {
    e.preventDefault();
    e.stopPropagation();
    $('#pga_save_keywords').trigger('click');
  });

  /* ============================================================
 * NORMALIZA KEYWORDS
 * ========================================================== */
  function normalizarKeywords(input) {
    if (Array.isArray(input)) {
      return input.map(v => String(v).trim()).filter(Boolean);
    }
    if (typeof input === 'string') {
      return input.split('\n').map(v => v.trim()).filter(Boolean);
    }
    return [];
  }

  /* ============================================================
   * COLETA DE PAYLOAD (TODOS OS GERADORES)
   * ========================================================== */
  function buildPayloadTotal() {
    const geradores = [];

    $('#pga_gen_container .pga-gen-box').each(function () {
      const $box = $(this);
      const kws = normalizarKeywords($box.find('.pga_keywords').val() || '');

      geradores.push({
        boxEl: this,
        enabled: $box.find('.pga_custom_enabled').is(':checked'),
        locale: $box.find('.pga_locale').val(),
        length: $box.find('.pga_length').val(),
        template_key: $box.find('.pga_template_key').val(),
        category_id: parseInt($box.find('.pga_category').val() || '0', 10),
        per_day: parseInt($box.find('.pga_per_day').val() || '1', 10),
        total: parseInt($box.find('.pga_total').val() || kws.length || '0', 10),
        first_delay_hours: ($box.find('.pga_first_delay_hours').val() || '').trim(),
        keywords: kws,
        tags: $box.find('.pga_tags').val() || [],
        faq: {
          enabled: $box.find('.pga_make_faq').is(':checked'),
          qty: parseInt($box.find('.pga_faq_qty').val() || '0', 10)
        },
        internal_links: {
          mode: $box.find('.pga_link_mode').val() || 'none',
          max: parseInt($box.find('.pga_link_max').val() || '0', 10),
          manual_ids: ($box.find('.pga_link_manual').val() || []).join(',')
        }
      });
    });

    return {
      global: {
        enabled: pgaGlobalIsOn(),
        total: parseInt($('#pga_plan_total').val() || '0', 10),
        start: $('#pga_plan_start').val()
      },
      geradores
    };
  }

  function gerarHorariosHumanizados(baseDayTs, postsNoDia) {
    const agora = Math.floor(Date.now() / 1000);

    const inicioPadrao = baseDayTs + (6.5 * 3600); // 06:30
    const fimPadrao = baseDayTs + (22 * 3600);  // 22:00

    // 🔥 se for hoje, começa depois do horário atual
    const buffer = 15 * 60; // 15 minutos de segurança
    const inicioReal = baseDayTs <= agora && agora < fimPadrao
      ? Math.max(inicioPadrao, agora + buffer)
      : inicioPadrao;

    // se já passou do horário permitido hoje, empurra para amanhã
    if (inicioReal >= fimPadrao) {
      return gerarHorariosHumanizados(
        baseDayTs + 86400,
        postsNoDia
      );
    }

    const janela = fimPadrao - inicioReal;
    const gapBase = janela / (postsNoDia + 1);

    const horarios = [];

    for (let i = 0; i < postsNoDia; i++) {
      let ts = inicioReal + gapBase * (i + 1);

      const variacaoMax = Math.min(45 * 60, gapBase * 0.3);
      const variacao = (Math.random() * variacaoMax) - (variacaoMax / 2);

      ts += variacao;
      horarios.push(Math.floor(ts));
    }

    return horarios;
  }

  /* ============================================================
   * MONTA FILA GLOBAL (ROUND ROBIN POR DIA)
   * ========================================================== */
  function montarFilaGlobal(geradores, totalGlobal, startDateStr) {
    if (!startDateStr) {
      Swal.fire({
        icon: 'warning',
        title: 'Data inicial inválida',
        text: 'Defina uma data válida para iniciar as publicações.'
      });
      return null;
    }

    const baseDate = new Date(startDateStr + 'T00:00:00');
    const baseDayTs = Math.floor(baseDate.getTime() / 1000);

    const fila = [];
    const cursores = geradores.map(() => 0);

    let diaOffset = 0;

    // enquanto ainda precisamos gerar posts
    while (fila.length < totalGlobal) {
      let algumGeradorUsou = false;

      for (let gi = 0; gi < geradores.length; gi++) {
        const g = geradores[gi];
        const horarios = gerarHorariosHumanizados(
          baseDayTs + diaOffset * 86400,
          g.per_day
        );

        let usadosNoDia = 0;

        while (
          usadosNoDia < g.per_day &&
          fila.length < totalGlobal &&
          cursores[gi] < g.keywords.length
        ) {
          fila.push({
            ...g,
            keyword: g.keywords[cursores[gi]],
            publish_time: horarios[usadosNoDia]
          });

          cursores[gi]++;
          usadosNoDia++;
          algumGeradorUsou = true;
        }

        // ⚠️ dia passa SEMPRE que um gerador foi processado
        if (usadosNoDia > 0) {
          diaOffset++;
        }

        if (fila.length >= totalGlobal) break;
      }

      if (!algumGeradorUsou) break;
    }

    return fila;
  }

  async function pgaFetchSectionWithRetry({ post_id, section_id }, retries = 3) {
    let lastError;

    for (let i = 0; i < retries; i++) {
      try {
        const res = await fetchJSON(`${REST}/orion/section`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': NONCE
          },
          body: JSON.stringify({
            post_id,
            section_id
          }),
          silent: true
        });

        if (res && !res.code) {
          return { ok: true };
        }

        lastError = res;
      } catch (e) {
        lastError = e;
      }
    }

    return { ok: false, error: lastError };
  }

  function marcarKeywordComoDone(rawKw, boxEl) {
    const kw = (rawKw || '').trim();
    if (!kw || !boxEl) return false;

    const $box = $(boxEl);
    const $ta = $box.find('.pga_keywords').first();
    if (!$ta.length) return false;

    const orig = $ta.val() || '';
    if (!orig) return false;

    const lines = orig
      .split('\n')
      .map(l => l.trim())
      .filter(l => l.length > 0);

    const idx = lines.findIndex(l => l.localeCompare(kw, undefined, { sensitivity: 'accent' }) === 0);
    if (idx === -1) return false;

    lines.splice(idx, 1);
    $ta.val(lines.join('\n'));

    // ✅ agora persiste e renderiza DONE corretamente
    // UI done + persistência
    window.pgaAddDone(kw);

    const $done = $('#pga_kw_done');
    if ($done.length) {
      const li = document.createElement('li');
      li.textContent = kw;
      $done.prepend(li); // topo
    }

    // ✅ só salva grupos (não mexe no dirty aqui)
    pgaSaveBoxesToLocal();
    return true;
  }

  /* ============================================================
   * GERADOR REAL (PIPELINE ORION)
   * ========================================================== */
  async function gerarPost(job, onStatus) {
    onStatus('Testando integrações…');

    const payload = {
      key: '',
      model: '',
      temperature: '',
      max_tokens: ''
    };

    const res = await fetch(`${REST}/selftest`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': NONCE
      },
      body: JSON.stringify(payload)
    });
    const j = await res.json();

    if (!res.ok) {
      const msg = j && (j.message || j.error || j.code) ? (j.message || j.error || j.code) : `HTTP ${res.status}`;
      await Swal.fire({ icon: 'error', title: __('Erro ao testar', 'alpha-suite'), text: msg });
      return;
    }

    onStatus('Gerando esboço…');
    const outline = await fetchJSON(`${REST}/orion/outline`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
      body: JSON.stringify({
        keyword: job.keyword,
        keywords: [job.keyword],
        length: job.length,
        locale: job.locale,
        template_key: job.template_key,
        publish_time: job.publish_time,
        category_id: job.category_id,
        post_type: 'posts_orion',
        tags: job.tags || []
      })
    });

    if (!outline?.post_id) {
      throw new Error('post_id ausente no outline');
    }

    const postId = outline.post_id;
    const sections = outline.sections || [];

    let done = 0;
    for (const sec of sections) {
      onStatus(`Gerando seções… (${++done}/${sections.length})`);
      await pgaFetchSectionWithRetry({
        post_id: postId,
        section_id: sec.id
      });
    }

    if (job.faq?.enabled) {
      onStatus('Gerando FAQ…');
      await fetchJSON(`${REST}/orion/faq`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
        body: JSON.stringify({
          post_id: postId,
          keyword: job.keyword,
          locale: job.locale,
          qty: Math.min(7, job.faq.qty || 5)
        })
      });
    }

    onStatus('Juntando conteúdo…');

    await fetchJSON(`${REST}/orion/finalize`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
      body: JSON.stringify({
        post_id: postId,
        internal_links: job.internal_links || {},
        tags: job.tags || []
      })
    });

    onStatus('Gerando imagem…');

    await fetchJSON(`${REST}/orion/image`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
      body: JSON.stringify({
        post_id: postId,
        keyword: job.keyword,
        locale: job.locale,
        template: job.template_key
      })
    });

    return outline;
  }

  /* ============================================================
   * EXECUTOR COM SWEET ALERT
   * ========================================================== */
  async function executarFila(jobs) {
    const total = jobs.length;
    const postsGerados = [];

    Swal.fire({
      title: 'Gerando conteúdos',
      html: `
      <div style="text-align:center">
        <div id="pga_prog" style="text-align:center;font-size:13px;margin-bottom:4px;">
          <div id="pga-progress-main">${sprintf(__('Progresso: %d de %d', 'alpha-suite'), 0, total)} posts</div>
        </div>
        <div id="pga-progress-stage" style="margin-top:8px;font-size:13px;color:#666">
          Preparando…
        </div>
      </div>
    `,

      allowOutsideClick: false,
      allowEscapeKey: false,
      didOpen: () => Swal.showLoading()
    });

    for (let i = 0; i < total; i++) {
      const job = jobs[i];

      const onStatus = (msg) => {
        document.getElementById('pga-progress-stage').innerText = msg;
      };

      onStatus(`Iniciando post ${i + 1} de ${total}…`);

      const res = await gerarPost(job, onStatus);

      document.getElementById('pga-progress-main').innerText =
        `${i + 1} de ${total} posts`;

      marcarKeywordComoDone(job.keyword, job.boxEl);

      if (res?.post_id) {
        postsGerados.push({
          post_id: res.post_id,
          title: res.title || job.keyword
        });
      }

    }

    Swal.fire({
      icon: 'success',
      title: 'Geração concluída',
      html: postsGerados.length
        ? `<strong>Posts gerados:</strong><br><br>${postsGerados
          .map(p =>
            `<a href="${PGA_CFG.site_url}/wp-admin/post.php?post=${p.post_id}&action=edit"
             target="_blank">
             ${p.title}
           </a>`
          )
          .join('<br>')}`
        : 'Conteúdos gerados com sucesso.'
    });

  }

  /* ============================================================
   * CLIQUES
   * ========================================================== */

  // GERADOR UNITÁRIO
  $(document).on('click', '.pga_generate_box', async function () {
    const payload = buildPayloadTotal();
    const box = $(this).closest('.pga-gen-box')[0];
    const gerador = payload.geradores.find(g => g.boxEl === box);

    if (!gerador || !gerador.keywords.length) return;

    const jobs = montarFilaGlobal(
      [gerador],
      gerador.total,
      gerador.first_delay_hours
    );

    if (!jobs) return;

    await executarFila(jobs);
  });

  // GLOBAL
  $('#pga_plan').on('click', async function () {
    const payload = buildPayloadTotal();

    if (!payload.global.enabled) {
      for (const gerador of payload.geradores) {
        if (!gerador.keywords.length) continue;

        const jobs = montarFilaGlobal(
          [gerador],
          gerador.total,
          gerador.first_delay_hours
        );

        if (!jobs) return;
        await executarFila(jobs);
      }
      return;
    }

    const geradoresAtivos = payload.geradores.filter(g => g.enabled);

    if (!geradoresAtivos.length) {
      Swal.fire('Nenhum gerador selecionado');
      return;
    }

    const jobs = montarFilaGlobal(
      geradoresAtivos,
      payload.global.total,
      payload.global.start
    );

    await executarFila(jobs);
  });

  let chartViews = null;
  let chartTime = null;

  
  async function loadChart(range = 30) {

    const data = await fetchJSON(PGA_CFG.rest + '/analytics?range=' + range, {
      method: 'GET',
      headers: { 'X-WP-Nonce': PGA_CFG.nonce },
      silent: true
    });

    // 📈 linha (views)
    const ctx1 = document.getElementById('pgaChart');

    if (chartViews) chartViews.destroy();

    chartViews = new Chart(ctx1, {
      type: 'line',
      data: {
        labels: data.labels,
        datasets: [
          {
            label: 'Visualizações',
            data: data.views,
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59,130,246,0.1)',
            borderWidth: 2,
            tension: 0,
            pointRadius: 2,
            pointHoverRadius: 4,
            yAxisID: 'y'
          },
          {
            label: 'Tempo médio (s)',
            data: data.duration,
            borderColor: '#ef4444',
            backgroundColor: 'rgba(239,68,68,0.1)',
            borderWidth: 2,
            tension: 0,
            pointRadius: 2,
            pointHoverRadius: 4,
            yAxisID: 'y1'
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,

        layout: {
          padding: { top: 10, bottom: 10 }
        },

        interaction: {
          mode: 'index',
          intersect: false
        },
        plugins: {
          tooltip: {
            mode: 'index',
            intersect: false,
            callbacks: {
              label: function (context) {
                if (context.dataset.label === 'Visualizações') {
                  return ` ${context.parsed.y} acessos`;
                }
                if (context.dataset.label === 'Tempo médio (s)') {
                  return ` ${context.parsed.y}s`;
                }
              }
            }
          },
          legend: {
            labels: {
              usePointStyle: true,
              boxWidth: 6
            }
          }
        },

        scales: {
          x: {
            grid: {
              display: false
            }
          },
          y: {
            grid: {
              color: 'rgba(0,0,0,0.05)'
            }
          },
          y1: {
            position: 'right',
            grid: {
              drawOnChartArea: false
            }
          }
        }

      }
    });

    // ⏱️ doughnut (tempo médio)
    const ctx2 = document.getElementById('pgaTimeChart');

    if (chartTime) chartTime.destroy();

    chartTime = new Chart(ctx2, {
      type: 'doughnut',
      data: {
        labels: ['Tempo médio', 'Restante'],
        datasets: [{
          data: [data.avg_duration, 120 - data.avg_duration]
        }]
      }
    });

    // 🏆 ranking views
    document.getElementById('topPosts').innerHTML =
      data.top_posts.map((p, i) => `
      <div class="flex items-center justify-between border-b border-gray-100 pb-2">

        <div class="flex items-center gap-2">

          <span class="text-xs text-gray-400 w-5">
            ${i + 1}.
          </span>

          <a href="${p.url}" target="_blank"
            class="text-gray-700 hover:text-blue-600 line-clamp-1">
            ${p.title}
          </a>

        </div>

        <span class="text-xs font-semibold text-gray-500">
          ${p.views}
        </span>

      </div>
    `).join('');

    // 🧠 ranking tempo
    document.getElementById('topTime').innerHTML =
      data.top_time.map((p, i) => `
      <div class="flex items-center justify-between border-b border-gray-100 pb-2">

        <div class="flex items-center gap-2">

          <span class="text-xs text-gray-400 w-5">
            ${i + 1}.
          </span>

          <a href="${p.url}" target="_blank"
            class="text-gray-700 hover:text-red-500 line-clamp-1">
            ${p.title}
          </a>

        </div>

        <span class="text-xs font-semibold text-gray-500">
          ${p.duration}s
        </span>

      </div>
    `).join('');
  }

  document.addEventListener("DOMContentLoaded", () => {
    const select = document.getElementById('pgaRange');

    loadChart(select.value);

    select.addEventListener('change', () => {
      loadChart(select.value);
    });
  });

  // init quando a tela carrega (para o primeiro box existente)
  $(function () {
    $('#pga_gen_container .pga-gen-box').each(function () {
      pgaInitSelect2InBox($(this));
    });
  });
})(jQuery);