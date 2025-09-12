<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="IE=edge,chrome=1" http-equiv="X-UA-Compatible">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>ProgressiveUtilities Documentation</title>

    <link href="https://fonts.googleapis.com/css?family=Open+Sans&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset("/vendor/scribe/css/theme-default.style.css") }}" media="screen">
    <link rel="stylesheet" href="{{ asset("/vendor/scribe/css/theme-default.print.css") }}" media="print">

    <script src="https://cdn.jsdelivr.net/npm/lodash@4.17.10/lodash.min.js"></script>

    <link rel="stylesheet"
          href="https://unpkg.com/@highlightjs/cdn-assets@11.6.0/styles/obsidian.min.css">
    <script src="https://unpkg.com/@highlightjs/cdn-assets@11.6.0/highlight.min.js"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jets/0.14.1/jets.min.js"></script>

    <style id="language-style">
        /* starts out as display none and is replaced with js later  */
                    body .content .bash-example code { display: none; }
                    body .content .javascript-example code { display: none; }
                    body .content .php-example code { display: none; }
                    body .content .python-example code { display: none; }
            </style>

    <script>
        var tryItOutBaseUrl = "https://backend.buxton.progressiveutilities.com";
        var useCsrf = Boolean();
        var csrfUrl = "/sanctum/csrf-cookie";
    </script>
    <script src="{{ asset("/vendor/scribe/js/tryitout-4.31.0.js") }}"></script>

    <script src="{{ asset("/vendor/scribe/js/theme-default-4.31.0.js") }}"></script>

</head>

<body data-languages="[&quot;bash&quot;,&quot;javascript&quot;,&quot;php&quot;,&quot;python&quot;]">

<a href="#" id="nav-button">
    <span>
        MENU
        <img src="{{ asset("/vendor/scribe/images/navbar.png") }}" alt="navbar-image"/>
    </span>
</a>
<div class="tocify-wrapper">
    
            <div class="lang-selector">
                                            <button type="button" class="lang-button" data-language-name="bash">bash</button>
                                            <button type="button" class="lang-button" data-language-name="javascript">javascript</button>
                                            <button type="button" class="lang-button" data-language-name="php">php</button>
                                            <button type="button" class="lang-button" data-language-name="python">python</button>
                    </div>
    
    <div class="search">
        <input type="text" class="search" id="input-search" placeholder="Search">
    </div>

    <div id="toc">
                    <ul id="tocify-header-introduction" class="tocify-header">
                <li class="tocify-item level-1" data-unique="introduction">
                    <a href="#introduction">Introduction</a>
                </li>
                            </ul>
                    <ul id="tocify-header-authenticating-requests" class="tocify-header">
                <li class="tocify-item level-1" data-unique="authenticating-requests">
                    <a href="#authenticating-requests">Authenticating requests</a>
                </li>
                            </ul>
                    <ul id="tocify-header-authentication" class="tocify-header">
                <li class="tocify-item level-1" data-unique="authentication">
                    <a href="#authentication">Authentication</a>
                </li>
                                    <ul id="tocify-subheader-authentication" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="authentication-POSTapi-v2-auth-login">
                                <a href="#authentication-POSTapi-v2-auth-login">POST api/v2/auth/login</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="authentication-POSTapi-v2-auth-logout">
                                <a href="#authentication-POSTapi-v2-auth-logout">POST api/v2/auth/logout</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-callbacks" class="tocify-header">
                <li class="tocify-item level-1" data-unique="callbacks">
                    <a href="#callbacks">Callbacks</a>
                </li>
                                    <ul id="tocify-subheader-callbacks" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="callbacks-POSTapi-v2-callbacks">
                                <a href="#callbacks-POSTapi-v2-callbacks">Register your callback URL</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="callbacks-GETapi-v2-callbacks">
                                <a href="#callbacks-GETapi-v2-callbacks">Get current callback URL configuration</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="callbacks-PUTapi-v2-callbacks">
                                <a href="#callbacks-PUTapi-v2-callbacks">Update existing callback URL configuration</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="callbacks-DELETEapi-v2-callbacks">
                                <a href="#callbacks-DELETEapi-v2-callbacks">Delete callback URL registration</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-meters" class="tocify-header">
                <li class="tocify-item level-1" data-unique="meters">
                    <a href="#meters">Meters</a>
                </li>
                                    <ul id="tocify-subheader-meters" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="meters-POSTapi-v2-meters--meter_number--valve">
                                <a href="#meters-POSTapi-v2-meters--meter_number--valve">Update Meter Valve Status</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="meters-GETapi-v2-meters--meter_number--readings">
                                <a href="#meters-GETapi-v2-meters--meter_number--readings">Get Meter Readings</a>
                            </li>
                                                                        </ul>
                            </ul>
            </div>

    <ul class="toc-footer" id="toc-footer">
                    <li style="padding-bottom: 5px;"><a href="{{ route("scribe.postman") }}">View Postman collection</a></li>
                            <li style="padding-bottom: 5px;"><a href="{{ route("scribe.openapi") }}">View OpenAPI spec</a></li>
                <li><a href="http://github.com/knuckleswtf/scribe">Documentation powered by Scribe ✍</a></li>
    </ul>

    <ul class="toc-footer" id="last-updated">
        <li>Last updated: September 12, 2025</li>
    </ul>
