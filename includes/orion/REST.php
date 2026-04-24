<?php
if (!defined('ABSPATH')) exit;

class AlphaSuite_REST
{

    /**
     * Normaliza array de strings: trim, remove vazios e duplicados (case-insensitive)
     */
    private static function unique_clean(array $arr): array
    {
        $arr = array_map('trim', $arr);

        $arr = array_filter($arr, function ($s) {
            return $s !== '';
        });

        $lower = function ($s) {
            return function_exists('mb_strtolower')
                ? mb_strtolower($s, 'UTF-8')
                : strtolower($s);
        };

        $seen = array();
        $out  = array();

        foreach ($arr as $s) {
            $k = $lower($s);
            if (!isset($seen[$k])) {
                $seen[$k] = 1;
                $out[]    = $s;
            }
        }

        return array_values($out);
    }

    /**
     * Salva PENDENTES como string "a\nb\nc"
     */
    private static function kw_set_pending(array $a): void
    {
        $clean = self::unique_clean($a);
        update_option('pga_kw_pending', implode("\n", $clean), false);
    }


    /**
     * Limpa pendentes
     */
    private static function kw_clear_pending(): void
    {
        update_option('pga_kw_pending', '', false);
    }

    /**
     * Limpa concluídas
     */
    private static function kw_clear_done(): void
    {
        update_option('pga_kw_done', '', false);
    }

    /**
     * Lê pendentes como ARRAY
     */
    protected static function kw_get_pending(): array
    {
        $raw = (string) get_option('pga_kw_pending', '');
        if ($raw === '') return array();

        $lines = preg_split('/\r\n|\r|\n/', $raw);
        $lines = array_map('trim', $lines);
        $lines = array_filter($lines, function ($s) {
            return $s !== '';
        });

        return array_values($lines);
    }

    /**
     * Lê concluídas como ARRAY
     */
    protected static function kw_get_done(): array
    {
        $raw = (string) get_option('pga_kw_done', '');
        if ($raw === '') return array();

        $lines = preg_split('/\r\n|\r|\n/', $raw);
        $lines = array_map('trim', $lines);
        $lines = array_filter($lines, function ($s) {
            return $s !== '';
        });

        return array_values($lines);
    }

    // ---------------------- utils ----------------------
    private static function verify_nonce($req)
    {
        $n = $req->get_header('X-WP-Nonce');
        if (!$n || !wp_verify_nonce($n, 'wp_rest')) {
            return new WP_Error('rest_forbidden', 'Nonce inválido ou ausente.', ['status' => 403]);
        }
        return true;
    }
    private static function clean($s)
    {
        return sanitize_text_field((string)$s);
    }

    private static function lines_to_array($txt)
    {
        if (!is_string($txt) || $txt === '') return array();

        $lines = preg_split('/\r\n|\r|\n/', $txt);
        $lines = array_map('trim', $lines);

        // sem arrow function
        $lines = array_filter($lines, function ($s) {
            return $s !== '';
        });

        return self::unique_clean($lines);
    }

