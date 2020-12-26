#!/usr/bin/env php
<?php

/**
 * @abstract This is the main cli file that is called via the command line.
 *           It displays a CLI Menu with selectable options that does a variety
 *           of things, such as Wallpaper generation, wallpaper file management etc.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// CLI HELPERS
use PhpSchool\CliMenu\CliMenu;
use PhpSchool\CliMenu\Builder\CliMenuBuilder;
use PhpSchool\CliMenu\MenuItem\AsciiArtItem;
use PhpSchool\CliMenu\Action\GoBackAction;

use League\CLImate;

// FORMAT HELPERS
use \Dallgoot\Yaml;

// PHP GOES TO ART SCHOOL
use Art\Generate;
use Art\Shapes;

use Wallpaper\Rectangle;
use Wallpaper\Circle;
use Wallpaper\Raster;
use Wallpaper\Generator;

// USED IN THUMBNAIL GENERATION.
// @TODO REMOVE THIS AND USE SIMPLEIMAGE
use Intervention\Image\ImageManager;

// ----------------------------------
// PHP GLOBAL DECLARATIONS
// ----------------------------------
global $climate, $imagine, $imgManager;

$imagine    = new Imagine\Imagick\Imagine();
$imgManager = new ImageManager(array('driver' => 'imagick'));
$climate    = new CLImate\CLImate;

$wp_selected_themes = array();

//
// CLI Functions
//

// Exit w/ error message
function cliexit($enum = "0001")
{
    $climate = new CLImate\CLImate;

    $climate->br();
    $climate->red()->bold()->out("CMD ERROR #RTD" . $enum . "! EXITING");
    $climate->br();
    exit;
    die();
}

// Output Result to Terminal
function outputImg($img)
{
    global $climate;

    cliImgDisplay($img);
    $name = basename($img);
    $climate->out("");
    $climate->green()->out("     Wallpaper saved to: " . $name);
    $climate->out("");
}

// Output message
function outputLog($msg)
{
    global $climate;

    $climate->cyan()->out($msg);
    $climate->br();
}

// Output ascii image + linebreak
function outputHeader($ascii_file = "blasted_sm")
{
    global $climate;

    $climate->addArt('ascii');

    if ($ascii_file === "large") {
        $climate->cyan()->boldDraw('spaceman');
    } else if ($ascii_file === "evil") {
        $climate->red()->boldDraw('skull');
    } else {
        $climate->draw($ascii_file);
    }

    $climate->br();
}

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

// Debug logging helper
function blastedFile($name, $content)
{
    $file = "out/" . $name;

    file_put_contents($file, $content);

    // register_shutdown_function(function() use($file) {
    //     unlink($file);
    // });

    return $file;
}

//
// File system helpers
//

// Compute size of directory
function folderSize($dir)
{
    $size = 0;

    foreach (glob(rtrim($dir, '/') . '/*', GLOB_NOSORT) as $each) {
        $size += is_file($each) ? filesize($each) : folderSize($each);
    }

    return $size;
}

// Print KB MB etc
function humanFilesize($bytes, $dec = 0)
{
    settype($bytes, "string");
    $size   = array('B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
    $factor = floor((strlen($bytes) - 1) / 3);

    return sprintf("%.{$dec}f", $bytes / pow(1024, $factor)) . @$size[$factor];
}

// Clear out Folder Contents
function folderEmptyOut($path)
{
    $path = rtrim($path, "/");
    $files = glob($path . '/*'); // get all file names
    foreach ($files as $file) { // iterate files
        if (is_file($file)) unlink($file);
    }
}

//  Helper for getting relative directory of files as array
function directoryToArray($dir, $typesString = "yaml,yml,YAML,YML")
{
    $files = array();
    $total = 0;
    foreach (glob("./" . $dir . "/*.{" . $typesString . "}", GLOB_BRACE) as $filename) {
        $files[] = $filename;
        $total++;
    }

    return $files;
}


//
// CLI MENU FUNCTIONS
//

function genWallTodoUI($cli, $theme, $theme_type, $wjd, $walllist, $first = false, $final = false)
{
    $cli->clear();
    $cli->br();

    if ($first === false)
        $cli->green()->boldDraw('blasted_colors');
    else
        $cli->green()->bold()->animation('blasted_colors')->enterFrom('top');

    $cli->lightBlue()->bold()->out('  ' . strtoupper($theme_type) . ' THEME: <cyan>' . strtoupper($theme) . '</cyan>');
    $cli->br();

    $padding = $cli->padding(50);

    $UsedCats = array();
    foreach ($wjd["Wallpaper_Types"] as $wpt) {
        foreach ($walllist as $walltxt) {
            $UsedCats[] = strtolower($walltxt["category"]);
        }
    }

    foreach ($wjd["Wallpaper_Types"] as $wpt) {

        if (in_array(strtolower($wpt), $UsedCats)) {
            $cli->cyan()->bold()->out("    " . strtoupper($wpt));
            $cli->cyan()->out("    ====================================");
        }

        foreach ($walllist as $walltxt) {

            if (!isset($walltxt["finished"]))
                $wr = $walltxt["category"];
            else
                $wr = $walltxt["image"];

            if (strtolower($wpt) === strtolower($walltxt["category"])) {
                $padding->label("    " . $walltxt["name"])->result("[" . $wr . "]");
                $cli->br();
            }
        }
    }

    // Generate Wallpaper TODO List
    if ($final === true) {
        $cli->br();
        $cli->green()->bold()->out("    FINISHED! RELOAD IN [15] Seconds...");
        $cli->br();

        sleep(15);
        $wallData = loadWallJSON();
        cliMenuDisplay($wallData);
    }
}

function menuFinalizedGenWallpapers($theme, $theme_type, $shuffle, $texture)
{
    global $climate, $wp_selected_themes;

    // GRAB WALLPAPER DATA FILE
    $wjd = loadWallJSON();

    //
    // CRAZY LOOPAGE
    //
    // FOR EACH USER SELECTED WALLPAPER STYLE,
    // LOOK THROUGH ALL ITEMS IN data.json FOR MATCH.
    // THEN EXECUTE FUNCTION FROM MATCHED ITEMS JSON DATA
    $runtilldat = array();
    foreach ($wp_selected_themes as $runCmd) {
        foreach ($wjd["Wallpapers"] as $wItem) {
            foreach ($wItem as $cat) {
                foreach ($cat as $item) {
                    if ($item["name"] === $runCmd)
                        $runtilldat[] = array("name" => $item["name"], "category" => $item["category"], "function" => $item["function"], "params" => $item["params"]);
                }
            }
        }
    }

    // OUTPUT SOME MENU SHIT
    $climate->addArt('ascii');
    genWallTodoUI($climate, $theme, $theme_type, $wjd, $runtilldat, true, false);

    $progress = $climate->progress()->total(100);
    $progress->current(10);

    // Loop through runtilldat and run each command. 
    // Updating Menu As you do.
    $done = 0;
    $rtd_count = 0;
    while ($done === 0) {

        // Run Each Wallpapers Function
        $runit = runTillData($theme, $theme_type, $shuffle, $texture, $runtilldat, $rtd_count);


        if ($done === 0) {
            $runtilldat = $runit["result_array"];
            genWallTodoUI($climate, $theme, $theme_type, $wjd, $runtilldat, false, false);
        }

        if (is_array($runit) && isset($runit["status"]))
            $done = $runit["status"];

        $progress->current($runit["progress"]);
        $rtd_count++;
        if ($rtd_count > 99) cliexit("991399");
    }

    // Finished Generating Wallpapers. Now Display Results.
    genWallTodoUI($climate, $theme, $theme_type, $wjd, $runtilldat, false, true);
    $progress->current(100);
}

function runTillData($theme, $theme_type, $shuffle, $texture, $funs, $i)
{

    $totalruns = count($funs);
    $totalruns--;

    $varfunk   = $funs[$i]["function"];
    $varparams = $funs[$i]["params"];

    /**
     * @abstract Magic Variable Function Execution.
     *           Function name found in JSON file is called w/ optional params.
     * @link https://www.php.net/manual/en/functions.variable-functions.php
     * @return array Contains resulting image & thumbnail 
     */

    $Jenny = new Generator();
    $result = $Jenny->$varfunk($theme, $theme_type, $shuffle, $texture, $varparams);
    // if ($varparams !== false && $varparams !== "false") {
    //     $result = $Jenny->$varfunk($theme, $theme_type, $shuffle, $texture, $varparams);
    // } else {
    //     $result = $Jenny->$varfunk($theme, $theme_type, $shuffle, $texture);
    // }

    // Progress bar feedback info & Loop controller.
    if ($totalruns === $i) {
        $status = 1;
        $progress = 99;
    } else {
        $status = 0;
        $progress = intval(round(($i / $totalruns) * 90));
    }

    // Return Thumbnail from Variable Function we ran above this.
    if (isset($result["thumbnail"]))
        $funs[$i]["thumbnail"] = $result["thumbnail"];
    else
        $funs[$i]["thumbnail"] = "ERROR. WALLPAPER NOT CREATED";

    // Return Image from Variable Function we ran above this.
    if (isset($result["image"]))
        $funs[$i]["image"] = $result["thumbnail"];
    else
        $funs[$i]["image"] = "ERROR. WALLPAPER NOT CREATED";

    // Set this Wallpaper as Done in the UI.
    $funs[$i]["finished"] = true;

    $final = array("status" => $status, "progress" => $progress, "result_array" => $funs);
    return $final;
}

