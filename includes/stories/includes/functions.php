<?php

// alpha-storys.php (principal)
if (! defined('ABSPATH')) exit;
// === 1) Constrói as páginas a partir do conteúdo: AGORA divide por <hr> (linha horizontal) ===
function alpha_build_storys_pages_from_content($html)
{
  $pages   = [];
  $content = do_shortcode($html);

  // 1) Normaliza separadores do Gutenberg em marcador
  // Forma com bloco aberto/fechado:
  $content = preg_replace(
    '/<!--\s*wp:separator[^>]*-->[\s\S]*?<!--\s*\/wp:separator\s*-->/i',
    '[[ALPHA_SPLIT]]',
    $content
  );
  // Forma autocontenida:
  $content = preg_replace(
    '/<!--\s*wp:separator\b[^>]*\/\s*-->/i',
    '[[ALPHA_SPLIT]]',
    $content
  );
  // Qualquer <hr> vira marcador
  $content = preg_replace('/<hr\b[^>]*>/i', '[[ALPHA_SPLIT]]', $content);

  $has_marker = (strpos($content, '[[ALPHA_SPLIT]]') !== false);

  // Helper: dentro de um pedaço (entre HRs), dividir por H2 múltiplos
  $build_from_chunk = function ($chunk_html) {
    $chunk_html = trim($chunk_html);
    if ($chunk_html === '') return [];

    // quebra preservando <h2> como delimitadores
    $parts = preg_split(
      '/(<h2[^>]*>.*?<\/h2>)/is',
      $chunk_html,
      -1,
      PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
    );

    $pages_local = [];
    $current_title = '';
    $current_body  = '';

    $saw_h2 = false;
    foreach ($parts as $piece) {
      if (preg_match('/^<h2[^>]*>(.*?)<\/h2>$/is', $piece, $m)) {
        // fecha a página anterior (se tiver conteúdo)
        if ($current_title || trim(wp_strip_all_tags($current_body)) !== '') {
          $pages_local[] = alpha_finalize_section($current_title, $current_body);
          $current_body  = '';
        }
        $saw_h2 = true;
        $current_title = wp_strip_all_tags($m[1]);
      } else {
        $current_body .= $piece;
      }
    }
    // Última página do pedaço
    if ($saw_h2 || trim(wp_strip_all_tags($current_body)) !== '') {
      $pages_local[] = alpha_finalize_section($current_title, $current_body);
    }

    // Se não havia H2 nesse chunk, gera uma única página com o corpo todo
    if (!$saw_h2 && empty($pages_local)) {
      $pages_local[] = alpha_finalize_section('', $chunk_html);
    }

    return $pages_local;
  };

  if ($has_marker) {
    // 2) Sempre que houver separadores, cada pedaço vira 1..n páginas (H2 dentro também quebra)
    $chunks = explode('[[ALPHA_SPLIT]]', $content);
    foreach ($chunks as $chunk) {
      $chunk = trim($chunk);
      if (!strlen(trim(wp_strip_all_tags($chunk)))) continue; // ignora vazios reais
      $pages = array_merge($pages, $build_from_chunk($chunk));
    }
  } else {
    // 3) Sem separadores: quebrar o conteúdo inteiro por H2 múltiplos
    $pages = $build_from_chunk($content);
  }

  // 4) Limpa totalmente vazias
  $pages = array_values(array_filter($pages, function ($p) {
    return !empty($p['heading']) || !empty($p['body']) || !empty($p['image']);
  }));

  return $pages;
}


// === 2) Fecha/normaliza cada seção: título opcional (1º <h2>), CTA e limpeza do texto ===
function alpha_finalize_section($title, $body_html)
{
  // Se não vier título externo, usa o 1º <h2> do bloco (se existir) e remove-o do corpo
  if (empty($title) && preg_match('/<h2[^>]*>(.*?)<\/h2>/is', $body_html, $mh2)) {
    $title     = wp_strip_all_tags($mh2[1]);
    $body_html = str_replace($mh2[0], '', $body_html);
  }

  // Extrai CTA via shortcode [storys-cta] (já remove do HTML)
  $cta = alpha_extract_storys_cta($body_html);

  // Primeira imagem da seção
  $img = '';
  if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $body_html, $im)) {
    $img = esc_url_raw($im[1]);
  }

  // Fallback: usa o 1º link como CTA e REMOVE esse link do texto (pra não duplicar no slide)
  if (empty($cta['url']) && preg_match('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $body_html, $alink)) {
    $cta['url'] = esc_url_raw($alink[1]);
    if (empty($cta['text'])) {
      $cta['text'] = wp_strip_all_tags($alink[2]);
    }
    // remove o link usado do corpo
    $body_html = str_replace($alink[0], '', $body_html);
  }
  if (empty($cta['text']) && !empty($cta['url'])) {
    $cta['text'] = 'Saiba mais';
  }

  // Texto final (curto)
  $text = trim(wp_strip_all_tags($body_html));
  if (function_exists('mb_strlen') && function_exists('mb_substr')) {
    if (mb_strlen($text) > 240) $text = mb_substr($text, 0, 240) . '...';
  } else {
    if (strlen($text) > 240) $text = substr($text, 0, 240) . '...';
  }

  return [
    'heading'   => trim($title),
    'body'      => $text,
    'image'     => $img,
    'cta_text'  => $cta['text'],
    'cta_url'   => $cta['url'],
    'cta_type'  => $cta['type'],   // 'button' | 'swipe' | ''
    'cta_icon'  => $cta['icon'],   // opcional (32x32)
  ];
}


