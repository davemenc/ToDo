<?php
/*
        Copyright 2005 Dave Menconi
   Licensed under the Apache License, Version 2.0 (the "License");
   you may not use this file except in compliance with the License.
   You may obtain a copy of the License at
       http://www.apache.org/licenses/LICENSE-2.0
   Unless required by applicable law or agreed to in writing, software
   distributed under the License is distributed on an "AS IS" BASIS,
   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
   See the License for the specific language governing permissions and
   limitations under the License.
*/

/* $Id: index.php,v 2.7 2010/06/23 20:28:27 dmenconi Exp $ */
/*****************************************
 * INDEX.PHP -- TODO LIST
 * This is main routine for the todolist
 * It generates a list by default and 
 * various other pages as well as parsing
 * said pages and updating the database
 *****************************************/
	include_once('config.php');
	include_once('header.php');
	include_once('../library/miscfunc.php');
	include_once('../library/debug.php');
	include_once('../library/loc_login.php');
	include_once('../library/date.php');
	include_once "../library/mysql.php";
	include_once('../library/list.php');
	include_once('footer.php');
	include_once('../library/filtersql.php');
log_on();
debug_string("--------------------------------- START TODO PROGAM _-------");
$PARAMS = FilterSQL(array_merge($_POST,$_GET));
//$PARAMS = array_merge($_POST,$_GET);
// Create link to DB
$link = make_mysql_connect($dbhost, $dbuser, $dbpass, $dbname);
//log in user
$login = loc_GetAuthenticated($PARAMS,$link,$appword,$cookiename="login",$admin=false,$expiry=0,$title="Todo List", $color='#F0F0F0',"index.php",false);
if(!login){debug_string("login failed"); exit();}
$userno=loc_get_userno($link,$cookiename="login");
if(-1==$userno)JumpTo();

//debug_on();
debug_string("userno",$userno);
unset($PARAMS['password']);
debug_array("params",$PARAMS);
debug_string ("login succeeded");

	// set persistent sort order 
	/* possible valid values are
	  sp -- sequence then project
	  ps -- project then sequence
	  st -- status then sequence
	  stp -- status then project
	*/
	if (isset($PARAMS['order'])) $sortorder = $PARAMS['order'];
	else $sortorder = "st"; // defaults to sequence

	// set the mode
	if (isset($PARAMS['mode'])){$mode=$PARAMS['mode'];}
	else $mode = "display_page";

	debug_string("mode",$mode);

	switch($mode){
		case "d_prod":
			$fieldinfo[]=array('title'=>"ID",'field'=>"id");
			$fieldinfo[]=array('title'=>"Project",'field'=>"product");
			$fieldinfo[]=array('title'=>"Description",'field'=>"description");
			$fieldinfo[]=array('title'=>"Hidden",'field'=>"hidden");
			$dbinfo['index']="id";
			$dbinfo['fieldnames']=array("id","product","description","hidden");
			$dbinfo['tablenames']=array("product");
			$dbinfo['where']=array("userno='$userno'");
			$menu = page_menu();
			$displayinfo = array('mastertitle'=>'Project', 'actionkeyword'=>"mode", 'listname'=>"d_prod", 'addname'=>"a_prod", 'editname'=>"e_prod", 'editfield'=>"id", 'editindexkeyword'=>"indexno", 'editindexfield'=>"id", 'menu'=>$menu, 'color'=>"feffcc");
			ListData($link,$fieldinfo,$dbinfo,$displayinfo,$PARAMS,$PageLengths="");
			break;
		case "e_prod":
			$fields['product']="Project";
			$fields['description']="Description";
			$fields['hidden']="Hidden?";
			$form = EditRecord($link,'product','id',$PARAMS['indexno'],$fields,'pe_prod');
			print $form;
			break;
		case "a_prod":
			$fields['product']="Project";
			$fields['description']="Description";
			$form  = AddRecord($link,'product',$fields,"pa_prod");
			print $form;
			break;
		case "pa_prod":
			$defaults['userno']=$userno;
			ParseAddRecord($link,"product",$PARAMS,$defaults);
			JumpTo("index.php?mode=d_prod");
			break;
		case "pe_prod":
			ParseEditRecord($link,'product','id',$PARAMS);
			JumpTo("index.php?mode=d_prod");
			break;
		case "display_add":
			show_add_form();
			break;
		case "parse_add":
			parse_input();
			show_add_form();
			//display_page();
			break;
		case "display_weekly":
			$start = $PARAMS['start'];
			$end = $PARAMS['end'];
			display_weekly($link,$start,$end);
			break;
		case "parse_summary":
			parse_summary();
			display_page($sortorder);
			break;
		case "parse_detail":	
			parse_detail();
			break;
		case "detail":
			display_detail($PARAMS['item']);	
			break;
		case "done_page":
			display_done_page();
			break;
		case "display_page":
			display_page($sortorder);
			break;
		case "display_prstat":
			display_project_stats($link);
			break;
		case "logout":
			loc_delete_cookie($cookiename="login");
			JumpTo();
			break;
		case "test":
			break;
		default:
			debug_string("mode=$mode");
			display_page($sortorder);
	}