function cliMenuDisplay($wpapers)
{
    global $wallpapers, $wp_selected_themes, $mainMenu;

    $wallpapers = $wpapers["Wallpapers"];

    $du1 = foldersize("out/walls");
    $du2 = foldersize("out/wall_thumbs");
    $du = round($du1 + $du2);
    $disk_used = humanFilesize($du);

    $art = file_get_contents("./ascii/spaceman.txt");

    // Handels User Selection
    $themeCallable = function (CliMenu $mainMenu) {
        echo $mainMenu->getSelectedItem()->getText();
    };

    $wallpaperCallable  = function (CliMenu $mainMenu) {
        global $wp_selected_themes;

        $xclimate = new League\CLImate\CLImate;
        $msg = $mainMenu->getSelectedItem()->getText();
        $xclimate->magenta()->bold()->out("  SELECTED: " . $msg);
        $wp_selected_themes[] = $msg;
        $result = array_unique($wp_selected_themes);
        $wp_selected_themes = $result;
    };

    $cleanCallable = function (CliMenu $clean) {
        global $climate, $mainMenu;

        $clean->confirm('For sure delete all generated wallpapers?')
            ->display('OK!');

        $dirs = array('out/walls/', 'out/wall_thumbs');
        foreach ($dirs as $dir) { // iterate files
            folderEmptyOut($dir);
        }
        $selectedItem = $mainMenu->getSelectedItem();
        $selectedItem->hideItemExtra();


        $mainMenu->redraw();
    };

    $colorCallable = function (CliMenu $menu) {

        // They have Selected a Base16 Theme and some wallpapers to build.
        // Close the main menu and bring up the Builder TUI. 
        $shuffle = "";
        $texture = "";

        $wp_selected_theme = $menu->getSelectedItem()->getText();
        $menu->close();

        // Set Theme File Info for Wallpaper Generators...
        $content = "base16," . $wp_selected_theme . "," . $shuffle . "," . $texture;
        $name = "colorParams.txt";
        blastedFile($name, $content);

        menuFinalizedGenWallpapers($wp_selected_theme, "base16", $shuffle, $texture);
    };

    // Displays the menu
    $mainMenu = ($builder = new CliMenuBuilder)
        ->addAsciiArt($art, AsciiArtItem::POSITION_CENTER, "  --  BLASTED  --")
        ->setTitleSeparator('nu')
        ->addSubMenu('Generate Wallpapers', function (CliMenuBuilder $b) use ($wallpaperCallable, $colorCallable) {

            global $wallpapers;

            $b->setTitle('Select Wallpaper(s)');
            $b->addLineBreak(' ');

            foreach ($wallpapers as $wItem) {
                foreach ($wItem as $cat) {
                    foreach ($cat as $item) {
                        $b->addCheckboxItem($item["name"], $wallpaperCallable);
                    }
                }
                $b->addLineBreak(' ');
            }

            $b->addSubMenu('Next Step ->', function (CliMenuBuilder $c) use ($colorCallable) {

                $c->setTitle('Select Color Palette')
                    ->addLineBreak(' ')
                    ->addSubMenu('Base16 Theme', function (CliMenuBuilder $d) use ($colorCallable) {

                        $themes = loadAllBaseThemes();

                        $d->setTitle('Available Base16 Themes')
                            ->setTitleSeparator('nu')
                            ->setPadding(2, 4)
                            ->setMarginAuto()
                            ->setForegroundColour('51')
                            ->setBackgroundColour('240');

                        $d->addLineBreak(' ');

                        foreach ($themes as $theme) {
                            $name = basename($theme, ".yaml");
                            $d->addItem($name, $colorCallable);
                        }

                        $d->addLineBreak(' ');
                        $d->addLineBreak(' ');
                    })
                    ->addLineBreak(' ')
                    ->addItem('Manually Enter', function (CliMenu $menu) {
                        echo sprintf('Executing option: %s', $menu->getSelectedItem()->getText());
                    })
                    ->addLineBreak(' ');
            })
                ->addLineBreak(' ');

            $b->addSubMenu('Options', function (CliMenuBuilder $e) {
                $e->setTitle('WALLPAPER OPTIONS')
                    ->addLineBreak(' ')
                    ->addLineBreak(' ')
                    ->addItem('SHUFFLE COLORS? (default: false)', function (CliMenu $menu) {
                        echo sprintf('SETTING OPTION: %s', $menu->getSelectedItem()->getText());
                    })
                    ->addLineBreak(' ')
                    ->addItem('APPLY TEXTURE? (default: false)', function (CliMenu $menu) {
                        echo sprintf('SETTING OPTION: %s', $menu->getSelectedItem()->getText());
                    })
                    ->addLineBreak(' ')
                    ->addLineBreak('-');
            })
                ->addLineBreak(' ');
        })
        ->addItem('Cleanup Wallpaper Folders', $cleanCallable, true)
        ->setItemExtra('[' . $disk_used . ']')
        ->addItem('Add New Theme', $themeCallable)
        ->addLineBreak('-')
        ->addSubMenu('Options', function (CliMenuBuilder $e) {
            $e->setTitle('WALLPAPER OPTIONS')
                ->addLineBreak(' ')
                ->addLineBreak(' ')
                ->addItem('SHUFFLE COLORS? (default: false)', function (CliMenu $menu) {
                    echo sprintf('SETTING OPTION: %s', $menu->getSelectedItem()->getText());
                })
                ->addLineBreak(' ')
                ->addItem('APPLY TEXTURE? (default: false)', function (CliMenu $menu) {
                    echo sprintf('SETTING OPTION: %s', $menu->getSelectedItem()->getText());
                })
                ->addLineBreak(' ')
                ->addLineBreak('-');
        })
        ->setPadding(2, 4)
        ->setMarginAuto()
        ->setForegroundColour('51')
        ->setBackgroundColour('240')
        ->build();

    $mainMenu->open();
}


