<?php
if (!defined('ABSPATH')) exit;

class AlphaSuite_RSS
{
    public static function render(): void
    {
        $opt = AlphaSuite_Settings::get();
?>
        <div class="pga-wrap">
            <?php
            $chk = AlphaSuite_License::check('alpha_orion');

            // 1) Aviso geral: licença/módulo não ativo
            if (empty($chk['ok'])) {
                // link para o painel Alpha Suite (ajusta o slug se for diferente)
                $url = admin_url('admin.php?page=alpha-suite-dashboard');

                $msg = $chk['message'] ?: __('Licença do módulo Alpha Órion inativa. Ative o módulo para continuar gerando e publicando posts.', 'alpha-suite');

                echo '<div class="notice notice-error is-dismissible"><p>'
                    . esc_html($msg)
                    . ' <a href="' . esc_url($url) . '">'
                    . esc_html__('Clique aqui para ativar a licença.', 'alpha-suite')
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
                $default_tpls = ['rss'];
            }
            ?>
            <div class="wrap pga-layout">
                <div class="pga-header-fixed">
                    <div class="pga-header-col pga-a-center">
                        <div>
                            <h1><?php esc_html_e('Gerador RSS', 'alpha-suite'); ?></h1>
                            <p class="pga-descricao"><?php esc_html_e('Criação de artigos com base em RSS', 'alpha-suite'); ?></p>
                        </div>
                    </div>
                    <div class="pga-header-col pga-a-center ">
                        <?php
                        $label = esc_html__('Salvar e agendar', 'alpha-suite');

                        echo $chk['ok']
                            ? '<button type="button" id="pga_save_keywords" class="pga-rss pga_save_box"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-save h-4 w-4 mr-2">
                                <path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"></path>
                                <path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"></path>
                                <path d="M7 3v4a1 1 0 0 0 1 1h7"></path>
                            </svg> ' . esc_html($label) . '</button>'
                            : '<button type="button" id="pga_save_keywords" class="pga-rss" disabled> <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-save h-4 w-4 mr-2">
                                <path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"></path>
                                <path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"></path>
                                <path d="M7 3v4a1 1 0 0 0 1 1h7"></path>
                            </svg>' . esc_html($label) . '</button>';
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

                    <style>
                        .pga-row .pga-field {
                            flex: 1 1 calc(24% - 10px);
                        }
                    </style>
                    <!-- Contêiner de grupos -->
                    <div id="pga_gen_container">
                        <div class="pga-gen-box pga-collapse  pga-collapse--open" data-generator-id="uuid" data-gen="1">
                            <div class="pga-collapse-head">
                                <button type="button" class="button pga-collapse-toggle">
                                    <div class="pga-circle-status"></div>
                                    <span class="pga-gen-title"><?php esc_html_e('Título', 'alpha-suite'); ?></span>
                                    <span class="pga-actions-colapse">
                                        <label style="display:flex;align-items:center;gap:10px;margin-bottom:6px;">
                                            <span class="pga-switch">
                                                <input type="checkbox" class="pga_active" checked>
                                                <span class="pga-switch-ui" aria-hidden="true"></span>
                                                <span class="pga-switch-label">Ativo</span>
                                            </span>
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
                                <div class="pga-grid">
                                    <div class="pga-field">
                                        <label for="pga_keywords"><?php esc_html_e('URL do RSS', 'alpha-suite'); ?></label>
                                        <input
                                            type="url"
                                            id="pga_keywords"
                                            class="pga_keywords"
                                            rows="14"
                                            placeholder="<?php esc_html_e('Insira sua url', 'alpha-suite'); ?>" />
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
                                        <label for="pga_author"><?php esc_html_e('Autor', 'alpha-suite'); ?></label>

                                        <?php
                                        wp_dropdown_users([
                                            'show_option_none' => '— Sem autor —',
                                            'option_none_value' => '0',
                                            'name' => 'pga_author',
                                            'id' => 'pga_author',
                                            'class' => 'regular-text pga_author',
                                            'orderby' => 'display_name',
                                            'selected' => 1,
                                            'who' => 'authors'
                                        ]);
                                        ?>
                                    </div>
                                    <div class="pga-field">
                                        <?php $current = $opt['defaults']['locale'] ?? 'pt_BR'; ?>
                                        <label for="pga_locale"><?php esc_html_e('Idioma', 'alpha-suite'); ?></label>
                                        <select id="pga_locale" class="pga_locale">
                                            <option value="pt_BR" <?php selected($current, 'pt_BR'); ?>>🇧🇷 Português (Brasil)</option>
                                            <option value="pt_PT" <?php selected($current, 'pt_PT'); ?>>🇵🇹 Português (Portugal)</option>

                                            <option value="en_US" <?php selected($current, 'en_US'); ?>>🇺🇸 English (United States)</option>
                                            <option value="en_GB" <?php selected($current, 'en_GB'); ?>>🇬🇧 English (United Kingdom)</option>

                                            <option value="es_ES" <?php selected($current, 'es_ES'); ?>>🇪🇸 Español (España)</option>
                                            <option value="es_MX" <?php selected($current, 'es_MX'); ?>>🇲🇽 Español (México)</option>

                                            <option value="fr_FR" <?php selected($current, 'fr_FR'); ?>>🇫🇷 Français (France)</option>
                                            <option value="de_DE" <?php selected($current, 'de_DE'); ?>>🇩🇪 Deutsch (Deutschland)</option>

                                            <option value="it_IT" <?php selected($current, 'it_IT'); ?>>🇮🇹 Italiano</option>
                                            <option value="nl_NL" <?php selected($current, 'nl_NL'); ?>>🇳🇱 Nederlands</option>

                                            <option value="ja_JP" <?php selected($current, 'ja_JP'); ?>>🇯🇵 日本語</option>
                                            <option value="ko_KR" <?php selected($current, 'ko_KR'); ?>>🇰🇷 한국어</option>

                                            <option value="zh_CN" <?php selected($current, 'zh_CN'); ?>>🇨🇳 中文 (简体)</option>
                                            <option value="zh_TW" <?php selected($current, 'zh_TW'); ?>>🇹🇼 中文 (繁體)</option>

                                            <option value="hi_IN" <?php selected($current, 'hi_IN'); ?>>🇮🇳 हिन्दी</option>
                                            <option value="ar_SA" <?php selected($current, 'ar_SA'); ?>>🇸🇦 العربية</option>
                                            <option value="ru_RU" <?php selected($current, 'ru_RU'); ?>>🇷🇺 Русский</option>
                                        </select>
                                    </div>
                                    <div class="pga-field">
                                        <label for="pga_template_key">
                                            <?php esc_html_e('Modelo de Post', 'alpha-suite'); ?>
                                        </label>

                                        <?php
                                        $tpls_enabled = class_exists('AlphaSuite_Orion_Templates')
                                            ? AlphaSuite_Orion_Templates::get_enabled()
                                            : [
                                                'article' => ['label' => 'Artigo'],
                                            ];
                                        unset($tpls_enabled['modelar_youtube']);
                                        ?>

                                        <select id="pga_template_key" class="pga_template_key">
                                            <?php foreach ($tpls_enabled as $key => $tpl): ?>
                                                <option value="<?php echo esc_attr($key); ?>">
                                                    <?php echo esc_html($tpl['label'] ?? $key); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="pga-field" style="display: flex; gap: 10px; flex-direction: column">
                                        <label for="pga_link_mode" style="margin-bottom: -5px;"><?php esc_html_e('Links internos', 'alpha-suite'); ?></label>
                                        <select id="pga_link_mode" class="pga_link_mode">
                                            <option value="none"><?php esc_html_e('Sem link interno', 'alpha-suite'); ?></option>
                                            <option value="auto"><?php esc_html_e('Automático', 'alpha-suite'); ?></option>
                                            <option value="pillar"><?php esc_html_e('Post pilar', 'alpha-suite'); ?></option>
                                            <option value="manual"><?php esc_html_e('Manual', 'alpha-suite'); ?></option>
                                        </select>
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
                                    </div>
                                    <div class="pga-field">
                                        <label><?php esc_html_e('Tags', 'alpha-suite'); ?></label>

                                        <select class="pga_tags pga-select2" multiple="multiple" style="width:100%">
                                            <?php
                                            $tags = get_terms([
                                                'taxonomy'   => 'post_tag',
                                                'hide_empty' => false,
                                                'number'     => 0,
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
                                    <div class="pga-field">
                                        <label for="pga_length"><?php esc_html_e('Extensão', 'alpha-suite'); ?></label>
                                        <select class="pga_length" name="pga_length" id="pga_length">
                                            <option value="short" selected>Pequeno</option>
                                            <option value="medium">Médio</option>
                                            <option value="long">Longo</option>
                                            <option value="extra-long">Extra Longo</option>
                                        </select>
                                    </div>
                                    <div class="pga-field">
                                        <label>Palavras proíbidas</label>
                                        <select class="pga_block_words pga-select2" multiple="multiple">
                                            <option value="loteria">loteria</option>
                                            <option value="cassino">cassino</option>
                                            <option value="quina">quina</option>
                                        </select>
                                    </div>
                                    <div class="pga-field pga-card-row pga-bg-light">
                                        <label>
                                            <input type="checkbox" id="pga_make_faq" class="pga_make_faq">
                                            <?php esc_html_e('Criar FAQ', 'alpha-suite'); ?>
                                        </label>
                                        <div class="pga-faq-qty-wrap" style="display:none; width:100%">
                                            <label for="pga_faq_qty"><?php esc_html_e('Perguntas', 'alpha-suite'); ?></label>
                                            <input
                                                id="pga_faq_qty"
                                                class="pga_faq_qty"
                                                type="number"
                                                min="1"
                                                step="1"
                                                max="7"
                                                value="5">
                                        </div>
                                    </div>
                                    <div class="pga-field pga-card-row pga-bg-light">
                                        <label>
                                            <input type="checkbox" id="pga_enable_multilang" class="pga_enable_multilang">
                                            <?php esc_html_e('Criar tradução', 'alpha-suite'); ?>
                                        </label>
                                        <div class="pga_languages1" style="width:100%; display:none;">
                                            <select class="pga_languages pga-select2" multiple="multiple">
                                                <option value="pt">Português</option>
                                                <option value="en">English</option>
                                                <option value="es">Español</option>
                                                <option value="fr">Français</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="pga-field pga-card-row pga-bg-light">
                                        <h2 style="margin-bottom: 5px;"><?php esc_html_e('Agendamento', 'alpha-suite'); ?></h2>
                                        <div class="pga-field">
                                            <label for="pga_start_hour">Iniciar às:</label>
                                            <input type="number" min="0" max="23" id="pga_start_hour" value="6" class="pga_start_hour">
                                        </div>
                                        <div class="pga-field">
                                            <label for="pga_end_hour">Parar às:</label>
                                            <input type="number" min="0" max="23" id="pga_end_hour" value="23" class="pga_end_hour">
                                        </div>

                                        <div class="pga-field">
                                            <label for="pga_interval_hours">Intervalo (min):</label>
                                            <input type="number" min="0" max="1440" id="pga_interval_hours" value="30" class="pga_interval_hours">
                                        </div>
                                    </div>
                                </div>
                                <div class="pga-generator-footer">
                                    <button type="button" class="pga_test_box" <?php echo empty($chk['ok']) ? 'disabled' : '' ?>>
                                        <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px">
                                            <path d="M320-200v-560l440 280-440 280Zm80-280Zm0 134 210-134-210-134v268Z" />
                                        </svg>
                                        <?php esc_html_e('Gerar agora', 'alpha-suite'); ?>
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
}
