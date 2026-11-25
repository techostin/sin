-- ---------------------------------------------------------------------------
-- Ficha de Adesão vinculada à CONTA (usuarios)
--
-- Novo fluxo de entrada no sistema:
--   1. pedido de acesso           -> usuarios (status 'pendente')
--   2. Admin autoriza             -> usuarios.status = 'ativo'
--   3. primeiro acesso            -> ficha de adesão (ligada a usuarios.id)
--   4. ficha submetida            -> status 'pendente' (Diretoria avalia)
--   5. ficha aprovada             -> cria associados a partir da ficha e vincula
--                                    usuarios.associado_id <-> associados.user_id
--                                    (mesma pessoa, sem duplicidade de identidade)
--
-- Por isso a ficha passa a existir ANTES do cadastro de associado:
--   - ficha_adesao.associado_id torna-se NULLABLE
--   - ficha_adesao.usuario_id identifica a conta que pediu acesso
-- ---------------------------------------------------------------------------

ALTER TABLE ficha_adesao MODIFY associado_id INT(11) NULL;

ALTER TABLE ficha_adesao ADD COLUMN usuario_id INT(11) NULL AFTER associado_id;

ALTER TABLE ficha_adesao ADD UNIQUE KEY uq_ficha_usuario (usuario_id);

ALTER TABLE ficha_adesao
    ADD CONSTRAINT fk_ficha_adesao_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL;

-- ---------------------------------------------------------------------------
-- Perfil de produção do PEDIDO DE ACESSO (conta em usuarios)
-- A ficha de adesão é preenchida depois; até lá o pedido precisa guardar se o
-- solicitante é agricultor/agroindústria e quais áreas produz.
-- Na aprovação da ficha esses dados são copiados para o cadastro do associado.
-- ---------------------------------------------------------------------------
ALTER TABLE usuarios ADD COLUMN tem_agroindustria TINYINT(1) NOT NULL DEFAULT 0 AFTER tipo_usuario;

ALTER TABLE usuarios ADD COLUMN area_producao SET('animal','vegetal','agroindustria') NOT NULL DEFAULT '' AFTER tem_agroindustria;
