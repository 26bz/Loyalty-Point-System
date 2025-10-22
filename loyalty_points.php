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
use WHMCS\Exception\Module\InvalidConfiguration;

function getCurrencyOptions()
{
    try {
        $currencies = Capsule::table('tblcurrencies')
            ->select(['id', 'code', 'prefix'])
            ->get();

        $options = [];
        foreach ($currencies as $currency) {
            $options[$currency->id] = $currency->code . ' (' . $currency->prefix . ')';
        }

        return $options;
    } catch (Exception $e) {
        logModuleCall('loyalty_points', 'getCurrencyOptions', [], $e->getMessage());
        return ['1' => 'USD ($)'];
    }
}

require_once __DIR__ . '/admin.php';
require_once __DIR__ . '/client.php';

function loyalty_points_config()
{
    return [
        'name' => 'Loyalty Points System',
        'description' => 'Advanced loyalty points system',
        'version' => '2.4.0',
        'author' => '<a href="https://26bz.online" target="_blank">26BZ</a>',
        'premium' => true,
        'language' => 'english',
        'fields' => [
            'base_currency' => [
                'FriendlyName' => 'Base Currency',
                'Type' => 'dropdown',
                'Options' => getCurrencyOptions(),
                'Description' => 'Reference currency for point value calculations',
                'Default' => '1'
            ],
            'conversion_rate' => [
                'FriendlyName' => 'Point Value',
                'Type' => 'text',
                'Size' => '10',
                'Default' => '0.01',
                'Description' => 'Monetary value of each point in base currency'
            ],
            'points_per_invoice' => [
                'FriendlyName' => 'Invoice Payment Points',
                'Type' => 'text',
                'Size' => '10',
                'Default' => '10',
                'Description' => 'Points awarded for paying invoices on time'
            ],
            'points_per_order' => [
                'FriendlyName' => 'New Order Points',
                'Type' => 'text',
                'Size' => '10',
                'Default' => '20',
                'Description' => 'Points awarded for placing new orders'
            ],
            'points_for_affiliate' => [
                'FriendlyName' => 'Affiliate Signup Points',
                'Type' => 'text',
                'Size' => '10',
                'Default' => '10',
                'Description' => 'Points awarded for joining affiliate program'
            ],
            'points_anniversary' => [
                'FriendlyName' => 'Anniversary Bonus',
                'Type' => 'text',
                'Size' => '10',
                'Default' => '100',
                'Description' => 'Annual bonus points on account anniversary'
            ],
            'points_reminder_deduction' => [
                'FriendlyName' => 'Late Payment Penalty',
                'Type' => 'text',
                'Size' => '10',
                'Default' => '5',
                'Description' => 'Points deducted for payment reminders'
            ],
            'suspension_penalty' => [
                'FriendlyName' => 'Suspension Penalty',
                'Type' => 'text',
                'Size' => '10',
                'Default' => '0',
                'Description' => 'Points deducted for service suspension (0 to disable)'
            ],
            'min_redemption_points' => [
                'FriendlyName' => 'Minimum Redemption',
                'Type' => 'text',
                'Size' => '10',
                'Default' => '100',
                'Description' => 'Minimum points required for redemption'
            ],
            'points_expiry_days' => [
                'FriendlyName' => 'Points Expiry (Days)',
                'Type' => 'text',
                'Size' => '10',
                'Default' => '365',
                'Description' => 'Days until points expire (0 for no expiry)'
            ],
            'excluded_groups' => [
                'FriendlyName' => 'Excluded Client Groups',
                'Type' => 'text',
                'Size' => '50',
                'Default' => '',
                'Description' => 'Comma-separated client group IDs to exclude'
            ],
            'exclude_free_orders' => [
                'FriendlyName' => 'Exclude Free Orders',
                'Type' => 'yesno',
                'Default' => 'yes',
                'Description' => 'Skip points for zero-value orders'
            ],
            'log_points_activity' => [
                'FriendlyName' => 'Activity Logging',
                'Type' => 'yesno',
                'Default' => 'yes',
                'Description' => 'Log points transactions to activity log'
            ]
        ]
    ];
}

