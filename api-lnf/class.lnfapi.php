<?php
require_once 'class.apiutility.php';
include_once INCLUDE_DIR.'class.api.php';
include_once INCLUDE_DIR.'class.ticket.php';

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

class LnfApiController extends ApiController {
    var $format;
    var $action;
    var $status_code = 200;

    function handleRequest() {
        if(!($key=$this->requireApiKey()) || !$key->canCreateTickets())
            return $this->exerr(401, __('API key not authorized'));

        $this->format = ApiUtility::getValue("format", ApiUtility::getValue("f", "json"));

        $this->action = ApiUtility::getValue("action", "get-open-tickets");

        $content = $this->processAction();

        $this->_response($this->status_code, $content);
    }

	function _response($code, $resp) {
		Http::response($code, json_encode($resp), "application/json", "UTF-8");
		exit();
	}

    function isPost() {
        return $_SERVER['REQUEST_METHOD'] == 'POST';
    }

    function processAction() {
        //the default action (no resource_id supplied) returns all open tickets

        $api = $this->createApi();

        $code = null;
        $result = array();

        try {
            switch ($this->action) {
                case "add-ticket":
                    if ($this->isPost()) {
                        // resource_id should now be like "######:ResourceName", for example "86001:IR Microscope"

                        $vars = array(
                            "email"         => ApiUtility::getValue("email", "", $_POST),
                            "name"          => ApiUtility::getValue("name", "", $_POST),
                            "subject"       => ApiUtility::getValue("subject", "", $_POST),
                            "message"       => ApiUtility::getValue("message", "", $_POST),
                            "pri"           => ApiUtility::getValue("pri", "", $_POST),
                            "resource_id"   => ApiUtility::getValue("resource_id", "", $_POST),
							"queue"			=> ApiUtility::getValue("queue", "", $_POST),
                        );

                        $addTicketResult = $api->addTicket($vars);

                        $result = array_merge(
                            array('add_ticket_result' => array('insert_id' => $addTicketResult['insert_id'], 'errors' => $addTicketResult['errors'])),
                            $this->openTicketsResult($api),
                        );
                    } else {
                        $code = 405;
                        throw new Exception("Method not supported: " . $_SERVER['REQUEST_METHOD']);
                    }
                    break;
                case "ticket-detail":
                    $ticket = $api->getTicketDetail();
                    $result = array('error' => false, 'message' => "action: $this->action, ticketID: $api->ticketID", 'detail' => $ticket);
                    break;
                case "dept-membership":
                    $data = $api->getDeptMembership();
                    echo $data;
                    break;
                case "select-tickets-by-email":
                    $api->search = "by-email";
                    $data = $api->getTickets();
                    $result = array('error' => false, 'message' => $this->getMessage($api, array('email' => $api->email)), 'tickets' => $data);
                    break;
                case "select-tickets-by-resource":
                    $api->search = "by-resource";
                    $data = $api->getTickets();
                    $result = array('error' => false, 'message' => $this->getMessage($api, array('resource_id' => $api->resource_id)), 'tickets' => $data);
                    break;
                case "select-tickets-by-date":
                    $api->search = "by-daterange";
                    $data = $api->getTickets();
                    $result = array('error' => false, 'message' => $this->getMessage($api, array('resource_id' => $api->resource_id)), 'tickets' => $data);
                    break;
                case "post-message":
                    if( $this->isPost() ) {
                        $result = $api->postMessage(array(
                            'ticketID'  => ApiUtility::getNumber("ticketID", 0, $_POST),
                            'email'     => ApiUtility::getValue("email", "", $_POST),
                            'message'   => ApiUtility::getValue("message", "", $_POST),
                        ));
                    } else {
                        $code = 405;
                        throw new Exception("Method not supported: " . $_SERVER['REQUEST_METHOD']);
                    }
                    break;
                case "summary":
                    $resources = ApiUtility::getValue("resources", "");

                    if ($resources) {
                        $summary = $api->getSummary($resources);
                        $result = array('error' => false, 'message' => $this->getMessage($api, array('resources' => $resources)), 'summary' => $summary);
                    } else {
                        $result = array('error' => true, 'message' => 'Invalid parameter: resources');
                    }
                    break;
                case "get-ticket-counts":
                    $staleCount = $api->getStaleTicketCount(0);
                    $overdueCount = $api->getOverdueTicketCount();
                    echo json_encode(array("staleTicketCount" => $staleCount, "overdueTicketCount" => $overdueCount));
                    break;
                case "get-lists":
                    $lists = $api->getLists(array(
                        'list_id'           => ApiUtility::getNumber("list_id", 0),
                        'form_field_name'   => ApiUtility::getValue("form_field_name", ""),
                    ));
                    $result = array('error' => false, 'lists' => $lists);
                    break;
                case "get-list-items":
                    $items = $api->getListItems(array(
                        'list_id'           => ApiUtility::getNumber("list_id", 0),
                        'form_field_name'   => ApiUtility::getValue("form_field_name", ""),
                        'list_item_value'   => ApiUtility::getValue("list_item_value", ""),
                        'list_item_status'  => ApiUtility::getNumber("list_item_status", -1),
                    ));
                    $result = array('error' => false, 'items' => $items);
                    break;
                case "":
                case "get-open-tickets":
                    $result = $this->openTicketsResult($api);
                    break;
                default:
                    throw new Exception('Unsupported action: ' . $this->action);
            }
        } catch (Exception $ex) {
            $result = $this->getErrorContent($ex->getMessage(), $code ?? 500);
        }

        return $result;
    }

