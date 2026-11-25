# Anexo — Mapa de Telas Verificado na Aplicação em Execução

> **Método:** navegação autenticada real em http://localhost/app (login efetuado, redirecionamento
> para /dashboard/admin). Cada rota foi requisitada por **GET** e o HTML foi analisado:
> título, cabeçalhos, formulários, campos, ações e colunas de tabela.
> **Nenhum formulário de domínio foi submetido e nenhuma rota destrutiva foi visitada**
> (bloqueio de /logout, /excluir, /estornar, /baixa, /gerar, /enviar e exportações).

| Métrica | Valor |
|---|---|
| Rotas distintas visitadas | 230 |
| Autenticadas (conteúdo real) | 226 |
| Não autenticadas ou de erro | 4 |
| Respostas diferentes de 200 | 0 |

---

## 1. Inventário completo

| # | Rota | HTTP | Autenticada | Título | Form. | Tabelas | Ações |
|---:|---|---:|:---:|---|---:|---:|---:|
| 1 | `/dashboard/admin` | 200 | sim | Painel Administrativo - SINPORG | 0 | 0 | 8 |
| 2 | `/portal/area` | 200 | NÃO | Portal do Associado - Login - SINPORG | 3 | 0 | 12 |
| 3 | `/dashboard/diretoria` | 200 | sim | Dashboard da Diretoria - SINPORG | 0 | 0 | 6 |
| 4 | `/associados` | 200 | sim | Listar Associados - SINPORG | 0 | 1 | 8 |
| 5 | `/cadastros-pendentes` | 200 | sim | Cadastros Pendentes - SINPORG | 2 | 2 | 12 |
| 6 | `/fichas-completas` | 200 | sim | Todas as Fichas de Adesão - SINPORG | 2 | 1 | 17 |
| 7 | `/associados/carteirinhas` | 200 | sim | Carteirinhas dos Associados - SINPORG | 2 | 1 | 10 |
| 8 | `/associacoes` | 200 | sim | Listar Associações - SINPORG | 3 | 1 | 7 |
| 9 | `/contas` | 200 | sim | Listar Contas - SINPORG | 0 | 1 | 7 |
| 10 | `/mensalidades` | 200 | sim | Mensalidades - SINPORG | 2 | 1 | 11 |
| 11 | `/caixa` | 200 | sim | Caixa - SINPORG | 6 | 1 | 9 |
| 12 | `/caixa/cobranca` | 200 | sim | Controle de Cobrança - SINPORG | 15 | 1 | 8 |
| 13 | `/caixa/relatorio` | 200 | sim | Relatório Financeiro - SINPORG | 1 | 4 | 8 |
| 14 | `/eventos` | 200 | sim | Listar Eventos - SINPORG | 0 | 1 | 8 |
| 15 | `/regionalizacao` | 200 | sim | Regionalização RS - SINPORG | 0 | 1 | 9 |
| 16 | `/regionalizacao/mapa` | 200 | sim | Mapa de Calor RS - SINPORG | 0 | 0 | 8 |
| 17 | `/relatorios_diretoria/ativos_municipio` | 200 | sim | Associados Ativos por Município - SINPORG | 1 | 1 | 9 |
| 18 | `/relatorios_diretoria/inadimplentes_micro` | 200 | sim | Inadimplentes por Microrregião - SINPORG | 1 | 1 | 9 |
| 19 | `/relatorios_diretoria/entradas_saidas` | 200 | sim | Entradas x Saídas - Fluxo de Caixa - SINPORG | 1 | 4 | 9 |
| 20 | `/comunicacao` | 200 | sim | Comunicação por Cluster - SINPORG | 1 | 1 | 9 |
| 21 | `/assembleia` | 200 | sim | Assembleias e Votações - SINPORG | 1 | 1 | 9 |
| 22 | `/admin/clusters` | 200 | sim | Clusters de Comunicação - SINPORG | 1 | 1 | 7 |
| 23 | `/dashboard/agroindustria` | 200 | sim | Painel de Agroindústria - SINPORG | 0 | 1 | 9 |
| 24 | `/agroindustria/minhas` | 200 | sim | Minhas Agroindústrias - SINPORG | 0 | 1 | 7 |
| 25 | `/agroindustria/setores` | 200 | sim | Setores da Agroindústria - SINPORG | 0 | 1 | 7 |
| 26 | `/agroindustria/categorias` | 200 | sim | Categorias de Atividades - SINPORG | 0 | 1 | 7 |
| 27 | `/agroindustria/atividades` | 200 | sim | Atividades Diárias da Agroindústria - SINPORG | 0 | 1 | 7 |
| 28 | `/agroindustria/ocorrencias` | 200 | sim | Ocorrências da Agroindústria - SINPORG | 0 | 1 | 7 |
| 29 | `/agroindustria/receitas` | 200 | sim | Receitas Agroindústria - SINPORG | 0 | 1 | 7 |
| 30 | `/agroindustria/higienizacao` | 200 | sim | Controle de Higienização - SINPORG | 0 | 1 | 7 |
| 31 | `/agroindustria/ordens` | 200 | sim | Ordens de Produção - SINPORG | 0 | 1 | 7 |
| 32 | `/agroindustria/rastreabilidade` | 200 | sim | Rastreabilidade do Produto Final - SINPORG | 1 | 0 | 3 |
| 33 | `/producao-animal` | 200 | sim | Painel de Produção Animal - SINPORG | 0 | 0 | 12 |
| 34 | `/manejo-animal` | 200 | sim | Manejo Animal - SINPORG | 0 | 1 | 7 |
| 35 | `/producao-animal/lotes` | 200 | sim | Lotes de Animais - SINPORG | 0 | 1 | 7 |
| 36 | `/producao-animal/alimentacao` | 200 | sim | Controles de Alimentação Animal - SINPORG | 0 | 1 | 7 |
| 37 | `/producao-animal/sanidade` | 200 | sim | Controles de Sanidade Animal - SINPORG | 0 | 1 | 7 |
| 38 | `/producao-animal/reproducao` | 200 | sim | Controle de Reprodução Animal - SINPORG | 0 | 1 | 7 |
| 39 | `/producao-animal/ambientes` | 200 | sim | Ambientes de Criação - SINPORG | 0 | 1 | 7 |
| 40 | `/propriedades` | 200 | sim | Listar Propriedades - SINPORG | 0 | 1 | 8 |
| 41 | `/lavouras` | 200 | sim | Listar Lavouras - SINPORG | 8 | 1 | 8 |
| 42 | `/insumos` | 200 | sim | Listar Insumos - SINPORG | 14 | 1 | 8 |
| 43 | `/produtos` | 200 | sim | Listar Produtos - SINPORG | 13 | 1 | 8 |
| 44 | `/lotes` | 200 | sim | Listar Lotes de Produção - SINPORG | 9 | 1 | 9 |
| 45 | `/fornecedor` | 200 | sim | Listar Fornecedores - SINPORG | 0 | 1 | 7 |
| 46 | `/atividades-diarias` | 200 | sim | Listar Atividades Diárias - SINPORG | 10 | 1 | 8 |
| 47 | `/caderno-campo/ocorrencias` | 200 | sim | Ocorrências de Manejo - SINPORG | 1 | 1 | 15 |
| 48 | `/rastreabilidade` | 200 | sim | Página não encontrada - SINPORG | 0 | 0 | 1 |
| 49 | `/relatorios` | 200 | sim | SINPORG | 3 | 0 | 12 |
| 50 | `/certificacoes` | 200 | sim | Certificações Participativas - SINPORG | 0 | 1 | 9 |
| 51 | `/certificadoras_list.php` | 200 | sim | Certificadoras - SINPORG | 0 | 1 | 7 |
| 52 | `/planos-manejo` | 200 | sim | Planos de Manejo Orgânico - SINPORG | 0 | 1 | 12 |
| 53 | `/insumos-aprovados` | 200 | sim | Insumos Orgânicos Aprovados - SINPORG | 0 | 1 | 6 |
| 54 | `/visitas-tecnicas` | 200 | sim | Visitas Técnicas / Inspeções - SINPORG | 2 | 1 | 9 |
| 55 | `/visita-tecnica/agendar` | 200 | sim | Agendar Nova Inspeção - SINPORG | 1 | 0 | 9 |
| 56 | `/roteiros-inspecao` | 200 | sim | Templates de Roteiros de Inspeção - SINPORG | 1 | 1 | 8 |
| 57 | `/nao-conformidades` | 200 | sim | Não Conformidades - SINPORG | 0 | 1 | 7 |
| 58 | `/caderno-campo/insumos/validacao` | 200 | sim | Listar Insumos - SINPORG | 14 | 1 | 8 |
| 59 | `/relatorios-admin` | 200 | sim | Relatórios Administrativos - SINPORG | 1 | 5 | 13 |
| 60 | `/relatorios-conformidade` | 200 | sim | Relatórios de Conformidade Orgânica - SINPORG | 1 | 0 | 8 |
| 61 | `/consulta-cnpo` | 200 | sim | Consulta CNPO (Cadastro Nacional de Produtores Orgânicos) - SINPORG | 1 | 1 | 8 |
| 62 | `/usuarios` | 200 | sim | Listar Usuários - SINPORG | 0 | 1 | 8 |
| 63 | `/associados/pendentes` | 200 | sim | Usuários Pendentes de Ativação - SINPORG | 2 | 1 | 8 |
| 64 | `/meu-perfil` | 200 | sim | Meu Perfil - SINPORG | 2 | 0 | 11 |
| 65 | `/usuario/configuracoes` | 200 | sim | Configurações - SINPORG | 2 | 0 | 15 |
| 66 | `/evento/novo` | 200 | sim | Cadastrar Evento - SINPORG | 1 | 0 | 9 |
| 67 | `/associado/novo` | 200 | sim | Cadastrar Associado - SINPORG | 1 | 0 | 9 |
| 68 | `/associados/relatorio` | 200 | sim | Gerar Relatório de Associados - SINPORG | 1 | 0 | 8 |
| 69 | `/associado/73/editar` | 200 | sim | SINPORG | 1 | 0 | 12 |
| 70 | `/cadastro-ficha/visualizar?id=20` | 200 | sim | SINPORG | 2 | 1 | 12 |
| 71 | `/associado/73/carteirinha-impressa` | 200 | sim |  | 0 | 0 | 0 |
| 72 | `/associado/73/carteirinha-enviar` | 200 | sim | Página não encontrada - SINPORG | 0 | 0 | 1 |
| 73 | `/associacao/novo` | 200 | sim | Cadastrar Associação - SINPORG | 1 | 0 | 8 |
| 74 | `/conta/novo` | 200 | sim | Cadastrar Conta - SINPORG | 1 | 0 | 8 |
| 75 | `/conta/4/editar` | 200 | sim | Editar Conta - SINPORG | 1 | 0 | 8 |
| 76 | `/mensalidade/novo` | 200 | sim | Lançamento Manual de Mensalidade - SINPORG | 1 | 0 | 9 |
| 77 | `/planos` | 200 | sim | Planos de Mensalidade - SINPORG | 1 | 1 | 8 |
| 78 | `/caixa/lancamento` | 200 | sim | Novo Lançamento de Caixa - SINPORG | 1 | 0 | 9 |
| 79 | `/eventos/presencas` | 200 | sim | Presença em Eventos - SINPORG | 0 | 1 | 7 |
| 80 | `/evento/9/editar` | 200 | sim | Editar Evento - SINPORG | 1 | 1 | 9 |
| 81 | `/assembleia/novo` | 200 | sim | Nova Assembleia - SINPORG | 1 | 0 | 9 |
| 82 | `/assembleia/1/editar` | 200 | sim | Editar Assembleia - SINPORG | 1 | 0 | 9 |
| 83 | `/assembleia/1` | 200 | sim | Assembleia: Assembleia Geral Ordinária-Teste - SINPORG | 0 | 0 | 11 |
| 84 | `/assembleia/1/resultado` | 200 | sim | Resultado: Assembleia Geral Ordinária-Teste - SINPORG | 0 | 1 | 8 |
| 85 | `/assembleia/1/encerrar` | 200 | sim | Encerrar Assembleia - SINPORG | 1 | 0 | 8 |
| 86 | `/agroindustria/ordens/nova` | 200 | sim | Nova Ordem de Produção - SINPORG | 1 | 0 | 9 |
| 87 | `/agroindustria/minha/nova` | 200 | sim | Nova Agroindústria - SINPORG | 1 | 0 | 8 |
| 88 | `/agroindustria/minha/4/editar` | 200 | sim | Editar Agroindústria - SINPORG | 2 | 0 | 9 |
| 89 | `/agroindustria/setor/novo` | 200 | sim | Novo Setor - SINPORG | 1 | 0 | 8 |
| 90 | `/agroindustria/setor/7/editar` | 200 | sim | Editar Setor - SINPORG | 1 | 0 | 8 |
| 91 | `/agroindustria/categoria/novo` | 200 | sim | Nova Categoria de Atividade - SINPORG | 1 | 0 | 8 |
| 92 | `/agroindustria/categoria/4/editar` | 200 | sim | Editar Categoria de Atividade - SINPORG | 1 | 0 | 8 |
| 93 | `/agroindustria/atividade/novo` | 200 | sim | Nova Atividade Diária - SINPORG | 1 | 0 | 8 |
| 94 | `/agroindustria/atividade/2/editar` | 200 | sim | Editar Atividade Diária - SINPORG | 1 | 0 | 8 |
| 95 | `/agroindustria/ocorrencia/novo` | 200 | sim | Nova Ocorrência - SINPORG | 1 | 0 | 8 |
| 96 | `/agroindustria/receitas/nova` | 200 | sim | Nova Receita - SINPORG | 1 | 0 | 9 |
| 97 | `/agroindustria/receitas/8/editar` | 200 | sim | Editar Receita - SINPORG | 1 | 0 | 9 |
| 98 | `/agroindustria/higienizacao/nova` | 200 | sim | Novo Registro de Higienização - SINPORG | 1 | 0 | 8 |
| 99 | `/agroindustria/ordens/3` | 200 | sim | Ordem de Produção - SINPORG | 0 | 1 | 10 |
| 100 | `/manejo-animal/novo` | 200 | sim | Cadastrar Animal - SINPORG | 1 | 0 | 8 |
| 101 | `/producao-animal/lote/novo` | 200 | sim | Novo Lote de Animais - SINPORG | 1 | 0 | 8 |
| 102 | `/producao-animal/alimentacao/novo` | 200 | sim | Novo Registro de Alimentação - SINPORG | 1 | 0 | 8 |
| 103 | `/producao-animal/sanidade/novo` | 200 | sim | Novo Registro de Sanidade - SINPORG | 1 | 0 | 8 |
| 104 | `/producao-animal/reproducao/novo` | 200 | sim | Novo Evento Reprodutivo - SINPORG | 1 | 0 | 8 |
| 105 | `/producao-animal/ambiente/novo` | 200 | sim | Novo Ambiente de Criação - SINPORG | 1 | 0 | 8 |
| 106 | `/manejo-animal/4` | 200 | sim | Animal — Manejo - SINPORG | 1 | 0 | 9 |
| 107 | `/manejo-animal/4/editar` | 200 | sim | Editar Animal - SINPORG | 1 | 0 | 8 |
| 108 | `/producao-animal/lote/9/editar` | 200 | sim | Editar Lote de Animais - SINPORG | 1 | 0 | 8 |
| 109 | `/producao-animal/alimentacao/7/editar` | 200 | sim | Editar Registro de Alimentação - SINPORG | 1 | 0 | 8 |
| 110 | `/producao-animal/sanidade/7/editar` | 200 | sim | Editar Registro de Sanidade - SINPORG | 1 | 0 | 8 |
| 111 | `/producao-animal/reproducao/6/editar` | 200 | sim | Editar Evento Reprodutivo - SINPORG | 1 | 0 | 8 |
| 112 | `/producao-animal/ambiente/5/editar` | 200 | sim | Editar Ambiente de Criação - SINPORG | 1 | 0 | 8 |
| 113 | `/caderno-campo/propriedade/novo` | 200 | sim | Cadastrar Propriedade - SINPORG | 1 | 0 | 8 |
| 114 | `/caderno-campo/dashboard` | 200 | sim | Dashboard - Caderno de Campo - SINPORG | 0 | 1 | 11 |
| 115 | `/caderno-campo/propriedade/28/editar` | 200 | sim | Editar Propriedade - SINPORG | 1 | 0 | 10 |
| 116 | `/caderno-campo/propriedade/28/imagens` | 200 | sim | Visualizar Imagens da Propriedade - SINPORG | 0 | 0 | 9 |
| 117 | `/propriedade/28/historico-conversao` | 200 | sim | Página não encontrada - SINPORG | 0 | 0 | 1 |
| 118 | `/caderno-campo/lavoura/novo` | 200 | sim | Cadastrar Lavoura - SINPORG | 1 | 0 | 9 |
| 119 | `/` | 200 | sim | Painel Administrativo - SINPORG | 0 | 0 | 8 |
| 120 | `/caderno-campo/lavoura/0fb8cb91-aab5-44a0-bb06-ab0504d71b98/editar` | 200 | sim | Editar Lavoura - SINPORG | 1 | 0 | 8 |
| 121 | `/caderno-campo/lavoura/e9c7c898-ecbf-46f6-baa0-b8f898a936b5/editar` | 200 | sim | Editar Lavoura - SINPORG | 1 | 0 | 8 |
| 122 | `/caderno-campo/lavoura/98ef79e5-1dae-4caf-a4f4-b28b07993985/editar` | 200 | sim | Editar Lavoura - SINPORG | 1 | 0 | 8 |
| 123 | `/caderno-campo/lavoura/891a0701-4b60-4184-986f-2b080a0caf48/editar` | 200 | sim | Editar Lavoura - SINPORG | 1 | 0 | 8 |
| 124 | `/caderno-campo/lavoura/0c716f4d-4a76-49f7-ab1f-a090d633f1e5/editar` | 200 | sim | Editar Lavoura - SINPORG | 1 | 0 | 8 |
| 125 | `/caderno-campo/lavoura/c1679c2d-bc39-4153-b8bd-eaa6bdb08f28/editar` | 200 | sim | Editar Lavoura - SINPORG | 1 | 0 | 8 |
| 126 | `/caderno-campo/lavoura/bb2f57e1-2aac-4f2d-bf24-4ba97b2f52a5/editar` | 200 | sim | Editar Lavoura - SINPORG | 1 | 0 | 8 |
| 127 | `/caderno-campo/lavoura/fa6bacf7-e2e5-448e-bde4-64cccb2187ef/editar` | 200 | sim | Editar Lavoura - SINPORG | 1 | 0 | 8 |
| 128 | `/caderno-campo/insumo/novo` | 200 | sim | Cadastrar Insumo - SINPORG | 1 | 0 | 12 |
| 129 | `/caderno-campo/insumo/f09485b9-7d22-4ac6-970e-d3af67b45be4/editar` | 200 | sim | Editar Insumos - SINPORG | 1 | 0 | 8 |
| 130 | `/caderno-campo/insumo/512f4cd6-4172-43f8-9183-d543fd2905b2/editar` | 200 | sim | Editar Insumos - SINPORG | 1 | 0 | 8 |
| 131 | `/caderno-campo/insumo/ff7a3fb4-6fff-4299-aba9-43a97b107346/editar` | 200 | sim | Editar Insumos - SINPORG | 1 | 0 | 8 |
| 132 | `/caderno-campo/insumo/b5e7691a-824a-478d-8621-8a8f4b991670/editar` | 200 | sim | Editar Insumos - SINPORG | 1 | 0 | 8 |
| 133 | `/caderno-campo/insumo/b0e88f1c-6399-4f77-906e-d0898393a926/editar` | 200 | sim | Editar Insumos - SINPORG | 1 | 0 | 8 |
| 134 | `/caderno-campo/insumo/103dc349-b131-4b3f-8348-143f0502c052/editar` | 200 | sim | Editar Insumos - SINPORG | 1 | 0 | 8 |
| 135 | `/caderno-campo/insumo/8bc21dba-2d79-4eb3-8af2-7d2bda7fff28/editar` | 200 | sim | Editar Insumos - SINPORG | 1 | 0 | 8 |
| 136 | `/caderno-campo/insumo/7728cce6-428a-4b11-bc6f-21b3f54720f5/editar` | 200 | sim | Editar Insumos - SINPORG | 1 | 0 | 8 |
| 137 | `/caderno-campo/insumo/27bbe717-5c00-4e2c-b891-2b9ed663b224/editar` | 200 | sim | Editar Insumos - SINPORG | 1 | 0 | 8 |
| 138 | `/caderno-campo/insumo/b6b9c20e-d58e-49b3-a977-efa1ef4994bc/editar` | 200 | sim | Editar Insumos - SINPORG | 1 | 0 | 8 |
| 139 | `/caderno-campo/insumo/49f4aed3-080d-4cc0-8829-c9f186fe3369/editar` | 200 | sim | Editar Insumos - SINPORG | 1 | 0 | 8 |
| 140 | `/caderno-campo/insumo/fd87d611-8ed9-4922-8f39-caf14243c6f7/editar` | 200 | sim | Editar Insumos - SINPORG | 1 | 0 | 8 |
| 141 | `/caderno-campo/insumo/38facc8a-a275-4bc0-a112-41bc3450c4b3/editar` | 200 | sim | Editar Insumos - SINPORG | 1 | 0 | 8 |
| 142 | `/caderno-campo/insumo/c1ad7ed7-df79-4ae1-b5bf-b8ebf87cf002/editar` | 200 | sim | Editar Insumos - SINPORG | 1 | 0 | 8 |
| 143 | `/caderno-campo/produto/novo` | 200 | sim | Cadastrar Produto - SINPORG | 1 | 0 | 8 |
| 144 | `/caderno-campo/produto/8764e82c-b682-4c56-bebf-7d02136d42dc/editar` | 200 | sim | Editar Produtos - SINPORG | 1 | 0 | 8 |
| 145 | `/caderno-campo/produto/e5a27e7c-f7b7-4965-838e-ae676b0562ba/editar` | 200 | sim | Editar Produtos - SINPORG | 1 | 0 | 8 |
| 146 | `/caderno-campo/produto/d48e82a3-23f0-4679-88d2-adad65a89fce/editar` | 200 | sim | Editar Produtos - SINPORG | 1 | 0 | 8 |
| 147 | `/caderno-campo/produto/b0765463-8e06-40e0-841f-5aa1c7844abb/editar` | 200 | sim | Editar Produtos - SINPORG | 1 | 0 | 8 |
| 148 | `/caderno-campo/produto/44f80253-8929-47ca-a9d4-96279d6f9e98/editar` | 200 | sim | Editar Produtos - SINPORG | 1 | 0 | 8 |
| 149 | `/caderno-campo/produto/ca5d98ec-3bce-4b1f-a3d7-93e3bd7be533/editar` | 200 | sim | Editar Produtos - SINPORG | 1 | 0 | 8 |
| 150 | `/caderno-campo/produto/a1db3d3c-b498-41d1-9a10-e006cb7e8afe/editar` | 200 | sim | Editar Produtos - SINPORG | 1 | 0 | 8 |
| 151 | `/caderno-campo/produto/1617abf5-249b-454a-bbc4-8e4094914221/editar` | 200 | sim | Editar Produtos - SINPORG | 1 | 0 | 8 |
| 152 | `/caderno-campo/produto/3dd19d28-97bb-428b-afc4-f1d6d8d0db8e/editar` | 200 | sim | Editar Produtos - SINPORG | 1 | 0 | 8 |
| 153 | `/caderno-campo/produto/4954612c-9db4-4b64-a495-cf8872454a50/editar` | 200 | sim | Editar Produtos - SINPORG | 1 | 0 | 8 |
| 154 | `/caderno-campo/produto/8640d07a-bbe7-451d-8b42-30e517dab6c5/editar` | 200 | sim | Editar Produtos - SINPORG | 1 | 0 | 8 |
| 155 | `/caderno-campo/produto/a241a177-2aaf-47ca-a5aa-abc5a220ab49/editar` | 200 | sim | Editar Produtos - SINPORG | 1 | 0 | 8 |
| 156 | `/caderno-campo/produto/dc6064cd-f4d7-4900-b48a-ec0a2822e794/editar` | 200 | sim | Editar Produtos - SINPORG | 1 | 0 | 8 |
| 157 | `/caderno-campo/lote/novo` | 200 | sim | Cadastrar Lote de Produção - SINPORG | 1 | 0 | 8 |
| 158 | `/lote/0afd0e88-5ade-40a4-a45c-9d3559c70b01` | 200 | sim | Rastrear Lote de Produção - SINPORG | 0 | 0 | 7 |
| 159 | `/lote/0afd0e88-5ade-40a4-a45c-9d3559c70b01/editar` | 200 | sim | Editar Lote de Produção - SINPORG | 1 | 0 | 9 |
| 160 | `/lote/161fa9f5-04ea-422c-9393-a2d6d9c87eb2` | 200 | sim | Rastrear Lote de Produção - SINPORG | 0 | 2 | 7 |
| 161 | `/lote/161fa9f5-04ea-422c-9393-a2d6d9c87eb2/editar` | 200 | sim | Editar Lote de Produção - SINPORG | 1 | 0 | 9 |
| 162 | `/lote/e11e123c-7457-426b-9228-a6d40c6e7cb6` | 200 | sim | Rastrear Lote de Produção - SINPORG | 0 | 2 | 7 |
| 163 | `/lote/e11e123c-7457-426b-9228-a6d40c6e7cb6/editar` | 200 | sim | Editar Lote de Produção - SINPORG | 1 | 0 | 9 |
| 164 | `/lote/10afb836-061f-4d3f-83d5-06b9728edb61` | 200 | sim | Rastrear Lote de Produção - SINPORG | 0 | 0 | 7 |
| 165 | `/lote/10afb836-061f-4d3f-83d5-06b9728edb61/editar` | 200 | sim | Editar Lote de Produção - SINPORG | 1 | 0 | 9 |
| 166 | `/lote/a3db331b-6a35-4d99-ba64-0f70958d8451` | 200 | sim | Rastrear Lote de Produção - SINPORG | 0 | 0 | 7 |
| 167 | `/lote/a3db331b-6a35-4d99-ba64-0f70958d8451/editar` | 200 | sim | Editar Lote de Produção - SINPORG | 1 | 0 | 9 |
| 168 | `/lote/6500a0cf-d4c5-405a-ae19-08b94f2c1c6c` | 200 | sim | Rastrear Lote de Produção - SINPORG | 0 | 2 | 7 |
| 169 | `/lote/6500a0cf-d4c5-405a-ae19-08b94f2c1c6c/editar` | 200 | sim | Editar Lote de Produção - SINPORG | 1 | 0 | 9 |
| 170 | `/lote/f6358c2e-22a3-4f85-abe8-fcde5daa8180` | 200 | sim | Rastrear Lote de Produção - SINPORG | 0 | 2 | 7 |
| 171 | `/lote/f6358c2e-22a3-4f85-abe8-fcde5daa8180/editar` | 200 | sim | Editar Lote de Produção - SINPORG | 1 | 0 | 9 |
| 172 | `/lote/f41eb70e-d499-4967-9bf0-4c448dc1847e` | 200 | sim | Rastrear Lote de Produção - SINPORG | 0 | 0 | 7 |
| 173 | `/lote/f41eb70e-d499-4967-9bf0-4c448dc1847e/editar` | 200 | sim | Editar Lote de Produção - SINPORG | 1 | 0 | 9 |
| 174 | `/lote/2baa3cb8-0d86-4aea-bd1a-e70179f5809c` | 200 | sim | Rastrear Lote de Produção - SINPORG | 0 | 0 | 7 |
| 175 | `/lote/2baa3cb8-0d86-4aea-bd1a-e70179f5809c/editar` | 200 | sim | Editar Lote de Produção - SINPORG | 1 | 0 | 9 |
| 176 | `/fornecedor/novo` | 200 | sim | SINPORG | 1 | 0 | 11 |
| 177 | `/fornecedor/5/editar` | 200 | sim | Editar Fornecedor - SINPORG | 1 | 0 | 10 |
| 178 | `/api/relatorios/relatorio_atividades_usuario.php?formato=pdf` | 200 | sim | RelatÃ³rio de Atividades â Todos os Associados | 0 | 0 | 0 |
| 179 | `/caderno-campo/atividade-diaria/novo` | 200 | sim | Cadastrar Atividade Diária - SINPORG | 1 | 0 | 8 |
| 180 | `/caderno-campo/atividade-diaria/74599286-6fe4-44f7-a356-64cc9fc1d30b/editar` | 200 | sim | Editar Atividade Diária - SINPORG | 1 | 0 | 8 |
| 181 | `/caderno-campo/atividade-diaria/af3e8250-a4b2-4495-bd7e-79b7c8ab99c6/editar` | 200 | sim | Editar Atividade Diária - SINPORG | 1 | 0 | 8 |
| 182 | `/caderno-campo/atividade-diaria/4c0215a7-5eee-4bd4-820f-418b778c0edb/editar` | 200 | sim | Editar Atividade Diária - SINPORG | 1 | 0 | 8 |
| 183 | `/caderno-campo/atividade-diaria/7df9b87d-982a-42dc-9f9a-190d32f4210a/imagens` | 200 | sim | Visualizar Imagens da Atividade Diária - SINPORG | 0 | 0 | 10 |
| 184 | `/caderno-campo/atividade-diaria/7df9b87d-982a-42dc-9f9a-190d32f4210a/editar` | 200 | sim | Editar Atividade Diária - SINPORG | 1 | 0 | 9 |
| 185 | `/caderno-campo/atividade-diaria/47967769-107f-49c3-bf24-8d9d03e3b0b2/imagens` | 200 | sim | Visualizar Imagens da Atividade Diária - SINPORG | 0 | 0 | 10 |
| 186 | `/caderno-campo/atividade-diaria/47967769-107f-49c3-bf24-8d9d03e3b0b2/editar` | 200 | sim | Editar Atividade Diária - SINPORG | 1 | 0 | 9 |
| 187 | `/caderno-campo/atividade-diaria/bded51dd-de5e-489c-96e7-70e274724c1d/imagens` | 200 | sim | Visualizar Imagens da Atividade Diária - SINPORG | 0 | 0 | 10 |
| 188 | `/caderno-campo/atividade-diaria/bded51dd-de5e-489c-96e7-70e274724c1d/editar` | 200 | sim | Editar Atividade Diária - SINPORG | 1 | 0 | 9 |
| 189 | `/caderno-campo/atividade-diaria/243b20f3-4279-4746-9f04-20736ab9be97/imagens` | 200 | sim | Visualizar Imagens da Atividade Diária - SINPORG | 0 | 0 | 10 |
| 190 | `/caderno-campo/atividade-diaria/243b20f3-4279-4746-9f04-20736ab9be97/editar` | 200 | sim | Editar Atividade Diária - SINPORG | 1 | 0 | 9 |
| 191 | `/caderno-campo/atividade-diaria/cb23dc59-ba87-4542-96a6-da1a769b2960/imagens` | 200 | sim | Visualizar Imagens da Atividade Diária - SINPORG | 0 | 0 | 10 |
| 192 | `/caderno-campo/atividade-diaria/cb23dc59-ba87-4542-96a6-da1a769b2960/editar` | 200 | sim | Editar Atividade Diária - SINPORG | 1 | 0 | 9 |
| 193 | `/caderno-campo/atividade-diaria/5d3740c0-5d92-4740-8c57-601586a8b3d6/editar` | 200 | sim | Editar Atividade Diária - SINPORG | 1 | 0 | 8 |
| 194 | `/caderno-campo/atividade-diaria/d05a7a79-b70f-4675-8be9-2db4b613f52f/editar` | 200 | sim | Editar Atividade Diária - SINPORG | 1 | 0 | 8 |
| 195 | `/caderno-campo/ocorrencia/novo` | 200 | sim | Cadastrar Ocorrência - SINPORG | 1 | 0 | 9 |
| 196 | `/modulos/associados/listar.php` | 200 | sim | Listar Associados - SINPORG | 0 | 1 | 8 |
| 197 | `/modulos/propriedades/listar.php` | 200 | sim | Página não encontrada - SINPORG | 0 | 0 | 1 |
| 198 | `/modulos/caderno_campo/atividade_diaria/listar.php` | 200 | sim | Listar Atividades Diárias - SINPORG | 10 | 1 | 8 |
| 199 | `/modulos/caderno_campo/insumos/listar.php` | 200 | sim | Listar Insumos - SINPORG | 14 | 1 | 8 |
| 200 | `/relatorios/atividades.php?tipo=pdf` | 200 | NÃO |  | 0 | 0 | 0 |
| 201 | `/relatorios/insumos.php?tipo=pdf` | 200 | NÃO |  | 0 | 0 | 0 |
| 202 | `/relatorios/lavouras.php?tipo=pdf` | 200 | NÃO |  | 0 | 0 | 0 |
| 203 | `/certificacao/novo` | 200 | sim | Nova Certificação - SINPORG | 1 | 0 | 9 |
| 204 | `/certificacao/${row.id}/editar` | 200 | sim | Página não encontrada - SINPORG | 0 | 0 | 1 |
| 205 | `/certificacao/${row.id}/renovar` | 200 | sim | Página não encontrada - SINPORG | 0 | 0 | 1 |
| 206 | `/certificacao/historico/${row.associado_id}` | 200 | sim | Página não encontrada - SINPORG | 0 | 0 | 1 |
| 207 | `/certificacao/${row.id}/certificado?format=html` | 200 | sim | Página não encontrada - SINPORG | 0 | 0 | 1 |
| 208 | `/certificacao/${data.id}/certificado?format=html` | 200 | sim | Página não encontrada - SINPORG | 0 | 0 | 1 |
| 209 | `/certificadoras_cadastrar.php` | 200 | sim | SINPORG | 1 | 0 | 7 |
| 210 | `/plano-manejo/novo` | 200 | sim | Novo Plano de Manejo Orgânico - SINPORG | 1 | 1 | 11 |
| 211 | `/${baseUrl}/${row.id}/visualizar` | 200 | sim | Página não encontrada - SINPORG | 0 | 0 | 1 |
| 212 | `/${baseUrl}/${row.id}/editar` | 200 | sim | Página não encontrada - SINPORG | 0 | 0 | 1 |
| 213 | `/roteiro-inspecao/novo` | 200 | sim | Novo Roteiro de Inspeção - SINPORG | 1 | 0 | 10 |
| 214 | `/api/relatorios/admin_export.php?tipo=html&relatorio=usuarios` | 200 | sim | Relatório de Usuários - Sistema de Relatórios | 0 | 1 | 4 |
| 215 | `/usuario/novo` | 200 | sim | Cadastrar Usuário - SINPORG | 1 | 0 | 8 |
| 216 | `/usuario/110/editar` | 200 | sim | Editar Usuário - SINPORG | 1 | 0 | 8 |
| 217 | `/plano/novo` | 200 | sim | Novo Plano de Mensalidade - SINPORG | 1 | 0 | 9 |
| 218 | `/plano/2/editar` | 200 | sim | Editar Plano de Mensalidade - SINPORG | 1 | 0 | 9 |
| 219 | `/modulos/eventos/relatorio_presenca.php?evento_id=9` | 200 | sim |  | 0 | 0 | 0 |
| 220 | `/caderno-campo/propriedades` | 200 | sim | Listar Propriedades - SINPORG | 0 | 1 | 8 |
| 221 | `/caderno-campo/lavouras` | 200 | sim | Listar Lavouras - SINPORG | 8 | 1 | 8 |
| 222 | `/caderno-campo/insumos` | 200 | sim | Listar Insumos - SINPORG | 14 | 1 | 8 |
| 223 | `/caderno-campo/produtos` | 200 | sim | Listar Produtos - SINPORG | 13 | 1 | 8 |
| 224 | `/caderno-campo/lotes` | 200 | sim | Listar Lotes de Produção - SINPORG | 9 | 1 | 9 |
| 225 | `/caderno-campo/categorias-atividades` | 200 | sim | Listar Categorias de Atividades - SINPORG | 7 | 1 | 8 |
| 226 | `/caderno-campo/atividades-diarias` | 200 | sim | Listar Atividades Diárias - SINPORG | 10 | 1 | 8 |
| 227 | `/caderno-campo/relatorios` | 200 | sim | Relatórios - Caderno de Campo - SINPORG | 1 | 6 | 17 |
| 228 | `/caderno-campo/atividade-diaria/9/editar` | 200 | sim | Editar Atividade Diária - SINPORG | 1 | 0 | 9 |
| 229 | `/caderno-campo/categoria-atividade/novo` | 200 | sim | Cadastrar Categoria de Atividade - SINPORG | 1 | 0 | 8 |
| 230 | `/caderno-campo/categoria-atividade/8/editar` | 200 | sim | Editar Categoria de Atividade - SINPORG | 1 | 0 | 9 |

