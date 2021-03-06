<?php

declare(strict_types=1);

namespace Wallpaper;

use Imagine\Image;
use Imagine\Image\Palette\RGB;
use Imagine\Image\Box;
use Imagine\Image\Point;

/**
 * Circle
 * @abstract This class builds out Circle Themed wallpapers. There are a number of different templates some with a few circles some with quite a few circles. Some classes can be thrown an optional parameter to randomize the color &/or location of the circles.
 *
 * @todo The function "uniqueX" needs to do a better job of checking if a RANGE of values is present not a specific point.
 * @todo The function "circleLocation" checks if the newly generated value is between two values from an array of previously used positions. It should do a better job and loop through the array instead of taking the min and max values from the array.
 *
 */
class Circle
{

    const OUTDIR       = 'out/walls/';
    const THUMBDIR     = 'out/wall_thumbs/';
    const WALLWIDTH    = 1920;
    const WALLHEIGHT   = 1080;
    const THUMBWIDTH   = 800;
    const THUMBHEIGHT  = 600;
    const BORDERSIZE   = 40;
    const BORDERMID    = 150;
    const BORDERIN     = 50;
    const BBMOVE       = 250;
    const STACKMIN     = 600;
    const STACKMAX     = 750;
    const SMALLGAP     = 5;
    const INLINESPACE  = 200;

    protected $mainSize;
    protected $circSpot_x;
    protected $circSpot_y;
    protected $dirMove;
    protected $allSizes;
    protected $xRangers;

    //
    // HELPER FUNCTIONS
    //

    // General Save & Thumbnail Function
    public function saveCircleWallpaper($wallpaperImage, $nameString)
    {
        $w_name = randomTxtString(5);
        $f_name = $nameString . $w_name . '.png';

        $wallpaperImage->save(self::OUTDIR . $f_name);

        wallThumbnail(self::OUTDIR . $f_name, self::THUMBWIDTH, self::THUMBHEIGHT, self::THUMBDIR . $f_name);
        outputImg(self::THUMBDIR . $f_name);

        return array("image" => self::OUTDIR . $f_name, "thumbnail" => self::THUMBDIR . $f_name);
    }

    // For given params, draw colored circle to image
    public function drawColoredCircle($wallpaperImage, $wallpaperPalette, $cX, $cY, $cSize, $cColor, $fill = true)
    {
        $wallpaperImage->draw()
            ->ellipse(
                new Point($cX, $cY),
                new Box($cSize, $cSize),
                $wallpaperPalette->color($cColor),
                $fill
            );
    }

    // 
    // Display Functions
    // Draw some kind of Circle(s) onto the Wallpaper
    //
    public function stackedCircle($wImg, $colorPalette, $color, $Counter, $X, $Y, $fill)
    {
        if ($Counter < 0) {
            $S = intval(self::STACKMIN);
        } else {
            $multiplier = "1." . $Counter;
            $multitwo = "0." . $Counter;
            settype($multiplier, "float");
            settype($multitwo, "float");
            $S = intval((self::STACKMAX * $multiplier) + (self::STACKMAX * $multitwo));
        }

        $this->drawColoredCircle($wImg, $colorPalette, $X, $Y, $S, $color, $fill);
    }

    // Generate random data for compound circles
    public function randomCircleData($cSize)
    {
        $cData = array();
        $cData["spotX"]  = rand(0, self::WALLWIDTH);
        $cData["spotY"]  = rand(0, self::WALLHEIGHT);

        return $cData;
    }

    /**
     * circleLocation
     *
     * @param [type] $cSize Int How big of circle we are making
     * @return object of coordinates to plot on grid
     * @todo do a better job of not returning used up areas, to avoid overlapping
     */
    public function circleLocation($cSize)
    {
        //global $allSizes;
        //outputLog("Circle Location. Size:" . $cSize);

        if (is_array($this->allSizes) && count($this->allSizes) >= 1) {

            $acceptable = false;
            $logicPass  = 0;

            while ($acceptable !== true) {

                $circLoc = $this->randomCircleData($cSize);
                asort($this->xRangers);
                $min = array_values($this->xRangers)[0];
                $max = end($this->xRangers);
                $tempRanges = range($min, $max);

                if (!in_array($circLoc["spotX"], $tempRanges)) {
                    $acceptable = true;
                    //outputLog("min" . $min . " max" . $max . " x" . $circLoc["spotX"]);
                }

                $logicPass++;
                if ($logicPass > 50 && $acceptable !== true) {
                    outputLog("Limit Reached");
                    outputLog($min . " " . $max);
                    $acceptable = true;
                    $this->xRangers = array();
                    $this->xRangers[] = $min;
                    $this->xRangers[] = $max;
                    $logicPass = 0;
                }
            }
        } else {
            $circLoc = $this->randomCircleData($cSize);
        }

        $this->allSizes[] = array("x" => $circLoc["spotX"], "y" => $circLoc["spotY"], "size" => $cSize);
        $this->usedUpRange($cSize, $circLoc["spotX"]);
        return $circLoc;
    }

