$iterator = function () use ( $guzzle, $brand, $total_pages, $host) {

					$index = 0;

					while( true ) {

						if( $index > 3) {
							break;
						}
						
						$request = new Request( "GET", 'https://'.$host.'/'.$brand. ($index==0?"":'/page/'.$index++), []);
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
					$crawler = new Crawler($data);
					$href = $crawler
							->filter('.products-grid li a.product-image')
							->each(function (Crawler $nodeCrawler) {
								return $nodeCrawler->attr('href');
							});

						file_put_contents(dirname(dirname(__FILE__)) . '/result/' . str_replace('www.', '', str_replace('.com', '', $host)).'-urls.json', stripslashes(json_encode( array("urls"=>$href)
						)) . ",", FILE_APPEND);
				}
			);

			$promise->wait();