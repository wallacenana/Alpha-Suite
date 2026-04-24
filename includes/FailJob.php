<?php
// includes/License.php
if (!defined('ABSPATH')) exit;

class AlphaSuite_FailJob
{
    public static function fail_job($post_id, WP_Error $err)
    {
        $data = $err->get_error_data() ?: [];

        wp_update_post([
            'ID'          => $post_id,
            'post_status' => 'draft',
            'post_title'  => '(Falhou) ' . get_the_title($post_id),
        ]);

        update_post_meta($post_id, '_pga_last_error', [
            'code'    => $err->get_error_code(),
            'message' => $err->get_error_message(),
            'data'    => $data,
            'time'    => time(),
        ]);

        return $err;
    }
}
