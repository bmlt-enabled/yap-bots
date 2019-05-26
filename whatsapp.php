<?php
require_once 'functions.php';
header("content-type: text/xml");
echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>";?>
<Response>
<?php
$input = json_decode(file_get_contents('php://input'), true);
if (isset($_REQUEST['Body'])) {
    $message = $_REQUEST['Body'];
    $coordinates = getCoordinatesForAddress($message);
    $results = getMeetingResults($coordinates);
    foreach ($results as $result) {
        echo "<Message>" . $result['message'] . "</Message>";
    }
} else {
    echo "<Message>Please specify an address or location.</Message>";
}
?>
</Response>
