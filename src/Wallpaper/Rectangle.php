<?php

declare(strict_types=1);

namespace Wallpaper;

class Rectangle
{

    const OUTDIR       = 'out/walls/';
    const THUMBDIR     = 'out/wall_thumbs/';
    const WALLWIDTH    = 1920;
    const WALLHEIGHT   = 1080;
    const THUMBWIDTH   = 800;
    const THUMBHEIGHT  = 600;

    //
    // ROUNDED RECTANGLES WALLPAPER
    //

    //
    // Compute Padding on either side
    public function computeRectSidePad($total_r)
    {
        if ($total_r > 8) {
            $side_pads = 2;
        } else if ($total_r >= 6) {
            $side_pads = 4;
        } else if ($total_r >= 4) {
            $side_pads = 6;
        } else {
            $side_pads = 8;
        }

        return $side_pads;
    }

    //
    // Compute Rectangles Sizes
    public function compRect($allColors, $rPos = false)
    {
        $totalColors         = count($allColors);
        $blank_sides         = $this->computeRectSidePad($totalColors);
        $rectangles          = intval($blank_sides + $totalColors);
        $indiv_rect_width    = intval(round(self::WALLWIDTH / $rectangles), 2);
        $blank_start_x       = intval(($blank_sides * 0.5) * $indiv_rect_width);
        $padding             = intval(round($indiv_rect_width * 0.45));

        // Vertical Positioning (optionally randomized)
        $blank_start_y       = intval(self::WALLHEIGHT * 0.20);
        $indiv_rect_height   = intval(round(self::WALLHEIGHT - ($blank_start_y * 2)));
        $blank_stop_y        = intval($blank_start_y + $indiv_rect_height);

        $cD = array(
            "width"    => $indiv_rect_width,
            "height"   => $indiv_rect_height,
            "total"    => $totalColors,
            "colors"   => $allColors,
            "padding"  => $padding,
            "start_x"  => $blank_start_x,
            "start_y"  => $blank_start_y,
            "stop_y"   => $blank_stop_y
        );

        if ($rPos !== false) {
            //outputLog("Random Mode Enabled!");

            $min_start_y = intval(self::WALLHEIGHT * 0.10);                      // 1000 * .1 = 100
            $max_start_y = intval(self::WALLHEIGHT * 0.20);                      // 1000 * .2 = 200
            $min_height  = intval(round(self::WALLHEIGHT - ($max_start_y * 2))); // 1000 - (200 * 2) = 400
            $max_height  = intval(round(self::WALLHEIGHT - ($min_start_y * 2))); // 1000 - (100 * 2) = 800
            $min_stop_y  = intval(self::WALLHEIGHT * 0.80);                      // 100 + 400 = 500
            $max_stop_y  = intval(self::WALLHEIGHT * 0.90);                      // 200 + 800 = 1000

            $cD["min_start_y"]  = $min_start_y;
            $cD["max_start_y"]  = $max_start_y;
            $cD["min_stop_y"]   = $min_stop_y;
            $cD["max_stop_y"]   = $max_stop_y;
            $cD["min_height"]   = $min_height;
            $cD["max_height"]   = $max_height;
        }

        return $cD;
    }

    //
    // Helper function that draws a single rounded rectangle
    public function drawRoundedRectangle($image, $rd)
    {
        $image->roundedRectangle(
            $rd["start_x"],
            $rd["start_y"],
            $rd["stop_x"],
            $rd["stop_y"],
            $rd["roundness"],
            $rd["rgb_color"],
            $rd["style"]
        );
    }

    //
    // Loop through each of the colors and draw a colored rectangle 
    public function roundedLoop($image, $rData, $rPos = false)
    {
        $count  = 1;
        $next_x = 0;

        foreach ($rData["colors"] as $color) {
            $irData = array();

            if ($count === 1)
                $irData["start_x"] = $rData["start_x"];
            else
                $irData["start_x"] = $next_x;

            if ($rPos === false) {
                $irData["start_y"]   = $rData["start_y"];
                $irData["stop_x"]    = intval($irData["start_x"] + ($rData["width"] - 10));
                $irData["stop_y"]    = $rData["stop_y"];
            } else {
                //outputLog("min_max_y| ".$rData["min_start_y"]."__".$rData["max_start_y"]);
                $irData["start_y"]   = intval(rand($rData["min_start_y"], $rData["max_start_y"]));
                $irData["stop_x"]    = intval($irData["start_x"] + ($rData["width"] - 10));
                $irData["stop_y"]    = intval(rand($rData["min_stop_y"], $rData["max_stop_y"]));
            }

            $irData["roundness"] = $rData["padding"];
            $irData["rgb_color"] = $color;
            $irData["style"]     = "filled";

            $this->drawRoundedRectangle($image, $irData);

            $next_x = intval($rData["start_x"] + intval($rData["width"] * $count));
            $count++;
        }
    }

    //
    // Main logic function to generate a rounded rectangle wallpaper
    public function makeRectangle($image, $theme = false, $theme_type = false, $shuffle = false, $texture = false, $randomPositions = false)
    {
        if ($theme_type === false || $theme_type === "base16") {
            $allColors   = loadThemeFile("base16", $shuffle, $theme);
        } else {
            $allColors   = "";
            //outputLog("ERROR. ALTERNATIVE THEME STYLES NOT SETUP.");
            cliexit("04480");
        }

        $bgColor     = $allColors["background"];
        $tclrs       = count($allColors["colors"]);

        // Make a blank image to draw on
        $image
            ->fromNew(self::WALLWIDTH, self::WALLHEIGHT, $bgColor)
            ->autoOrient();


        // Get the Data setup based on amount of colors
        $r_data = $this->compRect($allColors["colors"], $randomPositions);


        // Now we Draw on the blank image
        //outputLog("randomPositions".$randomPositions);
        $this->roundedLoop($image, $r_data, $randomPositions);


        // Optional Texture Overlay
        // if ($texture !== false) {
        //     if ($texture === true) $sendData = true;
        //     else $sendData = $texture;

        //     $t_file = new Raster();
        //     $overlay = $t_file->makeRandomTextureOverlay($sendData);

        //     outputLog("Adding Texture Overlay.............");

        //     $image->overlay($overlay, "center", 0.25);
        // }


        // Save Final Wallpaper Image
        $string_prefix = "recty_";
        if ($randomPositions === "random") $string_prefix = "randyrecty_";
        $string = $string_prefix . "__" . $allColors["theme"] . "__";

        $result = saveNewWallpaper($image, $string);

        return $result;
    }
}
