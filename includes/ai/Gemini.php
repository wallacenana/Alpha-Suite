<?php
if (!defined('ABSPATH')) exit;

class AlphaSuite_Gemini
{

    // ---- Lê config do plugin (GEMINI) ----
    private static function cfg(): array
    {
        // Fallback legado (sem AlphaSuite_Settings)
        if (!class_exists('AlphaSuite_Settings')) {
            return [
                'key'         => trim(get_option('alpha_orion_posts_gemini_key', '')),
                'model_text'  => get_option('alpha_orion_posts_gemini_model_text', 'gemini-1.5-pro'),
                'temperature' => (float) get_option('alpha_orion_posts_gemini_temperature', 0.6),
                'max_tokens'  => (int) get_option('alpha_orion_posts_gemini_max_tokens', 6000),
                'timeout'     => 60,
            ];
        }

        $opt = AlphaSuite_Settings::get();
        $ge  = $opt['apis']['gemini'] ?? [];

        return [
            'key'         => trim((string) ($ge['key']         ?? '')),
            'model_text'  => (string)      ($ge['model_text']  ?? 'gemini-1.5-pro'),
            'temperature' => (float)       ($ge['temperature'] ?? 0.6),
            'max_tokens'  => (int)         ($ge['max_tokens']  ?? 6000),
            'timeout'     => 60,
        ];
    }

    public static function is_configured(): bool
    {
        $c = self::cfg();
        return $c['key'] !== '';
    }

    /**
     * Helper único para chamar o Gemini.
     *
     * @param string $system     Prompt de sistema (regras, formato, etc).
     * @param string $userPrompt Prompt do usuário.
     * @param array  $args       ['model' => '...', 'temperature' => 0.7, 'max_tokens' => 4000, ...]
     *
     * @return string|WP_Error   Texto bruto retornado pelo modelo (concatenado) OU erro.
     */
    private static function call_gemini(string $prompt, array $args)
    {
        $body = [
            'contents' => [
                [
                    'role'  => 'user',
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature'     => $args['temperature'],
                'maxOutputTokens' => $args['max_tokens'],
            ],
        ];

        $endpoint = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
            rawurlencode($args['model']),
            rawurlencode($args['key'])
        );

        $res = wp_remote_post($endpoint, [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode($body),
            'timeout' => $args['timeout'],
        ]);

        if (is_wp_error($res)) return $res;

        $code = wp_remote_retrieve_response_code($res);
        $raw  = wp_remote_retrieve_body($res);

        if ($code < 200 || $code >= 300) {
            return new WP_Error('pga_gemini_http', 'Erro Gemini', [
                'http_code' => $code,
                'body'      => substr($raw, 0, 800),
            ]);
        }

        $json = json_decode($raw, true);

        $txt = '';
        if (!empty($json['candidates'][0]['content']['parts'])) {
            foreach ($json['candidates'][0]['content']['parts'] as $part) {
                if (!empty($part['text'])) {
                    $txt .= $part['text'];
                }
            }
        }

        return $txt;
    }

    public static function complete(string $prompt, array $schema = [], array $args = [])
    {
        $c = self::cfg();

        if (empty($c['key'])) {
            return new WP_Error('pga_no_key', 'Chave Gemini não configurada.');
        }

        $model = $c['model'] ?? $c['model_text'];
        $isStructured = !empty($schema);

        $maxTokens   = $args['max_tokens'] ?? ($isStructured ? 1800 : 5000);
        $temperature = $args['temperature'] ?? ($isStructured ? 0 : 0.2);
        $topP        = $args['top_p'] ?? ($isStructured ? 0 : 0.2);

        // 🔒 Força modo JSON se tiver schema
        $systemPrompt = $isStructured
            ? "Responda SOMENTE com JSON válido UTF-8.
            Sem markdown.
            Sem explicações.
            Sem texto fora do JSON.
            Não use aspas tipográficas."
            : "Você é um gerador de artigos focado em SEO GEO e E-E-A-T.";

        $finalPrompt = trim($systemPrompt) . "\n\n" . $prompt;

        $body = [
            'contents' => [
                [
                    'role'  => 'user',
                    'parts' => [
                        ['text' => $finalPrompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature'     => $temperature,
                'topP'            => $topP,
                'maxOutputTokens' => $maxTokens,
            ]
        ];

        $endpoint = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
            $model,
            $c['key']
        );

        $res = wp_remote_post($endpoint, [
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => $c['timeout'] ?? 60,
            'body'    => wp_json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        if (is_wp_error($res)) {
            return $res;
        }

        $raw  = wp_remote_retrieve_body($res);
        $json = json_decode($raw, true);

        if (!isset($json['candidates'][0]['content']['parts'][0]['text'])) {
            return new WP_Error(
                'pga_gemini_invalid',
                'Resposta inválida do Gemini.',
                ['raw' => $raw]
            );
        }

        $txt = trim((string)$json['candidates'][0]['content']['parts'][0]['text']);

        // 🔹 MODO TEXTO NORMAL
        if (!$isStructured) {
            return $txt;
        }

        /*
        |--------------------------------------------------------------------------
        | 🔥 PARSE FORÇADO IGUAL OPENAI
        |--------------------------------------------------------------------------
        */

        // remove qualquer lixo antes/depois do JSON
        $parsed = AlphaSuite_AI::decode_json_payload($txt);

        if (!is_array($parsed)) {
            return new WP_Error(
                'pga_json_invalid',
                'JSON inválido retornado pelo Gemini.',
                [
                    'snippet'    => mb_substr($txt, 0, 1000)
                ]
            );
        }

        return $parsed;
    }

    public static function embeddings(array $texts, array $args = [])
    {
        $c = self::cfg();

        if (empty($c['key'])) {
            return new WP_Error('no_key', 'Chave Gemini não configurada.');
        }

        $model = $args['model'] ?? 'models/embedding-001';
        $embeddings = [];

        foreach ($texts as $text) {

            $res = wp_remote_post(
                "https://generativelanguage.googleapis.com/v1beta/{$model}:embedContent?key={$c['key']}",
                [
                    'headers' => [
                        'Content-Type' => 'application/json',
                    ],
                    'body' => wp_json_encode([
                        'content' => [
                            'parts' => [
                                ['text' => $text]
                            ]
                        ]
                    ]),
                    'timeout' => $c['timeout'] ?? 30
                ]
            );

            if (is_wp_error($res)) {
                return $res;
            }

            $body = json_decode(wp_remote_retrieve_body($res), true);

            if (empty($body['embedding']['values'])) {
                return new WP_Error('embedding_error', 'Embedding Gemini inválido');
            }

            $embeddings[] = $body['embedding']['values'];
        }

        return $embeddings;
    }
}
