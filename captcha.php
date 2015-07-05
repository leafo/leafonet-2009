<?php

session_start();
header("Cache-control: private");

if (empty($_SESSION['captcha_code'])) exit('code not found');

header("Content-type: image/png");

$width = 120;
$height = 20;

$image = imageCreateTrueColor($width, $height);
$bg = imagecolorallocate($image, 255, 255, 255);
$color = imagecolorallocate($image, 0, 0, 255);
$line_color = imagecolorallocate($image, 150, 150, 250);

imagefilledrectangle($image, 0, 0, $width, $height, $bg);

for ($i = 0; $i < ($width/5); $i++) {
	imageline($image, 5*$i, 0, 5*($i+1), $height, $line_color);
}

$letter_width = 4;
$offset = strlen($_SESSION['captcha_code'])*$letter_width;

imagestring($image, 4, $width/2 - $offset, ($height/2) - 8 , $_SESSION['captcha_code'], $color);


imagePNG($image);
imageDestroy($image);

?>