break_mysql_connect($link);
exit();		
/*****************************************
 * isallspaces()
 * Check string to see if it has non-white content
 * INPUT: string containing text
 * OUPUT: None
 * RETURN: true or false
 *****************************************/
 function isallspaces($s){
	debug_string("isallspaces($s)");
 	$spaces=chr(9).chr(10).chr(11).chr(12).chr(13).chr(32);
	$spacepat='/[^'.$spaces.']/';
	$x = preg_match($spacepat,$s);
 	return (0==preg_match($spacepat,$s));
 }
/*****************************************
 * parse_detail()
 * Parse input from detail form
 * INPUT: from form generated by form detail
 * OUTPUT: updates the database
 * RETURN: redisplays main list
 *****************************************/
function parse_detail(){
	global $link,$PARAMS;
	debug_string("parse_detail()");
	debug_array("params",$PARAMS);
	$today = date("Y-m-d",time());
	$userno=loc_get_userno($link,$cookiename="login");
	$table=$tableprefix."todolist";
	$idnum=$PARAMS['id'];
	$hidflag = 0;
	$doneflag=0;
	foreach ($PARAMS as $key=>$val){
		//debug_string("key($key)",$val);
		switch ($key){
		case "productid":
    		debug_string("$key",$val);
			$val = substr($val,1);
			debug_string("newval",$val);
			$sql = "update $table set productid='$val' where id=$idnum";
			debug_string("sql",$sql);
    		$result = mysql_query($sql,$link) or die(mysql_error());
			break;
		case "dn":
			$sql = "update $table set done=1,status='Done',donedate='$today' where id=$idnum";
			debug_string("sql",$sql);
    		$result = mysql_query($sql,$link) or die(mysql_error());
    		$doneflag=1;
    		continue;
    		break;
    	case "hd":
			$sql = "update $table set hidden=1,status='Deleted' where id=$idnum";
			debug_string("sql",$sql);
    		$result = mysql_query($sql,$link) or die(mysql_error());
    		$hidflag=1;
    		continue;
    		break;
    	case "status": 
    		debug_string("$key",$val);
    		$sethid=$setdone="";
			if('Done'==$val){
				$setdone=",done=1,donedate='$today' ";
				$doneflag=1;
			}
			if('Deleted'==$val){
				$sethid=",hidden=1 ";
				$hidflag=1;
			}
			$sql = "update $table set status='$val'$setdone $sethid where id=$idnum";
			debug_string("sql",$sql);
    		$result = mysql_query($sql,$link) or die(mysql_error());
    		continue;
    		break;
    	case "details":
    		debug_string("$key",$val);
			if(isallspaces($val)) $val="";
		case "delto":
    	case "seq": 
    	case "title":
			$sql = "update $table set $key='$val' where id=$idnum";
			debug_string("!!! sql ",$sql);
    		$result = mysql_query($sql,$link) or die(mysql_error());
    		continue;
    		break;
    	case "userno":
    	case "mode":
    	case "id":
    	case "order":
    	default: 
    		continue;
		}
	}
	if ($hidflag==0){
		$sql = "update $table set hidden=0 where id=$idnum";
		debug_string("sql",$sql);
    	$result = mysql_query($sql,$link) or die(mysql_error());
    }
    if ($doneflag==0){
		$sql = "update $table set done=0 where id=$idnum";
		debug_string("sql",$sql);
    	$result = mysql_query($sql,$link) or die(mysql_error());
    }
	JumpTo();
}
/*****************************************
 * parse_summary()
 * parses the done and sequence info from summary
 * INPUT: post data from summary page (display page)
 * OUTPUT: updates the database
 * RETURN: 
 *****************************************/