</div>

<div class="page-wrapper">
    <div class="dark-box"></div>
    <div class="content">
        <h1 id="introduction">Introduction</h1>
<aside>
    <strong>Base URL</strong>: <code>https://backend.buxton.progressiveutilities.com</code>
</aside>
<p>This documentation aims to provide all the information you need to work with our API.</p>
<aside>As you scroll, you'll see code examples for working with the API in different programming languages in the dark area to the right (or as part of the content on mobile).
You can switch the language used with the tabs at the top right (or from the nav menu at the top left on mobile).</aside>

        <h1 id="authenticating-requests">Authenticating requests</h1>
<p>To authenticate requests, include an <strong><code>Authorization</code></strong> header with the value <strong><code>"Bearer {YOUR_AUTH_KEY}"</code></strong>.</p>
<p>All authenticated endpoints are marked with a <code>requires authentication</code> badge in the documentation below.</p>
<p>You can retrieve your token by logging via the login endpoint.</p>

        <h1 id="authentication">Authentication</h1>

    

                                <h2 id="authentication-POSTapi-v2-auth-login">POST api/v2/auth/login</h2>

<p>
</p>



<span id="example-requests-POSTapi-v2-auth-login">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "https://backend.buxton.progressiveutilities.com/api/v2/auth/login" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"email\": \"jerde.tressie@example.net\",
    \"password\": \"blanditiis\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://backend.buxton.progressiveutilities.com/api/v2/auth/login"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "email": "jerde.tressie@example.net",
    "password": "blanditiis"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>


<div class="php-example">
    <pre><code class="language-php">$client = new \GuzzleHttp\Client();
$url = 'https://backend.buxton.progressiveutilities.com/api/v2/auth/login';
$response = $client-&gt;post(
    $url,
    [
        'headers' =&gt; [
            'Content-Type' =&gt; 'application/json',
            'Accept' =&gt; 'application/json',
        ],
        'json' =&gt; [
            'email' =&gt; 'jerde.tressie@example.net',
            'password' =&gt; 'blanditiis',
        ],
    ]
);
$body = $response-&gt;getBody();
print_r(json_decode((string) $body));</code></pre></div>


<div class="python-example">
    <pre><code class="language-python">import requests
import json

url = 'https://backend.buxton.progressiveutilities.com/api/v2/auth/login'
payload = {
    "email": "jerde.tressie@example.net",
    "password": "blanditiis"
}
headers = {
  'Content-Type': 'application/json',
  'Accept': 'application/json'
}

response = requests.request('POST', url, headers=headers, json=payload)
response.json()</code></pre></div>

</span>

<span id="example-responses-POSTapi-v2-auth-login">
</span>
<span id="execution-results-POSTapi-v2-auth-login" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v2-auth-login"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v2-auth-login"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v2-auth-login" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v2-auth-login">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v2-auth-login" data-method="POST"
      data-path="api/v2/auth/login"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v2-auth-login', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v2-auth-login"
                    onclick="tryItOut('POSTapi-v2-auth-login');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v2-auth-login"
                    onclick="cancelTryOut('POSTapi-v2-auth-login');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v2-auth-login"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v2/auth/login</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v2-auth-login"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v2-auth-login"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>email</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="email"                data-endpoint="POSTapi-v2-auth-login"
               value="jerde.tressie@example.net"
               data-component="body">
    <br>
<p>Must be a valid email address. Example: <code>jerde.tressie@example.net</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>password</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="password"                data-endpoint="POSTapi-v2-auth-login"
               value="blanditiis"
               data-component="body">
    <br>
<p>Example: <code>blanditiis</code></p>
        </div>
        </form>

                    <h2 id="authentication-POSTapi-v2-auth-logout">POST api/v2/auth/logout</h2>

<p>
</p>



<span id="example-requests-POSTapi-v2-auth-logout">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "https://backend.buxton.progressiveutilities.com/api/v2/auth/logout" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://backend.buxton.progressiveutilities.com/api/v2/auth/logout"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "POST",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="php-example">
    <pre><code class="language-php">$client = new \GuzzleHttp\Client();
