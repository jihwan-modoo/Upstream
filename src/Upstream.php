<?php namespace Regulus\Upstream;

/*----------------------------------------------------------------------------------------------------------
	Upstream
		A simple composer package for Laravel 5 that assists in file uploads and image resizing/cropping.

		created by Cody Jassman
		version 0.6.0
		last updated on January 17, 2016
----------------------------------------------------------------------------------------------------------*/

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;

use Intervention\Image\ImageManagerStatic as Image;

class Upstream {

	public $config;
	public $files;
	public $returnData;
	public $imageExtensions = ['jpg', 'jpeg', 'gif', 'png'];

	/**
	 * Create an instance of Upstream with configuration settings (default and modified via array).
	 *
	 * @param  array    $id
	 * @return Upstream
	 */
	public function __construct($config = [])
	{
		// set default config
		$this->config = array_merge($this->formatDefaultConfig(), $config);
	}

	/**
	 * Method for instantiating Upstream.
	 *
	 * @param  array    $config
	 * @return Upstream
	 */
	public function make($config = [])
	{
		return new static($config);
	}

	/**
	 * Format config as camel case.
	 *
	 * @param  mixed    $defaultConfig
	 * @return array
	 */
	private function formatDefaultConfig($defaultConfig = null)
	{
		if (is_null($defaultConfig))
			$defaultConfig = config('upload.defaults');

		$formatted = [];
		foreach ($defaultConfig as $configItem => $value)
		{
			$formatted[camel_case($configItem)] = is_array($value) ? $this->formatDefaultConfig($value) : $value;
		}

		return $formatted;
	}

