<?php
if (!defined('ABSPATH')) exit;

class AlphaSuite_Pages_Generator
{
  public static function render(): void
  {
    $opt = AlphaSuite_Settings::get();
    $chk = AlphaSuite_License::check('alpha_orion');
?>
    <div class="pga-wrap">
      <?php
      if (!$chk['ok']) {
        $url = admin_url('admin.php?page=alpha-suite-license');

        echo '<div class="notice notice-error is-dismissible"><p>'
          . esc_html__('Módulo não ativado.', 'alpha-suite')
          . ' <a href="' . esc_url($url) . '">'
          . esc_html__('Clique aqui para ativar', 'alpha-suite')
          . '</a></p></div>';
      }

      $tpls = (array) get_option('pga_orion_templates', []);
      $default_tpls = [];

      foreach ($tpls as $slug => $row) {
        $slug = sanitize_key((string) $slug);
        if (!$slug) continue;

        $enabled = !empty($row['enabled']);
        $is_default = !empty($row['is_default']);

        if ($enabled && $is_default) {
          $default_tpls[] = $slug;
        }
      }

      // fallback: se user não marcou nada, evita “novo projeto vazio”
      if (!$default_tpls) {
        $default_tpls = ['article'];
      }
      ?>
      <div class="wrap pga-layout">
        <div class="pga-header-fixed">
          <div class="pga-header-col pga-a-center">
            <div>
              <h1><?php esc_html_e('Gerador — Alpha Órion', 'alpha-suite'); ?></h1>
              <p class="pga-descricao"><?php esc_html_e('Criação e automação de conteúdo com IA', 'alpha-suite'); ?></p>
            </div>
          </div>
          <div class="pga-header-col pga-a-center ">
            <button
              title="<?php esc_html_e('Salvar palavras-chave', 'alpha-suite'); ?>"
              type="button"
              class="pga_save_box"
              id="pga_save_keywords">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-save h-4 w-4 mr-2">
                <path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"></path>
                <path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"></path>
                <path d="M7 3v4a1 1 0 0 0 1 1h7"></path>
              </svg>
            </button>
            <?php
            $now_ts    = current_time('timestamp');
            $min_date = date_i18n('Y-m-d', $now_ts);

            // valor padrão = hoje
            $val_default = $val_default ?? $min_date;
            ?>
            <div class="pga-global-wrap" style="display:flex;align-items:center;gap:10px">
              <label class="pga-switch">
                <input type="checkbox" id="pga_plan_global_toggle">
                <span class="pga-switch-ui" aria-hidden="true"></span>
                <span class="pga-switch-label">Global</span>
              </label>
              <div id="pga_plan_custom_top" class="pga-field" style="display:none;align-items:center;gap:10px;">
                <label style="display:flex;align-items:center;gap:8px;">
                  <span><?php echo esc_html__('Total', 'alpha-suite'); ?></span>
                  <input id="pga_plan_total" type="number" min="1" step="1" value="30" style="width:90px;">
                </label>

                <label style="display:flex;align-items:center;gap:8px;">
                  <span><?php echo esc_html__('Início', 'alpha-suite'); ?></span>
                  <input
                    id="pga_plan_start"
                    type="date"
                    min="<?php echo esc_attr($min_date); ?>"
                    value="<?php echo esc_attr($val_default); ?>"
                    style="width:200px;"> </label>
              </div>
            </div>

            <?php
            $label = esc_html__('Planejar & Gerar', 'alpha-suite');

            echo $chk['ok']
              ? '<button type="button" id="pga_plan"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-sparkles h-4 w-4 mr-2"><path d="M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .963 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.581a.5.5 0 0 1 0 .964L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z"></path><path d="M20 3v4"></path><path d="M22 5h-4"></path><path d="M4 17v2"></path><path d="M5 18H3"></path></svg> ' . esc_html($label) . '</button>'
              : '<button type="button" id="pga_plan" disabled> <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-sparkles h-4 w-4 mr-2"><path d="M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .963 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.581a.5.5 0 0 1 0 .964L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z"></path><path d="M20 3v4"></path><path d="M22 5h-4"></path><path d="M4 17v2"></path><path d="M5 18H3"></path></svg>' . esc_html($label) . '</button>';
            ?>
          </div>
        </div>
        <div class="pga-main">
          <!-- Tabs -->
          <div class="pga-tabsbar">
            <div id="pga_tabs"></div>
            <button type="button" class="button" id="pga_tab_add"
              data-default-templates="<?php echo esc_attr(wp_json_encode(array_values($default_tpls))); ?>">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                class="lucide lucide-plus h-4 w-4">
                <path d="M5 12h14"></path>
                <path d="M12 5v14"></path>
              </svg> <?php esc_html_e('Novo projeto', 'alpha-suite'); ?></button>
          </div>

          <!-- Contêiner de grupos -->
          <div id="pga_gen_container">
            <div class="pga-gen-box pga-collapse pga-collapse--open" data-gen="1">
              <div class="pga-collapse-head">
                <button type="button" class="button pga-collapse-toggle">
                  <span class="pga-gen-title"><?php esc_html_e('Título', 'alpha-suite'); ?></span>
                  <span class="pga-actions-colapse">
                    <label class="pga-switch  pga_custom_wrap" style="display: none;">
                      <input type="checkbox" class="pga_custom_enabled" checked>
                      <span class="pga-switch-ui" aria-hidden="true"></span>
                    </label>
                    <span type="button" class="pga-copy-box" title="Duplicar este grupo" data-tooltip="Duplicar este grupo">
                      <span class="pga-icon"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3">
                          <path d="M360-240q-33 0-56.5-23.5T280-320v-480q0-33 23.5-56.5T360-880h360q33 0 56.5 23.5T800-800v480q0 33-23.5 56.5T720-240H360Zm0-80h360v-480H360v480ZM200-80q-33 0-56.5-23.5T120-160v-560h80v560h440v80H200Zm160-240v-480 480Z" />
                        </svg></span>
                    </span>
                  </span>
                </button>
              </div>
              <div class="pga-collapse-body">
                <div class="pga-card">
                  <div class="pga-row between">
                    <div class="pga-field" style="width:100%; position: relative">
                      <div class="pga-field pga-actions-unit">
                        <button
                          type="button"
                          class="pga_import_box"
                          title="Importar keywords (.txt)">
                          <span class="pga-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px">
                              <path d="M480-240 240-480l56-56 144 144v-368h80v368l144-144 56 56-240 240Z" />
                            </svg>
                          </span>
                        </button>

                        <!-- Exportar -->
                        <button
                          type="button"
                          class="pga_export_box"
                          title="Exportar keywords (.txt)">
                          <span class="pga-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px">
                              <path d="M280-160v-80h400v80H280Zm160-160v-327L336-544l-56-56 200-200 200 200-56 56-104-103v327h-80Z" />
                            </svg>
                          </span>
                        </button>
                      </div>
                      <label for="pga_keywords"><?php esc_html_e('Keywords (1 por linha)', 'alpha-suite'); ?></label>
                      <textarea
                        id="pga_keywords"
                        class="pga_keywords"
                        rows="14"
                        placeholder="<?php esc_html_e('Digite uma keyword por linha...', 'alpha-suite'); ?>"></textarea>
                    </div>
                  </div>

                  <div class="pga-row">
                    <div class="pga-field">
                      <label for="pga_template_key"><?php esc_html_e('Modelo de Post', 'alpha-suite'); ?></label>
                      <?php
                      $tpls_enabled = class_exists('AlphaSuite_Orion_Templates')
                        ? AlphaSuite_Orion_Templates::get_enabled()
                        : [
                          'article' => ['label' => 'Artigo'],
                          'modelar_youtube' => ['label' => 'Modelar vídeo do YouTube'],
                        ];

                      unset($tpls_enabled['rss']);
                      ?>

                      <select id="pga_template_key" class="pga_template_key">
                        <?php foreach ($tpls_enabled as $key => $tpl): ?>
                          <option value="<?php echo esc_attr($key); ?>">
                            <?php echo esc_html($tpl['label'] ?? $key); ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>

                    <div class="pga-field">
                      <label for="pga_category"><?php esc_html_e('Categoria', 'alpha-suite'); ?></label>
                      <?php
                      wp_dropdown_categories([
                        'show_option_none'  => '— Sem categoria —',
                        'option_none_value' => '0',
                        'taxonomy'          => 'category',
                        'hide_empty'        => 0,
                        'name'              => 'pga_category',
                        'id'                => 'pga_category',
                        'class'             => 'regular-text pga_category',
                        'orderby'           => 'name',
                        'hierarchical'      => true,
                        'value_field'       => 'term_id',
                        'selected'          => 0,
                      ]);
                      ?>
                    </div>

                    <div class="pga-field">
                      <label for="pga_length"><?php esc_html_e('Extensão', 'alpha-suite'); ?></label>
                      <select id="pga_length" class="pga_length">
                        <option value="short"><?php esc_html_e('Pequeno', 'alpha-suite'); ?></option>
                        <option value="medium"><?php esc_html_e('Médio', 'alpha-suite'); ?></option>
                        <option value="long"><?php esc_html_e('Longo', 'alpha-suite'); ?></option>
                        <option value="extra-long"><?php esc_html_e('Extra Longo', 'alpha-suite'); ?></option>
                      </select>
                    </div>
                    <!-- ... dentro da pga-row de campos do grupo ... -->

                    <div class="pga-field">
                      <label for="pga_link_mode"><?php esc_html_e('Links internos', 'alpha-suite'); ?></label>
                      <select id="pga_link_mode" class="pga_link_mode">
                        <option value="none"><?php esc_html_e('Sem link interno', 'alpha-suite'); ?></option>
                        <option value="auto"><?php esc_html_e('Automático', 'alpha-suite'); ?></option>
                        <option value="pillar"><?php esc_html_e('Post pilar', 'alpha-suite'); ?></option>
                        <option value="manual"><?php esc_html_e('Manual', 'alpha-suite'); ?></option>
                      </select>
                    </div>

                    <div class="pga-field pga_link_extra" style="display:none">
                      <label><?php esc_html_e('Links por post', 'alpha-suite'); ?></label>
                      <select class="pga_link_max">
                        <option value="1">1 link</option>
                        <option value="2">2 links</option>
                        <option value="3">3 links</option>
                        <option value="4">4 links</option>
                        <option value="5">5 links</option>
                        <option value="6">6 links</option>
                        <option value="7">7 links</option>
                        <option value="8">8 links</option>
                        <option value="9">9 links</option>
                        <option value="10">10 links</option>
                        <option value="11">11 links</option>
                        <option value="12">12 links</option>
                        <option value="13">13 links</option>
                        <option value="14">14 links</option>
                        <option value="15">15 links</option>
                      </select>
                    </div>
                    <div class="pga-field pga_link_manual_wrapper" style="display:none">
                      <label><?php esc_html_e('Posts para linkar (modo manual)', 'alpha-suite'); ?></label>
                      <?php
                      // últimos posts Orion (ajuste o post_type se for outro)
                      $orion_posts = get_posts([
                        'post_type'      => ['post', 'page', 'posts_orion'],
                        'post_status'    => 'any',
                        'numberposts'    => -1,
                        'orderby'        => 'date',
                        'order'          => 'DESC',
                      ]);
                      ?>
                      <select
                        class="pga_link_manual pga-link-manual-select pga-select2"
                        multiple="multiple"
                        size="6">
                        <?php if (!empty($orion_posts)) : ?>
                          <?php foreach ($orion_posts as $p) : ?>
                            <option value="<?php echo esc_attr($p->ID); ?>">
                              <?php echo esc_html(get_the_title($p)); ?>
                            </option>
                          <?php endforeach; ?>
                        <?php else : ?>
                          <option value="" disabled><?php esc_html_e('Nenhum post Órion publicado ainda.', 'alpha-suite'); ?></option>
                        <?php endif; ?>
                      </select>
                    </div>
                    <div class="pga-field">
                      <label for="pga_locale"><?php esc_html_e('Idioma', 'alpha-suite'); ?></label>
                      <select id="pga_locale" class="pga_locale">
                        <?php $current = $opt['defaults']['locale'] ?? 'pt_BR'; ?>

                        <option value="pt_BR" <?php selected($current, 'pt_BR'); ?>>Português (Brasil)</option>
                        <option value="pt_PT" <?php selected($current, 'pt_PT'); ?>>Português (Portugal)</option>

                        <option value="en_US" <?php selected($current, 'en_US'); ?>>English (United States)</option>
                        <option value="en_GB" <?php selected($current, 'en_GB'); ?>>English (United Kingdom)</option>

                        <option value="es_ES" <?php selected($current, 'es_ES'); ?>>Español (España)</option>
                        <option value="es_MX" <?php selected($current, 'es_MX'); ?>>Español (México)</option>

                        <option value="fr_FR" <?php selected($current, 'fr_FR'); ?>>Français (France)</option>
                        <option value="de_DE" <?php selected($current, 'de_DE'); ?>>Deutsch (Deutschland)</option>

                        <option value="it_IT" <?php selected($current, 'it_IT'); ?>>Italiano</option>
                        <option value="nl_NL" <?php selected($current, 'nl_NL'); ?>>Nederlands</option>

                        <option value="ja_JP" <?php selected($current, 'ja_JP'); ?>>日本語</option>
                        <option value="ko_KR" <?php selected($current, 'ko_KR'); ?>>한국어</option>

                        <option value="zh_CN" <?php selected($current, 'zh_CN'); ?>>中文 (简体)</option>
                        <option value="zh_TW" <?php selected($current, 'zh_TW'); ?>>中文 (繁體)</option>
                      </select>
                    </div>
                    <div class="pga-field">
                      <label><?php esc_html_e('Tags', 'alpha-suite'); ?></label>

                      <select class="pga_tags pga-select2" multiple="multiple" style="width:100%">
                        <?php
                        $tags = get_terms([
                          'taxonomy'   => 'post_tag',
                          'hide_empty' => false,
                          'number'     => 0, // não limita
                          'orderby'    => 'name',
                          'order'      => 'ASC',
                        ]);

                        if (!is_wp_error($tags) && !empty($tags)) :
                          foreach ($tags as $t) :
                        ?>
                            <option value="<?php echo esc_attr($t->term_id); ?>">
                              <?php echo esc_html($t->name); ?>
                            </option>
                        <?php
                          endforeach;
                        endif;
                        ?>
                      </select>
                    </div>

                    <div class="pga-row">
                      <div class="pga-field">
                        <label style="display:flex;align-items:center;gap:10px;margin-bottom:6px;">
                          <span class="pga-switch">
                            <input type="checkbox" id="pga_make_faq" class="pga_make_faq">
                            <span class="pga-switch-ui" aria-hidden="true"></span>
                            <span class="pga-switch-label"><?php esc_html_e('Criar FAQ', 'alpha-suite'); ?></span>
                          </span>
                        </label>
                      </div>
                      <div class="pga-field">
                        <div class="pga-faq-qty-wrap" style="display:none;align-items:center;gap:8px;">
                          <label for="pga_faq_qty"><?php esc_html_e('Perguntas', 'alpha-suite'); ?></label>
                          <input
                            id="pga_faq_qty"
                            class="pga_faq_qty"
                            type="number"
                            min="1"
                            step="1"
                            max="7"
                            value="7">
                        </div>
                      </div>
                    </div>

                    <div class="pga-plan">
                      <div class="pga-field pga-field-total">
                        <label for="pga_total"><?php esc_html_e('Quantidade total', 'alpha-suite'); ?></label>
                        <input id="pga_total" class="pga_total" type="number" min="1" step="1" value="6">
                      </div>

                      <div class="pga-field pga_quota_wrap" style="display:none">
                        <label for="pga_quota_day"><?php esc_html_e('Quota (posts/dia)', 'alpha-suite'); ?></label>
                        <input class="pga_quota_day" type="number" min="0" step="1" value="1">
                      </div>

                      <div class="pga-field">
                        <label for="pga_per_day"><?php esc_html_e('Posts por dia', 'alpha-suite'); ?></label>
                        <input id="pga_per_day" class="pga_per_day" type="number" min="1" step="1" value="3">
                      </div>

                      <div class="pga-field pga-field-program">
                        <label for="pga_first_delay_hours"><?php esc_html_e('Inicio', 'alpha-suite'); ?></label>

                        <input
                          id="pga_first_delay_hours"
                          class="pga_first_delay_hours"
                          type="date"
                          min="<?php echo esc_attr($min_date); ?>"
                          value="<?php echo esc_attr($val_default); ?>" />
                      </div>
                    </div>
                  </div>

                </div>
                <div class="pga-generator-footer">
                  <button type="button" class="pga_generate_box" id="pga_generator_btn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                      stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-zap h-4 w-4 mr-2">
                      <path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z">
                      </path>
                    </svg>
                    <?php esc_html_e('Gerar agora', 'alpha-suite'); ?>
                  </button>
                  <button type="button" class="pga_save_box">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                      stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-save h-4 w-4 mr-2">
                      <path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"></path>
                      <path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"></path>
                      <path d="M7 3v4a1 1 0 0 0 1 1h7"></path>
                    </svg>
                    <?php esc_html_e('Salvar gerador', 'alpha-suite'); ?>
                  </button>
                  <button type="button" class="pga_generate_keywords">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                      stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-sparkles h-4 w-4 mr-2">
                      <path d="M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 
                      .963 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.581a.5.5 0 0 1 0 .964L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z"></path>
                      <path d="M20 3v4"></path>
                      <path d="M22 5h-4"></path>
                      <path d="M4 17v2"></path>
                      <path d="M5 18H3"></path>
                    </svg>
                    <?php esc_html_e('Gerar keywords', 'alpha-suite'); ?>
                  </button>
                  <button type="button" class="pga_clear_box">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                      stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trash2 h-4 w-4 mr-2">
                      <path d="M3 6h18"></path>
                      <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                      <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                      <line x1="10" x2="10" y1="11" y2="17"></line>
                      <line x1="14" x2="14" y1="11" y2="17"></line>
                    </svg>
                    <?php esc_html_e('Excluir', 'alpha-suite'); ?>
                  </button>
                </div>
              </div>
            </div>
          </div>
          <div class="pga-add-generator">
            <button class="pga-add-container" id="pga_add_box">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus h-4 w-4">
                <path d="M5 12h14"></path>
                <path d="M12 5v14"></path>
              </svg>
              <?php esc_html_e('Adicionar gerador', 'alpha-suite'); ?>
            </button>
          </div>
        </div>
      </div>
    </div>
    <div class="pga-done-dropup">
      <button
        type="button"
        id="pga_done_toggle"
        class="button pga-floating-btn pga-icon-btn"
        aria-expanded="false"
        aria-controls="pga_done_panel"
        data-tooltip="Ver frases já geradas">
        <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px">
          <path d="M382-240 154-468l57-57 171 171 367-367 57 57-424 424Z" />
        </svg>
      </button>

      <div
        id="pga_done_panel"
        class="pga-card pga-done-panel"
        aria-hidden="true">
        <div class="pga-row">
          <h2>Concluídas</h2>
          <button
            type="button"
            id="pga_kw_clear_done"
            class="pga-icon-btn pga-btn-delete"
            data-tooltip="Limpar frases geradas">
            <span class="pga-icon">🗑️</span>
          </button>
        </div>
        <ul id="pga_kw_done" class="pga-list done"></ul>
      </div>
    </div>
<?php
  }

