<?php
if (!defined('ABSPATH')) exit;

class AlphaSuite_Youtube
{
    /**
     * Lê a chave da API do YouTube nas configurações.
     *
     * @return string|WP_Error
     */
    public static function get_key()
    {
        if (!class_exists('AlphaSuite_Settings')) {
            return new WP_Error(
                'pga_youtube_no_settings',
                'AlphaSuite_Settings não encontrado para ler a chave do YouTube.'
            );
        }

        $opt = AlphaSuite_Settings::get();
        $key = trim($opt['apis']['youtube']['key'] ?? '');

        if ($key === '') {
            return new WP_Error(
                'pga_youtube_no_key',
                'Nenhuma chave da API do YouTube configurada. Vá em Alpha Suite → Configurações → YouTube API.'
            );
        }

        return $key;
    }

    /**
     * Extrai o ID do vídeo a partir de uma URL do YouTube.
     *
     * Suporta:
     * - https://www.youtube.com/watch?v=ID
     * - https://youtu.be/ID
     *
     * @return string|WP_Error
     */
    public static function extract_video_id(string $url)
    {
        $url = trim($url);
        if ($url === '') {
            return new WP_Error('pga_youtube_bad_url', 'URL do YouTube vazia.');
        }

        // youtu.be/XXXXXXXX
        if (preg_match('~youtu\.be/([^?&]+)~i', $url, $m)) {
            return $m[1];
        }

        // youtube.com/watch?v=XXXXXXXX
        $parts = wp_parse_url($url);
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $q);
            if (!empty($q['v'])) {
                return $q['v'];
            }
        }

        return new WP_Error('pga_youtube_bad_url', 'Não consegui identificar o ID do vídeo do YouTube.');
    }

    /**
     * Busca dados básicos do vídeo na API do YouTube.
     *
     * Retorna algo como:
     * [
     *   'id'            => '...',
     *   'url'           => '...',
     *   'title'         => '...',
     *   'description'   => '...',
     *   'channel_title' => '...',
     *   'tags'          => [...],
     *   'published_at'  => '...',
     * ]
     *
     * @return array|WP_Error
     */
    public static function fetch_video_data(string $url)
    {
        $key = self::get_key();
        if (is_wp_error($key)) {
            return $key;
        }

        $videoId = self::extract_video_id($url);
        if (is_wp_error($videoId)) {
            return $videoId;
        }

        $apiUrl = add_query_arg([
            'part' => 'snippet,contentDetails,statistics',
            'id'   => $videoId,
            'key'  => $key,
        ], 'https://www.googleapis.com/youtube/v3/videos');

        $res = wp_remote_get($apiUrl, [
            'timeout' => 15,
        ]);

        if (is_wp_error($res)) {
            return new WP_Error(
                'pga_youtube_http',
                'Erro ao chamar a API do YouTube: ' . $res->get_error_message()
            );
        }

        $code = wp_remote_retrieve_response_code($res);
        $body = json_decode(wp_remote_retrieve_body($res), true);

        if ($code !== 200) {
            $msg = 'YouTube retornou HTTP ' . $code;
            if (!empty($body['error']['message'])) {
                $msg = $body['error']['message'];
            }

            return new WP_Error('pga_youtube_api', $msg, ['status' => $code]);
        }

        $item = $body['items'][0] ?? null;
        if (!$item) {
            return new WP_Error('pga_youtube_not_found', 'Vídeo do YouTube não encontrado ou não público.');
        }

        $snippet = $item['snippet'] ?? [];

        return [
            'id'            => $videoId,
            'url'           => $url,
            'title'         => (string)($snippet['title'] ?? ''),
            'description'   => (string)($snippet['description'] ?? ''),
            'channel_title' => (string)($snippet['channelTitle'] ?? ''),
            'tags'          => (array)($snippet['tags'] ?? []),
            'published_at'  => (string)($snippet['publishedAt'] ?? ''),
        ];
    }
}