    // Store compound circle's location to avoid overlapping
    public function usedUpRange($size, $xPoint)
    {
        $halfSize = intval(round($size * 0.5));
        $minPoint = intval($xPoint - $halfSize);
        $maxPoint = intval($xPoint + $halfSize);

        if ($minPoint <= 0)
            $minPoint = intval($halfSize);

        if ($maxPoint >= intval(self::WALLWIDTH))
            $maxPoint = intval(self::WALLWIDTH - $halfSize);

        $this->xRangers[] = $minPoint;
        $this->xRangers[] = $maxPoint;
    }

    // CircleInline Helper Function
    // Prints the circle based on previous circle position
    public function inlineCircle($wImg, $colorPalette, $mainColor, $circleSize, $counter)
    {
        if ($counter === 1)
            $this->circSpot_x = self::INLINESPACE;
        else
            $this->circSpot_x = intval(round(($circleSize + $this->circSpot_x)));

        $circSpot_y = intval(round((self::WALLHEIGHT * 0.5)));
        $modSize = intval(round($circleSize - self::SMALLGAP));

        $this->drawColoredCircle($wImg, $colorPalette, $this->circSpot_x, $circSpot_y, $modSize, $mainColor);

        $fA = array("x" => $this->circSpot_x, "y" => $circSpot_y, "c" => $mainColor);
        return $fA;
    }

    // CircleBurst Positioning 
    // Generate "SLIGHTLY" random X & Y postions
    public function randomOffCenterXY($counter, $centerX, $centerY, $tcs, $baseAmount)
    {

        if ($counter <= -1) {
            $offXMin = intval(round($centerX - $baseAmount));
            $offXMax = intval(round($centerX + $baseAmount));
            $offYMin = intval(round($centerY - $baseAmount));
            $offYMax = intval(round($centerY + $baseAmount));

            $randCenterX = intval(rand($offXMin, $offXMax));
            $randCenterY = intval(rand($offYMin, $offYMax));
        } else {
            // Additional Passes
            $extra    = intval($tcs * ($counter * $counter));
            $extraPos = intval($baseAmount + $extra);
            $extraNeg = intval(intval(-1 * $baseAmount) + intval(-1 * $extra));

            $randCenterX = intval($centerX + rand($extraNeg, $extraPos));
            $randCenterY = intval($centerY + rand($extraNeg, $extraPos));
        }

        $positions = array("x" => $randCenterX, "y" => $randCenterY);

        return $positions;
    }

    // CircleBurst Main Helper Function
    // Creates a set of Circles relative in size to Wallpaper & Total amount of circles to build
    public function borderBurstCircles($bbP = array(), $tcs)
    {
        // Compute Circle Size
        // First Circle size is relative to Wallpaper size / half of total colors
        // Additional Circles are relative [%]PERCENT of primary circle size
        if ($bbP["counter"] === 1) {
            $cSize = intval(round(rand(intval(self::WALLWIDTH * 0.25), intval(self::WALLWIDTH * 0.75))));
            $this->mainSize = $cSize;
        } else {
            $cSize = intval(round($this->mainSize * (($tcs - $bbP["counter"]) * .1)));
        }

        // Error checking min size & set min size

        $testSize = intval(round(self::WALLWIDTH * 0.13));
        if ($cSize < $testSize) $cSize = $testSize;
        //if ($bbP["counter"] === 1) $this->mainSize = $cSize;

        //outputLog("MainSize:" . $this->mainSize . " Count: " . $bbP["counter"]);

        // Background Outline Size 5% larger than circle size
        $borderSize = intval(round(self::BORDERSIZE + $cSize));

        $bInSize    = intval(round($this->mainSize * (($tcs - $bbP["counter"]) * .1)));
        $cInSize    = intval(round($bInSize * (($tcs - $bbP["counter"]) * .1)));

        if ($bInSize < self::BORDERMID) $bInSize = self::BORDERMID;
        if ($cInSize < self::BORDERIN) $cInSize = self::BORDERIN;

        // Compute Circle Location
        $spots = $this->circleLocation($cSize);
        $cX = $spots["spotX"];
        $cY = $spots["spotY"];

        // Add Border Circle to Wallpaper
        $this->drawColoredCircle($bbP["wImg"], $bbP["cPal"], $cX, $cY, $borderSize, $bbP["bgColor"]);

        // Add Theme Color Circle to Wallpaper
        $this->drawColoredCircle($bbP["wImg"], $bbP["cPal"], $cX, $cY, $cSize, $bbP["currentColor"]);

        $rChance = rand(1, 5);
        if ($rChance !== 1) {
            $this->drawColoredCircle($bbP["wImg"], $bbP["cPal"], $cX, $cY, $bInSize, $bbP["bgColor"]);
            $this->drawColoredCircle($bbP["wImg"], $bbP["cPal"], $cX, $cY, $cInSize, $bbP["currentColor"]);
        }

        $fA = array("x" => $cX, "y" => $cY);
        return $fA;
    }

