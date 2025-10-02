id: 43
source: 1
name: slider
properties: 'a:0:{}'

-----

use GuzzleHttp\Client;

/**
 * MODX Snippet: getHomepageSlider
 * Fetches the homepage slider from Strapi and renders each slide with a chunk.
 *
 * @param string $tplEvent Name of the chunk template for each slide
 */

$tpl = $modx->getOption('tpl', $scriptProperties, 'slide');

try {
    $client = new Client();
    $response = $client->request('GET', 'https://api.sumtv.org/api/homepage?locale=es&populate[slider][populate]=*');
    $data = json_decode($response->getBody()->getContents(), true);

    if (!$data || !isset($data['data']['attributes']['slider'])) {
        return 'No slider data found.';
    }

    $slides = $data['data']['attributes']['slider'];
    $sliderHtml = '';

    foreach ($slides as $slide) {
        $attributes = $slide['image_slider']['data']['attributes'] ?? [];

        $sliderHtml .= $modx->getChunk($tpl, [
            'heading'              => $slide['heading'] ?? '',
            'subheading'           => $slide['subheading'] ?? '',
            'pill_text'            => $slide['pill_text'] ?? '',
            'link_label'           => $slide['link_label'] ?? '',
            'link_url'             => $slide['link_url'] ?? '',
            'image_slider'         => $attributes['url'] ?? '',
            'image_slider_mobile'  => $slide['image_slider_mobile']['data']['attributes']['url']?? '',
        ]);
    }

    return $sliderHtml;

} catch (Exception $e) {
    return 'Error: ' . $e->getMessage();
}