function create_redemption_attempts_table()
{
    Capsule::schema()->create('mod_loyalty_points_redemption_attempts', function ($table) {
        $table->increments('id');
        $table->integer('client_id');
        $table->integer('points');
        $table->enum('status', ['pending', 'completed', 'failed', 'flagged', 'approved', 'rejected']);
        $table->string('ip_address', 45)->nullable();
        $table->text('user_agent')->nullable();
        $table->timestamp('timestamp');
        $table->text('admin_notes')->nullable();
        $table->integer('admin_id')->nullable();
        $table->timestamp('reviewed_at')->nullable();

        $table->index('client_id');
        $table->index('status');
        $table->index('timestamp');
    });
}



function loyalty_points_activate()
{
    try {
        if (!Capsule::schema()->hasTable('mod_loyalty_points')) {
            Capsule::schema()->create('mod_loyalty_points', function ($table) {
                $table->increments('id');
                $table->integer('client_id')->unique();
                $table->integer('points')->default(0);
                $table->timestamp('last_updated')->nullable();

                // Foreign key 
                $table->index('client_id');
            });
        }

        if (!Capsule::schema()->hasTable('mod_loyalty_points_log')) {
            Capsule::schema()->create('mod_loyalty_points_log', function ($table) {
                $table->increments('id');
                $table->integer('client_id');
                $table->integer('points');
                $table->text('description');
                $table->datetime('date');
                $table->datetime('expiry_date')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();

                // Performance indexes
                $table->index('client_id');
                $table->index('date');
                $table->index('expiry_date');
            });
        }

        if (!Capsule::schema()->hasTable('mod_loyalty_points_redemption_attempts')) {
            create_redemption_attempts_table();
        }

        syncPointsFromLogs();

        logActivity('Loyalty Points System: Module activated successfully');

        return [
            'status' => 'success',
            'description' => 'Loyalty Points module activated successfully. All database tables created and system initialized.'
        ];
    } catch (Exception $e) {
        logModuleCall(
            'loyalty_points',
            'activate',
            [],
            $e->getMessage(),
            null,
            []
        );

        return [
            'status' => 'error',
            'description' => 'Activation failed: ' . $e->getMessage()
        ];
    }
}

function syncPointsFromLogs()
{
    try {
        $clientsWithPoints = Capsule::table('mod_loyalty_points_log')
            ->select('client_id')
            ->groupBy('client_id')
            ->get();

        foreach ($clientsWithPoints as $client) {
            $totalPoints = Capsule::table('mod_loyalty_points_log')
                ->where('client_id', $client->client_id)
                ->sum('points');

            Capsule::table('mod_loyalty_points')
                ->updateOrInsert(
                    ['client_id' => $client->client_id],
                    [
                        'points' => $totalPoints,
                        'last_updated' => date('Y-m-d H:i:s')
                    ]
                );
        }
    } catch (\Exception $e) {
        logActivity("Loyalty Points Sync Error: " . $e->getMessage());
    }
}

function loyalty_points_output($vars)
{
    return loyalty_points_admin_output($vars);
}


function loyalty_points_clientarea($vars)
{
    return loyalty_points_client_output($vars);
}

function loyalty_points_deactivate()
{
    // We don't drop tables on deactivation to preserve points data
    // This allows admins to temporarily disable the module without losing client points

    try {
        // nothing <3
        return [
            'status' => 'success',
            'description' => 'Loyalty Points module deactivated successfully. Note: Points data has been preserved and will be available upon reactivation.'
        ];
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'description' => 'Error during deactivation: ' . $e->getMessage()
        ];
    }
}

