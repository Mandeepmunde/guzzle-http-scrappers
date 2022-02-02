<?php

namespace Scrappers\App;
use Symfony\Component\DomCrawler\Crawler as Crawler;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Pool;
/**
 * 
 */
class Flightclub
{
	
	public function getUrls( $host ) {

		$time_start = microtime(true);

		$guzzle = new Guzzle(["http_errors"=>false]);
		$request = $guzzle->get( $host);
		$data = $request->getBody()->getContents();

		$crawler = new Crawler( $data );
		$brand_links = $crawler
			->filter( '.nav-scroll #nav > li > a' )
			->each( function ( Crawler $nodeCrawler ) {
				$brand_link = $nodeCrawler->attr("href");
				return $brand_link;
			} );

		$brands = [];

		foreach($brand_links as $key => $links){
			$path = parse_url($links)["path"];
			if($path == "/sneakers") break;
			$request = $guzzle->get( $links);
			$data = $request->getBody()->getContents();
			$crawler = new Crawler( $data );
			$ajaxurls = $crawler
				->filter( '.toolbar-bottom .pages-dropdown li > a.page-number' )
				->each( function ( Crawler $nodeCrawler ) {
					return $nodeCrawler->attr("data-ajax-url");
				} );

			$brands[str_replace("/", "", $path)] = array("urls" => $ajaxurls, "count"=>count($ajaxurls));
		}

		Flightclub::getAllUrls($brands, $host, $time_start);
	}

	
	public function getAllUrls( $brands , $host, $time_start) {

	//unset($brands["air-jordans"]);
	//unset($brands["yeezy"]);
	//unset($brands["adidas"]);
	
	unlink(dirname(dirname(__FILE__)) . '/result/' . str_replace('www.', '', str_replace('.com', '', $host)).'-urls.json');
	file_put_contents(dirname(dirname(__FILE__)) . '/result/' . str_replace('www.', '', str_replace('.com', '', $host)).'-urls.json', "[", FILE_APPEND);
	foreach( $brands as $brand => $value ) {

			
			$guzzle = new Guzzle(['http_errors' => false]);

			$last_page = $value["count"];
			$url = $value["urls"];

			$iterator = function () use ( $guzzle, $brand, $last_page, $host, $url) {

				$index = 1;

				while( true ) {

					if( $index > $last_page ) {
						break;
					}
					
					$request = new Request( "GET", $url[$index++], []);
					yield $guzzle
						->sendAsync( $request )
						->then( function ( Response $response ) use ( $request ) {
							return [$request, $response];
						} );
				}
			};

			$promise = \GuzzleHttp\Promise\each_limit(
				$iterator(),
				$last_page,  /// concurrency,
				function ( $result, $index ) use ($host) {
					/** @var GuzzleHttp\Psr7\Request $request */

					list( $request, $response ) = $result;

					$data = $response->getBody()->getContents();

					$record = json_decode( $data );

					$record = $record->block_product_list->html;

					$crawler = new Crawler( $data );

					$crawler
						->filter( '.products-grid li a' )
						->each( function ( Crawler $nodeCrawler ) use ($host) {

							$url = $nodeCrawler->attr('href');	
							$image = $nodeCrawler->filter(".item-container .product-image img")->attr("src");
							$title = $nodeCrawler->filter(".item-container .product-image img")->attr("title");
							$brand = $nodeCrawler->filter(".item-container h2")->text();
							
							file_put_contents(dirname(dirname(__FILE__)) . '/result/' . str_replace('www.', '', str_replace('.com', '', $host)).'-urls.json', stripslashes(json_encode( array(

									"name" => stripslashes(trim(str_replace('"', "'", $title))),
									"url" => stripslashes($url),
									"brand" => strtolower($brand),
									"image" => stripslashes($image)

								))) . ",", FILE_APPEND);
						} );

				}
			);

			$promise->wait();
		}

		file_put_contents(dirname(dirname(__FILE__)) . '/result/' . str_replace('www.', '', str_replace('.com', '', $host)).'-urls.json', "]", FILE_APPEND);
		$content = file_get_contents(dirname(dirname(__FILE__)) . '/result/' . str_replace('www.', '', str_replace('.com', '', $host))."-urls.json");
		$content = str_replace(",]", "]", $content);
		$data = (array) json_decode($content);
		
		Flightclub::getStyleId( $data , $host, $time_start);
	}

