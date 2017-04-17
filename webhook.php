<?php

require_once('config.php');

/**
 * JSON data is POSTed directly, not as a parameter. Retrieve it and decode it.
 */
$_POST = json_decode(file_get_contents('php://input'), true);
//$_POST = json_decode(file_get_contents('jsontest.js'), true);


/**
 * If there was an error parsing the JSON, we should probably bail here.
 */
if (json_last_error() !== JSON_ERROR_NONE)
    leave();


/**
 * A simple check to see if the JSON data is structured correctly.
 */
if (!isset($_POST['result']) || empty($_POST['result']))
    leave();


/**
 * Get the result object from our JSON. It contains the information we need.
 */
$result = $_POST['result'];

/**
 * Bail out if an action was requested that isn't supported by this webhook.
 */
switch ($result['action']) {
    case 'ESV_Passage':
        break;
    default:
        leave();
}


/**
 * Handle the bb action
 */
$pattern = '/^(?:!)?(?:(bb)|(biblebot)|(bible)|(esv)|(kjv)\\s+)/i';

$query = preg_replace($pattern, '', trim($result['resolvedQuery']));
$query = preg_replace('/\s+/', '+', $query);

//$pattern = /(?:(?:[123]|I{1,3})\s*)?(?:[A-Z][a-zA-Z]+|Song of Songs|Song of Solomon).?\s*(?:1?[0-9]?[0-9]):\s*\d{1,3}(?:[,-]\s*\d{1,3})*(?:;\s*(?:(?:[123]|I{1,3})\s*)?(?:[A-Z][a-zA-Z]+|Song of Songs|Song of Solomon)?.?\s*(?:1?[0-9]?[0-9]):\s*\d{1,3}(?:[,-]\s*\d{1,3})*)*/i;


// Web service URL
$url = ESV_BASEURL . "/passageQuery?key=" . ESV_KEY . "&passage={$query}&include-headings=false&output-format=plain-text";

// Set up CURL
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// Execute the POST request
$data = curl_exec($ch);

// Close the connection
curl_close($ch);

// Parse the response
$text = preg_replace('/^=+\\n/i', '', $data);
$text = preg_replace('/_+/i', '_______________', $data);
$text = preg_replace('/=+/i', '===============', $data);

$speech = $text;
$displayText = $text;


/**
 * Format a webhook response object to be returned by the webhook.
 */
$webhook = new stdClass();
$webhook->speech = $speech;
$webhook->displayText = $displayText;
//$webhook->data = new stdClass();
//$webhook->data->contextOut = Array(
//        new stdClass()
//);
$webhook->source = 'apiai-openbible-bot';


/**
 * Send the response.
 */
header('Content-type: application/json;charset=utf-8');
echo json_encode($webhook);

exit();

function leave() {
    $webhook = new stdClass();
    $webhook->speech = 'Webhook ended prematurely.';
    $webhook->displayText = 'Webhook ended prematurely.';
    $webhook->source = 'apiai-openbible-bot';
    header('Content-type: application/json;charset=utf-8');
    echo json_encode($webhook);
    exit();
}

//EOF