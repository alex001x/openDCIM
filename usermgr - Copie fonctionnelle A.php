<?php
require_once('db.inc.php');
require_once('facilities.inc.php');

$subheader=__("Data Center Contact Detail");

if(!$person->ContactAdmin){
    header('Location: '.redirect());
    exit;
}

$c = new Country;
$countryList = $c->CountryList();

$userRights=new People();
$status="";

if(isset($_REQUEST['PersonID']) && strlen($_REQUEST['PersonID']) >0){
    $userRights->PersonID=$_REQUEST['PersonID'];
    $userRights->GetPerson();
}

if(isset($_POST['action'])&&isset($_POST['UserID'])){
    if((($_POST['action']=='Create')||($_POST['action']=='Update'))&&(isset($_POST['LastName'])&&$_POST['LastName']!=null&&$_POST['LastName']!='')){
        $userRights->UserID=$_POST['UserID'];
        $userRights->LastName=$_POST['LastName'];
        $userRights->FirstName=$_POST['FirstName'];
        $userRights->Phone1=$_POST['Phone1'];
        $userRights->Phone2=$_POST['Phone2'];
        $userRights->countryCode=$_POST['countryCode'];
        $userRights->Email=$_POST['Email'];
        $userRights->ExpirationDate=$_POST['ExpirationDate'];

        if ( isset($_POST['NewKey']) ) {
            $userRights->APIKey=md5($userRights->UserID . date('Y-m-d H:i:s') );
        }

        // if AUTHENTICATION == "LDAP" these get overwritten whenever an LDAP user logs in
        // however, an LDAP site still needs to be able to add a userid for API access
        // and set their rights
        $userRights->AdminOwnDevices=(isset($_POST['AdminOwnDevices']))?1:0;
        $userRights->ReadAccess=(isset($_POST['ReadAccess']))?1:0;
        $userRights->WriteAccess=(isset($_POST['WriteAccess']))?1:0;
        $userRights->DeleteAccess=(isset($_POST['DeleteAccess']))?1:0;
        $userRights->ContactAdmin=(isset($_POST['ContactAdmin']))?1:0;
        $userRights->RackRequest=(isset($_POST['RackRequest']))?1:0;
        $userRights->RackAdmin=(isset($_POST['RackAdmin']))?1:0;
        $userRights->BulkOperations=(isset($_POST['BulkOperations']))?1:0;
        $userRights->SiteAdmin=(isset($_POST['SiteAdmin']))?1:0;
        $userRights->Disabled=(isset($_POST['Disabled']))?1:0;

        // Process Datacenter ACL from form (no API)
        // Rebuild ACLs for all datacenters so unchecked boxes are saved as 0
        $acls=array();
        foreach($dbh->query("SELECT DataCenterID FROM fac_DataCenter ORDER BY DataCenterID") as $rowdc){
            $dcid=intval($rowdc['DataCenterID']);
            $bits=(isset($_POST['dcacl'][$dcid]) && is_array($_POST['dcacl'][$dcid]))?$_POST['dcacl'][$dcid]:array();
            $rights=0;
            if(isset($bits['r'])){ $rights|=1; }
            if(isset($bits['w'])){ $rights|=2; }
            if(isset($bits['d'])){ $rights|=4; }
            $acls[]=array('DataCenterID'=>$dcid,'Rights'=>$rights);
        }

        if($_POST['action']=='Create'){
            if ( $userRights->CreatePerson() ) {
                // Save ACLs if provided (SiteAdmin => remove explicit ACLs)
                if($userRights->SiteAdmin){
                    $st=$dbh->prepare('DELETE FROM fac_PermissionsDC WHERE UserID=:uid');
                    $st->execute(array(':uid'=>$userRights->UserID));
                }elseif(isset($acls)){
                    DCACL::setRightsForUser($userRights->UserID,$acls);
                }
                // Redirect to created user's page
                header('Location: '.redirect("usermgr.php?PersonID=$userRights->PersonID"));
                exit;
            } else {
                // Likely the UserID already exists
                if ( $userRights->GetPersonByUserID() ) {
                    $status=__("Existing UserID account displayed.");
                } else {
                    $status=__("Something is broken.   Unable to create Person account.");
                }
            }
        }else{
            $status=__("Updated");
            $userRights->UpdatePerson();
            // Save ACLs if provided (SiteAdmin => remove explicit ACLs)
            if($userRights->SiteAdmin){
                $st=$dbh->prepare('DELETE FROM fac_PermissionsDC WHERE UserID=:uid');
                $st->execute(array(':uid'=>$userRights->UserID));
            }elseif(isset($acls)){
                DCACL::setRightsForUser($userRights->UserID,$acls);
            }
        }
    }elseif($_POST['action']=='DeleteUser' && $_POST['UserID'] != $person->UserID) {
        // You can't delete yourself
        $userRights->UserID=$_POST['UserID'];
        $userRights->DeletePerson();

        $userRights->UserID=0;
    }
    // Reload rights because actions like disable reset other rights
    $userRights->GetUserRights();
}