	/**
	 * Upload files based on configuration.
	 *
	 * @param  array    $config
	 * @return array
	 */
	public function upload($config = [])
	{
		// modify upload settings through a single config parameter
		if (!empty($config))
			$this->config = array_merge($this->config, $config);

		// set file types config
		if ($this->config['fileTypes'] != '*')
		{
			$fileTypesImage = $this->config['fileTypes'] == "image" || $this->config['fileTypes'] == "images";

			$this->config['fileTypes'] = $this->formatFileTypesList($this->config['fileTypes']);
		}

		// format error triggers
		if ($this->config['maxFileSize'])    $this->config['maxFileSize']    = strtoupper(str_replace(' ', '', $this->config['maxFileSize']));
		if ($this->config['imageMinWidth'])  $this->config['imageMinWidth']  = str_replace('px', '', strtolower($this->config['imageMinWidth']));
		if ($this->config['imageMinHeight']) $this->config['imageMinHeight'] = str_replace('px', '', strtolower($this->config['imageMinHeight']));

		$this->returnData = (object) [
			'error'     => true,
			'uploaded'  => 0,
			'attempted' => 0,
			'files'     => [],
		];

		if (!$_FILES)
			return $this->returnData;

		// add trailing slash to path if it doesn't exist
		if (substr($this->config['path'], -1) != "/")
			$this->config['path'] .= "/";

		// create files array
		$this->files = [];

		foreach ($_FILES as $field => $filesInfo)
		{
			if (!empty($filesInfo))
			{
				// check if field is set to be uploaded in "fields" configuration
				if ((is_string($this->config['fields']) && $this->config['fields'] == $field)
				|| (is_array($this->config['fields']) && in_array($field, $this->config['fields']))
				|| (is_bool($this->config['fields']) && $this->config['fields']))
					$uploadFile = true;
				else
					$uploadFile = false;

				if ($uploadFile && isset($filesInfo['name']))
				{
					// array of files exists rather than just a single file; loop through them
					if (is_array($filesInfo['name']))
					{
						$keys = array_keys($filesInfo['name']);

						foreach ($keys as $key)
						{
							$this->files[$field] = (object) [
								'name'    => trim($filesInfo['name'][$key]),
								'type'    => $filesInfo['type'][$key],
								'tmpName' => $filesInfo['tmp_name'][$key],
								'error'   => $filesInfo['error'][$key],
								'size'    => $filesInfo['size'][$key],
								'field'   => $field,
								'key'     => $key,
							];
						}
					}
					else
					{
						$fileInfo            = $filesInfo;
						$this->files[$field] = (object) [
							'name'    => trim($fileInfo['name']),
							'type'    => $fileInfo['type'],
							'tmpName' => $fileInfo['tmp_name'],
							'error'   => $fileInfo['error'],
							'size'    => $fileInfo['size'],
							'field'   => $field,
							'key'     => 0,
						];
					}
				}
			}
		}

		$f = 1;
		foreach ($this->files as $i => &$file)
		{
			if ($file->field != $this->config['fieldThumb'])
			{
				$file = $this->addAdditionalFileData($file);

				// create directory if necessary
				if ($this->config['createDirectory'] && !is_dir($this->config['path'])) $this->createDirectory($this->config['path']);

				// get image dimensions if file is an image
				$dimensions = [];
				if (in_array($file->extension, $this->imageExtensions))
					$dimensions = $this->imageSize($file->tmpName);

				// check for errors
				$error           = false;
				$attemptedUpload = false;

				if ($file->name != "")
					$attemptedUpload = true;

				// error check 1: file exists and overwrite not set
				if (is_file($this->config['path'].$file->newFilename))
				{
					if ($this->config['overwrite']) //delete existing file if it exists and overwrite is set
						unlink($this->config['path'].$file->newFilename);
					else //file exists but overwrite is not set; do not upload
						$error = 'A file already exists with the name specified ('.$file->newFilename.').';
				}

				// error check 2: file type
				if (!$error && $this->config['fileTypes'] != '*' && is_array($this->config['fileTypes']))
				{
					if ($file->name != "")
					{
						if (!in_array($file->extension, $this->config['fileTypes']))
						{
							if ($fileTypesImage)
								$error = 'You must upload an image file.';
							else
								$error = 'You must upload a file in one of the following formats: '.implode(', ', $this->config['fileTypes']).'. ('.$file->name.')';
						}
					}
					else
					{
						if ($fileTypesImage)
							$error = 'You must upload an image.';
						else
							$error = 'You must upload a file.';
					}
				}

				// error check 3: maximum file size
				if (!$error && $this->config['maxFileSize'])
				{
					$maxFileSize = $this->config['maxFileSize'];

					if (substr($this->config['maxFileSize'], -2) == "KB") {
						$maxFileSizeBytes = str_replace('KB', '', $this->config['maxFileSize']) * 1024;
					} else if (substr($this->config['maxFileSize'], -2) == "MB") {
						$maxFileSizeBytes = str_replace('MB', '', $this->config['maxFileSize']) * 1024 * 1024;
					} else {
						$maxFileSizeBytes = str_replace('B', '', $this->config['maxFileSize']);
						$maxFileSize      = $this->config['maxFileSize'].'B';
					}

					if ($file->size > $maxFileSizeBytes)
						$error = 'Your file must not exceed '.$maxFileSize.'.';
				}

				// error check 4: minimum image dimensions
				if (!$error && in_array($file->extension, $this->imageExtensions) && !empty($dimensions) && ($this->config['imageMinWidth'] || $this->config['imageMinHeight']))
				{
					$errorWidth  = $this->config['imageMinWidth']  && $dimensions['w'] < $this->config['imageMinWidth'];
					$errorHeight = $this->config['imageMinHeight'] && $dimensions['h'] < $this->config['imageMinHeight'];

					if ($errorWidth || $errorHeight)
					{
						if ($this->config['imageMinWidth'] && $this->config['imageMinHeight'])
							$error = 'Your image must be at least '.$this->config['imageMinWidth'].' x '.$this->config['imageMinHeight'].'. ';
						elseif ($this->config['imageMinWidth'])
							$error = 'Your image must be at least '.$this->config['imageMinWidth'].' pixels in width.';
						elseif ($this->config['imageMinHeight'])
							$error = 'Your image must be at least '.$this->config['imageMinHeight'].' pixels in height.';

						$error .= 'Your uploaded image dimensions were '.$dimensions['w'].' x '.$dimensions['h'].'.';
					}
				}

				// error check 5: maximum image dimensions
				$maxWidthExceeded  = false;
				$maxHeightExceeded = false;

				if (!$error && in_array($file->extension, $this->imageExtensions) && !empty($dimensions)
				&& ($this->config['imageMaxWidth'] || $this->config['imageMaxHeight']))
				{
					if ($this->config['imageMaxWidth'] && $dimensions['w'] > $this->config['imageMaxWidth'])
						$maxWidthExceeded = true;

					if ($this->config['imageMaxHeight'] && $dimensions['h'] > $this->config['imageMaxHeight'])
						$maxHeightExceeded = true;

					if (!$this->config['imageResizeMax'] && ($maxWidthExceeded || $maxHeightExceeded))
					{
						if ($this->config['imageMaxWidth'] && $this->config['imageMaxHeight'])
							$error = 'Your image must be '.$this->config['imageMaxWidth'].' x '.$this->config['imageMaxHeight'].' or less. ';
						elseif ($this->config['imageMaxWidth'])
							$error = 'Your image must be '.$this->config['imageMaxWidth'].' pixels in width or less.';
						elseif ($this->config['imageMaxHeight'])
							$error = 'Your image must be '.$this->config['imageMaxHeight'].' pixels in height or less.';

						$error .= 'Your uploaded image dimensions were '.$dimensions['w'].' x '.$dimensions['h'].'.';
					}
				}

				if ($this->config['fieldNameAsFileIndex'])
					$fileIndex = $file->field;
				else
					$fileIndex = $f - 1;

				if (!$error)
				{
					// upload file to selected directory
					$fileTransferred = move_uploaded_file($file->tmpName, $this->config['path'].$file->newFilename);

					// resize image if necessary
					if ($fileTransferred)
					{
						if (in_array($file->extension, $this->imageExtensions))
						{
							if ($this->config['imageResize']
							|| ($this->config['imageResizeMax'] && ($maxWidthExceeded || $maxHeightExceeded)))
							{
								// configure resized image dimensions
								$resizeType = $this->config['imageResizeDefaultType'];

								if ($this->config['imageCrop'])
									$resizeType = "crop";

								if ($this->config['imageResize'])
								{
									$resizeDimensions = [
										'w' => $this->config['imageDimensions']['w'],
										'h' => $this->config['imageDimensions']['h'],
									];
								}
								else
								{
									if ($maxWidthExceeded && $maxHeightExceeded)
									{
										$resizeDimensions = [
											'w' => $this->config['imageMaxWidth'],
											'h' => $this->config['imageMaxHeight'],
										];
									}
									else if ($maxWidthExceeded)
									{
										$resizeDimensions = [
											'w' => $this->config['imageMaxWidth'],
											'h' => false,
										];

										$resizeType = 'landscape';
									}
									else if ($maxHeightExceeded)
									{
										$resizeDimensions = [
											'w' => false,
											'h' => $this->config['imageMaxHeight'],
										];

										$resizeType = 'portrait';
									}
								}

								// resize image
								$image = Image::make($this->config['path'].$file->newFilename);

								if ($resizeType == "crop")
								{
									$image->resize($resizeDimensions['w'] * 1.4, $resizeDimensions['h'] * 1.4, function ($constraint)
									{
										$constraint->aspectRatio();
									});

									$image->crop($resizeDimensions['w'], $resizeDimensions['h']);
								}
								else
								{
									$image->resize($resizeDimensions['w'], $resizeDimensions['h']);
								}

								$image->save($this->config['path'].$file->newFilename, $this->config['imageResizeQuality']);
							}

							// create thumbnail image if necessary
							if ($this->config['imageThumb'])
								$this->createThumbnailImage($file);
						}

						$file = $this->addImageDimensionsData($file);
					}

					if ($fileTransferred)
					{
						$this->addFile($file);

						$this->returnData->error = false;
					}
					else
					{
						$this->returnData->files[$fileIndex]->error = 'Something went wrong. Please try again.';
					}
				} else {
					$this->returnData->files[$fileIndex]->error = $error;
				}

				$this->returnData->files[$fileIndex]->field = $file->field;
				$this->returnData->files[$fileIndex]->key   = $file->key;

				$this->returnData->attempted += (int) $attemptedUpload;
			}

			$f ++;
		} // end foreach files

		// create thumbnail image if necessary (from thumbnail image field)
		if ($this->config['imageThumb'] && isset($this->files[$this->config['fieldThumb']]))
		{
			$this->createThumbnailImage();

			$this->returnData->attempted ++;
		}

		if ($this->config['returnSingleResult'])
			return $this->returnData = $this->returnData->files[0];

		// return result
		if ($this->config['returnJson'])
			return json_encode($this->returnData);
		else
			return $this->returnData;
	}

