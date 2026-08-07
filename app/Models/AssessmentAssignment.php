<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentAssignment extends Model
{
    protected $table = 'lsp_assessment_assignments';
    protected $guarded = ['id'];
    protected $casts = [
        'due_at' => 'datetime',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'revision_requested_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function process() { return $this->belongsTo(AssessmentProcess::class, 'process_id'); }
    public function version() { return $this->belongsTo(AssessmentFormVersion::class, 'form_version_id'); }
    public function assignee() { return $this->belongsTo(LspUser::class, 'assigned_to', 'kdlsp_user'); }
    public function answers() { return $this->hasMany(AssessmentAnswer::class, 'assignment_id'); }
    public function reviews() { return $this->hasMany(AssessmentReview::class, 'assignment_id'); }
    public function decision() { return $this->hasOne(AssessmentDecision::class, 'assignment_id'); }
}
