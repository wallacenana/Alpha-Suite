<?php
// includes/stories/MetaBox.php
if (!defined('ABSPATH')) exit;

class AlphaSuite_Stories_MetaBox
{
  const NONCE = 'alpha_storys_meta_nonce';

  public static function init(): void
  {
    add_action('add_meta_boxes',        [self::class, 'add_box']);
    add_action('save_post_alpha_storys', [self::class, 'save'], 10, 2);
    add_action('admin_enqueue_scripts', function ($hook) {
      // post.php = editar, post-new.php = novo
      if ($hook !== 'post.php' && $hook !== 'post-new.php') return;

      // (opcional) limita ao teu post type
      $screen = get_current_screen();
      if (!$screen) return;

      // se teu metabox é em um CPT específico, ex:
      // if ($screen->post_type !== 'web-story' && $screen->post_type !== 'pga_story') return;

      // ✅ isso carrega o wp.media e dependências
      wp_enqueue_media();

      // teu JS do picker
      wp_enqueue_script(
        'pga-metabox-media',
        plugins_url('../../../assets/metabox-media.js', __FILE__),
        ['jquery'],
        '1.0.1',
        true
      );
    });
  }

  public static function add_box(): void
  {
    add_meta_box(
      'alpha_storys_sidebar_meta',
      __('Opções', 'alpha-suite'),
      [self::class, 'render'],
      'alpha_storys',
      'side',
      'default'
    );
  }