function parse_summary(){
	global $link,$PARAMS;
	debug_string("parse_summary()");

	debug_array("PARAMS",$PARAMS);
	$table=$tableprefix."todolist";
	$existing = get_tododata("select * from todolist where hidden=0");
	$today = date("Y-m-d",time());
	foreach ($PARAMS as $key=>$val){
		debug_string("key=$key, val=$val");
		if(strstr($key,"dn")){
//			debug_string("in done code");
//			debug_string("key=$key, val=$val");

			$idnum=substr($key,2);
			if ($existing[$idnum]['done']==$val) continue; // skip it if the new value is the same as the old value
//			debug_string("idnum",$idnum);
			if (1==$val) $t=",status='Done',donedate='$today'"; else $t="";
			debug_string ("t",$t);
			$sql = "update $table set done='$val' $t  where id='$idnum'";
			debug_string("parse summary 1",$sql);
    		$result = mysql_query($sql,$link) or die(mysql_error());
		}
		else if(strstr($key,"sq")) {
			$idnum=substr($key,2);
			if ($existing[$idnum]['seq']==$val) continue; // skip it if the new value is the same as the old value
			$sql = "update $table set seq='$val' where id='$idnum'";
			debug_string("parse summary 2",$sql);
	   		$result = mysql_query($sql,$link) or die(mysql_error());
		}
		else if(strstr($key,"st")) {
			$sethid=$setdone="";
			$idnum=substr($key,2);
			if ($existing[$idnum]['status']==$val) continue; // skip it if the new value is the same as the old value
			debug_string("idnum",$idnum);
			debug_string("val",$val);
			if('Done'==$val){
				$setdone=",done=1,donedate='$today' ";
			}
			if('Active'==$val){
				$setdone=",done=0 ";
				$sethid=",hidden=0 ";
			}
			if('Deleted'==$val){
				debug_string("val deleted",$val);
				$sethid=",hidden=1 ";
			}
			$sql = "update $table set status='$val'$setdone $sethid where id=$idnum";
			debug_string("parse summary 3",$sql);
    		$result = mysql_query($sql,$link) or die(mysql_error());
		}
	}
}
/*****************************************
 * get_tododata
 * Gets the todo data specified in $sql and indexes it by $id
 * INPUT: $sql, a valid sql select string
 * OUTPUT nothing
 * RETURN: returns a re indexed array or an empty array
 *****************************************/
function get_tododata($sql){
	global $link;
    $tododata=MYSQLGetData($link,$sql);
    $records = count($tododata);
    $result = array();
    if (0==$records)return $result;// if we have no records, just stop now
    // step through the data and reorder the array
    for($i=0;$i<$records;$i++){
    	$id=$tododata[$i]['id'];
		$result[$id]=$tododata[$i];
    }
    return $result;
}
/*****************************************
 * display_page()
 * displays the pending items by calling display_results
 * INPUT: None (well the database)
 * OUTPUT: Draws the table
 * RETURN: just exits
 *****************************************/
