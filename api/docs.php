<?php
define('IN_SYS', true);
define('ROOT', __DIR__ . '/../');
require ROOT . 'rd/bootstrap.php';

$siteUrl = rtrim(siteUrl(), '/');
$apiBase = $siteUrl . '/api/index.php';
$apiKey = $_GET['api_key'] ?? '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>API 文档 - StarIDC</title>
<style>
:root{--bg:#f0f2f5;--card:#fff;--accent:#6366f1;--text:#1e293b;--muted:#64748b;--border:#e2e8f0;--success:#10b981;--danger:#ef4444;--warning:#f59e0b;--code:#f1f5f9;--radius:12px}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;background:var(--bg);color:var(--text);line-height:1.6;min-height:100vh}
.container{max-width:1100px;margin:0 auto;padding:24px}
.header{text-align:center;padding:48px 24px 32px}
.header h1{font-size:2rem;font-weight:800;background:linear-gradient(135deg,var(--accent),#a78bfa);-webkit-background-clip:text;-webkit-text-fill-color:transparent;margin-bottom:8px}
.header p{color:var(--muted);font-size:1.05rem}
.tabs{display:flex;gap:4px;margin-bottom:24px;background:var(--card);border-radius:var(--radius);padding:4px;box-shadow:0 1px 3px rgba(0,0,0,.06)}
.tab{padding:10px 24px;border:none;background:none;cursor:pointer;border-radius:10px;font-size:.92rem;font-weight:500;color:var(--muted);transition:all .2s}
.tab.active{background:var(--accent);color:#fff}
.card{background:var(--card);border-radius:var(--radius);padding:24px;margin-bottom:20px;box-shadow:0 1px 3px rgba(0,0,0,.06)}
.card h2{font-size:1.25rem;margin-bottom:16px;display:flex;align-items:center;gap:8px}
.card h3{font-size:1.05rem;margin:20px 0 12px;color:var(--accent)}
.endpoint{display:flex;align-items:flex-start;gap:12px;padding:14px 16px;margin-bottom:8px;background:var(--code);border-radius:10px;transition:all .2s;cursor:pointer}
.endpoint:hover{background:#e2e8f0}
.endpoint .method{font-size:.75rem;font-weight:700;padding:3px 10px;border-radius:6px;color:#fff;min-width:56px;text-align:center;flex-shrink:0}
.method.get{background:var(--success)}
.method.post{background:var(--accent)}
.endpoint .info{flex:1}
.endpoint .path{font-family:"SF Mono",Consolas,monospace;font-size:.9rem;font-weight:600;word-break:break-all}
.endpoint .desc{font-size:.82rem;color:var(--muted);margin-top:2px}
.endpoint .params{font-size:.78rem;color:var(--warning);margin-top:2px}
pre{background:var(--code);border-radius:8px;padding:16px;overflow-x:auto;font-size:.85rem;line-height:1.5;font-family:"SF Mono",Consolas,monospace;white-space:pre-wrap;word-break:break-all}
/* Debugger */
.debug-panel{display:none}
.debug-panel.active{display:block}
.form-group{margin-bottom:14px}
.form-group label{display:block;font-size:.85rem;font-weight:600;margin-bottom:4px;color:var(--text)}
.form-group input,.form-group select,.form-group textarea{width:100%;padding:10px 14px;border:2px solid var(--border);border-radius:8px;font-size:.9rem;font-family:inherit;transition:border .2s;outline:none}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{border-color:var(--accent)}
.form-group textarea{resize:vertical;min-height:80px}
.form-row{display:flex;gap:12px}
.form-row .form-group{flex:1}
.btn{display:inline-flex;align-items:center;gap:6px;padding:10px 24px;border:none;border-radius:8px;font-size:.9rem;font-weight:600;cursor:pointer;transition:all .2s}
.btn-primary{background:var(--accent);color:#fff}
.btn-primary:hover{opacity:.9}
.btn-secondary{background:var(--border);color:var(--text)}
.btn-secondary:hover{background:#cbd5e1}
.btn-sm{padding:6px 14px;font-size:.82rem}
.response-box{margin-top:16px}
.response-box .meta{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px}
.response-box .status{margin:0;font-size:.85rem;font-weight:600}
.response-box .status.ok{color:var(--success)}
.response-box .status.err{color:var(--danger)}
.response-box .time{font-size:.78rem;color:var(--muted)}
.response-box pre{max-height:400px;overflow-y:auto}
.endpoint-active{outline:2px solid var(--accent);background:#eef2ff}
.notice{background:#fef3c7;border:1px solid #fcd34d;border-radius:8px;padding:12px 16px;font-size:.85rem;color:#92400e;margin-bottom:16px}
.notice a{color:var(--accent);font-weight:600}
</style>
</head>
<body>
<div class="container">
<div class="header">
    <h1>StarIDC API</h1>
    <p>RESTful API 接口文档与在线调试</p>
</div>

<div class="notice">
    使用前请先在 <a href="../personalpanel.php?tab=api">个人中心 → API密钥</a> 创建 API Key，然后在此页面填入 Key 进行调试。
</div>

<div class="tabs">
    <button class="tab active" onclick="switchTab('docs')">文档</button>
    <button class="tab" onclick="switchTab('debug')">在线调试</button>
</div>

<!-- 文档面板 -->
<div id="tab-docs" class="debug-panel active">
    <div class="card">
        <h2>认证方式</h2>
        <p style="color:var(--muted);margin-bottom:12px">所有 API 请求需要携带 API Key，支持以下三种方式：</p>
        <pre>1. HTTP Header:  X-API-Key: sk-xxxxxxxxxxxx
2. Query 参数:   ?api_key=sk-xxxxxxxxxxxx
3. POST 参数:   api_key=sk-xxxxxxxxxxxx</pre>
    </div>

    <div class="card">
        <h2>响应格式</h2>
        <pre>{
  "code": 200,
  "message": "ok",
  "data": { ... }
}</pre>
    </div>

    <div class="card">
        <h2>接口列表</h2>
        <h3>用户</h3>
        <div class="endpoint" onclick="tryEndpoint('user_info','GET',{})">
            <span class="method get">GET</span>
            <div class="info"><div class="path">/api/index.php?action=user_info</div><div class="desc">获取用户信息</div></div>
        </div>
        <div class="endpoint" onclick="tryEndpoint('points','GET',{})">
            <span class="method get">GET</span>
            <div class="info"><div class="path">/api/index.php?action=points</div><div class="desc">获取积分余额</div></div>
        </div>
        <div class="endpoint" onclick="tryEndpoint('sign','POST',{})">
            <span class="method post">POST</span>
            <div class="info"><div class="path">/api/index.php?action=sign</div><div class="desc">每日签到</div></div>
        </div>

        <h3>主机</h3>
        <div class="endpoint" onclick="tryEndpoint('model_list','GET',{})">
            <span class="method get">GET</span>
            <div class="info"><div class="path">/api/index.php?action=model_list</div><div class="desc">获取可购买的主机型号列表（含时长折扣）</div></div>
        </div>
        <div class="endpoint" onclick="tryEndpoint('host_list','GET',{})">
            <span class="method get">GET</span>
            <div class="info"><div class="path">/api/index.php?action=host_list</div><div class="desc">获取我的主机列表</div></div>
        </div>
        <div class="endpoint" onclick="tryEndpoint('host_detail','GET',{host_id:''})">
            <span class="method get">GET</span>
            <div class="info"><div class="path">/api/index.php?action=host_detail&host_id={id}</div><div class="desc">获取主机详情</div><div class="params">参数: host_id (必填)</div></div>
        </div>
        <div class="endpoint" onclick="tryEndpoint('host_buy','POST',{model_id:'',duration_type:'month',elastic_values:'',coupon_code:''})">
            <span class="method post">POST</span>
            <div class="info"><div class="path">/api/index.php?action=host_buy</div><div class="desc">购买主机</div><div class="params">参数: model_id (必填), duration_type (默认month), elastic_values (JSON,选填), coupon_code (选填)</div></div>
        </div>
        <div class="endpoint" onclick="tryEndpoint('host_renew','POST',{host_id:'',duration_type:'month',coupon_code:''})">
            <span class="method post">POST</span>
            <div class="info"><div class="path">/api/index.php?action=host_renew</div><div class="desc">续费主机</div><div class="params">参数: host_id (必填), duration_type (默认month), coupon_code (选填)</div></div>
        </div>

        <h3>工单</h3>
        <div class="endpoint" onclick="tryEndpoint('ticket_list','GET',{})">
            <span class="method get">GET</span>
            <div class="info"><div class="path">/api/index.php?action=ticket_list</div><div class="desc">获取工单列表</div></div>
        </div>
        <div class="endpoint" onclick="tryEndpoint('ticket_detail','GET',{ticket_id:''})">
            <span class="method get">GET</span>
            <div class="info"><div class="path">/api/index.php?action=ticket_detail&ticket_id={id}</div><div class="desc">获取工单详情（含回复）</div><div class="params">参数: ticket_id (必填)</div></div>
        </div>
        <div class="endpoint" onclick="tryEndpoint('ticket_create','POST',{subject:'',content:'',host_id:''})">
            <span class="method post">POST</span>
            <div class="info"><div class="path">/api/index.php?action=ticket_create</div><div class="desc">创建工单</div><div class="params">参数: subject (必填), content (必填), host_id (选填)</div></div>
        </div>
        <div class="endpoint" onclick="tryEndpoint('ticket_reply','POST',{ticket_id:'',content:''})">
            <span class="method post">POST</span>
            <div class="info"><div class="path">/api/index.php?action=ticket_reply</div><div class="desc">回复工单</div><div class="params">参数: ticket_id (必填), content (必填)</div></div>
        </div>
        <div class="endpoint" onclick="tryEndpoint('ticket_close','POST',{ticket_id:''})">
            <span class="method post">POST</span>
            <div class="info"><div class="path">/api/index.php?action=ticket_close</div><div class="desc">关闭工单</div><div class="params">参数: ticket_id (必填)</div></div>
        </div>

        <h3>推荐</h3>
        <div class="endpoint" onclick="tryEndpoint('referral_info','GET',{})">
            <span class="method get">GET</span>
            <div class="info"><div class="path">/api/index.php?action=referral_info</div><div class="desc">获取推荐信息</div></div>
        </div>
    </div>

    <div class="card">
        <h2>代码示例</h2>
        <h3>cURL</h3>
        <pre>curl -H "X-API-Key: sk-xxxxxxxxxxxx" \
  "<?php echo h($apiBase); ?>?action=user_info"</pre>
        <h3>JavaScript (fetch)</h3>
        <pre>fetch('<?php echo h($apiBase); ?>?action=host_list', {
  headers: { 'X-API-Key': 'sk-xxxxxxxxxxxx' }
})
.then(r => r.json())
.then(d => console.log(d));</pre>
        <h3>Python</h3>
        <pre>import requests
r = requests.get('<?php echo h($apiBase); ?>',
    params={'action': 'points'},
    headers={'X-API-Key': 'sk-xxxxxxxxxxxx'})
print(r.json())</pre>
    </div>
</div>

<!-- 调试面板 -->
<div id="tab-debug" class="debug-panel">
    <div class="card">
        <h2>在线调试</h2>
        <div class="form-group">
            <label>API Key</label>
            <div class="form-row">
                <input type="text" id="debug-apikey" placeholder="sk-xxxxxxxxxxxx" value="<?php echo h($apiKey); ?>" style="flex:1">
                <button class="btn btn-secondary btn-sm" onclick="saveApiKey()" style="flex-shrink:0">保存</button>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>请求方式</label>
                <select id="debug-method"><option value="GET">GET</option><option value="POST">POST</option></select>
            </div>
            <div class="form-group">
                <label>接口</label>
                <select id="debug-action" onchange="onActionChange()">
                    <option value="user_info">user_info - 用户信息</option>
                    <option value="points">points - 积分余额</option>
                    <option value="sign">sign - 每日签到</option>
                    <option value="model_list">model_list - 主机型号列表</option>
                    <option value="host_list">host_list - 主机列表</option>
                    <option value="host_detail">host_detail - 主机详情</option>
                    <option value="host_buy">host_buy - 购买主机</option>
                    <option value="host_renew">host_renew - 续费主机</option>
                    <option value="ticket_list">ticket_list - 工单列表</option>
                    <option value="ticket_detail">ticket_detail - 工单详情</option>
                    <option value="ticket_create">ticket_create - 创建工单</option>
                    <option value="ticket_reply">ticket_reply - 回复工单</option>
                    <option value="ticket_close">ticket_close - 关闭工单</option>
                    <option value="referral_info">referral_info - 推荐信息</option>
                </select>
            </div>
        </div>
        <div id="debug-params"></div>
        <button class="btn btn-primary" onclick="sendDebug()" id="debug-send" style="margin-top:8px">发送请求</button>
        <div class="response-box" id="debug-response" style="display:none">
            <div class="meta">
                <span class="status" id="debug-status"></span>
                <span class="time" id="debug-time"></span>
            </div>
            <pre id="debug-result"></pre>
        </div>
    </div>
</div>
</div>

<script>
var paramDefs = {
    user_info: {method:'GET',params:{}},
    points: {method:'GET',params:{}},
    sign: {method:'POST',params:{}},
    model_list: {method:'GET',params:{}},
    host_list: {method:'GET',params:{}},
    host_detail: {method:'GET',params:{host_id:{label:'主机ID',placeholder:'输入主机ID'}}},
    host_buy: {method:'POST',params:{model_id:{label:'型号ID',placeholder:'输入型号ID'},duration_type:{label:'时长类型',placeholder:'month/quarter/half_year/year/2year/3year/5year/10year'},elastic_values:{label:'弹性配置(JSON)',placeholder:'{"web_space":512}',textarea:true},coupon_code:{label:'优惠码',placeholder:'选填'}}},
    host_renew: {method:'POST',params:{host_id:{label:'主机ID',placeholder:'输入主机ID'},duration_type:{label:'时长类型',placeholder:'month/quarter/half_year/year/2year/3year/5year/10year'},coupon_code:{label:'优惠码',placeholder:'选填'}}},
    ticket_list: {method:'GET',params:{}},
    ticket_detail: {method:'GET',params:{ticket_id:{label:'工单ID',placeholder:'输入工单ID'}}},
    ticket_create: {method:'POST',params:{subject:{label:'标题',placeholder:'简要描述问题'},content:{label:'内容',placeholder:'详细描述您遇到的问题',textarea:true},host_id:{label:'关联主机ID',placeholder:'选填'}}},
    ticket_reply: {method:'POST',params:{ticket_id:{label:'工单ID',placeholder:'输入工单ID'},content:{label:'回复内容',placeholder:'输入回复内容',textarea:true}}},
    ticket_close: {method:'POST',params:{ticket_id:{label:'工单ID',placeholder:'输入工单ID'}}},
    referral_info: {method:'GET',params:{}},
};

function switchTab(name) {
    document.querySelectorAll('.tab').forEach(function(t){t.classList.remove('active')});
    document.querySelectorAll('.debug-panel').forEach(function(p){p.classList.remove('active')});
    document.querySelector('.tab:nth-child('+(name==='docs'?1:2)+')').classList.add('active');
    document.getElementById('tab-'+name).classList.add('active');
}

function tryEndpoint(action, method, params) {
    document.getElementById('debug-action').value = action;
    document.getElementById('debug-method').value = method;
    renderParams(action);
    switchTab('debug');
    // 滚动到调试面板
    setTimeout(function() {
        document.getElementById('tab-debug').scrollIntoView({behavior:'smooth'});
        // 高亮文档中的端点
        document.querySelectorAll('.endpoint').forEach(function(e){e.classList.remove('endpoint-active')});
    }, 100);
}

function onActionChange() {
    var action = document.getElementById('debug-action').value;
    var def = paramDefs[action];
    if (def) {
        document.getElementById('debug-method').value = def.method;
        renderParams(action);
    }
}

function renderParams(action) {
    var def = paramDefs[action];
    var container = document.getElementById('debug-params');
    if (!def || Object.keys(def.params).length === 0) {
        container.innerHTML = '<p style="color:var(--muted);font-size:.85rem;margin-top:8px">无需额外参数</p>';
        return;
    }
    var html = '';
    for (var key in def.params) {
        var p = def.params[key];
        if (p.textarea) {
            html += '<div class="form-group"><label>'+p.label+' ('+key+')</label><textarea id="param-'+key+'" placeholder="'+p.placeholder+'"></textarea></div>';
        } else {
            html += '<div class="form-group"><label>'+p.label+' ('+key+')</label><input type="text" id="param-'+key+'" placeholder="'+p.placeholder+'"></div>';
        }
    }
    container.innerHTML = html;
}

function saveApiKey() {
    var key = document.getElementById('debug-apikey').value.trim();
    localStorage.setItem('staridc_api_key', key);
    alert('API Key 已保存到本地');
}

function sendDebug() {
    var apiKey = document.getElementById('debug-apikey').value.trim();
    var action = document.getElementById('debug-action').value;
    var method = document.getElementById('debug-method').value;
    var startTime = Date.now();
    var respBox = document.getElementById('debug-response');
    var statusEl = document.getElementById('debug-status');
    var timeEl = document.getElementById('debug-time');
    var resultEl = document.getElementById('debug-result');
    var sendBtn = document.getElementById('debug-send');

    if (!apiKey) { alert('请先填写 API Key'); return; }

    // 收集参数
    var params = {};
    var def = paramDefs[action];
    if (def) {
        for (var key in def.params) {
            var el = document.getElementById('param-'+key);
            if (el) params[key] = el.value.trim();
        }
    }

    sendBtn.disabled = true;
    sendBtn.textContent = '请求中...';
    respBox.style.display = 'block';
    resultEl.textContent = 'Loading...';

    var url = '../api/index.php?action=' + action + '&api_key=' + encodeURIComponent(apiKey);
    var options = {method: method, headers: {'Content-Type': 'application/x-www-form-urlencoded'}};

    if (method === 'GET') {
        for (var k in params) {
            if (params[k]) url += '&' + k + '=' + encodeURIComponent(params[k]);
        }
    } else {
        var body = [];
        for (var k in params) {
            if (params[k]) body.push(k + '=' + encodeURIComponent(params[k]));
        }
        options.body = body.join('&');
    }

    fetch(url, options)
        .then(function(r) {
            var elapsed = Date.now() - startTime;
            timeEl.textContent = elapsed + 'ms';
            if (r.ok) {
                statusEl.textContent = 'HTTP ' + r.status + ' OK';
                statusEl.className = 'status ok';
            } else {
                statusEl.textContent = 'HTTP ' + r.status;
                statusEl.className = 'status err';
            }
            return r.text();
        })
        .then(function(text) {
            try {
                var obj = JSON.parse(text);
                resultEl.textContent = JSON.stringify(obj, null, 2);
            } catch(e) {
                resultEl.textContent = text;
            }
            sendBtn.disabled = false;
            sendBtn.textContent = '发送请求';
        })
        .catch(function(err) {
            statusEl.textContent = 'Error';
            statusEl.className = 'status err';
            timeEl.textContent = (Date.now() - startTime) + 'ms';
            resultEl.textContent = err.message;
            sendBtn.disabled = false;
            sendBtn.textContent = '发送请求';
        });
}

// 初始化
(function(){
    var saved = localStorage.getItem('staridc_api_key');
    if (saved && !document.getElementById('debug-apikey').value) {
        document.getElementById('debug-apikey').value = saved;
    }
    renderParams('user_info');
})();
</script>
</body>
</html>