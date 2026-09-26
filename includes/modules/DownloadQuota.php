<?php
/**
 * @module  DownloadQuota
 * @desc    用户下载额度 API 类（Zhiji_Download_Quota，随下载中心加载）
 * @option  download_quota_enabled  总开关
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/DownloadQuota.php`
 *          （v2 迁移：CSF 块转 csf_section_for_legacy、常量与命名规范对齐）
 */

defined('ABSPATH') || exit;

Zhiji_Registry::register_module('download_quota', array(
    'title'    => '下载额度（辅助类）',
    'parent'   => 'zhiji_pay',
    'priority' => 15,
    'option'   => 'download_quota_enabled',
));



if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 下载次数令牌类
 */
class Zhiji_Download_Quota {

	/** 用户下载次数令牌 meta 键 */
	const DOWNLOAD_QUOTA_META = 'zhiji_download_quota';

	/**
	 * 发放下载次数令牌（叠加）。
	 *
	 * @param int $uid   用户 ID（0 取当前登录用户）
	 * @param int $value 增加次数（<=0 忽略）
	 * @return bool
	 */
	public static function add( $uid = 0, $value = 0 ) {
		$uid   = $uid ?: get_current_user_id();
		$value = (int) $value;
		if ( ! $uid || $value <= 0 ) {
			return false;
		}
		$cur = (int) get_user_meta( $uid, self::DOWNLOAD_QUOTA_META, true );
		update_user_meta( $uid, self::DOWNLOAD_QUOTA_META, $cur + $value );
		return true;
	}

	/**
	 * 查询用户剩余下载次数。
	 *
	 * @param int $uid 用户 ID（0 取当前登录用户）
	 * @return int
	 */
	public static function get( $uid = 0 ) {
		$uid = $uid ?: get_current_user_id();
		return (int) get_user_meta( $uid, self::DOWNLOAD_QUOTA_META, true );
	}

	/**
	 * 绝对设置用户下载次数令牌（clamp 到 >=0）。
	 * 用于后台「下载次数管理」页的手动调整/清零。
	 *
	 * @param int $uid   用户 ID（0 取当前登录用户）
	 * @param int $value 目标次数（<0 视为 0）
	 * @return bool
	 */
	public static function set( $uid = 0, $value = 0 ) {
		$uid   = $uid ?: get_current_user_id();
		$value = max( 0, (int) $value );
		if ( ! $uid ) {
			return false;
		}
		update_user_meta( $uid, self::DOWNLOAD_QUOTA_META, $value );
		return true;
	}

	/**
	 * 消耗 1 个下载次数令牌。
	 * 返回 true 表示扣减成功（有足够令牌），false 表示无令牌。
	 *
	 * @param int $uid 用户 ID（0 取当前登录用户）
	 * @return bool
	 */
	public static function consume( $uid = 0 ) {
		$uid = $uid ?: get_current_user_id();
		if ( ! $uid ) {
			return false;
		}
		$cur = (int) get_user_meta( $uid, self::DOWNLOAD_QUOTA_META, true );
		if ( $cur <= 0 ) {
			return false;
		}
		update_user_meta( $uid, self::DOWNLOAD_QUOTA_META, $cur - 1 );
		return true;
	}
}
