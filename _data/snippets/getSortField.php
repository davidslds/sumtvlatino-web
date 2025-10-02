id: 32
source: 1
name: getSortField
properties: 'a:0:{}'

-----

$allowedFields = ['id', 'pagetitle']; // Allowed sorting fields
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'id-desc';

$sortField = explode('-', $sort)[0]; // Extract field

return in_array($sortField, $allowedFields) ? $sortField : 'id';