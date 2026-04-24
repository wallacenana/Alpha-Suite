<?php
if (!defined('ABSPATH')) exit;

class AlphaSuite_Pollinations
{
    private static function cfg(): array
    {
        $opt = AlphaSuite_Settings::get();
        $pl  = $opt['apis']['pollinations'] ?? [];

        return [
            'model'       => (string) ($pl['model_text'] ?? 'claude-airforce'),
            'temperature' => (float) ($pl['temperature'] ?? 0.6),
            'max_tokens'  => (int) ($pl['max_tokens'] ?? 4000),
            'key'         => (string) ($pl['key'] ?? ''),
            'timeout'     => (int) ($pl['timeout'] ?? 120),
        ];
    }


    public static function is_configured(): bool
    {
        // Pollinations não precisa de API key
        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | COMPLETION (Texto)
    |--------------------------------------------------------------------------
    */

    public static function complete(string $prompt, array $schema = [], array $args = [])
    {
        $c = self::cfg();

        $url = 'https://gen.pollinations.ai/v1/chat/completions';

        $body = [
            "model" => 'gemini-fast',
            "messages" => [
                [
                    "role" => "system",
                    "content" => !empty($schema)
                        ? "Responda SOMENTE com JSON válido. Sem explicações."
                        : "Você é um gerador de conteúdo SEO."
                ],
                [
                    "role" => "user",
                    "content" => $prompt
                ]
            ],
            "temperature" => $args['temperature'] ?? 0.7,
            "max_tokens"  => $args['max_tokens'] ?? 2000
        ];

        $headers = [
            'Content-Type' => 'application/json',
        ];

        // 🔑 adiciona key se existir
        if (!empty($c['key'])) {
            $headers['Authorization'] = 'Bearer ' . $c['key'];
        }

        $res = wp_remote_post($url, [
            'headers' => $headers,
            'body' => wp_json_encode($body),
            'timeout' => $c['timeout'],
        ]);

        if (is_wp_error($res)) {
            return $res;
        }

        $raw  = wp_remote_retrieve_body($res);
        $json = json_decode($raw, true);
        error_log("Pollinations response: " . print_r($res, true));


        if (!isset($json['choices'][0]['message']['content'])) {
            return new WP_Error('pollinations_invalid', 'Resposta inválida', [
                'raw' => $raw
            ]);
        }

        $txt = trim($json['choices'][0]['message']['content']);

        // texto simples
        if (empty($schema)) {
            return $txt;
        }

        // tenta extrair JSON
        if (preg_match('/\{.*\}/s', $txt, $m)) {
            $txt = $m[0];
        }

        $parsed = json_decode($txt, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_error', json_last_error_msg(), [
                'snippet' => substr($txt, 0, 500)
            ]);
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

        // remove pontuação básica
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);

        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);

        // stopwords básicas (pt + en)
        $stopwords = [
            'a',
            'o',
            'e',
            'de',
            'do',
            'da',
            'em',
            'para',
            'com',
            'um',
            'uma',
            'os',
            'as',
            'the',
            'and',
            'of',
            'to',
            'in',
            'on',
            'for',
            'is',
            'are'
        ];

        $words = array_filter($words, function ($w) use ($stopwords) {
            return !in_array($w, $stopwords, true);
        });

        $size = 512;
        $vector = array_fill(0, $size, 0.0);

        $freq = [];

        // TF (term frequency)
        foreach ($words as $word) {
            $freq[$word] = ($freq[$word] ?? 0) + 1;
        }

        foreach ($freq as $word => $count) {
            $hash = abs(crc32($word));
            $index = $hash % $size;

            // peso logarítmico (melhora MUITO)
            $weight = 1 + log($count);

            $vector[$index] += $weight;
        }

        // normalização L2 (essencial pro cosine)
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