---

## 2. Detalhe por tela — formulários, campos e ações

### `/dashboard/admin`

- **Painel Administrativo**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Ver Todos · Agendar Evento · Mais

### `/dashboard/diretoria`

- **Dashboard da Diretoria**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Mais

### `/associados`

- **Lista de Associados**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Adicionar Novo · Gerar Relatório · Mais
- Colunas: Foto · Nome Completo · E-mail · Telefone · Cidade · Status · Nível · Ações

### `/cadastros-pendentes`

- **Cadastros Pendentes**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Ver Todos os Associados · Visualizar · Aprovar · Recusar · Cancelar · Confirmar Recusa · Mais
- Colunas: Ficha · Associado(a) · Tipo de Associado · CPF · Município · Submetida em · Ações · Nº de Cadastro · Resultado · Analisada em
- Formulário `POST (mesma página)` — campos: csrf_token:hidden, acao:hidden, ficha_id:hidden
- Formulário `POST (mesma página)` — campos: csrf_token:hidden, acao:hidden, ficha_id:hidden, motivo:textarea

### `/fichas-completas`

- **Todas as Fichas de Adesão**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Apenas Pendentes · Todas 5 · Pendentes 1 · Aprovadas 2 · Recusadas 0 · Rascunho 2 · Ver · Aprovar · Recusar · Cancelar · Confirmar Recusa · Mais
- Colunas: Ficha · Associado(a) · Tipo de Associado · CPF · Município · Status · Submetida em · Analisada em · Ações
- Formulário `POST (mesma página)` — campos: csrf_token:hidden, acao:hidden, ficha_id:hidden
- Formulário `POST (mesma página)` — campos: csrf_token:hidden, acao:hidden, ficha_id:hidden, motivo:textarea

