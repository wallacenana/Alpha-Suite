<?php

if (!defined('ABSPATH')) exit;

if (!function_exists('alpha_suite_generate_image')) {
    function alpha_suite_generate_image(array $args)
    {
        $target = strtolower(trim((string)($args['target'] ?? 'orion')));
        $post_id = absint($args['post_id'] ?? 0);
        $story_id = absint($args['story_id'] ?? 0);
        $ref_id = $story_id > 0 ? $story_id : $post_id;

        $title = trim((string)($args['title'] ?? ''));
        $keyword = trim((string)($args['keyword'] ?? $title));
        $content = trim((string)($args['content'] ?? ''));
        $brief = trim((string)($args['brief'] ?? ''));
        $prompt = trim((string)($args['prompt'] ?? ''));
        $desc = trim((string)($args['desc'] ?? ''));
        $alt = trim((string)($args['alt'] ?? ''));
        $locale = (string)($args['locale'] ?? 'pt_BR');
        $template = sanitize_key((string)($args['template'] ?? 'article'));
        $context = strtolower(trim((string)($args['context'] ?? (($target === 'story' || $target === 'ws_slide' || $target === 'alpha_story') ? 'story' : 'thumb'))));
        $allow_pick = !empty($args['allow_pick']);
        $auto_pick = !empty($args['auto_pick']);
        $pick_url = trim((string)($args['pick_url'] ?? ''));
        $provider_override = trim((string)($args['image_provider'] ?? ''));

        if ($ref_id <= 0 && $pick_url === '' && !$allow_pick) {
            return new WP_Error('pga_image_post', 'Post id invalido para imagem.');
        }

        if ($alt === '') {
            $alt = $title !== '' ? $title : ($keyword !== '' ? $keyword : '');
        }
        if ($alt === '' && $ref_id > 0) {
            $alt = get_the_title($ref_id) ?: '';
        }

        $provider = $provider_override !== '' ? strtolower($provider_override) : alpha_suite_resolve_image_provider($target, $context);
        if ($provider === '') {
            $provider = 'pollinations';
        }

        if ($provider === 'none') {
            return 0;
        }

        if ($allow_pick && $provider === 'pexels' && $pick_url === '' && !$auto_pick) {
            $query = alpha_suite_build_stock_image_query($target, $title, $desc, $keyword, $brief, $content, $locale, $template, $provider);
            $options = alpha_suite_fetch_stock_image_options($provider, $query, $story_id > 0 ? $story_id : $post_id, $context);

            if (is_wp_error($options)) {
                return $options;
            }

            if ($story_id > 0) {
                update_post_meta($story_id, '_pga_ws_last_image_options_' . max(0, (int)($args['index'] ?? -1)), $options);
            }

            return [
                'ok'       => true,
                'mode'     => 'pick',
                'provider' => $provider,
                'query'    => $query,
                'options'  => $options,
            ];
        }

        if ($provider === 'pexels' && ($pick_url !== '' || $auto_pick)) {
            if ($pick_url === '') {
                $query = alpha_suite_build_stock_image_query($target, $title, $desc, $keyword, $brief, $content, $locale, $template, $provider);
                $options = alpha_suite_fetch_stock_image_options($provider, $query, $story_id > 0 ? $story_id : $post_id, $context);
                if (is_wp_error($options)) {
                    return $options;
                }
                $first = $options[0] ?? null;
                $pick_url = is_array($first) ? (string)($first['full'] ?? '') : '';
            }

            if ($pick_url === '') {
                return new WP_Error('pga_image_pick_empty', 'Nao foi possivel escolher imagem.');
            }

            return alpha_suite_sideload_stock_image($pick_url, $ref_id, $alt, 'pexels', $story_id, $args);
        }

        if ($provider === 'unsplash' && ($pick_url !== '' || $auto_pick)) {
            if ($pick_url === '') {
                $query = alpha_suite_build_stock_image_query($target, $title, $desc, $keyword, $brief, $content, $locale, $template, $provider);
                $options = alpha_suite_fetch_stock_image_options($provider, $query, $story_id > 0 ? $story_id : $post_id, $context);
                if (is_wp_error($options)) {
                    return $options;
                }
                $first = $options[0] ?? null;
                $pick_url = is_array($first) ? (string)($first['full'] ?? '') : '';
            }

            if ($pick_url === '') {
                return new WP_Error('pga_image_pick_empty', 'Nao foi possivel escolher imagem.');
            }

            return alpha_suite_sideload_stock_image($pick_url, $ref_id, $alt, 'unsplash', $story_id, $args);
        }

        $final_prompt = trim($prompt);
        if ($final_prompt === '') {
            $final_prompt = alpha_suite_build_final_image_prompt($target, $title, $desc, $keyword, $brief, $content, $locale, $template, $provider);
            if (is_wp_error($final_prompt)) {
                return $final_prompt;
            }
        }

        if ($target === 'story' || $target === 'ws_slide' || $target === 'alpha_story') {
            if (!class_exists('AlphaSuite_Images')) {
                return new WP_Error('pga_images_missing', 'Classe de imagens nao encontrada.');
            }

            $att_id = AlphaSuite_Images::generate_story_by_settings($final_prompt, $ref_id, $alt);
            if (is_wp_error($att_id)) {
                return $att_id;
            }

            return [
                'ok'            => true,
                'mode'          => 'direct',
                'provider'      => $provider,
                'attachment_id' => (int) $att_id,
                'image_url'     => wp_get_attachment_image_url((int) $att_id, 'full') ?: '',
                'prompt'        => $final_prompt,
            ];
        }

        if (!class_exists('AlphaSuite_Images')) {
            return new WP_Error('pga_images_missing', 'Classe de imagens nao encontrada.');
        }

        $att_id = AlphaSuite_Images::generate_by_settings($final_prompt, $ref_id, $alt, [], 'thumb');
        if (is_wp_error($att_id)) {
            return $att_id;
        }

        return [
            'ok'            => true,
            'mode'          => 'direct',
            'provider'      => $provider,
            'attachment_id' => (int) $att_id,
            'image_url'     => wp_get_attachment_image_url((int) $att_id, 'full') ?: '',
            'prompt'        => $final_prompt,
        ];
    }
}

