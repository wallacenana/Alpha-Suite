<?php
// includes/License.php
if (!defined('ABSPATH')) exit;

class AlphaSuite_License
{
    const OPTION_KEY = 'pga_client_license';
    const CRON_HOOK  = 'plugins_alpha_license_daily_check';
    const PGA_LICENSE_API_BASE  = 'https://pluginsalpha.com/wp-json/pga-admin/v1';

    /**
     * Base da API do ADMIN (servidor central de licenças).
     * Pode ser sobrescrito com:
     *  - const PGA_LICENSE_API_BASE
     *  - filtro 'plugins_alpha/license_api_base'
     */
    public static function api_base(): string
    {
        return 'https://pluginsalpha.com/wp-json/pga-admin/v1';
    }
    
    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'menu']);
        add_action('admin_post_pga_activate_license', [self::class, 'handle_activate']);
        add_action('admin_post_pga_deactivate_license', [self::class, 'handle_deactivate']);
        add_action(self::CRON_HOOK, [self::class, 'cron_check']);
    }

    public static function menu(): void
    {
        add_submenu_page(
            'alpha-suite-dashboard',
            __('Licença', 'alpha-suite'),
            __('Licença', 'alpha-suite'),
            'manage_options',
            'alpha-suite-license',
            [self::class, 'render_page']
        );
    }

    // ===================== Helpers de estado =====================

    public static function get_state(): array
    {
        $opt = get_option(self::OPTION_KEY, []);
        if (!is_array($opt)) $opt = [];

        return [
            'license_key'  => (string)($opt['license_key'] ?? ''),
            'purchase_id'  => (string)($opt['purchase_id'] ?? ''), // legado
            'purchase_ids' => is_array($opt['purchase_ids'] ?? null) ? $opt['purchase_ids'] : [],

            'purchase_id_orion'   => (string)($opt['purchase_id_orion'] ?? ''),
            'purchase_id_stories' => (string)($opt['purchase_id_stories'] ?? ''),

            'email'       => (string)($opt['email'] ?? ''),
            'status'      => (string)($opt['status'] ?? 'inactive'),

            'plan'        => (string)($opt['plan'] ?? ''),
            'expires_at'  => $opt['expires_at'] ?? null,

            'modules'     => is_array($opt['modules'] ?? null) ? $opt['modules'] : [],
            'products'    => is_array($opt['products'] ?? null) ? $opt['products'] : [],
            'licenses'    => is_array($opt['licenses'] ?? null) ? $opt['licenses'] : [],

            'domains_used' => is_array($opt['domains_used'] ?? null) ? $opt['domains_used'] : [],
            'max_domains' => (int)($opt['max_domains'] ?? 1),

            'last_check'  => $opt['last_check'] ?? null,
        ];
    }

    private static function view_data(array $lic): array
    {
        // Purchase IDs (prioriza novo)
        $purchaseIds = [];
        if (!empty($lic['purchase_ids']) && is_array($lic['purchase_ids'])) {
            $purchaseIds = array_values(array_filter(array_map('strval', $lic['purchase_ids'])));
        } elseif (!empty($lic['purchase_id'])) {
            $purchaseIds = [(string)$lic['purchase_id']];
        }

        // Agregação via licenses[] (novo formato)
        $plans = [];
        $products = is_array($lic['products'] ?? null) ? $lic['products'] : [];
        $modules  = is_array($lic['modules'] ?? null) ? $lic['modules'] : [];
        $domainUsedCount = 0;
        $domainMaxSum = 0;

        $expiresCandidates = []; // timestamps
        $hasLifetime = false;

        $licenses = is_array($lic['licenses'] ?? null) ? $lic['licenses'] : [];
        foreach ($licenses as $row) {
            if (empty($row['ok']) || empty($row['license']) || !is_array($row['license'])) continue;
            $L = $row['license'];

            // plano (se vier)
            if (!empty($L['plan'])) $plans[] = (string)$L['plan'];

            // expiração (se vier)
            if (!empty($L['expires_at'])) {
                $ts = strtotime((string)$L['expires_at']);
                if ($ts) $expiresCandidates[] = $ts;
            }
            if (!empty($L['plan']) && (string)$L['plan'] === 'lifetime') {
                $hasLifetime = true;
            }

            // domínios (se vier)
            if (isset($L['max_domains'])) $domainMaxSum += max(0, (int)$L['max_domains']);
            if (!empty($L['domains_used']) && is_array($L['domains_used'])) {
                $domainUsedCount += count($L['domains_used']);
            }

            // módulos/produtos também podem vir por licença (se existir no seu admin)
            if (!empty($L['modules']) && is_array($L['modules'])) {
                $modules = array_values(array_unique(array_merge($modules, $L['modules'])));
            }
            if (!empty($L['products']) && is_array($L['products'])) {
                $products = array_values(array_unique(array_merge($products, $L['products'])));
            }
        }

        // Se o admin está mandando prod_ dentro de "modules", separa:
        $realModules = [];
        $prodIdsFromModules = [];
        foreach ($modules as $m) {
            $m = (string)$m;
            if (strpos($m, 'prod_') === 0) $prodIdsFromModules[] = $m;
            else $realModules[] = $m;
        }
        $modules = array_values(array_unique($realModules));
        $products = array_values(array_unique(array_merge($products, $prodIdsFromModules)));

        // Plano exibido: lista única (ou —)
        $plans = array_values(array_unique(array_filter(array_map('strval', $plans))));
        $planText = '—';
        if ($hasLifetime) {
            $planText = 'lifetime';
        } elseif (!empty($plans)) {
            $planText = implode(', ', $plans);
        } elseif (!empty($lic['plan'])) {
            $planText = (string)$lic['plan'];
        }

        // Expira: se lifetime -> Vitalício; senão menor expiração
        $expiresText = '—';
        if ($hasLifetime) {
            $expiresText = 'Vitalício';
        } else {
            $minTs = !empty($expiresCandidates) ? min($expiresCandidates) : 0;
            if ($minTs) {
                $expiresText = date_i18n('d/m/Y H:i', $minTs);
            } elseif (!empty($lic['expires_at'])) {
                $ts = strtotime((string)$lic['expires_at']);
                if ($ts) $expiresText = date_i18n('d/m/Y H:i', $ts);
            }
        }

        // Domínios: se não veio por licença, cai no legado
        $used = $domainUsedCount > 0 ? $domainUsedCount : (is_array($lic['domains_used']) ? count($lic['domains_used']) : 0);
        $max  = $domainMaxSum > 0 ? $domainMaxSum : (!empty($lic['max_domains']) ? (int)$lic['max_domains'] : 1);

        return [
            'purchase_ids_text' => !empty($purchaseIds) ? implode(', ', $purchaseIds) : '—',
            'plan_text'         => $planText,
            'expires_text'      => $expiresText,
            'domains_text'      => "{$used} / {$max}",
            'modules'           => $modules,
            'products'          => $products,
        ];
    }



    // ===================== API simplificada para outros módulos (Webhook etc.) =====================

    /**
     * Retorna o array CRU salvo na OPTION_KEY.
     * Útil para guardar metadados extras (buyer_email, product_id, webhook_token etc).
     */
    public static function get(): array
    {
        $opt = get_option(self::OPTION_KEY, []);
        return is_array($opt) ? $opt : [];
    }

    /**
     * Faz merge dos dados atuais com os novos e salva.
     * Não apaga chaves antigas que não foram enviadas.
     */
    public static function set(array $data): void
    {
        $current = self::get();
        $merged  = array_merge($current, $data);

        // Garantir formatos mínimos que o get_state() espera (defensivo)
        if (!isset($merged['modules']) || !is_array($merged['modules'])) {
            $merged['modules'] = [];
        }

        if (!isset($merged['domains_used']) || !is_array($merged['domains_used'])) {
            $merged['domains_used'] = [];
        }

        if (!isset($merged['max_domains'])) {
            $merged['max_domains'] = 1;
        }

        update_option(self::OPTION_KEY, $merged);
    }

    public static function is_active(?array $lic = null): bool
    {
        if ($lic === null) $lic = self::get_state();

        if (($lic['status'] ?? '') !== 'active') return false;

        if (!empty($lic['expires_at'])) {
            $ts = strtotime((string)$lic['expires_at']);
            if ($ts && $ts < time()) return false;
        }
        return true;
    }

    /**
     * Verifica se o módulo (ex.: 'alpha_stories') está liberado para este site.
     */
    public static function has_module(string $module): bool
    {
        $lic = self::get_state();
        return self::is_active() && in_array($module, $lic['modules'], true);
    }

    /**
     * Domínio atual normalizado (sem http, sem path).
     */
    public static function current_domain(): string
    {
        $url = home_url('/');
        $url = preg_replace('#^https?://#i', '', $url);
        $slash = strpos($url, '/');
        if ($slash !== false) $url = substr($url, 0, $slash);
        return strtolower(trim($url));
    }

    /**
     * Revalida a licença 1x por dia com o servidor central.
     * Se der certo, atualiza dados (expiração, módulos, etc).
     * Se der erro, só loga – não derruba a licença local.
     */
    public static function cron_check(): void
    {
        $lic = self::get_state();

        // pega ids separados (novo) + legado
        $purchase_orion   = (string)($lic['purchase_id_orion'] ?? '');
        $purchase_stories = (string)($lic['purchase_id_stories'] ?? '');
        $purchase_legacy  = (string)($lic['purchase_id'] ?? '');

        $purchase_ids = array_values(array_filter(array_unique(array_map('trim', [
            $purchase_orion,
            $purchase_stories,
            $purchase_legacy,
        ]))));

        // Se nem tem licença salva, não faz nada
        if (empty($purchase_ids) && empty($lic['license_key'])) {
            return;
        }

        $domain = self::current_domain();

        $body = [
            'email'          => $lic['email'] ?? '',
            // novo formato
            'purchase_ids'   => $purchase_ids,
            // compat legado (se o servidor ainda aceitar/usar)
            'purchase_id'    => $purchase_legacy,
            'license_key'    => $lic['license_key'] ?? '',
            'domain'         => $domain,
            'site_url'       => home_url('/'),
            'site_name'      => get_bloginfo('name'),
            'wp_version'     => get_bloginfo('version'),
            'php_version'    => PHP_VERSION,
            'plugin'         => 'alpha-suite',
            'plugin_version' => '1.0.1',
            // dica: você pode mandar um flag pro server não "consumir slot" no cron,
            // se você implementar isso depois:
            // 'mode' => 'check',
        ];

        $res = self::remote_call('/client/activate', $body);

        if (is_array($res) && isset($res['ok']) && $res['ok'] === false) {
            $msg = (string)($res['error'] ?? $res['message'] ?? 'Falha ao ativar licença.');
            if (wp_doing_ajax()) {
                wp_send_json_error(['message' => $msg, 'data' => $res], 400);
            }
            self::redirect_with_error($msg);
        }

        if (is_wp_error($res)) {
            return;
        }

        if (empty($res['ok'])) {
            return;
        }

        // --- NOVO: resposta agregada (licenses[])
        $licenses = [];
        if (!empty($res['licenses']) && is_array($res['licenses'])) {
            $licenses = $res['licenses'];
        }

        $any_ok = false;
        $domains_used = $lic['domains_used'] ?? [];
        $max_domains  = (int)($lic['max_domains'] ?? 1);
        $expires_at   = $lic['expires_at'] ?? null;
        $plan         = (string)($lic['plan'] ?? '');

        // tenta puxar alguns campos de uma licença "ok" (a primeira ok)
        foreach ($licenses as $r) {
            if (!empty($r['ok']) && !empty($r['license']) && is_array($r['license'])) {
                $any_ok = true;

                $l = $r['license'];

                if (is_array($l['domains_used'] ?? null)) {
                    $domains_used = $l['domains_used'];
                }
                if (isset($l['max_domains'])) {
                    $max_domains = (int)$l['max_domains'];
                }
                if (!empty($l['expires_at'])) {
                    $expires_at = $l['expires_at'];
                }
                if (!empty($l['plan'])) {
                    $plan = (string)$l['plan'];
                }

                break;
            }
        }

        // compat: se o servidor ainda retornar o formato antigo {license:{}}
        if (empty($licenses) && !empty($res['license']) && is_array($res['license'])) {
            $any_ok = true;
            $l = $res['license'];

            if (is_array($l['domains_used'] ?? null)) {
                $domains_used = $l['domains_used'];
            }
            if (isset($l['max_domains'])) {
                $max_domains = (int)$l['max_domains'];
            }
            if (!empty($l['expires_at'])) {
                $expires_at = $l['expires_at'];
            }
            if (!empty($l['plan'])) {
                $plan = (string)$l['plan'];
            }
        }

        // módulos agregados (preferencial)
        $modules = $lic['modules'] ?? [];
        if (!empty($res['modules']) && is_array($res['modules'])) {
            $modules = $res['modules'];
        } elseif (!empty($res['license']['modules']) && is_array($res['license']['modules'])) {
            $modules = $res['license']['modules'];
        }

        $opt = [
            'license_key'         => (string)($lic['license_key'] ?? ''),
            'purchase_id'         => $purchase_legacy, // mantém por compat
            'purchase_id_orion'   => $purchase_orion,
            'purchase_id_stories' => $purchase_stories,
            'email'               => (string)($lic['email'] ?? ''),
            'status'      => $any_ok ? 'active' : (string)($lic['status'] ?? 'inactive'),
            'plan'        => $plan,
            'modules'     => is_array($modules) ? $modules : [],
            'domains_used' => is_array($domains_used) ? $domains_used : [],
            'max_domains' => $max_domains > 0 ? $max_domains : 1,
            'expires_at'  => $expires_at,
            'last_check'  => current_time('mysql', true),

            // opcional: salvar debug do retorno agregado
            // 'last_results' => $licenses,
        ];

        update_option(self::OPTION_KEY, $opt);
    }


    public static function schedule_cron(): void
    {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            // agenda para daqui 1 hora e depois todo dia
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK);
        }
    }

    public static function clear_cron(): void
    {
        $ts = wp_next_scheduled(self::CRON_HOOK);
        if ($ts) {
            wp_unschedule_event($ts, self::CRON_HOOK);
        }
    }

    // ===================== UI =====================

    public static function render_page(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Sem permissão.', 'alpha-suite'));
        }


        // 2) Carrega estado atual
        $lic    = self::get_state();
        $domain = self::current_domain();
        $view = self::view_data($lic);

        $status_label = self::is_active()
            ? __('Ativa', 'alpha-suite')
            : __('Inativa', 'alpha-suite');

        $status_class = self::is_active()
            ? 'pga-badge-active'
            : 'pga-badge-inactive';

        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $error = isset($_GET['error'])
            ? sanitize_text_field(wp_unslash($_GET['error']))
            : '';

        $updated = isset($_GET['updated'])
            ? sanitize_text_field(wp_unslash($_GET['updated']))
            : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        if ($error) {
            echo '<div class="notice notice-error is-dismissible"><p>' .
                esc_html($error) .
                '</p></div>';
        }

        if ($updated) {
            echo '<div class="notice notice-success is-dismissible"><p>' .
                esc_html__('Licença atualizada com sucesso.', 'alpha-suite') .
                '</p></div>';
        }

        // === daqui pra baixo fica o teu HTML da tela de licença ===
