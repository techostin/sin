<?php
/**
 * Página: Solicitação de recuperação de senha.
 *
 * Exibe o formulário de e-mail; a requisição é enviada via AJAX para
 * api/recuperar_senha.php (para evitar recarregar a página e uniformizar
 * mensagens de erro/sucesso).
 */
require_once __DIR__ . '/config/config.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar senha &mdash; <?php echo SITE_TITLE; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { margin:0; min-height:100vh; font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif; background-color:#0f2417; background-image:url('<?php echo SITE_URL; ?>/assets/img/login-bg.jpg'); background-size:cover; background-position:center 30%; background-repeat:no-repeat; background-attachment:fixed; }
        body::before { content:''; position:fixed; inset:0; background:linear-gradient(120deg, rgba(5,30,15,0.55), rgba(10,40,20,0.60)); z-index:0; }
        .wrap { position:relative; z-index:1; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:1.5rem; }
        .card-reset { width:100%; max-width:430px; background:rgba(255,255,255,0.97); border-radius:20px; box-shadow:0 28px 64px rgba(0,0,0,0.45); overflow:hidden; }
        .card-head { background:linear-gradient(135deg,#0f5132 0%,#198754 60%,#20c997 100%); padding:1.6rem 1.6rem 1.3rem; text-align:center; color:#fff; }
        .card-head h1 { font-size:1.15rem; font-weight:700; margin:0; }
        .card-head p { font-size:0.8rem; margin:0.3rem 0 0; color:rgba(255,255,255,0.85); }
        .card-body { padding:1.75rem; }
        .form-label { font-weight:600; font-size:0.85rem; color:#374151; margin-bottom:0.4rem; }
        .form-control { min-height:50px; border-radius:10px; border:1.5px solid #e5e7eb; padding:0.625rem 1rem; }
        .form-control:focus { border-color:#198754; box-shadow:0 0 0 3px rgba(25,135,84,0.15); outline:none; }
        .btn-primary-full { width:100%; min-height:52px; background:linear-gradient(135deg,#198754 0%,#20c997 100%); border:none; border-radius:12px; color:#fff; font-weight:700; }
        .btn-primary-full:hover { filter:brightness(1.05); }
        .btn-loader { display:none; }
        .btn-primary-full.loading .btn-text { display:none; }
        .btn-primary-full.loading .btn-loader { display:inline-block; }
        @keyframes spin { from { transform:rotate(0deg); } to { transform:rotate(360deg); } }
        .btn-loader i { animation:spin 1s linear infinite; }
        .icon-i { font-size:2.4rem; color:#198754; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card-reset">
            <div class="card-head">
                <h1>Recuperar senha</h1>
                <p><?php echo SITE_TITLE; ?> &mdash; Recuperação de acesso</p>
            </div>
            <div class="card-body">
                <div class="text-center mb-3">
                    <i class="bi bi-envelope-at icon-i"></i>
                </div>
                <p class="text-muted small mb-3">
                    Informe o e-mail cadastrado na sua conta. Enviaremos um link com
                    validade de <strong>60 minutos</strong> para você definir uma nova senha.
                </p>

                <div id="alert-msg" class="alert d-none mb-3"></div>

                <form id="form-recuperar" novalidate>
                    <div class="mb-4">
                        <label for="email" class="form-label">E-mail</label>
                        <input type="email" class="form-control" id="email" name="email"
                               placeholder="seu@email.com" autocomplete="email"
                               autocapitalize="none" autocorrect="off" spellcheck="false" required>
                    </div>
                    <button type="submit" class="btn btn-primary-full" id="btn-enviar">
                        <span class="btn-text"><i class="bi bi-send me-1"></i> Enviar link de recuperação</span>
                        <span class="btn-loader"><i class="bi bi-arrow-repeat"></i> Enviando...</span>
                    </button>
                </form>

                <p class="text-center small text-muted mt-3 mb-0">
                    <a href="<?php echo SITE_URL; ?>/login">Voltar para o login</a>
                </p>
            </div>
        </div>
    </div>

    <script>
        var SITE_URL = '<?php echo SITE_URL; ?>';

        function mostrarAlerta(tipo, msg) {
            var el = document.getElementById('alert-msg');
            el.className = 'alert alert-' + tipo + ' mb-3';
            el.innerHTML = '<i class="bi ' + (tipo === 'success' ? 'bi-check-circle' : 'bi-exclamation-triangle-fill') + ' me-1"></i>' + msg;
            el.classList.remove('d-none');
        }

        document.getElementById('form-recuperar').addEventListener('submit', function(e) {
            e.preventDefault();
            var form = this;
            var btn = document.getElementById('btn-enviar');
            var email = document.getElementById('email').value.trim();

            document.getElementById('alert-msg').classList.add('d-none');
            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                mostrarAlerta('danger', 'Informe um e-mail válido.');
                return;
            }

            btn.classList.add('loading');

            fetch(SITE_URL + '/api/recuperar_senha.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'email=' + encodeURIComponent(email)
            })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                btn.classList.remove('loading');
                mostrarAlerta(res.success ? 'success' : 'danger', res.message || res.error || 'Erro inesperado.');
                if (res.success) {
                    form.querySelector('input').disabled = true;
                    btn.disabled = true;
                }
            })
            .catch(function() {
                btn.classList.remove('loading');
                mostrarAlerta('danger', 'Erro de comunicação. Tente novamente em instantes.');
            });
        });
    </script>
</body>
</html>