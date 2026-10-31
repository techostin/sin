<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Kernel\Http\Request;
use App\Modules\Associados\AssociadosController;
use App\Tests\Support\FakeAssociadosRepository;
use PHPUnit\Framework\TestCase;

final class AssociadosControllerTest extends TestCase
{
    private const UUID = '123e4567-e89b-12d3-a456-426614174000';

    public function testListMasksPersonalDataAndCapsPageSize(): void
    {
        $repository = new FakeAssociadosRepository([$this->row()]);
        $response = (new AssociadosController($repository))->list(
            Request::create('GET', '/associados', ['per_page' => '999'])
        );

        self::assertSame(200, $response->status);
        self::assertStringContainsString('***.***.***-00', $response->body);
        self::assertStringNotContainsString('123.456.789-00', $response->body);
        self::assertStringNotContainsString('produtor@example.test', $response->body);
        self::assertStringContainsString('"perPage":100', $response->body);
        self::assertSame(100, $repository->lastPerPage);
    }

    public function testListPassesStatusAndQuery(): void
    {
        $repository = new FakeAssociadosRepository([$this->row()]);
        $response = (new AssociadosController($repository))->list(
            Request::create('GET', '/associados', ['status' => 'ativo', 'q' => 'maria'])
        );

        self::assertSame(200, $response->status);
        self::assertSame('ativo', $repository->lastStatus);
        self::assertSame('maria', $repository->lastQ);
    }

    public function testPendingForcesPendingStatus(): void
    {
        $repository = new FakeAssociadosRepository([$this->row()]);
        $response = (new AssociadosController($repository))->pending(
            Request::create('GET', '/associados/pendentes')
        );

        self::assertSame(200, $response->status);
        self::assertSame('pendente', $repository->lastStatus);
    }

    public function testShowReturnsNotFoundAndInvalidUuid(): void
    {
        $controller = new AssociadosController(new FakeAssociadosRepository([$this->row()]));

        $invalid = $controller->show(Request::create('GET', '/associados/x'), 'x');
        self::assertSame(400, $invalid->status);

        $missing = $controller->show(
            Request::create('GET', '/associados/' . self::UUID),
            'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa'
        );
        self::assertSame(404, $missing->status);
    }

    public function testShowMasksData(): void
    {
        $controller = new AssociadosController(new FakeAssociadosRepository([$this->row()]));
        $response = $controller->show(Request::create('GET', '/associados/' . self::UUID), self::UUID);

        self::assertSame(200, $response->status);
        self::assertStringContainsString('"cpf_mascarado":"***.***.***-00"', $response->body);
        self::assertStringContainsString('"email_mascarado":"p***@example.test"', $response->body);
    }

    /** @return array<string, mixed> */
    private function row(): array
    {
        return [
            'uuid' => self::UUID,
            'nome' => 'Maria',
            'sobrenome' => 'Teste',
            'cpf' => '123.456.789-00',
            'email' => 'produtor@example.test',
            'status' => 'ativo',
            'registro_cnpo' => 123,
            'associacao_id' => 1,
            'associacao_nome' => 'Associação Teste',
            'microrregiao_id' => 2,
        ];
    }
}
