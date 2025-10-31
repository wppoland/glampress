<?php

namespace wpdFormAttr\Login;

use wpdFormAttr\FormConst\wpdFormConst;
use wpdFormAttr\Login\twitter\TwitterOAuthException;
use wpdFormAttr\Login\twitter\TwitterOAuth;
use wpdFormAttr\Login\Utils;
use wpdFormAttr\Tools\Sanitizer;

class SocialLogin {

    private static $_instance = null;
    private $generalOptions;

    private function __construct($options) {
        $this->generalOptions = $options;
        add_action("wpdiscuz_init", [&$this, "requestHandler"]);
        add_action("wpdiscuz_front_scripts", [&$this, "socialScripts"]);
        add_action("comment_main_form_bar_top", [&$this, "getButtons"]);
        add_action("comment_main_form_after_head", [&$this, "getAgreement"]);
        add_action("comment_reply_form_bar_top", [&$this, "getReplyFormButtons"], 1);
        add_action("comment_reply_form_bar_top", [&$this, "getAgreement"], 2);
        add_action("wp_ajax_wpd_social_login", [&$this, "login"]);
        add_action("wp_ajax_nopriv_wpd_social_login", [&$this, "login"]);
        add_action("wp_ajax_wpd_login_callback", [&$this, "loginCallBack"]);
        add_action("wp_ajax_nopriv_wpd_login_callback", [&$this, "loginCallBack"]);
        add_filter("get_avatar", [&$this, "userAvatar"], 999, 6);
    }

    public function requestHandler() {
        if ($this->generalOptions->social["enableInstagramLogin"] && (strpos($_SERVER['REQUEST_URI'], "wpdiscuz_auth/instagram") !== false)) {
            $this->instagramLoginCallBack();
        }
        if ($this->generalOptions->social["enableLinkedinLogin"] && (strpos($_SERVER['REQUEST_URI'], "wpdiscuz_auth/linkedin") !== false)) {
            $this->linkedinLoginCallBack();
        }
    }

    public function login() {
        if (!get_option('users_can_register')) {
            return;
        }
        $postID   = Sanitizer::sanitize(INPUT_POST, "postID", FILTER_SANITIZE_NUMBER_INT);
        $provider = Sanitizer::sanitize(INPUT_POST, "provider", "FILTER_SANITIZE_STRING");
        $token    = Sanitizer::sanitize(INPUT_POST, "token", "FILTER_SANITIZE_STRING");
        $userID   = Sanitizer::sanitize(INPUT_POST, "userID", FILTER_SANITIZE_NUMBER_INT);
        $response = ["code" => "error", "message" => esc_html__("Authentication failed.", "wpdiscuz"), "url" => ""];
        if ($provider === "facebook") {
            if ($this->generalOptions->social["fbUseOAuth2"]) {
                $response = $this->facebookLoginPHP($postID, $response);
            } else {
                $response = $this->facebookLogin($token, $userID, $response);
            }
        } else if ($provider === "instagram") {
            $response = $this->instagramLogin($postID, $response);
        } else if ($provider === "google") {
            $response = $this->googleLogin($postID, $response);
        } else if ($provider === "telegram") {
            $response = $this->telegramLogin($postID, $response);
        } else if ($provider === "disqus") {
            $response = $this->disqusLogin($postID, $response);
        } else if ($provider === "wordpress") {
            $response = $this->wordpressLogin($postID, $response);
        } else if ($provider === "twitter") {
            $response = $this->twitterLogin($postID, $response);
        } else if ($provider === "vk") {
            $response = $this->vkLogin($postID, $response);
        } else if ($provider === "yandex") {
            $response = $this->yandexLogin($postID, $response);
        } else if ($provider === "linkedin") {
            $response = $this->linkedinLogin($postID, $response);
        } else if ($provider === "wechat") {
            $response = $this->wechatLogin($postID, $response);
        } else if ($provider === "qq") {
            $response = $this->qqLogin($postID, $response);
        } else if ($provider === "weibo") {
            $response = $this->weiboLogin($postID, $response);
        } else if ($provider === "baidu") {
            $response = $this->baiduLogin($postID, $response);
        }
        if (!$response["url"]) {
            $response["url"] = $this->getPostLink($postID);
        }
        wp_die(json_encode(apply_filters("wpdiscuz_social_login_response", $response, $provider, $postID, $token, $userID)));
    }

    public function loginCallBack() {
        if (!get_option('users_can_register')) {
            return;
        }
        $this->deleteCookie();
        $provider = Sanitizer::sanitize(INPUT_GET, "provider", "FILTER_SANITIZE_STRING") ? Sanitizer::sanitize(INPUT_GET, "provider", "FILTER_SANITIZE_STRING") : Sanitizer::sanitize(INPUT_POST, "provider", "FILTER_SANITIZE_STRING");
        if ($provider === "facebook") {
            $response = $this->facebookLoginPHPCallBack();
        } else if ($provider === "google") {
            $response = $this->googleLoginCallBack();
        } else if ($provider === "telegram") {
            $response = $this->telegramLoginCallBack();
        } else if ($provider === "twitter") {
            $response = $this->twitterLoginCallBack();
        } else if ($provider === "disqus") {
            $response = $this->disqusLoginCallBack();
        } else if ($provider === "wordpress") {
            $response = $this->wordpressLoginCallBack();
        } else if ($provider === "vk") {
            $response = $this->vkLoginCallBack();
        } else if ($provider === "yandex") {
            $response = $this->yandexLoginCallBack();
        } else if ($provider === "wechat") {
            $response = $this->wechatLoginCallBack();
        } else if ($provider === "qq") {
            $response = $this->qqLoginCallBack();
        } else if ($provider === "weibo") {
            $response = $this->weiboLoginCallBack();
        } else if ($provider === "baidu") {
            $response = $this->baiduLoginCallBack();
        }
    }

    private function getPostLink($postID) {
        $url = home_url();
        if ($postID) {
            $url = get_permalink($postID);
        }
        return esc_url_raw($url);
    }

    // https://developers.facebook.com/docs/apps/register
    public function facebookLogin($token, $userID, $response) {
        if (!$token || !$userID) {
            $response["message"] = esc_html__("Facebook access token or user ID invalid.", "wpdiscuz");
            return $response;
        }
        if (!$this->generalOptions->social["fbAppSecret"]) {
            $response["message"] = esc_html__("Facebook App Secret is required.", "wpdiscuz");
            return $response;
        }
        $appsecret_proof = hash_hmac("sha256", $token, trim($this->generalOptions->social["fbAppSecret"]));
        $url             = add_query_arg(["fields" => "id,first_name,last_name,picture,email", "access_token" => $token, "appsecret_proof" => $appsecret_proof], "https://graph.facebook.com/v2.8/" . $userID);
        $fb_response     = wp_remote_get(esc_url_raw($url), ["timeout" => 30]);

        if (is_wp_error($fb_response)) {
            $response["message"] = $fb_response->get_error_message();
            return $response;
        }

        $fb_user = json_decode(wp_remote_retrieve_body($fb_response), true);

        if (isset($fb_user["error"])) {
            $response["message"] = "Error code: " . $fb_user["error"]["code"] . " - " . $fb_user["error"]["message"];
            return $response;
        }
        if (empty($fb_user["email"]) && $fb_user["id"]) {
            $fb_user["email"] = $fb_user["id"] . "@facebook.com";
        }
        $this->setCurrentUser(Utils::addUser($fb_user, "facebook"));
        $uID = Utils::addUser($fb_user, "facebook");
        if (is_wp_error($uID)) {
            $response["message"] = $uID->get_error_message();
        } else {
            $response = ["code" => 200];
        }
        $this->setCurrentUser($uID);
        return $response;
    }

    public function facebookLoginPHP($postID, $response) {
        if (!$this->generalOptions->social["fbAppID"] || !$this->generalOptions->social["fbAppSecret"]) {
            $response["message"] = esc_html__("Facebook Application ID and Application Secret  required.", "wpdiscuz");
            return $response;
        }
        $fbAuthorizeURL = "https://www.facebook.com/v16.0/dialog/oauth";
        $fbCallBack     = $this->createCallBackURL("facebook");
        $state          = Utils::generateOAuthState($this->generalOptions->social["fbAppID"]);
        Utils::addOAuthState("facebook", $state, $postID);
        $oautAttributs       = [
            "client_id"     => $this->generalOptions->social["fbAppID"],
            "redirect_uri"  => urlencode($fbCallBack),
            "response_type" => "code",
            "scope"         => "email,public_profile",
            "state"         => $state];
        $oautURL             = add_query_arg($oautAttributs, $fbAuthorizeURL);
        $response["code"]    = 200;
        $response["message"] = "";
        $response["url"]     = $oautURL;
        return $response;
    }

    public function facebookLoginPHPCallBack() {
        $code         = Sanitizer::sanitize(INPUT_GET, "code", "FILTER_SANITIZE_STRING");
        $state        = Sanitizer::sanitize(INPUT_GET, "state", "FILTER_SANITIZE_STRING");
        $providerData = Utils::getProviderByState($state);
        $provider     = $providerData[wpdFormConst::WPDISCUZ_OAUTH_STATE_PROVIDER];
        $postID       = $providerData[wpdFormConst::WPDISCUZ_OAUTH_CURRENT_POSTID];
        if (!$state || ($provider !== "facebook")) {
            $this->redirect($postID, esc_html__("Facebook authentication failed (OAuth state does not exist).", "wpdiscuz"));
        }
        if (!$code) {
            $this->redirect($postID, esc_html__("Facebook authentication failed (OAuth code does not exist).", "wpdiscuz"));
        }
        $fbCallBack           = $this->createCallBackURL("facebook");
        $fbAccessTokenURL     = "https://graph.facebook.com/v16.0/oauth/access_token";
        $accessTokenArgs      = ["client_id"     => $this->generalOptions->social["fbAppID"],
                                 "client_secret" => $this->generalOptions->social["fbAppSecret"],
                                 "redirect_uri"  => urlencode($fbCallBack),
                                 "code"          => $code];
        $fbAccessTokenURL     = add_query_arg($accessTokenArgs, $fbAccessTokenURL);
        $fbAccesTokenResponse = wp_remote_get($fbAccessTokenURL);

        if (is_wp_error($fbAccesTokenResponse)) {
            $this->redirect($postID, $fbAccesTokenResponse->get_error_message());
        }
        $fbAccesTokenData = json_decode(wp_remote_retrieve_body($fbAccesTokenResponse), true);
        if (isset($fbAccesTokenData["error"])) {
            $this->redirect($postID, $fbAccesTokenData["error"]["message"]);
        }
        $token             = $fbAccesTokenData["access_token"];
        $appsecret_proof   = hash_hmac("sha256", $token, trim($this->generalOptions->social["fbAppSecret"]));
        $fbGetUserDataURL  = add_query_arg(["fields" => "id,first_name,last_name,email", "access_token" => $token, "appsecret_proof" => $appsecret_proof], "https://graph.facebook.com/v16.0/me");
        $getFbUserResponse = wp_remote_get($fbGetUserDataURL);
        if (is_wp_error($getFbUserResponse)) {
            $this->redirect($postID, $getFbUserResponse->get_error_message());
        }
        $fbUserData = json_decode(wp_remote_retrieve_body($getFbUserResponse), true);
        if (isset($fbUserData["error"])) {
            $this->redirect($postID, $fbUserData["error"]["message"]);
        }
        if (empty($fbUserData["email"]) && $fbUserData["id"]) {
            $fbUserData["email"] = $fbUserData["id"] . "@facebook.com";
        }
        $uID = Utils::addUser($fbUserData, "facebook");
        if (is_wp_error($uID)) {
            $this->redirect($postID, $uID->get_error_message());
        }
        $this->setCurrentUser($uID);
        $this->redirect($postID);
    }

