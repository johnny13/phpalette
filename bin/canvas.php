#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Art\Generate;
use League\CLImate;
use Intervention\Image\ImageManager;
//use Imagine;
use Imagine\Image\Box;
use Imagine\Image\Point;
//use Claviska\SimpleImage;

use \Dallgoot\Yaml;

$imagine = new Imagine\Imagick\Imagine();
$imgManager = new ImageManager(array('driver' => 'imagick'));
$climate = new CLImate\CLImate;

global $climate, $imagine, $imgManager;

//
// CLI Functions
//
function outputImg($img, $name)
{
    global $climate;

    // Output Result to Terminal
    cliImgDisplay($img);
    $climate->green()->out("Wallpaper saved to: " . $name);
}

function outputLog($msg)
{
    global $climate;

    $climate->blue()->out($msg);
    $climate->br();
}

function outputHeader()
{
    global $climate;

    $climate->addArt('ascii');
    $climate->magenta()->boldDraw('huenity_small');
    $climate->br();
}

//
//  Displays an image in the terminal
function cliImgDisplay($imagePath)
{
    // Parse options from command line
    $opts = array_merge([
        'd' => 1,             // Dithering mode : 0 = DITHER_NONE, 1 = DITHER_ERROR
        'f' => $imagePath,
        'r' => 0.25,          // Resize factor 1.0 = 100%
        'w' => 0.5,           // Dither treshold weight
    ], getopt("f:r:w:d:ib"));

    $image = Pixeler\Pixeler::image($opts['f'], $opts['r'], isset($opts['i']), $opts['w'], $opts['d']);

    echo "\r\n" . $image;
}

//
//  Generates a smaller thumbnail from large image
function wallThumbnail($wall, $wall_w, $wall_h, $thumb_name)
{
    global $imgManager;

    $scale_w = intval(round($wall_w * .5));
    $scale_h = intval(round($wall_h * .5));

    outputLog("Creating Wallpaper Thumbnail...");

    $image = $imgManager->make($wall)->resize($scale_w, $scale_h);
    $image->save($thumb_name, 60);

    outputLog($thumb_name);

    return true;
}

//
//  Helper function for random filenames
function randomTxtString($limit = 5)
{
    $str = 'aAcCeEfFgGhHkKpPqQrRtTwWxXyYzZ234678';
    $w_name = substr(str_shuffle($str), 0, $limit);

    return $w_name;
}

//
//  Load YAML Theme file
function loadThemeFile($type = "base16", $shuffle = true)
{
    $colors = array();
    $file   = "";

    $bgColor = backgroundColor("dark");

    if ($type === "base16") {
        $debug = 0;
        $file = '/var/www/html/color/base16/base16-spectrum-generator/phpalette/colors/outrun-dark.yaml';
        $yaml = Yaml::parseFile($file, 0, $debug);

        $colors[] = $yaml->base08;
        $colors[] = $yaml->base09;
        $colors[] = $yaml->base0A;
        $colors[] = $yaml->base0B;
        $colors[] = $yaml->base0C;
        $colors[] = $yaml->base0D;
        $colors[] = $yaml->base0E;
        $colors[] = $yaml->base0F;

        $bgColor = $yaml->base00;
    } else {
        $colors = computeRectangleColors();
    }

    if ($shuffle === true) shuffle($colors);
    //if (count($colors) > 6) array_splice(array_unique($colors), 0, 6);

    $final = array("colors" => $colors, "background" => $bgColor);
    return $final;
}

//
// Default Black or White Background Color
function backgroundColor($type = "dark")
{
    $color = "#0f0f0f";
    if ($type !== "dark") $color = "#f0f0f0";

    return $color;
}


