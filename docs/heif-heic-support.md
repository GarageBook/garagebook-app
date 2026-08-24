# HEIF/HEIC image support

GarageBook accepts Apple HEIF/HEIC uploads on the photo upload paths and converts them to JPG after upload. This keeps browser display, thumbnails, public garage pages, private trip photos, and existing frontend code on broadly supported image formats.

## Required server stack

HEIF/HEIC decoding requires PHP Imagick backed by ImageMagick with libheif support. PHP GD is not enough for HEIF/HEIC.

Check the live server:

```bash
cd /home/forge/app.garagebook.nl/current
php -m | grep -i imagick
php -r 'echo "imagick=".(extension_loaded("imagick") ? "yes" : "no").PHP_EOL; if (extension_loaded("imagick")) { echo "formats=".implode(",", array_intersect(array_map("strtoupper", Imagick::queryFormats()), ["HEIC","HEIF"])).PHP_EOL; }'
```

On Forge/Ubuntu, install the required packages if `imagick=no` or neither `HEIC` nor `HEIF` is listed:

```bash
sudo apt-get update
sudo apt-get install -y libheif1 libheif-dev imagemagick php-imagick
sudo systemctl restart php8.3-fpm
sudo systemctl restart garagebook-worker.service
cd /home/forge/app.garagebook.nl/current
php artisan optimize:clear
```

Use the actual PHP-FPM and queue worker service names from production if they differ.