    // https://developers.facebook.com/docs/instagram-basic-display-api/getting-started
    public function instagramLogin($postID, $response) {
        if (!$this->generalOptions->social["instagramAppID"] || !$this->generalOptions->social["instagramAppSecret"]) {
            $response["message"] = esc_html__("Instagram Application ID and Application Secret  required.", "wpdiscuz");
            return $response;
        }
        $instagramAuthorizeURL = "https://api.instagram.com/oauth/authorize";
        $instagramCallBack     = site_url('/wpdiscuz_auth/instagram/');
        $state                 = Utils::generateOAuthState($this->generalOptions->social["instagramAppID"]);
        Utils::addOAuthState("instagram", $state, $postID);
        $oautAttributs       = [
            "client_id"     => $this->generalOptions->social["instagramAppID"],
            "redirect_uri"  => $instagramCallBack,
            "response_type" => "code",
            "scope"         => "user_profile,user_media",
            "state"         => $state
        ];
        $oautURL             = add_query_arg($oautAttributs, $instagramAuthorizeURL);
        $response["code"]    = 200;
        $response["message"] = "";
        $response["url"]     = $oautURL;
        return $response;
    }

    public function instagramLoginCallBack() {
        $code         = Sanitizer::sanitize(INPUT_GET, "code", "FILTER_SANITIZE_STRING");
        $state        = Sanitizer::sanitize(INPUT_GET, "state", "FILTER_SANITIZE_STRING");
        $providerData = Utils::getProviderByState($state);
        $provider     = $providerData[wpdFormConst::WPDISCUZ_OAUTH_STATE_PROVIDER];
        $postID       = $providerData[wpdFormConst::WPDISCUZ_OAUTH_CURRENT_POSTID];

        if (!$state || ($provider !== "instagram")) {
            $this->redirect($postID, esc_html__("Instagram authentication failed (OAuth state does not exist).", "wpdiscuz"));
        }
        if (!$code) {
            $this->redirect($postID, esc_html__("Instagram authentication failed (OAuth code does not exist).", "wpdiscuz"));
        }
        $instagramCallBack           = site_url('/wpdiscuz_auth/instagram/');
        $instagramAccessTokenURL     = "https://api.instagram.com/oauth/access_token";
        $accessTokenArgs             = ["client_id"     => $this->generalOptions->social["instagramAppID"],
                                        "client_secret" => $this->generalOptions->social["instagramAppSecret"],
                                        "grant_type"    => "authorization_code",
                                        "redirect_uri"  => $instagramCallBack,
                                        "code"          => $code];
        $instagramAccesTokenResponse = wp_remote_post($instagramAccessTokenURL, ['body' => $accessTokenArgs]);

        if (is_wp_error($instagramAccesTokenResponse)) {
            $this->redirect($postID, $instagramAccesTokenResponse->get_error_message());
        }
        $instagramAccesTokenData = json_decode(wp_remote_retrieve_body($instagramAccesTokenResponse), true);
        if (isset($instagramAccesTokenData["error"])) {
            $this->redirect($postID, $instagramAccesTokenData["error"]["message"]);
        }
        $token                    = $instagramAccesTokenData["access_token"];
        $userID                   = $instagramAccesTokenData["user_id"];
        $appsecret_proof          = hash_hmac("sha256", $token, trim($this->generalOptions->social["instagramAppSecret"]));
        $instagramGetUserDataURL  = add_query_arg(["fields" => "id,username", "access_token" => $token, "appsecret_proof" => $appsecret_proof], "https://graph.instagram.com/$userID");
        $getInstagramUserResponse = wp_remote_get($instagramGetUserDataURL);

        if (is_wp_error($getInstagramUserResponse)) {
            $this->redirect($postID, $getInstagramUserResponse->get_error_message());
        }
        $instagramUserData = json_decode(wp_remote_retrieve_body($getInstagramUserResponse), true);
        if (isset($instagramUserData["error"])) {
            $this->redirect($postID, $instagramUserData["error"]["message"]);
        }
        if (empty($instagramUserData["email"]) && $userID) {
            $instagramUserData["email"] = $userID . "@instagram.com";
        }
        $uID = Utils::addUser($instagramUserData, "instagram");
        if (is_wp_error($uID)) {
            $this->redirect($postID, $uID->get_error_message());
        }
        $this->setCurrentUser($uID);
        $this->redirect($postID);
    }

    // https://console.developers.google.com/
    public function googleLogin($postID, $response) {
        if (!$this->generalOptions->social["googleClientID"] || !$this->generalOptions->social["googleClientSecret"]) {
            $response["message"] = esc_html__("Google Client ID and Client Secret  required.", "wpdiscuz");
            return $response;
        }

        $googleAuthorizeURL = "https://accounts.google.com/o/oauth2/v2/auth";
        $googleCallBack     = $this->createCallBackURL("google");
        $state              = Utils::generateOAuthState($this->generalOptions->social["googleClientID"]);
        Utils::addOAuthState("google", $state, $postID);
        $oautAttributs       = [
            "client_id"     => urlencode($this->generalOptions->social["googleClientID"]),
            "scope"         => "openid email profile",
            "response_type" => "code",
            "state"         => $state,
            "redirect_uri"  => urlencode($googleCallBack)
        ];
        $oautURL             = add_query_arg($oautAttributs, $googleAuthorizeURL);
        $response["code"]    = 200;
        $response["message"] = "";
        $response["url"]     = $oautURL;
        return $response;
    }

    public function googleLoginCallBack() {
        $code         = Sanitizer::sanitize(INPUT_GET, "code", "FILTER_SANITIZE_STRING");
        $state        = Sanitizer::sanitize(INPUT_GET, "state", "FILTER_SANITIZE_STRING");
        $providerData = Utils::getProviderByState($state);
        $provider     = $providerData[wpdFormConst::WPDISCUZ_OAUTH_STATE_PROVIDER];
        $postID       = $providerData[wpdFormConst::WPDISCUZ_OAUTH_CURRENT_POSTID];
        if (!$state || ($provider !== "google")) {
            $this->redirect($postID, esc_html__("Google authentication failed (OAuth state does not exist).", "wpdiscuz"));
        }
        if (!$code) {
            $this->redirect($postID, esc_html__("Google authentication failed (OAuth code does not exist).", "wpdiscuz"));
        }
        $googleCallBack           = $this->createCallBackURL("google");
        $googleAccessTokenURL     = "https://www.googleapis.com/oauth2/v4/token";
        $accessTokenArgs          = ["client_id"     => $this->generalOptions->social["googleClientID"],
                                     "client_secret" => $this->generalOptions->social["googleClientSecret"],
                                     "redirect_uri"  => $googleCallBack,
                                     "code"          => $code,
                                     "grant_type"    => 'authorization_code'];
        $googleAccesTokenResponse = wp_remote_post($googleAccessTokenURL, ['body' => $accessTokenArgs]);
        if (is_wp_error($googleAccesTokenResponse)) {
            $this->redirect($postID, $googleAccesTokenResponse->get_error_message());
        }
        $googleAccesTokenData = json_decode(wp_remote_retrieve_body($googleAccesTokenResponse), true);
        if (isset($googleAccesTokenData["error"])) {
            $this->redirect($postID, $googleAccesTokenData["error_description"]);
        }
        $idToken                = $googleAccesTokenData["id_token"];
        $getGoogleUserRataURL   = add_query_arg(["id_token" => $idToken], 'https://oauth2.googleapis.com/tokeninfo');
        $googleUserDataResponse = wp_remote_get($getGoogleUserRataURL);
        if (is_wp_error($googleUserDataResponse)) {
            $this->redirect($postID, $googleUserDataResponse->get_error_message());
        }
        $googleUserData = json_decode(wp_remote_retrieve_body($googleUserDataResponse), true);
        if (isset($googleUserData["error"])) {
            $this->redirect($postID, $googleUserData["error_description"]);
        }
        $uID = Utils::addUser($googleUserData, "google");
        if (is_wp_error($uID)) {
            $this->redirect($postID, $uID->get_error_message());
        }
        $this->setCurrentUser($uID);
        $this->redirect($postID);
    }

    public function telegramLogin($postID, $response) {
        if (!$this->generalOptions->social["telegramToken"]) {
            $response["message"] = esc_html__("Telegram token is required.", "wpdiscuz");
            return $response;
        }
        $bot_id               = explode(':', $this->generalOptions->social["telegramToken"])[0];
        $telegramAuthorizeURL = "https://oauth.telegram.org/auth";
        $oautAttributs        = [
            "bot_id"         => $bot_id,
            "origin"         => get_home_url(),
            "request_access" => "write",
            "return_to"      => urlencode(get_permalink($postID)),
        ];
        $oautURL              = add_query_arg($oautAttributs, $telegramAuthorizeURL);
        $response["code"]     = 200;
        $response["message"]  = "";
        $response["url"]      = $oautURL;
        return $response;
    }

    public function telegramLoginCallBack() {
        if (!$this->generalOptions->social["telegramToken"]) {
            wp_send_json_error(__("Telegram token is required.", "wpdiscuz"));
        }

        $provider = "telegram";
        $user     = isset($_POST["user"]["hash"]) ? $_POST["user"] : null;

        $check_hash = $user["hash"];
        unset($user["hash"]);
        $data_check_arr = [];
        foreach ($user as $key => $value) {
            $data_check_arr[] = $key . "=" . $value;
        }
        sort($data_check_arr);
        $data_check_string = implode("\n", $data_check_arr);
        $secret_key        = hash("sha256", $this->generalOptions->social["telegramToken"], true);
        $hash              = hash_hmac("sha256", $data_check_string, $secret_key);
        if (strcmp($hash, $check_hash) !== 0) {
            wp_send_json_error(__("Data is NOT from Telegram", "wpdiscuz"));
        }
        if ((time() - $user["auth_date"]) > 86400) {
            wp_send_json_error(__("Data is outdated", "wpdiscuz"));
        }

        $uID = Utils::addUser($user, $provider);
        if (is_wp_error($uID)) {
            wp_send_json_error($uID->get_error_message());
        }
        $this->setCurrentUser($uID);
        wp_send_json_success();
    }

    // https://docs.microsoft.com/en-us/linkedin/shared/authentication/authorization-code-flow?context=linkedin/context
    public function linkedinLogin($postID, $response) {
        if (!$this->generalOptions->social["linkedinClientID"] || !$this->generalOptions->social["linkedinClientSecret"]) {
            $response["message"] = esc_html__("Linkedin Client ID and Client Secret  required.", "wpdiscuz");
            return $response;
        }
        $linkedinAuthorizeURL = "https://www.linkedin.com/oauth/v2/authorization";
        $linkedinCallBack     = site_url('/wpdiscuz_auth/linkedin/');
        $state                = Utils::generateOAuthState($this->generalOptions->social["linkedinClientID"]);
        Utils::addOAuthState("linkedin", $state, $postID);
        $scope               = $this->generalOptions->social["enableLinkedinLoginOpenID"] ? "openid profile email" : "r_liteprofile r_emailaddress";
        $oautAttributs       = [
            "client_id"     => $this->generalOptions->social["linkedinClientID"],
            "redirect_uri"  => urlencode($linkedinCallBack),
            "response_type" => "code",
            "scope"         => $scope,
            "state"         => $state
        ];
        $oautURL             = add_query_arg($oautAttributs, $linkedinAuthorizeURL);
        $response["code"]    = 200;
        $response["message"] = "";
        $response["url"]     = $oautURL;
        return $response;
    }

