<?php
/**
 * Image Controller
 * Parse path and cache/show image by get parameters
 */

include_once dirname (__FILE__) . '/ImageData.class.php';

Class ImageController
{
    /**
     * Instance of image Controller
     *
     * @var ImageController
     */
    private static $instance;

    const IMAGE_WIDTH_BASE      = 800;
    const IMAGE_QUALITY_DEFAULT = 75;
    const RESIZE_STYLE_DEFAULT  = 'r';
    const ALIGN_STYLE_DEFAULT   = 'mm';

    public static function createLink ($url, $width = self::IMAGE_WIDTH_DEFAULT, $height = null, $quality = self::IMAGE_QUALITY_DEFAULT, $rotation = 0, $resizeStyle = self::RESIZE_STYLE_DEFAULT, $resizeAlign = self::ALIGN_STYLE_DEFAULT, $urlWatermark = null)
    {
        $params = array (
            'url'          => $url,
            'width'        => $width,
            'height'       => $height,
            'quality'      => $quality,
            'rotation'     => $rotation,
            'resizeStyle'  => $resizeStyle,
            'resizeAlign'  => $resizeAlign,
            'urlWatermark' => $urlWatermark,
        );
        $image = new ImageData ($params);
        return $image->createLink ();
    }

    public static function printImage ($url)
    {
        $image = new ImageData ();
        $image->url = $url;
        $image->enableWatermark (true);
        $image->setWatermarkRatio (0.33);
        $image->urlWatermark = '/css/Watermark.png';
        print $image->publish ();
    }

}
