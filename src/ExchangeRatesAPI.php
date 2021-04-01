<?PHP

/**
 * A simple SDK wrapper for the ExchangeRatesAPI (https://exchangeratesapi.io/)
 * Written by Ben Major (https://github.com/benmajor)
 *
 * @link      https://github.com/benmajor/PHP-ExchangeRatesAPI
 * @copyright Copyright (c) 2019 Ben Major
 * @license   https://github.com/benmajor/PHP-ExchangeRatesAPI/blob/master/LICENSE (MIT License)
 */

namespace FmTod\ExchangeRatesAPI;

class ExchangeRatesAPI
{
    # Fetch date
    private $fetchDate;

    # Date from which to request historic rates:
    private $dateFrom;

    # Date to which to request historic rates:
    private $dateTo;

    # The base currency (default is EUR):
    private $baseCurrency;

    # Exchange rates to fetch
    private $rates = [ ];

    # Contains our Guzzle client:
    private $client;

    # API access key
    private $accessKey;

    # The http Etag header for retrieving new rates
    private $etag;

    # The modified since header for retrieving new rates
    private $modifiedSince;

    # The URL of the API:
    private $apiURL = 'https://api.exchangeratesapi.io/v1/';

    # Regular Expression for the date:
    private $dateRegExp = '/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/';

    # Regular Expression for the modified since header:
    private $modifiedSinceRegExp = '/^[A-za-z]{3}, (0[1-9]|[1-2]\d|3[0-1]) [A-za-z]{3} \d{4} \d{2}:\d{2}:\d{2} [A-za-z]{3}$/';

    # Regular Expression for the currency:
    private $currencyRegExp = '/^[A-Z]{3}$/';

    # Supported currencies:
    private $_currencies = [
        'USD', 'GBP', 'EUR', 'JPY', 'BGN', 'CZK', 'DKK', 'HUF', 'PLN', 'RON',
        'SEK', 'CHF', 'ISK', 'NOK', 'HRK', 'RUB', 'TRY', 'AUD', 'BRL', 'CAD',
        'CNY', 'HKD', 'IDR', 'ILS', 'INR', 'KRW', 'MXN', 'MYR', 'NZD', 'PHP',
        'SGD', 'THB', 'ZAR'
    ];

    # Error messages:
    private $_errors = [
        'format.invalid_etag'          => 'The specified etag and modified date combination is invalid.',
        'format.invalid_date'          => 'The specified date is invalid. Please use ISO 8601 notation (e.g. YYYY-MM-DD).',
        'format.invalid_currency_code' => 'The specified currency code is invalid. Please use ISO 4217 notation (e.g. EUR).',
        'format.unsupported_currency'  => 'The specified currency code is not currently supported.',
        'format.invalid_amount'        => 'Conversion amount must be specified as a numeric value.',
        'format.invalid_rounding'      => 'Rounding precision must be specified as a numeric value.'
    ];

    function __construct($accessKey = null)
    {
        $this->client = new \GuzzleHttp\Client([ 'base_uri' => $this->apiURL ]);
        $this->setAccessKey($accessKey);
    }

    /****************************/
    /*                          */
    /*         GETTERS          */
    /*                          */
    /****************************/

    # Get the API access key:
    public function getAccessKey()
    {
        return $this->accessKey;
    }

    # Get the fetch date date:
    public function getFetchDate()
    {
        return $this->dateFrom;
    }

    # Get the "from" date:
    public function getDateFrom()
    {
        return $this->dateFrom;
    }

    # Get the "to" date:
    public function getDateTo()
    {
        return $this->dateTo;
    }

    # Get the supported currencies:
    public function getSupportedCurrencies( string $concat = null )
    {
        return (is_null($concat)) ? $this->_currencies : implode($concat, $this->_currencies);
    }

    # Get the specified base currency:
    public function getBaseCurrency()
    {
        return (is_null($this->baseCurrency)) ? 'USD' : $this->baseCurrency;
    }

    # Get the rates:
    public function getRates( string $concat = null )
    {
        return (! is_null($concat) ) ? implode($concat, $this->rates) : $this->rates;
    }

    /****************************/
    /*                          */
    /*  SETTERS / DATA METHODS  */
    /*                          */
    /****************************/

    # Set the access key
    public function setAccessKey( string $accessKey )
    {
        $this->accessKey = $accessKey;

        return $this;
    }

