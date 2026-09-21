<?php
/**
 * @module  ServerStats
 * @desc    宝塔面板服务器状态展示
 * @option  bt_stats_enabled  总开关
 * @hook    init / 短代码 · 状态展示
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/ServerStats.php`
 *          （v2 迁移：CSF 块转 csf_section_for_legacy、常量与命名规范对齐）
 */

defined('ABSPATH') || exit;

Zhiji_Registry::register_module('server_stats', array(
    'title'    => '宝塔服务器状态',
    'parent'   => 'zhiji_over',
    'priority' => 20,
    'option'   => 'bt_stats_enabled',
));



if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ============================================================
 * 宝塔开放 API 客户端
 * ============================================================ */

/**
 * 计算宝塔请求签名。
 */
function zhiji_bt_sign( $time, $key ) {
	return md5( $time . md5( $key ) );
}

/**
 * 调用宝塔开放 API（带 10 秒 transient 缓存，避免页面高并发压垮面板）。
 *
 * @param string $action 接口路径，如 /system
 * @param array  $params 接口参数，如 array( 'action' => 'GetCpuInfo' )
 * @return array|WP_Error 解析后的数组
 */
function zhiji_bt_api( $action, $params = array() ) {
	$panel = trim( (string) zhiji_get_option( 'bt_panel_url', '' ) );
	$key   = trim( (string) zhiji_get_option( 'bt_api_key', '' ) );
	if ( '' === $panel || '' === $key ) {
		return new WP_Error( 'bt_not_configured', '尚未在后台配置宝塔面板地址与 API 密钥' );
	}
	if ( ! preg_match( '#^https?://#i', $panel ) ) {
		return new WP_Error( 'bt_bad_url', '面板地址需以 http(s):// 开头' );
	}

	$cache_key = 'zhiji_bt_' . md5( $action . wp_json_encode( $params ) );
	$cached    = get_transient( $cache_key );
	if ( false !== $cached ) {
		return $cached;
	}

	$time = time();
	$url  = untrailingslashit( $panel ) . $action;
	$args = array(
		'timeout' => 8,
		'body'    => array_merge( $params, array(
			'request_time' => $time,
			'request_token' => zhiji_bt_sign( $time, $key ),
		) ),
	);
	$resp = wp_remote_post( $url, $args );
	if ( is_wp_error( $resp ) ) {
		return $resp;
	}
	$code = wp_remote_retrieve_response_code( $resp );
	if ( 200 !== (int) $code ) {
		return new WP_Error( 'bt_http_' . $code, '面板返回 HTTP ' . $code );
	}
	$body = wp_remote_retrieve_body( $resp );
	$data = json_decode( $body, true );
	if ( ! is_array( $data ) ) {
		return new WP_Error( 'bt_bad_json', '面板响应解析失败' );
	}
	if ( empty( $data['status'] ) ) {
		return new WP_Error( 'bt_api_error', isset( $data['msg'] ) ? $data['msg'] : '接口调用失败' );
	}
	// 部分接口将 JSON 编码为字符串，需要二次解码
	foreach ( array( 'cpu', 'mem', 'load', 'disk', 'network', 'system' ) as $k ) {
		if ( isset( $data[ $k ] ) && is_string( $data[ $k ] ) ) {
			$decoded = json_decode( $data[ $k ], true );
			if ( is_array( $decoded ) ) {
				$data[ $k ] = $decoded;
			}
		}
	}
	set_transient( $cache_key, $data, 10 );
	return $data;
}

/**
 * 聚合服务器状态（供短代码与 API 共用）。
 *
 * @return array|WP_Error
 */
