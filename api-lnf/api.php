<?php

require_once(INCLUDE_DIR.'class.plugin.php');
require_once('config.php');

class ApiPlugin extends Plugin {
    var $config_class = "ApiPluginConfig";

    function bootstrap() {
        $config = $this->getConfig();
        if ($_SERVER['REMOTE_ADDR'] == '141.213.6.57') {
            require_once('class.lnfapi.php');
            Signal::connect('api', function($dispatcher) {
                $dispatcher->append(
                    url('^/lnf$', function() {
                        $lnfapi = new LnfApiController();
                        $lnfapi->handleRequest();
                    })
                );
            });
        }
    }
}
