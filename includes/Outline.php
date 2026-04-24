<?php

if (!defined('ABSPATH')) exit;

class AlphaSuite_Outline
{
    public static function generate(
        string $template,
        string $keyword,
        string $chosenTitle,
        string $length,
        string $locale,
        string $url = '',
        string $sourceContent = ''
    ) {
        if ($template === 'modelar_youtube') {
            $yt = AlphaSuite_Youtube::fetch_video_data($url);
            if (is_wp_error($yt)) {
                return $yt;
            }

            $prompt = AlphaSuite_Prompts::build_outline_prompt_modelar_youtube(
                $url,
                $yt,
                $chosenTitle,
                $length,
                $locale
            );
        } else {
            $prompt = AlphaSuite_Prompts::build_outline_prompt(
                $template,
                $keyword,
                $chosenTitle,
                $length,
                $locale,
                $url,
                $sourceContent
            );
        }

        $attempts = [
            $prompt,
            "A resposta anterior estava fora do formato. Retorne somente JSON UTF-8 válido, sem markdown, sem blocos de código e sem texto extra.\n\n" . $prompt,
        ];

        $last_error = null;
        $parsed = null;

        foreach ($attempts as $attempt_index => $attempt_prompt) {
            $outline = AlphaSuite_AI::complete(
                $attempt_prompt,
                ['sections' => 'array'],
                [
                    'template' => 'outline',
                ]
            );

            if (is_wp_error($outline)) {
                $last_error = $outline;
                continue;
            }

            $parsed = AlphaSuite_AI::decode_json_payload($outline);
            if (!is_array($parsed)) {
                $last_error = new WP_Error(
                    'invalid_outline_json',
                    $attempt_index === 0
                        ? 'Erro ao decodificar JSON do outline.'
                        : 'Erro ao decodificar JSON do outline após nova tentativa.',
                    [
                        'snippet' => is_string($outline) ? mb_substr($outline, 0, 800) : '',
                    ]
                );
                continue;
            }

            $sections = self::extract_sections($parsed, $length);
            if (is_wp_error($sections)) {
                $last_error = $sections;
                continue;
            }

            return [
                'sections' => $sections,
            ];
        }

        if (is_wp_error($last_error)) {
            return $last_error;
        }

        return new WP_Error(
            'invalid_outline_json',
            'Erro ao decodificar JSON do outline.',
            [
                'snippet' => is_string($parsed) ? mb_substr($parsed, 0, 800) : '',
            ]
        );
    }

    private static function extract_sections(array $outline, string $length)
    {
        $sections = [];
        if (isset($outline['sections']) && is_array($outline['sections'])) {
            $sections = $outline['sections'];
        } elseif (isset($outline[0]) && is_array($outline[0])) {
            $sections = $outline;
        }

        if (!is_array($sections) || empty($sections)) {
            return new WP_Error(
                'invalid_outline_sections',
                'Esboço inválido ou vazio.'
            );
        }

        $normalized = self::normalize_sections($sections, $length);
        if (is_wp_error($normalized)) {
            return $normalized;
        }

        return $normalized;
    }

    private static function normalize_sections(array $sections, string $length)
    {
        $cfg = ['min_sections' => 1, 'max_sections' => 8];
        if (class_exists('AlphaSuite_Prompts')) {
            $cfg = AlphaSuite_Prompts::outline_config($length);
        }

        $max_sections = max(1, (int) ($cfg['max_sections'] ?? 8));
        $min_sections = max(1, (int) ($cfg['min_sections'] ?? 1));

        $normalized = [];
        $h2_index = 1;

        foreach ($sections as $sec) {
            if (!is_array($sec)) {
                $sec = [
                    'heading' => (string) $sec,
                    'level'   => 'h2',
                ];
            }

            $heading = trim((string) ($sec['heading'] ?? ''));
            $paragraph = trim((string) ($sec['paragraph'] ?? ''));
            $bullets = $sec['bullets'] ?? [];
            $children = is_array($sec['children'] ?? null) ? $sec['children'] : [];

            if ($heading === '' && $paragraph === '' && empty($bullets) && empty($children)) {
                continue;
            }

            if ($heading === '') {
                $heading = 'Seção ' . $h2_index;
            }

            $sec['heading'] = $heading;
            $sec['level'] = 'h2';
            $sec['id'] = isset($sec['id']) && $sec['id'] !== '' ? (string) $sec['id'] : (string) $h2_index;
            $sec['children'] = [];

            $child_index = 1;
            foreach (array_slice($children, 0, 1) as $child) {
                if (!is_array($child)) {
                    $child = [
                        'heading' => (string) $child,
                        'level'   => 'h3',
                    ];
                }

                $child_heading = trim((string) ($child['heading'] ?? ''));
                $child_paragraph = trim((string) ($child['paragraph'] ?? ''));
                if ($child_heading === '' && $child_paragraph === '') {
                    continue;
                }

                if ($child_heading === '') {
                    $child_heading = $heading . ' - ' . $child_index;
                }

                $child['heading'] = $child_heading;
                $child['level'] = 'h3';
                $child['id'] = isset($child['id']) && $child['id'] !== '' ? (string) $child['id'] : ($sec['id'] . '.' . $child_index);
                $sec['children'][] = $child;
                $child_index++;
            }

            $normalized[] = $sec;
            $h2_index++;

            if (count($normalized) >= $max_sections) {
                break;
            }
        }

        if (count($normalized) < $min_sections) {
            return new WP_Error(
                'invalid_outline_sections',
                'Esboço gerado fora do formato esperado.'
            );
        }

        return $normalized;
    }
}