$url = 'https://backend.buxton.progressiveutilities.com/api/v2/auth/logout';
$response = $client-&gt;post(
    $url,
    [
        'headers' =&gt; [
            'Content-Type' =&gt; 'application/json',
            'Accept' =&gt; 'application/json',
        ],
    ]
);
$body = $response-&gt;getBody();
print_r(json_decode((string) $body));</code></pre></div>


<div class="python-example">
    <pre><code class="language-python">import requests
import json

url = 'https://backend.buxton.progressiveutilities.com/api/v2/auth/logout'
headers = {
  'Content-Type': 'application/json',
  'Accept': 'application/json'
}

response = requests.request('POST', url, headers=headers)
response.json()</code></pre></div>

</span>

<span id="example-responses-POSTapi-v2-auth-logout">
</span>
<span id="execution-results-POSTapi-v2-auth-logout" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v2-auth-logout"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v2-auth-logout"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v2-auth-logout" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v2-auth-logout">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v2-auth-logout" data-method="POST"
      data-path="api/v2/auth/logout"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v2-auth-logout', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v2-auth-logout"
                    onclick="tryItOut('POSTapi-v2-auth-logout');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v2-auth-logout"
                    onclick="cancelTryOut('POSTapi-v2-auth-logout');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v2-auth-logout"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v2/auth/logout</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v2-auth-logout"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v2-auth-logout"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                <h1 id="callbacks">Callbacks</h1>

    

                                <h2 id="callbacks-POSTapi-v2-callbacks">Register your callback URL</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-POSTapi-v2-callbacks">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "https://backend.buxton.progressiveutilities.com/api/v2/callbacks" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"callback_url\": \"https:\\/\\/client.example.com\\/webhooks\\/meter-updates\",
    \"secret_token\": \"your-webhook-secret-token-min-32-chars\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://backend.buxton.progressiveutilities.com/api/v2/callbacks"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "callback_url": "https:\/\/client.example.com\/webhooks\/meter-updates",
    "secret_token": "your-webhook-secret-token-min-32-chars"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>


<div class="php-example">
    <pre><code class="language-php">$client = new \GuzzleHttp\Client();
$url = 'https://backend.buxton.progressiveutilities.com/api/v2/callbacks';
$response = $client-&gt;post(
    $url,
    [
        'headers' =&gt; [
            'Authorization' =&gt; 'Bearer {YOUR_AUTH_KEY}',
            'Content-Type' =&gt; 'application/json',
            'Accept' =&gt; 'application/json',
        ],
        'json' =&gt; [
            'callback_url' =&gt; 'https://client.example.com/webhooks/meter-updates',
            'secret_token' =&gt; 'your-webhook-secret-token-min-32-chars',
        ],
    ]
);
$body = $response-&gt;getBody();
print_r(json_decode((string) $body));</code></pre></div>


<div class="python-example">
    <pre><code class="language-python">import requests
import json

url = 'https://backend.buxton.progressiveutilities.com/api/v2/callbacks'
payload = {
    "callback_url": "https:\/\/client.example.com\/webhooks\/meter-updates",
    "secret_token": "your-webhook-secret-token-min-32-chars"
}
headers = {
  'Authorization': 'Bearer {YOUR_AUTH_KEY}',
  'Content-Type': 'application/json',
  'Accept': 'application/json'
}

response = requests.request('POST', url, headers=headers, json=payload)
response.json()</code></pre></div>

</span>

<span id="example-responses-POSTapi-v2-callbacks">
</span>
<span id="execution-results-POSTapi-v2-callbacks" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v2-callbacks"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v2-callbacks"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v2-callbacks" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v2-callbacks">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v2-callbacks" data-method="POST"
      data-path="api/v2/callbacks"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v2-callbacks', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v2-callbacks"
                    onclick="tryItOut('POSTapi-v2-callbacks');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v2-callbacks"
                    onclick="cancelTryOut('POSTapi-v2-callbacks');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v2-callbacks"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v2/callbacks</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v2-callbacks"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v2-callbacks"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v2-callbacks"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>callback_url</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="callback_url"                data-endpoint="POSTapi-v2-callbacks"
               value="https://client.example.com/webhooks/meter-updates"
               data-component="body">
    <br>
<p>The HTTPS callback URL. Example: <code>https://client.example.com/webhooks/meter-updates</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>secret_token</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="secret_token"                data-endpoint="POSTapi-v2-callbacks"
               value="your-webhook-secret-token-min-32-chars"
               data-component="body">
    <br>
<p>optional Secret token for webhook signature verification. Min 32 chars. Example: <code>your-webhook-secret-token-min-32-chars</code></p>
        </div>
        </form>

                    <h2 id="callbacks-GETapi-v2-callbacks">Get current callback URL configuration</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-v2-callbacks">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "https://backend.buxton.progressiveutilities.com/api/v2/callbacks" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://backend.buxton.progressiveutilities.com/api/v2/callbacks"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="php-example">
    <pre><code class="language-php">$client = new \GuzzleHttp\Client();