### `/associados/carteirinhas`

- **Carteirinhas dos Associados**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Selecionar todos · Enviar por e-mail (selecionados) · Voltar · Buscar · Mais
- Colunas: Associado · E-mail · Categoria · Situação · Financeiro · Ações
- Formulário `GET http://localhost/app/associados/carteirinhas` — campos: busca:text
- Formulário `POST http://localhost/app/modulos/associados/carteirinhas_enviar_massa.php` — campos: csrf_token:hidden, associados[]:checkbox, associados[]:checkbox, associados[]:checkbox, associados[]:checkbox, associados[]:checkbox, associados[]:checkbox, associados[]:checkbox, associados[]:checkbox, associados[]:checkbox, associados[]:checkbox, associados[]:checkbox, associados[]:checkbox, associados[]:checkbox, associados[]:checkbox, associados[]:checkbox, associados[]:checkbox, associados[]:checkbox, associados[]:checkbox, associados[]:checkbox, associados[]:checkbox, associados[]:checkbox, associados[]:checkbox, associados[]:checkbox, associados[]:checkbox, associados[]:checkbox, associados[]:checkbox, associados[]:checkbox, associados[]:checkbox, associados[]:checkbox, associados[]:checkbox

### `/associacoes`

- **Associações Cadastradas**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Adicionar Nova · Mais
- Colunas: ID · Nome · Sigla · Cidade · Estado · Status · Ações
- Formulário `POST http://localhost/app/associacao/editar` — campos: id:hidden
- Formulário `POST http://localhost/app/associacao/editar` — campos: id:hidden
- Formulário `POST http://localhost/app/associacao/editar` — campos: id:hidden

### `/contas`

- **Contas**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Adicionar Nova Conta · Mais
- Colunas: ID · Nome · Tipo · Valor · Data · Ações

### `/mensalidades`

- **Mensalidades**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Gerar Lote do Mês · Lançamento Manual · Planos · Cobrança · Filtrar · Mais
- Colunas: Associado · Ref. · Associação · Plano · Vencimento · Valor · Situação · Pagamento · Ações
- Formulário `GET http://localhost/app/mensalidades` — campos: associacao_id:select, ano:select, mes:select, status:select
- Formulário `POST http://localhost/app/mensalidade/22/excluir` — campos: csrf_token:hidden

### `/caixa`

- **Caixa**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Novo Lançamento · Mensalidades · Filtrar · Mais
- Colunas: Data · Tipo · Descrição · Categoria · Associação · Valor · Ações
- Formulário `GET http://localhost/app/caixa` — campos: associacao_id:select, tipo:select, data_inicio:date, data_fim:date
- Formulário `POST http://localhost/app/caixa/10/excluir` — campos: csrf_token:hidden
- Formulário `POST http://localhost/app/caixa/9/excluir` — campos: csrf_token:hidden
- Formulário `POST http://localhost/app/caixa/8/excluir` — campos: csrf_token:hidden
- Formulário `POST http://localhost/app/caixa/3/excluir` — campos: csrf_token:hidden
- Formulário `POST http://localhost/app/caixa/2/excluir` — campos: csrf_token:hidden

### `/caixa/cobranca`

- **Controle de Cobrança**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar · Filtrar · Mais
- Colunas: Associado · Telefone · E-mail · Cidade · Contas em aberto · Total em Aberto · Cobrar
- Formulário `GET http://localhost/app/caixa/cobranca` — campos: associacao_id:select, ano:select, mes:select
- Formulário `POST (mesma página)` — campos: csrf_token:hidden, acao:hidden, associado_id:hidden, associacao_id:hidden, mes:hidden, ano:hidden
- Formulário `POST (mesma página)` — campos: csrf_token:hidden, acao:hidden, associado_id:hidden, associacao_id:hidden, mes:hidden, ano:hidden
- Formulário `POST (mesma página)` — campos: csrf_token:hidden, acao:hidden, associado_id:hidden, associacao_id:hidden, mes:hidden, ano:hidden
- Formulário `POST (mesma página)` — campos: csrf_token:hidden, acao:hidden, associado_id:hidden, associacao_id:hidden, mes:hidden, ano:hidden
- Formulário `POST (mesma página)` — campos: csrf_token:hidden, acao:hidden, associado_id:hidden, associacao_id:hidden, mes:hidden, ano:hidden
- Formulário `POST (mesma página)` — campos: csrf_token:hidden, acao:hidden, associado_id:hidden, associacao_id:hidden, mes:hidden, ano:hidden
- Formulário `POST (mesma página)` — campos: csrf_token:hidden, acao:hidden, associado_id:hidden, associacao_id:hidden, mes:hidden, ano:hidden

### `/caixa/relatorio`

- **Relatório Financeiro**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar · Filtrar · Mais
- Colunas: Mês · Entradas · Saídas · Saldo · Referência · Contas · Valor · Forma · Qtd · A Receber · Total · R$ 0,00
- Formulário `GET http://localhost/app/caixa/relatorio` — campos: associacao_id:select

### `/eventos`

- **Eventos**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Presenças em Eventos · Adicionar Novo Evento · Mais
- Colunas: ID · Nome · Data · Local · Status · Ações

### `/regionalizacao`

- **Regionalização RS**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Mapa de Calor · Exportar CSV · Gerenciar · Mais
- Colunas: Mesorregião · Microrregião · Municípios · Associados · Ativos · Inadimplentes · % Inadimplência

### `/regionalizacao/mapa`

- **Mapa de Calor RS**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Tabela · Atualizar · Mais

### `/relatorios_diretoria/ativos_municipio`

