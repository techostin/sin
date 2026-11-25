<?php

declare(strict_types=1);

namespace App\Tests\Unit\Platform;

use App\Platform\Aplicacoes;
use App\Platform\Autorizacao;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Matriz de acesso por perfil — o contrato central do framework.
 */
final class AutorizacaoTest extends TestCase
{
    /**
     * @param list<string> $papeis
     * @return array<string, mixed>
     */
    private function perfil(array $papeis, ?array $permissoes = null): array
    {
        $admin = in_array('admin', $papeis, true);
        if ($permissoes === null) {
            $permissoes = [];
            foreach ($papeis as $papel) {
                foreach (Aplicacoes::permissoesDoPapel($papel) as $permissao) {
                    if ($permissao !== '*') {
                        $permissoes[] = $permissao;
                    }
                }
            }
        }

        return [
            'id' => 1,
            'nome' => 'Pessoa de teste',
            'perfil' => Aplicacoes::rotuloPapel($papeis[0] ?? 'sem_perfil'),
            'papeis' => $papeis,
            'permissoes' => $admin ? ['*'] : array_values(array_unique($permissoes)),
            'admin' => $admin,
        ];
    }

    public function testAdminAcessaTodosOsSistemas(): void
    {
        $admin = $this->perfil(['admin']);

        self::assertTrue(Autorizacao::ehAdmin($admin));
        foreach (['assoc', 'social', 'producao'] as $slug) {
            self::assertTrue(Autorizacao::podeAcessar($admin, $slug), 'admin deve acessar ' . $slug);
        }
    }

    public function testAdminVeTodasAsFuncoesDeCadaSistema(): void
    {
        $admin = $this->perfil(['admin']);

        foreach (Aplicacoes::todos() as $slug => $app) {
            self::assertCount(count($app['funcoes']), Autorizacao::funcoesVisiveis($admin, (string) $slug));
        }
    }

    public function testAdminTemQualquerPermissao(): void
    {
        $admin = $this->perfil(['admin']);

        self::assertTrue(Autorizacao::pode($admin, 'associados.write'));
        self::assertTrue(Autorizacao::pode($admin, 'fiscalizacao.certificate'));
        self::assertTrue(Autorizacao::pode($admin, 'permissao.que.nao.existe'));
    }

    public function testGestorDeAssociacaoNaoEntraNoSocial(): void
    {
        $gestor = $this->perfil(['gestor_associacao']);

        self::assertTrue(Autorizacao::podeAcessar($gestor, 'assoc'));
        self::assertFalse(Autorizacao::podeAcessar($gestor, 'social'));
        self::assertFalse(Autorizacao::pode($gestor, 'fiscalizacao.certificate'));
    }

    public function testFiscalDeCampoSoEntraNoSocial(): void
    {
        $fiscal = $this->perfil(['fiscal_campo']);

        self::assertTrue(Autorizacao::podeAcessar($fiscal, 'social'));
        self::assertFalse(Autorizacao::podeAcessar($fiscal, 'assoc'));
        self::assertFalse(Autorizacao::podeAcessar($fiscal, 'producao'));
    }

    public function testFiscalDeCampoVeAFiscalizacaoSemRevisarNemCertificar(): void
    {
        $fiscal = $this->perfil(['fiscal_campo']);
        $funcoes = array_column(Autorizacao::funcoesVisiveis($fiscal, 'social'), 'chave');

        self::assertCount(13, $funcoes, 'fiscal de campo vê os 13 itens do menu de fiscalização');
        self::assertContains('painel.gestor', $funcoes);
        self::assertContains('agendar.visita', $funcoes);
        self::assertContains('validacao.insumos', $funcoes);
        self::assertFalse(Autorizacao::pode($fiscal, 'fiscalizacao.review'));
        self::assertFalse(Autorizacao::pode($fiscal, 'fiscalizacao.certificate'));
    }