$userList=$userRights->GetUserList();
$adminown=($userRights->AdminOwnDevices)?"checked":"";
$read=($userRights->ReadAccess)?"checked":"";
$write=($userRights->WriteAccess)?"checked":"";
$delete=($userRights->DeleteAccess)?"checked":"";
$contact=($userRights->ContactAdmin)?"checked":"";
$request=($userRights->RackRequest)?"checked":"";
$RackAdmin=($userRights->RackAdmin)?"checked":"";
$BulkOperations=($userRights->BulkOperations)?"checked":"";
$admin=($userRights->SiteAdmin)?"checked":"";
$Disabled=($userRights->Disabled)?"checked":"";

// Build Datacenter list (no API) and current ACLs for the selected user
$dcList = array();
if($person->ContactAdmin){
    foreach($dbh->query("SELECT DataCenterID, Name, ContainerID FROM fac_DataCenter ORDER BY Name ASC") as $row){
        $dcList[] = array('DataCenterID'=>intval($row['DataCenterID']), 'Name'=>stripslashes($row['Name']), 'ContainerID'=>intval($row['ContainerID']));
    }
}
$currentACL = array();
if($userRights->UserID!=''){
    $currentACL = DCACL::getRightsByUser($userRights->UserID);
}

// Prepare hierarchical ACL rows: containers + DCs with explicit indentation
$containers = array();
$childrenContainers = array();
foreach($dbh->query("SELECT ContainerID, Name, ParentID FROM fac_Container ORDER BY ParentID, Name") as $row){
    $cid = intval($row['ContainerID']);
    $pid = intval($row['ParentID']);
    $containers[$cid] = array('ContainerID'=>$cid,'Name'=>stripslashes($row['Name']),'ParentID'=>$pid);
    if(!isset($childrenContainers[$pid])){ $childrenContainers[$pid] = array(); }
    $childrenContainers[$pid][] = $cid;
}
$dcsByContainer = array();
foreach($dcList as $d){
    $cid = $d['ContainerID'];
    if(!isset($dcsByContainer[$cid])){ $dcsByContainer[$cid] = array(); }
    $dcsByContainer[$cid][] = $d['DataCenterID'];
}
$dcNameByID = array();
foreach($dcList as $d){ $dcNameByID[$d['DataCenterID']] = $d['Name']; }

