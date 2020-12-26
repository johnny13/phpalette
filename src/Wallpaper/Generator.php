<?php

/**
 * 
 *  @abstract This file controls the generating of all the various wallpapers.
 *  @author Derek Scott <derek@huement.com>
 *
 */

declare(strict_types=1);

namespace Wallpaper;

use Imagine\Imagick;
use Imagine\Imagick\Imagine;

class Generator
{

    const OUTDIR = 'out/walls/';

    public $imagine;
    public $simpleImage;

    public function __construct()
    {
        $this->imagine     = new Imagine();
        $this->simpleImage = new \claviska\SimpleImage();
    }

    public function paintGen($theme = false, $theme_type = false, $shuffle = false, $texture = false)
    {
        $GenWall = new Raster();
        $result = $GenWall->makePaintWallpaper();
        return $result;
    }

    public function roundRectGen($theme = false, $theme_type = false, $shuffle = false, $texture = false, $flow = false)
    {
        $GenWall = new Rectangle();
        $result = $GenWall->makeRectangle($this->simpleImage, $theme, $theme_type, $shuffle, $texture, $flow);
        return $result;
    }

    public function burstCircleGen($theme = false, $theme_type = false, $shuffle = false, $texture = false)
    {
        $GenWall = new Circle();
        $result = $GenWall->makeCircleComplex($this->imagine, $theme, $theme_type);
        return $result;
    }

    public function borderburstCircleGen($theme = false, $theme_type = false, $shuffle = false, $texture = false)
    {
        $GenWall = new Circle();
        $result = $GenWall->makeBorderBurst($this->imagine, $theme, $theme_type);

        return $result;
    }

    public function stackCircleGen($theme = false, $theme_type = false, $shuffle = false, $texture = false, $fill = true, $pos = true)
    {
        $GenWall = new Circle();
        $result = $GenWall->makeStackedCircle($this->imagine, $theme, $theme_type, $fill, $pos);
        return $result;
    }

    public function stackLineCircleGen($theme = false, $theme_type = false, $shuffle = false, $texture = false)
    {
        $GenWall = new Circle();
        $result = $GenWall->makeStackedCircle($this->imagine, $theme, $theme_type, false, false);
        return $result;
    }

    public function dotsGen($theme = false, $theme_type = false, $shuffle = false, $texture = false)
    {
        $GenWall = new Circle();
        $result = $GenWall->makeDots($this->imagine, $theme, $theme_type);
        return $result;
    }

    public function rowsOfCircleGen($theme = false, $theme_type = false, $shuffle = false, $texture = false, $mode = "random")
    {
        $GenWall = new Circle();
        $result = $GenWall->makeCircleRows($this->imagine, $theme, $theme_type, $mode);
        return $result;
    }

    public function inlineCircleGen($theme = false, $theme_type = false, $shuffle = false, $texture = false)
    {
        $GenWall = new Circle();
        $result = $GenWall->makeCircleInline($this->imagine, $theme, $theme_type);
        return $result;
    }
}
