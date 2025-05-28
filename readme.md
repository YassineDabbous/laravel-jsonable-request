
---

# Laravel Jsonable Request

[![Latest Version on Packagist](https://img.shields.io/packagist/v/yassinedabbous/laravel-jsonable-request.svg?style=flat-square)](https://packagist.org/packages/yassinedabbous/laravel-jsonable-request)
[![License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)

Seamlessly build and send dynamic HTTP requests within your Laravel application using structured array/JSON templates. Define your API calls once, and inject variable data on the fly.

## ✨ Features

*   **Template-driven Requests:** Define HTTP requests (endpoint, method, headers, body, auth) as reusable PHP arrays.
*   **Dynamic Data Interpolation:** Substitute placeholders (`{{key}}`) in your templates with runtime data, supporting nested arrays and preserving original data types (e.g., `int`, `bool`, `array`).
*   **Flexible Body Formats:** Automatically handles `query` parameters for GET requests and `json` bodies for others by default, with options for explicit control.
*   **Authentication Support:** Out-of-the-box support for Basic, Digest, and Bearer Token authentication.
*   **Built-in Validation:** Ensures your request templates are well-formed before processing.

## 🚀 Installation

You can install the package via Composer:

```bash
composer require yassinedabbous/laravel-jsonable-request
```

The package will automatically register its service provider.

## 📖 Usage

The `RequestBuilder` class is the core of this package. You can resolve it from the Laravel container or instantiate it directly.

```php
use YassineDabbous\JsonableRequest\RequestBuilder;

// Option 1: Instantiate directly
$builder = new RequestBuilder();

// Option 2: Resolve from container (if you've configured binding in your service provider)
// $builder = app(YassineDabbous\JsonableRequest\RequestBuilderContract::class);
```

### Basic GET Request

Define a template and provide the dynamic data:

```php
$template = [
    'endpoint' => 'https://jsonplaceholder.typicode.com/posts/{{postId}}',
    'method' => 'GET',
    'data' => [ // Data for query parameters in GET requests
        'comments' => '{{includeComments}}'
    ]
];

$data = [
    'postId' => 1,
    'includeComments' => 'true' // Example: can be 'true' or 'false'
];

$response = $builder->send($template, $data);

if ($response->ok()) {
    $post = $response->json();
    // ... handle post data
}
```

### POST Request with JSON Body

The package defaults to a `json` body format for `POST` requests if not specified. Data types provided in `$data` will be preserved in the JSON body.

```php
$template = [
    'endpoint' => 'https://jsonplaceholder.typicode.com/posts',
    'method' => 'POST',
    'headers' => [
        'X-Request-ID' => '{{requestId}}'
    ],
    'data' => [ // Data for JSON body in POST requests
        'title' => '{{postTitle}}',
        'body' => '{{postContent}}',
        'userId' => '{{authorId}}',
        'tags' => ['coding', '{{dynamicTag}}'],
        'is_published' => '{{publishedStatus}}'
    ],
    'body_format' => 'json' // Explicitly set, though 'json' is default for POST
];

$data = [
    'requestId' => uniqid('req_'),
    'postTitle' => 'My First Dynamic Post',
    'postContent' => 'This content was generated dynamically.',
    'authorId' => 123, // This integer will be preserved
    'dynamicTag' => 'laravel',
    'publishedStatus' => true // This boolean will be preserved
];

$response = $builder->send($template, $data);

if ($response->created()) {
    $newPost = $response->json();
    // ...
}
```

### Authentication

You can specify authentication details in the `auth` key.

#### Basic Authentication

```php
$template = [
    'endpoint' => 'https://api.example.com/secure/data',
    'method' => 'GET',
    'auth' => [
        'type' => 'basic',
        'username' => '{{apiUser}}',
        'password' => '{{apiPass}}'
    ]
];
$data = ['apiUser' => 'admin', 'apiPass' => 'super_secret'];
$response = $builder->send($template, $data);
```

#### Bearer Token Authentication

```php
$template = [
    'endpoint' => 'https://api.example.com/auth/resource',
    'method' => 'GET',
    'auth' => [
        'type' => 'token', // Uses withToken() in Laravel Http Client
        'token' => '{{accessToken}}'
    ]
];
$data = ['accessToken' => 'your_jwt_token_here'];
$response = $builder->send($template, $data);
```

#### Digest Authentication

```php
$template = [
    'endpoint' => 'https://api.example.com/digest/auth',
    'method' => 'GET',
    'auth' => [
        'type' => 'digest',
        'username' => '{{digestUser}}',
        'password' => '{{digestPass}}'
    ]
];
$data = ['digestUser' => 'digest_user', 'digestPass' => 'digest_secret'];
$response = $builder->send($template, $data);
```

### Understanding `parse()` and `send()`

The `RequestBuilder` provides two public methods:

*   `parse(array $template, array $data): array`
    *   This method takes a raw template and your dynamic data, performing all the interpolation and validation.
    *   It returns the fully prepared template array.
    *   Use this if you need to inspect or modify the template *after* interpolation but *before* sending the request.

*   `send(array $template, ?array $data = null): Response`
    *   This is the primary method for dispatching requests.
    *   If `$data` is provided (recommended), it first calls `parse()` internally to prepare the template, then sends the request.
    *   If `$data` is `null`, it assumes the provided `$template` is already parsed and validated, and proceeds to send the request directly. This is useful if you manually called `parse()` beforehand.

```php
// Common usage: parse and send in one go
$response = $builder->send($yourTemplate, $yourData);

// Advanced usage: Parse, modify, then send
$parsedTemplate = $builder->parse($yourTemplate, $yourData);
// Example: Add an extra header dynamically after parsing
$parsedTemplate['headers']['X-App-Version'] = '1.0.0';
$response = $builder->send($parsedTemplate); // No $data needed here
```

## Template Structure Reference

The `$template` array supports the following keys:

*   `endpoint` (string, **required**): The full URL for the request.
*   `method` (string, default: `'POST'`): The HTTP method (e.g., `'GET'`, `'POST'`, `'PUT'`, `'DELETE'`). Case-insensitive.
*   `headers` (array, default: `[]`): An associative array of custom headers.
*   `data` (array, default: `[]`):
    *   For `GET` requests (`body_format: 'query'`): This data will be appended as URL query parameters.
    *   For `POST`/`PUT`/`PATCH` requests (`body_format: 'json'` or `'form_params'`): This data will form the request body.
    *   Supports deeply nested interpolation.
*   `body_format` (string, default: `'query'` for `GET` methods, `'json'` for others):
    *   `'query'`: Data sent as URL query parameters.
    *   `'json'`: Data sent as a JSON request body (`Content-Type: application/json`).
    *   `'form_params'`: Data sent as `x-www-form-urlencoded` (`Content-Type: application/x-www-form-urlencoded`).
    *   (Future: May support `'multipart'` for file uploads).
*   `auth` (array, default: `[]`): Authentication details.
    *   `type` (string): `'basic'`, `'digest'`, or `'token'`.
    *   For `'basic'` or `'digest'`: requires `username` (string) and `password` (string).
    *   For `'token'`: requires `token` (string).

## Placeholder Interpolation Details

Placeholders are defined using **double braces `{{key}}`** to distinguish them from Laravel's route parameters.

The interpolation logic is robust:

*   **Exact Match:** If a string in your template is *exactly* `{{key}}` (e.g., `data => ['user_id' => '{{id}}']`), it will be replaced by the corresponding value from `$data` while **preserving its original PHP data type** (e.g., `int`, `bool`, `array`, `float`). This is crucial for correctly forming JSON bodies.
    *   Example: `data = ['user_id' => '{{user_id}}']`, `data_vars = ['user_id' => 123]`. Result: `['user_id' => 123]` (integer).
*   **Partial Match:** If a string contains a placeholder along with other text (e.g., `'Hello {{name}}'`, or `'User ID: {{id}}'`), the placeholder will be replaced by the **string representation** of its corresponding value from `$data`.
    *   **Important Note:** If the corresponding value in `$data` for a placeholder in a *partial string* is an `array` or `object`, that placeholder **will NOT be replaced** and will remain in the string, as arrays/objects cannot be directly embedded into strings.
    *   Example: `data = ['message' => 'Hello {{name}}']`, `data_vars = ['name' => 'Alice']`. Result: `['message' => 'Hello Alice']`.
    *   Example: `data = ['details' => 'Items: {{item_list}}']`, `data_vars = ['item_list' => ['apple', 'banana']]`. Result: `['details' => 'Items: {{item_list}}']` (placeholder remains).

## 🛡️ Validation & Error Handling

The `RequestBuilder` includes built-in validation to ensure template integrity:

*   Throws `InvalidArgumentException` if `endpoint` is missing.
*   Throws `InvalidArgumentException` if `auth` type `basic` or `digest` is used but `username` or `password` are missing.
*   Other validations (e.g., for unknown `method` or `body_format`) can be added.


## 🙌 Contributing

Contributions are welcome! Please feel free to open an issue or submit a pull request.

## 📄 License

This package is open-sourced software licensed under the [MIT license](LICENSE.md).

---