// 
// Circle Wallpaper Functions
//
function compoundCircle($wallpaperImg, $colorPalette, $mainColor, $bgColor)
{
    $finalArray = array();

    //Largest Color Circle
    $circleSize   = rand(100, 400);

    $bgRingSize   = rand(10, 45);
    $circleSizeBG = round($circleSize - $bgRingSize);

    $centerMaxSz  = intval(round($circleSizeBG - $bgRingSize));
    if ($centerMaxSz < $bgRingSize) $centerMaxSz = intval(round($bgRingSize * 2));
    $smRingSize   = rand($bgRingSize, $centerMaxSz);
    if ($smRingSize < 20) $smRingSize = rand(20, $centerMaxSz);

    // Generate Uniqe position for Circle
    $limitX     = intval(round(1920 - $circleSize));
    $circSpot_x = rand(0, $limitX); // 100px padding. 1920px max

    $limitY     = intval(round(1080 - $circleSize));
    $circSpot_y = rand(0, $limitY);

    // $spots      = circleLocation($circleSize);
    // $circSpot_x = $spots["spotX"];
    // $circSpot_y = $spots["spotY"];

    //Main Circle
    $wallpaperImg->draw()
        ->ellipse(
            new Point($circSpot_x, $circSpot_y),
            new Box($circleSize, $circleSize),
            $colorPalette->color($mainColor),
            true
        );

    //Inner bgColor Circle
    $smXPos = round($circSpot_x + ($bgRingSize * 0.115));
    $smYPos = round($circSpot_y + ($bgRingSize * 0.115));
    $wallpaperImg->draw()
        ->ellipse(
            new Point($smXPos, $smYPos),
            new Box($circleSizeBG, $circleSizeBG),
            $colorPalette->color($bgColor),
            true
        );

    outputLog("MC:" . $circleSize . "px @ X:" . $circSpot_x . " Y:" . $circSpot_y);
    outputLog("BC:" . $circleSizeBG . "px @ X:" . $smXPos . " Y:" . $smYPos);

    //Center Circle
    $smstXPos = intval(round($smXPos + ($bgRingSize * 0.115)));
    $smstYPos = intval(round($smYPos + ($bgRingSize * 0.115)));
    $wallpaperImg->draw()
        ->ellipse(
            new Point($smstXPos, $smstYPos),
            new Box($smRingSize, $smRingSize),
            $colorPalette->color($mainColor),
            true
        );

    $finalArray["size"] = $circleSize;
    $finalArray["area"] = array("x" => $circSpot_x, "y" => $circSpot_y);

    return $finalArray;
}

function randomCircleData($cSize)
{

    global $paperWidth, $paperHeight;

    $cData = array();

    $paperWidthMax = intval(round($paperWidth - 100));
    $paperHeightMax = intval(round($paperHeight - 40));

    $cData["limitX"] = intval(round($paperWidthMax - $cSize));
    $cData["spotX"]  = rand(100, $cData["limitX"]); // 100px padding. 1920px max
    $cData["limitY"] = intval(round($paperHeightMax - $cSize));
    $cData["spotY"]  = rand(40, $cData["limitY"]);

    return $cData;
}

function circleLocation($cSize)
{
    global $allSizes;

    if (count($allSizes) >= 1) {

        $acceptable = false;
        $logicPass  = 0;

        while ($acceptable !== true) {

            $circLoc = randomCircleData($cSize);

            // If $circLoc[spotX] is in the range of X's 
            // from known sizes in $allSizes, redo.
            if ($circLoc["spotX"] === $allSizes["acceptableRange"]) {
                $acceptable = true;
            }

            $logicPass++;
            if ($logicPass > 50) $acceptable = true;
        }
    } else {
        $circLoc = randomCircleData($cSize);
    }

    $allSizes[] = array("x" => $circLoc["spotX"], "y" => $circLoc["spotY"], "size" => $cSize);
    return $circLoc;
}

function themeCircle($wallpaperImg, $colorPalette, $mainColor, $circleSize, $counter)
{
    global $paperWidth, $paperHeight, $circSpot_x;
    $padding = 100;

    if ($counter === 1) {
        $circSpot_x = 100;
    } else {
        $circSpot_x = intval(round(($circleSize * $circSpot_x)));
    }

    $circSpot_y = intval(round(($paperHeight * 0.5)));

    $wallpaperImg->draw()
        ->ellipse(
            new Point($circSpot_x, $circSpot_y),
            new Box($circleSize, $circleSize),
            $colorPalette->color($mainColor),
            true
        );

    $fA = array("x" => $circSpot_x, "y" => $circSpot_y, "c" => $mainColor);
    return $fA;
}

function circleWallpaper()
{
    global $imagine, $paperWidth, $paperHeight;

    $paperWidth       = 1920;
    $paperHeight      = 1080;

    $allColors   = loadThemeFile("base16", false);

    outputLog("Creating Imagine Image....");

    $palette = new Imagine\Image\Palette\RGB();

    $image   = $imagine->create(new Box($paperWidth, $paperHeight), $palette->color($allColors["background"]));

    // Generate Compound Circle for each color in palette
    $maxCs = count($allColors["colors"]);
    $curCs = 1;
    $paperWidthMax  = intval(round($paperWidth - 200));

    $circleSize     = intval(round($paperWidthMax / $maxCs));

    outputLog(count($allColors["colors"]) . " circles @ " . $circleSize) . "px each";

    foreach ($allColors["colors"] as $themeColor) {
        $tC = themeCircle($image, $palette, $themeColor, $circleSize, $curCs);
        outputLog($tC["x"] . " " . $tC["y"] . " " . $curCs);
        $curCs++;
    }

    // Name & Save Image
    $w_name = randomTxtString(6);
    $f_name = 'circ_' . $w_name . '.png';
    $image->save('out/walls/' . $f_name);

    // Thumbnail Img
    $thumbPath = 'out/wall_thumbs/' . $f_name;
    wallThumbnail('out/walls/' . $f_name, 800, 600, $thumbPath);

    // Output Result to Terminal
    outputImg($thumbPath, $f_name);
}


