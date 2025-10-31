<?php
if (!defined("ABSPATH")) {
    exit();
}

class WpdiscuzHelper implements WpDiscuzConstants {

    private static $spoilerPattern = '@\[(\[?)(spoiler)(?![\w-])([^\]\/]*(?:\/(?!\])[^\]\/]*)*?)(?:(\/)\]|\](?:([^\[]*+(?:\[(?!\/\2\])[^\[]*+)*+)\[\/\2\])?)(\]?)@isu';
    private static $inlineFormPattern = '@\[(\[?)(wpdiscuz\-feedback)(?![\w-])([^\]\/]*(?:\/(?!\])[^\]\/]*)*?)(?:(\/)\]|\](?:([^\[]*+(?:\[(?!\/\2\])[^\[]*+)*+)\[\/\2\])?)(\]?)@isu';
    private static $inlineFormAttsPattern = '@([\w-]+)\s*=\s*\'([^\']*)\'(?:\s|$)|([\w-]+)\s*=\s*\'([^\']*)\'(?:\s|$)|([\w-]+)\s*=\s*([^\s\']+)(?:\s|$)|\'([^\']*)\'(?:\s|$)|\'([^\']*)\'(?:\s|$)|(\S+)(?:\s|$)@isu';
    private $options;

    /**
     * @var $dbManager WpdiscuzDBManager
     */
    private $dbManager;
    private $wpdiscuzForm;
    private static $current_time;
    private $avatars;

    public function __construct($options, $dbManager, $wpdiscuzForm) {
        $this->options      = $options;
        $this->dbManager    = $dbManager;
        $this->wpdiscuzForm = $wpdiscuzForm;
        self::$current_time = current_time("timestamp");
        add_filter("the_champ_login_interface_filter", [&$this, "wpDiscuzSuperSocializerLogin"], 15, 2);
        add_filter("pre_comment_user_ip", [&$this, "fixLocalhostIp"], 10);
        add_filter("get_avatar_url", [$this, "preGetDefaultAvatarUrl"], 99, 3);

        if ($this->options->subscription["enableUserMentioning"]) {
            add_filter("comment_text", [&$this, "userMentioning"], 10, 3);
        }
        if ($this->options->content["enableShortcodes"]) {
            add_filter("comment_text", [&$this, "doShortcode"], 10, 3);
        }
        add_filter("comment_text", [&$this, "multipleBlockquotesToOne"], 100);
        add_filter("wp_update_comment_data", [&$this, "commentDataArr"], 10, 3);
        add_action("post_updated", [&$this, "checkFeedbackShortcodes"], 10, 3);
        add_action("update_postmeta", [&$this, "checkMetaFeedbackShortcodes"], 10, 4);
        add_action("added_post_meta", [&$this, "checkMetaFeedbackShortcodes"], 10, 4);
        add_filter("comment_row_actions", [&$this, "commentRowStickAction"], 10, 2);
        add_filter("admin_comment_types_dropdown", [&$this, "addCommentTypes"]);
        add_filter("wpdiscuz_after_comment_author", [&$this, "userNicename"], 1, 3);

        add_action("wp_ajax_wpdGetInfo", [&$this, "wpdGetInfo"]);
        add_action("wp_ajax_nopriv_wpdGetInfo", [&$this, "wpdGetInfo"]);
        if ($this->options->login["showActivityTab"]) {
            add_action("wp_ajax_wpdGetActivityPage", [&$this, "getActivityPage"]);
            add_action("wp_ajax_nopriv_wpdGetActivityPage", [&$this, "getActivityPage"]);
        }
        if ($this->options->login["showSubscriptionsTab"]) {
            add_action("wp_ajax_wpdGetSubscriptionsPage", [&$this, "getSubscriptionsPage"]);
            add_action("wp_ajax_nopriv_wpdGetSubscriptionsPage", [&$this, "getSubscriptionsPage"]);
        }
        if ($this->options->login["showFollowsTab"]) {
            add_action("wp_ajax_wpdGetFollowsPage", [&$this, "getFollowsPage"]);
            add_action("wp_ajax_nopriv_wpdGetFollowsPage", [&$this, "getFollowsPage"]);
        }
        add_action("admin_post_disableAddonsDemo", [&$this, "disableAddonsDemo"]);
        $requestUri = !empty($_SERVER["REQUEST_URI"]) ? $_SERVER["REQUEST_URI"] : "";
        if (!get_option(self::OPTION_SLUG_DEACTIVATION) && (strpos($requestUri, "/plugins.php") !== false)) {
            add_action("admin_footer", [&$this, "wpdDeactivationReasonModal"]);
        }
        add_filter("wpdiscuz_comment_author", [$this, "umAuthorName"], 10, 2);
        add_action("add_meta_boxes", [&$this, "addRatingResetButton"], 10, 2);

        add_filter("nonce_life", [&$this, "setNonceLife"], 15, 2);
        add_action("wpdiscuz_init", [&$this, "setNonceInCookies"]);

        add_action("save_post", [$this, "updatePostAuthorsTrs"]);

    }

    public function filterKses() {
        $allowedtags               = [];
        $allowedtags["br"]         = [];
        $allowedtags["a"]          = [
            "href"     => true,
            "title"    => true,
            "target"   => true,
            "rel"      => true,
            "download" => true,
            "hreflang" => true,
            "media"    => true,
            "type"     => true
        ];
        $allowedtags["i"]          = ["class" => true];
        $allowedtags["b"]          = [];
        $allowedtags["u"]          = [];
        $allowedtags["strong"]     = [];
        $allowedtags["s"]          = [];
        $allowedtags["p"]          = [];
        $allowedtags["blockquote"] = ["cite" => true];
        $allowedtags["ul"]         = [];
        $allowedtags["li"]         = [];
        $allowedtags["ol"]         = [];
        $allowedtags["code"]       = [];
        $allowedtags["em"]         = [];
        $allowedtags["abbr"]       = ["title" => true];
        $allowedtags["q"]          = ["cite" => true];
        $allowedtags["acronym"]    = ["title" => true];
        $allowedtags["cite"]       = [];
        $allowedtags["strike"]     = [];
        $allowedtags["del"]        = ["datetime" => true];
        $allowedtags["span"]       = [
            "id"              => true,
            "class"           => true,
            "title"           => true,
            "contenteditable" => true,
            "data-name"       => true
        ];
        $allowedtags["pre"]        = ["class" => true, "spellcheck" => true];

        return apply_filters("wpdiscuz_allowedtags", $allowedtags);
    }

    public function filterCommentText($commentContent) {
        if (!current_user_can("unfiltered_html")) {
            kses_remove_filters();
            if ($this->options->form["richEditor"] === "none" && $this->options->form["enableQuickTags"] === 0) {
                $allowedTags = [];
            } else {
                $allowedTags = $this->filterKses();
            }
            $commentContent = wp_kses($commentContent, $allowedTags);
        }

        return $commentContent;
    }

    public function dateDiff($datetime) {
        $text = "";
        if ($datetime) {
            $search  = ["[number]", "[time_unit]", "[adjective]"];
            $replace = [];
            $now     = new DateTime(gmdate('Y-m-d H:i:s'));
            $ago     = new DateTime($datetime);
            $diff    = $now->diff($ago);
            if ($diff->y) {
                $replace[] = $diff->y;
                $replace[] = $diff->y > 1 ? esc_html($this->options->getPhrase("wc_year_text_plural")) : esc_html($this->options->getPhrase("wc_year_text"));
            } else if ($diff->m) {
                $replace[] = $diff->m;
                $replace[] = $diff->m > 1 ? esc_html($this->options->getPhrase("wc_month_text_plural")) : esc_html($this->options->getPhrase("wc_month_text"));
            } else if ($diff->d) {
                $replace[] = $diff->d;
                $replace[] = $diff->d > 1 ? esc_html($this->options->getPhrase("wc_day_text_plural")) : esc_html($this->options->getPhrase("wc_day_text"));
            } else if ($diff->h) {
                $replace[] = $diff->h;
                $replace[] = $diff->h > 1 ? esc_html($this->options->getPhrase("wc_hour_text_plural")) : esc_html($this->options->getPhrase("wc_hour_text"));
            } else if ($diff->i) {
                $replace[] = $diff->i;
                $replace[] = $diff->i > 1 ? esc_html($this->options->getPhrase("wc_minute_text_plural")) : esc_html($this->options->getPhrase("wc_minute_text"));
            } else if ($diff->s) {
                $replace[] = $diff->s;
                $replace[] = $diff->s > 1 ? esc_html($this->options->getPhrase("wc_second_text_plural")) : esc_html($this->options->getPhrase("wc_second_text"));
            }
            if ($replace) {
                $replace[] = esc_html($this->options->getPhrase("wc_ago_text"));
                $text      = str_replace($search, $replace, $this->options->general["dateDiffFormat"]);
            } else {
                $text = esc_html($this->options->getPhrase("wc_right_now_text"));
            }
        }

        return $text;
    }

    //================== Nonce==================================================
    public function setNonceLife($lifetime, $action = -1) {
        if (isset($action) && $action === $this->generateNonceKey()) {
            return DAY_IN_SECONDS / 2;
        }

        return $lifetime;
    }

    public function generateNonceKey() {
        return ($key = get_home_url()) ? md5($key) : self::GLOBAL_NONCE_NAME;
    }

    public function generateNonce() {
        return wp_create_nonce($this->generateNonceKey());
    }

    public function validateNonce() {
        if (is_user_logged_in() || apply_filters('wpdiscuz_validate_nonce_for_guests', false)) {
            $nonce         = !empty($_COOKIE[self::GLOBAL_NONCE_NAME . '_' . COOKIEHASH]) ? sanitize_text_field($_COOKIE[self::GLOBAL_NONCE_NAME . '_' . COOKIEHASH]) : "";
            $timeDependent = wp_verify_nonce($nonce, $this->generateNonceKey());
            if (!$timeDependent) {
                wp_die(__("Nonce is invalid.", "wpdiscuz"));
            }

//            unset($_COOKIE[self::GLOBAL_NONCE_NAME . '_' . COOKIEHASH]);
            $this->setNonceInCookies($timeDependent, false);
        }
    }

