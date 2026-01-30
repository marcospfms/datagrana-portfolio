<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue';
import { dashboard } from '@/routes';

// Contador animado para prova social
const userCount = ref(0);
const portfolioCount = ref(0);
const targetUsers = 12847;
const targetPortfolios = 45623;
let countersInterval: ReturnType<typeof setInterval> | undefined;
let autoplayInterval: ReturnType<typeof setInterval> | undefined;

onMounted(() => {
    const duration = 2000;
    const steps = 60;
    const userIncrement = targetUsers / steps;
    const portfolioIncrement = targetPortfolios / steps;

    let currentStep = 0;
    countersInterval = setInterval(() => {
        if (currentStep >= steps) {
            clearInterval(countersInterval);
            userCount.value = targetUsers;
            portfolioCount.value = targetPortfolios;
        } else {
            userCount.value = Math.floor(userIncrement * currentStep);
            portfolioCount.value = Math.floor(portfolioIncrement * currentStep);
            currentStep++;
        }
    }, duration / steps);
});

// Carrossel de Screenshots
const currentScreenshot = ref(0);
const screenshots = [
    { title: 'Resumo do Patrimônio', description: 'Visão geral do patrimônio e posições', image: 'home' },
    { title: 'Carteiras Ideais', description: 'Busca, criação e metas por carteira', image: 'portfolios' },
    { title: 'Composições da Carteira', description: 'Alocação e ativos organizados por categoria', image: 'composition' },
    { title: 'Comparação (Crossing)', description: 'Portfólio ideal x posição atual', image: 'crossing' }
];

const nextScreenshot = () => {
    currentScreenshot.value = (currentScreenshot.value + 1) % screenshots.length;
};

const prevScreenshot = () => {
    currentScreenshot.value = (currentScreenshot.value - 1 + screenshots.length) % screenshots.length;
};

const goToScreenshot = (index: number) => {
    currentScreenshot.value = index;
};

onMounted(() => {
    autoplayInterval = setInterval(() => {
        nextScreenshot();
    }, 4500);
});

onUnmounted(() => {
    if (countersInterval) {
        clearInterval(countersInterval);
    }
    if (autoplayInterval) {
        clearInterval(autoplayInterval);
    }
});

const plans = [
    {
        name: 'Gratuito',
        price: 0,
        period: '',
        highlight: false,
        badge: 'Teste Grátis',
        description: 'Ideal para começar a organizar seus investimentos',
        features: ['1 Carteira', 'Até 5 ativos', 'Até 5 posições ativas', '1 Conta bancária', 'Dashboard básico', 'Comparação Parcial'],
        excludedFeatures: ['Histórico de composição']
    },
    {
        name: 'Investidor Iniciante',
        price: 9.90,
        period: '/mês',
        highlight: false,
        badge: '',
        description: 'Para investidores começando a diversificar',
        features: ['2 Carteiras', 'Até 10 ativos por carteira', 'Até 10 posições ativas', '2 Contas bancárias', 'Comparação completa (crossing)', 'Histórico de composição'],
        excludedFeatures: []
    },
    {
        name: 'Investidor Pro',
        price: 14.90,
        period: '/mês',
        highlight: true,
        badge: 'Mais Popular',
        description: 'Para investidores ativos com múltiplas estratégias',
        features: ['4 Carteiras diferentes', 'Até 25 ativos por carteira', 'Até 25 posições ativas', '4 Contas bancárias', 'Comparação completa (crossing)', 'Histórico de composição'],
        excludedFeatures: []
    },
    {
        name: 'Premium',
        price: 24.90,
        period: '/mês',
        highlight: false,
        badge: 'Completo',
        description: 'Recursos ilimitados para investidores profissionais',
        features: ['Carteiras ilimitadas', 'Ativos ilimitados', 'Posições ilimitadas', 'Contas ilimitadas', 'Comparação completa (crossing)', 'Histórico de composição'],
        excludedFeatures: []
    }
];

const features = [
    { icon: '📊', title: '100% Mobile', description: 'Acesse suas carteiras de qualquer lugar' },
    { icon: '⚡', title: 'Tempo Real', description: 'Dados sempre atualizados automaticamente' },
    { icon: '🔒', title: '100% Seguro', description: 'Criptografia de ponta a ponta' },
    { icon: '🎯', title: 'Inteligente', description: 'Rebalanceamento automático' }
];

const testimonials = [
    { name: 'Carlos Silva', role: 'Investidor há 5 anos', avatar: 'CS', text: 'App sensacional! Acompanho meus FIIs e ações direto do celular com facilidade.' },
    { name: 'Ana Paula', role: 'Analista Financeira', avatar: 'AP', text: 'A análise de crossing é perfeita. Sei exatamente o que comprar para rebalancear.' },
    { name: 'Roberto Mendes', role: 'Investidor Pro', avatar: 'RM', text: 'Interface limpa e funcional. Melhor app de controle de investimentos!' }
];
</script>

