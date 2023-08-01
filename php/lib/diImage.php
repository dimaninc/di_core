<?php
/*
	// dimaninc

	// 2011/08/23
		* some watermark stuff added

	// 2010/01/11
		* ::make_thumb_or_copy()

	// 2010/03/30
		* transparent bug fixed
		* zooming of small pics while making thumbs bug fixed

	// 2010/09/15
		* merging wm in make_thumb_or_copy bug fixed

	// 2009/12/25
		* birthday

*/

define('IMG_TYPE_GIF', 1);
define('IMG_TYPE_JPG', 2);
define('IMG_TYPE_JPEG', 2);
define('IMG_TYPE_PNG', 3);

$image_type_to_ext_ar = [
    IMG_TYPE_GIF => 'gif',
    IMG_TYPE_JPEG => 'jpeg',
    IMG_TYPE_PNG => 'png',
];

// thumbnail modes
define('DI_THUMB_MODE_MASK', 0x000f);
define('DI_THUMB_FIT', 0x0001);
define('DI_THUMB_CROP', 0x0002);

// constants for cropping
define('DI_THUMB_X_POSITION_MASK', 0x0f00);
define('DI_THUMB_CENTER', 0x0000);
define('DI_THUMB_LEFT', 0x0100);
define('DI_THUMB_RIGHT', 0x0200);

define('DI_THUMB_Y_POSITION_MASK', 0xf000);
define('DI_THUMB_MIDDLE', 0x0000);
define('DI_THUMB_TOP', 0x1000);
define('DI_THUMB_BOTTOM', 0x2000);

define('DI_THUMB_EXPAND_SIZE_MASK', 0x00f0);
define('DI_THUMB_KEEP_SIZE', 0x0000);
define('DI_THUMB_EXPAND_TO_SIZE', 0x0010);

class diImage
{
    const TYPE_GIF = 1;
    const TYPE_JPG = 2;
    const TYPE_JPEG = 2;
    const TYPE_PNG = 3;
    const TYPE_SWF = 4;
    const TYPE_SWC = 13;
    const TYPE_HTML5 = 98;
    const TYPE_SWIFFY = 99;

    public static $typeTitles = [
        self::TYPE_GIF => 'Gif',
        self::TYPE_JPEG => 'Jpeg',
        self::TYPE_PNG => 'Png',
        self::TYPE_SWF => 'Swf',
        self::TYPE_SWC => 'Swc',
        self::TYPE_HTML5 => 'HTML5',
        self::TYPE_SWIFFY => 'Swiffy',
    ];

    public static $typeExtensions = [
        self::TYPE_GIF => 'gif',
        self::TYPE_JPEG => 'jpg',
        self::TYPE_PNG => 'png',
    ];

    public static $typeNames = [
        self::TYPE_GIF => 'gif',
        self::TYPE_JPEG => 'jpeg',
        self::TYPE_PNG => 'png',
    ];

    const DI_THUMB_MODE_MASK = 0x000f;
    const DI_THUMB_FIT = 0x0001;
    const DI_THUMB_CROP = 0x0002;

    const DI_THUMB_X_POSITION_MASK = 0x0f00;
    const DI_THUMB_CENTER = 0x0000;
    const DI_THUMB_LEFT = 0x0100;
    const DI_THUMB_RIGHT = 0x0200;

    const DI_THUMB_Y_POSITION_MASK = 0xf000;
    const DI_THUMB_MIDDLE = 0x0000;
    const DI_THUMB_TOP = 0x1000;
    const DI_THUMB_BOTTOM = 0x2000;

    const DI_THUMB_EXPAND_SIZE_MASK = 0x00f0;
    const DI_THUMB_KEEP_SIZE = 0x0000;
    const DI_THUMB_EXPAND_TO_SIZE = 0x0010;

    const MAX_GD_WIDTH = 4500;
    const MAX_GD_HEIGHT = 3500;

    const EXT_WEBP = '.webp';

    public $image;
    public $w, $h, $t;
    public $orig_fn;
    public $jpeg_quality;
    /** @var callable|null */
    public $post_function;
    public $dst_type;
    public $bg_color; // = array(0xFF, 0xFF, 0xFF);