	public function getStyleId( $result , $host, $time_start) {
		
			
		$urls = [];
		foreach( $result as $key => $value ) {
			$value = (array) $value;
			$urls[] = $value["url"]; 
			$result[$key] = $value;
		}

		
		
		//$urls = array_slice($urls, 0, 10); 
		// Create the client and turn off Exception throwing.
		$guzzle = new Guzzle(["http_errors"=>false]);
		//echo "<pre>"; print_r($new_data); exit;
		$requests = function ( $urls ) use ( $guzzle , $host) {
			foreach( $urls as $key => $value ) {
				yield function () use ( $guzzle, $value , $host) {
					return $guzzle->requestAsync( "GET", $value );
				};
			}
		};

		$filename = str_replace('www.', '', str_replace('.com', '', $host));
		file_put_contents(dirname(dirname(__FILE__)) . '/result/'.$filename. '.json', "[");

		$pool = new Pool( $guzzle, $requests( $urls ), [
			'concurrency' => 10,
			'fulfilled' => function ( Response $response, $index ) use ( $urls, $host, $filename, $result ) {

				$data = $response->getBody()->getContents();

				$crawler = new Crawler( $data );

				$style = $crawler
					->filter( 'title' )
					->text();

				$style = explode(" - ", $style)[2];

						$literal = $index<count($urls)-1?",":"";
					
						file_put_contents(dirname(dirname(__FILE__)) . '/result/'.$filename. '.json', stripslashes(json_encode(array(

							"name" => stripslashes(str_replace('"', "'", $result[$index]["name"])),
							"title" => stripslashes(str_replace('"', "'", $result[$index]["name"]))." ".$style,
							"url" => stripslashes( $result[$index]["url"]),
							"style" => $style,
							"brand" => strtolower($result[$index]["brand"]),
							"image" => stripslashes( $result[$index]["image"] )

						))) . $literal, FILE_APPEND);

						echo stripslashes(json_encode(array(

							"name" => stripslashes(str_replace('"', "'", $result[$index]["name"])),
							"title" => stripslashes(str_replace('"', "'", $result[$index]["name"]))." ".$style,
							"url" => stripslashes( $result[$index]["url"]),
							"style" => $style,
							"brand" => strtolower($result[$index]["brand"]),
							"image" => stripslashes( $result[$index]["image"] )

						)));
					

			},
			'rejected' => function ( Response $response ) {
				fwrite( STDOUT, sprintf( 'Failed getting data, code was %d.', $response->getStatusCode() ) );
			},
		] );

		$promise = $pool->promise();
		$promise->wait();
		file_put_contents(dirname(dirname(__FILE__)) . '/result/'.$filename. '.json', "]", FILE_APPEND);
		$content = file_get_contents(dirname(dirname(__FILE__)) . '/result/'.$filename. '.json');
		$content = str_replace(",]", "]", $content);
		$content = str_replace("}{", "},{", $content);
		unlink(dirname(dirname(__FILE__)) . '/result/'.$filename. '.json');
		file_put_contents(dirname(dirname(__FILE__)) . '/result/'.$filename. '.json',$content);
		$time_end = microtime(true);
		if(round(number_format($time_end - $time_start, 2, '.', '')) < 60){
			echo "Time Taken to crawl " .$host." ". number_format($time_end - $time_start, 2, '.', '')." Secs";
		}else{
			echo "Time Taken to crawl " .$host." ". number_format(($time_end - $time_start)/60, 2, '.', '')." Mins";
		}
		exit;
	}

}