<?php
if (!defined('ABSPATH')) exit;

class AlphaSuite_AI
{
    // ------------------------------------------------------------
    // BASE: leitura de settings (1 lugar só)
    // ------------------------------------------------------------
    private static function settings(): array
    {
        // Fallback legado (antes do AlphaSuite_Settings existir)
        if (!class_exists('AlphaSuite_Settings')) {
            return [
                'apis' => [
                    'openai' => [
                        'key'         => trim(get_option('alpha_orion_posts_openai_key', '')),
                        'model_text'  => get_option('alpha_orion_posts_model_text', 'gpt-4o-mini'),
                        'model_image' => get_option('alpha_orion_posts_model_image', 'gpt-image-1'),
                        'temperature' => (float) get_option('alpha_orion_posts_temperature', 0.6),
                        'max_tokens'  => (int) get_option('alpha_orion_posts_max_tokens', 6000),
                    ],
                    // Gemini não existia no legado → vazio por padrão
                    'gemini' => [
                        'key'         => '',
                        'model_text'  => 'gemini-2.5-flash-lite',
                        'temperature' => 0.6,
                        'max_tokens'  => 6000,
                    ],
                ],
                'orion_posts' => [
                    'text_provider'   => 'openai',
                    'images_provider' => 'pollinations',
                ],
            ];
        }

        return AlphaSuite_Settings::get();
    }

    public static function resolve_provider(string $provider)
    {
        // Normaliza
        $provider = strtolower(trim($provider));

        $map = [
            'openai'     => 'AlphaSuite_OpenAI',
            'gemini'     => 'AlphaSuite_Gemini',
            'perplexity' => 'AlphaSuite_Perplexity',
            'claude'     => 'AlphaSuite_Claude',
            'mistral'    => 'AlphaSuite_Mistral',
            'cohere'     => 'AlphaSuite_Cohere',
            'manus'      => 'AlphaSuite_Manus',
            'pollinations' => 'AlphaSuite_Pollinations',
        ];


        if (!isset($map[$provider])) {
            return new WP_Error('pga_invalid_provider', "Provider desconhecido: $provider");
        }

        $class = $map[$provider];

        if (!class_exists($class)) {
            return new WP_Error('pga_missing_class', "Classe do provider não encontrada: $class");
        }

        return $class;
    }


    /**
     * Provider de TEXTO (OpenAI, Gemini...)
     * - Lê de: pga_settings[orion_posts][text_provider]
     * - Default: openai
     */
    public static function get_text_provider($format = 'orion_posts'): string
    {
        $provider = 'openai';

        if (class_exists('AlphaSuite_Settings')) {
            $opts   = AlphaSuite_Settings::get();
            $bucket = $opts[$format] ?? [];

            if (!empty($bucket['text_provider'])) {
                $candidate = (string)$bucket['text_provider'];
                $provider = $candidate;
            }
        }

        return $provider;
    }

    /**
     * @param string $prompt
     * @param array  $schema
     * @param array  $args
     * @return array|WP_Error
     *
     * @psalm-suppress UndefinedMethod
     * @phpstan-ignore-next-line
     */
    public static function complete(string $prompt, array $schema = [], array $args = [])
    {
        $format = (string)($args['format'] ?? '');

        $provider = $args['provider']
            ?? self::get_text_provider($format ?: 'orion_posts');

        $ok = self::ensure_text_provider($provider);
        if (is_wp_error($ok)) {
            return $ok;
        }
        /**
         * @var class-string<AlphaSuite_OpenAI|AlphaSuite_Gemini>
         */
        $class = self::resolve_provider($provider);
        if (is_wp_error($class)) {
            return $class;
        }

        // 🔹 args vão crus, sem mexer
        return $class::complete($prompt, $schema, $args);
    }