// 2) Helper para extrair o shortcode da seção (Gutenberg salva como bloco "shortcode")
function alpha_extract_storys_cta(&$html)
{
  $out = ['type' => '', 'text' => '', 'url' => '', 'icon' => ''];

  // pega o shortcode (mesmo se vier dentro de <!-- wp:shortcode -->)
  if (preg_match('/\[storys-cta([^\]]*)\]/i', $html, $m)) {
    $atts = shortcode_parse_atts($m[1] ?? '');
    $type = isset($atts['type']) ? strtolower(trim($atts['type'])) : '';
    if (!in_array($type, ['button', 'swipe'], true)) $type = 'button';

    $out['type'] = $type;
    $out['text'] = isset($atts['text']) ? sanitize_text_field($atts['text']) : '';
    $out['url']  = isset($atts['url'])  ? esc_url_raw($atts['url']) : '';
    $out['icon'] = isset($atts['icon']) ? esc_url_raw($atts['icon']) : '';

    // remove o shortcode do HTML para não “vazar” no texto
    $html = str_replace($m[0], '', $html);
  }

  return $out;
}


function alpha_from_blocks(array $blocks)
{
  $pages = [];
  $current = [
    'heading'  => '',
    'body'     => '',
    'image'    => '',
    'cta_text' => '',
    'cta_url'  => '',
    'cta_type' => '',
    'cta_icon' => ''
  ];

  $push = function () use (&$pages, &$current) {
    $has_body = trim($current['body']) !== '';
    if ($current['heading'] || $has_body || $current['image']) {
      // limita corpo
      if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($current['body']) > 240) $current['body'] = mb_substr($current['body'], 0, 240) . '...';
      } else {
        if (strlen($current['body']) > 240) $current['body'] = substr($current['body'], 0, 240) . '...';
      }
      $pages[] = $current;
    }
    $current = [
      'heading'  => '',
      'body'     => '',
      'image'    => '',
      'cta_text' => '',
      'cta_url'  => '',
      'cta_type' => '',
      'cta_icon' => ''
    ];
  };

  $walk = function ($bs) use (&$walk, &$current, $push) {
    foreach ($bs as $b) {
      $name  = $b['blockName'] ?? '';
      $attrs = $b['attrs'] ?? [];
      $inner = $b['innerBlocks'] ?? [];
      $html  = $b['innerHTML'] ?? '';

      // === QUEBRAS DE PÁGINA ===
      // 1) H2 inicia nova página
      if ($name === 'core/heading' && !empty($attrs['level']) && (int)$attrs['level'] === 2) {
        $push();
        // texto do heading
        $text = '';
        if (!empty($attrs['content'])) {
          $text = wp_strip_all_tags($attrs['content']);
        } elseif ($html) {
          $text = wp_strip_all_tags($html);
        }
        $current['heading'] = $text;
      }
      // 2) Separador do Gutenberg força quebra
      elseif ($name === 'core/separator') {
        $push();
      }
      // 3) Bloco HTML contendo <hr> também força quebra
      elseif ($name === 'core/html' && preg_match('/<hr\b/i', $html)) {
        $push();
      }

      // === CONTEÚDO/CTAs/IMAGEM ===
      elseif ($name === 'core/paragraph') {
        // captura [storys-cta ...] se existir
        if (preg_match('/\[storys-cta\b([^\]]*)\]/i', $html, $mm)) {
          $atts = shortcode_parse_atts($mm[1] ?? '');
          $type = isset($atts['type']) ? strtolower(trim($atts['type'])) : '';
          if (!in_array($type, ['button', 'swipe'], true)) $type = '';
          if (!empty($atts['text'])) $current['cta_text'] = sanitize_text_field($atts['text']);
          if (!empty($atts['url']))  $current['cta_url']  = esc_url_raw($atts['url']);
          if (!empty($atts['icon'])) $current['cta_icon'] = esc_url_raw($atts['icon']);
          if ($type) $current['cta_type'] = $type;
          $html = str_replace($mm[0], '', $html); // remove o shortcode do corpo
        }
        // acumula o texto
        $text = wp_strip_all_tags($html);
        if ($text) $current['body'] .= ($current['body'] ? ' ' : '') . $text;

        // fallback CTA: 1º link do parágrafo
        if (empty($current['cta_url']) && preg_match('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/i', $html, $m)) {
          $current['cta_url']  = esc_url_raw($m[1]);
          if (empty($current['cta_text'])) $current['cta_text'] = wp_strip_all_tags($m[2]);
        }
      } elseif ($name === 'core/image') {
        if (empty($current['image'])) {
          $url = '';
          if (!empty($attrs['id'])) {
            $url = wp_get_attachment_image_url((int)$attrs['id'], 'storys_poster') ?: '';
          }
          if (!$url && !empty($attrs['url'])) $url = esc_url_raw($attrs['url']);
          if (!$url && preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $html, $m)) $url = esc_url_raw($m[1]);
          if ($url) $current['image'] = $url;
        }
      } elseif ($name === 'core/shortcode') {
        $sc = '';
        if (!empty($attrs['text'])) $sc = (string) $attrs['text'];
        elseif (!empty($html))      $sc = $html;
        if ($sc && preg_match('/\[storys-cta\b([^\]]*)\]/i', $sc, $mm)) {
          $atts = shortcode_parse_atts($mm[1] ?? '');
          $type = isset($atts['type']) ? strtolower(trim($atts['type'])) : '';
          if (!in_array($type, ['button', 'swipe'], true)) $type = '';
          if (!empty($atts['text'])) $current['cta_text'] = sanitize_text_field($atts['text']);
          if (!empty($atts['url']))  $current['cta_url']  = esc_url_raw($atts['url']);
          if (!empty($atts['icon'])) $current['cta_icon'] = esc_url_raw($atts['icon']);
          if ($type) $current['cta_type'] = $type;
        }
      } elseif ($name === 'core/button') {
        if (empty($current['cta_url']) && !empty($attrs['url'])) {
          $current['cta_url'] = esc_url_raw($attrs['url']);
          $current['cta_type'] = $current['cta_type'] ?: 'button';
        }
        if (empty($current['cta_text'])) {
          if (preg_match('/<a[^>]*>(.*?)<\/a>/i', $html, $tm)) {
            $current['cta_text'] = wp_strip_all_tags($tm[1]);
          } elseif (!empty($attrs['text'])) {
            $current['cta_text'] = sanitize_text_field($attrs['text']);
          }
        }
      }

      // desce na árvore
      if (!empty($inner)) $walk($inner);
    }
  };

  $walk($blocks);
  $push(); // última página (se tiver conteúdo)

  return $pages;
}