$url = 'https://backend.buxton.progressiveutilities.com/api/v2/callbacks';
$response = $client-&gt;get(
    $url,
    [
        'headers' =&gt; [
            'Authorization' =&gt; 'Bearer {YOUR_AUTH_KEY}',
            'Content-Type' =&gt; 'application/json',
            'Accept' =&gt; 'application/json',
        ],
    ]
);
$body = $response-&gt;getBody();
print_r(json_decode((string) $body));</code></pre></div>


<div class="python-example">
    <pre><code class="language-python">import requests
import json

url = 'https://backend.buxton.progressiveutilities.com/api/v2/callbacks'
headers = {
  'Authorization': 'Bearer {YOUR_AUTH_KEY}',
  'Content-Type': 'application/json',
  'Accept': 'application/json'
}

response = requests.request('GET', url, headers=headers)
response.json()</code></pre></div>

</span>

<span id="example-responses-GETapi-v2-callbacks">
            <blockquote>
            <p>Example response (200, Current callback configuration):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: true,
    &quot;message&quot;: &quot;Current callback configuration&quot;,
    &quot;data&quot;: {
        &quot;callback_url&quot;: &quot;https://client.example.com/webhooks/meter-updates&quot;,
        &quot;secret_token&quot;: &quot;your-webhook-secret-token-min-32-chars&quot;,
        &quot;registered_at&quot;: &quot;2025-09-11T10:30:45.000000Z&quot;,
        &quot;last_updated&quot;: &quot;2025-09-11T14:22:15.000000Z&quot;
    },
    &quot;errors&quot;: null
}</code>
 </pre>
            <blockquote>
            <p>Example response (404, Callback URL not found):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: false,
    &quot;message&quot;: &quot;No callback URL found for this client&quot;,
    &quot;data&quot;: null,
    &quot;errors&quot;: {
        &quot;type&quot;: &quot;CallbackNotFound&quot;,
        &quot;details&quot;: null
    }
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v2-callbacks" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v2-callbacks"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v2-callbacks"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v2-callbacks" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v2-callbacks">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v2-callbacks" data-method="GET"
      data-path="api/v2/callbacks"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v2-callbacks', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v2-callbacks"
                    onclick="tryItOut('GETapi-v2-callbacks');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v2-callbacks"
                    onclick="cancelTryOut('GETapi-v2-callbacks');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v2-callbacks"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v2/callbacks</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v2-callbacks"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v2-callbacks"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v2-callbacks"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="callbacks-PUTapi-v2-callbacks">Update existing callback URL configuration</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-PUTapi-v2-callbacks">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PUT \
    "https://backend.buxton.progressiveutilities.com/api/v2/callbacks" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"callback_url\": \"https:\\/\\/client.example.com\\/webhooks\\/meter-updates\",
    \"secret_token\": \"your-webhook-secret-token-min-32-chars\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://backend.buxton.progressiveutilities.com/api/v2/callbacks"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "callback_url": "https:\/\/client.example.com\/webhooks\/meter-updates",
    "secret_token": "your-webhook-secret-token-min-32-chars"
};

fetch(url, {
    method: "PUT",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>


<div class="php-example">
    <pre><code class="language-php">$client = new \GuzzleHttp\Client();
$url = 'https://backend.buxton.progressiveutilities.com/api/v2/callbacks';
$response = $client-&gt;put(
    $url,
    [
        'headers' =&gt; [
            'Authorization' =&gt; 'Bearer {YOUR_AUTH_KEY}',
            'Content-Type' =&gt; 'application/json',
            'Accept' =&gt; 'application/json',
        ],
        'json' =&gt; [
            'callback_url' =&gt; 'https://client.example.com/webhooks/meter-updates',
            'secret_token' =&gt; 'your-webhook-secret-token-min-32-chars',
        ],
    ]
);
$body = $response-&gt;getBody();
print_r(json_decode((string) $body));</code></pre></div>


<div class="python-example">
    <pre><code class="language-python">import requests
import json

url = 'https://backend.buxton.progressiveutilities.com/api/v2/callbacks'
payload = {
    "callback_url": "https:\/\/client.example.com\/webhooks\/meter-updates",
    "secret_token": "your-webhook-secret-token-min-32-chars"
}
headers = {
  'Authorization': 'Bearer {YOUR_AUTH_KEY}',
  'Content-Type': 'application/json',
  'Accept': 'application/json'
}

response = requests.request('PUT', url, headers=headers, json=payload)
response.json()</code></pre></div>

</span>

<span id="example-responses-PUTapi-v2-callbacks">
            <blockquote>
            <p>Example response (200, Callback URL updated successfully):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: true,
    &quot;message&quot;: &quot;Callback URL updated successfully&quot;,
    &quot;data&quot;: {
        &quot;callback_url&quot;: &quot;https://client.example.com/webhooks/meter-updates&quot;,
        &quot;secret_token&quot;: &quot;your-webhook-secret-token-min-32-chars&quot;
    },
    &quot;errors&quot;: null
}</code>
 </pre>
            <blockquote>
            <p>Example response (404, Callback URL not found):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: false,
    &quot;message&quot;: &quot;No callback URL found for this client. Please register first.&quot;,
    &quot;data&quot;: null,
    &quot;errors&quot;: {
        &quot;type&quot;: &quot;CallbackNotFound&quot;,
        &quot;details&quot;: null
    }
}</code>
 </pre>
    </span>
