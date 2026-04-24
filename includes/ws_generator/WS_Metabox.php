<?php
if (!defined('ABSPATH')) exit;

class AlphaSuite_WS_Metabox
{
    public static function init()
    {
        add_action('add_meta_boxes', [__CLASS__, 'add']);
    }

    public static function add()
    {
        $screens = ['post', 'posts_orion'];
        foreach ($screens as $screen) {
            add_meta_box(
                'pga_ws_metabox',
                __('Web Story (Generator)', 'alpha-suite'),
                [__CLASS__, 'render'],
                $screen,
                'side',
                'high'
            );
        }
    }

    public static function render($post)
    {
        if (!current_user_can('edit_post', $post->ID)) return;

        // Ajusta aqui se tua página do builder tiver outro slug
        $url = admin_url('admin.php?page=alpha-suite-ws-generator&source=' . absint($post->ID));
?>
        <p style="margin:0 0 10px;">
            <?php esc_html_e('Abrir o WS Generator com este post já selecionado.', 'alpha-suite'); ?>
        </p>

        <a class="button button-primary" href="<?php echo esc_url($url); ?>" style="width:100%;text-align:center;">
            <?php esc_html_e('Gerar Web Story', 'alpha-suite'); ?>
        </a>
<?php
    }
}

