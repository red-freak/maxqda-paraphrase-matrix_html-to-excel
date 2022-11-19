<?php

namespace App\Console\Commands;

use App\Models\Editor;
use App\Models\Interview;
use App\Models\Paraphrase;
use File;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\Console\Helper\ProgressBar;

class ImportHtmlCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'maxqda:import';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'imports from /storage/sources HTML-files into the SQLite-DB';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        if (!$this->confirm('To create the new paraphrases, the existing ones will be deleted. Continue?', true)) {
            exit(Command::FAILURE);
        }

        Paraphrase::truncate();
        Editor::truncate();
        Interview::truncate();
        $this->warn('All data deleted.');

        $files = File::glob(storage_path('sources/*.html'));

        foreach($files as $file) {
            $contentBlocks = $this->parseFileGetContentBlocks($file);
            $this->createInterviews($contentBlocks[0]);
            $contentRows = $this->getRawRows($contentBlocks[1]);
            $editors = $this->createEditors($contentRows);
            $this->createParaphrases($contentRows, $editors);
        }

        return Command::SUCCESS;
    }

    /**
     * @param  mixed  $file
     *
     * @return array
     */
    private function parseFileGetContentBlocks(string $file): array
    {
        $fileContent = File::get($file);
        preg_match('/<table.*?>(.*)<\/table>/s', $fileContent, $contentTable);
        $contentTable = $contentTable[1];
        preg_match_all('/<tr.*?>(.*?)<\/tr>/s', $contentTable, $contentBlocks);

        return $contentBlocks[1];
    }

    /**
     * Erstellt Interviews oder lädt sie.
     *
     * @param  string  $headerContentBlock
     *
     * @return void
     */
    private function createInterviews(string $headerContentBlock): void
    {
        preg_match_all('/<th.*?>(\S*).*?<\/th>/s', $headerContentBlock, $interviews);
        $interviews = array_unique($interviews[1]);

        $this->info('Creating or updating interviews');
        $bar = new ProgressBar($this->output, count($interviews));
        $bar->start();

        foreach($interviews as $interveiwId) {
            Interview::firstOrCreate(['id' => $interveiwId]);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
    }

    private function createEditors(array $contentRows): Collection
    {
        $editorsRaw = array_unique(data_get($contentRows, 'editor', []) ?? []);

        $this->info('Creating or updating editors');
        $bar = new ProgressBar($this->output, count($editorsRaw));
        $bar->start();

        $editors = new Collection();

        foreach($editorsRaw as $editor) {
            $editors->add(Editor::firstOrCreate(['name' => $editor]));
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        return $editors;
    }

    /**
     * @param  array  $contentRows
     * @param  Collection<Editor>  $editors
     *
     * @return void
     */
    private function createParaphrases(array $contentRows, Collection $editors): void
    {
        $rows = count(data_get($contentRows, 'editor', []));
        $this->info('Creating new paraphrases');
        $bar = new ProgressBar($this->output, $rows);
        $bar->start();

        for ($i = 0; $i < $rows; ++$i) {
            // get the correct editor
            if (!$editor = $editors->firstWhere('name', data_get($contentRows, 'editor.'.$i))) {
                throw new ModelNotFoundException('Editor not found');
            }
            // update or create the paraphrase
            Paraphrase::create([
                'editor_id' => $editor->id,
                'interview_id' => data_get($contentRows, 'interview_id.'.$i),
                'position_start' => data_get($contentRows, 'position_start.'.$i),
                'position_end' => data_get($contentRows, 'position_end.'.$i),
                'paraphrase' => data_get($contentRows, 'text.'.$i),
            ]);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
    }

    /**
     * @param $contentBlocks
     *
     * @return mixed
     */
    private function getRawRows($contentBlocks)
    {
        preg_match_all('/(?:<td.*?>|<br \/><br \/><br \/>)(?<text>.*?)<br \/><br \/>.*?(?<editor>\w*?)\s&gt;.*?(:?&gt;)?\s?(?<interview_id>.*?),\sPos\.\s(?<position_start>\d*)\s-\s(?<position_end>\d*)(?=<\/td>|\s<br \/><br \/><br \/>)/s',
            $contentBlocks,
            $contentRows
        );

        return $contentRows;
    }
}