    function openTicketsResult($api, $extra = null) {
        $api->status = "open";
        $data = $api->getTickets();
        $result = array('error' => false, 'message' => $this->getMessage($api), 'tickets' => $data);

        if ($extra)
            return array_merge($extra, $result);
        else
            return $result;
    }

    function createApi() {
        $api = new LnfApi();
        $api->search = ApiUtility::getValue("search", "");
        $api->ticket_id = ApiUtility::getNumber("ticket_id", 0);
        $api->ticketID = ApiUtility::getNumber("ticketID", 0);
        $api->resource_id = ApiUtility::getNumber("resource_id", 0);
        $api->assigned_to = ApiUtility::getValue("assigned_to", "");
        $api->unassigned = ApiUtility::getNumber("unassigned", 0);
        $api->email = ApiUtility::getValue("email", "");
        $api->name = ApiUtility::getValue("name", "");
        $api->priority_desc = ApiUtility::getValue("priority_desc", "");
        $api->status = ApiUtility::getValue("status", "");
        $api->sdate = ApiUtility::getDate("sdate", $this->defaultStartDate());
        $api->edate = ApiUtility::getDate("edate", "");
        $api->staff_id = ApiUtility::getNumber("staff_id", 0);
        return $api;
    }

    function defaultStartDate() {
        //first day of previous month
        $today = getdate();
        $fom = $today['year'].'-'.$today['mon'].'-1';
        return date('Y-m-d', strtotime("$fom -1 month"));
    }

    function getMessage($api, $extra = null) {
        $msg = "action: $this->action";
        $msg .= ", search: " . ($api->search ? $api->search : "null");
        $msg .= ", sdate: " . ($api->sdate ? $api->sdate : "null");
        $msg .= ", edate: " . ($api->edate ? $api->edate : "null");
        if ($extra) {
            foreach ($extra as $key => $val) {
                $msg .= ", $key: " . ($val ? $val : "null");
            }
        }
        return $msg;
    }

    function getErrorContent($msg, $code = 500) {
        $this->status_code = $code;
        return array('error' => true, 'message' => $msg);
    }

    function response($code, $resp) {
        $content = null;
        $contentType = null;

        if ($code != 200){
            parent::response($code, $resp);
        }else{
            switch ($this->format) {
                case 'xml':
                    $contentType = 'text/xml';
                    $content = ApiUtility::createXml($resp)->asXML();
                    break;
                case 'json':
                    $contentType = 'application/json';
                    $content = json_encode($resp);
                    break;
                default:
                    throw new Exception('Unsupported format: ' . $this->format);
            }

            Http::response($code, $content, $contentType);
            exit();
        }
    }
    
    function dump($var) {
        die(json_encode($var));
    }
}

class LnfApi {
    var $ticket_data;
    var $thread_data;
    var $staff_data;
    var $list_data;
    var $list_item_data;

    var $search;
    var $ticket_id;
    var $ticketID;
    var $resource_id;
    var $assigned_to;
    var $unassigned;
    var $email;
    var $name;
    var $priority_desc;
    var $status;
    var $sdate;
    var $edate;
    var $staff_id;

    function __construct() {
        $this->ticket_data = new TicketData();
        $this->thread_data = new ThreadData();
        $this->staff_data  = new StaffData();
        $this->list_data = new ListData();
        $this->list_item_data = new ListItemData();
    }

