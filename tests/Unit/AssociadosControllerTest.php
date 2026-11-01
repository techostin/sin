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

    public function testProducerCannotSeeAnotherAssociate(): void
    {
        $controller = new AssociadosController(new FakeAssociadosRepository([$this->row()]));
        $producer = new \App\Tests\Support\ContextoUsuarioFake(
            ['produtor'],
            99,
            null,
            [],
            [],
            'Outro Produtor',
            ['associados.read']
        );

        $response = $controller->show(Request::create('GET', '/x'), self::UUID, $producer);
        self::assertSame(404, $response->status);
    }

    public function testAssociationScopeAllowsOnlyItsAssociation(): void
    {
        $controller = new AssociadosController(new FakeAssociadosRepository([$this->row()]));
        $allowed = new \App\Tests\Support\ContextoUsuarioFake(
            ['gestor_associacao'],
            null,
            null,
            [1],
            [],
            'Gestor',
            ['associados.read']
        );
        $denied = new \App\Tests\Support\ContextoUsuarioFake(
            ['gestor_associacao'],
            null,
            null,
            [2],
            [],
            'Gestor',
            ['associados.read']
        );

        self::assertSame(200, $controller->show(Request::create('GET', '/x'), self::UUID, $allowed)->status);
        self::assertSame(404, $controller->show(Request::create('GET', '/x'), self::UUID, $denied)->status);
    }

    public function testStoreCreatesPendingAssociate(): void
    {
        $controller = new AssociadosController(new FakeAssociadosRepository());
        $response = $controller->store(Request::create(
            'POST',
            '/associados',
            [],
            [],
            null,
            '{"nome":"Maria","sobrenome":"Teste","email":"maria@example.test"}'
        ));

        self::assertSame(201, $response->status);
        self::assertStringContainsString('"status":"pendente"', $response->body);
    }

    public function testStoreRejectsInvalidEmailAndMissingNames(): void
    {
        $controller = new AssociadosController(new FakeAssociadosRepository());

        $invalidEmail = $controller->store(Request::create(
            'POST',
            '/associados',
            [],
            [],
            null,
            '{"nome":"Maria","sobrenome":"Teste","email":"invalido"}'
        ));
        self::assertSame(400, $invalidEmail->status);

        $missing = $controller->store(Request::create('POST', '/associados', [], [], null, '{}'));
        self::assertSame(400, $missing->status);
    }

    public function testApprove(): void
    {
        $controller = new AssociadosController(new FakeAssociadosRepository());

        self::assertSame(200, $controller->approve(Request::create('POST', '/x'), self::UUID)->status);
        self::assertSame(404, $controller->approve(
            Request::create('POST', '/x'),
            'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa'
        )->status);
    }

    /** @return array<string, mixed> */
    private function row(): array
    {
        return [
            'id' => 7,
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
