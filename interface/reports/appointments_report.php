<?php
 // Copyright (C) 2005-2010 Rod Roark <rod@sunsetsystems.com>
 //
 // This program is free software; you can redistribute it and/or
 // modify it under the terms of the GNU General Public License
 // as published by the Free Software Foundation; either version 2
 // of the License, or (at your option) any later version.

 // This report shows upcoming appointments with filtering and
 // sorting by patient, practitioner, appointment type, and date.

require_once("../globals.php");
require_once("../../library/patient.inc");
require_once("$srcdir/formatting.inc.php");

 $alertmsg = ''; // not used yet but maybe later

 // For each sorting option, specify the ORDER BY argument.
 //

 $ORDERHASH = array(
  'doctor'  => 'lower(u.lname), lower(u.fname), pc_eventDate, pc_startTime',
  'patient' => 'lower(p.lname), lower(p.fname), pc_eventDate, pc_startTime',
  'pubpid'  => 'lower(p.pubpid), pc_eventDate, pc_startTime',
  'time'    => 'pc_eventDate, pc_startTime, lower(u.lname), lower(u.fname)',
  'type'    => 'pc_catname, pc_eventDate, pc_startTime, lower(u.lname), lower(u.fname)'
 );

 $patient = $_REQUEST['patient'];

 if ($patient && ! $_POST['form_from_date']) {
  // If a specific patient, default to 2 years ago.
  $tmp = date('Y') - 2;
  $from_date = date("$tmp-m-d");
 } else {
  $from_date = fixDate($_POST['form_from_date'], date('Y-m-d'));
  $to_date = fixDate($_POST['form_to_date'], date('Y-m-d'));
 }

 //$to_date   = fixDate($_POST['form_to_date'], '');
 $provider  = $_POST['form_provider'];
 $facility  = $_POST['form_facility'];  //(CHEMED) facility filter

 $form_orderby = $ORDERHASH[$_REQUEST['form_orderby']] ?  $_REQUEST['form_orderby'] : 'time';

 $orderby = $ORDERHASH[$form_orderby];

 $where = "e.pc_pid != '' AND e.pc_eventDate >= '$from_date'";

 if ($to_date ) $where .= " AND e.pc_eventDate <= '$to_date'";
 if ($provider) $where .= " AND e.pc_aid = '$provider'";

 //(CHEMED) facility filter
 $facility_filter = '';
 if ($facility) {
     $event_facility_filter = " AND e.pc_facility = '$facility'";
     $provider_facility_filter = " AND users.facility_id = '$facility'";
 }
 //END (CHEMED)

 if ($patient ) $where .= " AND e.pc_pid = '$patient'";

 // Get the info.
 //

 $query = "SELECT " .
  "e.pc_eventDate, e.pc_startTime, e.pc_catid, e.pc_eid, " .
  "p.fname, p.mname, p.lname, p.pid, p.pubpid, " .
  "u.fname AS ufname, u.mname AS umname, u.lname AS ulname, " .
  "c.pc_catname " .
  "FROM openemr_postcalendar_events AS e " .
  "LEFT OUTER JOIN patient_data AS p ON p.pid = e.pc_pid " .
  "LEFT OUTER JOIN users AS u ON u.id = e.pc_aid " .
  "LEFT OUTER JOIN openemr_postcalendar_categories AS c ON c.pc_catid = e.pc_catid " .
  "WHERE $where $event_facility_filter ORDER BY $orderby";  //(CHEMED) facility filter

 $res = sqlStatement($query);

?>

<html>

<head>
<?php html_header_show();?>

<link rel="stylesheet" href="<?php echo $css_header;?>" type="text/css">

<title><?php xl('Appointments Report','e'); ?></title>

<script type="text/javascript" src="../../library/overlib_mini.js"></script>
<script type="text/javascript" src="../../library/textformat.js"></script>
<script type="text/javascript" src="../../library/dialog.js"></script>
<script type="text/javascript" src="../../library/js/jquery.1.3.2.js"></script>

<script LANGUAGE="JavaScript">

 var mypcc = '<?php echo $GLOBALS['phone_country_code'] ?>';

 function dosort(orderby) {
    var f = document.forms[0];
    f.form_orderby.value = orderby;
    f.submit();
    return false;
 }

 function oldEvt(eventid) {
    dlgopen('../main/calendar/add_edit_event.php?eid=' + eventid, 'blank', 550, 270);
 }

 function refreshme() {
    // location.reload();
    document.forms[0].submit();
 }

