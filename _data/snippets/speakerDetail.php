id: 27
source: 1
name: speakerDetail
properties: 'a:0:{}'

-----

/**
 * SpeakerDetail
 * Renders a speaker JSON object through the "speaker-detail" chunk.
 *
 * Params:
 *  &json        - (string) JSON for a single speaker. If empty, uses [[*content]].
 *  &chunk       - (string) Chunk to render. Default: speaker-detail
 *  &escape      - (0/1)    If 1, escape text fields. Default: 1
 *
 * Example:
 *   [[!SpeakerDetail? &json=`[[*content]]`]]
 *   [[!SpeakerDetail? &json=`[[*sectionsJson]]` &chunk=`speaker-detail`]]
 */

$json   = isset($json) ? (string)$json : '';
$chunk  = !empty($chunk) ? (string)$chunk : 'speaker-detail';
$escape = isset($escape) ? (int)$escape : 1;

// Support feeding the entire webhook envelope accidentally
// (if you pass the full Strapi webhook JSON, try to drill to entry)
if ($json === '') {
    $json = (string)$modx->resource->get('content');
}
$src = json_decode($json, true);
if (!is_array($src)) {
    return '<!-- SpeakerDetail: invalid JSON -->';
}
if (isset($src['entry']) && is_array($src['entry'])) {
    $entry = $src['entry']; // when the whole webhook body was passed
} else {
    $entry = $src;          // when only the speaker object was passed
}

/** Accept boolean true or true-like strings/ints */
$isTrueLike = static function ($v): bool {
    if (is_bool($v)) return $v;
    if (is_int($v))  return $v === 1;
    if (is_string($v)) return in_array(strtolower(trim($v)), ['1','true','yes','on'], true);
    return false;
};

$sumtvFlag = $entry['SUMtv_speaker'] ?? $entry['SUMtv_Speaker'] ?? null;
if (!$isTrueLike($sumtvFlag)) {
    return '<!-- SpeakerDetail: skipped (SUMtv_speaker is not true) -->';
}

/** Helpers to extract safe strings */
$clean = static function ($v) use ($escape) {
    if ($v === null) return '';
    $s = is_scalar($v) ? (string)$v : json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    return $escape ? htmlspecialchars($s, ENT_QUOTES, 'UTF-8') : $s;
};

/** Picture URLs (original + thumbnail when available) */
$getImage = static function (?array $img): array {
    if (!$img || !is_array($img)) return ['url'=>'','thumb'=>'','mime'=>''];
    $url   = $img['url']   ?? '';
    $mime  = $img['mime']  ?? '';
    $thumb = '';
    if (!empty($img['formats']['thumbnail']['url'])) {
        $thumb = $img['formats']['thumbnail']['url'];
    }
    return ['url'=>$url, 'thumb'=>$thumb, 'mime'=>$mime];
};

$pic  = $getImage($entry['picture'] ?? null);
$tpic = $getImage($entry['picture_transparent'] ?? null);

/** Build placeholders for the chunk */
$fullname = $entry['fullname']
    ?? trim(($entry['name'] ?? '').' '.($entry['lastname'] ?? ''))
    ?: 'Speaker';

$pl = [
    // identity
    'id'          => (string)($entry['id'] ?? ''),
    'fullname'    => $clean($fullname),
    'name'        => $clean($entry['name'] ?? ''),
    'lastname'    => $clean($entry['lastname'] ?? ''),
    'slug'        => $clean($entry['slug'] ?? ''),
    'title'       => $clean($entry['title'] ?? ''),

    // bios
    'short_bio'   => $clean($entry['short_bio'] ?? ''),
    'description' => $clean($entry['description'] ?? ''),
    'place'       => $clean($entry['place'] ?? ''),
    'featured'    => $isTrueLike($entry['featured'] ?? false) ? '1' : '0',

    // dates (raw ISO so you can format in chunk or with output filters)
    'createdAt'   => $clean($entry['createdAt'] ?? ''),
    'updatedAt'   => $clean($entry['updatedAt'] ?? ''),
    'publishedAt' => $clean($entry['publishedAt'] ?? ''),
    'locale'      => $clean($entry['locale'] ?? ''),

    // images
    'picture_url'           => $clean($pic['url']),
    'picture_thumb'         => $clean($pic['thumb']),
    'picture_mime'          => $clean($pic['mime']),
    'picture_transp_url'    => $clean($tpic['url']),
    'picture_transp_thumb'  => $clean($tpic['thumb']),
    'picture_transp_mime'   => $clean($tpic['mime']),

    // raw JSON (minified) if you want to keep/show it
    'json'        => json_encode($entry, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
];

// Render
return $modx->getChunk($chunk, $pl);