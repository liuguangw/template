<?php
namespace liuguang\template;

class TemplateEngine
{

    protected $contentType = 'text/html; charset=UTF-8';

    protected $sourceFileSuffix = '.tpl';

    protected $baseDir;

    protected $sourceDir;

    protected $cacheDir;

    protected $layoutDir;

    protected $useCache;

    protected $sourceFilePath;

    protected $cacheFilePath;

    protected $layoutFilePath;

    protected $params = [];

    /**
     * 开始标签
     *
     * @var string
     */
    protected $startTag = '{';

    /**
     * 结束标签
     *
     * @var string
     */
    protected $endTag = '}';

    /**
     *
     * @param string $baseDir
     *            模板基础目录
     * @param string $cacheDir
     *            模板缓存目录
     * @param bool $useCache
     *            是否使用已经存在的缓存
     */
    public function __construct(string $baseDir, string $cacheDir = '', bool $useCache = true)
    {
        $this->baseDir = $baseDir;
        $this->sourceDir = $baseDir . '/./src';
        if ($cacheDir == '') {
            $this->cacheDir = $baseDir . '/./dist';
        } else {
            $this->cacheDir = $cacheDir;
        }
        $this->layoutDir = $baseDir . '/./layout';
        $this->useCache = $useCache;
    }

    public function setLayout(string $layout): void
    {
        $this->layoutFilePath = $this->layoutDir . '/./' . $layout . $this->sourceFileSuffix;
    }

    public function setTemplateName(string $templateName): void
    {
        $this->sourceFilePath = $this->sourceDir . '/./' . $templateName . $this->sourceFileSuffix;
        $this->cacheFilePath = $this->cacheDir . '/./' . $templateName . '.php';
    }

    public function setContentType(string $contentType): void
    {
        $this->contentType = $contentType;
    }

    public function addParams(array $params): void
    {
        $this->params = array_merge($this->params, $params);
    }

    public function setParam(string $key, $value): void
    {
        $this->params[$key] = $value;
    }

    protected function renderHeader(): void
    {
        header('Content-Type: ' . $this->contentType);
    }

    protected function renderContent(): void
    {
        extract($this->params);
        include $this->getTargetPath();
    }

    /**
     * 获取模板源代码
     *
     * @return string
     */
    public function getTemplateSource(): string
    {
        $content = @file_get_contents($this->sourceFilePath);
        if ($content === false) {
            throw new TemplateException('读取模板源文件' . $this->sourceFilePath . '失败');
        }
        // 与布局文件合并
        if ($this->layoutFilePath !== null) {
            $layoutContent = @file_get_contents($this->layoutFilePath);
            if ($layoutContent === false) {
                throw new TemplateException('读取布局源文件' . $this->layoutFilePath . '失败');
            }
            $content = str_replace('{content}', $content, $layoutContent);
        }
        return $content;
    }

    protected function buildTemplate(): void
    {
        $content = $this->getTemplateSource();
        $distFileDir = dirname($this->cacheFilePath);
        if (! is_dir($distFileDir)) {
            mkdir($distFileDir, 0755, true);
        }
        // start
        // 处理include合并
        // /
        // /{include mobile/header}
        // /
        $this->processIncludeTag($content);
        // 处理动态包含
        // /
        // /{template mobile/header}
        // /
        $this->processDynamicTag($content);
        // 处理扩展
        if ($this->hasExtendRule()) {
            $this->extendTemplate($content);
        }
        // 处理变量输出
        // /
        // /{$a}
        // /
        $this->processVars($content);
        // 处理变量输出(过滤特殊符号)
        // /
        // /{text $a}
        // /
        $this->processTextVars($content);
        // 处理php标签
        // /
        // /{php}echo hello world;{/php}
        // /
        $this->processPhpTag($content);
        // 处理block
        $this->processBlocks($content);
        // 处理注释
        // /
        // /{info this is a comment}
        // /
        $this->processComment($content);
        // 处理if标签
        // /
        // /{if true}
        // /
        $this->processIfCondition($content);
        // 处理elseif标签
        // /
        // /{elseif true}
        // /
        $this->processElseifCondition($content);
        // 处理else标签
        // /
        // /{else}
        // /
        $this->processElseCondition($content);
        // 处理条件结束标签
        // /
        // /{/if}
        // /
        // /
        // /{/loop}
        // /
        $this->processEndCondition($content);
        // 处理loop循环标签
        // /
        // /{loop $arr $val}
        // /
        // /
        // /{loop $arr $key $val}
        // /
        $this->processLoop($content);
        // 编译时间记录
        // /
        // /{build_time}
        // /
        $this->processBuildTime($content);
        // 处理不转换的标签
        // /
        // /{!}{$val}
        // /
        $this->processNoConvert($content);
        // /合并PHP标签
        $this->mergePhpContent($content);
        // end
        $p = file_put_contents($this->cacheFilePath, $content);
        if ($p === false) {
            throw new TemplateException('模板缓存文件' . $this->cacheFilePath . '写入失败');
        }
    }

