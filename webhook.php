<?php

require_once('config.php');

/**
 * Override ESV_API key with environment variable if it exists
 */
$ESV_KEY = getenv('ESV_KEY', ESV_KEY);
$headers[] = 'Authorization: Token ' . $ESV_KEY;

/**
 * Override REBRANDLY_API key with environment variable if it exists
 */
$REBRANDLY_KEY = getenv('REBRANDLY_KEY', REBRANDLY_KEY);

/**
 * Define ESVAPI.org Endpoints
 */
define('ESVAPI_PASSAGE', ESV_BASEURL . 'passage/text/');

/**
 * JSON data is POSTed directly, not as a parameter. Retrieve it and decode it.
 */
if (ini_get('always_populate_raw_post_data') === false) {
    ini_set('always_populate_raw_post_data', '-1');
}
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
 * Log the request for debugging
 */
//error_log(print_r($_POST['result']));

/**
 * Bail out if an action was requested that isn't supported by this webhook.
 */
switch ($result['action']) {
    case 'ESV_Passage':
    case 'ESV_VOTD':
    case 'ESV_ReadingPlan':
    case 'ESV_Listen':
        break;
    default:
        leave();
}
$webhook = null;


/**
 * Handle the ESV_Passage action
 */
if ($result['action'] == 'ESV_Passage') {
    $pattern = '/^(?:!)?(?:(bb)|(biblebot)|(bible)|(esv)|(kjv)\\s+)/i';

    // The machine learning sometimes gets it wrong. Bail out if the query doesn't match anything
    if (!preg_match($pattern, trim($result['resolvedQuery']))) {
        exit();
    }

    $query = preg_replace($pattern, '', trim($result['resolvedQuery']));
    $query = preg_replace('/\s+/', '+', $query);

    //$pattern = /(?:(?:[123]|I{1,3})\s*)?(?:[A-Z][a-zA-Z]+|Song of Songs|Song of Solomon).?\s*(?:1?[0-9]?[0-9]):\s*\d{1,3}(?:[,-]\s*\d{1,3})*(?:;\s*(?:(?:[123]|I{1,3})\s*)?(?:[A-Z][a-zA-Z]+|Song of Songs|Song of Solomon)?.?\s*(?:1?[0-9]?[0-9]):\s*\d{1,3}(?:[,-]\s*\d{1,3})*)*/i;


    // Web service URL
    $url = ESVAPI_PASSAGE . "?q={$query}"
        . "&include-passage-horizontal-lines=false&include-heading-horizontal-lines=false"
        . "&include-headings=false";
    error_log($url);

    // Set up CURL
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Execute the POST request
    $data = curl_exec($ch);

    // Capture any errors that may have occurred
    $error = curl_errno($ch);

    // Close the connection
    curl_close($ch);

    // Parse the response
    if ($error) {
        $text = "Oops! I wasn't able to look that up for you. Please double check your scripture reference.";
    } else {
        $text = $data;
    }


    /**
     * Format a webhook response object to be returned by the webhook.
     */
    $webhook = new stdClass();
    $webhook->speech = $text;
    $webhook->displayText = $text;
    //$webhook->data = new stdClass();
    //$webhook->data->contextOut = Array(
    //        new stdClass()
    //);
    $webhook->source = 'apiai-openbible-bot';
}


/**
 * Handle the ESV_VOTD action
 */
if ($result['action'] == 'ESV_VOTD') {

    // Web service URL
    $url = ESV_BASEURL_V2 . "dailyVerse?key=TEST&include-headings=false&output-format=plain-text"
        . "&include-passage-horizontal-lines=false&include-heading-horizontal-lines=false";

    // Set up CURL
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Execute the request
    $data = curl_exec($ch);

    // Close the connection
    curl_close($ch);

    // Parse the response
    $text = $data;


    /**
     * Format a webhook response object to be returned by the webhook.
     */
    $webhook = new stdClass();
    $webhook->speech = $text;
    $webhook->displayText = $text;
    //$webhook->data = new stdClass();
    //$webhook->data->contextOut = Array(
    //        new stdClass()
    //);
    $webhook->source = 'apiai-openbible-bot';
}


