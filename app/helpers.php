<?php

/**
 * 获取配置项
 * @param string $key 配置键，支持点号分隔，如 'site.name'
 * @param mixed $default 默认值
 * @return mixed
 */
function config($key = null, $default = null)
{
    $config = defined('CONFIG') ? CONFIG : [];
    
    if (is_null($key)) {
        return $config;
    }
    
    $keys = explode('.', $key);
    $value = $config;
    
    foreach ($keys as $k) {
        if (!isset($value[$k])) {
            return $default;
        }
        $value = $value[$k];
    }
    
    return $value;
}
