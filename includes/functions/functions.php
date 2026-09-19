<?php
/**
 * 主题级功能函数加载入口
 *
 * 说明：原框架此处 require `zhiji-theme.php`（其自带的"必应 URL 推送"业务功能）。
 * 按 v2 规范「功能全部自研、框架只做骨架」，该文件已剔除，
 * 对应能力将由 `includes/modules/SeoPush.php` 自研实现（M5 阶段），故此处不再加载它。
 * 主题级通用函数请放本文件，业务功能请放 includes/modules/。
 */

defined('ABSPATH') || exit;
