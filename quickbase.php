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

	var $user_name = ''; 	// QuickBase user who will access the QuickBase
	var $passwd = ''; 		// Password of this user
	var $db_id = ''; 		// Table/Database ID of the QuickBase being accessed
	var $app_token = '';
	var $xml = true;
	var $user_id = 0;
	var $qb_site = "www.quickbase.com";
	var $qb_ssl = "https://www.quickbase.com/db/";
	var $ticketHours = '';	

 /*---------------------------------------------------------------------
 //	Do Not Change
 -----------------------------------------------------------------------*/

	var $input = "";
	var $output = "";
	var $ticket = '';

 /* --------------------------------------------------------------------*/	

	public function __construct($un, $pw, $usexml = true, $db = '', $token = '', $realm = '', $hours = '') {

		if($un) {
			$this->user_name = $un;
		}

		if($pw) {
			$this->passwd = $pw;
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

		$uid = $this->authenticate();

		if($uid) {
			$this->user_id = $uid;
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
			curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $input);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
		}
		else
		{
			$ch = curl_init($input);
			curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
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

	/* API_Authenticate: https://www.quickbase.com/up/6mztyxu8/g/rc7/en/va/QuickBaseAPI.htm#_Toc126579970 */
	public function authenticate() {
		if($this->xml) {

			$xml_packet = new SimpleXMLElement('<qdbapi></qdbapi>');
			$xml_packet->addChild('username',$this->user_name);
			$xml_packet->addChild('password',$this->passwd);

			if ($this->ticketHours)
				$xml_packet->addChild('hours',$this->ticketHours);

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

	/* API_AddField: https://www.quickbase.com/up/6mztyxu8/g/rc7/en/va/QuickBaseAPI.htm#_Toc126579958 */
	public function add_field ($field_name, $type) {
		if($this->xml) {
			$xml_packet = new SimpleXMLElement('<qdbapi></qdbapi>');
			$xml_packet->addChild('label',$field_name);
			$xml_packet->addChild('type',$type);
			$xml_packet->addChild('ticket',$this->ticket);
			$xml_packet = $xml_packet->asXML();

			$response = $this->transmit($xml_packet, 'API_AddField');
		}
		else {
			$url_string = $this->qb_ssl . $this->db_id. "?act=API_AddField&ticket=". $this->ticket ."&label=" .$field_name."&type=".$type;

			$response = $this->transmit($url_string);
		}

		if($response->errcode == 0) {
			return $response->fid;
		}
		return false;
	}

	/* API_AddRecord: https://www.quickbase.com/up/6mztyxu8/g/rc7/en/va/QuickBaseAPI.htm#_Toc126579962 */
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

			$xml_packet->addChild('ticket',$this->ticket);
			$xml_packet = $xml_packet->asXML();	

			$response = $this->transmit($xml_packet, 'API_AddRecord');
		}
		else {
			$url_string = $this->qb_ssl . $this->db_id. "?act=API_AddRecord&ticket=". $this->ticket;

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
			$xml_packet->addChild('ticket',$this->ticket);
			$xml_packet = $xml_packet->asXML();			

			$response = $this->transmit($xml_packet, 'API_ChangePermission');
		}
		else {
			$url_string = $this->qb_ssl . $this->db_id. "?act=API_ChangePermission&ticket=". $this->ticket
					. "&uname=".$uname
					."&modify=".$modify
					."&view=".$view
					."&create=".$create
					."&saveviews=".$save_views
					."&delete=".$delete
					."&admin=".$admin;

			$response = $this->transmit($url_string);
		}
		if($response) {
			return $response;
		}
		return false;
	}

	/* API_ChangeRecordOwner: https://www.quickbase.com/up/6mztyxu8/g/rc7/en/va/QuickBaseAPI.htm#_Toc126579977 */
	public function change_record_owner($new_owner, $rid){
		if($this->xml) {
			$xml_packet = new SimpleXMLElement('<qdbapi></qdbapi>');
			$xml_packet->addChild('rid',$rid);
			$xml_packet->addChild('newowner',$new_owner);
			$xml_packet->addChild('ticket',$this->ticket);
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

	/* API_CloneDatabase: https://www.quickbase.com/up/6mztyxu8/g/rc7/en/va/QuickBaseAPI.htm#_Toc126579981 */
	public function clone_database($new_name, $new_desc, $keep_data = 1){
		if($this->xml) {
			$xml_packet = new SimpleXMLElement('<qdbapi></qdbapi>');
			$xml_packet->addChild('newdbname',$new_name);
			$xml_packet->addChild('newdbdesc',$new_desc);
			$xml_packet->addChild('keepData',$keep_data);
			$xml_packet->addChild('ticket',$this->ticket);
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

	/* API_CreateDatabase: https://www.quickbase.com/up/6mztyxu8/g/rc7/en/va/QuickBaseAPI.htm#_Toc126579984 */
	public function create_database($db_name, $db_desc) {
		if($this->xml) {
			$xml_packet = new SimpleXMLElement('<qdbapi></qdbapi>');
			$xml_packet->addChild('dbname',$db_name);
			$xml_packet->addChild('dbdesc',$db_desc);
			$xml_packet->addChild('ticket',$this->ticket);
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

	/* API_DeleteDatabase: https://www.quickbase.com/up/6mztyxu8/g/rc7/en/va/QuickBaseAPI.htm#_Toc126579988*/
	public function delete_database() {
		if($this->xml) {
			$xml_packet = new SimpleXMLElement('<qdbapi></qdbapi>');
			$xml_packet->addChild('ticket',$this->ticket);
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

	/* API_DeleteField: https://www.quickbase.com/up/6mztyxu8/g/rc7/en/va/QuickBaseAPI.htm#_Toc126579992*/
	public function delete_field($fid) {
		if($this->xml) {
			$xml_packet = new SimpleXMLElement('<qdbapi></qdbapi>');
			$xml_packet->addChild('fid',$fid);
			$xml_packet->addChild('ticket',$this->ticket);
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

	/* API_DeleteRecord: https://www.quickbase.com/up/6mztyxu8/g/rc7/en/va/QuickBaseAPI.htm#_Toc126579996*/
	public function delete_record($rid) {
		if($this->xml) {
			$xml_packet = new SimpleXMLElement('<qdbapi></qdbapi>');
			$xml_packet->addChild('rid',$rid);
			$xml_packet->addChild('ticket',$this->ticket);
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

	/* API_DoQuery: https://www.quickbase.com/up/6mztyxu8/g/rc7/en/va/QuickBaseAPI.htm#_Toc126579999 */
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

	/* API_EditRecord: https://www.quickbase.com/up/6mztyxu8/g/rc7/en/va/QuickBaseAPI.htm#_Toc126580003 */
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

			$xml_packet->addChild('ticket',$this->ticket);
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
			return $response->update_id;
		}
		return false;
	}

	/* API_FieldAddChoices: https://www.quickbase.com/up/6mztyxu8/g/rc7/en/va/QuickBaseAPI.htm#_Toc126580007 */
	public function field_add_choices ($fid, $choices) {
		if($this->xml) {
			$xml_packet = new SimpleXMLElement('<qdbapi></qdbapi>');
			$xml_packet->addChild('fid',$fid);

			foreach($choices as $choice) {
				$xml_packet->addChild('choice',$choice);
			}

			$xml_packet->addChild('ticket',$this->ticket);
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

	/* API_FieldRemoveChoices: https://www.quickbase.com/up/6mztyxu8/g/rc7/en/va/QuickBaseAPI.htm#_Toc126580011 */
	public function field_remove_choices ($fid, $choices) {
		if($this->xml) {
			$xml_packet = new SimpleXMLElement('<qdbapi></qdbapi>');
			$xml_packet->addChild('fid',$fid);

			foreach($choices as $choice) {
				$xml_packet->addChild('choice',$choice);
			}

			$xml_packet->addChild('ticket',$this->ticket);
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

	/* API_GenAddRecordForm: https://www.quickbase.com/up/6mztyxu8/g/rc7/en/va/QuickBaseAPI.htm#_Toc126580019 */
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

	/* API_GenAddRecordForm: https://www.quickbase.com/up/6mztyxu8/g/rc7/en/va/QuickBaseAPI.htm#_Toc126580019 */
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

	/* API_GenResultsTable: https://www.quickbase.com/up/6mztyxu8/g/rc7/en/va/QuickBaseAPI.htm#_Toc126580023 */
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

	/* API_GetDBPage: https://www.quickbase.com/up/6mztyxu8/g/rc7/en/va/QuickBaseAPI.htm#_Toc126580033 */
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

	/* API_GetNumRecords: https://www.quickbase.com/up/6mztyxu8/g/rc7/en/va/QuickBaseAPI.htm#_Toc126580037 */
	public function get_num_records() {
		if($this->xml) {
			$xml_packet = new SimpleXMLElement('<qdbapi></qdbapi>');
			$xml_packet->addChild('ticket',$this->ticket);
			$xml_packet = $xml_packet->asXML();

			$response = $this->transmit($xml_packet, 'API_GetNumRecords');
		}
		$url_string = $this->qb_ssl . $this->db_id. "?act=API_GetNumRecords&ticket=". $this->ticket;

		$response = $this->transmit($url_string);

			if($response) {
				return $response;
			}
	}

	/* API_GetRecordAsHTML: https://www.quickbase.com/up/6mztyxu8/g/rc7/en/va/QuickBaseAPI.htm#_Toc126580040 */
	public function get_record_as_html($rid) {
		if($this->xml) {
			$xml_packet = new SimpleXMLElement('<qdbapi></qdbapi>');
			$xml_packet->addChild('rid',$rid);
			$xml_packet->addChild('ticket',$this->ticket);
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

	/* API_GetRecordInfo: https://www.quickbase.com/up/6mztyxu8/g/rc7/en/va/QuickBaseAPI.htm#_Toc126580046 */
	public function get_record_info($rid) {
		if($this->xml) {
			$xml_packet = new SimpleXMLElement('<qdbapi></qdbapi>');
			$xml_packet->addChild('rid',$rid);
			$xml_packet->addChild('ticket',$this->ticket);
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

	/* API_GetSchema: https://www.quickbase.com/up/6mztyxu8/g/rc7/en/va/QuickBaseAPI.htm#_Toc126580049 */
	public function get_schema () {
		if($this->xml) {
			$xml_packet = new SimpleXMLElement('<qdbapi></qdbapi>');
			$xml_packet->addChild('ticket',$this->ticket);

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

	/* API_GrantedDB's: https://www.quickbase.com/up/6mztyxu8/g/rc7/en/va/QuickBaseAPI.htm#_Toc126580052 */
	public function granted_dbs () {
		if($this->xml) {
			$xml_packet = new SimpleXMLElement('<qdbapi></qdbapi>');
			$xml_packet->addChild('ticket',$this->ticket);
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

	/*API_ImportFromCSV: https://www.quickbase.com/up/6mztyxu8/g/rc7/en/va/QuickBaseAPI.htm#_Toc126580055 */
	public function import_from_csv ($records_csv, $clist, $skip_first = 0) {
		if($this->xml) {
			$xml_packet = new SimpleXMLElement('<qdbapi></qdbapi>');
			$xml_packet->addChild('records_csv',$records_csv);
			$xml_packet->addChild('clist',$clist);
			$xml_packet->addChild('skipfirst',$skip_first);
			$xml_packet->addChild('ticket',$this->ticket);
                        $xml_packet->addChild('apptoken',$this->app_token);
			$xml_packet = $xml_packet->asXML();

			$response = $this->transmit($xml_packet, 'API_ImportFromCSV');
		}

		if($response) {
			return $response;
		}
		return false;
	}

	/* API_PurgeRecords: https://www.quickbase.com/up/6mztyxu8/g/rc7/en/va/QuickBaseAPI.htm#_Toc126580061 */
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

	/* API_SetFieldProperties: https://www.quickbase.com/up/6mztyxu8/g/rc7/en/va/QuickBaseAPI.htm#_Toc126580065 */
	public function set_field_properties($properties, $fid) {
		if($this->xml) {
			$xml_packet = new SimpleXMLElement('<qdbapi></qdbapi>');
			$xml_packet->addChild('fid',$fid);

			foreach($properties as $key => $value) {
				$xml_packet->addChild($key,$value);
			}

			if ($this->app_token)
				$xml_packet->addChild('apptoken', $this->app_token);

			$xml_packet->addChild('ticket',$this->ticket);
			$xml_packet = $xml_packet->asXML();
			$response = $this->transmit($xml_packet, 'API_SetFieldProperties');
		}

		if($response) {
			return true;
		}
		return false;
	}

	/* API_SignOut: https://www.quickbase.com/up/6mztyxu8/g/rc7/en/va/QuickBaseAPI.htm#_Toc126580069 */
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

	/* API_RunImport */
	public function api_run_import($id) {
		if($this->xml) {
			$xml_packet = new SimpleXMLElement('<qdbapi></qdbapi>');
			$xml_packet->addChild('id',$id);

			$xml_packet->addChild('ticket',$this->ticket);
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