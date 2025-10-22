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
use WHMCS\Authentication\CurrentUser;

function loyalty_points_admin_output($vars)
{
    $currentUser = new CurrentUser();
    if (!$currentUser->isAuthenticatedAdmin()) {
        echo '<div class="alert alert-danger">Access Denied</div>';
        return;
    }

    $modulelink = $vars['modulelink'];
    $config = [
        'points_per_invoice' => (int) ($vars['points_per_invoice'] ?? 50),
        'points_for_affiliate' => (int) ($vars['points_for_affiliate'] ?? 100),
        'points_anniversary' => (int) ($vars['points_anniversary'] ?? 100),
        'points_per_order' => (int) ($vars['points_per_order'] ?? 20),
        'min_redemption_points' => (int) ($vars['min_redemption_points'] ?? 100),
        'points_expiry_days' => (int) ($vars['points_expiry_days'] ?? 365),
        'conversion_rate' => (float) ($vars['conversion_rate'] ?? 0.01),
        'base_currency' => $vars['base_currency'] ?? 1,
        'suspension_penalty' => (int) ($vars['suspension_penalty'] ?? 0),
        'exclude_free_orders' => $vars['exclude_free_orders'] ?? 'no'
    ];

    try {
        if (!Capsule::schema()->hasTable('mod_loyalty_points_redemption_attempts')) {
            create_redemption_attempts_table();
        }
    } catch (Exception $e) {
        echo '<div class="alert alert-warning">Database table check: ' . $e->getMessage() . '</div>';
    }

    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'adjust_points':
                handleAdjustment($_POST);
                break;
            case 'approve_redemption':
                handleRedemptionApproval($_POST, $config);
                break;
            case 'reject_redemption':
                handleRedemptionRejection($_POST);
                break;
        }
    }

    $flaggedCount = Capsule::table('mod_loyalty_points_redemption_attempts')
        ->where('mod_loyalty_points_redemption_attempts.status', 'flagged')
        ->count();

    if ($flaggedCount > 0) {
        echo '<div class="alert alert-warning">';
        echo '<strong><i class="fa fa-exclamation-triangle"></i> ' . $flaggedCount . ' redemption' . ($flaggedCount > 1 ? 's' : '') . ' pending review</strong> - ';
        echo 'There ' . ($flaggedCount > 1 ? 'are' : 'is') . ' ' . $flaggedCount . ' flagged redemption' . ($flaggedCount > 1 ? 's' : '') . ' that require' . ($flaggedCount > 1 ? '' : 's') . ' your review.';
        echo '</div>';
    }

    displayOverview($config);
    displayFlaggedRedemptions($modulelink, $config);
    displayAdjustmentForm($modulelink);
    displayClientsTable($modulelink, $config);
}

function handleAdjustment($post)
{
    $clientId = (int) $post['client_id'];
    $points = (int) $post['points'];
    $description = trim($post['description']);

    if (!$clientId || !$points || !$description) {
        echo '<div class="alert alert-danger">Invalid input</div>';
        return;
    }

    try {
        Capsule::beginTransaction();

        $current = Capsule::table('mod_loyalty_points')
            ->where('client_id', $clientId)
            ->value('points') ?? 0;

        $newPoints = max(0, $current + $points);

        Capsule::table('mod_loyalty_points')
            ->updateOrInsert(['client_id' => $clientId], ['points' => $newPoints]);

        Capsule::table('mod_loyalty_points_log')->insert([
            'client_id' => $clientId,
            'points' => $points,
            'description' => $description,
            'date' => date('Y-m-d H:i:s'),
            'ip_address' => $_SERVER['REMOTE_ADDR']
        ]);

        Capsule::commit();
        echo '<div class="alert alert-success">Points adjusted successfully</div>';
    } catch (Exception $e) {
        Capsule::rollback();
        echo '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
    }
}

