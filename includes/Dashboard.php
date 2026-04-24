<?php
if (! defined('ABSPATH')) {
  exit;
}

class AlphaSuite_Dashboard
{

  public static function render(): void
  {
    $items = AlphaSuite_Remote::catalog();
    if (! is_array($items)) {
      $items = [];
    }

    $status_label = AlphaSuite_License::is_active()
      ? __('Licença ativa', 'alpha-suite')
      : __('Licença inativa', 'alpha-suite');

    $status_class = AlphaSuite_License::is_active()
      ? 'pga-badge-active'
      : 'pga-badge-inactive';
?>
    <div class="wrap pa-wrap">
      <script src="https://cdn.tailwindcss.com"></script>
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
      <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap');

        :root {
          --brand-primary: #6366f1;
          --bg-main: #fafafa;
        }

        body {
          font-family: 'Plus Jakarta Sans', sans-serif;
          letter-spacing: -0.02em;
          background-color: var(--bg-main);
        }

        .premium-card {
          background: white;
          transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
          border: 1px solid rgba(0, 0, 0, 0.04);
        }

        .premium-card:hover {
          transform: translateY(-4px);
          box-shadow: 0 20px 40px -12px rgba(0, 0, 0, 0.08);
          border-color: rgba(99, 102, 241, 0.2);
        }

        .suite-bar {
          background: radial-gradient(circle at top left, #1e293b, #0f172a);
        }

        .shimmer {
          position: relative;
          overflow: hidden;
        }

        .shimmer::after {
          content: "";
          position: absolute;
          top: -50%;
          left: -50%;
          width: 200%;
          height: 200%;
          background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.03), transparent);
          transform: rotate(45deg);
          animation: shimmer-anim 8s infinite linear;
        }

        @keyframes shimmer-anim {
          0% {
            transform: translateX(-100%) rotate(45deg);
          }

          100% {
            transform: translateX(100%) rotate(45deg);
          }
        }
      </style>
      <div class="max-w-[1200px] mx-auto px-6 py-12">

        <!-- Top Navigation / Header -->
        <nav class="flex justify-between items-center mb-12">
          <div class="flex items-center gap-2">
            <div class="w-8 h-8 bg-slate-900 rounded-lg flex items-center justify-center">
              <img src="<?php echo esc_attr(PGA_URL) . 'assets/images/favicon-alpha-suite.png' ?>" class="rounded-md pga-logo" alt="Alpha Suite" />
            </div>
            <span class="font-bold text-xl tracking-tight">Plugins Alpha</span></span>
          </div>
          <div class="flex items-center gap-6">
            <div class="md:flex gap-6 text-sm font-medium text-slate-500 pga-links">
              <a href="https://www.youtube.com/@pluginsalpha?sub_confirmation=1" target="_blank" class="hover:text-slate-900 transition-colors pga-color-black"><svg aria-hidden="true" class="e-font-icon-svg e-fab-youtube" viewBox="0 0 576 512" xmlns="http://www.w3.org/2000/svg">
                  <path d="M549.655 124.083c-6.281-23.65-24.787-42.276-48.284-48.597C458.781 64 288 64 288 64S117.22 64 74.629 75.486c-23.497 6.322-42.003 24.947-48.284 48.597-11.412 42.867-11.412 132.305-11.412 132.305s0 89.438 11.412 132.305c6.281 23.65 24.787 41.5 48.284 47.821C117.22 448 288 448 288 448s170.78 0 213.371-11.486c23.497-6.321 42.003-24.171 48.284-47.821 11.412-42.867 11.412-132.305 11.412-132.305s0-89.438-11.412-132.305zm-317.51 213.508V175.185l142.739 81.205-142.739 81.201z"></path>
                </svg> Tutoriais</a>
              <a href="https://wa.me/5562983012543?text=Quero%20mais%20informa%C3%A7%C3%B5es%20sobre%20o%20Alpha%20Suite" target="_blank" class="hover:text-slate-900 transition-colors pga-color-black"><svg aria-hidden="true" class="e-font-icon-svg e-fab-whatsapp" viewBox="0 0 448 512" xmlns="http://www.w3.org/2000/svg">
                  <path d="M380.9 97.1C339 55.1 283.2 32 223.9 32c-122.4 0-222 99.6-222 222 0 39.1 10.2 77.3 29.6 111L0 480l117.7-30.9c32.4 17.7 68.9 27 106.1 27h.1c122.3 0 224.1-99.6 224.1-222 0-59.3-25.2-115-67.1-157zm-157 341.6c-33.2 0-65.7-8.9-94-25.7l-6.7-4-69.8 18.3L72 359.2l-4.4-7c-18.5-29.4-28.2-63.3-28.2-98.2 0-101.7 82.8-184.5 184.6-184.5 49.3 0 95.6 19.2 130.4 54.1 34.8 34.9 56.2 81.2 56.1 130.5 0 101.8-84.9 184.6-186.6 184.6zm101.2-138.2c-5.5-2.8-32.8-16.2-37.9-18-5.1-1.9-8.8-2.8-12.5 2.8-3.7 5.6-14.3 18-17.6 21.8-3.2 3.7-6.5 4.2-12 1.4-32.6-16.3-54-29.1-75.5-66-5.7-9.8 5.7-9.1 16.3-30.3 1.8-3.7.9-6.9-.5-9.7-1.4-2.8-12.5-30.1-17.1-41.2-4.5-10.8-9.1-9.3-12.5-9.5-3.2-.2-6.9-.2-10.6-.2-3.7 0-9.7 1.4-14.8 6.9-5.1 5.6-19.4 19-19.4 46.3 0 27.3 19.9 53.7 22.6 57.4 2.8 3.7 39.1 59.7 94.8 83.8 35.2 15.2 49 16.5 66.6 13.9 10.7-1.6 32.8-13.4 37.4-26.4 4.6-13 4.6-24.1 3.2-26.4-1.3-2.5-5-3.9-10.5-6.6z"></path>
                </svg> Suporte</a>
            </div>
            <p>
              <span class="<?php echo esc_attr($status_class); ?>" style="display:inline-block;padding:2px 8px;border-radius:999px;font-weight:600;font-size:12px;
                    <?php echo AlphaSuite_License::is_active()
                      ? 'background:#46b450;color:#fff;'
                      : 'background:#dc3232;color:#fff;'; ?>">
                <?php echo esc_html($status_label); ?>
              </span>
            </p>
          </div>
        </nav>