function renderACLContainerRows($cid, $level, $containers, $childrenContainers, $dcsByContainer, $dcNameByID, $currentACL){
    $rows = '';
    if(isset($containers[$cid])){
        // Collect all descendant DCs for this container
        $descDCs = array();
        $stack = array($cid);
        while(!empty($stack)){
            $cur = array_pop($stack);
            if(isset($dcsByContainer[$cur])){ $descDCs = array_merge($descDCs, $dcsByContainer[$cur]); }
            if(isset($childrenContainers[$cur])){ foreach($childrenContainers[$cur] as $ch){ $stack[] = $ch; } }
        }
        $descDCs = array_values(array_unique($descDCs));
        $dataDC = implode(',', $descDCs);
        // Container row
        $rows .= '<tr class="acl-container" data-container-id="'.$cid.'" data-desc-dc="'.$dataDC.'">'
            .'<td><strong>'.htmlspecialchars($containers[$cid]['Name']).'</strong></td>'
            .'<td><input type="checkbox" class="acl-read-cont"></td>'
            .'<td><input type="checkbox" class="acl-write-cont"></td>'
            .'<td><input type="checkbox" class="acl-delete-cont"></td>'
            ."</tr>";
        // DCs directly under this container
        if(isset($dcsByContainer[$cid])){
            foreach($dcsByContainer[$cid] as $dcid){
                $r = (isset($currentACL[$dcid])) ? intval($currentACL[$dcid]) : 0;
                $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', max(1,$level+1));
                $name = isset($dcNameByID[$dcid]) ? $dcNameByID[$dcid] : ('DC #'.$dcid);
                $rows .= '<tr class="acl-dc level-'.$level.'" data-dcid="'.$dcid.'">'
                    .'<td>'.$indent.htmlspecialchars($name).'</td>'
                    .'<td><input type="checkbox" name="dcacl['.$dcid.'][r]" class="acl-read" '.(($r & 1)?'checked':'').'></td>'
                    .'<td><input type="checkbox" name="dcacl['.$dcid.'][w]" class="acl-write" '.(($r & 2)?'checked':'').'></td>'
                    .'<td><input type="checkbox" name="dcacl['.$dcid.'][d]" class="acl-delete" '.(($r & 4)?'checked':'').'></td>'
                    ."</tr>";
            }
        }
        // Recurse for child containers
        if(isset($childrenContainers[$cid])){
            foreach($childrenContainers[$cid] as $childCid){
                $rows .= renderACLContainerRows($childCid, $level+1, $containers, $childrenContainers, $dcsByContainer, $dcNameByID, $currentACL);
            }
        }
    }
    return $rows;
}

$acl_rows = '';
if(isset($childrenContainers[0])){
    foreach($childrenContainers[0] as $rootCid){
        $acl_rows .= renderACLContainerRows($rootCid, 0, $containers, $childrenContainers, $dcsByContainer, $dcNameByID, $currentACL);
    }
}
// DCs without container
if(isset($dcsByContainer[0])){
    foreach($dcsByContainer[0] as $dcid){
        $r = (isset($currentACL[$dcid])) ? intval($currentACL[$dcid]) : 0;
        $name = isset($dcNameByID[$dcid]) ? $dcNameByID[$dcid] : ('DC #'.$dcid);
        $acl_rows .= '<tr class="acl-dc level-0" data-dcid="'.$dcid.'">'
            .'<td>'.htmlspecialchars($name).'</td>'
            .'<td><input type="checkbox" name="dcacl['.$dcid.'][r]" class="acl-read" '.(($r & 1)?'checked':'').'></td>'
            .'<td><input type="checkbox" name="dcacl['.$dcid.'][w]" class="acl-write" '.(($r & 2)?'checked':'').'></td>'
            .'<td><input type="checkbox" name="dcacl['.$dcid.'][d]" class="acl-delete" '.(($r & 4)?'checked':'').'></td>'
            ."</tr>";
    }
}
?>
<!doctype html>
<html>
<head>
  <meta http-equiv="X-UA-Compatible" content="IE=Edge">
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  
  <title>openDCIM User Manager</title>
  <link rel="stylesheet" href="css/inventory.php" type="text/css">
  <link rel="stylesheet" href="css/jquery-ui.css" type="text/css">
  <link rel="stylesheet" href="css/validationEngine.jquery.css" type="text/css">
  
  <!--[if lt IE 9]>
  <link rel="stylesheet"  href="css/ie.css" type="text/css">
  <![endif]-->
  
  <script type="text/javascript" src="scripts/jquery.min.js"></script>
  <script type="text/javascript" src="scripts/jquery-ui.min.js"></script>
  <script type="text/javascript" src="scripts/jquery.validationEngine-en.js"></script>
  <script type="text/javascript" src="scripts/jquery.validationEngine.js"></script>
  <script type="text/javascript">
