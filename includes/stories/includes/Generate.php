<?php
if (!defined('ABSPATH')) exit;

class AlphaSuite_Generate
{
  public static function init(): void
  {
    add_action('add_meta_boxes', [self::class, 'register_metabox']);
    add_action('wp_ajax_alpha_ai_generate_now', [AlphaSuite_Generate::class, 'alpha_ajax_ai_generate_now']);
    add_action('admin_enqueue_scripts', [self::class, 'enqueue_admin_assets']);
  }

  public static function register_metabox(): void
  {
    add_meta_box(
      'alpha_ai_box',
      'Gerar Stories',
      [self::class, 'alpha_ai_autogen_cb'],
      ['post', 'posts_orion'],
      'side',
      'high'
    );
  }

  public static function alpha_ai_autogen_cb($post)
  {
    // nonce específico do AJAX “gerar agora”
    $ajax_nonce = wp_create_nonce('alpha_ai_generate_now');

    // checa licença do módulo Stories
    $chk = class_exists('AlphaSuite_License')
      ? AlphaSuite_License::check('alpha_stories')
      : ['ok' => true, 'message' => ''];

    $disabled = empty($chk['ok']);

    $title = empty($chk['ok'])
      ? ($chk['message'] ?: __('Ative o módulo Alpha Stories para gerar automaticamente.', 'alpha-suite'))
      : '';
?>

    <p>
      <button
        type="button"
        class="button button-primary"
        id="alpha_ai_generate_now"
        <?php echo $disabled ? 'disabled="disabled"' : ''; ?>
        <?php
        if ($title) {
          printf('title="%s"', esc_attr($title));
        }
        ?>>
        Gerar story agora
      </button>
      <span
        id="alpha_ai_generate_now_status"
        data-license-ok="<?php echo !empty($chk['ok']) ? '1' : '0'; ?>"
        data-license-message="<?php echo esc_attr($chk['message'] ?? ''); ?>"
        style="margin-left:8px;">
        <?php
        // mensagem visível do lado do botão (opcional)
        if (empty($chk['ok']) && !empty($chk['message'])) {
          echo esc_html($chk['message']);
        }
        ?>
      </span>

    </p>
<?php
  }

  public static function enqueue_admin_assets($hook): void
  {
    // Só nas telas de edição/criação de post
    if ($hook !== 'post.php' && $hook !== 'post-new.php') {
      return;
    }

    $screen = get_current_screen();
    if (!$screen || !in_array($screen->post_type, ['post', 'posts_orion'], true)) {
      return;
    }

    // Enfileira o JS externo
    wp_enqueue_script(
      'alpha-suite-generate',
      PGA_URL . 'includes/stories/assets/js/alpha-suite-generate.js', // cria esse arquivo depois
      ['jquery'],
      defined('PLUGINS_ALPHA_VERSION') ? PLUGINS_ALPHA_VERSION : '1.0.0',
      true
    );
    wp_enqueue_script(
      'alpha-suite-sweetalert',
      PGA_URL . 'assets/vendor/sweetalert2@11.js',
      [],
      defined('PLUGINS_ALPHA_VERSION') ? PLUGINS_ALPHA_VERSION : '1.0.0',
      true
    );

    // (Opcional, mas bem útil) – passa dados pro JS
    $chk = class_exists('AlphaSuite_License')
      ? AlphaSuite_License::check('alpha_stories')
      : ['ok' => true, 'message' => ''];

    wp_localize_script('alpha-suite-generate', 'PGA_Generate', [
      'ajaxUrl'    => admin_url('admin-ajax.php'),
      'nonce'      => wp_create_nonce('alpha_ai_generate_now'),
      'postId'     => get_the_ID(),
      'licenseOk'  => !empty($chk['ok']),
      'licenseMsg' => (string) ($chk['message'] ?? ''),
      'licenseUrl' => admin_url('admin.php?page=alpha-suite-dashboard'),
    ]);

    wp_localize_script(
      'alpha-suite-generate',
      'PGA_Stories',
      [
        'ajaxUrl'    => admin_url('admin-ajax.php'),
        'nonce'      => wp_create_nonce('alpha_ai_generate_now'),
        'sourceId'   => (int) get_the_ID(),
        'licenseUrl' => admin_url('admin.php?page=alpha-suite-license'),
      ]
    );
  }

  public static function alpha_ajax_ai_generate_now()
  {
    // valida nonce do AJAX
    check_ajax_referer('alpha_ai_generate_now', 'nonce');
    $source_id = 0;

    // source_id
    if (isset($_POST['source_id'])) {
      // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
      $source_id = absint(wp_unslash($_POST['source_id']));
    }

    if (! $source_id && isset($_POST['post_id'])) {
      // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
      $source_id = absint(wp_unslash($_POST['post_id']));
    }
    $preview   = !empty($_POST['preview']);

    if (!$source_id || !get_post($source_id)) {
      wp_send_json_error(['message' => 'Post de origem inválido.'], 400);
    }

    // permissão: vai gravar storys irmã => precisa editar o post de origem
    if (!current_user_can('edit_post', $source_id)) {
      wp_send_json_error(['message' => 'Permissão negada.'], 403);
    }

    if (!AlphaSuite_Helpers::alpha_ai_get_api_key()) {
      wp_send_json_error(['message' => 'Configure a OpenAI API Key nas Configurações.'], 400);
    }

    // Gera (a função cria/atualiza a irmã alpha_storys e retorna target_id)
    $res = AlphaSuite_Helpers::alpha_ai_generate_for_post($source_id);
    if (is_wp_error($res)) {
      wp_send_json_error(['message' => $res->get_error_message()], 500);
    }

    $target_id = (int)($res['target_id'] ?? 0);
    if (!$target_id) {
      // fallback: tenta descobrir a irmã
      if (function_exists('alpha_storys_get_or_create_storys')) {
        $tmp = alpha_storys_get_or_create_storys($source_id);
        if (!is_wp_error($tmp)) $target_id = (int)$tmp;
      }
    }

    wp_send_json_success([
      'preview'  => (bool)$preview,
      'count'    => (int)($res['count'] ?? 0),
      'storysId'  => $target_id,
      'edit_url' => $target_id ? get_edit_post_link($target_id, 'raw') : '',
      'view_url' => $target_id ? get_permalink($target_id) : '',
      'message'  => 'Story gerada com sucesso.',
    ]);
  }
}
