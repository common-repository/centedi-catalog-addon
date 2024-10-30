<?php
/*
Copyright 2009-2022 CentEDI s.r.o.
*/
if (!defined('ABSPATH')) {
    exit;
}
class Centedi_Category_List_Walker extends WC_Product_Cat_List_Walker
{
    public $tree_type = 'product_cat';

    public $db_fields = array(
        'parent' => 'parent',
        'id'     => 'term_id',
        'slug'   => 'slug',
    );
    public function start_el(&$output, $cat, $depth = 0, $args = array(), $current_object_id = 0)
    {
        $cat_id = intval($cat->term_id);

        $output .= '<li class="cat-item cat-item-' . $cat_id;

        if ($args['current_category'] === $cat_id) {
            $output .= ' current-cat';
        }

        if ($args['has_children'] && $args['hierarchical'] && (empty($args['max_depth']) || $args['max_depth'] > $depth + 1)) {
            $output .= ' cat-parent';
        }

        if ($args['current_category_ancestors'] && $args['current_category'] && in_array($cat_id, $args['current_category_ancestors'], true)) {
            $output .= ' current-cat-parent';
        }

        $output .= '"><a href="' . get_term_link($cat_id, $this->tree_type) . '">' . apply_filters('list_product_cats', $cat->name, $cat) . '</a>';

        if ($args['show_count']) {

            $args = array(
                'posts_per_page' => -1,
                'tax_query' => array(
                    'relation' => 'AND',
                    array(
                        'taxonomy' => 'product_cat',
                        'field' => 'slug',
                        'terms' => $cat->slug
                    ),
                ),
                'meta_query' => array(
                    'relation' => 'AND',
                    array(
                        'key' => '_price',
                        'value' => '',
                        'compare' => '!='
                    )
                ),
                'post_type' => array('product', 'product_variation'),
            );
            set_query_var('cedi_is_cat_widget_query', 1);
            $the_query = new WP_Query($args);
            $output .= ' <span class="count">(' . $the_query->post_count . ')</span>';
        }
    }
}
