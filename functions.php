<?php
include_once 'config.php';
static $days_of_the_week = [1 => "Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];
$google_maps_endpoint = "https://maps.googleapis.com/maps/api/geocode/json?key=" . trim($ss_google_maps_api_key);

class Coordinates {
    public $location;
    public $latitude;
    public $longitude;
}

class DataOutputType {
    const JSON = "json";
    const JSONP = "jsonp";
    const KML = "kml";
    const CSV = "csv";
    const POI_CSV = "poi";
}

function getResultsString($filtered_list) {
    return array(
        str_replace("&", "&amp;", $filtered_list->meeting_name),
        str_replace("&", "&amp;", $GLOBALS['days_of_the_week'][$filtered_list->weekday_tinyint]
                                  . ' ' . (new DateTime($filtered_list->start_time))->format('g:i A')),
        str_replace("&", "&amp;", $filtered_list->location_street
                                  . ($filtered_list->location_municipality !== "" ? " " . $filtered_list->location_municipality : "")
                                  . ($filtered_list->location_province !== "" ? ", " . $filtered_list->location_province : "")));

}

function getCoordinatesForAddress($address) {
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

function getMeetings($latitude, $longitude, $results_count, $today, $tomorrow) {
    return json_decode(get(getMeetingsUrl($latitude, $longitude, $results_count, $today, $tomorrow)));
}

function getMeetingsUrl($latitude, $longitude, $results_count, $today, $tomorrow, $format = DataOutputType::JSON) {
    return sprintf("%s/api/getMeetings.php?latitude=%s&longitude=%s&results_count=%s&today=%s&tomorrow=%s&format=%s",
        $GLOBALS['yap_url'],  $latitude, $longitude, $results_count, $today, $tomorrow, $format);
}

function getServiceBodyCoverage($latitude, $longitude) {
    return json_decode(get(sprintf("%s/api/getServiceBodyCoverage.php?latitude=%s&longitude=%s", $GLOBALS['yap_url'],  $latitude, $longitude)));
}

function get($url) {
    error_log($url);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0) +yap' );
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $data = curl_exec($ch);
    $errorno = curl_errno($ch);
    curl_close($ch);
    if ($errorno > 0) {
        throw new Exception(curl_strerror($errorno));
    }

    return $data;
}

function post($url, $payload, $is_json = true) {
    error_log($url);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    $post_field_count = $is_json ? 1 : substr_count($payload, '=');
    curl_setopt($ch, CURLOPT_POST, $post_field_count);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $is_json ? json_encode($payload) : $payload);
    if ($is_json) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0) +yap" );
    $data = curl_exec($ch);
    $errorno = curl_errno($ch);
    curl_close($ch);
    if ($errorno > 0) {
        throw new Exception(curl_strerror($errorno));
    }
    return $data;
}

function async_post($url, $payload)  {
    error_log($url);
    $parts = parse_url($url);

    if (isset($parts['port'])) {
        $port = $parts['port'];
    } else if ($parts['scheme'] == 'https') {
        $port = 443;
    } else {
        $port = 80;
    }

    $host = ($parts['scheme'] == 'https' ? "ssl://" : "") . $parts['host'];
    $fp = fsockopen($host, $port, $errno, $errstr, 30);
    assert(($fp!=0), "Couldn’t open a socket to ".$url." (".$errstr.")");
    $post_data = json_encode($payload);

    $out = "POST ".$parts['path']." HTTP/1.1\r\n";
    $out.= "Host: ".$parts['host']."\r\n";
    $out.= "User-Agent: Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0) +yap\r\n";
    $out.= "Content-Type: application/json\r\n";
    $out.= "Content-Length: ".strlen($post_data)."\r\n";
    $out.= "Connection: Close\r\n\r\n";
    if (isset($post_data)) $out.= $post_data;

    fwrite($fp, $out);
    fclose($fp);
}