$(document).ready(function(){
    $('.main form').validationEngine();

    function DataValidationIsDumb(e){
        if(this.checked){ $(this).attr('checked','checked'); }else{ $(this).removeAttr('checked'); }
        $('.main form').validationEngine('detach');
        $('.main form').validationEngine('attach');
    }

    $('#RackRequest').on('change',DataValidationIsDumb)

    $('#PersonID').change(function(e){ location.href='?PersonID='+this.value; });
    $('#showdept').click(showdept);
    $('#transferdevices').click(ShowModal);
    if(parseInt($('#PersonID').val())){ UpdateDeviceCount(); }
    $('#PrimaryContact').click(function(e){ window.open('search.php?key=dev&PrimaryContact='+$('#PersonID').val()+'&search'); });
    $('#nofloat :input').click(DisabledFlipper);

    function DisabledFlipper(e){
        if(e.currentTarget.name=='Disabled'){
            $('#nofloat :input[name!="Disabled"]').each(function(){this.checked=false;});
            if(e.currentTarget.checked && parseInt($('#PrimaryContact').text())){ ShowModal(); }
        }else{ $('#Disabled').prop('checked',false); }
    }

    function toggleACLUI(){
        if($('#SiteAdmin').is(':checked')){
            $('#dcacl-admin-note').removeClass('hide');
            $('#dcacl-table').css({'opacity':0.5,'pointer-events':'none'});
        }else{
            $('#dcacl-admin-note').addClass('hide');
            $('#dcacl-table').css({'opacity':1,'pointer-events':'auto'});
        }
    }
    $('#SiteAdmin').on('change', toggleACLUI);
    toggleACLUI();

    // Enforce dependencies on DC checkboxes: write implies read; delete implies write+read
    $('#dcacl-table').on('change', '.acl-read, .acl-write, .acl-delete', function(){
        var $row=$(this).closest('tr');
        var r=$row.find('.acl-read'), w=$row.find('.acl-write'), d=$row.find('.acl-delete');
        if(d.is(':checked')){ w.prop('checked', true); r.prop('checked', true); }
        if(w.is(':checked')){ r.prop('checked', true); }
        if(!r.is(':checked')){ w.prop('checked', false); d.prop('checked', false); }
        if(!w.is(':checked')){ d.prop('checked', false); }
        updateContainerStates();
    });

    // Container actions: toggle descendants with dependencies
    function toggleContainerColumn($contRow, colClass, value){
        var list = ($contRow.data('desc-dc')||'').toString(); if(!list){ return; }
        list.split(',').forEach(function(id){
            if(!id){return;}
            var $row=$('#dcacl-table tbody tr.acl-dc[data-dcid="'+id+'"]').first();
            if($row.length){ $row.find('.'+colClass).prop('checked', value).trigger('change'); }
        });
    }
    $('#dcacl-table').on('change', '.acl-read-cont',  function(){
        var v=$(this).is(':checked');
        if(!v){ $(this).closest('tr').find('.acl-write-cont,.acl-delete-cont').prop('checked',false); }
        toggleContainerColumn($(this).closest('tr'), 'acl-read',  v);
        updateContainerStates();
    });
    $('#dcacl-table').on('change', '.acl-write-cont', function(){
        var v=$(this).is(':checked');
        if(v){ $(this).closest('tr').find('.acl-read-cont').prop('checked',true); }
        else { $(this).closest('tr').find('.acl-delete-cont').prop('checked',false); }
        toggleContainerColumn($(this).closest('tr'), 'acl-write', v);
        updateContainerStates();
    });
    $('#dcacl-table').on('change', '.acl-delete-cont',function(){
        var v=$(this).is(':checked');
        if(v){ $(this).closest('tr').find('.acl-read-cont,.acl-write-cont').prop('checked',true); }
        toggleContainerColumn($(this).closest('tr'), 'acl-delete',v);
        updateContainerStates();
    });

    function updateContainerStates(){
        $('#dcacl-table tbody tr.acl-container').each(function(){
            var $cont=$(this), list = ($cont.data('desc-dc')||'').toString(), ids=list? list.split(','):[];
            ['read','write','delete'].forEach(function(kind){
                var sel='.acl-'+kind, $boxes=$();
                ids.forEach(function(id){ var $row=$('#dcacl-table tbody tr.acl-dc[data-dcid="'+id+'"]'); if($row.length){ $boxes=$boxes.add($row.find(sel)); }});
                var total=$boxes.length, checked=$boxes.filter(':checked').length, $h=$cont.find('.acl-'+kind+'-cont');
                if(total===0){ $h.prop({checked:false, indeterminate:false}); }
                else if(checked===0){ $h.prop({checked:false, indeterminate:false}); }
                else if(checked===total){ $h.prop({checked:true, indeterminate:false}); }
                else { $h.prop({checked:false, indeterminate:true}); }
            });
            // Enforce dependencies among container checkboxes
            var r=$cont.find('.acl-read-cont'), w=$cont.find('.acl-write-cont'), d=$cont.find('.acl-delete-cont');
            if(d.is(':checked')){ w.prop('checked',true); r.prop('checked',true); }
            if(w.is(':checked')){ r.prop('checked',true); }
            if(!r.is(':checked')){ w.prop('checked',false); d.prop('checked',false); }
            if(!w.is(':checked')){ d.prop('checked',false); }
        });
    }
    updateContainerStates();
});
function UpdateDeviceCount(){
    var PersonID=$('#PersonID').val();
    $.get('api/v1/device?PrimaryContact='+PersonID).done(function(data){
        $('#PrimaryContact').text(data.device.length);
        if(data.device.length){ $('#transferdevices').removeClass('hide').show(); }else{ $('#transferdevices').hide(); }
    });
}
function ShowModal(e){
    $('#copy').replaceWith($('#PersonID').clone().attr('id','copy'));
    $('#copy option[value=0]').text('');
    $('#copy option[value='+$('#PersonID').val()+']').remove();
    $.get('api/v1/people?Disabled=1').done(function(data){ if(!data.error){ for(var x in data.people){ $('#copy option[value='+data.people[x].PersonID+']').remove(); } } });
    $('#deletemodal').dialog({ dialogClass: "no-close", width: 600, modal: true, buttons: { Transfer: function(e){ $('#doublecheck').dialog({ dialogClass: "no-close", width: 600, modal: true, buttons: { Yes: function(e){ $.post('api/v1/people/'+$('#PersonID').val()+'/transferdevicesto/'+$('#copy').val()).done(function(data){ if(!data.error){ $('#doublecheck').dialog('destroy'); $('#deletemodal').dialog('destroy'); } }); UpdateDeviceCount(); }, No: function(e){ $('#doublecheck').dialog('destroy'); $('#deletemodal').dialog('destroy'); $('#Disabled').prop('checked',false); } } }); }, No: function(e){ $('#deletemodal').dialog('destroy'); $('#Disabled').prop('checked',false); } } });
}
function showdept(){
    if($(".main form").validationEngine('validate')){
        var formdata=$(".main form").serializeArray();
        formdata.push({name:'action',value:"Update"});
        $.post('',formdata);
        $('#nofloat').parent('div').addClass('hide');
        $('.center input').attr('readonly','true');
        $('#controls').hide().removeClass('caption');
        $('#groupadmin').css('display','block').attr('src', 'people_depts.php?personid='+$('#PersonID').val());
    }
}
  </script>