<span id="execution-results-PUTapi-v2-callbacks" hidden>
    <blockquote>Received response<span
                id="execution-response-status-PUTapi-v2-callbacks"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PUTapi-v2-callbacks"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PUTapi-v2-callbacks" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-PUTapi-v2-callbacks">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-PUTapi-v2-callbacks" data-method="PUT"
      data-path="api/v2/callbacks"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PUTapi-v2-callbacks', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-PUTapi-v2-callbacks"
                    onclick="tryItOut('PUTapi-v2-callbacks');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-PUTapi-v2-callbacks"
                    onclick="cancelTryOut('PUTapi-v2-callbacks');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-PUTapi-v2-callbacks"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-darkblue">PUT</small>
            <b><code>api/v2/callbacks</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="PUTapi-v2-callbacks"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PUTapi-v2-callbacks"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PUTapi-v2-callbacks"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>callback_url</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="callback_url"                data-endpoint="PUTapi-v2-callbacks"
               value="https://client.example.com/webhooks/meter-updates"
               data-component="body">
    <br>
<p>optional The HTTPS callback URL. Example: <code>https://client.example.com/webhooks/meter-updates</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>secret_token</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="secret_token"                data-endpoint="PUTapi-v2-callbacks"
               value="your-webhook-secret-token-min-32-chars"
               data-component="body">
    <br>
<p>optional Secret token for webhook signature verification. Min 32 chars. Example: <code>your-webhook-secret-token-min-32-chars</code></p>
        </div>
        </form>

                    <h2 id="callbacks-DELETEapi-v2-callbacks">Delete callback URL registration</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-DELETEapi-v2-callbacks">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request DELETE \
    "https://backend.buxton.progressiveutilities.com/api/v2/callbacks" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://backend.buxton.progressiveutilities.com/api/v2/callbacks"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "DELETE",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="php-example">
    <pre><code class="language-php">$client = new \GuzzleHttp\Client();
$url = 'https://backend.buxton.progressiveutilities.com/api/v2/callbacks';
$response = $client-&gt;delete(
    $url,
    [
        'headers' =&gt; [
            'Authorization' =&gt; 'Bearer {YOUR_AUTH_KEY}',
            'Content-Type' =&gt; 'application/json',
            'Accept' =&gt; 'application/json',
        ],
    ]
);
$body = $response-&gt;getBody();
print_r(json_decode((string) $body));</code></pre></div>


<div class="python-example">
    <pre><code class="language-python">import requests
import json

url = 'https://backend.buxton.progressiveutilities.com/api/v2/callbacks'
headers = {
  'Authorization': 'Bearer {YOUR_AUTH_KEY}',
  'Content-Type': 'application/json',
  'Accept': 'application/json'
}

response = requests.request('DELETE', url, headers=headers)
response.json()</code></pre></div>

</span>

<span id="example-responses-DELETEapi-v2-callbacks">
            <blockquote>
            <p>Example response (200, Callback URL deleted successfully):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: true,
    &quot;message&quot;: &quot;Callback URL deleted successfully&quot;,
    &quot;data&quot;: null,
    &quot;errors&quot;: null
}</code>
 </pre>
            <blockquote>
            <p>Example response (404, Callback URL not found):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: false,
    &quot;message&quot;: &quot;No callback URL found for this client&quot;,
    &quot;data&quot;: null,
    &quot;errors&quot;: {
        &quot;type&quot;: &quot;CallbackNotFound&quot;,
        &quot;details&quot;: null
    }
}</code>
 </pre>
    </span>
