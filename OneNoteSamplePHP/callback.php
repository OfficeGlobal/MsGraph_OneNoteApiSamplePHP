<?php
//*********************************************************
// Copyright (c) Microsoft Corporation
// All rights reserved.
//
// Licensed under the Apache License, Version 2.0 (the ""License"");
// you may not use this file except in compliance with the License.
// You may obtain a copy of the License at
// http://www.apache.org/licenses/LICENSE-2.0
//
// THIS CODE IS PROVIDED ON AN  *AS IS* BASIS, WITHOUT
// WARRANTIES OR CONDITIONS OF ANY KIND, EITHER EXPRESS
// OR IMPLIED, INCLUDING WITHOUT LIMITATION ANY IMPLIED
// WARRANTIES OR CONDITIONS OF TITLE, FITNESS FOR A PARTICULAR
// PURPOSE, MERCHANTABLITY OR NON-INFRINGEMENT.
//
// See the Apache Version 2.0 License for specific language
// governing permissions and limitations under the License.
//*********************************************************
define('AUTHCOOKIE', 'graph_auth');
define('ERRORCODE', 'error');
define('ERRORDESC', 'error_description');
define('ACCESSTOKEN', 'access_token');
define('ID_TOKEN', 'id_token');
define('CODE', 'code');
define('SCOPE', 'scope');
define('EXPIRESIN', 'expires_in');
define('REFRESHTOKEN', 'refresh_token');

// Update the following values
define('CLIENTID', '<CLIENT_ID>');
define('CLIENTSECRET', '<CLIENT_SECRET>');
define('SCOPES', 'openid Notes.ReadWrite Notes.ReadWrite.All offline_access User.Read');

// Make sure this is identical to the REDIRECT_URI_PATH parameter passed in WL.init() call.
define('CALLBACK', '<REDIRECT_URI_PATH>');

define('OAUTHURL', 'https://login.microsoftonline.com/common/oauth2/v2.0/token');

function buildQueryString($array) {
  $result = '';
  foreach ($array as $k => $v) {
    if ($result == '') $prefix = '';
    else $prefix = '&';

    $result .= $prefix . rawurlencode($k) . '=' . rawurlencode($v);
  }
  return $result;
}

function parseQueryString($query) {
  $result = array();
  $arr = preg_split('/&/', $query);
  foreach ($arr as $arg) {
    if (strpos($arg, '=') !== false)
      {
        $kv = preg_split('/=/', $arg);
        $result[rawurldecode($kv[0])] = rawurldecode($kv[1]);
      }
    }
  return $result;
}

function sendRequest(
  $url,
  $method = 'GET',
  $data = array(),
  $headers = array('Content-type: application/x-www-form-urlencoded;charset=UTF-8'))
{
  $context = stream_context_create(array
                                    (
                                    'http' => array(
                                      'method' => $method,
                                      'header' => $headers,
                                      'content' => buildQueryString($data)
                                    )
                                  ));

  return file_get_contents($url, false, $context);
}

function requestAccessToken($content) {
  $response = sendRequest(
    OAUTHURL,
    'POST',
    $content);

  if ($response !== false) {
    $authToken = json_decode($response);
    if (!empty($authToken) && !empty($authToken->{ACCESSTOKEN})) {
      return $authToken;
    }
  }

  return false;
}

function requestAccessTokenByVerifier($verifier){
  return requestAccessToken(array(
                              'client_id' => CLIENTID,
                              'redirect_uri' => CALLBACK,
                              'client_secret' => CLIENTSECRET,
                              'code' => $verifier,
                              'grant_type' => 'authorization_code',
                              'scope' => SCOPES
                            ));
}

function requestAccessTokenByRefreshToken($refreshToken) {
  return requestAccessToken(array(
                             'client_id' => CLIENTID,
                             'redirect_uri' => CALLBACK,
                             'client_secret' => CLIENTSECRET,
                             'refresh_token' => $refreshToken,
                             'grant_type' => 'refresh_token'
                            ));
}

function handlePageRequest() {
  if (!empty($_GET[ACCESSTOKEN])) {
    // There is a token available already. It should be the token flow. Ignore it.
    return;
  }

  $verifier = $_GET[CODE];
  if (!empty($verifier)) {
    $token = requestAccessTokenByVerifier($verifier);
    if ($token !== false) handleTokenResponse($token);
    else {
      handleTokenResponse(null, array(
                                 ERRORCODE => 'request_failed',
                                 ERRORDESC => 'Failed to retrieve user access token.'
                                ));
    }

  return;
  }

  $refreshToken = readRefreshToken();
  if (!empty($refreshToken)) {
    $token = requestAccessTokenByRefreshToken($refreshToken);
    if ($token !== false) handleTokenResponse($token);
    else {
      handleTokenResponse(null, array(
                                 ERRORCODE => 'request_failed',
                                 ERRORDESC => 'Failed to retrieve user access token.'
                               ));
    }

    return;
  }

  $errorCode = $_GET[ERRORCODE];
  $errorDesc = $_GET[ERRORDESC];

  if (!empty($errorCode)) {
    handleTokenResponse(null, array(
                               ERRORCODE => $errorCode,
                               ERRORDESC => $errorDesc));
  }
}

function readRefreshToken() {
    // read refresh token of the user identified by the site.
  return null;
}

function saveRefreshToken($refreshToken) {
    // save the refresh token and associate it with the user identified by your site credential system.
}

function handleTokenResponse($token, $error = null) {
  $authCookie = $_COOKIE[AUTHCOOKIE];
  $cookieValues = parseQueryString($authCookie);

  if (!empty($token)) {
    $cookieValues[ACCESSTOKEN] = $token->{ACCESSTOKEN};
    $cookieValues[ID_TOKEN] = $token->{ID_TOKEN};
    $cookieValues[SCOPES] = $token->{SCOPE};
    $cookieValues[EXPIRESIN] = $token->{EXPIRESIN};

    if (!empty($token->{ REFRESHTOKEN })) saveRefreshToken($token->{ REFRESHTOKEN });
    setrawcookie('session_state', 'Authorized', 0, '/', $_SERVER['SERVER_NAME']);
  }

  if (!empty($error)) {
    $cookieValues[ERRORCODE] = $error[ERRORCODE];
    $cookieValues[ERRORDESC] = $error[ERRORDESC];

    setrawcookie('session_state', 'Unauthorized', 0, '/', $_SERVER['SERVER_NAME']);
  }
  setrawcookie(AUTHCOOKIE, buildQueryString($cookieValues), 0, '/', $_SERVER['SERVER_NAME']);
}

handlePageRequest();

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
        "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml"
      xmlns:msgr="http://messenger.live.com/2009/ui-tags">
  <head>
    <title>Microsoft Graph OneNote Sample Callback Page</title>
    <script>

    window.onload = function(e) {
      window.close();
      window.opener.document.dispatchEvent(new Event('authStateChanged'));
    }
    </script>
  </head>
  <body>
  </body>
</html>
