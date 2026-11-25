# Relatório — Confirmação de privilégios do pedido de acesso (Gestor/Fiscal)

**Pedido do cliente:** quando alguém solicita acesso como **Gestor/Fiscal**, o Admin
precisa poder **confirmar se aquele usuário terá, de fato, os privilégios
solicitados** — implementar um método que dê ao Admin essa possibilidade.

## 1. O que foi implementado

Um pedido de acesso passa a registrar **o que foi solicitado** e só é liberado
depois que o **Admin confere e define o nível concedido**:

```
pre_cadastro (perfil=gestor)
  └─> usuarios: nivel=2, nivel_solicitado=2, perfil_solicitado='gestor', status='pendente'
        │
        └─> /associados/pendentes (Admin)
              ├─ CONFIRMAR: escolhe o nível concedido (Admin / Gestor-Fiscal / Usuário)
              │            + órgão/setor/especialidade (vínculo do fiscal)
              ├─ REBAIXAR: concede nível menor de privilégio (ex.: Usuário)
              └─ RECUSAR: informa o motivo (conta fica inativa)
```

### Método criado — `classes/AcessoPendente.php`

| Método | Para que serve |
|---|---|
| **`confirmarPrivilegios($usuarioId, $nivelConcedido, $dados, $admin)`** | **Método principal**: valida que quem confirma é Admin e que o nível está na whitelist, grava o nível concedido + `tipo`, o vínculo profissional (órgão/setor/especialidade), libera a conta e registra `ACESSO_CONFIRMADO` no `audit_log` com *solicitado × concedido* |
| `negarSolicitacao($usuarioId, $motivo, $admin)` | Recusa o pedido: conta fica `inativo`, grava `acesso_negado_motivo` e audita `ACESSO_NEGADO` |
| `solicitacoesPendentes()` / `solicitacao($id)` | Lista/detalha os pedidos aguardando análise |
| `ultimasAnalises($limit)` | Histórico de quem confirmou/recusou e quando |
| `podeConceder($quem, $nivel)` | Regra de autorização reutilizável (somente Admin concede; níveis válidos 1, 2 e 3) |
| `nivelSolicitado($usuario)` | Nível pedido (compatível com pedidos antigos sem a coluna) |
| `rotulo($nivel)` | Rótulo exibido ("Gestor/Fiscal", "Admin", "Usuário") |

### Schema (aditivo)

`sql/migracao_confirmacao_acesso.sql` + `dev-utils/migrar_confirmacao_acesso.php`
(idempotente, dry-run por padrão) adicionam em `usuarios`:
`nivel_solicitado`, `perfil_solicitado`, `acesso_confirmado_por`,
`acesso_confirmado_em`, `acesso_negado_motivo`.

### Tela do Admin — `/associados/pendentes`

Para cada pedido de conta (`origem=usuario`) a coluna
**"Confirmação do Admin (privilégios)"** mostra:

- o **privilégio solicitado** em destaque (badge "Gestor/Fiscal" + perfil escolhido);
- um **select "Conceder"** com Admin / Gestor-Fiscal / Usuário, **já posicionado no
  nível solicitado**;
- os campos **Órgão / Setor / Especialidade** (aparecem quando o pedido é de
  Gestor/Fiscal) para registrar o vínculo profissional na hora da confirmação;
- botões **"Confirmar acesso"** e **recusar com motivo**.

Pedidos da tabela `associados` (fluxo anterior) mantêm os botões Ativar/Rejeitar,
e o botão "ativar" de contas continua funcionando concedendo **exatamente o nível
solicitado** (compatibilidade).

### Regras e segurança

1. **Somente Admin** confirma/recusa (`podeConceder` + `hasPermission(NIVEL_ADMIN)` na página).
2. Níveis aceitos: 1 (Admin), 2 (Gestor/Fiscal), 3 (Usuário) — nada fora da whitelist.
3. CSRF obrigatório em todos os POSTs da tela.
4. `tipo` do usuário é sincronizado com o nível concedido (fim da divergência `nivel` × `tipo`).
5. Tudo auditado: `ACESSO_CONFIRMADO` / `ACESSO_NEGADO` com *solicitado × concedido*,
   e-mail, perfil e quem analisou.
6. **Semântica de privilégio**: no projeto **número menor = mais poder**; conceder um
   número maior que o solicitado é **redução** de privilégios (a mensagem e a auditoria
   refletem isso).

## 2. Evidências (execução real, dados de teste removidos ao final)

```
Pedidos: A=207, B=208, C=209 — todos nivel 2, nivel_solicitado 2, perfil gestor, pendentes

A) Admin CONFIRMA Gestor/Fiscal (nível 2) + vínculo MAPA/SFA/Fiscal
   HTTP 200 · "Acesso liberado como Gestor/Fiscal (privilégios solicitados confirmados)."
   DB: {"nivel":2,"nivel_solicitado":2,"tipo":"gestor","status":"ativo",
        "orgao":"MAPA","setor":"SFA","especialidade":"Fiscal","acesso_confirmado_por":206}
   Audit: ACESSO_CONFIRMADO "solicitado=Gestor/Fiscal(2) concedido=Gestor/Fiscal(2) ..."

B) Admin REBAIXA para Usuário (nível 3)
   "Acesso liberado como Usuário — o pedido de Gestor/Fiscal NÃO foi integralmente
    concedido (privilégios reduzidos)."
   DB: {"nivel":3,"nivel_solicitado":2,"tipo":"usuario","status":"ativo"}

C) Admin RECUSA com motivo "Documentacao incompleta"
   "Solicitação recusada. O interessado verá o motivo ao tentar acessar."
   DB: {"status":"inativo","acesso_negado_motivo":"Documentacao incompleta","acesso_confirmado_por":206}
   Audit: ACESSO_NEGADO "motivo=Documentacao incompleta conta=teste.priv.c@..."

D) Efeitos dos privilégios
   A (Gestor confirmado) ........ login OK · relatório restrito da equipe -> HTTP 200
   B (rebaixado p/ Usuário) ..... login OK · relatório restrito -> HTTP 403 · tela /associados/pendentes NEGADA
   C (recusado) ................. login BLOQUEADO · API -> HTTP 401

E) Segurança
   Gestor tentando conceder privilégios a outro -> "Acesso negado" (a tela exige Admin)
   Admin SEM token CSRF -> nada muda (status permanece pendente)
```

## 3. Compatibilidade e pendências

- Pedidos antigos (sem `nivel_solicitado`) continuam funcionando: o nível solicitado
  é lido do próprio `nivel` e o `dev-utils/migrar_confirmacao_acesso.php --apply`
  faz o *backfill* dos pendentes.
- O fluxo legado de `associados` em `/associados/pendentes` permanece igual.
- Sugestões de evolução: (a) notificar o solicitante por e-mail quando o acesso for
  confirmado/recusado (o `Mailer` já existe); (b) registrar também qual admin
  **solicitou** a análise em caso de reanálise; (c) aplicar a mesma confirmação na
  criação de contas pela tela `/usuario/novo` (hoje o Admin já escolhe o nível ali).
