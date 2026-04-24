<?php

if (!defined('ABSPATH')) exit;

class AlphaSuite_Excerpt
{
    public static function generate_excerpt(int $postId, string $content = '')
    {
        $postId = intval($postId);

        if (!$postId || !get_post($postId)) {
            return new WP_Error('pga_invalid_post', 'Post inválido.');
        }

        $title = get_post_meta($postId, '_pga_chosen_title', true);
        if (!$title) {
            $title = get_post_field('post_title', $postId);
        }

        $post = get_post($postId);

        if (!$content) {
            $post = get_post($postId);
            $content = $post ? $post->post_content : '';
        }

        if (!$title && !$content) {
            return new WP_Error('pga_missing_content', 'Conteúdo insuficiente para gerar subtítulo.');
        }

        // limita para ~250 palavras
        $words = preg_split('/\s+/', wp_strip_all_tags($content));
        $words = array_slice($words, 0, 250);
        $content = implode(' ', $words);
        $template = get_post_meta($postId, '_pga_template_key', true) ?: 'rss';
        $locale = get_post_meta($postId, '_pga_outline_locale', true) ?: 'pt_BR';

        $prompt = AlphaSuite_Prompts::build_excerpt_prompt($title, $content, $template, $locale);

        $excerpt = AlphaSuite_AI::complete($prompt);

        if (is_wp_error($excerpt)) {
            return $excerpt;
        }

        if (is_array($excerpt)) {
            $excerpt = $excerpt['content'] ?? '';
        }

        $excerpt = trim((string) $excerpt);

        wp_update_post([
            'ID'           => $postId,
            'post_excerpt' => $excerpt
        ]);

        return [
            'excerpt' => $excerpt
        ];
    }
}
