<?php
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Central de geração de imagens (OpenAI / Pollinations) para posts e stories.
 */
class AlphaSuite_Images
{
    /**
     * Gera uma imagem usando o provider configurado (thumb por padrão).
     *
     * @param string $prompt
     * @param int    $post_id
     * @param string $alt
     * @param mixed  $imgSettings   (array ou vazio; se vazio, pega das settings globais)
     * @param string $context       'thumb' | 'story'
     *
     * @return int|\WP_Error
     */
    public static function generate_by_settings(
        string $prompt,
        int $post_id,
        string $alt = '',
        $imgSettings = [],
        string $context = 'thumb' // thumb|story|outros
    ) {
        if ($prompt === '' || $post_id <= 0) {
            return 0;
        }

        if (!is_array($imgSettings)) {
            $imgSettings = [];
        }

        // Carrega settings globais se não veio override
        if (empty($imgSettings) && class_exists('AlphaSuite_Settings')) {
            $opts = AlphaSuite_Settings::get();

            // base: provedor global de imagens (Geral › Imagens)
            $globalImg = (isset($opts['apis']['images']) && is_array($opts['apis']['images']))
                ? $opts['apis']['images']
                : [];

            $imgSettings = $globalImg;

            // se for story, olha o provider específico dos stories
            if ($context === 'story') {
                $st = isset($opts['stories']) && is_array($opts['stories'])
                    ? $opts['stories']
                    : [];

                $storyProv = isset($st['images_provider'])
                    ? (string)$st['images_provider']
                    : 'inherit';

                // se NÃO for inherit, sobrescreve o provider
                if ($storyProv && $storyProv !== 'inherit') {
                    $imgSettings['provider'] = $storyProv;
                }
                // se for inherit, simplesmente usa o global mesmo
            }

            if ($context !== 'story') {
                $gp = isset($opts['orion_posts']) && is_array($opts['orion_posts'])
                    ? $opts['orion_posts']
                    : [];

                $orionProv = isset($gp['images_provider'])
                    ? (string) $gp['images_provider']
                    : 'inherit';

                if ($orionProv && $orionProv !== 'inherit') {
                    $imgSettings['provider'] = $orionProv;
                }
            }
        }


        $provider = isset($imgSettings['provider']) && $imgSettings['provider'] !== ''
            ? (string)$imgSettings['provider']
            : 'pollinations';


        if ($alt === '') {
            $alt = get_the_title($post_id) ?: '';
        }

        // Desliga geração
        if ($provider === 'none') {
            return 0;
        }

        switch ($provider) {
            case 'openai':
                return self::generate_openai_image(
                    $prompt,
                    $post_id,
                    $alt,
                    $imgSettings,
                    $context
                );

            case 'pexels':
                return self::generate_pexels_image(
                    $prompt,
                    $post_id,
                    $alt,
                    $context,
                    $opts['apis']['pexels'] ?? []
                );

            case 'unsplash':
                return self::generate_unsplash_image(
                    $prompt,
                    $post_id,
                    $alt,
                    $context
                );

            case 'pollinations':
            default:
                // Pollinations – aqui só escolhemos tamanho conforme o contexto
                $opts = [];

                if ($context === 'story') {
                    // não pode ser maior que ~640x900 (mobile vertical leve)
                    $opts = [
                        'width'  => 640,
                        'height' => 900,
                        'nologo' => true,
                        'model'  => 'flux',
                    ];
                } else {
                    // thumbnail de post – pode ser maior
                    $opts = [
                        'width'  => 1200,
                        'height' => 675,
                        'nologo' => true,
                        'model'  => 'flux',
                    ];
                }

                return self::generate_pollinations_image(
                    $prompt,
                    $post_id,
                    $opts,
                    $alt
                );
        }
    }


    /**
     * Atalho específico para STORIES usando o mesmo esquema de settings.
     *
     * @param string $prompt
     * @param int    $post_id
     * @param string $alt
     * @param mixed  $imgSettings
     *
     * @return int|\WP_Error
     */
    public static function generate_story_by_settings(
        string $prompt,
        int $post_id,
        string $alt = '',
        $imgSettings = []
    ) {
        return self::generate_by_settings($prompt, $post_id, $alt, $imgSettings, 'story');
    }

