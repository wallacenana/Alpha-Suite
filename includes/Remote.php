<?php
if (! defined('ABSPATH')) {
  exit;
}

class AlphaSuite_Remote
{

  /**
   * Retorna catálogo de módulos a partir da API remota (com cache)
   * e enriquece cada item com URLs locais quando possível.
   */
  public static function catalog(): array
  {
    $api_url = get_option('pa_admin_api_url', '');
    $api_key = get_option('pa_admin_api_key', '');

    $cache = get_transient('pa_catalog_cache');
    if ($cache && is_array($cache)) {
      return $cache;
    }

    $items = [];

    if ($api_url) {
      $res = wp_remote_get(
        rtrim($api_url, '/') . '/alpha/catalog',
        [
          'timeout' => 12,
          'headers' => [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
          ],
        ]
      );

      if (! is_wp_error($res)) {
        $body = json_decode(wp_remote_retrieve_body($res), true);
        if (is_array($body)) {
          $items = $body;
        }
      }
    }

    // fallback mock se remoto não respondeu ou veio vazio
    if (! $items) {
      $items = [
        [
          'slug'         => 'orion-posts',
          'name'         => 'Órion Posts',
          'desc'         => 'Gere calendários e artigos com IA.',
          'logo'         => PGA_URL . 'assets/images/orion-posts.png',
          'price'        => 24.90,
          'promo_price'  => 19.90,
          'buy_url'      => 'https://pluginsalpha.com/orion/',
          'status'       => 'active',
          'status_label' => 'Instalado',
        ],
        [
          'slug'         => 'alpha-stories',
          'name'         => 'Alpha Stories',
          'desc'         => 'Crie Web Stories otimizadas.',
          'logo'         => PGA_URL . 'assets/images/alpha-stories.png',
          'price'        => 19.90,
          'promo_price'  => 14.90,
          'buy_url'      => 'https://pluginsalpha.com/stories/',
          'status'       => 'active',
          'status_label' => 'Instalado',
        ],
      ];
    }

    // Normaliza / enriquece cada item
    $norm = [];
    foreach ($items as $it) {
      if (! is_array($it)) {
        continue;
      }
      $norm[] = self::normalize_item($it);
    }

    set_transient('pa_catalog_cache', $norm, 30 * MINUTE_IN_SECONDS);

    return $norm;
  }

  /**
   * Garante que cada item tenha os campos esperados
   * e preenche manage_url/admin_url para slugs conhecidos.
   */
  protected static function normalize_item(array $it): array
  {
    // slug / name básicos
    $slug          = isset($it['slug']) ? (string) $it['slug'] : '';
    $it['slug']    = $slug;
    $it['name']    = isset($it['name']) && $it['name'] !== '' ? (string) $it['name'] : 'Módulo';
    $it['desc']    = isset($it['desc']) ? (string) $it['desc'] : '';
    $it['logo']    = ! empty($it['logo']) ? (string) $it['logo'] : PGA_URL . 'assets/images/alpha-ico.png';
    $it['buy_url'] = isset($it['buy_url']) ? (string) $it['buy_url'] : '';

    // preços
    $it['price']       = isset($it['price']) ? (float) $it['price'] : 0.0;
    $it['promo_price'] = array_key_exists('promo_price', $it) && $it['promo_price'] !== ''
      ? (float) $it['promo_price']
      : null;

    // status / badge opcionais
    $it['status']       = isset($it['status']) ? (string) $it['status'] : '';
    $it['status_label'] = isset($it['status_label']) ? (string) $it['status_label'] : '';
    $it['badge']        = isset($it['badge']) ? (string) $it['badge'] : '';

    // URLs opcionais vindas do remoto
    $it['admin_url']      = isset($it['admin_url']) ? (string) $it['admin_url'] : '';
    $it['manage_url']     = isset($it['manage_url']) ? (string) $it['manage_url'] : '';
    $it['docs_url']       = isset($it['docs_url']) ? (string) $it['docs_url'] : '';
    $it['learn_more_url'] = isset($it['learn_more_url']) ? (string) $it['learn_more_url'] : '';

    // Se o remoto NÃO mandou manage/admin URL, inferimos baseado no slug
    if ($it['manage_url'] === '' && $it['admin_url'] === '') {
      switch ($slug) {
        case 'orion-posts':
          // Tela principal do Órion Posts
          $it['manage_url'] = admin_url('admin.php?page=alpha-suite-orion-posts');
          break;

        case 'alpha-stories':
          // Lista de posts do CPT alpha_storys (link que você passou)
          $it['manage_url'] = admin_url('edit.php?post_type=alpha_storys');
          break;

        default:
          // outros módulos futuros podem ser mapeados aqui
          break;
      }
    }

    return $it;
  }
}
