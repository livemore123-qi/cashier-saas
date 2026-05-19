<?php

namespace App\Extensions;

/**
 * 扩展模块基类
 * 所有扩展模块需继承此类
 */
abstract class ExtensionBase
{
    /**
     * 扩展唯一标识
     */
    protected string $code;

    /**
     * 扩展名称
     */
    protected string $name;

    /**
     * 扩展版本
     */
    protected string $version = '1.0.0';

    /**
     * 扩展描述
     */
    protected string $description = '';

    /**
     * 作者
     */
    protected string $author = '';

    /**
     * 扩展类型: payments | 安防 | network | openclaw | cli
     */
    protected string $type = 'generic';

    /**
     * 依赖扩展
     */
    protected array $depends = [];

    /**
     * 是否启用
     */
    protected bool $enabled = false;

    /**
     * 配置项
     */
    protected array $config = [];

    /**
     * 获取扩展信息
     */
    public function getInfo(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'version' => $this->version,
            'description' => $this->description,
            'author' => $this->author,
            'type' => $this->type,
            'depends' => $this->depends,
            'enabled' => $this->enabled,
            'config' => $this->config,
        ];
    }

    /**
     * 安装扩展
     */
    public function install(): bool
    {
        return true;
    }

    /**
     * 卸载扩展
     */
    public function uninstall(): bool
    {
        return true;
    }

    /**
     * 启用扩展
     */
    public function enable(): bool
    {
        $this->enabled = true;
        return true;
    }

    /**
     * 禁用扩展
     */
    public function disable(): bool
    {
        $this->enabled = false;
        return true;
    }

    /**
     * 获取配置
     */
    public function getConfig(string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->config;
        }
        return $this->config[$key] ?? $default;
    }

    /**
     * 设置配置
     */
    public function setConfig(string $key, $value): self
    {
        $this->config[$key] = $value;
        return $this;
    }

    /**
     * 获取扩展目录
     */
    public function getPath(): string
    {
        return base_path('extensions/' . $this->type . '/' . $this->code);
    }

    /**
     * 注册服务
     * 子类可重写
     */
    public function register(): void
    {
    }

    /**
     * 启动服务
     * 子类可重写
     */
    public function boot(): void
    {
    }
}