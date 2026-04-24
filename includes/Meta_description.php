<?php

if (!defined('ABSPATH')) exit;

class AlphaSuite_Meta_description
{
    public static function generate_meta(int $postId, string $content = '')
    {
        $postId = intval($postId);

        if (!$postId || !get_post($postId)) {
            return new WP_Error('pga_invalid_post', 'Post invalido.');
        }

        $locale   = get_post_meta($postId, '_pga_outline_locale', true) ?: 'pt_BR';
        $title    = get_post_meta($postId, '_pga_chosen_title', true);
        $template = get_post_meta($postId, '_pga_template_key', true) ?: 'rss';

        if (!$title) {
            $title = get_post_field('post_title', $postId);
        }

        if (!$content) {
            $post = get_post($postId);
            $content = $post ? $post->post_content : '';
        }

        $meta_desc = alpha_suite_generate_meta_description([
            'target'   => $template === 'rss' ? 'rss' : 'orion',
            'template' => (string) $template,
            'title'    => (string) $title,
            'locale'   => (string) $locale,
            'content'  => (string) $content,
            'provider' => (class_exists('AlphaSuite_AI') ? AlphaSuite_AI::get_text_provider() : 'openai'),
        ]);

        if (is_wp_error($meta_desc)) {
            return AlphaSuite_FailJob::fail_job($postId, $meta_desc);
        }

        update_post_meta($postId, '_pga_meta_description', $meta_desc);
        update_post_meta($postId, '_pga_job_status', 'meta_done');

        return [
            'post_id' => $postId,
            'meta'    => (string) $meta_desc,
        ];
    }
}
