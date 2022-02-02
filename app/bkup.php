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
		$brands = array(
				"adidas" => "Adidas",
				/*"air jordan" => "Air Jordan",
				"nike" => "Nike",
				"other brands" => "Other Brands",*/
			);

		Stockx::getAllUrls( $brands , parse_url($site)["host"]);
		exit;
	}

	
	public function getAllUrls( $brands , $host) {

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

					if( $index > $last_page ) {
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
				$last_page,  /// concurrency,
				function ( $result, $index ) use ($host) {
					/** @var GuzzleHttp\Psr7\Request $request */

					list( $request, $response ) = $result;

					$data = $response->getBody()->getContents();

					$record = json_decode( $data );

					$record = $record->Products;

					foreach( $record as $key => $value ) {

						$value = (array) $value;

						echo json_encode(array(
							"title" => $value["title"],
							"url" => 'https://'. $host .'/'. $value["urlKey"],
							"brand" => $value["brand"],
							"color" => stripslashes( $value["colorway"] ),
							"style" => $value["styleId"],
							"image" => stripslashes( $value["media"]->imageUrl )
						));

					}

				}
			);

			$promise->wait();
		}

	}

	public function getProductsData( $data ) {

		$host = $data["host"];
		$urls = [];
		$results = [];

		foreach( $data["urls"] as $key => $value ) {
			$value = (array) $value;
			$results[] = $value;
			$urls[] = $value["url"];
		}

		$urls = array_unique( $urls );
		$new_data = [];
		foreach( $results as $key => $value ) {
			if( in_array( $value["url"], $urls ) && $key < 10 ) {
				$new_data[] = $value;
			}
		}

		// Create the client and turn off Exception throwing.
		$guzzle = new GuzzleClient();
		//echo "<pre>"; print_r($new_data); exit;
		$requests = function ( $new_data ) use ( $guzzle , $host) {
			foreach( $new_data as $key => $value ) {
				yield function () use ( $guzzle, $value , $host) {
					return $guzzle->get( $value["url"], ['http_errors' => false] );
				};
			}
		};

		file_put_contents('test.json', "[", FILE_APPEND);

		$pool = new Pool( $guzzle, $requests( $new_data ), [
			'concurrency' => 10,
			'fulfilled' => function ( Response $response, $index ) use ( $new_data, $host ) {

				$data = $response->getBody()->getContents();

				/*$crawler = new Crawler( $data );

				$sizes = $crawler
					->filter( '.list-unstyled li' )
					->each( function ( Crawler $nodeCrawler ) {
						$size = $nodeCrawler->filter( '.title' );
						$price = $nodeCrawler->filter( '.subtitle' );

						return [
							'size' => $size->count() ? $size->text() : 0,
							'price' => $price->count() ? $price->text() : 0,
						];
					} );*/

						$literal = $index<count($new_data)-1?",":"";
					
						file_put_contents('test.json', json_encode(array(

							"name" => stripslashes( $new_data[ $index ]["title"] ),
							"title" => stripslashes( $new_data[ $index ]["title"] ) . " " . $new_data[ $index ]["style"],
							"url" => stripslashes( $new_data[ $index ]["url"]),
							"brand" => $new_data[ $index ]["brand"],
							"color" => stripslashes( $new_data[ $index ]["color"] ),
							"style" => $new_data[ $index ]["style"],
							"image" => stripslashes( $new_data[ $index ]["image"] ),
									//"sizes" => $sizes,

						)) . $literal, FILE_APPEND);
						echo json_encode(array(

							"name" => stripslashes( $new_data[ $index ]["title"] ),
							"title" => stripslashes( $new_data[ $index ]["title"] ) . " " . $new_data[ $index ]["style"],
							"url" => stripslashes( $new_data[ $index ]["url"]),
							"brand" => $new_data[ $index ]["brand"],
							"color" => stripslashes( $new_data[ $index ]["color"] ),
							"style" => $new_data[ $index ]["style"],
							"image" => stripslashes( $new_data[ $index ]["image"] ),
									//"sizes" => $sizes,
						));
					

			},
			'rejected' => function ( Response $response ) {
				fwrite( STDOUT, sprintf( 'Failed getting data, code was %d.', $response->getStatusCode() ) );
			},
		] );

		$promise = $pool->promise();
		$promise->wait();
		file_put_contents('test.json', "]", FILE_APPEND);
		exit;
	}


}