    /**
     * Núcleo de geração via OpenAI (usado tanto pra thumb quanto pra story).
     *
     * @param string $prompt
     * @param int    $post_id
     * @param string $alt
     * @param array  $imgSettings
     * @param string $context    'thumb' | 'story'
     *
     * @return int|\WP_Error
     */
    public static function generate_openai_image(
        string $prompt,
        int $post_id,
        string $alt,
        array $imgSettings = [],
        string $context = 'thumb'
    ) {
        if ($prompt === '' || $post_id <= 0) {
            return 0;
        }

        $opts = class_exists('AlphaSuite_Settings') ? AlphaSuite_Settings::get() : [];
        $api  = $opts['apis']['openai'] ?? [];
        $key  = trim((string) ($api['key'] ?? ''));

        if ($key === '') {
            return new \WP_Error('pga_openai_no_key', 'Chave da OpenAI não configurada.');
        }

        $model   = $imgSettings['model']   ?? 'dall-e-3';
        $quality = $imgSettings['quality'] ?? 'standard';

        // Se não veio size explícito nas configs, decide pelo contexto
        if (!empty($imgSettings['size'])) {
            $size = $imgSettings['size'];
        } else {
            if ($context === 'story') {
                // vertical pra stories
                $size = '1024x1792';
            } else {
                // thumbnail wide
                $size = '1792x1024';
            }
        }

        $body = [
            'model'   => $model,
            'prompt'  => $prompt,
            'n'       => 1,
            'size'    => $size,
            'quality' => $quality,
        ];

        // Só por segurança, se alguém enfiar response_format nas settings:
        if (isset($body['response_format'])) {
            unset($body['response_format']);
        }

        $res = wp_remote_post(
            'https://api.openai.com/v1/images/generations',
            [
                'timeout' => 60,
                'headers' => [
                    'Authorization' => 'Bearer ' . $key,
                    'Content-Type'  => 'application/json',
                ],
                'body'    => wp_json_encode($body),
            ]
        );

        if (is_wp_error($res)) {
            return $res;
        }

        $code = wp_remote_retrieve_response_code($res);
        $raw  = wp_remote_retrieve_body($res);

        if ($code >= 500 && $code < 600) {
            return new \WP_Error(
                'pga_openai_5xx',
                __('OpenAI está com instabilidade no momento ao gerar imagens. Tente novamente em instantes.', 'alpha-suite')
            );
        }

        if (200 !== $code || ! $raw) {
            return new \WP_Error(
                'pga_openai_http',
                sprintf(
                    __('Erro ao gerar imagem na OpenAI.', 'alpha-suite')
                )
            );
        }


        $json = json_decode($raw, true);
        if (empty($json['data'][0]['url'])) {
            return new \WP_Error(
                'pga_openai_bad_response',
                __('Resposta inesperada da API de imagens.', 'alpha-suite')
            );
        }

        $img_url = (string) $json['data'][0]['url'];

        // Baixa a imagem gerada
        $img_res = wp_remote_get(
            $img_url,
            [
                'timeout' => 60,
            ]
        );
        if (is_wp_error($img_res)) {
            return $img_res;
        }

        $img_body = wp_remote_retrieve_body($img_res);
        if (!$img_body) {
            return new \WP_Error(
                'pga_openai_empty_image',
                __('Imagem vazia retornada pela OpenAI.', 'alpha-suite')
            );
        }

        // Usa helper comum para salvar
        return self::create_attachment_from_binary(
            $img_body,
            $post_id,
            $alt,
            'openai'
        );
    }

