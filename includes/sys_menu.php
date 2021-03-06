<?php

function page_link_to($page = "") {
  if ($page == "") {
    return '?';
  }
  return '?p=' . $page;
}

function page_link_to_absolute($page) {
  return (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . preg_replace("/\?.*$/", '', $_SERVER['REQUEST_URI']) . page_link_to($page);
}

/**
 * Renders the header toolbar containing search, login/logout, user and settings links.
 */
function header_toolbar() {
  global $page, $privileges, $user, $enable_tshirt_size, $max_freeloadable_shifts, $enable_dect, $enable_unnecessary_Notifications;
  
  $toolbar_items = [];
  
  if (isset($user)) {
    $toolbar_items[] = toolbar_item_link(page_link_to('shifts') . '&amp;action=next', 'time', User_shift_state_render($user));
  }
  
  if (! isset($user) && in_array('register', $privileges)) {
    $toolbar_items[] = toolbar_item_link(page_link_to('register'), 'plus', register_title(), $page == 'register');
  }
  
  if (in_array('login', $privileges)) {
    $toolbar_items[] = toolbar_item_link(page_link_to('login'), 'log-in', login_title(), $page == 'login');
  }
  
  if (isset($user) && in_array('user_messages', $privileges)) {
    $toolbar_items[] = toolbar_item_link(page_link_to('user_messages'), 'envelope', user_unread_messages());
  }
  
  $hints = [];
  if (isset($user)) {
    $hint_class = 'info';
    $glyphicon = 'info-sign';
    // Erzengel Hinweis für unbeantwortete Fragen
    if ($page != "admin_questions") {
      $new_questions = admin_new_questions();
      if ($new_questions != "") {
        $hints[] = $new_questions;
      }
    }
    
    $unconfirmed_hint = user_angeltypes_unconfirmed_hint();
    if ($unconfirmed_hint != '') {
      $hints[] = $unconfirmed_hint;
    }
    if ($enable_unnecessary_Notifications) {
    if (! isset($user['planned_departure_date']) || $user['planned_departure_date'] == null) {
      $hints[] = info(_("Please enter your planned date of departure on your settings page to give us a feeling for teardown capacities."), true);
    }
    }
	else
	{}

    $driver_license_required = user_driver_license_required_hint();
    if ($driver_license_required != '') {
      $hints[] = $driver_license_required;
    }
    
    if (User_is_freeloader($user)) {
      $hints[] = error(sprintf(_("You freeloaded at least %s shifts. Shift signup is locked. Please go to the event organization's desk to be unlocked again."), $max_freeloadable_shifts), true);
      $hint_class = 'danger';
      $glyphicon = 'warning-sign';
    }
    
    // Hinweis für Engel, die noch nicht angekommen sind
    if ($enable_unnecessary_Notifications) {
	if ($user['Gekommen'] == 0) {
      $hints[] = error(_("You are not marked as arrived. Please go to the event organization's desk, get your helper badge and/or tell them that you arrived already."), true);
      $hint_class = 'danger';
      $glyphicon = 'warning-sign';
    }
    }
	else
	{}

    if ($enable_tshirt_size && $user['Size'] == "") {
      $hints[] = error(_("You need to specify a tshirt size in your settings!"), true);
      $hint_class = 'danger';
      $glyphicon = 'warning-sign';
    }

    if ($enable_dect) {
      if ($user['DECT'] == "") {
        $hints[] = error(_("You need to specify a DECT phone number in your settings! If you don't have a DECT phone, just enter \"-\"."), true);
        $hint_class = 'danger';
        $glyphicon = 'warning-sign';
      }
    }
  }
  if (count($hints) > 0) {
    $toolbar_items[] = toolbar_popover($glyphicon . ' text-' . $hint_class, '', $hints, 'bg-' . $hint_class);
  }
  
  $user_submenu = make_langselect();
  $user_submenu[] = toolbar_item_divider();
  if (in_array('user_myshifts', $privileges)) {
    $toolbar_items[] = toolbar_item_link(page_link_to('users') . '&amp;action=view', ' icon-icon_angel', $user['Nick'], $page == 'users');
  }
  
  if (in_array('user_settings', $privileges)) {
    $user_submenu[] = toolbar_item_link(page_link_to('user_settings'), 'list-alt', settings_title(), $page == 'user_settings');
  }
  
  if (in_array('logout', $privileges)) {
    $user_submenu[] = toolbar_item_link(page_link_to('logout'), 'log-out', logout_title(), $page == 'logout');
  }
  
  if (count($user_submenu) > 0) {
    $toolbar_items[] = toolbar_dropdown('', '', $user_submenu);
  }
  
  return toolbar($toolbar_items, true);
}

function make_navigation() {
  global $page, $privileges;
  
  $menu = [];
  $pages = [
      "news" => news_title(),
      "user_meetings" => meetings_title(),
      "user_shifts" => shifts_title(),
      "angeltypes" => angeltypes_title(),
      "user_questions" => questions_title() 
  ];
  
  foreach ($pages as $menu_page => $title) {
    if (in_array($menu_page, $privileges)) {
      $menu[] = toolbar_item_link(page_link_to($menu_page), '', $title, $menu_page == $page);
    }
  }
  
  $admin_menu = [];
  $admin_pages = [
      "admin_arrive" => admin_arrive_title(),
      "admin_active" => admin_active_title(),
      "admin_user" => admin_user_title(),
      "admin_free" => admin_free_title(),
      "admin_questions" => admin_questions_title(),
      "shifttypes" => shifttypes_title(),
      "admin_shifts" => admin_shifts_title(),
      "admin_rooms" => admin_rooms_title(),
      "admin_groups" => admin_groups_title(),
      "admin_import" => admin_import_title(),
      "admin_log" => admin_log_title(),
      "admin_event_config" => event_config_title() 
  ];
  
  foreach ($admin_pages as $menu_page => $title) {
    if (in_array($menu_page, $privileges)) {
      $admin_menu[] = toolbar_item_link(page_link_to($menu_page), '', $title, $menu_page == $page);
    }
  }
  
  if (count($admin_menu) > 0) {
    $menu[] = toolbar_dropdown('', _("Admin"), $admin_menu);
  }
  
  return toolbar($menu);
}

function make_menu() {
  return make_navigation();
}

?>
