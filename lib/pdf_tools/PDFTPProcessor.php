<?php

require_once __DIR__ . '/vendor/autoload.php';

use ZipArchive;
use setasign\Fpdi\Tcpdf\Fpdi;

class PDFTPProc
{
    private $templates, $data, $tempDir, $group_text;

    public function __construct($template)
    {
        $this->tempDir = $_SERVER["DOCUMENT_ROOT"] . '/templates/tmp';
        $this->templates = $template;
        if (!file_exists($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
        $this->group_text = [];

        // Перевірка залежностей
        if (!class_exists('ZipArchive')) {
            throw new Exception('ZipArchive extension не встановлено');
        }

        if (!class_exists('setasign\Fpdi\Tcpdf\Fpdi')) {
            throw new Exception('FPDI бібліотека не знайдена');
        }
    }

    function setData($data)
    {
        $this->data = $data;
    }

    private function splitTextByWords($text, $maxLength)
    {
        // Приведення до строки та очищення від зайвих пробілів
        $text = (string)$text;
        $text = trim($text);

        // Якщо текст порожній
        if (empty($text)) {
            return ['first' => '', 'remaining' => ''];
        }

        // Якщо весь текст поміщається в ліміт
        if (mb_strlen($text) <= $maxLength) {
            return ['first' => $text, 'remaining' => ''];
        }

        // Розбиваємо текст на слова
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        $firstPart = '';
        $wordIndex = 0;

        // Додаємо слова поки поміщаються
        foreach ($words as $index => $word) {
            $separator = $firstPart ? ' ' : '';
            $testString = $firstPart . $separator . $word;

            if (mb_strlen($testString) <= $maxLength) {
                $firstPart = $testString;
                $wordIndex = $index + 1;
            } else {
                break;
            }
        }

        // Формуємо залишок тексту
        $remainingWords = array_slice($words, $wordIndex);
        $remaining = implode(' ', $remainingWords);

        return [
            'first' => $firstPart,
            'remaining' => $remaining
        ];
    }

    function getFields()
    {
        $jsonFileName = 'config.json';
        $zip = new ZipArchive();
        $result = $zip->open($this->templates);

        if ($result !== TRUE) {
            throw new Exception("Не вдалося відкрити .pdftp архів. Код помилки: " . $result);
        }

        // Спосіб 1: Читання через getFromName()
        $jsonContent = $zip->getFromName($jsonFileName);
        $zip->close();

        if ($jsonContent === false) {
            throw new Exception("Файл '$jsonFileName' не знайдено в архіві");
        }

        $config = json_decode($jsonContent, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Помилка парсингу JSON: " . json_last_error_msg());
        }

        return $config;
    }

    function save($outFile)
    {
        $uncompressedPDF = null;
        $extractedFiles = null;

        try {
            // Перевірка існування template файлу
            if (!file_exists($this->templates)) {
                throw new Exception("Template файл не знайдено: " . $this->templates);
            }

            // Перевірка прав на запис
            $outputDir = dirname($outFile);
            if (!is_writable($outputDir)) {
                throw new Exception("Немає прав на запис в директорію: " . $outputDir);
            }

            $extractedFiles = $this->extractPDFTP($this->templates);
            $config = json_decode(file_get_contents($extractedFiles['config']), true);

            if (!$config) {
                throw new Exception("Не вдалося прочитати config.json");
            }

            // ВИПРАВЛЕНО: зберігаємо шлях до uncompressed PDF
            $uncompressedPDF = $this->decompressPDF($extractedFiles['pdf']);

            $pdf = new Fpdi('P', 'pt', [$config['pdfInfo']['originalSize']['width'], $config['pdfInfo']['originalSize']['height']]);
            $pageCount = $pdf->setSourceFile($uncompressedPDF);

            $pdf->SetFont('dejavusans', '', 12);
            $pdf->SetMargins(0, 0, 0, true);
            $pdf->SetHeaderMargin(0);
            $pdf->SetFooterMargin(0);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetAutoPageBreak(false, 0);

            for ($pageNum = 1; $pageNum <= $pageCount; $pageNum++) {
                $templateId = $pdf->importPage($pageNum);
                $pdf->AddPage();
                $pdf->SetXY(0, 0);
                $size = $pdf->getTemplateSize($templateId);
                $pdf->useTemplate($templateId, 0, 0, $size['width'], $size['height']);
                $fields = $config['fields'][$pageNum] ?? [];

                foreach ($fields as $field) {
                    $this->setField($pdf, $field);
                }
            }

            $absolute_path = pathinfo($outFile);
            if (!file_exists($absolute_path['dirname'])) {
                mkdir($absolute_path['dirname'], 0755, true);
            }

            $pdf->Output($outFile, 'F');

            // ВИПРАВЛЕНО: правильний порядок видалення
            $this->cleanup($extractedFiles);

            // Видаляємо uncompressed PDF файл
            if ($uncompressedPDF && file_exists($uncompressedPDF)) {
                unlink($uncompressedPDF);
            }

            return ['success' => true, 'outputPath' => $outFile];

        } catch (Exception $e) {
            // ДОДАНО: очищення в разі помилки
            if ($extractedFiles) {
                $this->cleanup($extractedFiles);
            }
            if ($uncompressedPDF && file_exists($uncompressedPDF)) {
                unlink($uncompressedPDF);
            }

            error_log("PDFTPProc Error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function setField($pdf, $field)
    {
        // Валідація обов'язкових полів
        $requiredFields = ['x', 'y', 'width', 'height'];
        foreach ($requiredFields as $reqField) {
            if (!isset($field[$reqField]) || !is_numeric($field[$reqField])) {
                error_log("Невірне або відсутнє поле '$reqField' в конфігурації поля");
                return; // Пропускаємо це поле
            }
        }

        $fontSize = isset($field['fontSize']) && is_numeric($field['fontSize']) ? $field['fontSize'] : 12;
        $maxLength = isset($field['maxLength']) && is_numeric($field['maxLength']) ? $field['maxLength'] : 1000;

        // Безпечне отримання значення поля
        $fieldValue = '';

        // ВИПРАВЛЕНО: правильна логіка для defaultValue
        if (!empty($field['defaultValue'])) {
            $fieldValue = (string)$field['defaultValue'];
        } elseif (!empty($field['key']) && isset($this->data[$field['key']])) {
            $fieldValue = (string)$this->data[$field['key']];
        }

        // Обробка групових полів
        if (!empty($field['groupId'])) {
            // ВИПРАВЛЕНО: безпечна ініціалізація групового тексту
            if (!isset($this->group_text[$field['groupId']])) {
                $this->group_text[$field['groupId']] = isset($this->data[$field['groupId']])
                    ? (string)$this->data[$field['groupId']]
                    : '';
            }

            // ВИПРАВЛЕНО: безпечна перевірка на порожність
            $groupText = $this->group_text[$field['groupId']];
            if (!empty($groupText)) {
                $split = $this->splitTextByWords($groupText, $maxLength);
                $fieldValue = $split['first'] ?? '';
                $this->group_text[$field['groupId']] = $split['remaining'] ?? '';
            }
        }
        $this->renderField($pdf, $field, $fieldValue);
        /*
             $pdf->SetFont('dejavusans', '', $fontSize);


             $pdf->SetXY($field['x'], $field['y']);
             $pdf->Cell(
                 $field['width'],  // ширина
                 $field['height'], // висота
                 $fieldValue,   // текст
                 0,                // рамка
                 0,                // ln (не переносити курсор)
                 !empty($field['isCenter']) ? 'C' : 'L', // вирівнювання
                 false             // заливка
             );


             $pdf->MultiCell(
                 $field['width'],  // ширина
                 $field['height'], // висота
                 $fieldValue,      // текст
                 0,                // рамка
                 !empty($field['isCenter']) ? 'C' : 'L', // вирівнювання
                 false,            // заливка
                 1,                // ln
                 $field['x'],      // x позиція
                 $field['y']       // y позиція
             );
     */
    }

    private function renderField($pdf, $field, $text)
    {
        $pdf->SetFont('dejavusans', '', $field['fontSize'] ?? 12);

        // Розбиваємо текст на рядки
        $words = explode(' ', $text);
        $lines = [];
        $currentLine = '';

        foreach ($words as $word) {
            $testLine = $currentLine ? $currentLine . ' ' . $word : $word;
            if ($pdf->GetStringWidth($testLine) <= $field['width']) {
                $currentLine = $testLine;
            } else {
                if ($currentLine) $lines[] = $currentLine;
                $currentLine = $word;
            }
        }
        if ($currentLine) $lines[] = $currentLine;

        // Відображаємо з переносом вгору
        $startY = $field['y'] - ((count($lines) - 1) * $field['height']);

        foreach ($lines as $i => $line) {
            $pdf->SetXY($field['x'], $startY + ($i * $field['height']));
            $pdf->Cell($field['width'], $field['height'], $line, 0, 0, !empty($field['isCenter']) ? 'C' : 'L', false);
        }
    }

    private function decompressPDF($pdfPath)
    {
        $uncompressedPath = $this->tempDir . '/pdf_uncompress_' . uniqid() . '.pdf';

        if (file_exists('/usr/bin/pdftk')) {
            exec(sprintf('pdftk %s output %s uncompress 2>/dev/null',
                escapeshellarg($pdfPath), escapeshellarg($uncompressedPath)), $output, $code);
            if ($code === 0 && file_exists($uncompressedPath)) {
                return $uncompressedPath;
            }
        }

        copy($pdfPath, $uncompressedPath);
        return $uncompressedPath;
    }

    private function cleanup($extractedFiles)
    {
        $this->removeDirectory($extractedFiles['dir']);
    }

    private function removeDirectory($dir)
    {
        if (!is_dir($dir)) return;

        foreach (array_diff(scandir($dir), ['.', '..']) as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function extractPDFTP($templatePath)
    {
        error_log("Спроба відкрити архів: " . $templatePath);

        $zip = new ZipArchive();
        $result = $zip->open($templatePath);

        if ($result !== TRUE) {
            $errorMsg = "Не вдалося відкрити .pdftp архів. Код помилки: " . $result;
            error_log($errorMsg);
            throw new Exception($errorMsg);
        }

        $extractDir = $this->tempDir . '/pdftp_' . uniqid();
        if (!mkdir($extractDir, 0755, true)) {
            throw new Exception("Не вдалося створити тимчасову директорію: " . $extractDir);
        }

        $zip->extractTo($extractDir);
        $zip->close();

        // Перевірка існування необхідних файлів
        $pdfFile = $extractDir . '/template.pdf';
        $configFile = $extractDir . '/config.json';

        if (!file_exists($pdfFile)) {
            throw new Exception("template.pdf не знайдено в архіві");
        }

        if (!file_exists($configFile)) {
            throw new Exception("config.json не знайдено в архіві");
        }

        return [
            'pdf' => $pdfFile,
            'config' => $configFile,
            'dir' => $extractDir
        ];
    }
}