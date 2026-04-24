<?php
// includes/License.php
if (!defined('ABSPATH')) exit;

class AlphaSuite_Titles
{
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

    public static function getTitle(
        int $draft_id,
        string $template = '',
        string $keyword = '',
        string $locale = 'pt-br',
        string $url = '',
        $seed = ''
    ) {
        if ($template === 'modelar_youtube') {
            $yt = AlphaSuite_Youtube::fetch_video_data($url);
            if (is_wp_error($yt)) {
                return $yt;
            }

            $result = alpha_suite_generate_title([
                'target'   => 'modelar_youtube',
                'template' => $template,
                'keyword'  => $keyword,
                'locale'   => $locale,
                'video'    => $yt,
            ]);
        } elseif ($template === 'rss') {
            $result = alpha_suite_generate_title([
                'target'   => 'rss',
                'template' => $template,
                'keyword'  => $keyword,
                'locale'   => $locale,
                'url'      => $url,
                'seed'     => $seed,
            ]);
        } else {
            $result = alpha_suite_generate_title([
                'target'   => 'orion',
                'template' => $template,
                'keyword'  => $keyword,
                'locale'   => $locale,
                'url'      => $url,
                'seed'     => $seed,
            ]);
        }

        if (is_wp_error($result)) {
            return AlphaSuite_FailJob::fail_job($draft_id, $result);
        }

        return is_string($result) ? trim($result) : '';
    }
}
