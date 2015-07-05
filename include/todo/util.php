<?php

/**
 * Utility class for misc methods
 *
 * @author leafo.net
 * @version 0.1
 * @package util
 * @package classes
 */
class Util
{
	/**
	 * create a thumbnail of a jpeg
	 */
	public static function shellThumbnail($source,
		$dest, $width, $crop = false)
	{
		// make sure that it is a jpeg
		$info = @getimagesize($source);
		if ($info === false) {
			// picture might be corrupted
			throw new Exception('Failed to find image: "'.$source.'"');
		}

		list($c_width, $c_height, $type) = $info;
		//echo '<pre>'.print_r($info, 1).'</pre>';

		if ($type != IMAGETYPE_JPEG)
			throw new Exception('Source image needs to be a jpg');
	
		// Use imagemagick to make thumb
		system('convert -thumbnail '.$width.
			' "'.$source.'" "'.$dest.'"');

		if ($crop == true) {
			// cut the height down to size if it is too tall
			$height = .75*$width;
			$info = getimagesize($dest);
			list($c_width, $c_height) = $info;
			if ($height >= $c_height) return; // do nothing

			$ty = ($c_height/2) - ($height/2);

			$old = imagecreatefromjpeg($dest);
			$new = imagecreatetruecolor($width, $height);
			imagecopyresampled($new, $old, 0, 0, 0, $ty, 
				$width, $height, $width, $height);
			imagejpeg($new, $dest, 100);
		}

		// Try to convet image to pnm
		/*system('djpeg "'.$source.'" > '.$tmp);
		$tmp = tempnam('/tmp', 'images_mid');
		$height = $width * .75;
		// Scale and save
		system("pnmscale -xy {$width} {$height} {$tmp} | ".
			"cjpeg -smoo 10 -qual 95 > ".'"'.$dest.'"');
		 */
	}
	

	/**
	 * Create a thumbnail of the image
	 */
	public static function imageThumbnail($source, 
		$dest, $width, $height = -1)
	{
		$info = @getimagesize($source);
		if ($info === false)
			throw new Exception('Failed to find image: "'.$source.'"');

		list($c_width, $c_height, $type) = $info;
		echo '<pre>'.print_r($info, 1).'</pre>';

		switch($type) {
		case IMAGETYPE_JPEG: 
			$image = @imagecreatefromjpeg($source);
			break;
		case IMAGETYPE_PNG:
			$image = @imagecreatefrompng($source);
			break;
		case IMAGETYPE_GIF:
			$image = @imagecreatefromgif($source);
			break;
		default:
			throw new Exception('Unknown image format');
		}

		if (!$image) throw new Exception('Failed to load image');

		$n_width = $width;
		if ($height > 0) $n_height = $height;
		else {
			$r = $width / $c_width;
			$n_height = $c_height * $r;
		}

		$new = imagecreatetruecolor($n_width, $n_height);
		imagecopyresampled($new, $image, 0,0,0,0, $n_width, $n_height,
			$c_width, $c_height);


		if (!@imagejpeg($new, $dest, 100))
			throw new Exception('Failed to write image');

		return $n_height;
	}

	/**
	 * recursively delete a directory, or file
	 */
	public static function delTree($target)
	{
		if (is_dir($target) && !is_link($target)) {
			if ($dh = opendir($target)) {
				while (($f = readdir($dh)) != false) {
					if ($f == '.' || $f == '..') continue;
					else Util::delTree($target.'/'.$f);
				}
				rmdir($target);
			} 
		} else return unlink($target);
		return false;
	}

	/**
	 * get the time a photo was taken from exif data
	 */
	public static function exifTime($image)
	{
		$data = exif_read_data($image);
		if (!$data) return false;

		if ($data['DateTime'])
			$date = $data['DateTime'];
		else if ($data['DateTimeDigitized'])
			$date = $data['DateTimeDigitized'];
		else if ($datt['DateTimeOriginal'])
			$date = $data['DateTimeOriginal'];
		else 
			return false;

		return strtotime($date);

	}

	public static function exifTimeFormatted($image)
	{
		if (($time = Util::exifTime($image)) == false)
			return null;
		else
			return date('F j, Y', $time);
	}

	public static function timestamp($date)
	{
		return date('F j, Y', $date);
	}

}



?>