    public $changed; // watermarked, etc.

    private $transparent; // gif using this

    // for gif
    public $info;

    public function __construct($fn = null)
    {
        //$this->image = false;
        $this->changed = false;
        $this->orig_fn = '';
        $this->w = 0;
        $this->h = 0;
        $this->t = 0;

        $this->jpeg_quality = 90;
        $this->post_function = null;

        $this->info = false;

        $this->set_bg_color('#ffffff');

        if (!is_null($fn)) {
            $this->open($fn);
        }
    }

    public static function typeExt($type)
    {
        return static::$typeExtensions[$type];
    }

    public static function typeName($type)
    {
        return static::$typeNames[$type];
    }

    public static function isImageType($type)
    {
        return in_array($type, [self::TYPE_GIF, self::TYPE_JPEG, self::TYPE_PNG]);
    }

    public static function isFlashType($type)
    {
        return in_array($type, [self::TYPE_SWF, self::TYPE_SWC]);
    }

    function open($fn)
    {
        $this->orig_fn = $fn;
        list($this->w, $this->h, $this->t) = is_file($fn)
            ? getimagesize($fn)
            : [0, 0, 0];

        if (
            $this->t >= 1 &&
            $this->t <= 3 &&
            $this->w * $this->h > self::MAX_GD_WIDTH * self::MAX_GD_HEIGHT &&
            class_exists('IMagick')
        ) {
            $im = new \Imagick($fn);
            $im->resizeImage(
                self::MAX_GD_WIDTH,
                self::MAX_GD_HEIGHT,
                \Imagick::FILTER_CATROM,
                1,
                true
            );
            $im->writeImage();
            $im->clear();
            $im->destroy();

            unset($im);

            list($this->w, $this->h, $this->t) = getimagesize($fn);
        }

        if (
            $this->t >= 1 &&
            $this->t <= 3 &&
            $this->w * $this->h <= self::MAX_GD_WIDTH * self::MAX_GD_HEIGHT
        ) {
            $create_func = static::createFunction($this->t);
            $this->image = $create_func($fn);

            $this->read_info();
        } else {
            $this->image = false;
        }

        return $this->image;
    }

    public function loaded()
    {
        return !!$this->image;
    }

    function store($dst_fn = '', $image = false)
    {
        $dst_type = $this->dst_type ?: $this->t;

        if (!$image) {
            $image = $this->image;
        }

        if ($this->post_function) {
            $image = $this->post_function($image);
        }

        $q = $dst_type == 3 ? round($this->jpeg_quality / 10) : $this->jpeg_quality;
        if ($q >= 10 && $dst_type == 3) {
            $q = 9;
        }

        $store_func = $this->storeFunction($dst_type);

        return $store_func ? $store_func($image, $dst_fn, $q) : false;
    }

    function set_bg_color($html_color)
    {
        $this->bg_color = rgb_color($html_color);
    }

    function read_info()
    {
        switch ($this->t) {
            case 1:
                // calculating transparent color
                $this->info = new GifInfo($this->orig_fn);
                if (
                    $this->info->m_version == '89a' &&
                    $this->info->m_colorFlag == 1
                ) {
                    $this->transparent = imagecolorallocate(
                        $this->image,
                        $this->info->m_transparentRed,
                        $this->info->m_transparentGreen,
                        $this->info->m_transparentBlue
                    );
                } else {
                    $this->transparent = imagecolorat($this->image, 0, 0);
                }
                //

                break;
        }
    }

    function close()
    {
        if ($this->image) {
            imagedestroy($this->image);
        }

        $this->image = null;
        $this->orig_fn = '';
        $this->w = 0;
        $this->h = 0;
        $this->t = 0;
    }

    function set_jpeg_quality($jpeg_quality)
    {
        $this->jpeg_quality = $jpeg_quality;
    }

    function set_post_function($post_function)
    {
        $this->post_function = $post_function;
    }

    public function getDstType()
    {
        return $this->dst_type;
    }

