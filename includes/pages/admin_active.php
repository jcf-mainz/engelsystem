<?php

function admin_active_title() {
  return _("Active helpers");
}

function admin_active() {
  global $tshirt_sizes, $shift_sum_formula;
  
  $msg = "";
  $search = "";
  $forced_count = sql_num_query("SELECT * FROM `User` WHERE `force_active`=1");
  $count = $forced_count;
  $limit = "";
  $set_active = "";
  
  if (isset($_REQUEST['search'])) {
    $search = strip_request_item('search');
  }
  
  $show_all_shifts = isset($_REQUEST['show_all_shifts']);
  
  if (isset($_REQUEST['set_active'])) {
    $valid = true;
    
    if (isset($_REQUEST['count']) && preg_match("/^[0-9]+$/", $_REQUEST['count'])) {
      $count = strip_request_item('count');
      if ($count < $forced_count) {
        error(sprintf(_("At least %s helpers are forced to be active. The number has to be greater."), $forced_count));
        redirect(page_link_to('admin_active'));
      }
    } else {
      $valid = false;
      $msg .= error(_("Please enter a number of helpers to be marked as active."), true);
    }
    
    if ($valid) {
      $limit = " LIMIT " . $count;
    }
    if (isset($_REQUEST['ack'])) {
      sql_query("UPDATE `User` SET `Aktiv` = 0 WHERE `Tshirt` = 0");
      $users = sql_select("
          SELECT `User`.*, COUNT(`ShiftEntry`.`id`) as `shift_count`, $shift_sum_formula as `shift_length` 
          FROM `User` 
          LEFT JOIN `ShiftEntry` ON `User`.`UID` = `ShiftEntry`.`UID` 
          LEFT JOIN `Shifts` ON `ShiftEntry`.`SID` = `Shifts`.`SID` 
          WHERE `User`.`Gekommen` = 1 AND `User`.`force_active`=0 
          GROUP BY `User`.`UID` 
          ORDER BY `force_active` DESC, `shift_length` DESC" . $limit);
      $user_nicks = [];
      foreach ($users as $usr) {
        sql_query("UPDATE `User` SET `Aktiv` = 1 WHERE `UID`='" . sql_escape($usr['UID']) . "'");
        $user_nicks[] = User_Nick_render($usr);
      }
      sql_query("UPDATE `User` SET `Aktiv`=1 WHERE `force_active`=TRUE");
      engelsystem_log("These helpers are active now: " . join(", ", $user_nicks));
      
      $limit = "";
      $msg = success(_("Marked helpers."), true);
    } else {
      $set_active = '<a href="' . page_link_to('admin_active') . '&amp;serach=' . $search . '">&laquo; ' . _("back") . '</a> | <a href="' . page_link_to('admin_active') . '&amp;search=' . $search . '&amp;count=' . $count . '&amp;set_active&amp;ack">' . _("apply") . '</a>';
    }
  }
  
  if (isset($_REQUEST['active']) && preg_match("/^[0-9]+$/", $_REQUEST['active'])) {
    $user_id = $_REQUEST['active'];
    $user_source = User($user_id);
    if ($user_source != null) {
      sql_query("UPDATE `User` SET `Aktiv`=1 WHERE `UID`='" . sql_escape($user_id) . "' LIMIT 1");
      engelsystem_log("User " . User_Nick_render($user_source) . " is working now.");
      $msg = success(_("Helper has been marked as working."), true);
    } else {
      $msg = error(_("Helper not found."), true);
    }
  } elseif (isset($_REQUEST['not_active']) && preg_match("/^[0-9]+$/", $_REQUEST['not_active'])) {
    $user_id = $_REQUEST['not_active'];
    $user_source = User($user_id);
    if ($user_source != null) {
      sql_query("UPDATE `User` SET `Aktiv`=0 WHERE `UID`='" . sql_escape($user_id) . "' LIMIT 1");
      engelsystem_log("User " . User_Nick_render($user_source) . " is NOT working now.");
      $msg = success(_("Helper has been marked as not working."), true);
    } else {
      $msg = error(_("Helper not found."), true);
    }
  } elseif (isset($_REQUEST['tshirt']) && preg_match("/^[0-9]+$/", $_REQUEST['tshirt'])) {
    $user_id = $_REQUEST['tshirt'];
    $user_source = User($user_id);
    if ($user_source != null) {
      sql_query("UPDATE `User` SET `Tshirt`=1 WHERE `UID`='" . sql_escape($user_id) . "' LIMIT 1");
      engelsystem_log("User " . User_Nick_render($user_source) . " has tshirt now.");
      $msg = success(_("Helper has got a t-shirt."), true);
    } else {
      $msg = error("Helper not found.", true);
    }
  } elseif (isset($_REQUEST['not_tshirt']) && preg_match("/^[0-9]+$/", $_REQUEST['not_tshirt'])) {
    $user_id = $_REQUEST['not_tshirt'];
    $user_source = User($user_id);
    if ($user_source != null) {
      sql_query("UPDATE `User` SET `Tshirt`=0 WHERE `UID`='" . sql_escape($user_id) . "' LIMIT 1");
      engelsystem_log("User " . User_Nick_render($user_source) . " has NO tshirt.");
      $msg = success(_("Helper has got no t-shirt."), true);
    } else {
      $msg = error(_("Helper not found."), true);
    }
  }
  
  $users = sql_select("
      SELECT `User`.*, COUNT(`ShiftEntry`.`id`) as `shift_count`, $shift_sum_formula as `shift_length` 
      FROM `User` LEFT JOIN `ShiftEntry` ON `User`.`UID` = `ShiftEntry`.`UID` 
      LEFT JOIN `Shifts` ON `ShiftEntry`.`SID` = `Shifts`.`SID` 
      WHERE `User`.`Gekommen` = 1
      " . ($show_all_shifts ? "" : "AND (`Shifts`.`end` < " . time() . " OR `Shifts`.`end` IS NULL)") . "
      GROUP BY `User`.`UID` 
      ORDER BY `force_active` DESC, `shift_length` DESC" . $limit);
  
  $matched_users = [];
  if ($search == "") {
    $tokens = [];
  } else {
    $tokens = explode(" ", $search);
  }
  foreach ($users as &$usr) {
    if (count($tokens) > 0) {
      $match = false;
      foreach ($tokens as $t) {
        if (stristr($usr['Nick'], trim($t))) {
          $match = true;
          break;
        }
      }
      if (! $match) {
        continue;
      }
    }
    $usr['nick'] = User_Nick_render($usr);
    $usr['shirt_size'] = $tshirt_sizes[$usr['Size']];
    $usr['work_time'] = round($usr['shift_length'] / 60) . ' min (' . round($usr['shift_length'] / 3600) . ' h)';
    $usr['active'] = glyph_bool($usr['Aktiv'] == 1);
    $usr['force_active'] = glyph_bool($usr['force_active'] == 1);
    $usr['tshirt'] = glyph_bool($usr['Tshirt'] == 1);
    
    $actions = [];
    if ($usr['Aktiv'] == 0) {
      $actions[] = '<a href="' . page_link_to('admin_active') . '&amp;active=' . $usr['UID'] . ($show_all_shifts ? '&amp;show_all_shifts=' : '') . '&amp;search=' . $search . '">' . _("set active") . '</a>';
    }
    if ($usr['Aktiv'] == 1 && $usr['Tshirt'] == 0) {
      $actions[] = '<a href="' . page_link_to('admin_active') . '&amp;not_active=' . $usr['UID'] . ($show_all_shifts ? '&amp;show_all_shifts=' : '') . '&amp;search=' . $search . '">' . _("remove active") . '</a>';
      $actions[] = '<a href="' . page_link_to('admin_active') . '&amp;tshirt=' . $usr['UID'] . ($show_all_shifts ? '&amp;show_all_shifts=' : '') . '&amp;search=' . $search . '">' . _("got t-shirt") . '</a>';
    }
    if ($usr['Tshirt'] == 1) {
      $actions[] = '<a href="' . page_link_to('admin_active') . '&amp;not_tshirt=' . $usr['UID'] . ($show_all_shifts ? '&amp;show_all_shifts=' : '') . '&amp;search=' . $search . '">' . _("remove t-shirt") . '</a>';
    }
    
    $usr['actions'] = join(' ', $actions);
    
    $matched_users[] = $usr;
  }
  
  $shirt_statistics = [];
  foreach (array_keys($tshirt_sizes) as $size) {
    if ($size != '') {
      $shirt_statistics[] = [
          'size' => $size,
          'needed' => sql_select_single_cell("SELECT count(*) FROM `User` WHERE `Size`='" . sql_escape($size) . "' AND `Gekommen`=1"),
          'given' => sql_select_single_cell("SELECT count(*) FROM `User` WHERE `Size`='" . sql_escape($size) . "' AND `Tshirt`=1") 
      ];
    }
  }
  $shirt_statistics[] = [
      'size' => '<b>' . _("Sum") . '</b>',
      'needed' => '<b>' . User_arrived_count() . '</b>',
      'given' => '<b>' . sql_select_single_cell("SELECT count(*) FROM `User` WHERE `Tshirt`=1") . '</b>' 
  ];
  
  return page_with_title(admin_active_title(), [
      form([
          form_text('search', _("Search helper:"), $search),
          form_checkbox('show_all_shifts', _("Show all shifts"), $show_all_shifts),
          form_submit('submit', _("Search")) 
      ], page_link_to('admin_active')),
      $set_active == "" ? form([
          form_text('count', _("How much helpers should be active?"), $count),
          form_submit('set_active', _("Preview")) 
      ]) : $set_active,
      msg(),
      table([
          'nick' => _("Nickname"),
          'shirt_size' => _("Size"),
          'shift_count' => _("Shifts"),
          'work_time' => _("Length"),
          'active' => _("Active?"),
          'force_active' => _("Forced"),
          'tshirt' => _("T-shirt?"),
          'actions' => "" 
      ], $matched_users),
      '<h2>' . _("Shirt statistics") . '</h2>',
      table([
          'size' => _("Size"),
          'needed' => _("Needed shirts"),
          'given' => _("Given shirts") 
      ], $shirt_statistics) 
  ]);
}
?>
