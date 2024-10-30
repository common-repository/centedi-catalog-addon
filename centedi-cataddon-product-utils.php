<?php
/*
Copyright 2009-2022 CentEDI s.r.o.
*/
if (!defined('ABSPATH')) {
	exit;
}
require_once('centedi-cataddon-utils.php');

class CentediProductUtils
{

	protected $commonUtils;
	private $erasedDataProducts;

	public function __construct()
	{
		$this->commonUtils = new CentediUtils();
		$this->erasedDataProducts = [];
	}

	public function createCategory($data, &$resultCatId, &$error = "")
	{
		// check if cat. exists & create if not. 
		$catId = $this->getCategoryByCID($data['GROUP_ID']);
		if ($catId == false) {
			$slug = $data['name'];
			if (term_exists($slug, 'product_cat')) $slug = 'cedi-' . $data['name'];
			$catData = wp_insert_term(
				$data['name'], // group name 
				'product_cat', // group taxonomy @ woo
				array(
					'slug' => $slug // url key on woo site
				)
			);
			if (!is_wp_error($catData)) {
				$catId = isset($catData['term_id']) ? $catData['term_id'] : 0;
				$this->addCategoryCID($catId, $data['GROUP_ID']);
				// img
				if ($data['img'] != "") {
					$thumbData = $this->storeImage($this->commonUtils->getPortalUrl() . $data['img']);
					if (is_wp_error($thumbData)) {
						$error = $thumbData->get_error_message();
						return;
					}
					update_term_meta($catData['term_id'], 'thumbnail_id', absint($thumbData));
				}
				$resultCatId = $catId;
			} else $error = $catData->get_error_message();
		} else {
			$resultCatId = $catId;
			wp_update_term($catId, 'product_cat', array(
				'name' => $data['name'],
			));
		}
		// common updateable props
		if (isset($data['parentGroupCID'])) $this->addCategoryParentByChildCID($data['GROUP_ID'], $data['parentGroupCID']);
	}
	public function createAttributeSets($catId, $attrData, &$attrSets, &$error = "")
	{
		global $wpdb;
		$dynData = $attrData["ATTRIBUTE_SETS"];
		$setOrder = 1;
		$setCodes = [];
		$attrSetIds = [];

		$wpdb->query('START TRANSACTION');
		try {
			foreach ($dynData as $setData) {
				if ($setData['ATTRIBUTE_SET_UID']) {
					$prepQuery = $wpdb->prepare(
						"INSERT INTO " . CEDI_ATTR_SET_TABLE . " (cat_id,set_legend,set_code,set_order) 
							VALUES (%d,%s,%s,%d) 
							ON DUPLICATE KEY UPDATE set_id=LAST_INSERT_ID(set_id),set_legend=VALUES(set_legend),set_order=VALUES(set_order)",
						$catId,
						$setData['ATTRIBUTE_SET_NAME'],
						md5($setData['ATTRIBUTE_SET_UID']),
						$setOrder
					);
					$results = $wpdb->query($prepQuery);
					if (!is_wp_error($results)) {
						$setId = $wpdb->insert_id;
						$attrSetIds[$setId] = [];
						$attrArr = $setData["ATTRIBUTES"];
						foreach ($attrArr as $attr) {
							$attrId = $this->createAttribute($attr, $setId, $suberr);
							if (!$attrId) {
								$error = '<br><br>' . __('Attribute [') . $attr['ATTRIBUTE_NAME'] . ']: ' . $suberr;
								$wpdb->query('ROLLBACK');
								return;
							} else {
								if (array_key_exists('ATTRIBUTE_VALUE', $attr)) $attrSetIds[$setId][$attrId] = $attr['ATTRIBUTE_VALUE'];
							}
						}
						if ($setData['ATTRIBUTE_SET_UID']) $setCodes[] = "'" . md5($setData['ATTRIBUTE_SET_UID']) . "'";
						$setOrder += 1;
					} else {
						$error = $results->get_error_message();
						$wpdb->query('ROLLBACK');
						return;
					}
				}
			}
		} catch (Exception $e) {
			$error = $e->getMessage();
			$wpdb->query('ROLLBACK');
			return;
		}
		if (count($setCodes) == 0) {
			$error = "Couldn't create attribute sets!";
			$wpdb->query('ROLLBACK');
			return;
		}

		$wpdb->query('COMMIT');
		$attrSets = $attrSetIds;
		// remove unused sets
		// $setCodes contains results of md5() so we dont need to prepare()
		$wpdb->query("DELETE FROM " . CEDI_ATTR_SET_TABLE . " WHERE cat_id=" . $catId . " AND set_code NOT IN (" . implode(',', $setCodes) . ")");
		$this->cleanupAttributes($attrSetIds);
	}
	function cleanupAttributes($portalSetData)
	{
		foreach ($portalSetData as $setId => $setData) {
			$portalSetAttrIds = [];
			foreach ($setData as $attrID => $attrVal) {
				$portalSetAttrIds[] = $this->getCentAttrIdById($attrID);
			}
			global $wpdb;
			// no prepare() here, $setId = $wpdb->insert_id
			$localSetAttrs = $wpdb->get_results("SELECT attr_cid,attr_woo_id FROM " . CEDI_ATTR_TABLE . " WHERE set_id=" . $setId . " AND attr_obj_prop_main_id =\"\"", OBJECT);
			foreach ($localSetAttrs as $localSetAttr) {
				if (array_search($localSetAttr->attr_cid, $portalSetAttrIds) === false) {
					// remove attribute from custom table & woo
					$wpdb->query("DELETE FROM " . CEDI_ATTR_TABLE . " WHERE attr_cid=" . $localSetAttr->attr_cid);
					wc_delete_attribute($localSetAttr->attr_woo_id);
				}
			}
		}
	}
	private function array_value($array, $key, $default_value = null)
	{
		return is_array($array) && array_key_exists($key, $array) ? $array[$key] : $default_value;
	}
	private function createAttribute($attrData, $setId, &$error = "")
	{
		global $woocommerce;
		global $wpdb;
		// check if we already have attribute with the same table & then use it
		$existingId = null;
		// check if we already have this attribute
		$existingId = $wpdb->get_var("SELECT attr_id FROM " . CEDI_ATTR_TABLE . " WHERE attr_code='" . md5($attrData['ATTRIBUTE_UID']) . "'");
		//print_r($attrData);
		if (!$existingId) {
			// check if we have this attribute in woocommerce ( in case of database loss )
			$slug = wc_sanitize_taxonomy_name(stripslashes(str_replace("uid", "cedi-", $attrData['ATTRIBUTE_UID'])));
			$id = $wpdb->get_var("SELECT attribute_id FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_name='" . $slug . "'");
			if (!$id) {
				// create attribute for wc
				$id = wc_create_attribute(array(
					'name'         => $attrData['ATTRIBUTE_NAME'],
					'slug'         => $slug,
					'type'         => 'select',
					'order_by'     => 'menu_order', // name_num for numeric values?
					'has_archives' => 1
				));
			}
			if (!is_wp_error($id)) {
				// store it's data in db
				$prepQuery = $wpdb->prepare(
					"INSERT INTO " . CEDI_ATTR_TABLE . "
						(attr_woo_id,
						attr_cid,
						set_id,
						attr_name,
						attr_type,
						attr_code,
						attr_table_code,
						attr_persku,
						attr_ess_choice,
						attr_filterable,
						attr_sku_width,
						attr_sku_height,
						attr_sku_length,
						attr_sku_weight,
						attr_table_type,
						attr_obj_prop_main_id,
						attr_obj_as_set,
						attr_tooltip) 
					VALUES (%d,	%d, %d, %s, %s, %s, %s, %d, %d, %d, %d, %d, %d, %d, %s, %s, %d, %s) 
					ON DUPLICATE KEY UPDATE 
						attr_id=LAST_INSERT_ID(attr_id),
						attr_cid=attr_cid,
						attr_name=attr_name,
						attr_type=attr_type,
						attr_code=attr_code,
						attr_table_code=attr_table_code,
						attr_filterable=attr_filterable,
						attr_persku=attr_persku,
						attr_ess_choice = attr_ess_choice,
						attr_sku_width=attr_sku_width,
						attr_sku_height=attr_sku_height,
						attr_sku_length=attr_sku_length,
						attr_sku_weight=attr_sku_weight,
						attr_table_type=attr_table_type,
						attr_obj_prop_main_id=attr_obj_prop_main_id,
						attr_obj_as_set=attr_obj_as_set,
						attr_tooltip=attr_tooltip",
					$id,
					$attrData['ATTRIBUTE_ID'],
					$setId,
					$attrData['ATTRIBUTE_NAME'],
					$attrData['ATTRIBUTE_VISUAL_TYPE'],
					md5($attrData['ATTRIBUTE_UID']),
					$this->array_value($attrData, 'ATTRIBUTE_TABLE_UID', ''),
					$this->array_value($attrData, 'ATTRIBUTE_IS_PER_SKU', 0),
					$this->array_value($attrData, 'ATTRIBUTE_IS_ESSENTIAL_FOR_CHOICE', 1),
					$this->array_value($attrData, 'ATTRIBUTE_IS_FILTERABLE', 1),
					$this->array_value($attrData, 'ATTRIBUTE_SKU_WIDTH', 0),
					$this->array_value($attrData, 'ATTRIBUTE_SKU_HEIGHT', 0),
					$this->array_value($attrData, 'ATTRIBUTE_SKU_LENGTH', 0),
					$this->array_value($attrData, 'ATTRIBUTE_SKU_WEIGHT', 0),
					$this->array_value($attrData, 'ATTRIBUTE_TABLE_TYPE', ""),
					$this->array_value($attrData, 'ATTRIBUTE_OBJECT_PROPERTY_ID', ""),
					$this->array_value($attrData, 'ATTRIBUTE_DISPLAY_AS_SET', 0),
					str_replace("\n", "<br/>", $this->array_value($attrData, 'ATTRIBUTE_INFO', ""))
				);

				$result = $wpdb->query($prepQuery);

				$attrId = $wpdb->insert_id;

				if (!is_wp_error($result)) {
					// terms
					if (!taxonomy_exists('pa_' . $slug)) {
						register_taxonomy(
							'pa_' . $slug,
							'product_variation',
							array(
								'hierarchical' => false,
								'label' => ucfirst($attrData['ATTRIBUTE_NAME']),
								'query_var' => true,
								'rewrite' => array('slug' => sanitize_title($attrData['ATTRIBUTE_NAME']))
							)
						);
					}
					/*if ((isset($attrData['ATTRIBUTE_VALUE_ARRAY'])) && (is_array($attrData['ATTRIBUTE_VALUE_ARRAY']))) {
						foreach ($attrData['ATTRIBUTE_VALUE_ARRAY'] as $term) {
							$this->addAttrOptionByName("pa_" . $slug, (string)$term);
						}
					}*/
					return $attrId;
				} else $error = $result->get_error_message();
			} else $error = $id->get_error_message();
		} else {
			// update attribute name etc
			$prepQuery = $wpdb->query($wpdb->prepare("UPDATE " . $wpdb->prefix . "woocommerce_attribute_taxonomies SET attribute_label=%s,attribute_public=1 WHERE attribute_id=%d", ucfirst($attrData['ATTRIBUTE_NAME']), $this->getAttrWCIdById($existingId)));
			delete_transient('wc_attribute_taxonomies');
			// update table type
			$isManualMode = (get_option('cedi_admin_settings_tab_filters_cb_manual_attribute_filters') == 'yes');
			$attrIsFilterable = $this->array_value($attrData, 'ATTRIBUTE_IS_FILTERABLE', 1);
			if ($isManualMode) {
				$groupId = $wpdb->get_var("SELECT DISTINCT cat_id FROM " . CEDI_ATTR_SET_TABLE . " WHERE set_id=" . $setId);
				$attrIsFilterable = get_option('cedi_attr_' . $groupId . '_' . $this->getAttrWCIdById($existingId), 0);
			}
			$prepQuery = $wpdb->prepare(
				"UPDATE " . CEDI_ATTR_TABLE . " SET 
					attr_name=%s,
					attr_type=%s,
					attr_tooltip=%s,
					attr_code=%s,
					attr_table_code=%s,
					attr_table_type=%s,
					attr_obj_as_set=%d,
					attr_persku=%d,
					attr_ess_choice=%d,
					attr_filterable=%d,
					attr_sku_width=%d,
					attr_sku_height=%d,
					attr_sku_length=%d,
					attr_sku_weight=%d
				WHERE attr_id=%d",
				$attrData['ATTRIBUTE_NAME'],
				$attrData['ATTRIBUTE_VISUAL_TYPE'],
				$this->array_value($attrData, 'ATTRIBUTE_INFO', ""),
				md5($attrData['ATTRIBUTE_UID']),
				$this->array_value($attrData, 'ATTRIBUTE_TABLE_UID', ''),
				$this->array_value($attrData, 'ATTRIBUTE_TABLE_TYPE', ""),
				$this->array_value($attrData, 'ATTRIBUTE_DISPLAY_AS_SET', 0),
				$this->array_value($attrData, 'ATTRIBUTE_IS_PER_SKU', 0),
				$this->array_value($attrData, 'ATTRIBUTE_IS_ESSENTIAL_FOR_CHOICE', 1),
				$attrIsFilterable,
				$this->array_value($attrData, 'ATTRIBUTE_SKU_WIDTH', 0),
				$this->array_value($attrData, 'ATTRIBUTE_SKU_HEIGHT', 0),
				$this->array_value($attrData, 'ATTRIBUTE_SKU_LENGTH', 0),
				$this->array_value($attrData, 'ATTRIBUTE_SKU_WEIGHT', 0),
				$existingId
			);
			$wpdb->query($prepQuery);
			return $existingId;
		}
		return false;
	}

