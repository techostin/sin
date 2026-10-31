<?php
require_once __DIR__ . '/Database.php';

class User {
    /**
     * B-01 (SQL Injection) - Whitelist de tabelas de usuario.
     *
     * O nome de uma tabela NAO pode ser um parametro preparado no PDO, entao
     * $_SESSION['user_table'] acaba interpolado direto na query. Uma sessao
     * adulterada permitiria executar SQL arbitrario. Apenas estes dois valores
     * sao aceitos; qualquer outro e descartado.
     */
    public const TABELAS_USUARIO = ['associados', 'usuarios'];
    public const TABELA_USUARIO_PADRAO = 'associados';

    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Valida um nome de tabela de usuario contra a whitelist (B-01).
     *
     * Use SEMPRE este metodo antes de interpolar a tabela do usuario em SQL.
     *
     * @param string|null $tabela Valor a validar. Se null, usa $_SESSION['user_table'].
     * @param string|null $padrao Valor devolvido quando o informado for invalido/ausente.
     * @return string Nome de tabela garantidamente seguro.
     */
    public static function tabelaUsuarioSegura($tabela = null, $padrao = null): string {
        $padrao = in_array($padrao, self::TABELAS_USUARIO, true)
            ? $padrao
            : self::TABELA_USUARIO_PADRAO;

        if ($tabela === null) {
            $tabela = $_SESSION['user_table'] ?? null;
        }

        if ($tabela === null || $tabela === '') {
            return $padrao;
        }

        $normalizada = strtolower(trim((string) $tabela));
        if (in_array($normalizada, self::TABELAS_USUARIO, true)) {
            return $normalizada;
        }

        // Fora da whitelist: registra (possivel adulteracao de sessao) e usa o padrao.
        error_log('User::tabelaUsuarioSegura - tabela invalida rejeitada: ' . substr((string) $tabela, 0, 100));
        return $padrao;
    }

