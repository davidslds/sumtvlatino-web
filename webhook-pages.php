<?php
/**
 * Strapi → MODX 3.1.2 Webhook
 * - Processes ONLY Spanish entries (locale === 'es')
 * - Supports events: entry.create, entry.update, entry.publish, entry.unpublish, entry.delete
 * - Matches resources by TV `strapiId`
 * - Uses navigation→parent mapping
 * - Maps fields: pagetitle, longtitle, introtext, alias, content (built from sections)
 * - Saves sections JSON to TV `sectionsJson` and locale to TV `locale`
 *
 * Requirements:
 *   TVs: strapiId (text), sectionsJson (textarea) [optional], locale (text) [optional]
 *   Template: use default_template or set $templateId
 */

use MODX\Revolution\modX;
use MODX\Revolution\modResource;
use MODX\Revolution\modSnippet;
use MODX\Revolution\modChunk;
use MODX\Revolution\modTemplateVar;
use MODX\Revolution\modTemplateVarResource;

require_once __DIR__ . '/config.core.php';
require_once MODX_CORE_PATH . 'vendor/autoload.php';

$modx = new modX();
$modx->initialize('web');

// ───────────────────────────────────────────────────────────────────────────────
// (Optional) Bearer token check – set in Strapi webhook header if you want
// ───────────────────────────────────────────────────────────────────────────────
$expectedToken = 'fwj9uoNNRr.gXy4m!9nX'; // e.g. 'MY_SUPER_SECRET' and send header Authorization: Bearer MY_SUPER_SECRET
$headerToken = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if ($expectedToken && $headerToken !== "Bearer $expectedToken") {
    http_response_code(403);
    exit('Forbidden');
}

// ───────────────────────────────────────────────────────────────────────────────
// Input validation
// ───────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
    file_put_contents('webhook_debug_log.txt', "Request blocked: Only POST is allowed.\n", FILE_APPEND);
}
$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    http_response_code(400);
    exit('Invalid JSON');
}
file_put_contents(
    __DIR__ . '/webhook_debug_log_array.txt',
    "Received Data:\n" . print_r($payload, true) . "\n\n",
    FILE_APPEND
);
$event = strtolower($payload['event'] ?? '');
$model = strtolower($payload['model'] ?? '');
$entry = $payload['entry'] ?? null;