</script>

<style type="text/css">

/* specifically include & exclude from printing */
@media print {
    #report_parameters {
        visibility: hidden;
        display: none;
    }
    #report_parameters_daterange {
        visibility: visible;
        display: inline;
    }
    #report_results table {
       margin-top: 0px;
    }
}

/* specifically exclude some from the screen */
@media screen {
    #report_parameters_daterange {
        visibility: hidden;
        display: none;
    }
}

</style>
</head>

<body class="body_top">

<!-- Required for the popup date selectors -->
<div id="overDiv" style="position:absolute; visibility:hidden; z-index:1000;"></div>

<span class='title'><?php xl('Report','e'); ?> - <?php xl('Appointments','e'); ?></span>

<div id="report_parameters_daterange">
<?php echo date("d F Y", strtotime($form_from_date)) ." &nbsp; to &nbsp; ". date("d F Y", strtotime($form_to_date)); ?>
</div>

<form method='post' name='theform' id='theform' action='appointments_report.php'>
<form name='theform' id='theform' method='post' action='referrals_report.php'>

<div id="report_parameters">

<table>
 <tr>
  <td width='470px'>
	<div style='float:left'>

	<table class='text'>
		<tr>
			<td class='label'>
				<?php xl('Facility','e'); ?>:
			</td>
			<td>
				<?php
				 // Build a drop-down list of facilities.
				 //
				 $query = "SELECT id, name FROM facility ORDER BY name";
				 $fres = sqlStatement($query);
				 echo "   <select name='form_facility'>\n";
				 echo "    <option value=''>-- " . xl('All Facilities') . " --\n";
				 while ($frow = sqlFetchArray($fres)) {
				  $facid = $frow['id'];
				  echo "    <option value='$facid'";
				  if ($facid == $form_facility) echo " selected";
				  echo ">" . $frow['name'] . "\n";
				 }
				 echo "    <option value='0'";
				 if ($form_facility === '0') echo " selected";
				 echo ">-- " . xl('Unspecified') . " --\n";
				 echo "   </select>\n";
				?>
			</td>
			<td class='label'>
			   <?php xl('Provider','e'); ?>:
			</td>
			<td>
				<?php

				 // Build a drop-down list of providers.
				 //

				 $query = "SELECT id, lname, fname FROM users WHERE ".
				  "authorized = 1 $provider_facility_filter ORDER BY lname, fname"; //(CHEMED) facility filter

				 $ures = sqlStatement($query);

				 echo "   <select name='form_provider'>\n";
				 echo "    <option value=''>-- " . xl('All') . " --\n";

				 while ($urow = sqlFetchArray($ures)) {
				  $provid = $urow['id'];
				  echo "    <option value='$provid'";
				  if ($provid == $_POST['form_provider']) echo " selected";
				  echo ">" . $urow['lname'] . ", " . $urow['fname'] . "\n";
				 }

				 echo "   </select>\n";

				?>
			</td>
		</tr>
		<tr>
			<td class='label'>
			   <?php xl('From','e'); ?>:
			</td>
			<td>
			   <input type='text' name='form_from_date' id="form_from_date" size='10' value='<?php echo $form_from_date ?>'
				onkeyup='datekeyup(this,mypcc)' onblur='dateblur(this,mypcc)' title='yyyy-mm-dd'>
			   <img src='../pic/show_calendar.gif' align='absbottom' width='24' height='22'
				id='img_from_date' border='0' alt='[?]' style='cursor:pointer'
				title='<?php xl('Click here to choose a date','e'); ?>'>
			</td>
			<td class='label'>
			   <?php xl('To','e'); ?>:
			</td>
			<td>
			   <input type='text' name='form_to_date' id="form_to_date" size='10' value='<?php echo $form_to_date ?>'
				onkeyup='datekeyup(this,mypcc)' onblur='dateblur(this,mypcc)' title='yyyy-mm-dd'>
			   <img src='../pic/show_calendar.gif' align='absbottom' width='24' height='22'
				id='img_to_date' border='0' alt='[?]' style='cursor:pointer'
				title='<?php xl('Click here to choose a date','e'); ?>'>
			</td>
		</tr>
	</table>

	</div>

  </td>
  <td align='left' valign='middle' height="100%">
	<table style='border-left:1px solid; width:100%; height:100%' >
		<tr>
			<td>
				<div style='margin-left:15px'>
					<a href='#' class='css_button' onclick='$("#form_refresh").attr("value","true"); $("#theform").submit();'>
					<span>
						<?php xl('Submit','e'); ?>
					</span>
					</a>

					<?php if ($_POST['form_refresh'] || $_POST['form_orderby'] ) { ?>
					<a href='#' class='css_button' onclick='window.print()'>
						<span>
							<?php xl('Print','e'); ?>
						</span>
					</a>
					<?php } ?>
				</div>
			</td>
		</tr>
	</table>
  </td>
 </tr>
