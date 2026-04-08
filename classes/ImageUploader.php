<?php
class ImageUploader {
    private $uploadDir;
    private $maxFileSize;
    private $allowedTypes;
    private $maxWidth;
    private $maxHeight;
    private $quality;

    public function __construct($uploadDir = 'uploads/propriedades/') {
        $this->uploadDir = $uploadDir;
        $this->maxFileSize = 5 * 1024 * 1024; // 5MB
        $this->allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        $this->maxWidth = 1200;
        $this->maxHeight = 800;
        $this->quality = 85;
        
        // Criar diretório se não existir
        $this->createUploadDirectory();
    }

    /**
     * Cria o diretório de upload se não existir
     */
    private function createUploadDirectory() {
        if (!file_exists($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }

    /**
     * Faz upload e otimiza uma imagem
     * @param array $file Array do arquivo enviado ($_FILES['field_name'])
     * @param string $prefix Prefixo para o nome do arquivo
     * @return array Array com status e caminho do arquivo
     */
    public function uploadImage($file, $prefix = 'img') {
        try {
            // Validar arquivo
            $validation = $this->validateFile($file);
            if (!$validation['success']) {
                return $validation;
            }

            // Gerar nome único para o arquivo
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = $prefix . '_' . uniqid() . '_' . time() . '.' . $extension;
            $filepath = $this->uploadDir . $filename;

            // Processar e otimizar a imagem
            $result = $this->processImage($file['tmp_name'], $filepath, $extension);
            
            if ($result['success']) {
                return [
                    'success' => true,
                    'filename' => $filename,
                    'filepath' => $filepath,
                    'size' => filesize($filepath),
                    'message' => 'Imagem enviada e otimizada com sucesso!'
                ];
            } else {
                return $result;
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao processar imagem: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Valida o arquivo enviado
     * @param array $file
     * @return array
     */
    private function validateFile($file) {
        // Verificar se houve erro no upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return [
                'success' => false,
                'message' => 'Erro no upload: ' . $this->getUploadErrorMessage($file['error'])
            ];
        }

        // Verificar tamanho
        if ($file['size'] > $this->maxFileSize) {
            return [
                'success' => false,
                'message' => 'Arquivo muito grande. Máximo permitido: ' . ($this->maxFileSize / 1024 / 1024) . 'MB'
            ];
        }

        // Verificar tipo
        if (!in_array($file['type'], $this->allowedTypes)) {
            return [
                'success' => false,
                'message' => 'Tipo de arquivo não permitido. Tipos aceitos: JPG, PNG, WEBP'
            ];
        }

        // Verificar se é realmente uma imagem
        if (!getimagesize($file['tmp_name'])) {
            return [
                'success' => false,
                'message' => 'Arquivo não é uma imagem válida'
            ];
        }

        return ['success' => true];
    }

    /**
     * Processa e otimiza a imagem
     * @param string $sourcePath
     * @param string $destinationPath
     * @param string $extension
     * @return array
     */
    private function processImage($sourcePath, $destinationPath, $extension) {
        try {
            // Obter informações da imagem
            $imageInfo = getimagesize($sourcePath);
            $width = $imageInfo[0];
            $height = $imageInfo[1];
            $type = $imageInfo[2];

            // Calcular novas dimensões mantendo proporção
            $newDimensions = $this->calculateNewDimensions($width, $height);
            $newWidth = $newDimensions['width'];
            $newHeight = $newDimensions['height'];

            // Criar imagem de origem
            $sourceImage = $this->createImageFromFile($sourcePath, $type);
            if (!$sourceImage) {
                return [
                    'success' => false,
                    'message' => 'Não foi possível criar imagem de origem'
                ];
            }

            // Criar nova imagem com dimensões otimizadas
            $newImage = imagecreatetruecolor($newWidth, $newHeight);

            // Manter transparência para PNG
            if ($extension === 'png') {
                imagealphablending($newImage, false);
                imagesavealpha($newImage, true);
                $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
                imagefill($newImage, 0, 0, $transparent);
            }

            // Redimensionar
            imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

            // Salvar imagem otimizada
            $result = $this->saveImage($newImage, $destinationPath, $extension);

            // Limpar memória
            imagedestroy($sourceImage);
            imagedestroy($newImage);

            return $result;

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao processar imagem: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Calcula novas dimensões mantendo proporção
     * @param int $width
     * @param int $height
     * @return array
     */
    private function calculateNewDimensions($width, $height) {
        $ratio = min($this->maxWidth / $width, $this->maxHeight / $height);
        
        // Se a imagem já é menor que o máximo, manter tamanho original
        if ($ratio > 1) {
            return ['width' => $width, 'height' => $height];
        }

        return [
            'width' => round($width * $ratio),
            'height' => round($height * $ratio)
        ];
    }

    /**
     * Cria imagem a partir do arquivo
     * @param string $path
     * @param int $type
     * @return resource|false
     */
    private function createImageFromFile($path, $type) {
        switch ($type) {
            case IMAGETYPE_JPEG:
                return imagecreatefromjpeg($path);
            case IMAGETYPE_PNG:
                return imagecreatefrompng($path);
            case IMAGETYPE_WEBP:
                return imagecreatefromwebp($path);
            default:
                return false;
        }
    }

    /**
     * Salva a imagem processada
     * @param resource $image
     * @param string $path
     * @param string $extension
     * @return array
     */
    private function saveImage($image, $path, $extension) {
        try {
            switch (strtolower($extension)) {
                case 'jpg':
                case 'jpeg':
                    imagejpeg($image, $path, $this->quality);
                    break;
                case 'png':
                    imagepng($image, $path, 9); // Máxima compressão para PNG
                    break;
                case 'webp':
                    imagewebp($image, $path, $this->quality);
                    break;
                default:
                    return [
                        'success' => false,
                        'message' => 'Formato de imagem não suportado'
                    ];
            }

            return ['success' => true];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao salvar imagem: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Remove uma imagem
     * @param string $filename
     * @return bool
     */
    public function removeImage($filename) {
        $filepath = $this->uploadDir . $filename;
        if (file_exists($filepath)) {
            return unlink($filepath);
        }
        return false;
    }

    /**
     * Retorna mensagem de erro do upload
     * @param int $errorCode
     * @return string
     */
    private function getUploadErrorMessage($errorCode) {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
                return 'Arquivo excede o tamanho máximo permitido pelo servidor';
            case UPLOAD_ERR_FORM_SIZE:
                return 'Arquivo excede o tamanho máximo permitido pelo formulário';
            case UPLOAD_ERR_PARTIAL:
                return 'Upload foi feito parcialmente';
            case UPLOAD_ERR_NO_FILE:
                return 'Nenhum arquivo foi enviado';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Falta pasta temporária';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Falha ao escrever arquivo no disco';
            case UPLOAD_ERR_EXTENSION:
                return 'Upload foi interrompido por uma extensão';
            default:
                return 'Erro desconhecido no upload';
        }
    }

    /**
     * Define configurações personalizadas
     * @param array $config
     */
    public function setConfig($config) {
        if (isset($config['maxFileSize'])) $this->maxFileSize = $config['maxFileSize'];
        if (isset($config['maxWidth'])) $this->maxWidth = $config['maxWidth'];
        if (isset($config['maxHeight'])) $this->maxHeight = $config['maxHeight'];
        if (isset($config['quality'])) $this->quality = $config['quality'];
        if (isset($config['allowedTypes'])) $this->allowedTypes = $config['allowedTypes'];
    }
}
?> 