  /**
   * $args:
   *  - keywords[]  (usa a 1ª como foco)
   *  - locale      (pt_BR|en_US...)
   *  - publish_time  (timestamp futuro)
   *  - category_id   (int)
   */


  public static function create_draft_and_outline(array $args)
  {

    // 0) item único (primeira linha) 
    $kwSrc = $args['keyword'] ?? $args['keywords'] ?? '';
    if (is_array($kwSrc)) {
      $raw = trim((string)($kwSrc[0] ?? ''));
    } else {
      $lines = preg_split('/\r\n|\r|\n/', (string)$kwSrc);
      $raw = trim((string)($lines[0] ?? ''));
    }
    if ($raw === '') {
      return new WP_Error('pga_no_kw', 'Item (linha 1) vazio.');
    }

    // 1) parâmetros básicos (sem if por template) 
    $template = $args['template'] ?? $args['template_key'] ?? 'article';
    $length = $args['length'] ?? 'medium';
    $locale = $args['locale'] ?? 'pt_BR';
    $provider = $args['provider'] ?? (class_exists('AlphaSuite_AI') ? AlphaSuite_AI::get_text_provider() : '');
    $jobArgs = ['provider' => $provider, 'template' => $template, 'length' => $length, 'locale' => $locale, 'step' => 'outline'];

    // 2) publish_time: NÃO calcula, só recebe e repassa (timestamp ou string) 
    $publish_ts = 0;
    if (!empty($args['publish_time'])) {
      $publish_ts = is_numeric($args['publish_time']) ? (int)$args['publish_time'] : (int)strtotime((string)$args['publish_time']);
    }
    $category_id = (int)($args['category_id'] ?? 0);
    $post_type = !empty($args['post_type']) ? sanitize_key((string)$args['post_type']) : 'posts_orion';

    // SE LICENÇA FOR VITALÍCIA → força post normal
    $lic = class_exists('AlphaSuite_License') ? AlphaSuite_License::check('alpha_orion') : ['ok' => false];
    $is_lifetime = !empty($lic['lifetime']) || (!empty($lic['plan']) && $lic['plan'] === 'lifetime');

    if ($is_lifetime && post_type_exists('post')) {
      $post_type = 'post';
    }

    // 3) contexto neutro: keyword = raw; url = raw se for URL 
    $keyword = $raw;
    $url = filter_var($raw, FILTER_VALIDATE_URL) ? $raw : '';

    // 4) slug base (temporário; depois atualiza com o título final) 
    $slug = sanitize_title($keyword);
    if ($slug === '') {
      $slug = sanitize_title(uniqid('orion_', true));
    }

    // 5) fallback de post_type (mantém teu comportamento) 
    if (!post_type_exists($post_type)) {
      if (post_type_exists('posts_orion')) {
        $post_type = 'posts_orion';
      } elseif (post_type_exists('post_orion')) {
        $post_type = 'post_orion';
      } else {
        $post_type = 'post';
      }
    } // 6) cria draft 
    $postarr = ['post_type' => $post_type, 'post_status' => 'draft', 'post_title' => '(Gerando) ' . $keyword, 'post_name' => $slug, 'post_content' => '', 'post_author' => get_current_user_id(),]; // só aplica se vier publish_time (sem “ajustes”) 
    if ($publish_ts > 0) {
      $postarr['post_date']     = wp_date('Y-m-d H:i:s', $publish_ts);
      $postarr['post_date_gmt'] = gmdate('Y-m-d H:i:s', $publish_ts);
    }

    $draft_id = wp_insert_post($postarr, true);
    if (is_wp_error($draft_id)) {
      return $draft_id;
    }
    $draft_id = (int)$draft_id; // metas base 
    if ($publish_ts > 0) {
      update_post_meta($draft_id, '_pga_publish_ts', $publish_ts);
    }
    update_post_meta($draft_id, '_pga_job_started', time());
    if ($category_id > 0) {
      wp_set_post_terms($draft_id, [$category_id], 'category', false);
      update_post_meta($draft_id, '_pga_orion_category_ids', [$category_id]);
    }

    // --- TAGS (salva contexto do job) ------------------------
    if (!empty($args['tags']) && is_array($args['tags'])) {
      $clean = [];

      foreach ($args['tags'] as $t) {
        $t = trim((string)$t);
        if ($t !== '') {
          $clean[] = $t;
        }
      }

      if ($clean) {
        update_post_meta($draft_id, '_pga_job_tags', $clean);
      }
    }

    $chosenTitle = AlphaSuite_Titles::getTitle(
      $draft_id,
      $template,
      $keyword,
      $locale,
      $url
    );

    if (is_wp_error($chosenTitle)) {
      return $chosenTitle; // ou trate o erro aqui
    }

    wp_update_post([
      'ID'         => $draft_id,
      'post_title' => '(Gerando) ' . $chosenTitle,
    ]);

    $promptSlug = AlphaSuite_Prompts::build_slug_prompt(
      (string)$template,
      (string)$keyword,
      (string)$chosenTitle,
      (string)$locale
    );

    // chama endpoint dedicado (ou complete, se você não tiver meta_description)
    $respSlug = AlphaSuite_AI::slug($promptSlug);

    if (!is_wp_error($respSlug)) {

      $slugTxt = '';

      // --------- EXTRAÇÃO SEGURA ----------
      if (is_string($respSlug)) {
        $slugTxt = $respSlug;
      } elseif (is_array($respSlug)) {
        $slugTxt = (string)($respSlug['slug'] ?? $respSlug['content'] ?? '');
      } elseif (is_object($respSlug)) {
        $slugTxt = (string)($respSlug->slug ?? $respSlug->content ?? '');
      }

      $slugTxt = trim($slugTxt);

      // --------- SE VIER JSON EM TEXTO ----------
      if ($slugTxt !== '' && ($slugTxt[0] === '{' || $slugTxt[0] === '[')) {
        $j = json_decode($slugTxt, true);
        if (is_array($j)) {
          $slugTxt = (string)($j['slug'] ?? $j['content'] ?? '');
        }
      }

      // --------- REMOVE PREFIXOS ----------
      $slugTxt = preg_replace('/^\s*(slug|post_name)\s*:\s*/i', '', $slugTxt);

      // --------- PRIMEIRA LINHA APENAS ----------
      $slugTxt = preg_split("/\r\n|\r|\n/", $slugTxt)[0] ?? $slugTxt;
      $slugTxt = trim($slugTxt);

      // --------- SANITIZAÇÃO ----------
      $newSlug = sanitize_title($slugTxt);

      // --------- FALLBACKS EM ORDEM ----------
      if ($newSlug === '') {
        $newSlug = sanitize_title($chosenTitle);
      }
      if ($newSlug === '') {
        $newSlug = sanitize_title($keyword);
      }
      if ($newSlug === '') {
        $newSlug = sanitize_title(uniqid('orion_', false));
      }

      // --------- GARANTE UNICIDADE ----------
      $newSlug = wp_unique_post_slug($newSlug, $draft_id, 'draft', $post_type, 0);

      // --------- ATUALIZA POST ----------
      wp_update_post([
        'ID'        => $draft_id,
        'post_name' => $newSlug,
      ]);

      update_post_meta($draft_id, '_pga_generated_slug', $newSlug);
    }

    // jobArgs úteis pro provider/prompt 
    $jobArgs['keyword'] = $keyword;
    $jobArgs['url'] = $url;
    $jobArgs['chosen_title'] = $chosenTitle; // salva base do job 
    update_post_meta($draft_id, '_pga_outline_length', $length);
    update_post_meta($draft_id, '_pga_outline_locale', $locale);
    update_post_meta($draft_id, '_pga_outline_keyword', $keyword);
    update_post_meta($draft_id, '_pga_outline_template', $template);
    update_post_meta($draft_id, '_pga_outline_url', $url);
    update_post_meta($draft_id, '_pga_chosen_title', $chosenTitle);

    $idea = AlphaSuite_Outline::build_idea_brief(
      $template,
      $keyword,
      $chosenTitle,
      $length,
      $locale,
      $url
    );

    update_post_meta(
      $draft_id,
      '_pga_outline_idea',
      wp_json_encode($idea, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );

    $outline = alpha_suite_generate_outline([
      'target'   => 'orion',
      'template' => $template,
      'keyword'  => $keyword,
      'title'    => $chosenTitle,
      'length'   => $length,
      'locale'   => $locale,
      'url'      => $url,
      'idea'     => $idea,
    ]);

    if (is_wp_error($outline)) {
      return AlphaSuite_FailJob::fail_job($draft_id, $outline);
    }

    // pega sections no novo formato compacto
    $sections = $outline['sections'] ?? $outline;

    if (!is_array($sections)) {
      $sections = [];
    }

    // garante lista indexada
    if (array_keys($sections) !== range(0, count($sections) - 1)) {
      $sections = array_values($sections);
    }

    // NORMALIZA
    $normalized = [];
    $h2Index = 1;
    foreach ($sections as $sec) {
      if (!is_array($sec)) {
        $sec = ['heading' => (string)$sec, 'level' => 'h2',];
      }
      if (empty($sec['level'])) {
        $sec['level'] = 'h2';
      }
      if (empty($sec['id'])) {
        $sec['id'] = (string)$h2Index;
      }
      if (!isset($sec['children']) || !is_array($sec['children'])) {
        $sec['children'] = [];
      }
      if (!empty($sec['children']) && is_array($sec['children'])) {
        $childIndex = 1;
        foreach ($sec['children'] as $ci => $child) {
          if (!is_array($child)) {
            $child = [
              'heading' => (string)$child,
              'level'   => 'h3',
            ];
          }
          $sec['level'] = strtolower(trim($sec['level'] ?? 'h2'));
          if (!in_array($sec['level'], ['h2', 'h3'], true)) {
            $sec['level'] = 'h2';
          }


          $child['level'] = 'h3';
          $child['id'] = $child['id'] ?? ($sec['id'] . '.' . $childIndex);

          $sec['children'][$ci] = $child;
          $childIndex++;
        }
      }
      $normalized[] = $sec;
      $h2Index++;
    }

    update_post_meta(
      $draft_id,
      '_pga_outline_sections',
      wp_json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );

    update_post_meta($draft_id, '_pga_job_status', 'outline_done');

    return [
      'post_id'   => $draft_id,
      'title'     => $chosenTitle,
      'sections'  => $normalized,
      'length'    => $length,
      'locale'    => $locale,
      'post_type' => $post_type,
    ];
  }

  /**
   * Tenta derivar uma keyword razoável a partir da URL:
   * - Título da página, se conseguir
   * - Senão, último segmento do path
   */
  protected static function derive_keyword_from_url(string $url): string
  {
    $url = trim($url);
    if ($url === '') return '';

    $resp = wp_remote_get($url, ['timeout' => 15]);
    if (is_wp_error($resp)) return '';

    $code = wp_remote_retrieve_response_code($resp);
    if ($code !== 200) return '';

    $body = wp_remote_retrieve_body($resp);
    if (!$body) return '';

    // tenta pegar o <title>
    if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $body, $m)) {
      $title = trim(wp_strip_all_tags($m[1]));
      if ($title !== '') return $title;
    }

