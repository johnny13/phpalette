<?php
/**
 * Point represents an X and Y coordinate
 *
 * @package Wallpapers
 * @access public
 */
class Point {

	/** @var X-coordinate for a pixel in an image   */
	public $x;

	/** @var Y-coordinate for a pixel in an image   */
	public $y;


	/**
	 * Constructor
	 *
	 * Create a new Point object
	 *
	 * @param String $one a necessary parameter
	 * @param String optional $two an optional value
	 * @return void
	 */
	public function __construct( $x, $y ){
		$this->x = $x;
		$this->y = $y;
	}


	/**
	 * Magic method. Return a string representing this object
	 *
	 * Create a string of this object when it's used in a string object
	 *
	 * @return string
	 */
	public function __toString(){
		echo "x={$this->x}, y={$this->y}";
	}
}
