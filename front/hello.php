<?php
/**
 * Hello - My Assigned Assets v1.6.6
 */
include '../../../inc/includes.php';
Session::checkLoginUser();
global $DB, $CFG_GLPI;

$title = __('My Assigned Assets', 'hello');
$root = isset($_GET['root']) ? $_GET['root'] : 'assets';
if ($root !== 'assets' && $root !== 'helpdesk') $root = 'assets';
Html::header($title, $_SERVER['PHP_SELF'], $root, '');

// ----------------- helpers -----------------
function h($s){ return Html::clean(Html::entity_decode_deep((string)$s)); }
function s($a,$k){ return isset($a[$k]) ? $a[$k] : ''; }
function dash($v){ $v = trim((string)$v); return $v === '' ? '—' : h($v); }
function dd($table,$id){ return $id ? Dropdown::getDropdownName($table,(int)$id) : ''; }

function hello_encode_path($rel){
  $parts = explode('/', (string)$rel);
  foreach ($parts as &$p) { $p = rawurlencode($p); }
  return implode('/', $parts);
}
function hello_picture_urls_from_rel($rel){
  global $CFG_GLPI;
  $enc = hello_encode_path($rel);
  $send   = $CFG_GLPI['root_doc'] . '/front/picture.send.php?file=' . $enc;
  $direct = $CFG_GLPI['root_doc'] . '/files/_pictures/' . $enc;
  return ['thumb'=>$send,'full'=>$send,'thumb_fallback'=>$direct,'full_fallback'=>$direct];
}
function hello_model_pic_urls($model_id, $kind='computer'){
  global $DB;
  if (!$model_id) return [];
  $table = $kind==='monitor' ? 'glpi_monitormodels' : 'glpi_computermodels';
  if (!$DB->tableExists($table)) return [];
  $it = $DB->request([
    'SELECT' => ['pictures','picture_front','picture_rear'],
    'FROM'   => $table,
    'WHERE'  => ['id' => (int)$model_id],
    'LIMIT'  => 1
  ]);
  if (!$it->count()) return [];
  $row = $it->current();
  $cands = [];
  if (!empty($row['pictures'])){
    $decoded = json_decode($row['pictures'], true);
    if (json_last_error()===JSON_ERROR_NONE && is_array($decoded)){
      foreach ($decoded as $p){ if (!empty($p)) $cands[] = $p; }
    }
  }
  if (!empty($row['picture_front'])) $cands[] = $row['picture_front'];
  if (!empty($row['picture_rear']))  $cands[] = $row['picture_rear'];
  if (!count($cands)) return [];
  return hello_picture_urls_from_rel($cands[0]);
}
function hello_item_doc_urls($itemtype,$items_id){
  global $DB, $CFG_GLPI;
  if (!$DB->tableExists('glpi_documents_items') || !$DB->tableExists('glpi_documents')) return [];
  $rows = $DB->request([
    'SELECT'=>['glpi_documents.id','glpi_documents.filename','glpi_documents.mime','glpi_documents.is_main'],
    'FROM'=>'glpi_documents_items',
    'LEFT JOIN'=>['glpi_documents'=>['ON'=>['glpi_documents_items'=>'documents_id','glpi_documents'=>'id']]],
    'WHERE'=>['glpi_documents_items.itemtype'=>$itemtype,'glpi_documents_items.items_id'=>(int)$items_id],
    'ORDER'=>['glpi_documents.is_main DESC','glpi_documents.id ASC'],
    'LIMIT'=>10
  ]);
  foreach ($rows as $r){
    $mime = strtolower((string)$r['mime']);
    $file = strtolower((string)$r['filename']);
    $is_img = (strpos($mime,'image/')===0) || preg_match('/\.(jpg|jpeg|png|gif|webp|bmp)$/i',$file);
    if ($is_img){
      $base = $CFG_GLPI['root_doc'] . '/front/document.send.php?docid=' . (int)$r['id'];
      return ['thumb'=>$base.'&showthumb=1','full'=>$base];
    }
  }
  return [];
}
function hello_primary_email($uid){
  global $DB;
  $email='';
  if ($DB->tableExists('glpi_useremails')){
    $it=$DB->request(['SELECT'=>['email','is_default'],'FROM'=>'glpi_useremails','WHERE'=>['users_id'=>$uid],'ORDER'=>['is_default DESC','id ASC']]);
    foreach ($it as $r){ $v=trim((string)$r['email']); if ($v!==''){ $email=$v; break; } }
  }
  if ($email===''){ $u=new User(); if ($u->getFromDB($uid)) $email = trim((string)$u->fields['email']); }
  return $email;
}
function hello_group_display($uid){
  global $DB;
  $ids=[]; $names=[]; $def='';
  if (class_exists('Group_User') && method_exists('Group_User','getUserGroups')){
    $ugs = Group_User::getUserGroups($uid);
    if (is_array($ugs)){
      foreach ($ugs as $ug){
        $gid=0; $gname='';
        if (is_array($ug)){
          if (isset($ug['groups_id'])) $gid=(int)$ug['groups_id']; elseif (isset($ug['id'])) $gid=(int)$ug['id'];
          if (isset($ug['name'])) $gname=$ug['name']; elseif ($gid) $gname=Dropdown::getDropdownName('glpi_groups',$gid);
          if (!empty($ug['is_default'])) $def=$gname;
        } elseif (is_numeric($ug)) { $gid=(int)$ug; $gname=Dropdown::getDropdownName('glpi_groups',$gid); }
        if ($gid) $ids[]=$gid; if ($gname!=='') $names[]=$gname;
      }
    }
  }
  if (!count($ids) && $DB->tableExists('glpi_groups_users')){
    $it=$DB->request(['SELECT'=>['glpi_groups.id AS gid','glpi_groups.name AS gname','glpi_groups_users.is_default'],
                      'FROM'=>'glpi_groups_users',
                      'LEFT JOIN'=>['glpi_groups'=>['ON'=>['glpi_groups_users'=>'groups_id','glpi_groups'=>'id']]],
                      'WHERE'=>['glpi_groups_users.users_id'=>$uid]]);
    foreach ($it as $r){ $gid=(int)$r['gid']; $gname=(string)$r['gname']; if ($gid) $ids[]=$gid; if ($gname!=='') $names[]=$gname; if (!empty($r['is_default'])) $def=$gname; }
  }
  if (count($names)) return $def!=='' ? $def : implode(', ', $names);
  $u=new User(); if ($u->getFromDB($uid)){ $ent=dd('glpi_entities',(int)s($u->fields,'entities_id')); if ($ent!=='') return $ent; }
  return '—';
}

