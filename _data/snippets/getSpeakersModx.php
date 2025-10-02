id: 29
source: 1
name: getSpeakersModx
properties: 'a:0:{}'

-----

/**
 * getSpeakers
 *
 * Usage:
 *   [[!getSpeakers? &parent=`7` &tpl=`speaker-card` &limit=`0` &debug=`0`]]
 *
 * Renders speaker cards from child resources under &parent.
 * It reads Strapi entry JSON from TV "sectionsJson"; if empty/invalid, falls back to resource content.
 * Skips resources if SUMtv_speaker is not truthy.
 */

$parent = (int)$modx->getOption('parent', $scriptProperties, 7);
$tpl    = $modx->getOption('tpl', $scriptProperties, 'card-2');
$limit  = (int)$modx->getOption('limit', $scriptProperties, 0);
$heading  = $modx->getOption('heading', $scriptProperties, '');
$subheading  = $modx->getOption('subheading', $scriptProperties, '');
$debug  = (int)$modx->getOption('debug', $scriptProperties, 0);

// Helper: robust truthiness for SUMtv_speaker
$isTrue = static function ($v) {
    if (is_bool($v)) return $v;
    if (is_int($v))  return $v === 1;
    if (is_string($v)) {
        $v = strtolower(trim($v));
        return in_array($v, ['1','true','yes','on'], true);
    }
    return false;
};

$criteria = [
    'parent'    => $parent,
    'published' => 1,
    'deleted'   => 0,
];

$q = $modx->newQuery('modResource', $criteria);
// $q->sortby('menuindex', 'ASC');
$q->sortby('pagetitle', 'ASC');
$speakers = $modx->getCollection('modResource', $q);

$output  = '';
$count   = 0;
$headingSectionTpl = $modx->getChunk('headingSection', [
    'heading' => $heading,
    'subheading' => $subheading,
]);
// echo  print_r($speakers);
foreach ($speakers as $sp) {
    $id        = (int)$sp->get('id');
    $title     = (string)$sp->get('pagetitle');
    $introtext = (string)$sp->get('introtext');
    $url       = $modx->makeUrl($id);
    $picture   = '';

    // 1) Try TV sectionsJson
    $entry = null;
    $sectionsJson = $sp->getTVValue('sectionsJson');
    if ($sectionsJson) {
        $tmp = json_decode($sectionsJson, true);
        $entry =$tmp;

    }

    if(!array_key_exists('SUMtv_speaker',$entry)) {
        continue;
    }

    // Resolve picture
    if (!empty($entry['picture']['url'])) {
        $picture = $entry['picture']['url'];
    }


    // Prefer fullname from entry JSON for heading if available
    $heading = !empty($entry['fullname']) ? $entry['fullname'] : $title;

    $output .= $modx->getChunk($tpl, [
        'id'        => $id,
        'heading'   => $heading,
        'body'      => $introtext,
        'url'       => $url,
        'picture'   => $picture,
    ]);

    $count++;
    if ($limit > 0 && $count >= $limit) break;
}
$html = '<div class="container">'.$headingSectionTpl .$output.'</div>';

return $html;