<!DOCTYPE html>
<html>
<head>
    <title>Teste AJAX</title>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
    <h1>Teste AJAX</h1>
    <button onclick="testarAjax()">Testar AJAX</button>
    <div id="resultado"></div>

    <script>
    function testarAjax() {
        const url = window.location.origin + '/evento/1';
        console.log('Testando URL:', url);
        
        fetch(url, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            console.log('Status:', response.status);
            return response.text();
        })
        .then(data => {
            console.log('Dados:', data);
            document.getElementById('resultado').innerHTML = '<pre>' + data + '</pre>';
        })
        .catch(error => {
            console.error('Erro:', error);
            document.getElementById('resultado').innerHTML = 'Erro: ' + error.message;
        });
    }
    </script>
</body>
</html> 