    function set_dst_type($type)
    {
        if (isset(self::$typeExtensions[$type])) {
            $this->dst_type = $type;
        } else {
            $this->dst_type = self::getTypeByExt($type);
        }

        return $this;
    }

    public static function getTypeByExt($ext)
    {
        $ext = strtolower($ext);

        if ($ext == 'jpeg') {
            $ext = 'jpg';
        }

        return array_search($ext, self::$typeExtensions);
    }

    function get_thumb($mode, $w, $h = 0)
    {
        if (!$this->image) {
            return false;
        }

        if (($mode & DI_THUMB_EXPAND_SIZE_MASK) == DI_THUMB_EXPAND_TO_SIZE) {
            if (
                $w >= $this->w &&
                $h >= $this->h &&
                ($mode & DI_THUMB_MODE_MASK) == DI_THUMB_CROP
            ) {
                $mode &=
                    DI_THUMB_X_POSITION_MASK |
                    DI_THUMB_Y_POSITION_MASK |
                    DI_THUMB_EXPAND_SIZE_MASK;
                $mode |= DI_THUMB_FIT;
            }
        }

        list(
            $src_w,
            $src_h,
            $src_x,
            $src_y,
            $dst_w,
            $dst_h,
            $dst_x,
            $dst_y,
        ) = $this->calculate_dst_dimentsions($mode, $w, $h);

        if (
            ($mode & DI_THUMB_EXPAND_SIZE_MASK) == DI_THUMB_KEEP_SIZE ||
            (($mode & DI_THUMB_EXPAND_SIZE_MASK) == DI_THUMB_EXPAND_TO_SIZE &&
                ($mode & DI_THUMB_MODE_MASK) == DI_THUMB_FIT)
        ) {
            $w = $dst_w;
            $h = $dst_h;
        }

        // preparations
        switch ($this->t) {
            case 1: //gif
                $dst_img = imagecreate($w, $h); //($dst_w, $dst_h);
                imagepalettecopy($dst_img, $this->image);
                break;

            case 3: //png
                $dst_img = imagecreatetruecolor($w, $h); //($dst_w, $dst_h);
                //imagealphablending($this->image, true);
                imagealphablending($dst_img, false);
                imagesavealpha($dst_img, true);
                break;

            default:
                $dst_img = imagecreatetruecolor($w, $h); //($dst_w, $dst_h);
                break;
        }

        if ($dst_w >= $src_w || $dst_h >= $src_h) {
            $bg = imagecolorallocate(
                $dst_img,
                $this->bg_color[0],
                $this->bg_color[1],
                $this->bg_color[2]
            );
            imagefilledrectangle($dst_img, 0, 0, $w, $h, $bg);
        }

        // doing it
        imagecopyresampled(
            $dst_img,
            $this->image,
            $dst_x,
            $dst_y,
            $src_x,
            $src_y,
            $dst_w,
            $dst_h,
            $src_w,
            $src_h
        );

        switch ($mode & DI_THUMB_MODE_MASK) {
            case DI_THUMB_CROP:
                break;

            case DI_THUMB_FIT:
            default:
                break;
        }
        //

        // post processes
        switch ($this->t) {
            case 1: //gif
                // for transparent gif
                $pixel_over_black = imagecolorat($dst_img, 0, 0);

                $bg = imagecolorallocate($dst_img, 255, 255, 255);
                imagefilledrectangle($dst_img, 0, 0, $w, $h, $bg);
                imagecopyresized(
                    $dst_img,
                    $this->image,
                    $dst_x,
                    $dst_y,
                    $src_x,
                    $src_y,
                    $dst_w,
                    $dst_h,
                    $src_w,
                    $src_h
                );

                $pixel_over_white = imagecolorat($dst_img, 0, 0);

                if ($pixel_over_black != $pixel_over_white) {
                    imagefilledrectangle($dst_img, 0, 0, $w, $h, $this->transparent);
                    imagecopyresized(
                        $dst_img,
                        $this->image,
                        $dst_x,
                        $dst_y,
                        $src_x,
                        $src_y,
                        $dst_w,
                        $dst_h,
                        $src_w,
                        $src_h
                    );
                    imagecolortransparent($dst_img, $this->transparent);
                }
                //
                break;

            default:
                break;
        }
        //

        return $dst_img;
    }