function displayOverview($config)
{
    $stats = [
        'issued' => Capsule::table('mod_loyalty_points_log')->where('points', '>', 0)->sum('points') ?? 0,
        'redeemed' => abs(Capsule::table('mod_loyalty_points_log')->where('points', '<', 0)->sum('points')) ?? 0,
        'active' => Capsule::table('mod_loyalty_points')->where('points', '>', 0)->count() ?? 0,
        'monthly' => Capsule::table('mod_loyalty_points_log')->where('date', '>=', date('Y-m-01'))->count() ?? 0
    ];

    $currency = getCurrency(null, $config['base_currency']);

    echo '<div class="row">';
    echo '<div class="col-md-6">';
    echo '<div class="panel panel-default">';
    echo '<div class="panel-heading">Configuration</div>';
    echo '<div class="panel-body">';
    echo '<div class="row">';
    echo '<div class="col-sm-6">';
    echo '<strong>Invoice Payment:</strong> ' . $config['points_per_invoice'] . ' pts<br>';
    echo '<strong>New Orders:</strong> ' . $config['points_per_order'] . ' pts<br>';
    echo '<strong>Affiliate Signup:</strong> ' . $config['points_for_affiliate'] . ' pts<br>';
    echo '<strong>Anniversary:</strong> ' . $config['points_anniversary'] . ' pts';
    echo '</div>';
    echo '<div class="col-sm-6">';
    echo '<strong>Base Currency:</strong> ' . $currency['code'] . '<br>';
    echo '<strong>Min Redemption:</strong> ' . $config['min_redemption_points'] . ' pts<br>';
    echo '<strong>Point Value:</strong> ' . number_format($config['conversion_rate'], 4) . '<br>';
    echo '<strong>Expiry:</strong> ' . ($config['points_expiry_days'] ? $config['points_expiry_days'] . ' days' : 'Never');
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    echo '<div class="col-md-6">';
    echo '<div class="panel panel-default">';
    echo '<div class="panel-heading">Statistics</div>';
    echo '<div class="panel-body">';
    echo '<div class="row">';
    echo '<div class="col-sm-6">';
    echo '<strong>Total Issued:</strong> ' . number_format($stats['issued']) . '<br>';
    echo '<strong>Total Redeemed:</strong> ' . number_format($stats['redeemed']) . '<br>';
    echo '</div>';
    echo '<div class="col-sm-6">';
    echo '<strong>Active Clients:</strong> ' . number_format($stats['active']) . '<br>';
    echo '<strong>Monthly Activity:</strong> ' . number_format($stats['monthly']) . '<br>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
}

function displayAdjustmentForm($modulelink)
{
    $clients = Capsule::table('tblclients')
        ->select(['id', 'firstname', 'lastname', 'companyname', 'email'])
        ->orderBy('firstname')
        ->get();

    echo '<div class="panel panel-default">';
    echo '<div class="panel-heading">Adjust Client Points</div>';
    echo '<div class="panel-body">';
    echo '<form method="post" action="' . $modulelink . '" class="form-inline">';
    echo '<input type="hidden" name="action" value="adjust_points">';

    echo '<div class="form-group" style="margin-right: 10px;">';
    echo '<select name="client_id" class="form-control" style="width: 300px;" required>';
    echo '<option value="">Select Client...</option>';

    foreach ($clients as $client) {
        $name = trim($client->firstname . ' ' . $client->lastname);
        if ($client->companyname) $name .= " ({$client->companyname})";
        echo '<option value="' . $client->id . '">' . htmlspecialchars($name . ' - ' . $client->email) . '</option>';
    }

    echo '</select>';
    echo '</div>';

    echo '<div class="form-group" style="margin-right: 10px;">';
    echo '<input type="number" name="points" class="form-control" style="width: 100px;" placeholder="+/-" required>';
    echo '</div>';

    echo '<div class="form-group" style="margin-right: 10px;">';
    echo '<input type="text" name="description" class="form-control" style="width: 200px;" placeholder="Description" required>';
    echo '</div>';

    echo '<button type="submit" class="btn btn-primary">Update Points</button>';

    echo '</form>';
    echo '</div>';
    echo '</div>';
}

