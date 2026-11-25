Módulo de Certificadoras SPG (Rio Grande do Sul) e Layout de Documentos
Este plano detalha a criação do módulo para gestão de certificadoras (focado em certificação participativa - SPG no RS), vinculação aos usuários e a atualização dinâmica dos PDFs gerados (Certificado e Termo de Compromisso).

Perguntas em Aberto
IMPORTANT

Vinculação do Usuário vs Associado No requisito, foi mencionado: "a logo da certificadora a qual o usuário atual pertence ou é vinculado seja inserido...". Atualmente temos a tabela usuarios (gestores/auditores) e associados (produtores).

A certificadora será vinculada ao Gestor (quem está logado gerando o documento) ou ao Produtor/Associado (quem está recebendo o certificado)?
Minha premissa no plano abaixo: Vamos vincular à tabela usuarios (gestor logado), pois geralmente o gestor/técnico atua em nome do OPAC/SPG. (Se preferir vincular ao produtor, por favor avise).
Mudanças Propostas
1. Banco de Dados
Vamos criar uma nova tabela para as certificadoras e adicionar um vínculo na tabela de usuários.

[NEW] Tabela certificadoras
id (INT, PK)
nome (VARCHAR) - Nome da certificadora/OPAC
cnpj (VARCHAR)
tipo (ENUM: 'Auditoria', 'SPG', 'OCS')
registro_mapa (VARCHAR)
estado (VARCHAR) - Ex: RS
logo_url (VARCHAR) - Caminho para a imagem
created_at, updated_at
[MODIFY] Tabela usuarios
Adicionar coluna certificadora_id (INT, FK referenciando certificadoras(id))
Carga Inicial (Seed) de SPGs do RS
Inserir no banco as principais redes SPG (OPAC) atuantes no Rio Grande do Sul:
Associação Rede Ecovida de Certificação Participativa
Associação dos Produtores da Rede Agroecológica Metropolitana (RAMA)
OPAC Litoral Norte
2. Módulo CRUD de Certificadoras
Criar o módulo completo no sistema para permitir que administradores gerenciem (e façam upload da logo) das certificadoras.

[NEW] app/modulos/certificadoras/listar.php
Tela de listagem (Datatable).
[NEW] app/modulos/certificadoras/cadastrar.php
Formulário com campo de upload para a logo (salva em app/uploads/logos_certificadoras/).
[NEW] app/api/certificadoras/controller.php
API backend para salvar, listar, editar e excluir (incluindo tratamento do multipart form-data da imagem).
3. Integração com Usuários
[MODIFY] app/modulos/usuarios/cadastrar.php (e editar.php)
Adicionar um campo select listando as certificadoras do banco para que o Administrador vincule o Gestor/Usuário à sua respectiva Certificadora/OPAC.
4. Layout dos PDFs (Certificado e Termo)
Modificar os templates geradores do Dompdf para injetar imagens dinamicamente nos cantos superiores.

Selo SisOrg / Orgânico Brasil: Vou baixar a imagem oficial do selo e salvar em app/assets/img/sisorg.png.
Logo da Certificadora: Será buscada a URL a partir do certificadora_id do usuário logado que clicou em "Gerar".
[MODIFY] app/modulos/roteiros_inspecao/templates/certificado.php
Remover o cabeçalho fixo com texto do IBD.
Canto Superior Esquerdo: <img src="<?= $logo_certificadora ?>" width="120">
Canto Superior Direito: <img src="<?= $logo_sisorg ?>" width="100">
Ajustar CSS para manter a tabela e layout intactos, usando absolute positioning ou headers floatados que o Dompdf entenda.
[MODIFY] app/modulos/roteiros_inspecao/templates/declaracao_compromisso.php
Aplicar o mesmo padrão de cabeçalho dinâmico com as logos.
Plano de Verificação
Testes Manuais
Executar o script SQL para criar as tabelas e inserir as certificadoras do RS.
Fazer upload manual do selo SisOrg para os assets do sistema.
Acessar "Certificadoras" e verificar a lista inicial; fazer upload de um logo teste para a Ecovida.
Vincular o usuário de teste (gestor) à "Rede Ecovida".
Gerar o PDF de um produtor e verificar se a logo da Ecovida saiu na esquerda e o SisOrg saiu na direita sem quebrar o layout da folha A4.