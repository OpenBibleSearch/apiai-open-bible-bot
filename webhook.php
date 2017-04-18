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
    case 'ESV_VOTD':
    case 'ESV_ReadingPlan':
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

    $query = preg_replace($pattern, '', trim($result['resolvedQuery']));
    $query = preg_replace('/\s+/', '+', $query);

    //$pattern = /(?:(?:[123]|I{1,3})\s*)?(?:[A-Z][a-zA-Z]+|Song of Songs|Song of Solomon).?\s*(?:1?[0-9]?[0-9]):\s*\d{1,3}(?:[,-]\s*\d{1,3})*(?:;\s*(?:(?:[123]|I{1,3})\s*)?(?:[A-Z][a-zA-Z]+|Song of Songs|Song of Solomon)?.?\s*(?:1?[0-9]?[0-9]):\s*\d{1,3}(?:[,-]\s*\d{1,3})*)*/i;


    // Web service URL
    $url = ESV_BASEURL . "passageQuery?key=" . ESV_KEY . "&passage={$query}"
        . "&include-passage-horizontal-lines=false&include-heading-horizontal-lines=false"
        . "&include-headings=false&output-format=plain-text";

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
 * Handle the ESV_VOTD action
 */
if ($result['action'] == 'ESV_VOTD') {

    // Web service URL
    $url = ESV_BASEURL . "dailyVerse?key=" . ESV_KEY . "&include-headings=false&output-format=plain-text"
        . "&include-passage-horizontal-lines=false&include-heading-horizontal-lines=false";

    // Set up CURL
    $ch = curl_init($url);
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
    $url = ESV_BASEURL . "readingPlanQuery?key=IP&date={$today}&reading-plan=through-the-bible";


    // /**
    //  * Shorten the url with shortify
    //  */
    // $short = file_get_contents('http://jd.ax/api/url/shorten/?url=' . $url);

    // if (substr($short, 0, 1) == 1) {
    //     // Success!
    //     $text = $date->format('M j') . ' ' . substr($short, 2);
    // } else {
    //     // Fail! Fall back to the full url.
    //     $text = $url;
    // }


    /**
     * Shorten the url with Rebrandly
     */
    $json = file_get_contents('https://api.rebrandly.com/v1/links/new?apikey=' . REBRANDLY_BASEURL
        . '&destination={$url}&domain[fullName]=biblebot.click');

    $link = json_decode($json);

    if (strlen($json) > 0 && json_last_error() == JSON_ERROR_NONE) {
        // Success!
        $text = $date->format('M j') . ' ' . $link['shortUrl'];
    } else {
        // Fail! Fall back to the full url.
        $text = $url;
    }


    // /**
    //  * Shorten the url with rebrandly
    //  */
    // $post_data['destination'] = $url;
    // $post_data['slashtag'] = 'A_NEW_SLASHTAG';
    // $post_data['title'] = 'Daily Reading Plan';

    // // Set up CURL
    // $ch = curl_init("https://api.rebrandly.com/v1/links");
    // curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    //     "apikey: YOUR_API_KEY",
    //     "Content-Type: application/json"
    // ));
    // curl_setopt($ch, CURLOPT_POST, 1);
    // curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    // curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
    
    // // Execute the POST request
    // $data = curl_exec($ch);
    
    // // Close the connection
    // curl_close($ch);
    
    // // Parse the response
    // $response = json_decode($data, true);
    // $short = $response["shortUrl"];


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

//EOF