    function criteria() {
        if ($this->search == "by-resource") {
			$this->sdate = null;
			$this->edate = null;
            return array(
                "resource_id"   => $this->resource_id,
                "status"        => "open",
				"sdate"			=> null,
				"edate"			=> null,
            );
        } else if ($this->search == "by-email") {
			$this->sdate = null;
			$this->edate = null;
            return array(
                "email"     => $this->email,
                "status"    => "open",
				"sdate"     => null,
                "edate"     => null,
            );
        } else {
            // default is by-daterange
            return array(
                "ticket_id"         => $this->ticket_id,
                "ticketID"          => $this->ticketID,
                "resource_id"       => $this->resource_id,
                "assigned_to"       => $this->assigned_to,
                "unassigned"        => $this->unassigned,
                "email"             => $this->email,
                "name"              => $this->name,
                "priority_desc"     => $this->priority_desc,
                "status"            => $this->status,
                "sdate"             => $this->sdate,
                "edate"             => $this->edate,
            );
        }
    }

    function getTicketDetail() {
        if (!is_numeric($this->ticketID) || $this->ticketID == 0)
            throw new Exception("Invalid parameter: ticketID");

		$this->sdate = null;
        $this->edate = null;

        $query = $this->getTickets();

        if (count($query) > 0) {
            $ticket = $query[0];
            $ticket_id = $ticket['ticket_id'];
            $thread = $this->thread_data->select(array('ticket_id' => $ticket_id));

            $result = array(
                'info' => array(
                    'ticketID'          => $ticket['ticketID'],
                    'subject'           => $ticket['subject'],
                    'status'            => $ticket['status'],
                    'priority'          => $ticket['priority_desc'],
                    'dept_name'         => $ticket['dept_name'],
                    'created'           => $ticket['created'],
                    'name'              => $ticket['name'],
                    'email'             => $ticket['email'],
                    'phone'             => $ticket['phone'],
                    'source'            => $ticket['source'],
                    'assigned_name'     => $ticket['assigned_to'],
                    'assigned_email'    => $ticket['assigned_email'],
                    'help_topic'        => $ticket['helptopic'],
                    'last_response'     => $ticket['lastresponse'],
                    'last_message'      => $ticket['lastmessage'],
                    'ip_address'        => $ticket['ip_address'],
                    'due_date'          => $ticket['duedate'],
                ),
                'messages' => $this->getMessages($thread),
                'responses' => $this->getResponses($thread),
            );

            return $result;
        }

        return null;
    }

    function getMessages($thread) {
        $result = array();
        foreach ($thread as $entry) {
            if ($entry['type'] == 'M') {
                $result[] = array(
                    'msg_id'        => $entry['entry_id'],
                    'created'       => $entry['entry_created'],
                    'message'       => $entry['body'],
                    'source'        => $entry['source'],
                    'ip_address'    => $entry['ip_address'],
                    'attachments'   => $entry['attachments'],
                );
            }
        }
        return $result;
    }

    function getResponses($thread) {
        $result = array();
        foreach ($thread as $entry) {
            if ($entry['type'] == 'R'){
                $result[] = array(
                    'response_id'   => $entry['entry_id'],
                    'msg_id'        => $entry['pid'],
                    'staff_id'      => $entry['staff_id'],
                    "staff_name"    => $entry['staff_name'],
                    'response'      => $entry['body'],
                    'ip_address'    => $entry['ip_address'],
                    'created'       => $entry['entry_created'],
                    'attachments'   => $entry['attachments'],
                );
            }
        }
        return $result;
    }

    function getTickets() {
        $criteria = $this->criteria();
        $result = $this->ticket_data->select($criteria);
        return $result;
    }

    function addTicket($vars) {
        // there are two scenarios here:
        //      1) resource_id is just a number: in this case we get the full list item value (######:ResourceName)
        //      2) resource_id is the full list item value (######:ResourceName) in which case we make sure the list item exists, add or update if needed

        $resource_id = null;

        if (is_numeric($vars['resource_id'])) {
            $id = $vars['resource_id'];
            $items = $this->list_item_data->select(array('form_field_name' => 'resource_id', 'list_item_value' => "$id:%"));
            if (count($items) > 0) {
                $resource_id = $items[0]['list_item_value'];
            } else {
                // if no list item is found then the ticket will not be assigned to a resource (because $resource_id = null)
                $resource_id = null;
            }
        } else {
            // resource_id should now be like "######:ResourceName", for example "86001:IR Microscope"
            $resource_id = $vars['resource_id'];
            // before adding the ticket we need to make sure the list item exists
            $this->addOrUpdateResource($resource_id);
        }

		// find the id for this queue email
		if (!($emailId = Email::getIdByEmail($vars["queue"])))
			throw new Exception('Unknown queue email: '.$vars["queue"]);

        // this will create a ticket and send alerts

        $errors = array();
        $ticket = Ticket::create(array(
            "mid"           => "",
			"emailId"		=> $emailId,
            "email"         => $vars['email'],
            "name"          => $vars['name'],
            "subject"       => $vars['subject'],
            "resource_id"   => $resource_id,
            "message"       => $vars['message'],
            "header"        => "",
            "pri"           => $vars['pri'],
            "source"        => 'api',
        ), $errors, 'api');

        $insert_id = $ticket->getId();

        $result = array('insert_id' => $insert_id, 'ticket' => $ticket, 'errors' => $errors);

        return $result;
    }