- **Associados Ativos por Município**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Exportar CSV · Filtrar · Limpar · Mais
- Colunas: Mesorregião · Microrregião · Município · IBGE · Total · Ativos · Inadimplentes · Afastados · % Inadimplência
- Formulário `GET (mesma página)` — campos: mesorregiao:select, microrregiao:select

### `/relatorios_diretoria/inadimplentes_micro`

- **Inadimplentes por Microrregião**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Exportar CSV · Filtrar · Limpar · Mais
- Colunas: Mesorregião · Microrregião · Qtd Inadimplentes · Valor Total (R$) · Média por Associado (R$)
- Formulário `GET (mesma página)` — campos: mesorregiao:select, mes:select, ano:select

### `/relatorios_diretoria/entradas_saidas`

- **Fluxo de Caixa**
- Seções: R$ 0,00
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Exportar CSV · Filtrar · Limpar · Mais
- Colunas: Categoria · Qtd · Valor (R$) · Forma de Pagamento · % · Tipo
- Formulário `GET (mesma página)` — campos: associacao_id:select, mes:select, ano:select

### `/comunicacao`

- **Comunicação Segmentada por Cluster**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Enviar Comunicação · Visualizar · Comunicar · Mais
- Colunas: Cluster · Qtd Associados · Com E-mail · Com Telefone · Ação
- Formulário `POST (mesma página)` — campos: csrf_token:hidden, acao:hidden, cluster:select, associacao_id:select, tipo_envio:radio, tipo_envio:radio, tipo_envio:radio, assunto:text, corpo:textarea

### `/assembleia`

- **Assembleias e Votações**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Nova Assembleia · Filtrar · Limpar · Mais
- Colunas: Título · Associação · Período · Status · Pautas · Votos · Ações
- Formulário `GET (mesma página)` — campos: status:select, associacao_id:select

### `/admin/clusters`

- **Clusters de Comunicação**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Atribuir Clusters Automaticamente · Mais
- Colunas: Cluster · Mesorregião · Microrregião · Associados · Ativos · % Ativos
- Formulário `POST (mesma página)` — campos: csrf_token:hidden

### `/dashboard/agroindustria`

- **Painel de Agroindústria**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Nova Ordem · Ver Todos · Agendar Evento · Mais
- Colunas: Lote Processado · Produto Final · Processamento · Qtde · Status

### `/agroindustria/minhas`

- **Agroindústrias**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Nova Agroindústria · Mais
- Colunas: Nome Fantasia · Razão Social / CNPJ · Localização · Responsável Técnico · Status · Associado · Ações

### `/agroindustria/setores`

- **Setores da Agroindústria**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Novo Setor · Mais
- Colunas: Setor · Descrição · Ações

### `/agroindustria/categorias`

- **Categorias de Atividades**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Nova Categoria · Mais
- Colunas: Cor · Categoria · Descrição · Ações

### `/agroindustria/atividades`

- **Atividades Diárias**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Nova Atividade · Mais
- Colunas: Data · Atividade · Setor · Categoria · Status · Ações

### `/agroindustria/ocorrencias`

- **Ocorrências**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Nova Ocorrência · Mais
- Colunas: Data · Ocorrência · Tipo · Setor · Severidade · Status · Ações

### `/agroindustria/receitas`

- **Receitas Agroindústria**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Nova Receita · Mais
- Colunas: Produto Final · % Orgânico · Selo Autorizado · Ingredientes · Status · Ações

### `/agroindustria/higienizacao`

- **Controle de Higienização**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Novo Registro · Mais
- Colunas: Equipamento / Linha · Início · Fim · Sanitizante · Responsável · Ações

### `/agroindustria/ordens`

- **Ordens de Produção**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Nova Ordem · Mais
- Colunas: Lote Processado · Produto Final · Processamento · Qtde Final · % Orgânico · Status Auditoria · Ações

### `/agroindustria/rastreabilidade`

- **SINPORG**
- Ações: Consultar · Abrir em nova aba · Fechar
- Formulário `GET http://localhost/app/agroindustria/rastreabilidade` — campos: lote:text

### `/producao-animal`

- **Painel de Produção Animal**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Cadastrar animal · Novo lote · Registrar alimentação · Registrar sanidade · Registrar reprodução · Cadastrar ambiente · Mais

### `/manejo-animal`

- **Manejo Animal (RF05)**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Cadastrar Animal · Mais
- Colunas: Identificação · Espécie / Raça · Lote · Origem · Status · Registros · Ações

### `/producao-animal/lotes`

- **Lotes de Animais**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Novo Lote · Mais
- Colunas: QR Code · Lote · Espécie · Categoria · Animais · Ações

### `/producao-animal/alimentacao`

- **Controle de Alimentação**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Novo Registro · Mais
- Colunas: Data · Animal/Lote · Tipo · Produto · Qtd (kg) · % MS · Origem · Ações

### `/producao-animal/sanidade`

- **Controle de Sanidade**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Novo Registro · Mais
- Colunas: Data · Animal/Lote · Tipo · Produto · Norma · Carência · Liberação · Ações

### `/producao-animal/reproducao`

- **Controle de Reprodução**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Novo Evento · Mais
- Colunas: Data · Animal (mãe) · Evento · Reprodutor · Vivos/Natimortos · Aleitamento · Prev. Parto · Ações

### `/producao-animal/ambientes`

- **Ambientes de Criação**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Novo Ambiente · Mais
- Colunas: Instalação · Espécie · Sistema · Área (m²) · Animais · Dens. Ref. · Sombra/Abrigo · Ações

### `/propriedades`

- **Propriedades Cadastradas**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Adicionar Nova Propriedade · Voltar ao Dashboard · Mais
- Colunas: ID · Nome · Área (ha) · Endereço · Cidade/Estado · Imagens · Ações

### `/lavouras`

- **Lavouras**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Nova Lavoura · Voltar · Mais
- Colunas: ID · Nome · Propriedade · Cultura · Área (ha) · Plantio · Ações
- Formulário `POST http://localhost/app/modulos/caderno_campo/lavouras/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/lavouras/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/lavouras/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/lavouras/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/lavouras/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/lavouras/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/lavouras/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/lavouras/excluir.php` — campos: id:hidden, csrf_token:hidden

### `/insumos`

- **Insumos Cadastrados**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Adicionar Novo Insumo · Voltar ao Dashboard · Mais
- Colunas: ID · Nome · Descrição · Associado · Fornecedor · Est. Anterior · Quant. Recebida · Quant. Estoque · Ações
- Formulário `POST http://localhost/app/modulos/caderno_campo/insumos/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/insumos/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/insumos/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/insumos/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/insumos/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/insumos/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/insumos/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/insumos/excluir.php` — campos: id:hidden, csrf_token:hidden

### `/produtos`

- **Produtos Cadastrados**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Adicionar Novo Produto · Voltar ao Dashboard · Mais
- Colunas: ID · Nome · Tipo · Unidade · Quantidade · Data Colheita · Ações
- Formulário `POST http://localhost/app/modulos/caderno_campo/produtos/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/produtos/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/produtos/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/produtos/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/produtos/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/produtos/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/produtos/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/produtos/excluir.php` — campos: id:hidden, csrf_token:hidden

### `/lotes`

- **Lotes de Produção Cadastrados**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Adicionar Novo Lote · Voltar ao Dashboard · Rastrear · Mais
- Colunas: ID · QR Code · Nome do Lote · Produto · Data Produção · Quantidade/Unidade · Ações
- Formulário `POST http://localhost/app/modulos/caderno_campo/lotes_producao/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/lotes_producao/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/lotes_producao/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/lotes_producao/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/lotes_producao/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/lotes_producao/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/lotes_producao/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/lotes_producao/excluir.php` — campos: id:hidden, csrf_token:hidden

### `/fornecedor`

- **Fornecedores Cadastrados**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Adicionar Novo Fornecedor · Mais
- Colunas: ID · Nome · Representante · Unidades Atendidas · Cidade · Estado · Ações

### `/atividades-diarias`

- **Atividades Diárias**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Adicionar Nova Atividade · Voltar ao Dashboard · Mais
- Colunas: # · Nome · Data · Horário · Tipo · Categoria · Status · Propriedade · Lavoura · Associado · Ações
- Formulário `POST http://localhost/app/modulos/caderno_campo/atividade_diaria/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/atividade_diaria/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/atividade_diaria/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/atividade_diaria/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/atividade_diaria/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/atividade_diaria/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/atividade_diaria/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/atividade_diaria/excluir.php` — campos: id:hidden, csrf_token:hidden

### `/caderno-campo/ocorrencias`

- **Ocorrências de Manejo**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Exportar · Nova Ocorrência · Todos · Aberta · Em Correção · Resolvida · Fechada · Cancelar · Confirmar · Mais
- Colunas: ID · Produtor · Tipo · Nome Comum · Propriedade · Lavoura · Data Detecção · Severidade · Status · Ações
- Formulário `GET (mesma página)` — campos: id:hidden, status:select, correcoes_realizadas:textarea

### `/rastreabilidade`

- **404**
- Ações: Voltar para o início

### `/relatorios`

- **Relatórios · 30 · 16**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Gerar PDF · Exportar Excel · Filtrar · Personalizar Relatório · Cancelar · Gerar Relatório · Mais
- Formulário `GET associados.php` — campos: nome_entidade:select, nome_responsavel:text
- Formulário `GET http://localhost/app/relatorios/financeiro.php` — campos: nome_entidade:select, data_inicio:date, data_fim:date
- Formulário `GET (mesma página)` — campos: (nenhum)

### `/certificacoes`

- **Certificações Participativas**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Nova Certificação · Voltar · Aplicar Filtros · Mais
- Colunas: ID · Tipo · Nº Certificado · Associado · Status · Emissão · Validade · Organismo · Ações

### `/certificadoras_list.php`

- **Certificadoras**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Nova Certificadora · Mais
- Colunas: Nome · Cidade/UF · Contato · Logo · Ações

### `/planos-manejo`

- **Planos de Manejo Orgânico**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Novo Plano · Todos 0 · Aprovados 0 · Em Análise 0 · Cancelar · Confirmar · Mais
- Colunas: Safra · Tipo · Propriedade · Agricultor · Status · Versão · Data Criação · Ações

### `/insumos-aprovados`

- **Insumos Orgânicos Aprovados**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Mais
- Colunas: Categoria · Nome Comercial · Princípio Ativo / Composição · Fabricante / Marca · Status

### `/visitas-tecnicas`

- **Visitas Técnicas e Inspeções**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Agendar Visita · Agendar · Confirmar Reagendamento · Mais
- Colunas: Data · Propriedade · Agricultor · Roteiro · Ciente · Status · Conformidade · Ações
- Formulário `GET (mesma página)` — campos: associado_id:select, propriedade_id:select, roteiro_id:select, data_visita:date
- Formulário `GET (mesma página)` — campos: visita_id:hidden, data_visita:date, hora_inicio:time

### `/visita-tecnica/agendar`

- **Agendar Nova Inspeção**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar para Listagem · Cancelar · Confirmar Agendamento · Mais
- Formulário `GET (mesma página)` — campos: associado_id:select, propriedade_id:select, roteiro_id:select, data_visita:date, tecnico_responsavel_id:select, acompanhantes:text

### `/roteiros-inspecao`

- **Templates de Roteiros**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Novo Roteiro · Criar Template · Mais
- Colunas: Código · Nome · Tipo · Versão · Itens · Ações
- Formulário `GET (mesma página)` — campos: nome:text, tipo:select, checklist:textarea

### `/nao-conformidades`

- **Não Conformidades**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Limpar · Mais
- Colunas: Data Visita · Produtor · Propriedade · Descrição · Gravidade · Prazo · Status · Ações

### `/caderno-campo/insumos/validacao`

- **Insumos Cadastrados**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Adicionar Novo Insumo · Voltar ao Dashboard · Mais
- Colunas: ID · Nome · Descrição · Associado · Fornecedor · Est. Anterior · Quant. Recebida · Quant. Estoque · Ações
- Formulário `POST http://localhost/app/modulos/caderno_campo/insumos/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/insumos/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/insumos/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/insumos/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/insumos/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/insumos/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/insumos/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/insumos/excluir.php` — campos: id:hidden, csrf_token:hidden

### `/relatorios-admin`

- **Relatórios Administrativos**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Aplicar Filtros · Limpar Filtros · Usuários · Associados · Associações · Fornecedores · Contas · Mais
- Colunas: ID · Nome · CPF · RG · E-mail · Tipo · Status · Último Acesso · Telefone · Cidade · UF · CNPJ · Total Associados · Total Propriedades · Razão Social · Nome Fantasia · CNPJ/CPF · Descrição · Valor · Vencimento · Responsável
- Formulário `POST (mesma página)` — campos: data_inicio:date, data_fim:date, status:select, tipo:select

### `/relatorios-conformidade`

- **Relatórios de Conformidade Orgânica**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Aplicar Filtros · Limpar · Mais
- Formulário `GET (mesma página)` — campos: data_inicio:date, data_fim:date, status:select, associado_id:select

### `/consulta-cnpo`

- **Consulta CNPO**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Buscar no CNPO · Limpar · Mais
- Colunas: Produtor · Entidade (certificadora/OCS) · Tipo · Município · UF · Escopo · Situação
- Formulário `GET (mesma página)` — campos: termo:text, uf:select, cidade:text, tipo_entidade:select

### `/usuarios`

- **Usuários**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Adicionar Novo Usuário · Gerar PDF · Mais
- Colunas: ID · Nome · E-mail · Nível · Status · Ações

### `/associados/pendentes`

- **Usuários Pendentes**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Ver Todos os Associados · Confirmar acesso · Mais
- Colunas: # · Nome · E-mail · Telefone · Perfil · Privilégio solicitado · Data do Cadastro · Confirmação do Admin (privilégios)
- Formulário `POST (mesma página)` — campos: csrf_token:hidden, id:hidden, origem:hidden, acao:hidden, nivel_concedido:select
- Formulário `POST (mesma página)` — campos: csrf_token:hidden, id:hidden, origem:hidden, acao:hidden, motivo:text

### `/meu-perfil`

- **Meu Perfil**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar ao Início · Salvar Alterações · Adicionar Nova Propriedade · Cancelar · Carregar · Mais
- Formulário `POST http://localhost/app/meu-perfil` — campos: csrf_token:hidden, nome:text, sobrenome:text, email:email, telefone:text, cpf:text, data_nascimento:date, cep:text, endereco:text, numero:text, bairro:text, complemento:text, estado:select, cidade:select, status:select, foto:file
- Formulário `GET http://localhost/app/meu-perfil` — campos: gerenciar_associado_id:select

### `/usuario/configuracoes`