function alpha_storys_get_or_create_storys($source_post_id)
{
  $source_post_id = absint($source_post_id);
  if (! $source_post_id) {
    return 0;
  }

  $existing = (int) get_post_meta($source_post_id, '_alpha_storys_id', true);
  if ($existing && get_post($existing)) {
    return $existing;
  }

  // Essa meta_query é segura: 1 resultado, fields=ids, no_found_rows=true.
  // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
  $q = new WP_Query(array(
    'post_type'      => 'alpha_storys',
    'post_status'    => array('publish', 'draft', 'pending', 'future', 'private'),
    'meta_query'     => array(
      array(
        'key'     => '_alpha_storys_source_post',
        'value'   => $source_post_id,
        'compare' => '=',
        'type'    => 'NUMERIC',
      ),
    ),
    'fields'         => 'ids',
    'posts_per_page' => 1,
    'no_found_rows'  => true,
  ));
  // phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_query

  if ($q->have_posts()) {
    $id = (int) $q->posts[0];
    update_post_meta($source_post_id, '_alpha_storys_id', $id);
    return $id;
  }

  // 3) Cria novo story
  $args = array(
    'post_type'   => 'alpha_storys',
    'post_title'  => get_the_title($source_post_id),
    'post_status' => 'draft',
    'post_author' => (int) get_post_field('post_author', $source_post_id),
  );

  $id = wp_insert_post($args, true);
  if (is_wp_error($id)) {
    return $id;
  }
  $id = (int) $id;

  update_post_meta($id, '_alpha_storys_source_post', $source_post_id);
  update_post_meta($source_post_id, '_alpha_storys_id', $id);

  // Thumbnail
  $thumb_id = get_post_thumbnail_id($source_post_id);
  if ($thumb_id) {
    set_post_thumbnail($id, $thumb_id);
  }

  // Opções padrão (publisher, logo, etc.)
  if (function_exists('alpha_storys_options')) {
    $o = alpha_storys_options();

    if (! empty($o['publisher_logo_id'])) {
      update_post_meta($id, '_alpha_storys_logo_id', (int) $o['publisher_logo_id']);
    }

    $publisher = ! empty($o['publisher_name'])
      ? sanitize_text_field($o['publisher_name'])
      : get_bloginfo('name');

    update_post_meta($id, '_alpha_storys_publisher', $publisher);
  }

  return $id;
}
