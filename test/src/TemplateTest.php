<?php

class TemplateTest extends BaseCase
{

    public function templateProvider()
    {
        return [
            [
                'include'
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