    public function borderBurstLoop($cParams, $tfc, $tcs, $loopMax = 3)
    {

        $colorLimit = 0;
        $loopCount  = 0;
        $burstMax   = 4;
        $gCount = 0;
        $curCs = 0;

        //while ($loopCount < $loopMax) {}

        if ($colorLimit >= $tcs) {
            $colorLimit = 0;
            $loopCount++;
        }

        $burstCount = 0;
        //$cParams["currentColor"] = $tfc["colors"][$colorLimit];



        foreach ($tfc["colors"] as $color) {
            //while ($burstCount < $burstMax) {

            $lastX = intval(rand(0, self::WALLWIDTH));
            $lastY = intval(rand(0, self::WALLHEIGHT));

            $cParams["currentColor"] = $color;
            $cParams["counter"] = $curCs;
            $cParams["centerX"] = $lastX;
            $cParams["centerY"] = $lastY;

            $results = $this->borderBurstCircles($cParams, $tcs);
            $lastX   = $results["x"];
            $lastY   = $results["y"];

            $burstCount++;
            $curCs++;

            //outputLog("COLOR: " . $color . " Global Count: " . $gCount);
            $gCount++;
        }

        $curCs = 1;
        $lastX = rand(intval(self::WALLWIDTH * 0.10), intval(self::WALLWIDTH * 0.90));
        $lastY = rand(intval(self::WALLHEIGHT * 0.25), intval(self::WALLHEIGHT * 0.75));

        $colorLimit++;
    }

    public function computeMovement($size)
    {

        $loc   = array();
        $qsize = intval(1.5 * $size);
        $psize = intval(intval(rand(1, 4)) * $size);

        //$moveVal = intval(round($size + intval($size * intval(0.1 * rand(1, 3)))));
        //$wiggle  = intval(rand(-250, 250));
        $locCheck = false;
        $lcC = 0;
        while ($locCheck === false) {
            $moveVal = intval(rand(intval(-1 * $psize), $psize));
            $wiggle  = intval(rand(intval(-1 * $qsize), $qsize));

            //outputLog("Moving:" . $this->dirMove);

            switch ($this->dirMove) {
                case "up":
                    $loc["x"] = intval($this->circSpot_x + $wiggle);
                    $loc["y"] = intval(round($this->circSpot_y + $moveVal));
                    break;
                case "down":
                    $loc["x"] = intval($this->circSpot_x - $wiggle);
                    $loc["y"] = intval(round($this->circSpot_y - $moveVal));
                    break;
                case "left":
                    $loc["x"] = intval(round($this->circSpot_x - $moveVal));
                    $loc["y"] = intval($this->circSpot_y - $wiggle);
                    break;
                case "right":
                    $loc["x"] = intval(round($this->circSpot_x + $moveVal));
                    $loc["y"] = intval($this->circSpot_y + $wiggle);
                    break;
            }

            if ((($loc["x"] > 0) && ($loc["x"] < self::WALLWIDTH)) && (($loc["y"] > 0) && ($loc["y"] < self::WALLHEIGHT)))
                $locCheck = true;

            $lcC++;
            if ($lcC > 50) {
                $locCheck = true;
            }
        }


        $directions = array("up", "down", "left", "right");
        shuffle($directions);
        $this->dirMove = $directions[0];

        //outputLog("Moved x@" . $loc["x"] . " y@" . $loc["y"]);
        return $loc;
    }

