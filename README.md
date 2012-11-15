[![Build Status](https://secure.travis-ci.org/sandvige/apimapper.png?branch=master)](http://travis-ci.org/sandvige/apimapper)

ApiMapper is a simple wrapper arround Buzz to perform API calls.

```php
<?php

$browser = new Buzz\Browser();
$mapper = new ApiMapper\ApiMapper($browser, 'https://api.baseurl.com/api');

$mapper->addEventListener(new AuthenticationListener($sessionManager));
$mapper->addQueryParameter('apiKey', new ApiKeyProvider('someApiKey'));
$mapper->addRouteParameter('{token}', new AuthenticationProvider($sessionManager));

// https://api.baseurl.com/api/auth?apiKey=someApiKey&userName=$userName&password=$password
$response = $mapper->get('auth', array(
    "userName" => $userName,
	"password" => $password
));

// There is no need here to provide the token since it has been
// wrapped when calling auth.
// https://api.baseurl.com/api/1234/some/obscure/12/route
$response = $mapper->get('{token}/some/obscure/{someId}/route', array(
    "{someId}" => 12
));
```
