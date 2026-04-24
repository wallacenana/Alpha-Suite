<?php
if (!defined('ABSPATH')) exit;

class AlphaSuite_Orion_Migrator
{
    const ORION_POST_TYPE = 'posts_orion';
    const TARGET_POST_TYPE = 'post';

    // meta pra marcar que já migrou (evita retrabalho)
    const META_MIGRATED = '_pga_migrated_to_post';

    // options pra notice
    const OPT_NOTICE_DISMISSED = 'pga_orion_migrate_notice_dismissed';
    const OPT_LEGACY_MIGRATED  = 'pga_orion_legacy_migrated_done';

    // actions
    const ACTION_MIGRATE_LEGACY = 'pga_orion_migrate_legacy';
    const ACTION_DISMISS_NOTICE = 'pga_orion_migrate_dismiss';

    // nonce
    const NONCE_ACTION = 'pga_orion_migrate_nonce';

    // lote
    const BATCH_LIMIT = 50;

    public static function init(): void
    {
        // 1) comportamento novo: converter quando publicar (se habilitado)
        add_action('transition_post_status', [__CLASS__, 'on_transition_status'], 10, 3);

        // 2) notice no admin para migração legada (opt-in)
        add_action('admin_notices', [__CLASS__, 'admin_notice_legacy_migration']);

        // 3) handlers (botões do notice)
        add_action('admin_post_' . self::ACTION_MIGRATE_LEGACY, [__CLASS__, 'handle_migrate_legacy']);
        add_action('admin_post_' . self::ACTION_DISMISS_NOTICE, [__CLASS__, 'handle_dismiss_notice']);
    }

    /**
     * NOVO: ao publicar, converte automático (se você quiser).
     * Observação: isso NÃO mexe em posts antigos. Só roda no evento do post.
     */
    public static function on_transition_status(string $new, string $old, \WP_Post $post): void
    {
        if ($new !== 'publish' || $old === 'publish') return;
        if (!$post || empty($post->ID)) return;
        if ($post->post_type !== self::ORION_POST_TYPE) return;

        // Se você quer condicionar por licença vitalícia, coloque aqui:
        // Exemplo:
        // $chk = AlphaSuite_License::check('alpha_orion');
        // if (empty($chk['ok']) || empty($chk['is_lifetime'])) return;
        //
        // Ou se você já tem uma função:
        // if (!AlphaSuite_License::is_lifetime('alpha_orion')) return;

        self::convert_orion_to_post((int)$post->ID);
    }

    /**
     * Helper único: converte 1 post Orion -> Post padrão.
     */
    public static function convert_orion_to_post(int $post_id): bool
    {
        $post_id = (int)$post_id;
        if ($post_id <= 0) return false;

        $pt = get_post_type($post_id);
        if ($pt !== self::ORION_POST_TYPE) return false;

        // já migrado?
        if (get_post_meta($post_id, self::META_MIGRATED, true)) return true;

        $r = wp_update_post([
            'ID'        => $post_id,
            'post_type' => self::TARGET_POST_TYPE,
        ], true);

        if (is_wp_error($r)) {
            // opcional: log
            return false;
        }

        update_post_meta($post_id, self::META_MIGRATED, 1);
        return true;
    }

    /**
     * Conta quantos posts "antigos" ainda estão em posts_orion e publicados,
     * e que ainda não foram migrados.
     */
    protected static function count_legacy_candidates(): int
    {
        $q = new \WP_Query([
            'post_type'      => self::ORION_POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => false,
            'meta_query'     => [
                [
                    'key'     => self::META_MIGRATED,
                    'compare' => 'NOT EXISTS',
                ]
            ],
        ]);

        return (int)($q->found_posts ?? 0);
    }

    /**
     * Notice opt-in: só aparece se houver legado a migrar e se não foi dispensado.
     */
    public static function admin_notice_legacy_migration(): void
    {
        if (!current_user_can('manage_options')) return;

        // se já marcou como "não mostrar mais"
        if (get_option(self::OPT_NOTICE_DISMISSED)) return;

        // se já migrou tudo (ou optou por concluir)
        if (get_option(self::OPT_LEGACY_MIGRATED)) return;

        $count = self::count_legacy_candidates();
        if ($count <= 0) {
            // se não tem mais nada, marca como done e não incomoda mais
            update_option(self::OPT_LEGACY_MIGRATED, 1, false);
            return;
        }

        $migrate_url = wp_nonce_url(
            admin_url('admin-post.php?action=' . self::ACTION_MIGRATE_LEGACY),
            self::NONCE_ACTION
        );

        $dismiss_url = wp_nonce_url(
            admin_url('admin-post.php?action=' . self::ACTION_DISMISS_NOTICE),
            self::NONCE_ACTION
        );

        $msg = sprintf(
            'Atualização do Órion: agora, posts publicados podem ser convertidos automaticamente para <strong>Posts</strong> padrão. Você tem <strong>%d</strong> posts antigos já publicados em <strong>%s</strong>. Deseja migrar agora?',
            $count,
            esc_html(self::ORION_POST_TYPE)
        );

        echo '<div class="notice notice-warning" style="padding:12px 12px 10px;">';
        echo '<p style="margin:0 0 8px;">' . wp_kses_post($msg) . '</p>';
        echo '<p style="margin:0; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">';
        echo '<a class="button button-primary" href="' . esc_url($migrate_url) . '">Migrar agora (até ' . (int)self::BATCH_LIMIT . ')</a>';
        echo '<a class="button" href="' . esc_url($dismiss_url) . '">Não migrar (dispensar)</a>';
        echo '<span style="color:#666;">Você pode clicar em migrar novamente para continuar em lotes.</span>';
        echo '</p>';
        echo '</div>';
    }

    /**
     * Clique em "Migrar agora": migra em lote (50).
     * Recarrega a página com mensagem.
     */
    public static function handle_migrate_legacy(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Sem permissão.');
        }

        check_admin_referer(self::NONCE_ACTION);

        $ids = get_posts([
            'post_type'      => self::ORION_POST_TYPE,
            'post_status'    => 'publish',
            'numberposts'    => self::BATCH_LIMIT,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => self::META_MIGRATED,
                    'compare' => 'NOT EXISTS',
                ]
            ],
        ]);

        $ok = 0;
        $fail = 0;

        foreach ($ids as $id) {
            if (self::convert_orion_to_post((int)$id)) $ok++;
            else $fail++;
        }

        // se acabou, marca como done
        $remaining = self::count_legacy_candidates();
        if ($remaining <= 0) {
            update_option(self::OPT_LEGACY_MIGRATED, 1, false);
        }

        // feedback simples (sem Swal)
        $redirect = add_query_arg([
            'pga_orion_migrated' => $ok,
            'pga_orion_failed'   => $fail,
            'pga_orion_left'     => max(0, $remaining),
        ], admin_url('admin.php?page=alpha-suite-orion-prompts')); // <-- ajuste pro seu slug de página

        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * Clique em "Não migrar": some o notice.
     */
    public static function handle_dismiss_notice(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Sem permissão.');
        }

        check_admin_referer(self::NONCE_ACTION);

        update_option(self::OPT_NOTICE_DISMISSED, 1, false);

        $redirect = admin_url('admin.php?page=alpha-suite-orion-prompts'); // <-- ajuste pro seu slug de página
        wp_safe_redirect($redirect);
        exit;
    }
}
