<?php

//mimic the actuall admin-ajax
define("DOING_AJAX", true);
$wpdiscuz_ajax_action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
if (!$wpdiscuz_ajax_action) {
    die('-1');
}
$ABSPATH = wpdiscuz_ABSPATH();
require_once($ABSPATH . "wp-load.php");

header("Content-Type: text/html");
send_nosniff_header();

header("Cache-Control: no-cache");
header("Pragma: no-cache");

$wpdiscuz             = wpDiscuz();
$helper               = $wpdiscuz->helper;
$helperAjax           = $wpdiscuz->helperAjax;
$helperEmail          = $wpdiscuz->helperEmail;
$helperUpload         = $wpdiscuz->helperUpload;
$wpdiscuz_ajax_action = esc_attr(trim($wpdiscuz_ajax_action));
$allowedActions       = [
    "wpdLoadMoreComments"        => ["object" => $wpdiscuz, "callback" => "loadMoreComments", "for" => "all"],
    "wpdSorting"                 => ["object" => $wpdiscuz, "callback" => "sorting", "for" => "all"],
    "wpdAddComment"              => ["object" => $wpdiscuz, "callback" => "addComment", "for" => "all"],
    "wpdGetSingleComment"        => ["object" => $wpdiscuz, "callback" => "getSingleComment", "for" => "all"],
    "wpdSaveEditedComment"       => ["object" => $wpdiscuz, "callback" => "saveEditedComment", "for" => "all"],
    "wpdUpdateAutomatically"     => ["object" => $wpdiscuz, "callback" => "updateAutomatically", "for" => "all"],
    "wpdShowReplies"             => ["object" => $wpdiscuz, "callback" => "showReplies", "for" => "all"],
    "wpdMostReactedComment"      => ["object" => $wpdiscuz, "callback" => "mostReactedComment", "for" => "all"],
    "wpdHottestThread"           => ["object" => $wpdiscuz, "callback" => "hottestThread", "for" => "all"],
    "wpdBubbleUpdate"            => ["object" => $wpdiscuz, "callback" => "bubbleUpdate", "for" => "all"],
    "wpdAddInlineComment"        => ["object" => $wpdiscuz, "callback" => "addInlineComment", "for" => "all"],
    "wpdGetInfo"                 => ["object" => $helper, "callback" => "wpdGetInfo", "for" => "all"],
    "wpdGetActivityPage"         => ["object" => $helper, "callback" => "getActivityPage", "for" => "all"],
    "wpdGetSubscriptionsPage"    => ["object" => $helper, "callback" => "getSubscriptionsPage", "for" => "all"],
    "wpdGetFollowsPage"          => ["object" => $helper, "callback" => "getFollowsPage", "for" => "all"],
    "wpdVoteOnComment"           => ["object" => $helperAjax, "callback" => "voteOnComment", "for" => "all"],
    "wpdRedirect"                => ["object" => $helperAjax, "callback" => "redirect", "for" => "all"],
    "wpdEditComment"             => ["object" => $helperAjax, "callback" => "editComment", "for" => "all"],
    "wpdReadMore"                => ["object" => $helperAjax, "callback" => "readMore", "for" => "all"],
    "wpdDeleteComment"           => ["object" => $helperAjax, "callback" => "deleteComment", "for" => "user"],
    "wpdCancelSubscription"      => ["object" => $helperAjax, "callback" => "deleteSubscription", "for" => "user"],
    "wpdCancelFollow"            => ["object" => $helperAjax, "callback" => "deleteFollow", "for" => "user"],
    "wpdGuestAction"             => ["object" => $helperAjax, "callback" => "guestAction", "for" => "guest"],
    "wpdStickComment"            => ["object" => $helperAjax, "callback" => "stickComment", "for" => "user"],
    "wpdCloseThread"             => ["object" => $helperAjax, "callback" => "closeThread", "for" => "user"],
    "wpdFollowUser"              => ["object" => $helperAjax, "callback" => "followUser", "for" => "user"],
    "wpdGetLastInlineComments"   => ["object" => $helperAjax, "callback" => "getLastInlineComments", "for" => "all"],
    "wpdGetInlineCommentForm"    => ["object" => $helperAjax, "callback" => "getInlineCommentForm", "for" => "all"],
    "wpdUnsubscribe"             => ["object" => $helperAjax, "callback" => "unsubscribe", "for" => "all"],
    "wpdUserRate"                => ["object" => $helperAjax, "callback" => "userRate", "for" => "all"],
    "wpdEmailDeleteLinks"        => ["object" => $helperEmail, "callback" => "emailDeleteLinksAction", "for" => "user"],
    "wpdAddSubscription"         => ["object" => $helperEmail, "callback" => "addSubscription", "for" => "all"],
    "wpdCheckNotificationType"   => ["object" => $helperEmail, "callback" => "checkNotificationType", "for" => "all"],
    "wmuUploadFiles"             => ["object" => $helperUpload, "callback" => "uploadFiles", "for" => "all"],
    "wmuRemoveAttachmentPreview" => ["object" => $helperUpload, "callback" => "removeAttachmentPreview", "for" => "all"],
    "wmuDeleteAttachment"        => ["object" => $helperUpload, "callback" => "deleteAttachment", "for" => "all"],
];

$allowedActions = apply_filters("wpdiscuz_custom_ajax_allowed_actions", $allowedActions);

foreach ($allowedActions as $action => $data) {
    if ($data["for"] === "user") {
        add_action("wpdiscuz_" . $action, [$data["object"], $data["callback"]]);
    } else if ($data["for"] === "guest") {
        add_action("wpdiscuz_nopriv_" . $action, [$data["object"], $data["callback"]]);
    } else {
        add_action("wpdiscuz_" . $action, [$data["object"], $data["callback"]]);
        add_action("wpdiscuz_nopriv_" . $action, [$data["object"], $data["callback"]]);
    }
}


if (array_key_exists($wpdiscuz_ajax_action, $allowedActions)) {
    if (is_user_logged_in()) {
        do_action("wpdiscuz_" . $wpdiscuz_ajax_action);
    } else {
        do_action("wpdiscuz_nopriv_" . $wpdiscuz_ajax_action);
    }
} else {
    die("-1");
}

function wpdiscuz_ABSPATH() {
    $path = join(DIRECTORY_SEPARATOR, ["wp-content", "plugins", "wpdiscuz", "utils", "ajax"]);

    return str_replace($path, "", __DIR__);
}
