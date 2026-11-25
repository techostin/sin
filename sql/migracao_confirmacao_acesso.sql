
-- ---------------------------------------------------------------------------
-- Confirmação de privilégios do pedido de acesso (Gestor/Fiscal etc.)
--
-- O pedido entra com o nível SOLICITADO (nivel_solicitado) e o perfil escolhido
-- (perfil_solicitado). Só depois de o ADMIN confirmar é que o acesso é liberado
-- com o nível CONCEDIDO (nivel) — que pode ser igual, menor (rebaixado) ou, por
-- decisão do Admin, diferente do solicitado. Fica registrado quem confirmou,
-- quando e (em caso de recusa) o motivo.
-- ---------------------------------------------------------------------------

ALTER TABLE usuarios ADD COLUMN nivel_solicitado TINYINT(1) NULL AFTER nivel;

ALTER TABLE usuarios ADD COLUMN perfil_solicitado VARCHAR(50) NULL AFTER nivel_solicitado;

ALTER TABLE usuarios ADD COLUMN acesso_confirmado_por INT(11) NULL AFTER perfil_solicitado;

ALTER TABLE usuarios ADD COLUMN acesso_confirmado_em DATETIME NULL AFTER acesso_confirmado_por;

ALTER TABLE usuarios ADD COLUMN acesso_negado_motivo VARCHAR(255) NULL AFTER acesso_confirmado_em;