        <!-- Horizontal Suite Bar (Compacta) -->
        <section class="mb-16">
          <div class="suite-bar shimmer rounded-3xl p-5 md:p-6 text-white relative overflow-hidden shadow-xl border border-white/5 flex flex-col md:flex-row items-center justify-between gap-6">
            <div class="flex items-center gap-6 relative z-10">
              <div class="hidden sm:flex w-12 h-12 bg-white/5 rounded-2xl items-center justify-center border border-white/10">
                <svg class="w-6 h-6 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-7.714 2.143L11 21l-2.286-6.857L1 12l7.714-2.143L11 3z"></path>
                </svg>
              </div>
              <div>
                <div class="flex items-center gap-3 mb-1">
                  <h2 class="text-lg font-bold tracking-tight pga-color-white">Alpha Suite Premium</h2>
                  <span class="text-[9px] bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 px-2 py-0.5 rounded-full font-bold uppercase tracking-widest">Oferta</span>
                </div>
                <p class="text-slate-400 text-sm">Desbloqueie o Órion + Stories com automação completa com 25% de desconto.</p>
              </div>
            </div>

            <div class="flex items-center gap-6 relative z-10 w-full md:w-auto justify-between md:justify-end border-t md:border-t-0 border-white/5 pt-4 md:pt-0">
              <div class="text-right">
                <span class="block text-[10px] text-slate-500 font-bold uppercase">Plano para até 3 domínios</span>
                <div class="flex items-baseline gap-1">
                  <span class="text-xl font-bold text-white">R$ 24,90</span>
                  <span class="text-[11px] text-slate-400">/mês</span>
                </div>
              </div>
              <a href="https://buy.stripe.com/3cIcN56KL5SV6e98pH4ZG0k" target="_blank" class="bg-indigo-600 hover:bg-indigo-500 text-white px-6 py-3 rounded-xl font-bold text-sm transition-all shadow-lg shadow-indigo-600/20 whitespace-nowrap">
                Comprar Agora
              </a>
            </div>
            <!-- Subtle Background Glow -->
            <div class="absolute right-0 top-0 w-64 h-full bg-indigo-600/10 blur-[60px] rounded-full"></div>
          </div>
        </section>

        <!-- 📈 GRÁFICO FULL -->
        <div class="bg-white rounded-2xl p-5 shadow-sm border mt-6">