</head>
<body>
<?php include( 'header.inc.php' ); ?>

<div class="page">
<?php include( 'sidebar.inc.php' );

echo '<div class="main">
<h3>',$status,'</h3>
<div class="center"><div>
<form method="POST">
<div class="table centermargin">


<div>
   <div><label for="PersonID">',__("User"),'</label></div>
     <div><select name="PersonID" id="PersonID">
     <option value=0>',__("New User"),'</option>';
  
  foreach($userList as $userRow){
      if($userRights->PersonID == $userRow->PersonID){$selected='selected';}else{$selected="";}
      print "<option value=$userRow->PersonID $selected>" . $userRow->LastName . ", " . $userRow->FirstName . " (" . $userRow->UserID . ")" . "</option>\n";
  }
  
  echo '  </select>&nbsp;&nbsp;<span title="',__("This user is the primary contact for this many devices"),'" id="PrimaryContact"></span></div>
</div>
<div>
   <div><label for="UserID">',__("UserID"),'</label></div>
   <div><input type="text" class="validate[required,minSize[1],maxSize[50]]" name="UserID" id="UserID" value="',$userRights->UserID,'"></div>
</div>
<div>
   <div><label for="LastName">',__("Last Name"),'</label></div>
   <div><input type="text" class="validate[required,minSize[1],maxSize[50]]" name="LastName" id="LastName" value="',$userRights->LastName,'"></div>