    public function testCertificadorVeAnaliseECertificacaoMasNaoAgendaNemRelatorios(): void
    {
        $certificador = $this->perfil(['certificador']);
        $funcoes = array_column(Autorizacao::funcoesVisiveis($certificador, 'social'), 'chave');

        self::assertContains('planos.analise', $funcoes);
        self::assertContains('certificacoes.listar', $funcoes);
        self::assertNotContains('agendar.visita', $funcoes);
        self::assertNotContains('relatorios.admin', $funcoes);
    }

    public function testFiscalLegadoTemAcessoTotalAFiscalizacaoESemAcessoAAssociacao(): void
    {
        // No legado, "Gestor" (nível 2) é o FISCAL/TÉCNICO — mapeado para gestor_fiscalizacao.
        $fiscal = $this->perfil(['gestor_fiscalizacao']);

        self::assertTrue(Autorizacao::podeAcessar($fiscal, 'social'));
        self::assertTrue(Autorizacao::pode($fiscal, 'fiscalizacao.certificate'));
        self::assertTrue(Autorizacao::pode($fiscal, 'fiscalizacao.review'));

        $funcoes = array_column(Autorizacao::funcoesVisiveis($fiscal, 'social'), 'chave');
        self::assertCount(13, $funcoes, 'fiscal deve ver os 13 itens do menu de fiscalização');
        self::assertContains('painel.gestor', $funcoes);
        self::assertContains('certificacoes.listar', $funcoes);
        self::assertContains('consulta.cnpo', $funcoes);
        self::assertContains('agendar.visita', $funcoes);

        // Técnico/fiscal NÃO acessa a área associativa.
        self::assertFalse(Autorizacao::podeAcessar($fiscal, 'assoc'));
        self::assertFalse(Autorizacao::pode($fiscal, 'associados.read'));
    }

    public function testDiretorTemAcessoTotalAoModuloDeAssociacao(): void
    {
        $diretor = $this->perfil(['diretor']);
        $funcoes = array_column(Autorizacao::funcoesVisiveis($diretor, 'assoc'), 'chave');

        self::assertTrue(Autorizacao::podeAcessar($diretor, 'assoc'));
        self::assertCount(16, $funcoes, 'diretor deve ver as funções do módulo + autoatendimento + painel');
        self::assertContains('painel.assoc', $funcoes);
        self::assertNotContains('papeis.atribuir', $funcoes);
        self::assertContains('membro.meus-documentos', $funcoes);
        self::assertContains('membro.minhas-mensalidades', $funcoes);
        self::assertContains('membro.meus-relatorios', $funcoes);
        self::assertFalse(Autorizacao::pode($diretor, 'papeis.gerenciar'));
        self::assertContains('documentos.gerir', $funcoes);
        self::assertContains('financeiro.mensalidades', $funcoes);
        self::assertContains('associados.novo', $funcoes);
        self::assertContains('aprovacoes.pendencias', $funcoes);

        // A gestão total do sistema é exclusiva do admin.
        $admin = $this->perfil(['admin']);
        self::assertTrue(Autorizacao::pode($admin, 'papeis.gerenciar'));
        self::assertCount(count(Aplicacoes::todos()['assoc']['funcoes']), Autorizacao::funcoesVisiveis($admin, 'assoc'));

        // Diretoria não atua na fiscalização nem na produção.
        self::assertFalse(Autorizacao::podeAcessar($diretor, 'social'));
        self::assertFalse(Autorizacao::podeAcessar($diretor, 'producao'));
        self::assertSame([], Autorizacao::funcoesVisiveis($diretor, 'social'));
    }

    public function testTesoureiroAcessaAreaFinanceiraSemDocumentos(): void
    {
        $tesoureiro = $this->perfil(['tesoureiro']);
        $funcoes = array_column(Autorizacao::funcoesVisiveis($tesoureiro, 'assoc'), 'chave');

        self::assertTrue(Autorizacao::podeAcessar($tesoureiro, 'assoc'));
        self::assertContains('financeiro.mensalidades', $funcoes);
        self::assertContains('membro.meus-documentos', $funcoes);
        self::assertContains('membro.minhas-mensalidades', $funcoes);
        self::assertNotContains('documentos.gerir', $funcoes);
        self::assertNotContains('associados.novo', $funcoes);
        self::assertNotContains('aprovacoes.pendencias', $funcoes);
        self::assertFalse(Autorizacao::pode($tesoureiro, 'associados.write'));
        self::assertFalse(Autorizacao::podeAcessar($tesoureiro, 'social'));
    }

