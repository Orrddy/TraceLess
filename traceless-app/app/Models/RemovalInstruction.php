<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RemovalInstruction extends Model
{
    protected $fillable = [
        'source_identifier',
        'title',
        'step_1',
        'step_2',
        'step_3',
        'step_4',
        'direct_opt_out_link',
        'typical_turnaround',
    ];
}