if (!function_exists('alpha_suite_resolve_image_provider')) {
    function alpha_suite_resolve_image_provider(string $target, string $context = 'thumb'): string
    {
        $target = strtolower(trim($target));
        $context = strtolower(trim($context));

        if (class_exists('AlphaSuite_Settings')) {
            $opts = AlphaSuite_Settings::get();

            if ($target === 'story' || $target === 'ws_slide' || $target === 'alpha_story' || $context === 'story') {
                $st = is_array($opts['stories'] ?? null) ? $opts['stories'] : [];
                $storyProv = trim((string)($st['images_provider'] ?? 'inherit'));
                if ($storyProv !== '' && $storyProv !== 'inherit') {
                    return strtolower($storyProv);
                }
            }

            if ($target === 'orion' || $target === 'rss' || $context === 'thumb') {
                $gp = is_array($opts['orion_posts'] ?? null) ? $opts['orion_posts'] : [];
                $orionProv = trim((string)($gp['images_provider'] ?? 'inherit'));
                if ($orionProv !== '' && $orionProv !== 'inherit') {
                    return strtolower($orionProv);
                }
            }
        }

        if (class_exists('AlphaSuite_AI')) {
            return strtolower((string) AlphaSuite_AI::get_image_provider());
        }

        return 'pollinations';
    }
}

if (!function_exists('alpha_suite_build_final_image_prompt')) {
    function alpha_suite_build_final_image_prompt(string $target, string $title, string $desc, string $keyword, string $brief, string $content, string $locale, string $template, string $provider)
    {
        if (!class_exists('AlphaSuite_Prompts')) {
            return new WP_Error('pga_prompts_missing', 'Classe de prompts nao encontrada.');
        }

        if ($target === 'ws_slide') {
            $meta = AlphaSuite_Prompts::build_ws_slide_image_prompt($title, $desc, $provider);
            if ($meta === '') {
                $meta = AlphaSuite_Prompts::build_image_prompt($keyword, $title, $locale, $template, $provider);
            }

            if (class_exists('AlphaSuite_AI')) {
                $schema = ['content' => 'string'];
                $resolved = AlphaSuite_AI::complete($meta, $schema, ['format' => 'stories']);
                if (!is_wp_error($resolved) && is_array($resolved) && !empty($resolved['content'])) {
                    return trim((string) $resolved['content']);
                }
            }

            return $meta;
        }

        $meta = AlphaSuite_Prompts::build_image_prompt($keyword, $title, $locale, $template, $provider);
        if ($meta === '') {
            return new WP_Error('pga_image_prompt_empty', 'Prompt de imagem vazio.');
        }

        if (class_exists('AlphaSuite_AI')) {
            $resolved = AlphaSuite_AI::image_prompt($meta, []);
            if (!is_wp_error($resolved) && is_string($resolved) && trim($resolved) !== '') {
                return trim($resolved);
            }
        }

        return $meta;
    }
}