function zhiji_bt_get_status() {
	$cpu = zhiji_bt_api( '/system', array( 'action' => 'GetCpuInfo' ) );
	if ( is_wp_error( $cpu ) ) {
		return $cpu;
	}
	$net = zhiji_bt_api( '/system', array( 'action' => 'GetNetWork' ) );
	if ( is_wp_error( $net ) ) {
		$net = array();
	}

	$out = array( 'time' => current_time( 'mysql' ) );

	// CPU 使用率（GetCpuInfo 的 cpu 字段为 [[时间戳, 使用率%], ...] 最近 60 次）
	if ( isset( $cpu['cpu'][0] ) && is_array( $cpu['cpu'][0] ) ) {
		$series = $cpu['cpu'];
		$last   = end( $series );
		$out['cpu'] = is_array( $last ) && isset( $last[1] ) ? round( (float) $last[1], 1 ) : 0;
	} elseif ( isset( $cpu['cpu'] ) ) {
		$out['cpu'] = round( (float) $cpu['cpu'], 1 );
	} else {
		$out['cpu'] = 0;
	}

	// 内存
	$mem = isset( $cpu['mem'] ) && is_array( $cpu['mem'] ) ? $cpu['mem'] : array();
	if ( ! empty( $mem['memTotal'] ) ) {
		$total = (float) $mem['memTotal'];
		$used  = (float) ( isset( $mem['memUsed'] ) ? $mem['memUsed'] : ( $total - (float) $mem['memFree'] ) );
		$out['mem_total'] = $total;
		$out['mem_used']  = $used;
		$out['mem_pct']   = $total > 0 ? round( $used / $total * 100, 1 ) : 0;
	} else {
		$out['mem_total'] = 0;
		$out['mem_used']  = 0;
		$out['mem_pct']   = 0;
	}

	// 负载（1/5/15 分钟）
	if ( isset( $cpu['load'] ) && is_array( $cpu['load'] ) ) {
		$out['load'] = array_map( 'floatval', array_slice( $cpu['load'], 0, 3 ) );
	} else {
		$out['load'] = array( 0, 0, 0 );
	}

	// 磁盘
	if ( isset( $cpu['disk'] ) && is_array( $cpu['disk'] ) ) {
		$d = $cpu['disk'];
		if ( isset( $d['size'] ) && $d['size'] > 0 ) {
			$out['disk_total'] = (float) $d['size'];
			$out['disk_used']  = (float) ( isset( $d['used'] ) ? $d['used'] : 0 );
			$out['disk_pct']   = round( $out['disk_used'] / $out['disk_total'] * 100, 1 );
		}
	}

	// 实时流量（GetNetWork 返回 up/down 字节数）
	if ( isset( $net['up'] ) && isset( $net['down'] ) ) {
		$out['net_up']   = (float) $net['up'];
		$out['net_down'] = (float) $net['down'];
	} else {
		$out['net_up']   = 0;
		$out['net_down'] = 0;
	}

	// 系统信息（可选字段）
	if ( isset( $cpu['system'] ) && is_array( $cpu['system'] ) && ! empty( $cpu['system']['System'] ) ) {
		$out['os'] = $cpu['system']['System'];
	}
	if ( isset( $cpu['system'] ) && is_array( $cpu['system'] ) && ! empty( $cpu['system']['cpu_cores'] ) ) {
		$out['cores'] = (int) $cpu['system']['cpu_cores'];
	} elseif ( isset( $cpu['system'] ) && is_array( $cpu['system'] ) && ! empty( $cpu['system']['cpuCore'] ) ) {
		$out['cores'] = (int) $cpu['system']['cpuCore'];
	}

	return $out;
}

/* ============================================================
 * 前端展示（短代码 + 内联 JS 轮询）
 * ============================================================ */

/**
 * 格式化字节。
 */
function zhiji_bt_fmt( $bytes ) {
	$bytes = (float) $bytes;
	if ( $bytes >= 1073741824 ) {
		return round( $bytes / 1073741824, 2 ) . ' GB';
	}
	if ( $bytes >= 1048576 ) {
		return round( $bytes / 1048576, 1 ) . ' MB';
	}
	if ( $bytes >= 1024 ) {
		return round( $bytes / 1024, 1 ) . ' KB';
	}
	return round( $bytes ) . ' B';
}