    # Set the Etag headers
    public function setEtag($etag, $modifiedSince)
    {
        if ($this->validateModifiedSinceFormat($modifiedSince)) {
            $this->etag = $etag;
            $this->modifiedSince = $modifiedSince;

            return $this;
        }

        throw new Exception( $this->_errors['format.invalid_etag'] );
    }

    # remove the Etag headers
    public function removeEtag()
    {
        $this->etag = null;
        $this->modifiedSince = null;

        return $this;
    }

    # Set the fetch date
    public function setFetchDate( string $date )
    {
        if( $this->validateDateFormat($date) )
        {
            $this->fetchDate = $date;

            # Return object to preserve method-chaining:
            return $this;
        }

        throw new Exception( $this->_errors['format.invalid_date'] );
    }

    # Remove the fetch date
    public function removeFetchDate()
    {
        $this->fetchDate = null;

        return $this;
    }

    # Add a date-from:
    public function addDateFrom( string $from )
    {
        if( $this->validateDateFormat($from) )
        {
            $this->dateFrom = $from;

            # Return object to preserve method-chaining:
            return $this;
        }

        throw new Exception( $this->_errors['format.invalid_date'] );
    }

    # Remove a date-from:
    public function removeDateFrom()
    {
        $this->dateFrom = null;

        # Return object to preserve method-chaining:
        return $this;
    }

    # Add a date-to:
    public function addDateTo( string $to )
    {
        if( $this->validateDateFormat($to) )
        {
            $this->dateTo = $to;

            # Return object to preserve method-chaining:
            return $this;
        }

        throw new Exception( $this->_errors['format.invalid_date'] );
    }

    # Remove the date-to:
    public function removeDateTo()
    {
        $this->dateTo = null;

        # Return object to preserve method-chaining:
        return $this;
    }

    # Check if a currency code is in the supported range:
    public function currencyIsSupported( string $code )
    {
        $currencyCode = $this->sanitizeCurrencyCode($code);

        if( ! $this->validateCurrencyCodeFormat($currencyCode) )
        {
            throw new Exception( $this->_errors['format.invalid_currency_code'] );
        }

        return in_array( $currencyCode, $this->_currencies );
    }

    # Set the base currency:
    public function setBaseCurrency( string $currency )
    {
        # Sanitize the code:
        $currencyCode = $this->sanitizeCurrencyCode($currency);

        # Is it valid?
        $this->verifyCurrencyCode( $currencyCode );

        $this->baseCurrency = $currencyCode;

        # Return object to preserve method-chaining:
        return $this;
    }

    # Add multiple currencies at once
    public function addRates( array $currencies )
    {
        foreach ($currencies as $currency)
        {
            $this->addRate($currency);
        }
        return $this;
    }

    # Add a currency to the returned rates:
    public function addRate( string $currency )
    {
        # Sanitize the code:
        $currencyCode = $this->sanitizeCurrencyCode($currency);

        $this->verifyCurrencyCode($currencyCode);

        $this->rates[] = $currencyCode;

        # Return object to preserve method-chaining:
        return $this;
    }

    # Remove multiple currencies at once
    public function removeRates( array $currencies )
    {
        foreach ($currencies as $currency)
        {
            $this->removeRate($currency);
        }
        return $this;
    }

    # Remove a currency from the returned rates:
    public function removeRate( string $currency )
    {
        # Sanitize the code:
        $currencyCode = $this->sanitizeCurrencyCode($currency);

        # Verify it's valid:
        $this->verifyCurrencyCode($currencyCode);

        $newRates = [ ];

        # Loop over the rates and check them against the currency to remove:
        foreach( $this->getRates() as $rate )
        {
            if( $rate != $currencyCode )
            {
                $newRates[] = $rate;
            }
        }

        # Copy the temp array to the rates:
        $this->rates = $newRates;

        # Return object to preseve method chaining:
        return $this;
    }

    /****************************/
    /*                          */
    /*   API FUNCTION CALLS     */
    /*                          */
    /****************************/

    # Static function to quickly make a conversion:
    public function convert( string $to, float $amount, $rounding = 2 )
    {
        $currencyTo = $this->sanitizeCurrencyCode($to);

        # Check it's an allowed currency:
        $this->verifyCurrencyCode($to);

        if( !is_numeric($amount) )
        {
            throw new Exception( $this->_errors['format.invalid_amount'] );
        }

        if( ! is_numeric($rounding) )
        {
            throw new Exception( $this->_errors['format.invalid_rounding'] );
        }

        # Now get the response:
        $rate = $this->addRate($currencyTo)->fetch()->getRate();

        return round(
            ($amount * $rate),
            $rounding
        );
    }