    public function linkedinLoginCallBack() {
        $code         = Sanitizer::sanitize(INPUT_GET, "code", "FILTER_SANITIZE_STRING");
        $state        = Sanitizer::sanitize(INPUT_GET, "state", "FILTER_SANITIZE_STRING");
        $providerData = Utils::getProviderByState($state);
        $provider     = $providerData[wpdFormConst::WPDISCUZ_OAUTH_STATE_PROVIDER];
        $postID       = $providerData[wpdFormConst::WPDISCUZ_OAUTH_CURRENT_POSTID];

        if (!$state || ($provider !== "linkedin")) {
            $this->redirect($postID, esc_html__("Linkedin authentication failed (OAuth state does not exist).", "wpdiscuz"));
        }
        if (!$code) {
            $this->redirect($postID, esc_html__("Linkedin authentication failed (OAuth code does not exist).", "wpdiscuz"));
        }
        $linkedinCallBack           = site_url('/wpdiscuz_auth/linkedin/');
        $linkedinAccessTokenURL     = "https://www.linkedin.com/oauth/v2/accessToken";
        $accessTokenArgs            = ["client_id"     => $this->generalOptions->social["linkedinClientID"],
                                       "client_secret" => $this->generalOptions->social["linkedinClientSecret"],
                                       "grant_type"    => "authorization_code",
                                       "redirect_uri"  => $linkedinCallBack,
                                       "code"          => $code];
        $linkedinAccesTokenResponse = wp_remote_post($linkedinAccessTokenURL, ['body' => $accessTokenArgs]);

        if (is_wp_error($linkedinAccesTokenResponse)) {
            $this->redirect($postID, $linkedinAccesTokenResponse->get_error_message());
        }
        $linkedinAccesTokenData = json_decode(wp_remote_retrieve_body($linkedinAccesTokenResponse), true);
        if (isset($linkedinAccesTokenData["error"])) {
            $this->redirect($postID, $linkedinAccesTokenData["error_description"]);
        }
        $token = $linkedinAccesTokenData["access_token"];

        $getLinkedinRequestArgs = [
            'timeout'     => 120,
            'redirection' => 5,
            'httpversion' => '1.1',
            'headers'     => 'Authorization:Bearer ' . $token
        ];

        if ($this->generalOptions->social["enableLinkedinLoginOpenID"]) {
            $linkedinGetUserDataURL  = 'https://api.linkedin.com/v2/userinfo';
            $getLinkedinUserResponse = wp_remote_get($linkedinGetUserDataURL, $getLinkedinRequestArgs);
            if (is_wp_error($getLinkedinUserResponse)) {
                $this->redirect($postID, $getLinkedinUserResponse->get_error_message());
            }
            $linkedinUserData = json_decode(wp_remote_retrieve_body($getLinkedinUserResponse), true);
        } else {
            $linkedinGetUserEmailURL  = 'https://api.linkedin.com/v2/emailAddress?q=members&projection=(elements*(handle~))';
            $linkedinGetUserAvatarURL = 'https://api.linkedin.com/v2/me?projection=(id,profilePicture(displayImage~:playableStreams))';
            $linkedinGetUserDataURL   = 'https://api.linkedin.com/v2/me?projection=(id,firstName,lastName,emailAddress,profilePicture(displayImage~:playableStreams))';
            $email                    = '';
            $avatar                   = '';


            $getLinkedinEmailResponse  = wp_remote_get($linkedinGetUserEmailURL, $getLinkedinRequestArgs);
            $getLinkedinAvatarResponse = wp_remote_get($linkedinGetUserAvatarURL, $getLinkedinRequestArgs);

            if (!is_wp_error($getLinkedinEmailResponse)) {
                $linkedinUserEmailData = json_decode(wp_remote_retrieve_body($getLinkedinEmailResponse), true);
                if (!isset($linkedinUserEmailData["error"]) && isset($linkedinUserEmailData['elements']['0']['handle~']['emailAddress'])) {
                    $email = $linkedinUserEmailData['elements']['0']['handle~']['emailAddress'];
                }
            }

            if (!is_wp_error($getLinkedinAvatarResponse)) {
                $linkedinUserAvatarData = json_decode(wp_remote_retrieve_body($getLinkedinAvatarResponse), true);
                if (!isset($linkedinUserAvatarData["error"]) && isset($linkedinUserAvatarData['profilePicture']['displayImage~']['elements']['0']['identifiers'][0]['identifier'])) {
                    $avatar = $linkedinUserAvatarData['profilePicture']['displayImage~']['elements']['0']['identifiers'][0]['identifier'];
                }
            }


            $getLinkedinUserResponse = wp_remote_get($linkedinGetUserDataURL, $getLinkedinRequestArgs);
            if (is_wp_error($getLinkedinUserResponse)) {
                $this->redirect($postID, $getLinkedinUserResponse->get_error_message());
            }
            $linkedinUserData = json_decode(wp_remote_retrieve_body($getLinkedinUserResponse), true);

            if (isset($linkedinUserData["error"])) {
                $this->redirect($postID, $linkedinUserData["error_description"]);
            }
            if ($email) {
                $linkedinUserData["email"] = $email;
            }

            if ($avatar) {
                $linkedinUserData["avatar"] = $avatar;
            }
        }


        $uID = Utils::addUser($linkedinUserData, "linkedin");
        if (is_wp_error($uID)) {
            $this->redirect($postID, $uID->get_error_message());
        }
        $this->setCurrentUser($uID);
        $this->redirect($postID);
    }

    public function disqusLogin($postID, $response) {
        if (!$this->generalOptions->social["disqusPublicKey"] || !$this->generalOptions->social["disqusSecretKey"]) {
            $response["message"] = esc_html__("Disqus Public Key and Secret Key  required.", "wpdiscuz");
            return $response;
        }
        $disqusAuthorizeURL = "https://disqus.com/api/oauth/2.0/authorize";
        $disqusCallBack     = $this->createCallBackURL("disqus");
        $state              = Utils::generateOAuthState($this->generalOptions->social["disqusPublicKey"]);
        Utils::addOAuthState("disqus", $state, $postID);
        $oautAttributs       = [
            "client_id"     => urlencode($this->generalOptions->social["disqusPublicKey"]),
            "scope"         => "read,email",
            "response_type" => "code",
            "state"         => $state,
            "redirect_uri"  => urlencode($disqusCallBack)
        ];
        $oautURL             = add_query_arg($oautAttributs, $disqusAuthorizeURL);
        $response["code"]    = 200;
        $response["message"] = "";
        $response["url"]     = $oautURL;
        return $response;
    }

    public function disqusLoginCallBack() {
        $code         = Sanitizer::sanitize(INPUT_GET, "code", "FILTER_SANITIZE_STRING");
        $state        = Sanitizer::sanitize(INPUT_GET, "state", "FILTER_SANITIZE_STRING");
        $providerData = Utils::getProviderByState($state);
        $provider     = $providerData[wpdFormConst::WPDISCUZ_OAUTH_STATE_PROVIDER];
        $postID       = $providerData[wpdFormConst::WPDISCUZ_OAUTH_CURRENT_POSTID];
        if (!$state || ($provider !== "disqus")) {
            $this->redirect($postID, esc_html__("Disqus authentication failed (OAuth state does not exist).", "wpdiscuz"));
        }
        if (!$code) {
            $this->redirect($postID, esc_html__("Disqus authentication failed (OAuth code does not exist).", "wpdiscuz"));
        }
        $disqusCallBack           = $this->createCallBackURL("disqus");
        $disqusAccessTokenURL     = "https://disqus.com/api/oauth/2.0/access_token";
        $accessTokenArgs          = [
            "grant_type"    => "authorization_code",
            "client_id"     => $this->generalOptions->social["disqusPublicKey"],
            "client_secret" => $this->generalOptions->social["disqusSecretKey"],
            "redirect_uri"  => $disqusCallBack,
            "code"          => $code
        ];
        $disqusAccesTokenResponse = wp_remote_post($disqusAccessTokenURL, ["body" => $accessTokenArgs]);

        if (is_wp_error($disqusAccesTokenResponse)) {
            $this->redirect($postID, $disqusAccesTokenResponse->get_error_message());
        }
        $disqusAccesTokenData = json_decode(wp_remote_retrieve_body($disqusAccesTokenResponse), true);
        if (isset($disqusAccesTokenData["error"])) {
            $this->redirect($postID, $disqusAccesTokenData["error_description"]);
        }
        if (!isset($disqusAccesTokenData["access_token"])) {
            $this->redirect($postID, esc_html__("Disqus authentication failed (access_token does not exist).", "wpdiscuz"));
        }
        if (!isset($disqusAccesTokenData["user_id"])) {
            $this->redirect($postID, esc_html__("Disqus authentication failed (user_id does not exist).", "wpdiscuz"));
        }
        $userID                = $disqusAccesTokenData["user_id"];
        $accesToken            = $disqusAccesTokenData["access_token"];
        $disqusGetUserDataURL  = "https://disqus.com/api/3.0/users/details.json";
        $disqusGetUserDataAttr = [
            "access_token" => $accesToken,
            "api_key"      => $this->generalOptions->social["disqusPublicKey"],
        ];

        $getDisqusUserResponse = wp_remote_get($disqusGetUserDataURL, ["body" => $disqusGetUserDataAttr]);
        if (is_wp_error($getDisqusUserResponse)) {
            $this->redirect($postID, $getDisqusUserResponse->get_error_message());
        }
        $disqusUserData = json_decode(wp_remote_retrieve_body($getDisqusUserResponse), true);
        if (isset($disqusUserData["code"]) && $disqusUserData["code"] != 0) {
            $this->redirect($postID, $disqusUserData["response"]);
        }
        $disqusUser            = $disqusUserData["response"];
        $disqusUser["user_id"] = $userID;
        $uID                   = Utils::addUser($disqusUser, "disqus");
        if (is_wp_error($uID)) {
            $this->redirect($postID, $uID->get_error_message());
        }
        $this->setCurrentUser($uID);
        $this->redirect($postID);
    }

    //https://developer.wordpress.com/docs/oauth2/  https://developer.wordpress.com/docs/wpcc/
    public function wordpressLogin($postID, $response) {
        if (!$this->generalOptions->social["wordpressClientID"] || !$this->generalOptions->social["wordpressClientSecret"]) {
            $response["message"] = esc_html__("Wordpress Client ID and Client Secret required.", "wpdiscuz");
            return $response;
        }
        $wordpressAuthorizeURL = "https://public-api.wordpress.com/oauth2/authorize";
        $wordpressCallBack     = $this->createCallBackURL("wordpress");
        $state                 = Utils::generateOAuthState($this->generalOptions->social["wordpressClientID"]);
        Utils::addOAuthState("wordpress", $state, $postID);
        $oautAttributs       = [
            "client_id"     => $this->generalOptions->social["wordpressClientID"],
            "scope"         => "auth",
            "response_type" => "code",
            "state"         => $state,
            "redirect_uri"  => urlencode($wordpressCallBack)
        ];
        $oautURL             = add_query_arg($oautAttributs, $wordpressAuthorizeURL);
        $response["code"]    = 200;
        $response["message"] = "";
        $response["url"]     = $oautURL;
        return $response;
    }