function loyalty_points_upgrade($vars)
{
    $currentVersion = $vars['version'];

    try {
        // Upgrade to version 2.0
        // Added expiry_date to points log
        if ($currentVersion < 2.0) {
            if (!Capsule::schema()->hasColumn('mod_loyalty_points_log', 'expiry_date')) {
                Capsule::schema()->table('mod_loyalty_points_log', function ($table) {
                    $table->datetime('expiry_date')->nullable();
                });
            }
            logActivity("Loyalty Points module upgraded to version 2.0");
        }

        // Upgrade to version 2.1
        // Added IP and user agent tracking
        if ($currentVersion < 2.1) {
            if (!Capsule::schema()->hasColumn('mod_loyalty_points_log', 'ip_address')) {
                Capsule::schema()->table('mod_loyalty_points_log', function ($table) {
                    $table->string('ip_address')->nullable();
                });
            }
            if (!Capsule::schema()->hasColumn('mod_loyalty_points_log', 'user_agent')) {
                Capsule::schema()->table('mod_loyalty_points_log', function ($table) {
                    $table->string('user_agent')->nullable();
                });
            }
            logActivity("Loyalty Points module upgraded to version 2.1");
        }

        // Upgrade to version 2.2
        // Added redemption attempts tracking and security features
        if ($currentVersion < 2.2) {
            if (!Capsule::schema()->hasTable('mod_loyalty_points_redemption_attempts')) {
                create_redemption_attempts_table();
            }

            $failedRedemptions = Capsule::table('mod_loyalty_points_log')
                ->where('points', '<', 0)
                ->where('description', 'LIKE', '%failed%')
                ->get();

            foreach ($failedRedemptions as $redemption) {
                Capsule::table('mod_loyalty_points_redemption_attempts')->insert([
                    'client_id' => $redemption->client_id,
                    'points' => abs($redemption->points),
                    'status' => 'failed',
                    'timestamp' => $redemption->date,
                    'ip_address' => $redemption->ip_address,
                    'user_agent' => $redemption->user_agent
                ]);
            }

            logActivity("Loyalty Points module upgraded to version 2.2");
        }

        // Upgrade to version 2.4
        // Added admin review workflow for flagged redemptions
        if ($currentVersion < 2.4) {
            if (Capsule::schema()->hasTable('mod_loyalty_points_redemption_attempts')) {
                $schema = Capsule::schema();

                // Add new columns for admin review workflow
                if (!$schema->hasColumn('mod_loyalty_points_redemption_attempts', 'admin_notes')) {
                    $schema->table('mod_loyalty_points_redemption_attempts', function ($table) {
                        $table->text('admin_notes')->nullable();
                    });
                }

                if (!$schema->hasColumn('mod_loyalty_points_redemption_attempts', 'admin_id')) {
                    $schema->table('mod_loyalty_points_redemption_attempts', function ($table) {
                        $table->integer('admin_id')->nullable();
                    });
                }

                if (!$schema->hasColumn('mod_loyalty_points_redemption_attempts', 'reviewed_at')) {
                    $schema->table('mod_loyalty_points_redemption_attempts', function ($table) {
                        $table->timestamp('reviewed_at')->nullable();
                    });
                }

                // Update status enum using raw SQL to avoid Doctrine DBAL requirement
                try {
                    Capsule::statement("ALTER TABLE mod_loyalty_points_redemption_attempts MODIFY COLUMN status ENUM('pending', 'completed', 'failed', 'flagged', 'approved', 'rejected') NOT NULL");
                } catch (Exception $e) {
                    // If enum update fails, log it but don't stop the upgrade
                    logModuleCall('loyalty_points', 'upgrade_enum', [], $e->getMessage(), null, []);
                }
            }

            logActivity("Loyalty Points module upgraded to version 2.4 - Admin review workflow added");
        }

        syncPointsFromLogs();

        return [
            'status' => 'success',
            'description' => 'Loyalty Points module upgraded successfully to version ' . $vars['version']
        ];
    } catch (Exception $e) {
        logModuleCall(
            'loyalty_points',
            'upgrade',
            [
                'from_version' => $currentVersion,
                'to_version' => $vars['version']
            ],
            $e->getMessage(),
            null,
            []
        );
        return [
            'status' => 'error',
            'description' => 'Error during upgrade: ' . $e->getMessage()
        ];
    }
}
