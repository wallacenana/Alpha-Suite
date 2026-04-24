<?php
if (!defined('ABSPATH')) exit;

class AlphaSuite_FAQ
{
    public static function render_faq_block(array $faq): string
    {
        if (
            ($faq['@type'] ?? '') !== 'FAQPage' ||
            empty($faq['mainEntity']) ||
            !is_array($faq['mainEntity'])
        ) {
            return '';
        }

        $out = [];

        // H2 – título da FAQ
        $out[] = '<!-- wp:heading {"level":2} -->';
        $out[] = '<h2>Perguntas frequentes</h2>';
        $out[] = '<!-- /wp:heading -->';

        foreach ($faq['mainEntity'] as $item) {
            $q = html_entity_decode((string)($item['name'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $a = html_entity_decode((string)($item['acceptedAnswer']['text'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');

            $q = trim($q);
            $a = trim($a);

            if ($q === '' || $a === '') {
                continue;
            }

            // Pergunta (H3)
            $out[] = '<!-- wp:heading {"level":3} -->';
            $out[] = '<h3>' . esc_html($q) . '</h3>';
            $out[] = '<!-- /wp:heading -->';

            // Resposta (parágrafo)
            $out[] = '<!-- wp:paragraph -->';
            $out[] = '<p>' . esc_html($a) . '</p>';
            $out[] = '<!-- /wp:paragraph -->';
        }

        // JSON-LD como bloco HTML isolado
        $out[] = '<!-- wp:html -->';
        $out[] = '<script type="application/ld+json">'
            . wp_json_encode($faq, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            . '</script>';
        $out[] = '<!-- /wp:html -->';

        return implode("\n", $out);
    }
}