    public function wordpressLoginCallBack() {
        $code         = Sanitizer::sanitize(INPUT_GET, "code", "FILTER_SANITIZE_STRING");
        $state        = Sanitizer::sanitize(INPUT_GET, "state", "FILTER_SANITIZE_STRING");
        $providerData = Utils::getProviderByState($state);
        $provider     = $providerData[wpdFormConst::WPDISCUZ_OAUTH_STATE_PROVIDER];
        $postID       = $providerData[wpdFormConst::WPDISCUZ_OAUTH_CURRENT_POSTID];
        if (!$state || ($provider !== "wordpress")) {
            $this->redirect($postID, esc_html__("Wordpress.com authentication failed (OAuth state does not exist).", "wpdiscuz"));
        }
        if (!$code) {
            $this->redirect($postID, esc_html__("Wordpress.com authentication failed (OAuth code does not exist).", "wpdiscuz"));
        }
        $wordpressCallBack           = $this->createCallBackURL("wordpress");
        $wordpressAccessTokenURL     = "https://public-api.wordpress.com/oauth2/token";
        $accessTokenArgs             = [
            "grant_type"    => "authorization_code",
            "client_id"     => $this->generalOptions->social["wordpressClientID"],
            "client_secret" => $this->generalOptions->social["wordpressClientSecret"],
            "redirect_uri"  => $wordpressCallBack,
            "code"          => $code
        ];
        $wordpressAccesTokenResponse = wp_remote_post($wordpressAccessTokenURL, ["body" => $accessTokenArgs]);

        if (is_wp_error($wordpressAccesTokenResponse)) {
            $this->redirect($postID, $wordpressAccesTokenResponse->get_error_message());
        }
        $wordpressAccesTokenData = json_decode(wp_remote_retrieve_body($wordpressAccesTokenResponse), true);
        if (isset($wordpressAccesTokenData["error"])) {
            $this->redirect($postID, $wordpressAccesTokenData["error_description"]);
        }
        if (!isset($wordpressAccesTokenData["access_token"])) {
            $this->redirect($postID, esc_html__("Wordpress.com authentication failed (access_token does not exist).", "wpdiscuz"));
        }
        $accesToken                     = $wordpressAccesTokenData["access_token"];
        $wordpressAccesTokenValidateURL = "https://public-api.wordpress.com/oauth2/token-info";
        $accesTokenValidateArgs         = ["client_id" => $this->generalOptions->social["wordpressClientID"], "token" => urlencode($accesToken)];
        $wordpressAccesTokenValidateURL = add_query_arg($accesTokenValidateArgs, $wordpressAccesTokenValidateURL);
        $accesTokenValidateResponse     = wp_remote_get($wordpressAccesTokenValidateURL, $accesTokenValidateArgs);
        if (is_wp_error($accesTokenValidateResponse)) {
            $this->redirect($postID, $accesTokenValidateResponse->get_error_message());
        }
        $accesTokenValidateData = json_decode(wp_remote_retrieve_body($accesTokenValidateResponse), true);
        if (!isset($accesTokenValidateData["user_id"]) || !$accesTokenValidateData["user_id"]) {
            $this->redirect($postID, esc_html__("Wordpress.com authentication failed (user_id does not exist).", "wpdiscuz"));
        }

        $wordpressGetUserDataURL  = "https://public-api.wordpress.com/rest/v1/me/";
        $wordpressGetUserDataAttr = ["Authorization" => "Bearer " . $accesToken];

        $getWordpressUserResponse = wp_remote_get($wordpressGetUserDataURL, ["headers" => $wordpressGetUserDataAttr]);

        if (is_wp_error($getWordpressUserResponse)) {
            $this->redirect($postID, $getWordpressUserResponse->get_error_message());
        }
        $wordpressUserData = json_decode(wp_remote_retrieve_body($getWordpressUserResponse), true);
        if (isset($wordpressUserData["error"])) {
            $this->redirect($postID, $wordpressUserData["message"]);
        }
        $uID = Utils::addUser($wordpressUserData, "wordpress");
        if (is_wp_error($uID)) {
            $this->redirect($postID, $uID->get_error_message());
        }
        $this->setCurrentUser($uID);
        $this->redirect($postID);
    }

    // https://apps.twitter.com/
    public function twitterLogin($postID, $response) {
        if ($this->generalOptions->social["twitterAppID"] && $this->generalOptions->social["twitterAppSecret"]) {
            $twitter         = new TwitterOAuth($this->generalOptions->social["twitterAppID"], $this->generalOptions->social["twitterAppSecret"]);
            $twitterCallBack = $this->createCallBackURL("twitter");
            try {
                $requestToken = $twitter->oauth("oauth/request_token", ["oauth_callback" => $twitterCallBack]);
                Utils::addOAuthState($requestToken["oauth_token_secret"], $requestToken["oauth_token"], $postID);
                $url                 = $twitter->url("oauth/authorize", ["oauth_token" => $requestToken["oauth_token"]]);
                $response["code"]    = 200;
                $response["message"] = "";
                $response["url"]     = $url;
            } catch (TwitterOAuthException $e) {
                $response["message"] = $e->getOAuthMessage();
            }
        } else {
            $response["message"] = esc_html__("X Consumer Key and Consumer Secret  required.", "wpdiscuz");
        }
        return $response;
    }

    public function twitterLoginCallBack() {
        $oauthToken      = Sanitizer::sanitize(INPUT_GET, "oauth_token", "FILTER_SANITIZE_STRING");
        $oauthVerifier   = Sanitizer::sanitize(INPUT_GET, "oauth_verifier", "FILTER_SANITIZE_STRING");
        $oauthSecretData = Utils::getProviderByState($oauthToken);
        $oauthSecret     = $oauthSecretData[wpdFormConst::WPDISCUZ_OAUTH_STATE_PROVIDER];
        $postID          = $oauthSecretData[wpdFormConst::WPDISCUZ_OAUTH_CURRENT_POSTID];
        if (!$oauthVerifier || !$oauthSecret) {
            $this->redirect($postID, esc_html__("X authentication failed (OAuth secret does not exist).", "wpdiscuz"));
        }
        $twitter = new TwitterOAuth($this->generalOptions->social["twitterAppID"], $this->generalOptions->social["twitterAppSecret"], $oauthToken, $oauthSecret);
        try {
            $accessToken = $twitter->oauth("oauth/access_token", ["oauth_verifier" => $oauthVerifier]);
            $connection  = new TwitterOAuth($this->generalOptions->social["twitterAppID"], $this->generalOptions->social["twitterAppSecret"], $accessToken["oauth_token"], $accessToken["oauth_token_secret"]);
            $twitterUser = $connection->get("account/verify_credentials", ["include_email" => "true"]);
            if (!empty($twitterUser->id)) {
                $uID = Utils::addUser($twitterUser, "twitter");
                if (is_wp_error($uID)) {
                    $this->redirect($postID, $uID->get_error_message());
                }
                $this->setCurrentUser($uID);
                $this->redirect($postID);
            } else {
                $this->redirect($postID, esc_html__("X connection failed.", "wpdiscuz"));
            }
        } catch (TwitterOAuthException $e) {
            $this->redirect($postID, $e->getOAuthMessage());
        }
    }

    //https://id.vk.com/about/business/go/docs/ru/vkid/latest/vk-id/connection/start-integration/auth-without-sdk/auth-without-sdk-web
    public function vkLogin($postID, $response) {
        if (!$this->generalOptions->social["vkAppID"]) {
            $response["message"] = esc_html__("VK App ID required.", "wpdiscuz");
            return $response;
        }
        $vkAuthorizeURL = "https://id.vk.ru/authorize";
        $vkCallBack     = $this->createCallBackURL("vk");
        $state          = Utils::generateOAuthState($this->generalOptions->social["vkAppID"]);
        $codeVerifier   = $this->generateCodeVerifier();
        $codeChallenge  = $this->generateCodeChallenge($codeVerifier);
        Utils::addOAuthState("vk," . $codeVerifier, $state, $postID);
        $oauthAttributes     = ["response_type"         => "code",
                                "client_id"             => $this->generalOptions->social["vkAppID"],
                                "code_challenge"        => $codeChallenge,
                                "code_challenge_method" => "S256",
                                "redirect_uri"          => urlencode($vkCallBack),
                                "scope"                 => "email",
                                "state"                 => $state];
        $oauthURL            = add_query_arg($oauthAttributes, $vkAuthorizeURL);
        $response["code"]    = 200;
        $response["message"] = "";
        $response["url"]     = $oauthURL;
        return $response;
    }


    private function generateCodeVerifier(int $length = 128): string {
        if ($length < 43 || $length > 128) {
            throw new InvalidArgumentException("code_verifier length must be between 43 and 128 characters");
        }
        $bytes     = random_bytes(32);
        $base64url = rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
        if (strlen($base64url) < $length) {
            $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-._~';
            while (strlen($base64url) < $length) {
                $base64url .= $chars[random_int(0, strlen($chars) - 1)];
            }
        }
        return substr($base64url, 0, $length);
    }


    private function generateCodeChallenge(string $code_verifier): string {
        // BASE64URL-ENCODE(SHA256(ASCII(code_verifier)))
        return rtrim(strtr(
            base64_encode(hash('sha256', $code_verifier, true)),
            '+/',
            '-_'
        ), '=');
    }

    public function vkLoginCallBack() {
        $code                 = Sanitizer::sanitize(INPUT_GET, "code", "FILTER_SANITIZE_STRING");
        $state                = Sanitizer::sanitize(INPUT_GET, "state", "FILTER_SANITIZE_STRING");
        $deviceId             = Sanitizer::sanitize(INPUT_GET, "device_id", "FILTER_SANITIZE_STRING");
        $providerData         = Utils::getProviderByState($state);
        $providerCodeVerifier = explode(',', $providerData[wpdFormConst::WPDISCUZ_OAUTH_STATE_PROVIDER]);
        $provider             = $providerCodeVerifier[0];
        $codeVerifier         = !empty($providerCodeVerifier[1]) ? $providerCodeVerifier[1] : '';
        $postID               = $providerData[wpdFormConst::WPDISCUZ_OAUTH_CURRENT_POSTID];
        if (!$state || ($provider !== "vk") || !$codeVerifier) {
            $this->redirect($postID, esc_html__("VK authentication failed (OAuth state does not exist).", "wpdiscuz"));
        }
        if (!$code) {
            $this->redirect($postID, esc_html__("VK authentication failed (OAuth code does not exist).", "wpdiscuz"));
        }
        $vkCallBack            = $this->createCallBackURL("vk");
        $vkAccessTokenURL      = "https://id.vk.ru/oauth2/auth";
        $accessTokenArgs       = ["grant_type"    => "authorization_code",
                                  "client_id"     => $this->generalOptions->social["vkAppID"],
                                  "code_verifier" => $codeVerifier,
                                  "redirect_uri"  => $vkCallBack,
                                  "code"          => $code,
                                  "device_id"     => $deviceId];
        $vkAccessTokenResponse = wp_remote_post($vkAccessTokenURL, ["body" => $accessTokenArgs]);

        if (is_wp_error($vkAccessTokenResponse)) {
            $this->redirect($postID, $vkAccessTokenResponse->get_error_message());
        }
        $vkAccessTokenData = json_decode(wp_remote_retrieve_body($vkAccessTokenResponse), true);
        if (isset($vkAccessTokenData["error"])) {
            $this->redirect($postID, $vkAccessTokenData["error_description"]);

        }
        if (!isset($vkAccessTokenData["user_id"])) {
            $this->redirect($postID, esc_html__("VK authentication failed (user_id does not exist).", "wpdiscuz"));
        }

        $userID            = $vkAccessTokenData["user_id"];
        $accessToken       = $vkAccessTokenData["access_token"];
        $vkGetUserDataURL  = "https://id.vk.ru/oauth2/user_info";
        $vkGetUserDataAttr = ["access_token" => $accessToken,
                              "client_id"    => $this->generalOptions->social["vkAppID"],];
        $getVkUserResponse = wp_remote_post($vkGetUserDataURL, ["body" => $vkGetUserDataAttr]);

        if (is_wp_error($getVkUserResponse)) {
            $this->redirect($postID, $getVkUserResponse->get_error_message());
        }
        $vkUserData = json_decode(wp_remote_retrieve_body($getVkUserResponse), true);
        if (isset($vkUserData["error"])) {
            $this->redirect($postID, $vkUserData["error_msg"]);
        }

        $vkUser          = $vkUserData["user"];
        $email           = $vkUser["email"] && $vkUser["verified"] ? $vkUser["email"] : "id" . $userID . "@vk.com";
        $vkUser["email"] = $email;
        $uID             = Utils::addUser($vkUser, "vk");
        if (is_wp_error($uID)) {
            $this->redirect($postID, $uID->get_error_message());
        }
        $this->setCurrentUser($uID);
        $this->redirect($postID);
    }

    //https://yandex.ru/dev/oauth/doc/dg/reference/auto-code-client-docpage/#auto-code-client
    public function yandexLogin($postID, $response) {
        if (!$this->generalOptions->social["yandexID"] || !$this->generalOptions->social["yandexPassword"]) {
            $response["message"] = esc_html__("Yandex ID and Password  required.", "wpdiscuz");
            return $response;
        }
        $yandexAuthorizeURL = "https://oauth.yandex.ru/authorize";
        $yandexCallBack     = $this->createCallBackURL("yandex");
        $state              = Utils::generateOAuthState($this->generalOptions->social["yandexID"]);
        Utils::addOAuthState("yandex", $state, $postID);
        $oautAttributs       = ["client_id"     => $this->generalOptions->social["yandexID"],
                                "redirect_uri"  => urlencode($yandexCallBack),
                                "response_type" => "code",
                                "state"         => $state];
        $oautURL             = add_query_arg($oautAttributs, $yandexAuthorizeURL);
        $response["code"]    = 200;
        $response["message"] = "";
        $response["url"]     = $oautURL;
        return $response;
    }

