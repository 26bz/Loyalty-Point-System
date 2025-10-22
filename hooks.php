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
use WHMCS\Language\AbstractLanguage as Lang;

function loyalty_points_invoice_paid($vars)
{
    try {
        $settings = getModuleSettings();

        if (empty($settings)) {
            return;
        }

        $invoice = Capsule::table('tblinvoices')
            ->where('id', $vars['invoiceid'])
            ->first();

        if (!$invoice) {
            return;
        }

        $userId = $invoice->userid;

        if (isClientExcluded($userId, $settings['excluded_groups'] ?? '')) {
            return;
        }

        $points = (int)($settings['points_per_invoice'] ?? 0);
        if ($points <= 0) {
            return;
        }

        awardPoints($userId, $points, "Points awarded for Invoice #{$vars['invoiceid']} payment");

        logActivity("Loyalty Points: Awarded {$points} points for Invoice #{$vars['invoiceid']} payment", $userId);
    } catch (Exception $e) {
        logModuleCall('loyalty_points', 'invoice_paid_hook', $vars, $e->getMessage());
        logActivity("Loyalty Points: Failed to award points for invoice payment: " . $e->getMessage(), $userId ?? null);
    }
}

function loyalty_points_order_created($vars)
{
    try {
        $settings = getModuleSettings();

        if (empty($settings)) {
            return;
        }

        if (isClientExcluded($vars['userid'], $settings['excluded_groups'] ?? '')) {
            return;
        }

        if ($settings['exclude_free_orders'] === 'yes' && $vars['amount'] <= 0) {
            return;
        }

        $points = (int)($settings['points_per_order'] ?? 0);
        if ($points <= 0) {
            return;
        }

        awardPoints($vars['userid'], $points, "Points awarded for Order #{$vars['orderid']}");

        logActivity("Loyalty Points: Awarded {$points} points for Order #{$vars['orderid']}", $vars['userid']);
    } catch (Exception $e) {
        logModuleCall('loyalty_points', 'order_created_hook', $vars, $e->getMessage());
        logActivity("Loyalty Points: Failed to award points for new order: " . $e->getMessage(), $vars['userid'] ?? null);
    }
}

function loyalty_points_affiliate_signup($vars)
{
    try {
        $settings = getModuleSettings();

        if (empty($settings)) {
            return;
        }

        if (isClientExcluded($vars['userid'], $settings['excluded_groups'] ?? '')) {
            return;
        }

        $points = (int)($settings['points_for_affiliate'] ?? 0);
        if ($points <= 0) {
            return;
        }

        awardPoints($vars['userid'], $points, "Points awarded for affiliate program registration");

        logActivity("Loyalty Points: Awarded {$points} points for affiliate signup (Affiliate ID: {$vars['affid']})", $vars['userid']);
    } catch (Exception $e) {
        logModuleCall('loyalty_points', 'affiliate_signup_hook', $vars, $e->getMessage());
        logActivity("Loyalty Points: Failed to award points for affiliate signup: " . $e->getMessage(), $vars['userid'] ?? null);
    }
}

function loyalty_points_daily_cron($vars)
{
    try {
        $settings = getModuleSettings();

        if (empty($settings)) {
            return;
        }

        $points = (int)($settings['points_anniversary'] ?? 0);
        if ($points <= 0) {
            return;
        }

        $today = date('m-d');
        $clients = Capsule::table('tblclients')
            ->whereRaw('DATE_FORMAT(datecreated, \"%m-%d\") = ?', [$today])
            ->get();

        foreach ($clients as $client) {
            if (isClientExcluded($client->id, $settings['excluded_groups'] ?? '')) {
                continue;
            }

            $created = strtotime($client->datecreated);
            $years = date('Y') - date('Y', $created);

            // Only award points if a full anniversary has passed (N >= 1)
            if ($years > 0 && date('Y-m-d', $created) == date('Y-m-d', strtotime("-{$years} years"))) {
                awardPoints($client->id, $points, "Points awarded for {$years} year account anniversary");
                logActivity("Loyalty Points: Awarded {$points} points for {$years} year account anniversary", $client->id);
            }
        }
    } catch (Exception $e) {
        logModuleCall('loyalty_points', 'daily_cron_hook', $vars, $e->getMessage());
        logActivity("Loyalty Points: Failed to process anniversary points: " . $e->getMessage());
    }
}