  public static function render(\WP_Post $post): void
  {
    wp_nonce_field(self::NONCE, self::NONCE);

    // SETTINGS (fallback)
    $opt = get_option('pga_settings', []);
    $st  = is_array($opt['stories'] ?? null) ? $opt['stories'] : [];

    $set_autoplay  = isset($st['autoplay']) ? (string) $st['autoplay'] : '';   // '1'|'0'|''
    $set_duration  = isset($st['duration']) ? (string) $st['duration'] : '';
    $set_show_ctrl = isset($st['show_controls']) ? (string) $st['show_controls'] : '';

    $set_style = isset($st['default_style']) ? (string) $st['default_style'] : '';
    $set_font  = isset($st['default_font'])  ? (string) $st['default_font']  : '';

    $set_bg     = isset($st['background_color']) ? (string) $st['background_color'] : '';
    $set_txt    = isset($st['text_color'])       ? (string) $st['text_color']       : '';
    $set_accent = isset($st['accent_color'])     ? (string) $st['accent_color']     : '';

    // META (cru) — não forçar default aqui
    $m_autoplay  = get_post_meta($post->ID, '_storys_autoplay', true);         // ''|'1'|'0'
    $m_duration  = get_post_meta($post->ID, '_storys_duration', true);         // ''|'7' etc
    $m_show_ctrl = get_post_meta($post->ID, '_storys_show_controls', true);    // ''|'1'|'0'

    $m_style = get_post_meta($post->ID, '_storys_style', true);                // ''|'clean'...
    $m_font  = get_post_meta($post->ID, '_storys_font', true);

    $m_bg     = get_post_meta($post->ID, '_storys_background_color', true);    // ''|'#...'
    $m_txt    = get_post_meta($post->ID, '_storys_text_color', true);
    $m_accent = get_post_meta($post->ID, '_storys_accent_color', true);

    // ===== Efetivos (para UI/preview) =====
    // bools: meta tem prioridade quando NÃO está vazio
    $autoplay  = ($m_autoplay !== '' && $m_autoplay !== null) ? (int)$m_autoplay
      : (($set_autoplay !== '' && $set_autoplay !== null) ? (int)$set_autoplay : 1);

    $show_ctrl = ($m_show_ctrl !== '' && $m_show_ctrl !== null) ? (int)$m_show_ctrl
      : (($set_show_ctrl !== '' && $set_show_ctrl !== null) ? (int)$set_show_ctrl : 1);

    // strings
    $duration = ($m_duration !== '' && $m_duration !== null) ? (string)$m_duration
      : ($set_duration !== '' ? (string)$set_duration : '7');

    $style = ($m_style !== '' && $m_style !== null) ? (string)$m_style
      : ($set_style !== '' ? (string)$set_style : 'clean');

    $font  = ($m_font !== '' && $m_font !== null) ? (string)$m_font
      : ($set_font !== '' ? (string)$set_font : 'plusjakarta');

    // cores: sanitiza e só usa fallback manual se settings também não tiver
    $bg_color   = sanitize_hex_color($m_bg) ?: (sanitize_hex_color($set_bg) ?: '#ffffff');
    $text_color = sanitize_hex_color($m_txt) ?: (sanitize_hex_color($set_txt) ?: '#111111');
    $accent     = sanitize_hex_color($m_accent) ?: (sanitize_hex_color($set_accent) ?: '#1C5CF4');

    // ===== Logo =====
    $meta_id = (int) get_post_meta($post->ID, '_alpha_storys_logo_id', true);

    $opt = get_option('pga_settings', []);
    $default_id = (int) ($opt['stories']['publisher_logo_id'] ?? 0);

    $effective_id  = $meta_id ?: $default_id;
    $effective_url = $effective_id ? wp_get_attachment_image_url($effective_id, 'full') : '';


?>
    <style>
      .alpha-field {
        margin-bottom: 10px;
      }

      .alpha-field label {
        font-weight: 600;
        display: block;
        margin-bottom: 4px;
      }

      .alpha-thumb {
        display: block;
        width: 100%;
        max-width: 100%;
        height: auto;
        margin: 6px 0;
        border: 1px solid #eee;
        border-radius: 6px;
      }

      .alpha-row {
        display: flex;
        gap: 8px;
        align-items: center;
      }

      .alpha-row>* {
        flex: 1;
      }

      .alpha-help {
        color: #666;
        font-size: 11px;
        margin-top: 2px;
      }

      .alpha-muted {
        color: #888;
        font-size: 12px;
      }

      .alpha-sep {
        border-top: 1px solid #eee;
        margin: 10px 0;
      }
    </style>

    <!-- Habilitar: não faz sentido nessa tela, então usamos hidden sempre = 1 -->
    <input type="hidden" name="storys_enable" value="1" />

    <div class="alpha-field">
      <label>
        <input type="checkbox" name="storys_autoplay" value="1" checked <?php checked($autoplay, 1); ?>>
        <?php esc_html_e('Autoplay', 'alpha-suite'); ?>
      </label>
    </div>

    <div class="alpha-field">
      <label for="storys_duration"><?php esc_html_e('Tempo por página (s)', 'alpha-suite'); ?></label>
      <select name="storys_duration" id="storys_duration">
        <?php foreach (['5', '7', '10', '12'] as $d): ?>
          <option value="<?php echo esc_attr($d); ?>" <?php selected($duration, $d); ?>>
            <?php echo esc_html($d); ?>s
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="alpha-field">
      <label>
        <input type="checkbox" name="storys_show_controls" value="1" checked <?php checked($show_ctrl, 1); ?>>
        <?php esc_html_e('Mostrar botão Play/Pause', 'alpha-suite'); ?>
      </label>
    </div>

    <div class="alpha-field">
      <label for="storys_style"><?php esc_html_e('Preset de estilo', 'alpha-suite'); ?></label>
      <select name="storys_style" id="storys_style">
        <?php
        $choices = [
          'top'       => 'Image top',
          'clean'     => 'Clean',
          'dark-left' => 'Dark Left',
          'card'      => 'Card',
          'split'     => 'Split',
        ];
        foreach ($choices as $val => $lab) {
          printf(
            '<option value="%s"%s>%s</option>',
            esc_attr($val),
            selected($style, $val, false),
            esc_html($lab)
          );
        }
        ?>
      </select>
    </div>

    <div class="alpha-field">
      <label for="storys_font"><?php esc_html_e('Fonte', 'alpha-suite'); ?></label>
      <select name="storys_font" id="storys_font">
        <?php
        $fonts = [
          'system'       => 'System UI',
          'inter'        => 'Inter',
          'poppins'      => 'Poppins',
          'merriweather' => 'Merriweather',
          'plusjakarta'  => 'Plus Jakarta Sans',
        ];
        foreach ($fonts as $val => $lab) {
          printf(
            '<option value="%s"%s>%s</option>',
            esc_attr($val),
            selected($font, $val, false),
            esc_html($lab)
          );
        }
        ?>
      </select>
    </div>

    <div class="alpha-row">
      <div class="alpha-field">
        <label><?php esc_html_e('Cor de fundo', 'alpha-suite'); ?></label>
        <input type="color" class="alpha-color" name="storys_background_color"
          value="<?php echo esc_attr($bg_color); ?>">
      </div>
    </div>
    <div class="alpha-row">
      <div class="alpha-field">
        <label><?php esc_html_e('Cor do texto', 'alpha-suite'); ?></label>
        <input type="color" class="alpha-color" name="storys_text_color"
          value="<?php echo esc_attr($text_color); ?>">
      </div>
    </div>
    <div class="alpha-row">
      <div class="alpha-field">
        <label><?php esc_html_e('Cor de destaque', 'alpha-suite'); ?></label>
        <input type="color" class="alpha-color" name="storys_accent_color"
          value="<?php echo esc_attr($accent); ?>">
      </div>
    </div>

    <div class="alpha-sep"></div>
    <div class="alpha-field">
      <label><?php esc_html_e('Logo do Publisher', 'alpha-suite'); ?></label>

      <img id="alpha_storys_logo_preview" class="alpha-thumb"
        src="<?php echo esc_url($effective_url ?: ''); ?>"
        style="<?php echo $effective_url ? '' : 'display:none'; ?>">
      <input type="hidden"
        id="alpha_storys_logo_id"
        name="alpha_storys_logo_id"
        value="<?php echo (int) $meta_id; ?>">
      <button type="button" class="button"
        data-alpha-media-target="alpha_storys_logo_id"
        data-alpha-preview="alpha_storys_logo_preview">
        Selecionar imagem
      </button>


      <button type="button" class="button"
        data-alpha-media-clear="alpha_storys_logo_id"
        data-alpha-preview="alpha_storys_logo_preview"
        style="margin-left:6px;">
        <?php esc_html_e('Remover', 'alpha-suite'); ?>
      </button>

    </div>

<?php
  }

