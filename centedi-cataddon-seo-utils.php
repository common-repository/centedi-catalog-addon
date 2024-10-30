<?php
/*
Copyright 2009-2022 CentEDI s.r.o.
*/
if (!defined('ABSPATH')) {
	exit;
}
class CentediSeoUtils
{
	protected $productUtils;

	public function __construct()
	{
		require_once('centedi-cataddon-product-utils.php');
		$this->productUtils = new CentediProductUtils();
	}

	public function genProductSchema($prodId)
	{
		$product = wc_get_product($prodId);


		// VARIABLE Product
		// https://www.schemaapp.com/newsletter/schema-org-variable-products-productmodels-offers/
		$existingVariations = $product->get_children();
		foreach ($existingVariations as $existingVariationId) {
			//$existingVariation=new WC_Product_Variation($existingVariationId);
			//$existingVariationsSkus[]=$existingVariation->get_sku();
			$json += $this->genSchemaById($existingVariationId);
		}
		return $json;
	}
	private function genSchemaById($prodId)
	{
		$json['@context'] = 'https://schema.org/';
		$json['@type'] = 'Product';
		$json['@id']  = 'product-' . $prodId;

		$title = get_the_title();
		if (!empty($title)) $json['name'] = $title;

		$image = wp_get_attachment_url($product->get_image_id());
		if (!empty($image)) $json['image'] = $image;

		$excerpt = get_the_excerpt();
		if (!empty($excerpt)) $json['description'] = $excerpt;

		$url = get_the_permalink();
		if (!empty($url)) $json['url'] = $url;

		$sku = $product->get_sku();
		if (!empty($sku)) {
			$json['sku'] = $sku;
			//$json['mpn'] = $sku;
		}

		$brand = $this->productUtils->getProductBrandByWcId($prodId);
		if (!empty($brand)) {
			$json['brand'] = array(
				'@type' => 'Brand',
				'name' => $brand
			);
		}

		$json['aggregateRating'] = array(
			'@type'                => 'AggregateRating',
			'ratingValue'          => '4.7',
			'reviewCount'          => '87',
		);


		$currency = get_woocommerce_currency();
		$price = $product->get_price();



		$json['offers'] = array(
			'@type'                  => 'Offer',
			'priceCurrency'          => $currency,
			'price'                  => $price != "" ? $price : 0,
			'itemCondition'          => 'http://schema.org/NewCondition',
			'availability'           => 'http://schema.org/' . $stock = ($product->is_in_stock() ? 'InStock' : 'OutOfStock')
		);


		/*if( $product->is_on_sale()) {	
			$regular_price = $product->regular_price;
			$sale_price = $product->sale_price;
			//TODO priceValidUntil
		}*/


		return json_encode($json);
	}
}
