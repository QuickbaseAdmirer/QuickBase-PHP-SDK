<?php
 /*----------------------------------------------------------------------
 Title : QuickBase PHP SDK
 Author : Joshua McGinnis (joshua_mcginnis@intuit.com)
 Description : The QuickBase PHP SDK is a simple class for interaction with the QuickBase REST API.
 The QuickBase API is well documented here:
 https://www.quickbase.com/up/6mztyxu8/g/rc7/en/va/QuickBaseAPI.htm
 
 License: Eclipse Public License
 http://www.eclipse.org/legal/epl-v10.html
 -----------------------------------------------------------------------*/
 
 ini_set('display_errors', 'on'); // ini setting for turning on errors
 Class QuickBase {
 /*---------------------------------------------------------------------
 // User Configurable Options
 -----------------------------------------------------------------------*/
	var $user_name   = ''; 	// QuickBase user who will access the QuickBase
	var $passwd      = '';	// Password of this user
	var $user_token      = '';	// Valid user token
	var $db_id       = ''; 	// Table/Database ID of the QuickBase being accessed
	var $app_token   = '';
	var $xml         = true;
	var $user_id     = 0;
	var $qb_site     = "www.quickbase.com";
	var $qb_ssl      = "https://www.quickbase.com/db/";
	var $ticketHours = '';	
 /*---------------------------------------------------------------------
 //	Do Not Change
 -----------------------------------------------------------------------*/
	var $input  = "";
	var $output = "";
	var $ticket = '';
 /* --------------------------------------------------------------------*/	
	public function __construct($un, $pw, $user_token='', $usexml = true, $db = '', $token = '', $realm = '', $hours = '') {
	    
		if($un) {
			$this->user_name = $un;
		}
		if($pw) {
			$this->passwd = $pw;
		}
		
		if($user_token) {
			$this->user_token = $user_token;
		}
		
		if($db) {
			$this->db_id = $db;
		}
		if($token)
			$this->app_token = $token;
		if($realm) {
			$this->qb_site = $realm . '.quickbase.com';
			$this->qb_ssl = 'https://' . $realm . '.quickbase.com/db/';
		}
		if($hours) {
			$this->ticketHours = (int) $hours;
		}		
		$this->xml = $usexml;
		
		if ($this->username)
		{
            $uid = $this->authenticate();
        
            if($uid) {
                $this->user_id = $uid;
            }
        }
		
	}
	public function set_xml_mode($bool) {
		$this->xml = $bool;
	}
	public function set_database_table($db) {
		$this->db_id = $db;
	}
	private function transmit($input, $action_name = "", $url = "", $return_xml = true) {	
		if($this->xml) {
			if($url == "") {
				$url = $this->qb_ssl. $this->db_id;
			}	
			$content_length = strlen($input);
			$headers = array(
				"POST /db/".$this->db_id." HTTP/1.0",
				"Content-Type: text/xml;",
                                "Accept: text/xml",
                                "Cache-Control: no-cache",
                                "Pragma: no-cache",
				"Content-Length: ".$content_length,
				'QUICKBASE-ACTION: '.$action_name
			);
			$this->input = $input;
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $input);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, FALSE);
		}
		else
		{
			$ch = curl_init($input);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_POST, true); 
			$this->input = $input;
		}
		$r = curl_exec($ch);
		if($return_xml) {
			$response = new SimpleXMLElement($r);
		}
		else {
			$response = $r;
		}
		return $response;
	}
	/* API_Authenticate: http://www.quickbase.com/api-guide/index.html#authenticate.html */
	public function authenticate() {
	    
		if($this->xml) {
			$xml_packet = new SimpleXMLElement('<qdbapi></qdbapi>');
			$xml_packet->addChild('username',$this->user_name);
			$xml_packet->addChild('password',$this->passwd);
			if ($this->ticketHours)
				$xml_packet->addChild('hours',$this->ticketHours);
				
            // this doesn't get called when used in usertoken mode
			$xml_packet->addChild('ticket',$this->ticket);
			$xml_packet = $xml_packet->asXML();
			$response = $this->transmit($xml_packet, 'API_Authenticate', $this->qb_ssl."main");
		}
		else {
			$url_string = $this->qb_ssl . "main?act=API_Authenticate&username=" . $this->user_name ."&password=" . $this->passwd;
			$response = $this->transmit($url_string);
		}
		if($response) {
			$this->ticket = $response->ticket;
			$this->user_id = $response->userid;
		}
	}
	/* API_AddField: http://www.quickbase.com/api-guide/index.html#add_field.html */
	public function add_field ($field_name, $type) {
		if($this->xml) {
			$xml_packet = new SimpleXMLElement('<qdbapi></qdbapi>');
			$xml_packet->addChild('label',$field_name);
			$xml_packet->addChild('type',$type);
			
			if ($this->user_token)
			{
    			$xml_packet->addChild('usertoken',$this->user_token);
			}
			else
			{
    			$xml_packet->addChild('ticket',$this->ticket);
    		}
			
			$xml_packet = $xml_packet->asXML();
			$response = $this->transmit($xml_packet, 'API_AddField');
		}
		else {
		    
			if ($this->user_token)
			{
    			$url_string = $this->qb_ssl . $this->db_id. "?act=API_AddField&usertoken=". $this->user_token ."&label=" .$field_name."&type=".$type;
    		}
    		else
    		{
    			$url_string = $this->qb_ssl . $this->db_id. "?act=API_AddField&ticket=". $this->ticket ."&label=" .$field_name."&type=".$type;
    		}
			
			$response = $this->transmit($url_string);
		}
		if($response->errcode == 0) {
			return $response->fid;
		}
		return false;
	}
	/* API_AddRecord: http://www.quickbase.com/api-guide/index.html#add_record.html */
	public function add_record ($fields, $uploads = false) {
		if($this->xml) {
			$xml_packet = new SimpleXMLElement('<qdbapi></qdbapi>');
			$i = intval(0);
			foreach($fields as $field) {
				$safe_value = preg_replace('/&(?!\w+;)/', '&', $field['value']);
				$xml_packet->addChild('field',$safe_value);
				$xml_packet->field[$i]->addAttribute('fid', $field['fid']);
				$i++;
			}
			if ($uploads) {
				foreach ($uploads as $upload) {
					$xml_packet->addChild('field', $upload['value']);
					$xml_packet->field[$i]->addAttribute('fid', $upload['fid']);
					$xml_packet->field[$i]->addAttribute('filename',$upload['filename']);
					$i++;
				}
			}
			if ($this->app_token)
				$xml_packet->addChild('apptoken', $this->app_token);
			
			if ($this->user_token)
			{
    			$xml_packet->addChild('usertoken',$this->user_token);
			}
			else
			{
    			$xml_packet->addChild('ticket',$this->ticket);
			}
			
			$xml_packet = $xml_packet->asXML();	
			$response = $this->transmit($xml_packet, 'API_AddRecord');
		}
		else {
			
			if ($this->user_token)
			{
    			$url_string = $this->qb_ssl . $this->db_id. "?act=API_AddRecord&usertoken=". $this->user_token;
			}
			else
			{
    			$url_string = $this->qb_ssl . $this->db_id. "?act=API_AddRecord&ticket=". $this->ticket;
			}

			
			foreach ($fields as $field) {
					$url_string .= "&_fid_" . $field['fid'] . "=" . urlencode($field['value']) . "";
				}
			$response = $this->transmit($url_string);
		}
			if($response) {
				return $response;
			}
			return false;
	}
	/* API_ChangePermission: https://www.quickbase.com/up/6mztyxu8/g/rc7/en/va/QuickBaseAPI.htm#_Toc126579974 */
	public function change_permission($uname, $modify, $view, $create, $save_views, $delete, $admin) {
		if($this->xml) {
			$xml_packet = new SimpleXMLElement('<qdbapi></qdbapi>');
			$xml_packet->addChild('uname',$uname);
			$xml_packet->addChild('modify',$modify);
			$xml_packet->addChild('view',$view);
			$xml_packet->addChild('create',$create);
			$xml_packet->addChild('saveviews',$save_views);
			$xml_packet->addChild('delete',$delete);
			$xml_packet->addChild('admin',$admin);
			
			if ($this->user_token)
			{
    			$xml_packet->addChild('usertoken',$this->user_token);
			}
			else
			{
    			$xml_packet->addChild('ticket',$this->ticket);
			}
			
			
			$xml_packet = $xml_packet->asXML();			
			$response = $this->transmit($xml_packet, 'API_ChangePermission');
		}
		else {
		    
		    
			$url_string = $this->qb_ssl . $this->db_id. "?act=API_ChangePermission"
					."&uname=".$uname
					."&modify=".$modify
					."&view=".$view
					."&create=".$create
					."&saveviews=".$save_views
					."&delete=".$delete
					."&admin=".$admin;
				
			if ($this->user_token)
			{
    			$url_string .= "&usertoken=".$this->user_token;
			}
			else
			{
    			$url_string .= "&ticket=". $this->ticket;
			}
			
					
			$response = $this->transmit($url_string);
		}
		if($response) {
			return $response;
		}
		return false;
	}
	/* API_ChangeRecordOwner: http://www.quickbase.com/api-guide/index.html#change_record_owner.html */
	public function change_record_owner($new_owner, $rid){
		if($this->xml) {
			$xml_packet = new SimpleXMLElement('<qdbapi></qdbapi>');
			$xml_packet->addChild('rid',$rid);
			$xml_packet->addChild('newowner',$new_owner);
			if ($this->user_token)
			{
    			$xml_packet->addChild('usertoken',$this->user_token);
			}
			else
			{
    			$xml_packet->addChild('ticket',$this->ticket);
			}

			$xml_packet = $xml_packet->asXML();				
			$response = $this->transmit($xml_packet, 'API_ChangeRecordOwner');
		}
		else {
			$url_string = $this->qb_ssl . $this->db_id. "?act=API_ChangeRecordOwner&ticket=". $this->ticket
					."&rid=".$rid
					."&newowner=".$new_owner;
			$response = $this->transmit($url_string);
		}
		if($response) {
			return true;
		}
		return false;
	}
	/* API_CloneDatabase: http://www.quickbase.com/api-guide/index.html#clone_database.html */
	public function clone_database($new_name, $new_desc, $keep_data = 1){
		if($this->xml) {
			$xml_packet = new SimpleXMLElement('<qdbapi></qdbapi>');
			$xml_packet->addChild('newdbname',$new_name);
			$xml_packet->addChild('newdbdesc',$new_desc);
			$xml_packet->addChild('keepData',$keep_data);
			if ($this->user_token)
			{
    			$xml_packet->addChild('usertoken',$this->user_token);
			}
			else
			{
    			$xml_packet->addChild('ticket',$this->ticket);
			}

			$xml_packet = $xml_packet->asXML();
			$response = $this->transmit($xml_packet, 'API_CloneDatabase');
		}
		else {
			$url_string = $this->qb_ssl . $this->db_id. "?act=API_CloneDatabase&ticket=". $this->ticket
					."&newdbname=".$new_name
					."&newdbdesc=".$new_desc
					."&keepData=".$keep_data;
			$response = $this->transmit($url_string);
		}
		if($response) {
			return $response->newdbid;
		}
		return false;
	}
	/* API_CreateDatabase: http://www.quickbase.com/api-guide/index.html#create_database.html */
	public function create_database($db_name, $db_desc) {
		if($this->xml) {
			$xml_packet = new SimpleXMLElement('<qdbapi></qdbapi>');
			$xml_packet->addChild('dbname',$db_name);
			$xml_packet->addChild('dbdesc',$db_desc);
			if ($this->user_token)
			{
    			$xml_packet->addChild('usertoken',$this->user_token);
			}
			else
			{
    			$xml_packet->addChild('ticket',$this->ticket);
			}

			$xml_packet = $xml_packet->asXML();	
			$response = $this->transmit($xml_packet, 'API_CreateDatabase');
		}
		else {
			$url_string = $this->qb_ssl . $this->db_id. "?act=API_CreateDatabase&ticket=". $this->ticket
					."&dbname=".$db_name
					."&dbdesc=".$db_desc;
			$response = $this->transmit($url_string);
		}
		if($response) {
			return $response->dbid;
		}
		return false;
	}
	/* API_DeleteDatabase: http://www.quickbase.com/api-guide/index.html#delete_database.html */
	public function delete_database() {
		if($this->xml) {
			$xml_packet = new SimpleXMLElement('<qdbapi></qdbapi>');
			if ($this->user_token)
			{
    			$xml_packet->addChild('usertoken',$this->user_token);
			}
			else
			{
    			$xml_packet->addChild('ticket',$this->ticket);
			}

			$xml_packet = $xml_packet->asXML();			
			$response = $this->transmit($xml_packet, 'API_DeleteDatabase');
		}
		else {
		$url_string = $this->qb_ssl . $this->db_id. "?act=API_DeleteDatabase&ticket=". $this->ticket;
		$response = $this->transmit($url_string);
		}
		if($response) {
			return true;
		}
		return false;
	}
	/* API_DeleteField: http://www.quickbase.com/api-guide/index.html#delete_field */
	public function delete_field($fid) {
		if($this->xml) {
			$xml_packet = new SimpleXMLElement('<qdbapi></qdbapi>');
			$xml_packet->addChild('fid',$fid);
			if ($this->user_token)
			{
    			$xml_packet->addChild('usertoken',$this->user_token);
			}
			else
			{
    			$xml_packet->addChild('ticket',$this->ticket);
			}

			$xml_packet = $xml_packet->asXML();
			$response = $this->transmit($xml_packet, 'API_DeleteField');
		}
		else {
			$url_string = $this->qb_ssl . $this->db_id. "?act=API_DeleteField&ticket=". $this->ticket
					."&fid=".$fid;
		$response = $this->transmit($url_string);
		}
		if($response) {
			return true;
		}
		return false;
	}
	/* API_DeleteRecord: http://www.quickbase.com/api-guide/index.html#delete_record.html */
	public function delete_record($rid) {
		if($this->xml) {
			$xml_packet = new SimpleXMLElement('<qdbapi></qdbapi>');
			$xml_packet->addChild('rid',$rid);
			if ($this->user_token)
			{
    			$xml_packet->addChild('usertoken',$this->user_token);
			}
			else
			{
    			$xml_packet->addChild('ticket',$this->ticket);
			}

			$xml_packet = $xml_packet->asXML();			
			$response = $this->transmit($xml_packet, 'API_DeleteRecord');
		}
		else {
			$url_string = $this->qb_ssl . $this->db_id. "?act=API_DeleteRecord&ticket=". $this->ticket
					."&rid=".$rid;
			$response = $this->transmit($url_string);
		}
		if($response) {
			return true;
		}
		return false;
	}
	/* API_DoQuery: http://www.quickbase.com/api-guide/index.html#do_query.html */
	public function do_query($queries =0, $qid= 0, $qname=0, $clist = 0, $slist=0, $fmt = 'structured', $options = "") {
		if($this->xml) {
			//A query in queries has the following items in this order:
			//field id, evaluator, criteria, and/or
			//The first element will not have an and/or
			$xml_packet='<qdbapi>';
			$pos = 0;
			if ($queries) {
				$xml_packet.='<query>';
				foreach ($queries as $query) {
					$criteria = "";
					if($pos > 0) {
						$criteria .= $query['ao'];
					}
					$criteria .= "{'" . $query['fid'] . "'."
						. $query['ev'] . ".'"
						. $query['cri']."'}";
					$xml_packet.= $criteria;
					$pos++;
				}
				$xml_packet.='</query>';
			}
			else if ($qid) {
				$xml_packet .= '<qid>'.$qid.'</qid>';
			}
			else if ($qname) {
				$xml_packet .= '<qname>'.$qname.'</qname>';
			}
			else {
				return false;
			}
			$xml_packet .= '
			<fmt>'.$fmt.'</fmt>';
			if($clist) $xml_packet .= '<clist>'.$clist.'</clist>';
			if($slist) {
				$xml_packet .= '<slist>'.$slist.'</slist>';
				$xml_packet .= '<options>'.$options.'</options>';
			}
			if ($this->app_token)
				$xml_packet .= '<apptoken>' . $this->app_token . '</apptoken>';
			$xml_packet .= '<ticket>'.$this->ticket.'</ticket>
				</qdbapi>';
		$response = $this->transmit($xml_packet, 'API_DoQuery');
		}
		else { // If not an xml packet
			$url_string = $this->qb_ssl . $this->db_id. "?act=API_DoQuery&ticket=". $this->ticket
					."&fmt=".$fmt;
			$pos = 0;
			if ($queries) {
				$url_string .= "&query=";
				foreach ($queries as $query) {
					$criteria = "";
					if($pos > 0) {
						$criteria .= $query['ao'];
					}
					$criteria .= "{'" . $query['fid'] . "'."
						. $query['ev'] . ".'"
						. $query['cri']."'}";
					$url_string.= $criteria;
					$pos++;
				}
			}
			else if ($qid) {
				$url_string .= "&qid=".$qid;
			}
			else if ($qname) {
				$url_string .= "&qname=".$qname;
			}
			else {
				return false;
			}
			if($clist) $url_string .= "&clist=".$clist;
			if($slist) $url_string .= "&slist=".$slist;
			if($options) $url_string .= "&options=".$options;
			$response = $this->transmit($url_string);
		}
		if($response) {
			return $response;
		}
		return false;
	}
	/* API_EditRecord: http://www.quickbase.com/api-guide/index.html#edit_record.html */
	public function edit_record($rid, $fields, $uploads = 0, $updateid = 0) {
		if($this->xml) {
			$xml_packet = new SimpleXMLElement('<qdbapi></qdbapi>');
			$xml_packet->addChild('rid',$rid);
			$i = intval(0);
			foreach($fields as $field) {
				$safe_value = preg_replace('/&(?!\w+;)/', '&', $field['value']);
				$xml_packet->addChild('field',$safe_value);
				$xml_packet->field[$i]->addAttribute('fid', $field['fid']);
				$i++;
			}
			if ($uploads) {
				foreach ($uploads as $upload) {
					$xml_packet->addChild('field', $upload['value']);
					$xml_packet->field[$i]->addAttribute('fid', $upload['fid']);
					$xml_packet->field[$i]->addAttribute('filename',$upload['filename']);
					$i++;
				}
			}
			if ($this->app_token)
				$xml_packet->addChild('apptoken', $this->app_token);
				
			if ($this->user_token)
			{
    			$xml_packet->addChild('usertoken',$this->user_token);
			}
			else
			{
    			$xml_packet->addChild('ticket',$this->ticket);
			}

			$xml_packet = $xml_packet->asXML();	
			$response = $this->transmit($xml_packet, 'API_EditRecord');
		}
		else {
			$url_string = $this->qb_ssl . $this->db_id. "?act=API_EditRecord&ticket=". $this->ticket
						."&rid=".$rid;
			foreach ($fields as $field) {
				$url_string .= "&_fid_" . $field['id'] . "=" . $field['value'];
			}
			if ($uploads) {
				foreach ($uploads as $upload) {
					$xml_packet .= "" . $upload['value'] . "";
				}
			}
			if($updateid) $url_string .= "&update_id=".$updateid;
			$response = $this->transmit($url_string);
		}
		if($response) {
			return $response;
		}
		return false;
	}
	/* API_FieldAddChoices: http://www.quickbase.com/api-guide/index.html#field_add_choices.html */
	public function field_add_choices ($fid, $choices) {
		if($this->xml) {
			$xml_packet = new SimpleXMLElement('<qdbapi></qdbapi>');
			$xml_packet->addChild('fid',$fid);
			foreach($choices as $choice) {
				$xml_packet->addChild('choice',$choice);
			}
			if ($this->user_token)
			{
    			$xml_packet->addChild('usertoken',$this->user_token);
			}
			else
			{
    			$xml_packet->addChild('ticket',$this->ticket);
			}

			$xml_packet = $xml_packet->asXML();
			$response = $this->transmit($xml_packet, 'API_FieldAddChoices');
		}
		else {
			$url_string = $this->qb_ssl . $this->db_id. "?act=API_FieldAddChoices&ticket=". $this->ticket
						."&fid=".$fid;
			foreach ($choices as $choice) {
				$url_string.='&choice='.$choice;
			}
			$response = $this->transmit($url_string);
		}				
		if($response) {
			return $response->numadded;
		}
		return false;
	}
	/* API_FieldRemoveChoices: http://www.quickbase.com/api-guide/index.html#field_remove_choices.html */
	public function field_remove_choices ($fid, $choices) {
		if($this->xml) {
			$xml_packet = new SimpleXMLElement('<qdbapi></qdbapi>');
			$xml_packet->addChild('fid',$fid);
			foreach($choices as $choice) {
				$xml_packet->addChild('choice',$choice);
			}
			if ($this->user_token)
			{
    			$xml_packet->addChild('usertoken',$this->user_token);
			}
			else
			{
    			$xml_packet->addChild('ticket',$this->ticket);
			}

			$xml_packet = $xml_packet->asXML();
			$response = $this->transmit($xml_packet, 'API_FieldRemoveChoices');
		}
		else {
			$url_string = $this->qb_ssl . $this->db_id. "?act=API_FieldRemoveChoices&ticket=". $this->ticket
						."&fid=".$fid;
			foreach ($choices as $choice) {
				$url_string.='&choice='.$choice;
			}
			$response = $this->transmit($url_string);
		}
		if($response) {
			return $response->numremoved;
		}
		return false;
	}
	/* API_GenAddRecordForm: http://www.quickbase.com/api-guide/index.html#find_db_by_name.html */
	public function find_db_by_name($db_name) {
		if($this->xml) {
		$xml_packet='
				'.$db_name.'
				'.$this->ticket.'
			   ';
		$response = $this->transmit($xml_packet, 'API_FindDBByName');
		}
		else {
		$url_string = $this->qb_ssl . $this->db_id. "?act=API_FindDBByName&ticket=". $this->ticket
					."&dbname=".$db_name;
		$response = $this->transmit($url_string);
		}
		if($response) {
			return $response->db_id;
		}
		return false;
	}
	/* API_GenAddRecordForm: http://www.quickbase.com/api-guide/index.html#gen_add_record_form.html */
	public function gen_add_record_form($fields){
		if($this->xml) {
			$xml_packet='';
			foreach ($fields as $field) {
				$xml_packet .= "" . $field['value'] . "";
			}
			$xml_packet .= ''.$this->ticket.'
			   ';
			$response = $this->transmit($xml_packet, 'API_GenAddRecordForm', "", false);
		}
		else {
			$url_string = $this->qb_ssl . $this->db_id. "?act=API_GenAddRecordForm&ticket=". $this->ticket;
			foreach ($fields as $field) {
				$url_string .= "&_fid_" . $field['id'] . "=" . $field['value'];
			}
			$response = $this->transmit($url_string, "" , "" , false);
		}
		if($response) {
			return $response;
		}
		return false;
	}
	/* API_GenResultsTable: http://www.quickbase.com/api-guide/index.html#gen_results_table.html */
	public function gen_results_table($queries = 0, $qid = 0, $qname=0, $clist = 0, $slist = 0, $options = 0) {
	//A query in the queries array contains the following in this order: Field ID, Evaluator, Criteria
	//The first element in the second query in queries would contain "and/or" if needed.
		if ($this->xml) {
			$xml_packet='<qdbapi>';
			$pos = 0;
			if ($queries) {
				$xml_packet.='<query>';
				foreach ($queries as $query) {
					$criteria = "";
					if($pos > 0) {
						$criteria .= $query['ao'];
					}
					$criteria .= "{'" . $query['fid'] . "'."
						. $query['ev'] . ".'"
						. $query['cri']."'}";
					$xml_packet.= $criteria;
					$pos++;
				}
				$xml_packet.='</query>';
			}
			else if ($qid) {
				$xml_packet .= '<qid>'.$qid.'</qid>';
			}
			else if ($qname) {
				$xml_packet .= '<qname>'.$qname.'</qname>';
			}
			else {
				return false;
			}
			$xml_packet .= '
			<fmt>'.$fmt.'</fmt>';
			if($clist) $xml_packet .= '<clist>'.$clist.'</clist>';
			if($slist) {
				$xml_packet .= '<slist>'.$slist.'</slist>';
			}
			if($options) {
				$xml_packet .= '<options>'.$options.'</options>';
			}
			$xml_packet .= '<ticket>'.$this->ticket.'</ticket>
				</qdbapi>';
		$response = $this->transmit($xml_packet, 'API_GenResultsTable', "" , false);		
		} else {
			$url_string = $this->qb_ssl . $this->db_id. "?act=API_GenResultsTable&ticket=". $this->ticket;
			$pos = 0;
			if ($queries) {
				$url_string .= "&query=";
				foreach ($queries as $query) {
					$criteria = "";
					if($pos > 0) {
						$criteria .= $query['ao'];
					}
					$criteria .= "{'" . $query['fid'] . "'."
						. $query['ev'] . ".'"
						. $query['cri']."'}";
					$url_string.= $criteria;
					$pos++;
				}
			}
			if($clist) $url_string .= "&clist=".$clist;
			if($slist) {
				$url_string .= "&slist=".$slist;
			}
			if ($options) {
			$url_string .= '&options=';
				foreach ($options as $option) {
					if($cot>0) {
						$url_string .= ".";
					}
					$url_string .= $option;
					$cot++;
				}
			}
			echo $url_string;
			$response = $this->transmit($url_string, "" , "" , false);
		}
				if($response) {
			return $response;
		}
		return false;
	}
	/* API_GetDBPage: http://www.quickbase.com/api-guide/index.html#get_db_page.html */
	public function get_db_page($page_id) {
		if($this->xml) {
			$xml_packet = '
'.$page_id.'
				';
			$response = $this->transmit($xml_packet, 'API_GetDBPage');
		}
		else {
			$url_string = $this->qb_ssl . $this->db_id. "?act=API_GetDBPage&ticket=". $this->ticket
					."&pageid=".$page_id;
			$response = $this->transmit($url_string);
		}
	}
	/* API_GetNumRecords: http://www.quickbase.com/api-guide/index.html#getnumrecords.html */
	public function get_num_records() {
		if($this->xml) {
			$xml_packet = new SimpleXMLElement('<qdbapi></qdbapi>');
			if ($this->user_token)
			{
    			$xml_packet->addChild('usertoken',$this->user_token);
			}
			else
			{
    			$xml_packet->addChild('ticket',$this->ticket);
			}

			$xml_packet = $xml_packet->asXML();
			$response = $this->transmit($xml_packet, 'API_GetNumRecords');
		}
		$url_string = $this->qb_ssl . $this->db_id. "?act=API_GetNumRecords&ticket=". $this->ticket;
		$response = $this->transmit($url_string);
			if($response) {
				return $response;
			}
	}
	/* API_GetRecordAsHTML: http://www.quickbase.com/api-guide/index.html#getrecordashtml.html */
	public function get_record_as_html($rid) {
		if($this->xml) {
			$xml_packet = new SimpleXMLElement('<qdbapi></qdbapi>');
			$xml_packet->addChild('rid',$rid);
			if ($this->user_token)
			{
    			$xml_packet->addChild('usertoken',$this->user_token);
			}
			else
			{
    			$xml_packet->addChild('ticket',$this->ticket);
			}

			$xml_packet = $xml_packet->asXML();
			$response = $this->transmit($xml_packet, 'API_GetRecordAsHTML', "", false);
		}
		else {
			$url_string = $this->qb_ssl . $this->db_id. "?act=API_GetRecordAsHTML&ticket=". $this->ticket
					."&rid=".$rid;
			$response = $this->transmit($url_string, "" , "" ,false);
		}
				if($response) {
			return $response;
		}
		return false;
	}
	/* API_GetRecordInfo: http://www.quickbase.com/api-guide/index.html#getrecordinfo.html */
	public function get_record_info($rid) {
		if($this->xml) {
			$xml_packet = new SimpleXMLElement('<qdbapi></qdbapi>');
			$xml_packet->addChild('rid',$rid);
			if ($this->user_token)
			{
    			$xml_packet->addChild('usertoken',$this->user_token);
			}
			else
			{
    			$xml_packet->addChild('ticket',$this->ticket);
			}

			$xml_packet = $xml_packet->asXML();
			$response = $this->transmit($xml_packet, 'API_GetRecordInfo');
		}
		else {
			$url_string = $this->qb_ssl . $this->db_id. "?act=API_GetRecordInfo&ticket=". $this->ticket
					."&rid=".$rid;
			$response = $this->transmit($url_string);
		}
				if($response) {
			return $response;
		}
		return false;
	}
	/* API_GetSchema: http://www.quickbase.com/api-guide/index.html#getschema.html */
	public function get_schema () {
		if($this->xml) {
			$xml_packet = new SimpleXMLElement('<qdbapi></qdbapi>');
			if ($this->user_token)
			{
    			$xml_packet->addChild('usertoken',$this->user_token);
			}
			else
			{
    			$xml_packet->addChild('ticket',$this->ticket);
			}

            if ($this->app_token)
				$xml_packet->addChild('apptoken', $this->app_token);
				
            $xml_packet = $xml_packet->asXML();
			$response = $this->transmit($xml_packet, 'API_GetSchema');
		}
		else {
			$url_string = $this->qb_ssl . $this->db_id. "?act=API_GetSchema&ticket=". $this->ticket;
			$response = $this->transmit($url_string);
		}
		if($response) {
			return $response;
		}
		return false;
	}

	/* API_GetUserRole: http://www.quickbase.com/api-guide/index.html#getuserrole.html */
  public function get_user_role () {
	  if($this->xml) {
	    $xml_packet = new SimpleXMLElement('<qdbapi></qdbapi>');
        if ($this->user_token)
        {
            $xml_packet->addChild('usertoken',$this->user_token);
        }
        else
        {
            $xml_packet->addChild('ticket',$this->ticket);
        }


	                      if ($this->app_token)
	      $xml_packet->addChild('apptoken', $this->app_token);
	    $xml_packet->addChild('uid', $this->user_id);
	                      $xml_packet = $xml_packet->asXML();
	    $response = $this->transmit($xml_packet, 'API_GetUserRole');
	  }
	  else {
	    $url_string = $this->qb_ssl . $this->db_id. "?act=API_GetUserRole&ticket=". $this->ticket;

	    $response = $this->transmit($url_string);
	  }

	  if($response) {
	    return $response;
	  }
    return false;
  }
  
	/* API_GrantedDB's: http://www.quickbase.com/api-guide/index.html */
	public function granted_dbs () {
		if($this->xml) {
			$xml_packet = new SimpleXMLElement('<qdbapi></qdbapi>');
			if ($this->user_token)
			{
    			$xml_packet->addChild('usertoken',$this->user_token);
			}
			else
			{
    			$xml_packet->addChild('ticket',$this->ticket);
			}

			$xml_packet = $xml_packet->asXML();
			$response = $this->transmit($xml_packet, 'API_GrantedDBs');
		}
		else {
			$url_string = $this->qb_ssl . $this->db_id. "?act=API_GrantedDBs&ticket=". $this->ticket;
			$response = $this->transmit($url_string);
		}	
		if($response) {
			return $response;
		}
		return false;
	}
	/*API_ImportFromCSV: http://www.quickbase.com/api-guide/index.html#importfromcsv.html */
	public function import_from_csv ($records_csv, $clist, $skip_first = 0) {
		if($this->xml) {
			$xml_packet = new SimpleXMLElement('<qdbapi></qdbapi>');
			$xml_packet->addChild('records_csv',$records_csv);
			$xml_packet->addChild('clist',$clist);
			$xml_packet->addChild('skipfirst',$skip_first);
			if ($this->user_token)
			{
    			$xml_packet->addChild('usertoken',$this->user_token);
			}
			else
			{
    			$xml_packet->addChild('ticket',$this->ticket);
			}
            $xml_packet->addChild('apptoken',$this->app_token);
			$xml_packet = $xml_packet->asXML();
			$response = $this->transmit($xml_packet, 'API_ImportFromCSV');
		}
		if($response) {
			return $response;
		}
		return false;
	}
	/* API_PurgeRecords: http://www.quickbase.com/api-guide/index.html#purgerecords.html */
	public function purge_records($queries = 0, $qid = 0, $qname = 0) {
		if($this->xml) {
			$xml_packet = '';
			if ($queries) {
			$xml_packet.='';
				foreach ($queries as $query) {
					$criteria = "";
					if($pos > 0) {
						$criteria .= $query['ao'];
					}
					$criteria .= "{'" . $query['fid'] . "'."
						. $query['ev'] . ".'"
						. $query['cri']."'}";
					$xml_packet.= $criteria;
					$pos++;
				}
			$xml_packet.='';
			}
			else if ($qid) {
				$xml_packet .= ''.$qid.'';
			}
			else if ($qname) {
				$xml_packet .= ''.$qname.'';
			}
			else {
				return false;
			}
			$xml_packet.=''.$this->ticket.'
				';
			$response = $this->transmit($xml_packet, 'API_PurgeRecords');
		}
		else {
			$url_string = $this->qb_ssl . $this->db_id. "?act=API_PurgeRecords&ticket=". $this->ticket;
			if ($queries) {
				$url_string .= "&query=";
				foreach ($queries as $query) {
					$criteria = "";
					if($pos > 0) {
						$criteria .= $query['ao'];
					}
					$criteria .= "{'" . $query['fid'] . "'."
						. $query['ev'] . ".'"
						. $query['cri']."'}";
					$url_string.= $criteria;
					$pos++;
				}
			}
			else if ($qid) {
				$url_string .= "&qid=".$qid;
			}
			else if ($qname) {
				$url_string .= "&qname=".$qname;
			}
			else {
				return false;
			}
			$response = $this->transmit($url_string);
		}
		if($response) {
			return $response->num_records_deleted;
		}
		return false;
	}
	/* API_SetFieldProperties: http://www.quickbase.com/api-guide/index.html#setfieldproperties.html */
	public function set_field_properties($properties, $fid) {
		if($this->xml) {
			$xml_packet = new SimpleXMLElement('<qdbapi></qdbapi>');
			$xml_packet->addChild('fid',$fid);
			foreach($properties as $key => $value) {
				$xml_packet->addChild($key,$value);
			}
			if ($this->app_token)
				$xml_packet->addChild('apptoken', $this->app_token);
			if ($this->user_token)
			{
    			$xml_packet->addChild('usertoken',$this->user_token);
			}
			else
			{
    			$xml_packet->addChild('ticket',$this->ticket);
			}
			$xml_packet = $xml_packet->asXML();
			$response = $this->transmit($xml_packet, 'API_SetFieldProperties');
		}
		if($response) {
			return true;
		}
		return false;
	}
	/* API_SignOut: http://www.quickbase.com/api-guide/index.html#signout.html */
	public function sign_out() {
		if($this->xml) {
			$xml_packet ='';
			$response = $this->transmit($xml_packet, 'API_SignOut', $this->qb_ssl."main");
		}
		else {
			$url_string="https://www.quickbase.com/db/main?act=API_SignOut&ticket=". $this->ticket;
			$response = $this->transmit($url_string);
		}
		if($response) {
			return true;
		}
		return false;
	}
	/* API_RunImport http://www.quickbase.com/api-guide/index.html */
	public function api_run_import($id) {
		if($this->xml) {
			$xml_packet = new SimpleXMLElement('<qdbapi></qdbapi>');
			$xml_packet->addChild('id',$id);
			if ($this->user_token)
			{
    			$xml_packet->addChild('usertoken',$this->user_token);
			}
			else
			{
    			$xml_packet->addChild('ticket',$this->ticket);
			}

			$xml_packet = $xml_packet->asXML();
			$response = $this->transmit($xml_packet, 'API_RunImport');
		}
		else {
			$url_string = $this->qb_ssl . $this->db_id.'?act=API_RunImport&ticket='. $this->ticket .'&id='. $id;
			$response = $this->transmit($url_string);
		}
		if($response) {
			return $response;
		}
		return false;
	}
}
?>