    function make_thumb(
        $mode,
        $dst_fn,
        $w,
        $h = 0,
        $sharpen = false,
        $wm = false,
        $wm_x_pos = 'left',
        $wm_y_pos = 'top'
    ) {
        if (!$this->image) {
            return false;
        }

        $dst_img = $this->get_thumb($mode, $w, $h);

        if ($sharpen) {
            self::sharpMask($dst_img, 80, 0.5, 0);
        }

        if (
            $wm &&
            is_file(
                get_absolute_path() .
                    diConfiguration::getFolder() .
                    diConfiguration::get($wm)
            )
        ) {
            $this->merge_wm_to(
                $dst_img,
                get_absolute_path() .
                    diConfiguration::getFolder() .
                    diConfiguration::get($wm),
                $wm_x_pos,
                $wm_y_pos
            );
        }

        $this->store($dst_fn, $dst_img);
        imagedestroy($dst_img);

        return true;
    }

    function make_thumb_or_copy(
        $mode,
        $dst_fn,
        $w,
        $h = 0,
        $sharpen = false,
        $wm = false,
        $wm_x_pos = 'left',
        $wm_y_pos = 'top'
    ) {
        if (!$this->image) {
            return false;
        }

        if (($this->w <= $w && $this->h <= $h) || (!$w && !$h)) {
            if ($this->changed) {
                $this->store($dst_fn);

                return true;
            } elseif (!$sharpen && !$wm) {
                copy($this->orig_fn, $dst_fn);

                return true;
            }
        }

        $dst_img = $this->get_thumb($mode, $w, $h);

        if ($sharpen) {
            self::sharpMask($dst_img, 80, 0.5, 0);
        }

        if (
            $wm &&
            diConfiguration::exists($wm) &&
            is_file(
                get_absolute_path() .
                    diConfiguration::getFolder() .
                    diConfiguration::get($wm)
            )
        ) {
            $this->merge_wm_to(
                $dst_img,
                get_absolute_path() .
                    diConfiguration::getFolder() .
                    diConfiguration::get($wm),
                $wm_x_pos,
                $wm_y_pos
            );
        }

        $this->store($dst_fn, $dst_img);
        imagedestroy($dst_img);

        return true;
    }

    function get_grayscale()
    {
        if (!$this->image) {
            return false;
        }

        $dst_img = imagecreate($this->w, $this->h);
        $palette = [];

        for ($c = 0; $c < 256; $c++) {
            $palette[$c] = imagecolorallocate($dst_img, $c, $c, $c);
        }

        for ($y = 0; $y < $this->h; $y++) {
            for ($x = 0; $x < $this->w; $x++) {
                $rgb = imagecolorat($this->image, $x, $y);
                $r = ($rgb >> 16) & 0xff;
                $g = ($rgb >> 8) & 0xff;
                $b = $rgb & 0xff;

                $gs = yiq($r, $g, $b);
                imagesetpixel($dst_img, $x, $y, $palette[$gs]);
            }
        }

        return $dst_img;
    }

    function make_grayscale($dst_fn)
    {
        if (!$this->image) {
            return false;
        }

        $dst_img = $this->get_grayscale();

        $this->store($dst_fn, $dst_img);
        imagedestroy($dst_img);

        return true;
    }

    public function sharpen()
    {
        if (!$this->image) {
            return false;
        }

        self::sharpMask($this->image, 80, 0.5, 0);

        return true;
    }

    // if x/y is positive, then watermark is related to top left corner
    // if negative - to right bottom
    function merge_wm($wm_fn, $x = 'left', $y = 'top')
    {
        $this->changed = true;

        return $this->merge_wm_to($this->image, $wm_fn, $x, $y);
    }

