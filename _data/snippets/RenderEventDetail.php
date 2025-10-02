id: 36
source: 1
name: RenderEventDetail
properties: 'a:0:{}'

-----

use GuzzleHttp\Client;

// Template variables
$tpl = $modx->getOption('tplEvent', $scriptProperties, 'event-detail');
$tplSessionDay = $modx->getOption('tplSessionDay', $scriptProperties, 'tplSessionDay');
$tplSessionDetail = $modx->getOption('tplSessionDetail', $scriptProperties, 'tplSessionDetail');
$tplSessionDetailWrapper = $modx->getOption('tplSessionDetailWrapper', $scriptProperties, 'tplSessionDetailWrapper');
$tplPricingSection = $modx->getOption('tplPricingSection', $scriptProperties, 'pricing-table');
$tplPricingCard = $modx->getOption('tplPricingCard', $scriptProperties, 'pricing-cards');
$tplPricingList = $modx->getOption('tplPricingList', $scriptProperties, 'list-item');
$tplSpeaker = $modx->getOption('tplSpeaker', $scriptProperties, 'tplSpeaker');
$tplBtn = $modx->getOption('tplBtn', $scriptProperties, 'tplBtn');
$strapiId = $modx->getOption('strapiId', $scriptProperties, 'strapiId');
$tplTrip = $modx->getOption('tplTrip', $scriptProperties, 'tplTrip');