function display_page($order="sp"){
	global $footer,$header,$link,$usertable;
	debug_string("display_page(order=$order)");
	$userno=loc_get_userno($link);
	$admin = loc_get_usertype($link)=="admin";
	print "$header";
	$tbl=$tableprefix."todolist";
	if ($order=="ps"){
		debug_string("order ps");
		$orderby = "product,status,seq";
	} else if ($order=="sp"){
		debug_string("order sp");
		$orderby = "seq,product,status";
	}else if($order="st"){
		debug_string("order st");
		$orderby = "status,seq,product";
	}else { // stp
		debug_string("order st");
		$orderby = "status,product,seq";
	}
	if ($admin) {
		$sql1= "select $tbl.id,seq,product.product,title,details,done,status,productid,duration,$tbl.hidden,taskdate,donedate,status,todoTS,if(length(details)>0,'*','') as mark , username from product, $tbl,$usertable where $usertable.id=$tbl.userno and productid=product.id and done=0 and $tbl.hidden=0 order by $tbl.userno,$orderby";
	} else {
		$sql1= "select $tbl.id,seq,product.product,title,details,done,status,productid,duration,$tbl.hidden,taskdate,donedate,status,todoTS,if(length(details)>0,'*','') as mark from product, $tbl where $tbl.userno=$userno and product.userno=$userno and $tbl.userno=product.userno and productid=product.id and done=0 and $tbl.hidden=0 order by $orderby";
	}
	debug_string("display page sql1",$sql1);
	print"<h1>Pending Todo Items</h1>";
	display_results($sql1);
	print "<p>\n";
	print "<a href=\"index.php?mode=done_page\">Show Done Items</a><br>";
	print "$footer";
	debug_string("left display_page(order=$order)");
}
/*****************************************
 * display_done_page()
 * displays the completed items by calling display_results
 * INPUT: None (well the database)
 * OUTPUT: Draws the table
 * RETURN: just exits
 *****************************************/
function display_done_page(){
	global $header,$footer,$link;
	debug_string("display_done_page");
	$userno=loc_get_userno($link,$cookiename="login");
	print $header;
	$tbl=$tableprefix."todolist";
 	$sql2= "select $tbl.id,seq,product.product,title,details,done,status,productid,duration,$tbl.hidden,taskdate,donedate,status,todoTS,if(length(details)>0,'*','') as mark from product, $tbl where $tbl.userno=$userno and product.userno=$userno and $tbl.userno=product.userno and productid=product.id and $tbl.hidden=0 and status='Done' order by donedate desc, product,seq";

	print "<a href=\"index.php\">Show List of Pending Items</a><br>";
	print"<h1>Completed Todo Items</h1>";
	display_results($sql2);
}
/*****************************************
 * page_menu()
 * 
 *****************************************/
function page_menu(){
	global $link;
	debug_string("page_menu");
$menu =  <<<EOF
<TABLE>
  <TR>
    <TD><B>Filter:</B></TD>
    <TD>
		<a href="index.php">Show Todo Items </a> |
		<a href="index.php?mode=display_weekly">Show Weekly Report  </a> |
		<a href="index.php?mode=done_page"">Show Done Items</a>&nbsp;&nbsp;&nbsp;
    </TD>
    <TD><B>Project:</B></TD>
    <TD> 
    	<a href="index.php?mode=d_prod">Show Projects</a>  </a> |
    	<a href="index.php?mode=display_prstat">Show Project Stats  </a> |
    	<a href="index.php?mode=a_prod">Add Project </a> 
    </TD>
  </TR>
  <TR>
    <TD><B>Other:</B></TD>
    <TD>
    	<a href="index.php?mode=display_add">Add New Todo Items</a> |
    	<a href="index.php?mode=logout">Log Out</a>
    </TD>
    <TD><B>Sort:</B></TD>
    <TD>
		<a href="index.php?order=ps">Order By Project </a> |
		<a href="index.php?order=st">Order by Status</a> |
		<a href="index.php?order=sp">Order By Seq  </a> 
    </TD>
  </TR>
</TABLE>
EOF;
//	$admin = loc_get_usertype($link)=="admin";
	$menu .= "<br>";
	return $menu;
}
/*****************************************
 * display_results()
 * display a table based on the query passed in
 * INPUT: $myquery which is a select
 * OUTPUT: displays the results of the select in a table
 * RETURN: post form that returns changes to the done and seq fields
 *****************************************/