    function addOrUpdateResource($resource_id) {
        if ($resource_id) {
            // split to get id and name parts
            $parts = explode(':', $resource_id);

            // make sure $resource_id is valid (######:ResourceName)
            if (count($parts) > 1 && is_numeric($parts[0])) {
                $id = $parts[0]; // this is the scheduler ResourceID

                // find any items that begin with the ResourceID
                $items = $this->list_item_data->select(array('form_field_name' => 'resource_id', 'list_item_value' => "$id:%"));

                if (count($items) == 0) {
                    // nothing found for this id (ResourceID)
                    // get the list
                    $lists = $this->list_data->select(array('form_field_name' => 'resource_id'));

                    if (count($lists) == 0)
                        return false; // there is no resource_id list, nothing else to do here

                    $list_id = $lists[0]['list_id'];
                    $list_item_id = $this->list_item_data->insert(array('list_id' => $list_id, 'list_item_value' => $resource_id));
                } else {
                    // found a resource, now check if the name is correct
                    $list_item_id = $items[0]['list_item_id'];
                    if ($items[0]['list_item_value'] !== $resource_id) {
                        // the resource name has changed, need to update
                        $this->list_item_data->update(array('list_item_id' => $list_item_id, 'list_item_value' => $resource_id));
                    }
                }

                // return either the new or existing list_item_id
                return $list_item_id;
            }
        }

        // invalid $resource_id
        return false;
    }

    function postMessage($vars) {
        // $vars["email"] should be the email of the message poster, not necessarily the ticket creator

        // if the poster is not the creator they will automatically be added as a collaborator, and a new user will be created if necessary

        if (($ticketID = $vars["ticketID"]) != 0) {
			if ($ticket = Ticket::lookupByNumber($ticketID)) {
				$email = $vars["email"] ?? $ticket->getEmail();

	            if(!($user = User::fromVars(array('email' => $email)))) {
	                $detail = "Cannot find or create user: $email";
	            } else {
					$body = null;
					if (($vars["format"] ?? "text") == "html")
						$body = new HtmlThreadEntryBody($vars["message"]);
					else
						$body = new TextThreadEntryBody($vars["message"]);

	                $ticket->postMessage(array(
            	        "userId"    => $user->getId(),
	    	            "message"	=> $body,
    	                "source"    => 'api',
	                ), 'api');

                	$detail = $this->getTicketDetails($ticket);
            	}
            	$result = array("error"=>false, "message"=>"ok: $ticketID", "detail"=>$detail);
			} else {
				$result = array("error"=>true, "message"=>"Cannot find ticket # $ticketID");
			}
        } else {
            $result = array("error"=>true, "message"=>"Invalid parameter: ticketID");
        }

        return $result;
    }

    function getDeptMembership() {
        $rows = $this->staff_data->select(array("staff_id" => $this->staff_id));

        $membership = array();

        if (count($rows) > 0){
            $deptPrimary = $rows[0]; //there should only be one
            $membership[] = array(
                'dept_id' => $deptPrimary['id'],
                'dept_name' => $deptPrimary['name'],
                'from' => 'staff'
            );
        }

        $rows = $this->staff_data->selectDeptAccess(array("staff_id" => $this->staff_id));

        // Determine if staff_id has access to any additional departments besides the one they belong to
        if ($rows != null){
            foreach ($rows as $deptSecondary){
                $membership[] = array(
                'dept_id' => $deptSecondary['id'],
                'dept_name' => $deptSecondary['name'],
                'from' => 'staff_dept_access'
                );
            }
        }

        $result = '';
        if ($this->staff_id > 0){
            $br = '';
            foreach ($membership as $dept){
                if ($dept['from'] == 'staff'){
                    $result .= '<span class="dept-name-staff" style="font-weight: normal;">'.$dept['dept_name'].'</span><div class="group-depts" style="display: none;">';
                } else {
                    $result .= $br.'<span class="dept-name-group" style="font-style: italic;">'.$dept['dept_name'].'</span>';
                    $br = '<br />';
                }
            }
            $result .= '</div>';
        } else {
            $result .= '<div style="font-style: italic; color: #808080;">No departments found.</div>';
        }

        return $result;
    }

