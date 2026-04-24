<?php

if (!defined('ABSPATH')) exit;

class AlphaSuite_Init
{
    public static function Init()
    {
        // Ajuste do ícone no menu (20x20)
        add_action('admin_head', function () {
            echo '<style>
    #adminmenu .toplevel_page_alpha-suite-dashboard .wp-menu-image img{
      width:16px;height:16px;object-fit:contain;opacity:1;
      padding-top: 0;
    }
      #adminmenu .toplevel_page_alpha-suite-dashboard .wp-menu-image {
      display: flex;
        justify-content: center;
        align-items: center;
      }
  </style>';
        });

        // MAIS VISUALIZADOS - TODOS OS TEMPOS
        add_action('elementor/query/most_viewed', function ($query) {

            global $wpdb;

            $table = $wpdb->prefix . 'pga_post_views';
            $current_id = get_queried_object_id();

            $ids = $wpdb->get_col("
        SELECT post_id
        FROM {$table}
        GROUP BY post_id
        ORDER BY COUNT(*) DESC
        LIMIT 20
    ");

            if (!empty($ids)) {

                $ids = array_values(array_unique(array_map('intval', $ids)));

                // remove post atual
                $ids = array_values(array_filter($ids, function ($id) use ($current_id) {
                    return $id != $current_id;
                }));

                if (!empty($ids)) {

                    $query->set('post__in', $ids);
                    $query->set('orderby', 'post__in');
                    $query->set('posts_per_page', 10);

                    return;
                }
            }

            // fallback
            $query->set('post__not_in', [$current_id]);
            $query->set('orderby', 'date');
            $query->set('posts_per_page', 10);
        });

        add_action('elementor/query/most_viewed_1_4', function ($query) {

            global $wpdb;

            $table = $wpdb->prefix . 'pga_post_views';
            $current_id = get_queried_object_id();

            $ids = $wpdb->get_col("
                SELECT post_id
                FROM {$table}
                GROUP BY post_id
                ORDER BY COUNT(*) DESC
                LIMIT 20
            ");

            $ids = array_values(array_unique(array_map('intval', $ids)));

            $ids = array_values(array_filter($ids, function ($id) use ($current_id) {
                return $id != $current_id;
            }));

            $ids = array_slice($ids, 1, 4);

            if (!empty($ids)) {

                $query->set('post__in', $ids);
                $query->set('orderby', 'post__in');
                $query->set('posts_per_page', 4);
            } else {

                $query->set('post__not_in', [$current_id]);
                $query->set('orderby', 'date');
                $query->set('posts_per_page', 4);
            }
        });
        add_action('elementor/query/most_viewed_0_1', function ($query) {

            global $wpdb;

            $table = $wpdb->prefix . 'pga_post_views';
            $current_id = get_queried_object_id();

            $ids = $wpdb->get_col("
                SELECT post_id
                FROM {$table}
                GROUP BY post_id
                ORDER BY COUNT(*) DESC
                LIMIT 20
            ");

            $ids = array_values(array_unique(array_map('intval', $ids)));

            $ids = array_values(array_filter($ids, function ($id) use ($current_id) {
                return $id != $current_id;
            }));

            $ids = array_slice($ids, 0, 1);

            if (!empty($ids)) {

                $query->set('post__in', $ids);
                $query->set('orderby', 'post__in');
                $query->set('posts_per_page', 4);
            } else {

                $query->set('post__not_in', [$current_id]);
                $query->set('orderby', 'date');
                $query->set('posts_per_page', 4);
            }
        });

        // MAIS VISUALIZADOS - ÚLTIMOS 7 DIAS
        add_action('elementor/query/most_viewed_week', function ($query) {

            global $wpdb;

            $table = $wpdb->prefix . 'pga_post_views';

            $ids = $wpdb->get_col("
                SELECT post_id
                FROM {$table}
                WHERE viewed_at >= NOW() - INTERVAL 7 DAY
                GROUP BY post_id
                ORDER BY COUNT(*) DESC
                LIMIT 10
            ");

            if (!empty($ids)) {
                $query->set('post__in', $ids);
                $query->set('orderby', 'post__in');
            } else {
                $query->set('orderby', 'date');
            }
        });


        // MAIS VISUALIZADOS - ÚLTIMAS 24H
        add_action('elementor/query/most_viewed_today', function ($query) {

            global $wpdb;

            $table = $wpdb->prefix . 'pga_post_views';

            $ids = $wpdb->get_col("
        SELECT post_id
        FROM {$table}
        WHERE viewed_at >= NOW() - INTERVAL 1 DAY
        GROUP BY post_id
        ORDER BY COUNT(*) DESC
        LIMIT 10
    ");

            if (!empty($ids)) {
                $query->set('post__in', $ids);
                $query->set('orderby', 'post__in');
            } else {
                $query->set('orderby', 'date');
            }
        });
    }
}