	private function addProductAttributeOption($product, $wc_attribute, $attr_type, $opt, $doAppend = false)
	{
		$optId = null;
		$opt = trim($opt);
		if (strlen($opt) > 0) {
			if ($attr_type == "boolean") $opt = $opt == 1 ? __("Yes", 'centedi-cataddon') : __("No", 'centedi-cataddon');
			if ($attr_type == "date") {
				$datetime = new DateTime($opt);
				$opt = $datetime->format('d/m/Y');
			}
			$optId = $this->getAttrOptionIdByName($wc_attribute->slug, $opt);
			if (!$optId)	$optId = $this->addAttrOptionByName($wc_attribute->slug, $opt);

			// add new terms
			if (!has_term($opt, $wc_attribute->slug, $product->get_id())) wp_set_object_terms($product->get_id(), $opt, $wc_attribute->slug, $doAppend); // ,true); if we want to append 
		}
		return $optId;
	}
	function createProduct($productData, $catId, $attrSets, &$resultId, $isUpdate = false)
	{

		global $woocommerce;
		global $wpdb;

		$wpdb->show_errors = false;

		$wpdb->query('START TRANSACTION');

		$existingId = $this->getWooProductIdByCediId($productData['PARENT_ID']);

		$className = "WC_Product_Variable";
		// disable simple for now
		$productData['SIMPLE'] = 0;

		if ($productData['SIMPLE'] == 1) $className = "WC_Product_Simple";

		if ($existingId) {
			$product = new $className($existingId);
			$isUpdate = true;
		} else {
			// non-updateable props for new product
			$product = new $className();
			$product->set_status('draft');
			// 'hidden' 'visible' 'search' 'catalog'
			$product->set_catalog_visibility('visible');
			$product->set_featured(false);
			$product->set_virtual(false);
			// category
			$product->set_category_ids(array($catId));
			// SKU for simple product, otherwise it will be variation's SKU
			if ($productData['SIMPLE'] == 1) $product->set_sku($productData['SKU']);
			$product->set_sold_individually(false);
			// Reviews, purchase note and menu order
			$product->set_reviews_allowed(true);
		}
		// common updateable props
		$product->set_name($productData['TITLE']);
		$product->set_short_description($productData['SHORT_DESCRIPTION'] . "<br/><i>" . $productData['KEY_PROPS_DESCRIPTION'] . "</i>");
		$product->set_description($productData['DETAILED_DESCRIPTION']);
		// Images
		$imgInd = 0;
		$galleryImgIds = [];

		//$product->set_gallery_image_ids(array());
		foreach ($productData['PARENT_IMAGE_VERSIONS'] as $imgPath) {
			$imgId = $this->storeImage($this->commonUtils->getPortalUrl() . $imgPath);
			if (is_wp_error($imgId)) {
				$wpdb->query('ROLLBACK');
				return __("Error saving product images", 'centedi-cataddon');
			}
			if ($imgInd == 0) $product->set_image_id($imgId); // main image or ''
			else $galleryImgIds[] = $imgId;
			$imgInd++;
		}
		$product->set_gallery_image_ids($galleryImgIds); // gallery images or array()

		// Attachments
		if (is_array($productData['DOCS']) && (count($productData['DOCS']) > 0)) {
			$wpdb->query($wpdb->prepare("DELETE FROM " . CEDI_ATTR_ATTACHMENTS_TABLE . " WHERE product_cedi_id=%d", $productData['PARENT_ID']));
			foreach ($productData['DOCS'] as $docData) {
				// ensure we have 3 pieces of mandatory data here
				$prodAttachmentData = explode("||", $docData);
				if (count($prodAttachmentData) == 3) {
					// store attachment & save it's path to db
					$storedPath = $this->storeAttachment($productData['PARENT_ID'], $prodAttachmentData);
					if ($storedPath != '') {
						$prodAttachmentData[0] = $storedPath;
						$wpdb->query($wpdb->prepare("INSERT INTO " . CEDI_ATTR_ATTACHMENTS_TABLE . " (product_cedi_id,attachment_data) VALUES (%d,%s)", $productData['PARENT_ID'], implode("||", $prodAttachmentData)));
						if ($wpdb->last_error) {
							$wpdb->query('ROLLBACK');
							return __("Error saving product attachments", 'centedi-cataddon');
						}
					}
				}
			}
		}
		// Attributes
		// create all if product is new
		$existingAttributes = (array)$product->get_attributes();
		$variationAttributes = [];
		$variationDimAttributes = [];
		$variationWeightAttributes = [];

		if (!isset($this->erasedDataProducts[$productData['PARENT_ID']])) {
			foreach ($existingAttributes as $attrSlug => $attr_data) {
				wp_set_object_terms($product->get_id(), NULL, $attrSlug, false);
			}
		}
		$this->erasedDataProducts[$productData['PARENT_ID']] = true;

		foreach ($attrSets as $setId => $attributes) {
			foreach ($attributes as $attrId => $value) {
				$wc_attribute_id = $this->getAttrWCIdById($attrId);
				$wc_attribute = wc_get_attribute($wc_attribute_id);
				$attr_type = $this->getAttrTypeById($attrId);
				$attr_table_type = $this->getAttrTableTypeById($attrId);

				// no prepare() cause $attrId=$wpdb->insert_id
				$isPerSKU = (bool)$wpdb->get_var("SELECT attr_persku FROM " . CEDI_ATTR_TABLE . " WHERE attr_id=" . $attrId);
				$isSKUWidth = (bool)$wpdb->get_var("SELECT attr_sku_width FROM " . CEDI_ATTR_TABLE . " WHERE attr_id=" . $attrId);
				$isSKUHeight = (bool)$wpdb->get_var("SELECT attr_sku_height FROM " . CEDI_ATTR_TABLE . " WHERE attr_id=" . $attrId);
				$isSKULength = (bool)$wpdb->get_var("SELECT attr_sku_length FROM " . CEDI_ATTR_TABLE . " WHERE attr_id=" . $attrId);
				$isSKUWeight = (bool)$wpdb->get_var("SELECT attr_sku_weight FROM " . CEDI_ATTR_TABLE . " WHERE attr_id=" . $attrId);
				$isElegibleForPerSkuAttr = $isPerSKU;

				// check if we already have this attribute
				$attribute = $this->getProductAttributeBySlug($product, $wc_attribute->slug);


				if (!$attribute) {
					$attribute = new WC_Product_Attribute();
					$attribute->set_id($wc_attribute->id);
					$attribute->set_name($wc_attribute->slug);
					$attribute->set_position(sizeof($existingAttributes) + 1);
					$attribute->set_visible(true);
				}
				$options = [];


				if (($attr_type == "select") || ($attr_type == "multiselect")) {
					$optArray = explode(",", $value);
					foreach ($optArray as $opt) {
						$optId = $this->addProductAttributeOption($product, $wc_attribute, $attr_type, $opt, 1);
						if ($optId) $options[] = $optId;
					}
				} else {
					if ($attr_table_type) {
						// store table html & set value to it's id
						$wpdb->query($wpdb->prepare("INSERT INTO " . CEDI_ATTR_HTML_TABLE . " (attr_id,product_cedi_id,html) VALUES (%d,%d,%s) ON DUPLICATE KEY UPDATE html=VALUES(html)", $attrId, $productData['PARENT_ID'], addslashes($value)));
						$value = $wpdb->insert_id;
					}
					if ($attr_type == "textarea") {
						$attribute->set_id(0); // mark attribute as taxonomy to store value >200 chars
						$options = wc_get_text_attributes($value);
					} else {
						$optId = $this->addProductAttributeOption($product, $wc_attribute, $attr_type, $value, 1);
						if ($optId) $options[] = $optId;
					}
				}
				// apply options
				$attribute->set_options(array_unique($options));

				// sku variations
				if ($attr_type == "boolean") $value = $value == 1 ? __("Yes", 'centedi-cataddon') : __("No", 'centedi-cataddon');
				$term = get_term_by('name', $value, $wc_attribute->slug);
				// add non-dimensional attributes only
				if ($isElegibleForPerSkuAttr) {
					$attribute->set_variation(true);
					if ($term) {
						$variationAttributes[$wc_attribute->slug] = $term->slug;
					}
				}
				// dims for variations
				if ($isSKUWidth) $variationDimAttributes['width'] = floatval($value);
				if ($isSKUHeight) $variationDimAttributes['height'] = floatval($value);
				if ($isSKULength) $variationDimAttributes['length'] = floatval($value);
				if ($isSKUWeight) $variationWeightAttributes['weight'] = floatval($value);

				$existingAttributes[] = $attribute;
			}
		}
		$productSaveAttrResult = $product->set_attributes($existingAttributes);

		if (is_wp_error($productSaveAttrResult)) {
			$wpdb->query('ROLLBACK');
			return $productSaveAttrResult->get_error_message();
		}
		$productSaveResult = $product->save();

		if (is_wp_error($productSaveResult)) {
			$wpdb->query('ROLLBACK');
			return $productSaveResult->get_error_message();
		}
		// brand
		if ($this->addProductBrand($product, $productData) === false) {
			$wpdb->query('ROLLBACK');
			return __("Error creating brand", 'centedi-cataddon');
		}
		// VARIATIONS
		if ($productData['SIMPLE'] == 0) {
			$existingVariationsSkus = [];
			$existingVariations = $product->get_children();
			foreach ($existingVariations as $existingVariationId) {
				$existingVariation = new WC_Product_Variation($existingVariationId);
				$existingVariationsSkus[] = $existingVariation->get_sku();
			}

			if (count($variationAttributes) > 0) {
				// check if we already have this variation
				if (array_search($productData['SKU'], $existingVariationsSkus) === false) {
					// insert it if not
					// NOTICE: if we are in update mode, update only existing skus, don't import everything! - test & update later
					//if($isUpdate==false){
					$variation = new WC_Product_Variation();
					$variation->set_parent_id($product->get_id());
					$variation->set_attributes($variationAttributes);
					$variation->set_sku($productData['SKU']);

					$variation->update_meta_data('cedi_sku_bc13', $productData['CODE']);
					$variation->save_meta_data();

					//$variation->set_regular_price();
					$variation->set_manage_stock(true);
					$variation->set_stock_quantity(0); // empty stock initially
					$variation->set_status('publish');
					//}
				} else {
					//existing variation, just update bindings
					$variation_id = $this->getProductVariationIdBySKU($product, $productData['SKU']);

					if ($variation_id) {
						$variation = new WC_Product_Variation($variation_id);
						$variation->set_attributes($variationAttributes);

						$variation->update_meta_data('cedi_sku_bc13', $productData['CODE']);
						$variation->save_meta_data();
					}
				}
				// image
				if (is_array($productData['IMAGE_VERSIONS']) && (count($productData['IMAGE_VERSIONS']) > 0)) {
					$imgPath = array_values($productData['IMAGE_VERSIONS'])[0];
					$imgId = $this->storeImage($this->commonUtils->getPortalUrl() . $imgPath);
					if (is_wp_error($imgId)) {
						$wpdb->query('ROLLBACK');
						return __("Error saving variation images", 'centedi-cataddon');
					}
					$variation->set_image_id($imgId);
				} else {
					$variation->set_image_id("");
				}
				// dims & weight
				if (array_key_exists('width', $variationDimAttributes)) $variation->set_width($variationDimAttributes['width']);
				if (array_key_exists('height', $variationDimAttributes)) $variation->set_height($variationDimAttributes['height']);
				if (array_key_exists('length', $variationDimAttributes)) $variation->set_length($variationDimAttributes['length']);
				if (array_key_exists('weight', $variationWeightAttributes)) $variation->set_weight($variationWeightAttributes['weight']);

				$variationSaveResult = $variation->save();
				if (is_wp_error($variationSaveResult)) {
					$wpdb->query('ROLLBACK');
					return $variationSaveResult->get_error_message();
				}
				$product->sync($variation->get_id());
				// if show variations as single products enabled
				if (get_option('cedi_admin_settings_tab_misc_show_variations_enable') == 'yes') {
					$variation_id = $variation->get_id();
					$parent_id = $product->get_id();
					$taxonomies = apply_filters('woosv_init_taxonomies', array(
						'product_cat',
						'product_tag'
					));
					foreach ($taxonomies as $taxonomy) {
						$terms = (array) wp_get_post_terms($parent_id, $taxonomy, array("fields" => "ids"));
						wp_set_post_terms($variation_id, $terms, $taxonomy);
					}
					$variation->set_menu_order($product->get_menu_order());
					$variation->save();

					$attributes = $variation->get_variation_attributes();
					if (!empty($attributes)) {
						foreach ($attributes as $key => $term) {
							$attr_tax = str_replace('attribute_', '', $key);
							wp_set_post_terms($variation_id, $term, $attr_tax);
						}
					}

					$parent_attributes = $product->get_attributes();
					if (!empty($parent_attributes)) {
						foreach ($parent_attributes as $parent_attribute) {
							if ($parent_attribute->get_variation() == true) {
								continue;
							}

							$attr_tax = $parent_attribute->get_taxonomy();
							$terms    = (array) $parent_attribute->get_terms();

							if (!empty($terms)) {
								$tmp = array();

								foreach ($terms as $term) {
									$tmp[] = $term->term_id;
								}
								wp_set_post_terms($variation_id, $tmp, $attr_tax);
							}
						}
					}

					$brands = wp_get_object_terms($product->get_id(), 'cedi-brand');
					if (!empty($brands))
						wp_set_object_terms($variation_id, $brands[0]->name, 'cedi-brand');
				}
			}
		} else {
			if (isset($productData['CODE'])) {
				$product->update_meta_data('cedi_sku_bc13', $productData['CODE']);
				$product->save_meta_data();
			}
		}

		$wpdb->query($wpdb->prepare("REPLACE INTO " . CEDI_PROD_TABLE . " (product_cedi_id,product_woo_id,product_model) VALUES (%d,%d,%s)", $productData['PARENT_ID'], $product->get_id(), $productData['MODEL']));

		if ($wpdb->last_error) {
			$wpdb->query('ROLLBACK');
			return __("Error writing product data to database", 'centedi-cataddon');
		}

		$this->setProductIsActual($product->get_id());

		$resultId = $productSaveResult;

		$wpdb->query('COMMIT');
		return __("Product created", 'centedi-cataddon');
	}
	// UTILS
	private function addCategoryCID($catId, $cid)
	{
		update_term_meta($catId, 'cedi_category_id', $cid);
	}
	private function getCategoryByCID($cid)
	{
		$categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
		foreach ($categories as &$cat) {
			if (get_term_meta($cat->term_id, 'cedi_category_id', true) == $cid) return $cat->term_id;
		}
		return false;
	}
	private function updateCategoryParentByChildCID($cid, $cidParent)
	{
		global $wpdb;
		$wpdb->query($wpdb->prepare(
			"INSERT INTO " . CEDI_GROUPS_TABLE .
				" (child_group_cedi_id,parent_group_cedi_id) VALUES (%d,%d)" .
				" ON DUPLICATE KEY UPDATE relation_id=LAST_INSERT_ID(relation_id),child_group_cedi_id=child_group_cedi_id,parent_group_cedi_id=parent_group_cedi_id",
			$cid,
			$cidParent
		));
		if ($wpdb->last_error) {
			return false;
		}
		// we've selected tree structure like on CentEDI portal
		$isTreeMode = (get_option('cedi_admin_settings_tab_filters_cb_use_centedi_tree') == 'yes');
		if ($isTreeMode) {
			$parentCatId = $this->getCategoryByCID($cidParent);
			// if parent cat exists
			if ($parentCatId) {
				// set category parent
				$catId = $this->getCategoryByCID($cid);
				wp_insert_category(
					array(
						'cat_ID'        => $catId,
						'category_parent' => $parentCatId,
					)
				);
			}
		}
		return $wpdb->insert_id;
	}
	private function getCategoryParentCIDByChildCID($cid)
	{
		global $wpdb;
		return $wpdb->get_var("SELECT parent_group_cedi_id FROM " . CEDI_GROUPS_TABLE . " WHERE child_group_cedi_id=" . $cid);
	}
	public function getAllCategories($includeNonCedi = true, $includeEmpty = false)
	{
		$resArray = [];

		global $wp_query;

		$cediQuery = $wp_query;
		$cediQuery->set('posts_per_page', -1);
		$cediQuery->set('paged', 1);
		$cediQuery->set('orderby', 'post_modified_gmt');
		$cediQuery->set('order', 'DESC');
		$the_query = new WP_Query($cediQuery->query_vars);

		$foundCats = [];
		//$ids = wp_list_pluck( $the_query->posts, "ID" );

		global $wpdb;
		foreach ($the_query->posts as $post) {
			$foundCatList = wp_get_post_terms($post->ID, 'product_cat');
			foreach ($foundCatList as $foundCat) {
				$foundCats[] = $foundCat->name;
				$resArray[$foundCat->term_id]['name'] = $foundCat->name;
				$resArray[$foundCat->term_id]['slug'] = $foundCat->slug;
				$resArray[$foundCat->term_id]['url'] = get_term_link($foundCat->term_id, 'product_cat');
				$resArray[$foundCat->term_id]['modified'] = $post->post_modified_gmt;
			}
		}
		$foundCatCounts = array_count_values($foundCats);

		foreach ($resArray as $catId => $catData) {
			foreach ($foundCatCounts as $foundCatName => $foundCatCount) {
				if ($catData['name'] == $foundCatName) $resArray[$catId]['count'] = $foundCatCount;
			}
		}
		return $resArray;
	}
	public function isBrandInCategories($brandSlug, $catSlugArr)
	{
		$taxes = ['product'];
		$meta_query = [];
		if (get_option('cedi_admin_settings_tab_misc_show_variations_enable') == 'yes') {
			$taxes = [
				'product', 'product_variation'
			];
			$meta_query[] = array(
				'key' => '_price',
				'value' => '',
				'compare' => '!='
			);
			if (get_option('cedi_admin_settings_tab_misc_hide_variations_parent_enable') == 'yes') {
				$taxes = ['product_variation'];
			}
		}

		$args = array(
			'posts_per_page' => -1,
			'post_type'   => $taxes,
			'post_status' => 'publish',
			'fields'      => 'ids',
			'product_cat' => implode(',', $catSlugArr),
			'meta_query' => $meta_query
		);
		set_query_var('cedi_brand_counter', 1);
		$query = new WP_Query($args);
		foreach ($query->posts as $product) {
			if (has_term($brandSlug, CEDI_BRAND_TAXONOMY_NAME, $product)) return true;
		}

		return false;
	}
	public function getBrandsCategories($brandSlugArr)
	{
		$cats = [];
		// for all published products
		$args = array(
			'status'    => 'publish',
			'limit'     => -1
		);
		foreach (wc_get_products($args) as $product) {
			$prodCats = get_the_terms($product->get_id(), 'product_cat');
			if ($prodCats) {
				foreach ($prodCats as $cat) {
					foreach ($brandSlugArr as $brandSlug) {
						if (has_term($brandSlug, CEDI_BRAND_TAXONOMY_NAME, $product->get_id())) $cats[] = $cat->slug;
					}
				}
			}
		}
		return array_unique($cats);
	}
	public function getFrontendBrands($isForSlider = false)
	{
		global $wp_query;
		$foundBrandCounts = [];

		if (!$isForSlider) {
			$brands = get_terms(
				array(
					'taxonomy' => CEDI_BRAND_TAXONOMY_NAME,
					'hide_empty' => false,
				)
			);

			foreach ($brands as $brand) {
				$foundBrandCounts[$brand->name] = $brand->count;
			}
		}
		$brands = [];
		$params = array(
			'taxonomy' => CEDI_BRAND_TAXONOMY_NAME,
			'hide_empty' => true,
		);
		if (!$isForSlider) {
			$params['orderby'] = 'name';
			$params['order'] = 'asc';
		}
		$brandTerms = get_terms($params);
		if (!is_wp_error($brandTerms) && !empty($brandTerms)) {
			foreach ($brandTerms as $brand) {
				$term = get_term_by('name', $brand->name, CEDI_BRAND_TAXONOMY_NAME);
				if ($term) {
					if (get_term_meta($term->term_id, CEDI_BRAND_VISIBILITY_FIELD_NAME, true) == 1) {
						$imgId = get_term_meta($term->term_id, CEDI_BRAND_IMG_FIELD_NAME, true);
						$brands[$brand->term_id] = [];
						$brands[$brand->term_id]['name'] = $brand->name;
						$img = wp_get_attachment_image_url($imgId, 'full');
						$img = ($img) ? $img : wc_placeholder_img_src(array('140', '140'));
						$brands[$brand->term_id]['logo'] = $img;
						$brands[$brand->term_id]['slug'] = $brand->slug;
						$brands[$brand->term_id]['url'] = get_term_link($term, CEDI_BRAND_TAXONOMY_NAME);
						$brands[$brand->term_id]['order'] = get_term_meta($brand->term_id, CEDI_BRAND_ORDER_FIELD_NAME, true);
						if (isset($foundBrandCounts[$brand->name])) $brands[$brand->term_id]['count'] = $foundBrandCounts[$brand->name];
					}
				}
			}
			if ($isForSlider) usort($brands, array($this, "sortByOrder"));
		}
		return $brands;
	}
	public function getAllCatBrands($catSlugArr)
	{
		global $wp_query;
		$cediQuery = $wp_query;
		$cediQuery->set('nopaging', 1);
		$cediQuery->set('fields', 'ID');

		if (isset($cediQuery->query_vars[CEDI_BRAND_TAXONOMY_NAME]) && !is_array($cediQuery->query_vars[CEDI_BRAND_TAXONOMY_NAME])) {
			$taxTermArr = [];
			$terms = explode(",", $cediQuery->query_vars[CEDI_BRAND_TAXONOMY_NAME]);
			foreach ($terms as $term) {
				$taxTermArr[] = [
					'taxonomy' => CEDI_BRAND_TAXONOMY_NAME,
					'terms' => $term,
					'field' => 'slug'
				];
			}
			$cediQuery->set('tax_query', [
				'relation' => 'AND',
				$taxTermArr
			]);
		}

		$the_query = new WP_Query($cediQuery->query_vars);

		$ids = wp_list_pluck($the_query->posts, "ID");

		$foundBrands = [];

		foreach ($ids as $id) {
			$foundBrand = $this->getProductBrandByWcId($id);
			if ($foundBrand) $foundBrands[] = $foundBrand;
		}

		$foundBrandCounts = array_count_values($foundBrands);

		$brands = [];
		$brandTerms = get_terms(['taxonomy' => CEDI_BRAND_TAXONOMY_NAME, 'hide_empty' => true]);
		if (!is_wp_error($brandTerms) && !empty($brandTerms)) {
			foreach ($brandTerms as $brand) {
				if (in_array($brand->name, $foundBrands)) {
					$term = get_term_by('name', $brand->name, CEDI_BRAND_TAXONOMY_NAME);
					if ($term) {
						if ($this->isBrandInCategories($brand->slug, $catSlugArr) || empty($catSlugArr)) {
							$imgId = get_term_meta($term->term_id, CEDI_BRAND_IMG_FIELD_NAME, true);
							$brands[$brand->term_id] = [];
							$brands[$brand->term_id]['name'] = $brand->name;
							$img = wp_get_attachment_image_url($imgId, 'full');
							$img = ($img) ? $img : wc_placeholder_img_src(array('140', '140'));
							$brands[$brand->term_id]['logo'] = $img;
							$brands[$brand->term_id]['slug'] = $brand->slug;
							$brands[$brand->term_id]['count'] = $foundBrandCounts[$brand->name];
							$brands[$brand->term_id]['url'] = get_term_link($brand->term_id, CEDI_BRAND_TAXONOMY_NAME);
						}
					}
				}
			}
		}
		return $brands;
	}

