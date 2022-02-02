<?php

namespace Scrappers\App;
use Psr\Http\Message\ServerRequestInterface as RequestInterface;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Scrappers\App\Stockx;
use Scrappers\App\Flightclub;
use Scrappers\App\Stadiumgoods;
use Scrappers\App\Urbanneccessities;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Pool;

/**
 * 
 */
class Ajax
{	

	private static $instance = null;

	public static function getInstance() {
		if( ! isset( static::$instance ) ) {
			static::$instance = new static;
		}

		return static::$instance;
	}
	
	public function getbrands( RequestInterface $request, ResponseInterface $response ): ResponseInterface {

		if (!file_exists(dirname(dirname(__FILE__)) . '/result')) {
		    mkdir(dirname(dirname(__FILE__)) . '/result', 0777, true);
		}

		if( $request->getParam( 'site' ) == "stockx.com" )
			Stockx::getBrandsAndUrl( $request->getParam( 'site' ) );

		if( $request->getParam( 'site' ) == "urbannecessities.com" ){
			Urbanneccessities::getBrands( $request->getParam( 'site' ) );
		}

		if( $request->getParam( 'site' ) == "www.stadiumgoods.com" ){
			Stadiumgoods::getBrands( $request->getParam( 'site' ) );
		}

		if( $request->getParam( 'site' ) == "www.flightclub.com" ){
			Flightclub::getUrls( $request->getParam( 'site' ) );
		}

		return $response;
	}

}