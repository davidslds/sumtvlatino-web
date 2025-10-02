id: 25
source: 1
name: Markdown
description: 'Markdown for MODX Revolution'
properties: 'a:4:{s:2:"id";a:7:{s:4:"name";s:2:"id";s:4:"desc";s:16:"markdown_prop_id";s:4:"type";s:11:"numberfield";s:7:"options";a:0:{}s:5:"value";s:0:"";s:7:"lexicon";s:19:"markdown:properties";s:4:"area";s:0:"";}s:5:"field";a:7:{s:4:"name";s:5:"field";s:4:"desc";s:19:"markdown_prop_field";s:4:"type";s:9:"textfield";s:7:"options";a:0:{}s:5:"value";s:7:"content";s:7:"lexicon";s:19:"markdown:properties";s:4:"area";s:0:"";}s:9:"stripTags";a:7:{s:4:"name";s:9:"stripTags";s:4:"desc";s:23:"markdown_prop_stripTags";s:4:"type";s:13:"combo-boolean";s:7:"options";a:0:{}s:5:"value";b:0;s:7:"lexicon";s:19:"markdown:properties";s:4:"area";s:0:"";}s:10:"escapeTags";a:7:{s:4:"name";s:10:"escapeTags";s:4:"desc";s:24:"markdown_prop_escapeTags";s:4:"type";s:13:"combo-boolean";s:7:"options";a:0:{}s:5:"value";b:1;s:7:"lexicon";s:19:"markdown:properties";s:4:"area";s:0:"";}}'
static_file: core/components/markdown/elements/snippets/snippet.markdown.php

-----

/** @var array $scriptProperties */
if (empty($field)) {
    $field = 'content';
} else {
    $field = strtolower($field);
}

// Allow to use parser for any text
if (!empty($raw)) {
    $input = $raw;
} elseif (empty($input)) {
    /** @var modResource $resource */
    $resource = empty($id)
        ? $modx->resource
        : $modx->getObject('modResource', $id);

    if (!$resource) {
        return 'Could not retrieve resource with id = "' . $id . '"';
    }

    $fields = $modx->getFields('modResource');
    $input = array_key_exists($field, $fields)
        ? $resource->get($field)
        : $resource->getTVValue($field);
}

// HTML tags in code
$input = preg_replace_callback('/`.*`/s', function ($matches) {
    return htmlspecialchars($matches[0], ENT_QUOTES, 'UTF-8');
}, $input);

// Strip HTML tags
if (!empty($stripTags)) {
    // Markdown links, that looks like tag
    $input = preg_replace('#<(.*?(://|@).*?)>#', '&lt;$1&gt;', $input);
    // Strip tags
    if (is_numeric($stripTags)) {
        $stripTags = '';
    } else {
        $tmp = explode(',', $stripTags);
        $tmp2 = array();
        foreach ($tmp as $v) {
            $tmp2[] = '<' . trim($v, '<> ') . '>';
        }
        $stripTags = implode($tmp2);
    }
    $input = strip_tags($input, $stripTags);
}
// And decode entities
$input = html_entity_decode($input, ENT_QUOTES, 'UTF-8');

// Remove possible BOM from start of string
$bom = pack('H*', 'EFBBBF');
$input = str_replace($bom, '', $input);

// Parse MODX tags
if (empty($escapeTags)) {
    $input = $modx->newObject('modChunk')->process(null, $input);
} else {
    $input = preg_replace('/=`(.*?)`/', '=&#96;$1&#96;', $input);
}

// Parse!
require_once MODX_CORE_PATH . 'components/markdown/vendor/autoload.php';
$input = ParsedownExtra::instance()->text($input);

// Escape MODX tags
if (!empty($escapeTags)) {
    $input = str_replace(
        array('[', ']', '&amp;#96;', '{', '}'),
        array('&#91;', '&#93;', '&#96;', '&#123;', '&#125;'),
        $input
    );
}

return $input;