function display_results($myquery){
	global $link,$sortorder;
	debug_string("display_results()",$myquery);

	$admin = loc_get_usertype($link)=="admin";

	$todolist = get_tododata($myquery);
   // $result = mysql_query($myquery,$link) or die(mysql_error());
    //$num_rows = mysql_num_rows($result);
    $num_rows = count($todolist);
    print page_menu();
	if ($num_rows>0){
		print "Total Incomplete Items: $num_rows<br>\n";
		print"<form method=\"POST\" action=\"index.php\">\n";
		print "<input type=\"hidden\" name=\"mode\" value=\"parse_summary\"/>\n";
		print "<input type=\"hidden\" name=\"order\" value=\"$sortorder\"/>\n";
		print "<input type=\"submit\" value=\"Update Changes\">\n";
		print"<table border=0>\n";
		if ($admin){
			print"<tr><td><b>Done</b></td><td><b>Seq</b></td><td><b>Status</b></td><td><b>User</b></td><td><b>Project</b></td><td ><b>Title</b></td></tr>";//<td><b>Details</b></td></tr>\n";
		} else {
			print"<tr><td><b>Done</b></td><td><b>Seq</b></td><td><b>Status</b></td><td><b>Project</b></td><td ><b>Title</b></td></tr>";//<td><b>Details</b></td></tr>\n";
		}
		//while ($todo=mysql_fetch_array($result)){
		foreach ($todolist as $key=>$todo){
			$id=$todo['id'];
			$doneval="";
			if ($todo['done']==1){$doneval=" checked ";}
			print "<tr>\n";
			print "<td> <input name=dn$id type=checkbox value=1 $doneval></td>";
			print "	<td><input name=sq$id type=text size=3 value=".$todo['seq']."></td>\n";
    		$vals=array('Active','Deferred','Delegated','Deleted','Done','Reference');
			print "<td><select  name=\"st$id\">";
			foreach($vals as $val){
				if($todo['status']==$val) $select  = "selected"; else $select = "";
				print "<option value=\"$val\" $select>$val";
			}
			print "</select></td>\n";

			if ($admin) print "	<td>".$todo['username']."</td>\n";
			print "	<td>".$todo['product']."</td>\n";
			print "	<td><a href=\"index.php?mode=detail&item=$id&order=$sortorder\"> ".$todo['title']."</a>". $todo['mark']."</td>\n";
			if ($todo['status']=="Done") print "<td>".$todo['donedate']."</td>\n";
			//print "	<td>".$todo['details']."</td> \n";
			print "</tr>\n";
		}
		print"</table>\n";
		print "<input type=\"submit\" value=\"Update Changes\">\n";
		print "</form>\n";
		print "Total Incomplete Items: $num_rows<br>\n";
	} 
	debug_string("left diplay_results");
}
/*****************************************
 * display_detail()
 * displays the detail for for editing
 * INPUT: the item number in $itemnum
 * OUTPUT: a post form with most of the fields in the record
 * RETURN: The field names need to match the database names
 *****************************************/
