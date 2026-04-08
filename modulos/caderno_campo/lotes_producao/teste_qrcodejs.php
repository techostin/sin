<!DOCTYPE html>
<html>
<head>
    <title>Teste QRCode.js</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
</head>
<body>
    <h1>Teste QRCode.js</h1>
    <div id="qrcode"></div>
    <script>
        window.addEventListener('load', function() {
            if (typeof QRCode !== 'undefined') {
                new QRCode(document.getElementById("qrcode"), "TESTE QRCode.js");
                console.log("QRCode.js carregado e QR gerado!");
            } else {
                document.body.innerHTML += '<p style="color:red;">QRCode.js não está disponível</p>';
                console.error("QRCode.js não está disponível");
            }
        });
    </script>
</body>
</html> 