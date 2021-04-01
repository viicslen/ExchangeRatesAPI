<?php

namespace FmTod\ExchangeRatesAPI;

class Response
{
    # The actual Guzzle response:
    private $response;
    
    # Core response:
    private $headers;
    private $bodyRaw;
    private $body;
    
    # Properties:
    private $statusCode;
    private $timestamp;
    private $date;
    private $baseCurrency;
    
    private $rates;

    function __construct( \GuzzleHttp\Psr7\Response $response )
    {
        $this->response = $response;
        
        $this->headers    = $response->getHeaders();
        $this->bodyRaw    = (string) $response->getBody();
        $this->body       = json_decode( $this->bodyRaw, false );

        if (!$this->body->success) {
            throw new Exception($this->body->error->info, $this->body->error->code);
        }
        
        # Set our properties:
        $this->statusCode   = $response->getStatusCode();
        $this->timestamp    = date('c');
        $this->date         = $this->body->date;
        $this->baseCurrency = $this->body->base;
        $this->rates        = $this->body->rates;
    }
    
    /****************************/
    /*                          */
    /*         GETTERS          */
    /*                          */
    /****************************/

    # Get Guzzle response object:
    public function getRawResponse()
    {
        return $this->response;
    }

    # Get the status code:
    public function getStatusCode()
    {
        return (int) $this->statusCode;
    }

    # Get the response headers:
    public function getHeaders()
    {
        return $this->headers;
    }
    
    # Get the timestamp of the request:
    public function getTimestamp()
    {
        return $this->timestamp;
    }

    # Get the date of the rates:
    public function getDate()
    {
        return $this->date;
    }
    
    # Get the base currency:
    public function getBaseCurrency()
    {
        return $this->baseCurrency;
    }
    
    # Get the exchange rates:
    public function getRates()
    {
        # Convert the rates to a key / value array:
        return json_decode( json_encode($this->rates), true );
    }
    
    # Return a specific rate:
    public function getRate( $code = null )
    {
        $rates = $this->getRates();
        
        # If there's only one rate, and the code is null, return the first one:
        if( count($rates) == 1 && $code == null )
        {
            return reset( $rates );
        }
        
        if( $this->body->rates->{$code} )
        {
            return $this->body->rates->{$code};
        }
        
        return null;
    }
    
    # Convert the response to JSON:
    public function toJSON()
    {
        return json_encode([
            'statusCode'   => $this->getStatusCode(),
            'timestamp'    => $this->getTimestamp(),
            'baseCurrency' => $this->getBaseCurrency(),
            'rates'        => $this->getRates()
        ]);
    }
}