    public function getTargetPath(): string
    {
        if (! $this->useCache) {
            $this->buildTemplate();
        } elseif (! is_file($this->cacheFilePath)) {
            $this->buildTemplate();
        }
        return $this->cacheFilePath;
    }

    public function display(): void
    {
        $this->renderHeader();
        $this->renderContent();
    }

    /**
     * 用于判断是否有附加的模板语法(用于子类添加额外的模板标签)
     *
     * @return bool
     */
    protected function hasExtendRule(): bool
    {
        return false;
    }

    /**
     * 模板附加标签语法
     *
     * @param string $tplContent
     *            模板内容
     * @return void
     */
    protected function extendTemplate(string &$tplContent)
    {}

    /**
     * 获取tag的正则表达式
     *
     * @param string $pattern
     *            中间标签
     * @return string
     */
    protected function getTagPattern(string $pattern): string
    {
        // {...}标签左侧为{!}时不执行转换
        return '/(?<!{!})' . preg_quote($this->startTag) . $pattern . preg_quote($this->endTag) . '/s';
    }

    /**
     * 处理include标签
     *
     * @param string $content
     *            原模板内容
     * @return void
     */
    protected function processIncludeTag(string &$content): void
    {
        $pattern = $this->getTagPattern('include\s+(.+?)');
        $subTemplate = new static($this->baseDir, $this->cacheDir, $this->useCache);
        while (preg_match($pattern, $content) != 0) {
            $content = preg_replace_callback($pattern, function ($match) use ($subTemplate) {
                $subTemplate->setTemplateName($match[1]);
                return $subTemplate->getTemplateSource();
            }, $content);
        }
    }

