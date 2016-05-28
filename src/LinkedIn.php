<?php

namespace PHPLI;
use PHPLI\Exceptions;
use PHPLI\Exceptions\AccessTokenExchangeFailureException;
use PHPLI\Exceptions\AccessTokenMissingException;
use PHPLI\Exceptions\LinkedInErrorException;
use PHPLI\Exceptions\LoginFailureException;


/**
 * Class LinkedIn
 *
 * A library for interacting with the LinkedIn API
 * @package PHPLI
 */
class LinkedIn
{

    public $accessToken;
    private $clientId;
    private $clientSecret;
    public $callback;
    public static $OAUTH   = "https://www.linkedin.com/uas/oauth2";
    public static $BASE    = "https://api.linkedin.com/v1";

    /**
     * LinkedIn constructor.
     *
     * Takes an array of arguments as follows:
     *  $args = array (
     *      'clientId'      => 'client_id'          // Your LinkedIn API Client Id (required)
     *      'clientSecret'  => 'client_secret'      // Your LinkedIn API Client Secret (required)
     *      'callback'  => 'URL/to/callback'        // The URL to your application's callback handler
     *      'accessToken'   => 'user_access_token'  // A pre-existing user access token
     * );
     *
     * @param string[] $args
     * @throws Exceptions\ArgumentMissingException
     */
    public function __construct(Array $args = [])
    {
        $accessToken = "";
        $callback = "";

        if(!isset($args["clientId"])) {
            Throw new Exceptions\ArgumentMissingException("Client ID (clientId) not provided");
        }

        if(!isset($args["clientSecret"])) {
            Throw new Exceptions\ArgumentMissingException("Client secret (clientSecret) not provided");
        }

        if(isset($args["callback"])) {
            $callback = $args["callback"];
        }

        if(isset($args["accessToken"])) {
            $accessToken = $args["accessToken"];
        }

        $this->clientId = $args["clientId"];
        $this->clientSecret = $args["clientSecret"];
        $this->callback = $callback;
        $this->accessToken = $accessToken;
    }

    /**
     * Generate a URL to "Sign In with LinkedIn".
     *
     * Accepts an array describing the scope requested. For details of permissions see
     *  https://developer.linkedin.com/docs/fields
     *  Example:
     *      $scope = array (
     *          "r_basicprofile",
     *          "r_emailaddress"
     *      );
     *
     * @param string[] $scope
     * @return string The login URL
     * @throws Exceptions\ArgumentMissingException
     */
    public function getLoginURL($scope = array())
    {
        if(!isset($this->callback) || $this->callback =="")
            Throw new Exceptions\ArgumentMissingException("Callback URL (callback) not set");
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE){session_start();}

        //Generate CSRF state token and store in session
        $state = md5(uniqid(rand(), true));
        $_SESSION["phpli_auth_state"] = $state;

        //Form the arguments
        $scopeStr = implode(" ",$scope);
        $args = [
            "response_type" => "code",
            "client_id"     => $this->clientId,
            "redirect_uri"  => $this->callback,
            "state"         => $state,
            "scope"         => $scopeStr
        ];