    function getSummary($resources) {
        $result = $this->ticket_data->selectSummary(array('resources' => $resources));
        return $result;
    }

    function getStaleTicketCount($numdays = 5) {
        $result = $this->staff_data->selectStaleTickets(
            array("staff_id" => $this->staff_id),
            $numdays
        );

        return $result;
    }

    function getOverdueTicketCount() {
        $result = $this->staff_data->selectOverdueTickets(array(
            "staff_id" => $this->staff_id
        ));

        return $result;
    }

    function getTicketDetails($ticket) {
        $staff = $ticket->getStaff();
        $topic = $ticket->getTopic();
        $messages  = array();
        $responses = array();

        $threadEntries = $ticket->getClientThread()->all();
        foreach( $threadEntries as $entry ) {
            if( $entry->getType() == 'M' ) {
                $messages[] = array(
                    "msg_id"        => $entry->getId(),
                    "created"       => $entry->getCreateDate(),
                    "message"       => (string)$entry->getMessage(),
                    "source"        => $entry->getSource(),
                    "ip_address"    => $entry->ip_address,
                    "attachments"   => $entry->getNumAttachments(),
                );
            } else {
                $responses[] = array(
                    "response_id"   => $entry->getId(),
                    "msg_id"        => "",
                    "staff_id"      => $entry->getStaffId(),
                    "staff_name"    => (string)$entry->getStaff(),
                    "response"      => (string)$entry->getMessage(),
                    "ip_address"    => $entry->ip_address,
                    "created"       => $entry->getCreateDate(),
                    "attachments"   => $entry->getNumAttachments(),
                );
            }
        }

        $result = array(
            "info" => array(
                "ticketID"          => $ticket->getNumber(),
                "subject"           => $ticket->getSubject(),
                "status"            => $ticket->getStatus()->getName(),
                "priority"          => $ticket->getPriority()->getDesc(),
                "dept_name"         => $ticket->getDeptName(),
                "created"           => $ticket->getCreateDate(),
                "name"              => $ticket->getName()->getFull(),
                "email"             => (string)$ticket->getEmail(),
                "phone"             => $ticket->getPhoneNumber(),
                "source"            => $ticket->getSource(),
                "assigned_name"     => ($staff) ? $staff->getName()  : "",
                "assigned_email"    => ($staff) ? $staff->getEmail() : "",
                "help_topic"        => ($topic) ? $topic->getName()  : "",
                "last_response"     => $ticket->getLastResponseDate(),
                "last_message"      => $ticket->getLastMessageDate(),
                "ip_address"        => $ticket->getIP(),
                "due_date"          => $ticket->getDueDate(),
            ),
            "messages"  => $messages,
            "responses" => $responses,
        );

        return $result;
    }

    function getLists($criteria) {
        $result = $this->list_data->select($criteria);
        return $result;
    }

    function getListItems($criteria) {
        $result = $this->list_item_data->select($criteria);
        return $result;
    }
}

class ListItemData extends ApiData {
    function select($criteria) {
        $sql = "SELECT ff.id AS form_field_id, ff.type AS form_field_type, ff.label AS form_field_label, ff.name AS form_field_name"
            . ", l.id AS list_id, l.name AS list_name, l.name_plural AS list_name_plural"
            . ", li.id AS list_item_id, li.status AS list_item_status, li.value AS list_item_value"
            . " FROM ".FORM_FIELD_TABLE." ff"
            . " INNER JOIN ".LIST_TABLE." l ON CONCAT('list-', l.id) = ff.type"
            . " INNER JOIN ".LIST_ITEM_TABLE." li ON li.list_id = l.id"
            . $this->where($criteria);

        $result = $this->execQuery($sql);

        return $result;
    }

    function insert($criteria) {
        $list_id = $this->escape($criteria["list_id"]);
        $list_item_value = $this->escape($criteria["list_item_value"]);

        if ($list_id > 0 && $list_item_value) {
            $sql = "INSERT ".LIST_ITEM_TABLE." (list_id, status, value, extra, sort, properties) VALUES ($list_id, 1, '$list_item_value', NULL, 1, '[]')";
            $this->execQuery($sql);
            return $this->getInsertId();
        }

        return false;
    }

