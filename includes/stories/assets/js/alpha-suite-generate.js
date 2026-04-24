jQuery(function ($) {
  const $btn = $('#alpha_ai_generate_now');
  const $status = $('#alpha_ai_generate_now_status');

  if (!$btn.length || !$status.length) return;

  const btn = $btn[0];
  const st = $status[0];

  const LICENSE_VALID = st.dataset.licenseOk === '1';
  const LICENSE_MSG = st.dataset.licenseMessage || '';

  const { licenseUrl } = window.PGA_Stories || {};

  async function ensureSwal() {
    for (let i = 0; i < 30; i++) {
      if (window.Swal) return true;
      await new Promise(r => setTimeout(r, 100));
    }
    return false;
  }

  // função auxiliar pra chamar o REST de imagem de UM slide
  async function gerarImagemSlide(postId, index, total, attempt = 1, maxAttempts = 3) {
    const baseMsg = `Gerando imagem do slide ${index + 1} de ${total}`;
    const msg = attempt > 1
      ? `${baseMsg} (tentativa ${attempt}/${maxAttempts})...`
      : `${baseMsg}...`;

    if (window.Swal && Swal.isVisible()) {
      Swal.update({
        title: 'Gerando imagens…',
        html: msg,
      });
    } else {
      $status.text(msg);
    }

    let res, json = null;

    try {
      res = await fetch(wpApiSettings.root + 'pga/v1/stories/image', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': wpApiSettings.nonce
        },
        body: JSON.stringify({
          post_id: postId,
          index: index
        })
      });

      try {
        json = await res.json();
      } catch (e) {
        json = null;
      }
    } catch (e) {
      console.error('Erro de rede ao gerar imagem do slide', index, e);
      // erro de rede genérico → tenta de novo até maxAttempts
      if (attempt < maxAttempts) {
        await new Promise(r => setTimeout(r, 1500 * attempt));
        return gerarImagemSlide(postId, index, total, attempt + 1, maxAttempts);
      }
      return null;
    }

    // sucesso total
    if (res.ok && json && json.ok === true) {
      return json;
    }

    console.error('Erro ao gerar imagem do slide', index, json || res);

    // se for erro HTTP do Pollinations (502/503/530), tenta mais algumas vezes
    if (
      attempt < maxAttempts &&
      json &&
      json.code === 'pga_pollinations_http' &&
      /HTTP\s+(502|503|530)/.test(String(json.message || ''))
    ) {
      await new Promise(r => setTimeout(r, 1500 * attempt));
      return gerarImagemSlide(postId, index, total, attempt + 1, maxAttempts);
    }

    // não dou throw pra não matar o loop geral
    return json;
  }

  // click principal – **UM handler só**
  $btn.on('click', async function (e) {
    e.preventDefault();

    const postId = parseInt($('#post_ID').val(), 10) || 0;
    if (!postId) {
      alert('ID do post inválido.');
      return;
    }

    // 0) Licença
    if (!LICENSE_VALID) {
      if (await ensureSwal()) {
        Swal.fire({
          icon: 'info',
          title: 'Licença necessária',
          html:
            (LICENSE_MSG ? '<p>' + LICENSE_MSG + '</p>' : '') +
            'Para gerar o Web Story, ative sua licença em <strong>Alpha Stories → Licença</strong>.<br><br>' +
            '<a class="button button-primary" href="' + (licenseUrl || '#') + '">Abrir configurações de licença</a>',
          confirmButtonText: 'OK',
        });
      } else {
        alert(LICENSE_MSG || 'Para gerar o Web Story, ative sua licença em Alpha Stories → Licença.');
      }
      return;
    }

    $btn.prop('disabled', true);
    $status.text('Gerando esboço do Web Story...');

    try {
      // 1) Modal de loading (ou fallback no span)
      if (await ensureSwal()) {
        Swal.fire({
          title: 'Gerando esboço…',
          html: 'Chamando IA para criar o outline do Web Story…',
          allowOutsideClick: false,
          allowEscapeKey: false,
          showConfirmButton: false,
          didOpen: () => Swal.showLoading(),
        });
      }

      // 2) GERA OUTLINE / ESBOÇO (texto + prompt) – 1 CHAMADA REST
      const resOutline = await fetch(wpApiSettings.root + 'pga/v1/stories/generate', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': wpApiSettings.nonce
        },
        body: JSON.stringify({ post_id: postId })
      });

      const outline = await resOutline.json();

      if (!resOutline.ok || !outline || outline.ok !== true) {
        console.error('Erro ao gerar outline:', outline);
        if (window.Swal && Swal.isVisible()) {
          Swal.fire({
            icon: 'error',
            title: 'Erro ao gerar esboço',
            text: outline && outline.message ? outline.message : 'Falha ao gerar esboço do Web Story.',
            confirmButtonText: 'OK',
          });
        } else {
          $status.text('Erro ao gerar esboço do Web Story.');
        }
        $btn.prop('disabled', false);
        return;
      }

      const targetId = outline.target_id || postId;
      const total = outline.count || 0;

      if (!total) {
        if (window.Swal && Swal.isVisible()) {
          Swal.fire({
            icon: 'info',
            title: 'Esboço gerado sem páginas',
            text: 'A IA não retornou páginas para este story.',
            confirmButtonText: 'OK',
          });
        } else {
          $status.text('Esboço gerado, mas sem páginas. Nada para gerar imagem.');
        }
        $btn.prop('disabled', false);
        return;
      }

      const infoOutline = `Esboço pronto (${total} slides). Agora vou gerar as imagens, uma por vez…`;
      if (window.Swal && Swal.isVisible()) {
        Swal.update({
          title: 'Esboço pronto',
          html: infoOutline,
        });
      } else {
        $status.text(infoOutline);
      }

      // 3) GERA SLIDE A SLIDE – 1 POR VEZ, AGUARDANDO COM await
      for (let i = 0; i < total; i++) {
        await gerarImagemSlide(targetId, i, total);
      }

      // 4) SÓ AQUI mostramos "completo"
      if (window.Swal) {
        const viewUrl = outline.view_url || '';
        const editUrl = outline.edit_url || '';
        let html = 'Texto e imagens gerados com sucesso.';
        const links = [];

        if (editUrl) {
          links.push('<a href="' + editUrl + '" target="_blank" rel="noreferrer">Editar story</a>');
        }
        if (viewUrl) {
          links.push('<a href="' + viewUrl + '" target="_blank" rel="noreferrer">Ver story</a>');
        }
        if (links.length) {
          html += '<br>' + links.join(' · ');
        }

        Swal.fire({
          icon: 'success',
          title: 'Story completo!',
          html,
          confirmButtonText: 'OK',
        });
      } else {
        $status.text('Story completo! Texto e imagens gerados.');
      }

    } catch (err) {
      console.error('Erro geral na geração de story:', err);
      if (window.Swal) {
        Swal.fire({
          icon: 'error',
          title: 'Ops…',
          text: err.message || 'Erro inesperado ao gerar o Web Story.',
          confirmButtonText: 'OK',
        });
      } else {
        $status.text('Erro inesperado ao gerar o Web Story.');
      }
    } finally {
      $btn.prop('disabled', false);
    }
  });

  // IMPORTANTE: **não** adicionar mais listeners no btn aqui embaixo.
  // Os "scripts antigos" que chamavam ajaxUrl/alpha_ai_generate_now
  // devem ser removidos ou comentados para não duplicar o fluxo.
});
