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

        $template['auth'] ??= [];
        $type = $template['auth']['type'] ?? null;
        if($type === 'basic' && (!isset($template['auth']['username']) || !isset($template['auth']['password']))){
            throw new InvalidArgumentException("Basic auth require a username and a password.");
        }
        if($type === 'digest' && (!isset($template['auth']['username']) || !isset($template['auth']['password']))){
            throw new InvalidArgumentException("Digest auth require a username and a password.");
        }
        
        $template['headers'] ??= [];
        $template['data'] ??= [];
        $template['method'] ??= 'POST';
        $template['body_format'] ??= (strtoupper($template['method']) === 'GET' ? 'query' : 'json');

        return $template;
    }
    
    

    public function parse(array $template, array $data): array
    {
        $template = $this->validate($template);

        $keys = array_map(fn($v) => "{{{$v}}}", array_keys($data));
        $values = array_values($data);

        $template['endpoint'] = $this->interpolate($template['endpoint'], $keys, $values);

        $template['headers'] = $this->interpolate($template['headers'], $keys, $values);

        $template['data'] = $this->interpolate($template['data'], $keys, $values);
        
        $template['auth'] = $this->interpolate($template['auth'], $keys, $values);
        
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
        
        $request = Http::withHeaders($headers); // Http::createPendingRequest() require L>11

        if(count($template['auth'])){
            $type = $template['auth']['type'] ?? 'basic';
            if($type === 'basic'){
                $request->withBasicAuth($template['auth']['username'], $template['auth']['password']);
            } else if ($type === 'digest'){
                $request->withDigestAuth($template['auth']['username'], $template['auth']['password']);
            } else if (isset( $template['auth']['token'] )){
                $request->withToken($template['auth']['token']);
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


    /** Recursively replace values while preserving data types. */
    private function interpolate(mixed $value, array $keys, array $values): mixed
    {
        if (is_array($value)) {
            return array_map(fn($v) => $this->interpolate($v, $keys, $values), $value);
        }

        if (is_string($value)) {
            // Check for exact match first to preserve original data type
            foreach ($keys as $index => $keyPlaceholder) {
                if ($value === $keyPlaceholder) {
                    return $values[$index];
                }
            }

            // non-scalar types (arrays, objects) would cause "Array to string conversion"
            $scalarKeys = [];
            $scalarValues = [];
            foreach ($keys as $index => $keyPlaceholder) {
                $originalValue = $values[$index];
                if (is_scalar($originalValue) || is_null($originalValue)) {
                    $scalarKeys[] = $keyPlaceholder;
                    $scalarValues[] = $originalValue;
                }
            }
            return str_replace($scalarKeys, $scalarValues, $value);
        }
        
         // Return non-string, non-array values as is
        return $value;
    }

    
}