    public static function generate_pexels_image(
        string $prompt,
        int $post_id,
        string $alt = '',
        string $context = 'thumb',
        array $api = []
    ) {
        if ($prompt === '' || $post_id <= 0) {
            return 0;
        }

        $api = trim((string)($api['key'] ?? ''));

        if ($api === '') {
            return new \WP_Error(
                'pga_pexels_no_key',
                __('Chave de API do Pexels não configurada.', 'alpha-suite')
            );
        }

        // vertical pra stories, horizontal pro resto
        $orientation = ($context === 'story') ? 'portrait' : 'landscape';

        // 🔹 Query já vem "otimizada" pelo Prompts (poucas palavras)
        // busca várias imagens na PRIMEIRA página e varia pelo índice
        $search_query = trim($prompt);
        $page         = 1; // evita cair em páginas vazias

        $endpoint = add_query_arg(
            [
                'query'       => $search_query,
                'per_page'    => 12,
                'page'        => $page,
                'orientation' => $orientation,
            ],
            'https://api.pexels.com/v1/search'
        );


        $res = wp_remote_get(
            $endpoint,
            [
                'timeout' => 30,
                'headers' => [
                    'Authorization' => $api,
                ],
            ]
        );

        if (is_wp_error($res)) {
            return $res;
        }

        $code = wp_remote_retrieve_response_code($res);
        $body = wp_remote_retrieve_body($res);

        if ($code < 200 || $code >= 300 || !$body) {
            return new \WP_Error(
                'pga_pexels_http',
                sprintf(__('Erro ao buscar imagem no Pexels.', 'alpha-suite'))
            );
        }

        $json    = json_decode($body, true);
        $results = $json['photos'] ?? [];

        if (empty($results)) {
            return new \WP_Error(
                'pga_pexels_empty',
                __('Nenhuma imagem encontrada no Pexels para este prompt.', 'alpha-suite')
            );
        }

        // Normaliza índice
        $results = array_values($results);

        // 🔹 Evita repetir a MESMA foto para o MESMO post
        $last_id = (int) get_post_meta($post_id, '_pga_last_pexels_photo_id', true);

        $idx       = wp_rand(0, count($results) - 1);
        $maxTries  = min(5, count($results));
        $chosen    = null;
        $chosen_id = 0;

        for ($i = 0; $i < $maxTries; $i++) {
            $c = $results[$idx] ?? null;

            if (!is_array($c)) {
                $idx = ($idx + 1) % count($results);
                continue;
            }

            $photo_id = isset($c['id']) ? (int) $c['id'] : 0;

            // se for diferente da última, usamos essa
            if ($photo_id && $photo_id !== $last_id) {
                $chosen    = $c;
                $chosen_id = $photo_id;
                break;
            }

            // senão, tenta o próximo índice
            $idx = ($idx + 1) % count($results);
        }

        // se mesmo assim não escolheu (ex.: só uma foto ou todas repetidas), pega qualquer uma
        if (!$chosen) {
            $chosen = $results[0];
            $chosen_id = isset($chosen['id']) ? (int) $chosen['id'] : 0;
        }

        // salva pra evitar repetir na próxima chamada
        if ($chosen_id > 0) {
            update_post_meta($post_id, '_pga_last_pexels_photo_id', $chosen_id);
        }

        $src = $chosen['src'] ?? [];

        $img_url = $src[$orientation] // portrait/landscape conforme orientação
            ?? $src['large2x']
            ?? $src['large']
            ?? $src['medium']
            ?? $src['original']
            ?? '';

        if (!$img_url) {
            return new \WP_Error(
                'pga_pexels_no_url',
                __('Não foi possível determinar a URL da imagem do Pexels.', 'alpha-suite')
            );
        }

        $img_res = wp_remote_get($img_url, [
            'timeout' => 60,
        ]);

        if (is_wp_error($img_res)) {
            return $img_res;
        }

        $binary = wp_remote_retrieve_body($img_res);
        if (!$binary) {
            return new \WP_Error(
                'pga_pexels_empty_image',
                __('Imagem vazia retornada pelo Pexels.', 'alpha-suite')
            );
        }

        return self::create_attachment_from_binary(
            $binary,
            $post_id,
            $alt,
            'pexels'
        );
    }

