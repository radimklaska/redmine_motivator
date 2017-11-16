<?php

$config = __DIR__ . '/config.php';
if (file_exists($config)) {
  include $config;
}

// Quick check.
if (!defined("REDMINE_API_KEY")) {
  die("No config!\n");
}

$first_day_of_last_month = date("Y-m-d", strtotime("first day of last month"));
$last_day_of_last_month = date("Y-m-d", strtotime("last day of last month"));
$first_day_of_this_month = date("Y-m-d", strtotime("first day of this month"));
$last_day_of_this_month = date("Y-m-d", strtotime("last day of this month"));
$yesterday = date("Y-m-d", strtotime("yesterday"));
$today = date("Y-m-d", strtotime("today"));
$tomorrow = date("Y-m-d", strtotime("tomorrow"));

$money_this_month = redmine_get_money($first_day_of_this_month, $last_day_of_this_month);
$money_last_month = redmine_get_money($first_day_of_last_month, $last_day_of_last_month);
$money_today = redmine_get_money($today, $today);
$hours_today = redmine_get_hours($today, $today);
$hours_yesterday = redmine_get_hours($yesterday, $yesterday);

if (php_sapi_name() == "cli") {
  // In cli-mode
  // Oneliner for use as "always on indicator" in toolbar.
  echo number_format($money_this_month / 1000, 0, ",", " ") . "/" . number_format($money_last_month / 1000, 0, ",", " ") . " | " .
    number_of_working_days($today, $last_day_of_this_month) . "d (" .
    '4:' . number_format(((convert(number_of_working_days($today, $last_day_of_this_month) * 4 * HOUR_RATE) + $money_this_month - $money_today) / 1000), 0, ",", "") . "/" .
    '6:' . number_format(((convert(number_of_working_days($today, $last_day_of_this_month) * 6 * HOUR_RATE) + $money_this_month - $money_today) / 1000), 0, ",", "") . "/" .
    '8:' . number_format(((convert(number_of_working_days($today, $last_day_of_this_month) * 8 * HOUR_RATE) + $money_this_month - $money_today) / 1000), 0, ",", "") . ")" .
    ' ' . number_format((8 - $hours_today), 1, ",", "") . 'h' . "/" .
    number_format((8 - $hours_yesterday), 1, ",", "") . 'h';
}
else {
  // Output with descriptions:
  echo 'Vydelano tento mesic: <b>' . number_format($money_this_month, 0, ",", " ") . " " . FINAL_CURRENCY . "</b><br>" .
    'Vydelano minuly mesic: <b>' . number_format($money_last_month, 0, ",", " ") . " " . FINAL_CURRENCY . "</b><br>" .
    'Zbyvajici pracovni dny: <b>' . number_of_working_days($today, $last_day_of_this_month) . "</b><br>" .
    'Na konci mesice pri tempu 4h/den: <b>' . number_format((convert(number_of_working_days($today, $last_day_of_this_month) * 4 * HOUR_RATE) + $money_this_month - $money_today), 0, ",", " ") . " " . FINAL_CURRENCY . "</b><br>" .
    'Na konci mesice pri tempu 6h/den: <b>' . number_format((convert(number_of_working_days($today, $last_day_of_this_month) * 6 * HOUR_RATE) + $money_this_month - $money_today), 0, ",", " ") . " " . FINAL_CURRENCY . "</b><br>" .
    'Na konci mesice pri tempu 8h/den: <b>' . number_format((convert(number_of_working_days($today, $last_day_of_this_month) * 8 * HOUR_RATE) + $money_this_month - $money_today), 0, ",", " ") . " " . FINAL_CURRENCY . "</b><br>" .
    'Do dnesniho cile zbyva: <b>' . number_format((8 - $hours_today), 1, ",", "") . 'h' . "</b><br>" .
    'Do vcerejsiho cile zbyva: <b>' . number_format((8 - $hours_yesterday), 1, ",", "") . 'h' . "</b><br>";
}

function redmine_get_hours($start, $end) {
  $pager = 100; // no more than 100, redmine forces 100 if you try more.
  $result = redmine_get_time_entries($start, $end, $pager);

  if ((isset($result['total_count']))
    && (isset($result['time_entries']))
    && (isset($result['limit']))
    // Fail if case redmine forces less than $pager.
    && ($result['limit'] == $pager)) {

    $page_count = floor($result['total_count'] / $pager) + 1;

    if ($page_count == 1) {
      $hours = redmine_entries_to_hours($result['time_entries']);
    }
    else {
      $hours = redmine_entries_to_hours($result['time_entries']);
      // We need to start flipping pages.
      for ($page = 2; $page <= $page_count; $page++) {
        $hours = $hours + redmine_entries_to_hours(redmine_get_time_entries($start, $end, $pager, ($page - 1) * $pager)['time_entries']);
      }
    }

    return $hours;
  }
  else {
    return 0;
  }
}

function redmine_get_time_entries($start, $end, $pager = 1, $offset = 0) {
  $url = "https://" . REDMINE_API_KEY . ":DefinitelyNotMyPassword@" . REDMINE_DOMAIN . "/time_entries.json?limit=" . $pager . "&offset=" . $offset . "&user_id=" . REDMINE_USERID . "&spent_on=><" . $start . "|" . $end;

  $curl = curl_init();
  curl_setopt($curl, CURLOPT_URL, $url);
  curl_setopt($curl, CURLOPT_HTTPGET, TRUE);
  curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
  curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 2);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
  $buffer = curl_exec($curl);
  curl_close($curl);

  $json_result = json_decode($buffer, TRUE);

  if ((isset($json_result['total_count'])) && (isset($json_result['time_entries']))) {
    return $json_result;
  }
  else {
    return NULL;
  }
}

function redmine_entries_to_hours($entries) {
  $hours = 0;
  foreach ($entries as $index => $entry) {
    $hours = $hours + $entry['hours'];
  }

  return $hours;
}

function redmine_get_money($start, $end) {
  if ($hours = redmine_get_hours($start, $end)) {
    $rate_currency = $hours * HOUR_RATE;
    return convert($rate_currency);
  }
  else {
    return 0;
  }
}

function number_of_working_days($from, $to) {
  $workingDays = [1, 2, 3, 4, 5]; # date format = N (1 = Monday, ...)
  $holidayDays = [
    '*-12-25',
    '*-01-01',
    '2013-12-23',
  ]; # variable and fixed holidays

  $from = new DateTime($from);
  $to = new DateTime($to);
  $to->modify('+1 day');
  $interval = new DateInterval('P1D');
  $periods = new DatePeriod($from, $interval, $to);

  $days = 0;
  foreach ($periods as $period) {
    if (!in_array($period->format('N'), $workingDays)) {
      continue;
    }
    if (in_array($period->format('Y-m-d'), $holidayDays)) {
      continue;
    }
    if (in_array($period->format('*-m-d'), $holidayDays)) {
      continue;
    }
    $days++;
  }
  return $days;
}

function convert($rate_currency) {
  if (HOUR_RATE_CURRENCY == FINAL_CURRENCY) {
    return $rate_currency;
  }

  $rate = file_get_contents('https://api.fixer.io/latest?base=' . HOUR_RATE_CURRENCY . '&symbols=' . FINAL_CURRENCY);
  $rate = json_decode($rate);
  $rate = $rate->rates->{FINAL_CURRENCY};

  $final_currency = $rate_currency * $rate;
  return $final_currency;
}
