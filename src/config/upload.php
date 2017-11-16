<?php

return [

	/*
	|--------------------------------------------------------------------------
	| Uploading and Cropping Defaults
	|--------------------------------------------------------------------------
	|
	| The default setup for uploading files and cropping images.
	|
	*/
	'defaults' => [
		'path'             => 'uploads',
		'fields'           => true, // use a string like "image" if you have only one file field called "image" that you would like to process or pass an array for specific file fields
		'field_thumb'      => 'thumbnail_image',
		'create_directory' => false,
		'filename'         => null,
		'overwrite'        => false,
		'return_json'      => false,
		'no_cache_url'     => true,

		// error triggers
		'file_types'    => '*',
		'max_file_size' => false,

		// image uploading size limits
		'image_min_width'  => false,
		'image_min_height' => false,
		'image_max_width'  => false, // if max is exceeded and imgResizeMax is true, image will be resized to max instead of triggering error
		'image_max_height' => false,

		// image resizing
		'image_resize'               => false,
		'image_resize_max'           => false, // used in conjunction with image_max_width and/or image_max_height to resize only if image exceeds maximums; images that are smaller will not be upscaled
		'image_resize_default_type'  => 'landscape', // if resizing but not cropping, this is the default cropping option (see Resizer bundle options)
		'image_resize_quality'       => 75,
		'image_thumb'                => false,
		'image_crop'                 => false,
		'image_crop_thumb'           => true,
		'image_crop_delete_original' => true,
		'image_dimensions'           => [
			'w'  =>	1024, // image width
			'h'  =>	768,  // image height
			'tw' => 180,  // thumbnail image width
			'th' => 180,  // thumbnail image height
		],
		'image_crop_position' => [
			'x' => 0, // X position
			'y' => 0, // Y position
			'w' => 180, // width of cropped area
			'h' => 180, // height of cropped area
		],

		'display_name'  => false, // use false to use filename as display name
		'default_thumb' => 'default-thumb-upload.png',

		'return_single_result'     => false,
		'field_name_as_file_index' => true,
	],

	/*
	|--------------------------------------------------------------------------
	| Thumbnails Directory / Suffix
	|--------------------------------------------------------------------------
	|
	| You may set a thumbnails directory relative to your main image file's
	| directory, such as "thumbnails". Alternately, you may set a suffix
	| instead, which is the default, that will make your file something like
	| "profile-small.jpg". Use false or null on either of these to not turn
	| them off. Please note though that if they are both false/null, no
	| thumbnail image will be created.
	|
	*/
	'thumbnails_directory' => null,
	'thumbnails_suffix'    => '-small',

	/*
	|--------------------------------------------------------------------------
	| File Type Categories
	|--------------------------------------------------------------------------
	|
	| The file type categories for allowed file types for uploading and for
	| the file type order when getting the contents of a directory. Setting
	| the "fileTypes" upload config variable to "images", for example, will
	| allow the upload() method to automatically allow all image file types.
	|
	*/
	'file_type_categories' => [
		'image' => [
			'jpg',
			'jpeg',
			'png',
			'gif',
		],

		'vector' => [
			'svg',
			'eps',
			'ai',
		],

		'audio' => [
			'mp3',
			'ogg',
			'wma',
			'wav',
		],

		'video' => [
			'mp4',
			'avi',
			'fla',
			'mov',
			'wmv',
		],
	],

];
