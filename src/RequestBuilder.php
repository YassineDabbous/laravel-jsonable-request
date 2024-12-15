<?php
namespace YassineDabbous\JsonableRequest; 
 
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use YassineDabbous\JsonableRequest\RequestBuilderContract;

class RequestBuilder implements RequestBuilderContract
{
    public function parse(array $template, array $data): array
    {
        $keys = array_map(fn($v) => "{{$v}}", array_keys($data));
        $values = array_values($data);

        $template['endpoint'] = str_replace($keys, $values, $template['endpoint']);

        $template['headers'] = array_map(fn($v) => str_replace($keys, $values, $v), $template['headers'] ?? []);

        $template['method'] = $template['method'] ?? 'POST';
        $template['body_format'] = $template['body_format'] ?? ($template['method'] == 'GET' ? 'query' : 'json');

        $template['data'] = array_map(fn($v) => str_replace($keys, $values, $v), $template['data'] ?? []);
        
        if(isset($template['auth'])){
            $template['auth'] = array_map(fn($v) => str_replace($keys, $values, $v), $template['auth'] ?? []);
        }

        return $template;
    }

    public function send(array $template, ?array $data = null): Response
    {
        if($data){
            $template = $this->parse($template, $data);
        }
                
        $url = $template['endpoint'];
        $headers = $template['headers'];
        $method = $template['method'];
        $bodyFormat = $template['body_format'];
        $body = $template['data'];
        
        $request = Http::createPendingRequest();

        if($auth = $template['auth'] ?? null){
            $type = $auth['type'] ?? 'basic';
            if($type === 'basic' && isset($auth['username']) && isset($auth['username'])){
                $request->withBasicAuth($auth['username'], $auth['password']);
            } else if ($type === 'digest' && isset($auth['username']) && isset($auth['username'])){
                $request->withDigestAuth($auth['username'], $auth['password']);
            } else if (isset( $auth['token'] )){
                $request->withToken($auth['token']);
            }
        }

        return $request->send(
            $method, 
            $url,
            [
                'headers' => $headers,
                $bodyFormat => $body,
            ]
        );
    }


    
}