if (!$event || !$model || !$entry || !isset($entry['id'])) {
    http_response_code(400);
    exit('Missing event/model/entry/id');

}
file_put_contents('webhook_debug_log.txt', "=== START " . date('c') . " ===\n", FILE_APPEND);
file_put_contents('webhook_debug_log.txt', "Received Data:\n" . json_encode($payload, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);

// ───────────────────────────────────────────────────────────────────────────────
// Process ONLY Spanish entries
// ───────────────────────────────────────────────────────────────────────────────
if (!empty($entry['locale']) && strtolower($entry['locale']) !== 'es') {
    $modx->log(xPDO::LOG_LEVEL_INFO, "[Webhook] Skipping entry {$entry['id']} (locale={$entry['locale']})");
    http_response_code(200);
    echo json_encode(['status' => 'skipped', 'reason' => 'non-spanish', 'locale' => $entry['locale']]);
    file_put_contents('webhook_debug_log.txt', "skipped, non-spanish.\n", FILE_APPEND);
    exit;
}

// ───────────────────────────────────────────────────────────────────────────────
$entryId = $entry['id'];

// Parent mapping you gave
$parentMap = [
    'about' => 2,
    'events' => 3,
    'sumtv' => 4,
    'resources' => 5,
    'speakers' => 7,
];
$parentId = 0;
if (!empty($entry['navigation'])) {
    $navKey = strtolower($entry['navigation']);
    if (isset($parentMap[$navKey])) $parentId = (int)$parentMap[$navKey];
}
$templateMap = [
    'baseTemplate' => 1,
    'baseTemplateTitle' => 5,
    'events' => 1,
    'eventsDetail' => 5,
    'resources' => 1,
    'resourcesDetail' => 5,
];
// Template
$defaultTemplateId = 5;  // your current default/fallback
$templateId = $defaultTemplateId;
if (!empty($entry['template'])) {
    $navKey = $entry['template'];
    if (isset($templateMap[$navKey])) $templateId = (int)$templateMap[$navKey];
}

file_put_contents('webhook_debug_log.txt', "template id: " . $templateId . "\n", FILE_APPEND);
// TVs
$tvStrapiId = 2;
$tvStrapiModel = 7;
$tvSections = 3;
$tvLocale = null;


// ───────────────────────────────────────────────────────────────────────────────
// Model routing – handle "page" (you can add cases for "series", "study-notes", etc.)
// ───────────────────────────────────────────────────────────────────────────────
switch ($model) {
    case 'page':
        handlePage($modx,
            $event,
            $entry,
            $entryId,
            $parentId,
            $templateId,
            2,
            $tvSections,
            7);
        break;
    case 'speaker':
        handleSpeaker(
            $modx,
            $event,
            $entry,
            $entryId,
            7,
            9,
            2,
            $tvSections,
            $tvStrapiModel
        );
        break;
    case 'serie':

        $strapiApiUrl = 'https://api.sumtv.org/api/series/'.$entryId.'?populate=*';

        $response = file_get_contents($strapiApiUrl);
        if (!$response) {
            die("Error fetching data from Strapi.");
        }
        $data = json_decode($response, true);
        file_put_contents('webhook_debug_log.txt', "Received Data api request:\n" . json_encode($data, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
        handleSerie(
            $modx,
            $event,
            $data['data']['attributes'],
            $entryId,
            8,
            10,
            2,
            11,
            12,
            6,
            $tvStrapiModel
        );
        break;
    case 'event':   // 👈 new case

        handleEvent(
            $modx,
            $event,
            $entry,
            $entryId,
            3,   // parent container for Events (adjust to your tree)
            8,  // template ID for Events (adjust to your setup)
            $tvStrapiId,
            $tvStrapiModel,
            $tvSections
        );
        break;
    case 'newsletter':
        $strapiApiUrl = 'https://api.sumtv.org/api/newsletters/'.$entryId.'?populate=*';

        $response = file_get_contents($strapiApiUrl);
        if (!$response) {
            die("Error fetching data from Strapi.");
        }
        $data = json_decode($response, true);

        handleNewsletter(
            $modx,
            $event,
            $data['data']['attributes'],
            $entryId,
            157,   // 📁 Parent container ID for newsletters (adjust!)
            10,  // 🧩 Template ID for newsletters (adjust!)
            $tvStrapiId,
            $tvStrapiModel
        );
        break;
    case 'study-note':
        // Strapi API URL (Fetch ALL newsletters)
        $strapiApiUrl = 'https://api.sumtv.org/api/study-notes/'.$entryId.'?populate=*';

// Fetch newsletters from Strapi
        $response = file_get_contents($strapiApiUrl);
        if (!$response) {
            die("Error fetching data from Strapi.");
        }
        $data = json_decode($response, true);
        handleStudyNote(
            $modx,
            $event,
            $data['data']['attributes'],
            $entryId,
            156,  // 📁 parent container for Study Notes
            7,  // 🧩 template ID for Study Notes
            $tvStrapiId,
            $tvStrapiModel
        );
        break;
    default:
        // Unknown/unsupported model – ignore cleanly
        http_response_code(200);
        echo json_encode(['status' => 'ignored', 'model' => $model]);
        file_put_contents('webhook_debug_log.txt', "ignored. Model is:" . $model . "\n", FILE_APPEND);
        break;
}

exit;

// ───────────────────────────────────────────────────────────────────────────────
// Helpers
// ───────────────────────────────────────────────────────────────────────────────

//function getTvId(modX $modx, string $name): ?int {
//    $tv = $modx->getObject(modTemplateVar::class, ['name' => $name]);
//    return $tv ? (int)$tv->get('id') : null;
//}

//function findResourceByStrapiId(modX $modx, int $tvStrapiId, $strapiId): ?modResource {
//    $c = $modx->newQuery(modResource::class);
//    $c->leftJoin(modTemplateVarResource::class, 'TV', "TV.tmplvarid = {$tvStrapiId} AND TV.contentid = modResource.id");
//    $c->where(['TV.value' => (string)$strapiId]);
//    return $modx->getObject(modResource::class, $c);
//}
function findResourceByStrapiPair(modX $modx, int $tvModelId, int $tvIdId, string $model, string $entryId)
{
    $entryId = (string) $entryId;

    $c = $modx->newQuery(modResource::class);
    $c->leftJoin(modTemplateVarResource::class, 'TV_MODEL', "TV_MODEL.tmplvarid = {$tvModelId} AND TV_MODEL.contentid = modResource.id");
    $c->leftJoin(modTemplateVarResource::class, 'TV_ID', "TV_ID.tmplvarid = {$tvIdId} AND TV_ID.contentid = modResource.id");

    $c->where([
        'TV_MODEL.value' => $model,
        'TV_ID.value'    => $entryId,
        'modResource.deleted' => false,
    ]);

    $c->prepare();
    $sql = $c->toSQL();
    file_put_contents('webhook_debug_log.txt', "SQL: {$sql}\n", FILE_APPEND);

    $res = $modx->getObject(modResource::class, $c);

    file_put_contents('webhook_debug_log.txt', "Resource found? " . ($res ? "YES\n" : "NO\n"), FILE_APPEND);

    return $res;
}
function extractTitle(array $e): string
{
    return $e['pagetitle'] ?? $e['title'] ?? $e['name'] ?? ("Entry " . $e['id']);
}

function extractAlias(array $e): string
{
    if (!empty($e['slug'])) return $e['slug'];
    $base = $e['pagetitle'] ?? $e['title'] ?? $e['name'] ?? (string)$e['id'];
    $a = preg_replace('/[^a-z0-9\-]+/i', '-', strtolower($base));
    $a = trim($a, '-');
    return $a ?: (string)$e['id'];
}

function extractLongTitle(array $e): ?string
{
    return $e['long_title'] ?? $e['longtitle'] ?? null;
}

function extractIntro(array $e): ?string{
    return $e['description'] ?? $e['introtext'] ?? $e['summary'] ?? null;
}

function extractLocaleVal(array $e): ?string{
    return $e['locale'] ?? null;
}

function extractIsPublished(array $e): bool{
    return !empty($e['publishedAt']) || !empty($e['published_at']);
}

function extractPublishedOn(array $e): int{
    $iso = $e['publishedAt'] ?? $e['published_at'] ?? null;
    return $iso ? (strtotime($iso) ?: time()) : time();
}

function extractCont(array $data): string {
    if (array_key_exists('sections', $data)) {
        unset($data['sections']);
    }
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function buildContentFromSections(array $e): string
{
    $parts = [];
    if (!empty($e['sections']) && is_array($e['sections'])) {
        foreach ($e['sections'] as $block) {
            $type = $block['__component'] ?? '';
            if ($type === 'elements.rich-text' && !empty($block['content'])) {
                $parts[] = $block['content']; // already HTML
            }
            // extend with other element types if desired
        }
    }
    return implode("\n\n", $parts);
}

/**
 * Return a copy of $entry with heavy relations removed
 * so we can store a compact JSON in resource content.
 */
function pruneEntryForContent(array $entry, array $heavyKeys = []): array
{
    // Default heavy relations to strip
    if (!$heavyKeys) {
        $heavyKeys = [
            'iltks',
            'series',
            'study_notes',
            'ss',
            'worship_services',
            'events',
            'sabbath_schools',
            'articles',
        ];
    }

    $clean = $entry;

    // Unset top-level heavy arrays
    foreach ($heavyKeys as $key) {
        if (array_key_exists($key, $clean)) {
            unset($clean[$key]);
        }
    }

    // (Optional) Trim image payload size by removing nested format renditions
    if (!empty($clean['picture']) && is_array($clean['picture'])) {
        if (!empty($clean['picture']['formats'])) {
            // Keep only original url + thumbnail to keep things light
            $thumb = $clean['picture']['formats']['thumbnail'] ?? null;
            $clean['picture']['formats'] = [];
            if ($thumb) {
                $clean['picture']['formats']['thumbnail'] = $thumb;
            }
        }
    }
    if (!empty($clean['picture_transparent']) && is_array($clean['picture_transparent'])) {
        if (!empty($clean['picture_transparent']['formats'])) {
            $thumb = $clean['picture_transparent']['formats']['thumbnail'] ?? null;
            $clean['picture_transparent']['formats'] = [];
            if ($thumb) {
                $clean['picture_transparent']['formats']['thumbnail'] = $thumb;
            }
        }
    }

    return $clean;
}

function handlePage(
    modX   $modx,
    string $event,
    array  $entry,
    int    $entryId,
    int    $parentId,
    int    $templateId,
    int    $tvStrapiId,
    int     $tvStrapiModel,
    ?int   $tvSections,
){
    $event = strtolower($event);
    $res = findResourceByStrapiPair($modx, 7, 2, 'page', $entryId);

    // Delete
    if ($event === 'entry.delete') {
        if ($res) {
            $id = $res->get('id');
            $res->remove();
        }
        http_response_code(200);
        echo json_encode(['status' => 'ok', 'action' => 'deleted', 'id' => $entryId]);
        return;
    }

    // Gather mapped fields
    $pagetitle = extractTitle($entry);
    $alias = extractAlias($entry);
    $longtitle = extractLongTitle($entry);
    $introtext = extractIntro($entry);
    $localeVal = extractLocaleVal($entry);
    $isPublished = extractIsPublished($entry);
    $publishedOn = extractPublishedOn($entry);
    $content = buildContentFromSections($entry);


// Determine context_key (use parent's context if parentId > 0; else 'web')
    $contextKey = 'web';
    if ($parentId > 0) {
        $parentObj = $modx->getObject(modResource::class, $parentId);
        if ($parentObj) {
            $contextKey = $parentObj->get('context_key') ?: 'web';
        }
    }

// Ensure resource exists on create/update/publish/unpublish
    if (!$res && in_array($event, ['entry.create', 'entry.update', 'entry.publish', 'entry.unpublish'], true)) {
        $res = $modx->newObject(modResource::class);
        $res->fromArray([
            'pagetitle' => $pagetitle,
            'alias' => $alias,          // ensure this isn't conflicting under the same parent
            'parent' => $parentId,
            'context_key' => $contextKey,     // IMPORTANT
            'template' => $templateId,
            'createdon' => time(),
            'createdby' => 0,
            'published' => $isPublished ? 1 : 0,
            'publishedon' => $isPublished ? $publishedOn : 0,
            'publishedby' => $isPublished ? 0 : 0,
            'richtext' =>  0,
            'searchable' => (int)$modx->getOption('search_default', null, 1),
            'hidemenu' => (int)$modx->getOption('hidemenu_default', null, 0),
            'class_key' => 'MODX\\Revolution\\modDocument', // explicit for MODX 3
        ]);

        if (!$res->save()) {
            // Log common reasons
            $msg = "[Webhook] Create FAILED: "
                . "ptitle='{$pagetitle}', alias='{$alias}', parent={$parentId}, template={$templateId}, ctx={$contextKey}";
            file_put_contents(__DIR__ . '/webhook_debug_log.txt', $msg . "\n", FILE_APPEND);

            // Try removing alias (let MODX generate one) to avoid duplicate-alias failures
            $res->set('alias', '');
            if (!$res->save()) {
                http_response_code(500);
                exit('Failed to create resource (alias retry also failed). Check template/parent/context and friendly URL settings.');
            }
        }

        // store strapiId TV (ID=2)
        $res->setTVValue('strapiModel', 'page');
        $res->setTVValue('strapiId', (string)$entryId);
    }

    if (!$res) {
        http_response_code(200);
        echo json_encode(['status' => 'ignored']);
        return;
    }

    // Update fields per event
    $fields = [
        'pagetitle' => $pagetitle,
        'alias' => $alias,
        'parent' => $parentId,
        'editedon' => time(),
        'editedby' => 0,
    ];
    if ($longtitle !== null) $fields['longtitle'] = $longtitle;
    if ($introtext !== null) $fields['introtext'] = $introtext;
//    if ($content !== '') $fields['content'] = $content;

    if ($event === 'entry.publish') {
        $fields['published'] = 1;
        $fields['publishedon'] = $publishedOn;
        $fields['publishedby'] = 0;
    } elseif ($event === 'entry.unpublish') {
        $fields['published'] = 0;
        $fields['publishedon'] = 0;
        $fields['publishedby'] = 0;
    } else {
        // entry.create / entry.update — mirror Strapi state
        $fields['published'] = $isPublished ? 1 : 0;
        $fields['publishedon'] = $isPublished ? $publishedOn : 0;
        $fields['publishedby'] = $isPublished ? 0 : 0;
    }
    // 🔁 Update template if changed
    if ($templateId && (int)$res->get('template') !== (int)$templateId) {
        $res->set('template', (int)$templateId);
    }

    $res->fromArray($fields);
    if (!$res->save()) {
        http_response_code(500);
        exit('Failed to save resource');
    }

// Save sections JSON to TV ID=3
    $res->setTVValue('sectionsJson', json_encode($entry['sections'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    // Refresh cache for this resource
    $modx->cacheManager->refresh(['resource' => [$res->get('id') => []]]);


    http_response_code(200);
    echo json_encode(['status' => 'ok', 'action' => $event, 'id' => $entryId, 'resourceId' => $res->get('id')]);
}

// ───────────────────────────────────────────────────────────────────────────────
// SPEAKER (SUMtv_speaker only, content = pruned JSON entry)
// Replace your existing handleSpeaker() with this
// ───────────────────────────────────────────────────────────────────────────────
function handleSpeaker(
    modX   $modx,
    string $event,
    array  $entry,
    int    $entryId,
    int    $parentId,     // pass 7 in switch
    int    $templateId,   // pass 9 in switch
    int    $tvStrapiId,
    int    $tvStrapiModel,
    ?int   $tvSections
){
    $event = strtolower($event);

    // Only run for SUMtv speakers
    $sumtvFlag = $entry['SUMtv_speaker'] ?? $entry['SUMtv_Speaker'] ?? null;

    $isTrueLike = function ($v): bool {
        if (is_bool($v)) return $v === true;
        if (is_int($v)) return $v === 1;
        if (is_string($v)) return in_array(strtolower(trim($v)), ['1', 'true', 'yes', 'on'], true);
        return false;
    };

    if (!$isTrueLike($sumtvFlag)) {
        http_response_code(200);
        echo json_encode([
            'status' => 'skipped',
            'reason' => 'SUMtv_speaker not true',
            'id' => $entryId,
            'model' => 'speaker'
        ]);
        return;
    }

    $res = findResourceByStrapiPair($modx, $tvStrapiModel, $tvStrapiId, 'speaker', $entryId);

    // Delete
    if ($event === 'entry.delete') {
        if ($res) {
            $res->remove();
        }
        http_response_code(200);
        echo json_encode(['status' => 'ok', 'action' => 'deleted', 'id' => $entryId, 'model' => 'speaker']);
        return;
    }

    // Field mapping
    $pagetitle = $entry['fullname'] ?? (($entry['name'] ?? '') . ' ' . ($entry['lastname'] ?? '')) ?: ('Speaker ' . $entryId);
    $alias = !empty($entry['slug']) ? $entry['slug'] : extractAlias(['pagetitle' => $pagetitle]);
    $introtext = $entry['short_bio'] ?? null;

    // Publish state mirrors Strapi
    $isPublished = extractIsPublished($entry);
    $publishedOn = extractPublishedOn($entry);

    // Content = pruned JSON
    $pruned = pruneEntryForContent($entry);
    $contentJson = json_encode($pruned, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // Determine context_key
    $contextKey = 'web';
    if ($parentId > 0) {
        $parentObj = $modx->getObject(modResource::class, $parentId);
        if ($parentObj) $contextKey = $parentObj->get('context_key') ?: 'web';
    }

    // Create if missing
    if (!$res && in_array($event, ['entry.create', 'entry.update', 'entry.publish', 'entry.unpublish'], true)) {
        $res = $modx->newObject(modResource::class);
        $res->fromArray([
            'pagetitle' => $pagetitle,
            'alias' => $alias,
            'parent' => $parentId,
            'context_key' => $contextKey,
            'template' => $templateId,
            'createdon' => time(),
            'createdby' => 0,
            'published' => $isPublished ? 1 : 0,
            'publishedon' => $isPublished ? $publishedOn : 0,
            'publishedby' => $isPublished ? 0 : 0,
            'richtext' =>  0,
            'searchable' => (int)$modx->getOption('search_default', null, 1),
            'hidemenu' => (int)$modx->getOption('hidemenu_default', null, 0),
            'class_key' => 'MODX\\Revolution\\modDocument',
            'content' => $contentJson,
            'introtext' => $introtext ?? '',
        ]);

        if (!$res->save()) {
            $msg = "[Webhook] Speaker Create FAILED: ptitle='{$pagetitle}', alias='{$alias}', parent={$parentId}, template={$templateId}, ctx={$contextKey}";
            file_put_contents(__DIR__ . '/webhook_debug_log.txt', $msg . "\n", FILE_APPEND);

            // Retry without alias (duplicate alias safeguard)
            $res->set('alias', '');
            if (!$res->save()) {
                http_response_code(500);
                exit('Failed to create speaker resource (alias retry failed).');
            }
        }
        // set strapiId TV
        $res->setTVValue('strapiModel', 'speaker');
        $res->setTVValue('strapiId', (string)$entryId);
    }

    if (!$res) {
        http_response_code(200);
        echo json_encode(['status' => 'ignored', 'reason' => 'no-resource', 'id' => $entryId, 'model' => 'speaker']);
        return;
    }

    // Update fields
    $fields = [
        'pagetitle' => $pagetitle,
        'alias' => $alias,
        'parent' => $parentId,
        'content' => $contentJson,
        'editedon' => time(),
        'editedby' => 0,
    ];
    if ($introtext !== null) $fields['introtext'] = $introtext;

    if ($event === 'entry.publish') {
        $fields['published'] = 1;
        $fields['publishedon'] = $publishedOn;
        $fields['publishedby'] = 0;
    } elseif ($event === 'entry.unpublish') {
        $fields['published'] = 0;
        $fields['publishedon'] = 0;
        $fields['publishedby'] = 0;
    } else {
        $fields['published'] = $isPublished ? 1 : 0;
        $fields['publishedon'] = $isPublished ? $publishedOn : 0;
        $fields['publishedby'] = $isPublished ? 0 : 0;
    }

    // Update template if changed
    if ($templateId && (int)$res->get('template') !== (int)$templateId) {
        $res->set('template', (int)$templateId);
    }

    $res->fromArray($fields);
    if (!$res->save()) {
        http_response_code(500);
        exit('Failed to save speaker resource');
    }

    // Optionally mirror pruned JSON into sectionsJson TV for debugging/search:
    // if ($tvSections) { $res->setTVValue('sectionsJson', $contentJson); }
    $res->setTVValue('sectionsJson', json_encode($pruned ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    // Refresh cache
    $modx->cacheManager->refresh(['resource' => [$res->get('id') => []]]);

    http_response_code(200);
    echo json_encode([
        'status' => 'ok',
        'action' => $event,
        'id' => $entryId,
        'resourceId' => $res->get('id'),
        'model' => 'speaker'
    ]);
}

function handleSerie(
    modX   $modx,
    string $event,
    array  $entry,
    int    $entryId,
    int    $parentId,
    int    $templateId,
    int    $tvStrapiId,
    int    $tvCover,
    int    $tvPoster,
    int    $tvCategory,
    int     $tvStrapiModel
){
    $event = strtolower($event);

    $res = findResourceByStrapiPair($modx, 7, 2, 'serie', $entryId);

    // Delete
    if ($event === 'entry.delete') {
        if ($res) {
            $res->remove();
        }
        http_response_code(200);
        echo json_encode(['status' => 'ok', 'action' => 'deleted', 'id' => $entryId, 'model' => 'serie']);
        return;
    }

    // Map fields
    $pagetitle = $entry['serie_title'] ?? ("Serie " . $entryId);
    $alias = !empty($entry['slug']) ? $entry['slug'] : extractAlias(['pagetitle' => $pagetitle]);
    $longtitle = $entry['serie_code'] ?? null;
    $introtext = $entry['serie_desc'] ?? null;
    $isPublished = extractIsPublished($entry);
    $publishedOn = extractPublishedOn($entry);

    // JSON for content (save whole entry)
    $contentJson = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // Extract categories (flatten names or slugs)
    $categories = [];
    if (!empty($entry['categories']['data']) && is_array($entry['categories']['data'])) {
        foreach ($entry['categories']['data'] as $cat) {
            if (!empty($cat['attributes']['category_name'])) {
                $categories[] = $cat['attributes']['category_name'];
            }
        }
    }
    $categoriesStr = implode(',', $categories);

    // Determine context_key
    $contextKey = 'web';
    if ($parentId > 0) {
        $parentObj = $modx->getObject(modResource::class, $parentId);
        if ($parentObj) $contextKey = $parentObj->get('context_key') ?: 'web';
    }

    // Create new resource if missing
    if (!$res && in_array($event, ['entry.create', 'entry.update', 'entry.publish', 'entry.unpublish'], true)) {
        $res = $modx->newObject(modResource::class);
        $res->fromArray([
            'pagetitle' => $pagetitle,
            'alias' => $alias,
            'parent' => $parentId,
            'context_key' => $contextKey,
            'template' => $templateId,
            'createdon' => time(),
            'createdby' => 0,
            'published' => $isPublished ? 1 : 0,
            'publishedon' => $isPublished ? $publishedOn : 0,
            'publishedby' => $isPublished ? 0 : 0,
            'class_key' => 'MODX\\Revolution\\modDocument',
            'content' => $contentJson,
            'introtext' => $introtext ?? '',
            'longtitle' => $longtitle ?? '',
            'richtext' =>  0,
            'searchable' => (int)$modx->getOption('search_default', null, 1),
            'hidemenu' => (int)$modx->getOption('hidemenu_default', null, 0),
            'show_in_tree' => 0,
        ]);

        if (!$res->save()) {
            http_response_code(500);
            exit('Failed to create serie resource.');
        }
        $res->setTVValue('strapiModel', 'serie');
        $res->setTVValue('strapiId', (string)$entryId);
    }

    if (!$res) {
        http_response_code(200);
        echo json_encode(['status' => 'ignored', 'reason' => 'no-resource', 'id' => $entryId, 'model' => 'serie']);
        return;
    }

    // Update fields
    $fields = [
        'pagetitle' => $pagetitle,
        'alias' => $alias,
        'content' => $contentJson,
        'editedon' => time(),
        'editedby' => 0,
    ];
    if ($longtitle !== null) $fields['longtitle'] = $longtitle;
    if ($introtext !== null) $fields['introtext'] = $introtext;

    if ($event === 'entry.publish') {
        $fields['published'] = 1;
        $fields['publishedon'] = $publishedOn;
        $fields['publishedby'] = 0;
    } elseif ($event === 'entry.unpublish') {
        $fields['published'] = 0;
        $fields['publishedon'] = 0;
        $fields['publishedby'] = 0;
    } else {
        $fields['published'] = $isPublished ? 1 : 0;
        $fields['publishedon'] = $isPublished ? $publishedOn : 0;
        $fields['publishedby'] = $isPublished ? 0 : 0;
    }

    $res->fromArray($fields);
    if (!$res->save()) {
        http_response_code(500);
        exit('Failed to save serie resource');
    }

    // Save cover & poster TVs
    if (!empty($entry['cover']['data']['attributes']['url'])) {
        $res->setTVValue('cover', $entry['cover']['data']['attributes']['url']);
    } else {
        $res->setTVValue('cover', '');
    }
    if (!empty($entry['poster']['data']['attributes']['url'])) {
        $res->setTVValue('poster', $entry['poster']['data']['attributes']['url']);
    } else {
        $res->setTVValue('poster', '');
    }
    if (!empty($entry['hero_image']['data']['attributes']['url'])) {
        $res->setTVValue('heroImage', $entry['hero_image']['data']['attributes']['url']);
    } else {
        $res->setTVValue('heroImage', '');
    }

    // Save categories (as comma separated tags)
    if ($tvCategory && $categoriesStr) {
        $res->setTVValue('category', $categoriesStr);
    }

    // Refresh cache
    $modx->cacheManager->refresh(['resource' => [$res->get('id') => []]]);

    http_response_code(200);
    echo json_encode([
        'status' => 'ok',
        'action' => $event,
        'id' => $entryId,
        'resourceId' => $res->get('id'),
        'model' => 'serie'
    ]);
}
function handleEvent(
    modX   $modx,
    string $event,
    array  $entry,
    int    $entryId,
    int    $parentId,
    int    $templateId,
    int    $tvStrapiId,
    int    $tvStrapiModel,
    ?int   $tvSections
) {
    $event = strtolower($event);

    // 🔁 Find by model + strapiId pair
    $res = findResourceByStrapiPair($modx, $tvStrapiModel, $tvStrapiId, 'event', $entryId);

    // 🗑️ Delete
    if ($event === 'entry.delete') {
        if ($res) $res->remove();
        http_response_code(200);
        echo json_encode(['status'=>'ok','action'=>'deleted','id'=>$entryId,'model'=>'event']);
        return;
    }

    // 📁 Dynamic parent by event_type
    $parentId = ($entry['event_type'] ?? '') === "Anchor" ? 78 : 79;

    // 📦 Field mapping
    $pagetitle   = $entry['event_title'] ?? "Evento $entryId";
    $alias       = !empty($entry['slug']) ? $entry['slug'] : extractAlias(['pagetitle'=>$pagetitle]);
    $longtitle   = $entry['event_title'] ?? null;
    $introtext   = $entry['event_desc'] ?? null;
    $isPublished = extractIsPublished($entry);
    $publishedOn = extractPublishedOn($entry);

    // ✅ Build content: JSON without `sections`
    $contentArr = $entry;
    if (isset($contentArr['sections'])) {
        unset($contentArr['sections']);
    }
    if (isset($contentArr['video_promo'])) {
        unset($contentArr['video_promo']);
    }

    $content = json_encode($contentArr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // 🧠 Context
    $contextKey = 'web';
    if ($parentId > 0) {
        $parentObj = $modx->getObject(modResource::class, $parentId);
        if ($parentObj) $contextKey = $parentObj->get('context_key') ?: 'web';
    }

    // 📄 Create if missing
    if (!$res && in_array($event, ['entry.create','entry.update','entry.publish','entry.unpublish'], true)) {
        $res = $modx->newObject(modResource::class);
        $res->fromArray([
            'pagetitle'   => $pagetitle,
            'alias'       => $alias,
            'parent'      => $parentId,
            'context_key' => $contextKey,
            'template'    => $templateId,
            'createdon'   => time(),
            'createdby'   => 0,
            'published'   => $isPublished ? 1 : 0,
            'publishedon' => $isPublished ? $publishedOn : 0,
            'class_key'   => 'MODX\\Revolution\\modDocument',
            'content'     => $content,
            'introtext'   => $introtext ?? '',
            'longtitle'   => $longtitle ?? '',
            'richtext'    => 0,
            'searchable'  => (int)$modx->getOption('search_default', null, 1),
            'hidemenu'    => (int)$modx->getOption('hidemenu_default', null, 0),
        ]);

        if (!$res->save()) {
            http_response_code(500);
            exit('Failed to create event resource.');
        }

        $res->setTVValue('strapiModel', 'event');
        $res->setTVValue('strapiId', (string)$entryId);

    }

    if (!$res) {
        http_response_code(200);
        echo json_encode(['status'=>'ignored','reason'=>'no-resource','id'=>$entryId,'model'=>'event']);
        return;
    }

    // ✏️ Update fields
    $fields = [
        'pagetitle' => $pagetitle,
        'alias'     => $alias,
        'content'   => $content,
        'editedon'  => time(),
        'editedby'  => 0,
        'richtext'  => 0, // ✅ make sure it's always off
    ];

    if ($longtitle !== null) $fields['longtitle'] = $longtitle;
    if ($introtext !== null) $fields['introtext'] = $introtext;

    // 📡 Publish / Unpublish logic
    if ($event === 'entry.publish') {
        $fields['published']   = 1;
        $fields['publishedon'] = $publishedOn;
        $fields['publishedby'] = 0;
    } elseif ($event === 'entry.unpublish') {
        $fields['published']   = 0;
        $fields['publishedon'] = 0;
        $fields['publishedby'] = 0;
    } else {
        $fields['published']   = $isPublished ? 1 : 0;
        $fields['publishedon'] = $isPublished ? $publishedOn : 0;
        $fields['publishedby'] = $isPublished ? 0 : 0;
    }

    $res->fromArray($fields);
    if (!$res->save()) {
        http_response_code(500);
        exit('Failed to save event resource');
    }
    // Save cover & poster TVs
    if (!empty($entry['image']['url'])) {
        $res->setTVValue('cover', $entry['image']['url']);
    }
    if (!empty($entry['poster']['url'])) {
        $res->setTVValue('poster', $entry['poster']['url']);
    }
    if (!empty($entry['hero_image']['url'])) {
        $res->setTVValue('heroImage', $entry['hero_image']['url']);
    }

    // 💾 Store sections JSON to TV
    $res->setTVValue('sectionsJson', json_encode($entry['sections'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $res->setTVValue('videoPromo', json_encode($entry['video_promo'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    // 🔄 Refresh cache
    $modx->cacheManager->refresh(['resource'=>[$res->get('id')=>[]]]);

    http_response_code(200);
    echo json_encode([
        'status'=>'ok',
        'action'=>$event,
        'id'=>$entryId,
        'resourceId'=>$res->get('id'),
        'model'=>'event'
    ]);
}
function handleNewsletter(
    modX   $modx,
    string $event,
    array  $entry,
    int    $entryId,
    int    $parentId,
    int    $templateId,
    int    $tvStrapiId,
    int    $tvStrapiModel
) {
    $event = strtolower($event);

    // Try to find existing resource
    $res = findResourceByStrapiPair($modx, $tvStrapiModel, $tvStrapiId, 'newsletter', $entryId);

    // Handle delete
    if ($event === 'entry.delete') {
        if ($res) $res->remove();
        http_response_code(200);
        echo json_encode(['status' => 'ok', 'action' => 'deleted', 'id' => $entryId, 'model' => 'newsletter']);
        return;
    }

    // Field mapping
    $pagetitle   = $entry['title'] ?? "Newsletter $entryId";
    $alias       = !empty($entry['slug']) ? $entry['slug'] : extractAlias(['pagetitle' => $pagetitle]);
    $longtitle   = $entry['title'] ?? null;
    $introtext   = $entry['description'] ?? null;
    $isPublished = extractIsPublished($entry);
    $publishedOn = extractPublishedOn($entry);

    // JSON for content (full newsletter entry, no pruning usually needed)
    $contentJson = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // Context
    $contextKey = 'web';
    if ($parentId > 0) {
        $parentObj = $modx->getObject(modResource::class, $parentId);
        if ($parentObj) $contextKey = $parentObj->get('context_key') ?: 'web';
    }

    // Create if not exists
    if (!$res && in_array($event, ['entry.create','entry.update','entry.publish','entry.unpublish'], true)) {
        $res = $modx->newObject(modResource::class);
        $res->fromArray([
            'pagetitle'   => $pagetitle,
            'alias'       => $alias,
            'parent'      => $parentId,
            'context_key' => $contextKey,
            'template'    => $templateId,
            'createdon'   => time(),
            'createdby'   => 0,
            'published'   => $isPublished ? 1 : 0,
            'publishedon' => $isPublished ? $publishedOn : 0,
            'class_key'   => 'MODX\\Revolution\\modDocument',
            'content'     => $contentJson,
            'introtext'   => $introtext ?? '',
            'longtitle'   => $longtitle ?? '',
            'richtext'    => 0
        ]);

        if (!$res->save()) {
            http_response_code(500);
            exit('Failed to create newsletter resource.');
        }

        // Save TVs
        $res->setTVValue('strapiModel', 'newsletter');
        $res->setTVValue('strapiId', (string)$entryId);
//        $res->setTVValue('poster', $entry['poster']['data']['attributes']['url']);
//        $res->setTVValue('cover', $entry['cover']['data']['attributes']['url']);
//        $res->setTVValue('heroImage', $entry['bg_image']['data']['attributes']['url']);
    }

    if (!$res) {
        http_response_code(200);
        echo json_encode(['status'=>'ignored','reason'=>'no-resource','id'=>$entryId,'model'=>'newsletter']);
        return;
    }

    // Update fields
    $fields = [
        'pagetitle'  => $pagetitle,
        'alias'      => $alias,
        'content'    => $contentJson,
        'editedon'   => time(),
        'editedby'   => 0,
    ];
    if ($longtitle !== null) $fields['longtitle'] = $longtitle;
    if ($introtext !== null) $fields['introtext'] = $introtext;

    if ($event === 'entry.publish') {
        $fields['published']   = 1;
        $fields['publishedon'] = $publishedOn;
    } elseif ($event === 'entry.unpublish') {
        $fields['published']   = 0;
        $fields['publishedon'] = 0;
    } else {
        $fields['published']   = $isPublished ? 1 : 0;
        $fields['publishedon'] = $isPublished ? $publishedOn : 0;
    }

    $res->fromArray($fields);
    if (!$res->save()) {
        http_response_code(500);
        exit('Failed to save newsletter resource');
    }

    // Save media fields into TVs if available
//    $res->setTVValue('poster', $entry['poster']['data']['attributes']['url']);
    $res->setTVValue('cover', $entry['cover']['data']['attributes']['url']);
    $res->setTVValue('heroImage', $entry['bg_image']['data']['attributes']['url']);

    // Refresh cache
    $modx->cacheManager->refresh(['resource' => [$res->get('id') => []]]);

    http_response_code(200);
    echo json_encode([
        'status'      => 'ok',
        'action'      => $event,
        'id'          => $entryId,
        'resourceId'  => $res->get('id'),
        'model'       => 'newsletter'
    ]);
}
function handleStudyNote(
    modX   $modx,
    string $event,
    array  $entry,
    int    $entryId,
    int    $parentId,
    int    $templateId,
    int    $tvStrapiId,
    int    $tvStrapiModel
) {
    $event = strtolower($event);

    // ─────────────────────────────────────────────
    // Find existing resource
    // ─────────────────────────────────────────────
    $res = findResourceByStrapiPair($modx, $tvStrapiModel, $tvStrapiId, 'study-note', $entryId);

    // Delete if necessary
    if ($event === 'entry.delete') {
        if ($res) $res->remove();
        http_response_code(200);
        echo json_encode(['status' => 'ok', 'action' => 'deleted', 'id' => $entryId, 'model' => 'study-note']);
        return;
    }

    // ─────────────────────────────────────────────
    // Map fields from entry
    // ─────────────────────────────────────────────
    $pagetitle   = $entry['title'] ?? ("Study Note " . $entryId);
    $alias       = !empty($entry['slug']) ? $entry['slug'] : extractAlias(['pagetitle' => $pagetitle]);
    $introtext   = $entry['description'] ?? '';
    $isPublished = extractIsPublished($entry);
    $publishedOn = extractPublishedOn($entry);

    // Full JSON payload as content
    $contentJson = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // Determine context_key from parent
    $contextKey = 'web';
    if ($parentId > 0) {
        $parentObj = $modx->getObject(modResource::class, $parentId);
        if ($parentObj) $contextKey = $parentObj->get('context_key') ?: 'web';
    }

    // ─────────────────────────────────────────────
    // Create if missing
    // ─────────────────────────────────────────────
    if (!$res && in_array($event, ['entry.create','entry.update','entry.publish','entry.unpublish'], true)) {
        $res = $modx->newObject(modResource::class);
        $res->fromArray([
            'pagetitle'   => $pagetitle,
            'alias'       => $alias,
            'parent'      => $parentId,
            'context_key' => $contextKey,
            'template'    => $templateId,
            'createdon'   => time(),
            'createdby'   => 0,
            'published'   => $isPublished ? 1 : 0,
            'publishedon' => $isPublished ? $publishedOn : 0,
            'class_key'   => 'MODX\\Revolution\\modDocument',
            'content'     => $contentJson,
            'introtext'   => $introtext,
            'richtext'    => 0
        ]);

        if (!$res->save()) {
            http_response_code(500);
            exit('Failed to create study-note resource.');
        }

        $res->setTVValue('strapiModel', 'study-note');
        $res->setTVValue('strapiId', (string)$entryId);
    }

    if (!$res) {
        http_response_code(200);
        echo json_encode(['status' => 'ignored', 'reason' => 'no-resource', 'id' => $entryId, 'model' => 'study-note']);
        return;
    }

    // ─────────────────────────────────────────────
    // Update fields
    // ─────────────────────────────────────────────
    $fields = [
        'pagetitle' => $pagetitle,
        'alias'     => $alias,
        'content'   => $contentJson,
        'editedon'  => time(),
        'editedby'  => 0,
    ];
    if ($introtext !== null) $fields['introtext'] = $introtext;

    if ($event === 'entry.publish') {
        $fields['published']   = 1;
        $fields['publishedon'] = $publishedOn;
    } elseif ($event === 'entry.unpublish') {
        $fields['published']   = 0;
        $fields['publishedon'] = 0;
    } else {
        $fields['published']   = $isPublished ? 1 : 0;
        $fields['publishedon'] = $isPublished ? $publishedOn : 0;
    }

    $res->fromArray($fields);
    if (!$res->save()) {
        http_response_code(500);
        exit('Failed to save study-note resource');
    }

    // ─────────────────────────────────────────────
    // Save related media in TVs
    // ─────────────────────────────────────────────
    if (!empty($entry['cover']['data'])) {
        $res->setTVValue('poster', $entry['cover']['data']['attributes']['url']);
    }
//    if (!empty($entry['pdf_file']['url'])) {
//        $res->setTVValue('pdf_file', $entry['pdf_file']['url']);
//    }
//    if (!empty($entry['epub_file']['url'])) {
//        $res->setTVValue('epub_file', $entry['epub_file']['url']);
//    }
//    if (!empty($entry['link_store'])) {
//        $res->setTVValue('link_store', $entry['link_store']);
//    }



    // ─────────────────────────────────────────────
    // Refresh cache and respond
    // ─────────────────────────────────────────────
    $modx->cacheManager->refresh(['resource' => [$res->get('id') => []]]);

    http_response_code(200);
    echo json_encode([
        'status'      => 'ok',
        'action'      => $event,
        'id'          => $entryId,
        'resourceId'  => $res->get('id'),
        'model'       => 'study-note'
    ]);
}