    public function yandexLoginCallBack() {
        $error        = Sanitizer::sanitize(INPUT_GET, "error", "FILTER_SANITIZE_STRING");
        $errorDesc    = Sanitizer::sanitize(INPUT_GET, "error_description", "FILTER_SANITIZE_STRING");
        $code         = Sanitizer::sanitize(INPUT_GET, "code", "FILTER_SANITIZE_STRING");
        $state        = Sanitizer::sanitize(INPUT_GET, "state", "FILTER_SANITIZE_STRING");
        $providerData = Utils::getProviderByState($state);
        $provider     = $providerData[wpdFormConst::WPDISCUZ_OAUTH_STATE_PROVIDER];
        $postID       = $providerData[wpdFormConst::WPDISCUZ_OAUTH_CURRENT_POSTID];
        if ($error) {
            $this->redirect($postID, esc_html($errorDesc));
        }
        if (!$state || ($provider !== "yandex")) {
            $this->redirect($postID, esc_html__("Yandex authentication failed (OAuth state does not exist).", "wpdiscuz"));
        }
        if (!$code) {
            $this->redirect($postID, esc_html__("Yandex authentication failed (code does not exist).", "wpdiscuz"));
        }
        $yandexCallBack           = $this->createCallBackURL("yandex");
        $yandexAccessTokenURL     = "https://oauth.yandex.ru/token";
        $accessTokenArgs          = ["client_id"     => $this->generalOptions->social["yandexID"],
                                     "client_secret" => $this->generalOptions->social["yandexPassword"],
                                     "redirect_uri"  => $yandexCallBack,
                                     "grant_type"    => "authorization_code",
                                     "code"          => $code];
        $yandexAccesTokenResponse = wp_remote_post($yandexAccessTokenURL, ["body" => $accessTokenArgs]);

        if (is_wp_error($yandexAccesTokenResponse)) {
            $this->redirect($postID, $yandexAccesTokenResponse->get_error_message());
        }
        $yandexAccesTokenData = json_decode(wp_remote_retrieve_body($yandexAccesTokenResponse), true);

        if (isset($yandexAccesTokenData["error"])) {
            $this->redirect($postID, $yandexAccesTokenData["error_description"]);
        }
        if (!isset($yandexAccesTokenData["access_token"])) {
            $this->redirect($postID, esc_html__("Yandex authentication failed (access_token does not exist).", "wpdiscuz"));
        }
        $accessToken          = $yandexAccesTokenData["access_token"];
        $yandexGetUserDataURL = "https://login.yandex.ru/info?format=json";

        $yandexGetUserDataAttr = [
            'timeout'     => 120,
            'redirection' => 5,
            'httpversion' => '1.1',
            'headers'     => 'Authorization: OAuth ' . $accessToken
        ];

        $getYandexUserResponse = wp_remote_post($yandexGetUserDataURL, $yandexGetUserDataAttr);

        if (is_wp_error($getYandexUserResponse)) {
            $this->redirect($postID, $getYandexUserResponse->get_error_message());
        }
        $yandexUserData = json_decode(wp_remote_retrieve_body($getYandexUserResponse), true);
        if (isset($yandexUserData["error"])) {
            $this->redirect($postID, $yandexUserData["error_description"]);
        }

        $uID = Utils::addUser($yandexUserData, "yandex");
        if (is_wp_error($uID)) {
            $this->redirect($postID, $uID->get_error_message());
        }
        $this->setCurrentUser($uID);
        $this->redirect($postID);
    }


    //https://developers.weixin.qq.com/doc/oplatform/en/Website_App/WeChat_Login/Wechat_Login.html
    public function wechatLogin($postID, $response) {
        if (!$this->generalOptions->social["wechatAppID"] || !$this->generalOptions->social["wechatSecret"]) {
            $response["message"] = esc_html__("WeChat AppKey and AppSecret  required.", "wpdiscuz");
            return $response;
        }

        $wechatAuthorizeURL = "https://open.weixin.qq.com/connect/qrconnect";
        $wechatCallBack     = $this->createCallBackURL("wechat");
        $state              = Utils::generateOAuthState($this->generalOptions->social["wechatAppID"]);
        Utils::addOAuthState("wechat", $state, $postID);
        $oautAttributs       = ["appid"         => $this->generalOptions->social["wechatAppID"],
                                "redirect_uri"  => urlencode($wechatCallBack),
                                "response_type" => "code",
                                "scope"         => "snsapi_login",
                                "state"         => $state];
        $oautURL             = add_query_arg($oautAttributs, $wechatAuthorizeURL);
        $response["code"]    = 200;
        $response["message"] = "";
        $response["url"]     = $oautURL . "#wechat_redirect";
        return $response;
    }

    public function wechatLoginCallBack() {
        $error        = Sanitizer::sanitize(INPUT_GET, "errcode", "FILTER_SANITIZE_STRING");
        $errorDesc    = Sanitizer::sanitize(INPUT_GET, "errmsg", "FILTER_SANITIZE_STRING");
        $code         = Sanitizer::sanitize(INPUT_GET, "code", "FILTER_SANITIZE_STRING");
        $state        = Sanitizer::sanitize(INPUT_GET, "state", "FILTER_SANITIZE_STRING");
        $providerData = Utils::getProviderByState($state);
        $provider     = $providerData[wpdFormConst::WPDISCUZ_OAUTH_STATE_PROVIDER];
        $postID       = $providerData[wpdFormConst::WPDISCUZ_OAUTH_CURRENT_POSTID];
        if ($error) {
            $this->redirect($postID, esc_html($errorDesc));
        }
        if (!$state || ($provider !== "wechat")) {
            $this->redirect($postID, esc_html__("WeChat authentication failed (OAuth state does not exist).", "wpdiscuz"));
        }
        if (!$code) {
            $this->redirect($postID, esc_html__("WeChat authentication failed (code does not exist).", "wpdiscuz"));
        }
        $wechatAccessTokenURL     = "https://api.weixin.qq.com/sns/oauth2/access_token";
        $accessTokenArgs          = ["appid"      => $this->generalOptions->social["wechatAppID"],
                                     "secret"     => $this->generalOptions->social["wechatSecret"],
                                     "grant_type" => "authorization_code",
                                     "code"       => $code];
        $wechatAccesTokenResponse = wp_remote_post($wechatAccessTokenURL, ["body" => $accessTokenArgs]);

        if (is_wp_error($wechatAccesTokenResponse)) {
            $this->redirect($postID, $wechatAccesTokenResponse->get_error_message());
        }
        $wechatAccesTokenData = json_decode(wp_remote_retrieve_body($wechatAccesTokenResponse), true);

        if (isset($wechatAccesTokenData["errcode"])) {
            $this->redirect($postID, $wechatAccesTokenData["errmsg"]);
        }
        if (!isset($wechatAccesTokenData["access_token"])) {
            $this->redirect($postID, esc_html__("WeChat authentication failed (access_token does not exist).", "wpdiscuz"));
        }
        $accessToken = $wechatAccesTokenData["access_token"];
        $uid         = $wechatAccesTokenData["openid"];

        $wechatGetUserDataAttributs = ["appid"        => $this->generalOptions->social["wechatAppID"],
                                       "access_token" => $accessToken,
                                       "openid"       => $uid
        ];

        $wechatGetUserDataURL = add_query_arg($wechatGetUserDataAttributs, "https://api.weixin.qq.com/sns/userinfo");

        $getWechatUserResponse = wp_remote_get($wechatGetUserDataURL);

        if (is_wp_error($getWechatUserResponse)) {
            $this->redirect($postID, $getWechatUserResponse->get_error_message());
        }
        $wechatUserData = json_decode(wp_remote_retrieve_body($getWechatUserResponse), true);
        if (isset($wechatUserData["errcode"])) {
            $this->redirect($postID, $wechatUserData["errmsg"]);
        }
        $uID = Utils::addUser($wechatUserData, "wechat");
        if (is_wp_error($uID)) {
            $this->redirect($postID, $uID->get_error_message());
        }
        $this->setCurrentUser($uID);
        $this->redirect($postID);
    }

    //https://wiki.connect.qq.com/%E5%BC%80%E5%8F%91%E6%94%BB%E7%95%A5_server-side
    public function qqLogin($postID, $response) {
        if (!$this->generalOptions->social["qqAppID"] || !$this->generalOptions->social["qqSecret"]) {
            $response["message"] = esc_html__("QQ AppKey and AppSecret  required.", "wpdiscuz");
            return $response;
        }

        $qqAuthorizeURL = "https://graph.qq.com/oauth2.0/authorize";
        $qqCallBack     = $this->createCallBackURL("qq");
        $state          = Utils::generateOAuthState($this->generalOptions->social["qqAppID"]);
        Utils::addOAuthState("qq", $state, $postID);
        $oautAttributs       = ["client_id"     => $this->generalOptions->social["qqAppID"],
                                "redirect_uri"  => urlencode($qqCallBack),
                                "response_type" => "code",
                                "scope"         => "get_user_info",
                                "state"         => $state];
        $oautURL             = add_query_arg($oautAttributs, $qqAuthorizeURL);
        $response["code"]    = 200;
        $response["message"] = "";
        $response["url"]     = $oautURL;
        return $response;
    }

