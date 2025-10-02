id: 21
name: tolinks
description: 'Builds links from tags.'
category: tagLister
properties: 'a:10:{s:5:"items";a:7:{s:4:"name";s:5:"items";s:4:"desc";s:23:"prop_tolinks.items_desc";s:4:"type";s:9:"textfield";s:7:"options";s:0:"";s:5:"value";s:0:"";s:7:"lexicon";s:20:"taglister:properties";s:4:"area";s:0:"";}s:3:"tpl";a:7:{s:4:"name";s:3:"tpl";s:4:"desc";s:21:"prop_tolinks.tpl_desc";s:4:"type";s:9:"textfield";s:7:"options";s:0:"";s:5:"value";s:4:"link";s:7:"lexicon";s:20:"taglister:properties";s:4:"area";s:0:"";}s:6:"target";a:7:{s:4:"name";s:6:"target";s:4:"desc";s:24:"prop_tolinks.target_desc";s:4:"type";s:9:"textfield";s:7:"options";s:0:"";s:5:"value";s:0:"";s:7:"lexicon";s:20:"taglister:properties";s:4:"area";s:0:"";}s:10:"inputDelim";a:7:{s:4:"name";s:10:"inputDelim";s:4:"desc";s:28:"prop_tolinks.inputdelim_desc";s:4:"type";s:9:"textfield";s:7:"options";s:0:"";s:5:"value";s:1:",";s:7:"lexicon";s:20:"taglister:properties";s:4:"area";s:0:"";}s:11:"outputDelim";a:7:{s:4:"name";s:11:"outputDelim";s:4:"desc";s:29:"prop_tolinks.outputdelim_desc";s:4:"type";s:9:"textfield";s:7:"options";s:0:"";s:5:"value";s:2:", ";s:7:"lexicon";s:20:"taglister:properties";s:4:"area";s:0:"";}s:15:"tagRequestParam";a:7:{s:4:"name";s:15:"tagRequestParam";s:4:"desc";s:33:"prop_tolinks.tagrequestparam_desc";s:4:"type";s:9:"textfield";s:7:"options";s:0:"";s:5:"value";s:3:"tag";s:7:"lexicon";s:20:"taglister:properties";s:4:"area";s:0:"";}s:9:"tagKeyVar";a:7:{s:4:"name";s:9:"tagKeyVar";s:4:"desc";s:27:"prop_tolinks.tagkeyvar_desc";s:4:"type";s:9:"textfield";s:7:"options";s:0:"";s:5:"value";s:3:"key";s:7:"lexicon";s:20:"taglister:properties";s:4:"area";s:0:"";}s:6:"tagKey";a:7:{s:4:"name";s:6:"tagKey";s:4:"desc";s:24:"prop_tolinks.tagkey_desc";s:4:"type";s:9:"textfield";s:7:"options";s:0:"";s:5:"value";s:4:"tags";s:7:"lexicon";s:20:"taglister:properties";s:4:"area";s:0:"";}s:3:"cls";a:7:{s:4:"name";s:3:"cls";s:4:"desc";s:21:"prop_tolinks.cls_desc";s:4:"type";s:9:"textfield";s:7:"options";s:0:"";s:5:"value";s:6:"tl-tag";s:7:"lexicon";s:20:"taglister:properties";s:4:"area";s:0:"";}s:13:"toPlaceholder";a:7:{s:4:"name";s:13:"toPlaceholder";s:4:"desc";s:31:"prop_tolinks.toplaceholder_desc";s:4:"type";s:9:"textfield";s:7:"options";s:0:"";s:5:"value";s:0:"";s:7:"lexicon";s:20:"taglister:properties";s:4:"area";s:0:"";}}'

-----

/**
 * tagLister
 *
 * Copyright 2010 by Shaun McCormick <shaun@modxcms.com>
 *
 * This file is part of tagLister, a simple tag listing snippet for MODx
 * Revolution.
 *
 * tagLister is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * tagLister is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU General Public License for more
 * details.
 *
 * You should have received a copy of the GNU General Public License along with
 * tagLister; if not, write to the Free Software Foundation, Inc., 59 Temple
 * Place, Suite 330, Boston, MA 02111-1307 USA
 *
 * @package taglister
 */
/**
 * tolinks snippet. Creates links out of tags.
 *
 * @var modX $modx
 * @var tagLister $tagLister
 * @var array $scriptProperties
 * 
 * @package taglister
 */
$tagLister = $modx->getService('taglister','TagLister',$modx->getOption('taglister.core_path',null,$modx->getOption('core_path').'components/taglister/').'model/taglister/',$scriptProperties);
if (!($tagLister instanceof TagLister)) return '';

/* setup default properties */
$inputDelim = $modx->getOption('inputDelim',$scriptProperties,',');
$outputDelim = $modx->getOption('outputDelim',$scriptProperties,', ');
$tagRequestParam = $modx->getOption('tagRequestParam',$scriptProperties,'tag');
$tagKeyVar = $modx->getOption('tagKeyVar',$scriptProperties,'key');
$tagKey = $modx->getOption('tagKey',$scriptProperties,'tags');
$target = !empty($scriptProperties['target']) ? $scriptProperties['target'] : $modx->resource->get('id');
$tpl = $modx->getOption('tpl',$scriptProperties,'link');
$cls = $modx->getOption('cls',$scriptProperties,'tl-tag');
$useTagsFurl = $modx->getOption('useTagsFurl',$scriptProperties,false);

/* get items */
$items = $modx->getOption('items',$scriptProperties,'');
if (empty($items)) return '';
$items = explode($inputDelim,$items);

/* if extra params, set em */
$extraParams = $modx->getOption('extraParams',$scriptProperties,'');
if (!empty($extraParams)) {
    $extraParams = trim(trim(trim($extraParams,'?'),'&'),'&amp;');
    $eps= explode(',',$extraParams);
    $extraParams = array();
    foreach ($eps as $ep) {
        $ep = explode('=',$ep);
        if (!empty($ep[1])) {
            $extraParams[$ep[0]] = $ep[1];
        }
    }
}

/* iterate */
$tags = array();
foreach ($items as $item) {
    $itemArray = array();
    $itemArray['item'] = trim($item);
    $params = array();
    if (empty($useTagsFurl)) {
        $params = array(
            $tagRequestParam => $itemArray['item'],
            $tagKeyVar => $tagKey,
        );
    }

    if (!empty($extraParams)) {
        $params = array_merge($extraParams,$params);
    }
    $itemArray['url'] = $modx->makeUrl($target,'',$params);
    if (!empty($useTagsFurl)) {
         $itemArray['url'] = rtrim($itemArray['url'],'/').'/'.$tagKey.'/'.$itemArray['item'];
    }
    $itemArray['url'] = str_replace(' ','+',$itemArray['url']);
    $itemArray['cls'] = $cls;
    $tags[] = $tagLister->getChunk($tpl,$itemArray);
}

/* output */
$toPlaceholder = $modx->getOption('toPlaceholder',$scriptProperties,false);
$output = trim(implode($outputDelim,$tags),$outputDelim);
if ($toPlaceholder) {
    $modx->setPlaceholder($toPlaceholder,$output);
    return '';
}
return $output;