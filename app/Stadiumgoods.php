<?php

namespace Scrappers\App;

use Symfony\Component\DomCrawler\Crawler as Crawler;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Pool;


class Stadiumgoods{

	public function getBrands( $host ){

		$time_start = microtime(true);

		$guzzle = new Guzzle(["http_errors"=>false]);
		$request = $guzzle->get( "https://" . $host);
		$data = $request->getBody()->getContents();

		$crawler = new Crawler( $data );
		$brand_links = $crawler
			->filter( '.nav-primary li a.level0' )
			->each( function ( Crawler $nodeCrawler ) {
				$brand_link = $nodeCrawler->attr("href");
				return $brand_link;
			} );

		$brands = [];
		foreach($brand_links as $key => $links){
			$path = parse_url($links)["path"];
			if($path == "/footwear") break;
			$brands[str_replace("/", "", $path)] = $links;
		}
		Stadiumgoods::getAllUrls($brands,$host, $time_start);
	}

	public function getAllUrls($brands, $host, $time_start){
		
		$result = [];
		foreach($brands as $key => $brand){

			$guzzle = new Guzzle(["http_errors"=>false]);
			$request = $guzzle->get( $brand);
			$data = $request->getBody()->getContents();

			$crawler = new Crawler( $data );

			$pageCountText = $crawler->filter( '.category-count h5 span:nth-child(2)' )->text();
			$pageCountText = str_replace("(", "", $pageCountText);

			$pageCountText = str_replace(")", "", $pageCountText);

			$products_range = $crawler->filter( '.category-count h5 .products-range' )->text();

			$result["brands"][str_replace("/" , "", parse_url($brand)["path"])] = array(
					"name" => str_replace("/" , "", parse_url($brand)["path"]),
					"total_pages" => ceil($pageCountText/explode("-", $products_range)[1])

			);
			
		}

		$result["host"] = $host;
		Stadiumgoods::getLinks($result, $time_start);
	}

	public function getLinks($result, $time_start){
		//print_r($result); exit();
		$host = $result["host"];
		unlink(dirname(dirname(__FILE__)) . '/result/' . str_replace('www.', '', str_replace('.com', '', $host)).'-urls.json');
	file_put_contents(dirname(dirname(__FILE__)) . '/result/' . str_replace('www.', '', str_replace('.com', '', $host)).'-urls.json', "[", FILE_APPEND);
	unset($result["brands"]["nike"]);
	unset($result["brands"]["air-jordan"]);
	$guzzle = new Guzzle(["http_errors"=>false]);
	
		foreach ($result["brands"] as $key => $value) {
						
			
			$brand = $value["name"];
			$total_pages = $value["total_pages"];

				for($i=1; $i<=2; $i++){

					$promise = $guzzle->requestAsync('GET','https://'.$host.'/'.$brand. ($i==1?"":'/page/'.$i));
					$promise->then(
					function ($response) use ($value, $brand, $host, $i, $total_pages) {

					$data = $response->getBody()->getContents();
					$crawler = new Crawler($data);
					$href = $crawler
							->filter('.products-grid li a.product-image')
							->each(function (Crawler $nodeCrawler) {
								return $nodeCrawler->attr('href');
							});

						file_put_contents(dirname(dirname(__FILE__)) . '/result/' . str_replace('www.', '', str_replace('.com', '', $host)).'-urls.json', 2==$i?json_encode(array("urls"=>$href)):json_encode(array("urls"=>$href)).",", FILE_APPEND);
			
						}, function ($exception) {
							return $exception->getMessage();
						}
					);

					$promise->wait();
					
				}
		}

		file_put_contents(dirname(dirname(__FILE__)) . '/result/' . str_replace('www.', '', str_replace('.com', '', $host)).'-urls.json', "]", FILE_APPEND);
		$content = file_get_contents(dirname(dirname(__FILE__)) . '/result/' . str_replace('www.', '', str_replace('.com', '', $host))."-urls.json");
		$content = str_replace(",]", "]", $content);
		echo $content = str_replace("}{", "},{", $content);
		$data = (array) json_decode($content);
		print_r($data); exit;
		Stadiumgoods::getProductsData( $data , $host, $time_start);
		
	}


