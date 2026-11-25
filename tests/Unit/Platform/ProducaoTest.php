<?php

declare(strict_types=1);

namespace App\Tests\Unit\Platform;

use App\Platform\Aplicacoes;
use App\Platform\Autorizacao;
use App\Platform\Cadastro;
use App\Platform\ProducaoAreas;
use App\Platform\ProducaoRecursos;
use PHPUnit\Framework\TestCase;

final class ProducaoTest extends TestCase
{
    public function testRecursosCobremAsTresAreasDaProducao(): void
    {
        $recursos = ProducaoRecursos::todos();

        self::assertGreaterThanOrEqual(20, count($recursos));
        foreach ($recursos as $chave => $def) {
            self::assertArrayHasKey('tabela', $def, $chave);
            self::assertArrayHasKey('titulo', $def, $chave);
            self::assertArrayHasKey('grupo', $def, $chave);
            self::assertNotEmpty($def['colunas'] ?? [], $chave);
        }

        $grupos = array_unique(array_map(static fn(array $def): string => (string) $def['grupo'], $recursos));
        self::assertContains('Produção Vegetal', $grupos);
        self::assertContains('Produção Animal', $grupos);
        self::assertContains('Agroindústria', $grupos);
    }

    public function testMenuDeProducaoNaoMisturaFuncoesDeOutrosPaineis(): void
    {
        $funcoes = Aplicacoes::todos()['producao']['funcoes'];
        self::assertNotEmpty($funcoes);

        $chaves = array_map(static fn(array $funcao): string => (string) $funcao['chave'], $funcoes);
        self::assertContains('caderno.listar', $chaves);
        self::assertContains('producao.propriedades', $chaves);
        self::assertContains('animal.animais', $chaves);
        self::assertContains('agroindustria.ordens', $chaves);
        self::assertContains('producao.rastreabilidade', $chaves);

        foreach ($chaves as $chave) {
            self::assertStringNotContainsString('associados.', $chave);
            self::assertStringNotContainsString('fiscalizacao.', $chave);
            self::assertStringNotContainsString('certificacao', $chave);
        }
    }

    public function testFuncoesDeProducaoApontamParaPaginasDoPainel(): void
    {
        foreach (Aplicacoes::todos()['producao']['funcoes'] as $funcao) {
            if (($funcao['chave'] ?? '') === 'portal.meu-cadastro') {
                continue;
            }
            self::assertNotEmpty($funcao['rota'] ?? '', (string) ($funcao['chave'] ?? ''));
            self::assertMatchesRegularExpression(
                '/^(painel\.php|meu-plano\.php|registro\.php|relatorios\.php|rastreabilidade\.php)/',
                (string) $funcao['rota']
            );
        }
    }

    /**
     * @param list<string> $papeis
     * @return array<string, mixed>
     */
    private function identidade(array $papeis, string $area, bool $temAgro = false): array
    {
        $permissoes = [];
        foreach ($papeis as $papel) {
            $permissoes = array_merge($permissoes, Aplicacoes::permissoesDoPapel($papel));
        }

        return [
            'id' => 1,
            'origem' => 'usuarios',
            'admin' => in_array('admin', $papeis, true),
            'papeis' => $papeis,
            'permissoes' => array_values(array_unique($permissoes)),
            'area_producao' => $area,
            'tem_agroindustria' => $temAgro ? 1 : 0,
            'tipo_usuario' => $temAgro ? 'agricultor_agroindustria' : 'agricultor',
            'associado_id' => 0,
            'nivel' => 3,
        ];
    }

    /** @return list<string> */
    private function menuProducao(array $identidade): array
    {
        return array_column(Autorizacao::funcoesVisiveis($identidade, 'producao'), 'chave');
    }

    public function testAgricultorDeVegetaisVeApenasModulosVegetais(): void
    {
        $identidade = $this->identidade(['produtor'], 'vegetal');
        self::assertSame(['vegetal'], ProducaoAreas::doUsuario($identidade));

        $menu = $this->menuProducao($identidade);
        self::assertContains('producao.propriedades', $menu);
        self::assertContains('caderno.listar', $menu);
        self::assertContains('producao.rastreabilidade', $menu);
        self::assertNotContains('animal.animais', $menu);
        self::assertNotContains('agroindustria.minhas', $menu);
    }

    public function testProdutorSomenteDeAgroindustriaVeApenasAgroindustria(): void
    {
        $identidade = $this->identidade(['agroindustria'], 'agroindustria');
        self::assertSame(['agroindustria'], ProducaoAreas::doUsuario($identidade));

        $menu = $this->menuProducao($identidade);
        self::assertContains('agroindustria.minhas', $menu);
        self::assertContains('agroindustria.ordens', $menu);
        self::assertContains('producao.rastreabilidade', $menu);
        self::assertNotContains('producao.propriedades', $menu);
        self::assertNotContains('animal.animais', $menu);
    }

    public function testVegetalComAgroindustriaVeOsDoisGruposSemAnimal(): void
    {
        $identidade = $this->identidade(['produtor'], 'vegetal', true);
        self::assertSame(['vegetal', 'agroindustria'], ProducaoAreas::doUsuario($identidade));

        $menu = $this->menuProducao($identidade);
        self::assertContains('producao.lavouras', $menu);
        self::assertContains('agroindustria.receitas', $menu);
        self::assertNotContains('animal.animais', $menu);
    }

    public function testCriadorDeAnimaisComAgroindustriaVeAnimalEAgro(): void
    {
        $identidade = $this->identidade(['produtor'], 'animal', true);
        self::assertSame(['animal', 'agroindustria'], ProducaoAreas::doUsuario($identidade));

        $menu = $this->menuProducao($identidade);
        self::assertContains('animal.animais', $menu);
        self::assertContains('animal.sanidade', $menu);
        self::assertContains('agroindustria.ordens', $menu);
        self::assertNotContains('producao.propriedades', $menu);
    }

    public function testAdminVeTodasAsAreas(): void
    {
        $identidade = $this->identidade(['admin'], '');
        self::assertSame(ProducaoAreas::TODAS, ProducaoAreas::doUsuario($identidade));

        $menu = $this->menuProducao($identidade);
        self::assertContains('producao.propriedades', $menu);
        self::assertContains('animal.animais', $menu);
        self::assertContains('agroindustria.ordens', $menu);
    }

    public function testPreCadastroOfereceCombinacoesDeArea(): void
    {
        self::assertArrayHasKey('vegetal_agro', Cadastro::SITUACOES);
        self::assertArrayHasKey('animal_agro', Cadastro::SITUACOES);
        self::assertArrayHasKey('agroindustria', Cadastro::SITUACOES);
        self::assertArrayHasKey('animal', Cadastro::SITUACOES);
    }

    public function testPerfilDePrimeiroAcessoDefineAreaQuandoNaoHaAreaGravada(): void
    {
        $identidade = $this->identidade(['produtor'], '');
        $identidade['perfil_solicitado'] = 'agroindustria';
        self::assertSame(['agroindustria'], ProducaoAreas::doUsuario($identidade));

        $identidade['perfil_solicitado'] = 'vegetal_agro';
        self::assertSame(['vegetal', 'agroindustria'], ProducaoAreas::doUsuario($identidade));
    }

    public function testSemPerfilNemAreaAssumeApenasVegetal(): void
    {
        $identidade = $this->identidade(['produtor'], '');
        self::assertSame(['vegetal'], ProducaoAreas::doUsuario($identidade));
    }
}