function displayFlaggedRedemptions($modulelink, $config)
{
    $flaggedRedemptions = Capsule::table('mod_loyalty_points_redemption_attempts')
        ->where('mod_loyalty_points_redemption_attempts.status', 'flagged')
        ->join('tblclients', 'mod_loyalty_points_redemption_attempts.client_id', '=', 'tblclients.id')
        ->select(
            'mod_loyalty_points_redemption_attempts.*',
            'tblclients.firstname',
            'tblclients.lastname',
            'tblclients.companyname',
            'tblclients.email'
        )
        ->orderBy('mod_loyalty_points_redemption_attempts.timestamp', 'desc')
        ->get();

    if (count($flaggedRedemptions) > 0) {
        echo '<div class="panel panel-warning">';
        echo '<div class="panel-heading"><i class="fa fa-exclamation-triangle"></i> Flagged Redemptions Pending Review</div>';
        echo '<div class="panel-body">';
        echo '<div class="table-responsive">';
        echo '<table class="table table-striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Client</th>';
        echo '<th>Points</th>';
        echo '<th>Value</th>';
        echo '<th>Date</th>';
        echo '<th>IP Address</th>';
        echo '<th>Actions</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($flaggedRedemptions as $redemption) {
            $name = trim($redemption->firstname . ' ' . $redemption->lastname);
            if ($redemption->companyname) {
                $name .= " ({$redemption->companyname})";
            }

            $clientCurrency = getCurrency($redemption->client_id);
            $baseCurrency = getCurrency(null, $config['base_currency']);
            $value = convertCurrency($redemption->points * $config['conversion_rate'], $baseCurrency['id'], $clientCurrency['id']);
            $formattedValue = formatCurrency($value, $clientCurrency['id']);

            echo '<tr>';
            echo '<td><a href="clientssummary.php?userid=' . $redemption->client_id . '">' . htmlspecialchars($name) . '</a><br>';
            echo '<small>' . htmlspecialchars($redemption->email) . '</small></td>';
            echo '<td>' . number_format($redemption->points) . '</td>';
            echo '<td>' . $formattedValue . '</td>';
            echo '<td>' . date('M j, Y H:i', strtotime($redemption->timestamp)) . '</td>';
            echo '<td>' . htmlspecialchars($redemption->ip_address) . '</td>';
            echo '<td>';
            echo '<form method="post" style="display:inline-block;margin-right:5px;">';
            echo '<input type="hidden" name="action" value="approve_redemption">';
            echo '<input type="hidden" name="redemption_id" value="' . $redemption->id . '">';
            echo '<input type="hidden" name="client_id" value="' . $redemption->client_id . '">';
            echo '<input type="hidden" name="points" value="' . $redemption->points . '">';
            echo generate_token();
            echo '<button type="submit" class="btn btn-success btn-sm"><i class="fa fa-check"></i> Approve</button>';
            echo '</form>';

            echo '<form method="post" style="display:inline-block;">';
            echo '<input type="hidden" name="action" value="reject_redemption">';
            echo '<input type="hidden" name="redemption_id" value="' . $redemption->id . '">';
            echo '<input type="hidden" name="client_id" value="' . $redemption->client_id . '">';
            echo generate_token();
            echo '<button type="submit" class="btn btn-danger btn-sm"><i class="fa fa-times"></i> Reject</button>';
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
}

function handleRedemptionApproval($post, $config)
{
    if (!check_token()) {
        echo '<div class="alert alert-danger">Invalid security token</div>';
        return;
    }

    $redemptionId = (int) $post['redemption_id'];
    $clientId = (int) $post['client_id'];
    $points = (int) $post['points'];
    $adminId = (new CurrentUser())->admin()->id;

    try {
        Capsule::beginTransaction();

        $currentPoints = Capsule::table('mod_loyalty_points')
            ->where('client_id', $clientId)
            ->value('points') ?? 0;

        if ($currentPoints < $points) {
            throw new Exception("Client does not have enough points for this redemption");
        }

        Capsule::table('mod_loyalty_points_redemption_attempts')
            ->where('id', $redemptionId)
            ->update([
                'status' => 'approved',
                'admin_id' => $adminId,
                'admin_notes' => 'Approved after manual review',
                'reviewed_at' => date('Y-m-d H:i:s')
            ]);

        $newPoints = $currentPoints - $points;
        Capsule::table('mod_loyalty_points')
            ->where('client_id', $clientId)
            ->update(['points' => $newPoints]);

        $clientCurrency = getCurrency($clientId);
        $baseCurrency = getCurrency(null, $config['base_currency']);
        $value = convertCurrency($points * $config['conversion_rate'], $baseCurrency['id'], $clientCurrency['id']);
        $formattedValue = formatCurrency($value, $clientCurrency['id']);

        Capsule::table('mod_loyalty_points_log')->insert([
            'client_id' => $clientId,
            'points' => -$points,
            'description' => "Redeemed points for {$formattedValue} credit (Approved after review)",
            'date' => date('Y-m-d H:i:s'),
            'ip_address' => $_SERVER['REMOTE_ADDR']
        ]);

        Capsule::table('tblcredit')->insert([
            'clientid' => $clientId,
            'date' => date('Y-m-d'),
            'description' => "Loyalty Points Redemption: {$points} points",
            'amount' => $value
        ]);

        logActivity("Loyalty Points: {$points} points redeemed for {$formattedValue} credit by Admin ID: {$adminId}", $clientId);

        Capsule::commit();
        echo '<div class="alert alert-success">Redemption approved successfully</div>';
    } catch (Exception $e) {
        Capsule::rollback();
        echo '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
    }
}

function handleRedemptionRejection($post)
{
    if (!check_token()) {
        echo '<div class="alert alert-danger">Invalid security token</div>';
        return;
    }

    $redemptionId = (int) $post['redemption_id'];
    $clientId = (int) $post['client_id'];
    $adminId = (new CurrentUser())->admin()->id;

    try {
        Capsule::table('mod_loyalty_points_redemption_attempts')
            ->where('id', $redemptionId)
            ->update([
                'status' => 'rejected',
                'admin_id' => $adminId,
                'admin_notes' => 'Rejected after manual review',
                'reviewed_at' => date('Y-m-d H:i:s')
            ]);

        logActivity("Loyalty Points: Redemption attempt rejected by Admin ID: {$adminId}", $clientId);

        echo '<div class="alert alert-success">Redemption rejected successfully</div>';
    } catch (Exception $e) {
        echo '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
    }
}

function displayClientsTable($modulelink, $config)
{
    $clients = Capsule::table('tblclients as c')
        ->leftJoin('mod_loyalty_points as p', 'c.id', '=', 'p.client_id')
        ->select(['c.id', 'c.firstname', 'c.lastname', 'c.companyname', 'c.email', 'c.datecreated', 'p.points'])
        ->orderBy('p.points', 'desc')
        ->orderBy('c.firstname')
        ->limit(50)
        ->get();

    echo '<div class="panel panel-default">';
    echo '<div class="panel-heading">All Clients</div>';
    echo '<div class="panel-body">';
    echo '<style>';
    echo '.client-details { display: none; background-color: #f9f9f9; }';
    echo '.expand-btn { cursor: pointer; color: #337ab7; }';
    echo '.expand-btn:hover { color: #23527c; }';
    echo '</style>';
    echo '<div class="table-responsive">';
    echo '<table class="table table-striped" id="clientsTable">';
    echo '<thead><tr><th width="30"></th><th>Client</th><th>Email</th><th>Points</th><th>Value</th></tr></thead>';
    echo '<tbody>';

    foreach ($clients as $client) {
        $name = trim($client->firstname . ' ' . $client->lastname);
        if ($client->companyname) $name .= " ({$client->companyname})";

        $points = $client->points ?? 0;
        $currency = getCurrency($client->id);
        $value = formatCurrency($points * $config['conversion_rate'], $currency['id']);

        $history = Capsule::table('mod_loyalty_points_log')
            ->where('client_id', $client->id)
            ->orderBy('date', 'desc')
            ->limit(5)
            ->get();

        $rejectedRedemptions = Capsule::table('mod_loyalty_points_redemption_attempts')
            ->where('client_id', $client->id)
            ->where('mod_loyalty_points_redemption_attempts.status', 'rejected')
            ->orderBy('timestamp', 'desc')
            ->limit(3)
            ->get();

        echo '<tr>';
        echo '<td><i class="fa fa-plus expand-btn" onclick="toggleDetails(' . $client->id . ')"></i></td>';
        echo '<td><a href="clientssummary.php?userid=' . $client->id . '">' . htmlspecialchars($name) . '</a></td>';
        echo '<td>' . htmlspecialchars($client->email) . '</td>';
        echo '<td>' . number_format($points) . '</td>';
        echo '<td>' . $value . '</td>';
        echo '</tr>';

        // Expandable details 
        echo '<tr id="details-' . $client->id . '" class="client-details">';
        echo '<td colspan="5">';
        echo '<div style="padding: 15px;">';
        echo '<div class="row">';
        echo '<div class="col-md-12">';
        echo '<strong>Loyalty Points History:</strong> (Total: ' . number_format($points) . ' pts | Value: ' . $value . ')';
        echo '<div style="margin-top: 10px;">';

        $allHistory = [];

        foreach ($history as $log) {
            $allHistory[] = [
                'type' => 'log',
                'date' => $log->date,
                'timestamp' => strtotime($log->date),
                'points' => $log->points,
                'description' => $log->description
            ];
        }

        foreach ($rejectedRedemptions as $rejection) {
            $allHistory[] = [
                'type' => 'rejected',
                'date' => $rejection->timestamp,
                'timestamp' => strtotime($rejection->timestamp),
                'points' => $rejection->points,
                'description' => 'Redemption attempt rejected by admin',
                'admin_notes' => $rejection->admin_notes
            ];
        }

        usort($allHistory, function ($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });

        $allHistory = array_slice($allHistory, 0, 8);

        if (count($allHistory) > 0) {
            echo '<table class="table table-condensed table-striped" style="margin-bottom: 0;">';
            echo '<thead><tr><th width="100">Date</th><th width="80">Points</th><th>Action</th></tr></thead>';
            echo '<tbody>';
            foreach ($allHistory as $item) {
                $date = date('M j, Y', $item['timestamp']);

                if ($item['type'] === 'rejected') {
                    $pointsText = '<span class="text-muted">' . $item['points'] . '</span>';
                    $actionType = '<span class="label label-danger">Rejected</span>';
                    $description = $item['description'];
                    if (!empty($item['admin_notes'])) {
                        $description .= ' - ' . htmlspecialchars($item['admin_notes']);
                    }
                } else {
                    $pointsText = ($item['points'] > 0 ? '<span class="text-success">+' . $item['points'] . '</span>' : '<span class="text-danger">' . $item['points'] . '</span>');
                    if ($item['points'] > 0) {
                        $actionType = '<span class="label label-success">Received</span>';
                    } else {
                        $actionType = '<span class="label label-warning">Deducted</span>';
                    }
                    $description = htmlspecialchars($item['description']);
                }

                echo '<tr>';
                echo '<td>' . $date . '</td>';
                echo '<td>' . $pointsText . '</td>';
                echo '<td>' . $actionType . ' ' . $description . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<div class="alert alert-info" style="margin-bottom: 0;"><small>No loyalty points activity yet</small></div>';
        }
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '</div>';

    echo '<script>';
    echo 'function toggleDetails(clientId) {';
    echo '  var row = document.getElementById("details-" + clientId);';
    echo '  var icon = event.target;';
    echo '  if (row.style.display === "none" || row.style.display === "") {';
    echo '    row.style.display = "table-row";';
    echo '    icon.className = "fa fa-minus expand-btn";';
    echo '  } else {';
    echo '    row.style.display = "none";';
    echo '    icon.className = "fa fa-plus expand-btn";';
    echo '  }';
    echo '}';
    echo '</script>';

    echo '</div>';
    echo '</div>';
}
