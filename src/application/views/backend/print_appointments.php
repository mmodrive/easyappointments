<?php
 $conn = mysqli_connect(Config::DB_HOST, Config::DB_USERNAME, Config::DB_PASSWORD, Config::DB_NAME);
 
 $post_at = "";
 $post_at_to_date = "";
 
 $queryCondition = "";
 if(!empty($_POST["search"]["post_at"])) {   
  $post_at = $_POST["search"]["post_at"];
  list($fid,$fim,$fiy) = explode("/",$post_at);
  
  $post_at_todate = date('Y-m-d');
  if(!empty($_POST["search"]["post_at_to_date"])) {
   $post_at_to_date = $_POST["search"]["post_at_to_date"];
   list($tid,$tim,$tiy) = explode("/",$_POST["search"]["post_at_to_date"]);
   $post_at_todate = "$tiy-$tim-$tid";
  }

  $service = $_POST["search"]["service"];
  $provider = $_POST["search"]["provider"];
  
  $queryCondition .= "WHERE start_datetime BETWEEN '$fiy-$fim-$fid 00:00:00' AND '" . $post_at_todate . " 23:59:59'";
  if ($service && $service !== "all")
    $queryCondition .= " AND id_services = " . $service;
  if ($provider && $provider !== "all")
    $queryCondition .= " AND id_users_provider = " . $provider;
 }
 $sql = "SELECT ea_appointments.*, customer.*, CONCAT(customer.first_name, \" \", customer.last_name) customer_name, ea_appointments.notes appointment_notes, CONCAT(provider.first_name, \" \", provider.last_name) provider_name, service.name service_name FROM ea_appointments INNER JOIN ea_users AS customer ON ea_appointments.id_users_customer=customer.id INNER JOIN ea_users AS provider ON ea_appointments.id_users_provider=provider.id INNER JOIN ea_services AS service ON ea_appointments.id_services=service.id " . $queryCondition . " ORDER BY service.name, start_datetime asc";
 
//echo $sql;
?>
<html>
<head>
<title>Print Appointments</title>  
<!-- <script src="http://code.jquery.com/jquery-1.9.1.js"></script> -->
    <script src="<?= asset_url('assets/ext/jquery/jquery.min.js') ?>"></script>
<script src="<?= asset_url('assets/ext/jquery-ui/jquery-ui.min.js') ?>"></script>
<link rel="stylesheet" href="http://code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css">
<style>
.table, tr, th, td{border: 1px solid black; font-size: 14px;}
.table-content{ width:80%;}
.table-content tr:hover {background-color: #f5f5f5;}
.table-content th {padding:5px 20px; background: #F0F0F0;vertical-align:top;text-align: left;background-color: #4CAF50;
color: white;}
.table-content td {padding:5px 20px; vertical-align:top;}
#customers {
  font-family: "Trebuchet MS", Arial, Helvetica, sans-serif;
  border-collapse: collapse;
  width: 80%;
}
#customers td, #customers th {
  border: 1px solid #ddd;
  padding: 8px;
}
#customers tr:nth-child(even){background-color: #f2f2f2;}
#customers tr:hover {background-color: #ddd;}
#customers th {
  padding-top: 12px;
  padding-bottom: 12px;
  text-align: left;
  background-color: #4CAF50;
  color: black;
}
@media print
{   
    .no-print, .no-print *
    {
        display: none !important;
    }
    h2 { 
        page-break-before: always;
    }
}
table.center {
    margin-left:auto;
    margin-right:auto;
  }
