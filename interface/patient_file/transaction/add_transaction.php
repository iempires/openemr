<?php
// add_transaction is a misnomer, as this script will now also edit
// existing transactions.

require_once("../../globals.php");
require_once("$srcdir/transactions.inc");
require_once("$srcdir/options.inc.php");

// Referral plugin support.
$fname = "../../../custom/LBF/REF.plugin.php";
if (file_exists($fname)) include_once($fname);

$transid = empty($_REQUEST['transid']) ? 0 : $_REQUEST['transid'] + 0;
$mode    = empty($_POST['mode' ]) ? '' : $_POST['mode' ];
$title   = empty($_POST['title']) ? '' : $_POST['title'];
$inmode    = $_GET['inmode'];
$body_onload_code="";
if ($inmode) {		/*	For edit func */
  $inedit = sqlStatement("SELECT * FROM transactions " .
    "WHERE id = '".$transid."'");
  while ($inmoderow = sqlFetchArray($inedit)) {
    $body = $inmoderow['body'];
  }
}
if ($mode) {
  $sets =
    "title='"          . $_POST['title'] . "'" .
    ", user = '"       . $_SESSION['authUser'] . "'" .
    ", groupname = '"  . $_SESSION['authProvider'] . "'" .
    ", authorized = '" . $userauthorized . "'" .
    ", date = NOW()";

  $fres = sqlStatement("SELECT * FROM layout_options " .
    "WHERE form_id = 'REF' AND uor > 0 AND field_id != '' " .
    "ORDER BY group_name, seq");
  while ($frow = sqlFetchArray($fres)) {
    $data_type = $frow['data_type'];
    $field_id  = $frow['field_id'];
    $value = $_POST["form_$field_id"];
    if ($field_id == 'body' && $title != 'Referral') {
      $value = $_POST["body"];
    }
    $sets .= ", $field_id = '$value'";
  }
  if ($transid) {
    sqlStatement("UPDATE transactions SET $sets WHERE id = '$transid'");
  }
  else {
    $sets .= ", pid = '$pid'";
    $transid = sqlInsert("INSERT INTO transactions SET $sets");
  }

  if ($GLOBALS['concurrent_layout'])
    $body_onload_code = "javascript:location.href='transactions.php';";
  else
    $body_onload_code = "javascript:parent.Transactions.location.href='transactions.php';";
}

$trans_types = array(
  'Referral'          => xl('Referral'),
  'Patient Request'   => xl('Patient Request'),
  'Physician Request' => xl('Physician Request'),
  'Legal'             => xl('Legal'),
  'Billing'           => xl('Billing'),
);

$CPR = 4; // cells per row

function end_cell() {
  global $item_count, $cell_count;
  if ($item_count > 0) {
    echo "</td>";
    $item_count = 0;
  }
}

function end_row() {
  global $cell_count, $CPR;
  end_cell();
  if ($cell_count > 0) {
    for (; $cell_count < $CPR; ++$cell_count) echo "<td></td>";
    echo "</tr>\n";
    $cell_count = 0;
  }
}

function end_group() {
  global $last_group;
  if (strlen($last_group) > 0) {
    end_row();
    echo " </table>\n";
    echo "</div>\n";
  }
}

// If we are editing a transaction, get its ID and data.
$trow = $transid ? getTransById($transid) : array();
?>
<html>
<head>
<?php html_header_show(); ?>

<link rel='stylesheet' href="<?php echo $css_header;?>" type="text/css">
<link rel="stylesheet" type="text/css" href="../../../library/js/fancybox/jquery.fancybox-1.2.6.css" media="screen" />

<style type="text/css">@import url(../../../library/dynarch_calendar.css);</style>
<script type="text/javascript" src="../../../library/dynarch_calendar.js"></script>
<?php include_once("{$GLOBALS['srcdir']}/dynarch_calendar_en.inc.php"); ?>
<script type="text/javascript" src="../../../library/dynarch_calendar_setup.js"></script>
<script type="text/javascript" src="../../../library/textformat.js"></script>
<script type="text/javascript" src="../../../library/dialog.js"></script>

<script type="text/javascript" src="<?php echo $GLOBALS['webroot'] ?>/library/js/jquery.js"></script>
<script type="text/javascript" src="<?php echo $GLOBALS['webroot'] ?>/library/js/common.js"></script>
<script type="text/javascript" src="<?php echo $GLOBALS['webroot'] ?>/library/js/jquery.1.3.2.js"></script>
<script type="text/javascript" src="<?php echo $GLOBALS['webroot'] ?>/library/js/fancybox/jquery.fancybox-1.2.6.js"></script>
<script type="text/javascript">
$(document).ready(function(){
    tabbify();
    enable_modals();
});
</script>
<script language="JavaScript">

var mypcc = '<?php echo $GLOBALS['phone_country_code'] ?>';

