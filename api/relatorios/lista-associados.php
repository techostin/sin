<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $db = Database::getInstance()->getConnection();

    $where = [];
    $params = [];

    if (!empty($_GET['dataInicio'])) {
        $where[] = "a.data_cadastro >= :dataInicio";
        $params[':dataInicio'] = $_GET['dataInicio'] . " 00:00:00";
    }
    
    if (!empty($_GET['dataFim'])) {
        $where[] = "a.data_cadastro <= :dataFim";
        $params[':dataFim'] = $_GET['dataFim'] . " 23:59:59";
    }

    if (!empty($_GET['associado'])) {
        $where[] = "a.id = :associado";
        $params[':associado'] = $_GET['associado'];
    }

    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    $query = "SELECT 
                a.id, 
                CONCAT(a.nome, ' ', a.sobrenome) as nome_completo,
                a.email,
                a.cpf,
                a.rg,
                a.telefone,
                c.nome as cidade_nome,
                a.estado,
                a.data_cadastro,
                a.status
              FROM associados a
              LEFT JOIN cidades c ON a.cidade = c.id
              $whereClause
              ORDER BY a.nome, a.sobrenome";

    $stmt = $db->prepare($query);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->execute();
    $associados = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<table id="reportTable" class="table table-striped table-bordered" style="width:100%">
    <thead>
        <tr>
            <th>Nome</th>
            <th>E-mail</th>
            <th>CPF</th>
            <th>RG</th>
            <th>Telefone</th>
            <th>Cidade/UF</th>
            <th>Status</th>
            <th>Data Cadastro</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($associados as $row): 
            $cpf = !empty($row['cpf']) ? preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $row['cpf']) : 'Não informado';
            $telefone = !empty($row['telefone']) ? $row['telefone'] : 'Não informado';
            $cidade_uf = (!empty($row['cidade_nome']) ? $row['cidade_nome'] : 'Não informada');
            if (!empty($row['estado'])) {
                $cidade_uf .= '/' . $row['estado'];
            }
            $dataCad = !empty($row['data_cadastro']) ? date('d/m/Y', strtotime($row['data_cadastro'])) : '-';
        ?>
        <tr>
            <td><?= htmlspecialchars($row['nome_completo']) ?></td>
            <td><?= htmlspecialchars($row['email'] ?? 'Não informado') ?></td>
            <td><?= htmlspecialchars($cpf) ?></td>
            <td><?= htmlspecialchars($row['rg'] ?? 'Não informado') ?></td>
            <td><?= htmlspecialchars($telefone) ?></td>
            <td><?= htmlspecialchars($cidade_uf) ?></td>
            <td>
                <?php if ($row['status'] === 'ativo'): ?>
                    <span class="badge bg-success">Ativo</span>
                <?php else: ?>
                    <span class="badge bg-danger">Inativo</span>
                <?php endif; ?>
            </td>
            <td><?= $dataCad ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Erro: ' . $e->getMessage() . '</div>';
}
?>
