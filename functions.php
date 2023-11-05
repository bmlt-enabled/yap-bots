<?php

include_once 'config.php';
include_once 'database.php';
static $days_of_the_week = [1 => "Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];
$google_maps_endpoint = "https://maps.googleapis.com/maps/api/geocode/json?key=" . trim($ss_google_maps_api_key);
$timezone_lookup_endpoint = "https://maps.googleapis.com/maps/api/timezone/json?key=" . trim($ss_google_maps_api_key);
static $state_expiry_seconds = 1200;

class Coordinates
{
    public $location;
    public $latitude;
    public $longitude;
}

class DataOutputType
{
    const JSON = "json";
    const JSONP = "jsonp";
    const KML = "kml";
    const CSV = "csv";
    const POI_CSV = "poi";
}

class StateDataType
{
    const DAY = "day";
    const LOCATION = "location";
}

function getState($senderId, $type)
{
    $db = new Database();
    $db->query(sprintf("SELECT data, timestampdiff(SECOND, timestamp, current_timestamp) as timediff FROM state_%s where senderId = :senderId and timestampdiff(SECOND, timestamp, current_timestamp) <= %s ORDER BY timestamp DESC LIMIT 1;", $type, $GLOBALS['state_expiry_seconds']));
    $db->bind(':senderId', $senderId);
    $resultset = $db->single();
    $db->close();
    return $resultset['data'];
}

function setState($senderId, $type, $data)
{
    $db = new Database();
    $stmt = sprintf("INSERT INTO `state_%s` (`senderId`,`data`,`timestamp`) VALUES (:senderId, :data, :timestamp)", $type);
    $db->query($stmt);
    $db->bind(':senderId', $senderId);
    $db->bind(':data', $data);
    date_default_timezone_set('UTC');
    $db->bind(':timestamp', gmdate("Y-m-d H:i:s"));
    $db->execute();
    $db->close();
}

function getResultsString($filtered_list)
{
    $additional_info_array = [];

    if ($GLOBALS['virtual']) {
        $additional_info = str_replace("tel:", "", str_replace(" ", "", $filtered_list->comments));
    } else {
        if ($filtered_list->location_text != "") {
            $additional_info_array[] = $filtered_list->location_text;
        }
        if ($filtered_list->location_info != "") {
            $additional_info_array[] = $filtered_list->location_info;
        }
        $additional_info = trim(implode(" / ", $additional_info_array));
    }

    $response = [];

    $response[] = str_replace("&", "&amp;", $filtered_list->meeting_name);
    $response[] = str_replace("&", "&amp;", $GLOBALS['days_of_the_week'][$filtered_list->weekday_tinyint]
        . ' ' . (new DateTime($filtered_list->start_time))->format('g:i A T'));

    if ($additional_info != null) {
        $response[] = $additional_info;
    }

    if (!$GLOBALS['virtual']) {
        $response[] = str_replace("&", "&amp;", $filtered_list->location_street
            . ($filtered_list->location_municipality !== "" ? " " . $filtered_list->location_municipality : "")
            . ($filtered_list->location_province !== "" ? ", " . $filtered_list->location_province : ""));
    }

    return $response;
}

function getTimeZoneForCoordinates($latitude, $longitude)
{
    $time_zone = get($GLOBALS['timezone_lookup_endpoint'] . "&location=" . $latitude . "," . $longitude . "&timestamp=" . time());
    return json_decode($time_zone);
}

function setTimeZoneForLatitudeAndLongitude($latitude, $longitude)
{
    $time_zone_results = getTimeZoneForCoordinates($latitude, $longitude);
    date_default_timezone_set($time_zone_results->timeZoneId);
}

function getMeetingResults($coordinates, $settings = null, $results_start = 0)
{
    setTimeZoneForLatitudeAndLongitude($coordinates->latitude, $coordinates->longitude);
    try {
        $results_count = ($GLOBALS['result_count_max'] ?? 10) + $results_start;

        $today = null;
        $tomorrow = null;
        if ($settings != null) {
            if ($today == null) {
                $today = (new DateTime($settings->set_day))->format('w') + 1;
            }
            if ($tomorrow == null) {
                $tomorrow = (new DateTime($settings->set_day))->modify('+1 day')->format('w') + 1;
            }
        }

        $meeting_results = getMeetings($coordinates->latitude, $coordinates->longitude, $results_count, $today, $tomorrow);
    } catch (Exception $e) {
        error_log($e);
        exit;
    }

    $filtered_list = $meeting_results->filteredList;
    $data = [];

    for ($i = $results_start; $i < $results_count; $i++) {
        $results = getResultsString($filtered_list[$i]);
        $distance_string = "(" . round($filtered_list[$i]->distance_in_miles) . " mi / " . round($filtered_list[$i]->distance_in_km) . " km)";

        $message = implode("\n", $results) . (!$GLOBALS['virtual'] ? "\n" . $distance_string : "");

        $data[] = [
            "latitude" => $filtered_list[$i]->latitude,
            "longitude" => $filtered_list[$i]->longitude,
            "distance" => $distance_string,
            "distance_in_miles" => $filtered_list[$i]->distance_in_miles,
            "raw_data" => $results,
            "message" => $message];
    }

    return $data;
}

function getCoordinatesForAddress($address)
{
    $coordinates = new Coordinates();

    if (strlen($address) > 0) {
        $map_details_response = get($GLOBALS['google_maps_endpoint']
                                    . "&address="
                                    . urlencode($address)
                                    . "&components=" . urlencode($GLOBALS['location_lookup_bias']));
        $map_details = json_decode($map_details_response);
        if (count($map_details->results) > 0) {
            $coordinates->location  = $map_details->results[0]->formatted_address;
            $geometry               = $map_details->results[0]->geometry->location;
            $coordinates->latitude  = $geometry->lat;
            $coordinates->longitude = $geometry->lng;
        }
    }

    return $coordinates;
}

function getMeetings($latitude, $longitude, $results_count, $today, $tomorrow)
{
    return json_decode(get(getMeetingsUrl($latitude, $longitude, $results_count, $today, $tomorrow)));
}

function getMeetingsUrl($latitude, $longitude, $results_count, $today, $tomorrow, $format = DataOutputType::JSON)
{
    return sprintf(
        "%s/api/getMeetings.php?latitude=%s&longitude=%s&results_count=%s&today=%s&tomorrow=%s&format=%s",
        ($GLOBALS['virtual'] ? "https://vphone.bmltenabled.org" : $GLOBALS['yap_url']),
        $latitude,
        $longitude,
        $results_count,
        $today,
        $tomorrow,
        $format
    );
}

function getServiceBodyCoverage($latitude, $longitude)
{
    return json_decode(get(sprintf("%s/api/getServiceBodyCoverage.php?latitude=%s&longitude=%s", $GLOBALS['yap_url'], $latitude, $longitude)));
}

function get($url)
{
    error_log($url);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0) +yap');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $data = curl_exec($ch);
    $errorno = curl_errno($ch);
    curl_close($ch);
    if ($errorno > 0) {
        throw new Exception(curl_strerror($errorno));
    }

    return $data;
}

function post($url, $payload, $is_json = true)
{
    error_log($url);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $post_field_count = $is_json ? 1 : substr_count($payload, '=');
    curl_setopt($ch, CURLOPT_POST, $post_field_count);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $is_json ? json_encode($payload) : $payload);
    if ($is_json) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0) +yap");
    $data = curl_exec($ch);
    error_Log($data);
    $errorno = curl_errno($ch);
    curl_close($ch);
    if ($errorno > 0) {
        throw new Exception(curl_strerror($errorno));
    }
    return $data;
}


function recursiveToString($value)
{
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

// Don't split in middle of word
function splitMessage($message, $limit)
{
    $messageChunks = [];
    $currentChunk = '';
    $words = explode(' ', $message);

    foreach ($words as $word) {
        $wordLength = strlen($word);

        if (strlen($currentChunk) + $wordLength + 1 <= $limit) {
            // add word and space to current chunk
            $currentChunk .= ($currentChunk ? ' ' : '') . $word;
        } else {
            // save current chunk and start new one
            $messageChunks[] = $currentChunk;
            $currentChunk = $word;
        }
    }

    // add last chunk, if any
    if (!empty($currentChunk)) {
        $messageChunks[] = $currentChunk;
    }

    return $messageChunks;
}
