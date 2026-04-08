-- Adicionar campo de notificações na tabela associados
ALTER TABLE associados ADD COLUMN notificacoes_ativas BOOLEAN DEFAULT TRUE;
ALTER TABLE associados ADD COLUMN dias_antecedencia_evento INT DEFAULT 7;
ALTER TABLE associados ADD COLUMN ultima_notificacao_evento TIMESTAMP NULL;

-- Adicionar campo de notificações na tabela usuarios
ALTER TABLE usuarios ADD COLUMN notificacoes_ativas BOOLEAN DEFAULT TRUE;
ALTER TABLE usuarios ADD COLUMN dias_antecedencia_evento INT DEFAULT 7;
ALTER TABLE usuarios ADD COLUMN ultima_notificacao_evento TIMESTAMP NULL;

-- Tabela para histórico de notificações
CREATE TABLE IF NOT EXISTS notificacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    tipo_usuario ENUM('associado', 'sistema') NOT NULL,
    tipo_notificacao VARCHAR(50) NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    mensagem TEXT NOT NULL,
    dados_adicional JSON NULL,
    lida BOOLEAN DEFAULT FALSE,
    criada_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    lida_em TIMESTAMP NULL,
    INDEX idx_usuario_tipo (usuario_id, tipo_usuario),
    INDEX idx_lida (lida),
    INDEX idx_criada_em (criada_em)
);

-- Inserir algumas notificações de exemplo
INSERT INTO notificacoes (usuario_id, tipo_usuario, tipo_notificacao, titulo, mensagem) VALUES
(13, 'associado', 'evento_proximo', 'Evento Próximo', 'Você tem um evento agendado para amanhã: Reunião da Associação'),
(13, 'associado', 'evento_hoje', 'Evento de Hoje', 'Você tem um evento agendado para hoje: Palestra sobre Agricultura Sustentável');

-- Atualizar configurações de notificação para usuários existentes
UPDATE associados SET notificacoes_ativas = TRUE, dias_antecedencia_evento = 7 WHERE notificacoes_ativas IS NULL;
UPDATE usuarios SET notificacoes_ativas = TRUE, dias_antecedencia_evento = 7 WHERE notificacoes_ativas IS NULL; 