<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentSection extends Model
{
    protected $table = 'lsp_assessment_sections';
    protected $guarded = ['id'];

    public function questions() { return $this->hasMany(AssessmentQuestion::class, 'section_id')->orderBy('sort_order'); }
}