//
// Image Saving 
//

// Save a SimpleImage to Folder
function saveNewWallpaper($SimpleImage, $nameString, $OUTDIR = "out/walls/")
{

    $THUMBWIDTH = "800";
    $THUMBHEIGHT = "600";

    $r_name = randomTxtString(5);
    $f_name = $nameString . $r_name . '.png';
    $t_name = "out/wall_thumbs/" . $f_name;

    $SimpleImage->toFile($OUTDIR . $f_name, "image/png");

    wallThumbnail($OUTDIR . $f_name, $THUMBWIDTH, $THUMBHEIGHT, $t_name);

    //outputImg($t_name);

    return array("thumbnail" => $t_name, "image" => $OUTDIR . $f_name);
}

//  Generates a smaller thumbnail from large image
function wallThumbnail($wall, $wall_w, $wall_h, $thumb_name)
{
    global $imgManager;

    $scale_w = intval(round($wall_w * .5));
    $scale_h = intval(round($wall_h * .5));

    $image = $imgManager->make($wall)->resize($scale_w, $scale_h);
    $image->save($thumb_name, 60);

    return true;
}

//  Helper function for random filenames
function randomTxtString($limit = 5)
{
    $str = 'aAcCeEfFgGhHkKpPqQrRtTwWxXyYzZ234678';
    $w_name = substr(str_shuffle($str), 0, $limit);

    return $w_name;
}


