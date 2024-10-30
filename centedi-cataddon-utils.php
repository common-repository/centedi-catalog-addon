<?php
/*
Copyright 2009-2022 CentEDI s.r.o.
*/
if (!defined('ABSPATH')) {
	exit;
}

class CentediUtils
{
	protected $authData;
	function __construct()
	{
		$this->authData = [];
		// auth data
		$cdata = get_option('centedi_cataddon_data');
		if (strlen($cdata) > 0) {
			$data = explode("||", $cdata);
			if (count($data) > 0) $this->authData = ['org' => $data[0], 'email' => $data[1], 'APIKey' => $data[2], 'EncKey' => $data[3], 'country' => $this->getCountry(), 'locale' => $this->getLocale()];
		}
		// mu data
		$weightUnit = get_option('woocommerce_weight_unit');
		$dimUnit = get_option('woocommerce_dimension_unit');
		$wcMetricUnits = ["kg", "g", "m", "cm", "mm"];
		$this->muDataTypes = ['weight' => array_search($weightUnit, $wcMetricUnits) !== false ? "m" : "i", 'dim' => array_search($dimUnit, $wcMetricUnits) !== false ? "m" : "i"];
	}
	public function AESCBCEncrypt($string, $key)
	{
		return openssl_encrypt($string, 'AES-256-CBC', $key, OPENSSL_RAW_DATA);
	}
	public function AESCBCDecrypt($encString, $key)
	{
		return openssl_decrypt($encString, 'AES-256-CBC', $key, OPENSSL_RAW_DATA);
	}
	public function saveCediCommonData($data)
	{
		$adata = $data['org'] . "||" . $data['email'] . "||" . $data['APIKey'] . "||" . $data['EncKey'];
		update_option('centedi_cataddon_data', $adata, 'yes');
	}
	public function getCediAuthData($clientOnly = false)
	{
		if ($clientOnly) {
			return ['org' => $this->authData['org'], 'email' => $this->authData['email']];
		}
		return $this->authData;
	}
	public function getMeasureDataTypes()
	{
		return $this->muDataTypes;
	}
	public function getCediEncKey()
	{
		return is_array($this->authData) && (count($this->authData) > 0) ? $this->authData['EncKey'] : '';
	}
	public function getCediAPIKey()
	{
		return is_array($this->authData) && (count($this->authData) > 0) ? $this->authData['APIKey'] : '';
	}
	public function getOrganization()
	{
		return is_array($this->authData) && (count($this->authData) > 0) ? $this->authData['org'] : '';
	}
	public function getEmail()
	{
		return is_array($this->authData) && (count($this->authData) > 0) ? $this->authData['email'] : '';
	}
	public function getCountry()
	{
		return explode(":", get_option('woocommerce_default_country'))[0];
	}
	public function getLocale()
	{
		return get_locale();
	}
	public function getPortalUrl()
	{
		return CEDI_PORTAL_URL;
	}
	public function getUrl()
	{
		return get_site_url(); //"http://wplocal.com";
	}
	public function getServiceUrl()
	{
		return admin_url('admin-ajax.php');
	}
	public function getRegButtonHtml()
	{
		return '<p class="submit"><input id="btn_request" type="button" class="button button-primary cedi-reg-btn" value=' . __("Register") . '></p>';
	}

	// XML SITEMAP