- **Configurações**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Editar Meu Perfil · Exportar Dados · Ajuda · Excluir Conta · Salvar Configurações · Voltar · Configurar 2FA · Ver Sessões · Salvar Notificações · Mais
- Formulário `POST (mesma página)` — campos: tema:select, idioma:select
- Formulário `POST http://localhost/app/usuario/salvar_notificacoes` — campos: notificacoes_ativas:checkbox, dias_antecedencia:select, notif_eventos_hoje:checkbox, notif_eventos_proximos:checkbox, notif_eventos_atrasados:checkbox

### `/evento/novo`

- **Cadastrar Evento**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar · Cadastrar · Cancelar · Mais
- Formulário `POST http://localhost/app/eventos/cadastrar` — campos: csrf_token:hidden, nome:text, descricao:textarea, data_evento:datetime-local, local:text, status:select

### `/associado/novo`

- **Cadastrar Associado**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar à lista · Cadastrar · Cancelar · Mais
- Formulário `POST http://localhost/app/associado/novo` — campos: csrf_token:hidden, nome:text, sobrenome:text, email:email, data_nascimento:date, senha:password, confirmar_senha:password, cpf:text, rg:text, tipo_usuario:select, tipo_associado:select, status_associado:select, data_admissao:date, profissao:text, data_desligamento:date, endereco:text, numero:text, complemento:text, bairro:text, cep:text, cidade:text, estado:select, associacoes[]:checkbox, associacoes[]:checkbox, associacoes[]:checkbox, associacoes_enviado:hidden

### `/associados/relatorio`

- **Gerar Relatório de Associados**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Gerar PDF · Exportar Excel · Mais
- Formulário `POST http://localhost/app/api/relatorios/relatorio_associados.php` — campos: nome_associacao:text, nome_responsavel:text

### `/associado/73/editar`

- **Editar Associado**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Atribuir Cargo · Salvar Alterações · Cadastrar Propriedade · Voltar · Cancelar · Salvar Cargo · Mais
- Formulário `POST http://localhost/app/associado/73/editar` — campos: csrf_token:hidden, nome:text, email:email, cpf:text, telefone:text, cep:text, endereco:text, estado:select, cidade:select, foto:file

### `/cadastro-ficha/visualizar?id=20`

- **Ficha de Adesão ao Quadro Associativo**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Imprimir · Voltar aos Pendentes · Aprovar Ficha · Recusar · Cancelar · Confirmar Recusa · Mais
- Colunas: # · Produto / Cultura · Variedade / Espécie · Produção Estimada · Unidade · Destino Principal
- Formulário `POST (mesma página)` — campos: csrf_token:hidden, acao:hidden, ficha_id:hidden
- Formulário `POST (mesma página)` — campos: csrf_token:hidden, acao:hidden, ficha_id:hidden, motivo:textarea

### `/associado/73/carteirinha-impressa`


### `/associado/73/carteirinha-enviar`

- **404**
- Ações: Voltar para o início

### `/associacao/novo`

- **Cadastrar Nova Associação**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Cadastrar Associação · Cancelar · Mais
- Formulário `POST http://localhost/app/associacao/novo` — campos: nome:text, sigla:text, descricao:textarea, objetivo:textarea, data_fundacao:date, responsavel:text, cargo_responsavel:text, telefone:text, email:email, site:url, endereco:text, estado:select, cidade:select, cep:text

### `/conta/novo`

- **Cadastrar Conta**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Salvar · Voltar · Mais
- Formulário `POST (mesma página)` — campos: nome:text, tipo:select, valor:text, data:date

### `/conta/4/editar`

- **Editar Conta**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Salvar Alterações · Voltar · Mais
- Formulário `POST (mesma página)` — campos: nome:text, tipo:select, valor:text, data:date

### `/mensalidade/novo`

- **Lançamento Manual**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar · Lançar · Cancelar · Mais
- Formulário `POST (mesma página)` — campos: csrf_token:hidden, associado_id:select, associacao_id:select, ano_referencia:number, mes_referencia:select, valor:text, data_vencimento:date, descricao:text, observacoes:text, data_pagamento:date, metodo_pagamento:select

### `/planos`

- **Planos de Mensalidade**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Novo Plano · Voltar · Mais
- Colunas: Nome · Tipo · Associação · Valor · Dia Venc. · Situação · Ações
- Formulário `POST http://localhost/app/plano/2/excluir` — campos: csrf_token:hidden

### `/caixa/lancamento`

- **Novo Lançamento**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar · Registrar · Cancelar · Mais
- Formulário `POST (mesma página)` — campos: csrf_token:hidden, tipo:select, data:date, descricao:text, valor:text, categoria:text, associacao_id:select

### `/eventos/presencas`

- **Presença em Eventos**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar aos Eventos · Mais
- Colunas: Evento · Associado · Status · Data Evento

### `/evento/9/editar`

- **Editar Evento**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Salvar Alterações · Voltar · Gerar Lista (PDF) · Mais
- Colunas: Associado · Status de Presença
- Formulário `POST (mesma página)` — campos: nome:text, data_evento:date, local:text, descricao:textarea, status:select

### `/assembleia/novo`

- **Nova Assembleia**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar · Criar Assembleia · Cancelar · Mais
- Formulário `POST (mesma página)` — campos: csrf_token:hidden, titulo:text, descricao:textarea, data_inicio:datetime-local, data_fim:datetime-local, associacao_id:select, tipo_votacao:select, quorum_minimo:number

### `/assembleia/1/editar`

- **Editar Assembleia**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar · Salvar Datas · Cancelar · Mais
- Formulário `POST (mesma página)` — campos: csrf_token:hidden, titulo:text, descricao:textarea, data_inicio:datetime-local, data_fim:datetime-local

### `/assembleia/1`

- **Assembleia Geral Ordinária-Teste**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Editar · Resultado · Encerrar · Lista · Ver Resultado · Mais

### `/assembleia/1/resultado`

- **Resultado da Assembleia**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Exportar CSV · Voltar · Mais
- Colunas: Opção · Votos · % · Visualização

### `/assembleia/1/encerrar`

- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Sim, Encerrar Agora · Cancelar · Mais
- Formulário `POST (mesma página)` — campos: csrf_token:hidden

### `/agroindustria/ordens/nova`

- **Nova Ordem de Produção**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar · Adicionar insumo · Registrar Ordem de Produção · Mais
- Formulário `POST (mesma página)` — campos: receita_id:select, lote_processado_final:select, data_processamento:datetime-local, quantidade_final_obtida:number, unidade_medida:select, higienizacao_previa_id:select, item_insumo[]:select, item_quantidade[]:number, item_unidade[]:text, item_nf[]:text, observacoes:textarea

### `/agroindustria/minha/nova`

- **Nova Agroindústria**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar · Salvar Agroindústria · Mais
- Formulário `POST (mesma página)` — campos: csrf_token:hidden, associado_id:select, nome_fantasia:text, razao_social:text, cnpj:text, inscricao_estadual:text, status:select, endereco:text, numero:text, bairro:text, cidade:text, estado:select, cep:text, telefone:text, email:email, responsavel_tecnico:text, observacoes:text

### `/agroindustria/minha/4/editar`

- **Editar Agroindústria**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar · Salvar Alterações · Adicionar Membro · Mais
- Formulário `POST (mesma página)` — campos: csrf_token:hidden, nome_fantasia:text, razao_social:text, cnpj:text, inscricao_estadual:text, status:select, endereco:text, numero:text, bairro:text, cidade:text, estado:select, cep:text, telefone:text, email:email, responsavel_tecnico:text, observacoes:text
- Formulário `POST (mesma página)` — campos: csrf_token:hidden, acao_membro:hidden, associado_membro_id:select

### `/agroindustria/setor/novo`

- **Novo Setor**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar · Salvar Setor · Mais
- Formulário `POST (mesma página)` — campos: nome:text, associado_id:select, agroindustria_id:select, descricao:textarea

### `/agroindustria/setor/7/editar`

- **Editar Setor**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar · Salvar Alterações · Mais
- Formulário `POST (mesma página)` — campos: nome:text, agroindustria_id:select, descricao:textarea

### `/agroindustria/categoria/novo`

- **Nova Categoria de Atividade**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar · Salvar Categoria · Mais
- Formulário `POST (mesma página)` — campos: nome:text, cor:color, descricao:textarea

### `/agroindustria/categoria/4/editar`

- **Editar Categoria de Atividade**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar · Salvar Alterações · Mais
- Formulário `POST (mesma página)` — campos: nome:text, cor:color, descricao:textarea

### `/agroindustria/atividade/novo`

- **Nova Atividade Diária**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar · Salvar Atividade · Mais
- Formulário `POST (mesma página)` — campos: nome:text, data_atividade:date, status:select, setor_id:select, categoria_id:select, tipo_atividade:select, hora_inicio:time, hora_fim:time, insumo_utilizado:text, descricao:textarea, observacoes:textarea

### `/agroindustria/atividade/2/editar`

- **Editar Atividade Diária**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar · Salvar Alterações · Mais
- Formulário `POST (mesma página)` — campos: nome:text, data_atividade:date, status:select, setor_id:select, categoria_id:select, tipo_atividade:select, hora_inicio:time, hora_fim:time, insumo_utilizado:text, descricao:textarea, observacoes:textarea

### `/agroindustria/ocorrencia/novo`

- **Nova Ocorrência**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar · Salvar Ocorrência · Mais
- Formulário `POST (mesma página)` — campos: titulo:text, data_ocorrencia:date, tipo:select, setor_id:select, severidade:select, status:select, descricao:textarea, acao_manejo:textarea

### `/agroindustria/receitas/nova`

- **Nova Receita**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar · Adicionar ingrediente · Salvar Receita · Mais
- Formulário `POST (mesma página)` — campos: nome_produto_final:text, associado_id:select, status:select, descricao:textarea, ing_nome[]:text, ing_quantidade[]:number, ing_unidade[]:select, ing_organico[]:select, ing_obs[]:text

### `/agroindustria/receitas/8/editar`

- **Editar Receita**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar · Adicionar ingrediente · Salvar Alterações · Mais
- Formulário `POST (mesma página)` — campos: nome_produto_final:text, associado_id:select, status:select, descricao:textarea, ing_nome[]:text, ing_quantidade[]:number, ing_unidade[]:select, ing_organico[]:select, ing_obs[]:text, ing_nome[]:text, ing_quantidade[]:number, ing_unidade[]:select, ing_organico[]:select, ing_obs[]:text, ing_nome[]:text, ing_quantidade[]:number, ing_unidade[]:select, ing_organico[]:select, ing_obs[]:text

### `/agroindustria/higienizacao/nova`

- **Novo Registro de Higienização**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar · Registrar Higienização · Mais
- Formulário `POST (mesma página)` — campos: data_hora_inicio:datetime-local, data_hora_fim:datetime-local, equipamento_linha:text, sanitizante_utilizado:select, responsavel_nome:text, observacoes:textarea

### `/agroindustria/ordens/3`

- **Ordem de Produção LOTE-AGR-0001**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Nova · Voltar · Ver página pública · Imprimir · Mais
- Colunas: Lote (Campo) · Produto · Quantidade

### `/manejo-animal/novo`

- **Cadastrar Animal**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar · Cadastrar Animal · Mais
- Formulário `POST (mesma página)` — campos: nome_animal:text, especie:text, raca:text, identificacao:text, sexo:select, data_nascimento:date, associado_id:select, origem_conversao:select, status:select, lote_id:select, peso_kg:number, tipo_entrada:select, fornecedor_origem:text, data_entrada:date, observacoes_origem:textarea

### `/producao-animal/lote/novo`

- **Novo Lote de Animais**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar · Salvar Lote · Mais
- Formulário `POST (mesma página)` — campos: nome_lote:text, especie:select, categoria:text, quantidade:number, descricao:textarea

### `/producao-animal/alimentacao/novo`

- **Novo Registro de Alimentação**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar · Salvar Registro · Mais
- Formulário `POST (mesma página)` — campos: data_alimentacao:date, tipo_alimento:select, origem:select, animal_id:select, lote_id:select, produto_utilizado:text, quantidade_kg:number, percentual_materia_seca:number, observacoes:textarea

### `/producao-animal/sanidade/novo`

- **Novo Registro de Sanidade**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar · Salvar Registro · Mais
- Formulário `POST (mesma página)` — campos: data_tratamento:date, tipo_tratamento:select, produto_normatizado:checkbox, produto_utilizado:text, motivo:text, animal_id:select, lote_id:select, dose_quantidade:text, via_aplicacao:text, responsavel:text, periodo_carencia_horas:number, data_liberacao_organica:date, aprovado_ocs:select, numero_tratamento:number, observacoes:textarea

### `/producao-animal/reproducao/novo`

- **Novo Evento Reprodutivo**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar · Salvar Evento · Mais
- Formulário `POST (mesma página)` — campos: tipo_evento:select, data_evento:date, animal_id:select, macho_reprodutor_id:text, previsao_parto:date, grupo_reprodutivo:text, nascidos_vivos:number, natimortos:number, periodo_aleitamento_dias:number, especie:select, observacoes:textarea

### `/producao-animal/ambiente/novo`

- **Novo Ambiente de Criação**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar · Salvar Ambiente · Mais
- Formulário `POST (mesma página)` — campos: nome_instalacao:text, especie:select, sistema:select, tipo_instalacao:text, area_total_m2:number, quantidade_animais:number, densidade_referencia:number, tem_sombreamento_abrigo:checkbox, observacoes:textarea

### `/manejo-animal/4`

- **Capricho CONVENCIONAL**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Editar · Voltar · Adicionar · Mais
- Formulário `POST (mesma página)` — campos: tipo_registro:select, data_registro:date, descricao:text, produto_utilizado:text, dose_quantidade:text, responsavel:text, observacoes:textarea

### `/manejo-animal/4/editar`

- **Editar Animal**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar · Salvar Alterações · Mais
- Formulário `POST (mesma página)` — campos: nome_animal:text, especie:text, raca:text, identificacao:text, sexo:select, data_nascimento:date, associado_id:select, origem_conversao:select, status:select, lote_id:select, peso_kg:number, tipo_entrada:select, fornecedor_origem:text, data_entrada:date, observacoes_origem:textarea

### `/producao-animal/lote/9/editar`

- **Editar Lote de Animais**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar · Salvar Alterações · Mais
- Formulário `POST (mesma página)` — campos: nome_lote:text, especie:select, categoria:text, quantidade:number, descricao:textarea

### `/producao-animal/alimentacao/7/editar`

- **Editar Registro de Alimentação**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar · Salvar Alterações · Mais
- Formulário `POST (mesma página)` — campos: data_alimentacao:date, tipo_alimento:select, origem:select, animal_id:select, lote_id:select, produto_utilizado:text, quantidade_kg:number, percentual_materia_seca:number, observacoes:textarea

### `/producao-animal/sanidade/7/editar`

