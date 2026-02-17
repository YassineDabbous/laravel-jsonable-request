<?php

namespace YassineDabbous\JsonableRequest\Tests\Feature;

use YassineDabbous\JsonableRequest\RequestBuilder;
use YassineDabbous\JsonableRequest\Tests\TestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class RequestBuilderTest extends TestCase
{
    /** @var RequestBuilder */
    protected $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new RequestBuilder();
    }

    // --- Tests for validate() method ---

    /** @test */
    public function validate_method_throws_exception_for_missing_endpoint()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Request template must define an 'endpoint'.");

        $this->builder->validate([]);
    }

    /** @test */
    public function validate_method_sets_defaults_correctly()
    {
        $template = [
            'endpoint' => 'https://example.com/api',
        ];

        $validatedTemplate = $this->builder->validate($template);

        $this->assertArrayHasKey('auth', $validatedTemplate);
        $this->assertEmpty($validatedTemplate['auth']);
        $this->assertArrayHasKey('headers', $validatedTemplate);
        $this->assertEmpty($validatedTemplate['headers']);
        $this->assertArrayHasKey('data', $validatedTemplate);
        $this->assertEmpty($validatedTemplate['data']);
        $this->assertEquals('POST', $validatedTemplate['method']);
        $this->assertEquals('json', $validatedTemplate['body_format']);
    }

    /** @test */
    public function validate_method_infers_query_body_format_for_get_requests()
    {
        $template = [
            'endpoint' => 'https://example.com/api',
            'method' => 'GET',
        ];

        $validatedTemplate = $this->builder->validate($template);

        $this->assertEquals('GET', $validatedTemplate['method']);
        $this->assertEquals('query', $validatedTemplate['body_format']);
    }

    /** @test */
    public function validate_method_throws_exception_for_incomplete_basic_auth()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Basic auth require a username and a password.");

        $template = [
            'endpoint' => 'https://example.com',
            'auth' => ['type' => 'basic', 'username' => 'testuser'], // Missing password
        ];
        $this->builder->validate($template);
    }

    /** @test */
    public function validate_method_throws_exception_for_incomplete_digest_auth()
    {
        $this->expectException(InvalidArgumentException::class);
        // IMPORTANT: Fix your actual error message in RequestBuilder.php
        $this->expectExceptionMessage("Digest auth require a username and a password.");

        $template = [
            'endpoint' => 'https://example.com',
            'auth' => ['type' => 'digest', 'password' => 'testpass'], // Missing username
        ];
        $this->builder->validate($template);
    }

    /** @test */
    public function validate_method_passes_with_valid_auth_configs()
    {
        $template = [
            'endpoint' => 'https://example.com',
            'auth' => ['type' => 'basic', 'username' => 'user', 'password' => 'pass'],
        ];
        $validated = $this->builder->validate($template);
        $this->assertArrayHasKey('auth', $validated); // Should not throw

        $template = [
            'endpoint' => 'https://example.com',
            'auth' => ['type' => 'digest', 'username' => 'user', 'password' => 'pass'],
        ];
        $validated = $this->builder->validate($template);
        $this->assertArrayHasKey('auth', $validated); // Should not throw

        $template = [
            'endpoint' => 'https://example.com',
            'auth' => ['type' => 'token', 'token' => 'abc'], // No specific validation for 'token' in validate, which is fine
        ];
        $validated = $this->builder->validate($template);
        $this->assertArrayHasKey('auth', $validated); // Should not throw
    }


    // --- Tests for parse() method ---

    /** @test */
    public function parse_interpolates_endpoint_headers_and_auth()
    {
        $template = [
            'endpoint' => 'https://api.example.com/users/{{userId}}',
            'headers' => ['Authorization' => 'Bearer {{apiToken}}', 'X-App-Id' => '{{appId}}'],
            'auth' => ['type' => 'basic', 'username' => '{{user}}', 'password' => '{{pass}}'],
        ];
        $data = [
            'userId' => '123',
            'apiToken' => 'my-secret-token',
            'appId' => 'xyz',
            'user' => 'admin',
            'pass' => 'p@ss',
        ];

        $parsedTemplate = $this->builder->parse($template, $data);

        $this->assertEquals('https://api.example.com/users/123', $parsedTemplate['endpoint']);
        $this->assertEquals([
            'Authorization' => 'Bearer my-secret-token',
            'X-App-Id' => 'xyz',
        ], $parsedTemplate['headers']);
        $this->assertEquals([
            'type' => 'basic',
            'username' => 'admin',
            'password' => 'p@ss',
        ], $parsedTemplate['auth']);
    }

    /** @test */
    public function parse_interpolates_nested_data_preserving_types()
    {
        $template = [
            'endpoint' => 'https://api.example.com/data',
            'data' => [
                'id' => '{{recordId}}',
                'isActive' => '{{activeStatus}}',
                'price' => '{{itemPrice}}',
                'details' => [
                    'name' => 'Item {{itemName}}',
                    'tags' => ['{{tag1}}', '{{tag2}}'],
                ],
                'optional' => '{{missingKey}}', // Should remain as a string if key is not found
                'complex_object' => [
                    'nested_array' => [
                        ['field' => '{{nestedField}}']
                    ]
                ]
            ],
        ];
        $data = [
            'recordId' => 123,
            'activeStatus' => true,
            'itemPrice' => 99.99,
            'itemName' => 'Widget',
            'tag1' => 'electronics',
            'tag2' => 'gadget',
            'nestedField' => ['value' => 'test']
        ];

        $parsedTemplate = $this->builder->parse($template, $data);

        $expectedData = [
            'id' => 123,           // int
            'isActive' => true,    // bool
            'price' => 99.99,      // float
            'details' => [
                'name' => 'Item Widget', // partial string
                'tags' => ['electronics', 'gadget'], // array of strings
            ],
            'optional' => '{{missingKey}}', // remains string as key not found
            'complex_object' => [
                'nested_array' => [
                    ['field' => ['value' => 'test']] // array with nested array/object
                ]
            ]
        ];

        $this->assertEquals($expectedData, $parsedTemplate['data']);
    }

    /** @test */
    public function parse_handles_empty_data_without_error()
    {
        $template = [
            'endpoint' => 'https://api.example.com/test/{{id}}',
            'headers' => ['X-Foo' => '{{bar}}'],
            'data' => ['key' => '{{value}}'],
        ];
        $data = []; // No data provided for interpolation

        $parsedTemplate = $this->builder->parse($template, $data);

        // Placeholders should remain as they were not replaced
        $this->assertEquals('https://api.example.com/test/{{id}}', $parsedTemplate['endpoint']);
        $this->assertEquals(['X-Foo' => '{{bar}}'], $parsedTemplate['headers']);
        $this->assertEquals(['key' => '{{value}}'], $parsedTemplate['data']);
    }

    // --- Tests for send() method ---

    /** @test */
    public function send_makes_get_request_with_query_params()
    {
        Http::fake([
            'example.com/*' => Http::response(['status' => 'success'], 200),
        ]);

        $template = [
            'endpoint' => 'https://example.com/users',
            'method' => 'GET',
            'data' => ['id' => '{{userId}}', 'status' => 'active'],
        ];
        $data = ['userId' => 5];

        $response = $this->builder->send($template, $data);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://example.com/users?id=5&status=active'
                   && $request->method() === 'GET';
        });

        $this->assertTrue($response->ok());
        $this->assertEquals(['status' => 'success'], $response->json());
    }

    /** @test */
    public function send_makes_post_request_with_json_body()
    {
        Http::fake([
            'example.com/*' => Http::response(['message' => 'created'], 201),
        ]);

        $template = [
            'endpoint' => 'https://example.com/posts',
            'method' => 'POST',
            'data' => [
                'title' => '{{postTitle}}',
                'content' => 'Lorem ipsum',
                'tags' => ['tag1', '{{tag2}}'],
                'author_id' => '{{authorId}}',
                'is_published' => '{{published}}'
            ],
            'body_format' => 'json'
        ];
        $data = [
            'postTitle' => 'My New Post',
            'tag2' => 'testing',
            'authorId' => 123,
            'published' => true
        ];

        $response = $this->builder->send($template, $data);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://example.com/posts'
                   && $request->method() === 'POST'
                   && $request->hasHeader('Content-Type', 'application/json')
                   && $request->data()['title'] === 'My New Post'
                   && $request->data()['content'] === 'Lorem ipsum'
                   && $request->data()['tags'][1] === 'testing'
                   && $request->data()['author_id'] === 123
                   && $request->data()['is_published'] === true; // Check type preservation
        });

        $this->assertTrue($response->created());
        $this->assertEquals(['message' => 'created'], $response->json());
    }

    /** @test */
    public function send_applies_custom_headers()
    {
        Http::fake([
            'example.com/*' => Http::response([], 200),
        ]);

        $template = [
            'endpoint' => 'https://example.com/api',
            'headers' => ['X-Custom-Header' => '{{customValue}}', 'User-Agent' => 'MyPackage/1.0'],
        ];
        $data = ['customValue' => 'FooBar'];

        $this->builder->send($template, $data);

        Http::assertSent(function (Request $request) {
            return $request->hasHeader('X-Custom-Header', 'FooBar')
                   && $request->hasHeader('User-Agent', 'MyPackage/1.0');
        });
    }

    /** @test */
    public function send_applies_basic_auth()
    {
        Http::fake([
            'example.com/*' => Http::response([], 200),
        ]);

        $template = [
            'endpoint' => 'https://example.com/secure',
            'auth' => ['type' => 'basic', 'username' => '{{user}}', 'password' => '{{pass}}'],
        ];
        $data = ['user' => 'apiuser', 'pass' => 'apipass'];

        $this->builder->send($template, $data);

        Http::assertSent(function (Request $request) {
            // Basic auth header is base64 encoded "username:password"
            return $request->hasHeader('Authorization', 'Basic ' . base64_encode('apiuser:apipass'));
        });
    }

    /** @test */
    public function send_applies_bearer_token_auth()
    {
        Http::fake([
            'example.com/*' => Http::response([], 200),
        ]);

        $template = [
            'endpoint' => 'https://example.com/secure',
            'auth' => ['type' => 'token', 'token' => '{{authToken}}'],
        ];
        $data = ['authToken' => 'my-super-secret-token'];

        $this->builder->send($template, $data);

        Http::assertSent(function (Request $request) {
            return $request->hasHeader('Authorization', 'Bearer my-super-secret-token');
        });
    }

    /** @test */
    public function send_handles_digest_auth()
    {
        // NOTE: Laravel's Http fake doesn't easily expose digest auth details in a way
        // that's easy to assert directly like Basic/Bearer. You'd primarily test
        // that the `withDigestAuth` method was called on the underlying Guzzle client
        // if you were mocking Guzzle directly. For Http::fake, we mostly rely on its
        // internal logic to ensure the auth is applied.
        Http::fake([
            'example.com/*' => Http::response([], 200),
        ]);

        $template = [
            'endpoint' => 'https://example.com/digest',
            'auth' => ['type' => 'digest', 'username' => '{{user}}', 'password' => '{{pass}}'],
        ];
        $data = ['user' => 'digestuser', 'pass' => 'digestpass'];

        $this->builder->send($template, $data);

        Http::assertSent(function (Request $request) {
            // Assert that the request was made to the correct URL and method.
            // Verifying the actual Digest header contents is complex with Http::fake()
            // without deeply inspecting Guzzle options. This test confirms the flow.
            return $request->url() === 'https://example.com/digest';
        });

        // A more advanced test would require inspecting the Guzzle client options directly,
        // which might be beyond the scope of a basic Http::fake() setup.
    }

    /** @test */
    public function send_works_with_pre_parsed_template_and_null_data()
    {
        Http::fake([
            'example.com/*' => Http::response(['status' => 'ok'], 200),
        ]);

        // Simulate a template that was already parsed or has no placeholders
        $preParsedTemplate = [
            'endpoint' => 'https://example.com/static',
            'method' => 'GET',
            'headers' => ['X-Static-Header' => 'StaticValue'],
            'data' => ['key' => 'value'],
            'body_format' => 'query'
        ];

        // Call send with null data
        $response = $this->builder->send($preParsedTemplate, null);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://example.com/static?key=value'
                   && $request->hasHeader('X-Static-Header', 'StaticValue');
        });

        $this->assertTrue($response->ok());
    }

    /** @test */
    public function send_handles_empty_data_arrays()
    {
        Http::fake([
            'example.com/*' => Http::response([], 200),
        ]);

        $template = [
            'endpoint' => 'https://example.com/empty-data',
            'method' => 'POST',
            'data' => [], // Empty data array
            'body_format' => 'json'
        ];

        $this->builder->send($template, []); // No interpolation data provided either

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://example.com/empty-data'
                   && $request->method() === 'POST'
                   && $request->data() === []; // Ensure empty JSON object is sent
        });
    }
}