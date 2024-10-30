<?php
/*
Copyright 2009-2022 CentEDI s.r.o.
*/
if (!defined('ABSPATH')) {
    exit;
}

class Centedi_Cataddon_Brands_Widget extends WP_Widget
{

    public function __construct()
    {
        $this->Core = new CentediCore();
        parent::__construct(
            __CLASS__,
            __('CentEDI Brands Slider', 'centedi-cataddon'),
            array(
                'classname' => __CLASS__,
                'description' => __('CentEDI Brands Slider', 'centedi-cataddon')
            )
        );
    }

    public function widget($args, $instance)
    {
        // if brands disabled - skip
        if (get_option('cedi_admin_settings_tab_brands_cb_enabled') == 'no') return;
        // just shop or category page for now
        if (!is_shop() && !is_product_category()) return;

        $args['instance'] = $instance;
        $args['sidebar_id'] = $args['id'];
        $args['sidebar_name'] = $args['name'];
?>
        <div class="cedi-brands-logo-slider-ct">
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
            'title' => __('CentEDI Brands Slider', 'centedi-cataddon')
        );
        $instance = wp_parse_args((array) $instance, $defaults);
        $args = array();
        $args['instance'] = $instance;
        $args['widget'] = $this;
    }
}
?>