<?php

define('IN_SYS', true);
define('ROOT', dirname(__DIR__) . '/');
include ROOT . 'rd/bootstrap.php';

if (!isAdmin()) {
    redirect('index.php');
    exit;
}

// ============================================================
// 宝塔面板配置 —— 从系统配置表读取（管理员在后台「系统配置 → 宝塔面板」中添加）
// 配置格式（bt_panels_config，JSON 数组）：
// [{"id":"bt_xxx","name":"服务器A","url":"http://1.2.3.4:8888","key":"xxxxxx"}, ...]
// ============================================================
$btPanels = json_decode(conf('bt_panels_config', '[]'), true);
if (!is_array($btPanels)) $btPanels = [];

class BTDomainAPI {
    private $panel;
    private $key;
    public function __construct($panel, $key) {
        $this->panel = rtrim($panel, '/');
        $this->key = $key;
    }
    private function keyData() {
        $t = time();
        return ['request_token' => md5($t . '' . md5($this->key)), 'request_time' => $t];
    }
    // 获取站点列表
    public function getSiteList($limit = 500) {
        $url = $this->panel . '/data?action=getData';
        $data = $this->keyData();
        $data['table'] = 'sites';
        $data['limit'] = $limit;
        $data['p'] = 1;
        $res = $this->post($url, $data);
        $json = json_decode($res, true);
        if (!is_array($json)) return [];
        $list = (isset($json['data']) && is_array($json['data'])) ? $json['data'] : $json;
        return is_array($list) ? $list : [];
    }
    public function getYmList($id) {
        $url = $this->panel . '/data?action=getData';
        $data = $this->keyData();
        $data['search'] = $id;
        $data['list'] = 'True';
        $data['table'] = 'domain';
        $res = $this->post($url, $data);
        $json = json_decode($res, true);
        if (!is_array($json)) return [];
        $list = (isset($json['data']) && is_array($json['data'])) ? $json['data'] : $json;
        return is_array($list) ? $list : [];
    }

    public function siteSetStatus($id, $name, $start) {
        $url = $this->panel . '/site?action=' . ($start ? 'SiteStart' : 'SiteStop');
        $data = $this->keyData();
        $data['id'] = $id;
        $data['name'] = $name;
        $res = $this->post($url, $data);
        $json = json_decode($res, true);
        return is_array($json) ? $json : ['raw' => $res];
    }
    private function post($url, $data, $timeout = 30) {
        $ck = sys_get_temp_dir() . '/bt_domain_ck_' . md5($this->panel) . '.txt';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $ck);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $ck);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $out = curl_exec($ch);
        curl_close($ch);
        return $out;
    }
}

$cacheFile = ROOT . 'cache/domain_check.cache.php';
$violFile  = ROOT . 'cache/domain_check_violations.json';

function dcLoadCache($f) {
    if (is_file($f)) {
        $c = include $f;
        if (is_array($c) && isset($c['time']) && isset($c['data'])) return $c;
    }
    return null;
}
function dcSaveCache($f, $data) {
    if (!is_dir(dirname($f))) @mkdir(dirname($f), 0755, true);
    @file_put_contents($f, '<?php return ' . var_export(['time' => time(), 'data' => $data], true) . ';', LOCK_EX);
}
function dcUpdateCacheStatus($f, $panelId, $siteId, $status) {
    $c = dcLoadCache($f);
    if ($c && is_array($c['data'])) {
        foreach ($c['data'] as &$row) {
            if ($row['panel_id'] === $panelId && $row['site_id'] === $siteId) { $row['status'] = $status; break; }
        }
        dcSaveCache($f, $c['data']);
    }
}
function dcLoadViol($f) {
    if (is_file($f)) {
        $v = @json_decode(@file_get_contents($f), true);
        return is_array($v) ? $v : [];
    }
    return [];
}
function dcSaveViol($f, $v) {
    if (!is_dir(dirname($f))) @mkdir(dirname($f), 0755, true);
    @file_put_contents($f, json_encode($v, JSON_UNESCAPED_UNICODE | LOCK_EX));
}

