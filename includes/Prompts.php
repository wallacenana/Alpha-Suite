<?php
if (!defined('ABSPATH')) exit;

class AlphaSuite_Prompts
{
    public static function init(): void {}
    public static function register_ajax(): void
    {
        add_action('wp_ajax_pga_orion_prompts_export', [__CLASS__, 'ajax_export']);
        add_action('wp_ajax_pga_orion_prompts_import_prepare', [__CLASS__, 'ajax_import_prepare']);
        add_action('wp_ajax_pga_orion_prompts_import_apply', [__CLASS__, 'ajax_import_apply']);
        add_action('wp_ajax_pga_orion_template_delete', [__CLASS__, 'ajax_delete_template']);
    }

    public static function ajax_delete_template(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Sem permissão.'], 403);
        }

        $okNonce = check_ajax_referer('pga_orion_prompts_ie', '_ajax_nonce', false);
        if (!$okNonce) {
            wp_send_json_error(['message' => 'Nonce inválido.'], 403);
        }

        $slug = sanitize_key((string)($_POST['slug'] ?? ''));
        if ($slug === '' || in_array($slug, ['article', 'modelar_youtube', 'rss', 'global'], true)) {
            wp_send_json_error(['message' => 'Modelo inválido.'], 400);
        }

        // remove do templates
        $templates = get_option('pga_orion_templates', []);
        if (!is_array($templates)) $templates = [];
        unset($templates[$slug]);
        update_option('pga_orion_templates', $templates, false);

        // remove prompts órfãos
        $prompts = get_option(self::OPTION, []);
        if (!is_array($prompts)) $prompts = [];
        unset($prompts[$slug]);
        update_option(self::OPTION, $prompts, false);

