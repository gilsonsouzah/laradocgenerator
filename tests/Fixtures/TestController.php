<?php

namespace LaraDocGenerator\Doc\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * @resource Testes
 */
class TestController extends Controller
{
    public function dummy()
    {
        return '';
    }

    /**
     * Example title.
     * This will be the long description.
     * It can also be multiple lines long.
     *
     * @showInAPIDocumentation
     */
    public function parseMethodDescription()
    {
        return '';
    }

    /**
     * @showInAPIDocumentation
     */
    public function parseFormRequestRules(TestRequest $request)
    {
        return '';
    }

    /**
     * @showInAPIDocumentation
     */
    public function addRouteBindingsToRequestClass(DynamicRequest $request)
    {
        return '';
    }

    /**
     * @showInAPIDocumentation
     */
    public function checkCustomHeaders(Request $request)
    {
        return $request->header();
    }

    /**
     * @showInAPIDocumentation
     */
    public function fetchRouteResponse()
    {
        $fixture = new \stdClass();
        $fixture->id = 1;
        $fixture->name = 'banana';
        $fixture->color = 'red';
        $fixture->weight = 300;
        $fixture->delicious = 1;

        return [
            'id' => (int) $fixture->id,
            'name' => ucfirst($fixture->name),
            'color' => ucfirst($fixture->color),
            'weight' => $fixture->weight.' grams',
            'delicious' => (bool) $fixture->delicious,
        ];
    }

    /**
     * @showInAPIDocumentation
     */
    public function dependencyInjection(DependencyInjection $dependency, TestRequest $request)
    {
        return '';
    }

    /**
     * @showInAPIDocumentation
     */
    public function utf8()
    {
        return ['result' => 'Лорем ипсум долор сит амет'];
    }

    /***
     * @hideFromAPIDocumentation
     */
    public function skip()
    {
    }
}
