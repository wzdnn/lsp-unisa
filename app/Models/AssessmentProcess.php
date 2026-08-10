<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentProcess extends Model
{
    protected $table = 'lsp_assessment_processes';
    protected $guarded = ['id'];
    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'decided_at' => 'datetime',
        'result_published_at' => 'datetime',
    ];

    public function asesi() { return $this->belongsTo(LspUser::class, 'asesi_id', 'kdlsp_user'); }
    public function assessor() { return $this->belongsTo(LspUser::class, 'assessor_id', 'kdlsp_user'); }
    public function periodeSkema() { return $this->belongsTo(LspPeriodeSkema::class, 'kdlsp_periode_skema', 'kdlsp_periode_skema'); }
    public function assignments() { return $this->hasMany(AssessmentAssignment::class, 'process_id'); }
}