    public function qqLoginCallBack() {
        $error        = Sanitizer::sanitize(INPUT_GET, "error", "FILTER_SANITIZE_STRING");
        $errorDesc    = Sanitizer::sanitize(INPUT_GET, "error_description", "FILTER_SANITIZE_STRING");
        $code         = Sanitizer::sanitize(INPUT_GET, "code", "FILTER_SANITIZE_STRING");
        $state        = Sanitizer::sanitize(INPUT_GET, "state", "FILTER_SANITIZE_STRING");
        $providerData = Utils::getProviderByState($state);
        $provider     = $providerData[wpdFormConst::WPDISCUZ_OAUTH_STATE_PROVIDER];
        $postID       = $providerData[wpdFormConst::WPDISCUZ_OAUTH_CURRENT_POSTID];
        if ($error) {
            $this->redirect($postID, esc_html($errorDesc));
        }
        if (!$state || ($provider !== "qq")) {
            $this->redirect($postID, esc_html__("QQ authentication failed (OAuth state does not exist).", "wpdiscuz"));
        }
        if (!$code) {
            $this->redirect($postID, esc_html__("QQ authentication failed (code does not exist).", "wpdiscuz"));
        }
        $qqCallBack           = $this->createCallBackURL("qq");
        $accessTokenArgs      = ["client_id"     => $this->generalOptions->social["qqAppID"],
                                 "client_secret" => $this->generalOptions->social["qqSecret"],
                                 "redirect_uri"  => urlencode($qqCallBack),
                                 "grant_type"    => "authorization_code",
                                 "code"          => $code];
        $qqAccessTokenURL     = add_query_arg($accessTokenArgs, "https://graph.qq.com/oauth2.0/token");
        $qqAccesTokenResponse = wp_remote_get($qqAccessTokenURL);
        if (is_wp_error($qqAccesTokenResponse)) {
            $this->redirect($postID, $qqAccesTokenResponse->get_error_message());
        }
        $qqAccesTokenResponseBody = wp_remote_retrieve_body($qqAccesTokenResponse);
        if (strpos($qqAccesTokenResponseBody, "callback") !== false) {
            $lpos                     = strpos($qqAccesTokenResponseBody, "(");
            $rpos                     = strrpos($qqAccesTokenResponseBody, ")");
            $qqAccesTokenResponseBody = substr($qqAccesTokenResponseBody, $lpos + 1, $rpos - $lpos - 1);
            $qqAccesTokenResponseMsg  = json_decode($qqAccesTokenResponseBody, true);
            if (isset($qqAccesTokenResponseMsg["error"])) {
                $this->redirect($postID, $qqAccesTokenResponseMsg["error_description"]);
            }
            $qqAccesTokenData = array();
            parse_str($qqAccesTokenResponseBody, $qqAccesTokenData);
            if (!isset($qqAccesTokenData["access_token"])) {
                $this->redirect($postID, esc_html__("QQ authentication failed (access_token does not exist).", "wpdiscuz"));
            }
            $accessToken      = $qqAccesTokenData["access_token"];
            $qqOpenIdResponse = wp_remote_get("https://graph.qq.com/oauth2.0/me?access_token=" . $accessToken);
            if (is_wp_error($qqOpenIdResponse)) {
                $this->redirect($postID, $qqOpenIdResponse->get_error_message());
            }
            $qqOpenIdResponseBody = wp_remote_retrieve_body($qqAccesTokenResponse);
            if (strpos($qqOpenIdResponseBody, "callback") !== false) {
                $lpos                 = strpos($qqOpenIdResponseBody, "(");
                $rpos                 = strrpos($qqOpenIdResponseBody, ")");
                $qqOpenIdResponseBody = substr($qqOpenIdResponseBody, $lpos + 1, $rpos - $lpos - 1);
            }
            $qqOpenIdResponseMsg = json_decode($qqOpenIdResponseBody, true);
            if (isset($qqOpenIdResponseMsg["error"])) {
                $this->redirect($postID, $qqOpenIdResponseMsg["error_description"]);
            }
            $openid                 = $qqOpenIdResponseMsg["openid"];
            $qqGetUserDataAttributs = ["oauth_consumer_key" => $this->generalOptions->social["qqAppID"],
                                       "access_token"       => $accessToken,
                                       "openid"             => $openid
            ];
            $qqGetUserDataURL       = add_query_arg($qqGetUserDataAttributs, "https://graph.qq.com/user/get_user_info");
            $getQQUserResponse      = wp_remote_get($qqGetUserDataURL);
            if (is_wp_error($getQQUserResponse)) {
                $this->redirect($postID, $getQQUserResponse->get_error_message());
            }
            $qqUserData = json_decode(wp_remote_retrieve_body($getQQUserResponse), true);
            if (isset($qqUserData["error"])) {
                $this->redirect($postID, $qqUserData["error_description"]);
            }
            $qqUserData["openid"] = $openid;
            $uID                  = Utils::addUser($qqUserData, "qq");
            if (is_wp_error($uID)) {
                $this->redirect($postID, $uID->get_error_message());
            }
            $this->setCurrentUser($uID);
            $this->redirect($postID);
        } else {
            $this->redirect($postID, esc_html__("QQ authentication failed (access_token does not exist).", "wpdiscuz"));
        }
    }

    //https://gwu-libraries.github.io/sfm-ui/posts/2016-04-26-weibo-api-guide
    //https://open.weibo.com/wiki/Connect/login
    public function weiboLogin($postID, $response) {
        if (!$this->generalOptions->social["weiboKey"] || !$this->generalOptions->social["weiboSecret"]) {
            $response["message"] = esc_html__("Weibo App Key and App Secret  required.", "wpdiscuz");
            return $response;
        }

        $weiboAuthorizeURL = "https://api.weibo.com/oauth2/authorize";
        $weiboCallBack     = $this->createCallBackURL("weibo");
        $state             = Utils::generateOAuthState($this->generalOptions->social["weiboKey"]);
        Utils::addOAuthState("weibo", $state, $postID);
        $oautAttributs       = ["client_id"     => $this->generalOptions->social["weiboKey"],
                                "redirect_uri"  => urlencode($weiboCallBack),
                                "response_type" => "code",
                                "state"         => $state];
        $oautURL             = add_query_arg($oautAttributs, $weiboAuthorizeURL);
        $response["code"]    = 200;
        $response["message"] = "";
        $response["url"]     = $oautURL;
        return $response;
    }

    public function weiboLoginCallBack() {
        $error        = Sanitizer::sanitize(INPUT_GET, "error", "FILTER_SANITIZE_STRING");
        $errorDesc    = Sanitizer::sanitize(INPUT_GET, "error_description", "FILTER_SANITIZE_STRING");
        $code         = Sanitizer::sanitize(INPUT_GET, "code", "FILTER_SANITIZE_STRING");
        $state        = Sanitizer::sanitize(INPUT_GET, "state", "FILTER_SANITIZE_STRING");
        $providerData = Utils::getProviderByState($state);
        $provider     = $providerData[wpdFormConst::WPDISCUZ_OAUTH_STATE_PROVIDER];
        $postID       = $providerData[wpdFormConst::WPDISCUZ_OAUTH_CURRENT_POSTID];
        if ($error) {
            $this->redirect($postID, esc_html($errorDesc));
        }
        if (!$state || ($provider !== "weibo")) {
            $this->redirect($postID, esc_html__("Weibo authentication failed (OAuth state does not exist).", "wpdiscuz"));
        }
        if (!$code) {
            $this->redirect($postID, esc_html__("Weibo authentication failed (code does not exist).", "wpdiscuz"));
        }
        $weiboCallBack           = $this->createCallBackURL("weibo");
        $weiboAccessTokenURL     = "https://api.weibo.com/oauth2/access_token";
        $accessTokenArgs         = ["client_id"     => $this->generalOptions->social["weiboKey"],
                                    "client_secret" => $this->generalOptions->social["weiboSecret"],
                                    "redirect_uri"  => $weiboCallBack,
                                    "grant_type"    => "authorization_code",
                                    "code"          => $code];
        $weiboAccesTokenResponse = wp_remote_post($weiboAccessTokenURL, ["body" => $accessTokenArgs]);

        if (is_wp_error($weiboAccesTokenResponse)) {
            $this->redirect($postID, $weiboAccesTokenResponse->get_error_message());
        }
        $weiboAccesTokenData = json_decode(wp_remote_retrieve_body($weiboAccesTokenResponse), true);

        if (isset($weiboAccesTokenData["error"])) {
            $this->redirect($postID, $weiboAccesTokenData["error_description"]);
        }
        if (!isset($weiboAccesTokenData["access_token"])) {
            $this->redirect($postID, esc_html__("Weibo authentication failed (access_token does not exist).", "wpdiscuz"));
        }
        $accessToken = $weiboAccesTokenData["access_token"];
        $uid         = $weiboAccesTokenData["uid"];

        $weiboGetUserDataURL = "https://api.weibo.com/2/users/show.json?uid=" . $uid;

        $weiboGetUserDataAttr = [
            'httpversion' => '1.1',
            'headers'     => 'Authorization:OAuth2 ' . $accessToken
        ];

        $getWeiboUserResponse = wp_remote_get($weiboGetUserDataURL, $weiboGetUserDataAttr);
        if (is_wp_error($getWeiboUserResponse)) {
            $this->redirect($postID, $getWeiboUserResponse->get_error_message());
        }
        $weiboUserData = json_decode(wp_remote_retrieve_body($getWeiboUserResponse), true);
        if (isset($weiboUserData["error"])) {
            $this->redirect($postID, $weiboUserData["error_description"]);
        }
        $uID = Utils::addUser($weiboUserData, "weibo");
        if (is_wp_error($uID)) {
            $this->redirect($postID, $uID->get_error_message());
        }
        $this->setCurrentUser($uID);
        $this->redirect($postID);
    }

    //https://developer.baidu.com/wiki/index.php?title=docs/oauth/application
    //https://developer.baidu.com/wiki/index.php?title=docs/oauth/showcase
    public function baiduLogin($postID, $response) {
        if (!$this->generalOptions->social["baiduAppID"] || !$this->generalOptions->social["baiduSecret"]) {
            $response["message"] = esc_html__("Baidu Client ID and Client Secret  required.", "wpdiscuz");
            return $response;
        }

        $baiduAuthorizeURL = "https://openapi.baidu.com/oauth/2.0/authorize";
        $baiduCallBack     = $this->createCallBackURL("baidu");
        $state             = Utils::generateOAuthState($this->generalOptions->social["baiduAppID"]);
        Utils::addOAuthState("baidu", $state, $postID);
        $oautAttributs       = ["client_id"     => $this->generalOptions->social["baiduAppID"],
                                "redirect_uri"  => urlencode($baiduCallBack),
                                "response_type" => "code",
                                "scope"         => "basic",
                                //'page', 'popup', 'touch' or 'mobile'
                                "display"       => wp_is_mobile() ? "mobile" : "page",
                                "state"         => $state];
        $oautURL             = add_query_arg($oautAttributs, $baiduAuthorizeURL);
        $response["code"]    = 200;
        $response["message"] = "";
        $response["url"]     = $oautURL;
        return $response;
    }

    public function baiduLoginCallBack() {
        $error        = Sanitizer::sanitize(INPUT_GET, "error", "FILTER_SANITIZE_STRING");
        $errorDesc    = Sanitizer::sanitize(INPUT_GET, "error_description", "FILTER_SANITIZE_STRING");
        $code         = Sanitizer::sanitize(INPUT_GET, "code", "FILTER_SANITIZE_STRING");
        $state        = Sanitizer::sanitize(INPUT_GET, "state", "FILTER_SANITIZE_STRING");
        $providerData = Utils::getProviderByState($state);
        $provider     = $providerData[wpdFormConst::WPDISCUZ_OAUTH_STATE_PROVIDER];
        $postID       = $providerData[wpdFormConst::WPDISCUZ_OAUTH_CURRENT_POSTID];
        if ($error) {
            $this->redirect($postID, esc_html($errorDesc));
        }
        if (!$state || ($provider !== "baidu")) {
            $this->redirect($postID, esc_html__("Baidu authentication failed (OAuth state does not exist).", "wpdiscuz"));
        }
        if (!$code) {
            $this->redirect($postID, esc_html__("Baidu authentication failed (code does not exist).", "wpdiscuz"));
        }
        $baiduCallBack           = $this->createCallBackURL("baidu");
        $baiduAccessTokenURL     = "https://openapi.baidu.com/oauth/2.0/token";
        $accessTokenArgs         = ["client_id"     => $this->generalOptions->social["baiduAppID"],
                                    "client_secret" => $this->generalOptions->social["baiduSecret"],
                                    "redirect_uri"  => $baiduCallBack,
                                    "grant_type"    => "authorization_code",
                                    "code"          => $code];
        $baiduAccesTokenResponse = wp_remote_post($baiduAccessTokenURL, ["body" => $accessTokenArgs]);

        if (is_wp_error($baiduAccesTokenResponse)) {
            $this->redirect($postID, $baiduAccesTokenResponse->get_error_message());
        }
        $baiduAccesTokenData = json_decode(wp_remote_retrieve_body($baiduAccesTokenResponse), true);

        if (isset($baiduAccesTokenData["error"])) {
            $this->redirect($postID, $baiduAccesTokenData["error_description"]);
        }
        if (!isset($baiduAccesTokenData["access_token"])) {
            $this->redirect($postID, esc_html__("Baidu authentication failed (access_token does not exist).", "wpdiscuz"));
        }
        $accessToken = $baiduAccesTokenData["access_token"];

        $getBaiduUserResponse = wp_remote_get("https://openapi.baidu.com/rest/2.0/passport/users/getLoggedInUser?access_token=" . $accessToken);
        if (is_wp_error($getBaiduUserResponse)) {
            $this->redirect($postID, $getBaiduUserResponse->get_error_message());
        }
        $baiduUserData = json_decode(wp_remote_retrieve_body($getBaiduUserResponse), true);
        if (isset($baiduUserData["error_code"])) {
            $this->redirect($postID, $baiduUserData["error_msg"]);
        }
        $uID = Utils::addUser($baiduUserData, "baidu");
        if (is_wp_error($uID)) {
            $this->redirect($postID, $uID->get_error_message());
        }
        $this->setCurrentUser($uID);
        $this->redirect($postID);
    }

    private function redirect($postID, $message = "") {
        if ($message) {
            setcookie('wpdiscuz_social_login_message', $message, time() + 3600, '/');
        }
        do_action("wpdiscuz_clean_post_cache", $postID, "social_login");
        wp_redirect($this->getPostLink($postID), 302);
        exit();
    }

