<?php
/**
 * Class to generate a randomized set of lines
 *
 * @package  Wallpapers
 * @access   public
 */
class LinesWallpaper extends BaseWallpaper {

	/**
	 * Create a randomized pattern using GD library
	 *
	 * @uses drawLine()
	 * @access public
	 * @return void
	 */
	public function draw() {

		$margin = $this->margin;
		imagefill($this->image, 0, 0, $this->palette->getColor('dark_gray'));

		$colors = array('light_gray', 'aqua', 'yellow', 'green', 'pink');
		$colorsLength = count( $colors );

		for ( $i = 0, $colorIndex=0; $i < two_8; $i++, $colorIndex++ )
		{
			if ($colorIndex >= $colorsLength)
			{
				$colorIndex = 0;
			}
			$color = $colors[ $colorIndex ];
			$start = new Point( rand(0, $this->width), 0 );
			$stop  = new Point( rand(0, $this->width), $this->height );

			$this->drawLine( $start, $stop, $this->palette->getColor( $color ) );
		}

	}


	/**
	 * Draw a line of specified color
	 *
	 * @access public
	 * @param Point $start
	 * @param Point $stop
	 * @param resource $color
	 * @return void
	 */
	public function drawLine( $start, $stop, $color ){
		imageline($this->image, $start->x, $start->y, $stop->x, $stop->y, $color );
	}
}
