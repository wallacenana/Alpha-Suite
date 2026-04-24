<?php
if (!defined('ABSPATH')) exit;

class AlphaSuite_Perplexity
{
    // ---- Lê config do plugin ----
    private static function cfg(): array
    {
        $opt = AlphaSuite_Settings::get();
        $px  = $opt['apis']['perplexity'] ?? [];

        return [
            'key'         => trim((string)($px['key'] ?? '')),
            'model_text'  => (string)($px['model_text'] ?? 'llama-3.1-sonar-large-128k-online'),
            'temperature' => (float) ($px['temperature'] ?? 0.6),
            'max_tokens'  => (int)   ($px['max_tokens'] ?? 8000),
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
            return new WP_Error('pga_no_key', 'Chave Perplexity não configurada.');
        }

        $model = $c['model_text'] ?? 'llama-3.1-sonar-small-128k-online';
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
            'https://api.perplexity.ai/chat/completions',
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
                'pga_perplexity_invalid',
                'Resposta inválida do Perplexity.',
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
                'JSON inválido retornado pelo Perplexity.',
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

        // se não tiver chave, usa local
        if (empty($c['perplexity_key'])) {
            $embeddings = [];

            foreach ($texts as $text) {
                $embeddings[] = self::local_embedding($text);
            }

            return $embeddings;
        }

        $res = wp_remote_post(
            'https://api.perplexity.ai/embeddings',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $c['perplexity_key'],
                    'Content-Type'  => 'application/json',
                ],
                'body' => wp_json_encode([
                    'model' => $args['model'] ?? 'sonar-small-embedding',
                    'input' => $texts
                ]),
                'timeout' => $c['timeout'] ?? 30
            ]
        );

        // se falhar API → fallback local
        if (is_wp_error($res)) {
            $embeddings = [];

            foreach ($texts as $text) {
                $embeddings[] = self::local_embedding($text);
            }

            return $embeddings;
        }

        $body = json_decode(wp_remote_retrieve_body($res), true);

        if (empty($body['data'])) {

            $embeddings = [];

            foreach ($texts as $text) {
                $embeddings[] = self::local_embedding($text);
            }

            return $embeddings;
        }

        $embeddings = [];

        foreach ($body['data'] as $row) {
            $embeddings[] = $row['embedding'];
        }

        return $embeddings;
    }
    
    private static function local_embedding(string $text): array
    {
        $text = mb_strtolower($text);
        $words = preg_split('/\W+/u', $text, -1, PREG_SPLIT_NO_EMPTY);

        $size = 384; // tamanho fixo do vetor
        $vector = array_fill(0, $size, 0.0);

        foreach ($words as $word) {
            $hash = abs(crc32($word));
            $index = $hash % $size;
            $vector[$index] += 1;
        }

        // normaliza (importante para cosine funcionar melhor)
        $norm = 0.0;
        foreach ($vector as $v) {
            $norm += $v * $v;
        }

        $norm = sqrt($norm);
        if ($norm > 0) {
            foreach ($vector as $i => $v) {
                $vector[$i] = $v / $norm;
            }
        }

        return $vector;
    }
}
