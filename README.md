# PHPLI

A PHP library to provide easy interaction with the LinkedIn API

## Installation

Install this package via composer:

Run `composer require jackbutler/php-linkedin`

Install manually:

Download this package into your project and include using:

```php
    <?php
        require 'path/to/LinkedIn/src/PHPLI/LinkedIn.php';
```

## Usage

Before carrying out calls to the API, your application should first be authenticated by the user
to interact with LinkedIn, namely by having them grant you an authorisation code, which you can then
exchange for a longer-lived access token (currently 60 days). The process recommended is as follows:

1. **Sign In with LinkedIn**

    The library includes a method for generating sign in URLs to provide a "Sign In with LinkedIn" button/link to
    your users. To generate this URL, you first need to create a new instance of the LinkedIn class:

    ```php
     <?php
       $args = array (
                 'clientId'      => 'client_id',          // Your LinkedIn API Client Id (required)
                 'clientSecret'  => 'client_secret',      // Your LinkedIn API Client Secret (required)
                 'callback'  => 'URL/to/callback'         // The absolute URL to your application's callback handler (required for auth requests)
            );

       $linkedin = new PHPLI\Linkedin($args);

    ```

    You can then use this instance to generate a login URL, for your required scope. For example:

    ```php
   <?php
       $scope = array (
           "r_basicprofile",
           "r_emailaddress"
                  );
       $signinUrl = $linkedin->getLoginURL($scope);
    ```

    You can then provide this URL to your users to sign in with LinkedIn:

2. **Exchange Authorisation Token for Access Token**

    Step 1 will take user through the sign in and authorisation process on LinkedIn, and will then return
    your user to the callback URL you provided, with an authorisation token (assuming all went well).
    In you callback script you then need to exchange the short-lived authorisation token for a longer-lived
    access token (currently 60 days).

    The library provides a login handler that can be used inside your callback script as follows:
    ```php
    <?php
       // Instantiate a LinkedIn object as step 1, then:

       $token = $linkedin->handleLogin();
    ```

    This method will attempt to retrieve the auth code and CSRF token from the URL query string  (via ``` $_GET ```),
    then internally calls the ```getAccessToken()``` method to carry out the exchange. As well as returning the access token, the token
    is also saved as a property of the LinkedIn object (during the ```getAccessToken()``` call) to prepare the object for
    subsequent requests. The access token will be stored in the object for the lifetime of that instance, so your application
    will need to store the access token if you wish to use it for later requests.

3. **Make requests to the API**

    To make requests to the API you will again need an instance of PHPLI\LinkedIn, but this time with an access token also set during
    instantiation:
    ```php
     <?php
       $args = array (
                 'clientId'      => 'client_id',          // Your LinkedIn API Client Id (required)
                 'clientSecret'  => 'client_secret',      // Your LinkedIn API Client Secret (required)
                 'accessToken'  => 'the_access_token'     // The access token authorised to make requests on the user's behalf
            );

       $linkedin = new PHPLI\Linkedin($args);

    ```

    Once you have the instance of LinkedIn, you can then use the ```request()``` method to carry out your request. The arguments of
    ```request()``` are as follows:

    ```php
       <?php
           request(
               String resource,
               String method = "GET",
               Array data = []
           );
    ```

    The following gives an example of getting a few attributes of the logged in user from
    LinkedIn

   ```php
       <?php
           $response = $linkedin->request("/people/~:(first-name,last-name,id,email-address)");

   ```

## Development

This library I developed as part of another project I was working on, but as I struggled
 myself to find a suitable library to interact with LinkedIn, I thought I'd extract it
 and package it for others to use. I do plan on maintaining it and improving it, I'd like to
 add some helper classes, such as a LinkedInUser class (if anyone has used the Facebook
 PHP SDK, I'd like to build this into something similar).

 Please feel free to fork this project, submit pull requests for improvements etc.

## License

This project is licensed under the MIT License.

