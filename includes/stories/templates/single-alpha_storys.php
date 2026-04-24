<?php
if (!defined('ABSPATH')) exit;
global $post;

// Metas e campos
$pages      = get_post_meta($post->ID, '_alpha_storys_pages', true);
$pages      = is_array($pages) ? $pages : [];

$alpha_storys_publisher   = get_post_meta($post->ID, '_alpha_storys_publisher', true) ?: (alpha_opt('publisher_name') ?: get_bloginfo('name'));

$alpha_logo_id  = (int) get_post_meta($post->ID, '_alpha_storys_logo_id', true);
$alpha_suite_default_logo_id = (int) AlphaSuite_Helpers::stories_logo_id();

$alpha_suite_effective_logo_id = $alpha_logo_id ?: $alpha_suite_default_logo_id;

$alpha_logo_src = $alpha_suite_effective_logo_id
  ? (wp_get_attachment_image_url($alpha_suite_effective_logo_id, 'thumbnail') ?: '')
  : (AlphaSuite_Helpers::stories_logo_url() ?: '');


$alpha_ga_id      = AlphaSuite_Helpers::alpha_get_ga4_id();
$alpha_ga_enable  = !empty($alpha_ga_id);

// Playback: meta do post > default das configurações > fallback hardcoded
$alpha_meta_autoplay = get_post_meta($post->ID, '_alpha_storys_autoplay', true);

// default global das configs de stories (1 = ligado)
$alpha_suite_opt_autoplay = (int) AlphaSuite_Helpers::alpha_opt('autoplay', 1);

if ($alpha_meta_autoplay === '' || $alpha_meta_autoplay === null) {
  // se o post não tiver meta, usa o global
  $alpha_suite_autoplay = !empty($alpha_suite_opt_autoplay);
} else {
  // se tiver meta, respeita o que está salvo no post
  $alpha_suite_autoplay = (bool) $alpha_meta_autoplay;
}

// duração: meta > config stories > fallback
$alpha_suite_meta_seconds = (int) get_post_meta($post->ID, '_alpha_storys_duration', true);
$alpha_suite_opt_seconds  = (int) AlphaSuite_Helpers::alpha_opt('duration', 7);

if ($alpha_suite_meta_seconds > 0) {
  $alpha_suite_seconds = $alpha_suite_meta_seconds;
} elseif ($alpha_suite_opt_seconds > 0) {
  $alpha_suite_seconds = $alpha_suite_opt_seconds;
} else {
  $alpha_suite_seconds = 7;
}

$alpha_suite_poster_id  = get_post_thumbnail_id($post->ID); // Poster obrigatório
$alpha_suite_poster     = $alpha_suite_poster_id ? wp_get_attachment_image_url($alpha_suite_poster_id, 'storys_poster') : '';

if (!$alpha_suite_poster) {
  foreach ($pages as $alpha_suite_p) {
    $alpha_suite_p = (array) $alpha_suite_p;

    $alpha_suite_img_id = !empty($alpha_suite_p['image_id']) ? (int) $alpha_suite_p['image_id'] : 0;
    $alpha_suite_url    = '';

    if ($alpha_suite_img_id) {
      // usa o size especial de poster (3:4)
      $alpha_suite_url = wp_get_attachment_image_url($alpha_suite_img_id, 'alpha_storys_poster');
    } elseif (!empty($alpha_suite_p['image'])) {
      // legado: usa URL antiga
      $alpha_suite_url = esc_url($alpha_suite_p['image']);
    }

    if ($alpha_suite_url) {
      $alpha_suite_poster = $alpha_suite_url;
      break;
    }
  }
}

if (!$alpha_suite_poster) {
  $alpha_suite_poster = get_stylesheet_directory_uri() . '/assets/story-poster-fallback.jpg';
}

// Ao menos 1 página
if (count($pages) === 0) {
  $pages[] = [
    'heading' => get_the_title($post),
    'body'   => '',
    'image'  => '',
    'cta_text' => '',
    'cta_url' => ''
  ];
}

// cores e estilo: meta do post > configs stories > fallback

$alpha_suite_style = get_post_meta($post->ID, '_alpha_storys_style', true);
if (!$alpha_suite_style) {
  $alpha_suite_style = AlphaSuite_Helpers::alpha_opt('default_style', 'clean');
}

