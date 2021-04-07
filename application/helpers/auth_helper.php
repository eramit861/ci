<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class auth_helper
{

    const SESSION_ELEMENT_NAME = 'kabaadiUserSession';
    const UNI_USER_COOKIE_NAME = '_uKAB';
    const DB_TBL_USER_PRR = 'tbl_user_password_reset_requests';
    const DB_TBL_USER_PRR_PREFIX = 'uprr_';
    const DB_TBL_USER_AUTH = 'tbl_user_auth_token';
    const DB_TBL_USER_AUTH_PREFIX = 'uauth_';
    const DB_TBL_USER_LOGIN_HISTORY = 'tbl_login_history';
    const DB_TBL_USER_LOGIN_HISTORY_PREFIX = 'lhistory_';


    public function __construct()
    {
        $this->load->database();
    }


    public static function encryptPassword($pass)
    {
        return md5(PASSWORD_SALT . $pass . PASSWORD_SALT);
    }

    public function logFailedAttempt($ip, $email)
    {
        $this->db->delete('tbl_failed_login_attempts', array('attempt_time <' => date('Y-m-d H:i:s', strtotime("-7 Day"))));
        $this->db->where('attempt_time < ' . date('Y-m-d H:i:s', strtotime("-7 Day")));
        $this->db->set(['attempt_username' => $email, 'attempt_ip' => $ip, 'attempt_time' => date('Y-m-d H:i:s')]);
        $this->db->insert('tbl_failed_login_attempts');
    }

    public function clearFailedAttempt($ip, $email)
    {
        $this->db->delete('tbl_failed_login_attempts');
        $this->db->where('attempt_username =' . $email);
        $this->db->where('attempt_ip =' . $ip);
     }

    public function isBruteForceAttempt($ip, $email)
    {
        $this->db->select('COUNT(*) AS total');
        $this->db->from('tbl_failed_login_attempts');
        $this->db->where('attempt_ip', $ip);
        $this->db->or_where('attempt_username', $email);
        $this->db->where('attempt_time', '>='.date('Y-m-d H:i:s', strtotime("-5 minutes")));
        $query = $this->db->get();
        $row = $query->result_array();  
        return ($row['total'] > 3);
    }

    public static function doCookieLogin($returnAuth = true)
    {
        $cookieName = self::UNI_USER_COOKIE_NAME;
        if (isset($_COOKIE[$cookieName])) {
            $token = $_COOKIE[$cookieName];
            $authRow = self::checkLoginTokenInDB($token);
            if (strlen($token) != 25 || empty($authRow)) {
                self::clearLoggedUserLoginCookie();
                return false;
            }
            $browser = CommonHelper::userAgent();
            if (strtotime($authRow['uauth_expiry']) < strtotime('now') || $authRow['uauth_browser'] != $browser || CommonHelper::userIp() != $authRow['uauth_last_ip']) {
                self::clearLoggedUserLoginCookie();
                return false;
            }
            $ths = new UserAuthentication();
            if ($ths->loginByCookie($authRow)) {
                if (true === $returnAuth) {
                    return $authRow;
                }
                return true;
            }
            return false;
        }
        return false;
    }

    public function login($email, $password, $ip, $encryptPassword = true, $loggedVia = AppConstants::LOGIN_VIA_WEB, $platform = 0)
    {
        if ($this->isBruteForceAttempt($ip, $email)) {
            $this->error = Labels::getLabel('ERR_LOGIN_ATTEMPT_LIMIT_EXCEEDED_PLEASE_TRY_LATER', $this->commonLangId);
            return false;
        }
        $loginBy = 0;
        $db = FatApp::getDb();
        $srch = User::getSearchObject();
        $srch->addCondition('user_email', '=', $email);
        $srch->addCondition('user_is_able_login', '=', AppConstants::YES);
        $srch->addCondition('company.company_active', '=', AppConstants::YES);
        $srch->doNotCalculateRecords();
        if ($encryptPassword) {
            $srch->addCondition('user_password', '=', UserAuthentication::encryptPassword($password));
        } else {
            if ($loggedVia == AppConstants::LOGIN_VIA_WEB) {
                $loginBy = AdminAuthentication::getLoggedAdminId();
            }
            $srch->addCondition('user_password', '=', $password);
        }

        $rs = $srch->getResultSet();
        $row = $db->fetch($rs);


        if (!empty($row)) {
            /* Restricting non-retailer user to login at retailer app */
            if (CommonHelper::isRetailerApiCall() && $row['company_is_retailer'] == AppConstants::NO) {
                $this->error = Labels::getLabel("ERR_You_doesn't_have_permission_to_login", $this->commonLangId);
                return false;
            }

            /* Restricting typical retailer user/retailer admin to login at uni app */
            if (CommonHelper::isUniApiCall() && !Privilege::canCustomerAccess($row['user_id'], false)) {
                $this->error = Labels::getLabel("ERR_You_doesn't_have_permission_to_login", $this->commonLangId);
                return false;
            }

            /* Checking if the user is retailer admin or else has the minimal user acess (buyer/vendor) to acess the weba areas */
            if (!API_CALL && !Privilege::canCustomerAccess($row['user_id'], false) && !Privilege::isRetailerAdmin($row['user_id'], false)) {
                $this->error = Labels::getLabel("ERR_You_doesn't_have_permission_to_login", $this->commonLangId);
                return false;
            }
        }

        if (!$row) {
            $this->logFailedAttempt($ip, $email);
            $userDetails = $this->getUserByEmail($email, false);

            if (!empty($userDetails)) {
                self::logLoginHistory($userDetails['user_id'], AppConstants::NO, $loginBy, 0, $loggedVia, $platform);
            }

            $this->error = Labels::getLabel('ERR_INVALID_EMAIL_OR_PASSWORD');
            return false;
        }

        if ($row['user_active'] != AppConstants::ACTIVE) {
            self::logLoginHistory($row['user_id'], AppConstants::NO, $loginBy, 0, $loggedVia);
            $this->error = Labels::getLabel('ERR_YOUR_ACCOUNT_IS_NOT_ACTIVE', $this->commonLangId);
            return false;
        }

        $historyId = self::logLoginHistory($row['user_id'], AppConstants::YES, $loginBy, 0, $loggedVia, $platform);
        if (!API_CALL) {
            $row['user_ip'] = $ip;
            $row['history_id'] = $historyId;
            $this->setSession($row);
        }
        $this->clearFailedAttempt($ip, $email);
        return true;
    }

    public function loginViaHashedToken($userId)
    {
        $db = FatApp::getDb();
        $srch = User::getSearchObject();
        $srch->addCondition('user_id', '=', $userId);
        $srch->addCondition('user_is_able_login', '=', AppConstants::YES);
        $srch->addCondition('company.company_active', '=', AppConstants::YES);
        $srch->doNotCalculateRecords();
        $srch->doNotLimitRecords();
        $rs = $srch->getResultSet();
        $row = $db->fetch($rs);
        if (empty($row)) {
            $this->error = Labels::getLabel("ERR_INVALID_EMAIL_OR_PASSWORD");
            return false;
        }
        $historyId = self::logLoginHistory($row['user_id'], AppConstants::YES, 0, true, AppConstants::LOGIN_VIA_WEB, AppConstants::PLATFORM_EZCALC);
        $row['user_ip'] = CommonHelper::userIp();
        $row['history_id'] = $historyId;
        $this->setSession($row);
        return true;
    }

    public static function logLoginHistory($userId, $status = 0, $loginBy = 0, $isAdmin = 0, $loggedVia = AppConstants::LOGIN_VIA_WEB, $platform = 0)
    {
        $ip = CommonHelper::userIp();
        $db = FatApp::getDb();
        $historyId = 0;
        $srch = new SearchBase(static::DB_TBL_USER_LOGIN_HISTORY);
        $srch->addFld('lhistory_id');
        if (!API_CALL && isset($_SESSION[UserAuthentication::SESSION_ELEMENT_NAME]['history_id'])) {
            $historyId = $_SESSION[UserAuthentication::SESSION_ELEMENT_NAME]['history_id'];
            $srch->addCondition('lhistory_id', '=', $historyId);
        } else {
            if ($platform > 0) {
                $srch->addCondition('lhistory_platform', '=', $platform);
            }
            $srch->addCondition('lhistory_user_id', '=', $userId);
        }

        $srch->addCondition('lhistory_login_via', '=', $loggedVia);
        $srch->addOrder('lhistory_id', 'DESC');
        $srch->doNotCalculateRecords();
        $srch->setPageSize(1);
        $record = FatApp::getDb()->fetch($srch->getResultSet());
        /** to cover last seen from api end */
        if (API_CALL && $record['lhistory_id'] > 0 && $platform == 0) {
            $historyId = $record['lhistory_id'];
        }

        if ($historyId > 0) {
            $stmt = ['smt' => 'lhistory_id = ?', 'vals' => [$historyId]];
            $db->updateFromArray(static::DB_TBL_USER_LOGIN_HISTORY, ['lhistory_last_seen' => date('Y-m-d H:i:s')], $stmt);
            return $historyId;
        }

        return self::addLoginHistory($userId, $ip, $status, $loginBy, $isAdmin, $loggedVia, $platform);
    }

    public static function addLoginHistory($userId, $ip, $status, $loginBy, $isAdmin, $loggedVia = AppConstants::LOGIN_VIA_WEB, $platform = 0)
    {
        $data = [
            'lhistory_user_id' => $userId,
            'lhistory_user_browser' => CommonHelper::userAgent(),
            'lhistory_user_ip' => $ip,
            'lhistory_success' => $status,
            'lhistory_login_by' => $loginBy,
            'lhistory_is_admin' => $isAdmin,
            'lhistory_login_time' => date('Y-m-d H:i:s'),
            'lhistory_last_seen' => date('Y-m-d H:i:s'),
            'lhistory_login_via' => $loggedVia,
            'lhistory_platform' => $platform
        ];
        $db = FatApp::getDb();
        $db->insertFromArray(static::DB_TBL_USER_LOGIN_HISTORY, $data);
        return $db->getInsertId();
    }

    public function updateSession($data)
    {
        if (API_CALL) {
            return true;
        }
        $_SESSION[UserAuthentication::SESSION_ELEMENT_NAME]['user_email'] = $data['user_email'];
        $_SESSION[UserAuthentication::SESSION_ELEMENT_NAME]['user_name'] = $data['user_name'];
        $_SESSION[UserAuthentication::SESSION_ELEMENT_NAME]['company_name'] = $data['company_name'];
        return true;
    }

    private function setSession($data)
    {
        $_SESSION[UserAuthentication::SESSION_ELEMENT_NAME] = [
            'user_id' => $data['user_id'],
            'user_ip' => $data['user_ip'],
            'user_email' => $data['user_email'],
            'user_name' => $data['user_name'],
            'company_id' => $data['company_id'],
            'company_name' => $data['company_name'],
            'history_id' => $data['history_id'],
            'user_alert' => FatApp::getConfig('CONF_USER_NOTIFICATION_ALERT'),
            'user_default_sort_by' => $data['user_default_sort_by']
        ];
        return true;
    }

    private function loginByCookie($authRow)
    {
        $user = new User($authRow['uauth_user_id']);
        if ($row = $user->getUserInfo(null, true)) {
            $historyId = self::logLoginHistory($row['user_id'], AppConstants::YES);
            $row['history_id'] = $historyId;
            $row['user_ip'] = CommonHelper::userIp();
            $this->setSession($row);
            return true;
        }
        self::clearLoggedUserLoginCookie();
        return false;
    }

    public static function saveRememberLoginToken(&$values)
    {
        $db = FatApp::getDb();
        if ($db->insertFromArray(static::DB_TBL_USER_AUTH, $values)) {
            return true;
        }
        return false;
    }

    public static function checkLoginTokenInDB($token)
    {
        $db = FatApp::getDb();
        tbl_user_auth_token
        
        $srch = new SearchBase(static::DB_TBL_USER_AUTH);
        $srch->addCondition('uauth_token', '=', $token);
        $srch->doNotCalculateRecords();
        $srch->doNotLimitRecords();
        $rs = $srch->getResultSet();
        return $db->fetch($rs);
    }

    public static function clearLoggedUserLoginCookie()
    {
        if (!isset($_COOKIE[static::UNI_USER_COOKIE_NAME])) {
            return false;
        }
        $db = FatApp::getDb();
        if (strlen($_COOKIE[static::UNI_USER_COOKIE_NAME])) {
            $db->deleteRecords(static::DB_TBL_USER_AUTH, ['smt' => 'uauth_token = ?', 'vals' => [$_COOKIE[static::UNI_USER_COOKIE_NAME]]]);
        }
        setcookie($_COOKIE[static::UNI_USER_COOKIE_NAME], '', time() - 3600, '/', '', FALSE, TRUE);
        return true;
    }

    public static function isUserLogged($ip = '', $return = false)
    {
        if ($ip == '') {
            $ip = CommonHelper::userIp();
        }

        if (API_CALL) {
            return static::tryLoginForApi($return);
        }

        if (
            isset($_SESSION[static::SESSION_ELEMENT_NAME]) &&
            /* $_SESSION[static::SESSION_ELEMENT_NAME]['user_ip'] == $ip && */
            is_numeric($_SESSION[static::SESSION_ELEMENT_NAME]['user_id']) &&
            0 < $_SESSION[static::SESSION_ELEMENT_NAME]['user_id']
        ) {
            return true;
        }
    }

    public static function tryLoginForApi($return = false)
    {
        $api = new Api();
        if (false === $api->isValidRequest()) {
            if ($return) {
                return false;
            }
            CommonHelper::dieWithJsonData(AppConstants::TOKEN_INVALID, array('msg' => Labels::getLabel('APIMSG_Invalid_API_call')), 1);
        }
        self::logLoginHistory(self::getLoggedUserId(), AppConstants::YES, 0, true, CommonHelper::getDeviceTypeId());
        return true;
    }

    public static function getLoggedUserAttribute($attr, $returnNullIfNotLogged = false)
    {
        if (API_CALL) {
            $srch = new SearchBase(AppToken::DB_TBL);
            $srch->joinTable(User::DB_TBL, 'INNER JOIN', 'user_id = apptoken_user_id');
            $srch->joinTable(Company::DB_TBL, 'INNER JOIN', 'company_id = user_company_id');
            $srch->addCondition('apptoken_token', '=', $_SERVER['HTTP_TOKEN'] ?? '');
            $srch->addFld($attr);
            $user = FatApp::getDb()->fetch($srch->getResultSet());
            if (empty($user) && $returnNullIfNotLogged) {
                return null;
            }
            if (empty($user)) {
                return false;
            }
            return $user[$attr] ?? '';
        }

        if (!static::isUserLogged()) {
            if ($returnNullIfNotLogged) {
                return null;
            }
            if (FatUtility::isAjaxCall()) {
                FatUtility::dieJsonError(Labels::getLabel('MSG_USER_NOT_LOGGED'));
            }
            return false;
        }

        if (array_key_exists($attr, $_SESSION[static::SESSION_ELEMENT_NAME])) {
            return $_SESSION[static::SESSION_ELEMENT_NAME][$attr];
        }

        return (new User($_SESSION[static::SESSION_ELEMENT_NAME]['user_id']))->getUserInfo($attr);
    }

    public static function getLoggedUserId($returnZeroIfNotLogged = false)
    {
        if (API_CALL) {
            $appToken = new AppToken();
            $token = isset($_SERVER['HTTP_TOKEN']) ? $_SERVER['HTTP_TOKEN'] : '';
            $token_data = $appToken->getTokenData($token);
            if (empty($token_data)) {
                return false;
            }

            if (isset($token_data[AppToken::DB_TBL_PREFIX . 'user_id'])) {
                $userId = $token_data[AppToken::DB_TBL_PREFIX . 'user_id'];
                return FatUtility::int($userId);
            }
        }
        return FatUtility::int(static::getLoggedUserAttribute('user_id', $returnZeroIfNotLogged));
    }

    public function getUserByEmail($email, $isActive = true)
    {
        $db = FatApp::getDb();
        $srch = User::getSearchObject();
        $srch->addCondition('user_email', '=', $email);
        if (true === $isActive) {
            $srch->addCondition('user_active', '=', AppConstants::ACTIVE);
        }
        $srch->addMultipleFields(['user_id', 'user_name', 'user_email', 'user_password', 'user_active']);
        $srch->doNotCalculateRecords();
        $srch->doNotLimitRecords();
        $rs = $srch->getResultSet();
        if (!$row = $db->fetch($rs, User::tblFld('id'))) {
            $this->error = Labels::getLabel('ERR_INVALID_EMAIL_ADDRESS', $this->commonLangId);
            return false;
        }
        return $row;
    }

    public function checkUserPwdResetRequest($userId)
    {
        $db = FatApp::getDb();
        $srch = new SearchBase(static::DB_TBL_USER_PRR);
        $srch->addCondition('uprr_user_id', '=', $userId);
        $srch->addCondition('uprr_expiry', '>', date('Y-m-d H:i:s'));
        $srch->addFld('uprr_user_id');
        $srch->doNotCalculateRecords();
        $srch->doNotLimitRecords();
        $rs = $srch->getResultSet();
        if (!$row = $db->fetch($rs)) {
            return false;
        }
        $this->error = Labels::getLabel('ERR_RESET_PASSWORD_REQUEST_ALREADY_PLACED', $this->commonLangId);
        return true;
    }

    public function deleteOldPasswordResetRequest()
    {
        $db = FatApp::getDb();
        if (!$db->deleteRecords(static::DB_TBL_USER_PRR, ['smt' => 'uprr_expiry < ?', 'vals' => [date('Y-m-d H:i:s')]])) {
            $this->error = $db->getError();
            return false;
        }
        return true;
    }

    public function addPasswordResetRequest($data = [])
    {
        if (!isset($data['user_id']) || $data['user_id'] < 1 || strlen($data['token']) < 20) {
            return false;
        }
        $this->removePasswordResetRequest($data);
        $db = FatApp::getDb();
        $resetRow = [
            'uprr_user_id' => intval($data['user_id']),
            'uprr_token' => $data['token'],
            'uprr_expiry' => date('Y-m-d H:i:s', strtotime("+3 DAY"))
        ];
        if ($db->insertFromArray(static::DB_TBL_USER_PRR, $resetRow)) {
            $db->deleteRecords(static::DB_TBL_USER_AUTH, ['smt' => 'uauth_user_id = ?', 'vals' => [$data['user_id']]]);
            return true;
        }
        return false;
    }

    private function removePasswordResetRequest($data = [])
    {
        $db = FatApp::getDb();
        $requestRemoved = $db->deleteRecords(static::DB_TBL_USER_PRR, ['smt' => 'uprr_user_id = ?', 'vals' => [$data['user_id']]]);
        if ($requestRemoved) {
            return true;
        }
        return false;
    }

    public function checkResetLink($uId, $token)
    {
        $uId = FatUtility::convertToType($uId, FatUtility::VAR_INT);
        $token = FatUtility::convertToType($token, FatUtility::VAR_STRING);
        if (intval($uId) < 1 || strlen($token) < 20) {
            $this->error = Labels::getLabel('ERR_INVALID_RESET_PASSWORD_REQUEST', $this->commonLangId);
            return false;
        }
        $db = FatApp::getDb();
        $srch = new SearchBase(static::DB_TBL_USER_PRR);
        $srch->addCondition('uprr_user_id', '=', $uId);
        $srch->addCondition('uprr_token', '=', $token);
        $srch->addCondition('uprr_expiry', '>', date('Y-m-d H:i:s'));
        $srch->doNotCalculateRecords();
        $srch->doNotLimitRecords();
        $rs = $srch->getResultSet();
        if (!$row = $db->fetch($rs)) {
            $this->error = Labels::getLabel('ERR_LINK_IS_INVALID_OR_EXPIRED', $this->commonLangId);
            return false;
        }
        if ($row['uprr_user_id'] == $uId && $row['uprr_token'] === $token) {
            return true;
        }
        $this->error = Labels::getLabel('ERR_LINK_IS_INVALID_OR_EXPIRED', $this->commonLangId);
        return false;
    }

    public function resetUserPassword($userId, $pwd)
    {
        $userId = FatUtility::convertToType($userId, FatUtility::VAR_INT);
        if ($userId < 1) {
            $this->error = Labels::getLabel('ERR_Invalid_Request', $this->commonLangId);
            return false;
        }
        if (!empty($pwd)) {
            $user = new User($userId);
            if (!$user->resetPassword($pwd)) {
                $this->error = $user->getError();
                return false;
            }
            FatApp::getDb()->deleteRecords(static::DB_TBL_USER_PRR, ['smt' => 'uprr_user_id =?', 'vals' => [$userId]]);
            return true;
        }
        $this->error = Labels::getLabel('ERR_INVALID_PASSWORD', $this->commonLangId);
        return false;
    }

    public static function checkLogin($redirect = true, $controllerName = '', $action = '')
    {

        if (!static::isUserLogged()) {
            if (FatUtility::isAjaxCall()) {
                FatUtility::dieJsonError(Labels::getLabel('MSG_Session_seems_to_be_expired', CommonHelper::getLangId()));
            }
            if ($redirect == true) {
                $utmString = '';
                if (isset($_COOKIE["reffered_url"])) {
                    $refferedUrl = $_COOKIE["reffered_url"];
                    $urlArray = explode("?", $refferedUrl);
                    $utmString = isset($urlArray[1]) ? $urlArray[1] : '';
                }
                if (!empty($utmString)) {
                    FatApp::redirectUser(CommonHelper::generateUrl('GuestUser', 'loginForm') . '?' . $utmString);
                }
                FatApp::redirectUser(CommonHelper::generateUrl('GuestUser', 'loginForm'));
            } else {
                return false;
            }
        }

        return true;
    }

    public static function userAlert()
    {
        return $_SESSION[static::SESSION_ELEMENT_NAME]['user_alert'] ?? '';
    }
}