- **Editar Registro de Sanidade**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar · Salvar Alterações · Mais
- Formulário `POST (mesma página)` — campos: data_tratamento:date, tipo_tratamento:select, produto_normatizado:checkbox, produto_utilizado:text, motivo:text, animal_id:select, lote_id:select, dose_quantidade:text, via_aplicacao:text, responsavel:text, periodo_carencia_horas:number, data_liberacao_organica:date, aprovado_ocs:select, numero_tratamento:number, observacoes:textarea

### `/producao-animal/reproducao/6/editar`

- **Editar Evento Reprodutivo**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar · Salvar Alterações · Mais
- Formulário `POST (mesma página)` — campos: tipo_evento:select, data_evento:date, animal_id:select, macho_reprodutor_id:text, previsao_parto:date, grupo_reprodutivo:text, nascidos_vivos:number, natimortos:number, periodo_aleitamento_dias:number, especie:select, observacoes:textarea

### `/producao-animal/ambiente/5/editar`

- **Editar Ambiente de Criação**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar · Salvar Alterações · Mais
- Formulário `POST (mesma página)` — campos: nome_instalacao:text, especie:select, sistema:select, tipo_instalacao:text, area_total_m2:number, quantidade_animais:number, densidade_referencia:number, tem_sombreamento_abrigo:checkbox, observacoes:textarea

### `/caderno-campo/propriedade/novo`

- **Cadastrar Nova Propriedade**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Cadastrar Propriedade · Cancelar · Mais
- Formulário `POST http://localhost/app/caderno-campo/propriedade/novo` — campos: propriedade:text, area_total:number, endereco:text, cep:text, bairro:text, cidade:select, estado:select, latitude:text, longitude:text, imagem_principal:file, imagem_secundaria:file, observacoes:textarea

### `/caderno-campo/dashboard`

- **Painel de Caderno de Campo**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Acessar · Gerar Relatórios · Nova Atividade · Ver Todos · Agendar Evento · Mais
- Colunas: Nome · Data · Propriedade · Lavoura · Tipo · Status · Ações

### `/caderno-campo/propriedade/28/editar`

- **Editar Propriedade**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Atualizar Propriedade · Cancelar · Mais · ' + 'Reagendar' + ' · ' + 'Confirmar reagendamento' + '
- Formulário `POST (mesma página)` — campos: propriedade:text, area_total:number, endereco:text, cep:text, bairro:text, cidade:text, estado:select, latitude:text, longitude:text, imagem_principal:file, imagem_secundaria:file, observacoes:textarea, zona_amortecimento:textarea

### `/caderno-campo/propriedade/28/imagens`

- **Imagens da Propriedade**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar à Lista · Editar Propriedade · Download · Mais

### `/propriedade/28/historico-conversao`

- **404**
- Ações: Voltar para o início

### `/caderno-campo/lavoura/novo`

- **Cadastrar Nova Lavoura**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar para Lista · Cadastrar Lavoura · Cancelar · Mais
- Formulário `POST http://localhost/app/caderno-campo/lavoura/novo` — campos: nome:text, propriedade_id:select, cultura:text, area:number, data_plantio:date

### `/`

- **Painel Administrativo**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Ver Todos · Agendar Evento · Mais

### `/caderno-campo/lavoura/0fb8cb91-aab5-44a0-bb06-ab0504d71b98/editar`

- **Editar Lavoura**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Listar Lavouras · Atualizar Lavoura · Mais
- Formulário `POST (mesma página)` — campos: propriedade_id:select, cultura:text, area:number, data_plantio:date, data_colheita_prevista:date, status:select, observacoes:textarea

### `/caderno-campo/lavoura/e9c7c898-ecbf-46f6-baa0-b8f898a936b5/editar`

- **Editar Lavoura**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Listar Lavouras · Atualizar Lavoura · Mais
- Formulário `POST (mesma página)` — campos: propriedade_id:select, cultura:text, area:number, data_plantio:date, data_colheita_prevista:date, status:select, observacoes:textarea

### `/caderno-campo/lavoura/98ef79e5-1dae-4caf-a4f4-b28b07993985/editar`

- **Editar Lavoura**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Listar Lavouras · Atualizar Lavoura · Mais
- Formulário `POST (mesma página)` — campos: propriedade_id:select, cultura:text, area:number, data_plantio:date, data_colheita_prevista:date, status:select, observacoes:textarea

### `/caderno-campo/lavoura/891a0701-4b60-4184-986f-2b080a0caf48/editar`

- **Editar Lavoura**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Listar Lavouras · Atualizar Lavoura · Mais
- Formulário `POST (mesma página)` — campos: propriedade_id:select, cultura:text, area:number, data_plantio:date, data_colheita_prevista:date, status:select, observacoes:textarea

### `/caderno-campo/lavoura/0c716f4d-4a76-49f7-ab1f-a090d633f1e5/editar`

- **Editar Lavoura**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Listar Lavouras · Atualizar Lavoura · Mais
- Formulário `POST (mesma página)` — campos: propriedade_id:select, cultura:text, area:number, data_plantio:date, data_colheita_prevista:date, status:select, observacoes:textarea

### `/caderno-campo/lavoura/c1679c2d-bc39-4153-b8bd-eaa6bdb08f28/editar`

- **Editar Lavoura**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Listar Lavouras · Atualizar Lavoura · Mais
- Formulário `POST (mesma página)` — campos: propriedade_id:select, cultura:text, area:number, data_plantio:date, data_colheita_prevista:date, status:select, observacoes:textarea

### `/caderno-campo/lavoura/bb2f57e1-2aac-4f2d-bf24-4ba97b2f52a5/editar`

- **Editar Lavoura**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Listar Lavouras · Atualizar Lavoura · Mais
- Formulário `POST (mesma página)` — campos: propriedade_id:select, cultura:text, area:number, data_plantio:date, data_colheita_prevista:date, status:select, observacoes:textarea

### `/caderno-campo/lavoura/fa6bacf7-e2e5-448e-bde4-64cccb2187ef/editar`

- **Editar Lavoura**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Listar Lavouras · Atualizar Lavoura · Mais
- Formulário `POST (mesma página)` — campos: propriedade_id:select, cultura:text, area:number, data_plantio:date, data_colheita_prevista:date, status:select, observacoes:textarea

### `/caderno-campo/insumo/novo`

- **Cadastrar Novo Insumo**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Listar Insumos · Escolher da lista · Cadastrar Insumo · Voltar · Cancelar · Usar este Insumo · Mais
- Formulário `POST (mesma página)` — campos: nome:text, associado_id:select, descricao:textarea, fornecedor_id:select, data_validade:date, estoq_anterior:number, quant_recebida:number, quantidade_estoque:number, observacoes:textarea

### `/caderno-campo/insumo/f09485b9-7d22-4ac6-970e-d3af67b45be4/editar`

- **Editar Insumo**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Listar Insumos · Atualizar Insumo · Mais
- Formulário `POST (mesma página)` — campos: associado_id:select, nome:text, descricao:textarea, fornecedor_id:select, quantidade_estoque:number, estoq_anterior:number, quant_recebida:number

### `/caderno-campo/insumo/512f4cd6-4172-43f8-9183-d543fd2905b2/editar`

- **Editar Insumo**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Listar Insumos · Atualizar Insumo · Mais
- Formulário `POST (mesma página)` — campos: associado_id:select, nome:text, descricao:textarea, fornecedor_id:select, quantidade_estoque:number, estoq_anterior:number, quant_recebida:number

### `/caderno-campo/insumo/ff7a3fb4-6fff-4299-aba9-43a97b107346/editar`

- **Editar Insumo**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Listar Insumos · Atualizar Insumo · Mais
- Formulário `POST (mesma página)` — campos: associado_id:select, nome:text, descricao:textarea, fornecedor_id:select, quantidade_estoque:number, estoq_anterior:number, quant_recebida:number

### `/caderno-campo/insumo/b5e7691a-824a-478d-8621-8a8f4b991670/editar`

- **Editar Insumo**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Listar Insumos · Atualizar Insumo · Mais
- Formulário `POST (mesma página)` — campos: associado_id:select, nome:text, descricao:textarea, fornecedor_id:select, quantidade_estoque:number, estoq_anterior:number, quant_recebida:number

### `/caderno-campo/insumo/b0e88f1c-6399-4f77-906e-d0898393a926/editar`

- **Editar Insumo**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Listar Insumos · Atualizar Insumo · Mais
- Formulário `POST (mesma página)` — campos: associado_id:select, nome:text, descricao:textarea, fornecedor_id:select, quantidade_estoque:number, estoq_anterior:number, quant_recebida:number

### `/caderno-campo/insumo/103dc349-b131-4b3f-8348-143f0502c052/editar`

- **Editar Insumo**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Listar Insumos · Atualizar Insumo · Mais
- Formulário `POST (mesma página)` — campos: associado_id:select, nome:text, descricao:textarea, fornecedor_id:select, quantidade_estoque:number, estoq_anterior:number, quant_recebida:number

### `/caderno-campo/insumo/8bc21dba-2d79-4eb3-8af2-7d2bda7fff28/editar`

- **Editar Insumo**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Listar Insumos · Atualizar Insumo · Mais
- Formulário `POST (mesma página)` — campos: associado_id:select, nome:text, descricao:textarea, fornecedor_id:select, quantidade_estoque:number, estoq_anterior:number, quant_recebida:number

### `/caderno-campo/insumo/7728cce6-428a-4b11-bc6f-21b3f54720f5/editar`

- **Editar Insumo**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Listar Insumos · Atualizar Insumo · Mais
- Formulário `POST (mesma página)` — campos: associado_id:select, nome:text, descricao:textarea, fornecedor_id:select, quantidade_estoque:number, estoq_anterior:number, quant_recebida:number

### `/caderno-campo/insumo/27bbe717-5c00-4e2c-b891-2b9ed663b224/editar`

- **Editar Insumo**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Listar Insumos · Atualizar Insumo · Mais
- Formulário `POST (mesma página)` — campos: associado_id:select, nome:text, descricao:textarea, fornecedor_id:select, quantidade_estoque:number, estoq_anterior:number, quant_recebida:number

### `/caderno-campo/insumo/b6b9c20e-d58e-49b3-a977-efa1ef4994bc/editar`

- **Editar Insumo**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Listar Insumos · Atualizar Insumo · Mais
- Formulário `POST (mesma página)` — campos: associado_id:select, nome:text, descricao:textarea, fornecedor_id:select, quantidade_estoque:number, estoq_anterior:number, quant_recebida:number

### `/caderno-campo/insumo/49f4aed3-080d-4cc0-8829-c9f186fe3369/editar`

- **Editar Insumo**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Listar Insumos · Atualizar Insumo · Mais
- Formulário `POST (mesma página)` — campos: associado_id:select, nome:text, descricao:textarea, fornecedor_id:select, quantidade_estoque:number, estoq_anterior:number, quant_recebida:number

### `/caderno-campo/insumo/fd87d611-8ed9-4922-8f39-caf14243c6f7/editar`

- **Editar Insumo**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Listar Insumos · Atualizar Insumo · Mais
- Formulário `POST (mesma página)` — campos: associado_id:select, nome:text, descricao:textarea, fornecedor_id:select, quantidade_estoque:number, estoq_anterior:number, quant_recebida:number

### `/caderno-campo/insumo/38facc8a-a275-4bc0-a112-41bc3450c4b3/editar`

- **Editar Insumo**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Listar Insumos · Atualizar Insumo · Mais
- Formulário `POST (mesma página)` — campos: associado_id:select, nome:text, descricao:textarea, fornecedor_id:select, quantidade_estoque:number, estoq_anterior:number, quant_recebida:number

### `/caderno-campo/insumo/c1ad7ed7-df79-4ae1-b5bf-b8ebf87cf002/editar`

- **Editar Insumo**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Listar Insumos · Atualizar Insumo · Mais
- Formulário `POST (mesma página)` — campos: associado_id:select, nome:text, descricao:textarea, fornecedor_id:select, quantidade_estoque:number, estoq_anterior:number, quant_recebida:number

### `/caderno-campo/produto/novo`

- **Cadastrar Novo Produto**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Cadastrar Produto · Listar Produtos · Mais
- Formulário `POST http://localhost/app/caderno-campo/produto/novo` — campos: nome:text, tipo:text, descricao:textarea, unidade_medida:select, preco_unitario:text, quantidade_produzida:text, data_colheita:date, lavoura_id:select, unidade_tipo:select, unidade_id:select, observacoes:textarea

### `/caderno-campo/produto/8764e82c-b682-4c56-bebf-7d02136d42dc/editar`

- **Editar Produto**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Listar Produtos · Salvar Alterações · Mais
- Formulário `POST (mesma página)` — campos: nome:text, tipo:text, descricao:textarea, unidade_medida:select, preco_unitario:text, quantidade_produzida:text, data_colheita:date, lavoura_id:select, unidade_tipo:select, unidade_id:select, observacoes:textarea

### `/caderno-campo/produto/e5a27e7c-f7b7-4965-838e-ae676b0562ba/editar`

- **Editar Produto**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Listar Produtos · Salvar Alterações · Mais
- Formulário `POST (mesma página)` — campos: nome:text, tipo:text, descricao:textarea, unidade_medida:select, preco_unitario:text, quantidade_produzida:text, data_colheita:date, lavoura_id:select, unidade_tipo:select, unidade_id:select, observacoes:textarea

### `/caderno-campo/produto/d48e82a3-23f0-4679-88d2-adad65a89fce/editar`

- **Editar Produto**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Listar Produtos · Salvar Alterações · Mais
- Formulário `POST (mesma página)` — campos: nome:text, tipo:text, descricao:textarea, unidade_medida:select, preco_unitario:text, quantidade_produzida:text, data_colheita:date, lavoura_id:select, unidade_tipo:select, unidade_id:select, observacoes:textarea

### `/caderno-campo/produto/b0765463-8e06-40e0-841f-5aa1c7844abb/editar`

- **Editar Produto**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Listar Produtos · Salvar Alterações · Mais
- Formulário `POST (mesma página)` — campos: nome:text, tipo:text, descricao:textarea, unidade_medida:select, preco_unitario:text, quantidade_produzida:text, data_colheita:date, lavoura_id:select, unidade_tipo:select, unidade_id:select, observacoes:textarea

### `/caderno-campo/produto/44f80253-8929-47ca-a9d4-96279d6f9e98/editar`

- **Editar Produto**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Listar Produtos · Salvar Alterações · Mais
- Formulário `POST (mesma página)` — campos: nome:text, tipo:text, descricao:textarea, unidade_medida:select, preco_unitario:text, quantidade_produzida:text, data_colheita:date, lavoura_id:select, unidade_tipo:select, unidade_id:select, observacoes:textarea

### `/caderno-campo/produto/ca5d98ec-3bce-4b1f-a3d7-93e3bd7be533/editar`

