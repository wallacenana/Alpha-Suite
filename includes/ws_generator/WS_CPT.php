<?php
// includes/ws/CPT.php
if (!defined('ABSPATH')) exit;

final class AlphaSuite_WS_CPT
{
    public const POST_TYPE = 'ws_generator';

    public static function init(): void
    {
        add_action('init', [__CLASS__, 'register']);

        // ✅ redirecionamentos (add new / edit / row actions)
        add_action('admin_init', [__CLASS__, 'admin_redirects']);

        // ações extras na lista
        add_filter('post_row_actions', [__CLASS__, 'row_actions'], 10, 2);

        // template do front
        add_filter('template_include', [__CLASS__, 'template_include']);

        // opcional: evita admin bar no front (admin bar quebra AMP se aparecer)
        add_filter('show_admin_bar', [__CLASS__, 'maybe_hide_admin_bar']);
    }

    public static function get_base_slug(): string
    {
        $base = trim((string) get_option('pga_story_base', ''), '/');
        if ($base === '') $base = 'webstories';
        return $base;
    }


    public static function register(): void
    {
        $base = self::get_base_slug();

        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name'          => __('Web Stories', 'alpha-suite'),
                'singular_name' => __('Web Story', 'alpha-suite'),
                'add_new'       => __('Adicionar', 'alpha-suite'),
                'add_new_item'  => __('Adicionar Web Story', 'alpha-suite'),
                'edit_item'     => __('Editar Web Story', 'alpha-suite'),
            ],

            'public'              => true,
            'publicly_queryable'  => true,
            'exclude_from_search' => false,

            'show_ui'            => true,
            'show_in_menu'       => false, // a gente cria nosso submenu manualmente
            'show_in_admin_bar'  => true,
            'show_in_nav_menus'  => true,
            'show_in_rest'       => true,
            'rest_base'          => $base,

            'menu_icon'          => 'dashicons-slides',
            'capability_type'    => 'post',
            'map_meta_cap'       => true,
            'hierarchical'       => false,

            'supports' => ['title', 'thumbnail', 'excerpt', 'author', 'revisions'],
            'taxonomies' => ['category', 'post_tag'],

            'rewrite' => [
                'slug'       => $base,
                'with_front' => false,
                'feeds'      => false,
                'pages'      => true,
            ],