add_shortcode( 'zhiji_server_stats', 'zhiji_bt_shortcode' );
function zhiji_bt_shortcode() {
	if ( ! filter_var( zhiji_get_option( 'bt_stats_enabled', false ), FILTER_VALIDATE_BOOLEAN ) ) {
		return '';
	}

	$status = zhiji_bt_get_status();
	if ( is_wp_error( $status ) ) {
		// 未配置时输出引导文案，配置后自动生效
		return '<div class="zhiji-bt-widget zhiji-bt-error">'
			. esc_html( '服务器状态暂不可用：' . $status->get_error_message() )
			. '</div>';
	}

	$cpu    = isset( $status['cpu'] ) ? $status['cpu'] : 0;
	$mem    = isset( $status['mem_pct'] ) ? $status['mem_pct'] : 0;
	$disk   = isset( $status['disk_pct'] ) ? $status['disk_pct'] : 0;
	$load   = isset( $status['load'] ) ? $status['load'] : array( 0, 0, 0 );
	$cores  = isset( $status['cores'] ) ? $status['cores'] : 1;
	$load1  = isset( $load[0] ) ? round( $load[0], 2 ) : 0;
	$load5  = isset( $load[1] ) ? round( $load[1], 2 ) : 0;
	$load15 = isset( $load[2] ) ? round( $load[2], 2 ) : 0;
	$net_u  = isset( $status['net_up'] ) ? zhiji_bt_fmt( $status['net_up'] ) : '0 B';
	$net_d  = isset( $status['net_down'] ) ? zhiji_bt_fmt( $status['net_down'] ) : '0 B';
	$time   = isset( $status['time'] ) ? $status['time'] : '';

	$html = '<div class="zhiji-bt-widget" data-zhiji-bt="1">';
	$html .= '<div class="zhiji-bt-grid">';
	foreach ( array(
		array( 'k' => 'cpu', 't' => 'CPU 使用率', 'v' => $cpu . '%', 'p' => $cpu, 'c' => '#3b82f6' ),
		array( 'k' => 'mem', 't' => '内存占用', 'v' => $mem . '%', 'p' => $mem, 'c' => '#10b981' ),
		array( 'k' => 'disk', 't' => '磁盘使用', 'v' => $disk . '%', 'p' => $disk, 'c' => '#f59e0b' ),
	) as $item ) {
		$html .= '<div class="zhiji-bt-item" data-k="' . esc_attr( $item['k'] ) . '">'
			. '<div class="zhiji-bt-label">' . esc_html( $item['t'] ) . '</div>'
			. '<div class="zhiji-bt-value">' . esc_html( $item['v'] ) . '</div>'
			. '<div class="zhiji-bt-track"><i class="zhiji-bt-bar" style="width:' . esc_attr( $item['p'] ) . '%;background:' . esc_attr( $item['c'] ) . '"></i></div>'
			. '</div>';
	}
	$html .= '</div>';
	$html .= '<div class="zhiji-bt-row">'
		. '<span class="zhiji-bt-label">负载（1/5/15min）</span>'
		. '<span class="zhiji-bt-load"><b>' . esc_html( $load1 ) . '</b> / ' . esc_html( $load5 ) . ' / ' . esc_html( $load15 ) . '（' . esc_html( $cores ) . ' 核）</span>'
		. '</div>';
	$html .= '<div class="zhiji-bt-row">'
		. '<span class="zhiji-bt-label">实时流量</span>'
		. '<span class="zhiji-bt-net">↑ ' . esc_html( $net_u ) . '　↓ ' . esc_html( $net_d ) . '</span>'
		. '</div>';
	$html .= '<div class="zhiji-bt-foot">' . esc_html( $time ) . ' · 每 15 秒自动刷新</div>';
	$html .= '</div>';

	zhiji_bt_enqueue_script( $status );
	return $html;
}

/**
 * 输出刷新脚本（一次性）。
 */
function zhiji_bt_enqueue_script( $initial = array() ) {
	if ( ! wp_script_is( 'zhiji-child-script', 'enqueued' ) ) {
		return;
	}
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;

	$payload = array(
		'ajax_url' => admin_url( 'admin-ajax.php' ),
		'nonce'    => wp_create_nonce( 'zhiji_nonce' ),
		'initial'  => $initial,
	);
	$json = wp_json_encode( $payload );
	wp_add_inline_script( 'zhiji-child-script', <<<JS
(function(){
  var cfg = {$json};
  var boxes = document.querySelectorAll('.zhiji-bt-widget[data-zhiji-bt="1"]');
  if(!boxes.length) return;
  var paint=(s){
    boxes.forEach(function(box){
      var items = box.querySelectorAll('.zhiji-bt-item');
      var map = {cpu:0, mem:0, disk:0};
      items.forEach(function(it){
        var k = it.getAttribute('data-k');
        if(k && typeof s[k] === 'number'){
          it.querySelector('.zhiji-bt-value').textContent = s[k].toFixed(1) + '%';
          it.querySelector('.zhiji-bt-bar').style.width = Math.min(100, s[k]).toFixed(1) + '%';
        }
      });
      var load = s.load || [];
      var l1 = box.querySelector('.zhiji-bt-load');
      if(l1 && load.length){ l1.innerHTML = '<b>'+Number(load[0]).toFixed(2)+'</b> / '+Number(load[1]||0).toFixed(2)+' / '+Number(load[2]||0).toFixed(2)+'（'+ (s.cores||1) +' 核）'; }
      var net = box.querySelector('.zhiji-bt-net');
      if(net && typeof s.net_up === 'number'){ net.textContent = '↑ ' + fmt(s.net_up) + '　↓ ' + fmt(s.net_down); }
    });
  }
  var fmt=(b){ b = b||0; if(b>=1073741824) return (b/1073741824).toFixed(2)+' GB'; if(b>=1048576) return (b/1048576).toFixed(1)+' MB'; if(b>=1024) return (b/1024).toFixed(1)+' KB'; return Math.round(b)+' B'; }
  if(cfg.initial && cfg.initial.cpu !== undefined){ paint(cfg.initial); }
  setInterval(function(){
    var fd = new FormData();
    fd.append('action','zhiji_api'); fd.append('api','server_stats'); fd.append('nonce', cfg.nonce);
    fetch(cfg.ajax_url, {method:'POST', body:fd, credentials:'same-origin'})
      .then(function(r){ return r.json(); })
      .then(function(j){ if(j && j.success && j.data) paint(j.data); })
      .catch(function(){});
  }, 15000);
})();
JS
	, 'after' );
}

