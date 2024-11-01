<?php
class ShoppImageToolsConverter {
	protected $storagePath = '';
	protected $message = '';
	protected $cleanup = false;
	protected $cleanupResult;


	public function __construct($cleanup = false) {
		$this->cleanup = (bool) $cleanup;
	}


	public function task($request = 'initialize') {
		switch ($request) {
			case 'initialize': return $this->preflightChecks(); break;
			case 'process': return $this->convertNextImage(); break;
		}
	}


	protected function preflightChecks() {
		$totals = self::summaryTotals();
		if ($totals['db'] < 1) return array(
			'message' => __('There are no database-served images in need of conversion!', 'shoppimagetools'),
			'status' => 'stop'
		);

		$this->storagePath = $this->getFileStoragePath();
		if ($this->storagePath === false) return array(
			'message' => __('No file storage path has been set.', 'shoppimagetools'),
			'status' => 'stop'
		);

		if (file_exists($this->storagePath) === false or is_dir($this->storagePath) === false) return array(
			'message' => __('The file storage path does not exist or is not a directory!', 'shoppimagetools'),
			'status' => 'stop'
		);

		if (is_writable($this->storagePath) === false) return array(
			'message' => __('The file storage path is not writeable, please correct this first of all!', 'shoppimagetools'),
			'status' => 'stop'
		);

		return array(
			'message' => sprintf(__('Ready to process. The following filepath will be used: %s', 'shoppimagetools'), $this->storagePath),
			'status' => 'continue'
		);
	}


	protected function convertNextImage() {
		// Nothing left to convert
		$totals = self::summaryTotals();
		if ($totals['db'] < 1) return array(
			'message' => __('No more database-served images can be found.', 'shoppimagetools'),
			'status' => 'stop'
		);

		// The image record was malformed or otherwise not as we would expect
		$imageRecord = $this->retrieveNextImageRecord();
		if ($imageRecord === null) return array(
			'message' => __('An unexpected error occurred while trying to convert an image.', 'shoppimagetools'),
			'status' => 'stop'
		);

		// Unable to retrieve key data from the image record
		$image = $this->pullImageData($imageRecord->value);
		if ($image === null or $image === false) return array(
			'message' => __('Image "'.$imageRecord->value->filename.'" could not be retrieved. '
				.'Unable to continue.', 'shoppimagetools'),
			'status' => 'stop'
		);

		// Convert!
		$conversion = $this->convertToFile($imageRecord, $image);

		// Successful (cleanup successfully completed, too)
		if ($conversion and is_bool($this->cleanupResult) and $this->cleanupResult) return array(
			'message' => __('Image "'.$imageRecord->value->filename.'" successfully converted and has been removed from the database ', 'shoppimagetools'),
			'status' => 'continue'
		);

		// Successful - except for the cleanup operation
		elseif ($conversion and is_bool($this->cleanupResult) and !$this->cleanupResult) return array(
			'message' => __('Image "'.$imageRecord->value->filename.'" successfully converted <em>but could not be removed from the database</em>. ', 'shoppimagetools'),
			'status' => 'continue'
		);

		// Successful, no cleanup requested
		elseif ($conversion and !isset($this->cleanupResult)) return array(
			'message' => __('Image "'.$imageRecord->value->filename.'" successfully converted. ', 'shoppimagetools'),
			'status' => 'continue'
		);

		// Unsuccessful
		else {
			$message = (empty($this->message) === false) ? '['.$this->message.']' : '';
			return array(
				'message' => __('Image "'.$imageRecord->value->filename.'" could not be converted: '
					.'you may need to repair this image record. Conversion will now stop. ',
					'shoppimagetools')
					.$message,
				'status' => 'stop'
			);
		}
	}


