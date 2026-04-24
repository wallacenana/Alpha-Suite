<?php
if (!defined('ABSPATH')) exit;

/**
 * Endpoints REST para Alpha Stories
 * - Geração do esboço (texto + prompts) via IA
 * - Geração de IMAGENS Pollinations, 1 slide por vez
 */
class AlphaSuite_StoriesRest
{
    const NS = 'pga/v1';

    /**
     * Registra rotas REST
     */
    public static function register_routes()
    {
        register_rest_route(
            self::NS,
            '/stories/generate',
            [
                'methods'             => 'POST',
                'callback'            => [__CLASS__, 'rest_generate_story'],
                'permission_callback' => [__CLASS__, 'can_edit_post_from_request'],
                'args'                => [
                    'post_id' => [
                        'type'     => 'integer',
                        'required' => true,
                    ],
                ],
            ]
        );

        register_rest_route(
            self::NS,
            '/stories/image',
            [
                'methods'             => 'POST',
                'callback'            => [__CLASS__, 'rest_generate_image_for_page'],
                'permission_callback' => [__CLASS__, 'can_edit_post_from_request'],
                'args'                => [
                    'post_id' => [
                        'type'     => 'integer',
                        'required' => true,
                    ],
                    'index'   => [
                        'type'     => 'integer',
                        'required' => true,
                    ],
                ],
            ]
        );
    }

    /**
     * Verifica permissão a partir de post_id enviado no corpo/params
     */
    public static function can_edit_post_from_request(\WP_REST_Request $req): bool
    {
        $post_id = (int) $req->get_param('post_id');
        if ($post_id <= 0) {
            return current_user_can('edit_posts');
        }
        return current_user_can('edit_post', $post_id);
    }

    /**
     * POST /pga/v1/stories/generate
     * Gera o esboço do Web Story (texto + prompts), SEM imagens
     */
    public static function rest_generate_story(\WP_REST_Request $req)
    {
        $post_id = (int) $req->get_param('post_id');
        if ($post_id <= 0) {
            return new \WP_Error('pga_story_post', 'post_id inválido.');
        }

        // aqui usamos o helper que você já tem
        $result = AlphaSuite_Helpers::alpha_ai_generate_for_post($post_id);

        if (is_wp_error($result)) {
            return $result;
        }

        // resultado padrão do helper:
        // ['ok' => true, 'count' => N, 'target_id' => X]
        return [
            'ok'        => !empty($result['ok']),
            'count'     => (int) ($result['count'] ?? 0),
            'target_id' => (int) ($result['target_id'] ?? $post_id),
            'edit_url'  => isset($result['edit_url']) ? (string) $result['edit_url'] : '',
            'view_url'  => isset($result['view_url']) ? (string) $result['view_url'] : '',
        ];
    }

    /**
     * POST /pga/v1/stories/image
     * Gera uma IMAGEM Pollinations para UM slide (index) de um story
     */
    public static function rest_generate_image_for_page(\WP_REST_Request $req)
    {
        $post_id = (int) $req->get_param('post_id');
        $index   = (int) $req->get_param('index');

        if ($post_id <= 0) {
            return new \WP_Error('pga_story_post', 'post_id inválido.');
        }

        if ($index < 0) {
            return new \WP_Error('pga_story_index', 'index inválido.');
        }

        $pages = get_post_meta($post_id, '_alpha_storys_pages', true);
        if (!is_array($pages) || empty($pages)) {
            return new \WP_Error('pga_story_pages', 'Nenhuma página de story encontrada.');
        }

        if (!isset($pages[$index])) {
            return new \WP_Error('pga_story_page_index', 'Página de story inexistente para este índice.');
        }

        // Se já existe imagem, retorna sem gerar novamente
        if (!empty($pages[$index]['image_id'])) {
            $img_url = '';
            $img_id  = (int) $pages[$index]['image_id'];

            $img_url = wp_get_attachment_image_url($img_id, 'alpha_storys_slide');
            if (!$img_url) {
                $img_url = wp_get_attachment_image_url($img_id, 'full');
            }

            // garante que 'image' também exista no meta
            if ($img_url) {
                $pages[$index]['image'] = $img_url;
                update_post_meta($post_id, '_alpha_storys_pages', $pages);

                // re-renderiza blocos com as imagens
                if (class_exists('AlphaSuite_Helpers')) {
                    $blocks = AlphaSuite_Helpers::alpha_render_storys_pages_to_blocks($pages);
                    wp_update_post([
                        'ID'           => $post_id,
                        'post_content' => $blocks,
                    ]);
                }
            }

            return [
                'ok'       => true,
                'skipped'  => true,
                'index'    => $index,
                'image_id' => $img_id,
                'image'    => $img_url,
            ];
        }

        $page   = $pages[$index];
        $prompt = isset($page['prompt']) ? trim((string)$page['prompt']) : '';

        if ($prompt === '') {
            return new \WP_Error('pga_story_no_prompt', 'Esta página não possui prompt de imagem.');
        }

        $alt = !empty($page['heading']) ? $page['heading'] : $prompt;
        // Usa o sistema global (decide OpenAI/Pollinations etc.)
        $att_id = AlphaSuite_Images::generate_story_by_settings(
            $prompt,
            $post_id,
            $alt
        );

        if (is_wp_error($att_id)) {
            return $att_id;
        }

        $att_id = (int) $att_id;

        // resolve URL e salva no array de páginas
        $img_url = wp_get_attachment_image_url($att_id, 'alpha_storys_slide');
        if (!$img_url) {
            $img_url = wp_get_attachment_image_url($att_id, 'full');
        }

        $pages[$index]['image_id'] = $att_id;
        if ($img_url) {
            $pages[$index]['image'] = $img_url;
        }

        update_post_meta($post_id, '_alpha_storys_pages', $pages);

        // RE-RENDERIZA o post_content com os blocos incluindo a imagem
        if (class_exists('AlphaSuite_Helpers')) {
            $blocks = AlphaSuite_Helpers::alpha_render_storys_pages_to_blocks($pages);
            wp_update_post([
                'ID'           => $post_id,
                'post_content' => $blocks,
            ]);
        }

        return [
            'ok'       => true,
            'index'    => $index,
            'image_id' => $att_id,
            'image'    => $img_url,
        ];
    }
}