    public static function generate_unsplash_image(
        string $prompt,
        int $post_id,
        string $alt = '',
        string $context = 'thumb'
    ) {
        if ($prompt === '' || $post_id <= 0) {
            return 0;
        }

        if (!class_exists('AlphaSuite_Settings')) {
            return new \WP_Error(
                'pga_unsplash_no_cfg',
                __('Configurações do Unsplash não encontradas.', 'alpha-suite')
            );
        }

        $opts = AlphaSuite_Settings::get();
        $api  = $opts['apis']['unsplash'] ?? [];
        $key  = trim((string) ($api['access_key'] ?? ''));

        if ($key === '') {
            return new \WP_Error(
                'pga_unsplash_no_key',
                __('Access Key do Unsplash não configurada.', 'alpha-suite')
            );
        }

        $orientation = ($context === 'story') ? 'portrait' : 'landscape';

        // opcional: ruído pra mudar ranking, mas ainda baseado no prompt
        // NÃO muda o sentido, só ajuda a variar
        $search_query = trim($prompt) . ' #' . wp_rand(1000, 9999);

        $endpoint = add_query_arg(
            [
                'query'          => $search_query,
                'per_page'       => 12,          // pega várias
                'orientation'    => $orientation,
                'content_filter' => 'high',
            ],
            'https://api.unsplash.com/search/photos'
        );

        $res = wp_remote_get(
            $endpoint,
            [
                'timeout' => 30,
                'headers' => [
                    'Authorization' => 'Client-ID ' . $key,
                ],
            ]
        );

        if (is_wp_error($res)) {
            return $res;
        }

        $code = wp_remote_retrieve_response_code($res);
        $body = wp_remote_retrieve_body($res);

        if ($code < 200 || $code >= 300 || !$body) {
            return new \WP_Error(
                'pga_unsplash_http',
                sprintf(__('Erro ao buscar imagem no Unsplash.', 'alpha-suite'))
            );
        }

        $json    = json_decode($body, true);
        $results = $json['results'] ?? [];

        if (empty($results)) {
            return new \WP_Error(
                'pga_unsplash_empty',
                __('Nenhuma imagem encontrada no Unsplash para este prompt.', 'alpha-suite')
            );
        }

        // 🎯 AQUI é o pulo do gato: escolhe UM resultado aleatório entre os retornados
        $idx    = wp_rand(0, count($results) - 1);
        $chosen = $results[$idx];

        $urls = $chosen['urls'] ?? [];
        $img_url = $urls['regular']
            ?? $urls['full']
            ?? $urls['small']
            ?? '';

        if (!$img_url) {
            return new \WP_Error(
                'pga_unsplash_no_url',
                __('Não foi possível determinar a URL da imagem do Unsplash.', 'alpha-suite')
            );
        }

        $img_res = wp_remote_get($img_url, [
            'timeout' => 60,
        ]);

        if (is_wp_error($img_res)) {
            return $img_res;
        }

        $binary = wp_remote_retrieve_body($img_res);
        if (!$binary) {
            return new \WP_Error(
                'pga_unsplash_empty_image',
                __('Imagem vazia retornada pelo Unsplash.', 'alpha-suite')
            );
        }

        return self::create_attachment_from_binary(
            $binary,
            $post_id,
            $alt,
            'unsplash'
        );
    }


    /**
     * Wrapper de compat: se em algum lugar antigo ainda chamarem generate_openai_thumbnail,
     * ele só delega pro núcleo com contexto 'thumb'.
     */
    public static function generate_openai_thumbnail(
        string $prompt,
        int $post_id,
        string $alt,
        array $imgSettings = []
    ) {
        return self::generate_openai_image($prompt, $post_id, $alt, $imgSettings, 'thumb');
    }

