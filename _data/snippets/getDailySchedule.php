id: 30
source: 1
name: getDailySchedule
properties: 'a:0:{}'

-----

ini_set('display_errors', 1);

use GuzzleHttp\Client;

$outerTpl = $modx->getOption('outerTpl', $scriptProperties, 'outerTpl');
$tabsTpl = $modx->getOption('tabsTpl', $scriptProperties, 'tabsTpl');
$tabsContentTpl = $modx->getOption('tabsContentTpl', $scriptProperties, 'tabsContentTpl');
$tabsContentOuterTpl = $modx->getOption('tabsContentOuterTpl', $scriptProperties, 'tabsContentOuterTpl');

$htmlOutput = '';

date_default_timezone_set('America/Los_Angeles');
setlocale(LC_TIME, 'es_ES.UTF-8'); // Will work on some systems, fallback below if needed

// Fallback: Day abbreviation translations
function translateDayAbbr($abbr) {
    $map = [
        'Mon' => 'Lun',
        'Tue' => 'Mar',
        'Wed' => 'Mié',
        'Thu' => 'Jue',
        'Fri' => 'Vie',
        'Sat' => 'Sáb',
        'Sun' => 'Dom'
    ];
    return $map[$abbr] ?? $abbr;
}

$client = new Client();
$response = $client->request('GET', 'https://utils.sumtv.app/weekScheduleSp.json');
if ($response->getStatusCode() !== 200) {
    return "Failed to fetch data";
}
$body = $response->getBody();
$dataFetch = json_decode($body, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    return "Error decoding JSON data";
}

function processData($groupedData, $modx, $tabsTpl, $tabsContentTpl, $outerTpl, $tabsContentOuterTpl) {
    $htmlOutput = '';
    $tabsOutput = '';
    $tabsContentOuter = '';
    $tabCounter = 0;
    $contentCounter = 0;

    foreach ($groupedData as $day => $items) {
        $timestamp = strtotime($day);
        $dayAbbr = translateDayAbbr(date('D', $timestamp));
        $dayFormatted = $dayAbbr . ', ' . date('d', $timestamp); // e.g., "Lun, 08"

        // Tabs titles
        $tabsOutput .= $modx->getChunk($tabsTpl, [
            'day' => $dayFormatted,
            'id' => ($tabCounter + 1),
            'selected' => $tabCounter === 0 ? 'true' : 'false',
            'classes' => $tabCounter === 0 ? 'active' : '',
        ]);

        // Tabs content
        $tabsContent = '';
        foreach ($items as $item) {
            $tabsContent .= $modx->getChunk($tabsContentTpl, [
                'title' => $item['title'],
                'seriesId' => $item['seriesId'],
                'seriesCode' => $item['seriesCode'],
                'episodeTitle' => $item['episodeTitle'],
                'time' => $item['time'],
                'dateTime' => $item['datetime'],
                'image' => $item['image'],
                'id' => $contentCounter
            ]);
            $contentCounter++;
        }

        // Outer content tab
        $tabsContentOuter .= $modx->getChunk($tabsContentOuterTpl, [
            'day' => $dayFormatted,
            'id' => ($tabCounter + 1),
            'tabsContent' => $tabsContent,
            'classes' => $tabCounter === 0 ? 'show active' : ''
        ]);

        $tabCounter++;
    }

    $htmlOutput .= $modx->getChunk($outerTpl, [
        'tabs' => $tabsOutput,
        'tabsContent' => $tabsContentOuter
    ]);

    return $htmlOutput;
}

$htmlOutput = processData($dataFetch, $modx, $tabsTpl, $tabsContentTpl, $outerTpl, $tabsContentOuterTpl);
return $htmlOutput;