	public function getSitemapXml($params)
	{
		switch ($params[0]) {
			case 'index':
				return $this->buildSitemapIndex();
				break;
			default:
				return $this->buildTaxonomyXml($params);
				break;
		}
		return "";
	}
	public function buildSitemapIndex()
	{
		$xml = PHP_EOL . '<sitemapindex xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/siteindex.xsd" xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
		$xml .= "\n";

		global $wpdb;
		$query = "SELECT MAX(post_modified_gmt) AS date	FROM $wpdb->posts WHERE post_status ='publish' AND post_type='product'";
		$lmdate = $wpdb->get_var($query);
		$xml .= $this->getIndexEntryXml(home_url('product-sitemap.xml'), $lmdate);

		$taxURLs = $this->getTaxURLs();
		foreach ($taxURLs as $taxURLdata) {
			$xml .= $this->getIndexEntryXml($taxURLdata['url'], $taxURLdata['modified']);
		}
		$xml .= "\n</sitemapindex>";
		return $xml;
	}
	public function buildTaxonomyXml($params)
	{
		// $params contains taxonomy name or 'product'
		//echo $params;
		switch ($params[0]) {
			case 'product_cat':
				$xml .= $this->buildCategoriesXml();
				break;
			case 'product':
				$xml .= $this->buildProductsXml($params[1]);
				break;
			default:
				$xml .= $this->buildOtherTaxXml($params[0]);
				break;
		}
		return $xml;
	}
	public function buildCategoriesXml()
	{
		$xml = PHP_EOL . '<urlset xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd http://www.google.com/schemas/sitemap-image/1.1 http://www.google.com/schemas/sitemap-image/1.1/sitemap-image.xsd" xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
		$xml .= "\n";
		require_once('centedi-cataddon-core.php');
		$Core = new CentediCore();
		$prodCats = $Core->getProductUtils()->getAllCategories();
		foreach ($prodCats as $prodCatId => $prodCatData) {
			$xml .= $this->getSitemapEntryXml($prodCatData['url'], $prodCatData['modified']);
		}
		$xml .= "\n</urlset>";
		return $xml;
	}
	public function buildProductsXml($pageNum = '')
	{
		$xml = "";

		$args = array(
			'posts_per_page' => $pageNum != '' ? CEDI_SITEMAP_MAX_PER_PAGE : -1,
			'post_type'   => 'product',
			'post_status' => 'publish',
			'orderby' => 'modified',
			'order' => 'ASC',
			'offset' => $pageNum != '' ? $pageNum * CEDI_SITEMAP_MAX_PER_PAGE : 0,
		);
		$query = new WP_Query($args);

		// check if we need to split products into pages
		if (count($query->posts) > CEDI_SITEMAP_MAX_PER_PAGE) {
			return $this->buildProductsXmlPagedIndex($query->posts);
		} else {
			$xml = PHP_EOL . '<urlset xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd http://www.google.com/schemas/sitemap-image/1.1 http://www.google.com/schemas/sitemap-image/1.1/sitemap-image.xsd" xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
			$xml .= "\n";
			$lastModifiedProductDate = '';
			foreach ($query->posts as $product) {
				$xml .= $this->getSitemapEntryXml(get_permalink($product->ID), $product->post_modified_gmt);
				$lastModifiedProductDate = $product->post_modified_gmt;
			}
			// add shop main page here?
			//$xml.=$this->getSitemapEntryXml(get_permalink(woocommerce_get_page_id('shop')),$lastModifiedProductDate);
			$xml .= "\n</urlset>";
		}
		return $xml;
	}
	private function buildProductsXmlPagedIndex($products)
	{
		$xml = PHP_EOL . '<sitemapindex xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/siteindex.xsd" xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
		$xml .= "\n";

		$pageNum = (int)ceil(count($products) / CEDI_SITEMAP_MAX_PER_PAGE);
		$pageRanges = range(0, count($products), CEDI_SITEMAP_MAX_PER_PAGE);

		$dates = [];

		foreach ($pageRanges as $index => $offset) {
			$args = array(
				'posts_per_page' => CEDI_SITEMAP_MAX_PER_PAGE,
				'offset' => $offset,
				'post_type'   => 'product',
				'post_status' => 'publish',
				'orderby' => 'modified',
				'order' => 'ASC'
			);
			$query = new WP_Query($args);
			//$dates[$index]=['offset'=>$offset."-".($offset+CEDI_SITEMAP_MAX_PER_PAGE),'product'=>$query->posts[count($query->posts)-1]->ID,'modified'=>$query->posts[count($query->posts)-1]->post_modified_gmt,'q'=>$query->request];
			$dates[$index] = $query->posts[count($query->posts) - 1]->post_modified_gmt;
		}
		//print_r($dates);

		for ($i = 0; $i < $pageNum; $i++) {
			$xml .= $this->getIndexEntryXml(home_url('product-sitemap' . $i . '.xml'), $dates[$i]);
		}
		//

		$xml .= "\n</sitemapindex>";
		return $xml;
	}
	public function buildOtherTaxXml($taxName)
	{
		$xml = PHP_EOL . '<urlset xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd http://www.google.com/schemas/sitemap-image/1.1 http://www.google.com/schemas/sitemap-image/1.1/sitemap-image.xsd" xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
		$xml .= "\n";
		$terms = get_terms([
			'taxonomy' => $taxName,
			'hide_empty' => true
		]);

		global $wp_query;
		$cediQuery = $wp_query;
		$cediQuery->set('posts_per_page', 1);
		$cediQuery->set('paged', 1);
		$cediQuery->set('orderby', 'modified');
		$cediQuery->set('order', 'DESC');

		foreach ($terms as $term) {
			$cediQuery->set('tax_query', array(
				array(
					'taxonomy' => $taxName,
					'field' => 'slug',
					'terms' => $term
				)
			));
			$the_query = new WP_Query($cediQuery->query_vars);
			$post = $the_query->posts[0];

			$xml .= $this->getSitemapEntryXml(get_term_link($term->term_id, $taxName), $post->post_modified_gmt);
		}
		$xml .= "\n</urlset>";
		return $xml;
	}


