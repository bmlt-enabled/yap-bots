<?php
include 'functions.php';

if (isset($_REQUEST['hub_verify_token']) && $_REQUEST['hub_verify_token'] === $GLOBALS['fbmessenger_verifytoken']) {
    echo $_REQUEST['hub_challenge'];
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
error_log(json_encode($input));
async_post("https://".$_SERVER['HTTP_HOST'].
           str_replace("gateway", "process", $_SERVER['PHP_SELF']), $input);
