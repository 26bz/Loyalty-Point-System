<?php

/**
 * @author     26BZ
 * @license    MIT License
 * @copyright  (c) 2025 26BZ - https://26bz.online/
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;
use WHMCS\User\Client;
use WHMCS\Authentication\CurrentUser;

function loyalty_points_client_output($vars)
{
    $currentUser = new CurrentUser();
    $client = $currentUser->client();

    $action = filter_var($_REQUEST['a'] ?? '', FILTER_SANITIZE_STRING);

    if (!$client) {
        return [
            'pagetitle' => 'Loyalty Points - Access Required',
            'templatefile' => 'clienthome',
            'requirelogin' => true,
            'vars' => ['error' => 'Please log in to access your loyalty points dashboard.']
        ];
    }

    if ($action === 'redeem') {
        return handleRedemption($client->id, $vars);
    }

    try {
        $clientData = getClientData($client->id, $vars);

        $templateVars = [
            'points_balance' => $clientData['points'],
            'points_value' => $clientData['statistics']['available_credit'],
            'modulelink' => $vars['modulelink'],
            'token' => generate_token(),
            'history' => $clientData['history'],
            'min_redemption' => $vars['min_redemption_points'] ?? 100,
            'earning_methods' => [
                (object)['text' => 'Pay invoices on time', 'points' => $vars['points_per_invoice'] ?? 50],
                (object)['text' => 'Annual account anniversary', 'points' => $vars['points_anniversary'] ?? 100],
                (object)['text' => 'Place new service orders', 'points' => $vars['points_per_order'] ?? 20],
                (object)['text' => 'Join affiliate program', 'points' => $vars['points_for_affiliate'] ?? 100]
            ]
        ];

        if (isset($_SESSION['loyalty_points_success'])) {
            $templateVars['success'] = $_SESSION['loyalty_points_success'];
            unset($_SESSION['loyalty_points_success']);
        }
        if (isset($_SESSION['loyalty_points_error'])) {
            $templateVars['error'] = $_SESSION['loyalty_points_error'];
            unset($_SESSION['loyalty_points_error']);
        }

        return [
            'pagetitle' => 'Loyalty Points Dashboard',
            'breadcrumb' => ['index.php?m=loyalty_points' => 'Loyalty Points'],
            'templatefile' => 'clienthome',
            'requirelogin' => true,
            'vars' => $templateVars
        ];
    } catch (Exception $e) {
        logActivity("Loyalty Points Client Area Error: " . $e->getMessage(), $client->id);
        return [
            'templatefile' => 'templates/client/clienthome',  // path
            'vars' => ['error' => $e->getMessage()]
        ];
    }
}

function getClientData($client_id, $vars)
{
    $points = Capsule::table('mod_loyalty_points')
        ->where('client_id', $client_id)
        ->value('points') ?? 0;
    $history = Capsule::table('mod_loyalty_points_log')
        ->where('client_id', $client_id)
        ->select(['date', 'points', 'description', 'expiry_date'])
        ->orderBy('date', 'desc')
        ->get();

    $rejectedRedemptions = Capsule::table('mod_loyalty_points_redemption_attempts')
        ->where('client_id', $client_id)
        ->where('mod_loyalty_points_redemption_attempts.status', 'rejected')
        ->select(['timestamp as date', 'points', 'admin_notes'])
        ->orderBy('timestamp', 'desc')
        ->get();

    $combinedHistory = [];

    foreach ($history as $item) {
        $combinedHistory[] = [
            'type' => 'log',
            'date' => $item->date,
            'timestamp' => strtotime($item->date),
            'points' => $item->points,
            'description' => $item->description,
            'expiry_date' => $item->expiry_date ?? null
        ];
    }

    foreach ($rejectedRedemptions as $rejection) {
        $combinedHistory[] = [
            'type' => 'rejected',
            'date' => $rejection->date,
            'timestamp' => strtotime($rejection->date),
            'points' => $rejection->points,
            'description' => 'Redemption request rejected',
            'admin_notes' => $rejection->admin_notes,
            'expiry_date' => null
        ];
    }

    usort($combinedHistory, function ($a, $b) {
        return $b['timestamp'] - $a['timestamp'];
    });

    $monthStart = toMySQLDate(getTodaysDate()); // First of current month
    $monthlyActivity = $history
        ->where('date', '>=', $monthStart)
        ->sum('points');

    $currency = getCurrency($client_id);
    $baseCurrency = getCurrency(null, $vars['base_currency']);
    $baseCreditAmount = $points * $vars['conversion_rate'];
    $creditAmount = convertCurrency($baseCreditAmount, $baseCurrency['id'], $currency['id']);

    return [
        'points' => $points,
        'history' => $combinedHistory,
        'statistics' => [
            'total_earned' => $history->where('points', '>', 0)->sum('points'),
            'total_redeemed' => abs($history->where('points', '<', 0)->sum('points')),
            'monthly_activity' => $monthlyActivity,
            'available_credit' => formatCurrency($creditAmount, $currency['id'])
        ],
        'currency' => $currency
    ];
}

function handleRedemption($client_id, $vars)
{
    try {
        $currency = getCurrency($client_id);
        check_token("WHMCS.default");
        if (isRateLimited($client_id)) {
            logActivity("Points redemption rate limit hit", $client_id);
            $_SESSION['loyalty_points_error'] = "Please wait before making another redemption request";
            header('Location: index.php?m=loyalty_points');
            exit;
        }

        try {
            if (!Capsule::schema()->hasTable('mod_loyalty_points_redemption_attempts')) {
                create_redemption_attempts_table();
            }
        } catch (Exception $e) {
            logModuleCall(
                'loyalty_points',
                'create_redemption_attempts_table',
                [],
                $e->getMessage(),
                null,
                []
            );
            logActivity("Redemption security table creation failed", $client_id);
        }

        $points = (int) $_POST['points_to_redeem'];
        if ($points < $vars['min_redemption_points']) {
            logActivity("Points redemption attempt below minimum: {$points} points", $client_id);
            $_SESSION['loyalty_points_error'] = "Minimum redemption amount is {$vars['min_redemption_points']} points";
            header('Location: index.php?m=loyalty_points');
            exit;
        }

        $current_points = Capsule::table('mod_loyalty_points')
            ->where('client_id', $client_id)
            ->lockForUpdate()
            ->value('points');

        if ($points > $current_points) {
            logActivity("Insufficient points balance for redemption: {$points} requested, {$current_points} available", $client_id);
            $_SESSION['loyalty_points_error'] = "Insufficient points balance";
            header('Location: index.php?m=loyalty_points');
            exit;
        }

        if (isSuspiciousActivity($client_id, $points)) {
            logActivity("Suspicious points redemption attempt detected - Points: {$points}", $client_id);

            Capsule::table('mod_loyalty_points_redemption_attempts')->insert([
                'client_id' => $client_id,
                'points' => $points,
                'status' => 'flagged',
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                'timestamp' => date('Y-m-d H:i:s')
            ]);

            $_SESSION['loyalty_points_error'] = "This redemption has been flagged for review by our team. You will be notified once it's approved.";
            header('Location: index.php?m=loyalty_points');
            exit;
        }

        $baseCurrency = getCurrency(null, $vars['base_currency']);
        $baseCreditAmount = $points * $vars['conversion_rate'];
        $credit = convertCurrency($baseCreditAmount, $baseCurrency['id'], $currency['id']);

        Capsule::beginTransaction();

        try {
            recordRedemptionAttempt($client_id, $points);
            Capsule::table('tblclients')
                ->where('id', $client_id)
                ->increment('credit', $credit);
            Capsule::table('mod_loyalty_points')
                ->where('client_id', $client_id)
                ->decrement('points', $points);
            Capsule::table('mod_loyalty_points_log')->insert([
                'client_id' => $client_id,
                'points' => -$points,
                'description' => "Points redeemed for credit (" . formatCurrency($credit, $currency['id']) . ")",
                'date' => toMySQLDate(getTodaysDate()),
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT']
            ]);

            Capsule::commit();
            logActivity("Successfully redeemed {$points} points for " . formatCurrency($credit, $currency['id']) . " credit", $client_id);

            updateRateLimit($client_id);

            $_SESSION['loyalty_points_success'] = "Successfully redeemed {$points} points";
            header('Location: index.php?m=loyalty_points');
            exit;
        } catch (Exception $e) {
            Capsule::rollBack();
            logActivity("Points redemption transaction failed: " . $e->getMessage(), $client_id);
            throw $e;
        }
    } catch (Exception $e) {
        $_SESSION['loyalty_points_error'] = $e->getMessage();
        header('Location: index.php?m=loyalty_points');
        exit;
    }
}

function isRateLimited($client_id)
{
    $fiveMinutesAgo = date('Y-m-d H:i:s', strtotime('-5 minutes'));
    $lastRedemption = Capsule::table('mod_loyalty_points_log')
        ->where('client_id', $client_id)
        ->where('points', '<', 0)  // Redemptions are negative points
        ->where('description', 'LIKE', '%redeemed for credit%')  // Only check actual redemptions
        ->where('date', '>=', $fiveMinutesAgo)
        ->first();

    return $lastRedemption ? true : false;
}

function isSuspiciousActivity($client_id, $points)
{
    $pendingFlagged = Capsule::table('mod_loyalty_points_redemption_attempts')
        ->where('client_id', $client_id)
        ->where('status', 'flagged')
        ->count();

    if ($pendingFlagged > 0) {
        logActivity("Redemption attempt while previous flagged redemption is pending review", $client_id);
        return true;
    }

    $oneDayAgo = date('Y-m-d H:i:s', strtotime('-1 day'));
    $failedAttempts = Capsule::table('mod_loyalty_points_redemption_attempts')
        ->where('client_id', $client_id)
        ->where('status', 'failed')
        ->where('timestamp', '>=', $oneDayAgo)
        ->count();

    if ($failedAttempts >= 5) {
        logActivity("Multiple failed redemption attempts detected", $client_id);
        return true;
    }

    $thirtyDaysAgo = date('Y-m-d H:i:s', strtotime('-30 days'));
    $avgRedemption = Capsule::table('mod_loyalty_points_log')
        ->where('client_id', $client_id)
        ->where('points', '<', 0)
        ->where('date', '>=', $thirtyDaysAgo)
        ->avg('points');

    if ($avgRedemption && abs($points) > abs($avgRedemption * 3)) {
        logActivity("Unusual redemption amount detected: {$points} points (3x average)", $client_id);
        return true;
    }

    $uniqueIPs = Capsule::table('mod_loyalty_points_log')
        ->where('client_id', $client_id)
        ->where('date', '>=', $oneDayAgo)
        ->distinct()
        ->count('ip_address');

    if ($uniqueIPs > 3) {
        logActivity("Multiple IP addresses detected for redemptions", $client_id);
        return true;
    }

    return false;
}

function recordRedemptionAttempt($client_id, $points)
{
    Capsule::table('mod_loyalty_points_redemption_attempts')->insert([
        'client_id' => $client_id,
        'points' => $points,
        'status' => 'pending',
        'ip_address' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT'],
        'timestamp' => toMySQLDate(getTodaysDate(true))
    ]);
}

function updateRateLimit($client_id)
{
    Capsule::table('mod_loyalty_points_redemption_attempts')
        ->where('client_id', $client_id)
        ->where('status', 'pending')
        ->update(['status' => 'completed']);
}