	/**
	 * Add additional data to a file array.
	 *
	 * @param  array    $file
	 * @return array
	 */
	public function addAdditionalFileData($file)
	{
		if (isset($file->newFilename))
			return $file;

		$originalFilename = $file->name;
		$originalFileExt  = strtolower(File::extension($originalFilename));

		if (!$this->config['filename'])
		{
			$filename = $this->filename($originalFilename);
		}
		else
		{
			if (in_array($this->config['filename'], array('[LOWERCASE]', '[UNDERSCORED]', '[LOWERCASE-UNDERSCORED]', '[DASHED]', '[LOWERCASE-DASHED]', '[RANDOM]')))
				$filename = $this->filename($originalFilename, $this->config['filename']);
			else
				$filename = $this->filename($this->config['filename']);
		}

		$filename = str_replace('[KEY]', $file->key, $filename);
		$fileExt  = File::extension($filename);

		// if file extension doesn't exist, use original extension
		if ($fileExt == "") {
			$fileExt   = $originalFileExt;
			$filename .= '.'.$fileExt;
		}

		$file->newFilename = $filename;
		$file->basename    = str_replace('.'.$fileExt, '', $filename);
		$file->extension   = $fileExt;

		if ($this->config['displayName'] && is_string($this->config['displayName']))
			$file->displayName = $this->config['displayName'];
		else
			$file->displayName = $file->newFilename;

		$file->isImage         = in_array($originalFileExt, $this->imageExtensions);
		$file->imageDimensions = (object) [
			'w'  => null,
			'h'  => null,
			'tw' => null,
			'th' => null,
		];

		$thumbnailsDirectory = config('upload.thumbnails_directory');

		// set path
		$file->path = $this->config['path'];
		if ($this->config['fieldThumb'] && $file->field == $this->config['fieldThumb'])
			$file->path .= $thumbnailsDirectory;

		// add image dimensions
		$file = $this->addImageDimensionsData($file);

		// set URL
		$file->url = url(str_replace('//', '/', $file->path.'/'.$file->newFilename));
		if ($this->config['noCacheUrl'])
			$file->url .= '?'.rand(1, 99999);

		// set thumbnail image URL
		$file->thumbnailUrl = url($this->config['defaultThumb']);
		if ($file->isImage)
		{
			if ($this->config['imageThumb'])
				$file->thumbnailUrl = url($this->config['path'].$thumbnailsDirectory.'/'.$file->newFilename).($this->config['noCacheUrl'] ? '?'.rand(1, 99999) : '');
			else
				$file->thumbnailUrl = $file->url;
		}

		return $file;
	}

