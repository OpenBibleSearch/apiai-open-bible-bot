<?php

require_once('config.php');

/**
 * Define character limit for messages
 */
define("CHAR_LIMIT", 2000);

/**
 * Define text for truncated messages
 */
define("TRUNCATED", " (message length exceeded)");

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
    leave(json_last_error());


/**
 * A simple check to see if the JSON data is structured correctly.
 */
if (!isset($_POST['session']) || empty($_POST['session']))
    leave("Invalid JSON");


/**
 * Get the result object from our JSON. It contains the information we need.
 */
$result = $_POST['queryResult'];


/**
 * Bail out if an action was requested that isn't supported by this webhook.
 */
switch ($result['action']) {
    case 'ESV_Passage':
    case 'ESV_VOTD':
    case 'ESV_ReadingPlan':
    case 'ESV_Listen':
    case 'Strong_Lookup':
        break;
    default:
        leave("Action does not exist");
}
$webhook = null;


/**
 * Handle the ESV_Passage action
 */
if ($result['action'] == 'ESV_Passage') {
    $pattern = '/^(?:!)?(?:(bb)|(biblebot)|(bible)|(esv)|(kjv)\\s+)/i';

    // The machine learning sometimes gets it wrong. Bail out if the query doesn't match anything
    if (!preg_match($pattern, trim($result['queryText']))) {
        exit();
    }

    $query = preg_replace($pattern, '', trim($result['queryText']));
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
        //$text = $data;
        $response = json_decode($data);
        if (count($response->passages) > 1) {
            $text = join($response->passages, '\n\n');
        } else {
            $text = $response->passages[0];
        }
    }

    // truncate strings longer than 2000 characters...
    error_log("message length: " . strlen($text));
    if (strlen($text) > CHAR_LIMIT) {
        $text = substr($text, 0, CHAR_LIMIT - strlen(TRUNCATED)) . TRUNCATED;
    }
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
}


/**
 * Handle the Strong_Lookup action
 */
if ($result['action'] == 'Strong_Lookup') {
    $pattern = '/^(?:!)?(?:strongs ([H|G]\d+))/i';

    // The machine learning sometimes gets it wrong. Bail out if the query doesn't match anything
    if (!preg_match($pattern, trim($result['queryText']), $matches)) {
        exit();
    }

    //$query = preg_replace($pattern, '', trim($result['queryText']));
    //$query = preg_replace('/\s+/', '+', $query);
    $entry = strtoupper($matches[1]);
    $filename = $entry .  '.json';

    if (!file_exists('/app/entries/' . $filename)) {
        $text = "Hmm, I can't find " . $entry;
    } else {
        // Get the file contents
        $json = file_get_contents('/app/entries/' . $filename);

        $data = json_decode($json, TRUE);

        $derivation = $data['derivation'];
        $lemma = $data['lemma'];
        $kjv_def = $data['kjv_def'];
        $strongs_def = $data['strongs_def'];

        $text = $entry . '  ' . $lemma . "\n\n" . $derivation . "\n\n" . $strongs_def;
    }

    // truncate strings longer than 2000 characters...
    error_log("message length: " . strlen($text));
    if (strlen($text) > CHAR_LIMIT) {
        $text = substr($text, 0, CHAR_LIMIT - strlen(TRUNCATED)) . TRUNCATED;
    }

    error_log($text);
}


/**
 * Format a webhook response object to be returned by the webhook.
 */
$webhook = new stdClass();
$webhook->fulfillmentText = $text;
$webhook->source = 'apiai-openbible-bot';


/**
 * Send the response.
 */
header('Content-type: application/json;charset=utf-8');
echo json_encode($webhook);

exit();

function leave($text="Webhook ended prematurely") {
    $webhook = new stdClass();
    $webhook->fulfillmentText = $text;
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