</div>
<div>
   <div><label for="FirstName">',__("First Name"),'</label></div>
   <div><input type="text" class="validate[required,minSize[1],maxSize[50]]" name="FirstName" id="FirstName" value="',$userRights->FirstName,'"></div>
</div>
<div>
   <div><label for="Phone1">',__("Phone 1"),'</label></div>
   <div><input type="text" name="Phone1" id="Phone1" value="',$userRights->Phone1,'"></div>
</div>
<div>
   <div><label for="Phone2">',__("Phone 2"),'</label></div>
   <div><input type="text" name="Phone2" id="Phone2" value="',$userRights->Phone2,'"></div>
</div>
<div>
   <div><label for="Country">',__("Country"),'</label></div>
   <div>
    <select name="countryCode" id="countryCode">';
    foreach($countryList as $countryRow ) {
        if ($userRights->countryCode == $countryRow->countryCode ) {
            $selected = 'selected';
        } elseif ( $userRights->countryCode == '' && $countryRow->countryCode == $config->ParameterArray['DefaultCountry'] ) {
            $selected = 'selected';
        } else {
            $selected = '';
        }
        print "<option value=$countryRow->countryCode $selected>" . $countryRow->countryCode . " - " . $countryRow->countryName . "</option>\n";
    }
    echo '     </select>
   </div>
</div>
<div>
   <div><label for="Email">',__("Email Address"),'</label></div>
   <div><input type="text" class="validate[optional,custom[email],condRequired[RackRequest]]" name="Email" id="Email" value="',$userRights->Email,'"></div>
</div>
<div>
    <div><label for="LastActivity">',__("Last Activity"),'</label></div>
    <div>',$userRights->LastActivity=='0000-00-00 00:00:00'?__("Never"):date('r',strtotime($userRights->LastActivity)),'</div>
</div>
<div>
    <div><label for="ExpirationDate">',__("Expiration Date"),'</label></div>
    <div><input type="text" name="ExpirationDate" id="ExpirationDate" value=',$userRights->ExpirationDate=='0000-00-00'?'':date('Y/m/d',strtotime($userRights->ExpirationDate)),'></div>
</div>
<div>
   <div><label for="APIKey">',__("API Key"),'</label></div>
   <div><input type="text" size="60" name="APIKey" id="APIKey" value="',$userRights->APIKey,'" readonly></div>
</div>
<div>
   <div><label for="NewKey">',__("Generate New Key"),'</label></div>
   <div><input name="NewKey" id="NewKey" type="checkbox"></div>
</div>