    public function compCircLoc($count, $size)
    {
        $loc = array();

        if ($count === 1) {
            $loc["x"] = intval(rand(intval(self::WALLWIDTH * 0.15), intval(self::WALLWIDTH * 0.85)));
            $loc["y"] = intval(rand(intval(self::WALLHEIGHT * 0.15), intval(self::WALLHEIGHT * 0.85)));

            $directions = array("up", "down", "left", "right");
            shuffle($directions);
            $this->dirMove = $directions[0];
        } else {

            $moveCheck = false;
            $mcC = 0;

            while ($moveCheck === false) {
                $lastMove = $this->dirMove;
                $loc = $this->computeMovement($size);
                $sSize = intval($size * 1.2);

                if ($lastMove === "up" && $loc["x"] > intval($this->circSpot_x + $sSize))
                    $moveCheck = true;

                if ($lastMove === "down" && $loc["x"] < intval($this->circSpot_x - $sSize))
                    $moveCheck = true;

                if ($lastMove === "left" && $loc["y"] > intval($this->circSpot_y + $sSize))
                    $moveCheck = true;

                if ($lastMove === "right" && $loc["y"] < intval($this->circSpot_y - $sSize))
                    $moveCheck = true;

                $mcC++;
                //outputLog("dir: " . $lastMove . " size: " . $sSize . " x: " . $loc["x"] . " y: " . $loc["y"]);
                if ($mcC > 50) {
                    $moveCheck = true;
                    outputLog(" ");
                    outputLog("Move  Limit!");
                    outputLog(" ");
                    $loc["x"] = intval(rand(0, self::WALLWIDTH));
                    $loc["y"] = intval(rand(0, self::WALLHEIGHT));
                }
            }
        }

        $this->circSpot_x = $loc["x"];
        $this->circSpot_y = $loc["y"];
        return $loc;
    }

    public function compCircSizes($count)
    {
        $sizes = array();

        $multiplier = "0.1" . $count;
        settype($multiplier, "float");

        $mSize = round(floatval($multiplier * self::WALLWIDTH));
        if ($count === 1) $this->mainSize = $mSize;

        $borderSize = intval($mSize + self::BORDERSIZE);

        $sizeCheck = false;
        $sCC = 0;
        while ($sizeCheck === false) {
            $rOne = rand(self::BORDERSIZE, intval(self::BORDERSIZE * 3));
            $rTwo = rand(self::BORDERSIZE, intval(self::BORDERSIZE * 3));

            $bInSize    = intval($mSize - $rOne);
            $cInSize    = intval($bInSize - $rTwo);

            // if ($bInSize < 0) $bInSize = intval($mSize = 50);
            // if ($cInSize < 0) $cInSize = intval($bInSize = 50);

            if (($bInSize > $cInSize && $cInSize > 0)) $sizeCheck = true;
            $sCC++;
            if ($sCC > 50) {
                $sizeCheck = true;
                outputLog("Size Check Limit Reached.");
            }
        }

        $sizes["main"]   = $mSize;
        $sizes["outB"]   = $borderSize;
        $sizes["inB"]    = $bInSize;
        $sizes["center"] = $cInSize;

        //outputLog("Mx: " . $multiplier . " R1: " . $rOne . " R2: " . $rTwo . " SC: " . $sCC);
        //outputLog("mS: " . $mSize . " oB: " . $borderSize . " iB: " . $bInSize . " cS: " . $cInSize);

        return $sizes;
    }

    public function complexCircleLoop($bbP)
    {
        $count = 1;
        $reversed = array_reverse($bbP["colors"]);

        //while ($count < $tcs) {
        foreach ($reversed as $color) {

            $sz = $this->compCircSizes($count);
            $pos = $this->compCircLoc($count, $sz["main"]);
            $X = $this->circSpot_x;
            $Y = $this->circSpot_y;

            $this->drawColoredCircle($bbP["wImg"], $bbP["cPal"], $X, $Y, $sz["outB"],   $bbP["bgColor"]);
            $this->drawColoredCircle($bbP["wImg"], $bbP["cPal"], $X, $Y, $sz["main"],   $color);
            $this->drawColoredCircle($bbP["wImg"], $bbP["cPal"], $X, $Y, $sz["inB"],    $bbP["bgColor"]);
            $this->drawColoredCircle($bbP["wImg"], $bbP["cPal"], $X, $Y, $sz["center"], $color);

            $count++;
        }
    }