	public function getIndexEntryXml($url, $lastMod)
	{
		$res = "\t<sitemap>\n";
		$res .= "\t\t<loc>" . htmlspecialchars((esc_url_raw($url)), ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc>\n";
		if ($lastMod > 0) $res .= "\t\t<lastmod>" . date('Y-m-d\TH:i:s+00:00', strtotime($lastMod)) . "</lastmod>\n";
		$res .= "\t</sitemap>\n";
		return $res;
	}
	public function getTaxURLs()
	{
		$taxURLs = [];
		$taxonomies = [];
		$tmpTaxArr = get_taxonomies(['public' => true], 'objects');

		foreach ($tmpTaxArr as $taxonomy) {
			$taxTerms = get_terms($taxonomy->name, ['hide_empty' => true, 'fields' => 'ids']);
			if (count($taxTerms) > 0) {
				// TODO: use IGNORE_CENTEDI_TAXONOMIES_IN_XML option here!
				if (strpos($taxonomy->name, 'pa_cedi') === false) $taxonomies[$taxonomy->name] = $taxonomy;
			}
		}
		global $wpdb;
		foreach ($taxonomies as $name => $taxonomy) {
			$query = "SELECT MAX(post_modified_gmt) AS date	FROM $wpdb->posts WHERE post_status ='publish' AND post_type='" . $taxonomy->object_type[0] . "'";
			$lmdate = $wpdb->get_var($query);

			$taxURLs[] = [
				'url'     => home_url($name . '-sitemap.xml'),
				'modified' => $lmdate
			];
		}
		return $taxURLs;
	}
	public function getSitemapEntryXml($url, $lastMod)
	{
		$res = "\t<url>\n";
		$res .= "\t\t<loc>" . htmlspecialchars((esc_url_raw($url)), ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc>\n";
		if ($lastMod > 0) $res .= "\t\t<lastmod>" . date('Y-m-d\TH:i:s+00:00', strtotime($lastMod)) . "</lastmod>\n";
		$res .= "\t</url>\n";
		return $res;
	}
	public function makePortalRequest($params, $useKey = true)
	{
		$curlObj = curl_init();
		// default params
		$params['type'] = 'woo';
		$params['url'] = $this->getUrl();
		$params['version'] = '1.0';
		if ($useKey == true) $params['key'] = $this->getCediAPIKey();

		$APIURL = CEDI_CAT_API_URL;
		if (array_key_exists('api', $params)) {
			if ($params['api'] === 'org') $APIURL = CEDI_ORG_API_URL;
			unset($params['api']);
		}

		curl_setopt_array($curlObj, array(
			CURLOPT_HEADER => 0,
			CURLOPT_USERAGENT => "CentEDI Cataddon",
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_URL => $APIURL . "?" . http_build_query($params),
		));
		$result = curl_exec($curlObj);

		curl_close($curlObj);

		$decodedResult = $this->AESCBCDecrypt(base64_decode($result), $this->getCediEncKey());

		return $decodedResult ? json_decode($decodedResult) : json_decode($result);
	}
}