    /**
     * Gera imagem via Pollinations (tamanho configurável) e salva como attachment.
     *
     * @return int|\WP_Error
     */
    public static function generate_pollinations_image(
        string $prompt,
        int $post_id,
        array $opts = [
            'width'  => 1200,
            'height' => 630,
            'nologo' => true,
            'model'  => 'flux',
        ],
        string $alt = ''
    ) {
        if ('' === $prompt || $post_id <= 0) {
            return 0;
        }

        $base_url = 'https://image.pollinations.ai/prompt/' . rawurlencode($prompt);

        $query = [];
        if (!empty($opts['width'])) {
            $query['width'] = (int) $opts['width'];
        }
        if (!empty($opts['height'])) {
            $query['height'] = (int) $opts['height'];
        }
        if (!empty($opts['model'])) {
            $query['model'] = (string) $opts['model'];
        }

        $query['seed'] = !empty($opts['seed'])
            ? (int) $opts['seed']
            : wp_rand(1, 1000000);

        $url = add_query_arg($query, $base_url);

        $res = wp_remote_get(
            $url,
            [
                'timeout' => 60,
                'headers' => [
                    'Accept' => 'image/avif,image/webp,image/jpeg,image/png,*/*',
                ],
            ]
        );

        if (is_wp_error($res)) {
            return $res;
        }

        $code = wp_remote_retrieve_response_code($res);

        if ($code < 200 || $code >= 300) {
            $body_err = wp_remote_retrieve_body($res);

            return new \WP_Error(
                'pga_pollinations_http',
                sprintf(__('Falha ao gerar imagem.', 'alpha-suite')),
                [
                    'status'    => $code,
                    'http_code' => $code,
                ]
            );
        }

        $body = wp_remote_retrieve_body($res);
        if (!$body) {
            return new \WP_Error(
                'pga_pollinations_empty',
                __('Resposta de imagem vazia.', 'alpha-suite')
            );
        }

        // detecta mime/ext
        $mime = 'image/jpeg';
        if (function_exists('getimagesizefromstring')) {
            $info = @getimagesizefromstring($body);
            if (!empty($info['mime'])) {
                $mime = $info['mime'];
            }
        }

        $ext = 'jpg';
        if ('image/png' === $mime) {
            $ext = 'png';
        } elseif ('image/webp' === $mime) {
            $ext = 'webp';
        }

        $filename = 'pollinations-' . $post_id . '-' . time() . '.' . $ext;

        $upload = wp_upload_bits($filename, null, $body);
        if (!empty($upload['error'])) {
            return new \WP_Error(
                'pga_pollinations_upload',
                $upload['error']
            );
        }

        $filetype = wp_check_filetype(basename($upload['file']), null);

        $attachment = [
            'guid'           => $upload['url'],
            'post_mime_type' => $filetype['type'] ?: $mime,
            'post_title'     => preg_replace('/\.[^.]+$/', '', basename($upload['file'])),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];

        $attach_id = wp_insert_attachment($attachment, $upload['file'], $post_id);
        if (is_wp_error($attach_id) || !$attach_id) {
            return $attach_id;
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attach_data = wp_generate_attachment_metadata($attach_id, $upload['file']);
        wp_update_attachment_metadata($attach_id, $attach_data);

        if ($alt) {
            update_post_meta(
                $attach_id,
                '_wp_attachment_image_alt',
                wp_strip_all_tags($alt)
            );
        }

        return (int) $attach_id;
    }

    /**
     * Helper thumbnail padrão (landscape).
     */
    public static function generate_pollinations_thumbnail(
        string $prompt,
        int $post_id,
        string $alt = ''
    ) {
        return self::generate_pollinations_image(
            $prompt,
            $post_id,
            [
                'width'  => 1200,
                'height' => 630,
                'nologo' => true,
                'model'  => 'flux',
            ],
            $alt
        );
    }

    /**
     * Helper: imagem para Web Stories (portrait).
     */
    public static function generate_pollinations_story_image(
        string $prompt,
        int $post_id,
        string $alt = ''
    ) {
        return self::generate_pollinations_image(
            $prompt,
            $post_id,
            [
                'width'  => 640,
                'height' => 900,
                'nologo' => true,
                'model'  => 'flux',
            ],
            $alt
        );
    }

    /**
     * Salva binário de imagem como attachment.
     *
     * @return int|\WP_Error
     */
    protected static function create_attachment_from_binary(
        string $binary,
        int $post_id,
        string $alt = '',
        string $prefix = 'img'
    ) {
        if ('' === $binary || $post_id <= 0) {
            return 0;
        }

        // tenta detectar mime
        $mime = 'image/jpeg';
        if (function_exists('getimagesizefromstring')) {
            $info = @getimagesizefromstring($binary);
            if (! empty($info['mime'])) {
                $mime = $info['mime'];
            }
        }

        $ext = 'jpg';
        if ('image/png' === $mime) {
            $ext = 'png';
        } elseif ('image/webp' === $mime) {
            $ext = 'webp';
        }

        $filename = $prefix . '-' . $post_id . '-' . time() . '.' . $ext;

        $upload = wp_upload_bits($filename, null, $binary);
        if (! empty($upload['error'])) {
            return new \WP_Error(
                'pga_upload_error',
                $upload['error']
            );
        }

        $filetype = wp_check_filetype(basename($upload['file']), null);

        $attachment = [
            'guid'           => $upload['url'],
            'post_mime_type' => $filetype['type'] ?: $mime,
            'post_title'     => preg_replace('/\.[^.]+$/', '', basename($upload['file'])),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];

        $attach_id = wp_insert_attachment($attachment, $upload['file'], $post_id);
        if (is_wp_error($attach_id) || ! $attach_id) {
            return $attach_id;
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attach_data = wp_generate_attachment_metadata($attach_id, $upload['file']);
        wp_update_attachment_metadata($attach_id, $attach_data);

        if ($alt) {
            update_post_meta(
                $attach_id,
                '_wp_attachment_image_alt',
                wp_strip_all_tags($alt)
            );
        }

        return (int) $attach_id;
    }
}