    public function setNonceInCookies($timeDependent = 2, $checkNonce = true) {
        if (headers_sent()) {
            return;
        }

        $validateNonceForGuests = apply_filters('wpdiscuz_validate_nonce_for_guests', false);

        if (!$validateNonceForGuests && !is_user_logged_in()) {
            return;
        }

        if ($checkNonce) {
            $nonce         = !empty($_COOKIE[self::GLOBAL_NONCE_NAME . '_' . COOKIEHASH]) ? sanitize_text_field($_COOKIE[self::GLOBAL_NONCE_NAME . '_' . COOKIEHASH]) : "";
            $timeDependent = wp_verify_nonce($nonce, $this->generateNonceKey());
        }

        if ($timeDependent && $timeDependent < 2) {
            return;
        }

        $expires = time() + HOUR_IN_SECONDS * 10;
        $nonce   = $this->generateNonce();
        if (version_compare(phpversion(), "7.3", ">=")) {
            setcookie(self::GLOBAL_NONCE_NAME . '_' . COOKIEHASH, $nonce, [
                'expires'  => $expires,
                'path'     => '/',
                'domain'   => '',
                'secure'   => false,
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
        } else {
            setcookie(self::GLOBAL_NONCE_NAME . '_' . COOKIEHASH, $nonce, $expires, '/', "", false, true);
        }
    }

    //==========================================================================

    public function getNumber($number) {
        if ($this->options->general["humanReadableNumbers"]) {
            if (absint($number) >= 1000000) {
                $number = sprintf(esc_html__("%sM", "wpdiscuz"), str_replace(".0", "", number_format($number / 1000000, 1)));
            } else if (absint($number) >= 1000) {
                $number = sprintf(esc_html__("%sK", "wpdiscuz"), str_replace(".0", "", number_format($number / 1000, 1)));
            }
        }

        return $number;
    }

    public function makeClickable($ret) {
        $ret  = " " . $ret;
        $hook = "?";
        if (is_ssl() && $this->options->general["commentLinkFilter"] == 1) {
            $hook = "";
        }
        $ret = preg_replace_callback("#[^\"|\'](https" . $hook . ":\/\/[^\s]+(\.jpe?g|\.png|\.gif|\.bmp))#i", [
            &$this,
            "replaceUrlToImg"
        ], $ret);
        // this one is not in an array because we need it to run last, for cleanup of accidental links within links
        $ret = preg_replace("#(<a( [^>]+?>|>))<a [^>]+?>([^>]+?)</a></a>#i", "$1$3</a>", $ret);
        $ret = trim($ret);

        return $ret;
    }

    public function replaceUrlToImg($matches) {
        $url = $matches[1];
        if (is_ssl() && $this->options->general["commentLinkFilter"] == 2 && strpos($matches[1], "https://") === false) {
            $url = str_replace("http://", "https://", $url);
        }
        $rel = "noreferrer ugc";
        if (strpos($url, get_site_url()) !== 0) {
            $rel .= " nofollow";
        }

        return apply_filters("wpdiscuz_source_to_image_conversion", "<a rel='$rel' target='_blank' href='" . esc_url_raw($url) . "'><img alt='comment image' src='" . esc_url_raw($url) . "' /></a>", $url);
    }

    /**
     * check if comment has been posted today or not
     *
     * @param type $comment WP_Comment object or Datetime value
     *
     * @return type
     */
    public static function isPostedToday($comment) {
        if (is_object($comment)) {
            return date("Ymd", strtotime(current_time("Ymd"))) <= date("Ymd", strtotime($comment->comment_date));
        } else {
            return date("Ymd", strtotime(current_time("Ymd"))) <= date("Ymd", strtotime($comment));
        }
    }

    public static function getMicrotime() {
        list($pfx_usec, $pfx_sec) = explode(" ", microtime());

        return ((float)$pfx_usec + (float)$pfx_sec);
    }

    /**
     * check if comment is still editable or not
     * return boolean
     */
    public function isCommentEditable($comment) {
        if (!$comment) {
            return false;
        }
        $commentTimestamp  = strtotime($comment->comment_date);
        $timeDiff          = self::$current_time - $commentTimestamp;
        $editableTimeLimit = $this->options->moderation["commentEditableTime"] === "unlimit" ? abs($timeDiff) + 100 : intval($this->options->moderation["commentEditableTime"]);

        return apply_filters("wpdiscuz_is_comment_editable", $editableTimeLimit && ($timeDiff < $editableTimeLimit), $comment);
    }

    /**
     * checks if the current comment content is in min/max range defined in options
     */
    public function isContentInRange($commentContent, $isReply) {
        if ($isReply) {
            $commentMinLength = intval($this->options->content["replyTextMinLength"]);
            $commentMaxLength = intval($this->options->content["replyTextMaxLength"]);
        } else {
            $commentMinLength = intval($this->options->content["commentTextMinLength"]);
            $commentMaxLength = intval($this->options->content["commentTextMaxLength"]);
        }
        $commentContent = trim(strip_tags($commentContent));
        $contentLength  = function_exists("mb_strlen") ? mb_strlen($commentContent) : strlen($commentContent);

        return ($contentLength >= $commentMinLength) && ($commentMaxLength == 0 || $contentLength <= $commentMaxLength);
    }

    /**
     * return client real ip
     */
    public static function getRealIPAddr() {
        $ip = $_SERVER["REMOTE_ADDR"];

        $ip = apply_filters("pre_comment_user_ip", $ip);

        if ($ip === "::1") {
            $ip = "127.0.0.1";
        }

        return $ip;
    }

    public function getUIDData($uid) {
        $id_strings = explode("_", $uid);

        return $id_strings;
    }

    public function superSocializerFix() {
        $output = "";
        if (function_exists("the_champ_login_button")) {
            $output .= "<div id='comments' style='width: 0;height: 0;clear: both;margin: 0;padding: 0;'></div>";
            $output .= "<div id='respond' class='comments-area'>";
        } else {
            $output .= "<div id='comments' class='comments-area'>";
            $output .= "<div id='respond' style='width: 0;height: 0;clear: both;margin: 0;padding: 0;'></div>";
        }
        echo $output;
    }

    public static function getCommentExcerpt($commentContent, $uniqueId, $options) {
        $readMoreLink = "<span id='wpdiscuz-readmore-" . esc_attr($uniqueId) . "'><span class='wpdiscuz-hellip'>&hellip;&nbsp;</span><span class='wpdiscuz-readmore' title='" . esc_attr($options->getPhrase("wc_read_more")) . "'>" . esc_html($options->getPhrase("wc_read_more")) . "</span></span>";

        return "<p>" . wp_trim_words($commentContent, $options->content["commentReadMoreLimit"], $readMoreLink) . "</p>";
    }

    public static function strWordCount($content) {
        $words = preg_split("/[\n\r\t ]+/", $content, -1, PREG_SPLIT_NO_EMPTY);
        $words = array_filter($words, function ($w) {
            return $w !== "&nbsp;";
        });

        return count($words);
    }

    public function isLoadWpdiscuz($post) {
        if (!$post || !is_object($post) || (is_front_page() && !$this->options->general["isEnableOnHome"])) {
            return false;
        }
        $form = $this->wpdiscuzForm->getForm($post->ID);

        return apply_filters("is_load_wpdiscuz", $form->getFormID() && (comments_open($post) || $post->comment_count) && is_singular() && post_type_supports($post->post_type, "comments"), $post);
    }

    public function replaceCommentContentCode($content) {
        if (is_ssl()) {
            $content = preg_replace_callback("#<\s*?img[^>]*src*=*[\"\']?([^\"\']*)[^>]+>#is", [
                &$this,
                "replaceImageToURL"
            ], $content);
        }

        return preg_replace_callback("#`(.*?)`#is", [&$this, "replaceCodeContent"], stripslashes($content));
    }

    private function replaceImageToURL($matches) {
        if (strpos($matches[1], "https://") === false && $this->options->general["commentLinkFilter"] == 1) {
            return "\r\n" . $matches[1] . "\r\n";
        } elseif (strpos($matches[1], "https://") === false && $this->options->general["commentLinkFilter"] == 2) {
            return str_replace("http://", "https://", $matches[0]);
        } else {
            return $matches[0];
        }
    }

    private function replaceCodeContent($matches) {
        $codeContent = trim($matches[1]);
        $codeContent = str_replace(["<", ">"], ["&lt;", "&gt;"], $codeContent);

        return "<code>" . $codeContent . "</code>";
    }

    public function spoiler($content) {
        return preg_replace_callback(self::$spoilerPattern, [$this, "_spoiler"], $content);
    }

    private function _spoiler($matches) {
        $html       = "<div class='wpdiscuz-spoiler-wrap'>";
        $title      = esc_html($this->options->getPhrase("wc_spoiler"));
        $matches[3] = str_replace(["&#8221;", "&#8220;", "&#8243;", "&#8242;"], "\"", $matches[3]);
        if (preg_match("@title[^\S]*=[^\S]*\"*([^\"]+)\"@is", $matches[3], $titleMatch)) {
            $title = trim($titleMatch[1]) ? trim($titleMatch[1]) : esc_html($this->options->getPhrase("wc_spoiler"));
        }

        $html .= "<div class='wpdiscuz-spoiler wpdiscuz-spoiler-closed'><i class='fas fa-plus' aria-hidden='true'></i>" . $title . "</div>";
        $html .= "<div class='wpdiscuz-spoiler-content'>" . $matches[5] . "</div>";
        $html .= "</div>";

        return $html;
    }

    public function getCurrentUserDisplayName($current_user) {
        $displayName = trim($current_user->display_name);
        if (!$displayName) {
            $user_nicename = trim($current_user->user_nicename);
            $displayName   = $user_nicename ? $user_nicename : trim($current_user->user_login);
        }

        return $displayName;
    }

    public function enqueueWpDiscuzStyle($slug, $fileName, $version, $form) {
        $themes           = $form->getThemes();
        $theme            = $form->getTheme();
        $wpdiscuzStyleURL = "";
        if (file_exists(get_stylesheet_directory() . "/wpdiscuz/$fileName.css")) {
            $wpdiscuzStyleURL = get_stylesheet_directory_uri() . "/wpdiscuz/$fileName.css";
        } elseif (file_exists(get_template_directory() . "/wpdiscuz/$fileName.css")) {
            $wpdiscuzStyleURL = get_template_directory_uri() . "/wpdiscuz/$fileName.css";
        } else if (file_exists($theme . "/$fileName.css")) {
            $wpdiscuzStyleURL = $themes[$theme]["url"] . "/$fileName.css";
        }
        if ($wpdiscuzStyleURL) {
            wp_register_style($slug, $wpdiscuzStyleURL, null, $version);
            wp_enqueue_style($slug);
        }
    }

    public function wpDiscuzSuperSocializerLogin($html, $theChampLoginOptions) {
        global $wp_current_filter;
        if (in_array("comment_form_top", $wp_current_filter) && isset($theChampLoginOptions["providers"]) && is_array($theChampLoginOptions["providers"]) && count($theChampLoginOptions["providers"]) > 0) {
            $html = "<style type='text/css'>#wpcomm .wc_social_plugin_wrapper .wp-social-login-connect-with_by_the_champ{float:left;font-size:13px;padding:5px 7px 0 0;text-transform:uppercase}#wpcomm .wc_social_plugin_wrapper ul.wc_social_login_by_the_champ{list-style:none outside none!important;margin:0!important;padding-left:0!important}#wpcomm .wc_social_plugin_wrapper ul.wc_social_login_by_the_champ .theChampLogin{width:24px!important;height:24px!important}#wpcomm .wpd-secondary-forms-social-content ul.wc_social_login_by_the_champ{list-style:none outside none!important;margin:0!important;padding-left:0!important}#wpcomm .wpd-secondary-forms-social-content ul.wc_social_login_by_the_champ .theChampLogin{width:24px!important;height:24px!important}#wpcomm .wpd-secondary-forms-social-content ul.wc_social_login_by_the_champ li{float:right!important}#wpcomm .wc_social_plugin_wrapper .theChampFacebookButton{ display:block!important; }#wpcomm .theChampTwitterButton{background-position:-4px -68px!important}#wpcomm .theChampGoogleButton{background-position:-36px -2px!important}#wpcomm .theChampVkontakteButton{background-position:-35px -67px!important}#wpcomm .theChampLinkedinButton{background-position:-34px -34px!important;}.theChampCommentingTabs #wpcomm li{ margin:0px 1px 10px 0px!important; }</style>";
            $html .= "<div class='wp-social-login-widget'>";
            $html .= "<div class='wp-social-login-connect-with_by_the_champ'>" . esc_html($this->options->getPhrase("wc_connect_with")) . ":</div>";
            $html .= "<div class='wp-social-login-provider-list'>";
            if (isset($theChampLoginOptions["gdpr_enable"])) {
                $html .= "<div class='heateor_ss_sl_optin_container'><label><input type='checkbox' class='heateor_ss_social_login_optin' value='1' />" . str_replace($theChampLoginOptions["ppu_placeholder"], "<a href='" . esc_url_raw($theChampLoginOptions["privacy_policy_url"]) . "' target='_blank'>" . $theChampLoginOptions["ppu_placeholder"] . "</a>", wp_strip_all_tags($theChampLoginOptions["privacy_policy_optin_text"])) . "</label></div>";
            }
            $html .= "<ul class='wc_social_login_by_the_champ'>";
            foreach ($theChampLoginOptions["providers"] as $k => $provider) {
                $html .= "<li><i ";
                if ($provider === "google") {
                    $html .= "id='theChamp" . esc_attr(ucfirst($provider)) . "Button' ";
                }
                $html .= "class='theChampLogin theChamp" . esc_attr(ucfirst($provider)) . "Background theChamp" . esc_attr(ucfirst($provider)) . "Login' ";
                $html .= "alt='Login with ";
                $html .= ucfirst($provider);
                $html .= "' title='Login with ";
                if ($provider === "live") {
                    $html .= "Windows Live";
                } else {
                    $html .= ucfirst($provider);
                }
                $html .= "' onclick='theChampCommentFormLogin = true; theChampInitiateLogin(this)' >";
                $html .= "<ss style='display:block' class='theChampLoginSvg theChamp" . esc_attr(ucfirst($provider)) . "LoginSvg'></ss></i></li>";
            }
            $html .= "</ul><div class='wpdiscuz_clear'></div></div></div>";
        }

        return $html;
    }

    public static function getCurrentUser() {
        if ($user_ID = get_current_user_id()) {
            $user = get_userdata($user_ID);
        } else {
            $user = wp_set_current_user(0);
        }

        return $user;
    }

    public function userNicename($html, $comment, $user) {
        if (apply_filters("wpdiscuz_show_nicename", false) && $this->options->subscription["enableUserMentioning"] && isset($user->user_nicename)) {
            $html .= "<span class='wpd-user-nicename' data-wpd-ismention='1' data-wpd-clipboard='" . esc_attr($user->user_nicename) . "'>(@" . esc_html($user->user_nicename) . ")</span>";
        }

        return $html;
    }

    public function canUserEditComment($comment, $currentUser, $commentListArgs = []) {
        if (!($comment instanceof WP_Comment)) {
            return false;
        }
        if (isset($commentListArgs["comment_author_email"])) {
            $storedCookieEmail = $commentListArgs["comment_author_email"];
        } else {
            $storedCookieEmail = isset($_COOKIE["comment_author_email_" . COOKIEHASH]) ? sanitize_email($_COOKIE["comment_author_email_" . COOKIEHASH]) : "";
        }

        return !(!$this->options->moderation["enableEditingWhenHaveReplies"] && $comment->get_children(["post_id" => $comment->comment_post_ID])) && (($storedCookieEmail === $comment->comment_author_email && $_SERVER["REMOTE_ADDR"] === $comment->comment_author_IP) || ($currentUser && $currentUser->ID && $currentUser->ID == $comment->user_id));
    }

    public function addCommentTypes($args) {
        $args[self::WPDISCUZ_STICKY_COMMENT] = esc_html__("Sticky", "wpdiscuz");

        return $args;
    }

    public function commentRowStickAction($actions, $comment) {
        if (!$comment->comment_parent) {
            $stickText = $comment->comment_type === self::WPDISCUZ_STICKY_COMMENT ? $this->options->getPhrase("wc_unstick_comment", ["comment" => $comment]) : $this->options->getPhrase("wc_stick_comment", ["comment" => $comment]);
            if (intval(get_comment_meta($comment->comment_ID, self::META_KEY_CLOSED, true))) {
                $closeText = $this->options->getPhrase("wc_open_comment", ["comment" => $comment]);
                $closeIcon = "fa-lock";
            } else {
                $closeText = $this->options->getPhrase("wc_close_comment", ["comment" => $comment]);
                $closeIcon = "fa-unlock";
            }
            $actions["stick"] = "<a data-comment='" . $comment->comment_ID . "' data-post='" . $comment->comment_post_ID . "' class='wpd_stick_btn' href='#'> <i class='fas fa-thumbtack'></i> <span class='wpd_stick_text'>" . esc_html($stickText) . "</span></a>";
            $actions["close"] = "<a data-comment='" . $comment->comment_ID . "' data-post='" . $comment->comment_post_ID . "' class='wpd_close_btn' href='#'> <i class='fas " . $closeIcon . "'></i> <span class='wpd_close_text'>" . esc_html($closeText) . "</span></a>";
        }

        return $actions;
    }

    public function wpdDeactivationReasonModal() {
        include_once WPDISCUZ_DIR_PATH . "/utils/deactivation-reason-modal.php";
    }

    public function disableAddonsDemo() {
        if (current_user_can("manage_options") && isset($_GET["_wpnonce"]) && wp_verify_nonce($_GET["_wpnonce"], "disableAddonsDemo") && isset($_GET["show"])) {
            update_option(self::OPTION_SLUG_SHOW_DEMO, intval($_GET["show"]));
            wp_redirect(admin_url("admin.php?page=" . WpdiscuzCore::PAGE_SETTINGS));
        }
    }

    public function getCommentDate($comment) {
        if ($this->options->general["simpleCommentDate"]) {
            $dateFormat = $this->options->wp["dateFormat"];
            $timeFormat = $this->options->wp["timeFormat"];
            if (self::isPostedToday($comment)) {
                $postedDate = $this->options->getPhrase("wc_posted_today_text") . " " . mysql2date($timeFormat, $comment->comment_date);
            } else {
                $postedDate = get_comment_date($dateFormat . " " . $timeFormat, $comment->comment_ID);
            }
        } else {
            $postedDate = $this->dateDiff($comment->comment_date_gmt);
        }

        return $postedDate;
    }

    public function getPostDate($post) {
        if ($this->options->general["simpleCommentDate"]) {
            $dateFormat = $this->options->wp["dateFormat"];
            $timeFormat = $this->options->wp["timeFormat"];
            if ($this->isPostPostedToday($post)) {
                $postedDate = $this->options->getPhrase("wc_posted_today_text") . " " . mysql2date($timeFormat, $post->post_date);
            } else {
                $postedDate = get_the_date($dateFormat . " " . $timeFormat, $post);
            }
        } else {
            $postedDate = $this->dateDiff($post->post_date_gmt);
        }

        return $postedDate;
    }

    public function getDate($comment) {
        if ($this->options->general["simpleCommentDate"]) {
            $dateFormat = $this->options->wp["dateFormat"];
            $timeFormat = $this->options->wp["timeFormat"];
            if (self::isPostedToday($comment)) {
                $postedDate = $this->options->getPhrase("wc_posted_today_text") . " " . mysql2date($timeFormat, $comment);
            } else {
                $postedDate = date($dateFormat . " " . $timeFormat, strtotime($comment));
            }
        } else {
            $postedDate = $this->dateDiff($comment);
        }

        return $postedDate;
    }

    private function isPostPostedToday($post) {
        return date("Ymd", strtotime(current_time("Ymd"))) <= date("Ymd", strtotime($post->post_date));
    }

    public function wpdGetInfo() {
        $this->validateNonce();
        $response    = "";
        $currentUser = self::getCurrentUser();
        if ($currentUser && $currentUser->ID) {
            $currentUserId    = $currentUser->ID;
            $currentUserEmail = $currentUser->user_email;
        } else {
            $currentUserId    = 0;
            $currentUserEmail = isset($_COOKIE["comment_author_email_" . COOKIEHASH]) ? sanitize_email($_COOKIE["comment_author_email_" . COOKIEHASH]) : "";
        }

        if (is_user_logged_in()) {
            $response .= "<div class='wpd-wrapper'>";
            $response .= "<ul class='wpd-list'>";
            if ($this->options->login["showActivityTab"]) {
                $response .= $this->getActivityTitleHtml();
            }
            if ($this->options->login["showSubscriptionsTab"]) {
                $response .= $this->getSubscriptionsTitleHtml();
            }
            if ($this->options->login["showFollowsTab"]) {
                $response .= $this->getFollowsTitleHtml();
            }
            $isFirstTab = true;
            $response   .= apply_filters("wpdiscuz_content_modal_title", "", $currentUser);
            $response   .= "</ul>";
            $response   .= "<div class='wpd-content'>";
            if ($this->options->login["showActivityTab"]) {
                $response   .= $this->getActivityContentHtml($currentUserId, $currentUserEmail, $isFirstTab);
                $isFirstTab = false;
            }
            if ($this->options->login["showSubscriptionsTab"]) {
                $response   .= $this->getSubscriptionsContentHtml($currentUserId, $currentUserEmail, $isFirstTab);
                $isFirstTab = false;
            }
            if ($this->options->login["showFollowsTab"]) {
                $response   .= $this->getFollowsContentHtml($currentUserId, $currentUserEmail, $isFirstTab);
                $isFirstTab = false;
            }
            $response .= apply_filters("wpdiscuz_content_modal_content", "", $currentUser, $isFirstTab);
            $response .= "</div>";
            $response .= "<div class='wpd-user-email-delete-links-wrap'>";
            $response .= "<a href='#' class='wpd-user-email-delete-links wpd-not-clicked'>";
            $response .= esc_html($this->options->getPhrase("wc_user_settings_email_me_delete_links"));
            $response .= "<span class='wpd-loading wpd-hide'><i class='fas fa-pulse fa-spinner'></i></span>";
            $response .= "</a>";
            $response .= "<div class='wpd-bulk-desc'>" . esc_html($this->options->getPhrase("wc_user_settings_email_me_delete_links_desc")) . "</div>";
            $response .= "</div>";
            $response .= "</div>";
        } else if ($currentUserEmail) {
            $commentBtn     = $this->getDeleteAllCommentsButton($currentUserEmail);
            $subscribeBtn   = $this->getDeleteAllSubscriptionsButton($currentUserEmail);
            $cookieBtnClass = !$commentBtn && !$subscribeBtn ? "wpd-show" : "wpd-hide";
            $response       .= "<div class='wpd-wrapper wpd-guest-settings'>";
            $response       .= $commentBtn;
            $response       .= $subscribeBtn;
            $response       .= $this->deleteCookiesButton($currentUserEmail, $cookieBtnClass);
            $response       .= "</div>";
        } else {
            $response .= "<div class='wpd-wrapper'>";
            $response .= esc_html($this->options->getPhrase("wc_user_settings_no_data"));
            $response .= "</div>";
        }
        wp_die($response);
    }

    private function getDeleteAllCommentsButton($email) {
        $html = "";
        if (!is_email($email)) {
            return $html;
        }
        $commentCount = get_comments(["author_email" => $email, "count" => true]);
        if ($commentCount) {
            $html .= "<div class='wpd-user-settings-button-wrap'>";
            $html .= "<div class='wpd-user-settings-button wpd-delete-all-comments wpd-not-clicked' data-wpd-delete-action='deletecomments'>";
            $html .= esc_html($this->options->getPhrase("wc_user_settings_request_deleting_comments"));
            $html .= "<span class='wpd-loading wpd-hide'><i class='fas fa-spinner fa-pulse'></i></span>";
            $html .= "</div>";
            $html .= "</div>";
        }

        return $html;
    }

    private function getDeleteAllSubscriptionsButton($email) {
        $html = "";
        if (!is_email($email)) {
            return $html;
        }
        $subscriptions = $this->dbManager->getSubscriptions($email, 1, 0);
        if ($subscriptions) {
            $html .= "<div class='wpd-user-settings-button-wrap'>";
            $html .= "<div class='wpd-user-settings-button wpd-delete-all-subscriptions wpd-not-clicked' data-wpd-delete-action='deleteSubscriptions'>";
            $html .= esc_html($this->options->getPhrase("wc_user_settings_cancel_subscriptions"));
            $html .= "<span class='wpd-loading wpd-hide'><i class='fas fa-spinner fa-pulse'></i></span>";
            $html .= "</div>";
            $html .= "</div>";
        }

        return $html;
    }

    private function deleteCookiesButton($email, $cookieBtnClass) {
        $html = "";
        if (!is_email($email)) {
            return $html;
        }
        $html .= "<div class='wpd-user-settings-button-wrap " . $cookieBtnClass . "'>";
        $html .= "<div class='wpd-user-settings-button wpd-delete-all-cookies wpd-not-clicked' data-wpd-delete-action='deleteCookies'>";
        $html .= esc_html($this->options->getPhrase("wc_user_settings_clear_cookie"));
        $html .= "<span class='wpd-loading wpd-hide'><i class='fas fa-spinner fa-pulse'></i></span>";
        $html .= "</div>";
        $html .= "</div>";

        return $html;
    }

    private function getActivityTitleHtml() {
        ob_start();
        include_once WPDISCUZ_DIR_PATH . "/utils/layouts/activity/title.php";

        return ob_get_clean();
    }

    private function getActivityContentHtml($currentUserId, $currentUserEmail, $isFirstTab) {
        $html = "<div id='wpd-content-item-1' class='wpd-content-item'>";
        if ($isFirstTab) {
            include_once WPDISCUZ_DIR_PATH . "/utils/layouts/activity/content.php";
        } else {
            $html .= "<img alt='wpdiscuz-loading' src='" . plugins_url(WPDISCUZ_DIR_NAME . "/assets/img/loading.gif") . "' />";
        }
        $html .= "</div>";

        return $html;
    }

    public function getActivityPage() {
        $this->validateNonce();
        ob_start();
        include_once WPDISCUZ_DIR_PATH . "/utils/layouts/activity/activity-page.php";
        $html = ob_get_clean();
        wp_die($html);
    }

    private function getSubscriptionsTitleHtml() {
//        $this->validateNonce();
        ob_start();
        include_once WPDISCUZ_DIR_PATH . "/utils/layouts/subscriptions/title.php";

        return ob_get_clean();
    }

    private function getSubscriptionsContentHtml($currentUserId, $currentUserEmail, $isFirstTab) {
        $html = "<div id='wpd-content-item-2' class='wpd-content-item'>";
        if ($isFirstTab) {
            include_once WPDISCUZ_DIR_PATH . "/utils/layouts/subscriptions/content.php";
        } else {
            $html .= "<img alt='wpdiscuz-loading' src='" . plugins_url(WPDISCUZ_DIR_NAME . "/assets/img/loading.gif") . "' />";
        }
        $html .= "</div>";

        return $html;
    }

    public function getSubscriptionsPage() {
        $this->validateNonce();
        ob_start();
        include_once WPDISCUZ_DIR_PATH . "/utils/layouts/subscriptions/subscriptions-page.php";
        $html = ob_get_clean();
        wp_die($html);
    }

    private function getFollowsTitleHtml() {
//        $this->validateNonce();
        ob_start();
        include_once WPDISCUZ_DIR_PATH . "/utils/layouts/follows/title.php";

        return ob_get_clean();
    }

    private function getFollowsContentHtml($currentUserId, $currentUserEmail, $isFirstTab) {
        $html = "<div id='wpd-content-item-3' class='wpd-content-item'>";
        if ($isFirstTab) {
            include_once WPDISCUZ_DIR_PATH . "/utils/layouts/follows/content.php";
        } else {
            $html .= "<img alt='wpdiscuz-loading' src='" . plugins_url(WPDISCUZ_DIR_NAME . "/assets/img/loading.gif") . "' />";
        }
        $html .= "</div>";

        return $html;
    }

    public function getFollowsPage() {
        ob_start();
        include_once WPDISCUZ_DIR_PATH . "/utils/layouts/follows/follows-page.php";
        $html = ob_get_clean();
        wp_die($html);
    }

    public static function getIP() {
        $ip = "";
        if (!empty($_SERVER["HTTP_CLIENT_IP"])) {
            $ip = $_SERVER["HTTP_CLIENT_IP"];
        } elseif (!empty($_SERVER["HTTP_X_FORWARDED_FOR"])) {
            $ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
        } else {
            $ip = $_SERVER["REMOTE_ADDR"];
        }
        return $ip;
    }

    public static function isBanned() {
        $mod_keys = trim(get_option('disallowed_keys'));
        if ('' === $mod_keys) {
            return false;
        }

        $currentUser = wp_get_current_user();
        $email       = '';
        $ip          = self::getIP();
        if ($currentUser->exists()) {
            $email = $currentUser->user_email;
        }

        $words = explode("\n", $mod_keys);

        foreach ((array)$words as $word) {
            $word = trim($word);
            if (empty($word)) {
                continue;
            }
            $word    = preg_quote($word, '#');
            $pattern = "#$word#iu";
            if (preg_match($pattern, $ip) || preg_match($pattern, $email)) {
                return true;
            }
        }
        return false;
    }

    public static function fixEmailFrom($domain) {
        $domain = strtolower($domain);
        if (substr($domain, 0, 4) === "www.") {
            $domain = substr($domain, 4);
        }
        $localhost = ['127.0.0.1', '::1'];
        if (in_array($_SERVER['REMOTE_ADDR'], $localhost, true)) {
            $domain .= ".com";
        }

        return $domain;
    }

    public function fixLocalhostIp($ip) {
        if (trim($ip) === "::1") {
            $ip = "127.0.0.1";
        }

        return $ip;
    }

    public function fixURLScheme($url) {
        if (is_ssl() && strpos($url, "http://") !== false) {
            $url = str_replace("http://", "https://", $url);
        }

        return $url;
    }

    public function commentDataArr($data, $comment, $commentarr) {
        if (!empty($data["wpdiscuz_comment_update"])) {
            $data["comment_date"]     = $comment["comment_date"];
            $data["comment_date_gmt"] = $comment["comment_date_gmt"];
        }

        return $data;
    }

    public function getTwitterShareContent($comment_content, $commentLink) {
        $commentLinkLength = strlen($commentLink);
        $twitt_content     = "";
        if ($commentLinkLength < 110) {
            $twitt_content = esc_attr(strip_tags($comment_content));
            $length        = strlen($twitt_content);
            $twitt_content = function_exists("mb_substr") ? mb_substr($twitt_content, 0, 135 - $commentLinkLength) : substr($twitt_content, 0, 135 - $commentLinkLength);
            if (strlen($twitt_content) < $length) {
                $twitt_content .= "... ";
            }
        }

        return $twitt_content;
    }

    public function getWhatsappShareContent($comment_content, $commentLink) {
        $whatsapp_content = esc_attr(strip_tags($comment_content));
        $length           = strlen($whatsapp_content);
        $whatsapp_content = function_exists("mb_substr") ? mb_substr($whatsapp_content, 0, 100) : substr($whatsapp_content, 0, 100);
        if (strlen($whatsapp_content) < $length) {
            $whatsapp_content .= "... ";
        }
        $whatsapp_content = urlencode($whatsapp_content) . ' URL: ' . urlencode($commentLink);

        return $whatsapp_content;
    }


    public function preGetDefaultAvatarUrl($url, $idOrEmail, $args) {

        if (empty($this->options->thread_layouts["defaultAvatarUrlForUser"]) &&
            empty($this->options->thread_layouts["defaultAvatarUrlForGuest"])) {
            return $url;
        }

        if ($this->options->thread_layouts["changeAvatarsEverywhere"] || isset($args["wpdiscuz_gravatar_user_email"])) {
            $nameAndEmail = $this->getUserNameAndEmail($idOrEmail);

            $valid = true;
            if (isset($this->avatars[$nameAndEmail["email"]]["is_valid"])) {
                $valid = $this->avatars[$nameAndEmail["email"]]["is_valid"];
            } else if ($this->isValidAvatar($nameAndEmail["email"])) {
                $this->avatars[$nameAndEmail["email"]]["is_valid"] = true;
            } else {
                $valid                                             = false;
                $this->avatars[$nameAndEmail["email"]]["is_valid"] = false;
            }

            if (!$valid) {
                if (empty($this->avatars[$nameAndEmail["email"]]["url"])) {
                    $bp_has_avatar = false;

                    if (function_exists('bp_get_user_has_avatar') && !empty($nameAndEmail["user_id"])) {
                        $bp_has_avatar = bp_get_user_has_avatar($nameAndEmail["user_id"]);
                    }

                    if (!$bp_has_avatar) {
                        if ($nameAndEmail["isUser"] && $this->options->thread_layouts["defaultAvatarUrlForUser"]) {
                            $url = $this->options->thread_layouts["defaultAvatarUrlForUser"];
                        } else if (!$nameAndEmail["isUser"] && $this->options->thread_layouts["defaultAvatarUrlForGuest"]) {
                            $url = $this->options->thread_layouts["defaultAvatarUrlForGuest"];
                        }
                    }
                } else {
                    $url = $this->avatars[$nameAndEmail["email"]]["url"];
                }
            }

            $this->avatars[$nameAndEmail["email"]]["url"] = $url;

        }
        return $url;
    }

    public function preGetDefaultAvatar($avatar, $idOrEmail, $args) {
        if (empty($this->options->thread_layouts["defaultAvatarUrlForUser"]) &&
            empty($this->options->thread_layouts["defaultAvatarUrlForGuest"])) {
            return $avatar;
        }

        if ($this->options->thread_layouts["changeAvatarsEverywhere"] || isset($args["wpdiscuz_gravatar_user_email"])) {
            $nameAndEmail = $this->getUserNameAndEmail($idOrEmail);

            $valid = true;
            if (isset($this->avatars[$nameAndEmail["email"]])) {
                $valid = $this->avatars[$nameAndEmail["email"]];
            } else if ($this->isValidAvatar($nameAndEmail["email"])) {
                $this->avatars[$nameAndEmail["email"]] = true;
            } else {
                $valid                                 = false;
                $this->avatars[$nameAndEmail["email"]] = false;
            }

            if (!$valid) {
                $class = ["avatar", "avatar-" . (int)$args["size"], "photo"];
                if ($args["class"]) {
                    if (is_array($args["class"])) {
                        $class = array_merge($class, $args["class"]);
                    } else {
                        $class[] = $args["class"];
                    }
                }
                $url = false;
                if (function_exists('bp_core_fetch_avatar') && !empty($nameAndEmail["user_id"])) {
                    $url = bp_core_fetch_avatar(
                        array(
                            'item_id' => $nameAndEmail["user_id"],
                            'no_grav' => true,
                            'html'    => false,
                            'type'    => 'full',
                        ));
                }
                xdebug_var_dump($url);
                if (!$url) {
                    $url = $nameAndEmail["isUser"] ? $this->options->thread_layouts["defaultAvatarUrlForUser"] : $this->options->thread_layouts["defaultAvatarUrlForGuest"];
                }

                $url2x  = $url;
                $avatar = sprintf("<img alt='%s' src='%s' srcset='%s' class='%s' height='%d' width='%d' %s/>", esc_attr($args["alt"]), esc_url_raw($url), esc_attr("$url2x 2x"), esc_attr(implode(" ", $class)), esc_attr((int)$args["height"]), esc_attr((int)$args["width"]), $args["extra_attr"]);
            }
        }
        return $avatar;
    }

    public function preGetDefaultAvatarForUser($avatar, $idOrEmail, $args) {
        if ($this->options->thread_layouts["changeAvatarsEverywhere"] || isset($args["wpdiscuz_gravatar_user_email"])) {
//            xdebug_var_dump($args['url']);
            $nameAndEmail = $this->getUserNameAndEmail($idOrEmail);
            if ($nameAndEmail["isUser"]) {
                $valid = true;
                if (isset($this->avatars[$nameAndEmail["email"]])) {
                    $valid = $this->avatars[$nameAndEmail["email"]];
                } else if ($this->isValidAvatar($nameAndEmail["email"])) {
                    $this->avatars[$nameAndEmail["email"]] = true;
                } else {
                    $valid                                 = false;
                    $this->avatars[$nameAndEmail["email"]] = false;
                }
                if (!$valid) {
                    $class = ["avatar", "avatar-" . (int)$args["size"], "photo"];
                    if ($args["class"]) {
                        if (is_array($args["class"])) {
                            $class = array_merge($class, $args["class"]);
                        } else {
                            $class[] = $args["class"];
                        }
                    }

                    if (self::hasGrvatar($nameAndEmail["email"])) {
                        $url = $this->options->thread_layouts["defaultAvatarUrlForUser"];
                    } else {
                        $url = apply_filters("get_avatar_url", $this->options->thread_layouts["defaultAvatarUrlForUser"], $idOrEmail, $args);
                    }

                    $url2x  = $url;
                    $avatar = sprintf("<img alt='%s' src='%s' srcset='%s' class='%s' height='%d' width='%d' %s/>", esc_attr($args["alt"]), esc_url_raw($url), esc_attr("$url2x 2x"), esc_attr(implode(" ", $class)), esc_attr((int)$args["height"]), esc_attr((int)$args["width"]), $args["extra_attr"]);
//                    xdebug_var_dump($url);
                }
            }
        }

        return $avatar;
    }

    public function preGetDefaultAvatarForGuest($avatar, $idOrEmail, $args) {
        if ($this->options->thread_layouts["changeAvatarsEverywhere"] || isset($args["wpdiscuz_gravatar_user_email"])) {
            $nameAndEmail = $this->getUserNameAndEmail($idOrEmail);
            if (!$nameAndEmail["isUser"]) {
                $valid = true;
                if (isset($this->avatars[$nameAndEmail["email"]])) {
                    $valid = $this->avatars[$nameAndEmail["email"]];
                } else if ($this->isValidAvatar($nameAndEmail["email"])) {
                    $this->avatars[$nameAndEmail["email"]] = true;
                } else {
                    $valid                                 = false;
                    $this->avatars[$nameAndEmail["email"]] = false;
                }
                if (!$valid) {
                    $class = ["avatar", "avatar-" . (int)$args["size"], "photo"];
                    if ($args["class"]) {
                        if (is_array($args["class"])) {
                            $class = array_merge($class, $args["class"]);
                        } else {
                            $class[] = $args["class"];
                        }
                    }
                    $url = $this->options->thread_layouts["defaultAvatarUrlForGuest"];
//                    $url2x  = $url;
//                    $avatar = sprintf("<img alt='%s' src='%s' srcset='%s' class='%s' height='%d' width='%d' %s/>", esc_attr($args["alt"]), esc_url_raw($url), esc_attr("$url2x 2x"), esc_attr(implode(" ", $class)), esc_attr((int)$args["height"]), esc_attr((int)$args["width"]), $args["extra_attr"]);
                }
            }
        }

        return $avatar;
    }

    private function isValidAvatar($email) {
        $url     = "https://www.gravatar.com/avatar/" . md5($email) . "?d=404";
        $headers = wp_remote_head($url);

        return !is_wp_error($headers) && 200 === $headers["response"]["code"];
    }

    public static function hasGrvatar($email) {
        $email_hash = hash('sha256', trim($email));
        $checkerUrl = "https://secure.gravatar.com/{$email_hash}.json";
        $headers    = wp_remote_head($checkerUrl);
        return !is_wp_error($headers) && 200 === $headers["response"]["code"];
    }

    private function getUserNameAndEmail($idOrEmail) {
        $nameAndEmail = ["name" => "guest", "email" => "unknown@example.com", "isUser" => 0, "user_id" => 0];
        if ($idOrEmail instanceof WP_Comment) {
            if (!empty($idOrEmail->comment_author_email)) {
                $nameAndEmail = [
                    "name"    => $idOrEmail->comment_author,
                    "email"   => $idOrEmail->comment_author_email,
                    "isUser"  => 1,
                    "user_id" => $idOrEmail->user_id,
                ];
            }
        } else if ($idOrEmail instanceof WP_User) {
            $nameAndEmail = [
                "name"    => $idOrEmail->display_name,
                "email"   => $idOrEmail->user_email,
                "isUser"  => 1,
                "user_id" => $idOrEmail->ID,
            ];
        } else if (is_numeric($idOrEmail)) {
            $user = get_user_by("id", $idOrEmail);
            if ($user) {
                $nameAndEmail = ["name" => $user->display_name, "email" => $user->user_email, "isUser" => 1, "user_id" => $user->ID];
            }
        } else if (is_string($idOrEmail)) {
            $user = get_user_by("email", $idOrEmail);
            if ($user) {
                $nameAndEmail = ["name" => $user->display_name, "email" => $user->user_email, "isUser" => 1, "user_id" => $user->ID];
            } else {
                $nameAndEmail["email"] = $idOrEmail;
            }
        }

        return $nameAndEmail;
    }

    public function userMentioning($content, $comment, $args = []) {
        if (apply_filters("wpdiscuz_enable_user_mentioning", true) && !empty($args["is_wpdiscuz_comment"]) && ($users = $this->getMentionedUsers($content))) {
            foreach ($users as $k => $user) {
                if ($this->options->login["enableProfileURLs"]) {
                    $user_link = "";
                    if (class_exists("UM_API")) {
                        $user_link = um_user_profile_url($user["u_id"]);
                    } else if (class_exists("BuddyPress")) {
                        $user_link = self::getBPUserUrl($user["u_id"]);
                    } else {
                        $user_link = get_author_posts_url($user["u_id"]);
                    }

                    if ($user_link) {
                        $replacement = "<a href='" . $user_link . "' rel='author'>@" . $user["name"] . "</a>";
                    } else {
                        $replacement = "<span>@" . $user["name"] . "</span>";
                    }
                } else {
                    $replacement = "<span>@" . $user["name"] . "</span>";
                }

                $replacement .= "$2";
                $content     = preg_replace("/(" . $user["replace"] . ")([\s\n\r\t\@\,\.\!\?\#\$\%\-\:\;\'\"\`\~\)\(\}\{\|\\\[\]]?)/", $replacement, $content);
            }
        }

        return $content;
    }

    public function doShortcode($content, $comment, $args = []) {
        if (!empty($args["is_wpdiscuz_comment"])) {
            return do_shortcode($content);
        }

        return $content;
    }

    public function getMentionedUsers($content) {
        $users = [];
        if (preg_match_all("/(@[^\s\,\@\.\!\?\#\$\%\:\;\'\"\`\~\)\(\}\{\|\\\[\]]*)/is", $content, $nicenames)) {
            $nicenames = array_unique(array_map("strip_tags", $nicenames[0]));
            foreach ($nicenames as $k => $nicename) {
                $user = $this->dbManager->getUserByNicename(ltrim($nicename, "@"));
                if ($user) {
                    $users[] = [
                        "replace" => $nicename,
                        "u_id"    => $user->ID,
                        "name"    => $user->display_name,
                        "email"   => $user->user_email
                    ];
                }
            }
        }

        return $users;
    }

    public function checkFeedbackShortcodes($post_ID, $post_after, $post_before) {
        if (comments_open($post_ID) && ($form = $this->wpdiscuzForm->getForm($post_ID)) && $form->getFormID()) {
            preg_match_all(self::$inlineFormPattern, $post_before->post_content, $matchesBefore, PREG_SET_ORDER);
            if ($post_after->post_content) {
                preg_match_all(self::$inlineFormPattern, $post_after->post_content, $matchesAfter, PREG_SET_ORDER);
            } else {
                $matchesAfter  = $matchesBefore;
                $matchesBefore = [];
            }
            if ($matchesAfter || $matchesBefore) {
                $inlineFormsBefore = [];
                $defaultAtts       = ["id" => "", "question" => "", "opened" => 0, "content" => ""];
                foreach ($matchesBefore as $k => $matchBefore) {
                    if (isset($matchBefore[3])) {
                        $atts = shortcode_parse_atts($matchBefore[3]);
                        $atts = array_merge($defaultAtts, $atts);
                        if (($atts["id"] = trim($atts["id"])) && ($atts["question"] = strip_tags($atts["question"]))) {
                            $inlineFormsBefore[$atts["id"]] = [
                                "question" => $atts["question"],
                                "opened"   => $atts["opened"],
                                "content"  => $matchBefore[5]
                            ];
                        }
                    }
                }
                foreach ($matchesAfter as $k => $matchAfter) {
                    if (isset($matchAfter[3])) {
                        if (function_exists("use_block_editor_for_post") && use_block_editor_for_post($post_ID)) {
                            $matchAfter[3] = json_decode('"' . $matchAfter[3] . '"');
                        }
                        $atts            = shortcode_parse_atts($matchAfter[3]);
                        $atts["content"] = $matchAfter[5];
                        $atts            = array_merge($defaultAtts, $atts);
                        if (($atts["id"] = trim($atts["id"])) && ($atts["question"] = strip_tags($atts["question"]))) {
                            if (isset($inlineFormsBefore[$atts["id"]])) {
                                if ($this->dbManager->getFeedbackFormByUid($post_ID, $atts["id"])) {
                                    if ($atts["question"] !== $inlineFormsBefore[$atts["id"]]["question"] || $atts["opened"] !== $inlineFormsBefore[$atts["id"]]["opened"] || $atts["content"] !== $inlineFormsBefore[$atts["id"]]["content"]) {
                                        $this->dbManager->updateFeedbackForm($post_ID, $atts["id"], $atts["question"], $atts["opened"], $atts["content"]);
                                    }
                                } else {
                                    $this->dbManager->addFeedbackForm($post_ID, $atts["id"], $atts["question"], $atts["opened"], $atts["content"]);
                                }
                                unset($inlineFormsBefore[$atts["id"]]);
                            } else {
                                $this->dbManager->addFeedbackForm($post_ID, $atts["id"], $atts["question"], $atts["opened"], $atts["content"]);
                            }
                        }
                    }
                }
                foreach ($inlineFormsBefore as $uid => $inlineFormBefore) {
                    $this->dbManager->deleteFeedbackForm($post_ID, $uid);
                }
            }
        }
    }

    public function checkMetaFeedbackShortcodes($meta_id, $post_ID, $meta_key, $meta_value) {
        if ($meta_key === '_edit_lock' || $meta_key === '_edit_last') {
            return;
        }
        if (comments_open($post_ID) && ($form = $this->wpdiscuzForm->getForm($post_ID)) && $form->getFormID()) {
            $meta_before = get_post_meta($post_ID, $meta_key, true);
            if (!is_string($meta_before) || !is_string($meta_value)) {
                return;
            }
            preg_match_all(self::$inlineFormPattern, $meta_before, $matchesBefore, PREG_SET_ORDER);
            if ($meta_value) {
                preg_match_all(self::$inlineFormPattern, $meta_value, $matchesAfter, PREG_SET_ORDER);
            } else {
                $matchesAfter  = $matchesBefore;
                $matchesBefore = [];
            }
            if ($matchesAfter || $matchesBefore) {
                $inlineFormsBefore = [];
                $defaultAtts       = ["id" => "", "question" => "", "opened" => 0, "content" => ""];
                foreach ($matchesBefore as $k => $matchBefore) {
                    if (isset($matchBefore[3])) {
                        $matchBefore[3] = str_replace('\"', "'", addslashes($matchBefore[3]));
                        if (preg_match_all(self::$inlineFormAttsPattern, $matchBefore[3], $attsBefore, PREG_SET_ORDER)) {
                            $atts = [];
                            foreach ($attsBefore as $k1 => $attrBefore) {
                                $atts[$attrBefore[1]] = $attrBefore[2];
                            }
                            $atts = array_merge($defaultAtts, $atts);
                            if (($atts["id"] = trim($atts["id"])) && ($atts["question"] = strip_tags($atts["question"]))) {
                                $inlineFormsBefore[$atts["id"]] = [
                                    "question" => $atts["question"],
                                    "opened"   => $atts["opened"],
                                    "content"  => $matchBefore[5]
                                ];
                            }
                        }
                    }
                }
                foreach ($matchesAfter as $k => $matchAfter) {
                    if (isset($matchAfter[3])) {
                        $matchAfter[3] = str_replace('\"', "'", addslashes($matchAfter[3]));
                        if (function_exists("use_block_editor_for_post") && use_block_editor_for_post($post_ID)) {
                            $matchAfter[3] = json_decode('"' . $matchAfter[3] . '"');
                        }
                        if (preg_match_all(self::$inlineFormAttsPattern, $matchAfter[3], $attsAfter, PREG_SET_ORDER)) {
                            $atts = [];
                            foreach ($attsAfter as $k1 => $attrAfter) {
                                $atts[$attrAfter[1]] = $attrAfter[2];
                            }
                            $atts["content"] = $matchAfter[5];
                            $atts            = array_merge($defaultAtts, $atts);
                            if (($atts["id"] = trim($atts["id"])) && ($atts["question"] = strip_tags($atts["question"]))) {
                                if (isset($inlineFormsBefore[$atts["id"]])) {
                                    if ($this->dbManager->getFeedbackFormByUid($post_ID, $atts["id"])) {
                                        if ($atts["question"] !== $inlineFormsBefore[$atts["id"]]["question"] || $atts["opened"] !== $inlineFormsBefore[$atts["id"]]["opened"] || $atts["content"] !== $inlineFormsBefore[$atts["id"]]["content"]) {
                                            $this->dbManager->updateFeedbackForm($post_ID, $atts["id"], $atts["question"], $atts["opened"], $atts["content"]);
                                        }
                                    } else {
                                        $this->dbManager->addFeedbackForm($post_ID, $atts["id"], $atts["question"], $atts["opened"], $atts["content"]);
                                    }
                                    unset($inlineFormsBefore[$atts["id"]]);
                                } else {
                                    $this->dbManager->addFeedbackForm($post_ID, $atts["id"], $atts["question"], $atts["opened"], $atts["content"]);
                                }
                            }
                        }
                    }
                }
                foreach ($inlineFormsBefore as $uid => $inlineFormBefore) {
                    $this->dbManager->deleteFeedbackForm($post_ID, $uid);
                }
            }
        }
    }

    public function getCommentFormPath($theme) {
        if (file_exists(get_stylesheet_directory() . "/wpdiscuz/comment-form.php")) {
            return get_stylesheet_directory() . "/wpdiscuz/comment-form.php";
        } elseif (file_exists(get_template_directory() . "/wpdiscuz/comment-form.php")) {
            return get_template_directory() . "/wpdiscuz/comment-form.php";
        } else {
            return apply_filters("wpdiscuz_comment_form_include", $theme . "/comment-form.php");
        }
    }

    public function getWalkerPath($theme) {
        if (file_exists(get_stylesheet_directory() . "/wpdiscuz/class.WpdiscuzWalker.php")) {
            return get_stylesheet_directory() . "/wpdiscuz/class.WpdiscuzWalker.php";
        } elseif (file_exists(get_template_directory() . "/wpdiscuz/class.WpdiscuzWalker.php")) {
            return get_template_directory() . "/wpdiscuz/class.WpdiscuzWalker.php";
        } else {
            return apply_filters("wpdiscuz_walker_include", $theme . "/class.WpdiscuzWalker.php");
        }
    }

    public function scanDir($path) {
        $scannedComponents = scandir($path);
        unset($scannedComponents[0]);
        unset($scannedComponents[1]);
        $components = [];
        foreach ($scannedComponents as $k => $component) {
            if ("index.html" !== $component) {
                $components[$component] = $path . $component;
            }
        }

        return $components;
    }

    public function getComponents($theme, $layout) {
        $wpdPath           = $theme . "/layouts/{$layout}/";
        $wpdComponents     = $this->scanDir($wpdPath);
        $scannedComponents = [];
        if (is_dir(get_stylesheet_directory() . "/wpdiscuz/layouts/" . $layout)) {
            $scannedComponents = $this->scanDir(get_stylesheet_directory() . "/wpdiscuz/layouts/" . $layout . "/");
        } else if (is_dir(get_template_directory() . "/wpdiscuz/layouts/" . $layout)) {
            $scannedComponents = $this->scanDir(get_template_directory() . "/wpdiscuz/layouts/" . $layout . "/");
        }
        $components = array_merge($wpdComponents, $scannedComponents);
        foreach ($components as $key => $component) {
            $components[$key] = file_get_contents($component);
        }

        return $components;
    }

    public function restrictCommentingPerUser($email, $comment_parent, $postId) {
        if ($this->options->moderation["restrictCommentingPerUser"] !== "disable") {
            $args = ["count" => true, "author_email" => $email];
            if ($this->options->moderation["restrictCommentingPerUser"] === "post") {
                $args["post_id"] = $postId;
            }
            if ($this->options->moderation["commentRestrictionType"] === "both") {
                $count = get_comments($args);
                if ($count >= $this->options->moderation["userCommentsLimit"]) {
                    wp_die(esc_html(sprintf($this->options->getPhrase("wc_not_allowed_to_comment_more_than"), $count)));
                }
            } else if ($this->options->moderation["commentRestrictionType"] === "parent" && !$comment_parent) {
                $args["parent"] = 0;
                $count          = get_comments($args);
                if ($count >= $this->options->moderation["userCommentsLimit"]) {
                    wp_die(esc_html(sprintf($this->options->getPhrase("wc_not_allowed_to_create_comment_thread_more_than"), $count)));
                }
            } else if ($this->options->moderation["commentRestrictionType"] === "reply" && $comment_parent) {
                $args["parent__not_in"] = [0];
                $count                  = get_comments($args);
                if ($count >= $this->options->moderation["userCommentsLimit"]) {
                    wp_die(esc_html(sprintf($this->options->getPhrase("wc_not_allowed_to_reply_more_than"), $count)));
                }
            }
        }
    }

    public function isUnapprovedInTree($comments) {
        foreach ($comments as $comment) {
            if ($comment->comment_approved === "0") {
                return true;
            }
            if ($children = $comment->get_children()) {
                if ($this->isUnapprovedInTree($children)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function getCommentAuthor($comment, $args) {
        $user = ["user" => ""];
        if ($comment->user_id) {
            $user["user"] = get_user_by("id", $comment->user_id);
        } else if ($this->options->login["isUserByEmail"]) {
            $user["user"] = get_user_by("email", $comment->comment_author_email);
        }
        $user["commentAuthorUrl"] = ("http://" === $comment->comment_author_url) ? "" : $comment->comment_author_url;
        $user["commentAuthorUrl"] = apply_filters("get_comment_author_url", $user["commentAuthorUrl"], $comment->comment_ID, $comment);
        $user["commentWrapClass"] = [];
        if ($user["user"]) {
            $user["authorName"]        = $comment->comment_author;
            $user["authorAvatarField"] = $user["user"]->ID;
            $user["gravatarUserId"]    = $user["user"]->ID;
            $user["gravatarUserEmail"] = $comment->comment_author_email;
            $user["profileUrl"]        = in_array($user["user"]->ID, $args["posts_authors"]) ? get_author_posts_url($user["user"]->ID) : "";
            $user["profileUrl"]        = $this->getProfileUrl($user["profileUrl"], $user["user"]);
            if ($this->options->social["displayIconOnAvatar"] && ($socialProvider = get_user_meta($user["user"]->ID, self::WPDISCUZ_SOCIAL_PROVIDER_KEY, true))) {
                $user["commentWrapClass"][] = "wpd-soc-user-" . $socialProvider;
                if ($socialProvider === "facebook") {
                    $user["socIcon"] = "<i><svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 320 512'><path d='M80 299.3V512H196V299.3h86.5l18-97.8H196V166.9c0-51.7 20.3-71.5 72.7-71.5c16.3 0 29.4 .4 37 1.2V7.9C291.4 4 256.4 0 236.2 0C129.3 0 80 50.5 80 159.4v42.1H14v97.8H80z'/></svg></i>";
                } else if ($socialProvider === "instagram") {
                    $user["socIcon"] = "<i><svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 448 512'><path d='M224.1 141c-63.6 0-114.9 51.3-114.9 114.9s51.3 114.9 114.9 114.9S339 319.5 339 255.9 287.7 141 224.1 141zm0 189.6c-41.1 0-74.7-33.5-74.7-74.7s33.5-74.7 74.7-74.7 74.7 33.5 74.7 74.7-33.6 74.7-74.7 74.7zm146.4-194.3c0 14.9-12 26.8-26.8 26.8-14.9 0-26.8-12-26.8-26.8s12-26.8 26.8-26.8 26.8 12 26.8 26.8zm76.1 27.2c-1.7-35.9-9.9-67.7-36.2-93.9-26.2-26.2-58-34.4-93.9-36.2-37-2.1-147.9-2.1-184.9 0-35.8 1.7-67.6 9.9-93.9 36.1s-34.4 58-36.2 93.9c-2.1 37-2.1 147.9 0 184.9 1.7 35.9 9.9 67.7 36.2 93.9s58 34.4 93.9 36.2c37 2.1 147.9 2.1 184.9 0 35.9-1.7 67.7-9.9 93.9-36.2 26.2-26.2 34.4-58 36.2-93.9 2.1-37 2.1-147.8 0-184.8zM398.8 388c-7.8 19.6-22.9 34.7-42.6 42.6-29.5 11.7-99.5 9-132.1 9s-102.7 2.6-132.1-9c-19.6-7.8-34.7-22.9-42.6-42.6-11.7-29.5-9-99.5-9-132.1s-2.6-102.7 9-132.1c7.8-19.6 22.9-34.7 42.6-42.6 29.5-11.7 99.5-9 132.1-9s102.7-2.6 132.1 9c19.6 7.8 34.7 22.9 42.6 42.6 11.7 29.5 9 99.5 9 132.1s2.7 102.7-9 132.1z'/></svg></i>";
                } else if ($socialProvider === "twitter") {
                    $user["socIcon"] = "<i><svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 512 512'><path d='M389.2 48h70.6L305.6 224.2 487 464H345L233.7 318.6 106.5 464H35.8L200.7 275.5 26.8 48H172.4L272.9 180.9 389.2 48zM364.4 421.8h39.1L151.1 88h-42L364.4 421.8z'/></svg></i>";
                } else if ($socialProvider === "google") {
                    $user["socIcon"] = "<i><svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 488 512'><path d='M488 261.8C488 403.3 391.1 504 248 504 110.8 504 0 393.2 0 256S110.8 8 248 8c66.8 0 123 24.5 166.3 64.9l-67.5 64.9C258.5 52.6 94.3 116.6 94.3 256c0 86.5 69.1 156.6 153.7 156.6 98.2 0 135-70.4 140.8-106.9H248v-85.3h236.1c2.3 12.7 3.9 24.9 3.9 41.4z'/></svg></i>";
                } else if ($socialProvider === "disqus") {
                    $user["socIcon"] = "<i><svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 384 512'><path d='M0 96C0 60.7 28.7 32 64 32l96 0c123.7 0 224 100.3 224 224s-100.3 224-224 224l-96 0c-35.3 0-64-28.7-64-64L0 96zm160 0L64 96l0 320 96 0c88.4 0 160-71.6 160-160s-71.6-160-160-160z'/></svg></i>";
                } else if ($socialProvider === "qq") {
                    $user["socIcon"] = "<i><svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 448 512'><path d='M433.8 420.4c-11.5 1.4-44.9-52.7-44.9-52.7 0 31.3-16.1 72.2-51.1 101.8 16.8 5.2 54.8 19.2 45.8 34.4-7.3 12.3-125.5 7.9-159.6 4-34.1 3.8-152.3 8.3-159.6-4-9-15.3 28.9-29.2 45.8-34.4-34.9-29.5-51.1-70.4-51.1-101.8 0 0-33.3 54.1-44.9 52.7-5.4-.7-12.4-29.6 9.3-99.7 10.3-33 22-60.5 40.1-105.8C60.7 98.1 109 0 224 0c113.7 0 163.2 96.1 160.3 215 18.1 45.2 29.9 72.9 40.1 105.8 21.8 70.1 14.7 99.1 9.3 99.7z'/></svg></i>";
                } else if ($socialProvider === "weibo") {
                    $user["socIcon"] = "<i><svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 512 512'><path d='M407 177.6c7.6-24-13.4-46.8-37.4-41.7-22 4.8-28.8-28.1-7.1-32.8 50.1-10.9 92.3 37.1 76.5 84.8-6.8 21.2-38.8 10.8-32-10.3zM214.8 446.7C108.5 446.7 0 395.3 0 310.4c0-44.3 28-95.4 76.3-143.7C176 67 279.5 65.8 249.9 161c-4 13.1 12.3 5.7 12.3 6 79.5-33.6 140.5-16.8 114 51.4-3.7 9.4 1.1 10.9 8.3 13.1 135.7 42.3 34.8 215.2-169.7 215.2zm143.7-146.3c-5.4-55.7-78.5-94-163.4-85.7-84.8 8.6-148.8 60.3-143.4 116s78.5 94 163.4 85.7c84.8-8.6 148.8-60.3 143.4-116zM347.9 35.1c-25.9 5.6-16.8 43.7 8.3 38.3 72.3-15.2 134.8 52.8 111.7 124-7.4 24.2 29.1 37 37.4 12 31.9-99.8-55.1-195.9-157.4-174.3zm-78.5 311c-17.1 38.8-66.8 60-109.1 46.3-40.8-13.1-58-53.4-40.3-89.7 17.7-35.4 63.1-55.4 103.4-45.1 42 10.8 63.1 50.2 46 88.5zm-86.3-30c-12.9-5.4-30 .3-38 12.9-8.3 12.9-4.3 28 8.6 34 13.1 6 30.8 .3 39.1-12.9 8-13.1 3.7-28.3-9.7-34zm32.6-13.4c-5.1-1.7-11.4 .6-14.3 5.4-2.9 5.1-1.4 10.6 3.7 12.9 5.1 2 11.7-.3 14.6-5.4 2.8-5.2 1.1-10.9-4-12.9z'/></svg></i>";
                } else if ($socialProvider === "weixin") {
                    $user["socIcon"] = "<i><svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 576 512'><path d='M385.2 167.6c6.4 0 12.6 .3 18.8 1.1C387.4 90.3 303.3 32 207.7 32 100.5 32 13 104.8 13 197.4c0 53.4 29.3 97.5 77.9 131.6l-19.3 58.6 68-34.1c24.4 4.8 43.8 9.7 68.2 9.7 6.2 0 12.1-.3 18.3-.8-4-12.9-6.2-26.6-6.2-40.8-.1-84.9 72.9-154 165.3-154zm-104.5-52.9c14.5 0 24.2 9.7 24.2 24.4 0 14.5-9.7 24.2-24.2 24.2-14.8 0-29.3-9.7-29.3-24.2 .1-14.7 14.6-24.4 29.3-24.4zm-136.4 48.6c-14.5 0-29.3-9.7-29.3-24.2 0-14.8 14.8-24.4 29.3-24.4 14.8 0 24.4 9.7 24.4 24.4 0 14.6-9.6 24.2-24.4 24.2zM563 319.4c0-77.9-77.9-141.3-165.4-141.3-92.7 0-165.4 63.4-165.4 141.3S305 460.7 397.6 460.7c19.3 0 38.9-5.1 58.6-9.9l53.4 29.3-14.8-48.6C534 402.1 563 363.2 563 319.4zm-219.1-24.5c-9.7 0-19.3-9.7-19.3-19.6 0-9.7 9.7-19.3 19.3-19.3 14.8 0 24.4 9.7 24.4 19.3 0 10-9.7 19.6-24.4 19.6zm107.1 0c-9.7 0-19.3-9.7-19.3-19.6 0-9.7 9.7-19.3 19.3-19.3 14.5 0 24.4 9.7 24.4 19.3 .1 10-9.9 19.6-24.4 19.6z'/></svg></i>";
                } else if ($socialProvider === "vk") {
                    $user["socIcon"] = "<i><svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 448 512'><path d='M31.5 63.5C0 95 0 145.7 0 247V265C0 366.3 0 417 31.5 448.5C63 480 113.7 480 215 480H233C334.3 480 385 480 416.5 448.5C448 417 448 366.3 448 265V247C448 145.7 448 95 416.5 63.5C385 32 334.3 32 233 32H215C113.7 32 63 32 31.5 63.5zM75.6 168.3H126.7C128.4 253.8 166.1 290 196 297.4V168.3H244.2V242C273.7 238.8 304.6 205.2 315.1 168.3H363.3C359.3 187.4 351.5 205.6 340.2 221.6C328.9 237.6 314.5 251.1 297.7 261.2C316.4 270.5 332.9 283.6 346.1 299.8C359.4 315.9 369 334.6 374.5 354.7H321.4C316.6 337.3 306.6 321.6 292.9 309.8C279.1 297.9 262.2 290.4 244.2 288.1V354.7H238.4C136.3 354.7 78 284.7 75.6 168.3z'/></svg></i>";
                } else if ($socialProvider === "wordpress") {
                    $user["socIcon"] = "<i><svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 512 512'><path d='M256 8C119.3 8 8 119.2 8 256c0 136.7 111.3 248 248 248s248-111.3 248-248C504 119.2 392.7 8 256 8zM33 256c0-32.3 6.9-63 19.3-90.7l106.4 291.4C84.3 420.5 33 344.2 33 256zm223 223c-21.9 0-43-3.2-63-9.1l66.9-194.4 68.5 187.8c.5 1.1 1 2.1 1.6 3.1-23.1 8.1-48 12.6-74 12.6zm30.7-327.5c13.4-.7 25.5-2.1 25.5-2.1 12-1.4 10.6-19.1-1.4-18.4 0 0-36.1 2.8-59.4 2.8-21.9 0-58.7-2.8-58.7-2.8-12-.7-13.4 17.7-1.4 18.4 0 0 11.4 1.4 23.4 2.1l34.7 95.2L200.6 393l-81.2-241.5c13.4-.7 25.5-2.1 25.5-2.1 12-1.4 10.6-19.1-1.4-18.4 0 0-36.1 2.8-59.4 2.8-4.2 0-9.1-.1-14.4-.3C109.6 73 178.1 33 256 33c58 0 110.9 22.2 150.6 58.5-1-.1-1.9-.2-2.9-.2-21.9 0-37.4 19.1-37.4 39.6 0 18.4 10.6 33.9 21.9 52.3 8.5 14.8 18.4 33.9 18.4 61.5 0 19.1-7.3 41.2-17 72.1l-22.2 74.3-80.7-239.6zm81.4 297.2l68.1-196.9c12.7-31.8 17-57.2 17-79.9 0-8.2-.5-15.8-1.5-22.9 17.4 31.8 27.3 68.2 27.3 107 0 82.3-44.6 154.1-110.9 192.7z'/></svg></i>";
                } else if ($socialProvider === "linkedin") {
                    $user["socIcon"] = "<i><svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 448 512'><path d='M100.3 448H7.4V148.9h92.9zM53.8 108.1C24.1 108.1 0 83.5 0 53.8a53.8 53.8 0 0 1 107.6 0c0 29.7-24.1 54.3-53.8 54.3zM447.9 448h-92.7V302.4c0-34.7-.7-79.2-48.3-79.2-48.3 0-55.7 37.7-55.7 76.7V448h-92.8V148.9h89.1v40.8h1.3c12.4-23.5 42.7-48.3 87.9-48.3 94 0 111.3 61.9 111.3 142.3V448z'/></svg></i>";
                } else if ($socialProvider === "yandex") {
                    $user["socIcon"] = "<i><svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 320 512'><path d='M129.5 512V345.9L18.5 48h55.8l81.8 229.7L250.2 0h51.3L180.8 347.8V512h-51.3z'/></svg></i>";
                } else if ($socialProvider === "baidu") {
                    $user["socIcon"] = "<i><svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 512 512'><path d='M226.5 92.9c14.3 42.9-.3 86.2-32.6 96.8s-70.1-15.6-84.4-58.5s.3-86.2 32.6-96.8s70.1 15.6 84.4 58.5zM100.4 198.6c18.9 32.4 14.3 70.1-10.2 84.1s-59.7-.9-78.5-33.3S-2.7 179.3 21.8 165.3s59.7 .9 78.5 33.3zM69.2 401.2C121.6 259.9 214.7 224 256 224s134.4 35.9 186.8 177.2c3.6 9.7 5.2 20.1 5.2 30.5l0 1.6c0 25.8-20.9 46.7-46.7 46.7c-11.5 0-22.9-1.4-34-4.2l-88-22c-15.3-3.8-31.3-3.8-46.6 0l-88 22c-11.1 2.8-22.5 4.2-34 4.2C84.9 480 64 459.1 64 433.3l0-1.6c0-10.4 1.6-20.8 5.2-30.5zM421.8 282.7c-24.5-14-29.1-51.7-10.2-84.1s54-47.3 78.5-33.3s29.1 51.7 10.2 84.1s-54 47.3-78.5 33.3zM310.1 189.7c-32.3-10.6-46.9-53.9-32.6-96.8s52.1-69.1 84.4-58.5s46.9 53.9 32.6 96.8s-52.1 69.1-84.4 58.5z'/></svg></i>";
                } else if ($socialProvider === "telegram") {
                    $user["socIcon"] = "<i><svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 496 512'><path d='M248 8C111 8 0 119 0 256S111 504 248 504 496 393 496 256 385 8 248 8zM363 176.7c-3.7 39.2-19.9 134.4-28.1 178.3-3.5 18.6-10.3 24.8-16.9 25.4-14.4 1.3-25.3-9.5-39.3-18.7-21.8-14.3-34.2-23.2-55.3-37.2-24.5-16.1-8.6-25 5.3-39.5 3.7-3.8 67.1-61.5 68.3-66.7 .2-.7 .3-3.1-1.2-4.4s-3.6-.8-5.1-.5q-3.3 .7-104.6 69.1-14.8 10.2-26.9 9.9c-8.9-.2-25.9-5-38.6-9.1-15.5-5-27.9-7.7-26.8-16.3q.8-6.7 18.5-13.7 108.4-47.2 144.6-62.3c68.9-28.6 83.2-33.6 92.5-33.8 2.1 0 6.6 .5 9.6 2.9a10.5 10.5 0 0 1 3.5 6.7A43.8 43.8 0 0 1 363 176.7z'/></svg></i>";
                }
            }
        } else {
            $user["authorName"]        = $comment->comment_author ? $comment->comment_author : esc_html($this->options->getPhrase("wc_anonymous"));
            $user["authorAvatarField"] = $comment->comment_author_email;
            $user["gravatarUserId"]    = 0;
            $user["gravatarUserEmail"] = $comment->comment_author_email;
            $user["profileUrl"]        = "";
        }
        $user["authorName"] = apply_filters("wpdiscuz_comment_author", $user["authorName"], $comment);
        if ($this->options->thread_layouts["showAvatars"] && $this->options->wp["showAvatars"]) {
            $user["authorAvatarField"] = apply_filters("wpdiscuz_author_avatar_field", $user["authorAvatarField"], $comment, $user["user"], $user["profileUrl"]);
            $user["gravatarArgs"]      = [
                "wpdiscuz_gravatar_field"      => $user["authorAvatarField"],
                "wpdiscuz_gravatar_size"       => $args["wpdiscuz_gravatar_size"],
                "wpdiscuz_gravatar_user_id"    => $user["gravatarUserId"],
                "wpdiscuz_gravatar_user_email" => $user["gravatarUserEmail"],
                "wpdiscuz_current_user"        => self::filterUser($user["user"]),
                "wpdiscuz_comment"             => $comment
            ];
            $user["avatar"]            = get_avatar($user["gravatarArgs"]["wpdiscuz_gravatar_field"], $user["gravatarArgs"]["wpdiscuz_gravatar_size"], "", $user["authorName"], $user["gravatarArgs"]);
        }
        $user["authorNameHtml"] = $user["authorName"];
        if ($this->options->login["enableProfileURLs"]) {
            if ($user["profileUrl"]) {
                $attributes = apply_filters("wpdiscuz_avatar_link_attributes", [
                    "href"   => $user["profileUrl"],
                    "target" => "_blank",
                    "rel"    => "noreferrer ugc"
                ]);
                if ($attributes && is_array($attributes)) {
                    $attributesHtml = "";
                    foreach ($attributes as $attribute => $value) {
                        $attributesHtml .= " $attribute='{$value}'";
                    }
                    $user["authorAvatarSprintf"] = "<a" . str_replace("%", "%%", $attributesHtml) . ">%s</a>";
                } else {
                    $user["authorAvatarSprintf"] = "<a rel='noreferrer ugc' href='" . str_replace("%", "%%", $user["profileUrl"]) . "' target='_blank'>%s</a>";
                }
            }
            if ((($href = $user["commentAuthorUrl"]) && $this->options->login["websiteAsProfileUrl"]) || ($href = $user["profileUrl"])) {
                $rel = "noreferrer ugc";
                if (strpos($href, $args["site_url"]) !== 0) {
                    $rel .= " nofollow";
                }
                $attributes = apply_filters("wpdiscuz_author_link_attributes", [
                    "href"   => $href,
                    "rel"    => $rel,
                    "target" => "_blank"
                ]);
                if ($attributes && is_array($attributes)) {
                    $attributesHtml = "";
                    foreach ($attributes as $attribute => $value) {
                        $attributesHtml .= " $attribute='$value'";
                    }
                    $user["authorNameHtml"] = "<a$attributesHtml>{$user["authorNameHtml"]}</a>";
                } else {
                    $user["authorNameHtml"] = "<a rel='$rel' href='$href' target='_blank'>{$user["authorNameHtml"]}</a>";
                }
            }
        }
        $this->fillUserRoleData($user, $args);

        return $user;
    }

    public function fillUserRoleData(&$user, $args) {
        $user["author_title"]         = "";
        $user["commentWrapRoleClass"] = [];
        if ($user["user"]) {
            if ($this->options->labels["blogRoles"]) {
                if ($user["user"]->roles && is_array($user["user"]->roles)) {
                    foreach ($user["user"]->roles as $k => $role) {
                        if (isset($this->options->labels["blogRoles"][$role])) {
                            $user["commentWrapRoleClass"][] = "wpd-blog-user";
                            $user["commentWrapRoleClass"][] = "wpd-blog-" . $role;
                            $rolePhrase                     = esc_html($this->options->getPhrase("wc_blog_role_" . $role, ["default" => ""]));
                            if (!empty($this->options->labels["blogRoleLabels"][$role])) {
                                $user["author_title"] = apply_filters("wpdiscuz_user_label", $rolePhrase, $user["user"]);
                            }
                            break;
                        }
                    }
                } else {
                    $user["commentWrapRoleClass"][] = "wpd-blog-guest";
                    if (!empty($this->options->labels["blogRoleLabels"]["guest"])) {
                        $user["author_title"] = esc_html($this->options->getPhrase("wc_blog_role_guest"));
                    }
                }
            }

            if ($user["user"]->ID == $args["post_author"]) {
                $user["commentWrapRoleClass"][] = "wpd-blog-user";
                $user["commentWrapRoleClass"][] = "wpd-blog-post_author";
                if (!empty($this->options->labels["blogRoleLabels"]["post_author"])) {
                    $user["author_title"] = esc_html($this->options->getPhrase("wc_blog_role_post_author"));
                }
            }
        } else {
            $user["commentWrapRoleClass"][] = "wpd-blog-guest";
            if (!empty($this->options->labels["blogRoleLabels"]["guest"])) {
                $user["author_title"] = esc_html($this->options->getPhrase("wc_blog_role_guest"));
            }
        }
    }

    public function getProfileUrl($profile_url, $user) {
        if ($this->options->login["enableProfileURLs"] && $user) {
            if (class_exists("BuddyPress")) {
                $profile_url = self::getBPUserUrl($user->ID);
            } else if (class_exists("UM_API") || class_exists("UM")) {
                um_fetch_user($user->ID);
                $profile_url = um_user_profile_url();
            } else if (function_exists("WPF")) {
                $profile_url = wpforo_member($user->ID, "profile_url");
            }
        }

        return apply_filters("wpdiscuz_profile_url", $profile_url, $user);
    }

    public function umAuthorName($author_name, $comment) {
        if ($comment->user_id) {
            if (class_exists("UM_API") || class_exists("UM")) {
                um_fetch_user($comment->user_id);
                $author_name = um_user("display_name");
                um_reset_user();
            }
        }

        return $author_name;
    }

    public function multipleBlockquotesToOne($content) {
        $content = preg_replace('~<\/blockquote>\s?<blockquote>~is', '</p><p>', $content);
        $content = preg_replace('~<\/code>\s?<code>~is', '</p><p>', $content);

        return $content;
    }

    public static function isUserCanFollowOrSubscribe($email) {
        return !in_array(strstr($email, "@"), [
            "@facebook.com",
            "@twitter.com",
            "@wechat.com",
            "@weibo.com",
            "@baidu.com",
            "@example.com",
        ]);
    }

    public function addRatingResetButton($postType, $post) {
        if (!$post || empty($post->ID)) {
            return;
        }
        $form = $this->wpdiscuzForm->getForm($post->ID);
        if ($form->getFormID() && ($form->getEnableRateOnPost() || $form->getRatingsExists())) {
            add_meta_box("wpd_reset_ratings", __("Reset Ratings", "wpdiscuz"), [
                &$this,
                "resetRatingsButtons"
            ], $postType, "side", "low");
        }
    }

    public function resetRatingsButtons($post) {
        $form       = $this->wpdiscuzForm->getForm($post->ID);
        $ajax_nonce = wp_create_nonce("wpd-reset-rating");
        if ($form->getFormID()) {
            if ($form->getEnableRateOnPost()) {
                ?>
                <script>
                    jQuery(document).ready(function ($) {
                        $(document).on('click', '#wpd_reset_post_rating', function () {
                            if (confirm('<?php esc_html_e("Are you sure you want to reset post rating?") ?>')) {
                                var $this = $(this);
                                $this.prop('disabled', true);
                                $this.next('.wpd_reset_rating_working').show();
                                $.ajax({
                                    url: wpdObject.ajaxUrl,
                                    type: "POST",
                                    data: {
                                        action: 'wpdResetPostRating',
                                        postId: <?php echo $post->ID; ?>,
                                        security: '<?php echo $ajax_nonce; ?>'
                                    }
                                }).done(function (r) {
                                    $this.next('.wpd_reset_rating_working').hide();
                                    if (r.success) {
                                        var sibling = $this.siblings('.wpd_reset_rating_done');
                                        sibling.show();
                                        setTimeout(function () {
                                            sibling.remove();
                                        }, 3000);
                                    }
                                }).fail(function (jqXHR, textStatus, errorThrown) {
                                    console.log(errorThrown);
                                });
                            }
                        });
                    });
                </script>
                <p id="wpd_reset_post_ratings_wrapper">
                    <button type="button" class="button" id="wpd_reset_post_rating"
                            name="wpd_reset_post_rating"><?php esc_html_e("Reset Post Rating", "wpdiscuz"); ?></button>
                    <span class="wpd_reset_rating_working"
                          style="display:none;"><?php esc_html_e("Working...", "wpdiscuz"); ?></span>
                    <span class="wpd_reset_rating_done"
                          style="display:none;color:#10b493;"><?php esc_html_e("Done", "wpdiscuz"); ?></span>
                </p>
                <?php
            }
            if ($form->getRatingsExists()) {
                ?>
                <script>
                    jQuery(document).ready(function ($) {
                        $(document).on('click', '#wpd_reset_fields_ratings', function () {
                            if (confirm('<?php esc_html_e("Are you sure you want to reset fields ratings?") ?>')) {
                                var $this = $(this);
                                $this.prop('disabled', true);
                                $this.next('.wpd_reset_rating_working').show();
                                $.ajax({
                                    url: wpdObject.ajaxUrl,
                                    type: "POST",
                                    data: {
                                        action: 'wpdResetFieldsRatings',
                                        postId: <?php echo $post->ID; ?>,
                                        security: '<?php echo $ajax_nonce; ?>'
                                    }
                                }).done(function (r) {
                                    $this.next('.wpd_reset_rating_working').hide();
                                    if (r.success) {
                                        var sibling = $this.siblings('.wpd_reset_rating_done');
                                        sibling.show();
                                        setTimeout(function () {
                                            sibling.remove();
                                        }, 3000);
                                    }
                                }).fail(function (jqXHR, textStatus, errorThrown) {
                                    console.log(errorThrown);
                                });
                            }
                        });
                    });
                </script>
                <p id="wpd_reset_fields_ratings_wrapper">
                    <button type="button" class="button" id="wpd_reset_fields_ratings"
                            name="wpd_reset_fields_ratings"><?php esc_html_e("Reset Fields Ratings", "wpdiscuz"); ?></button>
                    <span class="wpd_reset_rating_working"
                          style="display:none;"><?php esc_html_e("Working...", "wpdiscuz"); ?></span>
                    <span class="wpd_reset_rating_done"
                          style="display:none;color:#10b493;"><?php esc_html_e("Done", "wpdiscuz"); ?></span>
                </p>
                <?php
            }
        }
    }

    /**
     * init wpdiscuz styles
     */
    public function initCustomCss() {
        ob_start();
        $left                                 = is_rtl() ? "right" : "left";
        $right                                = is_rtl() ? "left" : "right";
        $dark                                 = $this->options->thread_styles["theme"] === "wpd-dark";
        $darkCommentAreaBG                    = $this->options->thread_styles["darkCommentAreaBG"] ? "background:" . $this->options->thread_styles["darkCommentAreaBG"] . ";" : "";
        $darkCommentTextColor                 = $this->options->thread_styles["darkCommentTextColor"] ? "color:" . $this->options->thread_styles["darkCommentTextColor"] . ";" : "";
        $darkCommentFieldsBG                  = $this->options->thread_styles["darkCommentFieldsBG"] ? "background:" . $this->options->thread_styles["darkCommentFieldsBG"] . ";" : "";
        $darkCommentFieldsBorderColor         = $this->options->thread_styles["darkCommentFieldsBorderColor"] ? "border: 1px solid " . $this->options->thread_styles["darkCommentFieldsBorderColor"] . ";" : "";
        $darkCommentFieldsTextColor           = $this->options->thread_styles["darkCommentFieldsTextColor"] ? "color:" . $this->options->thread_styles["darkCommentFieldsTextColor"] . ";" : "";
        $darkCommentFieldsPlaceholderColor    = $this->options->thread_styles["darkCommentFieldsPlaceholderColor"] ? "opacity:1;color:" . $this->options->thread_styles["darkCommentFieldsPlaceholderColor"] . ";" : "";
        $defaultCommentAreaBG                 = $this->options->thread_styles["defaultCommentAreaBG"] ? "background:" . $this->options->thread_styles["defaultCommentAreaBG"] . ";" : "";
        $defaultCommentTextColor              = $this->options->thread_styles["defaultCommentTextColor"] ? "color:" . $this->options->thread_styles["defaultCommentTextColor"] . ";" : "";
        $defaultCommentFieldsBG               = $this->options->thread_styles["defaultCommentFieldsBG"] ? "background:" . $this->options->thread_styles["defaultCommentFieldsBG"] . ";" : "";
        $defaultCommentFieldsBorderColor      = $this->options->thread_styles["defaultCommentFieldsBorderColor"] ? "border: 1px solid " . $this->options->thread_styles["defaultCommentFieldsBorderColor"] . ";" : "";
        $defaultCommentFieldsTextColor        = $this->options->thread_styles["defaultCommentFieldsTextColor"] ? "color:" . $this->options->thread_styles["defaultCommentFieldsTextColor"] . ";" : "";
        $defaultCommentFieldsPlaceholderColor = $this->options->thread_styles["defaultCommentFieldsPlaceholderColor"] ? "opacity:1;color:" . $this->options->thread_styles["defaultCommentFieldsPlaceholderColor"] . ";" : "";
        if ($this->options->thread_styles["theme"] !== "wpd-minimal") {
            $blogRoles = $this->options->labels["blogRoles"];
            if (!$blogRoles) {
                echo ".wc-comment-author a{color:#00B38F;} .wc-comment-label{background:#00B38F;}";
            }
            foreach ($blogRoles as $role => $color) {
                echo "\r\n";
                echo "#wpdcom .wpd-blog-" . esc_html($role) . " .wpd-comment-label{color: #ffffff; background-color: " . esc_html($color) . "; border: none;}\r\n";
                echo "#wpdcom .wpd-blog-" . esc_html($role) . " .wpd-comment-author, #wpdcom .wpd-blog-" . esc_html($role) . " .wpd-comment-author a{color: " . esc_html($color) . ";}\r\n";
                if ($role === "post_author") {
                    echo "#wpdcom .wpd-blog-post_author .wpd-avatar img{border-color: " . esc_html($color) . ";}";
                }
                if ($role !== "subscriber" && $role !== "guest") {
                    echo "#wpdcom.wpd-layout-1 .wpd-comment .wpd-blog-" . esc_html($role) . " .wpd-avatar img{border-color: " . esc_html($color) . ";}\r\n";
                }
                if ($role === "administrator" || $role === "editor" || $role === "post_author") {
                    echo "#wpdcom.wpd-layout-2 .wpd-comment.wpd-reply .wpd-comment-wrap.wpd-blog-" . esc_html($role) . "{border-" . esc_html($left) . ": 3px solid " . esc_html($color) . ";}\r\n";
                }
                if ($role !== "guest") {
                    echo "#wpdcom.wpd-layout-2 .wpd-comment .wpd-blog-" . esc_html($role) . " .wpd-avatar img{border-bottom-color: " . esc_html($color) . ";}\r\n";
                }
                echo "#wpdcom.wpd-layout-3 .wpd-blog-" . esc_html($role) . " .wpd-comment-subheader{border-top: 1px dashed " . esc_html($color) . ";}\r\n";
                if ($role !== "subscriber" && $role !== "guest") {
                    echo "#wpdcom.wpd-layout-3 .wpd-reply .wpd-blog-" . esc_html($role) . " .wpd-comment-right{border-" . esc_html($left) . ": 1px solid " . esc_html($color) . ";}\r\n";
                }
            }
            ?>
            <?php echo ($this->options->thread_styles["commentTextSize"] !== "14px") ? "#wpdcom .wpd-comment-text p{font-size:" . esc_html($this->options->thread_styles["commentTextSize"]) . ";}\r\n" : ""; ?>
            <?php if ($dark) { ?>
                #comments, #respond, .comments-area, #wpdcom.wpd-dark{<?php echo esc_html($darkCommentAreaBG . $darkCommentTextColor) ?>}
                #wpdcom .ql-editor > *{<?php echo esc_html($darkCommentFieldsTextColor) ?>}
                #wpdcom .ql-editor::before{<?php echo esc_html($darkCommentFieldsPlaceholderColor) ?>}
                #wpdcom .ql-toolbar{<?php echo esc_html($darkCommentFieldsBorderColor) ?>border-top:none;}
                #wpdcom .ql-container{<?php echo esc_html($darkCommentFieldsBG . $darkCommentFieldsBorderColor) ?>border-bottom:none;}
                #wpdcom .wpd-form-row .wpdiscuz-item input[type="text"], #wpdcom .wpd-form-row .wpdiscuz-item input[type="email"], #wpdcom .wpd-form-row .wpdiscuz-item input[type="url"], #wpdcom .wpd-form-row .wpdiscuz-item input[type="color"], #wpdcom .wpd-form-row .wpdiscuz-item input[type="date"], #wpdcom .wpd-form-row .wpdiscuz-item input[type="datetime"], #wpdcom .wpd-form-row .wpdiscuz-item input[type="datetime-local"], #wpdcom .wpd-form-row .wpdiscuz-item input[type="month"], #wpdcom .wpd-form-row .wpdiscuz-item input[type="number"], #wpdcom .wpd-form-row .wpdiscuz-item input[type="time"], #wpdcom textarea, #wpdcom select{<?php echo esc_html($darkCommentFieldsBG . $darkCommentFieldsBorderColor . $darkCommentFieldsTextColor) ?>}
                #wpdcom.wpd-dark .wpdiscuz-item.wpd-field-select select.wpdiscuz_select, #wpdcom.wpd-dark select{<?php echo str_replace(';', '!important;', esc_html($darkCommentFieldsBG . $darkCommentFieldsBorderColor . $darkCommentFieldsTextColor)) ?>}
                #wpdcom .wpd-form-row .wpdiscuz-item textarea{<?php echo esc_html($darkCommentFieldsBorderColor) ?>}
                #wpdcom input::placeholder, #wpdcom textarea::placeholder, #wpdcom input::-moz-placeholder, #wpdcom textarea::-webkit-input-placeholder{<?php echo esc_html($darkCommentFieldsPlaceholderColor) ?>}
                #wpdcom .wpd-comment-text{<?php echo esc_html($darkCommentTextColor) ?>}
                .lity-wrap .wpd-item a{color: #666;} .lity-wrap .wpd-item a:hover{color: #222;} .wpd-inline-shortcode.wpd-active{background-color: #666;}
            <?php } else { ?>
                #comments, #respond, .comments-area, #wpdcom{<?php echo esc_html($defaultCommentAreaBG) ?>}
                #wpdcom .ql-editor > *{<?php echo esc_html($defaultCommentFieldsTextColor) ?>}
                #wpdcom .ql-editor::before{<?php echo esc_html($defaultCommentFieldsPlaceholderColor) ?>}
                #wpdcom .ql-toolbar{<?php echo esc_html($defaultCommentFieldsBorderColor) ?>border-top:none;}
                #wpdcom .ql-container{<?php echo esc_html($defaultCommentFieldsBG . $defaultCommentFieldsBorderColor) ?>border-bottom:none;}
                #wpdcom .wpd-form-row .wpdiscuz-item input[type="text"], #wpdcom .wpd-form-row .wpdiscuz-item input[type="email"], #wpdcom .wpd-form-row .wpdiscuz-item input[type="url"], #wpdcom .wpd-form-row .wpdiscuz-item input[type="color"], #wpdcom .wpd-form-row .wpdiscuz-item input[type="date"], #wpdcom .wpd-form-row .wpdiscuz-item input[type="datetime"], #wpdcom .wpd-form-row .wpdiscuz-item input[type="datetime-local"], #wpdcom .wpd-form-row .wpdiscuz-item input[type="month"], #wpdcom .wpd-form-row .wpdiscuz-item input[type="number"], #wpdcom .wpd-form-row .wpdiscuz-item input[type="time"], #wpdcom textarea, #wpdcom select{<?php echo esc_html($defaultCommentFieldsBG . $defaultCommentFieldsBorderColor . $defaultCommentTextColor) ?>}
                #wpdcom .wpd-form-row .wpdiscuz-item textarea{<?php echo esc_html($defaultCommentFieldsBorderColor) ?>}
                #wpdcom input::placeholder, #wpdcom textarea::placeholder, #wpdcom input::-moz-placeholder, #wpdcom textarea::-webkit-input-placeholder{<?php echo esc_html($defaultCommentFieldsPlaceholderColor) ?>}
                #wpdcom .wpd-comment-text{<?php echo esc_html($defaultCommentTextColor) ?>}
            <?php } ?>
            #wpdcom .wpd-thread-head .wpd-thread-info{ border-bottom:2px solid <?php echo esc_html($this->options->thread_styles["primaryColor"]); ?>;}
            #wpdcom .wpd-thread-head .wpd-thread-info.wpd-reviews-tab svg{fill: <?php echo esc_html($this->options->thread_styles["primaryColor"]); ?>;}
            #wpdcom .wpd-thread-head .wpdiscuz-user-settings{border-bottom: 2px solid <?php echo esc_html($this->options->thread_styles["primaryColor"]); ?>;}
            #wpdcom .wpd-thread-head .wpdiscuz-user-settings:hover{color: <?php echo esc_html($this->options->thread_styles["primaryColor"]); ?>;}
            #wpdcom .wpd-comment .wpd-follow-link:hover{color: <?php echo esc_html($this->options->thread_styles["primaryColor"]); ?>;}
            #wpdcom .wpd-comment-status .wpd-sticky{color: <?php echo esc_html($this->options->thread_styles["primaryColor"]); ?>;}
            #wpdcom .wpd-thread-filter .wpdf-active{color: <?php echo esc_html($this->options->thread_styles["primaryColor"]); ?>; border-bottom-color:<?php echo esc_html($this->options->thread_styles["primaryColor"]); ?>;}
            #wpdcom .wpd-comment-info-bar {border: 1px dashed <?php echo esc_html($this->colorBrightness($this->options->thread_styles["primaryColor"], '0.2')); ?>; background: <?php echo esc_html($this->colorBrightness($this->options->thread_styles["primaryColor"], '0.9')); ?>; }
            #wpdcom .wpd-comment-info-bar .wpd-current-view i{color: <?php echo esc_html($this->options->thread_styles["primaryColor"]); ?>;}
            #wpdcom .wpd-filter-view-all:hover{background: <?php echo esc_html($this->options->thread_styles["primaryColor"]); ?>;}
            #wpdcom .wpdiscuz-item .wpdiscuz-rating > label {color: <?php echo esc_html($this->options->rating["ratingInactiveColor"]); ?>;}
            #wpdcom .wpdiscuz-item .wpdiscuz-rating:not(:checked) > label:hover,
            .wpdiscuz-rating:not(:checked) > label:hover ~ label {}
            #wpdcom .wpdiscuz-item .wpdiscuz-rating > input ~ label:hover,
            #wpdcom .wpdiscuz-item .wpdiscuz-rating > input:not(:checked) ~ label:hover ~ label,
            #wpdcom .wpdiscuz-item .wpdiscuz-rating > input:not(:checked) ~ label:hover ~ label{color: <?php echo esc_html($this->options->rating["ratingHoverColor"]); ?>;}
            #wpdcom .wpdiscuz-item .wpdiscuz-rating > input:checked ~ label:hover,
            #wpdcom .wpdiscuz-item .wpdiscuz-rating > input:checked ~ label:hover,
            #wpdcom .wpdiscuz-item .wpdiscuz-rating > label:hover ~ input:checked ~ label,
            #wpdcom .wpdiscuz-item .wpdiscuz-rating > input:checked + label:hover ~ label,
            #wpdcom .wpdiscuz-item .wpdiscuz-rating > input:checked ~ label:hover ~ label, .wpd-custom-field .wcf-active-star,
            #wpdcom .wpdiscuz-item .wpdiscuz-rating > input:checked ~ label{ color:<?php echo esc_html($this->options->rating["ratingActiveColor"]); ?>;}
            #wpd-post-rating .wpd-rating-wrap .wpd-rating-stars svg .wpd-star{fill: <?php echo esc_html($this->options->rating["ratingInactiveColor"]); ?>;}
            #wpd-post-rating .wpd-rating-wrap .wpd-rating-stars svg .wpd-active{fill:<?php echo esc_html($this->options->rating["ratingActiveColor"]); ?>;}
            #wpd-post-rating .wpd-rating-wrap .wpd-rate-starts svg .wpd-star{fill:<?php echo esc_html($this->options->rating["ratingInactiveColor"]); ?>;}
            #wpd-post-rating .wpd-rating-wrap .wpd-rate-starts:hover svg .wpd-star{fill:<?php echo esc_html($this->options->rating["ratingHoverColor"]); ?>;}
            #wpd-post-rating.wpd-not-rated .wpd-rating-wrap .wpd-rate-starts svg:hover ~ svg .wpd-star{ fill:<?php echo esc_html($this->options->rating["ratingInactiveColor"]); ?>;}
            .wpdiscuz-post-rating-wrap .wpd-rating .wpd-rating-wrap .wpd-rating-stars svg .wpd-star{fill:<?php echo esc_html($this->options->rating["ratingInactiveColor"]); ?>;}
            .wpdiscuz-post-rating-wrap .wpd-rating .wpd-rating-wrap .wpd-rating-stars svg .wpd-active{fill:<?php echo esc_html($this->options->rating["ratingActiveColor"]); ?>;}
            #wpdcom .wpd-comment .wpd-follow-active{color:#ff7a00;}
            #wpdcom .page-numbers{color:#555;border:#555 1px solid;}
            #wpdcom span.current{background:#555;}
            #wpdcom.wpd-layout-1 .wpd-new-loaded-comment > .wpd-comment-wrap > .wpd-comment-right{background:<?php echo esc_html($this->options->thread_styles["newLoadedCommentBGColor"]); ?>;}
            #wpdcom.wpd-layout-2 .wpd-new-loaded-comment.wpd-comment > .wpd-comment-wrap > .wpd-comment-right{background:<?php echo esc_html($this->options->thread_styles["newLoadedCommentBGColor"]); ?>;}
            #wpdcom.wpd-layout-2 .wpd-new-loaded-comment.wpd-comment.wpd-reply > .wpd-comment-wrap > .wpd-comment-right{background:transparent;}
            #wpdcom.wpd-layout-2 .wpd-new-loaded-comment.wpd-comment.wpd-reply > .wpd-comment-wrap {background:<?php echo esc_html($this->options->thread_styles["newLoadedCommentBGColor"]); ?>;}
            #wpdcom.wpd-layout-3 .wpd-new-loaded-comment.wpd-comment > .wpd-comment-wrap > .wpd-comment-right{background:<?php echo esc_html($this->options->thread_styles["newLoadedCommentBGColor"]); ?>;}
            #wpdcom .wpd-follow:hover i, #wpdcom .wpd-unfollow:hover i, #wpdcom .wpd-comment .wpd-follow-active:hover i{color:<?php echo esc_html($this->options->thread_styles["primaryColor"]); ?>;}
            #wpdcom .wpdiscuz-readmore{cursor:pointer;color:<?php echo esc_html($this->options->thread_styles["primaryColor"]); ?>;}
            .wpd-custom-field .wcf-pasiv-star, #wpcomm .wpdiscuz-item .wpdiscuz-rating > label {color: <?php echo esc_html($this->options->rating["ratingInactiveColor"]); ?>;}
            .wpd-wrapper .wpd-list-item.wpd-active{border-top: 3px solid <?php echo esc_html($this->options->thread_styles["primaryColor"]); ?>;}
            #wpdcom.wpd-layout-2 .wpd-comment.wpd-reply.wpd-unapproved-comment .wpd-comment-wrap{border-<?php echo esc_html($left) ?>: 3px solid <?php echo esc_html($this->options->thread_styles["newLoadedCommentBGColor"]); ?>;}
            #wpdcom.wpd-layout-3 .wpd-comment.wpd-reply.wpd-unapproved-comment .wpd-comment-right{border-<?php echo esc_html($left) ?>: 1px solid <?php echo esc_html($this->options->thread_styles["newLoadedCommentBGColor"]); ?>;}
            #wpdcom .wpd-prim-button{background-color: <?php echo esc_html($this->options->thread_styles["primaryButtonBG"]); ?>; color: <?php echo esc_html($this->options->thread_styles["primaryButtonColor"]); ?>;}
            #wpdcom .wpd_label__check i.wpdicon-on{color: <?php echo esc_html($this->options->thread_styles["primaryButtonBG"]); ?>; border: 1px solid <?php echo esc_html($this->colorBrightness($this->options->thread_styles["primaryButtonBG"], 0.5)); ?>;}
            #wpd-bubble-wrapper #wpd-bubble-all-comments-count{color:<?php echo esc_html($this->options->thread_styles["bubbleColors"]); ?>;}
            #wpd-bubble-wrapper > div{background-color:<?php echo esc_html($this->options->thread_styles["bubbleColors"]); ?>;}
            #wpd-bubble-wrapper > #wpd-bubble #wpd-bubble-add-message{background-color:<?php echo esc_html($this->options->thread_styles["bubbleColors"]); ?>;}
            #wpd-bubble-wrapper > #wpd-bubble #wpd-bubble-add-message::before{border-left-color:<?php echo esc_html($this->options->thread_styles["bubbleColors"]); ?>; border-right-color:<?php echo esc_html($this->options->thread_styles["bubbleColors"]); ?>;}
            #wpd-bubble-wrapper.wpd-right-corner > #wpd-bubble #wpd-bubble-add-message::before{border-left-color:<?php echo esc_html($this->options->thread_styles["bubbleColors"]); ?>; border-right-color:<?php echo esc_html($this->options->thread_styles["bubbleColors"]); ?>;}
            .wpd-inline-icon-wrapper path.wpd-inline-icon-first{fill:<?php echo esc_html($this->options->thread_styles["inlineFeedbackColors"]); ?>;}
            .wpd-inline-icon-count{background-color:<?php echo esc_html($this->options->thread_styles["inlineFeedbackColors"]); ?>;}
            .wpd-inline-icon-count::before{border-<?php echo esc_html($right) ?>-color:<?php echo esc_html($this->options->thread_styles["inlineFeedbackColors"]); ?>;}
            .wpd-inline-form-wrapper::before{border-bottom-color:<?php echo esc_html($this->options->thread_styles["inlineFeedbackColors"]); ?>;}
            .wpd-inline-form-question{background-color:<?php echo esc_html($this->options->thread_styles["inlineFeedbackColors"]); ?>;}
            .wpd-inline-form{background-color:<?php echo esc_html($this->options->thread_styles["inlineFeedbackColors"]); ?>;}
            .wpd-last-inline-comments-wrapper{border-color:<?php echo esc_html($this->options->thread_styles["inlineFeedbackColors"]); ?>;}
            .wpd-last-inline-comments-wrapper::before{border-bottom-color:<?php echo esc_html($this->options->thread_styles["inlineFeedbackColors"]); ?>;}
            .wpd-last-inline-comments-wrapper .wpd-view-all-inline-comments{background:<?php echo esc_html($this->options->thread_styles["inlineFeedbackColors"]); ?>;}
            .wpd-last-inline-comments-wrapper .wpd-view-all-inline-comments:hover,.wpd-last-inline-comments-wrapper .wpd-view-all-inline-comments:active,.wpd-last-inline-comments-wrapper .wpd-view-all-inline-comments:focus{background-color:<?php echo esc_html($this->options->thread_styles["inlineFeedbackColors"]); ?>;}
            <?php
        }
        ?>
        #wpdcom .ql-snow .ql-tooltip[data-mode="link"]::before{content:"<?php esc_html_e("Enter link:", "wpdiscuz"); ?>";}
        #wpdcom .ql-snow .ql-tooltip.ql-editing a.ql-action::after{content:"<?php esc_html_e("Save", "wpdiscuz"); ?>";}
        <?php
        do_action("wpdiscuz_dynamic_css", $this->options);
        if ($this->options->thread_styles["theme"] !== "wpd-minimal") {
            echo stripslashes($this->options->thread_styles["customCss"]);
        }
        $css = ob_get_clean();
        /* xMinfy Star ********************************************************* */
        if (apply_filters("wpdiscuz_minify_inline_css", true)) {
            $css = preg_replace('/\/\*((?!\*\/).)*\*\//', "", $css);
            $css = preg_replace('/\s{2,}/', " ", $css);
            $css = preg_replace('/\s*([:;{}])\s*/', "$1", $css);
            $css = preg_replace('/;}/', "}", $css);
        }

        /* xMinify End ********************************************************* */

        return $css;
    }

    /**
     * Increases or decreases the brightness of a color by a percentage of the current brightness.
     *
     * @param string $hexCode Supported formats: `#FFF`, `#FFFFFF`, `FFF`, `FFFFFF`
     * @param float $adjustPercent A number between -1 and 1. E.g. 0.3 = 30% lighter; -0.4 = 40% darker.
     *
     * @return  string
     */
    public function colorBrightness($hexCode, $adjustPercent = 1) {
        if (!$hexCode) {
            return '#000';
        }
        $hexCode = ltrim($hexCode, '#');
        if (strlen($hexCode) == 3) {
            $hexCode = $hexCode[0] . $hexCode[0] . $hexCode[1] . $hexCode[1] . $hexCode[2] . $hexCode[2];
        }
        $hexCode = array_map('hexdec', str_split($hexCode, 2));
        foreach ($hexCode as & $color) {
            $adjustableLimit = $adjustPercent < 0 ? $color : 255 - $color;
            $adjustAmount    = ceil($adjustableLimit * $adjustPercent);

            $color = str_pad(dechex($color + $adjustAmount), 2, '0', STR_PAD_LEFT);
        }

        return '#' . implode($hexCode);
    }

    public static function sanitize($action, $variable_name, $filter, $default = "") {
        if ($filter === "FILTER_SANITIZE_STRING") {
            $glob = INPUT_POST === $action ? $_POST : $_GET;
            if (key_exists($variable_name, $glob)) {
                return sanitize_text_field($glob[$variable_name]);
            } else {
                return $default;
            }
        }
        $variable = filter_input($action, $variable_name, $filter);

        return $variable ? $variable : $default;
    }

    public function handleCommentSubmission($post, $comment_parent, $isNewComment = true) {

        if (!$post) {
            return new WP_Error("post_not_found", __("Current post doesn't found.", "wpdiscuz"));
        }

        $_post = get_post($post);

        if (!$_post) {
            return new WP_Error("post_not_found", __("Current post doesn't found.", "wpdiscuz"));
        }

        $comment_post_id = $_post->ID;

        if ($comment_parent) {
            $comment_parent        = absint($comment_parent);
            $comment_parent_object = get_comment($comment_parent);
            if (
                0 !== $comment_parent &&
                (
                    !$comment_parent_object instanceof WP_Comment ||
                    0 === (int)$comment_parent_object->comment_approved
                )
            ) {
                do_action("comment_reply_to_unapproved_comment", $comment_post_id, $comment_parent);

                return new WP_Error("comment_reply_to_unapproved_comment", __("Sorry, replies to unapproved comments are not allowed."), 403);
            }
        }


        if (empty($_post->comment_status)) {

            do_action("comment_id_not_found", $comment_post_id);

            return new WP_Error("comment_id_not_found", __("Current post doesn't found.", "wpdiscuz"));
        }

        $status = get_post_status($_post);

        if (("private" === $status) && !current_user_can("read_post", $comment_post_id)) {
            return new WP_Error("comment_id_not_found", __("Current post doesn't found.", "wpdiscuz"));
        }

        $status_obj = get_post_status_object($status);

        if (!comments_open($comment_post_id)) {

            do_action("comment_closed", $comment_post_id);

            return new WP_Error("comment_closed", __("Sorry, comments are closed for this item.", "wpdiscuz"), 403);
        } elseif ("trash" === $status) {

            do_action("comment_on_trash", $comment_post_id);

            return new WP_Error("comment_on_trash", __("Current post doesn't found.", "wpdiscuz"));
        } elseif (!$status_obj->public && !$status_obj->private) {

            do_action("comment_on_draft", $comment_post_id);

            if (current_user_can("read_post", $comment_post_id)) {
                return new WP_Error("comment_on_draft", __("Sorry, comments are not allowed for this item.", "wpdiscuz"), 403);
            } else {
                return new WP_Error('comment_on_draft', __("Current post doesn't found.", "wpdiscuz"));
            }
        } elseif (post_password_required($comment_post_id)) {

            do_action('comment_on_password_protected', $comment_post_id);

            return new WP_Error('comment_on_password_protected', __("Sorry, comments are not allowed for this item.", "wpdiscuz"));
        }

        if ($isNewComment) {

            do_action('pre_comment_on_post', $comment_post_id);
        }

        return true;
    }

    public function updatePostAuthorsTrs($post_id) {
        set_transient(self::TRS_POSTS_AUTHORS, null);
        $this->dbManager->getPostsAuthors();
    }

    public static function getBPUserUrl($user_id) {
        return function_exists('bp_members_get_user_url') ? bp_members_get_user_url($user_id) : bp_core_get_user_domain($user_id);
    }

    /**
     * @param $user WP_User
     * @return WP_User
     */
    public static function filterUser($user) {
        if (isset($user->data->user_pass)) {
            unset($user->data->user_pass);
        }
        return $user;
    }

}