        wp_send_json_success(['message' => "Modelo '{$slug}' removido do banco."]);
    }

    private static function ie_nonce_action(): string
    {
        return 'pga_orion_prompts_ie';
    }

    private static function export_filename(): string
    {
        // YYYY-MM-DD_HH-mm-ss
        return 'orion-prompts-' . gmdate('Y-m-d_H-i-s') . '.json';
    }

    const OPTION = 'pga_orion_prompts';
    public static function date()
    {
        return wp_date('d/m/Y');
    }

    /* =============================
    * STAGES (etapas)
    * ============================= */

    public static function stages(): array
    {
        return [
            'title'                => __('Título', 'alpha-suite'),
            'idea'                 => __('Ideia', 'alpha-suite'),
            'outline'              => __('Esboço', 'alpha-suite'),
            'section'              => __('Seções', 'alpha-suite'),
            'vistoria'             => __('Vistoria', 'alpha-suite'),
            'excerpt'              => __('Subtítulo', 'alpha-suite'),
            'meta_description'     => __('Meta descrição', 'alpha-suite'),
            'keywords'             => __('Gerar keywords', 'alpha-suite'),
            'slug'                 => __('Slug', 'alpha-suite'),
        ];
    }

    /* =============================
   * OPTION RAW
   * ============================= */
    public static function get_all_raw(): array
    {
        $opt = get_option(self::OPTION, []);
        return is_array($opt) ? $opt : [];
    }

    /* =============================
   * GET PROMPT (template + stage)
   * ============================= */
    public static function get_prompt_for(string $template, string $stage): string
    {
        $template = $template !== '' ? sanitize_key($template) : 'article';
        $stage    = $stage !== '' ? sanitize_key($stage) : 'content';

        $raw = self::get_all_raw();

        // 1) salvo do template
        if (isset($raw[$template][$stage]) && is_string($raw[$template][$stage])) {
            $v = trim($raw[$template][$stage]);
            if ($v !== '') return $v;
        }

        // 3) default interno
        return self::default_prompt_for($template, $stage);
    }

    /* =============================
   * DEFAULTS INTERNOS (CORE)
   * ============================= */
    public static function default_prompt_for(string $template, string $stage): string
    {
        $template = sanitize_key($template);
        $stage    = sanitize_key($stage);

        if ($template === 'ryan') {
            if ($stage === 'outline') {
                return self::default_outline_ryan_prompt();
            }
            if ($stage === 'section') {
                return self::default_section_ryan_prompt();
            }
        }

        if ($template === 'modelar_youtube') {
            if ($stage === 'title') {
                return self::default_title_modelar_youtube_prompt();
            }
            if ($stage === 'idea') {
                return self::default_idea_modelar_youtube_prompt();
            }
            if ($stage === 'outline') {
                return self::default_outline_modelar_youtube_prompt();
            }
            if ($stage === 'section') {
                return self::default_section_modelar_youtube_prompt();
            }
        }

        if ($template === 'rss') {
            if ($stage === 'title') {
                return self::default_title_rss_prompt();
            }
            if ($stage === 'idea') {
                return self::default_idea_rss_prompt();
            }
            if ($stage === 'outline') {
                return self::default_outline_rss_prompt();
            }
            if ($stage === 'section') {
                return self::default_section_rss_prompt();
            }
        }

        // core 1: article
        switch ($stage) {
            case 'title':
                return self::default_title_prompt();
            case 'idea':
                return self::default_idea_prompt();
            case 'outline':
                return self::default_outline_prompt();
            case 'section':
                return self::default_section_prompt();
            case 'slug':
                return self::default_slug_prompt();
            case 'image':
                return self::default_image_prompt();
            case 'excerpt':
                return self::default_excerpt_prompt();
            case 'meta_description':
                return self::default_meta_description_prompt();
            case 'post_thumbnail_regen':
                return self::default_post_thumbnail_regen_prompt();
            case 'vistoria':
                return self::default_vistoria_prompt();
            case 'story':
                return self::story_default_template();
            case 'keywords':
                return self::default_keywords_prompt();
            default:
                return self::default_outline_prompt();
        }
    }
    private static function replace_vars(string $tpl, array $vars): string
    {
        $map = [];
        foreach ($vars as $k => $v) {
            $map['{{' . $k . '}}'] = (string)$v;
        }
        return strtr($tpl, $map);
    }

    /* =============================
   * SUFFIX JSON (NÃO EDITÁVEL)
   * ============================= */
    private static function title_json_suffix(): string
    {
        return "Responda APENAS em JSON UTF-8 válido no formato:\n"
            . "{ \"title\": [\"Título 1\", \"Título 2\", \"Título 3\"] }\n";
    }

    public static function idea_brief_to_text($idea): string
    {
        if (!is_array($idea) || empty($idea)) {
            return '';
        }

        $lines = [];

        foreach ([
            'angle' => 'Ângulo',
            'intent' => 'Intenção',
            'core_problem' => 'Problema central',
            'reader_promise' => 'Promessa ao leitor',
            'tone' => 'Tom',
            'avoid' => 'Evitar',
        ] as $key => $label) {
            if (!empty($idea[$key])) {
                $value = $idea[$key];
                if (is_array($value)) {
                    $value = implode(', ', array_slice(array_filter(array_map('trim', $value)), 0, 8));
                }
                $value = trim((string) $value);
                if ($value !== '') {
                    $lines[] = "{$label}: {$value}";
                }
            }
        }

        if (!empty($idea['must_cover']) && is_array($idea['must_cover'])) {
            $must = array_slice(array_filter(array_map('trim', $idea['must_cover'])), 0, 8);
            if ($must) {
                $lines[] = 'Cobertura obrigatória: ' . implode(', ', $must);
            }
        }

        if (!empty($idea['section_plan']) && is_array($idea['section_plan'])) {
            $plan = [];
            foreach (array_slice($idea['section_plan'], 0, 10) as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $heading = trim((string)($item['heading'] ?? ''));
                $purpose = trim((string)($item['purpose'] ?? ''));
                if ($heading === '') {
                    continue;
                }
                $line = "- {$heading}";
                if ($purpose !== '') {
                    $line .= " — {$purpose}";
                }
                $plan[] = $line;
            }

            if ($plan) {
                $lines[] = "Plano sugerido:\n" . implode("\n", $plan);
            }
        }

        return trim(implode("\n", $lines));
    }

    private static function outline_json_suffix(): string
    {
        return
            "Você está tendo muitos problemas para gerar um json válido POR FAVOR, PRESTE MUITA ATENÇÃO NA ABERTURA E FECHAMENTO DAS TAGS, ESTOU GASTANDO MUITOS CRÉDITOS NESSE MODELO QUE SÓ ESTÁ RETORNANDO UM JSON VÁLIDO, SE ATENTE EM ENVIAR UM JSON VALIDO, COM NO MÁXIMO 1 CHILDREN\n"
            . "Responda SOMENTE em JSON UTF-8 válido, sem markdown, não se esqueça de fechar o json, crie um json 100% válido, TENTE NÃO COLOCAR EM ASPAS OS DETALHES IMPORTANTES, POIS ISSO VAI ATRAPALHAR NA CRIAÇÃO DE UM JSON VÁLIDO, FOQUE APENAS NO JSON VÁLIDO\n"
            . "Formato exato (MÁXIMO DE 20 BULLETS INDEPENDENTE DA OCASIÃO, PROIBIDO GERAR BULLETS DUPLICADOS E INFINITOS):\n"
            . "{"
            . "\"sections\": [\n"
            . "  {\n"
            . "   \"id\": 1,\n"
            . "   \"level\": \"h2\",\n"
            . "   \"heading\": \"Título H2...\",\n"
            . "   \"paragraph\": \"contexto sobre o tema apresentado (ex.: nesta sessão fale sobre [x] que aborda [xx])\",\n"
            . "   \"bullets\": [\n"
            . "     \"ex.: [quando]\",\n"
            . "     \"ex.: [onde]\"\n"
            . "     \"ex.: [porque]\"\n"
            . "     \"ex.: [quanto]\"\n"
            . "     \"ex.: [quem]\"\n"
            . "     \"ex.: [envolvidos]\"\n"
            . "  ],\n"
            . "  \"children\": [\n (se houver necessidade de h3 na sessão)"
            . "    {\n"
            . "      \"id\": 1,\n"
            . "      \"level\": \"h3\",\n"
            . "      \"heading\": \"Subtítulo H3...\",\n"
            . "      \"paragraph\": \"paragrafo sobre o h3...\",\n"
            . "      \"bullets\": [\n"
            . "        \"...\",\n"
            . "        \"...\"\n"
            . "      ]\n"
            . "    }\n"
            . "   ]\n"
            . "  }\n"
            . " ]\n"
            . "}\n"
            . "Responda SOMENTE em JSON UTF-8 válido no formato {\"sections\":[...]} sem qualquer texto antes ou depois. FORMATO VALIDO JSON COM NO MÁXIMO 20 BULLETS, MÁXIMO.\n\n";
    }

    private static function meta_description_json_suffix(): string
    {
        return "Responda APENAS em JSON UTF-8 válido, no formato {\"content\": \"...\"}.\n";
    }

    private static function extract_youtube_chapters(string $desc): array
    {
        $desc = str_replace("\r\n", "\n", $desc);
        $lines = array_map('trim', explode("\n", $desc));

        $chapters = [];
        foreach ($lines as $line) {
            // aceita 0:00, 00:00, 0:00:00, 00:00:00
            if (preg_match('/^(?:\d{1,2}:)?\d{1,2}:\d{2}\s+(.+)$/', $line, $m)) {
                $title = trim($m[1]);
                if ($title !== '') $chapters[] = $title;
            }
        }

        // remove duplicados e lixo
        $chapters = array_values(array_unique(array_filter($chapters, fn($t) => mb_strlen($t) >= 3)));

        return array_slice($chapters, 0, 30);
    }

    public static function build_idea_prompt(
        string $template,
        string $keyword,
        string $articleTitle,
        string $length,
        string $lang,
        string $url = '',
        string $content = ''
    ): string {
        $tpl = self::get_prompt_for($template, 'idea');

        [$minWords, $maxWords] = self::length_to_range($length);
        $cfg = self::outline_config($length);

        $base = self::replace_vars($tpl, [
            'keyword'      => $keyword,
            'articleTitle' => $articleTitle,
            'lang'         => $lang,
            'template'     => $template,
            'content'      => $content,
            'min_sections' => $cfg['min_sections'] ?? 4,
            'max_sections' => $cfg['max_sections'] ?? 8,
            'min_words'    => $minWords,
            'max_words'    => $maxWords,
            'date'         => SELF::date(),
        ]);

        $ctx = "\n\nCONTEXTO BASE:\n";
        $ctx .= "- Titulo do artigo: {$articleTitle}\n";
        $ctx .= "- Palavra-chave: {$keyword}\n";
        $ctx .= "- Idioma: {$lang}\n";
        $ctx .= "- Hoje e: " . SELF::date() . "\n";

        if ($url !== '') {
            $ctx .= "- URL base: {$url}\n";
        }

        if ($content !== '') {
            $plain = wp_strip_all_tags($content);
            $plain = html_entity_decode($plain, ENT_QUOTES, 'UTF-8');
            $plain = function_exists('mb_substr') ? mb_substr($plain, 0, 1200) : substr($plain, 0, 1200);
            $ctx .= "- Conteudo base (trecho):\n{$plain}\n";
        }

        $minSections = max(1, (int)($cfg['min_sections'] ?? 4));
        $maxSections = max($minSections, (int)($cfg['max_sections'] ?? 8));

        $suffix = ""
            . "Retorne apenas JSON UTF-8 valido.\n"
            . "Nao use markdown, crases ou texto fora do JSON.\n"
            . "Formato obrigatorio:\n"
            . "{\n"
            . "  \"angle\": \"\",\n"
            . "  \"intent\": \"\",\n"
            . "  \"core_problem\": \"\",\n"
            . "  \"reader_promise\": \"\",\n"
            . "  \"tone\": \"\",\n"
            . "  \"must_cover\": [\"\"],\n"
            . "  \"avoid\": [\"\"],\n"
            . "  \"section_plan\": [\n"
            . "    {\"heading\": \"\", \"purpose\": \"\", \"level\": \"h2\"}\n"
            . "  ]\n"
            . "}\n"
            . "section_plan deve ter entre {$minSections} e {$maxSections} itens.\n"
            . "Nao use 'Introducao' ou 'Conclusao' sem necessidade clara.\n"
            . "Nao invente fatos externos. O objetivo e criar um briefing editorial, nao fazer pesquisa.\n";

        return $base . $ctx . "\n\n" . $suffix . "\n";
    }

    public static function build_outline_prompt_modelar_youtube(
        string $url,
        array  $video,
        string $articleTitle,
        string $length,
        string $lang,
        array $idea = []
    ): string {

        $tpl    = self::get_prompt_for('modelar_youtube', 'outline');
        $lang = $lang ?: 'pt_BR';

        [$minWords, $maxWords] = self::length_to_range($length);
        $cfg = self::outline_config($length);

        $videoTitle       = trim((string)($video['title'] ?? ''));
        $videoDescription = trim((string)($video['description'] ?? ''));
        $tagsArr          = (array)($video['tags'] ?? []);

        // limpa e corta descrição
        if ($videoDescription !== '') {
            $videoDescription = wp_strip_all_tags($videoDescription);
            $videoDescription = html_entity_decode($videoDescription, ENT_QUOTES, 'UTF-8');
        }

        // chapters extraídos da descrição (timestamps)
        $chapters = self::extract_youtube_chapters($videoDescription);

        // corta descrição (evita token gigante)
        if ($videoDescription !== '') {
            $videoDescription = function_exists('mb_substr')
                ? mb_substr($videoDescription, 0, 900)
                : substr($videoDescription, 0, 900);
        }

        $tags = '';
        if (!empty($tagsArr)) {
            $tags = implode(', ', array_slice(array_map('trim', $tagsArr), 0, 25));
        }

        // prompt base editável
        $base = self::replace_vars($tpl, [
            'lang'       => $lang,
            'articleTitle' => $articleTitle,
            'url'          => trim((string)$url),
            'videoTitle'   => $videoTitle,
            'chapters'     => $chapters,
            'videoDescription' => $videoDescription,
            'tags'         => $tags,
        ]);

        // CONTEXTO INTERNO: o que deixa fiel
        $ctx  = "\n\nCONTEXTO INTERNO (não cite vídeo/canal/URL):\n";
        if ($videoTitle !== '') {
            $ctx .= "- Título do material base: {$videoTitle}\n";
        }
        $ctx .= "- Título do artigo: {$articleTitle}\n";
        $ctx .= "- Hoje é: " . SELF::date();
        $ctx .= "O esboço deve ser gerada no idioma, pode traduzir incluse a KW: {$lang}\n\n";

        if (!empty($chapters)) {
            $ctx .= "- Capítulos (use como esqueleto principal do outline):\n";
            foreach ($chapters as $c) {
                $ctx .= "  - {$c}\n";
            }
            $ctx .= "- Regra: use os capítulos como base do outline.\n";
            $ctx .= "- Se houver MAIS capítulos do que o máximo de seções permitido, AGRUPE capítulos relacionados em uma única seção H2.\n";
            $ctx .= "- Você deve respeitar o limite de seções H2 definido nas regras técnicas.\n";
        } else {
            $ctx .= "- Não há capítulos claros. Use o trecho da descrição para inferir a progressão.\n";
        }

        if ($videoDescription !== '') {
            $ctx .= "- Descrição (trecho):\n{$videoDescription}\n";
        }

        if ($tags !== '') {
            $ctx .= "- Tags (APENAS como apoio, não como base principal): {$tags}\n";
        }

        $ctx .= "- Regra: inclua uma introdução curta (primeira seção H2) contextualizando o tema.\n";
        $ctx .= "- Não use markdown; use somente HTML.\n";

        $ideaText = self::idea_brief_to_text($idea);
        if ($ideaText !== '') {
            $ctx .= "- Brief editorial (ideia validada):\n{$ideaText}\n";
        }

        $suffix = ""
            . "Responda apenas com JSON UTF-8 valido.\n"
            . "Nao use markdown, crases, comentarios ou texto fora do JSON.\n"
            . "Formato obrigatorio: {\"sections\":[...]}.\n"
            . "Use level=\"h2\" para secoes principais e level=\"h3\" para children.\n"
            . "Mantenha no maximo 1 child por secao.\n";

        return $base . $ctx . "\n\n" . $suffix . "\n"
            . "INSTRUÃ‡ÃƒO FINAL: se houver qualquer conflito entre regras, prioridade mÃ¡xima Ã© JSON vÃ¡lido e fiel ao tÃ­tulo/chapters/descriÃ§Ã£o.\n"
            . "NÃ£o invente campos extras. NÃ£o use blocos de cÃ³digo. NÃ£o escreva texto fora do JSON.\n";
    }

    /* =============================
   * BUILDERS (API pública)
   * ============================= */
    public static function build_title_prompt(
        string $template,
        string $keyword,
        int $min = 3,
        int $max = 5,
        string $lang = 'pt_BR'
    ): string {
        $tpl = self::get_prompt_for($template, 'title');

        $base = self::replace_vars($tpl, [
            'keyword' => $keyword,
            'lang'  => $lang,
            'template' => $template,
        ]);

        return
            "CONTEXTO DO TEMA:\n"
            . "Assunto principal: \"{$keyword}\"\n"
            . "O titulo deve ser gerado em {$lang} e pode traduzir a Keyword também de maneira fluida \n"
            . "Data atual: " . SELF::date() . " (use o ano quando relevante)\n\n"
            . $base
            . "\n\n"
            . "FORMATO DE SAÍDA:\n"
            . "- Retorne apenas JSON válido, sem markdown, sem comentários\n"
            . "- Siga exatamente a estrutura especificada abaixo\n"
            . self::title_json_suffix();
    }

    public static function build_title_rss_prompt(
        string $seed_title,
        string $lang = 'pt_BR',
        string $url = '',
        string $template = 'rss'
    ): string {

        $tpl = self::get_prompt_for($template, 'title');

        $base = self::replace_vars($tpl, [
            'tituloRef'  => $seed_title,
            'lang'     => $lang,
            'url'        => $url,
        ]);

        $sourceContext = $url
            ? "Fonte original da notícia: {$url}\n"
            : '';

        return
            "CONTEXTO DA NOTÍCIA:\n"
            . "Título base: \"{$seed_title}\"\n"
            . $sourceContext
            . "O titulo deve ser gerado em, pode traduzir incluse a KW: {$lang}\n"
            . "Data atual: " . self::date() . "\n\n"
            . "INSTRUÇÕES:\n"
            . "- Reescreva o título\n"
            . "- Não invente informações\n"
            . "- Evite clickbait exagerado\n\n"
            . $base
            . "\n\nFORMATO DE SAÍDA:\n"
            . "Responda APENAS em JSON UTF-8 válido no formato:\n"
            . "{ \"title\": \"Título final aqui\" }\n";
    }

    public static function build_outline_prompt(
        string $template,
        string $keyword,
        string $articleTitle,
        string $length,
        string $lang,
        string $url = '',
        string $content = '',
        array $idea = [],

    ): string {
        $tpl = self::get_prompt_for($template, 'outline');

        [$minWords, $maxWords] = self::length_to_range($length);
        $cfg = self::outline_config($length);

        $minSections = $cfg['min_sections'];
        $maxSections = $cfg['max_sections'];

        $base = self::replace_vars($tpl, [
            'keyword'      => $keyword,
            'articleTitle' => $articleTitle,
            'lang'         => $lang,
            'template'     => $template,
            'content'      => $content,
            'min_sections' => $minSections,
            'max_sections' => $maxSections,
            'max_words'    => $maxWords,
            'min_words'    => $minWords,
            'date'         => SELF::date()
        ]);

        $ideaText = self::idea_brief_to_text($idea);
        if ($ideaText !== '') {
            $base .= "\n\nBRIEF ESTRATEGICO:\n{$ideaText}\n";
        }

        $suffix = ""
            . "Responda apenas com JSON UTF-8 valido.\n"
            . "Nao use markdown, crases, comentarios ou texto fora do JSON.\n"
            . "Formato obrigatorio: {\"sections\":[...]}.\n"
            . "Use level=\"h2\" para secoes principais e level=\"h3\" para children.\n"
            . "Mantenha no maximo 1 child por secao.\n";

        $ctt = '';
        if ($content)
            $ctt = "O conteúdo a ser modelado é com base nesse:\n"
                . "----- INICIO ------\n"
                . $content . "\n"
                . "------ FIM -------\n\n";


        return $base . "\n\n" . $ctt . $suffix . "\n"
            . "INSTRUÃ‡ÃƒO FINAL: retorne somente JSON vÃ¡lido e mantenha o outline coerente com o tema solicitado.\n"
            . "Se algo estiver em dÃºvida, simplifique o outline em vez de sair do formato.\n";
    }

    public static function build_meta_description_prompt(string $template, string $keyword, string $articleTitle, string $lang = 'pt_BR', string $content = ''): string
    {
        $tpl = self::get_prompt_for($template, 'meta_description');

        $plain = '';
        if ($content !== '') {
            $plain = wp_strip_all_tags($content);
            $plain = html_entity_decode($plain, ENT_QUOTES, 'UTF-8');
            if (function_exists('mb_strlen') && mb_strlen($plain) > 1200) {
                $plain = mb_substr($plain, 0, 1200) . '...';
            } elseif (strlen($plain) > 1200) {
                $plain = substr($plain, 0, 1200) . '...';
            }
        }

        $base = self::replace_vars($tpl, [
            'keyword'      => $keyword,
            'articleTitle' => $articleTitle,
            'lang'       => $lang,
            'content'      => $plain,
        ]);

        $default = "Você é um especialista em SEO e Copywriting em {$lang}. Pode traduzir incluse a KW.\n"
            . "- Hoje é: " . SELF::date()
            . "\n Sua tarefa é criar uma meta descrição altamente clicável para o Google.\n"
            . "Título: \"{$articleTitle}\"\n"
            . "Palavra-chave principal: \"{$keyword}\"\n"
            . "A meta deve ser gerado em, pode traduzir incluse a KW: \"{$lang}\"\n";

        return $default . "\n\n" . $base . "\n\n" . self::meta_description_json_suffix();
    }

    public static function build_slug_prompt(string $template, string $keyword, string $articleTitle, string $lang = 'pt_br'): string
    {
        $tpl = self::get_prompt_for($template, 'slug');

        $base = self::replace_vars($tpl, [
            'keyword'      => $keyword,
            'articleTitle' => $articleTitle,
            'lang'       => $lang,
        ]);

        $default = "Palavra-chave principal: \"{$keyword}\"\n"
            . "Gere um slug de URL para o título: \"{$articleTitle}\"\n"
            . "A slug deve ser gerada em: \"{$lang}\"\n"
            . "- Hoje é: " . SELF::date() . "\n\n";

        return $default . "\n\n" . $base . "\n\n" . self::meta_description_json_suffix();
    }

    public static function build_ws_slide_image_prompt(string $title, string $desc, string $imageProvider = 'pexels'): string
    {
        $provider = strtolower(trim((string)$imageProvider));
        $title = trim((string)$title);
        $desc  = trim((string)$desc);

        if ($title === '' && $desc === '') {
            $title = 'Nature scene';
            $desc  = 'Outdoor landscape';
        }

        // ---- Caso A: bancos de imagem (Pexels/Unsplash) ----
        if ($provider === 'pexels' || $provider === 'unsplash') {
            return ""
                . "You are a search query generator for image banks (Pexels/Unsplash).\n"
                . "OUTPUT REQUIREMENTS:\n"
                . "- Return ONLY ONE search phrase with 2-4 WORDS\n"
                . "- Use CONCRETE and VISUAL elements (people, objects, actions, settings)\n"
                . "- OUTPUT MUST BE IN ENGLISH (lowercase, no punctuation)\n"
                . "- DO NOT use commas or multiple tags\n"
                . "- DO NOT use prefixes like 'image of', 'photo of'\n"
                . "- The image needs ONE central element\n\n"
                . "PHOTOGRAPHIC LANGUAGE:\n"
                . "- Use specific subjects: 'woman', 'man', 'laptop', 'mountain trail'\n"
                . "- NOT abstract concepts: 'marketing', 'digital', 'strategy'\n"
                . "- NOT AI terms: 'illustration', 'render', '3d', 'cinematic', 'realistic'\n"
                . "- NOT generic locations: 'natural reserve brazil' → use 'forest', 'river', 'jungle'\n\n"
                . "CORRECT EXAMPLES:\n"
                . "✅ 'woman working laptop'\n"
                . "✅ 'mountain trail hiker'\n"
                . "✅ 'waterfall forest'\n"
                . "✅ 'coffee cup table'\n\n"
                . "WRONG EXAMPLES:\n"
                . "❌ 'tourists walking natural reserve brazil' (too generic/long)\n"
                . "❌ 'digital marketing' (abstract)\n"
                . "❌ 'illustration of nature' (AI term + prefix)\n"
                . "❌ 'forest, river, mountain' (multiple tags)\n\n"
                . "OUTPUT FORMAT:\n"
                . "Return ONLY valid JSON UTF-8, no markdown, no extra text:\n"
                . "{ \"content\": \"your search term here\" }\n\n"
                . "CONTEXT:\n"
                . "Slide title: {$title}\n"
                . "Slide text: {$desc}\n\n"
                . "Generate the English search term in JSON format now:\n";
        }

        // ---- Caso B: geração (IA) para Web Stories 9:16 ----
        return ""
            . "You are an AI image prompt generator for vertical Web Story images (9:16 aspect ratio).\n"
            . "OUTPUT REQUIREMENTS:\n"
            . "- Return ONLY ONE prompt (short phrase or paragraph)\n"
            . "- OUTPUT MUST BE IN ENGLISH\n"
            . "- Describe a vertical-friendly scene\n\n"
            . "CONTENT RULES:\n"
            . "- Focus on nature/outdoor travel scenes related to the slide content\n"
            . "- NO text, letters, logos, or watermarks in the image\n"
            . "- NO sexualized people, glamour shots, or body-focused imagery\n"
            . "- PREFER landscapes, trails, rivers, waterfalls, forests\n"
            . "- Include simple lighting/atmosphere details (e.g., 'morning light', 'misty forest')\n\n"
            . "STYLE GUIDELINES:\n"
            . "- Photorealistic outdoor photography style\n"
            . "- Natural colors and lighting\n"
            . "- Vertical composition (portrait orientation)\n"
            . "- Clear central subject or focal point\n\n"
            . "CORRECT EXAMPLES:\n"
            . "✅ 'Mountain trail with morning mist, hiker in distance, vertical composition'\n"
            . "✅ 'Waterfall cascading through lush forest, natural lighting, portrait view'\n"
            . "✅ 'Person standing by river at sunset, vertical outdoor scene'\n\n"
            . "CONTEXT:\n"
            . "Slide title: {$title}\n"
            . "Slide text: {$desc}\n\n"
            . "Generate the English image prompt now:\n";
    }

    public static function build_image_prompt(
        string $keyword,
        string $title,
        string $lang,
        string $imageProvider = ''
    ): string {
        $provider = strtolower(trim((string)$imageProvider));
        $tpl = self::get_prompt_for('', 'image');

        // base com vars (serve pros 2 casos)
        $base = self::replace_vars($tpl, [
            'keyword'  => $keyword,
            'lang'   => "English",
            'title'    => $title,
        ]);

        // ---- Caso A: bancos de imagem (Pexels/Unsplash) ----
        if ($provider === 'pexels' || $provider === 'unsplash') {
            $rules = ""
                . "You are a search query generator for image banks (Pexels/Unsplash).\n"
                . "OUTPUT REQUIREMENTS:\n"
                . "- Return ONLY ONE search phrase with 2-4 WORDS\n"
                . "- Use CONCRETE and VISUAL elements (people, objects, actions, settings)\n"
                . "- OUTPUT MUST BE IN ENGLISH (lowercase, no punctuation)\n"
                . "- DO NOT use commas or multiple tags\n"
                . "- DO NOT use prefixes like 'image of', 'photo of'\n\n"
                . "PHOTOGRAPHIC LANGUAGE:\n"
                . "- Use specific subjects: 'woman', 'man', 'laptop', 'coffee cup'\n"
                . "- NOT abstract concepts: 'marketing', 'digital', 'strategy'\n"
                . "- NOT AI terms: 'illustration', 'render', '3d', 'cinematic', 'realistic'\n"
                . "- NOT generic locations: 'natural reserve brazil' → use 'forest', 'river', 'jungle'\n"
                . "- The image needs ONE central element\n\n"
                . "CORRECT EXAMPLES:\n"
                . "✅ 'woman working laptop'\n"
                . "✅ 'coffee cup desk'\n"
                . "✅ 'man walking forest'\n"
                . "✅ 'notebook open table'\n\n"
                . "WRONG EXAMPLES:\n"
                . "❌ 'tourists walking natural reserve brazil' (too generic/long)\n"
                . "❌ 'digital marketing strategy' (abstract)\n"
                . "❌ 'illustration of laptop' (AI term + prefix)\n"
                . "❌ 'laptop, coffee, notebook' (multiple tags)\n\n"
                . "CONTEXT:\n"
                . "Article title: {$title}\n"
                . "Keyword: {$keyword}\n\n"
                . "Generate the English search term now:\n";

            return $rules . "\n" . $base;
        }

        // ---- Caso B: geração (IA) ----
        $s = ""
            . "You are an AI image prompt generator for article thumbnails.\n"
            . "Create a detailed prompt to generate a thumbnail image.\n\n"
            . "CONTEXT:\n"
            . "- Title: \"{$title}\"\n"
            . "- Keyword: \"{$keyword}\"\n\n"
            . "RULES:\n"
            . "- Describe the scene with specific visual elements\n"
            . "- Include main subjects, actions, and environment\n"
            . "- Specify style if relevant (photorealistic, illustration, minimalist)\n"
            . "- Avoid text overlay on the image\n"
            . "- Keep it visually compelling and relevant to the topic\n\n"
            . "Generate the image prompt now:\n";

        return $s . "\n" . $base;
    }

    /* =============================
   * PROMPT: regen thumbnail por post
   * ============================= */
    public static function build_post_thumbnail_regen_prompt(string $title, string $content, string $lang = 'pt_BR', string $imageProvider = ''): string
    {
        // esse stage é "core", não depende do template selecionado no gerador
        $tpl = self::get_prompt_for('article', 'post_thumbnail_regen');

        // Regra dinâmica por provider
        $imageProvider = trim((string)$imageProvider);
        if ($imageProvider === 'pexels' || $imageProvider === 'unsplash') {
            $tpl .= "\n\nYou are a search term generator for image banks (Pexels/Unsplash).\n";
            $tpl .= "MANDATORY RULES:\n";
            $tpl .= "- Return ONLY ONE search phrase with MAX 3-4 WORDS\n";
            $tpl .= "- Use CONCRETE and VISUAL nouns (objects, people, actions, places)\n";
            $tpl .= "- Base it ONLY on the title: \"{$title}\"\n";
            $tpl .= "- DO NOT use commas, DO NOT separate into multiple tags\n";
            $tpl .= "- DO NOT use prefixes like \"Image of\", \"Photo of\"\n";
            $tpl .= "- DO NOT use abstract concepts (marketing, digital, strategy)\n";
            $tpl .= "- USE tangible visual elements\n";
            $tpl .= "- OUTPUT MUST BE IN ENGLISH\n\n";
            $tpl .= "CORRECT EXAMPLES:\n";
            $tpl .= "Title: '7 filmes de terror na Netflix' → 'person watching TV night'\n";
            $tpl .= "Title: 'Marketing digital para WordPress' → 'person working laptop'\n";
            $tpl .= "Title: 'Receitas de bolo de chocolate' → 'chocolate cake table'\n";
            $tpl .= "Title: '5 dicas de produtividade' → 'organized desk notebook'\n";
            $tpl .= "Title: 'Best hiking trails' → 'mountain trail hiker'\n\n";
            $tpl .= "WRONG EXAMPLES:\n";
            $tpl .= "❌ 'marketing, digital, wordpress' (multiple tags)\n";
            $tpl .= "❌ 'digital marketing' (too abstract)\n";
            $tpl .= "❌ 'Image of computer' (has prefix)\n";
            $tpl .= "❌ 'pessoa trabalhando laptop' (not in English)\n\n";
            $tpl .= "Now generate the English search term based on the provided title.\n";
        } else {
            $tpl .= "\n\nSpecific rules for AI image generation:\n";
            $tpl .= "title: \"{$title}\".\n";
            $tpl .= "context: {$content}.\n";
        }

        $plain = wp_strip_all_tags($content);
        $plain = html_entity_decode($plain, ENT_QUOTES, 'UTF-8');
        if (function_exists('mb_strlen') && mb_strlen($plain) > 1200) {
            $plain = mb_substr($plain, 0, 1200) . '...';
        } elseif (strlen($plain) > 1200) {
            $plain = substr($plain, 0, 1200) . '...';
        }

        return self::replace_vars($tpl, [
            'title'   => $title,
            'content' => $plain,
            'lang'  => $lang,
        ]);
    }

    /* =============================
   * STORIES: prompt por post (JSON fixo)
   * ============================= */
    public static function build_story_prompt_for_post(WP_Post $post, string $raw_html, string $brief = '', string $imageProvider = 'pollinations', string $lang = 'pt_BR'): string
    {
        $tpl = self::get_prompt_for('article', 'story');

        $title   = get_the_title($post);
        $content = wp_strip_all_tags($raw_html);
        $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');
        $lang  = $lang;

        $provider = strtolower(trim((string)$imageProvider));

        // ---- Regra dinâmica para campo "prompt" (imagens dos slides) ----
        if ($provider === 'pexels' || $provider === 'unsplash') {
            $image_rule = ""
                . "PROMPT FIELD RULES (for image banks - Pexels/Unsplash):\n"
                . "- Generate search queries IN ENGLISH\n"
                . "- Maximum 2-4 simple words per query\n"
                . "- Use CONCRETE visual elements related to the slide title\n"
                . "- Include specific objects: 'laptop', 'coffee cup', 'mountain', 'woman working'\n"
                . "- DO NOT use generic phrases like 'speed loading website image'\n"
                . "- DO NOT use the word 'image' or 'photo' (it's already an image bank)\n"
                . "- DO NOT use prepositions, articles, or compound words\n\n"
                . "CORRECT EXAMPLES:\n"
                . "✅ 'laptop desk coffee' (for productivity slide)\n"
                . "✅ 'mountain trail hiker' (for travel slide)\n"
                . "✅ 'woman phone smiling' (for communication slide)\n\n"
                . "WRONG EXAMPLES:\n"
                . "❌ 'image of speed loading website' (generic + has 'image')\n"
                . "❌ 'digital marketing strategy' (abstract, no visual elements)\n"
                . "❌ 'velocidade carregamento' (not in English)\n";
        } else {
            $image_rule = ""
                . "PROMPT FIELD RULES (for AI image generation):\n"
                . "- Generate prompts IN ENGLISH for VERTICAL PHOTOREALISTIC images\n"
                . "- Style: cinematic, natural lighting, portrait orientation (9:16)\n"
                . "- NO text overlays, logos, or watermarks\n"
                . "- Focus on the slide's main topic with clear visual elements\n"
                . "- Include lighting/atmosphere details when relevant\n";
        }

        $base = self::replace_vars($tpl, [
            'title'             => $title,
            'content'           => $content,
            'brief'             => $brief,
            'image_prompt_rule' => $image_rule,
        ]);

        $s  = "You are a specialist in transforming blog posts into AMP Web Stories.\n\n";
        $s .= "CONTEXT:\n";
        $s .= "- Title: {$title}\n";
        $s .= "- Content: {$content}\n";
        $s .= "- Brief: {$brief}\n";
        $s .= "- lang, pode traduzir incluse a KW: {$lang}\n\n";
        $s .= "TASK:\n";
        $s .= "Convert the blog post into an engaging Web Story following the rules below.\n\n";

        return $s . $base . "\n\n" . self::story_json_format_block();
    }

    /**
     * $ctx esperado:
     * - slidesCount (int)
     * - lang (pt_BR etc)
     * - title (string)
     * - content (string)  -> enviado por último no prompt
     * - cta_pages (array<int>) -> páginas (1-based) que DEVEM ter CTA
     * - cta_url_default (string) opcional (ex: permalink do post)
     * - cta_text_default (string) opcional
     */
    public static function build_ws_story_prompt(array $a): string
    {
        $slidesCount = max(1, (int)($a['slidesCount'] ?? 6));
        $lang      = (string)($a['lang'] ?? 'pt_BR');
        $title       = trim((string)($a['title'] ?? ''));
        $content     = trim((string)($a['content'] ?? ''));
        $cta_pages   = is_array($a['cta_pages'] ?? null) ? array_values(array_filter(array_map('absint', $a['cta_pages']))) : [];
        $cta_url_def  = trim((string)($a['cta_url_default'] ?? ''));

        if ($title === '') $title = 'Web Story';
        if ($content === '') $content = $title;

        $cta_pages_str = empty($cta_pages) ? 'nenhuma' : implode(', ', $cta_pages);

        $prompt = ""
            . "Você é um gerador de Web Stories a partir de conteúdo.\n"
            . "Todo o conteúdo deve ser gerado em: {$lang} (pode traduzir incluse a KW), é uma informação muito importante, tudo precisa estar no idioma {$lang}, independente do texto base.\n"
            . "Título base: {$title}\n"
            . "Quantidade de páginas: {$slidesCount}\n"
            . "Páginas com CTA (0-indexado): {$cta_pages_str}\n\n"

            . "FORMATO OBRIGATÓRIO:\n"
            . "Responda APENAS em JSON válido UTF-8.\n"
            . "NÃO use markdown. NÃO explique nada.\n\n"

            . "Estrutura obrigatória do JSON:\n"
            . "{\n"
            . "  \"title\": \"\",\n"
            . "  \"desc\": \"\",\n"
            . "  \"slug\": \"\",\n"
            . "  \"pages\": [\n"
            . "    {\n"
            . "      \"heading\": \"\",\n"
            . "      \"body\": \"\",\n"
            . "      \"cta_text\": \"\",\n"
            . "      \"cta_url\": \"\"\n"
            . "    }\n"
            . "  ]\n"
            . "}\n\n"

            . "Regra de CTA:\n"
            . "- Apenas as páginas listadas devem conter CTA.\n"
            . "- Nas páginas com CTA, use para o cta_text, crie CTAs que tenham a ver com o conteúdo, não seja obvia demais como \"veja mais\", \"saiba mais\", mas traga coisas nesse sentido com no máximo 3 palavras etc e cta_url=\"{$cta_url_def}\".\n"
            . "- Nas páginas SEM CTA, cta_text e cta_url devem ser string vazia.\n\n"
            . "Formato obrigatório:\n"
            . "Responda APENAS em JSON UTF-8 válido, COM UMA CHAVE \"content\".\n"
            . "A chave \"content\" deve conter UMA STRING que seja um JSON válido no formato abaixo.\n"
            . "Não use markdown. Não explique nada.\n\n"
            . "JSON alvo (title/desc/slug + pages) que deve estar DENTRO de content:\n"
            . "- Use título, Descrição e slug coerentes com o conteúdo.\n\n"
            . "Regras para a slug:\n"
            . "- Retire qualquer preposição, \"como\", \"é\", \"para\", etc\n"
            . "- Crie uma slug válida com no máximo 5 palavras no formato de tags\n"
            . "- Não inclua numeros sem sentido\n"
            . "- Regras para o tíutlo:\n"
            . "- Crie algo que vá ser coerente com os slides e coerente com o nivel de funil do título principal\n"
            . "- Obrigatório evitar palavras de outros niveis de funil\n"
            . "- Regras para a descrição:\n"
            . "- Analise o nivel do funil do conteúdo e crie algo condizente com isso, a descrição deve ter entre 120 e 160 caracteres com cta no final. CTA levando em conta o nivel de funil e assim proibindo palavras de outros niveis\n"
            . "Regras editoriais:\n"
            . "- Slide 1 = capa com headline forte (máx 38 caracteres) + gancho (1 frase), sempre sem CTA.\n"
            . "- Slides 2+ = progressão (máx 45 caracteres no heading)\n"
            . "- body curto (1 a 2 frases)\n"
            . "- Evite repetição de palavras entre slides\n"
            . "- Deve ter coerencia do primeiro ao ultimo slide, como uma história contada. Se o primeiro slide promete x itens, então o conteúdo tem q demonstrar esses x itens \n"
            . "- Sem 'Slide #', sem listas, sem markdown\n"
            . "- Gere exatamente {$slidesCount} itens em pages.\n\n"
            . "Conteúdo base (use como fonte, não copie literalmente):\n"
            . "Regra CRÍTICA do Slide 1 (capa):\n"
            . "- O item pages[0].heading deve ser MUITO chamativo e gerar curiosidade (headline forte).\n"
            . "- Máx 38 caracteres, sem ponto final, sem 'Slide 1', sem emoji.\n"
            . "- pages[0].body deve ser 1 frase curta (gancho), sem entregar tudo.\n"
            . "- Interprete o conteúdo e avalie e nivel de funil e proiba palavras de outros niveis de funil, se for meio de funil, proiba palavras de topo e fundo, e assim sucessivamente para todos os niveis.\n\n"

            . $content;

        return $prompt;
    }

    public static function ajax_export(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Sem permissão.'], 403);
        }

        $ok = check_ajax_referer(self::ie_nonce_action(), '_ajax_nonce', false);
        if (!$ok) {
            wp_send_json_error(['message' => 'Nonce inválido.'], 403);
        }

        $data = self::export_data();
        $data['_meta'] = [
            'exported_at_gmt' => gmdate('c'),
            'filename'        => self::export_filename(),
            'version'         => '1',
        ];

        wp_send_json_success($data);
    }


    /* =============================
   * UI: render_page (PROMPTS)
   * ============================= */
    public static function render_page(): void
    {
        $nonce_ie = wp_create_nonce(self::ie_nonce_action());
?>
        <script>
            window.PGA_PROMPTS_EXPORT = {
                ajaxurl: <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,
                nonce: <?php echo wp_json_encode($nonce_ie); ?>
            };
        </script>
        <?php

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Sem permissão.', 'alpha-suite'));
        }

        self::handle_save();

        $raw = self::get_all_raw();
        $stages = self::stages();

        $tpls = class_exists('AlphaSuite_Orion_Templates')
            ? AlphaSuite_Orion_Templates::get_all()
            : [
                'article' => ['label' => 'Artigo (padrão)', 'builtin' => 1, 'enabled' => 1],
                'modelar_youtube' => ['label' => 'Modelar YouTube', 'builtin' => 1, 'enabled' => 1],
                'rss' => ['label' => 'Modelar RSS', 'builtin' => 1, 'enabled' => 1],
                'ryan' => ['label' => 'Ryan Nascimento', 'builtin' => 1, 'enabled' => 1],
            ];

        // Garante que os 2 core sempre apareçam
        if (!isset($tpls['article'])) {
            $tpls['article'] = ['label' => 'Artigo (padrão)', 'builtin' => 1, 'enabled' => 1];
        }
        if (!isset($tpls['modelar_youtube'])) {
            $tpls['modelar_youtube'] = ['label' => 'Modelar YouTube', 'builtin' => 1, 'enabled' => 1];
        }
        if (!isset($tpls['rss'])) {
            $tpls['rss'] = ['label' => 'Modelar RSS', 'builtin' => 1, 'enabled' => 1];
        }
        if (!isset($tpls['ryan'])) {
            $tpls['ryan'] = ['label' => 'Ryan Nascimento', 'builtin' => 1, 'enabled' => 1];
        }


        // Ordena: core primeiro
        uksort($tpls, function ($a, $b) {
            $prio = ['article' => 0, 'modelar_youtube' => 2, 'rss' => 1, 'ryan' => 3, 'global' => 4];
            $pa = $prio[$a] ?? (!empty($tpls_all[$a]['builtin']) ? 10 : 20);
            $pb = $prio[$b] ?? (!empty($tpls_all[$b]['builtin']) ? 10 : 20);

            if ($pa !== $pb) return $pa <=> $pb;
            return strcmp($a, $b);
        });

        $core_templates = ['article', 'modelar_youtube', 'rss'];

        $core_defaults = [];
        foreach ($core_templates as $ct) {
            foreach (array_keys($stages) as $sk) {
                $core_defaults[$ct][$sk] = self::default_prompt_for($ct, $sk);
            }
        }

        // Templates salvos
        $tpls_all = class_exists('AlphaSuite_Orion_Templates')
            ? AlphaSuite_Orion_Templates::get_all()
            : [];

        // Garante os 2 nativos (se por algum motivo não vierem)
        if (empty($tpls_all['article'])) {
            $tpls_all['article'] = ['label' => 'Artigo (padrão)', 'enabled' => 1, 'builtin' => 1];
        }
        if (empty($tpls_all['modelar_youtube'])) {
            $tpls_all['modelar_youtube'] = ['label' => 'Modelar YouTube', 'enabled' => 1, 'builtin' => 1];
        }

        if (empty($tpls_all['rss'])) {
            $tpls_all['rss'] = ['label' => 'Modelar RSS', 'enabled' => 1, 'builtin' => 1];
        }

        if (empty($tpls_all['ryan'])) {
            $tpls_all['ryan'] = ['label' => 'Ryan Nascimento', 'enabled' => 1, 'builtin' => 1];
        }

        if (empty($tpls_all['global'])) {
            $tpls_all['global'] = ['label' => 'Global', 'enabled' => 1, 'builtin' => 1];
        }


        // Só pra organizar: nativos primeiro
        uksort($tpls_all, function ($a, $b) use ($tpls_all) {
            $ab = !empty($tpls_all[$a]['builtin']) ? 0 : 1;
            $bb = !empty($tpls_all[$b]['builtin']) ? 0 : 1;
            if ($ab !== $bb) return $ab <=> $bb;
            return strcmp($a, $b);
        });

        settings_errors('alpha-suite-orion-prompts');
        ?>
        <style>
            .pga-card {
                display: block;
            }
        </style>
        <div class="wrap">
            <div class="pga-topbar">
                <div class="pga-title-row">
                    <div>
                        <h1 class="pga-h1"><?php esc_html_e('Prompts Gerais', 'alpha-suite'); ?></h1>
                        <p class="pga-sub">
                            <?php esc_html_e('Configure o comportamento da IA por modelo e etapa. Campos vazios herdam automaticamente o padrão interno.', 'alpha-suite'); ?>
                        </p>
                    </div>
                    <div class="pga-import-export">
                        <button type="button" class="pga-btn" id="pga-prompts-export">
                            <?php esc_html_e('Exportar prompts', 'alpha-suite'); ?>
                        </button>

                        <button type="button" class="pga-btn" id="pga-prompts-import">
                            <?php esc_html_e('Importar prompts', 'alpha-suite'); ?>
                        </button>

                        <!-- input hidden pra abrir file picker -->
                        <input type="file" id="pga-prompts-import-file" accept="application/json" style="display:none" />
                    </div>

                </div>
            </div>

            <!-- ✅ FORM ENVOLVENDO TUDO (prompts + templates + footer) -->
            <form method="post" action="">
                <?php wp_nonce_field('pga_orion_prompts_save', 'pga_orion_prompts_nonce'); ?>
                <input type="hidden" name="pga_action" value="save">
                <div class="" id="pga-prompts-app">

                    <!-- TABS (Templates) -->
                    <div class="pga-tabs" role="tablist" aria-label="Modelos">
                        <?php
                        $tplIndex = 0;
                        foreach ($tpls as $tpl_slug => $tpl_meta):
                            $tpl_slug = sanitize_key((string)$tpl_slug);
                            $label    = (string)($tpl_meta['label'] ?? $tpl_slug);
                            $isActive = ($tplIndex === 0);
                            $tplIndex++;
                        ?>
                            <button
                                type="button"
                                class="pga-tab"
                                role="tab"
                                aria-selected="<?php echo $isActive ? 'true' : 'false'; ?>"
                                data-pga-tab="tpl"
                                data-tpl="<?php echo esc_attr($tpl_slug); ?>">
                                <span><?php echo esc_html($label); ?></span>
                            </button>
                        <?php endforeach; ?>

                        <!-- Global tab -->
                        <button
                            type="button"
                            class="pga-tab"
                            role="tab"
                            aria-selected="false"
                            data-pga-tab="tpl"
                            data-tpl="global">
                            <span><?php esc_html_e('Global', 'alpha-suite'); ?></span>
                        </button>
                    </div>

                    <?php
                    // ===== PANELS por template =====
                    $panelIndex = 0;
                    foreach ($tpls as $tpl_slug => $tpl_meta):
                        $tpl_slug = sanitize_key((string)$tpl_slug);
                        $label    = (string)($tpl_meta['label'] ?? $tpl_slug);
                        $isActive = ($panelIndex === 0);
                        $panelIndex++;

                        // lista de stages (keys)
                        $stageKeys = array_keys($stages);
                        $firstStage = $stageKeys[0] ?? '';
                    ?>
                        <section class="pga-panel <?php echo $isActive ? 'is-active' : ''; ?>"
                            data-pga-panel="tpl"
                            data-tpl="<?php echo esc_attr($tpl_slug); ?>"
                            aria-label="<?php echo esc_attr($label); ?>">

                            <!-- stage tabs -->
                            <div class="pga-stage-tabs" role="tablist" aria-label="Etapas">
                                <?php foreach ($stages as $stage_key => $stage_label): ?>
                                    <span class="pga-barra">
                                        <button
                                            type="button"
                                            class="pga-stage-tab <?php echo ($stage_key === $firstStage) ? 'is-active' : ''; ?>"
                                            data-pga-tab="stage"
                                            data-stage="<?php echo esc_attr($stage_key); ?>">
                                            <?php echo  esc_html($stage_label); ?>
                                        </button>
                                    </span>
                                <?php endforeach; ?>
                            </div>

                            <!-- stage panels (cards) -->
                            <?php foreach ($stages as $stage_key => $stage_label):

                                // valor salvo ou default efetivo
                                if (isset($raw[$tpl_slug]) && array_key_exists($stage_key, $raw[$tpl_slug])) {
                                    $val = is_string($raw[$tpl_slug][$stage_key]) ? $raw[$tpl_slug][$stage_key] : '';
                                } else {
                                    $val = self::default_prompt_for($tpl_slug, $stage_key);
                                }

                                $default = self::default_prompt_for($tpl_slug, $stage_key);
                                $canRestore = in_array($tpl_slug, ['article', 'modelar_youtube', 'rss', 'ryan'], true);
                            ?>
                                <div
                                    class="pga-stage-card"
                                    data-pga-panel="stage"
                                    data-stage="<?php echo esc_attr($stage_key); ?>"
                                    style="<?php echo ($stage_key === $firstStage) ? '' : 'display:none'; ?>">

                                    <div class="pga-stage-head">
                                        <!-- <h3>
                                            <?php echo esc_html($stage_label); ?>
                                            <?php if ($stage_key === 'titles'): ?>
                                                <span class="pga-stage-chip">Google Discover</span>
                                            <?php endif; ?>
                                        </h3> -->
                                    </div>

                                    <textarea
                                        class="pga-textarea"
                                        rows="25"
                                        name="pga_orion_prompts[<?php echo esc_attr($tpl_slug); ?>][<?php echo esc_attr($stage_key); ?>]"
                                        data-default-b64="<?php echo esc_attr(base64_encode((string)$default)); ?>"><?php echo esc_textarea($val); ?></textarea>
                                    <?php if ($canRestore): ?>
                                        <button type="button"
                                            class="pga-restore"
                                            data-pga-restore="1">
                                            <span class="dashicons dashicons-update"></span>
                                            <?php esc_html_e('Restaurar padrão', 'alpha-suite'); ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </section>
                    <?php endforeach; ?>


                    <!-- ===== GLOBAL PANEL ===== -->
                    <section class="pga-panel"
                        data-pga-panel="tpl"
                        data-tpl="global"
                        aria-label="Global">

                        <?php
                        $globalStages = [
                            'image' => __('Imagem Thumbnail', 'alpha-suite'),
                            'post_thumbnail_regen' => __('Regenerar thumbnail', 'alpha-suite'),
                            'image_stock'          => __('Imagem (Pexels / Unsplash)', 'alpha-suite'),
                            'story'                => __('Web Stories', 'alpha-suite'),
                        ];
                        $globalKeys = array_keys($globalStages);
                        $firstGlobal = $globalKeys[0] ?? '';
                        ?>

                        <div class="pga-stage-tabs" role="tablist" aria-label="Etapas globais">
                            <?php foreach ($globalStages as $stage_key => $stage_label): ?>
                                <span class="pga-barra">
                                    <button
                                        type="button"
                                        class="pga-stage-tab <?php echo ($stage_key === $firstGlobal) ? 'is-active' : ''; ?>"
                                        data-pga-tab="stage"
                                        data-stage="<?php echo esc_attr($stage_key); ?>">
                                        <?php echo esc_html($stage_label); ?>
                                    </button>
                                </span>
                            <?php endforeach; ?>
                        </div>

                        <?php foreach ($globalStages as $stage_key => $stage_label):

                            // valor salvo global ou default interno global
                            if (isset($raw['global'][$stage_key]) && is_string($raw['global'][$stage_key])) {
                                $val = $raw['global'][$stage_key];
                            } else {
                                $val = self::get_prompt_for('global', $stage_key);
                            }

                            $default = self::get_prompt_for('global', $stage_key);
                        ?>
                            <div
                                class="pga-stage-card"
                                data-pga-panel="stage"
                                data-stage="<?php echo esc_attr($stage_key); ?>"
                                style="<?php echo ($stage_key === $firstGlobal) ? '' : 'display:none'; ?>">
                                <div class="pga-card">
                                    <div class="pga-stage-head">
                                        <!-- <h3><?php echo esc_html($stage_label); ?></h3> -->

                                        <!-- ✅ nos globais: restaurar sempre -->
                                        <button type="button"
                                            class="pga-restore"
                                            data-pga-restore="1">
                                            <span class="dashicons dashicons-update"></span>
                                            <?php esc_html_e('Restaurar padrão', 'alpha-suite'); ?>
                                        </button>
                                    </div>

                                    <textarea
                                        class="pga-textarea"
                                        rows="25"
                                        name="pga_orion_prompts[global][<?php echo esc_attr($stage_key); ?>]"
                                        data-default-b64="<?php echo esc_attr(base64_encode((string)$default)); ?>"><?php echo esc_textarea($val); ?></textarea>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </section>


                    <!-- ✅ MODAL: Modelos de conteúdo (DENTRO DO FORM) -->
                    <div class="pga-modal" id="pga-templates-modal" aria-hidden="true">
                        <div class="pga-modal__backdrop" data-pga-modal-close></div>

                        <div class="pga-modal__panel" role="dialog" aria-modal="true" aria-labelledby="pga-templates-title">
                            <div class="pga-modal__head">
                                <h2 id="pga-templates-title"><?php esc_html_e('Modelos de conteúdo', 'alpha-suite'); ?></h2>
                                <button type="button" class="pga-btn" data-pga-modal-close><?php esc_html_e('Fechar', 'alpha-suite'); ?></button>
                            </div>

                            <p class="pga-table-description" style="margin-top:0;">
                                <?php esc_html_e('Aqui você escolhe quais modelos aparecem no gerador do Órion. O plugin mantém 2 nativos: Artigo e Modelar YouTube.', 'alpha-suite'); ?>
                            </p>

                            <table class="pga-table" id="pga-orion-templates-table">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Modelo', 'alpha-suite'); ?></th>
                                        <th style="width:240px;"><?php esc_html_e('Ativo', 'alpha-suite'); ?></th>
                                        <th style="width:180px;"><?php esc_html_e('Padrão', 'alpha-suite'); ?></th>
                                        <th style="width:160px;text-align:right;"><?php esc_html_e('Ações', 'alpha-suite'); ?></th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php foreach ($tpls_all as $slug => $row):
                                        $slug = sanitize_key((string)$slug);
                                        $is_builtin = !empty($row['builtin']) || in_array($slug, ['global', 'article', 'modelar_youtube', 'rss'], true);
                                        $label = (string)($row['label'] ?? $slug);
                                        $enabled = !empty($row['enabled']) ? 1 : 0;
                                        $is_default = !empty($row['is_default']) ? 1 : 0;
                                    ?>
                                        <tr data-slug="<?php echo esc_attr($slug); ?>" data-builtin="<?php echo $is_builtin ? '1' : '0'; ?>">
                                            <td>
                                                <input
                                                    class="pga-input"
                                                    name="pga_orion_templates[<?php echo esc_attr($slug); ?>][label]"
                                                    value="<?php echo esc_attr($label); ?>"
                                                    <?php echo $is_builtin ? 'disabled' : ''; ?>>
                                            </td>
                                            <td>
                                                <?php if ($slug !== 'global'): ?>

                                                    <!-- sempre envia 0, mesmo desmarcado -->
                                                    <input type="hidden"
                                                        name="pga_orion_templates[<?php echo esc_attr($slug); ?>][is_default]"
                                                        value="0">

                                                    <label class="pga-mini" style="display:flex;align-items:center;gap:8px;">
                                                        <input type="checkbox"
                                                            name="pga_orion_templates[<?php echo esc_attr($slug); ?>][is_default]"
                                                            value="1"
                                                            <?php checked((int)$is_default === 1); ?>>
                                                        <span><?php esc_html_e('Novo projeto', 'alpha-suite'); ?></span>
                                                    </label>

                                                <?php endif; ?>
                                            </td>

                                            <td>
                                                <div style="display: <?php echo $slug === 'article' || $slug === 'global' || $slug === 'modelar_youtube' ? 'none' : 'block' ?>">
                                                    <label>
                                                        <input type="checkbox"
                                                            name="pga_orion_templates[<?php echo esc_attr($slug); ?>][enabled]"
                                                            value="1"
                                                            <?php checked($enabled === 1); ?>
                                                            <?php echo $is_builtin ? 'disabled' : ''; ?>>
                                                        <strong><?php echo $enabled ? esc_html__('Ativo', 'alpha-suite') : esc_html__('Inativo', 'alpha-suite'); ?></strong>
                                                    </label>

                                                    <?php if ($is_builtin): ?>
                                                        <input type="hidden"
                                                            name="pga_orion_templates[<?php echo esc_attr($slug); ?>][enabled]"
                                                            value="1">
                                                    <?php endif; ?>
                                                </div>
                                            </td>

                                            <td style="text-align:right;">
                                                <?php if (!$is_builtin): ?>
                                                    <button type="button" class="pga-btn pga-remove-tpl-row"><?php esc_html_e('Remover', 'alpha-suite'); ?></button>
                                                <?php else: ?>
                                                    <span class="pga-mini">—</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>

                                <tfoot>
                                    <tr>
                                        <td colspan="4">
                                            <button type="button" class="pga-btn pga-btn--primary" id="pga-add-tpl-row">+ <?php esc_html_e('Adicionar modelo personalizado', 'alpha-suite'); ?></button>
                                            <span class="pga-mini" style="margin-left:10px;"><?php esc_html_e('Ex.: receitas, review, modelar_url', 'alpha-suite'); ?></span>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- Loading overlay -->
                    <div class="pga-loading" id="pga-loading" aria-hidden="true">
                        <div class="pga-loading-card"><?php esc_html_e('Carregando…', 'alpha-suite'); ?></div>
                    </div>

                    <!-- ✅ BARRA FIXA (DENTRO DO FORM) -->
                    <div class="pga-bottom-bar">
                        <div class="pga-bottom-left">
                            <button type="submit" class="pga-btn pga-btn--primary">
                                <?php esc_html_e('Salvar prompts', 'alpha-suite'); ?>
                            </button>

                            <button type="button" class="pga-btn" id="pga-open-templates">
                                <?php esc_html_e('Modelos', 'alpha-suite'); ?>
                            </button>
                            <button type="button" class="pga-btn" id="pga-vars-btn">
                                <?php esc_html_e('Variáveis Disponíveis', 'alpha-suite'); ?>
                            </button>
                            <div id="pga-vars-panel" class="pga-vars-panel">
                                <div class="pga-vars-pop" id="pga-vars-pop" aria-hidden="true">
                                    <div class="pga-vars-pop__body">
                                        <div class="pga-vars-grid">
                                            <h3><?php esc_html_e('Título', 'alpha-suite'); ?></h3>
                                            <code>{{keyword}}</code>
                                            <code>{{lang}}</code>
                                            <code>{{template}}</code>

                                            <h3><?php esc_html_e('Esboço', 'alpha-suite'); ?></h3>
                                            <code>{{keyword}}</code>
                                            <code>{{articleTitle}}</code>
                                            <code>{{lang}}</code>
                                            <code>{{template}}</code>

                                            <h3><?php esc_html_e('Esboço', 'alpha-suite'); ?> Youtube</h3>
                                            <code>{{articleTitle}}</code>
                                            <code>{{lang}}</code>
                                            <code>{{url}}</code>
                                            <code>{{videoTitle}}</code>
                                            <code>{{chapters}}</code>
                                            <code>{{videoDescription}}</code>
                                            <code>{{tags}}</code>

                                            <h3><?php esc_html_e('Sessão', 'alpha-suite'); ?></h3>
                                            <code>{{keyword}}</code>
                                            <code>{{articleTitle}}</code>
                                            <code>{{lang}}</code>
                                            <code>{{section_number}}</code>
                                            <code>{{section_heading}}</code>
                                            <code>{{section_level}}</code>
                                            <code>{{section_bullets}}</code>
                                            <code>{{section_children}}</code>
                                            <code>{{sections_count}}</code>

                                            <h3><?php esc_html_e('Descrição', 'alpha-suite'); ?></h3>
                                            <code>{{keyword}}</code>
                                            <code>{{articleTitle}}</code>
                                            <code>{{lang}}</code>
                                            <code>{{content}}</code>

                                            <h3><?php esc_html_e('Slug', 'alpha-suite'); ?></h3>
                                            <code>{{keyword}}</code>
                                            <code>{{articleTitle}}</code>
                                            <code>{{lang}}</code>

                                            <h3><?php esc_html_e('Re-geração (image_stock)', 'alpha-suite'); ?></h3>
                                            <code>{{content}}</code>
                                            <code>{{title}}</code>
                                            <code>{{lang}}</code>

                                            <h3><?php esc_html_e('Imagem', 'alpha-suite'); ?></h3>
                                            <code>{{keyword}}</code>
                                            <code>{{title}}</code>
                                            <code>{{template}}</code>
                                            <code>{{lang}}</code>

                                            <h3><?php esc_html_e('Stories', 'alpha-suite'); ?></h3>
                                            <code>{{title}}</code>
                                            <code>{{content}}</code>
                                            <code>{{brief}}</code>
                                            <code>{{image_prompt_rule}}</code>

                                            <h3><?php esc_html_e('Keywords', 'alpha-suite'); ?></h3>
                                            <code>{{lang}}</code>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div><!-- /app -->
            </form>
        </div>

        <script>
            (function() {
                // =========================
                // Helpers
                // =========================
                function decodeB64Unicode(b64) {
                    try {
                        const bytes = Uint8Array.from(atob(b64), c => c.charCodeAt(0));
                        return new TextDecoder('utf-8').decode(bytes);
                    } catch (e) {
                        try {
                            return atob(b64);
                        } catch (e2) {
                            return '';
                        }
                    }
                }

                function showLoading(on) {
                    const el = document.getElementById('pga-loading');
                    if (!el) return;
                    el.classList.toggle('is-on', !!on);
                    el.setAttribute('aria-hidden', on ? 'false' : 'true');
                }

                // =========================
                // Template Tabs
                // =========================
                document.addEventListener('click', function(e) {
                    const btn = e.target.closest('[data-pga-tab="tpl"]');
                    if (!btn) return;

                    const tpl = btn.getAttribute('data-tpl');
                    if (!tpl) return;

                    // set active tab
                    document.querySelectorAll('[data-pga-tab="tpl"]').forEach(t => {
                        t.setAttribute('aria-selected', (t === btn) ? 'true' : 'false');
                    });

                    // show panel
                    document.querySelectorAll('[data-pga-panel="tpl"]').forEach(p => {
                        const is = p.getAttribute('data-tpl') === tpl;
                        p.classList.toggle('is-active', is);
                    });
                });

                // =========================
                // Stage Tabs (inside active template panel)
                // =========================
                document.addEventListener('click', function(e) {
                    const btn = e.target.closest('[data-pga-tab="stage"]');
                    if (!btn) return;

                    // find current template panel
                    const panel = btn.closest('[data-pga-panel="tpl"]');
                    if (!panel) return;

                    const stage = btn.getAttribute('data-stage');
                    if (!stage) return;

                    // set active stage tab inside this panel
                    panel.querySelectorAll('[data-pga-tab="stage"]').forEach(t => {
                        t.classList.toggle('is-active', (t === btn));
                    });

                    // show stage card inside this panel
                    panel.querySelectorAll('[data-pga-panel="stage"]').forEach(c => {
                        const is = c.getAttribute('data-stage') === stage;
                        c.style.display = is ? '' : 'none';
                    });
                });

                document.getElementById('pga-vars-btn').addEventListener('click', function() {
                    var panel = document.getElementById('pga-vars-panel');
                    panel.style.display = panel.style.display === 'block' ? 'none' : 'block';
                });

                (function() {
                    function decodeB64Unicode(b64) {
                        try {
                            const bytes = Uint8Array.from(atob(b64), c => c.charCodeAt(0));
                            return new TextDecoder('utf-8').decode(bytes);
                        } catch (e) {
                            try {
                                return atob(b64);
                            } catch (e2) {
                                return '';
                            }
                        }
                    }

                    document.addEventListener('click', function(e) {
                        const btn = e.target.closest('.pga-restore,[data-pga-restore="1"]');
                        if (!btn) return;

                        const stagePanel = btn.closest('[data-pga-panel="stage"]') || btn.closest('.pga-stage');
                        if (!stagePanel) return;

                        const ta = stagePanel.querySelector('textarea[data-default-b64]');
                        if (!ta) return;

                        const val = decodeB64Unicode(ta.getAttribute('data-default-b64') || '');

                        const apply = () => {
                            ta.value = val;
                            ta.dispatchEvent(new Event('input', {
                                bubbles: true
                            }));
                        };

                        if (window.Swal) {
                            Swal.fire({
                                title: 'Restaurar padrão?',
                                text: 'Vamos substituir o conteúdo atual deste campo pelo padrão do sistema.',
                                icon: 'warning',
                                showCancelButton: true,
                                confirmButtonText: 'Restaurar',
                                cancelButtonText: 'Cancelar'
                            }).then(r => {
                                if (r.isConfirmed) apply();
                            });
                        } else {
                            if (confirm('Restaurar padrão deste campo?')) apply();
                        }
                    });
                })();

                // =========================
                // Modal open/close
                // =========================
                function openModal() {
                    const m = document.getElementById('pga-templates-modal');
                    if (!m) return;
                    m.classList.add('is-open');
                    m.setAttribute('aria-hidden', 'false');
                }

                function closeModal() {
                    const m = document.getElementById('pga-templates-modal');
                    if (!m) return;
                    m.classList.remove('is-open');
                    m.setAttribute('aria-hidden', 'true');
                }

                document.addEventListener('click', function(e) {
                    if (e.target.closest('#pga-open-templates')) {
                        e.preventDefault();
                        openModal();
                    }
                    if (e.target.closest('[data-pga-modal-close]')) {
                        e.preventDefault();
                        closeModal();
                    }
                });

                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        const m = document.getElementById('pga-templates-modal');
                        if (m && m.classList.contains('is-open')) closeModal();
                    }
                });

                // =========================
                // Templates: add/remove row (names kept!)
                // =========================
                function slugify(s) {
                    s = (s || '').toString().trim().toLowerCase();
                    s = s.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
                    s = s.replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
                    if (!s) s = 'modelo_' + Math.floor(Math.random() * 9999);
                    return s;
                }

                // ✅ ADD TEMPLATE (SweetAlert) — substitui o window.prompt
                document.addEventListener('click', async function(e) {
                    const addBtn = e.target.closest('#pga-add-tpl-row');
                    if (!addBtn) return;

                    const table = document.getElementById('pga-orion-templates-table');
                    if (!table) return;

                    const tbody = table.querySelector('tbody');
                    if (!tbody) return;

                    // helper: slugify (mantém o seu padrão)
                    function slugify(str) {
                        return String(str || '')
                            .normalize('NFD').replace(/[\u0300-\u036f]/g, '') // remove acentos
                            .toLowerCase()
                            .trim()
                            .replace(/[^a-z0-9]+/g, '_')
                            .replace(/^_+|_+$/g, '');
                    }

                    // ✅ se não tiver Swal, cai pro prompt antigo
                    if (!window.Swal) {
                        const label = window.prompt('Nome do modelo (ex.: Receitas, Review, Modelar URL):', '');
                        if (!label) return;

                        const slug = slugify(label);
                        if (!slug) return;

                        if (tbody.querySelector(`tr[data-slug="${CSS.escape(slug)}"]`)) {
                            alert('Já existe um modelo com esse slug: ' + slug);
                            return;
                        }

                        const tr = document.createElement('tr');
                        tr.setAttribute('data-slug', slug);
                        tr.setAttribute('data-builtin', '0');
                        tr.innerHTML = `
                            <td>
                                <input class="pga-input"
                                name="pga_orion_templates[${slug}][label]"
                                value="${String(label).replace(/"/g,'&quot;')}">
                            </td>
                            <td>
                                <div class="pga-switch">
                                <label>
                                    <input type="checkbox"
                                    name="pga_orion_templates[${slug}][enabled]"
                                    value="1" checked>
                                    <strong>Ativo</strong>
                                </label>
                                </div>
                            </td>
                            <td style="text-align:right;">
                                <button type="button" class="pga-btn pga-remove-tpl-row">Remover</button>
                            </td>
                            `;
                        tbody.appendChild(tr);
                        return;
                    }

                    // ✅ SweetAlert modal input
                    const res = await Swal.fire({
                        title: 'Adicionar modelo',
                        html: `<div style="text-align:left">
                            <div style="font-size:13px;color:#666;margin:0 0 10px">
                            Digite um nome (ex.: <b>Receitas</b>, <b>Review</b>, <b>Modelar URL</b>).
                            </div>
                            <input id="pga_tpl_label" class="swal2-input" style="margin: 0!important" placeholder="Nome do modelo" autocomplete="off">
                        </div>`,
                        focusConfirm: false,
                        showCancelButton: true,
                        confirmButtonText: 'Adicionar',
                        cancelButtonText: 'Cancelar',
                        preConfirm: () => {
                            const label = (document.getElementById('pga_tpl_label')?.value || '').trim();
                            const enabled = 'enabled';

                            if (label.length < 2) {
                                Swal.showValidationMessage('Digite um nome com ao menos 2 caracteres.');
                                return false;
                            }

                            const slug = slugify(label);
                            if (!slug) {
                                Swal.showValidationMessage('Não consegui gerar o slug. Tente outro nome.');
                                return false;
                            }

                            // evita duplicar slug
                            if (tbody.querySelector(`tr[data-slug="${CSS.escape(slug)}"]`)) {
                                Swal.showValidationMessage('Já existe um modelo com esse slug: ' + slug);
                                return false;
                            }

                            return {
                                label,
                                slug,
                                enabled
                            };
                        },
                    });

                    if (!res.isConfirmed || !res.value) return;

                    const {
                        label,
                        slug,
                        enabled
                    } = res.value;

                    const tr = document.createElement('tr');
                    tr.setAttribute('data-slug', slug);
                    tr.setAttribute('data-builtin', '0');

                    tr.innerHTML = `
                        <td>
                        <input class="pga-input"
                            name="pga_orion_templates[${slug}][label]"
                            value="${String(label).replace(/"/g, '&quot;')}">
                        </td>
                        <td>
                        <div class="pga-switch">
                            <label>
                            <input type="checkbox"
                                name="pga_orion_templates[${slug}][enabled]"
                                value="1" ${enabled ? 'checked' : ''}>
                            <strong>${enabled ? 'Ativo' : 'Inativo'}</strong>
                            </label>
                        </div>
                        </td>
                        <td style="text-align:right;">
                        <button type="button" class="pga-btn pga-remove-tpl-row">Remover</button>
                        </td>
                    `;

                    tbody.appendChild(tr);

                    try {
                        document.body.classList.add('pga-has-unsaved');
                    } catch (e) {}

                    if (window.Swal) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Modelo adicionado',
                            html: `
                                <div style="text-align:left">
                                    <div><b>${label}</b></div>
                                    <div style="margin-top:6px;color:#666;font-size:13px">
                                    Ele já aparece na lista, mas <b>ainda não foi salvo</b>.<br>
                                    Clique em <b>Salvar prompts</b> na barra inferior para gravar.
                                    </div>
                                </div>
                                `,
                            confirmButtonText: 'OK',
                            allowOutsideClick: true,
                        });
                    }

                });


                document.addEventListener('click', function(e) {
                    const rm = e.target.closest('.pga-remove-tpl-row');
                    if (!rm) return;

                    const tr = rm.closest('tr');
                    if (!tr) return;

                    // remove row
                    tr.parentNode.removeChild(tr);
                });

            })();
        </script>