  public static function save(int $post_id, \WP_Post $post): void
  {
    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) return;
    if ($post->post_type !== 'alpha_storys') return;
    // Verifica nonce
    if (! isset($_POST[self::NONCE])) {
      return;
    }

    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    $nonce = wp_unslash($_POST[self::NONCE]);

    if (! wp_verify_nonce($nonce, self::NONCE)) {
      return;
    }

    // sempre habilitado nessa tela
    $enabled = 1;

    // autoplay (checkbox)
    $autoplay = 0;
    if (isset($_POST['storys_autoplay'])) {
      // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
      $autoplay = ! empty(wp_unslash($_POST['storys_autoplay'])) ? 1 : 0;
    }

    // duração
    $duration_raw = isset($_POST['storys_duration'])
      // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
      ? wp_unslash($_POST['storys_duration'])
      : '7';
    $duration = (string) absint($duration_raw);

    // controles (checkbox)
    $show_ctrl = 0;
    if (isset($_POST['storys_show_controls'])) {
      // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
      $show_ctrl = ! empty(wp_unslash($_POST['storys_show_controls'])) ? 1 : 0;
    }

    // style / font
    $style_raw = isset($_POST['storys_style'])
      // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
      ? wp_unslash($_POST['storys_style'])
      : 'clean';
    $style = sanitize_text_field($style_raw);