    protected function processDynamicTag(string &$content)
    {
        $pattern = $this->getTagPattern('template\s+(.+?)');
        $baseDir = $this->baseDir;
        $cacheDir = $this->cacheDir;
        $useCache = $this->useCache;
        $engineClass = get_class($this);
        $content = preg_replace_callback($pattern, function ($match) use ($baseDir, $cacheDir, $useCache, $engineClass) {
            $varName = '$subTemplate_' . substr(md5($match[1]), 0, 6);
            return '<?php ' . $varName . ' = new \\' . $engineClass . '(' . var_export($baseDir,true) . ', '. var_export($cacheDir,true) . ', ' . ($useCache ? 'true' : 'false') . ');
' . $varName . '->setTemplateName(\'' . $match[1] . '\');
include ' . $varName . '->getTargetPath(); ?>';
        }, $content);
    }

    /**
     * 处理变量输出
     *
     * @param string $content
     *            原模板内容
     * @return void
     */
    protected function processVars(string &$content): void
    {
        $pattern = $this->getTagPattern('(\$.+?)');
        $content = preg_replace_callback($pattern, function ($match) {
            return '<?php echo ' . $match[1] . '; ?>';
        }, $content);
    }

    /**
     * 处理变量输出(过滤HTML特殊符号)
     *
     * @param string $content
     *            原模板内容
     * @return void
     */
    protected function processTextVars(string &$content): void
    {
        $pattern = $this->getTagPattern('text\s+(\$.+?)');
        $content = preg_replace_callback($pattern, function ($match) {
            return '<?php echo str_replace([\'&\',\'<\',\'>\'],[\'&amp;\',\'&lt;\',\'&gt;\'],' . $match[1] . '); ?>';
        }, $content);
    }

    /**
     * 处理注释
     *
     * @param string $content
     *            原模板内容
     * @return void
     */
    protected function processComment(string &$content): void
    {
        $pattern = $this->getTagPattern('info\s+(.+?)');
        $content = preg_replace_callback($pattern, function ($match) {
            return '<?php /*' . $match[1] . '*/ ?>';
        }, $content);
    }

    /**
     * 处理if标签
     *
     * @param string $content
     *            原模板内容
     * @return void
     */
    protected function processIfCondition(string &$content): void
    {
        $pattern = $this->getTagPattern('if\s+(.+?)');
        $content = preg_replace_callback($pattern, function ($match) {
            return '<?php if( ' . $match[1] . ' ) { ?>';
        }, $content);
    }

    /**
     * 处理else标签
     *
     * @param string $content
     *            原模板内容
     * @return void
     */
    protected function processElseCondition(string &$content): void
    {
        $pattern = $this->getTagPattern('else');
        $content = preg_replace_callback($pattern, function ($match) {
            return '<?php } else { ?>';
        }, $content);
    }

    /**
     * 处理elseif标签
     *
     * @param string $content
     *            原模板内容
     * @return void
     */
    protected function processElseifCondition(string &$content): void
    {
        $pattern = $this->getTagPattern('elseif\s+(.+?)');
        $content = preg_replace_callback($pattern, function ($match) {
            return '<?php } elseif(' . $match[1] . ') { ?>';
        }, $content);
    }

    /**
     * 处理条件结束标签
     *
     * @param string $content
     *            原模板内容
     * @return void
     */
    protected function processEndCondition(string &$content): void
    {
        $pattern = $this->getTagPattern(preg_quote('/', '/') . '(if|loop)');
        $content = preg_replace_callback($pattern, function ($match) {
            return '<?php } ?>';
        }, $content);
    }

    /**
     * 处理loop循环标签
     *
     * @param string $content
     *            原模板内容
     * @return void
     */
    protected function processLoop(string &$content): void
    {
        $paramsRexp = '\\$[a-zA-Z_\\x7f-\\xff][a-zA-Z0-9_\\x7f-\\xff]*';
        $pattern = $this->getTagPattern('loop\s+(.+?)\s+(' . $paramsRexp . ')(\s+(' . $paramsRexp . '))?');
        $content = preg_replace_callback($pattern, function ($match) {
            if (isset($match[4])) {
                return '<?php foreach(' . $match[1] . ' as ' . $match[2] . ' => ' . $match[4] . '){ ?>';
            } else {
                return '<?php foreach(' . $match[1] . ' as ' . $match[2] . '){ ?>';
            }
        }, $content);
    }

    /**
     * 处理编译时间记录标签
     *
     * @param string $content
     *            原模板内容
     * @return void
     */
    protected function processBuildTime(string &$content): void
    {
        $pattern = $this->getTagPattern('build_time');
        $content = preg_replace_callback($pattern, function ($match) {
            return '<?php /*模板编译于' . date('Y-m-d H:i:s') . '*/ ?>';
        }, $content);
    }

    /**
     * 处理php标签
     *
     * @param string $content
     *            原模板内容
     * @return void
     */
    protected function processPhpTag(string &$content): void
    {
        $pattern = $this->getTagPattern('php' . preg_quote($this->endTag, '/') . '(.+?)' . preg_quote($this->startTag . '/php', '/'));
        $content = preg_replace_callback($pattern, function ($match) {
            return '<?php ' . $match[1] . ' ?>';
        }, $content);
    }

    /**
     * 处理block
     *
     * @param string $content            
     * @return void
     */
    protected function processBlocks(string &$content): void
    {
        $pattern = $this->getTagPattern('block\s+([_a-zA-Z][_a-zA-Z0-9]*)' . preg_quote($this->endTag, '/') . '\s*(.+?)\s*' . preg_quote($this->startTag . '/block', '/'));
        $blockCodes = [];
        $content = preg_replace_callback($pattern, function ($match) use (&$blockCodes) {
            $blockCodes[$match[1]] = $match[2];
            return '';
        }, $content);
        $displayPattern = $this->getTagPattern('display_block\s+([_a-zA-Z][_a-zA-Z0-9]*)');
        $content = preg_replace_callback($displayPattern, function ($match) use ($blockCodes) {
            if (isset($blockCodes[$match[1]])) {
                return $blockCodes[$match[1]];
            }
            return '';
        }, $content);
    }

    /**
     * 处理不转换标识
     *
     * @param string $content            
     * @return void
     */
    protected function processNoConvert(string &$content): void
    {
        $pattern = '/{!}(' . preg_quote($this->startTag) . '.+?' . preg_quote($this->endTag) . ')/s';
        $content = preg_replace($pattern, '\1', $content);
    }

    /**
     * 合并PHP标签
     *
     * @param string $content            
     * @return void
     */
    protected function mergePhpContent(string &$content): void
    {
        $content = preg_replace_callback('/\?\>(\s*)\<\?php/', function ($match) {
            return $match[1];
        }, $content);
    }
}

