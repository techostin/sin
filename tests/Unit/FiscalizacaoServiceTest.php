<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Modules\Fiscalizacao\FiscalizacaoService;
use App\Tests\Support\ContextoUsuarioFake;
use App\Tests\Support\FakeAuditLogger;
use App\Tests\Support\FakeDocumentNumberGenerator;
use App\Tests\Support\FakeFiscalizacaoRepository;
use App\Tests\Support\FakeRastreabilidadeRepository;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class FiscalizacaoServiceTest extends TestCase
{
    private FakeFiscalizacaoRepository $repository;
    private FiscalizacaoService $service;
    private ContextoUsuarioFake $fiscal;

    protected function setUp(): void
    {
        $this->repository = new FakeFiscalizacaoRepository();
        $this->fiscal = new ContextoUsuarioFake(
            ['fiscal_campo'],
            7,
            null,
            [],
            [],
            'Fiscal',
            ['fiscalizacao.read', 'fiscalizacao.write']
        );
        $this->service = new FiscalizacaoService(
            $this->repository,
            new FakeRastreabilidadeRepository(),
            new FakeDocumentNumberGenerator(),
            new FakeAuditLogger()
        );
    }

    public function testCreatesAndListsVisit(): void
    {
        $visit = $this->service->createVisit($this->fiscal, ['associado_id' => 7]);
        self::assertSame('agendada', $visit['status']);

        self::assertNotEmpty($this->service->listVisits($this->fiscal));
    }

    public function testAddsNonConformity(): void
    {
        $nc = $this->service->addNonConformity(
            $this->fiscal,
            '11111111-1111-4111-8111-111111111111',
            ['descricao' => 'Ausencia de registro de insumo', 'gravidade' => 'alta']
        );

        self::assertSame('alta', $nc['gravidade']);
    }

    public function testCompletesVisitFlow(): void
    {
        $uuid = '11111111-1111-4111-8111-111111111111';

        self::assertTrue($this->service->updateStatus($this->fiscal, $uuid, 'em_andamento'));
        self::assertTrue($this->service->updateStatus($this->fiscal, $uuid, 'concluida'));
    }

    public function testReviewsVisit(): void
    {
        $this->repository->visits['11111111-1111-4111-8111-111111111111']['status'] = 'concluida';
        $reviewer = new ContextoUsuarioFake(
            ['gestor_fiscalizacao'],
            null,
            1,
            [1],
            [],
            'Gestor',
            ['fiscalizacao.review']
        );

        self::assertTrue($this->service->review(
            $reviewer,
            '11111111-1111-4111-8111-111111111111',
            'aprovada',
            'Ok'
        ));
    }

    public function testIssuesCertificateOnlyWithApprovedVisitAndTraceableLot(): void
    {
        $this->repository->visits['11111111-1111-4111-8111-111111111111']['status'] = 'aprovada';
        $certifier = new ContextoUsuarioFake(
            ['certificador'],
            null,
            1,
            [1],
            [],
            'Certificador',
            ['fiscalizacao.certificate']
        );

        $document = $this->service->issueCertificate(
            $certifier,
            '11111111-1111-4111-8111-111111111111',
            '11111111-1111-4111-8111-111111111111'
        );

        self::assertSame('emitido', $document['status']);
        self::assertSame('CERT-' . date('Y') . '-000001', $document['numero']);
        self::assertNotNull($this->service->verifyDocument((string) $document['numero']));
    }

    public function testBlocksCertificateWithoutApproval(): void
    {
        $certifier = new ContextoUsuarioFake(
            ['certificador'],
            null,
            1,
            [1],
            [],
            'Certificador',
            ['fiscalizacao.certificate']
        );

        $this->expectException(InvalidArgumentException::class);
        $this->service->issueCertificate(
            $certifier,
            '11111111-1111-4111-8111-111111111111',
            '11111111-1111-4111-8111-111111111111'
        );
    }

    public function testProducerCannotSeeAnotherVisit(): void
    {
        $otherProducer = new ContextoUsuarioFake(
            ['produtor'],
            99,
            null,
            [],
            [],
            'Outro',
            ['fiscalizacao.read']
        );

        $this->expectException(InvalidArgumentException::class);
        $this->service->findVisit($otherProducer, '11111111-1111-4111-8111-111111111111');
    }

    public function testEvidenceRequiresFile(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->storeEvidence(
            $this->fiscal,
            '11111111-1111-4111-8111-111111111111',
            []
        );
    }
}