    private static function guard(callable $fn)
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            return new WP_Error('pga_exception', 'Exceção interna.', ['status' => 500]);
        }
    }

    // ---------------------- rotas ----------------------
    public static function register_routes()
    {
        $base = 'pga/v1';
        register_rest_route($base, '/orion/keywords', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'keywords'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        register_rest_route($base, '/orion/faq', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'generate_faq'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        register_rest_route($base, '/orion/outline', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'handle_outline'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        register_rest_route($base, '/analytics', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'alpha_suite_get_analytics'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            }
        ]);

        register_rest_route($base, '/orion/section', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'handle_section'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        register_rest_route($base, '/orion/finalize', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'handle_finalize'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        register_rest_route($base, '/orion/image', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'handle_image'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        register_rest_route($base, '/license/activate', [
            'methods'  => 'POST',
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
            'callback' => [__CLASS__, 'activate'],
        ]);

        register_rest_route($base, '/license/status', [
            'methods'  => 'GET',
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
            'callback' => [__CLASS__, 'status'],
        ]);

        register_rest_route($base, '/plan', [
            'methods'  => 'POST',
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'callback' => [__CLASS__, 'plan'],
        ]);

        register_rest_route($base, '/status', [
            'methods'  => 'GET',
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'callback' => [__CLASS__, 'status_licence'],
        ]);

        register_rest_route($base, '/keywords', [
            'methods'  => 'GET',
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'callback' => [__CLASS__, 'keywords_get'],
        ]);

        register_rest_route($base, '/keywords', [
            'methods'  => 'POST',
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'callback' => [__CLASS__, 'keywords_save'],
        ]);

        register_rest_route($base, '/keywords/clear', [
            'methods'  => 'POST',
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'callback' => [__CLASS__, 'keywords_clear'],
        ]);

        register_rest_route($base, '/selftest', [
            'methods'  => ['GET', 'POST'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
            'callback' => [__CLASS__, 'selftest'],
        ]);

        register_rest_route($base, '/youtube/selftest', [
            'methods'  => ['GET', 'POST'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
            'callback' => [__CLASS__, 'youtube_selftest'],
        ]);
    }

    private static function ensure_youtube_key()
    {
        if (!class_exists('AlphaSuite_Settings')) {
            return new WP_Error(
                'pga_youtube_no_settings',
                'AlphaSuite_Settings não encontrado para ler a chave do YouTube.'
            );
        }

        $opt = AlphaSuite_Settings::get();
        $key = trim($opt['apis']['youtube']['key'] ?? '');

        if ($key === '') {
            return new WP_Error(
                'pga_youtube_no_key',
                'Nenhuma chave da API do YouTube configurada. Vá em Alpha Suite → Configurações → YouTube API.',
                ['status' => 400]
            );
        }

        return $key;
    }

    public static function youtube_selftest($req)
    {
        $v = self::verify_nonce($req);
        if (is_wp_error($v)) return $v;

        $key = self::ensure_youtube_key();
        if (is_wp_error($key)) {
            return $key;
        }

        // Teste bem leve: tenta buscar 1 vídeo fixo
        $url = add_query_arg([
            'part' => 'id',
            'id'   => 'dQw4w9WgXcQ', // qualquer ID válido
            'key'  => $key,
        ], 'https://www.googleapis.com/youtube/v3/videos');

        $res = wp_remote_get($url, [
            'timeout' => 10,
        ]);

        if (is_wp_error($res)) {
            return new WP_Error(
                'pga_youtube_http',
                $res->get_error_message(),
                ['status' => 500]
            );
        }

        $code = wp_remote_retrieve_response_code($res);
        $body = json_decode(wp_remote_retrieve_body($res), true);

        if ($code !== 200) {
            $msg = 'YouTube retornou HTTP ' . $code;
            if (!empty($body['error']['message'])) {
                $msg = $body['error']['message'];
            }

            return new WP_Error(
                'pga_youtube_api',
                $msg,
                ['status' => $code]
            );
        }

        // Se chegou aqui, a chave é válida para consultas básicas
        return [
            'ok'     => true,
            'sample' => 'YouTube API funcionando.',
        ];
    }

    public static function permission()
    {
        return current_user_can('edit_posts');
    }

    /**
     * POST /wp-json/pga/v1/orion/image
     * Body: { post_id: 123, keyword?: "...", locale?: "...", template?: "..." }
     */
    public static function handle_image(WP_REST_Request $req)
    {
        $v = self::verify_nonce($req);
        if (is_wp_error($v)) return $v;

        $params = $req->get_json_params();
        if (empty($params)) {
            $params = $req->get_params();
        }

        $post_id = intval($params['post_id'] ?? 0);
        if (!$post_id || get_post_type($post_id) === null) {
            return new WP_Error(
                'pga_bad_request',
                __('post_id inválido.', 'alpha-suite'),
                ['status' => 400]
            );
        }

        // keyword / locale / template com fallbacks
        $keyword  = trim((string)($params['keyword'] ?? ''));
        if ($keyword === '') {
            $keyword = (string) get_post_meta($post_id, '_pga_outline_keyword', true);
        }

        $locale = (string)($params['locale'] ?? '');
        if ($locale === '') {
            $locale = get_post_meta($post_id, '_pga_outline_locale', true) ?: 'pt_BR';
        }

        $template = (string)($params['template'] ?? '');
        if ($template === '') {
            $template = get_post_meta($post_id, '_pga_outline_template', true) ?: 'discover_article';
        }

        $title = get_post_meta($post_id, '_pga_chosen_title', true);
        if ($title === '') {
            $title = get_the_title($post_id) ?: $keyword;
        }

        $image_alt = get_post_meta($post_id, '_pga_image_alt', true);
        if ($image_alt === '') {
            $image_alt = $title;
        }

        if ($keyword === '' || $title === '') {
            return new WP_Error(
                'pga_img_missing_data',
                __('Dados insuficientes para gerar imagem.', 'alpha-suite'),
                ['status' => 400]
            );
        }

        // 🔹 Provider de IMAGEM (Pexels / Unsplash / Pollinations / OpenAI etc.)
        if (isset($params['image_provider']) && $params['image_provider'] !== '') {
            $imageProvider = (string) $params['image_provider'];
        } else {
            // pega do Orion/settings
            $imageProvider = class_exists('AlphaSuite_AI')
                ? AlphaSuite_AI::get_image_provider()
                : 'pollinations';
        }

        $result = alpha_suite_generate_image([
            'target'        => 'orion',
            'post_id'       => $post_id,
            'keyword'       => $keyword,
            'title'         => $title,
            'content'       => get_post_field('post_content', $post_id) ?: '',
            'locale'        => $locale,
            'template'      => $template,
            'alt'           => $image_alt,
            'image_provider'=> $imageProvider,
        ]);

        if (is_wp_error($result)) {
            return new WP_Error(
                'pga_img_fail',
                $result->get_error_message(),
                ['status' => 500]
            );
        }

        $thumb_id = (int)($result['attachment_id'] ?? 0);
        if ($thumb_id <= 0) {
            return new WP_Error('pga_img_fail', 'Falha ao gerar imagem.', ['status' => 500]);
        }

        // define thumbnail do post
        set_post_thumbnail($post_id, $thumb_id);

        // guarda metadados pra referência
        update_post_meta($post_id, '_pga_image_prompt',   (string)($result['prompt'] ?? ''));
        update_post_meta($post_id, '_pga_image_provider', $imageProvider);
        update_post_meta($post_id, '_pga_image_alt',      $image_alt);

        return rest_ensure_response([
            'ok'        => true,
            'post_id'   => $post_id,
            'thumb_id'  => $thumb_id,
            'provider'  => $imageProvider,
            'prompt'    => $img_prompt,
        ]);
    }

    /**
     * POST /wp-json/pga/v1/orion/outline
     * Body: { keywords: [...], length, template, locale, publish_time, category_id, post_type }
     */
    public static function handle_outline(WP_REST_Request $req)
    {
        $v = self::verify_nonce($req);
        if (is_wp_error($v)) return $v;

        $params = $req->get_json_params();
        if (empty($params)) {
            $params = $req->get_params(); // fallback pra form-urlencoded
        }

        // Gera rascunho + outline
        $res = AlphaSuite_Pages_Generator::create_draft_and_outline($params);

        if (is_wp_error($res)) {
            return $res;
        }
        return rest_ensure_response($res);
    }

    /**
     * POST /wp-json/pga/v1/orion/section
     * Body: { post_id: 123, section_id: "1" }
     */
    public static function handle_section(WP_REST_Request $req)
    {
        $v = self::verify_nonce($req);
        if (is_wp_error($v)) return $v;

        $params     = $req->get_json_params();
        if (empty($params)) {
            $params = $req->get_params();
        }

        $post_id    = intval($params['post_id'] ?? 0);
        $section_id = (string)($params['section_id'] ?? '');

        if (!$post_id || $section_id === '') {
            return new WP_Error(
                'pga_bad_request',
                __('post_id ou section_id ausentes.', 'alpha-suite'),
                ['status' => 400]
            );
        }

        $res = AlphaSuite_Pages_Generator::generate_section_content($post_id, $section_id);
        if (is_wp_error($res)) {
            return $res;
        }

        return rest_ensure_response($res);
    }


    /**
     * POST /wp-json/pga/v1/orion/finalize
     * Body: { post_id: 123 }
     */
    public static function handle_finalize(WP_REST_Request $req)
    {
        $v = self::verify_nonce($req);
        if (is_wp_error($v)) return $v;

        $params = $req->get_json_params();
        if (empty($params)) {
            $params = $req->get_params();
        }

        $post_id = intval($params['post_id'] ?? 0);
        if (!$post_id) {
            return new WP_Error(
                'pga_bad_request',
                __('post_id ausente.', 'alpha-suite'),
                ['status' => 400]
            );
        }

        // opções de link interno vindas do JS
        $internal_opts = [];
        if (!empty($params['internal_links']) && is_array($params['internal_links'])) {
            $internal_opts = $params['internal_links'];
        }
        // NOVO: controla se a finalização vai ou não gerar imagem
        $skip_images    = !empty($params['images_provider']);
        $generate_image = !$skip_images;

        // 1) Finaliza via Generator
        $res = AlphaSuite_Pages_Generator::finalize_from_sections(
            $post_id,
            [
                'internal_links' => $internal_opts,
                'generate_image' => $generate_image,
            ]
        );

        if (is_wp_error($res)) {
            return $res;
        }

        // garante que $res seja array pra anexar o estado
        if (!is_array($res)) {
            $res = ['result' => $res];
        }

        return rest_ensure_response($res);
    }

    public static function activate(WP_REST_Request $req)
    {
        $nonce = $req->get_header('X-WP-Nonce');
        if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_Error('rest_forbidden', 'Nonce inválido.', ['status' => 403]);
        }
        $p = $req->get_json_params();
        $email = sanitize_email($p['email'] ?? '');
        $pid   = sanitize_text_field($p['purchase_id'] ?? '');
        if (!$email || !$pid) return new WP_Error('pga_lic', 'Informe e-mail e ID da compra.', ['status' => 400]);

        $lic = AlphaSuite_License::get_state($email, $pid);
        return ['ok' => AlphaSuite_License::is_active($lic), 'license' => $lic];
    }

    public static function status_licence()
    {
        $lic = AlphaSuite_License::get_state();
        return [
            'ok'      => AlphaSuite_License::is_active(),
            'license' => $lic,
        ];
    }

    public static function alpha_suite_get_analytics(WP_REST_Request $request)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'pga_post_views';

        $days = (int) $request->get_param('range');
        if ($days <= 0) $days = 30;

        // 📈 timeline
        $timeline = $wpdb->get_results($wpdb->prepare("
        SELECT 
            DATE(viewed_at) as date,
            COUNT(*) as views,
            AVG(duration) as avg_duration
            FROM $table
            WHERE viewed_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            GROUP BY DATE(viewed_at)
            ORDER BY date ASC
        ", $days), ARRAY_A);

        $labels = [];
        $views = [];
        $duration = [];

        foreach ($timeline as $row) {
            $labels[]   = $row['date'];
            $views[]    = (int) $row['views'];
            $duration[] = round((float)$row['avg_duration'], 0);
        }

        // 🏆 top views
        $top_views = $wpdb->get_results($wpdb->prepare("
            SELECT post_id, COUNT(*) as views
            FROM $table
            WHERE viewed_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            GROUP BY post_id
            ORDER BY views DESC
            LIMIT 10
        ", $days), ARRAY_A);


        $top_posts = array_map(function ($row) {
            $post = $row['post_id'];
            return [
                'title' => get_the_title($post),
                'url'   => $post ? get_permalink($post) : '#',
                'views' => (int)$row['views']
            ];
        }, $top_views);

        // 🧠 top duração
        $top_duration = $wpdb->get_results($wpdb->prepare("
            SELECT post_id, AVG(duration) as avg_duration
            FROM $table
            WHERE viewed_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            GROUP BY post_id
            ORDER BY avg_duration DESC
            LIMIT 10
        ", $days), ARRAY_A);

        $top_time = array_map(function ($row) {
            $post = $row['post_id'];
            return [
                'title' => get_the_title($post),
                'url'   => $post ? get_permalink($post) : '#',
                'duration' => round((float)$row['avg_duration'], 0)
            ];
        }, $top_duration);

        // ⏱️ média geral
        $avg = $wpdb->get_var($wpdb->prepare("
            SELECT AVG(duration)
            FROM $table
            WHERE viewed_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
        ", $days));

        return [
            'labels' => $labels,
            'views' => $views,
            'duration' => $duration,
            'avg_duration' => round((float)$avg, 0),
            'top_posts' => $top_posts,
            'top_time' => $top_time
        ];
    }

    public static function keywords(\WP_REST_Request $req)
    {
        $v = self::verify_nonce($req);
        if (is_wp_error($v)) return $v;

        $p = $req->get_json_params();
        $command  = trim((string)($p['command'] ?? ''));
        $locale   = sanitize_text_field((string)($p['locale'] ?? 'pt_BR'));
        $template = sanitize_key((string)($p['template'] ?? 'article'));

        $category_raw  = $p['category'] ?? '';
        $category_name = '';

        if (is_numeric($category_raw) && (int)$category_raw > 0) {
            $term = get_term((int)$category_raw, 'category');
            if ($term && !is_wp_error($term)) $category_name = (string)$term->name;
        } else {
            $slug = sanitize_title((string)$category_raw);
            if ($slug !== '') {
                $term = get_term_by('slug', $slug, 'category');
                if ($term && !is_wp_error($term)) $category_name = (string)$term->name;
                else $category_name = ucwords(str_replace(['-', '_'], ' ', $slug));
            }
        }

        $count = isset($p['count']) ? (int)$p['count'] : 20;
        $count = max(1, min(100, $count));

        $existing = (string)($p['existing_keywords'] ?? '');
        $existing = wp_strip_all_tags($existing);
        $existing = preg_replace("/\r\n|\r/", "\n", $existing);
        $existing_list = array_values(array_filter(array_map('trim', explode("\n", $existing))));
        $existing_list = array_slice($existing_list, 0, 200);

        $prompt = AlphaSuite_Prompts::build_keywords_prompt(
            $template,
            $command,
            $locale,
            $count,
            $category_name,
            $existing_list
        );


        $resp = AlphaSuite_AI::complete($prompt, [], [
            'temperature' => 0.3,
        ]);


        if (is_wp_error($resp)) return $resp;

        // 🔥 Se vier JSON como string, decodifica
        if (is_string($resp)) {
            $decoded = json_decode($resp, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $resp = $decoded;
            }
        }
        if (!is_array($resp) || !isset($resp['content'])) {
            return new WP_Error('kw_invalid_response', 'Resposta inválida do gerador de keywords.');
        }

        $content = $resp['content'];

        /*
        |--------------------------------------------------------------------------
        | 🔥 CASO 1: ARRAY DIRETO (CORRETO)
        |--------------------------------------------------------------------------
        */
        if (is_array($content)) {
            $lines = array_values(array_filter(
                array_map('trim', $content),
                fn($l) => $l !== ''
            ));
        }
        /*
        |--------------------------------------------------------------------------
        | 🔥 CASO 2: STRING NORMAL
        |--------------------------------------------------------------------------
        */ elseif (is_string($content)) {

            $text = trim($content);

            // unwrap se vier JSON dentro da string
            if ($text !== '' && $text[0] === '{') {
                $inner = json_decode($text, true);
                if (is_array($inner) && isset($inner['content'])) {
                    $content = $inner['content'];

                    if (is_array($content)) {
                        $lines = array_values(array_filter(
                            array_map('trim', $content),
                            fn($l) => $l !== ''
                        ));
                    } else {
                        $text = trim((string)$content);
                    }
                }
            }

            if (!isset($lines)) {
                $text = str_replace(["\r\n", "\r"], "\n", $text);

                $lines = array_values(array_filter(
                    array_map('trim', explode("\n", $text)),
                    fn($l) => $l !== ''
                ));
            }
        } else {
            return new WP_Error('kw_invalid_type', 'Formato inesperado do content.');
        }

        /*
        |--------------------------------------------------------------------------
        | 🔥 LIMITA
        |--------------------------------------------------------------------------
        */
        $lines = array_slice($lines, 0, $count);

        return [
            'ok' => true,
            'keywords_text' => implode("\n", $lines),
        ];
    }

    public static function generate_faq(WP_REST_Request $req)
    {
        $post_id = (int) $req['post_id'];
        $qty     = min(5, max(1, (int) $req['qty']));
        $keyword = sanitize_text_field($req['keyword'] ?? '');
        $locale  = sanitize_text_field($req['locale'] ?? 'pt_BR');
        $content = get_post_field('post_content', $post_id);

        if (!$content) {

            $sections_json = get_post_meta($post_id, '_pga_outline_sections', true);
            $sections = json_decode($sections_json, true);

            if (is_array($sections)) {

                $parts = [];

                foreach ($sections as $sec) {

                    if (!empty($sec['heading'])) {
                        $parts[] = $sec['heading'];
                    }

                    if (!empty($sec['paragraph'])) {
                        $parts[] = $sec['paragraph'];
                    }

                    if (!empty($sec['children']) && is_array($sec['children'])) {

                        foreach ($sec['children'] as $child) {

                            if (!empty($child['heading'])) {
                                $parts[] = $child['heading'];
                            }

                            if (!empty($child['paragraph'])) {
                                $parts[] = $child['paragraph'];
                            }
                        }
                    }
                }

                $content = implode("\n", $parts);
            }
        }
        $content = wp_trim_words($content, 600);

        $faq = AlphaSuite_AI::faq([
            'keyword' => $keyword,
            'qty'     => $qty,
            'locale'  => $locale,
            'context' => $content
        ]);

        if (is_wp_error($faq)) {
            return $faq;
        }

        // salva JSON-LD no meta
        update_post_meta($post_id, '_pga_faq_jsonld', $faq);

        return ['ok' => true];
    }

    public static function plan($req)
    {
        $v = self::verify_nonce($req);
        if (is_wp_error($v)) return $v;

        return self::guard(function () use ($req) {
            $p    = $req->get_json_params();
            $mode = (isset($p['mode']) && $p['mode'] === 'single') ? 'single' : 'multi';

            $il_raw = is_array($p['internal_links'] ?? null) ? $p['internal_links'] : [];

            // modo: none | auto | pillar | manual
            $link_mode = self::clean($il_raw['mode'] ?? 'none');
            if (!in_array($link_mode, ['none', 'auto', 'pillar', 'manual'], true)) {
                $link_mode = 'none';
            }

            // máximo de links por post
            $link_max = max(0, intval($il_raw['max'] ?? 0));

            // "12,34, 56" -> [12, 34, 56]
            $link_manual_ids = array_values(array_filter(array_map(
                'absint',
                explode(',', (string)($il_raw['manual_ids'] ?? ''))
            )));

            // textarea (um por linha)
            $kw_in = self::lines_to_array($p['keywords'] ?? '');

            $locale = self::clean($p['locale'] ?? 'pt_BR');
            $tpl    = self::clean($p['template_key'] ?? 'article');
            $length = self::clean($p['length'] ?? 'short');

            $tags = [];
            if (!empty($p['tags']) && is_array($p['tags'])) {
                $tags = array_values(array_filter(array_map(
                    'sanitize_text_field',
                    $p['tags']
                )));
            }

            $faq = [
                'enabled' => !empty($p['faq']['enabled']),
                'qty'     => min(5, max(1, intval($p['faq']['qty'] ?? 0))),
            ];

            // modelar URL e modelar YouTube
            $isModelar = in_array($tpl, ['modelar', 'modelar_youtube'], true);

            if ($tpl === 'modelar' || $tpl === 'modelar_youtube') {
                $kw_in = array_map(function ($u) {
                    $u = trim($u);

                    // remove espaços invisíveis / caracteres ruins
                    $u = preg_replace('/[\x00-\x1F\x7F]/u', '', $u);

                    // evita prefixos quebrados tipo "/-xxxxx"
                    if (str_starts_with($u, '/-')) {
                        $u = ltrim($u, '/-');
                    }

                    // se não começa com http, tenta consertar
                    if (!preg_match('~^https?://~i', $u)) {
                        $u = 'https://' . ltrim($u, '/');
                    }

                    return esc_url_raw($u);
                }, $kw_in);
            }

            if ($tpl === 'modelar_youtube') {
                $ytKey = self::ensure_youtube_key();
                if (is_wp_error($ytKey)) {
                    return $ytKey;
                }
            }

            // VALIDAÇÃO
            if (!$isModelar) {
                if ($mode === 'multi' && empty($kw_in)) {
                    return new WP_Error(
                        'pga_kw',
                        'Informe palavras-chave (modo múltiplo).',
                        ['status' => 400]
                    );
                }
                if ($mode === 'single' && empty($kw_in)) {
                    return new WP_Error(
                        'pga_kw',
                        'Informe ao menos 1 palavra (modo único).',
                        ['status' => 400]
                    );
                }
            } else {
                if (empty($kw_in)) {
                    return new WP_Error(
                        'pga_kw',
                        'Para modelar, informe pelo menos 1 URL (uma por linha).',
                        ['status' => 400]
                    );
                }
            }

            $total  = max(1, intval($p['total'] ?? ($mode === 'single' ? 1 : count($kw_in))));
            $perDay = max(1, intval($p['per_day'] ?? 3));

            // ---------- PRIMEIRA PUBLICAÇÃO (DATETIME-LOCAL) ----------
            $first_raw = trim((string)($p['first_delay_hours'] ?? ''));
            $now       = time();

            // baseTs = timestamp base do plano
            //  - se veio data/hora futura → data escolhida
            //  - senão → agora + 2h (fallback antigo)
            $baseTs = $now + 2 * HOUR_IN_SECONDS;

            if ($first_raw !== '') {
                $ts = strtotime($first_raw);
                if ($ts !== false && $ts > $now) {
                    $baseTs = $ts;
                }
            }

            $transition = [
                'strict'    => !empty($p['transition']['strict'] ?? false),
                'min_ratio' => floatval($p['transition']['min_ratio'] ?? 0.3),
                'words'     => is_array($p['transition']['words'] ?? null)
                    ? array_values(array_filter(array_map('trim', $p['transition']['words'])))
                    : [],
            ];

            // monta agenda leve
            $jobs   = [];
            $days   = (int) ceil($total / max(1, $perDay));
            $i      = 0;
            $cat_id = max(0, intval($p['category_id'] ?? 0));

            for ($d = 0; $d < $days; $d++) {
                $slotsToday = min($perDay, $total - count($jobs));
                $base       = [9 * 3600, 14 * 3600, 19 * 3600];

                for ($s = 0; $s < $slotsToday; $s++) {
                    $baseIdx = min($s, count($base) - 1);
                    $offset  = wp_rand(-40 * MINUTE_IN_SECONDS, 40 * MINUTE_IN_SECONDS);

                    // sempre a partir do baseTs, somando dias pra frente
                    $t = strtotime('+' . $d . ' day', $baseTs) + $base[$baseIdx] + $offset;

                    // escolhe LINHA (keyword OU URL) para este job
                    $lineValue = '';
                    if ($mode === 'single') {
                        $lineValue = $kw_in[0] ?? '';
                    } else {
                        $lineValue = $kw_in[$i] ?? '';
                    }

                    if ($lineValue === '') {
                        continue;
                    }

                    $jobs[] = [
                        'keyword'        => $lineValue,
                        'locale'         => $locale,
                        'length'         => $length,
                        'template_key'   => $tpl,
                        'publish_time'   => $t,
                        'transition'     => $transition,
                        'category_id'    => $cat_id,
                        'tags'           => $tags,
                        'faq'            => $faq,
                        'internal_links' => [
                            'mode'       => $link_mode,
                            'max'        => $link_max,
                            'manual_ids' => $link_manual_ids,
                        ],
                    ];

                    $i++;
                    if (count($jobs) >= $total) {
                        break;
                    }
                }
            }

            // multi: no máx. 1 job por linha
            if ($mode === 'multi' && count($kw_in) < $total) {
                $jobs = array_slice($jobs, 0, count($kw_in));
            }

            return [
                'ok'                 => true,
                'mode'               => $mode,
                'total_requested'    => $total,
                'jobs'               => $jobs,
                'available_keywords' => count($kw_in),
            ];
        });
    }

    /**
     * Monta a lista de jobs (agenda) a partir dos parâmetros do planejamento.
     *
     * Cada job já sai pronto para ser enviado para o /generate depois.
     */
    public static function status()
    {
        return ['ok' => true, 'time' => current_time('mysql')];
    }

    // ---------------------- keywords ----------------------
    public static function keywords_get($req)
    {
        $v = self::verify_nonce($req);
        if (is_wp_error($v)) return $v;
        return [
            'ok' => true,
            'pending' => self::kw_get_pending(),
            'done'   => self::kw_get_done(),
        ];
    }

    public static function keywords_save($req)
    {
        $v = self::verify_nonce($req);
        if (is_wp_error($v)) return $v;
        return self::guard(function () use ($req) {
            $p           = $req->get_json_params();
            $pending_txt = (string)($p['pending_text'] ?? '');
            $pending     = self::lines_to_array($pending_txt);
            self::kw_set_pending($pending);
            return [
                'ok' => true,
                'pending' => self::kw_get_pending(),
                'done'   => self::kw_get_done(),
            ];
        });
    }

    public static function keywords_clear($req)
    {
        $v = self::verify_nonce($req);
        if (is_wp_error($v)) return $v;

        return self::guard(function () use ($req) {
            $p   = $req->get_json_params();
            $who = self::clean($p['who'] ?? 'pending');

            if ($who === 'done') {
                self::kw_clear_done();
            } else {
                self::kw_clear_pending();
            }

            return [
                'ok' => true,
                'pending' => self::kw_get_pending(),
                'done'   => self::kw_get_done(),
            ];
        });
    }

    public static function get_text_provider($format = 'orion_posts'): string
    {
        $provider = 'openai';

        if (class_exists('AlphaSuite_Settings')) {
            $opts   = AlphaSuite_Settings::get();
            $bucket = $opts[$format] ?? [];

            if (!empty($bucket['text_provider'])) {
                $candidate = (string)$bucket['text_provider'];
                if (in_array($candidate, ['openai', 'gemini', 'claude', 'mistral', 'cohere', 'perplexity'], true)) {
                    $provider = $candidate;
                }
            }
        }

        return $provider;
    }

    public static function selftest($req)
    {
        if (!current_user_can('manage_options')) {
            return new WP_Error('forbidden', 'Sem permissão.', ['status' => 403]);
        }

        if (!class_exists('AlphaSuite_Settings')) {
            return new WP_Error('config_missing', 'Configurações não encontradas.');
        }

        $opts = AlphaSuite_Settings::get();

        $errors = [];

        /*
    |--------------------------------------------------------------------------
    | TEXTO
    |--------------------------------------------------------------------------
    */

        $textProvider = self::get_text_provider('orion_posts');
        $textKey = trim($opts['apis'][$textProvider]['key'] ?? '');

        if ($textKey === '') {
            $errors[] = strtoupper($textProvider) . ': chave não configurada.';
        }

        /*
    |--------------------------------------------------------------------------
    | IMAGEM (com inherit)
    |--------------------------------------------------------------------------
    */

        $context = $req->get_param('context') ?: 'post';

        $globalImg = is_array($opts['apis']['images'] ?? null)
            ? $opts['apis']['images']
            : [];

        $imgProvider = $globalImg['provider'] ?? 'none';

        if ($context === 'story') {
            $storyProv = $opts['stories']['images_provider'] ?? 'inherit';

            if ($storyProv && $storyProv !== 'inherit') {
                $imgProvider = $storyProv;
            }
        }

        // Se tiver provider definido, exige chave
        if ($imgProvider && $imgProvider !== 'none') {
            $imgKey = trim($opts['apis'][$imgProvider]['key'] ?? '');

            if ($imgKey === '') {
                $errors[] = strtoupper($imgProvider) . ': chave de imagem não configurada.';
            }
        }

        /*
    |--------------------------------------------------------------------------
    | RESULTADO
    |--------------------------------------------------------------------------
    */

        return rest_ensure_response([
            'ok'        => empty($errors),
            'text'      => $textProvider,
            'image'     => $imgProvider,
            'errors'    => $errors,
        ]);
    }
}
