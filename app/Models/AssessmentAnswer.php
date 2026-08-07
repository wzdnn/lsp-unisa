<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentAnswer extends Model
{
    protected $table = 'lsp_assessment_answers';
    protected $guarded = ['id'];
    protected $casts = ['answer_json' => 'array'];

    public function question() { return $this->belongsTo(AssessmentQuestion::class, 'question_id'); }
    public function evidences() { return $this->hasMany(AssessmentEvidence::class, 'answer_id'); }
}
