<?php

/**
 * @package CentEDI
 */
/*
Plugin Name: CentEDI Catalog Extension
Plugin URI: https://centedi.com/
Description: CentEDI Catalog Extension creates and fills in the products and images for you. Simply find the product by barcode or any part of product and press import and the product will be created & filled in on your WooCommerce store.
Version: 0.0.9
Author: CentEDI
Author URI: https://centedi.com/
License: GPLv2 or later
Text Domain: centedi-cataddon
*/

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

Copyright 2009-2022 CentEDI s.r.o.
*/
if (!defined('ABSPATH')) {
	exit;
}
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
	// DB creation & upgrades
	global $cedi_db_version;
	$cedi_db_version = '0.0.9'; // change this according to plugin version

	function cedi_install()
	{
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		global $wpdb;
		global $cedi_db_version;

		$sql = "CREATE TABLE " . CEDI_PROD_TABLE . " (
			product_id int(11) NOT NULL AUTO_INCREMENT,
			product_cedi_id int(11) NOT NULL,
			product_woo_id int(11) NOT NULL,
			product_model varchar(256) NOT NULL,
			last_update timestamp NOT NULL DEFAULT current_timestamp ON UPDATE current_timestamp,
			is_actual  tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
			PRIMARY KEY  (product_id),
			UNIQUE INDEX `uicediproduct` (`product_cedi_id`, `product_woo_id`) USING BTREE
		);";
		dbDelta($sql);

		$sql = "CREATE TABLE " . CEDI_ATTR_SET_TABLE . " (
			set_id int(11) NOT NULL AUTO_INCREMENT,
			set_legend tinytext NOT NULL,
			set_code varchar(32) NOT NULL,
			cat_id int(11) NOT NULL,
			set_order int(11) NOT NULL,
			PRIMARY KEY  (set_id),
			UNIQUE INDEX `uicedigroupset` (`cat_id`, `set_code`) USING BTREE
		);";
		dbDelta($sql);

		$sql = "CREATE TABLE " . CEDI_ATTR_TABLE . " (
			attr_id int(11) NOT NULL AUTO_INCREMENT,
			attr_woo_id int(11) NOT NULL,
			attr_cid int(11) NOT NULL,
			set_id int(11) NOT NULL,
			attr_name tinytext NOT NULL,
			attr_type tinytext NOT NULL,
			attr_code varchar(32) NOT NULL,
			attr_table_code varchar(32) NOT NULL,
			attr_persku tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
			attr_ess_choice tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
			attr_filterable tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
			attr_dimensional tinyint(1) UNSIGNED ZEROFILL DEFAULT 0,
			attr_sku_width  tinyint(1) UNSIGNED ZEROFILL DEFAULT 0,
			attr_sku_height tinyint(1) UNSIGNED ZEROFILL DEFAULT 0,
			attr_sku_length tinyint(1) UNSIGNED ZEROFILL DEFAULT 0,
			attr_sku_weight tinyint(1) UNSIGNED ZEROFILL DEFAULT 0,
			attr_table_type varchar(255) NULL DEFAULT NULL,
			attr_obj_prop_main_id varchar(255) NULL DEFAULT NULL,
			attr_obj_as_set tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
			attr_tooltip text CHARACTER SET utf8 COLLATE utf8_general_ci,
			PRIMARY KEY  (attr_id),
			UNIQUE INDEX `uicedisttrset` (`set_id`, `attr_code`) USING BTREE
		);";
		dbDelta($sql);

		$sql = "CREATE TABLE " . CEDI_ATTR_HTML_TABLE . " (
			html_id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			attr_id int(11) NOT NULL,
			product_cedi_id int(11) UNSIGNED NOT NULL,
			html text CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
			PRIMARY KEY  (html_id) USING BTREE,
			UNIQUE INDEX `cedihtmluniq`(`attr_id`, `product_cedi_id`) USING BTREE,
			CONSTRAINT `cedihtml2attr` FOREIGN KEY (`attr_id`) REFERENCES `" . CEDI_ATTR_TABLE . "` (`attr_id`) ON DELETE CASCADE ON UPDATE CASCADE
		);";
		dbDelta($sql);

		$sql = "CREATE TABLE " . CEDI_ATTR_ATTACHMENTS_TABLE . " (
			attachment_id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			product_cedi_id int(11) UNSIGNED NOT NULL,
			attachment_data text CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
			PRIMARY KEY  (attachment_id) USING BTREE
		);";

		dbDelta($sql);

		$sql = "CREATE TABLE " . CEDI_BRANDS_TABLE . " (
			brand_id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			product_cedi_id int(11) UNSIGNED NOT NULL,
			brand_name varchar(128) NOT NULL,
			brand_logo varchar(32),
			PRIMARY KEY  (brand_id) USING BTREE,
			UNIQUE INDEX `cedibranduniq`( `product_cedi_id`) USING BTREE
		);";
		dbDelta($sql);

		$sql = "CREATE TABLE " . CEDI_ATTR_RELATIONS_TABLE . " (
			relation_id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			attr_woo_id int(11) NOT NULL,
			attr_table_code varchar(32) NOT NULL,
			PRIMARY KEY  (relation_id) USING BTREE,
			UNIQUE INDEX `cedireluniq`( `attr_woo_id`, `attr_table_code`) USING BTREE
		);";
		dbDelta($sql);

		/*$sql = "CREATE TABLE ".CEDI_GROUPS_TABLE." (
			relation_id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			child_group_cedi_id int(11) UNSIGNED NOT NULL,
			parent_group_cedi_id int(11) UNSIGNED NOT NULL,
			PRIMARY KEY  (relation_id) USING BTREE,
			UNIQUE INDEX `cedigreluniq`( `child_group_cedi_id`) USING BTREE
		);";
		dbDelta($sql);
		*/


		add_option('cedi_db_version', $cedi_db_version); // store for updates
	}
	register_activation_hook(__FILE__, 'cedi_install');

	function cedi_uninstall()
	{
		// delete custom options
		//delete_option('centedi_cataddon_data');
		// delete custom tables
		// jusy leave for now
		/*global $wpdb;
		$wpdb->query("DROP TABLE IF EXISTS ".CEDI_PROD_TABLE);
		$wpdb->query("DROP TABLE IF EXISTS ".CEDI_ATTR_SET_TABLE);
		$wpdb->query("DROP TABLE IF EXISTS ".CEDI_ATTR_TABLE);
		//etc
		*/
	}
	register_uninstall_hook(__FILE__, 'cedi_uninstall');

	function cedi_plugin_loaded()
	{
		global $cedi_db_version;
		if (get_site_option('cedi_db_version') != $cedi_db_version) {
			cedi_table_update();
		}
		load_plugin_textdomain('centedi-cataddon', false, dirname(plugin_basename(__FILE__)) . '/languages/');
		if (get_option('autoptimize_js_include_inline') != 'on') {
			//add_filter('autoptimize_html_after_minify','cedi_defer_inline_jquery',10,1);
		}
	}

	function cedi_defer_inline_jquery($in)
	{
		if (preg_match_all('#<script.*>(.*)</script>#Usmi', $in, $matches, PREG_SET_ORDER)) {
			foreach ($matches as $match) {
				if ($match[1] !== '' && (strpos($match[1], 'jQuery') !== false || strpos($match[1], '$') !== false)) {
					// inline js that requires jquery, wrap deferring JS around it to defer it. 
					$new_match = 'var aoDeferInlineJQuery=function(){' . $match[1] . '}; if (document.readyState === "loading") {document.addEventListener("DOMContentLoaded", aoDeferInlineJQuery);} else {aoDeferInlineJQuery();}';
					$in = str_replace($match[1], $new_match, $in);
				} else if ($match[1] === '' && strpos($match[0], 'src=') !== false && strpos($match[0], 'defer') === false) {
					// linked non-aggregated JS, defer it.
					$new_match = str_replace('<script ', '<script defer ', $match[0]);
					$in = str_replace($match[0], $new_match, $in);
				}
			}
		}
		return $in;
	}
	function cedi_table_update()
	{
		return true;
	}
	add_action('plugins_loaded', 'cedi_plugin_loaded');

	// main process
	if (!class_exists('Centedi_Cataddon')) {
		require_once('centedi-cataddon-core.php');
		require_once('centedi-cataddon-widget.php');
		require_once('centedi-cataddon-brands-widget.php');
		require_once('centedi-cataddon-settings.php');
		require_once('centedi-cataddon-seo-utils.php');
		class Centedi_Cataddon
		{
			protected $Core;
			protected $SeoUtils;
			public function __construct()
			{
				$this->Core = new CentediCore();
				$this->SeoUtils = new CentediSeoUtils();
				add_action('wp_enqueue_scripts', array($this, 'frontend_load_resources'));
				add_action('admin_enqueue_scripts', array($this, 'admin_load_resources'));
				add_action('admin_menu', array($this, 'cedi_addAdminMenu'));
				add_action('manage_posts_extra_tablenav', array($this, 'admin_product_list_import_button'), 20);
				// remove product& info on trash. Do not allow restoring on cedi products
				add_action('trashed_post', array($this, 'cedi_on_trash'));

				// Product info & property tab override
				add_action('woocommerce_single_product_summary', array($this, 'override_product_info'), 15);
				add_filter('woocommerce_product_tabs', array($this, 'override_attr_tab'), 98);
				add_filter('woocommerce_product_tabs', array($this, 'add_product_files_tab'), 99);

				// Update action for products
				// currently disabled prior to popup window
				//add_filter( 'post_row_actions', array($this,'cedi_update_link_add'), 10, 2 );
				add_action('wp_ajax_getRegistrationStatus', array($this, 'getRegistrationStatus'));
				add_action('wp_ajax_registerOnPortal', array($this, 'registerOnPortal'));
				add_action('wp_ajax_checkRegistrationData', array($this, 'checkRegistrationData'));
				add_action('wp_ajax_getAuthData', array($this, 'getAuthData'));
				add_action('wp_ajax_saveAuthData', array($this, 'saveAuthData'));
				add_action('wp_ajax_saveProduct', array($this, 'saveProduct'));
				//add_action( 'wp_ajax_markProductsForUpdate', array($this, 'markProductsForUpdate') );

				// new server-side req
				add_action('wp_ajax_importSelectedProduct', array($this, 'importSelectedProduct'));
				add_action('wp_ajax_updateSelectedProduct', array($this, 'updateSelectedProduct'));
				add_action('wp_ajax_getUpdatedProducts', array($this, 'getUpdatedProducts'));
				add_action('wp_ajax_searchProducts', array($this, 'searchProductsOnPortal'));
				add_action('wp_ajax_notFoundProc', array($this, 'notFoundProc'));
				add_action('wp_ajax_getImageConfigs', array($this, 'getImageConfigs'));
				//
				add_action('wp_ajax_getProductsForUpdate', array($this, 'getProductsForUpdate'));
				add_action('wp_ajax_updateProduct', array($this, 'updateProduct'));



				add_action('wp_ajax_sortBrands', array($this, 'sortBrands'));
				add_action('wp_ajax_showHideBrands', array($this, 'showHideBrands'));
				add_action('wp_ajax_uploadFile', array($this, 'uploadFile'));
				add_action('widgets_init', array($this, 'initWidgets'));

				add_action('pre_get_posts', array($this, 'cedi_prePosts'));
				add_action('pre_get_terms', array($this, 'cedi_sortBrands'));

				// SEO etc
				if (get_option('cedi_admin_settings_tab_seo_enable_cedi_structured_data_cb_enabled') == 'yes') {
					add_filter('woocommerce_structured_data_product', array($this, 'cedi_updateMicrodata'), 10, 2);
					add_action('wp_head', array($this, 'cedi_addOrgSchema'), 10);
				}
				if (get_option('cedi_admin_settings_tab_seo_enable_metadescr_cb_enabled') == 'yes')
					add_action('wp_head', array($this, 'cedi_addMetaDescription'), 10);


				add_action('woocommerce_variation_options', array($this, 'cedi_product_identifier_sku_option'), 10, 3);
				add_action('woocommerce_save_product_variation', array($this, 'cedi_save_product_identifier_sku_option'), 10, 2);

				add_action('woocommerce_init', array($this, 'addBrandsTaxonomy'), 10, 0);

				add_action(CEDI_BRAND_TAXONOMY_NAME . '_add_form_fields', array($this, 'cedi_BrandsAddPageOverride'));
				add_action(CEDI_BRAND_TAXONOMY_NAME . '_edit_form_fields', array($this, 'cedi_BrandsEditPageOverride'));
				add_action('edited_' . CEDI_BRAND_TAXONOMY_NAME, array($this, 'cedi_SaveTaxonomy'));
				add_action('created_' . CEDI_BRAND_TAXONOMY_NAME, array($this, 'cedi_SaveTaxonomy'));
				add_filter('manage_edit-' . CEDI_BRAND_TAXONOMY_NAME . '_columns', array($this, 'cedi_TaxonomyColumnOverride'));
				add_filter('manage_' . CEDI_BRAND_TAXONOMY_NAME . '_custom_column', array($this, 'cedi_TaxonomyColumnManageOverride'), 10, 3);



				add_action('woocommerce_before_shop_loop', array($this, 'cedi_addBrandsSlider'), 10);
				add_action('woocommerce_before_shop_loop', array($this, 'cedi_overrideCategoryDisplay'), 15);
				add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'cedi_addActionLinks'));

				add_filter('woocommerce_product_categories_widget_args', array($this, 'cedi_Cat_Filter'));
				add_filter('upload_mimes', array($this, 'add_webp_type'));

				// VARIATIONS
				// possibly activate?
				//add_filter('woocommerce_available_variation', array($this, 'cedi_product_variations_override'), 10, 3);
				// 
				add_filter('woocommerce_get_product_terms', array($this, 'cedi_sortVariationValues'), 10, 4);
				add_filter('woocommerce_dropdown_variation_attribute_options_html', array($this, 'cedi_product_variation_html_override'), 1000, 2);
				add_filter('woocommerce_attribute_label', array($this, 'cedi_attribute_label'), 10, 2);
				add_action('woocommerce_single_product_summary', array($this, 'add_product_hidden_sku_options'), 40);

				add_action('init', array($this, 'cedi_Cfg'));

				add_filter('edit_' . CEDI_BRAND_TAXONOMY_NAME . '_per_page', array($this, 'cedi_screenOptionRowsPerPage'));
				add_filter('manage_edit-' . CEDI_BRAND_TAXONOMY_NAME . '_sortable_columns', array($this, 'cedi_brandsGridDisableSorting'));

				// remove xmlrpc links from html
				if (get_option('cedi_admin_settings_tab_misc_xmlrpc_disabled') == 'yes') {
					add_filter('xmlrpc_enabled', '__return_false', 10, 1);
					remove_action('wp_head', 'rsd_link');
					add_filter('bloginfo_url', array($this, 'cedi_remove_pingback'), 1, 2);
					add_filter('bloginfo', array($this, 'cedi_remove_pingback'), 1, 2);
				}
				if (get_option('cedi_admin_settings_tab_misc_json_disabled') == 'yes') {
					remove_action('wp_head', 'rest_output_link_wp_head', 10);
					remove_action('wp_head', 'wp_oembed_add_discovery_links', 10);
					remove_action('template_redirect', 'rest_output_link_header', 11, 0);
				}
				if (get_option('cedi_admin_settings_tab_misc_show_variations_enable') == 'yes') {
					add_action('woocommerce_product_query', array($this, 'cedi_product_query'));
					add_filter('the_title', array($this, 'cedi_loop_product_title'), 10, 2);
					if (get_option('cedi_admin_settings_tab_misc_hide_variations_parent_enable') == 'yes') {
						add_filter('posts_clauses', array($this, 'cedi_posts_clauses'), 10, 2);
					}
					add_action('woocommerce_before_shop_loop', array($this, 'cedi_total_count'), 1);
					add_filter('woocommerce_product_categories_widget_args', array($this, 'cedi_product_categories_widget_args'));
				}
				add_filter('woocommerce_product_get_default_attributes', array($this, 'cedi_product_default_attributes'), 99, 2);
				// schemas & seo
				//add_action('wp_footer', array( $this, 'cedi_schemas_seo'));
			}
			/*function cedi_schemas_seo() { 
				if(is_product()){
					echo '<script type="application/ld+json">'.$this->SeoUtils->genProductSchema(get_the_ID()).'</script>';
				}
			}*/
			function cedi_sortVariationValues($terms, $product_id, $taxonomy, $args)
			{
				// display values even if essentialForChoice = false for now
				/*
				if (is_product()) {
					global $product;
					if (!$this->Core->getProductUtils()->isAttributeEssentialForChoice($product, $taxonomy)) return false;
				}
				*/
				$sordtedTerms = [];
				$sordtedNames = [];
				foreach ($terms as $index => $term) {
					if (is_object($term)) $sordtedNames[$index] = $term->name;
					else $sordtedNames[$index] = $term;
				}
				asort($sordtedNames, SORT_NATURAL);
				foreach ($sordtedNames as $index => $name) {
					foreach ($terms as $subindex => $term) {
						if (is_object($term)) {
							if ($term->name == $name) $sordtedTerms[] = $term;
						} else {
							if ($term == $name) $sordtedNames[] = $term;
						}
					}
				}
				return $sordtedTerms;
				//}
				//return $terms;
			}
			public function add_product_hidden_sku_options()
			{
				global $product;
				if (is_a($product, 'WC_Product_Variable')) {
					$hiddenSkuOptsData = [];
					$variations = $product->get_available_variations();
					remove_filter('woocommerce_attribute_label', array($this, 'cedi_attribute_label'), 10, 2);
					foreach ($variations as $key => $variation) {
						$sku = $variation['sku'];
						$hiddenSkuOptsData[$sku] = [];
						foreach ($variation['attributes'] as $attr => $adata) {
							//if (count($variation['attributes']) > 1) {
							$attrName = str_replace('attribute_', '', $attr);
							if (!$this->Core->getProductUtils()->isAttributeEssentialForChoice($product, $attrName)) {
								$hiddenSkuOptsData[$sku][] =
									wc_attribute_label($attrName) . ": " . $adata;
							}
							//}
						}
					}
					if (count($hiddenSkuOptsData)) { ?>
						<script>
							const CEDI_HIDDEN_OPTS =
								<?php
								echo json_encode($hiddenSkuOptsData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
								?>
						</script>
						<div id="cedi_hidden_opts"></div>
					<?php
					}
				}
			}
			function cedi_product_variation_html_override($html, $args)
			{
				if (is_product()) {
					global $product;
					if (!$this->Core->getProductUtils()->isAttributeEssentialForChoice($product, $args['attribute'])) $html = '';
				}
				return $html;
			}
			function cedi_attribute_label($label, $name)
			{
				if (is_product()) {
					global $product;
					if (!$this->Core->getProductUtils()->isAttributeEssentialForChoice($product, $name)) return '';
				}
				return $label;
			}
			function cedi_product_variations_override($data, $product, $variation)
			{
				foreach ($data['attributes'] as $attr => $adata) {
					if (!$this->Core->getProductUtils()->isAttributeEssentialForChoice($product, $attr)) {
						unset($data['attributes'][$attr]);
					}
				}

				return $data;
			}

			public function cedi_product_query($q)
			{
				//if (!is_search()) 
				$q->set('post_type', array('product', 'product_variation'));
				if (get_option('cedi_admin_settings_tab_misc_hide_variations_parent_enable') == 'yes') {
					if (!is_shop()) $q->set('post_type', array('product_variation'));
				}
				$meta_query = $q->get('meta_query');
				$meta_query[] = array(
					'key' => '_price',
					'value' => '',
					'compare' => '!='
				);
				$q->set('meta_query', $meta_query);

				return $q;
			}
			public function cedi_posts_clauses($clauses, $query)
			{
				if (get_query_var('wc-ajax')) return $clauses;
				if (is_array(get_query_var('post_type'))) {
					if (in_array('product_variation', get_query_var('post_type'))) {
						global $wpdb;

						//if (get_query_var('cedi_is_filter_query') && !(get_query_var('cedi_brand_counter'))) return $clauses;
						//if (get_query_var('cedi_is_filter_query')) return $clauses;

						//if (get_query_var(CEDI_BRAND_TAXONOMY_NAME)) print_r($wp_query->request);
						/*global $wp_query;
						foreach ($wp_query->query_vars as $var => $data) {
							if (strpos($var, 'pa_cedi-') !== false) return $clauses;
						}*/

						$clauses['where'] .= " AND  0 = (select count(*) from {$wpdb->posts} as wposts where wposts.post_parent = {$wpdb->posts}.ID and wposts.post_type = 'product_variation') ";
						$clauses['where'] .= "AND 0 != ( SELECT count(*) FROM {$wpdb->posts} AS wpostsp WHERE wpostsp.ID = {$wpdb->posts}.post_parent AND wpostsp.post_status = 'publish' )";
					}
				}
				return $clauses;
			}
			function cedi_product_categories_widget_args($args)
			{
				require_once('centedi-cataddon-category-walker.php');
				$args['walker'] = new Centedi_Category_List_Walker;
				return $args;
			}
			function cedi_Cat_Filter($args)
			{
				if (!empty(get_query_var(CEDI_BRAND_TAXONOMY_NAME))) {
					$brands = explode(',', get_query_var(CEDI_BRAND_TAXONOMY_NAME));
					foreach ($brands as $key => $brand) {
						if (!term_exists($brand, CEDI_BRAND_TAXONOMY_NAME)) unset($brands[$key]);
					}
					if (is_archive()) {
						$groupIds = [];
						foreach ($this->Core->getProductUtils()->getBrandsCategories($brands) as $catSlug) {
							$groupIds[] = get_term_by('slug', $catSlug, 'product_cat')->term_id;
						}
						$args['include'] = $groupIds;
					}
				}
				return $args;
			}
			public function cedi_total_count()
			{
				global $wp_query;
				//echo $wp_query->request;
				/*$ids = wp_list_pluck($wp_query->posts, "ID");
				$count = 0;
				foreach ($ids as $id) {
					$product = wc_get_product($id);
					if (!$product) continue;
					$parent = wc_get_product($product->get_parent_id());
					if ($parent && $parent->get_status() == 'publish') $count++;
					if (is_a($product, 'WC_Product_Variable')) $count++;
					// TODO: check simple products like CPUs?
				}*/
				wc_set_loop_prop('total', $wp_query->post_count);
			}
			function cedi_product_default_attributes($default_attributes, $product)
			{
				if (!$product->is_type('variable')) return $default_attributes;
				// exit if we already have default attributes?
				if (!empty($default_attributes)) return $default_attributes;

				foreach ($product->get_attributes() as $attribute_slug => $attribute) {
					if ($attribute['variation']) {
						$attr_options = [];
						if (taxonomy_exists($attribute_slug)) {
							foreach ($attribute['options'] as $option) {
								$attr_options[] = get_term($option, $attribute_slug)->slug;
							}
						} else {
							$attr_options = $attribute['options'];
						}
						$default_attributes[$attribute_slug] = $attr_options[0];
					}
				}
				return $default_attributes;
			}
			function cedi_loop_product_title($title, $id)
			{
				if (in_the_loop()) {
					global $product;
					if ((get_class($product) == 'WC_Product_Variation')) {
						$vtitle = $product->get_title() . ' - ';
						$firstAttr = true;
						foreach ($product->get_variation_attributes() as $name => $value) {
							if (!$value) continue;
							$prefix = $firstAttr == true ? '' : ', ';
							$name = str_replace('attribute_', '', $name);
							$fvalue = get_term_by('slug', $value, $name);
							$vtitle .=  $prefix . wc_attribute_label($name) . ' ' . $fvalue->name;
							$firstAttr = false;
						}
						return '<h2 class="woocommerce-loop-product__title">' . $vtitle . '</h2>';
					} else {
						if (!is_product()) {
							return '<h2 class="woocommerce-loop-product__title">' . $title . '</h2>';
						}
					}
				}
				return $title;
			}
			function cedi_updateMicrodata($markup, $product)
			{
				// add brand
				$brand = $this->Core->getProductUtils()->getProductBrandByWcId(get_the_ID());
				if (!empty($brand)) {
					$markup['brand'] = array(
						'@type' => 'Brand',
						'name' => $brand
					);
				}
				// rating
				$rating = $product->get_average_rating();
				if ($rating > 0) {
					$markup['aggregateRating'] = array(
						'@type'                => 'AggregateRating',
						'ratingValue'          => $rating,
						'reviewCount'          => $product->get_review_count() // TODO: get product review count
					);
				}
				// variations extended
				$existingVariations = $product->get_children();

				if (count($existingVariations) > 0) {
					$markup['offers'] = [];
					foreach ($existingVariations as $existingVariationId) {
						$existingVariation = new WC_Product_Variation($existingVariationId);
						$existingVariationPrice = $existingVariation->get_price();
						if ($existingVariationPrice != '') {
							$markup['offers'][] = array(
								'@type' => 'Offer',
								'url' => $existingVariation->get_permalink(),
								'sku' => $existingVariation->get_sku(),
								'gtin' => $existingVariation->get_meta('cedi_sku_bc13'),
								'name' => $existingVariation->get_name(),
								'price' => $existingVariationPrice,
								'priceCurrency' => get_woocommerce_currency(),
								'priceValidUntil' => date('Y-m-d', strtotime('last day of december this year')),
								'availability' => 'http://schema.org/' . $stock = ($existingVariation->is_in_stock() ? 'InStock' : 'OutOfStock'),
							);
						}
					}
				} else {
					$markup['offers'] = array(
						'@type' => 'Offer',
						'url' => $product->get_permalink(),
						'sku' => $product->get_sku(),
						'name' => $product->get_name(),
						'price' => $product->get_price(),
						'priceCurrency' => get_woocommerce_currency(),
						'priceValidUntil' => date('Y-m-d', strtotime('last day of december this year')),
						'availability' => 'http://schema.org/' . $stock = ($product->is_in_stock() ? 'InStock' : 'OutOfStock')
					);
				}
				return $markup;
			}
			public function cedi_product_identifier_sku_option($loop, $data, $variation)
			{
				$variationObj = wc_get_product($variation->ID);

				$value = $variationObj->get_meta('cedi_sku_bc13');
				$label = __("Product Identifier", 'centedi-cataddon');

				woocommerce_wp_text_input(
					array(
						'id'            => "cedi_gtin_id_variable{$loop}",
						'name'          => "cedi_gtin_id_variable[{$loop}]",
						'value'         => $value,
						'label'         => $label,
						'wrapper_class' => 'form-row form-row-first',
					)
				);
			}
			public function cedi_save_product_identifier_sku_option($variation_id, $id)
			{
				$variation = wc_get_product($variation_id);
				if (isset($_POST['cedi_gtin_id_variable'][$id])) {
					$variation->update_meta_data('cedi_sku_bc13', wc_clean(wp_unslash($_POST['cedi_gtin_id_variable'][$id])));
					$variation->save_meta_data();
				}
			}
			function cedi_addMetaDescription()
			{
				//
				$shopStr = wp_strip_all_tags(get_option('cedi_admin_settings_tab_seo_metadescr_homepage_field'));
				// product pages
				if (is_product()) {
					$shortDescr = wp_strip_all_tags(get_the_excerpt(get_the_ID())) . ' - ' . get_bloginfo('name');
				}
				// homepage
				if (is_front_page()) {
					$shortDescr = $shopStr . ' - ' . get_bloginfo('name');
				}
				// brand pages
				if (is_tax(CEDI_BRAND_TAXONOMY_NAME)) {
					global $wp_query;
					if (isset($wp_query->query_vars[CEDI_BRAND_TAXONOMY_NAME])) {
						$term = get_term_by('slug', $wp_query->query_vars[CEDI_BRAND_TAXONOMY_NAME], CEDI_BRAND_TAXONOMY_NAME);
						if ($term)
							$shortDescr = $term->name . '. ' . $shopStr . ' - ' . get_bloginfo('name');
					}
				}
				// category pages
				if (is_tax('product_cat')) {
					global $wp_query;
					$cat = get_queried_object();
					$shortDescr = $cat->name . '. ' . $shopStr . ' - ' . get_bloginfo('name');
				}
				echo '<meta name="description" content="' . $shortDescr . '" />';
			}
			function cedi_addOrgSchema()
			{
				$orgSchema = '<script type="application/ld+json">';
				$orgSchema .= '{';
				$orgSchema .= '"@context": "http://www.schema.org",
					"@type": "Organization",';
				$orgSchema .= '"name": "' . get_bloginfo('name') . '",';
				$orgSchema .= '"url": "' . get_bloginfo('url') . '",';

				$logo = wp_get_attachment_image_src(get_theme_mod('custom_logo'), 'full');
				$logoURL = has_custom_logo() ? $logo[0] : get_bloginfo('name');
				$orgSchema .= '"logo": "' . $logoURL . '"';
				$orgSchema .= '}';
				$orgSchema .= '</script>';
				echo $orgSchema;
			}
			function cedi_remove_pingback($output, $show = '')
			{
				if ($show == 'pingback_url') $output = '';
				return $output;
			}
			function cedi_brandsGridDisableSorting($columns)
			{
				return [];
			}
			function cedi_screenOptionRowsPerPage($per_page)
			{
				global $pagenow;
				if (is_admin() && ('edit-tags.php' == $pagenow) && isset($_GET['taxonomy']) && ($_GET['taxonomy'] == CEDI_BRAND_TAXONOMY_NAME)) {
					return 999;
				}
				return $per_page;
			}
			function cedi_Cfg()
			{
				// sort brands by order if required
				global $pagenow;
				if (is_admin() && ('edit-tags.php' == $pagenow) && isset($_GET['taxonomy']) && ($_GET['taxonomy'] == CEDI_BRAND_TAXONOMY_NAME)) {

					$this->reorderBrands();
					////
					/*$brands = get_terms([
						'taxonomy' => CEDI_BRAND_TAXONOMY_NAME,
						'hide_empty' => false
					]);
					foreach($brands as $brand){
						echo $brand->name." ".get_term_meta($brand->term_id, CEDI_BRAND_ORDER_FIELD_NAME, true)."<br>";
					}*/
					////
				}
				// xml sitemap
				global $wp;
				if (get_option('cedi_admin_settings_tab_seo_enable_cedi_sitemap_cb_enabled') == 'yes') {

					$wp->add_query_var('sitemap');
					$wp->add_query_var('sitemap_page');

					add_filter('wp_sitemaps_enabled', '__return_false');
					add_action('template_redirect', array($this, 'cedi_SitemapRedirect'));
					add_filter('redirect_canonical', array($this, 'cedi_CanonicalRedirects'));
					// compatibility
					add_rewrite_rule('sitemap_index\.xml$', 'index.php?sitemap=index', 'top');
					add_rewrite_rule('([^/]+?)-sitemap([0-9]+)?\.xml$', 'index.php?sitemap=$matches[1]&sitemap_page=$matches[2]', 'top');

					// custom path to index file?
					$custom_xml = get_option('cedi_admin_settings_tab_seo_custom_xml_index');
					if ($custom_xml && $custom_xml != 'sitemap.xml') {
						add_rewrite_rule($custom_xml . '$', 'index.php?sitemap=index', 'top');
					}

					// todo: check if rules already flushed & skip?
					flush_rewrite_rules(false);
				}
			}
			// sitemap redirects
			function cedi_SitemapRedirect()
			{
				$redirect = "";
				if (strpos($_SERVER['REQUEST_URI'], '/wp-sitemap.') !== false) {
					if (strpos($_SERVER['REQUEST_URI'], 'wp-sitemap.xml') !== false) $redirect = '/sitemap_index.xml';
				}
				/*if(preg_match('/^\/wp-sitemap-(posts|taxonomies)-(\w+)-(\d+)\.xml$/',$_SERVER['REQUEST_URI'],$matches)){
					$index = ((int)$matches[3]-1);
					$index = ($index === 0)?'':(string)$index;
					return '/'.$matches[2].'-sitemap'.$index.'.xml';
				}
				*/

				if ($redirect != "") {
					wp_safe_redirect(home_url($redirect), 301, 'CentEDI Cataddon');
					exit;
				}
			}
			function cedi_CanonicalRedirects($redirect)
			{
				if (get_query_var('sitemap')) return false;
				return $redirect;
			}


			function cedi_sortBrands($query)
			{
				global $pagenow;
				if (is_admin() && ('edit-tags.php' == $pagenow) && isset($_GET['taxonomy']) && ($_GET['taxonomy'] == CEDI_BRAND_TAXONOMY_NAME)) {
					$meta_query_args = array(
						'order_clause' => array(
							'key'     => CEDI_BRAND_ORDER_FIELD_NAME,
							'compare' => 'EXISTS',
						)
					);
					$meta_query = new WP_Meta_Query($meta_query_args);
					$query->meta_query = $meta_query;
					$query->query_vars['orderby'] = 'meta_value_num';
					return $query;
				}
			}

			function isSearchPage()
			{
				return (isset($_GET['dgwt_wcas']) && isset($_GET['post_type']) && $_GET['post_type'] === 'product' && isset($_GET['s']));
			}
			function cedi_prePosts($query)
			{
				if (is_admin()) return;
				if (is_search()) {
					if (get_option('cedi_admin_settings_tab_misc_show_variations_enable') == 'yes') {
						$searchParams = array('product', 'product_variation');
						if (get_option('cedi_admin_settings_tab_misc_hide_variations_parent_enable') == 'yes') {
							if (!$this->isSearchPage()) $searchParams = array('product_variation');
						}
						$query->set('post_type', $searchParams);
						$query->is_post_type_archive = true;
					}
				}
				if (!empty(get_query_var(CEDI_BRAND_TAXONOMY_NAME))) {
					$brands = explode(',', get_query_var(CEDI_BRAND_TAXONOMY_NAME));
					foreach ($brands as $key => $brand) {
						if (!term_exists($brand, CEDI_BRAND_TAXONOMY_NAME)) unset($brands[$key]);
					}
					if ($query->is_main_query() && is_archive()) {
						$query->set('tax_query', array(
							'relation' => 'AND',
							array(
								'taxonomy' => CEDI_BRAND_TAXONOMY_NAME,
								'field' => 'slug',
								'terms' => $brands
							)
						));
						/*$groupIds=[];
						foreach($this->Core->getProductUtils()->getBrandsCategories($brands) as $catSlug){
							$groupIds[]=get_term_by('slug', $catSlug, 'product_cat')->term_id;
						}*/
					}
				}
				// sitemaps
				if (get_option('cedi_admin_settings_tab_seo_enable_cedi_sitemap_cb_enabled') == 'yes') {
					$sitemapParams = get_query_var('sitemap');
					if (!empty($sitemapParams) && $query->is_main_query()) {
						// see public function output() {
						remove_all_actions('widgets_init');
						if (!headers_sent()) {
							header($_SERVER['SERVER_PROTOCOL'] . ' 200 OK', true, 200);
							header('X-Robots-Tag: noindex, follow', true);
							header('Content-Type: text/xml; charset=UTF-8');
							// TODO: other headers needed?
						}

						$output = '<?xml version="1.0" encoding="UTF-8"?>';
						$output .= '
<?xml-stylesheet type="text/xsl" href="' . plugin_dir_url(__FILE__) . 'main-sitemap.xsl' . '"?>';
						$CentediUtils = new CentediUtils();
						$output .= $CentediUtils->getSitemapXml([get_query_var('sitemap'), get_query_var('sitemap_page')]);
						$output .= "\n
<!-- XML Sitemap generated by CentEDI Cataddon plugin -->";
						echo $output;
						remove_all_actions('wp_footer');
						die();
					}
				}
			}
			private function reorderBrands()
			{
				$brands = get_terms([
					'taxonomy' => CEDI_BRAND_TAXONOMY_NAME,
					'hide_empty' => false
				]);
				$brandArray = [];
				foreach ($brands as $brand) {
					$brandOrder = get_term_meta($brand->term_id, CEDI_BRAND_ORDER_FIELD_NAME, true);
					$brandArray[$brandOrder] = $brand->term_id;
				}
				ksort($brandArray);
				$brandArray = array_values($brandArray);
				foreach ($brands as $brand) {
					$newBrandOrder = array_search($brand->term_id, $brandArray);
					update_term_meta($brand->term_id, CEDI_BRAND_ORDER_FIELD_NAME, $newBrandOrder);
				}
			}
			function sortBrands()
			{
				try {
					if (is_array($_POST['brand_data'])) {
						$rows = $_POST['brand_data']['rows'];
						if (is_array($rows)) {
							foreach ($rows as $index => $brandId) {
								$brandTerm = get_term_by('id', $brandId, CEDI_BRAND_TAXONOMY_NAME);
								update_term_meta($brandTerm->term_id, CEDI_BRAND_ORDER_FIELD_NAME, $index);
							}
							echo json_encode(['status' => 'OK']);
							wp_die();
							return;
						}
					}
				} catch (Exception $e) {
					echo json_encode(['status' => 'FAILED', 'msg' => $e->getMessage()]);
				}
				echo json_encode(['status' => 'FAILED', 'msg' => __('Something went wrong', 'centedi-cataddon')]);
				wp_die();
			}
			function showHideBrands()
			{
				try {
					if (is_array($_POST['brand_data'])) {
						$brandId = $_POST['brand_data']['id'];
						$brandTerm = get_term_by('id', $brandId, CEDI_BRAND_TAXONOMY_NAME);
						update_term_meta($brandTerm->term_id, CEDI_BRAND_VISIBILITY_FIELD_NAME, $_POST['brand_data']['visible']);
						echo json_encode(['status' => 'OK']);
						wp_die();
						return;
					}
				} catch (Exception $e) {
					echo json_encode(['status' => 'FAILED', 'msg' => $e->getMessage()]);
				}
				echo json_encode(['status' => 'FAILED', 'msg' => __('Something went wrong', 'centedi-cataddon')]);
				wp_die();
			}
			function uploadFile()
			{
				try {
					$zeContents = '';
					if (isset($_FILES['cedi_bulk_import_file'])) {
						$barcodeColumn = 1;
						$skipFirstRow = false;
						$skipExiting = false;
						$CentediUtils = new CentediUtils();

						if (isset($_POST['cedi_upload_bc_col'])) $barcodeColumn = $_POST['cedi_upload_bc_col'];
						if (isset($_POST['cedi_upload_first_line_header'])) $skipFirstRow = $_POST['cedi_upload_first_line_header'] == 1 ? true
							: false;
						if (isset($_POST['cedi_upload_skip_existing'])) $skipExiting = $_POST['cedi_upload_skip_existing'] == 1 ? true : false;

						$resFile = wp_handle_upload($_FILES['cedi_bulk_import_file'], ['test_form' => false]);

						if ($resFile && !isset($resFile['error'])) {
							$info = pathinfo($resFile['file']);
							$data = implode("", file($resFile['file']));
							$barcodes = [];

							$fHandle = @fopen($resFile['file'], 'r');
							if ($fHandle) {
								if ($skipFirstRow) fgetcsv($fHandle, 10000, ","); // skip header if set
								while (($emapData = fgetcsv($fHandle, 10000, ",")) !== false) {
									// check if this is real barcode?
									$barcode = $emapData[$barcodeColumn - 1];
									if (is_numeric($barcode)) {
										if ($this->Core->getProductUtils()->getVariationByBarcodeGTIN($barcode)) {
											if (!$skipExiting) $barcodes[] = $barcode;
										} else {
											$barcodes[] = $barcode;
										}
									}
								}
								fclose(fHandle);
							} else {
								echo json_encode(['status' => 'FAILED', 'msg' => __('Error uploading file')]);
								wp_die();
								return;
							}

							$gzdata = gzencode($data, 9);

							$zeContents = base64_encode($CentediUtils->AESCBCEncrypt($gzdata, $CentediUtils->getCediEncKey()));

							if ($zeContents != '') {
								$result = $CentediUtils->makePortalRequest(array(
									'api' => 'catalog',
									'cmd' => 'product_csv_import',
									'data' => $zeContents,
									'barcodes' => base64_encode(implode(",", $barcodes)),
								));

								if (isset($result->status)) {
									if ($result->status != 1) {
										echo json_encode(['status' => 'FAILED', 'msg' => __('Error storing file')]);
										wp_die();
										return;
									} else {
										// don't import existing items from import if checked
										$resultItemArray = [];
										if ($skipExiting) {
											foreach ($result->items as $itemId => $itemName) {
												if (!$this->Core->getProductUtils()->getWooProductIdByCediId($itemId)) {
													$resultItemArray[$itemId] = $itemName;
												}
											}
											echo json_encode(['status' => 'OK', 'data' => $resultItemArray]);
										} else {
											echo json_encode(['status' => 'OK', 'data' => $result->items]);
										}
										wp_die();
										return;
									}
								}
							}
						} else {
							echo json_encode(['status' => 'FAILED', 'msg' => $resFile['error']]);
							wp_die();
							return;
						}
					}
					echo json_encode(['status' => 'FAILED', 'msg' => __('Something went wrong', 'centedi-cataddon')]);
					wp_die();
					return;
				} catch (Exception $e) {
					echo json_encode(['status' => 'FAILED', 'msg' => $e->getMessage()]);
				}
				echo json_encode(['status' => 'FAILED', 'msg' => __('Something went wrong', 'centedi-cataddon')]);
				wp_die();
			}

			function add_webp_type($mime_types)
			{
				$mime_types['webp'] = 'image/webp';
				return $mime_types;
			}

			function getAuthData()
			{
				try {
					echo json_encode($this->Core->getAuthData(true));
				} catch (Exception $e) {
					$error = $e->getMessage();
					$res = [
						'status' => 'FAILED',
						'msg' => $error
					];
					echo json_encode($res);
				}
				wp_die();
			}
			function getRegistrationStatus()
			{
				try {
					$CentediUtils = new CentediUtils();
					$portalResponse = $CentediUtils->makePortalRequest(array(
						'api' => 'org',
						'cmd' => 'subscription_get_source_status'
					));
					echo json_encode($portalResponse);
				} catch (Exception $e) {
					echo json_encode(['status' => 'FAILED', 'msg' => $e->getMessage()]);
				}
				wp_die();
			}
			function checkRegistrationData()
			{
				try {
					$CentediUtils = new CentediUtils();
					$orgName = isset($_POST['org']) ? $_POST['org'] : "";
					$email = isset($_POST['email']) ? $_POST['email'] : "";

					$portalResponse = $CentediUtils->makePortalRequest(array(
						'api' => 'org',
						'cmd' => 'registration_validate',
						'orgName' => $orgName,
						'email' => $email,
					));
					echo json_encode($portalResponse);
				} catch (Exception $e) {
					echo json_encode(['status' => 'FAILED', 'msg' => $e->getMessage()]);
				}
				wp_die();
			}
			function registerOnPortal()
			{
				try {
					$CentediUtils = new CentediUtils();
					$orgName = isset($_POST['org']) ? $_POST['org'] : "";
					$email = isset($_POST['email']) ? $_POST['email'] : "";
					$pass = isset($_POST['pass']) ? $_POST['pass'] : "";

					$portalResponse = $CentediUtils->makePortalRequest(array(
						'api' => 'org',
						'cmd' => 'registration_register',
						'orgName' => $orgName,
						'email' => $email,
						'password' => $pass,
					), false);
					echo json_encode($portalResponse);
				} catch (Exception $e) {
					echo json_encode(['status' => 'FAILED', 'msg' => $e->getMessage()]);
				}
				wp_die();
			}
			function saveAuthData()
			{
				try {
					if (isset($_POST['auth_data'])) {
						// validate & sanitize org. name, email & key
						$sanitizedData = [];
						$originalData = $_POST['auth_data'];
						if (isset($originalData['org'])) {
							$sanitizedData['org'] = sanitize_text_field($originalData['org']);
						}
						if (isset($originalData['email'])) {
							$sanitizedEmail = sanitize_email($originalData['email']);
							$sanitizedData['email'] = is_email($sanitizedEmail) ? $sanitizedEmail : "";
						}
						if (isset($originalData['APIKey'])) {
							$sanitizedKey = sanitize_text_field($originalData['APIKey']);
							$sanitizedData['APIKey'] = strlen($sanitizedKey) == 32 ? $sanitizedKey : "";
						}
						if (isset($originalData['EncKey'])) {
							$sanitizedKey = sanitize_text_field($originalData['EncKey']);
							$sanitizedData['EncKey'] = strlen($sanitizedKey) == 32 ? $sanitizedKey : "";
						}
						if (array_filter($sanitizedData) == []) {
							echo json_encode(['status' => 'FAILED', 'msg' => __(
								"You have an error in your data, please check fields!",
								'centedi-cataddon'
							)]);
						} else {
							echo json_encode($this->Core->saveAuthData($sanitizedData));
						}
					}
				} catch (Exception $e) {
					echo json_encode(['status' => 'FAILED', 'msg' => $e->getMessage()]);
				}
				wp_die();
			}
			function processProductSearchData($originalSearchData)
			{
				$sanitizedData = [];
				if (isset($originalSearchData['ID'])) {
					$sanitizedIID = sanitize_text_field($originalSearchData['ID']);
					if (is_numeric($sanitizedIID)) $sanitizedData['ID'] = $sanitizedIID;
				}
				if (isset($originalSearchData['PARENT_ID'])) {
					$sanitizedIID = sanitize_text_field($originalSearchData['PARENT_ID']);
					if (is_numeric($sanitizedIID)) $sanitizedData['PARENT_ID'] = $sanitizedIID;
				}
				if (isset($originalSearchData['TITLE'])) {
					$sanitizedData['TITLE'] = sanitize_text_field($originalSearchData['TITLE']);
				}
				if (isset($originalSearchData['SIMPLE'])) {
					$sanitizedData['SIMPLE'] = (int)sanitize_text_field($originalSearchData['SIMPLE']);
				}
				if (isset($originalSearchData['MODEL'])) {
					$sanitizedData['MODEL'] = sanitize_text_field($originalSearchData['MODEL']);
				}
				if (isset($originalSearchData['SKU'])) {
					$sanitizedData['SKU'] = sanitize_text_field($originalSearchData['SKU']);
				}
				if (isset($originalSearchData['CODE'])) {
					$sanitizedData['CODE'] = sanitize_text_field($originalSearchData['CODE']);
				}
				if (isset($originalSearchData['KEY_PROPS_DESCRIPTION'])) {
					$sanitizedData['KEY_PROPS_DESCRIPTION'] = wp_kses_post($originalSearchData['KEY_PROPS_DESCRIPTION']);
				}
				if (isset($originalSearchData['SHORT_DESCRIPTION'])) {
					$sanitizedData['SHORT_DESCRIPTION'] = sanitize_textarea_field($originalSearchData['SHORT_DESCRIPTION']);
				}
				if (isset($originalSearchData['DETAILED_DESCRIPTION'])) {
					$sanitizedData['DETAILED_DESCRIPTION'] = $originalSearchData['DETAILED_DESCRIPTION'];
				}
				if (isset($originalSearchData['IMAGE_VERSIONS'])) {
					$sanitizedData['IMAGE_VERSIONS'] = is_array($originalSearchData['IMAGE_VERSIONS']) ? $originalSearchData['IMAGE_VERSIONS'] : "";
				} else {
					$sanitizedData['IMAGE_VERSIONS'] = "";
				}
				if (isset($originalSearchData['PARENT_IMAGE_VERSIONS'])) {
					$sanitizedData['PARENT_IMAGE_VERSIONS'] = is_array($originalSearchData['PARENT_IMAGE_VERSIONS']) ? $originalSearchData['PARENT_IMAGE_VERSIONS'] : "";
				} else {
					$sanitizedData['PARENT_IMAGE_VERSIONS'] = "";
				}
				if (isset($originalSearchData['DOCS'])) {
					$sanitizedData['DOCS'] = is_array($originalSearchData['DOCS']) ? $originalSearchData['DOCS'] : "";
				} else {
					$sanitizedData['DOCS'] = "";
				}
				if (isset($originalSearchData['BRAND'])) {
					$sanitizedData['BRAND'] = wp_kses_post($originalSearchData['BRAND']);
				}
				if (isset($originalSearchData['BRAND_IMAGE_VERSIONS'])) {
					$sanitizedData['BRAND_IMAGE_VERSIONS'] = is_array($originalSearchData['BRAND_IMAGE_VERSIONS']) ? $originalSearchData['BRAND_IMAGE_VERSIONS'] :
						"";
				}
				return $sanitizedData;
			}
			function saveProduct()
			{
				try {
					$sanitizedSearchData = [];
					if (is_array($_POST['search_data'])) {
						// validate & sanitize important fields
						$sanitizedSearchData = $this->processProductSearchData($_POST['search_data']);
					}
					if (array_filter($sanitizedSearchData) != []) {
						// check if product_data is base64 encoded string
						if ((isset($_POST['product_data']['DATA'])) && (base64_decode($_POST['product_data']['DATA']) !== false)) {
							echo json_encode($this->Core->saveProduct(array('search_data' => $sanitizedSearchData, 'product_data' =>
							$_POST['product_data'])));
						} else {
							echo json_encode(['status' => 'FAILED', 'msg' => __("Invalid product data", 'centedi-cataddon')]);
						}
					} else {
						echo json_encode(['status' => 'FAILED', 'msg' => __("Invalid product data", 'centedi-cataddon')]);
					}
				} catch (Exception $e) {
					echo json_encode(['status' => 'FAILED', 'msg' => $e->getMessage()]);
				}
				wp_die();
			}
			function getUpdatedProducts()
			{
				try {
					$CentediUtils = new CentediUtils();
					$portalResponse = $CentediUtils->makePortalRequest(array(
						'cmd' => 'product_get_updated_products_list'
					));

					$ids = json_decode(json_encode($portalResponse), true);
					if (array_key_exists("status", $ids)) echo json_encode($ids);
					else echo json_encode($this->Core->getProductsForUpdate($ids));
				} catch (Exception $e) {
					echo json_encode(['status' => 'FAILED', 'msg' => $e->getMessage()]);
				}
				wp_die();
			}
			function searchProductsOnPortal()
			{
				try {
					$CentediUtils = new CentediUtils();
					$authdata = $CentediUtils->getCediAuthData();
					$mudata = $CentediUtils->getMeasureDataTypes();
					$portalResponse = $CentediUtils->makePortalRequest(array(
						'cmd' => 'product_search',
						'str'  => $_REQUEST['str'],
						'lang'  => $authdata['locale'],
						'country'  => $authdata['country'],
						'weightUnit'  => $mudata['weight'],
						'dimensionalUnit'  => $mudata['dim'],
					));
					echo json_encode($portalResponse);
				} catch (Exception $e) {
					echo json_encode(['status' => 'FAILED', 'msg' => $e->getMessage()]);
				}
				wp_die();
			}
			function notFoundProc()
			{
				try {
					$CentediUtils = new CentediUtils();
					$portalResponse = $CentediUtils->makePortalRequest(array(
						'cmd' => 'product_wish_request',
						'brand'  => $_REQUEST['brand'],
						'product'  => $_REQUEST['product'],
						'email' => $_REQUEST['email'],
					));
					echo json_encode($portalResponse);
				} catch (Exception $e) {
					echo json_encode(['status' => 'FAILED', 'msg' => $e->getMessage()]);
				}
				wp_die();
			}

			function importSelectedProduct()
			{
				$CentediUtils = new CentediUtils();
				$authdata = $CentediUtils->getCediAuthData();
				$mudata = $CentediUtils->getMeasureDataTypes();

				$portalResponse = $CentediUtils->makePortalRequest(array(
					'cmd' => 'product_import',
					'product_id_list'  => $_POST['itemId'],
					'lang'  => $authdata['locale'],
					'country'  => $authdata['country'],
					'weightUnit'  => $mudata['weight'],
					'dimensionalUnit'  => $mudata['dim'],
				));

				try {
					$sanitizedSearchData = [];
					if (is_array($_POST['search_data'])) {
						// validate & sanitize important fields
						$sanitizedSearchData = $this->processProductSearchData($_POST['search_data']);
					}
					if (array_filter($sanitizedSearchData) != []) {
						echo json_encode($this->Core->saveProduct(array('search_data' => $sanitizedSearchData, 'product_data' => (array)$portalResponse)));
					}
				} catch (Exception $e) {
					echo json_encode(['status' => 'FAILED', 'msg' => __("Error importing product", 'centedi-cataddon')]);
				}
				wp_die();
			}
			function updateSelectedProduct()
			{
				$msg = '';
				$failedSKUs = [];
				$CentediUtils = new CentediUtils();
				$authdata = $CentediUtils->getCediAuthData();
				$mudata = $CentediUtils->getMeasureDataTypes();

				$mode = $_POST['mode'];

				$portalResponse = $CentediUtils->makePortalRequest(array(
					'cmd' => 'product_import',
					'product_id_list'  => $_POST['itemId'],
					'lang'  => $authdata['locale'],
					'country'  => $authdata['country'],
					'mode'  => $mode,
					'weightUnit'  => $mudata['weight'],
					'dimensionalUnit'  => $mudata['dim'],
				));
				//wp_die();
				// contains list of skus for itemId
				$totalUpdatedSKUs  = count((array)$portalResponse->DATA);
				$skuArr = [];
				$updatedSKUs = 0;
				foreach ($portalResponse->DATA as $skuId => $skuData) {
					$skuArr[] = $skuId;
					$rawData = json_decode(json_encode($skuData), true);
					$skuData =	$rawData['GENERAL_DATA'][1];
					$sanitizedSearchData = $this->processProductSearchData($skuData);
					$encData = $rawData['DETAILED_DATA'][1];

					try {
						if (isset($rawData['DETAILED_DATA'])) {
							$result = $this->Core->updateProduct(array('search_data' => $sanitizedSearchData, 'product_data' => array('DATA' => $encData)));
							if ($result['status'] == 'OK') {
								$updatedSKUs++;
								$msg = $result['msg'];
							} else {
								$failedSKUs[] = $skuData['SKU'];
							}
						} else {
							//echo json_encode(['status' => 'FAILED', 'msg' => __("Invalid product data", 'centedi-cataddon')]);
							$msg = __("Invalid product data", 'centedi-cataddon');
						}
					} catch (Exception $e) {
						$failedSKUs[] = $skuData['SKU'];
					}
				}

				if ($totalUpdatedSKUs == $updatedSKUs) {
					// inform portal
					if ($mode == 'u') {
						foreach ($skuArr as $sku) {
							$portalResponse =  $CentediUtils->makePortalRequest(array(
								'cmd' => 'product_update_stats',
								'data'  => $sku,
							));
						}
					}
					// 
					echo json_encode(['status' => 'OK', 'msg' => $msg]);
				} else {
					echo json_encode(['status' => 'FAILED', 'msg' => __("Error processing SKUs: " . implode(", ", $failedSKUs), 'centedi-cataddon')]);
				}

				wp_die();
			}
			function getImageConfigs()
			{
				try {
					$CentediUtils = new CentediUtils();
					$portalResponse = $CentediUtils->makePortalRequest(array(
						'cmd' => 'cfg_get_img_formats'
					));
					echo json_encode($portalResponse);
				} catch (Exception $e) {
					echo json_encode(['status' => 'FAILED', 'msg' => $e->getMessage()]);
				}
				wp_die();
			}
			function getProductsForUpdate($updateIds)
			{
				$resultIds = [];
				if (is_array($updateIds)) {
					foreach ($updateIds as $id) {
						if (is_numeric($id)) $resultIds[] = $id;
					}
					return $this->Core->getProductsForUpdate($resultIds);
				}
			}
			function updateProduct()
			{
				try {
					if (is_array($_POST['search_data'])) {
						// validate & sanitize important fields
						$sanitizedSearchData = $this->processProductSearchData($_POST['search_data']);
						// if we have all required params ( 12 for now )
						if (array_filter($sanitizedSearchData) != []) {
							// check if product_data is base64 encoded string
							if ((isset($_POST['product_data']['DATA'])) && (base64_decode($_POST['product_data']['DATA']) !== false)) {
								echo json_encode($this->Core->updateProduct(array('search_data' => $sanitizedSearchData, 'product_data' =>
								$_POST['product_data'])));
							} else {
								echo json_encode(['status' => 'FAILED', 'msg' => __("Invalid product data", 'centedi-cataddon')]);
							}
						} else {
							echo json_encode(['status' => 'FAILED', 'msg' => __("Invalid product data", 'centedi-cataddon')]);
						}
					} else {
						echo json_encode(['status' => 'FAILED', 'msg' => __("Invalid product data", 'centedi-cataddon')]);
					}
				} catch (Exception $e) {
					echo json_encode(['status' => 'FAILED', 'msg' => $e->getMessage()]);
				}
				wp_die();
			}
			public function addBrandsTaxonomy()
			{
				$labels = array(
					'name' => __('Brand', 'centedi-cataddon'),
					'singular_name' => __('Brand', 'centedi-cataddon'),
					'search_items' => __('Search', 'centedi-cataddon'),
					'all_items' => __('All Brands', 'centedi-cataddon'),
					'view_item ' => __('View', 'centedi-cataddon'),
					'parent_item' => __('Parent', 'centedi-cataddon'),
					'parent_item_colon' => __('Parent:', 'centedi-cataddon'),
					'edit_item' => __('Edit', 'centedi-cataddon'),
					'update_item' => __('Update', 'centedi-cataddon'),
					'add_new_item' => __('Add new brand', 'centedi-cataddon'),
					'new_item_name' => __('New Brand Name', 'centedi-cataddon'),
					'menu_name' => __('Brands', 'centedi-cataddon'),
					'not_found' => __('No brands found', 'centedi-cataddon')
				);

				$args = array(
					'hierarchical' => true,
					'labels' => $labels,
					'show_ui' => true,
					'query_var' => true,
					'public' => true,
					'show_admin_column' => true,
					'rewrite' => true
				);
				register_taxonomy(CEDI_BRAND_TAXONOMY_NAME, array('product'), $args);
			}


			public function initWidgets()
			{
				register_widget('Centedi_Cataddon_Widget');
				register_widget('Centedi_Cataddon_Brands_Widget');
				register_sidebar(
					array(
						'name' => __('Brands container', 'centedi-cataddon'),
						'id' => 'cedi_brands',
						'description' => __('CentEDI brands slider container', 'centedi-cataddon'),
						'before_widget' => '<div id="%1$s" class="widget %2$s">',
						'after_widget' => '</div>',
						'before_title' => '<h3>',
						'after_title' => '</h3>'
					)
				);
			}
			public function admin_load_resources()
			{
				wp_enqueue_style('wp-jquery-ui-dialog');
				wp_enqueue_media();
				wp_enqueue_style('cedicss', plugin_dir_url(__FILE__) . 'css/centedi.min.css', array(), rand(1000, 99999));
				wp_enqueue_script(
					'cedijs',
					plugin_dir_url(__FILE__) . 'js/centedi.min.js',
					array(
						'jquery', 'jquery-ui-core', 'jquery-ui-autocomplete',
						'jquery-ui-dialog', 'jquery-ui-widget', 'jquery-ui-progressbar', 'jquery-ui-tooltip', 'jquery-ui-sortable', 'wp-i18n'
					),
					rand(1000, 99999)
				);
			}
			public function frontend_load_resources()
			{

				wp_enqueue_style('cedicss', plugin_dir_url(__FILE__) . 'css/centedi.min.css', array(), rand(1000, 99999));
				wp_enqueue_script('cedijs', plugin_dir_url(__FILE__) . 'js/centedi.min.js', array(
					'jquery', 'jquery-ui-core',
					'jquery-ui-dialog', 'jquery-ui-widget', 'jquery-ui-progressbar', 'jquery-ui-tooltip', 'wp-i18n'
				), rand(1000, 99999));
				$brands = $this->Core->getProductUtils()->getFrontendBrands(true);
				$sliderCfg = array(
					'imgPath' => plugin_dir_url(__FILE__) . "/img/",
					'brands' => $brands
				);
				wp_localize_script('cedijs', 'sliderCfg', $sliderCfg);
			}
			public function cedi_addAdminMenu()
			{
				$icon =
					"data:image/x-icon;base64,iVBORw0KGgoAAAANSUhEUgAAABQAAAAUCAYAAACNiR0NAAACkklEQVQ4jZ2UMUsjQRiG54fFRth0EraQxUKsLCyCeM2dpLOyiQREWJQUgiQQImmEYCEoGhQCGhJIZBMWliTMMuyEXcLqds9VZ5Ak53lfN/O+78M3M8wnxF8qnU5TLBbp9Xo0m01+/vrJ3/xLa2VlhaurK+I4RmvNcDjEcRy01ozHYw4ODv4dXKlUiKIIrTVJkuB5HoeHhxwfHxMEAVprPM/j9fWVk5OTxWDDMDg/PydJEqbTKaPRCNd12fuxNxcoFAp4nke32yUMQ6SUZDKZz77T01MA3t7e8H2f3d3dL4+Uz+eZTCbkcjkqlQqPj4+zzPX1NRcXFzw/P+P7PoVC4UtgKpXi6OiIKIpIkgSl1CwTRRHv7++Mx2POzs54eXlBSrkUXCqVkFLS7XbJ5XI4joOUcubVWuP7PrZt02q18DyPh4cHWq0WSimq1SrZbJZqtYpSitvbW7a3txFCCMuyFgOVUmxubiKEEDs7O7iui5SSWq2G67p4nofjOJim+anrjY2NxcDJZIJlWZ/MW1tbuK6LUmrpQ30L+Kfy+Tz9fn+hZpom/X7/e0AhhJhOpws1wzA+rucTMAxD1tfXlwKjKFqopdPpeWAQBPi+Tzab/TZwbW2NwWDAeDye6UopisUiQRBQKpUWBieTydy+YRg4jkO9Xmc4HM70/f19fN/n5uaGdrvNYDCYmyha64+1ZVnU63WklDSbTdrtNvf39/ONlMtllFI8PT3RbDYZjUYfE0VrjWVZNBoNgiDg8vKSXq9HGIbYtr38q5qmSblcJo5jOp0Od3d37P3YA0BKSaPRoNPpoLXGtm1SqdS/z8ZarcZwOCSTyZAkCb1eD6UU5XL5/ya3EEKsrq4ihBBxHJPP578E/QbWK4OKEsoAdgAAAABJRU5ErkJggg==";
				add_menu_page(
					__('CentEDI', 'centedi-cataddon'),
					__('CentEDI', 'centedi-cataddon'),
					'manage_options',
					'centedi-cataddon',
					'',
					$icon
				);
				add_submenu_page(
					'centedi-cataddon',
					__('Settings', 'centedi-cataddon'),
					__('Settings', 'centedi-cataddon'),
					'manage_options',
					'centedi-cataddon',
					array($this, 'cedi_showSettings')
				);
				add_submenu_page('centedi-cataddon', __('Visit CentEDI site', 'centedi-cataddon'), __(
					'Visit CentEDI site',
					'centedi-cataddon'
				), 'manage_options', 'centedi-cataddon-homepage', array($this, 'cedi_showHomepage'));
			}
			public function cedi_showSettings()
			{
				wp_redirect('admin.php?page=wc-settings&tab=cedi_settings_tab', 301);
				die();
			}
			public function cedi_showHomepage()
			{
				wp_redirect('https://www.centedi.com/', 301);
				die();
			}
			public function admin_product_list_import_button($which)
			{
				global $typenow;
				if ($typenow == 'product' && 'top' === $which) {
					$AuthData = $this->Core->getAuthData();
					if ($AuthData['data']['authdata']) {
						$CentediUtils = new CentediUtils();
					?>
						<script>
							// Globals
							var CEDI_BASE_URL = "<?php echo $CentediUtils->getUrl() ?>";
							var CEDI_AUTH_URL = "<?php echo $CentediUtils->getServiceUrl() ?>";
							var CEDI_PLUGIN_URL = "<?php echo plugin_dir_url(__FILE__) ?>";
						</script>
						<select id='cedi-select' class='cedi-select actions bulkactions button' style="display: none;">
							<option id="cedi-btn-actions" value="ignore"><?php echo __('CentEDI Actions', 'centedi-cataddon'); ?></option>
							<option id="cedi-btn-import" value="import"><?php echo __('Import product...', 'centedi-cataddon'); ?></option>
							<option id="cedi-btn-import-bulk" value="import_bulk">
								<?php echo __('CSV Bulk import', 'centedi-cataddon') . "..."; ?></option>
							<option id="cedi-btn-updates" value="update"><?php echo __('Check updates', 'centedi-cataddon'); ?></option>
						</select>

						<fieldset class="admin__fieldset" id="notfound_form_container" hidden>
							<form id="notfound_form" action="" method="post" style="position: relative;top: 25px;">
								<span>
									<h4 id="notfound_msg_1">
										<?php echo __('We have no such product. Please input brand for this product in order to help us to find the required information:', 'centedi-cataddon') ?>
									</h4>
									<div class="field centedi_reg" id="nfc">
										<label class="admin__field-label" for="nf_input"><span><?php echo __('Brand', 'centedi-cataddon') . " " . __('(not required)', 'centedi-cataddon') ?></span></label>
										<div class="control">
											<input name="nf_input" id="nf_input" title="<?php echo __('Brand', 'centedi-cataddon') . " " . __('(not required)', 'centedi-cataddon') ?>" class="input-text" type="text" />
										</div>
									</div>
									</br>
									<td class="use-default">
										<input id="notfound_notify_check" name="notfound_notify_check" type="checkbox" value="1" class="checkbox config-inherit" checked="checked">
										<label for="notfound_notify_check" class="inherit">
											<?php echo __('Yes I would like to receive the notification on my store admin email when product is ready for import', 'centedi-cataddon') ?>
										</label>
									</td>
								</span>
								</br>
							</form>
						</fieldset>
						<?php
					}
				}
			}
			public function cedi_on_trash($WCProductId)
			{
				if ($this->Core->getProductUtils()->checkProductIsImported($WCProductId)) {
					$this->Core->getProductUtils()->removeProductTotally($WCProductId);
				}
			}
			public function override_product_info()
			{
				global $product;
				if ($this->Core->getProductUtils()->checkProductIsImported($product->get_id())) {
					$brands = wp_get_object_terms($product->get_id(), CEDI_BRAND_TAXONOMY_NAME, array('fields' => 'names'));
					if (!is_wp_error($brands) && !empty($brands)) {
						$term = get_term_by('name', $brands[0], CEDI_BRAND_TAXONOMY_NAME);
						if ($term) {
							$imgId = get_term_meta($term->term_id, CEDI_BRAND_IMG_FIELD_NAME, true);
							$img = wp_get_attachment_image_url($imgId, 'full');
						?>
							<div style="max-width:140px;height:75px;">
								<a href="<?php echo get_term_link($term, CEDI_BRAND_TAXONOMY_NAME); ?>">
									<img class="cedi-brand-logo-image cedi-brand-logo-product-page" src="<?php echo $img; ?>" alt="<?php echo $brands[0]; ?>">
								</a>
							</div>
				<?php
						}
					}
					$model = $this->Core->getProductUtils()->getCediProductModelByWooId($product->get_id());
					if ($model != "") echo "<b>" . __("Model", 'centedi-cataddon') . ":</b> " . $model;
				}
				echo "";
			}
			public function override_attr_tab($tabs)
			{
				global $product;
				if ($this->Core->getProductUtils()->checkProductIsImported($product->get_id())) $tabs['additional_information']['callback'] = array($this, 'cedi_attr_tab_content');
				return $tabs;
			}
			function cedi_attr_tab_content()
			{
				global $product;
				$wcattr = $product->get_attributes();
				if (!$wcattr) return;
				$product_attr_sets = [];
				$object_attr_sets = [];

				foreach ($wcattr as $attribute) {
					//if($attribute->get_variation())continue;
					$key = str_replace('pa_', '', $attribute->get_name());
					$label = esc_html(wc_attribute_label($attribute->get_name(), $product));
					$value = $this->Core->getProductUtils()->getProductAttributeValues($product, $attribute);
					//if(!$value) $value=$product->get_attribute($attribute->get_name());
					// attribute is global or product-level?
					$attrId = $attribute->get_id();
					if ($attribute->get_id() == 0)	$attrId = $this->Core->getProductUtils()->getAttrWCIdByWCName($key);

					$set_data = $this->Core->getProductUtils()->getAttrSetDataByAttrId($attrId);

					if (!$set_data) continue;

					// tooltip
					$atrrTooltip = $this->Core->getProductUtils()->getAttrTooltipByWCId($attrId);
					if ($atrrTooltip != "") {
						$label = '<span data-toggle="tooltip" data-placement="right" data-container="body" data-boundary="window" data-original-title="' . wp_strip_all_tags($atrrTooltip) . '" title="' . wp_strip_all_tags($atrrTooltip) . '">' . $label . '</span>';
					}
					// ignore dimensions set
					if ($set_data->set_legend == "CEDI_DIM_WEIGHT") continue;

					$type = $this->Core->getProductUtils()->getAttrTypeByWCId($attrId);
					if ($type == "boolean") {
						$value = $value == "Yes" ? __("Yes", 'centedi-cataddon') : __("No", 'centedi-cataddon');
					}
					if ($type == "object") {
						// if we have asSet flag
						// check if attr. has attr_obj_prop_main_id (or it's cedi_id exists among other's attr_obj_prop_main_ids) == object type
						// add them all to $object_attr_sets
						if ($this->Core->getProductUtils()->checkObjectAttributeDisplayAsSet($this->Core->getProductUtils()->getCentAttrIdByWCId($attrId))) {
							// create set legend & model prop from parent object prop
							$attrId = $this->Core->getProductUtils()->getAttrIdByWCId($attrId);
							$object_attr_sets[]['Key'] = $key;
							$object_attr_sets[$attrId]['Legend'] = $label;
							$object_attr_sets[$attrId]['Attributes'][0] = array('label' => __("Model", 'centedi-cataddon'), 'value' => $value);
							continue;
						}
					}
					if ($type == "object_data") {
						$parentAttrId = $this->Core->getProductUtils()->getAttributeObjectParentIdByWCId($attrId);
						$parentAttrWCId = $this->Core->getProductUtils()->getAttrWCIdById($parentAttrId);
						if ($this->Core->getProductUtils()->checkObjectAttributeDisplayAsSet($this->Core->getProductUtils()->getCentAttrIdByWCId($parentAttrWCId))) {
							$attr_label = str_replace($this->Core->getProductUtils()->getAttrNameById($parentAttrId) . " ", "", $label);
							$object_attr_sets[$this->Core->getProductUtils()->getAttributeObjectParentIdByWCId($attrId)]['Attributes'][$key] = array('label' => $attr_label, 'value' => $value);
						}
						continue;
					}
					if ($type == "textarea") {
						$value = str_replace("¶", "<br/>", $value);
					}
					// load html for tables
					$tableType = $this->Core->getProductUtils()->getAttrTableTypeByWCId($attrId);
					if ($tableType) {
						$value = $this->Core->getProductUtils()->getAttrTableHTMLById($value);
					}
					$product_attr_sets[$set_data->set_order]['Attributes'][$key] = array('label' => $label, 'value' => $value);
					$product_attr_sets[$set_data->set_order]['Legend'] = $set_data->set_legend;
				}

				?>
				<table class="woocommerce-product-attributes shop_attributes">
					<?php if ($product->has_weight()) { ?>
						<tr>
							<th><?php _e('Weight', 'centedi-cataddon'); ?></th>
							<td class="product_weight"><?php echo esc_html(wc_format_weight($product->get_weight())); ?></td>
						</tr> <?php }
								?>
					<?php if ($product->has_dimensions()) { ?>
						<tr>
							<th><?php _e('Dimensions', 'centedi-cataddon'); ?></th>
							<td class="product_dimensions"><?php echo esc_html(wc_format_dimensions($product->get_dimensions(false))); ?>
							</td>
						</tr> <?php }
								?>
					<?php foreach ($product_attr_sets as $set_order => $set_data) : ?>
						<tr>
							<td colspan="2">
								<h3><b><?php echo esc_attr($set_data['Legend']); ?></b></h3>
							</td>
						</tr>
						<?php foreach ($set_data['Attributes'] as $product_attribute_key => $product_attribute) : ?>
							<tr class="woocommerce-product-attributes-item woocommerce-product-attributes-item--<?php echo esc_attr($product_attribute_key); ?>">
								<th class="woocommerce-product-attributes-item__label">
									<?php echo htmlspecialchars_decode(wp_kses_post($product_attribute['label'])); ?></th>
								<td class="woocommerce-product-attributes-item__value"><?php echo wp_kses_post($product_attribute['value']); ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endforeach; ?>
					<?php foreach ($object_attr_sets as $set_id => $set_data) : ?>
						<?php if (array_key_exists("Attributes", $set_data)) : ?>
							<?php ksort($set_data['Attributes'], SORT_NATURAL); ?>
							<tr>
								<td colspan="2">
									<h3><b><?php echo esc_attr($set_data['Legend']); ?></b></h3>
								</td>
							</tr>
							<?php foreach ($set_data['Attributes'] as $product_attribute_key => $product_attribute) : ?>
								<tr class="woocommerce-product-attributes-item woocommerce-product-attributes-item--<?php echo esc_attr($product_attribute_key); ?>">
									<th class="woocommerce-product-attributes-item__label"><?php echo wp_kses_post($product_attribute['label']); ?>
									</th>
									<td class="woocommerce-product-attributes-item__value"><?php echo wp_kses_post($product_attribute['value']); ?>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					<?php endforeach; ?>
				</table>
				<?php
			}

			function add_product_files_tab($tabs)
			{
				global $product;
				$prodAttachments = $this->Core->getProductUtils()->getProductAttachmentsByWCId($product->get_id());
				$attachCount = is_array($prodAttachments) ? count($prodAttachments) : 0;
				$tabs['desc_tab'] = array(
					'title'     => __('Product attachments', 'centedi-cataddon') . " (" . $attachCount . ")",
					'priority'  => 20,
					'callback'  => array($this, 'cedi_files_tab_content')
				);
				return $tabs;
			}
			function cedi_files_tab_content()
			{
				global $product;
				$prodAttachments = $this->Core->getProductUtils()->getProductAttachmentsByWCId($product->get_id());
				$attachCount = is_array($prodAttachments) ? count($prodAttachments) : 0;
				if ($attachCount > 0) {
				?>
					<table class="cedi-table">
						<thead>
							<tr>
								<th><?php _e('Type', 'centedi-cataddon'); ?></th>
								<th><?php _e('Attachment', 'centedi-cataddon'); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($prodAttachments as $prodAttachment) : ?>
								<tr>
									<td><?php echo $prodAttachment[2] ?></td>
									<td><a href="<?php echo $prodAttachment[0] ?>" download="<?php echo $prodAttachment[1] ?>"><?php echo $prodAttachment[1] ?></a></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php
				} else {
				?>
					<tr>
						<?php _e('Nothing found', 'centedi-cataddon'); ?></th>
					</tr>
				<?php
				}
			}
			public function cedi_BrandsAddPageOverride()
			{
				ob_start();
				?>
				<div class="form-field">
					<label for="cedi-brand-image-field"><?php _e('Logo', 'centedi-cataddon'); ?></label>
					<div style="max-width:150px;height:150px; border:1px solid #ccc;">
						<img id="cedi-brand-image-field-ct" class="cedi-brand-logo-image" src="<?php echo wc_placeholder_img_src(array('150', '150')); ?>" alt="<?php _e('Add logo...', 'centedi-cataddon'); ?>" style="padding-left:5px;">
					</div>
					<input type="hidden" name="<?php echo CEDI_BRAND_IMG_FIELD_NAME; ?>" id="cedi-brand-image-hidden" value="">
					<br />
					<a href="" style="margin-right:5px" id="cedi-brand-image-field-add" name="cedi-brand-image-field" title="<?php _e('Add logo...', 'centedi-cataddon'); ?>">
						<?php _e('Add logo...', 'centedi-cataddon'); ?>
					</a> | <a href="" style="margin-left:5px" id="cedi-brand-image-field-remove" name="cedi-brand-image-field-remove" title="<?php _e('Remove logo', 'centedi-cataddon'); ?>">
						<?php _e('Remove logo', 'centedi-cataddon'); ?>
					</a>
					<br />
					<p class="description"><?php _e('Add or remove brand logo.', 'centedi-cataddon'); ?></p>
				</div>
			<?php
				echo ob_get_clean();
			}
			public function cedi_BrandsEditPageOverride($term)
			{
				$imgId = get_term_meta($term->term_id, CEDI_BRAND_IMG_FIELD_NAME, true);
				$img = wp_get_attachment_image_url($imgId, 'full');
				$img = ($img) ? $img : wc_placeholder_img_src(array('150', '150'));
				ob_start();
			?>
				<table class="form-table">
					<tr class="form-field">
						<th>
							<label for="cedi-brand-image-field"><?php _e('Logo', 'centedi-cataddon'); ?></label>
						</th>
						<td>
							<div style="max-width:150px;height:150px; border:1px solid #ccc;">
								<img id="cedi-brand-image-field-ct" class="cedi-brand-logo-image cedi-brand-logo-image-edit-page" src="<?php echo $img; ?>" alt="<?php _e('Add logo...', 'centedi-cataddon'); ?>" style="padding-left:5px;">
							</div>
							<input type="hidden" name="<?php echo CEDI_BRAND_IMG_FIELD_NAME; ?>" id="cedi-brand-image-hidden" value="<?php echo $imgId; ?>">
							<br />
							<a href="" style="margin-right:5px" id="cedi-brand-image-field-add" name="cedi-brand-image-field" title="<?php _e('Add logo...', 'centedi-cataddon'); ?>">
								<?php _e('Add logo...', 'centedi-cataddon'); ?>
							</a> | <a href="" style="margin-left:5px" id="cedi-brand-image-field-remove" name="cedi-brand-image-field-remove" title="<?php _e('Remove logo', 'centedi-cataddon'); ?>">
								<?php _e('Remove logo', 'centedi-cataddon'); ?>
							</a>
							<br />
							<p class="description"><?php _e('Add or remove brand logo.', 'centedi-cataddon'); ?></p>
						</td>
					</tr>
				</table>
<?php
				echo ob_get_clean();
			}
			public function cedi_SaveTaxonomy($term_id)
			{
				if (isset($_POST[CEDI_BRAND_IMG_FIELD_NAME])) {
					delete_term_meta($term_id, CEDI_BRAND_IMG_FIELD_NAME);
					update_term_meta($term_id, CEDI_BRAND_IMG_FIELD_NAME, esc_attr($_POST[CEDI_BRAND_IMG_FIELD_NAME]));
				}
			}
			public function cedi_TaxonomyColumnOverride($columns)
			{
				if (isset($columns['description'])) unset($columns['description']);
				$new_columns = array();
				$new_columns_after = array();
				if (isset($columns['cb'])) {
					$new_columns['cb'] = $columns['cb'];
					unset($columns['cb']);
				}
				//$new_columns['order'] = __('Order', 'centedi-cataddon');
				$new_columns['logo'] = __('Logo', 'centedi-cataddon');
				$new_columns_after['visible'] = __('Visible', 'centedi-cataddon');
				return array_merge($new_columns, $columns, $new_columns_after);
			}
			public function cedi_TaxonomyColumnManageOverride($c, $column_name, $term_id)
			{
				switch ($column_name) {
					case 'order':
						return '<div style="max-width:50px;height:50px;">1</div>';
						break;
					case 'logo':
						$img = wp_get_attachment_image_url(get_term_meta($term_id, CEDI_BRAND_IMG_FIELD_NAME, true), 'full');
						return ($img) ? '<div style="max-width:50px;height:50px;"><img class="cedi-brand-logo-image cedi-brand-logo-image-column" src="' . $img . '"</div>' : wc_placeholder_img(array('50', '50'));
						break;
					case 'visible':
						$brandTerm = get_term_by('id', $term_id, CEDI_BRAND_TAXONOMY_NAME);
						$value = get_term_meta($brandTerm->term_id, CEDI_BRAND_VISIBILITY_FIELD_NAME, true) == 1 ? 1 : 0;
						$checked = $value == 1 ? 'checked="checked"' : '';
						return '<input name="cedi_admin_brand_visibility_' . $term_id . '" id="cedi_admin_brand_visibility_' . $term_id . '" type="checkbox" class="cedi-cb-brand-listing" value="' . $value . '" ' . $checked . '>';
						break;
						// other later
				}
			}

			public function cedi_addBrandsSlider()
			{
				if (is_search()) return;
				if (!is_shop()) return;
				//echo '<div class="cedi-brands-logo-slider-ct">';
				dynamic_sidebar('cedi_brands');
				//echo '</div>';
			}
			public function cedi_overrideCategoryDisplay()
			{
				if (is_search()) return '';
				$parentid = get_queried_object_id();
				$args = array(
					'parent' => $parentid
				);
				$terms = get_terms('product_cat', $args);
				if ($terms) {
					echo '<div class="product-cats">';
					foreach ($terms as $term) {
						echo '<a href="' .  esc_url(get_term_link($term)) . '" class="' . $term->slug . '">';
						woocommerce_subcategory_thumbnail($term);
						echo '<span class="cedi-cat-name">';
						echo $term->name;
						echo '</span>';
						echo '</a>';
					}
					echo '</div>';
				}
			}
			public function cedi_addActionLinks($links)
			{
				$links[] = '<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=cedi_settings_tab')) . '">' . __('Settings', 'centedi-cataddon') . '</a>';
				return $links;
			}
		}
		$centedi_cataddon = new Centedi_Cataddon();
	}
}
?>