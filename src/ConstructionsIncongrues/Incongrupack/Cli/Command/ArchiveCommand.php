<?php

namespace ConstructionsIncongrues\Incongrupack\Cli\Command;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Helper\ProgressHelper;

// Controller (will probably move when refactored)
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Finder\Finder;
use Alchemy\Zippy\Zippy;

// Logging
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class ArchiveCommand extends Command
{
    private $ffmpegFormatsClassmap = array(
        'flac'     => '\ConstructionsIncongrues\Incongrupack\FFMpeg\Format\Audio\Flac',
        'mp3'      => '\ConstructionsIncongrues\Incongrupack\FFMpeg\Format\Audio\Mp3',
        'ogg'      => '\ConstructionsIncongrues\Incongrupack\FFMpeg\Format\Audio\Vorbis',
    );

    private $metadataFields = array(
        'title',
        'artist',
        'album',
        'year',
        'genre',
        'comment',
        'tracknumber',
        'catalogid'
    );

    protected function configure()
    {
        $this
            ->setName('archive')
            ->setDescription('Generates archives ferom source files')
            ->addArgument('destination', InputArgument::REQUIRED, 'Path to directory that will hold the archives.')
            ->addArgument('source', InputArgument::OPTIONAL, 'Path to directory containing source files.', getcwd())
            ->addOption('archive-format', null, InputOption::VALUE_REQUIRED, 'Archive format', 'zip')
            ->addOption('archive-pattern', null, InputOption::VALUE_REQUIRED, 'Archive filename pattern', '%catalogid%_%outputformat%.%archiveformat%')
            ->addOption('output-format', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Output format. Different bitrates can be achieved using the "format_bitrate" format. eg. mp3_320, ogg_192, etc.', array_keys($this->ffmpegFormatsClassmap))
            ->addOption('source-format', null, InputOption::VALUE_REQUIRED, 'Source files audio format.', 'flac')
            ->addOption(
                'source-pattern',
                null,
                InputOption::VALUE_REQUIRED,
                sprintf('Source audio file pattern. Available fields : %s', implode(', ', $this->metadataFields)),
                '%tracknumber% - %tracktitle%.%sourceformat%'
            )
            ->addOption('workspace', null, InputOption::VALUE_REQUIRED, 'Directory to hold temporary files', sys_get_temp_dir().'/'.uniqid('incongrupack_'))
        ;
        foreach ($this->metadataFields as $field) {
            $this->addOption('metadata-'.$field, null, InputOption::VALUE_OPTIONAL, sprintf('Force value of the "%s" field', $field));
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Perform sanity checks
        $this->performSanityChecks($input, $output);

        // Get audio files
        $filesAudio = $this->getAudioFiles($input->getArgument('source'), $input->getOption('source-format'), $output);

        // Get other files
        $filesOther = $this->getOtherFiles($input->getArgument('source'), $input->getOption('source-format'), $output);

        foreach ($input->getOption('output-format') as $outputFormat) {
            // Transcode and tag audio files
            $filesTranscoded = $this->transcode(
                $filesAudio,
                $input->getOption('workspace'),
                $input->getOption('source-format'),
                $outputFormat,
                $input,
                $output
            );

            // Generate archive
            $this->generateArchive($filesTranscoded, $filesOther, $input->getArgument('destination'), $outputFormat, $input->getOption('archive-pattern'), $input, $output);
        }

        // Cleanup workspace
        $fs = new Filesystem();
        $fs->remove($input->getOption('workspace'));
        $output->writeln(sprintf('<info>Deleted workspace.</info> {workspace: "%s"}', $input->getOption('workspace')));
    }

    private function generateArchive(Finder $filesAudio, Finder $filesOther, $destinationDirectory, $outputFormat, $pattern, InputInterface $input, OutputInterface $output)
    {
        // Gather files
        $archiveFilename = str_replace(
            array('%catalogid%', '%outputformat%', '%archiveformat%'),
            array($input->getOption('metadata-catalogid'), $outputFormat, $input->getOption('archive-format')),
            sprintf('%s/%s', $destinationDirectory, $pattern)
        );
        foreach ($filesAudio as $file) {
            $files[] = $file->getRealPath();
        }
        foreach ($filesOther as $file) {
            $files[] = $file->getRealPath();
        }

        // Generate archive
        $zippy = Zippy::load();
        $archive = $zippy->create($archiveFilename, $files);

        // Log
        $output->writeln(
            sprintf(
                '<info>Generated archive.</info> {path: "%s", format: "%s"}',
                $archiveFilename,
                $input->getOption('archive-format')
            )
        );
    }

    private function extractMetadataFromFilename(\SplFileInfo $file, $pattern, InputInterface $input, OutputInterface $output)
    {
        // Build regex from pattern
        $regexParts = array();
        $patternParts = explode('%', $pattern);
        foreach ($patternParts as $part) {
            if (in_array($part, $this->metadataFields)) {
                $regexParts[] = sprintf('(?<%s>.+)', $part);
            } else {
                $regexParts[] = preg_quote($part);
            }
        }
        $regex = sprintf('/^%s$/', implode('', $regexParts));

        // Extract fields
        $matches = array();
        $basename = $file->getBasename('.'.pathinfo($file->getFilename(), PATHINFO_EXTENSION));
        $success = preg_match($regex, $basename, $matches);
        if (!$success) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Could not extract metadata from filename. {basename: %s, pattern: %s, regex: %s}',
                    $basename,
                    $pattern,
                    $regex
                )
            );
        }
        $namedMatches = array();
        $namedFields = array_filter(array_keys($matches), 'is_string');
        foreach ($namedFields as $field) {
            $namedMatches[$field] = $matches[$field];
        }

        // Enforce -metadata-* command line options
        foreach ($this->metadataFields as $field) {
            if ($input->getOption('metadata-'.$field)) {
                $namedMatches[$field] = $input->getOption('metadata-'.$field);
            }
        }

        // Fix for MP3
        if (isset($namedMatches['tracknumber'])) {
            $namedMatches['track'] = $namedMatches['tracknumber'];
        }

        return $namedMatches;
    }

    private function transcode(Finder $files, $workspaceRoot, $sourceFormat, $outputFormat, InputInterface $input, OutputInterface $output)
    {
        // Create dedicated workspace
        $workspace = $workspaceRoot.'/'.$outputFormat;
        $fs = new Filesystem();
        $fs->mkdir($workspace);

        // Log
        $output->writeln(
            sprintf(
                '<info>Transcoding files.</info> {count: %d, workspace: "%s", outputFormat: "%s"}',
                count($files),
                $workspace,
                $outputFormat
            )
        );

        // Display ffmpeg logs
        $log = new Logger('name');
        if ($output->getVerbosity() > 2) {
            $logLevel = Logger::DEBUG;
        } else {
            $logLevel = Logger::ERROR;
        }
        $log->pushHandler(new StreamHandler('php://stderr', $logLevel));

        // @see https://github.com/alchemy-fr/PHP-FFmpeg/blob/master/README.md
        $progressGlobal = $this->getHelperSet()->get('progress');
        $progressGlobal->start($output, count($files));
        $progressGlobal->setCurrent(0);
        $ffmpeg = \FFMpeg\FFMpeg::create(array(), $log);
        foreach ($files as $file) {
            $metadata = $this->extractMetadataFromFilename(
                $file,
                $input->getOption('source-pattern'),
                $input,
                $output
            );
            $progressGlobal->setFormat(ProgressHelper::FORMAT_NORMAL.sprintf(' %s', json_encode($metadata)));
            $formatSpec = explode('_', $outputFormat);
            $ffmpegFormatClass = $this->ffmpegFormatsClassmap[$formatSpec[0]];
            if (is_array($ffmpegFormatClass)) {
                $outputFormat = $ffmpegFormatClass['extension'];
                $ffmpegFormatClass = $ffmpegFormatClass['class'];
            }
            $ffmpegFormat = new $ffmpegFormatClass();
            $ffmpegMetadata = array();
            foreach ($metadata as $field => $value) {
                $ffmpegMetadata[] = '-metadata';
                $ffmpegMetadata[] = sprintf('%s=%s', $field, $value);
            }
            $ffmpegFormat->setExtraParams($ffmpegMetadata);

            if (isset($formatSpec[1])) {
                $ffmpegFormat->setAudioKiloBitrate($formatSpec[1]);
            }
            $audio = $ffmpeg->open($file->getRealPath());
            $audio->save(
                $ffmpegFormat,
                sprintf('%s/%s.%s', $workspace, $file->getBasename('.'.$sourceFormat), $formatSpec[0])
            );
            $progressGlobal->advance();
        }
        $progressGlobal->finish();

        // Return transcoded files
        $finder = new Finder();
        $filesTranscoded = $finder->name('*.'.$formatSpec[0])->in($workspace);
        return $filesTranscoded;
    }

    /**
     * Fail fast !
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     * @throws \Symfony\Component\Filesystem\Exception\IOException
     */
    private function performSanityChecks(InputInterface $input, OutputInterface $output)
    {
        // Make sure source directory exists
        if (!is_dir($dir = $input->getArgument('source'))) {
            throw new \InvalidArgumentException(sprintf('The directory "%s" does not exist.', $dir));
        }

        // Make sure destination directory exists or create it
        if (!is_dir($dir = $input->getArgument('destination'))) {
            $fs = new Filesystem();
            $fs->mkdir($dir);
            $output->writeln(sprintf('<info>Created directory</info>. {directory: "%s"}', $dir));
        }

        // Make sure php-ffmpeg can handle output format
        foreach ($input->getOption('output-format') as $outputFormat) {
            $formatSpec = explode('_', $outputFormat);
            $extension = $formatSpec[0];
            if (!isset($this->ffmpegFormatsClassmap[$extension])) {
                throw new \InvalidArgumentException(sprintf('Unknown output format "%s"', $extension));
            }
        }
    }

    private function getAudioFiles($sourceDirectory, $sourceFormat, OutputInterface $output)
    {
        $output->writeln(sprintf('<info>Looking for source audio files.</info> {directory: "%s", format: "%s"}', $sourceDirectory, $sourceFormat));
        $finder = new Finder();
        $files = $finder->name(sprintf('*.%s', $sourceFormat))->in($sourceDirectory);
        if (!count($files)) {
            throw new \InvalidArgumentException(
                sprintf('Could not find any source audio file {directory: "%s", format: "%s"}', $sourceFormat, $sourceDirectory)
            );
        }
        $output->writeln(
            sprintf(
                '<info>Found source audio files.</info> {directory: "%s", format: "%s", count: %d}',
                $sourceDirectory,
                $sourceFormat,
                count($files)
            )
        );

        return $files;
    }

    private function getOtherFiles($sourceDirectory, $sourceFormat, OutputInterface $output)
    {
        $output->writeln(sprintf('<info>Looking for non-audio source files.</info> {directory: "%s", format: "%s"}', $sourceDirectory, $sourceFormat));
        $finder = new Finder();
        $files = $finder->name('*.*')->in($sourceDirectory)->filter(function(\SplFileInfo $file) use ($sourceFormat) {
            return $file->getExtension() != $sourceFormat;
        });
        $output->writeln(
            sprintf(
                '<info>Found non-audio source files.</info> {directory: "%s", format: "%s", count: %d}',
                $sourceDirectory,
                $sourceFormat,
                count($files)
            )
        );

        return $files;
    }
}
