<?php

namespace Symfony\Upgrade\Fixer;

use Symfony\Component\Yaml;

class YamlFixer extends AbstractFixer
{
    public function fix(\SplFileInfo $file, $content)
    {
        if ('yml' !== strtolower($file->getExtension())) {
            return $content;
        }

        $wrapper = new ParserWrapper();

        return (new DumperWrapper())->dumpWithComments($wrapper->parse($content), $wrapper->getMetadata());
    }

    public function getDescription()
    {
        return 'YAML parser becomes less permissive, you should now escape mapping values containing a colon (:) or beginning by @, `, | and > signs.';
    }
}

class ParserWrapper extends Yaml\Parser
{
    private $metadata = [];

    public function parse($value, $exceptionOnInvalidType = false, $objectSupport = false, $objectForMap = false)
    {
        $this->setParentProp('lines', explode("\n", $value));
        while ($this->moveToNextLine()) {
            if ($this->isCurrentLineBlank()) {
                continue;
            }
            if ($this->isCurrentLineComment()) {
                array_push($this->metadata, [
                    'value' => $this->getParentProp('currentLine'),
                    'nextLine' => $this->getNextLine(),
                    'previousLine' => $this->getPreviousLine(),
                    'lineNb' => $this->getParentProp('currentLineNb'),
                    'realLineNb' => $this->getRealCurrentLineNb(),
                    'indentation' => $this->getCurrentLineIndentation(),
                ]);
            }
        }

        return parent::parse($value, $exceptionOnInvalidType, $objectSupport, $objectForMap);
    }

    private function getNextLine()
    {
        $this->moveToNextLine();
        $value = $this->getParentProp('currentLine');
        $this->moveToPreviousLine();

        return $value;
    }

    private function getPreviousLine()
    {
        $currentLine = $this->getParentProp('currentLineNb');
        if ($currentLine > 0) {
            $this->moveToPreviousLine();
        }
        $value = $this->getParentProp('currentLine');
        if ($currentLine > 0) {
            $this->moveToNextLine();
        }

        return $value;
    }

    private function getParentProp($name)
    {
        $prop = new \ReflectionProperty(get_parent_class($this), $name);
        $prop->setAccessible(true);

        return $prop->getValue($this);
    }

    private function setParentProp($name, $value)
    {
        $prop = new \ReflectionProperty(get_parent_class($this), $name);
        $prop->setAccessible(true);
        $prop->setValue($this, $value);
    }

    public function __call($name, $arguments)
    {
        $parent = (new \ReflectionClass($this))->getParentClass();
        if ($parent->hasMethod($name)) {
            $method = $parent->getMethod($name);
            $method->setAccessible(true);
            try {
                return $method->invoke($this, $arguments);
            } catch (\Exception $e) {
                throw new \RuntimeException(sprintf('Method %s with args "%s" ended with exception', $name, print_r($arguments)), 0, $e);
            }
        }

        throw new \RuntimeException(sprintf('Method %s does not exist', $name));
    }

    public function getMetadata()
    {
        return $this->metadata;
    }
}

class DumperWrapper extends Yaml\Dumper
{
    public function dumpWithComments($input, $metadata)
    {
        $content = explode("\n", parent::dump($input, 4));
        $toReturn = $content;
        foreach ($content as $lineNb => $line) {
            foreach ($metadata as $key => $commentData) {
                if (0 === $commentData['lineNb']) {
                    array_unshift($toReturn, $commentData['value']);
                    unset($metadata[$key]);
                    continue;
                }
                if ($line === $commentData['previousLine']) {
                    array_splice($toReturn, $lineNb + 1, 0, $commentData['value']);
                    unset($metadata[$key]);
                    continue;
                }

                if ($line === $commentData['nextLine']) {
                    array_splice($toReturn, $lineNb - 1, 0, $commentData['value']);
                    unset($metadata[$key]);
                    continue;
                }
            }
        }

        return implode("\n", $toReturn);
    }
}
