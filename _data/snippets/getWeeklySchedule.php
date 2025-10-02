id: 31
source: 1
name: getWeeklySchedule
properties: 'a:0:{}'

-----

use GuzzleHttp\Client;

date_default_timezone_set('America/Los_Angeles');

// Function to fetch JSON data via Guzzle
function fetchJSONData($url) {
    $client = new Client();
    try {
        $response = $client->request('GET', $url);
        if ($response->getStatusCode() !== 200) {
            return "Error: Failed to fetch data (HTTP " . $response->getStatusCode() . ")";
        }
        return json_decode($response->getBody(), true);
    } catch (Exception $e) {
        return "Error: " . $e->getMessage();
    }
}

// Spanish translation for weekdays
function translateToSpanishDay($englishDay) {
    $days = [
        'Sunday' => 'Domingo',
        'Monday' => 'Lunes',
        'Tuesday' => 'Martes',
        'Wednesday' => 'Miércoles',
        'Thursday' => 'Jueves',
        'Friday' => 'Viernes',
        'Saturday' => 'Sábado',
    ];
    return $days[$englishDay] ?? $englishDay;
}

// Function to generate the schedule table using MODX chunks
function generateScheduleTable($modx, $data, $tplTable, $tplHeader, $tplRow, $tplColumn) {
    if (!$data || !is_array($data)) {
        return "Error: Invalid schedule data.";
    }

    $schedule = [];
    $allTimes = [];

    // Process JSON data
    foreach ($data as $date => $programs) {
        $dayEnglish = date('l', strtotime($date)); // Weekday in English
        $daySpanish = translateToSpanishDay($dayEnglish);

        if (!isset($schedule[$daySpanish])) {
            $schedule[$daySpanish] = [];
        }

        foreach ($programs as $program) {
            $time = $program['time'] ?? '00:00';
            $schedule[$daySpanish][$time] = [
                'title' => $program['title'] ?? 'Programa desconocido',
                'link' => $program['link'] ?? '#'
            ];
            $allTimes[$time] = true;
        }
    }

    // Sort times in order
    ksort($allTimes);
    $timeSlots = array_keys($allTimes);

    // Extract available Spanish days
    $availableDays = array_keys($schedule);

    // Generate table header
    $headerOutput = '';
    foreach ($availableDays as $day) {
        $headerOutput .= $modx->getChunk($tplHeader, ['day' => $day]);
    }

    // Generate rows
    $rowsOutput = '';
    foreach ($timeSlots as $time) {
        $columns = '';
        foreach ($availableDays as $day) {
            $program = $schedule[$day][$time] ?? ['title' => '', 'link' => ''];
            $columns .= $modx->getChunk($tplColumn, [
                'title' => $program['title'],
                'link' => $program['link']
            ]);
        }
        $rowsOutput .= $modx->getChunk($tplRow, [
            'time' => $time,
            'columns' => $columns
        ]);
    }

    return $modx->getChunk($tplTable, [
        'tableHeader' => $headerOutput,
        'tableRows' => $rowsOutput
    ]);
}

// URL to fetch JSON data
$jsonUrl = 'https://utils.sumtv.app/weekScheduleSp.json';

// Fetch data
$data = fetchJSONData($jsonUrl);

// Generate and return schedule table
return generateScheduleTable($modx, $data, 'tplTable', 'tplHeader', 'tplRow', 'tplColumn');