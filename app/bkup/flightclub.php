public function getLinks($result, $time_start){

		$host = $result["host"];			
		$guzzle = new Guzzle(["http_errors"=>false]);
		$urls = [];
		//unset($result["brands"]["nike"]);
		//unset($result["brands"]["air-jordan"]);
		//unlink(dirname(dirname(__FILE__)) . '/result/' . str_replace('www.', '', str_replace('.com', '', $host)).'-urls.json');
		//file_put_contents(dirname(dirname(__FILE__)) . '/result/' . str_replace('www.', '', str_replace('.com', '', $host)).'-urls.json', "[", FILE_APPEND);
		foreach ($result["brands"] as $key => $value) {
			
			$brand = $value["name"];
			
			$total_pages = $value["total_pages"];

			try{
				
				for($i=0; $i<=$total_pages; $i++){

					$promise = $guzzle->requestAsync('GET','https://'.$host.'/'.$brand. ($i==0?"":'/page/'.$i));
					$promise->then(
					function ($response) use ($value, $brand, $host, $i, $total_pages) {

					$data = $response->getBody()->getContents();
					$crawler = new Crawler($data);
					$href = $crawler
							->filter('.products-grid li a.product-image')
							->each(function (Crawler $nodeCrawler) {
								return $nodeCrawler->attr('href');
							});

						file_put_contents(dirname(dirname(__FILE__)) . '/result/' . str_replace('www.', '', str_replace('.com', '', $host))."-".$brand . '-urls.json', $total_pages==$i?json_encode(array("urls"=>$href)):json_encode(array("urls"=>$href)).",", FILE_APPEND);
			
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
		//file_put_contents(dirname(dirname(__FILE__)) . '/result/' . str_replace('www.', '', str_replace('.com', '', $host)).'-urls.json', "]", FILE_APPEND);
		//$content = file_get_contents(dirname(dirname(__FILE__)) . '/results/' . str_replace('www.', '', str_replace('.com', '', $host))."-urls.json");

		 exit;
		//$content = str_replace(",]", "]", $content);
		//$data = (array) json_decode($content);
		Stadiumgoods::getProductsData( $data , $host ,$time_start);
		
	}