	public function getProductsData( $data, $host, $time_start) {
		
		//print_r($data); exit;
		$result = [];

		foreach( $data as $key => $value ) {
			$value = (array) $value;
			foreach( $value as $key1 => $value1) {
				$result[] = $value1; 
			}
		}
		$urls = [];
		foreach( $result as $key => $value ) {
			foreach( $value as $key => $product_url ) {
				$urls[] = $product_url; 
			}
		}

		$urls = array_values(array_unique($urls));
		
		//$urls = array_slice($urls, 0, 20); 

		// Create the client and turn off Exception throwing.

		$filename = str_replace('www.', '', str_replace('.com', '', $host));
		//unlink(dirname(dirname(__FILE__)) . '/result/'.$filename. '.json');
		file_put_contents(dirname(dirname(__FILE__)) . '/result/'.$filename. '.json', "[");

		$guzzle = new Guzzle(["http_errors"=>false]);
		//echo "<pre>"; print_r($new_data); exit;
		$requests = function ( $urls ) use ( $guzzle , $host) {
			foreach( $urls as $key => $value ) {
				yield function () use ( $guzzle, $value , $host) {
					return $guzzle->requestAsync( "GET", $value );
				};
			}
		};
		
		

		$pool = new Pool( $guzzle, $requests( $urls ), [
			'concurrency' => 10,
			'fulfilled' => function ( Response $response, $index ) use ( $urls, $host, $filename ) {

				$data = $response->getBody()->getContents();

				$crawler = new Crawler( $data );

				$name = $crawler
					->filter( '.product-name' )
					->text();
				
				$image = $crawler
					->filter( '.product-gallery-image img' )
					->attr("src");

				$product_brand = $crawler
					->filter( '.product-brand' )
					->text();

				$style = $crawler
					->filter( '#product-attribute-specs-table tr:nth-child(1) td' )
					->text();

				
						$literal = $index<count($urls)-1?",":"";
					
						file_put_contents(dirname(dirname(__FILE__)) . '/result/'.$filename. '.json', stripslashes(json_encode(array(

							"name" => stripslashes(str_replace('"', "'", $name)),
							"title" => stripslashes(str_replace('"', "'", $name))." ".$style,
							"url" => stripslashes( $urls[ $index ]),
							"style" => $style,
							"brand" => strtolower($product_brand),
							"image" => stripslashes( $image )

						))) . $literal, FILE_APPEND);

						echo stripslashes(json_encode(array(

							"name" => stripslashes($name ),
							"title" => stripslashes(str_replace('"', "'", $name))." ".$style,
							"url" => stripslashes( $urls[ $index ]),
							"style" => $style,
							"brand" => strtolower($product_brand),
							"image" => stripslashes( $image )

						)));
					

			},
			'rejected' => function ( Response $response ) {
				fwrite( STDOUT, sprintf( 'Failed getting data, code was %d.', $response->getStatusCode() ) );
			},
		] );

		$promise = $pool->promise();
		$promise->wait();
		file_put_contents(dirname(dirname(__FILE__)) . '/result/'.$filename. '.json', "]", FILE_APPEND);
		//unlink(dirname(dirname(__FILE__)) . '/result/' . str_replace('www.', '', str_replace('.com', '', $host)).'-urls.json');
		
		$time_end = microtime(true);
		if(round(number_format($time_end - $time_start, 2, '.', '')) < 60){
			echo "Time Taken to crawl " .$host." ". number_format($time_end - $time_start, 2, '.', '')." Secs";
		}else{
			echo "Time Taken to crawl " .$host." ". number_format(($time_end - $time_start)/60, 2, '.', '')." Mins";
		}
		exit;
		
	}

}