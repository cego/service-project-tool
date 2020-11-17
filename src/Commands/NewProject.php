<?php

namespace Cego\Commands;

use Cego\Yaml;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class NewProject extends Command
{
    /**
     * Holds the project name
     *
     * @var string $projectName
     */
    protected $projectName;

    /**
     * Holds the project namespace
     *
     * @var string $projectNamespace
     */
    protected $projectNamespace;

    /**
     * Holds the config as it is being built
     *
     * @var array $projectConfig
     */
    protected $projectConfig = [
        'project' => [
            'name'      => null,
            'namespace' => null,
        ],
        'service' => [
            'type'                 => null,
            'uses_cron'            => false,
            'uses_cloudflared'     => false,
            'needs_local_database' => false,
            'needs_local_redis'    => false,
        ],
        'updates' => [
        ],
        'packages' => [
        ],
        'deployment' => [
        ],
    ];

    /**
     * Holds the project creation path
     *
     * @var string $projectPath
     */
    protected $projectPath;

    /**
     * Holds the database password
     *
     * @var string $databasePassword
     */
    protected $databasePassword;

    /**
     * Configures the current command.
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('new')
            ->addArgument('project_namespace', InputArgument::REQUIRED, 'The namespace of the project/service')
            ->addArgument('project_name', InputArgument::REQUIRED, 'The name of the project/service to create (This will also be folder name)')
            ->setDescription('Creates a new services project');
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
        $output->writeln('Creating new services project...');
        $output->writeln('');

        // Get project name and namespace from arguments
        $this->projectName = $input->getArgument('project_name');
        $this->projectNamespace = $input->getArgument('project_namespace');

        $this->projectConfig['project']['name'] = $this->projectName;
        $this->projectConfig['project']['namespace'] = $this->projectNamespace;

        // Display chosen namespace and name
        $output->writeln(sprintf('<comment>project.namespace: </comment>%s', $this->projectNamespace));
        $output->writeln(sprintf('<comment>project.name: </comment>%s', $this->projectName));
        $output->writeln('');

        // Build up service components
        $this->useDatabaseForDevelopment($input, $output);
        $this->useRedisForDevelopment($input, $output);
        $this->useAsAnApi($input, $output);
        $this->useCloudflared($input, $output);
        $this->useCron($input, $output);
        $this->deployToSpilnu($input, $output);
        $this->deployToLyckost($input, $output);
        $this->selectPackages($input, $output);

        // Verify configuration
        $this->verifyConfiguration($input, $output);

        return 0;
    }

    /**
     * Asks if a database service should be present in local development environment
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function useDatabaseForDevelopment(InputInterface $input, OutputInterface $output)
    {
        $question = new ConfirmationQuestion('<question>Needs database service for local development? (Y/n)</question> ');
        $answer = $this->getHelper('question')->ask($input, $output, $question);

        if ($answer == false) {
            $output->writeln('<comment>service.needs_local_database: </comment>false');

            return;
        }

        $output->writeln('<comment>service.needs_local_database: </comment>true');

        $this->projectConfig['service']['needs_local_database'] = true;
    }

    /**
     * Asks if a Redis service should be present in local development environment
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function useRedisForDevelopment(InputInterface $input, OutputInterface $output)
    {
        $question = new ConfirmationQuestion('<question>Needs Redis service for local development? (Y/n)</question> ');
        $answer = $this->getHelper('question')->ask($input, $output, $question);

        if ($answer == false) {
            $output->writeln('<comment>service.needs_local_redis: </comment>false');

            return;
        }

        $output->writeln('<comment>service.needs_local_redis: </comment>true');

        $this->projectConfig['service']['needs_local_redis'] = true;
    }

    protected function useAsAnApi(InputInterface $input, OutputInterface $output)
    {
        $question = new ConfirmationQuestion('<question>Is service an api? (Y/n) (Determines if the docker service name is api or web)</question> ');
        $answer = $this->getHelper('question')->ask($input, $output, $question);

        if ($answer == false) {
            $output->writeln('service.type: <comment>web</comment>');

            $this->projectConfig['service']['type'] = 'web';

            return;
        }

        $output->writeln('service.type: <comment>api</comment>');

        $this->projectConfig['service']['type'] = 'api';
    }

    protected function useCloudflared(InputInterface $input, OutputInterface $output)
    {
        $question = new ConfirmationQuestion('<question>Use Cloudflared for service? (Y/n)</question> ');
        $answer = $this->getHelper('question')->ask($input, $output, $question);

        if ($answer == false) {
            $output->writeln('service.uses_cloudflared: <comment>false</comment>');

            return;
        }

        $output->writeln('service.uses_cloudflared: <comment>true</comment>');

        $this->projectConfig['service']['uses_cloudflared'] = true;
    }

    protected function useCron(InputInterface $input, OutputInterface $output)
    {
        $question = new ConfirmationQuestion('<question>Use Cron for service? (Y/n)</question> ');
        $answer = $this->getHelper('question')->ask($input, $output, $question);

        if ($answer == false) {
            $output->writeln('service.uses_cron: <comment>false</comment>');

            return;
        }

        $output->writeln('service.uses_cron: <comment>true</comment>');

        $this->projectConfig['service']['uses_cron'] = true;
    }

    protected function deployToSpilnu(InputInterface $input, OutputInterface $output)
    {
        $question = new ConfirmationQuestion('<question>Deploy to Spilnu? (Y/n)</question> ');
        $answer = $this->getHelper('question')->ask($input, $output, $question);

        if ($answer == false) {
            $output->writeln('deploy.spilnu: <comment>no</comment>');

            return;
        }

        $output->writeln('deploy.spilnu: <comment>yes</comment>');

        $this->projectConfig['deployment'][] = 'spilnu';
    }

    protected function deployToLyckost(InputInterface $input, OutputInterface $output)
    {
        $question = new ConfirmationQuestion('<question>Deploy to Lyckost? (y/N)</question> ', false);
        $answer = $this->getHelper('question')->ask($input, $output, $question);

        if ($answer == false) {
            $output->writeln('deploy.lyckost: <comment>no</comment>');

            return;
        }

        $output->writeln('deploy.lyckost: <comment>yes</comment>');

        $this->projectConfig['deployment'][] = 'lyckost';
    }

    protected function selectPackages(InputInterface $input, OutputInterface $output)
    {
        $options = [
            'No packages',
            'cego/request-log',
            'cego/request-insurance',
            'cego/endless-running-job',
            'cego/auth-middleware',
            'cego/filebeat-logger-laravel',
        ];

        $question = new ChoiceQuestion('<question>Select packages to add? (E.g. 0,1,2,3) default is no packages</question> ', $options, 0);
        $question->setMultiselect(true);

        $answer = $this->getHelper('question')->ask($input, $output, $question);

        if (array_search('No packages', $answer) !== false) {
            return;
        }

        $this->projectConfig['packages'] = $answer;
    }

    /**
     * Verifies the configuration before generating the project
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function verifyConfiguration(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<comment>Review configuration...</comment>');
        $output->writeln('');
        $output->writeln(json_encode($this->projectConfig, JSON_PRETTY_PRINT));
        $output->writeln('');

        $question = new ConfirmationQuestion('<question>Does this configuration look okay to you? (y/N)</question> ', false);
        $answer = $this->getHelper('question')->ask($input, $output, $question);

        if ($answer == false) {
            $output->writeln('Creation was cancelled!');
            $output->writeln('');

            return;
        }

        $this->createProject($output);
    }

    /**
     * Creates the service/project
     *
     * @param OutputInterface $output
     */
    protected function createProject(OutputInterface $output)
    {
        $output->writeln('<comment>Creating service now!</comment>');
        $output->writeln('');

        // Create project directory and enter it
        $this->projectPath = sprintf('%s/%s', getcwd(), $this->projectName);
        $output->writeln(sprintf('<info>Creating project directory: </info> %s', $this->projectPath));

        mkdir($this->projectPath);
        chdir($this->projectPath);

        // Write the configuration file
        file_put_contents('service-config.json', json_encode($this->projectConfig, JSON_PRETTY_PRINT), FILE_BINARY);

        // Create internal folder structure
        $output->writeln('<info>Creating docker folder.</info>');
        mkdir($this->projectPath . DIRECTORY_SEPARATOR . 'docker');

        $output->writeln('<info>Creating documentation folder.</info>');
        mkdir($this->projectPath . DIRECTORY_SEPARATOR . 'documentation');

        // Install Laravel
        $this->installLaravel($output);
        $this->cleanUpLaravelInstall($output);
        $this->setupLinting($output);
        $this->applyLinting($output);

        // Create database initialization script for stage and production
        $this->prepareDatabaseInitializationScript($output);

        // Setting up environments
        $this->generateEnvironmentFiles($output);

        // Copy stubbed files
        $this->copyStubbedFiles($output);

        // Install packages
        $this->installPackages($output);

        // Prepare the Dockerfile
        $this->prepareDockerfile($output);

        // Generate docker-compose files for all environments
        $this->generateDockerComposeFiles($output);

        // Generate CI
        $this->generateGitlabCIFile($output);

        // Generate App Script
        $this->generateAppScript($output);

        // Generate README.md
        $this->generateReadme($output);
    }

    /**
     * Installs a fresh Laravel
     *
     * @param OutputInterface $output
     */
    protected function installLaravel(OutputInterface $output)
    {
        $output->writeln('<info>Installing Laravel.</info>');
        `composer create-project laravel/laravel project`;

        $laravelPath = $this->projectPath . DIRECTORY_SEPARATOR . 'project';
        //mkdir($laravelPath);

        $output->writeln('<comment>Change directory to: </comment>' . $laravelPath);
        chdir($laravelPath);
    }

    /**
     * Removes files that are not needed from the fresh laravel installation
     *
     * @param OutputInterface $output
     */
    protected function cleanUpLaravelInstall(OutputInterface $output)
    {
        $output->writeln('<info>Cleaning up Laravel installation.</info>');

        $filesToDelete = [
            '.env',
            '.env.example',
            'README.md',
            '.styleci.yml',
        ];

        foreach ($filesToDelete as $file) {
            $output->writeln(sprintf('<comment>Deleting: </comment> %s', $file));
            unlink(sprintf('./%s', $file));
        }
    }

    /**
     * Sets up linting og the project
     *
     * @param OutputInterface $output
     */
    protected function setupLinting(OutputInterface $output)
    {
        $output->writeln('<info>Setting up project linting.</info>');

        $files = [
            'phpstan.neon',
            '.php_cs'
        ];

        $packages = [
            'friendsofphp/php-cs-fixer',
            'phpstan/phpstan',
        ];

        foreach ($files as $file) {
            $output->writeln(sprintf('<comment>Copying: </comment> %s', $file));

            $source = sprintf('%s%s%s', STUBS_PATH, DIRECTORY_SEPARATOR, $file);
            $destination = sprintf('./%s', $file);

            copy($source, $destination);
        }

        foreach ($packages as $package) {
            $output->writeln(sprintf('<comment>Installing package: </comment> %s', $package));

            `composer require $package --dev`;
        }
    }

    /**
     * Sets up linting og the project
     *
     * @param OutputInterface $output
     */
    protected function applyLinting(OutputInterface $output)
    {
        $output->writeln('<info>Applying linting on project.</info>');

        `./vendor/bin/php-cs-fixer fix --verbose`;
    }

    /**
     * Prepares the database initialization script
     *
     * @param OutputInterface $output
     */
    protected function prepareDatabaseInitializationScript(OutputInterface $output)
    {
        $output->writeln('<info>Creating setup script for a database</info>');

        // Create new password for the database user
        $output->writeln('<comment>Generating database password</comment>');
        $this->databasePassword = base64_encode(md5(microtime() . rand()));

        $fileContent = file_get_contents(STUBS_PATH . DIRECTORY_SEPARATOR . 'create-database.sql');
        $fileContent = str_replace('${PROJECT_NAME}', $this->projectName, $fileContent);
        $fileContent = str_replace('${DATABASE_PASSWORD}', $this->databasePassword, $fileContent);

        $output->writeln('<comment>Writing database sql script</comment>');
        file_put_contents('../docker/create-database.sql', $fileContent, FILE_BINARY);
    }

    /**
     * Generate environment files as needed
     *
     * @param OutputInterface $output
     */
    protected function generateEnvironmentFiles(OutputInterface $output)
    {
        $output->writeln('<info>Creating environments</info>');

        // Local environment .env.local
        $output->writeln('<comment>Creating environment: </comment> .env.local');

        $fileContent = file_get_contents(STUBS_PATH . DIRECTORY_SEPARATOR . '.env.local');
        $fileContent = str_replace('${PROJECT_NAME}', $this->projectName, $fileContent);

        $usesRedis = $this->projectConfig['service']['needs_local_redis'] === true;

        $fileContent = str_replace('${REDIS_OR_FILE}', $usesRedis ? 'redis' : 'file', $fileContent);

        file_put_contents('./.env.local', $fileContent, FILE_BINARY);

        // Other environments
        $environments = [
            'stage',
            'production'
        ];

        $domainEndings = [
            'spilnu'  => 'dk',
            'lyckost' => 'se',
        ];

        // Staging environment .env.stage
        foreach ($environments as $environment) {
            foreach ($this->projectConfig['deployment'] as $site) {
                $output->writeln(sprintf('<comment>Creating environment: </comment> .env.%s-%s', $site, $environment));

                $stubPath = sprintf('%s%s.env.%s', STUBS_PATH, DIRECTORY_SEPARATOR, $environment);

                $fileContent = file_get_contents($stubPath);
                $fileContent = str_replace('${PROJECT_NAME}', $this->projectName, $fileContent);
                $fileContent = str_replace('${PROJECT_SITE}', sprintf('%s.%s', $site, $domainEndings[$site]), $fileContent);
                $fileContent = str_replace('${DATABASE_PASSWORD}', $this->databasePassword, $fileContent);

                $usesRedis = $this->projectConfig['service']['needs_local_redis'] === true;

                $fileContent = str_replace('${REDIS_OR_FILE}', $usesRedis ? 'redis' : 'file', $fileContent);

                file_put_contents(sprintf('./.env.%s-%s', $site, $environment), $fileContent, FILE_BINARY);
            }
        }
    }

    /**
     * Copies all stubbed files that are needed
     *
     * @param OutputInterface $output
     */
    protected function copyStubbedFiles(OutputInterface $output)
    {
        $output->writeln('<info>Copying stubbed files.</info>');

        // Set mandatory files
        $files = [
            'nginx.conf',
            'php-fpm.conf',
            'php.ini',
            'entrypoint.sh',
        ];

        // Add cron files if applicable
        if ($this->projectConfig['service']['uses_cron']) {
            $files = array_merge([
                'crontab',
                'entrypoint-cron.sh'
            ], $files);
        }

        foreach ($files as $file) {
            $output->writeln(sprintf('<comment>Copying file: </comment> %s', $file));

            $source = sprintf('%s%s%s', STUBS_PATH, DIRECTORY_SEPARATOR, $file);
            $destination = sprintf('../docker/%s', $file);

            copy($source, $destination);

            // Make sure shell scripts are executable
            if (strpos($file, '.sh')) {
                chmod($destination, 0755);
            }
        }
    }

    /**
     * Installs all select packages
     *
     * @param OutputInterface $output
     */
    protected function installPackages(OutputInterface $output)
    {
        $output->writeln('<info>Installing selected packages.</info>');

        if (count($this->projectConfig['packages']) <= 0) {
            $output->writeln('<comment>No packages was been selected</comment>');

            return;
        }

        $output->writeln(sprintf('<comment>Packages being installed are: </comment>%s', implode(', ', $this->projectConfig['packages'])));

        foreach ($this->projectConfig['packages'] as $package) {
            `composer require $package`;
        }
    }

    /**
     * Prepares the Dockerfile from which every image is created
     *
     * @param OutputInterface $output
     */
    protected function prepareDockerfile(OutputInterface $output)
    {
        $output->writeln('<info>Preparing the Dockerfile.</info>');

        $output->writeln('<comment>Copying file: </comment> Dockerfile');

        $source = sprintf('%s%s%s', STUBS_PATH, DIRECTORY_SEPARATOR, 'Dockerfile');
        $destination = '../docker/Dockerfile';

        copy($source, $destination);
    }

    protected function generateDockerComposeFiles(OutputInterface $output)
    {
        $output->writeln('<info>Generating docker-compose files.</info>');

        $this->generateDockerComposeFilesForLocalDevelopment($output);
        $this->generateBaseDeploymentDockerComposeFiles($output);
        $this->generateDeploymentDockerComposeFiles($output);
    }

    /**
     * Generates docker-compose files for local development
     *
     * @param OutputInterface $output
     */
    protected function generateDockerComposeFilesForLocalDevelopment(OutputInterface $output)
    {
        // Support docker-compose file for local development
        if ($this->projectConfig['service']['needs_local_database'] || $this->projectConfig['service']['needs_local_redis']) {
            $output->writeln('<comment>Creating: </comment>docker-composer.support.yml');

            $content = file_get_contents(STUBS_PATH . DIRECTORY_SEPARATOR . 'docker-compose' . DIRECTORY_SEPARATOR . 'local.base.yml');
            $content = str_replace('${PROJECT_NAME}', $this->projectName, $content);

            if ($this->projectConfig['service']['needs_local_database']) {
                $databaseContent = file_get_contents(STUBS_PATH . DIRECTORY_SEPARATOR . 'docker-compose' . DIRECTORY_SEPARATOR . 'support.database.yml');
                $databaseContent = str_replace('${PROJECT_NAME}', $this->projectName, $databaseContent);

                $content .= sprintf("\n%s", $databaseContent);
            }

            if ($this->projectConfig['service']['needs_local_redis']) {
                $redisContent = file_get_contents(STUBS_PATH . DIRECTORY_SEPARATOR . 'docker-compose' . DIRECTORY_SEPARATOR . 'support.redis.yml');
                $redisContent = str_replace('${PROJECT_NAME}', $this->projectName, $redisContent);

                $content .= sprintf("\n%s", $redisContent);
            }

            file_put_contents('../docker-compose.support.yml', $content, FILE_BINARY);
        }

        // docker-compose file for local development
        $output->writeln('<comment>Creating: </comment>docker-composer.yml');

        $content = file_get_contents(STUBS_PATH . DIRECTORY_SEPARATOR . 'docker-compose' . DIRECTORY_SEPARATOR . 'local.base.yml');
        $content = str_replace('${PROJECT_NAME}', $this->projectName, $content);

        $serviceName = $this->projectConfig['service']['type'];

        $localContent = file_get_contents(STUBS_PATH . DIRECTORY_SEPARATOR . 'docker-compose' . DIRECTORY_SEPARATOR . 'local.service.yml');
        $localContent = str_replace('${PROJECT_NAME}', $this->projectName, $localContent);
        $localContent = str_replace('${PROJECT_NAMESPACE}', $this->projectNamespace, $localContent);
        $localContent = str_replace('${SERVICE_NAME}', $serviceName, $localContent);

        $content .= sprintf("\n%s", $localContent);

        if ($this->projectConfig['service']['uses_cron']) {
            $cronContent = file_get_contents(STUBS_PATH . DIRECTORY_SEPARATOR . 'docker-compose' . DIRECTORY_SEPARATOR . 'local.cron.yml');
            $cronContent = str_replace('${PROJECT_NAME}', $this->projectName, $cronContent);
            $cronContent = str_replace('${PROJECT_NAMESPACE}', $this->projectNamespace, $cronContent);

            $content .= sprintf("\n%s", $cronContent);
        }

        file_put_contents('../docker-compose.yml', $content, FILE_BINARY);
    }

    protected function generateBaseDeploymentDockerComposeFiles(OutputInterface $output)
    {
        $environments = [
            'stage',
            'production',
        ];

        $sites = $this->projectConfig['deployment'];

        foreach ($environments as $environment) {
            // Generate the base of the docker-compose file
            $composeStructure = [
                'version'  => '3.2',
                'networks' => [],
                'services' => []
            ];

            foreach ($sites as $site) {
                $composeStructure['networks'][] = sprintf('%s-%s', $this->projectName, $site);
            }

            foreach ($sites as $site) {
                $composeStructure['networks'][$site] = [
                    'external' => [
                        'name' => $this->getDockerNetwork($site)
                    ]
                ];
            }

            $deploymentBase = (Yaml::fromArray($composeStructure));

            // Add all the services needed for the project

            // Cloudflared
            if ($this->projectConfig['service']['uses_cloudflared']) {
                $file = sprintf('%s%sdocker-compose%s%s.base.cloudflared.yml', STUBS_PATH, DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, $environment);
                $content = file_get_contents($file);
                $content = str_replace('${SERVICE_NAME}', $this->projectConfig['service']['type'], $content);

                $deploymentBase .= sprintf("\n%s", $content);
            }

            // Redis
            if ($this->projectConfig['service']['needs_local_redis']) {
                $file = sprintf('%s%sdocker-compose%s%s.base.redis.yml', STUBS_PATH, DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, $environment);
                $content = file_get_contents($file);

                $deploymentBase .= sprintf("\n%s", $content);
            }

            // Main service
            $file = sprintf('%s%sdocker-compose%s%s.base.service.yml', STUBS_PATH, DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, $environment);
            $content = file_get_contents($file);
            $content = str_replace('${SERVICE_NAME}', $this->projectConfig['service']['type'], $content);
            $content = str_replace('${PROJECT_NAME}', $this->projectName, $content);
            $content = str_replace('${PROJECT_NAMESPACE}', $this->projectNamespace, $content);
            $content = str_replace('${ENVIRONMENT}', $environment, $content);

            $deploymentBase .= sprintf("\n%s", $content);

            // Cron
            if ($this->projectConfig['service']['uses_cron']) {
                $file = sprintf('%s%sdocker-compose%s%s.base.cron.yml', STUBS_PATH, DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, $environment);
                $content = file_get_contents($file);
                $content = str_replace('${PROJECT_NAME}', $this->projectName, $content);
                $content = str_replace('${PROJECT_NAMESPACE}', $this->projectNamespace, $content);
                $content = str_replace('${ENVIRONMENT}', $environment, $content);

                $deploymentBase .= sprintf("\n%s", $content);
            }

            $output->writeln(sprintf('<comment>Creating: </comment>docker-composer.base.%s.yml', $environment));
            $file = sprintf('../docker-compose.base.%s.yml', $environment);
            file_put_contents($file, $deploymentBase, FILE_BINARY);
        }
    }

    protected function generateDeploymentDockerComposeFiles(OutputInterface $output)
    {
        $environments = [
            'stage',
            'production',
        ];

        $sites = $this->projectConfig['deployment'];

        $sitePrefixes = [
            'spilnu'  => 'sn',
            'lyckost' => 'lo',
        ];

        foreach ($environments as $environment) {
            foreach ($sites as $site) {
                // Main service
                $structure = [
                    'version'  => '3.2',
                    'services' => []
                ];

                $networks = [];
                $networks[] = sprintf('%s-%s', $this->projectName, $site);
                $networks[] = $site;

                if ($site == 'spilnu') {
                    $networks[] = 'deprecated';
                }

                $structure['services'][$this->projectConfig['service']['type']] = [
                    'environment' => [
                        'LOGSPOUT' => 'ignore',
                        'APP_ENV'  => sprintf('%s-%s', $site, $environment),
                        'DB_HOST'  => sprintf('%s_mysql_mysql01-primary', $sitePrefixes[$site]),
                    ],
                    'networks' => $networks
                ];

                // Cron
                if ($this->projectConfig['service']['uses_cron']) {
                    $structure['services']['cron'] = $structure['services'][$this->projectConfig['service']['type']];
                }

                $deployment = Yaml::fromArray($structure);

                // Cloudflared
                if ($this->projectConfig['service']['uses_cloudflared']) {
                    $file = sprintf('%s%sdocker-compose%sdeployment.cloudflared.yml', STUBS_PATH, DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR);
                    $content = file_get_contents($file);
                    $content = str_replace('${SERVICE_NETWORK}', sprintf('%s-%s', $this->projectName, $site), $content);
                    $content = str_replace('${SERVICE_HOST}', $this->getHostname($environment, $site), $content);
                    $content = str_replace('${SERVICE_SITE}', mb_strtoupper($site), $content);

                    $deployment .= sprintf("\n%s", $content);
                }

                // Redis
                if ($this->projectConfig['service']['needs_local_redis']) {
                    $file = sprintf('%s%sdocker-compose%sdeployment.redis.yml', STUBS_PATH, DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR);
                    $content = file_get_contents($file);
                    $content = str_replace('${SERVICE_NETWORK}', sprintf('%s-%s', $this->projectName, $site), $content);

                    $deployment .= sprintf("\n%s", $content);
                }

                $output->writeln(sprintf('<comment>Creating: </comment>docker-composer.%s.%s.yml', $site, $environment));
                $file = sprintf('../docker-compose.%s.%s.yml', $site, $environment);
                file_put_contents($file, $deployment, FILE_BINARY);
            }
        }
    }

    protected function generateGitlabCIFile(OutputInterface $output)
    {
        $output->writeln('<info>Generating .gitlab-ci.yml</info>');

        $environments = [
            'stage',
            'production',
        ];

        $sites = $this->projectConfig['deployment'];

        $structure = [
            'stages' => [
                'setup',
                'qa',
                'build',
                'tag',
                'databases',
                'migrations',
                'seeders',
                'deployment'
            ],
            'default' => [
                'tags' => [
                    'shared-docker'
                ]
            ],
            'variables' => [
                'DOCKER_TAG'        => 'ci-pipeline-${CI_PIPELINE_ID}',
                'DOCKER_BUILDKIT'   => 1,
                'PROJECT_NAME'      => $this->projectName,
                'PROJECT_NAMESPACE' => $this->projectNamespace,
            ],
            '.qa' => [
                'stage'  => 'qa',
                'except' => [
                    'tags'
                ]
            ],
            '.build' => [
                'stage'  => 'build',
                'except' => [
                    'tags'
                ]
            ],
            '.stage' => [
                'tags' => [
                    'stack-deployer',
                    'spilnu',
                    'stage',
                ],
                'variables' => [
                    'APP_ENV' => 'stage'
                ]
            ],
            '.production' => [
                'tags' => [
                    'stack-deployer',
                    'spilnu',
                    'prod',
                ],
                'variables' => [
                    'APP_ENV' => 'production'
                ]
            ],
            '.spilnu' => [
                'variables' => [
                    'APP_SITE' => 'spilnu',
                    'DB_HOST'  => 'sn_mysql_mysql01-primary',
                ]
            ],
            '.lyckost' => [
                'variables' => [
                    'APP_SITE' => 'lyckost',
                    'DB_HOST'  => 'lo_mysql_mysql01-primary',
                ]
            ],
            '.database' => [
                'stage' => 'databases',
                'only'  => [
                    'tags'
                ],
                'when' => 'manual'
            ],
            '.migrator' => [
                'stage' => 'migrations',
                'only'  => [
                    'tags'
                ],
                'when' => 'manual'
            ],
            '.seeder' => [
                'stage' => 'seeders',
                'only'  => [
                    'tags'
                ],
                'when' => 'manual'
            ],
            '.deployment' => [
                'stage' => 'deployment',
                'only'  => [
                    'tags'
                ],
                'when' => 'manual'
            ],
            'dependencies' => [
                'stage'  => 'setup',
                'script' => [
                    'docker build --target dependencies -f docker/Dockerfile .'
                ]
            ],
            'phpunit' => [
                'extends' => '.qa',
                'script'  => [
                    'docker build --target phpunit -f docker/Dockerfile .'
                ]
            ],
            'php-cs-fixer' => [
                'extends' => '.qa',
                'script'  => [
                    'docker build --target phpcsfixer -f docker/Dockerfile .'
                ]
            ],
            'phpstan' => [
                'extends' => '.qa',
                'script'  => [
                    'docker build --target phpstan -f docker/Dockerfile .'
                ]
            ],
            'migrator' => $this->getBuildStructure('migrator'),
            'seeder'   => $this->getBuildStructure('seeder'),
            'shell'    => $this->getBuildStructure('shell'),
        ];

        // Add building of cron
        if ($this->projectConfig['service']['uses_cron']) {
            $structure['cron'] = $this->getBuildStructure('cron');
        }

        // Add building of main service
        $structure[$this->projectConfig['service']['type']] = $this->getBuildStructure($this->projectConfig['service']['type']);

        // Tag latest images
        $structure['latest'] = $this->addTaggingStructure('latest');

        // Tag release images
        $structure['release'] = $this->addTaggingStructure('${CI_COMMIT_TAG}');

        // Create databases section
        foreach ($sites as $site) {
            foreach ($environments as $environment) {
                $key = sprintf('%s-create-database-%s', $site, $environment);

                $structure[$key] = [
                    'extends' => [
                        '.database',
                        sprintf('.%s', $environment),
                        sprintf('.%s', $site),
                    ],
                    'script' => [
                        sprintf('docker run -i --rm --network %s mariadb:latest mysql -h mysql01-primary -uroot -p${%s_%s_MYSQL_ROOT_PASSWORD} < docker/setup-database.sql', $this->getDockerNetwork($site), mb_strtoupper($site), mb_strtoupper($environment))
                    ]
                ];
            }
        }

        // Create migrations section
        foreach ($sites as $site) {
            foreach ($environments as $environment) {
                $key = sprintf('%s-migrator-%s', $site, $environment);

                $structure[$key] = [
                    'extends' => [
                        '.migrator',
                        sprintf('.%s', $environment),
                        sprintf('.%s', $site),
                    ],
                    'variables' => [
                        'APP_ENV' => sprintf('%s-%s', $site, $environment),
                    ],
                    'script' => [
                        sprintf('docker run -e APP_ENV -e DB_HOST --network %s --rm registry.cego.dk/${PROJECT_NAMESPACE}/${PROJECT_NAME}/migrator:${CI_COMMIT_TAG}', $this->getDockerNetwork($site)),
                    ]
                ];
            }
        }

        // Create seeders section
        foreach ($sites as $site) {
            foreach ($environments as $environment) {
                $key = sprintf('%s-seeder-%s', $site, $environment);

                $structure[$key] = [
                    'extends' => [
                        '.seeder',
                        sprintf('.%s', $environment),
                        sprintf('.%s', $site),
                    ],
                    'variables' => [
                        'APP_ENV' => sprintf('%s-%s', $site, $environment),
                    ],
                    'script' => [
                        sprintf('docker run -e APP_ENV -e APP_SITE -e DB_HOST --network %s --rm registry.cego.dk/${PROJECT_NAMESPACE}/${PROJECT_NAME}/seeder:${CI_COMMIT_TAG}', $this->getDockerNetwork($site)),
                    ]
                ];
            }
        }

        // Create deployment section
        foreach ($sites as $site) {
            foreach ($environments as $environment) {
                $key = sprintf('%s-%s', $site, $environment);

                $structure[$key] = [
                    'extends' => [
                        '.deployment',
                        sprintf('.%s', $environment),
                        sprintf('.%s', $site),
                    ],
                    'script' => [
                        sprintf('docker stack deploy --with-registry-auth --compose-file docker-compose.base.%s.yml -c docker-compose.%s.%s.yml %s-%s', $environment, $site, $environment, $site, $this->projectName),
                    ]
                ];
            }
        }

        $ci = Yaml::fromArray($structure);
        $output->writeln('<comment>Writing: </comment>.gitlab-ci.yml');
        file_put_contents('../.gitlab-ci.yml', $ci, FILE_BINARY);
    }

    /**
     * Gets the docker network based on the site
     *
     * @param string $site
     *
     * @return string
     */
    protected function getDockerNetwork($site = 'spilnu')
    {
        $networkNameMap = [
            'spilnu'  => 'sn_default',
            'lyckost' => 'lo_default',
        ];

        if ( ! array_key_exists($site, $networkNameMap)) {
            return 'default';
        }

        return $networkNameMap[$site];
    }

    /**
     * Gets the structure for building an image
     *
     * @param $name
     *
     * @return array
     */
    protected function getBuildStructure($name)
    {
        return [
            'extends' => '.build',
            'script'  => [
                'docker build --target ' . $name . ' -t registry.cego.dk/${PROJECT_NAMESPACE}/${PROJECT_NAME}/' . $name . ':${DOCKER_TAG} -f docker/Dockerfile .',
                'docker push registry.cego.dk/${PROJECT_NAMESPACE}/${PROJECT_NAME}/' . $name . ':${DOCKER_TAG}'
            ]
        ];
    }

    /**
     * @param string $tag
     *
     * @return array
     */
    protected function addTaggingStructure($tag = 'latest')
    {
        $structure = [
            'stage'  => 'tag',
            'only'   => [
                ($tag == 'latest' ? 'master' : 'tags')
            ],
            'script' => [
                'docker build --target migrator -t registry.cego.dk/${PROJECT_NAMESPACE}/${PROJECT_NAME}/migrator:' . $tag . ' -f docker/Dockerfile .',
                'docker push registry.cego.dk/${PROJECT_NAMESPACE}/${PROJECT_NAME}/migrator:' . $tag,
                'docker build --target seeder -t registry.cego.dk/${PROJECT_NAMESPACE}/${PROJECT_NAME}/seeder:' . $tag . ' -f docker/Dockerfile .',
                'docker push registry.cego.dk/${PROJECT_NAMESPACE}/${PROJECT_NAME}/seeder:' . $tag,
                'docker build --target shell -t registry.cego.dk/${PROJECT_NAMESPACE}/${PROJECT_NAME}/shell:' . $tag . ' -f docker/Dockerfile .',
                'docker push registry.cego.dk/${PROJECT_NAMESPACE}/${PROJECT_NAME}/shell:' . $tag,
            ]
        ];

        // Add tagging of latest cron
        if ($this->projectConfig['service']['uses_cron']) {
            $structure['script'][] = 'docker build --target cron -t registry.cego.dk/${PROJECT_NAMESPACE}/${PROJECT_NAME}/cron:' . $tag . ' -f docker/Dockerfile .';
            $structure['script'][] = 'docker push registry.cego.dk/${PROJECT_NAMESPACE}/${PROJECT_NAME}/cron:' . $tag;
        }

        // Add tagging of latest main service
        $structure['script'][] = 'docker build --target ' . $this->projectConfig['service']['type'] . ' -t registry.cego.dk/${PROJECT_NAMESPACE}/${PROJECT_NAME}/' . $this->projectConfig['service']['type'] . ':' . $tag . ' -f docker/Dockerfile .';
        $structure['script'][] = 'docker push registry.cego.dk/${PROJECT_NAMESPACE}/${PROJECT_NAME}/' . $this->projectConfig['service']['type'] . ':' . $tag;

        return $structure;
    }

    /**
     * Gets the host name for the service based on environment and deployment site
     *
     * This does NOT include http:// or https://
     *
     * @param $environment
     * @param $site
     *
     * @return string
     */
    protected function getHostname($environment, $site)
    {
        $domainEndings = [
            'spilnu'  => 'dk',
            'lyckost' => 'se',
        ];

        $shortEnvironments = [
            'production' => 'prod',
            'stage'      => 'stage',
        ];

        return sprintf('%s-%s.%s.%s', $this->projectName, $shortEnvironments[$environment], $site, $domainEndings[$site]);
    }

    /**
     * Generates the App script for easy handling of project
     *
     * @param OutputInterface $output
     */
    protected function generateAppScript(OutputInterface $output)
    {
        $output->writeln('<info>Generating App script.</info>');

        $file = sprintf('%s%sapp.sh', STUBS_PATH, DIRECTORY_SEPARATOR);
        $content = file_get_contents($file);
        $content = str_replace('${--PROJECT_NAME--}', $this->projectName, $content);
        $content = str_replace('${--PROJECT_NAMESPACE--}', $this->projectNamespace, $content);
        $content = str_replace('${--SERVICE_NAME--}', $this->projectConfig['service']['type'], $content);

        $output->writeln('<comment>Writing: </comment>app');
        file_put_contents('../app', $content, FILE_BINARY);
        chmod('../app', 0755);
    }

    /**
     * Generates the Readme file for the project
     *
     * @param OutputInterface $output
     */
    protected function generateReadme(OutputInterface $output)
    {
        $output->writeln('<info>Generating the readme file.</info>');

        $file = sprintf('%s%sREADME.md', STUBS_PATH, DIRECTORY_SEPARATOR);
        $content = file_get_contents($file);
        $content = str_replace('${PROJECT_NAME}', $this->projectName, $content);

        $output->writeln('<comment>Writing: </comment>README.md');
        file_put_contents('../README.md', $content, FILE_BINARY);
    }
}
