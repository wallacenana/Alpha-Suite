<?php
if (!defined('ABSPATH')) exit;

class AlphaSuite_CPT_Posts_Orion
{
  /**
   * Slug interno do módulo no sistema de licença.
   */
  const MODULE_SLUG = 'post-orion';

  /**
   * Option usada na tela de Links Permanentes para base dos Órion Posts.
   * Ex.: 'orion', 'ia-posts' etc.
   */
  const OPTION_BASE = 'pga_posts_base';

  /**
   * Query var interna usada nas regras de rewrite para resolver o slug.
   */
  const QUERY_VAR = 'pga_posts_orion_slug';

  /**
   * Bootstrap
   */
  public static function init(): void
  {
    // já existentes
    add_action('init', [self::class, 'register']);
    add_filter('post_type_link', [self::class, 'filter_permalink'], 10, 4);

    if (is_admin()) {
      add_filter('post_row_actions', [self::class, 'filter_row_actions'], 10, 2);
      add_filter('get_edit_post_link', [self::class, 'filter_edit_link'], 10, 3);
      add_action('admin_notices', [self::class, 'admin_license_notices']);
    }

    add_action('transition_post_status', [self::class, 'block_publish_if_no_license'], 10, 3);

    add_action('pre_get_posts', [self::class, 'include_in_term_archives']);

    add_action('add_meta_boxes', function () {
      foreach (['posts_orion', 'post'] as $pt) {
        add_meta_box(
          'pga_regen_thumb',
          'Thumbnail',
          [self::class, 'pga_render_regen_thumb_box'],
          $pt,
          'side',
          'low'
        );
      }
    });

    add_action('wp_ajax_pga_regen_thumb', function () {
      if (
        ! isset($_POST['_wpnonce']) ||
        ! wp_verify_nonce(
          sanitize_text_field(wp_unslash($_POST['_wpnonce'])),
          'pga_regen_thumb'
        )
      ) {
        wp_send_json_error(esc_html__('Nonce inválido.', 'alpha-suite'));
      }

      $post_id = intval($_POST['post_id'] ?? 0);
      if ($post_id <= 0) {
        wp_send_json_error('ID de post inválido.');
      }

      $post = get_post($post_id);
      if (!$post) {
        wp_send_json_error('Post inexistente.');
      }

      if (!current_user_can('edit_post', $post_id)) {
        wp_send_json_error('Sem permissão para editar este post.');
      }

      $raw_prompt = '';

      if (isset($_POST['prompt'])) {
        $raw_prompt = sanitize_text_field(wp_unslash($_POST['prompt']));
      }

      // 🔹 Descobre provider de IMAGEM (Orion settings)
      $imageProvider = 'pollinations';
      if (class_exists('AlphaSuite_Settings')) {
        $opts       = AlphaSuite_Settings::get();
        $orionPosts = $opts['orion_posts'] ?? [];
        if (!empty($orionPosts['images_provider'])) {
          $imageProvider = (string) $orionPosts['images_provider'];
        }
      }

      // 1) META-PROMPT (texto rico, com título + conteúdo)
      if ($raw_prompt === '') {
        if (!class_exists('AlphaSuite_Prompts')) {
          wp_send_json_error('Classe de prompts ausente.');
        }

        $title   = get_the_title($post_id) ?: '';
        $content = get_post_field('post_content', $post_id) ?: '';

        $content = wp_strip_all_tags($content);
        if (function_exists('wp_html_excerpt')) {
          $content = wp_html_excerpt($content, 800, '...');
        } else {
          if (mb_strlen($content) > 800) {
            $content = mb_substr($content, 0, 800) . '...';
          }
        }

        $locale = get_locale() ?: 'pt_BR';

        $meta_prompt = AlphaSuite_Prompts::build_post_thumbnail_regen_prompt(
          $title,
          $content,
          $locale,
          $imageProvider
        );
      } else {
        // Usuário já escreveu o meta-prompt manualmente
        $meta_prompt = $raw_prompt;
      }

      // 2) IA de TEXTO gera o PROMPT FINAL DE IMAGEM
      $final_prompt = $meta_prompt;

      if (class_exists('AlphaSuite_AI') && $raw_prompt === '') {
        // aqui ele vai usar openai/gemini conforme get_text_provider ou args
        $resolved = AlphaSuite_AI::image_prompt($meta_prompt, []);

        if (!is_wp_error($resolved) && is_string($resolved) && $resolved !== '') {
          $final_prompt = $resolved;
        }
      }

      if (!class_exists('AlphaSuite_Images')) {
        wp_send_json_error('Classe de imagem ausente.');
      }

      // 3) Gera a thumbnail com o provider de IMAGEM configurado
      $thumb_id = AlphaSuite_Images::generate_by_settings(
        $final_prompt,
        $post_id
      );

      if (is_wp_error($thumb_id)) {
        wp_send_json_error($thumb_id->get_error_message());
      }

      $thumb_id = (int) $thumb_id;
      if ($thumb_id <= 0) {
        wp_send_json_error('Falha ao gerar a nova thumbnail.');
      }

      // 🔥 FORÇA trocar a imagem destacada do post
      delete_post_thumbnail($post_id);          // opcional, mas ajuda a garantir
      set_post_thumbnail($post_id, $thumb_id);

      $thumb_html = '';
      if (function_exists('_wp_post_thumbnail_html')) {
        $thumb_html = _wp_post_thumbnail_html($thumb_id, $post_id);
      }

      // se quiser já devolver a nova URL pro JS atualizar na hora
      $new_thumb_url = get_the_post_thumbnail_url($post_id, 'full');

      wp_send_json_success([
        'thumb_id'   => $thumb_id,
        'thumb_url'  => $new_thumb_url,
        'thumb_html' => $thumb_html,
        'message'    => 'Thumbnail regenerada com sucesso.',
      ]);
    });
  }