function display_detail($itemnum){
	global $link,$sortorder;
	global $footer,$header;
	$userno=loc_get_userno($link,$cookiename="login");
	debug_string("display_detail($itemnum)");

	// print the header string
	print "$header";

	// get the product information
	$sql="select * from product where hidden=0 and userno=$userno order by product";
	$products = MYSQLGetData($link,$sql);
	//debug_array("products",$products);

	// get the todo information
	$tbl=$tableprefix."todolist";
	$sql="SELECT * from $tbl where userno=$userno and  id=$itemnum limit 1";
	debug_string("sql",$sql);
	$records = MYSQLGetData($link,$sql);
	//debug_array("records",$records);
	$record=$records[0];
	debug_array("record",$record);
	$num_rows = count($records);
	debug_string("num_rows",$num_rows);
	$productid=$record['productid'];
	debug_string("productid",$productid);

	// create the product menu
	$productmenu = " <select  name=\"productid\">";
	debug_string("productid",$productid);
	foreach($products as $precord){
		$product=$precord['product'];
		$id = "i".$precord['id'];
		$select="";
		if ($productid==$precord['id']) $select="SELECTED";
		$productmenu.= "<option value=\"$id\" $select>$product\n";
	}
	$productmenu.= "</select>";
	$id = $record['id'];
	print "<html>\n<head>\n<Title>Todo Details</title>\n</head>\n";
	print "<body>\n";
	print "<h2>".$record['title']." ($id)</h2>\n";
	print "<font size=-1><a href=\"index.php?order=$sortorder\">Return to list</a><br><br>\n";
	//do the form
	print "<form method=\"post\" name=\"updaterecord\" action=\"index.php\">\n";
	print "<input type=\"hidden\" name=\"mode\" value=\"parse_detail\"/>\n";
	print "<input type=\"hidden\" name=\"id\" value=\"".$record['id']."\"/>\n";
	print "<input type=\"hidden\" name=\"order\" value=\"$sortorder\"/>\n";
	print "<table>\n";
	print "<tr><td><b>Title:</b></td><td> <input type=text name=\"title\" value=\"".$record['title']."\" size=70></td></tr>\n";
	print "<tr><td><b>Sequence:</b></td><td> <input type=text name=\"seq\" value=\"".$record['seq']."\" size=5></td></tr>\n";
	print "<tr><td><b>Project:</b></td><td> ".$productmenu. "</td></tr>\n";
	
    $vals=array('Active','Deferred','Delegated','Deleted','Done','Reference');

	$statusmenu= "<tr><td><b>Status:</b></td><td> <select name=\"status\">";
	$status=$record['status'];
	debug_array("record",$record);
	debug_string("status",$status);
	foreach($vals as $val){
		if($status==$val) $select  = "selected"; else $select = "";
		$statusmenu .= "<option value=\"$val\" $select>$val\n";
	}
	$statusmenu.="</select></td></tr>\n";
	print $statusmenu;
	print "<tr><td><b>Delegated To:</b></td><td> <input type=text name=\"delto\" value=\"".$record['delto']."\" size=15></td></tr>\n";
	print  "<tr><td><b>Last Change:</b></td><td>". $record['todoTS']."</td></tr>\n";
	print "</table>\n";
	print "<br><b>Details: </b><br>\n";
	print "<textarea name=\"details\" cols=80 rows=12>\n";
	print $record['details']."\n";
	print "</textarea><br>\n";


	if($record['done']){
		print "<b>Done!</b><br>\n";
	}else{
		print "<b>Not Done!</b><br>\n";
	}
	if ($record['done']==1){$doneval=" checked ";}
	print "<b>Done:</b> <input name=dn type=checkbox value=1 $doneval><br>";
	if ($record['hidden']==1){$hidval=" checked ";}
	print "<b>Delete:</b>  <input name=hd type=checkbox value=1 $hidval><br>";
	print "<input type=\"submit\" value=\"Update Item\">\n";
	print "</form>\n";
	print "$footer";
}
/*****************************************
 * display_weekly()
 * displays the data that should be put in the weekly report for the date range
 * INPUT:  link - a valid connection to the mysql database 
 *         start date and end date, start and end are in DB format
 * OUTPUT: a display of the weekly report (in wiki markup) for the date range
 * RETURN: none
 *****************************************/
function display_weekly($link,$start,$end){
	global $header,$footer;
	debug_string("display_weekly($start,$end)");
	debug_string("today",date("m/d/Y"));
	//debug_string("start",$start);
	//debug_string("end",$end);
	$userno=loc_get_userno($link,$cookiename="login");

	// fix start and end, if necessary
	$nstart = getstart($start);
	$nend = getend($end);
	$sql = "select todolist.id,seq,done,title,productid,duration,todolist.hidden,taskdate,donedate,status,todoTS,product.product from todolist,product where todolist.userno=$userno and product.userno=$userno and todolist.userno=product.userno and productid=product.id and todolist.hidden=0 and status='Done' and donedate>='$nstart' and donedate<='$nend' order by productid,donedate,seq";
	debug_string("sql 1",$sql);
	$dones = MYSQLGetData($link,$sql);
	debug_array("done",$dones);
	$donecount = count($dones);
	if ($donecount<10) $donecount=10;
	$sql = "select todolist.id,seq,title, done,productid,duration,todolist.hidden,taskdate,donedate,status,todoTS,product.product from todolist,product  where todolist.userno=$userno and product.userno=$userno and todolist.userno=product.userno and productid=product.id and  todolist.hidden=0 and status='Active' order by seq,productid,donedate desc limit $donecount";
	debug_string("sql 2",$sql);
	$todos = MYSQLGetData($link,$sql);
	debug_array("todo",$todos);

	// display page
	print $header;
	print page_menu()."<br>\n";
	print "<h1>Weekly Todo List</h1>";
	print "<b>Start: $start to End: $end </b><br>";
	print "(Start: $nstart to End: $nend <a href=\"index.php?mode=display_weekly&start=$nstart&end=$nend\">GO</a>)<br><br><br>";
	foreach($dones as $done){
		$title = ucwords($done['title']);
		$prod = $done['product'];
		print "* $prod: $title<br>\n";
	}
	print "<br>To Do <br><br>\n";
	foreach($todos as $todo){
		$title = ucwords($todo['title']);
		$prod = $todo['product'];
		print "* $prod: $title<br>\n";
	}
	print $footer;
}
/*****************************************
 *****************************************/
