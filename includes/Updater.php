<?php
if (!defined('ABSPATH')) exit;

class AlphaSuite_Updater
{
    protected static string $plugin_file = '';
    protected static string $slug        = 'alpha-suite';

    public static function init(string $plugin_file): void
    {
        // Caminho tipo: "plugins-alpha/plugins-alpha.php"
        self::$plugin_file = plugin_basename($plugin_file);

        // Slug = pasta do plugin
        $parts = explode('/', self::$plugin_file);
        if (!empty($parts[0])) {
            self::$slug = $parts[0];
        }

        // Hook que o WP chama quando atualiza a lista de updates
        add_filter('pre_set_site_transient_update_plugins', [self::class, 'check_for_update']);
    }

    protected static function get_current_version(): string
    {
        // 1) tenta ler a versão direto do cabeçalho do plugin (Version: X.Y.Z)
        if (!empty(self::$plugin_file)) {
            $file = WP_PLUGIN_DIR . '/' . self::$plugin_file;

            if (file_exists($file)) {
                // garante que a função existe (no admin sempre existe, mas por via das dúvidas)
                if (!function_exists('get_file_data')) {
                    require ABSPATH . 'wp-admin/includes/plugin.php';
                }

                $data = get_file_data($file, ['Version' => 'Version'], 'plugin');

                if (!empty($data['Version'])) {
                    return (string) $data['Version'];
                }
            }
        }

        // 2) fallback: se por algum motivo der ruim, usa a constante (se existir)
        if (defined('PLUGINS_ALPHA_VERSION')) {
            return (string) PLUGINS_ALPHA_VERSION;
        }

        // 3) fallback final
        return '1.0.0';
    }

    protected static function get_remote_info(): ?array
    {
        $url = 'https://pluginsalpha.com/wp-json/pga-admin/v1/client/plugin-update';

        $body = [
            'plugin_slug' => self::$slug,
            'version'     => self::get_current_version(),
        ];


        $r = wp_remote_post($url, [
            'timeout' => 15,
            'body'    => $body,
        ]);

        if (is_wp_error($r)) {
            return null;
        }

        $code = wp_remote_retrieve_response_code($r);
        $raw  = wp_remote_retrieve_body($r);

        if ($code !== 200) {
            return null;
        }

        $json = json_decode($raw, true);
        if (!is_array($json) || empty($json['ok'])) {
            return null;
        }

        return $json;
    }

    public static function check_for_update($transient)
    {
        // garante objeto
        if (!is_object($transient)) {
            $transient = new stdClass();
        }
        if (empty($transient->checked) || !is_array($transient->checked)) {
            return $transient;
        }

        // se o WP não conhece esse plugin, sai
        if (!isset($transient->checked[self::$plugin_file])) {
            return $transient;
        }

        $current = self::get_current_version();
        $remote  = self::get_remote_info();

        if (!$remote || empty($remote['version']) || empty($remote['download_url'])) {
            return $transient;
        }

        $remote_version = (string) $remote['version'];

        if (version_compare($remote_version, $current, '<=')) {
            unset($transient->response[self::$plugin_file]);
            return $transient;
        }

        // monta o objeto que o WP espera
        $obj              = new stdClass();
        $obj->slug        = self::$slug;
        $obj->plugin      = self::$plugin_file;
        $obj->new_version = $remote_version;
        $obj->package     = (string) $remote['download_url'];
        $obj->url         = (string) ($remote['homepage'] ?? '');
        $icons = [];
        if (!empty($remote['icon_1x'])) {
            $icons['1x'] = (string) $remote['icon_1x'];
        }
        if (!empty($remote['icon_2x'])) {
            $icons['2x'] = (string) $remote['icon_2x'];
        }
        if ($icons) {
            $obj->icons = $icons;
        }

        // registra update
        $transient->response[self::$plugin_file] = $obj;

        return $transient;
    }
}