    public function login($email, $senha) {
        try {
            if (!is_string($email) || !is_string($senha) || $email === '' || $senha === '') {
                return false;
            }

            // O mesmo e-mail pode existir nas duas tabelas: valida o hash nas
            // duas e prefere a conta de sistema (usuarios), para que a linha de
            // "associados" não esconda (nem rebaixe) uma conta de equipe.
            $stmt = $this->db->prepare("SELECT * FROM usuarios WHERE email = :email AND status = 'ativo'");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            $userSistema = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $this->db->prepare("SELECT * FROM associados WHERE email = :email AND status = 'ativo'");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            $userAssociado = $stmt->fetch(PDO::FETCH_ASSOC);

            $user = null;
            $table = '';
            if ($userSistema && !empty($userSistema['senha']) && password_verify($senha, $userSistema['senha'])) {
                $user = $userSistema;
                $table = 'usuarios';
            } elseif ($userAssociado && !empty($userAssociado['senha']) && password_verify($senha, $userAssociado['senha'])) {
                $user = $userAssociado;
                $table = 'associados';
            }

            if (!$user) {
                return false;
            }

            // Anti fixação de sessão
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_regenerate_id(true);
            }

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_nivel'] = $user['nivel'];
            $_SESSION['user_nome'] = trim($user['nome'] . ' ' . ($user['sobrenome'] ?? ''));
            $_SESSION['user_tipo'] = $user['tipo'];
            $_SESSION['user_tipo_usuario'] = $user['tipo_usuario'] ?? ($user['tipo'] ?? '');
            $_SESSION['user_area_producao'] = $user['area_producao'] ?? '';
            $_SESSION['user_table'] = $table;
            return true;
        } catch (PDOException $e) {
            error_log("Erro no login: " . $e->getMessage());
            return false;
        }
    }

    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    public function logout() {
        session_destroy();
    }

    public function hasPermission($required_level) {
        if (!$this->isLoggedIn() || !isset($_SESSION['user_nivel'])) {
            return false;
        }

        // B-04 (escalonamento de privilegio): o nivel vem da sessao e era
        // comparado com "==" / "<=" sem checagem de tipo. Em PHP 8, uma string
        // nao numerica (ex.: nivel adulterado para "") era comparada como texto
        // e "'' <= 1" resulta em TRUE, liberando acesso de Admin. Agora apenas
        // valores numericos e maiores que zero sao aceitos.
        $nivel = $_SESSION['user_nivel'];
        if (!is_numeric($nivel)) {
            error_log('User::hasPermission - nivel nao numerico rejeitado: ' . substr((string) $nivel, 0, 40));
            return false;
        }
        $nivel = (int) $nivel;
        $required_level = (int) $required_level;
        if ($nivel <= 0) {
            return false;
        }

        // O nível 1 (Admin) tem acesso a tudo
        if ($nivel === NIVEL_ADMIN) {
            return true;
        }
        // Verifica se o nível do usuário é igual ou mais privilegiado que o necessário
        return $nivel <= $required_level;
    }

    /**
     * Helper para verificar se o usuário é Gestor ou Admin
     * @return bool
     */
    public function isGestorOuAdmin(): bool {
        return $this->hasPermission(NIVEL_GESTOR);
    }

    /**
     * Identifica o dashboard do perfil do usuário logado.
     * Retorna um dos valores: 'admin', 'gestor', 'agricultor', 'agroindustria'.
     *
     * @return string
     */
    public function perfilDashboard(): string {
        $nivel = isset($_SESSION['user_nivel']) ? (int)$_SESSION['user_nivel'] : NIVEL_USUARIO;
        if ($nivel === NIVEL_ADMIN)  return 'admin';
        if ($nivel === NIVEL_GESTOR) return 'gestor';
        // Associado: distingue Agricultor de Produtor de Agroindústria
        $tipo = isset($_SESSION['user_tipo_usuario']) ? strtolower((string)$_SESSION['user_tipo_usuario']) : '';
        if ($tipo === 'produtor_agroindustria' || $tipo === 'agroindustria') {
            return 'agroindustria';
        }
        return 'agricultor';
    }

    /**
     * Verifica se o associado logado possui agroindústria.
     * Retorna true para: produtor de agroindústria, agricultor com
     * agroindústria (tem_agroindustria=1) ou quem já tem registro(s) em
     * agroindustrias. Gestor/Admin também recebem true (acesso ao módulo).
     *
     * @return bool
     */
    public function temAgroindustria(): bool {
        if (!$this->isLoggedIn()) return false;
        $nivel = isset($_SESSION['user_nivel']) ? (int)$_SESSION['user_nivel'] : NIVEL_USUARIO;
        if ($nivel === NIVEL_ADMIN || $nivel === NIVEL_GESTOR) return true;

        $currentUser = $this->getCurrentUser();
        if (!$currentUser) return false;

        $tipo = isset($currentUser['tipo_usuario']) ? strtolower((string)$currentUser['tipo_usuario']) : '';
        if ($tipo === 'produtor_agroindustria' || $tipo === 'agroindustria') return true;
        if (!empty($currentUser['tem_agroindustria'])) return true;

        $associadoId = $this->getAssociadoId();
        if ($associadoId) {
            try {
                $stmt = $this->db->prepare("SELECT COUNT(*) FROM agroindustrias WHERE associado_id = ? AND excluido = 0");
                $stmt->execute([(int)$associadoId]);
                return (int)$stmt->fetchColumn() > 0;
            } catch (PDOException $e) {
                return false;
            }
        }
        return false;
    }

    /**
     * Retorna as áreas de produção do usuário logado (set multiple).
     * Valores possíveis: animal, vegetal, agroindustria.
     * Admin/Gestor retornam todas (acesso aos módulos).
     *
     * @return array
     */
    public function areaProducao(): array {
        if (!$this->isLoggedIn()) return [];

        $nivel = isset($_SESSION['user_nivel']) ? (int)$_SESSION['user_nivel'] : NIVEL_USUARIO;
        if ($nivel === NIVEL_ADMIN || $nivel === NIVEL_GESTOR) {
            return ['animal', 'vegetal', 'agroindustria'];
        }

        if (!empty($_SESSION['user_area_producao'])) {
            $raw = (string)$_SESSION['user_area_producao'];
        } else {
            $currentUser = $this->getCurrentUser();
            $raw = is_array($currentUser) ? (string)($currentUser['area_producao'] ?? '') : '';
        }

        $areas = array_values(array_filter(array_map('trim', explode(',', $raw))));
        if (empty($areas)) {
            // Legado (sem área cadastrada): libera tudo para não quebrar acesso
            return ['animal', 'vegetal', 'agroindustria'];
        }
        return $areas;
    }

    /**
     * Busca dados do usuário logado
     * @return array|false Dados do usuário ou false se não encontrado
     */
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return false;
        }

        $table = self::tabelaUsuarioSegura(); // B-01: whitelist de tabelas
        $id = $_SESSION['user_id'];

        try {
            $stmt = $this->db->prepare("SELECT * FROM {$table} WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar usuário atual: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retorna o ID de associado vinculado ao usuário logado
     * @return int|null ID do associado ou null
     */
    public function getAssociadoId() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        $currentUser = $this->getCurrentUser();
        if (!$currentUser) return null;

        if (isset($_SESSION['user_table']) && $_SESSION['user_table'] === 'associados') {
            return $currentUser['id'];
        } elseif (isset($currentUser['associado_id'])) {
            return $currentUser['associado_id'];
        }
        return null;
    }

    /**
     * Cargos de diretoria (e comissões vinculadas à Diretoria) da Associação.
     * Estes cargos dão acesso ao Dashboard da Diretoria e às ferramentas da associação.
     */
    public const TIPOS_VINCULO_DIRETORIA = [
        'diretor',
        'secretario',
        'tesoureiro',
        'comissao_educativa',
        'comissao_etica',
        'conselho_fiscal',
        'presidente',
        'vice-presidente',
    ];

    /**
     * Resolve o ID de associado do usuário logado de forma robusta,
     * cobrindo as três formas de sessão existentes:
     *   1. Sessão do Portal do Associado ($_SESSION['associado_id']);
     *   2. Login principal na tabela "associados" (user_table = 'associados');
     *   3. Usuário da tabela "usuarios" com associado_id preenchido ou com um
     *      associado apontando user_id para ele (associados.user_id).
     *
     * @return int ID do associado ou 0 quando não houver vínculo.
     */
    public function getAssociadoIdResolvido(): int
    {
        // 1) Sessão do Portal do Associado (pode não ter user_id na sessão)
        if (!empty($_SESSION['associado_id'])) {
            return (int) $_SESSION['associado_id'];
        }

        if (!$this->isLoggedIn()) return 0;

        $currentUser = $this->getCurrentUser();
        if (!$currentUser) return 0;

        // 2) Login principal na tabela associados
        if (($_SESSION['user_table'] ?? '') === 'associados') {
            return (int) ($_SESSION['user_id'] ?? 0);
        }

        // 3) Usuário da tabela usuarios com associado_id vinculado
        if (!empty($currentUser['associado_id'])) {
            return (int) $currentUser['associado_id'];
        }

        // 4) Associado com user_id apontando para este usuário
        try {
            $stmt = $this->db->prepare("SELECT id FROM associados WHERE user_id = ? LIMIT 1");
            $stmt->execute([(int) ($_SESSION['user_id'] ?? 0)]);
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }

    /**
     * IDs das associações em que o usuário logado ocupa cargo de diretoria
     * ou de comissão vinculada à Diretoria (tipo_vinculo).
     *
     * @return int[]
     */
    public function getAssociacoesDiretoria(): array
    {
        $associadoId = $this->getAssociadoIdResolvido();
        if (!$associadoId) return [];

        try {
            $placeholders = implode(',', array_fill(0, count(self::TIPOS_VINCULO_DIRETORIA), '?'));
            $stmt = $this->db->prepare(
                "SELECT DISTINCT associacao_id FROM associado_associacao
                 WHERE associado_id = ? AND tipo_vinculo IN ({$placeholders})"
            );
            $stmt->execute(array_merge([$associadoId], self::TIPOS_VINCULO_DIRETORIA));
            return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        } catch (PDOException $e) {
            error_log('User::getAssociacoesDiretoria - erro: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * O usuário logado é membro da Diretoria (ou de comissão da Diretoria)
     * de alguma associação? Se $associacaoId > 0, verifica apenas aquela associação.
     *
     * @param int $associacaoId
     * @return bool
     */
    public function isMembroDiretoria(int $associacaoId = 0): bool
    {
        $associacoes = $this->getAssociacoesDiretoria();
        if ($associacaoId > 0) {
            return in_array($associacaoId, $associacoes, true);
        }
        return !empty($associacoes);
    }

    /**
     * Pode acessar o Dashboard da Diretoria e as ferramentas da associação?
     * Sim para: Admin e membros do quadro diretivo da Associação (Presidente,
     * Vice-Presidente, Diretor, Secretário, Tesoureiro, Conselho Fiscal e
     * comissões). Gestor/Fiscal NÃO tem acesso a esta área.
     *
     * @return bool
     */
    public function podeAcessarDiretoria(): bool
    {
        return $this->hasPermission(NIVEL_ADMIN) || $this->isMembroDiretoria();
    }

    /**
     * Verifica se o usuário logado possui cargo de direção executiva
     * (Presidente, Vice-Presidente, Diretor, Secretário, Tesoureiro)
     * que permite acessar o portal de qualquer associado via CPF.
     *
     * @return bool
     */
    public function isDiretoriaExecutiva(): bool
    {
        if ($this->hasPermission(NIVEL_ADMIN)) return true;
        
        $associadoId = $this->getAssociadoIdResolvido();
        if (!$associadoId) return false;

        $cargosExecutivos = ['presidente', 'vice-presidente', 'diretor', 'secretario', 'tesoureiro'];
        $placeholders = implode(',', array_fill(0, count($cargosExecutivos), '?'));
        
        try {
            $stmt = $this->db->prepare(
                "SELECT 1 FROM associado_associacao
                 WHERE associado_id = ? AND tipo_vinculo IN ({$placeholders})
                 LIMIT 1"
            );
            $stmt->execute(array_merge([$associadoId], $cargosExecutivos));
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            error_log('User::isDiretoriaExecutiva - erro: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Verifica se o perfil do usuario esta completo (campos de perfil preenchidos)
     * @return bool
     */
    public function hasPerfilCompleto() {
        if (!$this->isLoggedIn()) return false;
        $currentUser = $this->getCurrentUser();
        if (!$currentUser) return false;

        $nivel = $_SESSION['user_nivel'] ?? null;
        
        // Admin nao precisa preencher perfil extra
        if ($nivel == NIVEL_ADMIN) return true;
        
        // Gestor precisa de orgao, setor, especialidade
        if ($nivel == NIVEL_GESTOR) {
            return !empty($currentUser['orgao']) && !empty($currentUser['setor']) && !empty($currentUser['especialidade']);
        }
        
        // Usuário comum: considera o perfil completo quando existe QUALQUER
        // dado de contato/cadastro. Antes exigia apenas "nicho" — campo que
        // quase nenhum associado possui e que só podia ser preenchido em
        // /completar-perfil (sem link na interface), o que desviava ~94% da
        // base para /meu-perfil a cada login.
        if ($nivel == NIVEL_USUARIO) {
            return !empty($currentUser['nicho'])
                || !empty($currentUser['telefone'])
                || !empty($currentUser['cidade'])
                || !empty($currentUser['endereco'])
                || !empty($currentUser['cep']);
        }
        
        return false;
    }

    /**
     * Atualiza os dados de perfil do usuario
     * @param array $dados Dados a serem atualizados
     * @return bool
     */
    public function updatePerfil($dados) {
        if (!$this->isLoggedIn()) return false;
        
        $table = self::tabelaUsuarioSegura(); // B-01: whitelist de tabelas
        $id = $_SESSION['user_id'];
        
        // Campos permitidos para atualizacao
        $allowed = ['orgao', 'setor', 'especialidade', 'nicho'];
        $sets = [];
        $params = [];
        
        foreach ($dados as $key => $value) {
            if (in_array($key, $allowed)) {
                $sets[] = "{$key} = :{$key}";
                $params[":{$key}"] = $value;
                // Guardar na sessao
                $_SESSION["user_{$key}"] = $value;
            }
        }
        
        if (empty($sets)) return false;
        
        $params[':id'] = $id;
        $sql = "UPDATE {$table} SET " . implode(', ', $sets) . " WHERE id = :id";
        
        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Erro ao atualizar perfil: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Atualiza os dados pessoais do usuário logado na tabela correta
     * (associados ou usuarios) baseado em $_SESSION['user_table'].
     *
     * Apenas os campos que EXISTEM na tabela do usuário são atualizados.
     * Isso evita erros como "Unknown column 'sobrenome'" quando a tabela
     * 'usuarios' não possui colunas que existem na tabela 'associados'.
     *
     * @param array $dados Dados a serem atualizados (campos pessoais)
     * @return bool|string true em caso de sucesso, ou string de erro
     */
    public function updateCurrentUser($dados) {
        if (!$this->isLoggedIn()) return false;

        $table = self::tabelaUsuarioSegura(); // B-01: whitelist de tabelas
        $id = $_SESSION['user_id'];

        // Campos que NUNCA devem ser atualizados via este método (segurança)
        $blocked = ['id', 'senha', 'created_at', 'updated_at', 'data_criacao', 'data_atualizacao', 'ultimo_login', 'user_id'];

        // Buscar as colunas reais da tabela do usuário
        try {
            $stmt = $this->db->prepare("SHOW COLUMNS FROM {$table}");
            $stmt->execute();
            $colunas = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            error_log("Erro ao buscar colunas da tabela {$table}: " . $e->getMessage());
            return "Erro de banco de dados ao atualizar: " . $e->getMessage();
        }

        if (empty($colunas)) {
            return "Erro de banco de dados ao atualizar: tabela {$table} não encontrada.";
        }

        $sets = [];
        $params = [];

        // Campos com UNIQUE KEY (ex.: cpf, email, registro_cnpo). Uma string
        // vazia viola a unicidade porque só pode existir UM '' por índice.
        // Converter vazio em NULL, que o MySQL permite repetir, evita o erro
        // "1062 Duplicate entry '' for key 'cpf'".
        $campos_unique = ['cpf', 'cnpj', 'registro_cnpo', 'email', 'user_id'];

        foreach ($dados as $key => $value) {
            // Só atualiza campos que existem na tabela e não estão bloqueados
            if (in_array($key, $colunas) && !in_array($key, $blocked)) {
                if (in_array($key, $campos_unique, true) && $value === '') {
                    $value = null;
                }
                $sets[] = "{$key} = :{$key}";
                $params[":{$key}"] = $value;
            }
        }

        if (empty($sets)) return true;

        $params[':id'] = $id;
        $sql = "UPDATE {$table} SET " . implode(', ', $sets) . " WHERE id = :id";

        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Erro ao atualizar usuário atual: " . $e->getMessage());
            return "Erro de banco de dados ao atualizar: " . $e->getMessage();
        }
    }
}
