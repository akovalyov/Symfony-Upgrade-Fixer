<?php

namespace Symfony\Upgrade\Test\Fixer;

class YamlFixerTest extends AbstractFixerTestBase
{
    /**
     * @dataProvider provideExamples
     */
    public function testFix($expected, $input, $file)
    {
        $this->makeTest($expected, $input, $file);
    }

    public function provideExamples()
    {
        return [
            $this->prepareTestCase('case1-output.yml', 'case1-input.yml'),
//            $this->prepareTestCase('comment1-output.yml', 'comment1-input.yml'),
            $this->prepareTestCase('comment2-output.yml', 'comment2-input.yml'),
        ];
    }
}
