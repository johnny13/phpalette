<?php

namespace Art;

class Shapes
{
    const
        ERR_FILE_NOT_FOUND = 1,
        ERR_FONT_FILE = 2,
        ERR_FREETYPE_NOT_ENABLED = 3,
        ERR_GD_NOT_ENABLED = 4,
        ERR_INVALID_COLOR = 5,
        ERR_INVALID_DATA_URI = 6,
        ERR_INVALID_IMAGE = 7,
        ERR_LIB_NOT_LOADED = 8,
        ERR_UNSUPPORTED_FORMAT = 9,
        ERR_WEBP_NOT_ENABLED = 10,
        ERR_WRITE = 11;

    protected $image;
    protected $mimeType;
    protected $exif;

    /**
     * Creates a new SimpleImage object.
     *
     * @param string $image An image file or a data URI to load.
     * @throws \Exception Thrown if the GD library is not found; file|URI or image data is invalid.
     */
    public function __construct($image = null)
    {
        // Check for the required GD extension
        if (extension_loaded('gd')) {
            // Ignore JPEG warnings that cause imagecreatefromjpeg() to fail
            ini_set('gd.jpeg_ignore_warning', 1);
        } else {
            throw new \Exception('Required extension GD is not loaded.', self::ERR_GD_NOT_ENABLED);
        }

        // Load an image through the constructor
        if (preg_match('/^data:(.*?);/', $image)) {
            $this->fromDataUri($image);
        } elseif ($image) {
            $this->fromFile($image);
        }
    }

