<?php

require_once(__DIR__ . '/vendor/autoload.php');

include 'functions.php';

use FetchMeditation\JFTLanguage;
use FetchMeditation\JFTSettings;
use FetchMeditation\JFT;

use FetchMeditation\SPADLanguage;
use FetchMeditation\SPADSettings;
use FetchMeditation\SPAD;

$input = json_decode(file_get_contents('php://input'), true);
error_log(json_encode($input));

$messaging = $input['entry'][0]['messaging'][0];
if (isset($messaging['message']['attachments'])) {
    $messaging_attachment_payload = $messaging['message']['attachments'][0]['payload'];
}
$senderId  = $messaging['sender']['id'];
if (isset($messaging['message']['text']) && $messaging['message']['text'] !== null) {
    $messageText = $messaging['message']['text'];
    $GLOBALS['virtual'] = strpos(strtolower($messageText), "vm") === 0;
    $messageText = ltrim(preg_replace("/(vm)(.*)/", "$2", strtolower($messageText)));
    $coordinates = getCoordinatesForAddress($messageText);
} /*elseif (isset($messaging_attachment_payload) && $messaging_attachment_payload !== null) {
    $coordinates = new Coordinates();
    $coordinates->latitude = $messaging_attachment_payload['coordinates']['lat'];
    $coordinates->longitude = $messaging_attachment_payload['coordinates']['long'];
}*/

$payload = null;
$answer = "";
$jftLanguages = [
    'english' => JFTLanguage::English,
    'german' => JFTLanguage::German,
    'italian' => JFTLanguage::Italian,
    'japanese' => JFTLanguage::Japanese,
    'portuguese' => JFTLanguage::Portuguese,
    'russian' => JFTLanguage::Russian,
    'spanish' => JFTLanguage::Spanish,
    'swedish' => JFTLanguage::Swedish,
    'french' => JFTLanguage::French,
];

$settings = json_decode(getState($messaging['sender']['id'], StateDataType::DAY));