<span id="execution-results-DELETEapi-v2-callbacks" hidden>
    <blockquote>Received response<span
                id="execution-response-status-DELETEapi-v2-callbacks"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-DELETEapi-v2-callbacks"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-DELETEapi-v2-callbacks" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-DELETEapi-v2-callbacks">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-DELETEapi-v2-callbacks" data-method="DELETE"
      data-path="api/v2/callbacks"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('DELETEapi-v2-callbacks', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-DELETEapi-v2-callbacks"
                    onclick="tryItOut('DELETEapi-v2-callbacks');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-DELETEapi-v2-callbacks"
                    onclick="cancelTryOut('DELETEapi-v2-callbacks');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-DELETEapi-v2-callbacks"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-red">DELETE</small>
            <b><code>api/v2/callbacks</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="DELETEapi-v2-callbacks"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="DELETEapi-v2-callbacks"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="DELETEapi-v2-callbacks"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                <h1 id="meters">Meters</h1>

    

                                <h2 id="meters-POSTapi-v2-meters--meter_number--valve">Update Meter Valve Status</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Toggle a meter's valve to either open or closed state.</p>
<p><strong>Response Types:</strong></p>
<ul>
<li><strong>Request examples labeled &quot;(Direct operation)&quot;</strong> are returned immediately after the request</li>
<li><strong>Request examples labeled &quot;(sent to your callback URL)&quot;</strong> represent payloads that will be delivered to your callback URL for asynchronous operations</li>
</ul>
<p><strong>Asynchronous Flow (for supported meter types):</strong></p>
<ol>
<li>Send the valve control request</li>
<li>Receive immediate response with message_id and status: &quot;pending&quot;</li>
<li>Wait for callback to your registered webhook URL with the final result</li>
</ol>
<p><strong>Callback URL Requirements:</strong></p>
<ul>
<li>Must accept HTTP POST requests</li>
<li>Must respond with HTTP 200 status for successful delivery</li>
<li>Should handle JSON payload as shown in callback examples below</li>
</ul>
<p><strong>Callback Security &amp; Headers:</strong>
Callbacks are sent as HTTP POST requests with these headers:</p>
<ul>
<li>Content-Type: application/json</li>
<li>User-Agent: Hydro-Pro-Webhook/1.0</li>
<li>X-Webhook-Signature: sha256=[signature] (if secret token configured)</li>
</ul>
<p><strong>Security:</strong> If you've configured a secret token, verify the X-Webhook-Signature header using HMAC SHA256:
sha256(hmac(json_payload, your_secret_token))</p>
<p><strong>Retry Policy:</strong> Failed callback deliveries retry up to 3 times with intervals of 1 minute, 5 minutes, and 15 minutes.</p>
<p><strong>Note:</strong> The callback response examples below show payloads that will be sent TO YOUR CALLBACK URL, not returned by this API.</p>

<span id="example-requests-POSTapi-v2-meters--meter_number--valve">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "https://backend.buxton.progressiveutilities.com/api/v2/meters/MTR123456789/valve" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"valve_status\": 1
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://backend.buxton.progressiveutilities.com/api/v2/meters/MTR123456789/valve"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "valve_status": 1
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>


<div class="php-example">
    <pre><code class="language-php">$client = new \GuzzleHttp\Client();
$url = 'https://backend.buxton.progressiveutilities.com/api/v2/meters/MTR123456789/valve';
$response = $client-&gt;post(
    $url,
    [
        'headers' =&gt; [
            'Authorization' =&gt; 'Bearer {YOUR_AUTH_KEY}',
            'Content-Type' =&gt; 'application/json',
            'Accept' =&gt; 'application/json',
        ],
        'json' =&gt; [
            'valve_status' =&gt; 1,
        ],
    ]
);
$body = $response-&gt;getBody();
print_r(json_decode((string) $body));</code></pre></div>


<div class="python-example">
    <pre><code class="language-python">import requests
import json

url = 'https://backend.buxton.progressiveutilities.com/api/v2/meters/MTR123456789/valve'
payload = {
    "valve_status": 1
}
headers = {
  'Authorization': 'Bearer {YOUR_AUTH_KEY}',
  'Content-Type': 'application/json',
  'Accept': 'application/json'
}

response = requests.request('POST', url, headers=headers, json=payload)
response.json()</code></pre></div>

</span>

<span id="example-responses-POSTapi-v2-meters--meter_number--valve">
            <blockquote>
            <p>Example response (200, Valve control request initiated successfully (Direct operation)):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: true,
    &quot;message&quot;: &quot;Valve control request initiated&quot;,
    &quot;data&quot;: {
        &quot;meter_number&quot;: &quot;MTR123456789&quot;,
        &quot;message_id&quot;: &quot;MSG-2025091212345678&quot;,
        &quot;requested_valve_status&quot;: &quot;close&quot;,
        &quot;message&quot;: &quot;Request submitted successfully. Result will be delivered via callback.&quot;,
        &quot;status&quot;: &quot;pending&quot;
    },
    &quot;errors&quot;: null
}</code>
 </pre>
            <blockquote>
            <p>Example response (200, Callback - Valve Closed Successfully (sent to your callback URL)):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: true,
    &quot;message&quot;: &quot;Valve closed successfully&quot;,
    &quot;data&quot;: {
        &quot;event_type&quot;: &quot;valve_status_update&quot;,
        &quot;meter_number&quot;: &quot;MTR123456789&quot;,
        &quot;requested_action&quot;: &quot;valve-control&quot;,
        &quot;valve_status&quot;: &quot;closed&quot;,
        &quot;timestamp&quot;: &quot;2025-09-12T10:30:00.000Z&quot;,
        &quot;message_id&quot;: &quot;MSG-2025091212345678&quot;
    },
    &quot;errors&quot;: null
}</code>
 </pre>
            <blockquote>
            <p>Example response (200, Callback - Valve Opened Successfully (sent to your callback URL)):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: true,
    &quot;message&quot;: &quot;Valve opened successfully&quot;,
    &quot;data&quot;: {
        &quot;event_type&quot;: &quot;valve_status_update&quot;,
        &quot;meter_number&quot;: &quot;MTR123456789&quot;,
        &quot;requested_action&quot;: &quot;valve-control&quot;,
        &quot;valve_status&quot;: &quot;open&quot;,
        &quot;timestamp&quot;: &quot;2025-09-12T10:30:00.000Z&quot;,
        &quot;message_id&quot;: &quot;MSG-2025091212345678&quot;
    },
    &quot;errors&quot;: null
}</code>
 </pre>
            <blockquote>
            <p>Example response (200, Callback - Operation Timeout (sent to your callback URL)):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: false,
    &quot;message&quot;: &quot;Operation timed out&quot;,
    &quot;data&quot;: {
        &quot;event_type&quot;: &quot;valve_status_update&quot;,
        &quot;meter_number&quot;: &quot;MTR123456789&quot;,
        &quot;requested_action&quot;: &quot;valve-control&quot;,
        &quot;valve_status&quot;: &quot;unknown&quot;,
        &quot;timestamp&quot;: &quot;2025-09-12T10:30:00.000Z&quot;,
        &quot;message_id&quot;: &quot;MSG-2025091212345678&quot;
    },
    &quot;errors&quot;: {
        &quot;type&quot;: &quot;CallbackError&quot;,
        &quot;details&quot;: &quot;Operation timed out&quot;
    }
}</code>
 </pre>
            <blockquote>
            <p>Example response (200, Callback - Unknown Status (sent to your callback URL)):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: false,
    &quot;message&quot;: &quot;Unknown status: 999&quot;,
    &quot;data&quot;: {
        &quot;event_type&quot;: &quot;valve_status_update&quot;,
        &quot;meter_number&quot;: &quot;MTR123456789&quot;,
        &quot;requested_action&quot;: &quot;valve-control&quot;,
        &quot;valve_status&quot;: &quot;unknown&quot;,
        &quot;timestamp&quot;: &quot;2025-09-12T10:30:00.000Z&quot;,
        &quot;message_id&quot;: &quot;MSG-2025091212345678&quot;
    },
    &quot;errors&quot;: {
        &quot;type&quot;: &quot;CallbackError&quot;,
        &quot;details&quot;: &quot;Unknown status: 999&quot;
    }
}</code>
 </pre>
            <blockquote>
            <p>Example response (404, Meter not found (Direct operation)):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: false,
    &quot;message&quot;: &quot;Meter not found&quot;,
    &quot;data&quot;: null,
    &quot;errors&quot;: {
        &quot;type&quot;: &quot;ModelNotFoundException&quot;,
        &quot;details&quot;: null
    }
}</code>
 </pre>
            <blockquote>
            <p>Example response (422, Valve operation failed (Direct operation)):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: false,
    &quot;message&quot;: &quot;Failed, please contact website admin for help&quot;,
    &quot;data&quot;: null,
    &quot;errors&quot;: {
        &quot;type&quot;: &quot;ValveOperationError&quot;,
        &quot;details&quot;: null
    }
}</code>
 </pre>
            <blockquote>
            <p>Example response (422, Failed to initiate valve control request (Direct operation)):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: false,
    &quot;message&quot;: &quot;Failed to initiate valve control request&quot;,
    &quot;data&quot;: null,
    &quot;errors&quot;: {
        &quot;type&quot;: &quot;ValveOperationError&quot;,
        &quot;details&quot;: null
    }
}</code>
 </pre>
            <blockquote>
            <p>Example response (500, Server error (Direct operation)):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: false,
    &quot;message&quot;: &quot;An unexpected error occurred while processing the valve control request&quot;,
    &quot;data&quot;: null,
    &quot;errors&quot;: {
        &quot;type&quot;: &quot;ServerError&quot;,
        &quot;details&quot;: &quot;Specific error message details&quot;
    }
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-v2-meters--meter_number--valve" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v2-meters--meter_number--valve"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v2-meters--meter_number--valve"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v2-meters--meter_number--valve" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v2-meters--meter_number--valve">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v2-meters--meter_number--valve" data-method="POST"
      data-path="api/v2/meters/{meter_number}/valve"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v2-meters--meter_number--valve', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v2-meters--meter_number--valve"
                    onclick="tryItOut('POSTapi-v2-meters--meter_number--valve');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v2-meters--meter_number--valve"
                    onclick="cancelTryOut('POSTapi-v2-meters--meter_number--valve');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v2-meters--meter_number--valve"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v2/meters/{meter_number}/valve</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v2-meters--meter_number--valve"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v2-meters--meter_number--valve"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v2-meters--meter_number--valve"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>meter_number</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="meter_number"                data-endpoint="POSTapi-v2-meters--meter_number--valve"
               value="MTR123456789"
               data-component="url">
    <br>
