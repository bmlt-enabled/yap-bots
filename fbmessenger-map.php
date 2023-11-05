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
        var mylocation = {lat: <?php echo $_REQUEST['Latitude']?>, lng: <?php echo $_REQUEST['Longitude']?>};
        var map = new google.maps.Map(document.getElementById('map'), {
            center: mylocation
        });

        var locations = [];

        <?php
            $data_points = json_decode(base64_decode($_REQUEST['Data']));
        foreach ($data_points as $data_point) {
            $label = "";
            $row = 1;

            foreach ($data_point->raw_data as $result) {
                if ($row == 1) {
                    $label .= "<b>" . $result . "</b><br/>";
                } else {
                    $label .= $result . "<br/>";
                }

                $row++;
            }
            $label .= $data_point->distance . "<br/>";
            $label .= "<a href='https://google.com/maps?q=" . $data_point->latitude . "," . $data_point->longitude . "'>Open</a>"
            ?>

                addMarker({lat: <?php echo $data_point->latitude?>, lng: <?php echo $data_point->longitude?>}, map, "<?php echo $label?>", "red");
                locations.push(new google.maps.LatLng(<?php echo $data_point->latitude?>, <?php echo $data_point->longitude?>));
            <?php
        }
        ?>

        addMarker(mylocation, map, "You Are Here", "blue");
        autoZoom(locations, map);
    }

    function addMarker(location, map, content, icon_color) {
        var marker = new google.maps.Marker({
            position: location,
            icon: "https://maps.google.com/mapfiles/ms/icons/" + icon_color + "-dot.png",
            map: map,
            title: content,
            animation: google.maps.Animation.DROP});

        marker.addListener('click', function() {
            new google.maps.InfoWindow({
                content: content
            }).open(map, marker);
        });
    }

    function autoZoom(locations, map) {
        var bounds = new google.maps.LatLngBounds();
        for (var i = 0, locations_length = locations.length; i < locations_length; i++) {
            bounds.extend(locations[i]);
        }

        map.fitBounds(bounds);
    }
</script>
<script async defer
        src="https://maps.googleapis.com/maps/api/js?key=<?php echo $cs_google_maps_api_key?>&callback=initMap">
</script>
</body>
</html>