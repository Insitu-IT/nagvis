<?php
/*****************************************************************************
 *
 * CoreModLogonMultisite.php - Module for handling cookie based logins as
 *                             generated by multisite
 *
 * Copyright (c) 2004-2011 NagVis Project (Contact: info@nagvis.org)
 *
 * License:
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2 as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
 *
 *****************************************************************************/

class CoreModLogonMultisite extends CoreModule {
    protected $CORE;
    private   $bVerbose;
    private   $htpasswdPath;
    private   $secretPath;
    private   $allowSessionStore;

    public function __construct($CORE) {
        $this->sName = 'LogonMultisite';
        $this->CORE = $CORE;

        $this->aActions = Array('view' => 0);
        $this->bVerbose = true;
        $this->allowSessionStore = false;

        $this->htpasswdPath = cfg('global', 'logon_multisite_htpasswd');
        if(!file_exists($this->htpasswdPath)) {
            throw new NagVisException(l('LogonMultisite: The htpasswd file &quot;[PATH]&quot; does not exist.',
                          array('PATH' => $this->htpasswdPath)));
        }

        $this->secretPath   = cfg('global', 'logon_multisite_secret');
        if(!file_exists($this->htpasswdPath)) {
            throw new NagVisException(l('LogonMultisite: The auth secret file &quot;[PATH]&quot; does not exist.',
                          array('PATH' => $this->secretPath)));
        }
    }

    // This logon module needs to be called every time
    public function allowSessionStore() {
        return $this->allowSessionStore;
    }

    public function beQuiet() {
        // Sometimes less is more...
        $this->bVerbose = false;
    }

    private function loadHtpasswd() {
        $creds = array();
        foreach(file($this->htpasswdPath) AS $line) {
            list($username, $pwhash) = explode(':', $line, 2);
            $creds[$username] = rtrim($pwhash);
        }
        return $creds;
    }

    private function loadSecret() {
        return trim(file_get_contents($this->secretPath));
    }

    private function generateHash($username, $now, $pwhash) {
        $secret = $this->loadSecret();
        return md5($username . $now . $pwhash . $secret);
    }

    private function checkAuthCookie($cookieName) {
        if(!isset($_COOKIE[$cookieName]) || $_COOKIE[$cookieName] == '') {
            throw new Exception();
        }

        list($username, $issueTime, $cookieHash) = explode(':', $_COOKIE[$cookieName], 3);

        // FIXME: Check expire time?
        
        $users = $this->loadHtpasswd();
        if(!isset($users[$username])) {
            throw new Exception();
        }
        $pwhash = $users[$username];

        // Validate the hash
        if($cookieHash != $this->generateHash($username, $issueTime, $pwhash)) {
            throw new Exception();
        }

        // FIXME: Maybe renew the cookie here too

        return $username;
    }

    private function checkAuth() {
        foreach(array_keys($_COOKIE) AS $cookieName) {
            if(substr($cookieName, 0, 5) != 'auth_') {
                continue;
            }
            try {
                $name = $this->checkAuthCookie($cookieName);
                $_SESSION['multisiteLogonCookie'] = $cookieName;
                return $name;
            } catch(Exception $e) {}
        }
        return '';
    }

    public function handleAction() {
        $sReturn = '';

        if($this->offersAction($this->sAction)) {
            switch($this->sAction) {
                case 'view':
                    // Check if user is already authenticated
                    if(!isset($this->AUTHENTICATION) || !$this->AUTHENTICATION->isAuthenticated()) {
                        $sUser = $this->checkAuth();
                        if($sUser === '') {
                            // FIXME: Get the real path to multisite
                            header('Location:../../../check_mk/login.py?_origin=' . $_SERVER['REQUEST_URI']);
                            return false;
                        }

                        // Get the authentication instance from the core
                        $this->AUTHENTICATION = $this->CORE->getAuthentication();

                        // Check if the user exists
                        if(!$this->AUTHENTICATION->checkUserExists($sUser)) {
                            $bCreateUser = cfg('global', 'logon_multisite_createuser');
                            if(settype($bCreateUser, 'boolean')) {
                                // Create user when not existing yet
                                // Important to add a random password here. When someone
                                // changes the logon mechanism to e.g. LogonDialog it
                                // would be possible to logon with a hardcoded password
                                $this->AUTHENTICATION->createUser($sUser, (time() * rand(1, 10)));

                                $role = cfg('global', 'logon_multisite_createrole');
                                if($role !== '') {
                                    $AUTHORISATION = new CoreAuthorisationHandler($this->CORE, $this->AUTHENTICATION, cfg('global', 'authorisationmodule'));
                                    $AUTHORISATION->parsePermissions();
                                    $AUTHORISATION->updateUserRoles($AUTHORISATION->getUserId($sUser), Array($AUTHORISATION->getRoleId($role)));
                                }
                            } else {
                                if($this->bVerbose) {
                                    throw new NagVisException(l('Unable to authenticate user. User does not exist.'));
                                }
                                return false;
                            }
                        }

                        // Authenticate the user without providing logon information

                        // Reset the authentication check. Without this the cached result
                        // would prevent the authentication check with the given credentials
                        $this->AUTHENTICATION->resetAuthCheck();

                        // Set credentials to authenticate
                        // Use dummy password - with empty or unset password the session
                        // auth module would not authenticate the user
                        $this->AUTHENTICATION->passCredentials(Array('user' => $sUser, 'password' => '.'));

                        // Try to authenticate the user
                        if($this->AUTHENTICATION->isAuthenticated(AUTH_TRUST_USERNAME)) {
                            // Redirect without message to the user
                            header('Location:'.CoreRequestHandler::getRequestUri(cfg('paths', 'htmlbase')));
                        } else {
                            // Invalid credentials
                            // FIXME: Count tries and maybe block somehow
                            if($this->bVerbose) {
                                throw new NagVisException(l('You entered invalid credentials.'),
                                                          l('Authentication failed'),
                                                          10,
                                                          CoreRequestHandler::getReferer(''));
                            }

                            return false;
                        }
                    } else {
                        // When the user is already authenticated redirect to start page (overview)
                        header('Location:'.CoreRequestHandler::getRequestUri(cfg('paths', 'htmlbase')));
                    }
                break;
            }
        }

        return $sReturn;
    }
}

?>