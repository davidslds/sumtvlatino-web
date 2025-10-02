id: 42
source: 1
name: getWs
properties: 'a:0:{}'

-----

// Include Guzzle. If using Composer:
$tpl = $modx->getOption('tpl', $scriptProperties, 'tpl');
$lang = $modx->getOption('lang', $scriptProperties, 'sp');
$limit = $modx->getOption('limit', $scriptProperties, '0');

use GuzzleHttp\Client;

// Base URL of the API
$baseUrl = 'https://api.sumtv.org/api/worship-services?';

// Get the current page from the request, default to 1
$page = $modx->getOption('page', $_GET, 1);

// Items per page
// $limit = 2;

// Calculate the offset
$offset = ($page - 1) * $limit;




try {
  // Prepare the API request with pagination parameters
$client = new Client();
$response = $client->request('GET', $baseUrl, [
    'query' => [
        'pagination[limit]' => $limit,
        'pagination[start]' => $offset,
        'sort' => 'air_date:desc',
        'locale' => $lang,
        'populate[0]' => 'video_thumbnail',
        'populate[1]' => 'sermon_speakers',

    ]
]);

$data = json_decode($response->getBody(), true);

$htmlOutput = ''; // Initialize HTML output
    $counter = 0;
    

    foreach ($data['data'] as $item) {
      if ($counter >= $limit && $limit !== '0') break;
        $attributes = $item['attributes'];
        $dateString = $attributes['air_date'];
        $date = new DateTime($dateString);
         $formatter = new IntlDateFormatter('es_ES', IntlDateFormatter::MEDIUM, IntlDateFormatter::NONE);
         // Handling speakers
        $speakerNames = array_map(function ($sp) {
            return htmlspecialchars($sp['attributes']['fullname']);
        }, $item['attributes']['sermon_speakers']['data'] ?? []);
        $speakers = implode(', ', $speakerNames);
         
        $htmlOutput .= $modx->getChunk($tpl, [
            'title' => htmlspecialchars($attributes['ss_lesson']),
            'url' => 'https://sumtv.app/worship-service/'.htmlspecialchars($item['id']),
            'ep_number' =>htmlspecialchars($attributes['ep_number']),
            'air_date' => $formatter->format($date),
            'thumbnail_url' => htmlspecialchars($attributes['video_thumbnail']['data']['attributes']['formats']['medium']['url']),
            'description' => htmlspecialchars($attributes['description']),
            'sermon_speakers' => $speakers,
            'speaker_title' => $item['attributes']['sermon_speakers']['data'][0]['attributes']['title'],
          
        ]);
        $counter++; // Increment the counter
    }

    $htmlOutput .= '';
    return $htmlOutput;

} catch (Exception $e) {
    return 'Error: ' . $e->getMessage();
}