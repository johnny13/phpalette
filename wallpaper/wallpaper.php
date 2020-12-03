#!/usr/bin/php
<?php
/**
 * Wallpaper Generator - creates wallpapers based on randomness.
 *
 * @package Wallpapers
 * @author Andrew Woods
 */
require "palette.php";
require "point.php";
require "base-wallpaper.php";
require "dots-wallpaper.php";
require "lines-wallpaper.php";
require "web-wallpaper.php";

$prog     = array_shift($argv);
$style    = array_shift($argv);
$filename = array_shift($argv);

if ( ! $filename ){
	echo "No filename specified.";
	help();
	exit(0);
}

define('two_4', pow(2,4)); // 16
define('two_5', pow(2,5)); // 32
define('two_6', pow(2,6)); // 64
define('two_7', pow(2,7)); // 128
define('two_8', pow(2,8)); // 256
define('two_9', pow(2,9)); // 512
define('two_10', pow(2,10)); // 1024

// dimensions is pixels for a Macbook Pro
$width  = 2560;
$height = 1600;

// Create an image
$bgpaper = imagecreate($width, $height);
$palette = new Palette( $bgpaper );

switch ($style)
{
case 'dots':
	$dots = new DotsWallpaper( $bgpaper, $width, $height );
	$dots->addPalette( $palette );
	$dots->generate( $filename );
	break;

case 'lines':
	$lines = new LinesWallpaper( $bgpaper, $width, $height );
	$lines->addPalette( $palette );
	$lines->generate( $filename );
	break;

case 'vpoint':
	$vPoint = new VanishingPointWallpaper( $bgpaper, $width, $height );
	$vPoint->addPalette( $palette );
	$vPoint->setInterval( 64 );
	$vPoint->generate( $filename );
	break;

default:
	echo 'you must specify a style of wallpaper';
	exit(0);
	stuff;
}



/*------------------------------------------------------------------------------
 *	Functions
 *------------------------------------------------------------------------------
 */

function help()
{
?>
	Help
	================================================================================
	$ wallpaper.php (dots|lines|vpoint) filename.jpg

<?php
}