if (isset($messaging['postback']['payload'])
    && $messaging['postback']['payload'] == "get_started") {
    sendMessage($GLOBALS['title'] . ".  You can search for meetings by entering a City, County or Postal Code, or even a Full Address.  (Note: Distances, unless a precise location, will be estimates.)");
    sendMessage("By default, results for today will show up.  You can adjust this setting using the menu below.");
    sendMessage("If you start your search with `vm` and then location it will return virtual meetings displayed in your local timezone.  Example: vm Asheboro, NC");
} elseif ((isset($messageText) && strtoupper($messageText) == "JFT") || ((isset($messaging['postback']['payload'])
        && $messaging['postback']['payload'] == "JFT"))) {
    sendJftLanguageOptions(array_keys($jftLanguages));
} elseif (isset($messaging['message']['quick_reply']['payload']) && in_array(strtolower($messaging['message']['quick_reply']['payload']), array_keys($jftLanguages))) {
    $language = strtolower($messaging['message']['quick_reply']['payload']);
    handleJftLanguageSelection($jftLanguages, strtolower($language));
} elseif ((isset($messageText) && strtoupper($messageText) == "SPAD") || ((isset($messaging['postback']['payload'])
        && $messaging['postback']['payload'] == "SPAD"))) {

    $settings = new SPADSettings(SPADLanguage::English);
    $instance = SPAD::getInstance($settings);
    $entry = $instance->fetch();
    $entryTxt = recursiveToString($entry->withoutTags());
    sendMessage( $entryTxt );
} elseif (isset($messageText)
          && strtoupper($messageText) == "MORE RESULTS") {
    $payload = json_decode( $messaging['message']['quick_reply']['payload'] );
    sendMeetingResults($payload->coordinates, getMeetingResults($payload->coordinates, $settings, $payload->results_start));
} elseif (isset($messaging['postback']['payload'])) {
    $payload = json_decode($messaging['postback']['payload']);
    setState($senderId, StateDataType::DAY, json_encode($payload));

    $coordinates = getSavedCoordinates($senderId);
    if ($coordinates != null) {
        $settings = json_decode(getState($messaging['sender']['id'], StateDataType::DAY));
        sendMeetingResults($coordinates, getMeetingResults($coordinates, $settings));
    } else {
        sendMessage('The day has been set to ' . $payload->set_day . ".  This setting will reset to lookup Today's meetings in 5 minutes.  Enter a City, County or Zip Code.");
    }
} elseif (isset($messageText) && strtoupper($messageText) == "THANK YOU") {
    sendMessage( ":)" );
} elseif (isset($messageText) && strtoupper($messageText) == "HELP") {
    sendMessage($GLOBALS['title'] . ".  You can search for meetings by entering a City, County or Postal Code, or even a Full Address.  (Note: Distances, unless a precise location, will be estimates.)");
    sendMessage("If you start your search with `vm` and then location it will return virtual meetings displayed in your local timezone.  Example: vm Asheboro, NC");
    sendMessage("By default, results for today will show up.  You can adjust this setting using the menu below.");
    sendMessage("Recently Facebook removed the Quick Location button.  We are searching for an alternative approach to make your search experience better.");
    sendMessage("Not finding results close by?  It's likely that your community is not yet covered by the BMLT (https://doihavethebmlt.org).  Send an email to help@bmlt.app to find out how to get the BMLT in your community.");
} elseif (isset($messageText) && strtoupper($messageText) == "📞 HELPLINE") {
    $coordinates = json_decode( $messaging['message']['quick_reply']['payload'] )->coordinates;
    if ($coordinates != null) {
        sendServiceBodyCoverage($coordinates);
    } else {
        sendMessage("Enter a location, and then resubmit your request.", $coordinates);
    }
} else {
    sendMeetingResults($coordinates, getMeetingResults($coordinates, $settings));
    setState($senderId, StateDataType::LOCATION, json_encode($coordinates));
}

function sendServiceBodyCoverage($coordinates) {
    $service_body = getServiceBodyCoverage($coordinates->latitude, $coordinates->longitude);
    if ($service_body != null) {
        sendMessage("Covered by: " . $service_body->name . ", their phone number is: " . explode("|", $service_body->helpline)[0], $coordinates);
    } else {
        sendMessage("Cannot find Helpline coverage in the BMLT.  Join the BMLT Facebook Group and ask how to get this working.  https://www.facebook.com/BMLT-656690394722060/", $coordinates);
    }
}

function getSavedCoordinates($sender_id) {
    $location = getState($sender_id, StateDataType::LOCATION);
    if ($location != null) {
        return json_decode($location);
    } else {
        return null;
    }
}

function doIHaveTheBMLTChecker($results) {
    return round($results[0]['distance_in_miles']) < 100;
}

function sendMeetingResults($coordinates, $results) {
    if ($coordinates->latitude !== null && $coordinates->longitude !== null) {
        $map_payload = [];
        for ($i = 0; $i < count($results); $i++) {
            sendMessage($results[$i]['message'],
                $coordinates,
                count($results));

            array_push($map_payload, [
                "latitude" => $results[$i]['latitude'],
                "longitude" => $results[$i]['longitude'],
                "distance" => $results[$i]['distance'],
                "raw_data" => $results[$i]['raw_data']
            ]);
        }

        $map_page_url = "https://"
            . $_SERVER['HTTP_HOST'] . "/"
            . str_replace("process", "map", $_SERVER['PHP_SELF'])
            . "?Data=" . base64_encode(json_encode($map_payload))
            . "&Latitude=" . $coordinates->latitude
            . "&Longitude=" . $coordinates->longitude;

        sendButton('Follow-up Actions', 'Results Map', $map_page_url, $coordinates, count($results));

        if (!doIHaveTheBMLTChecker($results)) {
            sendMessage("Your community may not be covered by the BMLT yet.  https://www.doihavethebmlt.org/?latitude=" . $coordinates->latitude . "&longitude=" . $coordinates->longitude);
        }
    } else {
        sendMessage("Location not recognized.  I only recognize City, County or Postal Code.");
    }
}

