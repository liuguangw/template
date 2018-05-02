<?php
use PHPUnit\Framework\TestCase;
use liuguang\template\TemplateEngine;

class BaseCase extends TestCase
{

    public function doTemplate(string $templateName, $layout = '')
    {
        $tpl = new TemplateEngine(__DIR__ . '/../template', '', false);
        $tpl->setTemplateName($templateName);
        if ($layout != '') {
            $tpl->setLayout($layout);
        }
        $this->assertFileEquals(__DIR__ . '/../template_assert/' . $templateName . '.php', $tpl->getTargetPath());
    }
}

