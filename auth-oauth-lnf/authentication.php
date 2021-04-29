<?php

require_once(INCLUDE_DIR.'class.plugin.php');
require_once('config.php');

class OauthAuthPlugin extends Plugin {
    var $config_class = "OauthPluginConfig";

    function bootstrap() {
        $config = $this->getConfig();

        # ----- LNF ---------------------
        $lnf = $config->get('oauth-enabled');
        if (in_array($lnf, array('all', 'staff'))) {
            require_once('lnf.php');
            StaffAuthenticationBackend::register(
                new LnfStaffAuthBackend($this->getConfig()));
        }
        if (in_array($google, array('all', 'client'))) {
            require_once('lnf.php');
            UserAuthenticationBackend::register(
                new LnfClientAuthBackend($this->getConfig()));
        }
    }
}

require_once(INCLUDE_DIR.'UniversalClassLoader.php');
use Symfony\Component\ClassLoader\UniversalClassLoader_osTicket;
$loader = new UniversalClassLoader_osTicket();
$loader->registerNamespaceFallbacks(array(
    dirname(__file__).'/lib'));
$loader->register();