    # Send off the request:
    public function latest($returnJSON = false, $parseJSON = true )
    {
        return $this->fetch($returnJSON, $parseJSON, 'latest');
    }

    # Send off the request:
    public function getFromDate(string $date, $returnJSON = false, $parseJSON = true )
    {
        # Set the relevant endpoint:
        $endpoint = date('Y-m-d', strtotime($date));

        return $this->fetch($returnJSON, $parseJSON, $endpoint);
    }

    # Send off the request:
    public function history( $returnJSON = false, $parseJSON = true )
    {
        $this->fetch($returnJSON, $parseJSON, 'history');
    }

    # Send off the request:
    public function fetch( $returnJSON = false, $parseJSON = true, $endpoint = null )
    {
        # Build the URL:
        $params = [ ];

        # Set the relevant endpoint:
        if (is_null($endpoint)) {
            if( is_null($this->dateFrom) )
            {
                $endpoint = is_null($this->fetchDate) ? 'latest' : $this->fetchDate;
            }
            else
            {
                $endpoint = 'history';
            }
        }

        # Add dateFrom if specified:
        if( ! is_null($this->getDateFrom()) )
        {
            $params['start_at'] = $this->getDateFrom();
        }
        elseif ($endpoint === 'history') {
            throw new Exception('Missing required property [DateFrom]');
        }

        # Add a dateTo:
        if( ! is_null($this->getDateTo()) )
        {
            $params['end_at'] = $this->getDateTo();
        }
        elseif ($endpoint === 'history') {
            throw new Exception('Missing required property [DateTo]');
        }

        # Set the base currency:
        if( ! is_null($this->getBaseCurrency()) )
        {
            $params['base'] = $this->getBaseCurrency();
        }

        # Are there any rates set?
        if( count($this->rates) > 0 )
        {
            $params['symbols'] = $this->getRates(',');
        }

        # Is the access token set
        if( ! $this->accessKey )
        {
            throw new Exception('Missing required access key.');
        }
        $params['access_key'] = $this->getAccessKey();

        $options = [ 'query' => $params ];

        if ($this->etag) {
            $options['headers'] = [
                'If-None-Match' => $this->etag,
                'If-Modified-Since' => $this->modifiedSince,
            ];
        }

        # Begin the sending:
        try
        {
            $guzzleResponse = $this->client->request('GET', $endpoint, $options);

            $response = new Response( $guzzleResponse );

            if( $returnJSON )
            {
                $json = $response->toJSON();

                if( $parseJSON )
                {
                    return json_decode( $json );
                }

                return $json;
            }

            return $response;
        }
        catch( \Exception $e )
        {
            throw new Exception( $e->getMessage() );
        }
    }

    /****************************/
    /*                          */
    /*  INTERNAL VERIFICATION  */
    /*                          */
    /****************************/

    # Validate a date is in the correct format:
    private function validateDateFormat( string $date = null )
    {
        if( !is_null($date) )
        {
            return preg_match( $this->dateRegExp, $date);
        }

        return false;
    }

    # Validate a date is in the correct format:
    private function validateModifiedSinceFormat( string $date = null )
    {
        if( !is_null($date) )
        {
            return preg_match( $this->modifiedSinceRegExp, $date);
        }

        return false;
    }

    # Validate a currency code is in the correct format:
    private function validateCurrencyCodeFormat( string $code = null )
    {
        if( !is_null($code) )
        {
            # Is the string longer than 3 characters?
            if( strlen($code) != 3 )
            {
                return false;
            }

            # Does it contain non-alphabetical characters?
            if( ! preg_match( $this->currencyRegExp, $code) )
            {
                return false;
            }

            return true;
        }

        return false;
    }

    # Runs tests to verify a currency code:
    private function verifyCurrencyCode( string $code )
    {
        $currencyCode = $this->sanitizeCurrencyCode($code);

        # Is the currency code in ISO 4217 format?
        if( ! $this->validateCurrencyCodeFormat($currencyCode) )
        {
            throw new Exception( $this->_errors['format.invalid_currency_code'] );
        }

        # Is it a supported currency?
        if( ! $this->currencyIsSupported($currencyCode) )
        {
            throw new Exception( $this->_errors['format.unsupported_currency'] );
        }
    }

    # Sanitize a currency code:
    private function sanitizeCurrencyCode( string $code )
    {
        return trim(
            strtoupper( $code )
        );
    }
}
