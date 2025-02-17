<?php

declare(strict_types=1);

namespace TypistTech\Imposter;

class ConfigCollection implements ConfigCollectionInterface
{
    /**
     * @var ConfigInterface[]
     */
    private $configs = [];

    /**
     * @param ConfigInterface $config
     *
     * @return void
     */
    public function add(ConfigInterface $config)
    {
        $this->configs[$config->getPackageDir()] = $config;
    }

    /**
     * @return string[]
     */
    public function getAutoloads(): array
    {
        $autoloads = ArrayUtil::flattenMap(function (ConfigInterface $config) {
            return $config->getAutoloads();
        }, $this->all());

        // Add bin path
        foreach ($this->all() as $libPath => $config) {
            foreach ($config->getBin() as $binPath) {
                $autoloads[] = $libPath . $binPath;
            }
        }

        return array_unique($autoloads);
    }

    /**
     * @return ConfigInterface[]
     */
    public function all(): array
    {
        return $this->configs;
    }
}