          <div class="flex justify-between items-center mb-4">
            <h3 class="font-semibold text-sm text-gray-700">Desempenho</h3>

            <select id="pgaRange" class="text-sm border rounded-lg px-2 py-1">
              <option value="7">7 dias</option>
              <option value="15">15 dias</option>
              <option value="30" selected>30 dias</option>
              <option value="180">6 meses</option>
            </select>
          </div>

          <div class="h-[280px]">
            <canvas id="pgaChart"></canvas>
          </div>

        </div>

        <!-- 🏆 + 🧠 LADO A LADO -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6 mb-6">

          <div class="bg-white rounded-2xl p-5 shadow-sm border">
            <h3 class="font-semibold text-sm text-gray-700 mb-4">
              Top páginas (visualizações)
            </h3>
            <div id="topPosts" class="space-y-2 text-sm"></div>
          </div>

          <div class="bg-white rounded-2xl p-5 shadow-sm border">
            <h3 class="font-semibold text-sm text-gray-700 mb-4">
              Maior tempo de permanência
            </h3>
            <div id="topTime" class="space-y-2 text-sm"></div>
          </div>

        </div>

        <!-- Product Grid Title -->
        <div class="flex items-end justify-between mb-8">
          <div>
            <h3 class="text-xl font-extrabold tracking-tight">Seus Plugins</h3>
            <p class="text-slate-500 text-xs mt-1">Ferramentas de IA para produtividade em blogs.</p>
          </div>
          <div class="flex gap-2">
            <div class="bg-white border border-slate-200 px-3 py-1.5 rounded-xl text-[15px] font-bold text-slate-600 shadow-sm flex items-center gap-2">
              <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></span> 6 IAs Ativas
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

          <!-- Alpha Órion -->
          <div class="premium-card rounded-[2rem] p-8 flex flex-col justify-between">
            <div>
              <div class="flex justify-between items-start mb-8">
                <div class="w-12 h-12 bg-slate-50 border border-slate-100 rounded-xl flex items-center justify-center text-indigo-600 shadow-sm">
                  <img src="<?php echo esc_attr(PGA_URL) . 'assets/images/orion-posts.png' ?>" class="rounded-md" alt="Alpha Órion" />
                </div>
                <span class="text-[9px] font-extrabold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-md tracking-wider">INSTALADO</span>
              </div>
              <h4 class="text-lg font-extrabold mb-2">Alpha Órion</h4>
              <p class="text-slate-500 text-sm leading-relaxed mb-6">
                Automação de conteúdo via RSS e gerador de artigos em massa com IA.
              </p>
            </div>
            <div class="pt-6 border-t border-slate-50">
              <div class="flex items-center justify-between mb-4 px-1">
                <span class="text-slate-400 text-xs line-through font-medium">R$ 24,90</span>
                <span class="text-base font-bold text-slate-900">R$ 19,90 <small class="text-[10px] font-normal opacity-50">/mês</small></span>
              </div>
              <a href="<?php echo esc_attr(site_url()) ?>/wp-admin/admin.php?page=alpha-suite-orion-posts" class="w-full bg-slate-900 text-white py-3.5 rounded-xl font-bold text-sm hover:bg-slate-800 transition-all pga-full">Abrir Painel</a>
            </div>
          </div>

          <!-- Alpha Stories -->
          <div class="premium-card rounded-[2rem] p-8 flex flex-col justify-between">
            <div>
              <div class="flex justify-between items-start mb-8">
                <div class="w-12 h-12 bg-slate-50 border border-slate-100 rounded-xl flex items-center justify-center text-amber-500 shadow-sm">
                  <img src="<?php echo esc_attr(PGA_URL) . 'assets/images/alpha-stories.png' ?>" class="rounded-md" alt="Alpha Stories" />
                </div>
                <span class="text-[9px] font-extrabold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-md tracking-wider">INSTALADO</span>
              </div>
              <h4 class="text-lg font-extrabold mb-2">Alpha Stories & WS</h4>
              <p class="text-slate-500 text-sm leading-relaxed mb-6">
                Converta seus posts em Web Stories dinâmicos. Foco em Google Discover.
              </p>
            </div>
            <div class="pt-6 border-t border-slate-50">
              <div class="flex items-center justify-between mb-4 px-1">
                <span class="text-slate-400 text-xs line-through font-medium">R$ 19,90</span>
                <span class="text-base font-bold text-slate-900">R$ 14,90 <small class="text-[10px] font-normal opacity-50">/mês</small></span>
              </div>
              <a href="<?php echo esc_attr(site_url()) ?>/wp-admin/admin.php?page=alpha-suite-ws-generator" class="w-full bg-slate-900 text-white py-3.5 rounded-xl font-bold text-sm hover:bg-slate-800 transition-all pga-full">Abrir Painel</a>
            </div>
          </div>