//
// Theme Color Loading
//

//  Load YAML Theme file
function loadThemeFile($type = "base16", $shuffle = true, $name = false)
{
    $colors = array();
    $file   = "";

    $bgColor = backgroundColor("dark");

    if ($type === "base16") {

        if ($name === false) {
            $themes = array();

            foreach (glob("./colors/*.{yaml,yml,YAML,YML}", GLOB_BRACE) as $filename) {
                $themes[] = $filename;
            }
            shuffle($themes);

            $name = "random";
            $file = $themes[0];
            $display_name = basename($file, "yaml");
        } else {
            $file = "./colors/" . $name . ".yaml";
            $display_name = basename($file, "yaml");
        }

        outputLog("     <green>THEME</green> <light_blue>" . $display_name . "</light_blue>");

        $debug = 0;
        $yaml = Yaml::parseFile($file, 0, $debug);

        $colors[] = $yaml->base08;
        $colors[] = $yaml->base09;
        $colors[] = $yaml->base0A;
        $colors[] = $yaml->base0B;
        $colors[] = $yaml->base0C;
        $colors[] = $yaml->base0D;
        $colors[] = $yaml->base0E;
        $colors[] = $yaml->base0F;

        $bgColor  = $yaml->base00;
    } else {
        outputLog("ERROR! Theme Format not understood! Exiting :(");
        die();
        exit;
    }

    if ($shuffle === true) shuffle($colors);

    $contents = array($display_name, $file, $shuffle, $bgColor);
    $content = implode(" , ", $contents);
    blastedFile("log.txt", $content);
    $final = array("colors" => $colors, "background" => $bgColor, "theme" => $display_name);
    return $final;
}

