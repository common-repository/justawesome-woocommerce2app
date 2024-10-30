<?php
/*
Plugin Name: JustAwesome Woocommerce2App
Plugin URI: https://www.justawesome.de
Description: Mobile Api for app deployment of JustAwesome Webdesign
Author: Matthias Graffe
Author URI: https://www.justawesome.de/ueber-uns/
Text Domain: justawesome-woocommerce2app
Version: 1.0.0
*/


add_action( 'wp_head', 'justawesome_woocommerce2app_content_only');

function justawesome_woocommerce2app_content_only() {
	$isNative = false;
	if (isset($_GET['native']))
	{
		$isNative = true;
		$_SESSION['native'] = true;
	}
	if(isset($_SESSION['native']) && $_SESSION['native'])
	{
		$isNative = true;
		$_SESSION['native'] = true;
	}
	if(isset($_GET['nonative']))
	{
		$isNative = false;
		unset($_SESSION['native']);
	}		
	
	if($isNative)
	{
		?><style>
			header {
				display: none !important;
			}
			footer {
				display: none !important;
			}
			.footer {
				display: none !important;
			}
			.cc-grower {
				display: none !important;
			}
			.related-products-wrapper {
				display: none !important;
			}
			.ccw_plugin {
				display: none !important;
			}
		</style><?php
	}
}

add_action( 'rest_api_init', function () {
  register_rest_route( 'justawesome-woocommerce2app/v1', '/categories', array(
    'methods' => 'GET',
    'callback' => 'justawesome_woocommerce2app_categories',
  ) );
  
  register_rest_route( 'justawesome-woocommerce2app/v1', '/products(?:/(?P<id>\d+))?', array(
    'methods' => 'GET',
    'callback' => 'justawesome_woocommerce2app_products',
	   'args' => [
			'id'
		],
  ) );  
} );

function justawesome_woocommerce2app_categories()
{
	$returnData = array();

	$args = array(
		'taxonomy'   => "product_cat",
		'number'     => 1000,
		'orderby' 	 => 'menu_order',
		'order'      => 'ASC',
		'hide_empty' => false
	);
	$product_categories = get_terms($args);	
	foreach($product_categories as $cat)
	{
		$cat->name = html_entity_decode($cat->name);
		
		//$thumbnail_id = get_woocommerce_term_meta( $cat->term_id, 'thumbnail_id', true ); 
		//$cat->image = wp_get_attachment_url( $thumbnail_id ); 
		
		unset($cat->filter);
		unset($cat->taxonomy);
		unset($cat->description);
		unset($cat->term_group);
		unset($cat->term_taxonomy_id);
	}
	
	$returnData['categories'] = $product_categories;
	
	return $returnData;
}

function justawesome_woocommerce2app_products($request)
{	
	$returnData = array();
	$returnData['products'] = array();
	$returnData['currency'] = get_woocommerce_currency();
	
    $term = get_term_by('id', $request['id'], 'product_cat', 'ARRAY_A');	
    $args = array(
        'post_type'      => 'product',
        'posts_per_page' => 1000,
		'orderby'   	 => 'meta_value_num',
		'meta_key'  	 => 'total_sales',
		'order'      	 => 'DESC',
        'product_cat'    => $term['slug']
    );

    $loop = new WP_Query( $args );
    while ( $loop->have_posts() ) : $loop->the_post();
        global $product;
		
		$product_single['id'] = $product->get_id();
		$product_single['link'] = get_permalink();
		$product_single['title'] = html_entity_decode(get_the_title());
		$product_single['thumbnail'] = get_the_post_thumbnail_url(null,'medium');		
		$product_single['categorie'] = wc_get_product_term_ids( $product_single['id'],'product_cat' );
		$product_single['stock'] = get_post_meta($product_single['id'],'_stock_status',true);	
		
		$minVariationID = $product_single['id'];
		if($product->is_type('variable')){
			$minPrice = $product->get_price();
			
			foreach($product->get_available_variations() as $variation ){
				$variation_id = $variation['variation_id'];				
				// Prices
				$active_price = floatval($variation['display_price']); // Active price
				$regular_price = floatval($variation['display_regular_price']); // Regular Price
				if( $active_price != $regular_price ){
					$sale_price = $active_price; // Sale Price
				}
				if($active_price <= $minPrice)
				{
					$minPrice = $active_price;
					$minVariationID = $variation_id;
				}
			}
			$product_single['price'] = "ab ".number_format($minPrice,2,',','.').' €';							
		}
		else
		{	
			$product_single['price'] = number_format($product->get_price(),2,',','.').' €';						
		}
		if(get_post_meta($minVariationID,'_unit_price',true) == '')
		{
			$product_single['price_unit'] = '';
		}
		else
		{
			$unit_base = '';
			if(get_post_meta($product_single['id'],'_unit_base',true) > 1)
			{
				$unit_base = get_post_meta($product_single['id'],'_unit_base',true);
			}
			$product_single['price_unit'] = number_format(get_post_meta($minVariationID,'_unit_price',true),2,',','.').'€/'.$unit_base.get_post_meta($product_single['id'],'_unit',true);
		}
		
		$deliveryTerm = get_the_terms($product_single['id'], 'product_delivery_time');
		$product_single['delivery'] = '';
		if(isset($deliveryTerm[0]) && $deliveryTerm[0]->name != '')
		{
			$product_single['delivery'] = "Lieferzeit: ".$deliveryTerm[0]->name;
		}
			
		
		$returnData['products'][] = $product_single;
    endwhile;
    wp_reset_query();
	
	return $returnData;
}