            'has_archive' => false,
        ]);
    }

    public static function row_actions(array $actions, \WP_Post $post): array
    {
        if ($post->post_type !== self::POST_TYPE) return $actions;

        // abre no builder
        $url = admin_url('admin.php?page=alpha-suite-ws-generator&story_id=' . (int)$post->ID);
        $actions['pga_ws_open'] = '<a href="' . esc_url($url) . '">' . esc_html__('Abrir no Builder', 'alpha-suite') . '</a>';

        return $actions;
    }

    public static function template_include(string $template): string
    {
        if (!is_singular(self::POST_TYPE)) return $template;

        // template dentro do plugin
        $tpl = PGA_PATH . 'includes/ws_generator/templates/single-pga_ws.php';
        if (file_exists($tpl)) return $tpl;

        return $template;
    }

    public static function maybe_hide_admin_bar($show)
    {
        // admin bar invalida AMP. Melhor sempre esconder no front do story.
        if (is_singular(self::POST_TYPE)) return false;
        return $show;
    }

    /**
     * Faz:
     * - "Adicionar novo" do WP -> Builder
     * - "Editar" (post.php) do WP -> Builder
     */
    public static function admin_redirects(): void
    {
        if (!is_admin()) return;

        $pt = self::POST_TYPE;

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $post_type = isset($_GET['post_type'])  ? sanitize_key(wp_unslash($_GET['post_type']))  : '';

        $request_uri = isset($_SERVER['REQUEST_URI'])
            ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']))
            : '';

        if ($post_type === $pt && strpos($request_uri, 'post-new.php') !== false) {
            $url = admin_url('admin.php?page=alpha-suite-ws-generator');
            wp_safe_redirect($url);
            exit;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['post'], $_GET['action']) && sanitize_key(wp_unslash($_GET['action'])) === 'edit') {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $post_id = isset($_GET['post']) ? absint(wp_unslash($_GET['post'])) : 0;
            if ($post_id) {
                $post = get_post($post_id);
                if ($post && $post->post_type === $pt) {
                    $url = admin_url('admin.php?page=alpha-suite-ws-generator&story_id=' . $post_id);
                    wp_safe_redirect($url);
                    exit;
                }
            }
        }
    }

    public static function render(): void
    {
        $opt = AlphaSuite_Settings::get();
        $chk = AlphaSuite_License::check('alpha_stories');

        if (!$chk['ok']) {
            $url = admin_url('admin.php?page=alpha-suite-license');
            echo '<div class="notice notice-error is-dismissible"><p>'
                . esc_html__('Módulo não ativado.', 'alpha-suite')
                . ' <a href="' . esc_url($url) . '">'
                . esc_html__('Clique aqui para ativar', 'alpha-suite')
                . '</a></p></div>';
        }

        // ---------------------------
        // story_id (normal + fallback)
        // ---------------------------
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $story_id = isset($_GET['story_id']) ? absint(wp_unslash($_GET['story_id'])) : 0;

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (! $story_id && ! empty($_GET['page']) && is_string($_GET['page'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
            if (strpos($page, 'story_id=') !== false) {
                parse_str(
                    wp_parse_url(
                        'http://x/?' . ltrim(strstr($page, '?') ?: '', '?'),
                        PHP_URL_QUERY
                    ),
                    $tmp
                );
                $story_id = absint($tmp['story_id'] ?? 0);
            }
        }

        // ---------------------------
        // Dados do story (header)
        // ---------------------------
        $story_title  = '';
        $story_status = '';
        $story = null;

        if ($story_id > 0) {
            $story = get_post($story_id);
            if ($story) {
                $story_status = (string) $story->post_status;

                // Título central: meta_title (do story) ou post_title
                $meta_title_h = (string) get_post_meta($story_id, AlphaSuite_REST_Ws_Generator::META_TITLE, true);
                $story_title  = trim($meta_title_h) !== '' ? $meta_title_h : (get_the_title($story_id) ?: '');
            }
        }

        // badge status
        $status_label = '';
        $status_class = '';
        if ($story_id > 0) {
            if ($story_status === 'publish') {
                $status_label = esc_html__('Publicado', 'alpha-suite');
                $status_class = 'is-publish';
            } elseif ($story_status === 'future') {
                $status_label = esc_html__('Agendado', 'alpha-suite');
                $status_class = 'is-future';
            } elseif ($story_status === 'trash') {
                $status_label = esc_html__('Excluido', 'alpha-suite');
                $status_class = 'is-trash';
            } else {
                $status_label = esc_html__('Rascunho', 'alpha-suite');
                $status_class = 'is-draft';
            }
        }

        // ---------------------------
        // Defaults / Fallbacks (modal publish)
        // ---------------------------
        $default_logo_id = (int) ($opt['stories']['publisher_logo_id'] ?? 0);

        $modal_meta_title = '';
        $modal_slug       = '';
        $modal_meta_desc  = '';
        $modal_accent     = '#3b82f6';
        $modal_textc      = '#ffffff';
        $modal_locale     = 'pt_BR';

        $logo_id_meta   = 0;
        $poster_id_meta = 0;

        $effective_logo_id = 0;
        $effective_poster_id = 0;

        // se edição, puxa do story
        if ($story_id > 0 && $story) {
            $modal_meta_title = (string) get_post_meta($story_id, AlphaSuite_REST_Ws_Generator::META_TITLE, true);
            $modal_meta_desc  = (string) get_post_meta($story_id, AlphaSuite_REST_Ws_Generator::META_DESC, true);
            $modal_slug  = (string) get_post_meta($story_id, AlphaSuite_REST_Ws_Generator::META_SLUG, true);

            $modal_accent = (string) get_post_meta($story_id, AlphaSuite_REST_Ws_Generator::META_ACCENT, true) ?: $modal_accent;
            $modal_textc  = (string) get_post_meta($story_id, AlphaSuite_REST_Ws_Generator::META_TEXT_COLOR, true) ?: $modal_textc;

            // locale salvo (se existir)
            $loc = (string) get_post_meta($story_id, AlphaSuite_REST_Ws_Generator::META_LOCALE, true);
            if ($loc !== '') $modal_locale = $loc;

            $logo_id_meta   = (int) get_post_meta($story_id, AlphaSuite_REST_Ws_Generator::META_LOGO_ID, true);
            $poster_id_meta = (int) get_post_meta($story_id, AlphaSuite_REST_Ws_Generator::META_POSTER_ID, true);
        }

        // logo efetiva = meta do story OU default settings
        $effective_logo_id = $logo_id_meta ?: $default_logo_id;

        // poster efetivo = meta do story OU thumbnail do post fonte
        $effective_poster_id = $poster_id_meta;
        if ($effective_poster_id <= 0 && $story_id > 0) {
            $source_post = (int) get_post_meta($story_id, AlphaSuite_REST_Ws_Generator::META_SOURCE, true);
            if ($source_post > 0) {
                $thumb_id = (int) get_post_thumbnail_id($source_post);
                if ($thumb_id > 0) $effective_poster_id = $thumb_id;
            }
            $urlSource = site_url() . "/?p=" . $source_post;
        }

        $logo_url   = $effective_logo_id ? (wp_get_attachment_image_url($effective_logo_id, 'full') ?: '') : '';
        $poster_url = $effective_poster_id ? (wp_get_attachment_image_url($effective_poster_id, 'full') ?: '') : '';

        $publish_at_val = '';

        if ($story_id > 0 && $story && $story->post_status === 'future') {
            // post_date já é local time do WP
            $dt = mysql2date('Y-m-d\TH:i', $story->post_date, false);
            $publish_at_val = $dt ?: '';
        }

        $back = admin_url('admin.php?page=alpha-suite-ws-generator');

        $trashUrl = '';
        if ($story_id > 0) {
            // ação correta: trash
            $trashUrl = admin_url('post.php?post=' . (int)$story_id . '&action=trash');
            // nonce correto para trash:
            $trashUrl = wp_nonce_url($trashUrl, 'trash-post_' . (int)$story_id);
            // redirect final SEM id
            $trashUrl = add_query_arg('redirect_to', rawurlencode($back), $trashUrl);
        }
        $st     = $opt['stories'] ?? [];
        $family = trim($st['default_font'] ?? '');

        if ($family === '') {
            $family = 'inherit';
        } else {
            // sanitiza
            $family = sanitize_text_field($family);

            // adiciona aspas se tiver espaço
            if (strpos($family, ' ') !== false) {
                $family = '"' . $family . '"';
            }
        }
?>
        <style>
            .pga-ws-frame-content h2,
            .pga-ws-frame-content p,
            .pga-ws-frame-content a {
                font-family: <?php echo esc_attr($family); ?>, sans-serif;
            }
        </style>
        <div class="pga-wrap pga-ws" data-story-id="<?php echo (int) $story_id; ?>">
            <div class="wrap pga-layout">
                <div class="pga-header-fixed">
                    <div class="pga-header-col pga-a-center">
                        <div>
                            <h1><?php esc_html_e('Gerador — WS Generator', 'alpha-suite'); ?></h1>
                            <p class="pga-descricao"><?php esc_html_e('Criação de Web Stories com IA', 'alpha-suite'); ?></p>
                        </div>
                    </div>

                    <?php
                    echo $story_id ? '
                    <div class="pga-header-col pga-a-center pga-header-center">
                        <div class="pga-story-center">
                            <span id="pga_story_title_header" class="pga-post-title">' . esc_html($story_title ?: __('Story sem título', 'alpha-suite')) . '</span>
                        </div>
                    </div>' : '';
                    ?>

                    <div class="pga-header-col pga-a-center">
                        <?php echo $story_id ? '
                        <div class="pga-header-col pga-a-center pga-header-center">
                            <div class="pga-story-center">
                                <span id="pga_status_badge"
                                    class="pga-ws-status ' . esc_attr($status_class) . '"
                                    data-status="' . esc_attr($story_status) . '">'
                            . esc_html($status_label) .
                            '</span>
                            </div>
                        </div>
                        <a class="pga_save_box"
                        href="' . esc_url(site_url('/' . self::get_base_slug() . '/' . $modal_slug)) . '"
                        target="_blank"
                        rel="noopener">
                        <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px">
                        <path d="m256-240-56-56 384-384H240v-80h480v480h-80v-344L256-240Z"/></svg>
                        </a>
                        <button
                            title="' . esc_attr('Configurações do Story', 'alpha-suite') . '"
                            type="button"
                            class="pga_save_box"
                            onclick="openPublishModal()">
                            <svg xmlns="http://www.w3.org/2000/svg" height="24" viewBox="0 -960 960 960" width="24">
                                <path d="m370-80-16-128q-13-5-24.5-12T307-235l-119 50L78-375l103-78q-1-7-1-13.5v-27q0-6.5 1-13.5L78-585l110-190 119 50q11-8 23-15t24-12l16-128h220l16 128q13 5 24.5 12t22.5 15l119-50 110 190-103 78q1 7 1 13.5v27q0 6.5-2 13.5l103 78-110 190-118-50q-11 8-23 15t-24 12L590-80H370Zm70-80h79l14-106q31-8 57.5-23.5T639-327l99 41 39-68-86-65q5-14 7-29.5t2-31.5q0-16-2-31.5t-7-29.5l86-65-39-68-99 42q-22-23-48.5-38.5T533-694l-13-106h-79l-14 106q-31 8-57.5 23.5T321-633l-99-41-39 68 86 64q-5 15-7 30t-2 32q0 16 2 31t7 30l-86 65 39 68 99-42q22 23 48.5 38.5T427-266l13 106Zm42-180q58 0 99-41t41-99q0-58-41-99t-99-41q-59 0-99.5 41T342-480q0 58 40.5 99t99.5 41Zm-2-140Z" />
                            </svg>
                        </button>
                        ' : ''; ?>
                        <button
                            title="<?php esc_attr('Salvar Story', 'alpha-suite'); ?>"
                            type="button"
                            class="pga_save_box"
                            onclick="saveStory('save', true)">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-save h-4 w-4 mr-2">
                                <path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"></path>
                                <path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"></path>
                                <path d="M7 3v4a1 1 0 0 0 1 1h7"></path>
                            </svg>
                        </button>
                        <?php
                        $label_text = esc_html__('Publicar', 'alpha-suite');

                        $label_html = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-sparkles h-4 w-4 mr-2"><path d="M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .963 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.581a.5.5 0 0 1 0 .964L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z"></path><path d="M20 3v4"></path><path d="M22 5h-4"></path><path d="M4 17v2"></path><path d="M5 18H3"></path></svg> '
                            . esc_html__('Gerar Story', 'alpha-suite');

                        $label = $story_id ? $label_text : $label_html;

                        $onclick = $story_id
                            ? "saveStory('publish')"
                            : 'openPublishModal()';

                        if (! empty($chk['ok'])) {
                            echo '<button type="button" id="pga_plan" onclick="' . esc_attr($onclick) . '">'
                                . wp_kses_post($label) .
                                '</button>';
                        } else {
                            echo '<button type="button" id="pga_plan" disabled>'
                                . wp_kses_post($label) .
                                '</button>';
                        }
                        ?>

                    </div>



                </div>

                <!-- Área de Trabalho (Canvas) -->
                <main class="pga-main">
                    <div id="pga_tabs" class="pga-ws-theme-tabs">
                        <label class="pga-ws-style">
                            <input type="radio" name="pga_ws_theme" value="theme-normal" checked>
                            <span><?php esc_html_e('Normal', 'alpha-suite'); ?></span>
                        </label>
                        <label class="pga-ws-style">
                            <input type="radio" name="pga_ws_theme" value="theme-news">
                            <span><?php esc_html_e('Newsroom', 'alpha-suite'); ?></span>
                        </label>
                        <label class="pga-ws-style">
                            <input type="radio" name="pga_ws_theme" value="theme-dark">
                            <span><?php esc_html_e('Dark Neon', 'alpha-suite'); ?></span>
                        </label>
                        <label class="pga-ws-style">
                            <input type="radio" name="pga_ws_theme" value="theme-soft">
                            <span><?php esc_html_e('Soft Clean', 'alpha-suite'); ?></span>
                        </label>
                        <label class="pga-ws-style">
                            <input type="radio" name="pga_ws_theme" value="theme-pop">
                            <span><?php esc_html_e('Pop Bold', 'alpha-suite'); ?></span>
                        </label>
                    </div>

                    <div id="frames-container">
                        <div class="pga-skeleton">
                            <div class="sk-big"></div>
                            <div class="sk-row"></div>
                            <div class="sk-row"></div>
                            <div class="sk-row" style="width:70%"></div>
                        </div>
                    </div>
                </main>
            </div>

            <!-- Modal Único (WS Generator) -->

            <div id="pga_modal" class="pga-modal" aria-hidden="true">
                <div class="pga-modal-backdrop" data-close="1"></div>

                <div class="pga-modal-card" role="dialog" aria-modal="true" aria-labelledby="pga_modal_title">
                    <div class="pga-modal-head">
                        <div>
                            <h3 id="pga_modal_title" class="pga-modal-title">Modal</h3>
                        </div>
                        <button type="button" class="pga-modal-x" data-close="1">✕</button>
                    </div>
                    <div class="pga-modal-body">

                        <section class="pga-modal-panel" data-mode="publish" hidden>
                            <div class="pga-modal__body">
                                <?php if ($story_id) { ?>
                                    <div class="pga-field">
                                        <label><?php esc_html_e('Título', 'alpha-suite'); ?></label>
                                        <input id="pga_ws_meta_title" type="text" class="pga-input"
                                            value="<?php echo esc_attr($modal_meta_title); ?>"
                                            placeholder="<?php esc_attr_e('Ex: Guia Rápido de...', 'alpha-suite'); ?>">
                                    </div>

                                    <div class="pga-field">
                                        <label><?php esc_html_e('Meta descrição', 'alpha-suite'); ?></label>
                                        <textarea id="pga_ws_meta_desc" class="pga-textarea" rows="2"
                                            placeholder="<?php esc_attr_e('Breve descrição para o Google...', 'alpha-suite'); ?>">
                                        <?php
                                        echo esc_textarea($modal_meta_desc);
                                        ?></textarea>
                                    </div>

                                    <div class="pga-field">
                                        <label for="pga_story_slug">Slug</label>
                                        <input id="pga_story_slug" type="text" class="pga-input"
                                            value="<?php echo esc_attr($modal_slug); ?>"
                                            placeholder="<?php esc_attr('Ex.: post-exemplo'); ?>">
                                    </div>
                                    <div class="pga-row">
                                        <div class="pga-field">
                                            <label class="pga-label">Status</label>
                                            <div class="pga-status-line">
                                                <select id="pga_story_status" class="pga-input">
                                                    <option value="draft" <?php selected($story_status, 'draft'); ?>>Rascunho</option>
                                                    <option value="future" <?php selected($story_status, 'future'); ?>>Agendado</option>
                                                    <option value="publish" <?php selected($story_status, 'publish'); ?>>Publicado</option>
                                                </select>


                                                <span id="pga_story_status_badge" class="pga-ws-status is-draft">Rascunho</span>
                                            </div>
                                        </div>

                                        <div class="pga-field" id="pga_story_future_row" style="display:none;">
                                            <label class="pga-label">Agendamento</label>
                                            <input
                                                id="pga_story_publish_at"
                                                type="datetime-local"
                                                class="pga-input"
                                                value="<?php echo esc_attr($publish_at_val); ?>" />
                                        </div>
                                    </div>
                                <?php } ?>
                                <div class="pga-field">
                                    <label><?php esc_html_e('Categoria', 'alpha-suite'); ?></label>

                                    <?php
                                    $cats = get_terms([
                                        'taxonomy'   => 'category',
                                        'hide_empty' => false,
                                    ]);

                                    // categorias já selecionadas (se existir)
                                    $selected_cats = [];
                                    if ($story_id) {
                                        $selected_cats = wp_get_post_terms($story_id, 'category', ['fields' => 'ids']);
                                        if (!is_array($selected_cats)) $selected_cats = [];
                                    }
                                    ?>

                                    <select id="pga_ws_categories"
                                        data-placeholder="<?php echo esc_attr__('Selecione categorias...', 'alpha-suite'); ?>">
                                        <?php if (!empty($cats) && !is_wp_error($cats)) : ?>
                                            <?php foreach ($cats as $c) : ?>
                                                <option value="<?php echo (int)$c->term_id; ?>" <?php selected(in_array((int)$c->term_id, $selected_cats, true)); ?>>
                                                    <?php echo esc_html($c->name); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <!-- Logo (WP Media) -->
                                <div class="pga-row">
                                    <div class="pga-field">
                                        <label><?php esc_html_e('Logo do Publisher', 'alpha-suite'); ?></label>

                                        <input type="hidden" id="pga_ws_logo_id" value="<?php echo (int) $effective_logo_id; ?>">
                                        <div class="pga-media">
                                            <img id="pga_ws_logo_preview" class="pga-media__preview"
                                                src="<?php echo esc_url($logo_url); ?>" alt=""
                                                style="<?php echo $logo_url ? '' : 'display:none;'; ?>">
                                            <div class="pga-media__btns">
                                                <button type="button" class="button" id="pga_ws_pick_logo"><?php esc_html_e('Selecionar', 'alpha-suite'); ?></button>
                                                <button type="button" class="button" id="pga_ws_clear_logo"><?php esc_html_e('Remover', 'alpha-suite'); ?></button>
                                            </div>
                                        </div>

                                        <small class="pga-help"><?php esc_html_e('Usada como publisher logo no Web Story.', 'alpha-suite'); ?></small>
                                    </div>

                                    <?php if ($story_id) { ?>
                                        <!-- Poster (opcional) -->
                                        <div class="pga-field">
                                            <label><?php esc_html_e('Thumbnail', 'alpha-suite'); ?></label>

                                            <input type="hidden" id="pga_ws_poster_id" value="<?php echo (int) $effective_poster_id; ?>">
                                            <div class="pga-media">
                                                <img id="pga_ws_poster_preview" class="pga-media__preview"
                                                    src="<?php echo esc_url($poster_url); ?>" alt=""
                                                    style="<?php echo $poster_url ? '' : 'display:none;'; ?>">
                                                <div class="pga-media__btns">
                                                    <button type="button" class="button" id="pga_ws_pick_poster"><?php esc_html_e('Selecionar', 'alpha-suite'); ?></button>
                                                    <button type="button" class="button" id="pga_ws_clear_poster"><?php esc_html_e('Remover', 'alpha-suite'); ?></button>
                                                </div>
                                            </div>
                                            <small class="pga-help"><?php esc_html_e('Recomendado o formato 4:5 na vertical.', 'alpha-suite'); ?></small>
                                        </div>
                                    <?php } ?>
                                </div>

                                <!-- Cores + Idioma -->
                                <div class="pga-row">
                                    <div class="pga-field" <?php echo (!$story_id) ? "style=\"max-width: 180px;\"" : "" ?>>
                                        <label for="pga_ws_accent_color"><?php esc_html_e('Cor de destaque', 'alpha-suite'); ?></label>
                                        <input id="pga_ws_accent_color" type="color" class="pga-color" value="<?php echo esc_attr($modal_accent); ?>">
                                    </div>
                                </div>
                                <?php if (!$story_id) { ?>
                                    <div class="pga-global-wrap" style="display:flex;align-items:center;gap:10px">
                                        <label class="pga-switch">
                                            <input type="checkbox" id="pga_ws_generate_images" checked="true">
                                            <span class="pga-switch-ui" aria-hidden="true"></span>
                                            <span class="pga-switch-label">Gerar imagem para cada slide</span>
                                        </label>
                                    </div>
                                    <div class="pga-field">
                                        <label><?php esc_html_e('Idioma', 'alpha-suite'); ?></label>
                                        <select id="pga_ws_locale" class="pga-select">
                                            <option value="pt_BR" <?php selected($modal_locale, 'pt_BR'); ?>>Português (Brasil)</option>
                                            <option value="pt_PT" <?php selected($modal_locale, 'pt_PT'); ?>>Português (Portugal)</option>

                                            <option value="en_US" <?php selected($modal_locale, 'en_US'); ?>>English (United States)</option>
                                            <option value="en_GB" <?php selected($modal_locale, 'en_GB'); ?>>English (United Kingdom)</option>

                                            <option value="es_ES" <?php selected($modal_locale, 'es_ES'); ?>>Español (España)</option>
                                            <option value="es_MX" <?php selected($modal_locale, 'es_MX'); ?>>Español (México)</option>

                                            <option value="fr_FR" <?php selected($modal_locale, 'fr_FR'); ?>>Français (France)</option>
                                            <option value="de_DE" <?php selected($modal_locale, 'de_DE'); ?>>Deutsch (Deutschland)</option>

                                            <option value="it_IT" <?php selected($modal_locale, 'it_IT'); ?>>Italiano</option>
                                            <option value="nl_NL" <?php selected($modal_locale, 'nl_NL'); ?>>Nederlands</option>

                                            <option value="ja_JP" <?php selected($modal_locale, 'ja_JP'); ?>>日本語</option>
                                            <option value="ko_KR" <?php selected($modal_locale, 'ko_KR'); ?>>한국어</option>

                                            <option value="zh_CN" <?php selected($modal_locale, 'zh_CN'); ?>>中文 (简体)</option>
                                            <option value="zh_TW" <?php selected($modal_locale, 'zh_TW'); ?>>中文 (繁體)</option>
                                        </select>
                                    </div>

                                    <div class="pga-field">
                                        <label><?php esc_html_e('Início das Postagens', 'alpha-suite'); ?></label>
                                        <input type="datetime-local" id="start-date" class="pga-input">
                                    </div>
                                    <hr class="pga-hr">

                                    <!-- Unit -->
                                    <div id="unit-selection" class="pga-field hidden-tab">
                                        <label><?php esc_html_e('Post (unitário)', 'alpha-suite'); ?></label>

                                        <?php
                                        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                                        $source_id = isset($_GET['source']) ? absint(wp_unslash($_GET['source'])) : 0;

                                        $orion_posts = get_posts([
                                            'post_type'      => ['post', 'posts_orion'],
                                            'post_status'    => ['publish', 'pending', 'future'],
                                            'numberposts'    => 100,
                                            'orderby'        => 'date',
                                            'order'          => 'DESC',
                                        ]);
                                        ?>

                                        <select id="pga_ws_post_unit" class="pga-select">
                                            <option value="0"><?php esc_html_e('Selecione um post', 'alpha-suite'); ?></option>

                                            <?php if (!empty($orion_posts)) : ?>
                                                <?php foreach ($orion_posts as $p) : ?>
                                                    <option value="<?php echo esc_attr($p->ID); ?>" <?php selected($source_id, (int)$p->ID); ?>>
                                                        <?php echo esc_html(get_the_title($p)); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            <?php else : ?>
                                                <option value="0" disabled><?php esc_html_e('Nenhum post Órion publicado ainda.', 'alpha-suite'); ?></option>
                                            <?php endif; ?>
                                        </select>

                                    </div>

                                    <!-- Multi -->
                                    <div id="multi-selection">
                                        <div class="pga-field">
                                            <label><?php esc_html_e('Selecionamento de posts', 'alpha-suite'); ?></label>

                                            <?php
                                            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                                            $source_id = isset($_GET['source']) ? absint(wp_unslash($_GET['source'])) : 0;


                                            $orion_posts = get_posts([
                                                'post_type'      => ['post', 'posts_orion'],
                                                'post_status'    => ['publish', 'pending', 'future'],
                                                'numberposts'    => 100,
                                                'orderby'        => 'date',
                                                'order'          => 'DESC',
                                            ]);
                                            ?>

                                            <select id="pga_ws_posts_multi"
                                                class="pga-select pga-ws-select2"
                                                multiple="multiple"
                                                data-placeholder="<?php echo esc_attr__('Buscar postagens...', 'alpha-suite'); ?>"
                                                style="width:100%;">
                                                <?php if (!empty($orion_posts)) : ?>
                                                    <?php foreach ($orion_posts as $p) : ?>
                                                        <option value="<?php echo esc_attr($p->ID); ?>" <?php selected($source_id, (int)$p->ID); ?>>
                                                            <?php echo esc_html(get_the_title($p)); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </select>
                                        </div>
                                    </div>
                                <?php } ?>
                                <?php
                                if (isset($source_post)) {
                                    echo '<b>' . esc_html__('Referência', 'alpha-suite') . '</b>'
                                        . '<a href="' . esc_url($urlSource) . '" target="_blank" rel="noopener noreferrer">'
                                        . esc_html($source_post)
                                        . '</a>';
                                }
                                ?>
                            </div>

                            <div class="pga-modal__foot pga-generator-footer">
                                <?php if ($story_id) { ?>
                                    <button type="button" class="pga_clear_box" onclick="deleteStoryRedirect('<?php echo esc_url($trashUrl); ?>')">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trash2 h-4 w-4 mr-2">
                                            <path d="M3 6h18"></path>
                                            <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                                            <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                                            <line x1="10" x2="10" y1="11" y2="17"></line>
                                            <line x1="14" x2="14" y1="11" y2="17"></line>
                                        </svg>
                                        Excluir </button>
                                <?php } ?>

                                <button type="button" class="pga-btn pga-btn--ghost" data-close="1">
                                    <?php esc_html_e('Cancelar', 'alpha-suite'); ?>
                                </button>

                                <?php if ($story_id) { ?>
                                    <button type="button"
                                        class="pga-btn pga-btn--primary"
                                        id="pga_plan"
                                        onclick="saveStory('save')">
                                        <?php esc_html_e('Salvar', 'alpha-suite'); ?>
                                    </button>
                                <?php } else { ?>
                                    <button type="button"
                                        class="pga-btn pga-btn--primary"
                                        id="pga_plan"
                                        onclick="startGeneration()">
                                        <?php esc_html_e('Gerar', 'alpha-suite'); ?>
                                    </button>
                                <?php } ?>

                            </div>
                        </section>

                        <section class="pga-modal-panel" data-mode="story" hidden>
                            <div class="pga-modal-body">

                                <!-- Status -->
                                <div class="pga-row">
                                    <div class="pga-field">
                                        <label class="pga-label">Status</label>
                                        <div class="pga-status-line">
                                            <select id="pga_story_status" class="pga-input">
                                                <option value="draft">Rascunho</option>
                                                <option value="future">Agendado</option>
                                                <option value="publish">Publicado</option>
                                            </select>

                                            <span id="pga_story_status_badge" class="pga-ws-status is-draft">Rascunho</span>
                                        </div>
                                    </div>
                                </div>
                                <!-- Título / Meta -->
                                <div class="pga-row">
                                    <div class="pga-col">
                                        <label class="pga-label">Título</label>
                                        <input id="pga_story_title" type="text" class="pga-input" placeholder="Ex: Guia rápido de..." />
                                    </div>
                                </div>

                                <div class="pga-row">
                                    <div class="pga-col">
                                        <label class="pga-label">Meta descrição</label>
                                        <textarea id="pga_story_desc" class="pga-input pga-textarea" rows="3" placeholder="Digite aqui..."></textarea>
                                        <div class="pga-counter">
                                            <span id="pga_desc_count">0</span>/160
                                        </div>
                                    </div>
                                </div>

                                <!-- Logo / Poster -->
                                <div class="pga-row pga-row">
                                    <div class="pga-col">
                                        <label class="pga-label">Logo do Publisher</label>
                                        <input type="hidden" id="pga_story_logo_id" value="0" />
                                        <div class="pga-media-line">
                                            <img id="pga_story_logo_preview" class="pga-thumb" src="" alt="" style="display:none;" />
                                            <div class="pga-media-actions">
                                                <button type="button" class="button" id="pga_pick_logo">Selecionar</button>
                                                <button type="button" class="button" id="pga_clear_logo">Remover</button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="pga-col">
                                        <label class="pga-label">Poster/Capa</label>
                                        <input type="hidden" id="pga_story_poster_id" value="0" />
                                        <div class="pga-media-line">
                                            <img id="pga_story_poster_preview" class="pga-thumb" src="" alt="" style="display:none;" />
                                            <div class="pga-media-actions">
                                                <button type="button" class="button" id="pga_pick_poster">Selecionar</button>
                                                <button type="button" class="button" id="pga_clear_poster">Remover</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Cores -->
                                <div class="pga-row pga-row">
                                    <div class="pga-col">
                                        <label class="pga-label">Cor de destaque</label>
                                        <input id="pga_story_accent" type="color" class="pga-color" value="#3b82f6" />
                                    </div>
                                    <div class="pga-col">
                                        <label class="pga-label">Cor do texto</label>
                                        <input id="pga_story_text_color" type="color" class="pga-color" value="#ffffff" />
                                    </div>
                                </div>
                                <label class="pga-ws-cta-check" style="display:flex;gap:10px;align-items:center;margin-top:10px">
                                    <input id="pga_story_generate_images" type="checkbox" checked>
                                    <span>Gerar imagem para cada slide</span>
                                </label>

                                <div class="pga-field">
                                    <label><?php esc_html_e('Idioma', 'alpha-suite'); ?></label>
                                    <select id="pga_story_locale" class="pga-select">
                                        <option value="pt_BR">Português (Brasil)</option>
                                        <option value="en_US">English (US)</option>
                                        <option value="es_ES">Español (ES)</option>
                                    </select>
                                </div>

                                <!-- Referências (somente leitura) -->
                                <div class="pga-row">
                                    <div class="pga-col">
                                        <label class="pga-label">Referência</label>
                                        <div class="pga-ref">
                                            <div><strong>story_id:</strong> <span id="pga_ref_story_id">0</span></div>
                                            <div><strong>source_post:</strong> <span id="pga_ref_source_id">0</span></div>
                                        </div>
                                        <small class="pga-help">Usado para regerar e manter vínculo com o artigo.</small>
                                    </div>
                                </div>

                            </div>
                            <div class="pga-modal-foot">
                                <button type="button" class="button" data-close="1">Cancelar</button>
                                <button type="button" class="button button-primary" id="pga_story_save">Salvar</button>
                            </div>
                        </section>

                        <section class="pga-modal-panel" data-mode="slide" hidden>
                            <div class="pga-modal__body">
                                <div class="pga-field">
                                    <label>Título</label>
                                    <input id="pga_slide_heading" type="text" class="pga-input" autocomplete="off">
                                </div>

                                <div class="pga-field">
                                    <label>Descrição</label>
                                    <textarea id="pga_slide_body" class="pga-textarea" rows="3"></textarea>
                                </div>

                                <div class="pga-row">
                                    <div class="pga-field">
                                        <label>Texto CTA</label>
                                        <input id="pga_slide_cta_text" type="text" class="pga-input">
                                    </div>
                                    <div class="pga-field">
                                        <label>Link CTA</label>
                                        <input id="pga_slide_cta_url" type="url" class="pga-input" placeholder="https://...">
                                    </div>
                                </div>
                                <div class="pga-field">
                                    <label>Imagem do slide</label>

                                    <input type="hidden" id="pga_slide_image_id" value="0">
                                    <div class="pga-media">
                                        <img id="pga_slide_image_preview" class="pga-media__preview" src="" alt="" style="display:none;">
                                        <div class="pga-media__btns">
                                            <button type="button" class="button" id="pga_slide_pick_image">Selecionar</button>
                                            <button type="button" class="button" id="pga_slide_clear_image">Remover</button>
                                        </div>
                                    </div>
                                    <div id="pga_slide_notice" class="notice notice-warning pga-ws-notice" style="display:none;">
                                        <p id="pga_slide_notice_text" style="margin:0;"></p>
                                    </div>
                                </div>

                            </div>
                            <div class="pga-modal__foot">
                                <button type="button" class="pga-btn pga-btn--ghost" data-close="1">Cancelar</button>
                                <button type="button" class="pga-btn pga-btn--primary" onclick="pgaSaveSlideModal()">Salvar</button>
                            </div>
                        </section>

                        <section class="pga-modal-panel" data-mode="image" hidden>
                            <div class="pga-modal-body">
                                <div class="pga-field">
                                    <label class="pga-label" for="pga_img_brief">Brief (opcional)</label>
                                    <textarea id="pga_img_brief" class="pga-input pga-textarea" rows="4"
                                        placeholder="Ex: foto realista, luz natural, estilo cinematográfico, sem texto..."></textarea>
                                </div>
                                <div class="pga-help">Se vazio, o backend usa apenas o prompt padrão.</div>
                            </div>
                            <div class="pga-modal-foot">
                                <button type="button" class="pga-btn pga-btn--ghost" data-close="1">Cancelar</button>
                                <button type="button" class="pga-btn pga-btn--primary" id="pga_img_generate_btn">Gerar</button>
                            </div>
                        </section>

                    </div>
                </div>
            </div>
        </div>
<?php

    }
}
