<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orgânico Brasil - Produção Orgânica e Agricultura Familiar</title>
    <link rel="stylesheet" href="landing/inter.css">
    <script src="landing/tailwind.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        
        .hero-bg {
            background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.4)), 
                        url('landing/img/hero.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
        }
        
        .section-bg-1 {
            background: linear-gradient(rgba(255,255,255,0.95), rgba(255,255,255,0.95)), 
                        url('landing/img/sobre.jpg');
            background-size: cover;
            background-position: center;
        }
        
        .section-bg-2 {
            background: linear-gradient(rgba(34, 197, 94, 0.9), rgba(34, 197, 94, 0.9)), 
                        url('landing/img/producao.jpg');
            background-size: cover;
            background-position: center;
        }
        
        .section-bg-3 {
            background: linear-gradient(rgba(255,255,255,0.95), rgba(255,255,255,0.95)), 
                        url('landing/img/comissoes.jpg');
            background-size: cover;
            background-position: center;
        }
        
        .card-hover {
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }
        
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #b91c1c 0%, #991b1b 100%);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(220, 38, 38, 0.3);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
            transition: all 0.3s ease;
        }
        
        .btn-secondary:hover {
            background: linear-gradient(135deg, #15803d 0%, #166534 100%);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(22, 163, 74, 0.3);
        }
        
        .fade-in {
            animation: fadeIn 1s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .commission-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .stats-bg {
            background: linear-gradient(rgba(22, 163, 74, 0.9), rgba(21, 128, 61, 0.9)), 
                        url('landing/img/stats.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php if (isset($_GET['acesso']) && $_GET['acesso'] === 'negado'): ?>
        <div class="border-b border-red-200 bg-red-50 text-red-900">
            <div class="container mx-auto flex flex-wrap items-center gap-3 px-6 py-3 text-sm">
                <span><strong>Acesso não autorizado.</strong> Esta área não corresponde ao perfil da conta informada.</span>
                <a href="#topo" class="ml-auto rounded-full bg-red-700 px-3 py-1 font-semibold text-white hover:bg-red-800">Escolher o portal correto</a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Header -->
    <header id="topo" class="bg-white/95 backdrop-blur-md shadow-lg sticky top-0 z-50">
        <nav class="container mx-auto px-6 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-12 h-12 bg-green-600 rounded-full flex items-center justify-center">
                        <span class="text-white font-bold text-xl">🌿</span>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-green-800">Orgânico Brasil</h1>
                        <p class="text-xs text-gray-600">Ministério da Agricultura</p>
                    </div>
                </div>
                
                <div class="hidden flex-wrap items-center justify-end gap-3 md:flex">
                    <a href="#sobre" class="text-sm text-gray-700 hover:text-green-600 font-medium transition-colors">Sobre Nós</a>
                    <a href="#producao" class="text-sm text-gray-700 hover:text-green-600 font-medium transition-colors">Produção Orgânica</a>
                    <a href="#comissoes" class="text-sm text-gray-700 hover:text-green-600 font-medium transition-colors">Comissões</a>
                    <span class="mx-1 hidden h-6 w-px bg-gray-200 lg:block" aria-hidden="true"></span>
                    <a href="assoc/login.php" class="rounded-full border border-green-600 px-2.5 py-1.5 text-xs font-semibold text-green-700 transition-colors hover:bg-green-50">Área do Associado</a>
                    <a href="social/login.php" class="rounded-full border border-green-600 px-2.5 py-1.5 text-xs font-semibold text-green-700 transition-colors hover:bg-green-50">Área do Técnico</a>
                    <a href="producao/login.php" class="rounded-full border border-green-600 px-2.5 py-1.5 text-xs font-semibold text-green-700 transition-colors hover:bg-green-50">Área do Agricultor</a>
                    <a href="login" title="Área do Administrador" aria-label="Área do Administrador"
                       class="rounded-full bg-amber-300 px-2.5 py-1.5 text-xs font-semibold text-green-950 transition-colors hover:bg-amber-200">🔐</a>
                </div>
                
                <!-- Mobile menu button -->
                <button class="md:hidden text-gray-700" onclick="toggleMobileMenu()">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
            </div>
            
            <!-- Mobile menu -->
            <div id="mobileMenu" class="hidden md:hidden mt-4 pb-4">
                <a href="#sobre" class="block py-2 text-gray-700 hover:text-green-600">Sobre Nós</a>
                <a href="#producao" class="block py-2 text-gray-700 hover:text-green-600">Produção Orgânica</a>
                <a href="#comissoes" class="block py-2 text-gray-700 hover:text-green-600">Comissões</a>
                <div class="mt-3 flex flex-col gap-2">
                    <a href="assoc/login.php" class="rounded-full border border-green-600 px-4 py-2 text-center text-sm font-semibold text-green-700">Área do Associado</a>
                    <a href="social/login.php" class="rounded-full border border-green-600 px-4 py-2 text-center text-sm font-semibold text-green-700">Área do Técnico</a>
                    <a href="producao/login.php" class="rounded-full border border-green-600 px-4 py-2 text-center text-sm font-semibold text-green-700">Área do Agricultor</a>
                    <a href="login" class="rounded-full bg-amber-300 px-4 py-2 text-center text-sm font-semibold text-green-950">🔐 Área do Administrador</a>
                </div>
            </div>
        </nav>
    </header>

    <!-- Hero Section -->
    <section class="hero-bg text-white py-24 min-h-screen flex items-center">
        <div class="container mx-auto px-6">
            <div class="max-w-4xl mx-auto text-center fade-in">
                <h2 class="text-6xl font-bold mb-8 leading-tight">
                    Produção Orgânica <br>
                    <span class="text-green-300">do Brasil</span>
                </h2>
                <p class="text-2xl mb-12 text-gray-100 leading-relaxed max-w-3xl mx-auto">
                    Fortalecendo a agricultura familiar através da produção orgânica certificada. 
                    Apoiamos pequenos produtores na transição para sistemas sustentáveis de cultivo, 
                    livres de agrotóxicos e respeitosos com o meio ambiente.
                </p>
                <div class="flex flex-col sm:flex-row gap-6 justify-center">
                    <button onclick="document.getElementById('comissoes').scrollIntoView({behavior:'smooth'})" class="btn-secondary text-white px-10 py-5 rounded-full font-semibold text-xl">
                        Conheça as Comissões
                    </button>
                    <a href="#comissoes" class="bg-white text-green-700 px-10 py-5 rounded-full font-semibold text-xl hover:bg-gray-100 transition-colors inline-block text-center">
                        Como se certificar
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-bg text-white py-20">
        <div class="container mx-auto px-6">
            <div class="grid md:grid-cols-4 gap-8 text-center">
                <div class="fade-in">
                    <div class="text-5xl font-bold mb-2">27</div>
                    <p class="text-xl text-green-100">Comissões Estaduais</p>
                </div>
                <div class="fade-in">
                    <div class="text-5xl font-bold mb-2">15.000+</div>
                    <p class="text-xl text-green-100">Produtores Certificados</p>
                </div>
                <div class="fade-in">
                    <div class="text-5xl font-bold mb-2">2.5M</div>
                    <p class="text-xl text-green-100">Hectares Orgânicos</p>
                </div>
                <div class="fade-in">
                    <div class="text-5xl font-bold mb-2">R$ 5.8B</div>
                    <p class="text-xl text-green-100">Mercado Orgânico</p>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="sobre" class="section-bg-1 py-24">
        <div class="container mx-auto px-6">
            <div class="max-w-4xl mx-auto text-center mb-16">
                <h3 class="text-5xl font-bold text-gray-800 mb-8">A Importância da Produção Orgânica</h3>
                <p class="text-xl text-gray-700 leading-relaxed">
                    A produção orgânica no Brasil representa uma revolução silenciosa na agricultura, 
                    promovendo práticas sustentáveis que beneficiam produtores, consumidores e o meio ambiente. 
                    Através das Comissões da Produção Orgânica, garantimos qualidade, transparência e 
                    desenvolvimento rural sustentável.
                </p>
            </div>
            
            <div class="grid md:grid-cols-3 gap-10">
                <div class="card-hover bg-white/90 p-10 rounded-3xl text-center shadow-xl">
                    <div class="w-20 h-20 bg-green-600 rounded-full flex items-center justify-center mx-auto mb-8">
                        <span class="text-3xl">🌱</span>
                    </div>
                    <h4 class="text-2xl font-semibold text-gray-800 mb-6">Sustentabilidade</h4>
                    <p class="text-gray-600 text-lg leading-relaxed">
                        Práticas agrícolas que preservam o solo, a água e a biodiversidade, 
                        garantindo a produção de alimentos para as futuras gerações.
                    </p>
                </div>
                
                <div class="card-hover bg-white/90 p-10 rounded-3xl text-center shadow-xl">
                    <div class="w-20 h-20 bg-blue-600 rounded-full flex items-center justify-center mx-auto mb-8">
                        <span class="text-3xl">🏥</span>
                    </div>
                    <h4 class="text-2xl font-semibold text-gray-800 mb-6">Saúde Pública</h4>
                    <p class="text-gray-600 text-lg leading-relaxed">
                        Alimentos livres de agrotóxicos e aditivos químicos, contribuindo 
                        para a saúde da população e redução de doenças relacionadas à alimentação.
                    </p>
                </div>
                
                <div class="card-hover bg-white/90 p-10 rounded-3xl text-center shadow-xl">
                    <div class="w-20 h-20 bg-yellow-600 rounded-full flex items-center justify-center mx-auto mb-8">
                        <span class="text-3xl">💰</span>
                    </div>
                    <h4 class="text-2xl font-semibold text-gray-800 mb-6">Desenvolvimento Rural</h4>
                    <p class="text-gray-600 text-lg leading-relaxed">
                        Geração de renda para pequenos produtores, fortalecimento da agricultura 
                        familiar e fixação do homem no campo.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Production Section -->
    <section id="producao" class="section-bg-2 text-white py-24">
        <div class="container mx-auto px-6">
            <div class="grid md:grid-cols-2 gap-16 items-center">
                <div>
                    <h3 class="text-5xl font-bold mb-8">Sistema Brasileiro de Avaliação da Conformidade Orgânica</h3>
                    <p class="text-xl mb-8 text-green-100 leading-relaxed">
                        O Brasil possui um dos sistemas de certificação orgânica mais robustos do mundo, 
                        garantindo a qualidade e procedência dos produtos orgânicos através de três 
                        mecanismos de controle reconhecidos pelo Ministério da Agricultura.
                    </p>
                    
                    <div class="space-y-6">
                        <div class="flex items-start space-x-6">
                            <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center flex-shrink-0">
                                <span class="text-green-600 text-xl font-bold">1</span>
                            </div>
                            <div>
                                <h5 class="font-semibold text-xl mb-2">Certificação por Auditoria</h5>
                                <p class="text-green-100 text-lg">Organismos de avaliação da conformidade credenciados pelo INMETRO</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start space-x-6">
                            <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center flex-shrink-0">
                                <span class="text-green-600 text-xl font-bold">2</span>
                            </div>
                            <div>
                                <h5 class="font-semibold text-xl mb-2">Sistemas Participativos de Garantia</h5>
                                <p class="text-green-100 text-lg">Controle social com participação direta de produtores e consumidores</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start space-x-6">
                            <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center flex-shrink-0">
                                <span class="text-green-600 text-xl font-bold">3</span>
                            </div>
                            <div>
                                <h5 class="font-semibold text-xl mb-2">Controle Social na Venda Direta</h5>
                                <p class="text-green-100 text-lg">Para agricultura familiar em feiras, mercados e vendas diretas</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="commission-card p-10 rounded-3xl shadow-2xl">
                    <h4 class="text-3xl font-semibold text-gray-800 mb-8 text-center">Benefícios da Certificação</h4>
                    <div class="space-y-6">
                        <div class="flex items-center space-x-4">
                            <div class="w-8 h-8 bg-green-600 rounded-full flex items-center justify-center">
                                <span class="text-white text-sm">✓</span>
                            </div>
                            <span class="text-gray-700 text-lg">Acesso a mercados premium</span>
                        </div>
                        <div class="flex items-center space-x-4">
                            <div class="w-8 h-8 bg-green-600 rounded-full flex items-center justify-center">
                                <span class="text-white text-sm">✓</span>
                            </div>
                            <span class="text-gray-700 text-lg">Preços diferenciados</span>
                        </div>
                        <div class="flex items-center space-x-4">
                            <div class="w-8 h-8 bg-green-600 rounded-full flex items-center justify-center">
                                <span class="text-white text-sm">✓</span>
                            </div>
                            <span class="text-gray-700 text-lg">Credibilidade junto aos consumidores</span>
                        </div>
                        <div class="flex items-center space-x-4">
                            <div class="w-8 h-8 bg-green-600 rounded-full flex items-center justify-center">
                                <span class="text-white text-sm">✓</span>
                            </div>
                            <span class="text-gray-700 text-lg">Acesso a programas governamentais</span>
                        </div>
                        <div class="flex items-center space-x-4">
                            <div class="w-8 h-8 bg-green-600 rounded-full flex items-center justify-center">
                                <span class="text-white text-sm">✓</span>
                            </div>
                            <span class="text-gray-700 text-lg">Exportação facilitada</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Commissions Section -->
    <section id="comissoes" class="section-bg-3 py-24">
        <div class="container mx-auto px-6">
            <div class="text-center mb-16">
                <h3 class="text-5xl font-bold text-gray-800 mb-8">Comissões da Produção Orgânica</h3>
                <p class="text-xl text-gray-700 max-w-4xl mx-auto leading-relaxed">
                    As Comissões da Produção Orgânica (CPOrgs) são instâncias colegiadas de caráter consultivo, 
                    vinculadas ao Ministério da Agricultura, responsáveis por assessorar, avaliar e propor 
                    medidas para o desenvolvimento da agricultura orgânica em cada estado brasileiro.
                </p>
            </div>
            
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8 mb-16">
                <div class="commission-card p-8 rounded-2xl shadow-lg card-hover">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-green-600 rounded-full flex items-center justify-center mr-4">
                            <span class="text-white font-bold">AC</span>
                        </div>
                        <h4 class="text-xl font-semibold text-gray-800">CPOrg Acre</h4>
                    </div>
                    <p class="text-gray-600">Comissão da Produção Orgânica do Estado do Acre</p>
                </div>
                
                <div class="commission-card p-8 rounded-2xl shadow-lg card-hover">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-blue-600 rounded-full flex items-center justify-center mr-4">
                            <span class="text-white font-bold">AL</span>
                        </div>
                        <h4 class="text-xl font-semibold text-gray-800">CPOrg Alagoas</h4>
                    </div>
                    <p class="text-gray-600">Comissão da Produção Orgânica do Estado de Alagoas</p>
                </div>
                
                <div class="commission-card p-8 rounded-2xl shadow-lg card-hover">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-yellow-600 rounded-full flex items-center justify-center mr-4">
                            <span class="text-white font-bold">AP</span>
                        </div>
                        <h4 class="text-xl font-semibold text-gray-800">CPOrg Amapá</h4>
                    </div>
                    <p class="text-gray-600">Comissão da Produção Orgânica do Estado do Amapá</p>
                </div>
                
                <div class="commission-card p-8 rounded-2xl shadow-lg card-hover">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-red-600 rounded-full flex items-center justify-center mr-4">
                            <span class="text-white font-bold">AM</span>
                        </div>
                        <h4 class="text-xl font-semibold text-gray-800">CPOrg Amazonas</h4>
                    </div>
                    <p class="text-gray-600">Comissão da Produção Orgânica do Estado do Amazonas</p>
                </div>
                
                <div class="commission-card p-8 rounded-2xl shadow-lg card-hover">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-purple-600 rounded-full flex items-center justify-center mr-4">
                            <span class="text-white font-bold">BA</span>
                        </div>
                        <h4 class="text-xl font-semibold text-gray-800">CPOrg Bahia</h4>
                    </div>
                    <p class="text-gray-600">Comissão da Produção Orgânica do Estado da Bahia</p>
                </div>
                
                <div class="commission-card p-8 rounded-2xl shadow-lg card-hover">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-indigo-600 rounded-full flex items-center justify-center mr-4">
                            <span class="text-white font-bold">CE</span>
                        </div>
                        <h4 class="text-xl font-semibold text-gray-800">CPOrg Ceará</h4>
                    </div>
                    <p class="text-gray-600">Comissão da Produção Orgânica do Estado do Ceará</p>
                </div>
                
                <div class="commission-card p-8 rounded-2xl shadow-lg card-hover">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-pink-600 rounded-full flex items-center justify-center mr-4">
                            <span class="text-white font-bold">DF</span>
                        </div>
                        <h4 class="text-xl font-semibold text-gray-800">CPOrg Distrito Federal</h4>
                    </div>
                    <p class="text-gray-600">Comissão da Produção Orgânica do Distrito Federal</p>
                </div>
                
                <div class="commission-card p-8 rounded-2xl shadow-lg card-hover">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-teal-600 rounded-full flex items-center justify-center mr-4">
                            <span class="text-white font-bold">ES</span>
                        </div>
                        <h4 class="text-xl font-semibold text-gray-800">CPOrg Espírito Santo</h4>
                    </div>
                    <p class="text-gray-600">Comissão da Produção Orgânica do Estado do Espírito Santo</p>
                </div>
                
                <div class="commission-card p-8 rounded-2xl shadow-lg card-hover">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-orange-600 rounded-full flex items-center justify-center mr-4">
                            <span class="text-white font-bold">GO</span>
                        </div>
                        <h4 class="text-xl font-semibold text-gray-800">CPOrg Goiás</h4>
                    </div>
                    <p class="text-gray-600">Comissão da Produção Orgânica do Estado de Goiás</p>
                </div>
                
                <div class="commission-card p-8 rounded-2xl shadow-lg card-hover">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-cyan-600 rounded-full flex items-center justify-center mr-4">
                            <span class="text-white font-bold">MA</span>
                        </div>
                        <h4 class="text-xl font-semibold text-gray-800">CPOrg Maranhão</h4>
                    </div>
                    <p class="text-gray-600">Comissão da Produção Orgânica do Estado do Maranhão</p>
                </div>
                
                <div class="commission-card p-8 rounded-2xl shadow-lg card-hover">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-lime-600 rounded-full flex items-center justify-center mr-4">
                            <span class="text-white font-bold">MT</span>
                        </div>
                        <h4 class="text-xl font-semibold text-gray-800">CPOrg Mato Grosso</h4>
                    </div>
                    <p class="text-gray-600">Comissão da Produção Orgânica do Estado de Mato Grosso</p>
                </div>
                
                <div class="commission-card p-8 rounded-2xl shadow-lg card-hover">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-emerald-600 rounded-full flex items-center justify-center mr-4">
                            <span class="text-white font-bold">MS</span>
                        </div>
                        <h4 class="text-xl font-semibold text-gray-800">CPOrg Mato Grosso do Sul</h4>
                    </div>
                    <p class="text-gray-600">Comissão da Produção Orgânica do Estado de Mato Grosso do Sul</p>
                </div>
                
                <div class="commission-card p-8 rounded-2xl shadow-lg card-hover">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-violet-600 rounded-full flex items-center justify-center mr-4">
                            <span class="text-white font-bold">MG</span>
                        </div>
                        <h4 class="text-xl font-semibold text-gray-800">CPOrg Minas Gerais</h4>
                    </div>
                    <p class="text-gray-600">Comissão da Produção Orgânica do Estado de Minas Gerais</p>
                </div>
                
                <div class="commission-card p-8 rounded-2xl shadow-lg card-hover">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-rose-600 rounded-full flex items-center justify-center mr-4">
                            <span class="text-white font-bold">PA</span>
                        </div>
                        <h4 class="text-xl font-semibold text-gray-800">CPOrg Pará</h4>
                    </div>
                    <p class="text-gray-600">Comissão da Produção Orgânica do Estado do Pará</p>
                </div>
                
                <div class="commission-card p-8 rounded-2xl shadow-lg card-hover">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-slate-600 rounded-full flex items-center justify-center mr-4">
                            <span class="text-white font-bold">PB</span>
                        </div>
                        <h4 class="text-xl font-semibold text-gray-800">CPOrg Paraíba</h4>
                    </div>
                    <p class="text-gray-600">Comissão da Produção Orgânica do Estado da Paraíba</p>
                </div>
                
                <div class="commission-card p-8 rounded-2xl shadow-lg card-hover">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-green-700 rounded-full flex items-center justify-center mr-4">
                            <span class="text-white font-bold">PR</span>
                        </div>
                        <h4 class="text-xl font-semibold text-gray-800">CPOrg Paraná</h4>
                    </div>
                    <p class="text-gray-600">Comissão da Produção Orgânica do Estado do Paraná</p>
                </div>
                
                <div class="commission-card p-8 rounded-2xl shadow-lg card-hover">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-blue-700 rounded-full flex items-center justify-center mr-4">
                            <span class="text-white font-bold">PE</span>
                        </div>
                        <h4 class="text-xl font-semibold text-gray-800">CPOrg Pernambuco</h4>
                    </div>
                    <p class="text-gray-600">Comissão da Produção Orgânica do Estado de Pernambuco</p>
                </div>
                
                <div class="commission-card p-8 rounded-2xl shadow-lg card-hover">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-yellow-700 rounded-full flex items-center justify-center mr-4">
                            <span class="text-white font-bold">PI</span>
                        </div>
                        <h4 class="text-xl font-semibold text-gray-800">CPOrg Piauí</h4>
                    </div>
                    <p class="text-gray-600">Comissão da Produção Orgânica do Estado do Piauí</p>
                </div>
                
                <div class="commission-card p-8 rounded-2xl shadow-lg card-hover">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-red-700 rounded-full flex items-center justify-center mr-4">
                            <span class="text-white font-bold">RJ</span>
                        </div>
                        <h4 class="text-xl font-semibold text-gray-800">CPOrg Rio de Janeiro</h4>
                    </div>
                    <p class="text-gray-600">Comissão da Produção Orgânica do Estado do Rio de Janeiro</p>
                </div>
                
                <div class="commission-card p-8 rounded-2xl shadow-lg card-hover">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-purple-700 rounded-full flex items-center justify-center mr-4">
                            <span class="text-white font-bold">RN</span>
                        </div>
                        <h4 class="text-xl font-semibold text-gray-800">CPOrg Rio Grande do Norte</h4>
                    </div>
                    <p class="text-gray-600">Comissão da Produção Orgânica do Estado do Rio Grande do Norte</p>
                </div>
                
                <div class="commission-card p-8 rounded-2xl shadow-lg card-hover">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-indigo-700 rounded-full flex items-center justify-center mr-4">
                            <span class="text-white font-bold">RS</span>
                        </div>
                        <h4 class="text-xl font-semibold text-gray-800">CPOrg Rio Grande do Sul</h4>
                    </div>
                    <p class="text-gray-600 mb-4">Comissão da Produção Orgânica do Estado do Rio Grande do Sul</p>
                    <a href="https://www.agricultura.rs.gov.br/organicos" target="_blank" class="text-green-600 hover:text-green-800 font-medium text-sm flex items-center">
                        🌐 Acessar site oficial
                        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                        </svg>
                    </a>
                </div>
                
                <div class="commission-card p-8 rounded-2xl shadow-lg card-hover">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-pink-700 rounded-full flex items-center justify-center mr-4">
                            <span class="text-white font-bold">RO</span>
                        </div>
                        <h4 class="text-xl font-semibold text-gray-800">CPOrg Rondônia</h4>
                    </div>
                    <p class="text-gray-600">Comissão da Produção Orgânica do Estado de Rondônia</p>
                </div>
                
                <div class="commission-card p-8 rounded-2xl shadow-lg card-hover">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-teal-700 rounded-full flex items-center justify-center mr-4">
                            <span class="text-white font-bold">RR</span>
                        </div>
                        <h4 class="text-xl font-semibold text-gray-800">CPOrg Roraima</h4>
                    </div>
                    <p class="text-gray-600">Comissão da Produção Orgânica do Estado de Roraima</p>
                </div>
                
                <div class="commission-card p-8 rounded-2xl shadow-lg card-hover">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-orange-700 rounded-full flex items-center justify-center mr-4">
                            <span class="text-white font-bold">SC</span>
                        </div>
                        <h4 class="text-xl font-semibold text-gray-800">CPOrg Santa Catarina</h4>
                    </div>
                    <p class="text-gray-600 mb-4">Comissão da Produção Orgânica do Estado de Santa Catarina</p>
                    <a href="https://www.cidasc.sc.gov.br/organicos/" target="_blank" class="text-green-600 hover:text-green-800 font-medium text-sm flex items-center">
                        🌐 Acessar site oficial
                        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                        </svg>
                    </a>
                </div>
                
                <div class="commission-card p-8 rounded-2xl shadow-lg card-hover">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-cyan-700 rounded-full flex items-center justify-center mr-4">
                            <span class="text-white font-bold">SP</span>
                        </div>
                        <h4 class="text-xl font-semibold text-gray-800">CPOrg São Paulo</h4>
                    </div>
                    <p class="text-gray-600">Comissão da Produção Orgânica do Estado de São Paulo</p>
                </div>
                
                <div class="commission-card p-8 rounded-2xl shadow-lg card-hover">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-lime-700 rounded-full flex items-center justify-center mr-4">
                            <span class="text-white font-bold">SE</span>
                        </div>
                        <h4 class="text-xl font-semibold text-gray-800">CPOrg Sergipe</h4>
                    </div>
                    <p class="text-gray-600">Comissão da Produção Orgânica do Estado de Sergipe</p>
                </div>
                
                <div class="commission-card p-8 rounded-2xl shadow-lg card-hover">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-emerald-700 rounded-full flex items-center justify-center mr-4">
                            <span class="text-white font-bold">TO</span>
                        </div>
                        <h4 class="text-xl font-semibold text-gray-800">CPOrg Tocantins</h4>
                    </div>
                    <p class="text-gray-600">Comissão da Produção Orgânica do Estado do Tocantins</p>
                </div>
            </div>
            
            <div class="text-center">
                <div class="commission-card p-10 rounded-3xl shadow-2xl max-w-4xl mx-auto">
                    <h4 class="text-3xl font-semibold text-gray-800 mb-6">Funções das CPOrgs</h4>
                    <div class="grid md:grid-cols-2 gap-8 text-left">
                        <div>
                            <ul class="space-y-4">
                                <li class="flex items-start space-x-3">
                                    <span class="text-green-600 text-xl">•</span>
                                    <span class="text-gray-700">Propor diretrizes para desenvolvimento da produção orgânica</span>
                                </li>
                                <li class="flex items-start space-x-3">
                                    <span class="text-green-600 text-xl">•</span>
                                    <span class="text-gray-700">Credenciar Organismos Participativos de Avaliação da Conformidade</span>
                                </li>
                                <li class="flex items-start space-x-3">
                                    <span class="text-green-600 text-xl">•</span>
                                    <span class="text-gray-700">Fiscalizar e monitorar a produção orgânica</span>
                                </li>
                            </ul>
                        </div>
                        <div>
                            <ul class="space-y-4">
                                <li class="flex items-start space-x-3">
                                    <span class="text-green-600 text-xl">•</span>
                                    <span class="text-gray-700">Articular políticas públicas para o setor</span>
                                </li>
                                <li class="flex items-start space-x-3">
                                    <span class="text-green-600 text-xl">•</span>
                                    <span class="text-gray-700">Promover capacitação e assistência técnica</span>
                                </li>
                                <li class="flex items-start space-x-3">
                                    <span class="text-green-600 text-xl">•</span>
                                    <span class="text-gray-700">Fomentar pesquisa e desenvolvimento</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-24 bg-gradient-to-r from-green-600 to-green-700 text-white">
        <div class="container mx-auto px-6 text-center">
            <h3 class="text-5xl font-bold mb-8">Faça Parte da Revolução Orgânica</h3>
            <p class="text-2xl mb-12 max-w-3xl mx-auto leading-relaxed">
                Junte-se aos milhares de produtores que já fazem a diferença na agricultura brasileira. 
                Obtenha sua certificação orgânica e contribua para um futuro mais sustentável.
            </p>
            <div class="flex flex-col sm:flex-row gap-6 justify-center">
                <button onclick="location.href='social/login.php'" class="bg-white text-green-700 px-10 py-5 rounded-full font-semibold text-xl hover:bg-gray-100 transition-colors">
                    Iniciar Certificação
                </button>
                <a href="#sobre" class="btn-primary text-white px-10 py-5 rounded-full font-semibold text-xl inline-block text-center">
                    Conheça a SINPORG
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-16">
        <div class="container mx-auto px-6">
            <div class="grid md:grid-cols-4 gap-12">
                <div>
                    <div class="flex items-center space-x-3 mb-6">
                        <div class="w-10 h-10 bg-green-600 rounded-full flex items-center justify-center">
                            <span class="text-white font-bold">🌿</span>
                        </div>
                        <div>
                            <h4 class="text-2xl font-bold">Orgânico Brasil</h4>
                            <p class="text-sm text-gray-400">Ministério da Agricultura</p>
                        </div>
                    </div>
                    <p class="text-gray-400 leading-relaxed">
                        Promovendo a produção orgânica e fortalecendo a agricultura familiar brasileira 
                        através de políticas públicas eficazes e certificação de qualidade.
                    </p>
                </div>
                
                <div>
                    <h5 class="font-semibold mb-6 text-lg">Navegação</h5>
                    <ul class="space-y-3 text-gray-400">
                        <li><a href="#sobre" class="hover:text-white transition-colors">Sobre Nós</a></li>
                        <li><a href="#producao" class="hover:text-white transition-colors">Produção Orgânica</a></li>
                        <li><a href="#comissoes" class="hover:text-white transition-colors">Comissões</a></li>
                        <li><a href="#" class="hover:text-white transition-colors">Certificação</a></li>
                    </ul>
                </div>
                
                <div>
                    <h5 class="font-semibold mb-6 text-lg">Contato</h5>
                    <ul class="space-y-3 text-gray-400">
                        <li class="flex items-center space-x-2">
                            <span>📧</span>
                            <span><a href="mailto:organico@agricultura.gov.br" class="hover:text-white transition-colors">organico@agricultura.gov.br</a></span>
                        </li>
                        <li class="flex items-center space-x-2">
                            <span>📱</span>
                            <span>(61) 3218-2828</span>
                        </li>
                        <li class="flex items-center space-x-2">
                            <span>📍</span>
                            <span>Brasília, DF</span>
                        </li>
                    </ul>
                </div>
                
                <div>
                    <h5 class="font-semibold mb-6 text-lg">Redes Sociais</h5>
                    <div class="flex space-x-4">
                        <a href="#" class="w-12 h-12 bg-green-600 rounded-full flex items-center justify-center hover:bg-green-700 transition-colors">
                            <span class="text-lg">f</span>
                        </a>
                        <a href="#" class="w-12 h-12 bg-green-600 rounded-full flex items-center justify-center hover:bg-green-700 transition-colors">
                            <span class="text-lg">@</span>
                        </a>
                        <a href="#" class="w-12 h-12 bg-green-600 rounded-full flex items-center justify-center hover:bg-green-700 transition-colors">
                            <span class="text-lg">in</span>
                        </a>
                        <a href="#" class="w-12 h-12 bg-green-600 rounded-full flex items-center justify-center hover:bg-green-700 transition-colors">
                            <span class="text-lg">📺</span>
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="border-t border-gray-700 mt-12 pt-8 text-center text-gray-400">
                <p>&copy; 2024 Ministério da Agricultura, Pecuária e Abastecimento. Todos os direitos reservados.</p>
                <p class="mt-2">Sistema Brasileiro de Avaliação da Conformidade Orgânica</p>
            </div>
        </div>
    </footer>
    <script>
        function toggleMobileMenu() {
            const menu = document.getElementById('mobileMenu');
            menu.classList.toggle('hidden');
        }

        // Smooth scrolling for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Add scroll effect to navigation
        window.addEventListener('scroll', function() {
            const header = document.querySelector('header');
            if (window.scrollY > 100) {
                header.classList.add('shadow-xl');
            } else {
                header.classList.remove('shadow-xl');
            }
        });

        // Intersection Observer for fade-in animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('fade-in');
                }
            });
        }, observerOptions);

        // Observe all cards and sections
        document.querySelectorAll('.card-hover, .commission-card').forEach(el => {
            observer.observe(el);
        });
    </script>
</body>
</html>