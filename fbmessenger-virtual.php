<!DOCTYPE html>
<html>
<head>
    <title>Virtual Meetings</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href='https://fonts.googleapis.com/css?family=Droid+Sans' rel='stylesheet' type='text/css'>
    <link rel="stylesheet" type="text/css" href="croutonjs/crouton.min.css" />
    <script src="croutonjs/crouton.js"></script>
    <script type="text/javascript">
        jQuery(function() {
            var crouton = new Crouton({
                root_server: "https://bmlt.virtual-na.org/main_server",
                service_body: [ 4 ],
                parent_service_body: "4",
                recurse_service_bodies: true,
                theme: "sezf",
                template_path: "croutonjs/templates",
                has_zip_codes: "0",
                has_cities: "0",
                has_locations: "0",
                has_languages: "1",
                has_areas: "0",
                base_tz: "UTC",
                auto_tz_adjust: true,
                button_filters: []
            });

            crouton.render();
        })
</script>
</head>

<body>
    <div id="bmlt-tabs"></div>
</body>
</html>
