{if $error}
<div class="alert alert-danger">
    {$error}
</div>
{/if}

{if $success}
<div class="alert alert-success">
    {$success}
</div>
{/if}

{if $login_required}
<div class="alert alert-info">
    Please <a href="clientarea.php">log in</a> to access your loyalty points.
</div>
{else}

<div class="row">
    <div class="col-md-4">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">Your Points Balance</h3>
            </div>
            <div class="panel-body text-center">
                <h2 class="text-primary">{$points_balance}</h2>
                <p class="text-muted">Available Points</p>
                {if $points_value > 0}
                <p class="text-success">Worth: {$points_value}</p>
                {/if}
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">Redeem Points</h3>
            </div>
            <div class="panel-body">
                <form method="post" action="{$modulelink}&a=redeem">
                    <input type="hidden" name="token" value="{$token}" />
                    <div class="form-group">
                        <label for="points_to_redeem">Points to Redeem:</label>
                        <input type="number" 
                               class="form-control" 
                               id="points_to_redeem" 
                               name="points_to_redeem" 
                               min="{$min_redemption}" 
                               max="{$points_balance}" 
                               placeholder="Enter points to redeem" 
                               required>
                        <small class="text-muted">Minimum: {$min_redemption} points | Maximum: {$points_balance} points</small>
                    </div>
                    <button type="submit" class="btn btn-success">
                        Redeem Points
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">How to Earn Points</h3>
            </div>
            <div class="panel-body">
                <ul class="list-unstyled">
                    {foreach from=$earning_methods item=method}
                    <li class="margin-bottom">
                        <span class="text-success">✓</span>
                        {$method->text}
                        <span class="label label-primary pull-right">{$method->points} pts</span>
                    </li>
                    {/foreach}
                </ul>
            </div>
        </div>
    </div>

    {if $history}
    <div class="col-md-6">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">Recent Transactions</h3>
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                    <table class="table table-condensed">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Action</th>
                                <th class="text-right">Points</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach from=$history item=transaction}
                            <tr>
                                <td><small>{$transaction.date|date_format:"%b %d, %Y"}</small></td>
                                <td>
                                    {$transaction.description}
                                    {if $transaction.type == 'rejected' && $transaction.admin_notes}
                                        <br><small class="text-muted">{$transaction.admin_notes}</small>
                                    {/if}
                                </td>
                                <td class="text-right">
                                    {if $transaction.type == 'rejected'}
                                        <span class="label label-warning">
                                            {$transaction.points} pts
                                        </span>
                                    {else}
                                        <span class="label {if $transaction.points > 0}label-success{else}label-danger{/if}">
                                            {if $transaction.points > 0}+{/if}{$transaction.points}
                                        </span>
                                    {/if}
                                </td>
                            </tr>
                            {/foreach}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    {/if}
</div>

{/if}