    function where($criteria) {
        $list_id = $this->escape($criteria["list_id"]);
        $form_field_name = $this->escape($criteria["form_field_name"]);
        $list_item_value = $this->escape($criteria["list_item_value"]);
        $list_item_status = $this->escape($criteria["list_item_status"]);

        $result = "";
        $and = " WHERE";

        if ($list_id > 0) {
            $result .= "$and l.id = $list_id";
            $and = " AND";
        }
        if ($form_field_name) {
            $result .= "$and ff.name = '$form_field_name'";
            $and = " AND";
        }
        if ($list_item_value) {
            $result .= "$and li.value LIKE '$list_item_value'";
            $and = " AND";
        }
        if ($list_item_status === "0" || $list_item_status === "1") {
            $result .= "$and li.status = $list_item_status";
            $and = " AND";
        }

        return $result;
    }
}

class ListData extends ApiData {
    function select($criteria) {
        $sql = "SELECT l.id AS list_id, l.name AS list_name, l.name_plural AS list_name_plural"
            . ", ff.id AS form_field_id, ff.type AS form_field_type, ff.label AS form_field_label, ff.name AS form_field_name"
            . " FROM ".LIST_TABLE." l"
            . " LEFT JOIN ".FORM_FIELD_TABLE." ff ON CONCAT('list-', l.id) = ff.type"
            . $this->where($criteria);

        $result = $this->execQuery($sql);

        return $result;
    }

    function where($criteria) {
        $list_id = $this->escape($criteria["list_id"]);
        $form_field_name = $this->escape($criteria["form_field_name"]);

        $result = "";
        $and = " WHERE";

        if ($list_id > 0) {
            $result .= "$and l.id = $list_id";
            $and = " AND";
        }
        if ($form_field_name) {
            $result .= "$and ff.name = '$form_field_name'";
            $and = " AND";
        }

        return $result;
    }
}

class TicketData extends ApiData {
    function select($criteria) {
        // the goal here is to return the same columns as the old lnfapi version
        $sql = "SELECT t.ticket_id, t.number AS 'ticketID'"
            .", t.dept_id, d.name AS 'dept_name', IFNULL(tp.priority_id, 0) AS 'priority_id'"
            .", t.topic_id, t.staff_id, ue.address AS 'email', u.name, tcd.subject"
            .", t.helptopic, NULL AS 'phone', NULL AS 'phone_ext', t.ip_address, ts.state AS 'status'"
            .", t.source, t.isoverdue, t.isanswered, t.duedate, t.reopened, t.closed"
            .", th.lastmessage, th.lastresponse, t.created, t.updated"
            .", SUBSTRING_INDEX(li.value, ':', 1) AS 'resource_id', tp.priority_desc, tp.priority_urgency"
            .", CONCAT(s.lastname, ', ', s.firstname) AS 'assigned_to', s.email AS 'assigned_email'"
            ." FROM ".TICKET_TABLE." t"
            ." LEFT JOIN ".THREAD_TABLE." th ON th.object_id = t.ticket_id AND th.object_type = 'T'"
            ." LEFT JOIN ".TICKET_STATUS_TABLE." ts ON ts.id = t.status_id"
            ." LEFT JOIN ".TICKET_CDATA_TABLE." tcd ON tcd.ticket_id = t.ticket_id"
            ." LEFT JOIN ".LIST_ITEM_TABLE." li ON li.id = tcd.resource_id"
            ." LEFT JOIN ".PRIORITY_TABLE." tp ON tp.priority_id = tcd.priority"
            ." LEFT JOIN ".USER_TABLE." u ON u.id = t.user_id"
            ." LEFT JOIN ".USER_EMAIL_TABLE." ue ON ue.user_id = t.user_id"
            //." LEFT JOIN ".USER_CDATA_TABLE." ucd ON ucd.user_id = u.id"
            ." LEFT JOIN ".STAFF_TABLE." s ON s.staff_id = t.staff_id"
            ." LEFT JOIN ".DEPT_TABLE." d ON d.id = t.dept_id";

        $sql .= $this->where($criteria);

        $result = $this->execQuery($sql);

        return $result;
    }