// 遍历所有宝塔面板，拉取站点列表和域名
function dcFetchAll($btPanels, $cacheFile) {
    @set_time_limit(600);
    if (empty($btPanels)) {
        return ['error' => '尚未配置任何宝塔面板，请先在「系统配置 → 宝塔面板」中添加。', 'rows' => []];
    }
    $rows = [];
    $globalErr = '';
    foreach ($btPanels as $panel) {
        if (empty($panel['url']) || empty($panel['key'])) {
            $globalErr .= '面板「' . ($panel['name'] ?? '未命名') . '」缺少地址或密钥；';
            continue;
        }
        $api = new BTDomainAPI($panel['url'], $panel['key']);
        try {
            $sites = $api->getSiteList();
        } catch (\Throwable $e) {
            $globalErr .= '面板「' . ($panel['name'] ?? '未命名') . '」获取站点列表失败：' . $e->getMessage() . '；';
            continue;
        }
        if (!is_array($sites)) $sites = [];
        foreach ($sites as $s) {
            $names = [];
            $apiErr = '';
            $siteId = $s['id'] ?? '';
            $siteName = $s['name'] ?? '';
            $siteStatus = isset($s['status']) ? strval($s['status']) : '1';
            $expire = $s['edate'] ?? '';
            if (!empty($siteId)) {
                try {
                    $domains = $api->getYmList($siteId);
                    foreach ($domains as $d) {
                        if (is_array($d) && !empty($d['name'])) $names[] = $d['name'];
                    }
                } catch (\Throwable $e) {
                    $apiErr = $e->getMessage();
                }
            } else {
                $apiErr = '缺少站点ID';
            }
            $rows[] = [
                'panel_id'   => $panel['id'] ?? '',
                'panel_name' => $panel['name'] ?? '未命名',
                'site_id'    => $siteId,
                'site_name'  => $siteName,
                'expire'     => $expire,
                'domains'    => $names,
                'apiErr'     => $apiErr,
                'status'     => $siteStatus,
            ];
        }
    }
    dcSaveCache($cacheFile, $rows);
    return ['error' => $globalErr, 'rows' => $rows];
}


$act = $_REQUEST['act'] ?? '';
if (in_array($act, ['pause', 'resume', 'mark', 'unmark'], true)) {
    header('Content-Type: application/json; charset=utf-8');
    $panelId = trim((string)($_REQUEST['panel_id'] ?? ''));
    $siteId  = trim((string)($_REQUEST['site_id'] ?? ''));
    $siteName = trim((string)($_REQUEST['site_name'] ?? ''));
    $violKey = $panelId . '|' . $siteName;

    // mark/unmark 只需要面板ID和站点名；pause/resume 还需要站点ID
    if ($panelId === '' || $siteName === '') {
        echo json_encode(['ok' => 0, 'msg' => '缺少面板或站点参数']); exit;
    }
    if (in_array($act, ['pause', 'resume'], true) && $siteId === '') {
        echo json_encode(['ok' => 0, 'msg' => '缺少站点ID']); exit;
    }

    $viol = dcLoadViol($violFile);

    // 违规标记
    if ($act === 'mark' || $act === 'unmark') {
        if ($act === 'mark') {
            $reason = trim((string)($_REQUEST['reason'] ?? ''));
            $viol[$violKey] = ['reason' => $reason, 'at' => date('Y-m-d H:i:s')];
        } else {
            unset($viol[$violKey]);
        }
        dcSaveViol($violFile, $viol);
        echo json_encode(['ok' => 1, 'msg' => ($act === 'mark' ? '已标记为违规' : '已取消违规标记')]);
        exit;
    }

    // 暂停/恢复：找到对应面板配置
    $targetPanel = null;
    foreach ($btPanels as $p) {
        if (($p['id'] ?? '') === $panelId) { $targetPanel = $p; break; }
    }
    if (!$targetPanel) { echo json_encode(['ok' => 0, 'msg' => '未找到对应宝塔面板配置']); exit; }

    $start = ($act === 'resume');
    try {
        $api = new BTDomainAPI($targetPanel['url'], $targetPanel['key']);
        $r = $api->siteSetStatus($siteId, $siteName, $start);
        dcUpdateCacheStatus($cacheFile, $panelId, $siteId, $start ? '1' : '0');
        $btMsg = $r['msg'] ?? (isset($r['status']) ? 'status=' . var_export($r['status'], true) : json_encode($r, JSON_UNESCAPED_UNICODE));
        echo json_encode(['ok' => 1, 'msg' => ($start ? '已恢复站点：' : '已暂停站点：') . $btMsg]);
    } catch (\Throwable $e) {
        echo json_encode(['ok' => 0, 'msg' => '操作失败：' . $e->getMessage()]);
    }
    exit;
}

