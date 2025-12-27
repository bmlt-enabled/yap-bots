<?php
include_once 'config.php';
include_once 'database.php';

// Fetch all location data from state_location table
$db = new Database();
$db->query("SELECT data, timestamp FROM state_location WHERE data IS NOT NULL ORDER BY timestamp DESC");
$results = $db->resultset();

$locations = [];
$years = [];

foreach ($results as $row) {
    $data = json_decode($row['data'], true);
    if ($data && isset($data['latitude']) && isset($data['longitude'])) {
        $timestamp = $row['timestamp'];
        $year = $timestamp ? date('Y', strtotime($timestamp)) : null;

        if ($year) {
            $years[$year] = true;
        }

        $locations[] = [
            'lat' => (float)$data['latitude'],
            'lng' => (float)$data['longitude'],
            'location' => $data['location'] ?? 'Unknown location',
            'date' => $timestamp ? date('M j, Y g:i A', strtotime($timestamp)) : 'Unknown date',
            'year' => $year
        ];
    }
}

$years = array_keys($years);
rsort($years);
$locationsJson = json_encode($locations);
$yearsJson = json_encode($years);
?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="initial-scale=1.0, user-scalable=no">
    <meta charset="utf-8">
    <title>Historical Query Map</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        html, body {
            height: 100%;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        #map {
            height: 100%;
            width: 100%;
        }
        .controls {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 1000;
            background: white;
            padding: 12px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .control-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .control-group label {
            font-size: 14px;
            font-weight: 500;
            color: #333;
        }
        select {
            padding: 6px 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
        }
        .toggle-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .toggle-btn.markers {
            background: #3388ff;
            color: white;
        }
        .toggle-btn.heatmap {
            background: #ff6b6b;
            color: white;
        }
        .toggle-btn:hover {
            opacity: 0.9;
        }
        .stats {
            font-size: 12px;
            color: #666;
            padding-top: 5px;
            border-top: 1px solid #eee;
        }
    </style>
</head>
<body>
    <div id="map"></div>
    <div class="controls">
        <div class="control-group">
            <label for="yearFilter">Year:</label>
            <select id="yearFilter">
                <option value="all">All Years</option>
            </select>
        </div>
        <div class="control-group">
            <label>View:</label>
            <button id="toggleView" class="toggle-btn markers">Show Heatmap</button>
        </div>
        <div class="stats">
            <span id="count">0</span> locations
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js"></script>
    <script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
    <script>
        const allLocations = <?php echo $locationsJson; ?>;
        const years = <?php echo $yearsJson; ?>;

        // Initialize map centered on US
        const map = L.map('map').setView([39.8283, -98.5795], 4);

        // Add OpenStreetMap tiles
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        // Initialize marker cluster group
        const markers = L.markerClusterGroup({
            chunkedLoading: true,
            maxClusterRadius: 50
        });

        // Initialize heatmap layer (hidden by default)
        let heatLayer = null;
        let showingHeatmap = false;

        // Populate year filter
        const yearFilter = document.getElementById('yearFilter');
        years.forEach(year => {
            const option = document.createElement('option');
            option.value = year;
            option.textContent = year;
            yearFilter.appendChild(option);
        });

        // Filter and display locations
        function updateMap() {
            const selectedYear = yearFilter.value;
            const filteredLocations = selectedYear === 'all'
                ? allLocations
                : allLocations.filter(loc => loc.year === selectedYear);

            // Update count
            document.getElementById('count').textContent = filteredLocations.length;

            // Clear existing layers
            markers.clearLayers();
            if (heatLayer) {
                map.removeLayer(heatLayer);
            }

            // Add markers
            filteredLocations.forEach(loc => {
                const marker = L.marker([loc.lat, loc.lng]);
                marker.bindPopup(`
                    <strong>${loc.location}</strong><br>
                    <small>${loc.date}</small>
                `);
                markers.addLayer(marker);
            });

            // Create heatmap data
            const heatData = filteredLocations.map(loc => [loc.lat, loc.lng, 1]);
            heatLayer = L.heatLayer(heatData, {
                radius: 25,
                blur: 15,
                maxZoom: 10,
                gradient: {0.4: 'blue', 0.6: 'cyan', 0.7: 'lime', 0.8: 'yellow', 1: 'red'}
            });

            // Show appropriate layer
            if (showingHeatmap) {
                map.removeLayer(markers);
                heatLayer.addTo(map);
            } else {
                map.addLayer(markers);
            }

            // Fit bounds if we have locations
            if (filteredLocations.length > 0) {
                const bounds = L.latLngBounds(filteredLocations.map(loc => [loc.lat, loc.lng]));
                map.fitBounds(bounds, { padding: [50, 50] });
            }
        }

        // Toggle between markers and heatmap
        document.getElementById('toggleView').addEventListener('click', function() {
            showingHeatmap = !showingHeatmap;

            if (showingHeatmap) {
                map.removeLayer(markers);
                if (heatLayer) heatLayer.addTo(map);
                this.textContent = 'Show Markers';
                this.className = 'toggle-btn heatmap';
            } else {
                if (heatLayer) map.removeLayer(heatLayer);
                map.addLayer(markers);
                this.textContent = 'Show Heatmap';
                this.className = 'toggle-btn markers';
            }
        });

        // Listen for year filter changes
        yearFilter.addEventListener('change', updateMap);

        // Initial load
        updateMap();
    </script>
</body>
</html>
