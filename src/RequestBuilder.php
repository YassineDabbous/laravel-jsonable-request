<?php
namespace YassineDabbous\JsonableRequest; 
 
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use YassineDabbous\JsonableRequest\RequestBuilderContract;
use InvalidArgumentException;

class RequestBuilder implements RequestBuilderContract
{
    public function validate(array $template): array
    {
        if (!isset($template['endpoint'])) {
            throw new InvalidArgumentException("Request template must define an 'endpoint'.");
        }

        if(isset($template['auth'])){
            $type = $auth['type'] ?? null;
            if($type === 'basic' && (!isset($auth['username']) || !isset($auth['password']))){
                throw new InvalidArgumentException("Basic auth require a username and a password.");
            }
            if($type === 'digest' && (!isset($auth['username']) || !isset($auth['password']))){
                throw new InvalidArgumentException("Basic auth require a username and a password.");
            }
        }
        
        $template['headers'] ??= [];
        $template['data'] ??= [];
        $template['method'] ??= 'POST';
        $template['body_format'] ??= (strtoupper($template['method']) === 'GET' ? 'query' : 'json');

        return $template;
    }
    
    
    public function strict_replace(array|string $search, $replace, array|string $subject): mixed {
        $subject = str_replace($search, $replace, $subject);
        if(is_numeric($subject)){
            return $subject + 0;
        }
        return $subject;
    }

    public function parse(array $template, array $data): array
    {
        $template = $this->validate($template);

        $keys = array_map(fn($v) => "{{$v}}", array_keys($data));
        $values = array_values($data);

        $template['endpoint'] = str_replace($keys, $values, $template['endpoint']);

        $template['headers'] = array_map(fn($v) => str_replace($keys, $values, $v), $template['headers']);

        $template['data'] = array_map(fn($v) => $this->strict_replace($keys, $values, $v), $template['data']);

        if(isset($template['auth'])){
            $template['auth'] = array_map(fn($v) => str_replace($keys, $values, $v), $template['auth']);
        }
        
        return $template;
    }

    public function send(array $template, ?array $data = null): Response
    {
        if($data){
            $template = $this->parse($template, $data);
        } else {
            $template = $this->validate($template);
        }

        $url = $template['endpoint'];
        $headers = $template['headers'];
        $method = $template['method'];
        $bodyFormat = $template['body_format'];
        $body = $template['data'];
        
        $request = Http::withHeaders($headers); // ::createPendingRequest(); >L11

        if($auth = $template['auth'] ?? null){
            $type = $auth['type'] ?? 'basic';
            if($type === 'basic'){
                $request->withBasicAuth($auth['username'], $auth['password']);
            } else if ($type === 'digest'){
                $request->withDigestAuth($auth['username'], $auth['password']);
            } else if (isset( $auth['token'] )){
                $request->withToken($auth['token']);
            }
        }

        return $request->send(
            $method, 
            $url,
            [
                $bodyFormat => $body,
            ]
        );
    }


    
}