- **Editar Produto**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Listar Produtos · Salvar Alterações · Mais
- Formulário `POST (mesma página)` — campos: nome:text, tipo:text, descricao:textarea, unidade_medida:select, preco_unitario:text, quantidade_produzida:text, data_colheita:date, lavoura_id:select, unidade_tipo:select, unidade_id:select, observacoes:textarea

### `/caderno-campo/produto/a1db3d3c-b498-41d1-9a10-e006cb7e8afe/editar`

- **Editar Produto**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Listar Produtos · Salvar Alterações · Mais
- Formulário `POST (mesma página)` — campos: nome:text, tipo:text, descricao:textarea, unidade_medida:select, preco_unitario:text, quantidade_produzida:text, data_colheita:date, lavoura_id:select, unidade_tipo:select, unidade_id:select, observacoes:textarea

### `/caderno-campo/produto/1617abf5-249b-454a-bbc4-8e4094914221/editar`

- **Editar Produto**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Listar Produtos · Salvar Alterações · Mais
- Formulário `POST (mesma página)` — campos: nome:text, tipo:text, descricao:textarea, unidade_medida:select, preco_unitario:text, quantidade_produzida:text, data_colheita:date, lavoura_id:select, unidade_tipo:select, unidade_id:select, observacoes:textarea

### `/caderno-campo/produto/3dd19d28-97bb-428b-afc4-f1d6d8d0db8e/editar`

- **Editar Produto**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Listar Produtos · Salvar Alterações · Mais
- Formulário `POST (mesma página)` — campos: nome:text, tipo:text, descricao:textarea, unidade_medida:select, preco_unitario:text, quantidade_produzida:text, data_colheita:date, lavoura_id:select, unidade_tipo:select, unidade_id:select, observacoes:textarea

### `/caderno-campo/produto/4954612c-9db4-4b64-a495-cf8872454a50/editar`

- **Editar Produto**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Listar Produtos · Salvar Alterações · Mais
- Formulário `POST (mesma página)` — campos: nome:text, tipo:text, descricao:textarea, unidade_medida:select, preco_unitario:text, quantidade_produzida:text, data_colheita:date, lavoura_id:select, unidade_tipo:select, unidade_id:select, observacoes:textarea

### `/caderno-campo/produto/8640d07a-bbe7-451d-8b42-30e517dab6c5/editar`

- **Editar Produto**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Listar Produtos · Salvar Alterações · Mais
- Formulário `POST (mesma página)` — campos: nome:text, tipo:text, descricao:textarea, unidade_medida:select, preco_unitario:text, quantidade_produzida:text, data_colheita:date, lavoura_id:select, unidade_tipo:select, unidade_id:select, observacoes:textarea

### `/caderno-campo/produto/a241a177-2aaf-47ca-a5aa-abc5a220ab49/editar`

- **Editar Produto**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Listar Produtos · Salvar Alterações · Mais
- Formulário `POST (mesma página)` — campos: nome:text, tipo:text, descricao:textarea, unidade_medida:select, preco_unitario:text, quantidade_produzida:text, data_colheita:date, lavoura_id:select, unidade_tipo:select, unidade_id:select, observacoes:textarea

### `/caderno-campo/produto/dc6064cd-f4d7-4900-b48a-ec0a2822e794/editar`

- **Editar Produto**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Listar Produtos · Salvar Alterações · Mais
- Formulário `POST (mesma página)` — campos: nome:text, tipo:text, descricao:textarea, unidade_medida:select, preco_unitario:text, quantidade_produzida:text, data_colheita:date, lavoura_id:select, unidade_tipo:select, unidade_id:select, observacoes:textarea

### `/caderno-campo/lote/novo`

- **Cadastrar Novo Lote de Produção**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Cadastrar Lote · Cancelar · Mais
- Formulário `POST (mesma página)` — campos: nome_lote:text, produto_id:select, data_producao:date, quantidade:number, unidade_medida:text, observacoes:textarea

### `/lote/0afd0e88-5ade-40a4-a45c-9d3559c70b01`

- **Rastreabilidade do Lote: LOTE-AVEIACOCO-002**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar para Lotes de Produção · Mais

### `/lote/0afd0e88-5ade-40a4-a45c-9d3559c70b01/editar`

- **Editar Lote de Produção**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Listar Lotes · Salvar Alterações · Cancelar · Mais
- Formulário `POST http://localhost/app/lote/0afd0e88-5ade-40a4-a45c-9d3559c70b01/editar` — campos: nome_lote:text, produto_id:select, data_producao:date, quantidade:text, unidade_medida:text, observacoes:textarea

### `/lote/161fa9f5-04ea-422c-9393-a2d6d9c87eb2`

- **Rastreabilidade do Lote: Lote de Cima**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar para Lotes de Produção · Mais
- Colunas: Data · Hora · Atividade · Tipo · Status · Descrição · Insumo · Data de Aplicação · Tipo de Atividade

### `/lote/161fa9f5-04ea-422c-9393-a2d6d9c87eb2/editar`

- **Editar Lote de Produção**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Listar Lotes · Salvar Alterações · Cancelar · Mais
- Formulário `POST http://localhost/app/lote/161fa9f5-04ea-422c-9393-a2d6d9c87eb2/editar` — campos: nome_lote:text, produto_id:select, data_producao:date, quantidade:text, unidade_medida:text, observacoes:textarea

### `/lote/e11e123c-7457-426b-9228-a6d40c6e7cb6`

- **Rastreabilidade do Lote: 1212-BM**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar para Lotes de Produção · Mais
- Colunas: Data · Hora · Atividade · Tipo · Status · Descrição · Insumo · Data de Aplicação · Tipo de Atividade

### `/lote/e11e123c-7457-426b-9228-a6d40c6e7cb6/editar`

- **Editar Lote de Produção**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Listar Lotes · Salvar Alterações · Cancelar · Mais
- Formulário `POST http://localhost/app/lote/e11e123c-7457-426b-9228-a6d40c6e7cb6/editar` — campos: nome_lote:text, produto_id:select, data_producao:date, quantidade:text, unidade_medida:text, observacoes:textarea

### `/lote/10afb836-061f-4d3f-83d5-06b9728edb61`

- **Rastreabilidade do Lote: LOTE-AGR-0001**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar para Lotes de Produção · Mais

### `/lote/10afb836-061f-4d3f-83d5-06b9728edb61/editar`

- **Editar Lote de Produção**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Listar Lotes · Salvar Alterações · Cancelar · Mais
- Formulário `POST http://localhost/app/lote/10afb836-061f-4d3f-83d5-06b9728edb61/editar` — campos: nome_lote:text, produto_id:select, data_producao:date, quantidade:text, unidade_medida:text, observacoes:textarea

### `/lote/a3db331b-6a35-4d99-ba64-0f70958d8451`

- **Rastreabilidade do Lote: Lote de baixo**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar para Lotes de Produção · Mais

### `/lote/a3db331b-6a35-4d99-ba64-0f70958d8451/editar`

- **Editar Lote de Produção**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Listar Lotes · Salvar Alterações · Cancelar · Mais
- Formulário `POST http://localhost/app/lote/a3db331b-6a35-4d99-ba64-0f70958d8451/editar` — campos: nome_lote:text, produto_id:select, data_producao:date, quantidade:text, unidade_medida:text, observacoes:textarea

### `/lote/6500a0cf-d4c5-405a-ae19-08b94f2c1c6c`

- **Rastreabilidade do Lote: Lote central área norte**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar para Lotes de Produção · Mais
- Colunas: Data · Hora · Atividade · Tipo · Status · Descrição · Insumo · Data de Aplicação · Tipo de Atividade

### `/lote/6500a0cf-d4c5-405a-ae19-08b94f2c1c6c/editar`

- **Editar Lote de Produção**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Listar Lotes · Salvar Alterações · Cancelar · Mais
- Formulário `POST http://localhost/app/lote/6500a0cf-d4c5-405a-ae19-08b94f2c1c6c/editar` — campos: nome_lote:text, produto_id:select, data_producao:date, quantidade:text, unidade_medida:text, observacoes:textarea

### `/lote/f6358c2e-22a3-4f85-abe8-fcde5daa8180`

- **Rastreabilidade do Lote: testeEdy**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar para Lotes de Produção · Mais
- Colunas: Data · Hora · Atividade · Tipo · Status · Descrição · Insumo · Data de Aplicação · Tipo de Atividade

### `/lote/f6358c2e-22a3-4f85-abe8-fcde5daa8180/editar`

- **Editar Lote de Produção**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Listar Lotes · Salvar Alterações · Cancelar · Mais
- Formulário `POST http://localhost/app/lote/f6358c2e-22a3-4f85-abe8-fcde5daa8180/editar` — campos: nome_lote:text, produto_id:select, data_producao:date, quantidade:text, unidade_medida:text, observacoes:textarea

### `/lote/f41eb70e-d499-4967-9bf0-4c448dc1847e`

- **Rastreabilidade do Lote: LOTE-SATT-001**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar para Lotes de Produção · Mais

### `/lote/f41eb70e-d499-4967-9bf0-4c448dc1847e/editar`

- **Editar Lote de Produção**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Listar Lotes · Salvar Alterações · Cancelar · Mais
- Formulário `POST http://localhost/app/lote/f41eb70e-d499-4967-9bf0-4c448dc1847e/editar` — campos: nome_lote:text, produto_id:select, data_producao:date, quantidade:text, unidade_medida:text, observacoes:textarea

### `/lote/2baa3cb8-0d86-4aea-bd1a-e70179f5809c`

- **Rastreabilidade do Lote: Lote 1-01072025**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar para Lotes de Produção · Mais

### `/lote/2baa3cb8-0d86-4aea-bd1a-e70179f5809c/editar`

- **Editar Lote de Produção**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Listar Lotes · Salvar Alterações · Cancelar · Mais
- Formulário `POST http://localhost/app/lote/2baa3cb8-0d86-4aea-bd1a-e70179f5809c/editar` — campos: nome_lote:text, produto_id:select, data_producao:date, quantidade:text, unidade_medida:text, observacoes:textarea

### `/fornecedor/novo`

- **Cadastrar Fornecedor**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar · Remover · + Adicionar unidade · Cadastrar · Cancelar · Mais
- Formulário `POST http://localhost/app/fornecedor/cadastrar` — campos: csrf_token:hidden, nome:text, cnpj:text, razao_social:text, representante:text, email:email, telefone:text, produto_fornecido:text, endereco:text, bairro:text, estado:select, unidade_tipo[]:select, unidade_id[]:select

### `/fornecedor/5/editar`

- **Editar Fornecedor**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Remover · + Adicionar unidade · Salvar Alterações · Cancelar · Mais
- Formulário `POST http://localhost/app/fornecedor/5/editar` — campos: nome:text, representante:text, razao_social:text, cnpj:text, telefone:text, email:email, produto_fornecido:text, endereco:text, bairro:text, estado:select, cidade:select, unidade_tipo[]:select, unidade_id[]:select, unidade_tipo[]:select, unidade_id[]:select

### `/api/relatorios/relatorio_atividades_usuario.php?formato=pdf`


### `/caderno-campo/atividade-diaria/novo`

- **Cadastrar Atividade Diária**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Salvar · Voltar · Mais
- Formulário `POST (mesma página)` — campos: csrf_token:hidden, nome:text, tipo_atividade:select, descricao:textarea, data_atividade:date, hora_inicio:time, hora_fim:time, propriedade_id:select, lavoura_id:select, associado_id:select, categoria_id:select, insumo_utilisado:select, status:select, observacoes:textarea, imagem_diaria:file

### `/caderno-campo/atividade-diaria/74599286-6fe4-44f7-a356-64cc9fc1d30b/editar`

- **Editar Atividade Diária**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Salvar Alterações · Voltar · Mais
- Formulário `POST (mesma página)` — campos: csrf_token:hidden, nome:text, tipo_atividade:select, descricao:textarea, data_atividade:date, hora_inicio:time, hora_fim:time, propriedade_id:select, lavoura_id:select, associado_id:select, categoria_id:select, insumo_utilisado:select, status:select, observacoes:textarea, imagem_diaria:file

### `/caderno-campo/atividade-diaria/af3e8250-a4b2-4495-bd7e-79b7c8ab99c6/editar`

- **Editar Atividade Diária**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Salvar Alterações · Voltar · Mais
- Formulário `POST (mesma página)` — campos: csrf_token:hidden, nome:text, tipo_atividade:select, descricao:textarea, data_atividade:date, hora_inicio:time, hora_fim:time, propriedade_id:select, lavoura_id:select, associado_id:select, categoria_id:select, insumo_utilisado:select, status:select, observacoes:textarea, imagem_diaria:file

### `/caderno-campo/atividade-diaria/4c0215a7-5eee-4bd4-820f-418b778c0edb/editar`

- **Editar Atividade Diária**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Salvar Alterações · Voltar · Mais
- Formulário `POST (mesma página)` — campos: csrf_token:hidden, nome:text, tipo_atividade:select, descricao:textarea, data_atividade:date, hora_inicio:time, hora_fim:time, propriedade_id:select, lavoura_id:select, associado_id:select, categoria_id:select, insumo_utilisado:select, status:select, observacoes:textarea, imagem_diaria:file

### `/caderno-campo/atividade-diaria/7df9b87d-982a-42dc-9f9a-190d32f4210a/imagens`

- **Imagens da Atividade**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar à Lista · Editar Atividade · Baixar Imagem · Download · Mais

### `/caderno-campo/atividade-diaria/7df9b87d-982a-42dc-9f9a-190d32f4210a/editar`

- **Editar Atividade Diária**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Remover Imagem · Salvar Alterações · Voltar · Mais
- Formulário `POST (mesma página)` — campos: csrf_token:hidden, nome:text, tipo_atividade:select, descricao:textarea, data_atividade:date, hora_inicio:time, hora_fim:time, propriedade_id:select, lavoura_id:select, associado_id:select, categoria_id:select, insumo_utilisado:select, status:select, observacoes:textarea, remover_imagem:checkbox, imagem_diaria:file

### `/caderno-campo/atividade-diaria/47967769-107f-49c3-bf24-8d9d03e3b0b2/imagens`

- **Imagens da Atividade**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar à Lista · Editar Atividade · Baixar Imagem · Download · Mais

### `/caderno-campo/atividade-diaria/47967769-107f-49c3-bf24-8d9d03e3b0b2/editar`

- **Editar Atividade Diária**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Remover Imagem · Salvar Alterações · Voltar · Mais
- Formulário `POST (mesma página)` — campos: csrf_token:hidden, nome:text, tipo_atividade:select, descricao:textarea, data_atividade:date, hora_inicio:time, hora_fim:time, propriedade_id:select, lavoura_id:select, associado_id:select, categoria_id:select, insumo_utilisado:select, status:select, observacoes:textarea, remover_imagem:checkbox, imagem_diaria:file