?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Licença — Alpha Suite', 'alpha-suite'); ?></h1>

            <p style="margin-top:8px; color:#555;">
                <?php printf(
                    esc_html('Este site: %s (domínio usado para ativação)', 'alpha-suite'),
                    '<code>' . esc_html($domain) . '</code>'
                ); ?>
            </p>

            <div style="display:flex; gap:24px; align-items:flex-start; margin-top:20px;">

                <!-- STATUS -->
                <div style="flex:1; min-width:260px;">
                    <div style="background:#fff;border:1px solid #ccd0d4;border-radius:8px;padding:16px;">
                        <h2 style="margin-top:0;"><?php esc_html_e('Status da licença', 'alpha-suite'); ?></h2>

                        <p>
                            <span class="<?php echo esc_attr($status_class); ?>" style="display:inline-block;padding:2px 8px;border-radius:999px;font-weight:600;font-size:12px;
                    <?php echo self::is_active()
                        ? 'background:#46b450;color:#fff;'
                        : 'background:#dc3232;color:#fff;'; ?>">
                                <?php echo esc_html($status_label); ?>
                            </span>
                        </p>

                        <table class="form-table">
                            <tr>
                                <th><?php esc_html_e('E-mail', 'alpha-suite'); ?></th>
                                <td><code><?php echo esc_html($lic['email'] ?: '—'); ?></code></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Domínios usados', 'alpha-suite'); ?></th>
                                <td><code><?php echo esc_html($view['domains_text']); ?></code></td>
                            </tr>
                        </table>


                        <?php if (!empty($lic['modules'])): ?>
                            <h3><?php esc_html_e('Módulos liberados para essa licença', 'alpha-suite'); ?></h3>
                            <ul style="list-style:disc;margin-left:20px;">
                                <?php
                                $labels = self::module_labels();
                                foreach ($lic['modules'] as $m):
                                    $slug  = (string)$m;
                                    $label = $labels[$slug] ?? $slug;
                                ?>
                                    <li>
                                        <?php echo esc_html($label); ?>
                                        <span style="color:#777;font-size:11px;">(<?php echo esc_html($slug); ?>)</span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- FORMULÁRIO -->
                <div style="flex:1; min-width:320px;">
                    <div style="background:#fff;border:1px solid #ccd0d4;border-radius:8px;padding:16px;">
                        <h2 style="margin-top:0;">
                            <?php echo self::is_active()
                                ? esc_html__('Reativar / trocar licença', 'alpha-suite')
                                : esc_html__('Ativar licença', 'alpha-suite'); ?>
                        </h2>

                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('pga_activate_license'); ?>
                            <input type="hidden" name="action" value="pga_activate_license">

                            <table class="form-table">
                                <tr>
                                    <th><label for="pga_email"><?php esc_html_e('E-mail da compra', 'alpha-suite'); ?></label></th>
                                    <td>
                                        <input type="email"
                                            name="email"
                                            autocomplete="off"
                                            id="pga_email"
                                            class="regular-text"
                                            value="<?php echo esc_attr($lic['email'] ?? ''); ?>"
                                            required>
                                        <p class="description">
                                            <?php esc_html_e('O mesmo e-mail usado na compra (Hotmart / Stripe).', 'alpha-suite'); ?>
                                        </p>
                                    </td>
                                </tr>

                                <tr>
                                    <th><label for="pga_purchase_orion"><?php esc_html_e('1º ID da compra', 'alpha-suite'); ?></label></th>
                                    <td>
                                        <input type="text"
                                            name="purchase_id_orion"
                                            id="pga_purchase_orion"
                                            class="regular-text"
                                            value="<?php echo esc_attr($lic['purchase_id_orion'] ?? ''); ?>">
                                        <p class="description">
                                            <?php esc_html_e('Informe o ID da compra para o módulo Alpha Órion (deixe em branco se não tiver).', 'alpha-suite'); ?>
                                        </p>
                                    </td>
                                </tr>

                                <tr>
                                    <th><label for="pga_purchase_stories"><?php esc_html_e('2º ID da compra', 'alpha-suite'); ?></label></th>
                                    <td>
                                        <input type="text"
                                            name="purchase_id_stories"
                                            id="pga_purchase_stories"
                                            class="regular-text"
                                            value="<?php echo esc_attr($lic['purchase_id_stories'] ?? ''); ?>">
                                        <p class="description">
                                            <?php esc_html_e('Informe o ID da compra para o módulo Alpha Stories (deixe em branco se não tiver).', 'alpha-suite'); ?>
                                        </p>
                                    </td>
                                </tr>

                                <!-- <tr>
                                    <th><label for="pga_license_key"><?php esc_html_e('Chave de licença (opcional)', 'alpha-suite'); ?></label></th>
                                    <td>
                                        <input type="text"
                                            name="license_key"
                                            id="pga_license_key"
                                            class="regular-text"
                                            value="<?php echo esc_attr($lic['license_key'] ?? ''); ?>">
                                        <p class="description">
                                            <?php esc_html_e('Se o painel Alpha Suite gerar uma chave própria, use aqui. Caso contrário, pode deixar em branco.', 'alpha-suite'); ?>
                                        </p>
                                    </td>
                                </tr> -->
                            </table>

                            <?php submit_button(
                                self::is_active()
                                    ? esc_html__('Revalidar licenças neste domínio', 'alpha-suite')
                                    : esc_html__('Ativar licenças neste domínio', 'alpha-suite'),
                                'primary'
                            ); ?>
                        </form>

                        <?php if (!empty($lic['status']) && $lic['status'] !== 'inactive'): ?>
                            <hr>
                            <h3><?php esc_html_e('Desativar neste site', 'alpha-suite'); ?></h3>
                            <p><?php esc_html_e('Isso libera o slot deste domínio no painel do Alpha Suite, permitindo ativar em outro site (se o plano permitir).', 'alpha-suite'); ?></p>

                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                                onsubmit="return confirm('<?php echo esc_js(__('Desativar a licença neste site?', 'alpha-suite')); ?>');">
                                <?php wp_nonce_field('pga_deactivate_license'); ?>
                                <input type="hidden" name="action" value="pga_deactivate_license">
                                <?php submit_button(
                                    esc_html__('Desativar neste site', 'alpha-suite'),
                                    'secondary'
                                ); ?>
                            </form>
                        <?php endif; ?>

                    </div>
                </div>

            </div>
        </div>