if (!function_exists('alpha_suite_build_stock_image_query')) {
    function alpha_suite_build_stock_image_query(string $target, string $title, string $desc, string $keyword, string $brief, string $content, string $locale, string $template, string $provider): string
    {
        if (!class_exists('AlphaSuite_Prompts')) {
            return trim($keyword !== '' ? $keyword : $title);
        }

        if ($target === 'ws_slide') {
            $meta = AlphaSuite_Prompts::build_ws_slide_image_prompt($title, $desc, $provider);
        } else {
            $meta = AlphaSuite_Prompts::build_image_prompt($keyword, $title, $locale, $template, $provider);
        }

        if (class_exists('AlphaSuite_AI')) {
            $schema = ['content' => 'string'];
            $resolved = AlphaSuite_AI::complete($meta, $schema, ['format' => 'stories']);
            if (!is_wp_error($resolved) && is_array($resolved) && !empty($resolved['content'])) {
                return trim((string) $resolved['content']);
            }
        }

        return trim($meta);
    }
}

if (!function_exists('alpha_suite_fetch_stock_image_options')) {
    function alpha_suite_fetch_stock_image_options(string $provider, string $query, int $post_id, string $context = 'thumb')
    {
        $provider = strtolower(trim($provider));
        $query = trim($query);

        if ($query === '') {
            return new WP_Error('pga_image_query_empty', 'Consulta de imagem vazia.');
        }

        $orientation = ($context === 'story') ? 'portrait' : 'landscape';
        $options = [];

        if ($provider === 'pexels') {
            if (!class_exists('AlphaSuite_Settings')) {
                return new WP_Error('pga_pexels_no_cfg', 'Configuracoes do Pexels nao encontradas.');
            }

            $opts = AlphaSuite_Settings::get();
            $api = $opts['apis']['pexels'] ?? [];
            $key = trim((string)($api['key'] ?? ''));
            if ($key === '') {
                return new WP_Error('pga_pexels_no_key', 'Chave Pexels nao configurada.');
            }

            $endpoint = add_query_arg([
                'query'       => $query,
                'per_page'    => 3,
                'page'        => 1,
                'orientation' => $orientation,
            ], 'https://api.pexels.com/v1/search');

            $res = wp_remote_get($endpoint, [
                'timeout' => 30,
                'headers' => ['Authorization' => $key],
            ]);
            if (is_wp_error($res)) {
                return $res;
            }

            $code = wp_remote_retrieve_response_code($res);
            $body = wp_remote_retrieve_body($res);
            if ($code < 200 || $code >= 300 || !$body) {
                return new WP_Error('pga_pexels_http', 'Erro HTTP no Pexels.');
            }

            $json = json_decode($body, true);
            $photos = is_array($json['photos'] ?? null) ? $json['photos'] : [];
            if (empty($photos)) {
                return new WP_Error('pga_pexels_empty', 'Nenhuma imagem encontrada no Pexels.');
            }

            $used = get_post_meta($post_id, '_pga_ws_used_pexels', true);
            if (!is_array($used)) {
                $used = ['ids' => [], 'urls' => []];
            }

            $used_ids = array_map('intval', (array)($used['ids'] ?? []));
            $used_urls = array_map('strval', (array)($used['urls'] ?? []));

            foreach (array_slice(array_values($photos), 0, 10) as $ph) {
                $pid = isset($ph['id']) ? (int)$ph['id'] : 0;
                $src = is_array($ph['src'] ?? null) ? $ph['src'] : [];
                $thumb = $src['medium'] ?? $src['small'] ?? $src['tiny'] ?? ($src['portrait'] ?? ($src['large'] ?? ''));
                $full = $src['portrait'] ?? $src['large2x'] ?? $src['large'] ?? $src['original'] ?? '';
                if (!$pid || !$full) {
                    continue;
                }
                if (in_array($pid, $used_ids, true) || in_array((string)$full, $used_urls, true)) {
                    continue;
                }
                $options[] = ['id' => $pid, 'thumb' => (string)$thumb, 'full' => (string)$full];
                if (count($options) >= 3) {
                    break;
                }
            }

            return $options ?: new WP_Error('pga_pexels_no_options', 'Nao foi possivel montar opcoes de imagem.');
        }

        if ($provider === 'unsplash') {
            if (!class_exists('AlphaSuite_Settings')) {
                return new WP_Error('pga_unsplash_no_cfg', 'Configuracoes do Unsplash nao encontradas.');
            }

            $opts = AlphaSuite_Settings::get();
            $api = $opts['apis']['unsplash'] ?? [];
            $key = trim((string)($api['access_key'] ?? ''));
            if ($key === '') {
                return new WP_Error('pga_unsplash_no_key', 'Access key do Unsplash nao configurada.');
            }

            $endpoint = add_query_arg([
                'query'          => $query,
                'per_page'       => 12,
                'orientation'    => $orientation,
                'content_filter' => 'high',
            ], 'https://api.unsplash.com/search/photos');

            $res = wp_remote_get($endpoint, [
                'timeout' => 30,
                'headers' => ['Authorization' => 'Client-ID ' . $key],
            ]);
            if (is_wp_error($res)) {
                return $res;
            }

            $code = wp_remote_retrieve_response_code($res);
            $body = wp_remote_retrieve_body($res);
            if ($code < 200 || $code >= 300 || !$body) {
                return new WP_Error('pga_unsplash_http', 'Erro HTTP no Unsplash.');
            }

            $json = json_decode($body, true);
            $results = is_array($json['results'] ?? null) ? $json['results'] : [];
            if (empty($results)) {
                return new WP_Error('pga_unsplash_empty', 'Nenhuma imagem encontrada no Unsplash.');
            }

            foreach ($results as $item) {
                $urls = is_array($item['urls'] ?? null) ? $item['urls'] : [];
                $full = $urls['regular'] ?? $urls['full'] ?? $urls['small'] ?? '';
                if (!$full) {
                    continue;
                }
                $options[] = [
                    'id'    => isset($item['id']) ? (string)$item['id'] : '',
                    'thumb' => (string)($urls['thumb'] ?? $urls['small'] ?? $full),
                    'full'  => (string)$full,
                ];
            }

            return $options ?: new WP_Error('pga_unsplash_no_options', 'Nao foi possivel montar opcoes de imagem.');
        }

        return new WP_Error('pga_stock_provider_unsupported', 'Provider de imagem nao suportado para selecao.');
    }
}

