<?php

namespace LaraDocGenerator\Doc\Tests;

use Illuminate\Routing\Route;
use Orchestra\Testbench\TestCase;
use Illuminate\Contracts\Console\Kernel;
use Dingo\Api\Provider\LaravelServiceProvider;
use LaraDocGenerator\Doc\Generators\LaravelGenerator;
use LaraDocGenerator\Doc\Tests\Fixtures\TestController;
use LaraDocGenerator\Doc\ApiDocGeneratorServiceProvider;
use Illuminate\Support\Facades\Route as RouteFacade;
use LaraDocGenerator\Doc\Tests\Fixtures\DingoTestController;
use LaraDocGenerator\Doc\Tests\Fixtures\TestResourceController;

class GenerateDocumentationTest extends TestCase
{
    /**
     * @var \LaraDocGenerator\Doc\Generators\AbstractGenerator
     */
    protected $generator;

    /**
     * Setup the test environment.
     */
    public function setUp()
    {
        parent::setUp();

        $this->generator = new LaravelGenerator();
    }

    public function tearDown()
    {
        exec('rm -rf '.__DIR__.'/../public/docs/');
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     *
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            LaravelServiceProvider::class,
            ApiDocGeneratorServiceProvider::class,
        ];
    }

    public function testConsoleCommandNeedsAPrefixOrRoute()
    {
        $output = $this->artisan('api:generate');
        $this->assertEquals(
            'Informe uma rota ou prefixo para geração da documentação.'.PHP_EOL,
            $output
        );
    }

    public function testConsoleCommandDoesNotWorkWithClosure()
    {
        RouteFacade::get('/api/closure', function () {
            return 'foo';
        });
        RouteFacade::get('/api/test', TestController::class.'@parseMethodDescription');

        $output = $this->artisan('api:generate', [
            '--routePrefix' => 'api/*',
        ]);

        $this->assertContains('Rota ignorada: [GET,HEAD] api/closure', $output);
        $this->assertContains('Rota processada: [GET,HEAD] api/test', $output);
        $this->assertContains('Processando o grupo Testes', $output);
    }

    public function testCanParseResourceRoutes()
    {
        RouteFacade::resource('/api/user', TestResourceController::class);
        $output = $this->artisan('api:generate', [
            '--routePrefix' => 'api/*',
        ]);

        $this->assertContains('Rota processada: [GET,HEAD] api/user', $output);
        $this->assertContains('Rota processada: [GET,HEAD] api/user/create', $output);
        $this->assertContains('Rota processada: [POST] api/user', $output);
        $this->assertContains('Rota processada: [GET,HEAD] api/user/{id}', $output);
        $this->assertContains('Rota processada: [GET,HEAD] api/user/{id}/edit', $output);
        $this->assertContains('Rota processada: [PUT,PATCH] api/user/{id}', $output);
        $this->assertContains('Rota processada: [DELETE] api/user/{id}', $output);
        $this->assertContains('Processando o grupo General', $output);
    }

    public function testGeneratedPostmanCollectionFileIsCorrect()
    {
        RouteFacade::get('/api/test', TestController::class.'@parseMethodDescription');
        RouteFacade::post('/api/fetch', TestController::class.'@fetchRouteResponse');

        $output = $this->artisan('api:generate', [
            '--routePrefix' => 'api/*',
        ]);

        $this->assertEquals($output, $output);
    }

    public function testCanAppendCustomHttpHeaders()
    {
        RouteFacade::get('/api/headers', TestController::class.'@checkCustomHeaders');

        $output = $this->artisan('api:generate', [
            '--routePrefix' => 'api/*',
            '--header' => [
                'Authorization: customAuthToken',
                'X-Custom-Header: foobar',
            ],
        ]);

        $generatedMarkdown = file_get_contents(__DIR__.'/../public/docs/source/groups/Testes.md');
        $this->assertContains('"authorization": [
        "customAuthToken"
    ],
    "x-custom-header": [
        "foobar"
    ]', $generatedMarkdown);
    }

    public function testGeneratesUTF8Responses()
    {
        RouteFacade::get('/api/utf8', TestController::class.'@utf8');

        $output = $this->artisan('api:generate', [
            '--routePrefix' => 'api/*',
        ]);

        $generatedMarkdown = file_get_contents(__DIR__.'/../public/docs/source/groups/Testes.md');
        $this->assertContains('Лорем ипсум долор сит амет', $generatedMarkdown);
    }

    public function testGenerateAPIFromResourceRoutes()
    {
        RouteFacade::resource('/api/user', TestResourceController::class);
        $output = $this->artisan('api:generate', [
            '--routePrefix' => 'api/*',
        ]);

        $output = $this->artisan('api:parse');

        $this->assertFileExists(__DIR__.'/../public/docs/index.html');
    }

    /**
     * @param string $command
     * @param array $parameters
     *
     * @return mixed
     */
    public function artisan($command, $parameters = [])
    {
        $this->app[Kernel::class]->call($command, $parameters);

        return $this->app[Kernel::class]->output();
    }
}
