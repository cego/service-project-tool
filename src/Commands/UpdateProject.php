<?php

namespace Cego\Commands;

use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Exception\LogicException;

class UpdateProject extends Command
{
    /**
     * Holds the config for the project once loaded
     *
     * @var array $projectConfig
     */
    protected $projectConfig;

    /**
     * Holds a list of available updates once populated
     *
     * @var array $availableUpdates
     */
    protected $availableUpdates = [];

    /**
     * Configures the current command.
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('update')
            ->setDescription('Updates an existing services project');
    }

    /**
     * Executes the current command.
     *
     * This method is not abstract because you can use this class
     * as a concrete class. In this case, instead of defining the
     * execute() method, you set the code to execute by passing
     * a Closure to the setCode() method.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int 0 if everything went fine, or an exit code
     *
     * @throws LogicException When this abstract method is not implemented
     *
     * @see setCode()
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Bail out if no config file was found
        if ( ! file_exists('service-config.json')) {
            $output->writeln('<error>No "service-config.json" file was found for service project, aborting!</error>');

            return 1;
        }

        $output->writeln('<info>Configuration file found</info>');

        // Load config
        $this->projectConfig = $this->loadConfiguration();

        // Perform update if eligible
        $output->writeln('<info>Checking for updates</info>');

        if ($this->isEligibleForUpdate()) {
            $this->performUpdate($input, $output);
        }

        return 0;
    }

    /**
     * Loads the configuration file for current project
     *
     * @return array
     */
    protected function loadConfiguration()
    {
        return json_decode(file_get_contents('service-config.json'), true);
    }

    /**
     * Checks if the current project is eligible for updates
     *
     * @return bool
     */
    protected function isEligibleForUpdate()
    {
        $this->populateAvailableUpdates();

        $numberOfUpdatesNotApplied = array_diff($this->availableUpdates, $this->projectConfig['updates']);

        return count($numberOfUpdatesNotApplied) > 0;
    }

    /**
     * Populates the list of available updates
     *
     * @return void
     */
    protected function populateAvailableUpdates()
    {
        $files = Finder::create()
            ->in(UPDATES_PATH)
            ->sortByName(true);

        foreach ($files as $file) {
            if ( ! $file->isDir()) {
                continue;
            }

            if ($this->directoryNameDoesNotConformWithUpdateNamePattern($file->getFilename())) {
                continue;
            }

            array_push($this->availableUpdates, $file->getFilename());
        }
    }

    protected function performUpdate(InputInterface $input, OutputInterface $output)
    {
        $this->createBackup($output);

        $output->writeln('<comment>Proceeding will apply the following updates:</comment>');

        foreach ($this->availableUpdates as $update) {
            $output->writeln($update);
        }

        $question = new ConfirmationQuestion('<question>Proceed? (y/N)</question> ', false);
        $answer = $this->getHelper('question')->ask($input, $output, $question);

        if ($answer == false) {
            return;
        }
    }

    /**
     * Creates a backup point to facilitate rolling back to previous update
     *
     * @param OutputInterface $output
     *
     * @return void
     */
    protected function createBackup(OutputInterface $output)
    {
        // TODO: Will be implemented later
    }

    /**
     * Tells if a directory name conforms with the given update name pattern
     *
     * @param string $directoryName
     *
     * @return bool
     */
    protected function directoryNameConformWithUpdateNamePattern($directoryName)
    {
        // The naming of updates (Their root directory) must conform with a
        // specific pattern. This pattern is "number" "dot" "number" E.g.
        // "1.0" is a valid update name, "v10", as an example, is not.
        return (bool) preg_match('/[0-9]+\.[0-9]+/', $directoryName);
    }

    /**
     * Tells if a directory name does NOT conform with the given update name pattern
     * This is syntactic sugar for negating directoryNameConformWithUpdateNamePattern()
     *
     * @param string $directoryName
     *
     * @return bool
     */
    protected function directoryNameDoesNotConformWithUpdateNamePattern($directoryName)
    {
        return ! $this->directoryNameConformWithUpdateNamePattern($directoryName);
    }


}
