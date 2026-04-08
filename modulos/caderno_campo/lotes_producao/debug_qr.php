<!DOCTYPE html>
<html>
<head>
    <title>Debug QR Code</title>
    <script>
        // Verificar se a biblioteca já está carregada
        console.log('Antes do carregamento - QRCode disponível:', typeof QRCode !== 'undefined');
    </script>
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
    <script>
        // Verificar após o carregamento
        console.log('Após carregamento - QRCode disponível:', typeof QRCode !== 'undefined');
        if (typeof QRCode !== 'undefined') {
            console.log('QRCode object:', QRCode);
            console.log('QRCode methods:', Object.getOwnPropertyNames(QRCode));
        }
    </script>
</head>
<body>
    <h1>Debug QR Code</h1>
    <p>Verifique o console do navegador para informações de debug.</p>
    <canvas id="qrcode-test"></canvas>
    
    <script>
        window.addEventListener('load', function() {
            console.log('Window loaded - QRCode disponível:', typeof QRCode !== 'undefined');
            
            if (typeof QRCode !== 'undefined') {
                console.log('Tentando gerar QR Code...');
                try {
                    QRCode.toCanvas(
                        document.getElementById('qrcode-test'),
                        "TESTE-123",
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
                                console.error("Erro ao gerar QR Code:", error);
                                document.body.innerHTML += '<p style="color: red;">Erro: ' + error + '</p>';
                            } else {
                                console.log("QR Code gerado com sucesso!");
                                document.body.innerHTML += '<p style="color: green;">QR Code gerado com sucesso!</p>';
                            }
                        }
                    );
                } catch (error) {
                    console.error("Erro catch:", error);
                    document.body.innerHTML += '<p style="color: red;">Erro catch: ' + error + '</p>';
                }
            } else {
                console.error('QRCode não está disponível após carregamento da página');
                document.body.innerHTML += '<p style="color: red;">QRCode não está disponível</p>';
            }
        });
    </script>
</body>
</html> 