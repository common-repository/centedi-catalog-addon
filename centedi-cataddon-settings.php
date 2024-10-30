<?php
/*
Copyright 2009-2022 CentEDI s.r.o.
*/

/**
 * @package CentEDI
 */
if (!defined('ABSPATH')) {
	exit;
}

function cedi_settings()
{
	class Cedi_Settings extends WC_Settings_Page
	{
		protected $Core;
		public function __construct()
		{
			$this->id = 'cedi_settings_tab';
			$this->label = __('CentEDI Settings', 'centedi-cataddon');
			$this->Core = new CentediCore();

			add_filter('woocommerce_settings_tabs_array', array($this, 'add_settings_page'), 20);
			add_action('woocommerce_sections_' . $this->id, array($this, 'cedi_Sections'));
			add_action('woocommerce_settings_' . $this->id, array($this, 'cedi_LoadSettingsTabs'));
			add_action('woocommerce_settings_save_' . $this->id, array($this, 'cedi_SaveSettingsTabs'));
		}
		public function get_sections()
		{
			$sections = array(
				'' 				=> __('Registration', 'centedi-cataddon'),
				'cedi-filters' 	=> __('Filters', 'centedi-cataddon'),
				'cedi-brands'	=> __('Brands', 'centedi-cataddon'),
				'cedi-seo'	=> __('SEO/structured data', 'centedi-cataddon'),
				'cedi-misc'		=> __('Miscellaneous', 'centedi-cataddon')
			);
			return apply_filters('woocommerce_get_sections_' . $this->id, $sections);
		}
		public function cedi_Sections()
		{
			global $current_section;

			$sections = $this->get_sections();
			if (empty($sections) || 1 === sizeof($sections)) {
				return;
			}

			echo '<ul class="subsubsub">';
			$array_keys = array_keys($sections);
			foreach ($sections as $id => $label) {
				echo '<li>
						<a href="' . admin_url('admin.php?page=wc-settings&tab=' . $this->id . '&section=' . sanitize_title($id)) . '" class="' . ($current_section == $id ? 'current' : '') . '">' . $label . '</a> ' . (end($array_keys) == $id ? '' : '|') .
					'</li>';
			}
			echo '</ul><br class="clear" />';
		}
		public function get_settings($current_section = '')
		{
			switch ($current_section) {
				case 'cedi-filters':
					$settings = $this->getCediFiltersContent();
					break;
				case 'cedi-brands':
					$settings = $this->getCediBrandsContent();
					break;
				case 'cedi-seo':
					$settings = $this->getCediSeoContent();
					break;
				case 'cedi-misc':
					$settings = $this->getCediMiscContent();
					break;
				default:
					$settings = $this->getCediGeneralContent();
					break;
			}
			return apply_filters('woocommerce_get_settings_' . $this->id, $settings, $current_section);
		}
		private function getCediFiltersContent()
		{
			$settings = apply_filters('cedi_admin_settings_tab_filters', array(
				'section_title' => array(
					'name' => __('Filters', 'centedi-cataddon'),
					'type' => 'title',
					'desc' => '',
					'id' => 'cedi_admin_settings_tab_filters_title'
				),
				'filters_enabled' => array(
					'name' => __('Show filters', 'centedi-cataddon'),
					'type' => 'checkbox',
					'default' => 'yes',
					'id' => 'cedi_admin_settings_tab_filters_cb_enabled',
					'class' => 'cedi_cb_ios force-hidden'
				)/*,
				'filters_show_cedi_only' => array(
					'name' => __('Show CentEDI attributes only', 'centedi-cataddon'),
					'type' => 'checkbox',
					'default' => 'yes',
					'id' => 'cedi_admin_settings_tab_filters_cb_show_cedi_only',
					'class' => 'cedi_cb_ios force-hidden'
				)*/,
				'filters_show_filterable_only' => array(
					'name' => __('Show filterable attributes only', 'centedi-cataddon'),
					'type' => 'checkbox',
					'default' => 'yes',
					'id' => 'cedi_admin_settings_tab_filters_cb_show_filterable_only',
					'class' => 'cedi_cb_ios force-hidden'
				),
				'filters_manual_attrubute_filtering' => array(
					'name' => __('Manually choose attributes to filter', 'centedi-cataddon'),
					'type' => 'checkbox',
					'default' => 'no',
					'id' => 'cedi_admin_settings_tab_filters_cb_manual_attribute_filters',
					'class' => 'cedi_cb_ios force-hidden'
				),
				'section_end' => array(
					'type' => 'sectionend',
					'id' => 'cedi_admin_settings_tab_filters_end'
				)
			));
			return $settings;
		}
		private function getCediBrandsContent()
		{
			$settings = apply_filters('cedi_admin_settings_tab_brands', array(
				'section_title' => array(
					'name' => __('Brands', 'centedi-cataddon'),
					'type' => 'title',
					'desc' => '',
					'id' => 'cedi_admin_settings_tab_brands_title'
				),
				'brands_enabled' => array(
					'name' => __('Show brands slider', 'centedi-cataddon'),
					'type' => 'checkbox',
					'default' => 'yes',
					'id' => 'cedi_admin_settings_tab_brands_cb_enabled',
					'class' => 'cedi_cb_ios force-hidden'
				),
				'section_end' => array(
					'type' => 'sectionend',
					'id' => 'cedi_admin_settings_tab_brands_end'
				)
			));
			return $settings;
		}
		private function getCediSeoContent()
		{
			$settings = apply_filters('cedi_admin_settings_tab_seo', array(
				'section_title' => array(
					'name' => __('SEO&structured data', 'centedi-cataddon'),
					'type' => 'title',
					'desc' => '',
					'id' => 'cedi_admin_settings_tab_seo_title'
				),
				'sitemap_enabled' => array(
					'name' => __('Enable XML sitemap', 'centedi-cataddon'),
					'type' => 'checkbox',
					'default' => 'no',
					'id' => 'cedi_admin_settings_tab_seo_enable_cedi_sitemap_cb_enabled',
					'class' => 'cedi_cb_ios force-hidden'
				),
				'sitemap_index_name' => array(
					'name' => __('Sitemap file name', 'centedi-cataddon'),
					'type' => 'text',
					'default' => 'sitemap.xml',
					'id' => 'cedi_admin_settings_tab_seo_custom_xml_index'
				),
				'structured_data_enabled' => array(
					'name' => __('Enable structured data for products', 'centedi-cataddon'),
					'type' => 'checkbox',
					'default' => 'no',
					'id' => 'cedi_admin_settings_tab_seo_enable_cedi_structured_data_cb_enabled',
					'class' => 'cedi_cb_ios force-hidden'
				),
				'meta_descr_enabled' => array(
					'name' => __('Enable meta descriptions', 'centedi-cataddon'),
					'type' => 'checkbox',
					'default' => 'no',
					'id' => 'cedi_admin_settings_tab_seo_enable_metadescr_cb_enabled',
					'class' => 'cedi_cb_ios force-hidden'
				),
				'meta_descr_homepage' => array(
					'name' => __('Homepage meta description', 'centedi-cataddon'),
					'type' => 'text',
					'default' => '',
					'id' => 'cedi_admin_settings_tab_seo_metadescr_homepage_field'
				),
				'section_end' => array(
					'type' => 'sectionend',
					'id' => 'cedi_admin_settings_tab_brands_end'
				)
			));

			// TODO: if this checkbox is enabled, place div with google richsnippet preview near #cedi_admin_settings_tab_seo_enable_cedi_structured_data_cb_enabled
			if (get_option('cedi_admin_settings_tab_seo_enable_cedi_structured_data_cb_enabled') == 'yes')
				echo '
					<script>
						var seoPreviewCt = document.querySelector(".cedi-seo-tab-grs-preview-ct");
						if(seoPreviewCt){
							seoPreviewCt.parentNode.removeChild(seoPreviewCt);
						}
					</script>
					<div class="cedi-seo-tab-grs-preview-ct">
						<div class="cedi-seo-tab-grs-preview-top">
							<a class="cedi-seo-tab-grs-preview-top-a">
								<div class="cedi-seo-tab-grs-preview-top-a-breadcrumb">
									<img class="cedi-seo-tab-grs-preview-top-a-breadcrumb-img" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAABuklEQVR4AYWTA69cQRhAt1bU31HbdhvVtm1HtW3btm3bQY3VM67mdCZvZ42T3Ds+39AVjRCiquM4m+T3RX5m4Pssv41AZVcigNKy0zZSoOQySKl4g+8ACMMg9+g+/MN742lbH3eruviH9CDn0G7VpiW3IiQ6suP+i39gN9zNasT90qeMQqOWFFyzjuwb0EV1xNOmHtlb12G+foH59hXZ29bj69MB+8c3oqisom8CsF6sxtuhFu6WteWgl/EWH28/1ivBFwD7UQ2M06XJ3TsSUqMFH5XABLCul8G6WgzhPU84TedlE/3NPJynBUZIcK10gcBzLqVg8v5IwWeAjLvl8V0ty8lXy0jE7OP5KMGyc/la8MEVuGE8e7+I1ofbUftwF5673xHNx98OLRcUzODOR1sL1rqAygCGbdL1wniqH+xI3cPdWPVyN0//vZWy92x5e5i2ezbRZH46Q7flIgQKmYqK+iJtAviX46VbQBLv63ZiOX/ThY6+LvwBlVLXE8BwTA58Okvvy1Ooe6QbajbdL05g27uj5FrBtd8ASqqx0ZJNpEBF1oPjAlRWN0x+H+VnBL4P8lsbXHMY/wFxRv0mPHK4mAAAAABJRU5ErkJggg==" aria-hidden="true" width="16" height="16">
									<span class="cedi-seo-tab-grs-preview-top-a-breadcrumb-span"><span>' . home_url() . '</span><span style="color: #70757a;"> › ' . __("Rich snippet preview", "centedi-cataddon") . '</span></span>
								</div>
								<div class="cedi-seo-tab-grs-preview-top-a-descr" aria-level="3" role="heading">
									<div class="cedi-seo-tab-grs-preview-top-a-descr-str">
										PRODUCT_NAME_GOES_HERE – Anysto Local
									</div>
								</div>
							</a>
						</div>
						<div class="cedi-seo-tab-grs-preview-bottom">
							<span>Meta description goes here (if enabled). Meta description goes here (if enabled). Meta description goes here (if enabled). Meta description goes here (if enabl...</span>
							<div style="margin-top:8px"></div>
							<div class="cedi-seo-tab-grs-preview-bottom-sub-ct">
								<div class="cedi-seo-tab-grs-preview-bottom-sub-ct-rating">
									<div class="cedi-seo-tab-grs-preview-bottom-sub-ct-text">
										' . __("Rating") . '
									</div>
									<div style="margin-top:4px"></div>
									<div class="cedi-seo-tab-grs-preview-bottom-sub-ct-text">
										<span style="display: inline-block;color:#70757a;"> 
											<span aria-hidden="true">
												5,0
											</span>
											<div class="cedi-seo-tab-grs-preview-star" aria-label="Рейтинг: 5.0 из 5" role="img">
												<span class="cedi-seo-tab-grs-preview-star"></span>
											</div>
											<span>
												(1)
											</span>
										</span>
									</div>
								</div>
								<div class="cedi-seo-tab-grs-preview-bottom-sub-ct-price">
									<div class="cedi-seo-tab-grs-preview-bottom-sub-ct-text">
										' . __("Price") . '
									</div>
									<div style="margin-top:4px"></div>
									<div class="cedi-seo-tab-grs-preview-bottom-sub-ct-text" style="color:#70757a;">
										16,00&nbsp;' . get_woocommerce_currency() . '
									</div>
								</div>
								<div class="cedi-seo-tab-grs-preview-bottom-sub-ct-avail">
									<div class="MUxGbd lyLwlc aLF0Z">
										' . __("Availability") . '
									</div>
								<div style="margin-top:4px"></div>
								<div class="cedi-seo-tab-grs-preview-bottom-sub-ct-text" style="color:#70757a;">
									' . __("In stock") . '
								</div>
								</div>
							</div>
						</div>
					 </div>';


			return $settings;
		}
		private function getCediMiscContent()
		{
			$settings = apply_filters('cedi_admin_settings_tab_misc', array(
				'section_title' => array(
					'name' => __('Miscellaneous', 'centedi-cataddon'),
					'type' => 'title',
					'desc' => '',
					'id' => 'cedi_admin_settings_tab_misc_title'
				),
				'misc_img_import_cfg' => array(
					'name' => __('Image format', 'centedi-cataddon'),
					'type' => 'select',
					'id' => 'cedi_admin_settings_tab_misc_img_import_cfg_combo',
					'options' => array(
						'' => ''
					),
					'class' => 'cedi_admin_select_img'
				),
				'misc_xmlrpc_disabled' => array(
					'name' => __('Disable xmlrpc/pingback', 'centedi-cataddon'),
					'desc' => __('You can enable this option for security & performance reasons', 'centedi-cataddon') . "</br>" . __('Leave this disabled if you are using wordpress API to control your site', 'centedi-cataddon') . '',
					'type' => 'checkbox',
					'default' => 'no',
					'id' => 'cedi_admin_settings_tab_misc_xmlrpc_disabled',
					'class' => 'cedi_cb_ios force-hidden'
				),
				'misc_jsonrest_disabled' => array(
					'name' => __('Disable json/rest API', 'centedi-cataddon'),
					'desc' => __('You can enable this option for security & performance reasons', 'centedi-cataddon') . "</br>" . __('Leave this disabled if you are using REST API on your site', 'centedi-cataddon') . '',
					'type' => 'checkbox',
					'default' => 'no',
					'id' => 'cedi_admin_settings_tab_misc_json_disabled',
					'class' => 'cedi_cb_ios force-hidden'
				),
				'misc_show_variations_as_product_enabled' => array(
					'name' => __('Show variations as single product', 'centedi-cataddon'),
					'desc' => __('Show variations as single product on shop etc pages', 'centedi-cataddon'),
					'type' => 'checkbox',
					'default' => 'no',
					'id' => 'cedi_admin_settings_tab_misc_show_variations_enable',
					'class' => 'cedi_cb_ios force-hidden'
				),
				'misc_show_variations_parent_enabled' => array(
					'name' => __('Hide parent product for variations', 'centedi-cataddon'),
					'desc' => __('If previous option enabled, Hide parent product for the variations', 'centedi-cataddon'),
					'type' => 'checkbox',
					'default' => 'no',
					'id' => 'cedi_admin_settings_tab_misc_hide_variations_parent_enable',
					'class' => 'cedi_cb_ios force-hidden'
				),
				'section_end' => array(
					'type' => 'sectionend',
					'id' => 'cedi_admin_settings_tab_misc_end'
				)
			));
			require_once('centedi-cataddon-utils.php');
			$CentediUtils = new CentediUtils();
			echo '<script>
				var CEDI_BASE_URL="' . $CentediUtils->getUrl() . '";
				var CEDI_ORG="' . $CentediUtils->getOrganization() . '";
				var CEDI_CURRENT_IMG_CFG="' . get_option(CEDI_IMG_CFG_FIELD_NAME) . '";
			</script>';

			return $settings;
		}
		private function getCediGeneralContent()
		{
			$settings = [];
			require_once(plugin_dir_path(__FILE__) . 'centedi-cataddon-register.php');
			return $settings;
		}
		public function cedi_loadSettingsTabs()
		{
			global $current_section;

			$settings = $this->get_settings($current_section);
			WC_Admin_Settings::output_fields($settings);
			if ('cedi-filters' == $current_section) {
				$manualSettingsDisplayMode = get_option('cedi_admin_settings_tab_filters_cb_manual_attribute_filters') == 'yes' ? 'block' : 'none';
?>
				<div id='cedi_manual_filters_list' style='display: <?php echo $manualSettingsDisplayMode; ?>;'>
					<?php
					$prodCats = get_terms(
						'product_cat',
						array('hide_empty' => false)
					);
					foreach ($prodCats as $prodCat) { ?>
						<ul class="cedi-options-list cedi-accordion" style='display:none;'>
							<li class="toggle cedi-accordion-toggle">
								<span class="cedi-icon-plus" />
								<a class="cedi-options-link" href="javascript:void(0);"><?php echo $prodCat->name; ?></a>
							</li>
							<ul class="cedi-accordion-content">
								<table style="max-width:500px;" class="wc_gateways widefat" cellspacing="0">
									<thead>
										<tr>
											<th style="min-width:300px;"><b><?php _e('Attribute', 'centedi-cataddon'); ?></b></th>
											<th style="max-width:100px;"><b><?php _e('Filterable', 'centedi-cataddon'); ?></b></th>
										</tr>
									</thead>
									<tbody class="ui-sortable">
										<?php
										$attrs = $this->Core->getProductUtils()->getCategoryAttributes($prodCat->slug);
										foreach ($attrs as $attrId => $attrLabel) {
											// if filterable - enable slider, if not - disable
										?>
											<tr>
												<td><?php echo $attrLabel; ?></td>
												<td>
													<input name="<?php echo 'cediattr_' . $attrId . "_" . $prodCat->term_id; ?>" id="<?php echo 'cediattr_' . $attrId . "_" . $prodCat->term_id . '_hidden'; ?>" type="hidden" value="<?php echo $this->Core->getProductUtils()->isAttrFilterableByWCId($attrId, array($prodCat->slug)) ? '1' : '0'; ?>">
													<input name="<?php echo 'cediattr_' . $attrId . "_" . $prodCat->term_id; ?>" id="<?php echo 'cediattr_' . $attrId . "_" . $prodCat->term_id; ?>" type="checkbox" class="cedi_cb_ios" value="1" <?php echo $this->Core->getProductUtils()->isAttrFilterableByWCId($attrId, array($prodCat->slug)) ? 'checked="checked"' : ''; ?>>
												</td>
											</tr>
										<?php
										}
										?>
									</tbody>
								</table>
							</ul>
						</ul>
					<?php
					}
					?>
				</div>
			<?php
			}
		}
		public function cedi_SaveSettingsTabs()
		{
			global $current_section;
			$settings = $this->get_settings($current_section);
			WC_Admin_Settings::save_fields($settings);
			//print_r($_POST);
			if (isset($_POST['cedi_admin_settings_tab_filters_cb_manual_attribute_filters'])) {
				if ($_POST['cedi_admin_settings_tab_filters_cb_manual_attribute_filters'] == 1) {
					$filterableAttrs = [];
					foreach ($_POST as $paramName => $paramVal) {
						if (strpos($paramName, 'cediattr_') !== false) {
							$attrWithGroupArr = explode('_', $paramName);
							$filterableAttrs[$attrWithGroupArr[2]][] = array('attrWooId' => $attrWithGroupArr[1], 'value' => $paramVal);
						}
					}
					foreach ($filterableAttrs as $groupId => $attrs) {
						foreach ($attrs as $attrData) {
							$optionName = 'cedi_attr_' . $groupId . '_' . $attrData['attrWooId'];
							if (get_option($optionName) === false) {
								add_option($optionName, $attrData['value'], "", false);
							} else {
								update_option($optionName, $attrData['value']);
							}
						}
					}
				}
			}
			// image format for import
			if (isset($_POST[CEDI_IMG_CFG_FIELD_NAME])) {
				$imgCfg = $_POST[CEDI_IMG_CFG_FIELD_NAME];
				if (get_option(CEDI_IMG_CFG_FIELD_NAME) === false) {
					add_option(CEDI_IMG_CFG_FIELD_NAME, $imgCfg, "", false);
				} else {
					$res = update_option(CEDI_IMG_CFG_FIELD_NAME, $imgCfg);
				};
			?>
				<script>
					CEDI_CURRENT_IMG_CFG = "<?php echo get_option(CEDI_IMG_CFG_FIELD_NAME) ?>";
				</script>
<?php
			}
		}
	}
	return new Cedi_Settings();
}

add_filter('woocommerce_get_settings_pages', 'cedi_settings', 15);

?>