/* ============================================================
 * 统一网关 handler（登录用户手动刷新 / 前端轮询共用）
 * ============================================================ */
zhiji_api_register( 'server_stats', function () {
	$status = zhiji_bt_get_status();
	if ( is_wp_error( $status ) ) {
		return $status;
	}
	return $status;
}, true );

/* ============================================================
 * 后台 CSF 设置：扩展&增强 → 宝塔服务器状态
 * ============================================================ */
add_action( 'after_setup_theme', function () {
		Zhiji_Registry::csf_section_for_legacy( 'server_stats', array(
		'title'  => '宝塔服务器状态',
		'icon'   => 'fa fa-fw fa-server',
		'parent' => 'zhiji_over',
		'priority' => 20,
		'fields' => array(
			array( 'id' => 'bt_stats_enabled', 'type' => 'switcher', 'title' => '启用服务器状态', 'default' => false, 'desc' => '在前台页面通过短代码 [zhiji_server_stats] 展示服务器 CPU/内存/负载/流量。' ),
			array( 'id' => 'bt_panel_url', 'type' => 'text', 'title' => '宝塔面板地址', 'default' => '', 'desc' => '如 http://64.90.3.220:8889（不含 /rainy 路径）', 'dependency' => array( 'bt_stats_enabled', '==', '1' ) ),
			array( 'id' => 'bt_api_key', 'type' => 'text', 'title' => '宝塔 API 密钥', 'default' => '', 'desc' => '宝塔面板 → 软件商店 → 开放API → 生成密钥', 'dependency' => array( 'bt_stats_enabled', '==', '1' ) ),
		),
	) );
}, 20 );

/* 轻量样式（内联到 wp_head，仅当短代码渲染过才输出） */
add_action( 'wp_footer', function () {
	if ( ! wp_script_is( 'zhiji-child-script', 'enqueued' ) ) {
		return;
	}
	static $css = false;
	if ( $css ) {
		return;
	}
	$css = true;
	echo '<style id="zhiji-bt-css">'
		. '.zhiji-bt-widget{background:var(--main-bg-color,#fff);border:1px solid var(--border-color,#e5e7eb);border-radius:12px;padding:18px;font-size:14px;line-height:1.6;max-width:480px;margin:12px auto}'
		. '.zhiji-bt-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:14px}'
		. '.zhiji-bt-item .zhiji-bt-label{color:var(--muted-color,#8a919f);font-size:12px;margin-bottom:4px}'
		. '.zhiji-bt-value{font-size:20px;font-weight:700;margin-bottom:8px}'
		. '.zhiji-bt-track{height:6px;border-radius:3px;background:var(--bg-color,#f1f2f3);overflow:hidden}'
		. '.zhiji-bt-bar{display:block;height:100%;border-radius:3px;transition:width .6s ease}'
		. '.zhiji-bt-row{display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-top:1px dashed var(--border-color,#e5e7eb)}'
		. '.zhiji-bt-foot{text-align:right;color:var(--muted-color,#8a919f);font-size:12px;margin-top:8px}'
		. '.zhiji-bt-error{color:#dc2626;text-align:center}'
		. '</style>';
}, 98 );