    // fallback: último segmento da URL
    $path = wp_parse_url($url, PHP_URL_PATH);
    $path = trim((string)$path, "/");
    if ($path !== '') {
      $parts = explode('/', $path);
      $last  = end($parts);
      $last  = str_replace(['-', '_'], ' ', $last);
      return trim($last);
    }

    return '';
  }


  public static function generate_section_content(int $post_id, string $section_id)
  {
    $post_id = (int)$post_id;
    if (!$post_id || !get_post_type($post_id)) {
      return new WP_Error('pga_invalid_post', 'Post inválido.');
    }

    // --- CONTEXTO BASE ---
    $sections = json_decode(
      (string)get_post_meta($post_id, '_pga_outline_sections', true),
      true
    ) ?: [];

    if (!$sections) {
      return new WP_Error('pga_no_outline', 'Esboço não encontrado.');
    }

    $template = get_post_meta($post_id, '_pga_outline_template', true) ?: 'article';
    $length   = get_post_meta($post_id, '_pga_outline_length',   true) ?: 'medium';
    $locale   = get_post_meta($post_id, '_pga_outline_locale',   true) ?: 'pt_BR';
    $keyword  = get_post_meta($post_id, '_pga_outline_keyword',  true) ?: '';
    $title    = get_post_meta($post_id, '_pga_chosen_title',     true) ?: $keyword;
    $url      = get_post_meta($post_id, '_pga_outline_url',      true) ?: '';
    $idea_raw = get_post_meta($post_id, '_pga_outline_idea',     true) ?: '';
    $idea     = is_string($idea_raw) ? json_decode($idea_raw, true) : [];
    if (!is_array($idea)) {
      $idea = [];
    }

    if ($keyword === '') {
      return new WP_Error('pga_no_kw', 'Keyword vazia.');
    }

    // --- LOCALIZA SEÇÃO ---
    $section = null;
    foreach ($sections as $s) {
      if ((string)($s['id'] ?? '') === (string)$section_id) {
        $section = $s;
        break;
      }
    }

    if (!$section) {
      return new WP_Error('pga_section_not_found', 'Seção não encontrada.');
    }

    // --- CACHE: NÃO REGERA ---
    $meta_key = '_pga_section_content_' . sanitize_key($section_id);
    $existing = get_post_meta($post_id, $meta_key, true);
    if (!empty($existing)) {
      return [
        'post_id'    => $post_id,
        'section_id' => $section_id,
        'content'    => $existing,
        'alreadyDone' => true,
      ];
    }

    // --- PROMPT (TUDO ACONTECE AQUI) ---
    $prompt = AlphaSuite_Prompts::build_section_prompt(
      $template,
      $keyword,
      $title,
      $section,
      $length,
      $locale,
      count($sections),
      $section_id,
      '',
      $url,
      $idea,
    );

    $resp = AlphaSuite_AI::complete(
      $prompt,
      [], // sem schema, é HTML/texto livre
      [
        'max_tokens'  => 2000,
        'temperature' => 0.7,
        'template'    => 'section',
      ]
    );

    if (is_wp_error($resp)) {
      return AlphaSuite_FailJob::fail_job($post_id, $resp);
    }

    $content_html = trim((string)($resp ?? ''));

    if ($content_html === '') {
      return new WP_Error('pga_section_empty', 'Conteúdo vazio.');
    }

    // --- SALVA ---
    update_post_meta($post_id, $meta_key, $content_html);

    return [
      'post_id'    => $post_id,
      'section_id' => $section_id,
      'content'    => $content_html,
    ];
  }

  public static function finalize_from_sections(int $post_id, array $args = [])
  {
    $post_id = intval($post_id);
    if (!$post_id || get_post_type($post_id) === null) {
      return new WP_Error('pga_invalid_post', 'Post inválido.');
    }

    // --- 1) Carrega outline e dados base ---
    $sections_json = get_post_meta($post_id, '_pga_outline_sections', true);
    $sections      = json_decode($sections_json, true) ?: [];

    if (!$sections) {
      return new WP_Error('pga_no_outline', 'Esboço não encontrado para este post.');
    }

    $locale    = get_post_meta($post_id, '_pga_outline_locale',   true) ?: 'pt_BR';
    $keyword   = get_post_meta($post_id, '_pga_outline_keyword',  true) ?: '';
    $template  = get_post_meta($post_id, '_pga_outline_template', true) ?: 'article';
    $title     = get_post_meta($post_id, '_pga_chosen_title',     true) ?: $keyword;
    $post_type = get_post_type($post_id) ?: 'posts_orion';

    // --- 3) Monta conteúdo final a partir das seções ---
    $htmlParts = [];
    foreach ($sections as $s) {
      $sid      = (string)($s['id'] ?? '');
      $meta_key = '_pga_section_content_' . sanitize_key($sid);
      $chunk    = get_post_meta($post_id, $meta_key, true);

      if ($chunk) {
        $htmlParts[] = $chunk;
      }
    }

    $content_html = trim(implode("\n\n", $htmlParts));

    // 🔥 Normaliza parágrafos
    $content_html = wpautop($content_html);

    // remove QUALQUER H1 gerado pela IA
    $content_html = preg_replace('#</?h1[^>]*>#i', '', $content_html);

    if ($content_html === '') {
      return new WP_Error('pga_final_empty', 'Nenhum conteúdo de seção encontrado para juntar.');
    }

    // --- 3.1) Remove APENAS o primeiro H2 (introdução)
    $content_html = self::remove_first_h2($content_html);

    $content_html = self::convert_to_blocks($content_html);

    // --- 3.2) Aplica links internos, se houver configuração ---
    $internal = [];
    if (!empty($args['internal_links']) && is_array($args['internal_links'])) {
      $internal = $args['internal_links'];
    }

    if (!empty($internal)) {
      $content_html = self::apply_internal_links_to_content(
        $content_html,
        $internal,
        (int) $post_id
      );
    }

    // --- 4) Meta dados ---
    $meta_title = get_post_meta($post_id, '_pga_meta_title',       true) ?: $title;
    $meta_desc  = get_post_meta($post_id, '_pga_meta_description', true) ?: '';
    $image_alt  = get_post_meta($post_id, '_pga_image_alt',        true) ?: '';

    $meta_desc = trim((string)$meta_desc);

    // se estiver vazio (ou muito fraco), gera
    // monta prompt padronizado
    $result = AlphaSuite_Meta_description::generate_meta($post_id, $content_html);

    if (is_wp_error($result)) {
      return $result;
    }

    $meta_desc = $result['meta'] ?? '';

    $excerpt = AlphaSuite_Excerpt::generate_excerpt($post_id, $content_html);

    if (is_wp_error($excerpt)) {
      return $excerpt;
    }

    // --- 5) Agenda / criação final do post ---
    $generate_image = array_key_exists('generate_image', $args)
      ? !empty($args['generate_image'])
      : true;

    $image_alt = trim((string)$image_alt);
    $kw = trim((string)$keyword);
    $ttl = trim((string)$title);

    // se não tiver alt salvo OU se não contém keyword, cria/ajusta
    if ($kw !== '') {
      $kw_l = mb_strtolower($kw);
      $alt_l = mb_strtolower($image_alt);

      if ($image_alt === '' || mb_strpos($alt_l, $kw_l) === false) {
        // formato simples, sempre válido e com keyword
        // (você pode trocar o texto fixo depois)
        $image_alt = $ttl !== '' && mb_strpos(mb_strtolower($ttl), $kw_l) !== false
          ? $ttl // se o título já contém a keyword, usa o título
          : ($kw . ' — ' . ($ttl !== '' ? $ttl : 'imagem ilustrativa'));

        // sanitiza e limita (alt não precisa ser grande)
        $image_alt = wp_strip_all_tags($image_alt);
        $image_alt = html_entity_decode($image_alt, ENT_QUOTES, 'UTF-8');
        $image_alt = preg_replace('/\s+/', ' ', $image_alt);
        if (mb_strlen($image_alt) > 125) {
          $image_alt = mb_substr($image_alt, 0, 122) . '...';
        }

        update_post_meta($post_id, '_pga_image_alt', $image_alt);
      }
    }

    // --- TAGS ------------------------------------------------
    $raw_tags = get_post_meta($post_id, '_pga_job_tags', true);

    if (is_array($raw_tags) && $raw_tags) {
      $term_ids = [];

      foreach ($raw_tags as $raw) {
        $raw = trim((string)$raw);
        if ($raw === '') continue;

        // ID direto
        if (ctype_digit($raw)) {
          $term_ids[] = (int)$raw;
          continue;
        }

        // texto → cria ou reutiliza
        $name = sanitize_text_field($raw);
        $exists = term_exists($name, 'post_tag');

        if (is_array($exists)) {
          $term_ids[] = (int)$exists['term_id'];
        } else {
          $created = wp_insert_term($name, 'post_tag');
          if (!is_wp_error($created) && !empty($created['term_id'])) {
            $term_ids[] = (int)$created['term_id'];
          }
        }
      }

      if ($term_ids) {
        wp_set_object_terms($post_id, array_unique($term_ids), 'post_tag', false);
      }
    }

    // --- FAQ (visível + JSON-LD) -----------------------------
    $faq_json = get_post_meta($post_id, '_pga_faq_jsonld', true);

    if ($faq_json) {
      $faq = is_string($faq_json)
        ? json_decode($faq_json, true)
        : $faq_json;

      if (is_array($faq)) {
        $faq_block = AlphaSuite_FAQ::render_faq_block($faq, $content_html);

        if ($faq_block !== '') {
          $content_html .= "\n\n" . $faq_block;
        }
      }
    }

    $res = self::do_schedule_post($post_id, [
      'keyword'        => $keyword,
      'title'          => $title,
      'content'        => $content_html,
      'locale'         => $locale,
      'post_id'        => $post_id,
      'template'       => $template,
      'post_type'      => $post_type,
      'meta_title'     => $meta_title,
      'meta_desc'      => $meta_desc,
      'image_alt'      => $image_alt,
      'generate_image' => $generate_image,
      'edit'           => get_edit_post_link($post_id, ''),
    ]);

    if (is_wp_error($res)) {
      return AlphaSuite_FailJob::fail_job($post_id, $res, 'finalize');
    }

    return [
      'ok'        => true,
      'post_id'   => $post_id,
      'edit'      => get_edit_post_link($post_id, ''),
      'view_link' => get_permalink($post_id),
      'keyword'   => $keyword,
    ];
  }

  private static function convert_to_blocks($content)
  {
    $protected_tags = [
      'ul' => 'list',
      'ol' => 'list',
      'table' => 'table',
      'blockquote' => 'quote',
      'pre' => 'code',
      'figure' => 'image'
    ];

    $placeholders = [];
    $i = 0;

    // 🔒 Protege blocos complexos
    foreach ($protected_tags as $tag => $block_type) {

      if (preg_match_all('#<' . $tag . '.*?>.*?</' . $tag . '>#si', $content, $matches)) {

        foreach ($matches[0] as $html) {

          $key = "__BLOCK_" . $i . "__";

          $placeholders[$key] = [
            'html' => $html,
            'type' => $block_type
          ];

          $content = str_replace($html, $key, $content);

          $i++;
        }
      }
    }

    $lines = preg_split('/\r\n|\r|\n/', $content);

    $out = [];

    foreach ($lines as $line) {

      $line = trim($line);
      if ($line === '') continue;

      // 🔄 restaura bloco protegido
      if (isset($placeholders[$line])) {

        $block = $placeholders[$line];

        $out[] = '<!-- wp:' . $block['type'] . ' -->';
        $out[] = $block['html'];
        $out[] = '<!-- /wp:' . $block['type'] . ' -->';

        continue;
      }

      // H2
      if (preg_match('#^<h2#i', $line)) {

        $out[] = '<!-- wp:heading {"level":2} -->';
        $out[] = $line;
        $out[] = '<!-- /wp:heading -->';
      }
      // H3
      elseif (preg_match('#^<h3#i', $line)) {

        $out[] = '<!-- wp:heading {"level":3} -->';
        $out[] = $line;
        $out[] = '<!-- /wp:heading -->';
      }
      // Parágrafo existente
      elseif (preg_match('#^<p#i', $line)) {

        $out[] = '<!-- wp:paragraph -->';
        $out[] = $line;
        $out[] = '<!-- /wp:paragraph -->';
      }
      // fallback
      else {

        $out[] = '<!-- wp:paragraph -->';
        $out[] = '<p>' . $line . '</p>';
        $out[] = '<!-- /wp:paragraph -->';
      }
    }

    return implode("\n", $out);
  }

  /**
   * Define limite "saudável" de links internos por tamanho de texto.
   */
  protected static function max_links_for_length(int $wordCount): int
  {
    if ($wordCount < 600)  return 5;
    if ($wordCount < 1200) return 8;
    if ($wordCount < 2000) return 10;
    if ($wordCount < 4000) return 15;
    return 5;
  }

  /**
   * Monta e injeta links internos no HTML final.
   * - Respeita modo (none/manual/auto/pillar)
   * - NUNCA passa do limite configurado e nem do limite por tamanho
   * - Distribui de baixo pra cima (final + meio).
   */
  protected static function apply_internal_links_to_content(
    string $html,
    array $opts,
    int $post_id
  ): string {
    $mode = isset($opts['mode']) ? trim((string)$opts['mode']) : 'none';
    if ($mode === 'none') {
      return $html;
    }

    $maxUser = max(0, intval($opts['max'] ?? 0));

    // conta palavras do conteúdo para limitar quantidade
    $plain      = wp_strip_all_tags($html);
    $wordCount  = max(0, str_word_count($plain));
    $maxBySize  = self::max_links_for_length($wordCount);

    // se usuário não pôs nada, usa limite natural
    if ($maxUser <= 0) {
      $maxUser = $maxBySize;
    }

    // limite final = não passar nem do tamanho nem do configurado
    $maxFinal = min($maxUser, $maxBySize);
    if ($maxFinal <= 0) {
      return $html;
    }

    // --- MONTA LISTA DE POSTS ALVO ---
    $targets = [];

    if ($mode === 'manual') {
      $idsRaw = isset($opts['manual_ids']) ? (string)$opts['manual_ids'] : '';
      $ids    = array_filter(array_map('intval', preg_split('/[,\s]+/', $idsRaw)));

      if (!$ids) {
        return $html;
      }

      $q = new \WP_Query([
        'post_type'      => 'post',
        'post__in'       => $ids,
        'posts_per_page' => count($ids),
        'orderby'        => 'post__in',
      ]);

      if ($q->have_posts()) {
        foreach ($q->posts as $p) {
          if ((int)$p->ID === (int)$post_id) {
            continue; // não linkar para si mesmo
          }
          $targets[] = [
            'url'   => get_permalink($p),
            'title' => get_the_title($p),
          ];
        }
      }
      wp_reset_postdata();
    } elseif ($mode === 'auto' || $mode === 'pillar') {

      // seleciona dos posts
      $post_type = 'post';

      // categorias do post atual
      $cat_ids = wp_get_post_terms($post_id, 'category', ['fields' => 'ids']);
      if (is_wp_error($cat_ids)) {
        $cat_ids = [];
      }

      // base: mesma categoria (quando existir)
      $base_args = [
        'post_type'      => $post_type,
        'post_status'    => 'publish',
        'posts_per_page' => $maxFinal * 2,
        'orderby'        => 'date',
        'order'          => 'DESC',
      ];

      $posts = get_posts($base_args);

      $posts = array_filter($posts, function ($p) use ($post_id) {
        return (int) $p->ID !== (int) $post_id;
      });

      if (!empty($cat_ids)) {
        $base_args['category__in'] = $cat_ids;
      }

      $q = null;

      if ($mode === 'pillar') {
        // 1) TENTA PRIMEIRO: posts PILAR (Yoast, Rank Math, AIOSEO) da mesma categoria
        $pillar_args = $base_args;

        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
        $pillar_args['meta_query'] = [
          'relation' => 'OR',

          // Yoast: conteúdo pilar / cornerstone
          [
            'key'   => '_yoast_wpseo_is_cornerstone',
            'value' => '1',
          ],

          // Rank Math: conteúdo pilar
          [
            'key'   => '_rank_math_pillar_content',
            'value' => '1',
          ],
          [
            'key'   => '_rank_math_pillar_content',
            'value' => 'on',
          ],

          // AIOSEO (ajustável se precisar)
          [
            'key'   => '_aioseo_pillar_content',
            'value' => '1',
          ],
        ];

        $q = new \WP_Query($pillar_args);

        // 2) SE NÃO TIVER NENHUM PILAR, cai para a base normal (mesma categoria)
        if (!$q->have_posts()) {
          $q = new \WP_Query($base_args);
        }
      } else {
        // modo AUTO → só mesma categoria, sem filtro de pilar
        $q = new \WP_Query($base_args);
      }


      if ($q && $q->have_posts()) {
        foreach ($q->posts as $p) {
          if ((int) $p->ID === (int) $post_id) {
            continue; // não linka pra ele mesmo
          }

          $targets[] = [
            'url'   => get_permalink($p),
            'title' => get_the_title($p),
          ];
        }
      }

      wp_reset_postdata();
    }

    if (empty($targets)) {
      return $html;
    }

    // garante que não vamos extrapolar a quantidade de posts
    // se tiver menos targets do que maxFinal, podemos repetir alguns
    $links = [];
    $i     = 0;
    while (count($links) < $maxFinal && !empty($targets)) {
      $links[] = $targets[$i % count($targets)];
      $i++;
    }

    if (!$links) {
      return $html;
    }

    return self::inject_internal_links_in_html($html, $links);
  }

  /**
   * Insere CTAs "Leia também" distribuídos no texto:
   * - Sempre a partir do meio pra baixo
   * - Nunca imediatamente em cima de <h2> (não cola no título)
   */
  protected static function inject_internal_links_in_html(string $html, array $links): string
  {
    // Normaliza lista de links (garante que tem url e title)
    $links = array_values(array_filter($links, function ($l) {
      return !empty($l['url']) && !empty($l['title']);
    }));

    $totalLinks = count($links);
    if ($totalLinks === 0 || trim($html) === '') {
      return $html;
    }

    // Se não tivermos H2, tudo vai pro final (regra do "último pode ir no final")
    $parts = preg_split(
      '~(<h2\b[^>]*>.*?</h2>)~is',
      $html,
      -1,
      PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
    );

    if (!$parts || count($parts) === 1) {
      $ctaHtml = '';
      foreach ($links as $l) {
        $ctaHtml .= sprintf(
          '<p><strong>Leia também:</strong> <a href="%s">%s</a></p>',
          esc_url($l['url']),
          esc_html($l['title'])
        );
      }
      return $html . "\n\n" . $ctaHtml;
    }

    // Índices dos blocos que são H2
    $h2Idx = [];
    foreach ($parts as $idx => $chunk) {
      if (preg_match('~^<h2\b~i', trim($chunk))) {
        $h2Idx[] = $idx;
      }
    }

    if (empty($h2Idx)) {
      // nenhum H2 detectado → tudo no final
      $ctaHtml = '';
      foreach ($links as $l) {
        $ctaHtml .= sprintf(
          '<p><strong>Leia também:</strong> <a href="%s">%s</a></p>',
          esc_url($l['url']),
          esc_html($l['title'])
        );
      }
      return $html . "\n\n" . $ctaHtml;
    }

    // Quantos links vamos colocar ACIMA de H2:
    // - se só tiver 1 link → ele pode ir no final do post
    // - se tiver 2+ → (total - 1) acima de H2, o último no final
    $linksAboveH2 = ($totalLinks > 1) ? $totalLinks - 1 : 0;
    $linksAboveH2 = min($linksAboveH2, count($h2Idx));

    $positions = [];

    if ($linksAboveH2 > 0) {
      $nCandidates = count($h2Idx);

      // Distribui os CTAs entre os H2, mais concentrado do meio pra baixo
      for ($i = 0; $i < $linksAboveH2; $i++) {
        if ($linksAboveH2 === 1) {
          // um só → perto do final
          $frac = 0.9;
        } else {
          // vários → espalha entre meio e final
          $frac = ($i + 1) / ($linksAboveH2 + 1);
        }

        $candIdx = (int) round($frac * ($nCandidates - 1));
        $candIdx = max(0, min($nCandidates - 1, $candIdx));
        $positions[] = $h2Idx[$candIdx];
      }

      // remove duplicados e ordena
      $positions = array_values(array_unique($positions));
      sort($positions);
    }

    // Mapeia: índice do bloco H2 -> CTAs que vão antes dele
    $injectMap = [];
    $linkIndex = 0;

    foreach ($positions as $pos) {
      if (!isset($links[$linkIndex])) break;

      $l = $links[$linkIndex];
      $cta = sprintf(
        '<p><strong>Leia também:</strong> <a href="%s">%s</a></p>',
        esc_url($l['url']),
        esc_html($l['title'])
      );

      if (!isset($injectMap[$pos])) {
        $injectMap[$pos] = [];
      }
      $injectMap[$pos][] = $cta;

      $linkIndex++;
    }

    // Reconstrói HTML inserindo CTAs ANTES dos H2 escolhidos
    $out = '';
    foreach ($parts as $idx => $chunk) {
      if (!empty($injectMap[$idx])) {
        $out .= implode("\n", $injectMap[$idx]) . "\n";
      }
      $out .= $chunk;
    }

    // Se ainda houver link sobrando (último), ele vai no FINAL do conteúdo
    if ($linkIndex < $totalLinks) {
      $out .= "\n\n";
      for (; $linkIndex < $totalLinks; $linkIndex++) {
        $l = $links[$linkIndex];
        $out .= sprintf(
          '<p><strong>Leia também:</strong> <a href="%s">%s</a></p>',
          esc_url($l['url']),
          esc_html($l['title'])
        );
      }
    }

    return $out;
  }

  /**
   * Remove apenas o primeiro <h2>...</h2> do conteúdo (introdução).
   */
  protected static function remove_first_h2(string $html): string
  {
    return preg_replace('/<h2\b[^>]*>.*?<\/h2>/is', '', $html, 1);
  }


  /**
   * Remove APENAS o primeiro <h2>...</h2> do conteúdo.
   * Assim o post final fica:
   *   H1 (do título do WP)
   *   parágrafo já de cara, sem H2 "Introdução".
   */
  protected static function drop_first_intro_h2(string $html): string
  {
    return (string) preg_replace('/<h2\b[^>]*>.*?<\/h2>/is', '', $html, 1);
  }
  /**
   * Decide quais posts serão alvo dos links internos.
   *
   * $cfg:
   *   - mode: 'none' | 'auto' | 'pillar' | 'manual'
   *   - max:  int
   *   - manual_ids: string "12,34,56" ou array
   */
  protected static function resolve_internal_link_targets(array $cfg, int $post_id): array
  {
    $mode = isset($cfg['mode']) ? (string) $cfg['mode'] : 'none';
    $max  = max(0, intval($cfg['max'] ?? 0));

    if ($mode === 'none' || $max <= 0) {
      return [];
    }

    $post_type = 'post';

    // --- MANUAL: usa IDs enviados ---
    if ($mode === 'manual') {
      $raw = $cfg['manual_ids'] ?? '';
      if (is_array($raw)) {
        $ids = array_map('intval', $raw);
      } else {
        $ids = array_filter(array_map('intval', preg_split('/[,\s]+/', (string) $raw)));
      }

      // remove o próprio post
      $ids = array_diff($ids, [$post_id]);
      if (empty($ids)) return [];

      $query = get_posts([
        'post_type'      => $post_type,
        'post_status'    => 'publish',
        'post__in'       => $ids,
        'orderby'        => 'post__in', // mantém ordem que veio no select
        'posts_per_page' => $max,
      ]);

      return is_array($query) ? $query : [];
    }

    $query = get_posts([
      'post_type'      => $post_type,
      'post_status'    => 'publish',
      'orderby'        => 'date',
      'order'          => 'DESC',
      'posts_per_page' => $max * 2,
    ]);

    $query = array_filter($query, function ($p) use ($post_id) {
      return (int) $p->ID !== (int) $post_id;
    });

    // Se quiser garantir limite final:
    $query = array_slice($query, 0, $max);

    return is_array($query) ? $query : [];
  }
  /**
   * Injeta parágrafos "Leia também" dentro do conteúdo.
   *
   * - Nunca repete o mesmo post várias vezes.
   * - Usa no máximo min(max, número de posts disponíveis).
   * - Tenta encaixar depois de parágrafos <p>…</p>.
   */
  protected static function inject_internal_links(string $html, int $post_id, array $cfg): string
  {
    $mode = isset($cfg['mode']) ? (string) $cfg['mode'] : 'none';
    $max  = max(0, intval($cfg['max'] ?? 0));

    if ($mode === 'none' || $max <= 0) {
      return $html;
    }

    $targets = self::resolve_internal_link_targets($cfg, $post_id);
    if (empty($targets)) {
      return $html;
    }

    // NUNCA excede o número de posts disponíveis
    $limit   = min($max, count($targets));
    $targets = array_slice($targets, 0, $limit);

    // quebra em blocos incluindo o </p> como delimitador
    $parts = preg_split('~(</p>)~i', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    if (!is_array($parts) || count($parts) < 2) {
      // fallback: sem <p>, só adiciona ao final
      $linksHtml = '';
      foreach ($targets as $t) {
        $linksHtml .= sprintf(
          '<p><strong>%s</strong> <a href="%s">%s</a></p>',
          esc_html__('Leia também:', 'alpha-suite'),
          esc_url(get_permalink($t->ID)),
          esc_html(get_the_title($t->ID))
        );
      }
      return $html . "\n\n" . $linksHtml;
    }

    $out       = '';
    $inserted  = 0;
    $paragraph = 0;

    foreach ($parts as $chunk) {
      $out .= $chunk;

      // sempre que achar um </p>, é chance de inserir CTA
      if (preg_match('~</p>~i', $chunk)) {
        if ($inserted < $limit) {
          $t = $targets[$inserted];

          $out .= sprintf(
            '<p><strong>%s</strong> <a href="%s">%s</a></p>',
            esc_html__('Leia também:', 'alpha-suite'),
            esc_url(get_permalink($t->ID)),
            esc_html(get_the_title($t->ID))
          );

          $inserted++;
        }

        $paragraph++;
      }
    }

    return $out;
  }

  private static function do_schedule_post(int $post_id, array $args = [])
  {
    $post_id = intval($post_id);
    if (!$post_id || get_post_type($post_id) === null) {
      return new WP_Error('pga_invalid_post', 'Post inválido.');
    }

    $keyword      = (string)($args['keyword']      ?? '');
    $title        = (string)($args['title']        ?? '');
    $content_html = (string)($args['content']      ?? '');
    $publish_ts = (int) get_post_meta($post_id, '_pga_publish_ts', true);

    // se por algum motivo não tiver meta (fallback raro)
    if (!$publish_ts) {
      // tenta args (caso fluxos antigos ainda enviem)
      if (!empty($args['publish_time'])) {
        $raw = $args['publish_time'];
        if (is_numeric($raw)) {
          $publish_ts = (int)$raw;
        } else {
          $t = strtotime((string)$raw);
          if ($t !== false) {
            $publish_ts = $t;
          }
        }
      }
    }

    $post_type    = !empty($args['post_type']) ? sanitize_key($args['post_type']) : (get_post_type($post_id) ?: 'posts_orion');

    $meta_title   = (string)($args['meta_title']   ?? '');
    $meta_desc    = (string)($args['meta_desc']    ?? '');
    $image_alt    = (string)($args['image_alt']    ?? '');

    if ($title === '' || $content_html === '') {
      return new WP_Error(
        'empty_content',
        'O conteúdo, o título e o resumo estão vazios.',
        ['status' => 400, 'post_id' => $post_id]
      );
    }

    // Garante mínimo de 60s no futuro
    if ($publish_ts < (time() + 60)) {
      $publish_ts = time() + 60;
    }

    // --- NÃO POSTAR DE MADRUGADA (00:00–05:59) ---
    $offset   = get_option('gmt_offset') * HOUR_IN_SECONDS;
    $local_ts = $publish_ts + $offset;
    $hour     = (int)gmdate('G', $local_ts);

    if ($hour >= 0 && $hour < 6) {
      $diff       = (6 - $hour) * HOUR_IN_SECONDS;
      $publish_ts = $publish_ts + $diff + wp_rand(300, 2400);
    }

    // Limpa qualquer H1
    $content_html = preg_replace('#</?h1[^>]*>#i', '', $content_html);
    $upd = [
      'ID'            => $post_id,
      'post_title'    => wp_strip_all_tags($title),
      'post_content'  => $content_html,
      'post_status'   => 'future',
      'post_type'     => $post_type,
      'post_date'     => get_date_from_gmt(gmdate('Y-m-d H:i:s', $publish_ts), 'Y-m-d H:i:s'),
      'post_date_gmt' => gmdate('Y-m-d H:i:s', $publish_ts),
    ];

    $updated_id = wp_update_post($upd, true);
    if (is_wp_error($updated_id)) {
      return new WP_Error(
        $updated_id->get_error_code() ?: 'pga_wp_update',
        $updated_id->get_error_message(),
        [
          'status'   => 500,
          'post_id'  => $post_id,
          'step'     => 'wp_update_post',
          'payload'  => $upd,
        ]
      );
    }

    // SEO / Meta
    if (!$meta_title) {
      $meta_title = $title;
    }

    if (class_exists('AlphaSuite_SEO')) {
      AlphaSuite_SEO::apply_meta($post_id, [
        'title'         => $meta_title,
        'description'   => $meta_desc,
        'focus_keyword' => $keyword,
      ]);
    }

    if ($meta_title) update_post_meta($post_id, '_pga_meta_title',       $meta_title);
    if ($meta_desc)  update_post_meta($post_id, '_pga_meta_description', $meta_desc);
    if ($image_alt)  update_post_meta($post_id, '_pga_image_alt',        $image_alt);

    // status do job
    update_post_meta($post_id, '_pga_job_status', 'done');
    delete_post_meta($post_id, '_pga_job_started');

    return [
      'edit'      => get_edit_post_link($post_id, ''),
      'post_id'   => $post_id,
      'view_link' => get_permalink($post_id),
    ];
  }
}