.returned-content{text-align: center;}
#footer {
    position: fixed;
    bottom: 100;
    width: 100%;
    text-align: center;
}
#nav-block{display:inline-block;}
.button {
  padding: 15px 25px;
  font-size: 24px;
  text-align: center;
  cursor: pointer;
  outline: none;
  color: #fff;
  background-color: #4CAF50;
  border: none;
  border-radius: 15px;
  box-shadow: 0 9px #999;
}
.button:hover {background-color: #3e8e41;}
.button:active {
  background-color: #3e8e41;
  box-shadow: 0 5px #666;
  transform: translateY(4px);
}
h2 {
  font-size: 18px;
}
</style>
<script>
$.datepicker.setDefaults({
dateFormat: 'dd/mm/yy' 
});
$(function() {
$("#post_at").datepicker();
$("#post_at_to_date").datepicker();
});
</script>
</head>
 
<body>
<div class="returned-content">
<div class="no-print"><h4>Please select date range of bookings</h4> 
  <!-- <form name="frmSearch" method="post" action="" > -->
 <?php echo form_open('') ?>
  <p class="search_input">
   <input type="text" placeholder="From Date" id="post_at" name="search[post_at]"  value="<?php echo $post_at; ?>" class="input-control" />
   <input type="text" placeholder="To Date" id="post_at_to_date" name="search[post_at_to_date]" style="margin-left:10px"  value="<?php echo $post_at_to_date; ?>" class="input-control"  />   
   <select name="search[service]" class="input-control">
    <option value="all">All Services</option>
   <?php
    $service_result = mysqli_query($conn,"SELECT id, name FROM ea_services");
    while ($row = $service_result->fetch_assoc()) {
      echo '<option value="' . $row['id'] . '" ' . ($row['id'] == $service ? 'selected' : '') . '>' . $row['name'] . '</option>';
    }
    ?>
   </select>
   <select name="search[provider]" class="input-control">
    <option value="all">All Providers</option>
   <?php
    $provider_result = mysqli_query($conn,"SELECT user.id, CONCAT(user.first_name, \" \", user.last_name) as name FROM ea_users user WHERE user.id IN(SELECT id_users FROM ea_services_providers)");
    while ($row = $provider_result->fetch_assoc()) {
      echo '<option value="' . $row['id'] . '" ' . ($row['id'] == $provider ? 'selected' : '') . '>' . $row['name'] . '</option>';
    }
    ?>
   </select>
   <input type="submit" name="go" value="Search" >
  </p>
 </form> 
</div>
<?php
if( isset($_POST["search"]["post_at"]) )
{
$result = mysqli_query($conn,$sql);
};
$service_name = null;
if(!empty($result)){
  if (mysqli_query($conn,$sql)) {
    while ($row = $result->fetch_assoc()) {

$new_service = $service_name !== $row['service_name'];
if($new_service && !is_null($service_name))
  echo '</tbody></table>';
$service_name = $row['service_name'];
if($new_service)
{
  echo '<h2 class=""> ' . $service_name . ' (' . $row['provider_name'] . ') bookings between ' . $_POST["search"]["post_at"] . ' and ' . $_POST["search"]["post_at_to_date"] . '</h2>';
?>
<table id="customers" class="center">
    <thead>
  <tr>                     
   <th width="15%"><span>Date</span></th>
   <th width="15%"><span>Start Time</span></th>
   <th width="15%"><span>End Time</span></th>
   <th width="30%"><span>Name</span></th>
   <th width="15%"><span>Phone</span></th>         
   <th width="20%"><span>Dogs Name</span></th>  
        </tr>
    </thead>
 <tbody>
<?php   
}
   $start_date = strtotime($row["start_datetime"]);
   $end_date = strtotime($row["end_datetime"]);
   $field1name = $row["customer_name"];
   $field2name = $row["phone_number"];
   $field3name = date('d/m/y',$start_date);
   $field4name = date('h:i',$start_date);
   $field5name = date('h:i',$end_date);
   $field6name = $row["appointment_notes"];
        
       echo '<tr>
              <td>'.$field3name.'</td>
              <td>'.$field4name.'</td>
              <td>'.$field5name.'</td>
              <td>'.$field1name.'</td>
              <td>'.$field2name.'</td>
              <td>'.$field6name.'</td>
            </tr>';
}
 $result->free();
}
 
?>
</tbody>
</table>
<?php } ?>
 
</div>
<div id="footer" class="no-print">
 <div id="nav-block">
  <button class="button" onclick="window.print();">Print bookings</button>
  <button class="button" onclick="window.location.href='/index.php/backend';">Go back</button>
 </div> 
</div>

</body>
</html>