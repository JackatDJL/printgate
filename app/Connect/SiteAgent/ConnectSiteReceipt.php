<?php

namespace App\Connect\SiteAgent;

use Illuminate\Database\Eloquent\Model;

class ConnectSiteReceipt extends Model
{
    protected $table = 'connect_site_agent_receipts';

    protected $fillable = ['state', 'backend_job_id'];
}
