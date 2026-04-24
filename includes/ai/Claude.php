<?php
if (!defined('ABSPATH')) exit;

class AlphaSuite_Claude
{
    // ---- Lê config do plugin ----
    private static function cfg(): array
    {
        $opt = AlphaSuite_Settings::get();
        $cl  = $opt['apis']['claude'] ?? [];

        return [
            'key'         => trim((string)($cl['key'] ?? '')),
            'model_text'  => (string)($cl['model_text'] ?? 'claude-3-5-sonnet-20240620'),
            'temperature' => (float) ($cl['temperature'] ?? 0.95),
            'max_tokens'  => (int)   ($cl['max_tokens'] ?? 4096),
            'timeout'     => 120,
        ];
    }

    public static function is_configured(): bool
    {
        return self::cfg()['key'] !== '';
    }

    public static function complete(string $prompt, array $schema = [], array $args = [])
    {
        $c = self::cfg();

        if (empty($c['key'])) {
            return new WP_Error('pga_no_key', 'Chave Claude não configurada.');
        }

        $model = $c['model_text'] ?? 'claude-3-haiku-20240307';
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
            'max_tokens' => 4055,
            'temperature' => $temperature,
            'system' => $systemPrompt,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ]
        ];

        $res = wp_remote_post(
            'https://api.anthropic.com/v1/messages',
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'x-api-key' => $c['key'],
                    'anthropic-version' => '2023-06-01'
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

        if (!isset($json['content'][0]['text'])) {
            return new WP_Error(
                'pga_claude_invalid',
                'Resposta inválida do Claude.',
                ['raw' => $raw]
            );
        }

        $txt = trim((string)$json['content'][0]['text']);

        // 🔹 MODO TEXTO
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
                'JSON inválido retornado pelo Claude.',
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
        $embeddings = [];

        foreach ($texts as $text) {
            $embeddings[] = self::local_embedding($text);
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
