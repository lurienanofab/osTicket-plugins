<?php

require_once INCLUDE_DIR . 'class.plugin.php';

class OauthPluginConfig extends PluginConfig {

    // Provide compatibility function for versions of osTicket prior to
    // translation support (v1.9.4)
    function translate() {
        if (!method_exists('Plugin', 'translate')) {
            return array(
                function($x) { return $x; },
                function($x, $y, $n) { return $n != 1 ? $y : $x; },
            );
        }
        return Plugin::translate('auth-oauth');
    }

    function getOptions() {
        list($__, $_N) = self::translate();
        $modes = new ChoiceField(array(
            'label' => $__('Authentication'),
            'choices' => array(
                '0' => $__('Disabled'),
                'staff' => $__('Agents Only'),
                'client' => $__('Clients Only'),
                'all' => $__('Agents and Clients'),
            ),
        ));
        return array(
            'lnf' => new SectionBreakField(array(
                'label' => $__('LNF Authentication'),
            )),
            'oauth-client-id' => new TextboxField(array(
                'label' => $__('Client ID'),
                'configuration' => array('size'=>60, 'length'=>100),
            )),
            'oauth-client-secret' => new TextboxField(array(
                'label' => $__('Client Secret'),
                'configuration' => array('size'=>60, 'length'=>100),
            )),
            'oauth-authorize-url' => new TextboxField(array(
                 'label' => $__('Authorize Url'),
                 'configuration' => array('size'=>80, 'length'=>200),
            )),
            'oauth-access-url' => new TextboxField(array(
                 'label' => $__('Access Url'),
                 'configuration' => array('size'=>80, 'length'=>200),
            )),
            'oauth-profile-url' => new TextboxField(array(
                 'label' => $__('Profile Url'),
                 'configuration' => array('size'=>80, 'length'=>200),
            )),
            'oauth-enabled' => clone $modes,
        );
    }
}