try {
    $client = new Client();
    $response = $client->request('GET', 'https://api.sumtv.org/api/events/'.$strapiId);
    $data = json_decode($response->getBody()->getContents(), true);
    // echo '<pre>' . print_r($data, true) . '</pre>';

    if (!empty($data['data'])) {
        $event = $data['data']['attributes'];
        $videoPromo = json_decode($event['video_promo'] ?? '{}', true);
        //            echo('<pre>');
        //    print_r($videoPromo);
        //    echo('</pre>');
        $htmlVideo = $videoPromo['rawData']['html'];

        // Remove width/height attributes
        $htmlVideo = preg_replace('/\s(width|height)="\d+"/', '', $htmlVideo);

        // Wrap in responsive container
        $videoEmbeded = '<div class="video-wrapper">' . $htmlVideo . '</div>';
        $scheduleUrl = $event['schedule_pdf']['data']['attributes']['url'] ?? '';

        $placeholders = [
            'eventTitle' => $event['event_title'],
            'eventDesc' => $event['event_desc'],
            'place' => $event['place'],
            'eventType' => $event['event_type'],
            'address' => $event['address'],
            'startDate' => $event['date_Start'],
            'endDate' => $event['date_end'],
            'year' => formatDate($event['date_Start'], 'Y'),
            'email' => $event['email'],
            'phoneNumber' => $event['phone_number'],
            'inPerson'=> $event['inPerson'],
            'virtual'=>$event['virtual'],
            'registrationLink' => $event['registration_link'],
            'hotelRegistration' => $event['hotel_registration'],
            'imageCover' => $event['image']['data']['attributes']['url'],
            'imageHero' => $event['hero_image']['data']['attributes']['url'],
            'poster' => $event['poster']['data']['attributes']['url'],
            'imageMobile' => $event['mobile_image']['data']['attributes']['url'] ?? '',
            'venueImage'       => $event['venueImage']['data'][0]['attributes']['url'] ?? '',
            'scheduleUrl' => $event['schedule_pdf']['data']['attributes']['url'],
            'videoPromo' => $videoEmbeded,
        ];

        $sessionTabsHtml = '';
        $contentPanesHtml = '';

        $index = 0; // Initialize the index for tab ids

        foreach ($event['schedule'] as $day) {
            $sessionDetailHtml = '';
            $sessionListHtml = '';

            $sessionItemsDebug = print_r($day['schedule_item'], true);
            foreach ($day['schedule_item'] as $session) {
                $sessionPlaceholders = [
                    'sessionTitle' => $session['session_title'],
                    'sessionStart' => formatDate($session['session_start'], 'g:i a'),
                    'sessionEnd' => formatDate($session['session_end'], 'g:i a'),
                    'speakerFullname' => isset($session['speaker']['data']) ? $session['speaker']['data']['attributes']['fullname'] : '',
                ];
                $sessionDetailHtml .= $modx->getChunk($tplSessionDetail, $sessionPlaceholders);
            }
            $sessionListHtml = $sessionDetailHtml;

            // Building tabs and content panes
            $sessionTabsHtml .= $modx->getChunk($tplSessionDay, [
                'day' => $day['day'],
                'day_date' => formatDate($day['day_date'], 'F j'),
                'index' => $index,
                'active' => ($index == 0) ? 'active' : '', // Set the first tab as active
            ]);

            $contentPanesPlaceHolders = [
                'day' => $day['day'],
                'day_date' => formatDate($day['day_date'], 'F j'),
                'index' => $index,
                'sessionsList' =>  $sessionDetailHtml ,
                'active' => ($index == 0) ? 'show active' : '',
            ];


            $contentPanesHtml .= $modx->getChunk($tplSessionDetailWrapper,$contentPanesPlaceHolders);

            $sessionDetailHtml = '';
            $sessionListHtml = '';
            $index++; // Increment the index for the next tab
        }

        $pricingCardHtml = '';
        $pricingTable='';
        foreach ($event['pricing_table']['pricing'] as $pricing) {
            $pricingCardListHtml = '';

            foreach ($pricing['items'] as $feature) {
                $pricingCardListHtml .= $modx->getChunk($tplPricingList, [
                    'list_item' => $feature['list_item'],
                ]);
            }

            $pricingCardHtml .= $modx->getChunk($tplPricingCard, [
                'heading' => $pricing['heading'],
                'cost' => $pricing['cost'],
                'button_text' => $pricing['link']['button_text'],
                'button_link' => $pricing['link']['button_link'],
                'color'=> $pricing['link']['color'],
                'list-items' => $pricingCardListHtml,
            ]);
        }
        $pricingTable .= $modx->getChunk($tplPricingSection, [
            'heading' => $event['pricing_table']['heading'],
            'subheading' => $event['pricing_table']['subheading'],
            'footer_note' => $event['pricing_table']['footer_note'],
            'pricing-cards' => $pricingCardHtml,

        ]);

        $speakersHtml = '';
        $spCount = count($event['speakers_section']['speakers']['data']);
        $speakerSection = '';


        foreach ($event['speakers_section']['speakers']['data'] as $speaker) {
            //            echo('<pre>');
            //            print_r($speaker);
            //            echo('</pre>');

            if($spCount <= 1) {
                $speakersHtml .= $modx->getChunk('speakerCard-1', [
                    'fullname' => $speaker['attributes']['fullname'],
                    'shortBio' => $speaker['attributes']['short_bio'],
                    'speakerTitle' => $speaker['attributes']['title'],
                    'speakerPic' => $speaker['attributes']['picture']['data']['attributes']['formats']['small']['url'],
                    'id' => $speaker['id']
                ]);
            } else {
                $speakersHtml .= $modx->getChunk('speakerCard-2', [
                    'fullname' => $speaker['attributes']['fullname'],
                    'shortBio' => $speaker['attributes']['short_bio'],
                    'speakerTitle' => $speaker['attributes']['title'],
                    'speakerPic' => $speaker['attributes']['picture']['data']['attributes']['formats']['small']['url'],
                    'id' => $speaker['id']
                ]);
            }

        }
        $speakerSection .= $modx->getChunk('speakersSection', [
            'heading' => $event['speakers_section']['heading'],
            'subheading' => $event['speakers_section']['subheading'],
            'speakerCards' => $speakersHtml,
            'count' => $spCount,
        ]);

        $tripHtml = '';
        $step = 0;

        foreach ($event['trip'] as $trip) {
            //  echo('<pre>');
            //   print_r($trip);
            // echo('</pre>');
            $step +=1;
            $btnHtml = '';

            foreach ($trip['button'] as $btn) {
                $btnHtml .= $modx->getChunk($tplBtn, [
                    'btn_text' => $btn['button_text'],
                    'btn_link' => $btn['button_link'],
                    'btn_color' => $btn['color'],
                ]);
            }

            $tripHtml .= $modx->getChunk($tplTrip, [
                'heading' => $trip['heading'],
                'description' => $trip['description'],
                'buttons' => $btnHtml,
                'step' => $step,
            ]);
        }
        // echo '<pre>' . print_r($event['sections']) . '</pre>';
        $sections = $modx->runSnippet('renderSections', [
            'json' => json_encode($event['sections']),
        ]);
// echo json_encode($event['sections']);
        $isSchedule = '';

        if(!empty($scheduleUrl) || !empty($contentPanesHtml)) {
            $isSchedule = 'yes';
        } else {
            $isSchedule = 'no';
        }

        $placeholders['tabs'] = $sessionTabsHtml;
        $placeholders['isSchedule'] = $isSchedule;
        $placeholders['scheduleSection'] = $contentPanesHtml;
        $placeholders['pricingSection'] = $pricingTable;
        $placeholders['speakers'] = $speakerSection;
        $placeholders['tripSteps'] = $tripHtml;
        $placeholders['sections'] = $sections;

        return $modx->getChunk($tpl, $placeholders);
    } else {
        return 'No event data available.';
    }
} catch (\GuzzleHttp\Exception\GuzzleException $e) {
    return 'Error fetching data: ' . $e->getMessage();
}

function formatDate($date, $format) {
    $d = new DateTime($date,  new DateTimeZone('UTC'));
    $d->setTimezone(new DateTimeZone('America/Los_Angeles'));
    return $d->format($format);
}