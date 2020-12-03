<?php
/**
 * The base class for all Wallpaper subclasses
 *
 * @package  Wallpapers
 * @access   public
 */
class BaseWallpaper {

	/** a handle to the image */
	protected $image;

	/** the color palette */
	protected $palette;

	/** a buffer of empty space around the image */
	protected $margin;


	/**
	 * Constructor
	 *
	 * Create a new Wallpaper object
	 *
	 * @access public
	 * @param resource $image a handle to the image resource
	 * @param int $width the width of the image
	 * @param int $height the width of the image
	 * @return void
	 */
	public function __construct( $image, $width, $height ) {
		$this->image  = $image;
		$this->width  = $width;
		$this->height = $height;
		$this->margin = 0;
	}


	/**
	 * Add a palette to the image
	 *
	 * @access public
	 * @param Palette $pallete contains all the color values
	 * @return void
	 */
	public function addPalette( $palette ){
		$this->palette = $palette;
	}


	/**
	 * base draw function. This must be overridden in subclass
	 *
	 * @access public
	 * @return void
	 */
	public function draw(){
		throw new Exception( 'You must override this function.' );
	}


	/**
	 * Write the image resource to a JPG file
	 *
	 * @access public
	 * @param string $filename
	 * @return void
	 */
	public function save( $filename ) {
		imagejpeg( $this->image, $filename );
		imagedestroy( $this->image );
	}


	/**
	 * Wrapper function around draw() and save()
	 *
	 * @access public
	 * @param string $filename
	 * @return void
	 */
	public function generate( $filename ){
		$this->draw();
		$this->save( $filename );
	}
}