// ----------------- user card -----------------
$user_id = Session::getLoginUserID();
$user = new User();
$user->getFromDB($user_id);

$email = hello_primary_email($user_id);
$dept  = hello_group_display($user_id);

echo '<div class="card" style="max-width:1200px;margin:20px auto;"><div class="card-body">';
echo '<h3 class="card-title">'.h($title).'</h3>';
echo '<div class="row mt-3">';
echo ' <div class="col-md-3 text-center">';
$avatar='';
if (method_exists($user,'getPicture')){
  $p=$user->getPicture();
  if (is_string($p)&&$p!==''){ $avatar='<img src="'.h($p).'" style="max-width:120px;border-radius:50%;box-shadow:0 2px 6px rgba(0,0,0,.15);" alt="user">'; }
  elseif (is_array($p)){ $url=''; if (isset($p['small'])) $url=$p['small']; elseif (isset($p['url'])) $url=$p['url']; if ($url) $avatar='<img src="'.h($url).'" style="max-width:120px;border-radius:50%;box-shadow:0 2px 6px rgba(0,0,0,.15);" alt="user">'; }
}
if ($avatar===''){
  $fn = trim(s($user->fields,'firstname').' '.s($user->fields,'realname'));
  $inits=''; foreach (explode(' ',$fn) as $part){ if ($part!=='') $inits .= strtoupper($part[0]); }
  if ($inits==='') $inits = strtoupper(substr(s($user->fields,'name'),0,2));
  $avatar = '<div style="width:120px;height:120px;line-height:120px;border-radius:50%;background:#e5e7eb;display:inline-block;font-weight:700;"><span style="font-size:36px;color:#374151;">'.h($inits).'</span></div>';
}
echo $avatar;
echo ' </div>';
echo ' <div class="col-md-9"><table class="tab_cadre_fixe" style="width:100%;">';
$fullname = trim((s($user->fields,'firstname').' '.s($user->fields,'realname'))) ?: $user->getFriendlyName();
echo '  <tr class="tab_bg_1"><th style="width:240px;">'.__('Full Name').'</th><td>'.dash($fullname).'</td></tr>';
echo '  <tr class="tab_bg_1"><th>'.__('Title').'</th><td>'.dash(dd('glpi_usertitles',(int)s($user->fields,'usertitles_id'))).'</td></tr>';
echo '  <tr class="tab_bg_1"><th>'._n('Email','Emails',1).'</th><td>'.dash($email).'</td></tr>';
echo '  <tr class="tab_bg_1"><th>'.__('Department (Group)').'</th><td>'.dash($dept).'</td></tr>';
echo ' </table></div>';
echo '</div>';

