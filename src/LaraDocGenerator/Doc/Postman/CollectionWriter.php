<?php

namespace LaraDocGenerator\Doc\Postman;

use Ramsey\Uuid\Uuid;
use Illuminate\Support\Collection;

class CollectionWriter
{
    /**
     * @var string
     */
    private $group;

    /**
     * @var array
     */
    private $routes;

    /**
     * CollectionWriter constructor.
     * @param $group
     * @param Collection $routes
     */
    public function __construct($group, Collection $routes)
    {
        $this->group = $group;
        $this->routes = $routes;
    }

    public function getCollection()
    {
        $collection = [
            'variables' => [],
            'info' => [
                'name' => ucfirst($this->group)." API Collection",
                '_postman_id' => Uuid::uuid4()->toString(),
                'description' => '',
                'schema' => 'https://schema.getpostman.com/json/collection/v2.0.0/collection.json',
            ],
            'item' => $this->routes->map(function ($route) {
                return [
                    'name' => $route['title'] != '' ? $route['title'] : url($route['uri']),
                    'request' => [
                        'url' => url($route['uri']),
                        'method' => $route['methods'][0],
                        'body' => [
                            'mode' => 'formdata',
                            'formdata' => collect($route['parameters'])->map(function ($parameter, $key) {
                                return [
                                    'key' => $key,
                                    'value' => isset($parameter['value']) ? $parameter['value'] : '',
                                    'type' => 'text',
                                    'enabled' => true,
                                ];
                            })->values()->toArray(),
                        ],
                        'description' => $route['description'],
                    ],
                    'response' => []
                ];
            })->toArray(),
        ];



        return json_encode($collection);
    }
}
