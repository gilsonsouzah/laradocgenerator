<?php

namespace LaraDocGenerator\Doc\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;
use Mpociot\Documentarian\Documentarian;

class UpdateDocumentation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:parse 
                            {--location=public/docs : The documentation location}
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Criar documentação da API a partir dos markdowns.';

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
     * @return false|null
     */
    public function handle()
    {
        $outputPath = $this->option('location');
        $groupsDataContent = [];

        $infoText = view('apidoc::partials.info')
            ->with('outputPath', ltrim($outputPath, 'public/'));
        $frontMatter = view('apidoc::partials.frontmatter');

        $groupFiles = new Finder();
        $groupFiles->files()->in($outputPath.'/source/groups')->name('*.md')->sortByName();

        foreach ($groupFiles as $group) {
            $groupsDataContent[] = $group->getContents();
        }

        $documentarian = new Documentarian();
        $markDownFile = view('apidoc::documentarian')
            ->with('frontmatter', $frontMatter)
            ->with('infoText', $infoText)
            ->with('groups', $groupsDataContent)
            ->render();

        // Write output file
        file_put_contents($outputPath.DIRECTORY_SEPARATOR.'source'.DIRECTORY_SEPARATOR.'index.md', $markDownFile);
        $documentarian->generate($outputPath);

        $this->info('Gerando documentação da API em: '.$outputPath.'/public/index.html');
    }
}