	/**
	 * Add image dimensions data to a file array.
	 *
	 * @param  array    $file
	 * @return array
	 */
	public function addImageDimensionsData($file)
	{
		if ($file->isImage && File::exists($this->config['path'].'/'.$file->newFilename))
		{
			$size = getimagesize($this->config['path'].'/'.$file->newFilename);

			if (!empty($size))
			{
				$file->imageDimensions->w = $size[0];
				$file->imageDimensions->h = $size[1];

				$thumbnailsDirectory = config('upload.thumbnails_directory');

				if (File::exists($file->path.'/'.$thumbnailsDirectory.'/'.$file->newFilename))
				{
					$thumbnailSize = getimagesize($file->path.'/'.$thumbnailsDirectory.'/'.$file->newFilename);
					if (!empty($thumbnailSize))
					{
						$file->imageDimensions->tw = $thumbnailSize[0];
						$file->imageDimensions->th = $thumbnailSize[1];
					}
				}
			}
		}

		return $file;
	}

	/**
	 * Add a file array to return data.
	 *
	 * @param  mixed    $file
	 * @return string
	 */
	public function addFile($file)
	{
		$file = $this->addAdditionalFileData($file);

		$this->returnData->files[$file->field] = (object) [
			'name'            => $file->displayName,
			'filename'        => $file->newFilename,
			'basename'        => $file->basename,
			'extension'       => $file->extension,
			'path'            => $file->path,
			'url'             => $file->url,
			'fileSize'        => $file->size,
			'isImage'         => $file->isImage,
			'thumbnailUrl'    => $file->thumbnailUrl,
			'imageDimensions' => $file->imageDimensions,
			'error'           => false,
		];

		$this->returnData->uploaded ++;

		return $file->field;
	}

	/**
	 * Create a thumbnail image.
	 *
	 * @param  mixed    $file
	 * @return boolean
	 */
	public function createThumbnailImage($file = null)
	{
		$resizeDimensions = array(
			'w' => $this->config['imageDimensions']['tw'],
			'h' => $this->config['imageDimensions']['th'],
		);

		$thumbsPath = $this->config['path'].config('upload.thumbnails_directory').'/';
		if ($this->config['createDirectory'] && !is_dir($thumbsPath))
			$this->createDirectory($thumbsPath);

		if (!is_dir($thumbsPath))
			return false;

		if ($file)
		{
			$file       = $this->addAdditionalFileData($file);
			$fieldThumb = false;
		}
		else
		{
			if (!isset($this->files[$this->config['fieldThumb']]->tmpName))
				return false;

			$file = $this->addAdditionalFileData($this->files[$this->config['fieldThumb']]);

			move_uploaded_file($file->tmpName, $file->path.'/'.$file->newFilename);

			$fieldThumb = true;
		}

		$thumbSource           = str_replace('//', '/', $file->path.'/'.$file->newFilename);
		$thumbOriginalFilename = $file->name;
		$thumbOriginalFileExt  = strtolower(File::extension($thumbOriginalFilename));
		$thumbFilename         = $file->newFilename;

		if (!in_array($thumbOriginalFileExt, $this->imageExtensions))
			return false;

		if (!File::exists($thumbSource))
			return false;

		// resize image
		$image = Image::make($thumbSource);

		$image->resize($resizeDimensions['w'] * 1.4, $resizeDimensions['h'] * 1.4, function ($constraint)
		{
			$constraint->aspectRatio();
		});

		$image->crop($resizeDimensions['w'], $resizeDimensions['h']);
		$image->save($thumbsPath.$thumbFilename, $this->config['imageResizeQuality']);

		$this->returnData->error = false;

		if ($fieldThumb)
		{
			$size = getimagesize($thumbsPath.$thumbFilename);

			if (!empty($size))
			{
				$file->imageDimensions->w  = $size[0];
				$file->imageDimensions->h  = $size[1];
				$file->imageDimensions->tw = $size[0];
				$file->imageDimensions->th = $size[1];
			}

			$this->addFile($file);
		}

		return true;
	}

