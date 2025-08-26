<?php
require __DIR__ . '/vendor/autoload.php';

use Clarifai\API\V2\ClarifaiClient;

// Replace with your Personal Access Token from Clarifai
$PAT = "d6bd9dff52754476a794a2f06cb42659";  

// Initialize client
$client = new ClarifaiClient($PAT);

// Use the general image recognition model
$model_id = "general-image-recognition";

// Example: predict from an image URL
$response = $client->predict($model_id, "https://samples.clarifai.com/metro-north.jpg");

if ($response->status()->code() === 10000) {
    foreach ($response->outputs()[0]->data()->concepts() as $concept) {
        echo $concept->name() . " (" . $concept->value() . ")\n";
    }
} else {
    echo "Error: " . $response->status()->description() . "\n";
}
