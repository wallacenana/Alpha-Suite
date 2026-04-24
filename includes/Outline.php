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
        string $sourceContent = '',
        array $idea = []
    ) {
        if (empty($idea)) {
            $idea = self::build_idea_brief(
                $template,
                $keyword,
                $chosenTitle,
                $length,
                $locale,
                $url,
                $sourceContent
            );
        }

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
                $locale,
                $idea
            );
        } else {
            $prompt = AlphaSuite_Prompts::build_outline_prompt(
                $template,
                $keyword,
                $chosenTitle,
                $length,
                $locale,
                $url,
                $sourceContent,
                $idea
            );
        }

        $outline = AlphaSuite_AI::complete(
            $prompt,
            ['sections' => 'array'],
            [
                'template' => 'outline',
            ]
        );

        if (is_wp_error($outline)) {
            return [
                'sections' => self::fallback_outline_from_idea($idea, $length),
            ];
        }

        $parsed = AlphaSuite_AI::decode_json_payload($outline);
        if (!is_array($parsed)) {
            return [
                'sections' => self::fallback_outline_from_idea($idea, $length),
            ];
        }

        $sections = self::extract_sections($parsed, $length, $idea);
        if (is_wp_error($sections)) {
            return [
                'sections' => self::fallback_outline_from_idea($idea, $length),
            ];
        }

        return [
            'sections' => $sections,
        ];
    }

    public static function build_idea_brief(
        string $template,
        string $keyword,
        string $chosenTitle,
        string $length,
        string $locale,
        string $url = '',
        string $sourceContent = ''
    ): array {
        $prompt = AlphaSuite_Prompts::build_idea_prompt(
            $template,
            $keyword,
            $chosenTitle,
            $length,
            $locale,
            $url,
            $sourceContent
        );

        $raw = AlphaSuite_AI::complete(
            $prompt,
            ['section_plan' => 'array'],
            [
                'template' => 'idea',
                'api_mode' => 'chat_completions',
                'max_tokens' => 900,
                'temperature' => 0.2,
            ]
        );

        if (is_wp_error($raw)) {
            return self::fallback_idea_brief($template, $keyword, $chosenTitle, $length);
        }

        $parsed = AlphaSuite_AI::decode_json_payload($raw);
        if (!is_array($parsed)) {
            return self::fallback_idea_brief($template, $keyword, $chosenTitle, $length);
        }

        return self::normalize_idea_brief($parsed, $template, $keyword, $chosenTitle, $length);
    }

    private static function normalize_idea_brief(array $idea, string $template, string $keyword, string $chosenTitle, string $length): array
    {
        $fallback = self::fallback_idea_brief($template, $keyword, $chosenTitle, $length);

        foreach (['angle', 'intent', 'core_problem', 'reader_promise', 'tone'] as $key) {
            $value = trim((string)($idea[$key] ?? ''));
            if ($value !== '') {
                $fallback[$key] = $value;
            }
        }

        foreach (['must_cover', 'avoid'] as $key) {
            if (!empty($idea[$key]) && is_array($idea[$key])) {
                $fallback[$key] = array_values(array_filter(array_map('trim', $idea[$key])));
            }
        }

        if (!empty($idea['section_plan']) && is_array($idea['section_plan'])) {
            $plan = [];
            foreach ($idea['section_plan'] as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $heading = trim((string)($item['heading'] ?? ''));
                if ($heading === '') {
                    continue;
                }

                $plan[] = [
                    'heading' => $heading,
                    'purpose' => trim((string)($item['purpose'] ?? '')),
                    'level'   => strtolower(trim((string)($item['level'] ?? 'h2'))) === 'h3' ? 'h3' : 'h2',
                ];
            }

            if ($plan) {
                $cfg = class_exists('AlphaSuite_Prompts') ? AlphaSuite_Prompts::outline_config($length) : ['max_sections' => 8];
                $fallback['section_plan'] = array_slice($plan, 0, max(1, (int)($cfg['max_sections'] ?? 8)));
            }
        }

        return $fallback;
    }

    private static function fallback_idea_brief(string $template, string $keyword, string $chosenTitle, string $length): array
    {
        $topic = trim($chosenTitle !== '' ? $chosenTitle : $keyword);
        if ($topic === '') {
            $topic = 'o tema';
        }

        $intent = 'informational';
        if ($template === 'rss') {
            $intent = 'news';
        } elseif ($template === 'modelar_youtube') {
            $intent = 'explanatory';
        }

        $plan = self::fallback_section_plan($template, $topic, $keyword, $length);

        return [
            'angle' => $topic,
            'intent' => $intent,
            'core_problem' => $keyword !== '' ? $keyword : $topic,
            'reader_promise' => 'Entregar um artigo claro, util e menos generico.',
            'tone' => 'editorial',
            'must_cover' => [
                'contexto do tema',
                'aplicacao pratica',
                'erros comuns',
                'criticos de decisao',
            ],
            'avoid' => [
                'frases vagas',
                'headings genericos',
                'repeticao sem ganho informativo',
            ],
            'section_plan' => $plan,
        ];
    }

    private static function fallback_section_plan(string $template, string $topic, string $keyword, string $length): array
    {
        $max = 5;
        $cfg = class_exists('AlphaSuite_Prompts') ? AlphaSuite_Prompts::outline_config($length) : [];
        if (!empty($cfg['max_sections'])) {
            $max = max(1, (int) $cfg['max_sections']);
        }

        if ($template === 'rss') {
            $items = [
                ['heading' => 'Fato principal e contexto', 'purpose' => 'Apresente o que aconteceu e por que importa.', 'level' => 'h2'],
                ['heading' => 'O que a noticia muda agora', 'purpose' => 'Explique o impacto imediato e os desdobramentos.', 'level' => 'h2'],
                ['heading' => 'Pontos que precisam de atencao', 'purpose' => 'Mostre riscos, numeros ou detalhes relevantes.', 'level' => 'h2'],
                ['heading' => 'O que observar em seguida', 'purpose' => 'Feche com a leitura de futuro da noticia.', 'level' => 'h2'],
            ];
        } elseif ($template === 'modelar_youtube') {
            $items = [
                ['heading' => 'Panorama do tema', 'purpose' => 'Abra com o principal aprendizado do material.', 'level' => 'h2'],
                ['heading' => 'Como funciona na pratica', 'purpose' => 'Torne o tema aplicavel com passos claros.', 'level' => 'h2'],
                ['heading' => 'Erros e armadilhas', 'purpose' => 'Mostre o que derruba o resultado.', 'level' => 'h2'],
                ['heading' => 'Critérios para decidir', 'purpose' => 'Ajude o leitor a escolher com mais segurança.', 'level' => 'h2'],
                ['heading' => 'Próximos passos', 'purpose' => 'Finalize com uma orientação objetiva.', 'level' => 'h2'],
            ];
        } else {
            $items = [
                ['heading' => "Panorama de {$topic}", 'purpose' => 'Explique o tema sem cair em generalidades.', 'level' => 'h2'],
                ['heading' => "O que realmente importa em {$keyword}", 'purpose' => 'Aponte a dor central e o que o leitor precisa saber.', 'level' => 'h2'],
                ['heading' => 'Como aplicar na pratica', 'purpose' => 'Transforme a ideia em passos úteis.', 'level' => 'h2'],
                ['heading' => 'Erros e atalhos que prejudicam', 'purpose' => 'Mostre armadilhas e escolhas ruins.', 'level' => 'h2'],
                ['heading' => 'Quando vale avançar', 'purpose' => 'Feche com critérios claros de decisão.', 'level' => 'h2'],
            ];

            if ($max >= 7) {
                $items[] = ['heading' => 'Comparacoes e alternativas', 'purpose' => 'Ajude a diferenciar caminhos possíveis.', 'level' => 'h2'];
                $items[] = ['heading' => 'Checklist final', 'purpose' => 'Resuma o raciocínio em uma leitura final útil.', 'level' => 'h2'];
            }
        }

        return array_slice($items, 0, $max);
    }

    private static function extract_sections(array $outline, string $length, array $idea = [])
    {
        $sections = [];
        if (isset($outline['sections']) && is_array($outline['sections'])) {
            $sections = $outline['sections'];
        } elseif (isset($outline[0]) && is_array($outline[0])) {
            $sections = $outline;
        }

        if (!is_array($sections) || empty($sections)) {
            return new WP_Error('invalid_outline_sections', 'EsboÃ§o invÃ¡lido ou vazio.');
        }

        $normalized = self::normalize_sections($sections, $length, $idea);
        if (is_wp_error($normalized)) {
            return $normalized;
        }

        return $normalized;
    }

    private static function normalize_sections(array $sections, string $length, array $idea = [])
    {
        $cfg = ['min_sections' => 1, 'max_sections' => 8];
        if (class_exists('AlphaSuite_Prompts')) {
            $cfg = AlphaSuite_Prompts::outline_config($length);
        }

        $max_sections = max(1, (int) ($cfg['max_sections'] ?? 8));
        $min_sections = max(1, (int) ($cfg['min_sections'] ?? 1));
        $plan = self::fallback_section_plan('article', trim((string)($idea['angle'] ?? '')), trim((string)($idea['core_problem'] ?? '')), $length);
        if (!empty($idea['section_plan']) && is_array($idea['section_plan'])) {
            $plan = array_values($idea['section_plan']);
        }

        $normalized = [];
        $h2_index = 1;
        $seen = [];

        foreach ($sections as $i => $sec) {
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
            $planItem = $plan[$i] ?? ($plan[$h2_index - 1] ?? []);

            if ($heading === '' || self::is_generic_heading($heading)) {
                $heading = trim((string)($planItem['heading'] ?? ''));
            }

            if ($heading === '') {
                $heading = self::default_heading_from_idea($idea, $h2_index);
            }

            $heading = self::unique_heading($heading, $seen, $h2_index);

            if ($paragraph === '' && !empty($planItem['purpose'])) {
                $paragraph = trim((string) $planItem['purpose']);
            }

            if (empty($bullets) && !empty($planItem['bullets']) && is_array($planItem['bullets'])) {
                $bullets = $planItem['bullets'];
            }

            $sec['heading'] = $heading;
            $sec['level'] = 'h2';
            $sec['id'] = isset($sec['id']) && $sec['id'] !== '' ? (string) $sec['id'] : (string) $h2_index;
            $sec['paragraph'] = $paragraph;
            $sec['bullets'] = is_array($bullets) ? array_values($bullets) : [];
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
                if ($child_heading === '' || self::is_generic_heading($child_heading)) {
                    $child_heading = $heading . ' - ' . $child_index;
                }

                if ($child_heading === '' && $child_paragraph === '') {
                    continue;
                }

                $child['heading'] = self::clean_heading($child_heading);
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
            $fallback = self::fallback_outline_from_idea($idea, $length);
            if (!empty($fallback)) {
                return $fallback;
            }

            return new WP_Error('invalid_outline_sections', 'EsboÃ§o gerado fora do formato esperado.');
        }

        $vistoried = self::vistoria_sections($normalized, $idea, $length);
        if (!empty($vistoried)) {
            $normalized = $vistoried;
        }

        return $normalized;
    }

    private static function vistoria_sections(array $sections, array $idea, string $length): array
    {
        $plan = [];
        if (!empty($idea['section_plan']) && is_array($idea['section_plan'])) {
            $plan = array_values($idea['section_plan']);
        } else {
            $plan = self::fallback_section_plan(
                'article',
                trim((string)($idea['angle'] ?? '')),
                trim((string)($idea['core_problem'] ?? '')),
                $length
            );
        }

        $seen = [];
        foreach ($sections as $i => $sec) {
            if (!is_array($sec)) {
                continue;
            }

            $planItem = $plan[$i] ?? [];
            $heading = trim((string)($sec['heading'] ?? ''));
            if ($heading === '' || self::is_generic_heading($heading)) {
                $heading = trim((string)($planItem['heading'] ?? ''));
            }
            if ($heading === '') {
                $heading = self::default_heading_from_idea($idea, $i + 1);
            }
            $heading = self::unique_heading($heading, $seen, $i + 1);
            $sec['heading'] = $heading;

            if (trim((string)($sec['paragraph'] ?? '')) === '' && !empty($planItem['purpose'])) {
                $sec['paragraph'] = trim((string) $planItem['purpose']);
            }

            $sections[$i] = $sec;
        }

        return $sections;
    }

    private static function fallback_outline_from_idea(array $idea, string $length): array
    {
        $plan = [];
        if (!empty($idea['section_plan']) && is_array($idea['section_plan'])) {
            $plan = array_values($idea['section_plan']);
        } else {
            $plan = self::fallback_section_plan(
                'article',
                trim((string)($idea['angle'] ?? '')),
                trim((string)($idea['core_problem'] ?? '')),
                $length
            );
        }

        $sections = [];
        $idx = 1;
        foreach ($plan as $item) {
            if (!is_array($item)) {
                continue;
            }

            $heading = trim((string)($item['heading'] ?? ''));
            if ($heading === '') {
                continue;
            }

            $sections[] = [
                'id' => (string) $idx,
                'level' => 'h2',
                'heading' => $heading,
                'paragraph' => trim((string)($item['purpose'] ?? '')),
                'bullets' => [],
                'children' => [],
            ];
            $idx++;
        }

        if (empty($sections)) {
            $sections[] = [
                'id' => '1',
                'level' => 'h2',
                'heading' => 'Panorama do tema',
                'paragraph' => 'Estrutura base para organizar o conteudo.',
                'bullets' => [],
                'children' => [],
            ];
        }

        return $sections;
    }

    private static function is_generic_heading(string $heading): bool
    {
        $heading = self::clean_heading($heading);
        if ($heading === '') {
            return true;
        }

        $normalized = function_exists('remove_accents') ? remove_accents($heading) : $heading;
        $normalized = strtolower(trim(preg_replace('/\s+/', ' ', $normalized)));

        $generic = [
            'introducao',
            'introducao ao tema',
            'conclusao',
            'resumo',
            'visao geral',
            'panorama',
            'dicas',
            'beneficios',
            'erros',
            'passo a passo',
            'guia',
            'o que e',
            'como fazer',
            'perguntas frequentes',
            'faq',
            'melhores praticas',
            'como funciona',
            'explicacao',
            'contexto',
            'detalhes',
        ];

        if (in_array($normalized, $generic, true)) {
            return true;
        }

        foreach ($generic as $term) {
            if ($normalized === $term) {
                return true;
            }
        }

        if (mb_strlen($normalized) <= 12 && preg_match('/^(dicas|erros|guia|resumo|faq)$/', $normalized)) {
            return true;
        }

        return false;
    }

    private static function default_heading_from_idea(array $idea, int $index): string
    {
        $topic = trim((string)($idea['angle'] ?? ''));
        if ($topic === '') {
            $topic = trim((string)($idea['core_problem'] ?? ''));
        }
        if ($topic === '') {
            $topic = 'o tema';
        }

        $prefixes = [
            1 => 'Panorama de ',
            2 => 'O que realmente importa em ',
            3 => 'Como aplicar ',
            4 => 'Erros e armadilhas em ',
            5 => 'Critérios para decidir sobre ',
            6 => 'Comparacoes e alternativas para ',
            7 => 'Checklist final de ',
        ];

        $prefix = $prefixes[$index] ?? 'Mais sobre ';
        return self::clean_heading($prefix . $topic);
    }

    private static function clean_heading(string $heading): string
    {
        $heading = trim(wp_strip_all_tags($heading));
        $heading = preg_replace('/\s+/', ' ', $heading);
        return trim((string) $heading);
    }

    private static function unique_heading(string $heading, array &$seen, int $index): string
    {
        $heading = self::clean_heading($heading);
        if ($heading === '') {
            $heading = 'Seção ' . $index;
        }

        $key = function_exists('remove_accents') ? remove_accents($heading) : $heading;
        $key = strtolower(trim($key));

        if (empty($seen[$key])) {
            $seen[$key] = 1;
            return $heading;
        }

        $seen[$key]++;
        return $heading . ' ' . $seen[$key];
    }
}
