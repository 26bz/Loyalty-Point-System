# WHMCS Loyalty Points Module Documentation

## UPDATING?

Replace all files, goto your addons section in WHMCS and turn the addon off and on.

## Introduction

The WHMCS Loyalty Points Module enables providers to implement a rewards program for their clients. This documentation provides detailed instructions for installation, configuration, and troubleshooting.

## Technical Requirements

- WHMCS version 8.0 or higher (required for proper hook functionality)
- PHP 7.4 or higher
- MySQL 5.7 or higher / MariaDB 10.3 or higher
- Custom admin area pages must use the admin area template system

## Installation Process

### File Deployment

1. Extract the module package contents
2. Transfer the `loyalty_rewards` folder to your WHMCS `/addons` directory
3. Confirm the following directory structure:

```
modules/addons/loyalty_points/
```

### Module Implementation

1. Access your WHMCS administrative interface
2. Navigate to Setup → Addon Modules
3. Locate "Loyalty Points System"
4. Enable the module via the Activate button
5. Configure administrative access permissions

### Initial Setup

Configure essential parameters through the module settings:

- Points Allocation per Invoice
- Affiliate Registration Bonus Points
- Account Anniversary Rewards
- Point-to-Credit Conversion Rate
- Minimum Point Redemption Threshold
- Service Suspension Penalties
- Free Order Handling

To manage client points, access the Loyalty Points System through the addons menu. This interface enables point allocation, adjustment, and transaction history viewing.

### Implementation Verification

1. Access the client portal as a test user
2. Confirm the presence of the Loyalty Points navigation element
3. Validate point accrual functionality through the loyalty dashboard
4. Test point redemption with proper form handling

## Troubleshooting Guide

### Point Attribution Issues

1. Review client group exclusion settings
2. Confirm invoice amounts meet specified thresholds
3. Check rate limiting status
4. Verify transaction logs
5. Perform system cache clearance: Utilities → System → Clear Cache

### Database Management

Address database concerns by:

1. Accessing Utilities → System Health Status
2. Executing database repair procedures
3. Reviewing error logs for specific issues

### Access Management

Resolve administrator access issues:

1. Examine role configurations: Setup → Staff Management → Admin Roles
2. Verify Loyalty Points module permissions
3. Request administrator session refresh

## Advanced Settings

### Point Expiration Management

Configure expiration parameters:

1. Define point validity duration in days
2. Enter 0 to implement permanent points
3. Note: Changes affect future point assignments only

### Group Exclusion Configuration

Implement group restrictions:

1. Document target client group identifiers
2. Input group IDs as comma-separated values
3. Example: "3,4,7" excludes specified groups

### Rate Limiting Configuration

The module implements rate limiting for point redemptions:

1. 5-minute cooldown between redemptions
2. Prevents accidental double submissions
3. Configurable through code if needed

## Issue Reporting

Contact: https://26bz.online/discord
