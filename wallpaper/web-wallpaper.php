<?php
/**
 * Wallpaper subclass that creates a vanishing point image
 *
 * @package  Wallpapers
 * @access   public
 */
class VanishingPointWallpaper extends BaseWallpaper {

	/**
	 * Create the pattern and write it to the resource
	 *
	 * @return void
	 */
	public function draw() {

		$margin = $this->margin;
		imagefill($this->image, 0, 0, $this->palette->getColor('dark_gray'));

		$colors = array('light_gray', 'aqua', 'yellow', 'green', 'pink');
		$colorsLength = count( $colors );
		$step = floor( $this->width / $this->interval );

		for ( $i = 0, $colorIndex=0; $i <= $this->interval; $i++, $colorIndex++ )
		{
			if ($colorIndex >= $colorsLength)
			{
				$colorIndex = 0;
			}
			$color = $colors[ $colorIndex ];
			$width = $i * $step;
			$start = new Point( $width, 0 );
			$stop  = new Point( $this->width - $width, $this->height );
			$this->drawLine( $start, $stop, $this->palette->getColor( $color ) );
		}

	}


	/**
	 * Draw line from $start to sto with a known $color
	 *
	 * @param Point $start
	 * @param Point $stop
	 * @param resource $color
	 * @return void
	 */
	public function drawLine( $start, $stop, $color ){
		imageline($this->image, $start->x, $start->y, $stop->x, $stop->y, $color );
	}


	/**
	 * Determine the number of lines to be draw
	 *
	 * @param int $interval
	 * @return void
	 */
	public function setInterval( $interval ){
		$this->interval = $interval;
	}
}
