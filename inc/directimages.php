<?php
class ShoppImageToolsDirectMode {
	protected $imageTagFilters = array(
		'shopp_tag_product_coverimage',
		'shopp_tag_product_gallery',
		'shopp_tag_product_images',
		'shopp_tag_product_image',
	);

	protected $imgBaseDir = '';
	protected $imgBaseURL = '';

	protected $requestedWidth = 0;
	protected $requestedHeight = 0;


	public function __construct() {
		if ($this->determineBaseImgURL()) {
			$filters = apply_filters('shopp_image_tools_tag_filters', $this->imageTagFilters);

			foreach ($filters as $filter)
				add_filter($filter, array($this, 'filterImageURLs'));
		}
	}


	protected function determineBaseImgURL() {
		// Allow the base URL to be provided from within a theme/plugin
		$this->imgBaseURL = apply_filters('shopp_image_tools_direct_img_base', '');

		// Otherwise try to form the base storage URL
		if (empty($this->imgBaseURL)) {
			$storage = shopp_setting('FSStorage');
			if ($storage === false or isset($storage['path']['image']) === false)
				return false;

			$this->imgBaseDir = $storage['path']['image'];
			$this->imgBaseURL = $this->tryToFindPublicURL($this->imgBaseDir);
		}

		if (empty($this->imgBaseURL)) return false;
		return true;
	}


	/**
	 * Tries to find the public URL for Shopp product images stored using the
	 * FSStorage engine. Not bulletproof, it assumes that either the directory
	 * is subordinate to ABSPATH or is anyway relative to wp-content.
	 */
	protected function tryToFindPublicURL($storagePath) {
		$wpURL = get_option('siteurl');
		$wpDir = trim(ABSPATH, '/');
		$storageDir = trim($storagePath, '/');

		$wpDir = explode('/', $wpDir);
		$storageDir = explode('/', $storageDir);

		// Determine if the storage path leads to a WP sub-directory
		for ($segment = 0; $segment < count($wpDir); $segment++)
			if ($wpDir[$segment] !== $storageDir[$segment])
				// Bad match? Check if we have a relative-to-wp-content path instead
				return $this->relativePathOrFalse($storagePath);

		// Supposing the image directory isn't the WP root, append the trailing component
		if (count($storageDir) > count($wpDir))
			$trailingComponent = join('/', array_slice($storageDir, count($wpDir)));

		// Under normal circumstances we now have the public URL for the image dir
		if (isset($trailingComponent)) $publicURL = trailingslashit($wpURL).$trailingComponent;
		else $publicURL = $wpURL;

		return trailingslashit($publicURL);
	}


	/**
	 * Tests if the path leads to a real directory that is subordinate to the
	 * wp-content dir, or returns bool false.
	 */
	protected function relativePathOrFalse($path) {
		$path = trim($path, '/');
		$wpContent = trailingslashit(WP_CONTENT_DIR);
		$path = $wpContent.$path;

		if (is_dir($path)) return trailingslashit(WP_CONTENT_URL).$path;
		return false;
	}


	public function filterImageURLs($html) {
		$position = 0;
		$imgSources = array();

		// Scan for the opening section of an image element
		while (($imgTag = $this->seekString($html, '<img ', '>', $position)) !== false) {
			// Try to retrieve the src attribute value
			$srcVal = $this->seekString($imgTag, 'src="', '"');
			if ($srcVal !== false)
				$imgSources[] = $srcVal;
		}

		// Now attempt to replace each image src
		foreach ($imgSources as $srcURL)
			$html = str_replace($srcURL, $this->replacementImgSrc($srcURL), $html);

		return $html;
	}


