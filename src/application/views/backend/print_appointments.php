<script src="<?= asset_url('assets/ext/jquery-ui/jquery-ui-timepicker-addon.js') ?>"></script>
<script>
    var GlobalVariables = {
        csrfToken          : <?= json_encode($this->security->get_csrf_hash()) ?>,
        dateFormat         : <?= json_encode($date_format) ?>,
        timeFormat         : <?= json_encode($time_format) ?>,
        baseUrl            : <?= json_encode($base_url) ?>,
        user               : {
            id         : <?= $user_id ?>,
            email      : <?= json_encode($user_email) ?>,
            role_slug  : <?= json_encode($role_slug) ?>,
            privileges : <?= json_encode($privileges) ?>
        },
        availableProviders : <?= json_encode($available_providers) ?>,
    };

    $(document).ready(function() {
      $(function() {
        switch (GlobalVariables.dateFormat) {
            case 'DMY':
                dateFormat = 'dd/mm/yy';
                break;
            case 'MDY':
                dateFormat = 'mm/dd/yy';
                break;
            case 'YMD':
                dateFormat = 'yy/mm/dd';
                break;
            default:
                throw new Error('Invalid GlobalVariables.dateFormat value.');
        }

        $(".input-date").datepicker({
            dateFormat: dateFormat,
            timeFormat: GlobalVariables.timeFormat === 'regular' ? 'h:mm TT' : 'HH:mm',
            changeYear: true,
            changeMonth: true,
            yearRange: "-10:+0",

            // Translation
            dayNames: [EALang.sunday, EALang.monday, EALang.tuesday, EALang.wednesday,
                EALang.thursday, EALang.friday, EALang.saturday],
            dayNamesShort: [EALang.sunday.substr(0, 3), EALang.monday.substr(0, 3),
                EALang.tuesday.substr(0, 3), EALang.wednesday.substr(0, 3),
                EALang.thursday.substr(0, 3), EALang.friday.substr(0, 3),
                EALang.saturday.substr(0, 3)],
            dayNamesMin: [EALang.sunday.substr(0, 2), EALang.monday.substr(0, 2),
                EALang.tuesday.substr(0, 2), EALang.wednesday.substr(0, 2),
                EALang.thursday.substr(0, 2), EALang.friday.substr(0, 2),
                EALang.saturday.substr(0, 2)],
            monthNames: [EALang.january, EALang.february, EALang.march, EALang.april,
                EALang.may, EALang.june, EALang.july, EALang.august, EALang.september,
                EALang.october, EALang.november, EALang.december],
            prevText: EALang.previous,
            nextText: EALang.next,
            currentText: EALang.now,
            closeText: EALang.close,
            firstDay: 0
        });
      });

      $('#footer').addClass('no-print');
    });
</script>

