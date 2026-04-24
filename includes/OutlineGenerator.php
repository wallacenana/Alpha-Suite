<?php

if (!defined('ABSPATH')) exit;

if (!function_exists('alpha_suite_generate_outline')) {
    /**
     * Ponto unico para gerar outlines/paginas em todos os fluxos.
     *
     * Targets suportados:
     * - orion
     * - rss
     * - ws_story
     * - alpha_story
     *
     * Retorno:
     * - orion/rss: ['sections' => [...], 'idea' => [...]]
     * - ws_story: ['title' => '', 'desc' => '', 'slug' => '', 'pages' => [...]]
     * - alpha_story: ['pages' => [...]]
     */
    function alpha_suite_generate_outline(array $args)
    {
        $target = strtolower(trim((string)($args['target'] ?? 'orion')));
        $template = sanitize_key((string)($args['template'] ?? 'article'));
        $keyword = (string)($args['keyword'] ?? '');
        $title = (string)($args['title'] ?? '');
        $length = (string)($args['length'] ?? 'medium');
        $locale = (string)($args['locale'] ?? 'pt_BR');
        $url = (string)($args['url'] ?? '');
        $content = (string)($args['content'] ?? '');
        $provider = (string)($args['provider'] ?? (class_exists('AlphaSuite_AI') ? AlphaSuite_AI::get_text_provider() : 'openai'));
        $idea = is_array($args['idea'] ?? null) ? $args['idea'] : [];

        if (in_array($target, ['orion', 'rss'], true)) {
            if (empty($idea) && class_exists('AlphaSuite_Outline') && method_exists('AlphaSuite_Outline', 'build_idea_brief')) {
                $idea = AlphaSuite_Outline::build_idea_brief($template, $keyword, $title, $length, $locale, $url, $content);
            }

            if (!class_exists('AlphaSuite_Outline')) {
                return new WP_Error('pga_outline_missing', 'Motor de outline indisponivel.');
            }

            $outline = AlphaSuite_Outline::generate(
                $template,
                $keyword,
                $title,
                $length,
                $locale,
                $url,
                $content,
                $idea
            );

            if (is_wp_error($outline)) {
                return $outline;
            }

            $sections = is_array($outline['sections'] ?? null) ? $outline['sections'] : [];

            return [
                'sections' => $sections,
                'idea'     => $idea,
                'target'   => $target,
            ];
        }

        if ($target === 'ws_story') {
            $payload = [
                'slidesCount'      => (int)($args['slidesCount'] ?? 6),
                'locale'           => $locale,
                'title'            => $title,
                'content'          => $content,
                'cta_pages'        => is_array($args['cta_pages'] ?? null) ? array_values($args['cta_pages']) : [],
                'cta_text_default' => (string)($args['cta_text_default'] ?? 'Saiba mais'),
                'cta_url_default'  => (string)($args['cta_url_default'] ?? ''),
            ];

            $prompt = AlphaSuite_Prompts::build_ws_story_prompt($payload);
            $schema = [
                'title' => 'string',
                'desc'  => 'string',
                'slug'  => 'string',
                'pages' => 'array',
            ];

            $resp = AlphaSuite_AI::complete(
                $prompt,
                $schema,
                [
                    'provider'    => $provider,
                    'temperature' => (float)($args['temperature'] ?? 0.15),
                    'top_p'       => (float)($args['top_p'] ?? 0.7),
                    'max_tokens'  => (int)($args['max_tokens'] ?? 1800),
                    'template'    => 'story_outline',
                ]
            );

            if (is_wp_error($resp)) {
                return $resp;
            }

            $obj = alpha_suite_outline_extract_story_object($resp);
            if (is_wp_error($obj)) {
                return $obj;
            }

            $pages = [];
            foreach ((array)($obj['pages'] ?? []) as $p) {
                if (!is_array($p)) {
                    continue;
                }

                $pages[] = [
                    'heading'  => (string)($p['heading'] ?? ''),
                    'body'     => (string)($p['body'] ?? ''),
                    'cta_text' => (string)($p['cta_text'] ?? ''),
                    'cta_url'  => (string)($p['cta_url'] ?? ''),
                    'prompt'   => (string)($p['prompt'] ?? ''),
                ];
            }

            return [
                'title' => (string)($obj['title'] ?? ''),
                'desc'  => (string)($obj['desc'] ?? ''),
                'slug'  => (string)($obj['slug'] ?? ''),
                'pages' => $pages,
            ];
        }

        if ($target === 'alpha_story') {
            $post = $args['post'] ?? null;
            if (!$post instanceof WP_Post) {
                $post_id = absint($args['post_id'] ?? 0);
                $post = $post_id ? get_post($post_id) : null;
            }

            if (!$post instanceof WP_Post) {
                return new WP_Error('pga_story_post', 'Post de origem invalido.');
            }

            $raw_html = (string)($args['raw_html'] ?? '');
            if ($raw_html === '') {
                $raw_html = apply_filters('the_content', $post->post_content);
            }

            $brief = (string)($args['brief'] ?? '');
            $imageProvider = (string)($args['imageProvider'] ?? 'pollinations');
            $lang = (string)($args['lang'] ?? $locale);

            $prompt = AlphaSuite_Prompts::build_story_prompt_for_post(
                $post,
                $raw_html,
                $brief,
                $imageProvider,
                $lang
            );

            $resp = AlphaSuite_AI::generate_story_pages(
                $prompt,
                [
                    'provider'    => $provider,
                    'temperature' => (float)($args['temperature'] ?? 0.5),
                    'max_tokens'  => (int)($args['max_tokens'] ?? 6000),
                ]
            );

            if (is_wp_error($resp)) {
                return $resp;
            }

            $pages = [];
            foreach ((array)($resp['pages'] ?? []) as $p) {
                if (!is_array($p)) {
                    continue;
                }

                $pages[] = [
                    'heading'  => (string)($p['heading'] ?? ''),
                    'body'     => (string)($p['body'] ?? ''),
                    'cta_text' => (string)($p['cta_text'] ?? ''),
                    'cta_url'  => (string)($p['cta_url'] ?? ''),
                    'prompt'   => (string)($p['prompt'] ?? ''),
                ];
            }

            return [
                'pages' => $pages,
            ];
        }

        return new WP_Error('pga_outline_target', 'Target de outline invalido.');
    }
}

if (!function_exists('alpha_suite_outline_extract_story_object')) {
    function alpha_suite_outline_extract_story_object($resp)
    {
        if (!is_array($resp)) {
            return new WP_Error('pga_story_outline_invalid', 'Resposta invalida do story outline.');
        }

        if (isset($resp['content'])) {
            $content = $resp['content'];
            if (is_array($content)) {
                $resp = $content;
            } elseif (is_string($content)) {
                $decoded = json_decode($content, true);
                if (is_array($decoded)) {
                    $resp = $decoded;
                }
            }
        }

        if (!is_array($resp)) {
            return new WP_Error('pga_story_outline_invalid', 'Resposta invalida do story outline.');
        }

        if (!isset($resp['pages']) || !is_array($resp['pages'])) {
            return new WP_Error('pga_story_outline_invalid', 'Pages ausente no story outline.');
        }

        return $resp;
    }
}