	/**
	 * Crop images based on configuration.
	 *
	 * @param  array    $config
	 * @return array
	 */
	public function cropImage($config = [])
	{
		$config     = array_merge($this->formatDefaultConfig(config('upload.defaults.crop')), $config);
		$returnData = (object) ['error' => 'Something went wrong. Please try again.'];
		$path       = $config['path'];

		$originalFilename = $config['filename'];
		$originalFileExt  = File::extension($config['filename']);

		// error check 1: file not found
		if (!is_file($path.$originalFilename))
		{
			$returnData->error = 'The file you specified was not found ('.$originalFilename.').';
			return $returnData;
		}

		// error check 2: file is not an image
		if (!in_array($originalFileExt, $this->imageExtensions))
		{
			$returnData->error = 'The file you specified was not an image ('.$originalFilename.').';
			return $returnData;
		}

		if (!$config['newPath'])     $config['newPath']     = $config['path'];
		if (!$config['newFilename']) $config['newFilename'] = $config['filename'];

		$newPath = $config['newPath'];

		if (!$config['newFilename'])
			$filename = $this->filename($config['filename']);
		else
			$filename = $this->filename($config['newFilename']);

		$fileExt = File::extension($filename);

		// if file extension doesn't exist, use original extension
		if ($fileExt == "")
		{
			$fileExt   = $originalFileExt;
			$filename .= '.'.$fileExt;
		}

		// create image data from image file depending on file type
		$fileType = "";
		if (in_array($originalFileExt, ['jpg', 'jpeg']))
		{
			$imageOriginal = imagecreatefromjpeg($path.$originalFilename);
			$fileType      = "jpg";
		}
		else if ($originalFileExt == "gif")
		{
			$imageOriginal = imagecreatefromgif($path.$originalFilename);
			$fileType      = "gif";
		}
		else if ($originalFileExt == "png")
		{
			$imageOriginal = imagecreatefrompng($path.$originalFilename);
			$fileType      = "png";
		}

		if (isset($imageOriginal))
		{
			// error check 3: file exists and overwrite not set
			if (is_file($newPath.$file->newFilename))
			{
				if ($config['overwrite']) // delete existing file if it exists and overwrite is set
				{
					unlink($newPath.$file->newFilename);
				}
				else
				{
					$returnData->error = 'A file already exists with the name specified ('.$file->newFilename.').';
					return $returnData;
				}
			}

			if (!is_dir($config['newPath']))
			{
				if ($config['createDirectory'])
				{
					$this->createDirectory($config['newPath']);
				}
				else
				{
					$returnData->error = 'The directory you specified does not exist ('.$config['newPath'].').';
					return $returnData;
				}
			}

			// crop image
			$imageCropped = imagecreatetruecolor($config['imageDimensions']['w'], $config['imageDimensions']['h']);

			imagecopyresampled(
				$imageCropped, $imageOriginal, 0, 0,
				$config['cropPosition']['x'],    $config['cropPosition']['y'],
				$config['imageDimensions']['w'], $config['imageDimensions']['h'],
				$config['cropPosition']['w'],    $config['cropPosition']['h']
			);

			// save cropped image to file
			if ($fileType == "jpg")
				imagejpeg($imageCropped, $newPath.$filename, 72);
			elseif ($fileType == "gif")
				imagegif($imageCropped, $newPath.$filename);
			elseif ($fileType == "png")
				imagepng($imageCropped, $newPath.$filename, 72);

			$returnData->error = false;
			$returnData->name  = $filename;
			$returnData->path  = $newPath;
		}

		return $returnData;
	}