    function selectResources($criteria) {
        $resource_id = $this->escape($criteria["resource_id"]);

        $sql = "SELECT tr.resource_id, tp.priority_urgency, tp.priority_desc, COUNT(*) AS 'ticket_count' FROM ".TICKET_TABLE." t"
             ." INNER JOIN ".TICKET_RESOURCE_TABLE." tr ON tr.ticket_id = t.ticket_id"
             ." INNER JOIN ".FORM_ENTRY_TABLE." fe ON fe.object_id = t.ticket_id AND fe.object_type = 'T'"
             ." INNER JOIN ".FORM_ANSWER_TABLE." fa ON fa.entry_id = fe.id AND fa.value_id IS NOT NULL"
             ." LEFT JOIN ".TICKET_PRIORITY_TABLE." tp ON tp.priority_id = fa.value_id"
             ." LEFT JOIN ".TICKET_STATUS_TABLE." ts ON ts.id = t.status_id"
             ." WHERE tr.resource_id IN ($resource_id) AND ts.state IN ('open')"
             ." GROUP BY tr.resource_id, tp.priority_urgency, tp.priority_desc";

        return $this->execQuery($sql);
    }

    function selectSummary($criteria) {
        $resources = $this->escape($criteria["resources"]);

        $sql = "SELECT SUBSTRING_INDEX(li.value, ':', 1) AS 'resource_id'"
            .", tp.priority_urgency, tp.priority_desc, COUNT(*) AS 'ticket_count'"
            ." FROM ".TICKET_TABLE." t"
            ." LEFT JOIN ".TICKET_STATUS_TABLE." ts ON ts.id = t.status_id"
            ." LEFT JOIN ".TICKET_CDATA_TABLE." tcd ON tcd.ticket_id = t.ticket_id"
            ." LEFT JOIN ".LIST_ITEM_TABLE." li ON li.id = tcd.resource_id"
            ." LEFT JOIN ".PRIORITY_TABLE." tp ON tp.priority_id = tcd.priority"
            ." WHERE SUBSTRING_INDEX(li.value, ':', 1) IN ($resources) AND ts.state IN ('open')"
            ." GROUP BY SUBSTRING_INDEX(li.value, ':', 1), tp.priority_urgency, tp.priority_desc";

        return $this->execQuery($sql);
    }

    function updateCData($criteria) {
        $ticket_id = $this->escape($criteria["ticket_id"]);
        $resource_id = $this->escape($criteria["resource_id"]); // in this case resource_id is the id of the ost_list_items record
        $sql = "UPDATE ".TICKET_CDATA_TABLE." SET resource_id = $resource_id WHERE ticket_id = $ticket_id";
        $this->execQuery($sql);
        return $this->getAffectedRows();
    }

    function where($criteria) {
        $ticket_id = $this->escape($criteria["ticket_id"]);
        $ticketID = $this->escape($criteria["ticketID"]);
        $resource_id = $this->escape($criteria["resource_id"]);
        $assignedTo = $this->escape($criteria["assigned_to"]);
        $unassigned = $this->escape($criteria["unassigned"]);
        $email = $this->escape($criteria["email"]);
        $name = $this->escape($criteria["name"]);
        $status = $this->escape($criteria["status"]);
        $sdate = $this->escape($criteria["sdate"]);
        $edate = $this->escape($criteria["edate"]);
        $priorityDesc = $this->escape($criteria["priority_desc"]);

        $result = "";
        $and = " WHERE";

        if ($ticket_id > 0) {
            $result .= "$and t.ticket_id = $ticket_id";
            $and = " AND";
        }
        if ($ticketID > 0) {
            $result .= "$and t.number = $ticketID";
            $and = " AND";
        }
        if ($resource_id > 0) {
            $result .= "$and SUBSTRING_INDEX(li.value, ':', 1) = '$resource_id'";
            $and = " AND";
        }
        if ($this->hasValue($assignedTo)) {
            $result .= "$and CONCAT(s.lastname, ', ', s.firstname) LIKE '$assignedTo'";
            $and = " AND";
        }
        if ($unassigned == 1) {
            $result .= "$and t.staff_id = 0";
            $and = " AND";
        }
        if ($this->hasValue($email)) {
            $result .= "$and ue.address LIKE '$email'";
            $and = " AND";
        }
        if ($this->hasValue($name)) {
            $result .= "$and u.name LIKE '$name'";
            $and = " AND";
        }
        if ($this->hasValue($status)) {
            $result .= "$and ts.state = '$status'";
            $and = " AND";
        }
        if ($this->hasValue($priorityDesc)) {
            $result .= "$and tp.priority_desc LIKE '$priorityDesc'";
            $and = " AND";
        }

        $result .= $this->whereDateRange($and, "t.created", $sdate, $edate);

        return $result;
    }

    private function whereDateRange($and, $column, $sdate, $edate) {
        $and = trim($and);
        $result = "";
        $result .= ($this->hasValue($sdate)) ? " $and $column >= '$sdate'" : "";
        if ($this->hasValue($edate)){
            $result .= (!empty($result)) ? " AND" : " $and";
            $result .= " $column < '$edate'";
        }
        return $result;
    }
}

