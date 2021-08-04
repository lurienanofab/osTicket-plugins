<?php

require_once 'class.apiutility.php';
include_once INCLUDE_DIR.'class.api.php';
include_once INCLUDE_DIR.'class.ticket.php';
define('TICKET_RESOURCE_TABLE',TABLE_PREFIX.'ticket_resource');

class LnfApiController extends ApiController {

    var $format;
    var $action;
    var $status_code = 200;

    function handleRequest() {
        if(!($key=$this->requireApiKey()) || !$key->canCreateTickets())
            return $this->exerr(401, __('API key not authorized'));

        $this->format = ApiUtility::getval("format", ApiUtility::getval("f", "json"));

        $this->action = ApiUtility::getval("action", "get-open-tickets");

        $content = $this->processAction();

        $this->response($this->status_code, $content);
    }

    function isPost() {
        return $_SERVER['REQUEST_METHOD'] == 'POST';
    }

    function processAction() {
        //the default action (no resource_id supplied) returns all open tickets

        $api = $this->createApi();
        $sdate = $api->sdate ? $api->sdate : $api->defaultStartDate();

        $code = null;
        $result = array();

        try {
            switch ($this->action) {
                case "add-ticket":
                    if ($this->isPost()) {
                        $api->addTicket(array(
                            "email"         => ApiUtility::getval("email", "", $_POST),
                            "name"          => ApiUtility::getval("name", "", $_POST),
                            "subject"       => ApiUtility::getval("subject", "", $_POST),
                            "message"       => ApiUtility::getval("body", "", $_POST),
                            "pri"           => ApiUtility::getval("pri", "", $_POST),
                            "resource_id"   => ApiUtility::getval("resource_id", "", $_POST),
                            "source"        => ApiUtility::getval("source", "", $_POST),
                        ));
                        $api->sdate = $sdate;
                        $result = $this->openTicketsResult($api);
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
                    $api->sdate = $sdate;
                    $data = $api->getTickets();
                    $result = array('error' => false, 'message' => $this->getMessage($api, array('email' => $api->email)), 'data' => $data);
                    break;
                case "select-tickets-by-resource":
                    $api->search = "by-resource";
                    $api->sdate = $sdate;
                    $data = $api->getTickets();
                    $result = array('error' => false, 'message' => $this->getMessage($api, array('resource_id' => $api->resource_id)), 'data' => $data);
                    break;
                case "post-message":
                    if( $this->isPost() ) {
                        if( ($ticketID = ApiUtility::getnum("ticketID", 0, $_POST)) != 0 ) {
                            if( !$user = User::lookupByEmail(ApiUtility::getval("email", "", $_POST)) ) {
                                $detail = "User not found";
                            } else {
                                $ticket = Ticket::lookupByNumber($ticketID);
                                $ticket->postMessage(array(
                                    "userId"    => $user->getId(),
                                    "message"   => ApiUtility::getval("message", "", $_POST),
                                    "source"    => ApiUtility::getval("source", "", $_POST),
                                ));
                                $detail = $api->getTicketDetails($ticket);
                            }
                            $result = array("error"=>false, "message"=>"ok: $ticketID", "detail"=>$detail);
                        } else {
                            $result = array("error"=>true, "message"=>"Invalid parameter: ticketID");
                        }
                    } else {
                        $code = 405;
                        throw new Exception("Method not supported: " . $_SERVER['REQUEST_METHOD']);
                    }
                    break;
                case "summary":
                    $resource_id = $api->resource_id;
                    $summary = $api->getSummary();

                    if( $resource_id ) {
                        $result = array("error" => false, "message" => "ok: $resource_id", "summary" => $summary);
                    } else {
                        $result = array("error" => true, "message" => "Invalid parameter: resource_id");
                    }
                    break;
                case "get-ticket-counts":
                    $staleCount = $api->getStaleTicketCount(0);
                    $overdueCount = $api->getOverdueTicketCount();
                    echo json_encode(array("staleTicketCount"=>$staleCount, "overdueTicketCount"=>$overdueCount));
                    break;
                case "":
                case "get-open-tickets":
                    $api->sdate = $sdate;
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

    function openTicketsResult($api) {
        $api->search = "by-daterange";
        $api->status = "open";
        $data = $api->getTickets();
        $result = array('error' => false, 'message' => $this->getMessage($api), 'data' => $data);
        return $result;
    }

    function createApi() {
        $api = new LnfApi();
        $api->ticket_id = ApiUtility::getnum("ticket_id", 0);
        $api->ticketID = ApiUtility::getnum("ticketID", 0);
        $api->resource_id = ApiUtility::getnum("resource_id", 0);
        $api->assigned_to = ApiUtility::getval("assigned_to", "");
        $api->unassigned = ApiUtility::getnum("unassigned", 0);
        $api->email = ApiUtility::getval("email", "");
        $api->name = ApiUtility::getval("name", "");
        $api->priority_desc = ApiUtility::getval("priority_desc", "");
        $api->status = ApiUtility::getval("status", "");
        $api->sdate = ApiUtility::getval("sdate", "");
        $api->edate = ApiUtility::getval("edate", "");
        $api->staff_id = ApiUtility::getnum("staff_id", 0);
        return $api;
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

class LnfApi {

    var $ticket_data;
    var $thread_data;
    var $staff_data;
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
    }

    function criteria() {
        if ($this->search == "by-resource") {
            return array(
                "resource_id"   => $this->resource_id,
                "status"        => "open",
                "sdate"         => $this->sdate,
                "edate"         => $this->edate
            );
        } else if ($this->search == "by-email") {
            return array(
                "email"     => $this->email,
                "status"    => "open",
                "sdate"     => $this->sdate,
                "edate"     => $this->edate
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

        throw new Exception("Cannot find ticket with ticketID: $this->ticketID");
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
        $ticket = $this->ticket_data->insert(array(
            "mid"       => "",
            "email"     => $vars['email'],
            "name"      => $vars['name'],
            "subject"   => $vars['subject'],
            "message"   => $vars['message'],
            "header"    => "",
            "pri"       => $vars['pri'],
            "source"    => $vars['source'],
        ));

        return $ticket;
    }

    function defaultStartDate(){
        //first day of previous month
        $today = getdate();
        $fom = $today['year'].'-'.$today['mon'].'-1';
        return date('Y-m-d', strtotime("$fom -1 month"));
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

    function getSummary() {
        $result = $this->ticket_data->selectResources(array(
            "resource_id"   => $this->resource_id
        ));

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
}

class TicketData extends ApiData {
    function select($criteria) {
        // the goal here is to return the same columns as the old lnfapi version
        $sql = "SELECT t.ticket_id, t.number AS 'ticketID', t.dept_id, d.name AS 'dept_name', IFNULL(tp.priority_id, 0) AS 'priority_id', t.topic_id, t.staff_id, ue.address AS 'email', u.name"
            .", tcd.subject, t.helptopic, ucd.phone, NULL AS 'phone_ext', t.ip_address, ts.state AS 'status', t.source, t.isoverdue, t.isanswered, t.duedate, t.reopened"
            .", t.closed, th.lastmessage, th.lastresponse, t.created, t.updated, li.value AS 'resource_id', tp.priority_desc, tp.priority_urgency"
            .", CONCAT(s.lastname, ', ', s.firstname) AS 'assigned_to', s.email AS 'assigned_email'"
            ." FROM ".TICKET_TABLE." t"
            ." LEFT JOIN ".THREAD_TABLE." th ON th.object_id = t.ticket_id AND th.object_type = 'T'"
            ." LEFT JOIN ".TICKET_STATUS_TABLE." ts ON ts.id = t.status_id"
            ." LEFT JOIN ".TICKET_CDATA_TABLE." tcd ON tcd.ticket_id = t.ticket_id"
            ." LEFT JOIN ".LIST_ITEM_TABLE." li ON li.id = tcd.resource_id"
            ." LEFT JOIN ".PRIORITY_TABLE." tp ON tp.priority_id = tcd.priority"
            ." LEFT JOIN ".USER_TABLE." u ON u.id = t.user_id"
            ." LEFT JOIN ".USER_EMAIL_TABLE." ue ON ue.user_id = t.user_id"
            ." LEFT JOIN ".USER_CDATA_TABLE." ucd ON ucd.user_id = u.id"
            ." LEFT JOIN ".STAFF_TABLE." s ON s.staff_id = t.staff_id"
            ." LEFT JOIN ".DEPT_TABLE." d ON d.id = t.dept_id";

        $sql .= $this->where($criteria);

        //die($sql);

        $result = $this->execQuery($sql);

        $this->fixResources($result);

        return $result;
    }

    function selectResources($criteria) {
        $resourceId = $this->escape(ApiUtility::getnum("resource_id", 0, $criteria));

        $sql = "SELECT tr.resource_id, tp.priority_urgency, tp.priority_desc, COUNT(*) AS 'ticket_count' FROM ".TICKET_TABLE." t"
             ." INNER JOIN ".TICKET_RESOURCE_TABLE." tr ON tr.ticket_id = t.ticket_id"
             ." INNER JOIN ".FORM_ENTRY_TABLE." fe ON fe.object_id = t.ticket_id AND fe.object_type = 'T'"
             ." INNER JOIN ".FORM_ANSWER_TABLE." fa ON fa.entry_id = fe.id AND fa.value_id IS NOT NULL"
             ." LEFT JOIN ".TICKET_PRIORITY_TABLE." tp ON tp.priority_id = fa.value_id"
             ." LEFT JOIN ".TICKET_STATUS_TABLE." ts ON ts.id = t.status_id"
             ." WHERE tr.resource_id IN ($resourceId) AND ts.state IN ('open')"
             ." GROUP BY tr.resource_id, tp.priority_urgency, tp.priority_desc";

        return $this->execQuery($sql);
    }

    function insert($vars) {
        $errors = array();
        $ticket = Ticket::create($vars, $errors, "api");
        return array('ticket' => $ticket, 'errors' => $errors);
    }

    function fixResources(&$tickets) {
        // Coming in to this funciton $t['resouce_id'] will look something like '#####:Resource Name'.
        // This function splits the value and sets the array item value to the numeric part.
        foreach ($tickets as &$t) {
            $val = $t['resource_id'];
            if ($val) {
                $parts = explode(':', $val);
                if (count($parts) > 1) {
                    $t['resource_id'] = $parts[0];
                }
            }
        }
    }

    function where($criteria) {
        $ticket_id = $this->escape(ApiUtility::getnum("ticket_id", 0, $criteria));
        $ticketID = $this->escape(ApiUtility::getnum("ticketID", 0, $criteria));
        $resourceId = $this->escape(ApiUtility::getnum("resource_id", 0, $criteria));
        $assignedTo = $this->escape(ApiUtility::getval("assigned_to", "", $criteria));
        $unassigned = $this->escape(ApiUtility::getnum("unassigned", 0, $criteria));
        $email = $this->escape(ApiUtility::getval("email", "", $criteria));
        $name = $this->escape(ApiUtility::getval("name", "", $criteria));
        $status = $this->escape(ApiUtility::getval("status", "", $criteria));
        $sdate = $this->escape(ApiUtility::getval("sdate", "", $criteria));
        $edate = $this->escape(ApiUtility::getval("edate", "", $criteria));
        $priorityDesc = $this->escape(ApiUtility::getval("priority_desc", "", $criteria));

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
        if ($resourceId > 0) {
            $result .= "$and li.value LIKE '$resourceId:%'";
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

        //die($sql);

        $result = $this->execQuery($sql);

        return $result;
    }

    function where($criteria) {
        $ticket_id = $this->escape(ApiUtility::getnum("ticket_id", 0, $criteria));
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

        $staff_id = $this->escape(ApiUtility::getnum("staff_id", 0, $criteria));

        $sql .= " WHERE t.staff_id = $staff_id";
        $sql .= " AND ts.state = 'open' AND DATEDIFF(NOW(), th.lastresponse) > ".$numdays;

        $result = $this->execQuery($sql);

        return $result[0]["stale_ticket_count"];
    }

    function selectOverdueTickets($criteria) {
        $sql = "SELECT COUNT(*) AS 'overdue_ticket_count' FROM ".TICKET_TABLE." t"
             ." LEFT JOIN ".TICKET_STATUS_TABLE." ts ON ts.id = t.status_id";

        $staff_id = $this->escape(ApiUtility::getnum("staff_id", 0, $criteria));

        $sql .= " WHERE t.staff_id = $staff_id";
        $sql .= " AND ts.state = 'open' AND t.isoverdue = 1";

        $result = $this->execQuery($sql);

        return $result[0]["overdue_ticket_count"];
    }

    function where($criteria) {
        $staff_id = $this->escape(ApiUtility::getnum("staff_id", 0, $criteria));
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

    protected function escape($val) {
        return db_input($val, false);
    }
}
