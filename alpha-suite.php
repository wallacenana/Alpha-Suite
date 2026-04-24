<?php

/**
 * Plugin Name: Alpha Suite
 * Description: Tudo o que você precisa para criar seus conteúdos na velocidade de 1 clique — Alpha Órion, Alpha Stories e muito mais.
 * Version: 3.2.77
 * Author: Wallace Tavares
 * Author URI: https://pluginsalpha.com/
 * Text Domain: alpha-suite
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Tested up to: 6.9
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) exit;

define('PLUGINS_ALPHA_VERSION', '3.2.77');

// Constantes
define('PGA_FILE', __FILE__);
define('PGA_PATH', plugin_dir_path(__FILE__));
define('PGA_URL',  plugin_dir_url(__FILE__));

// === Constantes de diretórios (ajuste aqui se mudar) ===
if (!defined('PGA_INC_DIR'))        define('PGA_INC_DIR',        rtrim(PGA_PATH, '/\\') . '/includes');
if (!defined('PGA_INC_POSTS_DIR'))  define('PGA_INC_POSTS_DIR',  PGA_INC_DIR . '/orion');
if (!defined('PGA_INC_STORYS_DIR')) define('PGA_INC_STORYS_DIR', PGA_INC_DIR . '/stories');
if (!defined('PGA_INC_TRACKER_DIR')) define('PGA_INC_TRACKER_DIR', PGA_INC_DIR . '/tracker');

require_once PGA_PATH . "includes/tracker/REST_tracker.PHP";
require_once PGA_PATH . "includes/OutlineGenerator.php";
require_once PGA_PATH . "includes/TitleGenerator.php";
require_once PGA_PATH . "includes/MetaDescriptionGenerator.php";

// Versão de asset por filemtime (cache-bust)
function alpha_suite_asset_ver(string $relpath): string
{
  $path = PGA_PATH . ltrim($relpath, '/');
  return file_exists($path) ? (string) filemtime($path) : (string) time();
}

spl_autoload_register(function ($class) {
  if (strpos($class, 'AlphaSuite_') !== 0) return;

  // Normaliza: AlphaSuite_Foo_Bar  -> Foo_Bar
  // (se usar namespace no futuro, também troca "\" por "_")
  $short = substr($class, strlen('AlphaSuite_'));
  $short = str_replace('\\', '_', $short);

  $dirs = array_filter([
    PGA_INC_DIR,
    PGA_INC_POSTS_DIR,
    PGA_INC_STORYS_DIR,
    PGA_INC_TRACKER_DIR,
  ]);

  // 1) candidatos diretos (em todas as pastas)
  $candidates = [];
  foreach ($dirs as $dir) {
    $base = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR;
    $candidates[] = $base . $short . '.php';
    $candidates[] = $base . str_replace('_', DIRECTORY_SEPARATOR, $short) . '.php';
  }
  foreach ($candidates as $file) {
    if (is_file($file)) {
      require_once $file;
      return;
    }
  }

  // 2) fallback: indexa todos os .php (uma vez) e tenta por nome
  static $index = null;
  if ($index === null) {
    $index = [];
    foreach ($dirs as $dir) {
      if (!is_dir($dir)) continue;
      $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
      );
      foreach ($it as $f) {
        if (strtolower($f->getExtension()) !== 'php') continue;
        $basename = strtolower($f->getBasename('.php')); // ex.: generator
        // mapeia pelo nome do arquivo (sem extensão)
        if (!isset($index[$basename])) {
          $index[$basename] = $f->getPathname();
        }
        // opcional: mapeia também pelo caminho "com underscores"
        $rel = strtolower(str_replace(
          DIRECTORY_SEPARATOR,
          '_',
          trim(str_replace($dir, '', $f->getPath()), '/\\') . '/' . $f->getBasename('.php')
        ));
        $rel = trim($rel, '_/');
        if ($rel && !isset($index[$rel])) {
          $index[$rel] = $f->getPathname(); // ex.: pages_generator
        }
      }
    }
  }

  $keyFull = strtolower($short);
  $leaf    = strtolower(basename(str_replace('_', '/', $short)));

  foreach ([$keyFull, $leaf] as $k) {
    if (isset($index[$k]) && is_file($index[$k])) {
      require_once $index[$k];
      return;
    }
  }
});

if (class_exists('AlphaSuite_REST_tracker')) {
  add_action('rest_api_init', function () {
    AlphaSuite_REST_tracker::register_routes();
  });
}
// Bootstrap
add_action('plugins_loaded', function () {

  if (!class_exists('AlphaSuite_Plugin')) {
    return;
  }

  AlphaSuite_Plugin::init();

  // REST
  if (class_exists('AlphaSuite_REST')) {
    add_action('rest_api_init', ['AlphaSuite_REST', 'register_routes']);
  }

  if (class_exists('AlphaSuite_REST_Ws_Generator')) {
    add_action('rest_api_init', ['AlphaSuite_REST_Ws_Generator', 'register_routes']);
  }

  if (class_exists('AlphaSuite_RESTRSS')) {
    add_action('rest_api_init', ['AlphaSuite_RESTRSS', 'register_routes']);
  }

  if (class_exists('AlphaSuite_Updater')) {
    AlphaSuite_Updater::init(PGA_FILE);
  }

  // Outros módulos
  if (class_exists('AlphaSuite_License')) {
    AlphaSuite_License::init();
  }

  if (class_exists('AlphaSuite_WS_CPT')) {
    AlphaSuite_WS_CPT::init();
  }

  if (class_exists('AlphaSuite_WS_Metabox')) {
    AlphaSuite_WS_Metabox::init();
  }
});
/*
|--------------------------------------------------------------------------
| INTERVALO DE 1 MINUTO (OBRIGATÓRIO)
|--------------------------------------------------------------------------
*/

