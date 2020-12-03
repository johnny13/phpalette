<?php

declare(strict_types=1);

namespace Art;

class Generate
{

    const OUTDIR = 'out/';

    public function scarytonHeader($climate)
    {
        $climate->addArt('ascii');
        $climate->backgroundBlack()->red()->boldDraw('skull');
        $climate->backgroundBlack()->redBold()->border('/\ \/ /\ \/ ', 80);
        $climate->backgroundBlack()->redBold()->border('\/ /\ \/ /\\ ', 80);
    }


    public function makeFractal(): string
    {
        $imgname = self::OUTDIR . "plasma_swirl.jpg";
        shell_exec("convert -size 1080x920  plasma:fractal -blur 0x3 -swirl 180 -shave 20x20 " . $imgname);
        return $imgname;
    }

    public function colorPalette($imageFile, $numColors, $granularity = 5)
    {
        $granularity = max(1, abs((int)$granularity));
        $colors = array();
        $size = @getimagesize($imageFile);
        if ($size === false) {
            user_error("Unable to get image size data");
            return false;
        }
        $img = @imagecreatefromstring(file_get_contents($imageFile));
        // Andres mentioned in the comments the above line only loads jpegs, 
        // and suggests that to load any file type you can use this:
        // $img = @imagecreatefromstring(file_get_contents($imageFile)); 

        if (!$img) {
            user_error("Unable to open image file");
            return false;
        }
        for ($x = 0; $x < $size[0]; $x += $granularity) {
            for ($y = 0; $y < $size[1]; $y += $granularity) {
                $thisColor = imagecolorat($img, $x, $y);
                $rgb = imagecolorsforindex($img, $thisColor);
                $red = round(round(($rgb['red'] / 0x33)) * 0x33);
                $green = round(round(($rgb['green'] / 0x33)) * 0x33);
                $blue = round(round(($rgb['blue'] / 0x33)) * 0x33);
                $thisRGB = sprintf('%02X%02X%02X', $red, $green, $blue);
                if (array_key_exists($thisRGB, $colors)) {
                    $colors[$thisRGB]++;
                } else {
                    $colors[$thisRGB] = 1;
                }
            }
        }
        arsort($colors);
        return array_slice(array_keys($colors), 0, $numColors);
    }

    public function grabColors($imgFile)
    {
        // $imgFile = 'filename.png'; 
        // sample usage: 
        $palette = $this->colorPalette($imgFile, 10, 4);
        echo "<table>\n";
        foreach ($palette as $color) {
            echo "<tr><td style='background-color:#$color;width:2em;'>&nbsp;</td><td>#$color</td></tr>\n";
        }
        echo "</table>\n";
    }
}
