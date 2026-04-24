<?php
if (!defined('ABSPATH')) exit;

class AlphaSuite_Helpers
{
  public static function alpha_storys_options()
  {
    $o = get_option('alpha_storys_options', []);
    return is_array($o) ? $o : [];
  }

  public static function alpha_opt($key, $default = null)
  {
    $opts = self::alpha_storys_options();
    return array_key_exists($key, $opts) ? $opts[$key] : $default;
  }

  public static function alpha_ai_get_api_key(): string
  {

    // 1) PRIORIDADE: settings do Alpha GPT Posts (agp_settings[apis][openai][key])
    if (class_exists('AlphaSuite_Settings')) {

      $opt = AlphaSuite_Settings::get();

      if (!empty($opt['apis']['openai']['key'])) {
        $k = trim((string)$opt['apis']['openai']['key']);
        if ($k !== '') return $k;
      }
    }

    // 3) Variável de ambiente
    $env = getenv('OPENAI_API_KEY');
    if (is_string($env) && trim($env) !== '') {
      return trim($env);
    }

    // 4) Options específicas de outro plugin (Alpha Storys, por ex.)
    if (function_exists('alpha_storys_options')) {
      $o = self::alpha_storys_options();
      if (!empty($o['ai_api_key'])) {
        return trim((string)$o['ai_api_key']);
      }
    }

    // 5) Options legadas / genéricas
    foreach (['alpha_ai_openai_api_key', 'alpha_ai_api_key', 'openai_api_key'] as $name) {
      $v = get_option($name);
      if (is_string($v) && trim($v) !== '') {
        return trim($v);
      }
    }

    // nada encontrado
    return '';
  }


  public static function alpha_ai_get_model(): string
  {
    $o = self::alpha_storys_options();
    return !empty($o['ai_model']) ? (string)$o['ai_model'] : 'gpt-4o-mini';
  }

  public static function alpha_ai_get_temperature(): float
  {
    $o = self::alpha_storys_options();
    $t = isset($o['ai_temperature']) ? (float)$o['ai_temperature'] : 0.4;
    return max(0, min(1, $t));
  }

  public static function alpha_ai_get_default_brief(): string
  {
    $o = self::alpha_storys_options();
    return isset($o['ai_brief_default']) ? (string)$o['ai_brief_default'] : '';
  }

  public static function alpha_get_ga4_id(): string
  {
    $mode = self::alpha_opt('ga_mode', 'auto'); // auto|manual|off
    if ($mode === 'off') return '';
    if ($mode === 'manual') {
      $id = trim((string) self::alpha_opt('ga_manual_id', ''));
      return preg_match('/^G-[A-Z0-9\-]{4,}$/i', $id) ? $id : '';
    }
    $candidates = [
      'googlesitekit_analytics-4_settings',
      'googlesitekit_analytics-4',
      'googlesitekit_analytics_settings',
      'googlesitekit_gtag_settings',
    ];
    foreach ($candidates as $opt_name) {
      $opt = get_option($opt_name);
      if (is_array($opt)) {
        foreach (['measurementID', 'measurementId', 'measurement_id', 'ga4MeasurementId'] as $k) {
          if (!empty($opt[$k]) && preg_match('/^G-[A-Z0-9\-]{4,}$/i', $opt[$k])) return $opt[$k];
        }
        $flat = json_decode(json_encode($opt), true);
        $it = new RecursiveIteratorIterator(new RecursiveArrayIterator($flat));
        foreach ($it as $v) {
          if (is_string($v) && preg_match('/^G-[A-Z0-9\-]{4,}$/i', $v)) return $v;
        }
      }
    }
    $id = trim((string) self::alpha_opt('ga_manual_id', ''));
    return preg_match('/^G-[A-Z0-9\-]{4,}$/i', $id) ? $id : '';
  }

  public static function alpha_get_publisher_logo_url($size = 'full')
  {
    $id = (int) self::alpha_opt('publisher_logo_id', 0);
    return $id ? wp_get_attachment_image_url($id, $size) : '';
  }

  /** =========================
   *  storys: criar ou localizar
   *  ========================= */
  public static function alpha_get_or_create_storys_for_post($post_id)
  {
    $post = get_post($post_id);
    if (!$post) return 0;

    // SEM reuso. Sempre cria um story novo.
    $args = [
      'post_type'   => 'alpha_storys',
      'post_title'  => $post->post_title,
      'post_status' => 'draft',
      'post_author' => (int) $post->post_author,
    ];

    $storys_id = wp_insert_post($args);
    if (is_wp_error($storys_id) || !$storys_id) {
      return 0;
    }

    $storys_id = (int) $storys_id;

    // vínculo com o post original (pra saber de onde veio)
    update_post_meta($storys_id, '_alpha_storys_source_post', $post_id);

    // se você quiser, pode guardar “último story gerado” no post original,
    // mas NÃO usar isso pra reuso:
    update_post_meta($post_id, '_alpha_storys_last_id', $storys_id);

    // copia thumb do post original, se tiver
    $thumb = get_post_thumbnail_id($post_id);
    if ($thumb) {
      set_post_thumbnail($storys_id, $thumb);
    }

    // publisher / logo padrão
    $publisher = self::alpha_opt('publisher_name', get_bloginfo('name'));
    update_post_meta($storys_id, '_alpha_storys_publisher', sanitize_text_field($publisher));

    $logo_id = (int) self::alpha_opt('publisher_logo_id', 0);
    if ($logo_id) {
      update_post_meta($storys_id, '_alpha_storys_logo_id', $logo_id);
    }

    return $storys_id;
  }


