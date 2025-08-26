<?php
require __DIR__ . '/vendor/autoload.php';

use Clarifai\API\V2\ClarifaiClient;

$client = new ClarifaiClient('YOUR_API_KEY');

echo "Clarifai client loaded successfully!";