function getstart($start){
	debug_string("getstart($start)");
	if(""==$start) $epoch = time();
	else $epoch = datearray_to_epoch(parse_dbdate($start));
	debug_string("startdate",date("D,m/d/y",$epoch));
	$secsinday = 86400;
	$dow = date("w",$epoch);
	$e=$epoch;
	//step backward day by day until we get to a monday
	while (date("w",$e)!=1){//looking for Monday
		$e-=$secsinday;
	}
	debug_string("getstart result", date("D, m/d/y",$e));
	return date("Y-m-d",$e);
}
/*****************************************
 *****************************************/
function getend($end){
	debug_string("getend($end)");
	if(""==$end) $epoch = time();
	else $epoch = datearray_to_epoch(parse_dbdate($end));
	debug_string("enddate",date("D,m/d/y",$epoch));
	$secsinday = 86400;
	$dow = date("w",$epoch);
	$e=$epoch;
	//step forward day by day until we get to a friday
	while (date("w",$e)!=5){//looking for Friday
		$e+=$secsinday;
	}
    $result = date("Y-m-d",$e);
    // ok now add the time to ensure Friday is included
    $result .= " 23:59:59";

	debug_string("getend result", date("D, m/d/y",$e));
    return $result;
}
/*****************************************
 *****************************************/
function add_new_productname($productname){
	global $link;
	debug_string("add_new_productname($productname)");
	$sql="insert into product values ('','$productname')";
	//$result = mysql_query($sql,$link) or die(mysql_error());
}
	
/*****************************************
 * parse_input()
 * parses the data collected by the form
 * INPUT: none
 * OUTPUT: changes the database
 * RETURN: back to the show form stuff
 *****************************************/
function parse_input(){
	global $link,$PARAMS;
	debug_string("parse_input()");
	$userno=loc_get_userno($link,$cookiename="login");
	$done=0;
	if ("y"==$PARAMS['done']||"Y" == $PARAMS['done']){
		$done=1;
	}
	$today = date("Y-m-d",time());
	debug_string("today",$today);
	$id = substr($PARAMS['product'],1);
	debug_string("id",$id);
 	if (isallspaces($PARAMS['title'])) $title="No Title";
 	else $title = $PARAMS['title'];
	$sql="insert into todolist (seq,title,details,done,productid,userno,status,taskdate) values ('".$PARAMS['seq']."','".$title."','".$PARAMS['details']."','$done','$id','$userno','".$PARAMS['status']."','$today')";
	debug_string("sql",$sql);
	//execute the SQL Statement
	$result = mysql_query($sql,$link) or die(mysql_error());
}
/*****************************************
 * show_add_form()
 *  displays the add todo item form 
 * INPUT: none
 * OUTPUT: post data
 * RETURN: reenters with the from data
 *****************************************/