    // Circle Row Loops
    public function rowLoop($image, $palette, $tfc, $colorMode)
    {
        $circleSize = intval(round(self::WALLWIDTH * 0.10));
        $tcs        = count($tfc["colors"]);
        $tCRange    = $tcs - 1;
        $tenCount   = 0;
        $tenMax     = intval(round(self::WALLHEIGHT / 10));

        while ($tenCount <= $tenMax) {
            $this->circSpot_x = intval(round($circleSize * 0.5));

            $rowCount = 0;
            $rowMax   = 9;
            $tCCount  = -1;
            $blackOut = false;

            while ($rowCount <= $rowMax) {
                if ($tCCount > $tCRange) $blackOut = true;

                if ($rowCount > 0) $this->circSpot_x = intval(round(($circleSize + $this->circSpot_x)));
                if ($tenCount > 0)
                    $this->circSpot_y = intval(round(($circleSize * $tenCount) + 60));
                else
                    $this->circSpot_y = 60;

                $mainColor  = $tfc["background"];
                $randColor  = rand(0, $tCRange);
                $randChance = rand(0, $tcs);

                if ($colorMode === "random") {
                    if ($randChance >= intval(round($tcs * 0.5))) $mainColor = $tfc["colors"][$randColor];
                    $cSizish = intval(round(rand(5, intval(round($circleSize - 8)))));
                } else {
                    if ($blackOut !== true && $tCCount !== -1)
                        $mainColor = $tfc["colors"][$tCCount];
                    else
                        $mainColor = $tfc["background"];

                    $cSizish = intval(round($circleSize - 8));
                }

                $this->drawColoredCircle($image, $palette, $this->circSpot_x, $this->circSpot_y, $cSizish, $mainColor);

                $rowCount++;
                $tCCount++;
            }

            $tenCount++;
        }
    }

    public function uniqueX()
    {
        $X = rand(0, intval(self::WALLWIDTH));

        if (in_array($X, $this->xRangers))
            return 0;
        else
            return $X;
    }

    public function dots($bbP)
    {
        $count = 0;
        $tcs   = 8;
        $this->xRangers = array();

        while ($count < $tcs) {
            foreach ($bbP["colors"] as $color) {

                $S = rand(4, 40);
                $halfS = intval(round($S * 0.5));

                $vX = $this->uniqueX();
                while ($vX === 0) {
                    $vX = $this->uniqueX();
                }

                $X = $vX;
                $Y = rand(0, intval(self::WALLHEIGHT));

                $this->drawColoredCircle($bbP["wImg"], $bbP["cPal"], $X, $Y, $S, $color);

                $numbers = range(intval(round($X - $halfS)),  intval(round($X + $halfS)));
                foreach ($numbers as $number) {
                    $this->xRangers[] = $number;
                }
                $this->xRangers = array_unique($this->xRangers);
                asort($this->xRangers);
            }
            $count++;
        }
    }


    //
    // MAIN GENERATION FUNCTIONS
    // These functions are what generate wallpapers
    //

    // Row of theme colored circles
    public function makeCircleInline($imagine, $theme, $themeType)
    {

        $tfc   = loadThemeFile($themeType, false, $theme);

        $palette = new RGB();

        $image   = $imagine->create(new Box(self::WALLWIDTH, self::WALLHEIGHT), $palette->color($tfc["background"]));

        //$cBig = intval(round($WALLWIDTHMax / $maxCs));

        // Generate Inline Circle Pattern
        $maxCs = count($tfc["colors"]);
        $curCs = 1;
        $WALLWIDTHMax  = intval(round(self::WALLWIDTH - self::INLINESPACE));
        $circleSize    = intval(round(($WALLWIDTHMax / $maxCs))); // Small Gap between each circle. 

        //outputLog(count($tfc["colors"]) . " circles @ " . $circleSize) . "px each";

        foreach ($tfc["colors"] as $themeColor) {
            $tC = $this->inlineCircle($image, $palette, $themeColor, $circleSize, $curCs);
            //outputLog($tC["x"] . " " . $tC["y"] . " " . $curCs);
            $curCs++;
        }

        $cwt = "horizontal_";

        $result = $this->saveCircleWallpaper($image, $cwt);
        return $result;
    }