	/**
	 * Set a filename for an uploaded file.
	 *
	 * @param  string   $filename
	 * @param  mixed    $filenameModifier
	 * @param  boolean  $suffix
	 * @return string
	 */
	public function filename($filename, $filenameModifier = false, $suffix = false)
	{
		$fileExt = File::extension($filename);

		$newFilename = strtr($filename, 'ÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÒÓÔÕÖÙÚÛÜÝàáâãäåçèéêëìíîïðòóôõöùúûüýÿ', 'AAAAAACEEEEIIIIOOOOOUUUUYaaaaaaceeeeiiiioooooouuuuyy');
		$filename    = preg_replace('/([^.a-z0-9]+)/i', '_', $filename); //replace characters other than letters, numbers and . by _

		// get filename
		if ($filenameModifier == "[LOWERCASE]")
		{
			$newFilename = strtolower($newFilename);
		}
		else if ($filenameModifier == "[UNDERSCORED]")
		{
			$newFilename = str_replace(' ', '_', str_replace('-', '_', $newFilename));
		}
		else if ($filenameModifier == "[LOWERCASE-UNDERSCORED]")
		{
			$newFilename = strtolower(str_replace(' ', '_', str_replace('-', '_', $newFilename)));
		}
		else if ($filenameModifier == "[DASHED]")
		{
			$newFilename = str_replace(' ', '-', str_replace('_', '-', $newFilename));
		}
		else if ($filenameModifier == "[LOWERCASE-DASHED]")
		{
			$newFilename = strtolower(str_replace(' ', '-', str_replace('_', '-', $newFilename)));
		}
		else if ($filenameModifier == "[RANDOM]")
		{
			$newFilename = str_random(16);
		}
		else
		{
			// append suffix if it is set
			if ($suffix && $suffix != "")
			{
				$fileExt = File::extension($newFilename);
				$newFilename = str_replace('.'.$fileExt, '', $newFilename); //remove extension
				$newFilename .= '_'.$suffix.'.'.$fileExt;
			}
		}

		// get file extension
		$newFileExt = File::extension($newFilename);
		if ($fileExt == "ext")
			$newFileExt = $fileExt;

		$addExt = false;

		if ($newFileExt == "")
		{
			$newFileExt = $fileExt;
			$addExt     = true;
		}

		if ($newFileExt == "jpeg")
			$newFileExt = "jpg";

		if ($addExt && $newFileExt != "")
			$filename .= '.'.$newFileExt;

		$newFilename = str_replace('.ext', '.'.$fileExt, $newFilename); //replace .ext with original file extension

		return $newFilename;
	}

	/**
	 * Get the files in a directory.
	 *
	 * @param  mixed    $path
	 * @param  array    $config
	 * @return array
	 */
	public function directoryFiles($path = false, $config = [])
	{
		if (!$path)
		{
			$config = array_merge($this->formatDefaultConfig(config('upload.default.upload')), $config);
			$path   = $config['path'];
		}

		if (!isset($config['deleteUrl']))
			$config['deleteUrl'] = "";

		if (!isset($config['fileTypeOrder']))
			$config['fileTypeOrder'] = false;

		if (isset($config['fileTypes']))
			$config['fileTypes'] = $this->formatFileTypesList($config['fileTypes']);
		else
			$config['fileTypes'] = '*';

		$thumbnailsDirectory = config('upload.thumbnails_directory');

		$result = [];
		if (is_dir($path))
		{
			if (substr($path, -1) != "/")
				$path .= "/";

			if ($handle = opendir($path))
			{
				if ($config['fileTypeOrder'])
				{
					$config['fileTypeOrder'] = $this->formatFileTypesList($config['fileTypeOrder']);

					$files = glob($path.'*.{'.implode(',', $config['fileTypeOrder']).'}', GLOB_BRACE);
				} else {
					$files = scandir($path);
				}

				foreach ($files as $entry)
				{
					// if glob, remove path from filename
					if ($config['fileTypeOrder'])
						$entry = str_replace($path, '', $entry);

					if (is_file($path.$entry))
					{
						$filename  = $entry;
						$extension = File::extension($filename);

						if ($config['fileTypes'] == "*" || in_array($extension, $config['fileTypes']))
						{
							$deleteFullUrl = $config['deleteUrl'];
							if ($config['deleteUrl'] != "")
								$deleteFullUrl .= "/".str_replace(' ', '__', $filename);

							$file = (object) [
								'name'       => $filename,
								'url'        => URL::to($path.$filename),
								'fileSize'   => filesize($path.$filename),
								'fileType'   => filetype($path.$filename),
								'isImage'    => $this->isImage($filename),
								'deleteUrl'  => $deleteFullUrl,
								'deleteType' => 'DELETE',
								'error'      => false,
							];

							if ($file->isImage)
								$file->thumbnailUrl = is_file($path.$thumbnailsDirectory.'/'.$filename) ? URL::to($path.$thumbnailsDirectory.'/'.$filename) : $file->url;
							else
								$file->thumbnailUrl = url($this->config['defaultThumb']);

							$result[] = $file;
						}
					}
				}
			}
		}

		if (isset($config['returnJson']) && $config['returnJson'])
			return json_encode($result);
		else
			return $result;
	}