    function merge_wm_to(&$image, $wm_fn, $x = 'left', $y = 'top')
    {
        if (is_resource($wm_fn)) {
            $wm_w = imagesx($wm_fn);
            $wm_h = imagesy($wm_fn);
            $wm_t = 3;
        } else {
            list($wm_w, $wm_h, $wm_t) = is_file($wm_fn)
                ? getimagesize($wm_fn)
                : [0, 0, 0];
        }

        if (!$wm_w) {
            return false;
        }

        if ($wm_t == 3) {
            imagealphablending($image, true);
        }

        if ($x == 'left') {
            $x = 0;
        } elseif ($x == 'right') {
            $x = imagesx($image) - $wm_w;
        } elseif ($x == 'center') {
            $x = imagesx($image) - $wm_w >> 1;
        } else {
            $x = $x >= 0 ? $x : imagesx($image) - $wm_w + (int) $x;
        }

        if ($y == 'top') {
            $y = 0;
        } elseif ($y == 'bottom') {
            $y = imagesy($image) - $wm_h;
        } elseif ($y == 'center') {
            $y = imagesy($image) - $wm_h >> 1;
        } else {
            $y = $y >= 0 ? $y : imagesy($image) - $wm_h + (int) $y;
        }

        if (is_resource($wm_fn)) {
            $wm_img = $wm_fn;
        } else {
            $create_func = static::createFunction($wm_t);
            $wm_img = $create_func($wm_fn);
        }

        imagecopy($image, $wm_img, $x, $y, 0, 0, $wm_w, $wm_h);
        imagedestroy($wm_img);

        return true;
    }

    function get_flipped_horizontal()
    {
        if (!$this->image) {
            return false;
        }

        $dst_img = imagecreatetruecolor($this->w, $this->h);

        for ($x = 0; $x < $this->w; $x++) {
            imagecopy(
                $dst_img,
                $this->image,
                $x,
                0,
                $this->w - $x - 1,
                0,
                1,
                $this->h
            );
        }

        return $dst_img;
    }

    function flip_horizontal()
    {
        if (!$this->image) {
            return false;
        }

        $tmp = $this->get_flipped_horizontal();

        imagedestroy($this->image);

        $this->image = $tmp;

        return true;
    }

    function get_flipped_vertical()
    {
        if (!$this->image) {
            return false;
        }

        $dst_img = imagecreatetruecolor($this->w, $this->h);

        for ($y = 0; $y < $this->h; $y++) {
            imagecopy(
                $dst_img,
                $this->image,
                0,
                $y,
                0,
                $this->h - $y - 1,
                $this->w,
                1
            );
        }

        return $dst_img;
    }

    function flip_vertical()
    {
        if (!$this->image) {
            return false;
        }

        $tmp = $this->get_flipped_vertical();

        imagedestroy($this->image);

        $this->image = $tmp;

        return true;
    }

    /* ----------------------------------------------------------------------------------- */

    function calculate_dst_dimentsions($mode, $w, $h)
    {
        $src_w = $this->w;
        $src_h = $this->h;

        $src_x = 0;
        $src_y = 0;

        $dst_w = $this->w;
        $dst_h = $this->h;

        $dst_x = 0;
        $dst_y = 0;

        switch ($mode & DI_THUMB_MODE_MASK) {
            case DI_THUMB_CROP:
                $dst_w = $w;
                $dst_h = $h;

                if (!$w || !$h) {
                    throw new \Exception('Both dimensions for cropping needed');
                }

                if ($src_w / $src_h > $w / $h) {
                    $src_w = round($w * ($src_h / $h));
                } else {
                    $src_h = round($h * ($src_w / $w));
                }

                // x
                switch ($mode & DI_THUMB_X_POSITION_MASK) {
                    case DI_THUMB_LEFT:
                        $src_x = 0;
                        break;

                    case DI_THUMB_RIGHT:
                        $src_x = $this->w - $src_w;
                        break;

                    default:
                    case DI_THUMB_CENTER:
                        $src_x = round(($this->w - $src_w) / 2);
                        break;
                }

                // y
                switch ($mode & DI_THUMB_Y_POSITION_MASK) {
                    case DI_THUMB_TOP:
                        $src_y = 0;
                        break;

                    case DI_THUMB_BOTTOM:
                        $src_y = $this->h - $src_h;
                        break;

                    default:
                    case DI_THUMB_MIDDLE:
                        $src_y = round(($this->h - $src_h) / 2);
                        break;
                }

                break;

            case DI_THUMB_FIT:
            default:
                if ($w && $dst_w > $w) {
                    $dst_h = round($dst_h / ($dst_w / $w));
                    $dst_w = $w;
                }

                if ($h && $dst_h > $h) {
                    $dst_w = round($dst_w / ($dst_h / $h));
                    $dst_h = $h;
                }

                break;
        }

        if (($mode & DI_THUMB_EXPAND_SIZE_MASK) == DI_THUMB_EXPAND_TO_SIZE) {
            if ($dst_w < $w || $dst_h < $h) {
                if ($w / $h > $dst_w / $dst_w) {
                    $dst_h = round($dst_h / ($dst_w / $w));
                    $dst_w = $w;
                } else {
                    $dst_w = round($dst_w / ($dst_h / $h));
                    $dst_h = $h;
                }
            }
        }

        return [$src_w, $src_h, $src_x, $src_y, $dst_w, $dst_h, $dst_x, $dst_y];
    }