  public static function pga_render_regen_thumb_box($post)
  {
    wp_nonce_field('pga_regen_thumb', 'pga_regen_thumb_nonce');

    echo '<p>Use IA para gerar ou substituir a imagem destacada deste Órion Post.</p>';

    echo '<p><label for="pga_regen_thumb_prompt"><strong>Prompt da imagem</strong></label><br />';
    echo '<textarea id="pga_regen_thumb_prompt" rows="3" style="width:100%;" placeholder="Ex.: Ilustração realista de um gato usando coleira com rastreador, fundo claro, estilo fotográfico, 16:9."></textarea></p>';

    echo '<button 
        type="button" 
        class="button button-primary" 
        id="pga_regen_thumb_btn"
        data-post="' . esc_attr($post->ID) . '">
        Gerar nova thumb
    </button>';

    echo '<div id="pga_regen_thumb_status" style="margin-top:8px;font-size:12px;color:#555;"></div>';

?>
    <script>
      jQuery(function($) {
        const $btn = $('#pga_regen_thumb_btn');
        const $status = $('#pga_regen_thumb_status');
        const $prompt = $('#pga_regen_thumb_prompt');

        function showAlert(type, title, text) {
          if (window.Swal) {
            Swal.fire({
              icon: type,
              title: title,
              html: text,
            });
          } else {
            alert(title + "\n\n" + $(text).text());
          }
        }

        $btn.on('click', function() {
          const postId = $(this).data('post');
          const nonce = $('#pga_regen_thumb_nonce').val();
          let prompt = ($prompt.val() || '').trim();

          if (!postId) {
            showAlert('error', 'Erro', 'ID de post inválido.');
            return;
          }


          $status.text('Gerando imagem... isso pode levar alguns segundos.');

          $.ajax({
            url: ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
              action: 'pga_regen_thumb',
              post_id: postId,
              prompt: prompt,
              _wpnonce: nonce
            },
            success: function(r) {
              if (!r || !r.success) {
                const msg = (r && r.data) ? r.data : 'Falha desconhecida.';
                $status.text('Erro: ' + msg);
                showAlert('error', 'Erro ao gerar imagem', '<p>' + msg + '</p>');
                return;
              }

              const data = r.data || {};
              $status.text('Thumbnail atualizada com sucesso!');

              // Atualiza o box de imagem destacada, se veio HTML
              if (data.thumb_html) {
                $('#postimagediv .inside').html(data.thumb_html);
              }


              showAlert('success', 'Imagem gerada!',
                '<p>A nova thumbnail foi criada e aplicada ao post.</p>'
              );
            },
            error: function(xhr) {
              $status.text('Erro de comunicação com o servidor.');
              showAlert('error', 'Erro de comunicação', '<p>Falha ao contatar o servidor (AJAX).</p>');
            }
          });
        });
      });
    </script>
<?php
  }

  /**
   * Inclui o CPT posts_orion nas páginas de categoria e tag.
   */
  public static function include_in_term_archives($query): void
  {
    // somente front-end + query principal
    if (is_admin() || ! $query->is_main_query()) {
      return;
    }

    // category ou tag (se quiser só category, remove o is_tag)
    if (! $query->is_category() && ! $query->is_tag()) {
      return;
    }

    $post_types = $query->get('post_type');

    if (empty($post_types)) {
      // padrão do WP é "post", então a gente força os dois
      $post_types = ['post', 'posts_orion'];
    } elseif (is_string($post_types)) {
      $post_types = [$post_types];
    }

    if (! in_array('posts_orion', $post_types, true)) {
      $post_types[] = 'posts_orion';
    }

    $query->set('post_type', $post_types);
  }

  public static function admin_license_notices(): void
  {
    if (!function_exists('get_current_screen')) {
      return;
    }

    $screen = get_current_screen();
    if (!$screen) {
      return;
    }

    // Só nos interessa telas do nosso CPT
    if ($screen->post_type !== 'posts_orion') {
      return;
    }

    if (!class_exists('AlphaSuite_License')) {
      return;
    }

    $chk = AlphaSuite_License::check('alpha_orion');

    // 1) Aviso geral: licença/módulo não ativo
    if (empty($chk['ok'])) {
      // link para o painel Alpha Suite (ajusta o slug se for diferente)
      $url = admin_url('admin.php?page=alpha-suite-dashboard');

      $msg = $chk['message'] ?: __('Licença do módulo Alpha Órion inativa. Ative o módulo para continuar gerando e publicando posts.', 'alpha-suite');

      echo '<div class="notice notice-error is-dismissible"><p>'
        . esc_html($msg)
        . ' <a href="' . esc_url($url) . '">'
        . esc_html__('Clique aqui para ativar a licença.', 'alpha-suite')
        . '</a></p></div>';
    }

    // 2) Aviso específico na tela de edição se a publicação foi bloqueada
    if ('post' === $screen->base) {
      global $post;
      $post_id = ($post instanceof \WP_Post) ? (int) $post->ID : 0;

      if (!$post_id) {
        return;
      }

      $reason = get_post_meta($post_id, '_pga_blocked_publish_reason', true);
      if ($reason) {
        // Mensagem mais amigável independente do código
        $msg2 = __('Este post não pôde ser publicado porque a licença do módulo Alpha Órion não está ativa ou não inclui este módulo.', 'alpha-suite');

        echo '<div class="notice notice-warning is-dismissible"><p>'
          . esc_html($msg2)
          . '</p></div>';

        // remove a meta pra não ficar mostrando pra sempre
        delete_post_meta($post_id, '_pga_blocked_publish_reason');
      }
    }
  }

  public static function register(): void
  {
    $labels = [
      'name'               => __('Órion Posts', 'alpha-suite'),
      'singular_name'      => __('Órion Post', 'alpha-suite'),
      'menu_name'          => __('Órion Posts', 'alpha-suite'),
      'add_new'            => __('Adicionar novo', 'alpha-suite'),
      'add_new_item'       => __('Adicionar novo Órion Post', 'alpha-suite'),
      'edit_item'          => __('Editar Órion Post', 'alpha-suite'),
      'new_item'           => __('Novo Órion Post', 'alpha-suite'),
      'view_item'          => __('Ver Órion Post', 'alpha-suite'),
      'search_items'       => __('Buscar Órion Posts', 'alpha-suite'),
      'not_found'          => __('Nenhum Órion Post encontrado', 'alpha-suite'),
      'not_found_in_trash' => __('Nenhum Órion Post na lixeira', 'alpha-suite'),
      'all_items'          => __('Órion Posts', 'alpha-suite'),
    ];

    $supports = [
      'title',
      'editor',
      'author',
      'thumbnail',
      'excerpt',
      'trackbacks',
      'custom-fields',
      'comments',
      'revisions',
      'page-attributes',
      'post-formats',
    ];

    register_post_type('posts_orion', [
      'public'             => true,
      'show_ui'            => true,
      'show_in_menu'       => false,
      'menu_icon'          => 'dashicons-edit',
      'labels'             => $labels,
      'show_in_rest'       => true,
      'supports'           => $supports,
      'taxonomies'         => ['category', 'post_tag'],
      'capability_type'    => 'post',
      'publicly_queryable' => true,

      // ✅ permalink fixo: /orion/slug
      'rewrite' => [
        'slug'       => 'orion',
        'with_front' => false,
      ],
      'has_archive' => 'orion',
      'query_var'   => true,
    ]);
  }



  /**
   * Ajusta o permalink dos posts_orion para bater com as nossas regras:
   * - base vazia:   /slug
   * - base "orion": /orion/slug
   */
  public static function filter_permalink($permalink, $post, $leavename, $sample)
  {
    if (!($post instanceof \WP_Post)) {
      return $permalink;
    }

    if ($post->post_type !== 'posts_orion') {
      return $permalink;
    }

    $base = 'orion';
    if ($sample) {
      // Se o permalink original tem %postname%, mantemos ele
      if (strpos($permalink, '%postname%') !== false) {
        $slug_part = '%postname%';
      } else {
        // fallback: usa o marcador mesmo assim
        $slug_part = '%postname%';
      }

      $path = $base . '/' . $slug_part;
      return home_url(user_trailingslashit($path));
    }

    // Caso normal (front, links reais)
    $slug = $post->post_name ?: sanitize_title($post->post_title);
    $path = $base . '/' . $slug;

    return home_url(user_trailingslashit($path));
  }


  /**
   * Remove ações (Editar, Edição rápida, Ver) para posts_orion
   * quando a licença do módulo não está ok E o post não está publicado.
   */
  public static function filter_row_actions($actions, $post)
  {
    if (!($post instanceof \WP_Post)) {
      return $actions;
    }

    if ($post->post_type !== 'posts_orion') {
      return $actions;
    }

    if (!class_exists('AlphaSuite_License')) {
      return $actions;
    }

    $chk = AlphaSuite_License::check('alpha_orion');

    // Se licença ok OU post já publicado → deixa tudo normal
    if (!empty($chk['ok']) || $post->post_status === 'publish') {
      return $actions;
    }

    // Licença não ok + post NÃO publicado → remove edições/visualização
    unset($actions['edit']);
    unset($actions['inline hide-if-no-js']); // Edição rápida
    unset($actions['view']);

    return $actions;
  }

  /**
   * Remove o link de edição do título quando licença não está ok
   * e o post ainda não foi publicado.
   */
  public static function filter_edit_link($link, $post_id)
  {
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'posts_orion') {
      return $link;
    }

    if (!class_exists('AlphaSuite_License')) {
      return $link;
    }

    $chk = AlphaSuite_License::check('alpha_orion');

    // Licença ok ou post publicado → mantém link
    if (!empty($chk['ok']) || $post->post_status === 'publish') {
      return $link;
    }

    // Licença não ok + não publicado → sem link de edição
    return '';
  }

  /**
   * Bloqueia a publicação (inclui cron do WP) quando a licença/módulo não está ok.
   * - Só age em posts_orion
   * - Só quando status está indo PARA publish
   * - Não interfere em updates de posts já publicados.
   */
  public static function block_publish_if_no_license($new_status, $old_status, $post)
  {
    if (!($post instanceof \WP_Post)) {
      return;
    }

    // Só nos importa o nosso CPT
    if ($post->post_type !== 'posts_orion') {
      return;
    }

    // Só queremos quando está indo pra "publish"
    if ($new_status !== 'publish') {
      return;
    }

    // Se já era publish, ignora (edição de post já publicado)
    if ($old_status === 'publish') {
      return;
    }

    if (!class_exists('AlphaSuite_License')) {
      return;
    }

    $chk = AlphaSuite_License::check('alpha_orion');

    // Se licença OK, deixa publicar normal
    if (!empty($chk['ok'])) {
      return;
    }

    // Evita loop recursivo ao chamar wp_update_post
    remove_action('transition_post_status', [self::class, 'block_publish_if_no_license'], 10);

    // Volta o post para "draft"
    wp_update_post([
      'ID'          => $post->ID,
      'post_status' => 'draft',
    ]);

    // Marca meta explicando o motivo (pra usar em avisos se quiser)
    add_post_meta(
      $post->ID,
      '_pga_blocked_publish_reason',
      $chk['code'] ?? 'licenca_invalida',
      true
    );

    // Re-anexa o hook
    add_action('transition_post_status', [self::class, 'block_publish_if_no_license'], 10, 3);
  }
}
