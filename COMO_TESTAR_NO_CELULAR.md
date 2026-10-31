# Como testar o app SINPORG no seu celular

Guia para rodar o app no aparelho real, usando **Expo Go** — sem build nativo, sem
Android Studio, sem conta de desenvolvedor.

> Verificado em 12/09/2026: `expo-sqlite` e `expo-secure-store` estao **incluidos
> no Expo Go** para o SDK 57. Nao e preciso build de desenvolvimento.

---

## Antes de comecar

| Item | Como conferir |
|---|---|
| XAMPP com MySQL rodando | painel do XAMPP, servico **MySQL** verde |
| Celular no **mesmo Wi-Fi** do PC | Configuracoes > Wi-Fi |
| App **Expo Go** instalado e atualizado | Play Store / App Store: "Expo Go" — veja a secao de versao abaixo |
| Dependencias instaladas | `cd sinporg && pnpm install` |

**Rede:** seu PC esta em `192.168.0.41`. O celular precisa estar na mesma rede —
rede de convidados ou 4G **nao** funcionam.

---

## Contas para entrar no app

### Conta de teste criada para isso

```
E-mail:  admin.teste@sinporg.local
Senha:   TesteAdmin!2026
```

O app **nao pergunta o perfil**. Quem decide se a conta e agricultor ou tecnico e o
servidor, procurando primeiro em `associados` e depois em `usuarios` — igual ao
`login.php`.

Nivel 1 (admin) — enxerga tudo e tem acesso a todas as rotas. **Verificada em
12/09/2026**: login, `/auth/me`, listagem de propriedades e download do relatorio
Excel, todos com HTTP 200.

> **Remova antes de qualquer uso em producao:**
> ```sql
> DELETE FROM usuarios WHERE email = 'admin.teste@sinporg.local';
> ```

### Contas reais (voce define a senha)

Existem 3 administradores. As senhas estao em **bcrypt** — nao ha como recupera-las:

| Nivel | Contas |
|---|---|
| 1 — admin | `techostin@gmail.com`, `joaosilva@gmail.com`, `biel@gmail.com` |
| 2 — gestor | `kajiani@gmail.com`, `vanessa@gmail.com` |
| 3 — usuario | `roseleiporto@gmail.com`, `getulio@gmail.com`, `alderi@gmail.com`, `tulio@gmail.com`, `joselainesc@gmail.com` |

Se souber a senha de alguma, use-a. Nao ha seletor de perfil no app.

> **Atencao — contas com e-mail repetido:** quatro e-mails existem nas **duas**
> tabelas, com **senhas diferentes**:
>
> `techostin@gmail.com`, `roseleiporto@gmail.com`, `getulio@gmail.com`, `alderi@gmail.com`
>
> Como o PHP (e agora a API) procura `associados` primeiro, **vale a senha de
> `associados`** — a de `usuarios` e ignorada. Se a sua senha de admin e a de
> `usuarios`, ela nao vai funcionar ate esse dado ser corrigido.

**Esqueceu a senha?** O app tem recuperacao por e-mail (aba de login do site), mas
exige a `RESEND_API_KEY` rotacionada. No banco de desenvolvimento, o caminho direto:

```powershell
cd C:\xampp\htdocs\app\sinporg\apps\api
node -e "require('bcryptjs').hash('NovaSenha!123',10).then(h=>console.log(h))"
```

Copie o hash e rode:

```sql
UPDATE usuarios SET senha = '<hash gerado>' WHERE email = 'seu@email.com';
```

### Testar como agricultor

Para ver a experiencia do campo, use tambem o perfil **"Agricultor"** no app. O
login de agricultor autentica na tabela `associados` (30 contas, todas com senha).
Os e-mails sao os dos associados — consulte com:

```sql
SELECT id, nome, email FROM associados WHERE email IS NOT NULL ORDER BY id LIMIT 10;
```

As senhas tambem sao bcrypt; use o mesmo procedimento acima na tabela `associados`.

---

## Qual versao do Expo Go instalar

**A atual da loja (Play Store / App Store).** Nada especial.

O motivo: o Expo Go e versionado **igual ao SDK**. Seu projeto esta no **SDK 57**
(`expo@57.0.22`), e o SDK 57 e o **estavel mais recente**:

| Fonte | Versao |
|---|---|
| `expo` dist-tag `latest` | **57.0.22** |
| Ultima release do Expo Go | **Expo Go 57.0.9** (15/08/2026) |
| SDK 58 | apenas `next: 58.0.0-preview.0` e `canary` — **nao e estavel** |

Ou seja: a loja entrega o Expo Go 57.x, que casa exatamente com o projeto.

### Como conferir depois de instalar

Abra o Expo Go: a tela inicial mostra **"SDK 57"** (ou o numero do SDK suportado).
Se mostrar outro numero, e que o app da loja ja avancou — veja o plano B abaixo.

### Cuidados

- **Atualize** o Expo Go se ja tiver instalado de antes (versoes velhas nao abrem
  projetos de SDK novo)
- Instale **da loja oficial**, nao de sites de APK: o Expo Go da loja e assinado e
  recebe atualizacao automatica
- Quando o **SDK 58** ficar estavel, a loja vai atualizar o Expo Go para 58 e este
  projeto (57) parara de abrir. Nessa hora o caminho e subir o projeto para o SDK 58
  — o Expo suporta cada SDK por cerca de um ano

### Plano B: se a versao nao casar

