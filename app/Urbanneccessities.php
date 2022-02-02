<?php

namespace Scrappers\App;

use Symfony\Component\DomCrawler\Crawler as Crawler;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Pool;


class Urbanneccessities{

	public function getBrands( $host ){

		$time_start = microtime(true);

		$guzzle = new Guzzle(["http_errors" => false]);
		$request = $guzzle->get( "https://" . $host);
		$data = $request->getBody()->getContents();

		$crawler = new Crawler( $data );
		
		$brand_links = $crawler
			->filter( '.site-nav li a' )
			->each( function ( Crawler $nodeCrawler ) {
				$brand_link = $nodeCrawler->attr("href");
				return $brand_link;
			} );

		$brands = [];

		foreach($brand_links as $key => $links){
			$path = parse_url("https://" . $host.$links)["path"];
			if($path == "/collections/more-sneakers") break;
			$brands[str_replace("/collections/", "", $path)] = "https://" . $host . $links;
		}
		
		unset($brands['/']);
		
		$brands = array(
			"air-jordan" => $brands["air-jordan"],
			"nike" => $brands["nike"],
			"adidas" => $brands["adidas"]
		);

		Urbanneccessities::getAllUrls($brands, $host, $time_start);
	}

	public function getAllUrls($brands, $host, $time_start){
		
		$result = [];
		foreach($brands as $key => $brand){

			$guzzle = new Guzzle(["http_errors" => false]);
			$request = $guzzle->get($brand);
			$data = $request->getBody()->getContents();
			
			$crawler = new Crawler( $data );

			$pageCountText = $crawler->filter( '.pagination li:nth-last-child(2)' )->text();
			
			$result["brands"][str_replace("/collections/" , "", parse_url($brand)["path"])] = array(
					"name" => str_replace("/collections/" , "", parse_url($brand)["path"]),
					"total_pages" => ceil($pageCountText)

			);
			
		}

		$result["host"] = $host;

		Urbanneccessities::getLinks($result, $time_start);
		
	}

	public function getLinks($result, $time_start){

		$host = $result["host"];

		if(file_exists(dirname(dirname(__FILE__)))."/result/".str_replace(".com", "" , $host). '.json'){
			unlink(dirname(dirname(__FILE__)) . '/result/'.str_replace(".com", "" , $host) . '.json');	
		}
		file_put_contents(dirname(dirname(__FILE__)) . '/result/'.str_replace(".com", "" , $host). '.json', "[", FILE_APPEND);		
		$guzzle = new Guzzle(["http_errors" => false]);
		$urls = [];
		
		foreach ($result["brands"] as $key => $value) {
			
			$brand = $value["name"];
			
			$total_pages = $value["total_pages"];

			try{
				
				for($i=0; $i<=$total_pages; $i++){

					$promise = $guzzle->requestAsync('GET','https://'.$host.'/collections/'.$brand. ($i==0?"":'?page='.$i));
					$promise->then(
					function ($response) use ($value, $brand, $host, $filename) {

					$data = $response->getBody()->getContents();
					$crawler = new Crawler($data);
						$crawler
							->filter('.products-grid div.product figure')
							->each(function (Crawler $nodeCrawler) use ($host, $brand, $filename) {
								$title = $nodeCrawler->filter('figcaption .product-title a')->text();
								$url = $nodeCrawler->filter('.image-table .image-cell a')->attr("href");
								$image = $nodeCrawler->filter('.image-table .image-cell a img')->attr("src");
								$style = trim(end(explode(" - ", str_replace('"', "'", $title))));
								
								if($style == str_replace('"', "'", $title)){
									$style = trim(end(explode("- ", str_replace('"', "'", $title))));
									$style = $style == str_replace('"', "'", $title)?null:$style;
								}

								file_put_contents(dirname(dirname(__FILE__)) . '/result/'.str_replace(".com", "" , $host). '.json', stripslashes(json_encode( array(

									"name" => stripslashes(trim(str_replace('"', "'", $title))),
									"url" => stripslashes( "https://" . $host .$url),
									"style" => stripslashes($style),
									"brand" => strtolower($brand),
									"image" => stripslashes( $image )

								))) . ",", FILE_APPEND);
							});
			
						}, function ($exception) {
							return $exception->getMessage();
						}
					);

					$promise->wait();
					
				}

			} catch (Exception $e){
			    echo "Something went wrong";
			}

		}
		file_put_contents(dirname(dirname(__FILE__)) . '/result/'.str_replace(".com", "" , $host) . '.json', "]", FILE_APPEND);
		$data = file_get_contents(dirname(dirname(__FILE__)) . '/result/'.str_replace(".com", "" , $host) . '.json');
		$data = str_replace(",]", "]", $data);
		unlink(dirname(dirname(__FILE__)) . '/result/'.str_replace(".com", "" , $host) . '.json');
		file_put_contents(dirname(dirname(__FILE__)) . '/result/'.str_replace(".com", "" , $host) . '.json', $data);
		
		$time_end = microtime(true);
		if(round(number_format($time_end - $time_start, 2, '.', '')) < 60){
			echo "Time Taken to crawl " .$host." ". number_format($time_end - $time_start, 2, '.', '')." Secs";
		}else{
			echo "Time Taken to crawl " .$host." ". number_format(($time_end - $time_start)/60, 2, '.', '')." Mins";
		}
	}

}