function show_add_form(){
	global $link, $footer,$header,$PARAMS,$sortorder;
	debug_string("show_add_form()");
	$self=$_SERVER['PHP_SELF'];
	$userno=loc_get_userno($link,$cookiename="login");

	// output header string
	print"$header";
	$sql="select * from product where userno=$userno and hidden=0 order by product";
	debug_string("sql",$sql);
	$result = mysql_query($sql,$link) or die(mysql_error());

	if(!isset($PARAMS['seq'])) $new_seq=10;
	else $new_seq = $PARAMS['seq'];//+1;
echo <<<EOF
<h1> Add A Todo Item To the List</h1>
<a href=index.php?order=$sortorder>Display List of Todo Items </a><br><br>
<form action = "index.php" method=post>
<input type="hidden" name="mode" value="parse_add">
<input type="hidden" name="order" value="$sortorder">
<table>
<tr><td><b>Seq: </b></td>
<td><input type=text name="seq" size=5 value="$new_seq"></td></tr>
<tr><td><b>Project:&nbsp;</b></td>
<td><select  name="product">
EOF;
	while ($products = mysql_fetch_array($result)) {
		$product=$products['product'];
		$id = "i".$products['id'];
		$select="";
		if ($PARAMS['product']==$id) {$select="SELECTED";}
		print "<option value=\"$id\" $select>$product";
	}
echo <<<EOF
</select></td></tr>
<tr><td><b>Title:</b></td>
<td><input type=text name="title" size=80></td></tr>
<tr><td><b>Status:</b></td>
EOF;
print "\n<td><select  name=\"status\"><option value=\"Active\" selected>Active<option value=\"Deferred\">Deferred<option value=\"Delegated\">Delegated<option value=\"Delete\">Delete<option value=\"Done\">Done<option value=\"Reference\">Reference</td></tr></table>";
echo <<<EOF

<br>
<b>Details</b><br>
<textarea name="details" rows="9" cols="80" ></textarea><br>
<input type="submit" value="insert record"> 
</form>


EOF;
print "$footer";
}
/******************************************************************
 * display_project_stats
  ******************************************************************/
function display_project_stats($link){
	global $header,$footer;
	debug_string("display_project_stts()");
	$userno=loc_get_userno($link,$cookiename="login");
	$sql = "select * from product   where product.userno=$userno and  hidden=0 order by product";
	debug_string("sql 1",$sql);
	$products = MYSQLGetData($link,$sql);
	//debug_array("products",$products);
	for ($i=0;$i<count($products);$i++){
		// get product id number
		$productid=$products[$i]['id'];

 		//get todo stats on that product/project
		$sql = "select count(*) as count,sum(duration) as duration from todolist where todolist.userno=$userno and productid='$productid' and done=0";
	    debug_string("sql 2",$sql);
		$todostats = MYSQLGetData($link,$sql);
		//debug_array("todostats",$todostats);
		//add stats to the product/project array
		$products[$i]['count'] = $todostats[0]['count'];	
		$products[$i]['duration'] = $todostats[0]['duration'];	

 		//get done stats on that product/project
		$sql = "select count(*) as count,sum(duration) as duration from todolist where  todolist.userno=$userno and productid='$productid' and done=1";
	    debug_string("sql 3",$sql);
		$todostats = MYSQLGetData($link,$sql);
		//debug_array("todostats",$todostats);
		//add stats to the product/project array
		$products[$i]['donecount'] = $todostats[0]['count'];	
		$products[$i]['doneduration'] = $todostats[0]['duration'];	
	}

	//Print Page
	print $header;
	print page_menu()."<br>\n";
	print "<center><h1>Project Stats</h1></center>";
	print "<center<table border=1 cellpadding=5></center>\n";
	print "<tr><td><b>Project&nbsp;&nbsp;</b></center></td><td><center><b>Todo Count</b></center></td<td><b><center>Todo Time</b></center></td><td><b><center>Done Count</b></center></td><td><b><center>Done Time</b></center></td></tr>\n";
	foreach ($products as $prod){
		print "<tr><td>".$prod['product']."</td><td><center>".$prod['count']."</td><td><center>".$prod['duration']."</td><td><center>".$prod['donecount']."</td><td><center>".$prod['doneduration']."</td></tr>\n";
	}
	print "</table>\n";
	print $footer;
	exit();
}
/******************************************************************
 * FilterSQL
  ******************************************************************/
/*function FilterSQL($s){
	$result = str_replace("'","\'",$s);
	return $result;
}*/
?>