    public static function extract_json_fragment(string $raw): string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return '';
        }

        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);

        if (preg_match('/```(?:json)?\s*(.*?)\s*```/is', $raw, $m)) {
            $raw = trim((string) $m[1]);
        }

        $len = strlen($raw);
        $start = -1;
        $open = '';
        $close = '';

        for ($i = 0; $i < $len; $i++) {
            $ch = $raw[$i];
            if ($ch === '{') {
                $start = $i;
                $open = '{';
                $close = '}';
                break;
            }
            if ($ch === '[') {
                $start = $i;
                $open = '[';
                $close = ']';
                break;
            }
        }

        if ($start < 0) {
            return $raw;
        }

        $depth = 0;
        $in_string = false;
        $escape = false;

        for ($i = $start; $i < $len; $i++) {
            $ch = $raw[$i];

            if ($in_string) {
                if ($escape) {
                    $escape = false;
                    continue;
                }

                if ($ch === '\\') {
                    $escape = true;
                    continue;
                }

                if ($ch === '"') {
                    $in_string = false;
                }

                continue;
            }

            if ($ch === '"') {
                $in_string = true;
                continue;
            }

            if ($ch === $open) {
                $depth++;
                continue;
            }

            if ($ch === $close) {
                $depth--;
                if ($depth === 0) {
                    return substr($raw, $start, $i - $start + 1);
                }
            }
        }

        return $raw;
    }

    public static function decode_json_payload($raw)
    {
        if (is_array($raw)) {
            if (isset($raw['content']) && is_string($raw['content'])) {
                $inner = self::decode_json_payload($raw['content']);
                if (is_array($inner)) {
                    return $inner;
                }
            }

            return $raw;
        }

        if (is_object($raw)) {
            $raw = json_decode(wp_json_encode($raw), true);
            if (is_array($raw)) {
                return self::decode_json_payload($raw);
            }

            return null;
        }

        if (!is_string($raw)) {
            return null;
        }

        $candidates = [];
        $fragment = self::extract_json_fragment($raw);

        if ($fragment !== '') {
            $candidates[] = $fragment;
        }

        $candidates[] = trim($raw);

        $seen = [];
        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate === '' || isset($seen[$candidate])) {
                continue;
            }
            $seen[$candidate] = true;

            $decoded = json_decode($candidate, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                if (isset($decoded['content']) && is_string($decoded['content'])) {
                    $inner = self::decode_json_payload($decoded['content']);
                    if (is_array($inner)) {
                        return $inner;
                    }
                }

                return $decoded;
            }
        }

        return null;
    }

    public static function translate(string $text, string $targetLang)
    {
        $prompt = "Traduza o conteúdo que será enviado, para o idioma {$targetLang}.\n"
            . "\n"
            . "Regras obrigatórias:\n"
            . "- Retorne apenas o texto traduzido.\n"
            . "- Não adicione comentários.\n"
            . "- Não explique nada.\n"
            . "- Não use markdown.\n"
            . "- Preserve exatamente todas as tags HTML existentes.\n"
            . "- Não altere atributos HTML.\n"
            . "- Não adicione nem remova elementos.\n"
            . "\n"
            . "Conteúdo para ser traduzido:\n"
            . $text;

        return self::complete($prompt);
    }

    public static function embeddings(array $texts, array $args = [])
    {
        $format = (string)($args['format'] ?? '');

        $provider = $args['provider']
            ?? self::get_text_provider($format ?: 'orion_posts');

        $ok = self::ensure_text_provider($provider);
        if (is_wp_error($ok)) {
            return $ok;
        }

        $class = self::resolve_provider($provider);
        if (is_wp_error($class)) {
            return $class;
        }

        if (!method_exists($class, 'embeddings')) {
            return new WP_Error(
                'embedding_not_supported',
                'Provider não suporta embeddings em lote.'
            );
        }

        return $class::embeddings($texts, $args);
    }

    /**
     * Provider de IMAGEM (Pollinations, OpenAI, Pexels, Unsplash...)
     * - Lê de: pga_settings[orion_posts][images_provider]
     * - Default: pollinations
     */
    public static function get_image_provider(array $overrideArgs = []): string
    {
        if (!empty($overrideArgs['image_provider'])) {
            return (string) $overrideArgs['image_provider'];
        }

        $opts  = self::settings();
        $orion = $opts['orion_posts'] ?? [];

        $prov = isset($orion['images_provider']) ? (string) $orion['images_provider'] : 'pollinations';

        if (!in_array($prov, ['pollinations', 'openai', 'pexels', 'unsplash', 'none'], true)) {
            $prov = 'pollinations';
        }

        return $prov;
    }

    /**
     * Garante que o provider de TEXTO está configurado.
     * Se não tiver chave → WP_Error.
     *
     * @param string $provider
     * @return true|WP_Error
     */
    private static function ensure_text_provider(string $provider)
    {
        $class = self::resolve_provider($provider);

        if (is_wp_error($class)) {
            return $class;
        }

        return true;
    }

    public static function faq(array $args)
    {
        $provider = $args['provider'] ?? self::get_text_provider();

        $ok = self::ensure_text_provider($provider);
        if (is_wp_error($ok)) return $ok;

        $class = self::resolve_provider($provider);
        if (is_wp_error($class)) return $class;

        $context = trim((string)($args['context'] ?? ''));
        if ($context === '') {
            return new WP_Error('pga_faq_context', 'Contexto inválida para FAQ.');
        }

        $keyword = trim((string)($args['keyword'] ?? ''));
        if ($keyword === '') {
            return new WP_Error('pga_faq_kw', 'Keyword inválida para FAQ.');
        }

        $qty    = min(5, max(1, (int)($args['qty'] ?? 3)));
        $locale = $args['locale'] ?? 'pt_BR';

        // PROMPT enxuto e determinístico
        $prompt = AlphaSuite_Prompts::build_faq_prompt([
            'keyword' => $keyword,
            'qty'     => $qty,
            'locale'  => $locale,
            'context'  => $context,
        ]);

        // SCHEMA força JSON-LD válido
        $schema = [
            '@context'   => 'string',
            '@type'      => 'string',
            'mainEntity' => [
                [
                    '@type' => 'string',
                    'name'  => 'string',
                    'acceptedAnswer' => [
                        '@type' => 'string',
                        'text'  => 'string',
                    ],
                ],
            ],
        ];

        $result = $class::complete($prompt, $schema);

        if (is_wp_error($result)) {
            return $result;
        }

        return $result; // JSON-LD PRONTO
    }

    // ------------------------------------------------------------
    // PONTO ÚNICO: gerar TEXTO genérico (Orion, Stories etc.)
    // ------------------------------------------------------------

    public static function titles(string $prompt, array $args = [])
    {
        $provider = $args['provider'] ?? self::get_text_provider();

        $ok = self::ensure_text_provider($provider);
        if (is_wp_error($ok)) {
            return $ok;
        }

        /**
         * @var class-string<AlphaSuite_OpenAI|AlphaSuite_Gemini> $class
         */
        $class = self::resolve_provider($provider);
        if (is_wp_error($class)) {
            return $class;
        }

        // 🔹 contrato
        $schema = [
            'titles' => 'array',
        ];

        // 🔹 opções centralizadas
        $opts = [
            'template'    => 'titles',
            'temperature' => $args['temperature'] ?? 0.6,
            'max_tokens'  => $args['max_tokens'] ?? 600,
            'provider'    => $provider,
        ];

        // 🔥 chamada única
        $resp = $class::complete($prompt, $schema, $opts);
        if (is_wp_error($resp)) {
            return $resp;
        }

        if (
            !is_array($resp) ||
            empty($resp['titles']) ||
            !is_array($resp['titles'])
        ) {
            return new WP_Error(
                'pga_titles_invalid',
                'Resposta inválida para títulos.',
                ['response' => $resp]
            );
        }

        // normalização mínima
        $titles = array_values(
            array_filter(
                array_map(
                    fn($t) => trim((string) $t),
                    $resp['titles']
                )
            )
        );

        if (!$titles) {
            return new WP_Error('pga_no_titles', 'Nenhum título retornado.');
        }

        return $titles;
    }


    public static function outline(string $prompt, array $args = [])
    {
        // 1) Descobre o provider (args > settings)
        $provider = isset($args['provider'])
            ? (string) $args['provider']
            : self::get_text_provider();

        // 2) Valida credenciais
        $ok = self::ensure_text_provider($provider);
        if (is_wp_error($ok)) {
            return $ok;
        }

    // 3) Resolve a classe do provider (OpenAI / Gemini / etc.)
        /** 
         * @var class-string<
         *    AlphaSuite_OpenAI |
         *    AlphaSuite_Gemini
         * > $class 
         */
        $class = self::resolve_provider($provider);
        if (is_wp_error($class)) {
            return $class;
        }

        return $class::complete($prompt, $args);
    }

    /**
     * Provider padrão para STORIES (pode virar opção separada no futuro)
     */
    public static function get_story_text_provider(): string
    {
        $provider = 'openai';
        if (class_exists('AlphaSuite_Settings')) {
            $opts    = AlphaSuite_Settings::get();
            $stories = $opts['stories'] ?? [];

            if (!empty($stories['text_provider'])) {
                $candidate = (string) $stories['text_provider'];
                if (in_array($candidate, ['openai', 'gemini', 'claude', 'mistral', 'cohere', 'perplexity'], true)) {
                    $provider = $candidate;
                }
            }
        }

        return $provider;
    }

    /**
     * Gera páginas de Web Stories, despachando para o provedor correto.
     *
     * @param string $prompt Prompt final já montado (Prompts::build_story_prompt_for_post)
     * @param array  $args   ['provider' => 'gemini', 'model' => '...', 'temperature' => 0.4, ...]
     */
    public static function generate_story_pages(string $prompt, array $args = [])
    {
        $provider = isset($args['provider'])
            ? (string) $args['provider']
            : self::get_story_text_provider();

        $ok = self::ensure_text_provider($provider);
        if (is_wp_error($ok)) {
            return $ok;
        }

        /**
         * @var class-string<AlphaSuite_OpenAI|AlphaSuite_Gemini> $class
         */
        $class = self::resolve_provider($provider);
        if (is_wp_error($class)) {
            return $class;
        }

        // 🔹 schema do story
        $schema = [
            'pages' => 'array'
        ];

        // 🔹 opções de geração (único lugar!)
        $opts = [
            'temperature' => $args['temperature'] ?? 0.5,
            'max_tokens'  => $args['max_tokens'] ?? 6000,
            'template'    => 'story_pages',
        ];

        // 🔥 AQUI é o ponto-chave
        $resp = $class::complete($prompt, $schema, $opts);
        if (is_wp_error($resp)) {
            return $resp;
        }

        if (!isset($resp['pages']) || !is_array($resp['pages'])) {
            return new WP_Error(
                'pga_story_invalid',
                'Resposta inválida: pages ausente ou inválido.'
            );
        }

        // normalização mínima
        $pages = [];
        foreach ($resp['pages'] as $p) {
            $pages[] = [
                'heading'  => (string)($p['heading'] ?? ''),
                'body'     => (string)($p['body'] ?? ''),
                'cta_text' => (string)($p['cta_text'] ?? ''),
                'cta_url'  => (string)($p['cta_url'] ?? ''),
                'prompt'   => (string)($p['prompt'] ?? ''),
            ];
        }

        return [
            'pages' => $pages,
        ];
    }

    public static function meta_description(string $prompt, array $args = [])
    {
        $provider = $args['provider'] ?? self::get_text_provider();

        $ok = self::ensure_text_provider($provider);
        if (is_wp_error($ok)) {
            return $ok;
        }

        /**
         * @var class-string<AlphaSuite_OpenAI|AlphaSuite_Gemini> $class
         */
        $class = self::resolve_provider($provider);
        if (is_wp_error($class)) {
            return $class;
        }

        // 🔹 opções (meta description = TEXTO LIVRE)
        $opts = [
            'template'    => 'meta_description',
            'temperature' => $args['temperature'] ?? 0.6,
            'max_tokens'  => $args['max_tokens'] ?? 300,
            'provider'    => $provider,
        ];

        // ⚠️ SEM schema
        $resp = $class::complete($prompt, [], $opts);
        if (is_wp_error($resp)) {
            return $resp;
        }

        // ---------- EXTRAÇÃO ROBUSTA ----------
        $descTxt = '';

        if (is_string($resp)) {
            $descTxt = $resp;
        } elseif (is_array($resp)) {
            $descTxt = (string)($resp['content'] ?? '');
        } elseif (is_object($resp)) {
            $descTxt = (string)($resp->content ?? '');
        }

        $descTxt = trim(wp_strip_all_tags(
            html_entity_decode($descTxt, ENT_QUOTES, 'UTF-8')
        ));

        // se vier JSON como texto
        if ($descTxt !== '' && $descTxt[0] === '{') {
            $j = json_decode($descTxt, true);
            if (is_array($j)) {
                $descTxt = (string)($j['description'] ?? $j['content'] ?? $descTxt);
            }
        }

        // remove prefixos comuns
        $descTxt = preg_replace('/^\s*(meta\s*description|description)\s*:\s*/i', '', $descTxt);

        // uma linha só
        $descTxt = preg_split("/\r\n|\r|\n/", $descTxt)[0] ?? $descTxt;
        $descTxt = trim($descTxt);

        if ($descTxt === '') {
            return new WP_Error('pga_meta_desc_empty', 'Meta description vazia.');
        }

        return $descTxt;
    }

    public static function slug(string $prompt, array $args = [])
    {
        $provider = $args['provider'] ?? self::get_text_provider();

        $ok = self::ensure_text_provider($provider);
        if (is_wp_error($ok)) {
            return $ok;
        }

        /**
         * @var class-string<AlphaSuite_OpenAI|AlphaSuite_Gemini> $class
         */
        $class = self::resolve_provider($provider);
        if (is_wp_error($class)) {
            return $class;
        }

        // 🔹 opções (slug = texto livre)
        $opts = [
            'template'    => 'slug',
            'temperature' => $args['temperature'] ?? 0.3,
            'max_tokens'  => $args['max_tokens'] ?? 120,
            'provider'    => $provider,
        ];

        // ⚠️ SEM schema
        $resp = $class::complete($prompt, [], $opts);
        if (is_wp_error($resp)) {
            return $resp;
        }

        // ---------- EXTRAÇÃO ROBUSTA ----------
        $slugTxt = '';

        if (is_string($resp)) {
            $slugTxt = $resp;
        } elseif (is_array($resp)) {
            $slugTxt = (string)($resp['content'] ?? '');
        } elseif (is_object($resp)) {
            $slugTxt = (string)($resp->content ?? '');
        }

        $slugTxt = trim(wp_strip_all_tags(
            html_entity_decode($slugTxt, ENT_QUOTES, 'UTF-8')
        ));

        // se vier algo tipo {"slug":"..."} como texto
        if ($slugTxt !== '' && $slugTxt[0] === '{') {
            $j = json_decode($slugTxt, true);
            if (is_array($j)) {
                $slugTxt = (string)($j['slug'] ?? $j['content'] ?? $slugTxt);
            }
        }

        // remove prefixos comuns
        $slugTxt = preg_replace('/^\s*(slug|post_name)\s*:\s*/i', '', $slugTxt);

        // só primeira linha
        $slugTxt = preg_split("/\r\n|\r|\n/", $slugTxt)[0] ?? $slugTxt;
        $slugTxt = trim($slugTxt);

        if ($slugTxt === '') {
            return new WP_Error('pga_slug_empty', 'Slug vazia.');
        }

        // 🔒 sanitização final
        $slug = sanitize_title($slugTxt);

        if ($slug === '') {
            return new WP_Error('pga_slug_invalid_final', 'Slug inválida após sanitização.');
        }

        return $slug;
    }

    public static function image_prompt(string $prompt, array $args = [])
    {
        $provider = $args['provider'] ?? self::get_text_provider();

        $ok = self::ensure_text_provider($provider);
        if (is_wp_error($ok)) {
            return $ok;
        }

        /**
         * @var class-string<AlphaSuite_OpenAI|AlphaSuite_Gemini> $class
         */
        $class = self::resolve_provider($provider);
        if (is_wp_error($class)) {
            return $class;
        }

        // 🔹 opções (image prompt = TEXTO LIVRE)
        $opts = [
            'template'    => 'image_prompt',
            'temperature' => $args['temperature'] ?? 0.6,
            'max_tokens'  => $args['max_tokens'] ?? 300,
            'provider'    => $provider,
        ];

        // ⚠️ SEM schema
        $resp = $class::complete($prompt, [], $opts);
        if (is_wp_error($resp)) {
            return $resp;
        }

        // ---------- EXTRAÇÃO ROBUSTA ----------
        $imgPrompt = '';

        if (is_string($resp)) {
            $imgPrompt = $resp;
        } elseif (is_array($resp)) {
            $imgPrompt = (string)($resp['content'] ?? '');
        } elseif (is_object($resp)) {
            $imgPrompt = (string)($resp->content ?? '');
        }

        $imgPrompt = trim(wp_strip_all_tags(
            html_entity_decode($imgPrompt, ENT_QUOTES, 'UTF-8')
        ));

        // se vier JSON como texto
        if ($imgPrompt !== '' && $imgPrompt[0] === '{') {
            $j = json_decode($imgPrompt, true);
            if (is_array($j)) {
                $imgPrompt = (string)($j['prompt'] ?? $j['content'] ?? $imgPrompt);
            }
        }

        // remove prefixos comuns
        $imgPrompt = preg_replace('/^\s*(image\s*prompt|prompt)\s*:\s*/i', '', $imgPrompt);

        // uma linha só
        $imgPrompt = preg_split("/\r\n|\r|\n/", $imgPrompt)[0] ?? $imgPrompt;
        $imgPrompt = trim($imgPrompt);

        if ($imgPrompt === '') {
            return new WP_Error('pga_image_prompt_empty', 'Prompt de imagem vazio.');
        }

        return $imgPrompt;
    }
}