### `/caderno-campo/atividade-diaria/bded51dd-de5e-489c-96e7-70e274724c1d/imagens`

- **Imagens da Atividade**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar à Lista · Editar Atividade · Baixar Imagem · Download · Mais

### `/caderno-campo/atividade-diaria/bded51dd-de5e-489c-96e7-70e274724c1d/editar`

- **Editar Atividade Diária**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Remover Imagem · Salvar Alterações · Voltar · Mais
- Formulário `POST (mesma página)` — campos: csrf_token:hidden, nome:text, tipo_atividade:select, descricao:textarea, data_atividade:date, hora_inicio:time, hora_fim:time, propriedade_id:select, lavoura_id:select, associado_id:select, categoria_id:select, insumo_utilisado:select, status:select, observacoes:textarea, remover_imagem:checkbox, imagem_diaria:file

### `/caderno-campo/atividade-diaria/243b20f3-4279-4746-9f04-20736ab9be97/imagens`

- **Imagens da Atividade**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar à Lista · Editar Atividade · Baixar Imagem · Download · Mais

### `/caderno-campo/atividade-diaria/243b20f3-4279-4746-9f04-20736ab9be97/editar`

- **Editar Atividade Diária**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Remover Imagem · Salvar Alterações · Voltar · Mais
- Formulário `POST (mesma página)` — campos: csrf_token:hidden, nome:text, tipo_atividade:select, descricao:textarea, data_atividade:date, hora_inicio:time, hora_fim:time, propriedade_id:select, lavoura_id:select, associado_id:select, categoria_id:select, insumo_utilisado:select, status:select, observacoes:textarea, remover_imagem:checkbox, imagem_diaria:file

### `/caderno-campo/atividade-diaria/cb23dc59-ba87-4542-96a6-da1a769b2960/imagens`

- **Imagens da Atividade**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar à Lista · Editar Atividade · Baixar Imagem · Download · Mais

### `/caderno-campo/atividade-diaria/cb23dc59-ba87-4542-96a6-da1a769b2960/editar`

- **Editar Atividade Diária**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Remover Imagem · Salvar Alterações · Voltar · Mais
- Formulário `POST (mesma página)` — campos: csrf_token:hidden, nome:text, tipo_atividade:select, descricao:textarea, data_atividade:date, hora_inicio:time, hora_fim:time, propriedade_id:select, lavoura_id:select, associado_id:select, categoria_id:select, insumo_utilisado:select, status:select, observacoes:textarea, remover_imagem:checkbox, imagem_diaria:file

### `/caderno-campo/atividade-diaria/5d3740c0-5d92-4740-8c57-601586a8b3d6/editar`

- **Editar Atividade Diária**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Salvar Alterações · Voltar · Mais
- Formulário `POST (mesma página)` — campos: csrf_token:hidden, nome:text, tipo_atividade:select, descricao:textarea, data_atividade:date, hora_inicio:time, hora_fim:time, propriedade_id:select, lavoura_id:select, associado_id:select, categoria_id:select, insumo_utilisado:select, status:select, observacoes:textarea, imagem_diaria:file

### `/caderno-campo/atividade-diaria/d05a7a79-b70f-4675-8be9-2db4b613f52f/editar`

- **Editar Atividade Diária**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Salvar Alterações · Voltar · Mais
- Formulário `POST (mesma página)` — campos: csrf_token:hidden, nome:text, tipo_atividade:select, descricao:textarea, data_atividade:date, hora_inicio:time, hora_fim:time, propriedade_id:select, lavoura_id:select, associado_id:select, categoria_id:select, insumo_utilisado:select, status:select, observacoes:textarea, imagem_diaria:file

### `/caderno-campo/ocorrencia/novo`

- **Cadastrar Nova Ocorrência**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar para Lista · Salvar Ocorrência · Cancelar · Mais
- Formulário `POST http://localhost/app/caderno-campo/ocorrencia/novo` — campos: csrf_token:hidden, propriedade_id:select, lavoura_id:select, tipo:select, nome_comum:text, data_deteccao:date, severidade:select, descricao:textarea, acao_manejo:textarea, insumo_utilizado_id:select, foto:file

### `/modulos/associados/listar.php`

- **Lista de Associados**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Adicionar Novo · Gerar Relatório · Mais
- Colunas: Foto · Nome Completo · E-mail · Telefone · Cidade · Status · Nível · Ações

### `/modulos/propriedades/listar.php`

- **404**
- Ações: Voltar para o início

### `/modulos/caderno_campo/atividade_diaria/listar.php`

- **Atividades Diárias**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Adicionar Nova Atividade · Voltar ao Dashboard · Mais
- Colunas: # · Nome · Data · Horário · Tipo · Categoria · Status · Propriedade · Lavoura · Associado · Ações
- Formulário `POST http://localhost/app/modulos/caderno_campo/atividade_diaria/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/atividade_diaria/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/atividade_diaria/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/atividade_diaria/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/atividade_diaria/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/atividade_diaria/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/atividade_diaria/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/atividade_diaria/excluir.php` — campos: id:hidden, csrf_token:hidden

### `/modulos/caderno_campo/insumos/listar.php`

- **Insumos Cadastrados**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Adicionar Novo Insumo · Voltar ao Dashboard · Mais
- Colunas: ID · Nome · Descrição · Associado · Fornecedor · Est. Anterior · Quant. Recebida · Quant. Estoque · Ações
- Formulário `POST http://localhost/app/modulos/caderno_campo/insumos/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/insumos/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/insumos/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/insumos/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/insumos/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/insumos/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/insumos/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/insumos/excluir.php` — campos: id:hidden, csrf_token:hidden

### `/certificacao/novo`

- **Nova Certificação**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar · Cadastrar · Cancelar · Mais
- Formulário `POST http://localhost/app/certificacao/novo` — campos: csrf_token:hidden, associado_id:select, certificadora_id:select, numero_certificado:text, tipo_certificacao:select, data_emissao:date, data_vencimento:date, status:select, escopo:select, escopo:select, documento_url:url, declaracao_compromisso_url:url, observacoes:textarea

### `/certificacao/${row.id}/editar`

- **404**
- Ações: Voltar para o início

### `/certificacao/${row.id}/renovar`

- **404**
- Ações: Voltar para o início

### `/certificacao/historico/${row.associado_id}`

- **404**
- Ações: Voltar para o início

### `/certificacao/${row.id}/certificado?format=html`

- **404**
- Ações: Voltar para o início

### `/certificacao/${data.id}/certificado?format=html`

- **404**
- Ações: Voltar para o início

### `/certificadoras_cadastrar.php`

- **Cadastrar Certificadora**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Salvar · Mais
- Formulário `GET (mesma página)` — campos: nome:text, cidade:input, uf:input, contato:input, site:text, logo:file, logo_url:text

### `/plano-manejo/novo`

- **Plano de Manejo Orgânico**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Adicionar Cultura · Atualizar Preview · Anterior · Próximo · Concluir Plano · Mais
- Colunas: Cultura · Área (ha) · Tipo Semente
- Formulário `GET (mesma página)` — campos: propriedade_id:select, associado_id:select, ano_safra:text, titulo:text, area_organica_ha:number, area_conversao_ha:number, descricao_solo:textarea, topografia:select, manejo_pragas:textarea, manejo_doencas:textarea, praticas_conservacao:textarea, rotacao_culturas:textarea, compostagem:textarea, biofertilizantes:textarea, fonte_agua:textarea, clima:textarea, adubacao_verde:textarea, observacoes:textarea

### `/${baseUrl}/${row.id}/visualizar`

- **404**
- Ações: Voltar para o início

### `/${baseUrl}/${row.id}/editar`

- **404**
- Ações: Voltar para o início

### `/roteiro-inspecao/novo`

- **Novo Roteiro de Inspeção**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar para Listagem · Adicionar Item · Cancelar · Salvar Roteiro · Mais
- Formulário `GET (mesma página)` — campos: nome:text, codigo:text, tipo:select, pontuacao_minima:number, descricao:textarea, objetivo:textarea

### `/api/relatorios/admin_export.php?tipo=html&relatorio=usuarios`

- Ações: ← Fechar · Imprimir · Excel · PDF
- Colunas: ID · Nome · CPF · RG · E-mail · Tipo · Status · Último Acesso

### `/usuario/novo`

- **Cadastrar Usuário**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Salvar · Voltar · Mais
- Formulário `POST (mesma página)` — campos: csrf_token:hidden, associado_id:select, nome:text, email:email, senha:password, nivel:select, status:select

### `/usuario/110/editar`

- **Editar Usuário**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Salvar Alterações · Voltar · Mais
- Formulário `POST (mesma página)` — campos: csrf_token:hidden, associado_id:select, nome:text, email:email, senha:password, nivel:select, status:select

### `/plano/novo`

- **Novo Plano**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar · Salvar Plano · Cancelar · Mais
- Formulário `POST (mesma página)` — campos: csrf_token:hidden, nome:text, tipo:select, valor:text, dia_vencimento:number, ativo:checkbox, associacao_id:select

### `/plano/2/editar`

- **Editar Plano**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Voltar · Salvar Alterações · Cancelar · Mais
- Formulário `POST (mesma página)` — campos: csrf_token:hidden, nome:text, tipo:select, valor:text, dia_vencimento:number, ativo:checkbox, associacao_id:select

### `/modulos/eventos/relatorio_presenca.php?evento_id=9`


### `/caderno-campo/propriedades`

- **Propriedades Cadastradas**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Adicionar Nova Propriedade · Voltar ao Dashboard · Mais
- Colunas: ID · Nome · Área (ha) · Endereço · Cidade/Estado · Imagens · Ações

### `/caderno-campo/lavouras`

- **Lavouras**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Nova Lavoura · Voltar · Mais
- Colunas: ID · Nome · Propriedade · Cultura · Área (ha) · Plantio · Ações
- Formulário `POST http://localhost/app/modulos/caderno_campo/lavouras/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/lavouras/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/lavouras/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/lavouras/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/lavouras/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/lavouras/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/lavouras/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/lavouras/excluir.php` — campos: id:hidden, csrf_token:hidden

### `/caderno-campo/insumos`

- **Insumos Cadastrados**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Adicionar Novo Insumo · Voltar ao Dashboard · Mais
- Colunas: ID · Nome · Descrição · Associado · Fornecedor · Est. Anterior · Quant. Recebida · Quant. Estoque · Ações
- Formulário `POST http://localhost/app/modulos/caderno_campo/insumos/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/insumos/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/insumos/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/insumos/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/insumos/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/insumos/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/insumos/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/insumos/excluir.php` — campos: id:hidden, csrf_token:hidden

### `/caderno-campo/produtos`

- **Produtos Cadastrados**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Adicionar Novo Produto · Voltar ao Dashboard · Mais
- Colunas: ID · Nome · Tipo · Unidade · Quantidade · Data Colheita · Ações
- Formulário `POST http://localhost/app/modulos/caderno_campo/produtos/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/produtos/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/produtos/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/produtos/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/produtos/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/produtos/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/produtos/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/produtos/excluir.php` — campos: id:hidden, csrf_token:hidden

### `/caderno-campo/lotes`

- **Lotes de Produção Cadastrados**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Adicionar Novo Lote · Voltar ao Dashboard · Rastrear · Mais
- Colunas: ID · QR Code · Nome do Lote · Produto · Data Produção · Quantidade/Unidade · Ações
- Formulário `POST http://localhost/app/modulos/caderno_campo/lotes_producao/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/lotes_producao/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/lotes_producao/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/lotes_producao/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/lotes_producao/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/lotes_producao/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/lotes_producao/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/lotes_producao/excluir.php` — campos: id:hidden, csrf_token:hidden

### `/caderno-campo/categorias-atividades`

- **Categorias de Atividades**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Adicionar Nova Categoria · Voltar ao Dashboard · Mais
- Colunas: ID · Nome · Descrição · Cor · Associado · Data de Criação · Ações
- Formulário `POST http://localhost/app/modulos/caderno_campo/categorias_atividades/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/categorias_atividades/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/categorias_atividades/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/categorias_atividades/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/categorias_atividades/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/categorias_atividades/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/categorias_atividades/excluir.php` — campos: id:hidden, csrf_token:hidden

### `/caderno-campo/atividades-diarias`

- **Atividades Diárias**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Adicionar Nova Atividade · Voltar ao Dashboard · Mais
- Colunas: # · Nome · Data · Horário · Tipo · Categoria · Status · Propriedade · Lavoura · Associado · Ações
- Formulário `POST http://localhost/app/modulos/caderno_campo/atividade_diaria/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/atividade_diaria/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/atividade_diaria/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/atividade_diaria/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/atividade_diaria/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/atividade_diaria/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/atividade_diaria/excluir.php` — campos: id:hidden, csrf_token:hidden
- Formulário `POST http://localhost/app/modulos/caderno_campo/atividade_diaria/excluir.php` — campos: id:hidden, csrf_token:hidden

### `/caderno-campo/relatorios`

- **Relatórios**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Relatórios Administrativos · Aplicar Filtros · Limpar Filtros · Atividades · Insumos · Lotes de Produção · Produção · Propriedades · Ocorrências · Exportar Excel · Exportar PDF · Mais
- Colunas: Data · Atividade · Propriedade · Lavoura · Status · Responsável · Nome · Tipo · Estoque · Fornecedor · Certificação · Validade · Lote · Produto · Data Produção · Quantidade · Unidade · Cidade · Área Total · Total Lavouras · Nome Comum · Severidade · Produtor
- Formulário `POST (mesma página)` — campos: data_inicio:date, data_fim:date, status:select

### `/caderno-campo/atividade-diaria/9/editar`

- **Editar Atividade Diária**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Remover Imagem · Salvar Alterações · Voltar · Mais
- Formulário `POST (mesma página)` — campos: csrf_token:hidden, nome:text, tipo_atividade:select, descricao:textarea, data_atividade:date, hora_inicio:time, hora_fim:time, propriedade_id:select, lavoura_id:select, associado_id:select, categoria_id:select, insumo_utilisado:select, status:select, observacoes:textarea, remover_imagem:checkbox, imagem_diaria:file

### `/caderno-campo/categoria-atividade/novo`

- **Cadastrar Categoria de Atividade**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Salvar · Voltar · Mais
- Formulário `POST (mesma página)` — campos: nome:text, descricao:textarea, cor:color

### `/caderno-campo/categoria-atividade/8/editar`

- **Editar Categoria de Atividade**
- Ações: Abrir em nova aba · Fechar · PDF · Excel · Abrir · Listar Categorias · Atualizar Categoria · Cancelar · Mais
- Formulário `POST (mesma página)` — campos: nome:text, cor:color, descricao:textarea

