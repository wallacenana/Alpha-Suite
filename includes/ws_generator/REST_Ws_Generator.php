<?php
if (!defined('ABSPATH')) exit;

class AlphaSuite_REST_Ws_Generator
{
    const NS = 'pga/v1';

    const STORY_CPT = AlphaSuite_WS_CPT::POST_TYPE;

    // metas
    const META_PAYLOAD     = '_pga_ws_payload';
    const META_SLIDES      = '_pga_ws_slides';
    const META_THEME       = '_pga_ws_theme';
    const META_AI_RAW      = '_pga_ws_ai_raw';
    const META_SOURCE      = '_pga_ws_source_post';
    const META_TITLE       = '_pga_ws_meta_title';
    const META_DESC        = '_pga_ws_meta_desc';
    const META_SLUG        = '_pga_ws_slug';
    const META_LOGO_ID     = '_pga_ws_publisher_logo_id';
    const META_POSTER_ID   = '_pga_ws_poster_id';
    const META_ACCENT      = '_pga_ws_accent_color';
    const META_TEXT_COLOR  = '_pga_ws_text_color';
    const META_LOCALE      = '_pga_ws_locale';
    const META_PAGES       = '_pga_ws_pages';

    // ---------------------- utils (mesmo estilo do Orion) ----------------------
    private static function verify_nonce($req)
    {
        $n = $req->get_header('X-WP-Nonce');
        if (!$n || !wp_verify_nonce($n, 'wp_rest')) {
            return new WP_Error('rest_forbidden', 'Nonce inválido ou ausente.', ['status' => 403]);
        }
        return true;
    }

    private static function clean($s): string
    {
        return sanitize_text_field((string)$s);
    }

