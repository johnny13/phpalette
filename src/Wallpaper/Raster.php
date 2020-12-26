<?php

declare(strict_types=1);

namespace Wallpaper;

use Imagine\Image;
use Imagine\Image\Palette\RGB;
use Imagine\Image\Box;
use Imagine\Image\Point;

class Raster
{

    const OUTDIR       = 'out/walls/';
    const TEXTUREDIR   = 'images/';
    const THUMBDIR     = 'out/wall_thumbs/';
    const GREYTEXTDIR  = "images/1920x1080/";
    const FILTTEXTDIR  = "images/1920x1080_filtered/";
    const WALLWIDTH    = 1920;
    const WALLHEIGHT   = 1080;
    const THUMBWIDTH   = 800;
    const THUMBHEIGHT  = 600;

    // General Save & Thumbnail Function
    public function saveRasterWallpaper($wallpaperImage, $nameString, $savedir = self::OUTDIR, $skip = false)
    {
        $w_name = randomTxtString(3);
        $f_name = $nameString . $w_name . '.png';

        if (!is_dir($savedir) && !mkdir($savedir, 0777, true))
            die('Failed to create folder ' . $savedir . '...');

        $wallpaperImage->toFile($savedir . $f_name, 'image/png');

        wallThumbnail($savedir . $f_name, self::THUMBWIDTH, self::THUMBHEIGHT, self::THUMBDIR . $f_name);

        if ($skip === false) {
            outputImg(self::THUMBDIR . $f_name);
        }

        return array("image" => $savedir . $f_name, "thumbnail" => self::THUMBDIR . $f_name);
    }

    public function tempRasterWallpaper($wallpaperImage)
    {
        $result = $wallpaperImage->toDataUri();
        return array("image" => $result);
    }

    public function makeGrunge($image)
    {
        $walls = array("rr_F4RaW.png", "dots_YQ6fTz.png", "circleRowRandom_Wa3FQE.png", "circles_A8agWC.png", "circleBB_KXxt6y.png", "circleStacked_QHrwKR.png", "circleComp_aexfEz.png", "circleRow_xPTkYZ.png");

        $textures = array();
        foreach (glob(self::FILTTEXTDIR . "*.{png,PNG}", GLOB_BRACE) as $filename) {
            $textures[] = $filename;
        }

        foreach ($walls as $wall) {
            foreach ($textures as $texture) {
                $bname = basename($texture, "png");
                $wname = basename($wall, "png");
                $image
                    ->fromFile(self::OUTDIR . $wall)
                    ->autoOrient()
                    ->overlay($texture, "center", 0.15);

                $this->saveRasterWallpaper($image, $wname . "_" . $bname . "_O-15_");
            }
        }
    }

    public function loadTextures()
    {
        $textures = array();

        foreach (glob("images/1920x1080_filtered/*.{png,PNG}", GLOB_BRACE) as $filename) {
            $textures[] = $filename;
            //outputLog("File: " . $filename);
        }

        shuffle($textures);
        $random_texture = $textures[0];

        return array("files" => $textures, "random" => $random_texture);
    }

    public function loadPaints()
    {
        $textures = array();

        foreach (glob("images/paint_splatters/*.{png,PNG}", GLOB_BRACE) as $filename) {
            $textures[] = $filename;
        }

        shuffle($textures);

        return array("files" => $textures);
    }

    public function makeReady($image)
    {
        if (!is_dir(self::FILTTEXTDIR)) {
            if (!mkdir(self::FILTTEXTDIR, 0777, true)) {
                die('Failed to create folders...');
            }
        }

        $dir = self::GREYTEXTDIR;
        $typeString = "png,PNG";
        $textures = directoryToArray($dir, $typeString);

        // Run the loop
        foreach ($textures as $img) {

            $bname = basename($img);

            if (file_exists(self::FILTTEXTDIR . $bname)) {
                outPutLog("Already Processed " . $bname);
            } else {
                // Black and WHite Mode
                outputLog("Filtering..." . $bname);
                $image
                    ->fromFile($img)
                    ->sketch()
                    ->toFile(self::FILTTEXTDIR . $bname, 'image/png');
            }
        }

        outputLog("Finished!");
    }

    public function makeBlank($image)
    {
        $image
            ->fromNew(self::WALLWIDTH, self::WALLHEIGHT);

        return $image;
    }

    public function makeRandomTextureOverlay($filename)
    {
        if ($filename === true) {
            $textures = $this->loadTextures();
            $file = $textures["random"];
            outputLog("Random Texture: " . $file);
        } else {
            $file = $filename;
            outputLog("Fixed Texture: " . $file);
        }

        $image = new \claviska\SimpleImage();
        $image->fromFile($file);

        $chance = mt_rand(0, 4);

        if ($chance === 1)
            $image->flip("x");
        if ($chance === 2)
            $image->flip("y");
        if ($chance === 0)
            $image->flip("both");

        return $image;
    }

    public function makePaintWallpaper()
    {
        $paints = $this->loadPaints();

        $allColors   = loadThemeFile("base16", true);
        $bgColor     = $allColors["background"];

        $paintImages = array();
        $sC = 0;
        foreach ($allColors["colors"] as $color) {
            $img = $paints["files"][$sC];
            $image = new \claviska\SimpleImage();
            $image
                ->fromFile($img)
                ->colorize($color);

            $results = $this->tempRasterWallpaper($image);
            $paintImages[] = $results["image"];
            $sC++;
        }

        $pC = 0;
        $lastPaintImg = "";
        foreach ($paintImages as $pI) {
            $imageX = new \claviska\SimpleImage();
            if ($pC === 0) {
                $imageX
                    ->fromNew(self::WALLWIDTH, self::WALLHEIGHT);
            } else {
                $imageX->fromDataUri($lastPaintImg);
            }

            $imageX->overlay($pI, 'center center');
            $paintStep = $this->tempRasterWallpaper($imageX);
            $lastPaintImg = $paintStep["image"];
            $pC++;
        }

        outputLog("Final Step. Loading Image...");
        $imageZ = new \claviska\SimpleImage();
        $imageZ->fromDataUri($lastPaintImg);

        $imageY = new \claviska\SimpleImage();
        $imageY->fromNew(self::WALLWIDTH, self::WALLHEIGHT, $bgColor);
        $imageY->overlay($imageZ, 'center center');

        $paintStep = $this->saveRasterWallpaper($imageY, "paints_" . $allColors["theme"] . "_");
        return $paintStep;
    }
}
