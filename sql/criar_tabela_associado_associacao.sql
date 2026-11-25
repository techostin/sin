-- Migração: vínculo N:M entre associados e associações
-- Permite que um associado pertença a uma ou mais associações.
-- A coluna `vinculo` (associados) é mantida como associação principal
-- (primeira selecionada) para compatibilidade com relatórios/APIs existentes.

CREATE TABLE IF NOT EXISTS associado_associacao (
    associado_id INT(11) NOT NULL,
    associacao_id INT(11) NOT NULL,
    data_vinculo DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (associado_id, associacao_id),
    KEY idx_associacao (associacao_id),
    CONSTRAINT fk_aa_associado FOREIGN KEY (associado_id) REFERENCES associados(id) ON DELETE CASCADE,
    CONSTRAINT fk_aa_associacao FOREIGN KEY (associacao_id) REFERENCES associacoes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;