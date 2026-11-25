<?php

declare(strict_types=1);

namespace App\Tests\Unit\Platform;

use App\Platform\Onboarding;
use PHPUnit\Framework\TestCase;

/**
 * Regras do primeiro acesso: mesma lógica do sistema legado.
 */
final class OnboardingTest extends TestCase
{
    /**
     * @param array<string, mixed> $sobrescreve
     * @return array<string, mixed>
     */
    private function identidade(array $sobrescreve = []): array
    {
        return array_merge([
            'id' => 1,
            'origem' => 'usuarios',
            'nome' => 'Pessoa de teste',
            'nivel' => 3,
            'telefone' => '51999999999',
            'cpf' => '11144477735',
            'orgao' => '',
            'especialidade' => '',
            'associado_id' => 0,
        ], $sobrescreve);
    }

    public function testAdminNaoTemPendencias(): void
    {
        self::assertSame([], Onboarding::pendencias($this->identidade(['nivel' => 1, 'telefone' => '', 'cpf' => ''])));
    }

    public function testFichaSoEstaCompletaComOsCamposMinimos(): void
    {
        $completa = [
            'tipo_associado' => 'agricultor_familiar',
            'nome_completo' => 'Maria de Teste',
            'data_nascimento' => '1990-01-01',
            'cpf' => '11144477735',
            'municipio' => 'Porto Alegre',
            'estado' => 'RS',
            'telefone_whatsapp' => '51999999999',
            'email' => 'maria@example.com',
            'aceite_termos' => 1,
        ];

        $resultado = Onboarding::completudeFicha($completa);
        self::assertTrue($resultado['ok']);
        self::assertSame([], $resultado['faltando']);

        $incompleta = $completa;
        unset($incompleta['cpf'], $incompleta['aceite_termos']);
        $resultado = Onboarding::completudeFicha($incompleta);
        self::assertFalse($resultado['ok']);
        self::assertContains('CPF', $resultado['faltando']);
        self::assertContains('Aceite dos termos', $resultado['faltando']);
    }

    public function testFiscalSemVinculoPrecisaInformarOrgaoEspecialidadeESetor(): void
    {
        $fiscal = $this->identidade(['nivel' => 2, 'orgao' => '', 'especialidade' => '', 'telefone' => '', 'cpf' => '']);

        self::assertSame(['vinculo'], Onboarding::pendencias($fiscal));
        self::assertTrue(Onboarding::precisaVinculo($fiscal));

        // órgão e especialidade preenchidos, mas sem setor: o legado
        // (hasPerfilCompleto) continua considerando o perfil incompleto.
        $semSetor = $this->identidade(['nivel' => 2, 'orgao' => 'MAPA', 'setor' => '', 'especialidade' => 'Fiscal']);
        self::assertTrue(Onboarding::precisaVinculo($semSetor));
        self::assertSame(['vinculo'], Onboarding::pendencias($semSetor));
    }

    public function testFiscalComVinculoMasSemDadosEssenciaisPedePerfil(): void
    {
        $fiscal = $this->identidade(['nivel' => 2, 'orgao' => 'MAPA', 'setor' => 'Certificação', 'especialidade' => 'Fiscal', 'telefone' => '', 'cpf' => '']);

        self::assertSame(['perfil'], Onboarding::pendencias($fiscal));
    }

    public function testFiscalComVinculoEDadosCompletosNaoTemPendencias(): void
    {
        $fiscal = $this->identidade(['nivel' => 2, 'orgao' => 'MAPA', 'setor' => 'Certificação', 'especialidade' => 'Fiscal']);

        self::assertSame([], Onboarding::pendencias($fiscal));
    }

    public function testProdutorSemCadastroPrecisaDaFicha(): void
    {
        $produtor = $this->identidade(['nivel' => 3, 'associado_id' => 0]);

        self::assertSame(['ficha'], Onboarding::pendencias($produtor));
        self::assertTrue(Onboarding::precisaFicha($produtor));
    }

    public function testProdutorJaAprovadoNaoPrecisaDaFicha(): void
    {
        $produtor = $this->identidade(['nivel' => 3, 'associado_id' => 42]);

        self::assertSame([], Onboarding::pendencias($produtor));
        self::assertFalse(Onboarding::precisaFicha($produtor));
    }

    public function testFichaVemAntesDoPerfilParaProdutorSemCadastro(): void
    {
        // No legado, a conta nível 3 sem associado é redirecionada para a ficha
        // ANTES da checagem de perfil (/meu-perfil).
        $conta = $this->identidade([
            'nivel' => 3,
            'telefone' => '',
            'cpf' => '',
            'associado_id' => 0,
        ]);

        self::assertSame(['ficha'], Onboarding::pendencias($conta));
    }

    public function testProdutorAprovadoSemTelefoneOuCpfPedePerfil(): void
    {
        $conta = $this->identidade([
            'nivel' => 3,
            'telefone' => '',
            'cpf' => '',
            'associado_id' => 42,
        ]);

        self::assertSame(['perfil'], Onboarding::pendencias($conta));
    }

    public function testAssociadoNaoPassaPorPrimeiroAcesso(): void
    {
        $associado = $this->identidade(['origem' => 'associados', 'associado_id' => 7, 'telefone' => '', 'cpf' => '']);

        self::assertSame([], Onboarding::pendencias($associado));
    }
}
