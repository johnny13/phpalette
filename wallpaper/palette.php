<?php
/**
 * Class for managing colors
 *
 *
 *
 * @package  Wallpapers
 * @access   public
 */
class Palette {

	private $image;

	private $color = [];

	/**
	 * Constructor
	 *
	 * Creates a new palette object
	 *
	 * @param resource $image
	 * @return void
	 */
	public function __construct( $image ){
		$this->image = $image;

		$this->addColor('black', 0, 0, 0);
		$this->addColor('dark_gray', 32, 32, 32);
		$this->addColor('light_gray', 205, 205, 205);
		$this->addColor('red', 168, 0, 0);
		$this->addColor('yellow', 168, 168, 0);
		$this->addColor('blue', 0, 0, 168);
		$this->addColor('green', 0, 168, 0);
		$this->addColor('dark_aqua', 4, 39, 49);
		$this->addColor('dark_gold', 83, 87, 7);
		$this->addColor('dark_amber', 87, 55, 7);
		$this->addColor('dark_purple', 55, 14, 87);
		$this->addColor('pink', 205, 14, 180);
		$this->addColor('aqua', 7, 169, 187);
		$this->addColor('white', 230, 230, 230);
	}


	/**
	 * Register a color in the palette
	 *
	 * @uses public
	 * @param String $name
	 * @param int $red
	 * @param int $green
	 * @param int $blue
	 * @return void
	 */
	public function addColor( $name, $red, $green, $blue ){
		$this->color[ $name ] = imagecolorallocate($this->image, $red, $green, $blue);
	}


	/**
	 * Returns the resource for a known color
	 *
	 * @uses public
	 * @param String $name
	 * @return resource
	 */
	public function getColor( $name ){
		return $this->color[ $name ];
	}


}