// styles + universal modal
echo '<style>.hello-thumb{width:48px;height:36px;object-fit:cover;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,.2);cursor:pointer}
.hello-modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.6);display:none;align-items:center;justify-content:center;z-index:9999}
.hello-modal{background:#fff;border-radius:10px;max-width:800px;width:92%;box-shadow:0 10px 30px rgba(0,0,0,.35)}
.hello-modal-header{display:flex;justify-content:space-between;align-items:center;padding:12px 16px;border-bottom:1px solid #eee}
.hello-modal-body{padding:16px}
.hello-close{border:none;background:transparent;font-size:20px;cursor:pointer}
.hello-pc-big{max-width:100%;height:auto;border-radius:8px}</style>';
echo '</div></div>';

echo '<div id="helloModalOverlay" class="hello-modal-overlay" role="dialog" aria-modal="true">';
echo '  <div class="hello-modal">';
echo '    <div class="hello-modal-header"><h5 id="helloModalTitle" style="margin:0;">'.__('Model').'</h5><button type="button" class="hello-close" aria-label="Close">&times;</button></div>';
echo '    <div class="hello-modal-body"><img id="helloModalImg" class="hello-pc-big" alt="model"></div>';
echo '  </div>';
echo '</div>';

// ----------------- gather assigned items -----------------
$seen=[];
$iu = $DB->request(['SELECT'=>['itemtype','items_id'],'DISTINCT'=>true,'FROM'=>'glpi_items_users','WHERE'=>['users_id'=>$user_id]]);
foreach ($iu as $r){ $seen[$r['itemtype'].'#'.$r['items_id']] = true; }
foreach (['Computer'=>'glpi_computers','Monitor'=>'glpi_monitors'] as $itp=>$tbl){
  if (!$DB->tableExists($tbl)) continue;
  $res=$DB->request(['SELECT'=>['id'],'FROM'=>$tbl,'WHERE'=>['users_id'=>$user_id,'is_deleted'=>0,'is_template'=>0]]);
  foreach ($res as $row){ $seen[$itp.'#'.$row['id']] = true; }
}
$computers=[]; $monitors=[];
foreach (array_keys($seen) as $key){
  list($itp,$id) = explode('#',$key,2); $id=(int)$id;
  $item = getItemForItemtype($itp); if (!$item instanceof CommonDBTM) continue;
  if (!$item->getFromDB($id)) continue;
  if (!empty($item->fields['is_deleted']) || !empty($item->fields['is_template'])) continue;
  if ($itp==='Computer') $computers[] = $item;
  if ($itp==='Monitor')  $monitors[]  = $item;
}

// ----------------- computers table -----------------
echo '<div class="mt-4"><h4>'.__('Computer / Asset Info','hello').'</h4>';
if (!count($computers)){
  echo '<div class="alert alert-info">'.__('There are no computers assigned to your account.','hello').'</div>';
} else {
  echo '<table class="tab_cadre_fixe" style="width:100%;">';
  echo '<tr class="tab_bg_1"><th style="width:64px;text-align:center;">'.__('').'</th><th>'.__('Asset Number').'</th><th>'.__('Location').'</th><th>'.__('Type','hello').'</th><th>'.__('Computer Model','hello').'</th><th>'.__('S/N').'</th><th>'.__('Company').'</th><th>'.__('Agent','hello').'</th></tr>';
  $has_agents = $DB->tableExists('glpi_agents');
  foreach ($computers as $c){
    $loc   = dd('glpi_locations', s($c->fields,'locations_id'));
    $ctype = dd('glpi_computertypes', s($c->fields,'computertypes_id'));
    $cmodel= dd('glpi_computermodels', s($c->fields,'computermodels_id'));
    $ent   = dd('glpi_entities', s($c->fields,'entities_id'));

    $has_agent=false;
    if ($has_agents){
      $ag=$DB->request(['FROM'=>'glpi_agents','WHERE'=>['itemtype'=>'Computer','items_id'=>(int)$c->fields['id']]]);
      foreach ($ag as $row){ $has_agent=true; break; }
    }
    if (!$has_agent && isset($c->fields['is_dynamic'])) $has_agent = ((int)$c->fields['is_dynamic']===1);
    $agent_txt = $has_agent ? __('Yes') : __('No');

    $urls = hello_model_pic_urls((int)$c->fields['computermodels_id'],'computer');
    if (!$urls) $urls = hello_item_doc_urls('Computer',(int)$c->fields['id']);
    if ($urls){
      $img = '<img class="hello-thumb" src="'.h($urls['thumb']).'" alt="model" data-title="'.h($cmodel).'" data-full="'.h($urls['full']).'" data-thumb-fallback="'.h($urls['thumb_fallback'] ?? '').'" data-full-fallback="'.h($urls['full_fallback'] ?? '').'" onerror="if(!this.dataset.fb){this.dataset.fb=1; this.src=this.dataset.thumbFallback || this.src;}">';
    } else {
      $svg='data:image/svg+xml;utf8,'.rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="48" height="36" viewBox="0 0 24 16"><rect x="3" y="2" width="18" height="10" rx="1.5" fill="#D1D5DB"/><rect x="4" y="3" width="16" height="8" rx="0.5" fill="#F9FAFB"/><rect x="2" y="12" width="20" height="2" rx="1" fill="#9CA3AF"/></svg>');
      $img = '<img class="hello-thumb" src="'.$svg.'" alt="pc" data-title="'.h($cmodel).'" data-full="'.$svg.'">';
    }

    echo '<tr class="tab_bg_1">';
    echo '<td style="text-align:center;vertical-align:middle;">'.$img.'</td>';
    echo '<td>'.dash(s($c->fields,'name')).'</td>';
    echo '<td>'.dash($loc).'</td>';
    echo '<td>'.dash($ctype).'</td>';
    echo '<td>'.dash($cmodel).'</td>';
    echo '<td>'.dash(s($c->fields,'serial')).'</td>';
    echo '<td>'.dash($ent).'</td>';
    echo '<td>'.$agent_txt.'</td>';
    echo '</tr>';
  }
  echo '</table>';
}
echo '</div>';

// ----------------- monitors table -----------------
echo '<div class="mt-4"><h4>'.__('Monitor Info','hello').'</h4>';
if (!count($monitors)){
  echo '<div class="alert alert-info">'.__('No monitors assigned to your account.','hello').'</div>';
} else {
  echo '<table class="tab_cadre_fixe" style="width:100%;">';
  echo '<tr class="tab_bg_1"><th style="width:64px;text-align:center;">'.__('').'</th><th>'.__('Asset Number').'</th><th>'.__('Monitor Model').'</th><th>'.__('S/N').'</th></tr>';
  foreach ($monitors as $m){
    $model = dd('glpi_monitormodels', s($m->fields,'monitormodels_id'));
    $urls  = hello_model_pic_urls((int)$m->fields['monitormodels_id'],'monitor');
    if (!$urls) $urls = hello_item_doc_urls('Monitor',(int)$m->fields['id']);
    if ($urls){
      $img = '<img class="hello-thumb" src="'.h($urls['thumb']).'" alt="monitor" data-title="'.h($model).'" data-full="'.h($urls['full']).'" data-thumb-fallback="'.h($urls['thumb_fallback'] ?? '').'" data-full-fallback="'.h($urls['full_fallback'] ?? '').'" onerror="if(!this.dataset.fb){this.dataset.fb=1; this.src=this.dataset.thumbFallback || this.src;}">';
    } else {
      $svg='data:image/svg+xml;utf8,'.rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="48" height="36" viewBox="0 0 24 16"><rect x="3" y="2" width="18" height="10" rx="1.5" fill="#D1D5DB"/><rect x="4" y="3" width="16" height="8" rx="0.5" fill="#F9FAFB"/><rect x="2" y="12" width="20" height="2" rx="1" fill="#9CA3AF"/></svg>');
      $img = '<img class="hello-thumb" src="'.$svg.'" alt="monitor" data-title="'.h($model).'" data-full="'.$svg.'">';
    }
    echo '<tr class="tab_bg_1"><td style="text-align:center;vertical-align:middle;">'.$img.'</td><td>'.dash(s($m->fields,'name')).'</td><td>'.dash($model).'</td><td>'.dash(s($m->fields,'serial')).'</td></tr>';
  }
  echo '</table>';
}
echo '</div>';

// ----------------- licenses (license tables only) -----------------
echo '<div class="mt-4"><h4>'.__('Licenses / Software','hello').'</h4>';

$uid = (int)Session::getLoginUserID();
$rows = [];         // details per license id
$via  = [];         // licid => array of sources ('User', 'Computer <name>', 'Group <name>')

// Build base SELECT and LEFT JOINs starting from items_softwarelicenses or users_softwarelicenses as needed
$select_base = [
   "glpi_softwarelicenses.id AS licid",
   "glpi_softwarelicenses.name AS licname"
];
$select_swname = "glpi_softwarelicenses.name AS softname";
if ($DB->tableExists('glpi_softwares') && $DB->fieldExists('glpi_softwarelicenses','softwares_id')){
   $select_swname = "glpi_softwares.name AS softname";
}
$select_base[] = $select_swname;

// Version name (use whichever field exists)
$vername = "'' AS vername";
if ($DB->tableExists('glpi_softwareversions')){
   foreach (['softwareversions_id_use','softwareversions_id_buy','softwareversions_id'] as $vf){
      if ($DB->fieldExists('glpi_softwarelicenses',$vf)){
         $vername = "glpi_softwareversions.name AS vername";
         break;
      }
   }
}
$select_base[] = $vername;

// Key/Serial and Expire
$keyexpr = "'' AS lickey";
foreach (['license_key','serial','key'] as $kf){
   if ($DB->fieldExists('glpi_softwarelicenses',$kf)){ $keyexpr = "glpi_softwarelicenses.$kf AS lickey"; break; }
}
$select_base[] = $keyexpr;

$expexpr = "'' AS expireon";
foreach (['expire','expiration','end_date','use_end','date_expiration'] as $ef){
   if ($DB->fieldExists('glpi_softwarelicenses',$ef)){ $expexpr = "glpi_softwarelicenses.$ef AS expireon"; break; }
}
$select_base[] = $expexpr;

// Helper to merge
function hello_add_lic($r, $source){
   global $rows, $via;
   $id = (int)$r['licid'];
   if (!isset($rows[$id])){
      $rows[$id] = [
        'licid'=>$id,
        'soft'=>trim((string)$r['softname']),
        'lic' =>trim((string)$r['licname']),
        'ver' =>trim((string)$r['vername']),
        'key' =>trim((string)$r['lickey']),
        'exp' =>trim((string)$r['expireon'])
      ];
   }
   if (!isset($via[$id])) $via[$id] = [];
   if ($source && !in_array($source, $via[$id], true)) $via[$id][] = $source;
}

// Source 1: licenses directly assigned to the user
if ($DB->tableExists('glpi_users_softwarelicenses')){
   $left = ['glpi_softwarelicenses' => ['ON'=>['glpi_users_softwarelicenses'=>'softwarelicenses_id','glpi_softwarelicenses'=>'id']]];
   if (strpos($select_swname, 'glpi_softwares') !== false){
      $left['glpi_softwares'] = ['ON'=>['glpi_softwarelicenses'=>'softwares_id','glpi_softwares'=>'id']];
   }
   if (strpos($vername, 'glpi_softwareversions') !== false){
      $left['glpi_softwareversions'] = ['ON'=>['glpi_softwarelicenses'=>'softwareversions_id_use','glpi_softwareversions'=>'id']];
   }
   $where = ['glpi_users_softwarelicenses.users_id' => $uid];
   if ($DB->fieldExists('glpi_softwarelicenses','is_deleted')) $where['glpi_softwarelicenses.is_deleted'] = 0;
   $q1 = ['SELECT'=>$select_base, 'FROM'=>'glpi_users_softwarelicenses', 'LEFT JOIN'=>$left, 'WHERE'=>$where, 'DISTINCT'=>true];
   foreach ($DB->request($q1) as $r){ hello_add_lic($r, __('User')); }
}

// Collect user's computers (id and name)
$comp_ids = [];
$comp_names = [];
if (isset($computers) && is_array($computers) && count($computers)){
   foreach ($computers as $co){ $cid=(int)$co->fields['id']; $comp_ids[]=$cid; $comp_names[$cid]=$co->fields['name']; }
} elseif ($DB->tableExists('glpi_computers_users')){
   foreach ($DB->request(['SELECT'=>['computers_id'],'FROM'=>'glpi_computers_users','WHERE'=>['users_id'=>$uid]]) as $cr){
      $comp_ids[] = (int)$cr['computers_id'];
   }
   if (count($comp_ids) && $DB->tableExists('glpi_computers')){
      foreach ($DB->request(['SELECT'=>['id','name'],'FROM'=>'glpi_computers','WHERE'=>['id'=>$comp_ids]]) as $cr2){
         $comp_names[(int)$cr2['id']] = $cr2['name'];
      }
   }
}

// Source 2: licenses assigned to those computers
if (count($comp_ids) && $DB->tableExists('glpi_items_softwarelicenses')){
   $left = ['glpi_softwarelicenses'=>['ON'=>['glpi_items_softwarelicenses'=>'softwarelicenses_id','glpi_softwarelicenses'=>'id']]];
   if (strpos($select_swname, 'glpi_softwares') !== false){
      $left['glpi_softwares'] = ['ON'=>['glpi_softwarelicenses'=>'softwares_id','glpi_softwares'=>'id']];
   }
   if (strpos($vername, 'glpi_softwareversions') !== false){
      // pick one probable field for ON; DB driver accepts 'softwareversions_id_use' missing silently if not exists
      $left['glpi_softwareversions'] = ['ON'=>['glpi_softwarelicenses'=>'softwareversions_id_use','glpi_softwareversions'=>'id']];
   }
   if ($DB->tableExists('glpi_computers')){
      $left['glpi_computers'] = ['ON'=>['glpi_items_softwarelicenses'=>'items_id','glpi_computers'=>'id']];
   }
   $where = ['glpi_items_softwarelicenses.itemtype'=>'Computer','glpi_items_softwarelicenses.items_id'=>$comp_ids];
   if ($DB->fieldExists('glpi_softwarelicenses','is_deleted')) $where['glpi_softwarelicenses.is_deleted'] = 0;
   $sel = $select_base;
   if ($DB->tableExists('glpi_computers')){ $sel = array_merge($sel, ["glpi_computers.id AS cid","glpi_computers.name AS cname"]); }
   $q2 = ['SELECT'=>$sel, 'FROM'=>'glpi_items_softwarelicenses', 'LEFT JOIN'=>$left, 'WHERE'=>$where, 'DISTINCT'=>true];
   foreach ($DB->request($q2) as $r){
      $cn = isset($r['cname']) && $r['cname'] !== '' ? $r['cname'] : (isset($r['cid']) && isset($comp_names[(int)$r['cid']]) ? $comp_names[(int)$r['cid']] : '');
      $label = $cn ? __('Computer').' '.h($cn) : __('Computer');
      hello_add_lic($r, $label);
   }
}

// Optional Source 3: group licenses
if ($DB->tableExists('glpi_groups_users') && $DB->tableExists('glpi_items_softwarelicenses')){
   $gid=[];
   foreach ($DB->request(['SELECT'=>['groups_id'],'FROM'=>'glpi_groups_users','WHERE'=>['users_id'=>$uid]]) as $gr){ $gid[]=(int)$gr['groups_id']; }
   if (count($gid)){
      $left = ['glpi_softwarelicenses'=>['ON'=>['glpi_items_softwarelicenses'=>'softwarelicenses_id','glpi_softwarelicenses'=>'id']]];
      if (strpos($select_swname, 'glpi_softwares') !== false){
         $left['glpi_softwares'] = ['ON'=>['glpi_softwarelicenses'=>'softwares_id','glpi_softwares'=>'id']];
      }
      if (strpos($vername, 'glpi_softwareversions') !== false){
         $left['glpi_softwareversions'] = ['ON'=>['glpi_softwarelicenses'=>'softwareversions_id_use','glpi_softwareversions'=>'id']];
      }
      if ($DB->tableExists('glpi_groups')){
         $left['glpi_groups'] = ['ON'=>['glpi_items_softwarelicenses'=>'items_id','glpi_groups'=>'id']];
      }
      $where = ['glpi_items_softwarelicenses.itemtype'=>'Group','glpi_items_softwarelicenses.items_id'=>$gid];
      if ($DB->fieldExists('glpi_softwarelicenses','is_deleted')) $where['glpi_softwarelicenses.is_deleted'] = 0;
      $sel = $select_base;
      if ($DB->tableExists('glpi_groups')){ $sel = array_merge($sel, ["glpi_groups.name AS gname"]); }
      $q3 = ['SELECT'=>$sel, 'FROM'=>'glpi_items_softwarelicenses', 'LEFT JOIN'=>$left, 'WHERE'=>$where, 'DISTINCT'=>true];
      foreach ($DB->request($q3) as $r){
         $glabel = isset($r['gname']) && $r['gname'] !== '' ? __('Group').' '.h($r['gname']) : __('Group');
         hello_add_lic($r, $glabel);
      }
   }
}

// Output
if (!count($rows)){
   echo '<div class="alert alert-info">'.__('No license information found for your assigned assets.','hello').'</div>';
} else {
   echo '<table class="tab_cadre_fixe" style="width:100%;">';
   echo '<tr class="tab_bg_1"><th>'.__('Software name').'</th><th>'.__('License').'</th><th>'.__('License version').'</th><th>'.__('Key','hello').'</th><th>'.__('Expires on','hello').'</th><th>'.__('Assigned via','hello').'</th></tr>';
   foreach ($rows as $licid => $row){
      $soft = $row['soft'] !== '' ? h($row['soft']) : '—';
      $licn = $row['lic']  !== '' ? h($row['lic'])  : '—';
      $vern = $row['ver']  !== '' ? h($row['ver'])  : '—';
      $lkey = $row['key']  !== '' ? h($row['key'])  : '—';
      $expd = $row['exp']  !== '' ? h($row['exp'])  : '—';
      $sources = isset($via[$licid]) && is_array($via[$licid]) ? implode(', ', $via[$licid]) : '—';
      echo '<tr class="tab_bg_1"><td>'.$soft.'</td><td>'.$licn.'</td><td>'.$vern.'</td><td>'.$lkey.'</td><td>'.$expd.'</td><td>'.$sources.'</td></tr>';
   }
   echo '</table>';
}
echo '</div>';
// ----------------- modal JS once, for all thumbnails -----------------
echo '<script>
(function(){
  var overlay = document.getElementById("helloModalOverlay");
  var img     = document.getElementById("helloModalImg");
  var titleEl = document.getElementById("helloModalTitle");

  function openModal(src, fb, titleText){
    titleEl.textContent = titleText || "Model";
    img.removeAttribute("data-fb");
    img.onerror = function(){
      if(!img.dataset.fb && fb){
        img.dataset.fb = "1";
        img.src = fb;
      }
    };
    img.src = src;
    overlay.style.display = "flex";
  }

  function closeModal(){
    overlay.style.display = "none";
    img.removeAttribute("src");
    img.removeAttribute("data-fb");
    img.onerror = null;
  }

  overlay.addEventListener("click", function(e){
    if(e.target === overlay){ closeModal(); }
  });
  overlay.querySelector(".hello-close").addEventListener("click", closeModal);
  document.addEventListener("keydown", function(e){ if(e.key === "Escape") closeModal(); });

  var thumbs = document.querySelectorAll("img.hello-thumb");
  for (var i=0;i<thumbs.length;i++){
    thumbs[i].addEventListener("click", function(ev){
      ev.preventDefault();
      openModal(this.getAttribute("data-full"), this.getAttribute("data-full-fallback"), this.getAttribute("data-title") || "");
    });
  }
})();
</script>';

Html::footer();