$alpha_suite_font = get_post_meta($post->ID, '_alpha_storys_font', true);
if (!$alpha_suite_font) {
  $alpha_suite_font = AlphaSuite_Helpers::alpha_opt('default_font', 'inter');
}

$alpha_suite_bg_color = (string) get_post_meta($post->ID, '_alpha_storys_background_color', true);
if ($alpha_suite_bg_color === '') {
  $alpha_suite_bg_color = (string) AlphaSuite_Helpers::stories_opt('background_color', '#000000');
}

$alpha_suite_txt_color = (string) get_post_meta($post->ID, '_alpha_storys_text_color', true);
if ($alpha_suite_txt_color === '') {
  $alpha_suite_txt_color = (string) AlphaSuite_Helpers::stories_opt('text_color', '#ffffff');
}

$alpha_suite_accent = (string) get_post_meta($post->ID, '_alpha_storys_accent_color', true);
if ($alpha_suite_accent === '') {
  $alpha_suite_accent = (string) AlphaSuite_Helpers::stories_opt('accent_color', '#ffffff');
}


// Mapeia Google Fonts
function alpha_font_href($alpha_suite_font)
{
  switch ($alpha_suite_font) {
    case 'inter':
      return 'https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap';
    case 'poppins':
      return 'https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap';
    case 'merriweather':
      return 'https://fonts.googleapis.com/css2?family=Merriweather:wght@400;700;900&display=swap';
    case 'plusjakarta':
      return 'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;900&display=swap';
    default:
      return '';
  }
}
$alpha_suite_font_href = alpha_font_href($alpha_suite_font);

// Classe do estilo
$alpha_suite_style_class = 'style-' . preg_replace('/[^a-z0-9\-]/i', '', $alpha_suite_style);
// Família CSS
$alpha_suite_font_family = $alpha_suite_font === 'system'
  ? "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Ubuntu,'Helvetica Neue',Arial,'Noto Sans',sans-serif"
  : ($alpha_suite_font === 'merriweather'
    ? "'Merriweather',serif"
    : ($alpha_suite_font === 'poppins' ? "'Poppins',sans-serif" : "'Inter',sans-serif"));
?>
<!doctype html>
<html amp lang="<?php echo esc_attr(get_bloginfo('language')); ?>">

