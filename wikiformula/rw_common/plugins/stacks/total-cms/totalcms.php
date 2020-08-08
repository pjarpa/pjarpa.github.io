<?php
if (!ini_get('date.timezone')) date_default_timezone_set('Europe/London');
error_reporting(E_ALL);

if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
  $_SERVER['REMOTE_ADDR'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
}

require_once('vendor/autoload.php');
require_once('autoload.php');

use TotalCMS\Component\Alt;
use TotalCMS\Component\Blog;
use TotalCMS\Component\Component;
use TotalCMS\Component\DataStore;
use TotalCMS\Component\Date;
use TotalCMS\Component\Depot;
use TotalCMS\Component\Feed;
use TotalCMS\Component\File;
use TotalCMS\Component\Gallery;
use TotalCMS\Component\HipDepot;
use TotalCMS\Component\HipGallery;
use TotalCMS\Component\Image;
use TotalCMS\Component\Ratings;
use TotalCMS\Component\Text;
use TotalCMS\Component\Toggle;
use TotalCMS\Component\Video;
use TotalCMS\ReplaceText;
