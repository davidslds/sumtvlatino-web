id: 35
source: 1
name: getLatestChildLink
properties: 'a:0:{}'

-----

/**
 * getLatestChildLink - safe version
 *
 * Usage:
 * [[getLatestChildLink? &parent=`78`]]
 */

$parent = (int) $modx->getOption('parent', $scriptProperties, 78);
$modx->setDebug(true);
ini_set('display_errors', 1);
error_reporting(E_ALL);
// Build query safely
$c = $modx->newQuery(modResource::class);
$c->where([
    'parent'    => $parent,
    'published' => 1,
    'deleted'   => 0,
]);

// ✅ Important: make sure the column exists (publishedon)
$c->sortby('publishedon', 'DESC');
$c->limit(1);

$child = $modx->getObject(modResource::class, $c);

// Debug: uncomment this to see what we’re getting
// return '<pre>' . print_r($child ? $child->toArray() : 'NO CHILD FOUND', true) . '</pre>';

if ($child && $child->get('id')) {
    return $modx->makeUrl($child->get('id'), '', '', 'full');
}

// Fallback: return link to parent container
return $modx->makeUrl($parent, '', '', 'full');