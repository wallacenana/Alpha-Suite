<?php

if (!defined('ABSPATH')) exit;

if (!function_exists('alpha_suite_generate_title')) {
    /**
     * Ponto unico para gerar titulos e metadados de stories.
     *
     * Targets suportados:
     * - orion
     * - rss
     * - modelar_youtube
     * - ws_story
     * - alpha_story
     *
     * Retorno:
     * - orion/rss/modelar_youtube/alpha_story: string
     * - ws_story: array { title, desc, slug, pages }
     */
    function alpha_suite_generate_title(array $args)
    {
        $target = strtolower(trim((string)($args['target'] ?? 'orion')));
        $template = sanitize_key((string)($args['template'] ?? 'article'));
        $keyword = trim((string)($args['keyword'] ?? ''));
        $locale = (string)($args['locale'] ?? 'pt_BR');
        $url = trim((string)($args['url'] ?? ''));
        $seed = trim((string)($args['seed'] ?? ''));
        $provider = (string)($args['provider'] ?? (class_exists('AlphaSuite_AI') ? AlphaSuite_AI::get_text_provider() : 'openai'));

        if ($target === 'ws_story') {
            $payload = [
                'slidesCount'      => max(1, (int)($args['slidesCount'] ?? 6)),
                'locale'           => $locale,
                'title'            => trim((string)($args['title'] ?? '')),
                'content'          => trim((string)($args['content'] ?? '')),
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
                    'top_p'        => (float)($args['top_p'] ?? 0.7),
                    'max_tokens'   => (int)($args['max_tokens'] ?? 1800),
                    'template'     => 'story_outline',
                ]
            );

            if (is_wp_error($resp)) {
                return $resp;
            }

            $obj = alpha_suite_title_extract_story_object($resp);
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

        if ($target === 'modelar_youtube') {
            $video = is_array($args['video'] ?? null) ? $args['video'] : [];
            $topic = $keyword !== '' ? $keyword : (string)($video['title'] ?? '');
            $titlePrompt = AlphaSuite_Prompts::build_title_prompt_modelar_youtube(
                $video,
                $topic,
                3,
                5,
                $locale
            );
        } else {
            if ($target === 'rss') {
                $titlePrompt = AlphaSuite_Prompts::build_title_rss_prompt(
                    $seed !== '' ? $seed : $keyword,
                    $locale,
                    $url,
                    $template
                );
            } else {
                $titlePrompt = AlphaSuite_Prompts::build_title_prompt(
                    $template,
                    $keyword,
                    3,
                    5,
                    $locale
                );
            }
        }
        $titles = AlphaSuite_AI::complete(
            $titlePrompt,
            ['title' => 'string'],
            [
                'provider'    => $provider,
                'max_tokens'  => (int)($args['max_tokens'] ?? 400),
                'temperature' => (float)($args['temperature'] ?? 0.5),
            ]
        );

        if (is_wp_error($titles)) {
            return $titles;
        }

        return alpha_suite_title_extract_title_string($titles);
    }
}

if (!function_exists('alpha_suite_title_extract_story_object')) {
    function alpha_suite_title_extract_story_object($resp)
    {
        if (!is_array($resp)) {
            return new WP_Error('pga_story_title_invalid', 'Resposta invalida do story title.');
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

        if (!is_array($resp) || !isset($resp['pages']) || !is_array($resp['pages'])) {
            return new WP_Error('pga_story_title_invalid', 'Pages ausente no story title.');
        }

        return $resp;
    }
}

if (!function_exists('alpha_suite_title_extract_title_string')) {
    function alpha_suite_title_extract_title_string($titles)
    {
        if (!is_array($titles)) {
            return new WP_Error('pga_title_invalid', 'Resposta invalida para titulo.');
        }

        $newTitle = '';

        if (isset($titles['title'])) {
            if (is_string($titles['title'])) {
                $newTitle = trim($titles['title']);
            } elseif (is_array($titles['title'])) {
                if (isset($titles['title']['text'])) {
                    $newTitle = trim((string)$titles['title']['text']);
                } elseif (isset($titles['title'][0])) {
                    $newTitle = trim((string)$titles['title'][0]);
                }
            }
        }

        if ($newTitle === '') {
            return new WP_Error('pga_title_empty', 'Titulo retornado vazio.');
        }

        return $newTitle;
    }
}
