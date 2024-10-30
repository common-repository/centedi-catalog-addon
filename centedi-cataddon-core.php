<?php
/*
Copyright 2009-2022 CentEDI s.r.o.
*/
if (!defined('ABSPATH')) {
	exit;
}
class CentediCore
{
	protected $commonUtils;
	protected $productUtils;

	public function __construct()
	{
		require_once('centedi-cataddon-config.php');
		require_once('centedi-cataddon-utils.php');
		require_once('centedi-cataddon-product-utils.php');

		$this->commonUtils = new CentediUtils();
		$this->productUtils = new CentediProductUtils();
	}
	public function getProductUtils()
	{
		return $this->productUtils;
	}
	public function getCommonUtils()
	{
		return $this->commonUtils;
	}
	public function saveAuthData($data)
	{
		if (is_array($data)) {
			$this->commonUtils->saveCediCommonData($data);
			return [
				'status' => 'OK',
				'msg' => __('You have successfully registered!')
			];
		}
		return ['status' => 'OK'];
	}
	public function getAuthData($clientOnly = false)
	{
		return [
			'status' => 'OK',
			'data' => [
				'authdata' => $this->commonUtils->getCediAuthData($clientOnly),
				'mudata' => $this->commonUtils->getMeasureDataTypes()
			]
		];
	}
	public function saveProduct($encData, $update = false)
	{
		$msg = $update == false ? __("Product successfully imported!", 'centedi-cataddon') : __("Product successfully updated!", 'centedi-cataddon');
		$status = "OK";
		if (is_array($encData)) {
			// TODO: all messages will be encrypted fully, so we dont need to decrypt here
			$data =	json_decode(json_encode($encData["product_data"]["DATA"]), true);

			// first create category if needed
			if (is_array($data)) {
				if (!$update) {
					foreach ($data as $sku => $skuData) {
						$catId = false;
						$this->productUtils->createCategory(array('name' => $skuData["GROUP_NAME"], 'GROUP_ID' => $skuData["GROUP_ID"], 'img' => isset($skuData["GROUP_IMAGE_VERSIONS"]) ? $this->parseImages($skuData["GROUP_IMAGE_VERSIONS"])[1] : ""), $catId, $categorySaveResultMsg);
						if ($catId) {
							// create attribute sets
							$attrSets = false;
							$this->productUtils->createAttributeSets($catId, $skuData, $attrSets, $attrSetsSaveResultMsg);
							if ($attrSets) {
								$searchData = $encData["search_data"];
								$searchData['PARENT_ID'] = $searchData['ID'];
								$searchData['SKU'] = $sku;
								$searchData['CODE'] = $skuData["CODE"];
								$searchData['PARENT_IMAGE_VERSIONS'] = $this->parseImages($skuData["PARENT_IMAGE_VERSIONS"]);
								$searchData['IMAGE_VERSIONS'] = $this->parseImages($skuData["IMAGE_VERSIONS"]);
								$searchData['BRANDIMAGE'] = $this->parseImages($searchData["BRAND_IMAGE_VERSIONS"]);
								$searchData['FULLDATA'] = $skuData;
								$productSaveResultMsg = $this->productUtils->createProduct($searchData, $catId, $attrSets, $resultId, $update);
								if (!$resultId) {
									$msg = __("Error creating product: ", 'centedi-cataddon') . $productSaveResultMsg;
									$status = "FAILED";
								}
							} else {
								$msg = __("Error creating attribute sets: ", 'centedi-cataddon') . $attrSetsSaveResultMsg;
								$status = "FAILED";
							}
						} else {
							$msg = __("Error creating category: ", 'centedi-cataddon') . $categorySaveResultMsg;
							$status = "FAILED";
						}
					}
				} else {
					$catId = false;
					$this->productUtils->createCategory(array('name' => $data["GROUP_NAME"], 'GROUP_ID' => $data["GROUP_ID"], 'img' => isset($data["GROUP_IMAGE_VERSIONS"]) ? $this->parseImages($data["GROUP_IMAGE_VERSIONS"])[1] : ""), $catId, $categorySaveResultMsg);
					if ($catId) {
						// create attribute sets
						$attrSets = false;
						$this->productUtils->createAttributeSets($catId, $data, $attrSets, $attrSetsSaveResultMsg);
						if ($attrSets) {
							$encData["search_data"]['PARENT_IMAGE_VERSIONS'] = $this->parseImages($encData["search_data"]['PARENT_IMAGE_VERSIONS']);
							$encData["search_data"]['IMAGE_VERSIONS'] = $this->parseImages($encData["search_data"]['IMAGE_VERSIONS']);
							$encData["search_data"]['BRANDIMAGE'] = $this->parseImages($encData["search_data"]["BRAND_IMAGE_VERSIONS"]);
							$encData["search_data"]['FULLDATA'] = $data;
							$productSaveResultMsg = $this->productUtils->createProduct($encData["search_data"], $catId, $attrSets, $resultId, $update);
							if (!$resultId) {
								$msg = __("Error creating product: ", 'centedi-cataddon') . $productSaveResultMsg;
								$status = "FAILED";
							}
						} else {
							$msg = __("Error creating attribute sets: ", 'centedi-cataddon') . $attrSetsSaveResultMsg;
							$status = "FAILED";
						}
					} else {
						$msg = __("Error creating category: ", 'centedi-cataddon') . $categorySaveResultMsg;
						$status = "FAILED";
					}
				}
			} else {
				return ['status' => "FAILED", 'msg' => __("Invalid data or API key. Please contact CentEDI support.", 'centedi-cataddon')];
			}
		}
		return ['status' => $status, 'msg' => $msg];
	}
	public function parseImages($imgData)
	{
		$resArray = [];
		$selectedImgExt = get_option(CEDI_IMG_CFG_FIELD_NAME);
		if ($selectedImgExt !== false) {
			foreach ($imgData as $index => $cfgData) {
				/* i.e. 
					"GROUP_IMAGE_VERSIONS":{
						"1":{
						"ext":"webp",
						"path":"PORTALURL/?img=MD5_HASH"
						}
					}
				*/
				if ($cfgData['ext'] == $selectedImgExt) {
					$resArray[] = $cfgData['path'];
				}
			}
		}
		return $resArray;
	}
	public function updateProduct($encData)
	{
		return $this->saveProduct($encData, true);
	}

	public function getProductsForUpdate($serverUpdates)
	{
		global $wpdb;
		$existingUpdates = [];
		foreach ($serverUpdates as $cediProdId) {
			$prod_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . CEDI_PROD_TABLE . " WHERE product_cedi_id=%d", $cediProdId));
			if ($prod_count > 0) {
				$wooId = $this->productUtils->getWooProductIdByCediId($cediProdId);
				if ($wooId) {
					$product = new WC_Product($wooId);
					$existingUpdates[$cediProdId] = $product->get_name();
				}
			}
		}
		return [
			'status' => 'OK',
			'data' => [
				'proddata' => $existingUpdates
			]
		];
	}
}