**Android:** o proprio `npx expo start` resolve. Ao rodar o script, o terminal
oferece instalar no aparelho a versao do Expo Go **correspondente ao projeto**.
Aceite a instalacao pelo CLI.

**iOS:** isso **nao e possivel** — a Apple restringe instalacao fora da loja, e o CLI
so consegue fazer isso em simulador. Se a loja ja tiver avancado, as opcoes sao:
- subir o projeto para o SDK que a loja suporta, ou
- gerar um **development build** (`npx eas build --profile development`), que nao
  depende do Expo Go

---

## Passo a passo

### 1. Suba tudo com um comando

```powershell
cd C:\xampp\htdocs\app
.\scripts\testar-no-celular.ps1
```

O script confere o MySQL, descobre o IP, compila a API se preciso e sobe os dois
servidores.

**O Windows vai perguntar sobre o firewall.** Marque **Redes privadas** e clique em
**Permitir acesso**. Sem isso o celular nao enxerga o PC — e e a causa mais comum
de "nao conecta".

### 2. Leia o QR code

No terminal vai aparecer um QR code grande. No celular:

- **Android:** abra o **Expo Go** > "Scan QR code"
- **iPhone:** abra a **camera** e aponte — ela oferece abrir no Expo Go

O app baixa o bundle (alguns segundos na primeira vez) e abre na tela de login.

### 3. Entre

Use um usuario real do sistema. O app ja descobre sozinho o endereco da API — ele
pega o IP da maquina do proprio servidor do Metro, sem configuracao.

Para conferir qual endereco esta em uso: aba **Sincronizacao** > linha "API:".

---

## O que testar

### O caminho principal (é aqui que está o valor)

1. **Login** como agricultor
2. **Aba Atividades** > registre algo, ex.: "Capina do talhao 3", tipo *Capina*
3. O registro aparece como **"Registro guardado no aparelho."**

### O teste que importa: offline

4. **Ative o modo aviao** do celular
5. Registre **mais duas atividades**
6. Repare:
   - a faixa amarela diz **"N registro(s) aguardando envio"**
   - a lista **nao** atualiza (sem rede, como esperado)
7. **Feche o app completamente** (nao so minimize) e **abra de novo**
8. Va em **Sincronizacao**: os registros **continuam la** — a fila esta no SQLite
9. **Desligue o modo aviao**
10. Abra **Sincronizacao** e toque em **Sincronizar agora**

**O resultado esperado:** os registros sobem, a contagem volta a zero e eles
aparecem na lista de Atividades.

### O teste de robustez (reenvio)

11. Sem internet, registre uma atividade
12. Volte a internet e sincronize
13. Se puder, repita a sincronizacao logo em seguida

**Nada pode duplicar.** O `uuid` gerado no aparelho faz o servidor reconhecer o
reenvio e responder "duplicado" em vez de criar de novo. Esse e o ponto que
protege o agricultor de perder ou duplicar registro no campo.

---

## Se algo der errado

| Sintoma | Causa provavel | Solucao |
|---|---|---|
| "Nao foi possivel entrar" | celular nao alcanca o PC | confira o firewall (liberar Node) e se estao no mesmo Wi-Fi |
| Tela "API:" mostra IP errado | IP mudou (DHCP) | feche o Metro (Ctrl+C) e rode o script de novo |
| QR code nao abre | versao do Expo Go antiga | atualize o Expo Go |
| App abre mas tudo falha | XAMPP/MySQL parado | inicie o MySQL no painel do XAMPP |
| Sincroniza mas nao aparece | filtro de certificadora | veja observacao abaixo |

**Diagnostico rapido:** a aba **Sincronizacao** mostra o endereco exato da API.
Abra esse endereco no navegador **do celular** acrescentando `/health`:

```
http://192.168.0.41:3001/api/v2/health
```

Se o navegador do celular mostrar `{"status":"ok",...}`, a rede esta OK e o
problema e no app. Se nao mostrar, e rede/firewall.

---

## Duas observacoes honestas

### 1. Nada disso foi executado por mim

Escrevi e verifiquei o app **compilando, tipando e empacotando** (bundle Android de
2,32 MB). Mas **nao rodei em nenhum aparelho** — nao tenho emulador aqui.

Entao: sei que o codigo esta correto sintaticamente e que o bundle se forma. O que
**nao** sei e como a navegacao se comporta, se o SQLite grava como esperado ou se o
layout fica bom na tela. **Isso e exatamente o que este teste responde.**

Se algo falhar, me diga a mensagem que aparece — e o log do Metro na janela do
script.

### 2. O filtro de certificadora pode esconder registros

`AUTH_CERTIFICADORA_OBRIGATORIA=false` no `sinporg/.env`, entao **nao** se aplica
escopo — voce vera todos os registros.

Se mudar para `true`, um usuario com certificadora so ve registros **daquela**
certificadora. Como os 30 associados tem `certificadora_id` majoritariamente NULL,
ligar a flag agora pode fazer a lista parecer vazia. E o comportamento correto, mas
confuso sem contexto.

---

## Alternativa: build de desenvolvimento

So e necessario se voce adicionar um modulo que **nao** esta no Expo Go (camera
com recursos extras, por exemplo). Exige Android SDK ou uma conta Expo:

```bash
cd sinporg/apps/mobile
npx expo run:android    # exige Android SDK instalado
# ou
npx eas build --profile development --platform android   # exige conta Expo
```

Para o que esta implementado hoje, **Expo Go basta**.