    private function createCallBackURL($provider) {
        $adminAjaxURL = admin_url("admin-ajax.php");
        $urlAttributs = ["action" => "wpd_login_callback", "provider" => $provider];
        return add_query_arg($urlAttributs, $adminAjaxURL);
    }

    private function deleteCookie() {
        unset($_COOKIE["wpdiscuz_social_login_message"]);
        setcookie("wpdiscuz_social_login_message", "", time() - (15 * 60));
    }

    private function setCurrentUser($userID) {
        $user = get_user_by("id", $userID);
        wp_set_current_user($userID, $user->user_login);
        wp_set_auth_cookie($userID, (bool)$this->generalOptions->social["rememberLoggedinUser"]);
        do_action("wp_login", $user->user_login, $user);
    }

    public function getButtons() {
        global $post;
        if (!is_user_logged_in() && wpDiscuz()->helper->isLoadWpdiscuz($post) && $this->generalOptions->isShowLoginButtons()) {
            echo "<div class='wpd-social-login'>";
            echo "<span class='wpd-connect-with'>" . esc_html($this->generalOptions->getPhrase("wc_connect_with")) . "</span>";
            $this->facebookButton();
            $this->instagramButton();
            $this->twitterButton();
            $this->googleButton();
            $this->telegramButton();
            $this->disqusButton();
            $this->wordpressButton();
            $this->linkedinButton();
            $this->yandexButton();
            $this->vkButton();
            $this->wechatButton();
            $this->weiboButton();
            $this->qqButton();
            $this->baiduButton();
            echo "<div class='wpdiscuz-social-login-spinner'><i class='fas fa-spinner fa-pulse'></i></div><div class='wpd-clear'></div>";
            echo "</div>";
        }
    }

    public function getReplyFormButtons() {
        if ($this->generalOptions->social["socialLoginInSecondaryForm"]) {
            $this->getButtons();
        }
    }

    public function getAgreement() {
        global $post;
        if (!is_user_logged_in() && wpDiscuz()->helper->isLoadWpdiscuz($post) && $this->generalOptions->isShowLoginButtons() && $this->generalOptions->social["socialLoginAgreementCheckbox"]) {
            ?>
            <div class="wpd-social-login-agreement" style="display: none;">
                <div class="wpd-agreement-title"><?php echo $this->generalOptions->getPhrase("wc_social_login_agreement_label"); ?></div>
                <div class="wpd-agreement"><?php echo $this->generalOptions->getPhrase("wc_social_login_agreement_desc"); ?></div>
                <div class="wpd-agreement-buttons">
                    <div class="wpd-agreement-buttons-right"><span
                            class="wpd-agreement-button wpd-agreement-button-disagree"><?php echo $this->generalOptions->getPhrase("wc_agreement_button_disagree"); ?></span><span
                            class="wpd-agreement-button wpd-agreement-button-agree"><?php echo $this->generalOptions->getPhrase("wc_agreement_button_agree"); ?></span>
                    </div>
                    <div class="wpd-clear"></div>
                </div>
            </div>
            <?php
        }
    }

    private function facebookButton() {
        if ($this->generalOptions->social["enableFbLogin"] && $this->generalOptions->social["fbAppID"] && $this->generalOptions->social["fbAppSecret"]) {
            echo "<span class='wpdsn wpdsn-fb wpdiscuz-login-button' wpd-tooltip='Facebook'><i><svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 320 512'><path d='M80 299.3V512H196V299.3h86.5l18-97.8H196V166.9c0-51.7 20.3-71.5 72.7-71.5c16.3 0 29.4 .4 37 1.2V7.9C291.4 4 256.4 0 236.2 0C129.3 0 80 50.5 80 159.4v42.1H14v97.8H80z'/></svg></i></span>";
        }
    }

    private function instagramButton() {
        if ($this->generalOptions->social["enableInstagramLogin"] && $this->generalOptions->social["instagramAppID"] && $this->generalOptions->social["instagramAppSecret"]) {
            echo "<span class='wpdsn wpdsn-insta wpdiscuz-login-button' wpd-tooltip='Instagram'><i><svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 448 512'><path d='M224.1 141c-63.6 0-114.9 51.3-114.9 114.9s51.3 114.9 114.9 114.9S339 319.5 339 255.9 287.7 141 224.1 141zm0 189.6c-41.1 0-74.7-33.5-74.7-74.7s33.5-74.7 74.7-74.7 74.7 33.5 74.7 74.7-33.6 74.7-74.7 74.7zm146.4-194.3c0 14.9-12 26.8-26.8 26.8-14.9 0-26.8-12-26.8-26.8s12-26.8 26.8-26.8 26.8 12 26.8 26.8zm76.1 27.2c-1.7-35.9-9.9-67.7-36.2-93.9-26.2-26.2-58-34.4-93.9-36.2-37-2.1-147.9-2.1-184.9 0-35.8 1.7-67.6 9.9-93.9 36.1s-34.4 58-36.2 93.9c-2.1 37-2.1 147.9 0 184.9 1.7 35.9 9.9 67.7 36.2 93.9s58 34.4 93.9 36.2c37 2.1 147.9 2.1 184.9 0 35.9-1.7 67.7-9.9 93.9-36.2 26.2-26.2 34.4-58 36.2-93.9 2.1-37 2.1-147.8 0-184.8zM398.8 388c-7.8 19.6-22.9 34.7-42.6 42.6-29.5 11.7-99.5 9-132.1 9s-102.7 2.6-132.1-9c-19.6-7.8-34.7-22.9-42.6-42.6-11.7-29.5-9-99.5-9-132.1s-2.6-102.7 9-132.1c7.8-19.6 22.9-34.7 42.6-42.6 29.5-11.7 99.5-9 132.1-9s102.7-2.6 132.1 9c19.6 7.8 34.7 22.9 42.6 42.6 11.7 29.5 9 99.5 9 132.1s2.7 102.7-9 132.1z'/></svg></i></span>";
        }
    }

    private function linkedinButton() {
        if ($this->generalOptions->social["enableLinkedinLogin"] && $this->generalOptions->social["linkedinClientID"] && $this->generalOptions->social["linkedinClientSecret"]) {
            echo "<span class='wpdsn wpdsn-linked wpdiscuz-login-button' wpd-tooltip='Linkedin'><i><svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 448 512'><path d='M100.3 448H7.4V148.9h92.9zM53.8 108.1C24.1 108.1 0 83.5 0 53.8a53.8 53.8 0 0 1 107.6 0c0 29.7-24.1 54.3-53.8 54.3zM447.9 448h-92.7V302.4c0-34.7-.7-79.2-48.3-79.2-48.3 0-55.7 37.7-55.7 76.7V448h-92.8V148.9h89.1v40.8h1.3c12.4-23.5 42.7-48.3 87.9-48.3 94 0 111.3 61.9 111.3 142.3V448z'/></svg></i></span>";
        }
    }

    private function twitterButton() {
        if ($this->generalOptions->social["enableTwitterLogin"] && $this->generalOptions->social["twitterAppID"] && $this->generalOptions->social["twitterAppSecret"]) {
            echo "<span class='wpdsn wpdsn-tw wpdiscuz-login-button' wpd-tooltip='X'><i><svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 512 512'><path d='M389.2 48h70.6L305.6 224.2 487 464H345L233.7 318.6 106.5 464H35.8L200.7 275.5 26.8 48H172.4L272.9 180.9 389.2 48zM364.4 421.8h39.1L151.1 88h-42L364.4 421.8z'/></svg></i></span>";
        }
    }

    private function googleButton() {
        if ($this->generalOptions->social["enableGoogleLogin"] && $this->generalOptions->social["googleClientID"] && $this->generalOptions->social["googleClientSecret"]) {
            echo "<span class='wpdsn wpdsn-gg wpdiscuz-login-button' wpd-tooltip='Google'><i><svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 488 512'><path d='M488 261.8C488 403.3 391.1 504 248 504 110.8 504 0 393.2 0 256S110.8 8 248 8c66.8 0 123 24.5 166.3 64.9l-67.5 64.9C258.5 52.6 94.3 116.6 94.3 256c0 86.5 69.1 156.6 153.7 156.6 98.2 0 135-70.4 140.8-106.9H248v-85.3h236.1c2.3 12.7 3.9 24.9 3.9 41.4z'/></svg></i></span>";
        }
    }

    private function disqusButton() {
        if ($this->generalOptions->social["enableDisqusLogin"] && $this->generalOptions->social["disqusPublicKey"] && $this->generalOptions->social["disqusSecretKey"]) {
            echo "<span class='wpdsn wpdsn-ds wpdiscuz-login-button' wpd-tooltip='Disqus'><i><svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 384 512'><path d='M0 96C0 60.7 28.7 32 64 32l96 0c123.7 0 224 100.3 224 224s-100.3 224-224 224l-96 0c-35.3 0-64-28.7-64-64L0 96zm160 0L64 96l0 320 96 0c88.4 0 160-71.6 160-160s-71.6-160-160-160z'/></svg></i></span>";
        }
    }

    private function wordpressButton() {
        if ($this->generalOptions->social["enableWordpressLogin"] && $this->generalOptions->social["wordpressClientID"] && $this->generalOptions->social["wordpressClientSecret"]) {
            echo "<span class='wpdsn wpdsn-wp wpdiscuz-login-button' wpd-tooltip='WordPress'><i><svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 512 512'><path d='M256 8C119.3 8 8 119.2 8 256c0 136.7 111.3 248 248 248s248-111.3 248-248C504 119.2 392.7 8 256 8zM33 256c0-32.3 6.9-63 19.3-90.7l106.4 291.4C84.3 420.5 33 344.2 33 256zm223 223c-21.9 0-43-3.2-63-9.1l66.9-194.4 68.5 187.8c.5 1.1 1 2.1 1.6 3.1-23.1 8.1-48 12.6-74 12.6zm30.7-327.5c13.4-.7 25.5-2.1 25.5-2.1 12-1.4 10.6-19.1-1.4-18.4 0 0-36.1 2.8-59.4 2.8-21.9 0-58.7-2.8-58.7-2.8-12-.7-13.4 17.7-1.4 18.4 0 0 11.4 1.4 23.4 2.1l34.7 95.2L200.6 393l-81.2-241.5c13.4-.7 25.5-2.1 25.5-2.1 12-1.4 10.6-19.1-1.4-18.4 0 0-36.1 2.8-59.4 2.8-4.2 0-9.1-.1-14.4-.3C109.6 73 178.1 33 256 33c58 0 110.9 22.2 150.6 58.5-1-.1-1.9-.2-2.9-.2-21.9 0-37.4 19.1-37.4 39.6 0 18.4 10.6 33.9 21.9 52.3 8.5 14.8 18.4 33.9 18.4 61.5 0 19.1-7.3 41.2-17 72.1l-22.2 74.3-80.7-239.6zm81.4 297.2l68.1-196.9c12.7-31.8 17-57.2 17-79.9 0-8.2-.5-15.8-1.5-22.9 17.4 31.8 27.3 68.2 27.3 107 0 82.3-44.6 154.1-110.9 192.7z'/></svg></i></span>";
        }
    }

    private function telegramButton() {
        if ($this->generalOptions->social["enableTelegramLogin"] && $this->generalOptions->social["telegramToken"]) {
            echo "<span class='wpdsn wpdsn-telegram wpdiscuz-login-button' wpd-tooltip='Telegram'><i><svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 496 512'><path d='M248 8C111 8 0 119 0 256S111 504 248 504 496 393 496 256 385 8 248 8zM363 176.7c-3.7 39.2-19.9 134.4-28.1 178.3-3.5 18.6-10.3 24.8-16.9 25.4-14.4 1.3-25.3-9.5-39.3-18.7-21.8-14.3-34.2-23.2-55.3-37.2-24.5-16.1-8.6-25 5.3-39.5 3.7-3.8 67.1-61.5 68.3-66.7 .2-.7 .3-3.1-1.2-4.4s-3.6-.8-5.1-.5q-3.3 .7-104.6 69.1-14.8 10.2-26.9 9.9c-8.9-.2-25.9-5-38.6-9.1-15.5-5-27.9-7.7-26.8-16.3q.8-6.7 18.5-13.7 108.4-47.2 144.6-62.3c68.9-28.6 83.2-33.6 92.5-33.8 2.1 0 6.6 .5 9.6 2.9a10.5 10.5 0 0 1 3.5 6.7A43.8 43.8 0 0 1 363 176.7z'/></svg></i></span>";
        }
    }

