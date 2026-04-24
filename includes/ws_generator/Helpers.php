<?php
if (!defined('ABSPATH')) exit;

class AlphaSuite_Helpers
{
    public static function alpha_storys_options()
    {
        $o = get_option('alpha_storys_options', []);
        return is_array($o) ? $o : [];
    }

    public static function alpha_opt($key, $default = null)
    {
        $opts = self::alpha_storys_options();
        return array_key_exists($key, $opts) ? $opts[$key] : $default;
    }

    public static function alpha_get_ga4_id(): string
    {
        $mode = self::alpha_opt('ga_mode', 'auto'); // auto|manual|off
        if ($mode === 'off') return '';
        if ($mode === 'manual') {
            $id = trim((string) self::alpha_opt('ga_manual_id', ''));
            return preg_match('/^G-[A-Z0-9\-]{4,}$/i', $id) ? $id : '';
        }
        $candidates = [
            'googlesitekit_analytics-4_settings',
            'googlesitekit_analytics-4',
            'googlesitekit_analytics_settings',
            'googlesitekit_gtag_settings',
        ];
        foreach ($candidates as $opt_name) {
            $opt = get_option($opt_name);
            if (is_array($opt)) {
                foreach (['measurementID', 'measurementId', 'measurement_id', 'ga4MeasurementId'] as $k) {
                    if (!empty($opt[$k]) && preg_match('/^G-[A-Z0-9\-]{4,}$/i', $opt[$k])) return $opt[$k];
                }
                $flat = json_decode(json_encode($opt), true);
                $it = new RecursiveIteratorIterator(new RecursiveArrayIterator($flat));
                foreach ($it as $v) {
                    if (is_string($v) && preg_match('/^G-[A-Z0-9\-]{4,}$/i', $v)) return $v;
                }
            }
        }
        $id = trim((string) self::alpha_opt('ga_manual_id', ''));
        return preg_match('/^G-[A-Z0-9\-]{4,}$/i', $id) ? $id : '';
    }
}