	/**
	 * Get the filenames in a directory.
	 *
	 * @param  mixed    $path
	 * @param  array    $config
	 * @return array
	 */
	public function directoryFilenames($path = '', $config = [])
	{
		if (!isset($config['fileTypeOrder'])) $config['fileTypeOrder'] = false;

		$files = [];

		if (substr($path, -1) != "/")
			$path .= "/";

		if (is_dir($path) && $handle = opendir($path))
		{
			if ($config['fileTypeOrder'])
			{
				$config['fileTypeOrder'] = $this->formatFileTypesList($config['fileTypeOrder']);

				$files_list = glob($path.'*.{'.implode(',', $config['fileTypeOrder']).'}', GLOB_BRACE);
			} else {
				$files_list = scandir($path);
			}

			foreach ($files_list as $entry)
			{
				$entry = str_replace($path, '', $entry); // if glob, remove path from filename
				if (is_file($path.$entry)) $files[] = $entry;
			}
		}

		if (isset($config['returnJson']) && $config['returnJson'])
			return json_encode($files);
		elseif (isset($config['returnStr']) && $config['returnStr'])
			return implode(', ', $files);
		else
			return $files;
	}

	/**
	 * Create arrays of file types to remove (file type categories), and file types to add (from file type categories).
	 *
	 * @param  array    $fileTypes
	 * @return array
	 */
	public function formatFileTypesList($fileTypes = [])
	{
		$fileTypesFormatted = [];

		if (is_string($fileTypes) && ($fileTypes == "image" || $fileTypes == "images"))
			$fileTypes = $this->imageExtensions;

		if (!is_array($fileTypes))
			$fileTypes = explode('|', $fileTypes);

		$fileTypeCategories = config('upload.file_type_categories');
		for ($t=0; $t < count($fileTypes); $t++)
		{
			$category = false;

			foreach ($fileTypeCategories as $fileTypeCategory => $fileTypesForCategory)
			{
				if ($fileTypes[$t] == $fileTypeCategory)
				{
					$category = true;

					foreach ($fileTypesForCategory as $fileType) {
						$fileTypesFormatted[] = $fileType;
					}
				}
			}

			if (!$category)
				$fileTypesFormatted[] = $fileTypes[$t];
		}

		return $fileTypesFormatted;
	}

	/**
	 * Convert a URL-friendly filename to the actual filename.
	 *
	 * @param  string   $uri
	 * @return string
	 */
	public function uriToFilename($uri = '')
	{
		$sections = explode('_', $uri); $filename = "";
		$last     = count($sections) - 1;

		for ($s=0; $s < $last; $s++) {
			if ($filename != "")
				$filename .= "_";

			$filename .= $sections[$s];
		}

		$filename .= ".".$sections[$last];

		return $filename;
	}

	/**
	 * Convert a URL-friendly filename to the actual filename.
	 *
	 * @param  string   $path
	 * @param  integer  $permissions
	 * @return integer
	 */
	public function createDirectory($path, $permissions = 0777)
	{
		$pathArray          = explode('/', $path);
		$pathPartial        = "";
		$directoriesCreated = 0;

		for ($p=0; $p < count($pathArray); $p++)
		{
			if ($pathArray[$p] != "")
			{
				if ($pathPartial != "")
					$pathPartial .= "/";

				$pathPartial .= $pathArray[$p];

				if (!is_dir($pathPartial)) {
					mkdir($pathPartial);
					chmod($pathPartial, sprintf('%04d', $permissions));
					$directoriesCreated ++;
				}
			}
		}

		return $directoriesCreated;
	}

	/**
	 * Create an array of image dimensions for a specified image path.
	 *
	 * @param  string   $image
	 * @return array
	 */
	public function imageSize($image)
	{
		if (is_file($image))
		{
			$image = getimagesize($image);
			return [
				'w' => $image[0],
				'h' => $image[1],
			];
		} else {
			return [
				'w' => 0,
				'h' => 0,
			];
		}
	}

	/**
	 * Get the file size of a specified file.
	 *
	 * @param  string   $file
	 * @param  boolean  $convert
	 * @return mixed
	 */
	public function fileSize($file, $convert = true)
	{
		if (is_file($file))
		{
			$fileSize = filesize($file);

			if ($convert)
				return $this->convertFileSize($fileSize);
			else
				return $fileSize;

		} else {
			if ($convert)
				return '0.00 KB';
			else
				return 0;
		}
	}

	/**
	 * Convert a file size in bytes to the most logical units.
	 *
	 * @param  integer  $fileSize
	 * @return string
	 */
	public function convertFileSize($fileSize)
	{
		if ($fileSize < 1024) {
			return $fileSize .' B';
		} else if ($fileSize < 1048576) {
			return round($fileSize / 1024, 2) .' KB';
		} else if ($fileSize < 1073741824) {
			return round($fileSize / 1048576, 2) . ' MB';
		} else if ($fileSize < 1099511627776) {
			return round($fileSize / 1073741824, 2) . ' GB';
		} else if ($fileSize < 1125899906842624) {
			return round($fileSize / 1099511627776, 2) .' TB';
		} else {
			return round($fileSize / 1125899906842624, 2) .' PB';
		}
	}

