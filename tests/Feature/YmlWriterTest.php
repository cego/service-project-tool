<?php

namespace Tests\Feature;

use Nbj\Yaml;
use PHPUnit\Framework\TestCase;

class YmlWriterTest extends TestCase
{
    /** @test */
    public function it_can_write_an_array_to_yml()
    {
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
                'PROJECT_NAME'      => 'placeholder',
                'PROJECT_NAMESPACE' => 'placeholder',
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
            ]
        ];

        $yml = Yaml::fromArray($structure);

        //file_put_contents('/Users/nbj/Development/Github/yml-writer/test.yml', $yml, FILE_APPEND);
        var_export($yml);
        //$this->assertEquals("---\n\nversion: \"3.4\"\n\nnetworks:\n  marketing-automation:\n    external: true\n\nservices:\n  api:\n    image: \"registry.cego.dk/cego/marketing-automation/api\"\n", $yml);
    }
}
