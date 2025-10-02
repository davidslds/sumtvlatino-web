id: 39
source: 1
name: getWhatsOn
properties: 'a:0:{}'

-----

use GuzzleHttp\Client;

// Retrieve options
$tpl   = $modx->getOption('tpl', $scriptProperties, 'tpl');
$lang  = $modx->getOption('lang', $scriptProperties, 'en');
$limit = (int) $modx->getOption('limit', $scriptProperties, 0); // Ensure it's an integer
$page  = (int) $modx->getOption('page', $_GET, 1);

$baseUrl = 'https://utils.sumtv.app/onNowSp.json';

try {
    $client = new Client();
    $response = $client->request('GET', $baseUrl);
    $data = json_decode($response->getBody(), true);

    if (!is_array($data) || empty($data)) {
        return 'No data available';
    }

    // Default to Los Angeles timezone (PST/PDT)
    $defaultTimezone = new DateTimeZone('America/Los_Angeles');

    // Handling the limit conditions
    if ($limit === 1) {
        $data = array_slice($data, 0, 1);
    } elseif ($limit > 1) {
        $data = array_slice($data, 1, $limit);
    }

    $htmlOutput = '';
    $counter = 0;

    foreach ($data as $item) {
        if ($limit > 0 && $counter >= $limit) {
            break;
        }

        $dateString = $item['air_start'];  // Example: "2025-02-02T16:00:00-08:00"
        $dateStringEnd = $item['air_stop']; // Example: "2025-02-02T17:00:00-08:00"

        // Convert to Los Angeles time (for fallback)
        $startTimeObj = new DateTime($dateString);
        $startTimeObj->setTimezone($defaultTimezone);
        $fallbackStartTime = $startTimeObj->format('g:i A'); // e.g., "4:00 PM"

        $endTimeObj = new DateTime($dateStringEnd);
        $endTimeObj->setTimezone($defaultTimezone);
        $fallbackEndTime = $endTimeObj->format('g:i A'); // e.g., "5:00 PM"

        // Handle image retrieval
        $imageUrl = 'https://sumtv-app.s3.us-west-1.amazonaws.com/sumtvlatino/' . 
            htmlspecialchars($item['version']['series']['series_id']) . '-' . 
            htmlspecialchars($item['version']['series']['series_code']) . '-cover.webp';

        $image = @getimagesize($imageUrl) ? $imageUrl : 'https://sumtv-app.s3.us-west-1.amazonaws.com/sumtv/sumtvHD.webp';

        // Prepare placeholders for the chunk
        $chunkPlaceholders = [
            'series_title'   => htmlspecialchars($item['version']['series']['series_title']),
            'url'            => htmlspecialchars($item['id']),
            'ep_number'      => htmlspecialchars($item['version']['version_number']),
            'program_title'  => htmlspecialchars($item['version']['program_title']),
            'air_date'       => $dateString,  // Send UTC for JS conversion
            'end_time'       => $dateStringEnd,  // Send UTC for JS conversion
            'fallback_air'   => $fallbackStartTime, // Los Angeles fallback
            'fallback_end'   => $fallbackEndTime, // Los Angeles fallback
            'thumbnail_url'  => $image,
            'description'    => htmlspecialchars($item['version']['version_desc'])
        ];

        $htmlOutput .= $modx->getChunk($tpl, $chunkPlaceholders);
        $counter++;
    }

    // Include JS for client-side timezone conversion
    $htmlOutput .= '
    <script>
        function convertToClientTime() {
            document.querySelectorAll(".convert-time").forEach(el => {
                let utcTime = el.getAttribute("data-utc");
                if (!utcTime) return;

                try {
                    let localDate = new Date(utcTime);
                    let formattedTime = localDate.toLocaleTimeString([], { hour: "numeric", minute: "2-digit", hour12: true });

                    el.innerText = formattedTime;
                } catch (error) {
                    console.warn("Failed to convert time, using default Los Angeles time.");
                }
            });
        }
        document.addEventListener("DOMContentLoaded", convertToClientTime);
    </script>';

    return $htmlOutput;

} catch (Exception $e) {
    $modx->log(modX::LOG_LEVEL_ERROR, 'Error in onNowEn snippet: ' . $e->getMessage());
    return 'Error: ' . $e->getMessage();
}