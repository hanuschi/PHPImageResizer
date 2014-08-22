<?php

/**
 * Class ImageData Image data object which works as image resizer
 *
 * Examples:
 *
 * 1) url /image/img__-w:120-q:75.jpg URL takes img.jpg image from /image path,
 *                                    resizes that to the width 120px,
 *                                    sets 75% of image quality
 *                                    and show that on the browser
 *
 * 2) url /image/img__-w:256-a:mm-s:c URL takes img.jpg image from /image path,
 *                                    cut that to the width 120px,
 *                                    with the centering of the image
 *                                    to the middle/middle (horizontal/vertical)
 *                                    and show that on the browser
 *
 * To cache image type $image->publsh () with no parameter (default is true)
 *
 * To add watermark type $image->enableWatermark (true) (default is false)
 * Also the
 */
Class ImageData
{
    const QUALITY_PNG_CONSTANT      = 0.09;
    const OPTION_WIDTH_PARAM        = 'w';
    const OPTION_HEIGHT_PARAM       = 'h';
    const OPTION_QUALITY_PARAM      = 'q';
    const OPTION_ROTATION_PARAM     = 'r';
    const OPTION_RESIZE_STYLE_PARAM = 's';
    const OPTION_RESIZE_ALIGN_PARAM = 'a';

    /**
     * @var null|array of cached options from url
     */
    protected static $_cacheOptions = null;
    /**
     * @var string url of image
     */
    protected $url;
    /**
     * @var string name of the image without extension and options
     */
    protected $name;
    /**
     * @var string URL path without image name
     */
    protected $path;
    /**
     * @var string Extension of image
     */
    protected $extension;
    /**
     * @var string URL of image watermark if exists
     */
    protected $urlWatermark;
    /**
     * Ratio of watermark counted from the resized image values
     * @var float
     */
    protected $watermarkRatio = 0.333;
    /**
     * @var bool flag whether the watermark is added to the image or not
     */
    protected $enableWatermark = false;
    /**
     * URL option: -w:[value]
     * @var int image final width
     */
    protected $width;
    /**
     * URL option: -h:[value]
     * @var int image final height
     */
    protected $height;
    /**
     * URL option: -q:[value]
     * Default: 75
     * @var int image quality 0-100
     */
    protected $quality = 75;
    /**
     * URL option: -r:[value]
     * @var int image rotation 0-360
     */
    protected $rotation;
    /**
     * Resize style:
     * c = cut
     * r = resize
     *
     * Default is r
     *
     * URL option: -s:[value]
     * @var string image resize style
     */
    protected $resizeStyle = null;

    /**
     * @var string Default value for image resized style
     */
    protected $resizeStyleDefaultValue = 'r';
    /**
     * @var array List of resize style allowed values
     */
    protected $resizeStyleAllowedValues = array (
        'r',
        'c',
    );
    /**
     * tl = top left
     * tm = top middle
     * tr = top right
     * ml = middle left
     * mm = middle middle
     * mr = middle right
     * bl = bottom left
     * bm = bottom middle
     * br = bottom right
     *
     * default is mm
     *
     * URL option: -a:[value]
     * @var string image resize align
     */
    protected $resizeAlign = null;

    /**
     * @var string Default value for image resize align
     */
    protected $resizeAlignDefaultValue = 'mm';
    /**
     * @var array List of resize align allowed values
     */
    protected $resizeAlignAllowedValues = array (
        'tl',
        'tm',
        'tr',
        'ml',
        'mm',
        'mr',
        'bl',
        'bm',
        'br',
    );

    /**
     * @var string cache directory where all image caches are stored
     */
    public $cacheDir = './cache';
    public $baseRoot = './..';

    function __construct ($params = array ())
    {
        if (count ($params) > 0) {
            $classVars = get_class_vars (get_class ($this));
            foreach ($params as $key => $value) {
                if (!in_array ($key, array_keys ($classVars))) {
                    throw new Exception (sprintf ("Param '%s' is not a param of the class '%s'", $key, get_class ($this)));
                }
                $this->$key = $value;
            }
        }
    }

    /**
     * In case of a parameter is empty we try to call its setter
     * which returns the set value
     *
     * @param $name string
     * @return string
     */
    public function __get ($name)
    {
        if (!isset ($this->$name) && method_exists ($this, $name)) {
            return $this->$name ();
        }
        return $this->$name;
    }

    /**
     * Object uses protected parameters which has to be reached
     * by setter methods. This setter sets the basic methods redirection
     *
     * @param $name string name of parameter
     * @param $value mixed
     * @return mixed
     */
    public function __set ($name, $value)
    {
        $methodName = 'set' . ucfirst ($name);
        if (!isset ($this->$name) && method_exists ($this, $methodName)) {
            return $this->$methodName ($value);
        }
        $this->$name = $value;
    }

    /* PROTECTED METHODS */

    /**
     * Checks the type of image and shows/saves it by set extension
     *
     * @param $image image resource
     * @param null|string $filename name of a filepath to store
     */
    protected function printImage ($image, $filename = null)
    {
        switch (strtolower ($this->extension ())) {
            case 'jpg':
            case 'jpeg':
                if (!$filename) header ('Content-type: image/jpeg');
                imagejpeg ($image, $filename, $this->quality ());
                break;
            case 'png':
                if (!$filename) header ('Content-type: image/png');
                imagepng ($image, $filename, $this->qualityPNG ());
                break;
            case 'gif':
                if (!$filename) header ('Content-type: image/gif');
                imagegif ($image, $filename);
                break;
        }
    }

    /**
     * Takes path from the input values or from the object
     * in case of input value is null and return image resurce from
     * this path
     *
     * @param string $imagePath
     * @return resource Image
     * @throws Exception
     */
    protected function getImage ($imagePath = null)
    {
        if (!$this->url) {
            throw new Exception ('Cannot create link! Image does not have the url param set!');
        }
        if ($imagePath == null) $imagePath = $this->getRealImageFilePath ();
        switch (strtolower ($this->extension ())) {
            case 'jpg':
            case 'jpeg':
                return imagecreatefromjpeg ($imagePath);
            case 'png':
                return imagecreatefrompng ($imagePath);
            case 'gif':
                return imagecreatefrompng ($imagePath);

        }
    }

    protected function isImageTransparency ()
    {
        $extension = strtolower ($this->extension ());
        return (in_array ($extension, array (
            'png',
            'gif',
        )));
    }

    /**
     * Takes original resource image and creates new image
     * by predefined parameters (resize, rotate, watermark)
     *
     * @return resource
     */
    protected function prepareImage ()
    {
        $image = $this->getImage ();

        list ($origWidth, $origHeight) = getimagesize ($this->getRealImageFilePath ());
        list ($resizedWidth, $resizedHeight) = $this->getResizedImageSize ();

        // Create new image
        $image_p = imagecreatetruecolor ($resizedWidth, $resizedHeight);
        // Check positive size
        if (!$resizedWidth || !$resizedHeight) {
            throw new Exception ("Unknown resized width or height or one value is zero!");
        }
        // Check transparency
        if ($this->isImageTransparency ()){
            imagesavealpha ($image_p, true);
            $color = imagecolorallocatealpha ($image_p, 0, 0, 0, 127);
            imagefill($image_p, 0, 0, $color);
        }
        // Resize image
        switch ($this->resizeStyle ()) {
            case 'c':
                // First resize image to the window (with overleaking), then cut by style align
                list ($minResizedWidth, $minResizedHeight) = $this->getMinResizedImageCutSize ();
                // Image is created with the predefined values,
                // now we add an image into the created one by align style
                list ($originOffsetX, $originOffsetY) = $this->getOriginOffsetCutSizesByAlignStyle ();
                imagecopyresampled ($image_p, $image, 0, 0, $originOffsetX, $originOffsetY, $minResizedWidth, $minResizedHeight, $origWidth, $origHeight);
                break;
            case 'r':
            default:
                // just resize image
                imagecopyresampled ($image_p, $image, 0, 0, 0, 0, $resizedWidth, $resizedHeight, $origWidth, $origHeight);
                break;

        }
        // Rotate image
        if ($this->rotation ()) {
            $image_p = imagerotate ($image_p, $this->rotation (), 0);
        }
        return $image_p;
    }

    /**
     * Return original image size from image path or URL
     * @return array
     */
    public function getOriginImageSize ()
    {
        if (file_exists ($this->getRealImageFilePath())) {
            return getimagesize ($this->getRealImageFilePath ());
        } else if ($this->url ()) {
            return getimagesize ($this->url ());
        }
        return array (null, null);
    }

    /**
     * Return resized image width and height
     * by predefined parameters
     *
     * Result is in array (width, height)
     *
     * @return array
     * @throws Exception
     */
    public function getResizedImageSize ()
    {
        list ($origWidth, $origHeight) = getimagesize ($this->getRealImageFilePath ());
        $sizeRatio = $origWidth / $origHeight;
        if ($this->width () > 0 && $this->height () > 0) {
            return array (round ($this->width ()), round ($this->height ()));
        } else if ($this->width () > 0) {
            return array (round ($this->width ()), round ($this->width() / $sizeRatio));
        } else if ($this->height () > 0) {
            return array (round ($this->height () * $sizeRatio), round ($this->height ()));
        }
        throw new Exception ('No image width or height has been set!');
    }

    /**
     * Return min resized image width and height
     * by predefined parameters
     *
     * This method returns width and height of the origin
     * image size ratio which won't have any white spaces
     * in edges. This width or height can be higher than
     * the preset resized one (one of them in case of size
     * ratio is different)
     *
     * Width and Height of the new image has to be set before
     *
     * Result is in array (width, height)
     *
     * @return array
     * @throws Exception
     */
    public function getMinResizedImageCutSize ()
    {
        if (!$this->width () || !$this->height ()) {
            throw new Exception ('Both width and height of the resized image must be set to cut image!');
        }
        list ($origWidth, $origHeight) = $this->getOriginImageSize ();
        list ($resizedWidth, $resizedHeight) = $this->getResizedImageSize ();

        $widthRatio = $origWidth / $resizedWidth;
        $heightRatio = $origHeight / $resizedHeight;

        $cutWidth = null;
        $cutHeight = null;

        if ($widthRatio > $heightRatio) {
            // height has to resize the width
            $cutWidth  = round ($origWidth / $heightRatio);
            $cutHeight = $resizedHeight;
        } else {
            $cutWidth  = $resizedWidth;
            $cutHeight = round ($origHeight / $widthRatio);
        }

        return array (
            $cutWidth,
            $cutHeight
        );
    }

    public function getOriginOffsetCutSizesByAlignStyle ()
    {
        // get resized width and height
        list ($minResizedWidth, $minResizedHeight) = $this->getMinResizedImageCutSize ();
        // get final size
        list ($resizedWidth, $resizedHeight) = $this->getResizedImageSize ();

        $offsetX = 0;
        $offsetY = 0;

        switch ($this->resizeAlign ()) {
            case 'tl':    // Top left
                $offsetX = 0;
                $offsetY = 0;
                break;
            case 'tm':    // Top middle
                $offsetX = 0.5 * ($minResizedWidth - $resizedWidth);
                $offsetY = 0;
                break;
            case 'tr':    // Top Right
                $offsetX = $minResizedWidth - $resizedWidth;
                $offsetY = 0;
                break;
            case 'ml':    // Middle left
                $offsetX = 0;
                $offsetY = 0.5 * ($minResizedHeight - $resizedHeight);
                break;
            case '':
            case 'mm':    // Middle middle
                $offsetX = 0.5 * ($minResizedWidth - $resizedWidth);
                $offsetY = 0.5 * ($minResizedHeight - $resizedHeight);
                break;
            case 'mr':    // Middle right
                $offsetX = $minResizedWidth - $resizedWidth;
                $offsetY = 0.5 * ($minResizedHeight - $resizedHeight);
                break;
            case 'bl':    // Bottom left
                $offsetX = 0;
                $offsetY = $minResizedHeight - $resizedHeight;
                break;
            case 'bm':    // Bottom middle
                $offsetX = 0.5 * ($minResizedWidth - $resizedWidth);
                $offsetY = $minResizedHeight - $resizedHeight;
                break;
            case 'br':    // Bottom right
                $offsetX = $minResizedWidth - $resizedWidth;
                $offsetY = $minResizedHeight - $resizedHeight;
                break;
            default:
                throw new Exception (sprintf ("Resize Align parameter value '%s' is not allowed!", $this->resizeAlign ()));
        }

        return array (
            round ($offsetX),
            round ($offsetY),
        );
    }

    /* PUBLIC METHODS */

    /**
     * Returns real image file path on the disk
     *
     * @return string
     */
    public function getRealImageFilePath ()
    {
        return $this->path () . '/' . $this->name () . '.' . $this->extension ();
    }

    /**
     * returns array of predefined options from the image URL
     * in format
     * array (
     *     option1 => value1,
     *     option2 => value2,
     *     ...
     * )
     * @return array
     */
    public function getOptions ()
    {
        if (!$this->url) return array ();
        if (self::$_cacheOptions && is_array (self::$_cacheOptions)) {
            return self::$_cacheOptions;
        }

        $splitUrl = explode ('__', basename ($this->url, '.' . $this->extension ()));
        if (count ($splitUrl) < 2) {
            return array ();
        }

        $options    = array ();
        $optionsArr = explode ('-', $splitUrl [count ($splitUrl) - 1]);    // last part
        foreach ($optionsArr as $option) {
            $key   = substr($option, 0, 1);

            if (!$key) continue;

            $value = preg_replace ("/^[a-z]{1}\\:/", '', $option);
            if (is_numeric ($value)) $value = intval ($value);
            $options [$key] = $value;
        }

        self::$_cacheOptions = $options;
        return $options;

    }

    /**
     * Return respective value of the option
     * @param $option
     * @return null|option value
     */
    public function getOption ($option)
    {
        $options = $this->getOptions ();
        if (isset ($options [$option])) {
            return $options [$option];
        }
        return null;
    }

    /**
     * Validation of input options
     *
     * @throws Exception
     */
    public function checkOptions ()
    {
        if ($this->resizeStyle () && !in_array ($this->resizeStyle (), $this->resizeStyleAllowedValues)) {
            throw new Exception (sprintf ("Resize style '%s' is not valid value!", $this->resizeStyle ()));
        } else if ($this->resizeAlign () && !in_array ($this->resizeAlign (), $this->resizeAlignAllowedValues   )) {
            throw new Exception (sprintf ("Resize align '%s' is not valid value!", $this->resizeAlign ()));
        }
    }

    /**
     * Returns image url which is set in image object
     * @return string
     */
    public function url ()
    {
        return $this->url;
    }

    /**
     * Sets image url to the object
     * @param string value of the parameter
     */
    public function setUrl ($value)
    {
        $this->url = $value;
        // need to reset cache
        self::$_cacheOptions = null;
    }

    /**
     * Getter for image name without extension and options
     * @return string
     * @throws Exception
     */
    public function name ()
    {
        if ($this->name) return $this->name;

        $name = explode ('__', basename ($this->url, '.' . $this->extension ()));
        if (!count ($name)) {
            throw new Exception ('No name of image has been set in url!');
        } else if (count ($name) < 2) {
            $this->name = $name [0];
        } else {
            array_pop ($name);    // remove last __part with options
            $this->name = implode ('__', $name);
        }
        $this->name = strval ($this->name);

        return $this->name;
    }

    /**
     * Getter for image path on the disk
     * @return string
     */
    public function path ()
    {
        if ($this->path) return $this->path;

        $path = pathinfo ($this->url);
        $this->path = dirname (__FILE__) . '/' . $this->baseRoot . (isset ($path ['dirname']) ? strval ($path ['dirname']) : '');
        return $this->path;
    }

    /**
     * Getter for image extension
     * @return string
     */
    public function extension ()
    {
        if ($this->extension) return $this->extension;

        $splitName = explode ('.', $this->url);
        $this->extension = strval ($splitName [count ($splitName) - 1]);
        return $this->extension;
    }

    /**
     * Getter for image watermark url
     * @return string
     */
    public function urlWatermark ()
    {
        return $this->urlWatermark;
    }

    /**
     * Sets image watermark url to the object
     * @param string value of the parameter
     */
    public function setUrlWatermark ($urlWatermark)
    {
        $this->urlWatermark = $urlWatermark;
    }

    /**
     * Getter for image watermark ratio
     * @see $watermarkRatio
     * @return float
     */
    public function watermarkRatio ()
    {
        return $this->watermarkRatio;
    }

    /**
     * Sets image watermark ratio to the object
     * @param $ratio float watermark / image size ratio
     */
    public function setWatermarkRatio ($ratio)
    {
        $this->watermarkRatio = $ratio;
    }

    /**
     * If enableWatermark param is set
     * a watermark will be a part of image
     *
     * @param bool $enable
     */
    public function enableWatermark ($enable)
    {
        $this->enableWatermark = $enable;
    }

    /**
     * Return true if the watermark of the image is enabled
     * @return bool
     */
    public function isWatermarkEnabled ()
    {
        return $this->enableWatermark;
    }

    /**
     * Getter for image width
     * @return int
     */
    public function width ()
    {
        if ($this->width) return $this->width;

        $this->width = intval ($this->getOption (self::OPTION_WIDTH_PARAM));
        return $this->width;
    }

    /**
     * Sets image width to the object
     * @param string value of the parameter
     */
    public function setWidth ($value)
    {
        $this->width = $value;
    }

    /**
     * Getter for image height
     * @return int
     */
    public function height ()
    {
        if ($this->height) return $this->height;

        $this->height = intval ($this->getOption (self::OPTION_HEIGHT_PARAM));
        return $this->height;
    }

    /**
     * Sets image height to the object
     * @param string value of the parameter
     */
    public function setHeight ($value)
    {
        $this->height = $value;
    }

    /**
     * Getter for image quality
     * @return int
     */
    public function quality ()
    {
        if ($this->quality) return $this->quality;

        $this->quality = intval ($this->getOption (self::OPTION_QUALITY_PARAM));
        return $this->quality;
    }

    public function qualityPNG ()
    {
        $quality = round (self::QUALITY_PNG_CONSTANT * $this->quality ());
        return $quality;
    }

    /**
     * Sets image quality to the object
     * @param string value of the parameter
     */
    public function setQuality ($value)
    {
        $this->quality = $value;
    }

    /**
     * Getter for image rotation
     * @return int
     */
    public function rotation ()
    {
        if ($this->rotation) return $this->rotation;

        $this->rotation = intval ($this->getOption (self::OPTION_ROTATION_PARAM));
        return $this->rotation;
    }

    /**
     * Sets image rotation to the object
     * @param string value of the parameter
     */
    public function setRotation ($value)
    {
        $this->rotation = $value;
    }

    /**
     * Getter for image resize style
     * @see $resizeStyle
     * @return string
     */
    public function resizeStyle ()
    {
        if ($this->resizeStyle) return $this->resizeStyle;

        $this->resizeStyle = strval ($this->getOption (self::OPTION_RESIZE_STYLE_PARAM));
        return $this->resizeStyle;
    }

    /**
     * Sets image resize style to the object
     * @see $resizeStyle
     * @param string value of the parameter
     */
    public function setResizeStyle ($value)
    {
        $this->resizeStyle = $value;
    }

    /**
     * Getter for resize align
     * @see $resizeAlign
     * @return string
     */
    public function resizeAlign ()
    {
        if ($this->resizeAlign) return $this->resizeAlign;

        $this->resizeAlign = strval ($this->getOption (self::OPTION_RESIZE_ALIGN_PARAM));
        return $this->resizeAlign;
    }

    /**
     * Sets image resize align to the object
     * @see $resizeAlign
     * @param string value of the parameter
     */
    public function setResizeAlign ($value)
    {
        $this->resizeAlign = $value;
    }

    /**
     * All important controls of data validation
     * before any result
     */
    public function validate ()
    {
        $this->checkOptions ();
    }

    /**
     * Creates link from the predefined params in the object
     *
     * @return string
     * @throws Exception
     */
    public function createLink ()
    {
        if (!$this->url) {
            throw new Exception ('Cannot create link! Image does not have the url param set!');
        }

        $this->validate ();

        $link  =  dirname ($this->url ()) . '/';
        $link .=  $this->name () . '__';
        $link .= ($this->width ())       ? '-' . self::OPTION_WIDTH_PARAM        . ':' . $this->width ()       : '';
        $link .= ($this->height ())      ? '-' . self::OPTION_HEIGHT_PARAM       . ':' . $this->height ()      : '';
        $link .= ($this->quality ())     ? '-' . self::OPTION_QUALITY_PARAM      . ':' . $this->quality ()     : '';
        $link .= ($this->rotation ())    ? '-' . self::OPTION_ROTATION_PARAM     . ':' . $this->rotation ()    : '';
        $link .= ($this->resizeStyle ()) ? '-' . self::OPTION_RESIZE_STYLE_PARAM . ':' . $this->resizeStyle () : '';
        $link .= ($this->resizeAlign ()) ? '-' . self::OPTION_RESIZE_ALIGN_PARAM . ':' . $this->resizeAlign () : '';
        $link .= '.' . $this->extension ();
        return $link;
    }

    public function addWatermark ($image)
    {
        if (!$this->urlWatermark ()) {
            // no watermark is set
            throw new Exception ('Watermark url is not set!');
        }
        $watermark = new ImageData ();
        $watermark->setUrl ($this->urlWatermark);
        $watermark->enableWatermark (true);
        $watermark->setWidth (round ($this->width () * $this->watermarkRatio ()));

        list ($iWidth, $iHeight) = $this->getResizedImageSize ();
        list ($wWidth, $wHeight) = $watermark->getResizedImageSize ();
        $offset = min (round (0.05 * $iWidth), round (0.05 * $iHeight));

        $imageWatermark = $watermark->getPreparedImage ();

        imagecopy (
            $image,
            $imageWatermark,
            $iWidth - $wWidth - $offset,
            $iHeight - $wHeight - $offset,
            0,
            0,
            $wWidth,
            $wHeight
        );

        imagedestroy ($imageWatermark);
        return $image;
    }

    /**
     * Return cache file name by url
     * @return string
     */
    public function cacheGetFileName ()
    {
        return md5 (basename ($this->url ())) . '.cache';
    }

    /**
     * Return cache directory of the image by url
     * @return string
     */
    public function cacheGetFilePath ()
    {
        return $this->cacheDir . dirname ($this->url ());
    }

    public function cacheIsFileStored ()
    {
        $this->cacheValidate ();
        $cacheDirImageFile = $this->cacheGetFilePath () . '/' . $this->cacheGetFileName ();
        return file_exists ($cacheDirImageFile);
    }

    public function cacheValidate ()
    {
        $cacheDirImageFile = $this->cacheGetFilePath () . '/' . $this->cacheGetFileName ();
        $originalDirImageFile = $this->getRealImageFilePath ();
        if (file_exists ($cacheDirImageFile) && filemtime ($cacheDirImageFile) < filemtime($originalDirImageFile)) {
            unlink ($cacheDirImageFile);
        }
    }

    /**
     * Saves image resource to the storage
     *
     * @param $image resource
     */
    public function cacheSaveImage ($image)
    {
        $cacheDirImage = $this->cacheGetFilePath ();
        // Make Directory if not exists
        if (!is_dir ($cacheDirImage)) {
            mkdir($cacheDirImage, 0777, true);
        }
        // Unlink previous image if exists
        $cacheDirImageFile = $cacheDirImage . '/' . $this->cacheGetFileName ();
        if ($this->cacheIsFileStored ()) {
            unlink ($cacheDirImageFile);
        }
        // Save cache
        $this->printImage ($image, $cacheDirImageFile);
        chmod ($cacheDirImageFile, 0777);
    }

    /**
     * Returns image resource form the case storage in case of exists
     * @return null|resource
     */
    public function cacheGetImage ()
    {
        $cacheDirImage     = $this->cacheGetFilePath ();
        $cacheDirImageFile = $cacheDirImage . '/' . $this->cacheGetFileName ();
        if ($this->cacheIsFileStored ()) {
            return $this->getImage ($cacheDirImageFile);
        }
        return null;
    }

    /**
     * Method only covers prepare image function
     * to get it from another object
     * @return resource
     */
    public function getPreparedImage ()
    {
        $image = $this->prepareImage ();
        return $image;
    }

    /**
     * Publish image to the browser (as a result of the request)
     *
     * @param bool $cached
     * @param string filepath to save image to the storage
     */
    public function publish ($cached = true, $filename = null)
    {
        $image = null;
        // read cache
        if ($cached) {
            $image = $this->cacheGetImage ();
        }

        $this->validate ();

        // generate image if cache does not exist
        if (!$image) {
            $image = $this->prepareImage ();
            if ($this->isWatermarkEnabled ()) {
                $image = $this->addWatermark ($image);
            }
        }
        // check the image resource
        if (!$image) {
            throw new Exception ("Error reading image!");
        }
        // write cache
        if ($cached && !$this->cacheIsFileStored ()) {
            $this->cacheSaveImage ($image);
        }
        // print image
        $this->printImage ($image, $filename);
        imagedestroy ($image);

    }

}