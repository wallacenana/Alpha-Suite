<?php
if (!defined('ABSPATH')) exit;

define('ALPHA_STORYS_FILE', __FILE__);
define('ALPHA_STORYS_PATH', plugin_dir_path(__FILE__));
define('ALPHA_STORYS_URL',  plugin_dir_url(__FILE__));

// slug interno do módulo no sistema de licença (ajuste se usar outro nome)
if (!defined('PGA_STORIES_MODULE_SLUG')) {
  define('PGA_STORIES_MODULE_SLUG', 'alpha_stories');
}

require_once ALPHA_STORYS_PATH . 'includes/plugin.php';

// registra CPT
add_action('init', 'alpha_register_cpt_storys', 0);

function alpha_register_cpt_storys()
{
  require_once PGA_PATH . 'includes/stories/includes/MetaBox.php';
  require_once PGA_PATH . 'includes/stories/includes/Generate.php';
  require_once PGA_PATH . 'includes/stories/includes/Helpers.php';
  require_once PGA_PATH . 'includes/stories/includes/StoriesRest.php';
  add_action('rest_api_init', ['AlphaSuite_StoriesRest', 'register_routes']);

  AlphaSuite_Stories_MetaBox::init();
  AlphaSuite_Generate::init();

  if (empty($p['image']) && !empty($p['image_id'])) {
    $p['image'] = wp_get_attachment_image_url((int)$p['image_id'], 'alpha_storys_slide')
      ?: wp_get_attachment_image_url((int)$p['image_id'], 'full');
  }
  // base sempre com algo válido
  $base = alpha_storys_get_base_slug();

  $args = [
    'label'               => esc_html('Alpha Stories', 'alpha-storys'),
    'public'              => true,
    'publicly_queryable'  => true,
    'show_ui'             => true,
    'show_in_menu'        => false,
    'show_in_admin_bar'   => true,
    'show_in_nav_menus'   => true,
    'show_in_rest'        => true,
    'menu_icon'           => 'dashicons-slides',
    'menu_position'       => 20,
    'capability_type'     => 'post',
    'map_meta_cap'        => true,
    'hierarchical'        => false,

    // agora o arquivo e o slug batem com a base
    'has_archive'         => $base,
    'exclude_from_search' => false,
    'supports'            => [
      'title',
      'editor',
      'thumbnail',
      'excerpt',
      'author',
      'comments',
      'custom-fields',
      'revisions'
    ],
    'taxonomies'          => ['category'],

    // rewrite sempre com slug válido
    'rewrite'             => [
      'slug'       => $base,
      'with_front' => false,
      'feeds'      => false,
      'pages'      => true,
    ],
  ];

  register_post_type('alpha_storys', $args);
}


// Flush quando a base mudar nos Links Permanentes
add_action('update_option_pga_story_base', function ($old, $new) {
  if ($old !== $new) flush_rewrite_rules(false);
}, 10, 2);


function alpha_storys_get_base_slug(): string
{
  // valor salvo na tela de Links Permanentes (Alpha Suite / Stories)
  $base = trim((string) get_option('pga_story_base', ''), '/');

  // se estiver vazio, usamos um padrão seguro
  if ($base === '') {
    $base = 'stories'; // pode trocar por 'web-stories', 'alpha-stories', etc.
  }

  return $base;
}


/**
 * ======================
 *  BLOQUEIOS POR LICENÇA
 * ======================
 */

/**
 * Helper: retorna resultado do check de licença do módulo Stories.
 */
function alpha_storys_license_check(): array
{
  if (!class_exists('AlphaSuite_License')) {
    // se a classe não existir, não vamos travar nada pra não quebrar o admin
    return ['ok' => true, 'code' => 'no_license_class', 'message' => ''];
  }

  return AlphaSuite_License::check(PGA_STORIES_MODULE_SLUG);
}

/**
 * Remove ações (Editar, Edição rápida, Ver) quando licença não está ok
 * e o story ainda não foi publicado.
 */
add_filter('post_row_actions', function ($actions, $post) {
  if (!($post instanceof WP_Post)) {
    return $actions;
  }

  if ($post->post_type !== 'alpha_storys') {
    return $actions;
  }

  $chk = alpha_storys_license_check();

  // Se licença ok OU story já publicado → deixa tudo normal
  if (!empty($chk['ok']) || $post->post_status === 'publish') {
    return $actions;
  }

  // Licença não ok + story NÃO publicado → remove edições/visualização
  unset($actions['edit']);
  unset($actions['inline hide-if-no-js']); // Edição rápida
  unset($actions['view']);

  return $actions;
}, 10, 2);