          <!-- Alpha Form -->
          <div class="premium-card rounded-[2rem] p-8 flex flex-col justify-between group">
            <div>
              <div class="flex justify-between items-start mb-8">
                <div class="w-12 h-12 bg-slate-50 border border-slate-100 rounded-xl flex items-center justify-center text-pink-500 shadow-sm group-hover:bg-pink-50 transition-colors">
                  <img src="<?php echo esc_attr(PGA_URL) . 'assets/images/alpha-form.png' ?>" class="rounded-md" alt="Alpha Form" />
                </div>
                <span class="text-[9px] font-extrabold text-pink-600 bg-pink-50 px-2 py-0.5 rounded-md tracking-wider uppercase">Breve</span>
              </div>
              <h4 class="text-lg font-extrabold mb-2">Alpha Form</h4>
              <p class="text-slate-500 text-sm leading-relaxed mb-6">
                Quiz interativos e formulários no estilo Typeform, focados em conversão (editavel no Elementor).
              </p>
            </div>
            <div class="pt-6 border-t border-slate-50">
              <button class="w-full bg-white border border-slate-200 text-slate-900 py-3.5 rounded-xl font-bold text-sm hover:bg-slate-50 transition-all">Breve</button>
            </div>
          </div>

          <!-- Zap Alpha -->
          <div class="bg-slate-200/20 border border-slate-200 border-dashed rounded-[2rem] p-8 flex flex-col justify-between grayscale opacity-60">
            <div>
              <div class="w-10 h-10 bg-slate-100 rounded-xl flex items-center justify-center text-slate-400 mb-6">
                <i class="fab fa-whatsapp text-lg"></i>
              </div>
              <h4 class="text-base font-bold mb-2">Zap Alpha</h4>
              <p class="text-slate-500 text-[13px] leading-relaxed">Mensagens via API Oficial.</p>
            </div>
            <div class="mt-8">
              <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Em breve</span>
            </div>
          </div>

          <!-- Delivery Alpha -->
          <div class="bg-slate-200/20 border border-slate-200 border-dashed rounded-[2rem] p-8 flex flex-col justify-between grayscale opacity-60">
            <div>
              <div class="w-10 h-10 bg-slate-100 rounded-xl flex items-center justify-center text-slate-400 mb-6">
                <i class="fas fa-box text-lg"></i>
              </div>
              <h4 class="text-base font-bold mb-2">Delivery Alpha</h4>
              <p class="text-slate-500 text-[13px] leading-relaxed">Gestão de pedidos no WP.</p>
            </div>
            <div class="mt-8">
              <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Em breve</span>
            </div>
          </div>

        </div>

        <!-- Compact Footer -->
        <footer class="mt-20 pt-12 border-t border-slate-100 flex flex-col md:flex-row justify-between items-center gap-6">
          <div class="flex items-center gap-4 text-xs text-slate-400">
            <span class="font-bold text-slate-900">Alpha Plugins</span>
            <span>© 2026</span>
            <a href="https://pluginsalpha.com/transparencia/privacidade/" target="_blank" class="hover:text-slate-900">Privacidade</a>
            <a href="https://pluginsalpha.com/transparencia/termos/" target="_blank" class="hover:text-slate-900">Termos</a>
          </div>
          <div class="flex items-center gap-4">
            <span class="text-[10px] font-bold text-slate-300 uppercase tracking-[0.2em]">Tecnologia Alpha Core</span>
            <div class="flex gap-2">
              <div class="w-5 h-5 bg-slate-100 rounded flex items-center justify-center text-[10px] text-slate-400 font-bold">GPT</div>
              <div class="w-5 h-5 bg-slate-100 rounded flex items-center justify-center text-[10px] text-slate-400 font-bold">CLD</div>
              <div class="w-5 h-5 bg-slate-100 rounded flex items-center justify-center text-[10px] text-slate-400 font-bold">GMN</div>
            </div>
          </div>
        </footer>

      </div>
    </div>
<?php
  }
}