    /**
     * Destroys the image resource.
     */
    public function __destruct()
    {
        if ($this->image !== null && get_resource_type($this->image) === 'gd') {
            imagedestroy($this->image);
        }
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////
    // Loaders
    //////////////////////////////////////////////////////////////////////////////////////////////////

    /**
     * Loads an image from a data URI.
     *
     * @param string $uri A data URI.
     * @throws \Exception Thrown if URI or image data is invalid.
     * @return \claviska\SimpleImage
     */
    public function fromDataUri($uri)
    {
        // Basic formatting check
        preg_match('/^data:(.*?);/', $uri, $matches);
        if (!count($matches)) {
            throw new \Exception('Invalid data URI.', self::ERR_INVALID_DATA_URI);
        }

        // Determine mime type
        $this->mimeType = $matches[1];
        if (!preg_match('/^image\/(gif|jpeg|png)$/', $this->mimeType)) {
            throw new \Exception(
                'Unsupported format: ' . $this->mimeType,
                self::ERR_UNSUPPORTED_FORMAT
            );
        }

        // Get image data
        $uri = base64_decode(preg_replace('/^data:(.*?);base64,/', '', $uri));
        $this->image = imagecreatefromstring($uri);
        if (!$this->image) {
            throw new \Exception("Invalid image data.", self::ERR_INVALID_IMAGE);
        }

        return $this;
    }

    /**
     * Loads an image from a file.
     *
     * @param string $file The image file to load.
     * @throws \Exception Thrown if file or image data is invalid.
     * @return \claviska\SimpleImage
     */
    public function fromFile($file)
    {
        // Check if the file exists and is readable. We're using fopen() instead of file_exists()
        // because not all URL wrappers support the latter.
        $handle = @fopen($file, 'r');
        if ($handle === false) {
            throw new \Exception("File not found: $file", self::ERR_FILE_NOT_FOUND);
        }
        fclose($handle);

        // Get image info
        $info = getimagesize($file);
        if ($info === false) {
            throw new \Exception("Invalid image file: $file", self::ERR_INVALID_IMAGE);
        }
        $this->mimeType = $info['mime'];

        // Create image object from file
        switch ($this->mimeType) {
            case 'image/gif':
                // Load the gif
                $gif = imagecreatefromgif($file);
                if ($gif) {
                    // Copy the gif over to a true color image to preserve its transparency. This is a
                    // workaround to prevent imagepalettetruecolor() from borking transparency.
                    $width = imagesx($gif);
                    $height = imagesy($gif);
                    $this->image = imagecreatetruecolor($width, $height);
                    $transparentColor = imagecolorallocatealpha($this->image, 0, 0, 0, 127);
                    imagecolortransparent($this->image, $transparentColor);
                    imagefill($this->image, 0, 0, $transparentColor);
                    imagecopy($this->image, $gif, 0, 0, 0, 0, $width, $height);
                    imagedestroy($gif);
                }
                break;
            case 'image/jpeg':
                $this->image = imagecreatefromjpeg($file);
                break;
            case 'image/png':
                $this->image = imagecreatefrompng($file);
                break;
            case 'image/webp':
                $this->image = imagecreatefromwebp($file);
                break;
            case 'image/bmp':
            case 'image/x-ms-bmp':
            case 'image/x-windows-bmp':
                $this->image = imagecreatefrombmp($file);
                break;
        }
        if (!$this->image) {
            throw new \Exception("Unsupported format: " . $this->mimeType, self::ERR_UNSUPPORTED_FORMAT);
        }

        // Convert pallete images to true color images
        imagepalettetotruecolor($this->image);

        // Load exif data from JPEG images
        if ($this->mimeType === 'image/jpeg' && function_exists('exif_read_data')) {
            $this->exif = @exif_read_data($file);
        }

        return $this;
    }

    /**
     * Creates a new image.
     *
     * @param integer $width The width of the image.
     * @param integer $height The height of the image.
     * @param string|array $color Optional fill color for the new image (default 'transparent').
     * @return \claviska\SimpleImage
     */
    public function fromNew($width, $height, $color = 'transparent')
    {
        $this->image = imagecreatetruecolor($width, $height);

        // Use PNG for dynamically created images because it's lossless and supports transparency
        $this->mimeType = 'image/png';

        // Fill the image with color
        $this->fill($color);

        return $this;
    }

    /**
     * Creates a new image from a string.
     *
     * @param string $string The raw image data as a string.
     * @example
     *    $string = file_get_contents('image.jpg');
     * @return \claviska\SimpleImage
     */
    public function fromString($string)
    {
        return $this->fromFile('data://;base64,' . base64_encode($string));
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////
    // Savers
    //////////////////////////////////////////////////////////////////////////////////////////////////

    /**
     * Generates an image.
     *
     * @param string $mimeType The image format to output as a mime type (defaults to the original mime type).
     * @param integer $quality Image quality as a percentage (default 100).
     * @throws \Exception Thrown when WEBP support is not enabled or unsupported format.
     * @return array Returns an array containing the image data and mime type ['data' => '', 'mimeType' => ''].
     */
    protected function generate($mimeType = null, $quality = 100)
    {
        // Format defaults to the original mime type
        $mimeType = $mimeType ?: $this->mimeType;

        // Ensure quality is a valid integer
        if ($quality === null) $quality = 100;
        $quality = self::keepWithin((int) $quality, 0, 100);

        // Capture output
        ob_start();

        // Generate the image
        switch ($mimeType) {

            case 'image/gif':
                imagesavealpha($this->image, true);
                imagegif($this->image, null);
                break;
            case 'image/jpeg':
                imageinterlace($this->image, true);
                imagejpeg($this->image, null, $quality);
                break;
            case 'image/png':
                imagesavealpha($this->image, true);
                imagepng($this->image);
                break;
            case 'image/webp':
                // Not all versions of PHP will have webp support enabled
                if (!function_exists('imagewebp')) {
                    throw new \Exception(
                        'WEBP support is not enabled in your version of PHP.',
                        self::ERR_WEBP_NOT_ENABLED
                    );
                }
                imagesavealpha($this->image, true);
                imagewebp($this->image, null, $quality);
                break;
            case 'image/bmp':
            case 'image/x-ms-bmp':
            case 'image/x-windows-bmp':
                // Not all versions of PHP support bmp
                if (!function_exists('imagebmp')) {
                    throw new \Exception(
                        'BMP support is not available in your version of PHP.',
                        self::ERR_UNSUPPORTED_FORMAT
                    );
                }
                imageinterlace($this->image, true);
                imagebmp($this->image, null, $quality);
                break;
            default:
                throw new \Exception('Unsupported format: ' . $mimeType, self::ERR_UNSUPPORTED_FORMAT);
        }

        // Stop capturing
        $data = ob_get_contents();
        ob_end_clean();

        return [
            'data' => $data,
            'mimeType' => $mimeType
        ];
    }

    /**
     * Generates a data URI.
     *
     * @param string $mimeType The image format to output as a mime type (defaults to the original mime type).
     * @param integer $quality Image quality as a percentage (default 100).
     * @return string Returns a string containing a data URI.
     */
    public function toDataUri($mimeType = null, $quality = 100)
    {
        $image = $this->generate($mimeType, $quality);

        return 'data:' . $image['mimeType'] . ';base64,' . base64_encode($image['data']);
    }

    /**
     * Forces the image to be downloaded to the clients machine. Must be called before any output is sent to the screen.
     *
     * @param string $filename The filename (without path) to send to the client (e.g. 'image.jpeg').
     * @param string $mimeType The image format to output as a mime type (defaults to the original mime type).
     * @param integer $quality Image quality as a percentage (default 100).
     * @return \claviska\SimpleImage
     */
    public function toDownload($filename, $mimeType = null, $quality = 100)
    {
        $image = $this->generate($mimeType, $quality);

        // Set download headers
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Content-Description: File Transfer');
        header('Content-Length: ' . strlen($image['data']));
        header('Content-Transfer-Encoding: Binary');
        header('Content-Type: application/octet-stream');
        header("Content-Disposition: attachment; filename=\"$filename\"");

        echo $image['data'];

        return $this;
    }

    /**
     * Writes the image to a file.
     *
     * @param string $file The image format to output as a mime type (defaults to the original mime type).
     * @param string $mimeType Image quality as a percentage (default 100).
     * @param integer $quality Image quality as a percentage (default 100).
     * @throws \Exception Thrown if failed write to file.
     * @return \claviska\SimpleImage
     */
    public function toFile($file, $mimeType = null, $quality = 100)
    {
        $image = $this->generate($mimeType, $quality);

        // Save the image to file
        if (!file_put_contents($file, $image['data'])) {
            throw new \Exception("Failed to write image to file: $file", self::ERR_WRITE);
        }

        return $this;
    }

    /**
     * Outputs the image to the screen. Must be called before any output is sent to the screen.
     *
     * @param string $mimeType The image format to output as a mime type (defaults to the original mime type).
     * @param integer $quality Image quality as a percentage (default 100).
     * @return \claviska\SimpleImage
     */
    public function toScreen($mimeType = null, $quality = 100)
    {
        $image = $this->generate($mimeType, $quality);

        // Output the image to stdout
        header('Content-Type: ' . $image['mimeType']);
        echo $image['data'];

        return $this;
    }

    /**
     * Generates an image string.
     *
     * @param string $mimeType The image format to output as a mime type (defaults to the original mime type).
     * @param integer $quality Image quality as a percentage (default 100).
     * @return string
     */
    public function toString($mimeType = null, $quality = 100)
    {
        return $this->generate($mimeType, $quality)['data'];
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////
    // Utilities
    //////////////////////////////////////////////////////////////////////////////////////////////////

    /**
     * Ensures a numeric value is always within the min and max range.
     *
     * @param integer|float $value A numeric value to test.
     * @param integer|float $min The minimum allowed value.
     * @param integer|float $max The maximum allowed value.
     * @return integer|float
     */
    protected static function keepWithin($value, $min, $max)
    {
        if ($value < $min) return $min;
        if ($value > $max) return $max;
        return $value;
    }

    /**
     * Gets the image's current aspect ratio.
     *
     * @return float Returns the aspect ratio as a float.
     */
    public function getAspectRatio()
    {
        return $this->getWidth() / $this->getHeight();
    }

    /**
     * Gets the image's exif data.
     *
     * @return array|NULL Returns an array of exif data or null if no data is available.
     */
    public function getExif()
    {
        return isset($this->exif) ? $this->exif : null;
    }

    /**
     * Gets the image's current height.
     *
     * @return integer
     */
    public function getHeight()
    {
        return (int) imagesy($this->image);
    }

    /**
     * Gets the mime type of the loaded image.
     *
     * @return string
     */
    public function getMimeType()
    {
        return $this->mimeType;
    }

    /**
     * Gets the image's current orientation.
     *
     * @return string One of the values: 'landscape', 'portrait', or 'square'
     */
    public function getOrientation()
    {
        $width = $this->getWidth();
        $height = $this->getHeight();

        if ($width > $height) return 'landscape';
        if ($width < $height) return 'portrait';
        return 'square';
    }

    /**
     * Gets the resolution of the image
     *
     * @return mixed The resolution as an array of integers: [96, 96]
     */
    public function getResolution()
    {
        return imageresolution($this->image);
    }

    /**
     * Gets the image's current width.
     *
     * @return integer
     */
    public function getWidth()
    {
        return (int) imagesx($this->image);
    }
    /**
     * Draws an arc.
     *
     * @param integer $x The x coordinate of the arc's center.
     * @param integer $y The y coordinate of the arc's center.
     * @param integer $width The width of the arc.
     * @param integer $height The height of the arc.
     * @param integer $start The start of the arc in degrees.
     * @param integer $end The end of the arc in degrees.
     * @param string|array $color The arc color.
     * @param integer|string $thickness Line thickness in pixels or 'filled' (default 1).
     * @return \claviska\SimpleImage
     */
    public function arc($x, $y, $width, $height, $start, $end, $color, $thickness = 1)
    {
        // Allocate the color
        $color = $this->allocateColor($color);

        // Draw an arc
        if ($thickness === 'filled') {
            imagesetthickness($this->image, 1);
            imagefilledarc($this->image, $x, $y, $width, $height, $start, $end, $color, IMG_ARC_PIE);
        } else {
            imagesetthickness($this->image, $thickness);
            imagearc($this->image, $x, $y, $width, $height, $start, $end, $color);
        }
        return $this;
    }


    /**
     * Draws a border around the image.
     *
     * @param string|array $color The border color.
     * @param integer $thickness The thickness of the border (default 1).
     * @return \claviska\SimpleImage
     */
    public function border($color, $thickness = 1)
    {
        $x1 = 0;
        $y1 = 0;
        $x2 = $this->getWidth() - 1;
        $y2 = $this->getHeight() - 1;

        // Draw a border rectangle until it reaches the correct width
        for ($i = 0; $i < $thickness; $i++) {
            $this->rectangle($x1++, $y1++, $x2--, $y2--, $color);
        }

        return $this;
    }

    /**
     * Fills the image with a solid color.
     *
     * @param string|array $color The fill color.
     * @return \claviska\SimpleImage
     */
    public function fill($color)
    {
        // Draw a filled rectangle over the entire image
        $this->rectangle(0, 0, $this->getWidth(), $this->getHeight(), 'white', 'filled');

        // Now flood it with the appropriate color
        $color = $this->allocateColor($color);
        imagefill($this->image, 0, 0, $color);

        return $this;
    }
    /**
     * Draws a single pixel dot.
     *
     * @param integer $x The x coordinate of the dot.
     * @param integer $y The y coordinate of the dot.
     * @param string|array $color The dot color.
     * @return \claviska\SimpleImage
     */
    public function dot($x, $y, $color)
    {
        $color = $this->allocateColor($color);
        imagesetpixel($this->image, $x, $y, $color);

        return $this;
    }

    /**
     * Draws a line.
     *
     * @param integer $x1 The x coordinate for the first point.
     * @param integer $y1 The y coordinate for the first point.
     * @param integer $x2 The x coordinate for the second point.
     * @param integer $y2 The y coordinate for the second point.
     * @param string|array $color The line color.
     * @param integer $thickness The line thickness (default 1).
     * @return \claviska\SimpleImage
     */
    public function line($x1, $y1, $x2, $y2, $color, $thickness = 1)
    {
        // Allocate the color
        $color = $this->allocateColor($color);

        // Draw a line
        imagesetthickness($this->image, $thickness);
        imageline($this->image, $x1, $y1, $x2, $y2, $color);

        return $this;
    }

    /**
     * Draws a rectangle.
     *
     * @param integer $x1 The upper left x coordinate.
     * @param integer $y1 The upper left y coordinate.
     * @param integer $x2 The bottom right x coordinate.
     * @param integer $y2 The bottom right y coordinate.
     * @param string|array $color The rectangle color.
     * @param integer|array $thickness Line thickness in pixels or 'filled' (default 1).
     * @return \claviska\SimpleImage
     */
    public function rectangle($x1, $y1, $x2, $y2, $color, $thickness = 1)
    {
        // Allocate the color
        $color = $this->allocateColor($color);

        // Draw a rectangle
        if ($thickness === 'filled') {
            imagesetthickness($this->image, 1);
            imagefilledrectangle($this->image, $x1, $y1, $x2, $y2, $color);
        } else {
            imagesetthickness($this->image, $thickness);
            imagerectangle($this->image, $x1, $y1, $x2, $y2, $color);
        }

        return $this;
    }

    /**
     * Draws a rounded rectangle.
     *
     * @param integer $x1 The upper left x coordinate.
     * @param integer $y1 The upper left y coordinate.
     * @param integer $x2 The bottom right x coordinate.
     * @param integer $y2 The bottom right y coordinate.
     * @param integer $radius The border radius in pixels.
     * @param string|array $color The rectangle color.
     * @param integer|array $thickness Line thickness in pixels or 'filled' (default 1).
     * @return \claviska\SimpleImage
     */
    public function roundedRectangle($x1, $y1, $x2, $y2, $radius, $color, $thickness = 1)
    {
        if ($thickness === 'filled') {
            // Draw the filled rectangle without edges
            $this->rectangle($x1 + $radius + 1, $y1, $x2 - $radius - 1, $y2, $color, 'filled');
            $this->rectangle($x1, $y1 + $radius + 1, $x1 + $radius, $y2 - $radius - 1, $color, 'filled');
            $this->rectangle($x2 - $radius, $y1 + $radius + 1, $x2, $y2 - $radius - 1, $color, 'filled');
            // Fill in the edges with arcs
            $this->arc($x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, 180, 270, $color, 'filled');
            $this->arc($x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, 270, 360, $color, 'filled');
            $this->arc($x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, 90, 180, $color, 'filled');
            $this->arc($x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, 360, 90, $color, 'filled');
        } else {
            // Draw the rectangle outline without edges
            $this->line($x1 + $radius, $y1, $x2 - $radius, $y1, $color, $thickness);
            $this->line($x1 + $radius, $y2, $x2 - $radius, $y2, $color, $thickness);
            $this->line($x1, $y1 + $radius, $x1, $y2 - $radius, $color, $thickness);
            $this->line($x2, $y1 + $radius, $x2, $y2 - $radius, $color, $thickness);
            // Fill in the edges with arcs
            $this->arc($x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, 180, 270, $color, $thickness);
            $this->arc($x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, 270, 360, $color, $thickness);
            $this->arc($x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, 90, 180, $color, $thickness);
            $this->arc($x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, 360, 90, $color, $thickness);
        }

        return $this;
    }


    /**
     *
     *  CRUCIAL UTILITY FUNCTIONS
     *
     */

    /**
     * Converts a "friendly color" into a color identifier for use with GD's image functions.
     *
     * @param string|array $color The color to allocate.
     * @return integer
     */
    protected function allocateColor($color)
    {
        $color = self::normalizeColor($color);

        // Was this color already allocated?
        $index = imagecolorexactalpha(
            $this->image,
            $color['red'],
            $color['green'],
            $color['blue'],
            127 - ($color['alpha'] * 127)
        );

        if ($index > -1) {
            // Yes, return this color index
            return $index;
        }

        // Allocate a new color index
        return imagecolorallocatealpha(
            $this->image,
            $color['red'],
            $color['green'],
            $color['blue'],
            127 - ($color['alpha'] * 127)
        );
    }

    /**
     * Adjusts a color by increasing/decreasing red/green/blue/alpha values independently.
     *
     * @param string|array $color The color to adjust.
     * @param integer $red Red adjustment (-255 - 255).
     * @param integer $green Green adjustment (-255 - 255).
     * @param integer $blue Blue adjustment (-255 - 255).
     * @param integer $alpha Alpha adjustment (-1 - 1).
     * @return integer[] An RGBA color array.
     */
    public static function adjustColor($color, $red, $green, $blue, $alpha)
    {
        // Normalize to RGBA
        $color = self::normalizeColor($color);

        // Adjust each channel
        return self::normalizeColor([
            'red' => $color['red'] + $red,
            'green' => $color['green'] + $green,
            'blue' => $color['blue'] + $blue,
            'alpha' => $color['alpha'] + $alpha
        ]);
    }

    /**
     * Darkens a color.
     *
     * @param string|array $color The color to darken.
     * @param integer $amount Amount to darken (0 - 255).
     * @return integer[] An RGBA color array.
     */
    public static function darkenColor($color, $amount)
    {
        return self::adjustColor($color, -$amount, -$amount, -$amount, 0);
    }

    /**
     * Extracts colors from an image like a human would do.™ This method requires the third-party
     * library \League\ColorExtractor. If you're using Composer, it will be installed for you
     * automatically.
     *
     * @param integer $count The max number of colors to extract (default 5).
     * @param string|array $backgroundColor
     *    By default any pixel with alpha value greater than zero will
     *    be discarded. This is because transparent colors are not perceived as is. For example, fully
     *    transparent black would be seen white on a white background. So if you want to take
     *    transparency into account, you have to specify a default background color.
     * @throws \Exception Thrown if library \League\ColorExtractor is missing.
     * @return integer[] An array of RGBA colors arrays.
     */
    public function extractColors($count = 5, $backgroundColor = null)
    {
        // Check for required library
        if (!class_exists('\League\ColorExtractor\ColorExtractor')) {
            throw new \Exception(
                'Required library \League\ColorExtractor is missing.',
                self::ERR_LIB_NOT_LOADED
            );
        }

        // Convert background color to an integer value
        if ($backgroundColor) {
            $backgroundColor = self::normalizeColor($backgroundColor);
            $backgroundColor = \League\ColorExtractor\Color::fromRgbToInt([
                'r' => $backgroundColor['red'],
                'g' => $backgroundColor['green'],
                'b' => $backgroundColor['blue']
            ]);
        }

        // Extract colors from the image
        $palette = \League\ColorExtractor\Palette::fromGD($this->image, $backgroundColor);
        $extractor = new \League\ColorExtractor\ColorExtractor($palette);
        $colors = $extractor->extract($count);

        // Convert colors to an RGBA color array
        foreach ($colors as $key => $value) {
            $colors[$key] = self::normalizeColor(\League\ColorExtractor\Color::fromIntToHex($value));
        }

        return $colors;
    }

    /**
     * Gets the RGBA value of a single pixel.
     *
     * @param integer $x The horizontal position of the pixel.
     * @param integer $y The vertical position of the pixel.
     * @return integer[] An RGBA color array or false if the x/y position is off the canvas.
     */
    public function getColorAt($x, $y)
    {
        // Coordinates must be on the canvas
        if ($x < 0 || $x > $this->getWidth() || $y < 0 || $y > $this->getHeight()) {
            return false;
        }

        // Get the color of this pixel and convert it to RGBA
        $color = imagecolorat($this->image, $x, $y);
        $rgba = imagecolorsforindex($this->image, $color);
        $rgba['alpha'] = 127 - ($color >> 24) & 0xFF;

        return $rgba;
    }

    /**
     * Lightens a color.
     *
     * @param string|array $color The color to lighten.
     * @param integer $amount Amount to lighten (0 - 255).
     * @return integer[] An RGBA color array.
     */
    public static function lightenColor($color, $amount)
    {
        return self::adjustColor($color, $amount, $amount, $amount, 0);
    }

    /**
     * Normalizes a hex or array color value to a well-formatted RGBA array.
     *
     * @param string|array $color
     *    A CSS color name, hex string, or an array [red, green, blue, alpha].
     *    You can pipe alpha transparency through hex strings and color names. For example:
     *        #fff|0.50 <-- 50% white
     *        red|0.25 <-- 25% red
     * @throws \Exception Thrown if color value is invalid.
     * @return array [red, green, blue, alpha].
     */
    public static function normalizeColor($color)
    {
        // 140 CSS color names and hex values
        $cssColors = [
            'aliceblue' => '#f0f8ff', 'antiquewhite' => '#faebd7', 'aqua' => '#00ffff',
            'aquamarine' => '#7fffd4', 'azure' => '#f0ffff', 'beige' => '#f5f5dc', 'bisque' => '#ffe4c4',
            'black' => '#000000', 'blanchedalmond' => '#ffebcd', 'blue' => '#0000ff',
            'blueviolet' => '#8a2be2', 'brown' => '#a52a2a', 'burlywood' => '#deb887',
            'cadetblue' => '#5f9ea0', 'chartreuse' => '#7fff00', 'chocolate' => '#d2691e',
            'coral' => '#ff7f50', 'cornflowerblue' => '#6495ed', 'cornsilk' => '#fff8dc',
            'crimson' => '#dc143c', 'cyan' => '#00ffff', 'darkblue' => '#00008b', 'darkcyan' => '#008b8b',
            'darkgoldenrod' => '#b8860b', 'darkgray' => '#a9a9a9', 'darkgrey' => '#a9a9a9',
            'darkgreen' => '#006400', 'darkkhaki' => '#bdb76b', 'darkmagenta' => '#8b008b',
            'darkolivegreen' => '#556b2f', 'darkorange' => '#ff8c00', 'darkorchid' => '#9932cc',
            'darkred' => '#8b0000', 'darksalmon' => '#e9967a', 'darkseagreen' => '#8fbc8f',
            'darkslateblue' => '#483d8b', 'darkslategray' => '#2f4f4f', 'darkslategrey' => '#2f4f4f',
            'darkturquoise' => '#00ced1', 'darkviolet' => '#9400d3', 'deeppink' => '#ff1493',
            'deepskyblue' => '#00bfff', 'dimgray' => '#696969', 'dimgrey' => '#696969',
            'dodgerblue' => '#1e90ff', 'firebrick' => '#b22222', 'floralwhite' => '#fffaf0',
            'forestgreen' => '#228b22', 'fuchsia' => '#ff00ff', 'gainsboro' => '#dcdcdc',
            'ghostwhite' => '#f8f8ff', 'gold' => '#ffd700', 'goldenrod' => '#daa520', 'gray' => '#808080',
            'grey' => '#808080', 'green' => '#008000', 'greenyellow' => '#adff2f',
            'honeydew' => '#f0fff0', 'hotpink' => '#ff69b4', 'indianred ' => '#cd5c5c',
            'indigo ' => '#4b0082', 'ivory' => '#fffff0', 'khaki' => '#f0e68c', 'lavender' => '#e6e6fa',
            'lavenderblush' => '#fff0f5', 'lawngreen' => '#7cfc00', 'lemonchiffon' => '#fffacd',
            'lightblue' => '#add8e6', 'lightcoral' => '#f08080', 'lightcyan' => '#e0ffff',
            'lightgoldenrodyellow' => '#fafad2', 'lightgray' => '#d3d3d3', 'lightgrey' => '#d3d3d3',
            'lightgreen' => '#90ee90', 'lightpink' => '#ffb6c1', 'lightsalmon' => '#ffa07a',
            'lightseagreen' => '#20b2aa', 'lightskyblue' => '#87cefa', 'lightslategray' => '#778899',
            'lightslategrey' => '#778899', 'lightsteelblue' => '#b0c4de', 'lightyellow' => '#ffffe0',
            'lime' => '#00ff00', 'limegreen' => '#32cd32', 'linen' => '#faf0e6', 'magenta' => '#ff00ff',
            'maroon' => '#800000', 'mediumaquamarine' => '#66cdaa', 'mediumblue' => '#0000cd',
            'mediumorchid' => '#ba55d3', 'mediumpurple' => '#9370db', 'mediumseagreen' => '#3cb371',
            'mediumslateblue' => '#7b68ee', 'mediumspringgreen' => '#00fa9a',
            'mediumturquoise' => '#48d1cc', 'mediumvioletred' => '#c71585', 'midnightblue' => '#191970',
            'mintcream' => '#f5fffa', 'mistyrose' => '#ffe4e1', 'moccasin' => '#ffe4b5',
            'navajowhite' => '#ffdead', 'navy' => '#000080', 'oldlace' => '#fdf5e6', 'olive' => '#808000',
            'olivedrab' => '#6b8e23', 'orange' => '#ffa500', 'orangered' => '#ff4500',
            'orchid' => '#da70d6', 'palegoldenrod' => '#eee8aa', 'palegreen' => '#98fb98',
            'paleturquoise' => '#afeeee', 'palevioletred' => '#db7093', 'papayawhip' => '#ffefd5',
            'peachpuff' => '#ffdab9', 'peru' => '#cd853f', 'pink' => '#ffc0cb', 'plum' => '#dda0dd',
            'powderblue' => '#b0e0e6', 'purple' => '#800080', 'rebeccapurple' => '#663399',
            'red' => '#ff0000', 'rosybrown' => '#bc8f8f', 'royalblue' => '#4169e1',
            'saddlebrown' => '#8b4513', 'salmon' => '#fa8072', 'sandybrown' => '#f4a460',
            'seagreen' => '#2e8b57', 'seashell' => '#fff5ee', 'sienna' => '#a0522d',
            'silver' => '#c0c0c0', 'skyblue' => '#87ceeb', 'slateblue' => '#6a5acd',
            'slategray' => '#708090', 'slategrey' => '#708090', 'snow' => '#fffafa',
            'springgreen' => '#00ff7f', 'steelblue' => '#4682b4', 'tan' => '#d2b48c', 'teal' => '#008080',
            'thistle' => '#d8bfd8', 'tomato' => '#ff6347', 'turquoise' => '#40e0d0',
            'violet' => '#ee82ee', 'wheat' => '#f5deb3', 'white' => '#ffffff', 'whitesmoke' => '#f5f5f5',
            'yellow' => '#ffff00', 'yellowgreen' => '#9acd32'
        ];

        // Parse alpha from '#fff|.5' and 'white|.5'
        if (is_string($color) && strstr($color, '|')) {
            $color = explode('|', $color);
            $alpha = (float) $color[1];
            $color = trim($color[0]);
        } else {
            $alpha = 1;
        }

        // Translate CSS color names to hex values
        if (is_string($color) && array_key_exists(strtolower($color), $cssColors)) {
            $color = $cssColors[strtolower($color)];
        }

        // Translate transparent keyword to a transparent color
        if ($color === 'transparent') {
            $color = ['red' => 0, 'green' => 0, 'blue' => 0, 'alpha' => 0];
        }

        // Convert hex values to RGBA
        if (is_string($color)) {
            // Remove #
            $hex = preg_replace('/^#/', '', $color);

            // Support short and standard hex codes
            if (strlen($hex) === 3) {
                list($red, $green, $blue) = [
                    $hex[0] . $hex[0],
                    $hex[1] . $hex[1],
                    $hex[2] . $hex[2]
                ];
            } elseif (strlen($hex) === 6) {
                list($red, $green, $blue) = [
                    $hex[0] . $hex[1],
                    $hex[2] . $hex[3],
                    $hex[4] . $hex[5]
                ];
            } else {
                throw new \Exception("Invalid color value: $color", self::ERR_INVALID_COLOR);
            }

            // Turn color into an array
            $color = [
                'red' => hexdec($red),
                'green' => hexdec($green),
                'blue' => hexdec($blue),
                'alpha' => $alpha
            ];
        }

        // Enforce color value ranges
        if (is_array($color)) {
            // RGB default to 0
            $color['red'] = isset($color['red']) ? $color['red'] : 0;
            $color['green'] = isset($color['green']) ? $color['green'] : 0;
            $color['blue'] = isset($color['blue']) ? $color['blue'] : 0;

            // Alpha defaults to 1
            $color['alpha'] = isset($color['alpha']) ? $color['alpha'] : 1;

            return [
                'red' => (int) self::keepWithin((int) $color['red'], 0, 255),
                'green' => (int) self::keepWithin((int) $color['green'], 0, 255),
                'blue' => (int) self::keepWithin((int) $color['blue'], 0, 255),
                'alpha' => self::keepWithin($color['alpha'], 0, 1)
            ];
        }

        throw new \Exception("Invalid color value: $color", self::ERR_INVALID_COLOR);
    }
}