</table>

</div>  <!-- end of search parameters -->

<?php
 if ($_POST['form_refresh'] || $_POST['form_orderby']) {
?>
<div id="report_results">
<table>

 <thead>
  <th>
   <a href="nojs.php" onclick="return dosort('doctor')"
   <?php if ($form_orderby == "doctor") echo " style=\"color:#00cc00\"" ?>><?php  xl('Provider','e'); ?> </a>
  </th>

  <th>
   <a href="nojs.php" onclick="return dosort('time')"
   <?php if ($form_orderby == "time") echo " style=\"color:#00cc00\"" ?>><?php  xl('Time','e'); ?></a>
  </th>

  <th>
   <a href="nojs.php" onclick="return dosort('patient')"
   <?php if ($form_orderby == "patient") echo " style=\"color:#00cc00\"" ?>><?php  xl('Patient','e'); ?></a>
  </th>

  <th>
   <a href="nojs.php" onclick="return dosort('pubpid')"
   <?php if ($form_orderby == "pubpid") echo " style=\"color:#00cc00\"" ?>><?php  xl('ID','e'); ?></a>
  </th>

  <th>
   <a href="nojs.php" onclick="return dosort('type')"
   <?php if ($form_orderby == "type") echo " style=\"color:#00cc00\"" ?>><?php  xl('Type','e'); ?></a>
  </th>

 </thead>
 <tbody>  <!-- added for better print-ability -->
<?php

 if ($res) {
  $lastdocname = "";

  while ($row = sqlFetchArray($res)) {
   $patient_id = $row['pid'];
   $docname  = $row['ulname'] . ', ' . $row['ufname'] . ' ' . $row['umname'];
   $errmsg  = "";

?>

 <tr bgcolor='<?php echo $bgcolor ?>'>
  <td class="detail">
   &nbsp;<?php echo ($docname == $lastdocname) ? "" : $docname ?>
  </td>

  <td class="detail">
   <?php echo oeFormatShortDate($row['pc_eventDate']) . ' ' . substr($row['pc_startTime'], 0, 5) ?>
   <!--
   &nbsp;<a href='javascript:oldEvt(<?php echo $row['pc_eid'] ?>)'>
   </a>
   -->
  </td>

  <td class="detail">
   &nbsp;<?php echo $row['fname'] . " " . $row['lname'] ?>
  </td>

  <td class="detail">
   &nbsp;<?php echo $row['pubpid'] ?>
  </td>

  <td class="detail">
   &nbsp;<?php echo xl_appt_category($row['pc_catname']) ?>
  </td>

 </tr>

<?php
   $lastdocname = $docname;
  }
 }
?>
</tbody>
</table>
</div>  <!-- end of search results -->
<?php } else { ?>
<div class='text'>
 	<?php echo xl('Please input search criteria above, and click Submit to view results.', 'e' ); ?>
</div>
<?php } ?>

<input type="hidden" name="form_orderby" value="<?php echo $form_orderby ?>" />
<input type="hidden" name="patient" value="<?php echo $patient ?>" />
<input type='hidden' name='form_refresh' id='form_refresh' value=''/>

</form>

<script>

<?php
if ($alertmsg) { echo " alert('$alertmsg');\n"; }
?>

</script>

</body>

<!-- stuff for the popup calendar -->
<style type="text/css">@import url(../../library/dynarch_calendar.css);</style>
<script type="text/javascript" src="../../library/dynarch_calendar.js"></script>
<?php include_once("{$GLOBALS['srcdir']}/dynarch_calendar_en.inc.php"); ?>
<script type="text/javascript" src="../../library/dynarch_calendar_setup.js"></script>
<script language="Javascript">
 Calendar.setup({inputField:"form_from_date", ifFormat:"%Y-%m-%d", button:"img_from_date"});
 Calendar.setup({inputField:"form_to_date", ifFormat:"%Y-%m-%d", button:"img_to_date"});
</script>

</html>

