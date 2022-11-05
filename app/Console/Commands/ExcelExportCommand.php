<?php

namespace App\Console\Commands;

use App\Models\Editor;
use App\Models\Interview;
use App\Models\Paraphrase;
use Box\Spout\Common\Entity\Style\Style;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection as SupportCollection;
use Iterator;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Writer\Common\Creator\Style\StyleBuilder;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Str;
use Symfony\Component\Console\Helper\ProgressBar;

class ExcelExportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'maxqda:export';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Exports the paraphrases into an Excel-file (imported by mayqda:import)';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // prepare our Excel-file
        $now = new Carbon();
        $simpleExcelWriter = SimpleExcelWriter::create(
            storage_path('exports/export_'. Str::slug($now->toDateTimeString()) . '.xlsx')
        );
        $writer = $simpleExcelWriter->getWriter();

        // set header style
        /** @var Style $style */
        $style = (new StyleBuilder())
            ->setFontBold()
            ->setFontColor(Color::BLACK)
            ->setShouldWrapText()
            ->setBackgroundColor(Color::LIGHT_BLUE)
            ->build();

        $simpleExcelWriter->setHeaderStyle($style);

        $interviews = Interview::all();
        $interview_last = $interviews->last();

        // initialize the progress bar
        $bar = $this->initializeProgressBar($interviews);

        $interviews->each(function (Interview $interview) use ($writer, $simpleExcelWriter, $interview_last, $bar) {
            // set the interview name as sheet name
            $writer->getCurrentSheet()->setName($interview->id);
            $bar->setMessage('Exporting interview ' . $interview->id . '...');

            // collect and sort the data
            $editors = $interview->editors->unique();
            $paraphrasesByEditor = $this->getParaphrasesByEditors($editors, $interview);

            // write the paraphrases to the sheet
            $row = 1;
            $editorIterators = $this->getEditorIterators($paraphrasesByEditor);
            // add rows
            do {
                if (!$this->isLoopContinued($editorIterators)) break;

                $rowData = $this->getRowData($row, $editors, $editorIterators);
                $simpleExcelWriter->addRow($rowData);

                ++$row;
                $bar->advance();
            } while (true);

            if (!$interview->is($interview_last)) {
                $writer->addNewSheetAndMakeItCurrent();
            }
        });

        $this->finishProgressBar($bar);

        return Command::SUCCESS;
    }

    /**
     * Sort the paraphrases by editor and within by position (12-45 (id: 42) < 12-43 (6) < 12 (12) < 12 (18))
     *
     * @param  EloquentCollection  $editors
     * @param  Interview  $interview
     *
     * @return SupportCollection
     */
    private function getParaphrasesByEditors(EloquentCollection $editors, Interview $interview): SupportCollection
    {
        return $editors->mapWithKeys(
            fn($editor) => [
                $editor->name => $interview->paraphrases->where('editor_id', $editor->id)->sortBy(function (
                    Paraphrase $paraphrase
                ) {
                    // sort order should be 12-47 before 12-46 before 12, but all with 12 in order of reading it to the db
                    $sortKey = Str::padLeft($paraphrase->position_start, 6, 0);
                    $sortKey .= ($paraphrase->position_start === $paraphrase->position_end ? 999999 : Str::padLeft($paraphrase->position_end,
                        6, 0));

                    return $sortKey.Str::padLeft($paraphrase->id, 6, 0);
                })
            ]
        );
    }

    /**
     * @param  SupportCollection  $paraphrasesByEditor
     *
     * @return SupportCollection
     */
    private function getEditorIterators(SupportCollection $paraphrasesByEditor): SupportCollection
    {
        $editorIterators = new SupportCollection();
        $paraphrasesByEditor->each(function (EloquentCollection $paraphrases, string $editorName) use (&$editorIterators
        ) {
            // make sure we iterate form the beginning and add the iterator
            $iterator = $paraphrases->getIterator();
//            $iterator->rewind();
            $editorIterators->add(
                $iterator
            );
        });

        return $editorIterators;
    }

    /**
     * @param  int  $row
     * @param  EloquentCollection  $editors
     * @param  SupportCollection  $editorIterators
     *
     * @return array
     */
    private function getRowData(int $row, EloquentCollection $editors, SupportCollection $editorIterators): array
    {
        $rowData = [
            '#' => $row,
        ];

        // collect the current paraphrases
        $currentParaphrases = $this->getCurrentParaphrases($editors, $editorIterators);

        // get the minimum start position
        $positionStart = $currentParaphrases
            ->min(fn(?Paraphrase $paraphrase) => $paraphrase?->position_start ?? 999999);
        // get the maximum end position
        $positionEnd = $currentParaphrases
            ->where('position_start', '=', $positionStart)
            ->max(fn(?Paraphrase $paraphrase) => $paraphrase->position_end);

        // write the cells per editor
        $editors->each(function (Editor $editor, int $editor_index) use (
            &$editorIterators,
            $currentParaphrases,
            $positionStart,
            $positionEnd,
            &$rowData
        ) {
            $rowData = $this->getParaphraseCells($currentParaphrases, $editor_index, $positionStart, $positionEnd,
                $editor, $editorIterators, $rowData);
        });
        $rowData['pos.'] = ($positionStart === $positionEnd ? $positionStart : $positionStart.' - '.$positionEnd);
        $rowData['final paraphrase'] = '';
        $rowData['parent encoding'] = '';
        $rowData['category'] = '';

        return $rowData;
    }

    /**
     * @param  SupportCollection  $editorIterators
     *
     * @return bool
     */
    private function isLoopContinued(SupportCollection $editorIterators): bool
    {
        $continueLoop = false;
        $editorIterators->each(function (Iterator $editorIterator) use (&$continueLoop) {
            $continueLoop = $continueLoop || $editorIterator->valid();
        });

        return $continueLoop;
    }

    /**
     * @param  EloquentCollection  $editors
     * @param  SupportCollection  $editorIterators
     *
     * @return EloquentCollection
     */
    private function getCurrentParaphrases(
        EloquentCollection $editors,
        SupportCollection $editorIterators
    ): EloquentCollection {
        $currentParaphrases = new EloquentCollection();
        $editors->each(
            fn(
                Editor $editor,
                int $editor_index
            ) => $currentParaphrases->add($editorIterators->get($editor_index)->current())
        );

        return $currentParaphrases;
    }

    /**
     * @param  EloquentCollection  $currentParaphrases
     * @param  int  $editor_index
     * @param  mixed  $positionStart
     * @param  mixed  $positionEnd
     * @param  Editor  $editor
     * @param  SupportCollection  $editorIterators
     * @param  array  $rowData
     *
     * @return array
     */
    private function getParaphraseCells(
        EloquentCollection $currentParaphrases,
        int $editor_index,
        mixed $positionStart,
        mixed $positionEnd,
        Editor $editor,
        SupportCollection $editorIterators,
        array $rowData
    ): array {
        /** @var Paraphrase $paraphrase */
        $paraphrase = $currentParaphrases->get($editor_index);
        if ($paraphrase?->position_start === $positionStart && $paraphrase->position_end === $positionEnd) {
            // write the data
            $rowData[$editor->name] = $paraphrase->paraphrase;
            // and advance the iterator
            $editorIterators->get($editor_index)->next();
        } else {
            // write an empty cell
            $rowData[$editor->name] = '-';
        }

        return $rowData;
    }

    /**
     * @param  EloquentCollection  $interviews
     *
     * @return ProgressBar
     */
    private function initializeProgressBar(EloquentCollection $interviews): ProgressBar
    {
        $this->info('Exporting paraphrases to Excel...');
        $this->newLine();
        $paraphrasesCount = Paraphrase::whereIn('interview_id', $interviews->pluck('id'))->count();
        $bar              = $this->output->createProgressBar($paraphrasesCount);
        $bar->start();
        $bar->setFormat("<fg=green>%message%</>\n %current%/%max% [%bar%] %percent:3s%%");

        return $bar;
    }

    /**
     * @param  ProgressBar  $bar
     *
     * @return void
     */
    private function finishProgressBar(ProgressBar $bar): void
    {
        $bar->setMessage('Exporting finished.');
        $bar->finish();
        $this->newLine(2);
    }
}
