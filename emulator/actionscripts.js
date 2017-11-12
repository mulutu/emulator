/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

var previouse_func = "go_home";
var timmer_active = false;
var nextrefresh

loading = "<div style='margin:350px auto;width:100px;height:100px'><img alt='loading' src='images/progress.gif' /></div>";
					
function set_time(time_object){
	
	//document.getElementById("debug-div").innerHTML += "\nSajax:" + htmlEntities(sajax_debug_text);
	if(time_object['debug'] != "")
	{
		document.getElementById("debug-div").innerHTML += "\nDEBUG:" + htmlEntities(time_object['debug']);
	}
	sajax_debug_text = "";
	document.getElementById("notice_operator").innerHTML = "<a href='#' title='" + time_object['msisdn']+ "'>" + time_object['operator']+ "</a>";
	document.getElementById("notice_time").innerHTML = time_object['time'];
	document.getElementById("notice_message").innerHTML = time_object['messages'];
	if(! timmer_active)
		nextrefresh = setTimeout("get_time();", 10000);
}

function get_time(){
	x_get_time("", set_time);
}
	
function do_display(html_object){
	document.getElementById("display-screen").innerHTML = html_object['html'];
	if(html_object['debug'] != ""){
		//document.getElementById("debug-div").innerHTML += "\n" + html_object['debug']; //disable logs on interface
	}
				
	if(html_object['html'] == "<p>Invalid Session</p>")
	{					
		window.location = "createSession.php";
		//alert('session has expired; please log in!');
		return false;
	}
	return true;
}
	
function go_previouse(){
	get_time();
	switch (previouse_func){
		case "do_call":
			return do_call();
		case "go_messaging":
			return go_messaging();
		default:
			return go_home();
	}

}

function backspace(textvalue){
	textvalue = textvalue.substring(0, textvalue.length - 1);
	return textvalue; 
}
	
// load phone home page
function go_home() {
	document.getElementById("display-screen").innerHTML = loading;
	x_home_page("", do_display);				
}
			
// load dial pad page
function go_dialer(){
	document.getElementById("display-screen").innerHTML = loading;
	x_dial_page("", do_display);
		
}
	
// load call log page
function go_calllog(){
	document.getElementById("display-screen").innerHTML = loading;
	x_calllog_page("", do_display);
		
}

function go_browser(){
	document.getElementById("display-screen").innerHTML = loading;
	document.this = is_an_error;
	x_browser_page("", do_display);
}

function go_messaging(){
	document.getElementById("display-screen").innerHTML = loading;
	x_messaging_home_page("", do_display);
}

function do_new_message(){
	previouse_func = "go_messaging";
	document.getElementById("display-screen").innerHTML = loading;
	x_message_new_page("", do_display);	
}

function do_read_message(message_id){
	previouse_func = "go_messaging";
	document.getElementById("display-screen").innerHTML = loading;
	x_message_read_page(message_id, do_display);	
}

function go_profile(){
	document.getElementById("display-screen").innerHTML = loading;
	x_profile_page("HOME", do_display);
}

function browser_location(newLocation){
	document.getElementById('ibrowser').src = newLocation;	
}
	
// make a USSD call
function do_call(txtnumber){
	document.getElementById("display-screen").innerHTML = loading;
	x_make_call(txtnumber.value, do_display);		
}
			
function ussd_do_reply(txreply){
	document.getElementById("display-screen").innerHTML = loading;
	x_ussd_reply(txreply.value, do_display);
}

function sms_do_send(txnumber,txmessage){
	document.getElementById("display-screen").innerHTML = loading;
	//x_make_call(document.getElementById("txcallnumber").value, do_display);
	x_send_sms(txnumber.value,txmessage.value, do_display);
}
			
function ussd_do_exit(){
	document.getElementById("display-screen").innerHTML = loading;
	x_ussd_clear("", do_display);
}
				
function dialNumber(txreply){
	previouse_func = "do_call";
	document.getElementById("display-screen").innerHTML = loading;
	//x_make_call(document.getElementById("txcallnumber").value, do_display);
	x_make_call(txreply.value, do_display);
}
			
function message_inbox(){
	document.getElementById("display-screen").innerHTML = loading;
	x_list_messages("", do_display);
	return true;
}
			
function viewMessage(id){
	document.getElementById("display-screen").innerHTML = loading;
	x_view_message(id, do_display);
	return true;
}
	
function logout(){
	document.getElementById("display-screen").innerHTML = loading;
	x_log_out("", do_display);
	window.location = "createSession.php";
	return true;
}
	
function startBrowser(){
	document.getElementById("display-screen").innerHTML = loading;
	x_open_browser("", do_display);
	return true;
}

function htmlEntities(str) {
	return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