<?php
    }

    private static function handle_save(): void
    {
        $method = isset($_SERVER['REQUEST_METHOD'])
            ? strtoupper((string) sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])))
            : '';

        if ($method !== 'POST') return;

        if (!current_user_can('manage_options')) return;

        $action = isset($_POST['pga_action'])
            ? sanitize_key((string) wp_unslash($_POST['pga_action']))
            : '';

        // =========================================================
        // 1) IMPORT (form separado)
        // =========================================================
        if ($action === 'import') {

            if (
                empty($_POST['pga_orion_prompts_import_nonce']) ||
                !wp_verify_nonce(
                    sanitize_text_field(wp_unslash($_POST['pga_orion_prompts_import_nonce'])),
                    'pga_orion_prompts_import'
                )
            ) {
                add_settings_error(
                    'alpha-suite-orion-prompts',
                    'pga_import_nonce',
                    __('Nonce inválido no import.', 'alpha-suite'),
                    'error'
                );
                return;
            }

            if (empty($_FILES['pga_orion_import_file']['tmp_name'])) {
                add_settings_error(
                    'alpha-suite-orion-prompts',
                    'pga_import_file',
                    __('Envie um arquivo JSON para importar.', 'alpha-suite'),
                    'error'
                );
                return;
            }

            $raw = isset($_FILES['pga_orion_import_file']['tmp_name']) ? file_get_contents(sanitize_text_field(wp_unslash($_FILES['pga_orion_import_file']['tmp_name']))) : '';
            $data = json_decode((string) $raw, true);

            if (!is_array($data)) {
                add_settings_error(
                    'alpha-suite-orion-prompts',
                    'pga_import_json',
                    __('JSON inválido.', 'alpha-suite'),
                    'error'
                );
                return;
            }

            return;
        }

        // =========================================================
        // 2) SAVE PROMPTS (form principal)
        // =========================================================
        if (
            empty($_POST['pga_orion_prompts_nonce']) ||
            !wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST['pga_orion_prompts_nonce'])),
                'pga_orion_prompts_save'
            )
        ) {
            return;
        }

        $raw = [];
        if (isset($_POST['pga_orion_prompts'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $raw = wp_unslash($_POST['pga_orion_prompts']);
        }

        $out = [];

        if (is_array($raw)) {
            foreach ($raw as $tpl => $st) {
                $tpl = sanitize_key((string)$tpl);
                if (!is_array($st)) continue;

                foreach ($st as $stage => $val) {
                    $stage = sanitize_key((string)$stage);
                    if (!is_string($val)) continue;

                    $out[$tpl][$stage] = wp_kses_post($val);
                }
            }
        }

        // templates (do modal)
        // ... você já tem $templates e $out prontos

        $templates = isset($_POST['pga_orion_templates']) ? array_map('sanitize_text_field', (array) wp_unslash($_POST['pga_orion_templates'])) : [];

        // ✅ normaliza slugs válidos que ficaram na tabela (inclui nativos)
        $keep = [];
        foreach ($templates as $slug => $row) {
            $slug = sanitize_key((string)$slug);
            if ($slug === '') continue;
            if ($slug === 'global') continue;
            $keep[$slug] = true;
        }
        $keep['article'] = true;
        $keep['modelar_youtube'] = true;

        // ✅ remove prompts de templates que não existem mais
        if (is_array($out)) {
            foreach (array_keys($out) as $tpl_slug) {
                $tpl_slug = sanitize_key((string)$tpl_slug);
                if ($tpl_slug === 'global') continue;
                if (empty($keep[$tpl_slug])) {
                    unset($out[$tpl_slug]);
                }
            }
        }

        // templates (do modal)
        $templates_post = [];
        if (isset($_POST['pga_orion_templates'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $templates_post = (array) wp_unslash($_POST['pga_orion_templates']);
        }

        $clean_templates = [];
        // 2) depois, normaliza o que veio do POST
        foreach ($templates_post as $slug => $row) {
            $slug = sanitize_key((string) $slug);
            if ($slug === '' || $slug === 'global') continue;

            // nativos já foram forçados acima
            if (isset($clean_templates[$slug]) && !empty($clean_templates[$slug]['builtin'])) {
                continue;
            }

            $row = is_array($row) ? $row : [];

            $label      = sanitize_text_field((string) ($row['label'] ?? $slug));
            $enabled    = !empty($row['enabled']) ? 1 : 0;
            $is_default = !empty($row['is_default']) ? 1 : 0;

            // se for padrão, tem que estar ativo
            if ($is_default) $enabled = 1;

            $clean_templates[$slug] = [
                'label'      => $label ?: $slug,
                'enabled'    => $enabled,
                'builtin'    => 0,
                'is_default' => $is_default,
            ];
        }

        // 3) limpa prompts de templates que não existem mais
        $keep = array_fill_keys(array_keys($clean_templates), true);

        if (is_array($out)) {
            foreach (array_keys($out) as $tpl_slug) {
                $tpl_slug = sanitize_key((string) $tpl_slug);
                if ($tpl_slug === 'global') continue;

                if (empty($keep[$tpl_slug])) {
                    unset($out[$tpl_slug]);
                }
            }
        }

        update_option('pga_orion_templates', $clean_templates, false);
        update_option(self::OPTION, $out, false);


        add_settings_error(
            'alpha-suite-orion-prompts',
            'pga_orion_prompts_updated',
            __('Prompts salvos com sucesso.', 'alpha-suite'),
            'updated'
        );
    }

    private static function export_data(): array
    {
        $templates = get_option('pga_orion_templates', []);
        if (!is_array($templates)) $templates = [];

        $keep = ['article' => true, 'modelar_youtube' => true];
        foreach ($templates as $slug => $_) {
            $slug = sanitize_key((string)$slug);
            if ($slug && $slug !== 'global') $keep[$slug] = true;
        }

        $prompts = get_option(self::OPTION, []);
        if (!is_array($prompts)) $prompts = [];

        $filtered_prompts = [];
        foreach ($prompts as $tpl_slug => $stages) {
            $tpl_slug = sanitize_key((string)$tpl_slug);
            if (empty($keep[$tpl_slug])) continue;
            if (!is_array($stages)) continue;
            $filtered_prompts[$tpl_slug] = $stages;
        }

        if (!is_array($templates)) $templates = [];
        if (!is_array($prompts))   $prompts   = [];

        // remove "global" de templates se aparecer
        if (isset($templates['global'])) unset($templates['global']);

        return [
            'templates' => $templates,
            'prompts'   => $prompts,
        ];
    }

    public static function ajax_import_prepare(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Sem permissão.'], 403);
        }

        $ok = check_ajax_referer(self::ie_nonce_action(), '_ajax_nonce', false);
        if (!$ok) {
            wp_send_json_error(['message' => 'Nonce inválido.'], 403);
        }

        // IMPORTANT: o JS envia como "file"
        if (
            empty($_FILES['file']) ||
            ! isset($_FILES['file']['tmp_name'])
        ) {
            wp_send_json_error(['message' => 'Arquivo não recebido (campo "file").'], 400);
        }

        $f = array_map('sanitize_text_field', wp_unslash($_FILES['file']));


        if (!empty($f['error'])) {
            wp_send_json_error(['message' => 'Erro no upload: ' . (int)$f['error']], 400);
        }

        $raw = @file_get_contents($f['tmp_name']);
        if (!$raw) {
            wp_send_json_error(['message' => 'Não consegui ler o arquivo enviado.'], 400);
        }

        $json = json_decode($raw, true);
        if (!is_array($json)) {
            wp_send_json_error(['message' => 'JSON inválido.'], 400);
        }

        // valida estrutura mínima
        $templates = isset($json['templates']) && is_array($json['templates']) ? $json['templates'] : [];
        $prompts   = isset($json['prompts'])   && is_array($json['prompts'])   ? $json['prompts']   : [];

        if (!$templates && !$prompts) {
            wp_send_json_error(['message' => 'Arquivo não contém "templates" nem "prompts".'], 400);
        }

        // monta lista importável
        $items = self::build_import_items($templates, $prompts);

        // token + transient com payload (15 min)
        $token = wp_generate_password(20, false, false);
        $uid   = get_current_user_id();
        $tkey  = "pga_orion_ie_{$uid}_{$token}";

        set_transient($tkey, [
            'uid'       => $uid,
            'templates' => $templates,
            'prompts'   => $prompts,
            'items'     => $items,
            'created'   => time(),
        ], 15 * MINUTE_IN_SECONDS);

        wp_send_json_success([
            'token' => $token,
            'items' => $items,
        ]);
    }

    public static function ajax_import_apply(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Sem permissão.'], 403);
        }

        $ok = check_ajax_referer(self::ie_nonce_action(), '_ajax_nonce', false);
        if (!$ok) {
            wp_send_json_error(['message' => 'Nonce inválido.'], 403);
        }

        $token = isset($_POST['token']) ? sanitize_text_field(wp_unslash($_POST['token'])) : '';
        if ($token === '') {
            wp_send_json_error(['message' => 'Token ausente.'], 400);
        }

        $uid  = get_current_user_id();
        $tkey = "pga_orion_ie_{$uid}_{$token}";
        $pack = get_transient($tkey);

        if (!is_array($pack) || (int)($pack['uid'] ?? 0) !== (int)$uid) {
            wp_send_json_error(['message' => 'Token inválido/expirado.'], 400);
        }

        $overwrite = !empty($_POST['overwrite']) && (string)$_POST['overwrite'] === '1';

        $keys_json = isset($_POST['keys'])
            ? sanitize_text_field(wp_unslash($_POST['keys']))
            : '[]';
        $keys = json_decode($keys_json, true);
        if (!is_array($keys) || empty($keys)) {
            wp_send_json_error(['message' => 'Nenhum item selecionado.'], 400);
        }

        $templates = is_array($pack['templates'] ?? null) ? $pack['templates'] : [];
        $prompts   = is_array($pack['prompts'] ?? null) ? $pack['prompts'] : [];

        $result = self::apply_import_selected($templates, $prompts, $keys, $overwrite);

        // mata o token
        delete_transient($tkey);

        wp_send_json_success([
            'message' => sprintf('Importado: %d item(ns).', (int)$result['imported']),
            'details' => $result,
        ]);
    }

    private static function build_import_items(array $templates, array $prompts): array
    {
        $allowed_stages = array_keys(self::stages());
        $allowed_stages = array_merge($allowed_stages, ['image', 'image_stock', 'post_thumbnail_regen', 'story']);

        // existentes
        $current_templates = get_option('pga_orion_templates', []);
        $current_prompts   = get_option(self::OPTION, []);

        if (!is_array($current_templates)) $current_templates = [];
        if (!is_array($current_prompts))   $current_prompts   = [];

        $items = [];

        // templates
        foreach ($templates as $slug => $meta) {
            $slug = sanitize_key((string)$slug);
            if ($slug === '' || $slug === 'global') continue; // não importar "global" como template

            $label = is_array($meta) && isset($meta['label']) ? (string)$meta['label'] : $slug;

            $items[] = [
                'key'         => 'tpl:' . $slug,
                'type'        => 'template',
                'tpl'         => $slug,
                'stage'       => '',
                'label'       => $label,
                'hasExisting' => !empty($current_templates[$slug]),
                'size'        => (int) strlen($label),
            ];
        }

        // prompts
        foreach ($prompts as $tpl_slug => $stages) {
            $tpl_slug = sanitize_key((string)$tpl_slug);
            if ($tpl_slug === '' || $tpl_slug === 'global') continue; // global é tratado separado (se você quiser)
            if (!is_array($stages)) continue;

            foreach ($stages as $stage => $val) {
                $stage = sanitize_key((string)$stage);
                if (!in_array($stage, $allowed_stages, true)) continue;
                if (!is_string($val)) continue;

                $hasExisting = !empty($current_prompts[$tpl_slug]) && array_key_exists($stage, (array)$current_prompts[$tpl_slug]);

                $items[] = [
                    'key'         => 'pr:' . $tpl_slug . ':' . $stage,
                    'type'        => 'prompt',
                    'tpl'         => $tpl_slug,
                    'stage'       => $stage,
                    'label'       => '',
                    'hasExisting' => $hasExisting,
                    'size'        => (int) strlen($val),
                ];
            }
        }

        return $items;
    }

    private static function apply_import_selected(array $import_templates, array $import_prompts, array $keys, bool $overwrite): array
    {
        $allowed_stages = array_keys(self::stages());
        $allowed_stages = array_merge($allowed_stages, ['image', 'image_stock', 'post_thumbnail_regen', 'story']);

        $current_templates = get_option('pga_orion_templates', []);
        $current_prompts   = get_option(self::OPTION, []);

        if (!is_array($current_templates)) $current_templates = [];
        if (!is_array($current_prompts))   $current_prompts   = [];

        $imported = 0;

        // normaliza keys
        if (!is_array($keys)) $keys = [];
        $keys = array_values(array_unique(array_filter(array_map(function ($k) {
            $k = is_string($k) ? $k : '';
            $k = trim($k);
            return $k !== '' ? $k : null;
        }, $keys))));

        // index rápido do que foi selecionado
        $want = array_fill_keys(array_map('strval', $keys), true);

        // se veio sem nenhum prefixo reconhecido, NUNCA importa tudo
        $has_prefixed = false;
        foreach ($want as $k => $_) {
            $k = (string)$k;
            if (strpos($k, 'tpl:') === 0 || strpos($k, 'pr:') === 0 || strpos($k, 'template:') === 0 || strpos($k, 'prompt:') === 0) {
                $has_prefixed = true;
                break;
            }
        }

        // compat: aceita também "template:" e "prompt:" (converte para tpl:/pr:)
        if ($has_prefixed) {
            $want2 = $want;
            foreach ($want as $k => $_) {
                $k = (string)$k;

                if (strpos($k, 'template:') === 0) {
                    $slug = sanitize_key(substr($k, 9));
                    if ($slug !== '') $want2['tpl:' . $slug] = true;
                    continue;
                }

                if (strpos($k, 'prompt:') === 0) {
                    $rest = trim(substr($k, 7));
                    if ($rest !== '') {
                        // espera prompt:<tpl_slug>:<stage>
                        $parts = explode(':', $rest, 3);
                        $tpl   = sanitize_key($parts[0] ?? '');
                        $stage = sanitize_key($parts[1] ?? '');
                        if ($tpl !== '' && $stage !== '') {
                            $want2['pr:' . $tpl . ':' . $stage] = true;
                        }
                    }
                    continue;
                }
            }
            $want = $want2;
        }

        // 1) templates selecionados
        foreach ($import_templates as $slug => $meta) {
            $slug = sanitize_key((string)$slug);
            if ($slug === '' || $slug === 'global') continue;

            $k = 'tpl:' . $slug;
            if (empty($want[$k])) continue;

            $exists = array_key_exists($slug, $current_templates);
            if ($exists && !$overwrite) {
                continue;
            }

            $label   = is_array($meta) && isset($meta['label']) ? sanitize_text_field((string)$meta['label']) : $slug;
            $enabled = is_array($meta) && array_key_exists('enabled', $meta) ? (int)!empty($meta['enabled']) : 1;

            if (in_array($slug, ['article', 'modelar_youtube', 'rss'], true)) {
                $clean_templates[$slug] = [
                    'label'      => $label ?: $slug,
                    'enabled'    => 1,
                    'builtin'    => 1,
                    'is_default' => 1,
                ];
            } else {
                $current_templates[$slug] = ['label' => $label ?: $slug, 'enabled' => $enabled, 'builtin' => 0, 'is_default' => 0];
            }

            $imported++;
        }

        // 2) prompts selecionados
        foreach ($import_prompts as $tpl_slug => $stages) {
            $tpl_slug = sanitize_key((string)$tpl_slug);
            if ($tpl_slug === '' || $tpl_slug === 'global') continue;
            if (!is_array($stages)) continue;

            foreach ($stages as $stage => $val) {
                $stage = sanitize_key((string)$stage);
                if (!in_array($stage, $allowed_stages, true)) continue;
                if (!is_string($val)) continue;

                $k = 'pr:' . $tpl_slug . ':' . $stage;
                if (empty($want[$k])) continue;

                if (empty($current_templates[$tpl_slug])) {
                    $current_templates[$tpl_slug] = [
                        'label'   => $tpl_slug,
                        'enabled' => 1,
                        'builtin' => in_array($tpl_slug, ['article', 'modelar_youtube', 'rss'], true) ? 1 : 0,
                    ];
                }

                $exists = isset($current_prompts[$tpl_slug]) && array_key_exists($stage, (array)$current_prompts[$tpl_slug]);
                if ($exists && !$overwrite) {
                    continue;
                }

                $current_prompts[$tpl_slug][$stage] = wp_kses_post($val);
                $imported++;
            }
        }

        update_option('pga_orion_templates', $current_templates, false);
        update_option(self::OPTION, $current_prompts, false);

        return [
            'imported'  => $imported,
            'overwrite' => $overwrite ? 1 : 0,
        ];
    }


    /* =============================
   * HELPERS: length/outline config
   * ============================= */
    public static function length_to_range(string $length): array
    {
        switch ($length) {
            case 'short':
                return [300, 500];
            case 'medium':
                return [600, 1000];
            case 'long':
                return [1200, 2200];
            case 'extra-long':
            case 'extra_long':
            case 'extra':
                return [2500, 5000];
            default:
                return [300, 500];
        }
    }

    public static function outline_config(string $length): array
    {
        switch ($length) {
            case 'short':
                return ['min_sections' => 1, 'max_sections' => 4];
            case 'medium':
                return ['min_sections' => 4, 'max_sections' => 8];
            case 'long':
                return ['min_sections' => 8, 'max_sections' => 15];
            case 'extra-long':
            case 'extra_long':
            case 'extra':
                return ['min_sections' => 15, 'max_sections' => 30];
            default:
                return ['min_sections' => 1, 'max_sections' => 4];
        }
    }

    public static function build_story_prompt_from_post(string $title, string $content): string
    {
        $tpl = self::get_prompt_for('story', 'story');

        $base = self::replace_vars($tpl, [
            'articleTitle' => $title,
            'content'  => $content,
        ]);

        $rules = "REGRAS IMPORTANTES PARA A HISTÓRIA:\n"
            . "- A história deve ser envolvente e cativante.\n"
            . "- Use uma linguagem simples e clara.\n"
            . "- Mantenha os parágrafos curtos (máx. 2 frases).\n"
            . "- Evite jargões técnicos ou termos complexos.\n"
            . "- Certifique-se de que a história tenha um começo, meio e fim claros.\n"
            . "- Inclua diálogos para tornar a história mais viva.\n"
            . "- Use descrições sensoriais para criar uma imagem vívida na mente do leitor.\n"
            . "- Mantenha o tom apropriado para o público-alvo.\n"
            . "- Revise a história para garantir que não haja erros gramaticais ou ortográficos.\n";

        $final = $base . "\n\n" . $rules;

        return $final;
    }

    public static function build_faq_prompt(array $args): string
    {
        $keyword = trim((string)($args['keyword'] ?? ''));
        $qty     = min(5, max(1, (int)($args['qty'] ?? 3)));
        $lang  = $args['lang'] ?? 'pt_BR';
        $context  = $args['context'] ?? '';

        $lang = match ($lang) {
            'pt_BR' => 'português do Brasil',
            'en_US' => 'inglês',
            'es_ES' => 'espanhol',
            default => 'português',
        };

        $c = '';
        if ($context !== '') {
            $c = "Use o conteudo abaixo como base para criar as perguntas e torna-las mais verdadeiras\n"
                . "-----Inicio---\n"
                . $context . "\n"
                . "-----fim---\n\n";
        }

        $p = "Gere exatamente {$qty} perguntas frequentes (FAQ) sobre \"{$keyword}\"\n."
            . "Regras obrigatórias:\n"
            . "- Escreva em {$lang}.\n"
            . "- Use perguntas reais que um usuário faria.\n"
            . "- Respostas objetivas, claras e diretas.\n"
            . "- Não use listas, markdown ou emojis.\n"
            . "- Não mencione IA, modelos ou processos internos.\n\n"

            . $c

            . "Formato de saída:\n"
            . "Retorne APENAS um objeto JSON válido no padrão Schema.org FAQPage,\n"
            . "com @context, @type e mainEntity.\n"
            . "Não retorne texto fora do JSON.";

        return $p;
    }

    public static function build_section_prompt(
        string $template,
        string $keyword,
        string $articleTitle,
        array  $section,
        string $length,
        string $lang,
        int    $sectionsCount,
        string $section_number,
        string $content = '',
        string $url = '',
        array $idea = [],
    ): string {
        $tpl = self::get_prompt_for($template, 'section');
        $heading = trim((string)($section['heading'] ?? ''));
        $level   = strtolower(trim((string)($section['level'] ?? 'h2')));
        if ($level !== 'h2' && $level !== 'h3') $level = 'h2';

        $sectionParagraph = trim((string)($section['paragraph'] ?? ''));

        // children detalhado (H3 sugeridos com paragraph)
        $childrenDetailed = '';
        if (!empty($section['children']) && is_array($section['children'])) {
            $list = [];
            $n = 1;
            foreach ($section['children'] as $c) {
                $h = trim((string)($c['heading'] ?? ''));
                $p = trim((string)($c['paragraph'] ?? ''));
                if ($h === '') continue;

                $line = "{$n}. {$h}";
                if ($p !== '') $line .= " — {$p}";

                $list[] = $line;
                $n++;
            }
            if ($list) $childrenDetailed = implode("\n", $list);
        }

        // bullets (da própria seção)
        $bullets = '';
        if (!empty($section['bullets']) && is_array($section['bullets'])) {

            $list = [];

            foreach ($section['bullets'] as $b) {

                if (!is_scalar($b)) {
                    continue;
                }

                $b = trim(wp_strip_all_tags((string)$b));

                if ($b !== '') {
                    $list[] = '- ' . $b;
                }
            }

            if ($list) {
                $bullets = implode("\n", $list);
            }
        }

        // children headings (H3 sugeridos)
        $children = '';
        if (!empty($section['children']) && is_array($section['children'])) {
            $list = [];
            foreach ($section['children'] as $c) {
                $h = trim((string)($c['heading'] ?? ''));
                if ($h !== '') $list[] = '- ' . $h;
            }
            if ($list) $children = implode("\n", $list);
        }

        // word goal (se vier do outline)
        $goalMin = 0;
        $goalMax = 0;
        if (!empty($section['word_goal']) && is_array($section['word_goal'])) {
            $goalMin = (int)($section['word_goal']['min'] ?? 0);
            $goalMax = (int)($section['word_goal']['max'] ?? 0);
        }

        [$minWords, $maxWords] = self::length_to_range($length);

        if ($goalMin <= 0 || $goalMax <= 0) {
            $per = max(90, (int) floor($maxWords / max(1, $sectionsCount)));
            $goalMin = (int) max(60, floor($per * 0.55));
            $goalMax = (int) max($goalMin + 30, floor($per * 0.75));
        }

        $url = trim((string)$url);

        $base = self::replace_vars($tpl, [
            'keyword'                   => $keyword,
            'articleTitle'              => $articleTitle,
            'lang'                      => $lang,
            'section_heading'           => $heading,
            'section_level'             => $level,
            'section_paragraph'         => $sectionParagraph,
            'section_children'          => $children,
            'section_children_detailed' => $childrenDetailed,
            'section_bullets'           => $bullets,
            'sections_count'            => (string)$sectionsCount,
            'section_number'            => (string)$section_number,
            'url'                       => $url,
            'content'                   => $content,
            'min_Words'                 => $minWords,
            'max_Words'                 => $maxWords,
            'site_url'                  => site_url(),
            'goalMin'                   => $goalMin,
            'goalMax'                   => $goalMax
        ]);

        $idx = max(1, (int)$section_number);
        $total = max(1, (int)$sectionsCount);
        $remaining = max(0, $total - $idx);

        $state = "CONTEXTO DA SEÇÃO:\n"
            . "Título do artigo: \"{$articleTitle}\"\n"
            . "Esta é a seção {$idx} de {$total} (restam {$remaining})\n"
            . "Data atual: " . SELF::date() . "\n"
            . "Título da sessão: \"{$heading}\"\n"
            . "PROIBIDO MARKDOWN, SEMPRE MANDAR EM HTML: SÓ É PERMITIDO ENVIAR TAGS HTML, COMO <a>, <strong>, <em>, <p>, <ul>, <ol>, <li>,<table>\n"
            . "A sessão deve ser gerada no idioma \"{$lang}\"\n\n";

        if ($keyword) {
            $state .= "Frase chave: \"{$keyword}\"\n";
            $state .= " quando for inserir a frase chave, ela também deve ser traduzida, mas claro, de maneira fluida no texto e somente se o idioma dela for diferente do idioma solicitado\"\n";
        }
        $state .= "REGRAS DE FORMATAÇÃO:\n"
            . "- Você está gerando APENAS a seção {$section_number} de um total de {$sectionsCount} seções\n"
            . "- Cada seção é gerada ISOLADAMENTE\n\n";

        $brief = "BRIEF DA SEÇÃO (siga fielmente — esta é sua fonte principal):\n"
            . "Heading ({$level}): {$heading}\n";

        if ($sectionParagraph !== '') {
            $brief .= "Parágrafo-guia do {$level}: {$sectionParagraph}\n"
                . "REGRA: Desenvolva com clareza e objetividade, mantendo ritmo jornalístico. Evite explicações excessivamente longas ou acadêmicas.\n\n";
        }

        if ($childrenDetailed !== '') {
            $brief .= "Subtítulos H3:\n{$childrenDetailed}\n"
                . "REGRA: Crie cada H3 e desenvolva seguindo o brief específico de cada um.\n\n"
                . "PROIBIDO MARKDOWN, SEMPRE MANDAR EM HTML\n";
        } else if ($children !== '') {
            $brief .= "Subtítulos H3 sugeridos:\n{$children}\n\n";
        }

        if ($bullets !== '') {
            $brief .= "Guias sugeridos:\n{$bullets}\n\n";
        }

        $ideaText = self::idea_brief_to_text($idea);
        if ($ideaText !== '') {
            $state .= "IDEIA VALIDADA:\n{$ideaText}\n\n";
        }

        if ($content)
            $content .= "O conteúdo a ser modelado é com base nesse:\n"
                . "----- INICIO ------\n"
                . $content . "\n"
                . "------ FIM -------\n\n";

        $tech = "REGRAS TÉCNICAS (não discuta, apenas cumpra):\n";
        if ($childrenDetailed !== '' || $children !== '') {

            $tech .= "- SE houver subtítulos H3 no brief, você DEVE criar TODOS eles\n"
                . "- A estrutura obrigatória é:\n"
                . "<{$level}>{$heading}</{$level}>\n"
                . "<p>introdução da seção</p>\n"
                . "<h3>subtítulo</h3>\n"
                . "<p>conteúdo</p>\n"
                . "- NÃO omita nenhum H3 fornecido\n";
        } else {
            $tech .= "- Esta seção NÃO possui subtítulos H3\n"
                . "- Escreva apenas parágrafos explicativos\n";
        }

        $tech .= "- Use a frase chave de forma NATURAL, no máximo 1 vez nesta seção\n";
        $tech .= "- NÃO repita a frase chave em todos os parágrafos\n";
        $tech .= "- Use variações semânticas quando possível\n";
        $tech .= "- Desenvolva cada subtítulo com pelo menos um parágrafo completo\n";
        $tech .= "- Cada H3 deve conter pelo menos 80 palavras\n"
            . "Evite capitalização excessiva, capitalize apenas, nome, primeira letra, siglas. Isso vale também para a frase chave de foco, só capitalize se for necessário. "
            . "ex. de títulos da seção:\n"
            . "ERRADO: \"Erros Que Todo Mundo Comete Ao Lavar O Cabelo\"\n"
            . "CERTO: \"Erros que todo mundo comete ao lavar o cabelo\"\n"
            . "ERRADO: \"Como É Feito o Trabalho na C.I.A.\"\n"
            . "CERTO: \"Como é feito o trabalho na C.I.A.\"\n";

        if ($template === 'modelar_youtube') {
            $tech .= "- NUNCA mencione: vídeo, canal, link, URL, ou qualquer referência à fonte original\n";
        }

        $ideaText = self::idea_brief_to_text($idea);
        if ($ideaText !== '') {
            $brief = "IDEIA VALIDADA:\n{$ideaText}\n\n" . $brief;
        }

        return $base . "\n\n" . $state . $brief . $tech . "\n" . $content . "PROIBIDO MARKDOWN, SEMPRE MANDAR EM HTML\n";
    }

    public static function build_title_prompt_modelar_youtube(
        array $video,
        string $keyword,
        int $min = 1,
        int $max = 3,
        string $lang = 'pt_BR'
    ): string {

        $tpl    = self::get_prompt_for('modelar_youtube', 'title');
        $lang = $lang ?: 'pt_BR';

        $videoTitle       = trim((string)($video['title'] ?? ''));
        $videoDescription = trim((string)($video['description'] ?? ''));

        // corta descrição (evita token gigante)
        if ($videoDescription !== '') {
            $videoDescription = wp_strip_all_tags($videoDescription);
            $videoDescription = html_entity_decode($videoDescription, ENT_QUOTES, 'UTF-8');
            $videoDescription = function_exists('mb_substr')
                ? mb_substr($videoDescription, 0, 700)
                : substr($videoDescription, 0, 700);
        }

        // 1) prompt base editável (limpo)
        $base = self::replace_vars($tpl, [
            'keyword' => $keyword,
            'lang'  => $lang,
        ]);

        // 2) contexto interno (backend) — obrigatório, invisível pro user
        $ctx  = "\n\n";
        $ctx .= "CONTEXTO INTERNO:\n";
        $ctx .= "- Gere apenas títulos ";
        $ctx .= "- Hoje é: " . SELF::date();
        $ctx .= "- Gere um título com base no original: ";

        if ($videoTitle !== '')   $ctx .= $videoTitle . "\n";

        $ctx .= "- Lembre-se de contextualizar com o que está na descrição: ";
        if ($videoDescription !== '') {
            $ctx .= "Descrição: \n";
            $ctx .= $videoDescription . "\n";
        }

        // 3) regras fixas + suffix JSON fixo
        $fixed =
            "\n\n"
            . "Quantidade de títulos a gerar: entre {$min} e {$max}.\n"
            . "O título deve ser gerada no idioma, pode traduzir incluse a KW: {$lang}\n\n";


        return $fixed . $base . $ctx . "\n\n" . self::title_json_suffix();
    }

    public static function build_keywords_prompt(
        string $template,
        string $command,
        string $lang,
        int $count,
        string $category,
        array $existing_list = []
    ): string {
        $tpl = self::get_prompt_for($template, 'keywords');

        $command  = trim((string)$command);
        $lang   = $lang ?: 'pt_BR';
        $category = trim((string)$category);
        $count    = max(1, min(100, (int)$count));

        $base = self::replace_vars($tpl, [
            'command'  => $command,
            'lang'   => $lang,
            'category' => $category
        ]);

        $ban = '';
        if (!empty($existing_list)) {
            $existing_list = array_slice($existing_list, 0, 200);
            $ban = "\n\nPROIBIDO repetir qualquer uma destas keywords (nem variações mínimas):\n- "
                . implode("\n- ", array_map('trim', $existing_list))
                . "\n";
        }

        $suffix =
            "Responda APENAS em JSON UTF-8 válido, sem markdown.\n"
            . "- Hoje é: " . self::date() . "\n"
            . "Regras técnicas (não discuta, apenas cumpra):\n"
            . "- Gere {$count} keywords NOVAS e DIFERENTES.\n"
            . "- Gere em {$lang}.\n"
            . "- Categoria: {$category}.\n"
            . "- Use o comando como direção (caso tenha): \"{$command}\".\n"
            . "- O JSON deve ser VÁLIDO e em UMA LINHA.\n"
            . "- No campo \"content\", use UMA keyword por linha.\n"
            . "- IMPORTANTE: como o JSON é em uma linha, separe as linhas usando \\n (barra invertida + n).\n"
            . "- NÃO use bullets, NÃO use numeração, NÃO use vírgulas como separador.\n"
            . "- NÃO inclua barras \\ (exceto nos \\n), pipes | ou ponto-e-vírgula ; como separadores.\n"
            . "- Não adicione explicações.\n\n"
            . "Exemplo válido:\n"
            . "Responda SOMENTE em JSON válido no formato: "
            . "{\n"
            . " \"content\": [\n"
            . " \"item 1\",\n"
            . " \"item 2\",\n"
            . " \"item 3\"\n"
            . "]\n"
            . "}";



        return $base . $ban . "\n\n" . $suffix;
    }

    /* =============================
   * DEFAULTS (CORE)
   * ============================= */

    private static function default_keywords_prompt(): string
    {
        return
            "Gere frases-chave curtas para um artigo.\n\n"

            . "REGRAS:\n"
            . "- Priorizar short-tail keywords.\n"
            . "- Cada frase deve ter entre 1 e 3 palavras.\n"
            . "- Basear-se na categoria e no briefing abaixo (se tiver).\n"
            . "- Usar termos relevantes para busca.\n"
            . "- Usar termos com alta taxa de pesquisa.\n"
            . "- Usar termos com potencial de ranqueamento.\n"
            . "- Usar termos não obvios.\n"
            . "- Não usar pontuação.\n"
            . "- Não repetir palavras desnecessariamente.\n\n";
    }

    private static function default_title_prompt(): string
    {
        return
            "Você é um jornalista sênior especializado em criar títulos para Google Discover.\n\n"

            . "CARACTERÍSTICAS DE TÍTULOS EFICAZES:\n\n"

            . "ESPECIFICIDADE:\n"
            . "- Inclua números, nomes ou detalhes específicos\n"
            . "- Exemplos: '15 melhores empresas', 'Este homem de 32 anos ganhava US$ 17/hora'\n\n"

            . "EMOÇÃO E CURIOSIDADE:\n"
            . "- Evoque resposta emocional ou desperte curiosidade\n"
            . "- Exemplos: 'As cidades mais tristes do país', 'Disney Ride recebe revisão surpreendente'\n\n"

            . "RELEVÂNCIA E OPORTUNIDADE:\n"
            . "- Foque em tópicos atuais, tendências ou eventos recentes\n"
            . "- Crie senso de urgência e novidade\n"
            . "- Exemplos: 'trabalho híbrido', 'ChatGPT', desenvolvimentos recentes\n\n"

            . "AUTORIDADE:\n"
            . "- Cite especialistas ou fontes confiáveis quando relevante\n"
            . "- Exemplos: 'O que especialistas dizem', 'Pessoas emocionalmente inteligentes usam...'\n\n"

            . "PROBLEMA E SOLUÇÃO:\n"
            . "- Destaque um problema e forneça solução\n"
            . "- Exemplo: 'Fitbit responde a fãs furiosos com cinco correções muito necessárias'\n\n"

            . "ASPECTO NOTÍCIA:\n"
            . "- Toque em eventos atuais ou desenvolvimentos recentes\n"
            . "- Use senso de urgência: mudanças, surpresas, impactos\n"
            . "- Personalização: considere interesse atual do público\n\n"

            . "REGRAS:\n"
            . "- Título curto e impactante\n"
            . "- Clareza sem sacrificar interesse\n"
            . "- Vocabulário eloquente mas acessível\n"
            . "- Tom jornalístico profissional\n"
            . "- Capitalização: só primeira palavra + nomes próprios\n"
            . "Ex.: kw \"10 melhores filmes de ação de 2026\"\n"
            . "ERRADO: \"Os 10 Melhores Filmes de Ação de 2026\"\n"
            . "CORRETO: \"7 filmes de ação brutais que chegaram em 2026\"\n"
            . "CORRETO: \"Filmes de ação sobre a C.I.A com Jason Momoa\"";
    }

    private static function default_title_rss_prompt(): string
    {
        return
            "Crie um título otimizado e original baseado no RSS fornecido.\n\n"
            . "REGRAS:\n\n"
            . "ORIGINALIDADE (OBRIGATÓRIA):\n"
            . "- NUNCA copie o título original\n"
            . "- Reescreva completamente com suas próprias palavras\n"
            . "- Mude a estrutura e ângulo\n"
            . "- Não abrevie nomes simples de empresas, como o nome da Warner Bros. Discovery\n"
            . "- Mantenha o tema/assunto, mas com abordagem diferente\n\n"

            . "OTIMIZAÇÃO:\n"
            . "- Máximo 50-70 caracteres\n"
            . "- Capitalização: só primeira palavra + nomes próprios\n"
            . "- Tom jornalístico e direto\n\n"

            . "EXEMPLO:\n"
            . "KW: \"10 melhores filmes de ação de 2026\"\n"
            . "❌ ERRADO: \"Os 10 melhores filmes de ação de 2026\"\n"
            . "✅ CORRETO: \"7 filmes de ação brutais que chegaram em 2026\"\n"
            . "✅ CORRETO: \"Filmes de ação de 2026 que ninguém esperava\"\n\n";
    }

    private static function default_title_modelar_youtube_prompt(): string
    {
        return "Você é um redator sênior especializado em SEO e Google Discover.\n\n"
            . "Sua tarefa é gerar títulos fortes e naturais para um artigo que será inspirado por um tema.\n\n"
            . "Regras obrigatórias:\n"
            . "- NÃO mencione fonte, vídeo, canal ou URL no título.\n"
            . "- Não use aspas.\n"
            . "- Evite clickbait mentiroso; seja curioso e específico.\n"
            . "- Foque em clareza + curiosidade + benefício.\n\n"
            . "Gere algo diferente do que já gerou.\n"
            . "Gere variações diferentes (ângulos diferentes: guia, lista, erros, passo a passo, explicação simples, comparativo, etc.).\n";
    }

    private static function default_outline_modelar_youtube_prompt(): string
    {
        return "OBJETIVO E-E-A-T:\n"
            . "- Transformar o conhecimento empírico do vídeo em tópicos que demonstrem expertise técnica.\n"
            . "- Eliminar vícios de linguagem de vídeo ('deixe o like', 'se inscreva') e focar em entregar a solução prometida.\n\n"
            . "DIRETRIZES DO OUTLINE:\n"
            . "- H2s DE VALOR: Os títulos das seções devem responder diretamente às dores do usuário ou curiosidade do Google Discover.\n"
            . "- SEÇÃO DE 'INSIGHTS CHAVE': Crie um H2 inicial que resuma os pontos fundamentais discutidos no vídeo para oferecer valor imediato.\n"
            . "- PROFUNDIDADE (H3): Alguns H2 devem ter subtópicos (children) que detalham o 'como fazer' ou 'por que isso funciona'.\n"
            . "- LISTAS TÉCNICAS: Use bullets para organizar dados, passos ou requisitos que no vídeo estão dispersos.\n\n"
            . "REGRAS RÍGIDAS:\n"
            . "- Deve ser evitado capitalização de título, só capitalize quando realmente for necessário, naqueles momentos especificos.\n"
            . "- PROIBIDO citar que o conteúdo veio de um vídeo, canal, link ou YouTube.\n"
            . "- NÃO utilize termos de encerramento como 'Conclusão'. Termine com um tópico de 'Aplicação Prática' ou 'Próximos Passos'.\n"
            . "- O texto deve parecer um artigo nativo escrito por um especialista humano, não uma transcrição.";
    }

    private static function default_outline_prompt(): string
    {
        return
            "Você é um editor experiente responsável por estruturar artigos de alto valor informativo.\n"
            . "Sua tarefa é criar o ESBOÇO editorial de um artigo baseado na frase-chave fornecida.\n\n"

            . "OBJETIVO:\n"
            . "Criar uma estrutura que entregue informação útil, diferenciada e confiável.\n"
            . "O outline deve guiar um artigo que realmente resolva dúvidas do leitor.\n\n"

            . "PRINCÍPIOS EDITORIAIS:\n"
            . "- Priorizar utilidade real para o leitor.\n"
            . "- Demonstrar experiência prática quando possível.\n"
            . "- Incluir contexto, implicações e explicações claras.\n"
            . "- Evitar estruturas óbvias ou genéricas.\n"
            . "- Priorizar informações que ampliem entendimento do tema.\n\n"

            . "ESTRUTURAS GENÉRICAS:\n"
            . "- Evite títulos genéricos ou superficiais.\n"
            . "- Expressões como 'o que é', 'benefícios', 'vantagens', 'conclusão' só devem ser usadas quando forem realmente necessárias para explicar o tema.\n"
            . "- Quando utilizar essas expressões, torne o título mais específico e informativo.\n"
            . "- Prefira títulos que revelem um insight, contexto ou impacto real.\n\n"

            . "ESTRUTURA:\n"
            . "- Criar entre {{min_sections}} e {{max_sections}} seções H2.\n"
            . "- Esse detalhe é importante, pois o conteúdo final vai ter entre {{max_words}} e no max {{min_words}}.\n"
            . "- Cada seção deve ter um papel claro na progressão do artigo.\n"
            . "- A sequência deve aprofundar o tema gradualmente.\n\n"

            . "EXEMPLOS:\n"
            . "Evite títulos genéricos como:\n"
            . "- O que é a fusão\n"
            . "- Benefícios da fusão\n\n"

            . "EXEMPLOS DE BOAS DIREÇÕES DE SEÇÃO:\n"
            . "- erros comuns que mudam o resultado\n"
            . "- o detalhe que pouca gente percebe\n"
            . "- como especialistas avaliam o problema\n"
            . "- o impacto real dessa decisão\n"
            . "- o que muda a partir de agora\n"
            . "- O que realmente muda com a fusão Paramount e Warner Bros\n"
            . "- Por que essa fusão preocupa reguladores\n"
            . "- O impacto da fusão no mercado de streaming\n\n"
            . "- pontos ignorados pela maioria das análises\n\n"

            . "PROGRESSÃO IDEAL:\n"
            . "1. contexto ou fato central\n"
            . "2. explicação ou bastidores\n"
            . "3. análise ou impacto prático\n"
            . "4. implicações ou próximos desdobramentos"

            . "- Capitalização: só primeira palavra + nomes próprios\n"
            . "h2/h3 ERRADO: \"Como a Tecnologia Tem Ajudado\"\n"
            . "h2/h3 CORRETO: \"Como a tecnologia tem ajudado\"\n"
            . "h2/h3 ERRADO: \"Como Jason Momoa Faz o Trabalho da C.I.A.\"\n\n"
            . "h2/h3 CORRETO: \"Como Jason Momoa faz o trabalho da C.I.A.\"\n\n"

            . "FUNIL DE BUSCA:\n"
            . "- Identifique o nível de funil da frase-chave (informacional, comparativo ou decisório).\n"
            . "- Ajuste o conteúdo para esse nível de intenção.\n"
            . "- Conteúdos informacionais devem explicar e contextualizar.\n"
            . "- Conteúdos comparativos devem destacar diferenças.\n"
            . "- Conteúdos decisórios devem trazer critérios e implicações.";
    }

    private static function default_outline_rss_prompt(): string
    {
        return
            "Você é um jornalista profissional especializado em estruturar notícias factuais de alto padrão.\n"
            . "Sua tarefa é criar um ESBOÇO jornalístico técnico e objetivo com base na fonte fornecida.\n\n"

            . "ATENÇÃO ABSOLUTA:\n"
            . "- Não copie frases nem a estrutura da fonte.\n"
            . "- Reorganize completamente a ordem dos fatos.\n"
            . "- Não inclua opinião, análise, emoção ou linguagem promocional.\n"
            . "- Não use frases genéricas.\n"
            . "- Não invente informações ausentes.\n"
            . "- Cada bullet deve conter APENAS 1 fato verificável.\n"
            . "- Nunca repetir informação com palavras diferentes.\n"
            . "- Sempre que mencionar siglas, na primeira vez que for falar dela, deve ser colocado entre parenteses o significado, como: \"FCC (Federal Communications Commission). Além disso, instrua no bullet a colocar o parenteses\".\n"
            . "- Fale sobre o contexto da notícia, em que ela impacta.\n\n"

            . "PRIORIDADE JORNALÍSTICA:\n"
            . "- A PRIMEIRA seção deve conter obrigatoriamente o fato central da notícia.\n"
            . "- Se houver números relevantes, datas, valores ou rankings, eles devem aparecer na primeira seção.\n"
            . "- Priorize dados concretos antes de contexto secundário.\n"
            . "- Se houver números, valores, datas ou quantidades, eles devem aparecer antes de qualquer contexto narrativo.\n"
            . "- Evite bullets que contenham apenas contexto ou descrição sem dado concreto.\n"
            . "- Sempre priorizar fatos confirmados por fonte oficial ou dados divulgados publicamente.\n"
            . "- Evite repetir o mesmo nome de pessoa ou empresa em bullets consecutivos se não houver novo fato relevante.\n"
            . "- Se houver valor financeiro, ele deve ser incluído obrigatoriamente.\n"
            . "- Não concentrar mais de 3 bullets sobre o mesmo tipo de informação.\n"
            . "- Se o fato não altera a compreensão central da notícia, não incluir.\n"
            . "- Se um bullet não contiver data, número, local, entidade ou ação verificável, não gerar.\n"
            . "- Contexto histórico só deve aparecer se estiver presente na fonte.\n"
            . "\n"
            . "PROIBIDO:\n"
            . "- Interpretar intenções ou emoções de empresas ou pessoas.\n"
            . "- Usar linguagem institucional ou promocional.\n"
            . "- Criar contexto filosófico ou analítico.\n"
            . "- Criar conclusões ou interpretações.\n"
            . "- Cada fato deve ser passado apenas uma vez durante todo o esboço; não inventar fatos ou valores ficticios se houver a necessidade de falar sobre a mesma informação em outras sessões.\n"
            . "- Cada sessão deve ser independente e autosuficiente com novos fatos.\n"
            . "- Escrever o bullet se não houver dado factual.\n"
            . "\n"
            . "EXPRESSÕES PROIBIDAS:\n"
            . "- demonstra compromisso\n"
            . "- reforça compromisso\n"
            . "- evidencia estratégia\n"
            . "- mostra preocupação\n"
            . "- destaca a importância\n"
            . "- marca um passo importante\n"
            . "- reflete a estratégia\n"
            . "- reforça dedicação\n"
            . "- chama atenção\n"
            . "- levanta questões\n"
            . "- pode impactar\n"
            . "\n"
            . "ESTRUTURA OBRIGATÓRIA:\n"
            . "- Gerar no máximo 2 seções H2.\n"
            . "- Não gerar H3 ou children, exceto se o título mencionar lista, ranking ou quantidade.\n"
            . "- Máximo absoluto de 20 bullets por seção.\n"
            . "- Cada bullet deve ter no máximo 120 caracteres.\n"
            . "- Cada paragraph deve ter no máximo 200 caracteres.\n"
            . "- Apenas texto corrido.\n"
            . "- Não usar aspas, markdown ou formatação.\n\n"

            . "OS BULLETS DEVEM PRIORIZAR:\n"
            . "- Datas completas.\n"
            . "- Local exato do fato.\n"
            . "- Nome completo de pessoas ou empresas.\n"
            . "- Valores numéricos exatos.\n"
            . "- Percentuais.\n"
            . "- Premiações.\n"
            . "- Resultados oficiais.\n"
            . "- Prazos.\n"
            . "- Dados verificáveis.\n\n"

            . "EXEMPLOS DE BULLET CORRETO:\n"
            . "- No dia [x] de [xxxx] de [xxxx] a [empresa x] confirmou a/a [acontecimento]\n"
            . "- O [item] recebeu [avaliação] no [ranking/site]\n"
            . "- A prova será aplicada em [cidade] [estado]\n\n"

            . "EXEMPLOS PROIBIDOS:\n"
            . "- A decisão pode impactar o setor\n"
            . "- A produção promete emocionar\n"
            . "- A [empresa x] reforça seu compromisso\n"
            . "- O [item] é muito aguardado";
    }

    public static function build_excerpt_prompt(string $title, string $content, string $template, string $lang): string
    {
        $tpl = self::get_prompt_for($template, 'excerpt');
        $base = self::replace_vars($tpl, [
            'articleTitle' => $title,
            'content' => $content,
        ]);

        $base .= "Título: {$title}\n\n"
            . "Conteúdo:\n{$content}"
            . "Gere no idioma do conteúdo, mas pra ser mais preciso, gere em: \"{$lang}\" ";

        return $base;
    }

    private static function default_excerpt_prompt(): string
    {
        return
            "Escreva um subtítulo jornalístico para a notícia abaixo.\n"
            . "Regras:\n"
            . "- 1 frase apenas\n"
            . "- entre 10 e 18 palavras\n"
            . "- não repetir o título\n"
            . "- mencionar o fato principal\n"
            . "- linguagem factual\n\n";
    }

    private static function default_section_prompt(): string
    {
        return
            "Você é um jornalista especializado em produzir conteúdo confiável e informativo.\n"
            . "Sua tarefa é escrever o conteúdo completo de uma seção de artigo com base no tópico informado.\n\n"

            . "OBJETIVO:\n"
            . "Criar um conteúdo claro, escaneável e informativo, otimizado para Google Discover.\n"
            . "Cria a sessão entre {{goalMin}} e {{goalMax}} palavras.\n\n"

            . "REGRAS EDITORIAIS:\n"
            . "- Texto factual, informativo e direto.\n"
            . "- Demonstrar autoridade e contexto quando possível.\n"
            . "- Priorizar informações úteis para o leitor.\n"
            . "- Evitar frases vagas ou genéricas.\n"
            . "- Evitar repetições.\n"
            . "- Obrigatório que ao menos 30% do conteúdo tenha palavras de transição, como: mas, além disso, no entanto, portanto, por outro lado, enquanto isso, por exemplo, dessa forma, consequentemente, ainda assim, por fim, da mesma forma, assim sendo, de modo geral.\n"
            . "- OBRIGATÓRIO: Crie um conteúdo focado em GEO, sempre com microexemplos reais.\n"
            . "- Não usar linguagem promocional.\n\n"

            . "INTRODUÇÃO (LEAD):\n"
            . "- O primeiro parágrafo deve funcionar como o lead do artigo.\n"
            . "- Apresente imediatamente o fato principal ou a informação mais relevante.\n"
            . "- Inclua contexto suficiente para entender o tema e por que ele importa.\n"
            . "- O lead deve ter entre 40 e 60 palavras.\n"
            . "- Evite frases genéricas ou construções como 'neste artigo vamos explicar'.\n"
            . "- Sempre que possível, mencionar entidades relevantes no lead (empresas, instituições, pessoas).\n"
            . "- O parágrafo deve gerar interesse informativo e preparar o leitor para os tópicos seguintes.\n\n"

            . "ESTRUTURA DO TEXTO:\n"
            . "- Usar parágrafos curtos e escaneáveis.\n"
            . "- Cada parágrafo deve ter entre 2-4 linhas visuais.\n"
            . "- Cada parágrafo deve ter no máximo 3 frases que respondam algo para serem paragrafos escaneáveis para GEO.\n"
            . "- O primeiro parágrafo deve explicar rapidamente o ponto central da seção.\n"
            . "- Tamanho da sessão: entre {{goalMin}} e {{goalMax}} palavras.\n"
            . "- Se necessário, incluir bullet points para organizar informações.\n\n"

            . "FORMATAÇÃO HTML:\n"
            . "- Usar apenas HTML simples.\n"
            . "- Parágrafos devem usar a tag <p>.\n"
            . "- Listas devem usar <ul> e <li>.\n"
            . "- Não usar markdown.\n\n"

            . "LINKS E REFERÊNCIAS:\n"
            . "- Quando mencionar empresas, instituições ou organizações relevantes, incluir um link externo confiável.\n"
            . "- Usar o formato <a href=\"URL\">nome</a>.\n"
            . "- Evitar excesso de links.\n\n"

            . "EEAT:\n"
            . "- Explicar contexto, implicações ou consequências quando relevante.\n"
            . "- Priorizar informações verificáveis.\n"
            . "- Destacar fatos, dados ou declarações relevantes quando disponíveis.\n\n"

            . "FUNIL DE BUSCA:\n"
            . "- Identifique o nível de funil da frase-chave (informacional, comparativo ou decisório).\n"
            . "- Ajuste o conteúdo para esse nível de intenção.\n"
            . "- Conteúdos informacionais devem explicar e contextualizar.\n"
            . "- Conteúdos comparativos devem destacar diferenças.\n"
            . "- Conteúdos decisórios devem trazer critérios e implicações.\n\n"

            . "Responda apenas com o HTML da seção.";
    }

    private static function default_outline_ryan_prompt(): string
    {
        return "Crie um esboço focado em SEO, que fale com profundidade sobre o assunto, que vá além do que as pessoas buscam e, ao mesmo tempo, traga itens comuns aos usuários topo de funil.\n\n"

            . "TEMA/título: {{articleTitle}}\n"
            . "PALAVRA-CHAVE: {{keyword}}\n"
            . "IDIOMA: {{lang}}\n\n"

            . "REGRA DE H3:\n"
            . "- Use H3 APENAS quando houver necessidade de aprofundamento\n"
            . "- NUNCA crie estrutura 'H2 → H3 → H2'\n"
            . "- Estrutura mínima obrigatória: 'H2 → H3 → H3 → H2' (mínimo 2 H3)\n"
            . "- Só use H3 se realmente existir conteúdo para MAIS DE UM H3\n\n"

            . "BLOCOS SEMÂNTICOS OBRIGATÓRIOS (mínimo 6 H2):\n"
            . "1. Definição\n"
            . "2. Diagnóstico\n"
            . "3. Métodos/soluções\n"
            . "4. Comparações\n"
            . "5. Custos/frequência (OBRIGATÓRIO: intruir sobre a inserção de uma tabela ao final desta seção (cuiddo com o título desse item h3, seria melhor algo no sentido de \"Tabela comparativa\"))\n"
            . "6. Erros/cuidados\n\n"

            . "REGRA PARA TÍTULOS COM NÚMEROS (Não discuta, apenas execute, essa regra é a mais importante e obrigatória):\n"
            . "Se o título mencionar quantidade (ex: '5 erros', '7 tendências'), agrupe em 1 (UM) H2 com itens H3, a quantidade de h3 é a quantidade de itens mencionados no título:\n"
            . "Exemplo para título h2 \"5 erros que todo mundo comete ao lavar o cabelo\":\n"
            . "H2: 5 erros cometidos ao lavar o cabelo (obrigatório que os h3 tenham a quantidade mencionada no título)\n"
            . "  H3: Erro 1 - Água quente\n"
            . "  H3: Erro 2 - ...\n"
            . "  H3: Erro 3 - ...\n"
            . "  H3: Erro 4 - ...\n"
            . "  H3: Erro 5 - ...\n"
            . "ou seja, 1 H2 com 5 H3s, que a quantidade de h3 é a quantidade de itens mencionados no título (5)\n\n"

            . "ESTRUTURA OBRIGATÓRIA:\n"
            . "1. H2: Introdução ao tema\n"
            . "   - APENAS parágrafos\n"
            . "   - Mínimo 2 parágrafos\n"
            . "   - Passe um brienfing detalhado com base nas possiveis maiores necessidades deste lead, algo emocional e ao mesmo tempo jornalistico\n"
            . "   - NUNCA use H3\n\n"

            . "2-7. H2s principais (mínimo 6 blocos semânticos):\n"
            . "   - Abordem: Definição, Diagnóstico, Métodos, Comparações, Custos/frequência, Erros\n"
            . "   - Use H3 quando necessário (mínimo 2 H3 por H2)\n"
            . "   - Na seção 'Custos/frequência': OBRIGATÓRIO pedir tabela no briefing\n\n"

            . "8. H2 final: Conclusão\n"
            . "   - APENAS parágrafos\n"
            . "   - NUNCA use H3\n\n"

            . "Você é o mais importante para o contexto de tudo, "
            . "em cada bloco, você precisa enaltecer um dos elementos do E-E-A-T (expertise (conhecimento técnico), experience (vivência real), autoridade e confiabilidade).\n"
            . "Então supondo que na introdução tenha colocado a expertise, em outro você coloca a confiabilidade e em outro a autoridade e assim sucessivamente\n\n"

            . "TÍTULOS DAS SEÇÕES:\n"
            . "- Títulos criativos e profundos, FORA DO PADRÃO\n"
            . "- NUNCA use títulos genéricos: 'Conclusão', 'Erros', 'Benefícios'\n"
            . "- Títulos curtos (não exagere no tamanho)\n"
            . "- Seja especifico ao ponto de ser memorável e instigante\n\n"

            . "BRIEFING:\n"
            . "- Tom JORNALÍSTICO para boa compreensão\n"
            . "- Estrutura GRADATIVA: informações NÃO devem se repetir entre seções\n"
            . "- Exemplo: se falou 'orçamento' em uma seção, NÃO fale 'financiamento' em outra\n"
            . "- EXCEÇÃO: tabela solicitada (informações podem se repetir APENAS para montar tabela)\n"
            . "- Na seção que terá tabela: especifique TODOS os dados nos bullets e peça explicitamente no briefing a criação da tabela\n\n"

            . "REGRAS FINAIS:\n"
            . "- Números sempre em dígitos (1, 5, 7) NUNCA por extenso ('um', 'cinco')\n"
            . "- Traduzir keyword se idioma for diferente\n"
            . "- Capitalização: só primeira palavra + nomes próprios\n"
            . "- Cada brief AUTO-SUFICIENTE\n"
            . "- Mínimo 6 blocos semânticos (pode adicionar mais se necessário)\n\n"

            . "OPCIONAL (se relevante ao tema):\n"
            . "- Bloco semântico: 'Como saber se é para você/se você precisa'\n";
    }

    private static function default_section_ryan_prompt(): string
    {
        return "Você é um jornalista especializado em produzir conteúdo confiável e acima de tudo: útil para os usuários.\n"
            . "Sua tarefa é escrever o conteúdo completo de uma seção de artigo rico, utilizavel no dia a dia e com base no tópico informado.\n\n"

            . "OBJETIVO:\n"
            . "Criar conteúdo claro, escaneável e informativo, otimizado para Google Discover, SEO, GEO e principalmente E-E-A-T, ou seja, conteúdo que demonstre experiência, autoridade e confiabilidade.\n"
            . "Tamanho da seção: entre {{goalMin}} e {{goalMax}} palavras.\n"
            . "NUNCA escreva menos que {{goalMin}} palavras.\n\n"

            . "REGRAS EDITORIAIS:\n"
            . "- Texto factual, informativo e direto\n"
            . "- Demonstre autoridade e contexto quando possível\n"
            . "- Priorize informações úteis para o leitor\n"
            . "- Evite frases vagas ou genéricas\n"
            . "- Evite repetições\n"
            . "- Use conectores naturais entre ideias (palavras de transição) em ao menos 30% do conteúdo\n"
            . "- NÃO invente estudos, especialistas, clínicas ou relatos locais\n"
            . "- Caso não haja fonte confiável, use exemplos gerais\n"
            . "- NÃO use linguagem promocional ou marketeira\n\n"

            . "INTRODUÇÃO (LEAD):\n"
            . "- Primeiro parágrafo funciona como lead da seção\n"
            . "- Introduza claramente o tópico da seção\n"
            . "- Apresente imediatamente o fato principal ou informação mais relevante\n"
            . "- Inclua contexto suficiente: entenda o tema e por que importa\n"
            . "- Lead deve ter entre 40 e 60 palavras\n"
            . "- Evite frases genéricas como 'neste artigo vamos explicar'\n"
            . "- Crie um lead emocional e jornalistico ao mesmo tempo, seja firme e informativo ao ponto de ser relevante\n"
            . "- Sempre que possível, mencione entidades relevantes (empresas, instituições, pessoas)\n"
            . "- Gere interesse informativo e prepare para os tópicos seguintes\n\n"

            . "ESTRUTURA DO TEXTO:\n"
            . "- Use parágrafos curtos e escaneáveis\n"
            . "- Cada parágrafo: 2-4 linhas visuais que respondam algo (GEO)\n"
            . "- Primeiro parágrafo explica rapidamente o ponto central da seção\n"
            . "- Se necessário, inclua bullet points para organizar informações\n"
            . "- Não se esqueça de inserir a tabela quando mencionada, obrigatório que seja literalmente uma tabela, aquela com <table>, <tr>, <td>, <th>, provavelmente vai ser mencionado na parte dos custos.\n\n"

            . "FORMATAÇÃO HTML:\n"
            . "- Use apenas HTML simples\n"
            . "- Parágrafos: <p>\n"
            . "- Listas: <ul> e <li>\n"
            . "- Obrigatório criar tabela quando mencionado sobre tabela: <table>, <tr>, <td>, <th>\n"
            . "- Obrigatório inserir ao menos 2-3 negritos por paragrafo em alguma palavra ou frase impactante e importante, não passe de 3 negritos por paragrafo (OBRIGATÓRIO: sem mardown, somente html).\n"
            . "- NÃO use Markdown\n\n"

            . "LINKS E REFERÊNCIAS:\n"
            . "- Ao mencionar empresas, instituições ou organizações relevantes, inclua link externo confiável\n"
            . "- Formato: <a href=\"URL\" target=\"_blank\" rel=\"noopener\">nome</a>\n\n"

            . "E-E-A-T:\n"
            . "- Crie um conteúdo que demonstre expertise com conhecimento tecnico, experiência com vivência real, autoridade com resultados e dados e, confiabilidade com dados verificáveis.\n\n"

            . "FUNIL DE BUSCA:\n"
            . "- Identifique nível de funil da frase-chave (informacional, comparativo ou decisório)\n"
            . "- Ajuste conteúdo para esse nível de intenção:\n"
            . "  • Informacional: explicar e contextualizar\n"
            . "  • Comparativo: destacar diferenças\n"
            . "  • Decisório: trazer critérios e implicações\n\n"

            . "REGRAS FINAIS:\n"
            . "- Respeite limite de palavras: {{goalMin}} a {{goalMax}}\n"
            . "- Tom jornalístico profissional\n"
            . "- Conteúdo útil e confiável\n";
    }

    private static function default_section_rss_prompt(): string
    {
        return
            "Você é um jornalista responsável por escrever uma seção de uma notícia baseada nas informações fornecidas.\n"
            . "\n"
            . "OBJETIVO:\n"
            . "Desenvolver o texto completo da seção mantendo os fatos, mas com redação totalmente original.\n"
            . "Nunca copiar frases ou estrutura do conteúdo base.\n"
            . "Obrigatório que ao menos 30% do conteúdo tenha palavras de transição, como: mas, além disso, no entanto, portanto, por outro lado, enquanto isso, por exemplo, dessa forma, consequentemente, ainda assim, por fim, da mesma forma, assim sendo, de modo geral.\n"
            . "\n"
            . "REGRAS:\n"
            . "- Reescrever completamente o conteúdo.\n"
            . "- Manter apenas fatos verificáveis.\n"
            . "- Linguagem jornalística clara e objetiva.\n"
            . "- Parágrafos curtos com no máximo 3 frases, e 2-3 linhas visuais (nunca passar de 3 linhas) para ser escaneável e focar em GEO também.\n"
            . "- Frases diretas e naturais.\n"
            . "- Não usar listas.\n"
            . "- Não usar markdown.\n"
            . "- Não usar emojis.\n"
            . "- Não inventar informações.\n"
            . "- Não usar opinião ou análise.\n"
            . "- Não usar linguagem promocional ou institucional.\n"
            . "- Use tag html para esses links, ou seja, tag <a href>, nunca user formato MD.\n"
            . "- Se atente a questões ortgraficas, escreva as palavras sem erros.\n"
            . "- Se esta for a seção 1 de {{sections_count}}, Sempre que mencionar siglas, deve ser colocado entre parenteses o significado, como: \"Federal Communications Commission (FCC)\".\n"
            . "- PRECISÃO DE ENTIDADES: Em transações financeiras, verifique quem está aportando o capital e quem está sendo adquirido. Não inverta os papéis das empresas.\n"
            . "- FIDELIDADE AOS NOMES: Inclua nomes de CEOs, políticos e autoridades mencionadas como fontes das informações, vinculando-os diretamente às suas falas ou ações.\n"
            . "- DETALHAMENTO DE VALORES: Ao mencionar cifras bilionárias, especifique a origem ou o destino do montante (ex: investimento, valor da compra, aporte de dívida).\n"
            . "\n"
            . "ESTRUTURA:\n"
            . "- Cada parágrafo deve começar com um fato relevante.\n"
            . "- Cada parágrafo deve conter informação concreta.\n"
            . "- Se não houver fato verificável, não escrever o parágrafo.\n"
            . "- Priorizar números, datas, locais e entidades quando existirem.\n"
            . "- Fale sobre o contexto da notícia, em que ela impacta.\n"
            . "- Tamanho da sessão: entre {{goalMin}} e {{goalMax}} palavras.\n"
            . "- Identifique com precisão o sujeito (comprador) e o objeto (comprado). Em fusões, mantenha a distinção entre a entidade adquirente e a adquirida conforme o texto base.\n"
            . "\n"
            . "LEAD JORNALÍSTICO OBRIGATÓRIO:\n"
            . "- O primeiro parágrafo deve funcionar como lead da notícia, então deve ter no máximo 80 palavras.\n"
            . "- O lead deve apresentar imediatamente o fato principal da notícia.\n"
            . "- O lead deve incluir pelo menos dois dos seguintes elementos: data, valor numérico, empresa ou pessoa envolvida, local ou impacto do fato.\n"
            . "- O lead deve ter entre 30 e 60 palavras.\n"
            . "- O lead deve ser direto, factual e informativo.\n"
            . "- Não iniciar com contexto histórico.\n"
            . "- Não iniciar com explicações gerais.\n"
            . "- Não iniciar com frases genéricas.\n"
            . "\n"
            . "EXEMPLOS DE LEAD CORRETO:\n"
            . "- A fusão de 111 bilhões de dólares entre Paramount Skydance e Warner Bros Discovery pode receber aprovação rápida da FCC segundo o presidente Brendan Carr.\n"
            . "- A Universidade Federal de Lavras abriu concurso público com 28 vagas para professor com inscrições abertas até abril de 2026.\n"
            . "\n"
            . "EXEMPLOS PROIBIDOS:\n"
            . "- A decisão reforça o compromisso da empresa.\n"
            . "- A iniciativa demonstra a importância do projeto.\n"
            . "- O cenário atual levanta discussões no setor.\n"
            . "- A empresa segue investindo em inovação.\n"
            . "PROIBIDO:\n"
            . "- Frases genéricas.\n"
            . "- Interpretação de intenção ou emoção de pessoas ou empresas.\n"
            . "- Frases interpretativas, como: \"estratégia que gera menor resistência regulatória\". Isso ainda é uma interpretação, dado sem fato.\n"
            . "- Conclusões analíticas.\n"
            . "- Linguagem institucional como: reforça compromisso, demonstra dedicação, marca um passo importante, destaca a importância.\n"
            . "\n"
            . "LINKS:\n"
            . "- Use hiperlinks em termos-chave, não precisa fazer isso mais de uma vez para a mesma palavra, mas é necessário ao menos uma vez em nomes de empresas, nomes de federação, orgãos do governo e coisas nesse sentido.\n"
            . "- Ao inserir hiperlinks em nomes de veículos de imprensa (ex: CNBC, Financial Times, Bloomberg), direcione para a fonte original da declaração se o URL estiver disponível no conteúdo base.\n"
            . "- Inserir link externo quando relevante.\n"
            . "- Nunca no primeiro parágrafo.\n"
            . "- Usar HTML <a href=\"url\">texto</a>.\n"
            . "\n"
            . "FINALIZAÇÃO:\n"
            . "- Se esta for a seção {{sections_count}} de {{sections_count}}, o último parágrafo pode mencionar estado atual ou próximos passos.\n"
            . "- Nunca usar conclusões genéricas ou vagas..\n"
            . "\n"
            . "EXEMPLO DE FINALIZAÇÃO FORTE\n"
            . "- \"A conclusão do processo ainda depende de [aprovação/decisão] por parte de [órgão ou empresa], que deve analisar [aspecto principal do caso] nas próximas semanas.\"\n"
            . "- \"O próximo passo será [ação principal], quando [empresa x] deverá avaliar [aspecto do processo] antes da decisão final.\"\n"
            . "- \"A expectativa é que [empresa x] finalize [processo ou etapa] até [data/período], após a conclusão das análises conduzidas por [autoridade ou entidade].\"\n"
            . "- \"Se aprovado, o acordo permitirá que [empresa x] avance com [plano estratégico], ampliando sua presença em [setor ou mercado].\"\n"
            . "- \"Além da análise nos Estados Unidos, o negócio ainda será examinado por reguladores em [região ou país], que podem solicitar ajustes antes da aprovação final.\"\n"
            . "- \"Autoridades indicaram que continuarão acompanhando o caso enquanto [empresa x] cumpre as exigências estabelecidas durante o processo.\"\n"
            . "- \"As negociações continuam entre [empresa x] e [empresa y], que ainda discutem os termos finais antes da formalização do acordo.\"\n";
    }

    private static function default_section_modelar_youtube_prompt(): string
    {
        return "Você é um Especialista de Conteúdo Sênior (E-E-A-T).\n"
            . "Sua tarefa é redigir UMA seção específica de um artigo de autoridade, a partir dos pontos fornecidos.\n\default_meta_description_promptn"
            . "Diretrizes:\n"
            . "- Vá direto ao ponto: explique o 'como' e o 'porque' com clareza.\n"
            . "- Evite conteúdo AI Fluff, traga dados e do porquê você escolheria aquela opção, sempre com base nas suas experiências. "
            . "Evite coisas como \"um x (necessidade) confiável pode fazer toda a diferença\", use termos técnicos para mostrar a melhor solução.\n"
            . "- Quero um conteúdo sempre em primeira pessoa, falando de experiências e recomendações suas com base em testes, usabilidade e dando a sua opinião sobre qual o que é melhor.\n"
            . "- Faça de tudo para melhorar a autoridade do conteúdo, mencione ferramentas onlines quando necessário, ferramentas que você usa que poucas pessoas conhecem, se destaque sempre como autoridade no assunto e demonstre isso com comparativos, mencionando sempre o seu ponto de vista pessoal sobre cada um.\n"
            . "- Texto 100% nativo para leitura (não mencione vídeo, canal, assistir).\n"
            . "- Parágrafos curtos (2–4 linhas) e escaneáveis.\n"
            . "- Use <strong> apenas para destacar conceitos essenciais (sem exagero).\n"
            . "- Não invente estatísticas; quando não houver dado, use linguagem prudente.\n\n"
            . "Faça CTA fluido com links externos sobre cada ferramenta mencionada com a tag \"a\" com target _blank.\n\n"
            . "Pontos-chave para transformar em conteúdo denso, com profundidade:\n"
            . "{{section_bullets}}\n\n"
            . "Subtítulos sugeridos (se aplicável):\n"
            . "{{section_children}}\n";
    }


    private static function default_meta_description_prompt(): string
    {
        return
            "Escreva uma meta descrição jornalística para uma notícia.\n\n"

            . "REGRAS:\n"
            . "- Entre 140 e 160 caracteres.\n"
            . "- Texto factual e informativo.\n"
            . "- Baseado apenas nas informações do conteúdo.\n"
            . "- Não inventar dados.\n"
            . "- Não usar emojis.\n"
            . "- Não usar linguagem promocional.\n"
            . "- Não usar termos fracos como: veja, descubra, confira, imperdível.\n"
            . "- Finalizar com um complemento editorial que incentive a leitura.\n\n"

            . "FORMA DO TEXTO:\n"
            . "Fato principal + contexto relevante + complemento final informativo.\n\n"

            . "EXEMPLOS DE FINALIZAÇÃO:\n"
            . "acompanhe os desdobramentos do caso\n"
            . "entenda os impactos da decisão\n"
            . "leia a análise do acordo\n"
            . "acompanhe a cobertura do caso\n"
            . "veja como a decisão afeta o setor\n\n"

            . "Responda apenas com a meta descrição final.";
    }

    private static function default_slug_prompt(): string
    {
        return
            "Crie uma slug curta para uma notícia.\n\n"

            . "REGRAS:\n"
            . "- Usar apenas letras minúsculas.\n"
            . "- Separar palavras com hífen.\n"
            . "- Entre 3 e 7 palavras.\n"
            . "- Manter apenas termos essenciais da notícia.\n"
            . "- Priorizar nomes de empresas, pessoas ou instituições.\n"
            . "- Remover palavras fracas como: de, da, do, que, para, após, como 'o', 'a', 'com', 'para'"
            . "- Não usar números a menos que sejam essenciais.\n";
    }

    private static function default_image_prompt(): string
    {
        return "- Cena única marcante, proporção 16:9.\n"
            . "- Sem texto, sem marcas d’água, sem logos.\n";
    }

    private static function default_post_thumbnail_regen_prompt(): string
    {
        return "Ultra-realistic natural photo, 16:9 aspect ratio.\n"
            . "Avoid text, watermarks, clutter.\n"
            . "- FOTO REALISTA HORIZONTAL 16:9, luz natural, estilo cinematográfico.\n"
            . "- Sem texto, sem logos, sem marca d'água.\n";
    }

    public static function story_default_template(): string
    {
        $s = "- Gere 7 a 10 páginas curtas.\n";
        $s .= "- Linguagem simples e envolvente.\n";
        $s .= "- Responda somente em JSON.\n";
        return $s;
    }

    public static function story_json_format_block(): string
    {
        return "Responda APENAS em JSON UTF-8 válido, no seguinte formato:\n\n"
            . "{\n"
            . "  \"pages\": [\n"
            . "    {\n"
            . "      \"heading\": \"Título\",\n"
            . "      \"body\": \"Texto curto.\",\n"
            . "      \"cta_text\": \"\",\n"
            . "      \"cta_url\": \"\",\n"
            . "      \"prompt\": \"Prompt para gerar uma imagem sobre o slide (sempre em inglês, independente do idioma pedido do conteudo)\"\n"
            . "    }\n"
            . "  ]\n"
            . "}\n";
    }
    private static function default_idea_prompt(): string
    {
        return
            "Voce e um editor senior e deve criar um brief editorial curto, sem pesquisa externa.\n"
            . "Use apenas a palavra-chave, o titulo e o contexto fornecidos.\n\n"
            . "OBJETIVO:\n"
            . "- Definir o angulo editorial do artigo.\n"
            . "- Evitar um outline generico.\n"
            . "- Preparar uma progressao logica de secoes.\n\n"
            . "REGRAS:\n"
            . "- Nao invente fatos externos.\n"
            . "- Nao use frases vagas.\n"
            . "- Cada section_plan precisa ter funcao clara.\n"
            . "- Prefira titulos especificos, nao genericos.\n"
            . "- Se houver conflito, priorize coerencia editorial e utilidade.\n\n"
            . "SAIDA:\n"
            . "- Responda apenas em JSON valido.\n"
            . "- Entregue angle, intent, core_problem, reader_promise, tone, must_cover, avoid e section_plan.\n";
    }

    private static function default_idea_rss_prompt(): string
    {
        return
            "Crie um brief editorial curto para uma noticia de RSS, sem pesquisa externa.\n"
            . "Foque em preservar fatos, evitar exageros e organizar a progressao da materia.\n";
    }

    private static function default_idea_modelar_youtube_prompt(): string
    {
        return
            "Crie um brief editorial curto baseado no material-base do YouTube.\n"
            . "Use os capitulos e a descricao apenas como estrutura, sem citar a fonte.\n"
            . "Entregue um angulo claro, a dor central e uma progressao de secoes que transforme o material em artigo nativo.\n";
    }

    private static function default_vistoria_prompt(): string
    {
        return
            "Voce e um revisor editorial. Identifique genericidade, repeticoes e falta de coerencia.\n"
            . "Se estiver tudo certo, responda apenas 'ok'.\n"
            . "Se houver problema, responda com um resumo objetivo do que precisa ser corrigido.\n";
    }
}
