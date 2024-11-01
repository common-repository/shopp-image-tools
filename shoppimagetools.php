<?php
/*
Plugin Name: Shopp Image Tools
Description: Provides a tool that transfers Shopp product images from the database to the filesystem, updating Shopp's meta table so that the file-based images are used in future. It can also change the behaviour of Shopp product image template tags such that use of the Shopp Image Server is kept to an absolute minimum.
Version: 1.1.1
Author: Barry Hughes
Author URI: http://freshlybakedwebsites.net
License: GPL3

    "Shopp Image Tools" (facilitates improved image handling for Shopp)
    Copyright (C) 2012 Barry Hughes

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/


class ShoppImageTools {
	public $dir = '';
	public static $url = '';
	public static $id = 'shoppfimg';


	public static function init() {
		add_action('plugins_loaded', array(__CLASS__, 'loader'));
	}


	public static function loader() {
		if (defined('SHOPP_VERSION') and version_compare(SHOPP_VERSION, '1.2') >= 0)
			new ShoppImageTools;
	}


	public function __construct() {
		$this->dir = dirname(__FILE__);
		self::$url = WP_PLUGIN_URL.'/'.basename($this->dir);

		$this->createAdminPage();
		$this->directImages();
	}


	protected function createAdminPage() {
		if (is_admin()) {
			require $this->dir.'/inc/admin.php';
			require $this->dir.'/inc/converter.php';
			require $this->dir.'/inc/orphanfinder.php';
			add_action('admin_print_styles-setup_page_shopp-settings-image-tools', array($this, 'addAdminCSS'));
			add_action('wp_ajax_shopp_image_tools', array($this, 'converterAjaxInterface'));
			add_filter('shopp_admin_menus', array($this, 'registerSettingPage'));
		}
	}


	protected function directImages() {
		if (is_admin() === false and get_option('shopp_image_tools_direct_mode')) {
			require $this->dir.'/inc/directimages.php';
			new ShoppImageToolsDirectMode;
		}
	}


	public function addAdminCSS() {
		wp_enqueue_style('shopp-image-tools-css', self::$url.'/resources/shoppimagetools.css');
		wp_enqueue_script('shopp-image-tools-js', self::$url.'/resources/shoppimagetools.js');
	}


	public function registerSettingPage($adminFlow) {
		$adminFlow->addpage('settings-image-tools', __('Image Tools', 'shoppimagetools'),
			'ShoppImageToolsAdmin', false, 'settings');
		$adminFlow->caps['settings-image-tools'] = 'shopp_settings_presentation';

		$this->sensibleMenuPositioning($adminFlow);
	}


	/**
	 * Positions the new Image Tools menu item next to the existing Images
	 * item.
	 */
	protected function sensibleMenuPositioning($adminFlow) {
		$newMenu = array();
		$srcMenu = $adminFlow->Pages;

		$imagetoolsKey = 'shopp-settings-image-tools';
		$newItem = $srcMenu[$imagetoolsKey];
		unset ($srcMenu[$imagetoolsKey]);

		foreach ($srcMenu as $key => $object) {
			$newMenu[$key] = $object;
			if ($key === 'shopp-settings-images')
				$newMenu[$imagetoolsKey] = $newItem;
		}

		$adminFlow->Pages = $newMenu;
	}


	public function converterAjaxInterface() {
		if (check_ajax_referer('ajaxconversionop', 'check') and isset($_POST['task']) and isset($_POST['job'])) {
			switch ($_POST['job']) {
				case 'converter': $this->conversionTask(); break;
				case 'orphancleanup': $this->orphanTask(); break;
				default:
					exit(json_encode(array(
						'message' => __('Communication failure.', 'shoppimagetools'),
						'status' => 'stop'
					)));
				break;
			}
		}
		elseif (check_ajax_referer('ajaxconversionop', 'check') and $_POST['job'] === 'summaryupdate') {
			$this->getSummary();
		}
		else {
			exit(json_encode(array(
				'message' => __('Communication failure.', 'shoppimagetools'),
				'status' => 'stop'
			)));
		}
	}


	protected function conversionTask() {
		$cleanup = (isset($_POST['cleanup']) and $_POST['cleanup'] == '1') ? true : false;
		$converter = new ShoppImageToolsConverter($cleanup);
		$batchSize = apply_filters('shopp_img_conversion_batch_size', 12);
		$responses = array();

		if ($_POST['task'] === 'initialize') $batchSize = 1;

		for ($count = 1; $count <= $batchSize; $count++) {
			$response = $converter->task($_POST['task']);
			$responses[] = $response['message'];

			if ($response['status'] !== 'continue') {
				$responses[] = __('[Process ended]', 'shoppimagetools');
				break;
			}
		}

		$message = join(' --- ', $responses);
		exit(json_encode(array(
			'message' => $message,
			'status' => $response['status']
		)));
	}


	protected function orphanTask() {
		$orphanfinder = new ShoppImageToolsOrphanFinder;
		$responses = array();

		$response = $orphanfinder->task($_POST['task']);
		$responses[] = $response['message'];

		if ($response['status'] !== 'continue')
			$responses[] = __('[Process ended]', 'shoppimagetools');

		$message = join(' --- ', $responses);
		exit(json_encode(array(
			'message' => $message,
			'status' => $response['status']
		)));
	}


	protected function getSummary() {
		exit(json_encode(ShoppImageToolsConverter::summaryTotals()));
	}
}


ShoppImageTools::init();