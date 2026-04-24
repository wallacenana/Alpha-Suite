<?php
if (!defined('ABSPATH')) exit;

class AlphaSuite_Adminbar
{
    public static function init(): void
    {
        add_action('admin_bar_menu', [self::class, 'add_orion_nodes'], 90);

        // flush via admin-post.php
        add_action('admin_post_pga_orion_flush_permalinks', [self::class, 'handle_flush_permalinks']);
    }

    public static function add_orion_nodes(\WP_Admin_Bar $admin_bar): void
    {
        if (!is_user_logged_in() || !is_admin_bar_showing()) {
            return;
        }

        if (!is_singular('posts_orion')) {
            return;
        }

        $post_id = get_the_ID();
        if (!$post_id || !current_user_can('edit_post', $post_id)) {
            return;
        }

        // EDITAR POST
        $admin_bar->add_node([
            'id'     => 'pga-orion-edit',
            'title' => '<img src="' . esc_url(PGA_URL . 'assets/images/favicon-alpha-suite.png') . '" style="width:17px;margin-right:9px;float:left;margin-top:7px;" /> '
                . esc_html__('Editar Post', 'alpha-suite'),
            'href'   => get_edit_post_link($post_id, ''),
        ]);
    }
}
