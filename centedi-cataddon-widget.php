<?php
/*
Copyright 2009-2022 CentEDI s.r.o.
*/
if (!defined('ABSPATH')) {
    exit;
}

class Centedi_Cataddon_Widget extends WP_Widget
{

    public function __construct()
    {
        $this->Core = new CentediCore();
        parent::__construct(
            __CLASS__,
            __('CentEDI Filters', 'centedi-cataddon'),
            array(
                'classname' => __CLASS__,
                'description' => __('CentEDI Filters', 'centedi-cataddon')
            )
        );
    }

    public function widget($args, $instance)
    {
        // if filters disabled - skip
        if (get_option('cedi_admin_settings_tab_filters_cb_enabled') == 'no') return;

        // just shop or category page for now
        //if(!is_shop() && !is_product_category()) return;

        if (!is_archive()) return;
        set_query_var('cedi_is_filter_query', 1);
        $args['instance'] = $instance;
        $args['sidebar_id'] = $args['id'];
        $args['sidebar_name'] = $args['name'];

        $wc_options = wc_get_permalink_structure();
?>
        <script>
            var CEDI_REQ_ARRAY = <?php echo json_encode($_GET); ?>;
            var CEDI_IS_PERMALINK_ACTIVE = <?php echo get_option('permalink_structure', '') != '' ? 1 : 0; ?>;
            var CEDI_LOCATION_HOME = location.protocol + '//' + location.host;
            var CEDI_LOCATION_NO_PARAMS = (location.protocol + '//' + location.host + location.pathname).replace(/\page\/[0-9]+/,
                "");
            var CEDI_LOCATION_BASE = CEDI_IS_PERMALINK_ACTIVE == 0 ? (location.protocol + '//' + location.host + location.pathname +
                location.search.split('&')[0]).replace(/\page\/[0-9]+/, "") : CEDI_LOCATION_NO_PARAMS;
            var CEDI_CATEGORY_BASE = "<?php echo $wc_options['category_base']; ?>";
            var CEDI_PAGE_TYPE = "<?php echo is_shop() ? 'SHOP' : (is_product_category() ? 'CAT' : 'PROD'); ?>";
        </script>
        <div class="widget cedi-filter-widget" id="cedi-options-container">
            <?php
            if (!empty($instance['title'])) {
            ?>
                <h3 class="widget-title"><?php echo $instance['title'] ?></h3>
            <?php
            }
            ?>
            <ul class="cedi_filter_cb_group cedi-options-list cedi-accordion">
                <div class="cedi_filter_buttons_ct">
                    <button id="cedi_bn_filter_apply" style="float: left;" class="button cedi_filter_button"><?php _e('Filter', 'centedi-cataddon'); ?></button>
                    <button id="cedi_bn_filter_reset" class="button cedi_filter_button"><?php _e('Reset', 'centedi-cataddon'); ?></button>
                </div>
                <?php
                // main/shop page should display category/brands filters
                if (is_shop() && !get_query_var('product_cat') && !get_query_var(CEDI_BRAND_TAXONOMY_NAME)) {

                    $brands = $this->Core->getProductUtils()->getAllCatBrands([]);

                    if (count($brands) > 0) {
                        $isForVariations = false;
                ?>
                        <li class="toggle cedi-accordion-toggle">
                            <span class="cedi-icon-plus" />
                            <a class="cedi-options-link" href="javascript:void(0);"><?php _e('Brands', 'centedi-cataddon'); ?></a>
                        </li>
                        <ul class="cedi-accordion-content">
                            <?php
                            foreach ($brands as $brandId => $brandData) {
                                if (array_key_exists('count', $brandData)) {
                            ?>
                                    <li>
                                        <input type="checkbox" id="cedi_cb_brand_<?php echo $brandId; ?>" class="cedi_filter_cb" name="<?php echo $brandData['slug']; ?>" data-taxonomy="cedi-brand" data-brand-id="<?php echo $brandId; ?>" data-brand-url="<?php echo $brandData['url']; ?>" value="<?php echo $brandData['slug']; ?>">
                                        <label class="cedi_filter_cb_label " for="cedi_cb_<?php echo $brandId; ?>"><?php echo $brandData['name'] . ' (' . (!$isForVariations ? $brandData['count'] : $the_query->post_count) . ')'; ?></label>
                                    </li>
                            <?php
                                }
                            } ?>
                        </ul>
                    <?php
                    }
                }
                // CATEGORY-SPECIFIC FILTERS
                if (get_query_var('product_cat') || get_query_var(CEDI_BRAND_TAXONOMY_NAME)) {
                    $cats = get_query_var('product_cat') != '' ? explode(",", get_query_var('product_cat')) : '';
                    $brands = $this->Core->getProductUtils()->getAllCatBrands($cats);
                    $selectedBrands = get_query_var(CEDI_BRAND_TAXONOMY_NAME) != "" ? explode(",", get_query_var(CEDI_BRAND_TAXONOMY_NAME)) : null;
                    // in case of filtered result
                    if (count($brands) > 0) {
                        $isForVariations = false;
                    ?>
                        <div class="cedi_filter_ct cedi_filter_first">
                            <li class="toggle cedi-accordion-toggle">
                                <span class="cedi-icon-plus" />
                                <a class="cedi-options-link" href="javascript:void(0);"><?php _e('Brands', 'centedi-cataddon'); ?></a>
                            </li>
                            <ul class="cedi-accordion-content">
                                <?php
                                foreach ($brands as $brandId => $brandData) { ?>
                                    <li>
                                        <input type="checkbox" id="cedi_cb_brand_<?php echo $brandId; ?>" class="cedi_filter_cb" name="<?php echo $brandData['slug']; ?>" data-taxonomy="cedi-brand" data-brand-id="<?php echo $brandId; ?>" data-brand-url="<?php echo $brandData['url']; ?>" value="<?php echo $brandData['slug']; ?>">
                                        <label class="cedi_filter_cb_label " for="cedi_cb_<?php echo $brandId; ?>"><?php echo $brandData['name'] . ' (' . (!$isForVariations ? $brandData['count'] : $the_query->post_count) . ')'; ?></label>
                                    </li>
                                <?php
                                } ?>
                            </ul>
                        </div>
                        <?php
                    }

                    // check if current group has no children
                    $showAttrFilters = true;
                    global $wp_query;
                    $catTax = get_query_var('product_cat');
                    $brandTax = get_query_var(CEDI_BRAND_TAXONOMY_NAME);
                    if ($catTax) {
                        $term  = get_term_by('slug', $catTax, 'product_cat');
                        $childCats = get_term_children($term->term_id, 'product_cat');
                        if (count($childCats) > 0)    $showAttrFilters = false;
                    }
                    if ($brandTax) {
                        if (!$catTax) $showAttrFilters = false;
                    }

                    if ($showAttrFilters) {
                        $attrs = $this->Core->getProductUtils()->getAllCategoriesAttributes($cats, $selectedBrands);
                        foreach ($attrs as $attrId => $attrData) {
                            // skip non-filterable & empty attrs
                            // disabled option for now
                            //$showCediOnly=(get_option('cedi_admin_settings_tab_filters_cb_show_cedi_only')!='no');
                            $showFilterableOnly = (get_option('cedi_admin_settings_tab_filters_cb_show_filterable_only') != 'no');
                            if (!$this->Core->getProductUtils()->isAttrAllowedForFiltersByWCId($attrData['wcid'], false, $showFilterableOnly, $cats)) continue;
                            if (count($attrData['terms']) == 0) continue;
                        ?>
                            <div class="cedi_filter_ct">
                                <li class="toggle cedi-accordion-toggle">
                                    <span class="cedi-icon-plus" />
                                    <a class="cedi-options-link" href="javascript:void(0);"><?php echo $attrData['name']; ?></a>
                                </li>
                                <ul class="cedi-accordion-content">
                                    <?php
                                    if ($attrData['type'] == 'checkbox') {
                                        $idCounter = 1;
                                        asort($attrData['terms'], SORT_NATURAL);
                                        foreach ($attrData['terms'] as $termSlug => $termVal) {
                                    ?>
                                            <li>
                                                <input type="<?php echo $attrData['type']; ?>" id="cedi_cb_attr_<?php echo $attrId . "_" . $idCounter; ?>" class="cedi_filter_cb" name="<?php echo $termVal; ?>" data-taxonomy="product_attr" data-attr-id="<?php echo $attrId; ?>" value="<?php echo $termSlug; ?>">
                                                <label class="cedi_filter_cb_label " for="cedi_cb_<?php echo $attrId . "_" . $idCounter; ?>"><?php echo $termVal . ' (' . $attrData[$termSlug]['count'] . ')'; ?></label>
                                            </li>
                                        <?php
                                            $idCounter++;
                                        }
                                    } else {
                                        ?>
                                        <li>
                                            <input type="<?php echo $attrData['type']; ?>" id="cedi_cb_attr_<?php echo $attrId . "-1"; ?>" class="cedi_filter_cb" name="<?php echo $attrId . '_radio'; ?>" data-taxonomy="product_attr" data-attr-id="<?php echo $attrId; ?>" value="Yes">
                                            <label class="cedi_filter_cb_label " for="cedi_cb_<?php echo $attrId . "-1"; ?>"><?php echo _e('Yes', 'centedi-cataddon'); ?></label>
                                        </li>
                                        <li>
                                            <input type="<?php echo $attrData['type']; ?>" id="cedi_cb_attr_<?php echo $attrId . "-2"; ?>" class="cedi_filter_cb" name="<?php echo $attrId . '_radio'; ?>" data-taxonomy="product_attr" data-attr-id="<?php echo $attrId; ?>" value="No">
                                            <label class="cedi_filter_cb_label " for="cedi_cb_<?php echo $attrId . "-2"; ?>"><?php echo _e('No', 'centedi-cataddon'); ?></label>
                                        </li>
                                        <li>
                                            <input type="<?php echo $attrData['type']; ?>" id="cedi_cb_attr_<?php echo $attrId . "-3"; ?>" class="cedi_filter_cb" name="<?php echo $attrId . '_radio'; ?>" data-taxonomy="product_attr" data-radio-all="1" data-attr-id="<?php echo $attrId; ?>" value="All">
                                            <label class="cedi_filter_cb_label " for="cedi_cb_<?php echo $attrId . "-3"; ?>"><?php echo _e('All', 'centedi-cataddon'); ?></label>
                                        </li>
                                    <?php
                                    } ?>
                                </ul>
                            </div>
                    <?php }
                    }
                }
                if (is_product()) { ?>
                    <li><?php echo 'product'; ?></li>
                <?php } ?>
                <div class="cedi_filter_buttons_ct cedi_filter_buttons_ct_bottom" hidden>
                    <button id="cedi_bn_filter_apply" style="float: left;" class="button cedi_filter_button"><?php _e('Filter', 'centedi-cataddon'); ?></button>
                    <button id="cedi_bn_filter_reset" class="button cedi_filter_button"><?php _e('Reset', 'centedi-cataddon'); ?></button>
                </div>
            </ul>
        </div>
    <?php

    }
    public function update($new_instance, $old_instance)
    {
        $instance = $old_instance;
        $instance['title'] = $new_instance['title'];
        return $instance;
    }
    public function form($instance)
    {
        $defaults = array(
            'title' => ''
        );
        $instance = wp_parse_args((array) $instance, $defaults);
        $args = array();
        $args['instance'] = $instance;
        $args['widget'] = $this;
    ?>
        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Filter title', 'centedi-cataddon') ?>:</label>
            <input class="widefat" type="text" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" value="<?php echo $instance['title']; ?>" />
        </p>
<?php
    }
}
?>