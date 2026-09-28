<?php
/**
 * @module  PasswordStrength
 * @desc    注册/改密时的密码强度校验（2026-09-28 起**常驻启用**，总开关已移除）
 * @option  password_min_len        最小长度（默认 8）
 *          password_need_complex   是否要求复杂度（默认开）
 *          （`password_strength_enabled` 已于 2026-09-28 按「开关评估 A5」移除）
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/PasswordStrength.php`
 *          （v2 迁移：CSF 块转 csf_section_for_legacy、常量与命名规范对齐）
 */

defined('ABSPATH') || exit;

Zhiji_Registry::register_module('password_strength', array(
    'title'     => '密码强度校验',
    'parent'    => 'zhiji_user',
    'priority'  => 160,
    // 保留 option 名义值仅为兼容历史引用；开关已按「开关评估 A5」移除，always_on 才是运行期依据
    'option'    => 'password_strength_enabled',
    'always_on' => true,
));



if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ============================================================
 * 后台 CSF 设置：用户&互动 → 密码强度校验
 * ============================================================ */
    // 2026-09-26：改为 Registry 统一登记（P3-⑨），钩子由核心统一挂载
    Zhiji_Registry::register_options('password_strength', array(
			// 2026-09-28：「启用密码强度后端校验」开关已按「开关评估 A5」移除，改为常驻 + 说明。
			// 理由：前端强度计**可被绕过**，这里是唯一真正的密码策略执行点；
			// 把安全基线做成"可关闭"，等于把"关掉它"当成一个合法选项。
			zhiji_notice(
				__( '密码强度<strong>后端校验已常驻启用</strong>（2026-09-28 起不再可配置）。'
					. '前端强度计可被绕过，这里是注册/改密时唯一不可绕过的执行点；'
					. '最小长度与复杂度仍可在下方调整。', 'zhiji' )
			),
			array(
				// ⚠️ 原来的 dependency 指向已移除的 password_strength_enabled，
				// 若保留会导致本字段**永久隐藏**（父字段不存在 → 条件永不成立）→ 故一并去掉。
				'id'         => 'password_min_len',
				'type'       => 'number',
				'title'      => '最小长度',
				'desc' => __( '注册/改密时要求的最小密码长度。', 'zhiji' ),
				'default'    => 8,
				'min'        => 6,
				'max'        => 32,
			),
			array(
				'id'         => 'password_need_complex',
				'type'       => 'switcher',
				'title'      => '需复杂度',
				'default'    => true,
				'desc'       => '开启：必须含大小写字母 + 数字（或特殊字符）。关闭：仅校验长度。',
			),
		), 20);

/* ============================================================
 * 统一校验逻辑：返回 WP_Error 或 null
 * ============================================================ */

/**
 * 校验密码强度，返回 WP_Error 或 null（null 表示通过）。
 *
 * @param string $password   密码
 * @param string $user_login 用户名（可选，用于判断密码是否与用户名相同）
 * @param string $user_email 邮箱（可选，用于判断密码是否与邮箱相同）
 * @return WP_Error|null
 */
function zhiji_password_validate( $password, $user_login = '', $user_email = '' ) {
	$min_len  = (int) zhiji_get_option( 'password_min_len', 8 );
	$need_cx  = filter_var( zhiji_get_option( 'password_need_complex', true ), FILTER_VALIDATE_BOOLEAN );

	// 长度校验
	if ( mb_strlen( $password, 'UTF-8' ) < $min_len ) {
		return new WP_Error( 'zhiji_pw_len', sprintf( '密码长度不能少于 %d 位。', $min_len ) );
	}

	// 复杂度校验
	if ( $need_cx ) {
		$has_upper   = preg_match( '/[A-Z]/', $password );
		$has_lower   = preg_match( '/[a-z]/', $password );
		$has_digit   = preg_match( '/[0-9]/', $password );
		$has_special = preg_match( '/[^A-Za-z0-9]/', $password );

		if ( ! ( $has_upper && $has_lower && ( $has_digit || $has_special ) ) ) {
			return new WP_Error( 'zhiji_pw_cx', '密码需同时包含大写字母、小写字母，以及数字或特殊字符。' );
		}
	}

	// 不与账号相同
	if ( $user_login && strcasecmp( $password, $user_login ) === 0 ) {
		return new WP_Error( 'zhiji_pw_same', '密码不能与用户名相同。' );
	}

	// 不与邮箱相同
	if ( $user_email && strcasecmp( $password, $user_email ) === 0 ) {
		return new WP_Error( 'zhiji_pw_same', '密码不能与邮箱相同。' );
	}

	return null;
}

/* ============================================================
 * 注册时后端校验：registration_errors 钩子
 * ============================================================ */
add_filter( 'registration_errors', function ( $errors, $san_login, $user_email ) {
	// 2026-09-28：总开关已移除（「开关评估 A5」）→ 此处**恒校验**，不再有短路分支。
	// 已有其它错误（如用户名重复）先返回
	if ( is_wp_error( $errors ) && $errors->get_error_code() ) {
		return $errors;
	}

	// 获取密码（兼容不同注册表单的字段名）
	$pw = '';
	if ( ! empty( $_POST['password'] ) ) {
		$pw = sanitize_text_field( wp_unslash( $_POST['password'] ) );
	} elseif ( ! empty( $_POST['user_pass'] ) ) {
		$pw = sanitize_text_field( wp_unslash( $_POST['user_pass'] ) );
	} elseif ( ! empty( $_POST['pass1'] ) ) {
		$pw = sanitize_text_field( wp_unslash( $_POST['pass1'] ) );
	}

	if ( ! $pw ) {
		return $errors;
	}

	$err = zhiji_password_validate( $pw, $san_login, $user_email );
	if ( $err instanceof WP_Error ) {
		$errors->add( $err->get_error_code(), $err->get_error_message() );
	}

	return $errors;
}, 20, 3 );

/* ============================================================
 * 改密时后端校验：user_profile_update_errors 钩子
 * ============================================================ */
add_filter( 'user_profile_update_errors', function ( $errors, $update, $user ) {
	// 2026-09-28：总开关已移除（「开关评估 A5」）→ 此处**恒校验**，不再有短路分支。
	// 仅在更新用户资料时校验（非新建）
	if ( ! $update ) {
		return $errors;
	}

	// 获取新密码
	$pw = ! empty( $_POST['pass1'] ) ? sanitize_text_field( wp_unslash( $_POST['pass1'] ) ) : '';

	if ( ! $pw ) {
		return $errors; // 未修改密码
	}

	$err = zhiji_password_validate( $pw, $user->user_login, $user->user_email );
	if ( $err instanceof WP_Error ) {
		$errors->add( $err->get_error_code(), $err->get_error_message() );
	}

	return $errors;
}, 20, 3 );
