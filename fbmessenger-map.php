<?php
include_once 'config.php';
include_once 'functions.php';?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="initial-scale=1.0, user-scalable=no">
    <meta charset="utf-8">
    <title>Meetings for <?php echo $_REQUEST['Latitude']?>, <?php echo $_REQUEST['Longitude']?></title>
    <style>
        html, body {
            padding: 0;
            margin: 0;
            height: 100%;
        }
        #map {
            height: 100%;
        }
    </style>
</head>
<body>
<div id="map"></div>
<script>
    function initMap() {
        var mylocation = {lat: <?php echo $_REQUEST['Latitude']?>, lng: <?php echo $_REQUEST['Longitude']?>};                   // passed users location
        var map = new google.maps.Map(document.getElementById('map'), {
            center: mylocation,                                                   // Center on users location
            zoom: 9                                                               // zoom level
        });

        <?php
            $data_points = json_decode(base64_decode($_REQUEST['Data']));
            foreach ($data_points as $data_point) {
                $label = "";
                foreach ($data_point->results as $result) {
                    $label .= $result . "<br/>";
                }
                $label .= $data_point->distance . "<br/>";
                $label .= "<a href='https://google.com/maps?q=" . $data_point->latitude . "," . $data_point->longitude . "'>Open</a>"
                ?>
                addMarker({lat: <?php echo $data_point->latitude?>, lng: <?php echo $data_point->longitude?>}, map, "<?php echo $label?>");
        <?php
            }
        ?>

        addMarker(mylocation, map, "You Are Here")
    }

    function addMarker(location, map, content) {
        var marker = new google.maps.Marker({position: location, map: map, title: content, animation: google.maps.Animation.DROP}); // Marker for users location

        marker.addListener('click', function() {
            new google.maps.InfoWindow({
                content: content
            }).open(map, marker);
        });
    }
</script>
<script async defer
        src="https://maps.googleapis.com/maps/api/js?key=<?php echo $cs_google_maps_api_key?>&callback=initMap">
</script>
</body>
</html>