add_filter('cron_schedules', function ($schedules) {

  if (!isset($schedules['every_minute'])) {
    $schedules['every_minute'] = [
      'interval' => 60,
      'display'  => 'Every Minute'
    ];
  }

  return $schedules;
});


if (class_exists('AlphaSuite_Init')) {
  AlphaSuite_Init::init();
}
/*
|--------------------------------------------------------------------------
| CRON MASTER
|--------------------------------------------------------------------------
*/

add_action('pga_master_cron', ['AlphaSuite_CRON', 'dispatch']);

/*
|--------------------------------------------------------------------------
| ATIVAÇÃO
|--------------------------------------------------------------------------
*/

register_activation_hook(PGA_FILE, function () {

  // cria tabelas
  alpha_suite_create_tables();

  // agenda cron
  if (!wp_next_scheduled('pga_master_cron')) {
    wp_schedule_event(time(), 'every_minute', 'pga_master_cron');
  }

  flush_rewrite_rules(false);
});


/*
|--------------------------------------------------------------------------
| DESATIVAÇÃO
|--------------------------------------------------------------------------
*/

register_deactivation_hook(PGA_FILE, function () {

  wp_clear_scheduled_hook('pga_master_cron');
  flush_rewrite_rules(false);
});


/*
|--------------------------------------------------------------------------
| CRIAÇÃO DAS TABELAS
|--------------------------------------------------------------------------
*/

function alpha_suite_create_tables()
{
  global $wpdb;
  $charset = $wpdb->get_charset_collate();

  require_once ABSPATH . 'wp-admin/includes/upgrade.php';

  dbDelta("CREATE TABLE {$wpdb->prefix}pga_generators (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        tab_id VARCHAR(100) NOT NULL,
        name VARCHAR(190) NOT NULL,
        active TINYINT(1) DEFAULT 1,
        start_hour TINYINT DEFAULT 0,
        end_hour TINYINT DEFAULT 23,
        interval_hours INT DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY tab_id (tab_id),
        KEY active (active)
    ) $charset;");

  dbDelta("CREATE TABLE {$wpdb->prefix}pga_generator_config (
        generator_id BIGINT UNSIGNED NOT NULL,
        config_json LONGTEXT NOT NULL,
        PRIMARY KEY (generator_id)
    ) $charset;");

  dbDelta("CREATE TABLE {$wpdb->prefix}pga_generator_items (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      generator_id BIGINT UNSIGNED NOT NULL,
      keyword VARCHAR(64) NOT NULL,
      status VARCHAR(20) DEFAULT 'pending',
      embedding MEDIUMTEXT NULL,
      post_id BIGINT DEFAULT NULL,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      generated_at DATETIME NULL,
      PRIMARY KEY (id),
      KEY generator_id (generator_id),
      KEY generator_embedding (generator_id, id),
      KEY status (status)
  ) $charset;");

  dbDelta("CREATE TABLE {$wpdb->prefix}pga_generator_runtime (
        generator_id BIGINT UNSIGNED NOT NULL,
        next_run DATETIME NULL,
        last_run DATETIME NULL,
        lock_until DATETIME NULL,
        interval_hours INT DEFAULT 1,
        last_status VARCHAR(50) DEFAULT NULL,
        PRIMARY KEY (generator_id)
    ) $charset;");

  dbDelta("CREATE TABLE {$wpdb->prefix}pga_post_views (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        post_id BIGINT UNSIGNED NOT NULL,
        ip VARCHAR(45) DEFAULT NULL,
        session_id VARCHAR(64) DEFAULT NULL,
        duration INT DEFAULT 0,
        viewed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY post_id (post_id),
        KEY viewed_at (viewed_at),
        KEY session_id (session_id)
    ) $charset;");
}

define('ALPHASUITE_DB_VERSION', '1.1');

function maybe_update_database()
{
  global $wpdb;

  $installed_version = get_option('alphasuite_db_version');

  if ($installed_version === ALPHASUITE_DB_VERSION) {
    return;
  }

  require_once ABSPATH . 'wp-admin/includes/upgrade.php';

  $charset = $wpdb->get_charset_collate();

  dbDelta("CREATE TABLE {$wpdb->prefix}pga_generator_items (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        generator_id BIGINT UNSIGNED NOT NULL,
        keyword VARCHAR(64) NOT NULL,
        status VARCHAR(20) DEFAULT 'pending',
        embedding MEDIUMTEXT NULL,
        post_id BIGINT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        generated_at DATETIME NULL,
        PRIMARY KEY (id),
        KEY generator_id (generator_id),
        KEY generator_embedding (generator_id, id),
        KEY status (status)
    ) $charset;");

  dbDelta("CREATE TABLE {$wpdb->prefix}pga_post_views (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        post_id BIGINT UNSIGNED NOT NULL,
        ip VARCHAR(45) DEFAULT NULL,
        session_id VARCHAR(64) DEFAULT NULL,
        duration INT DEFAULT 0,
        viewed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY post_id (post_id),
        KEY viewed_at (viewed_at),
        KEY session_id (session_id)
    ) $charset;");

  update_option('alphasuite_db_version', ALPHASUITE_DB_VERSION);
}

// Link “Dashboard” na tela de Plugins
add_filter('plugin_action_links_' . plugin_basename(PGA_FILE), function ($links) {
  $links[] = '<a href="' . esc_url(admin_url('admin.php?page=alpha-suite-dashboard')) . '">' . esc_html__('Dashboard', 'alpha-suite') . '</a>';
  return $links;
});