<template>

    <Head title="DataGrana - Gestão de Investimentos no seu Bolso">
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" />
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
            rel="stylesheet" />
        <link rel="icon" type="image/png" href="/images/favicon.png" />
    </Head>

    <!-- Navegação Fixa -->
    <nav
        class="fixed top-0 left-0 right-0 z-50 bg-linear-to-b from-[#0b1216] to-[#0b1216]/95 backdrop-blur-lg border-b border-white/10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center gap-3">
                    <img src="/images/icon-transparent.png" alt="DataGrana" class="w-10 h-10" />
                    <div>
                        <span class="text-lg font-bold text-[#fef6ea]">Datagrana App</span>
                        <p class="text-xs text-[#b9d6d0]">Carteiras reais, decisões claras.</p>
                    </div>
                </div>

                <div class="hidden md:flex items-center gap-8">
                    <a href="#features"
                        class="text-[#cfe3df] hover:text-[#17a2b8] transition-colors font-medium text-sm">Recursos</a>
                    <a href="#pricing"
                        class="text-[#cfe3df] hover:text-[#17a2b8] transition-colors font-medium text-sm">Preços</a>
                    <a href="#testimonials"
                        class="text-[#cfe3df] hover:text-[#17a2b8] transition-colors font-medium text-sm">Depoimentos</a>
                </div>

                <div class="flex items-center gap-4">
                    <template v-if="$page.props.auth.user">
                        <Link :href="dashboard()"
                            class="px-6 py-2.5 bg-linear-to-r from-[#17a2b8] to-[#0d5f5f] text-white rounded-lg font-semibold hover:shadow-lg hover:shadow-[#17a2b8]/30 hover:scale-105 transition-all">
                            Dashboard
                        </Link>
                    </template>
                    <template v-else>
                        <a href="https://play.google.com/store" target="_blank" rel="noopener"
                            class="px-6 py-2.5 bg-linear-to-r from-[#17a2b8] to-[#0d5f5f] text-white rounded-lg font-semibold hover:shadow-lg hover:shadow-[#17a2b8]/30 hover:scale-105 transition-all flex items-center gap-2">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                                <path
                                    d="M3,20.5V3.5C3,2.91 3.34,2.39 3.84,2.15L13.69,12L3.84,21.85C3.34,21.6 3,21.09 3,20.5M16.81,15.12L6.05,21.34L14.54,12.85L16.81,15.12M20.16,10.81C20.5,11.08 20.75,11.5 20.75,12C20.75,12.5 20.53,12.9 20.18,13.18L17.89,14.5L15.39,12L17.89,9.5L20.16,10.81M6.05,2.66L16.81,8.88L14.54,11.15L6.05,2.66Z" />
                            </svg>
                            <span>Baixar App</span>
                        </a>
                    </template>
                </div>
            </div>
        </div>
    </nav>

    <div class="min-h-screen bg-linear-to-br from-[#0b1216] via-[#0b2a2b] to-[#091f1e]">
        <!-- Glows de fundo -->
        <div
            class="fixed top-0 right-0 w-96 h-96 bg-[#f7c873] opacity-25 blur-[150px] rounded-full pointer-events-none">
        </div>
        <div
            class="fixed bottom-0 left-0 w-125 h-125 bg-[#3ec9c2] opacity-18 blur-[180px] rounded-full pointer-events-none">
        </div>

        <!-- Hero + Screenshots Section (Lado a Lado) -->
        <section class="pt-32 pb-20 px-4 sm:px-6 lg:px-8 relative">
            <div class="max-w-7xl mx-auto">
                <div class="grid lg:grid-cols-2 gap-12 items-center">
                    <!-- Hero Content - Esquerda -->
                    <div>
                        <!-- Badges -->
                        <div class="flex flex-wrap items-center gap-3 mb-8">
                            <!-- Badge Mobile -->
                            <div
                                class="inline-flex items-center gap-2 px-4 py-2 bg-white/5 border border-white/10 rounded-full backdrop-blur-sm">
                                <svg class="w-5 h-5 text-[#17a2b8]" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                                <span class="text-[#17a2b8] text-sm font-semibold">📱 Aplicativo Mobile</span>
                            </div>

                            <!-- Badge de Escassez -->
                            <div
                                class="inline-flex items-center gap-2 px-4 py-2 bg-amber-500/10 border border-amber-500/30 rounded-full backdrop-blur-sm">
                                <span class="w-2 h-2 bg-amber-400 rounded-full animate-pulse"></span>
                                <span class="text-amber-400 text-sm font-semibold">🔥 Últimas 47 vagas - 33% OFF</span>
                            </div>
                        </div>

                        <!-- Headline -->
                        <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold text-[#fef6ea] leading-tight mb-6">
                            Seus investimentos
                            <span
                                class="bg-linear-to-r from-[#17a2b8] to-[#3ec9c2] bg-clip-text text-transparent block">
                                no seu bolso
                            </span>
                        </h1>

                        <p class="text-xl text-[#cfe3df] mb-8 leading-relaxed">
                            Acompanhe tudo em um único painel. Posições consolidadas, metas e crossing em tempo real.
                        </p>

                        <!-- Prova Social -->
                        <div class="grid grid-cols-3 gap-6 mb-8">
                            <div class="text-center">
                                <div class="text-3xl font-bold text-[#17a2b8]">{{ userCount.toLocaleString('pt-BR') }}+
                                </div>
                                <div class="text-xs text-[#b9d6d0]">Investidores</div>
                            </div>
                            <div class="text-center">
                                <div class="text-3xl font-bold text-[#17a2b8]">{{ portfolioCount.toLocaleString('pt-BR')
                                }}+
                                </div>
                                <div class="text-xs text-[#b9d6d0]">Carteiras</div>
                            </div>
                            <div class="text-center">
                                <div class="text-3xl font-bold text-[#17a2b8]">R$ 2.4Bi+</div>
                                <div class="text-xs text-[#b9d6d0]">Patrimônio</div>
                            </div>
                        </div>

                        <!-- Store Badges -->
                        <div class="flex flex-col sm:flex-row gap-4">
                            <a href="https://play.google.com/store" target="_blank" rel="noopener"
                                class="group flex items-center gap-3 px-6 py-3 bg-white text-gray-900 rounded-xl transition-all hover:scale-105 hover:shadow-xl">
                                <svg class="w-8 h-8" viewBox="0 0 24 24" fill="currentColor">
                                    <path
                                        d="M3,20.5V3.5C3,2.91 3.34,2.39 3.84,2.15L13.69,12L3.84,21.85C3.34,21.6 3,21.09 3,20.5M16.81,15.12L6.05,21.34L14.54,12.85L16.81,15.12M20.16,10.81C20.5,11.08 20.75,11.5 20.75,12C20.75,12.5 20.53,12.9 20.18,13.18L17.89,14.5L15.39,12L17.89,9.5L20.16,10.81M6.05,2.66L16.81,8.88L14.54,11.15L6.05,2.66Z" />
                                </svg>
                                <div class="text-left">
                                    <div class="text-xs opacity-70">Disponível na</div>
                                    <div class="text-base font-bold">Google Play</div>
                                </div>
                            </a>

                            <div
                                class="flex items-center gap-3 px-6 py-3 bg-white/10 text-[#cfe3df] rounded-xl cursor-not-allowed backdrop-blur-sm border border-white/10">
                                <svg class="w-8 h-8" viewBox="0 0 24 24" fill="currentColor">
                                    <path
                                        d="M18.71,19.5C17.88,20.74 17,21.95 15.66,21.97C14.32,22 13.89,21.18 12.37,21.18C10.84,21.18 10.37,21.95 9.1,22C7.79,22.05 6.8,20.68 5.96,19.47C4.25,17 2.94,12.45 4.7,9.39C5.57,7.87 7.13,6.91 8.82,6.88C10.1,6.86 11.32,7.75 12.11,7.75C12.89,7.75 14.37,6.68 15.92,6.84C16.57,6.87 18.39,7.1 19.56,8.82C19.47,8.88 17.39,10.1 17.41,12.63C17.44,15.65 20.06,16.66 20.09,16.67C20.06,16.74 19.67,18.11 18.71,19.5M13,3.5C13.73,2.67 14.94,2.04 15.94,2C16.07,3.17 15.6,4.35 14.9,5.19C14.21,6.04 13.07,6.7 11.95,6.61C11.8,5.46 12.36,4.26 13,3.5Z" />
                                </svg>
                                <div class="text-left">
                                    <div class="text-xs opacity-70">Em breve na</div>
                                    <div class="text-base font-bold">App Store</div>
                                </div>
                            </div>
                        </div>

                        <!-- Trust Indicators -->
                        <div class="mt-8 flex flex-col gap-3">
                            <div class="flex items-center gap-3">
                                <svg class="w-5 h-5 text-[#17a2b8]" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                        clip-rule="evenodd" />
                                </svg>
                                <span class="text-sm text-[#d5e7e2]">Token armazenado com SecureStore</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <svg class="w-5 h-5 text-[#17a2b8]" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                        clip-rule="evenodd" />
                                </svg>
                                <span class="text-sm text-[#d5e7e2]">Sessão revogável por dispositivo</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <svg class="w-5 h-5 text-[#17a2b8]" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                        clip-rule="evenodd" />
                                </svg>
                                <span class="text-sm text-[#d5e7e2]">Atualização automática do perfil</span>
                            </div>
                        </div>
                    </div>

                    <!-- Screenshots Carousel - Direita -->
                    <div class="relative">
                        <!-- Phone Frame -->
                        <div class="relative max-w-sm mx-auto mt-3">
                            <!-- Glow atrás do celular -->
                            <div
                                class="absolute inset-0 bg-linear-to-r from-[#17a2b8] to-[#3ec9c2] rounded-[3rem] blur-3xl opacity-30 animate-pulse">
                            </div>

                            <!-- Frame -->
                            <div class="relative bg-[#0b1216] rounded-[3rem] p-3 shadow-2xl border border-white/10">
                                <div
                                    class="bg-linear-to-br from-[#0b1216] via-[#0b2a2b] to-[#091f1e] rounded-[2.5rem] overflow-hidden">
                                    <!-- Screenshot Content -->
                                    <div
                                        class="h-[600px] bg-linear-to-br from-[#0b1216] via-[#0b2a2b] to-[#091f1e] p-5 relative overflow-hidden">
                                        <!-- Glows internos -->
                                        <div
                                            class="absolute top-0 right-0 w-32 h-32 bg-[#f7c873] opacity-20 blur-3xl rounded-full">
                                        </div>
                                        <div
                                            class="absolute bottom-0 left-0 w-40 h-40 bg-[#3ec9c2] opacity-15 blur-3xl rounded-full">
                                        </div>

                                        <!-- Home Screen -->
                                        <div v-if="currentScreenshot === 0" class="relative z-10 space-y-4">
                                            <div class="flex items-center justify-between mb-2">
                                                <div>
                                                    <p class="text-lg font-bold text-[#fef6ea]">Resumo</p>
                                                    <p class="text-xs text-[#b9d6d0]">Visão geral do seu patrimônio e
                                                        posições</p>
                                                </div>
                                                <div
                                                    class="px-2.5 py-1 text-[10px] uppercase tracking-[0.2em] text-[#cfe3df] bg-white/5 rounded-full border border-white/10">
                                                    Atualizado
                                                </div>
                                            </div>

                                            <div
                                                class="bg-white/8 backdrop-blur-sm rounded-2xl p-5 border border-white/10 relative overflow-hidden">
                                                <div
                                                    class="absolute top-0 right-0 w-24 h-24 bg-[#17a2b8] opacity-10 blur-2xl rounded-full">
                                                </div>
                                                <div class="flex items-center justify-between">
                                                    <span
                                                        class="text-[10px] uppercase tracking-[0.2em] text-[#b9d6d0]">Patrimônio
                                                        total</span>
                                                    <span
                                                        class="inline-flex items-center gap-1 text-[10px] px-2 py-1 bg-[#10B981]/20 text-[#10B981] rounded-full font-semibold">
                                                        ▲ 12,50%
                                                    </span>
                                                </div>
                                                <div class="text-3xl font-bold text-[#fef6ea] mt-3">R$ 847.543</div>
                                                <div class="grid grid-cols-2 gap-4 mt-4">
                                                    <div>
                                                        <div class="text-[10px] uppercase text-[#b9d6d0]">Investido
                                                        </div>
                                                        <div class="text-sm font-semibold text-[#cfe3df]">R$ 752.134
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <div class="text-[10px] uppercase text-[#b9d6d0]">Resultado
                                                        </div>
                                                        <div class="text-sm font-semibold text-[#10B981]">R$ 95.409
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="flex items-center justify-between">
                                                <p class="text-sm font-semibold text-[#fef6ea]">Patrimônio por conta</p>
                                                <span
                                                    class="text-[10px] px-2 py-1 bg-white/5 text-[#b9d6d0] rounded-full border border-white/10">Top
                                                    3</span>
                                            </div>
                                            <div
                                                class="bg-white/5 backdrop-blur-sm rounded-xl p-4 border border-white/10 space-y-3">
                                                <div class="flex items-center justify-between">
                                                    <div class="flex items-center gap-3">
                                                        <div
                                                            class="w-9 h-9 rounded-xl bg-[#111827] border border-white/10 flex items-center justify-center">
                                                            <span class="text-xs">🏦</span>
                                                        </div>
                                                        <div>
                                                            <div class="text-sm font-semibold text-[#fef6ea]">Inter
                                                            </div>
                                                            <div class="text-xs text-[#b9d6d0]">Conta Corrente</div>
                                                        </div>
                                                    </div>
                                                    <div class="text-right">
                                                        <div class="text-sm font-semibold text-[#fef6ea]">R$ 245.120
                                                        </div>
                                                        <div class="text-xs text-[#10B981]">+8,20%</div>
                                                    </div>
                                                </div>
                                                <div class="flex items-center justify-between">
                                                    <div class="flex items-center gap-3">
                                                        <div
                                                            class="w-9 h-9 rounded-xl bg-[#111827] border border-white/10 flex items-center justify-center">
                                                            <span class="text-xs">📈</span>
                                                        </div>
                                                        <div>
                                                            <div class="text-sm font-semibold text-[#fef6ea]">XP</div>
                                                            <div class="text-xs text-[#b9d6d0]">Investimentos</div>
                                                        </div>
                                                    </div>
                                                    <div class="text-right">
                                                        <div class="text-sm font-semibold text-[#fef6ea]">R$ 412.850
                                                        </div>
                                                        <div class="text-xs text-[#10B981]">+15,70%</div>
                                                    </div>
                                                </div>
                                                <div class="flex items-center justify-between">
                                                    <div class="flex items-center gap-3">
                                                        <div
                                                            class="w-9 h-9 rounded-xl bg-[#111827] border border-white/10 flex items-center justify-center">
                                                            <span class="text-xs">💼</span>
                                                        </div>
                                                        <div>
                                                            <div class="text-sm font-semibold text-[#fef6ea]">BTG</div>
                                                            <div class="text-xs text-[#b9d6d0]">Conta Invest</div>
                                                        </div>
                                                    </div>
                                                    <div class="text-right">
                                                        <div class="text-sm font-semibold text-[#fef6ea]">R$ 189.573
                                                        </div>
                                                        <div class="text-xs text-[#EF4444]">-2,10%</div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="flex items-center justify-between">
                                                <p class="text-sm font-semibold text-[#fef6ea]">Ativos por categoria</p>
                                                <span
                                                    class="text-[10px] px-2 py-1 bg-white/5 text-[#b9d6d0] rounded-full border border-white/10">Top
                                                    3</span>
                                            </div>
                                            <div class="space-y-2">
                                                <div
                                                    class="bg-white/5 backdrop-blur-sm rounded-xl px-4 py-3 border border-white/10 flex items-center justify-between">
                                                    <div class="flex items-center gap-3">
                                                        <div
                                                            class="w-8 h-8 rounded-lg border border-[#17a2b8]/50 bg-[#17a2b8]/10 flex items-center justify-center text-xs text-[#cfe3df]">
                                                            📊
                                                        </div>
                                                        <div>
                                                            <div class="text-sm font-semibold text-[#fef6ea]">Ações
                                                            </div>
                                                            <div class="text-xs text-[#b9d6d0]">6 ativos</div>
                                                        </div>
                                                    </div>
                                                    <div class="text-right">
                                                        <div class="text-sm font-semibold text-[#fef6ea]">R$ 380.110
                                                        </div>
                                                        <div class="text-xs text-[#10B981]">+9,40%</div>
                                                    </div>
                                                </div>
                                                <div
                                                    class="bg-white/5 backdrop-blur-sm rounded-xl px-4 py-3 border border-white/10 flex items-center justify-between">
                                                    <div class="flex items-center gap-3">
                                                        <div
                                                            class="w-8 h-8 rounded-lg border border-[#f7c873]/50 bg-[#f7c873]/10 flex items-center justify-center text-xs text-[#f7c873]">
                                                            🏢
                                                        </div>
                                                        <div>
                                                            <div class="text-sm font-semibold text-[#fef6ea]">FIIs</div>
                                                            <div class="text-xs text-[#b9d6d0]">4 ativos</div>
                                                        </div>
                                                    </div>
                                                    <div class="text-right">
                                                        <div class="text-sm font-semibold text-[#fef6ea]">R$ 290.450
                                                        </div>
                                                        <div class="text-xs text-[#10B981]">+6,30%</div>
                                                    </div>
                                                </div>
                                                <div
                                                    class="bg-white/5 backdrop-blur-sm rounded-xl px-4 py-3 border border-white/10 flex items-center justify-between">
                                                    <div class="flex items-center gap-3">
                                                        <div
                                                            class="w-8 h-8 rounded-lg border border-[#3ec9c2]/50 bg-[#3ec9c2]/10 flex items-center justify-center text-xs text-[#3ec9c2]">
                                                            🛡️
                                                        </div>
                                                        <div>
                                                            <div class="text-sm font-semibold text-[#fef6ea]">Tesouro
                                                            </div>
                                                            <div class="text-xs text-[#b9d6d0]">2 ativos</div>
                                                        </div>
                                                    </div>
                                                    <div class="text-right">
                                                        <div class="text-sm font-semibold text-[#fef6ea]">R$ 176.983
                                                        </div>
                                                        <div class="text-xs text-[#10B981]">+4,80%</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Portfolios Screen -->
                                        <div v-if="currentScreenshot === 1" class="relative z-10 space-y-4">
                                            <div class="flex items-center justify-between">
                                                <div>
                                                    <p class="text-lg font-bold text-[#fef6ea]">Carteiras</p>
                                                    <p class="text-xs text-[#b9d6d0]">Carteiras ideais e composições</p>
                                                </div>
                                                <div
                                                    class="px-3 py-1.5 rounded-full bg-linear-to-r from-[#17a2b8] to-[#0d5f5f] text-[10px] uppercase tracking-[0.25em] text-white font-semibold shadow-lg shadow-[#17a2b8]/30">
                                                    Nova
                                                </div>
                                            </div>

                                            <div class="bg-white/5 border border-white/10 rounded-xl px-4 py-2">
                                                <div class="flex items-center gap-3 text-[#b9d6d0] text-xs">
                                                    <span
                                                        class="w-7 h-7 rounded-full bg-white/10 flex items-center justify-center text-[11px]">🔍</span>
                                                    <span>Buscar carteira</span>
                                                </div>
                                            </div>

                                            <div class="space-y-3">
                                                <div
                                                    class="bg-white/8 backdrop-blur-sm rounded-2xl p-4 border border-white/10">
                                                    <div class="flex items-center gap-3 mb-3">
                                                        <div
                                                            class="w-10 h-10 rounded-xl bg-[#17a2b8]/10 border border-[#17a2b8]/40 flex items-center justify-center">
                                                            <span class="text-[#17a2b8] text-xs">▦</span>
                                                        </div>
                                                        <div class="flex-1">
                                                            <div class="text-sm font-bold text-[#fef6ea]">Dividendos
                                                            </div>
                                                            <div class="text-xs text-[#b9d6d0]">12 ativos</div>
                                                        </div>
                                                        <div
                                                            class="px-2 py-1 text-[10px] rounded-full border border-[#17a2b8]/40 text-[#17a2b8] bg-[#17a2b8]/10">
                                                            86,20%
                                                        </div>
                                                    </div>
                                                    <div class="grid grid-cols-2 gap-2">
                                                        <div
                                                            class="rounded-xl border border-white/10 bg-white/5 p-3 text-[10px] text-[#b9d6d0]">
                                                            Meta mensal
                                                            <div class="text-sm font-semibold text-[#fef6ea]">R$
                                                                2.400</div>
                                                        </div>
                                                        <div
                                                            class="rounded-xl border border-white/10 bg-white/5 p-3 text-[10px] text-[#b9d6d0]">
                                                            Meta alvo
                                                            <div class="text-sm font-semibold text-[#fef6ea]">R$
                                                                250.000</div>
                                                        </div>
                                                    </div>
                                                    <div class="mt-3 flex items-center justify-between text-xs">
                                                        <span class="text-[#b9d6d0]">Alocação atual:</span>
                                                        <span class="text-[#cfe3df] font-semibold">86,20%</span>
                                                    </div>
                                                </div>

                                                <div
                                                    class="bg-white/5 backdrop-blur-sm rounded-2xl p-4 border border-white/10">
                                                    <div class="flex items-center gap-3 mb-3">
                                                        <div
                                                            class="w-10 h-10 rounded-xl bg-[#3ec9c2]/10 border border-[#3ec9c2]/40 flex items-center justify-center">
                                                            <span class="text-[#3ec9c2] text-xs">▦</span>
                                                        </div>
                                                        <div class="flex-1">
                                                            <div class="text-sm font-bold text-[#fef6ea]">Crescimento
                                                            </div>
                                                            <div class="text-xs text-[#b9d6d0]">8 ativos</div>
                                                        </div>
                                                        <div
                                                            class="px-2 py-1 text-[10px] rounded-full border border-[#3ec9c2]/40 text-[#3ec9c2] bg-[#3ec9c2]/10">
                                                            62,40%
                                                        </div>
                                                    </div>
                                                    <div class="grid grid-cols-2 gap-2">
                                                        <div
                                                            class="rounded-xl border border-white/10 bg-white/5 p-3 text-[10px] text-[#b9d6d0]">
                                                            Meta mensal
                                                            <div class="text-sm font-semibold text-[#fef6ea]">R$
                                                                1.200</div>
                                                        </div>
                                                        <div
                                                            class="rounded-xl border border-white/10 bg-white/5 p-3 text-[10px] text-[#b9d6d0]">
                                                            Meta alvo
                                                            <div class="text-sm font-semibold text-[#fef6ea]">R$
                                                                180.000</div>
                                                        </div>
                                                    </div>
                                                    <div class="mt-3 flex items-center justify-between text-xs">
                                                        <span class="text-[#b9d6d0]">Alocação atual:</span>
                                                        <span class="text-[#cfe3df] font-semibold">62,40%</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Composition Screen -->
                                        <div v-if="currentScreenshot === 2" class="relative z-10 space-y-4">
                                            <div class="flex items-center justify-between">
                                                <div>
                                                    <p class="text-lg font-bold text-[#fef6ea]">Dividendos</p>
                                                    <p class="text-xs text-[#b9d6d0]">Composições da carteira</p>
                                                </div>
                                                <div class="flex gap-2">
                                                    <span
                                                        class="px-2 py-1 text-[10px] rounded-full border border-white/10 bg-white/5 text-[#b9d6d0]">+Ativos</span>
                                                    <span
                                                        class="px-2 py-1 text-[10px] rounded-full border border-white/10 bg-white/5 text-[#b9d6d0]">Comparar</span>
                                                </div>
                                            </div>

                                            <div class="flex items-center justify-between text-xs text-[#b9d6d0]">
                                                <span>Alocação total</span>
                                                <span class="text-[#fef6ea] font-semibold">92,40%</span>
                                            </div>
                                            <div
                                                class="flex items-center gap-2 rounded-xl border border-[#F59E0B]/40 bg-[#F59E0B]/10 px-3 py-2 text-xs text-[#F59E0B]">
                                                ⚠️ A soma das alocações é diferente de 100%.
                                            </div>

                                            <div class="flex flex-wrap gap-2">
                                                <div
                                                    class="px-3 py-1 rounded-full border border-[#17a2b8]/40 text-[10px] text-[#17a2b8] bg-[#17a2b8]/10">
                                                    Ações: 46,20%
                                                </div>
                                                <div
                                                    class="px-3 py-1 rounded-full border border-[#f7c873]/40 text-[10px] text-[#f7c873] bg-[#f7c873]/10">
                                                    FIIs: 31,40%
                                                </div>
                                                <div
                                                    class="px-3 py-1 rounded-full border border-[#3ec9c2]/40 text-[10px] text-[#3ec9c2] bg-[#3ec9c2]/10">
                                                    Renda fixa: 14,80%
                                                </div>
                                            </div>

                                            <div class="space-y-3">
                                                <div class="flex items-center justify-between text-xs text-[#b9d6d0]">
                                                    <div class="flex items-center gap-2">
                                                        <span
                                                            class="w-2.5 h-2.5 rounded-full bg-[#17a2b8] inline-block"></span>
                                                        <span class="uppercase tracking-[0.2em]">Ações</span>
                                                    </div>
                                                    <span class="text-[#17a2b8] font-semibold">46,20%</span>
                                                </div>
                                                <div
                                                    class="bg-white/5 backdrop-blur-sm rounded-2xl p-4 border border-[#17a2b8]/40">
                                                    <div class="flex items-center justify-between">
                                                        <div class="flex items-center gap-3">
                                                            <div
                                                                class="w-10 h-10 rounded-xl border border-[#17a2b8]/40 bg-[#17a2b8]/10 flex items-center justify-center text-xs text-[#17a2b8]">
                                                                PETR4
                                                            </div>
                                                            <div>
                                                                <div class="text-sm font-bold text-[#fef6ea]">PETR4
                                                                </div>
                                                                <div class="text-xs text-[#b9d6d0]">Energia</div>
                                                            </div>
                                                        </div>
                                                        <div class="text-right">
                                                            <div class="text-xs text-[#b9d6d0]">Percentual</div>
                                                            <div class="text-sm font-bold text-[#fef6ea]">12,00%</div>
                                                        </div>
                                                    </div>
                                                    <div class="mt-3 flex items-center justify-between text-xs">
                                                        <span class="text-[#b9d6d0]">Ajustar</span>
                                                        <div class="flex gap-2 text-[#b9d6d0]">
                                                            <span
                                                                class="px-2 py-1 rounded-full border border-white/10">✎</span>
                                                            <span
                                                                class="px-2 py-1 rounded-full border border-white/10">🗑</span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div
                                                    class="bg-white/5 backdrop-blur-sm rounded-2xl p-4 border border-[#f7c873]/40">
                                                    <div class="flex items-center justify-between">
                                                        <div class="flex items-center gap-3">
                                                            <div
                                                                class="w-10 h-10 rounded-xl border border-[#f7c873]/40 bg-[#f7c873]/10 flex items-center justify-center text-xs text-[#f7c873]">
                                                                MXRF11
                                                            </div>
                                                            <div>
                                                                <div class="text-sm font-bold text-[#fef6ea]">MXRF11
                                                                </div>
                                                                <div class="text-xs text-[#b9d6d0]">Fundos Imob.</div>
                                                            </div>
                                                        </div>
                                                        <div class="text-right">
                                                            <div class="text-xs text-[#b9d6d0]">Percentual</div>
                                                            <div class="text-sm font-bold text-[#fef6ea]">18,00%</div>
                                                        </div>
                                                    </div>
                                                    <div class="mt-3 flex items-center justify-between text-xs">
                                                        <span class="text-[#b9d6d0]">Ajustar</span>
                                                        <div class="flex gap-2 text-[#b9d6d0]">
                                                            <span
                                                                class="px-2 py-1 rounded-full border border-white/10">✎</span>
                                                            <span
                                                                class="px-2 py-1 rounded-full border border-white/10">🗑</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Crossing Screen -->
                                        <div v-if="currentScreenshot === 3" class="relative z-10 space-y-4">
                                            <div class="flex items-center justify-between">
                                                <div>
                                                    <p class="text-lg font-bold text-[#fef6ea]">Dividendos</p>
                                                    <p class="text-xs text-[#b9d6d0]">Portfólio ideal x posição atual
                                                    </p>
                                                </div>
                                                <div class="flex gap-2">
                                                    <span
                                                        class="px-2 py-1 text-[10px] rounded-full border border-white/10 bg-white/5 text-[#b9d6d0]">+Ativos</span>
                                                    <span
                                                        class="px-2 py-1 text-[10px] rounded-full border border-white/10 bg-white/5 text-[#b9d6d0]">Detalhes</span>
                                                </div>
                                            </div>

                                            <div
                                                class="rounded-2xl border border-white/10 bg-white/5 backdrop-blur-sm p-4">
                                                <div class="flex items-center justify-between">
                                                    <div>
                                                        <p class="text-sm font-semibold text-[#fef6ea]">Resumo da
                                                            comparação</p>
                                                        <p class="text-xs text-[#b9d6d0]">Distribuição ideal da carteira
                                                        </p>
                                                    </div>
                                                    <div class="text-right">
                                                        <p class="text-[10px] text-[#b9d6d0] uppercase">Distribuição
                                                            Atual</p>
                                                        <div class="flex items-center gap-2 justify-end">
                                                            <p class="text-sm font-bold text-[#fef6ea]">98,40%</p>
                                                            <span class="text-xs text-[#b9d6d0]">ⓘ</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="space-y-3">
                                                <div class="flex items-center gap-2 text-xs text-[#b9d6d0]">
                                                    <span
                                                        class="w-2.5 h-2.5 rounded-full bg-[#17a2b8] inline-block"></span>
                                                    <span class="uppercase tracking-[0.2em]">Ações</span>
                                                </div>
                                                <div
                                                    class="bg-white/5 backdrop-blur-sm rounded-2xl p-4 border border-white/10">
                                                    <div class="flex items-center justify-between mb-3">
                                                        <div class="flex items-center gap-2">
                                                            <div
                                                                class="w-8 h-8 rounded-lg bg-white/10 border border-white/10 flex items-center justify-center text-xs text-[#b9d6d0]">
                                                                📈
                                                            </div>
                                                            <div class="text-sm font-semibold text-[#fef6ea]">PETR4
                                                            </div>
                                                        </div>
                                                        <div
                                                            class="px-2 py-1 rounded-full text-[10px] border border-[#10B981]/40 text-[#10B981] bg-[#10B981]/10">
                                                            +11,20%
                                                        </div>
                                                    </div>
                                                    <div class="space-y-2 text-xs text-[#b9d6d0]">
                                                        <div class="flex justify-between">
                                                            <span>Investido</span>
                                                            <span class="text-[#fef6ea]">R$ 42.000</span>
                                                        </div>
                                                        <div class="flex justify-between">
                                                            <span>Comprado</span>
                                                            <span class="text-[#fef6ea]">340 cotas</span>
                                                        </div>
                                                        <div class="flex justify-between">
                                                            <span>P.M</span>
                                                            <span class="text-[#fef6ea]">R$ 31,50</span>
                                                        </div>
                                                    </div>
                                                    <div class="mt-3 flex items-center gap-2 text-xs">
                                                        <span class="text-[#b9d6d0]">R$ 38.900</span>
                                                        <span class="text-[#b9d6d0]">→</span>
                                                        <span class="text-[#b9d6d0]">R$ 42.500</span>
                                                    </div>
                                                    <div class="mt-2 h-2 bg-white/10 rounded-full overflow-hidden">
                                                        <div class="h-full bg-[#10B981] w-[92%]"></div>
                                                    </div>
                                                </div>

                                                <div
                                                    class="bg-white/5 backdrop-blur-sm rounded-2xl p-4 border border-white/10">
                                                    <div class="flex items-center justify-between mb-3">
                                                        <div class="flex items-center gap-2">
                                                            <div
                                                                class="w-8 h-8 rounded-lg bg-white/10 border border-white/10 flex items-center justify-center text-xs text-[#b9d6d0]">
                                                                🏢
                                                            </div>
                                                            <div class="text-sm font-semibold text-[#fef6ea]">HGLG11
                                                            </div>
                                                        </div>
                                                        <div
                                                            class="px-2 py-1 rounded-full text-[10px] border border-[#F59E0B]/40 text-[#F59E0B] bg-[#F59E0B]/10">
                                                            +7k
                                                        </div>
                                                    </div>
                                                    <div class="space-y-2 text-xs text-[#b9d6d0]">
                                                        <div class="flex justify-between">
                                                            <span>Meta</span>
                                                            <span class="text-[#fef6ea]">17,00%</span>
                                                        </div>
                                                        <div class="flex justify-between">
                                                            <span>A comprar</span>
                                                            <span class="text-[#fef6ea]">15 cotas</span>
                                                        </div>
                                                        <div class="flex justify-between">
                                                            <span>Cot. Atual</span>
                                                            <span class="text-[#fef6ea]">R$ 162,00</span>
                                                        </div>
                                                    </div>
                                                    <div class="mt-3 flex items-center gap-2 text-xs">
                                                        <span class="text-[#b9d6d0]">R$ 24.900</span>
                                                        <span class="text-[#b9d6d0]">→</span>
                                                        <span class="text-[#b9d6d0]">R$ 30.600</span>
                                                    </div>
                                                    <div class="mt-2 h-2 bg-white/10 rounded-full overflow-hidden">
                                                        <div class="h-full bg-[#F59E0B] w-[74%]"></div>
                                                    </div>
                                                    <div
                                                        class="mt-3 inline-flex items-center gap-2 text-[10px] px-2 py-1 rounded-full border border-[#F59E0B]/30 text-[#F59E0B] bg-[#F59E0B]/10">
                                                        Não posicionado
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Controles -->
                            <button @click="prevScreenshot"
                                class="absolute left-0 top-1/2 -translate-y-1/2 -translate-x-4 w-10 h-10 bg-white/10 backdrop-blur-sm rounded-full shadow-lg flex items-center justify-center hover:scale-110 transition-all border border-white/20">
                                <svg class="w-5 h-5 text-[#fef6ea]" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 19l-7-7 7-7" />
                                </svg>
                            </button>

                            <button @click="nextScreenshot"
                                class="absolute right-0 top-1/2 -translate-y-1/2 translate-x-4 w-10 h-10 bg-white/10 backdrop-blur-sm rounded-full shadow-lg flex items-center justify-center hover:scale-110 transition-all border border-white/20">
                                <svg class="w-5 h-5 text-[#fef6ea]" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        </div>

                        <!-- Dots -->
                        <div class="flex gap-2 justify-center mt-6">
                            <button v-for="(screenshot, index) in screenshots" :key="index"
                                @click="goToScreenshot(index)"
                                :class="['h-2 rounded-full transition-all', currentScreenshot === index ? 'w-8 bg-[#17a2b8]' : 'w-2 bg-white/20']">
                            </button>
                        </div>

                        <!-- Descrição -->
                        <div class="text-center mt-4">
                            <h3 class="text-base font-bold text-[#fef6ea] mb-1">{{ screenshots[currentScreenshot].title
                            }}
                            </h3>
                            <p class="text-sm text-[#cfe3df]">{{ screenshots[currentScreenshot].description }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section id="features" class="py-20 px-4 sm:px-6 lg:px-8 bg-white/5 backdrop-blur-sm border-y border-white/10">
            <div class="max-w-7xl mx-auto">
                <div class="text-center mb-16">
                    <h2 class="text-3xl sm:text-4xl font-bold text-[#fef6ea] mb-4">
                        Por que investidores escolhem o DataGrana?
                    </h2>
                    <p class="text-xl text-[#cfe3df] max-w-2xl mx-auto">
                        Controle total dos investimentos na palma da mão
                    </p>
                </div>

                <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-8">
                    <div v-for="feature in features" :key="feature.title"
                        class="group p-6 bg-white/5 backdrop-blur-sm rounded-xl border border-white/10 hover:border-[#17a2b8] hover:shadow-xl hover:shadow-[#17a2b8]/10 transition-all">
                        <div class="text-5xl mb-4">{{ feature.icon }}</div>
                        <h3 class="text-xl font-bold text-[#fef6ea] mb-2 group-hover:text-[#17a2b8] transition-colors">
                            {{ feature.title }}
                        </h3>
                        <p class="text-[#cfe3df]">{{ feature.description }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Pricing Section -->
        <section id="pricing" class="py-20 px-4 sm:px-6 lg:px-8 relative">
            <div class="max-w-7xl mx-auto">
                <div class="text-center mb-16">
                    <div
                        class="inline-flex items-center gap-2 px-4 py-2 bg-red-500/10 border border-red-500/30 rounded-full mb-6 backdrop-blur-sm">
                        <span class="text-red-400 text-sm font-semibold">⏰ Promoção de lançamento - 33% OFF nos 3
                            primeiros
                            meses</span>
                    </div>

                    <h2 class="text-3xl sm:text-4xl font-bold text-[#fef6ea] mb-4">
                        Comece grátis, escale quando precisar
                    </h2>
                    <p class="text-xl text-[#cfe3df] max-w-2xl mx-auto">
                        Sem truques, sem letras miúdas. Cancele quando quiser.
                    </p>
                </div>

                <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div v-for="plan in plans" :key="plan.name"
                        :class="['relative p-8 rounded-2xl border-2 transition-all hover:scale-105', plan.highlight ? 'bg-linear-to-br from-[#17a2b8] to-[#0d5f5f] border-[#17a2b8] shadow-2xl shadow-[#17a2b8]/30 scale-105 text-white' : 'bg-white/5 backdrop-blur-sm border-white/10 hover:border-[#17a2b8]']">

                        <div v-if="plan.badge"
                            :class="['absolute -top-4 left-1/2 -translate-x-1/2 px-4 py-1 rounded-full text-xs font-bold whitespace-nowrap', plan.highlight ? 'bg-amber-400 text-gray-900' : 'bg-[#17a2b8] text-white']">
                            {{ plan.badge }}
                        </div>

                        <h3 :class="['text-2xl font-bold mb-2', plan.highlight ? 'text-white' : 'text-[#fef6ea]']">{{
                            plan.name }}</h3>
                        <p :class="['text-sm mb-4', plan.highlight ? 'text-white/80' : 'text-[#cfe3df]']">{{
                            plan.description }}</p>

                        <div class="mb-6">
                            <div class="flex items-baseline gap-1">
                                <span :class="['text-4xl font-bold', plan.highlight ? 'text-white' : 'text-[#fef6ea]']">
                                    R$ {{ plan.price.toFixed(2).replace('.', ',') }}
                                </span>
                                <span :class="['text-lg', plan.highlight ? 'text-white/80' : 'text-[#b9d6d0]']">{{
                                    plan.period }}</span>
                            </div>
                            <div v-if="plan.price > 0"
                                :class="['text-sm mt-1', plan.highlight ? 'text-white/70' : 'text-[#b9d6d0]']">
                                <span class="line-through">R$ {{ (plan.price * 1.5).toFixed(2).replace('.', ',')
                                }}</span>
                                <span class="ml-2 font-semibold text-[#10B981]">33% OFF</span>
                            </div>
                        </div>

                        <ul class="space-y-3 mb-8">
                            <li v-for="feature in plan.features" :key="feature" class="flex items-start gap-2">
                                <svg class="w-5 h-5 mt-0.5 flex-shrink-0"
                                    :class="plan.highlight ? 'text-white' : 'text-[#17a2b8]'" fill="currentColor"
                                    viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                        clip-rule="evenodd" />
                                </svg>
                                <span :class="plan.highlight ? 'text-white' : 'text-[#cfe3df]'">{{ feature }}</span>
                            </li>
                            <li v-for="feature in plan.excludedFeatures" :key="'excluded-' + feature"
                                class="flex items-start gap-2">
                                <svg class="w-5 h-5 mt-0.5 flex-shrink-0 text-[#EF4444]/50" fill="currentColor"
                                    viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                        clip-rule="evenodd" />
                                </svg>
                                <span
                                    :class="plan.highlight ? 'text-white/50 line-through' : 'text-[#cfe3df]/50 line-through'">{{
                                        feature }}</span>
                            </li>
                        </ul>

                        <a href="https://play.google.com/store" target="_blank" rel="noopener"
                            :class="['block w-full py-3 rounded-lg font-bold text-center transition-all', plan.highlight ? 'bg-white text-[#17a2b8] hover:bg-gray-100' : 'bg-linear-to-r from-[#17a2b8] to-[#0d5f5f] text-white hover:shadow-lg hover:shadow-[#17a2b8]/30']">
                            {{ plan.price === 0 ? 'Baixar Grátis' : 'Assinar no App' }}
                        </a>
                    </div>
                </div>

                <div class="mt-12 text-center">
                    <div
                        class="inline-flex items-center gap-3 px-6 py-4 bg-[#10B981]/10 border-2 border-[#10B981]/30 rounded-xl backdrop-blur-sm">
                        <svg class="w-8 h-8 text-[#10B981]" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                clip-rule="evenodd" />
                        </svg>
                        <div class="text-left">
                            <div class="font-bold text-[#10B981]">Garantia de 7 dias</div>
                            <div class="text-sm text-[#10B981]/80">Reembolso total, sem perguntas</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Testimonials -->
        <section id="testimonials"
            class="py-20 px-4 sm:px-6 lg:px-8 bg-white/5 backdrop-blur-sm border-y border-white/10">
            <div class="max-w-7xl mx-auto">
                <div class="text-center mb-16">
                    <h2 class="text-3xl sm:text-4xl font-bold text-[#fef6ea] mb-4">
                        O que investidores estão dizendo
                    </h2>
                    <p class="text-xl text-[#cfe3df]">
                        Milhares de investidores já transformaram sua gestão financeira
                    </p>
                </div>

                <div class="grid md:grid-cols-3 gap-8">
                    <div v-for="testimonial in testimonials" :key="testimonial.name"
                        class="p-6 bg-white/5 backdrop-blur-sm rounded-xl border border-white/10 hover:border-[#17a2b8] hover:shadow-xl hover:shadow-[#17a2b8]/10 transition-all">
                        <div class="flex gap-1 mb-4">
                            <svg v-for="i in 5" :key="i" class="w-5 h-5 text-[#F59E0B]" fill="currentColor"
                                viewBox="0 0 20 20">
                                <path
                                    d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                            </svg>
                        </div>

                        <p class="text-[#cfe3df] mb-6 italic">"{{ testimonial.text }}"</p>

                        <div class="flex items-center gap-3">
                            <div
                                class="w-12 h-12 bg-linear-to-br from-[#17a2b8] to-[#0d5f5f] rounded-full flex items-center justify-center text-white font-bold">
                                {{ testimonial.avatar }}
                            </div>
                            <div>
                                <div class="font-bold text-[#fef6ea]">{{ testimonial.name }}</div>
                                <div class="text-sm text-[#b9d6d0]">{{ testimonial.role }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Final -->
        <section class="py-20 px-4 sm:px-6 lg:px-8 relative">
            <div class="absolute inset-0 bg-linear-to-r from-[#17a2b8] to-[#0d5f5f] opacity-10"></div>
            <div class="max-w-4xl mx-auto text-center relative z-10">
                <h2 class="text-3xl sm:text-4xl font-bold text-[#fef6ea] mb-6">
                    Baixe agora e comece grátis
                </h2>
                <p class="text-xl mb-6 text-[#cfe3df]">
                    Junte-se a mais de 12.000 investidores que já estão tomando decisões mais assertivas
                </p>

                <div
                    class="inline-flex items-center gap-2 px-4 py-2 bg-white/10 backdrop-blur-sm rounded-full mb-8 border border-white/20">
                    <span class="w-2 h-2 bg-amber-400 rounded-full animate-pulse"></span>
                    <span class="text-sm font-semibold text-amber-400">⚡ Últimas 47 vagas com 33% OFF</span>
                </div>

                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="https://play.google.com/store" target="_blank" rel="noopener"
                        class="inline-flex items-center gap-3 px-8 py-4 bg-white text-gray-900 rounded-xl font-bold text-lg hover:scale-105 transition-all shadow-xl">
                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor">
                            <path
                                d="M3,20.5V3.5C3,2.91 3.34,2.39 3.84,2.15L13.69,12L3.84,21.85C3.34,21.6 3,21.09 3,20.5M16.81,15.12L6.05,21.34L14.54,12.85L16.81,15.12M20.16,10.81C20.5,11.08 20.75,11.5 20.75,12C20.75,12.5 20.53,12.9 20.18,13.18L17.89,14.5L15.39,12L17.89,9.5L20.16,10.81M6.05,2.66L16.81,8.88L14.54,11.15L6.05,2.66Z" />
                        </svg>
                        <span>Baixar na Google Play</span>
                    </a>

                    <div
                        class="inline-flex items-center gap-3 px-8 py-4 bg-white/10 text-[#cfe3df] rounded-xl font-bold text-lg cursor-not-allowed backdrop-blur-sm border border-white/20">
                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor">
                            <path
                                d="M18.71,19.5C17.88,20.74 17,21.95 15.66,21.97C14.32,22 13.89,21.18 12.37,21.18C10.84,21.18 10.37,21.95 9.1,22C7.79,22.05 6.8,20.68 5.96,19.47C4.25,17 2.94,12.45 4.7,9.39C5.57,7.87 7.13,6.91 8.82,6.88C10.1,6.86 11.32,7.75 12.11,7.75C12.89,7.75 14.37,6.68 15.92,6.84C16.57,6.87 18.39,7.1 19.56,8.82C19.47,8.88 17.39,10.1 17.41,12.63C17.44,15.65 20.06,16.66 20.09,16.67C20.06,16.74 19.67,18.11 18.71,19.5M13,3.5C13.73,2.67 14.94,2.04 15.94,2C16.07,3.17 15.6,4.35 14.9,5.19C14.21,6.04 13.07,6.7 11.95,6.61C11.8,5.46 12.36,4.26 13,3.5Z" />
                        </svg>
                        <span>Em breve na App Store</span>
                    </div>
                </div>

                <p class="mt-6 text-sm text-[#b9d6d0]">
                    Baixe grátis • Teste por 7 dias • Cancele quando quiser
                </p>
            </div>
        </section>

        <!-- Footer -->
        <footer class="py-12 px-4 sm:px-6 lg:px-8 bg-[#0b1216] border-t border-white/10">
            <div class="max-w-7xl mx-auto">
                <div class="grid md:grid-cols-4 gap-8 mb-8">
                    <div class="md:col-span-2">
                        <div class="flex items-center gap-3 mb-4">
                            <img src="/images/icon-transparent.png" alt="DataGrana" class="w-10 h-10" />
                            <div>
                                <span class="text-lg font-bold text-[#fef6ea]">Datagrana App</span>
                                <p class="text-xs text-[#b9d6d0]">Carteiras reais, decisões claras.</p>
                            </div>
                        </div>
                        <p class="text-sm text-[#b9d6d0]">
                            Aplicativo mobile para gestão completa de investimentos
                        </p>
                    </div>

                    <div>
                        <h3 class="font-bold text-[#fef6ea] mb-4">App</h3>
                        <ul class="space-y-2 text-sm">
                            <li><a href="#features"
                                    class="text-[#cfe3df] hover:text-[#17a2b8] transition-colors">Recursos</a></li>
                            <li><a href="#pricing"
                                    class="text-[#cfe3df] hover:text-[#17a2b8] transition-colors">Preços</a>
                            </li>
                            <li><a href="#testimonials"
                                    class="text-[#cfe3df] hover:text-[#17a2b8] transition-colors">Depoimentos</a></li>
                        </ul>
                    </div>

                    <div>
                        <h3 class="font-bold text-[#fef6ea] mb-4">Empresa</h3>
                        <ul class="space-y-2 text-sm">
                            <li><a href="#" class="text-[#cfe3df] hover:text-[#17a2b8] transition-colors">Sobre</a></li>
                            <li><a href="#" class="text-[#cfe3df] hover:text-[#17a2b8] transition-colors">Blog</a></li>
                            <li><a href="#" class="text-[#cfe3df] hover:text-[#17a2b8] transition-colors">Contato</a>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="pt-8 border-t border-white/10 flex flex-col md:flex-row justify-between items-center gap-4">
                    <p class="text-sm text-[#b9d6d0]">© 2024 DataGrana. Todos os direitos reservados.</p>
                    <div class="flex gap-6 text-sm">
                        <a href="#" class="text-[#cfe3df] hover:text-[#17a2b8] transition-colors">Privacidade</a>
                        <a href="#" class="text-[#cfe3df] hover:text-[#17a2b8] transition-colors">Termos</a>
                        <a href="#" class="text-[#cfe3df] hover:text-[#17a2b8] transition-colors">Cookies</a>
                    </div>
                </div>
            </div>
        </footer>
    </div>
</template>

<style scoped>
@keyframes float {

    0%,
    100% {
        transform: translateY(0px);
    }

    50% {
        transform: translateY(-10px);
    }
}
</style>