	public function getProductBrandByWcId($productWcId)
	{
		$brands = wp_get_object_terms($productWcId, CEDI_BRAND_TAXONOMY_NAME, array('fields' => 'names'));
		if (!is_wp_error($brands) && !empty($brands)) return $brands[0];
		return '';
	}
	private function sortByOrder($a, $b)
	{
		$a = $a['order'];
		$b = $b['order'];
		if ($a == $b) return 0;
		return ($a < $b) ? -1 : 1;
	}

	public function getCategoryAttributes($catSlug)
	{
		// for all published products in this groups
		$attrs = [];
		$args = array(
			'category'  => $catSlug,
			'status'    => 'publish',
			'limit'     => -1
		);
		foreach (wc_get_products($args) as $product) {
			foreach ($product->get_attributes() as $attrTax => $attr) {
				$attrId = wc_attribute_taxonomy_id_by_name($attrTax);
				$attrs[$attrId] = wc_attribute_label($attrTax);
			}
		}
		return $attrs;
	}
	public function getAllCategoriesAttributes($catSlugArr = null, $brandSlugArr = null)
	{
		$attrs = [];

		global $wp_query;

		$cediQuery = $wp_query;
		$cediQuery->set('nopaging', 1);
		$cediQuery->set('fields', 'ID');
		$the_query = new WP_Query($cediQuery->query_vars);
		$ids = wp_list_pluck($the_query->posts, "ID");

		//print($the_query->request);

		foreach ($ids as $id) {
			$originalProduct = wc_get_product($id);
			$product = wc_get_product($id);
			if (is_a($product, 'WC_Product_Variation')) {
				$product = wc_get_product($product->get_parent_id());
			}
			if (!$product) continue;

			foreach ($product->get_attributes() as $taxonomy => $attribute) {
				//if (!is_object($attribute)) $attribute = wc_get_attribute(wc_attribute_taxonomy_id_by_name($taxonomy));
				if (is_object($attribute)) {
					$attrType = $this->getAttrTypeByWCId(wc_attribute_taxonomy_id_by_name($taxonomy), true);
					$allowedTypes = ['boolean', 'text', 'select', 'multiselect', '']; // empty val is for non-centedi attributes
					if (in_array($attrType, $allowedTypes)) {
						$attrs[$taxonomy]['wcid'] = wc_attribute_taxonomy_id_by_name($taxonomy);
						$attrs[$taxonomy]['name'] = wc_attribute_label($taxonomy);
						$attrs[$taxonomy]['type'] = $attrType == 'boolean' ? 'radio' : 'checkbox';

						$terms = [];
						$attrTerms = $attribute->get_terms();
						$originalTerms = get_the_terms($originalProduct->get_id(), $taxonomy);

						if (!array_key_exists('terms', $attrs[$taxonomy])) $attrs[$taxonomy]['terms'] = [];

						if (!empty($attrTerms)) {
							foreach ($attrTerms as $term) {
								if ($originalTerms[0]->slug != $term->slug) continue;
								$terms[$term->slug] = $term->name;
								if (!isset($attrs[$taxonomy][$term->slug]['count'])) $attrs[$taxonomy][$term->slug]['count'] = 1;
								else $attrs[$taxonomy][$term->slug]['count']++;
							}
							if (count($attrs[$taxonomy]['terms']) > 0) {
								$attrs[$taxonomy]['terms'] = $attrs[$taxonomy]['terms'] + $terms;
							} else $attrs[$taxonomy]['terms'] = $terms;

							$attrs[$taxonomy]['terms'] = array_unique($attrs[$taxonomy]['terms']);
						}
					}
				}
			}
		}
		return $attrs;
	}
	private function getAttrOptionIdByName($attrTaxonomy, $optionName)
	{
		if (term_exists($optionName, $attrTaxonomy)) return get_term_by('name', $optionName, $attrTaxonomy)->term_id;
		return false;
	}
	private function addAttrOptionByName($attrTaxonomy, $optionName)
	{
		if (trim($optionName) == "") return false;
		if (!term_exists($optionName, $attrTaxonomy)) {
			$res = wp_insert_term($optionName, $attrTaxonomy);
			if (is_wp_error($res)) {
				throw new Exception($res->get_error_message());
				return false;
			} else return $res['term_id'];
		}
		$term = get_term_by('name', $optionName, $attrTaxonomy);
		return $term !== false ? $term->term_id : $term;
	}
	// internal use functions
	public function getAttrWCIdById($attrId)
	{
		global $wpdb;
		return $wpdb->get_var("SELECT attr_woo_id FROM " . CEDI_ATTR_TABLE . " WHERE attr_id=" . $attrId);
	}
	public function getAttrWCIdByWCName($attrWCName)
	{
		global $wpdb;
		return $wpdb->get_var("SELECT attribute_id FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_name='" . str_replace("pa_", "", $attrWCName) . "'");
	}
	public function getAttrNameByWCId($attrWCId)
	{
		global $wpdb;
		return $wpdb->get_var("SELECT attr_name FROM " . CEDI_ATTR_TABLE . " WHERE attr_woo_id=" . $attrWCId);
	}
	public function getAttrIdByWCId($attrWCId)
	{
		global $wpdb;
		return $wpdb->get_var("SELECT attr_id FROM " . CEDI_ATTR_TABLE . " WHERE attr_woo_id=" . $attrWCId);
	}
	public function getCentAttrIdByWCId($attrWCId)
	{
		global $wpdb;
		return $wpdb->get_var("SELECT attr_cid FROM " . CEDI_ATTR_TABLE . " WHERE attr_woo_id=" . $attrWCId);
	}
	public function getCentAttrIdById($attrId)
	{
		global $wpdb;
		return $wpdb->get_var("SELECT attr_cid FROM " . CEDI_ATTR_TABLE . " WHERE attr_id=" . $attrId);
	}
	public function getAttrNameById($attrId)
	{
		global $wpdb;
		return $wpdb->get_var("SELECT attr_name FROM " . CEDI_ATTR_TABLE . " WHERE attr_id=" . $attrId);
	}
	public function getAttrSetDataByAttrId($attrId, $isWCId = true)
	{
		global $wpdb;
		$field = $isWCId == true ? 'attr_woo_id' : 'attr_id';
		return $wpdb->get_row("SELECT set_legend,set_order FROM " . CEDI_ATTR_SET_TABLE . " WHERE set_id=(SELECT set_id FROM " . CEDI_ATTR_TABLE . " WHERE " . $field . "=" . $attrId . ") ORDER BY set_order");
	}
	public function getAttrSetIdAttrWcId($attrWCId)
	{
		global $wpdb;
		return $wpdb->get_var("SELECT set_id FROM " . CEDI_ATTR_TABLE . " WHERE attr_woo_id=" . $attrWCId);
	}
	public function getAttrTooltipByWCId($attrWCId)
	{
		global $wpdb;
		return $wpdb->get_var("SELECT attr_tooltip FROM " . CEDI_ATTR_TABLE . " WHERE attr_woo_id=" . $attrWCId);
	}
	public function getAttrTypeById($attrId)
	{
		global $wpdb;
		return $wpdb->get_var("SELECT attr_type FROM " . CEDI_ATTR_TABLE . " WHERE attr_id=" . $attrId);
	}
	public function getAttrTableTypeById($attrId)
	{
		global $wpdb;
		return $wpdb->get_var("SELECT attr_table_type FROM " . CEDI_ATTR_TABLE . " WHERE attr_id=" . $attrId);
	}
	// main object prop, parent for propset
	public function checkAttributeIsObjectType($cediAttrId)
	{
		global $wpdb;
		return $wpdb->get_var("SELECT COUNT(*) as ct FROM " . CEDI_ATTR_TABLE . " WHERE attr_obj_prop_main_id=" . $cediAttrId) > 0 ? true : false;
	}
	public function checkObjectAttributeDisplayAsSet($cediAttrId)
	{
		global $wpdb;
		return $wpdb->get_var("SELECT attr_obj_as_set FROM " . CEDI_ATTR_TABLE . " WHERE attr_cid=" . $cediAttrId) > 0 ? true : false;
	}
	// object data prop, inside propset
	public function checkAttributeIsObjectDataType($cediAttrId)
	{
		global $wpdb;
		return $wpdb->get_var("SELECT attr_obj_prop_main_id FROM " . CEDI_ATTR_TABLE . " WHERE attr_cid=" . $cediAttrId) > 0 ? true : false;
	}
	public function getAttributeObjectParentIdByWCId($cediObjDataAttrId)
	{
		global $wpdb;
		return $wpdb->get_var("SELECT attr_id FROM " . CEDI_ATTR_TABLE . " WHERE attr_cid=(SELECT attr_obj_prop_main_id FROM " . CEDI_ATTR_TABLE . " WHERE attr_woo_id=" . $cediObjDataAttrId . ")");
	}
	public function getAttrTypeByWCId($attrWCId, $forcePlainType = false)
	{
		global $wpdb;
		if (!$forcePlainType) {
			if ($this->checkAttributeIsObjectType($this->getCentAttrIdByWCId($attrWCId))) return 'object';
			if ($this->checkAttributeIsObjectDataType($this->getCentAttrIdByWCId($attrWCId))) return 'object_data';
		}
		return $wpdb->get_var("SELECT attr_type FROM " . CEDI_ATTR_TABLE . " WHERE attr_woo_id=" . $attrWCId);
	}
	public function getAttrTypeByCentId($cediAttrId, $forcePlainType = false)
	{
		global $wpdb;
		if (!$forcePlainType) {
			if ($this->checkAttributeIsObjectType($cediAttrId)) return 'object';
			if ($this->checkAttributeIsObjectDataType($cediAttrId)) return 'object_data';
		}
		return $wpdb->get_var("SELECT attr_type FROM " . CEDI_ATTR_TABLE . " WHERE attr_cid=" . $cediAttrId);
	}
	public function getAttrTableTypeByWCId($attrWCId)
	{
		global $wpdb;
		return $wpdb->get_var("SELECT attr_table_type FROM " . CEDI_ATTR_TABLE . " WHERE attr_woo_id=" . $attrWCId);
	}
	public function isAttrAllowedForFiltersByWCId($attrWCId, $showCediOnly = false, $showFilterableOnly = false, $categories = [])
	{
		//global $wpdb;
		// if attribute is not CEDI & $showCediOnly=true - hide it
		if (!$this->getCentAttrIdByWCId($attrWCId)) return !$showCediOnly;
		// if attribute is CEDI & is not filterable & $showFilterableOnly=true - hide it
		if (!$this->isAttrFilterableByWCId($attrWCId, $categories) && $showFilterableOnly) return false;
		// otherwise show it in filters
		return true;
	}
	public function isAttrFilterableByWCId($attrWCId, $categories = [])
	{
		global $wpdb;
		$isManualMode = (get_option('cedi_admin_settings_tab_filters_cb_manual_attribute_filters') == 'yes');
		// automatic filterable management
		if (($this->getCentAttrIdByWCId($attrWCId)) && (!$isManualMode)) return (bool)$wpdb->get_var("SELECT attr_filterable FROM " . CEDI_ATTR_TABLE . " WHERE attr_woo_id=" . $attrWCId);
		// manual mode enabled
		if ($isManualMode) {
			$filterableInCats = 0;
			if (is_array($categories)) {
				foreach ($categories as $catSlug) {
					$groupId = get_term_by('slug', $catSlug, 'product_cat')->term_id;
					if (get_option('cedi_attr_' . $groupId . '_' . $attrWCId, 0) == 1) $filterableInCats++;
				}
				return $filterableInCats == count($categories) ? 1 : 0;
			} else {
				$groupId = $wpdb->get_var("SELECT cat_id FROM " . CEDI_ATTR_SET_TABLE . " WHERE set_id=" . $this->getAttrSetIdAttrWcId($attrWCId));
				return get_option('cedi_attr_' . $groupId . '_' . $attrWCId, 0);
			}
		}
		return 0;
	}
	public function isAttrFilterableByCentId($cediAttrId)
	{
		global $wpdb;
		return (bool)$wpdb->get_var("SELECT attr_filterable FROM " . CEDI_ATTR_TABLE . " WHERE attr_cid=" . $cediAttrId);
	}
	public function getAttrTableHTMLById($htmlId)
	{
		global $wpdb;
		return $wpdb->get_var("SELECT html FROM " . CEDI_ATTR_HTML_TABLE . " WHERE html_id=" . $htmlId);
	}
	public function checkProductIsImported($prodWCId)
	{
		global $wpdb;
		$ct = $wpdb->get_var("SELECT COUNT(*) FROM " . CEDI_PROD_TABLE . " WHERE product_woo_id=" . $prodWCId);
		return $ct > 0 ? true : false;
	}
	public function checkProductIsActual($prodWCId)
	{
		global $wpdb;
		$isActual = $wpdb->get_var("SELECT is_actual FROM " . CEDI_PROD_TABLE . " WHERE product_woo_id=" . $prodWCId);
		return (bool)$isActual;
	}
	public function setProductIsActual($prodWCId, $isActual = true)
	{
		global $wpdb;
		return $wpdb->query("UPDATE " . CEDI_PROD_TABLE . " SET is_actual=" . (int)$isActual . " WHERE product_woo_id=" . $prodWCId);
	}
	public function getWooProductIdByCediId($cediProdId)
	{
		global $wpdb;
		return $wpdb->get_var("SELECT product_woo_id FROM " . CEDI_PROD_TABLE . " WHERE product_cedi_id=" . $cediProdId);
	}
	public function getCediProductIdByWooId($wooProdId)
	{
		global $wpdb;
		return $wpdb->get_var("SELECT product_cedi_id FROM " . CEDI_PROD_TABLE . " WHERE product_woo_id=" . $wooProdId);
	}
	public function getCediProductModelByWooId($wooProdId)
	{
		global $wpdb;
		return $wpdb->get_var("SELECT product_model FROM " . CEDI_PROD_TABLE . " WHERE product_woo_id=" . $wooProdId);
	}
	public function removeProductTotally($prodWCId)
	{
		global $woocommerce;
		if (!is_wp_error(wp_delete_post($prodWCId, true))) {
			global $wpdb;
			$cediId = $this->getCediProductIdByWooId($prodWCId);
			$wpdb->query("DELETE FROM " . CEDI_ATTR_HTML_TABLE . " WHERE product_cedi_id=" . $cediId);
			$wpdb->query("DELETE FROM " . CEDI_PROD_TABLE . " WHERE product_cedi_id=" . $cediId);
			$wpdb->query("DELETE FROM " . CEDI_BRANDS_TABLE . " WHERE product_cedi_id=" . $cediId);
		}
	}
	public function getProductAttributeBySlug($product, $slug)
	{
		$attributes = $product->get_attributes();
		foreach ($attributes as $key => $attribute) {
			if ($attribute->get_name() == $slug) return $attribute;
		}
		return false;
	}
	public function isAttributeEssentialForChoice($product, $slug)
	{
		$attribute = $this->getProductAttributeBySlug($product, $slug);
		global $wpdb;
		return (bool)$wpdb->get_var("SELECT attr_ess_choice FROM " . CEDI_ATTR_TABLE . " WHERE attr_woo_id=" . $attribute['ID']);
	}
	public function getProductVariationIdBySKU($product, $sku)
	{
		$existingVariations = $product->get_children();
		foreach ($existingVariations as $existingVariationId) {
			$existingVariation = new WC_Product_Variation($existingVariationId);
			if ($existingVariation->get_sku() == $sku) return $existingVariationId;
		}
		return false;
	}
	private function storeImage($url)
	{
		$file = wp_remote_get($url);
		if (!is_wp_error($file)) {
			$guid = md5(basename($url));
			$filename =  $guid . "." . get_option(CEDI_IMG_CFG_FIELD_NAME);
			$uploadedFile = wp_upload_bits($filename, null, wp_remote_retrieve_body($file));

			if (!$uploadedFile['error']) {
				$hash = hash_file('md5', $uploadedFile['file']);

				$imgId = $this->getExistingImageId($hash);

				if ($imgId) {
					//echo $imgId . " EXISTS" . "\n";
					return $imgId;
				}

				$filetype = wp_check_filetype($filename, null);
				$attachment = array(
					'post_mime_type' => $filetype['type'],
					'post_title' => $filename
				);
				$attachmentData = wp_insert_attachment($attachment, $uploadedFile['file']);
				if (!is_wp_error($attachmentData)) {
					require_once(ABSPATH . 'wp-admin/includes/image.php');
					$meta = wp_generate_attachment_metadata($attachmentData, $uploadedFile['file']);
					wp_update_attachment_metadata($attachmentData, $meta);
					add_post_meta($attachmentData, 'image_guid', $hash, true);
					//echo $attachmentData . " INSERTED" . "\n";
					return $attachmentData;
				} else {
					return new WP_Error('img_upload', __('Error uploading image: ', 'centedi-cataddon') . $attachmentData->get_error_message());
				}
			}
			return new WP_Error('img_upload', __('Error uploading image: ', 'centedi-cataddon') . $uploadedFile['error']);
		}
		return new WP_Error('img_upload', __('Error uploading image: ', 'centedi-cataddon') . $file->get_error_message());
	}
	private function getExistingImageId($guid)
	{
		$args = array(
			'post_type'  => 'attachment',
			'post_status' => array('inherit'),
			'meta_key' => 'image_guid',
			'meta_query' => array(
				array(
					'key' => 'image_guid',
					'value' => $guid,
					'compare' => '=',
				)
			)
		);
		$query = new WP_Query($args);
		$ids = wp_list_pluck($query->posts, "ID");
		if (count($ids) > 0) return $ids[0];
		return false;
	}
	private function storeAttachment($cediProdId, $prodAttachmentData)
	{
		$file = wp_remote_get(CEDI_PORTAL_URL . $prodAttachmentData[0]);
		if (!is_wp_error($file)) {
			$filename = md5($cediProdId . $prodAttachmentData[1]) . "." . pathinfo($prodAttachmentData[1], PATHINFO_EXTENSION);
			$uploadedFile = wp_upload_bits($filename, null, wp_remote_retrieve_body($file));
			if (!$uploadedFile['error']) {
				return $uploadedFile['url'];
			}
		}
		return '';
	}
	public function getProductAttachmentsByWCId($prodWCId)
	{
		global $wpdb;
		$attachments = [];
		$cediProdId = $this->getCediProductIdByWooId($prodWCId);
		if ($cediProdId) {
			$prodAttachmentsData = $wpdb->get_results("SELECT attachment_data FROM " . CEDI_ATTR_ATTACHMENTS_TABLE . " WHERE product_cedi_id=" . $cediProdId, OBJECT);
			foreach ($prodAttachmentsData as $prodAttachmentData) {
				$attachments[] = explode("||", $prodAttachmentData->attachment_data);
			}
			return $attachments;
		}
	}
	private function addProductBrand($wcProduct, $cediProdData)
	{
		$brandData = explode("||", $cediProdData['BRAND']);
		$brandName = $brandData[0];

		$imgArr = array_values($cediProdData['BRANDIMAGE']);
		$brandImg = isset($imgArr[0]) ? $imgArr[0] : '';

		wp_set_object_terms($wcProduct->get_id(), $brandName, CEDI_BRAND_TAXONOMY_NAME);
		global $wpdb;
		$prepQuery = $wpdb->prepare(
			"INSERT INTO " . CEDI_BRANDS_TABLE . " (product_cedi_id,brand_name,brand_logo) 
							VALUES (%d,%s,%s) 
							ON DUPLICATE KEY UPDATE brand_id=LAST_INSERT_ID(brand_id),brand_name=VALUES(brand_name),brand_logo=VALUES(brand_logo)",
			$cediProdData['PARENT_ID'],
			$brandName,
			$brandImg
		);
		$results = $wpdb->query($prepQuery);
		$resId = $wpdb->insert_id;
		if (!is_wp_error($results)) {
			$term = get_term_by('name', $brandName, CEDI_BRAND_TAXONOMY_NAME);
			if ($term) {
				if ($brandImg != '') {
					$imgId = $this->storeImage($this->commonUtils->getPortalUrl() . $brandImg);
					if (is_wp_error($imgId)) return false;
					wp_set_object_terms($wcProduct->get_id(), $imgId, CEDI_BRAND_IMG_FIELD_NAME);
					delete_term_meta($term->term_id, CEDI_BRAND_IMG_FIELD_NAME);
					update_term_meta($term->term_id, CEDI_BRAND_IMG_FIELD_NAME, $imgId);
				}
				if (!get_term_meta($term->term_id, CEDI_BRAND_ORDER_FIELD_NAME, true)) update_term_meta($term->term_id, CEDI_BRAND_VISIBILITY_FIELD_NAME, 1);
				if (!get_term_meta($term->term_id, CEDI_BRAND_ORDER_FIELD_NAME, true)) {
					update_term_meta($term->term_id, CEDI_BRAND_ORDER_FIELD_NAME, $this->getMaxBrandOrder());
				}
			}
			return $resId;
		}
		return false;
	}
	private function getMaxBrandOrder()
	{
		$brands = get_terms([
			'taxonomy' => CEDI_BRAND_TAXONOMY_NAME,
			'hide_empty' => false
		]);
		$maxBrandOrder = 0;
		foreach ($brands as $brand) {
			$maxBrandOrder++;
		}
		return $maxBrandOrder;
	}
	public function getProductAttributeValues($product, $attribute)
	{
		$values = [];
		$terms = wc_get_product_terms($product->get_id(), $attribute->get_name());
		foreach ($terms as $term) {
			$values[] = $term->name;
		}
		$value = implode(', ', $values);
		return $value != '' ? $value : $product->get_attribute($attribute->get_name());
	}
	public function getVariationByBarcodeGTIN($bc13GTIN)
	{
		global $wpdb;
		$products = $wpdb->get_results("SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'cedi_sku_bc13' AND  meta_value = " . $bc13GTIN . " LIMIT 1", ARRAY_A);
		return count($products) > 0 ? $products[0]['post_id'] : false;
	}
}
