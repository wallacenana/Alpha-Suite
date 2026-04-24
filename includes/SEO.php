<?php
if (!defined('ABSPATH')) exit;

class AlphaSuite_SEO
{

    /**
     * Aplica metadados de SEO nos plugins populares.
     *
     * $meta = [
     *   'title'         => 'Meta title opcional',
     *   'description'   => 'Meta description opcional',
     *   'focus_keyword' => 'frase chave de foco',
     * ]
     */
    public static function apply_meta(int $post_id, array $meta): void
    {
        $m = wp_parse_args($meta, [
            'title'         => '',
            'description'   => '',
            'focus_keyword' => '',
        ]);

        // --- Saneamento básico + limites clássicos ---
        $title       = self::sanitize_text((string) $m['title'], 70);       // ~60–65 chars
        $description = self::sanitize_text((string) $m['description'], 170);
        $focus_kw    = self::sanitize_text((string) $m['focus_keyword'], 120);

        // ===== Rank Math =====
        if (function_exists('rank_math')) {
            if ($title !== '') {
                update_post_meta($post_id, 'rank_math_title', $title);
            }
            if ($description !== '') {
                update_post_meta($post_id, 'rank_math_description', $description);
            }
            if ($focus_kw !== '') {
                update_post_meta($post_id, 'rank_math_focus_keyword', $focus_kw);
            }
        }

        // ===== SmartCrawl =====
        if (defined('SMARTCRAWL_VERSION') || function_exists('smartcrawl_init')) {

            if ($title !== '') {
                update_post_meta($post_id, '_wds_title', $title);
            }

            if ($description !== '') {
                update_post_meta($post_id, '_wds_metadesc', $description);
            }

            if ($focus_kw !== '') {
                update_post_meta($post_id, '_wds_focus_keyword', $focus_kw);
            }
        }

        // ===== Yoast SEO =====
        if (defined('WPSEO_VERSION')) {
            if ($title !== '') {
                update_post_meta($post_id, '_yoast_wpseo_title', $title);
            }
            if ($description !== '') {
                update_post_meta($post_id, '_yoast_wpseo_metadesc', $description);
            }
            if ($focus_kw !== '') {
                update_post_meta($post_id, '_yoast_wpseo_focuskw', $focus_kw);
            }
        }

        // ===== SEOPress =====
        if (defined('SEOPRESS_VERSION')) {
            if ($title !== '') {
                update_post_meta($post_id, '_seopress_titles_title', $title);
            }
            if ($description !== '') {
                update_post_meta($post_id, '_seopress_titles_desc', $description);
            }
            if ($focus_kw !== '') {
                update_post_meta($post_id, '_seopress_analysis_target_kw', $focus_kw);
            }
        }

        // ===== All in One SEO (AIOSEO v4+) =====
        if (defined('AIOSEO_VERSION')) {
            self::apply_aioseo_meta($post_id, $title, $description, $focus_kw);
        }
    }

    // ------------------------------------------------------------------
    // AIOSEO: usa o meta "aioseo_post_settings" (array serializado).
    // Mantemos os metas antigos como fallback, mas o principal é esse array.

    protected static function apply_aioseo_meta(int $post_id, string $title, string $description, string $focus_kw): void
    {
        $settings = get_post_meta($post_id, 'aioseo_post_settings', true);
        if (!is_array($settings)) {
            $settings = [];
        }

        // Campos principais usados pelo AIOSEO v4
        if ($title !== '') {
            $settings['title'] = $title;
        }
        if ($description !== '') {
            $settings['description'] = $description;
        }
        if ($focus_kw !== '') {
            // AIOSEO trabalha com “keyphrase”
            $settings['keyphrase'] = $focus_kw;
        }

        update_post_meta($post_id, 'aioseo_post_settings', $settings);

        // Fallbacks antigos (não atrapalham, só somam)
        if ($title !== '') {
            update_post_meta($post_id, '_aioseo_title', $title);
        }
        if ($description !== '') {
            update_post_meta($post_id, '_aioseo_description', $description);
        }
        if ($focus_kw !== '') {
            update_post_meta($post_id, '_aioseo_focus_keyphrase', $focus_kw);
        }
    }

    // ------------------------------------------------------------------

    private static function sanitize_text(string $s, int $maxLen = 255): string
    {
        $s = wp_strip_all_tags($s, true);
        $s = trim(preg_replace('/\s+/u', ' ', $s));
        if ($maxLen > 0 && mb_strlen($s) > $maxLen) {
            $s = mb_substr($s, 0, $maxLen);
            // evita cortar no meio de entidade/palavra (leve)
            $s = rtrim($s, " \t\n\r\0\x0B,.;:-–—");
        }
        return $s;
    }
}
