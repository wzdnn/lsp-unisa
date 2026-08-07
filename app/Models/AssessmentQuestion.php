<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentQuestion extends Model
{
    protected $table = 'lsp_assessment_questions';
    protected $guarded = ['id'];
    protected $casts = ['is_required' => 'boolean', 'options' => 'array', 'settings' => 'array'];
}
