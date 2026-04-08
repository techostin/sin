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

    // Filtros de DataVencimento para mensalidades atrasadas não são fáceis na subquery, 
    // mas vamos adicionar filtro de Associado caso exista.
    if (!empty($_GET['associado'])) {
        $where[] = "a.id = :associado";
        $params[':associado'] = $_GET['associado'];
    }

    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    $query = "SELECT 
                a.id, 
                CONCAT(a.nome, ' ', a.sobrenome) as nome_completo,
                a.cpf,
                a.telefone,
                a.status as status_cadastro,
                (SELECT COUNT(*) FROM mensalidades m 
                 WHERE m.associado_id = a.id 
                 AND (m.status_mensalidade = 'atrasado' OR (m.status_mensalidade = 'pendente' AND m.data_vencimento < CURDATE()))) as parcelas_atrasadas,
                (SELECT SUM(valor_cobrado) FROM mensalidades m 
                 WHERE m.associado_id = a.id 
                 AND (m.status_mensalidade = 'atrasado' OR (m.status_mensalidade = 'pendente' AND m.data_vencimento < CURDATE()))) as valor_divida
              FROM associados a
              $whereClause
              ORDER BY parcelas_atrasadas DESC, a.nome ASC";

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
            <th>CPF</th>
            <th>Telefone</th>
            <th>Situação Cadastral</th>
            <th>Status Financeiro</th>
            <th>Parcelas Atrasadas</th>
            <th>Valor da Dívida (R$)</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($associados as $row): 
            $cpf = !empty($row['cpf']) ? preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $row['cpf']) : 'Não informado';
            $telefone = !empty($row['telefone']) ? $row['telefone'] : 'Não informado';
            
            $statusCad = $row['status_cadastro'] === 'ativo' ? 'Ativo' : 'Inativo';
            
            $parcelas_atrasadas = (int)$row['parcelas_atrasadas'];
            $valor_divida = (float)$row['valor_divida'];
            
            if ($parcelas_atrasadas > 0) {
                $statusFin = '<span class="badge bg-danger">Inadimplente</span>';
            } else {
                $statusFin = '<span class="badge bg-success">Em Dia</span>';
            }
        ?>
        <tr>
            <td><?= htmlspecialchars($row['nome_completo']) ?></td>
            <td><?= htmlspecialchars($cpf) ?></td>
            <td><?= htmlspecialchars($telefone) ?></td>
            <td><?= $statusCad ?></td>
            <td><?= $statusFin ?></td>
            <td class="text-center">
                <?php if ($parcelas_atrasadas > 0): ?>
                    <span class="text-danger fw-bold"><?= $parcelas_atrasadas ?></span>
                <?php else: ?>
                    <span class="text-success">0</span>
                <?php endif; ?>
            </td>
            <td class="text-end">
                <?php if ($valor_divida > 0): ?>
                    <span class="text-danger fw-bold">R$ <?= number_format($valor_divida, 2, ',', '.') ?></span>
                <?php else: ?>
                    <span class="text-success">R$ 0,00</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Erro: ' . $e->getMessage() . '</div>';
}
?>