    private function vkButton() {
        if ($this->generalOptions->social["enableVkLogin"] && $this->generalOptions->social["vkAppID"]) {
            echo "<span class='wpdsn wpdsn-vk wpdiscuz-login-button' wpd-tooltip='VKontakte'><i><svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 448 512'><path d='M31.5 63.5C0 95 0 145.7 0 247V265C0 366.3 0 417 31.5 448.5C63 480 113.7 480 215 480H233C334.3 480 385 480 416.5 448.5C448 417 448 366.3 448 265V247C448 145.7 448 95 416.5 63.5C385 32 334.3 32 233 32H215C113.7 32 63 32 31.5 63.5zM75.6 168.3H126.7C128.4 253.8 166.1 290 196 297.4V168.3H244.2V242C273.7 238.8 304.6 205.2 315.1 168.3H363.3C359.3 187.4 351.5 205.6 340.2 221.6C328.9 237.6 314.5 251.1 297.7 261.2C316.4 270.5 332.9 283.6 346.1 299.8C359.4 315.9 369 334.6 374.5 354.7H321.4C316.6 337.3 306.6 321.6 292.9 309.8C279.1 297.9 262.2 290.4 244.2 288.1V354.7H238.4C136.3 354.7 78 284.7 75.6 168.3z'/></svg></i></span>";
        }
    }

    private function yandexButton() {
        if ($this->generalOptions->social["enableYandexLogin"] && $this->generalOptions->social["yandexID"] && $this->generalOptions->social["yandexPassword"]) {
            echo "<span class='wpdsn wpdsn-yandex wpdiscuz-login-button' wpd-tooltip='Yandex'><i><svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 320 512'><path d='M129.5 512V345.9L18.5 48h55.8l81.8 229.7L250.2 0h51.3L180.8 347.8V512h-51.3z'/></svg></i></span>";
        }
    }

    private function wechatButton() {
        if ($this->generalOptions->social["enableWechatLogin"] && $this->generalOptions->social["wechatAppID"] && $this->generalOptions->social["wechatSecret"]) {
            echo "<span class='wpdsn wpdsn-weixin wpdiscuz-login-button' wpd-tooltip='WeChat'><i><svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 576 512'><path d='M385.2 167.6c6.4 0 12.6 .3 18.8 1.1C387.4 90.3 303.3 32 207.7 32 100.5 32 13 104.8 13 197.4c0 53.4 29.3 97.5 77.9 131.6l-19.3 58.6 68-34.1c24.4 4.8 43.8 9.7 68.2 9.7 6.2 0 12.1-.3 18.3-.8-4-12.9-6.2-26.6-6.2-40.8-.1-84.9 72.9-154 165.3-154zm-104.5-52.9c14.5 0 24.2 9.7 24.2 24.4 0 14.5-9.7 24.2-24.2 24.2-14.8 0-29.3-9.7-29.3-24.2 .1-14.7 14.6-24.4 29.3-24.4zm-136.4 48.6c-14.5 0-29.3-9.7-29.3-24.2 0-14.8 14.8-24.4 29.3-24.4 14.8 0 24.4 9.7 24.4 24.4 0 14.6-9.6 24.2-24.4 24.2zM563 319.4c0-77.9-77.9-141.3-165.4-141.3-92.7 0-165.4 63.4-165.4 141.3S305 460.7 397.6 460.7c19.3 0 38.9-5.1 58.6-9.9l53.4 29.3-14.8-48.6C534 402.1 563 363.2 563 319.4zm-219.1-24.5c-9.7 0-19.3-9.7-19.3-19.6 0-9.7 9.7-19.3 19.3-19.3 14.8 0 24.4 9.7 24.4 19.3 0 10-9.7 19.6-24.4 19.6zm107.1 0c-9.7 0-19.3-9.7-19.3-19.6 0-9.7 9.7-19.3 19.3-19.3 14.5 0 24.4 9.7 24.4 19.3 .1 10-9.9 19.6-24.4 19.6z'/></svg></i></span>";
        }
    }

    private function baiduButton() {
        if ($this->generalOptions->social["enableBaiduLogin"] && $this->generalOptions->social["baiduAppID"] && $this->generalOptions->social["baiduSecret"]) {
            echo "<span class='wpdsn wpdsn-baidu wpdiscuz-login-button' wpd-tooltip='Baidu'><i><svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 512 512'><path d='M226.5 92.9c14.3 42.9-.3 86.2-32.6 96.8s-70.1-15.6-84.4-58.5s.3-86.2 32.6-96.8s70.1 15.6 84.4 58.5zM100.4 198.6c18.9 32.4 14.3 70.1-10.2 84.1s-59.7-.9-78.5-33.3S-2.7 179.3 21.8 165.3s59.7 .9 78.5 33.3zM69.2 401.2C121.6 259.9 214.7 224 256 224s134.4 35.9 186.8 177.2c3.6 9.7 5.2 20.1 5.2 30.5l0 1.6c0 25.8-20.9 46.7-46.7 46.7c-11.5 0-22.9-1.4-34-4.2l-88-22c-15.3-3.8-31.3-3.8-46.6 0l-88 22c-11.1 2.8-22.5 4.2-34 4.2C84.9 480 64 459.1 64 433.3l0-1.6c0-10.4 1.6-20.8 5.2-30.5zM421.8 282.7c-24.5-14-29.1-51.7-10.2-84.1s54-47.3 78.5-33.3s29.1 51.7 10.2 84.1s-54 47.3-78.5 33.3zM310.1 189.7c-32.3-10.6-46.9-53.9-32.6-96.8s52.1-69.1 84.4-58.5s46.9 53.9 32.6 96.8s-52.1 69.1-84.4 58.5z'/></svg></i></span>";
        }
    }

    private function qqButton() {
        if ($this->generalOptions->social["enableQQLogin"] && $this->generalOptions->social["qqAppID"] && $this->generalOptions->social["qqSecret"]) {
            echo "<span class='wpdsn wpdsn-qq wpdiscuz-login-button' wpd-tooltip='Tencent QQ'><i><svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 448 512'><path d='M433.8 420.4c-11.5 1.4-44.9-52.7-44.9-52.7 0 31.3-16.1 72.2-51.1 101.8 16.8 5.2 54.8 19.2 45.8 34.4-7.3 12.3-125.5 7.9-159.6 4-34.1 3.8-152.3 8.3-159.6-4-9-15.3 28.9-29.2 45.8-34.4-34.9-29.5-51.1-70.4-51.1-101.8 0 0-33.3 54.1-44.9 52.7-5.4-.7-12.4-29.6 9.3-99.7 10.3-33 22-60.5 40.1-105.8C60.7 98.1 109 0 224 0c113.7 0 163.2 96.1 160.3 215 18.1 45.2 29.9 72.9 40.1 105.8 21.8 70.1 14.7 99.1 9.3 99.7z'/></svg></i></span>";
        }
    }

    private function weiboButton() {
        if ($this->generalOptions->social["enableWeiboLogin"] && $this->generalOptions->social["weiboKey"] && $this->generalOptions->social["weiboSecret"]) {
            echo "<span class='wpdsn wpdsn-weibo wpdiscuz-login-button' wpd-tooltip='Sina Weibo'><i><svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 512 512'><path d='M407 177.6c7.6-24-13.4-46.8-37.4-41.7-22 4.8-28.8-28.1-7.1-32.8 50.1-10.9 92.3 37.1 76.5 84.8-6.8 21.2-38.8 10.8-32-10.3zM214.8 446.7C108.5 446.7 0 395.3 0 310.4c0-44.3 28-95.4 76.3-143.7C176 67 279.5 65.8 249.9 161c-4 13.1 12.3 5.7 12.3 6 79.5-33.6 140.5-16.8 114 51.4-3.7 9.4 1.1 10.9 8.3 13.1 135.7 42.3 34.8 215.2-169.7 215.2zm143.7-146.3c-5.4-55.7-78.5-94-163.4-85.7-84.8 8.6-148.8 60.3-143.4 116s78.5 94 163.4 85.7c84.8-8.6 148.8-60.3 143.4-116zM347.9 35.1c-25.9 5.6-16.8 43.7 8.3 38.3 72.3-15.2 134.8 52.8 111.7 124-7.4 24.2 29.1 37 37.4 12 31.9-99.8-55.1-195.9-157.4-174.3zm-78.5 311c-17.1 38.8-66.8 60-109.1 46.3-40.8-13.1-58-53.4-40.3-89.7 17.7-35.4 63.1-55.4 103.4-45.1 42 10.8 63.1 50.2 46 88.5zm-86.3-30c-12.9-5.4-30 .3-38 12.9-8.3 12.9-4.3 28 8.6 34 13.1 6 30.8 .3 39.1-12.9 8-13.1 3.7-28.3-9.7-34zm32.6-13.4c-5.1-1.7-11.4 .6-14.3 5.4-2.9 5.1-1.4 10.6 3.7 12.9 5.1 2 11.7-.3 14.6-5.4 2.8-5.2 1.1-10.9-4-12.9z'/></svg></i></span>";
        }
    }

    public function userAvatar($avatar, $id_or_email, $size, $default, $alt, $args = []) {
        if (strpos($avatar, "gravatar.com") === false || !$this->generalOptions->social["displaySocialAvatar"]) {
            return $avatar;
        }
        $userID = false;
        if (isset($args["wpdiscuz_current_user"])) {
            if ($args["wpdiscuz_current_user"]) {
                $userID = $args["wpdiscuz_current_user"]->ID;
            }
        } else {
            if (is_numeric($id_or_email)) {
                $userID = (int)$id_or_email;
            } elseif (is_object($id_or_email)) {
                if (!empty($id_or_email->user_id)) {
                    $userID = (int)$id_or_email->user_id;
                }
            } else {
                $user   = get_user_by("email", $id_or_email);
                $userID = isset($user->ID) ? $user->ID : 0;
            }
        }

        if ($userID && $avatarURL = get_user_meta($userID, wpdFormConst::WPDISCUZ_SOCIAL_AVATAR_KEY, true)) {
//            $avatarURL = apply_filters("get_avatar_url", $avatarURL, $id_or_email, $args);
            $class = ["avatar", "avatar-" . (int)$args["size"], "photo"];
            if (is_array($args["class"])) {
                $class = array_merge($class, $args["class"]);
            } else {
                $class[] = $args["class"];
            }
            $avatar = "<img alt='" . esc_attr($alt) . "' src='" . esc_attr($avatarURL) . "' class='" . esc_attr(join(" ", $class)) . " wpdiscuz-social-avatar' height='" . intval($size) . "' width='" . intval($size) . "' " . $args["extra_attr"] . "/>";
        }
        return $avatar;
    }

    public function socialScripts() {
        if (!$this->generalOptions->general["loadComboVersion"] && ($this->generalOptions->social["enableFbShare"] || (!is_user_logged_in() && $this->generalOptions->isShowLoginButtons()))) {
            $suf = $this->generalOptions->general["loadMinVersion"] ? ".min" : "";
            wp_register_script("wpdiscuz-social-js", plugins_url(WPDISCUZ_DIR_NAME . "/assets/js/wpdiscuz-social$suf.js"), ["wpdiscuz-ajax-js"], get_option("wc_plugin_version", "1.0.0"), true);
            wp_enqueue_script("wpdiscuz-social-js");
        }
    }

    public static function getInstance($options) {
        if (is_null(self::$_instance)) {
            self::$_instance = new self($options);
        }
        return self::$_instance;
    }

}
