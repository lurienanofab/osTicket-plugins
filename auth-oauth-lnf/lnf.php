<?php
include 'vendor/autoload.php';

use ohmy\Auth2;

class LnfAuth {
    var $config;
    var $access_token;

    function __construct($config) {
        $this->config = $config;
    }

    function triggerAuth() {
        $self = $this;
        return Auth2::legs(3)
            ->set('id', $this->config->get('oauth-client-id'))
            ->set('secret', $this->config->get('oauth-client-secret'))
            ->set('redirect', 'https://' . $_SERVER['HTTP_HOST'] . ROOT_PATH . 'api/auth/ext')
            ->set('scope', 'profile email')

            ->authorize($this->config->get('oauth-authorize-url'))
            ->access($this->config->get('oauth-access-url'))

            ->finally(function($data) use ($self) {
                $self->access_token = $data['access_token'];
            });
    }
}

class LnfStaffAuthBackend extends ExternalStaffAuthenticationBackend {
    static $id = "lnf";
    static $name = "LNF Online Services";

    static $sign_in_image_url = null;
    static $service_name = "LNF Online Services";

    var $config;

    function __construct($config) {
        $this->config = $config;
        $this->lnf = new LnfAuth($config);
    }

    function signOn() {
        // TODO: Check session for auth token
        if (isset($_SESSION[':oauth']['email'])) {
            if (($staff = StaffSession::lookup(array('email' => $_SESSION[':oauth']['email'])))
                && $staff->getId()
            ) {
                if (!$staff instanceof StaffSession) {
                    // osTicket <= v1.9.7 or so
                    $staff = new StaffSession($user->getId());
                }
                return $staff;
            }
            else
                $_SESSION['_staff']['auth']['msg'] = 'Have your administrator create a local account';
        }
    }

    static function signOut($user) {
        parent::signOut($user);
        unset($_SESSION[':oauth']);
    }


    function triggerAuth() {
        parent::triggerAuth();
        $lnf = $this->lnf->triggerAuth();
        $profile_url = $this->config->get('oauth-profile-url') . '?access_token=' . $this->lnf->access_token;
        $lnf->GET($profile_url)
            ->then(function($response) {
                require_once INCLUDE_DIR . 'class.json.php';
                if ($json = JsonDataParser::decode($response->text))
                {
                    if (in_array('Staff', $json['roles']))
                        $_SESSION[':oauth']['email'] = $json['email'];
                }
                Http::redirect(ROOT_PATH . 'scp');
            }
        );
    }
}

class LnfClientAuthBackend extends ExternalUserAuthenticationBackend {
    static $id = "lnf.client";
    static $name = "LNF Online Services";

    static $sign_in_image_url = null;
    static $service_name = "LNF Online Services";

    function __construct($config) {
        $this->config = $config;
        $this->lnf = new LnfAuth($config);
    }

    function supportsInteractiveAuthentication() {
        return false;
    }

    function signOn() {
        // TODO: Check session for auth token
        if (isset($_SESSION[':oauth']['email'])) {
            if (($acct = ClientAccount::lookupByUsername($_SESSION[':oauth']['email']))
                    && $acct->getId()
                    && ($client = new ClientSession(new EndUser($acct->getUser()))))
                return $client;

            elseif (isset($_SESSION[':oauth']['profile'])) {
                // TODO: Prepare ClientCreateRequest
                $profile = $_SESSION[':oauth']['profile'];
                $info = array(
                    'email' => $_SESSION[':oauth']['email'],
                    'name' => $profile['displayName'],
                );
                return new ClientCreateRequest($this, $info['email'], $info);
            }
        }
    }

    static function signOut($user) {
        parent::signOut($user);
        unset($_SESSION[':oauth']);
    }

    function triggerAuth() {
        require_once INCLUDE_DIR . 'class.json.php';
        parent::triggerAuth();
        $lnf = $this->lnf->triggerAuth();
        $token = $this->lnf->access_token;
        $lnf->GET($this->config->get('oauth-profile-url') . '?access_token=' . $access_token)
            ->then(function($response) use ($lnf, $token) {
                if (!($json = JsonDataParser::decode($response->text)))
                    return;
                $_SESSION[':oauth']['email'] = $json['email'];
                $_SESSION[':oauth']['profile'] = $json;
                Http::redirect(ROOT_PATH . 'login.php');
            }
        );
    }
}


