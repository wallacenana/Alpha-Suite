(function ($) {
    'use strict';

    window.PGA_STORY_READY = false;

    // -------------------------------------------------------
    // Helpers (REST + NONCE + i18n)
    // -------------------------------------------------------
    const REST = PGA_CFG.rest;
    const NONCE = PGA_CFG.nonce;
    const siteUrl = PGA_CFG.site_url;

    const sprintf = (window.wp && window.wp.i18n && window.wp.i18n.sprintf) ? window.wp.i18n.sprintf : function (s) { return s; };

    const __ = (window.wp && window.wp.i18n && window.wp.i18n.__)
        ? window.wp.i18n.__
        : function (s) { return s; };

    function getSelectedTheme() {
        const el = document.querySelector('input[name="pga_ws_theme"]:checked');
        return el ? el.value : 'theme-normal';
    }

    function applyTheme(themeClass) {
        const container = document.getElementById('frames-container');
        if (!container) return;

        const themeClasses = ['theme-normal', 'theme-news', 'theme-dark', 'theme-soft', 'theme-pop'];
        container.classList.remove(...themeClasses);
        container.classList.add(themeClass);

        // marca label ativo (leve)
        const labels = document.querySelectorAll('#pga_tabs .pga-ws-style');
        labels.forEach(lb => lb.classList.remove('is-active'));
        const activeLabel = document
            .querySelector(`#pga_tabs input[name="pga_ws_theme"][value="${themeClass}"]`)
            ?.closest('label');
        if (activeLabel) activeLabel.classList.add('is-active');
    }

    function isEditMode() {
        // se tiver ?story_id=123 => edição
        const p = new URLSearchParams(window.location.search || '');
        const id = parseInt(p.get('story_id') || '0', 10);
        return id > 0;
    }

    // -------------------------------------------------------
    // Tabs unit/multi
    // -------------------------------------------------------
    // let currentTab = 'unit';

    // window.switchTab = function (tab) {
    //     currentTab = (tab === 'multi') ? 'multi' : 'unit';

    //     // tenta primeiro dentro do modal, depois fora
    //     const scope = pgaGetModalPanel('publish') || document;

    //     const unitEl = scope.querySelector('#unit-selection');
    //     const multiEl = scope.querySelector('#multi-selection');

    //     // se não existe ainda, só atualiza estado e sai
    //     if (!unitEl || !multiEl) return;

    //     unitEl.classList.toggle('hidden-tab', currentTab !== 'unit');
    //     multiEl.classList.toggle('hidden-tab', currentTab !== 'multi');

    //     const tabUnitBtn = scope.querySelector('#tab-unit') || document.getElementById('tab-unit');
    //     const tabMultiBtn = scope.querySelector('#tab-multi') || document.getElementById('tab-multi');

    //     if (tabUnitBtn) tabUnitBtn.classList.toggle('tab-active', currentTab === 'unit');
    //     if (tabMultiBtn) tabMultiBtn.classList.toggle('tab-active', currentTab === 'multi');
    // };


    function showSkeleton() {
        const c = document.getElementById('frames-container');
        if (!c) return;
        c.classList.add('is-loading');

        // garante que o skeleton existe (caso algum innerHTML tenha apagado)
        if (!c.querySelector('.pga-skeleton')) {
            const sk = document.createElement('div');
            sk.className = 'pga-skeleton';
            sk.innerHTML = `
      <div class="sk-big"></div>
      <div class="sk-row"></div>
      <div class="sk-row"></div>
      <div class="sk-row" style="width:70%"></div>
    `;
            c.prepend(sk);
        }
    }

    function hideSkeleton() {
        const c = document.getElementById('frames-container');
        if (!c) return;
        c.classList.remove('is-loading');
    }

    function getSlidesCountFromState() {
        // maior índice numérico existente no frameState
        let max = 0;
        Object.keys(frameState).forEach(k => {
            const n = parseInt(k, 10);
            if (n > max) max = n;
        });
        return Math.max(1, max || 1);
    }

    function cloneState(st) {
        return st ? JSON.parse(JSON.stringify(st)) : { template: 'template-1', ctaOn: false };
    }

    // -------------------------------------------------------
    // Frames state (template + cta por slide)
    // -------------------------------------------------------
    const frameState = {}; // { [i]: { template, ctaOn } }

    function ensureState(i) {
        if (!frameState[i]) frameState[i] = {};

        const st = frameState[i];

        // defaults só se não existir
        if (!('template' in st)) st.template = 'template-1';

        if (!('cta_text' in st)) st.cta_text = '';
        if (!('cta_url' in st)) st.cta_url = '';

        if (!('heading' in st)) st.heading = '';
        if (!('body' in st)) st.body = '';

        // touched flags (opcional)
        if (!('heading_touched' in st)) st.heading_touched = false;
        if (!('body_touched' in st)) st.body_touched = false;
        if (!('cta_touched' in st)) st.cta_touched = false;

        // imagem (se você já tem)
        if (!('image_url' in st)) st.image_url = '';
        if (!('image_id' in st)) st.image_id = 0;

        return st;
    }


    function getSlidePlaceholderUrl(i) {
        const st = ensureState(i);

        if (!st.image_seed) {
            // seed estável por slide (uma vez). Pode ser Date.now + i, só não mudar depois.
            st.image_seed = String(Date.now()) + '-' + String(i) + '-' + String(Math.floor(Math.random() * 1e9));
        }

        // picsum com seed fixo -> não muda em re-render
        return `https://picsum.photos/seed/${encodeURIComponent(st.image_seed)}/400/700`;
    }


    function cloneState(st) {
        return st ? JSON.parse(JSON.stringify(st)) : { template: 'template-1', ctaOn: false };
    }

    function insertAt(indexToInsert) {
        const cur = getSlidesCountFromState();
        const max = 15;
        if (cur >= max) return;

        // permite cur+1 (inserir no fim)
        const at = Math.max(1, Math.min(cur + 1, indexToInsert));

        // shift do fim até at
        for (let k = cur + 1; k > at; k--) {
            frameState[k] = cloneState(frameState[k - 1]);
        }

        // slide novo
        frameState[at] = { template: 'template-1', ctaOn: false };
        generateFrames();
    }


    function removeAt(indexToRemove) {
        const cur = getSlidesCountFromState();
        if (cur <= 1) return;

        const at = Math.max(1, Math.min(cur, indexToRemove));

        for (let k = at; k < cur; k++) {
            frameState[k] = cloneState(frameState[k + 1]);
        }
        delete frameState[cur];

        generateFrames();
    }


    function setTemplate(i, tpl) {
        const st = ensureState(i);
        st.template = tpl;

        const preview = document.getElementById(`preview-${i}`);
        if (!preview) return;

        preview.classList.remove('template-1', 'template-2', 'template-3');
        preview.classList.add(tpl);
    }

    function setCTA(i, enabled) {
        const st = ensureState(i);
        st.ctaOn = !!enabled;

        const preview = document.getElementById(`preview-${i}`);
        if (!preview) return;

        preview.classList.toggle('has-cta', st.ctaOn);
        const cta = preview.querySelector('.pga-ws-cta');
        if (cta) cta.style.display = st.ctaOn ? '' : 'none';
    }

    // -------------------------------------------------------
    // generateFrames (render 1x e listeners)
    // -------------------------------------------------------
    window.generateFrames = function () {
        const container = document.getElementById('frames-container');
        if (!container) return;

        const count = getSlidesCountFromState();
        const themeClass = getSelectedTheme();

        // aplica tema no wrapper geral (se existir)
        if (typeof applyTheme === 'function') applyTheme(themeClass);

        // preserva skeleton (se existir)
        const skeleton = container.querySelector('.pga-skeleton');

        // limpa só frames antigos
        container.querySelectorAll('.pga-ws-frame-wrap').forEach(n => n.remove());

        // recoloca skeleton no topo se sumiu
        if (skeleton && !container.contains(skeleton)) container.prepend(skeleton);

        for (let i = 1; i <= count; i++) {
            const st = ensureState(i);

            // -------------------------------
            // DEFAULTS embaralhados (1x)
            // -------------------------------
            if (!st.__defaultsApplied) {
                const tplDefaults = { 2: 'template-3', 4: 'template-2', 6: 'template-1' };
                if (!st.template) st.template = tplDefaults[i] || 'template-1';

                const ctaDefaults = {
                    2: { text: 'Ver o post' },
                    4: { text: 'Leia agora' },
                    6: { text: 'Abrir guia' },
                };

                if (ctaDefaults[i]) {
                    if (!st.cta_text) st.cta_text = ctaDefaults[i].text;
                    if (!st.cta_url) st.cta_url = ctaDefaults[i].url;
                }

                st.__defaultsApplied = 1;
            }

            // -------------------------------
            // Agora calcula render (com defaults já aplicados)
            // -------------------------------
            const title = st.heading || `Slide #${i}`;
            const body = st.body || `Texto inteligente gerado para capturar atenção.`;
            const ctaText = (st.cta_text || '').trim();
            const hasCTA = ctaText.length > 0;

            const img = (st.image_url && st.image_url.trim())
                ? st.image_url.trim()
                : getSlidePlaceholderUrl(i);

            const wrap = document.createElement('div');
            wrap.className = 'pga-ws-frame-wrap';
            wrap.setAttribute('data-slide', String(i));

            wrap.innerHTML = `
      <div id="preview-${i}" class="pga-ws-story-frame ${themeClass} ${st.template || 'template-1'} ${hasCTA ? 'has-cta' : ''}">
        <div class="pga-frame-img" style="background-image:url('${String(img).replace(/'/g, "%27")}')"></div>

        <button type="button" class="pga-ws-del" data-action="del" title="Excluir">✕</button>

        <div class="pga-ws-hover-actions">
          <button type="button" data-action="img" title="Imagem">✨</button>
          <button type="button" class="pga-ws-edit" data-action="edit" data-i="${i}" title="Editar">✎</button>
        </div>

        <div class="pga-ws-frame-content">
          <h3 class="pga-ws-frame-title">${title}</h3>
          <div class="pga-ws-frame-divider" aria-hidden="true"></div>
          <p class="pga-ws-frame-text">${body}</p>
          <a href="${(st.cta_url || '#')}"
             class="pga-ws-cta"
             style="${hasCTA ? '' : 'display:none'}">${ctaText}</a>
        </div>

        <div class="pga-numb" data-pganumber="${i}">${String(i).padStart(2, '0')}</div>
      </div>

      <div class="pga-ws-controls">
        <select class="pga-ws-template-select" data-preview-id="${i}">
          <option value="template-1">Clássico (Baixo)</option>
          <option value="template-2">Editorial (Centro)</option>
          <option value="template-3">Moderno (Topo)</option>
        </select>
      </div>

      <button type="button" data-action="add-after" class="pga-addMore" data-i="${i}" title="Adicionar depois">+</button>
    `;

            container.appendChild(wrap);

            // -------------------------------
            // garante o SELECT refletindo o state
            // (à prova de browser chato)
            // -------------------------------
            const sel = wrap.querySelector('.pga-ws-template-select');
            if (sel) {
                const v = (st.template || 'template-1');
                sel.value = v;
                if (sel.value !== v) {
                    const opt = sel.querySelector(`option[value="${v}"]`);
                    if (opt) opt.selected = true;
                }

                sel.addEventListener('change', (e) => {
                    const id = parseInt(e.target.getAttribute('data-preview-id') || '0', 10);
                    const val = e.target.value || 'template-1';

                    // atualiza state
                    const s2 = ensureState(id);
                    s2.template = val;

                    // atualiza classe do preview
                    const pv = document.getElementById(`preview-${id}`);
                    if (pv) {
                        pv.classList.remove('template-1', 'template-2', 'template-3');
                        pv.classList.add(val);
                    }
                });
            }
        }

        // -------------------------------
        // “pente fino” final: se algo rodar depois e resetar,
        // aqui a gente força de novo.
        // -------------------------------
        container.querySelectorAll('.pga-ws-frame-wrap').forEach(wrap => {
            const i = parseInt(wrap.getAttribute('data-slide') || '0', 10);
            if (!i) return;
            const st = ensureState(i);
            const sel = wrap.querySelector('.pga-ws-template-select');
            if (sel) sel.value = st.template || 'template-1';
        });

        if (typeof applyTheme === 'function') applyTheme(themeClass);
    };

    function updateSelectedPostTitleFromSelect() {
        const el = document.getElementById('pga_selected_post_title');
        if (!el) return;

        // tenta achar o select unitário (o seu não tem id, então pega o primeiro dentro de #unit-selection)
        const sel = document.querySelector('#unit-selection select');
        if (!sel) return;

        const opt = sel.options[sel.selectedIndex];
        const txt = opt ? (opt.textContent || '').trim() : '';
        if (txt && txt.toLowerCase().indexOf('selecione') === -1) {
            el.textContent = txt;
        } else {
            el.textContent = 'Nenhum post selecionado';
        }
    }

    // -------------------------------------------------------
    // Modal Único (shell + panels por mode)
    // -------------------------------------------------------

    function pgaGetModalShell() {
        return document.getElementById('pga_modal');
    }

    function pgaGetModalPanel(mode) {
        const shell = pgaGetModalShell();
        if (!shell) return null;
        return shell.querySelector(`.pga-modal-panel[data-mode="${mode}"]`);
    }

    function pgaModalSetTitle(txt) {
        const t = document.getElementById('pga_modal_title');
        if (t) t.textContent = String(txt || '');
    }

    function pgaModalShowMode(mode) {
        const shell = pgaGetModalShell();
        if (!shell) return;
        shell.querySelectorAll('.pga-modal-panel[data-mode]').forEach(p => {
            p.hidden = (p.getAttribute('data-mode') !== mode);
        });
    }

    function pgaModalOpen(mode, opts = {}) {
        const shell = pgaGetModalShell();
        if (!shell) return;

        // título por mode (se quiser)
        if (mode === 'publish') pgaModalSetTitle(__('Metadados e Publicação', 'alpha-suite'));
        else if (mode === 'story') pgaModalSetTitle(__('Informações do Story', 'alpha-suite'));
        else if (mode === 'slide') pgaModalSetTitle(__('Editar Slide', 'alpha-suite'));
        else if (mode === 'image') pgaModalSetTitle(__('Gerar Imagem do Slide', 'alpha-suite'));

        pgaModalShowMode(mode);

        shell.classList.add('is-open');
        shell.setAttribute('aria-hidden', 'false');

        // guarda contexto básico (ex.: slide)
        if (opts && typeof opts === 'object') {
            shell.dataset.pgaMode = mode;
            if (opts.slide) shell.dataset.pgaSlide = String(opts.slide);
            else delete shell.dataset.pgaSlide;
        }
    }

    function pgaModalClose() {
        const shell = pgaGetModalShell();
        if (!shell) return;
        shell.classList.remove('is-open');
        shell.setAttribute('aria-hidden', 'true');
        delete shell.dataset.pgaMode;
        delete shell.dataset.pgaSlide;
    }

    // bind close (1x)
    function pgaBindUnifiedModalClose() {
        const shell = pgaGetModalShell();
        if (!shell || shell.dataset.pgaCloseBound === '1') return;
        shell.dataset.pgaCloseBound = '1';

        // elementos com data-close=1
        shell.querySelectorAll('[data-close="1"]').forEach(el => {
            el.addEventListener('click', (e) => {
                e.preventDefault();
                pgaModalClose();
            });
        });

        // ESC
        document.addEventListener('keydown', (e) => {
            if (e.key !== 'Escape') return;
            const sh = pgaGetModalShell();
            if (!sh || sh.classList.contains('hidden')) return;
            pgaModalClose();
        });
    }

    pgaBindUnifiedModalClose();

    // -------------------------------------------------------
    // Modal (reuso do DOM unit/multi dentro do modal)
    // -------------------------------------------------------

    function openPublishModal() {
        // modal único
        if (typeof pgaModalOpen === 'function') {
            // abre o panel publish dentro do modal único
            pgaModalOpen('publish');
            return;
        }

        // fallback (se ainda não carregou o manager por algum motivo)
        const shell = document.getElementById('pga_modal');
        if (!shell) return;

        // mostra panel publish
        shell.querySelectorAll('.pga-modal-panel[data-mode]').forEach(p => {
            p.hidden = (p.getAttribute('data-mode') !== 'publish');
        });

        shell.classList.remove('hidden');
        shell.classList.add('is-open');
        shell.setAttribute('aria-hidden', 'false');
    }

    function closePublishModal() {
        pgaModalClose();
    }

    // mantém compat com inline onclick do PHP
    window.openPublishModal = openPublishModal;
    window.closePublishModal = closePublishModal;


    // -------------------------------------------------------
    // REST call
    // -------------------------------------------------------
    async function pgaPostJSON(endpoint, payload) {
        const nonce = PGA_CFG.nonce;

        const r = await fetch(`${REST}${endpoint}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce,
            },
            body: JSON.stringify(payload),
        });

        let data = null;
        try { data = await r.json(); } catch (e) { /* ignore */ }

        if (!r.ok) {
            const msg = (data && (data.message || data.error)) || __('Falha no REST.', 'alpha-suite');
            throw new Error(msg);
        }

        return data;
    }

    window.pgaPostJSON = pgaPostJSON;

    function collectModeAndPosts() {
        if (currentTab === 'multi') {
            const sel = document.getElementById('pga_ws_posts_multi');
            const ids = sel ? Array.from(sel.selectedOptions).map(o => parseInt(o.value, 10)).filter(Boolean) : []; return { mode: 'bulk', post_ids: ids };
        } // unit 
        const unitSel = document.getElementById('pga_ws_post_unit') || document.querySelector('#publish-modal #unit-selection select') || document.querySelector('#unit-selection select');
        const post_id = unitSel ? parseInt(unitSel.value || '0', 10) : 0; return { mode: 'single', post_id };
    }

    function collectModalMeta() {
        const modal = document.getElementById('publish-modal');
        if (!modal) return { title: '', desc: '', start: '' };
        const titleEl = modal.querySelector('input[type="text"]');
        const descEl = modal.querySelector('textarea');
        const startEl = modal.querySelector('#start-date');
        const genImage = modal.document.getElementById('pga_ws_gen_images')?.checked ? 1 : 0;
        return {
            title: (titleEl ? titleEl.value : '').trim(),
            desc: (descEl ? descEl.value : '').trim(),
            start: startEl ? startEl.value : '',
            genImage: genImage ? genImage : 1
        };
    }
    // ------------------------------------------------------- 
    // // Coleta de dados (payload) 
    // // ------------------------------------------------------- 

    function collectSlidesConfig() {
        const arr = []; const frames = document.querySelectorAll('#frames-container .pga-ws-story-frame[id^="preview-"]');
        frames.forEach(
            frame => {
                const idx = parseInt(frame.id.replace('preview-', ''), 10) || 0;
                const template = frame.classList.contains('template-2') ? 'template-2' : frame.classList.contains('template-3') ? 'template-3' : 'template-1';
                const cta_enabled = frame.classList.contains('has-cta');
                arr.push({ index: idx, template, cta_enabled });
            });
        arr.sort((a, b) => a.index - b.index); return arr;
    }

    // -------------------------------------------------------
    // Collect selected posts (Select2 multiple)
    // -------------------------------------------------------
    window.collectSelectedPosts = function () {
        const el = document.getElementById('pga_ws_posts_multi');
        if (!el) return [];

        return Array.from(el.selectedOptions)
            .map(o => parseInt(String(o.value || '').trim(), 10))
            .filter(n => Number.isFinite(n) && n > 0);
    };

    function pgaTomorrowAt(hour = 9, min = 0) {
        const d = new Date();
        d.setDate(d.getDate() + 1);
        d.setHours(hour, min, 0, 0);

        const pad = n => String(n).padStart(2, '0');
        const yyyy = d.getFullYear();
        const mm = pad(d.getMonth() + 1);
        const dd = pad(d.getDate());
        const hh = pad(d.getHours());
        const mi = pad(d.getMinutes());
        return `${yyyy}-${mm}-${dd} ${hh}:${mi}:00`;
    }

    // -------------------------------------------------------
    // Start generation (botão "Gerar" do modal)
    // -------------------------------------------------------
    window.startGeneration = function () {
        try {
            const countEl = document.getElementsByClassName('pga-ws-frame-wrap');
            const slidesCount = parseInt(countEl?.length || '6', 10) || 6;

            const theme = (typeof getSelectedTheme === 'function') ? getSelectedTheme() : 'theme-normal';
            const slides = (typeof collectSlidesConfig === 'function') ? collectSlidesConfig() : [];

            const genImage = document.getElementById('pga_ws_generate_images');
            const gen_images = genImage?.checked ? 1 : 0;

            const startEl = document.getElementById('start-date');
            const locale = document.getElementById('pga_ws_locale').value;
            const startData = startEl?.value ? String(startEl.value).trim() : '';

            const ids = (typeof window.collectSelectedPosts === 'function')
                ? window.collectSelectedPosts()
                : [];

            if (!Array.isArray(ids) || ids.length === 0) {
                Swal.fire({ icon: 'warning', title: __('Selecione postagens', 'alpha-suite') });
                return;
            }

            let publish_start = startData;

            // se não veio data, usa amanhã (só a data)
            if (!publish_start) {
                const d = new Date();
                d.setDate(d.getDate() + 1);
                const yyyy = d.getFullYear();
                const mm = String(d.getMonth() + 1).padStart(2, '0');
                const dd = String(d.getDate()).padStart(2, '0');
                publish_start = `${yyyy}-${mm}-${dd}`; // YYYY-MM-DD
            }

            const payloadBase = {
                mode: 'single',
                post_id: 0,
                locale: locale,
                publish_start,
                meta: { title: '', desc: '', slug: '' },
                genImage: gen_images,
                layout: { theme, slidesCount, slides }
            };

            const totalJobs = ids.length;

            Swal.fire({
                title: __('Gerando stories…', 'alpha-suite'),
                html: `
                    <div style="height:8px;background:#eee;border-radius:4px;overflow:hidden;margin-bottom:8px">
                    <div id="pga_progbar" style="height:8px;width:0%;background:#3b82f6;transition:width .25s ease"></div>
                    </div>

                    <div id="pga_current" style="text-align:center;font-size:12px;color:#6b7280;min-height:16px;">
                    ${__('Preparando geração…', 'alpha-suite')}
                    </div>
                `,
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: async () => {
                    // fecha teu modal antigo depois que o Swal abriu
                    if (typeof closePublishModal === 'function') closePublishModal();

                    const refs = {
                        status: document.getElementById('pga_prog'),
                        bar: document.getElementById('pga_progbar'),
                        cur: document.getElementById('pga_current'),
                        total: totalJobs
                    };

                    const update = (done, text) => {
                        const d = parseInt(done || 0, 10) || 0;

                        if (refs.status) {
                            refs.status.textContent = sprintf(__('Progresso: %d de %d', 'alpha-suite'), d, refs.total);
                        }

                        if (refs.bar) {
                            const pct = refs.total > 0 ? Math.round((d / refs.total) * 100) : 0;
                            refs.bar.style.width = pct + '%';
                        }

                        if (refs.cur && typeof text === 'string') refs.cur.textContent = text;
                    };

                    const created = []; // {story_id, post_id}

                    try {
                        update(0, __('Iniciando…', 'alpha-suite'));

                        for (let i = 0; i < ids.length; i++) {
                            const pid = parseInt(ids[i], 10) || 0;
                            if (!pid) continue;

                            update(i, sprintf(__('Gerando %d de %d…', 'alpha-suite'), i + 1, refs.total));

                            // base = publish_start; agenda 1 por dia e horário aleatório entre 06:00 e 12:00
                            const base = String(publish_start).trim();
                            const isoBase = base.includes(' ') ? base.replace(' ', 'T') : (base + 'T00:00:00');
                            const dt = new Date(isoBase);

                            // se falhar parse, cai pra amanhã
                            if (isNaN(dt.getTime())) {
                                const d = new Date();
                                d.setDate(d.getDate() + 1);
                                dt.setTime(d.getTime());
                                dt.setHours(0, 0, 0, 0);
                            }

                            // + i dias (1 por dia)
                            dt.setDate(dt.getDate() + i);

                            // hora aleatória 06..11 e minuto 0..59 (manhã)
                            dt.setHours(6 + Math.floor(Math.random() * 6), Math.floor(Math.random() * 60), 0, 0);

                            // formata "YYYY-MM-DD HH:MM:00"
                            const yyyy = dt.getFullYear();
                            const mm = String(dt.getMonth() + 1).padStart(2, '0');
                            const dd = String(dt.getDate()).padStart(2, '0');
                            const hh = String(dt.getHours()).padStart(2, '0');
                            const mi = String(dt.getMinutes()).padStart(2, '0');
                            const publish_at = `${yyyy}-${mm}-${dd} ${hh}:${mi}:00`;

                            const itemPayload = { ...payloadBase, post_id: pid, locale: locale, publish_start: publish_at };
                            const data = await pgaPostJSON('/ws/generate', itemPayload);

                            const sid = parseInt((data && (data.story_id || data.id)) || '0', 10) || 0;
                            if (sid) created.push({ story_id: sid, post_id: pid });

                            update(i + 1, sprintf(__('Concluído %d de %d', 'alpha-suite'), i + 1, refs.total));
                        }

                        Swal.close();

                        // monta links de edição
                        const itemsHtml = created.length
                            ? `<ul style="text-align:left;margin:10px 0 0;padding-left:18px;">
                ${created.map(it => {
                                const editUrl = (window.pgaWsEditUrlBase)
                                    ? (window.pgaWsEditUrlBase + String(it.story_id))
                                    : siteUrl + '/wp-admin/admin.php?page=alpha-suite-ws-generator&story_id=' + String(it.story_id);
                                return `<li style="margin:6px 0;">
                                    <a href="${editUrl}" target="_blank" rel="noopener noreferrer"
                                    style="text-decoration:none;font-weight:600;">
                                    ${sprintf(__('Editar story #%d', 'alpha-suite'), it.story_id)}
                                    </a>
                                </li>`;
                            }).join('')}
                            </ul>`
                            : `<div style="margin-top:8px;color:#6b7280;font-size:12px;">
                                ${__('Nenhum story foi criado.', 'alpha-suite')}
                            </div>`;

                        await Swal.fire({
                            icon: 'success',
                            title: __('Concluído!', 'alpha-suite'),
                            html: `
                            <div style="text-align:center;font-size:13px;color:#374151;margin-bottom:6px;">
                                ${sprintf(__('Foram gerados %d stories.', 'alpha-suite'), created.length)}
                            </div>
                            ${itemsHtml}
                            `,
                            confirmButtonColor: '#0f172a'
                        });

                    } catch (e) {
                        Swal.close();
                        Swal.fire({
                            icon: 'error',
                            title: __('Erro', 'alpha-suite'),
                            text: (e && e.message) ? e.message : __('Falha ao gerar.', 'alpha-suite')
                        });
                    }
                }
            });

        } catch (e) {
            Swal.fire({
                icon: 'error',
                title: __('Erro', 'alpha-suite'),
                text: (e && e.message) ? e.message : __('Falha ao iniciar.', 'alpha-suite')
            });
        }
    };

    function pgaToast(icon, title) {
        const SwalRef = window.Swal;
        if (!SwalRef) { alert(title); return Promise.resolve(); }
        return SwalRef.fire({
            toast: true,
            position: 'bottom-end',
            icon,
            title,
            showConfirmButton: false,
            timer: 1600,
            timerProgressBar: true
        });
    }

    function pgaGetStoryId() {
        const wrap = document.querySelector('.pga-wrap.pga-ws');
        return parseInt(wrap?.getAttribute('data-story-id') || '0', 10) || 0;
    }

    function pgaGetPublishAt() {
        const els = Array.from(document.querySelectorAll('#pga_story_publish_at'));
        const visible = els.find(el => el.offsetParent !== null);
        const v = (visible?.value || '').trim(); // YYYY-MM-DDTHH:MM
        return v ? (v.replace('T', ' ') + ':00') : '';
    }


    function pgaCollectModalMetaAndSettings() {
        const meta_title = (document.getElementById('pga_ws_meta_title')?.value || '').trim();
        const meta_desc = (document.getElementById('pga_ws_meta_desc')?.value || '').trim();
        const slug = (document.getElementById('pga_story_slug')?.value || '').trim();

        const catEl = document.getElementById('pga_ws_categories');
        const catVal = catEl ? (parseInt(catEl.value || '0', 10) || 0) : 0;

        const publisher_logo_id = parseInt(document.getElementById('pga_ws_logo_id')?.value || '0', 10) || 0;
        const poster_id = parseInt(document.getElementById('pga_ws_poster_id')?.value || '0', 10) || 0;
        const accent_color = document.getElementById('pga_ws_accent_color')?.value || '#3b82f6';
        const text_color = document.getElementById('pga_ws_text_color')?.value || '#ffffff';
        const locale = (document.getElementById('pga_ws_locale')?.value || 'pt_BR');

        return {
            meta: {
                title: meta_title,
                desc: meta_desc,
                categories: catVal ? [catVal] : [],
                slug: slug || ''
            },
            settings: {
                publisher_logo_id,
                poster_id,
                accent_color,
                text_color,
                locale
            },
            slug
        };
    }

    async function saveStory(mode = 'save') {
        let status;
        if (mode === "publish") status = "publish";
        else status = document.getElementById('pga_story_status').value;
        const story_id = pgaGetStoryId();

        // agendamento
        const publish_at = (() => {
            const v = (document.querySelector('#pga_story_publish_at')?.value || '').trim();
            return v ? v.replace('T', ' ') + ':00' : '';
        })();

        const { meta, settings, slug } = pgaCollectModalMetaAndSettings();

        // ✅ validações (publish/future exigem meta)
        const needMeta = (status === 'publish' || status === 'future');
        if (needMeta) {
            if (!meta.title) { await pgaToast('warning', 'Falta o título'); return; }
            if (!meta.desc) { await pgaToast('warning', 'Falta a descrição'); return; }
        }

        if (status === 'future') {
            if (!publish_at) { await pgaToast('warning', 'Defina a data do agendamento'); return; }
        }

        // ✅ payload: se story_id=0, backend deve criar
        const payload = {
            story_id: story_id || 0,
            status: status,
            publish_at: (status === 'future') ? publish_at : '',
            slug: slug || '',
            meta,
            layout: {
                theme: getSelectedTheme(),
                slidesCount: getSlidesCountFromState(),
                slides: collectLayoutSlidesSimple(),
            },
            settings,
            pages: collectPagesSimple(),
        };

        try {
            const res = await pgaPostJSON('/ws/story/save', payload);
            if (!res?.ok) throw new Error('Falha ao salvar.');
            closePublishModal();

            const newStatus = res?.status || res?.story?.post_status || status;
            const newTitle = meta?.title || document.getElementById('pga_ws_meta_title')?.value || '';

            pgaUpdateHeaderUI({ title: newTitle, status: newStatus });
            pgaUpdateStatusUI();

            const newId = parseInt(res?.story_id || res?.story?.id || '0', 10) || 0;

            await pgaToast('success',
                status === 'trash' ? 'Excluído' :
                    status === 'future' ? 'Salvo' :
                        status === 'publish' ? 'Publicado' :
                            'Salvo'
            );

            // ✅ se era novo (sem story_id) e backend criou -> redireciona pro builder com o ID
            if (!story_id && newId) {
                const url = new URL(window.location.href);
                url.searchParams.set('page', 'alpha-suite-ws-generator');
                url.searchParams.set('story_id', String(newId));
                window.location.href = url.toString();
                return res;
            }

            return res;
        } catch (e) {
            await pgaToast('error', e?.message || 'Falha ao salvar.');
            throw e;
        }
    }

    function deleteStoryRedirect(trashUrl) {
        const back = `${window.location.origin}/alpha/wp-admin/admin.php?page=alpha-suite-ws-generator`;

        // se não veio URL, volta pro builder
        if (!trashUrl) {
            window.location.href = back;
            return;
        }

        // Se alguém te passar action=delete por acidente, corrige pra trash
        // (pra nunca mais deletar definitivo sem querer)
        try {
            const u = new URL(trashUrl, window.location.href);

            // força action=trash sempre
            if ((u.searchParams.get('action') || '').toLowerCase() !== 'trash') {
                u.searchParams.set('action', 'trash');
            }

            // garante redirect_to
            if (!u.searchParams.get('redirect_to')) {
                u.searchParams.set('redirect_to', back);
            }

            trashUrl = u.toString();
        } catch (e) {
            // se deu ruim parseando, volta pro builder
            window.location.href = back;
            return;
        }

        const go = () => { window.location.href = trashUrl; };

        if (window.Swal) {
            Swal.fire({
                icon: 'warning',
                title: 'Excluir story?',
                html: 'Ele será movido para a <b>lixeira</b>.',
                showCancelButton: true,
                confirmButtonText: 'Excluir',
                cancelButtonText: 'Cancelar'
            }).then(r => { if (r.isConfirmed) go(); });
        } else {
            if (confirm('Excluir story? Ele será movido para a lixeira.')) go();
        }
    }


    window.deleteStoryRedirect = deleteStoryRedirect;
    window.saveStory = saveStory;

    document.getElementById('pga_slide_pick_image')?.addEventListener('click', (e) => {
        e.preventDefault();
        pickSlideImage();
    });

    document.getElementById('pga_slide_clear_image')?.addEventListener('click', (e) => {
        e.preventDefault();
        document.getElementById('pga_slide_image_id').value = '0';
        const prev = document.getElementById('pga_slide_image_preview');
        if (prev) {
            prev.src = '';
            prev.style.display = 'none';
            prev.dataset.tmpUrl = '';
            prev.dataset.tmpW = '';
            prev.dataset.tmpH = '';
        }
    });

    function pgaSetStatusBadge(status) {
        const badge = document.getElementById('pga_story_status_badge');
        if (!badge) return;

        badge.classList.remove('is-draft', 'is-future', 'is-publish');

        if (status === 'publish') {
            badge.classList.add('is-publish');
            badge.textContent = 'Publicado';
        } else if (status === 'future') {
            badge.classList.add('is-future');
            badge.textContent = 'Agendado';
        } else {
            badge.classList.add('is-draft');
            badge.textContent = 'Rascunho';
        }
    }

    function pgaStatusUI(statusRaw) {
        const st = (statusRaw || 'draft').toString().trim();

        if (st === 'publish') {
            return { label: 'Publicado', cls: 'is-publish', data: 'publish' };
        }
        if (st === 'future') {
            return { label: 'Agendado', cls: 'is-future', data: 'future' };
        }
        // trash: geralmente você vai redirecionar pra lista, mas deixo seguro
        if (st === 'trash') {
            return { label: 'Lixeira', cls: 'is-trash', data: 'trash' };
        }
        return { label: 'Rascunho', cls: 'is-draft', data: 'draft' };
    }

    /**
     * Atualiza header sem refresh.
     * - titleEl: #pga_story_title_header
     * - badgeEl: #pga_status_badge
     */
    function pgaUpdateHeaderUI({ title, status }) {
        const titleEl = document.getElementById('pga_story_title_header');
        if (titleEl) {
            const t = (title || '').trim();
            if (t) titleEl.textContent = t;
        }

        const badgeEl = document.getElementById('pga_status_badge');
        if (badgeEl) {
            const ui = pgaStatusUI(status);

            badgeEl.textContent = ui.label;
            badgeEl.setAttribute('data-status', ui.data);

            // troca classes
            badgeEl.classList.remove('is-draft', 'is-future', 'is-publish', 'is-trash');
            badgeEl.classList.add(ui.cls);
        }
    }
    function pgaUpdateStatusUI() {
        const sel = document.getElementById('pga_story_status');
        const badge = document.getElementById('pga_story_status_badge');
        const row = document.getElementById('pga_story_future_row');

        if (!sel) return;

        const v = (sel.value || 'draft').trim();

        if (row) row.style.display = (v === 'future') ? '' : 'none';

        if (badge) {
            // texto
            badge.textContent =
                (v === 'publish') ? 'Publicado' :
                    (v === 'future') ? 'Agendado' :
                        'Rascunho';

            // classe (ajusta conforme teu CSS)
            badge.classList.remove('is-draft', 'is-future', 'is-publish');
            badge.classList.add(v === 'publish' ? 'is-publish' : v === 'future' ? 'is-future' : 'is-draft');

            // se tu também usa data-status em algum lugar
            badge.setAttribute('data-status', v);
        }
    }

    $(function () {
        // inicializa badge com o select default
        const st = document.getElementById('pga_story_status');
        if (st) pgaSetStatusBadge(st.value);
    });

    function pgaToggleFutureDateUI() {
        const st = document.getElementById('pga_story_status');
        const row = document.getElementById('pga_story_future_row');
        if (!st || !row) return;
        row.style.display = (st.value === 'future') ? '' : 'none';
    }

    const st = document.getElementById('pga_story_status');
    if (st) {
        st.addEventListener('change', () => {
            pgaSetStatusBadge(st.value);
            pgaToggleFutureDateUI();
        });
        // inicial
        pgaToggleFutureDateUI();
    }

    function pgaSlideNoticeShow(msg) {
        const box = document.getElementById('pga_slide_notice');
        const txt = document.getElementById('pga_slide_notice_text');
        if (!box || !txt) return;
        txt.textContent = msg || '';
        box.style.display = msg ? '' : 'none';
    }

    function pgaSlideNoticeHide() {
        pgaSlideNoticeShow('');
    }


    async function loadStoryIntoUI(storyId) {
        showSkeleton();
        try {
            const data = await pgaGetJSON(`/ws/story?story_id=${storyId}`);

            if (!data || !data.ok) throw new Error('Falha ao carregar story.');

            // 1) set slide count
            const count = parseInt(data?.layout?.slidesCount || (data.pages?.length || 6), 10) || 6;
            const countEl = document.getElementById('slide-count');
            if (countEl) countEl.value = count;

            // 2) set theme radio
            const theme = data?.layout?.theme || 'theme-normal';
            const radio = document.querySelector(`#pga_tabs input[name="pga_ws_theme"][value="${theme}"]`);
            if (radio) radio.checked = true;

            applyTheme(theme);

            // 3) preencher inputs do modal (meta + settings)
            const t = document.getElementById('pga_ws_meta_title');
            const d = document.getElementById('pga_ws_meta_desc');
            if (t) t.value = (data?.meta?.title || '').trim();
            if (d) d.value = (data?.meta?.desc || '').trim();

            const accent = document.getElementById('pga_ws_accent_color');
            const textc = document.getElementById('pga_ws_text_color');
            if (accent && data?.settings?.accent_color) accent.value = data.settings.accent_color;
            if (textc && data?.settings?.text_color) textc.value = data.settings.text_color;

            // logo preview
            if (data?.settings?.publisher_logo_id) {
                const logoId = parseInt(data.settings.publisher_logo_id, 10) || 0;
                const hid = document.getElementById('pga_ws_logo_id');
                if (hid) hid.value = String(logoId);
                // se quiser, você pode criar um endpoint pra pegar URL por ID
                // por enquanto: esconde preview se não tiver URL
            }

            // 4) montar frameState com base em layout.slides OU pages
            // limpa estado anterior
            Object.keys(frameState).forEach(k => delete frameState[k]);

            const pages = Array.isArray(data.pages) ? data.pages : [];
            const slides = Array.isArray(data?.layout?.slides) ? data.layout.slides : [];

            const loc = (document.getElementById('pga_ws_locale') || document.getElementById('pga_story_locale'));
            if (loc && data?.settings?.locale) loc.value = data.settings.locale;

            for (let i = 1; i <= count; i++) {
                const st = ensureState(i);

                // tenta pegar config do slide salvo
                const conf = slides.find(s => parseInt(s.index, 10) === i);
                if (conf) {
                    st.template = conf.template || 'template-1';
                    st.ctaOn = !!conf.cta_enabled;
                } else {
                    // fallback: se pages tiver cta_text, liga
                    const pg = pages[i - 1];
                    st.template = 'template-1';
                    st.ctaOn = !!(pg && pg.cta_text);
                }

                // salva também conteúdo da página dentro do state (pra preencher preview)
                const pg = pages[i - 1] || {};

                if (conf) {
                    st.template = conf.template || 'template-1';
                    st.ctaOn = !!conf.cta_enabled;
                } else {
                    // 👇 fallback: usa template salvo na página
                    st.template = (pg.template || 'template-1');
                    st.ctaOn = !!(pg.cta_text && pg.cta_text.trim().length > 0);
                }

                st.heading = (pg.heading || '').trim();
                st.body = (pg.body || '').trim();
                st.cta_text = (pg.cta_text || '').trim();
                st.cta_url = (pg.cta_url || '').trim();

                // ✅ regra nova
                st.ctaOn = (st.cta_text.length > 0);
                st.image_url = (pg.image_url || pg.image || '').trim(); // se você salvar depois
            }

            // 5) renderizar frames já com conteúdo
            window.PGA_STORY_READY = true;
            generateFrames();

            for (let i = 1; i <= count; i++) {
                const st = ensureState(i);

                const sel = document.querySelector(`.pga-ws-template-select[data-preview-id="${i}"]`);
                if (sel) sel.value = st.template || 'template-1';

                setTemplate(i, st.template || 'template-1');
                setCTA(i, !!st.ctaOn);
            }
            hideSkeleton();

        } catch (e) {
            hideSkeleton();

            const backUrl = siteUrl + '/wp-admin/admin.php?page=alpha-suite-ws-generator';

            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: e?.message || 'Falha ao carregar story.',
                timer: 3000,
                timerProgressBar: true,
                showConfirmButton: false,
                didClose: () => {
                    window.location.href = backUrl;
                }
            });
        }

    }

    // GET helper (igual seu post helper)
    async function pgaGetJSON(endpoint) {
        const r = await fetch(`${REST}${endpoint}`, {
            method: 'GET',
            headers: { 'X-WP-Nonce': NONCE }
        });

        let data = null;
        try { data = await r.json(); } catch (e) { }

        if (!r.ok) {
            const msg = (data && (data.message || data.error)) || 'Falha no REST.';
            throw new Error(msg);
        }
        return data;
    }


    function pickSlideImage() {
        if (!window.wp || !wp.media) {
            alert('wp.media não disponível');
            return;
        }

        const frame = wp.media({
            title: 'Selecionar imagem do slide',
            button: { text: 'Usar esta imagem' },
            multiple: false
        });

        frame.on('select', () => {
            const att = frame.state().get('selection').first().toJSON();
            const id = parseInt(att.id || 0, 10) || 0;
            const url = att.url || '';

            const w = parseInt(att.width || 0, 10) || 0;
            const h = parseInt(att.height || 0, 10) || 0;

            document.getElementById('pga_slide_image_id').value = String(id);

            const prev = document.getElementById('pga_slide_image_preview');
            if (prev) {
                prev.src = url;
                prev.style.display = url ? '' : 'none';
                prev.dataset.tmpUrl = url || '';
                prev.dataset.tmpW = String(w);
                prev.dataset.tmpH = String(h);
            }

            // opcional: guardar uma URL temporária em dataset
            prev.dataset.tmpUrl = url;
            const st = ensureState(PGA_ACTIVE_SLIDE);

            const imgId = parseInt(document.getElementById('pga_slide_image_id')?.value || '0', 10) || 0;

            const hasImage = (imgId > 0 && url !== '');

            if (hasImage) {
                st.image_id = imgId;
                st.image_url = url;
                st.image_w = w;
                st.image_h = h;
            } else {
                st.image_id = 0;
                st.image_url = '';
                st.image_w = 0;
                st.image_h = 0;

                // opcional: se quiser avisar “sem imagem”
                pgaSlideNoticeHide();
            }

        });

        frame.open();
    }

    (function () {
        function bindMediaPicker(pickBtnId, clearBtnId, inputId, previewImgId, opts = {}) {
            const pickBtn = document.getElementById(pickBtnId);
            const clearBtn = document.getElementById(clearBtnId);
            const input = document.getElementById(inputId);
            const preview = document.getElementById(previewImgId);

            if (!pickBtn || !input) return;

            let frame = null;

            pickBtn.addEventListener('click', (e) => {
                e.preventDefault();

                if (!window.wp || !wp.media) {
                    return;
                }

                // reusa frame (melhor UX)
                if (frame) {
                    frame.open();
                    return;
                }

                frame = wp.media({
                    title: opts.title || 'Selecionar imagem',
                    button: { text: opts.button || 'Usar esta imagem' },
                    library: { type: opts.type || 'image' },
                    multiple: false
                });

                frame.on('select', () => {
                    const att = frame.state().get('selection').first()?.toJSON();
                    if (!att || !att.id) return;

                    input.value = String(att.id);

                    // tenta uma URL boa (full ou tamanho preferido)
                    const url =
                        (att.sizes && opts.size && att.sizes[opts.size] && att.sizes[opts.size].url) ||
                        (att.sizes && att.sizes.full && att.sizes.full.url) ||
                        att.url ||
                        '';

                    if (preview && url) {
                        preview.src = url;
                        preview.style.display = '';
                    }

                    // opcional: guarda dimensões / url temporária
                    if (preview) {
                        preview.dataset.tmpUrl = url || '';
                        preview.dataset.tmpW = String(att.width || '');
                        preview.dataset.tmpH = String(att.height || '');
                    }
                });

                frame.open();
            });

            if (clearBtn) {
                clearBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    input.value = '0';

                    if (preview) {
                        preview.src = '';
                        preview.style.display = 'none';
                        preview.dataset.tmpUrl = '';
                        preview.dataset.tmpW = '';
                        preview.dataset.tmpH = '';
                    }
                });
            }
        }

        // --- Publish: Logo + Poster ---
        document.addEventListener('DOMContentLoaded', () => {
            bindMediaPicker(
                'pga_ws_pick_logo',
                'pga_ws_clear_logo',
                'pga_ws_logo_id',
                'pga_ws_logo_preview',
                {
                    title: 'Selecionar logo do publisher',
                    button: 'Usar como logo',
                    type: 'image',
                    size: 'medium' // preview leve
                }
            );

            bindMediaPicker(
                'pga_ws_pick_poster',
                'pga_ws_clear_poster',
                'pga_ws_poster_id',
                'pga_ws_poster_preview',
                {
                    title: 'Selecionar thumbnail',
                    button: 'Usar como thumbnail',
                    type: 'image',
                    size: 'large' // preview maior
                }
            );
        });
    })();

    let PGA_ACTIVE_SLIDE = 0;

    window.pgaOpenImageModal = function (slideNum1) {
        PGA_ACTIVE_SLIDE = parseInt(slideNum1 || '0', 10) || 0;
        if (!PGA_ACTIVE_SLIDE) return;

        const m = pgaGetModalPanel('image');
        if (!m) return;

        pgaModalOpen('image', { slide: PGA_ACTIVE_SLIDE });
        const brief = document.getElementById('pga_img_brief');
        if (brief) brief.value = '';


    };

    function pgaCloseImageModal() {
        pgaModalClose();
    }

    document.getElementById('pga_img_generate_btn')?.addEventListener('click', async () => {
        try {
            const storyId = parseInt(document.querySelector('.pga-wrap.pga-ws')?.getAttribute('data-story-id') || '0', 10) || 0;
            if (!storyId) throw new Error('Story não encontrado (salve/abra um story primeiro).');

            const slideNum = PGA_ACTIVE_SLIDE;           // 1..N
            const index = Math.max(0, slideNum - 1);     // 0-based

            const st = ensureState(slideNum);

            const heading = (st.heading || '').trim();
            const body = (st.body || '').trim();
            const brief = (document.getElementById('pga_img_brief')?.value || '').trim();

            // ✅ regra: precisa ter algum contexto
            if (!heading && !body && !brief) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Falta contexto',
                    html: 'Nada cadastrado no <b>título</b> ou no <b>conteúdo</b> deste slide.<br>Por favor, insira um <b>prompt</b> no campo acima ou preencha o título/descrição do slide.',
                    confirmButtonText: 'Ok',
                });
                return;
            }

            Swal.fire({
                title: 'Gerando imagem…',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: async () => {
                    const data = await window.pgaPostJSON('/ws/slide/image/generate', { story_id: storyId, index, brief, force: 1 });

                    if (data.mode === 'pick' && Array.isArray(data.options)) {
                        // render as opções dentro do Swal
                        const html = `
                            <div class="pga-pick-grid">
                            ${data.options.map((o, k) => `
                                <button type="button" class="pga-pick-item" data-k="${k}">
                                <img src="${o.thumb}" alt="" style="width:100%;height:auto;border-radius:10px" />
                                <div style="margin-top:8px;font-weight:600">Usar esta</div>
                                </button>
                            `).join('')}
                            </div>
                        `;

                        await Swal.fire({
                            title: 'Selecione uma imagem',
                            html,
                            showConfirmButton: false,
                            showCancelButton: true,
                            cancelButtonText: 'Cancelar',
                            didOpen: () => {
                                document.querySelectorAll('.pga-pick-item').forEach(btn => {
                                    btn.addEventListener('click', async () => {
                                        const k = parseInt(btn.getAttribute('data-k') || '0', 10);
                                        const opt = data.options[k];
                                        if (!opt) return;

                                        Swal.showLoading();

                                        const saved = await window.pgaPostJSON('/ws/slide/image/select', {
                                            story_id: storyId,
                                            index,
                                            url: opt.full,
                                            alt: (st.heading || '').trim()
                                        });

                                        st.image_id = parseInt(saved.image_id || '0', 10) || 0;
                                        st.image_url = (saved.image_url || '').trim();

                                        generateFrames();
                                        Swal.close();
                                        pgaCloseImageModal();
                                    });
                                });
                            }
                        });

                        return; // importante
                    }

                    Swal.close();
                    // modo direto (IA)
                    st.image_id = parseInt(data.image_id || 0, 10) || 0;
                    st.image_url = (data.image_url || '').trim();
                    generateFrames();
                    pgaCloseImageModal();

                }
            });

        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Erro', text: e?.message || 'Falha ao gerar imagem.' });
        }
    });

    window.pgaOpenSlideModal = function (i) {
        PGA_ACTIVE_SLIDE = parseInt(i || '0', 10) || 0;
        if (!PGA_ACTIVE_SLIDE) return;

        // se edição, espera carregar via REST (se você usa isso)
        if (isEditMode() && window.PGA_STORY_READY === false) {
            Swal.fire({ icon: 'info', title: 'Carregando…', text: 'Aguarde os dados do story.' });
            return;
        }

        const st = ensureState(PGA_ACTIVE_SLIDE);

        // preencher inputs (STATE -> UI)
        const headingEl = document.getElementById('pga_slide_heading');
        const bodyEl = document.getElementById('pga_slide_body');
        const ctaTextEl = document.getElementById('pga_slide_cta_text');
        const ctaUrlEl = document.getElementById('pga_slide_cta_url');
        const ctaOnEl = document.getElementById('pga_slide_cta_on');

        if (headingEl) headingEl.value = st.heading || '';
        if (bodyEl) bodyEl.value = st.body || '';
        if (ctaTextEl) ctaTextEl.value = st.cta_text || '';
        if (ctaUrlEl) ctaUrlEl.value = st.cta_url || '';

        const hasCTA = ((st.cta_text || '').trim().length > 0);
        if (ctaOnEl) ctaOnEl.checked = hasCTA;

        // imagem (STATE -> UI)
        const imgIdEl = document.getElementById('pga_slide_image_id');
        const imgPrev = document.getElementById('pga_slide_image_preview');

        if (imgIdEl) imgIdEl.value = String(st.image_id || 0);

        const url = (st.image_url && String(st.image_url).trim())
            ? String(st.image_url).trim()
            : '';

        if (imgPrev) {
            imgPrev.src = url;
            imgPrev.style.display = url ? '' : 'none';
        }

        // ✅ abre o MODAL ÚNICO no mode slide
        pgaModalOpen('slide', { slide: PGA_ACTIVE_SLIDE });
    };

    window.pgaCloseSlideModal = function () {
        PGA_ACTIVE_SLIDE = 0;
        pgaModalClose();
    };

    window.pgaSaveSlideModal = function () {
        if (!PGA_ACTIVE_SLIDE) return;

        const st = ensureState(PGA_ACTIVE_SLIDE);

        st.heading = (document.getElementById('pga_slide_heading')?.value || '').trim();
        st.body = (document.getElementById('pga_slide_body')?.value || '').trim();
        st.cta_text = (document.getElementById('pga_slide_cta_text')?.value || '').trim();
        st.cta_url = (document.getElementById('pga_slide_cta_url')?.value || '').trim();

        // CTA = tem texto (se não tem texto, limpa url)
        if (!st.cta_text) st.cta_url = '';

        // imagem
        st.image_id = parseInt(document.getElementById('pga_slide_image_id')?.value || '0', 10) || 0;
        // st.image_url você setaria quando escolher a imagem via wp.media

        st.heading_touched = true;
        st.body_touched = true;
        st.cta_touched = true;

        // re-render pra refletir no frame
        generateFrames();

        pgaModalClose();
    };

    function applyModeUI() {
        const edit = isEditMode();
        const tabsMode = document.querySelector('.pga-ws-tabs');
        if (tabsMode) tabsMode.style.display = edit ? 'none' : '';

        // se for edição: por enquanto não mexe no resto
        // depois tu usa REST pra carregar dados do story e preencher frames
    }

    function getSlidesCountFromState() {
        let max = 0;
        Object.keys(frameState).forEach(k => {
            const n = parseInt(k, 10);
            if (n > max) max = n;
        });
        return Math.max(1, max || 1);
    }

    function collectPagesSimple() {
        const n = getSlidesCountFromState();
        const pages = [];

        for (let i = 1; i <= n; i++) {
            const st = ensureState(i);

            pages.push({
                index: i,
                heading: (st.heading || '').trim(),
                body: (st.body || '').trim(),
                cta_text: (st.cta_text || '').trim(),
                cta_url: (st.cta_url || '').trim(),
                template: st.template || 'template-1',
                // imagem (se tiver)
                image_id: parseInt(st.image_id || 0, 10) || 0,
                image_url: (st.image_url || '').trim(),
            });
        }
        return pages;
    }

    function collectLayoutSlidesSimple() {
        const n = getSlidesCountFromState();
        const slides = [];

        for (let i = 1; i <= n; i++) {
            const st = ensureState(i);
            slides.push({
                index: i,
                template: st.template || 'template-1',
                cta_enabled: !!(st.ctaOn || (st.cta_text && String(st.cta_text).trim().length > 0)),
            });
        }
        return slides;
    }

    // -------------------------------------------------------
    // Init
    // -------------------------------------------------------
    $(function () {
        applyModeUI();

        document.querySelectorAll('input[name="pga_ws_theme"]').forEach(r => {
            r.addEventListener('change', () => applyTheme(getSelectedTheme()));
        });

        const p = new URLSearchParams(window.location.search || '');
        const storyId = parseInt(p.get('story_id') || '0', 10);

        if (storyId > 0) {
            loadStoryIntoUI(storyId);
        } else {
            const scope = pgaGetModalPanel('publish') || document;
            // if (scope.querySelector('#unit-selection') && scope.querySelector('#multi-selection')) {
            //     switchTab('unit');
            // }

            // default 6 slides no gerador
            if (!Object.keys(frameState).length) {
                for (let i = 1; i <= 6; i++) ensureState(i);
            }
            generateFrames();
        }

        // const unitSel = document.querySelector('#unit-selection select');
        // if (unitSel) {
        //     unitSel.addEventListener('change', updateSelectedPostTitleFromSelect);
        //     updateSelectedPostTitleFromSelect();
        // }

        const frames = document.getElementById('frames-container');
        if (frames) {
            frames.addEventListener('click', (e) => {
                const actionEl = e.target.closest('[data-action]');
                if (!actionEl) return;

                const action = actionEl.getAttribute('data-action') || '';
                if (!action) return;

                const wrap = e.target.closest('.pga-ws-frame-wrap');
                if (!wrap) return;

                const i = parseInt(wrap.getAttribute('data-slide') || '0', 10) || 0;
                if (!i) return;

                e.preventDefault();
                e.stopPropagation();

                if (action === 'add-before') insertAt(i);
                else if (action === 'add-after') insertAt(i + 1);
                else if (action === 'del') removeAt(i);
                else if (action === 'edit') pgaOpenSlideModal(i);
                else if (action === 'img') pgaOpenImageModal(i);
            });
        }
    });


})(jQuery);

jQuery(function ($) {
    const $multi = $('#pga_ws_posts_multi');
    if (!$multi.length) return;

    // ✅ pega o modal pai (ajusta o seletor pro teu modal real)
    const $modal = $multi.closest('.swal2-popup').length
        ? $multi.closest('.swal2-popup')
        : $multi.closest('.pga-modal, .pga-popup, .pga-ws-modal, .pga-modal-wrap');

    $multi.select2({
        width: '100%',
        placeholder: $multi.data('placeholder') || 'Buscar postagens...',
        closeOnSelect: false,
        allowClear: true,
        minimumResultsForSearch: 0,

        // ✅ isto resolve "abre atrás do modal"
        dropdownParent: $modal.length ? $modal : $(document.body)
    });
});