function titleChanged() {
 var sel = document.forms[0].title;
 var si = (sel.selectedIndex < 0) ? 0 : sel.selectedIndex;
 if (sel.options[si].value == 'Referral') {
  document.getElementById('otherdiv').style.display = 'none';
  document.getElementById('referdiv').style.display = 'block';
 } else {
  document.getElementById('referdiv').style.display = 'none';
  document.getElementById('otherdiv').style.display = 'block';
 }
 return true;
}

function divclick(cb, divid) {
 var divstyle = document.getElementById(divid).style;
 if (cb.checked) {
  divstyle.display = 'block';
 } else {
  divstyle.display = 'none';
 }
 return true;
}

// This is for callback by the find-code popup.
// Appends to or erases the current list of related codes.
function set_related(codetype, code, selector, codedesc) {
 var frc = document.getElementById('form_related_code');
 var s = frc.value;
 if (code) {
  if (s.length > 0) s += ';';
  s += codetype + ':' + code;
 } else {
  s = '';
 }
 frc.value = s;
}

// This invokes the find-code popup.
function sel_related() {
 dlgopen('../encounter/find_code_popup.php<?php if ($GLOBALS['ippf_specific']) echo '?codetype=IPPF' ?>', '_blank', 500, 400);
}

// Process click on Delete link.
function deleteme() {
// onclick='return deleteme()'
 dlgopen('../deleter.php?transaction=<?php echo $transid ?>', '_blank', 500, 450);
 return false;
}

// Called by the deleteme.php window on a successful delete.
function imdeleted() {
 top.restoreSession();
 location.href = 'transaction/transactions.php';
}

// Compute the length of a string without leading and trailing spaces.
function trimlen(s) {
 var i = 0;
 var j = s.length - 1;
 for (; i <= j && s.charAt(i) == ' '; ++i);
 for (; i <= j && s.charAt(j) == ' '; --j);
 if (i > j) return 0;
 return j + 1 - i;
}

// Validation logic for form submission.
function validate(f) {
 var errCount = 0;
 var errMsgs = new Array();

 var sel = f.title;
 var si = (sel.selectedIndex < 0) ? 0 : sel.selectedIndex;
 if (sel.options[si].value == 'Referral') {
    <?php generate_layout_validation('REF'); ?>
 }

 var msg = "";
 msg += "<?php xl('The following fields are required', 'e' ); ?>:\n\n";
 for ( var i = 0; i < errMsgs.length; i++ ) {
	msg += errMsgs[i] + "\n";
 }
 msg += "\n<?php xl('Please fill them in before continuing.', 'e'); ?>";

 if ( errMsgs.length > 0 ) {
	alert(msg);
 }

 return errMsgs.length < 1;
}

function submitme() {
 var f = document.forms['new_transaction'];
 if (validate(f)) {
  top.restoreSession();
  f.submit();
 }
}

<?php if (function_exists('REF_javascript')) call_user_func('REF_javascript'); ?>

</script>


<style type="text/css">
div.tab {
	height: auto;
	width: auto;
}
</style>

</head>
<body class="body_top" onload="<?php echo $body_onload_code; ?>" >
<form name='new_transaction' method='post' action='add_transaction.php?transid=<?php echo $transid ?>' onsubmit='return validate(this)'>
<input type='hidden' name='mode' value='add'>

	<table>
	    <tr>
            <td>
                <b><?php xl('Add/Edit Patient Transaction','e'); ?></b>&nbsp;</td><td>
                 <a href="javascript:;"  <?php if (!$GLOBALS['concurrent_layout']) echo "target='Main'"; ?> class="css_button" onclick="submitme();">
                    <span><?php xl('Save','e'); ?></span>
                 </a>
             </td>
             <td>
                <a href="transactions.php"  <?php if (!$GLOBALS['concurrent_layout']) echo "target='Main'"; ?> class="css_button" onclick="top.restoreSession()">
                    <span><?php xl('Cancel','e'); ?></span>
                </a>
            </td>
        </tr>
	</table>

	<table class="text">
	    <tr><td>
        <?php xl('Transaction Type','e'); ?>:&nbsp;</td><td>
        <select name='title' onchange='titleChanged()'>
        <?php
        $db_title=$_REQUEST['title'];
        foreach ($trans_types as $key => $value) {
          echo "    <option value='$key'";
          if ($key == $db_title) echo " selected";
          echo ">$value</option>\n";
        }
        ?>
        </select>
        </td></tr>
	</table>

<div id='referdiv'>

					<div id="DEM">
						<ul class="tabNav">
<?php
$fres = sqlStatement("SELECT * FROM layout_options " .
  "WHERE form_id = 'REF' AND uor > 0 " .
  "ORDER BY group_name, seq");
$last_group = '';
$cell_count = 0;
$item_count = 0;
$display_style = 'block';

