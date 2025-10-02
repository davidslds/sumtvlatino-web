id: 28
source: 1
name: getSermons
properties: 'a:0:{}'

-----

/**
 * SpeakerWorshipServices
 *
 * Params:
 *  &tpl      (string)  Chunk to render each card. Default: card-featured
 *  &lang     (string)  Locale for API (and date). Default: es
 *  &limit    (int)     Items per page. Default: 4
 *  &page     (int)     Page number (1-based). Default: from $_GET['page'] or 1
 *  &strapiId (int)     Speaker Strapi ID to filter by (preferred)
 *  &spName   (string)  Fallback: speaker fullname to filter by if no strapiId
 *
 * Example:
 *   [[!SpeakerWorshipServices? &strapiId=`39` &limit=`8` &tpl=`card-featured`]]
 */

use GuzzleHttp\Client;

$tpl      = (string) $modx->getOption('tpl',      $scriptProperties, 'card-featured');
$lang     = (string) $modx->getOption('lang',     $scriptProperties, 'es');
$limit    = (int)    $modx->getOption('limit',    $scriptProperties, 4);
$spName   = (string) $modx->getOption('spName',   $scriptProperties, ''); // fallback
$strapiId = (string) $modx->getOption('strapiId', $scriptProperties, ''); // preferred

// Allow page override via GET
$page = (int) ($modx->getOption('page', $_GET, 1));
if ($page < 1) { $page = 1; }
$offset = ($limit > 0) ? ($page - 1) * $limit : 0;

// Ensure Composer autoload (adjust path if your vendor dir is elsewhere)
if (!class_exists(Client::class)) {
    $autoload = MODX_CORE_PATH . 'vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
    } else {
        return 'Error: Guzzle not found. Install composer deps or fix vendor path.';
    }
}

$baseUrl = 'https://api.sumtv.org/api/worship-services';

try {
    $client = new Client([
        'timeout' => 10,
        'http_errors' => false,
    ]);

    // Build Strapi query
    $query = [
        'pagination[limit]' => $limit,
        'pagination[start]' => $offset,
        'sort'              => 'air_date:desc',
        'locale'            => $lang,
        'populate'          => '*',
    ];

    // Prefer filtering by speaker ID if provided
    if ($strapiId !== '') {
        $query['filters[sermon_speakers][id][$eq]'] = (int)$strapiId;
    } elseif ($spName !== '') {
        // Fallback: full name contains (case-insensitive)
        // Note: This assumes the relation has "fullname" attribute exposed
        $query['filters[sermon_speakers][fullname][$containsi]'] = $spName;
    }

    $response = $client->request('GET', $baseUrl, ['query' => $query]);
    $status   = $response->getStatusCode();
    if ($status !== 200) {
        return 'Error: API returned HTTP ' . $status;
    }

    $payload = json_decode((string)$response->getBody(), true);
    if (!is_array($payload) || empty($payload['data']) || !is_array($payload['data'])) {
        return ''; // nothing to render
    }

    // Date formatter (nice Spanish format if intl exists)
    $formatDate = function (?string $iso) use ($lang) {
        if (!$iso) return '';
        try {
            $dt = new DateTime($iso);
        } catch (Exception $e) {
            return '';
        }
        if (class_exists(IntlDateFormatter::class)) {
            // es_ES (or lang-based) locale; MEDIUM is a nice default
            $loc = ($lang === 'es') ? 'es_ES' : $lang;
            $fmt = new IntlDateFormatter($loc, IntlDateFormatter::MEDIUM, IntlDateFormatter::NONE);
            return $fmt->format($dt);
        }
        // Fallback: YYYY-MM-DD
        return $dt->format('Y-m-d');
    };

    $html = '';
    $count = 0;

    foreach ($payload['data'] as $row) {
        if ($limit > 0 && $count >= $limit) break;

        $attr = $row['attributes'] ?? [];

        // Title (try ss_lesson, then sermon_title, then blank)
        $title = $attr['ss_lesson'] ?? $attr['sermon_title'] ?? '';

        // URL – adjust to your site’s route
        $url = 'https://sumtv.app/worship-service/' . (int)($row['id'] ?? 0);

        // Speakers (list of fullnames)
        $speakerData = $attr['sermon_speakers']['data'] ?? [];
        $speakerNames = [];
        $speakerTitle = '';
        if (is_array($speakerData)) {
            foreach ($speakerData as $sp) {
                $fn = $sp['attributes']['fullname'] ?? '';
                if ($fn !== '') $speakerNames[] = $fn;
            }
            // Optional: primary speaker title (first speaker)
            if (!empty($speakerData[0]['attributes']['title'])) {
                $speakerTitle = $speakerData[0]['attributes']['title'];
            }
        }
        $speakers = implode(', ', $speakerNames);

        // Image (medium -> thumbnail -> blank)
        $imageUrl = '';
        if (!empty($attr['video_thumbnail']['data']['attributes']['formats']['medium']['url'])) {
            $imageUrl = $attr['video_thumbnail']['data']['attributes']['formats']['medium']['url'];
        } elseif (!empty($attr['video_thumbnail']['data']['attributes']['url'])) {
            $imageUrl = $attr['video_thumbnail']['data']['attributes']['url'];
        }

        $placeholders = [
            'title'           => htmlspecialchars((string)$title, ENT_QUOTES, 'UTF-8'),
            'url'             => htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
            'ep_number'       => htmlspecialchars((string)($attr['ep_number'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'air_date'      => htmlspecialchars($formatDate($attr['air_date'] ?? null), ENT_QUOTES, 'UTF-8'),
            'thumb_url'       => htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8'),
            'description'     => htmlspecialchars((string)($attr['description'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'sermon_speakers' => htmlspecialchars($speakers, ENT_QUOTES, 'UTF-8'),
            'speaker_title'   => htmlspecialchars($speakerTitle, ENT_QUOTES, 'UTF-8'),
        ];

        $html .= $modx->getChunk($tpl, $placeholders);
        $count++;
    }

    return $html;

} catch (Throwable $e) {
    return 'Error: ' . $e->getMessage();
}