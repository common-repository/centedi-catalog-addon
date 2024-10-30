<?php
/*
Copyright 2009-2022 CentEDI s.r.o.
*/
global $wpdb;

define('CEDI_PROD_TABLE',                $wpdb->prefix . "cedi_products");
define('CEDI_ATTR_TABLE',                $wpdb->prefix . "cedi_attributes");
define('CEDI_ATTR_HTML_TABLE',            $wpdb->prefix . "cedi_html");
define('CEDI_ATTR_SET_TABLE',           $wpdb->prefix . "cedi_attribute_sets");
define('CEDI_ATTR_ATTACHMENTS_TABLE',    $wpdb->prefix . "cedi_attachments");
define('CEDI_ATTR_RELATIONS_TABLE',        $wpdb->prefix . "cedi_attr_to_table_relations");
define('CEDI_BRANDS_TABLE',                $wpdb->prefix . "cedi_brands");
define('CEDI_GROUPS_TABLE',                $wpdb->prefix . "cedi_group_relations");
define('CEDI_PORTAL_URL',                "https://www.centedi.com/portal/");
define('CEDI_CAT_API_URL',                    "https://api.centedi.com/catalog");
define('CEDI_ORG_API_URL',                    "https://api.centedi.com/org");

define('CEDI_BRAND_TAXONOMY_NAME', 'cedi-brand');
define('CEDI_BRAND_IMG_FIELD_NAME', 'cedi_brand_image');
define('CEDI_BRAND_ORDER_FIELD_NAME', 'cedi_brand_order');
define('CEDI_BRAND_VISIBILITY_FIELD_NAME', 'cedi_brand_visible');
define('CEDI_IMG_CFG_FIELD_NAME', 'cedi_admin_settings_tab_misc_img_import_cfg_combo');


define('CEDI_SITEMAP_MAX_PER_PAGE', 50000);