while ($frow = sqlFetchArray($fres)) {
  $this_group = $frow['group_name'];
  $titlecols  = $frow['titlecols'];
  $datacols   = $frow['datacols'];
  $data_type  = $frow['data_type'];
  $field_id   = $frow['field_id'];
  $list_id    = $frow['list_id'];

  $currvalue  = '';
  if (isset($trow[$field_id])) $currvalue = $trow[$field_id];

  // Handle special-case default values.
  if (!$currvalue && !$transid) {
    if ($field_id == 'refer_date') {
      $currvalue = date('Y-m-d');
    }
    else if ($field_id == 'body' ) {
      $tmp = sqlQuery("SELECT reason FROM form_encounter WHERE " .
        "pid = '$pid' ORDER BY date DESC LIMIT 1");
      if (!empty($tmp)) $currvalue = $tmp['reason'];
    }
  }

  // Handle a data category (group) change.
  if (strcmp($this_group, $last_group) != 0) {
    $group_seq  = substr($this_group, 0, 1);
    $group_name = substr($this_group, 1);
    $last_group = $this_group;
	if($group_seq==1)	echo "<li class='current'>";
	else				echo "<li class=''>";
	echo "<a href='/play/javascript-tabbed-navigation/' id='div_$group_seq'>".xl_layout_label($group_name)."</a></li>";
  }
  ++$item_count;
}
?>
						</ul>
						<div class="tabContainer">

								<?php
$fres = sqlStatement("SELECT * FROM layout_options " .
  "WHERE form_id = 'REF' AND uor > 0 " .
  "ORDER BY group_name, seq");
$last_group = '';
$cell_count = 0;
$item_count = 0;
$display_style = 'block';

while ($frow = sqlFetchArray($fres)) {
  $this_group = $frow['group_name'];
  $titlecols  = $frow['titlecols'];
  $datacols   = $frow['datacols'];
  $data_type  = $frow['data_type'];
  $field_id   = $frow['field_id'];
  $list_id    = $frow['list_id'];

  $currvalue  = '';
  if (isset($trow[$field_id])) $currvalue = $trow[$field_id];

  // Handle special-case default values.
  if (!$currvalue && !$transid) {
    if ($field_id == 'refer_date') {
      $currvalue = date('Y-m-d');
    }
    else if ($field_id == 'body' && $transid > 0 ) {
	   $tmp = sqlQuery("SELECT reason FROM form_encounter WHERE " .
        "pid = '$pid' ORDER BY date DESC LIMIT 1");
      if (!empty($tmp)) $currvalue = $tmp['reason'];
    }
  }

  // Handle a data category (group) change.
  if (strcmp($this_group, $last_group) != 0) {
    end_group();
    $group_seq  = substr($this_group, 0, 1);
    $group_name = substr($this_group, 1);
    $last_group = $this_group;
	if($group_seq==1)	echo "<div class='tab current' id='div_$group_seq'>";
	else				echo "<div class='tab' id='div_$group_seq'>";
    echo " <table border='0' cellpadding='0'>\n";
    $display_style = 'none';
  }

  // Handle starting of a new row.
  if (($titlecols > 0 && $cell_count >= $CPR) || $cell_count == 0) {
    end_row();
    echo " <tr>";
  }

  if ($item_count == 0 && $titlecols == 0) $titlecols = 1;

  // Handle starting of a new label cell.
  if ($titlecols > 0) {
    end_cell();
    echo "<td width='70' valign='top' colspan='$titlecols'";
    echo ($frow['uor'] == 2) ? " class='required'" : " class='bold'";
    if ($cell_count == 2) echo " style='padding-left:10pt'";
    echo ">";
    $cell_count += $titlecols;
  }
  ++$item_count;

  echo "<b>";

  // Modified 6-09 by BM - Translate if applicable
  if ($frow['title']) echo (xl_layout_label($frow['title']) . ":"); else echo "&nbsp;";

  echo "</b>";

  // Handle starting of a new data cell.
  if ($datacols > 0) {
    end_cell();
    echo "<td valign='top' colspan='$datacols' class='text'";
    if ($cell_count > 0) echo " style='padding-left:5pt'";
    echo ">";
    $cell_count += $datacols;
  }

  ++$item_count;
  generate_form_field($frow, $currvalue);
  echo "</div>";
}

end_group();

?>
</div></div>
</form>
</div>
<p>
<div id='otherdiv' style='display:none'>
<span class='bold'><?php xl('Details','e'); ?>:</span><br>
<textarea name='body' rows='6' cols='40' wrap='virtual'><?php echo $body; ?>
</textarea>
</div>
</p>



<!-- include support for the list-add selectbox feature -->
<?php include $GLOBALS['fileroot']."/library/options_listadd.inc"; ?>

</body>

<script language="JavaScript">
<?php echo $date_init; ?>
titleChanged();
<?php
if (function_exists('REF_javascript_onload')) {
  call_user_func('REF_javascript_onload');
}
?>

</script>

</html>