        // Construct the login URL
        $url = self::$OAUTH."/authorization?";
        $url .= http_build_query($args);
        return $url;
    }

    /**
     * Exchange the short-lived auth token for a long-lived access token
     *
     * Accepts a the auth code as a single argument (usually gained after
     *  signing in with LinkedIn), and after a successful exchange returns the
     *  access token, whilst also saving the access token to the `accessToken`
     *  property. Throws an AccessTokencExchangeFailure on fail.
     *
     * @param $auth
     * @return string
     * @throws AccessTokenExchangeFailureException
     */
    public function getAccessToken($auth)
    {

        // Define URL for the request
        $post_url = self::$OAUTH."/accessToken";

        // Prepare the POST data
        $data = [
            "grant_type"    => "authorization_code",
            "code"          => $auth,
            "redirect_uri"  => $this->callback,
            "client_id"     => $this->clientId,
            "client_secret" => $this->clientSecret
        ];

        // Build the complete request URL
        $data = http_build_query($data);

        // Initialize cURL
        $curl = curl_init();

        // Set the options
        curl_setopt($curl,CURLOPT_URL, $post_url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl,CURLOPT_POST, sizeof($data));
        curl_setopt($curl,CURLOPT_POSTFIELDS, $data);

        // Execute the request
        $result = curl_exec($curl);
        curl_close($curl);

        $codes = json_decode($result);
        if(!isset($codes->access_token) || isset($codes->error))
        {
            Throw new AccessTokenExchangeFailureException();
        }

        else {
            $this->accessToken = $codes->access_token;
            return $this->accessToken;
        }
    }

    public function handleLogin($code = "", $state = "")
    {
        if($code == "")
        {
            // Authorisation code not explicitly defined, so attempt to retrieve from GET
            if(!isset($_GET["code"]))
            {
                Throw new LoginFailureException("Authorisation code not provided");
            }

            $code = $_GET["code"];
        }

        if($state == "")
        {
            // CSRF token not explicitly defined, so attempt to retrieve from GET
            if(!isset($_GET["state"]))
            {
                Throw new LoginFailureException("CSRF token not provided");
            }

            $state = $_GET["state"];
        }

        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE){session_start();}

        // Check provided state token against that saved in the session

        if($_SESSION["phpli_auth_state"] != $state)
        {
            Throw new LoginFailureException("CSRF verification failed");
        }

        // All okay, request access token

        $token = $this->getAccessToken($code);

        return $token;

    }

    /**
     * Carry out a request to the LinkedIn API
     *
     * Accepts three arguments:
     *  $resource (required)    - the location of the resource e.g. "/people"
     *  $method                 - the HTTP method for the request (defaults to GET)
     *  $data                   - an array of any data to be sent with the request. Will
     *                              be appended to the URL for a GET request, or sent
     *                              as post fields for a POST request
     *  Throws an AccessTokenMissingException if the accessToken property has not been set
     *      (either explicitly or through the use of getAccessToken(), and returns a LinkedInResponse
     *       object on success)
     *
     * @param $resource
     * @param string $method
     * @param array $data
     * @return LinkedInResponse
     * @throws AccessTokenMissingException
     * @throws LinkedInErrorException
     */
    public function request($resource, $method = "GET", $data = array())
    {
        // Instanciate an empty object to hold the result
        $response = new LinkedInResponse();

        // Check we have an access token
        if(!$this->accessToken)
        {
            Throw new AccessTokenMissingException();
        }

        // Construct the URL for the request
        $url = self::$BASE.$resource;
        $queryData = http_build_query($data);
        $authorization = "Authorization: Bearer ".$this->accessToken;

        // If we are carrying out a GET request, append the data
        if($method == "GET") {
            $url = $url . "?" . $queryData;
        }

        // Add the request URL and data to our response object (useful for debugging)
        $response->requestUrl = $url;
        $response->requestData = $data;

        // Initialize cURL
        $curl = curl_init();

        // Set the options
        curl_setopt($curl,CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        // Define additional parameters for POST requests
        if($method == "POST")
        {
            curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded','x-li-format: json',$authorization));
            curl_setopt($curl,CURLOPT_POST, sizeof($queryData));
            curl_setopt($curl,CURLOPT_POSTFIELDS, $queryData);
        }

        else
        {
            curl_setopt($curl, CURLOPT_HTTPHEADER, array('x-li-format: json',$authorization));
        }

        // Execute the request
        $result = curl_exec($curl);
        curl_close($curl);

        // Parse Result
        $result = json_decode($result);

        if(isset($result->errorCode))
        {
            Throw new LinkedInErrorException($result->message, $result->errorCode);
        }

        // We have no error, return the result of the request
        else
        {
            $response->data = $result;
        }
        return $response;
    }
}
