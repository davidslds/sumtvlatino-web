id: 33
source: 1
name: getSortDirection
properties: 'a:0:{}'

-----

$allowedDirections = ['asc', 'desc'];
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'id-desc';

$sortDirection = explode('-', $sort)[1] ?? 'desc';

return in_array($sortDirection, $allowedDirections) ? strtoupper($sortDirection) : 'DESC';