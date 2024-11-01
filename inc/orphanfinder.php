<?php
class ShoppImageToolsOrphanFinder {
	const STATE_TRANSIENT = 'shoppImageToolsOF';
	const TIMEOUT = 2800;


	protected $state = array(
		'registeredAssets' => array(),
		'offset' => 0,
		'removed' => 0
	);

	protected $started = 0;


	public function __construct() {
		$this->started = mktime();
	}


	public function task($request = 'initialize') {
		switch ($request) {
			case 'initialize':
				$msg = $this->initialize();
			break;
			case 'process':
				$this->loadState();
				$msg = $this->process();
			break;
		}

		$this->saveState();
		if ($msg['status'] === 'stop') delete_transient(self::STATE_TRANSIENT);
		return $msg;
	}


	protected function loadState() {
		$state = get_transient(self::STATE_TRANSIENT);
		if ($state === false) return;
		$this->state = array_merge($this->state, (array) $state);
	}


	protected function saveState() {
		set_transient(self::STATE_TRANSIENT, $this->state, 120);
	}


	/**
	 * We need to build a list of images/other assets that should not be purged from the
	 * assets table, first of all.
	 *
	 * @return array
	 */
	protected function initialize() {
		global $wpdb;

		// Test to see if GD lib is available before we do anything
		if (!extension_loaded('gd')) return array(
			'message' => __('The GD library is unavailable, processing will stop.', 'shoppimagetools'),
			'status' => 'stop'
		);

		$results = $wpdb->get_results("
			SELECT * FROM `{$wpdb->prefix}shopp_meta`
			WHERE `value` LIKE '%DBStorage%'; ");

		if (is_array($results)) foreach ($results as $row) {
			$assetData = @unserialize($row->value);
			if ($assetData->storage === 'DBStorage' and is_int($assetData->uri))
				$this->state['registeredAssets'][] = $assetData->uri;
		}

		$totalEntries = (int) $wpdb->get_var("
			SELECT COUNT(*) FROM `{$wpdb->prefix}shopp_asset` ");

		$toScan = $totalEntries - count($this->state['registeredAssets']);
		if ($toScan < 0) $toScan = 0;

		$status = ($toScan === 0) ? 'stop' : 'continue'; // No point if there is nothing to remove

		return array(
			'message' => sprintf(__('Starting orphan cleanup: %d valid asset(s) currently in the database, '
				.'%d asset(s) to scan for possible orphans', 'shoppimagetools'),
				count($this->state['registeredAssets']), $toScan),
			'status' => $status
		);
	}


	/**
	 * Scans the asset table for rows (that are not known-to-be-in-use image assets) which contain an image.
	 * Matching rows are deleted.
	 */
	protected function process() {
		global $wpdb;

		$row = $wpdb->get_row("
			SELECT * FROM `{$wpdb->prefix}shopp_asset`
			LIMIT {$this->state['offset']}, 1; ");

		// Check in case we have a db failure/we've reached the end of the line
		if (!$row or is_null($row)) {
			return array(
				'message' => __("Cleanup complete: {$this->state['removed']} asset(s) were removed", 'shoppimagetools'),
				'status' => 'stop'
			);
		}
		// Skip if it is a known/registered asset
		elseif (in_array($row->id, $this->state['registeredAssets'])) {
			$this->state['offset']++;
		}
		// Is it an image?
		elseif ($this->isImage($row->data)) {
			$this->deleteAsset($row->id); // No need to increment the offset as we have removed 1 row
			$this->state['removed']++;
		}
		// Otherwise, it is an unknown asset but not an image - leave alone!
		else {
			$this->state['offset']++;
		}

		// Check for timeout: if we're still in bounds, repeat the process
		if (mktime() - $this->started < self::TIMEOUT)
			$this->process();

		return array(
			'message' => __("{$this->state['removed']} asset(s) removed so far&hellip;", 'shoppimagetools'),
			'status' => 'continue'
		);
	}


	/**
	 * Determines if the data is an image, and returns true or false as appropriate.
	 *
	 * @param $data
	 * @return bool
	 */
	protected function isImage($data) {
		$data = getimagesizefromstring($data);
		if ($data === false) return false;
		if (isset($data['mime']) and strpos($data['mime'], 'image') !== false) return true; // Match
		return false;
	}


	/**
	 * Removes the row specified by $id from the asset table.
	 *
	 * @param $id
	 */
	protected function deleteAsset($id) {
		global $wpdb;

		$assetID = absint($id);
		if ($assetID != $id) return;

		$wpdb->query("DELETE FROM `{$wpdb->prefix}shopp_asset` WHERE `id` = '$assetID' LIMIT 1;");
	}
}