<?php

require_once(INCLUDE_DIR.'class.plugin.php');
require_once('config.php');
require_once('class.lnfapi.php');

class ApiPlugin extends Plugin {
    var $config_class = "ApiPluginConfig";

    function bootstrap() {
        $config = $this->getConfig();

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
