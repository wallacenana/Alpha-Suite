<?php
if (!defined('ABSPATH')) exit;

class AlphaSuite_OpenAI
{
    /*
    |--------------------------------------------------------------------------
    | Config
    |--------------------------------------------------------------------------
    */

    private static function cfg(): array
    {
        $opt = AlphaSuite_Settings::get();
        $oa  = $opt['apis']['openai'] ?? [];

        return [
            'key'        => trim((string) ($oa['key'] ?? '')),
            'model'      => (string) ($oa['model_text'] ?? 'gpt-4.1'),
            'temperature' => (float)  ($oa['temperature'] ?? 0.6),
            'max_output_tokens' => (int) ($oa['max_output_tokens'] ?? 4000),
            'timeout'    => 120,
        ];
    }

    public static function is_configured(): bool
    {
        $c = self::cfg();
        return !empty($c['key']);
    }

    /*
    |--------------------------------------------------------------------------
    | COMPLETION (Texto ou JSON Estruturado)
    |--------------------------------------------------------------------------
    */

    public static function complete(string $prompt, array $schema = [], array $args = [])
    {
        $c = self::cfg();

        if (!$c['key']) {
            return new WP_Error('pga_no_key', 'Chave OpenAI não configurada.');
        }

        $isStructured = !empty($schema);

        $body = [
            "model" => $c['model'] ?? $c['model_text'],
            "input" => $prompt
        ];

        $res = wp_remote_post(
            'https://api.openai.com/v1/responses',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $c['key'],
                    'Content-Type'  => 'application/json',
                ],
                'body' => wp_json_encode($body),
                'timeout' => 120,
            ]
        );

        if (is_wp_error($res)) {
            return $res;
        }

        $code = wp_remote_retrieve_response_code($res);
        $raw  = wp_remote_retrieve_body($res);

        if ($code !== 200) {
            $j = json_decode($raw, true);
            $msg = $j['error']['message'] ?? ('HTTP ' . $code);
            return new WP_Error('pga_openai_http', $msg, ['http_code' => $code]);
        }

        $json = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error(
                'pga_openai_invalid_json',
                'Resposta inválida da OpenAI.',
                ['raw_tail' => substr($raw, -500)]
            );
        }

        $txt = self::extract_output_text($json);

        if (!$txt) {
            return new WP_Error('pga_no_output', 'Nenhum texto retornado pela API.');
        }

        if (!$isStructured) {
            return trim($txt);
        }

        $parsed = AlphaSuite_AI::decode_json_payload($txt);

        if (!is_array($parsed)) {
            return new WP_Error(
                'pga_json_invalid',
                'JSON inválido retornado.',
                ['snippet' => mb_substr($txt, 0, 1000)]
            );
        }

        return $parsed;
    }

    public static function embeddings(array $texts, array $args = [])
    {
        $c = self::cfg();

        if (empty($c['key'])) {
            return new WP_Error('no_key', 'Chave OpenAI não configurada.');
        }

        $res = wp_remote_post(
            'https://api.openai.com/v1/embeddings',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $c['key'],
                    'Content-Type'  => 'application/json',
                ],
                'body' => wp_json_encode([
                    'model' => $args['model'] ?? 'text-embedding-3-small',
                    'input' => $texts   // 🔥 AQUI ESTÁ A MUDANÇA
                ]),
                'timeout' => $c['timeout'] ?? 30
            ]
        );

        if (is_wp_error($res)) {
            return $res;
        }

        $body = json_decode(wp_remote_retrieve_body($res), true);

        if (empty($body['data'])) {
            return new WP_Error('embedding_error', 'Embedding inválido');
        }

        $embeddings = [];

        foreach ($body['data'] as $row) {
            $embeddings[] = $row['embedding'];
        }

        return $embeddings;
    }

    /*
    |--------------------------------------------------------------------------
    | Extração segura de texto
    |--------------------------------------------------------------------------
    */

    private static function extract_output_text(array $json): string
    {
        $txt = '';

        if (!empty($json['output'])) {
            foreach ($json['output'] as $item) {
                if ($item['type'] === 'message' && !empty($item['content'])) {
                    foreach ($item['content'] as $content) {
                        if ($content['type'] === 'output_text') {
                            $txt .= $content['text'] ?? '';
                        }
                    }
                }
            }
        }

        return trim($txt);
    }
}
