<?php
class ShoppImageToolsAdmin extends AdminController {
	public $dir;


	public function __construct() {
		$this->dir = dirname(__FILE__);
	}


	public function admin() {
		if (wp_verify_nonce($_GET['_wpnonce'], 'imageconversion')) {
			$output = '<ol>';
			$converter = new ShoppImageToolsConverter;
			$response = $converter->task('initialize');
			$output .= '<li>'.$response['message'].'</li>';

			while ($response['status'] === 'continue') {
				$response = $converter->task('process');
				$output .= '<li>'.$response['message'].'</li>';
			}

			$conversionLog = "$output </ol>";
		}

		$directOption = 'shopp_image_tools_direct_mode';
		$directMode = (bool) get_option($directOption, false);

		if (wp_verify_nonce($_GET['_wpnonce'], 'directmode')) {
			if ($directMode) $directMode = false;
			else $directMode = true;
			update_option($directOption, $directMode);
		}

		$conversionAction = wp_nonce_url(
			get_admin_url(null, 'admin.php?page=shopp-settings-image-tools'),
			'imageconversion');
		$directModeAction = wp_nonce_url(
			get_admin_url(null, 'admin.php?page=shopp-settings-image-tools'),
			'directmode');
		$totals = ShoppImageToolsConverter::summaryTotals();
		$tickIcon = ShoppImageTools::$url.'/resources/nuvola-tick-16px.png';
		$crossIcon = ShoppImageTools::$url.'/resources/nuvola-cross-16px.png';

		include $this->dir.'/adminui.php';
	}
}