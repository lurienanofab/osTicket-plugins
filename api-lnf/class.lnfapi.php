<?php

require_once 'class.apiutility.php';
include_once INCLUDE_DIR.'class.api.php';
include_once INCLUDE_DIR.'class.ticket.php';

class LnfApiController extends ApiController {
    var $format;
    var $status_code = 200;

    function handleRequest() {
        if(!($key=$this->requireApiKey()) || !$key->canCreateTickets())
            return $this->exerr(401, __('API key not authorized'));

        $this->format = ApiUtility::getval("format", ApiUtility::getval("f", "json"));

        $action = ApiUtility::getval("action", "get-open-tickets");

        $content = $this->processAction($action);

        $this->response($this->status_code, $content);
    }

    function createApi() {
        $api = new LnfApi();
        $api->search = ApiUtility::getval("search", "");
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

    function processAction($action) {
        //the default action (no resourceId supplied) returns all open tickets
        $api = $this->createApi();
        $result = array();
        switch ($action){
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
                //$email = $this->getval("email", "");
                //$this->outputTickets(LNF::selectTicketsByEmail($email));
                $result = $this->getErrorContent('not yet implemented');
                break;
            case "select-tickets-by-resource":
                //$this->outputTickets($this->selectTickets());
                $result = $this->getErrorContent('not yet implemented');
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
                $api->status = "open";
                $result = $api->selectTickets();
                break;
            default:
                $result = $this->getErrorContent('unsupported action: ' . $action);
        }

        return $result;
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
        $sql = "SELECT t.*, ts.state AS 'status', cd.subject, tp.priority_id, tp.priority_desc, cd.resource_id AS 'resource_id', tp.priority_desc, tp.priority_urgency, CONCAT(s.lastname, ', ', s.firstname) AS 'assigned_to'"
            ." FROM ".TICKET_TABLE." t"
            ." INNER JOIN ".TICKET_STATUS_TABLE." ts ON ts.id = t.status_id"
            ." INNER JOIN ".TICKET_CDATA_TABLE." cd ON cd.ticket_id = t.ticket_id"
            ." INNER JOIN ".PRIORITY_TABLE." tp ON tp.priority_id = cd.priority"
            //." LEFT JOIN ".TICKET_RESOURCE_TABLE." tr ON t.ticket_id = tr.ticket_id"
            //." INNER JOIN ".TICKET_PRIORITY_TABLE." tp ON tp.priority_id = t.priority_id"
            ." LEFT JOIN ".STAFF_TABLE." s ON s.staff_id = t.staff_id";

        $sql .= $this->where($criteria);

        $result = $this->execQuery($sql);

        return $result;
    }

    function where($criteria) {
        $ticket_id = ApiUtility::getnum("ticket_id", 0, $criteria);
        $ticketID = ApiUtility::getnum("ticketID", 0, $criteria);
        $resourceId = ApiUtility::getnum("resource_id", 0, $criteria);
        $assignedTo = $this->escape(ApiUtility::getval("assigned_to", "", $criteria));
        $unassigned = ApiUtility::getnum("unassigned", 0, $criteria);
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
            $result .= "$and t.ticketID = $ticketID";
            $and = " AND";
        }
        if ($resourceId > 0) {
            $result .= "$and cd.resource_id = $resourceId";
            $and = " AND";
        }
        if ($this->hasValue($assignedTo)) {
            $result .= "$and CONCAT(s.lastname, ', ', s.firstname) LIKE $assignedTo";
            $and = " AND";
        }
        if ($unassigned == 1) {
            $result .= "$and t.staff_id = 0";
            $and = " AND";
        }
        if ($this->hasValue($email)) {
            $result .= "$and t.email LIKE $email";
            $and = " AND";
        }
        if ($this->hasValue($name)) {
            $result .= "$and t.name LIKE $name";
            $and = " AND";
        }
        if ($this->hasValue($status)) {
            $result .= "$and ts.state = $status";
            $and = " AND";
        }
        if ($this->hasValue($priorityDesc)) {
            $result .= "$and tp.priority_desc LIKE $priorityDesc";
            $and = " AND";
        }

        $result .= $this->whereDateRange($and, "t.created", $sdate, $edate);

        return $result;
    }

    private function whereDateRange($and, $column, $sdate, $edate) {
        $and = trim($and);
        $result = "";
        $result .= ($this->hasValue($sdate)) ? " $and $column >= $sdate" : "";
        if ($this->hasValue($edate)){
            $result .= (!empty($result)) ? " AND" : " $and";
            $result .= " $column < $edate";
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
        //print_r($sql);
        //die();
        try {
            $result = db_assoc_array(db_query($sql));
            return $result;
        } catch (Exception $e) {
            die(print_r($e, true));
        }
    }

    protected function escape($val) {
        return db_input($val);
    }
}