<?php
    }

    /**
     * Valida a licença e (opcionalmente) um módulo específico.
     *
     * @param string $module_slug Ex.: 'alpha_stories', 'alpha_orion_posts' ou '' para só validar a licença.
     * @return array {
     *   ok      => bool,
     *   code    => string (ok|licenca_inativa|licenca_expirada|modulo_indisponivel),
     *   message => string (para exibir pro usuário, se quiser),
     * }
     */
    public static function check(string $module_slug = ''): array
    {
        $lic = self::get_state();

        // 1) licença inativa / sem dados
        if (empty($lic['status']) || $lic['status'] !== 'active') {
            return [
                'ok'      => false,
                'code'    => 'licenca_inativa',
                'message' => __('Licença inativa. Ative ou revalide a licença no painel Alpha Suite.', 'alpha-suite'),
            ];
        }

        // 2) expirada
        if (!empty($lic['expires_at'])) {
            $ts = strtotime($lic['expires_at']);
            if ($ts && $ts < time()) {
                return [
                    'ok'      => false,
                    'code'    => 'licenca_expirada',
                    'message' => __('Licença expirada. Renove ou atualize sua assinatura.', 'alpha-suite'),
                ];
            }
        }

        // 3) módulo não disponível no plano
        if ($module_slug !== '') {
            $modules = is_array($lic['modules'] ?? null) ? $lic['modules'] : [];
            if (!in_array($module_slug, $modules, true)) {
                return [
                    'ok'      => false,
                    'code'    => 'modulo_indisponivel',
                    'message' => __('Este módulo não está disponível no seu plano.', 'alpha-suite'),
                ];
            }
        }

        return [
            'ok'      => true,
            'code'    => 'ok',
            'message' => '',
        ];
    }


    /**
     * Traduz status do Hotmart para o status interno da licença.
     * - $purchaseStatus: status da compra (APPROVED, COMPLETED, CANCELLED, REFUNDED, etc.)
     * - $subscriptionStatus: status da assinatura (ACTIVE, CANCELLED, EXPIRED, etc.), se existir
     *
     * Importante: o resto do plugin só considera "active" como licença válida.
     */
    public static function map_hotmart_status(string $purchaseStatus, string $subscriptionStatus = ''): string
    {
        $p = strtoupper(trim($purchaseStatus));
        $s = strtoupper(trim($subscriptionStatus));

        // Casos que derrubam a licença de cara (cancelado, estornado, chargeback etc.)
        if (in_array($p, ['REFUNDED', 'CHARGEBACK', 'CANCELLED'], true)) {
            return 'inactive';
        }

        if (in_array($s, ['CANCELLED', 'EXPIRED', 'SUSPENDED', 'INACTIVE'], true)) {
            return 'inactive';
        }

        // Casos em que consideramos a licença ativa
        if (in_array($p, ['APPROVED', 'COMPLETED', 'CONFIRMED', 'ACTIVE'], true)) {
            return 'active';
        }

        // Pagamento em análise / aguardando → tratamos como "pending" (não é ativa ainda)
        if (in_array($p, ['PENDING', 'WAITING_PAYMENT', 'IN_ANALYSIS', 'IN_PROCESS'], true)) {
            return 'pending';
        }

        // Fallback: qualquer coisa desconhecida é inativa
        return 'inactive';
    }


    /**
     * Atalho simples: retorna só boolean.
     * Ex.: AlphaSuite_License::is_module_available('alpha_stories')
     */
    public static function is_module_available(string $module_slug): bool
    {
        $r = self::check($module_slug);
        return !empty($r['ok']);
    }


    public static function module_labels(): array
    {
        return [
            'alpha_stories'   => __('Alpha Stories', 'alpha-suite'),
            'alpha_orion' => __('Alpha Órion', 'alpha-suite'),
        ];
    }

    // ===================== Handlers =====================

    public static function handle_activate(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Sem permissão.');
        }
        check_admin_referer('pga_activate_license');

        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';

        $purchase_orion = isset($_POST['purchase_id_orion']) ? sanitize_text_field(wp_unslash($_POST['purchase_id_orion'])) : '';
        $purchase_stories = isset($_POST['purchase_id_stories']) ? sanitize_text_field(wp_unslash($_POST['purchase_id_stories'])) : '';

        $license_key = isset($_POST['license_key']) ? sanitize_text_field(wp_unslash($_POST['license_key'])) : '';

        $purchase_ids = array_values(array_unique(array_filter([
            $purchase_orion,
            $purchase_stories,
        ], function ($v) {
            return is_string($v) && trim($v) !== '';
        })));

        if (!$email || empty($purchase_ids)) {
            self::redirect_with_error(__('Informe o e-mail e pelo menos um ID de compra (Órion ou Stories).', 'alpha-suite'));
        }

        $domain = self::current_domain();

        $body = [
            'email'          => $email,
            'purchase_ids'   => $purchase_ids,
            'license_key'    => $license_key,
            'domain'         => $domain,
            'site_url'       => home_url('/'),
            'site_name'      => get_bloginfo('name'),
            'wp_version'     => get_bloginfo('version'),
            'php_version'    => PHP_VERSION,
            'plugin'         => 'alpha-suite',
            'plugin_version' => '1.0.1',
        ];

        $res = self::remote_call('/client/activate', $body);

        // 0) Erro de transporte / resposta inválida (WP_Error)
        if (is_wp_error($res)) {
            self::redirect_with_error($res->get_error_message());
        }

        // 1) Resposta sem ok => erro
        if (!is_array($res) || empty($res['ok'])) {
            // tenta extrair msg dos padrões do WP REST: { code, message, data:{...} }
            $msg = (string)($res['message'] ?? __('Falha ao ativar licença.', 'alpha-suite'));

            // se tiver results detalhado (seu caso), pega a 1a msg
            if (!empty($res['data']['results'][0]['message'])) {
                $msg = (string)$res['data']['results'][0]['message'];
            }

            self::redirect_with_error($msg);
        }

        // 2) NOVO FORMATO preferido: { ok:true, modules:[], products:[], licenses:[] }
        if (!empty($res['licenses']) && is_array($res['licenses'])) {
            $opt = [
                'email'       => (string)($res['email'] ?? $email),
                'status'      => 'active',
                'plan'        => '',

                'modules'     => is_array($res['modules'] ?? null) ? array_values(array_unique($res['modules'])) : [],
                'products'    => is_array($res['products'] ?? null) ? array_values(array_unique($res['products'])) : [],
                'licenses'    => $res['licenses'],

                'domains_used' => [],
                'max_domains'  => 0,
                'expires_at'   => null,

                'purchase_ids' => $purchase_ids,

                'license_key'         => $license_key,
                'purchase_id_orion'   => $purchase_orion,
                'purchase_id_stories' => $purchase_stories,

                'last_check' => current_time('mysql', true),
            ];

            update_option(self::OPTION_KEY, $opt);

            wp_safe_redirect(add_query_arg(
                ['page' => 'alpha-suite-license', 'updated' => 1],
                admin_url('admin.php')
            ));
            exit;
        }

        // 3) Se ok=true mas não veio licenses[] => considera erro de formato (pra não dar “sucesso fake”)
        self::redirect_with_error(__('Resposta inesperada do servidor de licenças.', 'alpha-suite'));
    }

    public static function handle_deactivate(): void
    {
        if (!current_user_can('manage_options')) wp_die('Sem permissão.');
        check_admin_referer('pga_deactivate_license');

        $lic    = self::get_state();
        $domain = self::current_domain();

        $purchase_ids = [];
        if (!empty($lic['purchase_ids']) && is_array($lic['purchase_ids'])) {
            $purchase_ids = $lic['purchase_ids'];
        } elseif (!empty($lic['purchase_id'])) {
            $purchase_ids = [(string)$lic['purchase_id']];
        }

        $body = [
            'email'        => (string)($lic['email'] ?? ''),
            'purchase_ids' => $purchase_ids,
            'license_key'  => (string)($lic['license_key'] ?? ''),
            'domain'       => $domain,
        ];

        $res = self::remote_call('/client/deactivate', $body);

        // independente do retorno, remove local
        delete_option(self::OPTION_KEY);

        wp_safe_redirect(add_query_arg(
            ['page' => 'alpha-suite-license', 'updated' => 1],
            admin_url('admin.php')
        ));
        exit;
    }

    // ===================== HTTP =====================

    private static function remote_call(string $endpoint, array $body)
    {
        $base = self::api_base();
        $url  = rtrim($base, '/') . $endpoint;

        $args = [
            'timeout' => 15,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
            'body' => wp_json_encode($body, JSON_UNESCAPED_UNICODE),
        ];

        $response = wp_remote_post($url, $args);

        // erro de transporte
        if (is_wp_error($response)) {
            return new \WP_Error(
                'pga_license_http',
                sprintf(
                    __('Erro ao conectar ao servidor de licença', 'alpha-suite')
                ),
                ['transport' => true]
            );
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $raw  = (string) wp_remote_retrieve_body($response);

        // tenta JSON sempre
        $json = null;
        if ($raw !== '') $json = json_decode($raw, true);

        // se veio JSON, devolve (mesmo 403/400)
        if (is_array($json)) {
            $json['_http_code'] = $code;
            return $json;
        }

        // sem JSON => resposta inválida
        $snippet = mb_substr($raw, 0, 300);
        $msg = __('Resposta inválida do servidor de licença.', 'alpha-suite') . ' (HTTP ' . $code . ')';

        return new \WP_Error(
            'pga_license_bad_response',
            $msg,
            ['code' => $code, 'body_snippet' => $snippet, 'raw' => $raw]
        );
    }

    // ===================== Redirect helpers =====================

    private static function redirect_with_error(string $msg): void
    {
        $location = add_query_arg(
            [
                'page'  => 'alpha-suite-license',
                'error' => rawurlencode($msg),
            ],
            admin_url('admin.php')
        );

        wp_safe_redirect($location);
        exit;
    }
}
