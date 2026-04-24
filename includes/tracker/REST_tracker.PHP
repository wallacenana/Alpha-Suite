<?php
if (!defined('ABSPATH')) exit;

class AlphaSuite_REST_tracker
{
    public static function register_routes()
    {
        $base = 'pga/v1';

        register_rest_route($base, '/tracker', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'tracker_view'],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route($base, '/tracker/duration', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'tracker_duration'],
            'permission_callback' => '__return_true'
        ]);
    }

    public static function tracker_view(WP_REST_Request $req)
    {
        global $wpdb;

        $post_id = intval($req->get_param('post_id'));
        $session = sanitize_text_field($req->get_param('session'));
        $ip      = $_SERVER['REMOTE_ADDR'] ?? '';

        if (!$post_id || !$session) {
            return ['ok' => false];
        }

        $table = $wpdb->prefix . 'pga_post_views';

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE post_id=%d AND session_id=%s LIMIT 1",
            $post_id,
            $session
        ));

        if (!$exists) {

            $wpdb->insert(
                $table,
                [
                    'post_id' => $post_id,
                    'session_id' => $session,
                    'ip' => $ip
                ],
                ['%d', '%s', '%s']
            );
        }

        return ['ok' => true];
    }

    public static function tracker_duration(WP_REST_Request $req)
    {
        global $wpdb;

        $post_id  = intval($req->get_param('post_id'));
        $session  = sanitize_text_field($req->get_param('session'));
        $duration = intval($req->get_param('duration'));

        if (!$post_id || !$session || !$duration) {
            return ['ok' => false];
        }

        $table = $wpdb->prefix . 'pga_post_views';

        $wpdb->update(
            $table,
            ['duration' => $duration],
            [
                'post_id' => $post_id,
                'session_id' => $session
            ],
            ['%d'],
            ['%d', '%s']
        );

        return ['ok' => true];
    }
}