class ThreadData extends ApiData {
    function select($criteria) {
        // pid must be parent id
        $sql = "SELECT th.id AS 'thread_id', th.object_id AS 'ticket_id', th.lastresponse, th.lastmessage, th.created AS 'thread_created', te.id AS 'entry_id', te.pid, te.staff_id"
            .", te.type, te.source, te.title, te.body, te.ip_address, te.created AS 'entry_created', te.updated, CONCAT(s.firstname, ' ', s.lastname) AS 'staff_name'"
            .", IFNULL(a.attachments, 0) AS 'attachments'"
            ." FROM ".THREAD_TABLE." th"
            ." INNER JOIN ".THREAD_ENTRY_TABLE." te ON te.thread_id = th.id"
            ." LEFT JOIN (SELECT te.id AS 'entry_id', COUNT(*) AS 'attachments'"
                ." FROM ".ATTACHMENT_TABLE." a"
                ." INNER JOIN ".THREAD_ENTRY_TABLE." te ON te.id = a.object_id"
                ." INNER JOIN ".THREAD_TABLE." th ON th.id = te.thread_id"
                ." WHERE a.type = 'H' GROUP BY te.id) a ON a.entry_id = te.id"
            ." LEFT JOIN ".STAFF_TABLE." s ON s.staff_id = te.staff_id";

        $sql .= $this->where($criteria);

        $result = $this->execQuery($sql);

        return $result;
    }

    function where($criteria) {
        $ticket_id = $this->escape($criteria["ticket_id"]);
        return " WHERE th.object_type = 'T' and th.object_id = $ticket_id";
    }
}

class StaffData extends ApiData {
    function select($criteria) {
        $sql = "SELECT s.staff_id, d.id, d.name FROM ".STAFF_TABLE." s"
            ." LEFT JOIN ".DEPT_TABLE." d ON d.id = s.dept_id";

        $sql .= $this->where($criteria);

        $result = $this->execQuery($sql);

        return $result;
    }

    function selectDeptAccess($criteria) {
        $sql = "SELECT s.staff_id, d.id, d.name FROM ".STAFF_TABLE." s"
            ." LEFT JOIN ".STAFF_DEPT_TABLE." da ON da.staff_id = s.staff_id"
            ." LEFT JOIN ".DEPT_TABLE." d ON d.id = da.dept_id";

        $sql .= $this->where($criteria);

        $result = $this->execQuery($sql);

        return $result;
    }

    function selectStaleTickets($criteria, $numdays) {
        $sql = "SELECT COUNT(*) AS 'stale_ticket_count' FROM ".TICKET_TABLE." t"
             ." LEFT JOIN ".TICKET_STATUS_TABLE." ts ON ts.id = t.status_id"
             ." LEFT JOIN ".THREAD_TABLE." th ON th.object_id = t.ticket_id";

        $staff_id = $this->escape($criteria["staff_id"]);

        $sql .= " WHERE t.staff_id = $staff_id";
        $sql .= " AND ts.state = 'open' AND DATEDIFF(NOW(), th.lastresponse) > ".$numdays;

        $result = $this->execQuery($sql);

        return $result[0]["stale_ticket_count"];
    }

    function selectOverdueTickets($criteria) {
        $sql = "SELECT COUNT(*) AS 'overdue_ticket_count' FROM ".TICKET_TABLE." t"
             ." LEFT JOIN ".TICKET_STATUS_TABLE." ts ON ts.id = t.status_id";

        $staff_id = $this->escape($criteria["staff_id"]);

        $sql .= " WHERE t.staff_id = $staff_id";
        $sql .= " AND ts.state = 'open' AND t.isoverdue = 1";

        $result = $this->execQuery($sql);

        return $result[0]["overdue_ticket_count"];
    }

    function where($criteria) {
        $staff_id = $this->escape($criteria["staff_id"]);
        return " WHERE s.staff_id = $staff_id";
    }
}

abstract class ApiData {
    abstract function select($criteria);
    abstract function where($criteria);

    protected function hasValue($v) {
        return !empty($v) && is_string($v) && $v != "" && $v != "''";
    }

    protected function execQuery($sql) {
        try {
            $result = db_assoc_array(db_query($sql));
            return $result;
        } catch (Exception $e) {
            die(print_r($e, true));
        }
    }

    protected function getInsertId() {
        return db_insert_id();
    }

    protected function getAffectedRows() {
        return db_affected_rows();
    }

    protected function escape($val) {
        return db_input($val, false);
    }
}
