<?php
if (!defined('ABSPATH')) exit;

class AlphaSuite_Plugin
{
  public static function init(): void
  {

    add_action('wp_enqueue_scripts', [__CLASS__, 'assets_wp']);

    if (class_exists('AlphaSuite_CPT_Posts_Orion')) AlphaSuite_CPT_Posts_Orion::init();
    if (class_exists('AlphaSuite_Settings')) AlphaSuite_Settings::init();
    if (class_exists('AlphaSuite_Adminbar')) AlphaSuite_Adminbar::init();
    if (class_exists('AlphaSuite_Orion_Migrator')) AlphaSuite_Orion_Migrator::init();
    if (class_exists('AlphaSuite_Prompts')) AlphaSuite_Prompts::register_ajax();

    require_once PGA_PATH . 'includes/stories/autoload.php';
    // Menus e assets
    add_action('admin_menu', ['AlphaSuite_AdminMenus', 'register']);
    add_action('admin_enqueue_scripts', [__CLASS__, 'assets']);

    if (class_exists('AlphaSuite_PermalinkSettings')) AlphaSuite_PermalinkSettings::init();
  }

  public static function assets_wp()
  {
    if (!is_singular() && !is_archive() && !is_home()) {
      return;
    }

    wp_enqueue_script(
      'alpha-suite-tracker',
      PGA_URL . 'assets/alpha-suite-tracker.js',
      [],
      alpha_suite_asset_ver('assets/alpha-suite-tracker.js'),
      true
    );

    wp_localize_script('alpha-suite-tracker', 'PI_TRACKER', [
      'post_id' => get_queried_object_id() ?: 0,
      'endpoint' => rest_url('pga/v1'),
    ]);
  }

  public static function assets($hook): void
  {
    // 1) Detecta telas do plugin (menus próprios) usando o hook recebido
    // Ex.: $hook = 'toplevel_page_alpha-suite', 'alpha-suite_page_alpha-suite-license', etc.
    $is_plugin_page = (false !== strpos((string) $hook, 'alpha-suite'));

    if (! $is_plugin_page) {
      return;
    }

    // 2) Detecta telas dos CPTs (lista, novo, editar)
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    $is_cpt_screen = false;

    if ($screen) {
      // CPTs que vão usar esse JS
      $allowed_post_types = ['posts_orion', 'alpha_storys'];

      if (!empty($screen->post_type) && in_array($screen->post_type, $allowed_post_types, true)) {
        $is_cpt_screen = true;
      }
    }

    if (!$is_plugin_page && !$is_cpt_screen) {
      return; // não carrega fora dos nossos contexts
    }

    // === CSS/JS do admin ===
    wp_enqueue_style(
      'pga-admin',
      PGA_URL . 'assets/admin.css',
      [],
      alpha_suite_asset_ver('assets/admin.css')
    );

    wp_register_style(
      'select2',
      PGA_URL . 'assets/vendor/select2.min.css',
      [],
      '4.1.0'
    );
    wp_register_script(
      'select2',
      PGA_URL . 'assets/vendor/select2.min.js',
      ['jquery'],
      '4.1.0',
      true
    );
    wp_enqueue_style('select2');
    wp_enqueue_script('select2');

    // SweetAlert2
    wp_enqueue_script(
      'sweetalert2',
      PGA_URL . 'assets/vendor/sweetalert2@11.js',
      [],
      '11',
      true
    );

    wp_enqueue_script(
      'chartjs',
      'https://cdn.jsdelivr.net/npm/chart.js',
      [],
      null,
      true
    );

    // JS principal
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';

    if ($page !== 'alpha-suite-ws-generator' && $page !== 'alpha-suite-rss') {
      wp_enqueue_script(
        'pga-admin',
        PGA_URL . 'assets/admin.js',
        ['jquery', 'wp-util', 'sweetalert2', 'wp-i18n'],
        alpha_suite_asset_ver('assets/admin.js'),
        true
      );
    }

    wp_enqueue_media();

    if ($page === 'alpha-suite-rss') {
      wp_enqueue_script(
        'pga-rss',
        PGA_URL . 'assets/pga-rss.js',
        ['jquery', 'sweetalert2', 'wp-i18n'],
        alpha_suite_asset_ver('assets/pga-rss.js'),
        true
      );

      wp_localize_script('pga-rss', 'PGA_CFG', [
        'rest'   => esc_url_raw(rest_url('pga/v1')),
        'nonce'  => wp_create_nonce('wp_rest'),
        'options' => class_exists('AlphaSuite_Settings') ? AlphaSuite_Settings::get() : [],
        'site_url'     => site_url(),
      ]);

      wp_enqueue_script(
        'pga-admin-rss',
        PGA_URL . 'assets/admin-rss.js',
        ['jquery', 'sweetalert2', 'wp-i18n'],
        alpha_suite_asset_ver('assets/admin-rss.js'),
        true
      );

      wp_localize_script('pga-admin-rss', 'PGA_CFG', [
        'rest'   => esc_url_raw(rest_url('pga/v1')),
        'nonce'  => wp_create_nonce('wp_rest'),
        'options' => class_exists('AlphaSuite_Settings') ? AlphaSuite_Settings::get() : [],
        'site_url'     => site_url(),
      ]);
    }

    if ($page === 'alpha-suite-ws-generator') {
      wp_enqueue_script(
        'pga-ws-builder',
        PGA_URL . 'assets/ws-builder.js',
        ['jquery', 'sweetalert2', 'wp-i18n'],
        alpha_suite_asset_ver('assets/ws-builder.js'),
        true
      );

      wp_localize_script('pga-ws-builder', 'PGA_CFG', [
        'rest'   => esc_url_raw(rest_url('pga/v1')),
        'nonce'  => wp_create_nonce('wp_rest'),
        'options' => class_exists('AlphaSuite_Settings') ? AlphaSuite_Settings::get() : [],
        'site_url'     => site_url(),
        'isCPT'  => (bool) $is_cpt_screen,
      ]);

      wp_enqueue_style('pga-ws-builder', PGA_URL . 'assets/ws-builder.css', [], alpha_suite_asset_ver('assets/ws-builder.css'));
    }

    wp_localize_script('pga-admin', 'PGA_CFG', [
      'rest'   => esc_url_raw(rest_url('pga/v1')),
      'nonce'  => wp_create_nonce('wp_rest'),
      'options' => class_exists('AlphaSuite_Settings') ? AlphaSuite_Settings::get() : [],
      'site_url'     => site_url(),
      'isCPT'  => (bool) $is_cpt_screen,
    ]);
  }
}
