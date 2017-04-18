<?php

namespace Spatie\MediaLibrary\Commands;

use Exception;
use Spatie\MediaLibrary\Media;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Console\ConfirmableTrait;
use Spatie\MediaLibrary\FileManipulator;
use Spatie\MediaLibrary\MediaRepository;

class RegenerateCommand extends Command
{
    use ConfirmableTrait;

    protected $signature = 'medialibrary:regenerate {modelType?} {--ids=*}
    {-- force : Force the operation to run when in production}';

    protected $description = 'Regenerate the derived images of media';

    /** @var \Spatie\MediaLibrary\MediaRepository */
    protected $mediaRepository;

    /** @var \Spatie\MediaLibrary\FileManipulator */
    protected $fileManipulator;

    /** @var array */
    protected $erroredMediaIds = [];

    public function __construct(MediaRepository $mediaRepository, FileManipulator $fileManipulator)
    {
        parent::__construct();

        $this->mediaRepository = $mediaRepository;
        $this->fileManipulator = $fileManipulator;
    }

    public function handle()
    {
        if (! $this->confirmToProceed()) {
            return;
        }

        $mediaFiles = $this->getMediaToBeRegenerated();

        $progressBar = $this->output->createProgressBar($mediaFiles->count());

        $this->errorMessages = [];

        $mediaFiles->each(function (Media $media) use ($progressBar) {
            try {
                $this->fileManipulator->createDerivedFiles($media);
            } catch (Exception $exception) {
                $this->errorMessages[$media->id] = $exception->getMessage();
            }

            $progressBar->advance();
        });

        $progressBar->finish();

        if (count($this->errorMessages)) {
            $this->warn('All done, but with some error messages:');

            foreach ($this->errorMessages as $mediaId => $message) {
                $this->warn('Media id '.$mediaId.': "'.$message.'"');
            }
        } else {
            $this->info('All done!');
        }
    }

    public function getMediaToBeRegenerated(): Collection
    {
        $modelType = $this->argument('modelType') ?? '';
        $mediaIds = $this->option('ids');

        if ($modelType === '' && ! $mediaIds) {
            return $this->mediaRepository->all();
        }

        if ($mediaIds) {
            if (! is_array($mediaIds)) {
                $mediaIds = explode(',', $mediaIds);
            }

            return $this->mediaRepository->getByIds($mediaIds);
        }

        return $this->mediaRepository->getByModelType($modelType);
    }
}
