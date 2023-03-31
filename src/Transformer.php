<?php

declare(strict_types=1);

namespace TypistTech\Imposter;

use SplFileInfo;

class Transformer implements TransformerInterface
{
    /**
     * @var FilesystemInterface
     */
    private $filesystem;

    /**
     * @var string
     */
    private $namespacePrefix;

    /**
     * Transformer constructor.
     *
     * @param string              $namespacePrefix
     * @param FilesystemInterface $filesystem
     */
    public function __construct(string $namespacePrefix, FilesystemInterface $filesystem)
    {
        $this->namespacePrefix = StringUtil::ensureDoubleBackwardSlash($namespacePrefix);
        $this->filesystem = $filesystem;
    }

    /**
     * Transform a file or directory recursively.
     *
     * @todo Skip non-php files.
     *
     * @param string $target Path to the target file or directory.
     *
     * @return void
     */
    public function transform(string $target)
    {
        if ($this->filesystem->isFile($target)) {
            $this->doTransform($target);

            return;
        }

        $files = $this->filesystem->allFiles($target);

        array_walk($files, function (SplFileInfo $file) {
            $this->doTransform($file->getRealPath());
        });
    }

    /**
     * @param string $targetFile
     *
     * @return void
     */
    private function doTransform(string $targetFile)
    {
        $this->prefixNamespace($targetFile);
        $this->prefixUseConst($targetFile);
        $this->prefixUseFunction($targetFile);
        $this->prefixUse($targetFile);

        if ($this->isComposerJson($targetFile)) {
            $this->prefixComposerJson($targetFile);
        }
    }

    /**
     * @param string $targetFile
     * @return void
     */
    private function prefixComposerJson(string $targetFile)
    {
        $content = $this->filesystem->get($targetFile);
        $arrayContent = json_decode($content, true);

        if (JSON_ERROR_NONE !== json_last_error()) {
            return;
        }

        if (
            !isset($arrayContent['autoload'])
            && !isset($arrayContent['autoload']['psr-4'])
        ) {
            return;
        }

        $autoload = $arrayContent['autoload']['psr-4'];

        if (!is_array($autoload)) {
            return;
        }

        $newAutoload = [];

        foreach ($autoload as $namespace => $item) {
            $newAutoload[str_replace('\\\\', '\\', $this->namespacePrefix) . $namespace] = $item;
        }

        $arrayContent['autoload']['psr-4'] = $newAutoload;

        $newContent = json_encode($arrayContent, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT);

        $this->filesystem->put($targetFile, $newContent);
    }

    /**
     * @param string $targetFile
     * @return bool
     */
    private function isComposerJson(string $targetFile)
    {
        return strpos($targetFile, 'composer.json') !== false;
    }

    /**
     * Prefix namespace at the given path.
     *
     * @param string $targetFile
     *
     * @return void
     */
    private function prefixNamespace(string $targetFile)
    {
        $pattern = sprintf(
            '/(\s+)%1$s\\s+(?!(%2$s)|(Composer(\\\\|;))|(\\\\\"|\\\\\'))(?=\S*\n)/',
            'namespace',
            $this->namespacePrefix
        );
        $replacement = sprintf('%1$s %2$s', '${1}namespace', $this->namespacePrefix);

        $this->replace($pattern, $replacement, $targetFile);
    }

    /**
     * Replace string in the given file.
     *
     * @param string $pattern
     * @param string $replacement
     * @param string $targetFile
     *
     * @return void
     */
    private function replace(string $pattern, string $replacement, string $targetFile)
    {
        $this->filesystem->put(
            $targetFile,
            preg_replace(
                $pattern,
                $replacement,
                $this->filesystem->get($targetFile)
            )
        );
    }

    /**
     * Prefix `use const` keywords at the given path.
     *
     * @param string $targetFile
     *
     * @return void
     */
    private function prefixUseConst(string $targetFile)
    {
        $pattern = sprintf(
            '/%1$s\\s+(?!(%2$s)|(\\\\(?!.*\\\\.*))|(Composer(\\\\|;)|(?!.*\\\\.*)))/',
            'use const',
            $this->namespacePrefix
        );
        $replacement = sprintf('%1$s %2$s', 'use const', $this->namespacePrefix);

        $this->replace($pattern, $replacement, $targetFile);
    }

    /**
     * Prefix `use function` keywords at the given path.
     *
     * @param string $targetFile
     *
     * @return void
     */
    private function prefixUseFunction(string $targetFile)
    {
        $pattern = sprintf(
            '/%1$s\\s+(?!(%2$s)|(\\\\(?!.*\\\\.*))|(Composer(\\\\|;)|(?!.*\\\\.*)))/',
            'use function',
            $this->namespacePrefix
        );
        $replacement = sprintf('%1$s %2$s', 'use function', $this->namespacePrefix);

        $this->replace($pattern, $replacement, $targetFile);
    }

    /**
     * Prefix `use` keywords at the given path.
     *
     * @param string $targetFile
     *
     * @return void
     */
    private function prefixUse(string $targetFile)
    {
        $pattern = sprintf(
            '/%1$s\\s+(?!(const)|(function)|(%2$s)|(\\\\(?!.*\\\\.*))|(Composer(\\\\|;)|(?!.*\\\\.*)))/',
            'use',
            $this->namespacePrefix
        );
        $replacement = sprintf('%1$s %2$s', 'use', $this->namespacePrefix);

        $this->replace($pattern, $replacement, $targetFile);
    }
}