	/**
	 * Finds a substring of $source bounded by the $startDelimiter and
	 * $endDelimiter.
	 *
	 * If $position is passed this will be updated to indicate the end of the
	 * matched string. Optional param $inner can be set to false if it is desirable
	 * to capture the delimiters.
	 *
	 * If the substring match fails, false is returned.
	 */
	protected function seekString($source, $startDelimiter, $endDelimiter, &$position = 0, $inner = true) {
		// Search for the start - return false if not found
		$start = strpos($source, $startDelimiter, $position);
		if ($start === false) return false;

		// Move past the start delimiter
		$position = $start + strlen($startDelimiter);

		// Now search for the end - return false if not found
		$end = strpos($source, $endDelimiter, $position);
		if ($end === false) return false;

		// Move past the end delimiter - adjust end position by 1 char
		$position = $end + strlen($endDelimiter);
		$end++;

		// If inner is true, adjust so the delimiters are not returned
		if ($inner) {
			$start += strlen($startDelimiter);
			$end -= strlen($endDelimiter);
		}

		// Extract the string from inbetween the delimiters
		$length = $end - $start;
		return substr($source, $start, $length);
	}


	protected function replacementImgSrc($imgSrc) {
		// Break down the image URL - if false then return the Image Server URL
		$request = parse_url($imgSrc);
		if ($request === false) return $imgSrc;

		// Look out for clear PNG requests! This clear PNG replacement
		// behaviour can be turned off using the following filter
		if (apply_filters('shopp_clear_image_replacement', true) === true) {
			if (strpos($request['path'], '000') === 0) return $this->clearPNGRequest();
		}
		else {
			return $imgSrc;
		}

		$imgID = -1;
		$params = '';

		// Pull the image ID from the URL query or URL segment (depending on
		// whether default or pretty permalinks are in use
		if (isset($query['siid'])) {
			$imgID = $query['siid'];
		}
		elseif (preg_match('/\/images\/(\d+).*$/', $imgSrc, $matches)) {
			if (is_array($matches) and count($matches) === 2)
				$imgID = $matches[1];
		}

		// No image ID match? Return the Shopp Image Server URL
		if ($imgID === -1) return $imgSrc;

		// Form the params part of the filename (if necessary)
		if (isset($request['query']))
			$params = $this->getImageParamsFromQuery($request['query']);

		$filename = $this->determineFilename($imgID);

		$cachePath = $this->imgBaseDir.'/'.$params.$filename;
		$cacheURL = $this->imgBaseURL.$params.$filename;

		$sourcePath = $this->imgBaseDir.'/'.$filename;
		$sourceURL = $this->imgBaseURL.$filename;

		// Lets look for the resized and cached file first of all
		if (file_exists($cachePath)) {
			return $cacheURL;
		}

		// It's possible the specific size requested is that of the source (original) image
		elseif (file_exists($sourcePath)) {
			$img = getimagesize($sourcePath);
			if (is_array($img)) {
				$width = (int) $img[0];
				$height = (int) $img[1];

				if ($height === $this->requestedHeight and $width === $this->requestedWidth)
					return $sourceURL;
			}
		}

		// Fallback on the Shopp Image Server
		return $imgSrc;
	}


	protected function getImageParamsFromQuery($queryStr) {
		// Parse the query element
		$query = array();
		parse_str($queryStr, $query);

		// Locate the first key to contain commas
		foreach ($query as $key => $unused)
			if (strpos($key, ',') !== false)
				$params = $key;

		// Concatenate upto the first 5 image parameters in a single string
		$params = explode(',', $params);
		if (count($params) > 5)
			$params = array_slice($params, 0, 5);

		// It's useful to record the width and height for future use
		if (count($params) >= 2) {
			$this->requestedWidth = (int) $params[0];
			$this->requestedHeight = (int) $params[1];
		}

		$params = join('_', $params);
		return "cache_{$params}_";
	}


	protected function determineFilename($imageID) {
		global $wpdb;
		$imageID = absint($imageID);

		$imgMeta = $wpdb->get_var("
			SELECT `value` FROM `{$wpdb->prefix}shopp_meta`
			WHERE `id` = '$imageID' LIMIT 1;");

		$imgMeta = (array) unserialize($imgMeta);

		if (isset($imgMeta['filename'])) return $imgMeta['filename'];
		else return '';
	}


	/**
	 * Replace clear PNG requests with a single pixel, statically served image.
	 *
	 * Normally Shopp generates transparent PNGs of the specified size
	 * dynamically (no caching) - we will let the img size attributes take care
	 * of proportions.
	 */
	protected function clearPNGRequest() {
		return ShoppImageTools::$url.'/resources/transparent-pixel.png';
	}
}