/**
 * Handle the ESV_ReadingPlan action
 */
if ($result['action'] == 'ESV_ReadingPlan') {

    // Today's date (Central Time) in YYYY-MM-DD format
    $date = new DateTime('now', new DateTimeZone('America/Chicago'));
    $today = $date->format('Y-m-d');

    // Web service URL
    $url = ESV_BASEURL_V2 . "readingPlanQuery?key=TEST&date={$today}&reading-plan=through-the-bible";

    $text = $date->format('M j') . ' ';

    // this is much simpler in PHP 7+!
    //$short = shortenWithRebrandly($url) ?? shortenWithShortify($url) ?? $url;

    // but, for legacy versions...
    $short = null;

    if (!shortenWithRebrandly($url, $short)) {
        if (!shortenWithShortify($url, $short)) {
            $short = $url;
        }
    }

    $text .= $short;



    /**
     * Format a webhook response object to be returned by the webhook.
     */
    $webhook = new stdClass();
    $webhook->speech = $text;
    $webhook->displayText = $text;
    //$webhook->data = new stdClass();
    //$webhook->data->contextOut = Array(
    //        new stdClass()
    //);
    $webhook->source = 'apiai-openbible-bot';
}


/**
 * Handle the ESV_Listen action
 */
if ($result['action'] == 'ESV_Listen') {

    $pattern = '/^(?:!)?(?:(listento)\\s+)/i';

    // The machine learning sometimes gets it wrong. Bail out if the query doesn't match anything
    if (!preg_match($pattern, trim($result['resolvedQuery']))) {
        exit();
    }

    $query = preg_replace($pattern, '', trim($result['resolvedQuery']));
    $query = preg_replace('/\s+/', '+', $query);

    // Web service URL
    $url = ESV_BASEURL_V2 . "passageQuery?key=TEST&passage={$query}"
        . "&output-format=mp3";

    // Set up CURL
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Execute the POST request
    $data = curl_exec($ch);

    // Capture any errors that may have occurred
    $error = curl_errno($ch);

    // Close the connection
    curl_close($ch);

    // Parse the response
    if ($error) {
        $text = "Oops! I wasn't able to look that up for you. Please double check your scripture reference.";
    } else {
        $url = $data;
        $short = null;

        if (!shortenWithRebrandly($url, $short)) {
            if (!shortenWithShortify($url, $short)) {
                $short = $url;
            }
        }

        $text = "Listen: " . $short;
    }


    /**
     * Format a webhook response object to be returned by the webhook.
     */
    $webhook = new stdClass();
    $webhook->speech = $text;
    $webhook->displayText = $text;
    //$webhook->data = new stdClass();
    //$webhook->data->contextOut = Array(
    //        new stdClass()
    //);
    $webhook->source = 'apiai-openbible-bot';
}


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


/**
 * Shorten the url with Rebrandly
 */
function shortenWithRebrandly($url, &$short) {
    $json = file_get_contents(REBRANDLY_BASEURL . 'links/new?apikey=' . $REBRANDLY_KEY
        . "&destination={$url}&domain[fullName]=biblebot.click");

    $link = json_decode($json);

    if (strlen($json) > 0 && json_last_error() == JSON_ERROR_NONE) {
        // Success!
        $short = 'https://' . $link->shortUrl;

        return true;
    }

    return false;
}


/**
 * Shorten the url with shortify
 */
function shortenWithShortify($url, &$short) {
    $short = file_get_contents('http://jd.ax/api/url/shorten/?url=' . $url);

    if (substr($short, 0, 1) == 1) {
        // Success!
        $short = substr($short, 2);

        return true;
    }

    return false;
}

//EOF
