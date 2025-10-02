id: 38
source: 1
name: rederNewsletterDetail
properties: 'a:0:{}'

-----

use ColorThief\ColorThief;

ini_set('display_errors', 1);

$autoloadPath = MODX_BASE_PATH . 'vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    return "Error: Color Thief is not installed. Run `composer require ksubileau/color-thief-php`.";
}
require_once $autoloadPath;

// Convert WebP to JPEG using GD
function convertWebpToJpeg($inputPath, $outputPath) {
    if (!function_exists('imagecreatefromwebp')) {
        return 'GD WebP support not available.';
    }

    if (!file_exists($inputPath)) {
        return 'WebP file does not exist.';
    }

    $size = filesize($inputPath);
    if ($size < 100) {
        return 'Downloaded WebP is too small (' . $size . ' bytes).';
    }

    $im = @imagecreatefromwebp($inputPath);
    if (!$im) {
        return 'imagecreatefromwebp() failed to read image.';
    }

    if (!imagejpeg($im, $outputPath, 90)) {
        return 'imagejpeg() failed to write JPEG.';
    }

    imagedestroy($im);
    return true;
}

$tpl = $modx->getOption('tpl', $scriptProperties, 'study-note-detail');

$colorCount = (int) $modx->getOption('count', $scriptProperties, 5);
$resource = $modx->resource;

$contentJson = $resource->get('content');
$data = json_decode($contentJson, true);
if (!$data) return 'Invalid JSON in content field.';

// echo '<pre>' . print_r($data, true) . '</pre>';

$coverUrl  = $resource->getTVValue('cover');
$posterUrl = $resource->getTVValue('poster');
if (!$coverUrl) return 'Missing cover image.';

$tempWebp = MODX_BASE_PATH . 'assets/cache/tmp_cover.webp';
$tempJpeg = MODX_BASE_PATH . 'assets/cache/tmp_cover.jpg';

$imageData = @file_get_contents($coverUrl);
if (!$imageData || strlen($imageData) < 100) return 'Error: Could not fetch image.';

file_put_contents($tempWebp, $imageData);
$ext = strtolower(pathinfo($coverUrl, PATHINFO_EXTENSION));

if ($ext === 'webp') {
    $result = convertWebpToJpeg($tempWebp, $tempJpeg);
    if ($result !== true) {
        return 'Error: '. $result;
    }
    $imagePath = $tempJpeg;
    @unlink($tempWebp);
} else {
    $imagePath = $tempWebp;
}

try {
    $palette = ColorThief::getPalette($imagePath, $colorCount);
    @unlink($imagePath);

    $hexColors = array_map(fn($c) => sprintf("#%02x%02x%02x", $c[0], $c[1], $c[2]), $palette);
    $color1 = sprintf("rgba(%d, %d, %d, 0.9)", ...$palette[0]);
    $color2 = $hexColors[1] ?? '#666';

    function getContrastColor($hexColor) {
        list($r, $g, $b) = sscanf($hexColor, "#%02x%02x%02x");
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
        return $luminance > 0.5 ? "#000000" : "#ffffff";
    }

    $color3 = getContrastColor($hexColors[0] ?? '#888888');

} catch (Exception $e) {
    $color1 = 'rgba(68, 68, 68, 0.9)';
    $color2 = '#666';
    $color3 = '#ffffff';
    $hexColors = ['#444', '#666'];
}



$placeholders = array_merge($data, [
    'cover'        => $coverUrl,
    'poster'       => $posterUrl,
    'bgColor'      => $color1,
    'heroText'     => $color2,
    'heroSubtext'  => $color3,
    'title'        => $resource->get('pagetitle'),
    'storeLink'    => $data['link_store'],
    'pdfFile' => $data['pdf_file']['data']['attributes']['url'],
    'description' => $data['description'],
]);

return $modx->getChunk($tpl, $placeholders);