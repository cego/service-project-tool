<?php

namespace Cego;

class Yaml
{
    /**
     * Holds the indentation value
     *
     * @var string $indentation
     */
    protected $indentation = '  ';

    /**
     * Holds custom rules
     *
     * @var array $customRules
     */
    protected $customRules = [];

    /**
     * Holds the latest root key
     *
     * @var string
     */
    protected $latestRootKey = null;

    /**
     * Converts an array to Yaml
     *
     * @param array $data
     *
     * @return string
     */
    public static function fromArray(array $data)
    {
        return (new static)
            ->convertArrayToYaml($data);
    }

    /**
     * Yml constructor.
     *
     * Protected to force use of named constructs
     */
    protected function __construct()
    {
    }

    public function convertArrayToYaml(array $data)
    {
        $yaml = "---\n";

        $this->populateYamlRecursively($yaml, $data);

        return $yaml;
    }

    protected function populateYamlRecursively(&$yaml, $data, $recursionLevel = 0)
    {
        $indentation = $this->getIndentation($recursionLevel);

        $keysThatNeedQuotes = [
            'version'
        ];

        if (Is::array($data) && Is::sequentialArray($data)) {
            foreach ($data as $value) {
                if (preg_match('/[0-9]+\:[0-9]+/', $value)) {
                    $yaml .= sprintf("%s- \"%s\"\n", $indentation, $value);

                    continue;
                }

                $yaml .= sprintf("%s- %s\n", $indentation, $value);
            }

            return;
        }

        foreach ($data as $key => $value) {
            if ($recursionLevel == 0) {
                $yaml .= "\n";
                $this->latestRootKey = $key;
            }

            if (Is::array($value)) {
                if ($this->latestRootKey == 'services' && $recursionLevel == 1) {
                    $yaml .= "\n";
                }

                $yaml .= sprintf("%s%s:\n", $indentation, $key);

                $this->populateYamlRecursively($yaml, $value, $recursionLevel + 1);
            }

            if (Is::integer($key) && Is::notArray($value)) {
                $yaml .= sprintf("%s%s:\n", $indentation, $value);

                continue;
            }

            if (Is::integer($value)) {
                $yaml .= sprintf("%s%s: %d\n", $indentation, $key, (int) $value);
            }

            if (Is::boolean($value)) {
                $yaml .= sprintf("%s%s: %s\n", $indentation, $key, $value ? 'true': 'false');
            }

            if (Is::string($value)) {
                if (in_array($key, $keysThatNeedQuotes)) {
                    $yaml .= sprintf("%s%s: \"%s\"\n", $indentation, $key, $value);

                    continue;
                }

                $yaml .= sprintf("%s%s: %s\n", $indentation, $key, $value);
            }
        }
    }

    protected function getFormattedValue($key, $value)
    {
        $rules = array_merge([

        ], $this->customRules);

        if ( ! array_key_exists($key, $rules)) {
            return $value;
        }

        return $value;
    }

    /**
     * Gets the indentation as a string
     *
     * @param int $multiplier
     *
     * @return string
     */
    protected function getIndentation($multiplier)
    {
        return str_repeat($this->indentation, $multiplier);
    }
}
