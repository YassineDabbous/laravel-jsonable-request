<?php
namespace YassineDabbous\JsonableRequest; 
 
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use YassineDabbous\JsonableRequest\RequestBuilderContract;

class RequestBuilder implements RequestBuilderContract
{
    public function strict_replace(array|string $search, $replace, array|string $subject): mixed {
        $subject = str_replace($search, $replace, $subject);
        if(is_numeric($subject)){
            return $subject + 0;
        }
        return $subject;
    }

    public function parse(array $template, array $data): array
    {
        $keys = array_map(fn($v) => "{{$v}}", array_keys($data));
        $values = array_values($data);

        $template['endpoint'] = str_replace($keys, $values, $template['endpoint']);

        $template['headers'] = array_map(fn($v) => str_replace($keys, $values, $v), $template['headers'] ?? []);

        $template['method'] = $template['method'] ?? 'POST';
        $template['body_format'] = $template['body_format'] ?? ($template['method'] == 'GET' ? 'query' : 'json');

        $template['data'] = array_map(fn($v) => $this->strict_replace($keys, $values, $v), $template['data'] ?? []);

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
        
        $request = Http::baseUrl($url); // ::createPendingRequest(); >L11

        if($auth = $template['auth'] ?? null){
            $type = $auth['type'] ?? 'basic';
            if($type === 'basic' && isset($auth['username']) && isset($auth['password'])){
                $request->withBasicAuth($auth['username'], $auth['password']);
            } else if ($type === 'digest' && isset($auth['username']) && isset($auth['password'])){
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