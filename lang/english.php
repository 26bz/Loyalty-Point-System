<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

// Client area navigation
$_LANG['loyaltypoints'] = "Loyalty Points";

// Addon language strings
$_ADDONLANG['title'] = "Loyalty Points System";
$_ADDONLANG['description'] = "Reward customers with points for various actions including invoice payments, affiliate signups, and more.";

// Admin area
$_ADDONLANG['settings_title'] = "Current Settings";
$_ADDONLANG['metrics_title'] = "System Metrics";
$_ADDONLANG['total_points'] = "Total Points";
$_ADDONLANG['active_users'] = "Active Users";
$_ADDONLANG['monthly_activity'] = "Monthly Activity";
$_ADDONLANG['adjust_points'] = "Adjust Points";
$_ADDONLANG['select_client'] = "Select Client...";
$_ADDONLANG['points_amount'] = "Points (+/-)";
$_ADDONLANG['description_field'] = "Description";
$_ADDONLANG['update_button'] = "Update";
$_ADDONLANG['client_overview'] = "Client Points Overview";
$_ADDONLANG['points_history'] = "Points History";
$_ADDONLANG['no_points_data'] = "No client points data available yet.";

// Client area
$_ADDONLANG['points_balance'] = "Points Balance";
$_ADDONLANG['available_points'] = "Available Points";
$_ADDONLANG['total_earned'] = "Total Earned";
$_ADDONLANG['total_redeemed'] = "Total Redeemed";
$_ADDONLANG['available_credit'] = "Available Credit";
$_ADDONLANG['redeem_points'] = "Redeem Points";
$_ADDONLANG['points_to_redeem'] = "Points to Redeem";
$_ADDONLANG['min_redemption'] = "Minimum redemption";
$_ADDONLANG['conversion_rate'] = "Conversion rate";
$_ADDONLANG['redeem_button'] = "Redeem for Credit";
$_ADDONLANG['ways_to_earn'] = "Ways to Earn Points";
$_ADDONLANG['redemption_benefits'] = "Redemption Benefits";

// Earning methods
$_ADDONLANG['earn_invoice'] = "Pay invoices on time";
$_ADDONLANG['earn_anniversary'] = "Annual account anniversary";
$_ADDONLANG['earn_order'] = "Place new service orders";
$_ADDONLANG['earn_affiliate'] = "Refer new customers";

// Benefits
$_ADDONLANG['benefit_credit_title'] = "Account Credit";
$_ADDONLANG['benefit_credit_desc'] = "Convert points to credit";
$_ADDONLANG['benefit_discount_title'] = "Service Discounts";
$_ADDONLANG['benefit_discount_desc'] = "Reduce service costs";

// Messages
$_ADDONLANG['success_redemption'] = "Successfully redeemed {points} points";
$_ADDONLANG['error_min_points'] = "Minimum redemption amount is {min} points";
$_ADDONLANG['error_insufficient'] = "Insufficient points balance";
$_ADDONLANG['error_rate_limit'] = "Please wait before making another redemption request";
$_ADDONLANG['error_suspicious'] = "This redemption has been flagged for review";
$_ADDONLANG['error_select_client'] = "Please select a client";
$_ADDONLANG['error_enter_points'] = "Please enter points value";
$_ADDONLANG['error_description'] = "Please provide a description";
$_ADDONLANG['error_invalid_client'] = "Selected client does not exist";

// Table headers
$_ADDONLANG['header_date'] = "Date";
$_ADDONLANG['header_description'] = "Description";
$_ADDONLANG['header_points'] = "Points";
$_ADDONLANG['header_client'] = "Client";
$_ADDONLANG['header_email'] = "Email";
$_ADDONLANG['header_last_updated'] = "Last Updated";

// Settings labels
$_ADDONLANG['setting_points_invoice'] = "Points per Invoice";
$_ADDONLANG['setting_points_order'] = "Points per Order";
$_ADDONLANG['setting_points_affiliate'] = "Affiliate Points";
$_ADDONLANG['setting_min_redemption'] = "Minimum Redemption";
$_ADDONLANG['setting_conversion'] = "Conversion Rate";
$_ADDONLANG['setting_expiry'] = "Points Expiry";
$_ADDONLANG['setting_anniversary'] = "Anniversary Points";
$_ADDONLANG['setting_suspension'] = "Suspension Penalty";
$_ADDONLANG['setting_free_orders'] = "Free Orders";
$_ADDONLANG['text_excluded'] = "Excluded";
$_ADDONLANG['text_included'] = "Included";
$_ADDONLANG['text_never'] = "Never";
$_ADDONLANG['text_days'] = "days";

// Additional admin labels
$_ADDONLANG['point_value'] = "Point Value";
$_ADDONLANG['points_expiry'] = "Points Expiry";
$_ADDONLANG['anniversary_bonus'] = "Anniversary Bonus";
$_ADDONLANG['suspension_penalty'] = "Suspension Penalty";
$_ADDONLANG['free_orders'] = "Free Orders";
$_ADDONLANG['points'] = "Points";
$_ADDONLANG['description'] = "Description";
$_ADDONLANG['update'] = "Update";
$_ADDONLANG['client'] = "Client";
$_ADDONLANG['last_updated'] = "Last Updated";
$_ADDONLANG['no_client_points_data'] = "No client points data available yet.";
$_ADDONLANG['client_points_overview'] = "Client Points Overview";

// Access control
$_ADDONLANG['access_denied'] = "Access Denied";
$_ADDONLANG['login_required'] = "Please log in to access your loyalty points.";

// Upgrade messages
$_ADDONLANG['upgrade_success'] = "Loyalty Points module upgraded successfully to version {version}";
$_ADDONLANG['upgrade_error'] = "Error during upgrade: {error}";
$_ADDONLANG['deactivate_preserved'] = "Loyalty Points module deactivated successfully. Note: Points data has been preserved and will be available upon reactivation.";
