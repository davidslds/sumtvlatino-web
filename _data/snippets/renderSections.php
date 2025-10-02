id: 26
source: 1
name: renderSections
properties: 'a:0:{}'

-----

/**
 * RenderSections v2
 *
 * Usage:
 *   [[!RenderSections? &json=`[[*sectionsJson]]` &wrapperTpl=`sections-wrapper` &debug=`0`]]
 *
 * Behavior:
 *   - __component: "elements.X" or "blocks.X" -> render Chunk "X"
 *   - __component: "snippets.X"              -> run Snippet "X"
 *   - All block keys become placeholders; nested arrays/objects are flattened to both dot and underscore keys.
 *   - For list arrays (e.g., list_items), if a row chunk "X.list_items.row" exists, it renders rows and sets [[+list_items_rows]].
 *
 * Params:
 *   &json        JSON string (sections array) — typically [[*sectionsJson]]
 *   &wrapperTpl  Optional wrapper chunk; receives [[+output]]
 *   &debug       0/1: add HTML comments when chunks/snippets missing
 */

$json       = (string) ($json ?? '');
$wrapperTpl = (string) ($wrapperTpl ?? '');
$debug      = (int) ($debug ?? 0);

if (!$json) return '';
$blocks = json_decode($json, true);
if (!is_array($blocks)) {
    return $debug ? "<!-- RenderSections: invalid JSON -->" : '';
}

$out = [];
$index = 0;
$html = '';

// echo "<pre>";
// echo print_r($blocks, true);
// echo "</pre>";
foreach ($blocks as $block) {

    switch ($block['__component']) {
        case 'elements.rich-text':
            $html .= $modx->getChunk('rich_text', [
                'content' => $block['content'],
            ]);
            break;
        case 'blocks.feature1':
            $buttons = '';
            foreach ($block['button'] as $btn){
                $buttons .= $modx->getChunk('button', [
                    'button_link' => $btn['button_link'],
                    'button_color' => $btn['color'],
                    'button_text' => $btn['button_text']
                ]);
            }

            $html .= $modx->getChunk('feature1', [
                'variant' => $block['variant'],
                'pill_color' => $block['pill']['pill_color'],
                'pill_label' => $block['pill']['pill_label'],
                'heading' => $block['heading'],
                'bodyContent' => $block['bodyContent'],
                'picture' => $block['image']['url']?? $block['image']['data']['attributes']['url']?? '',
                'buttons' => $buttons,
            ]);


            break;
        case 'blocks.feature2':
            $buttons = '';
            foreach ($block['button'] as $btn){
                $buttons .= $modx->getChunk('button', [
                    'button_link' => $btn['button_link'],
                    'button_color' => $btn['color'],
                    'button_text' => $btn['button_text']
                ]);
            }


            $html .= $modx->getChunk('feature2', [
                'variant' => $block['variant'],
                'subheading' => $block['subheading'],
                'heading' => $block['heading'],
                'bodyContent' => $block['bodyContent'],
                'picture' => $block['image']['url']?? $block['image']['data']['attributes']['url']?? '',
                'buttons' => $buttons,
            ]);



            break;
        case 'blocks.feature3':
            $buttons = '';
            foreach ($block['button'] as $btn){
                $buttons .= $modx->getChunk('button', [
                    'button_link' => $btn['button_link'],
                    'button_color' => $btn['color'],
                    'button_text' => $btn['button_text']
                ]);
            }

            $html .= $modx->getChunk('feature3', [
                'variant' => $block['variant'],
                'pill_color' => $block['pill']['pill_color'],
                'pill_label' => $block['pill']['pill_label'],
                'heading' => $block['heading'],
                'color' => $block['color'],
                'bodyContent' => $block['bodyContent'],
                'picture' => $block['image']['url']?? $block['image']['data']['attributes']['url']?? '',
                'buttons' => $buttons,
            ]);



            break;
        case 'blocks.pricing-table':

            $pricingCards ='';
            foreach ($block['pricing'] as $item) {
                $listItems = '';
                foreach ($item['items'] as $i){
                    $listItems .= $modx->getChunk('list-item', [
                        'list_item' => $i['list_item'],
                        'id' => $i['id'],
                    ]);
                }
                $pricingCards .= $modx->getChunk('pricing-cards', [
                    'heading' => $item['heading'],
                    'cost' => $item['cost'],
                    'list-items' => $listItems,
                    'button_link' => $item['link']['button_link'],
                    'button_text' => $item['link']['button_text'],
                    'color' => $item['link']['color'],
                ]);
            }
            $html .= $modx->getChunk('pricing-table', [
                'heading' => $block['heading'],
                'subheading' => $block['subheading'],
                'cost' => $block['cost'],
                'pricing-cards' => $pricingCards,
                'footer_note' => $block['footer_note'],

            ]);

            break;
        case 'blocks.list-group':
            $listGroupItems = '';
            foreach ($block['list_items'] as $item){
                $listGroupItems .= $modx->getChunk('list-group-item', [
                    'item_heading' => $item['item_heading'],
                    'item_body' => $item['item_body'],
                    'id' => $item['id']
                ]);
            }
            $html .= $modx->getChunk('list-group', [
                'heading' => $block['heading'],
                'list-group-items' => $listGroupItems,
            ]);

            break;

        case 'blocks.shareable-resources':
            $files = '';
            foreach ($block['file'] as $file){
                $files .= $modx->getChunk('file-preview', [
                    'file_title' => $file['title'],
                    'file_mime' => $file['file']['mime'],
                    'file_url' => $file['file']['url'] ?? $file['file']['data']['attributes']['url'],
                    'file_id' => $file['file']['id']
                ]);
            }
            $html .= $modx->getChunk('shareable-resources', [
                'heading' => $block['heading'],
                'subheading' =>$block['subheading'],
                'files' => $files,
                'id' => $block['id']
            ]);

            break;
        case 'blocks.steps-block':
            $steps = '';

            // Loop through each step

            foreach ($block['step'] as $i => $step) {
                $links = '';

                // Step number: use index + 1
                $stepNumber = $i + 1;

                // Loop through links for this step
                if (!empty($step['link']) && is_array($step['link'])) {
                    foreach ($step['link'] as $link) {
                        $links .= $modx->getChunk('button', [
                            'button_text'  => $link['button_text'] ?? '',
                            'button_link'  => $link['button_link'] ?? '',
                            'button_color' => $link['color'] ?? 'primary',
                            'button_id'    => $link['id'] ?? '',
                        ]);
                    }
                }

                // Render each step
                $steps .= $modx->getChunk('steps', [
                    'step_heading' => $step['heading'] ?? '',
                    'content' => $step['content'] ?? '',
                    'buttons'        => $links,
                    'step_id'      => $step['id'] ?? '',
                    'step_number'  => $stepNumber,
                ]);
            }

            // Wrap all steps
            $html .= $modx->getChunk('steps-block', [
                'heading'    => $block['heading'] ?? '',
                'subheading' => $block['subheading'] ?? '',
                'steps'      => $steps,
                'id'         => $block['id'] ?? '',
            ]);


            break;
        case 'snippets.speakers':
            // Run a snippet that gets speakers (resources under parent 7)
            $html .= $modx->runSnippet('getSpeakersModx', [
                'parent' => 7,
                'tpl'    => $block['template'],
                'limit' => $block['limit'],
                'debug' => 0,
                'heading' => $block['heading'],
                'Subheading' => '',
            ]);
            break;
        default:
            $html .= 'no component chunk match';
            break;
    }
}


return $html;