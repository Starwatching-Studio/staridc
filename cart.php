<?php
define('IN_SYS', true);
define('ROOT', __DIR__ . '/');
include ROOT . 'rd/bootstrap.php';
include ROOT . 'rd/MNBT_API.php';

$user = getUser();
$loggedIn = isLogin();
$error = '';
$success = '';

// ========== 时长月数映射 ==========
$durationMonthsMap = [
    'month' => 1, 'quarter' => 3, 'half_year' => 6,
    'year' => 12, '2year' => 24, '3year' => 36,
    '5year' => 60, '10year' => 120
];

// ========== AJAX 处理 ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $ajaxAction = $_POST['action'];

    // ---- 公开接口（无需登录） ----
    if ($ajaxAction === 'get_subcategories') {
        header('Content-Type: application/json; charset=utf-8');
        $parentId = $_POST['parent_id'] ?? '';
        if ($parentId === '') {
            $stmt = $DB->prepare("SELECT id, name, parent_id, sort_order FROM vhost_categories WHERE parent_id IS NULL ORDER BY sort_order, id");
            $stmt->execute();
        } else {
            $stmt = $DB->prepare("SELECT id, name, parent_id, sort_order FROM vhost_categories WHERE parent_id=? ORDER BY sort_order, id");
            $stmt->execute([intval($parentId)]);
        }
        echo json_encode(['cats' => $stmt->fetchAll()], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($ajaxAction === 'get_models') {
        header('Content-Type: application/json; charset=utf-8');
        $categoryId = intval($_POST['category_id'] ?? 0);
        // 加载当前分类及其所有后代分类下的型号
        $catIds = [$categoryId];
        try {
            $catRows = $DB->query("SELECT id, parent_id FROM vhost_categories")->fetchAll();
            $catChildren = [];
            foreach ($catRows as $cr) {
                if ($cr['parent_id']) {
                    $catChildren[$cr['parent_id']][] = $cr['id'];
                }
            }
            $queue = [$categoryId];
            while (!empty($queue)) {
                $pid = array_shift($queue);
                if (isset($catChildren[$pid])) {
                    foreach ($catChildren[$pid] as $cid) {
                        $catIds[] = $cid;
                        $queue[] = $cid;
                    }
                }
            }
        } catch (Exception $e) {}
        $inClause = implode(',', array_fill(0, count($catIds), '?'));
        $stmt = $DB->prepare("SELECT id, name, price, web_space, db_space, flow, domain_limit, is_elastic FROM vhost_models WHERE status=1 AND category_id IN ({$inClause}) ORDER BY sort_order, id");
        $stmt->execute($catIds);
        $models = $stmt->fetchAll();
        // 为弹性型号附加 elastic 配置数据（供产品卡片显示范围）
        foreach ($models as &$m) {
            if ($m['is_elastic'] == 1) {
                try {
                    $eStmt = $DB->prepare("SELECT field_name, min_value, max_value FROM vhost_model_elastic WHERE model_id=? AND enabled=1");
                    $eStmt->execute([$m['id']]);
                    $m['elastic'] = $eStmt->fetchAll();
                } catch (Exception $e) {
                    $m['elastic'] = [];
                }
            } else {
                $m['elastic'] = [];
            }
        }
        unset($m);
        echo json_encode(['models' => $models], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ---- 需登录接口 ----
    if (!$loggedIn) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => L('buy_please_login')], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($ajaxAction === 'get_model_data') {
        header('Content-Type: application/json; charset=utf-8');
        $modelId = intval($_POST['model_id'] ?? 0);
        $stmt = $DB->prepare("SELECT id, name, price, web_space, db_space, flow, domain_limit, is_elastic FROM vhost_models WHERE id=? AND status=1");
        $stmt->execute([$modelId]);
        $model = $stmt->fetch();
        if (!$model) {
            echo json_encode(['error' => '主机型号不存在'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $stmt = $DB->prepare("SELECT duration_type, discount FROM vhost_model_durations WHERE model_id=? AND enabled=1 ORDER BY FIELD(duration_type, 'month','quarter','half_year','year','2year','3year','5year','10year')");
        $stmt->execute([$modelId]);
        $durations = $stmt->fetchAll();
        try {
            $stmt = $DB->prepare("SELECT field_name, min_value, max_value, step, unit_price FROM vhost_model_elastic WHERE model_id=? AND enabled=1");
            $stmt->execute([$modelId]);
            $elastic = $stmt->fetchAll();
        } catch (Exception $e) {
            $elastic = [];
        }
        echo json_encode([
            'model' => $model,
            'durations' => $durations,
            'elastic' => $elastic
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($ajaxAction === 'check_coupon') {
        header('Content-Type: application/json; charset=utf-8');
        $code = trim($_POST['code'] ?? '');
        $modelId = intval($_POST['model_id'] ?? 0);
        $durationType = trim($_POST['duration_type'] ?? 'month');
        $elasticValuesJson = $_POST['elastic_values'] ?? '{}';

        $stmt = $DB->prepare("SELECT * FROM vhost_models WHERE id=? AND status=1");
        $stmt->execute([$modelId]);
        $model = $stmt->fetch();
        if (!$model) {
            echo json_encode(['valid' => false, 'message' => '主机型号不存在'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 计算时长价格
        $months = $durationMonthsMap[$durationType] ?? 1;
        $stmt = $DB->prepare("SELECT discount FROM vhost_model_durations WHERE model_id=? AND duration_type=? AND enabled=1");
        $stmt->execute([$modelId, $durationType]);
        $durRow = $stmt->fetch();
        $durationDiscount = $durRow ? intval($durRow['discount']) : 0;
        $durationPrice = intval(ceil($model['price'] * $months * (100 - $durationDiscount) / 100));

        // 计算弹性加价
        $elasticSurcharge = 0;
        if ($model['is_elastic']) {
            $elasticValues = json_decode($elasticValuesJson, true) ?: [];
            try {
                $stmt = $DB->prepare("SELECT field_name, min_value, max_value, step, unit_price FROM vhost_model_elastic WHERE model_id=? AND enabled=1");
                $stmt->execute([$modelId]);
                $elasticConfigs = $stmt->fetchAll();
            } catch (Exception $e) {
                $elasticConfigs = [];
            }
            foreach ($elasticConfigs as $ec) {
                $fn = $ec['field_name'];
                $baseVal = intval($model[$fn] ?? 0);
                $submittedVal = isset($elasticValues[$fn]) ? intval($elasticValues[$fn]) : $baseVal;
                $submittedVal = max(intval($ec['min_value']), min(intval($ec['max_value']), $submittedVal));
                if ($submittedVal > $baseVal && intval($ec['step']) > 0) {
                    $elasticSurcharge += intval(($submittedVal - $baseVal) / intval($ec['step']) * intval($ec['unit_price']));
                }
            }
        }

        $subtotal = $durationPrice + $elasticSurcharge;

        // 验证优惠码
        $stmt = $DB->prepare("SELECT * FROM coupons WHERE code=? AND status=0 AND (used_count < max_uses OR max_uses = 0) AND (expire_at IS NULL OR expire_at > NOW())");
        $stmt->execute([$code]);
        $cp = $stmt->fetch();
        if (!$cp) {
            echo json_encode(['valid' => false, 'message' => L('coupon_invalid')], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($cp['model_id'] !== null && intval($cp['model_id']) !== $modelId) {
            echo json_encode(['valid' => false, 'message' => '该优惠码不适用于此型号'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $finalPrice = intval(ceil($subtotal * (100 - $cp['discount']) / 100));
        echo json_encode([
            'valid' => true,
            'discount' => intval($cp['discount']),
            'subtotal' => $subtotal,
            'final_price' => $finalPrice
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ---- 购买提交 ----
    if ($ajaxAction === 'buy_host') {
        $modelId = intval($_POST['model_id'] ?? 0);
        $couponCode = trim($_POST['coupon_code'] ?? '');
        $durationType = trim($_POST['duration_type'] ?? 'month');
        $elasticValuesJson = $_POST['elastic_values'] ?? '{}';

        $stmt = $DB->prepare("SELECT * FROM vhost_models WHERE id=? AND status=1");
        $stmt->execute([$modelId]);
        $model = $stmt->fetch();
        if (!$model) {
            $error = '该主机型号不存在或已下架';
        } else {
            // 计算时长价格
            $months = $durationMonthsMap[$durationType] ?? 1;
            $stmt = $DB->prepare("SELECT discount FROM vhost_model_durations WHERE model_id=? AND duration_type=? AND enabled=1");
            $stmt->execute([$modelId, $durationType]);
            $durRow = $stmt->fetch();
            if (!$durRow) {
                $error = '无效的购买时长';
            }
            if (!$error) {
                $durationDiscount = intval($durRow['discount']);
                $durationPrice = intval(ceil($model['price'] * $months * (100 - $durationDiscount) / 100));

                // 计算弹性加价和实际资源值
                $elasticSurcharge = 0;
                $elasticValues = json_decode($elasticValuesJson, true) ?: [];
                $finalWebSpace = intval($model['web_space']);
                $finalDbSpace = intval($model['db_space']);
                $finalFlow = intval($model['flow']);
                $finalDomainLimit = intval($model['domain_limit']);

                if ($model['is_elastic']) {
                    try {
                        $stmt = $DB->prepare("SELECT field_name, min_value, max_value, step, unit_price FROM vhost_model_elastic WHERE model_id=? AND enabled=1");
                        $stmt->execute([$modelId]);
                        $elasticConfigs = $stmt->fetchAll();
                    } catch (Exception $e) {
                        $elasticConfigs = [];
                    }
                    foreach ($elasticConfigs as $ec) {
                        $fn = $ec['field_name'];
                        $baseVal = intval($model[$fn] ?? 0);
                        $submittedVal = isset($elasticValues[$fn]) ? intval($elasticValues[$fn]) : $baseVal;
                        $submittedVal = max(intval($ec['min_value']), min(intval($ec['max_value']), $submittedVal));
                        if ($submittedVal > $baseVal && intval($ec['step']) > 0) {
                            $elasticSurcharge += intval(($submittedVal - $baseVal) / intval($ec['step']) * intval($ec['unit_price']));
                        }
                        // 更新实际资源值
                        switch ($fn) {
                            case 'web_space': $finalWebSpace = $submittedVal; break;
                            case 'db_space': $finalDbSpace = $submittedVal; break;
                            case 'flow': $finalFlow = $submittedVal; break;
                            case 'domain_limit': $finalDomainLimit = $submittedVal; break;
                        }
                    }
                }

                $subtotal = $durationPrice + $elasticSurcharge;
                $finalPrice = $subtotal;
                $couponDiscount = 0;
                $couponId = 0;

                // 优惠码
                if ($couponCode) {
                    $stmt = $DB->prepare("SELECT * FROM coupons WHERE code=? AND status=0 AND (used_count < max_uses OR max_uses = 0) AND (expire_at IS NULL OR expire_at > NOW())");
                    $stmt->execute([$couponCode]);
                    $cp = $stmt->fetch();
                    if ($cp) {
                        if ($cp['model_id'] !== null && intval($cp['model_id']) !== $modelId) {
                            $error = '该优惠码不适用于此型号';
                        } else {
                            $couponDiscount = intval($cp['discount']);
                            $couponId = intval($cp['id']);
                            $finalPrice = intval(ceil($subtotal * (100 - $couponDiscount) / 100));
                        }
                    } else {
                        $error = L('coupon_invalid');
                    }
                }

                if (!$error && $user['points'] < $finalPrice) {
                    $error = L('buy_insufficient_points');
                }

                if (!$error) {
                    // 全局限购检查
                    $globalLimit = intval(conf('max_hosts_per_user', '5'));
                    $vhostCount = $DB->prepare("SELECT COUNT(*) as c FROM vhosts WHERE user_id=?");
                    $vhostCount->execute([$user['id']]);
                    $totalVhosts = $vhostCount->fetch()['c'];
                    if ($globalLimit > 0 && $totalVhosts >= $globalLimit) {
                        $error = '每人最多购买' . $globalLimit . '台虚拟主机';
                    }
                    // 型号限购检查
                    if (!$error) {
                        $maxPerUser = isset($model['max_per_user']) ? intval($model['max_per_user']) : 0;
                        if ($maxPerUser > 0) {
                            $modelCount = $DB->prepare("SELECT COUNT(*) as c FROM vhosts WHERE user_id=? AND model_id=?");
                            $modelCount->execute([$user['id'], $modelId]);
                            if ($modelCount->fetch()['c'] >= $maxPerUser) {
                                $error = '该型号每人最多购买' . $maxPerUser . '台';
                            }
                        }
                    }
                    if (!$error) {
                        $account = genVhostAccount($user['id'], $modelId);
                        $password = genVhostPassword();
                        $expireTime = date('Y-m-d', strtotime('+' . $months . ' months'));
                        $server = getServer($model['server_id']);
                        $mnbtResult = MNBT_API::openHost($account, $password, $finalWebSpace, $finalDbSpace, $finalFlow, $finalDomainLimit, $expireTime, $server);
                        if ($mnbtResult['success']) {
                            if ($couponId) {
                                $cpStmt = $DB->prepare("UPDATE coupons SET used_count=used_count+1, used_by=?, used_at=NOW() WHERE id=?");
                                $cpStmt->execute([$user['id'], $couponId]);
                                $cpCheck = $DB->prepare("SELECT max_uses, used_count FROM coupons WHERE id=?");
                                $cpCheck->execute([$couponId]);
                                $cpInfo = $cpCheck->fetch();
                                if ($cpInfo && $cpInfo['max_uses'] > 0 && $cpInfo['used_count'] >= $cpInfo['max_uses']) {
                                    $DB->prepare("UPDATE coupons SET status=1 WHERE id=?")->execute([$couponId]);
                                }
                            }
                            $stmt2 = $DB->prepare("UPDATE users SET points=points-? WHERE id=?");
                            $stmt2->execute([$finalPrice, $user['id']]);
                            $stmt3 = $DB->prepare("INSERT INTO vhosts(user_id,model_id,account,password,mnbt_opened,expire_time,server_id,web_space,db_space,flow,domain_limit) VALUES(?,?,?,?,1,?,?,?,?,?,?)");
                            $stmt3->execute([$user['id'], $modelId, $account, $password, $expireTime, $model['server_id'], $finalWebSpace, $finalDbSpace, $finalFlow, $finalDomainLimit]);
                            $discountInfo = $couponDiscount ? "（优惠码省了 " . ($subtotal - $finalPrice) . " 积分）" : "";
                            $success = L('buy_success') . '！' . L('panel_account') . '：' . h($account) . '，' . L('panel_expire_time') . '：' . $expireTime . $discountInfo;
                            $user = getUser();
                            if (conf('mail_notify_host') === '1') {
                                $notifySubject = '主机开通成功 - ' . conf('site_name', '云主机');
                                $notifyBody = "您的虚拟主机已成功开通！\n\n"
                                    . "型号：" . $model['name'] . "\n"
                                    . "账号：" . $account . "\n"
                                    . "密码：" . $password . "\n"
                                    . "到期时间：" . $expireTime . "\n\n"
                                    . "请及时登录管理面板查看。";
                                Mailer::sendNotify($user['email'], $notifySubject, $notifyBody);
                            }
                        } else {
                            $error = L('buy_mnbt_fail') . '：' . $mnbtResult['message'];
                        }
                    }
                }
            }
        }
    }
}

// ========== 购物车 AJAX ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $ajaxAction = $_POST['action'];

    if ($ajaxAction === 'add_to_cart') {
    header('Content-Type: application/json; charset=utf-8');
    if (!$user) { echo json_encode(['ok' => false, 'message' => L('buy_please_login')]); exit; }
    $modelId = intval($_POST['model_id'] ?? 0);
    $durationType = $_POST['duration_type'] ?? 'month';
    $elasticValues = $_POST['elastic_values'] ?? '';
    $couponCode = trim($_POST['coupon_code'] ?? '');
    $quantity = max(1, intval($_POST['quantity'] ?? 1));
    // 验证型号是否存在且启用
    $chkStmt = $DB->prepare("SELECT id FROM vhost_models WHERE id=? AND status=1");
    $chkStmt->execute([$modelId]);
    if (!$chkStmt->fetch()) {
        echo json_encode(['ok' => false, 'message' => '该主机型号不存在或已下架']);
        exit;
    }
    $stmt = $DB->prepare("INSERT INTO cart_items (user_id, model_id, duration_type, elastic_values, coupon_code, quantity) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$user['id'], $modelId, $durationType, $elasticValues, $couponCode, $quantity]);
    echo json_encode(['ok' => true, 'message' => '已加入购物车', 'cart_count' => getCartCount($user['id'])]);
    exit;
}
if ($ajaxAction === 'remove_from_cart') {
    header('Content-Type: application/json; charset=utf-8');
    if (!$user) { echo json_encode(['ok' => false, 'message' => L('buy_please_login')]); exit; }
    $itemId = intval($_POST['item_id'] ?? 0);
    $DB->prepare("DELETE FROM cart_items WHERE id=? AND user_id=?")->execute([$itemId, $user['id']]);
    echo json_encode(['ok' => true, 'cart_count' => getCartCount($user['id'])]);
    exit;
}
if ($ajaxAction === 'get_cart') {
    header('Content-Type: application/json; charset=utf-8');
    if (!$user) { echo json_encode(['items' => [], 'total' => 0, 'count' => 0]); exit; }
    $items = getCartItems($user['id']);
    echo json_encode($items, JSON_UNESCAPED_UNICODE);
    exit;
}
if ($ajaxAction === 'cart_checkout') {
    header('Content-Type: application/json; charset=utf-8');
    if (!$user) { echo json_encode(['ok' => false, 'message' => L('buy_please_login')]); exit; }
    $cartItems = getCartItems($user['id']);
    if (empty($cartItems['items'])) { echo json_encode(['ok' => false, 'message' => '购物车为空']); exit; }
    $totalPoints = $cartItems['total'];
    $results = [];
    $allSuccess = true;
    foreach ($cartItems['items'] as $item) {
        $modelId = intval($item['model_id']);
        $durationType = $item['duration_type'];
        $couponCode = $item['coupon_code'];
        $quantity = intval($item['quantity']);
        $elasticValues = json_decode($item['elastic_values'] ?? '{}', true) ?: [];
        $modelStmt = $DB->prepare("SELECT * FROM vhost_models WHERE id=? AND status=1");
        $modelStmt->execute([$modelId]);
        $model = $modelStmt->fetch();
        if (!$model) { $results[] = ['name' => $item['model_name'], 'ok' => false, 'message' => '型号不存在']; $allSuccess = false; continue; }
        $months = $durationMonthsMap[$durationType] ?? 1;
        $server = getServer($model['server_id']);
        for ($i = 0; $i < $quantity; $i++) {
            $account = genVhostAccount($user['id'], $modelId);
            $password = genVhostPassword();
            $finalWebSpace = $model['web_space']; $finalDbSpace = $model['db_space'];
            $finalFlow = $model['flow']; $finalDomainLimit = $model['domain_limit'];
            $elasticSurcharge = 0;
            if ($model['is_elastic']) {
                try {
                    $eStmt = $DB->prepare("SELECT * FROM vhost_model_elastic WHERE model_id=? AND enabled=1");
                    $eStmt->execute([$modelId]);
                    $elasticRows = $eStmt->fetchAll();
                } catch (Exception $e) {
                    $elasticRows = [];
                }
                foreach ($elasticRows as $ec) {
                    $fn = $ec['field_name'];
                    $val = isset($elasticValues[$fn]) ? intval($elasticValues[$fn]) : intval($model[$fn]);
                    if ($val > intval($model[$fn]) && intval($ec['step']) > 0) {
                        $elasticSurcharge += intval(($val - intval($model[$fn])) / intval($ec['step']) * intval($ec['unit_price']));
                    }
                    switch ($fn) {
                        case 'web_space': $finalWebSpace = $val; break;
                        case 'db_space': $finalDbSpace = $val; break;
                        case 'flow': $finalFlow = $val; break;
                        case 'domain_limit': $finalDomainLimit = $val; break;
                    }
                }
            }
            $durDiscount = 0;
            $dStmt = $DB->prepare("SELECT discount FROM vhost_model_durations WHERE model_id=? AND duration_type=? AND enabled=1");
            $dStmt->execute([$modelId, $durationType]);
            $durRow = $dStmt->fetch();
            if ($durRow) $durDiscount = intval($durRow['discount']);
            $basePrice = intval(ceil($model['price'] * $months * (100 - $durDiscount) / 100));
            $itemPrice = $basePrice + $elasticSurcharge;
            $couponId = null;
            if (!empty($couponCode)) {
                $cpStmt = $DB->prepare("SELECT * FROM coupons WHERE code=? AND status=0 AND (used_count < max_uses OR max_uses = 0) AND (expire_at IS NULL OR expire_at > NOW())");
                $cpStmt->execute([$couponCode]);
                $cp = $cpStmt->fetch();
                if ($cp && ($cp['model_id'] === null || intval($cp['model_id']) === $modelId)) {
                    $itemPrice = intval(ceil($itemPrice * (100 - $cp['discount']) / 100));
                    $couponId = $cp['id'];
                }
            }
            if ($user['points'] < $itemPrice) {
                $results[] = ['name' => $item['model_name'], 'ok' => false, 'message' => L('buy_insufficient_points')];
                $allSuccess = false; break 2;
            }
            $expireTime = date('Y-m-d', strtotime('+' . $months . ' months'));
            $mnbtResult = MNBT_API::openHost($account, $password, $finalWebSpace, $finalDbSpace, $finalFlow, $finalDomainLimit, $expireTime, $server);
            if (!$mnbtResult['success']) {
                $results[] = ['name' => $item['model_name'], 'ok' => false, 'message' => L('buy_mnbt_fail') . '：' . $mnbtResult['message']];
                $allSuccess = false; break 2;
            }
            $DB->prepare("UPDATE users SET points=points-? WHERE id=?")->execute([$itemPrice, $user['id']]);
            $DB->prepare("INSERT INTO vhosts(user_id,model_id,account,password,mnbt_opened,expire_time,server_id,web_space,db_space,flow,domain_limit) VALUES(?,?,?,?,1,?,?,?,?,?,?)")
                ->execute([$user['id'], $modelId, $account, $password, $expireTime, $model['server_id'], $finalWebSpace, $finalDbSpace, $finalFlow, $finalDomainLimit]);
            if ($couponId) $DB->prepare("UPDATE coupons SET used_count=used_count+1 WHERE id=?")->execute([$couponId]);
            $results[] = ['name' => $item['model_name'], 'ok' => true, 'message' => L('buy_success')];
            $user = getUser();
        }
    }
    if ($allSuccess) {
        $DB->prepare("DELETE FROM cart_items WHERE user_id=?")->execute([$user['id']]);
    }
    echo json_encode(['ok' => $allSuccess, 'results' => $results, 'cart_count' => getCartCount($user['id'])]);
    exit;
    }
}

// ========== 页面数据：分类树 ==========
$allCats = $DB->query("SELECT id, name, parent_id, sort_order FROM vhost_categories ORDER BY sort_order, id")->fetchAll();
$l1Cats = [];
$childrenMap = [];
foreach ($allCats as $c) {
    if ($c['parent_id'] === null) {
        $l1Cats[] = $c;
    } else {
        $childrenMap[$c['parent_id']][] = $c;
    }
}

$hasCategories = !empty($l1Cats);
$defaultL1Id = 0;
$defaultL2Id = 0;
$defaultL3Id = 0;

if ($hasCategories) {
    $defaultL1Id = intval($l1Cats[0]['id']);
    if (isset($childrenMap[$defaultL1Id]) && !empty($childrenMap[$defaultL1Id])) {
        $defaultL2Id = intval($childrenMap[$defaultL1Id][0]['id']);
        if (isset($childrenMap[$defaultL2Id]) && !empty($childrenMap[$defaultL2Id])) {
            $defaultL3Id = intval($childrenMap[$defaultL2Id][0]['id']);
        } else {
            // 二级分类下没有三级时，默认用二级分类
            $defaultL3Id = $defaultL2Id;
        }
    } else {
        // 一级分类下没有子分类时，默认用一级分类
        $defaultL3Id = $defaultL1Id;
    }
}

// 初始型号列表：查询默认分类及其所有后代分类下的型号
$models = [];
if ($hasCategories && $defaultL3Id) {
    $defaultCatIds = [$defaultL3Id];
    $queue = [$defaultL3Id];
    while (!empty($queue)) {
        $pid = array_shift($queue);
        if (isset($childrenMap[$pid])) {
            foreach ($childrenMap[$pid] as $c) {
                $defaultCatIds[] = $c['id'];
                $queue[] = $c['id'];
            }
        }
    }
    $inClause = implode(',', array_fill(0, count($defaultCatIds), '?'));
    $stmt = $DB->prepare("SELECT id, name, price, web_space, db_space, flow, domain_limit, is_elastic, category_id FROM vhost_models WHERE status=1 AND category_id IN ({$inClause}) ORDER BY sort_order, id");
    $stmt->execute($defaultCatIds);
    $models = $stmt->fetchAll();
} elseif (!$hasCategories) {
    $models = $DB->query("SELECT id, name, price, web_space, db_space, flow, domain_limit, is_elastic, category_id FROM vhost_models WHERE status=1 ORDER BY sort_order,id")->fetchAll();
}

renderHeader(L('buy_title'));
?>
<!-- ========== 三级分类筛选 ========== -->
<div class="page-header">
    <h1>🛒 <?php echo L('buy_title'); ?></h1>
    <p>选择适合您的主机方案，使用积分兑换</p>
</div>

<?php if ($error): ?><div class="msg msg-error"><?php echo h($error); ?></div><?php endif; ?>
<?php if ($success): ?><div class="msg msg-success"><?php echo h($success); ?></div><?php endif; ?>

<?php if (!$loggedIn): ?>
<div class="msg msg-warn">请先 <a href="login.php">登录</a> 后再购买主机</div>
<?php else: ?>
<div class="user-points-bar">
    <span>💰 当前积分：<strong><?php echo $user['points']; ?></strong></span>
    <a href="personalpanel.php" class="btn-sm">充值积分</a>
</div>
<?php endif; ?>

<?php if ($hasCategories): ?>
<div class="category-filter">
    <div class="cat-tabs" id="catTabsL1">
        <?php foreach ($l1Cats as $i => $c): ?>
        <button type="button" class="cat-tab<?php echo $i===0?' active':''; ?>" data-id="<?php echo $c['id']; ?>" onclick="selectCat(1, <?php echo $c['id']; ?>)"><?php echo h($c['name']); ?></button>
        <?php endforeach; ?>
    </div>
    <div class="cat-tabs cat-subtabs" id="catTabsL2" style="display:none"></div>
    <div class="cat-tabs cat-subtabs" id="catTabsL3" style="display:none"></div>
</div>
<?php endif; ?>

<div class="product-grid" id="productGrid">
    <?php foreach ($models as $m): ?>
    <div class="product-card" data-model-id="<?php echo $m['id']; ?>">
        <div class="product-name">
            <?php echo h($m['name']); ?>
            <?php if ($m['is_elastic']): ?>
            <span class="elastic-badge"><?php echo L('buy_elastic'); ?></span>
            <?php endif; ?>
        </div>
        <div class="product-price"><?php echo $m['price']; ?> <span>积分/月</span></div>
        <ul class="product-features">
            <li>💾 网页空间：<?php echo $m['web_space'] >= 1024 ? round($m['web_space']/1024,1).'GB' : $m['web_space'].'MB'; ?></li>
            <li>🗄️ 数据库：<?php echo $m['db_space'] >= 1024 ? round($m['db_space']/1024,1).'GB' : $m['db_space'].'MB'; ?></li>
            <li>📊 月流量：<?php echo $m['flow']; ?>GB</li>
            <li>🌐 域名绑定：<?php echo $m['domain_limit']; ?>个</li>
        </ul>
        <?php if ($loggedIn): ?>
        <button type="button" class="btn-primary" style="width:100%" <?php echo $user['points'] < $m['price'] ? 'disabled' : ''; ?>
            onclick="openBuyModal(<?php echo $m['id']; ?>)">
            <?php echo $user['points'] < $m['price'] ? '积分不足' : '立即购买'; ?>
        </button>
        <?php else: ?>
        <a href="login.php" class="btn-primary" style="width:100%;display:block;text-align:center">登录后购买</a>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php if (empty($models)): ?>
    <div class="empty-state" id="emptyState"><?php echo L('buy_no_models'); ?></div>
    <?php endif; ?>
</div>

<!-- ========== 购买弹窗 ========== -->
<div id="buyOverlay" class="modal-overlay" style="display:none"></div>
<div id="buyModal" class="buy-modal" style="display:none">
    <div class="buy-modal-content">
        <div class="buy-modal-header">
            <h3><i class="fas fa-shopping-cart"></i> <?php echo L('buy_confirm'); ?></h3>
            <button type="button" class="modal-close" onclick="closeBuyModal()">&times;</button>
        </div>
        <div class="buy-modal-body">
            <p class="model-name-display" id="modalModelName"></p>

            <!-- 时长选择 -->
            <div class="duration-section">
                <label class="section-label">📅 <?php echo L('buy_select_duration'); ?></label>
                <div class="duration-btns" id="durationBtns"></div>
            </div>

            <!-- 弹性配置 -->
            <div class="elastic-section" id="elasticSection" style="display:none">
                <label class="section-label">⚙️ <?php echo L('buy_elastic'); ?></label>
                <div id="elasticFields"></div>
            </div>

            <!-- 优惠码 -->
            <div class="coupon-section">
                <div class="coupon-toggle" onclick="toggleCoupon()">
                    <i class="fas fa-ticket-alt"></i> <?php echo L('buy_coupon'); ?>
                    <i class="fas fa-chevron-down" id="couponArrow"></i>
                </div>
                <div id="couponArea" class="coupon-area" style="display:none">
                    <div class="coupon-row">
                        <input type="text" id="couponInput" placeholder="<?php echo L('buy_coupon_placeholder'); ?>" maxlength="32" autocomplete="off">
                        <button type="button" class="btn-coupon" id="couponBtn" onclick="applyCoupon()"><?php echo L('buy_coupon_check'); ?></button>
                    </div>
                    <div id="couponMsg" class="coupon-msg"></div>
                    <div id="couponDiscountInfo" class="coupon-discount-info" style="display:none">
                        <i class="fas fa-check-circle"></i>
                        <?php echo L('buy_coupon_discount'); ?>：<strong id="discountPercent">0</strong>%
                        &nbsp;|&nbsp; <?php echo L('buy_final_price'); ?> <strong id="discountedPrice" style="color:var(--red)">0</strong> <?php echo L('buy_points'); ?>
                        <button type="button" class="btn-remove-coupon" onclick="removeCoupon()"><i class="fas fa-times"></i> <?php echo L('cancel'); ?></button>
                    </div>
                </div>
            </div>

            <!-- 价格明细 -->
            <div class="price-summary" id="priceSummary">
                <div class="price-row"><span><?php echo L('buy_base_price'); ?></span><span id="summaryDurationPrice">0</span></div>
                <div class="price-row" id="summaryElasticRow" style="display:none"><span><?php echo L('buy_elastic_price'); ?></span><span id="summaryElasticSurcharge">0</span></div>
                <div class="price-row" id="summaryCouponRow" style="display:none"><span><?php echo L('buy_coupon_discount'); ?></span><span id="summaryCouponDiscount" style="color:var(--green)">0</span></div>
                <div class="price-row price-total"><span><?php echo L('buy_final_price'); ?></span><span id="summaryTotal">0</span></div>
            </div>

            <div class="user-balance" id="balanceInfo">
                <?php echo L('points'); ?>：<strong id="userPointsDisplay"><?php echo $loggedIn ? $user['points'] : 0; ?></strong>
                <span id="balanceAfter" style="display:none;margin-left:8px">
                    → 剩余：<strong id="remainingPoints" style="color:var(--green)">0</strong>
                </span>
            </div>
        </div>
        <div class="buy-modal-footer">
            <button type="button" class="btn-cancel" onclick="closeBuyModal()"><?php echo L('cancel'); ?></button>
            <button type="button" class="btn-cart" id="addToCartBtn" onclick="addToCart()"><?php echo L('buy_add_cart'); ?></button>
            <button type="button" class="btn-confirm" id="confirmBuyBtn" onclick="confirmBuy()"><?php echo L('buy_confirm'); ?></button>
        </div>
    </div>
</div>

<!-- 隐藏表单 -->
<form id="buyForm" method="post" style="display:none">
    <?php echo csrfField(); ?>
    <input type="hidden" name="action" value="buy_host">
    <input type="hidden" name="model_id" id="formModelId" value="">
    <input type="hidden" name="coupon_code" id="formCouponCode" value="">
    <input type="hidden" name="duration_type" id="formDurationType" value="">
    <input type="hidden" name="elastic_values" id="formElasticValues" value="">
</form>

<style>
/* ========== 分类筛选样式 ========== */
.category-filter {
    background: #fff;
    border-radius: 12px;
    padding: 0;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
    overflow: hidden;
}
.cat-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 0;
}
.cat-tab {
    padding: 12px 24px;
    border: none;
    border-bottom: 3px solid transparent;
    background: none;
    font-size: .92rem;
    color: #666;
    cursor: pointer;
    transition: all .2s;
    font-weight: 500;
    white-space: nowrap;
}
.cat-tab:hover {
    color: var(--primary, #6366f1);
    background: rgba(99, 102, 241, 0.04);
}
.cat-tab.active {
    color: var(--primary, #6366f1);
    border-bottom-color: var(--primary, #6366f1);
    font-weight: 700;
}
.cat-subtabs {
    border-top: 1px solid #f0f0f0;
    padding: 4px 8px;
    background: #fafbff;
}
.cat-subtabs .cat-tab {
    padding: 8px 16px;
    font-size: .85rem;
    border-bottom: 2px solid transparent;
}

/* ========== 弹性标签 ========== */
.elastic-badge {
    display: inline-block;
    background: linear-gradient(135deg, #f59e0b, #f97316);
    color: #fff;
    font-size: .7rem;
    padding: 2px 8px;
    border-radius: 10px;
    margin-left: 6px;
    vertical-align: middle;
    font-weight: 500;
}

/* ========== 弹窗新增样式 ========== */
.model-name-display {
    font-size: 1.05rem;
    font-weight: 600;
    color: #333;
    margin: 0 0 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid #eee;
}
.section-label {
    display: block;
    font-size: .9rem;
    font-weight: 600;
    color: #444;
    margin-bottom: 10px;
}
.duration-section {
    margin-bottom: 16px;
}
.duration-btns {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.duration-btn {
    padding: 8px 14px;
    border: 2px solid #dee2ed;
    border-radius: 8px;
    background: #fff;
    cursor: pointer;
    font-size: .85rem;
    transition: all .2s;
    text-align: center;
    min-width: 70px;
    position: relative;
    overflow: hidden;
}
.duration-btn:hover {
    border-color: var(--primary, #6366f1);
    color: var(--primary, #6366f1);
}
.duration-btn.active {
    border-color: var(--primary, #6366f1);
    background: var(--primary, #6366f1);
    color: #fff;
    font-weight: 600;
}
.duration-badge {
    position: absolute;
    top: 0;
    right: 0;
    background: #f97316;
    color: #fff;
    font-size: .65rem;
    padding: 1px 5px;
    border-radius: 0 7px 0 5px;
    line-height: 1.4;
    font-weight: 600;
}

/* ========== 弹性配置样式 ========== */
.elastic-section {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 14px;
    margin-bottom: 16px;
}
.elastic-field {
    margin-bottom: 14px;
}
.elastic-field:last-child {
    margin-bottom: 0;
}
.elastic-field-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 6px;
}
.elastic-field-name {
    font-size: .85rem;
    font-weight: 500;
    color: #444;
}
.elastic-field-info {
    font-size: .8rem;
    color: #888;
}
.elastic-field-info .surcharge {
    color: var(--primary, #6366f1);
    font-weight: 600;
}
.elastic-field input[type="range"] {
    width: 100%;
    -webkit-appearance: none;
    appearance: none;
    height: 6px;
    border-radius: 3px;
    background: #dee2ed;
    outline: none;
    cursor: pointer;
}
.elastic-field input[type="range"]::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: var(--primary, #6366f1);
    cursor: pointer;
    border: 2px solid #fff;
    box-shadow: 0 1px 4px rgba(0,0,0,.2);
}
.elastic-field .range-labels {
    display: flex;
    justify-content: space-between;
    font-size: .75rem;
    color: #999;
    margin-top: 2px;
}

/* ========== 价格明细样式 ========== */
.price-summary {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 12px 14px;
    margin-bottom: 12px;
}
.price-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: .88rem;
    color: #555;
    padding: 3px 0;
}
.price-row span:last-child {
    font-weight: 500;
}
.price-total {
    border-top: 1px dashed #dee2ed;
    margin-top: 6px;
    padding-top: 8px;
    font-size: 1rem;
    font-weight: 700;
    color: #333;
}
.price-total span:last-child {
    color: var(--primary, #6366f1);
    font-size: 1.1rem;
}

/* ========== 弹窗覆盖样式（宽度加宽） ========== */
.buy-modal {
    width: 500px;
    max-width: 94vw;
}

/* ========== 复用原有弹窗样式 ========== */
.modal-overlay {
    position: fixed; top:0; left:0; right:0; bottom:0;
    background: rgba(0,0,0,.55); z-index:9999;
    backdrop-filter: blur(4px);
    animation: fadeIn .2s ease;
}
.buy-modal {
    position: fixed; top:50%; left:50%; transform:translate(-50%,-50%);
    z-index:10000;
    animation: slideUp .25s ease;
}
.buy-modal-content {
    background: #fff; border-radius:16px; overflow:hidden;
    box-shadow: 0 20px 60px rgba(0,0,0,.3);
}
.buy-modal-header {
    display:flex; align-items:center; justify-content:space-between;
    padding:20px 24px 0;
}
.buy-modal-header h3 { margin:0; font-size:1.15rem; }
.buy-modal-header h3 i { margin-right:8px; color:var(--primary, #6366f1); }
.modal-close {
    background:none; border:none; font-size:1.6rem; cursor:pointer;
    color:#999; line-height:1; padding:0 4px;
}
.modal-close:hover { color:#333; }
.buy-modal-body { padding:16px 24px; max-height: 70vh; overflow-y: auto; }

.coupon-section {
    background:#f8f9fa; border-radius:10px; padding:12px 14px; margin-bottom:12px;
}
.coupon-toggle {
    display:flex; align-items:center; gap:8px;
    cursor:pointer; color:var(--primary, #6366f1); font-weight:500; font-size:.95rem;
    user-select:none;
}
.coupon-toggle i:first-child { font-size:1rem; }
.coupon-toggle .fa-chevron-down { margin-left:auto; transition:transform .2s; font-size:.8rem; }
.coupon-toggle .fa-chevron-down.rotated { transform:rotate(180deg); }
.coupon-area { margin-top:10px; }
.coupon-row {
    display:flex; gap:8px;
}
.coupon-row input {
    flex:1; padding:9px 12px; border:2px solid #dee2ed; border-radius:8px;
    font-size:.92rem; outline:none; transition:border .2s;
}
.coupon-row input:focus { border-color:var(--primary, #6366f1); }
.btn-coupon {
    padding:9px 18px; background:var(--primary, #6366f1); color:#fff;
    border:none; border-radius:8px; cursor:pointer; font-weight:500; font-size:.9rem;
    white-space:nowrap; transition:opacity .2s;
}
.btn-coupon:hover { opacity:.88; }
.btn-coupon:disabled { opacity:.5; cursor:not-allowed; }
.coupon-msg { font-size:.85rem; margin-top:6px; min-height:20px; }
.coupon-msg.error { color:#e74c3c; }
.coupon-msg.success { color:var(--green, #10b981); }
.coupon-msg.loading { color:#999; }
.coupon-discount-info {
    margin-top:8px; padding:8px 12px; background:#ecfdf5; border-radius:8px;
    font-size:.85rem; color:#065f46; display:flex; align-items:center; flex-wrap:wrap; gap:4px;
}
.coupon-discount-info i { color:var(--green, #10b981); font-size:1rem; }
.btn-remove-coupon {
    background:none; border:none; color:#999; cursor:pointer;
    font-size:.8rem; padding:2px 6px; margin-left:auto;
}
.btn-remove-coupon:hover { color:#e74c3c; }

.user-balance { font-size:.9rem; color:#666; padding:4px 0; }

.buy-modal-footer {
    display:flex; gap:10px; padding:0 24px 20px; justify-content:flex-end;
}
.btn-cancel {
    padding:10px 24px; background:#f1f3f5; color:#555; border:none;
    border-radius:10px; cursor:pointer; font-weight:500; font-size:.9rem;
    transition:background .2s;
}
.btn-cancel:hover { background:#e9ecef; }
.btn-confirm {
    padding:10px 28px; background:var(--primary, #6366f1); color:#fff;
    border:none; border-radius:10px; cursor:pointer; font-weight:600; font-size:.9rem;
    transition:opacity .2s;
}
.btn-confirm:hover { opacity:.88; }
.btn-confirm:disabled { opacity:.5; cursor:not-allowed; }
.btn-cart { padding:10px 28px; background:#fff; color:var(--primary, #6366f1); border:2px solid var(--primary, #6366f1); border-radius:10px; cursor:pointer; font-weight:600; font-size:.9rem; transition:all .2s }
.btn-cart:hover { background:var(--primary, #6366f1); color:#fff }

@keyframes fadeIn { from{opacity:0} to{opacity:1} }
@keyframes slideUp { from{opacity:0;transform:translate(-50%,-50%) scale(.94)} to{opacity:1;transform:translate(-50%,-50%) scale(1)} }
</style>

<script>
// ========== 多语言 ==========
var _L = Object.assign(_L || {}, {
    buyNoModels: <?php echo json_encode(L('buy_no_models'), JSON_UNESCAPED_UNICODE); ?>,
    buyLoadFail: <?php echo json_encode(L('buy_load_fail'), JSON_UNESCAPED_UNICODE); ?>,
    buyElastic: <?php echo json_encode(L('buy_elastic'), JSON_UNESCAPED_UNICODE); ?>,
    buySelectDuration: <?php echo json_encode(L('buy_select_duration'), JSON_UNESCAPED_UNICODE); ?>,
    buyInsufficient: <?php echo json_encode(L('buy_insufficient_points'), JSON_UNESCAPED_UNICODE); ?>,
    buyBtn: <?php echo json_encode(L('buy_confirm'), JSON_UNESCAPED_UNICODE); ?>,
    buyAddCart: <?php echo json_encode(L('cart_added'), JSON_UNESCAPED_UNICODE); ?>,
    buyPoints: <?php echo json_encode(L('buy_points'), JSON_UNESCAPED_UNICODE); ?>,
    buyMonth: <?php echo json_encode(L('buy_month'), JSON_UNESCAPED_UNICODE); ?>,
    buyQuarter: <?php echo json_encode(L('buy_quarter'), JSON_UNESCAPED_UNICODE); ?>,
    buyHalfYear: <?php echo json_encode(L('buy_half_year'), JSON_UNESCAPED_UNICODE); ?>,
    buyYear: <?php echo json_encode(L('buy_year'), JSON_UNESCAPED_UNICODE); ?>,
    buy2year: <?php echo json_encode(L('buy_2year'), JSON_UNESCAPED_UNICODE); ?>,
    buy3year: <?php echo json_encode(L('buy_3year'), JSON_UNESCAPED_UNICODE); ?>,
    buy5year: <?php echo json_encode(L('buy_5year'), JSON_UNESCAPED_UNICODE); ?>,
    buy10year: <?php echo json_encode(L('buy_10year'), JSON_UNESCAPED_UNICODE); ?>,
    couponInvalid: <?php echo json_encode(L('coupon_invalid'), JSON_UNESCAPED_UNICODE); ?>,
    couponPlaceholder: <?php echo json_encode(L('coupon_placeholder'), JSON_UNESCAPED_UNICODE); ?>,
    couponValidating: <?php echo json_encode(L('coupon_validating'), JSON_UNESCAPED_UNICODE); ?>,
    couponVerifyFail: <?php echo json_encode(L('coupon_verify_fail'), JSON_UNESCAPED_UNICODE); ?>,
    buySuccess: <?php echo json_encode(L('buy_success'), JSON_UNESCAPED_UNICODE); ?>,
    buyMnbtFail: <?php echo json_encode(L('buy_mnbt_fail'), JSON_UNESCAPED_UNICODE); ?>,
    buyPleaseLogin: <?php echo json_encode(L('buy_please_login'), JSON_UNESCAPED_UNICODE); ?>,
    buyModelNotFound: <?php echo json_encode(L('buy_model_not_found'), JSON_UNESCAPED_UNICODE); ?>
});

// ========== 全局数据 ==========
var userPoints = <?php echo $loggedIn ? $user['points'] : 0; ?>;
var allCategories = <?php echo json_encode($allCats, JSON_UNESCAPED_UNICODE); ?>;
var childrenMap = <?php echo json_encode($childrenMap, JSON_UNESCAPED_UNICODE); ?>;
var l1Cats = <?php echo json_encode($l1Cats, JSON_UNESCAPED_UNICODE); ?>;
var defaultL1Id = <?php echo $defaultL1Id; ?>;
var defaultL2Id = <?php echo $defaultL2Id; ?>;
var defaultL3Id = <?php echo $defaultL3Id; ?>;
var hasCategories = <?php echo $hasCategories ? 'true' : 'false'; ?>;

var durationLabels = {
    'month': _L.buyMonth, 'quarter': _L.buyQuarter, 'half_year': _L.buyHalfYear,
    'year': _L.buyYear, '2year': _L.buy2year, '3year': _L.buy3year, '5year': _L.buy5year, '10year': _L.buy10year
};
var durationMonths = {
    'month': 1, 'quarter': 3, 'half_year': 6,
    'year': 12, '2year': 24, '3year': 36,
    '5year': 60, '10year': 120
};
var fieldLabels = {
    'web_space': '网页空间', 'db_space': '数据库空间',
    'flow': '月流量', 'domain_limit': '域名绑定'
};

// ========== 模态窗状态 ==========
var currentModelId = 0;
var currentModelData = null;
var currentDurations = [];
var currentElastic = [];
var currentDurationType = '';
var currentDurationDiscount = 0;
var currentElasticValues = {};
var currentElasticSurcharge = 0;
var currentDurationPrice = 0;
var currentSubtotal = 0;
var currentCouponDiscount = 0;
var appliedCouponCode = '';
var currentFinalPrice = 0;

// ========== CSRF 辅助 ==========
function _csrfParam() {
    var t = document.querySelector('meta[name="csrf-token"]');
    return t ? '&_csrf=' + encodeURIComponent(t.getAttribute('content')) : '';
}

// ========== 格式化函数 ==========
function formatBytes(mb) {
    if (mb >= 1024) return (mb / 1024).toFixed(1) + 'GB';
    return mb + 'MB';
}

function formatFieldValue(fieldName, value) {
    if (fieldName === 'web_space' || fieldName === 'db_space') return formatBytes(value);
    if (fieldName === 'flow') return value + 'GB';
    if (fieldName === 'domain_limit') return value + '个';
    return value;
}

// ========== 三级分类筛选（tab风格） ==========
function selectCat(level, id) {
    // 高亮当前tab
    var container = document.getElementById('catTabsL' + level);
    var tabs = container.querySelectorAll('.cat-tab');
    tabs.forEach(function(t) { t.classList.remove('active'); });
    var activeTab = container.querySelector('.cat-tab[data-id="' + id + '"]');
    if (activeTab) activeTab.classList.add('active');

    if (level === 1) {
        // 加载二级分类
        renderSubTabs(2, id, 0);
        // 隐藏三级
        var l3 = document.getElementById('catTabsL3');
        l3.style.display = 'none';
        l3.innerHTML = '';
        // 如果有二级，等待二级选择；否则直接加载
        var children = childrenMap[id] || [];
        if (children.length === 0) {
            loadModels(id);
        }
    } else if (level === 2) {
        // 加载三级分类
        renderSubTabs(3, id, 0);
        var children = childrenMap[id] || [];
        if (children.length === 0) {
            loadModels(id);
        }
    } else if (level === 3) {
        loadModels(id);
    }
}

function renderSubTabs(level, parentId, selectedId) {
    var container = document.getElementById('catTabsL' + level);
    var children = childrenMap[parentId] || [];
    if (children.length === 0) {
        container.style.display = 'none';
        container.innerHTML = '';
        return;
    }
    container.style.display = '';
    container.innerHTML = '';
    children.forEach(function(c) {
        var active = (selectedId && c.id == selectedId) || (!selectedId && children[0].id === c.id) ? ' active' : '';
        container.innerHTML += '<button type="button" class="cat-tab' + active + '" data-id="' + c.id + '" onclick="selectCat(' + level + ', ' + c.id + ')">' + escHtml(c.name) + '</button>';
    });
    // 自动选择第一个
    var selId = selectedId || children[0].id;
    if (level === 2) {
        selectCat(2, selId);
    } else {
        selectCat(3, selId);
    }
}

function initCategories() {
    if (!hasCategories) return;
    // 默认选择第一个一级分类
    if (l1Cats.length > 0) {
        selectCat(1, l1Cats[0].id);
    }
}

function loadModels(categoryId) {
    var grid = document.getElementById('productGrid');
    if (!categoryId) {
        grid.innerHTML = '<div class="empty-state">' + _L.buyNoModels + '</div>';
        return;
    }

    grid.innerHTML = '<div class="empty-state" style="color:#999">加载中...</div>';

    var xhr = new XMLHttpRequest();
    xhr.open('POST', '', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
        try {
            var res = JSON.parse(xhr.responseText);
            if (res.models && res.models.length > 0) {
                grid.innerHTML = res.models.map(function(m) {
                    return renderProductCard(m);
                }).join('');
            } else {
                grid.innerHTML = '<div class="empty-state">' + _L.buyNoModels + '</div>';
            }
        } catch(e) {
            grid.innerHTML = '<div class="empty-state">' + _L.buyLoadFail + '</div>';
        }
    };
    xhr.send('action=get_models&category_id=' + categoryId + _csrfParam());
}

function renderProductCard(m) {
    var html = '<div class="product-card" data-model-id="' + m.id + '">';
    html += '<div class="product-name">' + escHtml(m.name);
    if (m.is_elastic == 1) {
        html += ' <span class="elastic-badge"><?php echo L('buy_elastic'); ?></span>';
    }
    html += '</div>';
    html += '<div class="product-price">' + m.price + ' <span>积分/月</span></div>';
    html += '<ul class="product-features">';
    if (m.is_elastic == 1 && m.elastic && m.elastic.length > 0) {
        // 弹性型号：显示弹性范围
        var elasticMap = {};
        for (var ei = 0; ei < m.elastic.length; ei++) {
            elasticMap[m.elastic[ei].field_name] = m.elastic[ei];
        }
        var fields = [
            {key: 'web_space', emoji: '💾', label: '网页空间'},
            {key: 'db_space', emoji: '🗄️', label: '数据库'},
            {key: 'flow', emoji: '📊', label: '月流量'},
            {key: 'domain_limit', emoji: '🌐', label: '域名绑定'}
        ];
        for (var fi = 0; fi < fields.length; fi++) {
            var f = fields[fi];
            var ec = elasticMap[f.key];
            if (ec) {
                html += '<li>' + f.emoji + ' ' + f.label + '：' + formatFieldValue(f.key, parseInt(ec.min_value)) + ' - ' + formatFieldValue(f.key, parseInt(ec.max_value)) + '</li>';
            } else {
                html += '<li>' + f.emoji + ' ' + f.label + '：' + formatFieldValue(f.key, parseInt(m[f.key])) + '</li>';
            }
        }
    } else {
        html += '<li>💾 网页空间：' + (m.web_space >= 1024 ? (m.web_space/1024).toFixed(1)+'GB' : m.web_space+'MB') + '</li>';
        html += '<li>🗄️ 数据库：' + (m.db_space >= 1024 ? (m.db_space/1024).toFixed(1)+'GB' : m.db_space+'MB') + '</li>';
        html += '<li>📊 月流量：' + m.flow + 'GB</li>';
        html += '<li>🌐 域名绑定：' + m.domain_limit + '个</li>';
    }
    html += '</ul>';

    var loggedIn = <?php echo $loggedIn ? 'true' : 'false'; ?>;
    if (loggedIn) {
        var disabled = userPoints < m.price ? ' disabled' : '';
        var label = userPoints < m.price ? _L.buyInsufficient : _L.buyBtn;
        html += '<button type="button" class="btn-primary" style="width:100%"' + disabled + ' onclick="openBuyModal(' + m.id + ')">' + label + '</button>';
    } else {
        html += '<a href="login.php" class="btn-primary" style="width:100%;display:block;text-align:center">登录后购买</a>';
    }
    html += '</div>';
    return html;
}

function escHtml(str) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
}

// ========== 购买弹窗 ==========
function openBuyModal(modelId) {
    currentModelId = modelId;
    resetModalState();

    document.getElementById('buyOverlay').style.display = 'block';
    document.getElementById('buyModal').style.display = 'block';
    document.body.style.overflow = 'hidden';

    // 加载型号数据
    document.getElementById('modalModelName').textContent = '加载中...';
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
        try {
            var res = JSON.parse(xhr.responseText);
            if (res.error) {
                alert(res.error);
                closeBuyModal();
                return;
            }
            currentModelData = res.model;
            currentDurations = res.durations || [];
            currentElastic = res.elastic || [];

            document.getElementById('modalModelName').textContent = currentModelData.name;
            renderDurations();
            renderElastic();
            updatePriceDisplay();
        } catch(e) {
            alert('加载失败，请重试');
            closeBuyModal();
        }
    };
    xhr.send('action=get_model_data&model_id=' + modelId + _csrfParam());
}

function resetModalState() {
    currentModelData = null;
    currentDurations = [];
    currentElastic = [];
    currentDurationType = '';
    currentDurationDiscount = 0;
    currentElasticValues = {};
    currentElasticSurcharge = 0;
    currentDurationPrice = 0;
    currentSubtotal = 0;
    currentCouponDiscount = 0;
    appliedCouponCode = '';
    currentFinalPrice = 0;

    document.getElementById('durationBtns').innerHTML = '';
    document.getElementById('elasticFields').innerHTML = '';
    document.getElementById('elasticSection').style.display = 'none';
    document.getElementById('couponInput').value = '';
    document.getElementById('couponMsg').textContent = '';
    document.getElementById('couponMsg').className = 'coupon-msg';
    document.getElementById('couponDiscountInfo').style.display = 'none';
    document.getElementById('couponArea').style.display = 'none';
    var arrow = document.getElementById('couponArrow');
    if (arrow) arrow.classList.remove('rotated');
    document.getElementById('balanceAfter').style.display = 'none';
    document.getElementById('confirmBuyBtn').disabled = false;
    document.getElementById('userPointsDisplay').textContent = userPoints;
    document.getElementById('formCouponCode').value = '';
    document.getElementById('formDurationType').value = '';
    document.getElementById('formElasticValues').value = '';
}

function closeBuyModal() {
    document.getElementById('buyOverlay').style.display = 'none';
    document.getElementById('buyModal').style.display = 'none';
    document.body.style.overflow = '';
}

// ========== 时长选择 ==========
function renderDurations() {
    var container = document.getElementById('durationBtns');
    if (currentDurations.length === 0) {
        container.innerHTML = '<span style="color:#999;font-size:.85rem">暂无可用时长</span>';
        return;
    }

    var html = '';
    for (var i = 0; i < currentDurations.length; i++) {
        var d = currentDurations[i];
        var months = durationMonths[d.duration_type] || 1;
        var price = Math.ceil(currentModelData.price * months * (100 - d.discount) / 100);
        var label = durationLabels[d.duration_type] || d.duration_type;
        var discountBadge = '';
        if (d.discount > 0) {
            var zhe = ((100 - d.discount) / 10).toFixed(1);
            // 去掉末尾的 .0
            if (zhe.indexOf('.0') === zhe.length - 2) zhe = zhe.slice(0, -2);
            discountBadge = '<span class="duration-badge">' + zhe + '折</span>';
        }
        html += '<button type="button" class="duration-btn" data-type="' + d.duration_type + '" data-discount="' + d.discount + '" data-price="' + price + '" onclick="selectDuration(this, \'' + d.duration_type + '\', ' + d.discount + ', ' + price + ')">' + discountBadge + label + '<br><small>' + price + '积分</small></button>';
    }
    container.innerHTML = html;

    // 默认选中第一个
    var firstBtn = container.querySelector('.duration-btn');
    if (firstBtn) {
        firstBtn.click();
    }
}

function selectDuration(btn, type, discount, price) {
    var btns = document.querySelectorAll('.duration-btn');
    for (var i = 0; i < btns.length; i++) {
        btns[i].classList.remove('active');
    }
    btn.classList.add('active');
    currentDurationType = type;
    currentDurationDiscount = discount;
    currentDurationPrice = price;
    updatePriceDisplay();
}

// ========== 弹性配置 ==========
function renderElastic() {
    var section = document.getElementById('elasticSection');
    var container = document.getElementById('elasticFields');

    if (!currentElastic || currentElastic.length === 0 || currentModelData.is_elastic != 1) {
        section.style.display = 'none';
        currentElasticValues = {};
        currentElasticSurcharge = 0;
        return;
    }

    section.style.display = 'block';
    var html = '';

    // 初始化 elastic values（从min_value开始，不是模型存值）
    var values = {};
    for (var i = 0; i < currentElastic.length; i++) {
        var ec = currentElastic[i];
        values[ec.field_name] = parseInt(ec.min_value);
    }
    currentElasticValues = values;

    for (var i = 0; i < currentElastic.length; i++) {
        var ec = currentElastic[i];
        var fn = ec.field_name;
        var minV = parseInt(ec.min_value);
        var maxV = parseInt(ec.max_value);
        var step = parseInt(ec.step);
        var unitPrice = parseInt(ec.unit_price);

        html += '<div class="elastic-field">';
        html += '<div class="elastic-field-header">';
        html += '<span class="elastic-field-name">' + (fieldLabels[fn] || fn) + '</span>';
        html += '<span class="elastic-field-info" id="elasticInfo_' + fn + '">' + formatFieldValue(fn, minV) + ' <span class="surcharge">+0积分</span></span>';
        html += '</div>';
        html += '<input type="range" id="elasticRange_' + fn + '" min="' + minV + '" max="' + maxV + '" step="' + step + '" value="' + minV + '" oninput="onElasticChange(\'' + fn + '\', this.value)">';
        html += '<div class="range-labels"><span>' + formatFieldValue(fn, minV) + '</span><span>' + formatFieldValue(fn, maxV) + '</span></div>';
        html += '</div>';
    }
    container.innerHTML = html;

    recalcElasticSurcharge();
}

function onElasticChange(fieldName, value) {
    currentElasticValues[fieldName] = parseInt(value);
    recalcElasticSurcharge();
}

function recalcElasticSurcharge() {
    var surcharge = 0;
    for (var i = 0; i < currentElastic.length; i++) {
        var ec = currentElastic[i];
        var fn = ec.field_name;
        var baseVal = parseInt(ec.min_value);
        var currentVal = parseInt(currentElasticValues[fn]) || baseVal;
        var step = parseInt(ec.step);
        var unitPrice = parseInt(ec.unit_price);

        var infoEl = document.getElementById('elasticInfo_' + fn);
        if (infoEl) {
            var fieldSurcharge = 0;
            if (currentVal > baseVal && step > 0) {
                fieldSurcharge = Math.floor((currentVal - baseVal) / step) * unitPrice;
            }
            surcharge += fieldSurcharge;
            infoEl.innerHTML = formatFieldValue(fn, currentVal) + ' <span class="surcharge">+' + fieldSurcharge + '积分</span>';
        }
    }
    currentElasticSurcharge = surcharge;
    updatePriceDisplay();
}

// ========== 价格计算与显示 ==========
function updatePriceDisplay() {
    if (!currentModelData) return;

    currentSubtotal = currentDurationPrice + currentElasticSurcharge;

    if (currentCouponDiscount > 0 && appliedCouponCode) {
        currentFinalPrice = Math.ceil(currentSubtotal * (100 - currentCouponDiscount) / 100);
    } else {
        currentFinalPrice = currentSubtotal;
    }

    // 更新价格明细
    document.getElementById('summaryDurationPrice').textContent = currentDurationPrice + ' 积分';
    var elasticRow = document.getElementById('summaryElasticRow');
    if (currentElasticSurcharge > 0) {
        elasticRow.style.display = 'flex';
        document.getElementById('summaryElasticSurcharge').textContent = '+' + currentElasticSurcharge + ' 积分';
    } else {
        elasticRow.style.display = 'none';
    }

    var couponRow = document.getElementById('summaryCouponRow');
    if (currentCouponDiscount > 0 && appliedCouponCode) {
        couponRow.style.display = 'flex';
        document.getElementById('summaryCouponDiscount').textContent = '-' + (currentSubtotal - currentFinalPrice) + ' 积分';
    } else {
        couponRow.style.display = 'none';
    }

    document.getElementById('summaryTotal').textContent = currentFinalPrice + ' 积分';

    // 更新余额显示
    var remain = userPoints - currentFinalPrice;
    document.getElementById('remainingPoints').textContent = remain >= 0 ? remain : 0;
    document.getElementById('balanceAfter').style.display = 'inline';

    // 更新确认按钮
    document.getElementById('confirmBuyBtn').disabled = (userPoints < currentFinalPrice);
}

// ========== 优惠码 ==========
function toggleCoupon() {
    var area = document.getElementById('couponArea');
    var arrow = document.getElementById('couponArrow');
    if (area.style.display === 'none') {
        area.style.display = 'block';
        arrow.classList.add('rotated');
    } else {
        area.style.display = 'none';
        arrow.classList.remove('rotated');
    }
}

function applyCoupon() {
    var code = document.getElementById('couponInput').value.trim();
    var msg = document.getElementById('couponMsg');
    if (!code) { msg.textContent = '请输入优惠码'; msg.className = 'coupon-msg error'; return; }
    if (!currentDurationType) { msg.textContent = '请先选择时长'; msg.className = 'coupon-msg error'; return; }

    msg.textContent = '验证中...'; msg.className = 'coupon-msg loading';
    document.getElementById('couponBtn').disabled = true;

    var elasticJson = JSON.stringify(currentElasticValues);

    var xhr = new XMLHttpRequest();
    xhr.open('POST', '', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
        document.getElementById('couponBtn').disabled = false;
        try {
            var res = JSON.parse(xhr.responseText);
            if (res.valid) {
                currentCouponDiscount = res.discount;
                appliedCouponCode = code;
                currentFinalPrice = res.final_price;

                document.getElementById('discountPercent').textContent = res.discount;
                document.getElementById('discountedPrice').textContent = res.final_price;
                document.getElementById('couponDiscountInfo').style.display = 'flex';
                document.getElementById('couponMsg').textContent = '';
                document.getElementById('couponMsg').className = 'coupon-msg';

                updatePriceDisplay();

                if (userPoints < currentFinalPrice) {
                    document.getElementById('confirmBuyBtn').disabled = true;
                    msg.textContent = '积分不足，请先充值'; msg.className = 'coupon-msg error';
                } else {
                    document.getElementById('confirmBuyBtn').disabled = false;
                }
            } else {
                msg.textContent = res.message; msg.className = 'coupon-msg error';
            }
        } catch(e) {
            msg.textContent = '验证失败，请重试'; msg.className = 'coupon-msg error';
        }
    };
    var params = 'action=check_coupon&code=' + encodeURIComponent(code) + '&model_id=' + currentModelId + '&duration_type=' + encodeURIComponent(currentDurationType) + '&elastic_values=' + encodeURIComponent(elasticJson) + _csrfParam();
    xhr.send(params);
}

function removeCoupon() {
    currentCouponDiscount = 0;
    appliedCouponCode = '';
    document.getElementById('couponDiscountInfo').style.display = 'none';
    document.getElementById('couponInput').value = '';
    document.getElementById('couponMsg').textContent = '';
    document.getElementById('couponMsg').className = 'coupon-msg';
    updatePriceDisplay();
}

// ========== 确认购买 ==========
function confirmBuy() {
    if (!currentDurationType) {
        alert(_L.buySelectDuration);
        return;
    }
    document.getElementById('formModelId').value = currentModelId;
    document.getElementById('formCouponCode').value = appliedCouponCode;
    document.getElementById('formDurationType').value = currentDurationType;
    document.getElementById('formElasticValues').value = JSON.stringify(currentElasticValues);
    document.getElementById('confirmBuyBtn').disabled = true;
    if (typeof showLoading === 'function') showLoading();
    document.getElementById('buyForm').submit();
}

// ========== 购物车 ==========
function addToCart() {
    if (!currentDurationType) { alert(_L.buySelectDuration); return; }
    var fd = new FormData();
    fd.append('action', 'add_to_cart');
    fd.append('model_id', currentModelId);
    fd.append('duration_type', currentDurationType);
    fd.append('elastic_values', JSON.stringify(currentElasticValues));
    fd.append('coupon_code', appliedCouponCode);
    fetch('', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.ok) {
                alert(_L.buyAddCart);
                updateCartBadge(res.cart_count);
            } else {
                alert(res.message);
            }
        });
}

// ========== 初始化 ==========
document.addEventListener('DOMContentLoaded', function() {
    var overlay = document.getElementById('buyOverlay');
    overlay.addEventListener('click', closeBuyModal);
    initCategories();
});
</script>
<?php renderFooter(); ?>