<div>
   <div><label>',__("Rights"),'</label></div>
   <div id="nofloat">
    <input name="AdminOwnDevices" id="AdminOwnDevices" type="checkbox" ',$adminown,'><label for="AdminOwnDevices">',__("Admin Own Devices"),'</label><br>
    <input name="ReadAccess" id="ReadAccess" type="checkbox" ',$read,'><label for="ReadAccess">',__("Read/Report Access (Global)"),'</label><br>
    <input name="WriteAccess" id="WriteAccess" type="checkbox" ',$write,'><label for="WriteAccess">',__("Modify/Enter Devices (Global)"),'</label><br>
    <input name="DeleteAccess" id="DeleteAccess" type="checkbox" ',$delete,'><label for="DeleteAccess">',__("Delete Devices (Global)"),'</label><br>
    <input name="ContactAdmin" id="ContactAdmin" type="checkbox" ',$contact,'><label for="ContactAdmin">',__("Enter/Modify Contacts and Departments"),'</label><br>
    <input name="RackRequest" id="RackRequest" type="checkbox" ',$request,'><label for="RackRequest">',__("Enter Rack Requests"),'</label><br>
    <input name="RackAdmin" id="RackAdmin" type="checkbox" ',$RackAdmin,'><label for="RackAdmin">',__("Complete Rack Requests"),'</label><br>
    <input name="BulkOperations" id="BulkOperations" type="checkbox" ',$BulkOperations,'><label for="BulkOperations">',__("Perform Bulk Operations"),'</label><br>
    <input name="SiteAdmin" id="SiteAdmin" type="checkbox" ',$admin,'><label for="SiteAdmin">',__("Manage Site and Users"),'</label><br>
    <input name="Disabled" id="Disabled" type="checkbox" ',$Disabled,'><label for="Disabled">',__("Disabled"),'</label><br>
   </div>
</div>
<br><br>
</div> <!-- END div.table -->

  <div>
     <div><label>',__("Datacenter Access (ACL)"),'</label></div>
     <div id="dcacl-block">
       <div id="dcacl-admin-note" class="',($userRights->SiteAdmin?'':'hide'),'">',__("Full access (SiteAdmin)"),'</div>
       <table id="dcacl-table" class="table whiteborder" style="width:100%;',($userRights->SiteAdmin?'opacity:0.5; pointer-events:none;':''),'">
         <thead>
           <tr>
             <th>',__("Datacenter/Container"),'</th>
             <th>',__("Read"),'</th>
             <th>',__("Write"),'</th>
             <th>',__("Delete"),'</th>
           </tr>
         </thead>
        <tbody>',$acl_rows,'</tbody>
       </table>
     </div>
  </div>
<br><br>

<div>
  <div class="caption" id="controls">';
    if($userRights->PersonID>0){
        echo '<button type="submit" name="action" value="Update">',__("Update"),'</button><button type="button" id="showdept">',__("Department Membership"),'</button><button class="hide" id="transferdevices" type="button">',__("Transfer Devices"),'</button><button type="submit" name="action" value="DeleteUser" id="deleteuser">',__("Delete User"),'</button>';
    }else{
        echo '   <button type="submit" name="action" value="Create">',__("Create"),'</button>';
    }
    ?>
  </div> <!-- END div.caption -->

</form> <!-- END form -->
</div> <!-- END div before form -->
  <iframe name="groupadmin" id="groupadmin" scrolling="no" height="400px"></iframe>
</div></div>
<?php echo '<a href="index.php">[ ',__("Return to Main Menu"),' ]</a>'; ?>
</div> <!-- END div.main -->
</div> <!-- END div.page -->
<script type="text/javascript">
$('iframe').on('load', function() {
    this.style.height =
    this.contentWindow.document.body.offsetHeight + 'px';
}).attr({frameborder:0,scrolling:'no'});
</script>
<?php echo '
<!-- hiding modal dialogs here so they can be translated easily -->
<div class="hide">
    <div title="',__("Transfer all devices to another primary contact"),'" id="deletemodal">
        <div>Transfer all existing devices to <select id="copy"></select></div>
    </div>
    <div title="',__("Are you REALLY sure?"),'" id="doublecheck">
        <div id="modaltext" class="warning"><span style="float:left; margin:0 7px 20px 0;" class="ui-icon ui-icon-alert"></span>',__("Are you sure REALLY sure?  There is no undo!!"),'
        <br><br>
        </div>
    </div>
</div>'; ?>
</body>
</html>
