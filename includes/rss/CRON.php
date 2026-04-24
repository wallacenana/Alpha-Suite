<?php
if (!defined('ABSPATH')) exit;

class AlphaSuite_CRON
{
    // =========================
    // DISPATCHER
    // =========================
    public static function dispatch()
    {
        global $wpdb;

        // 🔥 SEMPRE usar horário do WordPress
        $now_mysql = current_time('mysql');
        $hour      = (int) current_time('H');

        $table_g = esc_sql($wpdb->prefix . 'pga_generators');
        $table_r = esc_sql($wpdb->prefix . 'pga_generator_runtime');

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "
            SELECT g.id, g.start_hour, g.end_hour, g.interval_hours, r.next_run
            FROM {$table_g} g
            INNER JOIN {$table_r} r ON r.generator_id = g.id
            WHERE g.active = 1
            AND r.next_run <= %s
        ";

        $rows = $wpdb->get_results(
            $wpdb->prepare($sql, $now_mysql)
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared


        if (!$rows) {
            return;
        }

        if (get_transient('pga_cron_lock')) {
            return;
        }

        set_transient('pga_cron_lock', 1, 55);

        foreach ($rows as $row) {

            $start = (int) $row->start_hour;
            $end   = (int) $row->end_hour;

            // janela de horário (São Paulo = WP timezone)
            if ($hour < $start || $hour > $end) {
                continue;
            }

            self::run_generator($row);
        }

        delete_transient('pga_cron_lock');
    }

    // =========================
    // EXECUTA GERADOR
    // =========================
    public static function run_generator($row)
    {
        global $wpdb;
        $generator_id = (int) $row->id;

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "
        SELECT g.*, c.config_json
        FROM {$wpdb->prefix}pga_generators g
        JOIN {$wpdb->prefix}pga_generator_config c
          ON c.generator_id = g.id
        WHERE g.id = %d
        ",
                $generator_id
            )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        if (!$row) {
            return;
        }

        $config = json_decode($row->config_json, true);

        if (!$config) {
            return;
        }

        $feedUrl = trim($config['keywords'] ?? '');

        if (!$feedUrl) {
            self::update_runtime($row, 'feed_url_missing');
            return;
        }

        // 🔥 Chama o pipeline único
        AlphaSuite_RESTRSS::process_feed($feedUrl, $generator_id, $config);
        
        // 🔥 Atualiza runtime como executado
        self::update_runtime($row, 'executed');
    }

    // =========================
    // UPDATE RUNTIME
    // =========================
    private static function update_runtime($row, $status)
    {
        global $wpdb;

        $generator_id = (int) $row->id;

        $interval = (int) $row->interval_hours;
        if ($interval < 1) {
            $interval = 1;
        }

        $base_ts = current_time('timestamp');

        if (in_array($status, ['feed_empty', 'no_new_items'], true)) {
            $seconds = 10 * MINUTE_IN_SECONDS;
        } else {
            $seconds = $interval * MINUTE_IN_SECONDS;
        }

        $next_ts = $base_ts + $seconds;

        $next_run = date('Y-m-d H:i:s', $next_ts);
        $last_run = current_time('mysql');

        $wpdb->update(
            $wpdb->prefix . 'pga_generator_runtime',
            [
                'last_run'    => $last_run,
                'next_run'    => $next_run,
                'last_status' => $status,
            ],
            ['generator_id' => $generator_id]
        );
    }
}
