<!DOCTYPE html>
<html>
<head>
    <title>Teste QR Code</title>
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
</head>
<body>
    <h1>Teste QR Code</h1>
    <canvas id="qrcode-test"></canvas>
    
    <script>
        // Função para aguardar o carregamento da biblioteca QRCode
        function waitForQRCode(callback, maxAttempts = 10) {
            let attempts = 0;
            
            function checkQRCode() {
                attempts++;
                console.log('Tentativa', attempts, 'de verificar QRCode:', typeof QRCode !== 'undefined');
                
                if (typeof QRCode !== 'undefined') {
                    console.log('QRCode carregado com sucesso!');
                    callback();
                } else if (attempts < maxAttempts) {
                    console.log('QRCode ainda não carregou, tentando novamente em 500ms...');
                    setTimeout(checkQRCode, 500);
                } else {
                    console.error('QRCode não carregou após', maxAttempts, 'tentativas');
                    callback(false);
                }
            }
            
            checkQRCode();
        }

        window.addEventListener('load', function() {
            console.log('Window loaded - QRCode disponível:', typeof QRCode !== 'undefined');
            
            waitForQRCode(function(success) {
                if (success !== false) {
                    try {
                        QRCode.toCanvas(
                            document.getElementById('qrcode-test'),
                            "LOTE-000001",
                            {
                                width: 200,
                                height: 200,
                                margin: 2,
                                color: {
                                    dark: '#000000',
                                    light: '#FFFFFF'
                                }
                            },
                            function (error) {
                                if (error) {
                                    console.error("Erro:", error);
                                    document.body.innerHTML += '<p style="color: red;">Erro ao gerar QR Code: ' + error + '</p>';
                                } else {
                                    console.log("QR Code gerado com sucesso!");
                                    document.body.innerHTML += '<p style="color: green;">QR Code gerado com sucesso!</p>';
                                }
                            }
                        );
                    } catch (error) {
                        console.error("Erro:", error);
                        document.body.innerHTML += '<p style="color: red;">Erro: ' + error + '</p>';
                    }
                } else {
                    document.body.innerHTML += '<p style="color: red;">QRCode não está disponível</p>';
                }
            });
        });
    </script>
</body>
</html> 