    public static function createFunction($img_type)
    {
        $func_suffix =
            $img_type >= 1 && $img_type <= 3 ? self::$typeNames[$img_type] : '';

        return $func_suffix ? "imagecreatefrom{$func_suffix}" : '';
    }

    public static function storeFunction($img_type)
    {
        $func_suffix =
            $img_type >= 1 && $img_type <= 3 ? self::$typeNames[$img_type] : '';

        return $func_suffix ? "image{$func_suffix}" : '';
    }

    public static function sharpMask($img, $amount, $radius, $threshold)
    {
        //// p h p U n s h a r p M a s k
        //// Unsharp mask algorithm by Torstein Honsi 2003.
        //// thoensi_at_netcom_dot_no.
        //// Please leave this notice.

        // Attempt to calibrate the parameters to Photoshop:
        if ($amount > 500) {
            $amount = 500;
        }
        $amount = $amount * 0.016;
        if ($radius > 50) {
            $radius = 50;
        }
        $radius = $radius * 2;
        if ($threshold > 255) {
            $threshold = 255;
        }

        $radius = abs(round($radius)); // Only integers make sense.
        if ($radius == 0) {
            return $img;
        }

        $w = imagesx($img);
        $h = imagesy($img);
        $imgCanvas = imagecreatetruecolor($w, $h);
        $imgCanvas2 = imagecreatetruecolor($w, $h);
        $imgBlur = imagecreatetruecolor($w, $h);
        $imgBlur2 = imagecreatetruecolor($w, $h);
        imagecopy($imgCanvas, $img, 0, 0, 0, 0, $w, $h);
        imagecopy($imgCanvas2, $img, 0, 0, 0, 0, $w, $h);

        // Gaussian blur matrix:
        //
        //		1		2		1
        //		2		4		2
        //		1		2		1
        //
        //////////////////////////////////////////////////

        // Move copies of the image around one pixel at the time and merge them with weight
        // according to the matrix. The same matrix is simply repeated for higher radii.
        for ($i = 0; $i < $radius; $i++) {
            imagecopy($imgBlur, $imgCanvas, 0, 0, 1, 1, $w - 1, $h - 1); // up left
            imagecopymerge($imgBlur, $imgCanvas, 1, 1, 0, 0, $w, $h, 50); // down right
            imagecopymerge($imgBlur, $imgCanvas, 0, 1, 1, 0, $w - 1, $h, 33.33333); // down left
            imagecopymerge($imgBlur, $imgCanvas, 1, 0, 0, 1, $w, $h - 1, 25); // up right
            imagecopymerge($imgBlur, $imgCanvas, 0, 0, 1, 0, $w - 1, $h, 33.33333); // left
            imagecopymerge($imgBlur, $imgCanvas, 1, 0, 0, 0, $w, $h, 25); // right
            imagecopymerge($imgBlur, $imgCanvas, 0, 0, 0, 1, $w, $h - 1, 20); // up
            imagecopymerge($imgBlur, $imgCanvas, 0, 1, 0, 0, $w, $h, 16.666667); // down
            imagecopymerge($imgBlur, $imgCanvas, 0, 0, 0, 0, $w, $h, 50); // center
            imagecopy($imgCanvas, $imgBlur, 0, 0, 0, 0, $w, $h);

            // During the loop above the blurred copy darkens, possibly due to a roundoff
            // error. Therefore the sharp picture has to go through the same loop to
            // produce a similar image for comparison. This is not a good thing, as processing
            // time increases heavily.
            imagecopy($imgBlur2, $imgCanvas2, 0, 0, 0, 0, $w, $h);
            imagecopymerge($imgBlur2, $imgCanvas2, 0, 0, 0, 0, $w, $h, 50);
            imagecopymerge($imgBlur2, $imgCanvas2, 0, 0, 0, 0, $w, $h, 33.33333);
            imagecopymerge($imgBlur2, $imgCanvas2, 0, 0, 0, 0, $w, $h, 25);
            imagecopymerge($imgBlur2, $imgCanvas2, 0, 0, 0, 0, $w, $h, 33.33333);
            imagecopymerge($imgBlur2, $imgCanvas2, 0, 0, 0, 0, $w, $h, 25);
            imagecopymerge($imgBlur2, $imgCanvas2, 0, 0, 0, 0, $w, $h, 20);
            imagecopymerge($imgBlur2, $imgCanvas2, 0, 0, 0, 0, $w, $h, 16.666667);
            imagecopymerge($imgBlur2, $imgCanvas2, 0, 0, 0, 0, $w, $h, 50);
            imagecopy($imgCanvas2, $imgBlur2, 0, 0, 0, 0, $w, $h);
        }

        // Calculate the difference between the blurred pixels and the original
        // and set the pixels
        for ($x = 0; $x < $w; $x++) {
            // each row
            for ($y = 0; $y < $h; $y++) {
                // each pixel
                $rgbOrig = imagecolorat($imgCanvas2, $x, $y);
                $rOrig = ($rgbOrig >> 16) & 0xff;
                $gOrig = ($rgbOrig >> 8) & 0xff;
                $bOrig = $rgbOrig & 0xff;

                $rgbBlur = imagecolorat($imgCanvas, $x, $y);

                $rBlur = ($rgbBlur >> 16) & 0xff;
                $gBlur = ($rgbBlur >> 8) & 0xff;
                $bBlur = $rgbBlur & 0xff;

                // When the masked pixels differ less from the original
                // than the threshold specifies, they are set to their original value.
                $rNew =
                    abs($rOrig - $rBlur) >= $threshold
                        ? max(0, min(255, $amount * ($rOrig - $rBlur) + $rOrig))
                        : $rOrig;
                $gNew =
                    abs($gOrig - $gBlur) >= $threshold
                        ? max(0, min(255, $amount * ($gOrig - $gBlur) + $gOrig))
                        : $gOrig;
                $bNew =
                    abs($bOrig - $bBlur) >= $threshold
                        ? max(0, min(255, $amount * ($bOrig - $bBlur) + $bOrig))
                        : $bOrig;

                if ($rOrig != $rNew || $gOrig != $gNew || $bOrig != $bNew) {
                    $pixCol = imagecolorallocate($img, $rNew, $gNew, $bNew);
                    imagesetpixel($img, $x, $y, $pixCol);
                }
            }
        }

        imagedestroy($imgCanvas);
        imagedestroy($imgCanvas2);
        imagedestroy($imgBlur);
        imagedestroy($imgBlur2);

        return $img;
    }

    public static function yiq($r, $g, $b)
    {
        return $r * 0.299 + $g * 0.587 + $b * 0.114;
    }
}

/** @deprecated  */
function UnsharpMask($img, $amount, $radius, $threshold)
{
    return \diImage::sharpMask($img, $amount, $radius, $threshold);
}

/** @deprecated  */
function yiq($r, $g, $b)
{
    return \diImage::yiq($r, $g, $b);
}
