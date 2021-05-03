<?php

require_once 'class.apiutility.php';
include_once INCLUDE_DIR.'class.api.php';
include_once INCLUDE_DIR.'class.ticket.php';

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
        $api->sdate = ApiUtility::getval("sdate", $api->defaultStartDate());
        $api->edate = ApiUtility::getval("edate", "");
        return $api;
    }

    function processAction() {
        //the default action (no resourceId supplied) returns all open tickets
        $api = $this->createApi();
        $result = array();
        switch ($this->action){
            case "add-ticket":
                //$result = $this->addTicket();
                //$this->outputTickets($this->selectTickets(), "[action:$this->action] created ticket #{$result['ticket']->extid} in {$result['timeTaken']} seconds");
                $result = $this->getErrorContent('not yet implemented');
                break;
            case "ticket-detail":
                //if ($this->ticketID != 0){
                //    $detail = $this->selectTicketDetail();
                //    $this->outputTicketDetail(array("error"=>false, "message"=>"ok: $this->ticketID", "detail"=>$detail));
                //}
                //else
                //    $this->outputTicketDetail(array("error"=>true, "message"=>"Invalid parameter: ticketID"));
                $result = $this->getErrorContent('not yet implemented');
                break;
            case "dept-membership":
                //$staff_id = $this->getval("staff_id", 0);
                //$html = LNF::getDeptMembership(array("staff_id"=>$staff_id));
                //echo $html;
                $result = $this->getErrorContent('not yet implemented');
                break;
            case "select-tickets-by-email":
                $api->search = "by-email";
                $result = array('message' => $this->getMessage($api, array('email' => $api->email)), 'data' => $api->selectTickets());
                break;
            case "select-tickets-by-resource":
                $api->search = "by-resource";
                $result = array('message' => $this->getMessage($api, array('resource_id' => $api->resource_id)), 'data' => $api->selectTickets());
                break;
            case "post-message":
                //if ($this->ticketID != 0){
                //    $message = $this->getval("message", "");
                //    $ticket = TicketPlugin::getTicket($this->ticketID);
                //    $ticket->postMessage($message);
                //    $detail = $this->selectTicketDetail();
                //    $this->outputTicketDetail(array("error"=>false, "message"=>"ok: $this->ticketID", "detail"=>$detail));
                //}
                //else
                //    $this->outputTicketDetail(array("error"=>true, "message"=>"Invalid parameter: ticketID"));
                $result = $this->getErrorContent('not yet implemented');
                break;
            case "summary":
                //$resources = $this->getval("resources", "");
                //if ($resources)
                //    echo $this->outputSummary(array("error"=>false, "message"=>"ok: $resources", "summary"=>LNF::summary($resources)));//$this->outputSummar>                else
                //    $this->outputSummary(array("error"=>true, "message"=>"Invalid parameter: resources"));
                $result = $this->getErrorContent('not yet implemented');
                break;
            case "get-ticket-counts":
                //$user = new Staff($this->getval("staff_userID", ""));
                //$staleCount = LNF::getStaleTicketCount($user);
                //$overdueCount = LNF::getOverdueTicketCount($user);
                //echo json_encode(array("staleTicketCount"=>$staleCount, "overdueTicketCount"=>$overdueCount));
                $result = $this->getErrorContent('not yet implemented');
                break;
            case "":
            case "get-open-tickets":
                $api->search = "by-daterange";
                $api->status = "open";
                $result = array('message' => $this->getMessage($api), 'data' => $api->selectTickets());
                break;
            default:
                $result = $this->getErrorContent('unsupported action: ' . $this->action);
        }

        return $result;
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
        return array('message' => $msg);
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

    function __construct() {
        $this->ticket_data = new TicketData();
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
                "edate"             => $this->edate
            );
        }
    }

    function selectTickets() {
        $criteria = $this->criteria();
        $result = $this->ticket_data->select($criteria);
        return $result;
    }

    function defaultStartDate(){
        //first day of previous month
        $today = getdate();
        $fom = $today['year'].'-'.$today['mon'].'-1';
        return date('Y-m-d', strtotime("$fom -1 month"));
    }
}

class TicketData extends ApiData {
    function select($criteria) {
        // the goal here is to return the same columns as the old lnfapi version
        $sql = "SELECT t.ticket_id, t.number AS 'ticketID', t.dept_id, IFNULL(tp.priority_id, 0) AS 'priority_id', t.topic_id, t.staff_id, ue.address AS 'email', u.name"
            .", tcd.subject, t.helptopic, ucd.phone, NULL AS 'phone_ext', t.ip_address, ts.state AS 'status', t.source, t.isoverdue, t.isanswered, t.duedate, t.reopened"
            .", t.closed, th.lastmessage, th.lastresponse, t.created, t.updated, li.value AS 'resource_id', tp.priority_desc, tp.priority_urgency"
            .", CONCAT(s.lastname, ', ', s.firstname) AS 'assigned_to'"
            ." FROM ".TICKET_TABLE." t"
            ." LEFT JOIN ".THREAD_TABLE." th ON th.object_id = t.ticket_id AND th.object_type = 'T'"
            ." LEFT JOIN ".TICKET_STATUS_TABLE." ts ON ts.id = t.status_id"
            ." LEFT JOIN ".TICKET_CDATA_TABLE." tcd ON tcd.ticket_id = t.ticket_id"
            ." LEFT JOIN ".LIST_ITEM_TABLE." li ON li.id = tcd.resource_id"
            ." LEFT JOIN ".PRIORITY_TABLE." tp ON tp.priority_id = tcd.priority"
            ." LEFT JOIN ".USER_TABLE." u ON u.id = t.user_id"
            ." LEFT JOIN ".USER_EMAIL_TABLE." ue ON ue.id = t.user_email_id AND u.id = ue.user_id"
            ." LEFT JOIN ".USER_CDATA_TABLE." ucd ON ucd.user_id = u.id"
            ." LEFT JOIN ".STAFF_TABLE." s ON s.staff_id = t.staff_id";

        $sql .= $this->where($criteria);

        //die($sql);

        $result = $this->execQuery($sql);

        $this->fixResources($result);

        return $result;
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
