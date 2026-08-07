<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentEvidence extends Model
{
    protected $table = 'lsp_assessment_evidences';
    protected $guarded = ['id'];

    public function answer() { return $this->belongsTo(AssessmentAnswer::class, 'answer_id'); }
}
