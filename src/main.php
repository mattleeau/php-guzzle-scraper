<?php
namespace Scrape;

require_once __DIR__ . '/../vendor/autoload.php';

$data = Scrape::run('http://archive-grbj-2.s3-website-us-west-1.amazonaws.com/', 5, 2);

echo '<pre>';
var_dump($data);
echo '</pre>';
