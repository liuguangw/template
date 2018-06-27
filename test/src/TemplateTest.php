<?php
use PHPUnit\Framework\TestCase;
use liuguang\template\TemplateEngine;

class TemplateTest extends TestCase
{

    private function doTemplate(string $templateName, $layout = '')
    {
        $tpl = new TemplateEngine($templateName, __DIR__ . '/../template');
        $tpl->setForceRebuild(true);
        if ($layout != '') {
            $tpl->setLayout($layout);
        }
        $this->assertFileEquals(__DIR__ . '/../template_assert/' . $templateName . '.php', $tpl->getTargetPath());
    }

    public function templateProvider()
    {
        return [
            [
                'include'
            ],
            [
                'include2'
            ],
            [
                'template'
            ],
            [
                'control1'
            ],
            [
                'control2'
            ],
            [
                'php'
            ],
            [
                'text'
            ],
            [
                'layout',
                'main'
            ],
            [
                'layout1'
            ],
            [
                'layout2',
                'main'
            ],
            [
                'block'
            ],
            [
                'block1',
                'main1'
            ]
        ];
    }

    /**
     * @dataProvider templateProvider
     */
    public function testAll($templateName, $layout = '')
    {
        $this->doTemplate($templateName, $layout);
    }
}