    private static function guard(callable $fn)
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            return new WP_Error('pga_ws_exception', 'Exceção interna.', [
                'status' => 500,
                'msg'    => $e->getMessage(),
            ]);
        }
    }

    private static function get_story_or_error(int $story_id)
    {
        if ($story_id <= 0) {
            return new WP_Error('pga_ws_bad_story', 'story_id inválido.', ['status' => 400]);
        }

        $post = get_post($story_id);
        if (!$post) {
            return new WP_Error('pga_ws_not_found', 'Story não encontrado.', ['status' => 404]);
        }

        // permissão por post
        if (!current_user_can('edit_post', $story_id)) {
            return new WP_Error('pga_ws_forbidden', 'Sem permissão para editar este story.', ['status' => 403]);
        }

        return $post;
    }
    private static function normalize_color($hex, $fallback): string
    {
        $hex = strtoupper(trim((string)$hex));
        if ($hex === '') return $fallback;
        if (!preg_match('/^#[0-9A-F]{6}$/', $hex)) return $fallback;
        return $hex;
    }

    private static function att_url(int $att_id): string
    {
        if ($att_id <= 0) return '';
        $u = wp_get_attachment_image_url($att_id, 'full');
        return $u ? $u : '';
    }

    private static function as_story_payload(\WP_Post $story): array
    {
        $id = (int) $story->ID;

        $meta_title = (string) get_post_meta($id, self::META_TITLE, true);
        $meta_desc  = (string) get_post_meta($id, self::META_DESC, true);

        $logo_id   = (int) get_post_meta($id, self::META_LOGO_ID, true);
        $poster_id = (int) get_post_meta($id, self::META_POSTER_ID, true);

        $accent = (string) get_post_meta($id, self::META_ACCENT, true);
        $textc  = (string) get_post_meta($id, self::META_TEXT_COLOR, true);

        $source = (int) get_post_meta($id, self::META_SOURCE, true);

        $out = [
            'id'          => $id,
            'status'      => (string) $story->post_status,
            'slug' => [
                'post_name' => (string) $story->post_name,
                'custom'    => (string) get_post_meta($id, self::META_SLUG, true),
            ],
            'post_title'  => (string) get_the_title($id),
            'meta_title'  => $meta_title,
            'meta_desc'   => $meta_desc,

            'logo_id'     => $logo_id,
            'logo_url'    => self::att_url($logo_id),

            'poster_id'   => $poster_id,
            'poster_url'  => self::att_url($poster_id),

            'accent_color' => self::normalize_color($accent, '#3B82F6'),
            'text_color'  => self::normalize_color($textc, '#FFFFFF'),

            'source_post' => $source,

            'edit_url'    => get_edit_post_link($id, '') ?: '',
            'view_url'    => get_permalink($id) ?: '',
            'created_at' => (string) $story->post_date,
        ];

        return $out;
    }

    private static function json_params(WP_REST_Request $req): array
    {
        $p = $req->get_json_params();
        if (empty($p)) $p = $req->get_params();
        return is_array($p) ? $p : [];
    }

    private static function require_classes()
    {
        if (!class_exists('AlphaSuite_AI')) {
            return new WP_Error('pga_ws_ai_missing', 'AlphaSuite_AI não encontrado.', ['status' => 500]);
        }
        if (!class_exists('AlphaSuite_Prompts')) {
            return new WP_Error('pga_ws_prompts_missing', 'AlphaSuite_Prompts não encontrado.', ['status' => 500]);
        }
        return true;
    }

    private static function get_payload(int $story_id): array
    {
        $p = get_post_meta($story_id, self::META_PAYLOAD, true);
        return is_array($p) ? $p : [];
    }

    /**
     * Normaliza para sempre retornar o mesmo formato.
     */
    private static function canonical_payload(int $story_id, array $p): array
    {
        $p = is_array($p) ? $p : [];

        // garante sub-blocos
        $p['meta']     = isset($p['meta']) && is_array($p['meta']) ? $p['meta'] : [];
        $p['layout']   = isset($p['layout']) && is_array($p['layout']) ? $p['layout'] : [];
        $p['settings'] = isset($p['settings']) && is_array($p['settings']) ? $p['settings'] : [];
        $p['source']   = isset($p['source']) && is_array($p['source']) ? $p['source'] : [];

        // meta
        $p['meta']['title'] = self::clean($p['meta']['title'] ?? '');
        $p['meta']['desc']  = sanitize_textarea_field((string)($p['meta']['desc'] ?? ''));

        // layout
        $p['layout']['theme'] = self::clean($p['layout']['theme'] ?? (string)get_post_meta($story_id, self::META_THEME, true));
        if ($p['layout']['theme'] === '') $p['layout']['theme'] = 'theme-normal';

        $p['layout']['slidesCount'] = absint($p['layout']['slidesCount'] ?? 0);
        if ($p['layout']['slidesCount'] <= 0) {
            // tenta inferir pelo META_SLIDES
            $pages = get_post_meta($story_id, self::META_SLIDES, true);
            $p['layout']['slidesCount'] = is_array($pages) ? max(1, count($pages)) : 0;
            if ($p['layout']['slidesCount'] <= 0) $p['layout']['slidesCount'] = 6;
        }

        $rawSlides = $p['layout']['slides'] ?? [];
        $p['layout']['slides'] = self::normalize_slides(is_array($rawSlides) ? $rawSlides : [], (int)$p['layout']['slidesCount']);

        // cta_pages sempre derivado de slides (fonte única)
        $cta_pages = [];
        foreach ($p['layout']['slides'] as $s) {
            if (!empty($s['cta_enabled'])) $cta_pages[] = (int)($s['index'] ?? 0);
        }
        $p['layout']['cta_pages'] = array_values(array_filter(array_unique(array_map('absint', $cta_pages))));

        // settings
        $p['settings']['publisher_logo_id'] = absint($p['settings']['publisher_logo_id'] ?? get_post_meta($story_id, self::META_LOGO_ID, true));
        $p['settings']['poster_id']         = absint($p['settings']['poster_id'] ?? get_post_meta($story_id, self::META_POSTER_ID, true));
        $p['settings']['accent_color']      = self::normalize_color($p['settings']['accent_color'] ?? get_post_meta($story_id, self::META_ACCENT, true), '#3B82F6');
        $p['settings']['text_color']        = self::normalize_color($p['settings']['text_color'] ?? get_post_meta($story_id, self::META_TEXT_COLOR, true), '#FFFFFF');
        $p['settings']['locale']            = self::clean($p['settings']['locale'] ?? get_post_meta($story_id, self::META_LOCALE, true));
        if ($p['settings']['locale'] === '') $p['settings']['locale'] = 'pt_BR';

        // source
        $p['source']['post_id']       = absint($p['source']['post_id'] ?? get_post_meta($story_id, self::META_SOURCE, true));
        $p['source']['post_ids']      = isset($p['source']['post_ids']) && is_array($p['source']['post_ids'])
            ? array_values(array_filter(array_map('absint', $p['source']['post_ids'])))
            : [];
        $p['source']['publish_start'] = self::clean($p['source']['publish_start'] ?? '');

        // opcional: mantém mode se existir
        if (isset($p['mode'])) {
            $p['mode'] = ($p['mode'] === 'bulk') ? 'bulk' : 'single';
        }

        return $p;
    }

    // ---------------------- rotas ----------------------
    public static function register_routes()
    {
        register_rest_route(self::NS, '/ws/story', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'story_get'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        register_rest_route('pga/v1', '/ws/generate', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'generate'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        register_rest_route(self::NS, '/ws/story/save', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'story_save'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        register_rest_route(self::NS, '/ws/slide/image/generate', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'slide_image_generate'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        register_rest_route(self::NS, '/ws/slide/image/select', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'slide_image_select'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);
    }

    public static function slide_image_select(\WP_REST_Request $req)
    {
        $v = self::verify_nonce($req);
        if (is_wp_error($v)) return $v;

        return self::guard(function () use ($req) {

            $p = self::json_params($req);

            $story_id = absint($p['story_id'] ?? 0);
            $index    = intval($p['index'] ?? -1);
            $url      = esc_url_raw((string)($p['url'] ?? ''));
            $alt      = sanitize_text_field((string)($p['alt'] ?? ''));

            $story = self::get_story_or_error($story_id);
            if (is_wp_error($story)) return $story;

            if ($index < 0) {
                return new \WP_Error('pga_ws_bad_index', 'index inválido.', ['status' => 400]);
            }
            if ($url === '') {
                return new \WP_Error('pga_ws_no_url', 'URL da imagem não informada.', ['status' => 400]);
            }

            // pages
            $pages = get_post_meta($story_id, '_pga_ws_pages', true);
            if (!is_array($pages) || empty($pages)) {
                $pages = get_post_meta($story_id, self::META_SLIDES, true);
            }
            if (!is_array($pages) || empty($pages)) {
                return new \WP_Error('pga_ws_pages', 'Nenhuma página de story encontrada.', ['status' => 400]);
            }
            if (!isset($pages[$index]) || !is_array($pages[$index])) {
                return new \WP_Error('pga_ws_page_index', 'Página inexistente para este índice.', ['status' => 400]);
            }

            // alt fallback
            if ($alt === '') {
                $h = trim((string)($pages[$index]['heading'] ?? ''));
                $alt = $h !== '' ? $h : 'Web Story';
            }

            // baixa + cria attachment (sem estourar RAM)
            $att_id = self::create_attachment_from_url($url, $story_id, $alt, 'pexels');
            if (is_wp_error($att_id)) return $att_id;

            $att_id  = (int)$att_id;
            $img_url = wp_get_attachment_image_url($att_id, 'full') ?: '';

            // salva no slide
            $pages[$index]['image_id']  = $att_id;
            $pages[$index]['image_url'] = $img_url;
            $pages[$index]['image']     = $img_url;
            update_post_meta($story_id, '_pga_ws_pages', $pages);

            return rest_ensure_response([
                'ok'        => true,
                'index'     => $index,
                'image_id'  => $att_id,
                'image_url' => $img_url,
            ]);
        });
    }
    private static function create_attachment_from_url(string $url, int $post_id, string $alt = '', string $source = 'remote')
    {
        if ($post_id <= 0 || $url === '') return new \WP_Error('pga_attach_bad', 'Parâmetros inválidos.');

        if (!function_exists('download_url')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        if (!function_exists('media_handle_sideload')) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }
        if (!function_exists('wp_read_image_metadata')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $tmp = download_url($url, 60);
        if (is_wp_error($tmp)) return $tmp;

        $name = basename(wp_parse_url($url, PHP_URL_PATH) ?: 'image.jpg');
        if (!$name) $name = 'image.jpg';

        $file_array = [
            'name'     => sanitize_file_name($name),
            'tmp_name' => $tmp,
        ];

        $att_id = media_handle_sideload($file_array, $post_id);

        if (is_wp_error($att_id)) {
            wp_delete_file($tmp);
            return $att_id;
        }

        if ($alt !== '') {
            update_post_meta($att_id, '_wp_attachment_image_alt', $alt);
        }
        update_post_meta($att_id, '_pga_image_source', $source);

        return (int)$att_id;
    }

    public static function slide_image_generate(\WP_REST_Request $req)
    {
        $v = self::verify_nonce($req);
        if (is_wp_error($v)) return $v;

        return self::guard(function () use ($req) {

            $p = self::json_params($req);

            $story_id = absint($p['story_id'] ?? 0);
            $index    = intval($p['index'] ?? -1);
            $brief    = sanitize_textarea_field((string)($p['brief'] ?? ''));
            $force    = !empty($p['force']);

            $tit  = sanitize_text_field($p['title'] ?? '');
            $desc = sanitize_textarea_field($p['desc'] ?? '');


            $story = self::get_story_or_error($story_id);
            if (is_wp_error($story)) return $story;

            if ($index < 0) {
                return new \WP_Error('pga_ws_bad_index', 'index inválido.', ['status' => 400]);
            }

            // pages
            $pages = get_post_meta($story_id, '_pga_ws_pages', true);
            if (!is_array($pages) || empty($pages)) {
                $pages = get_post_meta($story_id, self::META_SLIDES, true);
            }
            if (!is_array($pages) || empty($pages)) {
                return new \WP_Error('pga_ws_pages', 'Nenhuma página de story encontrada.', ['status' => 400]);
            }

            if (!isset($pages[$index]) || !is_array($pages[$index])) {
                return new \WP_Error('pga_ws_page_index', 'Página inexistente para este índice.', ['status' => 400]);
            }

            // se já existe e não é force
            if (!$force && !empty($pages[$index]['image_id'])) {
                $img_id  = absint($pages[$index]['image_id']);
                $img_url = $img_id ? (wp_get_attachment_image_url($img_id, 'full') ?: '') : '';
                return rest_ensure_response([
                    'ok' => true,
                    'skipped' => true,
                    'index' => $index,
                    'image_id' => $img_id,
                    'image_url' => $img_url,
                ]);
            }

            // contexto do slide
            $heading = trim((string)($pages[$index]['heading'] ?? ''));
            $body    = trim((string)($pages[$index]['body'] ?? ''));
            $slidePrompt = trim((string)($pages[$index]['prompt'] ?? ''));

            $ctx = '';
            if ($brief !== '') $ctx = $brief;
            else if ($slidePrompt !== '') $ctx = $slidePrompt;
            else $ctx = trim($heading . ' ' . $body);

            if ($ctx === '') {
                return new \WP_Error('pga_ws_no_context', 'Sem contexto para gerar imagem (brief/prompt/heading/body vazio).', ['status' => 400]);
            }

            // provider vindo do settings
            $provider = 'pollinations';
            if (class_exists('AlphaSuite_Settings')) {
                $opts = AlphaSuite_Settings::get();
                $orionPosts = $opts['orion_posts'] ?? [];
                if (!empty($orionPosts['images_provider'])) {
                    $provider = (string)$orionPosts['images_provider'];
                }
            }
            $provider = strtolower(trim($provider));

            // Se for banco, retornamos 3 opções (sem salvar nada)
            if ($provider === 'pexels') {

                if (!class_exists('AlphaSuite_Settings')) {
                    return new \WP_Error('pga_pexels_no_cfg', 'Configurações não encontradas.', ['status' => 500]);
                }

                $opts = AlphaSuite_Settings::get();
                $api  = $opts['apis']['pexels'] ?? [];
                $key  = trim((string)($api['key'] ?? ''));

                if ($key === '') {
                    return new \WP_Error('pga_pexels_no_key', 'Chave Pexels não configurada.', ['status' => 400]);
                }

                // ✅ NOVO: se veio pick_url (ou pick_id) => baixa + salva no WP + grava no slide
                $pick_url = trim((string)($p['pick_url'] ?? ''));
                $auto     = !empty($p['auto']); // opcional p/ bulk: escolher o 1º automaticamente

                // se não veio pick_url e auto=1, vamos buscar opções e escolher a primeira
                if ($pick_url === '') {

                    // query curta p/ banco
                    $query = $ctx;

                    if (class_exists('AlphaSuite_Prompts') && method_exists('AlphaSuite_Prompts', 'build_image_prompt')) {

                        if ($tit || $desc)
                            $meta = AlphaSuite_Prompts::build_ws_slide_image_prompt($tit, $desc, $provider);
                        else
                            $meta = AlphaSuite_Prompts::build_image_prompt('ws_generator', '', '', 'en', 'pexels');

                        $schema = ['content' => 'string'];
                        if (class_exists('AlphaSuite_AI')) {
                            $ai = AlphaSuite_AI::complete(
                                $meta,
                                $schema,
                                [
                                    'format'            => 'stories'
                                ],
                            );

                            if (!is_wp_error($ai)) {

                                $q = trim((string)($ai['content'] ?? ''));
                                if ($q !== '') $query = $q;
                            }
                        }
                    }

                    $endpoint = add_query_arg(
                        [
                            'query'       => $query,
                            'per_page'    => 3,
                            'page'        => 1,
                            'orientation' => 'portrait',
                        ],
                        'https://api.pexels.com/v1/search'
                    );

                    $res = wp_remote_get($endpoint, [
                        'timeout' => 30,
                        'headers' => ['Authorization' => $key],
                    ]);

                    if (is_wp_error($res)) return $res;

                    $code = wp_remote_retrieve_response_code($res);
                    $bodyJson = wp_remote_retrieve_body($res);

                    if ($code < 200 || $code >= 300 || !$bodyJson) {
                        return new \WP_Error('pga_pexels_http', "Erro HTTP {$code} no Pexels.", ['status' => 500]);
                    }

                    $json = json_decode($bodyJson, true);
                    $photos = $json['photos'] ?? [];

                    if (!is_array($photos) || empty($photos)) {
                        return new \WP_Error('pga_pexels_empty', 'Nenhuma imagem encontrada no Pexels.', ['status' => 404]);
                    }

                    // usados por story (evita repetir imagem no mesmo story)
                    $used = get_post_meta($story_id, '_pga_ws_used_pexels', true);
                    if (!is_array($used)) $used = ['ids' => [], 'urls' => []];

                    $used_ids  = array_map('intval', (array)($used['ids'] ?? []));
                    $used_urls = array_map('strval', (array)($used['urls'] ?? []));

                    $options = [];

                    foreach (array_slice(array_values($photos), 0, 10) as $ph) { // pega mais que 3 pra ter margem
                        $pid = isset($ph['id']) ? (int)$ph['id'] : 0;
                        $src = $ph['src'] ?? [];

                        $thumb = $src['medium'] ?? $src['small'] ?? $src['tiny'] ?? ($src['portrait'] ?? ($src['large'] ?? ''));
                        $full  = $src['portrait'] ?? $src['large2x'] ?? $src['large'] ?? $src['original'] ?? '';

                        if (!$pid || !$full) continue;

                        // pula se já foi usada
                        if (in_array($pid, $used_ids, true)) continue;
                        if (in_array((string)$full, $used_urls, true)) continue;

                        $options[] = ['id' => $pid, 'thumb' => (string)$thumb, 'full' => (string)$full];
                        if (count($options) >= 3) break;
                    }


                    if (empty($options)) {
                        return new \WP_Error('pga_pexels_no_options', 'Não foi possível montar opções de imagem.', ['status' => 500]);
                    }

                    update_post_meta($story_id, '_pga_ws_last_image_options_' . $index, $options);

                    // ✅ auto=1: escolhe a primeira opção NÃO usada
                    if ($auto) {

                        $pick = null;

                        foreach ($options as $op) {
                            $pid  = (int)($op['id'] ?? 0);
                            $full = (string)($op['full'] ?? '');

                            if (!$pid || $full === '') continue;

                            if (in_array($pid, $used_ids, true)) continue;
                            if (in_array($full, $used_urls, true)) continue;

                            $pick = $op;
                            break;
                        }

                        // se todas já foram usadas, cai no fallback: pega a primeira mesmo (ou você pode tentar page=2)
                        if (!$pick) {
                            $pick = $options[0] ?? null;
                        }

                        $pick_url = (string)($pick['full'] ?? '');
                        if ($pick_url === '') {
                            return new \WP_Error('pga_pexels_no_pick', 'Não foi possível escolher imagem automaticamente.', ['status' => 500]);
                        }

                        // guarda pra marcar como usado depois do sideload
                        $picked_pid = (int)($pick['id'] ?? 0);
                    } else {
                        // modo normal: devolve opções para o JS escolher
                        return rest_ensure_response([
                            'ok'       => true,
                            'mode'     => 'pick',
                            'index'    => $index,
                            'provider' => 'pexels',
                            'options'  => $options,
                            'query'    => $query,
                        ]);
                    }
                }

                // ✅ DAQUI PRA BAIXO: download + sideload + grava no slide
                require_once ABSPATH . 'wp-admin/includes/file.php';
                require_once ABSPATH . 'wp-admin/includes/media.php';
                require_once ABSPATH . 'wp-admin/includes/image.php';

                $tmp = download_url($pick_url, 30);
                if (is_wp_error($tmp)) {
                    return new \WP_Error('pga_pexels_download', 'Falha ao baixar imagem do Pexels.', ['status' => 500]);
                }

                $filename = basename(wp_parse_url($pick_url, PHP_URL_PATH) ?: 'pexels.jpg');
                if (!preg_match('/\.(jpg|jpeg|png|webp)$/i', $filename)) {
                    $filename .= '.jpg';
                }

                $file_array = [
                    'name'     => sanitize_file_name($filename),
                    'tmp_name' => $tmp,
                ];

                $alt = $heading !== '' ? $heading : $ctx;

                $att_id = media_handle_sideload($file_array, $story_id, $alt);
                if (is_wp_error($att_id)) {
                    wp_delete_file($tmp);
                    return $att_id;
                }

                // marca como usada (por story) — evita repetir nos próximos slides
                if (!empty($picked_pid)) $used_ids[] = (int)$picked_pid;
                if (!empty($pick_url))   $used_urls[] = (string)$pick_url;

                $used_ids  = array_values(array_unique(array_map('intval', $used_ids)));
                $used_urls = array_values(array_unique(array_map('strval', $used_urls)));

                update_post_meta($story_id, '_pga_ws_used_pexels', [
                    'ids'  => $used_ids,
                    'urls' => $used_urls,
                ]);

                $att_id = (int)$att_id;
                $img_url = wp_get_attachment_image_url($att_id, 'full') ?: '';

                $pages[$index]['image_id']  = $att_id;
                $pages[$index]['image_url'] = $img_url;
                $pages[$index]['image']     = $img_url;

                update_post_meta($story_id, '_pga_ws_pages', $pages);

                return rest_ensure_response([
                    'ok'        => true,
                    'mode'      => 'direct',
                    'provider'  => 'pexels',
                    'index'     => $index,
                    'image_id'  => $att_id,
                    'image_url' => $img_url,
                    'pick_url'  => $pick_url,
                ]);
            }

            if (!class_exists('AlphaSuite_Images')) {
                return new \WP_Error('pga_ws_images_missing', 'AlphaSuite_Images ausente.', ['status' => 500]);
            }

            $alt = $heading !== '' ? $heading : $ctx;

            $att_id = AlphaSuite_Images::generate_story_by_settings($ctx, $story_id, $alt);
            if (is_wp_error($att_id)) return $att_id;

            $att_id = (int)$att_id;
            $img_url = wp_get_attachment_image_url($att_id, 'full') ?: '';

            $pages[$index]['image_id']  = $att_id;
            $pages[$index]['image_url'] = $img_url;
            $pages[$index]['image']     = $img_url;
            update_post_meta($story_id, '_pga_ws_pages', $pages);

            return rest_ensure_response([
                'ok'        => true,
                'mode'      => 'direct',
                'index'     => $index,
                'image_id'  => $att_id,
                'image_url' => $img_url,
            ]);
        });
    }

    public static function story_save(WP_REST_Request $req)
    {
        $v = self::verify_nonce($req);
        if (is_wp_error($v)) return $v;

        return self::guard(function () use ($req) {
            $p = self::json_params($req);

            $story_id = absint($p['story_id'] ?? 0);

            // ---------------------------
            // STATUS (normaliza)
            // ---------------------------
            $status = sanitize_key((string)($p['status'] ?? ''));
            if (!in_array($status, ['draft', 'publish', 'future', 'trash'], true)) {
                $status = 'draft';
            }

            $want_publish = ($status === 'publish');
            $want_future  = ($status === 'future');

            // ---------------------------
            // PUBLISH_AT (somente future)
            // ---------------------------
            $publish_at_raw = (string)($p['publish_at'] ?? '');
            $publish_at = '';

            if ($want_future) {
                $publish_at = sanitize_text_field($publish_at_raw);

                if ($publish_at === '') {
                    return new WP_Error('pga_ws_future_date', 'Defina a data do agendamento.', ['status' => 400]);
                }

                $ts = strtotime($publish_at);
                if (!$ts) {
                    return new WP_Error('pga_ws_future_date_invalid', 'Data de agendamento inválida.', ['status' => 400]);
                }
            }

            // ---------------------------
            // PAYLOAD
            // ---------------------------
            $meta     = is_array($p['meta'] ?? null) ? $p['meta'] : [];
            $layout   = is_array($p['layout'] ?? null) ? $p['layout'] : [];
            $settings = is_array($p['settings'] ?? null) ? $p['settings'] : [];
            $pages    = is_array($p['pages'] ?? null) ? $p['pages'] : [];

            if (empty($pages)) {
                return new WP_Error('pga_ws_no_pages', 'Sem slides para salvar.', ['status' => 400]);
            }

            $meta_title = self::clean($meta['title'] ?? '');
            $meta_desc  = sanitize_textarea_field((string)($meta['desc'] ?? ''));

            $poster_id_req = absint($settings['poster_id'] ?? 0);

            // publish ou future exigem meta + thumb
            if ($want_publish || $want_future) {
                if ($meta_title === '') {
                    return new WP_Error('pga_ws_pub_title', 'Título é obrigatório.', ['status' => 400]);
                }
                if ($meta_desc === '') {
                    return new WP_Error('pga_ws_pub_desc', 'Meta descrição é obrigatória.', ['status' => 400]);
                }
                if ($poster_id_req <= 0) {
                    return new WP_Error('pga_ws_pub_thumb', 'Thumbnail é obrigatória.', ['status' => 400]);
                }
            }

            // ---------------------------
            // TRASH
            // ---------------------------
            if ($status === 'trash') {
                if ($story_id <= 0) {
                    return new WP_Error('pga_ws_no_story', 'Sem story_id para excluir.', ['status' => 400]);
                }
                $story = self::get_story_or_error($story_id);
                if (is_wp_error($story)) return $story;

                wp_trash_post($story_id);

                return rest_ensure_response([
                    'ok'       => true,
                    'story_id' => $story_id,
                    'status'   => 'trash',
                ]);
            }

            // ---------------------------
            // CREATE / UPDATE POST
            // ---------------------------
            $now_local = current_time('mysql');

            if ($story_id > 0) {
                $story = self::get_story_or_error($story_id);
                if (is_wp_error($story)) return $story;

                $upd = ['ID' => $story_id];

                if ($meta_title !== '') {
                    $upd['post_title'] = $meta_title;
                }

                if ($want_publish) {
                    // evita WP “voltar” pra future por post_date futuro
                    $upd['post_status']   = 'publish';
                    $upd['post_date']     = $now_local;
                    $upd['post_date_gmt'] = get_gmt_from_date($now_local);
                } elseif ($want_future) {
                    $upd['post_status']   = 'future';
                    $upd['post_date']     = $publish_at;
                    $upd['post_date_gmt'] = get_gmt_from_date($publish_at);
                } else {
                    $upd['post_status'] = 'draft';
                }

                $r = wp_update_post($upd, true);
                if (is_wp_error($r)) {
                    return new WP_Error('pga_ws_update_fail', $r->get_error_message(), ['status' => 500]);
                }
            } else {
                $title = $meta_title !== '' ? $meta_title : 'Web Story';

                $ins_arr = [
                    'post_type'    => self::STORY_CPT,
                    'post_title'   => $title,
                    'post_content' => '',
                    'post_status'  => $want_publish ? 'publish' : ($want_future ? 'future' : 'draft'),
                ];

                if ($want_publish) {
                    $ins_arr['post_date']     = $now_local;
                    $ins_arr['post_date_gmt'] = get_gmt_from_date($now_local);
                } elseif ($want_future) {
                    $ins_arr['post_date']     = $publish_at;
                    $ins_arr['post_date_gmt'] = get_gmt_from_date($publish_at);
                }

                $ins = wp_insert_post($ins_arr, true);
                if (is_wp_error($ins) || !$ins) {
                    return new WP_Error('pga_ws_create_fail', 'Falha ao criar story.', ['status' => 500]);
                }

                $story_id = (int)$ins; // ✅ AGORA temos ID real
            }

            // ---------------------------
            // SLUG (aplica no post_name DEPOIS do ID existir)
            // ---------------------------
            $slug_in = isset($p['slug']) ? sanitize_title((string)$p['slug']) : '';
            $slug_desired = $slug_in !== '' ? $slug_in : ('ws-' . $story_id); // ✅ nunca mais ws-0

            $slug_unique = wp_unique_post_slug(
                $slug_desired,
                $story_id,
                $want_publish ? 'publish' : ($want_future ? 'future' : 'draft'),
                self::STORY_CPT,
                0
            );

            // aplica no WP de verdade (slug real)
            wp_update_post([
                'ID' => $story_id,
                'post_name' => $slug_unique,
            ]);

            // (se você quiser guardar “o que ficou” também em meta, ok)
            update_post_meta($story_id, self::META_SLUG, $slug_unique);

            // ---------------------------
            // NORMALIZA/SALVA PAGES
            // ---------------------------
            $out_pages = [];
            foreach ($pages as $pg) {
                if (!is_array($pg)) continue;
                $out_pages[] = [
                    'index'     => absint($pg['index'] ?? 0),
                    'heading'   => sanitize_text_field((string)($pg['heading'] ?? '')),
                    'body'      => sanitize_textarea_field((string)($pg['body'] ?? '')),
                    'cta_text'  => sanitize_text_field((string)($pg['cta_text'] ?? '')),
                    'cta_url'   => esc_url_raw((string)($pg['cta_url'] ?? '')),
                    'template'  => sanitize_key((string)($pg['template'] ?? 'template-1')),
                    'image_id'  => absint($pg['image_id'] ?? 0),
                    'image_url' => esc_url_raw((string)($pg['image_url'] ?? '')),
                ];
            }

            $theme         = self::clean($layout['theme'] ?? 'theme-normal');
            $slidesCount   = absint($layout['slidesCount'] ?? count($out_pages));
            $layout_slides = is_array($layout['slides'] ?? null) ? $layout['slides'] : [];

            // ---------------------------
            // METAS
            // ---------------------------
            update_post_meta($story_id, self::META_TITLE, $meta_title);
            update_post_meta($story_id, self::META_DESC,  $meta_desc);
            update_post_meta($story_id, self::META_THEME, $theme);
            update_post_meta($story_id, self::META_SLIDES, $out_pages);

            $logo_id   = absint($settings['publisher_logo_id'] ?? 0);
            $poster_id = absint($settings['poster_id'] ?? 0);

            update_post_meta($story_id, self::META_LOGO_ID, $logo_id);
            update_post_meta($story_id, self::META_POSTER_ID, $poster_id);

            if (($want_publish || $want_future) && $poster_id > 0) {
                set_post_thumbnail($story_id, $poster_id);
            }

            update_post_meta($story_id, self::META_ACCENT, self::normalize_color($settings['accent_color'] ?? '', '#3B82F6'));
            update_post_meta($story_id, self::META_TEXT_COLOR, self::normalize_color($settings['text_color'] ?? '', '#FFFFFF'));
            update_post_meta($story_id, self::META_LOCALE, self::clean($settings['locale'] ?? 'pt_BR'));

            update_post_meta($story_id, self::META_PAYLOAD, [
                'meta' => ['title' => $meta_title, 'desc' => $meta_desc],
                'layout' => [
                    'theme' => $theme,
                    'slidesCount' => $slidesCount,
                    'slides' => $layout_slides,
                ],
                'settings' => [
                    'publisher_logo_id' => $logo_id,
                    'poster_id' => $poster_id,
                    'accent_color' => self::normalize_color($settings['accent_color'] ?? '', '#3B82F6'),
                    'text_color' => self::normalize_color($settings['text_color'] ?? '', '#FFFFFF'),
                    'locale' => self::clean($settings['locale'] ?? 'pt_BR'),
                ],
                'publish_at' => $want_future ? $publish_at : '',
                'status' => $status,
                'slug' => $slug_unique,
            ]);

            $story = get_post($story_id);

            return rest_ensure_response([
                'ok'       => true,
                'story_id' => $story_id,
                'status'   => $story ? $story->post_status : '',
                'story'    => $story ? self::as_story_payload($story) : [],
            ]);
        });
    }

    // ---------------------- GET story (opcional) ----------------------
    public static function story_get(WP_REST_Request $req)
    {
        $v = self::verify_nonce($req);
        if (is_wp_error($v)) return $v;

        return self::guard(function () use ($req) {
            $story_id = absint($req->get_param('story_id'));
            $story = self::get_story_or_error($story_id);
            if (is_wp_error($story)) return $story;

            $payload = self::get_payload($story_id);
            $payload = self::canonical_payload($story_id, $payload);

            $pages = get_post_meta($story_id, self::META_SLIDES, true);
            if (!is_array($pages)) $pages = [];

            return rest_ensure_response([
                'ok'    => true,
                'story' => self::as_story_payload($story),

                // 🔥 fonte única pro front
                'payload'  => $payload,

                // conveniência (mantém compat com seu JS atual)
                'meta'     => $payload['meta'],
                'layout'   => $payload['layout'],
                'settings' => $payload['settings'],
                'source'   => $payload['source'],

                'pages'    => $pages,
            ]);
        });
    }

    // ---------------------- POST generate (principal) ----------------------
    public static function generate(WP_REST_Request $req)
    {
        $v = self::verify_nonce($req);
        if (is_wp_error($v)) return $v;

        $dep = self::require_classes();
        if (is_wp_error($dep)) return $dep;

        return self::guard(function () use ($req) {

            $p = self::json_params($req);

            $nonce = '';
            if (method_exists($req, 'get_header')) {
                $nonce = (string) $req->get_header('X-WP-Nonce');
            }
            if (!$nonce) {
                $nonce = (string) ($p['_wpnonce'] ?? '');
            }

            // mode: single | bulk (igual teu JS)
            $mode = (isset($p['mode']) && $p['mode'] === 'bulk') ? 'bulk' : 'single';

            // ids (do JS)
            $post_id  = absint($p['post_id'] ?? 0);
            $post_ids = is_array($p['post_ids'] ?? null)
                ? array_values(array_filter(array_map('absint', $p['post_ids'])))
                : [];

            // layout (vem do JS)
            $layout = is_array($p['layout'] ?? null) ? $p['layout'] : [];
            $theme  = self::clean($layout['theme'] ?? 'theme-normal');

            $slidesCount = isset($layout['slidesCount']) ? absint($layout['slidesCount']) : 6;
            $slidesCount = max(1, (int)$slidesCount);

            $rawSlides = $layout['slides'] ?? [];
            $slides = self::normalize_slides(is_array($rawSlides) ? $rawSlides : [], $slidesCount);

            // CTA pages (índices com cta_enabled=true)
            $cta_pages = [];
            foreach ($slides as $s) {
                if (!empty($s['cta_enabled'])) {
                    $cta_pages[] = (int)($s['index'] ?? 0);
                }
            }
            $cta_pages = array_values(array_filter(array_unique(array_map('absint', $cta_pages))));

            // meta (title/desc) - vem do modal
            $meta = is_array($p['meta'] ?? null) ? $p['meta'] : [];
            $meta_title = self::clean($meta['title'] ?? '');
            $meta_desc  = sanitize_textarea_field((string)($meta['desc'] ?? ''));

            // settings (vem do modal)
            $settings          = is_array($p['settings'] ?? null) ? $p['settings'] : [];
            $publisher_logo_id = absint($settings['publisher_logo_id'] ?? 0);
            $poster_id         = absint($settings['poster_id'] ?? 0);
            $accent_color      = self::clean($settings['accent_color'] ?? '');
            $text_color        = self::clean($settings['text_color'] ?? '');
            $locale            = $p['locale'] ?? 'pt_BR';

            $gen_images = !empty($p['gen_images']) || !empty($p['genImage']);

            // publish_start (bulk)
            $publish_start = self::clean($p['publish_start'] ?? '');

            // validações
            if ($mode === 'single') {
                if (!$post_id) {
                    return new WP_Error('pga_ws_missing_post', 'post_id obrigatório (single).', ['status' => 400]);
                }
                if (!get_post($post_id)) {
                    return new WP_Error('pga_ws_bad_post', 'post_id inválido.', ['status' => 400]);
                }
                $post_ids = [$post_id];
            } else {
                if (empty($post_ids)) {
                    return new WP_Error('pga_ws_missing_posts', 'post_ids obrigatório (bulk).', ['status' => 400]);
                }
            }

            // payload bruto salvo no story (reabrir e depurar)
            $payload = [
                'mode' => $mode,
                'meta' => ['title' => $meta_title, 'desc' => $meta_desc],
                'layout' => [
                    'theme'       => $theme,
                    'slidesCount' => $slidesCount,
                    'slides'      => $slides,
                    'cta_pages'   => $cta_pages,
                ],
                'settings' => [
                    'publisher_logo_id' => $publisher_logo_id,
                    'poster_id'         => $poster_id,
                    'accent_color'      => $accent_color,
                    'text_color'        => $text_color,
                    'locale'            => $locale,
                    'gen_images'        => $gen_images ? 1 : 0,
                ],
                'source' => [
                    'post_id'       => $post_id,
                    'post_ids'      => $post_ids,
                    'publish_start' => $publish_start,
                ],
            ];

            // agenda simples (você pode evoluir depois)
            $schedule = self::build_schedule($mode, $post_ids, $publish_start);

            $story_ids = [];

            foreach ($post_ids as $idx => $pid) {

                $src = get_post($pid);
                if (!$src) continue;

                // conteúdo base (texto limpo)
                $post_title = get_the_title($pid) ?: '';
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
                $raw_html   = apply_filters('the_content', $src->post_content);

                $content_txt = wp_strip_all_tags($raw_html);
                $content_txt = html_entity_decode($content_txt, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $content_txt = trim(preg_replace('/\s+/u', ' ', $content_txt));

                if ($content_txt === '') {
                    $content_txt = $post_title !== '' ? $post_title : '(conteúdo vazio)';
                }

                // 1) 1 prompt só (meta + pages)
                $prompt = AlphaSuite_Prompts::build_ws_story_prompt([
                    'slidesCount'      => $slidesCount,
                    'locale'           => $locale,
                    'title'            => $post_title,
                    'content'          => $content_txt,
                    'cta_pages'        => $cta_pages,
                    'cta_text_default' => 'Saiba mais',
                    'cta_url_default'  => get_permalink($pid) ?: '',
                ]);

                $schema = [
                    'title' => 'string',
                    'desc'  => 'string',
                    'slug'  => 'string',
                    'pages' => 'array',
                ];

                $ai_raw = AlphaSuite_AI::complete(
                    $prompt,
                    $schema,
                    [
                        'temperature' => 0.15,
                        'top_p'       => 0.7,
                        'max_tokens'  => 1800,
                    ]
                );

                if (is_wp_error($ai_raw)) {
                    return new WP_Error(
                        'pga_ws_ai_fail',
                        $ai_raw->get_error_message(),
                        ['status' => 500]
                    );
                }

                if (!is_array($ai_raw)) {
                    return new WP_Error('pga_ws_badjson', 'Resposta inválida da IA.', ['status' => 500]);
                }

                // 1) caso já seja o objeto final
                if (isset($ai_raw['title']) || isset($ai_raw['pages'])) {
                    $obj = $ai_raw;

                    // 2) caso venha embrulhado em content
                } elseif (isset($ai_raw['content'])) {
                    if (is_array($ai_raw['content'])) {
                        $obj = $ai_raw['content'];
                    } else {
                        $obj = json_decode((string)$ai_raw['content'], true);
                    }
                } else {
                    return new WP_Error('pga_ws_badjson', 'Resposta inválida da IA.', ['status' => 500]);
                }

                // 3) 🔥 DESEMBRULHA SE AINDA VEIO content DENTRO
                if (is_array($obj) && isset($obj['content']) && is_array($obj['content'])) {
                    $obj = $obj['content'];
                }

                if (!is_array($obj)) {
                    return new WP_Error('pga_ws_badjson', 'JSON inválido (story).', ['status' => 500]);
                }

                // meta
                $meta_title_story = self::clean($obj['title'] ?? '');
                $meta_desc_story  = sanitize_textarea_field((string)($obj['desc'] ?? ''));
                $slug_story       = sanitize_title((string)($obj['slug'] ?? ''));

                // fallbacks
                if ($meta_title_story === '') $meta_title_story = ($post_title ?: 'Web Story');
                if ($meta_desc_story === '')  $meta_desc_story  = wp_trim_words($content_txt, 26, '…');
                if ($slug_story === '')       $slug_story = sanitize_title($meta_title_story);

                // slug único no CPT
                $exists = get_page_by_path($slug_story, OBJECT, self::STORY_CPT);
                if ($exists) $slug_story .= '-' . wp_date('ymd-His');

                // pages (garante formato)
                $ai = [
                    'pages' => is_array($obj['pages'] ?? null) ? $obj['pages'] : [],
                ];

                // payload por story
                $payload_story = $payload;
                $payload_story['meta'] = [
                    'title' => $meta_title_story,
                    'desc'  => $meta_desc_story,
                    'slug'  => $slug_story,
                ];
                $payload_story['source']['post_id'] = $pid;

                // aplica templates definidos no front (slides[])
                $tpl_map = [];
                foreach ($slides as $s) {
                    $i = isset($s['index']) ? absint($s['index']) : 0;
                    $t = isset($s['template']) ? self::clean($s['template']) : 'template-1';
                    if (!$t) $t = 'template-1';
                    $tpl_map[$i] = $t;
                }
                for ($pi = 0; $pi < count($ai['pages']); $pi++) {
                    $ai['pages'][$pi]['template'] = $tpl_map[$pi] ?? 'template-1';
                }

                // 4) cria story (draft/future)
                $when = isset($schedule[$idx]) ? (int)$schedule[$idx] : 0;
                $story_id = self::create_story_post($pid, $ai, $payload_story, $when);
                if (is_wp_error($story_id)) return $story_id;

                $story_id = (int)$story_id;

                $thumb_id  = (int) get_post_thumbnail_id($pid);

                $effective_poster_id = $poster_id;
                if ($effective_poster_id <= 0 && $thumb_id > 0) {
                    $effective_poster_id = $thumb_id;
                }

                // payload canônico
                $payload_story = self::canonical_payload($story_id, $payload_story);

                // salva payload completo
                update_post_meta($story_id, self::META_PAYLOAD, $payload_story);

                // salva metas “espelhadas” (opc, mas ajuda)
                update_post_meta($story_id, self::META_THEME, $payload_story['layout']['theme']);
                update_post_meta($story_id, self::META_TITLE, $payload_story['meta']['title']);
                update_post_meta($story_id, self::META_DESC,  $payload_story['meta']['desc']);
                update_post_meta($story_id, self::META_SLUG,  $payload_story['meta']['slug']);
                update_post_meta($story_id, self::META_LOGO_ID, $payload_story['settings']['publisher_logo_id']);
                update_post_meta($story_id, self::META_ACCENT, $payload_story['settings']['accent_color']);
                update_post_meta($story_id, self::META_TEXT_COLOR, $payload_story['settings']['text_color']);
                update_post_meta($story_id, self::META_LOCALE, $payload_story['settings']['locale']);
                update_post_meta($story_id, self::META_SOURCE, $payload_story['source']['post_id']);
                update_post_meta($story_id, self::META_PAYLOAD, $payload_story);

                // páginas (conteúdo)
                update_post_meta($story_id, self::META_SLIDES, $ai['pages']);
                update_post_meta($story_id, '_pga_ws_pages', $ai['pages']);
                // espelha pra onde o slide_image_generate prioriza ler

                if ($gen_images) {
                    for ($i = 0; $i < count($ai['pages']); $i++) {
                        $heading = trim((string)($ai['pages'][$i]['heading'] ?? ''));
                        $body    = trim((string)($ai['pages'][$i]['body'] ?? ''));
                        $brief   = trim($heading . ' ' . $body);

                        $bodyReq = [
                            'story_id' => $story_id,
                            'index'    => $i,
                            'brief'    => $brief,
                            'force'    => 0,
                            'auto'     => 1,
                            'title'    => $heading,
                            'desc'     => $body
                        ];

                        $req2 = new WP_REST_Request('POST', '/');
                        $req2->set_header('Content-Type', 'application/json; charset=UTF-8');
                        if ($nonce) $req2->set_header('X-WP-Nonce', $nonce);
                        $req2->set_body(wp_json_encode($bodyReq));

                        $r = self::slide_image_generate($req2);
                    }

                    // recarrega pages atualizadas e espelha nos 2 metas
                    $pages_new = get_post_meta($story_id, '_pga_ws_pages', true);
                    if (is_array($pages_new) && !empty($pages_new)) {
                        update_post_meta($story_id, self::META_SLIDES, $pages_new);
                    }
                }

                $story_ids[] = $story_id;
            }

            if (empty($story_ids)) {
                return new WP_Error('pga_ws_no_story', 'Nenhum story foi criado.', ['status' => 500]);
            }

            return rest_ensure_response([
                'ok'        => true,
                'mode'      => $mode,
                'story_id'  => $story_ids[0],
                'story_ids' => $story_ids,
            ]);
        });
    }

    private static function normalize_slides(array $slides, int $slidesCount): array
    {
        $slidesCount = max(1, (int)$slidesCount);

        $out = [];

        foreach ($slides as $s) {
            if (!is_array($s)) continue;

            $idx = isset($s['index']) ? absint($s['index']) : 0;
            if ($idx < 1 || $idx > $slidesCount) continue;

            $tpl = isset($s['template']) ? sanitize_key((string)$s['template']) : 'template-1';
            if (!in_array($tpl, ['template-1', 'template-2', 'template-3'], true)) {
                $tpl = 'template-1';
            }

            $cta = $s['cta_enabled'] ?? false;
            $cta_enabled = filter_var($cta, FILTER_VALIDATE_BOOLEAN);

            $out[$idx] = [
                'index'       => $idx,
                'template'    => $tpl,
                'cta_enabled' => $cta_enabled,
            ];
        }

        // completa faltantes (1..N)
        for ($i = 1; $i <= $slidesCount; $i++) {
            if (!isset($out[$i])) {
                $out[$i] = [
                    'index'       => $i,
                    'template'    => 'template-1',
                    'cta_enabled' => false,
                ];
            }
        }

        ksort($out);
        return array_values($out);
    }

    private static function build_schedule(string $mode, array $post_ids, string $publish_start): array
    {
        $n = count($post_ids);
        if ($n <= 0) return [];

        // base: se não vier data, usa hoje (local WP)
        $base_ts = $publish_start !== '' ? strtotime($publish_start) : false;
        if (!$base_ts) $base_ts = current_time('timestamp');

        // dia local (zera pra data)
        $day = wp_date('Y-m-d', $base_ts);

        // janela 06:00–23:00 (local)
        $min = strtotime($day . ' 06:00:00');
        $max = strtotime($day . ' 23:00:00');
        if (!$min || !$max || $max <= $min) {
            // fallback: agora + 10min
            $now = current_time('timestamp');
            return array_map(fn($i) => $now + (10 * MINUTE_IN_SECONDS) + ($i * 20 * MINUTE_IN_SECONDS), range(0, $n - 1));
        }

        // cria horários humanizados com espaçamento
        $out = [];
        $last = 0;

        for ($i = 0; $i < $n; $i++) {
            $t = wp_rand($min, $max);

            // espaçamento mínimo (12–35 min) pra não ficar robótico
            if ($last > 0 && $t <= $last) {
                $t = $last + wp_rand(12 * MINUTE_IN_SECONDS, 35 * MINUTE_IN_SECONDS);
            }

            // se estourou a janela, puxa pro fim com jitter
            if ($t > $max) {
                $t = $max - wp_rand(0, 10 * MINUTE_IN_SECONDS);
            }

            $out[] = $t;
            $last = $t;
        }

        sort($out);
        return $out;
    }

    private static function create_story_post(int $source_post_id, array $ai, array $payload, int $whenTs = 0)
    {
        $title = trim((string)($payload['meta']['title'] ?? ''));
        if ($title === '') $title = get_the_title($source_post_id);
        if ($title === '') $title = 'Web Story';

        $slug = sanitize_title((string)($payload['meta']['slug'] ?? ''));
        $excerpt = sanitize_textarea_field((string)($payload['meta']['desc'] ?? ''));

        $status = 'draft';
        $post_date = current_time('mysql');
        $post_date_gmt = current_time('mysql', 1);

        // se agendado, cria como future
        // se veio horário, cria como future
        if ($whenTs > 0) {
            $status = 'future';
            $post_date = wp_date('Y-m-d H:i:s', $whenTs);
            $post_date_gmt = gmdate('Y-m-d H:i:s', $whenTs);
        }

        $story_id = wp_insert_post([
            'post_type'      => self::STORY_CPT,
            'post_status'    => $status,
            'post_title'     => $title,
            'post_name'      => $slug,
            'post_excerpt'   => $excerpt,
            'post_content'   => '', // o render pega das metas/slides
            'post_date'      => $post_date,
            'post_date_gmt'  => $post_date_gmt,
        ], true);

        if (is_wp_error($story_id) || !$story_id) {
            return new WP_Error('pga_ws_create_fail', 'Falha ao criar story.', ['status' => 500]);
        }

        // salva meta: payload + slides + raw
        update_post_meta($story_id, self::META_SOURCE, $source_post_id);
        update_post_meta($story_id, self::META_PAYLOAD, $payload);
        update_post_meta($story_id, self::META_THEME, $payload['layout']['theme'] ?? 'theme-normal');
        update_post_meta($story_id, self::META_SLIDES, $ai['pages']);
        update_post_meta($story_id, self::META_AI_RAW, $ai);

        return (int)$story_id;
    }
}