<p>The meter number. Example: <code>MTR123456789</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>valve_status</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="valve_status"                data-endpoint="POSTapi-v2-meters--meter_number--valve"
               value="1"
               data-component="body">
    <br>
<p>The desired valve status (1 for open, 0 for closed). Example: <code>1</code></p>
        </div>
        </form>

                    <h2 id="meters-GETapi-v2-meters--meter_number--readings">Get Meter Readings</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Retrieve the latest meter readings for a given meter number.</p>

<span id="example-requests-GETapi-v2-meters--meter_number--readings">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "https://backend.buxton.progressiveutilities.com/api/v2/meters/MTR123456789/readings" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://backend.buxton.progressiveutilities.com/api/v2/meters/MTR123456789/readings"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="php-example">
    <pre><code class="language-php">$client = new \GuzzleHttp\Client();
$url = 'https://backend.buxton.progressiveutilities.com/api/v2/meters/MTR123456789/readings';
$response = $client-&gt;get(
    $url,
    [
        'headers' =&gt; [
            'Authorization' =&gt; 'Bearer {YOUR_AUTH_KEY}',
            'Content-Type' =&gt; 'application/json',
            'Accept' =&gt; 'application/json',
        ],
    ]
);
$body = $response-&gt;getBody();
print_r(json_decode((string) $body));</code></pre></div>


