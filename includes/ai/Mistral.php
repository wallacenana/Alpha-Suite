<?php
if (!defined('ABSPATH')) exit;

class AlphaSuite_Mistral
{
    // ---- Lê config do plugin ----
    private static function cfg(): array
    {
        $opt = AlphaSuite_Settings::get();
        $mi  = $opt['apis']['mistral'] ?? [];

        return [
            'key'         => trim((string)($mi['key'] ?? '')),
            'model_text'  => (string)($mi['model_text'] ?? 'mistral-large-latest'),
            'temperature' => (float) ($mi['temperature'] ?? 0.6),
            'max_tokens'  => (int)   ($mi['max_tokens'] ?? 8000),
            'timeout'     => 120,
        ];
    }

    public static function is_configured(): bool
    {
        return self::cfg()['key'] !== '';
    }

    // ---- Completar texto ----
    public static function complete(string $prompt, array $schema = [], array $args = [])
    {
        $c = self::cfg();

        if (empty($c['key'])) {
            return new WP_Error('pga_no_key', 'Chave Mistral não configurada.');
        }

        $model = $c['model_text'] ?? 'mistral-large-latest';
        $isStructured = !empty($schema);

        $maxTokens   = $args['max_tokens'] ?? ($isStructured ? 1800 : 4000);
        $temperature = $args['temperature'] ?? ($isStructured ? 0 : 0.3);

        $systemPrompt = $isStructured
            ? "Responda SOMENTE com JSON válido UTF-8.
Sem markdown.
Sem explicações.
Sem texto fora do JSON.
Não use aspas tipográficas."
            : "Você é um gerador de artigos focado em SEO GEO e E-E-A-T.";

        $body = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $systemPrompt
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => $temperature,
            'max_tokens'  => $maxTokens
        ];

        $res = wp_remote_post(
            'https://api.mistral.ai/v1/chat/completions',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $c['key'],
                    'Content-Type'  => 'application/json',
                ],
                'timeout' => $c['timeout'] ?? 60,
                'body'    => wp_json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]
        );

        if (is_wp_error($res)) {
            return $res;
        }

        $raw  = wp_remote_retrieve_body($res);
        $json = json_decode($raw, true);

        if (!isset($json['choices'][0]['message']['content'])) {
            return new WP_Error(
                'pga_mistral_invalid',
                'Resposta inválida do Mistral.',
                ['raw' => $raw]
            );
        }

        $txt = trim((string)$json['choices'][0]['message']['content']);

        // 🔹 MODO TEXTO NORMAL
        if (!$isStructured) {
            return $txt;
        }

        /*
    |--------------------------------------------------------------------------
    | 🔥 PARSE FORÇADO
    |--------------------------------------------------------------------------
    */

        // remove qualquer lixo antes/depois do JSON
        if (preg_match('/\{.*\}/s', $txt, $m)) {
            $txt = $m[0];
        }

        $parsed = json_decode($txt, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error(
                'pga_json_invalid',
                'JSON inválido retornado pelo Mistral.',
                [
                    'json_error' => json_last_error_msg(),
                    'snippet'    => mb_substr($txt, 0, 1000)
                ]
            );
        }

        // Caso venha {"content":"{...json interno...}"}
        if (isset($parsed['content']) && is_string($parsed['content'])) {
            $inner = json_decode($parsed['content'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($inner)) {
                $parsed = $inner;
            }
        }

        return $parsed;
    }

    public static function embeddings(array $texts, array $args = [])
    {
        $c = self::cfg();

        if (empty($c['key'])) {
            return new WP_Error('no_key', 'Chave Mistral não configurada.');
        }

        $model = $args['model'] ?? 'mistral-embed';

        $res = wp_remote_post(
            'https://api.mistral.ai/v1/embeddings',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $c['key'],
                    'Content-Type'  => 'application/json',
                ],
                'body' => wp_json_encode([
                    'model' => $model,
                    'input' => array_values($texts) // batch igual OpenAI
                ]),
                'timeout' => $c['timeout'] ?? 30
            ]
        );

        if (is_wp_error($res)) {
            return $res;
        }

        $body = json_decode(wp_remote_retrieve_body($res), true);

        if (empty($body['data'])) {
            return new WP_Error('embedding_error', 'Embedding Mistral inválido');
        }

        $embeddings = [];

        foreach ($body['data'] as $row) {
            $embeddings[] = $row['embedding'];
        }

        return $embeddings;
    }
}
