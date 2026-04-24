<?php
// includes/orion/Templates.php
if (!defined('ABSPATH')) exit;

/**
 * Cadastro de modelos (templates) do Órion + migração.
 *
 * - Core (imutáveis): article, modelar_youtube
 * - Usuário pode criar modelos custom.
 *
 * Storage (option):
 *   pga_orion_templates = [
 *     'article' => ['label'=>'Artigo', 'builtin'=>1, 'enabled'=>1],
 *     'modelar_youtube' => ['label'=>'Modelar vídeo do YouTube', 'builtin'=>1, 'enabled'=>1],
 *     'receitas' => ['label'=>'Receitas', 'builtin'=>0, 'enabled'=>1],
 *   ]
 */
class AlphaSuite_Orion_Templates
{
    const OPTION = 'pga_orion_templates';

    public static function builtins(): array
    {
        return [
            'article' => [
                'label'      => __('Artigo', 'alpha-suite'),
                'enabled'    => 1,
                'builtin'    => 1,
                'is_default' => 1,
            ],
            'modelar_youtube' => [
                'label'      => __('Modelar YouTube', 'alpha-suite'),
                'enabled'    => 1,
                'builtin'    => 1,
                'is_default' => 1,
            ],
            'rss' => [
                'label'      => __('Modelar RSS', 'alpha-suite'),
                'enabled'    => 1,
                'builtin'    => 1,
                'is_default' => 1,
            ],
            'ryan' => [
                'label'      => __('Ryan Nascimento', 'alpha-suite'),
                'enabled'    => 1,
                'builtin'    => 1,
                'is_default' => 1,
            ],
        ];
    }


    public static function get_all(): array
    {
        $saved = get_option(self::OPTION, []);
        $saved = is_array($saved) ? $saved : [];

        // garante formato
        $norm = [];
        foreach ($saved as $slug => $row) {
            $slug = sanitize_key($slug);
            if (!$slug) continue;

            $row = is_array($row) ? $row : [];

            $norm[$slug] = [
                'label'      => sanitize_text_field($row['label'] ?? $slug),
                'enabled'    => !empty($row['enabled']) ? 1 : 0,
                'builtin'    => !empty($row['builtin']) ? 1 : 0,
                'is_default' => !empty($row['is_default']) ? 1 : 0, // ✅ AQUI
            ];
        }

        // builtin sempre por cima e sempre enabled=1
        $all = self::builtins();
        foreach ($norm as $slug => $row) {
            if (!isset($all[$slug])) {
                $row['builtin'] = 0;
                $all[$slug] = $row;
            } else {
                // ✅ se existir salvo pro builtin, respeita o is_default salvo
                $all[$slug]['is_default'] = !empty($row['is_default']) ? 1 : 0;
            }
        }

        // força builtins
        $all['article']['enabled'] = 1;
        $all['article']['builtin'] = 1;
        $all['modelar_youtube']['enabled'] = 1;
        $all['modelar_youtube']['builtin'] = 1;
        $all['rss']['builtin'] = 1;
        $all['rss']['enabled'] = 1;
        $all['ryan']['builtin'] = 1;
        $all['ryan']['enabled'] = 1;

        // ✅ regra: se for default, enabled = 1
        foreach ($all as $slug => $row) {
            if (!empty($row['is_default'])) {
                $all[$slug]['enabled'] = 1;
            }
        }

        return $all;
    }

    public static function defaults(): array
    {
        return [
            'article' => [
                'label'      => __('Artigo', 'alpha-suite'),
                'builtin'    => 1,
                'enabled'    => 1,
                'is_default' => 1,
            ],
            'modelar_youtube' => [
                'label'      => __('Modelar vídeo do YouTube', 'alpha-suite'),
                'builtin'    => 1,
                'enabled'    => 1,
                'is_default' => 1,
            ],
            'rss' => [
                'label'      => __('Modelar RSS', 'alpha-suite'),
                'builtin'    => 1,
                'enabled'    => 1,
                'is_default' => 1,
            ],
            'ryan' => [
                'label'      => __('Ryan', 'alpha-suite'),
                'builtin'    => 1,
                'enabled'    => 1,
                'is_default' => 1,
            ],
        ];
    }

    public static function get_enabled(): array
    {
        $all = self::get_all(); // ou get_option etc

        // ✅ remove global sempre
        unset($all['global']);

        // filtra enabled...
        $out = [];
        foreach ($all as $slug => $row) {
            if (!empty($row['enabled'])) $out[$slug] = $row;
        }

        // garante core
        if (empty($out['article'])) $out['article'] = ['label' => __('Artigo (padrão)', 'alpha-suite'), 'enabled' => 1, 'builtin' => 1];
        if (empty($out['modelar_youtube'])) $out['modelar_youtube'] = ['label' => __('Modelar YouTube', 'alpha-suite'), 'enabled' => 1, 'builtin' => 1];
        if (empty($out['rss'])) $out['rss'] = ['label' => __('Modelar RSS', 'alpha-suite'), 'enabled' => 1, 'builtin' => 1];
        if (empty($out['ryan'])) $out['ryan'] = ['label' => __('Ryan', 'alpha-suite'), 'enabled' => 1, 'builtin' => 1];

        return $out;
    }


    public static function save_from_post($raw): void
    {
        $raw = is_array($raw) ? $raw : [];

        $builtins = self::builtins();
        $out = $builtins; // começa pelos obrigatórios

        foreach ($raw as $slug => $row) {
            $slug = sanitize_key($slug);
            if (!$slug) continue;

            $row = is_array($row) ? $row : [];

            $label      = sanitize_text_field($row['label'] ?? $slug);
            $enabled    = !empty($row['enabled']) ? 1 : 0;
            $is_default = !empty($row['is_default']) ? 1 : 0;

            // se for padrão, tem que estar ativo
            if ($is_default) $enabled = 1;

            if (isset($builtins[$slug])) {
                // ✅ builtin: mantém enabled=1 e builtin=1, mas salva is_default
                $out[$slug] = [
                    'label'      => $builtins[$slug]['label'], // ou $label se quiser permitir renomear
                    'enabled'    => 1,
                    'builtin'    => 1,
                    'is_default' => $is_default,
                ];
                continue;
            }

            $out[$slug] = [
                'label'      => $label,
                'enabled'    => $enabled,
                'builtin'    => 0,
                'is_default' => $is_default,
            ];
        }

        update_option(self::OPTION, $out, false);
    }
}
