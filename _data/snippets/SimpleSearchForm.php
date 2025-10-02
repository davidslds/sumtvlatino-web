id: 24
name: SimpleSearchForm
category: SimpleSearch
properties: 'a:5:{s:3:"tpl";a:7:{s:4:"name";s:3:"tpl";s:4:"desc";s:26:"simplesearch.tpl_form_desc";s:4:"type";s:9:"textfield";s:7:"options";a:0:{}s:5:"value";s:10:"SearchForm";s:7:"lexicon";s:23:"simplesearch:properties";s:4:"area";s:0:"";}s:7:"landing";a:7:{s:4:"name";s:7:"landing";s:4:"desc";s:25:"simplesearch.landing_desc";s:4:"type";s:9:"textfield";s:7:"options";a:0:{}s:5:"value";s:0:"";s:7:"lexicon";s:23:"simplesearch:properties";s:4:"area";s:0:"";}s:11:"searchIndex";a:7:{s:4:"name";s:11:"searchIndex";s:4:"desc";s:29:"simplesearch.searchindex_desc";s:4:"type";s:9:"textfield";s:7:"options";a:0:{}s:5:"value";s:6:"search";s:7:"lexicon";s:23:"simplesearch:properties";s:4:"area";s:0:"";}s:6:"method";a:7:{s:4:"name";s:6:"method";s:4:"desc";s:24:"simplesearch.method_desc";s:4:"type";s:13:"combo-boolean";s:7:"options";a:2:{i:0;a:2:{s:4:"text";s:16:"simplesearch.get";s:5:"value";s:3:"get";}i:1;a:2:{s:4:"text";s:17:"simplesearch.post";s:5:"value";s:4:"post";}}s:5:"value";s:3:"get";s:7:"lexicon";s:23:"simplesearch:properties";s:4:"area";s:0:"";}s:13:"toPlaceholder";a:7:{s:4:"name";s:13:"toPlaceholder";s:4:"desc";s:31:"simplesearch.toplaceholder_desc";s:4:"type";s:9:"textfield";s:7:"options";a:0:{}s:5:"value";s:0:"";s:7:"lexicon";s:23:"simplesearch:properties";s:4:"area";s:0:"";}}'

-----

use SimpleSearch\SimpleSearch;
/**
 * Show the search form
 *
 * @var modX $modx
 * @var array $scriptProperties
 * @package simplesearch
 */
$search = new SimpleSearch($modx, $scriptProperties);

/* Setup default options. */
$scriptProperties = array_merge(
    array(
        'tpl'           => 'SearchForm',
        'method'        => 'get',
        'searchIndex'   => 'search',
        'toPlaceholder' => '',
        'landing'       => $modx->resource->get('id'),
), $scriptProperties);

if (empty($scriptProperties['landing'])) {
  $scriptProperties['landing'] = $modx->resource->get('id');
}

/* If get value already exists, set it as default. */
$searchValue  = $_REQUEST[$scriptProperties['searchIndex']] ?? '';
$searchValues = explode(' ', $searchValue);

array_map(array($modx, 'sanitizeString'), $searchValues);

$searchValue  = implode(' ', $searchValues);
$placeholders = array(
    'method'      => $scriptProperties['method'],
    'landing'     => $scriptProperties['landing'],
    'searchValue' => strip_tags(htmlspecialchars($searchValue, ENT_QUOTES, 'UTF-8')),
    'searchIndex' => $scriptProperties['searchIndex'],
);

$output = $search->getChunk($scriptProperties['tpl'], $placeholders);

return $search->output($output, $scriptProperties['toPlaceholder']);