<?php

require_once 'class.xmlutility.php';
include_once INCLUDE_DIR.'class.api.php';
include_once INCLUDE_DIR.'class.ticket.php';

class LnfApiController extends ApiController {
    var $format;

    function handleRequest() {
        if(!($key=$this->requireApiKey()) || !$key->canCreateTickets())
            return $this->exerr(401, __('API key not authorized'));

        $this->format = $this->reqvar("format", "json");

        $action = $this->reqvar("action", "get-tickets");

        $content = $this->processAction($action);

        $this->response(200, $content);
    }

    function processAction($action) {
        return array('action' => $action);
    }

    function reqvar($key, $defval='') {
        return isset($_REQUEST[$key]) ? $_REQUEST[$key] : $defval;
    }

    function response($code, $resp) {
        $content = null;
        $contentType = null;

        switch ($this->format) {
            case 'xml':
                $contentType = 'text/xml';
                $content = XmlUtility::createXml($resp)->asXML();
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

