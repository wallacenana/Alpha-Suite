<?php

if (!defined('ABSPATH')) exit;

class AlphaSuite_Meta_description
{
    public static function generate_meta(int $postId, string $content = '')
    {
        $postId = intval($postId);

        if (!$postId || !get_post($postId)) {
            return new WP_Error('pga_invalid_post', 'Post inválido.');
        }

        $locale   = get_post_meta($postId, '_pga_outline_locale', true) ?: 'pt_BR';
        $title    = get_post_meta($postId, '_pga_chosen_title', true);
        $template = get_post_meta($postId, '_pga_template_key', true) ?: 'rss';


        if (!$title) {
            $title = get_post_field('post_title', $postId);
        }

        if (!$title) {
            return new WP_Error('pga_no_title', 'Título não encontrado para gerar meta.');
        }

        if (!$content) {
            $post = get_post($postId);
            $content = $post ? $post->post_content : '';
        }

        // opcional: limitar tamanho (recomendado)
        $content_excerpt = wp_trim_words(wp_strip_all_tags($content), 200, '...');
        $promptMeta = AlphaSuite_Prompts::build_meta_description_prompt(
            (string)$template,
            (string)'',
            (string)$title,
            (string)$locale,
            (string)$content_excerpt
        );

        $respMeta = AlphaSuite_AI::meta_description($promptMeta);

        if (is_wp_error($respMeta)) {
            return AlphaSuite_FailJob::fail_job($postId, $respMeta);
        }

        $meta_desc = '';
        $raw = '';

        // --------- EXTRAÇÃO SEGURA ----------
        if (is_string($respMeta)) {
            $raw = $respMeta;
        } elseif (is_array($respMeta)) {
            $raw = (string)($respMeta['meta_description'] ?? $respMeta['description'] ?? $respMeta['content'] ?? '');
        } elseif (is_object($respMeta)) {
            $raw = (string)($respMeta->meta_description ?? $respMeta->description ?? $respMeta->content ?? '');
        }

        $raw = trim($raw);

        // --------- JSON DENTRO DE TEXTO ----------
        if ($raw !== '' && ($raw[0] === '{' || $raw[0] === '[')) {
            $j = json_decode($raw, true);
            if (is_array($j)) {
                $raw = (string)(
                    $j['meta_description']
                    ?? $j['description']
                    ?? $j['content']
                    ?? ''
                );
            }
        }

        // --------- REMOVE PREFIXOS ----------
        $raw = preg_replace(
            '/^\s*(meta\s*description|meta\s*descri[cç][aã]o|description)\s*:\s*/i',
            '',
            $raw
        );

        // --------- PRIMEIRA LINHA ----------
        $raw = preg_split("/\r\n|\r|\n/", $raw)[0] ?? $raw;
        $raw = trim($raw);

        // --------- SANITIZA ----------
        if ($raw !== '') {
            $raw = wp_strip_all_tags($raw);
            $raw = html_entity_decode($raw, ENT_QUOTES, 'UTF-8');
            $raw = preg_replace('/\s+/', ' ', $raw);
            $raw = trim($raw);
        }

        if ($raw !== '') {
            $meta_desc = $raw;
            update_post_meta($postId, '_pga_meta_description', $meta_desc);
            update_post_meta($postId, '_pga_job_status', 'meta_done');
        }

        return [
            'post_id' => $postId,
            'meta'    => $meta_desc,
        ];
    }
}
