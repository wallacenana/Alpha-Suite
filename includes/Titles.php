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
            if (is_wp_error($yt)) return $yt;

            // Aqui "keyword" pode ser:
            // - o próprio $keyword (se você usa URL no campo)
            // - OU um assunto derivado
            // - OU simplesmente $yt['title'] (muita gente prefere isso)
            $topic = $keyword ?: ($yt['title'] ?? '');

            $titlePrompt = AlphaSuite_Prompts::build_title_prompt_modelar_youtube(
                $yt,
                $topic,
                3,
                5,
                $locale
            );
        } else if ($template === 'rss') {
            $titlePrompt = AlphaSuite_Prompts::build_title_rss_prompt(
                $seed,
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

        $titles = AlphaSuite_AI::complete(
            $titlePrompt,
            ['title' => 'string'],
            [
                'max_tokens'  => 400,
                'temperature' => 0.5,
            ]
        );

        if (is_wp_error($titles)) {
            return AlphaSuite_FailJob::fail_job($draft_id, $titles);
        }

        $newTitle = '';

        if (isset($titles['title'])) {

            if (is_string($titles['title'])) {
                $newTitle = trim($titles['title']);
            } elseif (is_array($titles['title'])) {

                // Se for array com texto dentro
                if (isset($titles['title']['text'])) {
                    $newTitle = trim($titles['title']['text']);
                }

                // Se for array indexado
                elseif (isset($titles['title'][0])) {
                    $newTitle = trim((string)$titles['title'][0]);
                }
            }
        }

        return  $newTitle;
    }
}