<div class="python-example">
    <pre><code class="language-python">import requests
import json

url = 'https://backend.buxton.progressiveutilities.com/api/v2/meters/MTR123456789/readings'
headers = {
  'Authorization': 'Bearer {YOUR_AUTH_KEY}',
  'Content-Type': 'application/json',
  'Accept': 'application/json'
}

response = requests.request('GET', url, headers=headers)
response.json()</code></pre></div>

</span>

<span id="example-responses-GETapi-v2-meters--meter_number--readings">
            <blockquote>
            <p>Example response (200, Meter readings retrieved successfully):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: true,
    &quot;message&quot;: &quot;Current Meter Readings&quot;,
    &quot;data&quot;: {
        &quot;current_meter_readings&quot;: 345.67,
        &quot;last_reading_date&quot;: &quot;2025-08-09 12:34:56&quot;
    },
    &quot;errors&quot;: null
}</code>
 </pre>
            <blockquote>
            <p>Example response (404, Meter not found):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: false,
    &quot;message&quot;: &quot;No query results for model [Meter]&quot;,
    &quot;data&quot;: null,
    &quot;errors&quot;: {
        &quot;type&quot;: &quot;ModelNotFoundException&quot;,
        &quot;details&quot;: null
    }
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v2-meters--meter_number--readings" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v2-meters--meter_number--readings"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v2-meters--meter_number--readings"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v2-meters--meter_number--readings" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v2-meters--meter_number--readings">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v2-meters--meter_number--readings" data-method="GET"
      data-path="api/v2/meters/{meter_number}/readings"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v2-meters--meter_number--readings', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v2-meters--meter_number--readings"
                    onclick="tryItOut('GETapi-v2-meters--meter_number--readings');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v2-meters--meter_number--readings"
                    onclick="cancelTryOut('GETapi-v2-meters--meter_number--readings');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v2-meters--meter_number--readings"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v2/meters/{meter_number}/readings</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v2-meters--meter_number--readings"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v2-meters--meter_number--readings"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v2-meters--meter_number--readings"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>meter_number</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="meter_number"                data-endpoint="GETapi-v2-meters--meter_number--readings"
               value="MTR123456789"
               data-component="url">
    <br>
<p>The unique meter number. Example: <code>MTR123456789</code></p>
            </div>
                    </form>

            

        
    </div>
    <div class="dark-box">
                    <div class="lang-selector">
                                                        <button type="button" class="lang-button" data-language-name="bash">bash</button>
                                                        <button type="button" class="lang-button" data-language-name="javascript">javascript</button>
                                                        <button type="button" class="lang-button" data-language-name="php">php</button>
                                                        <button type="button" class="lang-button" data-language-name="python">python</button>
                            </div>
            </div>
</div>
</body>
</html>
