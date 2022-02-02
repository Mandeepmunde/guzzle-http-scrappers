<?php

namespace Scrappers\App;
use Symfony\Component\DomCrawler\Crawler as Crawler;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Pool;
/**
 * 
 */
class Stockx
{
	
	public function getBrandsAndUrl( $site ) {

		$time_start = microtime(true);

		$brands = array(
				"adidas" => "Adidas",
				"air jordan" => "Air Jordan",
				"nike" => "Nike",
				"other brands" => "Other Brands",
			);

		Stockx::getAllUrls( $brands , $site, $time_start);
		
	}

	
	public function getAllUrls( $brands , $host, $time_start) {
		
		if(file_exists(dirname(dirname(__FILE__)))."/result/".str_replace(".com", "" , $host). '.json'){
			unlink(dirname(dirname(__FILE__)) . '/result/'.str_replace(".com", "" , $host) . '.json');	
		}
		
		file_put_contents(dirname(dirname(__FILE__)) . '/result/'.str_replace(".com", "" , $host). '.json', "[", FILE_APPEND);
		foreach( $brands as $brand => $label ) {

			$url = "https://". $host ."/api/browse?_tags=" . $brand;
			$guzzle = new GuzzleClient(['http_errors' => false]);

			$request = $guzzle->get( $url );

			$data = $request->getBody()->getContents();

			$data = json_decode( $data );

			$last_page = $data->Pagination->lastPage;

			$iterator = function () use ( $guzzle, $brand, $last_page, $host, $data ) {

				$index = 0;

				while( true ) {

					if( $index > 10 ) {
						break;
					}

					$url = 'https://'. $host .'/api/browse?_tags=' . $brand . '&productCategory='. $data->Products[0]->productCategory .'&page=' . $index++;
					$request = new Request( "GET", $url, []);
					yield $guzzle
						->sendAsync( $request )
						->then( function ( Response $response ) use ( $request ) {
							return [$request, $response];
						} );
				}
			};

			$promise = \GuzzleHttp\Promise\each_limit(
				$iterator(),
				10,  /// concurrency,
				function ( $result, $index ) use ($host) {
					/** @var GuzzleHttp\Psr7\Request $request */

					list( $request, $response ) = $result;

					$data = $response->getBody()->getContents();

					$record = json_decode( $data );

					$record = $record->Products;

					foreach( $record as $key => $value ) {

						$value = (array) $value;

						file_put_contents(dirname(dirname(__FILE__)) . '/result/'.str_replace(".com", "" , $host). '.json', stripslashes(json_encode(array(
							"name" => str_replace('"', "'", $value["title"]),
							"title" => str_replace('"', "'", $value["title"]). " " .$value["styleId"],
							"url" => stripslashes('https://'. $host .'/'. $value["urlKey"]),
							"brand" => $value["brand"],
							"color" => stripslashes( $value["colorway"] ),
							"style" => $value["styleId"],
							"image" => stripslashes( $value["media"]->imageUrl )
						))) . ",", FILE_APPEND);

					}

				}
			);

			$promise->wait();
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
		
		//echo $time_end - $time_start;
	}

}