function loadAllBaseThemes()
{
    $themes = array();

    foreach (glob("./colors/*.{yaml,yml,YAML,YML}", GLOB_BRACE) as $filename) {
        $themes[] = $filename;
    }

    return $themes;
}

function loadWallJSON()
{
    $jfile = file_get_contents("src/data.json");
    $json_wall = json_decode($jfile, true);
    $walls = $json_wall["Wallpapers"];
    $types = $json_wall["Wallpaper_Types"];

    return array("Wallpapers" => $walls, "Wallpaper_Types" => $types);
}

// Default Black or White Background Color
function backgroundColor($type = "dark")
{
    $color = "#0f0f0f";
    if ($type !== "dark") $color = "#f0f0f0";

    return $color;
}


// 
// Other Wallpaper Functions
//

function testAscii()
{
    outputHeader("blasted_colors");
    outputLog("Tested!");
}

function localGen()
{
    $GenWall = new Generate();

    $result = $GenWall->makeFractal();
    return $result;
}

function superGen()
{
    global $climate;

    $GenWall = new Generate();

    $imgFile = "examples/lips.png";
    $html = $GenWall->grabColors($imgFile);

    file_put_contents("out/test.html", $html);
}

function avatarIconGen()
{
    $GenShape = new Shapes();
    $avatar = new LasseRafn\InitialAvatarGenerator\InitialAvatar();
    $image = $avatar->glyph('f6e2')->font('fonts/Font-Awesome-5-Free-Solid-900.otf')->color('#e0e0e0')->background('#4f4f4f')->size(256)->fontSize(0.75)->smooth()->generate();
    return $image->save('examples/avatar.png');
}

function texturePrep()
{
    $GenRaster = new Raster();
    $image = new \claviska\SimpleImage();

    $GenRaster->makeReady($image);
}

function rasterGrungeGen()
{
    $GenWall = new Raster();
    $image = new \claviska\SimpleImage();
    $result = $GenWall->makeGrunge($image);
    return $result;
}

// Turn image into greyscale version (used in textures)
function makeGreyscale()
{
    if (!is_dir("./images/1920x1080/")) {
        if (!mkdir("./images/1920x1080/", 0777, true)) {
            die('Failed to create folders...');
        }
    }

    $image = new \claviska\SimpleImage();

    $dir = "images";
    $typeString = "jpeg,jpg";
    $textures = directoryToArray($dir, $typeString);

    // Run the loop
    foreach ($textures as $img) {

        $bname = basename($img);

        if (file_exists("images/1920x1080/" . $bname)) {
            outPutLog("Already Processed " . $bname);
        } else {

            // Black and WHite Mode
            outputLog("Oldschooling " . basename($img) . " like its Nick@Night");

            $stripped = basename($img, '.jpg');

            $image
                ->fromFile($img)
                ->resize(1920, 1080)
                ->desaturate()
                ->toFile("images/1920x1080/" . $stripped . ".png", 'image/png');
        }
    }

    outputLog("Finished!");
}

// TODO LIST
// @TODO ADD A PREPARE BASE16 THEME FUNCTION THAT REMOVES ANY COMMENTS AND FIXES FILE EXTENSION
// @TODO ADD UI FOR FUNCTION THAT PREPARES RASTER IMAGES USED AS FILTERS
// @TODO ADD ABILITY TO OVERLAY AN SVG IMAGE (IE LOGO) ONTO FINISHED WALLPAPER(S)
// 
// @TODO GENERATE COMPLEX WALLPAPERS THAT TAKE USER INPUT BEFORE CREATION
// @TODO RASTER WALLPAPER IDEA (REPEATING ROW/COLUMNS OF SVG ICON(S))
// 

//
//  MAIN MENU ACTIVATION
//
$wallData = loadWallJSON();
cliMenuDisplay($wallData);

//testAscii();
//
//  Other Wallpaper Functions
//
    // rasterGrungeGen();
    // makeGreyscale();
    // texturePrep();
    // localGen();
    // superGen();
    // avatarIconGen();