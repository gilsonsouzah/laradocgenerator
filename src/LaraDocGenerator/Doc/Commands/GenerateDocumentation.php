<?php

namespace LaraDocGenerator\Doc\Commands;

use Illuminate\Console\Command;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use LaraDocGenerator\Doc\Generators\AbstractGenerator;
use LaraDocGenerator\Doc\Generators\LaravelGenerator;
use LaraDocGenerator\Doc\Postman\CollectionWriter;
use Mpociot\Documentarian\Documentarian;
use Mpociot\Reflection\DocBlock;
use ReflectionClass;

class GenerateDocumentation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:generate 
                            {--output=public/docs : The output path for the generated documentation}
                            {--routePrefix= : The route prefix to use for generation}
                            {--routes=* : The route names to use for generation}
                            {--middleware= : The middleware to use for generation}
                            {--noPostmanCollection : Disable Postman collection creation}
                            {--useMiddlewares : Use all configured route middlewares}
                            {--force : Force rewriting of existing routes}
                            {--header=* : Custom HTTP headers to add to the example requests.}
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cria a documentação da API';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $generator = new LaravelGenerator();

        $allowedRoutes = $this->option('routes');
        $routePrefix = $this->option('routePrefix');
        $middleware = $this->option('middleware');

        if ($routePrefix === null && !count($allowedRoutes) && $middleware === null) {
            $this->error('Informe uma rota ou prefixo para geração da documentação.');
        }

        $generator->prepareMiddleware($this->option('useMiddlewares'));

        $parsedRoutes = $this->processLaravelRoutes($generator, $allowedRoutes, $routePrefix, $middleware);
        $parsedRoutes = collect($parsedRoutes)->groupBy('resource')->sort(function ($a, $b) {

            return strcmp($a->first()['resource'], $b->first()['resource']);
        });

        $this->writeMarkdown($parsedRoutes);
    }

    /**
     * Processa as rotas do laravel e gera a documentação da rota
     * @param AbstractGenerator $generator Laravel Generator
     * @param $allowedRoutes array Rotas permitidas na geração da documentação
     * @param $routePrefix string Prefixo de rotas a serem geradas
     * @param $middleware array Middlewares a serem inicializados
     * @return array
     */
    private function processLaravelRoutes(AbstractGenerator $generator, $allowedRoutes, $routePrefix, $middleware)
    {
        $routes = $this->getRoutes();
        $parsedRoutes = [];

        foreach ($routes as $route) {
            if (in_array($route->getName(), $allowedRoutes) ||
                str_is($routePrefix, $route->uri()) || in_array($middleware, $route->middleware())
            ) {
                if ($this->isValidRoute($route) &&
                    $this->isRouteVisibleForDocumentation($route->getAction()['uses'])
                ) {
                    $parsedRoutes[] = $generator->processRoute($route, $this->option('header'));
                    $this->info('Rota processada: [' . implode(
                        ',',
                        $route->methods()
                    ) . '] ' . $this->addRouteModelBindings($route));
                } else {
                    $this->warn('Rota ignorada: [' . implode(
                        ',',
                        $route->methods()
                    ) . '] ' . $this->addRouteModelBindings($route));
                }
            }
        }

        return $parsedRoutes;
    }

    /**
     * Retorna as rotas
     * @return RouteCollection
     */
    private function getRoutes()
    {
        return Route::getRoutes();
    }

    /**
     * Verifica se a rota é aplicavel a documentação
     * @param $route Route rota atual
     * @return bool
     */
    private function isValidRoute($route)
    {
        return !is_callable($route->getAction()['uses']) && !is_null($route->getAction()['uses']);
    }

    /**
     * Verifica se a rota é visivel para a documentação
     * @param $route string Rota
     * @return bool
     */
    private function isRouteVisibleForDocumentation($route)
    {
        list($class, $method) = explode('@', $route);
        $reflection = new ReflectionClass($class);
        $comment = $reflection->getMethod($method)->getDocComment();
        if ($comment) {
            $phpdoc = new DocBlock($comment);

            return (bool)collect($phpdoc->getTags())
                ->filter(function ($tag) use ($route) {
                    return $tag->getName() === 'showInAPIDocumentation';
                })
                ->count();
        }

        return false;
    }

    /**
     * @param  Collection $parsedRoutes
     *
     * @return void
     */
    private function writeMarkdown($parsedRoutes)
    {
        $outputPath = $this->option('output');
        $docDirectory = $outputPath . DIRECTORY_SEPARATOR . 'source' . DIRECTORY_SEPARATOR;

        $documentarian = new Documentarian();
        if (!is_dir($outputPath.'/assets')) {
            $documentarian->create($outputPath);
        }

        if (!is_dir($docDirectory.'/groups')) {
            mkdir($docDirectory.'/groups');
        }

        $parsedRouteOutput = $parsedRoutes->map(function ($routeGroup) {
            return $routeGroup->map(function ($route) {
                $route['output'] = (string)view('apidoc::partials.route')->with('parsedRoute', $route);

                return $route;
            });
        });

        foreach ($parsedRouteOutput as $group => $routes) {
            $this->info("Processando o grupo {$group}");


            $targetFile = $docDirectory . 'groups' . DIRECTORY_SEPARATOR . $group;

            $groupMarkdown = view('apidoc::partials.group')
                ->with('group', $group)
                ->with('routes', $routes);

            /*
            * In case the target file already exists, we should check if the documentation was modified
            * and skip the modified parts of the routes.
            */
            if (file_exists($targetFile.'.md')) {
                $compareDocumentation = file_get_contents($targetFile.'.md');

                $routes = $routes->transform(function ($route) use ($groupMarkdown, $compareDocumentation) {
                    if (preg_match(
                        '/<!-- START_' . $route['id'] . ' -->(.*)<!-- END_' . $route['id'] . ' -->/is',
                        $groupMarkdown,
                        $routeMatch
                    )) {
                        $routeDocumentationChanged = (
                            preg_match(
                                '/<!-- START_' . $route['id'] . ' -->(.*)<!-- END_' . $route['id'] . ' -->/is',
                                $compareDocumentation,
                                $compareMatch
                            ) && $compareMatch[1] !== $routeMatch[1]);
                        if ($routeDocumentationChanged === false || $this->option('force')) {
                            if ($routeDocumentationChanged) {
                                $this->warn('Sobrescrevendo alterações manuais em  [' . implode(
                                    ',',
                                    $route['methods']
                                ) . '] ' . $route['uri']);
                            }
                        } else {
                            $this->warn('Ignorando rota modificada manualmente [' . implode(
                                ',',
                                $route['methods']
                            ) . '] ' . $route['uri']);

                            $route['output'] = $compareMatch[0];
                        }
                    }

                    return $route;
                });

                $groupMarkdown = view('apidoc::partials.group')
                    ->with('group', $group)
                    ->with('routes', $routes);
            }

            file_put_contents($targetFile.'.md', $groupMarkdown);

            if ($this->option('noPostmanCollection') !== true) {
                $this->info('Gerandos as colections do postman');

                file_put_contents($targetFile.'Postman.json', $this->generatePostmanCollection($group, $routes));
            }
        }
    }

    /**
     * Gera a coleção do postman das rotas processadas na documentação
     * @param $group string Grupo de rotas
     * @param $routes Collection Rotas
     * @return string
     */
    private function generatePostmanCollection($group, $routes)
    {
        $writer = new CollectionWriter($group, $routes);

        return $writer->getCollection();
    }

    /**
     * Adiciona os models bindings na rota
     * @param $route
     * @return mixed
     */
    private function addRouteModelBindings($route)
    {
        $uri = $route->uri();
        $uri = preg_replace('/({+\w+})/', '{id}', $uri);

        return $uri;
    }
}