// ============ 普通渲染 ============
$force = isset($_GET['refresh']);
$rows = [];
$error = '';
$cachedAt = 0;

if (empty($btPanels)) {
    $error = '尚未配置任何宝塔面板，请先在「系统配置 → 宝塔面板」中添加面板地址和 API 密钥，添加后方可使用域名检查功能。';
} elseif ($force || !dcLoadCache($cacheFile)) {
    $r = dcFetchAll($btPanels, $cacheFile);
    $error = $r['error'];
    $rows = $r['rows'];
    $cachedAt = time();
} else {
    $c = dcLoadCache($cacheFile);
    $rows = $c['data'];
    $cachedAt = $c['time'];
}

$viol = dcLoadViol($violFile);

$totalSites = count($rows);
$totalDomains = 0;
foreach ($rows as $r) $totalDomains += count($r['domains']);
$badCount = 0;
foreach ($rows as $r) if ($r['apiErr'] !== '') $badCount++;
$violCount = 0;
foreach ($rows as $r) if (isset($viol[($r['panel_id'] ?? '') . '|' . $r['site_name']])) $violCount++;
$pausedCount = 0;
foreach ($rows as $r) if (($r['status'] ?? '1') === '0') $pausedCount++;

function dcStatusHtml($status, $violated, $reason) {
    $paused = ($status ?? '1') === '0';
    $h = '';
    if ($paused) $h .= '<span class="badge" style="background:#fee2e2;color:#b91c1c">已暂停</span>';
    if ($violated) $h .= '<span class="badge" title="' . h($reason) . '" style="background:#fef3c7;color:#92400e">违规</span>';
    return $h === '' ? '<span class="muted">正常</span>' : $h;
}
function dcOpHtml($panelId, $siteId, $siteName, $status, $violated) {
    $paused = ($status ?? '1') === '0';
    $pid = h($panelId); $sid = h($siteId); $sn = h($siteName);
    if ($paused) $p = '<button class="btn-act btn-green" data-act="resume" data-panel="' . $pid . '" data-site="' . $sid . '" data-name="' . $sn . '">恢复</button> ';
    else $p = '<button class="btn-act btn-red" data-act="pause" data-panel="' . $pid . '" data-site="' . $sid . '" data-name="' . $sn . '">暂停</button> ';
    if ($violated) $p .= '<button class="btn-act btn-gray" data-act="unmark" data-panel="' . $pid . '" data-name="' . $sn . '">取消违规</button>';
    else $p .= '<button class="btn-act btn-yellow" data-act="mark" data-panel="' . $pid . '" data-name="' . $sn . '">标记违规</button>';
    return $p;
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>域名检查 - 54188 管理后台</title>
<style>
body{font-family:-apple-system,"PingFang SC","Microsoft YaHei",sans-serif;background:#f0f2f5;margin:0;color:#1f2937}
.topbar{background:#1e293b;color:#fff;padding:14px 22px;display:flex;align-items:center;gap:14px;flex-wrap:wrap}
.topbar h1{font-size:1.05rem;margin:0;font-weight:600}
.topbar .stat{font-size:.8rem;color:#cbd5e1;background:rgba(255,255,255,.08);padding:4px 10px;border-radius:20px}
.topbar .btn{margin-left:auto;background:#6366f1;color:#fff;text-decoration:none;padding:8px 16px;border-radius:8px;font-size:.85rem;font-weight:600}
.wrap{padding:22px;max-width:1280px;margin:0 auto}
.toolbar{display:flex;gap:12px;align-items:center;margin-bottom:14px;flex-wrap:wrap}
.toolbar input[type=text]{flex:1;min-width:240px;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:.9rem}
.toolbar label{font-size:.85rem;color:#374151;display:flex;align-items:center;gap:6px}
table{width:100%;border-collapse:collapse;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.06)}
th,td{padding:12px 14px;text-align:left;font-size:.86rem;border-bottom:1px solid #f1f5f9;vertical-align:top}
th{background:#f8fafc;color:#475569;font-weight:600;white-space:nowrap}
tr:hover td{background:#fafbff}
.panel-name{font-weight:600;color:#7c3aed;font-size:.8rem}
.site-name{font-weight:600;color:#1d4ed8}
.exp{color:#64748b;white-space:nowrap}
.dom a{display:inline-block;background:#eef2ff;color:#4338ca;text-decoration:none;padding:3px 9px;border-radius:6px;margin:2px 4px 2px 0;font-size:.8rem;word-break:break-all}
.dom a:hover{background:#c7d2fe}
.muted{color:#9ca3af;font-size:.82rem}
.err{color:#dc2626;font-size:.82rem}
.none{color:#9ca3af}
.foot{margin-top:14px;color:#94a3b8;font-size:.78rem;text-align:center}
.badge{display:inline-block;padding:2px 8px;border-radius:20px;font-size:.72rem;margin-right:4px}
.btn-act{font-size:.78rem;padding:4px 10px;border:none;border-radius:6px;cursor:pointer;font-weight:600;color:#fff}
.btn-act:disabled{opacity:.5;cursor:not-allowed}
.btn-red{background:#dc2626}.btn-green{background:#059669}.btn-yellow{background:#d97706}.btn-gray{background:#64748b}
.status-cell .muted{font-size:.8rem}
.notice{background:#fffbeb;border:1px solid #fcd34d;color:#92400e;padding:14px 18px;border-radius:10px;margin-bottom:14px;font-size:.88rem}
.notice a{color:#4338ca;font-weight:600}
</style>
</head>
<body>
<div class="topbar">
  <h1>域名检查</h1>
  <span class="stat">站点 <?php echo $totalSites; ?></span>
  <span class="stat">域名 <?php echo $totalDomains; ?></span>
  <?php if ($pausedCount > 0): ?><span class="stat" style="background:rgba(220,38,38,.15);color:#fca5a5">已暂停 <?php echo $pausedCount; ?></span><?php endif; ?>
  <?php if ($violCount > 0): ?><span class="stat" style="background:rgba(217,119,6,.18);color:#fcd34d">违规 <?php echo $violCount; ?></span><?php endif; ?>
  <?php if ($badCount > 0): ?><span class="stat" style="background:rgba(220,38,38,.15);color:#fca5a5">异常 <?php echo $badCount; ?></span><?php endif; ?>
  <?php if (!empty($btPanels)): ?><a class="btn" href="?refresh=1">刷新全部域名</a><?php endif; ?>
</div>
<div class="wrap">
  <?php if ($error !== ''): ?>
    <?php if (empty($btPanels)): ?>
      <div class="notice"><?php echo h($error); ?> <a href="index.php?page=config">前往配置</a></div>
    <?php else: ?>
      <div class="err" style="background:#fef2f2;border:1px solid #fecaca;padding:12px 16px;border-radius:10px;margin-bottom:14px"><?php echo h($error); ?></div>
    <?php endif; ?>
  <?php endif; ?>
  <?php if (!empty($btPanels)): ?>
  <div class="toolbar">
    <input type="text" id="kw" placeholder="搜索面板 / 站点 / 已绑定域名…" oninput="filterRows()">
    <label><input type="checkbox" id="onlyEmpty" onchange="filterRows()"> 仅显示未绑定域名的站点</label>
    <label><input type="checkbox" id="onlyErr" onchange="filterRows()"> 仅显示异常</label>
    <label><input type="checkbox" id="onlyViol" onchange="filterRows()"> 仅显示违规</label>
    <label><input type="checkbox" id="onlyPaused" onchange="filterRows()"> 仅显示已暂停</label>
  </div>
  <table>
    <thead>
      <tr><th>面板</th><th>站点</th><th>到期时间</th><th>已绑定域名（点击访问）</th><th>数量</th><th>状态</th><th>操作</th></tr>
    </thead>
    <tbody id="tbody">
    <?php foreach ($rows as $r):
        $vk = ($r['panel_id'] ?? '') . '|' . $r['site_name'];
        $isViol = isset($viol[$vk]);
        $reason = $isViol ? $viol[$vk]['reason'] : '';
        $status = $r['status'] ?? '1';
        $paused = $status === '0';
    ?>
      <tr data-panel="<?php echo h($r['panel_name']); ?>" data-panel-id="<?php echo h($r['panel_id']); ?>" data-site-id="<?php echo h($r['site_id']); ?>" data-site="<?php echo h($r['site_name']); ?>" data-domains="<?php echo h(implode(' ', $r['domains'])); ?>" data-err="<?php echo $r['apiErr'] !== '' ? '1' : '0'; ?>" data-paused="<?php echo $paused ? '1' : '0'; ?>" data-violated="<?php echo $isViol ? '1' : '0'; ?>" data-reason="<?php echo h($reason); ?>">
        <td class="panel-name"><?php echo h($r['panel_name']); ?></td>
        <td class="site-name"><?php echo h($r['site_name']); ?></td>
        <td class="exp"><?php echo h($r['expire']); ?></td>
        <td class="dom">
          <?php if ($r['apiErr'] !== ''): ?>
            <span class="err">⚠ <?php echo h($r['apiErr']); ?></span>
          <?php elseif (count($r['domains']) === 0): ?>
            <span class="none">（无绑定域名）</span>
          <?php else: ?>
            <?php foreach ($r['domains'] as $d):
              $href = (strpos($d, '://') === false) ? 'http://' . $d : $d; ?>
              <a href="<?php echo h($href); ?>" target="_blank" rel="noopener"><?php echo h($d); ?></a>
            <?php endforeach; ?>
          <?php endif; ?>
        </td>
        <td class="exp"><?php echo count($r['domains']); ?></td>
        <td class="status-cell"><?php echo dcStatusHtml($status, $isViol, $reason); ?></td>
        <td class="op-cell"><?php echo dcOpHtml($r['panel_id'], $r['site_id'], $r['site_name'], $status, $isViol); ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($rows)): ?>
      <tr><td colspan="7" class="muted" style="text-align:center;padding:40px">暂无数据，请点击右上角"刷新全部域名"首次拉取。</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
  <div class="foot">数据缓存于 <?php echo $cachedAt ? date('Y-m-d H:i:s', $cachedAt) : '—'; ?> · 常规查看读取缓存（秒开），点击"刷新"才重新逐个面板拉取 · "暂停/恢复"会真实调用宝塔接口</div>
  <?php endif; ?>
</div>
<script>
function escAttr(s){ return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function statusHtml(paused, violated, reason){
  var h='';
  if(paused) h+='<span class="badge" style="background:#fee2e2;color:#b91c1c">已暂停</span>';
  if(violated) h+='<span class="badge" title="'+escAttr(reason)+'" style="background:#fef3c7;color:#92400e">违规</span>';
  return h==='' ? '<span class="muted">正常</span>' : h;
}
function opHtml(panelId, siteId, siteName, status, violated){
  var paused = status==='0';
  var pid=escAttr(panelId), sid=escAttr(siteId), sn=escAttr(siteName), h='';
  if(paused) h+='<button class="btn-act btn-green" data-act="resume" data-panel="'+pid+'" data-site="'+sid+'" data-name="'+sn+'">恢复</button> ';
  else h+='<button class="btn-act btn-red" data-act="pause" data-panel="'+pid+'" data-site="'+sid+'" data-name="'+sn+'">暂停</button> ';
  if(violated) h+='<button class="btn-act btn-gray" data-act="unmark" data-panel="'+pid+'" data-name="'+sn+'">取消违规</button>';
  else h+='<button class="btn-act btn-yellow" data-act="mark" data-panel="'+pid+'" data-name="'+sn+'">标记违规</button>';
  return h;
}
function refreshRow(tr){
  var paused = tr.dataset.paused==='1', violated = tr.dataset.violated==='1';
  tr.querySelector('.status-cell').innerHTML = statusHtml(paused, violated, tr.dataset.reason||'');
  var btns = tr.querySelector('.op-cell');
  // op-cell 中的 data 属性从原始 PHP 渲染的按钮获取（panel_id / site_id 不在 dataset 中）
  // 简化：直接重新渲染按钮
  var panelId = tr.getAttribute('data-panel-id') || '';
  var siteId = tr.getAttribute('data-site-id') || '';
  var siteName = tr.getAttribute('data-site-name') || tr.dataset.site || '';
  btns.innerHTML = opHtml(panelId, siteId, siteName, paused?'0':'1', violated);
}
function filterRows(){
  var kw=document.getElementById('kw').value.trim().toLowerCase();
  var onlyEmpty=document.getElementById('onlyEmpty').checked;
  var onlyErr=document.getElementById('onlyErr').checked;
  var onlyViol=document.getElementById('onlyViol').checked;
  var onlyPaused=document.getElementById('onlyPaused').checked;
  var trs=document.querySelectorAll('#tbody tr');
  trs.forEach(function(tr){
    if(!tr.dataset.site) return;
    var hit=true;
    if(kw){ var hay=(tr.dataset.panel+' '+tr.dataset.site+' '+tr.dataset.domains).toLowerCase(); hit=hay.indexOf(kw)>-1; }
    if(hit&&onlyEmpty){ hit=tr.dataset.domains.trim()===''; }
    if(hit&&onlyErr){ hit=tr.dataset.err==='1'; }
    if(hit&&onlyViol){ hit=tr.dataset.violated==='1'; }
    if(hit&&onlyPaused){ hit=tr.dataset.paused==='1'; }
    tr.style.display=hit?'':'none';
  });
}
function postAct(act, panel, site, name, btn, reason){
  btn.disabled=true;
  var url='?act='+act+'&panel_id='+encodeURIComponent(panel)+'&site_id='+encodeURIComponent(site)+'&site_name='+encodeURIComponent(name)+(reason!==undefined?'&reason='+encodeURIComponent(reason):'');
  fetch(url,{method:'GET',credentials:'same-origin'})
    .then(function(r){return r.json();})
    .then(function(j){
      btn.disabled=false;
      if(j.ok){
        var tr=btn.closest('tr');
        if(act==='pause'){ tr.dataset.paused='1'; refreshRow(tr); }
        else if(act==='resume'){ tr.dataset.paused='0'; refreshRow(tr); }
        else if(act==='mark'){ tr.dataset.violated='1'; tr.dataset.reason=reason||''; refreshRow(tr); }
        else if(act==='unmark'){ tr.dataset.violated='0'; tr.dataset.reason=''; refreshRow(tr); }
        alert(j.msg);
      } else { alert('操作失败：'+(j.msg||'未知错误')); }
    })
    .catch(function(err){ btn.disabled=false; alert('请求出错：'+err); });
}
document.getElementById('tbody').addEventListener('click', function(e){
  var btn=e.target.closest('.btn-act');
  if(!btn) return;
  var act=btn.dataset.act, panel=btn.dataset.panel, site=btn.dataset.site||'', name=btn.dataset.name||'';
  if(act==='pause'){ if(confirm('确认暂停该站点？网站将停止访问。')) postAct('pause',panel,site,name,btn); }
  else if(act==='resume'){ if(confirm('确认恢复该站点？')) postAct('resume',panel,site,name,btn); }
  else if(act==='mark'){ var r=prompt('标记违规原因（可选）：',''); if(r!==null) postAct('mark',panel,'',name,btn,r); }
  else if(act==='unmark'){ postAct('unmark',panel,'',name,btn); }
});
</script>
</body>
</html>