  /** =========================
   *  Mídia: sideload de imagens
   *  ========================= */
  public static function alpha_sideload_image_to_post($image_url, $attach_to_post_id = 0)
  {
    $image_url = trim((string)$image_url);
    if ($image_url === '' || !filter_var($image_url, FILTER_VALIDATE_URL)) return 0;

    if (!function_exists('media_sideload_image')) {
      require_once ABSPATH . 'wp-admin/includes/media.php';
      require_once ABSPATH . 'wp-admin/includes/file.php';
      require_once ABSPATH . 'wp-admin/includes/image.php';
    }

    $att_id = 0;
    // media_sideload_image pode retornar HTML ou ID se pedirmos 'id'
    $result = media_sideload_image($image_url, $attach_to_post_id, null, 'id');

    if (is_wp_error($result)) return 0;
    $att_id = (int) $result;
    return $att_id > 0 ? $att_id : 0;
  }

  /** =========================
   *  Render: páginas -> blocos
   *  ========================= */
  public static function alpha_render_storys_pages_to_blocks(array $pages)
  {
    $blocks = '';

    foreach ($pages as $idx => $p) {
      $heading  = isset($p['heading']) ? wp_strip_all_tags($p['heading']) : '';
      $body     = isset($p['body'])    ? wp_kses_post($p['body']) : '';
      $cta_text = isset($p['cta_text']) ? sanitize_text_field($p['cta_text']) : '';
      $cta_url  = isset($p['cta_url']) ? esc_url_raw($p['cta_url']) : '';
      $prompt   = isset($p['prompt']) ? sanitize_text_field($p['prompt']) : '';
      $image_id = isset($p['image_id']) ? (int) $p['image_id'] : 0;

      $blocks .= "<!-- wp:group {\"className\":\"alpha-storys-page\"} -->\n";
      $blocks .= "<div class=\"wp-block-group alpha-storys-page\">\n";

      if ($heading !== '') {
        $blocks .= "<!-- wp:heading {\"level\":2} -->\n";
        $blocks .= "<h2>" . esc_html($heading) . "</h2>\n";
        $blocks .= "<!-- /wp:heading -->\n";
      }

      // IMAGEM DO SLIDE (Pollinations) – se existir
      if ($image_id) {
        $src = wp_get_attachment_image_url($image_id, 'full');
        if ($src) {
          $blocks .= "<!-- wp:image {\"id\":{$image_id}} -->\n";
          $blocks .= "<figure class=\"wp-block-image\">";
          $blocks .= "<img src=\"" . esc_url($src) . "\" alt=\"" . esc_attr($heading ?: $prompt) . "\" />";
          $blocks .= "</figure>\n";
          $blocks .= "<!-- /wp:image -->\n";
        }
      }

      if ($body !== '') {
        $blocks .= "<!-- wp:paragraph -->\n";
        $blocks .= "<p>" . wp_kses($body, [
          'a' => ['href' => [], 'rel' => [], 'target' => []],
          'strong' => [],
          'em' => [],
          'br' => []
        ]) . "</p>\n";
        $blocks .= "<!-- /wp:paragraph -->\n";
      }

      if ($cta_text !== '' || $cta_url !== '') {
        $blocks .= "<!-- wp:paragraph -->\n";
        $blocks .= "<a href=\"" . esc_attr($cta_url) . "\" target=\"_blank\">" . esc_html($cta_text) . "</a>\n";
        $blocks .= "<!-- /wp:paragraph -->\n";
      }

      $blocks .= "</div>\n";
      $blocks .= "<!-- /wp:group -->\n";
    }

    return $blocks;
  }

  public static function stories_opt(string $key, $default = null)
  {
    $opts = get_option('pga_settings', []);
    $st   = is_array($opts['stories'] ?? null) ? $opts['stories'] : [];
    return array_key_exists($key, $st) ? $st[$key] : $default;
  }

  public static function stories_logo_id(): int
  {
    return (int) self::stories_opt('publisher_logo_id', 0);
  }

  public static function stories_logo_url(): string
  {
    $id = self::stories_logo_id();
    if ($id > 0) {
      $url = wp_get_attachment_image_url($id, 'full');
      return $url ? $url : '';
    }
    return '';
  }