<head>
  <meta charset="utf-8">
  <title><?php echo esc_html(get_the_title($post)); ?></title>
  <meta name="viewport" content="width=device-width,minimum-scale=1,initial-scale=1">
  <?php
  // ===== JSON-LD para Web Stories (Article + AmpStory) =====
  $alpha_suite_permalink     = get_permalink($post);
  $alpha_suite_headline      = get_the_title($post);
  $alpha_suite_description   = has_excerpt($post)
    ? wp_strip_all_tags(get_the_excerpt($post))
    : wp_trim_words(wp_strip_all_tags(get_post_field('post_content', $post)), 35, '…');
  $alpha_suite_datePublished = get_post_time('c', true, $post);
  $alpha_suite_dateModified  = get_post_modified_time('c', true, $post);

  // Imagens (poster + primeiras imagens das páginas)
  $alpha_suite_images = [];
  if (!empty($alpha_suite_poster_id)) {
    if ($alpha_suite_src = wp_get_attachment_image_src($alpha_suite_poster_id, 'full')) {
      $alpha_suite_images[] = [
        '@type'  => 'ImageObject',
        'url'    => $alpha_suite_src[0],
        'width'  => (int) $alpha_suite_src[1],
        'height' => (int) $alpha_suite_src[2],
      ];
    }
  } elseif (!empty($alpha_suite_poster)) {
    $alpha_suite_images[] = $alpha_suite_poster; // fallback simples
  }

  if (!empty($pages) && is_array($pages)) {
    foreach ($pages as $alpha_suite_p) {
      if (!empty($alpha_suite_p['image'])) {
        $alpha_suite_images[] = ['@type' => 'ImageObject', 'url' => esc_url($alpha_suite_p['image'])];
      }
    }
  }
  // Remove duplicadas mantendo estrutura
  $alpha_suite_images = array_values(array_unique($alpha_suite_images, SORT_REGULAR));

  // Autor
  $alpha_suite_author = [
    '@type' => 'Person',
    'name'  => get_the_author_meta('display_name', $post->post_author),
    'url'   => get_author_posts_url($post->post_author),
  ];

  // Publisher + logo
  $alpha_suite_publisher_logo = null;
  if (!empty($alpha_logo_id) && ($alpha_suite_lsrc = wp_get_attachment_image_src($alpha_logo_id, 'full'))) {
    $alpha_suite_publisher_logo = [
      '@type'  => 'ImageObject',
      'url'    => $alpha_suite_lsrc[0],
      'width'  => (int) $alpha_suite_lsrc[1],
      'height' => (int) $alpha_suite_lsrc[2],
    ];
  } elseif (!empty($alpha_logo_src)) {
    $alpha_suite_publisher_logo = ['@type' => 'ImageObject', 'url' => $alpha_logo_src];
  }

  $alpha_suite_publisher_data = [
    '@type' => 'Organization',
    'name'  => $alpha_storys_publisher,
  ];
  if ($alpha_suite_publisher_logo) $alpha_suite_publisher_data['logo'] = $alpha_suite_publisher_logo;

  // Monta o Article (+ AmpStory opcional)
  $alpha_suite_schema = [
    '@context'          => 'https://schema.org',
    '@type'             => ['Article', 'AmpStory'],
    'mainEntityOfPage'  => ['@type' => 'WebPage', '@id' => $alpha_suite_permalink],
    'headline'          => wp_strip_all_tags($alpha_suite_headline),
    'description'       => $alpha_suite_description,
    'image'             => $alpha_suite_images,
    'datePublished'     => $alpha_suite_datePublished,
    'dateModified'      => $alpha_suite_dateModified,
    'author'            => $alpha_suite_author,
    'publisher'         => $alpha_suite_publisher_data,
  ];
  ?>
  <script type="application/ld+json">
    <?php echo wp_json_encode($alpha_suite_schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>
  </script>

  <link rel="canonical" href="<?php echo esc_url(get_permalink($post)); ?>">

  <?php if ($alpha_suite_font_href) :
    // phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
  ?>
    <link rel="stylesheet" href="<?php echo esc_url($alpha_suite_font_href); ?>">
  <?php
  // phpcs:enable WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
  endif; ?>


  <style amp-boilerplate>
    body {
      -webkit-animation: -amp-start 8s steps(1, end) 0s 1 normal both;
      -moz-animation: -amp-start 8s steps(1, end) 0s 1 normal both;
      -ms-animation: -amp-start 8s steps(1, end) 0s 1 normal both;
      animation: -amp-start 8s steps(1, end) 0s 1 normal both
    }

    @-webkit-keyframes -amp-start {
      from {
        visibility: hidden
      }

      to {
        visibility: visible
      }
    }

    @-moz-keyframes -amp-start {
      from {
        visibility: hidden
      }

      to {
        visibility: visible
      }
    }

    @-ms-keyframes -amp-start {
      from {
        visibility: hidden
      }

      to {
        visibility: visible
      }
    }

    @-o-keyframes -amp-start {
      from {
        visibility: hidden
      }

      to {
        visibility: visible
      }
    }

    @keyframes -amp-start {
      from {
        visibility: hidden
      }

      to {
        visibility: visible
      }
    }
  </style>
  <noscript>
    <style amp-boilerplate>
      body {
        -webkit-animation: none;
        -moz-animation: none;
        -ms-animation: none;
        animation: none
      }
    </style>
  </noscript>

  <!-- AMP scripts: apenas UMA vez cada -->
  <?php // phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedScript	
  ?>
  <script async src="https://cdn.ampproject.org/v0.js"></script>
  <script async custom-element="amp-story" src="https://cdn.ampproject.org/v0/amp-story-1.0.js"></script>

  <?php if ($alpha_ga_enable) : ?>
    <script async custom-element="amp-analytics" src="https://cdn.ampproject.org/v0/amp-analytics-0.1.js"></script>
    <script async custom-element="amp-story-auto-analytics" src="https://cdn.ampproject.org/v0/amp-story-auto-analytics-0.1.js"></script>
  <?php endif; ?>
  <?php // phpcs:enable WordPress.WP.EnqueuedResources.NonEnqueuedScript	
  ?>

  <style amp-custom>
    /* Fonte e estilos base */
    <?php // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped 
    ?>amp-story {
      font-family: <?php echo $alpha_suite_font_family; ?>;
    }

    <?php // phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped 
    ?>.pad {
      padding: 24px
    }

    .h2 {
      font-size: 26px;
      line-height: 1.1;
      color: #fff;
      margin: 0 0 10px;
      padding-left: 15px;
      border-left: 3px solid <?php echo esc_html($alpha_suite_accent); ?>;
    }

    .p {
      font-size: 18px;
      color: #fff;
      margin: 0;
    }

    .btn {
      display: inline-block;
      padding: 12px 20px;
      color: #000;
      border-radius: 10px;
      text-decoration: none;
      font-weight: 700;
      box-shadow: 0 4px 24px rgba(0, 0, 0, .35)
    }

    .bg {
      width: 100%;
      height: 100%;
      background: <?php echo esc_html($alpha_suite_bg_color); ?> center / cover no-repeat;
    }

    .overlay {
      position: absolute;
      top: 0;
      right: 0;
      bottom: 0;
      left: 0;
      background: linear-gradient(180deg, rgba(0, 0, 0, .35), rgba(0, 0, 0, .55))
    }

    /* CLEAN - texto central com imagem de fundo */
    .style-clean .layer-content {
      align-content: end;
      justify-content: center;
      text-align: left;
      padding-bottom: 120px;
    }

    /* garante que a layer possa receber o pseudo-elemento */
    .style-clean .layer-content {
      position: relative;
    }

    /* overlay de gradiente: transparente no topo e preto no rodapé */
    .style-clean .layer-content::before {
      content: "";
      position: absolute;
      inset: 0;
      pointer-events: none;
      background: linear-gradient(to bottom, rgba(0, 0, 0, 0) 46%, rgba(0, 0, 0, 0.5) 64%, rgba(0, 0, 0, .8) 90%);
      z-index: -1;
    }

    /* DARK-LEFT - overlay e texto à esquerda */
    .style-dark-left .layer-content {
      align-content: center;
      justify-content: center;
      text-align: left;
      padding: 40px;
    }

    .style-dark-left .overlay {
      background: linear-gradient(120deg, rgba(0, 0, 0, .65), rgba(0, 0, 0, .2) 60%);
    }

    .style-dark-left .h2,
    .style-dark-left .p {
      text-shadow: 0 4px 30px rgba(0, 0, 0, .8);
    }

    /* CARD - imagem em cartão, texto abaixo */
    .style-card .card {
      width: 78%;
      max-width: 820px;
      height: 260px;
      border-radius: 24px;
      overflow: hidden;
      background: #111 center / cover no-repeat;
      box-shadow: 0 4px 24px rgba(0, 0, 0, .35);
      margin: 0 auto 18px auto;
    }

    amp-story-grid-layer {
      border-bottom: 5px solid <?php echo esc_html($alpha_suite_accent); ?>;
    }

    .style-card .layer-content {
      align-content: end;
      justify-content: end;
      text-align: center;
      padding: 24px;
    }

    /* SPLIT - imagem esquerda, texto direita */
    .style-split .split {
      display: flex;
      align-items: center;
      height: 100%;
      padding: 24px;
    }

    .style-split .split .left {
      width: 45%;
      height: 80%;
      border-radius: 20px;
      background: #111 center / cover no-repeat;
      box-shadow: 0 4px 24px rgba(0, 0, 0, .35);
      margin-right: 24px;
    }

    .style-split .split .right {
      flex: 1;
      color: #fff;
    }

    .style-split .right .h2 {
      margin-bottom: 12px;
    }

    .h2,
    .p {
      color: <?php echo esc_html($alpha_suite_txt_color); ?>;
    }

    /* Fundo desfocado tipo Web Stories plugin */
    .bg-blur {
      filter: blur(22px) saturate(1.1);
      transform: scale(1.08);
    }

    /* TOP — imagem no topo (full width), borda arredondada embaixo; textos abaixo centralizados (container), alinhados à esquerda */
    .style-top .bg-solid {
      position: absolute;
      inset: 0;
      background: <?php echo esc_html($alpha_suite_bg_color); ?>;
    }

    .style-top .layer-content-top {
      align-content: start;
      justify-content: start;
      padding-top: 0;
    }

    .style-top .hero img {
      object-fit: cover;
    }

    .style-top .hero {
      position: relative;
      width: 100%;
      height: 56vh;
      /* altura “razoável” */
      max-height: 65%;
      overflow: hidden;
      object-fit: cover;
      border-radius: 0 0 12px 12px;
    }

    .style-top .content {
      width: 100%;
      padding: 18px 0 0;
    }

    .style-top .content-inner {
      width: 86%;
      max-width: 820px;
      margin: 0 auto;
      /* centraliza o container */
      text-align: left;
      /* mas textos alinhados à esquerda */
    }

    /* opcional: você já tem .overlay; ela escurece por cima do blur */
    .brand {
      position: absolute;
      z-index: 10;
    }

    .brand .logo {
      width: 36px;
      height: 36px;
      overflow: hidden;
    }

    .brand .logo amp-img,
    .brand .logo img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .brand .name {
      font-size: 12px;
      font-weight: 700;
      letter-spacing: .2px;
      color: <?php echo esc_html($alpha_suite_txt_color); ?>;
      text-shadow: 0 6px 24px rgba(0, 0, 0, .45);
      max-width: 210px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
  </style>
</head>

<body>
  <amp-story
    standalone
    class="<?php echo esc_attr($alpha_suite_style_class); ?>"
    title="<?php echo esc_attr(get_the_title($post)); ?>"
    publisher="<?php echo esc_attr($alpha_storys_publisher); ?>"
    publisher-logo-src="<?php echo esc_url($alpha_logo_src); ?>"
    poster-portrait-src="<?php echo esc_url($alpha_suite_poster); ?>">
    <?php if ($alpha_ga_enable): ?>
      <amp-story-auto-analytics gtag-id="<?php echo esc_attr($alpha_ga_id); ?>"></amp-story-auto-analytics>
    <?php endif;

    $alpha_suite_i = 1;
    foreach ($pages as $alpha_suite_p):
      $alpha_suite_p = array_merge([
        'image'     => '',
        'image_id'  => 0,
        'heading'   => '',
        'body'      => '',
        'cta_url'   => '',
        'cta_text'  => '',
        'cta_type'  => '',
        'cta_icon'  => '',
        'duration'  => null,
      ], (array) $alpha_suite_p);

      // === IMAGEM DO SLIDE (usa sizes especiais) ==========================
      $alpha_suite_img_id = (int) ($alpha_suite_p['image_id'] ?? 0);
      $alpha_suite_img    = '';

      // define o size default por estilo
      $alpha_suite_size = 'alpha_storys_slide'; // vertical 9:16 padrão

      switch ($alpha_suite_style) {
        case 'top':
          // hero no topo, pode ser 3:4 sem problema
          $alpha_suite_size = 'alpha_storys_poster';
          break;

        case 'card':
        case 'split':
        case 'dark-left':
        default:
          $alpha_suite_size = 'alpha_storys_slide';
          break;
      }

      // 1) Se tiver image_id, usa attachment + size
      if ($alpha_suite_img_id) {
        $alpha_suite_img = wp_get_attachment_image_url($alpha_suite_img_id, $alpha_suite_size);

        // fallback se precisar
        if (!$alpha_suite_img) {
          $alpha_suite_img = wp_get_attachment_image_url($alpha_suite_img_id, 'alpha_storys_slide')
            ?: wp_get_attachment_image_url($alpha_suite_img_id, 'alpha_storys_poster');
        }
      }

      // 2) Compat com stories antigos que só tinham 'image' (URL pura)
      if (!$alpha_suite_img && !empty($alpha_suite_p['image'])) {
        $alpha_suite_att_id = attachment_url_to_postid($alpha_suite_p['image']);
        if ($alpha_suite_att_id) {
          $alpha_suite_img = wp_get_attachment_image_url($alpha_suite_att_id, $alpha_suite_size)
            ?: wp_get_attachment_image_url($alpha_suite_att_id, 'alpha_storys_slide')
            ?: wp_get_attachment_image_url($alpha_suite_att_id, 'alpha_storys_poster');
        }

        // último fallback: se ainda não achou attachment, aí sim usa a URL original
        if (!$alpha_suite_img) {
          $alpha_suite_img = esc_url($alpha_suite_p['image']);
        }
      }

      $alpha_suite_dur = $alpha_suite_p['duration'] ? (int)$alpha_suite_p['duration'] : (int)$alpha_suite_seconds;

      // CTA (fallback = swipe)
      $alpha_suite_cta_url  = !empty($alpha_suite_p['cta_url'])  ? esc_url($alpha_suite_p['cta_url']) : '';
      $alpha_suite_cta_text = !empty($alpha_suite_p['cta_text']) ? esc_html($alpha_suite_p['cta_text']) : 'Saiba mais';
      $alpha_suite_cta_type = !empty($alpha_suite_p['cta_type']) ? $alpha_suite_p['cta_type'] : ($alpha_suite_cta_url ? 'swipe' : '');
      $alpha_suite_cta_icon = !empty($alpha_suite_p['cta_icon']) ? esc_url($alpha_suite_p['cta_icon']) : '';
      $alpha_suite_is_first = ($alpha_suite_i === 1);
      if ($alpha_suite_is_first && $alpha_suite_cta_type === 'button') $alpha_suite_cta_type = 'swipe';

      // Animações só a partir do 2º slide
      $alpha_suite_anim = ($alpha_suite_i > 1);
      // presets por estilo:
      $alpha_suite_anim_card_div = $alpha_suite_anim ? ' animate-in="fly-in-right" animate-in-delay="0s" animate-in-duration="350ms" animate-in-timing-function="ease-out"' : '';
      $alpha_suite_anim_h2_clean = $alpha_suite_anim ? ' animate-in="fly-in-bottom" animate-in-delay="0.08s" animate-in-duration="360ms" animate-in-timing-function="ease-out"' : '';
      $alpha_suite_anim_p_clean  = $alpha_suite_anim ? ' animate-in="fade-in"      animate-in-delay="0.20s" animate-in-duration="360ms" animate-in-timing-function="ease-out"' : '';

      $alpha_suite_anim_left_split = $alpha_suite_anim ? ' animate-in="fly-in-left"  animate-in-delay="0s"    animate-in-duration="360ms" animate-in-timing-function="ease-out"' : '';
      $alpha_suite_anim_h2_split   = $alpha_suite_anim ? ' animate-in="fade-in"       animate-in-delay="0.12s" animate-in-duration="360ms" animate-in-timing-function="ease-out"' : '';
      $alpha_suite_anim_p_split    = $alpha_suite_anim ? ' animate-in="fly-in-bottom" animate-in-delay="0.22s" animate-in-duration="360ms" animate-in-timing-function="ease-out"' : '';
    ?>

      <amp-story-page
        id="p<?php echo (int)$alpha_suite_i; ?>"
        <?php if ($alpha_suite_autoplay): ?>auto-advance-after="<?php echo (int)$alpha_suite_dur; ?>s" <?php endif; ?>>

        <?php if ($alpha_suite_style === 'card'): ?>
          <amp-story-grid-layer template="fill">
            <?php if ($alpha_suite_img): ?>
              <amp-img layout="fill" src="<?php echo esc_attr($alpha_suite_img); ?>" alt=""></amp-img>
            <?php else: ?>
              <div class="bg"></div>
            <?php endif; ?>
            <div class="overlay"></div>
          </amp-story-grid-layer>

          <amp-story-grid-layer template="vertical" class="layer-content">
            <div class="card"
              <?php if ($alpha_suite_img): ?>style="background-image:url('<?php echo esc_url($alpha_suite_img); ?>');" <?php endif; ?>
              <?php echo esc_attr($alpha_suite_anim_card_div); ?>></div>

            <?php if (!empty($alpha_suite_p['heading'])): ?>
              <h2 class="h2"><?php echo esc_html($alpha_suite_p['heading']); ?></h2>
            <?php endif; ?>
            <?php if (!empty($alpha_suite_p['body'])): ?>
              <p class="p"><?php echo esc_html($alpha_suite_p['body']); ?></p>
            <?php endif; ?>
          </amp-story-grid-layer>

        <?php elseif ($alpha_suite_style === 'top'): ?>
          <amp-story-grid-layer template="fill">
            <div class="bg-solid"></div>
          </amp-story-grid-layer>

          <amp-story-grid-layer template="vertical" class="layer-content-top" style="padding:0;display:block">
            <div class="hero" <?php echo esc_attr($alpha_suite_anim_card_div); ?>>
              <?php if ($alpha_suite_img): ?>
                <amp-img layout="fill" src="<?php echo esc_url($alpha_suite_img); ?>" alt=""></amp-img>
              <?php endif; ?>
            </div>

            <div class="content">
              <div class="content-inner">
                <?php if (!empty($alpha_suite_p['heading'])): ?>
                  <h2 class="h2"><?php echo esc_html($alpha_suite_p['heading']); ?></h2>
                <?php endif; ?>
                <?php if (!empty($alpha_suite_p['body'])): ?>
                  <p class="p"><?php echo esc_html($alpha_suite_p['body']); ?></p>
                <?php endif; ?>
              </div>
            </div>
          </amp-story-grid-layer>

        <?php elseif ($alpha_suite_style === 'split'): ?>
          <amp-story-grid-layer template="fill">
            <?php if ($alpha_suite_img): ?>
              <amp-img layout="fill" src="<?php echo esc_url($alpha_suite_img); ?>" alt=""></amp-img>
            <?php else: ?>
              <div class="bg"></div>
            <?php endif; ?>
            <div class="overlay"></div>
          </amp-story-grid-layer>

          <amp-story-grid-layer template="vertical">
            <div class="split">
              <div class="left"
                <?php if ($alpha_suite_img): ?>style="background-image:url('<?php echo esc_url($alpha_suite_img); ?>');" <?php endif; ?>
                <?php echo esc_attr($alpha_suite_anim_left_split); ?>></div>
              <div class="right">
                <?php if (!empty($alpha_suite_p['heading'])): ?>
                  <h2 class="h2"><?php echo esc_html($alpha_suite_p['heading']); ?></h2>
                <?php endif; ?>
                <?php if (!empty($alpha_suite_p['body'])): ?>
                  <p class="p"><?php echo esc_html($alpha_suite_p['body']); ?></p>
                <?php endif; ?>
              </div>
            </div>
          </amp-story-grid-layer>

        <?php else: ?>
          <amp-story-grid-layer template="fill">
            <?php if ($alpha_suite_img): ?>
              <amp-img layout="fill" src="<?php echo esc_url($alpha_suite_img); ?>" alt=""></amp-img>
            <?php else: ?>
              <div class="bg"></div>
            <?php endif; ?>
            <?php if ($alpha_suite_style === 'dark-left'): ?><div class="overlay"></div><?php endif; ?>
          </amp-story-grid-layer>

          <amp-story-grid-layer template="vertical" class="layer-content pad">
            <?php if (!empty($alpha_suite_p['heading'])): ?>
              <h2 class="h2"><?php echo esc_html($alpha_suite_p['heading']); ?></h2>
            <?php endif; ?>
            <?php if (!empty($alpha_suite_p['body'])): ?>
              <p class="p"><?php echo esc_html($alpha_suite_p['body']); ?></p>
            <?php endif; ?>
          </amp-story-grid-layer>
        <?php endif; ?>
        <?php if ($alpha_logo_src): ?>
          <amp-story-grid-layer template="fill">
            <div class="brand" style="padding-top: 30px; padding-left: 18px;">
              <div class="logo">
                <amp-img
                  src="<?php echo esc_url($alpha_logo_src); ?>"
                  width="36"
                  height="36"
                  layout="responsive"
                  alt="<?php echo esc_attr($alpha_storys_publisher); ?>">
                </amp-img>
              </div>
            </div>
          </amp-story-grid-layer>
        <?php endif; ?>
        <!-- CTA fica igual -->
        <?php if ($alpha_suite_cta_url): ?>
          <?php if ($alpha_suite_cta_type === 'button' && !$alpha_suite_is_first): ?>
            <amp-story-cta-layer>
              <a class="btn"
                href="<?php echo esc_url($alpha_suite_cta_url); ?>"
                target="_blank"
                rel="noreferrer">
                <?php echo esc_html($alpha_suite_cta_text); ?>
              </a>
            </amp-story-cta-layer>
          <?php elseif ($alpha_suite_cta_type === 'swipe'): ?>
            <amp-story-page-outlink
              layout="nodisplay"
              theme="dark"
              <?php if ($alpha_suite_cta_icon): ?>cta-image="<?php echo esc_attr($alpha_suite_cta_icon); ?>" <?php endif; ?>>
              <a href="<?php echo esc_url($alpha_suite_cta_url); ?>" target="_blank" rel="noreferrer"><?php echo esc_html($alpha_suite_cta_text); ?></a>
            </amp-story-page-outlink>
          <?php endif; ?>
        <?php endif; ?>
      </amp-story-page>

    <?php
      $alpha_suite_i++;
    endforeach;
    // phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

    ?>
  </amp-story>
</body>

</html>