	/**
	 * Delete a file.
	 *
	 * @param  string   $file
	 * @return boolean
	 */
	public function deleteFile($file)
	{
		$success = false;

		$file = str_replace('__', ' ', $file);
		if (is_file($file))
			$success = unlink($file);

		$extension = File::extension($file);

		// delete thumbnail image if it exists
		if (in_array($extension, $this->imageExtensions))
		{
			$thumbnailsDirectory = config('upload.thumbnails_directory');

			$pathArray = explode('/', $file);
			$path      = "";
			$last      = count($pathArray) - 1;

			for ($p=0; $p < $last; $p++) {
				if ($path != "")
					$path .= "/";

				$path .= $pathArray[$p];
			}

			if (is_file($path.'/'.$thumbnailsDirectory.'/'.$pathArray[$p]))
				unlink($path.'/'.$thumbnailsDirectory.'/'.$pathArray[$p]);
		}

		return $success;
	}

	/**
	 * Apply file limits by type to a specified directory. If a file type's limit is exceeded,
	 * files will be deleted starting with the oldest files. Example array: ['jpg' => 3, 'pdf' => 3];
	 *
	 * @param  string   $directory
	 * @param  array    $limits
	 * @return array
	 */
	public function directoryFileLimits($directory = '', $limits = [])
	{
		// add trailing slash to directory if it doesn't exist
		if (substr($directory, -1) != "/")
			$directory .= "/";

		$deletedFiles = [];

		if (is_dir($directory) && $handle = opendir($directory))
		{
			foreach ($limits as $fileTypes => $limit)
			{
				$fileTypes = $this->formatFileTypesList($fileTypes);

				$filesForType = [];
				$quantity     = 0;

				while (false !== ($entry = readdir($handle))) {
					if (is_file($directory.$entry)) {
						$fileExt = File::extension($entry);
						if ($fileExt) {
							if (in_array(strtolower($fileExt), $fileTypes) && !in_array($directory.$entry, $filesForType)) {
								$filesForType[] = $directory.$entry;
								$quantity ++;
							}
						} // end if file extension exists (entry is not a directory)
					}
				} // end while file in directory

				while ($quantity > $limit) { // if there are too many files of filetype being checked delete until at limit starting with oldest files
					$oldestFile = -1;
					foreach ($filesForType as $index => $file) {
						if ($oldestFile == -1) {
							$oldestFile = $index;
						} else {
							if (filemtime($file) < filemtime($filesForType[$oldestFile])) $oldestFile = $index;
						}
					}

					if (isset($filesForType[$oldestFile]) && is_file($filesForType[$oldestFile])) {
						unlink($filesForType[$oldestFile]);
						$deletedFiles[] = $filesForType[$oldestFile];
						unset($filesForType[$oldestFile]);
						$quantity --;
					}
				} // end while quantity > limit
				rewinddir($handle);
			} // end while limits
		} // end if directory can be opened

		return $deletedFiles;
	}

	/**
	 * Delete a directory and, optionally, all contents contained within it.
	 *
	 * @param  string   $directory
	 * @param  boolean  $deleteAllContents
	 * @return integer
	 */
	public function deleteDirectory($directory = '', $deleteAllContents = false)
	{
		if (substr($directory, -1) != "/") $directory .= "/"; // add trailing slash to directory if it doesn't exist
		$deletedFiles = 0;

		if (is_dir($directory) && $handle = opendir($directory)) {
			while (false !== ($entry = readdir($handle))) {
				if (!$deleteAllContents) return $deletedFiles;

				if ($entry != "." && $entry != "..") {
					if (is_file($directory.$entry)) {
						unlink($directory.$entry);
						$deletedFiles ++;
					} else if (is_dir($directory.$entry)) { // delete sub-directory and all files/directorys it contains
						$deletedFiles += $this->deleteDirectory($directory.$entry, true);
					}
				}
			} // end while file in directory

			// remove directory
			rmdir($directory);

		} // end if directory can be opened

		return $deletedFiles;
	}

	/**
	 * Check if a file is an image based on the extension.
	 *
	 * @param  string   $file
	 * @return boolean
	 */
	public function isImage($file = '')
	{
		return in_array(strtolower(File::extension($file)), array_merge($this->imageExtensions, ['svg']));
	}

	/**
	 * Check if a file is a raster image based on the extension.
	 *
	 * @param  string   $file
	 * @return boolean
	 */
	public function isRasterImage($file = '')
	{
		return in_array(strtolower(File::extension($file)), $this->imageExtensions);
	}

}