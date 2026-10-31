<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/ManejoAnimal.php';

/**
 * ProducaoAnimal — Controles de Produção Animal Orgânica.
 *
 * Base normativa: Portaria MAPA nº 52/2021, Título III (Sistemas Orgânicos de
 * Produção Animal). Cobre: lotes, conversão/aquisição, alimentação (arts.
 * 34-37), ambiente de criação (arts. 38-44), sanidade (arts. 59-63) e
 * reprodução/aleitamento.
 */
class ProducaoAnimal extends ManejoAnimal
{
    /* ───────────────────────── Lotes ───────────────────────── */

    public function getLotes($filtroAssociado = null)
    {
        $sql = "SELECT l.*, (SELECT COUNT(*) FROM animais a WHERE a.lote_id = l.id AND a.excluido = 0) AS animais_total
                FROM animal_lotes l WHERE l.excluido = 0";
        $params = [];
        if ($filtroAssociado) {
            $sql .= " AND l.associado_id = ?";
            $params[] = (int)$filtroAssociado;
        }
        $sql .= " ORDER BY l.criado_em DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLoteById($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM animal_lotes WHERE id = ? AND excluido = 0");
        $stmt->execute([(int)$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function criarLote($dados)
    {
        $stmt = $this->db->prepare(
            "INSERT INTO animal_lotes (associado_id, nome_lote, especie, categoria, quantidade, descricao, criado_por)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            !empty($dados['associado_id']) ? (int)$dados['associado_id'] : null,
            trim($dados['nome_lote']),
            !empty($dados['especie']) ? trim($dados['especie']) : null,
            !empty($dados['categoria']) ? trim($dados['categoria']) : null,
            !empty($dados['quantidade']) ? (int)$dados['quantidade'] : 0,
            !empty($dados['descricao']) ? $dados['descricao'] : null,
            !empty($dados['criado_por']) ? (int)$dados['criado_por'] : null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function atualizarLote($id, $dados)
    {
        $stmt = $this->db->prepare(
            "UPDATE animal_lotes SET nome_lote = ?, especie = ?, categoria = ?, quantidade = ?, descricao = ?
             WHERE id = ?"
        );
        return $stmt->execute([
            trim($dados['nome_lote']),
            !empty($dados['especie']) ? trim($dados['especie']) : null,
            !empty($dados['categoria']) ? trim($dados['categoria']) : null,
            !empty($dados['quantidade']) ? (int)$dados['quantidade'] : 0,
            !empty($dados['descricao']) ? $dados['descricao'] : null,
            (int)$id,
        ]);
    }

    public function softDeleteLote($id)
    {
        $stmt = $this->db->prepare("UPDATE animal_lotes SET excluido = 1 WHERE id = ?");
        return $stmt->execute([(int)$id]);
    }

    /* ─────── Animais (expansão: entrada, conversão e lote) ─────── */

    public function atualizarAnimal($id, $dados)
    {
        $stmt = $this->db->prepare(
            "UPDATE animais SET nome_animal = ?, especie = ?, raca = ?, identificacao = ?, sexo = ?,
                data_nascimento = ?, status = ?, origem_conversao = ?, lote_id = ?, peso_kg = ?,
                tipo_entrada = ?, fornecedor_origem = ?, data_entrada = ?, observacoes_origem = ?
             WHERE id = ?"
        );
        return $stmt->execute([
            trim($dados['nome_animal']),
            trim($dados['especie']),
            !empty($dados['raca']) ? trim($dados['raca']) : null,
            !empty($dados['identificacao']) ? trim($dados['identificacao']) : null,
            !empty($dados['sexo']) ? $dados['sexo'] : null,
            !empty($dados['data_nascimento']) ? $dados['data_nascimento'] : null,
            $dados['status'] ?? 'ATIVO',
            $dados['origem_conversao'] ?? 'ORGANICO',
            !empty($dados['lote_id']) ? (int)$dados['lote_id'] : null,
            !empty($dados['peso_kg']) ? (float)$dados['peso_kg'] : null,
            !empty($dados['tipo_entrada']) ? $dados['tipo_entrada'] : null,
            !empty($dados['fornecedor_origem']) ? trim($dados['fornecedor_origem']) : null,
            !empty($dados['data_entrada']) ? $dados['data_entrada'] : null,
            !empty($dados['observacoes_origem']) ? $dados['observacoes_origem'] : null,
            (int)$id,
        ]);
    }

    /* ─────────────────── Alimentação (arts. 34-37) ─────────────────── */

    public function getAlimentacoes($filtroAssociado = null)
    {
        $sql = "SELECT c.*, a.nome_animal, a.especie AS especie_animal, l.nome_lote
                FROM controle_alimentacao_animal c
                LEFT JOIN animais a ON a.id = c.animal_id
                LEFT JOIN animal_lotes l ON l.id = c.lote_id
                WHERE c.excluido = 0";
        $params = [];
        if ($filtroAssociado) {
            $sql .= " AND c.associado_id = ?";
            $params[] = (int)$filtroAssociado;
        }
        $sql .= " ORDER BY c.data_alimentacao DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAlimentacaoById($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM controle_alimentacao_animal WHERE id = ? AND excluido = 0");
        $stmt->execute([(int)$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function criarAlimentacao($dados)
    {
        $stmt = $this->db->prepare(
            "INSERT INTO controle_alimentacao_animal
                (associado_id, animal_id, lote_id, data_alimentacao, tipo_alimento, produto_utilizado,
                 quantidade_kg, percentual_materia_seca, origem, observacoes, criado_por)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            !empty($dados['associado_id']) ? (int)$dados['associado_id'] : null,
            !empty($dados['animal_id']) ? (int)$dados['animal_id'] : null,
            !empty($dados['lote_id']) ? (int)$dados['lote_id'] : null,
            $dados['data_alimentacao'],
            $dados['tipo_alimento'],
            !empty($dados['produto_utilizado']) ? trim($dados['produto_utilizado']) : null,
            !empty($dados['quantidade_kg']) ? (float)$dados['quantidade_kg'] : null,
            !empty($dados['percentual_materia_seca']) ? (float)$dados['percentual_materia_seca'] : null,
            $dados['origem'] ?? 'ORGANICO',
            !empty($dados['observacoes']) ? $dados['observacoes'] : null,
            !empty($dados['criado_por']) ? (int)$dados['criado_por'] : null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function atualizarAlimentacao($id, $dados)
    {
        $stmt = $this->db->prepare(
            "UPDATE controle_alimentacao_animal SET animal_id = ?, lote_id = ?, data_alimentacao = ?,
                tipo_alimento = ?, produto_utilizado = ?, quantidade_kg = ?, percentual_materia_seca = ?,
                origem = ?, observacoes = ? WHERE id = ?"
        );
        return $stmt->execute([
            !empty($dados['animal_id']) ? (int)$dados['animal_id'] : null,
            !empty($dados['lote_id']) ? (int)$dados['lote_id'] : null,
            $dados['data_alimentacao'],
            $dados['tipo_alimento'],
            !empty($dados['produto_utilizado']) ? trim($dados['produto_utilizado']) : null,
            !empty($dados['quantidade_kg']) ? (float)$dados['quantidade_kg'] : null,
            !empty($dados['percentual_materia_seca']) ? (float)$dados['percentual_materia_seca'] : null,
            $dados['origem'] ?? 'ORGANICO',
            !empty($dados['observacoes']) ? $dados['observacoes'] : null,
            (int)$id,
        ]);
    }

    public function softDeleteAlimentacao($id)
    {
        $stmt = $this->db->prepare("UPDATE controle_alimentacao_animal SET excluido = 1 WHERE id = ?");
        return $stmt->execute([(int)$id]);
    }

    /* ─────────────────── Sanidade (arts. 59-63) ─────────────────── */

    public function getSanidades($filtroAssociado = null)
    {
        $sql = "SELECT c.*, a.nome_animal, a.especie AS especie_animal, l.nome_lote
                FROM controle_sanidade_animal c
                LEFT JOIN animais a ON a.id = c.animal_id
                LEFT JOIN animal_lotes l ON l.id = c.lote_id
                WHERE c.excluido = 0";
        $params = [];
        if ($filtroAssociado) {
            $sql .= " AND c.associado_id = ?";
            $params[] = (int)$filtroAssociado;
        }
        $sql .= " ORDER BY c.data_tratamento DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSanidadeById($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM controle_sanidade_animal WHERE id = ? AND excluido = 0");
        $stmt->execute([(int)$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function criarSanidade($dados)
    {
        $stmt = $this->db->prepare(
            "INSERT INTO controle_sanidade_animal
                (associado_id, animal_id, lote_id, data_tratamento, tipo_tratamento, produto_utilizado,
                 motivo, dose_quantidade, via_aplicacao, responsavel, produto_normatizado,
                 periodo_carencia_horas, data_liberacao_organica, aprovado_ocs, numero_tratamento,
                 observacoes, criado_por)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            !empty($dados['associado_id']) ? (int)$dados['associado_id'] : null,
            !empty($dados['animal_id']) ? (int)$dados['animal_id'] : null,
            !empty($dados['lote_id']) ? (int)$dados['lote_id'] : null,
            $dados['data_tratamento'],
            $dados['tipo_tratamento'],
            !empty($dados['produto_utilizado']) ? trim($dados['produto_utilizado']) : null,
            !empty($dados['motivo']) ? trim($dados['motivo']) : null,
            !empty($dados['dose_quantidade']) ? trim($dados['dose_quantidade']) : null,
            !empty($dados['via_aplicacao']) ? trim($dados['via_aplicacao']) : null,
            !empty($dados['responsavel']) ? trim($dados['responsavel']) : null,
            isset($dados['produto_normatizado']) ? (int)$dados['produto_normatizado'] : 1,
            !empty($dados['periodo_carencia_horas']) ? (int)$dados['periodo_carencia_horas'] : null,
            !empty($dados['data_liberacao_organica']) ? $dados['data_liberacao_organica'] : null,
            !empty($dados['aprovado_ocs']) ? (int)$dados['aprovado_ocs'] : null,
            !empty($dados['numero_tratamento']) ? (int)$dados['numero_tratamento'] : 1,
            !empty($dados['observacoes']) ? $dados['observacoes'] : null,
            !empty($dados['criado_por']) ? (int)$dados['criado_por'] : null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function atualizarSanidade($id, $dados)
    {
        $stmt = $this->db->prepare(
            "UPDATE controle_sanidade_animal SET animal_id = ?, lote_id = ?, data_tratamento = ?,
                tipo_tratamento = ?, produto_utilizado = ?, motivo = ?, dose_quantidade = ?,
                via_aplicacao = ?, responsavel = ?, produto_normatizado = ?, periodo_carencia_horas = ?,
                data_liberacao_organica = ?, aprovado_ocs = ?, numero_tratamento = ?, observacoes = ?
             WHERE id = ?"
        );
        return $stmt->execute([
            !empty($dados['animal_id']) ? (int)$dados['animal_id'] : null,
            !empty($dados['lote_id']) ? (int)$dados['lote_id'] : null,
            $dados['data_tratamento'],
            $dados['tipo_tratamento'],
            !empty($dados['produto_utilizado']) ? trim($dados['produto_utilizado']) : null,
            !empty($dados['motivo']) ? trim($dados['motivo']) : null,
            !empty($dados['dose_quantidade']) ? trim($dados['dose_quantidade']) : null,
            !empty($dados['via_aplicacao']) ? trim($dados['via_aplicacao']) : null,
            !empty($dados['responsavel']) ? trim($dados['responsavel']) : null,
            isset($dados['produto_normatizado']) ? (int)$dados['produto_normatizado'] : 1,
            !empty($dados['periodo_carencia_horas']) ? (int)$dados['periodo_carencia_horas'] : null,
            !empty($dados['data_liberacao_organica']) ? $dados['data_liberacao_organica'] : null,
            !empty($dados['aprovado_ocs']) ? (int)$dados['aprovado_ocs'] : null,
            !empty($dados['numero_tratamento']) ? (int)$dados['numero_tratamento'] : 1,
            !empty($dados['observacoes']) ? $dados['observacoes'] : null,
            (int)$id,
        ]);
    }

    public function softDeleteSanidade($id)
    {
        $stmt = $this->db->prepare("UPDATE controle_sanidade_animal SET excluido = 1 WHERE id = ?");
        return $stmt->execute([(int)$id]);
    }

    /* ─────────────────── Reprodução e aleitamento ─────────────────── */

    public function getReproducoes($filtroAssociado = null)
    {
        $sql = "SELECT c.*, a.nome_animal, a.especie AS especie_animal
                FROM controle_reproducao_animal c
                LEFT JOIN animais a ON a.id = c.animal_id
                WHERE c.excluido = 0";
        $params = [];
        if ($filtroAssociado) {
            $sql .= " AND c.associado_id = ?";
            $params[] = (int)$filtroAssociado;
        }
        $sql .= " ORDER BY c.data_evento DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getReproducaoById($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM controle_reproducao_animal WHERE id = ? AND excluido = 0");
        $stmt->execute([(int)$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function criarReproducao($dados)
    {
        $stmt = $this->db->prepare(
            "INSERT INTO controle_reproducao_animal
                (associado_id, animal_id, grupo_reprodutivo, tipo_evento, data_evento, macho_reprodutor_id,
                 previsao_parto, nascidos_vivos, natimortos, periodo_aleitamento_dias, observacoes, criado_por)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            !empty($dados['associado_id']) ? (int)$dados['associado_id'] : null,
            !empty($dados['animal_id']) ? (int)$dados['animal_id'] : null,
            !empty($dados['grupo_reprodutivo']) ? trim($dados['grupo_reprodutivo']) : null,
            $dados['tipo_evento'],
            $dados['data_evento'],
            !empty($dados['macho_reprodutor_id']) ? trim($dados['macho_reprodutor_id']) : null,
            !empty($dados['previsao_parto']) ? $dados['previsao_parto'] : null,
            isset($dados['nascidos_vivos']) && $dados['nascidos_vivos'] !== '' ? (int)$dados['nascidos_vivos'] : null,
            isset($dados['natimortos']) && $dados['natimortos'] !== '' ? (int)$dados['natimortos'] : null,
            !empty($dados['periodo_aleitamento_dias']) ? (int)$dados['periodo_aleitamento_dias'] : null,
            !empty($dados['observacoes']) ? $dados['observacoes'] : null,
            !empty($dados['criado_por']) ? (int)$dados['criado_por'] : null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function atualizarReproducao($id, $dados)
    {
        $stmt = $this->db->prepare(
            "UPDATE controle_reproducao_animal SET animal_id = ?, grupo_reprodutivo = ?, tipo_evento = ?,
                data_evento = ?, macho_reprodutor_id = ?, previsao_parto = ?, nascidos_vivos = ?,
                natimortos = ?, periodo_aleitamento_dias = ?, observacoes = ? WHERE id = ?"
        );
        return $stmt->execute([
            !empty($dados['animal_id']) ? (int)$dados['animal_id'] : null,
            !empty($dados['grupo_reprodutivo']) ? trim($dados['grupo_reprodutivo']) : null,
            $dados['tipo_evento'],
            $dados['data_evento'],
            !empty($dados['macho_reprodutor_id']) ? trim($dados['macho_reprodutor_id']) : null,
            !empty($dados['previsao_parto']) ? $dados['previsao_parto'] : null,
            isset($dados['nascidos_vivos']) && $dados['nascidos_vivos'] !== '' ? (int)$dados['nascidos_vivos'] : null,
            isset($dados['natimortos']) && $dados['natimortos'] !== '' ? (int)$dados['natimortos'] : null,
            !empty($dados['periodo_aleitamento_dias']) ? (int)$dados['periodo_aleitamento_dias'] : null,
            !empty($dados['observacoes']) ? $dados['observacoes'] : null,
            (int)$id,
        ]);
    }

    public function softDeleteReproducao($id)
    {
        $stmt = $this->db->prepare("UPDATE controle_reproducao_animal SET excluido = 1 WHERE id = ?");
        return $stmt->execute([(int)$id]);
    }

    /* ─────────────────── Ambiente de criação (arts. 38-44) ─────────────────── */

    public function getAmbientes($filtroAssociado = null)
    {
        $sql = "SELECT * FROM ambiente_criacao_animal WHERE excluido = 0";
        $params = [];
        if ($filtroAssociado) {
            $sql .= " AND associado_id = ?";
            $params[] = (int)$filtroAssociado;
        }
        $sql .= " ORDER BY criado_em DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAmbienteById($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM ambiente_criacao_animal WHERE id = ? AND excluido = 0");
        $stmt->execute([(int)$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function criarAmbiente($dados)
    {
        $stmt = $this->db->prepare(
            "INSERT INTO ambiente_criacao_animal
                (associado_id, nome_instalacao, especie, sistema, tipo_instalacao, area_total_m2,
                 quantidade_animais, densidade_referencia, tem_sombreamento_abrigo, observacoes, criado_por)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            !empty($dados['associado_id']) ? (int)$dados['associado_id'] : null,
            trim($dados['nome_instalacao']),
            !empty($dados['especie']) ? $dados['especie'] : null,
            !empty($dados['sistema']) ? $dados['sistema'] : null,
            !empty($dados['tipo_instalacao']) ? trim($dados['tipo_instalacao']) : null,
            !empty($dados['area_total_m2']) ? (float)$dados['area_total_m2'] : null,
            !empty($dados['quantidade_animais']) ? (int)$dados['quantidade_animais'] : null,
            !empty($dados['densidade_referencia']) ? (float)$dados['densidade_referencia'] : null,
            isset($dados['tem_sombreamento_abrigo']) ? (int)$dados['tem_sombreamento_abrigo'] : 0,
            !empty($dados['observacoes']) ? $dados['observacoes'] : null,
            !empty($dados['criado_por']) ? (int)$dados['criado_por'] : null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function atualizarAmbiente($id, $dados)
    {
        $stmt = $this->db->prepare(
            "UPDATE ambiente_criacao_animal SET nome_instalacao = ?, especie = ?, sistema = ?,
                tipo_instalacao = ?, area_total_m2 = ?, quantidade_animais = ?, densidade_referencia = ?,
                tem_sombreamento_abrigo = ?, observacoes = ? WHERE id = ?"
        );
        return $stmt->execute([
            trim($dados['nome_instalacao']),
            !empty($dados['especie']) ? $dados['especie'] : null,
            !empty($dados['sistema']) ? $dados['sistema'] : null,
            !empty($dados['tipo_instalacao']) ? trim($dados['tipo_instalacao']) : null,
            !empty($dados['area_total_m2']) ? (float)$dados['area_total_m2'] : null,
            !empty($dados['quantidade_animais']) ? (int)$dados['quantidade_animais'] : null,
            !empty($dados['densidade_referencia']) ? (float)$dados['densidade_referencia'] : null,
            isset($dados['tem_sombreamento_abrigo']) ? (int)$dados['tem_sombreamento_abrigo'] : 0,
            !empty($dados['observacoes']) ? $dados['observacoes'] : null,
            (int)$id,
        ]);
    }

    public function softDeleteAmbiente($id)
    {
        $stmt = $this->db->prepare("UPDATE ambiente_criacao_animal SET excluido = 1 WHERE id = ?");
        return $stmt->execute([(int)$id]);
    }

    /* ─────────────────── Densidades de referência ─────────────────── */

    public function getDensidades($especie = null)
    {
        $sql = "SELECT * FROM densidades_maximas_animais WHERE ativo = 1";
        $params = [];
        if ($especie) {
            $sql .= " AND especie = ?";
            $params[] = $especie;
        }
        $sql .= " ORDER BY especie, fase";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ─────────── Estatísticas (dashboards e relatórios) ─────────── */

    public function estatisticas($filtroAssociado = null)
    {
        $where = " WHERE excluido = 0";
        $params = [];
        if ($filtroAssociado) {
            $where .= " AND associado_id = ?";
            $params[] = (int)$filtroAssociado;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*) AS total,
                SUM(CASE WHEN status = 'ATIVO' THEN 1 ELSE 0 END) AS ativos,
                SUM(CASE WHEN especie = 'BOVINOS' OR especie LIKE '%bovino%' THEN 1 ELSE 0 END) AS bovinos,
                SUM(CASE WHEN especie = 'AVES' OR especie LIKE '%ave%' THEN 1 ELSE 0 END) AS aves,
                SUM(CASE WHEN especie = 'SUINOS' OR especie LIKE '%suíno%' OR especie LIKE '%suino%' THEN 1 ELSE 0 END) AS suinos,
                SUM(CASE WHEN origem_conversao = 'CONVENCIONAL' THEN 1 ELSE 0 END) AS convencionais
            FROM animais$where");
        $stmt->execute($params);
        $animais = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $this->db->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(quantidade), 0) AS cabecas FROM animal_lotes$where");
        $stmt->execute($params);
        $lotes = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $this->db->prepare("SELECT COUNT(*) AS total FROM controle_sanidade_animal$where");
        $stmt->execute($params);
        $sanidade = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $this->db->prepare("SELECT COUNT(*) AS total FROM controle_reproducao_animal$where");
        $stmt->execute($params);
        $reproducao = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $this->db->prepare("SELECT COUNT(*) AS registros FROM manejo_animal_registros r
                INNER JOIN animais a ON a.id = r.animal_id AND a.excluido = 0
                WHERE 1=1" . ($filtroAssociado ? " AND a.associado_id = ?" : ""));
        $paramsReg = $filtroAssociado ? [(int)$filtroAssociado] : [];
        $stmt->execute($paramsReg);
        $manejo = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'animais_total' => (int)$animais['total'],
            'animais_ativos' => (int)$animais['ativos'],
            'bovinos' => (int)$animais['bovinos'],
            'aves' => (int)$animais['aves'],
            'suinos' => (int)$animais['suinos'],
            'convencionais' => (int)$animais['convencionais'],
            'lotes' => (int)$lotes['total'],
            'cabecas_lotes' => (int)$lotes['cabecas'],
            'sanidade' => (int)$sanidade['total'],
            'reproducao' => (int)$reproducao['total'],
            'manejo_registros' => (int)$manejo['registros'],
        ];
    }
}