    $font_raw = isset($_POST['storys_font'])
      // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
      ? wp_unslash($_POST['storys_font'])
      : 'plusjakarta';
    $font = sanitize_text_field($font_raw);

    // cores

    $bg = isset($_POST['storys_background_color'])
      ? sanitize_hex_color(wp_unslash($_POST['storys_background_color']))
      : null;

    $txt = isset($_POST['storys_text_color'])
      ? sanitize_hex_color(wp_unslash($_POST['storys_text_color']))
      : null;

    $accent = isset($_POST['storys_accent_color'])
      ? sanitize_hex_color(wp_unslash($_POST['storys_accent_color']))
      : null;


    // bg
    if ($bg) {
      update_post_meta($post_id, '_alpha_storys_background_color', $bg);
    } else {
      delete_post_meta($post_id, '_alpha_storys_background_color');
    }

    // text
    if ($txt) {
      update_post_meta($post_id, '_alpha_storys_text_color', $txt);
    } else {
      delete_post_meta($post_id, '_alpha_storys_text_color');
    }

    // accent
    if ($accent) {
      update_post_meta($post_id, '_alpha_storys_accent_color', $accent);
    } else {
      delete_post_meta($post_id, '_alpha_storys_accent_color');
    }


    $poster_id = isset($_POST['storys_poster']) ? (int) $_POST['storys_poster'] : 0;
    // Logo do Publisher (override por post)
    $logo_id = isset($_POST['alpha_storys_logo_id'])
      ? absint(wp_unslash($_POST['alpha_storys_logo_id']))
      : 0;

    if ($logo_id > 0) {
      update_post_meta($post_id, '_alpha_storys_logo_id', $logo_id);
    } else {
      // se vazio, remove override e volta a usar o settings
      delete_post_meta($post_id, '_alpha_storys_logo_id');
    }


    update_post_meta($post_id, '_storys_enable',           $enabled);
    update_post_meta($post_id, '_storys_autoplay',         $autoplay);
    update_post_meta($post_id, '_storys_duration',         in_array($duration, ['5', '7', '10', '12'], true) ? $duration : '7');
    update_post_meta($post_id, '_storys_show_controls',    $show_ctrl);

    update_post_meta($post_id, '_storys_style',            $style);
    update_post_meta($post_id, '_storys_font',             $font);

    update_post_meta($post_id, '_storys_background_color', $bg);
    update_post_meta($post_id, '_storys_text_color',       $txt);
    update_post_meta($post_id, '_storys_accent_color',     $accent);

    update_post_meta($post_id, '_storys_poster',           $poster_id);

    // gera/atualiza páginas a partir do próprio conteúdo
    if ($enabled) {
      $pages = alpha_build_storys_pages_from_content($post->post_content);
      if (!empty($pages)) {
        $publisher = get_bloginfo('name');

        // neste fluxo, o próprio $post_id é a story final
        $storys_id = $post_id;

        if ($poster_id) {
          set_post_thumbnail($storys_id, $poster_id);
        }

        update_post_meta($storys_id, '_alpha_storys_source_post', $post_id);
        update_post_meta($storys_id, '_alpha_storys_pages',       $pages);
        update_post_meta($storys_id, '_alpha_storys_publisher',   sanitize_text_field($publisher));

        // estilos / cores / playback replicados com prefixo _alpha_
        update_post_meta($storys_id, '_alpha_storys_background_color', $bg);
        update_post_meta($storys_id, '_alpha_storys_text_color',       $txt);
        update_post_meta($storys_id, '_alpha_storys_accent_color',     $accent);
        update_post_meta($storys_id, '_alpha_storys_style',            $style);
        update_post_meta($storys_id, '_alpha_storys_font',             $font);
        update_post_meta($storys_id, '_alpha_storys_autoplay',         $autoplay);
        update_post_meta($storys_id, '_alpha_storys_duration',         in_array($duration, ['5', '7', '10', '12'], true) ? $duration : '7');
      }
    }
  }
}