if (!function_exists('alpha_suite_sideload_stock_image')) {
    function alpha_suite_sideload_stock_image(string $image_url, int $post_id, string $alt, string $source, int $story_id = 0, array $args = [])
    {
        $image_url = trim($image_url);
        if ($image_url === '') {
            return new WP_Error('pga_stock_image_empty', 'URL de imagem vazia.');
        }

        if (!function_exists('download_url')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        if (!function_exists('media_handle_sideload')) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $tmp = download_url($image_url, 30);
        if (is_wp_error($tmp)) {
            return $tmp;
        }

        $filename = basename(wp_parse_url($image_url, PHP_URL_PATH) ?: 'image.jpg');
        if (!preg_match('/\.(jpg|jpeg|png|webp)$/i', $filename)) {
            $filename .= '.jpg';
        }

        $file_array = [
            'name'     => sanitize_file_name($filename),
            'tmp_name' => $tmp,
        ];

        $attach_to = $story_id > 0 ? $story_id : $post_id;
        $att_id = media_handle_sideload($file_array, $attach_to, $alt);
        if (is_wp_error($att_id)) {
            wp_delete_file($tmp);
            return $att_id;
        }

        $att_id = (int) $att_id;
        $img_url = wp_get_attachment_image_url($att_id, 'full') ?: '';

        if ($story_id > 0 && !empty($args['index'])) {
            $pages = get_post_meta($story_id, '_pga_ws_pages', true);
            if (is_array($pages)) {
                $index = (int)$args['index'];
                if (isset($pages[$index]) && is_array($pages[$index])) {
                    $pages[$index]['image_id'] = $att_id;
                    $pages[$index]['image_url'] = $img_url;
                    $pages[$index]['image'] = $img_url;
                    update_post_meta($story_id, '_pga_ws_pages', $pages);
                }
            }
        }

        return [
            'ok'            => true,
            'mode'          => 'direct',
            'provider'      => $source,
            'attachment_id' => $att_id,
            'image_url'     => $img_url,
        ];
    }
}
