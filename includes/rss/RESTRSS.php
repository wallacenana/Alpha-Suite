<?php

if (!defined('ABSPATH')) exit;

class AlphaSuite_RESTRSS
{
    public static function register_routes()
    {
        $base = 'pga/v1';

        register_rest_route($base, '/rss/get', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'get_rss'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        register_rest_route($base, '/rss/languages', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'get_languages'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            }
        ]);

        register_rest_route($base, '/rss/excerpt', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'rest_generate_excerpt'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            }
        ]);

        register_rest_route($base, '/generators/runtime', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'get_generators_runtime'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            }
        ]);

        register_rest_route($base, '/rss/translations', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'rest_create_translations'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            }
        ]);

        register_rest_route($base, '/rss/faq', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'rest_generate_faq'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        register_rest_route($base, '/generators/save', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'pga_rest_save_generators'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        register_rest_route($base, '/rss/selftest', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'selftest'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            }
        ]);

        register_rest_route($base, '/rss/start', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'start'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            }
        ]);

        register_rest_route($base, '/rss/title', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'rest_generate_title'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            }
        ]);

        register_rest_route($base, '/rss/meta', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'rest_generate_meta'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            }
        ]);

        register_rest_route($base, '/rss/slug', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'rest_generate_slug'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            }
        ]);

        register_rest_route($base, '/rss/outline', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'rest_generate_outline'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            }
        ]);

        register_rest_route($base, '/rss/section', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'rest_generate_section'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            }
        ]);

        register_rest_route($base, '/rss/finalize', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'rest_finalize'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            }
        ]);

        register_rest_route($base, '/rss/extract-image', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'rest_extract_image'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            }
        ]);
    }

    public static function get_generators_runtime(WP_REST_Request $req)
    {
        global $wpdb;

        $generator_id = intval($req->get_param('generator_id'));

        if (!$generator_id) {
            return rest_ensure_response(['ok' => false]);
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT next_run, last_run, last_status 
             FROM {$wpdb->prefix}pga_generator_runtime 
             WHERE generator_id = %d",
                $generator_id
            )
        );

        return rest_ensure_response([
            'ok' => true,
            'data' => $row
        ]);
    }

    public static function rest_create_translations(WP_REST_Request $req)
    {
        $postId = intval($req->get_param('post_id'));

        if (!$postId || !get_post($postId)) {
            return new WP_Error('invalid_post', 'Post inválido', ['status' => 400]);
        }

        $multilang_enabled = (bool) get_post_meta($postId, '_pga_multilang_enabled', true);
        $languages = (array) get_post_meta($postId, '_pga_languages', true);

        if (!$multilang_enabled || empty($languages)) {
            return rest_ensure_response([
                'ok' => false,
                'message' => 'Multilíngue não habilitado.'
            ]);
        }

        self::create_translations($postId, [
            'multilang_enabled' => true,
            'languages' => $languages
        ]);

        return rest_ensure_response([
            'ok' => true
        ]);
    }

    public static function get_languages()
    {
        if (!function_exists('PLL')) {
            return rest_ensure_response([
                'ok' => false,
                'error' => 'Polylang não instalado'
            ]);
        }

        $languages = PLL()->model->get_languages_list();

        $data = [];

        foreach ($languages as $lang) {

            $data[] = [
                'slug'   => $lang->slug,   // en
                'locale' => $lang->locale, // en_US
                'name'   => $lang->name    // English
            ];
        }

        return rest_ensure_response([
            'ok' => true,
            'languages' => $data
        ]);
    }

    private static function get_post_language($postId)
    {
        if (function_exists('pll_get_post_language')) {
            return pll_get_post_language($postId);
        }

        return null;
    }

    private static function set_post_language($postId, $lang)
    {
        if (function_exists('pll_set_post_language')) {
            pll_set_post_language($postId, $lang);
        }
    }

    private static function create_translations($originalPostId, $config)
    {
        if (
            !function_exists('pll_set_post_language') ||
            !function_exists('pll_get_post_language') ||
            !function_exists('pll_save_post_translations')
        ) {
            return;
        }

        $languages = (array)($config['languages'] ?? []);
        if (empty($languages)) {
            return;
        }

        $originalPost = get_post($originalPostId);
        if (!$originalPost) {
            return;
        }

        $originalLang = self::get_post_language($originalPostId);

        if (!$originalLang) {
            $originalLang = $languages[0];
            self::set_post_language($originalPostId, $originalLang);
        }

        $translations = [
            $originalLang => $originalPostId
        ];

        foreach ($languages as $lang) {

            if ($lang === $originalLang) {
                continue;
            }

            // 🔹 Traduz título e conteúdo
            $translatedTitle   = AlphaSuite_AI::translate($originalPost->post_title, $lang);
            $translatedContent = AlphaSuite_AI::translate($originalPost->post_content, $lang);

            if (is_wp_error($translatedTitle) || is_wp_error($translatedContent)) {
                continue;
            }

            // 🔹 Traduz slug
            $translatedSlugRaw = AlphaSuite_AI::translate($originalPost->post_name, $lang);
            $translatedSlug = is_wp_error($translatedSlugRaw)
                ? sanitize_title($translatedTitle)
                : sanitize_title($translatedSlugRaw);

            // 🔹 Traduz meta do seu sistema
            $metaDescription = get_post_meta($originalPostId, '_pga_meta_description', true);
            $translatedMeta  = '';

            if ($metaDescription) {

                $metaRaw = AlphaSuite_AI::translate($metaDescription, $lang);

                if (is_wp_error($metaRaw)) {
                    continue;
                }

                if (is_array($metaRaw)) {
                    $metaRaw = $metaRaw['content'] ?? $metaRaw['text'] ?? '';
                }

                if (is_string($metaRaw) && $metaRaw !== '') {
                    $translatedMeta = trim($metaRaw);
                }
            }

            // 🔹 Cria post traduzido
            $newPostId = wp_insert_post([
                'post_type'    => $originalPost->post_type,
                'post_status'  => 'publish',
                'post_title'   => $translatedTitle,
                'post_content' => $translatedContent,
                'post_author'  => $originalPost->post_author,
                'post_name'    => $translatedSlug,
            ]);

            if (!$newPostId || is_wp_error($newPostId)) {
                continue;
            }

            // 🔥 Define idioma do post primeiro
            self::set_post_language($newPostId, $lang);

            // 🔹 Salva meta traduzida
            if ($translatedMeta) {
                update_post_meta($newPostId, '_pga_meta_description', $translatedMeta);
            }

            if (class_exists('AlphaSuite_SEO')) {
                AlphaSuite_SEO::apply_meta($newPostId, [
                    'title'         => $translatedTitle,
                    'description'   => $translatedMeta,
                    'focus_keyword' => '',
                ]);
            }

            $terms = wp_get_post_terms($originalPostId, 'category');

            if (!empty($terms) && !is_wp_error($terms)) {

                $translatedTerms = [];

                foreach ($terms as $term) {

                    $translatedTermId = function_exists('pll_get_term')
                        ? pll_get_term($term->term_id, $lang)
                        : 0;

                    if ($translatedTermId) {
                        $translatedTerms[] = $translatedTermId;
                    }
                }

                if (!empty($translatedTerms)) {
                    wp_set_post_terms($newPostId, $translatedTerms, 'category');
                }
            }

            // 🔹 Duplica attachment (mesmo arquivo, novo post_attachment)
            $thumbId = get_post_thumbnail_id($originalPostId);

            if ($thumbId) {

                $file = get_attached_file($thumbId);

                if ($file && file_exists($file)) {

                    $filetype = wp_check_filetype(basename($file), null);

                    $attachment = [
                        'post_mime_type' => $filetype['type'],
                        'post_title'     => $translatedTitle,
                        'post_content'   => '',
                        'post_status'    => 'inherit'
                    ];

                    $newAttachmentId = wp_insert_attachment($attachment, $file, $newPostId);

                    require_once ABSPATH . 'wp-admin/includes/image.php';
                    $attach_data = wp_generate_attachment_metadata($newAttachmentId, $file);
                    wp_update_attachment_metadata($newAttachmentId, $attach_data);

                    // 🔥 Define idioma do attachment
                    pll_set_post_language($newAttachmentId, $lang);

                    // 🔥 Traduz ALT
                    $alt = get_post_meta($thumbId, '_wp_attachment_image_alt', true);

                    if ($alt) {
                        $translatedAlt = AlphaSuite_AI::translate($alt, $lang);
                        if (!is_wp_error($translatedAlt)) {
                            update_post_meta($newAttachmentId, '_wp_attachment_image_alt', $translatedAlt);
                        }
                    }

                    set_post_thumbnail($newPostId, $newAttachmentId);
                }
            }

            $translations[$lang] = $newPostId;
        }

        pll_save_post_translations($translations);
    }

    public static function selftest()
    {
        if (!function_exists('pll_set_post_language')) {
            return rest_ensure_response([
                'ok' => false,
                'errors' => ['Polylang não está instalado ou ativo.']
            ]);
        }

        return rest_ensure_response(['ok' => true]);
    }

    public static function pga_rest_save_generators(WP_REST_Request $req)
    {
        global $wpdb;

        $tab_id = sanitize_text_field($req['tab_id']);
        $generators = $req['generators'];

        if (!$tab_id || !is_array($generators)) {
            return new WP_Error('invalid_data', 'Dados inválidos');
        }

        // geradores existentes da tab
        $existing = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}pga_generators WHERE tab_id = %s",
                $tab_id
            )
        );

        $existing_ids = wp_list_pluck($existing, 'id');
        $received_ids = [];

        foreach ($generators as $g) {

            $gen_id = intval($g['id'] ?? 0);

            $active = !empty($g['active']) ? 1 : 0;
            $start  = intval($g['start_hour'] ?? 0);
            $end    = intval($g['end_hour'] ?? 23);
            $interval = intval($g['interval_hours'] ?? 1);
            $name   = sanitize_text_field($g['template_key'] ?? 'Gerador');

            if ($gen_id && in_array($gen_id, $existing_ids)) {

                // UPDATE
                $wpdb->update(
                    "{$wpdb->prefix}pga_generators",
                    [
                        'active' => $active,
                        'start_hour' => $start,
                        'end_hour' => $end,
                        'interval_hours' => $interval,
                        'name' => $name
                    ],
                    ['id' => $gen_id]
                );
            } else {

                // INSERT
                $wpdb->insert(
                    "{$wpdb->prefix}pga_generators",
                    [
                        'tab_id' => $tab_id,
                        'name' => $name,
                        'active' => $active,
                        'start_hour' => $start,
                        'end_hour' => $end,
                        'interval_hours' => $interval
                    ]
                );

                $gen_id = $wpdb->insert_id;

                $wpdb->insert(
                    "{$wpdb->prefix}pga_generator_runtime",
                    [
                        'generator_id' => $gen_id,
                        'interval_hours' => $interval,
                        'next_run' => wp_date(
                            'Y-m-d H:i:s',
                            current_time('timestamp') + ($interval * MINUTE_IN_SECONDS)
                        )
                    ]
                );
            }

            // config
            $wpdb->replace(
                "{$wpdb->prefix}pga_generator_config",
                [
                    'generator_id' => $gen_id,
                    'config_json' => wp_json_encode($g)
                ]
            );

            $received_ids[] = $gen_id;
        }

        // remove geradores deletados da UI
        foreach ($existing_ids as $old_id) {

            if (!in_array($old_id, $received_ids)) {

                $wpdb->delete("{$wpdb->prefix}pga_generators", ['id' => $old_id]);
                $wpdb->delete("{$wpdb->prefix}pga_generator_config", ['generator_id' => $old_id]);
                $wpdb->delete("{$wpdb->prefix}pga_generator_runtime", ['generator_id' => $old_id]);
            }
        }

        return ['success' => true];
    }

    private static function resolve_google_redirect($link)
    {
        if (strpos($link, 'google.com/url?') === false) {
            return $link;
        }

        $parts = parse_url($link);

        if (empty($parts['query'])) {
            return $link;
        }

        parse_str($parts['query'], $query);

        if (!empty($query['url'])) {
            return esc_url_raw($query['url']);
        }

        return $link;
    }

    public static function rest_extract_image(WP_REST_Request $request)
    {
        $post_id = (int) $request->get_param('post_id');
        $url     = (string) $request->get_param('url');

        return self::extract_image($post_id, $url);
    }

    public static function extract_image($postId, $url = '')
    {
        if (!$postId) {
            return false;
        }

        // 🔥 Se já tem thumbnail, não faz nada
        if (has_post_thumbnail($postId)) {
            return true;
        }

        $title   = get_the_title($postId);

        $image_alt = trim($title ?? 'Imagem ilustrativa');

        $attachmentId = 0;

        /*
    |--------------------------------------------------------------------------
    | 1️⃣ Tenta extrair imagem da página
    |--------------------------------------------------------------------------
    */

        if (!empty($url)) {

            $response = wp_remote_get($url, [
                'timeout' => 10,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (AlphaSuiteBot)'
                ]
            ]);

            if (!is_wp_error($response)) {

                $html = wp_remote_retrieve_body($response);

                if ($html) {

                    $imageUrl = '';

                    // og:image
                    if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)/i', $html, $m)) {
                        $imageUrl = esc_url_raw($m[1]);
                    }

                    // twitter:image
                    if (!$imageUrl && preg_match('/<meta[^>]+name=["\']twitter:image["\'][^>]+content=["\']([^"\']+)/i', $html, $m)) {
                        $imageUrl = esc_url_raw($m[1]);
                    }

                    // fallback img
                    if (!$imageUrl && preg_match('/<img[^>]+src=["\']([^"\']+)/i', $html, $m)) {
                        $imageUrl = esc_url_raw($m[1]);
                    }

                    if ($imageUrl) {

                        // normaliza relativa
                        if (!preg_match('#^https?://#', $imageUrl)) {
                            $imageUrl = esc_url_raw(
                                rtrim($url, '/') . '/' . ltrim($imageUrl, '/')
                            );
                        }

                        require_once ABSPATH . 'wp-admin/includes/file.php';
                        require_once ABSPATH . 'wp-admin/includes/media.php';
                        require_once ABSPATH . 'wp-admin/includes/image.php';

                        $attachmentId = media_sideload_image($imageUrl, $postId, null, 'id');
                    }
                }
            }
        }
        $template = get_post_meta($postId, '_pga_template_key', true) ?: 'rss';


        if (!$attachmentId || is_wp_error($attachmentId)) {
            if (!class_exists('AlphaSuite_Prompts') || !class_exists('AlphaSuite_Images')) {
                return false;
            }

            $imageProvider = class_exists('AlphaSuite_AI')
                ? AlphaSuite_AI::get_image_provider()
                : 'pollinations';

            $meta_img_prompt = AlphaSuite_Prompts::build_image_prompt(
                $title,
                $title,
                '',
                $template,
                $imageProvider
            );

            $img_prompt = $meta_img_prompt;

            if (class_exists('AlphaSuite_AI')) {
                $resolved = AlphaSuite_AI::image_prompt($meta_img_prompt, []);
                if (!is_wp_error($resolved) && is_string($resolved) && $resolved !== '') {
                    $img_prompt = trim($resolved);
                }
            }

            if ($img_prompt) {

                $attachmentId = AlphaSuite_Images::generate_by_settings(
                    $img_prompt,
                    intval($postId),
                    $image_alt
                );
            }
        }

        /*
    |--------------------------------------------------------------------------
    | 3️⃣ Finaliza
    |--------------------------------------------------------------------------
    */

        if (!is_wp_error($attachmentId) && $attachmentId) {

            set_post_thumbnail($postId, $attachmentId);

            update_post_meta($attachmentId, '_wp_attachment_image_alt', $image_alt);
            update_post_meta($postId, '_pga_image_alt', $image_alt);

            return $attachmentId;
        }

        return false;
    }


    public static function start(WP_REST_Request $req)
    {
        $title  = sanitize_text_field($req->get_param('title'));
        $hash   = sanitize_text_field($req->get_param('hash'));
        $link   = esc_url_raw($req->get_param('link'));
        $source = sanitize_text_field($req->get_param('source'));
        $length = sanitize_text_field($req->get_param('length') ?: 'short');
        $locale = sanitize_text_field($req->get_param('locale') ?: 'pt_BR');
        $category_id = intval($req->get_param('category_id'));
        $author = intval($req->get_param('author'));
        $tags        = (array) $req->get_param('tags');
        $link_mode   = sanitize_text_field($req->get_param('link_mode') ?: 'none');
        $link_max    = intval($req->get_param('link_max') ?: 1);
        $link_manual = (array) $req->get_param('pga_link_max');
        $link_ids = (array) $req->get_param('link_manual_ids');
        $languages = (array) $req->get_param('pga_languages');
        $make_faq    = !empty($req->get_param('make_faq'));
        $faq_qty     = intval($req->get_param('faq_qty') ?: 0);
        $template_key     = sanitize_text_field($req->get_param('template_key') ?: 'rss');

        $enable_multilang     = intval($req->get_param('enable_multilang') ?: 0);


        if (!$title || !$hash) {
            return new WP_Error('pga_invalid_data', 'Título ou hash inválido.');
        }

        /*
        |--------------------------------------------------------------------------
        | 1️⃣ Verifica duplicidade
        |--------------------------------------------------------------------------
        */

        // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value
        $exists = new WP_Query([
            'post_type'              => 'posts_orion',
            'meta_key'               => '_pga_news_hash',
            'meta_value'             => $hash,
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);
        // phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value

        if ($exists->have_posts()) {
            return ['duplicate' => true];
        }

        /*
        |--------------------------------------------------------------------------
        | 2️⃣ Cria post base
        |--------------------------------------------------------------------------
        */

        $postId = wp_insert_post([
            'post_title'  => "GERANDO " . $title,
            'post_status' => 'draft',
            'post_type'   => 'posts_orion',
            'post_author' => $author
        ]);

        if (is_wp_error($postId) || !$postId) {
            return new WP_Error('pga_insert_failed', 'Falha ao criar post.');
        }

        /*
        |--------------------------------------------------------------------------
        | 3️⃣ Salva dados RSS
        |--------------------------------------------------------------------------
        */

        update_post_meta($postId, '_pga_news_hash', $hash);

        update_post_meta($postId, '_pga_rss_context', [
            'link'   => $link,
            'source' => $source
        ]);

        update_post_meta($postId, '_pga_author', $author);
        update_post_meta($postId, '_pga_template_key', $template_key);
        update_post_meta($postId, '_pga_multilang_enabled', $enable_multilang);
        update_post_meta($postId, '_pga_languages', $languages);
        update_post_meta($postId, '_pga_rss_seed_title', $title);

        update_post_meta($postId, '_pga_outline_length', $length);
        update_post_meta($postId, '_pga_outline_locale', $locale);
        update_post_meta($postId, '_pga_length', $length);
        update_post_meta($postId, '_pga_locale', $locale);

        update_post_meta($postId, '_pga_link_mode', $link_mode);
        update_post_meta($postId, '_pga_link_max', $link_max);
        update_post_meta($postId, '_pga_link_manual', $link_ids);
        update_post_meta($postId, 'pga_link_max', $link_manual);

        update_post_meta($postId, '_pga_make_faq', $make_faq);
        update_post_meta($postId, '_pga_faq_qty', $faq_qty);

        update_post_meta($postId, '_pga_job_status', 'started');

        $tags = array_map('intval', (array)$tags);

        if (!empty($tags)) {
            wp_set_object_terms($postId, $tags, 'post_tag', false);
        }

        if ($category_id > 0) {
            wp_set_post_terms($postId, [$category_id], 'category');
        }

        $hasSourceContent = false;

        if (!empty($link)) {

            $data = self::extract_article_data($link);

            if (is_wp_error($data)) {
                return AlphaSuite_FailJob::fail_job($postId, $data);
            }

            if (!empty($data['content'])) {
                update_post_meta($postId, '_pga_source_content', $data['content']);
                update_post_meta($postId, '_pga_source_url', $link);
                $hasSourceContent = true;
            }
        }

        update_post_meta($postId, '_pga_has_source_content', $hasSourceContent);

        /*
        |--------------------------------------------------------------------------
        | 5️⃣ Retorno
        |--------------------------------------------------------------------------
        */

        return [
            'duplicate' => false,
            'post_id'   => $postId,
            'has_source_content' => $hasSourceContent
        ];
    }

    public static function rest_generate_faq(WP_REST_Request $req)
    {
        $postId = (int) $req->get_param('post_id');

        if (!$postId) {
            return new WP_Error('pga_invalid_post', 'Post ID inválido.');
        }

        $result = self::build_faq($postId);

        if (is_wp_error($result)) {
            return $result;
        }

        return [
            'ok'  => true,
            'faq' => $result['faq']
        ];
    }

    public static function build_faq(int $postId)
    {
        $postId = intval($postId);

        if (!$postId || !get_post($postId)) {
            return new WP_Error('pga_invalid_post', 'Post inválido.');
        }

        $keyword = get_post_meta($postId, '_pga_rss_seed_title', true) ?: '';
        $qty     = (int) get_post_meta($postId, '_pga_faq_qty', true) ?: 5;
        $locale  = get_post_meta($postId, '_pga_locale', true) ?: 'pt_BR';

        // 🔥 CONTEXTO INTELIGENTE (aqui tá o pulo do gato)
        $context = get_post_field('post_content', $postId);

        if (!$context) {
            $sections_json = get_post_meta($postId, '_pga_outline_sections', true);
            $sections = json_decode($sections_json, true);

            if (is_array($sections)) {
                $parts = [];

                foreach ($sections as $sec) {
                    $parts[] = $sec['heading'] ?? '';
                    $parts[] = $sec['paragraph'] ?? '';

                    if (!empty($sec['children'])) {
                        foreach ($sec['children'] as $child) {
                            $parts[] = $child['heading'] ?? '';
                            $parts[] = $child['paragraph'] ?? '';
                        }
                    }
                }

                $context = implode("\n", array_filter($parts));
            }
        }

        if (!$context) {
            return new WP_Error('pga_faq_no_context', 'Sem contexto para FAQ.');
        }

        // opcional (recomendado)
        $context = wp_trim_words($context, 300);

        $faq = AlphaSuite_AI::faq([
            'keyword' => $keyword,
            'qty'     => $qty,
            'locale'  => $locale,
            'context' => $context
        ]);

        if (is_wp_error($faq)) {
            return $faq;
        }

        update_post_meta($postId, '_pga_faq_jsonld', $faq);

        return [
            'ok'      => true,
            'faq'     => $faq,
            'post_id' => $postId
        ];
    }

    private static function extract_article_data(string $url)
    {
        if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $response = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (AlphaSuiteBot)'
            ]
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code === 403) {
            return new WP_Error('domain_forbidden', 'Domain blocked (403)');
        }

        if ($code !== 200) {
            return false;
        }

        $html = wp_remote_retrieve_body($response);

        if (!$html || strlen($html) < 500) {
            return false;
        }

        // Remove scripts e styles
        $html = preg_replace('#<script(.*?)</script>#is', '', $html);
        $html = preg_replace('#<style(.*?)</style>#is', '', $html);

        $title = '';
        $content = '';

        /*
    |--------------------------------------------------------------------------
    | 1️⃣ OG TITLE
    |--------------------------------------------------------------------------
    */
        if (preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)/i', $html, $m)) {
            $title = trim($m[1]);
        }

        /*
    |--------------------------------------------------------------------------
    | 2️⃣ Extrai conteúdo do <article> ou <main>
    |--------------------------------------------------------------------------
    */

        libxml_use_internal_errors(true);

        if (!$html) {
            return new WP_Error('pga_invalid_content', 'O site não permite copia.');
        }

        $dom = new DOMDocument();
        $dom->loadHTML($html);

        $xpath = new DOMXPath($dom);

        // Tenta primeiro <article>
        $nodes = $xpath->query('//article//p');

        if ($nodes->length === 0) {
            // Fallback para <main>
            $nodes = $xpath->query('//main//p');
        }

        $paragraphs = [];

        foreach ($nodes as $node) {

            $text = trim($node->textContent);
            $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

            if (mb_strlen($text) > 80) {
                $paragraphs[] = $text;
            }
        }

        if (empty($paragraphs)) {
            return false;
        }

        $maxChars = 9000; // ~1300 a 1500 palavras

        $totalChars = 0;
        $finalParagraphs = [];

        foreach ($paragraphs as $p) {

            $length = mb_strlen($p);

            if (($totalChars + $length) > $maxChars) {
                break; // para quando atingir o limite
            }

            if ($length < 80) {
                continue; // ignora parágrafos muito curtos
            }

            $finalParagraphs[] = $p;
            $totalChars += $length;
        }

        // Se nenhum parágrafo couber (fallback)
        if (empty($finalParagraphs)) {
            $finalParagraphs = array_slice($paragraphs, 0, 5);
        }

        // Reconstrói HTML
        $wrapped = [];

        foreach ($finalParagraphs as $p) {
            $wrapped[] = '<p>' . esc_html($p) . '</p>';
        }

        $content = implode("\n", $wrapped);

        return [
            'title'   => $title,
            'content' => $content,
        ];
    }


    public static function generate_slug(int $postId)
    {
        $postId = intval($postId);

        if (!$postId || !get_post($postId)) {
            return new WP_Error('pga_invalid_post', 'Post inválido.');
        }

        $template = get_post_meta($postId, '_pga_template_key', true) ?: 'rss';
        $locale   = get_post_meta($postId, '_pga_outline_locale', true) ?: 'pt_BR';

        $chosenTitle = get_post_meta($postId, '_pga_chosen_title', true);
        if (!$chosenTitle) {
            $chosenTitle = get_post_field('post_title', $postId);
        }

        $keyword = get_post_meta($postId, '_pga_keyword', true) ?: $chosenTitle;

        if (!$chosenTitle) {
            return new WP_Error('pga_no_title', 'Título não encontrado para gerar slug.');
        }

        // 🔥 MESMO PADRÃO DO ORION
        $promptSlug = AlphaSuite_Prompts::build_slug_prompt(
            (string)$template,
            (string)$keyword,
            (string)$chosenTitle,
            (string)$locale
        );

        $respSlug = AlphaSuite_AI::slug($promptSlug);

        if (is_wp_error($respSlug)) {
            return AlphaSuite_FailJob::fail_job($postId, $respSlug);
        }

        $slugTxt = '';

        // --------- EXTRAÇÃO SEGURA ----------
        if (is_string($respSlug)) {
            $slugTxt = $respSlug;
        } elseif (is_array($respSlug)) {
            $slugTxt = (string)($respSlug['slug'] ?? $respSlug['content'] ?? '');
        } elseif (is_object($respSlug)) {
            $slugTxt = (string)($respSlug->slug ?? $respSlug->content ?? '');
        }

        $slugTxt = trim($slugTxt);

        // --------- JSON DENTRO DE TEXTO ----------
        if ($slugTxt !== '' && ($slugTxt[0] === '{' || $slugTxt[0] === '[')) {
            $j = json_decode($slugTxt, true);
            if (is_array($j)) {
                $slugTxt = (string)($j['slug'] ?? $j['content'] ?? '');
            }
        }

        // --------- REMOVE PREFIXOS ----------
        $slugTxt = preg_replace('/^\s*(slug|post_name)\s*:\s*/i', '', $slugTxt);

        // --------- PRIMEIRA LINHA ----------
        $slugTxt = preg_split("/\r\n|\r|\n/", $slugTxt)[0] ?? $slugTxt;
        $slugTxt = trim($slugTxt);

        // --------- SANITIZA ----------
        $newSlug = sanitize_title($slugTxt);

        // --------- FALLBACKS ----------
        if ($newSlug === '') {
            $newSlug = sanitize_title($chosenTitle);
        }
        if ($newSlug === '') {
            $newSlug = sanitize_title($keyword);
        }
        if ($newSlug === '') {
            $newSlug = sanitize_title(uniqid('rss_', false));
        }

        $postType = get_post_type($postId) ?: 'posts_orion';

        $newSlug = wp_unique_post_slug(
            $newSlug,
            $postId,
            'draft',
            $postType,
            0
        );

        wp_update_post([
            'ID'        => $postId,
            'post_name' => $newSlug,
        ]);

        update_post_meta($postId, '_pga_generated_slug', $newSlug);
        update_post_meta($postId, '_pga_job_status', 'slug_done');

        return [
            'post_id' => $postId,
            'slug'    => $newSlug,
        ];
    }

    public static function rest_generate_slug(WP_REST_Request $req)
    {
        $postId = intval($req->get_param('post_id'));

        if (!$postId) {
            return new WP_Error('pga_invalid_post', 'Post ID inválido.');
        }

        $result = self::generate_slug($postId);

        if (is_wp_error($result)) {
            return $result;
        }

        return [
            'ok'   => true,
            'slug' => $result['slug'],
        ];
    }

    public static function rest_generate_title(WP_REST_Request $req)
    {
        $postId = intval($req->get_param('post_id'));

        if (!$postId) {
            return new WP_Error('invalid_post', 'Post ID inválido.');
        }

        $result = self::generate_title($postId);

        if (is_wp_error($result)) {
            return $result;
        }

        return [
            'ok'    => true,
            'title' => $result['title'],
        ];
    }

    public static function generate_title(int $postId)
    {
        $postId = intval($postId);

        if (!$postId || !get_post($postId)) {
            return new WP_Error('invalid_post', 'Post inválido.');
        }

        $context = get_post_meta($postId, '_pga_rss_context', true) ?: [];
        $template = get_post_meta($postId, '_pga_template_key', true) ?: 'rss';

        // 👇 seed central
        $seed = get_post_meta($postId, '_pga_rss_seed_title', true);
        if (!$seed) {
            $seed = get_post_field('post_title', $postId);
        }

        if (!$seed) {
            return new WP_Error('no_seed', 'Título base vazio.');
        }

        $locale = get_post_meta($postId, '_pga_outline_locale', true) ?: 'pt_BR';
        $url    = $context['link'] ?? '';

        $newTitle = AlphaSuite_Titles::getTitle(
            $postId,
            $template,
            '',
            $locale,
            $url,
            $seed,

        );

        if (is_wp_error($newTitle)) {
            return $newTitle; // ESSENCIAL
        }

        if (!is_string($newTitle) || trim($newTitle) === '') {
            return new WP_Error('empty_title', 'Título retornado vazio.');
        }

        wp_update_post([
            'ID' => $postId,
            'post_title' => $newTitle,
        ]);


        update_post_meta($postId, '_pga_chosen_title', $newTitle);
        update_post_meta($postId, '_pga_job_status', 'title_done');

        return [
            'post_id' => $postId,
            'title'   => $newTitle,
        ];
    }

    public static function rest_generate_meta(WP_REST_Request $req)
    {
        $postId = intval($req->get_param('post_id'));

        if (!$postId) {
            return new WP_Error('pga_invalid_post', 'Post ID inválido.');
        }

        $content = get_post_field('post_content', $postId);

        // fallback → outline
        if (!$content) {

            $sections_json = get_post_meta($postId, '_pga_outline_sections', true);
            $sections = json_decode($sections_json, true);

            if (is_array($sections)) {

                $parts = [];

                foreach ($sections as $sec) {

                    $parts[] = $sec['heading'] ?? '';
                    $parts[] = $sec['paragraph'] ?? '';

                    if (!empty($sec['children'])) {
                        foreach ($sec['children'] as $child) {
                            $parts[] = $child['heading'] ?? '';
                            $parts[] = $child['paragraph'] ?? '';
                        }
                    }
                }

                $content = implode("\n", array_filter($parts));
            }
        }

        $result = AlphaSuite_Meta_description::generate_meta($postId, $content);

        if (is_wp_error($result)) {
            return $result;
        }

        return [
            'ok'   => true,
            'meta' => $result['meta'],
        ];
    }

    public static function rest_generate_excerpt(WP_REST_Request $req)
    {
        $postId = intval($req->get_param('post_id'));

        if (!$postId) {
            return new WP_Error('pga_invalid_post', 'Post ID inválido.');
        }

        $content = get_post_field('post_content', $postId);

        if (!$content) {

            $sections_json = get_post_meta($postId, '_pga_outline_sections', true);
            $sections = json_decode($sections_json, true);

            if (is_array($sections)) {

                $parts = [];

                foreach ($sections as $sec) {

                    $parts[] = $sec['paragraph'] ?? '';

                    if (!empty($sec['children'])) {
                        foreach ($sec['children'] as $child) {
                            $parts[] = $child['paragraph'] ?? '';
                        }
                    }
                }

                $content = implode("\n", array_filter($parts));
            }
        }

        $result = AlphaSuite_Excerpt::generate_excerpt($postId, $content);

        if (is_wp_error($result)) {
            return $result;
        }

        return [
            'ok'      => true,
            'excerpt' => $result['excerpt'],
        ];
    }


    public static function generate_outline(int $postId, string $content)
    {
        $postId = intval($postId);

        if (!$postId || !get_post($postId)) {
            return new WP_Error('pga_invalid_post', 'Post inválido.');
        }

        if (!$content) {
            $sourceContent = get_post_meta($postId, '_pga_source_content', true);
        } else {
            $sourceContent = $content;
        }

        $length        = get_post_meta($postId, '_pga_outline_length', true) ?: 'short';
        $locale        = get_post_meta($postId, '_pga_outline_locale', true) ?: 'pt_BR';

        $title = get_post_meta($postId, '_pga_chosen_title', true);
        if (!$title) {
            $title = get_post_field('post_title', $postId);
        }

        if (!$title) {
            return new WP_Error('pga_no_title', 'Título não encontrado.');
        }

        $context = get_post_meta($postId, '_pga_rss_context', true) ?: [];
        $url     = $context['link'] ?? '';

        $template = get_post_meta($postId, '_pga_template_key', true) ?: 'rss';

        $outline = AlphaSuite_Outline::generate(
            $template,
            '',
            $title,
            $length,
            $locale,
            $url,
            $sourceContent
        );

        if (is_wp_error($outline)) {
            return AlphaSuite_FailJob::fail_job($postId, $outline);
        }

        $sections = self::normalize_outline($outline);

        if (!is_array($sections)) {
            return new WP_Error('pga_outline_invalid', 'Outline inválido "outline".');
        }

        // Normalização igual Orion
        $normalized = [];
        $h2Index = 1;

        foreach ($sections as $sec) {

            if (!is_array($sec)) {
                $sec = [
                    'heading' => (string)$sec,
                    'level'   => 'h2',
                ];
            }

            $sec['level'] = 'h2';
            $sec['id']    = $sec['id'] ?? (string)$h2Index;
            $sec['children'] = $sec['children'] ?? [];

            $childIndex = 1;

            foreach ($sec['children'] as $ci => $child) {

                if (!is_array($child)) {
                    $child = [
                        'heading' => (string)$child,
                        'level'   => 'h3',
                    ];
                }

                $child['level'] = 'h3';
                $child['id']    = $child['id'] ?? ($sec['id'] . '.' . $childIndex);

                $sec['children'][$ci] = $child;
                $childIndex++;
            }

            $normalized[] = $sec;
            $h2Index++;
        }

        $linkMode = get_post_meta($postId, '_pga_link_mode', true) ?: 'none';

        if ($linkMode !== 'none') {

            $maxLinks = intval(get_post_meta($postId, 'pga_link_max', true) ?: 1);

            $internalLinks = [];
            if ($linkMode === 'manual') {

                $manualIds = get_post_meta($postId, '_pga_link_manual', true) ?: [];

                foreach ((array)$manualIds as $pid) {
                    $p = get_post(intval($pid));
                    if ($p) {
                        $internalLinks[] = [
                            'anchor' => $p->post_title,
                            'url'    => get_permalink($p->ID),
                        ];
                    }
                }
            } elseif ($linkMode === 'auto') {

                $recent = get_posts([
                    'post_type'      => 'post',
                    'post_status'    => 'publish',
                    'posts_per_page' => $maxLinks,
                    'orderby'        => 'date',
                    'order'          => 'DESC'
                ]);

                foreach ($recent as $p) {
                    $internalLinks[] = [
                        'anchor' => $p->post_title,
                        'url'    => get_permalink($p->ID),
                    ];
                }
            }

            $internalLinks = array_slice($internalLinks, 0, $maxLinks);

            if (!empty($internalLinks)) {

                $totalSections = count($normalized);
                $totalLinks    = count($internalLinks);

                // 🔥 Se não tem seção, aborta distribuição
                if ($totalSections === 0) {

                    $normalized[] = [
                        'id' => 1,
                        'level' => 'h2',
                        'heading' => 'Conteúdo',
                        'paragraph' => '',
                        '_internal_links' => []
                    ];

                    $totalSections = 1;
                }

                for ($i = 0; $i < $totalLinks; $i++) {

                    $pos = $i % $totalSections;

                    $normalized[$pos]['_internal_links'][] = $internalLinks[$i];
                }
            }
        }

        update_post_meta(
            $postId,
            '_pga_outline_sections',
            wp_json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        update_post_meta($postId, '_pga_job_status', 'outline_done');

        return [
            'post_id'  => $postId,
            'sections' => $normalized,
        ];
    }

    private static function normalize_outline($resp)
    {
        if (is_array($resp)) {

            if (isset($resp['sections']) && is_array($resp['sections'])) {
                return $resp['sections'];
            }

            if (isset($resp[0]) && is_array($resp[0])) {
                return $resp;
            }
        }

        if (is_string($resp)) {
            $decoded = json_decode($resp, true);

            if (isset($decoded['sections'])) {
                return $decoded['sections'];
            }

            if (isset($decoded[0])) {
                return $decoded;
            }
        }

        return [];
    }

    public static function rest_generate_outline(WP_REST_Request $req)
    {
        $postId = intval($req->get_param('post_id'));

        if (!$postId) {
            return new WP_Error('pga_invalid_post', 'Post ID inválido.');
        }

        $result = self::generate_outline($postId, '');

        if (is_wp_error($result)) {
            return $result;
        }

        return [
            'ok'       => true,
            'sections' => $result['sections'],
        ];
    }

    public static function generate_section(int $postId, string $sectionId)
    {
        $postId = intval($postId);

        if (!$postId || !get_post($postId)) {
            return new WP_Error('pga_invalid_post', 'Post inválido.');
        }

        $sectionsRaw = get_post_meta($postId, '_pga_outline_sections', true);

        if (!$sectionsRaw) {
            return new WP_Error('pga_no_outline', 'Outline não encontrado.');
        }

        // 🔥 HÍBRIDO
        if (is_array($sectionsRaw)) {

            // Caso array contendo JSON string
            if (isset($sectionsRaw[0]) && is_string($sectionsRaw[0])) {
                $sections = json_decode($sectionsRaw[0], true);
            } else {
                $sections = $sectionsRaw;
            }
        } elseif (is_string($sectionsRaw)) {

            $sections = json_decode($sectionsRaw, true);
        } else {
            $sections = [];
        }

        if (!is_array($sections) || empty($sections)) {
            return new WP_Error('pga_outline_invalid', 'Outline inválido "section".');
        }

        $section = null;
        $index   = 0;

        foreach ($sections as $i => $sec) {
            if ((string)($sec['id'] ?? '') === (string)$sectionId) {
                $section = $sec;
                $index   = $i + 1;
                break;
            }
        }

        if (!$section) {
            return new WP_Error('pga_section_not_found', 'Seção não encontrada.');
        }

        $metaKey = '_pga_section_content_' . sanitize_key($sectionId);

        // 🔒 Evita regeração
        if (get_post_meta($postId, $metaKey, true)) {
            return ['already_done' => true];
        }

        $title    = get_the_title($postId);
        $length   = get_post_meta($postId, '_pga_outline_length', true) ?: 'medium';
        $locale   = get_post_meta($postId, '_pga_outline_locale', true) ?: 'pt_BR';
        $context = get_post_meta($postId, '_pga_rss_context', true) ?: [];
        $url     = $context['link'] ?? '';
        $font    = $context['source'] ?? '';

        $sectionsCount = count($sections);

        /*
        |--------------------------------------------------------------------------
        | 🔗 LINK INTERNO INJETADO NO PROMPT
        |--------------------------------------------------------------------------
        */

        $internalLinks = $section['_internal_links'] ?? [];

        $linkInstruction = '';

        if (!empty($internalLinks)) {
            $linkInstruction .= "OBRIGATÓRIO INSERIR OS SEGUINTES LINKS INTERNOS:\n";

            foreach ($internalLinks as $link) {

                $anchor = esc_html($link['anchor']);
                $href   = esc_url($link['url']);

                $linkInstruction .=
                    "No lugar de \"{$anchor}\" resuma para algum termo referente ao título:\n"
                    . "- Use nesse formato HTML:\n"
                    . "<a href=\"{$href}\">[termo]</a>\n"
                    . "- Não altere a URL\n"
                    . "- Insira o link de maneira fluida, se encaixando no texto, nada de \"clique para saber mais\", \"acesse o link\"... "
                    . "ou seja, zero CTA em texto, apenas o texto fluído\n"
                    . "- Ex: \"Quando Jorge Kimberland <a href target>inventou a invenção x</a>, todos se alegraram.\"\n"
                    . "- Use cada link apenas uma vez e é obrigatório usar cada um ao menos uma vez\n\n";
            }

            $linkInstruction .=
                "REGRA:\n"
                . "- Distribua os links naturalmente ao longo do texto\n"
                . "- Nunca coloque todos os links no mesmo parágrafo\n"
                . "- Não crie seção apenas para link\n\n";
        }

        /*
        |--------------------------------------------------------------------------
        | 🧠 PROMPT BASE
        |--------------------------------------------------------------------------
        */

        $template = get_post_meta($postId, '_pga_template_key', true) ?: 'rss';


        $prompt = AlphaSuite_Prompts::build_section_prompt(
            $template,
            '',
            $title,
            $section,
            $length,
            $locale,
            $sectionsCount,
            (string)$index,
            '',
            $url
        );

        // 🔥 adiciona instrução de link ao final
        $prompt .= "\n\n" . $linkInstruction;

        $resp = AlphaSuite_AI::complete(
            $prompt,
            [],
            [
                'max_tokens'  => 2000,
                'temperature' => 0.6,
                'template'    => 'section',
            ]
        );

        if (is_wp_error($resp)) {
            return AlphaSuite_FailJob::fail_job($postId, $resp);
        }

        $content_html = trim((string)($resp ?? ''));

        if ($content_html === '') {
            return new WP_Error('pga_section_empty', 'Conteúdo vazio.');
        }

        update_post_meta($postId, $metaKey, $content_html);

        return [
            'post_id'    => $postId,
            'section_id' => $sectionId,
        ];
    }

    public static function rest_generate_section(WP_REST_Request $req)
    {
        $postId    = intval($req->get_param('post_id'));
        $sectionId = (string)$req->get_param('section_id');

        if (!$postId || !$sectionId) {
            return new WP_Error('pga_invalid_params', 'Parâmetros inválidos.');
        }

        $result = self::generate_section($postId, $sectionId);

        if (is_wp_error($result)) {
            return $result;
        }

        return [
            'ok'         => true,
            'section_id' => $sectionId,
        ];
    }

    public static function finalize(int $postId)
    {
        $postId = intval($postId);

        if (!$postId || !get_post($postId)) {
            return new WP_Error('pga_invalid_post', 'Post inválido.');
        }

        $sectionsJson      = get_post_meta($postId, '_pga_outline_sections', true);
        $metaDescription   = get_post_meta($postId, '_pga_meta_description', true);
        $meta_title        = get_post_meta($postId, '_pga_chosen_title', true);

        if (!$sectionsJson) {
            return new WP_Error('pga_no_outline', 'Outline não encontrado.');
        }

        $sections = json_decode($sectionsJson, true);

        if (!is_array($sections)) {
            return new WP_Error('pga_outline_invalid', 'Outline inválido "finalize".');
        }

        $contentParts = [];

        foreach ($sections as $sec) {

            $sid = sanitize_key($sec['id'] ?? '');
            if (!$sid) continue;

            $metaKey = '_pga_section_content_' . $sid;
            $text    = get_post_meta($postId, $metaKey, true);

            if (!$text) continue;

            $contentParts[] = $text; // 🔥 NÃO adiciona H2 manualmente
        }
        $content = trim(implode("\n\n", $contentParts));

        if ($content === '') {
            return new WP_Error('pga_empty_content', 'Nenhuma seção encontrada.');
        }

        // 🔥 Normaliza parágrafos
        $content = wpautop($content);

        // 🔥 Remove H1
        $content = preg_replace('#</?h1[^>]*>#i', '', $content);

        // 🔥 Remove o PRIMEIRO H2
        $content = preg_replace('#<h2[^>]*>.*?</h2>#i', '', $content, 1);
        $content = self::convert_to_blocks($content);

        $faq_json = get_post_meta($postId, '_pga_faq_jsonld', true);

        if ($faq_json) {
            $faq = is_string($faq_json)
                ? json_decode($faq_json, true)
                : $faq_json;

            if (is_array($faq)) {
                $faq_block = AlphaSuite_FAQ::render_faq_block($faq, $content);

                if ($faq_block !== '') {
                    $content .= "\n\n" . $faq_block;
                }
            }
        }

        // Atualiza conteúdo
        wp_update_post([
            'ID'           => $postId,
            'post_content' => $content,
        ]);

        // 🔥 PUBLICA O POST
        wp_update_post([
            'ID'          => $postId,
            'post_status' => 'publish',
        ]);

        update_post_meta($postId, '_pga_job_status', 'finalized');

        if (class_exists('AlphaSuite_SEO')) {
            AlphaSuite_SEO::apply_meta($postId, [
                'title'         => $meta_title,
                'description'   => $metaDescription,
                'focus_keyword' => '',
            ]);
        }

        return [
            'post_id' => $postId,
            'title'   => get_the_title($postId),
            'url'     => get_permalink($postId),
        ];
    }

    private static function convert_to_blocks($content)
    {
        $protected_tags = [
            'ul' => 'list',
            'ol' => 'list',
            'table' => 'table',
            'blockquote' => 'quote',
            'pre' => 'code',
            'figure' => 'image'
        ];

        $placeholders = [];
        $i = 0;

        // 🔒 Protege blocos complexos
        foreach ($protected_tags as $tag => $block_type) {

            if (preg_match_all('#<' . $tag . '.*?>.*?</' . $tag . '>#si', $content, $matches)) {

                foreach ($matches[0] as $html) {

                    $key = "__BLOCK_" . $i . "__";

                    $placeholders[$key] = [
                        'html' => $html,
                        'type' => $block_type
                    ];

                    $content = str_replace($html, $key, $content);

                    $i++;
                }
            }
        }

        $lines = preg_split('/\r\n|\r|\n/', $content);

        $out = [];

        foreach ($lines as $line) {

            $line = trim($line);
            if ($line === '') continue;

            // 🔄 restaura bloco protegido
            if (isset($placeholders[$line])) {

                $block = $placeholders[$line];

                $out[] = '<!-- wp:' . $block['type'] . ' -->';
                $out[] = $block['html'];
                $out[] = '<!-- /wp:' . $block['type'] . ' -->';

                continue;
            }

            // H2
            if (preg_match('#^<h2#i', $line)) {

                $out[] = '<!-- wp:heading {"level":2} -->';
                $out[] = $line;
                $out[] = '<!-- /wp:heading -->';
            }
            // H3
            elseif (preg_match('#^<h3#i', $line)) {

                $out[] = '<!-- wp:heading {"level":3} -->';
                $out[] = $line;
                $out[] = '<!-- /wp:heading -->';
            }
            // Parágrafo existente
            elseif (preg_match('#^<p#i', $line)) {

                $out[] = '<!-- wp:paragraph -->';
                $out[] = $line;
                $out[] = '<!-- /wp:paragraph -->';
            }
            // fallback
            else {

                $out[] = '<!-- wp:paragraph -->';
                $out[] = '<p>' . $line . '</p>';
                $out[] = '<!-- /wp:paragraph -->';
            }
        }

        return implode("\n", $out);
    }

    public static function rest_finalize(WP_REST_Request $req)
    {
        $postId = intval($req->get_param('post_id'));

        if (!$postId) {
            return new WP_Error('pga_invalid_post', 'Post ID inválido.');
        }

        $result = self::finalize($postId);

        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response([
            'ok'      => true,
            'post_id' => $result['post_id'],
            'title'   => $result['title'],
            'url'     => $result['url'],
        ]);
    }

    public static function get_rss(WP_REST_Request $req)
    {
        $params  = $req->get_json_params();
        $rssUrl  = trim($params['feedUrl'] ?? '');
        $blocked = array_map('mb_strtolower', (array)($params['blocked_words'] ?? []));

        if (!$rssUrl) {
            return new WP_Error('no_url', 'URL is required', ['status' => 400]);
        }

        $response = wp_remote_get($rssUrl, [
            'timeout' => 60,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (AlphaSuiteRSS)'
            ]
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('fetch_error', 'Failed to fetch RSS', ['status' => 500]);
        }

        $body = wp_remote_retrieve_body($response);
        if (!$body) {
            return new WP_Error('empty_body', 'Empty RSS body', ['status' => 500]);
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);

        if (!$xml) {
            return new WP_Error('invalid_rss', 'Invalid RSS/Atom feed', ['status' => 500]);
        }

        if (!empty($xml->channel->item)) {
            $entries = $xml->channel->item;
        } elseif (!empty($xml->entry)) {
            $entries = $xml->entry;
        } else {
            return new WP_Error('invalid_rss', 'Feed sem itens válidos', ['status' => 500]);
        }

        $items = [];

        foreach ($entries as $item) {

            $title = trim((string) $item->title);
            $title = html_entity_decode($title, ENT_QUOTES, 'UTF-8');
            $title = strip_tags($title);
            $title = preg_replace('/\s+-\s+.+$/', '', $title);

            if (!$title) continue;

            // 🔥 FILTRO BLOCK WORDS AQUI
            $titleCheck = mb_strtolower($title);
            $skip = false;

            foreach ($blocked as $word) {
                if ($word === '') continue;

                if (preg_match('/\b' . preg_quote($word, '/') . '\b/ui', $titleCheck)) {
                    $skip = true;
                    break;
                }
            }

            if ($skip) continue;

            $link = '';

            if (isset($item->link)) {
                if (is_string((string)$item->link)) {
                    $link = trim((string) $item->link);
                }

                if (empty($link) && isset($item->link['href'])) {
                    $link = trim((string) $item->link['href']);
                }
            }

            if (empty($link)) continue;

            $link = self::resolve_google_redirect($link);
            if (empty($link)) continue;

            $source = '';
            if (!empty($item->source)) {
                $source = trim((string) $item->source);
            }

            $items[] = [
                'title'   => $title,
                'link'    => $link,
                'guid'    => $link,
                'source'  => $source,
                'author'  => $source,
                'pubDate' => (string) ($item->pubDate ?? $item->published ?? ''),
                'hash'    => md5(strtolower($link))
            ];

            // 🔥 Retorna no máximo 10 válidos
            if (count($items) >= 10) {
                break;
            }
        }

        return rest_ensure_response([
            'rss_url' => $rssUrl,
            'count'   => count($items),
            'items'   => $items
        ]);
    }

    public static function process_feed($rssUrl, $generator_id = 0, $generator_config = [])
    {
        global $wpdb;

        $items = self::fetch_feed_items($rssUrl, 3);

        if (empty($items)) {
            return;
        }

        // 🔥 1️⃣ Filtra títulos com palavras proibidas
        $blocked = array_map('mb_strtolower', (array)($generator_config['blocked_words'] ?? []));

        $validItems = [];

        foreach ($items as $item) {

            $title = $item['title'] ?? '';

            $skip = false;

            foreach ($blocked as $word) {
                if ($word === '') continue;

                if (preg_match('/\b' . preg_quote($word, '/') . '\b/ui', $title)) {
                    $skip = true;
                    break;
                }
            }

            if (!$skip) {
                $validItems[] = $item;
            }
        }

        if (empty($validItems)) {
            return;
        }

        // 🔥 2️⃣ Monta textos para embedding
        $texts = [];

        foreach ($validItems as $item) {
            $texts[] = trim(
                ($item['title'] ?? '') . ' ' .
                    ($item['description'] ?? '')
            );
        }

        // 🔥 3️⃣ Gera embeddings em lote
        $embeddings = AlphaSuite_AI::embeddings($texts);

        if (is_wp_error($embeddings)) {
            return;
        }

        // 🔥 4️⃣ Busca embeddings antigos UMA VEZ
        $recent = self::get_recent_embeddings($generator_id, 30);

        // 🔥 5️⃣ Loop final
        foreach ($validItems as $index => $item) {

            $newEmbedding = $embeddings[$index] ?? null;

            if (!$newEmbedding || !is_array($newEmbedding)) {
                continue;
            }

            foreach ($recent as $row) {

                if (empty($row->embedding)) continue;

                $oldEmbedding = json_decode($row->embedding, true);

                if (!$oldEmbedding || !is_array($oldEmbedding)) continue;

                $score = self::cosine_similarity($newEmbedding, $oldEmbedding);

                if ($score > 0.75) {
                    continue 2;
                }
            }

            // 🔹 Se passou na verificação → cria post
            $postId = self::create_base_post($item, $generator_config);

            if (!$postId) {
                continue;
            }

            $content = '';
            $hasSourceContent = false;

            if (!empty($item['link'])) {

                $data = self::extract_article_data($item['link']);

                if (is_wp_error($data)) {
                    wp_delete_post($postId, true);
                    continue;
                }

                if (!empty($data['content'])) {

                    $content = $data['content'];
                    $hasSourceContent = true;
                } else {
                    wp_delete_post($postId, true);
                    continue;
                }
            }

            update_post_meta($postId, '_pga_has_source_content', $hasSourceContent);

            $category_id = intval($generator_config['category'] ?? 0);

            if ($category_id > 0) {
                wp_set_post_terms($postId, [$category_id], 'category');
            }

            self::generate_title($postId);
            self::generate_slug($postId);

            if (!$content) {
                wp_delete_post($postId, true);
                continue;
            }

            $outline  = self::generate_outline($postId, $content);

            $sections = $outline['sections'] ?? [];

            if (!empty($sections)) {
                foreach ($sections as $sec) {
                    if (!empty($sec['id'])) {
                        self::generate_section($postId, (string) $sec['id']);
                    }
                }
            }

            AlphaSuite_Excerpt::generate_excerpt($postId, $content);
            AlphaSuite_Meta_description::generate_meta($postId, $content);

            if ($generator_config['make_faq']) {
                self::build_faq($postId);
            }

            $result = self::finalize($postId);

            if (is_wp_error($result)) {
                wp_delete_post($postId, true);
                continue;
            }

            // 🔹 Se multilíngue ativo, traduz
            $multilang_enabled = !empty($generator_config['enable_multilang']);
            $languages = (array)($generator_config['languages'] ?? []);

            if ($multilang_enabled && !empty($languages)) {
                self::create_translations($postId, [
                    'multilang_enabled' => true,
                    'languages' => $languages
                ]);
            }

            // 🔹 Salva registro com embedding
            $wpdb->insert(
                "{$wpdb->prefix}pga_generator_items",
                [
                    'generator_id' => $generator_id,
                    'status'       => 'done',
                    'post_id'      => $postId,
                    'embedding'    => wp_json_encode($newEmbedding),
                    'created_at'   => current_time('mysql'),
                    'generated_at' => current_time('mysql'),
                ]
            );

            // 🔹 Extrai imagem se existir link
            if (!empty($item['link'])) {
                self::extract_image($postId, $item['link']);
            }

            break;
        }
    }

    public static function mark_as_done($generator_id, $hash, $postId)
    {
        global $wpdb;

        if (!$generator_id || !$hash) {
            return;
        }

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->insert(
            "{$wpdb->prefix}pga_generator_items",
            [
                'generator_id' => $generator_id,
                'keyword'      => $hash,
                'status'       => 'done',
                'post_id'      => $postId,
                'created_at'   => current_time('mysql'),
                'generated_at' => current_time('mysql'),
            ]
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    }


    public static function fetch_feed_items($rssUrl, $limit = 5)
    {
        $response = wp_remote_get($rssUrl, [
            'timeout' => 15,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (AlphaSuiteRSS)'
            ]
        ]);

        if (is_wp_error($response)) {
            return [];
        }

        $body = wp_remote_retrieve_body($response);
        if (!$body) {
            return [];
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);

        if (!$xml) {
            return [];
        }

        // 🔥 Detecta formato
        if (!empty($xml->channel->item)) {
            $entries = $xml->channel->item; // RSS
        } elseif (!empty($xml->entry)) {
            $entries = $xml->entry; // Atom (Google Alerts)
        } else {
            return [];
        }

        $items = [];

        foreach ($entries as $item) {

            // 🔹 TÍTULO (remove html e bold do Google Alerts)
            $title = trim((string) $item->title);
            $title = html_entity_decode($title, ENT_QUOTES, 'UTF-8');
            $title = strip_tags($title);
            $title = preg_replace('/\s+-\s+.+$/', '', $title);

            // 🔹 LINK
            $link = '';

            if (isset($item->link)) {

                // RSS
                if (is_string((string)$item->link)) {
                    $link = trim((string) $item->link);
                }

                // Atom (href attribute)
                if (empty($link) && isset($item->link['href'])) {
                    $link = trim((string) $item->link['href']);
                }
            }

            if (empty($link)) {
                continue;
            }

            // 🔥 resolve redirect Google
            $link = self::resolve_google_redirect($link);

            if (empty($link)) {
                continue;
            }

            // 🔥 normaliza link
            $normalizedLink = preg_replace('/\?.*/', '', strtolower(trim($link)));

            // 🔹 SOURCE
            $source = '';

            if (!empty($item->source)) {
                $source = trim((string) $item->source);
            }

            $items[] = [
                'title'   => $title,
                'link'    => $link,
                'source'  => $source,
                'pubDate' => (string) ($item->pubDate ?? $item->published ?? ''),
                'hash'    => md5($normalizedLink)
            ];

            if (count($items) >= $limit) {
                break;
            }
        }

        return $items;
    }

    public static function create_base_post(array $item, array $generator_config = [])
    {
        $author_id = $generator_config['author'] ?? 1;

        $postId = wp_insert_post([
            'post_title'  => $item['title'],
            'post_status' => 'draft',
            'post_type'   => 'posts_orion',
            'post_author' => $author_id
        ]);

        if (!$postId || is_wp_error($postId)) {
            return false;
        }


        if (!empty($generator_config['tags'])) {

            $tags = array_map('intval', (array)$generator_config['tags']);

            if (!empty($tags)) {
                wp_set_object_terms($postId, $tags, 'post_tag', false);
            }
        }

        $template_key = $generator_config['template_key'] ?? 'rss';

        update_post_meta($postId, '_pga_news_hash', $item['hash']);

        update_post_meta($postId, '_pga_rss_context', [
            'link'   => $item['link'],
            'source' => $item['source']
        ]);

        update_post_meta($postId, '_pga_rss_seed_title', $item['title']);
        update_post_meta($postId, '_pga_length', 'short');
        update_post_meta($postId, '_pga_locale', 'pt_BR');
        update_post_meta($postId, '_pga_job_status', 'started');
        update_post_meta($postId, '_pga_template_key', $template_key);

        /*
        |---------------------------------------------------
        | 🔥 MULTILANG CONFIG
        |---------------------------------------------------
        */

        $multilang_enabled = !empty($generator_config['multilang_enabled']);
        $languages         = (array)($generator_config['languages'] ?? []);
        $default_language  = $generator_config['default_language'] ?? 'pt';

        update_post_meta($postId, '_pga_multilang_enabled', $multilang_enabled);
        update_post_meta($postId, '_pga_languages', $languages);

        if ($multilang_enabled && function_exists('pll_set_post_language')) {

            if (!in_array($default_language, $languages, true)) {
                $default_language = $languages[0] ?? 'pt';
            }

            pll_set_post_language($postId, $default_language);
        }

        return $postId;
    }

    private static function get_recent_embeddings($generator_id, $limit = 30)
    {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "
            SELECT embedding
            FROM {$wpdb->prefix}pga_generator_items
            WHERE embedding IS NOT NULL
            ORDER BY id DESC
            LIMIT %d
            ",
                $generator_id,
                $limit
            )
        );
    }

    private static function cosine_similarity(array $a, array $b): float
    {
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        foreach ($a as $i => $val) {

            if (!isset($b[$i])) continue;

            if (!is_numeric($val) || !is_numeric($b[$i])) {
                continue;
            }

            $valA = (float) $val;
            $valB = (float) $b[$i];

            $dot   += $valA * $valB;
            $normA += $valA * $valA;
            $normB += $valB * $valB;
        }

        if ($normA == 0.0 || $normB == 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }
}