function loyalty_points_service_suspended($vars)
{
    try {
        $settings = getModuleSettings();
        if (!$settings || !$settings['points_per_invoice']) {
            return;
        }

        if (isClientExcluded($vars['userid'], $settings['excluded_groups'])) {
            return;
        }

        $points = -$settings['suspension_penalty'];

        awardPoints($vars['userid'], $points, "Points deducted for Service #{$vars['serviceid']} suspension");

        logActivity("Deducted {$points} points for Service #{$vars['serviceid']} suspension", $vars['userid']);
    } catch (Exception $e) {
        logActivity("Failed to deduct points for service suspension: " . $e->getMessage(), $vars['userid']);
    }
}

function getModuleSettings()
{
    $settings = [];
    $result = Capsule::table('tbladdonmodules')
        ->where('module', 'loyalty_points')
        ->get();

    foreach ($result as $row) {
        $settings[$row->setting] = $row->value;
    }

    return $settings;
}

function isClientExcluded($clientId, $excludedGroups)
{
    if (empty($excludedGroups)) {
        return false;
    }

    $excludedGroups = explode(',', $excludedGroups);

    $clientGroup = Capsule::table('tblclients')
        ->where('id', $clientId)
        ->value('groupid');

    return in_array($clientGroup, $excludedGroups);
}

function awardPoints($clientId, $points, $description)
{
    try {
        Capsule::beginTransaction();

        Capsule::table('mod_loyalty_points_log')->insert([
            'client_id' => $clientId,
            'points' => $points,
            'description' => $description,
            'date' => date('Y-m-d H:i:s'),
            'expiry_date' => null
        ]);

        Capsule::table('mod_loyalty_points')
            ->updateOrInsert(
                ['client_id' => $clientId],
                [
                    'points' => Capsule::raw("COALESCE(points, 0) + {$points}"),
                    'last_updated' => date('Y-m-d H:i:s')
                ]
            );

        Capsule::commit();
        return true;
    } catch (Exception $e) {
        Capsule::rollBack();
        throw $e;
    }
}

use WHMCS\View\Menu\Item as MenuItem;

function loyalty_points_client_area_nav($primaryNavbar)
{
    try {
        $currentUser = new \WHMCS\Authentication\CurrentUser;
        if (!$currentUser->client()) {
            return;
        }

        $moduleActive = Capsule::table('tbladdonmodules')
            ->where('module', 'loyalty_points')
            ->where('setting', 'version')
            ->exists();

        if (!$moduleActive) {
            return;
        }

        $primaryNavbar->addChild(
            'loyaltyPoints',
            [
                'name' => 'Loyalty Points',
                'label' => 'Loyalty Points',
                'uri' => 'index.php?m=loyalty_points',
                'order' => 999,
            ]
        );
    } catch (Exception $e) {
        logModuleCall('loyalty_points', 'client_nav_hook', [], $e->getMessage());
    }
}

add_hook('AddInvoicePayment', 1, 'loyalty_points_invoice_paid');
add_hook('AcceptOrder', 1, 'loyalty_points_order_created');
add_hook('AffiliateActivation', 1, 'loyalty_points_affiliate_signup');
add_hook('DailyCronJob', 1, 'loyalty_points_daily_cron');
add_hook('AfterModuleSuspend', 1, 'loyalty_points_service_suspended');
add_hook('ClientAreaPrimaryNavbar', 1, 'loyalty_points_client_area_nav');