    // Random Piles of Circles
    public function makeCircleComplex($imagine, $themeName, $themeType)
    {

        $theme = loadThemeFile($themeType, false, $themeName);

        $palette = new RGB();

        $image = $imagine->create(
            new Box(self::WALLWIDTH, self::WALLHEIGHT),
            $palette->color($theme["background"])
        );

        $cParams = array(
            "wImg"    => $image,
            "cPal"    => $palette,
            "bgColor" => $theme["background"],
            "colors"  => $theme["colors"]
        );

        outputLog("complexCircleLoop starting....");

        $this->complexCircleLoop($cParams);

        $cwt = "circleComp_";

        $result = $this->saveCircleWallpaper($image, $cwt);
        return $result;
    }

    public function makeBorderBurst($imagine, $themeName, $themeType)
    {

        $theme = loadThemeFile($themeType, false, $themeName);

        $palette = new RGB();

        $image = $imagine->create(
            new Box(self::WALLWIDTH, self::WALLHEIGHT),
            $palette->color($theme["background"])
        );

        $cParams = array(
            "wImg"    => $image,
            "cPal"    => $palette,
            "bgColor" => $theme["background"],
            "colors"  => $theme["colors"]
        );

        outputLog("Border Burst Loop Starting....");

        $this->borderBurstLoop($cParams, $theme, count($theme["colors"]));

        $cwt = "burst_";

        $result = $this->saveCircleWallpaper($image, $cwt);
        return $result;
    }

    // Row of theme colored circles
    public function makeCircleRows($imagine, $themeName, $themeType, $colorMode = "random")
    {
        //global $imagine;

        $tfc   = loadThemeFile($themeType, false, $themeName);

        $palette = new RGB();
        $image   = $imagine->create(
            new Box(self::WALLWIDTH, self::WALLHEIGHT),
            $palette->color($tfc["background"])
        );

        // Generate Rows and Rows of Circles
        $this->rowLoop($image, $palette, $tfc, $colorMode);

        $cwt = "rows_";
        if ($colorMode === "random") $cwt = "rand-os_";

        $result = $this->saveCircleWallpaper($image, $cwt);
        return $result;
    }

    public function makeStackedCircle($imagine, $themeName, $themeType, $fill = true, $pos = true)
    {
        $theme   = loadThemeFile($themeType, false, $themeName);

        $palette = new RGB();

        $image = $imagine->create(
            new Box(self::WALLWIDTH, self::WALLHEIGHT),
            $palette->color($theme["background"])
        );

        $cParams = array(
            "wImg"    => $image,
            "cPal"    => $palette,
            "bgColor" => $theme["background"],
            "colors"  => $theme["colors"]
        );

        if ($pos === true) {
            $X = intval(round(self::WALLWIDTH * 0.5));
            $Y = intval(round(self::WALLHEIGHT * 1));
        } else {
            $Xmax = intval(round(self::WALLWIDTH * 0.8));
            $Xmin = intval(round(self::WALLWIDTH * 0.2));
            $Ymax = intval(round(self::WALLHEIGHT * 0.8));
            $Ymin = intval(round(self::WALLHEIGHT * 0.2));
            $X = intval(rand($Xmin, $Xmax));
            $Y = intval(rand($Ymin, $Ymax));
        }

        outputLog("stackedCircle starting....");
        $tCount = intval(count($cParams["colors"]));
        $mCount = 1;
        foreach ($theme["colors"] as $color) {
            $rCount = intval($tCount - $mCount);
            //outputLog($rCount);
            $this->stackedCircle($cParams["wImg"], $cParams["cPal"], $color, $rCount, $X, $Y, $fill);
            $mCount++;
        }

        $this->stackedCircle($cParams["wImg"], $cParams["cPal"], $theme["background"], -1, $X, $Y, $fill);

        //outputLog("mCount" . $mCount . "rCount" . $rCount . " tCount" . $tCount);
        $cwt = "circleStacked_";
        if ($fill !== true) $cwt = "lineStacked_";

        $result = $this->saveCircleWallpaper($image, $cwt);
        return $result;
    }

    public function makeDots($imagine, $themeName, $themeType)
    {
        $theme = loadThemeFile($themeType, false, $themeName);
        $palette = new RGB();

        $image = $imagine->create(
            new Box(self::WALLWIDTH, self::WALLHEIGHT),
            $palette->color($theme["background"])
        );

        $cParams = array(
            "wImg"    => $image,
            "cPal"    => $palette,
            "bgColor" => $theme["background"],
            "colors"  => $theme["colors"]
        );

        outputLog("Dots starting....");

        $this->dots($cParams);

        $cwt = "dots_";
        $result = $this->saveCircleWallpaper($image, $cwt);
        return $result;
    }
}