    public function testSecretarioAcessaDocumentosSemFinanceiro(): void
    {
        $secretario = $this->perfil(['secretario']);
        $funcoes = array_column(Autorizacao::funcoesVisiveis($secretario, 'assoc'), 'chave');

        self::assertTrue(Autorizacao::podeAcessar($secretario, 'assoc'));
        self::assertContains('documentos.gerir', $funcoes);
        self::assertContains('documentos.consultar', $funcoes);
        self::assertContains('membro.meus-documentos', $funcoes);
        self::assertContains('membro.minha-ficha', $funcoes);
        self::assertNotContains('financeiro.mensalidades', $funcoes);
        self::assertNotContains('aprovacoes.pendencias', $funcoes);
        self::assertFalse(Autorizacao::pode($secretario, 'financeiro.write'));
        self::assertFalse(Autorizacao::podeAcessar($secretario, 'social'));
    }

    public function testProdutorEntraNaProducaoENoAssociadosMasNaoNoSocial(): void
    {
        $produtor = $this->perfil(['produtor']);

        self::assertTrue(Autorizacao::podeAcessar($produtor, 'producao'));
        self::assertTrue(Autorizacao::podeAcessar($produtor, 'assoc'));
        self::assertFalse(Autorizacao::podeAcessar($produtor, 'social'));
    }

    public function testProdutorNaoVeFuncaoFinanceira(): void
    {
        $produtor = $this->perfil(['produtor']);
        $funcoes = array_column(Autorizacao::funcoesVisiveis($produtor, 'assoc'), 'chave');

        self::assertContains('portal.meu-cadastro', $funcoes);
        self::assertContains('membro.minha-ficha', $funcoes);
        self::assertContains('membro.meus-documentos', $funcoes);
        self::assertContains('membro.minhas-mensalidades', $funcoes);
        self::assertContains('membro.meus-certificados', $funcoes);
        self::assertContains('membro.meus-relatorios', $funcoes);
        self::assertNotContains('financeiro.mensalidades', $funcoes);
        self::assertNotContains('associados.novo', $funcoes);
        self::assertNotContains('papeis.atribuir', $funcoes);
    }

    public function testMembroNaoVeListaCompletaMesmoComPermissoesResiduais(): void
    {
        // Simula o que acontecia no banco: permissões globais antigas no papel.
        $membro = $this->perfil(['produtor'], [
            'associados.read', 'associados.write', 'relatorios.read', 'fiscalizacao.read',
            'caderno.read', 'portal.read', 'associados.self', 'documentos.self',
            'financeiro.self', 'relatorios.self',
        ]);

        self::assertFalse(Autorizacao::pode($membro, 'associados.read'));
        self::assertFalse(Autorizacao::pode($membro, 'relatorios.read'));
        self::assertFalse(Autorizacao::pode($membro, 'fiscalizacao.read'));

        $funcoes = array_column(Autorizacao::funcoesVisiveis($membro, 'assoc'), 'chave');
        self::assertNotContains('associados.quadro', $funcoes);
        self::assertNotContains('associados.novo', $funcoes);
        self::assertNotContains('relatorios.assoc', $funcoes);
        self::assertContains('portal.meu-cadastro', $funcoes);
        self::assertContains('membro.meus-documentos', $funcoes);

        self::assertTrue(Autorizacao::podeAcessar($membro, 'assoc'));
        self::assertFalse(Autorizacao::podeAcessar($membro, 'social'));
    }

