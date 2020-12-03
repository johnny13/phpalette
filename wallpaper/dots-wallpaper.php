<?php
/**
 * Class to generate a image of randomized dots
 *
 * @package  Wallpapers
 * @access   public
 */
class DotsWallpaper extends BaseWallpaper {

	/**
	 * Create the pattern of randomized dots
	 *
	 * @return void
	 */
	public function draw()
	{
		$margin = $this->margin;
		imagefill( $this->image, 0, 0, $this->palette->getColor('black'));
		imagefilledrectangle($this->image,
			$margin,
			$margin,
			$this->width,
			$this->height - $margin,
			$this->palette->getColor('dark_aqua')
		);

		$dots = array('aqua', 'dark_gold', 'dark_amber', 'dark_purple');
		$dots_length = count($dots);

		for ( $i = 0, $color_index = 0; $i < two_8; $i++, $color_index++ )
		{
			if ($color_index >= $dots_length)
			{
				$color_index = 0;
			}

			$dotColor = $dots[$color_index];
			$radius = rand(4, two_5);
			$center = $this->create_center( $radius );
			$this->drawDot( $center, $radius, $this->palette->getColor( $dotColor ) );
		}
	}


	/**
	 * Set the margin, which is representsed as a border around the image
	 *
	 * @param int $margin
	 * @return void
	 */
	public function setMargin( $margin ){
		$this->margin = $margin;
	}


	/**
	 * Random create the center of a dot, and account for the use of margins
	 *
	 * @param int $radius
	 * @return array
	 */
	public function create_center( $radius ){
		$xMin = $this->margin + $radius;
		$xMax = $this->width - $this->margin - $radius;

		$yMin = $this->margin + $radius;
		$yMax = $this->height - $this->margin - $radius;

		// TODO: replace this array with an object of the Point class
		return array(
			'x' => rand( $xMin, $xMax ),
			'y' => rand( $yMin , $yMax )
		);
	}


	/**
	 * Draw a dot
	 *
	 * @param array $center
	 * @param int $radius determines how big the circle should be
	 * @return void
	 */
	public function drawDot( $center, $radius, $dotColor ){
		imagefilledellipse(
			$this->image,
			$center['x'],
			$center['y'],
			$radius,
			$radius,
			$dotColor
		);
	}

}