// 
// Fractal Wallpaper Functions
//
function localGen()
{
    $wallpaper = new Generate();

    outputLog("Creating Fractal Wallpaper.....");
    $result = $wallpaper->makeFractal();
    outputLog($result);
}


//
// ROUNDED RECTANGLES WALLPAPER
//
function computeRectSidePad($total_r)
{
    $side_pads = 4;

    if ($total_r >= 6) {
        $side_pads = 4;
    } else if ($total_r < 6 && $total_r >= 4) {
        $side_pads = 6;
    } else {
        $side_pads = 8;
    }

    return $side_pads;
}

function computeRectangles($width, $height, $padding, $total_r)
{
    $rect_pad = intval(round($padding * 2));         // total padding per rectangle (1 for each side)
    $side_pad = computeRectSidePad($total_r);        // dynamic number of empty sections on each side

    $total_s  = intval(round($total_r + $side_pad)); // ie 6 shapes + 4 empty sections

    $s_width  = intval(round($width / $total_s));    // width of each section
    $start_x  = intval(round($s_width * 2));         // start x coordinate

    $r_width  = intval(round($s_width - $rect_pad));  // width of each rectangle (section width - total padding)

    $y_pad    = intval(round($s_width * 1));
    $r_height = intval(round($height - ($y_pad * 2)));

    return array(
        "rect_pad" => $rect_pad,
        "side_pad" => $side_pad,
        "total_s"  => $total_s,
        "s_width"  => $s_width,
        "start_x"  => $start_x,
        "y_pad"    => $y_pad,
        "r_height" => $r_height,
        "r_width"  => $r_width
    );
}

function computeRectangleColors()
{
    $colors = array();
    $colors[] = "#05688f";
    $colors[] = "#24799e";
    $colors[] = "#535275";
    $colors[] = "#02c59b";
    $colors[] = "#ffd166";
    $colors[] = "#540d6e";
    $colors[] = "#1ae8ff";
    $colors[] = "#8236ec";

    shuffle($colors);
    return $colors;
}

function makeRectangleWall()
{
    global $climate;

    $width       = 1920;
    $height      = 1080;

    $allColors   = loadThemeFile("base16", false);

    $padding     = (12 - count($allColors["colors"]));
    $total_r     = count($allColors["colors"]);
    $bgColor     = $allColors["background"];

    // Build Rectangles
    $r_data           = computeRectangles($width, $height, $padding, $total_r);
    $r_data["colors"] = $allColors["colors"];
    $roundness        = intval(round($r_data["r_width"] * 0.5));

    // Log message
    outputLog("Starting Rectangle Rendering...");

    $image = new \claviska\SimpleImage();
    $image
        ->fromNew(1920, 1080, $bgColor)
        ->autoOrient();

    $current_x = 0;
    for ($x = 0; $x < $total_r; $x++) {

        $current_x = intval(round($current_x + $padding + $padding + $r_data["r_width"]));
        if ($x === 0) $current_x = intval(round($r_data["start_x"] + $padding));

        $current_x_stop = $current_x + $r_data["r_width"];
        $current_y_stop = $r_data["y_pad"] + $r_data["r_height"];

        $image->roundedRectangle(
            $current_x,
            $r_data["y_pad"],
            $current_x_stop,
            $current_y_stop,
            $roundness,
            $r_data["colors"][$x],
            'filled'
        );
    }

    $rs        = randomTxtString(5);
    $w_name    = "rr_" . $rs . ".png";
    $imagePath = 'out/walls/' . $w_name;
    $thumbPath = 'out/wall_thumbs/' . $w_name;

    // Save resulting image
    $image->toFile($imagePath, 'image/png', 100);

    // Create a thumbnail for whatever
    wallThumbnail($imagePath, $width, $height, $thumbPath);

    // Output resulting image to terminal
    outputImg($thumbPath, $w_name);
}


//
//  ACTION CENTER
//

outputHeader();
//localGen();
//imgManagerDrawing();

//print_r(loadThemeFile());

// Partially Works
// Needs more options and coolness added
circleWallpaper();

// Generate rounded rectangle wallpaper based on color palette.
// Displays the resulting image on the command line. 
// makeRectangleWall();