    public function testConsumidorVeSomenteOsPropriosDados(): void
    {
        $consumidor = $this->perfil(['consumidor']);

        self::assertTrue(Autorizacao::podeAcessar($consumidor, 'assoc'));
        self::assertFalse(Autorizacao::podeAcessar($consumidor, 'producao'));
        self::assertFalse(Autorizacao::podeAcessar($consumidor, 'social'));

        $funcoes = array_column(Autorizacao::funcoesVisiveis($consumidor, 'assoc'), 'chave');
        self::assertContains('portal.meu-cadastro', $funcoes);
        self::assertContains('membro.minha-ficha', $funcoes);
        self::assertContains('membro.meus-documentos', $funcoes);
        self::assertContains('membro.minhas-mensalidades', $funcoes);
        self::assertContains('membro.meus-certificados', $funcoes);
        self::assertContains('membro.meus-relatorios', $funcoes);
        self::assertNotContains('associados.quadro', $funcoes);
        self::assertNotContains('financeiro.mensalidades', $funcoes);
        self::assertNotContains('relatorios.assoc', $funcoes);
        self::assertNotContains('papeis.atribuir', $funcoes);

        self::assertFalse(Autorizacao::pode($consumidor, 'associados.read'));
        self::assertFalse(Autorizacao::pode($consumidor, 'caderno.read'));
    }

    public function testFichaPendenteAcessaMinhaFichaSemAcessoAmplo(): void
    {
        $pendente = [
            'id' => 1,
            'nome' => 'Pessoa com ficha pendente',
            'origem' => 'usuarios',
            'nivel' => 3,
            'associado_id' => 0,
            'ficha_pendente' => true,
            'papeis' => [],
            'permissoes' => [],
            'admin' => false,
        ];

        self::assertTrue(Autorizacao::pode($pendente, 'associados.self'));
        self::assertTrue(Autorizacao::pode($pendente, 'portal.read'));
        self::assertFalse(Autorizacao::pode($pendente, 'associados.read'));
        self::assertFalse(Autorizacao::pode($pendente, 'caderno.read'));

        $funcoes = array_column(Autorizacao::funcoesVisiveis($pendente, 'assoc'), 'chave');
        self::assertContains('membro.minha-ficha', $funcoes);
        self::assertContains('portal.meu-cadastro', $funcoes);
        self::assertNotContains('associados.quadro', $funcoes);
        self::assertFalse(Autorizacao::podeAcessar($pendente, 'producao'));
    }

    public function testDiretoriaEntraNosTresSistemas(): void
    {
        $diretoria = $this->perfil(['diretoria']);

        foreach (['assoc', 'social', 'producao'] as $slug) {
            self::assertTrue(Autorizacao::podeAcessar($diretoria, $slug), 'diretoria deve acessar ' . $slug);
        }
    }

    public function testPerfilSemPapeisNaoAcessaNada(): void
    {
        $vazio = $this->perfil([], []);

        foreach (['assoc', 'social', 'producao'] as $slug) {
            self::assertFalse(Autorizacao::podeAcessar($vazio, $slug), $slug . ' deveria negar perfil sem papel');
            self::assertSame([], Autorizacao::funcoesVisiveis($vazio, $slug));
        }
    }

    public function testSistemasPermitidosDoProdutor(): void
    {
        $produtor = $this->perfil(['produtor']);

        self::assertSame(['assoc', 'producao'], Autorizacao::sistemasPermitidos($produtor));
    }

    public function testSistemaDesconhecidoFalhaExplicitamente(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Aplicacoes::obter('inexistente');
    }

    public function testCadaSistemaDeclaraFuncoesComPermissaoConhecida(): void
    {
        $conhecidas = Aplicacoes::todasPermissoes();

        foreach (Aplicacoes::todos() as $slug => $app) {
            self::assertNotEmpty($app['funcoes'], 'sistema ' . $slug . ' sem funções');
            foreach ($app['funcoes'] as $funcao) {
                self::assertContains(
                    $funcao['permissao'],
                    $conhecidas,
                    'função ' . $funcao['chave'] . ' usa permissão fora do catálogo'
                );
            }
        }
    }
}
