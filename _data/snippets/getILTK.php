id: 40
source: 1
name: getILTK
properties: 'a:0:{}'

-----

use GuzzleHttp\Client;

$tpl = $modx->getOption('tpl', $scriptProperties, 'tpl');
$lang = $modx->getOption('lang', $scriptProperties, 'es');
$limit = $modx->getOption('limit', $scriptProperties, '0');

$client = new Client();
$url = 'https://api.sumtv.org/api/iltks?pagination[pageSize]=5&sort=air_date:desc&populate[0]=video_thumbnail&populate[1]=questions&locale='.$lang;

$htmlOutput = ''; // Initialize HTML output

try {
    $response = $client->request('GET', $url);
    $data = json_decode($response->getBody(), true);

    if (!isset($data['data']) || json_last_error() !== JSON_ERROR_NONE) {
        return 'Error: Invalid JSON response';
    }

    // Remove first item if limit is not 1
    if ($limit !== '1') {
        unset($data['data'][0]);
    }

    $counter = 0;

    foreach ($data['data'] as $item) {
        if ($counter >= $limit && $limit !== '0') break;
        
        $attributes = $item['attributes'];
        $dateString = $attributes['air_date'] ?? '';
        
        // Format date properly (March 25, 2025)
        if (!empty($dateString)) {
            $date = new DateTime($dateString);
            $formatter = new IntlDateFormatter('es_ES', IntlDateFormatter::LONG, IntlDateFormatter::NONE);
            $formattedDate = $formatter->format($date);
        } else {
            $formattedDate = '';
        }

        // Process questions
        $questions = '';
        if (!empty($attributes['questions'])) {
            foreach ($attributes['questions'] as $q) {
                $questions .= $modx->getChunk('iltkQtpl', [
                    'question' => htmlspecialchars($q['question'])
                ]);
            }
        }

        // Ensure thumbnail exists
        $thumbnailUrl = $attributes['video_thumbnail']['data']['attributes']['formats']['medium']['url'] ?? '';

        // Render chunk
        $htmlOutput .= $modx->getChunk($tpl, array_filter([
            'title' => htmlspecialchars($attributes['title'] ?? ''),
            'url' => 'https://sumtv.app/iltk/'.htmlspecialchars($item['id'] ?? ''),
            'ep_number' => htmlspecialchars($attributes['ep_number'] ?? ''),
            'air_date' => $formattedDate,
            'thumbnail_url' => htmlspecialchars($thumbnailUrl),
            'description' => htmlspecialchars($attributes['description'] ?? ''),
            'questions' => $questions
        ]));

        $counter++;
    }

    return $htmlOutput;

} catch (Exception $e) {
    return 'Error: ' . $e->getMessage();
}