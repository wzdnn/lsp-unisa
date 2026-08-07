<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentDecision extends Model
{
    protected $table = 'lsp_assessment_decisions';
    protected $guarded = ['id'];
    protected $casts = ['is_published' => 'boolean', 'decided_at' => 'datetime', 'published_at' => 'datetime'];
}
