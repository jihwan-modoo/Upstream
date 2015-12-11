Upstream
========

**A simple composer package for Laravel 5 that assists in file uploads and image resizing/cropping.**

> **Note:** For Laravel 4, you may use <a href="https://github.com/Regulus343/Upstream/tree/v0.4.5">version 0.4.5</a>.

- [Installation](#installation)
- [Uploading Files](#uploading-files)
- [Resizing Images and Creating Thumbnails](#images)

<a name="installation"></a>
## Installation

To install Upstream, make sure `regulus/upstream` has been added to Laravel 5's `composer.json` file.

	"require": {
		"regulus/upstream": "0.5.*"
	},

Then run `php composer.phar update` from the command line. Composer will install the Upstream package. Now, all you have to do is register the service provider and set up Upstream's alias in `config/app.php`. Add this to the `providers` array:

	Regulus\Upstream\UpstreamServiceProvider::class,

And add this to the `aliases` array:

	'Upstream' => Regulus\Upstream\Facade::class,

<a name="uploading-files"></a>
## Uploading Files

	$config = [
		'path'            => 'uploads/pdfs', //the path to upload to
		'fields'          => 'file',         //name of field or fields
		'filename'        => 'temp',         //the basename of the file (extension will be added automatically)
		'fileTypes'       => ['png', 'jpg'], //the file types to allow
		'createDirectory' => true,           //automatically creates directory if it doesn't exist
		'overwrite'       => true,           //whether or not to overwrite existing file of the same name
		'maxFileSize'     => '5MB',          //the maximum filesize of file to be uploaded
	];

	$upstream = Upstream::make($config);
	$result   = $upstream->upload();

> **Note:** Special `filename` strings are available including `[LOWERCASE]`, `[UNDERSCORE]`, `[LOWERCASE-UNDERSCORE]`, and `[RANDOM]`. The former three are used to make formatting adjustments to the original filename. The latter can be used to set the filename to a random string.

<a name="images"></a>
## Resizing Images and Creating Thumbnails

	$config = [
		'path'               => 'uploads/images',
		'fields'             => 'file',
		'filename'           => 'temp',
		'fileTypes'          => 'images',
		'createDirectory'    => true,
		'overwrite'          => true,
		'maxFileSize'        => '5MB',
		'imageResize'        => true,
		'imageResizeQuality' => 60,
		'imageCrop'          => true,
		'imageDimensions'    => [
			'w'  => 720, //image width
			'h'  => 360, //image height
			'tw' => 180, //thumbnail image width
			'th' => 180, //thumbnail image height
		],
	];

	$upstream = Upstream::make($config);
	$result   = $upstream->upload();

An entire set of configuration array is available in the config file at `src/config/config.php`.