/**
 * Remove o link de edição do título quando licença não está ok
 * e o story ainda não foi publicado.
 */
add_filter('get_edit_post_link', function ($link, $post_id, $context) {
  $post = get_post($post_id);
  if (!$post || $post->post_type !== 'alpha_storys') {
    return $link;
  }

  $chk = alpha_storys_license_check();

  // Licença ok ou story publicado → mantém link normal
  if (!empty($chk['ok']) || $post->post_status === 'publish') {
    return $link;
  }

  // Licença não ok + não publicado → sem link de edição
  return '';
}, 10, 3);


/**
 * Bloqueia a publicação (inclui cron do WP) quando a licença/módulo não está ok.
 * - Só age em alpha_storys
 * - Só quando status está indo PARA publish
 * - Não interfere em updates de stories já publicados.
 */
add_action('transition_post_status', function ($new_status, $old_status, $post) {
  if (!($post instanceof WP_Post)) {
    return;
  }

  if ($post->post_type !== 'alpha_storys') {
    return;
  }

  // Só queremos quando está indo pra "publish"
  if ($new_status !== 'publish') {
    return;
  }

  // Se já era publish, ignora (edição/delegada)
  if ($old_status === 'publish') {
    return;
  }

  $chk = alpha_storys_license_check();

  // Se licença OK, deixa publicar normal
  if (!empty($chk['ok'])) {
    return;
  }

  // Evita loop recursivo ao chamar wp_update_post
  remove_action('transition_post_status', __FUNCTION__, 10);

  // Volta o post para "draft"
  wp_update_post([
    'ID'          => $post->ID,
    'post_status' => 'draft',
  ]);

  // Marca meta explicando o motivo (pra mostrar aviso depois)
  add_post_meta(
    $post->ID,
    '_pga_story_blocked_publish_reason',
    $chk['code'] ?? 'licenca_invalida',
    true
  );

  // Reanexa o hook
  add_action('transition_post_status', __FUNCTION__, 10, 3);
}, 10, 3);


/**
 * Avisos no admin:
 * 1) Aviso geral se a licença do módulo Stories não estiver ativa.
 * 2) Aviso específico na tela de edição quando a publicação foi bloqueada.
 */
add_action('admin_notices', function () {
  if (!function_exists('get_current_screen')) {
    return;
  }

  $screen = get_current_screen();
  if (!$screen) {
    return;
  }

  // Só telas relacionadas ao CPT alpha_storys
  if ($screen->post_type !== 'alpha_storys') {
    return;
  }

  $chk = alpha_storys_license_check();

  // 1) Aviso geral de licença/módulo não ativo
  if (empty($chk['ok'])) {
    $url = admin_url('admin.php?page=alpha-suite-license');

    $msg = $chk['message'] ?: __('Licença do módulo Alpha Stories inativa. Ative o módulo para continuar criando e publicando stories.', 'alpha-suite');

    echo '<div class="notice notice-error is-dismissible"><p>'
      . esc_html($msg)
      . ' <a href="' . esc_url($url) . '">'
      . esc_html__('Clique aqui para ativar a licença.', 'alpha-suite')
      . '</a></p></div>';
  }

  // 2) Aviso específico na tela de edição se a publicação foi bloqueada
  if ('post' === $screen->base) {

    $post_id = 0;

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    if (isset($_GET['post'])) {
      $post_id = absint(wp_unslash($_GET['post']));
    }
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    if ($post_id > 0) {
      $reason = get_post_meta($post_id, '_pga_story_blocked_publish_reason', true);

      if ($reason) {
        $msg2 = esc_html__(
          'Este story não pôde ser publicado porque a licença do módulo Alpha Stories não está ativa ou não inclui este módulo.',
          'alpha-suite'
        );

        printf(
          '<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
          esc_html($msg2)
        );

        // Remove a meta pra não mostrar o aviso pra sempre
        delete_post_meta($post_id, '_pga_story_blocked_publish_reason');
      }
    }
  }
});
