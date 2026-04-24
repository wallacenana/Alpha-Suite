<?php

if (!defined('ABSPATH')) exit;

if (!function_exists('alpha_suite_generate_meta_description')) {
    /**
     * Helper unico para gerar meta description.
     *
     * Targets suportados:
     * - orion
     * - rss
     * - modelar_youtube
     *
     * Retorno:
     * - string
     */
    function alpha_suite_generate_meta_description(array $args)
    {
        $target = strtolower(trim((string)($args['target'] ?? 'orion')));
        $template = sanitize_key((string)($args['template'] ?? 'rss'));
        $keyword = trim((string)($args['keyword'] ?? ''));
        $title = trim((string)($args['title'] ?? ''));
        $locale = (string)($args['locale'] ?? 'pt_BR');
        $content = trim((string)($args['content'] ?? ''));
        $provider = (string)($args['provider'] ?? (class_exists('AlphaSuite_AI') ? AlphaSuite_AI::get_text_provider() : 'openai'));

        if ($title === '') {
            $title = $keyword;
        }

        if ($title === '') {
            return new WP_Error('pga_no_title', 'Titulo nao encontrado para gerar meta description.');
        }

        $content_excerpt = $content;
        if ($content_excerpt !== '') {
            $content_excerpt = wp_trim_words(wp_strip_all_tags($content_excerpt), 200, '...');
        }

        $prompt = AlphaSuite_Prompts::build_meta_description_prompt(
            $template,
            $keyword,
            $title,
            $locale,
            $content_excerpt
        );

        $respMeta = AlphaSuite_AI::meta_description($prompt, [
            'provider'    => $provider,
            'temperature' => (float)($args['temperature'] ?? 0.6),
            'max_tokens'  => (int)($args['max_tokens'] ?? 300),
        ]);

        if (is_wp_error($respMeta)) {
            return $respMeta;
        }

        $raw = '';
        if (is_string($respMeta)) {
            $raw = $respMeta;
        } elseif (is_array($respMeta)) {
            $raw = (string)($respMeta['meta_description'] ?? $respMeta['description'] ?? $respMeta['content'] ?? '');
        } elseif (is_object($respMeta)) {
            $raw = (string)($respMeta->meta_description ?? $respMeta->description ?? $respMeta->content ?? '');
        }

        $raw = trim($raw);

        if ($raw !== '' && ($raw[0] === '{' || $raw[0] === '[')) {
            $j = json_decode($raw, true);
            if (is_array($j)) {
                $raw = (string)($j['meta_description'] ?? $j['description'] ?? $j['content'] ?? $raw);
            }
        }

        $raw = preg_replace(
            '/^\s*(meta\s*description|meta\s*descri[cç][aã]o|description)\s*:\s*/i',
            '',
            $raw
        );

        $raw = preg_split("/\r\n|\r|\n/", $raw)[0] ?? $raw;
        $raw = trim($raw);

        if ($raw !== '') {
            $raw = wp_strip_all_tags($raw);
            $raw = html_entity_decode($raw, ENT_QUOTES, 'UTF-8');
            $raw = preg_replace('/\s+/', ' ', $raw);
            $raw = trim($raw);
        }

        if ($raw === '') {
            return new WP_Error('pga_meta_desc_empty', 'Meta description vazia.');
        }

        return $raw;
    }
}