<style>
.print {
  font-size: 14px;
  font-family: "Trebuchet MS", Arial, Helvetica, sans-serif;
  border-collapse: collapse;
  width: 80%;
  margin: 0 auto;
}
.print td, .print th {
  border: 1px solid #ddd;
  padding: 8px;
  text-align: center;
}
.print td {
  white-space: nowrap;
} 
.print tr td:nth-child(6) {
  text-align: left;
  white-space: normal;
}
.print tr td:nth-child(7) {
  text-align: left;
  white-space: normal;
  width: 100%;
}
.print tr td:nth-child(5){
}
.print th {
  padding-top: 12px;
  padding-bottom: 12px;
  background-color: #4CAF50;
  color: white;
}
.print tr:nth-child(even){background-color: #f2f2f2;}
.print tr:hover {background-color: #ddd;}
.print tr td:nth-child(n+8), .print tr th:nth-child(n+8) {
    display: none;
}
@media print
{   
    .print {
        width: 100%;
    }
    .no-print, .no-print *
    {
        display: none;
    }
    .print tr td:nth-child(n+8), .print tr th:nth-child(n+8) {
        display: table-cell;
        width: 100px;
    }
    h2 { 
        page-break-before: always;
    }
}
.returned-content{text-align: center;}
h2 {
  font-size: 1.5em;
}
</style>
<div class="returned-content">
  <div class="no-print">
    <h4>Please select date range of bookings</h4> 
    <!-- <form name="frmSearch" method="post" action="" > -->
   <?php echo form_open('') ?>
    <p class="search_input">
     <input type="text" placeholder="From Date" name="post_at" value="<?= $post_at ?? ''; ?>" autocomplete="off" class="input-control input-date" />
     <input type="text" placeholder="To Date" name="post_at_to_date" style="margin-left:10px" value="<?= $post_at_to_date ?? ''; ?>" class="input-control input-date" autocomplete="off" />   
     <select name="service" class="input-control">
      <option value="all">All Services</option>
     <?php
      foreach ($available_services as $iservice) {
        echo '<option value="' . $iservice['id'] . '" ' . ($iservice['id'] == $service ? 'selected' : '') . '>' . $iservice['name'] . '</option>';
      }
      ?>
     </select>
     <select name="provider" class="input-control">
      <option value="all">All Providers</option>
     <?php
      foreach ($available_providers as $iprovider) {
        echo '<option value="' . $iprovider['id'] . '" ' . ($iprovider['id'] == $provider ? 'selected' : '') . '>' . $iprovider['first_name'] . ' ' . $iprovider['last_name'] . '</option>';
      }
      ?>
     </select>
     <input type="submit" name="search" value="Search" >
    </p>
   </form> 
   <button class="button" onclick="window.print();">Print bookings</button>
  </div>
<?php
$service_provider_name = null;
$prev_sp_appointment = null;
if(!empty($appointments)){
    foreach ($appointments as $appointment) {

$new_service_provider = $service_provider_name !== ($appointment['service_name'] . '|' . $appointment['provider_name']);
if($new_service_provider && !is_null($service_provider_name)) {
  echo '</tbody></table>';
  $prev_sp_appointment = null;
}
$service_provider_name = $appointment['service_name'] . '|' . $appointment['provider_name'];
if($new_service_provider)
{
  echo '<h2 class=""> ' . $appointment['service_name'] . ' (' . $appointment['provider_name'] . ') bookings between ' . $post_at . ' and ' . $post_at_to_date . '</h2>';
?>
  <table class="center print">
    <thead>
      <tr>
        <th><span><?= lang('date') ?></span></th>
        <th><span><?= lang('time_start') ?></span></th>
        <th><span><?= lang('time_end') ?></span></th>
        <th><span><?= lang('duration') ?> <?= lang('minutes') ?></span></th>
        <th><span><?= lang('customer') ?></span></th>
        <th><span><?= lang('phone_number') ?></span></th>
        <th><span><?= lang('pet') ?></span></th>
        <th><span><?= lang('payment_amount') ?></span></th>
        <th><span><?= lang('payment_type') ?></span></th>
      </tr>
    </thead>
    <tbody>
<?php   
}
  if ($prev_sp_appointment && date($php_date_format,strtotime($prev_sp_appointment["start_datetime"])) === date($php_date_format, strtotime($appointment["start_datetime"])) ) {
      $wp = json_decode($appointment["provider_working_plan"]);
      $dow = strtolower(date("l", strtotime($appointment["start_datetime"])));
      if ( property_exists($wp, $dow) && !empty($wp->$dow->breaks) ) {
        $previous_end = strtotime($prev_sp_appointment["end_datetime"]);
        $current_start = strtotime($appointment["start_datetime"]);
        $breaks = array_filter($wp->$dow->breaks, function($break) use($previous_end,$current_start,$php_date_format) {
          $puredate = date_time_set(date_timestamp_set(date_create(), $previous_end),0,0);
          return $previous_end <= date_timestamp_get(date_modify($puredate,$break->start)) && date_timestamp_get(date_modify($puredate,$break->end)) <= $current_start;
        });
        if ($breaks) {
          $break = reset($breaks);
          echo '<tr>
                <td>'.date($php_date_format,strtotime($appointment["start_datetime"])).'</td>
                <td>'.date($php_time_format,strtotime($break->start)).'</td>
                <td>'.date($php_time_format,strtotime($break->end)).'</td>
                <td>'.round((strtotime($appointment["end_datetime"])-strtotime($appointment["start_datetime"]))/60,2).'</td>
                <td>Break</td>
                <td>--</td>
                <td>--</td>
                <td></td>
                <td></td>
              </tr>';
        }
      }
  }
  echo '<tr>
        <td>'.date($php_date_format,strtotime($appointment["start_datetime"])).'</td>
        <td>'.date($php_time_format,strtotime($appointment["start_datetime"])).'</td>
        <td>'.date($php_time_format,strtotime($appointment["end_datetime"])).'</td>
        <td>'.round((strtotime($appointment["end_datetime"])-strtotime($appointment["start_datetime"]))/60,2).'</td>
        <td>'.($appointment["customer_name"] ?? '').'</td>
        <td>'.($appointment["phone_number"] ?? '').'</td>
        <td>'.($appointment["pet_title"] ?? '').'</td>
        <td>'.($appointment["app_discount"] ? "FREE" : "").'</td>
        <td></td>
      </tr>';
  $prev_sp_appointment = $appointment;
}
 
?>
    </tbody>
  </table>
<?php } ?>
 
</div>