  /** =========================
   *  IA: gerar e salvar conteúdo (Stories)
   *  ========================= */
  public static function alpha_ai_generate_for_post($post_id)
  {
    $post = get_post($post_id);
    if (!$post) {
      return new \WP_Error('alpha_ai_post', 'Post inválido.');
    }

    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
    $raw_html = apply_filters('the_content', $post->post_content);
    $brief    = self::alpha_ai_get_default_brief();

    // Descobre provider de IMAGEM configurado para Stories
    $imageProvider = 'pollinations';
    if (class_exists('AlphaSuite_Settings')) {
      $opts    = AlphaSuite_Settings::get();
      $stories = $opts['stories'] ?? [];
    }

    if (!empty($stories['images_provider'])) {
      $imageProvider = (string) $stories['images_provider'];
    }

    $lang = (string) $stories['language'];

    // 1) Monta o prompt via central de prompts, passando o provider
    $prompt = AlphaSuite_Prompts::build_story_prompt_for_post(
      $post,
      $raw_html,
      $brief,
      $imageProvider,
      $lang
    );

    $result = AlphaSuite_AI::generate_story_pages($prompt, []);

    if (is_wp_error($result)) {
      return $result;
    }

    if (empty($result['pages']) || !is_array($result['pages'])) {
      return new WP_Error('alpha_ai_empty', 'A IA não retornou páginas válidas.');
    }

    // 3) Sanitiza páginas vindas da IA
    $pages = [];
    foreach ($result['pages'] as $p) {
      $heading = isset($p['heading']) ? wp_strip_all_tags($p['heading']) : '';
      $body    = isset($p['body'])    ? wp_strip_all_tags($p['body'])    : '';

      // Se vier tudo vazio, ignora
      if ($heading === '' && $body === '') {
        continue;
      }

      $pages[] = [
        'heading'  => $heading,
        'body'     => $body,
        'cta_text' => isset($p['cta_text']) ? sanitize_text_field($p['cta_text']) : '',
        // vamos controlar cta_url no PHP
        'cta_url'  => '',
        'prompt'   => isset($p['prompt']) ? sanitize_text_field($p['prompt']) : '',
        // IMPORTANTE: não mexemos aqui em image_id/image ainda
      ];
    }

    if (empty($pages)) {
      return new WP_Error('alpha_ai_empty', 'A IA não retornou páginas válidas.');
    }

    // Descobre o post de origem (artigo) para o CTA
    $source_id = (int) $post_id;
    if ('alpha_storys' === get_post_type($post_id)) {
      $src = (int) get_post_meta($post_id, '_alpha_storys_source_post', true);
      if ($src > 0) {
        $source_id = $src;
      }
    }

    $default_link = get_permalink($source_id);

    // Se tiver link, aplica nos slides que têm CTA
    if ($default_link) {
      $lastIndex = count($pages) - 1;

      foreach ($pages as $i => &$pg) {
        $hasCTA = !empty($pg['cta_text']);

        if ($hasCTA) {
          $pg['cta_url'] = $default_link;
        } else {
          $pg['cta_url'] = '';
        }
      }
      unset($pg);

      // garante que o ÚLTIMO slide tenha CTA pro artigo
      if ($lastIndex >= 0) {
        if (empty($pages[$lastIndex]['cta_text'])) {
          $pages[$lastIndex]['cta_text'] = 'Saiba mais no artigo completo';
        }
        $pages[$lastIndex]['cta_url'] = $default_link;
      }
    }

    // 4) Descobre o CPT de destino (alpha_storys)
    $target_id = ('alpha_storys' === get_post_type($post_id))
      ? (int) $post_id
      : self::alpha_get_or_create_storys_for_post((int) $post_id);

    if (!$target_id) {
      return new WP_Error('alpha_storys_target', 'Não foi possível criar ou localizar o Web Story.');
    }

    // 4.1) PRESERVAR IMAGENS EXISTENTES (image_id / image)
    $existing = get_post_meta($target_id, '_alpha_storys_pages', true);
    if (is_array($existing)) {
      foreach ($pages as $i => &$pg) {
        if (!empty($existing[$i]['image_id'])) {
          $pg['image_id'] = (int) $existing[$i]['image_id'];
        }
        if (!empty($existing[$i]['image'])) {
          $pg['image'] = $existing[$i]['image'];
        }
      }
      unset($pg);
    }

    // 5) Salva meta com as páginas (agora possivelmente já contendo image_id/image)
    update_post_meta($target_id, '_alpha_storys_pages', $pages);

    // 6) Renderiza blocos (se já tiver image_id, as imagens vão pros blocos também)
    $blocks = self::alpha_render_storys_pages_to_blocks($pages);

    wp_update_post([
      'ID'           => $target_id,
      'post_content' => $blocks,
      'post_title'   => get_post_field('post_title', $target_id) ?: get_the_title($post_id),
    ]);

    $edit_url = get_edit_post_link($target_id, '');
    $view_url = get_permalink($target_id);

    return [
      'ok'        => true,
      'count'     => count($pages),
      'target_id' => (int) $target_id,
      'edit_url'  => $edit_url ?: '',
      'view_url'  => $view_url ?: '',
    ];
  }
}