function sendMessage($message, $coordinates = null, $results_count = 0  ) {
    $quick_replies_payload = quickReplies( $coordinates, $results_count );

    sendBotResponse([
        'recipient' => ['id' => $GLOBALS['senderId']],
        'messaging_type' => 'RESPONSE',
        'message' => [
            'text' => $message,
            'quick_replies' => $quick_replies_payload
        ]
    ]);
}

function sendButton($title, $button_title, $link, $coordinates = null, $results_count = 0  ) {
    $quick_replies_payload = quickReplies( $coordinates, $results_count );

    sendBotResponse([
        'recipient' => ['id' => $GLOBALS['senderId']],
        'messaging_type' => 'RESPONSE',
        'message' => [
            'attachment' => [
                'type' => 'template',
                'payload' => [
                    'template_type' => 'button',
                    'text' => $title,
                    'buttons' => array([
                        'type' => 'web_url',
                        'url' => $link,
                        'title' => $button_title
                    ],[
                        'type' => 'web_url',
                        'url' => sprintf("https://%s/%s", $_SERVER['HTTP_HOST'], str_replace("process", "virtual", $_SERVER['PHP_SELF'])),
                        'title' => 'Virtual Meetings'
                    ])
                ]
            ],
            'quick_replies' => $quick_replies_payload
        ]
    ]);
}

function quickReplies( $coordinates, $results_count ) {
    $quick_replies_payload = array();

    if ( isset( $coordinates ) ) {
        array_push( $quick_replies_payload,
            [
                'content_type' => 'text',
                'title'        => '📞 Helpline',
                'payload'      => json_encode( [
                    'coordinates' => $coordinates
                ] )
            ] );
    }

    if ( $results_count > 0 ) {
        array_push( $quick_replies_payload,
            [
                'content_type' => 'text',
                'title'        => 'More Results',
                'payload'      => json_encode( [
                    'results_start' => $results_count + 1,
                    'coordinates'   => $coordinates
                ] )
            ] );
    }

    array_push( $quick_replies_payload,
        [
            'content_type' => 'text',
            'title'        => 'Help',
            'payload'      => 'help'
        ]);

    return $quick_replies_payload;
}

function sendBotResponse($payload) {
    post('https://graph.facebook.com/v5.0/me/messages?access_token=' . $GLOBALS['fbmessenger_accesstoken'], $payload);
}

function handleJFtLanguageSelection($jftLanguages, $selectedLanguage) {
    $selectedLanguage = $jftLanguages[$selectedLanguage] ?? JFTLanguage::English;
    $settings = new JFTSettings($selectedLanguage);
    $instance = JFT::getInstance($settings);
    $entry = $instance->fetch();
    $entryTxt = recursiveToString($entry->withoutTags());
    sendMessage( $entryTxt );
}

function sendJftLanguageOptions($languages) {
    $quickReplies = [];
    foreach ($languages as $language) {
        $quickReplies[] = [
            "content_type" => "text",
            "title" => ucfirst($language),
            "payload" => strtolower($language)
        ];
    }

    sendBotResponse([
        'recipient' => ['id' => $GLOBALS['senderId']],
        'messaging_type' => 'RESPONSE',
        'message' => [
            'text' => 'Please select a language:',
            'quick_replies' => $quickReplies
        ]
    ]);
}

function recursiveToString($value) {
    if (is_array($value)) {
        $result = '';
        foreach ($value as $item) {
            $result .= recursiveToString($item);
        }
        return $result;
    } else {
        return $value . "\n\n";
    }
}