	protected function retrieveNextImageRecord() {
		global $wpdb;

		$record = $wpdb->get_row("
			SELECT * FROM `{$wpdb->prefix}shopp_meta`
			WHERE `type` = 'image'
			AND   `value` LIKE '%DBStorage%'
			LIMIT 1; ");

		$record->value = @unserialize($record->value);
		return $record;
	}


	protected function pullImageData($imageMeta) {
		if (property_exists($imageMeta, 'uri') === false) return false;
		$assetID = (int) $imageMeta->uri;

		global $wpdb;
		return $wpdb->get_var("
			SELECT `data` FROM `{$wpdb->prefix}shopp_asset`
			WHERE `id` = '$assetID'; ");
	}


	protected function convertToFile($record, $image) {
		$fullPath = trailingslashit($this->getFileStoragePath()).$record->value->filename;

		// Check for a pre-existing file of the same name and amend if necessary
		while (file_exists($fullPath))
			$fullPath = $this->numericallyIncrementFilename($fullPath);

		// Try to obtain a file handle
		$file = fopen($fullPath, 'wb'); // "b" flag for binary format (Windows)
		if ($file === false) {
			$this->message = __('Could not obtain a file handle', 'shoppimagetools');
			return false;
		}

		$bytes = fwrite($file, $image);
		fclose($file);

		// Test for a successful write operation
		if ($bytes === 0 or $bytes === false) {
			$this->message = __('The write operation failed (or zero bytes were committed)', 'shoppimagetools');
			return false;
		}

		// Record the entry's location in the asset table first of all
		$assetID = $record->value->uri;

		// Update the meta record
		$record->value->storage = 'FSStorage';
		$record->value->filename = basename($fullPath);
		$record->value->uri = $record->value->filename;
		$imageMeta = serialize($record->value);

		global $wpdb;
		$update = $wpdb->update($wpdb->prefix.'shopp_meta',
			array('value' => $imageMeta),
			array('id' => $record->id),
			array('%s'),
			array('%d'));

		// Do we need to cleanup, was the update successful and do we have an asset key?
		if ($this->cleanup and is_int($assetID) and $update !== false) $this->smartcleanup($assetID);

		return ($update === false) ? false : true;
	}


	protected function smartcleanup($assetID) {
		global $wpdb;

		if (absint($assetID) !== $assetID) {
			$this->cleanupResult = false;
			return;
		}

		// Kill the asset table entry
		$result = $wpdb->query("DELETE FROM `{$wpdb->prefix}shopp_asset` WHERE `id` = '$assetID' LIMIT 1;");

		if ($result !== false and $result > 0) $this->cleanupResult = true;
		else $this->cleanupResult = false;
	}


	protected function numericallyIncrementFilename($filename) {
		$ext = '';
		$lastDot = strrpos($filename, '.');
		$diff = strlen($filename) - $lastDot;

		if ($lastDot !== false) {
			$body = substr($filename, 0, $lastDot);
			$ext = substr($filename, ++$lastDot, --$diff);
		}
		else {
			$body = $filename;
		}

		// Check if the filename is already indexed (filename-n)
		$alreadyIndexed = preg_match('#-\d+$#', $body);

		// Increment the existing index
		if ($alreadyIndexed === 1) {
			$hyphen = strrpos($body, '-');
			$diff = strlen($body) - $hyphen - 1;
			$index = substr($body, ++$hyphen, $diff);

			$index++;
			$body = substr($body, 0, $hyphen).$index;
		}
		else {
			$body = "$body-1";
		}

		if (!empty($ext)) $filename = "$body.$ext";
		else $filename = $body;

		return $filename;
	}

	protected function getFileStoragePath() {
		$fileStorage = shopp_setting('FSStorage');

		if ($fileStorage === false or
			is_array($fileStorage) === false or
			array_key_exists('path', $fileStorage) === false or
			is_array($fileStorage['path']) === false or
			array_key_exists('image', $fileStorage['path']) === false)
				return false;

		return $fileStorage['path']['image'];
	}


	public static function summaryTotals() {
		global $wpdb;
		$totals = array();

		$totals['all'] = (int) $wpdb->get_var("
			SELECT COUNT(*) FROM `{$wpdb->prefix}shopp_meta`
			WHERE `type`='image';");

		$totals['fs'] = (int) $wpdb->get_var("
			SELECT COUNT(*) FROM `{$wpdb->prefix}shopp_meta`
			WHERE `type`='image'
			AND `value` LIKE '%FSStorage%';");

		$totals['db'] = (int) $wpdb->get_var("
			SELECT COUNT(*) FROM `{$wpdb->prefix}shopp_meta`
			WHERE `type`='image'
			AND `value` LIKE '%DBStorage%';");

		$totals['other'] = $totals['all'] - $totals['db'] - $totals['fs'];
		return $totals;
	}
}